# Refactor to Tagged Caching Package

- Branch: `feature/2026-10-10-refactor-to-tagged-caching-package`

## Overview

We've got a custom `CacheKey` / `CacheOperation` abstraction that wraps the Laravel `Cache` facade. It uses colon-delimited string keys and MD5-hashed param fingerprints. It works, but there are no tags. Every invalidation means rebuilding the exact key string and calling `->forget()`. Hashed list keys (e.g. `myAssignedIssues`) are the worst case. If we don't know the exact params that populated the cache at invalidation time, the entry sticks around until TTL expiry.

The `assertchris/laravel-tagged-cache` package gives us a fluent, tag-scoped cache helper that fixes this. We tag every entry with its logical group (service + account, model type, resource ID). Then a single `->flush()` on a tag clears every related entry, and we don't need to rebuild any keys.

This refactor deletes `CacheKey` and `CacheOperation`, installs the package, and moves every cache site over to `TaggedCache::for()`. Observers and responders get simpler too. Instead of forgetting N individual keys, they flush the relevant tag — sometimes in two calls when both a resource-scoped tag and an account-scoped tag need clearing.

### Background / Current Behavior

- **Current behavior**: Every read and write path goes through `CacheKey::for('jira', $accountId, 'issue', $key)->remember(...)` / `->forget()`. Hash-parameterised list keys can't be reliably invalidated.
- **Problem**: Stale hashed list keys (assigned issues, search results, sprint issues) don't get invalidated on write mutations. Observers and responders keep long lists of explicit key forgets, and those lists have to stay in sync with every new cache site.
- **Where it happens**: `app/Support/CacheKey.php`, `app/Support/CacheOperation.php`, all Services, all Observers, write Responders.
- **Impact**: Cache staleness bugs on list endpoints after mutations. Invalidation gets fragile every time we add a cached field.

### Target Outcome

We're replacing the bespoke key/operation classes with `assertchris/laravel-tagged-cache`. Once that's done:
- Every cache entry carries meaningful tags (service, account, resource)
- List-endpoint cache entries can be invalidated without rebuilding hashed keys
- Observers and responders flush tags instead of enumerating individual keys

## Requirements

### Functional
- [ ] `assertchris/laravel-tagged-cache` installed and working against the configured Valkey/Redis store
- [ ] All `CacheKey::for()` and `CacheKey::hashed()` call sites replaced with `TaggedCache::for(tags: [...], context: [...])`
- [ ] Tag strategy applied consistently: service-level tag + account-scoped tag on every entry
- [ ] Observers and responders flush tags instead of forgetting individual keys
- [ ] `PersonalAccessToken` `saving` hook preserved: `last_used_at` patch updates cache without touching the DB, and the `saving` observer still returns `false` to abort the save
- [ ] `CacheKey` and `CacheOperation` classes deleted once all sites migrated

### Non-Functional
- [ ] Cache store must support tags — Valkey/Redis required (not `database` or `file`)
- [ ] Every previously cached endpoint still caches: feature tests assert that a second identical request hits the cache (callback not called twice)
- [ ] Every invalidation path still clears the right entries: feature tests assert the cached value is absent after the relevant observer/responder fires
- [ ] All tests passing

### Out of Scope
- Switching the default `CACHE_STORE` env var in production (configuration change, not code)
- Caching any new endpoints not currently cached
- Changing TTL values

## Implementation Plan

> **Code style**: We don't add useless comments. We only comment tricky or non-obvious code.
> **Before committing**: Run `vendor/bin/pint --dirty` and `php artisan test --compact` before every commit.
> **No auto-committing**: Only commit when Chris explicitly asks. `git status`, `git diff`, and `git log` are read-only, so never follow them with `git add` or `git commit` unless Chris said "commit." **This rule applies to the orchestrating session only. Sub-agents implementing phases MUST commit their work at the end of each phase.**
> **One step at a time**: Finish the current phase before asking to move on.

### Phase 1 — Install Package and Confirm Cache Store

Laravel Cloud provides Redis — the cache store concern is a non-issue in production. The test environment uses the `array` driver, which also supports tags.

1. Run `composer require assertchris/laravel-tagged-cache`
2. Read `config/cache.php` to confirm the Redis store name added in PR #21. If `CACHE_STORE` in `.env` is not already `redis` locally, set it — tests that require a tag-capable store will fail silently against `database` or `file`.
3. Write a unit test in `tests/Unit/TaggedCacheTest.php` confirming `TaggedCache::for()` can `remember`, `get`, and `flush` against the `array` store (set via `Cache::setDefaultDriver('array')` or `Config::set('cache.default', 'array')` in the test).

### Phase 2 — Define Tag Strategy and Migrate Models

Here's how we'll name the tags:
- Model entries: tag with `['accounts']` / `['users']` / `['tokens']` plus a resource-scoped tag `["account:{id}"]` / `["user:{id}"]` / `["token:{hash}"]`
- Service entries: tag with `['jira', "jira:{accountId}"]`, `['slack', "slack:{accountId}"]`, `['calendar', "calendar:{accountId}"]`, `['github', "github:{accountId}"]`
- For resource-specific invalidation within a service, add a resource-scoped tag: e.g. `["jira:{accountId}:issue:{key}"]`

Next, we move the models over:
- `Account::resolveRouteBinding` → `TaggedCache::for(tags: ['accounts', "account:{$value}"], context: ['id' => $value])->remember(now()->addDays(7), ...)`
- `User::findCached` → `TaggedCache::for(tags: ['users', "user:{$id}"], context: ['id' => $id])->remember(now()->addDays(7), ...)`. Note: `findToken` calls `User::findCached` to warm the user cache — this still happens, just via the new API.
- `PersonalAccessToken::findToken` → `TaggedCache::for(tags: ['tokens', "token:{$hash}"], context: ['hash' => $hash])->remember(now()->addDays(7), ...)` where `$hash = hash('sha256', $token)` (the raw input token, not the stored value)

Then we update the observers:
- `AccountObserver::saved/deleted` → two flush calls: `TaggedCache::for(tags: ["account:{$account->id}"])->flush()` for the single-account entry, and `TaggedCache::for(tags: ["account:user:{$account->user_id}"])->flush()` for the accounts-list entry. If `user_id` changed during save, also flush `"account:user:{$original_user_id}"` — the observer already tracks the original value via Eloquent's dirty check (`$account->getOriginal('user_id')`). `ListAccountsResponder` must tag its cache entry with `"account:user:{$userId}"` for this to work.
- `UserObserver::saved/deleted` → `TaggedCache::for(tags: ["user:{$user->id}"])->flush()`
- `PersonalAccessTokenObserver::saving` — `$hash = $token->token` (the stored field is the SHA-256 hash of the raw token; `findToken` caches with `$hash = hash('sha256', $rawToken)`, which equals `$token->token`). Patch `last_used_at` via `get()`/`put()` using `TaggedCache::for(tags: ['tokens', "token:{$hash}"], context: ['hash' => $hash])` — tags and context must exactly match `findToken`.
- `PersonalAccessTokenObserver::saved/deleted` → `$hash = $token->token`; `TaggedCache::for(tags: ["token:{$hash}"])->flush()`

Write tests in `tests/Feature/ModelCacheTest.php` covering: account/user/token remember hits cache on second call; AccountObserver flush clears single-account entry; AccountObserver flush clears accounts-list entry (via `"account:user:{id}"` tag); AccountObserver handles user_id change by flushing both old and new user tags; token `saving` hook patches `last_used_at` in cache and returns false to skip DB write; token `saved/deleted` hook clears the token cache entry.

### Phase 3 — Migrate Services

**JiraService** — 28 cached methods. Tag strategy:
- All Jira entries: `['jira', "jira:{$accountId}"]`
- Issue-specific entries additionally tagged: `["jira:{$accountId}:issue:{$key}"]`
- Sprint-specific entries additionally tagged: `["jira:{$accountId}:sprint:{$sprintId}"]`
- Context = the params that define uniqueness (replacing the existing MD5 approach — `TaggedCache` handles key derivation internally via an MD5 of the key-sorted context array)
- Methods with no variable params beyond `$accountId` (e.g. `myself`, `filters`, `dashboards`, `boards`) use `context: ['account' => $accountId]` so each account gets a distinct cache key. Do not put `$accountId` in the tags string AND in context — that's redundant. Tags carry the account scope; context carries the uniqueness params.

Example:
```php
// Before
CacheKey::hashed(['jql' => $jql, 'startAt' => $startAt, ...], 'jira', $accountId, 'search')->remember(now()->addMinutes(5), ...)

// After
TaggedCache::for(
    tags: ['jira', "jira:{$accountId}"],
    context: ['jql' => $jql, 'startAt' => $startAt, ...]
)->remember(now()->addMinutes(5), ...)
```

**SlackService** — 3 cached methods. Tag: `['slack', "slack:{$accountId}"]`. Context = all variable params (`cursor`, `limit`, etc.) — never `$accountId` alone in context, since `$accountId` is already part of the tag and adding it to context would change the key hash unnecessarily.

**CalendarService** — 2 cached methods. Tag: `['calendar', "calendar:{$accountId}"]`. Context = all variable params (`maxResults`, `pageToken`, `eventId`).

**GitHubService** — 19 cached methods. Tag strategy:
- All GitHub entries: `['github', "github:{$accountId}"]`
- PR-specific entries additionally tagged: `["github:{$accountId}:pr:{$owner}:{$repo}:{$number}"]`
- Methods with no variable params (e.g. `repos`) use `context: ['account' => $accountId]`

Write feature tests for each service's cache behavior. Tests should: (a) assert the closure is not called on a second identical request; (b) assert the cached value is absent after a `->flush()` on the relevant tag. Name test files after the service: `tests/Feature/JiraServiceCacheTest.php`, `SlackServiceCacheTest.php`, `CalendarServiceCacheTest.php`, `GitHubServiceCacheTest.php`.

### Phase 4 — Simplify Responders and Observers

We'll swap every per-key `->forget()` in the responders for a tag flush:

| Responder | Old | New |
|---|---|---|
| `CreateCalendarEntryResponder` | `CacheKey::hashed([], 'calendar', $id, 'entries')->forget()` | `TaggedCache::for(tags: ["calendar:{$id}"])->flush()` |
| `SendSlackMessageResponder` | `CacheKey::hashed([], 'slack', $id, 'messages', $channelId)->forget()` | `TaggedCache::for(tags: ["slack:{$id}"])->flush()` |
| Jira `AddCommentResponder` | forgets `issue:{key}:comments` | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` |
| Jira `UpdateCommentResponder` | forgets `issue:{key}:comments` | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` |
| Jira `DeleteCommentResponder` | forgets `issue:{key}:comments` | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` |
| Jira `AddWorklogResponder` | forgets `issue:{key}:worklogs` | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` |
| Jira `DeleteWorklogResponder` | forgets `issue:{key}:worklogs` | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` |
| Jira `AssignIssueResponder` | forgets issue key + user:assigned key | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` + `TaggedCache::for(tags: ["jira:{$id}"])->flush()` |
| Jira `TransitionIssueResponder` | forgets issue + transitions | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` |
| Jira `UpdateIssueResponder` | forgets issue + 2 user view keys | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` + `TaggedCache::for(tags: ["jira:{$id}"])->flush()` |
| Jira `CreateIssueResponder` | forgets 3 user view keys | `TaggedCache::for(tags: ["jira:{$id}"])->flush()` |
| Jira `DeleteIssueResponder` | forgets issue + 3 user view keys | `TaggedCache::for(tags: ["jira:{$id}:issue:{$key}"])->flush()` + `TaggedCache::for(tags: ["jira:{$id}"])->flush()` |
| Jira `MoveIssuesToSprintResponder` | forgets sprint:issues key | `TaggedCache::for(tags: ["jira:{$id}:sprint:{$sprintId}"])->flush()` |

The GitHub responders (`GetPullResponder`, `ListPullsResponder`) only read, so they don't need invalidation. That changes if we add a GitHub write responder. Note: `GetPullResponder` has a special case where it extends the TTL to 300 seconds for non-open PRs — this must be preserved by migrating the `->put($data, 300)` call to `TaggedCache::for(tags: [...], context: [...])->put($data, 300)`.

`ListAccountsResponder` needs the `"account:user:{$userId}"` tag too. That way the `AccountObserver` flush covers the list.

Write tests in `tests/Feature/ResponderCacheTest.php`. For each write responder: seed a cached value under the relevant tag(s), trigger the responder action, assert the cached value is no longer present. For responders that fire two flush calls, assert both tags are cleared.

### Phase 5 — Delete CacheKey and CacheOperation

Once every call site has moved over:
1. Delete `app/Support/CacheKey.php`
2. Delete `app/Support/CacheOperation.php`
3. Grep for any remaining references: `grep -r 'CacheKey\|CacheOperation' app/` — fix anything that surfaces
4. Run the full test suite

### Phase 6 — Final Cleanup and Test Pass

1. Run `php artisan test --compact` — zero failures required
2. Run `vendor/bin/pint --dirty` — zero formatting issues required
3. Confirm `composer.json` locks `assertchris/laravel-tagged-cache` to an exact version (no `^` or `~`)

## Progress

### Completed
- Codebase audit (all cache sites identified)
- Package research (`assertchris/laravel-tagged-cache` v1.0)
- Phase 1: Install Package and Confirm Cache Store
- Phase 2: Define Tag Strategy and Migrate Models
- Phase 3: Migrate Services
- Phase 4: Simplify Responders and Observers

### In Progress
- Nothing yet

### To Do
- Phases 5–6

## Technical Notes

- The `PersonalAccessToken::saving` observer patches `last_used_at` in cache to avoid a DB write on every token use. `TaggedCache` `get()`/`put()` covers this, but the tags and context in the observer must exactly match those used in `findToken` — if they diverge, `get()` returns null and the optimisation silently falls back to a DB write without error.
- Laravel Cloud provides Redis, so the tag-compatible store requirement is already met in production. Tests use the `array` driver, which also supports tags.
- `TaggedCache` derives its cache key from an MD5 hash of the key-sorted context array. That matches what `CacheKey::hashed()` does for parameterised keys. For non-parameterised keys (e.g. `CacheKey::for('account', $id)`), the old format was `account:{id}` while the new context-based key will be a different MD5 — both produce a cold start on deploy, no stale reads.
- A `flush()` on a tag clears ALL entries under that tag across all context variants. That's intentional — it replaces the need to know every param combination to invalidate list endpoints.
- `Cache::tags(['a', 'b'])->flush()` (which `TaggedCache` uses internally) flushes entries carrying ALL listed tags (AND semantics, not OR). This is why the plan uses two separate `->flush()` calls for responders that need to clear both an issue-specific tag and the broader account tag — a single call with both tags would only hit entries carrying both simultaneously.
- Flushing `"jira:{$id}"` on mutations like Assign/Update/DeleteIssue is intentionally broader than the original per-key forgets. It clears all cached Jira data for that account, including projects and boards, not just the affected user-view keys. This is an acceptable trade-off: correctness over cache efficiency, and the TTLs are short (5–15 min).

## Files Modified/Created

### Services / Core
- `app/Support/CacheKey.php` — deleted
- `app/Support/CacheOperation.php` — deleted

### Models
- `app/Models/Account.php` — migrate to TaggedCache
- `app/Models/User.php` — migrate to TaggedCache
- `app/Models/PersonalAccessToken.php` — migrate to TaggedCache

### Services / Core
- `app/Services/JiraService.php` — migrate 28 cached methods
- `app/Services/SlackService.php` — migrate 3 cached methods
- `app/Services/CalendarService.php` — migrate 2 cached methods
- `app/Services/GitHubService.php` — migrate 19 cached methods

### API / Controllers / Responders
- `app/Http/Responders/Api/ListAccountsResponder.php` — add account:user tag
- `app/Http/Responders/Api/CreateCalendarEntryResponder.php` — tag flush
- `app/Http/Responders/Api/SendSlackMessageResponder.php` — tag flush
- `app/Http/Responders/Api/Jira/*.php` — tag flushes (11 responders: AddComment, UpdateComment, DeleteComment, AddWorklog, DeleteWorklog, AssignIssue, TransitionIssue, UpdateIssue, CreateIssue, DeleteIssue, MoveIssuesToSprint)

### Console / Observers
- `app/Observers/AccountObserver.php` — tag flush
- `app/Observers/UserObserver.php` — tag flush
- `app/Observers/PersonalAccessTokenObserver.php` — migrate to TaggedCache

### Tests
- `tests/Unit/TaggedCacheTest.php` — smoke test (Phase 1)
- `tests/Feature/ModelCacheTest.php` — Account, User, PersonalAccessToken cache paths and observer invalidation (Phase 2)
- `tests/Feature/JiraServiceCacheTest.php` — remember + tag flush (Phase 3)
- `tests/Feature/SlackServiceCacheTest.php` — remember + tag flush (Phase 3)
- `tests/Feature/CalendarServiceCacheTest.php` — remember + tag flush (Phase 3)
- `tests/Feature/GitHubServiceCacheTest.php` — remember + tag flush (Phase 3)
- `tests/Feature/ResponderCacheTest.php` — invalidation paths for all write responders (Phase 4)
