=== Cart Rebound ===
Contributors: rishadbitcode
Tags: woocommerce, abandoned cart, cart recovery, ecommerce, cart abandonment
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts with secure links, optional emails, opt-in guest tracking, and accurate revenue attribution.

== Description ==

Cart Rebound tracks in-progress WooCommerce carts, detects abandonment after a configurable idle window, and helps recover lost sales through secure recovery links and optional emails.

Logged-in carts are tracked while the plugin is active. Guest cart tracking is available for classic checkout and block / Store API checkout, and is disabled by default. When a shopper goes idle past your threshold, the cart is flagged as abandoned. If automatic recovery emails are enabled, a single email is scheduled after the configured delay. Automatic emails are disabled by default.

The customer can use a secure recovery link that rebuilds the cart with its items, variations, and coupons, then returns them to checkout.

Revenue attribution is exact: orders are linked to carts by explicit order meta, not fuzzy total matching, so coupons, shipping, and tax never break the link. You see the true recovered revenue and recovery rate on your dashboard.

= Why Cart Rebound is different =

Cart Rebound focuses on verifiable attribution, conservative defaults, and native WooCommerce operation without an external recovery service.

* **Explicit attribution** — dedicated order metadata separates carts recovered after abandonment from ordinary completed carts.
* **Modern checkout coverage** — guest capture and order linking support classic checkout and Store API checkout blocks.
* **Conservative, local defaults** — guest tracking and automatic emails are disabled initially; data stays in WordPress with no telemetry or Cart Rebound account.
* **Developer visibility** — Action Scheduler support, lifecycle events, an activity log, privacy tools, and a capability-protected REST API are included.

**Key features:**

* **Guest and logged-in cart capture** — supports both classic checkout and block / Store API checkout. Guest tracking is opt-in and can capture the email entered at checkout before the order is submitted.
* **Configurable abandonment detection** — driven by Action Scheduler with a wp-cron fallback. The idle threshold lives in the query, so changing it takes effect on the next scan without rescheduling.
* **Tokenized recovery links** — rebuild the cart with items, variations, and coupons and send the shopper to checkout. No raw session key is exposed in the URL.
* **Accurate revenue attribution** — orders are linked to carts by explicit order meta, never fuzzy total matching. Carts resolve to *recovered* or *completed* with separate timestamps and a dedicated recovered-amount field.
* **Recovery email template editor** — create and manage multiple templates, mark a default for automatic sends, format with a full rich-text toolbar, insert images from the Media Library, preview against sample data, and send on demand per cart. Merge tags: `{first_name}`, `{products}`, `{recovery_url}`, and `{coupon_code}` (pick a WooCommerce coupon per template).
* **Activity log** — a filterable log of abandonments, recoveries, and sent emails, filterable by level, event, and cart.
* **Developer events & REST API** — fires `do_action( 'cart_rebound_abandoned', $payload )` and `do_action( 'cart_rebound_recovered', $payload )` with a flat payload, plus a read API for carts, stats, and recovered revenue.
* **Admin dashboard** — active, abandoned, and recovered counts, recovered revenue, recovery rate, and a filterable list of cart sessions with row actions.
* **HPOS-compatible** — built for WooCommerce High-Performance Order Storage.

**Requirements:**

* WooCommerce (active)
* WordPress 6.2 or later
* PHP 7.4 or later

== Installation ==

1. Install and activate WooCommerce.
2. Upload the Cart Rebound plugin to your `wp-content/plugins` directory, or install the zip via **Plugins → Add New → Upload Plugin**.
3. Activate Cart Rebound through the **Plugins** menu in WordPress.
4. Visit **Cart Rebound** in the admin sidebar to configure guest tracking, retention, abandonment detection, and optional recovery emails.

== Frequently Asked Questions ==

= Does Cart Rebound work with guest checkout? =

Yes. Enable **Track guest carts** in the plugin settings to capture guest carts on classic and block / Store API checkout. Guest tracking is disabled by default.

= Does it support the WooCommerce block checkout? =

Yes. Guest email is captured server-side through the Store API, and order linking is stamped on both classic and block order-processing hooks.

= How is recovered revenue calculated? =

When an order is placed from a previously abandoned cart (via a recovery link or a matched session), the cart is marked *recovered* and the order total is stored as the recovered amount. Carts purchased without ever being abandoned are marked *completed* and do not contribute to recovered revenue.

= Can other plugins or automation tools react to abandonment and recovery? =

Yes. Cart Rebound fires `cart_rebound_abandoned` and `cart_rebound_recovered` actions with a flat payload containing cart and customer details. A read REST API is also available for carts, stats, and recovered revenue.

= Does Cart Rebound work with WooCommerce High-Performance Order Storage (HPOS)? =

Yes. Cart Rebound declares HPOS compatibility and does not rely on the legacy post-based order storage.

= Are recovery links secure? =

Yes. Recovery links use a 32-character token generated by `wp_generate_password()`, providing over 160 bits of entropy. No raw session key is exposed in the URL.

= Can I send recovery emails manually? =

Yes. You can send a recovery email on demand for any cart from the admin dashboard, choosing which template to use. Automatic sending is disabled by default; when enabled, it respects the configured delay after abandonment.

= What merge tags are available in email templates? =

`{first_name}`, `{products}`, `{recovery_url}`, and `{coupon_code}`. You can attach a WooCommerce coupon to each template and the code is substituted into the email automatically.

== Privacy ==

Cart Rebound stores cart-recovery data in the site's WordPress database. Depending on the shopper and checkout progress, this can include a session identifier, WordPress user ID, email address, first and last name, phone number, cart items, quantities, variations, prices, coupons, totals, currency, cart and order status, order and recovered-revenue references, and lifecycle timestamps. Activity logs store operational events associated with a cart.

Guest cart tracking is disabled by default. For tracked carts, Cart Rebound uses a first-party `cart_rebound_ref` cookie to associate a visitor with a cart across requests. The cookie contains an unguessable, plugin-specific browser identifier, is HTTP-only, uses SameSite=Lax, and expires after approximately 30 days.

Automatic recovery emails are disabled by default. When enabled, messages are sent through the site's configured WordPress mail system. Cart Rebound does not transmit tracked-cart data, telemetry, or usage information to the plugin author or to a Cart Rebound service. A site's mail, hosting, or integration providers may process data according to that site's own configuration.

By default, stale active and unrecovered carts are removed after 30 days. Recovered and completed carts are removed after 365 days. Site administrators can configure both retention periods. Activity logs associated with a removed cart are also removed.

Cart Rebound integrates with the WordPress personal-data Export and Erase tools. Requests are matched by email address and, for registered shoppers, WordPress user ID. Exports include the matching cart and activity data; erasure removes matching cart records and their associated activity logs.

Site owners are responsible for choosing an appropriate legal basis, obtaining any required consent, configuring retention, and describing cart tracking and recovery emails in their own privacy policy.

== Development ==

The human-readable source code is maintained at [github.com/RishadAlam/cart-rebound](https://github.com/RishadAlam/cart-rebound). The repository contains the uncompressed PHP, TypeScript, React, and CSS source as well as all build configuration.

Building requires PHP 7.4 or later, Composer, Node.js 20 or later, pnpm, and WP-CLI with the i18n command. From the repository root, run `composer install`, `pnpm install --frozen-lockfile`, and `pnpm build`. Run `pnpm production-zip` to execute the quality checks, generate the translation template, and create `build/cart-rebound.zip`.

Bundled third-party JavaScript libraries are GPL-compatible and distributed under the MIT License. Copyright and license notices are included in `THIRD-PARTY-LICENSES.txt`.

== Changelog ==

= 0.1.0 =
* Initial release: cart tracking (logged-in + guest, classic + block checkout), configurable abandonment detection via Action Scheduler, tokenized recovery links, explicit order-to-cart linking with recovered/completed attribution, recovery emails with a rich-text multi-template editor (Media Library images, preview, per-cart send, `{coupon_code}` and WooCommerce coupon selection), an activity log filterable by level/event/cart, event + REST API, and an admin dashboard.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
