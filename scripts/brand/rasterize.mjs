/**
 * SVG -> PNG. ImageMagick's built-in SVG renderer drops colour and gradients,
 * so rasterisation goes through resvg instead of `convert`.
 *
 *   node scripts/brand/rasterize.mjs <in.svg> <out.png> <width>
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { Resvg } from '@resvg/resvg-js';

const [src, out, width] = process.argv.slice(2);
const r = new Resvg(readFileSync(src, 'utf8'), {
	fitTo: { mode: 'width', value: Number(width) },
	shapeRendering: 2,
	imageRendering: 0,
});
writeFileSync(out, r.render().asPng());
console.log(`${out} @${width}`);
