# Security hardening assessment - 1.0.22-dev

This release contains source-level hardening and standalone regression tests, not a full penetration test or certification of FluentAuth, WordPress, ACF, or ZeroBounce.

## Added defenses

- Validate `url` inputs as HTTP/HTTPS URLs only; reject script/data/file URL schemes.
- For `true_false`, require canonical `0`, `1`, or empty input (empty maps to false); reject arbitrary strings or arrays.
- Reject non-finite numeric values, unexpectedly long scalar inputs (over 4096 bytes), and oversized choice values.
- Reject unknown ACF field keys on native and shortcode POST paths.
- Normalize submitted ACF values before reintroducing them into ACF rendering after a rejected registration.
- Reject malformed and oversized emails on the public live validation AJAX endpoint.
- Document the ZeroBounce upstream null offset warning and potential duplicate WordPress-registration checks.

## Caveats and unclosed gates

- Stored field values are sanitized; they are not generally HTML-escaped before storage, because escaping is output-context-specific. Custom rendering must call `esc_html`, `esc_attr`, `esc_url`, or another matching escaper at its output sink.
- ACF core/custom `acf/validate_value` logic is not guaranteed to run in the custom registration path. Configured constraints and supported types must be independently audited against real ACF field settings.
- Public email validation transient rate limiting is not an atomic distributed quota. Add or configure upstream WAF limits, provider quotas and monitoring before wide deployment.
- Pending data is short-lived plaintext in a custom database table; do not request sensitive information in registration fields. Database backups may retain older copies.
- The standalone suite does not boot WordPress, inspect actual rendered HTML/XSS with a browser, test real verification-code flows, or perform concurrency/traffic/load testing.
- ZeroBounce's version 1.1.6 line-378 null dereference belongs to its own plugin; our code does not modify the vendor code.
- WordPress Plugin Check, actual FluentAuth native/shortcode regression, WPFusion, accessibility, translations, and plugin-review policy checks are still required.

## Recommended staging adversarial cases

Use native and shortcode forms and submit crafted POST data for each supported ACF type: HTML tags, quote-breaking attributes, nested arrays, unknown keys, invalid choice values, huge payloads, invalid calendar data, URL schemes, malformed floats, and wrong verification transaction/email. Confirm rejection before pending table writes, or inert output after restoration. Verify rendered user profiles and every downstream FluentCRM/community integration. Test API rate limits under concurrency and rotate credentials when necessary.

No GitHub or WordPress.org publication is authorized by this report.
