<?php
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/class-diagnostics.php';
require_once __DIR__ . '/class-settings.php';
require_once __DIR__ . '/class-email-validator.php';
require_once __DIR__ . '/class-registration.php';
require_once __DIR__ . '/class-crm.php';
require_once __DIR__ . '/class-wpfusion.php';
require_once __DIR__ . '/class-pending-registrations.php';
require_once __DIR__ . '/class-acf.php';
require_once __DIR__ . '/class-cleanup.php';
require_once __DIR__ . '/class-community-redirect.php';
final class Plugin {
    private static bool $booted = false;
    public static function boot(): void {
        if (self::$booted) { return; }
        self::$booted = true;
        if (!defined('FLUENT_SECURITY_VERSION') && !class_exists('FluentAuth\App\Services\AuthService')) {
            add_action('admin_notices', static function (): void {
                if (current_user_can('activate_plugins')) { echo '<div class="notice notice-error"><p>' . esc_html__('APlusPlugins FluentAuth Extensions requires the active FluentAuth (Fluent Security) plugin.', 'aplusplugins-fluentauth-extensions') . '</p></div>'; }
            });
            return;
        }
        Settings::register();
        Cleanup::register();
        ACF_Adapter::register();
        Registration::register();
        Community_Redirect::register();
        WP_Fusion_Adapter::register();
    }
    public static function activate(): void { Pending_Registrations::install(); Cleanup::reschedule(); }
    public static function deactivate(): void { Cleanup::unschedule(); $o=self::options(); if (!empty($o['drop_pending_on_deactivate'])) { Pending_Registrations::drop(); delete_option('apls_faext_db_version'); } }
    public static function options(): array { return wp_parse_args((array) get_option('apls_faext_options', []), Settings::defaults()); }
    public static function zb_active(): bool { return self::active_slug('zerobounce/zerobounce.php') || class_exists('Zerobounce_Email_Validator_API'); }
    public static function wpf_active(): bool { return function_exists('wp_fusion') && function_exists('wpf_get_option'); }
    public static function crm_active(): bool { return function_exists('FluentCrmApi'); }
    public static function community_active(): bool { return defined('FLUENT_COMMUNITY_PLUGIN_VERSION') || class_exists('FluentCommunity\App\App'); }
    private static function active_slug(string $slug): bool { return in_array($slug, (array) get_option('active_plugins', []), true) || (is_multisite() && isset(get_site_option('active_sitewide_plugins', [])[$slug])); }
}
