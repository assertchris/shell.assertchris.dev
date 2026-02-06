# Add Email Proxy

- Branch: `feature/orphan-2-add-email-proxy`
- Related Issue: orphan-2
- **Status**: ✅ Complete
- Examples: 🟡 In Progress / ✅ Complete / 🔴 Blocked

## Overview

Shell is a read-only proxy between personal assistants and external services. Currently the app is a scaffold with a Filament admin panel but no functional features. There is no way for an assistant to authenticate with the app or read any external data.

This feature adds IMAP email support so the admin can store credentials for multiple Gmail accounts, issue API tokens to assistants, and those assistants can read mail through a JSON API — without ever being able to write, send, or modify anything.

### Background / Current Behavior
- **Current behavior**: No API, no token auth, no mailbox management
- **Problem**: Assistants have no way to read email on the admin's behalf
- **Where it happens**: Entire app — no models, controllers, or API routes exist yet
- **Impact**: The core value proposition of the app is undelivered

### Target Outcome
This feature will add token-authenticated read-only email access so that personal assistants can:
- Authenticate via bearer token to a JSON API
- List configured mailboxes and their folders
- Read email messages (subject, sender, body, metadata) without any write capability

## Requirements

### Functional
- [x] Admin can create/revoke API access tokens via Filament
- [x] Admin can add/edit/delete IMAP mailbox credentials via Filament
- [x] Admin can test mailbox connections from the admin panel
- [x] API accepts bearer token authentication
- [x] API lists mailboxes (id and name only — no credentials)
- [x] API lists folders for a mailbox
- [x] API lists messages in a folder
- [x] API returns a single message with full body

### Non-Functional
- [x] Security: Tokens stored as SHA-256 hashes, mailbox passwords encrypted at rest
- [x] Security: No write/send/delete operations exposed via API
- [x] Security: Mailbox credentials never exposed via API responses

### Out of Scope
- Sending or modifying email
- Webhook/push notifications for new mail
- Attachment file downloads
- Multi-user/multi-tenant support

## Implementation Plan

### Phase 1: Access Tokens
- Migration: `access_tokens` table (user_id, name, token hash, last_used_at, expires_at)
- Model: `AccessToken` with `generate()` static method (Str::random(64), store SHA-256 hash)
- Filament Resource: list, create (show plain token once), delete
- Middleware: `AuthenticateAccessToken` — validates bearer token, updates last_used_at
- Routes: `routes/api.php` with `GET /api/status` health check behind auth
- Tests: token generation, middleware accept/reject, Filament CRUD, API endpoint

### Phase 2: IMAP Mailbox Management (Admin)
- Install `directorytree/imapengine-laravel`
- Migration: `mailboxes` table (user_id, name, host, port, username, password (encrypted), encryption, is_active)
- Model: `Mailbox` with `encrypted` password cast, `toImapConfig()` method
- Filament Resource: list, create, edit, delete, "Test Connection" action
- Tests: model creation, encrypted storage, Filament CRUD, connection test (mocked)

### Phase 3: Read-Only Email API
- Service: `MailboxService` — connects to IMAP via `Imap::build()`, fetches folders/messages
- Controllers: `Api\MailboxController`, `Api\MessageController`
- API Resources: `MailboxResource`, `FolderResource`, `MessageSummaryResource`, `MessageResource`
- Routes: `GET /api/mailboxes`, `.../folders`, `.../messages`, `.../messages/{uid}`
- Tests: each endpoint, auth required, no credential leaks, service unit tests

## Progress

### Completed
- Feature doc created
- Phase 1: Access Tokens (Sanctum install, API routes, Filament resource, tests)
- Phase 2: IMAP Mailbox Management (model, migration, factory, Filament resource, test connection, tests)
- Phase 3: Read-Only Email API (MailboxService, controllers, API resources, routes, tests)

### In Progress
- None

### To Do
- None — all phases complete

## Technical Notes

- Mailbox credentials stored in DB (not config files) since admin adds them dynamically
- Use `Imap::build($config)` to create mailbox instances from DB-stored credentials at runtime
- Plain tokens shown once on creation, never retrievable again
- ImapEngine does not require the PHP IMAP extension (pure PHP implementation)

## Files Modified/Created

### Database
- `database/migrations/2026_02_06_072704_create_personal_access_tokens_table.php` (Sanctum published)
- `database/migrations/2026_02_06_075015_create_mailboxes_table.php`

### Models
- `app/Models/User.php` — added `HasApiTokens` trait, `mailboxes()` relationship
- `app/Models/Mailbox.php` — encrypted password cast, `toImapConfig()`, `belongsTo(User)`

### Factories
- `database/factories/MailboxFactory.php` — realistic defaults, `inactive()` state

### Config
- `config/sanctum.php` (Sanctum published)

### Services
- `app/Services/MailboxService.php` — connects to IMAP, fetches folders/messages

### API / Routes
- `routes/api.php` — `GET /api/status`, mailbox/folder/message endpoints behind `auth:sanctum`
- `bootstrap/app.php` — registered API routes

### API Resources
- `app/Http/Resources/MailboxResource.php` — `{id, name}` only (no credentials)
- `app/Http/Resources/FolderResource.php` — `{path, name}`
- `app/Http/Resources/MessageSummaryResource.php` — `{uid, subject, from, to, date, has_attachments}`
- `app/Http/Resources/MessageResource.php` — full message with `{cc, text, html, attachment_count}`

### Responders
- `app/Http/Responders/Api/ListMailboxesResponder.php` — list active mailboxes
- `app/Http/Responders/Api/ListFoldersResponder.php` — list IMAP folders
- `app/Http/Responders/Api/ListMessagesResponder.php` — paginated message summaries
- `app/Http/Responders/Api/ShowMessageResponder.php` — full message with body

### Admin / CMS
- `app/Filament/Resources/AccessTokenResource.php` — removed action icons
- `app/Filament/Resources/AccessTokenResource/Pages/ListAccessTokens.php`
- `app/Filament/Resources/AccessTokenResource/Pages/CreateAccessToken.php`
- `app/Filament/Resources/MailboxResource.php` — list, create, edit, delete, test connection, removed action icons
- `app/Filament/Resources/MailboxResource/Pages/ListMailboxes.php`
- `app/Filament/Resources/MailboxResource/Pages/CreateMailbox.php`
- `app/Filament/Resources/MailboxResource/Pages/EditMailbox.php`

### Tests
- `tests/Feature/Api/StatusTest.php` — API auth tests
- `tests/Feature/AccessTokenTest.php` — token model tests
- `tests/Feature/Filament/AccessTokenResourceTest.php` — Filament CRUD tests
- `tests/Feature/Filament/AdminSmokeTest.php` — smoke tests for all admin pages
- `tests/Feature/MailboxTest.php` — model, encrypted cast, toImapConfig, relationships
- `tests/Feature/Filament/MailboxResourceTest.php` — Filament CRUD, scoping, test connection (mocked)
- `tests/Unit/MailboxServiceTest.php` — service unit tests (connect, getFolders, getFolder, getMessages, getMessage)
- `tests/Feature/Api/MailboxApiTest.php` — list mailboxes, list folders, auth, scoping, connection failure
- `tests/Feature/Api/MessageApiTest.php` — list messages, show message, pagination, folder/uid 404s, auth

## Testing
✅ **Phase 3 complete**: 60 passing tests
- Total tests: 60
- Assertions: 158

## Decisions Made
- **Token storage**: SHA-256 hash (not bcrypt) for fast lookup on every API request
- **Mailbox passwords**: Laravel `encrypted` cast for at-rest encryption in DB
- **IMAP package**: `directorytree/imapengine-laravel` — pure PHP, no ext-imap needed, supports dynamic mailbox config

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Explored codebase: Laravel 12, Filament 5, SQLite, Pest v4, no custom code yet
- Researched ImapEngine package: installation, multi-mailbox support, read operations
- Created feature doc and implementation plan (3 phases)

### Session 2
- Planned Phase 1 implementation approach

### Session 3
- Implemented Phase 1: Access Tokens
- Installed `laravel/boost` (dev) and `laravel/sanctum`
- Published Sanctum config/migration, ran migration
- Added `HasApiTokens` to User model
- Created `routes/api.php` with `GET /api/status` behind `auth:sanctum`
- Registered API routes in `bootstrap/app.php`
- Created Filament `AccessTokenResource` with list, create, delete (no edit — tokens are immutable)
- Filament 5 notes: `$navigationIcon` type is `string|BackedEnum|null`, actions moved to `Filament\Actions` namespace, use `Heroicon` enum
- Wrote 14 new tests (3 API auth, 3 token model, 5 Filament CRUD, 3 admin smoke)
- All 16 tests passing (23 assertions)

### Session 4
- Implemented Phase 2: IMAP Mailbox Management
- Installed `directorytree/imapengine-laravel` v1.2.0
- Created `Mailbox` model with encrypted password cast, `toImapConfig()` method, `belongsTo(User)`
- Created migration with user_id, name, host, port, encryption, username, password, is_active columns
- Created `MailboxFactory` with realistic defaults and `inactive()` state
- Added `mailboxes()` relationship to User model
- Created Filament `MailboxResource` with list, create, edit, delete, "Test Connection" action
- Test Connection uses `Imap::build()` with try/catch, shows success/failure notification
- Edit page keeps current password when left blank via `dehydrateStateUsing()`
- Wrote 19 new tests (6 model, 10 resource CRUD + scoping + connection test, 3 admin smoke)
- All 35 tests passing (67 assertions)

### Session 5
- Implemented Phase 3: Read-Only Email API
- Created `MailboxService` with connect, getFolders, getFolder, getMessages, getMessage methods
- Created 4 API Resources: MailboxResource (id/name only), FolderResource, MessageSummaryResource, MessageResource
- Created `MailboxController` (index: list active mailboxes, folders: list IMAP folders)
- Created `MessageController` (index: paginated message summaries, show: full message with body)
- Added 4 routes: GET mailboxes, folders, messages, messages/{uid} — all behind `auth:sanctum`
- Controllers scope mailbox to authenticated user (404 if mismatch), catch IMAP failures as 503
- MessageController caps per_page at 50, re-throws HTTP exceptions through catch block
- Uses ImapEngine's own LengthAwarePaginator (not Laravel's) for message pagination
- Removed action icons from AccessTokenResource and MailboxResource edit/delete actions
- Wrote 7 unit tests for MailboxService + 8 feature tests for mailbox API + 8 feature tests for message API
- All 60 tests passing (158 assertions)
