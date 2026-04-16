# Slack Integration

- Branch: `feature/006-slack-integration`
- Related Issue: N/A
- **Status**: 🟡 In Progress

## Overview

Shell is a personal AI agent host that proxies external services (mail, calendar) so the agent can act on the user's behalf. Currently, the agent can read emails and calendar events, but has no awareness of Slack — meaning it cannot see conversations happening there or participate in them.

This feature adds full Slack integration: the user connects their Slack workspace via OAuth, and the agent gains HTTP API endpoints to list channels, read messages, and send messages. The agent acts as the user, using their credentials, not a shared bot token.

### Background / Current Behavior

- **Current behavior**: Shell supports mail (IMAP) and Google Calendar as proxied services. No Slack integration exists.
- **Problem**: The agent cannot see or interact with Slack, limiting its usefulness as a personal assistant.
- **Where it happens**: `app/Services/`, `app/Http/Controllers/`, `app/Filament/` (Accounts UI)
- **Impact**: Chris cannot delegate Slack-related tasks to his AI agent.

### Target Outcome

This feature will add a full Slack integration so that Chris's AI agent can:
- Connect a Slack workspace via OAuth (user token, not bot token)
- List channels and workspaces visible to the connected account
- Read messages in any channel the user can access
- Send messages to any channel on behalf of the user

## Requirements

### Functional

- [ ] User can connect a Slack workspace via OAuth in the Accounts admin section
- [ ] User can disconnect a connected Slack workspace from the same UI
- [ ] Agent can list all channels visible to the connected Slack account (via HTTP API)
- [ ] Agent can read messages from a specific channel (via HTTP API)
- [ ] Agent can send a message to a specific channel (via HTTP API)
- [ ] HTTP API endpoints are authenticated (requires a valid Sanctum token)
- [ ] All Slack credentials are stored encrypted at rest

### Non-Functional

- [ ] Observability: Slack API errors return 503 with contextual message, consistent with mail/calendar pattern
- [ ] Performance: Slack API calls are live-proxied (no caching or background sync); Slack rate limits handled gracefully
- [ ] Security/Permissions: Slack OAuth token stored encrypted; HTTP API endpoints require `auth:sanctum`; account ownership verified before using credentials
- [ ] Data retention / lifecycle: Tokens cleared on disconnect; no message data persisted to the database
- [ ] Backwards compatibility: Existing `slack.notifications` config key left untouched

### UX / Admin

- [ ] Accounts list shows a `slack_used` boolean icon column
- [ ] Account edit page has a Slack section with toggle + connect/disconnect action, matching Google Calendar UX
- [ ] Account create page shows `slack_used` toggle only (OAuth requires an existing record ID)

### Out of Scope

- Slack slash commands or bot webhook inbound events
- Persisting message history to the database
- Multi-workspace support (one Slack connection per Account)
- DM/IM reading (can be added later via additional scopes)
- Token rotation (Slack's default OAuth v2 tokens do not expire unless rotation is enabled)

---

## Implementation Plan

### Phase 1 — Slack OAuth & Account Storage

**Goal:** Wire up Slack OAuth so the user can connect/disconnect their Slack workspace from the Filament Accounts UI. Store credentials encrypted on the `Account` model.

#### Step 1.1 — Add Socialite Slack driver

```bash
composer require socialiteproviders/slack
```

Register the event listener in `app/Providers/AppServiceProvider.php` `boot()`:

```php
Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
    $event->extendSocialite('slack', \SocialiteProviders\Slack\Provider::class);
});
```

#### Step 1.2 — Config: add OAuth credentials

In `config/services.php`, add under the existing `slack` array (do NOT modify `slack.notifications`):

```php
'slack' => [
    'notifications' => [...], // existing — do not touch
    'client_id'     => env('SLACK_CLIENT_ID'),
    'client_secret' => env('SLACK_CLIENT_SECRET'),
    'redirect'      => env('SLACK_REDIRECT_URI'),
],
```

Add to `.env.example`:
```
SLACK_CLIENT_ID=
SLACK_CLIENT_SECRET=
SLACK_REDIRECT_URI=
```

Slack OAuth scopes to request: `channels:read channels:history chat:write identity.basic im:read im:history`

#### Step 1.3 — Migration: add Slack columns to `accounts`

Create a new migration (do not modify the original):

```bash
php artisan make:migration add_slack_columns_to_accounts_table --no-interaction
```

Columns to add:
- `slack_used` — `boolean`, default `false`, nullable: no
- `slack_access_token` — `text`, nullable, cast as `encrypted`
- `slack_team_id` — `string`, nullable (Slack workspace/team ID)
- `slack_team_name` — `string`, nullable (display name of the workspace)

No `slack_refresh_token` or `slack_token_expires_at` — Slack default OAuth v2 tokens do not expire unless token rotation is enabled in the Slack app.

#### Step 1.4 — Update `Account` model

- Add `slack_used`, `slack_access_token`, `slack_team_id`, `slack_team_name` to `$fillable`
- Add to `casts()`: `'slack_used' => 'boolean'`, `'slack_access_token' => 'encrypted'`
- Add helper: `isSlackConnected(): bool` — returns `$this->slack_used && !empty($this->slack_access_token)`

#### Step 1.5 — Responders

Create `app/Http/Responders/Auth/RedirectSlackOAuthResponder.php`:
- Store `slack_oauth_account_id` in session
- Redirect via `Socialite::driver('slack')->scopes([...])->redirect()`

Create `app/Http/Responders/Auth/CallbackSlackOAuthResponder.php`:
- Pull `slack_oauth_account_id` from session
- Call `Socialite::driver('slack')->user()`
- Update `Account` with `slack_access_token`, `slack_team_id` (from `$slackUser->getRaw()['team_id']`), `slack_team_name`, `slack_used = true`
- Redirect to Filament edit page with success flash

#### Step 1.6 — Routes

In `routes/web.php`, under `auth` middleware, add:

```php
Route::get('/auth/slack/{account}/redirect', RedirectSlackOAuthResponder::class)->name('slack.redirect');
Route::get('/auth/slack/callback', CallbackSlackOAuthResponder::class)->name('slack.callback');
```

#### Step 1.7 — Filament UI updates

**`AccountResource.php`** table — add:
```php
IconColumn::make('slack_used')->boolean(),
```

**`CreateAccount.php`** — add to form:
```php
Toggle::make('slack_used'),
```

**`EditAccount.php`** — add a new `Section::make('Slack')` containing:
- `Toggle::make('slack_used')->live()`
- `Action::make('connectSlack')` — visible when `slack_used` is true and `!$record->isSlackConnected()`; navigates to `route('slack.redirect', $record)`
- `Action::make('disconnectSlack')` — visible when `$record->isSlackConnected()`; clears `slack_access_token`, `slack_team_id`, `slack_team_name`, sets `slack_used = false`

#### Step 1.8 — Factory

In `AccountFactory`, add state:

```php
public function withSlack(): static
{
    return $this->state(fn () => [
        'slack_used' => true,
        'slack_access_token' => fake()->sha256(),
        'slack_team_id' => 'T' . fake()->bothify('??########'),
        'slack_team_name' => fake()->company(),
    ]);
}
```

#### Step 1.9 — Tests

Create `tests/Feature/SlackOAuthTest.php` mirroring `GoogleOAuthTest.php`:
- Redirect route stores session key, calls Socialite driver, returns redirect
- Callback with valid Socialite user updates account fields correctly
- Callback with missing session ID aborts (404)
- Disconnect action clears all slack fields on account

Update `tests/Feature/Filament/AccountResourceTest.php`:
- Edit page shows Slack section when `slack_used` is true
- Connect action is visible when not yet connected
- Disconnect action clears credentials

---

### Phase 2 — Slack API Client & Data Layer

**Goal:** Build `SlackService` to wrap the Slack Web API using Laravel's HTTP client. No external SDK; no persisted message data.

#### Step 2.1 — Create `SlackService`

```bash
php artisan make:class app/Services/SlackService --no-interaction
```

Constructor takes no injected services (token comes from `Account` argument on each method, matching `CalendarService` pattern).

**Methods:**

```php
/**
 * @return array<int, array{id: string, name: string, is_private: bool, num_members: int}>
 */
public function listChannels(Account $account, ?string $cursor = null): array

/**
 * @return array{messages: array<int, array{ts: string, user: string, text: string}>, next_cursor: ?string}
 */
public function listMessages(Account $account, string $channelId, int $limit = 50, ?string $cursor = null): array

/**
 * @return array{ok: bool, ts: string}
 */
public function sendMessage(Account $account, string $channelId, string $text): array
```

Implementation pattern:
- Use `Http::withToken($account->slack_access_token)->get('https://slack.com/api/conversations.list', [...])` etc.
- Check `$response->json('ok')` — if false, throw `\RuntimeException($response->json('error'))`
- Callers (`try/catch \Throwable`) will turn this into a 503, consistent with existing service pattern

Slack API endpoints:
- `GET https://slack.com/api/conversations.list` — `listChannels()`; params: `limit`, `cursor`, `exclude_archived=true`, `types=public_channel,private_channel`
- `GET https://slack.com/api/conversations.history` — `listMessages()`; params: `channel`, `limit`, `cursor`
- `POST https://slack.com/api/chat.postMessage` — `sendMessage()`; body: `channel`, `text`

#### Step 2.2 — Tests

Create `tests/Feature/SlackServiceTest.php`:
- `listChannels` returns shaped array from mocked HTTP response
- `listChannels` throws on non-ok response
- `listMessages` returns messages + next_cursor
- `sendMessage` posts correctly and returns ts
- Uses `Http::fake()` to mock Slack API responses — no real HTTP calls

---

### Phase 3 — HTTP API Read Endpoints

**Goal:** Expose `GET /api/slack/{account}/channels` and `GET /api/slack/{account}/channels/{channel}/messages` so the agent can read Slack data over the existing HTTP API. Follow the same controller/route conventions as the rest of the API.

#### Step 3.1 — Check existing API route conventions

Before creating anything, inspect `routes/api.php` and existing API controllers (e.g. mail or calendar) to understand the route structure, controller naming, and auth middleware in use.

#### Step 3.2 — Create `SlackController`

```bash
php artisan make:controller Api/SlackController --no-interaction
```

Methods:
- `channels(Request $request, Account $account): JsonResponse` — calls `SlackService::listChannels()`; supports `?cursor=` query param
- `messages(Request $request, Account $account, string $channelId): JsonResponse` — calls `SlackService::listMessages()`; supports `?limit=` and `?cursor=` query params

Guards for both methods:
- `abort_unless($account->user_id === $request->user()->id, 404)`
- `abort_unless($account->isSlackConnected(), 404)`

On `\Throwable`: return `response()->json(['error' => 'Slack API unavailable: '.$e->getMessage()], 503)`

#### Step 3.3 — Register routes

In `routes/api.php`, under `auth:sanctum` middleware, add:

```php
Route::get('/slack/{account}/channels', [SlackController::class, 'channels']);
Route::get('/slack/{account}/channels/{channel}/messages', [SlackController::class, 'messages']);
```

#### Step 3.4 — Tests

Create `tests/Feature/SlackReadEndpointsTest.php`:
- Unauthenticated request returns 401
- Wrong account (not owned by user) returns 404
- Account not Slack-connected returns 404
- `channels` happy path returns channel list JSON
- `messages` happy path returns messages + next_cursor JSON
- `channels` service error returns 503

Mock `SlackService` with `$this->mock(SlackService::class, ...)`.

---

### Phase 4 — HTTP API Send Endpoint

**Goal:** Expose `POST /api/slack/{account}/channels/{channel}/messages` so the agent can send messages to Slack.

#### Step 4.1 — Add `send` method to `SlackController`

`send(Request $request, Account $account, string $channelId): JsonResponse`

Validate: `text` (required string).
Same ownership/connected guards. Delegates to `SlackService::sendMessage()`.
Returns `response()->json(['ts' => $result['ts']])` on success.

#### Step 4.2 — Register route

In `routes/api.php`, alongside the read routes:

```php
Route::post('/slack/{account}/channels/{channel}/messages', [SlackController::class, 'send']);
```

#### Step 4.3 — Tests

Create `tests/Feature/SlackSendEndpointTest.php`:
- Unauthenticated request returns 401
- Wrong account returns 404
- Account not Slack-connected returns 404
- Missing `text` returns 422
- Happy path returns `{'ts': '...'}` JSON
- Service error returns 503

---

### Phase 5 — Documentation

**Goal:** Update `resources/docs/slack.md` to document the HTTP REST endpoints (replacing the MCP tool format written earlier), and keep `DocPagesService` and `DocPagesTest` consistent.

#### Step 5.1 — Rewrite `resources/docs/slack.md`

The file already exists but was written for MCP tools. Rewrite it following `mailboxes.md` format exactly:
- H1: `# Slack`
- Auth reminder + required headers code block
- Setup note: "Slack must be connected via OAuth in the admin panel (Accounts → Edit → Slack section)"
- Three endpoint sections using proper REST patterns:

```
## GET /api/slack/{account}/channels
## GET /api/slack/{account}/channels/{channel}/messages
## POST /api/slack/{account}/channels/{channel}/messages
```

Each section: parameter table, example request, example success response, error table (401, 404, 422, 503).

#### Step 5.2 — Verify `DocPagesService` and tests

`app/Services/DocPagesService.php` already has the `slack` entry. `tests/Feature/Docs/DocPagesTest.php` already has the Slack assertions. No changes needed unless the rewritten doc breaks existing assertions — check and fix if so.

---

## Progress

### Completed
- Branch and worktree created
- Feature doc written with full implementation plan
- Phase 1: Slack OAuth & Account Storage
- Phase 2: Slack API Client & Data Layer
- Phase 3: HTTP API Read Endpoints
- Phase 4: HTTP API Send Endpoint
- Phase 5: Documentation (rewrite doc for REST endpoints)

### In Progress
- (none)

### Blocked
- (none)

### To Do
- (none)

## Technical Notes

**Why `socialiteproviders/slack` and not a custom driver?**
`laravel/socialite` v5 does not ship a Slack driver. The `socialiteproviders/slack` community package is the standard approach. It registers via an event listener in `AppServiceProvider::boot()`.

**Why user OAuth tokens, not a bot token?**
The goal is for the agent to see what Chris sees — his private channels, DMs, etc. A bot token only sees channels the bot is explicitly invited to. A user OAuth token with `channels:read channels:history` gives full visibility into the user's workspace.

**Why live proxy, no database persistence?**
Consistent with the mail and calendar integrations. Messages are fetched on demand. No sync jobs, no message table. Keeps the system simple and avoids staleness issues.

**Agent access via HTTP API**
The agent interacts with Shell exclusively over the HTTP API (Sanctum-authenticated). No MCP server is needed — REST endpoints in `routes/api.php` are the integration point.

**`config/services.php` key collision**
The existing `slack.notifications` block is for Laravel's built-in Slack notification channel. OAuth credentials are added as `slack.client_id`, `slack.client_secret`, `slack.redirect` — alongside, not replacing, the notifications block.

**Slack token rotation**
Disabled by default in Slack apps. Do NOT add `slack_refresh_token` or `slack_token_expires_at` columns unless token rotation is explicitly enabled in the Slack app configuration.

## Files Modified/Created

### Database
- `database/migrations/XXXX_add_slack_columns_to_accounts_table.php` — new migration

### Models
- `app/Models/Account.php` — add fillable, casts, `isSlackConnected()`

### Services / Core
- `app/Services/SlackService.php` — new; Slack Web API client (listChannels, listMessages, sendMessage)
- `app/Services/DocPagesService.php` — add `slack` entry to `all()`

### API / Controllers / Responders
- `app/Http/Responders/Auth/RedirectSlackOAuthResponder.php` — new
- `app/Http/Responders/Auth/CallbackSlackOAuthResponder.php` — new

### API / HTTP
- `app/Http/Controllers/Api/SlackController.php` — new; channels, messages, send endpoints

### Views / Frontend
- `resources/docs/slack.md` — new doc page

### Admin / CMS
- `app/Filament/Resources/AccountResource.php` — add `slack_used` icon column
- `app/Filament/Resources/AccountResource/Pages/CreateAccount.php` — add `slack_used` toggle
- `app/Filament/Resources/AccountResource/Pages/EditAccount.php` — add Slack section

### Config
- `config/services.php` — add `slack.client_id`, `slack.client_secret`, `slack.redirect`
- `.env.example` — add `SLACK_CLIENT_ID`, `SLACK_CLIENT_SECRET`, `SLACK_REDIRECT_URI`

### Factories / Seeders
- `database/factories/AccountFactory.php` — add `withSlack()` state

### Providers
- `app/Providers/AppServiceProvider.php` — register Socialite Slack event listener

### Tests
- `tests/Feature/SlackOAuthTest.php` — new
- `tests/Feature/SlackServiceTest.php` — new
- `tests/Feature/SlackReadEndpointsTest.php` — new
- `tests/Feature/SlackSendEndpointTest.php` — new
- `tests/Feature/Filament/AccountResourceTest.php` — update for Slack UI elements
- `tests/Feature/Docs/DocPagesTest.php` — update for Slack doc page

## Testing
🟡 **Not started**: No tests written yet.
- Total tests: 0
- Assertions: 0
- Key coverage:
  - Slack OAuth redirect/callback/disconnect flows
  - SlackService HTTP interactions (Http::fake())
  - MCP tool auth guards (unauthenticated, wrong user, not connected)
  - MCP tool happy paths (channels, messages, send)
  - Filament UI elements (connect/disconnect actions)
  - Docs page (200, content, no auth required)

## Questions/Decisions Needed
- [x] Confirm approval to `composer require socialiteproviders/slack` — ✅ approved
- [x] Slack OAuth scopes: include `im:read` + `im:history` now — ✅ approved
- [x] API auth: Sanctum token only (`auth:sanctum`) — ✅ confirmed
- [x] Agent accesses Slack via HTTP API, not MCP — ✅ confirmed (MCP removed)

## Decisions Made
- User OAuth tokens (not bot tokens) — agent sees what Chris sees
- Live proxy pattern — no message persistence, no background sync
- No token rotation support (not needed for default Slack OAuth v2)
- `config/services.php` `slack.notifications` block left untouched
- `composer require socialiteproviders/slack` — approved
- DM/IM scopes (`im:read`, `im:history`) included in initial OAuth scopes
- HTTP API auth: Sanctum token only (`auth:sanctum`), consistent with rest of API
- MCP server removed — agent interacts via HTTP API only

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Created branch `feature/006-slack-integration` and worktree
- Created feature doc

### Session 2
- Ran phased planning: launched 4 research sub-agents covering OAuth, API client, MCP tools, and docs
- Wrote full implementation plan to feature doc (5 phases)

### Session 3
- Implemented Phase 1: Slack OAuth & Account Storage
  - Added `socialiteproviders/slack` via composer
  - Registered Socialite Slack driver event listener in `AppServiceProvider`
  - Added OAuth credentials to `config/services.php` and `.env.example`
  - Created migration adding `slack_used`, `slack_access_token`, `slack_team_id`, `slack_team_name` to `accounts`
  - Updated `Account` model: fillable, casts, `isSlackConnected()` helper
  - Created `RedirectSlackOAuthResponder` and `CallbackSlackOAuthResponder`
  - Registered `slack.redirect` and `slack.callback` routes
  - Updated Filament: `slack_used` icon column in list, toggle in create, Slack section in edit
  - Added `withSlack()` factory state to `AccountFactory`
  - Created `tests/Feature/SlackOAuthTest.php` (7 tests)
  - Added Slack UI tests to `tests/Feature/Filament/AccountResourceTest.php` (4 tests)
- Implemented Phase 2: Slack API Client & Data Layer
  - Created `app/Services/SlackService.php` with `listChannels`, `listMessages`, `sendMessage`
  - Created `tests/Feature/SlackServiceTest.php` (8 tests, all using `Http::fake()`)

### Session 4
- Removed MCP server: agent interacts via HTTP API only
  - Deleted `routes/ai.php`, `app/Mcp/` directory tree, `tests/Feature/Mcp/` directory tree
  - Removed `laravel/mcp` from `composer.json`
  - Rewrote Phases 3, 4, 5 in feature doc to target HTTP API controllers instead of MCP tools
  - Updated Progress: Phases 3–5 moved back to To Do
  - Updated Requirements, Technical Notes, Files, and Decisions sections throughout
