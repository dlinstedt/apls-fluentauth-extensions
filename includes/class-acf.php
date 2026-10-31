<?php
/** Optional ACF field integration. */
namespace APLS\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class ACF_Adapter {
    private const ALLOWED = ['text','textarea','number','email','url','select','radio','checkbox','true_false','date_picker','date_time_picker','time_picker'];
    public static function active(): bool { return function_exists('acf_get_field_group') && function_exists('acf_get_fields') && function_exists('update_field'); }
    public static function groups(): array {
        if (!self::active() || !function_exists('acf_get_field_groups')) { return []; }
        return (array) acf_get_field_groups();
    }
    public static function selected(): array { return array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)(Plugin::options()['acf_groups'] ?? []))))); }
    public static function validate_groups(array $ids): array {
        if (!$ids) { return []; }
        if (!self::active()) { return [__('ACF is not active.', 'apls-fluentauth-extensions')]; }
        $errors=[]; $names=[];
        foreach ($ids as $id) {
            $group=acf_get_field_group($id);
            /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
            if (!$group || empty($group['key'])) { $errors[]=sprintf(__('ACF group %s cannot be found.', 'apls-fluentauth-extensions'),$id); continue; }
            foreach ((array)acf_get_fields($group) as $field) {
                $type=$field['type'] ?? ''; $key=$field['key'] ?? '';
                if (!in_array($type,self::ALLOWED,true)) {
                    /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
                    $errors[]=sprintf(__('Unsupported ACF field %1$s (%2$s) in group %3$s.', 'apls-fluentauth-extensions'),$field['label'] ?? $key,$type,$group['title'] ?? $id);
                }
                if (empty($field['name']) || !$key || isset($names[$field['name']])) {
                    /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
                    $errors[]=sprintf(__('Missing or repeated ACF field name: %s.', 'apls-fluentauth-extensions'),$field['name'] ?? $key);
                }
                $names[$field['name'] ?? '']=$key;
                if (!empty($field['conditional_logic'])) {
                    /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
                    $errors[]=sprintf(__('Conditional ACF field %s is not supported by the registration server validator.', 'apls-fluentauth-extensions'),$field['label'] ?? $key);
                }
                if (in_array($type,['radio','select','checkbox'],true) && (!empty($field['other_choice']) || !empty($field['save_other_choice']) || !empty($field['create_options']) || !empty($field['save_options']) || !empty($field['ajax']))) {
                    /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
                    $errors[]=sprintf(__('Dynamic or custom choices are not supported for registration field %s.', 'apls-fluentauth-extensions'),$field['label'] ?? $key);
                }
            }
        }
        return $errors;
    }
    public static function fields(): array {
        $result=[];
        foreach (self::selected() as $id) {
            $group=acf_get_field_group($id);
            if (!$group) { continue; }
            foreach ((array)acf_get_fields($group) as $field) { $result[$field['key']]=$field; }
        }
        return $result;
    }
    public static function schema_hash(): string {
        $values=[];
        foreach (self::fields() as $key=>$field) {
            $values[$key]=array_intersect_key($field,array_flip(['key','name','type','required','choices','min','max','minlength','maxlength','date_format','display_format','return_format','conditional_logic','multiple','other_choice','save_other_choice','create_options','save_options','ajax']));
        }
        return hash('sha256',wp_json_encode($values));
    }
    public static function register(): void {
        add_action('fluent_auth/after_signup_validation',[self::class,'capture'],8,1);
        add_action('fluent_auth/after_creating_user',[self::class,'persist'],5,2);
        add_action('acf/validate_save_post',[self::class,'validate_field_group_save'],20);
        add_action('register_post',[self::class,'validate_native_second_step'],8,3);
    }
    public static function validate_field_group_save(): void {
        if (!is_admin() || !current_user_can('manage_options') || !self::selected()) { return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        $post_id=isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
        if (!$post_id || !in_array('group_'.$post_id,self::selected(),true) && !in_array((string)$post_id,self::selected(),true)) { return; }
        // The ACF save API may vary by version: runtime validation remains authoritative.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        if (isset($_POST['acf_fields']) && is_array($_POST['acf_fields'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
            foreach (wp_unslash($_POST['acf_fields']) as $field) {
                if (is_array($field) && !in_array($field['type'] ?? '',self::ALLOWED,true)) {
                    acf_add_validation_error('',__('This field type is not supported for FluentAuth registration.','apls-fluentauth-extensions'));
                    break;
                }
            }
        }
    }
    public static function notify(array $errors): void {
        $fingerprint=hash('sha256',implode('|',$errors));
        $key='apls_faext_acf_alert_'.substr($fingerprint,0,24);
        if (get_transient($key)) { return; }
        set_transient($key,1,DAY_IN_SECONDS);
        wp_mail(get_option('admin_email'),__('FluentAuth registration blocked: ACF configuration','apls-fluentauth-extensions'),sprintf("Site: %s\nConfigured field groups: %s\nErrors:\n%s\nReview FluentAuth > Registration Extensions > ACF Fields.",home_url('/'),implode(', ',self::selected()),implode("\n",$errors)));
    }
    /** Server-side HTML. ACF renders field internals, but does not own the form. */
    public static function render_registration_fields(): string {
        if (!self::selected()) { return ''; }
        $errors = self::validate_groups(self::selected());
        if ($errors) {
            self::notify($errors);
            Diagnostics::log('acf.schema_failure', ['errors' => $errors]);
            return '<div class="apls-faext-acf-message" role="alert">' . esc_html__('Registration is temporarily unavailable. Please contact the site administrator.', 'apls-fluentauth-extensions') . '</div>';
        }
        ob_start();
        echo '<div class="apls-faext-acf-host"><div class="apls-faext-acf-fields">';
        foreach (self::fields() as $field) {
            // Preserve non-sensitive ACF inputs after a failed native registration POST.
            // Never restore password-like or unrecognized values.
            $field_key = $field['key'];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
            if (isset($_POST['acf']) && is_array($_POST['acf']) && array_key_exists($field_key, $_POST['acf'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
                $raw_value = wp_unslash($_POST['acf'][$field_key]);
                if (is_scalar($raw_value) || is_array($raw_value)) {
                    $safe_value = self::normalize($field, $raw_value);
                    if (!is_wp_error($safe_value)) { $field['value'] = $safe_value; }
                }
            }
            acf_render_field_wrap($field, 'div', 'label');
        }
        echo '</div></div>';
        return (string) ob_get_clean();
    }
    public static function capture($data): void {
        if (!self::selected()) { return; }
        Diagnostics::log('acf.capture_hook',['verification_step'=>!empty($data['_email_verification_token'])]);
        $errors=self::validate_groups(self::selected());
        if ($errors) { self::notify($errors); wp_send_json(['message'=>__('Registration is temporarily unavailable. Please contact the administrator.','apls-fluentauth-extensions')],503); }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        $transaction=sanitize_text_field(wp_unslash((string)($_POST['apls_faext_transaction'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/',$transaction)) { wp_send_json(['message'=>__('Invalid registration session. Reload the form.','apls-fluentauth-extensions')],422); }
        $email=strtolower(sanitize_email($data['email'] ?? ''));
        if (!$email) { return; }
        if (!empty($data['_email_verification_token'])) {
            if (!Pending_Registrations::valid($transaction,$email,self::schema_hash())) { wp_send_json(['message'=>__('Registration session expired. Start again.','apls-fluentauth-extensions')],422); }
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        $raw=isset($_POST['acf']) && is_array($_POST['acf']) ? wp_unslash($_POST['acf']) : [];
        $validated=[];
        if (array_diff(array_keys($raw),array_keys(self::fields()))) { wp_send_json(['message'=>__('Unexpected registration field.','apls-fluentauth-extensions')],422); }
        foreach (self::fields() as $key=>$field) {
            $value=$raw[$key] ?? null;
            /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
            if (!empty($field['required']) && ($value===null || $value==='' || $value===[])) { wp_send_json(['message'=>sprintf(__('Required field is missing: %s','apls-fluentauth-extensions'),$field['label'])],422); }
            if ($value===null) { continue; }
            if (!is_scalar($value) && !is_array($value)) { wp_send_json(['message'=>__('Invalid field value.','apls-fluentauth-extensions')],422); }
            $valid=self::normalize($field,$value);
            if (is_wp_error($valid)) { wp_send_json(['message'=>$valid->get_error_message()],422); }
            $validated[$key]=$valid;
        }
        if (!Pending_Registrations::store($transaction,$email,self::schema_hash(),$validated)) { wp_send_json(['message'=>__('Unable to save registration fields. Try again.','apls-fluentauth-extensions')],503); }
    }
    /** Native registration: use WP_Error rather than the shortcode JSON transport. */
    public static function capture_native(\WP_Error $errors,string $email,bool $second): void {
        Diagnostics::log('acf.native_capture',['verification_step'=>$second]);
        $issues=self::validate_groups(self::selected());
        if ($issues) {
            self::notify($issues);
            $errors->add('apls_acf_schema',__('Registration is temporarily unavailable. Please contact the site administrator.','apls-fluentauth-extensions'));
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        $transaction=isset($_POST['apls_faext_transaction']) ? sanitize_text_field(wp_unslash((string)$_POST['apls_faext_transaction'])) : '';
        if (!preg_match('/^[a-f0-9]{64}$/',$transaction)) {
            $errors->add('apls_acf_transaction',__('Registration session missing. Reload the registration page.','apls-fluentauth-extensions'));
            return;
        }
        $email=strtolower(sanitize_email($email));
        if ($second) {
            if (!Pending_Registrations::valid($transaction,$email,self::schema_hash())) {
                $errors->add('apls_acf_expired',__('Registration session expired. Please restart registration.','apls-fluentauth-extensions'));
            }
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        $raw=isset($_POST['acf']) && is_array($_POST['acf']) ? wp_unslash($_POST['acf']) : [];
        $payload=[];
        if (array_diff(array_keys($raw),array_keys(self::fields()))) {
            $errors->add('apls_acf_unknown',__('Unexpected registration field.','apls-fluentauth-extensions'));
            return;
        }
        foreach (self::fields() as $key=>$field) {
            $value=$raw[$key] ?? null;
            if (!empty($field['required']) && ($value===null || $value==='' || $value===[])) {
                /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
                $errors->add('apls_acf_required',sprintf(__('Required field missing: %s','apls-fluentauth-extensions'),$field['label']));
                continue;
            }
            if ($value===null) { continue; }
            if (!is_scalar($value) && !is_array($value)) {
                $errors->add('apls_acf_invalid',__('Invalid registration field value.','apls-fluentauth-extensions'));
                continue;
            }
            $normalized=self::normalize($field,$value);
            if (is_wp_error($normalized)) { $errors->add('apls_acf_invalid',$normalized->get_error_message()); continue; }
            $payload[$key]=$normalized;
        }
        if (!$errors->has_errors() && !Pending_Registrations::store($transaction,$email,self::schema_hash(),$payload)) {
            $errors->add('apls_acf_storage',__('Registration fields could not be stored. Please retry.','apls-fluentauth-extensions'));
        }
    }
    public static function validate_native_second_step($username,$email,$errors): void {
        if (!self::selected() || !($errors instanceof \WP_Error) || $errors->has_errors() || !did_action('login_init')) { return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        if (empty($_POST['_email_verification_hash'])) { return; }
        self::capture_native($errors,(string)$email,true);
    }
    private static function normalize(array $field,$value) {
        $type=$field['type'];
        if (is_array($value)) {
            if ($type!=='checkbox' && $type!=='select') { return new \WP_Error('invalid',__('Invalid field value.','apls-fluentauth-extensions')); }
            if (count($value)>64) { return new \WP_Error('invalid',__('Too many choices.','apls-fluentauth-extensions')); }
            foreach ($value as $option) { if (!is_string($option) && !is_int($option)) { return new \WP_Error('invalid',__('Invalid nested choice value.','apls-fluentauth-extensions')); } }
            $value=array_map('sanitize_text_field',$value);
        } else { if (!is_string($value) && !is_numeric($value)) { return new \WP_Error('invalid',__('Invalid scalar value.','apls-fluentauth-extensions')); } $value=($type==='textarea') ? sanitize_textarea_field($value) : sanitize_text_field($value); }
        // Hard maximum applies even when administrators omit ACF maxlength.
        if (is_string($value) && strlen($value)>4096) { return new \WP_Error('invalid',__('Field value is too large.','apls-fluentauth-extensions')); }
        if (is_array($value)) { foreach ($value as $choice) { if (strlen((string)$choice)>256) { return new \WP_Error('invalid',__('Field choice is too large.','apls-fluentauth-extensions')); } } }
        // Enforce both cardinality and allowed values on the server, independently
        // of the browser's <select>, checkbox and radio controls.
        if ($type==='select' && !empty($field['multiple']) && !is_array($value)) {
            return new \WP_Error('invalid',__('Multiple selection requires an array.','apls-fluentauth-extensions'));
        }
        if ($type==='select' && empty($field['multiple']) && is_array($value)) {
            return new \WP_Error('invalid',__('Single selection must not be an array.','apls-fluentauth-extensions'));
        }
        if ($type==='radio' && is_array($value)) {
            return new \WP_Error('invalid',__('Radio selection must be a single value.','apls-fluentauth-extensions'));
        }
        if ($type==='checkbox' && !is_array($value)) {
            return new \WP_Error('invalid',__('Checkbox selections must be an array.','apls-fluentauth-extensions'));
        }
        if (is_array($value) && count($value)!==count(array_unique(array_map('strval',$value)))) {
            return new \WP_Error('invalid',__('Duplicate field selections are not allowed.','apls-fluentauth-extensions'));
        }
        if (in_array($type,['select','checkbox','radio'],true)) {
            $choices=array_map('strval',array_keys((array)($field['choices'] ?? [])));
            foreach ((array)$value as $item) { if (!in_array((string)$item,$choices,true)) { return new \WP_Error('invalid',__('Invalid field choice.','apls-fluentauth-extensions')); } }
        }
        if ($type==='email' && $value!=='' && !is_email($value)) { return new \WP_Error('invalid',__('Invalid email field.','apls-fluentauth-extensions')); }
        if ($type==='url' && $value!=='' && (!filter_var($value,FILTER_VALIDATE_URL) || !in_array(strtolower((string)wp_parse_url((string)$value, PHP_URL_SCHEME)), ['http','https'],true))) { return new \WP_Error('invalid',__('Invalid URL field.','apls-fluentauth-extensions')); }
        if ($type==='number' && $value!=='' && (!is_numeric($value) || !is_finite((float)$value))) { return new \WP_Error('invalid',__('Invalid number field.','apls-fluentauth-extensions')); }
        // Enforce ACF's configured scalar bounds on the server, not merely in the browser.
        // This deliberately does not claim to implement all custom ACF validation filters.
        if (in_array($type, ['text','textarea','email','url'], true) && is_string($value)) {
            $length=function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            foreach (['minlength'=>'min','maxlength'=>'max'] as $setting=>$bound) {
                if (isset($field[$setting]) && $field[$setting] !== '' && is_numeric($field[$setting])) {
                    $threshold=max(0,(int)$field[$setting]);
                    if (($bound==='min' && $length<$threshold) || ($bound==='max' && $length>$threshold)) {
                        return new \WP_Error('invalid',__('Field length is outside the permitted range.','apls-fluentauth-extensions'));
                    }
                }
            }
        }
        if ($type==='number' && $value!=='') {
            foreach (['min'=>'min','max'=>'max'] as $setting=>$bound) {
                if (isset($field[$setting]) && $field[$setting] !== '' && is_numeric($field[$setting])) {
                    if (($bound==='min' && (float)$value<(float)$field[$setting]) || ($bound==='max' && (float)$value>(float)$field[$setting])) {
                        return new \WP_Error('invalid',__('Number is outside the permitted range.','apls-fluentauth-extensions'));
                    }
                }
            }
        }
        if ($type==='checkbox' && is_array($value)) {
            $count=count($value);
            foreach (['min'=>'min','max'=>'max'] as $setting=>$bound) {
                if (isset($field[$setting]) && $field[$setting] !== '' && is_numeric($field[$setting])) {
                    if (($bound==='min' && $count<(int)$field[$setting]) || ($bound==='max' && $count>(int)$field[$setting])) {
                        return new \WP_Error('invalid',__('Invalid number of selected choices.','apls-fluentauth-extensions'));
                    }
                }
            }
        }
        if ($type==='true_false') {
            if (is_array($value) || !in_array((string)$value,['0','1',''],true)) { return new \WP_Error('invalid',__('Invalid boolean value.','apls-fluentauth-extensions')); }
            $value=(int)($value==='1' || $value===1);
        }
        $formats=['date_picker'=>'Ymd','date_time_picker'=>'Y-m-d H:i:s','time_picker'=>'H:i:s'];
        if (isset($formats[$type]) && $value!=='') {
            $format=$formats[$type];
            // ACF stores Date Picker fields as Ymd regardless of display/return format.
            // Accept the configured display format as an input but persist canonical ACF values.
            $input_formats = [$format];
            if (!empty($field['display_format']) && !in_array($field['display_format'], $input_formats, true)) {
                $input_formats[] = $field['display_format'];
            }
            $parsed = false;
            foreach ($input_formats as $input_format) {
                $candidate = \DateTimeImmutable::createFromFormat('!' . $input_format, (string) $value);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($candidate && ($errors === false || (!$errors['warning_count'] && !$errors['error_count'])) && $candidate->format($input_format) === (string) $value) {
                    $value = $candidate->format($format);
                    $parsed = true;
                    break;
                }
            }
            if (!$parsed) { return new \WP_Error('invalid',__('Invalid date or time format.','apls-fluentauth-extensions')); }
        }
        return $value;
    }
    public static function persist($user_id,$data): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        Diagnostics::log('acf.persist_hook',['acf_enabled'=>(bool)self::selected(),'transaction_present'=>!empty($_POST['apls_faext_transaction'])]);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        if (!self::selected() || empty($_POST['apls_faext_transaction'])) { return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Registration is processed by FluentAuth; ACF values are validated and normalized by this adapter.
        $transaction=sanitize_text_field(wp_unslash((string)$_POST['apls_faext_transaction']));
        // FluentAuth's final action uses user_email rather than email. Trust the
        // WordPress user created by FluentAuth as the canonical identity source.
        $user = get_userdata(absint($user_id));
        $email = $user ? strtolower(sanitize_email($user->user_email)) : '';
        if (!$email) {
            Diagnostics::log('acf.persist_failure', ['reason' => 'user_email_unavailable', 'user_id' => absint($user_id)]);
            self::notify([__('Created user email unavailable for ACF persistence.','apls-fluentauth-extensions')]);
            return;
        }
        $payload=Pending_Registrations::read($transaction,$email,self::schema_hash());
        if ($payload===null) {
            Diagnostics::log('acf.persist_failure', ['reason' => 'pending_lookup_failed', 'user_id' => absint($user_id)]);
            self::notify([__('Pending registration field data was not found after user creation.','apls-fluentauth-extensions')]);
            return;
        }
        $fields=self::fields();
        $failed=0; $saved=0;
        foreach ($payload as $key=>$value) {
            if (!isset($fields[$key])) { $failed++; continue; }
            $saved_result = update_field($key,$value,'user_'.absint($user_id));
            $actual = get_field($key,'user_'.absint($user_id),false);
            // update_field() can return false for a no-op; verify the stored value.
            if ($actual === $value || (is_scalar($actual) && is_scalar($value) && (string)$actual === (string)$value) || (is_array($actual) && is_array($value) && $actual == $value)) {
                $saved++;
                Diagnostics::log('acf.field_saved', ['user_id' => absint($user_id), 'field_key' => sanitize_key($key), 'field_name' => sanitize_key($fields[$key]['name'] ?? ''), 'field_type' => sanitize_key($fields[$key]['type'] ?? ''), 'result' => $saved_result === false ? 'already_present' : 'ok']);
            } else {
                $failed++;
                Diagnostics::log('acf.field_save_failed', ['user_id' => absint($user_id), 'field_key' => sanitize_key($key), 'field_name' => sanitize_key($fields[$key]['name'] ?? ''), 'field_type' => sanitize_key($fields[$key]['type'] ?? ''), 'reason' => 'readback_mismatch']);
            }
        }
        Diagnostics::log('acf.persist_result',['user_id'=>absint($user_id),'field_count'=>count($payload),'saved_count'=>$saved,'failed_count'=>$failed]);
        if ($failed) { self::notify([__('Some ACF fields could not be confirmed after registration.','apls-fluentauth-extensions')]); return; }
        Pending_Registrations::delete($transaction);
        do_action('apls/fluentauth/acf_fields_saved',absint($user_id),array_keys($payload));
    }
}
