# Cart Rebound — Pro Features

Planned feature set for **Cart Rebound Pro**, a paid add-on that layers onto the
free WordPress.org plugin. The free plugin proves recovery works (tracking, the
full paid/unpaid lifecycle, and **one** follow-up email); Pro exists to _maximize_
recovered revenue.

This document is a product + implementation spec. Each feature notes what it is,
why it matters, how it fits Cart Rebound's existing architecture, the new
settings/schema/events it needs, and dependencies.

## Architecture it builds on

The free plugin already ships the primitives Pro extends — build on these, don't
replace them:

- **`CartSession` model + `wp_cart_rebound_sessions` table** — one row per cart,
  status lifecycle (`active → abandoned → pending-payment → recovered / completed
/ lost`). Well indexed (`uq_session_key`, `idx_status_activity`, `idx_email`,
  `idx_order_id`, `idx_recovery_token`).
- **`Cron\Scheduler`** — Action Scheduler wrapper (`schedule_single`,
  `clear_with_args`, `ensure_recurring`) with a wp-cron fallback.
- **`Cron\AbandonmentDetector`** — recurring sweep that flips idle carts to
  `abandoned` and queues the recovery email.
- **`Mail\RecoveryMailer`** (hook `cart_rebound_send_recovery_email`) +
  **`Mail\TemplateStore`** — renders/sends a single templated email.
- **`Events\EventDispatcher`** — fires `cart_rebound_abandoned`,
  `cart_rebound_recovered`, `cart_rebound_email_sent` for integrations.
- **`Recovery\RecoveryLink` / `RecoveryHandler`** — tokenised cart-restore links.
- **REST namespace `cart-rebound/v1`** (see `routes/api.php`) — already exposes a
  `/capture` endpoint; Pro adds routes here.
- **`Support\Settings`** — single `cart_rebound_settings` option, typed getters.

Pro should register its own service providers and gate every feature behind a
single `cart_rebound_is_pro()` capability check + license state.

---

## 1. Multi-step email sequence (flagship)

**What it is.** Replace the single follow-up with an ordered sequence of emails
per abandoned cart — e.g. _1h: gentle reminder → 24h: social proof / urgency →
72h: coupon_. Each step has its own delay, template, and enable toggle.

**Why it matters.** This is the single biggest lever on recovery rate: a 3-touch
sequence typically recovers 2–3× what one email does. It is the primary reason a
store upgrades to Pro.

**How it works.**

- A `sequence` config = ordered list of steps `{ delay_minutes, template_id,
enabled, coupon_policy }`, stored in settings (or a `wp_cart_rebound_sequence`
  table for many steps).
- When `AbandonmentDetector` marks a cart `abandoned`, instead of scheduling one
  `cart_rebound_send_recovery_email`, Pro schedules step 1 and, on each successful
  send, schedules the next step via `Scheduler::schedule_single(time + step.delay,
HOOK, [cart_id, step_index])`.
- The send handler skips the cart the moment it leaves `abandoned`
  (recovered/pending-payment/unsubscribed) — reuse the existing status guard in
  `RecoveryMailer::send()`, extended to accept a step index.
- On conversion, `OrderLinker` already calls
  `Scheduler::clear_with_args(HOOK, [cart_id])`; extend it to clear all pending
  steps for the cart.

**Settings.** `sequence_enabled` (bool), `sequence_steps` (array of steps). The
free single-email settings become "step 1" for backward compatibility.

**Schema.** Add `email_step tinyint` + `next_step_at datetime` to the sessions
table (or a small `sequence_jobs` table) so the current position survives a
scheduler backend switch.

**Events.** Extend `cart_rebound_email_sent` payload with `step` + `template_id`.

**Dependencies.** None new. Reuses Scheduler + TemplateStore + RecoveryMailer.

---

## 2. Auto-generated unique coupons

**What it is.** Generate a **unique, single-use, auto-expiring** WooCommerce
coupon per abandoned cart (or per sequence step), injected into the email via the
existing `{coupon_code}` token, with an optional countdown ("expires in 24h").

**Why it matters.** The free plugin ships a _static_ coupon string — reusable and
shareable, so it leaks and can't create real urgency. Per-cart codes with expiry
turn the discount into a controlled incentive and unlock urgency messaging, which
lifts conversion on the coupon step.

**How it works.**

- On the coupon step, call `wc_create_coupon`-equivalent (`WC_Coupon` +
  `->save()`) to mint a code like `REBOUND-<8 random>`, configured from a coupon
  policy: type (percent/fixed), amount, `date_expires`, `usage_limit = 1`,
  optional `email_restrictions = [cart email]`, `minimum_amount`.
- Store the code + expiry on the cart row; render `{coupon_code}` and
  `{coupon_expiry}` tokens in `RecoveryMailer::build_body()`.
- A cron pass (extend `Cron\Janitor`) deletes expired unredeemed coupons so the
  WooCommerce coupon list stays clean.

**Settings.** `coupon_auto` (bool), `coupon_type`, `coupon_amount`,
`coupon_expiry_hours`, `coupon_min_amount`, `coupon_restrict_email` (bool),
`coupon_prefix`.

**Schema.** `coupon_code varchar(64)` + `coupon_expires_at datetime` on the
sessions table.

**Events.** New `cart_rebound_coupon_generated` action `($cart_id, $code)`.

**Dependencies.** WooCommerce coupons must be enabled. Guard for stores that
disable coupons.

---

## 3. Email open / click / conversion tracking

**What it is.** Measure each email: opens (tracking pixel), clicks (wrapped
links), and attributed conversions (recovery within the window after a send).
Surfaces per-template and per-step performance.

**Why it matters.** You cannot tune the sequence (feature 1) without measurement.
This is what tells the merchant which email actually recovers carts — the data
that justifies the Pro price.

**How it works.**

- **Opens:** append a 1×1 pixel `…/cart-rebound/v1/track/open?e=<signed cart+step>`
  to the email HTML; the REST handler records the open and returns a transparent
  GIF. Sign the token (HMAC of cart id + step) to prevent forgery.
- **Clicks:** rewrite outbound links through
  `…/cart-rebound/v1/track/click?u=<enc url>&e=<token>`; record then 302-redirect.
  The recovery link already carries a token — reuse it.
- **Conversion:** already known — `OrderLinker` marks the cart `recovered`; join
  the send log to attribute the conversion to the last email step.
- Respect `prefers` and privacy: opens are approximate (image-proxy caches); make
  tracking toggleable and disclosed.

**Settings.** `tracking_opens` (bool), `tracking_clicks` (bool).

**Schema.** A `wp_cart_rebound_email_events` table: `id, cart_id, step, type
(sent|open|click), url, created_at` — or JSON counters on the send log.

**Events.** `cart_rebound_email_opened`, `cart_rebound_email_clicked`.

**Dependencies.** New REST routes under `cart-rebound/v1`. HMAC secret stored in
options.

---

## 4. Advanced analytics

**What it is.** A dedicated analytics screen: recovered revenue over time,
recovery-rate trend, top abandoned products, average time-to-recovery, and the
email-step performance from feature 3 — with date-range filters and CSV export.

**Why it matters.** Free ships a single rate number and lifetime counters. Pro
turns raw events into decisions (which products leak, which step converts, is the
threshold right).

**How it works.**

- Aggregate queries over `wp_cart_rebound_sessions` (+ `email_events`) grouped by
  day/status; decode `cart_contents` to rank abandoned products.
- New REST route `cart-rebound/v1/analytics?from=&to=` returning series + tops.
- A React page (mirror the existing `Dashboard`/`Carts` pages) with a lightweight
  inline SVG chart (no external chart lib — keep the bundle self-contained) and a
  CSV export action.

**Settings.** None required; optional `analytics_retention_days`.

**Schema.** None beyond feature 3. Consider a nightly rollup table
(`wp_cart_rebound_daily_stats`) if row counts get large.

**Dependencies.** Feature 3 for email metrics; works standalone for cart/revenue
metrics.

---

## 5. Early email capture (exit-intent)

**What it is.** Capture the shopper's email _before_ checkout — via an
exit-intent modal, an inline field, or a floating bar — so carts are recoverable
even when the shopper never reaches the checkout email field.

**Why it matters.** Today capture depends on the checkout email input, so a
shopper who bails on the cart/product page is invisible. Widening the captured
pool grows _recoverable_ carts more than any email tweak — you can't recover what
you never captured.

**How it works.**

- A front-end script binds `mouseleave` (desktop) / inactivity + scroll-up
  (mobile) to show a configurable capture UI; posts to the existing
  `cart-rebound/v1/capture` endpoint (already used by the checkout beacon), which
  back-fills identity onto the current cart row via `CartTracker::capture_identity`.
- Frequency-capped per visitor (cookie), suppressed for logged-in users and after
  capture. GDPR consent gate honoured.
- Admin: a small visual editor (headline, offer, styling) rendered from a template.

**Settings.** `capture_enabled`, `capture_trigger` (exit|inline|bar),
`capture_headline`, `capture_offer_coupon`, `capture_frequency_hours`,
`capture_pages` (cart/product/all).

**Schema.** None (reuses identity fields on the sessions table).

**Events.** `cart_rebound_email_captured` action.

**Dependencies.** Front-end asset + consent handling. Reuses the capture route.

---

## 6. Advanced trigger rules

**What it is.** Rules that decide _which_ carts enter recovery: minimum cart
value, exclude user roles (e.g. admins/wholesale), exclude specific
products/categories, and per-rule email/coupon overrides.

**Why it matters.** Focuses recovery effort (and coupons) on carts worth chasing
and cuts noise/false sends — more efficient spend, cleaner list.

**How it works.**

- Evaluate rules in `AbandonmentDetector` before queuing email (add WHERE clauses
  / a post-fetch filter) and in `CartTracker::tracking_allowed()` for
  role/product exclusions at capture time.
- Rules stored as an ordered array in settings; a small rule-matcher service
  keeps it testable.

**Settings.** `min_cart_total`, `excluded_roles[]`, `excluded_products[]`,
`excluded_categories[]`.

**Schema.** None.

**Dependencies.** None.

---

## 7. A/B testing

**What it is.** Split-test subject lines and templates within a step; auto-report
the winner by conversion.

**Why it matters.** Compounds features 1–3 — small subject wins add up across
every send.

**How it works.**

- A step holds N variants; assign a variant per cart deterministically
  (`crc32(cart_id) % N`) and record it on the send log.
- Analytics (feature 4) groups conversion by variant; optionally auto-promote the
  winner after a minimum sample.

**Settings.** Per-step `variants[]`, `ab_min_sample`.

**Schema.** `variant` column on the email-events/send log.

**Dependencies.** Features 1, 3, 4.

---

## 8. SMS / WhatsApp recovery

**What it is.** Send recovery messages over SMS/WhatsApp using the phone already
captured at checkout, as an extra channel or sequence step.

**Why it matters.** SMS open rates dwarf email; a single well-timed text can
out-recover a whole email sequence for some audiences. Premium, high-value.

**How it works.**

- A provider abstraction (`Sms\Provider` interface) with a Twilio (and/or
  WhatsApp Cloud API) driver; credentials in settings.
- A `RecoverySms` sender parallel to `RecoveryMailer`, driven by the same
  Scheduler/sequence engine (a step's `channel` = email|sms).
- Consent + STOP/unsubscribe handling per channel (feature 9 of free = email
  unsubscribe; SMS needs its own opt-out keyword handling).

**Settings.** `sms_enabled`, provider creds, `sms_from`, per-step channel.

**Schema.** Reuses `phone` on the sessions table; add `sms_sent` counters.

**Dependencies.** External provider account; carrier compliance (opt-in, STOP).

---

## 9. Web push recovery

**What it is.** Browser push notifications to bring shoppers back, for visitors
who opted into push.

**Why it matters.** A no-PII channel that reaches shoppers who never gave an
email — extends reach beyond the captured pool.

**How it works.**

- Service worker + push subscription captured on-site; store the subscription
  keyed to the cart session.
- A `RecoveryPush` sender (Web Push protocol / VAPID) as another sequence channel.

**Settings.** `push_enabled`, VAPID keys, template.

**Schema.** `wp_cart_rebound_push_subscriptions` (endpoint, keys, cart_key).

**Dependencies.** HTTPS, service-worker registration, VAPID keys.

---

## Build order & effort

| #   | Feature                        | Impact | Effort | Depends on          |
| --- | ------------------------------ | ------ | ------ | ------------------- |
| 1   | Multi-step email sequence      | ★★★★★  | M      | —                   |
| 2   | Auto-generated unique coupons  | ★★★★☆  | S–M    | WooCommerce coupons |
| 3   | Open/click/conversion tracking | ★★★★☆  | M      | —                   |
| 4   | Advanced analytics             | ★★★☆☆  | M      | 3                   |
| 5   | Early email capture            | ★★★★☆  | M      | capture route       |
| 6   | Advanced trigger rules         | ★★★☆☆  | S      | —                   |
| 7   | A/B testing                    | ★★☆☆☆  | M      | 1, 3, 4             |
| 8   | SMS / WhatsApp                 | ★★★★☆  | L      | provider            |
| 9   | Web push                       | ★★☆☆☆  | L      | service worker      |

**Recommended first release (Pro v1):** 1 + 2 + 3 — the sequence, unique coupons,
and tracking. That trio is the core value multiplier and the clearest upgrade
story. Follow with 5 (early capture) and 4 (analytics), then 6/7, then 8/9.

## New schema summary (cumulative)

| Table / column                                       | For                    |
| ---------------------------------------------------- | ---------------------- |
| `sessions.email_step`, `sessions.next_step_at`       | Sequence position (1)  |
| `sessions.coupon_code`, `sessions.coupon_expires_at` | Unique coupons (2)     |
| `wp_cart_rebound_email_events`                       | Tracking + A/B (3, 7)  |
| `wp_cart_rebound_daily_stats` (optional rollup)      | Analytics at scale (4) |
| `wp_cart_rebound_push_subscriptions`                 | Web push (9)           |

All Pro migrations must be additive and version-gated (write only after the
column/table exists) so pre-migration installs never fatal — the same pattern the
free plugin uses in `Database\Migrations`.

## Free ↔ Pro boundary (do not cross)

Keep these **free** — gating them makes the free plugin non-compliant or too weak
to adopt: cart tracking, the full status lifecycle, one recovery email, the
recovery link, unsubscribe/suppression (legal), basic dashboard stats, privacy
export/erase. Pro is strictly _additive_ on top.
