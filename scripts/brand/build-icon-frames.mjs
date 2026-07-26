/**
 * Renders the animated wordpress.org icon, frame by frame, straight from the
 * brand geometry.
 *
 *   node scripts/brand/build-icon-frames.mjs <outDir> <size> [<size> ...]
 *
 * Writes <outDir>/<size>/f-000.png … and <outDir>/frames.txt, one
 * "basename delay-in-centiseconds" line per frame, for the GIF assembler.
 *
 * The mark is a basket with the recovery cut out of it, so the animation lifts
 * that cut-out. The basket settles in, then the knockout rises from the floor to
 * its resting place, carrying the mint behind it — the value coming back up out
 * of the basket, which is the only thing the mark claims. Nothing moves that is
 * not already part of it, and because every frame is re-rendered from the same
 * geometry the plain icon uses, the animation cannot drift away from it.
 *
 * The motion runs for about 1.4s and then holds still for 2.5s. A directory
 * icon sits in a grid next to other content and loops forever; something that
 * moves continuously in that context is noise, not delight. Playing the story
 * once and then getting out of the way is the point of the long hold.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { Resvg } from '@resvg/resvg-js';

import { C, MARK, RISE_AXIS, fit, markBody, r, svg, xform } from './brand.mjs';

const [outDir, ...sizes] = process.argv.slice(2);

if (!outDir || !sizes.length) {
	console.error('usage: build-icon-frames.mjs <outDir> <size> [<size> ...]');
	process.exit(1);
}

/* ------------------------------------------------------------------ timing */

const FPS = 25;
const MOTION = 34; // frames of animation
const FADE = 5; // frames that dissolve back to an empty stage, so the loop cuts cleanly
const HOLD_CS = 250; // centiseconds the finished mark sits still

const T = {
	basketStart: 0.0,
	basketEnd: 0.3,
	liftStart: 0.32,
	liftEnd: 0.86,
};

const clamp01 = (x) => Math.min(1, Math.max(0, x));
const span = (t, a, b) => clamp01((t - a) / (b - a));
const easeOut = (u) => 1 - Math.pow(1 - u, 3);

/**
 * Overshoot with no bounce afterwards. The head is a small element arriving
 * fast; a full spring would read as wobble at 128px, a linear pop reads as a
 * missing frame. One controlled overshoot is the whole gesture.
 */
const overshoot = (u) => {
	const e = easeOut(u);
	return e + Math.sin(Math.PI * u) * 0.14;
};

/* ------------------------------------------------------------------- frame */

const SIZE = 512;
const t = fit(MARK.box, SIZE, 0.7);
const radius = r(SIZE * 0.22);

/**
 * One frame of the loop.
 *
 * @param {number} time  Motion progress, 0..1.
 * @param {number} alpha Whole-stage opacity, for the loop-out dissolve.
 * @return {string} SVG document.
 */
function frame(time, alpha = 1) {
	// The basket arrives first: the knockout only reads as something rising out
	// of it once there is a basket for it to rise out of.
	const settled = easeOut(span(time, T.basketStart, T.basketEnd));
	const lift = overshoot(span(time, T.liftStart, T.liftEnd));

	return svg(
		SIZE,
		SIZE,
		[
			`<defs>`,
			`<linearGradient id="a-bg" x1="0" y1="0" x2="0" y2="${SIZE}" gradientUnits="userSpaceOnUse">`,
			`<stop offset="0" stop-color="${C.indigo600}"/>`,
			`<stop offset="1" stop-color="${C.ink900}"/>`,
			`</linearGradient>`,
			`<radialGradient id="a-glow" cx=".32" cy=".78" r=".72">`,
			`<stop offset="0" stop-color="${C.indigo400}" stop-opacity=".45"/>`,
			`<stop offset="1" stop-color="${C.indigo400}" stop-opacity="0"/>`,
			`</radialGradient>`,
			`<linearGradient id="a-rise" ${RISE_AXIS} gradientUnits="userSpaceOnUse">`,
			`<stop offset="0" stop-color="${C.mint400}"/>`,
			`<stop offset=".55" stop-color="${C.mint200}"/>`,
			`<stop offset="1" stop-color="${C.white}"/>`,
			`</linearGradient>`,
			`</defs>`,
			`<rect width="${SIZE}" height="${SIZE}" rx="${radius}" fill="url(#a-bg)"/>`,
			`<rect width="${SIZE}" height="${SIZE}" rx="${radius}" fill="url(#a-glow)"/>`,
			`<g transform="${xform(t)}" opacity="${r(alpha * settled, 3)}">`,
			markBody({
				id: 'a',
				cartColor: C.white,
				riseColor: 'url(#a-rise)',
				arrowLift: lift,
				groundColor: C.white,
				groundAlpha: r(0.05 + 0.09 * settled, 3),
			}),
			`</g>`,
			`<rect x=".5" y=".5" width="${SIZE - 1}" height="${SIZE - 1}" rx="${r(radius - 0.5)}" fill="none" stroke="${C.white}" stroke-opacity=".12"/>`,
		].join('')
	);
}

/* -------------------------------------------------------------------- emit */

const timeline = [];

for (let i = 0; i < MOTION; i++) {
	timeline.push({
		svg: frame(i / (MOTION - 1)),
		delay: Math.round(100 / FPS),
	});
}

// The finished mark, held.
timeline.at(-1).delay = HOLD_CS;

// Dissolve to an empty stage so frame 0 is not a hard cut back to nothing.
for (let i = 1; i <= FADE; i++) {
	timeline.push({
		svg: frame(1, 1 - i / FADE),
		delay: Math.round(100 / FPS),
	});
}

mkdirSync(outDir, { recursive: true });

for (const size of sizes) {
	const dir = resolve(outDir, String(size));
	mkdirSync(dir, { recursive: true });

	timeline.forEach((f, i) => {
		const png = new Resvg(f.svg, {
			fitTo: { mode: 'width', value: Number(size) },
			shapeRendering: 2,
		})
			.render()
			.asPng();
		writeFileSync(resolve(dir, `f-${String(i).padStart(3, '0')}.png`), png);
	});

	console.log(`  ${timeline.length} frames @ ${size}px`);
}

writeFileSync(
	resolve(outDir, 'frames.txt'),
	timeline
		.map((f, i) => `f-${String(i).padStart(3, '0')}.png ${f.delay}`)
		.join('\n') + '\n'
);
