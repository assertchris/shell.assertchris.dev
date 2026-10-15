# Laravel Cloud

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/vnd.api+json
```

A Laravel Cloud API token must be added via the admin panel (Accounts → Edit → Laravel Cloud section). Enable the **Laravel Cloud** toggle and paste your API token. To generate a token, log in to [cloud.laravel.com](https://cloud.laravel.com), open your organisation settings, and create a new API token under **API Tokens**.

All Laravel Cloud endpoints are prefixed with `/api/{account}/laravel-cloud/` where `{account}` is your account ID.

## Special Behaviour

**Commands are asynchronous.** `POST .../commands` returns immediately with `status: pending`. Poll `GET /api/{account}/laravel-cloud/commands/{id}` until `status` becomes `command.success` or `command.failure`. The HTTP status is always `200` — read `exit_code` for the outcome (`0` = success, `1` = failure, `null` = still running).

**Log endpoints require `from` and `to` query params.** Both `GET .../environments/{environment}/logs` and `GET .../deployments/{deployment}/logs` require `from` and `to` as ISO 8601 datetimes (e.g. `2026-10-15T00:00:00Z`). Omitting either returns a `422`.

**Purge cache accepts exactly one target.** The `POST .../environments/{environment}/purge-cache` body must contain exactly one of `path`, `prefix`, or `tag`. Combining any two of these fields returns a `422`.

**Metrics accept an optional period.** Metrics endpoints accept an optional `?period=` query param: `6h`, `24h` (default), `3d`, `7d`, or `30d`.

## Global Errors

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated — your shell API token is missing or invalid |
| 403 | The account does not belong to you |
| 404 | Account not found, Laravel Cloud is not connected, or the requested resource does not exist |
| 429 | Laravel Cloud rate limit exceeded — check the `Retry-After` header |
| 502 | Upstream Laravel Cloud API error |

---

## GET /api/{account}/laravel-cloud/applications

Returns all applications in the Laravel Cloud account. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/applications" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "app-a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "name": "My App",
      "slug": "my-app",
      "region": "us-east-1",
      "root_directory": "/",
      "slack_channel": "#deployments",
      "avatar_url": "https://cloud.laravel.com/avatars/my-app.png",
      "framework": "laravel",
      "language": "php",
      "created_at": "2026-01-15T09:00:00Z",
      "repository": {
        "full_name": "cgpitt/my-app",
        "default_branch": "main"
      }
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Laravel Cloud not connected |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/applications/{application}

Returns a single application. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `application` | string | Application ID (prefixed UUID, e.g. `app-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/applications/app-a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {
    "id": "app-a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "My App",
    "slug": "my-app",
    "region": "us-east-1",
    "root_directory": "/",
    "slack_channel": "#deployments",
    "avatar_url": "https://cloud.laravel.com/avatars/my-app.png",
    "framework": "laravel",
    "language": "php",
    "created_at": "2026-01-15T09:00:00Z",
    "repository": {
      "full_name": "cgpitt/my-app",
      "default_branch": "main"
    }
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or application not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/applications/{application}/environments

Returns all environments for an application. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `application` | string | Application ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/applications/app-a1b2c3d4-e5f6-7890-abcd-ef1234567890/environments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "env-b2c3d4e5-f6a7-8901-bcde-f01234567891",
      "name": "production",
      "slug": "my-app",
      "status": "running",
      "color": "green",
      "vanity_domain": "my-app.laravel.cloud",
      "framework": "laravel",
      "language": "php",
      "php_major_version": "8.3",
      "serving": true,
      "uses_octane": false,
      "uses_hibernation": false,
      "timeout": 30,
      "shutdown_timeout": 5,
      "uses_push_to_deploy": true,
      "uses_deploy_hook": false,
      "network_settings": {},
      "created_at": "2026-01-15T09:05:00Z"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or application not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/environments/{environment}

Returns a single environment. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID (prefixed UUID, e.g. `env-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as a single item from [List environments](#get-apiaccount-laravel-cloud-applications-application-environments).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/environments/{environment}/logs

Returns log entries for an environment. Not cached. `from` and `to` are required.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |
| `from` | string | Start of log window (ISO 8601, required) |
| `to` | string | End of log window (ISO 8601, required) |
| `type` | string | Log type filter (optional) |
| `query` | string | Full-text search string (optional) |
| `cursor` | string | Pagination cursor (optional) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/logs?from=2026-10-15T00:00:00Z&to=2026-10-15T01:00:00Z" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "message": "GET /api/health 200 12ms",
      "level": "info",
      "type": "access",
      "logged_at": "2026-10-15T00:03:17Z",
      "data": {}
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 422 | `from` or `to` is missing |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/environments/{environment}/metrics

Returns resource metrics for an environment. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |
| `period` | string | One of `6h`, `24h` (default), `3d`, `7d`, `30d` (optional) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/metrics?period=24h" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {
    "cpu_usage": {
      "labels": ["2026-10-15T00:00:00Z", "2026-10-15T01:00:00Z"],
      "data": [
        { "x": "2026-10-15T00:00:00Z", "y": 12.4 },
        { "x": "2026-10-15T01:00:00Z", "y": 14.1 }
      ],
      "average": 13.25,
      "min": 12.4,
      "max": 14.1,
      "total": null
    },
    "memory_usage": { "labels": [], "data": [], "average": 256.0, "min": 240.0, "max": 270.0, "total": null },
    "http_response_count": { "labels": [], "data": [], "average": null, "min": null, "max": null, "total": 8412 },
    "replica_count": { "labels": [], "data": [], "average": 2.0, "min": 1, "max": 3, "total": null },
    "web_workers_count": { "labels": [], "data": [], "average": 4.0, "min": 4, "max": 4, "total": null }
  },
  "meta": {
    "period": "24h",
    "available_periods": ["6h", "24h", "3d", "7d", "30d"]
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/environments/{environment}/deployments

Returns deployments for an environment. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/deployments" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "depl-c3d4e5f6-a7b8-9012-cdef-012345678902",
      "slug": "42",
      "status": "finished",
      "branch_name": "main",
      "commit_hash": "abc1234",
      "commit_message": "Fix rate limiting middleware",
      "commit_author": "cgpitt",
      "failure_reason": null,
      "php_major_version": "8.3",
      "build_command": "composer install --no-dev",
      "node_version": "20",
      "uses_octane": false,
      "uses_hibernation": false,
      "started_at": "2026-10-15T08:00:00Z",
      "finished_at": "2026-10-15T08:03:42Z"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/environments/{environment}/instances

Returns running instances for an environment. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/instances" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "inst-d4e5f6a7-b8c9-0123-defa-123456789013",
      "name": "web-1",
      "status": "running",
      "region": "us-east-1",
      "cpu": 0.12,
      "memory": 256,
      "created_at": "2026-10-14T20:00:00Z"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/environments/{environment}/commands

Returns recent commands run against an environment. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/commands" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "cmd-e5f6a7b8-c9d0-1234-efab-234567890124",
      "command": "php artisan cache:clear",
      "output": "Application cache cleared successfully.",
      "status": "command.success",
      "exit_code": 0,
      "failure_reason": null,
      "started_at": "2026-10-15T07:55:00Z",
      "finished_at": "2026-10-15T07:55:02Z",
      "created_at": "2026-10-15T07:54:59Z"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/environments/{environment}/commands

Runs an Artisan command on the environment. Returns immediately with `status: pending`. Poll [GET commands/{id}](#get-apiaccount-laravel-cloud-commands-command) until the command completes. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

**Request Body**

| Field | Type | Description |
|-------|------|-------------|
| `command` | string | The Artisan command to run (e.g. `php artisan cache:clear`) |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/commands" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json" \
  -H "Content-Type: application/json" \
  -d '{"command": "php artisan cache:clear"}'
```

### Success Response (200)

```json
{
  "data": {
    "id": "cmd-e5f6a7b8-c9d0-1234-efab-234567890125",
    "command": "php artisan cache:clear",
    "output": null,
    "status": "pending",
    "exit_code": null,
    "failure_reason": null,
    "started_at": null,
    "finished_at": null,
    "created_at": "2026-10-15T09:00:00Z"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 422 | `command` field is missing |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/environments/{environment}/purge-cache

Purges the edge cache for an environment. Accepts exactly one of `path`, `prefix`, or `tag`. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

**Request Body** (exactly one field required)

| Field | Type | Description |
|-------|------|-------------|
| `path` | string | Purge a specific URL path (e.g. `/about`) |
| `prefix` | string | Purge all paths under a prefix (e.g. `/blog`) |
| `tag` | string | Purge all responses tagged with this cache tag |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/purge-cache" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json" \
  -H "Content-Type: application/json" \
  -d '{"tag": "products"}'
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 422 | Body must contain exactly one of `path`, `prefix`, or `tag` |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/environments/{environment}/start

Starts a stopped environment. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/start" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/environments/{environment}/stop

Stops a running environment. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `environment` | string | Environment ID |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/environments/env-b2c3d4e5-f6a7-8901-bcde-f01234567891/stop" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or environment not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/deployments/{deployment}

Returns a single deployment. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `deployment` | string | Deployment ID (prefixed UUID, e.g. `depl-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/deployments/depl-c3d4e5f6-a7b8-9012-cdef-012345678902" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as a single item from [List deployments](#get-apiaccount-laravel-cloud-environments-environment-deployments).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or deployment not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/deployments/{deployment}/logs

Returns build logs for a deployment. Not cached. `from` and `to` are required.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `deployment` | string | Deployment ID |
| `from` | string | Start of log window (ISO 8601, required) |
| `to` | string | End of log window (ISO 8601, required) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/deployments/depl-c3d4e5f6-a7b8-9012-cdef-012345678902/logs?from=2026-10-15T08:00:00Z&to=2026-10-15T08:10:00Z" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "message": "Installing dependencies from lock file...",
      "level": "info",
      "type": "build",
      "logged_at": "2026-10-15T08:00:05Z",
      "data": {}
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or deployment not found |
| 422 | `from` or `to` is missing |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/instances/{instance}

Returns a single instance. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `instance` | string | Instance ID (prefixed UUID, e.g. `inst-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/instances/inst-d4e5f6a7-b8c9-0123-defa-123456789013" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as a single item from [List instances](#get-apiaccount-laravel-cloud-environments-environment-instances).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or instance not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/instances/{instance}/failed-jobs

Returns failed queue jobs on an instance. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `instance` | string | Instance ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/instances/inst-d4e5f6a7-b8c9-0123-defa-123456789013/failed-jobs" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "name": "App\\Jobs\\ProcessPayment",
      "queue": "payments",
      "failed_at": "2026-10-15T07:30:00Z",
      "started_at": "2026-10-15T07:29:58Z",
      "attempts": 3,
      "exception": "Illuminate\\Database\\QueryException: Connection refused",
      "retried_at": null,
      "retry_reserved_until": null
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or instance not found |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/instances/{instance}/failed-jobs/{job}/retry

Retries a specific failed job on an instance. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `instance` | string | Instance ID |
| `job` | string | Failed job ID |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/instances/inst-d4e5f6a7-b8c9-0123-defa-123456789013/failed-jobs/9d4f2a1b-3c5e-4d6f-8a7b-0c1d2e3f4a5b/retry" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, instance not found, or job not found |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/instances/{instance}/pause

Pauses queue workers on an instance. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `instance` | string | Instance ID |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/instances/inst-d4e5f6a7-b8c9-0123-defa-123456789013/pause" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or instance not found |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/instances/{instance}/resume

Resumes queue workers on a paused instance. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `instance` | string | Instance ID |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/instances/inst-d4e5f6a7-b8c9-0123-defa-123456789013/resume" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or instance not found |
| 502 | Upstream Cloud API error |

---

## POST /api/{account}/laravel-cloud/instances/{instance}/purge

Purges the queue on an instance, discarding all pending jobs. Not cached.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `instance` | string | Instance ID |

```bash
curl -s -X POST "https://your-domain.com/api/1/laravel-cloud/instances/inst-d4e5f6a7-b8c9-0123-defa-123456789013/purge" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": {}
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or instance not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/commands/{command}

Returns a single command. Use this to poll for completion after [running a command](#post-apiaccount-laravel-cloud-environments-environment-commands). Not cached.

Poll until `status` is `command.success` or `command.failure`. The HTTP status is always `200` — read `exit_code` for the outcome: `0` = success, `1` = failure, `null` = still running.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `command` | string | Command ID (prefixed UUID, e.g. `cmd-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/commands/cmd-e5f6a7b8-c9d0-1234-efab-234567890125" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200) — still running

```json
{
  "data": {
    "id": "cmd-e5f6a7b8-c9d0-1234-efab-234567890125",
    "command": "php artisan cache:clear",
    "output": null,
    "status": "pending",
    "exit_code": null,
    "failure_reason": null,
    "started_at": "2026-10-15T09:00:01Z",
    "finished_at": null,
    "created_at": "2026-10-15T09:00:00Z"
  }
}
```

### Success Response (200) — completed

```json
{
  "data": {
    "id": "cmd-e5f6a7b8-c9d0-1234-efab-234567890125",
    "command": "php artisan cache:clear",
    "output": "Application cache cleared successfully.",
    "status": "command.success",
    "exit_code": 0,
    "failure_reason": null,
    "started_at": "2026-10-15T09:00:01Z",
    "finished_at": "2026-10-15T09:00:03Z",
    "created_at": "2026-10-15T09:00:00Z"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or command not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/databases

Returns all managed database clusters. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/databases" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "db-f6a7b8c9-d0e1-2345-fabc-345678901235",
      "name": "my-app-db",
      "status": "available",
      "engine": "mysql",
      "engine_version": "8.0",
      "region": "us-east-1",
      "created_at": "2026-01-15T09:10:00Z"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Laravel Cloud not connected |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/databases/{database}

Returns a single database cluster. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `database` | string | Database ID (prefixed UUID, e.g. `db-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/databases/db-f6a7b8c9-d0e1-2345-fabc-345678901235" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as a single item from [List databases](#get-apiaccount-laravel-cloud-databases).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or database not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/databases/{database}/metrics

Returns resource metrics for a database cluster. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `database` | string | Database ID |
| `period` | string | One of `6h`, `24h` (default), `3d`, `7d`, `30d` (optional) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/databases/db-f6a7b8c9-d0e1-2345-fabc-345678901235/metrics?period=24h" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as [Environment metrics](#get-apiaccount-laravel-cloud-environments-environment-metrics).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or database not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/caches

Returns all managed cache clusters. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/caches" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "cache-a7b8c9d0-e1f2-3456-abcd-456789012346",
      "name": "my-app-cache",
      "status": "available",
      "engine": "redis",
      "engine_version": "7.0",
      "region": "us-east-1",
      "created_at": "2026-01-15T09:15:00Z"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found or Laravel Cloud not connected |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/caches/{cache}

Returns a single cache cluster. Cached for 5 minutes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `cache` | string | Cache ID (prefixed UUID, e.g. `cache-{uuid}`) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/caches/cache-a7b8c9d0-e1f2-3456-abcd-456789012346" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as a single item from [List caches](#get-apiaccount-laravel-cloud-caches).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or cache not found |
| 502 | Upstream Cloud API error |

---

## GET /api/{account}/laravel-cloud/caches/{cache}/metrics

Returns resource metrics for a cache cluster. Cached for 60 seconds.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `cache` | string | Cache ID |
| `period` | string | One of `6h`, `24h` (default), `3d`, `7d`, `30d` (optional) |

```bash
curl -s "https://your-domain.com/api/1/laravel-cloud/caches/cache-a7b8c9d0-e1f2-3456-abcd-456789012346/metrics?period=24h" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/vnd.api+json"
```

### Success Response (200)

Same shape as [Environment metrics](#get-apiaccount-laravel-cloud-environments-environment-metrics).

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, Laravel Cloud not connected, or cache not found |
| 502 | Upstream Cloud API error |
