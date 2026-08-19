# GitHub PAT Integration

- Branch: `feature/2026-08-19-github-accounts`

## Overview

Shell is a personal API hub that stores credentials for external services (Mail, Slack, Google Calendar) and exposes proxy endpoints that agents call. Agents authenticate to Shell with Sanctum tokens; Shell handles all external communication server-side. GitHub is not yet a supported service.

The goal is to add GitHub Personal Access Token (PAT) support to Shell's existing multi-account pattern so that agents can query GitHub data (PRs, issues, notifications, repositories) through Shell endpoints without ever touching a raw GitHub PAT themselves.

### Background / Current Behavior

- **Current behavior**: Shell supports Mail (IMAP), Google Calendar (OAuth2), and Slack (OAuth2). GitHub is not wired in.
- **Problem**: Agents have no way to query GitHub data through Shell. They would need raw GitHub credentials, which defeats Shell's purpose as a security boundary.
- **Where it happens**: No GitHub routes, no GitHub columns on `accounts`, no GitHub service layer.
- **Impact**: Agents cannot retrieve PR reviews, issues, notifications, or repository data via Shell.

### Target Outcome

This feature adds GitHub PAT storage and a proxy API layer so that agents can:
- Connect one or more GitHub accounts per user profile via the Filament admin UI
- Query repositories, pull requests, issues, notifications, and search results via Shell endpoints
- Have GitHub PATs encrypted at rest and never exposed to agents

## Architecture Decisions

### Token Strategy
- **Classic PAT, no expiry, maximum scope** — fine-grained PATs are explicitly out of scope. Shell is the security boundary.
- Required PAT scopes: `repo`, `notifications`, `read:org`
- Rotation is manual via Shell settings when needed.

### Schema
Two new columns on the `accounts` table (same pattern as Mail, Slack, Google Calendar):

| Column | Type | Notes |
|---|---|---|
| `github_used` | boolean | Master toggle — default false |
| `github_pat` | text, nullable | Encrypted at rest via `encrypted` cast |

No read/write permission split for now. Can be added later.

### Auth Flow
Agent → Shell Sanctum token + `account_id` route param → `EnsureAccountOwnership` middleware → `EnsureServiceConnected:github` middleware → Responder → `GitHubService` → GitHub API using stored PAT.

### API Design
All routes are `GET`. All require Sanctum auth. Route structure: `/github/{path}` with `{account}` implicit binding. Pagination metadata mirrored from GitHub via `meta` envelope. Short TTL cache on open PR lists (60s); uncached on search endpoints (GitHub rate-limits search at 30 req/min).

## Implementation Plan

> **Code style**: No useless comments. Only add comments that explain tricky/non-obvious code.
> **Before committing**: Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` before every commit.
> **No auto-committing**: Only commit when explicitly asked. **This rule applies to the orchestrating session only. Sub-agents implementing phases MUST commit their work at the end of each phase.**
> **One phase at a time**: Complete the current phase before starting the next.

---

### Phase 1: Migration, Account Model & Factory

**Goal**: Extend the `accounts` table with GitHub columns and update the model and factory to match.

#### Steps

1. **Migration** — `php artisan make:migration add_github_columns_to_accounts_table --no-interaction`
   - `github_used` — `boolean()->default(false)->after('slack_team_name')`
   - `github_pat` — `text()->nullable()->after('github_used')`
   - Rollback: `dropColumn(['github_used', 'github_pat'])`

2. **Account model** (`app/Models/Account.php`)
   - Add `github_used`, `github_pat` to `$fillable`
   - Add casts: `'github_used' => 'boolean'`, `'github_pat' => 'encrypted'`
   - Add helper: `isGitHubConnected(): bool` — returns `$this->github_used && !empty($this->github_pat)`
   - Add helper: `toGitHubConfig(): array` — returns `['pat' => $this->github_pat]`

3. **AccountFactory** (`database/factories/AccountFactory.php`)
   - Add `withGitHub()` state: sets `github_used: true`, `github_pat: fake()->sha256()`

4. **Tests** — update or add to `tests/Feature/AccountTest.php`:
   - `github_pat` is encrypted at rest (raw DB value differs from plaintext)
   - `github_used` casts to boolean
   - `isGitHubConnected()` returns true only when both `github_used` is true and `github_pat` is non-empty
   - `isGitHubConnected()` returns false when `github_used` is false even if PAT is set
   - `toGitHubConfig()` returns the decrypted PAT under `pat` key
   - `withGitHub()` factory state produces a connectable account

---

### Phase 2: Filament GitHub Section

**Goal**: Surface GitHub PAT management in the Filament `AccountResource` Create and Edit pages.

#### Steps

1. **CreateAccount page** (`app/Filament/Resources/AccountResource/Pages/CreateAccount.php`)
   - Add a `Section::make('GitHub')` after the Slack section
   - Fields:
     - `Toggle::make('github_used')->default(false)->live()`
     - `TextInput::make('github_pat')->password()->revealable()->visible(fn (Get $get) => $get('github_used'))->requiredIf('github_used', true)`

2. **EditAccount page** (`app/Filament/Resources/AccountResource/Pages/EditAccount.php`)
   - Same section and toggle
   - `github_pat` uses the preserve-on-blank pattern:
     ```php
     TextInput::make('github_pat')
         ->password()
         ->revealable()
         ->visible(fn (Get $get) => $get('github_used'))
         ->dehydrateStateUsing(fn (?string $state, Account $record) =>
             filled($state) ? $state : $record->github_pat
         )
         ->helperText('Leave blank to keep the current PAT.')
     ```

3. **Tests** (`tests/Feature/GitHub/FilamentAccountTest.php`):
   - Can create an account with `github_used` true and a PAT set
   - Can edit an account and leave PAT blank — existing PAT is preserved
   - Can edit an account and supply a new PAT — new value is saved

---

### Phase 3: GitHubClient & GitHubService

**Goal**: Build the service layer that wraps the GitHub API using the stored PAT.

#### Steps

1. **Exception classes** (each extends `RuntimeException`):
   - `app/Exceptions/GitHub/GitHubAuthException.php`
   - `app/Exceptions/GitHub/GitHubScopeException.php`
   - `app/Exceptions/GitHub/GitHubRateLimitException.php` — constructor accepts `int $retryAfter`

2. **`app/Services/GitHubClient.php`**
   - Constructor: `public function __construct(private string $pat) {}`
   - Private `http(): PendingRequest` — `Http::withHeaders(['Authorization' => "token {$this->pat}"])->baseUrl('https://api.github.com')`
     - **Note**: GitHub requires `Authorization: token {pat}` not `Bearer`. Do NOT use `Http::withToken()`.
   - Public `get(string $path, array $query = []): array`:
     - 401 → throw `GitHubAuthException`
     - 403 → throw `GitHubScopeException`
     - 404 → return `[]`
     - 429 → throw `new GitHubRateLimitException((int) $response->header('Retry-After', 60))`
     - Other non-2xx → throw `RuntimeException` with status code

3. **`app/Services/GitHubService.php`**
   - Constructor: `public function __construct(private Account $account) {}`
   - Private `client(): GitHubClient` — returns `new GitHubClient($this->account->github_pat)`
   - Public methods (each delegates to `$this->client()->get(...)`):
     - `repos(array $params = []): array` → `GET /user/repos`
     - `repo(string $owner, string $repo): array` → `GET /repos/{owner}/{repo}`
     - `pulls(string $owner, string $repo, array $params = []): array` → `GET /repos/{owner}/{repo}/pulls`
     - `pull(string $owner, string $repo, int $number): array` → `GET /repos/{owner}/{repo}/pulls/{number}`
     - `pullFiles(string $owner, string $repo, int $number): array`
     - `pullCommits(string $owner, string $repo, int $number): array`
     - `pullReviews(string $owner, string $repo, int $number): array`
     - `pullReviewComments(string $owner, string $repo, int $number, int $reviewId): array`
     - `pullRequestedReviewers(string $owner, string $repo, int $number): array`
     - `searchPulls(string $q, array $params = []): array` → `GET /search/issues` with `q` appending ` is:pr`
     - `searchIssues(string $q, array $params = []): array` → `GET /search/issues` with `q` appending ` is:issue`
     - `issues(array $params = []): array` → `GET /issues`
     - `userIssues(array $params = []): array` → `GET /user/issues`
     - `repoIssues(string $owner, string $repo, array $params = []): array`
     - `issue(string $owner, string $repo, int $number): array`
     - `issueComments(string $owner, string $repo, int $number): array`
     - `notifications(array $params = []): array` → `GET /notifications`
     - `notification(string $threadId): array` → `GET /notifications/threads/{threadId}`

4. **Tests** (`tests/Unit/Services/GitHubClientTest.php`):
   - Request uses `Authorization: token {pat}` header (not Bearer)
   - 401 throws `GitHubAuthException`
   - 403 throws `GitHubScopeException`
   - 429 throws `GitHubRateLimitException` with correct `retryAfter` value
   - 404 returns `[]`
   - `searchPulls()` appends ` is:pr` to query string
   - `searchIssues()` appends ` is:issue` to query string

---

### Phase 4: Middleware & Route Registration

**Goal**: Wire GitHub into the service connection middleware and register all API routes.

#### Steps

1. **`EnsureServiceConnected` middleware** (`app/Http/Middleware/EnsureServiceConnected.php`)
   - Add `'github'` case: `return $account->isGitHubConnected()`
   - Follow the exact same pattern as the `'slack'` case

2. **`routes/api.php`**
   - Inside the existing `auth:sanctum, account.owner` group, add:
     ```php
     Route::prefix('github')->middleware('service.connected:github')->group(function () {
         Route::get('/repos', ListReposResponder::class);
         Route::get('/repos/{owner}/{repo}', GetRepoResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls', ListPullsResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls/{number}', GetPullResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls/{number}/files', GetPullFilesResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls/{number}/commits', GetPullCommitsResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls/{number}/reviews', GetPullReviewsResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls/{number}/reviews/{review_id}/comments', GetPullReviewCommentsResponder::class);
         Route::get('/repos/{owner}/{repo}/pulls/{number}/requested-reviewers', GetPullRequestedReviewersResponder::class);
         Route::get('/search/pulls', SearchPullsResponder::class);
         Route::get('/search/issues', SearchIssuesResponder::class);
         Route::get('/issues', ListIssuesResponder::class);
         Route::get('/user/issues', ListUserIssuesResponder::class);
         Route::get('/repos/{owner}/{repo}/issues', ListRepoIssuesResponder::class);
         Route::get('/repos/{owner}/{repo}/issues/{number}', GetIssueResponder::class);
         Route::get('/repos/{owner}/{repo}/issues/{number}/comments', GetIssueCommentsResponder::class);
         Route::get('/notifications', ListNotificationsResponder::class);
         Route::get('/notifications/{thread_id}', GetNotificationResponder::class);
     });
     ```

3. **Tests** (`tests/Feature/GitHub/MiddlewareTest.php`):
   - 401 without Sanctum token
   - 404 when account belongs to another user
   - 503 when `github_used` is false
   - 503 when `github_pat` is null even though `github_used` is true
   - 200 when connected (mock `GitHubClient` to avoid real HTTP)

---

### Phase 5: Resources & Responders — Repos, PRs & Search

**Goal**: Implement JSON Resources and Responders for repos, pull requests, and search endpoints.

#### JSON Resources (`app/Http/Resources/GitHub/`)

- **`GitHubRepoResource`** — id, name, full_name, description, private, html_url, default_branch, updated_at
- **`GitHubPullResource`** — number, title, state, draft, html_url, user (login, avatar_url), created_at, updated_at, merged_at, head (ref, sha), base (ref, sha)
- **`GitHubPullFileResource`** — filename, status, additions, deletions, changes, patch (nullable)
- **`GitHubCommitResource`** — sha, commit (message, author), html_url
- **`GitHubReviewResource`** — id, user (login), state, body, submitted_at
- **`GitHubSearchResource`** — total_count + items as the appropriate resource collection

Pagination envelope for list responses:
```json
{ "data": [...], "meta": { "total_count": N } }
```

#### Responders (`app/Http/Responders/Api/GitHub/`)

- **`ListReposResponder`** — validates `per_page` (int, default 30, max 100), `page` (int, default 1), `type` (owner/member/all). Returns `GitHubRepoResource::collection()`.
- **`GetRepoResponder`** — no params. Returns `new GitHubRepoResource($data)`.
- **`ListPullsResponder`** — validates `state` (open/closed/all, default open), `page`, `per_page`. Caches open lists 60s: key `github.pulls.{account_id}.{owner}.{repo}.{state}.{page}`. Returns `GitHubPullResource::collection()`.
- **`GetPullResponder`** — single PR. Cache 60s if open, 300s if merged/closed.
- **`GetPullFilesResponder`** — passthrough. Returns `GitHubPullFileResource::collection()`.
- **`GetPullCommitsResponder`** — passthrough. Returns `GitHubCommitResource::collection()`.
- **`GetPullReviewsResponder`** — passthrough. Returns `GitHubReviewResource::collection()`.
- **`GetPullReviewCommentsResponder`** — validates `review_id` is integer. Passthrough.
- **`GetPullRequestedReviewersResponder`** — passthrough raw array (users + teams).
- **`SearchPullsResponder`** — validates `q` (required string), `page`, `per_page`. No caching. Returns `GitHubSearchResource` wrapping `GitHubPullResource::collection($data['items'])`.
- **`SearchIssuesResponder`** — same shape.

#### Tests

- **`tests/Feature/GitHub/ReposTest.php`** — list repos, get single repo, pagination params forwarded, unknown repo returns 404
- **`tests/Feature/GitHub/PullsTest.php`** — list (open/closed/all filters), get PR, get files/commits/reviews/review-comments/requested-reviewers; open PR list is cached; closed PR list uses longer TTL
- **`tests/Feature/GitHub/SearchTest.php`** — search pulls appends `is:pr`; search issues appends `is:issue`; missing `q` returns 422; 429 from GitHub propagates correctly

---

### Phase 6: Resources & Responders — Issues & Notifications

**Goal**: Implement JSON Resources and Responders for issues and notifications.

#### JSON Resources (`app/Http/Resources/GitHub/`)

- **`GitHubIssueResource`** — number, title, state, html_url, body, user (login), labels (name, color), created_at, updated_at, closed_at
- **`GitHubIssueCommentResource`** — id, user (login), body, created_at, updated_at
- **`GitHubNotificationResource`** — id, reason, unread, subject (title, type, url), repository (full_name), updated_at

#### Responders (`app/Http/Responders/Api/GitHub/`)

- **`ListIssuesResponder`** — validates `filter` (assigned/created/mentioned/subscribed/all), `state` (open/closed/all), `page`, `per_page`.
- **`ListUserIssuesResponder`** — validates `state`, `page`, `per_page`.
- **`ListRepoIssuesResponder`** — validates `state`, `labels` (comma-separated string), `page`, `per_page`.
- **`GetIssueResponder`** — single issue.
- **`GetIssueCommentsResponder`** — list of `GitHubIssueCommentResource`.
- **`ListNotificationsResponder`** — validates `all` (boolean, default false), `participating` (boolean, default false). No caching.
- **`GetNotificationResponder`** — single notification thread by `thread_id`.

#### Tests

- **`tests/Feature/GitHub/IssuesTest.php`** — list (all three scopes), get single issue, list comments, state/labels filters forwarded
- **`tests/Feature/GitHub/NotificationsTest.php`** — list notifications, `all=true` param forwarded, get single thread

## Files to Create/Modify

### Database
- `database/migrations/YYYY_MM_DD_HHMMSS_add_github_columns_to_accounts_table.php`

### Models & Factories
- `app/Models/Account.php` — github_used, github_pat, isGitHubConnected(), toGitHubConfig()
- `database/factories/AccountFactory.php` — withGitHub() state

### Exceptions
- `app/Exceptions/GitHub/GitHubAuthException.php`
- `app/Exceptions/GitHub/GitHubScopeException.php`
- `app/Exceptions/GitHub/GitHubRateLimitException.php`

### Services
- `app/Services/GitHubClient.php`
- `app/Services/GitHubService.php`

### Middleware & Routes
- `app/Http/Middleware/EnsureServiceConnected.php` — add github case
- `routes/api.php` — GitHub route group

### Responders (19 files in `app/Http/Responders/Api/GitHub/`)
- ListReposResponder, GetRepoResponder
- ListPullsResponder, GetPullResponder, GetPullFilesResponder, GetPullCommitsResponder
- GetPullReviewsResponder, GetPullReviewCommentsResponder, GetPullRequestedReviewersResponder
- SearchPullsResponder, SearchIssuesResponder
- ListIssuesResponder, ListUserIssuesResponder, ListRepoIssuesResponder, GetIssueResponder, GetIssueCommentsResponder
- ListNotificationsResponder, GetNotificationResponder

### Resources (9 files in `app/Http/Resources/GitHub/`)
- GitHubRepoResource, GitHubPullResource, GitHubPullFileResource
- GitHubCommitResource, GitHubReviewResource, GitHubSearchResource
- GitHubIssueResource, GitHubIssueCommentResource, GitHubNotificationResource

### Admin / Filament
- `app/Filament/Resources/AccountResource/Pages/CreateAccount.php`
- `app/Filament/Resources/AccountResource/Pages/EditAccount.php`

### Tests
- `tests/Feature/AccountTest.php` — extended with GitHub assertions
- `tests/Unit/Services/GitHubClientTest.php`
- `tests/Feature/GitHub/MiddlewareTest.php`
- `tests/Feature/GitHub/FilamentAccountTest.php`
- `tests/Feature/GitHub/ReposTest.php`
- `tests/Feature/GitHub/PullsTest.php`
- `tests/Feature/GitHub/SearchTest.php`
- `tests/Feature/GitHub/IssuesTest.php`
- `tests/Feature/GitHub/NotificationsTest.php`

## Technical Notes

- **PAT Authorization header**: GitHub requires `Authorization: token {pat}` — NOT `Bearer`. `Http::withToken()` sends `Bearer`, so use `Http::withHeaders(['Authorization' => "token {$pat}"])` explicitly.
- **Search rate limits**: GitHub search is 30 req/min authenticated. Do not cache search — it masks rate limit signals. Let 429s bubble to the agent.
- **Cache key scope**: Always include `account_id` in cache keys to prevent cross-account data leakage.
- **No write endpoints**: All endpoints are read-only (`GET`). Write operations are explicitly out of scope.
- **`github_read` / `github_write` toggles**: Not included in this feature. Can be added in a follow-up migration.
