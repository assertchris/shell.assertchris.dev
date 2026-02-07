# Dashboard Messages

- Branch: `feature/orphan-5-add-dashboard`
- Related Issue: N/A
- **Status**: ✅ Complete
- Examples: 🟡 In Progress / ✅ Complete / 🔴 Blocked

## Overview

The application is a personal assistant shell. An external AI assistant interacts with the app via Sanctum-authenticated API endpoints (e.g., reading mailboxes). Currently there's no way for the assistant to **push updates back** to the user. This feature adds a dashboard message system so the assistant can post notifications (e.g., urgent email summaries with HTML content) that the user sees when they log into the Filament admin panel.

### Background / Current Behavior
- **Current behavior**: The assistant can read data (mailboxes, etc.) via API but has no way to push information back to the user
- **Problem**: No notification/message channel from assistant to user
- **Where it happens**: API layer and Filament admin panel
- **Impact**: The user must manually check external sources for updates; the assistant cannot proactively surface important information

### Target Outcome
This feature will add a dashboard message system so that the assistant can:
- Post messages (with HTML content, priority levels) via API
- User can view, read, archive, and delete messages in the Filament admin panel
- User sees unread message count in the navigation badge and a widget on the dashboard

## Requirements

### Functional
- [x] `DashboardMessage` model with user_id, title, body (HTML), priority, source tracking, read_at, archived_at
- [x] `DashboardMessagePriority` enum: Low, Normal, High, Urgent
- [x] `POST /api/dashboard/messages` — create a message (captures source token name)
- [x] `GET /api/dashboard/messages` — paginated list with `unread_only` and `include_archived` filters
- [x] `PATCH /api/dashboard/messages/{message}` — toggle read/archive with ownership check
- [x] Filament resource for viewing/managing messages (no create/edit — API only)
- [x] Dashboard widget showing 5 most recent unread messages, polling every 30s
- [x] Navigation badge with unread message count

### Non-Functional
- [x] Security/Permissions: Messages scoped to authenticated user; ownership check on updates
- [x] Performance: Indexes on `(user_id, archived_at, created_at)` and `(user_id, read_at)`
- [x] Backwards compatibility: No impact on existing features

### UX / Admin
- [x] Filament list view with priority badges, read status icons, filters
- [x] Filament view page rendering HTML body with Mark Read/Archive/Delete actions
- [x] Auto-mark as read when viewing a message
- [x] Dashboard widget with clickable messages linking to view page

### Out of Scope
- Push notifications (browser/mobile)
- Message threading or replies
- Rich text editor for composing messages in the admin panel
- Message expiration/auto-cleanup

## Implementation Plan

### Phase 1: Data Model + API Endpoints
- Create `DashboardMessagePriority` enum (Low, Normal, High, Urgent)
- Create migration for `dashboard_messages` table
- Create `DashboardMessage` model with casts, relationships, factory
- Add `dashboardMessages(): HasMany` to User model
- Create form requests: `StoreDashboardMessageRequest`, `UpdateDashboardMessageRequest`
- Create `DashboardMessageResource` (API resource)
- Create responders: `StoreDashboardMessageResponder`, `ListDashboardMessagesResponder`, `UpdateDashboardMessageResponder`
- Add routes to `routes/api.php` inside existing `auth:sanctum` group
- Create `tests/Feature/Api/DashboardMessageApiTest.php`

### Phase 2: Filament Resource
- Create `DashboardMessageResource` with user-scoped query
- List page: table with title, priority (colored badge), source, read status (icon), created_at; filters for read status, priority, archived
- View page: HTML body rendering, header actions (Toggle Read, Toggle Archive, Delete)
- Auto-mark as read on view
- No create/edit pages
- Create `tests/Feature/Filament/DashboardMessageResourceTest.php`

### Phase 3: Dashboard Widget + Navigation Badge
- Create `RecentDashboardMessagesWidget` table widget (5 most recent unread, polls 30s)
- Add `getNavigationBadge()` and `getNavigationBadgeColor()` to resource
- Create `tests/Feature/Filament/DashboardMessageWidgetTest.php`

## Progress

### Completed
- Codebase exploration and planning
- Phase 1: Data Model + API Endpoints
- Phase 2: Filament Resource
- Phase 3: Dashboard Widget + Navigation Badge

### In Progress
- (none)

### To Do
- (none)

## Technical Notes

**Responder pattern:** Invokable classes matching existing pattern (see `ListMailboxesResponder`). User scoping via `$request->user()->dashboardMessages()`. Ownership check via `abort_unless($message->user_id === $request->user()->id, 404)`.

**Source tracking:** The `source` field stores the token name and `source_token_id` stores the token ID, capturing which API token created each message.

**HTML body:** The body field is longText to support HTML content with images, videos, and links. Filament view page uses `TextEntry::make('body')->html()` for rendering (no `HtmlEntry` in Filament v5).

## Files Modified/Created

### Database
- `database/migrations/2026_02_07_162943_create_dashboard_messages_table.php` — dashboard_messages table with indexes

### Models
- `app/Models/DashboardMessage.php` — Eloquent model with casts and relationships
- `app/Models/User.php` — added `dashboardMessages(): HasMany` relationship

### Enums
- `app/Enums/DashboardMessagePriority.php` — Low, Normal, High, Urgent

### API / Controllers / Responders
- `app/Http/Requests/StoreDashboardMessageRequest.php` — validates title, body, priority
- `app/Http/Requests/UpdateDashboardMessageRequest.php` — validates is_read, is_archived
- `app/Http/Resources/DashboardMessageResource.php` — API resource
- `app/Http/Responders/Api/StoreDashboardMessageResponder.php` — POST endpoint
- `app/Http/Responders/Api/ListDashboardMessagesResponder.php` — GET endpoint with pagination/filters
- `app/Http/Responders/Api/UpdateDashboardMessageResponder.php` — PATCH endpoint
- `routes/api.php` — added 3 routes in auth:sanctum group

### Factories / Seeders
- `database/factories/DashboardMessageFactory.php` — factory with read(), archived(), urgent(), high() states

### Admin / CMS
- `app/Filament/Resources/DashboardMessageResource.php` — resource with user-scoped query, table, filters, navigation badge
- `app/Filament/Resources/DashboardMessageResource/Pages/ListDashboardMessages.php` — list page (no create action)
- `app/Filament/Resources/DashboardMessageResource/Pages/ViewDashboardMessage.php` — view page with infolist, auto-read, header actions
- `app/Filament/Widgets/RecentDashboardMessagesWidget.php` — table widget, 5 most recent unread, polls 30s

### Tests
- `tests/Feature/Api/DashboardMessageApiTest.php` — full API endpoint coverage
- `tests/Feature/Filament/DashboardMessageResourceTest.php` — list, view, actions, user scoping
- `tests/Feature/Filament/DashboardMessageWidgetTest.php` — widget rendering, scoping, filtering

## Testing
✅ **All phases complete**: 33 feature tests, 111 total across suite
- Phase 1 (API): 15 tests, 46 assertions
  - Create, list, update endpoints with validation, filters, ownership checks, auth
- Phase 2 (Filament): 12 tests, 24 assertions
  - List page renders, user scoping, archived filter default
  - View page renders, auto-marks as read, preserves existing read_at
  - Toggle read/archive actions, delete action
  - No create action on list page
- Phase 3 (Widget): 6 tests, 6 assertions
  - Widget renders, shows unread/unarchived only, user scoping, max 5 messages

## Decisions Made
- **Messages are API-only**: No create/edit in Filament admin — messages come exclusively from the API
- **HTML body**: Using longText to support rich HTML content from the assistant
- **Priority enum**: String-backed enum with four levels (Low, Normal, High, Urgent) with Normal as default
- **Soft archive**: Messages have `archived_at` rather than soft deletes, keeping archive and delete as separate concepts

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Explored codebase: existing responder pattern, API routes, User model, Filament resources
- Designed 3-phase implementation plan for dashboard messages
- Created feature doc from template
- Implemented Phase 1: enum, migration, model, factory, form requests, API resource, responders, routes, tests
- All Phase 1 API tests passing
- Implemented Phase 2: Filament DashboardMessageResource with list/view pages
- List page: title, priority badge, source, read icon, created_at; filters for read, priority, archived
- View page: infolist with HTML body, auto-marks as read on mount, toggle read/archive/delete actions
- No HtmlEntry in Filament v5 — used TextEntry with ->html() instead
- All 105 tests pass (27 feature-specific + 78 existing)
- Implemented Phase 3: RecentDashboardMessagesWidget table widget, navigation badge on resource
- Widget: 5 most recent unread/unarchived, polls 30s, titles link to view page
- Navigation badge: red count of unread+unarchived messages
- All 111 tests pass (33 feature-specific + 78 existing)
- All 3 phases complete
