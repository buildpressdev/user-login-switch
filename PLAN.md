# User Login Switch Plugin - Implementation Plan

## 1) Objective

Build a WordPress plugin that lets administrators instantly switch into any user account (without knowing passwords) for testing and support workflows, then safely return to the original administrator session.

Primary goals:
- One-click switch from admin context.
- Safe impersonation (switched user gets only their real role capabilities).
- Clear return-to-admin flow.
- Audit trail proving the session originated from an administrator.
- Basic settings page for behavior, placement, and style controls.

## 2) Confirmed Product Decisions

- Platform scope: support both single-site and multisite.
- Initiator: administrator only.
- Targets: any role.
- Switching mode: true impersonation (recommended) - admin capabilities are dropped while switched.
- Remaining defaults chosen with security-first behavior.

## 3) UX and Permission Model

### Who can switch
- Only users with `manage_options` can initiate switching.
- On multisite network admin screens, allow super admins (and optionally map to `manage_network_options` where relevant).

### Who can be switched into
- Any existing user role is valid target.
- No password required for target account.

### During switched session
- Current session runs with target user permissions only.
- Persistent notice in wp-admin: "You are switched from Admin <name>."
- "Return to original admin" action available in:
  - admin bar menu
  - secure fallback URL endpoint

### Return flow
- One-click return restores original admin identity.
- If token/session is invalid or expired, return action fails safely with clear message.

## 4) Security Design

### Core protections
- Capability check on every switch/return action.
- Nonce validation for all action links/forms.
- Signed, expiring switch token bound to session fingerprint.
- Token invalidated after successful return or logout.

### Token/session data tracked
- `origin_user_id`
- `target_user_id`
- `created_at`
- `expires_at`
- `session_hash`
- `blog_id` (important for multisite traceability)
- `status` (`active`, `returned`, `expired`, `revoked`)

### Threat controls
- Prevent CSRF via nonces.
- Prevent privilege confusion by always reading actual current user caps while switched.
- Prevent replay via expiration + one-time return semantics.
- Fail closed if token verification fails.

## 5) Audit Logging

Audit events to store:
- `switch_started`
- `switch_returned`
- `switch_failed`
- `switch_expired`

Event fields:
- event id
- site/blog id
- actor (origin admin) user id
- target user id
- current user id at event time
- timestamp (UTC)
- IP address (if available)
- user agent (if available)
- action
- status/details message

Retention default:
- keep logs for 90 days (configurable).

## 6) Settings Page (v1)

Menu: `Settings -> User Login Switch`

Sections:
1. General
   - Enable plugin functionality
   - Session timeout (default 120 minutes)
2. Access
   - Initiator locked to admin-only (display as fixed in UI)
   - Target roles (default all)
3. UI Placement
   - Show switch action in Users list row actions
   - Show switch menu in admin bar
   - Show admin notice/banner while switched
4. Style
   - Button style preset: `default`, `compact`, `minimal`
5. Logging
   - Enable audit logging
   - Log retention days
   - Clear logs action (nonce protected)

## 7) Multisite Behavior

Support model:
- Works when activated per site and when network-activated.
- Switch context is site-aware; include `blog_id` in token and logs.
- On network activation, maintain per-site settings by default to avoid unexpected global side effects.
- For super admin in network admin, allow switching with equivalent admin-only restrictions.

Out of scope for v1 (can be v2):
- Global network-wide shared settings UI.
- Cross-site switch teleport that auto-jumps between dashboards.

## 8) Proposed Plugin Architecture

```
user-login-switch/
  user-login-switch.php
  includes/
    class-uls-plugin.php
    class-uls-switch-manager.php
    class-uls-token-store.php
    class-uls-audit-log.php
    class-uls-settings.php
    class-uls-admin-ui.php
    class-uls-multisite.php
  admin/
    views/
      settings-page.php
  assets/
    admin.css
  uninstall.php
```

Responsibilities:
- `switch-manager`: switch and return workflows.
- `token-store`: signed token lifecycle and validation.
- `audit-log`: DB table create/write/prune.
- `settings`: register settings and sanitize inputs.
- `admin-ui`: users list actions, admin bar controls, notices.
- `multisite`: capability and blog context helpers.

## 9) Data Layer

Custom table (recommended) for durable audit logs:
- table name: `{$wpdb->prefix}uls_audit_log`

Columns:
- `id` bigint PK
- `blog_id` bigint
- `actor_user_id` bigint
- `target_user_id` bigint nullable
- `current_user_id` bigint nullable
- `action` varchar(50)
- `status` varchar(20)
- `ip_address` varchar(45) nullable
- `user_agent` text nullable
- `details` text nullable
- `created_at_gmt` datetime

Token storage (v1):
- user meta or transient + signed payload.
- prefer user meta keyed by origin admin for deterministic return handling.

## 10) Implementation Phases

### Phase 1 - Core
- Bootstrap plugin structure.
- Add activation/deactivation hooks.
- Implement switch/return endpoints with nonce + capability checks.
- Add admin notice + return link.

### Phase 2 - UI
- Add Users list row action: "Switch To".
- Add admin bar menu for switch state and return action.
- Add basic admin CSS style presets.

### Phase 3 - Settings
- Register settings, sanitizers, defaults.
- Build settings page and persistence.
- Wire toggles into runtime behavior.

### Phase 4 - Audit + Multisite
- Create audit table and logging service.
- Add prune routine (scheduled cleanup).
- Validate behavior for single-site + multisite contexts.

### Phase 5 - Hardening + QA
- Expired token scenarios.
- Nonce/capability bypass tests.
- Plugin compatibility smoke test with common admin plugins.

## 11) Acceptance Criteria

- Admin can switch to any user from Users list in one click.
- Switched session reflects target user permissions only.
- Return-to-admin works reliably until timeout/logout.
- Audit log records switch and return with actor + target + timestamp.
- Non-admin users cannot initiate switch action.
- Plugin works on single-site and multisite without fatal errors.
- Settings page controls key toggles and persists values correctly.

## 12) Test Checklist

Functional:
- admin -> subscriber switch -> return
- admin -> editor switch -> return
- multiple sequential switches

Security:
- invalid nonce rejected
- direct URL without capability rejected
- expired token cannot return

Multisite:
- per-site activation behavior
- network activation behavior
- blog_id correctness in logs

Regression:
- login/logout while switched
- admin bar disabled user preference
- users with deleted target account

## 13) Risks and Mitigations

- Risk: session confusion in custom auth environments.
  - Mitigation: rely on core WP auth cookies and strict token checks.
- Risk: plugin conflicts with custom user management plugins.
  - Mitigation: feature flags and conservative hook priorities.
- Risk: privacy concerns for IP/UA storage.
  - Mitigation: allow logging toggle and retention control.

## 14) Next Step

Start Phase 1 by scaffolding plugin files and implementing the secure switch/return engine first, then layer UI and settings.
