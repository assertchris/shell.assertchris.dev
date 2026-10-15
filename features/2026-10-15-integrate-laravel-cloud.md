# Integrate Laravel Cloud

- Branch: `feature/2026-10-15-integrate-laravel-cloud`

## Overview

`shell.assertchris.dev` is my personal shell API. It pulls third-party services (GitHub, Jira, Slack, Google Calendar) together behind one REST interface. Right now it has no view of Laravel Cloud. Deployments, environments, logs, metrics, and managed queues only live in the dashboard or the CLI.

This integration makes Laravel Cloud a first-class service. It adds read-only query endpoints and a small set of write operations (run Artisan commands, purge edge caches, manage environment and queue state) under `/{account}/laravel-cloud`. Each account stores one encrypted API token, and the Filament admin manages it.

### Background / Current Behavior

- **Current behavior**: The shell API exposes no Laravel Cloud data.
- **Problem**: If the shell is your control plane, you can't monitor deployments, tail logs, run Artisan commands, or check environment health without leaving it.
- **Where it happens**: The gaps sit in `routes/api.php`, `app/Http/Responders/Api/`, and the `accounts` table schema.
- **Impact**: Anyone who uses the shell as a control plane feels this. It matters most when you're watching production workloads on Laravel Cloud.

### Target Outcome

This feature adds a `laravel-cloud` service integration. Users can:
- Query apps, environments, deployments, instances, database clusters, caches, and usage data
- Tail environment and deployment logs
- Run Artisan commands on live environments and poll for completion
- Purge edge caches and manage environment and queue state
- Set their Laravel Cloud API token through the Filament admin

## Requirements

### Functional

- [ ] GET endpoints for all read-only resources (apps, environments, deployments, instances, databases, caches)
- [ ] GET log endpoints for environments and deployments
- [ ] POST endpoints for commands (run + poll), cache purge, environment start/stop, queue pause/resume/purge, and failed-job retry
- [ ] Credentials (single API token) stored encrypted per account; configurable via Filament admin

### Non-Functional

- [ ] Observability: errors come back as JSON error responses with HTTP status codes that match the Cloud API semantics
- [ ] Performance: slow data (apps, envs, clusters, caches, org, regions, usage) cached at 5 minutes; fast data (metrics, deployments, instances, failed jobs, commands list) cached at 60 seconds; logs and command polling uncached
- [ ] Security/Permissions: token encrypted at rest; route group gated by `service.connected:laravel-cloud` middleware; account ownership enforced by outer `account.owner` middleware
- [ ] Backwards compatibility: existing service routes and admin behaviour don't change

### UX / Admin

- [ ] Laravel Cloud section in Create/Edit account form (toggle + revealable token field)
- [ ] `Laravel Cloud` icon column in account table

### Out of Scope

- Secrets and environment variable endpoints (read or write)
- Initiating new deployments (git push handles this)
- Deleting failed jobs (single or bulk)

## Implementation Plan

> **No auto-committing**: Only commit when Chris asks. `git status`, `git diff`, and `git log` are read-only, so don't follow them with `git add` or `git commit` unless Chris says "commit." **This rule applies to the orchestrating session only. Sub-agents implementing phases MUST commit their work at the end of each phase. Don't pass "do not commit" instructions down to phase agents.**
> **One step at a time**: Finish the current step before asking to move on.
> **Before committing**: Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` before every commit.

### Pre-requisite

Add `LARAVEL_CLOUD_API_TOKEN=<token>` to the worktree's `.env` file before Phase 1. We use this to probe live API responses before writing any code — confirming the metrics endpoint shape and whether string IDs are slugs or UUIDs.

### Phase 1: Migration, Model, and Middleware

1. Generate the migration with `php artisan make:migration add_laravel_cloud_columns_to_accounts_table --no-interaction`. Add two columns inside `Schema::table('accounts', ...)`:
   - `$table->boolean('laravel_cloud_used')->default(false)->after('jira_api_token');`
   - `$table->text('laravel_cloud_api_token')->nullable()->after('laravel_cloud_used');`
   Run the migration with `php artisan migrate --no-interaction`.

2. In `app/Models/Account.php`:
   - Add `'laravel_cloud_used'` and `'laravel_cloud_api_token'` to the end of `$fillable`.
   - Add to `casts()`: `'laravel_cloud_used' => 'boolean'`, `'laravel_cloud_api_token' => 'encrypted'`.
   - Add `isLaravelCloudConnected(): bool`. It returns `$this->laravel_cloud_used && !empty($this->laravel_cloud_api_token)`.
   - Add `toLaravelCloudConfig(): array`. It returns `['api_token' => $this->laravel_cloud_api_token]`.

3. In the `EnsureServiceConnected` middleware, add `'laravel-cloud' => $account->isLaravelCloudConnected()` to the `match`.

4. In `database/factories/AccountFactory.php`, add a `withLaravelCloud()` state. It sets `laravel_cloud_used = true` and `laravel_cloud_api_token = 'fake-lc-token'`.

5. Write tests in `tests/Feature/LaravelCloud/MiddlewareTest.php`. They cover:
   - Unauthenticated request → 401
   - Account owned by another user → 403
   - `laravel_cloud_used = false` → 404
   - `laravel_cloud_used = true`, token null → 404
   - Connected account → 200 (use any GET endpoint once routes exist. Skip this assertion in Phase 1 and come back to it in Phase 7.)

### Phase 2: Client, Service, Exceptions, and Cache Config

1. Create three exception classes under `app/Exceptions/LaravelCloud/`:
   - `LaravelCloudAuthException extends RuntimeException`. It stays empty, with no constructor.
   - `LaravelCloudRateLimitException extends RuntimeException`. Its constructor takes `public readonly int $retryAfter`, and the parent message is `"Laravel Cloud rate limit exceeded. Retry after {$retryAfter} seconds."`.
   - `LaravelCloudClientException extends RuntimeException`. Its constructor takes `public readonly int $statusCode, string $message`, and the parent message gets set.

2. Create `app/Services/LaravelCloudClient.php`. The constructor takes `string $apiToken`. It follows the JiraClient pattern:
   - Private `http(): PendingRequest` returns `Http::withHeaders(['Authorization' => "Bearer {$this->apiToken}", 'Accept' => 'application/vnd.api+json'])->baseUrl('https://cloud.laravel.com/api')`.
   - Private `handleResponse(Response $response): array` matches on the status. 200/201 returns `$response->json() ?? []`. 204/404 returns `[]`. 401 throws `LaravelCloudAuthException`. 429 throws `LaravelCloudRateLimitException((int) ($response->header('Retry-After') ?: 60))`. Anything else throws `LaravelCloudClientException($response->status(), ...)`.
   - Public `get(string $path, array $query = []): array`.
   - Public `post(string $path, array $body = []): array`. It covers commands, purge, queue operations, and environment start/stop.

3. Create `app/Services/LaravelCloudService.php`. The constructor takes `Account $account`. The private `client(): LaravelCloudClient` method builds the client with `new LaravelCloudClient($this->account->laravel_cloud_api_token)`.

   Caching conventions:
   - Tags: `['laravel-cloud', "laravel-cloud:{$this->account->id}"]`.
   - **5-minute TTL** (`config('cache.ttl.laravel_cloud.slow')`): applications, environments, database clusters, caches, organization, regions, usage.
   - **60-second TTL** (`config('cache.ttl.laravel_cloud.fast')`): metrics, deployments, instances, failed jobs, commands list.
   - **No cache**: log endpoints, `GET /commands/{id}` (polling), and all POST endpoints.

   Each endpoint in the Route Surface section gets one public method. Implement every read method now. POST methods delegate to `$this->client()->post(...)` and return the raw response array without caching.

4. Add this to `config/cache.php` under `ttl`:
   ```php
   'laravel_cloud' => [
       'slow' => 300,   // 5 minutes — apps, environments, clusters, caches, org, regions, usage
       'fast' => 60,    // 60 seconds — metrics, deployments, instances, failed jobs, commands list
   ],
   ```

### Phase 3: API Resources

Create `app/Http/Resources/LaravelCloud/` with one resource class per resource type. Each one extends `Illuminate\Http\Resources\Json\JsonResource`, and each `toArray()` carries `@return array<string, mixed>`.

**JSON:API envelope flattening rule**: Cloud returns `{ "data": { "id": "...", "attributes": { ... } } }` for single resources and `{ "data": [ ... ] }` for collections. Resources receive the raw decoded array from the service. For single resources, read `$this->resource['data']['id']` and `$this->resource['data']['attributes']['field']`. For list endpoints, the service passes the inner `data` array to `Resource::collection()`.

Resource classes to create:
- `ApplicationResource` — id, name, slug, region, root_directory, slack_channel, avatar_url, framework, language, created_at, repository (full_name, default_branch)
- `EnvironmentResource` — id, name, slug, status, color, vanity_domain, framework, language, php_major_version, serving, uses_octane, uses_hibernation, timeout, shutdown_timeout, uses_push_to_deploy, uses_deploy_hook, network_settings, created_at
- `LogResource` — message, level, type, logged_at, data
- `DeploymentResource` — id, slug, status, branch_name, commit_hash, commit_message, commit_author, failure_reason, php_major_version, build_command, node_version, uses_octane, uses_hibernation, started_at, finished_at
- `CommandResource` — id, command, output, status, exit_code, failure_reason, started_at, finished_at, created_at
- `InstanceResource` — id and all instance attribute fields
- `FailedJobResource` — name, queue, failed_at, started_at, attempts, exception, retried_at, retry_reserved_until
- `DatabaseResource` — id and all database attribute fields
- `CacheResource` — id and all cache attribute fields
- `MetricsResource` — passes data through as-is. **Not JSON:API** — the metrics response is a plain object: `{ data: { cpu_usage: {...}, memory_usage: {...}, http_response_count: {...}, replica_count: {...}, web_workers_count: {...} }, meta: { period, available_periods } }`. Each metric has `labels`, `data` (array of `{x, y}`), and aggregate fields (`average`, `min`, `max`, `total`). Accepts optional `?period=` param (6h, 24h, 3d, 7d, 30d; default 24h).

### Phase 4: Responders and Routes

Create `app/Http/Responders/Api/LaravelCloud/` with one responder per endpoint. Each responder is a plain PHP class with `__invoke()`. They take no constructor injection. Each one builds `new LaravelCloudService($account)` inline.

**Error handling in every responder**: Wrap the service call in a try/catch. `LaravelCloudAuthException` returns 401, `LaravelCloudRateLimitException` returns 429 with a `Retry-After` header, and `LaravelCloudClientException` returns 502. Each returns `response()->json(['error' => '...'], status)`. If the service returns an empty array for a single-resource fetch, use `abort(404)`.

**Write responders** flush the cache. After a successful POST, they call `TaggedCache::for(tags: ["laravel-cloud:{$account->id}"])->flush()`.

**Write responders** that take a request body use inline `$request->validate([...])` with the array-of-strings rule syntax.

Responders:
```
ListApplicationsResponder           — GET  applications
GetApplicationResponder             — GET  applications/{application}
ListEnvironmentsResponder           — GET  applications/{application}/environments
GetEnvironmentResponder             — GET  environments/{environment}
ListEnvironmentLogsResponder        — GET  environments/{environment}/logs (query: from, to, type, query, cursor)
GetEnvironmentMetricsResponder      — GET  environments/{environment}/metrics
ListDeploymentsResponder            — GET  environments/{environment}/deployments
GetDeploymentResponder              — GET  deployments/{deployment}
GetDeploymentLogsResponder          — GET  deployments/{deployment}/logs
ListInstancesResponder              — GET  environments/{environment}/instances
GetInstanceResponder                — GET  instances/{instance}
ListFailedJobsResponder             — GET  instances/{instance}/failed-jobs
ListCommandsResponder               — GET  environments/{environment}/commands
GetCommandResponder                 — GET  commands/{command}
ListDatabasesResponder              — GET  databases
GetDatabaseResponder                — GET  databases/{database}
GetDatabaseMetricsResponder         — GET  databases/{database}/metrics
ListCachesResponder                 — GET  caches
GetCacheResponder                   — GET  caches/{cache}
GetCacheMetricsResponder            — GET  caches/{cache}/metrics
RunCommandResponder                 — POST environments/{environment}/commands
PurgeEdgeCacheResponder             — POST environments/{environment}/purge-cache
StartEnvironmentResponder           — POST environments/{environment}/start
StopEnvironmentResponder            — POST environments/{environment}/stop
RetryFailedJobResponder             — POST instances/{instance}/failed-jobs/{job}/retry
PauseQueueResponder                 — POST instances/{instance}/pause
ResumeQueueResponder                — POST instances/{instance}/resume
PurgeQueueResponder                 — POST instances/{instance}/purge
```

Add the route group to `routes/api.php`:
```php
Route::prefix('{account}/laravel-cloud')->middleware('service.connected:laravel-cloud')->group(function () {
    // routes here
});
```

### Phase 5: Filament Admin

1. **`AccountResource.php` table**: After the `jira_used` column, add:
   ```php
   IconColumn::make('laravel_cloud_used')
       ->label('Laravel Cloud')
       ->boolean(),
   ```

2. **`CreateAccount.php` form**: After the Jira section, add:
   ```php
   Section::make('Laravel Cloud')
       ->schema([
           Toggle::make('laravel_cloud_used')
               ->default(false)
               ->live(),
           TextInput::make('laravel_cloud_api_token')
               ->password()
               ->revealable()
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('laravel_cloud_used'))
               ->requiredIf('laravel_cloud_used', true),
       ]),
   ```

3. **`EditAccount.php` form**: After the Jira section, add the same Section with:
   ```php
   ->dehydrateStateUsing(fn (?string $state, Account $record) => filled($state) ? $state : $record->laravel_cloud_api_token)
   ->helperText('Leave blank to keep the current token.')
   ```
   on the token input. Don't add `->requiredIf()` on edit. A blank field keeps the existing token.

We don't need new imports, since both files already import the component classes.

### Phase 6: Documentation

Create `resources/docs/laravel-cloud.md`. Structure:

1. `# Laravel Cloud` heading
2. Auth preamble (Bearer token header, Accept header)
3. Setup section. Explain how to create an API token in the Laravel Cloud org settings and how to enter it in the Filament admin.
4. Special behaviour notes:
   - Commands are async. POST returns right away with `status: pending`. Poll `GET commands/{id}` until you see `command.success` or `command.failure`. HTTP status is always 200, so read `exit_code` for the outcome.
   - Logs require the `from` and `to` query params (ISO 8601 datetimes).
   - The purge cache body accepts exactly one of `path`, `prefix`, or `tag`. You can't combine them.
5. Global error table (401, 404, 429)
6. One `## METHOD /api/{account}/laravel-cloud/<path>` section per endpoint. Each one has a description, a parameter table, a curl example, a success response JSON, and an error responses table.

### Phase 7: Tests

Create `tests/Feature/LaravelCloud/` with these files:

- `MiddlewareTest.php` — auth (401), ownership (403), `laravel_cloud_used = false` (404), token null (404), connected account passes through
- `ApplicationsTest.php` — list apps (200, collection shape), get single app (200, data shape), missing app (404)
- `EnvironmentsTest.php` — list environments, get single, logs (with from/to params), metrics, start (POST), stop (POST)
- `DeploymentsTest.php` — list deployments, get single, deployment logs
- `CommandsTest.php` — list commands, get command (including polling: pending → success), run command (POST, validate body), command failure shape (exit_code = 1)
- `InstancesTest.php` — list instances, get instance, failed jobs, retry failed job (POST), pause/resume/purge queue (POST)
- `DatabasesTest.php` — list databases, get database, database metrics
- `CachesTest.php` — list caches, get cache, cache metrics, purge edge cache (POST, validate body: one of path/prefix/tag)

All test files use `uses(RefreshDatabase::class)` and no base class. They fake HTTP with `Http::fake(['https://cloud.laravel.com/*' => Http::response([...], status)])` and build accounts with `Account::factory()->withLaravelCloud()->for($user)->create()`.

Rate-limit tests assert `->assertStatus(429)` and check the `Retry-After` header.

## Progress

### Completed
- Research and planning
- Pre-requisite: API probe (prefixed UUIDs confirmed, metrics shape confirmed, database endpoint corrected to `/api/databases`)
- Phase 1: Migration, Model, Middleware, Factory
- Phase 2: Client, Service, Exceptions, Cache Config
- Phase 3: API Resources
- Phase 4: Responders + Routes
- Phase 5: Filament Admin

### In Progress
- (none)

### Blocked
- (none)

### To Do
- Phase 6: Docs
- Phase 7: Tests

## Technical Notes

- **JSON:API envelope**: Laravel Cloud returns `{ data: { id, type, attributes, relationships, links } }` for single resources and `{ data: [...], meta: {...}, links: {...} }` for collections. Resources flatten this. The service layer passes the full raw array from the client, and resources pull out `data.id` and `data.attributes.*`.
- **Resource ID format (confirmed by probe)**: All resource IDs are **prefixed UUIDs**, not bare UUIDs and not slugs. Format: `app-{uuid}`, `env-{uuid}`, `depl-{uuid}`, `db-{uuid}`, `cache-{uuid}`. A separate `slug` attribute exists (e.g. `"shell.assertchris.dev"`) but is never the ID. Route path params will receive these prefixed UUIDs.
- **Metrics endpoint is NOT JSON:API**: `GET /environments/{id}/metrics` returns a plain object — `{ data: { cpu_usage, memory_usage, http_response_count, replica_count, web_workers_count }, meta: { period, available_periods } }`. Each metric contains `labels`, `data` (array of `{x, y}`), and aggregates. Forward the full response body as-is. Accepts optional `?period=` (6h, 24h, 3d, 7d, 30d).
- **Databases endpoint**: The correct path is `/api/databases` (not `/api/database-clusters`). Database IDs use the `db-{uuid}` prefix.
- **Organization/regions/usage endpoints**: Returned 401 during the probe — these endpoints require org-level token scoping, not app-level. Removed from the route surface.
- **`environment_variables` in environment response**: Contains live credential values unredacted. Responders must NOT include this field in `EnvironmentResource.toArray()`.
- **Deployment slug**: A sequential integer string (`"1"`, `"2"`, …), not a UUID. The ID is still `depl-{uuid}`.
- **Command async pattern**: `POST /commands` is fire-and-forget at the transport layer. The responder returns the initial `CommandResource` with `status: pending`. The caller then polls `GET /commands/{id}`. Polling happens on the client side, not the server side.
- **Command HTTP status vs exit code**: The shell API always returns HTTP 200 for command responses. The `exit_code` field is the authoritative signal: 0 means success, 1 means failure, and null means still running.
- **No cache busting on read-only ops**: Only POST operations flush the TaggedCache for the account.
- **`service.connected` middleware key**: It must be `'laravel-cloud'` (hyphenated) to match the route middleware parameter `service.connected:laravel-cloud`.

## Files Modified/Created

### Database
- `database/migrations/YYYY_MM_DD_HHMMSS_add_laravel_cloud_columns_to_accounts_table.php`

### Models
- `app/Models/Account.php` — new fillable, casts, `isLaravelCloudConnected()`, `toLaravelCloudConfig()`

### Services / Core
- `app/Services/LaravelCloudClient.php` — new
- `app/Services/LaravelCloudService.php` — new
- `app/Exceptions/LaravelCloud/LaravelCloudAuthException.php` — new
- `app/Exceptions/LaravelCloud/LaravelCloudRateLimitException.php` — new
- `app/Exceptions/LaravelCloud/LaravelCloudClientException.php` — new
- `config/cache.php` — add `ttl.laravel_cloud` block

### API / Controllers / Responders
- `app/Http/Responders/Api/LaravelCloud/*.php` — ~30 new files
- `app/Http/Middleware/EnsureServiceConnected.php` — add `laravel-cloud` arm

### Views / Frontend
- (none)

### Admin / CMS
- `app/Filament/Resources/AccountResource.php` — new table column
- `app/Filament/Resources/AccountResource/Pages/CreateAccount.php` — new form section
- `app/Filament/Resources/AccountResource/Pages/EditAccount.php` — new form section

### Resources
- `app/Http/Resources/LaravelCloud/*.php` — ~10 new files

### Console / Jobs
- (none)

### Factories / Seeders
- `database/factories/AccountFactory.php` — add `withLaravelCloud()` state

### Tests
- `tests/Feature/LaravelCloud/MiddlewareTest.php`
- `tests/Feature/LaravelCloud/ApplicationsTest.php`
- `tests/Feature/LaravelCloud/EnvironmentsTest.php`
- `tests/Feature/LaravelCloud/DeploymentsTest.php`
- `tests/Feature/LaravelCloud/CommandsTest.php`
- `tests/Feature/LaravelCloud/InstancesTest.php`
- `tests/Feature/LaravelCloud/DatabasesTest.php`
- `tests/Feature/LaravelCloud/CachesTest.php`

### Docs
- `resources/docs/laravel-cloud.md` — new

## Testing

⬜ **Not started**: Tests get written in Phase 7.

## Questions/Decisions Needed

- [x] Does the live API return string slugs or UUIDs for resource IDs? **Prefixed UUIDs** (`app-{uuid}`, `env-{uuid}`, `depl-{uuid}`, `db-{uuid}`, `cache-{uuid}`). A `slug` attribute exists separately.
- [x] What's the exact metrics endpoint response shape? **Plain object, not JSON:API.** See Technical Notes → "Metrics endpoint is NOT JSON:API".

## Decisions Made

- **Environment start/stop**: IN
- **Initiate deployment**: OUT (git push handles it)
- **Failed job retry (single)**: IN
- **Failed job delete**: OUT
- **Secrets endpoints**: OUT
- **Env var endpoints**: OUT
- **Command failure HTTP status**: 200 with full CommandResource. `exit_code` determines the outcome.
- **Cache TTL — slow data**: 5 minutes
- **Cache TTL — fast data**: 60 seconds
- **Cache TTL — logs + command polling**: no cache
- **Database endpoint path**: `/api/databases` (not `/api/database-clusters`)
- **Organization/regions/usage**: OUT — these endpoints 401 with a per-resource API token
- **`environment_variables` field**: EXCLUDED from `EnvironmentResource` — contains live secrets
- **Resource ID format**: prefixed UUIDs (`app-{uuid}`, `env-{uuid}`, `depl-{uuid}`, `db-{uuid}`, `cache-{uuid}`)
- **Response normalisation**: Flatten the JSON:API envelope. `data.id` + `data.attributes` become a flat object.
- **Token per account**: One API token per account. Scoping is managed in the Laravel Cloud UI.
- **Validation**: Inline `$request->validate()` in write responders. No FormRequest classes, to match the existing convention.

## Session History

### Session 1
- Research complete. Plan written.
