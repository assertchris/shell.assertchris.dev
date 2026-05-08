# Calendars

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

Calendar access requires an Account with `google_calendar_used` enabled and connected via the Google OAuth flow in the admin panel. To connect Google Calendar, edit the account in the Filament admin area, enable the **Google Calendar** toggle, and click **Connect Google Account**.

## Permissions

Each account has two fine-grained permission flags that control calendar access:

- `google_calendar_read` — must be enabled to use GET endpoints. If disabled, all GET requests return `403 Forbidden`.
- `google_calendar_write` — must be enabled to use POST endpoints. If disabled, all POST requests return `403 Forbidden`.

Both flags default to **enabled**. They can be toggled per-account in the Filament admin UI under the Google Calendar section.

---

## GET /api/{account}/calendar

Returns a paginated list of upcoming calendar entries from the account's primary Google Calendar.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Results per page (default: 15, max: 50) |

```bash
curl -s "https://your-domain.com/api/1/calendar?page=1&per_page=10" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "abc123xyz",
      "title": "Team Standup",
      "start": "2026-04-11T09:00:00+00:00",
      "end": "2026-04-11T09:30:00+00:00",
      "location": "Google Meet"
    },
    {
      "id": "def456uvw",
      "title": "Product Review",
      "start": "2026-04-11T14:00:00+00:00",
      "end": "2026-04-11T15:00:00+00:00",
      "location": null
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Google Calendar read permission is disabled for this account |
| 404 | Account not found, does not belong to you, or Google Calendar is not connected |
| 503 | Unable to reach Google Calendar API |

---

## GET /api/{account}/calendar/{eventId}

Returns full details for a single calendar entry.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `eventId` | string | Google Calendar event ID |

```bash
curl -s https://your-domain.com/api/1/calendar/abc123xyz \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "id": "abc123xyz",
    "title": "Team Standup",
    "description": "Daily sync with the engineering team.",
    "start": "2026-04-11T09:00:00+00:00",
    "end": "2026-04-11T09:30:00+00:00",
    "location": "Google Meet",
    "attendees": [
      {
        "email": "alice@example.com",
        "name": "Alice Smith",
        "response_status": "accepted"
      },
      {
        "email": "bob@example.com",
        "name": "Bob Jones",
        "response_status": "needsAction"
      }
    ],
    "html_link": "https://www.google.com/calendar/event?eid=..."
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Google Calendar read permission is disabled for this account |
| 404 | Account not found, does not belong to you, Google Calendar is not connected, or event not found |
| 503 | Unable to reach Google Calendar API |

---

## POST /api/{account}/calendar

Creates a new event on the account's primary Google Calendar.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID (route parameter) |
| `summary` | string | Event title (required) |
| `description` | string | Event description (optional) |
| `location` | string | Event location (optional) |
| `start` | object | Event start time (required) |
| `start.dateTime` | string | RFC3339 date-time string, e.g. `2026-04-11T09:00:00+02:00` (required) |
| `start.timeZone` | string | IANA time zone name, e.g. `Africa/Johannesburg` (required) |
| `end` | object | Event end time (required) |
| `end.dateTime` | string | RFC3339 date-time string (required) |
| `end.timeZone` | string | IANA time zone name (required) |

```bash
curl -s -X POST "https://your-domain.com/api/1/calendar" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "summary": "Team Standup",
    "description": "Daily sync with the engineering team.",
    "location": "Google Meet",
    "start": {
      "dateTime": "2026-04-11T09:00:00+02:00",
      "timeZone": "Africa/Johannesburg"
    },
    "end": {
      "dateTime": "2026-04-11T09:30:00+02:00",
      "timeZone": "Africa/Johannesburg"
    }
  }'
```

### Success Response (201)

```json
{
  "data": {
    "id": "abc123xyz",
    "title": "Team Standup",
    "description": "Daily sync with the engineering team.",
    "start": "2026-04-11T09:00:00+02:00",
    "end": "2026-04-11T09:30:00+02:00",
    "location": "Google Meet",
    "attendees": [],
    "html_link": "https://www.google.com/calendar/event?eid=..."
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 403 | Google Calendar write permission is disabled for this account |
| 404 | Account not found, does not belong to you, or Google Calendar is not connected |
| 422 | Validation error — `summary`, `start`, or `end` missing or malformed |
| 503 | Unable to reach Google Calendar API |
