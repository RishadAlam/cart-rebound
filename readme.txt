=== Cart Rebound ===
Contributors: rishadbitcode
Tags: woocommerce, abandoned cart, cart recovery, abandoned cart recovery, cart abandonment
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track WooCommerce carts, detect abandonment, recover lost sales with tokenized links and automated emails, and attribute recovered revenue.

== Description ==

Cart Rebound records every in-progress WooCommerce cart — logged-in and guest — flips it to *abandoned* after a
configurable idle window, lets customers restore their cart through a tokenized recovery link, and attributes
recovered revenue to the real order. It exposes a clean event and REST surface so automation tools can react to
abandonment and recovery without coupling to the plugin internals.

Requires an active WooCommerce installation.

**Key features:**

* **Reliable capture** of logged-in and guest carts, including the email a guest types at checkout before
  submitting — supported on both classic checkout (AJAX + server-side hooks) and block / Store API checkout.
* **Configurable abandonment detection** driven by Action Scheduler (with a wp-cron fallback); the idle threshold
  lives in the query, so changing it takes effect on the next scan without rescheduling.
* **Tokenized recovery links** that rebuild the cart (items, variations, and coupons) and send the shopper to
  checkout — no raw session key in the URL.
* **Accurate revenue attribution**: orders are linked to carts by explicit order meta, never by fuzzy total
  matching, so coupons, shipping, and tax never break the link. Carts resolve to *recovered* or *completed*
  with separate timestamps and a dedicated recovered-amount field.
* **Recovery emails with a rich-text template editor** — create and manage multiple templates, mark the default
  used for automatic sends, format with a full toolbar, insert images from the Media Library, preview against
  sample data, and send on demand per cart (choosing which template). Merge tags: `{first_name}`, `{products}`,
  `{recovery_url}`, and `{coupon_code}` (pick a WooCommerce coupon per template). A single follow-up email is
  scheduled a configurable delay after abandonment.
* **Activity log** of abandonments, recoveries, and sent emails — filterable by level, event, and cart.
* **Event & REST API** for integrations: `do_action( 'cart_rebound_abandoned', $payload )` and
  `do_action( 'cart_rebound_recovered', $payload )`, and a read API for carts, stats, and recovered revenue.
* **Admin dashboard**: active / abandoned / recovered counts, recovered revenue, recovery rate, and a filterable
  list of cart sessions with row actions.

**Requirements:**

* WooCommerce (active)
* WordPress 6.2 or later
* PHP 7.4 or later

== Installation ==

1. Install and activate WooCommerce.
2. Upload the Cart Rebound plugin to your `wp-content/plugins/` directory, or install the zip via **Plugins → Add New → Upload Plugin**.
3. Activate Cart Rebound through the **Plugins** menu in WordPress.
4. Visit **Cart Rebound** in the admin sidebar to configure the abandonment threshold, cleanup window, and recovery email.

== Frequently Asked Questions ==

= Does this work with guest checkout? =

Yes. Cart Rebound captures the email a guest enters at checkout (classic and block / Store API) before the order
is submitted, so guest carts are recoverable.

= Does it support the WooCommerce block checkout? =

Yes. Guest email is captured server-side through the Store API, and order linking is stamped on both classic and
block order-processing hooks.

= How is recovered revenue calculated? =

When an order is placed from a previously abandoned cart (via a recovery link or a matched session), the cart is
marked *recovered* and the order total is stored as the recovered amount. Carts purchased without ever being
abandoned are marked *completed* and contribute no recovered revenue.

= Can other plugins react to abandonment and recovery? =

Yes. Cart Rebound fires `cart_rebound_abandoned` and `cart_rebound_recovered` actions with a flat payload.

== Changelog ==

= 0.1.0 =
* Initial release: cart tracking (logged-in + guest, classic + block checkout), configurable abandonment
  detection via Action Scheduler, tokenized recovery links, explicit order-to-cart linking with recovered/completed
  attribution, recovery emails with a rich-text multi-template editor (Media Library images, preview, per-cart
  send, `{coupon_code}` and WooCommerce coupon selection), an activity log filterable by level/event/cart,
  event + REST API, and an admin dashboard.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
