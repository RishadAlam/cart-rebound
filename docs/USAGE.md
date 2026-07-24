# Cart Rebound — User & Developer Guide

End-to-end documentation for **Cart Rebound**, the WooCommerce abandoned-cart recovery plugin: install, configure, understand how tracking and recovery work, manage carts from the admin, and integrate via events and the REST API.

## Table of Contents

- [Overview](#overview)
- [Installation & Activation](#installation--activation)
- [Settings](#settings)
- [How Cart Tracking Works](#how-cart-tracking-works)
- [Recovery Links & Emails](#recovery-links--emails)
- [Order Linking & Recovered Revenue](#order-linking--recovered-revenue)
- [Admin Dashboard & Managing Carts](#admin-dashboard--managing-carts)
- [Developer Guide: Events (Hooks)](#developer-guide-events-hooks)
- [Developer Guide: REST API](#developer-guide-rest-api)
- [Scheduling & Cron](#scheduling--cron)
- [Troubleshooting & FAQ](#troubleshooting--faq)
- [Privacy & Personal Data](#privacy--personal-data)
- [Uninstall & Data](#uninstall--data)

## Overview

Cart Rebound is a WooCommerce abandoned-cart recovery plugin. It records logged-in carts and, when a site owner opts in, guest carts. It flips a cart to _abandoned_ after a configurable idle window, lets shoppers restore their cart through a tokenized recovery link, and attributes recovered revenue back to the real order. It exposes a clean event and REST surface so automation tools can react to abandonment and recovery without coupling to the plugin's internals.

### Features

- **Reliable cart capture** for logged-in carts and opt-in guest carts, including the email a guest types at checkout before submitting — supported on both classic checkout (AJAX + server-side hooks) and the block / Store API checkout.
- **Configurable abandonment detection** driven by Action Scheduler, with a wp-cron fallback. The idle threshold lives in the scan query, so changing it takes effect on the next scan without rescheduling.
- **Tokenized recovery links** that rebuild the cart (items, variations, and coupons) and send the shopper to checkout — no raw session key is exposed in the URL.
- **Accurate revenue attribution**: orders are linked to carts by explicit order meta rather than fuzzy total matching, so coupons, shipping, and tax never break the link. Carts resolve to _recovered_ or _completed_, each with its own timestamp, plus a dedicated recovered-amount field.
- **Optional built-in recovery email**, disabled by default and scheduled a configurable delay after abandonment, supporting the `{first_name}`, `{products}`, `{recovery_url}`, and `{coupon_code}` tokens.
- **Event & REST API for integrations**: fires `do_action( 'cart_rebound_abandoned', $payload )` and `do_action( 'cart_rebound_recovered', $payload )`, and provides a read API for carts, stats, and recovered revenue.
- **Admin dashboard** showing active / abandoned / recovered counts, recovered revenue, recovery rate, and a filterable list of cart sessions with row actions.

### Requirements

- **WordPress** 6.2 or later (tested up to 7.0)
- **WooCommerce** installed and active (declared via the `Requires Plugins: woocommerce` header; the plugin shows an admin notice if WooCommerce is not active)
- **PHP** 7.4 or later

### HPOS compatibility

Cart Rebound is compatible with WooCommerce's High-Performance Order Storage (HPOS / custom order tables). On the `before_woocommerce_init` hook it calls `FeaturesUtil::declare_compatibility( 'custom_order_tables', CART_REBOUND_FILE, true )`, so the plugin works whether WooCommerce stores orders in the legacy posts tables or the custom order tables.

> Note: Cart Rebound is a Composer-based plugin. The plugin bootstraps from `vendor/autoload.php`; if the autoloader is missing it shows an admin notice prompting you to run `composer install` before activation.

---

## Installation & Activation

Cart Rebound is a WooCommerce extension, so WooCommerce must be installed and active before Cart Rebound will run. The plugin declares this dependency in its main file header (`cart-rebound.php`):

```php
* Requires Plugins:  woocommerce
* Requires at least: 6.2
* Requires PHP:      7.4
```

Stable release: **0.1.0**. Tested up to WordPress 7.0.

### Requirements

- WooCommerce (installed and **active**)
- WordPress 6.2 or later
- PHP 7.4 or later

Install and activate WooCommerce first. Because of the `Requires Plugins: woocommerce` header, WordPress (6.5+) recognizes the dependency and will block activation of Cart Rebound — showing its built-in "This plugin requires WooCommerce to be installed and active" notice — until WooCommerce is active.

### Path A — Install the prebuilt zip (recommended)

Use this if you just want to run the plugin and not build it yourself.

1. Download `cart-rebound.zip` from the project's **GitHub Releases** page (`https://github.com/RishadAlam/cart-rebound`).
2. In wp-admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the downloaded `cart-rebound.zip` and click **Install Now**.
4. Click **Activate Plugin**.

The release zip is already production-ready: its front-end assets are pre-built and it ships with a bundled `vendor/autoload.php`, so no `composer install` or `pnpm build` step is needed on the server.

### Path B — Build or develop from source

Clone or download the source into (or link it at)
`wp-content/plugins/cart-rebound`. Source development requires:

- A local WordPress 6.2+ installation with WP-CLI available
- PHP 7.4+ and Composer (`phpdbg` is needed only for `composer test:coverage`)
- Node.js 24+ and pnpm 11.5.0

```bash
# from the plugin root (cart-rebound/)
composer setup        # install PHP + pnpm dependencies and build admin assets
bash scripts/build-zip.sh
```

`composer setup` invokes `bash scripts/setup-development.sh`; the setup script
may also be run directly.

`composer setup` assumes the repository is at the standard plugin path, making
the WordPress root `../../..`. It installs both dependency sets, builds the
admin assets, enables local WordPress debugging and Cart Rebound HMR, installs
WooCommerce if necessary, activates both plugins, and runs the plugin database
migrations. The long-running Vite server is intentionally separate; start it
with `pnpm dev` after setup.

The equivalent dependency/build setup is `composer install`, followed by
`pnpm install --frozen-lockfile` and `pnpm build`. The remaining setup steps use
WP-CLI to configure `wp-config.php`, install and activate WooCommerce, activate
Cart Rebound, and run `wp cart-rebound migrate`.

For dependency maintenance, use the individual project commands:

```bash
pnpm run clean
pnpm run fresh-install
composer clean
composer fresh-install
```

`pnpm run clean` preserves `pnpm-lock.yaml`. `composer clean` removes both
`vendor` and `composer.lock`. Therefore, `composer fresh-install` resolves the
current allowed dependency versions and generates a new lockfile. Neither
cleaner removes compiled assets.

#### Local development with HMR

HMR means **Hot Module Replacement**. While the Vite development server is
running, React, TypeScript, and CSS changes can appear on Cart Rebound admin
pages without a full page reload.

HMR is deliberately opt-in. Add the following to the local WordPress
`wp-config.php` before it loads `wp-settings.php`. If `WP_DEBUG` is already
defined, update that declaration rather than defining the constant twice.

```php
define( 'WP_DEBUG', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'CART_REBOUND_ENABLE_HMR', true );
```

`WP_ENVIRONMENT_TYPE` may be either `local` or `development`. Start Vite from
the plugin root and keep the command running:

```bash
pnpm dev
```

Vite uses the fixed origin `http://localhost:5173` and fails to start if port
5173 is occupied. Once listening, it writes `public/hot`. On Cart Rebound admin
pages, PHP reads that marker and loads the Vite module client and
`resources/js/admin/main.tsx` instead of the compiled `public/build` assets.
The local WordPress site must also be opened over HTTP; an HTTPS admin page will
block the HTTP development scripts as mixed content.

A normal Vite shutdown removes `public/hot`. If Vite exits unexpectedly and
leaves a stale marker, restart `pnpm dev` or run `pnpm build` to remove the
marker and regenerate the compiled assets.

To develop without HMR, leave `CART_REBOUND_ENABLE_HMR` undefined or set it to
`false`, run `pnpm build` after front-end changes, and let WordPress load the
hashed assets in `public/build`. Production environments always ignore
`public/hot`.

`scripts/build-zip.sh` automates the full packaging flow:

1. `pnpm exec vite build` — builds the front-end assets.
2. `composer install --no-dev --optimize-autoloader` — installs production-only PHP dependencies and an optimized autoloader.
3. Stages an explicit runtime allowlist via `rsync`, including PHP, compiled assets, runtime views/configuration, translations, licenses, and the production Composer autoloader. Development tooling and uncompiled source cannot leak into the archive.
4. `composer install` — restores the dev dependencies for continued local work.
5. Zips the staged folder.

The result is written to **`build/cart-rebound.zip`**. Upload that file through **Plugins → Add New → Upload Plugin** exactly as in Path A.

> If you skip the build and activate the raw source without running `composer install`, the plugin will not boot. `cart-rebound.php` checks for `vendor/autoload.php` and, when it is missing, registers an admin notice instead of loading: _"Cart Rebound: run 'composer install' to generate the autoloader before activating the plugin."_ It then returns early, so no functionality is registered until the autoloader exists.

### Activation

1. Confirm WooCommerce is active.
2. Activate **Cart Rebound** from the **Plugins** screen. On activation, `CartRebound\Core\Plugin::activate` runs (registered via `register_activation_hook`).

### Finding the admin menu

Once active, Cart Rebound adds a top-level admin menu item:

- **Menu label:** Cart Rebound (with the `dashicons-screenoptions` icon, near menu position 58)
- **Page slug:** `cart-rebound`
- **Required capability:** `manage_woocommerce`

The menu is registered in `src/Admin/Menu.php` via `add_menu_page( 'Cart Rebound', 'Cart Rebound', 'manage_woocommerce', 'cart-rebound', … )`. Any user who can manage WooCommerce (shop managers and administrators) can open it; users without `manage_woocommerce` will not see the menu. From there you reach the dashboard (active / abandoned / recovered counts, recovered revenue, recovery rate, and the cart-session list) and the settings for the abandonment threshold, cleanup window, and recovery email.

---

## Settings

All plugin behavior is configured under **Cart Rebound → Settings** in the WordPress admin. The form lives in `resources/js/admin/pages/Settings.tsx` and posts to the REST settings endpoint, which persists a single `cart_rebound_settings` option via `CartRebound\Support\Settings` (`src/Support/Settings.php`). Defaults come from `Settings::defaults()`, and every saved value is normalized by `Settings::sanitise()`.

### Every setting

| Key                      | UI label                             | Default | Controls                                                                                |
| ------------------------ | ------------------------------------ | ------- | --------------------------------------------------------------------------------------- |
| `guest_tracking`         | **Track guest carts**                | `false` | Whether carts from logged-out visitors are tracked. Logged-in cart tracking remains on. |
| `abandonment_threshold`  | **Abandonment threshold (minutes)**  | `30`    | Minutes of inactivity before an eligible cart is considered abandoned.                  |
| `scan_interval`          | **Scan interval (minutes)**          | `5`     | How often the background abandonment scan runs.                                         |
| `cleanup_days`           | **Unrecovered cleanup after (days)** | `30`    | Retention for stale active and unconverted abandoned/lost carts.                        |
| `converted_cleanup_days` | **Converted cleanup after (days)**   | `365`   | Retention for recovered and completed cart records.                                     |
| `recovery_email_enabled` | **Send recovery email**              | `false` | Whether one automatic recovery email is scheduled for an eligible abandoned cart.       |
| `email_delay_minutes`    | **Send delay (minutes)**             | `60`    | Delay between abandonment and an enabled automatic recovery email.                      |

The `email_subject`, `email_body`, `email_from_name`, `email_from_email`, and `email_coupon` values are retained as backward-compatible seed values for the first default template. Manage current email content, sender details, and coupons under **Cart Rebound → Templates**.

### Email template merge tags

Template subjects and bodies support these placeholders:

- `{first_name}` — the customer's first name
- `{products}` — the products left in the cart
- `{recovery_url}` — the link that restores the abandoned cart
- `{coupon_code}` — the WooCommerce coupon selected for the template, or an empty value

Example default body:

```text
Hi {first_name}, your cart is still waiting: {products} {recovery_url}
```

### Validation and sanitisation notes

- **Checkboxes** (`guest_tracking`, `recovery_email_enabled`) are coerced to strict booleans server-side via `! empty( … )`.
- **Number fields** (`abandonment_threshold`, `scan_interval`, `cleanup_days`, `converted_cleanup_days`, `email_delay_minutes`) are cast to `int` and forced to a minimum of `1` server-side. The form also enforces `min={1}` client-side and falls back to `1` if the input is left non-numeric.
- **Legacy seed text fields** use `sanitize_text_field()`, `sanitize_textarea_field()`, or `sanitize_email()` according to their type. Template content is separately sanitized when saved from the Templates tab.
- Saved values are merged over the defaults on read (`Settings::all()` does `array_merge( defaults(), stored )`), so any missing key falls back to its default.

---

## How Cart Tracking Works

Cart Rebound watches the live WooCommerce cart, writes a snapshot to a single tracked row per visitor, back-fills the shopper's email/name/phone, and then a background scan flips idle carts to `abandoned`. This section walks the full path from "item added to cart" to a terminal status.

### What gets captured, and when

Capture is wired in `src/Providers/CaptureServiceProvider.php::boot()`. Four WooCommerce cart hooks (all at priority `20`) call `CartTracker::track()`:

- `woocommerce_add_to_cart`
- `woocommerce_cart_updated`
- `woocommerce_cart_item_removed`
- `woocommerce_cart_emptied`

Each call re-snapshots the cart and upserts the row. The snapshot (`CartTracker::snapshot()`) stores: `cart_contents` (JSON of product_id, variation_id, variation, quantity, name, price, line_total per line), `cart_total` (from `WC()->cart->get_total('edit')`), `currency`, `items_count`, `coupons` (applied coupon codes), and `checkout_url`.

Tracking runs whenever `CartTracker::tracking_allowed()` passes: WooCommerce must be loaded. There is no master on/off toggle — tracking is active while the plugin is active. Logged-in users are always tracked; guests are tracked only when the `guest_tracking` setting is on.

### The stable session key and the `cart_rebound_ref` cookie

WooCommerce rotates its session customer id on login/expiry, which would otherwise fragment one shopper into multiple rows. `src/Tracking/SessionManager.php` solves this with a stable, unguessable identifier created specifically for Cart Rebound:

1. `resolve_session_key()` first reads the first-party cookie `cart_rebound_ref` (constant `SessionManager::COOKIE`) and accepts only the plugin's strict 64-character alphanumeric format.
2. Otherwise it reads Cart Rebound's private slot in the WooCommerce session. If the slot is empty, it creates a cryptographically random 64-character identifier with `wp_generate_password()` and stores it there.
3. The identifier is mirrored into the first-party cookie. If there is no WooCommerce session yet, the method returns an empty string and nothing is tracked.

The cookie is set for ~30 days (`30 * DAY_IN_SECONDS`), with `path` = `COOKIEPATH` (or `/`), `secure` on SSL, `httponly` true, and `samesite=Lax`. The identifier is independent of predictable WordPress user IDs and WooCommerce customer IDs. Because it is pinned to the cookie and WooCommerce session, the same DB row keeps being updated even after WooCommerce rotates its customer id. The row is also protected by a UNIQUE index on `session_key`.

### Logged-in user vs guest identity

- Logged-in: on the first insert, `CartTracker::logged_in_identity()` back-fills `email`, `first_name`, and `last_name` from the user account and stores `user_id` = `get_current_user_id()`.
- Guest: the row is created with no identity; email/name/phone are filled later by the checkout-capture paths below.
- Login merge: `SessionManager::merge_guest_into_user()` attaches a guest's existing tracked row (matched by the cookie key) to the user that just logged in — setting `user_id`, and copying account email/name only if the row had no email yet.

### Guest email capture at classic checkout

Guests typically have no identity until checkout, so three independent paths capture it (any one is enough — they all funnel into `CartTracker::capture_identity()`, which sanitises fields, requires a valid email to store it, and refuses to write to a terminal row):

1. Front-end beacon (`assets/js/checkout-capture.js`). Enqueued only on the checkout page (not the order-received page) when `guest_tracking` is on. It reads `billing_email`, `billing_first_name`, `billing_last_name`, and `billing_phone`; debounces 600 ms on `billing_*` changes; fires immediately on email blur; and POSTs JSON to `cart-rebound/v1/capture` with an `X-WP-Nonce` (`wp_rest`) header. It sends nothing unless an email is present. The nonce-gated route is handled by `CaptureController::store()`.
2. `woocommerce_checkout_update_order_review` → `capture_from_review()`. The serialised review payload is parsed with `parse_str()` and mapped from `billing_*` fields. This fires during normal AJAX checkout refreshes.
3. `woocommerce_after_checkout_validation` → `capture_from_validation()`. A no-JS safety net that runs at order submission so identity is captured even if the beacon never fired.

### Guest capture at block / Store API checkout

For the block checkout there is no classic billing form to scrape. `woocommerce_store_api_checkout_update_order_from_request` → `capture_from_store_api()` reads `billing_email`, `billing_first_name`, `billing_last_name`, and `billing_phone` directly off the `WC_Order` being built from the request and passes them to `capture_identity()`.

### How a cart is flipped to "abandoned"

The recurring scan `src/Cron/AbandonmentDetector.php` (action hook `cart_rebound_scan_abandoned`) does this. The idle window lives in the query, so changing the threshold takes effect on the very next scan with no rescheduling. A row is selected only when ALL of these hold:

- `status` = `active`
- `abandonment_notified` = `0`
- `email` is not empty (a captured email is required)
- `items_count` > `0` (the cart still has items)
- `last_activity` < cutoff, where cutoff = now − (`abandonment_threshold` minutes, minimum 1) × 60s

Carts with no captured email are never abandoned (they are instead purged later as stale `active` rows by the daily `Janitor`). The scan runs in batches of 50, capped at 500 rows per run. `mark_abandoned()` updates the row to `status = abandoned`, sets `abandoned_at`, and sets `abandonment_notified = 1` before dispatching the abandonment event — so a row can never be picked up twice. If `recovery_email_enabled` is on and the row has an email, a recovery email is scheduled `email_delay_minutes` later.

### An active shopper re-activates an abandoned cart

If a shopper comes back and touches the cart again, `CartTracker::upsert()` detects the existing row is `abandoned` (and not terminal) and returns it to the active funnel: `status` → `active`, `abandonment_notified` → `0`, `abandoned_at` → `null`. Any non-terminal row also gets `last_activity` bumped on every snapshot.

### Status lifecycle

Statuses are defined in `src/Models/CartSession.php`:

```
active ──(idle past threshold, has email + items; scan)──> abandoned
abandoned ──(shopper returns to cart)──> active        (re-activation)
abandoned ──(paid order, or arrived via recovery link)──> recovered   (terminal)
active    ──(paid order, never abandoned)─────────────────> completed  (terminal)
abandoned/lost ──(no order, past cleanup window)──> purged by Janitor
```

- `active` — new or still-active cart.
- `abandoned` — idle past the threshold; abandonment event fired.
- `recovered` — set by `src/Recovery/OrderLinker.php` when a linked order reaches a paid status (`processing`/`completed`) and the cart was either `abandoned` or arrived through a recovery link; stores `order_id`, `recovered_amount`, and `recovered_at`.
- `completed` — a cart that converted to a paid order without ever being abandoned (`OrderLinker::link()` when the cart was not abandoned/recovered).
- `lost` — terminal status for an abandoned cart purged after the cleanup window with no order; treated as terminal alongside `recovered`/`completed`.

`recovered`, `completed`, and `lost` are terminal: `CartTracker::is_terminal()` stops further tracking on those rows. When a terminal row's key is reused for a brand-new cart cycle, the old row's `session_key` is archived (renamed to `key#id`) to free the UNIQUE slot, and a fresh `active` row is inserted with a new 32-char `recovery_token` — preserving the prior order/revenue attribution.

---

## Recovery Links & Emails

Cart Rebound recovers an abandoned cart by handing the shopper a single tokenized link. Clicking it rebuilds their exact cart in WooCommerce and drops them on checkout. That link can be delivered automatically through an optional recovery email scheduled a configurable delay after a cart is flagged abandoned.

### The tokenized recovery URL

Every tracked cart row carries an unguessable `recovery_token`. `RecoveryLink::url()` (`src/Recovery/RecoveryLink.php`) builds the link by appending two query vars to the WooCommerce **cart** page URL (`wc_get_cart_url()`, falling back to `home_url('/')`):

| Query var              | Constant                    | Value                                      |
| ---------------------- | --------------------------- | ------------------------------------------ |
| `cart_rebound_recover` | `RecoveryLink::QUERY_FLAG`  | always `1`                                 |
| `cart_rebound_token`   | `RecoveryLink::QUERY_TOKEN` | the row's `recovery_token` (rawurlencoded) |

A finished link looks like:

```
https://example.com/cart/?cart_rebound_recover=1&cart_rebound_token=<token>
```

The token is the only credential in the URL — the session key is never exposed. Because the token is unguessable, the handler authenticates on the token alone and does **not** require a WordPress nonce (the same model as a password-reset link).

### What happens when a shopper clicks it

`RecoveryHandler::handle()` (`src/Recovery/RecoveryHandler.php`) is hooked on `template_redirect` (registered in `src/Providers/RecoveryServiceProvider.php`), so it runs on every front-end page load — including the cart page the link targets. The flow:

1. It reads `cart_rebound_recover`; if the value is not exactly `1`, it returns and the page renders normally.
2. It reads `cart_rebound_token`; an empty token also returns.
3. It looks up a `CartSession` row whose `recovery_token` matches **and** whose `status` is `active` or `abandoned`. No match (e.g. a cart that already converted, or a bad token) means it silently returns — no error, no redirect.
4. On a match it calls `restore_cart()`, which:
    - empties the current WooCommerce cart (`WC()->cart->empty_cart()`),
    - decodes the stored `cart_contents` JSON and re-adds each line via `add_to_cart( product_id, quantity, variation_id, variation )` — quantity is forced to at least `1`, lines with a non-positive `product_id` are skipped, and variation IDs/attributes are preserved,
    - decodes the stored `coupons` JSON and re-applies each non-empty coupon code via `apply_coupon()`.
5. It binds the cart row id into the WooCommerce session under `cart_rebound_recovery_cart_id` (`RecoveryHandler::SESSION_CART_ID`) so a resulting order can later be attributed as recovered by `OrderLinker`.
6. It redirects with `wp_safe_redirect()` to `wc_get_checkout_url()` (falling back to `home_url('/')`) and `exit`s.

If WooCommerce or its cart object isn't available, `restore_cart()` returns `false` and no redirect happens.

### The optional recovery email

Sending an email is off by default. The `recovery_email_enabled` switch and `email_delay_minutes` delay live in `cart_rebound_settings`. Email subjects, rich-text bodies, sender details, and optional coupons are managed under **Cart Rebound → Templates**. Exactly one template is marked as the default for automatic sends; an administrator can choose any template for an on-demand send.

**Scheduling.** When `AbandonmentDetector` (`src/Cron/AbandonmentDetector.php`) flips an idle cart to `abandoned`, and `recovery_email_enabled` is on and the row has a non-empty email, it schedules **one** send: `Scheduler::schedule_single( time() + email_delay_minutes*60, RecoveryMailer::HOOK, [ $cart_id ] )`. `RecoveryMailer::HOOK` is the action name `cart_rebound_send_recovery_email`. The `Scheduler` (`src/Cron/Scheduler.php`) prefers WooCommerce's bundled **Action Scheduler** (`as_schedule_single_action()`, group `cart-rebound`) and falls back to native `wp_schedule_single_event()` only when Action Scheduler isn't available. The handler is wired in `SchedulerServiceProvider::boot()` to `RecoveryMailer::send()`.

**Eligibility / skip rules.** `RecoveryMailer::send()` (`src/Mail/RecoveryMailer.php`) re-checks the cart at send time and bails (sends nothing) if any of these is true:

- `recovery_email_enabled` is off;
- the cart row no longer exists;
- the stored `email` is empty or not a valid email;
- the row's `status` is no longer `abandoned` (e.g. it converted in the meantime — skip-if-converted);
- `email_sent` is already `1` (dedup — never send twice);
- `items_count` is `0` or less (skip-if-empty).

On a successful `wp_mail()` send it sets the row's `email_sent` to `1`, which is what makes the dedup permanent.

**Template tokens.** `build_body()` replaces four supported merge tags in the selected template:

| Token            | Replaced with                                                                     |
| ---------------- | --------------------------------------------------------------------------------- |
| `{first_name}`   | the shopper's stored first name (escaped)                                         |
| `{products}`     | an HTML `<ul>` list of `name × quantity` per cart line (empty string if no items) |
| `{recovery_url}` | the tokenized recovery URL from `RecoveryLink::url()` (escaped)                   |
| `{coupon_code}`  | the selected WooCommerce coupon code, or an empty value                           |

The token-replaced content is rendered inside the HTML template at `resources/views/emails/recovery.php`, which wraps it in a 600px container and appends a styled "Complete your order" button pointing at the same recovery URL. If that template file is missing/unreadable, the body falls back to `wpautop( $content )`.

**From header & content type.** A `From:` header is added only when the selected template has a valid `from_email`; it uses `from_name` as the label (or the address itself if no name). The mailer temporarily adds a `wp_mail_content_type` filter forcing `text/html` and removes it immediately after sending, so the HTML content type never leaks into other site email.

---

## Order Linking & Recovered Revenue

Cart Rebound connects a finished WooCommerce order back to the cart it came from, and decides whether that cart should be marked **recovered** or **completed**. The whole flow is driven by explicit order meta — not by guessing from cart totals or amounts — which makes attribution deterministic and HPOS-safe.

### How an order is linked to a cart (explicit meta, never total matching)

Two order meta keys carry the link, both defined on `CartRebound\Recovery\OrderLinker`:

- `_cart_rebound_session_id` (`OrderLinker::META_CART`) — the row id of the originating cart session, stamped onto the order.
- `_cart_rebound_recovered` (`OrderLinker::META_RECOVERED`) — set to `'1'` when the order arrived through a recovery link.

When an order is created, `on_order_created()` resolves the originating cart and stamps its id onto the order. There is no fuzzy matching on order total, amount, or line items anywhere in the linker — the cart is found by:

1. **Recovery-link binding (highest priority).** If the WooCommerce session holds a bound cart id (`RecoveryHandler::SESSION_CART_ID`), that cart is used and the order is flagged as arriving `via_link` (which writes `_cart_rebound_recovered = '1'`).
2. **Session key match.** Otherwise the resolved session key (`SessionManager::resolve_session_key()`) is matched against open carts (`active` or `abandoned`), most recent activity first.
3. **Customer id match.** Failing that, for logged-in customers it matches open carts by `user_id`, most recent activity first.

If none of these resolve to a cart, no id is stamped and nothing is attributed. The recovery-link binding is **single-use**: after stamping, `clear_recovery_binding()` removes it from the WooCommerce session so a later, unrelated order cannot be mis-attributed to the same recovery cart.

The stamping only happens if `_cart_rebound_session_id` is not already set, so re-entrant hook firing won't overwrite an existing link.

### The cart only transitions on a PAID order

Stamping the cart id is separate from transitioning the cart's status. The cart is **only** resolved to its final state when the order reaches a paid status — `processing` or `completed`:

- In `on_order_created()`, the cart is linked immediately only if the new order already has status `processing` or `completed`.
- For orders that start pending/unpaid (e.g. async/IPN gateways), `on_status_changed()` waits for the status to change **to** `processing` or `completed`, then reconciles the stamped-but-unlinked order.

A pending or never-paid order will not prematurely complete or recover a cart.

### Recovered vs completed

The decision is made in the private `link()` method once the order is paid:

- **Recovered** — the cart is marked `recovered` if either the order arrived `via_link` (`_cart_rebound_recovered === '1'`) **or** the cart's current status was `abandoned`. In other words: the shopper either clicked a recovery link, or had already been flagged as an abandoned cart that later converted.
- **Completed** — if neither condition holds (the cart converted normally without ever being abandoned and without a recovery link), the cart is marked `completed` instead.

`link()` is idempotent: if the cart row already has an `order_id`, it returns early and does not re-transition or re-fire events. This guards against the same order triggering both `on_order_created` and `on_status_changed`.

For a **recovered** cart, the update writes:

- `status` → recovered
- `order_id` → the order id
- `recovered_amount` → **the order total** (`$order->get_total()`)
- `currency` → `$order->get_currency()`
- `recovered_at` → current UTC timestamp

It then dispatches a `recovered` event with the channel `email_link` (when `via_link`) or `direct` (abandoned-then-converted).

For a **completed** cart, the update writes `status` → completed, `order_id`, and `completed_at` — no recovered amount and no recovery event.

So **recovered revenue is simply the order total of orders attributed to recovered carts** — Cart Rebound does not compute a partial or delta amount; the full order total is recorded as the recovered amount.

### Admin and programmatic orders are intentionally excluded

`CartRebound\Providers\RecoveryServiceProvider::boot()` wires only front-end checkout entry points:

- `woocommerce_checkout_order_processed` — classic checkout
- `woocommerce_store_api_checkout_order_processed` — block / Store API checkout (handled via `on_store_api_order()`, which forwards the order id to `on_order_created()`)
- `woocommerce_order_status_changed` — for later paid-status reconciliation

The generic `woocommerce_new_order` hook is **deliberately not** registered. As noted in the code comment, it fires for admin-created and programmatically created orders too, and hooking it would risk mis-attributing those orders to an unrelated tracked cart. Only orders that pass through an actual customer checkout flow are auto-attributed.

### HPOS safety

All order reads and writes go through the WooCommerce order object API rather than direct post meta:

- Reads use `$order->get_meta()` (e.g. `$order->get_meta( OrderLinker::META_CART )`).
- Writes use `$order->update_meta_data()` followed by `$order->save()`.
- Orders are loaded with `wc_get_order()` and type-checked against `WC_Order`.

Because it never touches `get_post_meta`/`update_post_meta` or the posts table directly, the linker works correctly whether WooCommerce stores orders in the legacy posts table or in High-Performance Order Storage (HPOS).

---

## Admin Dashboard & Managing Carts

Cart Rebound adds a single top-level admin menu entry. In `src/Admin/Menu.php` the page is registered with `add_menu_page()` using the title **Cart Rebound**, the menu slug `cart-rebound` (`Menu::SLUG`), the `dashicons-screenoptions` icon, and menu position `58`. The required capability for the page is **`manage_woocommerce`** — only users who can manage WooCommerce see the menu and can open the screen.

The screen is a React single-page app with two views: a **Dashboard** of statistics and a **Carts** list. Both views read and write through the plugin's own REST routes (defined in `routes/admin.php`), every one of which is guarded by the middleware stack `array( 'nonce', 'can:manage_woocommerce' )`. So beyond seeing the menu, each data request also re-checks the nonce and the `manage_woocommerce` capability server-side.

### Dashboard stat cards

The Dashboard (`resources/js/admin/pages/Dashboard.tsx`) calls `GET stats`, which is served by `StatsController::index()` → `CartRepository::get_stats()`. It renders six cards in a responsive grid (2 columns on small screens, 3 on medium and up):

| Card                  | Source value        | Notes                                                             |
| --------------------- | ------------------- | ----------------------------------------------------------------- |
| **Active**            | `counts.active`     | Count of carts with status `active`.                              |
| **Abandoned**         | `counts.abandoned`  | Count of carts currently in status `abandoned`.                   |
| **Recovered**         | `counts.recovered`  | Count of carts in status `recovered`.                             |
| **Completed**         | `counts.completed`  | Count of carts in status `completed`.                             |
| **Recovered revenue** | `recovered_revenue` | Sum of `recovered_amount` across all carts in status `recovered`. |
| **Recovery rate**     | `recovery_rate`     | Shown as `{value}%`.                                              |

A few details worth knowing, grounded in `CartRepository::get_stats()`:

- The per-status counts are live `COUNT` queries against the cart sessions table for each of the five statuses (`active`, `abandoned`, `recovered`, `completed`, `lost`). The Dashboard surfaces four of them as cards; `lost` is counted but not given its own card.
- **Recovery rate is computed from purge-immune lifetime counters, not the live counts.** It uses two persisted options (`EventDispatcher::OPTION_ABANDONED` and `EventDispatcher::OPTION_RECOVERED`) as `round( ( lifetime_recovered / lifetime_abandoned ) * 100, 1 )`, and is `0.0` when no carts have ever been abandoned. This is deliberate: the background Janitor deletes unrecovered abandoned carts, so a rate based on live status counts would drift upward over time.
- **Recovered revenue** is formatted client-side with `Intl.NumberFormat` in `currency` style using the `currency` field returned by the API (which is `get_woocommerce_currency()`, or an empty string if WooCommerce is unavailable). If the currency is empty or `Intl` throws, it falls back to a plain two-decimal number.

The Dashboard shows `Loading…` while fetching. On error it shows `Could not load statistics.`, except for a `401` response, which renders `Your session has expired. Please reload the page.`

### Carts tab

The Carts view (`resources/js/admin/pages/Carts.tsx`) lists tracked carts via `GET carts`, served by `CartsController::index()` → `CartRepository::get_carts()`.

**Status filter.** A dropdown lets you filter by status. The options are `All` (empty value, no filter), `active`, `abandoned`, `recovered`, `completed`, and `lost`. Changing the filter resets the view to page 1. Server-side, the chosen status is passed through `CartsController::status_arg()` (which sanitizes a single value or a list) and applied by `CartRepository::apply_filters()` as a `WHERE status = ...` (or `WHERE status IN (...)` for arrays). An optional `email` substring filter exists in the API (`WHERE email LIKE %...%`), but the Carts UI sends it empty.

**Columns.** Each row renders:

| Column        | Field           | Display                                                     |
| ------------- | --------------- | ----------------------------------------------------------- |
| Email         | `email`         | The email, or `—` when blank.                               |
| Items         | `items_count`   | Number of line items.                                       |
| Total         | `cart_total`    | Shown with two decimals (`toFixed(2)`); no currency symbol. |
| Status        | `status`        | Rendered as a small badge.                                  |
| Last activity | `last_activity` | Timestamp string from the row.                              |
| Order         | `order_id`      | `#{order_id}` when linked to an order, otherwise `—`.       |
| Actions       | —               | Row actions (see below).                                    |

**Ordering & pagination.** Results are ordered by `last_activity` descending. The Carts UI defaults to 20 rows per page, while the Activity Log defaults to 30. Both tables use the shared **Rows per page** selector with options for 10, 20, 30, 50, or 100 rows; changing it resets the table to page 1. The API defaults to 20 when no value is supplied and caps `per_page` at 100. Pagination uses **Previous** / **Next** buttons with a `Page X of Y` indicator, where the total page count is `ceil(total / per_page)`. Previous is disabled on page 1 and Next is disabled on the last page. When a page has no rows, the view shows `No carts found.` The list shows `Loading…` while fetching and `Could not load carts.` on error.

### Row actions

Each cart row offers four icon actions:

**View details.** The eye button opens the cart-detail dialog with the captured customer identity, line items, totals, coupons, linked order, and lifecycle timeline. Clicking the cart ID provides the same shortcut.

**Mark recovered.** Enter an order ID into the numeric input (minimum `1`) and click **Mark recovered**. The component parses the input with `parseInt` and only sends the request when the value is greater than `0`. It calls `POST carts/{id}/mark-recovered` with the body `{ id, order_id }`. The request is validated by `MarkRecoveredRequest`, whose rules require both fields:

```
'id'       => 'required|integer',
'order_id' => 'required|integer',
```

The work is done in `CartRepository::mark_recovered()`, which is intentionally strict:

- It returns `false` (no-op) if WooCommerce's `wc_get_order()` is unavailable, if the cart row doesn't exist, or if the cart is **already linked to an order** (`order_id > 0`) — it never re-attributes an already-recovered cart.
- It loads the order with `wc_get_order()` and bails if the result is not a `WC_Order`.
- On success it updates the cart to status `recovered`, sets `order_id`, stores `recovered_amount` from the order total and `currency` from the order, stamps `recovered_at`, and fires the `recovered` event with source `'direct'`.

The endpoint responds with `{ "updated": true|false }`.

**Delete.** The **Delete** button calls `DELETE carts/{id}` (`CartsController::destroy()` → `CartRepository::delete_cart()`), which removes the cart row and responds with `{ "deleted": true|false }`. There is no confirmation prompt in the UI — the click deletes immediately.

> Note: All of these actions require the `manage_woocommerce` capability and a valid nonce; requests without them are rejected before reaching the controller.

---

## Developer Guide: Events (Hooks)

Cart Rebound exposes its abandonment and recovery lifecycle through standard WordPress action hooks fired by `src/Events/EventDispatcher.php`. Automation tools (FlowMattic, Bit Integrations, etc.) and custom code should hook these actions rather than coupling to the plugin internals. Every event passes a single argument: a flat, mappable `$payload` array.

### Actions

| Action                   | When it fires                                        | Payload                              |
| ------------------------ | ---------------------------------------------------- | ------------------------------------ |
| `cart_rebound_abandoned` | A cart is detected as abandoned.                     | Base payload                         |
| `cart_rebound_recovered` | An abandoned cart is recovered by a completed order. | Base payload + recovered-only fields |

When `EventDispatcher::abandoned()` runs it calls `do_action( 'cart_rebound_abandoned', $payload )` so a listener receives the cart data. The dispatcher also bumps lifetime counters after firing (`cart_rebound_lifetime_abandoned` on abandonment, `cart_rebound_lifetime_recovered` on recovery), but those are internal options, not part of the payload.

### Base payload (all events)

Both the abandoned and the recovered event include these base fields, built by `base_payload()`:

| Key                | Type   | Source / notes                                                    |
| ------------------ | ------ | ----------------------------------------------------------------- |
| `cart_id`          | int    | Internal cart session row id (`row['id']`).                       |
| `session_id`       | string | WooCommerce/session key (`row['session_key']`).                   |
| `customer_id`      | int    | WordPress user id, `0` for guests (`row['user_id']`).             |
| `customer_email`   | string | `row['email']`.                                                   |
| `first_name`       | string | `row['first_name']`.                                              |
| `last_name`        | string | `row['last_name']`.                                               |
| `phone`            | string | `row['phone']`.                                                   |
| `cart_total`       | float  | `row['cart_total']`.                                              |
| `currency`         | string | `row['currency']`.                                                |
| `cart_items_count` | int    | `row['items_count']`.                                             |
| `products`         | array  | List of line items (see structure below).                         |
| `checkout_url`     | string | `row['checkout_url']`.                                            |
| `recovery_url`     | string | Built from the cart's `recovery_token` via `RecoveryLink::url()`. |
| `last_activity`    | string | `row['last_activity']`.                                           |

Each entry in the `products` array (decoded from the stored cart snapshot) has this shape:

| Key          | Type   | Source               |
| ------------ | ------ | -------------------- |
| `product_id` | int    | `line['product_id']` |
| `name`       | string | `line['name']`       |
| `qty`        | int    | `line['quantity']`   |
| `price`      | float  | `line['price']`      |
| `total`      | float  | `line['line_total']` |

If the stored cart snapshot is missing or not valid JSON, `products` is an empty array.

### Recovered-only fields

`cart_rebound_recovered` adds the following keys to the base payload (in this order), set in `EventDispatcher::recovered()`:

| Key                | Type   | Source / notes                                                         |
| ------------------ | ------ | ---------------------------------------------------------------------- |
| `order_id`         | int    | The recovering order's id, `$order->get_id()`.                         |
| `recovered_amount` | float  | `row['recovered_amount']` if present, otherwise `$order->get_total()`. |
| `recovered_at`     | string | `row['recovered_at']` (empty string if unset).                         |
| `recovery_method`  | string | How the cart was recovered: `'email_link'` or `'direct'`.              |

### Example: listen for abandonment

```php
add_action( 'cart_rebound_abandoned', function ( array $payload ) {
	// Base payload fields are available here.
	error_log( sprintf(
		'Cart %d abandoned by %s — %s %.2f across %d items. Recover: %s',
		$payload['cart_id'],
		$payload['customer_email'],
		$payload['currency'],
		$payload['cart_total'],
		$payload['cart_items_count'],
		$payload['recovery_url']
	) );

	foreach ( $payload['products'] as $product ) {
		// $product['product_id'], ['name'], ['qty'], ['price'], ['total']
	}
} );
```

### Example: listen for recovery

```php
add_action( 'cart_rebound_recovered', function ( array $payload ) {
	// Base payload PLUS the recovered-only fields.
	error_log( sprintf(
		'Cart %d recovered via %s by order #%d — %s %.2f at %s',
		$payload['cart_id'],
		$payload['recovery_method'],   // 'email_link' or 'direct'
		$payload['order_id'],
		$payload['currency'],
		$payload['recovered_amount'],
		$payload['recovered_at']
	) );
} );
```

---

## Developer Guide: REST API

Cart Rebound registers all of its routes under a single REST namespace: **`cart-rebound/v1`**. The base URL is therefore `rest_url( 'cart-rebound/v1' )`, which on a standard install resolves to:

```
https://your-site.com/wp-json/cart-rebound/v1
```

Routes are defined in `routes/api.php` (front-end / public-but-nonce) and `routes/admin.php` (authenticated admin), then registered on the `rest_api_init` hook by `RouteServiceProvider`. Each route declares a middleware stack that the router runs as its `permission_callback`, so no route is callable without passing its guards.

### Authentication model

Two middleware layers gate the endpoints:

- **`nonce`** — `VerifyNonce` (`src/Http/Middleware/VerifyNonce.php`) reads the **`X-WP-Nonce`** request header and verifies it against the `wp_rest` action with `wp_verify_nonce()`. A missing or stale nonce returns a `401` with code `cart_rebound_invalid_nonce`.
- **`can:<capability>`** — `RequireCapability` (`src/Http/Middleware/RequireCapability.php`) calls `current_user_can()`. Failure returns a `403` with code `cart_rebound_forbidden`. The admin routes all use `can:manage_woocommerce`.

This means admin endpoints require **both** a valid WordPress logged-in session (the cookie that `current_user_can()` relies on) **and** the `X-WP-Nonce` header. The standard `wp.apiFetch` middleware and a localized nonce handle this automatically in the admin UI.

The pipeline is "secure by default": a route with an empty middleware stack is denied with a `403` (`cart_rebound_no_authorization`). The middleware aliases are defined in `src/Http/Kernel.php` (`nonce`, `cors`, `public`), and `can:` is parsed dynamically.

#### Where the nonce comes from

The plugin localizes the nonce and base URL for its own scripts (see `src/Providers/AssetServiceProvider.php` and `src/Providers/CaptureServiceProvider.php`):

```php
'apiUrl'   => esc_url_raw( rest_url( 'cart-rebound/v1' ) ),
'nonce'    => wp_create_nonce( 'wp_rest' ),
// capture beacon:
'endpoint' => esc_url_raw( rest_url( 'cart-rebound/v1/capture' ) ),
'nonce'    => wp_create_nonce( 'wp_rest' ),
```

A typical authenticated call:

```js
fetch(crData.apiUrl + '/carts?status=abandoned&page=1&per_page=20', {
	headers: { 'X-WP-Nonce': crData.nonce },
	credentials: 'same-origin', // send the auth cookie
});
```

### Public-but-nonce capture beacon

`POST capture` (`routes/api.php`) is **state-changing but intentionally nonce-only** — it carries `nonce` middleware but **no capability check**. Guests have no capability, and the payload is a non-sensitive cart-identity snapshot used to back-fill the tracked cart row. This mirrors the WooCommerce Store API path. It is the only write endpoint reachable by a logged-out visitor (a valid `wp_rest` nonce is still required).

### Endpoint reference

All paths below are relative to `cart-rebound/v1`. Error responses follow the standard WordPress `WP_Error` JSON shape: `{ "code": "...", "message": "...", "data": { "status": <int>, ... } }`.

| Method | Path                         | Auth                               | Purpose                               |
| ------ | ---------------------------- | ---------------------------------- | ------------------------------------- |
| GET    | `/ping`                      | `nonce`                            | Boilerplate health check              |
| POST   | `/capture`                   | `nonce` (public-but-nonce)         | Guest identity back-fill beacon       |
| GET    | `/carts`                     | `nonce` + `can:manage_woocommerce` | List tracked carts                    |
| GET    | `/carts/{id}`                | `nonce` + `can:manage_woocommerce` | Fetch one cart                        |
| DELETE | `/carts/{id}`                | `nonce` + `can:manage_woocommerce` | Delete a cart row                     |
| POST   | `/carts/{id}/mark-recovered` | `nonce` + `can:manage_woocommerce` | Manually attribute a cart to an order |
| GET    | `/stats`                     | `nonce` + `can:manage_woocommerce` | Aggregate dashboard stats             |
| GET    | `/settings`                  | `nonce` + `can:manage_woocommerce` | Read plugin settings                  |
| POST   | `/settings`                  | `nonce` + `can:manage_woocommerce` | Update plugin settings                |

---

#### GET `/ping`

Boilerplate health check (`PingController::index`). Requires only `nonce`.

```json
{ "pong": true, "version": "0.1.0" }
```

---

#### POST `/capture`

Back-fills the email/name/phone a guest enters at checkout onto the current cart row (`CaptureController::store` → `CartTracker::capture_identity`). Validated by `CaptureEmailRequest`.

Body params (all optional; validated by `src/Http/Requests/CaptureEmailRequest.php`):

| Param        | Rules                   |
| ------------ | ----------------------- |
| `email`      | `email`, max 100 chars  |
| `first_name` | `string`, max 100 chars |
| `last_name`  | `string`, max 100 chars |
| `phone`      | `string`, max 40 chars  |

Success response:

```json
{ "captured": true }
```

Invalid input (e.g. a malformed email) returns `422` with code `cart_rebound_validation_failed` and a field-level `data.errors` map.

---

#### GET `/carts`

Lists carts with optional filters and paging (`CartsController::index` → `CartRepository::get_carts`).

Query params:

| Param      | Type            | Notes                                                                                                                                                           |
| ---------- | --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `status`   | string or array | One of `active`, `abandoned`, `recovered`, `completed`, `lost`. Pass an array (`status[]=abandoned&status[]=lost`) to match multiple. Empty = no status filter. |
| `email`    | string          | Substring match (`LIKE %email%`).                                                                                                                               |
| `page`     | int             | 1-based; clamped to a minimum of 1.                                                                                                                             |
| `per_page` | int             | Default 20, capped at 100.                                                                                                                                      |

Results are ordered by `last_activity DESC`. Success response:

```json
{
	"items": [{/* cart object, see below */}],
	"total": 42,
	"page": 1,
	"per_page": 20
}
```

Each cart object (from `CartRepository::present()`) has this shape:

```json
{
	"id": 12,
	"session_key": "abc123",
	"user_id": 0,
	"email": "guest@example.com",
	"first_name": "Ada",
	"last_name": "Lovelace",
	"phone": "",
	"cart_total": 59.99,
	"currency": "USD",
	"items_count": 2,
	"status": "abandoned",
	"order_id": 0,
	"recovered_amount": 0.0,
	"created_at": "2026-06-30 10:00:00",
	"last_activity": "2026-06-30 10:15:00",
	"abandoned_at": "2026-06-30 10:45:00",
	"recovered_at": "",
	"completed_at": "",
	"products": [
		{ "product_id": 101, "name": "Widget", "qty": 1, "total": 29.99 }
	],
	"coupons": ["save10"]
}
```

---

#### GET `/carts/{id}`

Fetches a single cart (`CartsController::show`). `{id}` is the numeric cart id (`(?P<id>\d+)`). Returns the cart object shown above on success, or `404` when the row does not exist:

```json
{ "message": "Cart not found." }
```

---

#### DELETE `/carts/{id}`

Deletes the cart row (`CartsController::destroy` → `CartRepository::delete_cart`).

```json
{ "deleted": true }
```

---

#### POST `/carts/{id}/mark-recovered`

Manually attributes a cart to a completed order (`CartsController::mark_recovered`). Validated by `MarkRecoveredRequest` (`src/Http/Requests/MarkRecoveredRequest.php`):

| Param      | Source   | Rules                 |
| ---------- | -------- | --------------------- |
| `id`       | URL path | `required`, `integer` |
| `order_id` | body     | `required`, `integer` |

On success the cart's status is set to `recovered`, the order total/currency are copied onto the row, and a `recovered` event is dispatched. The operation is **idempotent**: it returns `updated: false` if the cart is already linked to an order, if the order id does not resolve to a `WC_Order`, or if WooCommerce is unavailable.

```json
{ "updated": true }
```

Missing `id` or `order_id` returns `422` (`cart_rebound_validation_failed`).

---

#### GET `/stats`

Returns aggregate dashboard statistics (`StatsController::index` → `CartRepository::get_stats`).

```json
{
	"counts": {
		"active": 5,
		"abandoned": 12,
		"recovered": 3,
		"completed": 40,
		"lost": 1
	},
	"recovered_revenue": 149.97,
	"recovery_rate": 25.0,
	"currency": "USD"
}
```

Notes:

- `counts` are live status counts per cart status.
- `recovered_revenue` sums `recovered_amount` across recovered carts.
- `recovery_rate` is a percentage (rounded to 1 dp) computed from **lifetime** counters (`get_option` totals), not live counts, because the Janitor purges unrecovered abandoned carts over time.
- `currency` comes from `get_woocommerce_currency()` (empty string if WooCommerce is not active).

---

#### GET `/settings`

Returns the full settings array merged over defaults (`SettingsController::index` → `Settings::all()`). The settings are stored in the `cart_rebound_settings` option. Shape and defaults (from `src/Support/Settings.php`):

```json
{
	"guest_tracking": false,
	"abandonment_threshold": 30,
	"scan_interval": 5,
	"cleanup_days": 30,
	"converted_cleanup_days": 365,
	"recovery_email_enabled": false,
	"email_delay_minutes": 60,
	"email_subject": "You left something in your cart",
	"email_body": "Hi {first_name}, your cart is still waiting: {products} {recovery_url}",
	"email_from_name": "",
	"email_from_email": "",
	"email_coupon": ""
}
```

---

#### POST `/settings`

Persists settings and re-syncs the scheduler (`SettingsController::update` → `Settings::update()`). Only these keys are read from the request; any other params are ignored. A `null` (omitted) param leaves the stored value unchanged, while `''` is an explicit clear that the `Settings` sanitiser coerces per field type:

`guest_tracking`, `abandonment_threshold`, `scan_interval`, `cleanup_days`, `converted_cleanup_days`, `recovery_email_enabled`, `email_delay_minutes`, `email_subject`, `email_body`, `email_from_name`, `email_from_email`, `email_coupon`.

Sanitisation rules: booleans via `! empty()`; `abandonment_threshold`, `scan_interval`, `cleanup_days`, `converted_cleanup_days`, and `email_delay_minutes` are cast to int and floored at `1`; text and email fields use the corresponding WordPress sanitization functions.

After saving, the controller fires the `cart_rebound_settings_updated` action with the full sanitised settings so the scheduler can reconcile its cron schedule:

```php
do_action( 'cart_rebound_settings_updated', $all );
```

The response is the full, sanitised settings array (same shape as `GET /settings`).

---

### Error status summary

| Status | Code                             | When                                                                           |
| ------ | -------------------------------- | ------------------------------------------------------------------------------ |
| 401    | `cart_rebound_invalid_nonce`     | Missing/invalid `X-WP-Nonce` header                                            |
| 403    | `cart_rebound_forbidden`         | Capability check failed                                                        |
| 403    | `cart_rebound_no_authorization`  | Route has no middleware (config error)                                         |
| 404    | —                                | `GET /carts/{id}` for a non-existent cart (`{ "message": "Cart not found." }`) |
| 422    | `cart_rebound_validation_failed` | FormRequest validation failed; `data.errors` holds per-field messages          |
| 500    | `cart_rebound_server_error`      | Uncaught exception in a handler (message logged only when `WP_DEBUG` is on)    |

---

## Scheduling & Cron

Cart Rebound runs all of its background work through a small scheduler abstraction (`CartRebound\Cron\Scheduler`) that prefers WooCommerce's bundled **Action Scheduler** and falls back to native **wp-cron** when Action Scheduler is unavailable. Action Scheduler is the production-grade choice because it self-heals on low-traffic sites where wp-cron stalls; the wp-cron fallback only exists to keep the plugin functional if WooCommerce (and therefore Action Scheduler) is somehow absent.

`Scheduler::uses_action_scheduler()` decides the path at runtime by checking that `as_schedule_recurring_action()`, `as_next_scheduled_action()`, `as_schedule_single_action()`, and `as_unschedule_all_actions()` all exist. If they do, jobs are registered under the Action Scheduler group **`cart-rebound`** (the `Scheduler::GROUP` constant).

### The recurring jobs

`SchedulerServiceProvider::sync_schedule()` reconciles the schedule against the current settings. It runs on `init`, on the `cart_rebound_activated` action, and is idempotent (safe to run repeatedly). It registers two recurring jobs:

| Job              | Hook                            | Interval                                                   |
| ---------------- | ------------------------------- | ---------------------------------------------------------- |
| Abandonment scan | `cart_rebound_scan_abandoned`   | `scan_interval` minutes (default 5), floored at 60 seconds |
| Daily cleanup    | `cart_rebound_cleanup_sessions` | `DAY_IN_SECONDS` (once a day)                              |

The scan interval is computed as `max( 60, (int) scan_interval * MINUTE_IN_SECONDS )`, so even if `scan_interval` is set to a sub-minute value the job never runs more often than once per minute.

In the wp-cron fallback path, the scan uses a custom recurrence named **`cart_rebound_scan_interval`**, registered via the `cron_schedules` filter (`register_fallback_schedule()`) with a fixed 5-minute interval and the display label "Every 5 minutes (Cart Rebound)". Note that this fallback recurrence is a fixed 5 minutes regardless of the `scan_interval` setting; the configurable interval only applies on the Action Scheduler path. The daily cleanup falls back to WordPress's built-in `daily` recurrence.

### The one-off recovery-email job

The scan job does not send emails itself. When `AbandonmentDetector` flips a cart to `abandoned`, and only if `recovery_email_enabled` is on and the cart has a captured email, it schedules a **single** (one-off) job via `Scheduler::schedule_single()`:

- Hook: `cart_rebound_send_recovery_email` (`RecoveryMailer::HOOK`)
- Run time: `time() + ( email_delay_minutes * MINUTE_IN_SECONDS )`, where `email_delay_minutes` (default 60) is floored at 1 minute
- Argument: the cart session row `id`

So each abandoned cart that qualifies gets its own scheduled email, fired once after the configured delay.

### Changing `scan_interval` reschedules

When settings are saved, the `cart_rebound_settings_updated` action triggers `SchedulerServiceProvider::reschedule()`, which **clears** the existing `cart_rebound_scan_abandoned` job and then calls `sync_schedule()` again. This guarantees a new scan interval replaces the already-registered recurring job rather than stacking on top of it. The daily cleanup is re-ensured idempotently in the same pass.

Note that the **abandonment threshold** (`abandonment_threshold`, default 30 minutes) is _not_ part of the cron cadence — it lives in the scan's SQL `WHERE` clause (`last_activity < cutoff`). Changing the threshold takes effect on the next scan automatically, with no rescheduling required.

On deactivation, the lifecycle handler removes the scan and cleanup jobs plus all pending one-off recovery-email actions.

### What the cleanup deletes (and keeps)

`Janitor::run()` (the `cart_rebound_cleanup_sessions` handler) applies two retention windows, each floored at one day:

1. **Unrecovered retention (`cleanup_days`, default 30)** — unconverted abandoned/lost rows older than the cutoff and stale active rows with no linked order.
2. **Converted retention (`converted_cleanup_days`, default 365)** — recovered rows by `recovered_at` and completed rows by `completed_at`.

Deletes run in bounded batches of 100. Each cart deletion also removes activity logs carrying that cart ID, and the job returns the total number of cart-session rows removed.

Abandoned/lost records with a linked order are not part of the unrecovered purge. Administrators can delete individual cart records from the dashboard or process a WordPress personal-data erasure request when earlier removal is required.

### Inspecting and triggering jobs

When WooCommerce is active, all of these jobs appear under **WooCommerce → Status → Scheduled Actions** (Tools → Scheduled Actions), filtered to the **`cart-rebound`** group. There you can see the next run time, run history, and any failures, and you can run a pending action immediately. On the wp-cron fallback, the hooks are standard WordPress cron events and can be inspected with tools such as WP-CLI's `wp cron event list`.

### Relevant source files

---

## Troubleshooting & FAQ

The answers below are derived from the plugin's actual capture, detection, and mailing code. Where a setting is named, it is the real key stored in the `cart_rebound_settings` option (see `src/Support/Settings.php`).

### Carts aren't being tracked at all

Tracking only runs when `CartTracker::tracking_allowed()` returns true (`src/Tracking/CartTracker.php`). Check, in order:

- **WooCommerce must be active.** The plugin dependency guard prevents Cart Rebound from booting without it. Logged-in tracking runs automatically while both plugins are active; there is no master tracking switch.
- **Guests need guest tracking enabled.** Logged-in users (`get_current_user_id() > 0`) are always tracked. For visitors who are not logged in, `guest_tracking` (default `false`) must be turned on — otherwise `tracking_allowed()` returns false for them.
- **A stable session key is required.** If `SessionManager::resolve_session_key()` returns an empty string (no WooCommerce session/cookie yet), `track()` and `capture_identity()` both bail.
- **The cart row is refreshed on cart events.** Tracking is wired to `woocommerce_add_to_cart`, `woocommerce_cart_updated`, `woocommerce_cart_item_removed`, and `woocommerce_cart_emptied` (`src/Providers/CaptureServiceProvider.php`). If a theme/page builder manipulates the cart without firing these standard hooks, snapshots may not refresh.

Note that a cart row can exist with zero items (e.g. after `woocommerce_cart_emptied`). That is expected — but an empty cart will never be flagged as abandoned (see below).

### A guest's email isn't being captured

Guest identity is back-filled onto the existing cart row, so the cart must already be tracked first (a row must exist and not be in a terminal status). Capture flows differ by checkout type (`src/Providers/CaptureServiceProvider.php`):

- **Classic (shortcode) checkout** captures via two server-side hooks: `woocommerce_checkout_update_order_review` (as the shopper edits the form) and `woocommerce_after_checkout_validation` (a no-JS safety net at submit). Both read WooCommerce `billing_*` fields.
- **Block / Store API checkout** captures via `woocommerce_store_api_checkout_update_order_from_request`, reading `get_billing_email()` and friends off the in-progress order.
- **The front-end beacon** (`assets/js/checkout-capture.js`) is only enqueued when you're on the checkout page (not the order-received page) and **guest tracking is enabled**. It POSTs to the REST route `cart-rebound/v1/capture` with a `wp_rest` nonce (`wp_create_nonce( 'wp_rest' )`). If the nonce is stale (for example, on a cached checkout page), the request is rejected and no email is stored.
- **Validation rules.** `capture_identity()` only keeps an email that passes `is_email()`; an invalid/blank email is dropped silently. The REST payload is validated by `CaptureEmailRequest` (`email|max:100`, name fields `max:100`, `phone max:40`).
- **Terminal carts are skipped.** If the cart row is already `recovered`, `completed`, or `lost`, identity back-fill is ignored.

If guest tracking is off, none of the guest paths persist anything, even though logged-in users still get their email from their account profile automatically.

### Abandonment never fires

The detector (`src/Cron/AbandonmentDetector.php`) flips `active` carts to `abandoned`. A cart is only selected when **every** condition holds:

- `status = active`, and `abandonment_notified = 0`.
- **`email` is not empty** — a cart with no captured email is _never_ marked abandoned. This is the most common surprise: anonymous carts where the shopper never typed an email simply expire/are purged rather than becoming recovery candidates.
- `items_count > 0` — empty carts don't qualify.
- `last_activity` is older than the cutoff, where cutoff = now minus `abandonment_threshold` minutes (default `30`, floored at 1).

Timing notes:

- **Threshold changes apply on the next scan** — the threshold lives in the query's WHERE clause, not the schedule, so there's nothing to reschedule.
- **Scan cadence** is `scan_interval` minutes (default `5`, floored at 60 seconds). The job runs on Action Scheduler when WooCommerce is present (it self-heals on low-traffic sites), otherwise it falls back to native wp-cron with a registered "Every 5 minutes" schedule (`SchedulerServiceProvider`).
- **Low-traffic sites using wp-cron may stall.** wp-cron only fires on page visits; with no traffic, the scan won't run. Action Scheduler (bundled with WooCommerce) mitigates this, so keeping WooCommerce active is the reliable path. As a last resort, trigger the scan with a real system cron hitting `wp-cron.php`.

### The recovery email isn't sending

Recovery email is an opt-in single send. Walk these checks:

- **`recovery_email_enabled` is `false` by default.** Turn it on, or nothing is ever scheduled. It's checked both when scheduling (in `AbandonmentDetector::mark_abandoned()`) and again at send time (`RecoveryMailer::send()`).
- **Scheduling requires an email on the row.** The follow-up is only queued if the abandoned row has a non-empty `email`. The send is scheduled `email_delay_minutes` after abandonment (default `60`, floored at 1).
- **The cart must still be abandoned and unsent at send time.** `RecoveryMailer::send()` skips the message if the row's `status` is no longer `abandoned` (e.g. the shopper returned and the cart went back to `active`, or it converted), if `email_sent` is already `1`, or if `items_count <= 0` (cart was emptied). `email_sent` is only set to `1` after `wp_mail()` returns success, so a failed send can be retried.
- **A blank/invalid recipient is skipped.** The recipient must pass `is_email()`.
- **The From header is conditional.** A `From:` header is only added when the selected template has a valid sender email. An invalid From address doesn't fail the send — the header is omitted and WordPress's default From address is used. If mail isn't arriving at all, that is usually a site-wide deliverability/SMTP issue: the plugin uses core `wp_mail()` and restores its temporary HTML content-type filter immediately after each message.

### Recovered revenue looks wrong / too low

Revenue is `SUM(recovered_amount)` over rows with `status = recovered` (`CartRepository::get_stats()`), and attribution is deliberately conservative (`src/Recovery/OrderLinker.php`):

- **Only paid orders count.** A cart is linked to its order only when the order reaches `processing` or `completed`. Pending, failed, on-hold, or cancelled orders do not attribute revenue, even though the cart is stamped with the order id at creation.
- **`recovered` vs `completed` are different buckets.** A cart is marked `recovered` only if it converted via an email recovery link _or_ was already in `abandoned` status when the order was paid. A cart that converted normally (never abandoned) is marked `completed` — and **`completed` is not included in recovered revenue.** If your "recovered" total seems low, those conversions are likely counted as `completed`.
- **`recovered_amount` is the full order total** (`$order->get_total()`), including shipping/tax/fees — not just the original cart subtotal — so individual amounts can look larger than the cart snapshot.
- **Recovery rate uses lifetime counters.** The `recovery_rate` is computed from purge-immune option counters (lifetime abandoned vs recovered), not the live status counts, because the Janitor deletes old unrecovered carts. Live row counts and the rate can therefore diverge.
- **Manual "Mark recovered" exists.** Admins can attribute a cart to an order via `CartsController::mark_recovered()` → `CartRepository::mark_recovered()`. It requires a valid WooCommerce order, copies that order's total/currency into `recovered_amount`, and is idempotent — it refuses to re-attribute a cart that already has an `order_id`.

### Does it support block checkout and HPOS?

Yes to both.

- **Block / Store API checkout** is captured via the `woocommerce_store_api_checkout_update_order_from_request` hook, alongside the classic-checkout hooks (`src/Providers/CaptureServiceProvider.php`).
- **HPOS (High-Performance Order Storage)** is supported because order linking uses WooCommerce's CRUD order API throughout — `wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()`, and `$order->save()` — rather than direct post-meta access. The cart id is stored on the order as `_cart_rebound_session_id`, and link-sourced recoveries are flagged with `_cart_rebound_recovered`.

---

## Privacy & Personal Data

Cart Rebound stores tracked-cart and activity data locally in the site's WordPress database. It does not send telemetry or cart data to the plugin author or to a Cart Rebound service.

Depending on checkout progress, a cart record can include a stable session identifier, WordPress user ID, email address, first and last name, phone number, cart contents, quantities, variations, prices, coupons, totals, currency, status, linked order and recovered amount, and lifecycle timestamps. The activity log stores its timestamp, level, event, message, and related cart ID.

Guest tracking is disabled by default. When a site owner enables it, the first-party `cart_rebound_ref` cookie associates the visitor with a cart row. It is HTTP-only, uses SameSite=Lax, is marked secure on HTTPS sites, and expires after approximately 30 days.

Automatic recovery emails are also disabled by default. When enabled, messages use `wp_mail()` and therefore the site's configured mail transport or email provider. Site owners are responsible for an appropriate legal basis, required consent, and their own privacy-policy disclosure.

The daily cleanup uses two configurable retention windows:

- `cleanup_days` (30 days by default) removes stale active and unconverted abandoned/lost carts.
- `converted_cleanup_days` (365 days by default) removes recovered and completed carts.

Associated activity logs are removed when a cart is purged or manually deleted. The standalone activity log is additionally capped at its 5,000 most recent rows.

Under **Tools → Export Personal Data**, Cart Rebound exports matching cart sessions and linked activity-log records in batches of 20. For a registered shopper, matching covers both the current sanitized email address and WordPress user ID so carts remain discoverable after an account email change. Security-sensitive recovery tokens and stored checkout URLs are not included in the export. Under **Tools → Erase Personal Data**, the same identity matching removes cart records and their associated log entries, also in batches of 20.

---

## Uninstall & Data

Cart Rebound stores runtime data in two custom tables and several WordPress options. Deactivation preserves that data, while a normal WordPress uninstall removes it.

### What lives where

| Data                                   | Type           | Holds                                                                                   |
| -------------------------------------- | -------------- | --------------------------------------------------------------------------------------- |
| `{$wpdb->prefix}cart_rebound_sessions` | DB table       | One row per tracked cart (snapshot, status, recovery token, order/recovery attribution) |
| `{$wpdb->prefix}cart_rebound_logs`     | DB table       | Activity events associated with tracked carts                                           |
| `cart_rebound_migrations`              | Option (array) | Basenames of migrations already applied, so re-activating only runs new ones            |
| `cart_rebound_settings`                | Option (array) | All plugin settings (see the Settings section)                                          |
| `cart_rebound_email_templates`         | Option (array) | Recovery email templates and their default designation                                  |
| `cart_rebound_db_version`              | Option (int)   | Activity-log schema version                                                             |
| `cart_rebound_lifetime_abandoned`      | Option (int)   | Purge-immune lifetime count of abandoned carts                                          |
| `cart_rebound_lifetime_recovered`      | Option (int)   | Purge-immune lifetime count of recovered carts                                          |

The table names are built in `src/Database/Migration.php` (`$wpdb->prefix . 'cart_rebound_'`) plus the `sessions` or `logs` suffix supplied by each migration. With the default prefix they resolve to `wp_cart_rebound_sessions` and `wp_cart_rebound_logs`.

The two `lifetime_*` counters are defined in `src/Events/EventDispatcher.php` and incremented as carts are abandoned/recovered. They are intentionally "purge-immune": the daily Janitor deletes old session rows, but these running totals survive so the recovery-rate stat in the dashboard (computed in `src/Data/CartRepository.php`) stays accurate over time.

### On activation

`CartRebound\Core\Plugin::activate()` resolves the `Migrator` and calls `run()`:

- `Migrator::run()` (`src/Database/Migrator.php`) discovers each migration file in `src/Database/Migrations/`, skips any already listed in the `cart_rebound_migrations` option, and calls `up()` on the rest. The migrations create the indexed sessions and activity-log tables through `dbDelta()`.
- Each newly applied migration's basename is appended to `cart_rebound_migrations` (persisted after every step, so a mid-batch failure can't orphan succeeded migrations).
- It then fires `do_action( 'cart_rebound_activated', $app )`. The `SchedulerServiceProvider` listens on this hook and calls `sync_schedule()` to register the recurring abandonment-scan and daily Janitor jobs.

Activation is idempotent — reactivating an existing install does not wipe either table; it only runs migrations not yet recorded.

### On deactivation

`Plugin::deactivate()` does **not** touch stored data. It clears the recurring abandonment scan, daily cleanup, and all pending one-off recovery-email actions before firing `cart_rebound_deactivated`.

After deactivation, both custom tables and all plugin options remain intact. Reactivating resumes with the stored configuration and records.

### On uninstall

Deleting the plugin through the WordPress Plugins screen runs `uninstall.php`, which calls `Plugin::uninstall()`. It:

1. Clears the abandonment scan, cleanup job, and pending recovery-email actions.
2. Rolls back all recorded migrations, dropping both custom tables and deleting `cart_rebound_migrations`.
3. Deletes `cart_rebound_settings`, `cart_rebound_email_templates`, `cart_rebound_db_version`, and the two lifetime-counter options.

After a normal WordPress uninstall, Cart Rebound leaves no plugin tables, settings, templates, counters, or scheduled actions behind. Removing the plugin files directly from the filesystem bypasses WordPress's uninstall hook and therefore cannot perform this cleanup.
