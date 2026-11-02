<?php
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Email_Validator {
    public static function validate(string $email, string $phase='server'): array {
        $o=Plugin::options();
        $result=['allowed'=>false, 'status'=>'error', 'provider'=>$o['provider'], 'message'=>__('Email validation temporarily unavailable. Please try again.', 'aplusplugins-fluentauth-extensions')];
        if (!is_email($email)) { return ['allowed'=>false,'status'=>'invalid','provider'=>'local','message'=>__('Enter a valid email address.', 'aplusplugins-fluentauth-extensions')]; }
        if ('none'===$o['provider']) { return ['allowed'=>true,'status'=>'skipped','provider'=>'none','message'=>'']; }
        $key='apls_faext_'.hash_hmac('sha256', strtolower($email).'|'.$o['provider'].'|'.md5(wp_json_encode(get_option('zerobounce_settings_validation_pass', []))), wp_salt('auth'));
        $cached=get_transient($key);
        if (is_array($cached)) { return apply_filters('apls/fluentauth/validate_email', $cached, $email, $phase); }
        if ('zerobounce'===$o['provider'] && Plugin::zb_active()) { $result=self::zerobounce($email); }
        $result=apply_filters('apls/fluentauth/validate_email', $result, $email, $phase);
        if (!is_array($result) || !isset($result['allowed']) || !is_bool($result['allowed'])) { $result=['allowed'=>false,'status'=>'error','provider'=>'external','message'=>__('Invalid validation provider response.', 'aplusplugins-fluentauth-extensions')]; }
        if ('error'===$result['status'] && 'continue'===$o['on_error']) { $result['allowed']=true; }
        if ($result['status']!=='error') { set_transient($key,$result, 15*MINUTE_IN_SECONDS); }
        Diagnostics::log('email.validation_result',['provider'=>$result['provider']??'unknown','status'=>$result['status']??'unknown','mode'=>$phase]);
        do_action('apls/fluentauth/email_validated', $email, $result, $phase);
        return $result;
    }
    private static function zerobounce(string $email): array {
        $fail=['allowed'=>false,'status'=>'error','provider'=>'zerobounce','message'=>__('Email verification service is temporarily unavailable.', 'aplusplugins-fluentauth-extensions')];
        $api_key=get_option('zerobounce_settings_api_key');
        if (!is_string($api_key) || ''===trim($api_key)) { return $fail; }
        $zone=(array)get_option('zerobounce_settings_api_zone', []);
        $url=in_array('api_usa',$zone,true) ? 'https://api-us.zerobounce.net/v2/validate' : 'https://api.zerobounce.net/v2/validate';
        $timeout=max(2,min(8,(int)get_option('zerobounce_settings_api_timeout', 8)));
        $response=wp_safe_remote_post($url,['timeout'=>$timeout,'redirection'=>0,'headers'=>['Accept'=>'application/json','Content-Type'=>'application/x-www-form-urlencoded'],'body'=>['api_key'=>$api_key,'email'=>$email,'ip_address'=>'']]);
        if (is_wp_error($response) || 200!==wp_remote_retrieve_response_code($response)) { return $fail; }
        $payload=json_decode(wp_remote_retrieve_body($response),true);
        if (!is_array($payload) || empty($payload['status']) || !is_string($payload['status'])) { return $fail; }
        $status=sanitize_key($payload['status']);
        $passes=get_option('zerobounce_settings_validation_pass', ['valid','catch-all','unknown']);
        $passes=is_array($passes) ? $passes : [];
        $block_free=(array)get_option('zerobounce_settings_block_free_email', []);
        $free=!empty($payload['free_email']) && (true===$payload['free_email'] || 'true'===$payload['free_email']);
        $allowed=in_array($status,$passes,true) && (!$free || empty($block_free));
        $message='';
        if (!$allowed) {
            $message=(string)get_option('zerobounce_settings_error_message','');
            if (''===$message) { $message=__('Please use a different email address.', 'aplusplugins-fluentauth-extensions'); }
            /* translators: Placeholder values identify the ACF field, group, or email validation detail. */
            if (!empty($payload['did_you_mean']) && get_option('zerobounce_settings_did_you_mean')) { $message.=' '.sprintf(__('Did you mean %s?', 'aplusplugins-fluentauth-extensions'), sanitize_email($payload['did_you_mean'])); }
        }
        return ['allowed'=>$allowed,'status'=>$status,'provider'=>'zerobounce','message'=>sanitize_text_field($message)];
    }
}
