# Installation and verification

1. Back up the site and test this development package on staging.
2. Confirm FluentAuth (Fluent Security) is installed and active. ACF Pro, ZeroBounce, FluentCRM, WPFusion and FluentCommunity are optional and are only required for their matching integrations.
3. Upload the plugin ZIP in Plugins > Add New > Upload Plugin. Activate it, then open FluentAuth > Registration Extensions.
4. Configure marketing consent and CRM mode independently of the ACF field groups. Configure ZeroBounce only when using its provider, and enable FluentAuth email verification.
5. Select compatible ACF User Field Groups. Check WordPress user profiles after verification, not only the registration page.
6. Register a staging account using both native and shortcode forms; verify ACF persistence, consent, CRM status and tags, pending-table cleanup, and field error responses.
7. For admin loading/spinner problems, first temporarily deactivate this plugin and test the same WordPress admin page. Then check browser Developer Tools > Network for requests marked pending/failed, WordPress debug log, and Query Monitor; reactivate and compare. Never delete plugin data merely to diagnose a spinner.

## Site-specific code snippet

The code snippet referenced in the user request was not included with the message. Do not insert an unknown snippet. Add the reviewed snippet and its placement instructions here before release.


## Optional FluentCommunity authentication redirects (v1.0.21)

Under **FluentAuth > Registration Extensions > FluentCommunity**, enable **Redirect FluentCommunity login and registration entry points to FluentAuth** after configuring the destinations. Off by default.

The **source** routes are fixed and cannot be edited:

| Source URL | Editable destination (default) |
|---|---|
| `/community/?fcom_action=auth` | `/login/` |
| `/community/?fcom_action=auth&form=login` | Same login destination `/login/` |
| `/community?fcom_action=auth&form=register` | `/login/?action=register` |

You may set either destination to a **site-relative page URL** such as `/register/` or `/my-login/` if that page contains a supported FluentAuth registration/login shortcode. Enter a leading `/`, optional query parameters, and **no domain or protocol**. Absolute/external URLs, `/community/` self-redirects, WordPress admin/login endpoints, and suspicious paths are rejected. For custom FluentCommunity portal path use the documented `apls/fluentauth/community_portal_path` developer filter; only the two known authentication actions are captured. Invitation, reset, signed-in, POST and other stateful operations are excluded.

**Important limitations:** This plugin renders additional ACF registration field groups, ZeroBounce checks, and marketing consent **only on supported FluentAuth native and FluentAuth shortcode registration forms**. If you point a destination at a page using **LoginPress, FluentForms, or another form builder** for login or registration, that third-party form **will not acquire our ACF fields or marketing checkbox**. Integration of those fields and consent with other form builders is the site's developer's responsibility. A redirect controls navigation, **not** form capabilities. Merely creating a WordPress page does not provide FluentAuth integration; it must contain a supported FluentAuth shortcode.

For native mode, use `/login/` and `/login/?action=register`. For shortcode mode, publish your pages with the appropriate FluentAuth shortcodes and enter their paths in the destination fields. Test both URLs, reset and invitation flows, and the ACF/marketing behavior before deploying to production. No global `/wp-login.php` redirect is installed.

## ACF server-side security restrictions

Current registration support deliberately rejects ACF fields configured with conditional logic, Radio **Allow Other**, Select **Create Options**, saved custom choices, or dynamic AJAX options. These ACF features require matching server-side policy and are not suitable for the strict choice allowlist used here. Multi-select is supported with a predefined choice list. The plugin validates allowed choices and single/multiple cardinality server-side. Administrators must not use sensitive personal data in public registration field groups without assessing storage and privacy implications.


## ZeroBounce WordPress Registration overlap (important)

The ZeroBounce WordPress plugin has its own optional **WordPress Registration** validation integration. When APlusPlugins is configured to validate FluentAuth registrations through ZeroBounce, running both checks can duplicate API requests and may trigger a known warning in ZeroBounce 1.1.6 (`class-zerobounce-email-validator-public.php` line 378) when its validation response is null. Consider disabling ZeroBounce's separate WordPress Registration integration **only when every registration route you operate remains protected by another server-side validator**. Do not disable validation for third-party registration paths without replacement protection. APlusPlugins uses the provider's official API directly and does not change ZeroBounce's source code.

## Security and validation boundaries

All registration values are untrusted. The extension sanitizes supported ACF values before pending-table storage, rejects unknown/oversized field submissions and invalid configured predefined choices, and validates allowed web URL schemes. It does not run all third-party ACF custom validation hooks and cannot guarantee safety of downstream rendering; any custom frontend output must use context-appropriate WordPress escaping. Never configure ACF fields to collect passwords, financial details or other highly sensitive information. Prevent anonymous abuse using host-level controls and provider quotas. The WordPress registration and shortcode flows must be retested with real plugins before production.


### WPFusion marketing consent - administrator responsibility (v1.0.23)

In **CRM Integration > WPFusion**, marketing consent is available only after an administrator checks the explicit acknowledgement that WPFusion and its connected CRM own the contact's subscription status. The Marketing tab remains independent of the ACF group. If the acknowledgement is absent, this extension does not show its marketing checkbox in WPFusion mode or apply its consent-specific marketing tags. The settings API rejects attempts to enable the checkbox in WPFusion mode without acknowledgement.

**Important:** Checking this acknowledgement does **not** cause this extension to change WPFusion subscription status. WPFusion may create contacts as Subscribed, Pending, Unsubscribed or another status according to its CRM configuration. This extension does not enforce Transactional status outside Direct FluentCRM mode. Configure WPFusion/CRM defaults and verify both checked and unchecked registrations before enabling marketing consent. Unchecked consent never authorizes this extension to add marketing tags; it does not prevent WPFusion or other plugins from independently subscribing users.

Changing to WPFusion mode from an earlier plugin version leaves the checkbox suppressed until acknowledgement is saved. Switching back to Direct FluentCRM restores that integration's consent-aware status behavior.
