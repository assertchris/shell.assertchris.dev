# Shell

A self-hosted API proxy that lets AI assistants (like Claude via MCP, or any HTTP client) interact with your personal services using a single authenticated endpoint.

Instead of giving your AI assistant direct access to every service's API token, Shell holds your credentials server-side and exposes a unified, permission-scoped REST API. You connect once, control access per-token.

## Supported Services

| Service | What you can do |
|---|---|
| **GitHub** | Repos, pull requests, issues, notifications, reviews |
| **Jira** | Projects, issues, sprints, boards, worklogs, comments |
| **Google Calendar** | List and create calendar events |
| **Mail (IMAP)** | Folders, messages, attachments |
| **Slack** | Channels, messages, users, DMs |
| **Laravel Cloud** | Applications, environments, deployments, databases, caches, queues, commands |

## How it works

1. You deploy Shell and log in to the admin panel at `/admin`
2. You create an **Account** (one per person) and connect services via OAuth or API token
3. You generate a **Sanctum API token** for that account
4. Your AI assistant (or any HTTP client) calls Shell's API using that token
5. Shell proxies requests to the connected services on your behalf

All API endpoints require `Authorization: Bearer <token>` and are scoped to the account the token belongs to.

## Getting Started

```bash
composer setup
php artisan migrate
php artisan db:seed
```

This creates a default admin user:

- **Email**: `me@example.com`
- **Password**: `password123`

Log in at `/admin`, change your credentials, and connect your services.

> Seeders are never run automatically in production. The `db:seed` command must be run manually.

## Configuration

Copy `.env.example` to `.env` and configure:

```env
APP_URL=https://your-domain.com

# Google OAuth (for Calendar)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

# Slack OAuth
SLACK_CLIENT_ID=
SLACK_CLIENT_SECRET=
SLACK_REDIRECT_URI=

# Optional: Cloudflare security rule sync
CF_API_TOKEN=
CF_ZONE_ID=
CF_HOSTNAMES=your-domain.com
```

## API

All routes are under `/api` and require a Bearer token.

```
GET  /api/status
GET  /api/accounts
GET  /api/{account}/github/repos
GET  /api/{account}/jira/issues/{key}
GET  /api/{account}/calendar
GET  /api/{account}/mailbox
GET  /api/{account}/laravel-cloud/applications
...
```

Full route list is in [`routes/api.php`](routes/api.php).

## Permissions

Tokens can be scoped with fine-grained permissions (e.g. `google_calendar_read`, `google_calendar_write`, `slack_read`, `slack_write`). Manage these in the admin panel under each account's token settings.

## Running Tests

```bash
php artisan test --compact
```

## Tech Stack

- [Laravel 12](https://laravel.com)
- [Filament 5](https://filamentphp.com) — admin panel
- [Livewire 4](https://livewire.laravel.com)
- [Pest 5](https://pestphp.com) — test suite

## License

MIT — see [LICENSE.md](LICENSE.md).
