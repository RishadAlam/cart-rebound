# wordpress.org listing assets

These files are for the plugin's **wordpress.org directory page**, not the
plugin itself. They are excluded from the shipped zip (`.gitattributes`
`export-ignore` + `build-zip.sh`). On deploy they go in the plugin's SVN
`/assets/` folder (or are picked up automatically by the
`10up/action-wordpress-plugin-deploy` GitHub Action, which reads `.wordpress-org/`).

## Files

| File                  | Purpose                  | Notes                              |
| --------------------- | ------------------------ | ---------------------------------- |
| `icon.svg`            | Icon source (vector)     | Edit here, then re-render the PNGs |
| `icon-256x256.png`    | Plugin icon (hi-dpi)     | Directory + admin                  |
| `icon-128x128.png`    | Plugin icon (standard)   |                                    |
| `banner.svg`          | Banner source (vector)   |                                    |
| `banner-1544x500.png` | Header banner (retina)   |                                    |
| `banner-772x250.png`  | Header banner (standard) |                                    |

## Credits

The cart glyph is the Heroicons `shopping-cart` icon (MIT). See
[`CREDITS.txt`](CREDITS.txt) for the notice. Everything else (badge,
gradient, layout, copy) is original.

## Screenshots

Screenshots (`screenshot-1.png`, `screenshot-2.png`, …) are **not** generated
here — capture them from the real running admin (Dashboard, Carts, Templates
editor, Log) and add a matching `== Screenshots ==` section to `readme.txt`.

## Re-render the PNGs

Requires Google Chrome (headless). From this folder:

```bash
render() {
  local svg="$1" w="$2" h="$3" out="$4"
  printf '<!doctype html><meta charset=utf-8><style>*{margin:0;padding:0}html,body{width:%spx;height:%spx;overflow:hidden}img{display:block;width:%spx;height:%spx}</style><img src="file://%s/%s">' \
    "$w" "$h" "$w" "$h" "$(pwd)" "$svg" > _wrap.html
  google-chrome --headless=new --disable-gpu --no-sandbox --hide-scrollbars \
    --force-device-scale-factor=1 --default-background-color=00000000 \
    --window-size="${w},${h}" --screenshot="${out}" "file://$(pwd)/_wrap.html"
  rm -f _wrap.html
}
render icon.svg   256 256  icon-256x256.png
render icon.svg   128 128  icon-128x128.png
render banner.svg 772 250  banner-772x250.png
render banner.svg 1544 500 banner-1544x500.png
```
