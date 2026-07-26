#!/usr/bin/env bash
#
# Regenerates the wordpress.org listing assets (banners + icons) from the brand
# system in scripts/brand/.
#
#   bash scripts/build-wporg-assets.sh
#
# Everything is vector-derived: build-brand.mjs writes the SVG masters, resvg
# rasterises them, and ImageMagick is used only to assemble the GIFs. That is
# the reason this replaced the previous raster pipeline — the old build
# resampled one fixed-resolution render, so the icon could not be re-cut for a
# new size without losing edges, and the animation was a nudge applied to a
# finished image rather than the mark actually moving.
#
# Format rules enforced here come from the plugin handbook
# (https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/):
#
#   * Banners MUST be PNG or JPG. GIF banners are ignored by the directory,
#     so the listing renders with no banner at all.
#   * Icons MAY be PNG, JPG, GIF or SVG. Animated GIF icons are served as-is.
#
# Requires: node with the repo's devDependencies installed, ImageMagick
# (convert, identify), and optionally optipng.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/.wordpress-org/src"
OUT="$ROOT/.wordpress-org"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

command -v convert >/dev/null || { echo "ImageMagick 'convert' not found" >&2; exit 1; }
command -v node >/dev/null || { echo "node not found" >&2; exit 1; }

raster() { node "$ROOT/scripts/brand/rasterize.mjs" "$1" "$2" "$3" >/dev/null; }

echo "▶ Brand masters"
node "$ROOT/scripts/brand/build-brand.mjs"

echo "▶ Banners (PNG — the only format the directory serves)"
raster "$SRC/banner-master.svg" "$OUT/banner-1544x500.png" 1544
raster "$SRC/banner-master.svg" "$OUT/banner-772x250.png" 772

echo "▶ Static icons (fallback for clients that skip the GIF)"
# Icons are emitted as GIF only. The handbook lists icon-256x256.(png|jpg|gif)
# — one file per size — and says nothing about precedence when two of them
# exist, so shipping a same-named PNG alongside the animation risks the
# directory quietly serving the still.

echo "▶ Icon animation frames"
node "$ROOT/scripts/brand/build-icon-frames.mjs" "$WORK/frames" 256 128

echo "▶ Icon GIFs"
# One palette built from every frame and applied without dithering. A shared
# dithered palette speckles the flat background and makes static areas shimmer
# between frames; mapping identical pixels identically keeps them still and
# costs almost nothing to encode.
#
# -treedepth 8 matters more than it looks. At the default depth the quantiser
# settles on ~110 colours because most pixels are near-identical background,
# and the tile's radial glow then bands into visible rings. Forcing the full
# tree depth spends the whole 256 on the gradient, which is where every one of
# them is needed.
build_gif() {
	local size="$1" out="$2" dir="$WORK/frames/$1" pal="$WORK/pal-$1.png"
	local args=()

	convert "$dir"/f-*.png +append -treedepth 8 -dither None -colors 256 \
		-unique-colors "$pal"

	# Per-frame delays: the animation runs at 25fps and then holds on the
	# finished mark, so the icon is mostly still in a directory grid.
	while read -r file delay; do
		args+=( -delay "$delay" "$dir/$file" )
	done < "$WORK/frames/frames.txt"

	convert -loop 0 "${args[@]}" \
		+dither -remap "$pal" -layers OptimizeTransparency "$out"
}

build_gif 256 "$OUT/icon-256x256.gif"
build_gif 128 "$OUT/icon-128x128.gif"

if command -v optipng >/dev/null; then
	echo "▶ optipng"
	optipng -quiet -o5 "$OUT/banner-1544x500.png" "$OUT/banner-772x250.png" \

fi

echo
printf '%-24s %8s  %s\n' FILE SIZE DETAILS
for f in banner-1544x500.png banner-772x250.png \
	icon-256x256.gif icon-128x128.gif; do
	printf '%-24s %8s  %s\n' "$f" \
		"$(du -h "$OUT/$f" | cut -f1)" \
		"$(identify -format '%wx%h, %n frame(s), %k colors\n' "$OUT/$f" | head -1)"
done
