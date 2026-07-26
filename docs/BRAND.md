# Cart Rebound brand

Everything visual is generated from one file. There is no hand-drawn artwork to
keep in sync, and no binary master that can only be edited in a design tool.

```bash
pnpm brand          # rewrite assets/brand/*.svg and the wordpress.org masters
pnpm brand:wporg    # the above, plus the PNG/GIF listing assets
```

Source of truth: [`scripts/brand/brand.mjs`](../scripts/brand/brand.mjs) —
palette, cart and curve geometry, wordmark outlining. The emitters
([`build-brand.mjs`](../scripts/brand/build-brand.mjs),
[`banner.mjs`](../scripts/brand/banner.mjs),
[`build-icon-frames.mjs`](../scripts/brand/build-icon-frames.mjs)) only compose
what that file defines.

## The mark

A shopping basket with the recovery cut out of it.

**Why a knockout.** Three marks were rejected getting here, all for the same
reason. A bare fall-and-rise trajectory read as a generic analytics chevron. A
trolley beside an arrow was the Material cart glyph next to the most-used symbol
in SaaS — adjacency makes neither shape ownable, it just produces a lockup of
clip art. A trolley whose handle became the arrow was better, but it was still an
assembly of thin strokes covering **8% of the tile**, so it read as an icon set
rather than a logo. A knockout cannot be read as two things standing next to each
other, because there is only one shape and the idea lives in the gap it leaves.
This mark fills **23%**.

**Why a basket, not a trolley.** Fewer parts — no wheels, no push bar — so it is
one closed silhouette instead of an assembly, and it holds together at 20px where
a trolley falls apart.

**Why a straight arrow.** A return arc says _recovery_ more precisely than an up
arrow says it, and it was drawn. In negative space at 24px it lost its head and
read as a mint hump echoing the handle above it. The up arrow is unambiguous at
every size, which beats being more precise at one size.

The mint sits on a plate behind the basket, clipped to it, so the hole reads as
the recovery showing through rather than as a gap onto the tile. That is the
system's single hue move, and it lands inside the thing being recovered.

### One tier

There is no reduced variant, and there is no cart-only variant for the menu.
Earlier marks needed both, because stroke assemblies fall apart when you shrink
them. A filled silhouette does not: the only thing that changes between 256px and
20px is that the knockout stops showing mint and starts showing whatever the mark
is sitting on. `MARK_SM` and `MARK_XS` are kept as aliases of `MARK` so call
sites did not all have to learn that.

## Palette

Anchored on the accent the admin UI already ships (`--cr-accent`,
`oklch(0.55 0.16 264)`). The previous lavender artwork never matched it.

| Token                                   | Hex                           | Role                                               |
| --------------------------------------- | ----------------------------- | -------------------------------------------------- |
| `ink950` / `ink900` / `ink800`          | `#0B1228` `#111D44` `#182762` | banner field, wordmark on light                    |
| `indigo600` / `indigo500` / `indigo400` | `#2C51B6` `#406BCE` `#5D87E2` | tile, product accent, glow                         |
| `mint400` → `mint200`                   | `#40DBAC` → `#B1F6DF`         | the rise, on dark only                             |
| `teal600`                               | `#00A77B`                     | the rise, on light — mint has no contrast on paper |

`teal600` clears 3:1 against white as a graphic element; `mint400` does not,
which is why light and dark lockups do not share a gradient.

## Typography

**Archivo** (SIL OFL, variable, instanced at `wght 700 / wdth 112`).

Not a geometric sans. The product is a logistics tool that moves money back
into a store, and Archivo's grotesque, slightly squared shapes read like a
shipping manifest rather than another startup wordmark. Semi-expanded so
"Rebound" feels like a push and not a squeeze.

Every wordmark is outlined to path data at build time, so no shipped SVG
depends on a font being installed anywhere. The font itself lives in
`.wordpress-org/src/fonts/` and never ships with the plugin.

## Files

`assets/brand/` ships inside the plugin:

| File                                      | Use                                               |
| ----------------------------------------- | ------------------------------------------------- |
| `icon.svg`                                | 512 app tile                                      |
| `icon-small.svg`                          | tile with the reduced mark, for ≤48px             |
| `mark.svg` / `mark-inverse.svg`           | mark alone, light / dark background               |
| `mark-mono.svg` / `mark-mono-inverse.svg` | flat single colour                                |
| `lockup.svg` / `lockup-inverse.svg`       | mark + wordmark, horizontal                       |
| `wordmark.svg` / `wordmark-inverse.svg`   | wordmark alone                                    |
| `menu-icon.svg`                           | 20px wp-admin menu icon, consumed by `Admin\Menu` |

`.wordpress-org/` is build output and is excluded from the release archive.

Icons ship as GIF only. The handbook lists `icon-256x256.(png|jpg|gif)` — one
file per size — and says nothing about precedence when two of them exist, so a
same-named PNG sitting beside the animation risks the directory quietly serving
the still.

## The banner

Not the mark on a field. A directory banner has one job — tell someone
scrolling a grid what the plugin _does_ — and a mark alone cannot do that: it
has to be decoded before it says anything, and at 772px it is decoration with a
wordmark beside it. An earlier version was exactly that, and measuring it made
the case: a 202px dead gap between the text and the mark, and 106px of empty
field below the horizon.

So the banner shows the mechanism
([`banner.mjs`](../scripts/brand/banner.mjs)). The question is which mechanism,
and the answer has to come from what this plugin actually is.

**It is a sequence in time, not a matrix.** `readme.txt` already names the five
steps: capture the cart, identify the abandonment, schedule the email, restore
the cart, attribute the order. One after another. A plugin that fanned many
triggers into many actions would want a two-column diagram with a hub — that
shape encodes _that_ product. Drawing one here would describe a plugin this
isn't.

What fits is a **recovery curve**: cart value falls away when the shopper
leaves, and the plugin brings it back higher than it started, with the steps as
milestones along it.

The curve is not a new drawing. It is the mark's own fall-and-rise geometry
stretched to banner width — same faded parabola down, same heavy mint
acceleration up, same arrowhead. The logo at 128px and the banner at 1544px are
one idea at two scales, which is the entire reason for having a mark.

Two rules keep it honest:

- **Labels are verified, not eyeballed.** `verifyLabels()` fails the build if two
  labels overlap or if one lands on the rise. The curve is steep at the top
  right, so a label a fixed distance from its node can still sit on the stroke —
  which looks survivable at 1544 and turns to mush at 772. Each milestone
  therefore carries its own leader length.
- **Every string is outlined and width-clamped.** The directory also serves a
  772px downscale of this exact artwork, so a label that overflows there
  overflows for half the audience.

The area fill under the rise is what makes it read as revenue returning rather
than as a decorative swoosh. The fall is drawn as spaced dots rather than a
stroke for the opposite reason: that half of the story is the half the shop owner
never sees happen, and it should not compete with the half the plugin is selling.

## The animated icon

The mark is a basket with the recovery cut out of it, so the animation lifts that
cut-out. `build-icon-frames.mjs` renders every frame from the same geometry the
static icon uses, so it cannot drift away from the mark. The basket settles in
first; then the knockout rises from below the floor to its resting place,
carrying the mint behind it. Then it holds still for 2.5 seconds before
dissolving and looping.

The basket has to arrive first: the knockout only reads as something rising out
of it once there is a basket for it to rise out of. Nothing moves that is not
already part of the mark.

The knockout is a **mask**, not an `evenodd` subpath. Mid-lift it sits partly
outside the basket, and as a subpath the part hanging below the floor stopped
subtracting and started filling — a solid arrow under the basket for the first
third of the loop. A mask can only ever take away.

The long hold matters too. A directory icon sits in a grid next to other content
and loops forever; something that moves continuously there is noise, not
delight.

## Changing something

Edit `brand.mjs` and run `pnpm brand:wporg`. Mark bounds are measured from the
geometry — curve samples, cart corners, wheels, arrowhead — rather than hard-coded,
so re-shaping any of it re-centres every asset automatically instead of silently
decentring them.

Banner copy lives in `banner.mjs` (`STEPS`, `PAYOFF`, and the headline and body
strings). Re-word freely: `verifyLabels()` will fail the build with the offending
label named if a change makes two milestones collide or pushes one onto the
curve. Fix it by adjusting that step's `lead` or `t`.
