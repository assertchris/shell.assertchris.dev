# Shell

A read-only proxy between personal assistants and external services.

## Getting Started

```bash
composer setup
php artisan migrate
php artisan db:seed
```

This creates a default admin user you can use to log in at `/admin`:

- **Email**: `me@example.com`
- **Password**: `password123`

> Seeders are never run automatically in production. The `db:seed` command must be run manually.
