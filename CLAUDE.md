# CLAUDE.md

This file gives Claude Code (and other AI assistants) the context needed to work effectively in this repository.

## What this is

A multi-tenant ISP (Internet Service Provider) management system built on **Laravel 12** with an **Inertia.js + Vue 3** frontend. It lets an ISP operator manage network zones, sub-zones, packages, clients, and MikroTik routers, and supports onboarding new tenant organizations ("landlord/tenant" SaaS model) with per-tenant MySQL databases.

Stack:
- Backend: PHP 8.2+, Laravel 12, Inertia Laravel 2.0
- Frontend: Vue 3 (Composition/Options API mix), Inertia.js, Tailwind CSS 3, Vite 7
- Charts/UI: ApexCharts (`vue3-apexcharts`), `lucide-vue-next` icons, `@meforma/vue-toaster`
- MikroTik integration: `evilfreelancer/routeros-api-php` (RouterOS API client)
- Auth: Laravel Breeze (Inertia stack)
- Default local DB: SQLite (see `.env.example`); tenant databases use MySQL

## Repository layout

```
app/
  Http/
    Controllers/
      API/                       # Resource controllers for dashboard features
        ClientController.php
        DashboardController.php
        PackageController.php
        SubZoneController.php
        ZoneController.php
        Mikrotik/MikrotikController.php
      Auth/                      # Breeze auth controllers
      LandlordTenantController.php     # Landlord-side tenant approval UI
      TenantApplicationController.php  # Public "apply for org" form
      ProfileController.php
    Middleware/
      SetTenantDatabase.php      # Subdomain -> tenant DB connection switcher
      HandleInertiaRequests.php
    Requests/
  Models/
    Client.php, Zone.php, SubZone.php, Package.php, Mikrotik.php
    TenantApplication.php, User.php
  Services/
    MikrotikService.php           # RouterOS API wrapper (connect/getSystemStats/getInterfaceTraffic)
    TenantProvisioningService.php # Creates tenant DB + runs migrations on approval
database/
  migrations/                   # users/cache/jobs + mikrotiks/zones/sub_zones/clients/packages/tenant_applications
  seeders/DatabaseSeeder.php
  factories/UserFactory.php
resources/
  js/
    Pages/                      # Inertia page components (one dir per feature area)
      Area/ (Zones.vue, SubZones.vue)
      Clients/, Mikrotik/, Packages/, Landlord/, Tenant/, Auth/, Profile/
      Dashboard.vue, Monitor.vue, Welcome.vue
    Layouts/                    # AuthenticatedLayout, GuestLayout, ISPLayout
    Components/                 # Shared Breeze-style UI components (buttons, inputs, modal, dropdown)
  views/                        # Blade entry view for Inertia (app.blade.php)
routes/
  web.php                       # Main app + dashboard + landlord routes
  auth.php                      # Breeze auth routes
  console.php
config/                         # Laravel config, incl. tenant DB connection in database.php
tests/
  Feature/, Unit/               # PHPUnit (mostly default Breeze auth/profile tests)
```

## Multi-tenancy model

This app uses a **single-application, database-per-tenant** approach, not a package like `stancl/tenancy`:

1. A visitor submits an application at `/apply-organization` (`TenantApplicationController`), creating a `TenantApplication` row with `status = pending`.
2. A "landlord" admin reviews pending applications at `/landlord/tenants` (`LandlordTenantController::index`) and approves one (`LandlordTenantController::approve`).
3. `TenantProvisioningService::approve()`:
   - Slugifies the org name, builds a database name as `{TENANT_DB_PREFIX}{slug}` (default prefix `production_`).
   - Requires the **default** connection to be `mysql` (throws otherwise — tenant provisioning does not work on SQLite).
   - Runs `CREATE DATABASE IF NOT EXISTS` for the tenant DB, points the `tenant` connection at it, and runs `php artisan migrate --database=tenant --force`.
   - Marks the application `approved` with `slug`, `database_name`, `subdomain` (`{slug}.{LANDLORD_DOMAIN}`), and `approved_at`.
4. `SetTenantDatabase` middleware (registered globally in `bootstrap/app.php`, runs first in the `web` group) inspects the request host on every request:
   - If host equals `LANDLORD_DOMAIN` (or `www.` + it), pass through untouched — this is the landlord/main app.
   - If host doesn't end with `.{LANDLORD_DOMAIN}`, pass through untouched.
   - Otherwise treat the subdomain as a tenant slug, look up an **approved** `TenantApplication`, and if found, swap `database.default` to the `tenant` connection (pointed at that tenant's database) for the rest of the request. 404s if no approved tenant matches.

Key env vars: `LANDLORD_DOMAIN` (default `localhost`), `TENANT_DB_PREFIX` (default `production_`), `TENANT_DB_DATABASE`. The `tenant` connection is defined in `config/database.php` alongside the default `mysql`/`sqlite` connections.

When working on tenant-related features, remember: the `tenant` connection's `database` value is mutated at runtime via `Config::set` + `DB::purge('tenant')` + `DB::reconnect('tenant')`. Don't assume `config('database.connections.tenant.database')` is static.

## Core domain models

- **Zone** `hasMany` **SubZone**; **SubZone** `belongsTo` **Zone**.
- **Client** `belongsTo` **Zone** and `belongsTo` **SubZone** (relation method is `sub_zone`, not `subZone`).
- **Package** `belongsTo` **Mikrotik**.
- **Mikrotik** stores router credentials (`host`, `port`, `username`, `password`) in plaintext columns — treat as sensitive; do not log or expose via API responses.
- **TenantApplication**: `status` is a plain string (`pending`/`approved`), not an enum.
- Most models use `protected $guarded = []` (mass-assignment fully open) rather than `$fillable` — be consistent with existing models unless there's a reason to lock down fields (e.g. `TenantApplication` does use `$fillable`).

## MikroTik integration

`App\Services\MikrotikService` wraps `evilfreelancer/routeros-api-php`:
- `connect($host, $user, $pass, $port = 8728)` — returns a `RouterOS\Client` or `false` on failure (catches and logs exceptions, never throws to caller).
- `getSystemStats(...)` — CPU/RAM/uptime via `/system/resource/print`; returns `['error' => ...]` shape on failure instead of throwing.
- `getInterfaceTraffic(...)` — Tx/Rx via `/interface/monitor-traffic`.

`MikrotikController::getLiveStats` (route: `dashboard.mikrotik.mikrotik.stats`, see note below) combines system stats with active PPPoE users (`/ppp/active/print`), ARP entries (`/ip/arp/print`), and live interface traffic, converting bits/sec to Mbps for the dashboard widgets.

Because live calls hit real routers over the network with a short timeout, treat any code touching `MikrotikService` as **I/O that can fail/hang** — always check for `false`/`error` keys before using results, matching the existing pattern.

## Routing conventions

- Public: `/`, `/apply-organization` (GET/POST).
- Authenticated app lives under `auth` + `verified` middleware in `routes/web.php`:
  - `/dashboard` → `DashboardController::index`
  - `/landlord/tenants` (`landlord.tenants.*`) — tenant application review/approval
  - `/dashboard/*` (`dashboard.*`) — zones, sub-zones, packages, clients (all `Route::resource`), plus a hand-rolled `mikrotik` sub-group (`dashboard.mikrotik.*`) for router CRUD + live stats/monitor endpoints.
- Note the existing route name has a duplication quirk: the stats route is named `mikrotik.stats` inside a group already named `mikrotik.`, producing `dashboard.mikrotik.mikrotik.stats`. Preserve this name when referencing it in Vue (`route('dashboard.mikrotik.mikrotik.stats', ...)`) unless you deliberately rename it everywhere (controller, routes, and all Vue page references).
- Auth routes (`login`, `register`, password reset, email verification) come from Breeze in `routes/auth.php`.

## Frontend conventions

- Pages live in `resources/js/Pages/<Feature>/<Action>.vue` (e.g. `Clients/Index.vue`, `Clients/Create.vue`, `Clients/Edit.vue`) mirroring controller resource actions — follow this naming when adding new resource UIs.
- Shared layouts: `AuthenticatedLayout.vue` (Breeze default), `GuestLayout.vue` (Breeze default), `ISPLayout.vue` (custom layout for dashboard/ISP feature pages — prefer this one for new dashboard pages over `AuthenticatedLayout` unless replicating a Breeze-style page).
- Navigation config lives in `resources/js/Layouts/Nevigations/VerticalNavigation.js` (note: directory name is misspelled "Nevigations" — match existing path, don't silently rename without updating imports).
- Reusable form/UI components are in `resources/js/Components/` (Breeze-style: `PrimaryButton`, `TextInput`, `InputLabel`, `InputError`, `Modal`, `Dropdown`, etc.) — reuse these instead of creating ad hoc styled elements.
- Charts use `vue3-apexcharts`; icons use `lucide-vue-next`; toasts use `@meforma/vue-toaster`.
- Tailwind config: `tailwind.config.js` + `@tailwindcss/forms` plugin. Vite entry is `resources/js/app.js` (Inertia + Vue + Ziggy setup, standard Breeze scaffold).

## Development workflow

Install:
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # default local DB is sqlite
php artisan migrate
```
(`composer run setup` automates install + env + migrate + npm build.)

Run dev environment (server + queue listener + log tail + Vite, all concurrently):
```bash
composer run dev
```
Or individually: `php artisan serve`, `npm run dev` (Vite), `php artisan queue:listen`, `php artisan pail`.

Build frontend for production:
```bash
npm run build
```

Tests:
```bash
composer run test     # clears config cache, then `php artisan test`
# or directly:
php artisan test
./vendor/bin/phpunit
```
Test suite is currently the stock Breeze scaffold (`tests/Feature/Auth/*`, `ProfileTest`, example tests) — there is **no test coverage yet** for Zones, Clients, Packages, Mikrotik, or tenant provisioning. When adding features in those areas, add Feature tests under `tests/Feature/` following the existing Breeze test style (uses `RefreshDatabase`, sqlite in-memory per `phpunit.xml`).

Code style: Laravel Pint is available (`./vendor/bin/pint`) for PHP formatting — run it before committing PHP changes. No JS linter/formatter is configured (no ESLint/Prettier config present); match surrounding Vue file style.

## Conventions & gotchas to respect

- **`.env` is required but not committed.** `LANDLORD_DOMAIN` defaults to `localhost` for local dev — tenant subdomain routing only kicks in for hosts ending in `.{LANDLORD_DOMAIN}`.
- **Tenant provisioning requires MySQL** as the default connection; it will throw `RuntimeException` on SQLite. Don't "fix" this by relaxing the check without discussing — it's intentional given `CREATE DATABASE` semantics differ across drivers.
- **MikroTik credentials are stored in plaintext** in the `mikrotiks` table and passed around as raw strings — be careful not to add new code paths that leak them into logs, error messages, or client-visible JSON.
- **Migration filenames use far-future dates** (`2026_02_07_...`); don't "correct" these to today's date — they reflect the actual migration creation order.
- **`SetTenantDatabase` middleware is global** (in the `web` group, in `bootstrap/app.php`), so it runs on every web request including the landlord/marketing pages. Be mindful when adding new routes/subdomains.
- Prefer Eloquent relationships already defined on models (`zone()`, `sub_zone()`, `subZones()`, `mikrotik()`) over raw queries when traversing zone/sub-zone/client/package data.
- This is an early-stage project (4 commits in history) — expect gaps (no tests for core features, a misspelled directory, a duplicated route name) rather than deep entrenched architecture. Fix opportunistically when touching nearby code, but don't do drive-by renames across the whole codebase without being asked.
