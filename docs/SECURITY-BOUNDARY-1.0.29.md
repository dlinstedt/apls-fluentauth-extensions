# v1.0.29 - Registration input boundary and PHPCS annotations

This document describes the intentional registration request-input boundary in `includes/class-registration.php`. It is a source-level rationale, **not** a security certification or substitute for live testing.

## Centralized input access

`Registration::submitted($name)` permits only an explicit list of known registration field names. It checks whether the submitted field is present, applies WordPress's `wp_unslash()`, and returns only strings or arrays (otherwise `null`). It does **not** validate the caller's authorization or sanitize arbitrary ACF values.

`Registration::submitted_text($name)` accepts only a scalar string and calls `sanitize_text_field()`. Do not use it for structured ACF values.

## Why exactly two PHPCS annotations remain

1. `WordPress.Security.NonceVerification.Missing` on the `isset($_POST[$name])` line: this is a presence check, not a write or authorization decision. Initial registration processing must check `Registration::registration_nonce_valid()` before storing field values.
2. `WordPress.Security.NonceVerification.Missing` and `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` on the `wp_unslash($_POST[$name])` line: this deliberately retrieves untrusted, potentially structured data so that a downstream ACF field-specific validator can reject malformed types, unsupported choices, and invalid formats **before** storage. Applying a generic text sanitizer here would damage legitimate arrays and could conceal malformed input.

Neither annotation changes PHP behavior, sanitizes input, or grants authorization. They apply only to the identified lines, not to the rest of registration processing.

## Security boundaries and invariants

- Initial ACF pending-record creation requires a valid plugin-issued registration nonce (`wp_verify_nonce()` with the `apls_faext_register` action).
- ACF values must undergo field-specific normalization, type and choice validation, and size limits before pending-table storage.
- The email-code confirmation stage relies on FluentAuth's verification flow and the plugin's expiring, HMAC-bound transaction, email, and schema checks. A WordPress nonce is not a single-use proof of identity.
- Output must be escaped for its destination context. Reading a POST value does not execute it; passing it to an unsafe interpreter or unescaped output would be a separate risk.
- Other callers of `Registration::submitted()` must not treat its return value as safe merely because it passed through this accessor.

## Required release verification

Run the security, nonce, and WPFusion regression suites; test native and shortcode registration with valid and forged first-stage submissions; test successful and expired verification transactions; and run WordPress Plugin Check against the exact distribution ZIP. A scanner pass alone does not establish runtime security.
