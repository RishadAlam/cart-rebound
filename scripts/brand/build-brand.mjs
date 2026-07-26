/**
 * Generates every Cart Rebound brand file from scripts/brand/brand.mjs.
 *
 *   pnpm brand
 *
 * Outputs:
 *   assets/brand/*.svg          shipped with the plugin (admin UI, docs)
 *   .wordpress-org/src/*.svg    masters that build-wporg-assets.sh rasterises
 *
 * Nothing here is hand-edited. Change the geometry or palette in brand.mjs and
 * re-run, so the tile, the lockup and the animated directory icon can never
 * disagree about what the mark is.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

import {
	C,
	CAP_RATIO,
	MARK,
	MARK_SM,
	MARK_XS,
	ROOT,
	clampWidth,
	fit,
	markBody,
	markDefs,
	markSmallBody,
	markTinyBody,
	outline,
	outlineToWidth,
	place,
	r,
	riseGradientLight,
	svg,
	xform,
} from './brand.mjs';
import { banner } from './banner.mjs';

const write = (rel, content) => {
	const path = resolve(ROOT, rel);
	mkdirSync(resolve(path, '..'), { recursive: true });
	writeFileSync(path, content);
	console.log(`  ${rel.padEnd(44)} ${String(content.length).padStart(6)} B`);
};

/* ------------------------------------------------------------- 1. app tile */

/**
 * The tile.
 *
 * Fitted to 70% of the canvas rather than the usual 80% so it survives the
 * circular crop some surfaces apply — the arrowhead is the furthest point from
 * centre and would be the first thing a circle clips.
 *
 * `reduced` swaps in the small-size mark. Anything at or under about 48px gets
 * that version: at those sizes the trail dots are sub-pixel and the full mark
 * collapses into grey mush.
 *
 * @param {number} size            Canvas edge in px.
 * @param {Object} [o]             Options.
 * @param {boolean} [o.rounded]    Draw the 22% corner radius.
 * @param {boolean} [o.reduced]    Use the small-size mark.
 * @return {string} SVG document.
 */
function tile(size = 512, { rounded = true, reduced = false } = {}) {
	const t = fit(reduced ? MARK_SM.box : MARK.box, size, reduced ? 0.62 : 0.7);
	const radius = rounded ? r(size * 0.22) : 0;

	return svg(
		size,
		size,
		[
			`<defs>`,
			`<linearGradient id="t-bg" x1="0" y1="0" x2="0" y2="${size}" gradientUnits="userSpaceOnUse">`,
			`<stop offset="0" stop-color="${C.indigo600}"/>`,
			`<stop offset="1" stop-color="${C.ink900}"/>`,
			`</linearGradient>`,
			`<radialGradient id="t-glow" cx=".32" cy=".78" r=".72">`,
			`<stop offset="0" stop-color="${C.indigo400}" stop-opacity=".45"/>`,
			`<stop offset="1" stop-color="${C.indigo400}" stop-opacity="0"/>`,
			`</radialGradient>`,
			reduced ? '' : markDefs('t'),
			`</defs>`,
			`<rect width="${size}" height="${size}" rx="${radius}" fill="url(#t-bg)"/>`,
			`<rect width="${size}" height="${size}" rx="${radius}" fill="url(#t-glow)"/>`,
			`<g transform="${xform(t)}">`,
			reduced
				// Same mark, just single-colour defs: the knockout shows mint.
				? markSmallBody(C.white, C.mint300)
				: markBody({
						id: 't',
						cartColor: C.white,
						riseColor: 'url(#t-rise)',
						groundColor: C.white,
						groundAlpha: 0.14,
					}),
			`</g>`,
			// Hairline inner highlight: the tile reads as a physical key rather
			// than a flat swatch, and it survives down to 64px.
			rounded
				? `<rect x=".5" y=".5" width="${size - 1}" height="${size - 1}" rx="${r(radius - 0.5)}" fill="none" stroke="${C.white}" stroke-opacity=".12"/>`
				: '',
		].join('')
	);
}

/* ------------------------------------------------- 2. mark, no background */

function mark({ onDark }) {
	const size = 512;
	const t = fit(MARK.box, size, 0.94);
	const id = onDark ? 'md' : 'ml';

	return svg(
		size,
		size,
		`<defs>${onDark ? markDefs(id) : riseGradientLight(id)}</defs>` +
			`<title>Cart Rebound</title>` +
			`<g transform="${xform(t)}">` +
			markBody({
				id,
				cartColor: onDark ? C.white : C.ink900,
				riseColor: `url(#${id}-rise)`,
				groundColor: onDark ? C.white : C.ink900,
				groundAlpha: onDark ? 0.14 : 0.1,
			}) +
			`</g>`
	);
}

/* ------------------------------------------------ 3. reduced / mono marks */

/** Square, single colour, flat. Favicons, email headers, anywhere monochrome. */
function markMono(size, color) {
	const t = fit(MARK_SM.box, size, 0.84);
	return svg(
		size,
		size,
		`<title>Cart Rebound</title>` +
			`<g transform="${xform(t)}">${markSmallBody(color)}</g>`
	);
}

/**
 * wp-admin menu icon. WordPress serves a data-URI menu icon as a background
 * image and never recolours it; core only swaps opacity (0.6 idle, 1 on hover
 * and on the current item). Authoring it white therefore lands on exactly the
 * idle grey core expects and brightens correctly when the menu is active.
 */
function menuIcon() {
	const t = fit(MARK_XS.box, 20, 0.92);
	return svg(20, 20, `<g transform="${xform(t)}">${markTinyBody(C.white)}</g>`);
}

/* ---------------------------------------------------------- 4. lockups */

// markHeight is set off the mark's *height*, and the reduced mark is 1.37 wide,
// so a value tuned when it was nearly square left the cart looking undersized
// next to the wordmark.
const LOCKUP = { height: 120, markHeight: 96, cap: 54, gap: 24, baseline: 87 };

/**
 * Horizontal lockup. Uses the full mark.
 *
 * The reduced mark was tried here and looked wrong: its rise is cut short so the
 * arrow stays chunky at 20px, and at lockup size you can see that it is a stub
 * rather than a sweep — it reads as a leaf on the cart. The truncation only
 * works when the whole mark is genuinely tiny.
 */
function lockup({ onDark }) {
	const { height, markHeight, cap, gap, baseline } = LOCKUP;
	const word = outline('Cart Rebound', cap / CAP_RATIO, -0.018);
	const markW = (MARK.box.w / MARK.box.h) * markHeight;
	const id = onDark ? 'ld' : 'll';

	// The mark is centred on the wordmark's cap height, not on the canvas:
	// optically the logo sits on the letters, and cap-centring is what makes a
	// lockup stop looking like two things placed near each other.
	const t = place(MARK.box, markHeight, 0, baseline - cap / 2 - markHeight / 2);

	return svg(
		r(markW + gap + word.width),
		height,
		[
			`<defs>${onDark ? markDefs(id) : riseGradientLight(id)}</defs>`,
			`<title>Cart Rebound</title>`,
			`<g transform="${xform(t)}">`,
			markBody({
				id,
				cartColor: onDark ? C.white : C.ink900,
				riseColor: `url(#${id}-rise)`,
				// No ground line in a lockup: the wordmark's baseline is already
				// the horizontal the eye is using.
				ground: false,
			}),
			`</g>`,
			`<g transform="translate(${r(markW + gap)} ${baseline})" fill="${onDark ? C.white : C.ink900}">`,
			`<path d="${word.d}"/>`,
			`</g>`,
		].join('')
	);
}

function wordmark({ onDark }) {
	const cap = 68;
	const word = outline('Cart Rebound', cap / CAP_RATIO, -0.018);
	const pad = 12;

	return svg(
		r(word.width),
		cap + pad * 2,
		`<title>Cart Rebound</title>` +
			`<g transform="translate(0 ${cap + pad})" fill="${onDark ? C.white : C.ink900}">` +
			`<path d="${word.d}"/></g>`
	);
}

/* -------------------------------------------------------------- 6. emit */

console.log('Cart Rebound brand');

write('assets/brand/icon.svg', tile(512));
write('assets/brand/icon-small.svg', tile(64, { reduced: true }));
write('assets/brand/mark.svg', mark({ onDark: false }));
write('assets/brand/mark-inverse.svg', mark({ onDark: true }));
write('assets/brand/mark-mono.svg', markMono(64, C.ink900));
write('assets/brand/mark-mono-inverse.svg', markMono(64, C.white));
write('assets/brand/lockup.svg', lockup({ onDark: false }));
write('assets/brand/lockup-inverse.svg', lockup({ onDark: true }));
write('assets/brand/wordmark.svg', wordmark({ onDark: false }));
write('assets/brand/wordmark-inverse.svg', wordmark({ onDark: true }));
write('assets/brand/menu-icon.svg', menuIcon());

write('.wordpress-org/src/icon-master.svg', tile(512));
write(
	'.wordpress-org/src/icon-master-square.svg',
	tile(512, { rounded: false })
);
write('.wordpress-org/src/banner-master.svg', banner());

console.log('done');
