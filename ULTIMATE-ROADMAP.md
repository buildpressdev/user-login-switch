# Ultimate User Switcher Roadmap

## Product Goal

Ship the safest and fastest admin/developer user switcher in the WordPress plugin directory, with strict production safety defaults and strong operational auditability.

## Locked Decisions

- Production safety defaults: **strict mode enabled by default**.
- CSV export: **include** (useful for support/compliance workflows).
- Reason-for-switch field: **not required**.

## Positioning

- Primary user: WordPress administrators and developers.
- Primary use cases: QA role testing, support debugging, content visibility checks.
- Promise: quick switching without weakening security.

## Core Standards (Must Never Break)

- True impersonation only: switched account has only target permissions.
- Capability + nonce checks for every privileged switch action.
- Signed, expiring token model with one-time return semantics.
- Graceful failure on invalid state; no privilege escalation paths.
- Multisite support and backward compatibility (WP 6.0+, PHP 7.4+).

## Strict Production Safety Mode

Default behavior on production:

- Logged-out quick login disabled.
- Guest-facing quick switch UI disabled.
- Only logged-in admin-initiated switching allowed.
- Sensitive endpoints rate-limited and fully audited.

Override path (explicit):

- Require settings opt-in and code-level constant guard for production-only risky features.

## Roadmap Phases

### Phase 1 - Security Hardening (Immediate)

1. Add centralized policy service (initiator, target, environment gating).
2. Add endpoint rate limiting for quick-login and search actions.
3. Add token lifecycle hardening (logout invalidation, replay prevention checks).
4. Add explicit unsupported-environment fail notices (no fatal behavior).

Acceptance:

- No switch path bypasses capability and nonce checks.
- Production defaults block logged-out quick login.

### Phase 2 - Admin UX Excellence

1. Keep users table quick-switch icon column as primary admin action.
2. Improve admin bar menu with better grouping and recent switch context.
3. Improve frontend widget accessibility (keyboard, focus trap, ARIA feedback).
4. Keep same-page redirect behavior after frontend switch.

Acceptance:

- Admin completes switch/return in <= 2 clicks from common contexts.

### Phase 3 - Audit Center + CSV Export

1. Add audit log admin page with filters:
   - action, actor, target, date range, status.
2. Add CSV export of filtered results.
3. Add retention tools and manual prune action.
4. Add clear privacy copy for logged fields.

Acceptance:

- Support/admin can export switch timeline as CSV.
- Retention cleanup remains automatic and configurable.

### Phase 4 - Compatibility and Submission Readiness

1. WPCS and plugin review hardening pass.
2. `readme.txt` completion (features, FAQ, privacy, screenshots).
3. i18n completion and language template generation.
4. Backward compatibility matrix test:
   - WP min/current, PHP min/current.
5. Multisite test matrix (site + network activation).

Acceptance:

- Plugin installs and runs clean on supported versions.
- No reviewer-blocking security/compliance issues.

## Testing Matrix

### Functional

- admin -> subscriber -> return
- admin -> editor -> return
- frontend same-page switch redirect
- admin bar quick switch + return

### Security

- invalid nonce rejected
- unauthorized user denied
- expired token denied
- replayed quick-login link denied
- production guest quick-login default denied

### Multisite

- per-site activation
- network activation
- blog context correctness in logs

### Compatibility

- WP 6.0 baseline smoke test
- latest WP smoke test
- PHP 7.4 baseline syntax/runtime smoke test

## KPI Targets

- Time to switch user: < 5 seconds in common admin flow.
- Return reliability: 100% in valid switched sessions.
- Security regressions: 0 known bypasses.
- Plugin review readiness: pass internal checklist before every release.

## Next Implementation Batch

1. Build audit log viewer screen with filtering and CSV export.
2. Add rate limiting for quick-login endpoint.
3. Add production safety notice panel in settings.
