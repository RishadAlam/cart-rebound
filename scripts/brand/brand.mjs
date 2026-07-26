/**
 * Cart Rebound brand system — single source of truth.
 *
 * Every logo file in assets/brand/ and every wordpress.org master in
 * .wordpress-org/src/ is generated from the geometry and palette here, so the
 * mark can never drift between the plugin UI, the directory listing and the
 * animated icon.
 *
 * The mark is a shopping basket with the recovery cut out of it. One filled
 * silhouette, and the idea lives in the hole: a knockout cannot be read as two
 * things standing next to each other, which is exactly what sank the versions
 * before it.
 *
 * Three marks were rejected getting here. A bare fall-and-rise trajectory read
 * as a generic analytics chevron. A trolley beside an arrow was the Material
 * cart glyph next to the most-used symbol in SaaS — adjacency makes neither
 * shape ownable. A trolley whose handle became the arrow was better but still an
 * assembly of thin strokes covering 8% of the tile, so it read as an icon set
 * rather than a logo. This one fills 23%.
 */

import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import * as fontkit from 'fontkit';

const HERE = dirname(fileURLToPath(import.meta.url));
export const ROOT = resolve(HERE, '../..');

export const r = (n, p = 2) => Number(n.toFixed(p));

/* ------------------------------------------------------------------ palette */

/**
 * Anchored on the accent the admin UI already ships (--cr-accent, i.e.
 * oklch(0.55 0.16 264)); the old lavender artwork never matched it. Mint is the
 * payoff colour and is only ever used on the rising half of the trajectory, so
 * the one hue change in the whole system carries the one idea in the product.
 */
export const C = {
	ink950: '#0B1228',
	ink900: '#111D44',
	ink800: '#182762',
	indigo700: '#213D93',
	indigo600: '#2C51B6',
	indigo500: '#406BCE',
	indigo400: '#5D87E2',
	mint400: '#40DBAC',
	mint300: '#7DEBC7',
	mint200: '#B1F6DF',
	// Mint is unreadable on white, so light-background lockups swap in these
	// darker greens; teal600 clears 3:1 against paper as a graphic element.
	teal600: '#00A77B',
	teal700: '#008963',
	paper: '#F9FAFC',
	white: '#FFFFFF',
};

/* ------------------------------------------------------ curve mathematics */

const bezier = (pts, t) => {
	let p = pts;
	while (p.length > 1) {
		p = p
			.slice(1)
			.map((q, i) => [
				p[i][0] + (q[0] - p[i][0]) * t,
				p[i][1] + (q[1] - p[i][1]) * t,
			]);
	}
	return p[0];
};

/** Cumulative-arclength table, so points can be spaced by distance not by t. */
function arclength(pts, steps = 512) {
	const table = [{ s: 0, p: bezier(pts, 0) }];
	let total = 0;

	for (let i = 1; i <= steps; i++) {
		const p = bezier(pts, i / steps);
		const prev = table[i - 1].p;
		total += Math.hypot(p[0] - prev[0], p[1] - prev[1]);
		table.push({ s: total, p });
	}

	return {
		total,
		at: (frac) =>
			(table.find((row) => row.s >= frac * total) ?? table.at(-1)).p,
	};
}

const box = (items) => {
	const b = items.reduce(
		(acc, [x, y, pad]) => ({
			x0: Math.min(acc.x0, x - pad),
			y0: Math.min(acc.y0, y - pad),
			x1: Math.max(acc.x1, x + pad),
			y1: Math.max(acc.y1, y + pad),
		}),
		{ x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity }
	);
	return { x: b.x0, y: b.y0, w: b.x1 - b.x0, h: b.y1 - b.y0 };
};

const samples = (pts, pad, n = 96) =>
	Array.from({ length: n + 1 }, (_, i) => [...bezier(pts, i / n), pad]);

/* ----------------------------------------------------------------- geometry */

/**
 * Authored in a 512 grid; consumers re-fit it to whatever canvas they need.
 *
 * A basket, not a trolley. Fewer parts — no wheels, no push bar — so it is one
 * closed silhouette instead of an assembly, and it holds together at 20px where
 * a trolley falls apart.
 */
const BASKET = {
	rimY: 232,
	rimL: 92,
	rimR: 420,
	floorY: 412,
	/** How far each side draws in toward the floor. */
	taper: 46,
	/** Corner softening on the floor. */
	round: 26,
	/** Handle arc: span across the rim, and how high it lifts. */
	handleInset: 76,
	handleLift: 102,
	handleStroke: 30,
};

/**
 * The recovery, as a hole.
 *
 * Cut out of the basket rather than drawn beside it. Two earlier marks put a
 * cart and an arrow next to each other and neither shape earned anything from
 * the other; a knockout cannot be decomposed that way, because there is only one
 * shape and the idea lives in the gap it leaves.
 */
const ARROW = {
	cx: 256,
	tipY: 258,
	baseY: 392,
	shaftW: 60,
	headW: 138,
	/** Given, not derived: deriving it from headW collapsed the shaft to 7px. */
	headH: 82,
};

/**
 * Tapered tub with softened floor corners.
 *
 * @param {Object} b Basket spec.
 * @return {string} Path data.
 */
function tub(b) {
	const fl = b.rimL + b.taper;
	const fr = b.rimR - b.taper;

	return (
		`M${b.rimL} ${b.rimY}H${b.rimR}L${r(fr + b.round * 0.35)} ${r(b.floorY - b.round)}` +
		`Q${fr} ${b.floorY} ${r(fr - b.round)} ${b.floorY}` +
		`H${r(fl + b.round)}Q${fl} ${b.floorY} ${r(fl - b.round * 0.35)} ${r(b.floorY - b.round)}Z`
	);
}

/**
 * Handle arc, springing from the rim.
 *
 * @param {Object} b Basket spec.
 * @return {string} Path data.
 */
function handleArc(b) {
	const l = b.rimL + b.handleInset;
	const rr = b.rimR - b.handleInset;
	const top = b.rimY - b.handleLift;

	return `M${l} ${b.rimY}C${l} ${top} ${rr} ${top} ${rr} ${b.rimY}`;
}

/**
 * The arrow as a closed outline, for knocking out with `fill-rule="evenodd"`.
 *
 * @param {Object} a  Arrow spec.
 * @param {number} dy Vertical offset, so the animation can lift it.
 * @return {string} Path data.
 */
function arrowPath(a, dy = 0) {
	const { cx, shaftW, headW, headH } = a;
	const tip = a.tipY + dy;
	const base = a.baseY + dy;

	return (
		`M${r(cx - shaftW / 2)} ${r(base)}H${r(cx + shaftW / 2)}V${r(tip + headH)}` +
		`H${r(cx + headW / 2)}L${cx} ${r(tip)}L${r(cx - headW / 2)} ${r(tip + headH)}` +
		`H${r(cx - shaftW / 2)}Z`
	);
}

const TUB = tub(BASKET);
const HANDLE = handleArc(BASKET);

/**
 * The mark.
 *
 * One tier, used at every size. Earlier versions needed a reduced variant and
 * then a third cart-only variant because they were assemblies of thin strokes
 * that fell apart small. A single filled silhouette does not need reducing: the
 * only thing that changes between 256px and 20px is that the knockout stops
 * showing mint and starts showing whatever is behind it.
 */
export const MARK = {
	basket: TUB,
	handle: HANDLE,
	handleStroke: BASKET.handleStroke,
	arrow: arrowPath(ARROW),
	/** For the animation, which lifts the knockout out of the basket. */
	arrowAt: (dy) => arrowPath(ARROW, dy),
	arrowTravel: ARROW.baseY - ARROW.tipY,
	/** Plate behind the knockout, so the hole reads as the recovery showing through. */
	plate: {
		x: BASKET.rimL,
		y: BASKET.rimY,
		w: BASKET.rimR - BASKET.rimL,
		h: BASKET.floorY - BASKET.rimY,
	},
	ground: { y: BASKET.floorY + 26, x1: 108, x2: 404 },
	box: box([
		[BASKET.rimL, BASKET.rimY, 0],
		[BASKET.rimR, BASKET.floorY, 0],
		[
			(BASKET.rimL + BASKET.rimR) / 2,
			BASKET.rimY - BASKET.handleLift * 0.78,
			BASKET.handleStroke / 2,
		],
	]),
};

/**
 * Kept as aliases so the lockup, the mono marks and the admin menu keep working
 * without every call site having to know there is only one mark now.
 */
export const MARK_SM = MARK;
export const MARK_XS = MARK;

/* ------------------------------------------------------------- placement */

/** Fits a mark box into a square canvas, centred, at the given coverage. */
export function fit(markBox, canvas, coverage) {
	const s = (canvas * coverage) / Math.max(markBox.w, markBox.h);
	return {
		s,
		tx: (canvas - markBox.w * s) / 2 - markBox.x * s,
		ty: (canvas - markBox.h * s) / 2 - markBox.y * s,
	};
}

/** Fits a mark box to a target height at an arbitrary top-left origin. */
export function place(markBox, height, x, y) {
	const s = height / markBox.h;
	return { s, tx: x - markBox.x * s, ty: y - markBox.y * s };
}

export const xform = (t) =>
	`translate(${r(t.tx)} ${r(t.ty)}) scale(${r(t.s, 5)})`;

/* ----------------------------------------------------------------- wordmark */

const FONT = resolve(ROOT, '.wordpress-org/src/fonts/Archivo-VF.ttf');

/**
 * Archivo, not a geometric sans. The brand is a logistics tool that moves money
 * back into a store, and Archivo's grotesque, slightly squared shapes read like
 * a shipping manifest rather than another startup wordmark. Semi-expanded so
 * "Rebound" has room to feel like a push rather than a squeeze.
 */
let font;

function instance() {
	if (!font) {
		font = fontkit.openSync(FONT).getVariation({ wght: 700, wdth: 112 });
	}
	return font;
}

/**
 * Outlines a string to path data so no shipped SVG depends on a font being
 * installed. Baseline sits at y=0 and the glyphs run upward (negative y).
 *
 * @param {string} text     String to outline.
 * @param {number} size     Em size in user units.
 * @param {number} tracking Letter-spacing in em.
 * @return {{d: string, width: number, cap: number}} Outline and metrics.
 */
export function outline(text, size, tracking = 0) {
	const f = instance();
	const upem = f.unitsPerEm ?? 1000;
	const run = f.layout(text);
	const s = size / upem;
	let x = 0;
	const parts = [];

	run.glyphs.forEach((g, i) => {
		const d = g.path.scale(s, -s).translate(x, 0).toSVG();
		if (d) {
			parts.push(d);
		}
		x += run.positions[i].xAdvance * s + tracking * size;
	});

	return {
		d: parts.join(''),
		width: x - tracking * size,
		cap: (f.capHeight / upem) * size,
	};
}

/** Cap height as a fraction of em, for sizing text off its cap not its em. */
export const CAP_RATIO = 0.686;

/** Outlines text scaled so the result is exactly `width` user units wide. */
export function outlineToWidth(text, width, tracking = 0) {
	const probe = outline(text, 100, tracking);
	return outline(text, (width / probe.width) * 100, tracking);
}

/** Outlines at `size`, shrinking only if the result would exceed `max`. */
export function clampWidth(text, size, max, tracking = 0) {
	const o = outline(text, size, tracking);
	return o.width <= max ? o : outlineToWidth(text, max, tracking);
}

/* -------------------------------------------------------------------- parts */

/** Gradient axis for the plate behind the knockout: bottom-left to top-right. */
export const RISE_AXIS =
	`x1="${MARK.plate.x}" y1="${MARK.plate.y + MARK.plate.h}" ` +
	`x2="${MARK.plate.x + MARK.plate.w}" y2="${MARK.plate.y}"`;

/** Gradient defs shared by every colour variant of the mark. */
export function markDefs(id, { glow = false } = {}) {
	return (
		`<linearGradient id="${id}-rise" ${RISE_AXIS} gradientUnits="userSpaceOnUse">` +
		`<stop offset="0" stop-color="${C.mint400}"/>` +
		`<stop offset="1" stop-color="${C.mint200}"/>` +
		`</linearGradient>` +
		(glow
			? `<radialGradient id="${id}-glow" cx=".5" cy=".5" r=".5">` +
				`<stop offset="0" stop-color="${C.indigo500}" stop-opacity=".55"/>` +
				`<stop offset="1" stop-color="${C.indigo500}" stop-opacity="0"/>` +
				`</radialGradient>`
			: '')
	);
}

/** On paper mint has no contrast, so the plate runs indigo into teal instead. */
export const riseGradientLight = (id) =>
	`<linearGradient id="${id}-rise" ${RISE_AXIS} gradientUnits="userSpaceOnUse">` +
	`<stop offset="0" stop-color="${C.indigo600}"/>` +
	`<stop offset="1" stop-color="${C.teal600}"/>` +
	`</linearGradient>`;

/**
 * The mark in the 512 authoring space.
 *
 * @param {Object} o             Options.
 * @param {string} o.id          Unique prefix, for the clip path.
 * @param {string} o.cartColor   Paint for the basket and handle.
 * @param {string} o.riseColor   Paint showing through the knockout.
 * @param {number} o.arrowLift   0..1, how far the knockout has risen. The
 *                               animation uses it; static marks leave it at 1.
 * @param {string} o.groundColor Colour of the line the basket sits on.
 * @param {number} o.groundAlpha Opacity of that line.
 * @param {boolean} o.ground     Draw the ground line at all.
 * @return {string} SVG fragment.
 */
export function markBody({
	id = 'm',
	cartColor,
	riseColor,
	arrowLift = 1,
	groundColor,
	groundAlpha,
	ground = true,
}) {
	const p = MARK.plate;
	const dy = r((1 - arrowLift) * MARK.arrowTravel);

	return (
		`<g stroke-linecap="round" stroke-linejoin="round">` +
		(ground
			? `<path d="M${MARK.ground.x1} ${MARK.ground.y}H${MARK.ground.x2}" fill="none" stroke="${groundColor}" stroke-opacity="${groundAlpha}" stroke-width="9"/>`
			: '') +
		// Clipped so the plate can never leak past the basket it shows through.
		`<clipPath id="${id}-tub"><path d="${MARK.basket}"/></clipPath>` +
		`<g clip-path="url(#${id}-tub)">` +
		`<rect x="${p.x}" y="${p.y}" width="${p.w}" height="${p.h}" fill="${riseColor}"/>` +
		`</g>` +
		// A mask, not an evenodd subpath. While the animation is lifting the
		// knockout it sits partly outside the basket, and as a subpath the part
		// hanging below the floor stopped subtracting and started filling — a
		// solid arrow under the basket. A mask can only ever take away.
		`<mask id="${id}-cut" maskUnits="userSpaceOnUse" x="0" y="0" width="512" height="512">` +
		`<rect width="512" height="512" fill="#fff"/>` +
		`<path d="${MARK.arrowAt(dy)}" fill="#000"/>` +
		`</mask>` +
		`<path d="${MARK.basket}" fill="${cartColor}" mask="url(#${id}-cut)"/>` +
		`<path d="${MARK.handle}" fill="none" stroke="${cartColor}" stroke-width="${MARK.handleStroke}"/>` +
		`</g>`
	);
}

/**
 * Single-colour mark. The knockout becomes a true hole, so whatever the mark is
 * sitting on shows through — which is what makes it work unmodified in the
 * wp-admin menu at 20px.
 *
 * @param {string} color     Paint.
 * @param {string} risePaint Optional paint behind the knockout; omit for a hole.
 * @return {string} SVG fragment.
 */
export function markSmallBody(color, risePaint = null) {
	return (
		`<g stroke-linecap="round" stroke-linejoin="round">` +
		(risePaint
			? `<clipPath id="s-tub"><path d="${MARK.basket}"/></clipPath>` +
				`<g clip-path="url(#s-tub)">` +
				`<rect x="${MARK.plate.x}" y="${MARK.plate.y}" width="${MARK.plate.w}" height="${MARK.plate.h}" fill="${risePaint}"/>` +
				`</g>`
			: '') +
		`<path d="${MARK.basket}${MARK.arrow}" fill="${color}" fill-rule="evenodd"/>` +
		`<path d="${MARK.handle}" fill="none" stroke="${color}" stroke-width="${MARK.handleStroke}"/>` +
		`</g>`
	);
}

export const markTinyBody = markSmallBody;

export const svg = (w, h, body) =>
	`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" ` +
	`viewBox="0 0 ${w} ${h}" fill="none" role="img">${body}</svg>\n`;
