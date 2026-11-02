# APlusPlugins Registration Extensions for FluentAuth

**Publisher:** [APlusPlugins](https://aplusplugins.com) | **Version:** 1.0.30 | **License:** GPL-2.0-or-later | **WordPress.org contributor:** `dlinstedt`

Extends **FluentAuth (Fluent Security)** registration with independent marketing consent, optionally selected Advanced Custom Fields (ACF) user fields, email-address quality checks, and an optional CRM adapter. FluentAuth remains the authority for account creation, password handling, email-code verification, and login. The extension does **not** replace FluentAuth, create its own login system, or require a CRM to operate.

> **Release status:** GitHub preparation / production candidate, **not security-certified or WordPress.org-approved**. See [release review](docs/RELEASE-READINESS.md) for gates still requiring validation. Native and shortcode flows have been tested on the maintainer's staging system, including eight ACF fields and both marketing-consent outcomes.

## Requirements and optional integrations

| Component | Required? | Role / notes |
| --- | --- | --- |
| WordPress 6.5+ and PHP 8.2+ | Yes | PHP DOM extension needed for shortcode form decoration; WordPress cron used for periodic cleanup. |
| FluentAuth / Fluent Security | **Yes** | Target integration tested with **3.1.0**; current plugin dependency slug `fluent-security`. |
| ACF or ACF Pro | No | Required **only** when selecting ACF registration field groups. ACF Pro is not a universal prerequisite. |
| ZeroBounce WordPress plugin | No | Required **only** for ZeroBounce-backed email quality checks; observed tested version 1.1.6. Service API usage may incur credits. |
| FluentCRM | No | Required **only** for direct contact/tag synchronization; alternative modes are WPFusion, developer hooks, or disabled. |
| WPFusion | No | Separate, optional CRM synchronization adapter; do not enable competing contact-creation strategies. |
| FluentCommunity / FluentCommunity Pro | No | Community membership is configured **outside** this plugin; tag-based automatic Space/Course access may require Pro. |

**Without optional plugins**, the extension can still show an independent marketing checkbox and expose developer events, and can use the `None` or external-filter email validation provider. Marketing consent is stored in WordPress metadata when affirmative, but no CRM is contacted unless configured. The form remains a FluentAuth form. **Do not select ACF groups when ACF is inactive.**

## Installation and essential FluentAuth settings

1. Back up the site and install/activate FluentAuth (Fluent Security). Enable and verify FluentAuth user registration on the appropriate registration route or shortcode page.
2. In **FluentAuth settings**, enable signup / public registration if you intend to accept registrations; make sure the default WordPress registration setting and any FluentAuth registration controls are consistent. Configure password policy, terms and conditions, email sender, login/redirect behavior, and spam controls in FluentAuth, not here.
3. **Recommended:** enable FluentAuth's **Validate Email / email-verification** switch. The integration currently registers a `fluent_auth/verify_signup_email` filter that returns true, but future compatibility should not depend on the present implementation detail. Test that a verification **code** is required before the user is created.
4. Install/activate this extension. Go to **FluentAuth > Registration Extensions**; configure each relevant tab below and **Save settings**.
5. Test both applicable entry points: FluentAuth's native registration (`wp-login.php?action=register` or the site's FluentAuth registration route) and the `[fluent_auth_signup]` shortcode. Some FluentAuth installations also use `[fluent_auth]`; verify the actual registration renderer when changing layouts or shortcodes.
6. Verify user metadata, verification-code completion, optional CRM status/tags, and community access after registration. Configure and test the no-consent path separately.

**Registration disabled?** Turn off public registration in FluentAuth / WordPress; this extension does **not** override that policy or provide an independent allow/deny registration switch. **Email-quality validation disabled?** Select `None`, or turn off **Enforce validation on registration submission**, according to your requirements. These are different settings from FluentAuth email *ownership verification*.

## Settings by tab

### Marketing

Enable the optional, **unchecked-by-default** marketing consent box and edit its label. The box is independent of ACF and appears between supplemental fields and the submit button. A person agreeing to Terms and Conditions has **not** opted into marketing. Declining marketing must never prevent account registration.

In **Direct FluentCRM** mode, an affirmative checkbox produces `subscribed` for a newly created contact; unchecked produces `transactional` for a newly created contact. Existing contacts preserve established subscribed/unsubscribed/suppressed states, with a narrowly defined upgrade for existing unsuppressed `pending` or `transactional` contacts after a fresh affirmative consent. This extension does not automatically remove a prior marketing tag when somebody leaves the box unchecked; handle future withdrawals through your CRM's normal unsubscribe process. Contact state is separate from account access. Marketing tags must **not** determine community entitlements.

### ACF Fields

1. Install and activate ACF or ACF Pro. In **ACF > Field Groups**, add a field group and publish it. Give every field a unique **Field Name** within the selected groups.
2. Select **FluentAuth > Registration Extensions > ACF Fields** and choose one or more field groups. ACF location rules **do not determine** which fields this plugin injects: selection here does. Unsupported fields in a selected group cause the group to be rejected, and runtime invalid configuration blocks signup and alerts the administrator.
3. For a field to show automatically when an administrator edits a WordPress user, configure the field group's **Location Rules** in ACF, e.g. **User Form = All** (or the intended user-form context). This is separate from registration-form injection. Open **Users > All Users > Edit** to see and edit the saved ACF data.
4. Configure ACF required flags, choices, display formats, and supported field-specific validation. Registration saves ACF values as WordPress **user meta**, using ACF `update_field()` after FluentAuth finishes verifying the email and creating the user. Values are keyed by ACF field keys, and ACF's WordPress-user convention uses `user_{id}`. The ACF group does **not** automatically become a FluentCommunity profile group.
5. Perform a staging test whenever changing selected field names, keys, types, or choices. In-progress registrations are tied to the selected field schema and expire after approximately **15 minutes**.

**Supported registration field types (source allowlist):** `text`, `textarea`, `number`, `email`, `url`, `select`, `radio`, `checkbox`, `true_false`, `date_picker`, `date_time_picker`, `time_picker`.

**Unsupported:** repeater, flexible content, group, clone, relationship, post object, user, taxonomy, gallery, file, image, WYSIWYG, Google Map, and any other ACF type not explicitly listed. Some ACF custom behavior (particularly dynamic "Other" choices and conditional rules) needs its own validation tests. Select and checkbox values must correspond to configured choices. Browser UI may enforce more constraints than current server-side normalization: see the release-readiness report before public production release.

**ACF date storage:** date-picker user meta is stored in ACF's canonical `Ymd` format regardless of the display/return format. Date/time controls use ACF-compatible normalized values. Empty and optional values must be tested separately from required fields.

### CRM Integration

Choose **exactly one extension CRM mode**:

- **Direct FluentCRM:** Activate FluentCRM; select Direct FluentCRM, enable **Synchronize completed registrations with FluentCRM**, choose **registration tags** (assigned after every verified registration), and optionally choose **marketing tags** (assigned only if marketing was explicitly checked). The extension creates/updates a contact after verified account creation. For a **new contact**: checked = `subscribed`; unchecked = `transactional`. It reads back the saved status for diagnostics. For existing contacts, the integration intentionally avoids reactivating unsubscribed, bounced, or otherwise suppressed contacts. FluentCRM lists are **not** assigned directly by the extension; use CRM automations triggered by registration tags where desired.
- **WPFusion:** Install/configure WPFusion and its CRM connection. Under **Settings > WP Fusion > General Settings**, configure **Create Contacts** and default registration tags. Select **WPFusion** in this plugin, choose marketing-opt-in tags here, and let WPFusion own contact creation and normal sync. The extension attempts to bridge **affirmative consent** tags after a connected CRM contact exists. It does **not** control WPFusion's global registration behavior or guarantee a recipient's newsletter subscription state for all supported CRM providers. Test the selected CRM provider and event timing.
- **Developer hooks only:** Do not run a bundled CRM adapter. Connect custom logic to the documented hooks.
- **Disabled:** No CRM adapter runs **from this plugin**. Other plugins can still react to user creation.

**Important:** If WPFusion and Direct FluentCRM are both active, WPFusion may create contacts independently. Disable its competing new-contact sync or use WPFusion mode to avoid double-processing.

**Mapping ACF fields into a CRM:** This extension intentionally does **not** map ACF registration values to FluentCRM custom contact fields. With WPFusion, use its documented [ACF integration](https://wpfusion.com/documentation/integrations/advanced-custom-fields/) and **Contact Fields** mappings; make sure the ACF field group also appears on a WordPress **User Form**, as WPFusion requires for automatic discovery. The WPFusion documentation describes syncing on **profile updates**, so do not assume values will sync automatically at the precise registration hook in every setup; test and/or trigger an authorized profile resync. Without WPFusion, no general, reliable no-code registration-time ACF-to-FluentCRM mapping is implemented here. FluentCRM supports custom contact properties in automations, but do **not** assume that it can read arbitrary ACF user meta and map it correctly without a verified SmartCode/source; use custom code with the documented hook API if this is required.

### Email Validation

This is **email quality/deliverability** validation, different from FluentAuth's verification-code check for email ownership.

- **None:** no external quality-provider API request (ordinary `is_email()` validation still applies where checked).
- **ZeroBounce WordPress settings:** install and configure ZeroBounce's WordPress plugin, including its API key, API zone, permitted statuses, free-email blocking, message, and timeout **there**. This extension consumes those options and calls the service over HTTPS using its own validation request; credentials are **not** configured or duplicated here. Verify which accepted statuses (e.g. catch-all/unknown) are permitted in your ZeroBounce plugin's settings.
- **Custom validation via filter:** integrate an external validator by implementing `apls/fluentauth/validate_email` and returning the exact contract shown in [developer hooks](docs/DEVELOPER-HOOKS.md).
- **Live validation:** asynchronous checks for supported shortcode forms. **Server validation:** authoritative check at form submission; enable this for meaningful enforcement. Native and shortcode registration should be retested when changing FluentAuth versions.
- **Provider unavailable:** `Block and retry` is fail-closed; `Continue to FluentAuth email verification` is fail-open for provider/API errors. Email verification is not a substitute for validation, and quality checks are not perfect. This plugin's public AJAX validator has a per-IP request limiter, but service-call quotas and abuse mitigation still require production monitoring.

**External data disclosure:** When ZeroBounce validation is enabled, the entered email address is transmitted to ZeroBounce's configured HTTPS endpoint. Review your privacy notice, processing basis, and ZeroBounce account terms; live validation may use paid API credits.

### FluentCommunity

**No direct Space creation, membership insertion, or ACF-to-profile synchronization is performed by this extension.** FluentCommunity itself is optional.

**Direct FluentCRM mode:** For automatic tag-linked access, install FluentCommunity Pro where the feature is available; go to **FluentCommunity > Settings > Access Management**, enable **Access Management with FluentCRM**, map a **registration/access tag** to a Space or Course, enable **Sync this tag with Space/Course access**, and save. Alternatively, use the **FluentCRM automation** actions for FluentCommunity Spaces/Courses. Review any **Create WordPress User** or **Create FluentCRM Contact** switches to avoid account/contact duplication. Never use the marketing-consent tag as an entitlement. [FluentCommunity's documented tag mapping](https://docs.fluentcommunity.co/managing-access-with-fluentcrm-tags).

**WPFusion mode:** Configure linked CRM tags and content/Space access in WPFusion and FluentCommunity according to their installed versions. This plugin only applies optional marketing-consent tags after verified signup; it does **not** grant community access directly.

**Custom profile data:** ACF user fields are **not** automatically displayed on a FluentCommunity member's profile or copied into FluentCommunity custom profile fields. A separate integration with supported field mappings, permission checks, and profile visibility rules would be required.

### Developer Hooks

See [docs/DEVELOPER-HOOKS.md](docs/DEVELOPER-HOOKS.md) for every hook, exact parameters, expected result types, timing, examples, and failure behavior. Action callbacks do not return values into the registration engine; only the email-validation **filter** consumes a returned result.

### Plugin Cleanup

Temporarily enable registration diagnostics (Basic or Detailed) for troubleshooting. Logs are written to protected `wp-content/apls-faext-logs/events.php`, readable from the admin-only log viewer. They omit submitted field values, email addresses, passwords, verification codes, and API secrets. Disable diagnostics after testing; assess log-directory access under your web server.

A temporary `wp_apls_faext_pending`-style table (actual prefix varies) stores **submitted ACF values**, including any personal data, as JSON until verification or expiry; it is **not encrypted at rest**. Records expire after about 15 minutes; WP-Cron cleanup defaults to daily near site-local midnight and can be rescheduled. The table can be dropped on deactivation **only if expressly selected**; uninstall cleanup is configurable. No user account, FluentCRM contact, or FluentCommunity membership is deleted on plugin uninstall. Consider backups and your organization's data retention policies.

## Screenshots - **11 screenshots - upload separately**

Create PNG files (redact emails, names, credentials, API keys, access tokens and user IDs if identifying). Every screenshot has a stable filename and order; **do not ship placeholders as real screenshots**.

| # | File in `assets/screenshots/` | Capture |
| --- | --- | --- |
| 1 | `screenshot-1.png` | Marketing tab - marketing consent configuration and explanation |
| 2 | `screenshot-2.png` | ACF Fields tab - selected registration groups |
| 3 | `screenshot-3.png` | CRM Integration tab - Direct FluentCRM and tag selections |
| 4 | `screenshot-4.png` | Email Validation tab - provider and failure policy |
| 5 | `screenshot-5.png` | FluentCommunity tab - fixed sources and editable redirect destinations |
| 6 | `screenshot-6.png` | Developer Hooks tab - visible extension-point summary |
| 7 | `screenshot-7.png` | Plugin Cleanup tab - diagnostics and retention controls |
| 8 | `screenshot-8.png` | ACF Field Group editor - sample group fields, types, required flags, and user-form location rule |
| 9 | `screenshot-9.png` | Native FluentAuth registration with ACF and marketing consent |
| 10 | `screenshot-10.png` | Shortcode signup with ACF fields, including choices / date picker |
| 11 | `screenshot-11.png` | Completed registration - ACF fields visible in WordPress user profile |

On GitHub, images are referenced from `assets/screenshots/`. For WordPress.org publication, move/copy the numbered images to **SVN root `assets/`**, alongside `trunk/` and `tags/`, and keep the `readme.txt` screenshot list in matching order. WordPress.org plugin screenshots must **not** be copied into the installed plugin's `trunk/assets/` only.

## Security and compatibility boundaries

- Never use this extension as the sole anti-abuse control: protect the site with FluentAuth policies, security controls, rate limits, logging and service budgets.
- Do not store highly sensitive ACF registration fields (financial, medical, government IDs) in the temporary pending table; its JSON is not encrypted at rest.
- ACF registration capture uses a restricted type allowlist and server-side type, size, choice and supported constraint validation. Custom ACF validation callbacks are not automatically executed; see release review.
- This plugin currently hooks FluentAuth email validation to force verification (`__return_true`); treat that as an integration compatibility decision that must be regression tested.
- DOMDocument/PHP DOM is required for shortcode registration HTML decoration. Theme, FluentAuth markup, shortcode, or version changes can affect insertion.
- Live email validation endpoint consumes provider API credits; monitor quota, misuse and per-IP throttling. Email-quality failures may block registration depending on configured policy.
- `register_form` and shortcode integrations are the supported paths. Social/signup plugins, third-party forms, and alternate verification flows are **not** implicitly covered.
- Data and consent policy implementation must be reviewed for applicable law; a checkbox alone is not a complete compliance program.

## Documentation

- [Developer hook contracts and examples](docs/DEVELOPER-HOOKS.md)
- [Release readiness and security review](docs/RELEASE-READINESS.md)
- [Current release qualification status](docs/RELEASE-STATUS-1.0.29.md)
- [Changelog](CHANGELOG.md)
- [Official WPFusion ACF integration](https://wpfusion.com/documentation/integrations/advanced-custom-fields/)
- [Official FluentCommunity FluentCRM tag access](https://docs.fluentcommunity.co/managing-access-with-fluentcrm-tags)
- [Official WordPress.org readme guide](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)

## License and contributions

GPL-2.0-or-later. Issues and pull requests can be reviewed through the project's GitHub repository once published. Source distribution does not imply that compatibility has been certified for third-party versions other than those specifically tested.


Installation and environment validation: see [`docs/INSTALLATION.md`](docs/INSTALLATION.md). No site-specific redirect snippet is required: the optional FluentCommunity auth redirect has editable same-site destinations.


### FluentCommunity auth redirect and strict ACF choices (v1.0.20)

An optional, default-off FluentCommunity tab setting redirects standard community login and registration entry-point GET requests to FluentAuth's native `/login/` forms. It does not intercept invitations, recovery/reset tokens, POST requests, or other URL parameters. See [Installation](docs/INSTALLATION.md#optional-fluentcommunity-authentication-redirect-v1020).

Selected ACF groups cannot use conditional visibility, dynamic/AJAX-created options, or Radio custom Other choices in this strict server-validation mode. Radio, Select, Multi-Select, and Checkbox membership and cardinality are enforced on the server. The plugin rejects unsupported ACF configuration instead of accepting values that cannot be independently verified. Automated standalone cases: `php tests/security-regression.php`.

### Redirect destinations and form-builder limitations

The FluentCommunity integration tab displays fixed login and registration **source URLs** and editable, same-site **destination paths**. Defaults are `/login/` and `/login/?action=register`; destinations may instead point to pages containing supported FluentAuth shortcodes. See [Installation and redirect configuration](docs/INSTALLATION.md).

**Third-party form limitation:** ACF field groups and the marketing checkbox are rendered only in supported **FluentAuth native registration and FluentAuth shortcode registration forms**. Registration/login forms built with **LoginPress, FluentForms, or any other form builder do not automatically receive these fields**, even when a redirect points to them. Implementing equivalent fields, server validation, storage, and consent in those builders is the website developer's responsibility.


## ZeroBounce WordPress Registration overlap (important)

The ZeroBounce WordPress plugin has its own optional **WordPress Registration** validation integration. When APlusPlugins is configured to validate FluentAuth registrations through ZeroBounce, running both checks can duplicate API requests and may trigger a known warning in ZeroBounce 1.1.6 (`class-zerobounce-email-validator-public.php` line 378) when its validation response is null. Consider disabling ZeroBounce's separate WordPress Registration integration **only when every registration route you operate remains protected by another server-side validator**. Do not disable validation for third-party registration paths without replacement protection. APlusPlugins uses the provider's official API directly and does not change ZeroBounce's source code.

## Security and validation boundaries

All registration values are untrusted. The extension sanitizes supported ACF values before pending-table storage, rejects unknown/oversized field submissions and invalid configured predefined choices, and validates allowed web URL schemes. It does not run all third-party ACF custom validation hooks and cannot guarantee safety of downstream rendering; any custom frontend output must use context-appropriate WordPress escaping. Never configure ACF fields to collect passwords, financial details or other highly sensitive information. Prevent anonymous abuse using host-level controls and provider quotas. The WordPress registration and shortcode flows must be retested with real plugins before production.


### WPFusion marketing consent - administrator responsibility (v1.0.23)

In **CRM Integration > WPFusion**, marketing consent is available only after an administrator checks the explicit acknowledgement that WPFusion and its connected CRM own the contact's subscription status. The Marketing tab remains independent of the ACF group. If the acknowledgement is absent, this extension does not show its marketing checkbox in WPFusion mode or apply its consent-specific marketing tags. The settings API rejects attempts to enable the checkbox in WPFusion mode without acknowledgement.

**Important:** Checking this acknowledgement does **not** cause this extension to change WPFusion subscription status. WPFusion may create contacts as Subscribed, Pending, Unsubscribed or another status according to its CRM configuration. This extension does not enforce Transactional status outside Direct FluentCRM mode. Configure WPFusion/CRM defaults and verify both checked and unchecked registrations before enabling marketing consent. Unchecked consent never authorizes this extension to add marketing tags; it does not prevent WPFusion or other plugins from independently subscribing users.

Changing to WPFusion mode from an earlier plugin version leaves the checkbox suppressed until acknowledgement is saved. Switching back to Direct FluentCRM restores that integration's consent-aware status behavior.


### Developer Hooks tab quick reference

The **Developer Hooks** tab displays a read-only table of the public actions and filters, expandable parameter and return contracts, and small code examples. For full lifecycle guarantees and integration caveats see [`docs/DEVELOPER-HOOKS.md`](docs/DEVELOPER-HOOKS.md).


## Screenshot gallery (maintainer images)

The 11 screenshots are uploaded separately to [`assets/screenshots/`](assets/screenshots/README.md). Use the exact filenames in the manifest above. For WordPress.org, place copies in the SVN repository root `assets/` directory.


WordPress.org compatibility notes: see [Plugin Check remediation](docs/PLUGIN-CHECK-REMEDIATION.md).

### v1.0.29 security input boundary

See [security input-boundary notes](docs/SECURITY-BOUNDARY-1.0.29.md). Staging verification is required before release.
