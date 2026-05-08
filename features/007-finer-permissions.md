# Finer Permissions

- Branch: `feature/007-finer-permissions`
- Related Issue: —
- **Status**: 🟡 In Progress

## Overview

Shell currently connects users to external services — Google Calendar, mail (IMAP), and Slack — using coarse boolean flags on the `accounts` table (`google_calendar_used`, `mail_used`, `slack_used`). These flags control whether a service is enabled at all, but provide no granularity around what an agent is allowed to do with it. An enabled Slack account can both read and write; an enabled Calendar account can currently only read — there are no write endpoints yet.

As Shell grows into a more agent-facing product, this coarseness becomes a problem. An admin may want to allow an agent to read calendar events but not create them, or to receive Slack messages but never send them. Today there is no mechanism to express or enforce that, and agents cannot create calendar events at all.

This feature does two things: it splits service access into fine-grained read/write permission flags (stored on `accounts`, enforced by new middleware, togglable in the Filament admin UI), and it adds the missing `POST /{account}/calendar` endpoint so agents can create Google Calendar events. `mail_read` and `mail_write` will be added to the schema and UI but not wired to any endpoint (the underlying functionality does not exist yet). `google_calendar_read`, `google_calendar_write`, `slack_read`, and `slack_write` will all be fully wired.

### Background / Current Behavior

- **Current behavior**: `EnsureServiceConnected` middleware accepts a service name (`calendar`, `mail`, `slack`) and checks the corresponding `_used` flag plus token presence. All calendar routes share a single `service.connected:calendar` check; all Slack routes share `service.connected:slack`. There is no distinction between read and write.
- **Problem**: No way to grant read-only access to a service or to revoke write access without disabling the service entirely.
- **Where it happens**: `app/Http/Middleware/EnsureServiceConnected.php`, `routes/api.php`, `app/Models/Account.php`, `app/Filament/Resources/AccountResource/`
- **Impact**: All agent access is all-or-nothing per service. Write permissions cannot be scoped down.

### Target Outcome

This feature will add fine-grained permission toggles per service and a Google Calendar write endpoint so that admins and agents can:
- Grant or revoke read access to Calendar, Mail, or Slack independently of write access
- Grant or revoke write access to Calendar, Mail, or Slack independently of read access
- Have those permissions enforced automatically on every relevant API endpoint via middleware
- Configure permissions per account in the Filament admin UI using the same toggle pattern already used for service connection flags
- Create Google Calendar events via `POST /{account}/calendar`

## Requirements

### Functional

- [x] Six new boolean columns on `accounts`: `google_calendar_read`, `google_calendar_write`, `mail_read`, `mail_write`, `slack_read`, `slack_write` — all defaulting to `true`
- [x] New `EnsurePermission` middleware (alias `permission`) enforces a named permission flag on the authenticated account; returns 403 JSON on failure
- [x] `google_calendar_read` applied to `GET /api/{account}/calendar` and `GET /api/{account}/calendar/{eventId}`
- [x] New `POST /{account}/calendar` endpoint for creating Google Calendar events (`CreateCalendarEntryResponder`)
- [x] `CalendarService::createEntry(Account $account, array $data)` added, using `$calendar->events->insert('primary', $event)`
- [x] `google_calendar_write` applied to `POST /{account}/calendar`
- [x] `slack_read` applied to `GET` Slack routes (`/channels`, `/channels/{channel}/messages`, `/users`)
- [x] `slack_write` applied to `POST` Slack routes (`/channels/{channel}/messages`, `/conversations/open`)
- [x] Filament `EditAccount` UI: new "Permissions" section with six `Toggle` components, one per flag
- [x] `mail_read` and `mail_write` exist in schema and UI but no endpoint enforcement (functionality not built yet)

### Non-Functional

- [x] Observability: middleware returns structured JSON `{'message': 'Forbidden.'}` consistent with `EnforceTokenIpBinding`
- [x] Performance: permission check is a single boolean column read on an already-loaded `Account` model — negligible overhead
- [x] Security/Permissions: new permission flags default to `true` so existing accounts retain full access; no silent lockouts on migration
- [x] Backwards compatibility: existing `mail_used`, `google_calendar_used`, `slack_used` columns are **not** renamed or removed — they continue to control connection/OAuth status and are checked by `EnsureServiceConnected` first

### UX / Admin (if applicable)

- [x] Read/write toggles nested inside each service's existing section in `EditAccount` — not in a separate "Permissions" block
- [x] Toggles are only visible when the parent service toggle (`mail_used`, `google_calendar_used`, `slack_used`) is enabled
- [x] Labels are concise: "Read" / "Write" (context from the section header makes them self-explanatory)
- [x] Mail read/write toggles carry a helper text noting they are not yet enforced

### Out of Scope

- Per-token ability gating (Sanctum `abilities` column exists but is not used; this feature does not change that)
- `mail_read` / `mail_write` endpoint enforcement (no mail API routes exist yet)
- Additional calendar write operations beyond event creation (update, delete)
- User-facing (non-admin) permission management

---

## Implementation Plan

### Phase 1 — Database, Model & Factory

**Goal**: Add six fine-grained permission columns to `accounts`, update the `Account` model and its factory, and write model-level tests confirming the new columns are cast correctly and fillable.

#### Steps

1. **Migration**
   - Generate: `php artisan make:migration add_fine_grained_permissions_to_accounts_table --no-interaction`
   - Add six boolean columns with `->default(true)`:
     ```
     $table->boolean('google_calendar_read')->default(true)->after('google_calendar_used');
     $table->boolean('google_calendar_write')->default(true)->after('google_calendar_read');
     $table->boolean('mail_read')->default(true)->after('mail_used');
     $table->boolean('mail_write')->default(true)->after('mail_read');
     $table->boolean('slack_read')->default(true)->after('slack_used');
     $table->boolean('slack_write')->default(true)->after('slack_read');
     ```
   - Default `true` preserves existing behaviour — all currently connected accounts retain full access post-migration.
   - Do **not** touch or remove `mail_used`, `google_calendar_used`, or `slack_used`.

2. **Account Model** (`app/Models/Account.php`)
   - Add all six columns to `$fillable`.
   - Add all six to the `casts()` method as `'boolean'` (following existing cast pattern for `mail_used` etc.).

3. **AccountFactory** (`database/factories/AccountFactory.php`)
   - Update `withGoogleCalendar()` state to also set `google_calendar_read: true, google_calendar_write: true`.
   - Update `withSlack()` state to also set `slack_read: true, slack_write: true`.
   - Update `mailOnly()` state to also set `mail_read: true, mail_write: true`.
   - Add convenience states: `withCalendarReadOnly()`, `withSlackReadOnly()` for use in middleware tests.

4. **Tests** (`tests/Feature/AccountTest.php` or new `tests/Feature/AccountPermissionsTest.php`)
   - Assert each new column is cast to boolean.
   - Assert each column is mass-assignable.
   - Assert new factory states produce expected flag values.
   - Assert default column values are `true` on a freshly created account.

---

### Phase 2 — Middleware & Route Enforcement

**Goal**: Create the `EnsurePermission` middleware, register it, apply it to the appropriate calendar and Slack routes, and write feature tests proving enforcement works.

#### Steps

1. **Create Middleware** (`app/Http/Middleware/EnsurePermission.php`)
   - Generate: `php artisan make:middleware EnsurePermission --no-interaction`
   - Accept a single string parameter: the permission name (e.g. `google_calendar_read`, `slack_write`).
   - Resolve the account from the route binding (`$request->route('account')`).
   - Check `$account->{$permission}` — if `false`, return a 403 JSON response:
     ```php
     return response()->json(['message' => 'Forbidden.'], 403);
     ```
   - If the column does not exist on the model, throw a descriptive exception in development (fail loudly against misconfiguration).

2. **Register Middleware** (`bootstrap/app.php`)
   - Add alias `'permission'` → `EnsurePermission::class` inside `$middleware->alias([...])`, alongside `account.owner` and `service.connected`.

3. **Apply to Routes** (`routes/api.php`)
   - Calendar read routes — add `permission:google_calendar_read` to the existing `service.connected:calendar` group or as an additional middleware per route:
     - `GET /{account}/calendar`
     - `GET /{account}/calendar/{eventId}`
   - Slack read routes — add `permission:slack_read`:
     - `GET /slack/{account}/channels`
     - `GET /slack/{account}/channels/{channel}/messages`
     - `GET /slack/{account}/users`
   - Slack write routes — add `permission:slack_write`:
     - `POST /slack/{account}/channels/{channel}/messages`
     - `POST /slack/{account}/conversations/open`
   - `google_calendar_write` — the route is built in Phase 3; add the middleware there, not here.
   - `mail_read` / `mail_write` — no routes to apply yet; note as future work.

4. **Tests** (`tests/Feature/Middleware/EnsurePermissionTest.php`)
   - Mirror the structure of `EnsureServiceConnectedTest.php` (uses `RefreshDatabase`, Sanctum bearer tokens, factory states).
   - Cover all enforced routes:
     - **google_calendar_read = false** → `GET /calendar` returns 403
     - **google_calendar_read = true** → `GET /calendar` proceeds (mock service or stub response)
     - **slack_read = false** → `GET /slack/.../channels` returns 403
     - **slack_read = true** → passes through
     - **slack_write = false** → `POST /slack/.../messages` returns 403
     - **slack_write = true** → passes through
   - Also assert that a missing/disconnected service still returns 404 from `EnsureServiceConnected` before the permission check is even reached (middleware order matters).

---

### Phase 3 — Calendar Write API

**Goal**: Add `POST /{account}/calendar` to allow agents to create Google Calendar events, protected by `service.connected:calendar` and `permission:google_calendar_write`.

#### Steps

1. **Extend `CalendarService`** (`app/Services/CalendarService.php`)
   - Add a `createEntry(Account $account, array $data): array` method.
   - Call `$this->connect($account)` to get the `Google\Service\Calendar` instance.
   - Build a `Google\Service\Calendar\Event` object from `$data`:
     - `summary` (string, required)
     - `description` (string, optional)
     - `location` (string, optional)
     - `start` → `Google\Service\Calendar\EventDateTime` with `dateTime` (RFC3339) and `timeZone`
     - `end` → `Google\Service\Calendar\EventDateTime` with `dateTime` (RFC3339) and `timeZone`
   - Call `$calendar->events->insert('primary', $event)`.
   - Return the created event as an array (or pass through to a resource — see responder step).
   - Wrap in the same try/catch pattern as `listEntries` / `getEntry`.

2. **Create Responder** (`app/Http/Responders/Api/CreateCalendarEntryResponder.php`)
   - Follow the write responder pattern (Slack POST responders as reference).
   - Constructor-inject `CalendarService`.
   - `__invoke(Request $request, Account $account)` signature.
   - Inline `$request->validate([...])`:
     - `summary` — required string
     - `description` — nullable string
     - `location` — nullable string
     - `start` — required array with `dateTime` (required, date_format:RFC3339 or `Y-m-d\TH:i:sP`) and `timeZone` (required string)
     - `end` — required array with `dateTime` and `timeZone`
   - Call `$this->calendarService->createEntry($account, $request->validated())`.
   - Return `new CalendarEntryResource($result)` (reuse existing resource) with HTTP 201, or a `JsonResponse` if the Google response shape doesn't map cleanly to the existing resource.

3. **Register Route** (`routes/api.php`)
   - Inside the existing `service.connected:calendar` group, add:
     ```php
     Route::post('/{account}/calendar', CreateCalendarEntryResponder::class)
         ->middleware('permission:google_calendar_write');
     ```
   - The GET routes in the same group gain `permission:google_calendar_read` here too (or confirm they were already wired in Phase 2 — keep consistent).

4. **Tests** (`tests/Feature/Api/CalendarApiTest.php` — new file or extend existing)
   - Mirror the existing calendar read tests for structure (mock `CalendarService`, use Sanctum bearer token, `RefreshDatabase`).
   - `POST /api/{account}/calendar` with valid payload and `google_calendar_write = true` → 201, response matches expected shape.
   - `POST /api/{account}/calendar` with `google_calendar_write = false` → 403.
   - `POST /api/{account}/calendar` with invalid payload (missing `summary`, bad `dateTime` format) → 422 with validation errors.
   - `POST /api/{account}/calendar` when `CalendarService::createEntry` throws → 503.
   - `POST /api/{account}/calendar` when calendar not connected → 404 (from `EnsureServiceConnected`).

---

### Phase 4 — Filament Admin UI

**Goal**: Add read/write permission toggles inside each service's existing section in `EditAccount`, so they only appear when the service is relevant.

#### Steps

1. **Update `EditAccount` form** (`app/Filament/Resources/AccountResource/Pages/EditAccount.php` or the shared form schema in `AccountResource.php`)
   - Do **not** add a standalone "Permissions" section. Instead, nest the permission toggles inside each existing service block:
   - **Mail section** — add `mail_read` and `mail_write` toggles directly inside the existing mail `Section`, after `Toggle::make('mail_used')`:
     ```php
     Toggle::make('mail_read')->label('Read')->helperText('Not yet enforced — mail read endpoints are not built yet.'),
     Toggle::make('mail_write')->label('Write')->helperText('Not yet enforced — mail write endpoints are not built yet.'),
     ```
   - **Google Calendar section** — add `google_calendar_read` and `google_calendar_write` inside the existing Google Calendar `Section`, after `Toggle::make('google_calendar_used')`:
     ```php
     Toggle::make('google_calendar_read')->label('Read'),
     Toggle::make('google_calendar_write')->label('Write'),
     ```
   - **Slack section** — add `slack_read` and `slack_write` inside the existing Slack `Section`, after `Toggle::make('slack_used')`:
     ```php
     Toggle::make('slack_read')->label('Read'),
     Toggle::make('slack_write')->label('Write'),
     ```
   - Use `->visible(fn (Get $get) => (bool) $get('mail_used'))` (and equivalents for `google_calendar_used` / `slack_used`) so the read/write toggles only appear when the parent service toggle is on. This avoids showing permission controls for services that aren't connected.

2. **Tests** (`tests/Feature/Filament/AccountResourceTest.php`)
   - Extend existing Filament resource tests.
   - Assert that with `google_calendar_used = true`, the `google_calendar_read` and `google_calendar_write` fields render.
   - Assert saving the form with `google_calendar_read = false` persists correctly.
   - Assert saving the form with `slack_write = false` persists correctly.
   - Assert toggling `google_calendar_read` to `false` and saving reflects the updated value on reload.

---

### Phase 5 — Documentation

**Goal**: Update `resources/docs/` so agents reading the docs after merge have accurate, complete information about the new permission system and the calendar write endpoint — specifically so a future agent can implement mail write without needing to reverse-engineer the codebase.

#### Steps

1. **Update `resources/docs/calendars.md`**
   - Add a "Permissions" section near the top (after the connection/setup paragraph) explaining:
     - `google_calendar_read` must be enabled on the account to use GET endpoints; returns 403 if disabled
     - `google_calendar_write` must be enabled on the account to use POST endpoints; returns 403 if disabled
     - Both default to enabled; toggled per-account in the Filament admin UI
   - Add `403` row to the error table for both existing GET endpoints ("Google Calendar read permission is disabled for this account")
   - Add the new `POST /api/{account}/calendar` endpoint section:
     - Description, parameters table (account, summary, description, location, start/end with dateTime + timeZone)
     - `curl` example with JSON body
     - Success response (201) showing the created event shape (mirror `CalendarEntryResource` output)
     - Error table including 401, 403, 404, 422, 503

2. **Update `resources/docs/slack.md`**
   - Add a "Permissions" section near the top explaining:
     - `slack_read` must be enabled to use GET endpoints; returns 403 if disabled
     - `slack_write` must be enabled to use POST endpoints; returns 403 if disabled
     - Both default to enabled; toggled per-account in the Filament admin UI
   - Add `403` row to the error tables for all GET endpoints ("Slack read permission is disabled for this account")
   - Add `403` row to the error tables for both POST endpoints ("Slack write permission is disabled for this account")

3. **No new doc files needed** — mail endpoints don't exist yet; there is nothing to document for `mail_read`/`mail_write` until the functionality is built

---

## Progress

### Completed
- Worktree created at `.worktrees/feature/007-finer-permissions`
- Feature doc created
- Planning complete
- Phase 1: Migration, model, factory, tests
- Phase 2: `EnsurePermission` middleware, apply to existing calendar/Slack routes, tests
- Phase 3: Calendar write API (`CalendarService::createEntry`, `CreateCalendarEntryResponder`, route + `google_calendar_write` middleware), tests

### In Progress
- —

### Blocked
- —

### To Do
- [ ] Phase 4: Filament UI toggles, tests
- [ ] Phase 5: Update `resources/docs/calendars.md` and `resources/docs/slack.md` with permission system and new endpoint

---

## Technical Notes

### Why permissions live on `accounts`, not on tokens

Sanctum's `abilities` column on `personal_access_tokens` exists and is populated but never checked anywhere in this codebase. The existing service-level flags (`mail_used`, `google_calendar_used`, `slack_used`) live on `accounts`, and `EnsureServiceConnected` reads from the account model. Keeping the new fine-grained flags on `accounts` follows the same established pattern and avoids the complexity of per-token ability management. This is a deliberate architectural choice — if per-token scoping is needed in the future it can be layered on top.

### Middleware ordering

`EnsureServiceConnected` runs before `EnsurePermission`. This means a 404 ("service not connected") is returned before a 403 ("permission denied") is ever considered. This is intentional — it avoids leaking the existence of a permission check when the service isn't even enabled.

### Default `true` for new columns

All six columns default to `true`. This ensures zero disruption for existing accounts: no one loses access after the migration runs. Permissions can be selectively revoked via the admin UI going forward.

### `mail_read` / `mail_write` not enforced yet

The underlying read-mail API functionality does not exist. The columns and UI toggles are added now so the schema is ready when the functionality lands. The middleware parameter `mail_read` / `mail_write` should not be applied to any route on this branch.

### `google_calendar_write` and the new write endpoint

`POST /{account}/calendar` is built in Phase 3 and decorated with `permission:google_calendar_write` at the point of route registration. The Google Calendar SDK's `events->insert('primary', $event)` method is already available in the vendored client — `CalendarService` just needs a `createEntry` wrapper.

---

## Files Modified/Created

### Database
- `database/migrations/xxxx_add_fine_grained_permissions_to_accounts_table.php` *(new)*

### Models
- `app/Models/Account.php` — add to `$fillable` and `casts()`

### Services / Core
- `app/Http/Middleware/EnsurePermission.php` *(new)*
- `app/Services/CalendarService.php` — add `createEntry()` method

### API / Controllers / Responders
- `app/Http/Responders/Api/CreateCalendarEntryResponder.php` *(new)*
- `routes/api.php` — add `POST /{account}/calendar` route; apply `permission:*` middleware to calendar and Slack routes

### Views / Frontend
- —

### Documentation
- `resources/docs/calendars.md` — add Permissions section, 403 errors, `POST /api/{account}/calendar` endpoint
- `resources/docs/slack.md` — add Permissions section, 403 errors to all GET and POST endpoints

### Admin / CMS
- `app/Filament/Resources/AccountResource.php` (or `EditAccount.php`) — add Permissions section

### Console / Jobs
- —

### Factories / Seeders
- `database/factories/AccountFactory.php` — update states, add read-only convenience states

### Tests
- `tests/Feature/AccountPermissionsTest.php` *(new)* — model column casting + factory states
- `tests/Feature/Middleware/EnsurePermissionTest.php` *(new)* — middleware enforcement across calendar and Slack routes
- `tests/Feature/Api/CalendarApiTest.php` *(new)* — calendar write endpoint (201, 403, 422, 503, 404)
- `tests/Feature/Filament/AccountResourceTest.php` — extend with permission toggle tests

---

## Testing
🟡 **Not started**: No tests written yet.
- Total tests: 0
- Assertions: 0

---

## Questions/Decisions Needed
- [ ] Should `google_calendar_write` and `mail_write` default to `true` or `false`? (Currently proposed: `true` to preserve existing access. If a stricter default is preferred, change the migration default and update factory states accordingly.)

## Decisions Made
- Permissions stored on `accounts` table (not on Sanctum token abilities) — follows existing pattern
- Defaults to `true` to prevent silent lockouts post-migration
- `EnsureServiceConnected` runs before `EnsurePermission` (404 before 403)
- `mail_read` / `mail_write` / `google_calendar_write` added to schema and UI but not applied to any routes on this branch

---

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Worktree created for `feature/007-finer-permissions`
- Feature doc initialised

### Session 2
- Full planning completed via sub-agent research
- Three phases identified: DB/Model, Middleware, Filament UI
- Plan written to feature doc
