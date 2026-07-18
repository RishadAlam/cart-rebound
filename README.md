# Cart Rebound — WooCommerce Abandoned Cart Recovery

> Recover abandoned WooCommerce carts with secure links, optional emails, configurable tracking, and accurate revenue attribution.

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759b.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-required-96588a.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Cart Rebound** is a free, open-source **WooCommerce abandoned cart recovery** plugin for WordPress. It records logged-in carts and, when enabled, guest carts; flips inactive carts to _abandoned_ after a configurable idle window; lets shoppers restore their cart through an unguessable **tokenized recovery link**; and attributes **recovered revenue** to the real order. Guest tracking and automatic recovery emails are disabled by default. A clean `do_action` event surface and REST API let automation tools react to abandonment and recovery without coupling to plugin internals.

## Documentation

📖 **[Full usage & developer guide → `docs/USAGE.md`](docs/USAGE.md)** — step-by-step installation, every setting, how tracking & recovery work end to end, the admin dashboard, and the events + REST API reference.

## Features

- **Reliable cart capture** — logged-in carts plus opt-in guest carts, including the email a guest types at checkout _before_ submitting. Works on both **classic checkout** (AJAX beacon + server-side hooks) and **block / Store API checkout**.
- **Configurable abandonment detection** — driven by **Action Scheduler** (WooCommerce's bundled, self-healing scheduler) with a wp-cron fallback. The idle threshold lives in the query, so changing it takes effect on the next scan.
- **Tokenized recovery links** — rebuild the cart (items, variations, and coupons) and send the shopper straight to checkout. No raw session key in the URL.
- **Accurate revenue attribution** — orders are linked to carts by **explicit order meta, never fuzzy total matching**, so coupons, shipping, and tax never break the link. Carts resolve to _recovered_ or _completed_ only on real payment, with separate timestamps and a dedicated recovered-amount field.
- **Optional built-in recovery email** — disabled by default and scheduled a configurable delay after abandonment, with `{first_name}`, `{products}`, `{recovery_url}`, and `{coupon_code}` tokens.
- **Developer event &amp; REST API** — `cart_rebound_abandoned` / `cart_rebound_recovered` actions, and a read API for carts, stats, and recovered revenue.
- **Admin dashboard** — active / abandoned / recovered counts, **recovered revenue**, recovery rate, and a filterable list of cart sessions with row actions.
- **HPOS-compatible** — built for WooCommerce High-Performance Order Storage.

## Requirements

- WordPress 6.2+
- WooCommerce (active)
- PHP 7.4+

## Installation

1. Install and activate **WooCommerce**.
2. Download the latest `cart-rebound.zip` from [Releases](https://github.com/RishadAlam/cart-rebound/releases) (or build it — see below).
3. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, install, activate.
4. Visit **Cart Rebound** in the admin sidebar to configure tracking, retention, abandonment detection, and optional recovery emails.

## How it works

```
add_to_cart / cart_updated / checkout-email  ──▶  active
                                                    │  idle > threshold (scan)
                                                    ▼
   completed ◀── order paid (was active)        abandoned ──▶ lost ──▶ purged
        ▲                                            │            (cleanup window, no order)
        └────────── order paid & linked to ──────────┘
                    this cart  ⇒  recovered (revenue attributed)
```

Status only transitions to _completed_ / _recovered_ when the order is actually **paid**, so a pending or never-paid order never prematurely removes a cart from recovery.

## Developer API

React to recovery events from your own plugin or an automation tool:

```php
add_action( 'cart_rebound_abandoned', function ( array $payload ) {
    // $payload: cart_id, customer_email, first_name, cart_total, currency,
    // products[], recovery_url, last_activity, …
} );

add_action( 'cart_rebound_recovered', function ( array $payload ) {
    // adds: order_id, recovered_amount, recovered_at, recovery_method
} );
```

REST (namespace `cart-rebound/v1`, capability `manage_woocommerce`, nonce-protected): `GET carts`, `GET carts/{id}`, `GET stats`, `GET/POST settings`, `POST carts/{id}/mark-recovered`, `DELETE carts/{id}`.

## Development

Built on a Laravel-style, container-driven OOP framework (service providers, REST routing with middleware, form requests, a query builder, dbDelta migrations) with a React + TypeScript + Vite admin.

The complete, human-readable source is maintained in this public repository. Production archives contain compiled assets; their uncompressed TypeScript, React, and CSS sources are under [`resources/`](resources/).

```bash
composer install && pnpm install
composer qa        # phpcs (WP-Extra), PHPStan L8, PHP 7.4 compat, Rector, PHPUnit
pnpm qa            # tsc strict, prettier, eslint, stylelint
pnpm dev           # live Vite source assets + HMR on the plugin admin pages
pnpm build         # compile the admin app
bash scripts/build-zip.sh   # build assets/POT → build/cart-rebound.zip
```

Local HMR is deliberately opt-in. Set `WP_DEBUG` to `true`, set
`WP_ENVIRONMENT_TYPE` to `local` or `development`, and define
`CART_REBOUND_ENABLE_HMR` as `true` in `wp-config.php`. While `pnpm dev` is
running, Vite writes `public/hot` and WordPress loads `@vite/client` plus
`resources/js/admin/main.tsx` strictly from `http://localhost:5173`. Stopping
Vite removes the marker and the plugin automatically falls back to the hashed
assets in `public/build`. Production environments ignore the marker.

The archive command also requires WP-CLI with `wp i18n make-pot` available. `pnpm production-zip` runs the full PHP and JavaScript quality gates and writes the submission archive to `build/cart-rebound.zip`.

## Privacy

Guest tracking and automatic recovery emails are disabled by default. The plugin stores tracked cart and checkout identity data locally in the WordPress database and uses a first-party, HTTP-only `cart_rebound_ref` cookie for approximately 30 days. It does not send telemetry or tracked-cart data to the plugin author.

The default retention windows are 30 days for stale active/unrecovered carts and 365 days for recovered/completed carts; both are configurable. Cart Rebound registers WordPress personal-data exporters and erasers for matching cart and activity-log data. Recovery emails use the site's configured WordPress mail transport. See [`readme.txt`](readme.txt) for the full disclosure.

## License

[GPL-2.0-or-later](LICENSE). Bundled JavaScript library notices are in [`THIRD-PARTY-LICENSES.txt`](THIRD-PARTY-LICENSES.txt). Contributions welcome.
