# Calendar Support

- Branch: `feature/005-calendar-support`
- Related Issue: —
- **Status**: 🟡 In Progress

## Overview

The application currently stores account credentials and IMAP config in a single `Mailbox` model. The goal is to replace `Mailbox` with an `Account` model that holds all IMAP `mail_*` fields and Google Calendar OAuth `google_calendar_*` fields behind service toggles (`mail_used`, `google_calendar_used`), then add full Google Calendar API support.

### Background / Current Behavior

- **Current behavior**: `Mailbox` stores the identity of an account and its IMAP connection config in one model. `MailboxService` connects via IMAP using those fields. There is no concept of an account that can authorise multiple services.
- **Problem**: Calendar access requires Google OAuth2 tokens; there is nowhere to store them, and no concept of a shared account that gates access to multiple services.
- **Where it happens**: `app/Models/Mailbox.php`, `app/Services/MailboxService.php`, `app/Filament/Resources/MailboxResource.php`, `routes/api.php`, `app/Http/Responders/Api/`
- **Impact**: The AI agent cannot access Google Calendar. Credential management is tightly coupled to IMAP config.

### Target Outcome

Replace `Mailbox` with `Account` as the single credential record. `Account` holds IMAP `mail_*` config and Google Calendar OAuth tokens. The AI agent will be able to:

- List accounts and their enabled services
- Browse IMAP folders and messages for a mail-enabled account
- List and show calendar entries for a Google Calendar-connected account

## Route Structure

```
GET /api/accounts                                    → list accounts
GET /api/{account}/mailbox                           → list IMAP folders
GET /api/{account}/mailbox/{folder}/messages         → list messages in folder
GET /api/{account}/mailbox/{folder}/messages/{uid}   → show message
GET /api/{account}/calendar                          → list calendar entries
GET /api/{account}/calendar/{eventId}                → show calendar entry
```

## Requirements

### Functional
- [ ] New `Account` model with `mail_used`, `google_calendar_used`, `mail_*` IMAP fields, `google_calendar_*` OAuth fields, and `is_active`
- [ ] `GET /api/accounts` lists the user's accounts
- [ ] `GET /api/{account}/mailbox` lists IMAP folders for the account
- [ ] `GET /api/{account}/mailbox/{folder}/messages` lists messages in a folder
- [ ] `GET /api/{account}/mailbox/{folder}/messages/{uid}` shows a message
- [ ] `GET /api/{account}/calendar` lists calendar entries for the account
- [ ] `GET /api/{account}/calendar/{eventId}` shows a calendar entry
- [ ] Google Calendar section in Filament has Connect/Disconnect buttons via Socialite OAuth
- [ ] Docs updated: accounts and calendars doc pages

### Non-Functional
- [ ] `google_calendar_access_token` and `google_calendar_refresh_token` stored encrypted
- [ ] Ownership enforced on all routes (`account->user_id === $request->user()->id`)
- [ ] Calendar endpoints check `isGoogleCalendarConnected()`
- [ ] Mail endpoints check `mail_used`
- [ ] Service errors return 503; `HttpException` re-thrown

### UX / Admin
- [ ] Filament `AccountResource` replaces `MailboxResource`; conditional form sections:
  - Mail section: `mail_used` toggle + IMAP fields (host, port, encryption, username, password)
  - Google Calendar section: `google_calendar_used` toggle + Connect/Disconnect header actions
- [ ] Connect redirects to Google OAuth; callback stores tokens on the Account
- [ ] Disconnect clears OAuth tokens and sets `google_calendar_used = false`

### Out of Scope
- Storing calendar or event data in the database (all fetched live)
- Calendar write operations
- SMTP send support

## Implementation Plan

> ✅ **Dependencies approved**: `laravel/socialite` (Phase 2) and `google/apiclient` (Phase 3).

---

### Phase 1 — Replace Mailbox with Account; update all responders and routes

**Scope**: Replace the `Mailbox` model with `Account`, update `MailboxService` to accept `Account`, replace `MailboxResource` with `AccountResource` in Filament, update all responders and routes.

#### 1.1 — Database migrations

**Migration 1** — `create_accounts_table`:
- `id`, `user_id` (FK → users, cascade), `name` (string)
- `mail_used` (boolean, default `true`)
- `mail_host` (string, nullable)
- `mail_port` (integer, nullable)
- `mail_encryption` (string, nullable)
- `mail_username` (string, nullable)
- `mail_password` (text, nullable, encrypted)
- `google_calendar_used` (boolean, default `false`)
- `google_calendar_access_token` (text, nullable, encrypted)
- `google_calendar_refresh_token` (text, nullable, encrypted)
- `google_calendar_token_expires_at` (datetime, nullable)
- `is_active` (boolean, default `true`)
- `timestamps`

**Migration 2** — `drop_mailboxes_table`:
- Drop the `mailboxes` table (data migration not required — dev environment)

#### 1.2 — Account model

Create via `php artisan make:model Account --factory --no-interaction`:

```php
protected function casts(): array
{
    return [
        'mail_password'                    => 'encrypted',
        'google_calendar_access_token'     => 'encrypted',
        'google_calendar_refresh_token'    => 'encrypted',
        'google_calendar_token_expires_at' => 'datetime',
        'mail_used'                        => 'boolean',
        'google_calendar_used'             => 'boolean',
        'is_active'                        => 'boolean',
    ];
}
```

Relationships and methods:
- `user(): BelongsTo` — belongs to `User`
- `isGoogleCalendarConnected(): bool` — `google_calendar_access_token !== null && google_calendar_used`

#### 1.3 — User model

- Add `accounts(): HasMany<Account>`
- Remove `mailboxes(): HasMany<Mailbox>` (Mailbox model is deleted)

#### 1.4 — Delete Mailbox model

Delete:
- `app/Models/Mailbox.php`
- `database/factories/MailboxFactory.php`

#### 1.5 — AccountFactory

`database/factories/AccountFactory.php`:
- `name` (faker company name), `mail_used = true`, `mail_host`, `mail_port = 993`, `mail_encryption = 'ssl'`, `mail_username`, `mail_password`
- `google_calendar_used = false`, OAuth fields `null`, `is_active = true`
- State `inactive()`: `is_active = false`
- State `withGoogleCalendar()`: `google_calendar_used = true`, `google_calendar_access_token = fake()->sha256()`, `google_calendar_refresh_token = fake()->sha256()`, `google_calendar_token_expires_at = now()->addHour()`
- State `mailOnly()`: `google_calendar_used = false`, OAuth fields `null`

#### 1.6 — MailboxService

Update `app/Services/MailboxService.php` to accept `Account` instead of `Mailbox`:
- Replace `Mailbox $mailbox` with `Account $account` in all method signatures
- IMAP config reads from `mail_*` fields: `$account->mail_host`, `$account->mail_port`, etc.

#### 1.7 — Filament resources

**Delete `MailboxResource`** (`app/Filament/Resources/MailboxResource.php` and its `Pages/` directory).

**New `AccountResource`** (`app/Filament/Resources/AccountResource.php`):
- `$model = Account::class`, scoped to `auth()->id()` via `getEloquentQuery()`
- Table columns: `name`, `mail_used` (IconColumn), `google_calendar_used` (IconColumn), `is_active` (IconColumn)
- Form with conditional sections:
  ```
  Section: "Mail"
    Toggle: mail_used  ->live()
    [visible when mail_used is true]
    TextInput: mail_host
    TextInput: mail_port
    Select: mail_encryption  (ssl, tls, none)
    TextInput: mail_username
    TextInput: mail_password  ->password()

  Section: "Google Calendar"
    Toggle: google_calendar_used  ->live()
    [hint: connection status — shown when record exists]
  ```
  Connect/Disconnect are header actions on `EditAccount` (stubbed in Phase 1, wired in Phase 2)
- Pages: `ListAccounts`, `CreateAccount`, `EditAccount`

#### 1.8 — API resources

New `AccountResource.php` (`app/Http/Resources/`):
- `toArray()`: `id`, `name`, `mail_used`, `google_calendar_used`, `is_active`

Delete `app/Http/Resources/MailboxResource.php` (replaced by `AccountResource`).

#### 1.9 — API responders

**New `ListAccountsResponder.php`**:
```php
public function __invoke(Request $request): AnonymousResourceCollection
{
    $accounts = $request->user()->accounts()->where('is_active', true)->get();

    return AccountResource::collection($accounts);
}
```

**Delete `ListMailboxesResponder.php`** (no longer needed).

**Update `ListFoldersResponder`**: replace `Mailbox $mailbox` with `Account $account`:
```php
public function __invoke(Request $request, Account $account): JsonResponse
{
    abort_unless($account->user_id === $request->user()->id, 404);
    abort_unless($account->mail_used, 404);

    try {
        $imapMailbox = $this->mailboxService->connect($account);
        // ... rest unchanged
    } catch (\Throwable $e) { ... }
}
```

**Update `ListMessagesResponder`**: same — replace `Mailbox $mailbox` with `Account $account`, same ownership + `mail_used` checks.

**Update `ShowMessageResponder`**: same.

#### 1.10 — Routes

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/accounts', ListAccountsResponder::class);

    Route::get('/{account}/mailbox', ListFoldersResponder::class);
    Route::get('/{account}/mailbox/{folder}/messages', ListMessagesResponder::class);
    Route::get('/{account}/mailbox/{folder}/messages/{uid}', ShowMessageResponder::class);
});
```

Remove old `/mailboxes` routes.

#### 1.11 — Tests

- New `tests/Feature/AccountTest.php` — model, factory, relationships, `isGoogleCalendarConnected()`
- New `tests/Feature/Api/AccountApiTest.php` — list accounts, auth required, ownership
- New `tests/Feature/Api/MailboxApiTest.php` (replaces old) — new route structure, ownership checks, `mail_used` gate
- Update `tests/Feature/Api/MessageApiTest.php` — new route structure (`/{account}/mailbox/{folder}/messages/{uid}`)
- Update `tests/Unit/MailboxServiceTest.php` — use `Account` factory instead of `Mailbox`
- New `tests/Feature/Filament/AccountResourceTest.php`
- Delete `tests/Feature/Filament/MailboxResourceTest.php` (replaced)

- Run: `php artisan test --compact`
- Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 2 — Google OAuth via Socialite

**Scope**: Install Socialite, add OAuth redirect/callback responders, wire Connect/Disconnect into Filament.

> ✅ **Approved**: `composer require laravel/socialite`

#### 2.1 — Composer + config

```bash
composer require laravel/socialite --no-interaction
```

Add to `config/services.php`:
```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('GOOGLE_REDIRECT_URI'),
],
```

Add to `.env.example`: `GOOGLE_CLIENT_ID=`, `GOOGLE_CLIENT_SECRET=`, `GOOGLE_REDIRECT_URI=`

#### 2.2 — OAuth responders

Following the responder pattern, place in `app/Http/Responders/Auth/`:

**`RedirectGoogleOAuthResponder.php`**:
```php
public function __invoke(Request $request, Account $account): RedirectResponse
{
    abort_unless($account->user_id === $request->user()->id, 403);

    session(['google_oauth_account_id' => $account->id]);

    return Socialite::driver('google')
        ->scopes(['https://www.googleapis.com/auth/calendar.readonly'])
        ->with(['access_type' => 'offline', 'prompt' => 'consent'])
        ->redirect();
}
```

**`CallbackGoogleOAuthResponder.php`**:
```php
public function __invoke(Request $request): RedirectResponse
{
    $accountId = session()->pull('google_oauth_account_id');
    $account = Account::findOrFail($accountId);

    abort_unless($account->user_id === $request->user()->id, 403);

    $socialiteUser = Socialite::driver('google')->user();

    $account->update([
        'google_calendar_access_token'     => $socialiteUser->token,
        'google_calendar_refresh_token'    => $socialiteUser->refreshToken,
        'google_calendar_token_expires_at' => now()->addSeconds($socialiteUser->expiresIn),
        'google_calendar_used'             => true,
    ]);

    return redirect()->to(AccountResource::getUrl('edit', ['record' => $account]))->with('success', 'Google Calendar connected.');
}
```

#### 2.3 — OAuth routes

In `routes/web.php` inside an `auth` middleware group:
```php
Route::get('/auth/google/{account}/redirect', RedirectGoogleOAuthResponder::class)->name('google.redirect');
Route::get('/auth/google/callback', CallbackGoogleOAuthResponder::class)->name('google.callback');
```

#### 2.4 — Filament Connect/Disconnect actions

In `EditAccount.php`, wire the header actions:

```php
Actions\Action::make('connectGoogle')
    ->label('Connect Google Account')
    ->url(fn (Account $record): string => route('google.redirect', $record))
    ->visible(fn (Account $record): bool => $record->google_calendar_used && ! $record->isGoogleCalendarConnected())

Actions\Action::make('disconnectGoogle')
    ->label('Disconnect Google')
    ->requiresConfirmation()
    ->action(function (Account $record): void {
        $record->update([
            'google_calendar_access_token'     => null,
            'google_calendar_refresh_token'    => null,
            'google_calendar_token_expires_at' => null,
            'google_calendar_used'             => false,
        ]);
        Notification::make()->title('Google Calendar disconnected.')->success()->send();
    })
    ->visible(fn (Account $record): bool => $record->isGoogleCalendarConnected())
```

#### 2.5 — Tests

`tests/Feature/GoogleOAuthTest.php`:
- Redirect route → 302, ownership enforced (403 for wrong user)
- Callback with mocked Socialite → tokens stored, redirected to Filament edit page
- Disconnect action → tokens cleared, `google_calendar_used = false`

- Run: `php artisan test --compact --filter=GoogleOAuth`
- Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 3 — CalendarService

**Scope**: Install `google/apiclient`, build `CalendarService`.

> ✅ **Approved**: `composer require google/apiclient`

#### 3.1 — Composer

```bash
composer require google/apiclient --no-interaction
```

#### 3.2 — CalendarService

Create `app/Services/CalendarService.php`:

- `connect(Account $account): \Google\Service\Calendar` — builds `\Google\Client`, sets token from `google_calendar_access_token`; refreshes if expired using `google_calendar_refresh_token`; persists updated token back to the account
- `listEntries(Account $account, int $maxResults = 50, ?string $pageToken = null): array` — fetches from primary calendar; params: `singleEvents=true`, `orderBy=startTime`, `timeMin=now()`; returns `['items' => [...], 'nextPageToken' => ?string]`; maps items to `['id', 'title', 'start', 'end', 'location']`
- `getEntry(Account $account, string $eventId): array` — fetches from primary calendar; maps to `['id', 'title', 'description', 'start', 'end', 'location', 'attendees', 'html_link']`

#### 3.3 — Tests

`tests/Unit/CalendarServiceTest.php` — mock Google client classes; test each method maps correctly; test token refresh is triggered and persisted.

- Run: `php artisan test --compact --filter=CalendarService`
- Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 4 — Calendar API Endpoints

**Scope**: Add calendar routes, responders, and API resources.

#### 4.1 — Routes

Add to the `auth:sanctum` group in `routes/api.php`:

```php
Route::get('/{account}/calendar', ListCalendarEntriesResponder::class);
Route::get('/{account}/calendar/{eventId}', ShowCalendarEntryResponder::class);
```

`{eventId}` is a plain string (Google event ID), not model-bound.

#### 4.2 — API resources

- `CalendarEntrySummaryResource` (list): `id`, `title`, `start`, `end`, `location`
- `CalendarEntryResource` (detail): `id`, `title`, `description`, `start`, `end`, `location`, `attendees`, `html_link`

#### 4.3 — Responders

All in `app/Http/Responders/Api/`. Each follows the established pattern:
- Ownership check: `abort_unless($account->user_id === $request->user()->id, 404)`
- Service check: `abort_unless($account->isGoogleCalendarConnected(), 404)`
- `try/catch(\Throwable)` — rethrow `HttpException`, else `abort(503)`

**`ListCalendarEntriesResponder`** — `CalendarService` injected; reads `$page`/`$perPage` (cap 50); returns `CalendarEntrySummaryResource::collection(...)` with `->additional(['meta' => [...]])`

**`ShowCalendarEntryResponder`** — `CalendarService` injected; returns `new CalendarEntryResource($entry)`

#### 4.4 — Tests

`tests/Feature/Api/CalendarApiTest.php`:
- unauthenticated → 401
- wrong user → 404
- not connected → 404
- list entries → 200, correct shape
- list entries with pagination → 200, meta
- show entry → 200, full shape
- service throws → 503

Mock `CalendarService` throughout.

- Run: `php artisan test --compact --filter=CalendarApi`
- Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 5 — Documentation Updates

#### 5.1 — Update `resources/docs/mailboxes.md`

- Update route examples to new structure (`/{account}/mailbox/...`)
- Note that IMAP config is managed per-account in admin

#### 5.2 — Create `resources/docs/calendars.md`

- Intro: requires Account with `google_calendar_used` connected via admin OAuth flow
- Endpoints: list calendar entries (pagination), show entry — with curl examples and response shapes
- Error responses: 401, 404, 503

#### 5.3 — `DocPagesService`

Add `'calendars' => ['title' => 'Calendars', 'file' => 'calendars.md']`.

#### 5.4 — Tests

Update `DocPagesTest.php`: assert calendars appears in index; `/docs/calendars` → 200; update mailboxes URL assertions.

- Run: `php artisan test --compact --filter=DocPages`
- Run: `vendor/bin/pint --dirty --format agent`

---

## Progress

### Completed
- Worktree and feature doc created
- Planning complete

### Blocked
- Nothing

### To Do
- Phase 1: Replace Mailbox with Account; update all responders and routes
- Phase 2: Google OAuth via Socialite
- Phase 3: CalendarService
- Phase 4: Calendar API Endpoints
- Phase 5: Docs

## Technical Notes

- **`Account` replaces `Mailbox` entirely**: no sub-model. All IMAP config lives on `Account` as `mail_*` fields. All Google Calendar OAuth lives as `google_calendar_*` fields.
- **`MailboxService` is renamed in spirit but not necessarily in name**: it still handles IMAP operations, now accepting `Account` instead of `Mailbox`.
- **`isGoogleCalendarConnected()`**: `google_calendar_access_token !== null && google_calendar_used`. Used in Filament action visibility and API service checks.
- **OAuth responders in `app/Http/Responders/Auth/`**: follows the established responder pattern.
- **`access_type=offline` + `prompt=consent`**: required to receive a refresh token on every authorisation.
- **Primary calendar only**: no `{calendarId}` in routes — 1 account = 1 calendar context (Google primary).
- **No DB storage for calendar data**: all fetched live.

## Files Modified/Created

### Database
- `database/migrations/*_create_accounts_table.php` — new
- `database/migrations/*_drop_mailboxes_table.php` — new

### Models
- `app/Models/Account.php` — new
- `app/Models/Mailbox.php` — deleted
- `app/Models/User.php` — replace `mailboxes()` HasMany with `accounts()` HasMany

### Services
- `app/Services/MailboxService.php` — update to accept `Account` instead of `Mailbox`
- `app/Services/CalendarService.php` — new
- `app/Services/DocPagesService.php` — add calendars entry

### Responders
- `app/Http/Responders/Api/ListAccountsResponder.php` — new
- `app/Http/Responders/Api/ListMailboxesResponder.php` — deleted
- `app/Http/Responders/Api/ListFoldersResponder.php` — update to accept `Account` (no `Mailbox`)
- `app/Http/Responders/Api/ListMessagesResponder.php` — update to accept `Account` (no `Mailbox`)
- `app/Http/Responders/Api/ShowMessageResponder.php` — update to accept `Account` (no `Mailbox`)
- `app/Http/Responders/Api/ListCalendarEntriesResponder.php` — new
- `app/Http/Responders/Api/ShowCalendarEntryResponder.php` — new
- `app/Http/Responders/Auth/RedirectGoogleOAuthResponder.php` — new
- `app/Http/Responders/Auth/CallbackGoogleOAuthResponder.php` — new
- `routes/api.php` — updated
- `routes/web.php` — add OAuth routes

### Views / Docs
- `resources/docs/mailboxes.md` — update route examples
- `resources/docs/calendars.md` — new

### Admin
- `app/Filament/Resources/AccountResource.php` — new
- `app/Filament/Resources/AccountResource/Pages/ListAccounts.php` — new
- `app/Filament/Resources/AccountResource/Pages/CreateAccount.php` — new
- `app/Filament/Resources/AccountResource/Pages/EditAccount.php` — new
- `app/Filament/Resources/MailboxResource.php` — deleted
- `app/Filament/Resources/MailboxResource/Pages/` — deleted

### API Resources
- `app/Http/Resources/AccountResource.php` — new
- `app/Http/Resources/MailboxResource.php` — deleted
- `app/Http/Resources/CalendarEntrySummaryResource.php` — new
- `app/Http/Resources/CalendarEntryResource.php` — new

### Factories
- `database/factories/AccountFactory.php` — new
- `database/factories/MailboxFactory.php` — deleted

### Tests
- `tests/Feature/AccountTest.php` — new
- `tests/Feature/Api/AccountApiTest.php` — new
- `tests/Feature/Api/MailboxApiTest.php` — replaced (new routes, Account-based)
- `tests/Feature/Api/MessageApiTest.php` — updated (new route structure)
- `tests/Feature/Api/CalendarApiTest.php` — new
- `tests/Feature/GoogleOAuthTest.php` — new
- `tests/Unit/MailboxServiceTest.php` — updated (use Account factory)
- `tests/Unit/CalendarServiceTest.php` — new
- `tests/Feature/Filament/AccountResourceTest.php` — new
- `tests/Feature/Filament/MailboxResourceTest.php` — deleted
- `tests/Feature/Docs/DocPagesTest.php` — updated

## Questions/Decisions Needed
- [ ] **Token refresh failures** — 503 or specific re-auth error?

## Decisions Made
- `Account` replaces `Mailbox` entirely — no separate Mailbox sub-model
- `Account` holds `mail_*` IMAP fields and `google_calendar_*` OAuth fields
- Routes: `GET /api/accounts`, `GET /api/{account}/mailbox`, `GET /api/{account}/mailbox/{folder}/messages`, `GET /api/{account}/mailbox/{folder}/messages/{uid}`, `GET /api/{account}/calendar`, `GET /api/{account}/calendar/{eventId}`
- OAuth handled by responders in `app/Http/Responders/Auth/` (not controllers)
- `google_calendar_*` prefix on all OAuth fields; `mail_*` prefix on all IMAP fields
- Read-only calendar scope (`calendar.readonly`)
- No DB storage for calendar data
- `laravel/socialite` and `google/apiclient` approved for installation

## Pre-Commit Checklist
- [ ] `composer format`
- [ ] `composer test`
- [ ] Feature doc updated
- **Never commit unless explicitly asked.**

## Session History

### Session 1
- Worktree created; research and planning
- Multiple plan iterations: Account naming, field prefixes, OAuth via Socialite, form sections
- Final architecture: `Account` replaces `Mailbox` (all IMAP `mail_*` fields + Google Calendar `google_calendar_*` fields on `Account`)
- Routes: `GET /api/accounts`, `GET /api/{account}/mailbox`, `GET /api/{account}/mailbox/{folder}/messages`, `GET /api/{account}/mailbox/{folder}/messages/{uid}`, `GET /api/{account}/calendar`, `GET /api/{account}/calendar/{eventId}`
- OAuth via responders in `app/Http/Responders/Auth/`
