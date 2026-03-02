# User Login Switch - WordPress.org Ready Plan

## 1) Goal

Build a secure, admin-only WordPress plugin that allows one-click user switching for testing/support, with safe return to the original admin and a verifiable audit trail.

This plan is refined for WordPress Plugin Directory standards.

## 2) Locked Product Decisions

- Scope: single-site and multisite.
- Initiator: administrator only.
- Target users: any role.
- Behavior: true impersonation (switched user gets only their own capabilities).
- Return flow: always available while switched.

## 3) WordPress.org Compliance Requirements

### Licensing and packaging
- Plugin must be GPL-compatible.
- No obfuscated or minified-only source without readable source.
- No external libraries with incompatible licenses.

### Security
- Validate capability on every privileged action.
- Verify nonces for all switch/return and settings actions.
- Sanitize all input and escape all output.
- Use `$wpdb->prepare()` for custom SQL.
- Fail closed when token/session checks fail.

### Privacy
- If logging IP/User-Agent, document clearly and make it optional.
- Provide retention settings and cleanup routine.
- Add privacy policy guidance text via plugin docs.

### Internationalization
- Wrap user-facing strings in translation functions with text domain `user-login-switch`.
- Load text domain on `plugins_loaded`.

### Performance
- No heavy queries on every admin page load.
- Limit logging and cleanup work (scheduled daily).

### Backward compatibility
- Define minimum supported versions and enforce them in docs and code.
- Initial baseline: WordPress `6.0+` and PHP `7.4+`.
- Avoid newer APIs without compatibility guards/fallbacks.
- On unsupported environments, fail gracefully with admin notice (no fatal errors).
- Keep schema/settings upgrades idempotent for safe updates from older plugin versions.

### Plugin directory expectations
- Include standard `readme.txt` format.
- Avoid trademark misuse and misleading naming.
- No upsell spam, adware patterns, or forced tracking.

## 4) UX and Permission Model

### Who can switch
- Only users with `manage_options` and administrator role.
- On multisite, allow super admins.

### Switched state
- Session acts as target user only.
- Admin notice shows switched context.
- Admin bar includes "Return to Original Admin".

### Return behavior
- One-click return restores original admin if token is valid.
- Expired/invalid token returns a safe error and logs event.

## 5) Security Model

### Token/session fields
- `origin_user_id`
- `target_user_id`
- `created_at`
- `expires_at`
- `session_hash`
- `blog_id`
- `status`

### Controls
- Signed, expiring token.
- Token bound to session context and blog.
- One-time return semantics (invalidate after use).
- Invalidate token on logout and plugin deactivation.

## 6) Audit Logging Model

### Events
- `switch_started`
- `switch_returned`
- `switch_failed`
- `switch_expired`

### Fields
- id, blog_id, actor_user_id, target_user_id, current_user_id
- action, status, details
- ip_address (optional), user_agent (optional)
- created_at_gmt

### Retention
- Default 90 days; configurable.
- Daily scheduled cleanup.

## 7) Settings Page (v1)

Menu: `Settings -> User Login Switch`

Sections:
- General: enable plugin, timeout.
- Access: admin-only initiator (fixed), target role filter.
- UI: users-row action, admin-bar menu, switched notice.
- Style: default/compact/minimal button style.
- Logging: enable logs, retention days.

## 8) Actual File Architecture (Current)

```
user-login-switch/
  user-login-switch.php
  includes/
    class-uls-plugin.php
    class-uls-switch-manager.php
    class-uls-audit-log.php
    class-uls-settings.php
    class-uls-admin-ui.php
  admin/
    views/
      settings-page.php
  assets/
    admin.css
  uninstall.php
```

## 9) Gap Fixes Before Plugin Directory Submission

1. Add `readme.txt` in WordPress.org format.
2. Add `languages/` folder and load text domain.
3. Add uninstall cleanup for multisite options/tables if needed.
4. Add explicit logout hook handling for switched tokens.
5. Add PHPCS compliance pass with WordPress Coding Standards.
6. Add nonce and capability checks for every settings-side action (including log clear action if added).
7. Add privacy disclosure text for audit fields.
8. Add compatibility testing notes: latest WP + PHP versions.
9. Add backward compatibility matrix and minimum version policy in docs.

## 10) Implementation Phases

### Phase 1 - Core hardening
- Finish token lifecycle edge cases (logout, expiration, invalidation).
- Confirm multisite switch behavior and context safety.

### Phase 2 - Standards and docs
- Add `readme.txt`, license clarity, i18n bootstrap.
- Add privacy and data-retention docs.

### Phase 3 - QA and submission readiness
- Run WP coding standards checks.
- Manual test matrix for single-site and multisite.
- Prepare release tag and changelog.

### Phase 4 - Compatibility verification
- Validate on minimum supported WordPress and PHP versions.
- Validate upgrade path from older plugin versions (settings and audit table).
- Add guards for unavailable functions/constants and test graceful degradation.

## 11) Acceptance Criteria

- Admin can switch to any user in one click.
- Switched user has only target capabilities.
- Return-to-admin works reliably within timeout.
- Audit records prove switch origin.
- Non-admin users cannot switch.
- Plugin works in single-site and multisite.
- Settings persist and safely control behavior.
- Packaging/docs meet WordPress.org expectations.
- Plugin runs without fatal errors on minimum supported WP/PHP versions.

## 12) Quick Submission Checklist

- [ ] `readme.txt` valid and complete.
- [ ] All user-facing strings translatable.
- [ ] Security review complete (nonce/capability/sanitization/escaping).
- [ ] Privacy disclosure included.
- [ ] No PHP warnings/notices on debug mode.
- [ ] Tested on current WP and supported PHP versions.
- [ ] Minimum supported WordPress/PHP versions documented and verified.
