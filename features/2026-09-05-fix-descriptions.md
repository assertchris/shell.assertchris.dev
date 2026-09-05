# Fix Descriptions

- Branch: `feature/2026-09-05-fix-descriptions`

## Overview

The Shell API wraps Jira and exposes issue management endpoints. The `description` field is a core Jira issue field, but it has been silently dropped from every read response and left unvalidated on every write endpoint. Users cannot read descriptions they authored in Jira, and there is no documented or validated way to set a description when creating or updating issues.

The Jira API already returns `description` — it is listed in `DEFAULT_FIELDS` — so the data is fetched but discarded inside `IssueResource::toArray()`. The fix is purely additive across five files: one resource, two responders, the docs, and the test suite.

### Background / Current Behavior

- **Current behavior**: `description` is fetched from Jira but dropped in `IssueResource::toArray()`. Create and update endpoints accept it silently (it passes through to Jira) but do not validate or document it.
- **Problem**: Callers cannot read descriptions from any issue endpoint, and there is no documented or validated way to write one.
- **Where it happens**: `app/Http/Resources/Jira/IssueResource.php`, `app/Http/Responders/Api/Jira/CreateIssueResponder.php`, `app/Http/Responders/Api/Jira/UpdateIssueResponder.php`, `resources/docs/jira.md`, `tests/Feature/Jira/IssuesTest.php`
- **Impact**: Any caller relying on issue descriptions — including the Friday assistant and any MCP client — gets incomplete issue data and has no reliable way to write descriptions.

### Target Outcome

This feature will add full `description` support to the Jira issue endpoints so that callers can:
- Read `description` from any get, search, or list response
- Write `description` when creating an issue
- Write `description` when updating an issue
- See `description` documented in the API docs with correct types

## Requirements

### Functional

- [x] `GET /jira/issues/{key}` returns `description` (nullable string)
- [x] `GET /jira/issues` (search/list) returns `description` (nullable string)
- [x] `POST /jira/issues` accepts and validates optional `fields.description` (string)
- [x] `PUT /jira/issues/{key}` accepts and validates optional `fields.description` (string)

### Non-Functional

- [x] No new classes, no schema changes — purely additive
- [x] Backwards compatible — `description` is nullable; existing callers are unaffected

### Out of Scope

- Rich text / Atlassian Document Format support — plain text only
- Any other Jira fields not currently in `DEFAULT_FIELDS`

## Implementation Plan

> **Code style**: No useless comments. Only add comments that explain tricky/non-obvious code.
> **Before committing**: Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` before every commit.
> **No auto-committing**: Only commit when explicitly asked.
> **One step at a time**: Complete only the current phase before proceeding.

### Phase 1: Resource + Responders + Tests

**Files**: `IssueResource.php`, `CreateIssueResponder.php`, `UpdateIssueResponder.php`, `IssuesTest.php`

1. **`app/Http/Resources/Jira/IssueResource.php`**
   - In `toArray()`, add `'description' => $fields['description'] ?? null` to the returned array.
   - Type: nullable string. This fixes all read endpoints at once (get, search, all list variants).

2. **`app/Http/Responders/Api/Jira/CreateIssueResponder.php`**
   - Add `'fields.description' => ['sometimes', 'nullable', 'string']` to the validation rules array.

3. **`app/Http/Responders/Api/Jira/UpdateIssueResponder.php`**
   - Add `'fields.description' => ['sometimes', 'nullable', 'string']` to the validation rules array.

4. **`tests/Feature/Jira/IssuesTest.php`**
   - Add `'description' => 'Test issue description'` to `makeJiraIssueData()` fixture.
   - Add assertion that get and search responses include `description`.
   - Add test case for create with `fields.description` set — assert it is passed through to the mock Jira client.
   - Add test case for update with `fields.description` set — same assertion.

5. Run `vendor/bin/pint --dirty --format agent` then `php artisan test --compact --filter=IssuesTest` and confirm green.

### Phase 2: Docs

**File**: `resources/docs/jira.md`

1. In the response shape example for get and search endpoints, add `description` (nullable string) to the issue object.
2. In the create endpoint parameter table, add `fields.description` as an optional string parameter.
3. In the update endpoint parameter table, add `fields.description` as an optional string parameter.

## Progress

### Completed

- Phase 1: Resource + Responders + Tests
- Phase 2: Docs

### In Progress

### Blocked

### To Do

## Technical Notes

- `description` is already in `JiraService::DEFAULT_FIELDS` — no service changes needed.
- Jira returns `null` for `description` when the field is empty; nullable mapping is correct.
- The create/update responders do not forward the full validated payload to the Jira service — check how `fields` is passed through to confirm `description` will reach Jira without additional wiring.

## Files Modified/Created

### API / Controllers / Responders

- `app/Http/Resources/Jira/IssueResource.php` — add `description` to `toArray()`
- `app/Http/Responders/Api/Jira/CreateIssueResponder.php` — add `fields.description` validation
- `app/Http/Responders/Api/Jira/UpdateIssueResponder.php` — add `fields.description` validation

### Views / Frontend

- `resources/docs/jira.md` — document `description` in response shape and parameters

### Tests

- `tests/Feature/Jira/IssuesTest.php` — fixture + assertions for description read/write

## Testing

⏳ **Pending**: Not yet run
- Key coverage:
  - `description` present in get/search responses
  - `description` accepted and forwarded on create
  - `description` accepted and forwarded on update

## Questions/Decisions Needed

## Session History

### Session 1

- Brief sourced from note 235 (pre-researched)
- Plan written based on research findings in note
