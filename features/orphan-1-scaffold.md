# Scaffold Application

- Branch: `feature/orphan-1-scaffold`
- Related Issue: N/A
- **Status**: 🟡 In Progress

## Overview

Shell is a new Laravel 12 application that acts as a read-only proxy between personal assistants and external services (mail, Slack, etc.). It provides a hard boundary -- assistants can read data from connected services but cannot send messages, modify data, or take actions on the user's behalf. Assistants can also push data into a frontend UI for the user to review.

The repo will be open-sourced. This feature sets up the project structure, tooling, and deployment config so future feature work has a solid foundation.

### Background / Current Behavior
- **Current behavior**: Fresh Laravel 12 install with default scaffolding (Sail, Pail, welcome page, PHPUnit, no admin panel)
- **Problem**: Project needs tooling, testing, admin panel, CI, and deployment config before feature work can begin
- **Where it happens**: Entire project
- **Impact**: All future development depends on this scaffold being solid

### Target Outcome
This feature will set up the project scaffold so that developers can:
- Run `composer format` to format code with Pint
- Run `composer refresh-ide-helpers` and `composer refresh-ide-model-docblock` to generate IDE helper files
- Run `composer test` to execute Pest tests
- Access a Filament admin panel at `/admin` with a custom theme
- Push to GitHub and have tests run automatically via CI
- Deploy to Forge via Herd integration

## Requirements

### Functional
- [ ] Unused dependencies (Sail, Pail) are removed
- [ ] IDE helpers installed and configured with output to `meta/`, mirroring assertchris.dev settings
- [ ] Pint formatting works via `composer format` (Laravel preset defaults)
- [ ] Pest replaces PHPUnit; example tests converted and passing
- [ ] Filament 5 installed with admin panel and custom theme
- [ ] GitHub Actions workflow runs tests on push/PR
- [ ] Project has proper open-source identity (LICENSE, README, composer metadata)
- [ ] Forge deployment configured via `herd.yml` (placeholder IDs)

### Non-Functional
- [ ] Frontend stack (Vite, Tailwind, npm) preserved for future UI work
- [ ] Generated meta files are git-ignored
- [ ] CI builds Vite manifest before running tests
- [ ] `.env.example` contains no secrets and has sensible defaults

### Out of Scope
- Actual proxy service integrations (mail, Slack, etc.)
- API route structure and middleware
- Authentication/authorization beyond Filament's built-in user model
- Custom Filament resources or pages

## Implementation Plan

### Phase 1: Remove unused dependencies and files
- `composer remove laravel/sail --dev` and `composer remove laravel/pail --dev`
- Delete `resources/views/welcome.blade.php`
- Update `composer.json` scripts: replace `npm install` with `npm ci` in setup, remove pail from dev, remove `post-create-project-cmd`
- Replace welcome route with JSON response in `routes/web.php`
- Remove `inspire` command from `routes/console.php`
- Clean up `.editorconfig` (remove `[compose.yaml]`), `.gitignore` (remove `/storage/pail`), `phpunit.xml` (remove PULSE/TELESCOPE/NIGHTWATCH env vars)

### Phase 2: Install and configure IDE helpers
- `composer require barryvdh/laravel-ide-helper:^3.0` (in `require`, not `require-dev`)
- Publish config, then mirror every setting from `../assertchris.dev/config/ide-helper.php` (output paths to `meta/`, include_fluent, include_helpers, use_generics_annotations, type_overrides, etc.)
- Create `meta/.gitignore` (`*` and `!.gitignore`)
- Add composer scripts: `clear-caches`, `format`, `refresh-ide-helpers`, `refresh-ide-model-docblock`

### Phase 3: Configure Pint
- Already installed; uses Laravel preset defaults (no `pint.json`)
- `format` script added in Phase 2
- Verify `composer format` works

### Phase 4: Set up Pest
- Remove phpunit, install pestphp/pest, run `pest:install`
- Delete `tests/TestCase.php`, convert example tests to Pest syntax

### Phase 5: Install Filament 5
- Follow [installation docs](https://filamentphp.com/docs/5.x/introduction/installation): `composer require filament/filament:"^5.0"`, `php artisan filament:install --panels`
- Follow [custom theme docs](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme): `php artisan make:filament-theme`
- Verify AdminPanelProvider registered, viteTheme configured, User model implements FilamentUser
- `npm run build` to compile theme

### Phase 6: GitHub CI workflow
- Create `.github/workflows/tests.yml`
- Triggers on push to `main`/`feature/**` and PRs to `main`
- Sets up PHP 8.2, Node, Composer deps, npm ci, npm run build (Vite manifest), SQLite, migrations, tests

### Phase 7: Open source preparation
- Create MIT `LICENSE` file
- Update `composer.json` metadata (`assertchris/shell`, description, keywords)
- Update `.env.example` (`APP_NAME=Shell`, clean defaults)
- Replace stock `README.md` with project-specific content
- Update `config/app.php` default name to `'Shell'`

### Phase 8: Laravel Forge deployment
- Create `herd.yml` with placeholder server-id/site-id
- Ensure `composer setup` works for deployment

### Phase 9: Final verification
- Run all tooling end-to-end: format, IDE helpers, tests, artisan, Filament panel

## Progress

### Completed
- Planning and feature doc creation
- Phase 1: Remove unused dependencies and files
- Phase 2: IDE helpers
- Phase 3: Pint (config in `meta/pint.json`)

- Phase 4: Pest (replaced PHPUnit, tests converted and passing)
- Phase 5: Filament 5 (v5.2.0 installed, admin panel, custom theme, FilamentUser on User model)

### To Do
- Phase 6: GitHub CI
- Phase 7: Open source prep
- Phase 8: Forge deployment
- Phase 9: Final verification

## Technical Notes
- IDE helper is in `require` (not `require-dev`) to match the assertchris.dev portfolio project pattern
- The Vite manifest must be built before tests run (any view using `@vite` will fail without it)
- Frontend stack is kept despite this being primarily a proxy -- assistants will push data into a UI

## Files Modified/Created

### Config
- `config/ide-helper.php` — Created via vendor:publish, settings mirrored from assertchris.dev
- `config/app.php` — Default APP_NAME changed to 'Shell'

### Models
- `app/Models/User.php` — FilamentUser interface added by Filament install

### Views / Frontend
- `resources/views/welcome.blade.php` — Deleted
- `resources/css/filament/admin/theme.css` — Created by make:filament-theme
- `vite.config.js` — Updated with Filament theme input

### Admin / CMS
- `app/Providers/Filament/AdminPanelProvider.php` — Created by filament:install

### Tests
- `tests/Pest.php` — Created by pest:install
- `tests/Feature/ExampleTest.php` — Converted to Pest syntax
- `tests/Unit/ExampleTest.php` — Converted to Pest syntax
- `tests/TestCase.php` — Deleted

### Infrastructure
- `.github/workflows/tests.yml` — CI workflow
- `herd.yml` — Forge deployment config (placeholder IDs)
- `meta/.gitignore` — Ignores generated IDE helper files, whitelists pint.json
- `meta/pint.json` — Pint config (Laravel preset, excludes meta/)
- `LICENSE` — MIT license
- `README.md` — Project-specific content
- `.env.example` — Cleaned up defaults
- `composer.json` — Dependencies, scripts, metadata
- `.editorconfig` — Removed Docker Compose override
- `.gitignore` — Removed pail reference
- `phpunit.xml` — Removed unused env vars
- `routes/web.php` — JSON response instead of welcome view
- `routes/console.php` — Removed inspire command
- `bootstrap/providers.php` — AdminPanelProvider registered

## Testing
✅ **Passing**: 2 tests, 2 assertions (Pest v4)

## Decisions Made
- **Pint config**: Laravel preset, `meta/pint.json` excludes `meta/` from formatting
- **Frontend stack**: Keep Vite/Tailwind/npm for future UI work
- **Forge IDs**: Placeholders for now, fill in when site is provisioned
- **IDE helper placement**: In `require` (not `require-dev`) to match portfolio project pattern
- **Filament installation**: Follow official v5 docs, not copy from assertchris.dev (which uses v3)
- **Dev workflow**: No `composer dev` script — app served via nginx/Herd, npm dev run in separate tabs
- **Pre-commit discipline**: Format, test, and update feature doc before every commit

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Created feature doc from template
- Explored current project state (fresh Laravel 12) and reference project (assertchris.dev) patterns
- Planned 9 implementation phases covering: cleanup, IDE helpers, Pint, Pest, Filament 5, GitHub CI, open source prep, Forge deployment, final verification
- Decided to keep frontend stack, use Pint defaults, use Forge placeholders
- Added Filament 5 phase with admin panel and custom theme (following official docs)

### Session 2
- Completed Phase 1: removed Sail/Pail, deleted welcome view, replaced with JSON route, cleaned console.php, updated composer scripts (npm ci, removed dev/post-create-project-cmd), cleaned .editorconfig/.gitignore/phpunit.xml
- Added pre-commit checklist to template and feature doc
- Completed Phase 2: installed IDE helper, published and configured config (output to meta/), added composer scripts (clear-caches, format, refresh-ide-helpers, refresh-ide-model-docblock)
- Completed Phase 3: created meta/pint.json with Laravel preset excluding meta/, verified composer format works
- Completed Phase 4: replaced PHPUnit with Pest v4, converted example tests, deleted TestCase.php, cleaned up Pest.php

### Session 3
- Completed Phase 5: installed Filament v5.2.0, ran filament:install --panels (created AdminPanelProvider, registered in bootstrap/providers.php), ran make:filament-theme (created theme CSS, auto-configured vite.config.js and panel provider with viteTheme), added FilamentUser interface to User model with canAccessPanel(), compiled theme via npm run build
