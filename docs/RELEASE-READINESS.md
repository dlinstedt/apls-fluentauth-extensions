# Release-readiness review - v1.0.18

**Status: HOLD for broad public/WordPress.org release.** The maintainer has completed successful native and shortcode registration tests on staging, including ACF persistence (8/8) and Direct FluentCRM subscribed/transactional outcomes. This does not replace security review or compatibility tests. No static analysis result proves absence of vulnerabilities.

## Must resolve or explicitly accept before production

1. **ACF server-side validation completeness:** `ACF_Adapter::normalize()` checks basic types/choices, email/URL, numeric syntax and date formats; it does **not** comprehensively apply ACF's min/max, minlength/maxlength, step/format, conditional-required, dynamically added choice and other ACF field rules. Crafted POST requests may circumvent browser constraints. Add server-side rule parity for every allowlisted field, reject unapproved extra submitted keys, verify conditional logic semantics, and add adversarial regression tests. At present, **do not claim that all ACF validation rules are enforced server-side**.
2. **Data minimization and availability:** Pending registration JSON contains user-supplied ACF data unencrypted at rest. There is no explicit hard size cap on submitted ACF payload / number of stored pending registrations. Introduce payload size/count boundaries and rate controls appropriate to anonymous registration traffic, enforce restrictive DB access, monitor table growth and schedule cleanup via reliable system cron when needed. Avoid sensitive ACF collection.
3. **Untrusted public validation endpoint:** WordPress nonces are not an anti-abuse barrier for anonymous users. Per-IP transient throttling is a useful starting point but has race, distributed-IP and NAT limitations. Add edge/WAF rate limiting, service quota limits and capacity tests before widespread exposure.
4. **Provider consent and privacy:** Document ZeroBounce disclosure, retention, privacy policy, and security of the site's configured SMTP, WordPress user database and exports. Define suppression, resubscribe and consent evidence retention with legal review.
5. **End-to-end failure testing:** Test invalid/missing/expired/forged verification transactions, invalid field choices, malformed arrays, concurrent registrations, dropped DB writes, mail delivery failure, transient ZeroBounce failures, duplicate contact, existing unsubscribed contact, subscription upgrade, and incomplete ACF writes.
6. **Version and compatibility:** Regression-test against target WordPress/PHP, FluentAuth, ACF/ACF Pro, FluentCRM, WPFusion, ZeroBounce and FluentCommunity versions. PHP DOM availability must be checked. FluentAuth's email verification filter currently forces `true`, so confirm administrators cannot unintentionally weaken a desired policy; an option-based, clearly documented choice may be preferable. Ensure alternative registration entry points cannot bypass policy.
7. **Official packaging checks:** Run PHPCS (WordPress rules), WordPress Plugin Check, WordPress.org readme validator, translation extraction checks and PHPUnit/WP integration tests. Capture real screenshot assets. Confirm `Update URI` behavior before WordPress.org publication, plugin header metadata, distribution source, and GitHub licensing.

## Additional observations (not automatically blockers)

- FluentCRM `crm_sync_completed` and WPFusion bridge do not guarantee identical second-argument types; the public hook contract needs normalization before promising a strict object signature.
- `Email_Validator::validate` returns early for cached results, so `email_validated` does not fire for every validation attempt. Consumers must not use that action as a complete audit trail.
- ACF profile visibility follows ACF location rules; it does not imply FluentCommunity profile rendering.
- The code uses a DOMDocument transformation for shortcode markup insertion; upstream markup changes can break placement without compile-time failures.
- Deactivation can optionally drop the pending table; warn if in-flight registrations are active.
- Diagnostic event data may include error strings in specific paths: audit and test privacy redaction under intentionally malformed inputs.

## Minimum release QA matrix

| Scenario | Expected outcome |
| --- | --- |
| Native + shortcode, marketing unchecked | Account created after code verification, new FluentCRM contact `transactional`, registration tag only |
| Native + shortcode, marketing checked | Account created after code verification, new FluentCRM contact `subscribed`, registration + marketing tags |
| Existing suppressed contact | No unapproved resurrection |
| ACF: each of 12 supported field types | Correct presence, required check, storage, user-profile display, rejection of invalid crafted POST |
| Multiple ACF groups / duplicate names / unsupported type | Safe validation error and administrator notification |
| Date/time formats and Select/Checkbox/Radio Other | Responsive UI, canonical values, no server-side bypass |
| ZeroBounce valid, invalid, unknown, timeout, rate limiting | Correct policy decision / no secret leakage |
| Pending record expired / malformed / reused | Safe refusal, no ACF data crossover |
| Failed ACF write, failed CRM sync | Diagnostic evidence, no unsupported success claim |
| Screen sizes and keyboard navigation | Stable options, no critical layout shifts, readable errors |
| Plugin deactivate/reactivate/uninstall | Expected data retention, scheduled cleanup, no third-party deletion |

## Deployment recommendation

A **controlled deployment on the maintainer's own production site** can be a business-risk decision after an explicit acceptance of the above residual risks and a rollback plan. A **general public release** should wait for full server-side validation hardening and test coverage. This repo preparation deliberately does not modify proven production logic to introduce untested changes immediately before deployment.

## 1.0.19 security audit delta

- Added direct server-side checks for ACF numeric min/max, text min/max lengths, and checkbox option count bounds. Custom ACF validation filters and conditional required logic are **not** yet equivalent to the browser ACF engine; release remains gated on adversarial testing.
- Caps pending registration payloads at 64 values and 16 KiB encoded JSON.
- Removed schema install/dbDelta from ordinary `init` requests; activation still creates the table. On existing sites, confirm `apls_faext_db_version` and table exist.
- The browser admin spinner has **not been reproduced** in a live WordPress installation. Admin JS is scoped to the plugin settings page; isolate the installed plugin by temporarily deactivating it and comparing admin behavior, and inspect Network for blocked requests or REST/Heartbeat errors.
- Do not claim that these changes constitute a completed security assessment or production-ready release.


## v1.0.20 security regression additions

- Static regression command: `php tests/security-regression.php` (redirect allow/deny matrix and ACF option-normalization scenarios).
- Native FluentCommunity auth redirect deliberately off by default; must be browser-tested against installed FluentCommunity with login, register, invitation, password reset and custom community portal paths.
- Server rejects custom/dynamic and conditional ACF configurations instead of silently trusting client-state conditional rules.
- Multi-select cardinality, predefined choice membership, duplicate selection, oversized/nested array inputs validated server-side.

**Outstanding publication gates:** test with actual installed FluentAuth, ACF, FluentCommunity, FluentCRM and ZeroBounce; obtain WordPress Plugin Check output; review anonymous email validation rate-limit effectiveness and concurrent traffic; verify WordPress.org external-service disclosure and screenshot accuracy. Standalone tests are not end-to-end integration tests.
