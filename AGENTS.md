# AGENTS.md

Guidance for AI/code agents contributing to this plugin.

## Project intent

- WordPress plugin for admin-only one-click user switching.
- Must be safe for WordPress.org plugin directory submission.
- Prioritize security, backward compatibility, and WordPress coding standards.

## Non-negotiable rules

1. Never weaken permission checks.
2. Never remove nonce verification from privileged actions.
3. Never introduce raw SQL without `$wpdb->prepare()`.
4. Never output unescaped user-controlled data.
5. Never add telemetry or external calls without explicit user request.
6. Do not break multisite compatibility.

## Coding standards

- Follow WordPress PHP Coding Standards (WPCS).
- Use plugin text domain: `user-login-switch`.
- Wrap all user-facing strings with i18n functions.
- Keep code ASCII unless file already uses Unicode.
- Add comments only when logic is non-obvious.

## Security checklist for every change

- Capability check present (`manage_options` + admin constraints).
- Nonce check present for action endpoints/forms.
- Input sanitized (`absint`, `sanitize_text_field`, `sanitize_key`, etc.).
- Output escaped (`esc_html`, `esc_attr`, `esc_url`, etc.).
- Token/session changes fail closed on invalid state.

## Data and privacy rules

- Audit logging is optional and configurable.
- Respect retention settings and cleanup schedule.
- If adding new personal data fields, update docs and settings controls.

## File and architecture expectations

- Keep core logic in `includes/` classes.
- Keep admin UI rendering in `admin/views/`.
- Keep styles in `assets/`.
- Do not add large framework dependencies.

## Testing expectations

When code changes are made, verify at minimum:

- PHP syntax checks on changed files.
- Admin switch -> target user -> return flow.
- Invalid nonce/capability denial paths.
- Multisite activation path does not fatal.

## Commit guidance

- Use clear, concise commit messages focused on why.
- Do not commit secrets or local environment artifacts.
- Keep commits scoped to related changes.

## Preferred roadmap order

1. Security hardening and edge cases.
2. WordPress.org docs/readme and i18n completeness.
3. Audit log admin viewer (if requested).
4. UX improvements after core safety is complete.
