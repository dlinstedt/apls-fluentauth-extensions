# v1.0.24 Release Status

This is the frozen development baseline, not an official WordPress.org release. Maintainer staging validation has passed native and shortcode registrations, Direct FluentCRM subscribed/transactional flows, WPFusion mode, eight ACF field types, FluentCommunity auth redirects, failure-path settings checks, LiteSpeed login/verification exclusions, and plugin reactivation. These are user-reported functional tests; they are not a third-party penetration test.

## Remaining publication checks

- Run WordPress Plugin Check and resolve blocking findings.
- Run WordPress coding-standard checks and ensure the external-service disclosure matches actual ZeroBounce usage.
- Exercise adversarial HTTP requests on isolated staging against real FluentAuth and ACF; standalone mock tests are not sufficient.
- Confirm compatibility across the stated minimum WP/PHP versions and current optional dependency versions.
- Upload the 11 screenshots to `assets/screenshots/` and WordPress.org SVN root `assets/` when preparing that channel.

## Snapshot

Runtime version: `1.0.24`. No registration runtime logic was modified during documentation packaging.
