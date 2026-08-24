# Cart Rebound — Manual QA Pass

**Date:** 2026-08-24
**Build tested:** 1.1.1 (`cart-rebound` on a live WooCommerce store)
**Method:** driven through a real Chrome session against a running WordPress + WooCommerce install — clicking the admin like an operator would, and shopping through the storefront in isolated browser contexts like a customer would. Backend state was verified against the database after every step.

Run in two passes. The first covered the everyday paths an operator and a shopper take. The second went after everything the first deliberately skipped: role and capability enforcement, the classic (shortcode) checkout, variable products, the cleanup job, activation/uninstall, WordPress.org release compliance, accessibility, right-to-left, internationalisation, the Pro add-on, and behaviour at 5,000 carts.

Automated checks (php-cs-fixer, phpcs, PHPStan, PHPCompatibility, Rector, PHPUnit, tsc, Prettier, ESLint, Stylelint) were green before this pass and are green after it. **Every defect below was invisible to them** — they are behavioural, which is why the pass was driven by hand.

---

## Scope covered

| Area                 | Exercised                                                                                                                                |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Onboarding           | All four wizard steps, Skip, Finish, and the settings it writes                                                                          |
| Dashboard            | Metrics, 7/30/90-day chart, recent activity, product report                                                                              |
| Carts                | Filter, sort, paginate, detail dialog, inline status change, send email, mark recovered, single delete, bulk status, bulk delete         |
| Templates            | Message editor, merge-tag reference, preview, test send, product-table options, delivery/sender/coupon                                   |
| Log                  | Filters, pagination                                                                                                                      |
| Settings             | Save, client and server validation, out-of-range and hostile input                                                                       |
| Storefront           | Guest cart tracking, live email/name/phone capture at block checkout, recovery link, unsubscribe                                         |
| Lifecycle            | active → abandoned → pending-payment → recovered / completed / lost, plus order reversal                                                 |
| Cron                 | Abandonment detector, Action Scheduler queue                                                                                             |
| Email                | Automatic send, manual send, test send, admin recovery notification, mail-transport failure path                                         |
| REST                 | Unauthenticated access to every route, nonce enforcement, invalid payloads, pagination limits                                            |
| Privacy              | Personal-data exporter and eraser registration and output                                                                                |
| Responsive           | 390 × 844 mobile viewport                                                                                                                |
| Roles                | Editor and Subscriber (no `manage_woocommerce`) and Shop Manager, against the admin screens and all nine REST routes, with a valid nonce |
| Checkout modes       | Classic (`[woocommerce_checkout]` shortcode) as well as block / Store API, each end to end to a paid order                               |
| Products             | Simple and variable, including variation attributes through capture, snapshot, recovery and reorder                                      |
| Coupons              | A live coupon and a deleted one restored together through a recovery link                                                                |
| Jobs                 | Janitor retention sweep across all six statuses; migration re-run; deactivate / reactivate cycle                                         |
| Install lifecycle    | `Plugin::uninstall()` executed for real against a backed-up database, then restored                                                      |
| Compliance           | WordPress Plugin Check against the built release archive                                                                                 |
| Accessibility        | Lighthouse audit plus measured WCAG contrast ratios for every badge, chip, link and button                                               |
| Internationalisation | POT completeness (including strings inside the minified bundle) and a full RTL pass in Arabic                                            |
| Add-on               | Cart Rebound Pro activated alongside                                                                                                     |
| Scale                | 5,042 carts across every status, with all read endpoints timed                                                                           |

---

## Defects found and fixed

### 1. A failed dialog action reported nothing at all — **fixed**

**Severity:** high (silent data-affecting failure)

**Reproduced:** with no working mail transport, clicking **Send email** on cart #1 returned `HTTP 200 {"sent":false,"message":"WordPress could not send the email: Could not instantiate mail function."}`. The dialog stayed open, unchanged. Nothing indicated failure.

**Cause:** both `SendDialog` and `RecoverDialog` reported errors through the page-level notice. That notice renders _behind_ the open modal `<dialog>` backdrop, and clears itself after four seconds — so by the time the operator dismissed the dialog, the error was already gone. The operator's reasonable conclusion is "it sent".

**Fix:** the mutation error is now rendered inside the dialog itself (`DialogError`), and the mutation resets when the dialog opens for a new cart. Verified live: sending to an unsubscribed address now shows _"This address has unsubscribed from recovery emails."_ in the dialog.

`resources/js/admin/pages/Carts.tsx`, `resources/js/admin/styles/main.css`

---

### 2. A shopper who returned without the link lost their recovery attribution — **fixed**

**Severity:** high (silently under-reports the plugin's headline metric)

**Reproduced end to end:**

1. Guest adds a product, types an email at checkout → cart #47 tracked.
2. Detector marks it **abandoned** — lifetime abandoned counter increments, log records it.
3. Shopper returns to the store by browsing (no recovery link) → cart returns to **active**.
4. Shopper checks out and pays.
5. **Result: status `completed`, `recovered_amount` 0.00.**

**Cause:** `CartTracker::upsert()` set `abandoned_at = null` when reopening an abandoned cart. `OrderLinker::link()` decides recovered-vs-completed on exactly that column, with the comment _"a cart that was ever abandoned … counts as recovered"_ — but "ever" was no longer true. The same nulling happened in `OrderLinker::on_reversal()` when a cancelled order returned a pending-payment cart to active.

The visible damage: recovered revenue understated, recovery rate deflated (the abandonment was already counted on the denominator), and the cart labelled with a status the plugin's own in-app guide defines as _"converted to a paid order **without ever being abandoned**"_.

**Fix:** `abandoned_at` is preserved on reopen in both places — it is the historical marker, not the current-state flag (`abandonment_notified` already plays that role). Verified live: the same flow now ends `recovered`, `recovered_amount` 500.00, with the order note _"Cart Rebound: recovered cart #49 via direct return."_ Regression test added (`tests/Unit/CartTrackerTest.php`).

`src/Tracking/CartTracker.php`, `src/Recovery/OrderLinker.php`

---

### 3. Clicking a recovery link created a duplicate cart — **fixed**

**Severity:** high (duplicate records, duplicate emails to the same shopper)

**Reproduced:** cart #3 (`k.tanaka@example.co.jp`, abandoned) had its recovery link opened in a clean browser. Restoring the cart made `CartTracker` open **cart #48** for that session, while cart #3 stayed abandoned. The shopper then typed their email at checkout, which back-filled #48. The detector abandoned #48 too, and Action Scheduler ended up holding a second `cart_rebound_send_recovery_email` job for the very shopper who had just clicked the first email:

```
#60919 args=[48] at=2026-08-24 15:57:12
```

Two abandoned rows, two emails, one cart.

**Cause:** `RecoveryHandler` restored the cart into the visitor's session but never associated the recovered row with that session, so the tracker saw an untracked cart and opened a new row.

**Fix:** `RecoveryHandler::adopt_session()` re-points the recovered row's `session_key` at the visitor's tracking key _before_ the cart is rebuilt, archiving any row already holding that key using the same deterministic rename `CartTracker` already uses for terminal rows. Verified live twice: no new row is created, the original row is reused, and its history is intact.

`src/Recovery/RecoveryHandler.php`

---

### 4. The recovery link dropped the shopper on an empty checkout form — **fixed**

**Severity:** medium (defeats the point of a one-click recovery link)

**Observed:** clicking the recovery link for cart #1 rebuilt the cart correctly but landed on checkout with **Email address blank**, first and last name blank, and _"You are currently checking out as a guest."_ The plugin already stored `email`, `first_name`, `last_name` and `phone` for that cart — it emailed the shopper at that address — and then asked them to type it all again.

**Fix:** `RecoveryHandler::prefill_customer()` seeds the WooCommerce customer from the tracked row, writing only fields that are still empty so a logged-in customer's saved details are never overwritten. Both billing and shipping name/phone are filled, because the block checkout compares the two to decide whether to keep _"use the same address for billing"_ ticked — filling one side only splits the form into two addresses. Verified live: checkout opens with email, name and phone in place and a single address form.

`src/Recovery/RecoveryHandler.php`

---

### 5. Deleted coupons greeted returning shoppers with a red error — **fixed**

**Severity:** medium (first impression on the recovery landing page)

**Observed:** a cart captured with coupons `spring5` and `freeship` was recovered weeks later. The first thing on the page was:

> **The following problems were found:**
> Coupon "spring5" cannot be applied because it does not exist.
> Coupon "freeship" cannot be applied because it does not exist.

Coupons expire and get deleted; carts outlive them. Re-applying a dead code cannot succeed and only produces an error block.

**Fix:** `restore_cart()` checks `wc_get_coupon_id_by_code()` before applying, so only live codes are restored. Verified live: the same cart now opens clean.

`src/Recovery/RecoveryHandler.php`

---

### 6. Every admin page shipped the user's entire capability list — **fixed**

**Severity:** medium (page weight, needless exposure)

**Observed:** `window.CartRebound.currentUser.caps` carried every capability granted to the current user. On the test store — an administrator on a site with many plugins — that inline `<script>` was roughly **40 KB**, on every Cart Rebound admin page load. Nothing in the admin bundle read `currentUser` at all; a search across both the free and Pro plugins found zero consumers.

**Fix:** `currentUser` and the `current_user_caps()` helper are gone. Verified live: the boot payload is now four keys and **394 bytes**.

`src/Providers/AssetServiceProvider.php`, `resources/js/admin/types/wp.d.ts`

---

### 7. `session_key` was serialised into every REST cart row — **fixed**

**Severity:** low–medium (needless exposure of a session identifier)

**Observed:** `GET /cart-rebound/v1/carts` returned `session_key` for every row — the visitor's tracking session identifier — even though no admin view renders it and no Pro code reads it.

**Fix:** removed from `CartRepository::present()` and from the `Cart` TypeScript type. The value is still available server-side to hooks via the `session_id` event-payload field, which is unchanged. Verified live: the field no longer appears in the response.

`src/Data/CartRepository.php`, `resources/js/admin/types/api.ts`

---

### 8. `paid_order_statuses` accepted anything — **fixed**

**Severity:** low (settings integrity)

**Reproduced:** `POST /cart-rebound/v1/settings` with `{"paid_order_statuses":["bogus","wc-processing","processing"]}` stored all three. A script payload was defanged by `sanitize_key()` (`<script>alert(1)</script>` → `scriptalert1script`) so this was never an XSS, but arbitrary junk could sit in the setting indefinitely, and a `wc-` prefixed slug never matched `has_status()` — quietly disabling attribution for that status.

**Fix:** `Settings::sanitise_statuses()` now strips a `wc-` prefix and validates against `wc_get_order_statuses()`, discarding anything WooCommerce does not register and falling back to `['processing','completed']` if nothing survives. The whitelist step is skipped (rather than rejecting everything) when WooCommerce is unavailable. Two regression tests added.

`src/Support/Settings.php`, `tests/Unit/SettingsTest.php`

---

### 9. Two UI colours failed the WCAG AA contrast floor — **fixed**

**Severity:** medium (accessibility)

**Measured:** the accent blue used as text on its own pale background came out at **4.37:1**, under the 4.5:1 AA floor for 12px type. It affected the **Pending payment** badge — which sits in the cart list permanently — and the coupon chip in the cart detail dialog. Every other badge measured 4.75–8.49:1.

Lighthouse scored the Carts screen **95** on accessibility with `color-contrast` as the only failure.

**Fix:** `--cr-accent` darkened from `oklch(0.55 …)` to `oklch(0.53 …)`, with `--cr-accent-hover` and the focus ring moved in step. Only the one token changes, so the accent stays the same hue everywhere it is used. Re-measured: badge **4.76:1**, links 5.43–5.51:1, primary button 5.29:1 — and every accent pairing improved, none regressed. Lighthouse accessibility is now **100**.

`resources/js/admin/styles/main.css`

---

### 10. Creating a template threw the editor onto a different template — **fixed**

**Severity:** medium (silent loss of context, risk of editing the wrong record)

**Reproduced:** filled in a new template, pressed **Create template**, then sampled the selection every 700 ms:

```
t=700   selected: CRQA Second Template   editing: CRQA Second Template
t=1400  selected: CRQA Second Template   editing: CRQA Second Template
…
```

The template that was just created saved correctly and appeared in the list — but the editor was now sitting on the **default** template. Anything typed next edited the wrong record.

**Cause:** a race in the "keep a valid template selected" effect. Creating a template sets the selection to the new id and invalidates the templates query in the same commit. The effect re-runs immediately, still holding the pre-refetch list, decides the selected id no longer exists, and "rescues" the selection onto the default.

**Fix:** the rescue is skipped while the list is refetching, so it can no longer read the gap between "selected" and "present in the cache" as a deleted template. Delete still re-selects correctly — verified separately.

`resources/js/admin/pages/Templates.tsx`

---

### 11. The loading shimmer was the page's only layout-shift culprit — **fixed**

**Severity:** low (performance)

**Measured:** a Chrome performance trace of the Carts screen named the skeleton shimmer as the sole root cause of the page's layout-shift cluster:

```
non-composited animation: cr-shimmer
Unsupported CSS properties: background-position-x
Failure reasons: TARGET_HAS_INVALID_COMPOSITING_STATE, UNSUPPORTED_CSS_PROPERTY
```

It was listed roughly fifty times — once per skeleton cell in the loading table — because `background-position` cannot be composited, so every shimmer ran on the main thread.

**Fix:** the sweep moved to a `transform: translateX()` on a pseudo-element, which composites. Re-traced: no root causes identified for the residual shift, which is the ordinary skeleton-to-data swap and sits at 0.059, inside the "good" band. LCP also came down from 1555 ms to 1254 ms. The reduced-motion rule was repointed at the pseudo-element so it still disables the animation.

`resources/js/admin/styles/main.css`

---

## Verified working — no change needed

These were exercised and behaved correctly.

- **Guest capture at block checkout.** Typing an email into the block checkout back-filled email, first name, last name and phone onto the live cart row within seconds, without a page reload.
- **Full recovery via email link.** Abandoned cart → manual send → link clicked → cart rebuilt → order placed (cheque → on-hold) → cart moved to `pending-payment` with the order id stamped → order marked processing → cart `recovered`, `recovered_amount` equal to the real order total, order note _"recovered cart #1 via email link."_
- **Paid-only transitions.** An unpaid on-hold order held the cart at `pending-payment` and never counted as revenue.
- **Abandonment detector.** Correctly required a captured email, at least one item, and idle time past the threshold; flipped the row and queued the delayed email through Action Scheduler.
- **Recovery email content.** Subject and body merge tags, the product list, the appended _Complete your order_ button, and the unsubscribe footer all rendered correctly.
- **Unsubscribe.** Confirmation page, suppression row written, and suppression honoured by both the scheduled and the manual send paths.
- **Admin recovery notification.** Delivered with the order number, total and customer.
- **Template preview and test send.** Sample render matched what was delivered.
- **REST authorisation.** All seven admin routes, plus `capture` and `ping`, returned `401 cart_rebound_invalid_nonce` unauthenticated. Admin routes are uniformly gated on `nonce` + `can:manage_woocommerce`.
- **Input validation.** Unknown status → `422`; missing cart → `{"updated":false}`; non-existent order on mark-recovered → `{"updated":false}`; `page=-1&per_page=99999` → clamped to page 1, 100 per page. Numeric settings clamp to a minimum of 1 both client and server side; the notification-email field is validated by the browser and `sanitize_email()`.
- **Bulk actions.** Bulk delete removed the selection and reported _"Deleted 1 cart."_
- **Privacy.** Both the exporter (`cart-rebound-carts`, `cart-rebound-logs`) and the eraser (`cart-rebound`) are registered and return correctly grouped data.
- **Responsive.** At 390 × 844 the cart table scrolls inside its own container; the page never scrolls horizontally.
- **Console.** No JavaScript errors or warnings on any admin screen.
- **Capability enforcement.** An Editor — holding a genuine `wp_rest` nonce lifted from another admin screen — was refused on every admin route, read and write alike, with `403 cart_rebound_forbidden`. The plugin's own admin page returned _"Sorry, you are not allowed to access this page."_ A Shop Manager, who does hold `manage_woocommerce`, got full access as intended.
- **Stored XSS.** A template saved by a Shop Manager (a role without `unfiltered_html`) with `<script>`, `onerror`, `javascript:` hrefs, `<iframe>` and `<style>` payloads came back stripped by `wp_kses_post`; the subject was reduced to plain text. Hostile first/last names planted directly in the database rendered as literal text in the admin (React escapes) and as `&lt;script&gt;` in the recovery email body.
- **Injection and tampering.** SQL-injection, union, and XSS payloads in the recovery and unsubscribe tokens all produced a normal page with no redirect and nothing reflected. Recovery links for `lost` and `completed` carts were refused; unsubscribe still worked for any valid token, as it should.
- **Classic checkout.** With the checkout page switched to the `[woocommerce_checkout]` shortcode, the capture beacon enqueued, picked up the classic `billing_*` field ids, and back-filled email, first name, last name and phone. The order then linked, moved to `pending-payment`, and settled as `completed` on payment.
- **Variable products.** A two-variation product tracked as `{"product_id":3358,"variation_id":3360,"variation":{"attribute_size":"Large"},"quantity":2}`, and the recovery link rebuilt it exactly — "Size: Large", quantity 2, correct total — with contact details prefilled.
- **Coupon restore.** A cart carrying one live and one deleted coupon restored the live one (−7.00 discount applied) and skipped the dead one with no error banner.
- **Janitor.** Six carts seeded 400 days old, one per status, were all purged on the correct retention window, and their log rows went with them. No live cart was touched.
- **Uninstall.** `Plugin::uninstall()` run for real dropped all three tables and all six options, and left no plugin option behind. Every option the plugin writes is covered. Restored from backup afterwards.
- **Migrations.** Re-running from an empty ledger is idempotent — `dbDelta` left all 43 carts, 103 log rows and 3 suppressions intact.
- **Deactivate / reactivate.** Deactivation cleared all three scheduled hooks; reactivation re-armed the recurring jobs and left settings, templates, counters and data untouched.
- **WordPress.org compliance.** Plugin Check against the built release archive is clean. The release contains no tests, docs, scripts, dotfiles or dist configs — 1.9 MB, `export-ignore` doing its job.
- **Internationalisation.** The generated POT carries 495 strings, including ones that exist only inside the minified React bundle (`"What do these statuses mean?"`, `"Rows per page"`, `"This address has unsubscribed from recovery emails."`).
- **Right-to-left.** With the admin switched to Arabic, both the Carts table and the Templates editor mirror correctly — columns reverse, panels swap sides, toolbars and the save bar flip. No RTL stylesheet is needed because the layout is built on flexbox and grid rather than physical offsets.
- **Scale.** At 5,042 carts every read endpoint answered in 283–330 ms, and page 100 was as fast as page 1 — the indexes hold. The dashboard rendered four-digit counts and seven-digit currency without breaking.

---

## Notes, not defects

- **The dashboard's recovery rate does not match the cards beside it.** By design — the cards are a live snapshot, the rate uses purge-immune lifetime counters so cleanup cannot inflate it. Both scopes are labelled on screen, and the ⓘ on the rate says so. Documented in [MANUAL.md](MANUAL.md#dashboard).
- **A rate that lands on a whole number renders as `17%`, not `17.0%`.** Cosmetic only; the value is rounded to one decimal server-side.
- **`{products}` placed inside a `<p>` produces invalid nesting.** The tag renders a `<ul>`, so dropping it mid-paragraph yields `<p>…<ul>…</ul>…</p>`. Mail clients tolerate it. This is a function of where the template author puts the tag, not of the renderer.
- **`cleanup_days` has no upper bound.** `99999` saves fine. A merchant may legitimately want a very long retention window, so this is left open.
- **A shopper who abandons, returns, and abandons again counts twice** on the lifetime abandoned counter. That is the intended reading — the counter measures abandonment _events_, not distinct carts.
- **The product report samples rather than scans.** `REPORT_SCAN_LIMIT` caps the tally at the 2,000 most recent qualifying carts so a busy store cannot blow the request budget. Documented in the code and intentional, but the panel gives no on-screen hint that a very busy store is seeing a sample.
- **The readme plugin name.** Plugin Check raises one warning on the release build: the readme's plugin name (_"Cart Rebound – Cart Abandonment Recovery for WooCommerce"_) differs from the header name (_"Cart Rebound"_). That is a deliberate, common WordPress.org practice, so it is left as it is.
- **The `+ New` button reads as `New +` in RTL.** The `+` is a character inside the translatable string, so bidi reorders it. Cosmetic; moving the glyph into an icon element would settle it.
- **Cart Rebound Pro cannot run against `main`.** Activating the Pro add-on shows _"Cart Rebound Pro could not find the Cart Rebound follow-up pipeline it extends."_ Pro requires `CartRebound\Followup\Runner`, which lives on the unmerged `feat/pro-surface` branch, not on `main`. Not a defect in this branch — but Pro is unusable until that branch lands. The free plugin degrades correctly: with Pro active the dashboard, carts and every metric still render, with no console errors.

---

## Reproducing this pass

The lifecycle work needs real orders, so drive it through `wp eval` against genuine `WC_Order` objects rather than editing rows directly:

```bash
# Force an abandonment scan without waiting for cron
wp eval 'do_action( "cart_rebound_scan_abandoned" );'

# Age a cart so the detector picks it up
wp db query "UPDATE wp_cart_rebound_sessions
             SET last_activity = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
             WHERE id = <id>;"

# Watch the queued recovery emails
wp eval 'foreach ( as_get_scheduled_actions( array(
    "hook"   => "cart_rebound_send_recovery_email",
    "status" => ActionScheduler_Store::STATUS_PENDING,
) ) as $id => $a ) { printf( "#%d %s\n", $id, wp_json_encode( $a->get_args() ) ); }'

# Drive a real paid transition
wp eval '$o = wc_get_order( <order_id> ); $o->update_status( "processing", "QA" );'
```

A local mail catcher (Mailpit on `127.0.0.1:1025`) plus a temporary `phpmailer_init` mu-plugin makes the email paths observable end to end. Remember to remove that mu-plugin afterwards — it redirects **all** site mail.

For the second pass:

```bash
# Compliance: check the built release, not the working tree — the dev files
# flagged in a working-tree run are all export-ignored and never ship.
bash scripts/build-zip.sh
unzip -q build/cart-rebound.zip -d /tmp/relcheck
cp -r /tmp/relcheck/cart-rebound wp-content/plugins/crqa-relcheck
wp plugin check crqa-relcheck        # ignore TextDomainMismatch: the slug is renamed

# Classic checkout: point the checkout page at a shortcode page, then restore
wp option update woocommerce_checkout_page_id <shortcode-page-id>
wp option update woocommerce_checkout_page_id 11

# Right-to-left
wp language core install ar && wp user meta update 1 locale ar
wp user meta delete 1 locale && wp language core uninstall ar

# Uninstall, for real — back the data up first, this drops the tables
wp db export /tmp/cr.sql --tables=wp_cart_rebound_sessions,wp_cart_rebound_logs,wp_cart_rebound_unsubscribes
wp eval 'CartRebound\Core\Plugin::uninstall();'
wp db import /tmp/cr.sql

# Retention sweep
wp eval 'echo CartRebound\Core\Application::get_instance()
    ->make( CartRebound\Cron\Janitor::class )->run();'
```

Accessibility and performance were measured with Chrome DevTools: a Lighthouse audit for the score, contrast ratios computed from `getComputedStyle` through a canvas (`oklch()` does not parse as RGB, so read it back off a 1×1 context), and a performance trace for the layout-shift culprits.
