# IP Watching for Sanctum Tokens

- Branch: `feature/orphan-4-ip-watching`
- Related Issue: N/A
- **Status**: 🟡 In Progress
- Examples: 🟡 In Progress / ✅ Complete / 🔴 Blocked

## Overview

API tokens (`personal_access_tokens`) currently have no IP awareness. A stolen token can be used from any IP address without detection. This feature adds IP-based security that detects when a token is used from multiple IP addresses within a single day — a strong indicator of token theft.

### Background / Current Behavior
- **Current behavior**: Sanctum tokens work from any IP with no tracking or restrictions
- **Problem**: A compromised token can be used undetected from anywhere
- **Where it happens**: All API routes protected by `auth:sanctum` in `routes/api.php`
- **Impact**: Token theft goes unnoticed; no way to detect suspicious usage patterns

### Target Outcome
This feature will add IP binding to Sanctum tokens so that the system can:
- Track which IP address each token is being used from
- Allow one IP change per day (e.g., home to office)
- Block requests when a token is used from too many IPs in one day (403)
- Show IP tracking info in the Filament admin panel

## Requirements

### Functional
- [ ] Track `last_ip_address` and `ip_changed_at` on each token
- [ ] First request from a token records the IP silently
- [ ] Same IP on subsequent requests continues without extra DB writes
- [ ] First IP change of the day is allowed (columns updated)
- [ ] Second+ IP change in the same day returns 403 with message
- [ ] Next calendar day resets — IP changes are allowed again
- [ ] Session-based auth (Filament/web TransientToken) is not affected
- [ ] Blocked requests are logged via `Log::warning`

### Non-Functional
- [ ] Observability: `Log::warning` on blocked requests with token ID, IPs, and user context
- [ ] Performance: No extra DB write when IP matches (read-only check via model attributes)
- [ ] Security/Permissions: Only affects bearer token auth, not session auth
- [ ] Backwards compatibility: Existing tokens work — first request just records the IP

### UX / Admin
- [ ] Filament AccessTokenResource shows `last_ip_address` and `ip_changed_at` columns
- [ ] Columns have sensible placeholders ("Never used", "Never")

### Out of Scope
- Token revocation on suspicious activity (reject-only for now)
- IP whitelisting or allow-lists per token
- Configurable change threshold (hardcoded to 1 change/day)

## Implementation Plan

### Phase 1: Database + Custom Model
- Create migration adding `last_ip_address` (varchar 45, nullable) and `ip_changed_at` (timestamp, nullable) to `personal_access_tokens`
- Create `app/Models/PersonalAccessToken.php` extending Sanctum's model with `ip_changed_at` datetime cast
- Register custom model in `AppServiceProvider::boot()` via `Sanctum::usePersonalAccessTokenModel()`
- Update `AccessTokenResource` to reference the custom model
- Verify all existing tests pass unchanged

### Phase 2: Enforcement Middleware + Tests
- Create `app/Http/Middleware/EnforceTokenIpBinding.php` with IP check logic
- Register middleware in `bootstrap/app.php` api group
- Create `tests/Feature/Api/IpWatchingTest.php` covering all scenarios
- Verify with `composer format && composer test`

### Phase 3: Filament UI — Show IP Info
- Add `last_ip_address` and `ip_changed_at` TextColumns to AccessTokenResource table
- Add test coverage for column rendering
- Verify with `composer format && composer test`

## Progress

### Completed
- Codebase exploration and planning
- Phase 1: Database + Custom Model

### In Progress
- Phase 2: Enforcement Middleware + Tests

### To Do
- Phase 3: Filament UI — Show IP Info

## Technical Notes

**Middleware logic:** Runs after `auth:sanctum`. Checks if the current token is a `PersonalAccessToken` (skips `TransientToken` for session auth). Compares request IP to `last_ip_address`. If different and `ip_changed_at` is already today, returns 403. Otherwise updates the tracking columns.

**IP column size:** varchar(45) accommodates the longest IPv6 representation.

**No race condition concern:** At this app's scale, two simultaneous requests from different IPs reading the same state before either writes is not a practical risk.

## Files Modified/Created

### Database
- `database/migrations/2026_02_06_143334_add_ip_tracking_to_personal_access_tokens.php`

### Models
- `app/Models/PersonalAccessToken.php`

### Providers
- `app/Providers/AppServiceProvider.php` — registers custom token model

### Services / Core
- (pending) `app/Http/Middleware/EnforceTokenIpBinding.php`

### Admin / CMS
- `app/Filament/Resources/AccessTokenResource.php` — updated model reference

### Tests
- (pending) `tests/Feature/Api/IpWatchingTest.php`

## Testing
🟡 **Pending**: No tests written yet
- Key coverage:
- First use records IP
- Same IP allowed repeatedly
- First IP change of day allowed
- Second IP change blocked with 403
- Return to original IP after change is blocked
- Next day resets
- TransientToken (session auth) unaffected
- Blocked requests logged

## Decisions Made
- **Block behavior**: Reject request with 403, do not revoke the token. Token still works from the allowed IP and resets next day.
- **Threshold**: One IP change per calendar day is tolerated. This is hardcoded, not configurable.
- **Middleware vs callback**: Middleware after `auth:sanctum` rather than Sanctum's `authenticateAccessTokensUsing` callback, because the middleware can safely perform side effects (updating IP columns).

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Explored codebase: Sanctum config, token model, API routes, middleware setup, Filament AccessTokenResource
- Designed IP tracking approach with two columns (`last_ip_address`, `ip_changed_at`) and middleware enforcement
- Decided on reject-only (403) rather than token revocation
- Created 3-phase implementation plan

### Session 2
- Implemented Phase 1: migration, custom PersonalAccessToken model, Sanctum registration, AccessTokenResource model swap
- All 67 existing tests pass unchanged
