# Authentication

This API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens for authentication. All API endpoints require a valid token.

## Creating a Token

Tokens are created through the admin panel at `/admin/access-tokens`. After creating a token, you will be shown the plain-text value once — copy it immediately, as it cannot be retrieved later.

## Using Your Token

Include the token in the `Authorization` header of every request:

```
Authorization: Bearer YOUR_API_TOKEN
```

You should also include the `Accept: application/json` header to ensure JSON responses.

## Example Request

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

### Unauthorized Response (401)

If the token is missing or invalid, you will receive a `401` response:

```json
{
  "message": "Unauthenticated."
}
```

## Notes

- Tokens may have an expiry date set during creation. Expired tokens will return a `401` response.
- Tokens can be revoked at any time through the admin panel.
- Always include `Accept: application/json` to receive proper JSON error responses.
