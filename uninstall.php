<?php
/**
 * Uninstall APlusPlugins FluentAuth Registration Extensions.
 *
 * @package APlusPlugins\FluentAuthExtensions
 */
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

/** Delete this plugin's own optional data only. Never touch dependency data. */
function apls_faext_uninstall_site(): void {
    $options = (array) get_option('apls_faext_options', []);
    if (!empty($options['delete_consent_on_uninstall'])) {
        delete_metadata('user', 0, '_apls_faext_marketing_consent_at', '', true);
        delete_metadata('user', 0, '_apls_faext_completed', '', true);
        delete_metadata('user', 0, '_apls_faext_wpf_marketing_done', '', true);
    }
    wp_clear_scheduled_hook('apls_faext_cleanup');
    if (!empty($options['drop_pending_on_uninstall'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'apls_faext_pending';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit administrator-approved removal of the plugin-owned pending table.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
        delete_option('apls_faext_db_version');
    }
    if (!empty($options['delete_settings_on_uninstall'])) {
        delete_option('apls_faext_options');
    }
}

if (is_multisite() && is_network_admin()) {
    $apls_faext_sites = get_sites(['fields' => 'ids']);
    foreach ($apls_faext_sites as $apls_faext_site_id) {
        switch_to_blog((int) $apls_faext_site_id);
        apls_faext_uninstall_site();
        restore_current_blog();
    }
} else {
    apls_faext_uninstall_site();
}
