# Cart Rebound — Illustrated Manual

A screen-by-screen walkthrough of Cart Rebound for store owners and shop managers. Every screenshot in this manual was captured from a live WooCommerce store running the current build.

Looking for the API reference, hook payloads, or the internals of tracking and order linking? That lives in [USAGE.md](USAGE.md). This document is the operator's guide: what each screen does, what to click, and what the plugin does in response.

---

## Table of Contents

1. [What Cart Rebound does](#what-cart-rebound-does)
2. [First run: the setup wizard](#first-run-the-setup-wizard)
3. [Dashboard](#dashboard)
4. [Carts](#carts)
    - [The cart list](#the-cart-list)
    - [What the statuses mean](#what-the-statuses-mean)
    - [Cart details](#cart-details)
    - [Sending a recovery email by hand](#sending-a-recovery-email-by-hand)
    - [Attributing a cart to an order](#attributing-a-cart-to-an-order)
    - [Bulk actions](#bulk-actions)
5. [Templates](#templates)
    - [Message](#message)
    - [Merge tags](#merge-tags)
    - [Previewing and testing](#previewing-and-testing)
    - [Product table](#product-table)
    - [Delivery](#delivery)
6. [Log](#log)
7. [Settings](#settings)
8. [What the shopper sees](#what-the-shopper-sees)
    - [The recovery link](#the-recovery-link)
    - [Unsubscribing](#unsubscribing)
9. [The recovery lifecycle end to end](#the-recovery-lifecycle-end-to-end)
10. [Accessibility and localisation](#accessibility-and-localisation)
11. [Troubleshooting](#troubleshooting)

---

## What Cart Rebound does

Cart Rebound watches every WooCommerce cart on your store. When a cart sits untouched past a threshold you choose, it is marked **abandoned** and — if you enable it — the shopper receives one email containing a one-click link that rebuilds their cart and reopens checkout.

When that shopper pays, the order is linked back to the cart it came from and the money is counted as **recovered revenue**. Nothing is guessed: orders are matched by explicit order meta and a session binding, never by comparing totals.

Everything is self-hosted. No external service, no account, no data leaving your site.

**Requirements:** WordPress 6.2+, PHP 7.4+, and an active WooCommerce install. Cart Rebound declares `Requires Plugins: woocommerce`, so WordPress blocks activation without it.

---

## First run: the setup wizard

The first time you open **Cart Rebound**, a four-step wizard collects the handful of choices that matter. Everything it asks can be changed later under **Settings**, and **Skip** dismisses it for good.

![The Cart Rebound setup wizard, showing step 1 of 4](images/onboarding-wizard.png)

| Step           | Question                                                    | Why it matters                                                                          |
| -------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| 1. Welcome     | —                                                           | Summarises what the plugin will do.                                                     |
| 2. Guest carts | Track logged-out shoppers?                                  | Most abandonment happens before an account exists. Recommended for most stores.         |
| 3. Timing      | How long can a cart sit idle before it counts as abandoned? | 30–60 minutes suits most stores. Too short and you email people who are still shopping. |
| 4. Email       | Send one recovery email, and how long after?                | Turning this off keeps tracking and reporting while sending nothing.                    |

The wizard writes the same settings the **Settings** tab does — there is no separate "onboarding" state to get out of sync.

---

## Dashboard

**Cart Rebound → Dashboard** is the reporting view.

![The Cart Rebound dashboard: overview metrics, a revenue chart, recent activity and a product report](images/dashboard.png)

**Overview** is lifetime and current-state, not a time window:

| Metric              | What it counts                                                                               |
| ------------------- | -------------------------------------------------------------------------------------------- |
| Recoverable orders  | Carts sitting in **Abandoned** right now — still open to recovery.                           |
| Recovered orders    | Carts that were abandoned and came back and paid.                                            |
| Lost orders         | Abandoned carts cleaned up without converting, plus paid orders later refunded or cancelled. |
| Recoverable revenue | Total value of the carts currently in **Abandoned**.                                         |
| Recovered revenue   | Paid order value won back from recovered carts.                                              |
| Recovery rate       | Share of all abandoned carts ever recorded that were recovered.                              |

The recovery rate runs off purge-immune lifetime counters rather than the rows still in the table. That is deliberate: the daily cleanup job deletes old unconverted carts, and counting live rows would make the rate drift upward every time the store tidied itself. It also means the rate will not equal _recovered ÷ recoverable_ from the cards beside it — those two are a snapshot, the rate is the whole history.

Each metric carries an ⓘ that repeats its definition, so nobody has to come back to this table.

**Revenue over time** plots abandoned value against recovered value per day over 7, 30 or 90 days. **Recent activity** is the newest six tracked carts, and **Product report** ranks which products get abandoned most over the selected range, with how many of each came back.

---

## Carts

### The cart list

**Cart Rebound → Carts** is every tracked cart, filterable by status and sortable on any column.

![The Carts list with its status filter, sortable columns and per-row actions](images/carts-list.png)

- **Status filter** narrows the table to one status; the count on the right always reflects the filter.
- **Every column header sorts.** Click once for ascending, again for descending.
- **Rows per page** offers 10 / 20 / 30 / 50 / 100 (20 by default).
- The **Order** column links straight to the WooCommerce order a converted cart produced.

Each row carries up to four actions:

| Icon | Action                                    | Disabled when                                                   |
| ---- | ----------------------------------------- | --------------------------------------------------------------- |
| 👁    | View cart details                         | never                                                           |
| 🔗   | Mark this cart recovered against an order | the cart already has an order                                   |
| ✉    | Send the recovery email now               | no email captured, cart is empty, or the cart already converted |
| 🗑    | Delete this cart                          | never                                                           |

Hovering a disabled action tells you exactly why it is disabled rather than leaving you guessing.

### What the statuses mean

The **What do these statuses mean?** panel above the table explains the lifecycle without leaving the page.

![The expanded status guide, explaining each status and the flow between them](images/cart-status-guide.png)

| Status              | Meaning                                                                                                                 |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Active**          | Shopper is still building their cart — no order placed yet.                                                             |
| **Abandoned**       | Idle past the threshold; a recovery email may be scheduled.                                                             |
| **Pending payment** | Order placed but not paid yet (cheque, bank transfer, a pending gateway). Items are kept and no recovery email is sent. |
| **Recovered**       | An abandoned cart that came back and paid — a recovery win.                                                             |
| **Completed**       | Converted to a paid order without ever being abandoned.                                                                 |
| **Lost**            | Abandoned and cleaned up, or a paid order later refunded or cancelled.                                                  |

A cancelled or failed order returns the cart to **Active** with its items kept, so it can be recovered like any other. A refund moves a converted cart to **Lost** and clears its recovered revenue, so reporting never counts money that went back.

You can also override a status directly from the coloured select in each row — useful for correcting an attribution by hand.

### Cart details

Clicking the eye icon (or the cart's ID) opens the full record: who the shopper is, what was in the cart, and when each lifecycle event happened.

![The cart detail dialog showing customer, line items and a timeline](images/cart-detail.png)

The **Timeline** shows Created, Abandoned, Recovered and Last activity as they apply. Line items are the snapshot captured at tracking time, which is what the recovery link rebuilds.

### Sending a recovery email by hand

The ✉ action sends the recovery email immediately, using whichever template you pick.

![The send recovery email dialog with a template picker](images/send-recovery-email.png)

Manual sends skip the scheduling rules — the enabled toggle, the abandoned-only rule and the already-sent flag — so you can re-send at will. They still refuse to mail:

- a cart with no valid email address,
- a cart with nothing in it,
- a cart already linked to an order,
- an address that has unsubscribed.

If a send is refused or your mail transport fails, the reason appears inside the dialog itself so you see it before dismissing the box.

### Attributing a cart to an order

Sometimes an order and its cart never get linked automatically — a manual order, an unusual gateway, a store that was mid-migration. The 🔗 action links them by hand.

![The mark cart recovered dialog, with a recent-order picker and a manual order ID field](images/mark-recovered.png)

Pick from recent orders or type an order ID. The link is refused if the cart already has an order, if the order does not exist, or if the order is not in a paid status — so this cannot be used to invent revenue.

### Bulk actions

Tick any number of rows to reveal the bulk bar.

![The bulk action bar after selecting several carts](images/bulk-actions.png)

**Set status…** applies one status to every selected cart. **Delete** removes them after a confirmation. **Clear** drops the selection.

---

## Templates

**Cart Rebound → Templates** is where the recovery email is written. You can keep several templates; the one marked **Default** is what automatic abandonment emails use, and any of them can be chosen for a manual send.

### Message

![The template editor: name, subject, and a rich-text body with a merge-tag inserter](images/templates-editor.png)

- **Template name** is internal — only you see it.
- **Subject** accepts merge tags too. `{first_name}` and `{coupon_code}` are the useful ones.
- **Body** has a **Visual** and an **HTML** view. The toolbar covers bold/italic/underline/strikethrough, text and highlight colour, two heading levels, paragraph and quote, alignment, bullet and numbered lists, links, images from the media library, and a horizontal rule.
- **Insert tag…** drops a merge tag at the cursor.
- A **Complete your order** button is appended automatically below your body — you do not need to build the call to action yourself.

The save bar stays within reach at the bottom of the panel and tells you whether changes are pending. Switching template with unsaved edits warns first.

### Merge tags

The **What each of the 19 merge tags becomes** panel documents every placeholder in place.

![The merge tag reference, listing all 19 tags and what each renders](images/merge-tags.png)

| Group   | Tags                                                                                              |
| ------- | ------------------------------------------------------------------------------------------------- |
| Shopper | `{first_name}` `{last_name}` `{full_name}` `{email}`                                              |
| Cart    | `{products}` `{products_table}` `{product_names}` `{items_count}` `{cart_total}` `{abandoned_on}` |
| Links   | `{recovery_url}` `{checkout_url}` `{unsubscribe_url}`                                             |
| Offer   | `{coupon_code}`                                                                                   |
| Store   | `{store_name}` `{store_url}` `{store_email}` `{manager_name}` `{current_year}`                    |

`{recovery_url}` is the one that matters most: it is the tokenised, one-click link that rebuilds the shopper's cart.

### Previewing and testing

**Preview email** renders the template with representative sample data — a shopper named "Jordan" and two demo items — so you see exactly what lands in an inbox, sender line included.

![The email preview dialog rendering the template with sample data](images/email-preview.png)

**Send test** mails that same sample render to any address you type, which is the fastest way to check how a real client renders it.

### Product table

The **Product table** tab controls what `{products_table}` produces. Left off, the tag renders product, quantity and line total on ruled rows.

![The product table options: column picker, layout and row detail toggles](images/product-table-options.png)

Turn on **Lay the table out myself** to choose:

- **Columns** — which columns appear and in what order (left to right, in the order you add them).
- **Table style** — ruled rows, boxed, or no rules.
- **Thumbnail size** — used once the Thumbnail column is on.
- **Show column headings** — off gives a bare list of rows.
- **Prices include tax**, **link the product**, **show the chosen variation**, **close with a cart total row**.
- **Rows before "and N more"** — keeps a 30-item cart from becoming a 30-row email. `0` lists everything.

Every value is clamped server-side, so an unknown column or style can never reach the renderer.

### Delivery

![The delivery tab: sender name, sender address and coupon picker](images/template-delivery.png)

- **From name** / **From email** — leave blank to use the WordPress default sender. Use an address on your own domain so the mail is not treated as spoofed.
- **Coupon** — pick an existing WooCommerce coupon and `{coupon_code}` prints it. Cart Rebound never generates new coupons; it only prints codes you already created.

---

## Log

**Cart Rebound → Log** is the audit trail: every abandonment, every email sent, every recovery.

![The activity log with level, event and cart filters](images/activity-log.png)

Filter by **Level** (info / success / warning / error), by **Event** (Emails sent / Abandoned / Recovered), or by a specific **Cart ID**. The filters compose, and the entry count on the right always reflects them.

**Clear log** empties it after a confirmation.

![The activity log after being cleared, showing its empty state](images/activity-log-empty.png)

The log is the first place to look when recovery is not behaving: if a cart never appears with an _Abandoned_ entry it was never eligible, and if an _Emails sent_ entry is missing the message never left WordPress.

---

## Settings

**Cart Rebound → Settings** holds everything the wizard asked plus the retention and attribution controls.

![The settings screen: tracking, abandonment and cleanup, and recovery email sections](images/settings.png)

**Tracking**

- **Track guest carts** — capture carts and the email guests type at checkout, not just logged-in customers.

**Abandonment & cleanup**

| Field                                       | What it does                                                     |
| ------------------------------------------- | ---------------------------------------------------------------- |
| Abandonment threshold (minutes)             | Idle time before a cart is abandoned.                            |
| Scan interval (minutes)                     | How often the detector runs.                                     |
| Cleanup after (days)                        | Unrecovered carts are purged after this.                         |
| Converted cart retention (days)             | Recovered and completed carts are purged after this.             |
| Count a cart as recovered when its order is | Which order statuses mark a tracked cart as paid and attributed. |

The threshold lives in the query, not the schedule, so changing it takes effect on the very next scan with no rescheduling.

The paid-status list is checked against the statuses WooCommerce actually registers, so a stale or mistyped status cannot quietly sit in your settings and stop attribution.

**Recovery email**

| Field                    | What it does                                                     |
| ------------------------ | ---------------------------------------------------------------- |
| Send recovery email      | Schedules a single follow-up per abandoned cart.                 |
| Notify admin on recovery | Emails the store whenever a tracked cart converts.               |
| Notification email       | Where those notifications go; blank uses the site admin address. |
| Send delay (minutes)     | Wait time after abandonment before sending.                      |

Email _content_ is not here — subject, body, sender and coupon are per template on the **Templates** tab.

---

## What the shopper sees

### The recovery link

`{recovery_url}` carries an unguessable token. Clicking it rebuilds the cart and drops the shopper straight onto checkout with their details already filled in.

![Checkout reopened from a recovery link, with the cart rebuilt and contact details prefilled](images/recovery-link-checkout.png)

What the link does, in order:

1. Looks up the cart by its token. A token that does not match an open cart does nothing at all — no error page, no redirect.
2. Adopts that cart into the visitor's tracking session, so returning does not spawn a second, duplicate cart record.
3. Empties the current cart and re-adds every stored line, preserving quantities and variations.
4. Re-applies the stored coupons — skipping any code that has since been deleted, so the shopper is not greeted by a coupon error.
5. Fills in the email, name and phone that were captured, leaving anything the shopper already has untouched.
6. Binds the cart to the session so the resulting order is attributed as **recovered via email link**.
7. Redirects to checkout.

The token is the only credential in the URL. The session key is never exposed.

### Unsubscribing

Every recovery email carries an unsubscribe link. It opens a confirmation rather than acting on the click, so a scanner or a mis-tap cannot silently opt someone out.

![The unsubscribe confirmation page](images/unsubscribe-confirm.png)

Confirming suppresses that address permanently.

![The unsubscribed confirmation page](images/unsubscribe-done.png)

Suppression is checked both when the scheduled email fires and when you send one by hand, so an unsubscribed address cannot be mailed from anywhere in the plugin.

---

## The recovery lifecycle end to end

```
   shopper adds items
           │
           ▼
      ┌─────────┐   idle past threshold        ┌───────────┐
      │ Active  │ ──────────────────────────▶  │ Abandoned │
      └─────────┘   (has email + items)        └───────────┘
           ▲                                          │
           │  shopper returns                         │  recovery email
           └──────────────────────────────────────────┤  (once, after the delay)
                                                      │
                                                      ▼
                                             ┌─────────────────┐
                        order placed ──────▶ │ Pending payment │
                                             └─────────────────┘
                                                      │ order paid
                        ┌─────────────────────────────┴───────────────┐
                        ▼                                             ▼
                  ┌───────────┐                                 ┌───────────┐
                  │ Recovered │  was abandoned, or arrived       │ Completed │  never abandoned
                  └───────────┘  via the recovery link          └───────────┘
                        │                                             │
                        └──────── refunded / cancelled ──────▶ ┌──────┴──┐
                                                               │  Lost   │
                                                               └─────────┘
```

Two rules are worth committing to memory:

**Only paid orders count.** An order awaiting payment sits in **Pending payment**. It stops the recovery email — the shopper has already ordered — but it does not count as recovered revenue until the money actually arrives.

**Coming back counts, however you come back.** A cart that was ever abandoned and later converts is **Recovered**, whether the shopper clicked the emailed link or simply returned to the store on their own. Only a cart that was never abandoned at all converts as **Completed**. The order note records which route it took: _via email link_ or _via direct return_.

---

## Accessibility and localisation

Cart Rebound's admin screens score **100 on Lighthouse accessibility**. Every status badge, chip, link and button clears the WCAG AA contrast minimum, each icon-only action carries a label, and the loading shimmer stops for anyone who has asked their system for reduced motion.

The plugin is fully translatable — 495 strings, including everything inside the React admin — and its layout mirrors correctly in right-to-left languages without a separate stylesheet.

---

## Troubleshooting

**Carts are not being tracked at all.**
Confirm WooCommerce is active. If the carts are from logged-out visitors, check **Track guest carts** is on.

**A cart never becomes abandoned.**
A cart is only eligible once it has a captured email address and at least one item, and only after it has been idle past the threshold. Carts with no email are never abandoned — they are purged later as stale active rows. Check the **Log** for the cart id.

**No email arrives.**
Look for an _Emails sent_ entry in the **Log**. If it is there, the message left WordPress and the problem is your mail transport — install an SMTP plugin and use **Send test** on the Templates tab to confirm. If it is not there, the cart was ineligible at send time: it converted, it was already emailed, or the address unsubscribed.

**A send fails silently.**
It does not. The reason is shown inside the send dialog. If the message is _"WordPress could not send the email…"_ the failure is in your mail transport, not in Cart Rebound.

**A recovered order was recorded as Completed.**
Check the order note on the WooCommerce order. If it reads _"linked to tracked cart #N (completed)"_ the cart genuinely never reached **Abandoned** — usually because the threshold is longer than the shopper's pause.

**Revenue looks wrong after a refund.**
That is intended. Refunding or cancelling a converted order moves the cart to **Lost** and zeroes its recovered revenue.

**The recovery rate does not match the cards above it.**
It is not meant to. The cards are a live snapshot; the rate is the plugin's whole history, measured with counters the cleanup job cannot touch.
