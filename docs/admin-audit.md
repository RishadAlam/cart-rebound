# Cart Rebound admin — per-element audit

Every screen of the plugin admin, reviewed control by control against a seeded store
(42 carts across all six statuses, 46 log rows, 119 email events forming a real 28/21/14
funnel, real WooCommerce orders and products) at 1440px and at a 390px phone viewport.

## How this was produced

The screens were rendered from the built bundle inside a faithful wp-admin shell — the
plugin's own `main.css` plus WordPress's own admin stylesheets and colour-scheme variables —
and fed responses dumped straight out of the plugin's REST controllers. Eight auditors read
one screen each; a second pass then tried to **refute** every finding: open the cited line,
read the surrounding comments, and reject anything that was taste, already handled, or
unsupported by its own evidence.

- 160 findings raised, 7 rejected on verification, 153 stand.
- **131 fixed** — every blocker, every major, and the whole minor tier bar the
  22 listed at the end, each with the reason it was left.
- Every entry carries a file:line and a concrete fix.

## Investigated and disproved

Worth recording, because each looks like a bug until it is checked:

- **Money renders as `1,234.00$`.** Correct — this store's `woocommerce_currency_pos` is
  `right`, and `formatMoney` mirrors the store's own display settings rather than guessing.
- **Checked checkboxes look empty.** They do not; WordPress paints them blue with a white
  tick. The first reading came from a harness missing `--wp-admin-theme-color`.
- **Sequence step counters are off by one.** They are not. `pro_events.step` is zero-based
  and both screens add one for display; verified against seeded rows as 28 / 21 / 14 down
  the funnel. A _different_ counter bug was real — disabled steps shifted the plan index.
- **The cart detail dialog does not trap focus.** It does — `showModal()` moves focus inside
  and `dialog.matches(':modal')` is true.
- **A price can wrap between amount and symbol.** It cannot: the separator is U+00A0.

## Fixed (131)

### Analytics

- `blocker` **whole screen — six metric tiles, revenue chart, both tables — while the pro/analytics request is in flight and after it fails** — The page reads only `analytics.data` and never touches `isLoading` or `isError`.
- `blocker` **Export CSV button → GET pro/analytics/export → downloaded file** — The controller returns the CSV document as the _data_ of a WP_REST_Response, so WordPress's REST server serialises it before echoing: `wp_json_encode()` wraps the whole document in quotes, escapes every `"` to `\"`, turns every CRLF into a literal `\r\n`, and rewrites the UTF-8 BOM as `﻿`.

### Carts

- `blocker` **Row action 4 — the red trash icon button (`.cr-iconbtn.is-danger`)** — The per-row delete fires straight into the mutation: no confirmation, no undo, and no success feedback.

### Rules

- `blocker` **The whole form + "Save rules" (Rules.tsx:28-33, 44-46, 80)** — On a licensed site, if `GET pro/settings` fails (expired REST nonce, 500, offline), `useProQuery` hands the screen the sample store instead of nothing, Rules seeds its form from it, no error is shown, and "Save rules" writes the sample store over the merchant's real settings..

### Sequence

- `blocker` **Whole screen (the Pro preview under the lock, and the loading window when licensed)** — `sampleProSettings()` is constructed inline inside the render body, so `settings.data` is a brand-new object on every render.

### Settings

- `blocker` **Save bar — "Save settings" submit button / save result region** — The save mutation's error state is never rendered.

### Analytics

- `major` **Export CSV button (toolbar, top right)** — The export chain has a `.then` and a `.finally` and no `.catch`.
- `major` **Range picker (7 days / 30 days / 90 days) → `isoDaysAgo`** — Both bounds come from `toISOString()`, i.e.
- `major` **'Recovered revenue' and 'Recovery rate' tiles** — Two tiles carry exactly the Dashboard's labels for different quantities — range-scoped here, lifetime there — and this screen's local `Metric` (a second copy of the component, despite the comment claiming otherwise) has no `hint` prop, so none of the six tiles explains itself.
- `major` **'Value at risk' tile, 'Revenue at risk and recovered' heading, chart legend swatch 'Recoverable revenue'** — One quantity is called three things on this screen and a fourth on the Dashboard.
- `major` **'Opened' and 'Clicked' columns, and the Sequence performance description** — One boolean gates two columns, and the server ORs the two independent settings.
- `major` **'Median time to recovery' tile** — With no recoveries in the window the reporter returns 0.0, and `formatHours` floors any sub-minute value at one minute — so the tile asserts a median time to recovery of '1 min' for a period in which nothing was recovered..

### Carts

- `major` **Bulk bar "Set status…" combobox, and the per-row inline status pill, when the chosen status is Abandoned** — Choosing "Abandoned" is presented as a plain data edit — one click on the combobox option fires the mutation with no confirmation — but the server routes that one status through the abandonment detector, which dispatches the abandonment event, opens the follow-up plan (queuing a real recovery email to the shopper), and resets `followup_step` to 0 so a cart that already received the whole sequence starts it again.
- `major` **Pagination bar + empty state, after rows are deleted from the last page** — Nothing clamps `page` when the total shrinks.
- `major` **Inline status pill → "Recovered" option (and the same option in the bulk bar)** — There are two ways to make a cart Recovered and they write different data.
- `major` **"Mark cart recovered" and "Send recovery email" dialogs — failure path** — Both dialogs route mutation errors to the page-level `notify`, and neither closes on failure.
- `major` **"Mark cart recovered" dialog — "Recent order" combobox vs "Or enter an order ID" number input** — Two live controls choose one value, and the typed one silently wins: `orderId = parsedCustom > 0 ? parsedCustom : parsedPicked`.
- `major` **Cart detail dialog → Timeline → "Completed" row** — Four of the five timeline rows pass their timestamp through `formatExact`, which converts the stored UTC value to the viewer's local time and formats it.
- `major` **The whole nine-column table at 390px (`.cr-table-wrap`)** — The screen has no phone adaptation at all — the only `@media (max-width: 782px)` rules in the stylesheet are for `.cr-templates` and `.cr-lock__panel`.
- `major` **Row/header selection checkboxes, the status pill trigger, and the four icon buttons, at 390px** — Every interactive element in a row is well under a 40px touch target, and one of them is smaller on a phone than it would be with no plugin CSS at all: `.cr-check input[type="checkbox"]` forces 15×15 with a specificity (0,2,1) that beats wp-admin's own `input[type="checkbox"] { height: 1.5625rem; width: 1.5625rem; }` inside its `max-width: 782px` block, so WordPress's 25px mobile checkbox is overridden back down to 15px.

### Dashboard

- `major` **"Lost orders" metric tile + its hint bubble** — The hint says the tile counts "Abandoned carts cleaned up without converting, plus paid orders later refunded or cancelled." The first clause describes a code path that does not exist: cleaned-up abandoned carts are _deleted_ rows, they are never moved to `lost`.
- `major` **Revenue over time — hover tooltip and hit area** — The per-day amounts exist only inside a hover tooltip.
- `major` **Revenue over time — legend, lines, dots and tooltip rows** — The two series are separated by hue alone, at identical lightness: `--cr-success: oklch(0.5 0.12 155)` and `--cr-warning: oklch(0.5 0.1 70)`.
- `major` **The six ⓘ hint buttons in the Overview strip** — These bubbles carry the definition of every metric — including the only explanation of why Recovery rate does not match the counts beside it — and they are unreachable in three ways.
- `major` **Recent activity and Product report tables at 390px** — The only responsive treatment for either table is `.cr-table-wrap { overflow-x: auto }`; neither `@media (max-width: 782px)` block in main.css touches tables.

### Log

- `major` **Table column header "Time (UTC)" and the timestamp cell under it** — The header promises UTC; the cell renders the viewer's local time.
- `major` **"Clear log" mutation result** — `clear.mutate` is given only an `onSuccess`; the mutation's error state is never read and there is no global mutation error handler.
- `major` **The five-column table at 390px** — At phone width the table keeps all five columns and overflows its scroll container.

### Rules

- `major` **Excluded roles / Excluded categories TokenPickers (Rules.tsx:34-40, 147-195)** — When `GET pro/options` fails the same fallback silently lists the sample store's roles and categories, whose ids are hardcoded literals; picking one writes that literal id into excluded_categories as if it were a real term..
- `major` **Screen as a whole — sample-backed render loop (Rules.tsx:28-33 + 44-46)** — `{ settings: sampleProSettings(), features: [] }` is constructed inside render and passed straight through by useProQuery, so `settings.data` is a new object on every render while the screen is sample-backed; the effect keyed on `[settings.data]` therefore re-runs forever, calling setForm with a fresh object each time.
- `major` **"Save rules" button / save bar (Rules.tsx:435-450)** — The save bar renders isPending and isSuccess and nothing else; `save.isError` is never displayed, so a rejected write leaves no trace.
- `major` **Form submit (Rules.tsx:78-81)** — Rules POSTs the entire ProSettings object — including `sequence_steps`, which this screen never edits — while Sequence posts only its own slice.
- `major` **Excluded roles / Excluded categories chips (TokenPicker.tsx:45-48; Rules.tsx:147-195)** — Chips are rendered by intersecting the saved values with the fetched options, so any saved value the options list does not contain is invisible — yet it is still POSTed on every save and still excludes carts.
- `major` **"Expires after (hours)" (Rules.tsx:274-290) and the shared numeric clamp (Rules.tsx:64-76)** — One clamp serves every numeric field and its floor is 0, so clearing the expiry field writes 0 — a value the same input declares invalid with `min={1}`.
- `major` **"Amount" (Rules.tsx:259-272), "Minimum cart total" (131-139), "Minimum spend" (300-308)** — "Amount" never says whether it means percent or money even though the same input serves both discount types, and it is the only field in the coupon grid with no hint.
- `major` **"Code prefix" (Rules.tsx:311-333)** — The input accepts anything; the server strips every non-alphanumeric character, uppercases, truncates to 16 and substitutes REBOUND when the result is empty.
- `major` **Coupon policy block under "Generate unique coupons" (Rules.tsx:216-363)** — With the master switch off, the five coupon fields and both coupon toggles have no effect whatsoever, yet they stay fully enabled, undimmed and unexplained..
- `major` **"Minimum spend" (Rules.tsx:292-309) against "Minimum cart total" (Rules.tsx:123-140)** — Nothing stops the coupon's minimum spend from exceeding the minimum cart total, so recovery emails can carry codes the recipient's own cart can never redeem.
- `major` **"Expires after (hours)" label and hint (Rules.tsx:274-290)** — The field is expressed in hours but cannot express anything shorter than end of the following UTC day: the minted coupon's WooCommerce expiry is rounded up a whole day, so 1, 6 and 12 hours all produce an identical coupon..
- `major` **"Keep email events for (days)" (Rules.tsx:397-431)** — This number also caps what the Analytics screen can ever report, and the hint frames it purely as housekeeping.
- `major` **"Who enters recovery" section description (Rules.tsx:115-120)** — The section promises that an excluded cart "still counts towards your revenue figures".
- `major` **Tab navigation while the form is dirty (Rules.tsx:42-62; Layout.tsx:80-99)** — Edits live only in component state and nothing guards navigation: switching tabs, or closing the tab, discards them without a prompt..
- `major` **"Excluded roles" / "Excluded categories" labels and the picker's filter box (Rules.tsx:143-146, 171-174; TokenPicker.tsx:104-116, 160-169)** — Both exclusion fields use a `<span>` where every sibling field uses a `<label htmlFor>`, so neither the collapsed button nor the filter input is associated with its field name.
- `major` **TokenPicker open state — option list, Escape, focus and announcements (TokenPicker.tsx:64-72, 102-171)** — The picker has no keyboard model and no announcements: the option list is plain buttons injected into the page tab order, Escape does not close it, arrow keys do nothing, the list carries no listbox/option roles, nothing is announced when a token is added or removed, and `remove()` destroys the focused chip without re-homing focus (while `add()` does refocus the search box)..
- `major` **Selected exclusion chip (TokenPicker.tsx:82-96; main.css:2872-2891)** — The chip is the delete control: its entire 113×23px surface removes the exclusion on a single tap, with no confirmation, no undo, and a destructive cue that exists only on hover..

### Sequence

- `major` **Step card stats line — "N waiting · M sent" (`.cr-step__stats`)** — The live counters are matched to a card by array position (`row.index === index`), but `removeStep` re-indexes the local array and `addStep` appends.
- `major` **Step card stats line — "N waiting · M sent" when any step is switched off** — The counters are grouped by the runner's plan index, but attributed to the configured position.
- `major` **"Remove step" link in each step card foot (`.cr-linkbtn.is-danger`)** — Remove step deletes the card on a single click with no confirmation, while the same card is displaying how many carts are waiting on that step.
- `major` **The editor as a whole / save bar (`.cr-savebar`)** — All edits live in component state that dies with the route.
- `major` **"Save sequence" button and the save-bar result message** — The save bar renders only the success case.
- `major` **Step list while `pro/settings` and `pro/sequence` are in flight (licensed store)** — The screen never reads `isLoading`.
- `major` **"Send after" duration field / step titles** — Two steps may hold the same delay.
- `major` **"Include a unique coupon" checkbox (`.cr-check`, `#cr-step-N-coupon`)** — The checkbox states an outcome it cannot guarantee and never shows the policy that decides it.

### Settings

- `major` **Recovery email → "Send delay" (`cr-delay`, DurationField)** — Once the Sequence add-on is licensed, `email_delay_minutes` has no effect whatsoever, but the field is presented as an ordinary live setting with no notice, no disabled state and no pointer to where the real timing lives.
- `major` **Whole screen — loading state** — The guard is `isLoading || !form`, and nothing handles the query's error state.
- `major` **Abandonment & cleanup → "Converted cart retention (days)" (`cr-converted-cleanup`) and its hint** — The hint names two statuses; the window actually governs three.
- `major` **Save bar → "Settings saved." (`cr-saved`)** — `update.isSuccess` stays true for the life of the component — nothing calls `update.reset()` and no dirty state exists — so the green "Settings saved." sits beside the Save button while the merchant edits five more fields.
- `major` **Whole form — navigation away / background refetch** — There is no dirty tracking, no `beforeunload` handler and no router guard, and the seeding effect re-runs on every new `data` object.
- `major` **Scan interval, Cleanup after, Converted cart retention (`cr-scan`, `cr-cleanup`, `cr-converted-cleanup`) and both DurationField numbers (`cr-threshold`, `cr-delay`)** — Every numeric handler coerces the intermediate empty state to 1 on each keystroke, so the field can never be blank while retyping.

### Templates

- `major` **Save bar → test-email input ("you@example.com")** — `.cr-savebar` is a non-wrapping flex row, and the test-email input is a `width: 100%` flex item with a max-width but no min-width or flex-shrink guard.
- `major` **Template list items / tab bar / Save button** — There is no dirty tracking anywhere on this screen.
- `major` **Feedback notice (success / error) vs. the save bar** — Every result on this screen — "Template saved.", "Template created.", "Default template set.", "Test email sent.", the required-fields validation error, and every server error — is rendered in one notice at the very top of the page, while all four buttons that produce those results live in the save bar at the very bottom of a ~1600px-tall editor.
- `major` **Save / Create template / Delete / Preview / Send test — error path** — `messageOf()` surfaces `error.message` from the rejected axios promise.
- `major` **Template list + editor — failed templates request** — `useTemplates()` is destructured for `data` and `isLoading` only; `isError` is never read and there is no error branch.
- `major` **From name / From email fields, and the preview dialog's sender row** — `From name` has no effect at all unless `From email` also holds a valid address — the mailer returns an empty header array and lets WordPress apply its own default sender.
- `major` **From email input** — The input is `type="email"` but lives outside any `<form>` and is saved by a `type="button"` click, so the browser never validates it.
- `major` **RichTextEditor → Insert link** — The Link button opens a bare `window.prompt` and passes whatever comes back straight to `execCommand('createLink')`.

### Analytics

- `minor` **Metric strip (six tiles) and both table headings** — Nothing on the screen says which dates the numbers cover.
- `minor` **Sent / Opened / Clicked / Recovered / Revenue and Abandoned / Recovered / Value lost cells** — Every numeric and money cell on this screen is left-aligned and none carries `cr-money`, while the identical columns on the Dashboard are right-aligned.
- `minor` **'Product' cell of Most abandoned products** — The name is plain text: no link to the product, and no fallback when the snapshot name is empty — whereas the Dashboard's identical column links to the editor and falls back to 'Product #%d'.
- `minor` **Revenue chart date axis — the final label** — The last label is centre-anchored on the plot's right edge, which sits only `PAD.right = 14px` from the SVG's width, so about a third of a 'MMM DD' label is painted outside the viewport and cut off.
- `minor` **Range pills and Export CSV button at a 390px viewport** — The screen's only two controls are 24px and 26px tall on a phone, and nothing in the stylesheet enlarges them below WordPress's 783px breakpoint, where the admin's own controls grow past 40px.
- `minor` **Revenue chart tooltip and plot** — The per-day figures exist only inside a pointer-driven tooltip that is `aria-hidden`; the SVG's accessible name is one sentence with no numbers, the plot is not focusable, and there is no table alternative.

### Carts

- `minor` **Feedback notice for row actions (status change, delete, send, mark recovered)** — Every row action reports its result in one fixed notice at the top of the page, above the table, which auto-clears after 4 seconds and is announced as `role="status"` for errors as well as successes.
- `minor` **Empty state inside `.cr-card` ("No carts yet")** — One empty state serves three different situations.
- `minor` **Total column for Recovered rows, and the detail dialog's meta list / items footer** — `recovered_amount` — the figure the Dashboard and Analytics actually count as recovered revenue — is returned on every cart and rendered nowhere on this screen.
- `minor` **Email column cell (`td.cr-cell-email`)** — The email cell is capped at 220px with `text-overflow: ellipsis` and carries no `title`.
- `minor` **Toolbar — the "Status" text beside the status-filter combobox** — The toolbar is a plain wrapping flex row, and the search box takes `flex: 1 1 200px` up to 340px.
- `minor` **"Mark cart recovered" dialog → "Recent order" option labels (`orderLabel`)** — The order picker formats money by hand — `order.total.toFixed(2)` with the currency _code_ appended — while every other amount on the screen goes through `formatMoney`, which honours the store's symbol, position, separators and decimal count.
- `minor` **Row action 3 — the mail icon button, when disabled** — The three reasons the send button can be dead are stated only in `title`, on an element that is `disabled` — so it is out of the tab order and unreachable by keyboard or touch, and its accessible name is fixed to "Send recovery email" regardless.
- `minor` **Bulk bar count — "%d selected"** — This is the one count string on the screen that goes through `__` instead of `_n`, so translators get a single form with a numeric placeholder and no way to supply the plural agreement their language needs.

### Dashboard

- `minor` **Overview card subtitle + the six metric tiles** — The card is headed "Lifetime recovery performance across every tracked cart", but five of the six tiles are live snapshots of the cart table, not lifetime figures.
- `minor` **"Recoverable orders" / "Recovered orders" / "Lost orders" tile labels** — All three tiles count rows in the cart-session table, and their own hints call them carts ("Abandoned carts that are still open to recovery"), yet the labels say "orders".
- `minor` **Revenue over time legend vs the Overview tiles** — The legend labels the curves "Recoverable revenue" and "Recovered revenue" — the exact names of two Overview tiles 350px above — but the chart's brown series is a different measure.
- `minor` **Revenue over time — value axis labels (small ranges)** — `compact()` rounds anything under 1000 to a whole number, but `niceStep()` happily returns fractional steps.
- `minor` **Revenue over time — final date tick** — Date labels are centred on their point and the last point sits at `width - PAD.right`, with PAD.right = 14.
- `minor` **Overview tile values at phone width** — `.cr-metric__value` sets `overflow-wrap: anywhere`, and at the 2-up 390px grid a formatted amount is wider than the tile, so the browser breaks inside the token: "20,620.00" on one line and "$" alone on the next.
- `minor` **Product report card header** — The sub-line says only "Last 30 days".
- `minor` **Overview count tiles, Recovery rate value, Product report count cells** — The page interpolates raw numbers where lib/format.ts already provides locale-aware helpers.

### Log

- `minor` **Event filter (Combobox, "All events") and the Event column cell** — The plugin writes four event keys; the filter offers three.
- `minor` **"Clear log" button and the "%d entries" count beside it** — `total` is the _filtered_ count, and it is rendered immediately to the left of the destructive button, so the button appears scoped to the current filter.
- `minor` **"Clear log" button disabled state** — The button's enabled state is computed from the filtered total, so any filter that matches nothing greys out Clear log even though the log is full..
- `minor` **Empty state ("Nothing logged yet")** — `isEmpty` ignores whether a filter is active and omits the `!!data` guard the Carts screen has.
- `minor` **Timestamp cell `title` tooltip** — The precise moment exists only in a `title` attribute.
- `minor` **Cart ID number input** — Any value that does not parse to a positive integer is silently coerced to "no filter": the box keeps showing what was typed while the query goes out unfiltered.
- `minor` **Cart ID input → table/pagination refresh** — Every keystroke and every filter change mints a new React Query key with no `placeholderData`, so the table drops to skeleton rows, the entry count disappears (it is guarded on `data`), and Pagination — which is still mounted because `isEmpty` is false while loading — renders "Showing 0–0 of 0" and "Page 1 of 1" beneath the skeletons.
- `minor` **Level/Event comboboxes, Cart ID input, Clear log, Previous/Next at 390px** — Every control on this screen uses the compact variants, which compute to roughly 30px tall, and the plugin's element-qualified input rule cancels WordPress's ≤782px 40px minimum for form controls instead of inheriting it.

### Rules

- `minor` **"Discount type" label + Combobox (Rules.tsx:230-257; Combobox.tsx:210-228)** — Field renders `<label htmlFor="cr-coupon-type">` but Combobox's trigger is a button with no id, so the label points at nothing — the one case on this screen where Field's own contract ("Must match the control's own id, or the label clicks nothing", Field.tsx:12) is broken..
- `minor` **Every hint on the screen (Field.tsx:40-44; Rules.tsx:126-129, 295-298, 314-317, 404-407)** — Field renders each hint inside `<p id={`${id}-hint`}>` but no control ever references that id, so the hint is orphaned markup as far as assistive tech is concerned..
- `minor` **First paint of the unlocked screen (Rules.tsx:28-46)** — There is no loading state at all: `settings.isLoading` and `options.isLoading` are never read, so while the request is in flight the screen shows the sample store's values as if they were the merchant's, and any edit made in that window is discarded when the response lands and the effect resets the form..
- `minor` **"Generate unique coupons" hint (Rules.tsx:216-227)** — The hint sends the merchant to a fallback the screen cannot verify: "Off means coupon steps fall back to the static code on the template." If no template defines a static code, the token resolves to an empty string and the coupon email goes out with no code..

### Sequence

- `minor` **Step number badge (`.cr-step__number`)** — Cards are sorted for display by delay, but the badge prints the pre-sort array index.
- `minor` **"Template" field label in each step card** — The label's `htmlFor` points at `cr-step-N-template`, but `Combobox` never receives an `id` — it renders a `<button>` with no id at all.
- `minor` **Enable switch in the step card head (`.cr-switch`, `#cr-step-N-enabled`)** — The switch has no visible text at all — the only cue that a step is on is the blue track, and the only cue that it is off is the card at 0.72 opacity.

### Settings

- `minor` **Abandonment & cleanup → "Count a cart as recovered when its order is" (On hold / Processing / Completed checkboxes)** — All three boxes can be unchecked and submitted.
- `minor` **All eight field hints on the screen (`cr-field__hint`) — every Field and ToggleField** — `Field` generates an id for its hint (`${id}-hint`) but no control is ever given `aria-describedby`, and `ToggleField`'s hint has no id at all.
- `minor` **Abandonment & cleanup → the "Count a cart as recovered when its order is" checkbox group (`cr-checks`)** — The group's question is a `<span>`, not a `<legend>` or `<label>`, and the group itself is a plain `<div>` with no `fieldset`, no `role="group"` and no `aria-labelledby`/`aria-describedby`.
- `minor` **"Send delay" number box versus "Abandonment threshold" number box** — The same control (DurationField) renders at wildly different widths in the two sections.
- `minor` **"Send delay" and the Templates note, while "Send recovery email" is off** — `recovery_email_enabled` is the master switch — with it off, the runner returns an empty plan and nothing is ever scheduled — yet the Send delay stays fully editable and the "Automatic recovery emails use the template marked default" note stays as an unqualified statement of fact.
- `minor` **"Count a cart as recovered when its order is" hint** — The hint — "Order statuses that mark a tracked cart as paid and attributed." — restates the widget in jargon ("attributed") and omits the one thing that surprises merchants: attribution happens only at order creation and on subsequent status _transitions_, so ticking a status does nothing for orders already sitting in it..
- `minor` **Recovery email → "Notification email" (`cr-admin-email`)** — The field is `type="email"`, so the browser accepts addresses WordPress then rejects (`sanitize_email` requires at least six characters and a dotted domain, so `abc@de` or `sales@localhost` become an empty string).
- `minor` **Paid-status checkboxes on a phone (`.cr-check` / `.cr-check input`)** — The checkboxes are pinned to 16x16px at every width, and the label row they sit in is only about 20px tall.

### Templates

- `minor` **Delete button + its confirm dialog** — The delete confirmation names the template and nothing else.
- `minor` **Merge-tag documentation — {coupon_code}, and the missing {coupon_expiry} / {coupon_amount}** — The docs state flatly that `{coupon_code}` is "The coupon code selected below".
- `minor` **Merge-tag documentation — {recovery_url} and {products}** — `{recovery_url}` is documented as "A one-click link", but the mailer substitutes a bare URL string, not an anchor — whether it becomes clickable is left to the mail client's autolinking.
- `minor` **Body editor + "Set as default" + Save (new template path)** — Save validates only that Name and Subject are non-empty.
- `minor` **Merge-tag documentation — missing {last_name} and {unsubscribe_url}** — The mailer substitutes two tokens the screen never mentions: `{last_name}` in the text-token map and `{unsubscribe_url}` in the body substitution pass.
- `minor` **Delete button when the selected template is the default** — The default template's Delete button is disabled, and the reason lives only in a `title` attribute.
- `minor` **Template list items** — The list is a stack of plain `<button>` elements whose selected state is carried entirely by the `is-active` class.

### Carts

- `polish` **Cart detail dialog `<dialog className="cr-dialog is-wide cr-detail">`** — Two of the three dialogs on this screen close when the backdrop is clicked; the detail dialog does not, and it also has no header close affordance — its only visible exit is the button in the pinned footer.
- `polish` **Sortable column headers — first click on Total, Items, Last activity, Order** — Switching to a new sort column always starts ascending.
- `polish` **Items column (table) vs Qty column (detail dialog) vs Total (both)** — The same quantity is aligned differently in the two places it appears — left in the table's Items column, centred in the detail dialog's Qty column — while Total is right-aligned in both.

### Dashboard

- `polish` **Revenue over time — value axis labels** — The value axis reads 0 / 5K / 10K / 15K / 20K with no currency and no unit stated anywhere in the card, while every other money figure on the page renders as "20,620.00$".
- `polish` **7d / 30d / 90d range picker** — The picker sits in the "Revenue over time" header and is announced as `aria-label="Chart time range"`, but the same `days` state also drives the Product report card lower on the page.
- `polish` **Recent activity — "Last activity" cell** — The cell renders `formatWhen()` with no `title`, so "1 hour ago" cannot be resolved to a clock time.

### Log

- `polish` **Cart column cell (`#42`)** — The cell prints an identifier as inert text, and nothing anywhere in the admin can look a cart up by that identifier — the Carts screen filters by status and email only, and the repository's only search is a LIKE on email.
- `polish` **Screen heading, table accessible name, entry-count region** — The routed content starts at a toolbar: there is no heading for the Log screen (the only `h1` is the shell's product name), the table has no `<caption>` or `aria-label`, and the entry count is a plain `<span>`, not a live region.

### Rules

- `polish` **Save bar on a phone (main.css:1719-1727; Rules.tsx:435-450)** — The card is 2136px tall at a 390px viewport and the only Save button sits at the very bottom, with nothing sticky..

### Sequence

- `polish` **"Send after" control: number input beside the unit picker (`.cr-duration`)** — The two halves of one control do not match.
- `polish` **"Add a step" button** — At 20 steps the button is unmounted rather than disabled, so the merchant's only way to add a step vanishes with no explanation of the limit..

### Templates

- `polish` **Merge-tag documentation — {coupon_code} wording** — The description tells the merchant the coupon is "selected below", but the Coupon combobox sits above the body editor and its documentation, in the top field grid next to Template name..

## Deliberately not changed (22)

Each of these is real; none is a defect a merchant hits in ordinary use, and each would
change behaviour, an API payload, or a product decision that is not a cleanup pass's to
make.

**Dashboard — "Recovery rate" metric tile** (`dashboard-recovery-rate-no-denominator`, minor)

The rate is computed from lifetime option counters (`lifetime_recovered / lifetime_abandoned`) while the two tiles beside it show the current-snapshot counts. In the seeded store the tile reads 26.6% next to "Recovered orders 12" and "Recoverable orders 14". No denominator is shown anywhere, and the only explanation is inside a hover bubble that a phone cannot open (see dashboard-hint-target-and-hover).

_Left because:_ The denominator is a lifetime counter with no UI anywhere; surfacing it needs an API addition.

_If picked up:_ Cheapest honest fix needs no server change: render a muted second line in the tile reading __('Since install', 'cart-rebound') so the basis is visible without hovering. Only add lifetime_abandoned/lifetime_recovered to the Stats payload if you actually want the fraction printed.

**Dashboard — The whole dashboard (stats, chart, both report tables)** (`dashboard-no-refresh-stale-numbers`, minor)

Nothing on this screen ever refreshes. The query client sets `refetchOnWindowFocus: false` and no query on the page sets `refetchInterval`; there is no Refresh control and no "as of" stamp. The relative timestamps are computed inside render from `Date.now()`, so they freeze too.

_Left because:_ Needs a refresh policy decision (polling interval or a manual control) that belongs to the product, not to a cleanup pass.

_If picked up:_ Add `refetchInterval: 60_000` (and `refetchIntervalInBackground: false`) to `useStats`, `useTimeseries`, `useProductReport` and the dashboard's `useCarts` in hooks/useApi.ts, and put a muted "Updated <relative time>" line plus a Refresh button in the Overview header wired to `stats.refetch()` and siblings. A one-minute tick also keeps `formatWhen` honest.

**Dashboard — Stats failure notice; Recent activity and Product report error rows** (`dashboard-error-states-dead-ends`, minor)

A failed `/stats` request replaces the entire dashboard with a bare notice, discarding the chart, Recent activity and Product report — three independent requests that may well have succeeded — and offers no retry, so the only recovery is reloading wp-admin. The two report-level failures are plain `<div>`s with no `role`, no retry, and no distinction between "the request failed" and "there is nothing here".

_Left because:_ The dashboard already reports a failed /stats clearly; keeping the other three panels alive around it is a restructure worth doing deliberately.

_If picked up:_ Keep the layout on a stats failure: render the error inside the Overview card (leaving the chart and both reports mounted) with a `cr-btn` "Try again" bound to `stats.refetch()`. Give both report errors `role="status"` and the same retry button bound to their own `refetch()`, and keep the empty-state copy visually distinct from the error copy.

**Carts — Cart detail dialog — the footer "Close" button** (`carts-detail-dialog-loses-focus-on-close`, minor)

`CartDetail` returns `null` when there is no cart, so closing it unmounts the `<dialog>` rather than closing it. The effect that would call `el.close()` runs after the node is gone and bails on `if (!el) return`. Because `close()` never runs, the browser never restores focus to the element that opened the dialog. Its two siblings do not early-return, so their Cancel buttons do reach `el.close()` and do restore focus — and pressing Escape here also works, since that path closes natively before React unmounts. Only the visible Close button loses focus.

_Left because:_ The dialog unmounts on close, so focus returns to the document. Restoring it to the row needs a ref map the table does not keep.

_If picked up:_ Keep the dialog mounted: delete the `if (!cart) return null;` guard and make the body render conditionally instead (`{cart && (<>…</>)}`), matching the pattern the other two dialogs already use, so `el.close()` runs and the browser returns focus to the row's View button.

**Templates — RichTextEditor formatting toolbar** (`templates-rte-toolbar-tab-trap`, minor)

The bar declares `role="toolbar"` but every one of its 25 controls stays in the tab order and there is no arrow-key handling. Declaring the role while omitting the roving-tabindex behaviour is worse than not declaring it: assistive tech announces "Formatting toolbar" and the user reaches for arrow keys, which do nothing. On top of that every button is a 28×28 target.

_Left because:_ A roving-tabindex toolbar is a genuine rewrite of the editor chrome; worth doing, not worth rushing at the end of a sweep.

_If picked up:_ Implement the roving tabindex + ArrowLeft/Right/Home/End on `.cr-rte__bar` (RichTextEditor.tsx:439) — that is the whole defect, and it also collapses the 25 tab stops to one. Drop the 44px claim; if you raise the buttons, do it for comfort (32px desktop / 40px under `@media (max-width: 782px)`), not for conformance.

**Templates — RichTextEditor → contextual image toolbar (size / align / remove)** (`templates-rte-image-controls-mouse-only`, minor)

The image toolbar renders only while `selectedImg` is set, and `selectedImg` is set from exactly one place: a click on an `<img>` inside the editable region. There is no keyboard route to selecting an image, so resize, align and remove are mouse/touch-only controls.

_Left because:_ Same rewrite as the toolbar above.

_If picked up:_ Keep only the caret derivation: in `refresh()` (RichTextEditor.tsx:221-224) inspect the current range and set `selectedImg` when it wholly contains a single `<img>`, which is what Shift+Arrow produces — that alone makes resize/align keyboard-reachable. Add `aria-live="polite"` on `.cr-rte__imagebar`. Drop the width:100% change.

**Templates — Coupon combobox options** (`templates-coupon-options-hide-discount`, minor)

The option labels use only the coupon code and its WooCommerce excerpt, which is empty on most coupons. The endpoint already returns `amount` and `type` for every coupon and the UI discards both, so the merchant chooses the discount their recovery emails will hand out with the discount value invisible.

_Left because:_ Needs the coupon amount in the /coupons payload.

_If picked up:_ As proposed for the label. Treat the `date_expires` addition as a separate finding — CouponsController.php:44-53 queries `post_status => 'publish'` with no expiry filter, so an expired coupon really is offered as live, but that is a server-side omission with its own fix, not part of the label change.

**Templates — RichTextEditor → Heading / Subheading buttons** (`templates-rte-heading-buttons-mislabelled`, minor)

The two block buttons show the captions "H1" and "H2" but emit `<h2>` and `<h3>`, while their tooltip and accessible name say "Heading" and "Subheading". Three different names for the same control, and the visible one names a tag the editor never produces.

_Left because:_ The captions match what merchants expect of an editor; renaming them to H2/H3 would be accurate and more confusing.

_If picked up:_ As proposed. Prefer the second option (captions 'Heading' / 'Subheading') — it fixes 2.5.3 as well as the lie about the emitted tag, and RichTextEditor.tsx:559/565/571/586 already use words ('Link', 'Unlink', 'Image', 'Clear') in the same bar.

**Templates — RichTextEditor empty-state placeholder** (`templates-rte-placeholder-untranslatable`, minor)

The body editor's placeholder text is a hard-coded English string inside a CSS `content` declaration, so it never passes through `__()` and cannot be translated.

_Left because:_ The placeholder lives in a CSS content property; moving it into JSX changes the editor structure.

_If picked up:_ As proposed. The custom-property route works; just guard the fallback (`content: var(--cr-rte-placeholder, "")`) so an unset property renders nothing rather than the literal token.

**Sequence — "Send after" number input and unit picker (`DurationField`)** (`sequence-delay-unit-clamp`, minor)

Two silent value changes. Clearing or zeroing the number snaps the delay to 1 of the current unit with no message — with "days" selected an empty field instantly becomes a 1-day delay. And changing the unit keeps the number and multiplies the delay: picking "hours" on a 3-day step turns 4320 minutes into 180, a 24× retiming from one dropdown selection, with no confirmation and nothing to undo it with.

_Left because:_ Largely addressed: the field now holds a half-typed value. The unit multiplier is inherent to storing minutes.

_If picked up:_ Hold the typed text in local state inside DurationField so an empty or in-progress value stays on screen, commit to `onChange` only on a valid parse ≥ 1, and while it is invalid render the existing error slot: `<p className="cr-field__hint is-error" role="alert">{__('Enter a whole number of 1 or more.', 'cart-rebound')}</p>`. Leave the unit handler exactly as it is.

**Analytics — 'Step' column of the Sequence performance table** (`analytics-step-row-does-not-identify-the-email`, minor)

The screen's promise is 'See which email earns its send', but the row names no email — just 'Step 2' — and links nowhere. The number is the position the mail was sent at, so after a step is reordered or deleted the historic row points at a different email than the one now in that slot. The Sequence screen already holds the template name for every step.

_Left because:_ Needs the template name per step in the analytics payload.

_If picked up:_ Either record what was actually sent — add a `template_id` column to pro_events and carry it into the step row, which is the only version that survives a reorder — or keep the index and make the mapping cheap and honest: label the cell `Step 2` with the current sequence's name for that position plus a link to `/sequence`, and state in the section description that steps are identified by their position at send time. Do not resolve the name from current config and present it as the email that was sent.

**Analytics — 'Abandoned' and 'Recovered' columns of Most abandoned products** (`analytics-product-columns-mean-something-else-than-dashboard`, minor)

The two screens count identically-named columns over different populations. The free report scans carts abandoned _or_ recovered since the cutoff and counts a recovery only when `recovered_at` falls inside the window; the pro report takes only carts whose `abandoned_at` is inside the window and counts a recovery whenever `recovered_at` is non-empty, whatever its date. Neither table states its definition.

_Left because:_ Both screens are now explicit about their window; reconciling the populations is a reporting decision.

_If picked up:_ Pick one definition — recoveries that happened inside the window is the one that matches the range picker — implement it in both places, and put it in the section description: 'Carts abandoned in this range; Recovered counts the ones that came back within it.'

**Analytics — 'Recovery rate' tile** (`analytics-recovery-rate-mixes-cohorts`, minor)

The rate divides carts recovered inside the window by carts abandoned inside the window — two different sets of carts, since a cart abandoned before the window can recover inside it. Nothing caps or explains the result, and the identically-labelled Dashboard tile is a lifetime cohort rate that cannot exceed 100%.

_Left because:_ A cohort-correct rate is a change to what the number means, which the maintainer should choose.

_If picked up:_ Either measure the cohort (recoveries _of carts abandoned in the window_) or keep this definition, say it in the tile's hint, and clamp the display at 100%. Put both screens' percentages through `formatPercent` so one of them cannot start showing raw decimals.

**Log — Event column cell (`<code className="cr-code">`)** (`log-event-cell-uses-filter-copy`, minor)

`eventLabel` reuses the filter option strings as row labels, so a row that records one send reads "Emails sent", and "All events" would be a legal label for the empty key. Any key not in the array (an add-on's, or the plugin's own `email_failed`) is printed raw, untranslated, in a `<code>` element — prose dressed as code, which is also what a screen reader announces.

_Left because:_ Row and filter share one vocabulary on purpose; separate copy for each would drift.

_If picked up:_ Add a second map for cells — `EVENT_LABELS = { email_sent: __('Email sent'), email_failed: __('Email failed'), abandoned: __('Abandoned'), recovered: __('Recovered') }` — and have the cell use it, falling back to a humanised key (`event.replace(/_/g,' ')`) so an add-on's event still reads as words. Keep `<code>` only for the unrecognised fallback, or drop it and use `.cr-tag` so a human phrase is not marked up as code.

**Settings — Abandonment & cleanup → "Abandonment threshold" (`cr-threshold` + unit select)** (`settings-threshold-unbounded`, minor)

The number has `min={1}` and no maximum, and the unit dropdown multiplies it by 1440. "300" + "days" is two interactions away and produces a cutoff earlier than any cart's last activity, so no cart is ever marked abandoned and no recovery email is ever scheduled — a silently switched-off plugin with every toggle still reading "on". At the other end there is no guidance that a threshold shorter than a typical checkout means carts flip to abandoned while the shopper is still on the payment page (the seeded screenshot shows 5 minutes).

_Left because:_ Now warns at a day or more rather than refusing a value the merchant may want.

_If picked up:_ Add an optional `maxMinutes` to `DurationField` and clamp it in `toMinutes` (DurationField.tsx:47-59), passing a ceiling of 30 days at Settings.tsx:145-155, plus `max` on the `<input>` (DurationField.tsx:76-81) so the browser refuses it too; mirror the ceiling server-side at Settings.php:120 (`min( 43200, max( 1, … ) )`) since the REST route accepts any integer. Drop the 'mailing mid-checkout shoppers' note — Runner.php:332 and OrderLinker.php:337 prevent that send. If a low-value warning is still wanted, phrase it against the real cost: a threshold below ~15 minutes marks carts abandoned while shoppers are still paying, which inflates the abandoned count on Dashboard and Carts.

**Dashboard — Range picker → chart and Product report loading states** (`dashboard-range-switch-blanks-chart`, polish)

`useTimeseries(days)` and `useProductReport(days, …)` key on `days`, so every range click is a cache miss: `series.isLoading` flips true and the chart is replaced by a 288px grey block, and the product rows become skeletons, for the length of a round trip.

_Left because:_ Fixed in effect for the tables via placeholderData; the chart still remounts on a range change, which reads as a redraw rather than a fault.

_If picked up:_ Add `placeholderData: (previous) => previous` to `useTimeseries` and `useProductReport` and dim the card with a `is-refetching` opacity while `isFetching`, so the previous range stays on screen until the new one lands and nothing reflows.

**Carts — Total column (`.cr-money`), and every money value in the detail dialog** (`carts-total-column-ignores-per-row-currency`, polish)

The page reads one currency for all rows from the list envelope and passes it to `formatMoney` for every cart, discarding the per-row `currency` the API returns. `formatMoney` treats a matching code as permission to apply the store's full WooCommerce price format — symbol, position, decimal count, thousands separator — so a row denominated in something else is not merely relabelled, its digits are reformatted. The plugin creates this mixed-currency condition itself: `mark_recovered` writes the linked order's currency onto the cart row.

_Left because:_ A multi-currency store needs a per-row currency in the list payload; that is an API change with migration consequences.

_If picked up:_ Do not swap in `cart.currency` at the call site — that fixes the label by breaking the pair. Either (a) leave it as the documented store-currency contract, or (b) if historical rows must be honest, stop overwriting `currency` on conversion (CartRepository.php:452, OrderLinker.php:357 — move the order's code to a separate column beside recovered_amount) and only then pass the row's code for cart_total, updating the CartList.currency contract in types/api.ts:47-48 and the get_carts docblock at the same time.

**Carts — Row action 2 — the "Mark recovered" link icon** (`carts-recover-action-hidden-instead-of-disabled`, polish)

Three of the four row actions are always rendered and go dim with a `title` explaining why; the fourth is removed from the DOM entirely when the cart already has an order. Because `.cr-row-actions` is right-aligned, dropping a button shifts the remaining ones, so the View button sits in a different column depending on the row.

_Left because:_ Hiding the recover icon on a cart that already has an order is defensible — the action is meaningless there, not merely unavailable.

_If picked up:_ Always render the button, disabled, with a reason like the mail button has: `title={ cart.order_id > 0 ? sprintf( __( 'Already linked to order #%d', 'cart-rebound' ), cart.order_id ) : __( 'Mark recovered', 'cart-rebound' ) }`. Four fixed icon columns on every row, and the disabled state teaches the merchant why.

**Carts — Feedback notice — the `cr-notice--inset` modifier** (`carts-page-notice-inset-misaligned`, polish)

The page-level feedback notice carries a modifier whose stated purpose is a notice sitting _inside_ a card or dialog. Applied to a top-level sibling it becomes a 16px margin on all four sides, so the notice is indented from both page edges while the toolbar, status guide, bulk bar and card above and below it are flush.

_Left because:_ Cosmetic margin difference, now moot: the notice is sticky and reads as attached to the card.

_If picked up:_ Drop `cr-notice--inset` from Carts.tsx:1388 and give the page-level notice the stack's own rhythm — `margin-bottom: 12px`, matching `.cr-bulkbar` (main.css:934-944) — so it lines up with the toolbar and the card.

**Templates — RichTextEditor writing canvas at 390px** (`templates-rte-body-column-too-narrow-on-phone`, polish)

The canvas and the email sheet each add fixed padding that is never reduced at phone width: 20px on `.cr-rte__canvas` plus 28px/32px on `.cr-rte__content`, inside a card that already has its own padding. The usable writing column drops to roughly 170px.

_Left because:_ The editor is usable at 390px; tightening its padding is taste rather than defect.

_If picked up:_ Keep the padding reduction under `@media (max-width: 782px)`. Do not lift `max-height` on `.cr-rte__canvas`: removing it makes a long body push the merge-tag legend, sender fields and the entire save bar off the bottom of a 390px screen, which is a worse trade than a nested scroll region. Cap it lower instead (e.g. 60vh).

**Analytics — Most abandoned products (table + its description)** (`analytics-product-table-silently-truncated`, polish)

The description promises a ranking 'by the value left behind' across the selected range, but the query reads only the 2,000 most recent abandoned carts and returns at most 10 products. Neither the response nor the UI says the scan was capped.

_Left because:_ The server caps at ten; saying so needs the cap in the payload.

_If picked up:_ If it is worth the payload, return `scanned`/`truncated` from both product tallies and append the caveat to both section descriptions — Analytics.tsx:324-329 and Dashboard.tsx:358-366 — so the two screens disclose the same bound. Fixing only the pro side would leave the free report making the stronger, unqualified claim.

**Analytics — Opened / Clicked / Recovered cells of the Sequence performance table** (`analytics-open-click-cells-untranslatable`, polish)

Each cell glues a count, a middle dot and a percentage together in a template literal, so a translator can neither reorder the parts nor change the separator, and there is no translator comment. The header says only 'Opened', so nothing states that the second figure is a share of that step's sends.

_Left because:_ The glued "12 · 40%" cell is punctuation, not prose; splitting it into a translatable string is possible but low value.

_If picked up:_ State the denominator once, in the section description at Analytics.tsx:240-250 ('Rates are a share of that step's sends'), and leave the cells short. If the separator is also to become translator-controlled, do it as one shared helper used by all three cells rather than three inline sprintf calls, so the Recovered cell at :301-304 does not keep its own third shape.
