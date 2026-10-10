# Add Aggressive Caching

- Branch: `feature/2026-10-10-add-aggressive-caching`

## Overview

shell.assertchris.dev is a Laravel API that proxies requests to third-party services (GitHub, Jira, Slack, Google Calendar) on behalf of authenticated users. Every API call currently runs through Sanctum token authentication, which fires a `SELECT` against `personal_access_tokens` on every request, followed by a user lookup and an account route-binding lookup. Third-party service responses are fetched live on every call with no caching layer in place.

The hot path runs 2–4 DB queries on every single request — token lookup, user load, account binding, and optionally an accounts list — before any actual service work happens. Third-party responses are fetched fresh every time, regardless of how recently the same data was requested.

We're introducing Valkey as the cache backend and adding two caching tiers: a long-TTL (7-day) tier for DB-owned models (users, accounts, access tokens) with explicit cache busting on model writes, and a short-TTL (3–30 minute) tier for third-party API responses that expire naturally. Routine API requests will skip the DB entirely for auth and account resolution, and third-party calls will be served from cache for their TTL window.

### Background / Current Behavior

**Current behavior**: Every API request runs 2–4 DB queries (token lookup, user load, account binding, optional accounts list) plus live third-party HTTP calls.

**Problem**: No caching anywhere in the hot path. Identical Sanctum token lookups, user loads, and account bindings hit SQLite on every request. Third-party API calls are live on every request.

**Where it happens**: `PersonalAccessToken::findToken()` (called by Sanctum on every request), `Account` route model binding (all `{account}` routes), `ListAccountsResponder`, and all service methods in `GitHubService`, `JiraService`, `SlackService`, `CalendarService`.

**Impact**: Unnecessary DB load and latency on every request. External API rate limits get consumed faster than needed.

### Target Outcome

- Sanctum token authentication skips the DB after the first request for the token's 7-day TTL window
- User and account model lookups are served from cache on repeat requests within the same TTL
- GitHub, Jira, Slack, and Calendar responses are cached per account with TTLs matching their expected data freshness
- Cache is busted automatically on model writes via Eloquent observers and on third-party write operations via responders

## Requirements

### Functional

- [ ] A `valkey` cache store is configured in `config/cache.php` using the `redis` driver, switchable via `CACHE_STORE=valkey`
- [ ] A `CacheKey` helper at `app/Support/CacheKey.php` generates consistent, account-scoped cache keys for all cacheable resources
- [ ] `PersonalAccessToken::findToken()` is wrapped in a 7-day cache; the `last_used_at` DB write is suppressed when only `last_used_at` is dirty; cache is busted on any security-relevant field change
- [ ] `User::findCached()` wraps the user lookup in a 7-day cache; `UserObserver` busts on save/delete
- [ ] `Account::resolveRouteBinding()` wraps the account lookup in a 7-day cache; `ListAccountsResponder` wraps the active-accounts query in a 7-day cache; `AccountObserver` busts on save/delete
- [ ] All read methods in `GitHubService` are wrapped in 5-minute cache with account-scoped keys
- [ ] All Jira read methods are cached with TTLs ranging from 5–30 minutes per endpoint category; Jira write responders bust affected keys immediately after a successful write
- [ ] Slack read methods (`listChannels`, `listMessages`, `listUsers`) are cached (3–30 min); `SendSlackMessageResponder` busts the relevant `listMessages` key
- [ ] Calendar read methods (`listEntries`, `getEntry`) are cached (5–10 min); `CreateCalendarEntryResponder` busts the `listEntries` key for the account

### Non-Functional

- [ ] Observability: Standard Laravel cache events are sufficient; no new logging required
- [ ] Performance: No request should hit the DB for token/user/account lookup after the first warm request within the TTL window
- [ ] Security/Permissions: Cache keys are scoped by `account_id`; the token cache key uses the SHA-256 hash, never the plaintext token; cache is busted immediately on any security-relevant model change
- [ ] Backwards compatibility: `CACHE_STORE=database` remains the default for local dev; production switches via env var with no application code branching

### Out of Scope

- Moving sessions or queues to Valkey (server-side Forge config only)
- Filament or admin UI changes
- IMAP/mail caching
- Cache metrics or observability dashboards

## Implementation Plan

> **Code style**: No useless comments. Only add comments for tricky/non-obvious code.
> **Before committing**: Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` before every commit.
> **One phase at a time**: Complete and commit each phase before starting the next.
> **Sub-agents implementing phases MUST commit their work.**

### Phase 1: Valkey config + CacheKey helper

Stand up the configuration and key-generation infrastructure that all subsequent phases depend on.

1. **`config/database.php`** — add a `valkey` Redis connection block alongside `default` and `cache`:
   ```php
   'valkey' => [
       'url' => env('VALKEY_URL'),
       'host' => env('VALKEY_HOST', '127.0.0.1'),
       'username' => env('VALKEY_USERNAME'),
       'password' => env('VALKEY_PASSWORD'),
       'port' => env('VALKEY_PORT', '6379'),
       'database' => env('VALKEY_CACHE_DB', '2'),
   ],
   ```

2. **`config/cache.php`** — add a `valkey` store entry:
   ```php
   'valkey' => [
       'driver' => 'redis',
       'connection' => env('REDIS_CACHE_CONNECTION', 'valkey'),
       'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
   ],
   ```

3. **`.env.example`** — add Valkey env vars below the existing Redis block. Keep `CACHE_STORE=database` as the local default; comment the intended production value:
   ```
   VALKEY_HOST=127.0.0.1
   VALKEY_PASSWORD=null
   VALKEY_PORT=6379
   VALKEY_CACHE_DB=2
   # Production: CACHE_STORE=valkey
   ```

4. **Deploy hook** — add `php artisan cache:clear` to the Laravel Cloud deploy commands for `shell.assertchris.dev`. This is a Laravel Cloud dashboard change; no committed file is involved.

5. **`app/Support/CacheKey.php`** — create via `php artisan make:class Support/CacheKey --no-interaction`. Implement two static methods:
   ```php
   public static function for(string|int ...$parts): string
   {
       return implode(':', $parts);
   }

   public static function hashed(array $params, string|int ...$prefix): string
   {
       ksort($params);
       return implode(':', [...$prefix, md5(serialize($params))]);
   }
   ```
   `for()` joins parts with `:`. `hashed()` sorts `$params` by key before serializing so equivalent param sets always produce the same hash regardless of insertion order.

6. **Tests** — `tests/Unit/Support/CacheKeyTest.php`:
   - `for()` joins parts with `:`
   - `hashed()` produces a stable hash regardless of param insertion order
   - `hashed()` with empty `$params` produces a consistent, stable hash

### Phase 2: DB-tier caching — PersonalAccessToken, User, Account

Eliminate the 2–3 hot-path DB queries that fire on every API request.

**PersonalAccessToken**

1. **`app/Models/PersonalAccessToken.php`** — add `findToken()` override. Override the static method to wrap the parent lookup in a 7-day cache, keyed by the SHA-256 hash Sanctum stores in the `token` column. After retrieving the cached token, stitch in the cached user via `setRelation()` so `$token->tokenable` never hits the DB:
   ```php
   public static function findToken($token): ?static
   {
       $hash = hash('sha256', $token);

       $cachedToken = Cache::remember(
           CacheKey::for('token', $hash),
           now()->addDays(7),
           fn () => parent::findToken($token),
       );

       if ($cachedToken) {
           $cachedToken->setRelation('tokenable', User::findCached($cachedToken->tokenable_id));
       }

       return $cachedToken;
   }
   ```

2. **`app/Models/User.php`** — add `findCached()` static method. Do not override `find()` itself — too broad and breaks seeders and tests:
   ```php
   public static function findCached(int $id): ?static
   {
       return Cache::remember(
           CacheKey::for('user', $id),
           now()->addDays(7),
           fn () => static::find($id),
       );
   }
   ```

3. **`app/Observers/PersonalAccessTokenObserver.php`** — new observer (create `app/Observers/` directory). Security-relevant fields: `['token', 'name', 'abilities', 'expires_at', 'last_ip_address', 'ip_changed_at']`.
   - `saving()`: if only `last_used_at` is dirty (no security fields), update `last_used_at` on the cached model in-place, re-put with a fresh 7-day TTL, and return `false` to cancel the DB write entirely. Otherwise return `true`.
   - `saved()`: `Cache::forget(CacheKey::for('token', $token->token))` — fires only when `saving()` returned `true`.
   - `deleted()`: same forget.

   The `saving()` observer intercepts Sanctum's `forceFill(['last_used_at' => now()])->save()` call. Returning `false` prevents the DB write; the cached model stays accurate and the TTL resets to 7 days on every warm request.

4. **`app/Providers/AppServiceProvider.php`** — register observer in `boot()`:
   ```php
   PersonalAccessToken::observe(PersonalAccessTokenObserver::class);
   ```

5. **Tests** — `tests/Feature/Auth/TokenCachingTest.php`:
   - First authenticated request warms the token cache; a second identical request does not query `personal_access_tokens`
   - `last_used_at` update does NOT write to DB (assert no `UPDATE personal_access_tokens` in query log)
   - An IP-binding change (security-relevant field) DOES write to DB and busts the cache
   - Token deletion busts the cache

**User**

6. **`app/Observers/UserObserver.php`** — new observer:
   - `saved()`: `Cache::forget(CacheKey::for('user', $user->id))`
   - `deleted()`: same

7. Register `User::observe(UserObserver::class)` in `AppServiceProvider::boot()`.

8. **Tests** — `tests/Feature/Auth/UserCachingTest.php`:
   - `findCached()` caches the user; a second call does not hit the DB
   - `saved` observer busts the user cache

**Account**

9. **`app/Models/Account.php`** — override `resolveRouteBinding()`. Implicit route model binding calls `Account::find($id)` on every `{account}` route; wrapping this in `Cache::remember` intercepts that lookup:
   ```php
   public function resolveRouteBinding($value, $field = null): ?static
   {
       return Cache::remember(
           CacheKey::for('account', $value),
           now()->addDays(7),
           fn () => static::find($value),
       );
   }
   ```

10. **`app/Http/Responders/Api/ListAccountsResponder.php`** — wrap the active-accounts query:
    ```php
    $userId = $request->user()->id;
    $accounts = Cache::remember(
        CacheKey::for('accounts', 'user', $userId),
        now()->addDays(7),
        fn () => $request->user()->accounts()->where('is_active', true)->get(),
    );
    ```

11. **`app/Observers/AccountObserver.php`** — new observer:
    - `saved()`: forget `account:{id}` and `accounts:user:{user_id}`. If `user_id` changed, also forget the old user's accounts key via `$account->getOriginal('user_id')`.
    - `deleted()`: same.

12. Register `Account::observe(AccountObserver::class)` in `AppServiceProvider::boot()`.

13. **Tests** — `tests/Feature/Api/AccountCachingTest.php`:
    - Route model binding serves the account from cache after the first request (no second DB query for `{account}`)
    - `ListAccountsResponder` uses cache; a second call does not re-query
    - `AccountObserver` busts both `account:{id}` and `accounts:user:{user_id}` on save

### Phase 3: Third-party read caching — GitHub and Jira

Cache all read responses from GitHub and Jira with account-scoped keys.

**GitHubService** (`app/Services/GitHubService.php`)

Wrap each read method body in `Cache::remember(CacheKey::..., now()->addMinutes(5), fn () => ...)`. Use `CacheKey::for(...)` for single-resource endpoints and `CacheKey::hashed($params, ...)` for parameterised list endpoints:

| Method | Cache key |
|---|---|
| `repos($params)` | `CacheKey::hashed($params, 'github', $this->account->id, 'repos')` |
| `repo($owner, $repo)` | `CacheKey::for('github', $this->account->id, 'repo', $owner, $repo)` |
| `pulls($owner, $repo, $params)` | `CacheKey::hashed($params, 'github', $this->account->id, 'prs', $owner, $repo)` |
| `pull($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'pr', $owner, $repo, $number)` |
| `pullFiles($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'pr', $owner, $repo, $number, 'files')` |
| `pullCommits($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'pr', $owner, $repo, $number, 'commits')` |
| `pullReviews($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'pr', $owner, $repo, $number, 'reviews')` |
| `pullReviewComments($owner, $repo, $number, $reviewId)` | `CacheKey::for('github', $this->account->id, 'pr', $owner, $repo, $number, 'review', $reviewId, 'comments')` |
| `pullRequestedReviewers($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'pr', $owner, $repo, $number, 'reviewers')` |
| `searchPulls($q, $params)` | `CacheKey::hashed(array_merge(['q' => $q], $params), 'github', $this->account->id, 'search', 'pulls')` |
| `searchIssues($q, $params)` | `CacheKey::hashed(array_merge(['q' => $q], $params), 'github', $this->account->id, 'search', 'issues')` |
| `issues($params)` | `CacheKey::hashed($params, 'github', $this->account->id, 'issues', 'all')` |
| `userIssues($params)` | `CacheKey::hashed($params, 'github', $this->account->id, 'issues', 'user')` |
| `repoIssues($owner, $repo, $params)` | `CacheKey::hashed($params, 'github', $this->account->id, 'issues', 'repo', $owner, $repo)` |
| `issue($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'issue', $owner, $repo, $number)` |
| `issueComments($owner, $repo, $number)` | `CacheKey::for('github', $this->account->id, 'issue', $owner, $repo, $number, 'comments')` |
| `notifications($params)` | `CacheKey::hashed($params, 'github', $this->account->id, 'notifications')` |
| `notification($threadId)` | `CacheKey::for('github', $this->account->id, 'notification', $threadId)` |

**JiraService** (`app/Services/JiraService.php`)

Same `Cache::remember` pattern. `myAssignedIssues`, `myReportedIssues`, `myWatchedIssues`, `issuesMentioningMe`, and `issuesWithRecentActivity` all delegate to `searchIssues()` internally — wrap the delegating methods with their own distinct keys so each has an independent cache entry. TTLs by category:

- **5 min**: issue-level endpoints, user view lists, sprint issues, JQL searches
- **10 min**: sprint detail
- **15 min**: projects, boards, filters, dashboards, project components/versions
- **30 min**: `myself()`

User view keys: `CacheKey::hashed($params, 'jira', $accountId, 'user', $view)` where `$view` is one of `assigned`, `reported`, `watching`, `mentions`, `activity`.

**Tests** — `tests/Feature/GitHub/GitHubCachingTest.php` and `tests/Feature/Jira/JiraCachingTest.php`:
- Two identical API calls to the same endpoint result in `Http::assertSentCount(1)` — the second is served from cache
- A request from a different account hits the external API (no cross-account cache leakage)

### Phase 4: Third-party read caching + write busting — Slack, Calendar, Jira writes

Cache Slack and Calendar reads; flush the appropriate keys in write responders.

**SlackService** (`app/Services/SlackService.php`)

SlackService receives `Account` as a method argument rather than via constructor. Wrap each read method body:

| Method | TTL | Cache key |
|---|---|---|
| `listChannels($account, $cursor)` | 15 min | `CacheKey::hashed(['cursor' => $cursor], 'slack', $account->id, 'channels')` |
| `listMessages($account, $channelId, $limit, $cursor)` | 3 min | `CacheKey::hashed(['limit' => $limit, 'cursor' => $cursor], 'slack', $account->id, 'messages', $channelId)` |
| `listUsers($account, $cursor)` | 30 min | `CacheKey::hashed(['cursor' => $cursor], 'slack', $account->id, 'users')` |

**`SendSlackMessageResponder`** — after `sendMessage` succeeds, bust the default-params `listMessages` key for the channel:
```php
Cache::forget(CacheKey::hashed([], 'slack', $account->id, 'messages', $channelId));
```

**CalendarService** (`app/Services/CalendarService.php`)

| Method | TTL | Cache key |
|---|---|---|
| `listEntries($account, $maxResults, $pageToken)` | 5 min | `CacheKey::hashed(['maxResults' => $maxResults, 'pageToken' => $pageToken], 'calendar', $account->id, 'entries')` |
| `getEntry($account, $eventId)` | 10 min | `CacheKey::for('calendar', $account->id, 'event', $eventId)` |

**`CreateCalendarEntryResponder`** — after `createEntry` succeeds, bust the default-params `listEntries` key:
```php
Cache::forget(CacheKey::hashed([], 'calendar', $account->id, 'entries'));
```

**Jira write responders** — add cache-busting calls after each successful write. Use `CacheKey::hashed([], ...)` to produce the zero-params hash for paginated endpoints. Express all bust calls via `CacheKey` — never hardcode the `:` key string directly:

| Responder | Keys to bust |
|---|---|
| `UpdateIssueResponder` | `CacheKey::for('jira', $account->id, 'issue', $key)`, `CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned')`, `CacheKey::hashed([], 'jira', $account->id, 'user', 'activity')` |
| `TransitionIssueResponder` | `CacheKey::for('jira', $account->id, 'issue', $key)`, `CacheKey::for('jira', $account->id, 'issue', $key, 'transitions')` |
| `AssignIssueResponder` | `CacheKey::for('jira', $account->id, 'issue', $key)`, `CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned')` |
| `AddCommentResponder` / `UpdateCommentResponder` / `DeleteCommentResponder` | `CacheKey::for('jira', $account->id, 'issue', $key, 'comments')` |
| `AddWorklogResponder` / `UpdateWorklogResponder` / `DeleteWorklogResponder` | `CacheKey::for('jira', $account->id, 'issue', $key, 'worklogs')` |
| `CreateIssueResponder` | `CacheKey::hashed([], 'jira', $account->id, 'user', 'assigned')`, `CacheKey::hashed([], 'jira', $account->id, 'user', 'reported')`, `CacheKey::hashed([], 'jira', $account->id, 'user', 'activity')` |
| `DeleteIssueResponder` | `CacheKey::for('jira', $account->id, 'issue', $key)` + all three user view keys above |
| `MoveIssuesToSprintResponder` | `CacheKey::hashed([], 'jira', $account->id, 'sprint', $sprintId, 'issues')` |

**Tests** — `tests/Feature/Api/SlackCachingTest.php`, `tests/Feature/Api/CalendarCachingTest.php`, `tests/Feature/Jira/JiraWriteCachingTest.php`:
- Slack: two `listChannels` calls → `Http::assertSentCount(1)`; after `sendMessage`, the next `listMessages` call hits the external API again
- Calendar: `listEntries` is cached; `createEntry` busts it and the next `listEntries` call hits the API
- Jira writes: after each write responder, the relevant read key is absent from cache (assert `Cache::missing($key)` after the write)

## Technical Notes

**Why override `findToken()` rather than hooking middleware?**
`PersonalAccessToken::findToken()` is the single Sanctum-internal entry point for token resolution. Overriding it in our already-subclassed model is the minimal, clean hook — no middleware required, no Sanctum internals modified.

**Why suppress `last_used_at` DB writes?**
Sanctum calls `forceFill(['last_used_at' => now()])->save()` after every successful authentication. Without interception, this fires a DB write on every request, defeating the token cache. The `PersonalAccessTokenObserver::saving()` hook intercepts this specific case (only `last_used_at` dirty, no security fields changed), updates the cached model in-place, and returns `false` to cancel the DB write. `last_used_at` drifts in the DB — it only writes when a security-relevant field also changes. This is acceptable: `last_used_at` is telemetry, not a security control.

**Why `ksort()` before serializing params?**
Two callers passing `['b' => 2, 'a' => 1]` and `['a' => 1, 'b' => 2]` represent the same request. Without `ksort`, they produce different MD5 hashes and different cache entries. Sorting ensures equivalent param sets always hit the same cache slot.

**Valkey vs. Redis naming**
Valkey is wire-compatible with Redis, so the `redis` driver works as-is. The store is named `valkey` in the config to make the intent explicit and leave the existing `redis` store untouched for any code that references it by name.

**Deploy invalidation**
`php artisan cache:clear` in the Laravel Cloud deploy commands prevents stale serialized Eloquent models from being served after a deploy that changes model shape. No SHA-keying needed.

## Files Modified/Created

### Config
- `config/database.php` — add `valkey` Redis connection
- `config/cache.php` — add `valkey` store
- `.env.example` — add `VALKEY_*` vars

### Models
- `app/Models/PersonalAccessToken.php` — add `findToken()` override
- `app/Models/User.php` — add `findCached()` static method
- `app/Models/Account.php` — override `resolveRouteBinding()`

### Services / Core
- `app/Support/CacheKey.php` — new key-builder helper

### Observers
- `app/Observers/PersonalAccessTokenObserver.php` — new
- `app/Observers/UserObserver.php` — new
- `app/Observers/AccountObserver.php` — new

### Services
- `app/Services/GitHubService.php` — wrap all read methods
- `app/Services/JiraService.php` — wrap all read methods
- `app/Services/SlackService.php` — wrap read methods
- `app/Services/CalendarService.php` — wrap read methods

### API / Controllers / Responders
- `app/Http/Responders/Api/ListAccountsResponder.php` — cache accounts query
- `app/Http/Responders/Api/SendSlackMessageResponder.php` — bust `listMessages` cache
- `app/Http/Responders/Api/CreateCalendarEntryResponder.php` — bust `listEntries` cache
- `app/Http/Responders/Api/UpdateIssueResponder.php` — bust cache
- `app/Http/Responders/Api/TransitionIssueResponder.php` — bust cache
- `app/Http/Responders/Api/AssignIssueResponder.php` — bust cache
- `app/Http/Responders/Api/AddCommentResponder.php` — bust cache
- `app/Http/Responders/Api/UpdateCommentResponder.php` — bust cache
- `app/Http/Responders/Api/DeleteCommentResponder.php` — bust cache
- `app/Http/Responders/Api/AddWorklogResponder.php` — bust cache
- `app/Http/Responders/Api/UpdateWorklogResponder.php` — bust cache
- `app/Http/Responders/Api/DeleteWorklogResponder.php` — bust cache
- `app/Http/Responders/Api/CreateIssueResponder.php` — bust cache
- `app/Http/Responders/Api/DeleteIssueResponder.php` — bust cache
- `app/Http/Responders/Api/MoveIssuesToSprintResponder.php` — bust cache

### Providers
- `app/Providers/AppServiceProvider.php` — register three observers in `boot()`

### Tests
- `tests/Unit/Support/CacheKeyTest.php` — new
- `tests/Feature/Auth/TokenCachingTest.php` — new
- `tests/Feature/Auth/UserCachingTest.php` — new
- `tests/Feature/Api/AccountCachingTest.php` — new
- `tests/Feature/GitHub/GitHubCachingTest.php` — new
- `tests/Feature/Jira/JiraCachingTest.php` — new
- `tests/Feature/Jira/JiraWriteCachingTest.php` — new
- `tests/Feature/Api/SlackCachingTest.php` — new
- `tests/Feature/Api/CalendarCachingTest.php` — new

## Questions/Decisions Needed

- [ ] Confirm Valkey host/port/DB index for the production Forge server
- [ ] Confirm `php artisan cache:clear` has been added to the Laravel Cloud deploy commands

## Decisions Made

- **DB TTL**: 7 days — safe because all DB writes explicitly bust the cache
- **Valkey provisioning**: Server-side Forge config only; no code changes needed to switch drivers, just `CACHE_STORE=valkey`
- **Bust location**: Responders for third-party data, observers for first-party data; services are cache-unaware
- **`last_used_at` drift**: Acceptable — it's telemetry, not a security control; the DB value only updates when a security-relevant field also changes
- **`ksort()` before serializing params**: Ensures equivalent param sets always produce the same cache key regardless of insertion order
- **Deploy invalidation**: `cache:clear` in the Laravel Cloud deploy commands prevents stale serialized models

## Session History

### Session 1 (2026-10-10)

- Research complete from note 261; all decisions locked
- Plan drafted covering four phases: config + CacheKey, DB-tier caching, GitHub + Jira reads, Slack + Calendar reads + all write busting
