# Mailboxes

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

---

## GET /api/status

Health check endpoint.

```bash
curl -s https://your-domain.com/api/status \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "status": "ok"
}
```

---

## GET /api/mailboxes

Returns all active mailboxes for the authenticated user.

```bash
curl -s https://your-domain.com/api/mailboxes \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Work Email"
    },
    {
      "id": 2,
      "name": "Personal Email"
    }
  ]
}
```

---

## GET /api/mailboxes/{mailbox}/folders

Returns IMAP folders for a mailbox.

| Parameter | Type | Description |
|-----------|------|-------------|
| `mailbox` | integer | Mailbox ID |

```bash
curl -s https://your-domain.com/api/mailboxes/1/folders \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "path": "INBOX",
      "name": "INBOX"
    },
    {
      "path": "INBOX.Sent",
      "name": "Sent"
    },
    {
      "path": "INBOX.Drafts",
      "name": "Drafts"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 404 | Mailbox not found or does not belong to you |
| 503 | Unable to connect to mailbox (IMAP failure) |

---

## GET /api/mailboxes/{mailbox}/folders/{folder}/messages

Returns paginated message summaries for a folder.

| Parameter | Type | Description |
|-----------|------|-------------|
| `mailbox` | integer | Mailbox ID |
| `folder` | string | Folder path (e.g. `INBOX`) |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Results per page (default: 15, max: 50) |

```bash
curl -s "https://your-domain.com/api/mailboxes/1/folders/INBOX/messages?page=1&per_page=10" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "uid": 42,
      "subject": "Meeting tomorrow",
      "from": {
        "address": "alice@example.com",
        "name": "Alice Smith"
      },
      "to": [
        {
          "address": "you@example.com",
          "name": "You"
        }
      ],
      "date": "2025-01-15T10:30:00+00:00",
      "has_attachments": false
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 42
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 404 | Mailbox not found, or folder not found |
| 503 | Unable to connect to mailbox (IMAP failure) |

---

## GET /api/mailboxes/{mailbox}/folders/{folder}/messages/{uid}

Returns a single message with full body content.

| Parameter | Type | Description |
|-----------|------|-------------|
| `mailbox` | integer | Mailbox ID |
| `folder` | string | Folder path (e.g. `INBOX`) |
| `uid` | integer | Message UID |

```bash
curl -s https://your-domain.com/api/mailboxes/1/folders/INBOX/messages/42 \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": {
    "uid": 42,
    "subject": "Meeting tomorrow",
    "from": {
      "address": "alice@example.com",
      "name": "Alice Smith"
    },
    "to": [
      {
        "address": "you@example.com",
        "name": "You"
      }
    ],
    "date": "2025-01-15T10:30:00+00:00",
    "has_attachments": false,
    "cc": [],
    "text": "Hi, just a reminder about our meeting tomorrow at 10am.",
    "html": "<p>Hi, just a reminder about our meeting tomorrow at 10am.</p>",
    "attachment_count": 0
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 404 | Mailbox not found, folder not found, or message not found |
| 503 | Unable to connect to mailbox (IMAP failure) |
