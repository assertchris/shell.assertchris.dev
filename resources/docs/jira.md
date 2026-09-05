# Jira

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

**Cloud only.** Jira Server and Data Center use a different authentication model and are not supported.

---

## Prerequisites

1. Log in to your Atlassian account at [id.atlassian.com](https://id.atlassian.com).
2. Go to **Security** → **API tokens** → **Create API token**.
3. Copy the token immediately — it is not shown again.

---

## Setup

Add credentials via the Filament admin panel: **Accounts → Edit → Jira section**.

1. Enable the **Jira** toggle.
2. Enter your **Base URL** — e.g. `https://yourcompany.atlassian.net`.
3. Enter your **Email** — the address tied to your Atlassian account.
4. Paste your **API token**.

All three fields are required when the toggle is on. The token is stored encrypted. To update the token later, paste the new value and leave other fields blank to preserve their current values.

---

## Pagination

Endpoints that return lists of issues, sprints, or board sprints accept `startAt` and `maxResults` query parameters and include pagination metadata in the response:

```json
{
  "data": [...],
  "meta": {
    "start_at": 0,
    "max_results": 50,
    "total": 243
  }
}
```

Other list endpoints (projects, filters, dashboards) return a `meta.total_count` field equal to the number of items returned.

---

## Error Codes

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated Shell request, or Jira credentials are invalid |
| 403 | Authenticated but the API token does not have permission for this operation |
| 404 | Account not found, does not belong to you, Jira is not connected, or the requested resource does not exist |
| 422 | Validation failed — check the `errors` field for details |
| 429 | Jira rate limit exceeded — check the `Retry-After` response header |

All endpoints are prefixed with `/api/{account}/jira/` where `{account}` is your account ID.

---

## JQL

The `/issues/search` endpoint accepts a `jql` query string. JQL (Jira Query Language) is Jira's structured search syntax.

Examples:

```
project = MYAPP AND status = "In Progress"
assignee = currentUser() ORDER BY updated DESC
priority = High AND created >= -7d
text ~ "login" AND project in (MYAPP, PLATFORM)
sprint in openSprints() AND assignee = currentUser()
issuetype = Bug AND status != Done ORDER BY priority ASC
```

Pass `startAt` and `maxResults` to page through results.

---

## ADF Comment Format

Comments use Atlassian Document Format (ADF). Pass the full ADF document as the `body` field. Shell validates that `type`, `version`, and `content` are present but does not transform the document.

Minimal example:

```json
{
  "body": {
    "type": "doc",
    "version": 1,
    "content": [
      {
        "type": "paragraph",
        "content": [
          {
            "type": "text",
            "text": "This is a comment."
          }
        ]
      }
    ]
  }
}
```

For the full ADF specification see [Atlassian Document Format — developer.atlassian.com](https://developer.atlassian.com/cloud/jira/platform/apis/document/structure/).

---

## GET /api/{account}/jira/projects

Returns Jira projects accessible to the authenticated user.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/jira/projects" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10001",
      "key": "MYAPP",
      "name": "My Application",
      "description": "Main product repository",
      "projectTypeKey": "software",
      "style": "next-gen",
      "isPrivate": false
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
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded — check `Retry-After` header |

---

## GET /api/{account}/jira/projects/{key}

Returns details for a specific project.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Project key (e.g. `MYAPP`) |

```bash
curl -s "https://your-domain.com/api/1/jira/projects/MYAPP" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "id": "10001",
    "key": "MYAPP",
    "name": "My Application",
    "description": "Main product repository",
    "projectTypeKey": "software",
    "style": "next-gen",
    "isPrivate": false
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or project not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/projects/{key}/components

Returns the components defined for a project.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Project key |

```bash
curl -s "https://your-domain.com/api/1/jira/projects/MYAPP/components" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    { "id": "10050", "name": "Backend" },
    { "id": "10051", "name": "Frontend" }
  ],
  "meta": {
    "total_count": 2
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or project not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/projects/{key}/versions

Returns fix versions for a project.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Project key |

```bash
curl -s "https://your-domain.com/api/1/jira/projects/MYAPP/versions" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    { "id": "10100", "name": "v1.0.0", "released": true },
    { "id": "10101", "name": "v1.1.0", "released": false }
  ],
  "meta": {
    "total_count": 2
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or project not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/issues/search

Searches issues using JQL. See [JQL](#jql) for syntax examples.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `jql` | string | — | JQL query (required) |
| `startAt` | integer | `0` | Offset for pagination |
| `maxResults` | integer | `50` | Results per page |
| `fields` | string | — | Comma-separated field list to include |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/search?jql=project+%3D+MYAPP+AND+status+%3D+%22In+Progress%22" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10200",
      "key": "MYAPP-42",
      "summary": "Fix login redirect bug",
      "status": "In Progress",
      "assignee": {
        "accountId": "5b10a2844c20165700ede21g",
        "displayName": "Jane Smith"
      },
      "reporter": {
        "accountId": "5b10ac8d82e165700ede21h",
        "displayName": "Chris Pitt"
      },
      "priority": "High",
      "issuetype": "Bug",
      "project": {
        "key": "MYAPP",
        "name": "My Application"
      },
      "description": {
        "type": "doc",
        "version": 1,
        "content": [{ "type": "paragraph", "content": [{ "type": "text", "text": "Users are redirected to /home instead of the original destination after login." }] }]
      },
      "created": "2026-08-01T09:00:00.000+0000",
      "updated": "2026-08-20T14:30:00.000+0000",
      "labels": ["auth", "regression"],
      "fixVersions": ["v1.1.0"],
      "components": ["Backend"],
      "parent": null,
      "subtasks": []
    }
  ],
  "meta": {
    "start_at": 0,
    "max_results": 50,
    "total": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 422 | `jql` parameter is required |
| 429 | Jira rate limit exceeded — check `Retry-After` header |

---

## GET /api/{account}/jira/issues/{key}

Returns a specific issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key (e.g. `MYAPP-42`) |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/MYAPP-42" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as a single item from [Search issues](#get-apiaccount-jira-issues-search).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/issues/{key}/comments

Returns comments on an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/MYAPP-42/comments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10300",
      "author": {
        "accountId": "5b10ac8d82e165700ede21h",
        "displayName": "Chris Pitt"
      },
      "body": { "type": "doc", "version": 1, "content": [...] },
      "created": "2026-08-20T10:00:00.000+0000",
      "updated": "2026-08-20T10:00:00.000+0000"
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
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/issues/{key}/worklogs

Returns work log entries for an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/MYAPP-42/worklogs" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10400",
      "author": {
        "accountId": "5b10ac8d82e165700ede21h",
        "displayName": "Chris Pitt"
      },
      "timeSpentSeconds": 3600,
      "started": "2026-08-20T09:00:00.000+0000",
      "comment": "Investigated root cause"
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
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/issues/{key}/transitions

Returns available workflow transitions for an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/MYAPP-42/transitions" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "21",
      "name": "In Progress",
      "to": {
        "name": "In Progress",
        "statusCategory": "In Progress"
      }
    },
    {
      "id": "31",
      "name": "Done",
      "to": {
        "name": "Done",
        "statusCategory": "Done"
      }
    }
  ],
  "meta": {
    "total_count": 2
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/issues/{key}/changelog

Returns the change history for an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/MYAPP-42/changelog" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10500",
      "author": {
        "accountId": "5b10ac8d82e165700ede21h",
        "displayName": "Chris Pitt"
      },
      "created": "2026-08-20T14:30:00.000+0000",
      "items": [
        {
          "field": "status",
          "from": "10000",
          "to": "10001",
          "fromString": "To Do",
          "toString": "In Progress"
        }
      ]
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
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/issues/{key}/attachments

Returns attachment metadata for an issue. Download URLs are included but attachment upload is not supported.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |

```bash
curl -s "https://your-domain.com/api/1/jira/issues/MYAPP-42/attachments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10600",
      "filename": "screenshot.png",
      "mimeType": "image/png",
      "size": 84321,
      "content": "https://yourcompany.atlassian.net/rest/api/3/attachment/content/10600",
      "thumbnail": "https://yourcompany.atlassian.net/rest/api/3/attachment/thumbnail/10600"
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
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## POST /api/{account}/jira/issues

Creates a new issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `fields` | object | Issue fields (required) |
| `fields.project.key` | string | Project key (required) |
| `fields.issuetype.name` | string | Issue type name (required) |
| `fields.summary` | string | Issue summary (required) |
| `fields.description` | object\|null | Issue description as an [Atlassian Document Format (ADF)](https://developer.atlassian.com/cloud/jira/platform/apis/document/structure/) object (optional) |

> **ADF required.** Jira Cloud REST API v3 stores `description` as a structured ADF document, not a plain string. Pass `null` to leave the description empty, or provide a well-formed ADF object. A minimal single-paragraph ADF body looks like: `{"type":"doc","version":1,"content":[{"type":"paragraph","content":[{"type":"text","text":"Your text here"}]}]}`.

```bash
curl -s -X POST "https://your-domain.com/api/1/jira/issues" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "fields": {
      "project": { "key": "MYAPP" },
      "issuetype": { "name": "Bug" },
      "summary": "Login button does not respond on iOS"
    }
  }'
```

### Success Response (201)

```json
{
  "data": {
    "id": "10700",
    "key": "MYAPP-43",
    "summary": "Login button does not respond on iOS",
    ...
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | API token does not have permission to create issues |
| 404 | Account not found or Jira not connected |
| 422 | Validation failed — `fields`, `fields.project.key`, `fields.issuetype.name`, or `fields.summary` missing |
| 429 | Jira rate limit exceeded |

---

## PUT /api/{account}/jira/issues/{key}

Updates fields on an existing issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `fields` | object | Fields to update (required) |
| `fields.description` | object\|null | Updated description as an [ADF](https://developer.atlassian.com/cloud/jira/platform/apis/document/structure/) object (optional). See POST endpoint for ADF format details. |

```bash
curl -s -X PUT "https://your-domain.com/api/1/jira/issues/MYAPP-42" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ "fields": { "priority": { "name": "Critical" } } }'
```

### Success Response (204)

Empty body.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, or issue not found |
| 422 | `fields` is required |
| 429 | Jira rate limit exceeded |

---

## DELETE /api/{account}/jira/issues/{key}

Deletes an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |

```bash
curl -s -X DELETE "https://your-domain.com/api/1/jira/issues/MYAPP-42" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (204)

Empty body.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## POST /api/{account}/jira/issues/{key}/transitions

Moves an issue to a new workflow status.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `transition_id` | string | Transition ID from [List transitions](#get-apiaccount-jira-issues-key-transitions) (required) |

```bash
curl -s -X POST "https://your-domain.com/api/1/jira/issues/MYAPP-42/transitions" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ "transition_id": "31" }'
```

### Success Response (204)

Empty body.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, or issue not found |
| 422 | `transition_id` is required |
| 429 | Jira rate limit exceeded |

---

## PUT /api/{account}/jira/issues/{key}/assignee

Assigns or unassigns an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `account_id` | string\|null | Atlassian account ID of the assignee; pass `null` to unassign |

```bash
curl -s -X PUT "https://your-domain.com/api/1/jira/issues/MYAPP-42/assignee" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ "account_id": "5b10a2844c20165700ede21g" }'
```

### Success Response (204)

Empty body.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, or issue not found |
| 429 | Jira rate limit exceeded |

---

## POST /api/{account}/jira/issues/{key}/comments

Adds a comment to an issue. The `body` must be a valid ADF document. See [ADF Comment Format](#adf-comment-format).

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `body` | object | ADF document (required); must contain `type`, `version`, and `content` |

```bash
curl -s -X POST "https://your-domain.com/api/1/jira/issues/MYAPP-42/comments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "body": {
      "type": "doc",
      "version": 1,
      "content": [
        {
          "type": "paragraph",
          "content": [{ "type": "text", "text": "Confirmed on iOS 17." }]
        }
      ]
    }
  }'
```

### Success Response (201)

```json
{
  "data": {
    "id": "10300",
    "author": {
      "accountId": "5b10ac8d82e165700ede21h",
      "displayName": "Chris Pitt"
    },
    "body": { "type": "doc", "version": 1, "content": [...] },
    "created": "2026-08-22T08:00:00.000+0000",
    "updated": "2026-08-22T08:00:00.000+0000"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, or issue not found |
| 422 | `body`, `body.type`, `body.version`, or `body.content` missing or invalid |
| 429 | Jira rate limit exceeded |

---

## PUT /api/{account}/jira/issues/{key}/comments/{commentId}

Updates an existing comment.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `commentId` | string | Comment ID |
| `body` | object | Updated ADF document (required) |

```bash
curl -s -X PUT "https://your-domain.com/api/1/jira/issues/MYAPP-42/comments/10300" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "body": {
      "type": "doc",
      "version": 1,
      "content": [
        {
          "type": "paragraph",
          "content": [{ "type": "text", "text": "Confirmed on iOS 17 and 18." }]
        }
      ]
    }
  }'
```

### Success Response (200)

Same shape as a single item from [Add comment](#post-apiaccount-jira-issues-key-comments).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, issue not found, or comment not found |
| 422 | `body`, `body.type`, `body.version`, or `body.content` missing or invalid |
| 429 | Jira rate limit exceeded |

---

## DELETE /api/{account}/jira/issues/{key}/comments/{commentId}

Deletes a comment.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `commentId` | string | Comment ID |

```bash
curl -s -X DELETE "https://your-domain.com/api/1/jira/issues/MYAPP-42/comments/10300" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (204)

Empty body.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, issue not found, or comment not found |
| 429 | Jira rate limit exceeded |

---

## POST /api/{account}/jira/issues/{key}/worklogs

Logs time against an issue.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `time_spent` | string | Time spent in Jira duration format, e.g. `1h 30m` (required) |
| `started` | string | ISO 8601 datetime when work started (required) |
| `comment` | string | Optional description of the work done |

```bash
curl -s -X POST "https://your-domain.com/api/1/jira/issues/MYAPP-42/worklogs" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "time_spent": "2h",
    "started": "2026-08-22T09:00:00.000+0000",
    "comment": "Investigated and fixed root cause"
  }'
```

### Success Response (201)

```json
{
  "data": {
    "id": "10400",
    "author": {
      "accountId": "5b10ac8d82e165700ede21h",
      "displayName": "Chris Pitt"
    },
    "timeSpentSeconds": 7200,
    "started": "2026-08-22T09:00:00.000+0000",
    "comment": "Investigated and fixed root cause"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, or issue not found |
| 422 | `time_spent` or `started` missing |
| 429 | Jira rate limit exceeded |

---

## PUT /api/{account}/jira/issues/{key}/worklogs/{worklogId}

Updates a work log entry.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `worklogId` | string | Worklog ID |
| `time_spent` | string | Updated duration, e.g. `3h` (required) |
| `started` | string | Updated start datetime (required) |
| `comment` | string | Updated description |

```bash
curl -s -X PUT "https://your-domain.com/api/1/jira/issues/MYAPP-42/worklogs/10400" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{ "time_spent": "3h", "started": "2026-08-22T09:00:00.000+0000" }'
```

### Success Response (200)

Same shape as a single item from [Add worklog](#post-apiaccount-jira-issues-key-worklogs).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, issue not found, or worklog not found |
| 422 | `time_spent` or `started` missing |
| 429 | Jira rate limit exceeded |

---

## DELETE /api/{account}/jira/issues/{key}/worklogs/{worklogId}

Deletes a work log entry.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `key` | string | Issue key |
| `worklogId` | string | Worklog ID |

```bash
curl -s -X DELETE "https://your-domain.com/api/1/jira/issues/MYAPP-42/worklogs/10400" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (204)

Empty body.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Insufficient permission |
| 404 | Account not found, Jira not connected, issue not found, or worklog not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/user/issues

Returns issues assigned to the authenticated Jira user, ordered by most recently updated.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |

```bash
curl -s "https://your-domain.com/api/1/jira/user/issues" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-jira-issues-search) including `meta`.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/user/reported

Returns issues reported by the authenticated Jira user, ordered by most recently updated.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |

```bash
curl -s "https://your-domain.com/api/1/jira/user/reported" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-jira-issues-search) including `meta`.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/user/watching

Returns issues watched by the authenticated Jira user, ordered by most recently updated.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |

```bash
curl -s "https://your-domain.com/api/1/jira/user/watching" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-jira-issues-search) including `meta`.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/user/mentions

Returns issues where the authenticated Jira user is mentioned.

**Note:** This endpoint uses the JQL `text ~ currentUser()` operator, which performs a full-text search across all issue fields. Results are approximate — the query may return issues where the user's name appears in other contexts. Some relevant issues may also be missed if the mention is in structured data that Jira does not include in its text index. Expect occasional false positives.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |

```bash
curl -s "https://your-domain.com/api/1/jira/user/mentions" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-jira-issues-search) including `meta`.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/user/activity

Returns issues assigned to the authenticated Jira user that were updated in the last 7 days, ordered by most recently updated.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |

```bash
curl -s "https://your-domain.com/api/1/jira/user/activity" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-jira-issues-search) including `meta`.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/boards

Returns Agile boards accessible to the authenticated user.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/jira/boards" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "MYAPP Sprint Board",
      "type": "scrum",
      "location": {
        "projectKey": "MYAPP",
        "projectName": "My Application"
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
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/boards/{boardId}

Returns details for a specific board.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `boardId` | integer | Board ID |

```bash
curl -s "https://your-domain.com/api/1/jira/boards/1" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as a single item from [List boards](#get-apiaccount-jira-boards).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or board not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/boards/{boardId}/sprints

Returns sprints for a board.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `boardId` | integer | — | Board ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |
| `state` | string | — | Filter by `active`, `closed`, or `future` |

```bash
curl -s "https://your-domain.com/api/1/jira/boards/1/sprints?state=active" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 10,
      "name": "Sprint 4",
      "state": "active",
      "startDate": "2026-08-15T00:00:00.000Z",
      "endDate": "2026-08-29T00:00:00.000Z",
      "goal": "Ship the Jira integration"
    }
  ],
  "meta": {
    "start_at": 0,
    "max_results": 50,
    "total": 1
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or board not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/sprints/{sprintId}

Returns details for a specific sprint.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `sprintId` | integer | Sprint ID |

```bash
curl -s "https://your-domain.com/api/1/jira/sprints/10" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as a single item from [List sprints](#get-apiaccount-jira-boards-boardid-sprints).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or sprint not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/sprints/{sprintId}/issues

Returns issues in a sprint.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `account` | integer | — | Account ID |
| `sprintId` | integer | — | Sprint ID |
| `startAt` | integer | `0` | Pagination offset |
| `maxResults` | integer | `50` | Results per page |

```bash
curl -s "https://your-domain.com/api/1/jira/sprints/10/issues" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as [Search issues](#get-apiaccount-jira-issues-search) including `meta`.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or sprint not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/myself

Returns the authenticated Jira user's profile.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/jira/myself" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "accountId": "5b10ac8d82e165700ede21h",
    "displayName": "Chris Pitt",
    "emailAddress": "chris@example.com",
    "active": true,
    "avatarUrls": {
      "48x48": "https://secure.gravatar.com/avatar/abc?s=48",
      "24x24": "https://secure.gravatar.com/avatar/abc?s=24"
    }
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/filters

Returns the authenticated user's favourite Jira filters.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/jira/filters" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10800",
      "name": "My open bugs",
      "description": "All open bugs assigned to me",
      "jql": "issuetype = Bug AND assignee = currentUser() AND status != Done",
      "owner": {
        "accountId": "5b10ac8d82e165700ede21h",
        "displayName": "Chris Pitt"
      },
      "favourite": true
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
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/filters/{id}

Returns a specific filter.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `id` | integer | Filter ID |

```bash
curl -s "https://your-domain.com/api/1/jira/filters/10800" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

Same shape as a single item from [List filters](#get-apiaccount-jira-filters).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Jira not connected, or filter not found |
| 429 | Jira rate limit exceeded |

---

## GET /api/{account}/jira/dashboards

Returns Jira dashboards accessible to the authenticated user.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/jira/dashboards" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "10900",
      "name": "Team Overview",
      "description": "Sprint progress and open blockers",
      "popularity": 12
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
| 404 | Account not found or Jira not connected |
| 429 | Jira rate limit exceeded |
