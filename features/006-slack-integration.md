# Slack Integration

- Branch: `feature/006-slack-integration`
- Related Issue: N/A
- **Status**: 🟡 In Progress

## Overview

Shell is a personal AI agent host that proxies external services (mail, calendar) so the agent can act on the user's behalf. Currently, the agent can read emails and calendar events, but has no awareness of Slack — meaning it cannot see conversations happening there or participate in them.

This feature adds full Slack integration: the user connects their Slack workspace via OAuth, and the agent gains MCP tools to list channels, read messages, and send messages. The agent acts as the user, using their credentials, not a shared bot token.

### Background / Current Behavior

- **Current behavior**: Shell supports mail (IMAP) and Google Calendar as proxied services. No Slack integration exists.
- **Problem**: The agent cannot see or interact with Slack, limiting its usefulness as a personal assistant.
- **Where it happens**: `app/Services/`, `app/Mcp/` (not yet scaffolded), `app/Filament/` (Accounts UI)
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
- [ ] Agent can list all channels visible to the connected Slack account
- [ ] Agent can read messages from a specific channel
- [ ] Agent can send a message to a specific channel
- [ ] MCP endpoint is authenticated (requires a valid Sanctum token)
- [ ] All Slack credentials are stored encrypted at rest

### Non-Functional

- [ ] Observability: Slack API errors return 503 with contextual message, consistent with mail/calendar pattern
- [ ] Performance: Slack API calls are live-proxied (no caching or background sync); Slack rate limits handled gracefully
- [ ] Security/Permissions: Slack OAuth token stored encrypted; MCP endpoint requires `auth:sanctum`; account ownership verified before using credentials
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

### Phase 3 — MCP Server & Read Tools

**Goal:** Scaffold the MCP server and expose tools for listing channels and reading messages.

#### Step 3.1 — Add `laravel/mcp` to composer.json

The package is in vendor but not yet in `composer.json`. Add it:

```bash
composer require laravel/mcp --no-interaction
```

#### Step 3.2 — Create `routes/ai.php`

The `McpServiceProvider` auto-loads this file. Create it:

```php
<?php

use App\Mcp\Servers\ShellServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', ShellServer::class)->middleware('auth:sanctum');
```

The `auth:sanctum` middleware ensures only authenticated users can call MCP tools. `$request->user()` will be available in tool handlers.

#### Step 3.3 — Create `ShellServer`

```bash
php artisan make:mcp-server ShellServer --no-interaction
```

Path: `app/Mcp/Servers/ShellServer.php`

Register tools:
```php
protected array $tools = [
    \App\Mcp\Tools\ListSlackChannelsTool::class,
    \App\Mcp\Tools\ReadSlackMessagesTool::class,
];
```

#### Step 3.4 — Create `ListSlackChannelsTool`

```bash
php artisan make:mcp-tool ListSlackChannelsTool --no-interaction
```

Path: `app/Mcp/Tools/ListSlackChannelsTool.php`

```php
protected string $description = 'List all Slack channels visible to the connected account.';

public function schema(JsonSchema $schema): array
{
    return [
        'account_id' => $schema->integer('The Account ID to use for the Slack connection')->required(),
        'cursor'     => $schema->string('Pagination cursor from a previous response')->nullable(),
    ];
}

public function handle(Request $request): Response
{
    $request->validate(['account_id' => ['required', 'integer']]);

    $account = Account::query()->findOrFail($request->get('account_id'));
    abort_unless($account->user_id === $request->user()->id, 404);
    abort_unless($account->isSlackConnected(), 404);

    try {
        $channels = app(SlackService::class)->listChannels($account, $request->get('cursor'));
        return Response::json($channels);
    } catch (\Throwable $e) {
        return Response::error('Slack API unavailable: ' . $e->getMessage());
    }
}
```

#### Step 3.5 — Create `ReadSlackMessagesTool`

```bash
php artisan make:mcp-tool ReadSlackMessagesTool --no-interaction
```

Path: `app/Mcp/Tools/ReadSlackMessagesTool.php`

Schema inputs: `account_id` (required integer), `channel_id` (required string), `limit` (optional integer, default 50), `cursor` (optional string).

Delegates to `SlackService::listMessages()`. Returns `Response::json([...])`.

#### Step 3.6 — Tests

Create `tests/Feature/Mcp/ListSlackChannelsToolTest.php`:
- Unauthenticated request returns error
- Wrong account (not owned by user) returns error
- Account not Slack-connected returns error
- Happy path returns channel list

Create `tests/Feature/Mcp/ReadSlackMessagesToolTest.php`:
- Same auth/ownership/connected guards
- Happy path returns messages + next_cursor
- Service error returns MCP error response

Use `ShellServer::tool(ListSlackChannelsTool::class, [...])->actingAs($user)->assertSee(...)` pattern.
Mock `SlackService` with `$this->mock(SlackService::class, ...)`.

---

### Phase 4 — MCP Send Tool

**Goal:** Add a `SendSlackMessageTool` so the agent can post messages to Slack on the user's behalf.

#### Step 4.1 — Create `SendSlackMessageTool`

```bash
php artisan make:mcp-tool SendSlackMessageTool --no-interaction
```

Path: `app/Mcp/Tools/SendSlackMessageTool.php`

Schema inputs: `account_id` (required integer), `channel_id` (required string), `text` (required string).

Same auth/ownership/connected guards as read tools. Delegates to `SlackService::sendMessage()`. Returns `Response::text("Message sent (ts: {$result['ts']})")`.

#### Step 4.2 — Register in `ShellServer`

Add `SendSlackMessageTool::class` to `$tools` array.

#### Step 4.3 — Tests

Create `tests/Feature/Mcp/SendSlackMessageToolTest.php`:
- Unauthenticated request returns error
- Wrong account returns error
- Account not Slack-connected returns error
- Missing required fields (`channel_id`, `text`) return validation error
- Happy path returns success message with ts
- Service error returns MCP error response

---

### Phase 5 — Documentation

**Goal:** Add a Slack doc page following the exact pattern of `mailboxes.md` and `calendars.md`, register it in `DocPagesService`, and update tests.

#### Step 5.1 — Create `resources/docs/slack.md`

Follow the `mailboxes.md` format exactly:
- H1: `# Slack`
- Auth reminder + required headers code block
- Setup note: "Slack must be connected via OAuth in the admin panel (Accounts → Edit → Slack section)"
- Three endpoint sections:

```
---
## GET /mcp  (tool: list_slack_channels)
## GET /mcp  (tool: read_slack_messages)
## POST /mcp (tool: send_slack_message)
```

Since MCP tools don't follow REST route patterns, the doc should describe the tool names, their input parameters, and example JSON request/response shapes. See `authentication.md` for style guidance on non-standard flows.

Each tool section should include:
- Parameter table (name, type, required, description)
- Example MCP request JSON
- Example success response JSON
- Error responses table (401 unauthenticated, 404 account not found/not connected, 503 Slack API unavailable)

#### Step 5.2 — Update `DocPagesService`

In `app/Services/DocPagesService.php`, add to the `all()` return array:

```php
'slack' => ['title' => 'Slack', 'file' => 'slack.md'],
```

#### Step 5.3 — Update `DocPagesTest.php`

In `tests/Feature/Docs/DocPagesTest.php`:
- Add `assertSee('Slack')` to the docs index test
- Add test: `/docs/slack` returns 200
- Add `/docs/slack` to the "no auth required" assertions

---

## Progress

### Completed
- Branch and worktree created
- Feature doc written with full implementation plan
- Phase 1: Slack OAuth & Account Storage
- Phase 2: Slack API Client & Data Layer
- Phase 3: MCP Server & Read Tools

### In Progress
- (none)

### Blocked
- (none)

### To Do
- Phase 4: MCP Send Tool
- Phase 5: Documentation

## Technical Notes

**Why `socialiteproviders/slack` and not a custom driver?**
`laravel/socialite` v5 does not ship a Slack driver. The `socialiteproviders/slack` community package is the standard approach. It registers via an event listener in `AppServiceProvider::boot()`.

**Why user OAuth tokens, not a bot token?**
The goal is for the agent to see what Chris sees — his private channels, DMs, etc. A bot token only sees channels the bot is explicitly invited to. A user OAuth token with `channels:read channels:history` gives full visibility into the user's workspace.

**Why live proxy, no database persistence?**
Consistent with the mail and calendar integrations. Messages are fetched on demand. No sync jobs, no message table. Keeps the system simple and avoids staleness issues.

**`laravel/mcp` in vendor but not `composer.json`**
The package was likely installed via the monorepo or Boost tooling. It must be explicitly added to `composer.json` with `composer require laravel/mcp` so it's tracked as a project dependency.

**MCP route auth**
`Mcp::web('/mcp', ShellServer::class)->middleware('auth:sanctum')` — the MCP POST endpoint will require a Sanctum bearer token, the same as the REST API. `$request->user()` is available in tool `handle()` methods.

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

### MCP
- `routes/ai.php` — new; registers ShellServer on `/mcp` with `auth:sanctum`
- `app/Mcp/Servers/ShellServer.php` — new; lists all tools
- `app/Mcp/Tools/ListSlackChannelsTool.php` — new
- `app/Mcp/Tools/ReadSlackMessagesTool.php` — new
- `app/Mcp/Tools/SendSlackMessageTool.php` — new

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
- `tests/Feature/Mcp/ListSlackChannelsToolTest.php` — new
- `tests/Feature/Mcp/ReadSlackMessagesToolTest.php` — new
- `tests/Feature/Mcp/SendSlackMessageToolTest.php` — new
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
- [x] Confirm approval to `composer require laravel/mcp` — ✅ approved
- [x] Slack OAuth scopes: include `im:read` + `im:history` now — ✅ approved
- [x] MCP auth: Sanctum token only (`auth:sanctum`) — ✅ confirmed

## Decisions Made
- User OAuth tokens (not bot tokens) — agent sees what Chris sees
- Live proxy pattern — no message persistence, no background sync
- No token rotation support (not needed for default Slack OAuth v2)
- `config/services.php` `slack.notifications` block left untouched
- `composer require socialiteproviders/slack` — approved
- `composer require laravel/mcp` — approved
- DM/IM scopes (`im:read`, `im:history`) included in initial OAuth scopes
- MCP endpoint auth: Sanctum token only (`auth:sanctum`), no web session auth needed

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
