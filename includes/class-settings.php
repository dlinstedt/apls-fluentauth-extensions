<?php
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Settings {
    private static string $page_hook = '';
    public static function defaults(): array {
        return ['debug_registration' => 0, 'debug_console' => 0, 'debug_detail' => 'basic', 'marketing' => 1, 'wpf_consent_ack' => 0, 'marketing_text' => 'Send me occasional technical articles, community events, and training updates. I can unsubscribe at any time.', 'live' => 1, 'server' => 1, 'provider' => 'zerobounce', 'on_error' => 'block', 'crm' => 0, 'crm_provider' => 'fluentcrm', 'wpf_marketing_tags' => [], 'crm_tags' => [], 'crm_marketing_tags' => [], 'delete_settings_on_uninstall' => 0, 'delete_consent_on_uninstall' => 0, 'acf_groups' => [], 'cleanup_every' => 1, 'cleanup_unit' => 'days', 'drop_pending_on_deactivate' => 0, 'drop_pending_on_uninstall' => 1, 'community_auth_redirect' => 0, 'community_login_destination' => '/login/', 'community_register_destination' => '/login/?action=register'];
    }
    /** Only collect marketing consent in WPFusion mode after administrator acknowledgement. */
    public static function marketing_enabled(array $options): bool {
        if (empty($options['marketing'])) { return false; }
        return ($options['crm_provider'] ?? 'fluentcrm') !== 'wpfusion' || !empty($options['wpf_consent_ack']);
    }
    public static function register(): void {
        add_action('admin_menu', [self::class,'menu'], 30);
        add_action('admin_init', [self::class,'setup']);
        add_action('admin_enqueue_scripts', [self::class,'enqueue_admin']);
        add_action('rest_api_init', [self::class, 'register_rest_routes']);
    }
    public static function menu(): void {
        self::$page_hook = add_submenu_page('fluent-auth', __('Registration Extensions', 'aplusplg-fluentauth-extensions'), __('Registration Extensions', 'aplusplg-fluentauth-extensions'), 'manage_options', 'aplusplg-fluentauth-extensions', [self::class, 'render']);
    }
    public static function register_rest_routes(): void {
        Diagnostics::register_routes();
        register_rest_route('apls-fluentauth/v1', '/settings', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [self::class, 'rest_save'],
            'permission_callback' => static function (): bool { return current_user_can('manage_options'); },
            'args'                => [
                'options' => ['required' => true, 'type' => 'object'],
            ],
        ]);
    }
    public static function rest_save(\WP_REST_Request $request) {
        $input = $request->get_param('options');
        if (!is_array($input)) {
            return new \WP_Error('apls_invalid_options', __('Invalid settings payload.', 'aplusplg-fluentauth-extensions'), ['status' => 400]);
        }
        // Reject an incompatible ACF group before saving any of the submitted settings.
        if (ACF_Adapter::active()) {
            $groups = array_values(array_unique(array_map('sanitize_text_field', (array) ($input['acf_groups'] ?? []))));
            $errors = ACF_Adapter::validate_groups($groups);
            if ($errors) {
                return new \WP_Error('apls_acf_invalid', implode(' ', $errors), ['status' => 422]);
            }
        }
        foreach (['community_login_destination', 'community_register_destination'] as $key) {
            if (array_key_exists($key, $input) && Community_Redirect::sanitize_destination($input[$key]) === '') {
                return new \WP_Error('apls_invalid_redirect', __('Redirect destinations must be safe site-relative URLs outside the FluentCommunity authentication route.', 'aplusplg-fluentauth-extensions'), ['status' => 422]);
            }
        }
        $provider = sanitize_key((string) ($input['crm_provider'] ?? Plugin::options()['crm_provider']));
        if ($provider === 'wpfusion' && !empty($input['marketing']) && empty($input['wpf_consent_ack'])) {
            return new \WP_Error('apls_wpf_consent_ack_required', __('To display marketing consent in WPFusion mode, acknowledge that WPFusion and its CRM independently control subscription status and must be configured to prevent marketing without consent.', 'aplusplg-fluentauth-extensions'), ['status' => 422]);
        }
        // REST requests do not necessarily run admin_init/register_setting.
        // Call the same sanitizer used by options.php, then persist sanitized values.
        $sanitized = self::sanitize($input);
        update_option('apls_faext_options', $sanitized);
        $saved = Plugin::options();
        return rest_ensure_response([
            'success' => true,
            'message' => __('Settings successfully saved', 'aplusplg-fluentauth-extensions'),
            'saved_at' => wp_date(get_option('date_format') . ' ' . get_option('time_format')),
            'options' => $saved,
        ]);
    }
    public static function setup(): void {
        register_setting('apls_faext_group', 'apls_faext_options', ['type'=>'array', 'sanitize_callback'=>[self::class,'sanitize'], 'default'=>self::defaults()]);
    }
    public static function sanitize($input): array {
        $old = Plugin::options(); $in = is_array($input) ? $input : [];
        $old['debug_registration'] = empty($in['debug_registration']) ? 0 : 1;
        $old['debug_console'] = empty($in['debug_console']) ? 0 : 1;
        $old['debug_detail'] = ($in['debug_detail'] ?? 'basic') === 'detailed' ? 'detailed' : 'basic';
        $old['marketing'] = empty($in['marketing']) ? 0 : 1;
        $old['wpf_consent_ack'] = empty($in['wpf_consent_ack']) ? 0 : 1;
        $old['community_auth_redirect'] = empty($in['community_auth_redirect']) ? 0 : 1;
        foreach (['community_login_destination', 'community_register_destination'] as $destination_key) {
            if (array_key_exists($destination_key, $in)) {
                $candidate = Community_Redirect::sanitize_destination($in[$destination_key]);
                if ($candidate === '') {
                    add_settings_error('apls_faext_options', $destination_key, __('Enter a valid site-relative URL beginning with /, outside the FluentCommunity authentication route.', 'aplusplg-fluentauth-extensions'), 'error');
                } else {
                    $old[$destination_key] = $candidate;
                }
            }
        }
        $old['marketing_text'] = sanitize_textarea_field($in['marketing_text'] ?? '');
        $old['live'] = empty($in['live']) ? 0 : 1;
        $old['server'] = empty($in['server']) ? 0 : 1;
        $old['provider'] = in_array($in['provider'] ?? '', ['zerobounce','external','none'], true) ? $in['provider'] : 'none';
        $old['on_error'] = in_array($in['on_error'] ?? '', ['block','continue'], true) ? $in['on_error'] : 'block';
        $old['delete_settings_on_uninstall'] = empty($in['delete_settings_on_uninstall']) ? 0 : 1;
        $old['delete_consent_on_uninstall'] = empty($in['delete_consent_on_uninstall']) ? 0 : 1;
        $mode = sanitize_key($in['crm_provider'] ?? $old['crm_provider']);
        if (in_array($mode, ['fluentcrm', 'wpfusion', 'custom', 'none'], true)) {
            $old['crm_provider'] = $mode;
        }
        if ($old['crm_provider'] === 'wpfusion' && !empty($old['marketing']) && empty($old['wpf_consent_ack'])) {
            add_settings_error('apls_faext_options', 'wpf_consent_ack', __('WPFusion consent responsibility acknowledgment is required before marketing consent can be displayed.', 'aplusplg-fluentauth-extensions'), 'error');
        }
        if (Plugin::wpf_active()) {
            $available = (array) wpf_get_option('available_tags', []);
            $old['wpf_marketing_tags'] = array_values(array_filter(array_map('strval', (array)($in['wpf_marketing_tags'] ?? [])), static function ($v) use ($available) { return array_key_exists($v, $available); }));
        }
        if (Plugin::crm_active()) {
            $old['crm'] = empty($in['crm']) ? 0 : 1;
            $old['crm_tags'] = self::ids($in['crm_tags'] ?? []);
            $old['crm_marketing_tags'] = self::ids($in['crm_marketing_tags'] ?? []);
        }
        $old['drop_pending_on_deactivate'] = empty($in['drop_pending_on_deactivate']) ? 0 : 1;
        $old['drop_pending_on_uninstall'] = empty($in['drop_pending_on_uninstall']) ? 0 : 1;
        $old['cleanup_every'] = max(1,min(365,absint($in['cleanup_every'] ?? 1)));
        $old['cleanup_unit'] = in_array($in['cleanup_unit'] ?? '', ['minutes','hours','days','weeks','months'],true) ? $in['cleanup_unit'] : 'days';
        if (ACF_Adapter::active()) {
            $groups = array_values(array_unique(array_map('sanitize_text_field',(array)($in['acf_groups'] ?? []))));
            $errors = ACF_Adapter::validate_groups($groups);
            if ($errors) { add_settings_error('apls_faext_options','acf_invalid',implode(' ', $errors),'error'); }
            else { $old['acf_groups']=$groups; }
        }
        Cleanup::reschedule($old);
        return $old;
    }
    private static function ids($ids): array { return array_values(array_unique(array_filter(array_map('absint', (array)$ids)))); }
    private static function check(string $key, string $label, array $opts, bool $disabled=false): void {
        printf('<label><input type="checkbox" name="apls_faext_options[%1$s]" value="1" %2$s %3$s> %4$s</label><br>', esc_attr($key), checked(!empty($opts[$key]), true, false), disabled($disabled, true, false), esc_html($label));
    }
    public static function enqueue_admin(string $hook): void {
        if (!is_admin() || !$hook || $hook !== self::$page_hook) { return; }
        $js = 'assets/js/admin.js';
        $css = 'assets/css/admin.css';
        wp_enqueue_script('apls-faext-admin', plugins_url($js, FILE), [], (file_exists(dirname(FILE) . '/' . $js) ? filemtime(dirname(FILE) . '/' . $js) : VERSION), true);
        wp_enqueue_style('apls-faext-admin', plugins_url($css, FILE), [], (file_exists(dirname(FILE) . '/' . $css) ? filemtime(dirname(FILE) . '/' . $css) : VERSION));
        wp_add_inline_script('apls-faext-admin', 'window.aplsFaextAdmin=' . wp_json_encode([
            'search' => __('Search tags...', 'aplusplg-fluentauth-extensions'),
            'none' => __('No matching tags', 'aplusplg-fluentauth-extensions'),
            'remove' => __('Remove tag', 'aplusplg-fluentauth-extensions'),
            'endpoint' => esc_url_raw(rest_url('apls-fluentauth/v1/settings')),
            'diagnosticsEndpoint' => esc_url_raw(rest_url('apls-fluentauth/v1/diagnostics')),
            'nonce' => wp_create_nonce('wp_rest'),
            'saving' => __('Saving...', 'aplusplg-fluentauth-extensions'),
            'saveFailed' => __('Settings could not be saved. Please try again.', 'aplusplg-fluentauth-extensions'),
        ]) . ';', 'before');
    }
    /** Display a read-only quick reference for documented public extension points. */
    private static function render_developer_hooks(): void {
        echo '<h2>' . esc_html__('Developer extension points', 'aplusplg-fluentauth-extensions') . '</h2>';
        echo '<p class="apls-faext-intro">' . esc_html__('Read-only quick reference for the public hooks provided by this plugin. Actions ignore callback return values; filters must return the documented type. Registration hooks are not transactional and must not be treated as delivery guarantees.', 'aplusplg-fluentauth-extensions') . '</p>';
        $hooks = [
            [ 'apls/fluentauth/validate_email', 'Filter', 'array $result, string $email, string $phase', 'array: allowed (bool), status (string), provider (string), message (string)', 'Modify an email validation result. Phase is live or server; cached results may also pass through this filter.' ],
            [ 'apls/fluentauth/email_validated', 'Action', 'string $email, array $result, string $phase', 'None (ignored)', 'Uncached email validation finished. Not guaranteed for cached or locally rejected email addresses.' ],
            [ 'apls/fluentauth/registration_validated', 'Action', 'array $form_data', 'None (ignored)', 'FluentAuth shortcode registration has passed validation. Data may contain sensitive information.' ],
            [ 'apls/fluentauth/acf_fields_saved', 'Action', 'int $user_id, array $field_keys', 'None (ignored)', 'All selected ACF values were read back successfully and the pending record was deleted. Field keys, not values, are supplied.' ],
            [ 'apls/fluentauth/consent_recorded', 'Action', 'int $user_id, array $context', 'None (ignored)', 'Affirmative marketing consent was recorded after verified registration.' ],
            [ 'apls/fluentauth/registration_completed', 'Action', 'int $user_id, array $context', 'None (ignored)', 'Verified registration completed; does not guarantee that CRM sync succeeded.' ],
            [ 'apls/fluentauth/crm_sync_completed', 'Action', 'int $user_id, mixed $result, array $context', 'None (ignored)', 'CRM adapter reported success. The second parameter varies by integration; do not assume a contact object.' ],
            [ 'apls/fluentauth/crm_sync_failed', 'Action', 'int $user_id, string $reason', 'None (ignored)', 'CRM adapter reported a failure; reason may include sensitive exception details.' ],
            [ 'apls/fluentauth/community_portal_path', 'Filter', 'string $portal_path', 'string: safe site-relative community path', 'Override the recognized FluentCommunity portal path (default /community). Applies only when optional auth redirection is enabled.' ],
        ];
        echo '<div class="apls-faext-hook-table-wrap"><table class="widefat striped apls-faext-hook-table"><thead><tr><th scope="col">' . esc_html__('Hook', 'aplusplg-fluentauth-extensions') . '</th><th scope="col">' . esc_html__('Type', 'aplusplg-fluentauth-extensions') . '</th><th scope="col">' . esc_html__('Purpose', 'aplusplg-fluentauth-extensions') . '</th></tr></thead><tbody>';
        foreach ($hooks as $hook) {
            echo '<tr><td><code>' . esc_html($hook[0]) . '</code></td><td>' . esc_html($hook[1]) . '</td><td>' . esc_html($hook[4]) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<h3>' . esc_html__('Parameters and examples', 'aplusplg-fluentauth-extensions') . '</h3>';
        foreach ($hooks as $hook) {
            echo '<details class="apls-faext-hook-detail"><summary><code>' . esc_html($hook[0]) . '</code> <span class="apls-faext-hook-kind">(' . esc_html($hook[1]) . ')</span></summary>';
            echo '<p><strong>' . esc_html__('Parameters:', 'aplusplg-fluentauth-extensions') . '</strong> <code>' . esc_html($hook[2]) . '</code></p>';
            echo '<p><strong>' . esc_html__('Return contract:', 'aplusplg-fluentauth-extensions') . '</strong> ' . esc_html($hook[3]) . '</p>';
            echo '<p>' . esc_html($hook[4]) . '</p>';
            echo '</details>';
        }
        echo '<h3>' . esc_html__('Example: confirmed registration', 'aplusplg-fluentauth-extensions') . '</h3>';
        $sample = "add_action('apls/fluentauth/registration_completed', function (int \$user_id, array \$context): void {\n    // Perform an idempotent action; never assume CRM synchronization succeeded.\n}, 10, 2);";
        echo '<pre class="apls-faext-hook-example"><code>' . esc_html(str_replace('\\$', '$', $sample)) . '</code></pre>';
        echo '<h3>' . esc_html__('Example: custom email validation', 'aplusplg-fluentauth-extensions') . '</h3>';
        $sample = "add_filter('apls/fluentauth/validate_email', function (array \$result, string \$email, string \$phase): array {\n    // Validate externally as needed. Return the complete result array.\n    return \$result;\n}, 10, 3);";
        echo '<pre class="apls-faext-hook-example"><code>' . esc_html(str_replace('\\$', '$', $sample)) . '</code></pre>';
        echo '<p class="description">' . esc_html__('Full reference (including lifecycle, privacy and compatibility caveats): docs/DEVELOPER-HOOKS.md in the plugin package. Avoid processing registration passwords or logging form data.', 'aplusplg-fluentauth-extensions') . '</p>';
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'aplusplg-fluentauth-extensions')); }
        $o=Plugin::options(); $zb=Plugin::zb_active(); $crm=Plugin::crm_active(); $community=Plugin::community_active(); $wpf=Plugin::wpf_active();
        echo '<div class="wrap apls-faext-settings"><header class="apls-faext-heading"><div><h1>' . esc_html__('FluentAuth Registration Extensions', 'aplusplg-fluentauth-extensions') . '</h1><p>' . esc_html__('Configure registration consent, integrations, and developer extension points.', 'aplusplg-fluentauth-extensions') . '</p><p class="apls-faext-publisher">' . esc_html__('Discover more WordPress extensions, developer resources, and our community at', 'aplusplg-fluentauth-extensions') . ' <a href="https://aplusplugins.com" target="_blank" rel="noopener noreferrer">APlusPlugins.com</a>.</p></div><span class="apls-faext-version">v' . esc_html(VERSION) . '</span></header><div id="apls-faext-save-notice" class="apls-faext-save-notice" role="status" aria-live="polite" hidden></div><form action="options.php" method="post" id="apls-faext-settings-form">';
        settings_fields('apls_faext_group');
        echo '<nav class="apls-faext-tabs" aria-label="' . esc_attr__('Registration extension settings', 'aplusplg-fluentauth-extensions') . '">';
        foreach (['marketing'=>__('Marketing Checkbox', 'aplusplg-fluentauth-extensions'),'acf'=>__('ACF Fields', 'aplusplg-fluentauth-extensions'),'crm'=>__('CRM Integration', 'aplusplg-fluentauth-extensions'),'zerobounce'=>__('Email Validation', 'aplusplg-fluentauth-extensions'),'community'=>__('FluentCommunity', 'aplusplg-fluentauth-extensions'),'developer'=>__('Developer Hooks', 'aplusplg-fluentauth-extensions'),'cleanup'=>__('Plugin Cleanup', 'aplusplg-fluentauth-extensions')] as $id=>$label) { printf('<button type="button" class="apls-faext-tab%s" data-apls-tab="%s" aria-selected="%s">%s</button>', $id === 'marketing' ? ' is-active' : '', esc_attr($id), $id === 'marketing' ? 'true' : 'false', esc_html($label)); }
        echo '</nav><main class="apls-faext-panel-wrap"><section class="apls-faext-panel" data-apls-panel="marketing"><h2>' . esc_html__('Marketing checkbox', 'aplusplg-fluentauth-extensions') . '</h2><p class="apls-faext-intro">' . esc_html__('Optional marketing consent, independent of account registration.', 'aplusplg-fluentauth-extensions') . '</p>'; 
        self::check('marketing', __('Show optional marketing opt-in checkbox', 'aplusplg-fluentauth-extensions'), $o);
        echo '<p><label for="apls-marketing">' . esc_html__('Opt-in language', 'aplusplg-fluentauth-extensions') . '</label></p><textarea class="large-text" rows="3" id="apls-marketing" name="apls_faext_options[marketing_text]">' . esc_textarea($o['marketing_text']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Consent is optional and unchecked by default. FluentAuth signup email verification remains enforced.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<p class="apls-faext-guidance">' . esc_html__('WPFusion: the checkbox records an affirmative choice and can apply selected marketing tags, but does not control CRM subscription status. Enabling it in WPFusion mode requires an administrator acknowledgment in CRM Integration. Without that acknowledgment it is not shown on registration.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<p class="apls-faext-guidance">' . esc_html__('Direct FluentCRM: A new contact is created as Subscribed only when the marketing checkbox is checked. If left unchecked, a new contact is created as Transactional (operational messages only, no marketing campaigns). Existing contacts retain their established subscription or suppression status.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '</section><section class="apls-faext-panel" data-apls-panel="acf" hidden><h2>' . esc_html__('ACF registration fields','aplusplg-fluentauth-extensions') . '</h2>';
        echo '<p class="apls-faext-intro">' . esc_html__('Select an existing ACF field group to display on the FluentAuth registration form. After email verification, field values are saved to the new WordPress user account as ACF user meta (wp_usermeta), not post meta or options.','aplusplg-fluentauth-extensions') . '</p>';
        echo '<div class="apls-faext-guidance"><strong>' . esc_html__('ACF location rules are not storage destinations.','aplusplg-fluentauth-extensions') . '</strong> ' . esc_html__('You do not need to use User Form = Register, User Role = All, or Logged In. APlusPlugins renders the selected field groups during FluentAuth registration and always saves their values to the newly verified user. Set ACF location rules for where you want the group to appear in normal WordPress/ACF screens; avoid broad rules if you do not want those fields displayed elsewhere.','aplusplg-fluentauth-extensions') . '</div>';
        $acf = ACF_Adapter::active();
        $groups = $acf ? ACF_Adapter::groups() : [];
        if (!$acf) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('To enable registration fields, install or activate ACF or ACF Pro.','aplusplg-fluentauth-extensions') . '</p></div>';
        } elseif (!$groups) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('No ACF field groups were found. Create and publish a field group in ACF, then return here to select it for FluentAuth registration.','aplusplg-fluentauth-extensions') . '</p></div>';
        } else {
            echo '<div class="apls-faext-field"><label class="apls-faext-field-label" for="apls-faext-acf-groups">' . esc_html__('Registration field groups','aplusplg-fluentauth-extensions') . '</label><select class="apls-faext-tag-select" id="apls-faext-acf-groups" name="apls_faext_options[acf_groups][]" multiple>';
            foreach ($groups as $group) {
                $key = $group['key'] ?? '';
                if (!$key) { continue; }
                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected(in_array($key, (array) $o['acf_groups'], true), true, false), esc_html($group['title'] ?? $key));
            }
            echo '</select></div>';
        }
        echo '<p class="description">' . esc_html__('Supported: text, textarea, number, email, URL, select, radio, checkbox, true/false, date, date-time, and time. Unsupported field groups are rejected.','aplusplg-fluentauth-extensions') . '</p>';
        echo '<p class="description">' . esc_html__('A runtime configuration failure blocks registration and sends a deduplicated administrator email.','aplusplg-fluentauth-extensions') . '</p>';
        echo '</section><section class="apls-faext-panel" data-apls-panel="crm" hidden><h2>' . esc_html__('CRM Integration', 'aplusplg-fluentauth-extensions') . '</h2><p class="apls-faext-intro">' . esc_html__('Choose one CRM integration. WPFusion can still synchronize users independently: review its own settings to avoid duplicate contact processing.', 'aplusplg-fluentauth-extensions') . '</p>';
        $mode = $o['crm_provider'] ?? 'fluentcrm';
        echo '<fieldset class="apls-faext-radio-group"><legend class="screen-reader-text">' . esc_html__('CRM integration provider', 'aplusplg-fluentauth-extensions') . '</legend>';
        foreach (['fluentcrm'=>__('Direct FluentCRM', 'aplusplg-fluentauth-extensions'),'wpfusion'=>__('WPFusion', 'aplusplg-fluentauth-extensions'),'custom'=>__('Developer hooks only', 'aplusplg-fluentauth-extensions'),'none'=>__('Disabled', 'aplusplg-fluentauth-extensions')] as $value=>$label) {
            $unavailable=($value==='fluentcrm'&&!$crm)||($value==='wpfusion'&&!$wpf);
            printf('<label class="apls-faext-radio"><input type="radio" name="apls_faext_options[crm_provider]" value="%s" %s %s><span>%s</span></label>',esc_attr($value),checked($mode,$value,false),disabled($unavailable,true,false),esc_html($label));
        }
        echo '</fieldset>';
        if (!$crm) { echo '<div class="notice notice-info inline"><p>' . esc_html__('Direct FluentCRM requires installing or activating FluentCRM.', 'aplusplg-fluentauth-extensions') . '</p></div>'; }
        if (!$wpf) { echo '<div class="notice notice-info inline"><p>' . esc_html__('WPFusion integration requires installing or activating WPFusion.', 'aplusplg-fluentauth-extensions') . '</p></div>'; }
        if ($wpf && $mode==='fluentcrm') { echo '<div class="notice notice-warning inline"><p>' . esc_html__('WPFusion is active. If Create Contacts is enabled in WPFusion, it may independently synchronize new users even when Direct FluentCRM is selected here. Disable WPFusion registration contact creation or choose WPFusion mode; APlusPlugins does not change third-party configuration.', 'aplusplg-fluentauth-extensions') . '</p></div>'; }
        echo '<div class="apls-faext-mode" data-apls-mode="fluentcrm">';
        self::check('crm', __('Synchronize completed registrations with FluentCRM', 'aplusplg-fluentauth-extensions'), $o, !$crm);
        self::crm_select('crm_tags', __('Apply tags on every completed registration', 'aplusplg-fluentauth-extensions'), $o, $crm);
        self::crm_select('crm_marketing_tags', __('Apply additional tags only when marketing opt-in is checked', 'aplusplg-fluentauth-extensions'), $o, $crm);
        echo '<p class="description">' . esc_html__('Manage lists through FluentCRM automations triggered by tags. Marketing consent does not override an existing unsubscribe.', 'aplusplg-fluentauth-extensions') . '</p></div>';
        echo '<div class="apls-faext-mode" data-apls-mode="wpfusion"><p>' . esc_html__('WPFusion owns CRM contact creation and default registration tags. Configure those inside WPFusion. APlusPlugins bridges the affirmative marketing consent and applies only the selected marketing tags.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<div class="notice notice-warning inline" role="note"><p>' . esc_html__('IMPORTANT: WPFusion and its connected CRM independently manage contact creation and subscription status. This extension never sets Transactional, Subscribed, or Unsubscribed in WPFusion mode. An unchecked registration checkbox does not guarantee that WPFusion will refrain from subscribing the contact. Configure a non-marketing default/contact status in WPFusion and your CRM, and verify both checked and unchecked registrations before enabling marketing consent.', 'aplusplg-fluentauth-extensions') . '</p></div>';
        self::check('wpf_consent_ack', __('I understand that WPFusion and its connected CRM control subscription status; I have configured and tested consent-safe defaults. Enable the marketing checkbox under this responsibility.', 'aplusplg-fluentauth-extensions'), $o);
        self::wpf_select('wpf_marketing_tags', __('WPFusion marketing opt-in tags', 'aplusplg-fluentauth-extensions'), $o, $wpf);
        echo '<p class="description">' . esc_html__('Check WPFusion General Settings > Create Contacts. Its global registration synchronization continues regardless of the selector above. Consent does not authorize resubscribing an existing unsubscribed contact.', 'aplusplg-fluentauth-extensions') . '</p></div>';
        echo '<div class="apls-faext-mode" data-apls-mode="custom"><p>' . esc_html__('No built-in CRM synchronization runs. Subscribe to apls/fluentauth/registration_completed and apls/fluentauth/consent_recorded for custom integrations.', 'aplusplg-fluentauth-extensions') . '</p></div>';
        echo '<div class="apls-faext-mode" data-apls-mode="none"><p>' . esc_html__('APlusPlugins will not run a CRM adapter. Third-party plugins may still respond to WordPress user creation.', 'aplusplg-fluentauth-extensions') . '</p></div>';
        echo '</section><section class="apls-faext-panel" data-apls-panel="zerobounce" hidden><h2>' . esc_html__('Email validation', 'aplusplg-fluentauth-extensions') . '</h2><p class="apls-faext-intro">' . esc_html__('Use the existing ZeroBounce WordPress credentials and validation rules.', 'aplusplg-fluentauth-extensions') . '</p>'; 
        if (!$zb) { echo '<div class="notice notice-warning inline"><p>' . esc_html__('To enable ZeroBounce settings, install or activate the ZeroBounce WordPress plugin.', 'aplusplg-fluentauth-extensions') . '</p></div>'; }
        echo '<p><label>' . esc_html__('Validation provider', 'aplusplg-fluentauth-extensions') . ' <select name="apls_faext_options[provider]">';
        foreach (['zerobounce'=>__('ZeroBounce WordPress settings', 'aplusplg-fluentauth-extensions'), 'external'=>__('Custom validation via filter', 'aplusplg-fluentauth-extensions'), 'none'=>__('None', 'aplusplg-fluentauth-extensions')] as $value=>$label) { printf('<option value="%s" %s %s>%s</option>', esc_attr($value), selected($o['provider'],$value,false), disabled($value==='zerobounce'&&!$zb,true,false), esc_html($label)); }
        echo '</select></label></p>';
        self::check('live', __('Enable live email validation on FluentAuth shortcode registration', 'aplusplg-fluentauth-extensions'), $o);
        self::check('server', __('Enforce validation on registration submission', 'aplusplg-fluentauth-extensions'), $o);
        echo '<p><label>' . esc_html__('Provider unavailable / API error', 'aplusplg-fluentauth-extensions') . ' <select name="apls_faext_options[on_error]">';
        foreach (['block'=>__('Block and retry', 'aplusplg-fluentauth-extensions'),'continue'=>__('Continue to FluentAuth email verification', 'aplusplg-fluentauth-extensions')] as $value=>$label) { printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($o['on_error'],$value,false), esc_html($label)); }
        echo '</select></label></p>';
        echo '</section><section class="apls-faext-panel" data-apls-panel="community" hidden><h2>' . esc_html__('FluentCommunity', 'aplusplg-fluentauth-extensions') . '</h2>'; 
        if (!$community) { echo '<div class="notice notice-warning inline"><p>' . esc_html__('To enable community integration, install or activate FluentCommunity.', 'aplusplg-fluentauth-extensions') . '</p></div>'; }
        self::check('community_auth_redirect', __('Redirect FluentCommunity login and registration entry points to FluentAuth', 'aplusplg-fluentauth-extensions'), $o, !$community);
        echo '<p class="description">' . esc_html__('Optional (off by default). Only the fixed FluentCommunity authentication URLs below are intercepted. Destinations must be relative URLs on this WordPress site, beginning with /. Configure and test the destination forms before enabling redirects. Invitation and recovery requests are excluded.', 'aplusplg-fluentauth-extensions') . '</p>';
        foreach ([
            'community_login_destination' => ['/community/?fcom_action=auth', __('Login destination', 'aplusplg-fluentauth-extensions')],
            'community_register_destination' => ['/community/?fcom_action=auth&form=register', __('Registration destination', 'aplusplg-fluentauth-extensions')],
        ] as $key => $labels) {
            echo '<div class="apls-faext-field"><label class="apls-faext-field-label" for="apls-faext-' . esc_attr($key) . '"><code>' . esc_html($labels[0]) . '</code><br><span class="description">' . esc_html($labels[1]) . '</span></label>';
            echo '<input type="text" class="regular-text" id="apls-faext-' . esc_attr($key) . '" name="apls_faext_options[' . esc_attr($key) . ']" value="' . esc_attr((string)($o[$key] ?? '')) . '" placeholder="/login/" ' . disabled(!$community, true, false) . '></div>';
        }
        echo '<p class="description">' . esc_html__('The login destination also handles form=login. These redirects only change where visitors go; ACF fields and marketing consent are injected exclusively into the supported FluentAuth native and FluentAuth shortcode registration forms, never into third-party forms such as LoginPress, FluentForms or other page/form builders.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<h3>' . esc_html__('Direct FluentCRM', 'aplusplg-fluentauth-extensions') . '</h3><p>' . esc_html__('Use registration tags to trigger FluentCRM automations that add people to Spaces, or configure FluentCommunity tag-based Space access. Do not use marketing tags for access entitlements.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<h3>' . esc_html__('WPFusion', 'aplusplg-fluentauth-extensions') . '</h3><p>' . esc_html__('WPFusion can link CRM tags to FluentCommunity Spaces for enrollment and removal. Configure the Linked Tag and access requirements in WPFusion and FluentCommunity; this extension never assigns Spaces directly.', 'aplusplg-fluentauth-extensions') . '</p>';
        if (!$wpf) { echo '<p class="description">' . esc_html__('To enable WPFusion Space integration, install or activate WPFusion and configure its CRM connection.', 'aplusplg-fluentauth-extensions') . '</p>'; }
        echo '</section><section class="apls-faext-panel" data-apls-panel="developer" hidden>';
        self::render_developer_hooks();
        echo '</section><section class="apls-faext-panel" data-apls-panel="cleanup" hidden><h2>' . esc_html__('Plugin cleanup', 'aplusplg-fluentauth-extensions') . '</h2><p class="apls-faext-intro">' . esc_html__('Choose what happens when the plugin is deleted from WordPress. Deactivation preserves settings and user metadata. Temporary table removal is configurable.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<h3>' . esc_html__('Registration diagnostics', 'aplusplg-fluentauth-extensions') . '</h3>';
        self::check('debug_registration', __('Enable registration diagnostics (temporary troubleshooting only)', 'aplusplg-fluentauth-extensions'), $o);
        self::check('debug_console', __('Enable browser console diagnostics (development only)', 'aplusplg-fluentauth-extensions'), $o);
        echo '<p><label>' . esc_html__('Logging detail', 'aplusplg-fluentauth-extensions') . ' <select name="apls_faext_options[debug_detail]">';
        foreach (['basic'=>__('Basic', 'aplusplg-fluentauth-extensions'),'detailed'=>__('Detailed database tracing', 'aplusplg-fluentauth-extensions')] as $key=>$value) { printf('<option value="%s" %s>%s</option>',esc_attr($key),selected($o['debug_detail'],$key,false),esc_html($value)); }
        echo '</select></label></p>';
        echo '<div class="apls-faext-log-tools"><h3>' . esc_html__('Registration event log', 'aplusplg-fluentauth-extensions') . '</h3><p id="apls-faext-log-status" aria-live="polite"></p>';
        echo '<textarea id="apls-faext-log-view" readonly rows="15" spellcheck="false" aria-label="' . esc_attr__('Registration diagnostic events', 'aplusplg-fluentauth-extensions') . '"></textarea>';
        echo '<p><button type="button" class="button" id="apls-faext-log-refresh">' . esc_html__('Refresh log', 'aplusplg-fluentauth-extensions') . '</button> ';
        echo '<button type="button" class="button" id="apls-faext-log-copy">' . esc_html__('Copy log', 'aplusplg-fluentauth-extensions') . '</button> ';
        echo '<button type="button" class="button" id="apls-faext-log-clear">' . esc_html__('Clear log', 'aplusplg-fluentauth-extensions') . '</button></p></div>';
        echo '<p class="description">' . esc_html__('Diagnostic events are appended to a protected directory under wp-content/apls-faext-logs/events.php (protected PHP log). Disable after troubleshooting. No field values, email addresses, passwords, or verification codes are logged.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '<h3>' . esc_html__('Scheduled pending-data cleanup','aplusplg-fluentauth-extensions') . '</h3><p>' . esc_html__('Delete expired pending registration records every','aplusplg-fluentauth-extensions') . ' <input type="number" min="1" max="365" name="apls_faext_options[cleanup_every]" value="' . esc_attr($o['cleanup_every']) . '" class="small-text"> <select name="apls_faext_options[cleanup_unit]">';
        foreach (['minutes','hours','days','weeks','months'] as $unit) { printf('<option value="%s" %s>%s</option>',esc_attr($unit),selected($o['cleanup_unit'],$unit,false),esc_html(ucfirst($unit))); }
        echo '</select></p><p class="description">' . esc_html__('Default: every 24 hours, scheduled near midnight in the WordPress site timezone. WP-Cron runs when site traffic triggers it, so midnight is approximate.','aplusplg-fluentauth-extensions') . '</p>';
        self::check('drop_pending_on_deactivate',__('Drop the temporary pending-registration table on plugin deactivation (destructive)','aplusplg-fluentauth-extensions'),$o);
        self::check('drop_pending_on_uninstall',__('Drop the temporary pending-registration table on permanent uninstall','aplusplg-fluentauth-extensions'),$o);
        self::check('delete_settings_on_uninstall', __('Delete APlusPlugins extension settings when uninstalled', 'aplusplg-fluentauth-extensions'), $o);
        self::check('delete_consent_on_uninstall', __('Delete only APlusPlugins registration markers and marketing-consent user metadata when uninstalled', 'aplusplg-fluentauth-extensions'), $o);
        echo '<p class="description">' . esc_html__('These choices take effect only when you delete the plugin in WordPress. They do not delete WordPress users, FluentCRM contacts, tags, lists, ZeroBounce credentials, or FluentCommunity memberships. Review your legal retention requirements before deleting consent evidence.', 'aplusplg-fluentauth-extensions') . '</p>';
        echo '</section></main><div class="apls-faext-savebar">'; submit_button(__('Save settings', 'aplusplg-fluentauth-extensions'), 'primary', 'submit', false); echo '</div></form></div>';
    }
    private static function wpf_select(string $field, string $label, array $options, bool $active): void {
        echo '<div class="apls-faext-field"><label class="apls-faext-field-label" for="apls-faext-' . esc_attr($field) . '">' . esc_html($label) . '</label>';
        echo '<select id="apls-faext-' . esc_attr($field) . '" class="apls-faext-tag-select" multiple name="apls_faext_options[' . esc_attr($field) . '][]" ' . disabled(!$active, true, false) . '>';
        if ($active) {
            foreach ((array) wpf_get_option('available_tags', []) as $id => $label) {
                if (!is_scalar($label)) { continue; }
                printf('<option value="%s" %s>%s</option>', esc_attr((string)$id), selected(in_array((string)$id, array_map('strval', (array)($options[$field] ?? [])), true), true, false), esc_html((string)$label));
            }
        }
        echo '</select></div>';
    }
    private static function crm_select(string $field, string $label, array $o, bool $active): void {
        echo '<div class="apls-faext-field"><label class="apls-faext-field-label" for="apls-faext-' . esc_attr($field) . '">' . esc_html($label) . '</label>';
        echo '<select id="apls-faext-' . esc_attr($field) . '" class="apls-faext-tag-select" multiple name="apls_faext_options[' . esc_attr($field) . '][]" ' . disabled(!$active, true, false) . '>';
        if ($active) {
            try {
                $api = fluentCrmApi('tags'); $items = $api->all();
                foreach ($items as $item) {
                    $id=(int)$item->id;
                    printf('<option value="%s" %s>%s</option>', esc_attr((string)$id), selected(in_array($id, $o[$field] ?? [], true),true,false), esc_html((string)($item->title ?? $item->slug ?? $id)));
                }
            } catch (\Throwable $e) { echo '<option disabled>' . esc_html__('Unable to list current CRM tags', 'aplusplg-fluentauth-extensions') . '</option>'; }
        }
        echo '</select></div>';
    }
}
