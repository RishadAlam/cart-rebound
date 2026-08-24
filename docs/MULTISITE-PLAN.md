# Plan — Multisite support

**Status:** not started. Cart Rebound has never been run on a WordPress network, and nothing in the plugin, the readme or the docs claims it is supported.

This is a plan, not a change log. It records what was found by reading the code on 2026-08-24, what actually breaks, and what it would take to close each gap. Nothing here has been tested on a real network — that is step 0.

---

## Where it already stands up

The data model is the right shape for a network, and that is the expensive part to get wrong.

| Concern                                   | Current code                                                                                                                     | Verdict                                                                                                                     |
| ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Table names                               | `$wpdb->prefix . 'cart_rebound_' . …` (`src/Database/Migration.php`, `src/Models/Model.php`)                                     | **Per site.** Each subsite gets its own carts, logs and suppressions. Correct — a store's carts must not leak across sites. |
| Settings, templates, counters             | `get_option()` / `update_option()`, never `get_site_option()`                                                                    | **Per site.** Each store configures itself. Correct.                                                                        |
| Recovery + unsubscribe links              | Built from `home_url()` / `wc_get_checkout_url()`                                                                                | **Per site.** Resolve to the right subsite.                                                                                 |
| Admin menu + REST gating                  | `manage_woocommerce`, checked per request                                                                                        | **Per site.** A shop manager on one subsite gets nothing on another.                                                        |
| Recurring jobs                            | `SchedulerServiceProvider::sync_schedule()` on `init`                                                                            | **Self-arming.** Each subsite schedules its own detector and janitor on its first request. No activation needed.            |
| Missing tables after a skipped activation | `LogServiceProvider::maybe_upgrade()` on `admin_init` re-runs migrations whenever `cart_rebound_db_version` ≠ the plugin version | **Mostly self-healing.** A subsite that never saw an activation hook builds its tables on the first wp-admin page load.     |

That last row matters, and it is easy to miss: **a network-activated Cart Rebound does not stay permanently broken on the other subsites.** It repairs itself the first time somebody opens wp-admin there.

---

## What actually breaks

### 1. A subsite's storefront can run before its tables exist

**Severity: high.** The self-heal is hooked on `admin_init`, so it only fires inside wp-admin. A subsite that takes front-end traffic before any administrator loads its dashboard runs `CartTracker::upsert()`, `SessionManager`, and the capture endpoint against tables that do not exist yet. Every one is a `$wpdb` error — silent unless `WP_DEBUG` is on — and every cart in that window goes untracked.

This is not strictly a multisite bug. It is the same hole on a single site if activation is ever bypassed (a `wp plugin activate --skip-plugins`, a migration that half-ran, a restore from a database dump taken before the tables existed). Multisite just makes it the normal case rather than the rare one.

**Fix:** move the version gate off `admin_init` so it runs for front-end requests too, while keeping it cheap.

- Hook the check on `plugins_loaded` (or the tail of `Application::bootstrap()`) rather than `admin_init`.
- Keep the guard a single `get_option()` string compare — it already is — so the cost on a warm site is one autoloaded option read.
- Add a short-lived lock (a transient, or `wp_cache_add`) around `Migrator::run()` so a burst of concurrent front-end requests on a cold subsite cannot run `dbDelta` in parallel.
- Have the tracker fail closed rather than erroring: if the version gate has not completed, `CartTracker::track()` should return early instead of querying.

**Verification:** on a network, create a subsite, activate nothing by hand, hit its storefront with a product add-to-cart before ever opening its wp-admin, and confirm a row appears and no `$wpdb` error is logged.

---

### 2. Network activation only migrates one site

**Severity: medium** (given the self-heal above, this is a latency problem rather than a data problem).

`Plugin::activate()` (`src/Core/Plugin.php:39`) takes no parameters. WordPress passes `$network_wide` to that callback and fires it **once** — the plugin is expected to loop the network itself. So a Network Activate builds tables for whichever blog is current and leaves the rest to the self-heal.

**Fix:**

```php
public static function activate( bool $network_wide = false ): void { … }
```

- When `$network_wide && is_multisite()`, iterate `get_sites( [ 'fields' => 'ids', 'number' => …, 'offset' => … ] )` in batches, `switch_to_blog()` → migrate → `restore_current_blog()`.
- Batch it. A network with thousands of sites must not try to migrate all of them inside one activation request; page through, and fall back to the self-heal for anything not reached before the request budget runs out.
- Keep the single-site path exactly as it is today.

---

### 3. New subsites are never provisioned

**Severity: low** (again, the self-heal covers it on first admin load — but not before front-end traffic, per gap 1).

There is no `wp_initialize_site` hook, so a site created after activation is provisioned only by whichever fallback fires first.

**Fix:** hook `wp_initialize_site` (priority after WordPress finishes creating the site's tables), `switch_to_blog()`, run the migrator, restore. Guard on the plugin being network-active.

---

### 4. Network uninstall leaves every other subsite's data behind

**Severity: high — this one is genuinely broken and has no self-heal.**

`Plugin::uninstall()` drops `$wpdb->prefix` tables and calls `delete_option()` for the current blog only. The no-autoloader fallback in `uninstall.php` does the same. On a network uninstall, every subsite except one keeps its three tables and six options forever. The plugin is otherwise careful about leaving nothing behind — a manual uninstall run during QA confirmed all three tables and all six options go on a single site — so this is the one place the contract is broken.

**Fix:** both paths need the same network loop.

- In `Plugin::uninstall()`, when `is_multisite()`, page `get_sites()` and repeat the drop + option deletion per site under `switch_to_blog()`.
- Mirror it in the `uninstall.php` fallback branch, which cannot use the container and must do it with raw `$wpdb` calls.
- Also clear scheduled actions per site — `Scheduler::clear()` targets the current site's Action Scheduler store.

**Verification:** network with three subsites, data on each, Network Deactivate → Delete, then confirm no `*_cart_rebound_*` table and no `cart_rebound_*` option survives on any site.

---

### 5. Activation `wp_die()`s when WooCommerce is not network-active

**Severity: medium.**

`Plugin::activate()` calls `wp_die()` if `Requirements::has_woocommerce()` is false. Under a Network Activate that check runs in the network-admin context: if WooCommerce is active per site rather than network-wide, the activation hard-stops with a white screen instead of activating where it can.

**Fix:** on a network-wide activation, skip sites without WooCommerce rather than dying — provision the ones that have it, and surface the rest through the existing `Requirements::render_admin_notice()` on each affected subsite. Keep the `wp_die()` for a single-site activation, where it is the right, loud behaviour.

---

### 6. The tracking cookie may be shared across subdomains

**Severity: low, needs measurement before any change.**

`SessionManager::cookie_options()` sets `cart_rebound_ref` on `COOKIEPATH`. On subdirectory multisite that is `/site/`, so cookies scope per site cleanly. On **subdomain** multisite with a shared `COOKIE_DOMAIN`, the same key value would be sent to every subsite.

The consequence is probably benign — each site has its own `wp_N_cart_rebound_sessions`, so a key from another site simply matches no row and a fresh one is created. But it should be measured rather than assumed, because it also means one site's tracking identifier is readable on another.

**Fix, if measurement confirms it matters:** namespace the cookie per site (`cart_rebound_ref_{blog_id}`) or set an explicit path/domain rather than inheriting `COOKIEPATH`.

---

## Suggested order

Do them in this order — each step is independently shippable, and the first two are worth doing regardless of whether multisite is ever supported, because they harden the single-site case too.

| #   | Work                                                                                           | Multisite-only?                                              |
| --- | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| 1   | Move the version gate off `admin_init`; add the concurrency lock; make the tracker fail closed | No — fixes a real single-site hole                           |
| 2   | Network-aware uninstall, both code paths                                                       | No — the contract is broken either way once a network exists |
| 3   | `activate( $network_wide )` with a batched site loop                                           | Yes                                                          |
| 4   | `wp_initialize_site` provisioning                                                              | Yes                                                          |
| 5   | Skip-not-die when WooCommerce is missing on a subsite                                          | Yes                                                          |
| 6   | Measure the cookie scope on subdomain multisite; namespace it if needed                        | Yes                                                          |

---

## Before any of it: build the test bed

None of the above can be called done from reading code. Stand up a real network first:

```bash
wp core multisite-convert --subdomains=false      # or a fresh --subdomains install
wp site create --slug=store-a
wp site create --slug=store-b
wp plugin activate woocommerce --network          # and once per-site, to test both shapes
wp plugin activate cart-rebound --network
```

Then check, per subsite:

```bash
wp db query "SHOW TABLES LIKE '%cart_rebound%'" --url=store-a.example.test
wp option get cart_rebound_db_version --url=store-a.example.test
wp eval 'echo (int) as_next_scheduled_action( "cart_rebound_scan_abandoned", [], "cart-rebound" );' --url=store-a.example.test
```

The cases that matter, each driven end to end:

- Network Activate with WooCommerce network-active, and again with WooCommerce active per site only.
- Per-site activation on one subsite while the others stay off.
- A subsite created **after** activation, whose storefront is hit before its wp-admin ever is.
- A cart abandoned and recovered on `store-a` while `store-b` has its own cart — confirm neither list, dashboard, log nor recovery link crosses over.
- Network Deactivate, then Delete — confirm nothing survives anywhere.

---

## Decide first: is this in scope at all?

Worth answering before any code is written. Multisite support means a permanent test matrix and a second set of upgrade paths to keep honest. Cart Rebound is a per-store plugin; a network of stores is a real but narrow audience.

If the answer is no, the honest move is cheaper than the fix: say so in `readme.txt`, and still do items **1** and **2** from the table above, since both are single-site correctness issues that merely happen to surface first on a network.
