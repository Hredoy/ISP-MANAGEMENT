# Claude Handoff

## Current Branch

- Branch: `main`
- Working tree has uncommitted changes (see `git status`). Some of these predate this
  session (e.g. `TenantFrontendController`, `TenantWebsiteController`, `Tenant*` frontend
  models, `resources/js/Pages/Tenant/*`, `resources/js/Pages/Tenant/FrontendAdmin.vue`,
  `resources/js/Layouts/Nevigations/VerticalNavigation.js`, `package-lock.json`/`package.json`
  churn from an earlier "phase 2" commit) — **not** authored in this session, listed here only
  so the next session doesn't confuse them with the work below.
- Nothing in this session has been committed. Review with `git diff` / `git status` before
  committing; consider splitting into logical commits (MikroTik review, tenant session/login
  fix, Sprint 1 Day 2 client/package management) rather than one giant commit.

## Stack Reminder

Vue 3 + Inertia.js end-to-end. **No React anywhere in this app.** If a future spec/prompt says
"React" or "TanStack Table", it almost certainly means "use `@tanstack/vue-table`" (now
installed) — confirm before introducing a second frontend framework.

## Session Summary

This session covered four mostly-independent threads, in order:

### 1. MikroTik service review (`app/Services/MikroTikService.php`, `MikrotikController`)

- Verified `evilfreelancer/routeros-api-php` is installed and `MikroTikService` already
  implements `connect()`, `testConnection()`, `getPPPoEUsers()`, `getActiveSessions()`,
  `addPPPoEUser()`, `removePPPoEUser()`, `suspendUser()`, `unsuspendUser()`, `getIPPools()`,
  `getQueues()`, `getHotspotUsers()`, and auto-sync of PPPoE users → `clients` table on first
  connect. Credentials are encrypted via `Crypt::encryptString()` in `App\Models\Mikrotik`
  attribute casts.
- **Fixed a real security bug**: `MikrotikController::store()` inserted new routers via a raw
  `DB::connection('tenant')->table('mikrotiks')->insert()`, bypassing the model's encrypted
  casts entirely — passwords were saved in **plaintext** for the tenant-connection path, which
  is the path taken on every real request (tenant middleware requires `tenancy()->initialized`).
  Fixed to use `Mikrotik::on('tenant')->create(...)` so encryption applies. Regression test
  added in `tests/Feature/MikrotikRouterTest.php` (asserts stored password isn't plaintext) —
  verified it actually catches the regression by reverting the fix and watching it fail.
- Fixed `store()` ignoring the router's custom port when test-connecting (always tested on
  8728 regardless of user input).
- Removed a dead route: `GET /dashboard/mikrotik/{mikrotik}/monitor` pointed at
  `MikrotikController::monitor()`, a method that never existed and no frontend page calls.

### 2. Tenant session/cookie/cache isolation + broken tenant admin login

Root cause #1 — **middleware order** (`bootstrap/app.php`): `SetTenantDatabase` (which calls
`tenancy()->initialize()`) was `append`ed to the `web` middleware group, running **after**
`StartSession`, `VerifyCsrfToken`, and `SubstituteBindings`. This meant:
- Session storage for cache-based drivers (redis/memcached/etc.) resolved its store instance
  *before* tenancy swapped in the tenant-scoped `CacheManager` — sessions were never actually
  tenant-tagged (confirmed by reading `vendor/stancl/tenancy/src/Bootstrappers/CacheTenancyBootstrapper.php`:
  it swaps the container's `cache` binding via `Container::extend()`, which doesn't retroactively
  fix an already-resolved handler instance in the same request).
- Route-model binding (`SubstituteBindings`) resolved against the **central** DB connection
  instead of the tenant one.

  **Fix**: `bootstrap/app.php` now `prepend`s `SetTenantDatabase` so it's the very first
  middleware in the `web` group. `EnsureTenantIsActive` stays appended (it calls
  `auth()->guard('web')->logout()`, which needs the session to already exist).

Root cause #2 — **`SESSION_DOMAIN` hardcoded to `.isp-management.test`** in local `.env`.
Tenants can have **custom domains** (e.g. `vortexbytz.com`, `next.com` — see `domains` table)
distinct from `*.isp-management.test`. A cookie with `Domain=.isp-management.test` set from a
response served on `vortexbytz.com` is invalid per RFC 6265 and every browser silently discards
it — proved directly with `curl --resolve` (empty cookie jar despite `Set-Cookie` headers being
sent). No cookie ever gets stored → next request's CSRF token never matches → **guaranteed 419
"Page Expired"** on every login attempt via a custom domain. Fixed by setting
`SESSION_DOMAIN=null` in `.env` (already the correct default in `.env.example`; local `.env` had
drifted). Verified end-to-end with `php artisan serve` + `curl --resolve` (no hosts-file changes
needed) — cookies now store correctly on both the tenant subdomain and the custom domain, and a
full login → dashboard round trip succeeds on both, including when run **concurrently**.

Added `tests/Feature/TenantAuthenticationTest.php`: creates a tenant, logs in as its admin over
one request, then makes an independent follow-up GET to confirm the session actually persists
(not just in-PHP-memory state within a single request).

**Known gap**: this was validated with PHPUnit's `array` session/cache driver (test env
default) plus manual `curl`/redis verification, not an automated redis-backed test — `array`
driver can't reproduce the cache-manager-swap timing bug since it doesn't go through the `Cache`
facade at all. If regressions show up in production specifically with `SESSION_DRIVER=redis`,
re-verify with real redis rather than trusting the test suite alone.

### 3. Dead resource routes (found while fixing #1 and #2)

`Route::resource(...)` registers all 7 REST routes regardless of which methods the controller
actually implements. Found three controllers with routes pointing at nonexistent methods (500 if
ever visited):
- `dashboard.packages.show` → `PackageController` had no `show()`. **Fixed**: added
  `->except('show')` to the route registration (no product need for a package detail page).
- `dashboard.clients.show` → `ClientController` had no `show()`. **Fixed** the same way.
- `dashboard.zones.*` and `dashboard.sub-zones.*` — `ZoneController`/`SubZoneController` only
  implement `index`/`store`/`destroy`, but routes register all 7. **Not fixed** — out of scope
  for this session, flagged as a background task chip (`task_e584310a`) for a follow-up session.
  Check whether zones/sub-zones actually need create/edit/show pages or just need
  `->except([...])` added to match reality.

### 4. Sprint 1 · Day 2 · MikroTik — Client + Package management

Spec asked for React + TanStack Table + `/api/*` JSON endpoints. Confirmed with the user this
app is Vue+Inertia only — agreed direction: **stay in Vue, use `@tanstack/vue-table`**, extend
the existing Inertia `dashboard.clients.*` / `dashboard.packages.*` routes rather than building a
separate REST API layer.

Backend:
- `database/migrations/tenant/2026_07_01_000005_add_soft_deletes_to_clients_table.php`: adds
  `deleted_at` + indexes on `status`/`package_name`/`full_name`. Applied to both existing local
  tenant DBs (`tenant_next-qbcrwi`, `tenant_vortexbytz-j7jcqw`) — **still needs to run on any
  other tenant DB** via `php artisan tenants:migrate` or per-tenant `migrate --database=tenant`.
- `App\Models\Client`: added `SoftDeletes`, `search()`/`filter()` query scopes, and an
  `effective_status` accessor. Status vocabulary is now **Active / Suspended / Expired**
  (previously inconsistent Active/Inactive). `Suspended` is only ever set explicitly (via the
  new suspend endpoint, mirroring the MikroTik PPPoE `disabled` flag). `Expired` is **computed**
  from `expiry_date` at read time, not stored — no background job flips it. Also updated
  `MikroTikService::syncPPPoEUsersToClients()` to use `Suspended` instead of the old `Inactive`.
  ⚠️ `expiry_date` deliberately has **no** Eloquent cast (kept as a raw `YYYY-MM-DD` string) —
  adding a `date` cast breaks the `<input type="date">` v-model binding in Create/Edit because
  Eloquent's default JSON serialization for date casts is full ISO-8601, not `YYYY-MM-DD`. The
  `effective_status` accessor does its own `Carbon::parse()` internally instead of relying on a
  cast.
- `MikroTikService::updatePPPoEUser()` added (find-by-username, then `/ppp/secret/set`), used by
  `ClientController::update()` so it no longer hand-rolls RouterOS queries.
- `ClientController`: `index()` is paginated (Laravel `paginate()`, `withQueryString()`),
  searchable (name/pppoe_username/phone via `LIKE`), filterable (zone/status/package), sortable
  (whitelisted columns only). `store()` hard-fails (no DB record created) if MikroTik
  provisioning throws — intentional, don't want phantom clients with no real PPPoE secret.
  `update()`/`destroy()` soft-fail (DB still updates/soft-deletes, just flashes a
  router-unreachable warning) — intentional, matches the pre-existing `destroy()` philosophy of
  not blocking local record management on router availability. `suspend()`/`unsuspend()`
  **hard**-fail on router-unreachable — intentional and safety-motivated: don't want the DB to
  claim a customer is suspended when the router never actually cut them off.
- `PackageController`: added `edit()`/`update()` (were missing — see dead-routes section above).
- Routes: added `POST dashboard/clients/{client}/suspend` and `.../unsuspend`.

Frontend:
- Installed `@tanstack/vue-table` and `@tanstack/vue-virtual` (`npm install ... --legacy-peer-deps`
  — this repo has a pre-existing `vite@7` vs `@vitejs/plugin-vue@5` peer conflict unrelated to
  these packages; plain `npm install` fails without `--legacy-peer-deps`, that's not new).
- `resources/js/Pages/Clients/Index.vue`: rebuilt using TanStack Table (manual/server-driven
  sorting — click a sortable header to trigger an Inertia partial reload with `sort`/`direction`
  query params, not client-side re-sorting of the current page) + `@tanstack/vue-virtual` row
  virtualization inside a scrollable container. Debounced search input, zone/status/package
  filter selects, prev/next pagination, status badges (Active=primary, Suspended=yellow,
  Expired=red), suspend/unsuspend/edit/delete row actions.
  - Design note: true 10k+ row scale is handled by **server-side pagination**, not by loading
    all 10k rows into the browser and virtual-scrolling them — that's the standard/correct
    approach for a searchable/filterable dataset at that scale. Virtualization is there so a
    given page (even a large one) renders smoothly.
- `resources/js/Pages/Clients/Create.vue` / `Edit.vue`: package dropdown was hardcoded
  (`['5Mbps', '10Mbps', '20Mbps', 'Starter_Pack']`) — now pulled from the real `packages` table,
  filtered to whichever router is selected, and auto-fills `monthly_bill` from the package price
  on selection. Removed the `status` field from the Create form (new clients are always created
  `Active`; suspension happens via the dedicated action, not a create-time toggle). Edit form's
  status select is now `Active`/`Suspended` only (`Expired` is computed, not selectable).
- `resources/js/Pages/Packages/Edit.vue`: new file, since `PackageController::edit()` now
  exists. Added an edit (pencil) icon link on `Packages/Index.vue` cards.

Tests: `tests/Feature/ClientManagementTest.php` (new) — index search/filter/sort/pagination,
store + PPPoE provisioning (mocked `MikroTikService`), store failure when router unreachable
(asserts no DB record created), destroy soft-delete + PPPoE removal, suspend, unsuspend, package
update. All use the same tenant-DB-reconnect-after-each-request pattern as the existing
`MikrotikRouterTest`/`TenantAuthenticationTest` (see "Testing tenant-scoped features" below).

### 5. Sprint 1 · Day 2 · MikroTik — One-click client provisioning + OLT + SMS Integrations

Spec asked for a single form that runs 6 steps in one click (DB record → PPPoE → OLT/ONU
bind → bandwidth queue → expiry → welcome SMS) with per-step loading/error/retry UI. None
of steps 3/4/6 existed anywhere in the app before this session — no OLT model/driver, no
bandwidth-queue creation (only profile-level rate-limit), and `SmsService` was a log-only
stub. User explicitly asked for **real multi-vendor OLT integration** (Huawei, ZTE, VSOL)
and a **real Integrations panel** with multiple togglable SMS gateways, not a single
hardcoded stub — see the full plan at the time in this session's plan-mode output if you
need the original reasoning.

**⚠️ Honesty flag, read before touching OLT code**: I have no real Huawei/ZTE/VSOL
hardware or SMS gateway credentials to test against. The OLT driver command sequences
(`app/Services/Olt/HuaweiOltDriver.php`, `ZteOltDriver.php`) and the VSOL SNMP OID
(`VsolOltDriver.php`) follow each vendor's standard *documented* CLI/SNMP syntax, but
exact commands/OIDs vary by firmware/model generation and are **only exercised via
mocks in tests** — never against real equipment. Same caveat class as thread #2's Redis
gap. Verify against real hardware before relying on this in production; the driver
interface (`OltDriverInterface`) isolates any needed corrections to one file per vendor.

Backend:
- New tenant tables: `olts` (encrypted `password`/`snmp_community` via the same
  `Attribute::make()` + `Crypt` pattern as `Mikrotik.php`), `sms_gateways` (encrypted
  `credentials` JSON blob, single `is_active` gateway at a time). Also added
  `olt_id`/`onu_mac`/`onu_serial`/`pon_port` to `clients` for audit/re-run.
- `App\Services\Olt\*`: `OltDriverInterface`, `HuaweiOltDriver`/`ZteOltDriver` (telnet via
  a hand-rolled `Support\TelnetClient` raw-socket client — no maintained telnet Composer
  package exists — or SSH via newly-added `phpseclib/phpseclib:^3.0` when a device's port
  is `22`), `VsolOltDriver` (SNMP via `ext-snmp`, guarded by `extension_loaded('snmp')`),
  `OltDriverFactory`, and `App\Services\OltService` (thin try/catch facade, same shape as
  `MikroTikService`).
- `App\Services\Sms\*`: `SmsDriverInterface`, `SslWirelessDriver`/`AlphaSmsDriver`/
  `TwilioDriver` (Laravel's `Http` facade, no new HTTP dependency)/`CustomHttpDriver`
  (templated URL + `{{to}}`/`{{message}}` payload for any other REST gateway),
  `SmsGatewayDriverFactory`. `SmsService::sendWelcomeMessage()` resolves the currently
  active `SmsGateway` and composes the message; returns a clear "configure one in
  Integrations" error if none is active.
- `App\Http\Controllers\API\OltController` (CRUD mirroring `MikrotikController`) and
  `IntegrationController` (SMS gateway CRUD/activate/test-send), both gated by new
  module slugs (`olt` reuses the CRUD-module pattern; SMS reuses the **existing** `sms`
  slug already in `TenantProvisioningService::defaultModules()`). ⚠️ Existing tenants
  won't have the new `olt` module enabled automatically — `TenantModule` rows are only
  assigned at provisioning time. A landlord admin needs to explicitly enable `olt` for
  pre-existing tenants via the landlord tenant-modules UI.
- `App\Http\Controllers\API\ClientProvisioningController` (new, separate from
  `ClientController` on purpose — the existing atomic `store()` and its tests are
  untouched): 6 independently-retryable JSON step endpoints under
  `dashboard/clients/provision/*`. Validation shared with `ClientController::store()`
  via new `App\Http\Requests\StoreClientRequest`. Idempotency: the PPPoE step checks for
  an existing secret before adding (safe to retry); the queue/expiry/onu steps are
  naturally idempotent DB/router lookups.
- **Bandwidth queue design decision**: PPPoE clients get dynamic IPs from a pool, so a
  `/queue/simple` entry keyed by IP is only meaningful when the package has a genuine
  static IP in `remote_address` (checked via IPv4 regex). If it's a pool name instead
  (the common case), the step returns `skipped: true` with an explanation — rate
  limiting is already enforced via the PPP profile's `rate-limit` (existing
  `PackageController` behavior), so nothing is silently missing. New
  `MikroTikService::addSimpleQueue()` added for the static-IP case.
- **Expiry step**: now always auto-computed as `today + 30 days` server-side in the new
  wizard (`ClientProvisioningController::createClient()`), not user-picked. The old
  `ClientController::store()`/`update()` and `Edit.vue` still take a manual `expiry_date`
  for renewals — unchanged.

Frontend:
- `resources/js/Pages/Clients/Create.vue` reworked in place into the wizard: OLT/ONU
  fields (shown only if an OLT is selected), a read-only "Expires: today+30" display
  instead of a date picker, and a sequential-axios-calls step list (pending → running →
  success/error/skipped, with a per-step **Retry** button) — extends the same
  axios-from-a-Vue-page pattern already used by `Mikrotik/Index.vue`'s
  `checkConnection()`, just chained across 6 steps instead of 1.
- New `resources/js/Pages/Olts/{Index,Create,Edit}.vue` (mirrors `Mikrotik/*.vue`) and
  `resources/js/Pages/Integrations/Index.vue` (mirrors `Tenant/FrontendAdmin.vue`'s
  grouped-sections pattern) for gateway/OLT management.
- Added `OLT_DEVICES` and `INTEGRATIONS` entries to
  `resources/js/Layouts/Nevigations/VerticalNavigation.js`.

Tests (all follow the existing `setupTenant()`/`reconnectTenant()` tenant-DB pattern —
see below): `tests/Feature/OltManagementTest.php`, `tests/Feature/IntegrationSettingsTest.php`
(includes an `Http::fake()` test for the Twilio driver), `tests/Feature/ClientProvisioningWizardTest.php`
(one test per step: success, 422-on-failure, skip logic, idempotent retry — caught a real
bug here: calling `$this->mock(SomeService::class)` **twice** in one test replaces the
prior mock/expectations rather than adding to them; fixed by using the single-callback
form `$this->mock(Service::class, function ($mock) { ... })`).

## Testing Tenant-Scoped Features (pattern to reuse)

`SetTenantDatabase` calls `tenancy()->end()` in a `finally` block after every request, which
reverts the dynamic `tenant` DB connection config. So: after any `$this->post(...)` /
`$this->get(...)` call against a tenant subdomain, you **must** re-run
`Config::set('database.connections.tenant', ...) + DB::purge('tenant') + DB::reconnect('tenant')`
before making further `assertDatabaseHas`/`Model::on('tenant')` calls, or you'll hit
`InvalidArgumentException: Database connection [tenant] not configured.` This tripped up the new
`ClientManagementTest` initially — see its private `reconnectTenant()` helper for the fix, and
`MikrotikRouterTest`/`TenantAuthenticationTest` for the pre-existing versions of the same
pattern.

## Checks Already Run

- `php artisan test` — 53/53 passing (40 pre-existing/fixed + 13 new across
  `OltManagementTest`, `IntegrationSettingsTest`, `ClientProvisioningWizardTest`).
- `npm run build` — compiles clean (pre-existing >500kB chunk warning for
  `vue3-apexcharts`, unrelated to this session). One real bug caught and fixed here: a
  literal `{{to}}` placeholder string in `Integrations/Index.vue`'s label text broke the
  Vue template compiler (looked like an unterminated mustache interpolation) — fixed by
  wrapping the literal braces in `<span v-pre>`.
- `./vendor/bin/pint --test` — clean on every file touched this session; the full-repo
  run does show pre-existing violations in files this session never touched
  (`DashboardController`, `SubZoneController`, `ZoneController`,
  `HandleInertiaRequests`, `SubZone`/`Tenant`/`Zone` models, two `stancl/tenancy`
  migrations) — pre-existing debt, not introduced here.
- `php artisan route:list` spot-checked for `clients`, `packages`, `mikrotik` — all routes now
  resolve to real controller methods.
- Manual end-to-end verification of the session/login fix via `php artisan serve` + `curl
  --resolve` (no hosts-file/DNS changes) against real local tenant data.
- **Not done this session** (no browser available): visually exercising the new
  `Clients/Create.vue` step wizard, `Olts/*` pages, or `Integrations/Index.vue` in an
  actual browser. Only verified via the Feature test suite (mocked services) and a clean
  `npm run build`.

## Suggested Next Steps

1. Split this session's changes into logical commits (or one commit per numbered thread above)
   before pushing.
2. Run the new `2026_07_01_000005_add_soft_deletes_to_clients_table` migration on any tenant DB
   not covered above (also applies to the newer `..._000006/7/8_*` migrations from thread #5).
3. Manually smoke-test the virtualized Clients table in a real browser with a large seeded
   dataset — not verified visually in this sandbox.
4. Pick up the zones/sub-zones dead-route cleanup (background task `task_e584310a`).
5. Decide whether Sprint 1 Day 2 needs a true `/api/*` JSON layer after all (e.g. for a future
   mobile app) — current implementation deliberately stayed inside the existing Inertia routes
   per the user's explicit choice.
6. If `SESSION_DRIVER=redis` regressions appear, re-verify the middleware-order fix against real
   redis rather than the test suite's `array` driver (see thread #2's "Known gap").
7. **Before relying on OLT binding in production**: verify `HuaweiOltDriver`/`ZteOltDriver`
   CLI command syntax and `VsolOltDriver`'s SNMP OID against real hardware — see thread
   #5's honesty flag. None of this has touched a real OLT.
8. Wire real SMS gateway credentials into the Integrations panel and send an actual test
   message end-to-end (only `Http::fake()`-mocked in tests so far).
9. Enable the new `olt` module for any tenant that existed before this session (module
   assignment only happens automatically at provisioning time — see thread #5).
10. Manually browser-test the new 6-step `Clients/Create.vue` wizard against a real (or
    at least reachable) MikroTik router to confirm the retry UX behaves as designed.
