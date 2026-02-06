# Add Assistant Documentation Pages

- Branch: `feature/orphan-3-add-assistant-docs`
- Related Issue: N/A
- **Status**: 🟡 In Progress
- Examples: 🟡 In Progress / ✅ Complete / 🔴 Blocked

## Overview

The app is a read-only email proxy API authenticated via Sanctum tokens. An AI assistant will consume this API, but there are no documentation pages describing the endpoints, authentication, or expected request/response formats. The assistant needs clear, rendered documentation with sample requests and placeholder tokens.

### Background / Current Behavior
- **Current behavior**: API endpoints exist but have no user-facing documentation
- **Problem**: No way for an assistant (or any consumer) to discover how to authenticate or use the API
- **Where it happens**: No docs pages exist — `resources/views/` is empty
- **Impact**: Assistant cannot self-serve API usage without manual instruction

### Target Outcome
This feature will add markdown-rendered documentation pages so that an AI assistant can:
- Understand how to authenticate using a Sanctum bearer token
- Discover all available mailbox API endpoints
- See sample requests and responses for each endpoint

## Requirements

### Functional
- [ ] Public `/docs` index page listing all documentation topics
- [ ] `/docs/authentication` page covering Sanctum token usage with `YOUR_API_TOKEN` placeholder
- [ ] `/docs/mailboxes` page covering all 5 API endpoints with sample curl requests and JSON responses
- [ ] Unknown slugs return 404

### Non-Functional
- [ ] No authentication required to view docs (they explain how to authenticate)
- [ ] Styled with Tailwind Typography (`prose` classes) for readable markdown rendering
- [ ] Extensible — adding a new doc page requires only a `.md` file and one registry entry

### Out of Scope
- Syntax highlighting for code blocks (future enhancement)
- Search within docs
- Versioned documentation

## Implementation Plan

1. **Install `@tailwindcss/typography`** and add `@plugin` directive to `resources/css/app.css`

2. **Create Blade layout** — `resources/views/components/docs-layout.blade.php` with sidebar nav and prose-styled content area

3. **Create page registry** — `app/Http/Responders/Docs/DocPages.php` mapping slugs to titles and `.md` filenames

4. **Create responders**
   - `app/Http/Responders/Docs/ListDocPagesResponder.php` — index page
   - `app/Http/Responders/Docs/ShowDocPageResponder.php` — renders markdown via `Str::markdown()`

5. **Create markdown content**
   - `resources/docs/authentication.md` — token creation, usage, sample request, error responses
   - `resources/docs/mailboxes.md` — all 5 endpoints with descriptions, sample curl + JSON responses

6. **Add routes** to `routes/web.php`
   - `GET /docs` → `ListDocPagesResponder` (name: `docs.index`)
   - `GET /docs/{slug}` → `ShowDocPageResponder` (name: `docs.show`)

7. **Create views**
   - `resources/views/docs/index.blade.php`
   - `resources/views/docs/show.blade.php`

8. **Write tests** — `tests/Feature/Docs/DocPagesTest.php` covering index, each page, 404, no auth required

## Progress

### Completed
- Explored codebase (responder pattern, API endpoints, resources, routing)
- Created implementation plan
- Phase 1: Installed `@tailwindcss/typography`, added `@plugin` directive to `resources/css/app.css`
- Phase 2: Created docs layout, index view, and show view
- Phase 3: Created `DocPagesService` (moved from registry to proper service)
- Phase 4: Created `ListDocPagesResponder` and `ShowDocPageResponder` with DI
- Phase 5: Created `authentication.md` and `mailboxes.md` documentation
- Phase 6: Added routes to `web.php`, fixed doc content issues

### In Progress
- Phase 7: Write tests

### To Do
- Write tests
- Format and verify

## Technical Notes

- Responders follow the existing pattern: single-action invokable classes, no base class, `__invoke()` method
- Markdown rendered via `Str::markdown()` (Laravel built-in, uses CommonMark)
- Content lives in `resources/docs/*.md` — easy to edit without touching PHP
- Token placeholder throughout docs: `YOUR_API_TOKEN`

## Files Modified/Created

### API / Controllers / Responders
- `app/Services/DocPagesService.php` — Page registry service (slug → title + file mapping)
- `app/Http/Responders/Docs/ListDocPagesResponder.php` — Index responder
- `app/Http/Responders/Docs/ShowDocPageResponder.php` — Show responder

### Views / Frontend
- `resources/views/components/layouts/docs.blade.php` — Shared layout component
- `resources/views/docs/index.blade.php` — Index page
- `resources/views/docs/show.blade.php` — Individual doc page
- `resources/docs/authentication.md` — Auth documentation
- `resources/docs/mailboxes.md` — API endpoint documentation
- `resources/css/app.css` — Add typography plugin directive
- `routes/web.php` — Add doc routes

### Tests
- `tests/Feature/Docs/DocPagesTest.php` — Feature tests for doc pages

## Decisions Made
- **Single responder with slug param**: More extensible than one responder per page, since all pages do the same render logic
- **Markdown files in `resources/docs/`**: Separates content from presentation, easy to edit
- **Public routes (no auth)**: Docs explain how to authenticate, so they must be accessible without a token
- **Tailwind Typography**: Standard approach for styling rendered HTML in Tailwind projects

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Created feature doc
- Explored codebase: responder pattern, all API endpoints, routing, auth, resources, views
- Designed implementation plan (8 phases)
- Plan pending approval

### Session 2
- Phase 1: Installed `@tailwindcss/typography`, added plugin directive to CSS
- Phase 2: Created layout (`components/layouts/docs.blade.php`), index and show views
- Phase 3: Created `DocPagesService`, responders with DI
- Phase 4+5: Created responders and markdown documentation content
- Phase 6: Added routes, fixed doc content (removed read-only claim, fixed token expiry, removed duplicate status endpoint)
