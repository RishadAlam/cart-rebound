=== Cart Rebound – Abandoned Cart Recovery for WooCommerce ===
Contributors: rishadbitcode
Tags: abandoned cart, cart recovery, woocommerce, cart abandonment recovery, abandoned cart recovery
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted WooCommerce abandoned cart recovery plugin. Track cart abandonment, send automated recovery emails, and restore checkouts in 1 click.

== Description ==

**Cart Rebound** is a free, lightweight, self-hosted **abandoned cart recovery plugin for WooCommerce**. It tracks abandoned carts, sends automated **cart recovery** emails (with a coupon code if you want one), and lets shoppers get their checkout back in one click. You don't pay a monthly SaaS fee or sign up for an outside account. If you want a WooCommerce abandoned cart plugin that keeps every cart and every email inside your own store, that's what Cart Rebound is built for.

Shopping cart abandonment is when a customer adds products to the cart and leaves before paying. Cart Rebound takes care of the whole cart abandonment recovery process. It saves the cart, notices when it goes quiet, and gives the shopper an easy way back. You get clear abandoned cart reports, reminder emails you can customize, secure recovery links, product insights, and order tracking you can trust, all inside WordPress.

Every abandoned checkout is a sale that almost happened. Some store owners call them incomplete orders. Either way, the shopper picked products, started checkout, and left. Cart Rebound helps you win back some of those sales with a timely reminder email and a link that restores the saved cart and takes the shopper back to checkout.

It works for logged-in customers and, if you turn it on, for guest shoppers too. It supports the classic WooCommerce checkout, the **Checkout Block / Store API**, **variable products**, coupons, and **WooCommerce High-Performance Order Storage (HPOS)**. Guest tracking and automatic emails start switched off, so you decide when to use them.

New here? The [Quick start guide](https://cart-rebound.com/docs/quick-start) walks you through the first setup, and the documentation links below explain every screen and setting.

= WooCommerce abandoned cart recovery at a glance =

- Tracks carts for logged-in customers. One switch pauses tracking whenever you want.
- Can also save guest carts, along with the email, name, and phone number typed at checkout.
- Detects cart abandonment after the idle time you choose, using WooCommerce Action Scheduler.
- Sends one automatic reminder email after a delay you set. You can also send a follow-up by hand at any time.
- Lets you build reusable recovery emails in a rich-text or HTML editor, preview them with sample data, and send yourself a test.
- Adds a `{products_table}` of the items left behind, with the columns, thumbnails, variations, and tax-inclusive prices you pick.
- Personalizes each email with merge tags for the customer, the products, the recovery link, your store, and an existing WooCommerce coupon.
- Restores products, quantities, variations, and coupon codes in one click through a secure link.
- Fills in the returning shopper's email, name, and phone number at checkout so they can pay faster.
- Finds any cart by customer email, name, phone number, cart ID, or WooCommerce order number.
- Shows abandoned carts, recovered orders, your recovery rate, recovered revenue, and which products get left behind.
- Can email you when a tracked cart turns into a paid order.
- Keeps all tracking data in your own WordPress database, with retention and automatic cleanup you control.
- Works with the WordPress privacy export and erase tools, and puts a one-click unsubscribe link in every reminder.
- Lets developers hook into every step through WordPress actions and protected REST endpoints.

= Who Cart Rebound is for =

* **Small WooCommerce shops** that want free abandoned cart recovery without a monthly SaaS subscription or caps on carts and emails.
* **Stores with guest checkout** that need to save the shopper's email before the order is placed, so an abandoned checkout can still be followed up.
* **Owners who want simple abandoned cart reminder emails** in their own words, with a table of the items left behind and an optional discount coupon.
* **Stores using the WooCommerce Checkout Block**, as well as stores on the classic checkout page.
* **Shop owners who want honest numbers**: which products show up most in abandoned and recovered carts, how many carts came back, and how much revenue was won back.
* **Developers and agencies** who want to send abandoned cart events to a CRM, an email marketing tool, or a webhook through custom code or an automation plugin.

= How Cart Rebound recovers abandoned carts =

**1. It saves the WooCommerce cart**

Whenever a shopper adds, changes, removes, or empties items, Cart Rebound saves a snapshot of the cart. Logged-in customers are tracked while the plugin is active. If you turn on guest tracking, Cart Rebound also links a logged-out visitor to their cart and saves the email, name, and phone number they type at checkout.

The snapshot holds the products, quantities, variations, prices, cart total, currency, coupon codes, item count, customer details, and when each change happened.

**2. It spots the abandoned checkout**

You choose how many idle minutes make a cart abandoned and how often Cart Rebound checks. A cart only counts as abandoned if it still has items in it and a valid email address. When you change the idle time, the new value applies from the next check.

The check runs in the background through WooCommerce Action Scheduler, with WP-Cron as a fallback, so cart abandonment checks stay out of the shopper's page load.

**3. It schedules a cart recovery email**

When a cart becomes abandoned, Cart Rebound can schedule one automatic email after the delay you set. Right before sending, it looks at the cart again. If the cart has already turned into an order, is empty, has no valid email, or was already emailed, the email is skipped.

Automatic recovery emails stay off until you turn them on. You can also pick a saved template and send a reminder by hand from the cart screen.

**4. It restores the abandoned shopping cart**

Every tracked cart gets its own random recovery token. The email link uses that token, never the WooCommerce session key. When the shopper clicks the link, Cart Rebound adds back the items that are still available, with their saved quantities, variations, and coupon codes, and then takes the shopper to checkout.

**5. It credits the recovered order**

When checkout creates an order, Cart Rebound tags the order with the cart it came from. Once the order reaches one of the paid statuses you chose, an abandoned cart is marked as recovered and its order total counts as recovered revenue. A cart that checks out before it was ever abandoned is marked as completed and stays out of the recovery totals.

= Automated abandoned cart emails =

Once you turn on email, Cart Rebound schedules one email for each abandoned cart that qualifies. You choose how long to wait and which template to use. Every sent email is recorded, so the same automatic email never goes out twice.

Emails go out through the normal WordPress mail system. If you use an SMTP or transactional email plugin that works with `wp_mail()`, Cart Rebound uses it too. Whether an email reaches the inbox still depends on your hosting, DNS, mail provider, and SMTP setup.

= Custom recovery email template builder =

Make as many templates as you like, for different products, seasons, or tones of voice. Pick one as the default for automatic cart recovery emails, and use any of them when you send a reminder by hand.

In the editor you can:

* set the template name and subject line
* format text and add links
* add headings, lists, alignment, quotes, and text colors
* insert images from the WordPress Media Library
* set a sender name and sender email
* attach an existing WooCommerce coupon
* preview the email with sample data
* send yourself a test email
* choose which template is the default

Merge tags fill in the details for each shopper:

* `{first_name}`, `{last_name}`, `{full_name}`, `{email}` — who the shopper is
* `{products}`, `{products_table}`, `{product_names}`, `{items_count}`, `{cart_total}` — what they left behind
* `{abandoned_on}` — the date they left it
* `{recovery_url}`, `{checkout_url}`, `{unsubscribe_url}` — where the email can send them
* `{coupon_code}` — the WooCommerce coupon you picked
* `{store_name}`, `{store_url}`, `{store_email}`, `{manager_name}`, `{current_year}` — details about your store

Each email gets a clear **Complete your order** button that opens the recovery link. You can restyle the button in the **Button** tab, place it yourself with `{recovery_button}`, or switch it off. Every recovery email also includes a one-click unsubscribe link.

The table of abandoned products has its own settings in each template. Pick the columns (thumbnail, product, SKU, quantity, unit price, line total) and their order. Choose ruled, boxed, or plain rows and the thumbnail size. You can also show prices with tax, link each row to its product page, show the chosen variation under the name, end with a cart-total row, and limit how many rows appear before an "and N more items" line.

= WooCommerce coupons and cart restoration =

Cart Rebound works with coupons in two ways:

1. Coupon codes that were already on the cart are saved and applied again when the cart is restored.
2. You can attach an existing WooCommerce coupon to an email template and show its code with `{coupon_code}`.

Cart Rebound doesn't create new coupons for you. WooCommerce still controls each coupon's expiry, usage limits, product rules, and discount, and runs its usual stock and catalog checks as items go back into the cart.

= Secure one-click cart recovery links =

Each saved cart is identified by a long, random token. The link never shows the WooCommerce session key, the WordPress user ID, or anything else that could be guessed about the customer.

When a shopper opens a valid recovery link, Cart Rebound:

1. finds their active or abandoned cart
2. empties the current cart so unrelated items don't get mixed in
3. adds back the products that are still available, with their saved quantities
4. restores the chosen variations
5. applies the saved coupon codes again
6. connects the restored cart to this recovery
7. takes the shopper to WooCommerce checkout

Invalid links, and links for carts that already became orders, don't restore anything. Once a recovered cart is tied to its order, Cart Rebound clears that connection, so a later, unrelated purchase isn't counted as a recovery too.

= Accurate recovered revenue and order attribution =

Honest numbers are at the heart of Cart Rebound. It never guesses that an order belongs to a cart because the email, products, or total look similar. Instead, it writes a direct reference to the cart on the WooCommerce order, and it only settles the cart once the order reaches a paid status you chose.

Because the link is direct, coupons, shipping, tax, slow payments, and edited order totals don't break it.

Here is how each cart is counted:

* A paid order from a cart that was abandoned first counts as **recovered**.
* A paid order from a cart that was never abandoned counts as **completed**.
* An order that was placed but not paid yet stays **pending payment** until its status changes.

Recovered revenue is the paid WooCommerce order total of recovered carts only. Ordinary sales are not counted as recovery wins.

= Cart abandonment dashboard and analytics =

The dashboard turns your tracked carts into WooCommerce cart recovery reports you can read at a glance:

* your recovered revenue, in plain words
* how many carts are in each status
* your all-time cart recovery rate
* abandoned versus recovered value over time
* the biggest open carts still worth chasing
* whether tracking and recovery emails are switched on
* the products that show up in abandoned and recovered carts

You can view the last 7, 30, or 90 days, and each period is compared with the one before it. The product report shows which items are often left in abandoned shopping carts and which of them later come back in recovered carts.

The reports show what was recorded; they don't claim Cart Rebound caused every sale.

= Privacy tools and safe defaults =

Cart and checkout details can be personal data. Cart Rebound gives you tools to handle them responsibly:

* guest tracking is off by default
* automatic recovery emails are off by default
* every reminder email includes an unsubscribe link
* you set separate retention periods for open carts and for converted carts
* suggested text is added to the WordPress privacy policy guide
* the WordPress personal data tools can export cart and activity data
* the same tools can erase matching cart and log data

No plugin can make a store compliant with GDPR, PECR, CCPA, CAN-SPAM, or any other privacy or email marketing law on its own. Your legal basis, consent, and privacy notice stay your responsibility.

= WooCommerce compatibility =

Cart Rebound is built for how WooCommerce works today: the classic checkout and the Checkout Block, simple and variable products, coupons, High-Performance Order Storage (HPOS), Action Scheduler background jobs, and the WooCommerce order APIs. Some third-party checkout, cart, caching, translation, or email extensions change how WooCommerce normally behaves, so compatibility with a specific one can vary.

= Documentation and support =

Getting started:

* [Quick start](https://cart-rebound.com/docs/quick-start)
* [Cart statuses](https://cart-rebound.com/docs/cart-statuses): what active, abandoned, recovered, completed, and lost mean

Setting it up:

* [Settings reference](https://cart-rebound.com/docs/settings): every setting and its default
* [Email templates](https://cart-rebound.com/docs/email-templates) and [merge tags](https://cart-rebound.com/docs/merge-tags)
* [Recovery links](https://cart-rebound.com/docs/recovery-links)

Daily use and reports:

* [Dashboard and reports](https://cart-rebound.com/docs/dashboard), [Manage carts](https://cart-rebound.com/docs/manage-carts), and the [Activity log](https://cart-rebound.com/docs/activity-log)
* [Revenue attribution](https://cart-rebound.com/docs/attribution): how a recovered order is counted
* [Troubleshooting](https://cart-rebound.com/docs/troubleshooting)

More links:

* [Official website](https://cart-rebound.com/)
* [Full FAQ](https://cart-rebound.com/faq)
* [Changelog](https://cart-rebound.com/changelog) and [roadmap](https://cart-rebound.com/roadmap)
* [Support forum](https://wordpress.org/support/plugin/cart-rebound/)
* [Report a bug or request a feature](https://github.com/RishadAlam/cart-rebound/issues)

= Requirements =

* WooCommerce, installed and active
* WordPress 6.2 or later
* PHP 7.4 or later

= Privacy =

Cart Rebound stores cart and customer details in your own WordPress database and sets one first-party cookie, `cart_rebound_ref`. It does not send tracked cart data, telemetry, or usage information to the plugin author or to any Cart Rebound service. The FAQ below lists exactly what is stored, and [Privacy and data](https://cart-rebound.com/docs/privacy-and-data) covers it in full.

= Development =

The full, human-readable source (PHP, TypeScript, React, and CSS), along with tests, build setup, and documentation, is on GitHub at [github.com/RishadAlam/cart-rebound](https://github.com/RishadAlam/cart-rebound).

Cart Rebound fires WordPress actions for custom integrations, including `cart_rebound_abandoned`, `cart_rebound_recovered` and `cart_rebound_cart_status_changed`. Its admin REST routes live under the `cart-rebound/v1` namespace and need the right permission. See the [actions and filters reference](https://cart-rebound.com/docs/developers/hooks) and the [REST API reference](https://cart-rebound.com/docs/developers/rest-api).

The bundled third-party JavaScript libraries use the MIT License, which is compatible with the GPL. Their copyright and license notices are in `THIRD-PARTY-LICENSES.txt`.

== Installation ==

1. Install and activate WooCommerce.
2. In WordPress, go to **Plugins → Add New Plugin**, search for **Cart Rebound**, and click **Install Now**. You can also upload the zip file under **Plugins → Add New Plugin → Upload Plugin**.
3. Activate **Cart Rebound – Abandoned Cart Recovery for WooCommerce**.
4. Open **Cart Rebound** in the WordPress admin menu.
5. Go through the welcome screen, then open **Settings**.
6. Choose the abandonment time, how often to check, which order statuses count as paid, and how long to keep data.
7. Turn on guest tracking only if it suits your store.
8. Open **Templates** to look over the default recovery email, the sender details, merge tags, and the optional coupon.
9. Send yourself a test email to make sure your site's email works.
10. Turn on automatic recovery emails when you're ready to start sending reminders to customers.

The [installation guide](https://cart-rebound.com/docs/installation) and the [Quick start](https://cart-rebound.com/docs/quick-start) go through each step in more detail.

= See the whole thing work in three minutes =

By default, a cart waits an hour before it counts as abandoned, and then another hour before the email goes out. That's right for a real shop, but it means a fresh install shows nothing for two hours, and you can't tell whether everything is set up correctly.

Speed it up once, watch a full cycle, then put it back:

1. In **Settings**, set the abandonment time, the check interval, and the email delay to 1 minute each. Write down the old values first.
2. Open your shop in a private browser window. Add a product, go to checkout, type an email address you can check, and close the tab without ordering.
3. In **Carts**, watch the cart change from Active to Abandoned within a minute or two, then check your inbox for the reminder.
4. Click the **Complete your order** button. The cart rebuilds itself and checkout opens with your details already filled in. Place the order.
5. Mark that order as Processing or Completed in WooCommerce. The cart changes to Recovered, and the amount shows up in recovered revenue.
6. Put the original timings back.

Use a staging site for this if you can. While the timings are this short, real shoppers on a live store can be marked as abandoned and emailed within minutes.

WordPress only runs background jobs when someone visits the site. On a quiet test store, load the shop's front page a couple of times so the check can catch up.

= Recommended first-time setup =

Before you turn on customer emails:

1. Check your store's privacy notice and how you get consent.
2. Decide whether to track guest carts.
3. Pick an abandonment time that fits how your customers shop.
4. Set how long to wait before the recovery email goes out.
5. Rewrite the default template in your store's own voice.
6. Add an existing coupon only if a discount makes sense for you.
7. Send a test email and follow its recovery link with a test cart.
8. Check that products, variations, quantities, and coupons come back the way you expect.
9. Confirm that email delivery works with your usual mail or SMTP tools.
10. Turn on automatic sending, then keep an eye on the dashboard and the activity log.

== Frequently Asked Questions ==

= What is WooCommerce cart abandonment? =

Cart abandonment is when a shopper adds products to a WooCommerce cart and leaves before paying. The abandoned checkout often still holds useful details, such as the products and the shopper's email, that let you follow up while the purchase is still on their mind.

= What does an abandoned cart recovery plugin do? =

A WooCommerce abandoned cart plugin saves shopping carts, notices when a checkout was left unfinished, and gives you a way to bring the shopper back. Cart Rebound can send a reminder email with a secure link that restores the saved cart and takes the customer to checkout.

= How do I recover abandoned carts in WooCommerce? =

Install and activate Cart Rebound, then open its **Settings**. Choose how long a cart must sit idle before it counts as abandoned. Turn on guest cart tracking if your store allows guest checkout. Then switch on the recovery email and set how long to wait. When a cart with a saved email stays idle past your time limit, Cart Rebound marks it as abandoned and sends a reminder with a link that rebuilds the cart and opens checkout. The [Quick start guide](https://cart-rebound.com/docs/quick-start) walks through each step.

= Can I see abandoned checkouts and incomplete orders? =

Yes. The **Carts** screen lists every tracked cart with its status: active, abandoned, pending payment, recovered, completed, or lost. You can filter by status, search by customer or order, open a cart to see its products and customer details, or send a reminder email. A cart whose order was placed but not paid yet shows as pending payment.

= Is Cart Rebound free? =

Yes. Cart Rebound is a free WooCommerce abandoned cart plugin, and it's open source under GPLv2 or later. You don't need an account, subscription, or license key to use it.

= Is there a limit on how many carts or emails Cart Rebound handles? =

No. The free version does not cap tracked carts, abandoned cart emails, or recovered orders. Your hosting and mail provider set the practical limits on how much email your store can send.

= Will Cart Rebound slow down my store? =

Cart Rebound saves a small cart snapshot when a tracked cart changes. Abandonment checks and recovery emails run in the background through WooCommerce Action Scheduler, with WP-Cron as a fallback, so they don't hold up the shopper's page. Cart data is stored in the plugin's own database table, and old records are removed after the retention periods you choose.

= When is a cart considered abandoned? =

You choose the idle time in minutes. The cart must have items in it and a valid email address. Once it has been idle for longer than your time limit, the next background check marks it as abandoned.

= Are anonymous carts with no email marked abandoned? =

No. A cart needs a saved email before it can be marked as abandoned. Open carts without an email are deleted later, based on your retention setting.

= Does Cart Rebound track logged-in customers? =

Yes. Carts of logged-in customers are tracked while Cart Rebound and WooCommerce are active. Their account details can be linked to the cart for reports and recovery.

= Can Cart Rebound recover guest carts? =

Yes. Turn on **Track guest carts** to save carts for logged-out shoppers, along with the email address they type at checkout. Guest tracking is off by default.

= Does it support the WooCommerce Checkout Block? =

Yes. Guest details and order linking work on both the classic checkout and the block checkout, which uses the WooCommerce Store API.

= Can it send automatic abandoned cart emails? =

Yes. Turn on the recovery email, choose the delay, and pick a default template. Cart Rebound then schedules one automatic email for each abandoned cart that qualifies.

= Will a recovery email still be sent after the customer buys? =

No, as long as the order is linked to the cart before the email is due. Cart Rebound checks the cart again right before sending and skips any cart that has already turned into an order or no longer qualifies.

= Can I create an automated email sequence? =

Not in the current version. Cart Rebound schedules one automatic recovery email per cart. You can create several templates and send another one by hand when it makes sense.

= Can I send a recovery email manually? =

Yes. Open the Carts screen, pick a cart that has items and a valid email, and choose the template to send.

= Can I customize the cart reminder email? =

Yes. You can change the subject, the body text, the sender name and email, images, links, formatting, merge tags, the button, and the optional coupon. You can preview each template with sample data and send yourself a test.

= Which email personalization tags are available? =

Shopper tags (`{first_name}`, `{last_name}`, `{full_name}`, `{email}`), cart tags (`{products}`, `{products_table}`, `{product_names}`, `{items_count}`, `{cart_total}`, `{abandoned_on}`), link tags (`{recovery_url}`, `{checkout_url}`, `{unsubscribe_url}`), the `{coupon_code}` tag, and store tags (`{store_name}`, `{store_url}`, `{store_email}`, `{manager_name}`, `{current_year}`). The full list, with a short description of each tag, is under the body editor on the Templates screen.

= Can I add a coupon to an abandoned cart email? =

Yes. Pick an existing WooCommerce coupon in the template and put `{coupon_code}` in the message. Cart Rebound doesn't create new, unique, or time-limited coupons.

= Does the recovery link restore coupons already applied to the cart? =

Yes. Coupon codes saved with the cart are applied again when the cart is restored, as long as WooCommerce still accepts them.

= Does cart recovery support variable products? =

Yes. The saved variation and its attributes are used to rebuild the cart. WooCommerce still runs its normal stock and purchasability checks.

= What happens if a saved product is no longer available? =

WooCommerce decides whether each product or variation can be added back. Items that are unavailable may not be restored, but the rest of the cart still is, and the shopper is told what was left out.

= Is the cart recovery link secure? =

The link uses a random recovery token and never shows the raw session key. Only an active or abandoned cart with a matching token can be restored. Like any personal link, it shouldn't be shared publicly.

= Where does the shopper go after restoring the cart? =

Once the saved items and coupons are back in the cart, Cart Rebound takes the shopper to the WooCommerce checkout page.

= How does Cart Rebound know an order was recovered? =

It tags the WooCommerce order with a direct reference to the tracked cart it came from. When the order reaches one of your paid statuses, a cart that was abandoned before is marked as recovered.

= How is recovered revenue calculated? =

Recovered revenue is the WooCommerce order total of paid orders that are linked to recovered carts. Normal purchases made before a cart was ever abandoned are counted separately and don't add to recovered revenue.

= Can I choose which order statuses count as paid? =

Yes. Pick the WooCommerce statuses that should count. Processing and Completed are selected by default.

= Can I manually attribute a recovered cart to an order? =

Yes. Store managers can link a cart to a recent paid WooCommerce order from the Carts screen to record the recovery.

= What cart recovery reports are included? =

The dashboard shows your recovered revenue, how many carts are in each status, your all-time recovery rate, abandoned versus recovered value over the last 7, 30, or 90 days, the biggest open carts, and a report of the products in abandoned and recovered carts.

= Can I get an email when a cart is recovered? =

Yes. Turn on the admin recovery notification and enter an email address, or use your site's admin email.

= Does Cart Rebound track email opens and clicks? =

No. The current version records when each recovery email is sent, but it doesn't track opens or clicks.

= Can Cart Rebound send abandoned cart SMS or WhatsApp messages? =

No. Cart Rebound sends recovery reminders by email only. Developers can use the `cart_rebound_abandoned` action, which includes the shopper's details and recovery link, to pass the cart to an SMS or messaging service of their choice.

= Does Cart Rebound use an external service? =

No. WooCommerce cart recovery runs inside your own WordPress site, with no outside recovery platform or Cart Rebound account. Data is stored in your WordPress database, and email goes through your site's WordPress mail setup.

= Does it work with SMTP plugins? =

Cart Rebound sends email through `wp_mail()`, so SMTP and transactional email plugins that hook into the WordPress mail system should handle these emails too. Always send a test, because delivery depends on your whole mail setup.

= Does Cart Rebound work with WooCommerce HPOS? =

Yes. Cart Rebound declares support for High-Performance Order Storage and uses the WooCommerce order APIs instead of relying on the old post storage.

= Is Cart Rebound GDPR compliant? =

Cart Rebound gives you privacy-friendly controls, but it can't guarantee legal compliance on its own. Guest tracking and automatic emails are off by default, retention is up to you, every email has an unsubscribe link, data stays on your site, and the WordPress export and erase tools are supported. Each store still has to set these up and run them according to the laws that apply to it.

= What data does Cart Rebound store? =

Cart Rebound may store a plugin session ID, WordPress user ID, email address, first and last name, phone number, cart products, quantities, variations, prices, coupon codes, totals, currency, cart and order status, order references, recovered amounts, timestamps, and related activity logs. A cart's activity log is deleted along with the cart.

Logged-in customers' carts are tracked while the plugin is active. Guest tracking and automatic recovery emails are off by default. Before you turn them on, check your legal basis and whether you need consent. Emails go through your site's WordPress mail system, so your hosting, SMTP, mail, security, backup, or integration providers may still handle data, depending on how your site is set up.

= Does Cart Rebound set cookies? =

Yes, one. To keep track of a cart, it sets a first-party cookie called `cart_rebound_ref`. It holds a random ID created by the plugin. The cookie is HTTP-only, uses SameSite=Lax, is marked Secure on HTTPS sites, and lasts about 30 days.

= How does Cart Rebound handle personal data requests? =

It adds exporters and erasers to the WordPress personal data tools. Requests are matched by email address and, for registered shoppers, by WordPress user ID. Recovery tokens and checkout links are left out of exports for security. Suggested text is also added to the WordPress privacy policy guide. You stay responsible for consent, lawful processing, email marketing rules, retention settings, answering privacy requests, keeping stored data safe, and publishing an accurate privacy notice.

= Can customers unsubscribe from cart recovery emails? =

Yes. Every recovery email has an unsubscribe link. Cart Rebound checks for unsubscribed addresses before sending any future recovery email.

= What happens to old cart data? =

A daily cleanup deletes old records based on your retention settings. By default, stale open and unrecovered carts are deleted after 30 days, and recovered and completed carts after 365 days.

= What happens when Cart Rebound is uninstalled? =

When you delete the plugin the normal WordPress way, it removes its tables, settings, templates, counters, and scheduled tasks. Deactivating it keeps everything, so you can turn it back on later.

= Where can I learn more about cart abandonment? =

The Cart Rebound site has plain-English guides:

* [WooCommerce cart abandonment explained](https://cart-rebound.com/guides/woocommerce-cart-abandonment)
* [How to reduce your cart abandonment rate](https://cart-rebound.com/guides/reduce-cart-abandonment-rate)
* [Abandoned cart emails not sending](https://cart-rebound.com/guides/woocommerce-abandoned-cart-not-sending-emails)
* [GDPR and abandoned cart emails](https://cart-rebound.com/guides/gdpr-abandoned-cart-emails)
* [How to measure your cart recovery rate](https://cart-rebound.com/guides/measure-cart-recovery-rate)

= Does Cart Rebound prevent cart abandonment? =

Cart Rebound focuses on cart abandonment recovery: winning back carts after shoppers leave. Its product and revenue reports can help you spot patterns, but it doesn't promise to fix the reasons people abandon checkout.

= Where can I get help? =

Start with the [Cart Rebound documentation](https://cart-rebound.com/docs/quick-start) and the [Troubleshooting guide](https://cart-rebound.com/docs/troubleshooting). If you still need help, post in the [WordPress.org support forum](https://wordpress.org/support/plugin/cart-rebound/). You can report bugs and request features on [GitHub](https://github.com/RishadAlam/cart-rebound/issues).

= Can developers connect Cart Rebound to other automation tools? =

Yes. Cart Rebound fires a WordPress action at every step of the cart's life. Each one passes a simple, flat array, so it's easy to use from custom code, webhooks, and automation plugins:

- `cart_rebound_cart_created` — a new tracked cart is created.
- `cart_rebound_cart_captured` — a shopper's cart or checkout contact details are saved.
- `cart_rebound_abandoned` — a cart goes past the idle time and is marked as abandoned.
- `cart_rebound_recovery_email_sent` — an automatic or manual recovery email is sent.
- `cart_rebound_recovery_coupon_sent` — a recovery email with a coupon code is sent.
- `cart_rebound_recovery_email_failed` — a recovery email fails to send.
- `cart_rebound_recovery_link_clicked` — a shopper opens a cart recovery link.
- `cart_rebound_order_linked` — a WooCommerce order is linked to a tracked cart.
- `cart_rebound_recovered` — a cart that was abandoned reaches one of your paid order statuses.
- `cart_rebound_unsubscribed` — someone unsubscribes using the link in a recovery email.
- `cart_rebound_cart_status_changed` — a cart moves from one status to another.
- `cart_rebound_cart_status_{status}` — fires for the new status by name (for example `active`, `abandoned`, `recovered`, `completed`, or `lost`).

The admin screens also use protected REST endpoints under the `cart-rebound/v1` namespace.

== Screenshots ==

1. **WooCommerce cart recovery dashboard** — See your recovered revenue in plain words, how many carts are in each status, your all-time recovery rate, abandoned versus recovered value over time, the open carts worth chasing, your recovery setup, and how each product is doing.
2. **Abandoned cart management** — Filter and search every tracked cart, check its status and linked order, open its details, send a follow-up email, change its status, and take bulk actions.
3. **Recovery email template builder** — Write reusable abandoned cart emails with merge tags, Media Library images, WooCommerce coupons, previews, sender details, and test sends.
4. **Cart recovery activity log** — Follow abandoned carts, sent reminder emails, linked WooCommerce orders, recovered carts, cleanups, and failures, with filters for level, event, and cart.
5. **Cart abandonment settings** — Control guest tracking, abandonment timing, how often to check, data retention, paid statuses, automatic recovery emails, admin notifications, and the email delay.

== Changelog ==

= 1.1.3 =

**Release date:** 2026-11-02

**New**

* Design your own email button. Each email template has a new **Button** tab where you can change the button's text, size, colours, rounded corners, alignment and width, with a live preview. Add the `{recovery_button}` tag to place the button anywhere in your message, or switch the button off.
* A friendlier starter email. New stores get a ready-to-send recovery email with a greeting, a table of the items left in the cart, a coupon line and an invitation to reply. If you never changed the old default email, it is updated for you. Emails you have edited stay exactly as they are.
* A clearer "cart recovered" email for you. When a cart turns into a paid order, the email you get now shows the amount, the order, the customer and how the cart came back, with a **View order** button.
* Your store's look on the unsubscribe page. Shoppers who unsubscribe now see your site logo and colours instead of the Cart Rebound logo.
* After Cart Rebound brings back its first paid order, a short note on the Dashboard and Carts screens asks how the plugin has worked for you. You can write a review, be reminded in 14 days, or not be asked again. Your answer is remembered for the whole store.

**Improved**

* Recovery emails now look right in more email apps, including Outlook and Gmail, and on right-to-left language sites.
* Emails show product options by name (for example "Blue" instead of "blue-1"), and product images keep their shape.
* No email is sent when every product in the cart has since been deleted, or when the cart already has an order.
* Emails now include a reply-to address and a one-click unsubscribe option that Gmail and Yahoo show next to the sender.
* The date a cart was left behind now uses your site's time zone, and the `{manager_name}` tag says "our team" when no name is set.
* Shoppers who open an expired recovery link now see a message explaining what happened. If some items are out of stock, the rest of the cart is restored and the shopper is told what was left out.
* Cart Rebound now warns you before you leave the Templates or Settings screen with unsaved changes.
* When something can't be saved, you now see a clear message that names the field in plain words.
* The Carts screen asks before deleting a cart, shows each cart's total in its own currency, and marks times as UTC.
* The Dashboard tells you when its numbers fail to load, and shows numbers in your language's format.
* The setup wizard now closes only after your choices are saved, and lets you try again if saving fails.
* Merge tag names in the tag picker now match the tag reference list (for example "First name" for `{first_name}`), and you can search for a tag by typing its code.
* Personal data exports now include whether a shopper unsubscribed. When personal data is erased, unsubscribed email addresses are kept, so those shoppers still receive no reminders. The erasure report says why.
* Better keyboard support, screen reader labels and small-screen layouts across Cart Rebound screens.

**Fixed**

* Recovered revenue and the recovery rate now stay correct when orders change. A recovered order that is later refunded or cancelled is taken out of the totals, and the recovery rate never goes above 100%.
* **Mark recovered** now refuses an order that hasn't been paid, an order that doesn't exist, or an order already linked to another cart, so no sale is counted twice or too early.
* When a payment gateway and the checkout page both report a payment at the same moment, the recovery is now counted once and you get one "cart recovered" email, not two.
* A recovery link whose items are all out of stock no longer replaces the shopper's current cart.
* Checkout no longer fills in the original shopper's details when a different customer is logged in.
* The checkout page no longer sends the same customer details to your store over and over while a shopper types.
* Carts that already have an order are no longer marked as abandoned.
* Switches and checkboxes stay visible even when another plugin or a browser extension changes the page styles.
* Fixed layout jumps in the tab bar, Settings fields and Carts table, and sideways scrolling on small screens.
* Opening an email template without changing it no longer shows it as having unsaved changes.

= 1.1.2 =

**Release date:** 2026-10-16

**New**

* Search your carts. The Carts screen now has a search box that finds a cart by the customer's email, name or phone number, or by cart or order number.
* Pause cart tracking with one switch. A new **Track carts** setting lets you stop collecting carts at any time without deactivating the plugin. It is on by default.
* Clean up old activity automatically. You can now have Cart Rebound delete activity history after a period you choose, from 7 days to a year. This is off by default.
* Connect Cart Rebound to your other tools. Developers and automation plugins can now react when a cart is created, a customer's contact details are saved, a recovery email is sent or fails, a coupon is sent, a recovery link is clicked, an order is placed, a customer unsubscribes, or a cart changes status.

**Improved**

* Cart Rebound now looks and feels like the rest of WooCommerce, with the same fonts and sizes, and it follows the admin color scheme you picked in your WordPress profile.
* Each Cart Rebound screen now has its own clean web address, so you can bookmark a screen or open it in a new browser tab. The sidebar menu now highlights the screen you are on, and the browser's Back and Forward buttons work as expected.
* Customers who come back through a recovery link now find their email, name and phone number already filled in at checkout, so they can pay faster.
* Admin screens load faster and no longer jump around while loading.
* Better privacy: Cart Rebound sends less information to the browser and no longer shares a visitor's internal tracking code.

**Fixed**

* Sales were sometimes not counted as recovered. If a customer came back to an abandoned cart on their own, without clicking the email link, and then paid, the sale is now correctly counted as recovered revenue.
* Customers could receive a second recovery email for the same cart after clicking the recovery link. This no longer happens.
* A coupon that had been deleted from your store no longer shows a "coupon does not exist" error when a customer returns to their cart.
* When sending a recovery email or marking a cart as recovered fails, you now see the reason in the pop-up window instead of nothing happening.
* The "count a cart as recovered when its order is" setting now only accepts real WooCommerce order statuses.
* After creating a new email template, the editor now stays on that template, so your next change is saved to the right one.
* On a brand-new install, the Templates screen could show an error until the page was reloaded. It now opens normally the first time.
* The "Pending payment" label and the coupon tag are now easier to read.
* Fixed misaligned rows and borders in the Carts table, and the WordPress sidebar menu no longer sticks at the wrong height after switching screens.
* Cart Rebound's styles no longer change the look of other WordPress admin pages.

= 1.1.1 =

**Release date:** 2026-08-22

**Improved**

* Tested with WordPress 7.1.

**Fixed**

* The plugin banner and screenshots on WordPress.org now show correctly.

= 1.1.0 =

**Release date:** 2026-08-18

**New**

* Write emails in a Visual or an HTML view. A **Format** button tidies up hand-written HTML.
* New merge tags for the shopper's last name, full name, and email address.
* New merge tags for the names of the items in the cart, how many items there are, the cart value, and the date it was left.
* New merge tags for the checkout page, your store's name, web address, and contact email, the manager's name, and the current year.
* A `{products_table}` merge tag that shows the items left behind as a table.
* Product table options for each template: which columns to show and in what order, ruled, boxed, or plain rows, thumbnail size, column headings, prices with tax, links to each product, the chosen variation under each name, a cart-total row at the end, and a limit on rows.

**Improved**

* The template editor is now split into Message, Product table, and Delivery sections. The save bar stays in view and tells you when you have unsaved changes.
* You're warned before switching to another template with unsaved edits.
* Every merge tag is listed with a short description under the body editor, and the tag picker is searchable.
* The template list now has a filter, a template count, and a subject preview.

**Fixed**

* Bullets and numbers now show in the editor, so a list looks the same as it will in the inbox.
* List and alignment buttons now change only the current line, not the whole email.
* A stray paragraph tag no longer shows up inside the product table.
* The product list and table are now styled inline, so email apps show them the same way.
* If a saved cart item had no name, the email now uses the product's current name.

= 1.0.0 =

**Release date:** 2026-07-26

**New**

* Clear cart statuses: active, abandoned, pending payment, recovered, completed, and lost.
* Choose which WooCommerce order statuses count a cart as recovered.
* Shoppers confirm before they unsubscribe, and anyone who opts out stays opted out.
* Send test recovery emails filled with sample customer, product, coupon, and link details.
* A first-run setup wizard for guest tracking, abandonment timing, and recovery emails.
* Full cart details: linked orders, status history, cart IDs, what each status means, and clear view buttons.
* Recoverable revenue, 7-, 30-, and 90-day trends, recent activity, and reports on which products are abandoned and recovered.
* An optional email to you when an abandoned cart becomes a paid order.

**Improved**

* Cart and activity tables let you choose how many rows to show per page, and order links work with HPOS.
* Redesigned dashboard, cart details, email template preview, unsubscribe page, settings, and other busy admin screens.

**Fixed**

* Prices now use your store's WooCommerce currency symbol, position, and decimal and thousands separators.
* Checkout no longer clears saved cart contents too early or leaves finished carts marked as active.
* Recovery status and recovered revenue stay correct after a linked order is cancelled, fails, or is refunded.
* Fixed form alignment in the admin, and the Cart Rebound menu now sits right below WooCommerce.

**Security**

* Unsubscribe request values are now cleaned before they are processed.

= 0.1.0 =

**Release date:** 2026-07-22

**First release**

* Cart tracking for registered customers, plus optional guest tracking, on both the classic checkout and the WooCommerce Checkout Block.
* Abandonment detection you can adjust, recovery email templates, manual reminders, and secure one-click cart restore.
* Recovered orders credited through a direct order link, a recovered revenue report, an admin dashboard, and a filterable activity log.
* HPOS support, privacy tools, protected REST endpoints, and WordPress actions for custom recovery workflows.
