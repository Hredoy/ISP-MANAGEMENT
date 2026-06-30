# Codex Handoff

## Current Branch

- Branch: `codex/stancl-tenancy-isolation`
- Base: `origin/main`
- Worktree: has uncommitted changes for the Stancl tenancy implementation.
- Do not mix this with the Mikrotik PR branch.

## User Goal

Implement:

- Install `stancl/tenancy`
- Tenant identification by subdomain and custom domain
- Tenant DB creation on signup
- Isolated schema/database per ISP
- Tenant seeder for default admin, packages, settings
- Local dev `.env` setup for MySQL + Redis
- Test two tenants for complete isolation

## What Was Changed

- Installed Composer packages:
  - `stancl/tenancy`
  - `predis/predis`
- Ran `php artisan tenancy:install`, creating:
  - `config/tenancy.php`
  - `routes/tenant.php`
  - `app/Providers/TenancyServiceProvider.php`
  - central `tenants` and `domains` migrations
  - `database/migrations/tenant/`
- Registered `App\Providers\TenancyServiceProvider` in `bootstrap/providers.php`.
- Added `App\Models\Tenant` using Stancl `HasDatabase` and `HasDomains`.
- Refactored `App\Http\Middleware\SetTenantDatabase` to initialize tenancy from Stancl `domains`.
- Refactored `TenantProvisioningService` to create Stancl tenants and domain records.
- Changed signup flow in `TenantApplicationController@store` to provision immediately on signup instead of waiting for landlord approval.
- Tenant ID now uses the unique generated application slug, avoiding duplicate organization-name DB collisions.
- Added custom domain normalization/validation:
  - strips `http://` / `https://`
  - trims slashes/spaces
  - lowercases
  - validates hostname shape
  - rejects central domains
  - checks uniqueness in `tenant_applications.custom_domain` and `domains.domain`
- Removed ISP business migrations from central migration path:
  - `mikrotiks`
  - `zones`
  - `sub_zones`
  - `clients`
  - `packages`
- Copied/kept those tables under `database/migrations/tenant/`.
- Added tenant `settings` table migration.
- Added `TenantDatabaseSeeder`:
  - tenant admin user
  - default router placeholder
  - starter packages
  - organization/billing settings
- Added `App\Models\Setting`.
- Updated `TenantApplication` fields:
  - `custom_domain`
  - `tenant_id`
- Updated application form UI for custom domain.
- Updated landlord tenant list UI to show custom domain.
- Updated `.env.example` and local `.env` for:
  - MySQL
  - Redis via Predis
  - central domains
  - tenant DB prefix

## Important Implementation Notes

- `config/tenancy.php` has `RedisTenancyBootstrapper` disabled because Stancl's bootstrapper directly uses the `phpredis` extension API. The app still uses Redis for cache/session/queue via Predis.
- `routes/tenant.php` is intentionally empty. Existing `routes/web.php` routes are made tenant-aware by `SetTenantDatabase` middleware.
- Current signup text says tenant is ready immediately.
- Existing landlord approval flow still exists but may now be redundant because signup auto-approves/provisions.

## Known Remaining Concern

- Tenant admins are seeded with a generated random password. Add an onboarding/reset-password delivery flow before production use so the ISP admin can set their own password.

## Checks Already Run

Passed:

- `vendor\bin\pint app\Http\Controllers\TenantApplicationController.php app\Services\TenantProvisioningService.php config\tenancy.php tests\Feature\TenantIsolationTest.php`
- `php -l app\Http\Controllers\TenantApplicationController.php`
- `php -l app\Services\TenantProvisioningService.php`
- `php -l tests\Feature\TenantIsolationTest.php`
- `php artisan config:clear`
- `php artisan route:list --path=apply-organization`
- `php artisan migrate:status`
- `npm run build`

- `php artisan test --filter=TenantIsolationTest` should use the MySQL test database configured in `phpunit.xml` (`isp_management_test`).

## SQA Status

For these two specific requirements, current code is okay:

- tenant identification by subdomain + custom domain
- tenant DB creation on signup with isolated tenant migrations

Remaining product follow-up:

- Add tenant-admin onboarding/reset-password delivery so generated credentials are usable without sharing a default password.

## Suggested Next Steps

1. Decide whether immediate signup provisioning replaces landlord approval entirely.
2. Harden tenant admin onboarding/password setup.
3. Commit changes on `codex/stancl-tenancy-isolation`.
4. Push/open PR if requested.
