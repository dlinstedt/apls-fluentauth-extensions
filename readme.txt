=== APlusPlugins Registration Extensions for FluentAuth ===
Contributors: dlinstedt
Tags: fluentauth, registration, acf, fluentcrm, email-validation
Requires at least: 6.5
Requires PHP: 8.2
Tested up to: 7.1
Stable tag: 1.0.30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optional ACF signup fields, independent marketing consent, email validation and CRM tags for FluentAuth.

== Description ==

APlusPlugins Registration Extensions for FluentAuth augments FluentAuth (Fluent Security) registration without replacing login, passwords, verification or account creation. Works with native FluentAuth registration and supported signup shortcodes. You can use marketing consent without installing ACF, ZeroBounce, FluentCRM, WPFusion or FluentCommunity.

Features:
* Optional, unchecked marketing-consent checkbox independent of Terms and Conditions and ACF.
* Optional selected ACF field groups saved as WordPress user fields after email verification.
* Optional ZeroBounce quality checks using the ZeroBounce WordPress plugin's existing credentials and configured accepted statuses; live and server checks.
* Optional Direct FluentCRM contact creation, registration tags, marketing-only tags, and consent-aware subscription status.
* Optional WPFusion marketing-tag bridge or developer hooks.
* Admin diagnostics and scheduled cleanup of temporary registration values.

**Required:** WordPress 6.5+, PHP 8.2+, FluentAuth (Fluent Security). PHP DOM is required for shortcode field injection. Target versions tested: Fluent Security 3.1.0; ZeroBounce plugin 1.1.6 when enabled. ACF/ACF Pro, FluentCRM, WPFusion, FluentCommunity are **optional** and needed only for their respective capabilities. FluentCommunity tag-based auto access may require FluentCommunity Pro.

**Privacy:** When enabled, email quality checks send entered addresses to the configured ZeroBounce API. Selected ACF data is temporarily stored as unencrypted JSON in an APlusPlugins-owned database table until verification or expiration. Review privacy notices and do not collect high-sensitivity fields without appropriate protections.

**Limitations:** Built-in normalization does not execute every custom ACF validation hook, and live WordPress integration regression testing is still required. Test field rules and supported formats before production. No built-in mapping of ACF user fields to FluentCRM custom properties or FluentCommunity profiles. No direct Space/Course provisioning. See the full GitHub README and release-readiness document for details.

== Installation ==

1. Install and activate FluentAuth (Fluent Security); configure its public registration and verification flow. We recommend enabling FluentAuth **Validate Email** / email ownership verification, even though this integration currently requests verification through a filter.
2. Install/activate APlusPlugins Registration Extensions for FluentAuth. Find **FluentAuth > Registration Extensions**.
3. **Marketing:** enable or disable independent marketing opt-in; adjust the consent text.
4. **ACF Fields:** if using ACF or ACF Pro, create/publish compatible ACF Field Groups, select them here, and save. To display fields on **Users > Edit User**, also set the ACF group location to the appropriate User Form context.
5. **CRM Integration:** select Direct FluentCRM, WPFusion, Developer hooks only, or Disabled. Do not configure competing CRM contact creators.
6. **Email Validation:** choose None, ZeroBounce (with its separately installed WordPress plugin and configured API credentials/status policy), or a custom validator. Enable server enforcement if required; choose provider-error fail-open/fail-closed behavior.
7. **FluentCommunity:** configure Space/Course access separately in FluentCommunity's Access Management or FluentCRM automation; this plugin only assigns the selected registration tags.
8. Test native and shortcode registration, verification-code handling, ACF user-profile persistence, CRM status and tags, and no-consent behavior on staging.

== Frequently Asked Questions ==

= Is FluentCRM, ACF or ZeroBounce mandatory? =

No. Only FluentAuth is mandatory. Optional integrations require their own respective plugins and settings.

= Can I turn registration on or off here? =

No. Enable/disable public registration in FluentAuth / WordPress. This extension only adds form features and validation.

= Should FluentAuth's Validate Email setting be enabled? =

Yes, recommended. Verification-code processing remains FluentAuth's responsibility. Distinguish email ownership verification from ZeroBounce email quality checks.

= What is the difference between Terms and Conditions and marketing consent? =

Accepting Terms is not marketing consent. The marketing checkbox starts unchecked and is independent of ACF groups and registration eligibility.

= How does Direct FluentCRM set subscription status? =

For **new** contacts: checked marketing consent => `subscribed`, unchecked => `transactional`. Registration tags are assigned after verified registration; separate marketing tags require affirmative consent. Existing contacts retain their prior subscription/suppression state except certain unsuppressed pending/transactional contacts can be upgraded on fresh explicit consent. Marketing refusal is not a withdrawal of an existing subscription.

= How do I configure WPFusion? =

Connect its CRM in Settings > WP Fusion; configure global Create Contacts/registration tags. Choose WPFusion mode here and optional marketing-opt-in tags. Test with your CRM provider. WPFusion may independently create contacts even when you select a different mode in this extension.

= Which ACF fields are supported? =

Text, textarea, number, email, URL, select, radio, checkbox, true/false, date picker, date-time picker and time picker. Repeater, flexible content, relationship, image, file, group, clone and other non-allowlisted types are unsupported. Selected groups with unsupported types, conditional logic, or dynamic choices are rejected. Existing ACF user-form location rules control appearance in **Users > Edit User**; selected groups control signup injection. The extension applies strict server-side normalization and configured constraints but does not execute every custom ACF validation hook.

= Can ACF values sync to FluentCRM custom fields? =

Not directly in this plugin. WPFusion can map ACF user fields through Contact Fields if its documented ACF User Form setup is followed: https://wpfusion.com/documentation/integrations/advanced-custom-fields/ . No native, guaranteed registration-time ACF-to-FluentCRM custom mapping is supplied here.

= Does this plugin provision FluentCommunity members or profile fields? =

No. For FluentCommunity Pro with FluentCRM, use Settings > Access Management and map registration/access tags to Spaces/Courses. Never use marketing tags as access rights. ACF user fields do not automatically appear on FluentCommunity profiles.

= What happens when the plugin is removed? =

Deactivation removes its scheduled cleanup event and preserves most data; optional table-drop setting is destructive. Uninstall retention flags control options, APlusPlugins consent markers and pending table; WordPress users, FluentCRM contacts, community memberships and other plugin settings are never deleted by this extension.

= Does it use ZeroBounce API credits? =

When enabled, validation may request the external ZeroBounce HTTPS API using settings from the ZeroBounce WordPress plugin. Monitor the account, quotas and risk from public registration traffic.

== Screenshots ==

1. Marketing tab: independent consent and new-contact subscription behavior.
2. ACF Fields tab: select compatible field groups.
3. CRM Integration tab: Direct FluentCRM contact and tag settings.
4. Email Validation tab: provider and failure policy.
5. FluentCommunity tab: fixed source routes and editable same-site redirect destinations.
6. Developer Hooks tab: quick-reference hooks, parameters, return values and examples.
7. Plugin Cleanup tab: diagnostics and retention choices.
8. ACF field-group configuration, including User Form location rule.
9. Native FluentAuth registration with ACF fields and consent.
10. FluentAuth shortcode signup with sample ACF fields.
11. WordPress user profile displaying saved ACF fields after verification.

== Changelog ==

= 1.0.29 =
* WordPress.org compatibility: literal translation domain, translated placeholder comments, safe SQL identifier placeholders, and removal of custom update URI. No changes to registration behavior.

= 1.0.24 =
* Added Developer Hooks admin quick reference documenting public actions and filters, parameters, return contracts and examples.
* Retains the validated registration, ACF, ZeroBounce, Direct FluentCRM, WPFusion and FluentCommunity integrations.

= 1.0.23 =
* Added WPFusion consent-responsibility acknowledgment and fail-safe marketing checkbox gating.

= 1.0.22 =
* Strengthened server-side ACF input, choice, length, URL scheme and malformed-request validation.

= 1.0.21 =
* Added configurable same-site login and registration destinations for fixed FluentCommunity authentication URL sources.

= 1.0.20 =
* Added optional FluentCommunity authentication redirect and expanded server-side ACF choice validation.

== Upgrade Notice ==

= 1.0.29 =
WordPress.org compatibility release. Registration and integration behavior remain unchanged.
