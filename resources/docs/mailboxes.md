# Mailboxes

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

IMAP configuration (host, port, encryption, username, and password) is managed per-account in the admin panel. To set up or update IMAP credentials, edit the account in the Filament admin area and ensure the **Mail** toggle is enabled.

---

## GET /api/{account}/mailbox

Returns IMAP folders for the account.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |

```bash
curl -s https://your-domain.com/api/1/mailbox \
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
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or mail is not enabled |
| 503 | Unable to connect to mailbox (IMAP failure) |

---

## GET /api/{account}/mailbox/{folder}/messages

Returns paginated message summaries for a folder.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `folder` | string | Folder path (e.g. `INBOX`) |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Results per page (default: 15, max: 50) |

```bash
curl -s "https://your-domain.com/api/1/mailbox/INBOX/messages?page=1&per_page=10" \
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
| 401 | Unauthenticated |
| 404 | Account not found, or folder not found |
| 503 | Unable to connect to mailbox (IMAP failure) |

---

## GET /api/{account}/mailbox/{folder}/messages/{uid}

Returns a single message with full body content.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `folder` | string | Folder path (e.g. `INBOX`) |
| `uid` | integer | Message UID |

```bash
curl -s https://your-domain.com/api/1/mailbox/INBOX/messages/42 \
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
    "has_attachments": true,
    "cc": [],
    "text": "Hi, just a reminder about our meeting tomorrow at 10am.",
    "html": "<p>Hi, just a reminder about our meeting tomorrow at 10am.</p>",
    "attachment_count": 2
  }
}
```

The `attachment_count` field tells you how many attachments are available on the message. Use the [List Attachments](#get-apiaccountmailboxfoldermessagesuidattachments) endpoint to retrieve their metadata and the [Download Attachment](#get-apiaccountmailboxfoldermessagesuidattachmentsindex) endpoint to download their contents.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, folder not found, or message not found |
| 503 | Unable to connect to mailbox (IMAP failure) |

---

## GET /api/{account}/mailbox/{folder}/messages/{uid}/attachments

Returns a JSON array of attachment metadata for a message.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `folder` | string | Folder path (e.g. `INBOX`) |
| `uid` | integer | Message UID |

```bash
curl -s https://your-domain.com/api/1/mailbox/INBOX/messages/42/attachments \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "index": 0,
      "filename": "invoice.pdf",
      "content_type": "application/pdf",
      "extension": "pdf",
      "content_id": null
    },
    {
      "index": 1,
      "filename": "logo.png",
      "content_type": "image/png",
      "extension": "png",
      "content_id": "<logo@example.com>"
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, mail is not enabled, folder not found, or message not found |
| 503 | Unable to connect to mailbox (IMAP failure) |

---

## GET /api/{account}/mailbox/{folder}/messages/{uid}/attachments/{index}

Streams a single attachment as a binary download. The response body is the raw file content, not JSON.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `folder` | string | Folder path (e.g. `INBOX`) |
| `uid` | integer | Message UID |
| `index` | integer | 0-based attachment index (from the list attachments response) |

```bash
curl -s https://your-domain.com/api/1/mailbox/INBOX/messages/42/attachments/0 \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -o invoice.pdf
```

### Success Response (200)

The response is a binary stream. The `Content-Type` header matches the attachment's MIME type (e.g. `application/pdf`, `image/png`) and `Content-Disposition: attachment` is set with the original filename.

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, mail is not enabled, folder not found, message not found, or index out of bounds |
| 503 | Unable to connect to mailbox (IMAP failure) |
