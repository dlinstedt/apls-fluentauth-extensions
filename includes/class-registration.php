<?php
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Registration {
    private static bool $completed=false;
    /**
     * A single boundary for submitted registration values. This does not grant
     * authorization: first-stage handlers MUST call registration_nonce_valid()
     * before using input. Verification-stage handlers validate FluentAuth's
     * verified token and our HMAC-bound pending transaction instead.
     *
     * @return string|array|null Unslashed data, not yet field-type normalized.
     */
    public static function submitted(string $name) {
        $allowed = ['apls_faext_registration_nonce', 'apls_faext_transaction', 'apls_faext_marketing', 'acf', 'acf_fields', '_email_verification_hash', 'user_email', 'user_login', 'email', '_fls_signup_nonce'];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only, not authorization; the call site verifies the first-stage nonce before storing data. The verified-code stage uses FluentAuth and the HMAC-bound transaction.
        if (!in_array($name, $allowed, true) || !isset($_POST[$name])) { return null; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Intentional raw, unslashed boundary for ACF scalar/array values. Do not flatten/sanitize here: typed callers validate, sanitize, and enforce nonce or verified-code transaction BEFORE any persistence.
        $raw = wp_unslash($_POST[$name]);
        return is_string($raw) || is_array($raw) ? $raw : null;
    }
    /** Return only a normalized scalar for known registration controls. */
    public static function submitted_text(string $name): ?string {
        $value = self::submitted($name);
        return is_string($value) ? sanitize_text_field($value) : null;
    }
    /** Nonce guards our initial write; does not replace verification-code checks. */
    public static function registration_nonce_valid(): bool {
        $nonce = self::submitted_text('apls_faext_registration_nonce');
        return $nonce !== null && wp_verify_nonce($nonce, 'apls_faext_register') !== false;
    }

    private static array $contexts=[];
    public static function pending_context(int $user_id): ?array { return self::$contexts[$user_id] ?? null; }
    public static function register(): void {
        add_filter('fluent_auth/verify_signup_email','__return_true', PHP_INT_MAX);
        add_action('fluent_auth/after_signup_validation',[self::class,'validate_shortcode'],10,1);
        add_action('register_post',[self::class,'validate_native'],7,3);
        add_action('register_form',[self::class,'native_fields'],25);
        add_filter('registration_errors',[self::class,'native_registration_errors'],8,3);
        add_action('fluent_auth/after_creating_user',[self::class,'completed'],10,2);
        add_action('wp_ajax_nopriv_apls_faext_check_email',[self::class,'ajax_validate']);
        add_action('wp_ajax_apls_faext_check_email',[self::class,'ajax_validate']);
        add_action('wp_enqueue_scripts',[self::class,'enqueue']);
        add_action('login_enqueue_scripts',[self::class,'enqueue_login']);
        add_filter('fluent_auth/registration_form_fields',[self::class,'trace_form_render'],99,1);
        add_filter('do_shortcode_tag',[self::class,'render_shortcode_fields'],20,4);
    }
    public static function validate_shortcode($data): void {
        if (!is_array($data)) { return; }
        $o=Plugin::options();
        if (empty($data['_email_verification_token']) && !empty($o['server'])) {
            $r=Email_Validator::validate(sanitize_email($data['email'] ?? ''));
            if (!$r['allowed']) { wp_send_json(['message'=>$r['message']],422); }
        }
        Diagnostics::log('registration.validation_hook', ['verification_step'=>!empty($data['_email_verification_token'])]);
        do_action('apls/fluentauth/registration_validated', $data);
    }
    public static function validate_native($username, $email, $errors): void {
        if (!($errors instanceof \WP_Error) || $errors->has_errors() || empty(Plugin::options()['server'])) { return; }
        // Only apply to an actual WordPress registration request, never arbitrary register_post calls.
        if (self::submitted_text('user_email') === null && self::submitted_text('user_login') === null) { return; }
        $r=Email_Validator::validate(sanitize_email($email));
        if (!$r['allowed']) { $errors->add('apls_faext_email', $r['message']); }
    }
    /** Insert registration extras during the native WordPress register_form action. */
    public static function native_fields(): void {
        $o = Plugin::options();
        $submitted_token = self::submitted_text('apls_faext_transaction');
        $token = $submitted_token ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { $token = bin2hex(random_bytes(32)); }
        if (ACF_Adapter::selected()) {
            wp_nonce_field('apls_faext_register', 'apls_faext_registration_nonce', false);
            printf('<input type="hidden" name="apls_faext_transaction" value="%s" />', esc_attr($token));
            if (self::submitted_text('_email_verification_hash') === null) {
                echo ACF_Adapter::render_registration_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ACF generates escaped field markup.
            }
        }
        if (Settings::marketing_enabled($o) && self::submitted_text('_email_verification_hash') === null) {
            $checked = self::submitted_text('apls_faext_marketing') === 'yes';
            echo '<p class="apls-faext-native-consent"><label><input type="checkbox" name="apls_faext_marketing" value="yes" ' . checked($checked, true, false) . ' /><span class="apls-faext-consent-text">' . esc_html($o['marketing_text']) . '</span></label></p>';
        }
        Diagnostics::log('registration.native_fields_rendered', ['acf_enabled' => (bool) ACF_Adapter::selected(), 'marketing' => Settings::marketing_enabled($o)]);
    }
    /**
     * Decorate FluentAuth's PHP-generated shortcode HTML before it reaches the browser.
     * The outer fluent_auth shortcode invokes fluent_auth_signup internally, so do not
     * decorate the outer shortcode a second time.
     */
    public static function render_shortcode_fields($output, $tag, $attrs, $match) {
        if ('fluent_auth_signup' !== $tag || !is_string($output)) { return $output; }
        $o = Plugin::options();
        if (!Settings::marketing_enabled($o) && !ACF_Adapter::selected()) { return $output; }
        if (!class_exists('DOMDocument')) {
            Diagnostics::log('registration.php_dom_unavailable');
            return $output;
        }
        $acf_html = ACF_Adapter::selected() ? ACF_Adapter::render_registration_fields() : '';
        $consent_html = Settings::marketing_enabled($o)
            ? '<label class="apls-faext-consent"><input type="checkbox" name="apls_faext_marketing" value="yes" /><span class="apls-faext-consent-text">' . esc_html($o['marketing_text']) . '</span></label>'
            : '';
        $transaction_html = ACF_Adapter::selected()
            ? wp_nonce_field('apls_faext_register', 'apls_faext_registration_nonce', false, false) . '<input type="hidden" name="apls_faext_transaction" value="' . esc_attr(bin2hex(random_bytes(32))) . '" />'
            : '';
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $flags = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET;
            $wrapper_id = 'apls-faext-php-fragment';
            $html = '<div id="' . $wrapper_id . '">' . $output . '</div>';
            if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, $flags)) { return $output; }
            $xpath = new \DOMXPath($dom);
            $fields = $xpath->query('//*[@id="flsRegistrationForm"]//*[contains(concat(" ",normalize-space(@class)," ")," fls_registration_fields ")]');
            if (!$fields || !$fields->length) { Diagnostics::log('registration.shortcode_fields_missing'); return $output; }
            $target = $fields->item(0);
            $extra = new \DOMDocument('1.0', 'UTF-8');
            $extra->loadHTML('<?xml encoding="UTF-8"><div id="apls-faext-insert">' . $transaction_html . $acf_html . $consent_html . '</div>', $flags);
            $extra_root = $extra->getElementById('apls-faext-insert');
            if (!$extra_root) { return $output; }
            // FluentAuth includes its submit button inside .fls_registration_fields.
            // Insert immediately BEFORE the button, never at the end of the wrapper.
            $submit = $xpath->query('.//*[@id="fls_submit"]', $target);
            if (!$submit || !$submit->length || $submit->item(0)->parentNode !== $target) {
                Diagnostics::log('registration.shortcode_submit_missing');
                return $output;
            }
            $submit_node = $submit->item(0);
            foreach (iterator_to_array($extra_root->childNodes) as $child) {
                $target->insertBefore($dom->importNode($child, true), $submit_node);
            }
            $root = $dom->getElementById($wrapper_id);
            if (!$root) { return $output; }
            $result = '';
            foreach ($root->childNodes as $child) { $result .= $dom->saveHTML($child); }
            Diagnostics::log('registration.shortcode_php_rendered', ['acf' => (bool) ACF_Adapter::selected(), 'marketing' => Settings::marketing_enabled($o)]);
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
    public static function native_registration_errors($errors,$username,$email) {
        if (!($errors instanceof \WP_Error) || $errors->has_errors() || !ACF_Adapter::selected()) { return $errors; }
        // The first native POST is inspected before FluentAuth sends its code.
        if (self::submitted_text('_email_verification_hash') === null) {
            ACF_Adapter::capture_native($errors,(string)$email,false);
        }
        return $errors;
    }
    public static function trace_form_render($fields) {
        Diagnostics::log('fluentauth.registration_fields_filter', ['fields_count'=>is_array($fields)?count($fields):0]);
        return $fields;
    }
    public static function enqueue_login(): void { self::enqueue(true); }
    private static function is_registration_location(): bool {
        if (is_page()) {
            $id=get_queried_object_id();
            $content=$id ? (string)get_post_field('post_content',$id) : '';
            foreach (['fluent_auth_signup','fluent_auth','fluent_auth_login'] as $shortcode) {
                if (has_shortcode($content,$shortcode)) { return true; }
            }
        }
        // FluentAuth can use a dedicated /login/ route instead of a shortcode page.
        // Restrict this fallback to the site's login path rather than enqueuing globally.
        $request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $request_path=(string)wp_parse_url($request_uri,PHP_URL_PATH);
        $site_login=(string)wp_parse_url(home_url('/login/'),PHP_URL_PATH);
        $request_path=untrailingslashit($request_path);
        $site_login=untrailingslashit($site_login);
        return ($request_path !== '' && $request_path === $site_login);
    }
    public static function enqueue(bool $login_hook=false): void {
        $o=Plugin::options();
        if (is_admin() && !wp_doing_ajax()) { return; }
        if (!$login_hook && !self::is_registration_location()) { return; }
        Diagnostics::log('registration.assets_requested', ['login_hook'=>$login_hook,'acf_selected'=>count(ACF_Adapter::selected()),'marketing'=>Settings::marketing_enabled($o)]);
        if (!Settings::marketing_enabled($o) && empty($o['live']) && !ACF_Adapter::selected() && !Diagnostics::enabled()) { return; }
        $js='assets/js/registration.js'; $css='assets/css/registration.css';
        wp_enqueue_script('apls-faext-registration',plugins_url($js,FILE),[],filemtime(dirname(FILE).'/'.$js),true);
        wp_add_inline_script('apls-faext-registration','window.aplsFaext='.wp_json_encode(['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('apls_faext_email'),'acf'=>(bool)ACF_Adapter::selected(),'debug'=>Diagnostics::enabled() && !empty($o['debug_console']),'live'=>(bool)$o['live'],'marketing'=>Settings::marketing_enabled($o),'language'=>$o['marketing_text'],'checking'=>__('Checking email address...', 'aplusplugins-fluentauth-extensions'),'bad'=>__('Please correct your email address.', 'aplusplugins-fluentauth-extensions')]).';','before');
        if (ACF_Adapter::active() && ACF_Adapter::selected() && function_exists('acf_enqueue_scripts')) { acf_enqueue_scripts(); }
        wp_enqueue_style('apls-faext-registration',plugins_url($css,FILE),[],filemtime(dirname(FILE).'/'.$css));
    }
    public static function ajax_validate(): void {
        if (!check_ajax_referer('apls_faext_email','nonce',false)) { wp_send_json_error(['message'=>__('Request expired.', 'aplusplugins-fluentauth-extensions')],403); }
        if (empty(Plugin::options()['live'])) { wp_send_json_error(['message'=>__('Live validation is disabled.', 'aplusplugins-fluentauth-extensions')],403); }
        $raw_email=self::submitted('email') ?? '';
        if (!is_string($raw_email) || strlen($raw_email)>254) { wp_send_json_error(['message'=>__('Invalid email address.','aplusplugins-fluentauth-extensions')],422); }
        $email=sanitize_email($raw_email);
        if (!is_email($email)) { wp_send_json_error(['message'=>__('Enter a valid email address.', 'aplusplugins-fluentauth-extensions')],422); }
        // Limit anonymous validation credits per source network within a rolling window.
        $ip=sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $limit='apls_faext_rate_'.hash_hmac('sha256',$ip,wp_salt('nonce'));
        $count=(int)get_transient($limit);
        if ($count>=15) { wp_send_json_error(['message'=>__('Too many requests. Please try again later.', 'aplusplugins-fluentauth-extensions')],429); }
        set_transient($limit,$count+1, HOUR_IN_SECONDS);
        $r=Email_Validator::validate($email,'live');
        wp_send_json_success(['allowed'=>(bool)$r['allowed'],'message'=>$r['message']]);
    }
    public static function completed($user_id, $data): void {
        Diagnostics::log('registration.user_created', ['user_created'=>(bool)$user_id, 'user_id'=>absint($user_id)]);
        $user_id=absint($user_id);
        if (!$user_id || self::$completed || get_user_meta($user_id,'_apls_faext_completed',true)) { return; }
        self::$completed=true;
        $o=Plugin::options();
        $is_shortcode=self::submitted_text('_fls_signup_nonce') !== null;
        $is_native=!$is_shortcode && self::submitted_text('user_email') !== null && did_action('login_init');
        // Missing checkbox is a refusal, not permission. Social registrants are not auto-subscribed.
        $consent=($is_shortcode || $is_native) && Settings::marketing_enabled($o) && self::submitted_text('apls_faext_marketing') === 'yes';
        $context=['source'=>$is_shortcode?'fluentauth_shortcode':($is_native?'fluentauth_native':'fluentauth_other'),'marketing_consent'=>$consent,'consent_text'=>$consent?$o['marketing_text']:''];
        if ($consent) { update_user_meta($user_id,'_apls_faext_marketing_consent_at',gmdate('Y-m-d H:i:s')); do_action('apls/fluentauth/consent_recorded',$user_id,$context); }
        self::$contexts[$user_id] = $context;
        update_user_meta($user_id,'_apls_faext_completed',gmdate('Y-m-d H:i:s'));
        do_action('apls/fluentauth/registration_completed',$user_id,$context);
        Diagnostics::log('registration.email_verified_user_created', ['user_id'=>$user_id,'mode'=>$context['source']]);
        CRM::sync($user_id,$context);
        Diagnostics::log('registration.completed', ['user_id'=>$user_id]);
        if (($o['crm_provider'] ?? 'fluentcrm') === 'wpfusion') { WP_Fusion_Adapter::apply_marketing($user_id,$context); }
    }
}
