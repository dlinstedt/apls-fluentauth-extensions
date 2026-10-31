<?php
/** Optional, narrowly scoped FluentCommunity auth entry-point compatibility. */
namespace APLS\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Community_Redirect {
    public static function register(): void {
        add_action('fluent_community/auth/before_auth_page_process', [self::class, 'maybe_redirect'], 5, 2);
    }
    /** Reject external, protocol-relative, ambiguous and auth-loop destinations. */
    public static function sanitize_destination($value, string $community_path = '/community'): string {
        if (!is_string($value) || $value === '' || strlen($value) > 1024 || $value[0] !== '/' || strncmp($value, '//', 2) === 0) { return ''; }
        if (strpos($value, chr(92)) !== false || preg_match('/[\x00-\x1f\x7f#]/', $value)) { return ''; }
        $parts = wp_parse_url($value);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) { return ''; }
        $path = $parts['path'] ?? '';
        if ($path === '' || str_contains(strtolower($path), '%2f') || str_contains(strtolower($path), '%5c') || str_contains($path, '/./') || str_contains($path, '/../') || str_ends_with($path, '/..') || str_ends_with($path, '/.')) { return ''; }
        if (rtrim($path, '/') === rtrim($community_path, '/')) { return ''; }
        if (preg_match('~^/(wp-admin|wp-login\.php|wp-json)(?:/|$)~i', $path)) { return ''; }
        return $value;
    }
    /** Pure decision function for integration and regression testing. */
    public static function destination(string $method, string $path, array $query, string $community_path = '/community'): string {
        if (strtoupper($method) !== 'GET') { return ''; }
        if (rtrim($path, '/') !== rtrim($community_path, '/')) { return ''; }
        if (!isset($query['fcom_action']) || !is_string($query['fcom_action']) || $query['fcom_action'] !== 'auth') { return ''; }
        // Authentication flows with state must never be dropped or redirected.
        foreach (['invitation_token','token','code','redirect_to','callback','nonce','_wpnonce','action'] as $reserved) {
            if (isset($query[$reserved])) { return ''; }
        }
        $allowed = ['fcom_action'=>true,'form'=>true];
        foreach (array_keys($query) as $key) { if (!isset($allowed[$key])) { return ''; } }
        if (!isset($query['form'])) { return '/login/'; }
        if (!is_string($query['form'])) { return ''; }
        if ($query['form'] === 'register') { return '/login/?action=register'; }
        if ($query['form'] === 'login') { return '/login/'; }
        return '';
    }
    public static function maybe_redirect($user_id = 0, $invitation = null): void {
        if (empty(Plugin::options()['community_auth_redirect']) || !Plugin::community_active()) { return; }
        if ($invitation || $user_id || is_user_logged_in() || is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) { return; }
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        $path = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH) : '';
        if (!is_string($path)) { return; }
        // Community base route is configurable by FluentCommunity. Explicitly
        // prefer the current portal route, falling back to /community only.
        $portal = (string) apply_filters('apls/fluentauth/community_portal_path', '/community');
        if (!preg_match('~^/[A-Za-z0-9/_-]+$~D', $portal)) { return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL routing; never processes submitted form data.
        $query = wp_unslash($_GET);
        if (!is_array($query)) { return; }
        $destination = self::destination($method, $path, $query, $portal);
        if ($destination === '') { return; }
        $options = Plugin::options();
        // Select by the recognized source query, not by customizable target URLs.
        $key = isset($query['form']) && $query['form'] === 'register' ? 'community_register_destination' : 'community_login_destination';
        $destination = self::sanitize_destination($options[$key] ?? '', $portal);
        if ($destination === '') { return; }
        $url = home_url($destination);
        if (!wp_validate_redirect($url, false)) { return; }
        wp_safe_redirect($url, 302, 'APlusPlugins FluentAuth Extensions');
        exit;
    }
}
