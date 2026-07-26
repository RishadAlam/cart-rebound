/**
 * The wordpress.org banner.
 *
 * A directory banner has one job: tell someone scrolling a grid what the plugin
 * does. The mark alone cannot do that — it has to be decoded before it says
 * anything — so the banner shows the mechanism instead.
 *
 * The mechanism here is a *sequence in time*, not a matrix. readme.txt already
 * names it: capture the cart, identify the abandonment, schedule the email,
 * restore the cart, attribute the order. Five steps, one after another. So the
 * banner is a recovery curve with those five steps sitting on it — cart value
 * falls away when the shopper leaves, and the plugin brings it back higher than
 * it started.
 *
 * That curve is not a new drawing. It is the mark's own fall-and-rise geometry
 * stretched to banner width: same faded parabola down, same heavy mint
 * acceleration up, same arrowhead. The logo at 128px and the banner at 1544px
 * are the same idea at two scales, which is the whole reason the mark is worth
 * having.
 *
 * Every string is outlined and width-clamped rather than trusted to fit, since
 * the directory also serves a 772px downscale of this exact artwork and a label
 * that overflows there overflows for half the audience.
 */

import {
	C,
	CAP_RATIO,
	MARK_SM,
	clampWidth,
	fit,
	markSmallBody,
	outline,
	outlineToWidth,
	r,
	svg,
	xform,
} from './brand.mjs';

export const BANNER = { w: 1544, h: 500 };

const L = {
	padX: 80,
	tile: 58,
	brandGap: 19,
	brandY: 96,
	wordCap: 33,
	headCap: 52,
	head1Y: 262,
	head2Y: 330,
	headMax: 470,
	bodySize: 21,
	bodyY: 386,
	bodyLead: 30,
	bodyMax: 480,
};

/* -------------------------------------------------------------- the curve */

/** Where value sits before anything happens, and the floor it is measured off. */
const AXIS = 424;

/**
 * The loss. A true parabola that leaves horizontally — the cart was sitting
 * still — and drops away. Drawn faded and dotted, like the mark's trail: this
 * is the half of the story the shop owner never sees.
 */
const FALL = [
	[648, 168],
	[772, 168],
	[892, 350],
];

/**
 * The recovery. Deliberately not a parabola: it leaves the trough flat and
 * accelerates, ending higher than the fall began, because the product's claim
 * is that a recovered cart beats one that never wobbled.
 */
const RISE = [
	[892, 350],
	[1030, 350],
	[1272, 306],
	[1436, 132],
];

const RISE_W = 13;

/**
 * Fraction along each curve where a milestone sits, which side of the curve its
 * label hangs on, and how far out the leader runs.
 *
 * The leader length is per-step rather than constant because the curve is not:
 * near the top right it climbs steeply, so a label a fixed distance "below" its
 * node still lands on the stroke. These four are checked against the curve and
 * against each other — see the collision test in `verifyLabels`.
 */
const STEPS = [
	{ on: 'fall', t: 0, label: 'Cart captured', icon: 'cart', side: -1, lead: 30 },
	{ on: 'fall', t: 1, label: 'Marked abandoned', icon: 'clock', side: 1, lead: 30 },
	{ on: 'rise', t: 0.42, label: 'Recovery email sent', icon: 'mail', side: -1, lead: 56 },
	{ on: 'rise', t: 0.78, label: 'Cart restored', icon: 'restore', side: 1, lead: 74 },
];

/** The payoff, called out rather than dotted like the rest. */
const PAYOFF = { label: 'Revenue attributed', icon: 'chart' };

/* ----------------------------------------------------------------- glyphs */

/**
 * Monoline glyphs on a 24 grid, centred on their anchor. Stroke-only and open —
 * at the ~20px they render to, a filled icon turns into a blob. No chips behind
 * them: the curve is already carrying the structure, and boxing every label
 * would bury it.
 *
 * @type {Object<string, string>}
 */
const GLYPH = {
	cart: 'M2.5 3.5h2.6l2.9 10h9.4M8.6 10.6h9.9l1.9-5.4H6.6',
	clock: 'M12 3.6a8.4 8.4 0 1 1 0 16.8 8.4 8.4 0 0 1 0-16.8ZM12 7.2v5.2l3.4 2',
	mail: 'M3.2 6.2h17.6v11.6H3.2ZM3.2 6.8 12 13l8.8-6.2',
	restore: 'M4.4 12a7.6 7.6 0 1 0 2.4-5.5M4.2 3.6v4.6h4.6',
	chart: 'M4.2 20.2h15.6M7.6 20.2v-6.4M12 20.2V6.6M16.4 20.2v-9.4',
};

const glyph = (name, color, alpha = 1) =>
	`<g transform="translate(-12 -12)" fill="none" stroke="${color}" ` +
	`stroke-opacity="${alpha}" stroke-width="2" stroke-linecap="round" ` +
	`stroke-linejoin="round"><path d="${GLYPH[name]}"/></g>`;

/* ------------------------------------------------------------------- maths */

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

const at = (which, t) => bezier(which === 'fall' ? FALL : RISE, t);

const curve = (pts) =>
	pts.length === 3
		? `M${pts[0][0]} ${pts[0][1]}Q${pts[1][0]} ${pts[1][1]} ${pts[2][0]} ${pts[2][1]}`
		: `M${pts[0][0]} ${pts[0][1]}C${pts
				.slice(1)
				.map((p) => p.join(' '))
				.join(' ')}`;

/**
 * Solid head solved from the rise's end tangent, so it sits square on the curve
 * instead of being eyeballed.
 *
 * @param {number} halfWidth Half the base width.
 * @param {number} height    Tip-to-base distance.
 * @return {{d: string, tip: number[]}} Path data and the tip point.
 */
function arrowhead(halfWidth, height) {
	const base = RISE.at(-1);
	const tan = [base[0] - RISE.at(-2)[0], base[1] - RISE.at(-2)[1]];
	const len = Math.hypot(...tan);
	const d = [tan[0] / len, tan[1] / len];
	const perp = [-d[1], d[0]];
	const pts = [
		[base[0] + d[0] * height, base[1] + d[1] * height],
		[base[0] + perp[0] * halfWidth, base[1] + perp[1] * halfWidth],
		[base[0] - perp[0] * halfWidth, base[1] - perp[1] * halfWidth],
	].map((p) => [r(p[0]), r(p[1])]);

	return {
		tip: pts[0],
		d: `M${pts[0][0]} ${pts[0][1]}L${pts[1][0]} ${pts[1][1]}L${pts[2][0]} ${pts[2][1]}Z`,
	};
}

/**
 * The fall as spaced dots rather than a stroke, matching the mark's trail. They
 * grow toward the trough because that is the direction the value is moving.
 *
 * @param {number} n Dot count.
 * @return {string} SVG fragment.
 */
function fallDots(n = 11) {
	return Array.from({ length: n }, (_, i) => {
		const u = i / (n - 1);
		const [x, y] = at('fall', 0.04 + u * 0.9);
		return `<circle cx="${r(x)}" cy="${r(y)}" r="${r(3.6 + u * 3.6)}" fill="${C.white}" fill-opacity="${r(0.2 + u * 0.4, 3)}"/>`;
	}).join('');
}

/**
 * One milestone: a node on the curve, a hairline leader out to the label, and
 * the label itself with its glyph.
 *
 * @param {Object} step Milestone spec.
 * @return {string} SVG fragment.
 */
function milestone(step) {
	const [x, y] = at(step.on, step.t);
	const lost = step.on === 'fall';
	const ink = lost ? C.indigo400 : C.mint400;
	const lead = step.lead;
	const labelY = y + step.side * (lead + 20);
	const o = outline(step.label, 21, -0.004);
	const gx = r(x - o.width / 2 - 17);

	return (
		`<g>` +
		`<path d="M${r(x)} ${r(y + step.side * 9)}V${r(y + step.side * lead)}" stroke="${ink}" stroke-opacity=".55" stroke-width="1.5"/>` +
		`<circle cx="${r(x)}" cy="${r(y)}" r="7" fill="${C.ink900}" stroke="${ink}" stroke-width="3.5"/>` +
		`<g transform="translate(${gx} ${r(labelY - 7)}) scale(.8)">` +
		glyph(step.icon, C.white, 0.72) +
		`</g>` +
		`<g transform="translate(${r(x - o.width / 2 + 6)} ${r(labelY)})" fill="${C.white}" fill-opacity=".9">` +
		`<path d="${o.d}"/></g>` +
		`</g>`
	);
}

/**
 * Box a milestone's label occupies, glyph included.
 *
 * @param {Object} step Milestone spec.
 * @return {{label: string, x0: number, x1: number, y: number}} Label box.
 */
function labelBox(step) {
	const [x, y] = at(step.on, step.t);
	const o = outline(step.label, 21, -0.004);
	return {
		label: step.label,
		x0: x - o.width / 2 - 26,
		x1: x + o.width / 2 + 6,
		y: y + step.side * (step.lead + 20),
	};
}

/**
 * Fails the build if two labels overlap, or if a label lands on the rise.
 *
 * The curve is steep at the top right, so a label a fixed distance from its node
 * can still sit on the stroke — which is exactly the kind of thing that looks
 * fine in the 1544 render and turns to mush at 772. Re-word a milestone and this
 * tells you immediately rather than shipping the overlap.
 */
function verifyLabels() {
	const boxes = STEPS.map(labelBox);
	const clash = (a, b) =>
		a.x1 > b.x0 && b.x1 > a.x0 && Math.abs(a.y - b.y) < 26;

	for (let i = 0; i < boxes.length; i++) {
		for (let j = i + 1; j < boxes.length; j++) {
			if (clash(boxes[i], boxes[j])) {
				throw new Error(
					`banner: "${boxes[i].label}" overlaps "${boxes[j].label}" — ` +
						`change one label's lead or t`
				);
			}
		}
	}

	for (const b of boxes) {
		for (let t = 0; t <= 1; t += 0.002) {
			const [px, py] = at('rise', t);
			if (px > b.x0 && px < b.x1 && Math.abs(py - b.y) < 22) {
				throw new Error(
					`banner: "${b.label}" sits on the rise at x=${r(px)} — ` +
						`increase its lead`
				);
			}
		}
	}
}

/* ------------------------------------------------------------------ banner */

/**
 * @return {string} SVG document.
 */
export function banner() {
	const { w, h } = BANNER;

	verifyLabels();

	const word = outline('Cart Rebound', L.wordCap / CAP_RATIO, -0.018);
	const head1 = outlineToWidth('Recover abandoned', L.headMax, -0.024);
	const head2 = outlineToWidth('WooCommerce carts', L.headMax, -0.024);
	const body = [
		'Every cart that goes quiet gets tracked, emailed',
		'back, and counted when it converts.',
	].map((s) => clampWidth(s, L.bodySize, L.bodyMax));

	const head = arrowhead(RISE_W * 1.5, RISE_W * 2.6);
	const payoff = outline(PAYOFF.label, 22, -0.004);
	const payoffX = r(head.tip[0] - payoff.width - 24);
	const payoffY = r(head.tip[1] - 34);

	// Area under the recovery, closed on the axis. The fill is what makes the
	// curve read as revenue coming back rather than as a decorative swoosh.
	const area =
		curve(RISE) +
		`L${RISE.at(-1)[0]} ${AXIS}L${RISE[0][0]} ${AXIS}Z`;

	return svg(
		w,
		h,
		[
			`<defs>`,
			`<linearGradient id="b-bg" x1="0" y1="0" x2="${w}" y2="${h}" gradientUnits="userSpaceOnUse">`,
			`<stop offset="0" stop-color="${C.ink950}"/>`,
			`<stop offset=".55" stop-color="${C.ink900}"/>`,
			`<stop offset="1" stop-color="${C.ink800}"/>`,
			`</linearGradient>`,
			`<radialGradient id="b-glow" cx=".5" cy=".5" r=".5">`,
			`<stop offset="0" stop-color="${C.indigo500}" stop-opacity=".42"/>`,
			`<stop offset="1" stop-color="${C.indigo500}" stop-opacity="0"/>`,
			`</radialGradient>`,
			`<linearGradient id="b-rise" x1="${RISE[0][0]}" y1="${RISE[0][1]}" x2="${head.tip[0]}" y2="${head.tip[1]}" gradientUnits="userSpaceOnUse">`,
			`<stop offset="0" stop-color="${C.mint400}"/>`,
			`<stop offset=".55" stop-color="${C.mint200}"/>`,
			`<stop offset="1" stop-color="${C.white}"/>`,
			`</linearGradient>`,
			`<linearGradient id="b-area" x1="0" y1="132" x2="0" y2="${AXIS}" gradientUnits="userSpaceOnUse">`,
			`<stop offset="0" stop-color="${C.mint400}" stop-opacity=".3"/>`,
			`<stop offset="1" stop-color="${C.mint400}" stop-opacity="0"/>`,
			`</linearGradient>`,
			`<linearGradient id="b-tile" x1="0" y1="0" x2="0" y2="1">`,
			`<stop offset="0" stop-color="${C.indigo500}"/>`,
			`<stop offset="1" stop-color="${C.indigo700}"/>`,
			`</linearGradient>`,
			`</defs>`,

			`<rect width="${w}" height="${h}" fill="url(#b-bg)"/>`,
			`<ellipse cx="1180" cy="300" rx="560" ry="440" fill="url(#b-glow)"/>`,

			/* the plot */
			`<g stroke="${C.white}" stroke-opacity=".07" stroke-width="1">` +
				[240, 332].map((y) => `<path d="M604 ${y}H1476"/>`).join('') +
				`</g>`,
			`<path d="M604 ${AXIS}H1476" stroke="${C.white}" stroke-opacity=".16" stroke-width="1.5"/>`,
			`<path d="${area}" fill="url(#b-area)"/>`,
			fallDots(),
			`<path d="${curve(RISE)}" fill="none" stroke="url(#b-rise)" stroke-width="${RISE_W}" stroke-linecap="round"/>`,
			`<path d="${head.d}" fill="url(#b-rise)" stroke="url(#b-rise)" stroke-width="4" stroke-linejoin="round"/>`,
			STEPS.map(milestone).join(''),

			/* the payoff, the only label that is not dotted onto the curve */
			`<g transform="translate(${r(payoffX - 17)} ${r(payoffY - 7)}) scale(.82)">` +
				glyph(PAYOFF.icon, C.mint300) +
				`</g>`,
			`<g transform="translate(${r(payoffX + 6)} ${payoffY})" fill="${C.mint300}"><path d="${payoff.d}"/></g>`,

			/* the word */
			`<g transform="translate(${L.padX} ${L.brandY})">`,
			`<rect width="${L.tile}" height="${L.tile}" rx="${r(L.tile * 0.28)}" fill="url(#b-tile)"/>`,
			`<g transform="${xform(fit(MARK_SM.box, L.tile, 0.62))}">`,
			markSmallBody(C.white, C.mint300),
			`</g>`,
			`</g>`,
			`<g transform="translate(${L.padX + L.tile + L.brandGap} ${r(L.brandY + L.tile / 2 + L.wordCap / 2)})" fill="${C.white}">`,
			`<path d="${word.d}"/></g>`,

			`<g transform="translate(${L.padX} ${L.head1Y})" fill="${C.white}"><path d="${head1.d}"/></g>`,
			// The second line carries the mint. One hue move in the mark, one in
			// the headline, both landing on the thing being recovered.
			`<g transform="translate(${L.padX} ${L.head2Y})" fill="${C.mint300}"><path d="${head2.d}"/></g>`,

			body
				.map(
					(b, i) =>
						`<g transform="translate(${L.padX} ${L.bodyY + i * L.bodyLead})" fill="${C.white}" fill-opacity=".7"><path d="${b.d}"/></g>`
				)
				.join(''),
		].join('')
	);
}
