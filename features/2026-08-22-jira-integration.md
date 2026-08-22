# Jira Integration

- Branch: `feature/2026-08-22-jira-integration`

## Overview

Shell is an API gateway that exposes third-party service integrations (GitHub, Slack, mail, calendar) through a unified, account-scoped REST API. Clients authenticate with Sanctum tokens, and each account stores encrypted credentials for each connected service. The GitHub integration is the most recent addition and serves as the direct template for this feature.

The current system has no Jira support. Teams using Jira Cloud alongside GitHub cannot manage their tickets, sprints, or projects through Shell — they have to use the Jira API directly. This is a gap for users who want a single Shell-based integration layer for their tooling.

This feature adds full Jira Cloud support following the established GitHub integration pattern: HTTP Basic Auth (email + API token), an account-scoped credential set, a thin HTTP client, a service layer covering all read and write operations, API responders, JSON resources, Filament admin UI, and a comprehensive test suite.

### Background / Current Behavior
- **Current behavior**: Shell has no Jira Cloud integration. No routes, no credentials, no service layer.
- **Problem**: Accounts cannot connect to Jira; there are no endpoints to list projects, search issues, manage sprints, post comments, or log worklogs.
- **Where it happens**: Missing entirely from `app/Services/`, `app/Http/Responders/Api/`, `app/Http/Resources/`, and `routes/api.php`.
- **Impact**: Users cannot use Shell as a Jira proxy; all Jira API access must go through Atlassian directly.

### Target Outcome
This feature will add Jira Cloud as a first-class integration so that authenticated Shell accounts can:
- Connect a Jira Cloud workspace using email + API token credentials managed via the Filament admin
- Query projects, issues (by JQL), sprints, boards, filters, dashboards, and user data through Shell's API
- Create, update, delete, and transition issues; add/edit/delete comments (ADF format), worklogs, and watchers
- View personal issue views (assigned, reported, watching, mentions, recent activity) with pagination meta

## Requirements

### Functional
- [ ] Accounts can store Jira Cloud credentials (`jira_base_url`, `jira_email`, `jira_api_token`) via the Filament admin
- [ ] A `service.connected:jira` middleware guard rejects requests when credentials are not configured
- [ ] All Jira read endpoints return structured JSON resources with pagination meta where applicable
- [ ] All Jira write endpoints accept and validate input before forwarding to the Jira API
- [ ] Comments accept raw ADF JSON; `AddCommentResponder` and `UpdateCommentResponder` validate presence of `type`, `version`, and `content` keys
- [ ] `JiraRateLimitException` (429), `JiraAuthException` (401), and `JiraForbiddenException` (403) propagate as appropriate HTTP responses

### Non-Functional
- [ ] Observability: exception types map to HTTP status codes; `Retry-After` header forwarded on 429
- [ ] Performance: no caching layer — parity with GitHub integration
- [ ] Security/Permissions: `jira_api_token` stored with Laravel `encrypted` cast; all routes require `auth:sanctum` + `account.owner`
- [ ] Backwards compatibility: new columns only; no existing columns modified

### UX / Admin
- [ ] Filament `CreateAccount` has a Jira section: toggle + URL + email + token inputs; inputs visible only when toggle is on
- [ ] Filament `EditAccount` has the same section; all three fields use `dehydrateStateUsing` to preserve existing values when left blank

### Out of Scope
- Jira Server / Data Center (different auth model)
- Attachment upload (metadata + download URL only)
- Webhook ingestion from Jira
- Caching of Jira API responses

## Implementation Plan

> **Code style**: No useless comments. Only add comments for tricky/non-obvious logic.
> **Before committing**: Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` before every commit.
> **No auto-committing** (orchestrating session only): sub-agents implementing phases MUST commit their work.
> **One phase at a time**: complete the current phase before proceeding.

### Phase 1: Schema, Model & Factory

**Goal:** Add Jira credential columns to the `accounts` table, update the Account model, and add a factory state.

1. Create the migration:
   ```
   php artisan make:migration add_jira_columns_to_accounts_table --no-interaction
   ```
   Add four columns after `github_pat`:
   - `jira_used` — boolean, default false
   - `jira_base_url` — text, nullable
   - `jira_email` — text, nullable
   - `jira_api_token` — text, nullable

2. Update `app/Models/Account.php`:
   - Add `jira_used`, `jira_base_url`, `jira_email`, `jira_api_token` to `$fillable`
   - Add casts: `'jira_used' => 'boolean'`, `'jira_api_token' => 'encrypted'`
   - Add helper after `isGitHubConnected()`:
     ```php
     public function isJiraConnected(): bool
     {
         return $this->jira_used
             && ! empty($this->jira_base_url)
             && ! empty($this->jira_email)
             && ! empty($this->jira_api_token);
     }
     ```

3. Update `database/factories/AccountFactory.php` — add `withJira()` after `withGitHub()`:
   ```php
   public function withJira(): static
   {
       return $this->state(fn (array $attributes) => [
           'jira_used'      => true,
           'jira_base_url'  => 'https://test.atlassian.net',
           'jira_email'     => fake()->safeEmail(),
           'jira_api_token' => fake()->sha256(),
       ]);
   }
   ```

4. Run: `php artisan migrate --no-interaction`
5. Run: `vendor/bin/pint --dirty --format agent`
6. Confirm nothing broken: `php artisan test --compact`

---

### Phase 2: Exceptions & JiraClient

**Goal:** Create the Jira exception namespace and a thin HTTP client using HTTP Basic Auth.

1. Create `app/Exceptions/Jira/JiraAuthException.php` — extends `RuntimeException`, empty body (401).

2. Create `app/Exceptions/Jira/JiraForbiddenException.php` — extends `RuntimeException`, empty body (403).

3. Create `app/Exceptions/Jira/JiraRateLimitException.php`:
   ```php
   class JiraRateLimitException extends RuntimeException
   {
       public function __construct(public readonly int $retryAfter)
       {
           parent::__construct("Jira rate limit exceeded. Retry after {$retryAfter} seconds.");
       }
   }
   ```

4. Create `app/Services/JiraClient.php`:
   ```php
   class JiraClient
   {
       public function __construct(
           private string $baseUrl,
           private string $email,
           private string $apiToken,
       ) {}

       private function http(): PendingRequest
       {
           return Http::withBasicAuth($this->email, $this->apiToken)
               ->baseUrl(rtrim($this->baseUrl, '/') . '/rest/api/3');
       }

       private function agileHttp(): PendingRequest
       {
           return Http::withBasicAuth($this->email, $this->apiToken)
               ->baseUrl(rtrim($this->baseUrl, '/') . '/rest/agile/1.0');
       }

       public function get(string $path, array $query = []): array
       {
           return $this->handleResponse($this->http()->get($path, $query));
       }

       public function agileGet(string $path, array $query = []): array
       {
           return $this->handleResponse($this->agileHttp()->get($path, $query));
       }

       public function post(string $path, array $body = []): array
       {
           return $this->handleResponse($this->http()->post($path, $body));
       }

       // For PUT endpoints that return a response body (updateComment, updateWorklog)
       public function putJson(string $path, array $body = []): array
       {
           return $this->handleResponse($this->http()->put($path, $body));
       }

       // For PUT endpoints that return 204 no content (updateIssue, assignIssue)
       public function put(string $path, array $body = []): void
       {
           $this->handleResponse($this->http()->put($path, $body));
       }

       public function delete(string $path, array $query = []): void
       {
           $this->handleResponse($this->http()->delete($path, $query));
       }

       // Jira's addWatcher endpoint requires a bare JSON string body, not an object
       public function postRaw(string $path, string $body): void
       {
           $response = Http::withBasicAuth($this->email, $this->apiToken)
               ->baseUrl(rtrim($this->baseUrl, '/') . '/rest/api/3')
               ->withBody($body, 'application/json')
               ->post($path);

           $this->handleResponse($response);
       }

       private function handleResponse(Response $response): array
       {
           return match ($response->status()) {
               200, 201 => $response->json() ?? [],
               204      => [],
               404      => [],
               400      => throw new RuntimeException('Jira API error: ' . $response->body()),
               401      => throw new JiraAuthException(),
               403      => throw new JiraForbiddenException(),
               429      => throw new JiraRateLimitException((int) ($response->header('Retry-After') ?: 60)),
               default  => throw new RuntimeException("Jira API error: {$response->status()}"),
           };
       }
   }
   ```

5. Run: `vendor/bin/pint --dirty --format agent`

6. Write `tests/Unit/Services/JiraClientTest.php` using `uses(TestCase::class, RefreshDatabase::class)`.
   Fake with `Http::fake(['https://test.atlassian.net/*' => Http::response($body, $status)])`.
   Cover:
   - `get()` sends Basic Auth header (`Authorization` starts with `Basic `)
   - 200 → returns decoded JSON array
   - 404 → returns `[]` (no exception)
   - 201 → returns JSON; 204 → returns `[]`
   - 400 → throws `RuntimeException`
   - 401 → throws `JiraAuthException`
   - 403 → throws `JiraForbiddenException`
   - 429 → throws `JiraRateLimitException` with correct `retryAfter` (use try/catch to assert property)
   - 500 → throws `RuntimeException`
   - `agileGet()` hits `/rest/agile/1.0/` not `/rest/api/3/`
   - `post()` sends JSON body and returns response
   - `putJson()` returns array; `put()` returns void
   - `delete()` sends DELETE

7. Run: `php artisan test --compact --filter=JiraClientTest`

---

### Phase 3: JiraService — Read Operations

**Goal:** Create `app/Services/JiraService.php` with all read methods.

Constructor mirrors `GitHubService`:
```php
class JiraService
{
    private const DEFAULT_FIELDS = 'summary,status,assignee,reporter,priority,issuetype,project,created,updated,description,comment,labels,fixVersions,components,attachment,parent,subtasks';

    public function __construct(private Account $account) {}

    private function client(): JiraClient
    {
        return new JiraClient(
            $this->account->jira_base_url,
            $this->account->jira_email,
            $this->account->jira_api_token,
        );
    }
}
```

All methods delegate to `$this->client()` — no error handling in service layer; exceptions propagate to responders.

**Projects:**
- `projects(array $params = []): array` — `get('/project/search', $params)`
- `project(string $key): array` — `get("/project/{$key}")`
- `projectComponents(string $key): array` — `get("/project/{$key}/components")`
- `projectVersions(string $key): array` — `get("/project/{$key}/versions")`

**Issues:**
- `searchIssues(string $jql, array $params = []): array` — `post('/search', array_merge(['jql' => $jql, 'fields' => self::DEFAULT_FIELDS], $params))`
- `issue(string $key, array $params = []): array` — `get("/issue/{$key}", array_merge(['fields' => self::DEFAULT_FIELDS], $params))`
- `issueComments(string $key, array $params = []): array` — `get("/issue/{$key}/comment", $params)`
- `issueWorklogs(string $key): array` — `get("/issue/{$key}/worklog")`
- `issueChangelog(string $key, array $params = []): array` — `get("/issue/{$key}/changelog", $params)`
- `issueTransitions(string $key): array` — `get("/issue/{$key}/transitions")`
- `issueAttachments(string $key): array` — `get("/issue/{$key}", ['fields' => 'attachment'])`, return `$result['fields']['attachment'] ?? []`

**Personal views (JQL wrappers):**
- `myAssignedIssues(array $params = []): array` — JQL: `assignee = currentUser() ORDER BY updated DESC`
- `myReportedIssues(array $params = []): array` — JQL: `reporter = currentUser() ORDER BY updated DESC`
- `myWatchedIssues(array $params = []): array` — JQL: `watcher = currentUser() ORDER BY updated DESC`
- `issuesMentioningMe(array $params = []): array` — JQL: `text ~ currentUser() ORDER BY updated DESC`
- `issuesWithRecentActivity(array $params = []): array` — JQL: `assignee = currentUser() AND updated >= -7d ORDER BY updated DESC`

**Boards & Sprints (Agile API):**
- `boards(array $params = []): array` — `agileGet('/board', $params)`
- `board(int $boardId): array` — `agileGet("/board/{$boardId}")`
- `boardSprints(int $boardId, array $params = []): array` — `agileGet("/board/{$boardId}/sprint", $params)`
- `sprint(int $sprintId): array` — `agileGet("/sprint/{$sprintId}")`
- `sprintIssues(int $sprintId, array $params = []): array` — `agileGet("/sprint/{$sprintId}/issue", $params)`

**Users, Filters, Dashboards:**
- `myself(): array` — `get('/myself')`
- `user(string $accountId): array` — `get('/user', ['accountId' => $accountId])`
- `assignableUsers(string $projectKey): array` — `get('/user/assignable/search', ['project' => $projectKey])`
- `filters(): array` — `get('/filter/favourite')`
- `filter(int $id): array` — `get("/filter/{$id}")`
- `dashboards(): array` — `get('/dashboard')`

Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 4: JiraService — Write Operations

**Goal:** Add all write methods to `JiraService`.

**Issues:**
- `createIssue(array $fields): array` — `post('/issue', ['fields' => $fields])`
- `updateIssue(string $key, array $fields): void` — `put("/issue/{$key}", ['fields' => $fields])`
- `deleteIssue(string $key): void` — `delete("/issue/{$key}")`
- `transitionIssue(string $key, string $transitionId): void` — `post("/issue/{$key}/transitions", ['transition' => ['id' => $transitionId]])` cast to void
- `assignIssue(string $key, ?string $accountId): void` — `put("/issue/{$key}/assignee", ['accountId' => $accountId])`

**Comments (ADF bodies):**
- `addComment(string $key, array $adf): array` — `post("/issue/{$key}/comment", ['body' => $adf])`
- `updateComment(string $key, string $commentId, array $adf): array` — `putJson("/issue/{$key}/comment/{$commentId}", ['body' => $adf])`
- `deleteComment(string $key, string $commentId): void` — `delete("/issue/{$key}/comment/{$commentId}")`

**Worklogs:**
- `addWorklog(string $key, array $data): array` — `post("/issue/{$key}/worklog", $data)`
- `updateWorklog(string $key, string $worklogId, array $data): array` — `putJson("/issue/{$key}/worklog/{$worklogId}", $data)`
- `deleteWorklog(string $key, string $worklogId): void` — `delete("/issue/{$key}/worklog/{$worklogId}")`

**Watchers:**
- `addWatcher(string $key, string $accountId): void` — `postRaw("/issue/{$key}/watchers", json_encode($accountId))` — Jira requires a bare JSON string, not an object
- `removeWatcher(string $key, string $accountId): void` — `delete("/issue/{$key}/watchers", ['accountId' => $accountId])`

Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 5: Middleware & Routes

**Goal:** Register the `jira` service case in the middleware and add all routes.

1. Update `app/Http/Middleware/EnsureServiceConnected.php` — add before the `default` case:
   ```php
   'jira' => $account->isJiraConnected(),
   ```

2. Add all Jira responder `use` imports to `routes/api.php` after the GitHub imports block, under a `// Jira` comment, one class per line, alphabetically.

3. Add the Jira route group after the GitHub group (inside the existing `auth:sanctum, account.owner` middleware group):
   ```php
   Route::prefix('{account}/jira')->middleware('service.connected:jira')->group(function () {
       // Projects
       Route::get('/projects', ListProjectsResponder::class);
       Route::get('/projects/{key}', GetProjectResponder::class);
       Route::get('/projects/{key}/components', ListProjectComponentsResponder::class);
       Route::get('/projects/{key}/versions', ListProjectVersionsResponder::class);

       // Issues (read)
       Route::get('/issues/search', SearchIssuesResponder::class);
       Route::get('/issues/{key}', GetIssueResponder::class);
       Route::get('/issues/{key}/comments', GetIssueCommentsResponder::class);
       Route::get('/issues/{key}/worklogs', GetIssueWorklogsResponder::class);
       Route::get('/issues/{key}/transitions', GetIssueTransitionsResponder::class);
       Route::get('/issues/{key}/changelog', GetIssueChangelogResponder::class);
       Route::get('/issues/{key}/attachments', GetIssueAttachmentsResponder::class);

       // Issues (write)
       Route::post('/issues', CreateIssueResponder::class);
       Route::put('/issues/{key}', UpdateIssueResponder::class);
       Route::delete('/issues/{key}', DeleteIssueResponder::class);
       Route::post('/issues/{key}/transitions', TransitionIssueResponder::class);
       Route::put('/issues/{key}/assignee', AssignIssueResponder::class);

       // Comments
       Route::post('/issues/{key}/comments', AddCommentResponder::class);
       Route::put('/issues/{key}/comments/{commentId}', UpdateCommentResponder::class);
       Route::delete('/issues/{key}/comments/{commentId}', DeleteCommentResponder::class);

       // Worklogs
       Route::post('/issues/{key}/worklogs', AddWorklogResponder::class);
       Route::put('/issues/{key}/worklogs/{worklogId}', UpdateWorklogResponder::class);
       Route::delete('/issues/{key}/worklogs/{worklogId}', DeleteWorklogResponder::class);

       // Personal views
       Route::get('/user/issues', ListMyAssignedIssuesResponder::class);
       Route::get('/user/reported', ListMyReportedIssuesResponder::class);
       Route::get('/user/watching', ListMyWatchedIssuesResponder::class);
       Route::get('/user/mentions', ListMyMentionsResponder::class);
       Route::get('/user/activity', ListMyRecentActivityResponder::class);

       // Boards & Sprints
       Route::get('/boards', ListBoardsResponder::class);
       Route::get('/boards/{boardId}', GetBoardResponder::class);
       Route::get('/boards/{boardId}/sprints', ListSprintsResponder::class);
       Route::get('/sprints/{sprintId}', GetSprintResponder::class);
       Route::get('/sprints/{sprintId}/issues', ListSprintIssuesResponder::class);

       // Users
       Route::get('/myself', GetMyselfResponder::class);

       // Filters & Dashboards
       Route::get('/filters', ListFiltersResponder::class);
       Route::get('/filters/{id}', GetFilterResponder::class);
       Route::get('/dashboards', ListDashboardsResponder::class);
   });
   ```

4. Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 6: Responders & JSON Resources

**Goal:** Create all JSON resource classes and invokable responder classes.

#### JSON Resources — `app/Http/Resources/Jira/`

All extend `JsonResource`. Operate on `$this->resource` as an array. Use `?? null` fallbacks. Map nested objects inline; map arrays with `array_map`.

| Class | Key fields |
|---|---|
| `ProjectResource` | `id`, `key`, `name`, `description`, `projectTypeKey`, `style`, `isPrivate` |
| `IssueResource` | `id`, `key`; from `fields`: `summary`, `status.name`, `assignee.displayName/accountId`, `reporter.displayName/accountId`, `priority.name`, `issuetype.name`, `project.key/name`, `created`, `updated`, `labels`, `fixVersions` (name), `components` (name), `parent.key/summary`, `subtasks` (key+summary) |
| `CommentResource` | `id`, `author.accountId/displayName`, `body` (raw ADF as-is), `created`, `updated` |
| `WorklogResource` | `id`, `author.accountId/displayName`, `timeSpentSeconds`, `started`, `comment` |
| `TransitionResource` | `id`, `name`, `to.name`, `to.statusCategory.name` |
| `AttachmentResource` | `id`, `filename`, `mimeType`, `size`, `content` (download URL), `thumbnail` |
| `ChangelogResource` | `id`, `author.accountId/displayName`, `created`, `items` (field, from, to, fromString, toString) |
| `BoardResource` | `id`, `name`, `type`, `location.projectKey/projectName` |
| `SprintResource` | `id`, `name`, `state`, `startDate`, `endDate`, `goal` |
| `UserResource` | `accountId`, `displayName`, `emailAddress`, `active`, `avatarUrls` |
| `FilterResource` | `id`, `name`, `description`, `jql`, `owner.accountId/displayName`, `favourite` |
| `DashboardResource` | `id`, `name`, `description`, `popularity` |

#### Responders — `app/Http/Responders/Api/Jira/`

Each is an invokable class. Standard pattern:

```php
public function __invoke(Request $request, Account $account): ResourceType|JsonResponse
{
    $validated = $request->validate([...]);

    try {
        $data = (new JiraService($account))->method(...);
    } catch (JiraRateLimitException $e) {
        return response()->json(['error' => 'Jira rate limit exceeded.'], 429, ['Retry-After' => $e->retryAfter]);
    } catch (JiraAuthException) {
        return response()->json(['error' => 'Jira authentication failed.'], 401);
    } catch (JiraForbiddenException) {
        return response()->json(['error' => 'Jira access forbidden.'], 403);
    }

    if (empty($data)) {
        abort(404); // single-item endpoints only
    }

    return new SomeResource($data);
}
```

**Pagination meta for issue search / sprints / board sprints** (Jira returns `startAt`, `maxResults`, `total`):
```php
return IssueResource::collection($data['issues'] ?? [])
    ->additional(['meta' => [
        'start_at'    => $data['startAt'] ?? 0,
        'max_results' => $data['maxResults'] ?? 0,
        'total'       => $data['total'] ?? 0,
    ]]);
```

**Pagination meta for lists without server totals** (projects, filters, dashboards):
```php
$items = $data['values'] ?? $data;
return ProjectResource::collection($items)
    ->additional(['meta' => ['total_count' => count($items)]]);
```

**ADF validation** (`AddCommentResponder`, `UpdateCommentResponder`):
```php
$validated = $request->validate([
    'body'         => 'required|array',
    'body.type'    => 'required|string',
    'body.version' => 'required|integer',
    'body.content' => 'required|array',
]);
```

**Write validation rules:**
- `CreateIssueResponder`: `fields` required array; `fields.project.key`, `fields.issuetype.name`, `fields.summary` required strings
- `UpdateIssueResponder`: `fields` required array
- `TransitionIssueResponder`: `transition_id` required string
- `AssignIssueResponder`: `account_id` nullable string
- `AddWorklogResponder` / `UpdateWorklogResponder`: `time_spent` required string, `started` required date string

**HTTP status codes for write endpoints:**
- POST (create) → resource with `->response()->setStatusCode(201)`
- PUT returning body (updateComment, updateWorklog) → resource with 200
- PUT/DELETE with no body → `response()->json([], 204)`

Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 7: Filament Admin

**Goal:** Add a Jira section to `CreateAccount` and `EditAccount`.

1. `app/Filament/Resources/AccountResource/Pages/CreateAccount.php` — add after the GitHub section:
   ```php
   Section::make('Jira')
       ->schema([
           Toggle::make('jira_used')->default(false)->live(),
           TextInput::make('jira_base_url')
               ->url()
               ->placeholder('https://mycompany.atlassian.net')
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('jira_used'))
               ->requiredIf('jira_used', true),
           TextInput::make('jira_email')
               ->email()
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('jira_used'))
               ->requiredIf('jira_used', true),
           TextInput::make('jira_api_token')
               ->password()->revealable()
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('jira_used'))
               ->requiredIf('jira_used', true),
       ]),
   ```

2. `app/Filament/Resources/AccountResource/Pages/EditAccount.php` — add after the GitHub section:
   ```php
   Section::make('Jira')
       ->schema([
           Toggle::make('jira_used')->default(false)->live(),
           TextInput::make('jira_base_url')
               ->url()
               ->placeholder('https://mycompany.atlassian.net')
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('jira_used'))
               ->dehydrateStateUsing(fn (?string $state, Account $record) => filled($state) ? $state : $record->jira_base_url)
               ->helperText('Leave blank to keep the current URL.'),
           TextInput::make('jira_email')
               ->email()
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('jira_used'))
               ->dehydrateStateUsing(fn (?string $state, Account $record) => filled($state) ? $state : $record->jira_email)
               ->helperText('Leave blank to keep the current email.'),
           TextInput::make('jira_api_token')
               ->password()->revealable()
               ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('jira_used'))
               ->dehydrateStateUsing(fn (?string $state, Account $record) => filled($state) ? $state : $record->jira_api_token)
               ->helperText('Leave blank to keep the current token.'),
       ]),
   ```

3. Run: `vendor/bin/pint --dirty --format agent`

---

### Phase 8: Tests

**Goal:** Full test coverage mirroring `tests/Feature/GitHub/` and `tests/Unit/Services/GitHubClientTest.php`.

All feature tests: `uses(RefreshDatabase::class)`. Authenticate via `'Authorization' => 'Bearer '.$token->plainTextToken`. Create accounts with `Account::factory()->withJira()->for($user)->create()`. Define module-level `makeXxxData(array $overrides = []): array` helpers per file. Use `Http::fake(['https://test.atlassian.net/*' => ...])` for read tests; use `$this->mock(JiraService::class, ...)` for write tests.

**`tests/Unit/Services/JiraClientTest.php`** — already written in Phase 2.

**`tests/Feature/Jira/MiddlewareTest.php`**: Four guards against `GET /api/{id}/jira/projects`:
1. Unauthenticated → 401
2. Different user's account → 404
3. `jira_used = false` → 404
4. `jira_used = true` but credentials null → 404
5. Fully connected → 200

**`tests/Feature/Jira/FilamentAccountTest.php`**:
- Create: Jira fields hidden when toggle off; visible when toggled on
- Create: account saved with credentials
- Edit: blank token preserves existing value

**`tests/Feature/Jira/ProjectsTest.php`**: list → 200 + `data`/`meta.total_count`; get → 200/404; components/versions → 200.

**`tests/Feature/Jira/IssuesTest.php`**: search → 200 + `meta` (start_at, max_results, total); assert `startAt`/`maxResults` forwarded; get → 200/404; comments/worklogs/transitions/changelog/attachments → 200; 429 → propagates with `Retry-After`.

**`tests/Feature/Jira/CommentsTest.php`**: valid ADF → 201; missing `body.type`/`body.version`/`body.content`/non-array body → 422; update → 200; delete → 204.

**`tests/Feature/Jira/WorklogsTest.php`**: add (valid) → 201; missing `time_spent` → 422; update → 200; delete → 204.

**`tests/Feature/Jira/AttachmentsTest.php`**: get → 200 with id/filename/mimeType/size/content.

**`tests/Feature/Jira/BoardsTest.php`**: list → 200; get → 200/404.

**`tests/Feature/Jira/SprintsTest.php`**: list board sprints → 200 + meta; get sprint → 200/404; sprint issues → 200 + meta.

**`tests/Feature/Jira/UserIssuesTest.php`**: all five personal view endpoints → 200 + `meta` present on each.

**`tests/Feature/Jira/MyselfTest.php`**: `/myself` → 200 with UserResource fields.

**`tests/Feature/Jira/FiltersTest.php`**: list → 200; get → 200/404.

**`tests/Feature/Jira/DashboardsTest.php`**: list → 200 + data.

Run: `php artisan test --compact --filter=Jira`

---

### Phase 9: Documentation

**Goal:** Write `resources/docs/jira.md` as the end-user reference.

Cover:
1. Prerequisites: Atlassian account + API token (Atlassian account settings → Security → API tokens → Create)
2. **Cloud only** — Server/Data Center not supported
3. Setup via Filament admin: base URL (`https://yourcompany.atlassian.net`), email, token
4. All endpoints with HTTP method, path, parameters, and response shape
5. Pagination: `startAt`/`maxResults` query params; `meta.start_at`/`meta.max_results`/`meta.total` in response
6. JQL syntax examples for `/issues/search`
7. ADF comment format with minimal example and link to Atlassian ADF docs
8. Error codes: 401 (credentials), 403 (permission), 404 (not found), 422 (validation), 429 (rate limited — `Retry-After`)
9. Note on `/user/mentions`: `text ~` is approximate; false positives are possible

---

## Progress

### Completed
- Research and planning
- Phase 1: Schema, Model & Factory
- Phase 2: Exceptions & JiraClient
- Phase 3: JiraService — Read Operations
- Phase 4: JiraService — Write Operations
- Phase 5: Middleware & Routes
- Phase 6: Responders & JSON Resources
- Phase 7: Filament Admin
- Phase 8: Tests

### In Progress
_(nothing)_

### To Do
- Phase 9: Documentation

## Technical Notes

- **Jira Cloud REST API v3** for all standard operations. **Agile API** (`/rest/agile/1.0/`) for boards and sprints only — requires separate `agileHttp()` on `JiraClient`.
- **ADF** is required for comment bodies in v3. Callers pass raw ADF; Shell validates `type`/`version`/`content` presence only.
- **Field selection**: `DEFAULT_FIELDS` constant as default; callers override via `fields` param; `IssueResource` maps known fields only.
- **Pagination**: Jira's `startAt`/`maxResults`/`total` surfaced as `start_at`/`max_results`/`total` in `meta`.
- **`/user/mentions`**: `text ~ currentUser()` is approximate — searches all issue text as a string. Document the false positive risk.
- **Write test pattern**: Use `$this->mock(JiraService::class, ...)` — not `Http::fake()`. GitHub has no write endpoints; this follows the Slack write test convention.
- **`addWatcher` quirk**: Jira requires bare JSON string body. `postRaw()` on `JiraClient` handles this.
- **`updateComment`/`updateWorklog`**: PUT endpoints that return a body. `putJson(): array` handles these; `put(): void` is for 204 endpoints.
- **No named routes**: Follows existing `routes/api.php` convention.
- **No caching**: Parity with GitHub integration.

## Files Modified/Created

### Database
- `database/migrations/YYYY_MM_DD_HHMMSS_add_jira_columns_to_accounts_table.php`

### Models
- `app/Models/Account.php` — fillable, casts, `isJiraConnected()`

### Exceptions
- `app/Exceptions/Jira/JiraAuthException.php`
- `app/Exceptions/Jira/JiraForbiddenException.php`
- `app/Exceptions/Jira/JiraRateLimitException.php`

### Services / Core
- `app/Services/JiraClient.php`
- `app/Services/JiraService.php`

### Middleware
- `app/Http/Middleware/EnsureServiceConnected.php` — add `'jira'` case

### Routes
- `routes/api.php` — Jira route group + imports

### API / Responders
- `app/Http/Responders/Api/Jira/` — ~35 invokable responder classes

### Resources
- `app/Http/Resources/Jira/` — 12 JSON resource classes

### Admin / CMS
- `app/Filament/Resources/AccountResource.php` — add `github_used` and `jira_used` `IconColumn` entries; apply `->label('Mail')`, `->label('Calendar')`, `->label('Slack')`, `->label('GitHub')`, `->label('Jira')` so column headings show the service name only
- `app/Filament/Resources/AccountResource/Pages/CreateAccount.php`
- `app/Filament/Resources/AccountResource/Pages/EditAccount.php`

### Factories / Seeders
- `database/factories/AccountFactory.php` — `withJira()` state

### Tests
- `tests/Unit/Services/JiraClientTest.php`
- `tests/Feature/Jira/MiddlewareTest.php`
- `tests/Feature/Jira/FilamentAccountTest.php`
- `tests/Feature/Jira/ProjectsTest.php`
- `tests/Feature/Jira/IssuesTest.php`
- `tests/Feature/Jira/CommentsTest.php`
- `tests/Feature/Jira/WorklogsTest.php`
- `tests/Feature/Jira/AttachmentsTest.php`
- `tests/Feature/Jira/BoardsTest.php`
- `tests/Feature/Jira/SprintsTest.php`
- `tests/Feature/Jira/UserIssuesTest.php`
- `tests/Feature/Jira/MyselfTest.php`
- `tests/Feature/Jira/FiltersTest.php`
- `tests/Feature/Jira/DashboardsTest.php`

### Docs
- `resources/docs/jira.md`

## Testing
⬜ **Not started**

## Decisions Made
- **Auth**: HTTP Basic Auth (email + API token); Jira Cloud only
- **ADF comments**: Accept raw ADF; validate `type`/`version`/`content`; no transformation
- **Field selection**: `DEFAULT_FIELDS` constant; callers override via `fields` param
- **Pagination meta**: `start_at`, `max_results`, `total` in `meta` for paginated endpoints
- **Attachments**: Read only (metadata + download URLs)
- **`/user/mentions`**: Keep endpoint; document `text ~` approximation
- **Write test pattern**: `$this->mock(JiraService::class, ...)` following Slack convention
- **404 handling**: Client returns `[]`; responders `abort(404)` on empty single-item results
- **`addWatcher`**: Bare JSON string body via `postRaw()` on client
- **`updateComment`/`updateWorklog`**: `putJson(): array` for PUT-with-body; `put(): void` for 204
- **No named routes**: Follows existing convention

## Session History

### Session 1
- Compiled design brief from research notes covering auth, schema, client, service, routes, responders, resources, Filament, and tests
- Researched existing GitHub/Slack patterns across all layers
- Wrote phased implementation plan
