# Cart Rebound — MVP Specification

**Derived from a full teardown of the market-leading free competitor:** _Cart
Abandonment Recovery for WooCommerce_ (WCAR) v2.1.3 by Brainstorm Force /
CartFlows — installed locally at `wp-content/plugins/woo-cart-abandonment-recovery`.

This document has three jobs:

1. Record **every feature WCAR ships in its free plugin**, with descriptions.
2. Record **every feature WCAR gates behind its Pro plugin**, with descriptions.
3. Define the **Cart Rebound MVP** — the minimum feature set that makes Cart
   Rebound a credible free alternative — and mark what is already shipped in
   1.0.0, what is missing, and what is deliberately deferred to Pro.

> **Ground rule.** WCAR was read for _behaviour and UX_ only. No WCAR code is
> copied into Cart Rebound; the implementation notes below describe our own
> architecture (`src/`), not theirs.

## Table of Contents

- [1. Method & sources](#1-method--sources)
- [2. WCAR at a glance](#2-wcar-at-a-glance)
- [3. WCAR free features (complete inventory)](#3-wcar-free-features-complete-inventory)
- [4. WCAR Pro features (complete inventory)](#4-wcar-pro-features-complete-inventory)
- [5. Parity matrix vs Cart Rebound 1.0.0](#5-parity-matrix-vs-cart-rebound-100)
- [6. Cart Rebound MVP definition](#6-cart-rebound-mvp-definition)
- [7. Open product decisions](#7-open-product-decisions)
- [8. Build order](#8-build-order)
- [9. Non-goals for the MVP](#9-non-goals-for-the-mvp)

## 1. Method & sources

| What               | Where                                                                                       |
| ------------------ | ------------------------------------------------------------------------------------------- |
| Plugin analysed    | `woo-cart-abandonment-recovery` v2.1.3 (WC tested 9.8.5, WP tested 6.9, PHP 7.2+)           |
| Backend surface    | `modules/cart-abandonment/classes/*` (tracking, cron, email schedule, templates, DB)        |
| Admin surface      | `admin/src/**` (React app), `admin/api/*` (REST), `admin/ajax/*` (admin-ajax)               |
| Settings surface   | `admin/inc/meta-options.php` (all free + Pro-teaser field definitions)                      |
| Pro surface        | Pro-gated field definitions, `admin/src/components/pro/*`, `wcar_pro_*` AJAX action names   |
| Cart Rebound state | `src/**`, `routes/api.php`, `routes/admin.php`, `src/Support/Settings.php`, `docs/USAGE.md` |

Because the Pro plugin (`woo-cart-abandonment-recovery-pro`) is **not installed
locally**, section 4 is reconstructed from the free build's own gating code:
Pro-only settings that render as locked teasers, the upgrade-CTA copy, the Pro
tabs the free UI renders in read-only/dummy mode, and the `wcar_pro_*` AJAX
actions the free JS already calls. That is a reliable inventory of _what Pro
does_, but not of _how it is implemented_.

## 2. WCAR at a glance

**Architecture.** Four custom tables, one wp-cron sweep, one React admin app.

| Table                                       | Purpose                                                                                                                                                                                                                                                                                                                             |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `{prefix}cartflows_ca_cart_abandonment`     | One row per tracked cart: `session_id` (unique), `email`, `cart_contents`, `cart_total`, `other_fields` (serialised billing/shipping/phone/consent), `order_status` enum (`normal`/`abandoned`/`completed`/`lost`), `unsubscribed`, `coupon_code`, `time`. v2.1.3 adds `order_id` + `original_cart_total` via a runtime DB updater. |
| `{prefix}cartflows_ca_email_templates`      | Follow-up email templates: name, subject, body, `is_activated`, `frequency` + `frequency_unit` (MINUTE/HOUR/DAY).                                                                                                                                                                                                                   |
| `{prefix}cartflows_ca_email_templates_meta` | Per-template options (coupon overrides, product-table options, Woo styling, admin copy, etc.).                                                                                                                                                                                                                                      |
| `{prefix}cartflows_ca_email_history`        | The send queue/log: `template_id`, `ca_session_id`, `coupon_code`, `scheduled_time`, `email_sent` (`0` pending, `1` sent, `-1` cancelled).                                                                                                                                                                                          |

**The sweep.** A single wp-cron event (`cartflows_ca_update_order_status_action`,
interval = the cut-off-time setting) does everything on each run: delete
zero-total rows → flip idle `normal` rows to `abandoned` (scheduling their
emails, minting the global coupon, firing the webhook) → send every due email →
flip fully-mailed old abandoned rows to `lost` → garbage-collect coupons.

**Attribution.** On `woocommerce_new_order` / `woocommerce_thankyou` /
`woocommerce_order_status_changed`, WCAR matches the order to a cart by **billing
email** (bounded to a 90-day window since 2.1.3), marks it `completed`,
cancels pending emails, overwrites `cart_total` with the real order total
(preserving the original in `original_cart_total`) and adds an order note.

## 3. WCAR free features (complete inventory)

### 3.1 Capture & tracking

| Feature                         | Description                                                                                                                                                                                                            |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Checkout email capture          | A front-end script on the checkout page posts field values to `admin-ajax` (`cartflows_save_cart_abandonment_data`) as the shopper types, so the cart is recoverable **before** "Place order" is pressed.              |
| Block checkout support          | Same capture path for the WooCommerce Blocks / Store API checkout (since 1.3.0); field prefixes differ (`billing-` vs `billing_`) and the script is enqueued on the blocks checkout hook.                              |
| Full checkout snapshot          | Stores cart contents (incl. variations, bundles, product add-ons), cart total, billing company/address/state/postcode, shipping name/company/country/address/city/state/postcode, phone, country+city, order comments. |
| Session keying                  | A `wcf_session_id` in the WooCommerce session identifies the row; if absent, an existing abandoned/normal row with the same email is adopted, else a new id is minted.                                                 |
| Enable/disable tracking         | Master toggle (`Enable Tracking`).                                                                                                                                                                                     |
| Cart abandoned cut-off time     | Minutes of inactivity before a cart counts as abandoned (default 20, minimum 10). Doubles as the cron interval.                                                                                                        |
| Abandoned cart lost time        | Days after which a fully-mailed abandoned cart is marked `lost` (minimum 10).                                                                                                                                          |
| Disable tracking for roles      | Multi-select of user roles excluded from tracking entirely (typical use: admin/shop manager while testing).                                                                                                            |
| Zero-value / empty-cart hygiene | Carts with a total of 0 are never inserted and are purged on every sweep.                                                                                                                                              |
| Duplicate-purchase suppression  | Before sending, WCAR checks the last 30 days of processing/completed orders for that email; if any contains a product from the cart, the row is deleted and no mail goes out.                                          |
| Out-of-stock suppression        | If any product in the captured cart is out of stock, the recovery email is skipped (1.3.2).                                                                                                                            |
| Exclude order statuses          | Order statuses for which no further recovery emails are sent (the cart is treated as resolved).                                                                                                                        |
| Checkout-page exclusion filter  | `woo_ca_exclude_specific_checkout_page` lets developers exclude specific checkout pages from tracking.                                                                                                                 |

### 3.2 Lifecycle & revenue attribution

| Feature               | Description                                                                                                                                                                     |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Four-state lifecycle  | `normal` (live cart) → `abandoned` (cut-off passed) → `completed` (recovered) or `lost` (all emails sent, lost-time elapsed).                                                   |
| Recovery detection    | Order → cart matching by billing email within a filterable 90-day window (`cartflows_ca_recovery_match_window_days`).                                                           |
| Revenue correction    | On recovery the row's `cart_total` is replaced by the real order total and the pre-order value is kept in `original_cart_total`, so revenue reports stop inflating (2.1.3 fix). |
| Order note            | "This order was abandoned & subsequently recovered." is appended to the WooCommerce order.                                                                                      |
| Email cancellation    | Pending rows in the history table are flipped to `-1` so no further follow-ups go out after purchase.                                                                           |
| Failed-order handling | Failed orders don't cancel tracking; the cart stays recoverable.                                                                                                                |

### 3.3 Follow-up emails

| Feature                    | Description                                                                                                                                                                                                                                                                                                                                       |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unlimited email templates  | Any number of templates, each independently activated, forming a de-facto sequence.                                                                                                                                                                                                                                                               |
| Per-template send delay    | "Send this email _N_ minute(s)/hour(s)/day(s) after the cart is abandoned." Delays are measured from the abandonment timestamp, so N templates = an N-step sequence.                                                                                                                                                                              |
| Seeded starter sequence    | Three sample templates are installed on activation: 30 minutes ("Purchase issue?"), 1 day ("Need help?"), 3 days (discount offer).                                                                                                                                                                                                                |
| Rich-text editor           | TinyMCE body editor with a merge-tag inserter, hardened in 2.1.0 for email-safe markup; alignment classes are converted to inline styles at send time.                                                                                                                                                                                            |
| Merge tags                 | `{{customer.firstname}}`, `{{customer.lastname}}`, `{{customer.fullname}}`, `{{admin.firstname}}`, `{{admin.company}}`, `{{cart.coupon_code}}`, `{{cart.abandoned_date}}`, `{{cart.checkout_url}}`, `{{cart.unsubscribe}}`, `{{cart.product.names}}`, `{{cart.product.table}}`, `{{site.url}}`, plus `{store_address}` in the WooCommerce footer. |
| Product table block        | `{{cart.product.table}}` renders an HTML table of the abandoned items with images.                                                                                                                                                                                                                                                                |
| Per-template product table | (2.1.2) Toggle to customise the table per email: image size (32/48/64px), tax-inclusive prices, and which columns show (image, name, quantity, price, subtotal).                                                                                                                                                                                  |
| WooCommerce email styling  | Per-template toggle to wrap the body in the store's WooCommerce email header/footer template instead of a bare HTML email.                                                                                                                                                                                                                        |
| Global sender identity     | "From" name, "From" address and "Reply-To" address.                                                                                                                                                                                                                                                                                               |
| Admin CC/BCC copy          | (2.1.3) Per template: send the admin a CC or BCC copy of every recovery email, to a configurable comma-separated address list (defaults to the site admin). Test sends are excluded.                                                                                                                                                              |
| Test email                 | Send any template to an arbitrary address using a dummy cart session; the generated checkout link shows a "this page was generated for testing" notice.                                                                                                                                                                                           |
| Template import/export     | (1.3.3) Export selected templates (with their meta) as JSON and import them back — backup, sharing, staging→production migration.                                                                                                                                                                                                                 |
| Send-time guards           | Never sends when: the cart is unsubscribed, the order completed, the cart holds out-of-stock items, or the shopper already bought one of the products in the last 30 days.                                                                                                                                                                        |

### 3.4 Recovery links

| Feature                | Description                                                                                                                                                 |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| One-click restore link | Every email carries a `wcf_ac_token` link back to the checkout page. On load, the cart is emptied and rebuilt from the stored contents.                     |
| Full cart fidelity     | Variations, custom/variation attributes, product add-ons (PPOM) and bundled products are restored; bundle children are skipped so the parent rebuilds them. |
| Checkout pre-fill      | Name, phone, email, city and country are pushed into the checkout fields — correctly prefixed for classic vs block checkout.                                |
| Coupon auto-apply      | If the template enables auto-apply, the coupon travels in the token and is applied to the restored cart.                                                    |
| UTM parameters         | A global list of query parameters appended to every recovery link for campaign attribution in analytics.                                                    |

### 3.5 Coupons

| Feature                      | Description                                                                                                                                                                                                                                                   |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Global abandonment coupon    | When enabled, a unique 8-character WooCommerce coupon is minted for each cart as it flips to abandoned: percentage or fixed-cart discount, configurable amount, expiry in hours/days, `usage_limit = 1`. Surfaced in the UI under **Integrations → Webhook**. |
| Per-template coupon override | A template can mint its own coupon instead: discount type, amount, expiry, **free shipping**, **individual use only**, and **auto-apply at checkout**.                                                                                                        |
| Coupon garbage collection    | Weekly background cleanup of used/expired plugin-generated coupons, plus a manual "Delete" button; coupons are tagged with a `coupon_generated_by` marker so only plugin coupons are removed.                                                                 |

### 3.6 Compliance & privacy

| Feature               | Description                                                                                                                                                                              |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GDPR consent notice   | Optional consent message rendered under the checkout email field with a "No Thanks" opt-out that sets a `wcf_ca_skip_track_data` cookie and stops capture for that visitor.              |
| Unsubscribe link      | `{{cart.unsubscribe}}` renders a "Don't remind me again" link; clicking sets `unsubscribed = 1`, which permanently excludes the cart from the send query. The notice text is filterable. |
| Bulk unsubscribe      | Admins can unsubscribe selected rows straight from the follow-up report; unsubscribed rows are flagged in both reports.                                                                  |
| Usage-tracking opt-in | Opt-in non-sensitive telemetry (BSF analytics), plus an NPS survey and a deactivation survey.                                                                                            |
| Delete plugin data    | Optional purge of all plugin tables/options on plugin deletion.                                                                                                                          |

_Gap worth noting: WCAR registers **no** WordPress personal-data exporter or
eraser, so core "Export/Erase personal data" requests do not reach its tables._

### 3.7 Reporting & admin UI

| Feature                      | Description                                                                                                                                                                                                                |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Dashboard                    | Date-range selector with previous-period comparison; cards for recoverable / recovered / lost orders, recoverable and recovered revenue, and recovery rate; a revenue chart; recent email logs.                            |
| Follow-up report             | Table of tracked carts with tabs **All / Abandoned Orders / Recovered Orders / Lost Orders** (plus a Pro **Blacklisted** tab), search, pagination, per-row and bulk delete, bulk unsubscribe, and unsubscribed indicators. |
| Export                       | Export the follow-up report (including the recorded abandonment date and phone number) to a spreadsheet.                                                                                                                   |
| Detailed report (per cart)   | Drill-down showing customer details, billing/shipping address, cart contents, the full email schedule with sent/pending status, and a **reschedule emails** action. SMS/WhatsApp tabs are Pro.                             |
| Weekly recovery report email | Action-Scheduler job every Monday 2pm emailing a recovery summary to one or many admin addresses — sent only when at least one order was recovered — with its own unsubscribe link.                                        |
| Admin recovery notification  | Optional email to the store admin every time an abandoned cart converts, rendered with the WooCommerce "new order" template.                                                                                               |
| Onboarding wizard            | Six-screen first-run flow: Welcome → Recovery settings → Follow-up channels → Report email → Add-ons → Finish.                                                                                                             |
| Rollback                     | (2.0.6) One-click rollback to a previous plugin version from the Advanced tab.                                                                                                                                             |
| Access control               | All screens require `manage_woocommerce`, so shop managers can use the plugin.                                                                                                                                             |

### 3.8 Integrations & extensibility

| Feature                       | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Outbound webhook              | Fires to a configured URL on **abandonment** and on **recovery** (Zapier / Pabbly / Make). Payload: first/last name, phone, billing + shipping address, email, checkout URL, product names, product table HTML, coupon code, order status, cart total. The URL is validated against non-HTTP schemes, loopback hosts and private IP ranges (SSRF hardening).                                                                                                                                      |
| OttoKit (SureTriggers)        | Embedded automation panel inside the plugin for no-code workflows.                                                                                                                                                                                                                                                                                                                                                                                                                                |
| WordPress Abilities API / MCP | (2.1.1) Registers five abilities — `wcar/get-settings`, `wcar/get-setting`, `wcar/update-setting`, `wcar/get-dashboard-stats`, `wcar/get-product-stats` — and adds them to the default MCP server config, making the plugin scriptable by AI agents.                                                                                                                                                                                                                                              |
| REST API (admin)              | Internal `WP_REST` controllers for dashboard, follow-up list, follow-up emails and detailed report, all gated on `manage_woocommerce`.                                                                                                                                                                                                                                                                                                                                                            |
| Developer hooks               | ~40 actions/filters, notably `wcf_ca_process_abandoned_order`, `wcf_ca_after_save_abandonment_data`, `wcf_ca_after_email_sent`, `wcf_ca_after_coupon_created`, `wcar_after_restore_cart_abandonment_data`, `wcar_after_unsubscribe_cart_abandonment_emails`, `woo_ca_session_abandoned_data`, `woo_ca_recovery_email_data`, `wcf_ca_should_send_email`, `wcf_ca_should_schedule_template`, `woo_ca_webhook_trigger_details`, `woo_ca_generate_coupon`, `cartflows_ca_recovery_match_window_days`. |
| HPOS compatibility            | Declares WooCommerce High-Performance Order Storage compatibility.                                                                                                                                                                                                                                                                                                                                                                                                                                |
| Internationalisation          | (2.0.0) Ships translation files for multiple languages.                                                                                                                                                                                                                                                                                                                                                                                                                                           |

## 4. WCAR Pro features (complete inventory)

Reconstructed from the free build's gating code (locked fields, dummy-data
previews, upgrade CTAs and `wcar_pro_*` AJAX actions the free JS already calls).
Marketing summary used in-product: _"Product Reports · SMS + WhatsApp Followups ·
Smart Rules · Advanced Automations."_

| Pro feature                          | Description                                                                                                                                                                                         | Evidence in free build                                                                                          |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| **SMS follow-ups**                   | A second recovery channel: SMS reminders sent automatically on abandonment, with their own message templates, previews, test sends, per-cart schedule and reschedule action.                        | `sms-integration` settings tab, `SmsPreview.js`, `SmsDetails.js`, `wcar_pro_reschedule_sms`                     |
| **WhatsApp follow-ups**              | Same as above over WhatsApp, with its own templates and per-cart delivery history.                                                                                                                  | `whatsapp-integration` tab, `WhatsappPreview.js`, `WhatsappDetails.js`, `wcar_pro_reschedule_whatsapp_messages` |
| **Product reports**                  | Product-level analytics: which products are abandoned most, which recover, and their revenue contribution — with its own table and delete action. Dummy data is rendered under a blur in free.      | `pages/Product.js`, `ProductReportDummyData.js`, `wcar_pro_delete_product_reports`, `wcar/get-product-stats`    |
| **Dynamic conditions (rule engine)** | Per-email conditional sending: a rule builder (moved into a modal in 2.1.2) that decides whether a given follow-up should go out for a given cart — e.g. cart value, products, customer attributes. | `enable_email_rule_engine` Pro field, `components/RuleEngine/ConditionalRulesField.js`                          |
| **Exclude products from coupons**    | Product picker that suppresses coupon generation when the cart contains selected products, protecting margin on excluded SKUs.                                                                      | `exclude_product_ids` Pro field                                                                                 |
| **Email/domain blacklist**           | Block recovery messages to specific addresses or whole domains, and a **Blacklisted** tab in the follow-up report with per-cart blacklist/unblacklist actions.                                      | `blacklist-settings` tab, `wcar_pro_blacklist_cart` / `wcar_pro_unblacklist_cart`                               |
| **Phone GDPR consent**               | A consent message under the checkout phone field, the legal prerequisite for the SMS/WhatsApp channels. The free build already captures `wcf_gdpr_phone_consent` into the cart row.                 | `wcf_ca_phone_gdpr_status` Pro field                                                                            |
| **On-site reminder banner**          | An on-site banner shown to returning shoppers reminding them what they left in the cart — recovery without needing an email address.                                                                | `banner-settings` tab                                                                                           |
| **License management**               | License key screen, activation/deactivation, and gating: Pro features stay locked until the license is active (`is_pro && licenseStatus === '1'`).                                                  | `LicenseSettings.js`, `LicenseNotice.js`, `useProAccess.js`                                                     |

## 5. Parity matrix vs Cart Rebound 1.0.0

Legend: ✅ shipped · 🟡 partial · ❌ missing · ➕ Cart Rebound advantage

| Capability                                 |                 WCAR free                  | Cart Rebound 1.0.0                                                     | Verdict                 |
| ------------------------------------------ | :----------------------------------------: | ---------------------------------------------------------------------- | ----------------------- |
| Logged-in cart tracking                    |                     ✅                     | ✅                                                                     | Parity                  |
| Guest cart tracking                        |                     ✅                     | ✅ (opt-in setting `guest_tracking`)                                   | Parity                  |
| Classic + block checkout capture           |                     ✅                     | ✅                                                                     | Parity                  |
| Cut-off / abandonment threshold            |                     ✅                     | ✅ (`abandonment_threshold`, applied in the scan query)                | ➕ no reschedule needed |
| Lost-cart state                            |                     ✅                     | ✅ (`lost` status in the lifecycle)                                    | Parity                  |
| Pending-payment state                      |                     ❌                     | ✅                                                                     | ➕                      |
| Revenue attribution                        |       🟡 email match, 90-day window        | ✅ explicit order meta                                                 | ➕ exact                |
| Recovery link (tokenised, cart rebuild)    |                     ✅                     | ✅ (items, variations, coupons)                                        | Parity                  |
| One automated recovery email               |                     ✅                     | ✅ (off by default, configurable delay)                                | Parity                  |
| **Multi-step email sequence**              |      ✅ unlimited templates × delays       | ❌ single send per cart                                                | **Gap — P0**            |
| Template library / CRUD                    |                     ✅                     | ✅ (multiple stored templates, one default)                            | Parity                  |
| Merge tags                                 |                   ✅ 13                    | 🟡 4 (`{first_name}`, `{products}`, `{recovery_url}`, `{coupon_code}`) | **Gap — P0**            |
| Product table in email                     |         ✅ + per-template options          | 🟡 `{products}` list                                                   | **Gap — P1**            |
| WooCommerce email styling                  |                     ✅                     | ❌                                                                     | Gap — P1                |
| Test email / preview                       |                     ✅                     | ✅ (`templates/test`, `templates/preview`)                             | Parity                  |
| Template import/export                     |                     ✅                     | ❌                                                                     | Gap — P2                |
| **Auto-generated unique coupon**           |                     ✅                     | 🟡 static coupon string only (`email_coupon`)                          | **Gap — P0**            |
| Coupon expiry / single use / free shipping |                     ✅                     | ❌                                                                     | Gap — P0/P1             |
| Coupon garbage collection                  |                     ✅                     | ❌                                                                     | Gap — P1                |
| Unsubscribe link + suppression list        |                     ✅                     | ✅ (dedicated `unsubscribes` table)                                    | ➕ durable              |
| **GDPR consent gate at checkout**          |                     ✅                     | ❌                                                                     | **Gap — P0**            |
| WP personal-data export/erase              |                     ❌                     | ✅                                                                     | ➕                      |
| **Exclude user roles from tracking**       |                     ✅                     | ❌                                                                     | **Gap — P0**            |
| Exclude order statuses                     |                     ✅                     | 🟡 `paid_order_statuses` only                                          | Gap — P1                |
| Out-of-stock / already-purchased guards    |                     ✅                     | ❌                                                                     | Gap — P1                |
| Dashboard stats + revenue chart            |                     ✅                     | ✅ (`stats`, `stats/timeseries`, `RevenueChart`)                       | Parity                  |
| Previous-period comparison                 |                     ✅                     | ❌                                                                     | Gap — P2                |
| Cart list: search, filter, bulk actions    |                     ✅                     | ✅ (`carts`, `carts/bulk`, status/mark-recovered/delete)               | Parity                  |
| Per-cart detail + manual resend            |                     ✅                     | ✅ (`carts/{id}`, `carts/{id}/send-email`)                             | Parity                  |
| **CSV/spreadsheet export of carts**        |                     ✅                     | ❌                                                                     | **Gap — P1**            |
| Email send log                             |              🟡 history table              | ✅ dedicated log table + REST + UI                                     | ➕                      |
| Product-level report                       |                  Pro only                  | ✅ `stats/products`                                                    | ➕ free                 |
| Weekly digest email to admin               |                     ✅                     | ❌                                                                     | Gap — P1                |
| Admin notification on recovery             |                     ✅                     | ✅ (`admin_recovery_email`)                                            | Parity                  |
| Outbound webhook (Zapier/Make)             |                     ✅                     | 🟡 PHP events only (`cart_rebound_abandoned`, `…_recovered`)           | **Gap — P1**            |
| Public REST API for integrations           |                🟡 internal                 | ✅ documented `cart-rebound/v1`                                        | ➕                      |
| Onboarding wizard                          |                     ✅                     | ✅                                                                     | Parity                  |
| Action Scheduler + wp-cron fallback        | 🟡 wp-cron only (AS for the weekly digest) | ✅                                                                     | ➕                      |
| Rollback to previous version               |                     ✅                     | ❌                                                                     | Non-goal                |
| Telemetry / NPS / deactivation survey      |                     ✅                     | ❌                                                                     | Non-goal                |
| Abilities API / MCP exposure               |                     ✅                     | ❌                                                                     | Gap — P2                |

## 6. Cart Rebound MVP definition

**MVP goal.** A store owner installs Cart Rebound on a WooCommerce store, runs
the wizard, and within one abandonment cycle sees carts captured, follow-ups
delivered, carts restored by link, and recovered revenue attributed — without an
external account, and without doing anything that would embarrass them legally.

**Success criteria for the MVP as a whole**

1. A cold install recovers a real cart end-to-end with **no code and no support ticket**.
2. Every claim on the WordPress.org page is demonstrable in the plugin.
3. Nothing in the free tier is knowingly weaker than WCAR free on the four things
   merchants compare first: _sequence, coupons, reports, compliance_.

### 6.1 P0 — must ship (MVP is not complete without these)

| #     | Item                          | Description                                                                                                                                                                                | Done when                                                                                                                                       | Status                            |
| ----- | ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| P0-1  | Cart capture                  | Logged-in + opt-in guest capture on classic and block checkout, including the email typed before submit.                                                                                   | A guest typing an email at checkout and leaving produces one row with items, total and identity.                                                | ✅ done                           |
| P0-2  | Lifecycle & attribution       | `active → abandoned → pending-payment → recovered / completed / lost`, with orders linked by explicit order meta.                                                                          | Coupons/shipping/tax never break the link; recovered revenue equals the order total.                                                            | ✅ done                           |
| P0-3  | Recovery link                 | Tokenised link that rebuilds items, variations and coupons and lands the shopper on checkout; no raw session key in the URL.                                                               | Link restores a variation product + applied coupon in one click.                                                                                | ✅ done                           |
| P0-4  | Automated recovery email      | Templated email scheduled a configurable delay after abandonment, opt-in, with sender identity and test send.                                                                              | Email arrives at the configured delay; test send renders identically.                                                                           | ✅ done                           |
| P0-5  | **Multi-step sequence**       | At least **two** scheduled follow-ups per cart (reminder + last chance), each with its own delay, template and enable toggle. Sending stops instantly on conversion or unsubscribe.        | Two emails go out at their own delays; converting after the first cancels the second; the cart detail shows both steps with sent/pending state. | ❌ **build**                      |
| P0-6  | **Auto-generated coupon**     | Per-cart unique WooCommerce coupon: percent/fixed, amount, expiry, `usage_limit = 1`, optional auto-apply through the recovery link, rendered via `{coupon_code}`.                         | Each abandoned cart gets its own code; the code expires; reusing it fails; the coupon list stays clean via cleanup.                             | 🟡 static string only — **build** |
| P0-7  | **Merge-tag set**             | Extend tokens to cover what real templates need: first/last/full name, store name, cart total, abandonment date, product list, product table, checkout URL, coupon code, unsubscribe link. | Every documented token resolves in both preview and live send; unknown tokens degrade to empty, never to raw text.                              | 🟡 4 tokens — **build**           |
| P0-8  | **Unsubscribe + suppression** | One-click unsubscribe in every email, honoured permanently across all steps and future carts for that email.                                                                               | An unsubscribed address never receives another recovery email from any cart.                                                                    | ✅ done                           |
| P0-9  | **Consent gate (GDPR)**       | Optional consent notice under the checkout email field with a decline action that stops capture for that visitor, plus a documented lawful-basis note in the readme/privacy policy.        | With consent required and declined, no row is written and no cookie-based tracking continues.                                                   | ❌ **build**                      |
| P0-10 | **Exclude roles**             | Multi-select of user roles excluded from tracking (admins/shop managers/wholesale).                                                                                                        | Logged-in excluded role produces no rows while testing.                                                                                         | ❌ **build**                      |
| P0-11 | Dashboard & cart admin        | Counts, recovered revenue, recovery rate, revenue chart, filterable cart list, per-cart detail, bulk actions, send-now.                                                                    | A merchant can answer "did it work, and for whom" without SQL.                                                                                  | ✅ done                           |
| P0-12 | Send log                      | Every send attempt recorded with result and error, visible in the admin.                                                                                                                   | A failed `wp_mail` is visible with its error text.                                                                                              | ✅ done                           |
| P0-13 | Scheduling reliability        | Action Scheduler with wp-cron fallback; threshold changes take effect on the next scan without rescheduling.                                                                               | Changing the threshold from 30 to 10 minutes changes behaviour on the next scan.                                                                | ✅ done                           |
| P0-14 | Privacy tooling               | WordPress personal-data exporter + eraser covering cart rows, logs and unsubscribes; documented uninstall behaviour.                                                                       | A core "Erase personal data" request removes the shopper from all plugin tables.                                                                | ✅ done                           |
| P0-15 | Onboarding                    | First-run wizard that turns on tracking, sets the threshold, enables the first email and verifies the sender address.                                                                      | A new install is recovering carts without opening Settings.                                                                                     | ✅ done                           |

### 6.2 P1 — first follow-up release (parity polish)

| #    | Item                       | Description                                                                                                                                         |
| ---- | -------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| P1-1 | Product table in email     | `{products_table}` block with image, name, quantity, price, subtotal; per-template image size, tax display and column choice.                       |
| P1-2 | WooCommerce email styling  | Per-template toggle to wrap the body in the store's WooCommerce email header/footer.                                                                |
| P1-3 | Send guards                | Skip sends when the cart holds out-of-stock items, or the address already bought those products recently.                                           |
| P1-4 | Outbound webhook           | POST the abandonment/recovery payload to a configured URL, with SSRF-safe URL validation (scheme, loopback, private ranges) and a test-fire button. |
| P1-5 | CSV export                 | Export the filtered cart list (identity, items, totals, status, timestamps) to CSV.                                                                 |
| P1-6 | Weekly digest email        | Opt-in weekly recovery summary to one or more admin addresses, sent only when something was recovered.                                              |
| P1-7 | Exclude order statuses     | Statuses that resolve a cart and stop further sends, beyond the paid-status list.                                                                   |
| P1-8 | Coupon cleanup             | Scheduled deletion of expired/used plugin-generated coupons, plus a manual purge button.                                                            |
| P1-9 | Previous-period comparison | Dashboard deltas versus the preceding equal-length period.                                                                                          |

### 6.3 P2 — nice to have, not blocking

Template import/export (JSON), Abilities API / MCP exposure, per-template
sender overrides, admin CC/BCC copies, richer cart-detail timeline, in-app
knowledge base links.

### 6.4 Deferred to Pro

Owned by [`PRO-FEATURES.md`](./PRO-FEATURES.md) and explicitly **out** of the MVP:
unlimited/conditional sequences, open-click-conversion tracking, advanced
analytics, exit-intent capture, advanced trigger rules, A/B testing,
SMS/WhatsApp, web push, blacklist, on-site reminder banner.

## 7. Open product decisions

Two MVP items (P0-5, P0-6) contradict the current Pro plan, and the contradiction
should be resolved deliberately rather than by whoever writes the code first.

1. **Sequence.** `PRO-FEATURES.md` lists the multi-step sequence as Pro feature
   #1 and keeps free at one email. WCAR free ships **unlimited** templates with
   per-template delays. A single free email is therefore below the market's table
   stakes and is the first thing a reviewer will compare.
   **Recommendation:** free ships a **capped sequence (2 steps)** — reminder plus
   last chance. Pro keeps unlimited steps, conditional rules, A/B and extra
   channels. This preserves the upgrade story while removing the "weaker than the
   free competitor" objection.
2. **Coupons.** `PRO-FEATURES.md` treats auto-generated unique coupons as Pro #2,
   while WCAR mints unique single-use coupons for free.
   **Recommendation:** free ships **one coupon policy** (type, amount, expiry,
   single use, auto-apply). Pro adds per-step coupon policies, product/category
   exclusions, minimum-spend rules and email-restricted codes.

Both recommendations move value from the Pro column into free; both are reversible
before launch and expensive to reverse after. Sign-off needed before P0-5/P0-6
start.

## 8. Build order

| Order | Work                                     | Why here                                                                                           | Rough size |
| ----- | ---------------------------------------- | -------------------------------------------------------------------------------------------------- | ---------- |
| 1     | P0-7 merge tags                          | Unblocks every template change; no schema impact.                                                  | S          |
| 2     | P0-10 role exclusion + P0-9 consent gate | Small, compliance-critical, and they change what gets captured — do before more data flows in.     | S–M        |
| 3     | P0-5 two-step sequence                   | Biggest competitive gap; touches schema (`email_step`, `next_step_at`), scheduler and cart detail. | M          |
| 4     | P0-6 auto-generated coupon               | Depends on the sequence for step-level codes; adds `coupon_code`/`coupon_expires_at`.              | M          |
| 5     | P1-1/P1-2 email rendering                | Makes the emails look like a real store's emails.                                                  | M          |
| 6     | P1-4 webhook + P1-5 CSV export           | Unlocks integrations and data portability with no new concepts.                                    | S          |
| 7     | P1-3 guards, P1-6 digest, P1-7/P1-8/P1-9 | Polish pass before the Pro build starts.                                                           | S each     |

All schema work must stay additive and version-gated, matching the existing
`Database\Migrations` pattern, so a pre-migration install never fatals.

## 9. Non-goals for the MVP

- **Version rollback** and **plugin telemetry / NPS / deactivation surveys** —
  WCAR ships them; they add maintenance and privacy surface without recovering a
  single cart.
- **Embedded third-party automation panels** (WCAR embeds SureTriggers). The
  documented REST API plus events plus the P1 webhook cover the same need without
  binding the plugin to a vendor.
- **Rewriting attribution to email matching.** Order-meta linking is strictly
  better than WCAR's 90-day email-match heuristic; keep it.
- **Any Pro feature.** Free must stay adoptable on its own: tracking, the full
  lifecycle, the capped sequence, recovery links, unsubscribe/suppression,
  dashboard stats and privacy tooling never move behind the licence.
