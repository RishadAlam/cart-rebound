=== Cart Rebound – Cart Abandonment Recovery for WooCommerce ===
Contributors: rishadbitcode
Tags: woocommerce, abandoned cart, cart abandonment, cart recovery, recovery emails
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free, self-hosted WooCommerce cart abandonment recovery with automated emails, secure restore links, coupons, and revenue reports.

== Description ==

Cart Rebound is a **free, self-hosted WooCommerce cart abandonment recovery plugin**. Track abandoned carts, send automated recovery emails, restore saved carts in one click, and measure recovered revenue without an external account, subscription, or cart recovery service.

Shopping cart abandonment happens when a customer adds products but leaves before completing checkout. Cart Rebound records eligible carts, detects when they become inactive, and gives the shopper a direct path back to purchase. Store owners get clear abandoned cart reports, customizable reminder emails, secure recovery URLs, product insights, and accurate cart-to-order attribution inside WordPress.

The plugin supports logged-in customers, optional guest cart tracking, classic WooCommerce checkout, the Checkout Block / Store API, variable products, applied coupons, and WooCommerce High-Performance Order Storage (HPOS). Guest tracking and automatic emails are disabled by default so each store can choose when to enable them.

= WooCommerce abandoned cart recovery at a glance =

* Track active shopping carts for registered customers.
* Optionally capture guest carts and checkout email addresses.
* Detect cart abandonment after a configurable period of inactivity.
* Send one automatic cart reminder email after a chosen delay.
* Create reusable rich-text recovery email templates.
* Personalize email content with customer, product, recovery-link, and coupon data.
* Restore saved products, quantities, variations, and coupon codes in one click.
* Send recovery emails manually when a personal follow-up is appropriate.
* Measure abandoned carts, recovered orders, recovery rate, and recovered revenue.
* Review product-level abandonment and recovery data.
* Receive an optional admin email when a tracked cart becomes a paid order.
* Keep tracking data in the site's own WordPress database.
* Use WordPress privacy exporters, erasers, retention controls, and unsubscribe links.
* Connect custom workflows through WordPress actions and protected REST endpoints.

= How Cart Rebound recovers abandoned carts =

**1. Capture the WooCommerce cart**

Cart Rebound takes a snapshot as the shopper adds, updates, removes, or empties cart items. Logged-in customers are tracked while the plugin is active. When guest tracking is enabled, the plugin can also associate a logged-out visitor with a cart and capture the email address, name, and phone number they provide during checkout.

The saved cart snapshot can include products, quantities, variation IDs and attributes, line prices, cart total, currency, applied coupon codes, item count, customer details, and lifecycle timestamps.

**2. Identify an abandoned checkout**

Choose how many idle minutes must pass before a cart is considered abandoned and how often eligible carts should be scanned. A cart must still contain items and have a valid captured email before it enters the abandoned state. Changing the threshold affects the next scan.

Background detection uses WooCommerce Action Scheduler when available and falls back to WP-Cron. This keeps cart abandonment processing out of the shopper's page request.

**3. Schedule a cart recovery email**

When an eligible cart becomes abandoned, Cart Rebound can schedule one automatic follow-up email after the configured delay. The mailer checks the cart again at send time. If the cart is no longer abandoned, has already converted, no longer contains items, has no valid email, or was already emailed, the message is skipped.

Automatic recovery email sending is optional and disabled by default. Store managers can also choose a saved template and send an on-demand reminder from the cart screen.

**4. Restore the abandoned shopping cart**

Every tracked cart has a random recovery token. The email includes a secure link that uses this token instead of exposing the WooCommerce session key. After validation, Cart Rebound rebuilds the available cart lines with their saved quantities, variation data, and coupon codes, then redirects the customer to checkout.

**5. Attribute the recovered order**

When checkout creates an order, Cart Rebound adds an explicit reference to the originating cart. After the order reaches a configured paid status, an abandoned cart is marked recovered and its order total is recorded as recovered revenue. A cart that completes checkout before being abandoned is marked completed and kept out of recovery totals.

= Automated abandoned cart emails =

Cart Rebound provides focused email automation for stores that want a clear, manageable recovery workflow. Once enabled, one email is scheduled for each eligible abandoned cart. Store owners choose the send delay and the default template used for automatic reminders.

Before sending, the plugin verifies that the cart is still eligible. This protects customers from receiving an unnecessary reminder after they have already completed the purchase. Successful sends are recorded so the same automatic message is not sent twice.

Recovery messages use the site's normal WordPress mail system. If the store uses an SMTP or transactional email plugin that integrates with `wp_mail()`, Cart Rebound uses that existing delivery configuration. Email deliverability remains dependent on the site's hosting, DNS, mail provider, and SMTP configuration.

= Custom recovery email template builder =

Create multiple reusable templates for different products, seasons, store voices, or manual follow-up situations. Select one template as the default for automatic cart recovery emails and choose any available template for a manual send.

The built-in editor includes:

* template name and email subject;
* rich-text formatting and links;
* headings, lists, alignment, quotes, and text colors;
* images selected from the WordPress Media Library;
* optional sender name and sender email;
* an existing WooCommerce coupon selector;
* sample-data preview;
* test email sending; and
* a clear default-template setting.

Use these merge tags to personalize the message:

* `{first_name}`, `{last_name}`, `{full_name}`, `{email}` — who the shopper is;
* `{products}`, `{products_table}`, `{product_names}`, `{items_count}`, `{cart_total}` — what they left behind;
* `{abandoned_on}` — the date the cart was left;
* `{recovery_url}`, `{checkout_url}`, `{unsubscribe_url}` — where the message can send them;
* `{coupon_code}` — the selected WooCommerce coupon code; and
* `{store_name}`, `{store_url}`, `{store_email}`, `{manager_name}`, `{current_year}` — details about your store.

Cart Rebound adds a prominent **Complete your order** button linked to the same recovery URL. Each recovery message also includes a one-click unsubscribe link.

The abandoned-products table has its own per-template layout: pick the columns (thumbnail, product, SKU, quantity, unit price, line total) and their order, choose ruled / boxed / rule-free rows, set the thumbnail size, show prices with tax, link rows to the product page, print the chosen variation under the name, close with a cart-total row, and cap how many rows list before an "and N more items" line.

= WooCommerce coupons and cart restoration =

Cart Rebound works with coupons in two ways:

1. Coupon codes already applied to the saved cart are stored and reapplied during cart restoration.
2. A store manager can select an existing WooCommerce coupon for an email template and insert the code with `{coupon_code}`.

The plugin does not create new or dynamic coupons. Coupon validity, usage restrictions, expiration, product eligibility, and discount rules continue to be managed by WooCommerce.

Recovery links also preserve product quantities and variation data. WooCommerce performs its normal validation when products are added back to the cart, so current stock status, purchasability, and catalog rules still apply.

= Secure one-click cart recovery links =

A long, random token identifies the saved cart. No raw WooCommerce session key, WordPress user ID, or predictable customer identifier is exposed in the URL.

When a shopper follows a valid recovery link, Cart Rebound:

1. finds the matching active or abandoned cart;
2. clears the current cart to avoid mixing unrelated items;
3. restores available products and saved quantities;
4. restores variation IDs and variation attributes;
5. reapplies saved coupon codes;
6. binds the restored cart to the recovery session; and
7. redirects the shopper to WooCommerce checkout.

Converted or invalid recovery links do not restore a cart. The recovery binding is cleared after it has been attached to an order so a later purchase cannot be incorrectly credited to the earlier recovery.

= Accurate recovered revenue and order attribution =

Reliable attribution is central to Cart Rebound. The plugin does not guess that an order belongs to a cart because an email address, product list, or total looks similar. It writes dedicated cart references to the WooCommerce order and resolves the cart only after the order reaches a configured paid status.

This explicit relationship means coupons, shipping, tax, payment timing, and changed order totals do not break the link between the tracked cart and the resulting order.

The distinction is simple:

* A paid order from a cart that was previously abandoned counts as **recovered**.
* A paid order from a cart that never became abandoned counts as **completed**.
* A created but unpaid order remains **pending payment** until its status changes.

Recovered revenue is the paid WooCommerce order total linked to a recovered cart. The dashboard does not treat every completed order as a recovery win.

= Cart abandonment dashboard and analytics =

The Cart Rebound dashboard turns tracked sessions into practical cart recovery reporting. Store managers can monitor:

* recoverable orders;
* recovered orders;
* lost orders;
* recoverable revenue;
* recovered revenue;
* lifetime cart recovery rate;
* abandoned versus recovered value over time;
* recent cart activity; and
* products found in abandoned and recovered carts.

Revenue trends can be viewed over recent 7-day, 30-day, or 90-day periods. The product report helps identify items that frequently appear in abandoned shopping carts and shows which products later appear in recovered carts.

These reports describe recorded recovery activity; they do not claim that the plugin caused every purchasing decision or guarantee a particular recovery percentage.

= Privacy tools and responsible defaults =

Cart and checkout information can be personal data. Cart Rebound includes controls that help store owners build an appropriate privacy process:

* guest tracking is disabled by default;
* automatic recovery email is disabled by default;
* customer messages include an unsubscribe link;
* active/unrecovered and converted-cart retention are independently configurable;
* suggested disclosure text is added to the WordPress privacy-policy guide;
* cart and activity data can be exported through WordPress personal-data tools; and
* matching cart and log data can be erased through WordPress personal-data tools.

No plugin can automatically guarantee compliance with GDPR, PECR, CCPA, CAN-SPAM, or every privacy and electronic-marketing law. Site owners are responsible for determining a lawful basis, obtaining consent where required, honoring marketing preferences, configuring retention, securing their site, and publishing an accurate privacy notice.

= WooCommerce compatibility =

Cart Rebound is designed for current WooCommerce architecture:

* classic checkout support;
* Checkout Block and Store API support;
* registered and optional guest customer tracking;
* simple and variable product restoration;
* WooCommerce coupon restoration;
* High-Performance Order Storage (HPOS) compatibility;
* Action Scheduler background processing;
* WooCommerce order-object APIs; and
* configurable paid order statuses.

WooCommerce must be installed and active. Compatibility with a specific third-party checkout, cart, caching, multilingual, or email extension can depend on how that extension modifies standard WooCommerce behavior.

= Requirements =

* WooCommerce must be installed and active.
* WordPress 6.2 or later.
* PHP 7.4 or later.

== Installation ==

1. Install and activate WooCommerce.
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the Cart Rebound zip file. You can also copy the plugin directory to `/wp-content/plugins/`.
3. Activate **Cart Rebound – Cart Abandonment Recovery for WooCommerce**.
4. Open **Cart Rebound** in the WordPress admin menu.
5. Review the onboarding screen and open **Settings**.
6. Choose the abandonment threshold, scan interval, paid order statuses, and retention periods.
7. Enable guest tracking only if it is appropriate for your store.
8. Open **Templates** to review the default recovery email, sender details, merge tags, and optional coupon.
9. Send a test email and confirm that the site's email delivery is configured correctly.
10. Enable automatic recovery email when the store is ready to send customer reminders.

= See the whole thing work in three minutes =

The default timings wait an hour before a cart counts as abandoned and another hour before emailing. That is right for a real shop, but it means a new installation shows nothing for two hours and you cannot tell whether it is set up correctly.

Speed it up once, watch a full cycle, then put it back:

1. In **Settings**, set the abandonment threshold to 1 minute, the scan interval to 1 minute, and the send delay to 1 minute. Note the old values first.
2. Open the shop in a private browsing window, add a product, go to checkout, type an email address you can open, and close the tab without ordering.
3. In **Carts**, watch the row turn from Active to Abandoned within a minute or two, then check the inbox for the reminder.
4. Follow the "Complete your order" button — the cart rebuilds itself and checkout reopens with the details already filled in — and place the order.
5. Mark that order Processing or Completed in WooCommerce. The cart becomes Recovered and the amount appears in recovered revenue.
6. Restore the original timings.

Run this on a staging site where possible. While the timings are short, real shoppers on a live store can be marked abandoned and emailed within minutes.

Background jobs in WordPress only run when somebody visits the site, so on a quiet test store load the shop's front page a couple of times to let the scan catch up.

= Recommended first-time setup =

Before enabling customer email:

1. Confirm the store's privacy notice and consent process.
2. Decide whether guest cart tracking should be enabled.
3. Choose an abandonment threshold that matches the store's buying cycle.
4. Set the recovery email delay.
5. Customize the default template in the store's brand voice.
6. Add an existing coupon only when a discount fits the recovery strategy.
7. Send a test message and follow its recovery link in a test cart.
8. Confirm that restored products, variations, quantities, and coupons behave as expected.
9. Verify email delivery with the site's normal mail or SMTP tools.
10. Enable automatic sending and monitor the dashboard and activity log.

== Frequently Asked Questions ==

= What is WooCommerce cart abandonment? =

Cart abandonment occurs when a shopper adds products to a WooCommerce cart but leaves before completing payment. An abandoned checkout may still contain useful cart, customer, and product information that can support a timely follow-up.

= What does an abandoned cart recovery plugin do? =

An abandoned cart plugin records eligible shopping carts, identifies when a checkout has been left incomplete, and provides a way to re-engage the shopper. Cart Rebound can send a reminder email with a secure link that restores the saved cart and returns the customer to checkout.

= Is Cart Rebound free? =

Yes. Cart Rebound is free, open-source software licensed under GPLv2 or later. The plugin does not require a paid Cart Rebound account, subscription, or license key.

= When is a cart considered abandoned? =

You choose the idle-time threshold in minutes. A cart must have items and a captured, valid email address. After it remains inactive beyond the threshold, the next scheduled scan marks it abandoned.

= Are anonymous carts with no email marked abandoned? =

No. Cart Rebound requires a captured email before a cart becomes eligible for abandonment recovery. Stale active carts without an email can later be removed according to the configured retention period.

= Does Cart Rebound track logged-in customers? =

Yes. Logged-in carts are tracked while Cart Rebound and WooCommerce are active. Available account details can be associated with the cart to support reporting and recovery.

= Can Cart Rebound recover guest carts? =

Yes. Enable **Track guest carts** to record carts for logged-out customers and capture the email address they provide during checkout. Guest tracking is disabled by default.

= Does it support the WooCommerce Checkout Block? =

Yes. Guest information capture and order linking support both classic checkout and block-based checkout through the WooCommerce Store API.

= Can it send automatic abandoned cart emails? =

Yes. Enable recovery email, choose the delay, and select a default template. Cart Rebound schedules one automatic message for each eligible abandoned cart.

= Will a recovery email still be sent after the customer purchases? =

No, provided the order is linked and the cart is no longer abandoned before the scheduled send runs. The mailer checks the cart again immediately before sending and skips carts that have converted or are otherwise ineligible.

= Can I create an automated email sequence? =

Not in the current version. Cart Rebound schedules one automatic recovery email per eligible cart. You can create multiple templates and manually send another selected template when appropriate.

= Can I send a recovery email manually? =

Yes. Open the cart management screen, choose an eligible cart with a valid email and items, and select the template to send.

= Can I customize the cart reminder email? =

Yes. Customize the subject, rich-text body, sender name, sender email, images, links, formatting, merge tags, and optional coupon. Templates can be previewed with sample data and sent as test messages.

= Which email personalization tags are available? =

Shopper tags (`{first_name}`, `{last_name}`, `{full_name}`, `{email}`), cart tags (`{products}`, `{products_table}`, `{product_names}`, `{items_count}`, `{cart_total}`, `{abandoned_on}`), link tags (`{recovery_url}`, `{checkout_url}`, `{unsubscribe_url}`), the `{coupon_code}` tag, and store tags (`{store_name}`, `{store_url}`, `{store_email}`, `{manager_name}`, `{current_year}`). The full list, with a description of each, sits under the body editor in the template screen.

= Can I add a coupon to an abandoned cart email? =

Yes. Select an existing WooCommerce coupon in the template and place `{coupon_code}` in the message. Cart Rebound does not generate new, unique, or time-limited coupons.

= Does the recovery link restore coupons already applied to the cart? =

Yes. Coupon codes stored with the cart are reapplied during restoration, subject to current WooCommerce coupon validity and restrictions.

= Does cart recovery support variable products? =

Yes. The saved variation ID and variation attributes are used when rebuilding the cart. WooCommerce still performs its standard stock and purchasability checks.

= What happens if a saved product is unavailable? =

WooCommerce controls whether each product or variation can be added. Unavailable or invalid items may not be restored, while eligible items can still be added to the cart.

= Is the cart recovery link secure? =

The URL uses a random recovery token and does not expose the raw session key. Only an active or abandoned cart with a matching token can be restored. As with any customer-specific login or recovery link, recipients should not share it publicly.

= Where does the shopper go after restoring the cart? =

After the saved items and coupons are restored, Cart Rebound redirects the shopper to the WooCommerce checkout page.

= How does Cart Rebound know an order was recovered? =

It stamps the WooCommerce order with an explicit reference to the originating tracked cart. When the order reaches a configured paid status, the plugin marks a previously abandoned cart as recovered.

= How is recovered revenue calculated? =

Recovered revenue is the WooCommerce order total for paid orders explicitly attributed to recovered carts. Normal purchases completed before abandonment are recorded separately and do not increase recovered revenue.

= Can I choose which order statuses count as paid? =

Yes. Select the WooCommerce statuses that should complete attribution. Processing and Completed are selected by default.

= Can I manually attribute a recovered cart to an order? =

Yes. Authorized managers can use the cart screen to link a cart to a recent WooCommerce order and record the recovery.

= What cart recovery reports are included? =

The dashboard includes recoverable, recovered, and lost order counts; recoverable and recovered revenue; lifetime recovery rate; revenue trends; recent activity; and a product abandonment/recovery report.

= Can the administrator be notified after a recovery? =

Yes. Enable admin recovery notification and choose an email address, or use the site's administrator address.

= Does Cart Rebound track email opens and clicks? =

No. The current version records recovery email send activity but does not include open-rate or click-rate tracking.

= Does Cart Rebound use an external service? =

No. The plugin does not require an external cart recovery platform or Cart Rebound account. Data is stored in the WordPress database and email is sent through the site's configured WordPress mail system.

= Does it work with SMTP plugins? =

Cart Rebound sends through `wp_mail()`. SMTP and transactional email plugins that correctly integrate with the WordPress mail system should handle those messages. Always send a test message because delivery depends on the site's complete mail configuration.

= Does Cart Rebound work with WooCommerce HPOS? =

Yes. Cart Rebound declares compatibility with High-Performance Order Storage and uses WooCommerce order APIs rather than directly relying on legacy post storage.

= Is Cart Rebound GDPR compliant? =

The plugin provides privacy-focused controls, but it does not claim to guarantee legal compliance. Guest tracking and automatic email default to off, retention is configurable, unsubscribe links are included, data stays local, and WordPress export and erasure tools are supported. Each store must configure and operate these features under the laws that apply to it.

= Can customers unsubscribe from cart recovery emails? =

Yes. Recovery messages include an unsubscribe link. An unsubscribed address is checked before future recovery messages are sent.

= What happens to old cart data? =

The daily cleanup process removes stale records according to the configured retention periods. Defaults are 30 days for stale active and unrecovered carts and 365 days for recovered and completed carts.

= What happens when Cart Rebound is uninstalled? =

A normal WordPress uninstall removes the plugin's tables, settings, templates, counters, and scheduled actions. Deactivation alone preserves data so the plugin can be reactivated.

= Does Cart Rebound prevent cart abandonment? =

Cart Rebound focuses on recovering eligible carts after shoppers leave. Its product and revenue reports may help a store investigate patterns, but the plugin does not promise to eliminate the underlying causes of checkout abandonment.

= Can developers integrate Cart Rebound with other automation? =

Yes. Use the `cart_rebound_abandoned` and `cart_rebound_recovered` WordPress actions. The flat event payload is designed for custom code and automation tools. The plugin's administrative application also uses protected REST endpoints.

== Screenshots ==

1. **WooCommerce cart recovery dashboard** — Monitor recoverable orders, recovered orders, lost orders, recoverable revenue, recovered revenue, lifetime recovery rate, revenue trends, recent activity, and product performance.
2. **Abandoned cart management** — Filter every tracked cart, review status and order attribution, inspect details, copy recovery links, send follow-up emails, update statuses, and perform bulk actions.
3. **Recovery email template builder** — Create reusable rich-text abandoned cart emails with merge tags, Media Library images, WooCommerce coupons, previews, sender details, and test sends.
4. **Cart recovery activity log** — Trace abandoned carts, sent reminder emails, linked WooCommerce orders, recovered carts, cleanup, and failures using level, event, and cart filters.
5. **Flexible cart abandonment settings** — Control guest tracking, abandonment timing, scan interval, retention, paid statuses, automatic recovery emails, admin notifications, and email delay.

== Privacy ==

Cart Rebound may store a plugin-specific session identifier, WordPress user ID, email address, first and last name, phone number, cart products, quantities, variations, prices, coupon codes, totals, currency, cart and order status, order references, recovered amounts, timestamps, and related activity logs.

For tracked carts, a first-party `cart_rebound_ref` cookie associates a browser with its cart. It contains a random plugin-specific identifier, is HTTP-only, uses SameSite=Lax, uses the Secure attribute on HTTPS, and expires after approximately 30 days.

Guest tracking is disabled by default. Logged-in cart tracking operates while the plugin is active. Store owners should evaluate their legal basis and consent requirements before enabling and using tracking or recovery email features.

Automatic recovery emails are disabled by default. When enabled, messages use the site's WordPress mail system. Cart Rebound does not transmit tracked-cart data, telemetry, or usage information to the plugin author or a Cart Rebound service. A site's hosting, SMTP, mail, security, backup, or integration providers may process data according to that site's configuration.

By default, stale active and unrecovered carts are removed after 30 days, while recovered and completed carts are removed after 365 days. Both periods are configurable. Associated activity logs are deleted with each removed cart.

Cart Rebound registers exporters and erasers with the WordPress personal-data tools. Requests are matched by email and, for registered shoppers, WordPress user ID. Security-sensitive recovery tokens and checkout URLs are excluded from personal-data exports.

Suggested disclosure text is added to the WordPress privacy-policy guide. Site owners remain responsible for consent, lawful processing, email-marketing rules, retention settings, responding to privacy requests, securing stored information, and publishing an accurate privacy notice.

== Development ==

Human-readable PHP, TypeScript, React, and CSS source, tests, build configuration, and documentation are available at [github.com/RishadAlam/cart-rebound](https://github.com/RishadAlam/cart-rebound).

The plugin exposes `cart_rebound_abandoned` and `cart_rebound_recovered` actions for custom integrations. Administrative REST routes use the `cart-rebound/v1` namespace and require the appropriate permission.

Production packages can be created with `pnpm production-zip`. See the repository documentation for Composer, Node.js, pnpm, testing, translation, and local-development instructions.

Bundled third-party JavaScript libraries are GPL-compatible and distributed under the MIT License. Copyright and license notices are included in `THIRD-PARTY-LICENSES.txt`.

== Changelog ==

= 1.1.2 =

**Release date:** unreleased

**Fixed**

* Fixed recovered revenue being lost when a shopper returned to an abandoned cart without clicking the recovery link and then paid — the order was recorded as a straight-through completion instead of a recovery.
* Fixed a recovery link opening a second, duplicate cart record for the same shopper, which could queue them a second recovery email for a cart they had already returned to.
* Fixed the recovery link dropping shoppers onto an empty checkout form; the email, name and phone already captured for the cart are now filled in for them.
* Fixed coupons that had been deleted since the cart was captured being re-applied on the recovery landing page, where they produced a "coupon does not exist" error.
* Fixed a failed "send recovery email" or "mark recovered" reporting nothing: the reason is now shown inside the dialog instead of behind it.
* Fixed the "count a cart as recovered when its order is" setting accepting order statuses WooCommerce does not register.
* Fixed the Pending payment badge and the coupon chip falling below the WCAG AA contrast minimum.
* Fixed creating an email template switching the editor to a different template, so the next edit landed on the wrong one.

**Improved**

* Made the loading shimmer run on the compositor instead of the main thread, removing the only layout-shift culprit on the admin screens.
* Stopped shipping the current user's full capability list into every Cart Rebound admin page, cutting tens of kilobytes from each page load.
* Stopped exposing the visitor's tracking session key in the carts REST response; nothing displayed it.

= 1.1.1 =

**Release date:** 2026-08-22

**Improved**

* Tested against WordPress 7.1.

**Fixed**

* Corrected the plugin banner and screenshot assets shown on WordPress.org.

= 1.1.0 =

**Release date:** 2026-08-18

**Added**

* Added Visual and HTML views to the email body editor, with a Format button that indents hand-written markup.
* Added merge tags for the shopper's surname, full name, and email address.
* Added merge tags for the cart's item names, item count, value, and the date it was left behind.
* Added merge tags for the checkout page, the store name, address, contact email, manager name, and the current year.
* Added a `{products_table}` merge tag that renders the abandoned items as a table.
* Added per-template product-table options: column choice and order, ruled, boxed, or rule-free rows, thumbnail size, column headings, tax-inclusive prices, product links, the chosen variation under each name, a closing cart-total row, and a row cap.

**Improved**

* Reorganised the template editor into Message, Product table, and Delivery sections with a save bar that stays in reach and reports whether changes are pending.
* Warned before switching template while edits are unsaved.
* Listed every merge tag with a description under the body editor, and made the picker searchable.
* Added a template filter, template count, and subject preview to the template list.

**Fixed**

* Showed bullet and number markers inside the body editor, so a list looks in the editor the way it arrives in the inbox.
* Kept list and alignment commands on the current line instead of applying them to the whole email body.
* Stopped a stray paragraph tag appearing inside the rendered product table.
* Styled the product list and table inline so email clients render them consistently.
* Used the live product name when a tracked cart line was stored without one.

= 1.0.0 =

**Release date:** 2026-07-26

**Added**

* Added pending-payment tracking and clear active, abandoned, recovered, completed, and lost cart lifecycle states.
* Added configurable WooCommerce order statuses that determine when a cart counts as recovered.
* Added confirmation-based unsubscribe and persistent suppression for shoppers who opt out of recovery emails.
* Added recovery email test sends with sample customer, product, coupon, and recovery-link data.
* Added a first-run wizard for guest tracking, abandonment timing, and recovery email setup.
* Added complete cart details, linked orders, status history, cart IDs, lifecycle guidance, and explicit view actions.
* Added recoverable revenue, 7-day, 30-day, and 90-day trends, recent activity, and product-level abandonment and recovery reports.
* Added optional administrator notifications when an abandoned cart becomes a paid order.

**Improved**

* Improved cart and activity tables with configurable page sizes and HPOS-aware WooCommerce order links.
* Redesigned the dashboard, cart details, email template preview, unsubscribe screen, settings, and data-dense administration layouts.

**Fixed**

* Formatted monetary values using the store's WooCommerce currency symbol, position, decimal, and thousands-separator settings.
* Prevented checkout events from clearing tracked cart contents prematurely or leaving completed carts active.
* Kept recovery status and recovered revenue accurate after linked orders are cancelled, failed, or refunded.
* Corrected admin form alignment and positioned the Cart Rebound menu directly below WooCommerce.

**Security**

* Sanitized recovery-email unsubscribe request values before processing.

= 0.1.0 =

**Release date:** 2026-07-22

**Initial release**

* Launched registered-customer and optional guest cart tracking for classic checkout and the WooCommerce Checkout Block.
* Added configurable abandonment detection, recovery email templates, manual reminders, and secure one-click cart restoration.
* Added explicit recovered-order attribution, recovered revenue reporting, an administration dashboard, and a filterable activity log.
* Added HPOS compatibility, privacy tools, protected REST endpoints, and WordPress actions for custom recovery workflows.
