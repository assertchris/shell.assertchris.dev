# GitHub

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

A GitHub Personal Access Token (PAT) must be added via the admin panel (Accounts → Edit → GitHub section). Enable the **GitHub** toggle and paste your PAT. The PAT requires at least `repo`, `read:user`, and `notifications` scopes.

All GitHub endpoints are prefixed with `/api/{account}/github/` where `{account}` is your account ID.

If the GitHub PAT is missing or invalid, all endpoints return `404`. If the GitHub API rate limit is exceeded, endpoints return `429` with a `Retry-After` header indicating when to retry.

---

## GET /api/{account}/github/repos

Returns repositories accessible to the authenticated GitHub user.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `type` | string | `owner` | Filter by `owner`, `member`, or `all` |
| `per_page` | integer | `30` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

```bash
curl -s "https://your-domain.com/api/1/github/repos?type=owner&per_page=10" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 123456789,
      "name": "my-repo",
      "full_name": "cgpitt/my-repo",
      "description": "A repository",
      "private": false,
      "html_url": "https://github.com/cgpitt/my-repo",
      "default_branch": "main",
      "updated_at": "2026-08-19T12:00:00Z"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or GitHub is not connected |
| 429 | GitHub rate limit exceeded — check `Retry-After` header |

---

## GET /api/{account}/github/repos/{owner}/{repo}

Returns details for a specific repository.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner (user or organisation) |
| `repo` | string | Repository name |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "id": 123456789,
    "name": "my-repo",
    "full_name": "cgpitt/my-repo",
    "description": "A repository",
    "private": false,
    "html_url": "https://github.com/cgpitt/my-repo",
    "default_branch": "main",
    "updated_at": "2026-08-19T12:00:00Z"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or repository not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls

Returns pull requests for a repository. Open pull requests are cached for 60 seconds.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `owner` | string | — | Repository owner |
| `repo` | string | — | Repository name |
| `state` | string | `open` | Filter by `open`, `closed`, or `all` |
| `per_page` | integer | `30` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls?state=open" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "number": 42,
      "title": "Add new feature",
      "state": "open",
      "draft": false,
      "html_url": "https://github.com/cgpitt/my-repo/pull/42",
      "user": {
        "login": "cgpitt",
        "avatar_url": "https://avatars.githubusercontent.com/u/12345"
      },
      "created_at": "2026-08-19T10:00:00Z",
      "updated_at": "2026-08-19T12:00:00Z",
      "merged_at": null,
      "head": {
        "ref": "feature/new-thing",
        "sha": "abc1234"
      },
      "base": {
        "ref": "main",
        "sha": "def5678"
      }
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or repository not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls/{number}

Returns a specific pull request. Cached for 60 seconds (300 seconds if closed/merged).

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Pull request number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls/42" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as a single item from [List pulls](#get-apiaccount-github-repos-owner-repo-pulls).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or pull request not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls/{number}/files

Returns the list of files changed in a pull request.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Pull request number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls/42/files" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "filename": "app/Http/Controllers/FooController.php",
      "status": "modified",
      "additions": 12,
      "deletions": 3,
      "changes": 15,
      "patch": "@@ -1,3 +1,12 @@\n ..."
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or pull request not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls/{number}/commits

Returns the commits in a pull request.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Pull request number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls/42/commits" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "sha": "abc1234",
      "commit": {
        "message": "Add new feature",
        "author": {
          "name": "Chris Pitt",
          "email": "chris@example.com",
          "date": "2026-08-19T10:00:00Z"
        }
      },
      "html_url": "https://github.com/cgpitt/my-repo/commit/abc1234"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or pull request not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls/{number}/reviews

Returns reviews submitted on a pull request.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Pull request number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls/42/reviews" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 987654321,
      "user": {
        "login": "reviewer"
      },
      "state": "APPROVED",
      "body": "Looks good!",
      "submitted_at": "2026-08-19T11:00:00Z"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or pull request not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls/{number}/reviews/{review_id}/comments

Returns inline comments for a specific review on a pull request.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Pull request number |
| `review_id` | integer | Review ID |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls/42/reviews/987654321/comments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 111222333,
      "user": {
        "login": "reviewer"
      },
      "body": "Consider extracting this to a method.",
      "created_at": "2026-08-19T11:05:00Z",
      "updated_at": "2026-08-19T11:05:00Z"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or review not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/pulls/{number}/requested-reviewers

Returns the users and teams requested to review a pull request.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Pull request number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/pulls/42/requested-reviewers" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "users": [
      { "login": "reviewer-one" }
    ],
    "teams": []
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or pull request not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/search/pulls

Searches pull requests across GitHub using the GitHub search syntax.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `q` | string | GitHub search query (required) |
| `per_page` | integer | Results per page (max 100, optional) |
| `page` | integer | Page number (optional) |

```bash
curl -s "https://your-domain.com/api/1/github/search/pulls?q=is:open+repo:cgpitt/my-repo" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "number": 42,
      "title": "Add new feature",
      "state": "open",
      "draft": false,
      "html_url": "https://github.com/cgpitt/my-repo/pull/42",
      "user": { "login": "cgpitt", "avatar_url": "..." },
      "created_at": "2026-08-19T10:00:00Z",
      "updated_at": "2026-08-19T12:00:00Z",
      "merged_at": null,
      "head": { "ref": "feature/new-thing", "sha": "abc1234" },
      "base": { "ref": "main", "sha": "def5678" }
    }
  ],
  "meta": {
    "total_count": 47
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or GitHub not connected |
| 422 | `q` parameter is required |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/search/issues

Searches issues across GitHub using the GitHub search syntax.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `q` | string | GitHub search query (required) |
| `per_page` | integer | Results per page (max 100, optional) |
| `page` | integer | Page number (optional) |

```bash
curl -s "https://your-domain.com/api/1/github/search/issues?q=is:open+label:bug+repo:cgpitt/my-repo" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "number": 7,
      "title": "Button is broken on mobile",
      "state": "open",
      "html_url": "https://github.com/cgpitt/my-repo/issues/7",
      "body": "Steps to reproduce...",
      "user": { "login": "cgpitt" },
      "labels": [
        { "name": "bug", "color": "d73a4a" }
      ],
      "created_at": "2026-08-18T09:00:00Z",
      "updated_at": "2026-08-19T10:00:00Z",
      "closed_at": null
    }
  ],
  "meta": {
    "total_count": 3
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or GitHub not connected |
| 422 | `q` parameter is required |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/issues

Returns issues across all repositories assigned to, created by, mentioned in, or subscribed to by the authenticated user.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `filter` | string | `assigned` | One of `assigned`, `created`, `mentioned`, `subscribed`, `all` |
| `state` | string | `open` | One of `open`, `closed`, `all` |
| `per_page` | integer | `30` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

```bash
curl -s "https://your-domain.com/api/1/github/issues?filter=assigned&state=open" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-github-search-issues) `data` items.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or GitHub not connected |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/user/issues

Returns issues assigned to the authenticated GitHub user across all repositories they have access to.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `state` | string | `open` | One of `open`, `closed`, `all` |
| `per_page` | integer | `30` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

```bash
curl -s "https://your-domain.com/api/1/github/user/issues?state=open" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [List issues](#get-apiaccount-github-issues) response.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or GitHub not connected |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/issues

Returns issues for a specific repository.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `owner` | string | — | Repository owner |
| `repo` | string | — | Repository name |
| `state` | string | `open` | One of `open`, `closed`, `all` |
| `per_page` | integer | `30` | Results per page (max 100) |
| `page` | integer | `1` | Page number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/issues?state=open" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [List issues](#get-apiaccount-github-issues) response.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or repository not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/issues/{number}

Returns a specific issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Issue number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/issues/7" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "number": 7,
    "title": "Button is broken on mobile",
    "state": "open",
    "html_url": "https://github.com/cgpitt/my-repo/issues/7",
    "body": "Steps to reproduce...",
    "user": { "login": "cgpitt" },
    "labels": [
      { "name": "bug", "color": "d73a4a" }
    ],
    "created_at": "2026-08-18T09:00:00Z",
    "updated_at": "2026-08-19T10:00:00Z",
    "closed_at": null
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or issue not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/repos/{owner}/{repo}/issues/{number}/comments

Returns comments on a specific issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `owner` | string | Repository owner |
| `repo` | string | Repository name |
| `number` | integer | Issue number |

```bash
curl -s "https://your-domain.com/api/1/github/repos/cgpitt/my-repo/issues/7/comments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 444555666,
      "user": { "login": "cgpitt" },
      "body": "I can reproduce this consistently.",
      "created_at": "2026-08-18T10:00:00Z",
      "updated_at": "2026-08-18T10:00:00Z"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or issue not found |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/notifications

Returns GitHub notifications for the authenticated user.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `all` | boolean | `false` | Include read notifications |
| `participating` | boolean | `false` | Only show notifications where you are participating |

```bash
curl -s "https://your-domain.com/api/1/github/notifications?all=false" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "1234567890",
      "reason": "mention",
      "unread": true,
      "subject": {
        "title": "Add new feature",
        "type": "PullRequest",
        "url": "https://api.github.com/repos/cgpitt/my-repo/pulls/42"
      },
      "repository": {
        "full_name": "cgpitt/my-repo"
      },
      "updated_at": "2026-08-19T12:00:00Z"
    }
  ],
  "meta": {
    "total_count": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or GitHub not connected |
| 429 | GitHub rate limit exceeded |

---

## GET /api/{account}/github/notifications/{thread_id}

Returns a specific notification thread.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `thread_id` | string | Notification thread ID |

```bash
curl -s "https://your-domain.com/api/1/github/notifications/1234567890" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as a single item from [List notifications](#get-apiaccount-github-notifications).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, GitHub not connected, or notification not found |
| 429 | GitHub rate limit exceeded |
