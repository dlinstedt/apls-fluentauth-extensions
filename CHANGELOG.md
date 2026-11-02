## 1.0.30 - APlusPlugins publisher identity
- Changed plugin display name, primary filename, PHP namespace, and text domain to APlusPlugins branding.
- Retained existing stored settings, user metadata, database table names, API routes, JavaScript identifiers, and public integration hooks for backward compatibility.
- No intended changes to registration behavior.

## 1.0.29 - Centralized request boundary documentation
- Scoped PHPCS exceptions to the two deliberate raw-read operations. No validation or registration behavior was changed.
- Retained field-specific validation and stage-specific nonce/verified transaction checks.

## 1.0.29 - Centralized registration input boundary

- Centralized registration POST retrieval into allowlisted input accessor and scalar sanitizer.
- Initial registration ACF writes continue to require a verified nonce; verification-stage reads continue to require FluentAuth verification and HMAC-bound pending transaction.
- Replaced scattered direct POST reads in registration/ACF adapters.
- The bundled regression tests pass; live WordPress Plugin Check and registration regression remain required.

## 1.0.29 - Plugin Check compliance
- Migrated diagnostics to WP_Filesystem; documented best-effort non-atomic logging.
- Clarified scanner suppressions for FluentAuth-controlled registration lifecycle and dedicated pending table.
- Updated documentation metadata.

## 1.0.29 - WordPress.org compliance

- Removed custom Update URI header.
- Corrected translation domain and added translator comments.
- Hardened pending-table identifier quoting using WordPress prepared SQL identifiers.
- Updated readme metadata. Registration behavior unchanged.

## 1.0.24-dev - Developer Hooks settings reference
- Expand the read-only Developer Hooks tab with an exact public hook reference, parameter and return contracts, and safe examples.
- No registration or integration business logic changes.

## 1.0.23-dev
- Require explicit administrator acknowledgment for marketing consent under WPFusion integration.
- Suppress marketing checkbox and consent-tag adapter without the acknowledgment.
- Explain that this plugin never overrides WPFusion CRM subscription status.
- Document WPFusion consent responsibility in both readmes and installation guide.

## 1.0.23-dev
- FluentCommunity authentication redirect source URLs stay fixed; destinations editable as safe, site-relative paths.
- Reject external, self-referential and privileged redirect destinations.
- Document FluentAuth native/shortcode vs third-party form-builder limitations.
- Added destination security regression cases.

## 1.0.20-dev - Security and community auth compatibility
- Added optional default-off FluentCommunity native auth redirect to FluentAuth login and registration routes, with strict request scope and invitation/recovery exclusions.
- Enforced stricter server-side ACF choice type/cardinality rules; rejected dynamic/Other and conditional ACF fields for which safe server equivalence is not implemented.
- Rejected nested and excessive choice arrays and added standalone security regression tests.
- Updated installation instructions and release readiness notes. Not yet verified in a live WordPress installation.

## 1.0.19-dev - security review (not approved for release)
- Enforce selected ACF min/max and length constraints server-side.
- Cap pending registration payload size and field count.
- Remove dbDelta migration from routine init requests.
- Add installation verification and admin spinner isolation guidance.

## 1.0.18 - Development
- Remove our 12px horizontal ACF field inset so custom fields align with native FluentAuth input boundaries.
- Retain a gutter between adjacent ACF columns without changing FluentAuth form styles, and remove the gutter when stacked on mobile.
- Preserve existing registration, ACF, CRM, and consent behavior.

## 1.0.17 - Development
- Correct ACF checkbox/radio alignment within existing field columns, including checked state and dynamic Other input; do not override FluentAuth layout.
- Add unobtrusive APlusPlugins.com publisher link above settings tabs.
- Standardize user-facing WPFusion name and simplify CRM integration introduction.
- Display settings success in a nonblocking, five-second toast; keep errors visible.
- Mark inactive tabs hidden in PHP before JavaScript initializes to reduce initial layout flash.

## 1.0.16 - Development
- Center ACF radio and checkbox option groups while aligning options to the left; preserve responsive Other-input behavior.
- Clarify Direct FluentCRM opt-in, transactional status, and existing-contact protection on the Marketing tab.
- Allow ACF field identifiers and final CRM subscription statuses through the privacy-minimized diagnostic logger.
- Retain all existing diagnostic instrumentation and registration lifecycle hooks.

## 1.0.16 - Development
- Set new FluentCRM contacts to transactional without marketing consent or subscribed with explicit consent; preserve existing suppression states.
- Log final confirmed FluentCRM subscription status and marketing choice.
- Add ACF field key, name and type to per-field persistence diagnostics (without values).

## 1.0.16 (development)
- Fix ACF pending-data lookup by resolving email from the newly created WordPress user.
- Normalize accepted ACF date/time inputs to documented storage formats and verify each saved value.
- Preserve non-sensitive ACF field values on native registration validation errors.
- Hide ACF and optional marketing controls during the native verification-code stage.
- Retain pending data on persistence failure; improve field-level result diagnostics.

## 1.0.13 (development)
- Fix shortcode field placement: ACF fields and marketing checkbox now render before the Signup submit button, consistent with native registration.
- Preserve PHP server-side rendering and existing native registration behavior.

## 1.0.11-dev
- Render ACF fields and marketing consent in PHP for both shortcode and native registration.
- Use do_shortcode_tag with DOM parsing for shortcode insertion; remove the ACF markup AJAX endpoint and browser-side injection.
- Retain JavaScript for ACF widget activation, validation feedback and live email checks.

## 1.0.10-dev
- Fix marketing consent text wrapping in FluentAuth shortcode and native registration forms using an isolated two-column grid.
- Retain ACF JS/AJAX enhancement for shortcode form; native registration fields and consent use WordPress PHP hooks.

## 1.0.9-dev
- Support native FluentAuth/WordPress registration fields through the register_form, registration_errors, and register_post hooks.
- Carry pending ACF transaction ID through FluentAuth native email-code verification and persist user meta after account creation.
- Support the optional marketing checkbox on both native and shortcode registration forms.
- Correct ACF label/help spacing and checkbox wrapping with narrowly scoped CSS, preserving column widths.
- Improve native/shortcode browser diagnostics and log write failure reporting.

## 1.0.11 (development)
- Save settings asynchronously via authenticated WordPress REST API with WordPress option sanitization and capability enforcement.
- Timestamped success notice dismisses automatically after 30 seconds; failures remain visible.
- Preserve non-JavaScript Settings API fallback.

## 1.0.6-dev
- Show a clear no-field-groups message instead of an empty ACF selector.
- Clarify that ACF location rules control display, while APlusPlugins saves field values to WordPress user meta.
- Replace cramped CRM provider radios with responsive, accessible choice cards.

## 1.0.5-dev
- Added optional ACF field group integration and temporary registration table.
- Added configurable scheduled cleanup and table removal preferences.
- Pending schema verification and admin notification.

## 1.0.4 (development)
- Added Plugin Cleanup tab with separate, opt-in deletion of plugin settings and plugin-owned user metadata during uninstall only.
- Confirmed Settings API nonce protection via settings_fields(); added explicit admin-only guard for settings assets.
- Preserved settings and records upon deactivation.
- WP Fusion integration remains planned; not enabled in this release.

# Changelog

## 1.0.0 - 2026-10-30
- Initial development implementation of registration validators, settings, opt-in and FluentCRM adapter.

## 1.0.1 - 2026-10-30
- Reordered admin settings, added searchable removable tag pills, split registration and consent-triggered tags, and delegated lists to FluentCRM automations.

## 1.0.2 - 2026-10-30
- Fixed admin asset loading by using the actual hook suffix returned by WordPress submenu registration.
- Initialized admin controls at DOM readiness and kept native multi-selects as a no-JavaScript fallback.
- Redesigned the settings page with accessible tab navigation, full-width panels and a persistent save action.
- Refreshed plugin translation template.

### 1.0.4-dev
- Added exclusive CRM provider choice (direct FluentCRM, WP Fusion, hooks only, disabled).
- Added WP Fusion marketing-consent tag bridge, plus configuration warnings.
- Renamed ZeroBounce tab to Email Validation and expanded FluentCommunity guidance.
- No direct FluentCommunity provisioning; no forced WP Fusion contact creation.

- 1.0.13 development: secure event-log viewer, optional console tracing, detailed pending-table and ACF/CRM operation outcomes.

### Documentation preparation (no runtime version change)
- Replaced obsolete README and readme.txt instructions; added dependency matrix, full tab setup, consent semantics, screenshot manifest, source-grounded hook contracts and release-readiness/security review.

## 1.0.29 - Registration trust boundary review
- Added plugin-owned CSRF nonce to initial native and shortcode ACF registration submissions.
- Verify initial request nonce before temporary ACF payload storage; keep email-code verification and transaction binding as separate controls.
- Removed broad suppressions for registration data handling pending fresh PHPCS review.
