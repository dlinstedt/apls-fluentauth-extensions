<?php
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
/** Consent bridge. WP Fusion owns contact creation and its regular registration tags. */
final class WP_Fusion_Adapter {
    public static function register(): void {
        // WP Fusion may create its contact before FluentAuth's completion hook.
        // Revisit consent-specific tags when its contact-creation event fires.
        add_action('wpf_user_created', [self::class, 'on_contact_created'], 20, 2);
    }
    public static function on_contact_created($user_id, $contact_id): void {
        if ('wpfusion' !== (Plugin::options()['crm_provider'] ?? 'fluentcrm')) { return; }
        $user_id = absint($user_id);
        // No marketing tag without the explicit, completed signup consent marker.
        if (!$user_id || !get_user_meta($user_id, '_apls_faext_marketing_consent_at', true)) { return; }
        if (!get_user_meta($user_id, '_apls_faext_completed', true)) { return; }
        self::apply_marketing($user_id, ['marketing_consent' => true, 'source' => 'fluentauth']);
    }
    public static function apply_marketing(int $user_id, array $context): void {
        if (empty($context['marketing_consent']) || !Plugin::wpf_active() || !Settings::marketing_enabled(Plugin::options())) { return; }
        if (get_user_meta($user_id, '_apls_faext_wpf_marketing_done', true)) { return; }
        $tags = array_values(array_filter(array_map('strval', (array)(Plugin::options()['wpf_marketing_tags'] ?? []))));
        if (!$tags) { return; }
        try {
            $service = wp_fusion();
            if (!$service || !isset($service->user) || !$service->user->get_contact_id($user_id)) { return; }
            $result = $service->user->apply_tags($tags, $user_id);
            if ($result) {
                update_user_meta($user_id, '_apls_faext_wpf_marketing_done', gmdate('Y-m-d H:i:s'));
                do_action('apls/fluentauth/crm_sync_completed', $user_id, $result, $context);
            }
        } catch (\Throwable $error) {
            do_action('apls/fluentauth/crm_sync_failed', $user_id, $error->getMessage());
        }
    }
}
