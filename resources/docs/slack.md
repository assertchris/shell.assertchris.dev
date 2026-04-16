# Slack

All MCP tools require authentication. See [Authentication](/docs/authentication) for details.

Every request should include these headers:

```
Authorization: Bearer YOUR_API_TOKEN
Accept: application/json
```

Slack must be connected via OAuth in the admin panel (Accounts → Edit → Slack section). Enable the **Slack** toggle and click **Connect Slack Account** to complete the OAuth flow. The connected workspace name and team ID are stored with the account.

---

## tool: list_slack_channels

Returns all Slack channels visible to the connected account (public and private channels the user is a member of).

### Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `account_id` | integer | yes | Account ID with a connected Slack workspace |
| `cursor` | string | no | Pagination cursor from a previous response |

### Example MCP Request

```json
{
  "tool": "list_slack_channels",
  "parameters": {
    "account_id": 1,
    "cursor": null
  }
}
```

### Success Response

```json
[
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
  }
]
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 503 | Slack API unavailable |

---

## tool: read_slack_messages

Returns messages from a specific Slack channel.

### Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `account_id` | integer | yes | Account ID with a connected Slack workspace |
| `channel_id` | string | yes | Slack channel ID (e.g. `C012AB3CD`) |
| `limit` | integer | no | Number of messages to return (default: 50) |
| `cursor` | string | no | Pagination cursor from a previous response |

### Example MCP Request

```json
{
  "tool": "read_slack_messages",
  "parameters": {
    "account_id": 1,
    "channel_id": "C012AB3CD",
    "limit": 20,
    "cursor": null
  }
}
```

### Success Response

```json
{
  "messages": [
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
  "next_cursor": "dXNlcjpVMDYxNTU3MjAw"
}
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 503 | Slack API unavailable |

---

## tool: send_slack_message

Sends a message to a Slack channel on behalf of the connected account.

### Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `account_id` | integer | yes | Account ID with a connected Slack workspace |
| `channel_id` | string | yes | Slack channel ID (e.g. `C012AB3CD`) |
| `text` | string | yes | Message text to send |

### Example MCP Request

```json
{
  "tool": "send_slack_message",
  "parameters": {
    "account_id": 1,
    "channel_id": "C012AB3CD",
    "text": "Deploying v1.2.3 to production now."
  }
}
```

### Success Response

```
Message sent (ts: 1713200200.000300)
```

### Error Responses

| Status | Reason |
|--------|--------|
| 401 | Unauthenticated |
| 404 | Account not found, does not belong to you, or Slack is not connected |
| 503 | Slack API unavailable |
