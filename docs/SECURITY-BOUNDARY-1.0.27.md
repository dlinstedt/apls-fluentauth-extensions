# v1.0.27 trust-boundary audit

The initial ACF collection POST now requires a plugin-issued WordPress nonce (`apls_faext_registration_nonce`) for both native and shortcode paths. A nonce does **not** authenticate the registrant, prevent automated requests, or replace rate limiting. The verification step is protected by FluentAuth email-code validation plus the HMAC-bound, expiring pending transaction record, not by replaying an initial-stage nonce. ACF user values must pass server-side normalization before pending-table storage.

The ACF field-group editor-side supplemental guard checks `_acf_nonce` using `acf_form`; this is fail-closed if ACF changes its nonce shape. Production compatibility needs a staging test of saving field groups.

The registered WordPress `register_form` and FluentAuth shortcode request formats should be replayed under WordPress integration tests to confirm nonce posting. Unlike a PHPCS warning, these controls affect actual request execution. No claim of zero scanner findings or penetration-test certification is made.

## PHPCS suppression disposition
Broad registration-suppression annotations were removed rather than copied forward. Any remaining scanner findings must be reviewed against real request lifecycle evidence and should not be hidden merely for a clean score. Intentional direct database queries on the plugin-owned pending table remain isolated and parameterized; they use narrow, documented PHPCS exceptions because WordPress has no equivalent table API. The HTML-output exception corresponds specifically to ACF's own escaped field markup. This version is not claimed to pass Plugin Check until a new scan is performed.

## Test requirements
Run native and shortcode first-stage registration tests, native and shortcode verification, missing/forged nonce rejects, registration with ACF disabled, ACF field-group editor save, consent checked and unchecked, Wordfence/LiteSpeed compatibility, and diagnostics.
