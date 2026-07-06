# Claude Handoff

## Current Branch

- Branch: `86ey2f32x-public-website-1-click-enable`, pushed to `origin`, working tree **clean**
  (nothing uncommitted).
- Latest commit `a850aa7` — "Add 1-click enable + package visibility to the public website
  generator (S1-D6)". This branch has **no PR yet** — automated PR creation (see "GitHub PR
  creation" below) kept hanging on this one. Create it manually:
  `https://github.com/Hredoy/ISP-MANAGEMENT/pull/new/86ey2f32x-public-website-1-click-enable`,
  then add the PR link as a comment on ClickUp task `86ey2f32x` and move it to **In Review**.
- `main` is up to date through PR #17 (merged). PRs #10–#17 below were all opened/merged in the
  session before this one; this session's only code change is the pending #18 (public website).
- Full history mixes two workflows: squash-merged PRs from Claude sessions (`Merge pull request
  #N ...`) and direct short-message commits from a teammate (`feat: billing`, `feat: phase 2`,
  `feat: codex`, `feat: issue`, etc.) — those are **not** Claude-authored, don't attribute them
  to this handoff's threads.

## Stack Reminder

Vue 3 + Inertia.js end-to-end. **No React anywhere in this app** (except the standalone
`sms-reader/` companion mobile app, which is intentionally React Native — see thread 1). If a
future spec says "React" or "TanStack Table" for the main app, it almost certainly means
`@tanstack/vue-table` — confirm before introducing a second frontend framework.

## Session Summary (PRs #10–#17, merged; #18 pending)

Each thread below was one ClickUp task run through the standard pick-up→branch→implement→
test→PR→comment→In-Review loop.

### 1. React Native SMS reader companion app — PR #10 (`86ey2f30x`)

Standalone app under `sms-reader/` (separate `package.json`, not part of the main Vue build).
Reads incoming SMS on the phone and forwards payment-confirmation texts to the Laravel backend.
Paired with `App\Models\SmsDeviceToken` (device registration/auth) and consumed by thread 2's
`PaymentSmsMatchController`.

### 2. Payment SMS auto-match API — PR #11 (`86ey2f318`)

`app/Http/Controllers/API/PaymentSmsMatchController.php` ingests forwarded SMS text, parses
amount/sender phone/transaction ID, and matches against `App\Models\Payment` /
`PaymentTransaction` (the pre-existing but previously-unused uuid-keyed "ISP foundation" schema —
reused rather than building parallel tables). On a confident match it records the payment and
calls MikroTik to auto-unblock/unsuspend the client. Deliberately conservative: duplicate
transaction IDs are rejected idempotently, unknown phones and partial payments are held/flagged
for manual review rather than auto-actioned, and a router-unreachable condition still records the
payment but flags it (never silently drops money that was actually received).

### 3. Billing automation scheduler — PR #12 (`86ey2f31d`)

`app/Console/Commands/ProcessBillingExpirations.php`, scheduled via the app's scheduler. Sends
expiry reminders ahead of `expiry_date`, throttles/suspends PPPoE clients past expiry via
`MikroTikService`, matching the existing suspend/unsuspend semantics from an earlier session's
`ClientController`.

### 4. Dashboard business widgets — PR #13 (`86ey2f31m`)

New dashboard widgets: revenue, client counts, devices, payments. Backed by thread 5's Redis
caching layer from the start (cache keys were added alongside the widgets, not bolted on after).

### 5. Redis caching + invalidation — PR #14 (`86ey2f31u`)

`App\Support\TenantCache` — per-tenant namespaced cache helper (`remember()` + explicit
`forget()`/invalidation) for `dashboard`, `clients`, `devices`, and `ai_answer` (hashed-query)
keys. Invalidation is surgical: writing a client invalidates `clients` + `dashboard` but not
`devices`; writing a device invalidates `devices` independently. See
`tests/Feature/TenantCacheTest.php`.

### 6. AI chatbot (local KB + Groq/Gemini fallback) — PR #15 (`86ey2f323`)

`App\Services\Chat\ChatbotService` tries a local knowledge-base lookup first
(`App\Models\KbArticle`, tenant-scoped FAQ/article content) before falling back to an external
LLM provider — `GroqChatDriver` then `GeminiChatDriver` — via a driver-factory pattern matching
the existing `MikroTikServiceFactory`/`SmsGatewayDriverFactory` convention. Answers are cached
via `TenantCache`'s `ai_answer` key (hashed on the question text) to avoid re-billing the same
question repeatedly.
**Honesty flag**: no real Groq/Gemini API keys were available this session — the HTTP drivers are
implemented against each provider's documented chat-completions API shape but only verified via
`Http::fake()` in tests, never against the live APIs. Wire real keys and do one live smoke-test
call before trusting this in production.

### 7. Smart ticket system + realtime broadcasting scaffold — PR #16 (`86ey2f329`)

`App\Models\Ticket`/`Fault` (reusing the pre-existing unused "ISP foundation" schema — added
`category`/`sla_due_at` columns to `tickets`, and `mikrotik_id` FK to `faults` since the generic
uuid `device_id` FK didn't fit the real integer-keyed `mikrotiks`/`olts` tables).
`App\Services\TicketService`: auto-categorizes new tickets, sets an SLA due date, assigns to the
least-loaded technician, and an `escalateOverdue()` step that flips overdue tickets' status
without touching others. Three `ShouldBroadcastNow` events (`NewTicketCreated`,
`PaymentReceivedBroadcast`, `DeviceStatusChanged`) using
`Dispatchable, InteractsWithSockets, SerializesModels`. New `config/broadcasting.php` +
`Illuminate\Broadcasting\BroadcastServiceProvider` registered in `bootstrap/providers.php`.
**Honesty flag**: `BROADCAST_CONNECTION` defaults to `log` (safe no-op) — **no Soketi/Pusher/Ably
server is actually deployed**. The event classes and payloads are real and tested, but "realtime"
delivery to a browser has never been verified end-to-end. Before relying on this, stand up
Soketi (or equivalent), switch `BROADCAST_CONNECTION`, and confirm a browser client actually
receives an event.

### 8. MikroTik status/CPU polling + fault detection — PR #17 (`86ey2f32m`)

`App\Services\DevicePollingService` polls router SNMP/API state, tracks sustained-high-CPU
windows, and raises `Fault` records + `DeviceStatusChanged` broadcasts on real state transitions
(not on every poll — only when status actually changes, to avoid broadcast spam).
**Deliberately not implemented**: automatic remediation (e.g. auto-rebooting an ONU/PPPoE
session on a "stuck" signal). This was scoped out on purpose — auto-restarting customer hardware
based on inference from polling data is a real customer-impact risk without much more
confidence in the signal quality; faults are surfaced for a human to act on instead.
**Bug caught and fixed here**: Carbon 3 changed `diffInMinutes()` to return a **signed** value
(negative when the compared timestamp is in the past). `now()->diffInMinutes($highCpuSince)` was
silently always negative, so the "5 minutes of sustained high CPU" fault never fired. Fixed with
`abs()` — if you touch any other duration-threshold code in this codebase, check for the same
gotcha before assuming `diffInMinutes()`/`diffInSeconds()` etc. are unsigned.

### 9. Public website — 1-click enable + package visibility (PR pending, `86ey2f32x`)

The app already had a substantial auto-generated public website
(`TenantWebsiteController`/`resources/js/Pages/Tenant/Website.vue`, driven by live DB data: hero,
sliders, packages, blogs, connection/complaint/referral/payment forms, branding + SEO all
editable via `resources/js/Pages/Tenant/FrontendAdmin.vue`) — this task only needed the 3 gaps
literally named in the spec:
- **1-click enable**: `is_enabled` flag on tenant frontend settings (default `true`, so existing
  live sites are unaffected); toggling it off 404s the public site instantly
  (`TenantWebsiteController::assertTenantWebsite()`).
- **Package visibility**: `packages.is_public` column (default `true`), checkbox in
  `Packages/Create.vue`/`Edit.vue`, "Hidden" badge in `Packages/Index.vue`; the public site now
  filters to `is_public = true`.
- **Coverage zones**: public site lists the tenant's `Zone` names as a badge list (no map — the
  actual Leaflet network map is separate, still out of scope).
Covered by `tests/Feature/PublicWebsiteTest.php` (7 tests, all passing — see below).

## Ad hoc / unscheduled work this session

- **Sidebar scrollability fix** (`resources/js/Layouts/ISPLayout.vue`): sidebar `<nav>` is now
  `flex-1 min-h-0 overflow-y-auto` with a themed thin scrollbar (`.sidebar-scroll` scoped style,
  Firefox `scrollbar-width`/`scrollbar-color` + WebKit `::-webkit-scrollbar*`), so a tall nav menu
  scrolls inside the sidebar instead of overflowing the viewport. Already committed (part of
  `main`'s history — run `git log -- resources/js/Layouts/ISPLayout.vue` if you need the exact
  commit).
- A user request to enumerate all push-notification types/payloads for mobile deep-linking was
  interrupted before any work started — not implemented, no design decided yet. Revisit if asked
  again.

## Testing Tenant-Scoped Features (pattern to reuse)

`SetTenantDatabase` calls `tenancy()->end()` in a `finally` block after every request, which
reverts the dynamic `tenant` DB connection config. So: after any `$this->post(...)` /
`$this->get(...)` call against a tenant subdomain, you **must** re-run
`Config::set('database.connections.tenant', ...); DB::purge('tenant'); DB::reconnect('tenant');`
(a `reconnectTenant()` helper) before making further `assertDatabaseHas`/`Model::on('tenant')`
calls, **and** before reading any Eloquent *cast* attribute (e.g. a `datetime` cast internally
calls `getConnection()`) — this trips people up because it looks like a plain attribute read, not
a query. See `reconnectTenant()` in `tests/Feature/PublicWebsiteTest.php`,
`ClientManagementTest`, `MikrotikRouterTest`, or `TenantAuthenticationTest` for the pattern.

Other test gotchas hit repeatedly this session, worth remembering:
- `TenantProvisioningService::seedTenant()` auto-creates a "Default MikroTik" router and 4
  default packages (Nano/Starter/Pro/Enterprise). Don't assume a fresh tenant has 0 or 1 of
  either — delete seeded rows first (`Package::on('tenant')->delete();`) or count dynamically.
- Mockery: calling `$this->mock(SomeService::class)` **twice** in one test replaces the prior
  expectations rather than adding to them. Use the single-callback form
  (`$this->mock(Service::class, function ($mock) { $mock->shouldReceive(...)->once(); ... });`)
  and give every `shouldReceive()` an explicit call count when a test has multiple phases with
  different return values.
- If the full suite starts throwing "table already exists" / "table not found" on the *central*
  `isp_management_test` database across unrelated tests, it's very likely accumulated
  corruption from heavy tenant-DB churn in a long session, not a real regression — confirm by
  re-running and seeing different random tests fail each time, then fix with
  `mysql -h127.0.0.1 -uroot -e "DROP DATABASE IF EXISTS isp_management_test; CREATE DATABASE isp_management_test;"`
  (safe: it's an isolated test DB).

## GitHub PR creation (known flaky step)

The automated flow extracts a token via `git credential fill` piped into a throwaway Node script
and POSTs to the GitHub API — it frequently hangs (`ETIMEDOUT`). When it hangs: stop after 1–2
retries, give the user the manual compare URL
(`https://github.com/<owner>/<repo>/pull/new/<branch>`), and either wait for them to paste the
URL or, if they just say "done", look it up via
`GET /repos/<owner>/<repo>/pulls?head=<owner>:<branch>&state=all`.
**Absolute rule**: never print any part of a credential/token, even truncated/partial — a live
PAT was accidentally printed in full this session during a debugging attempt to inspect
`git credential fill` output fields, and the user was told to treat it as compromised and revoke
it immediately. Confirm with the user that this has actually been done before assuming it's safe
to move on. Do not repeat the inspection technique that caused it (dumping raw credential-helper
output) under any circumstance — if you need to debug PR creation, debug the HTTP request/response
instead, never the credential extraction step.

## Checks Already Run

- `php artisan test` — after fixing the central test-DB corruption (see above), full suite passes
  **109/109** (414 assertions, ~550s), including this session's new tests: `PublicWebsiteTest` (7)
  plus all pre-existing suites (`TenantAuthenticationTest`, `TenantCacheTest`,
  `TenantIsolationTest`, `TenantDatabaseSeederTest`, `TicketSystemTest`, `ProfileTest`, etc.).
- `npm run build` — compiles clean.
- Manual verification of PR #10–#17 features was via the Feature test suite (mocked
  MikroTik/SMS/AI-provider services) — **not** against real hardware or live third-party APIs for
  any of them (see the per-thread honesty flags above).

## Known Gaps / Honesty Flags (carried forward, cross-session)

1. **Broadcasting/realtime** (thread 7): no Soketi/Pusher server deployed; `BROADCAST_CONNECTION`
   is `log`. Verify against a real WebSocket server before calling any "realtime" feature done.
2. **AI chatbot** (thread 6): Groq/Gemini drivers untested against live APIs — no keys available.
3. **OLT drivers** (`app/Services/Olt/HuaweiOltDriver.php`, `ZteOltDriver.php`,
   `VsolOltDriver.php`, from an earlier session): command/OID syntax is per-vendor documentation,
   never run against real hardware.
4. **SMS gateway drivers** (`app/Services/Sms/*`, from an earlier session): only
   `Http::fake()`-tested; no real gateway credentials wired into the Integrations panel yet.
5. **Redis session/cache tenancy fix** (an earlier session's middleware-order fix): validated
   with PHPUnit's `array` driver + manual `curl`, not an automated `SESSION_DRIVER=redis` test.
   Re-verify against real Redis if session/cache-isolation bugs resurface in production.
6. **PPPoE-stuck / ONU auto-reboot remediation** (thread 8): intentionally not built — faults are
   surfaced for a human, not auto-acted-on.
7. **Leaked GitHub PAT**: needs the user's confirmation that it was rotated (see "GitHub PR
   creation" above) — not confirmed as of this handoff.
8. **Zones/sub-zones dead routes**: `dashboard.zones.*`/`dashboard.sub-zones.*` still register
   all 7 REST routes though the controllers only implement `index`/`store`/`destroy` (flagged as
   background task `task_e584310a` in an earlier session — still unresolved).
9. Push-notification deep-link types/payloads: requested once, interrupted before design or
   implementation started.

## Suggested Next Steps

1. Create the PR for `86ey2f32x-public-website-1-click-enable` (manual link above), add it as a
   ClickUp comment on task `86ey2f32x`, move the task to In Review.
2. Confirm the leaked GitHub PAT has been revoked/rotated.
3. Decide priority order for the honesty-flag gaps above — broadcasting/Soketi and the AI
   provider keys are the two most likely to be asked about next given they're user-facing.
4. Pick up the zones/sub-zones dead-route cleanup (`task_e584310a`).
5. If a push-notification deep-linking spec comes back, design the screen-type/payload contract
   before implementing (nothing decided yet).
