# Slack

All endpoints require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

Slack must be connected via OAuth in the admin panel (Accounts → Edit → Slack section). Enable the **Slack** toggle and click **Connect Slack Account** to complete the OAuth flow. The connected workspace name and team ID are stored with the account.

---

## GET /api/slack/{account}/channels

Returns all conversations visible to the connected account: public channels, private channels, direct messages (DMs), and group DMs (MPDMs).

DM conversations have no Slack-assigned name. Their `name` field is normalised to `dm:<user_id>` where `<user_id>` is the Slack user ID of the other party. Group DM names are passed through as-is (e.g. `mpdm-alice--bob--charlie-1`).

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `cursor` | string | Pagination cursor from a previous response (optional) |

```bash
curl -s "https://your-domain.com/api/slack/1/channels" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "C012AB3CD",
      "name": "general",
      "is_private": false,
      "num_members": 42
    },
    {
      "id": "C034EF5GH",
      "name": "engineering",
      "is_private": false,
      "num_members": 12
    },
    {
      "id": "D001ABCDE",
      "name": "dm:U012AB3CD",
      "is_private": true,
      "num_members": 2
    },
    {
      "id": "G001FGHIJ",
      "name": "mpdm-alice--bob--charlie-1",
      "is_private": true,
      "num_members": 3
    }
  ]
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 503 | Slack API unavailable |

---

## GET /api/slack/{account}/channels/{channel}/messages

Returns messages from a specific Slack channel.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `channel` | string | Slack channel ID (e.g. `C012AB3CD`) |
| `limit` | integer | Number of messages to return (default: 50, max: 200) |
| `cursor` | string | Pagination cursor from a previous response (optional) |

```bash
curl -s "https://your-domain.com/api/slack/1/channels/C012AB3CD/messages?limit=20" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "ts": "1713200000.000100",
      "user": "U012AB3CD",
      "text": "Good morning team!"
    },
    {
      "ts": "1713200100.000200",
      "user": "U034EF5GH",
      "text": "Morning! Ready for standup?"
    }
  ],
  "meta": {
    "next_cursor": "dXNlcjpVMDYxNTU3MjAw"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 503 | Slack API unavailable |

---

## POST /api/slack/{account}/channels/{channel}/messages

Sends a message to a Slack channel on behalf of the connected account.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `channel` | string | Slack channel ID (e.g. `C012AB3CD`) |
| `text` | string | Message text to send (required, request body) |

```bash
curl -s -X POST "https://your-domain.com/api/slack/1/channels/C012AB3CD/messages" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"text": "Deploying v1.2.3 to production now."}'
```

### Success Response (200)

```json
{
  "ts": "1713200200.000300"
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 422 | Validation error — `text` field is required |
| 503 | Slack API unavailable |

---

## GET /api/slack/{account}/users

Returns all non-deleted workspace members visible to the connected account.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `cursor` | string | Pagination cursor from a previous response (optional) |

```bash
curl -s "https://your-domain.com/api/slack/1/users" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

### Success Response (200)

```json
{
  "data": [
    {
      "id": "U012AB3CD",
      "name": "alice",
      "real_name": "Alice Smith",
      "is_bot": false
    },
    {
      "id": "U034EF5GH",
      "name": "bob",
      "real_name": "Bob Jones",
      "is_bot": false
    }
  ],
  "meta": {
    "next_cursor": "dXNlcjpVMDYxNTU3MjAw"
  }
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 503 | Slack API unavailable |

---

## POST /api/slack/{account}/conversations/open

Opens or retrieves an existing DM channel with a specific user. Returns the Slack channel ID for the DM. Use the returned `channel_id` with the messages endpoints to read or send messages in the DM.

| Parameter | Type | Description |
|-----------|------|-------------|
| `account` | integer | Account ID |
| `user_id` | string | Slack user ID to open a DM with (required, request body) |

```bash
curl -s -X POST "https://your-domain.com/api/slack/1/conversations/open" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"user_id": "U012AB3CD"}'
```

### Success Response (200)

```json
{
  "channel_id": "D001ABCDE"
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 422 | Validation error — `user_id` field is required |
| 503 | Slack API unavailable |
