<?php
/** Configurable WP-Cron cleanup of expired temporary registration payloads. */
namespace APLS\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Cleanup {
    private const HOOK='apls_faext_cleanup';
    public static function register(): void {
        add_filter('cron_schedules',[self::class,'schedules']);
        add_action(self::HOOK,[Pending_Registrations::class,'cleanup']);
        add_action('init',[self::class,'ensure'],30);
    }
    private static function seconds(array $options): int {
        $multipliers=['minutes'=>60,'hours'=>3600,'days'=>DAY_IN_SECONDS,'weeks'=>WEEK_IN_SECONDS,'months'=>30*DAY_IN_SECONDS];
        return max(60,(int)($options['cleanup_every'] ?? 1)*($multipliers[$options['cleanup_unit'] ?? 'days'] ?? DAY_IN_SECONDS));
    }
    public static function schedules(array $schedules): array {
        $options=Plugin::options();
        $schedules['apls_faext_custom']=['interval'=>self::seconds($options),'display'=>__('APlusPlugins pending registration cleanup','apls-fluentauth-extensions')];
        return $schedules;
    }
    private static function midnight(): int {
        $now=new \DateTimeImmutable('now',wp_timezone());
        return $now->modify('tomorrow')->setTime(0,5)->getTimestamp();
    }
    public static function reschedule(?array $options=null): void {
        $options=$options ?? Plugin::options();
        wp_clear_scheduled_hook(self::HOOK);
        $seconds=self::seconds($options);
        // Schedule registration happens independently of the cron_schedules filter on settings save.
        add_filter('cron_schedules',static function($schedules) use ($seconds) {
            $schedules['apls_faext_custom']=['interval'=>$seconds,'display'=>'APlusPlugins pending registration cleanup'];
            return $schedules;
        },PHP_INT_MAX);
        wp_schedule_event(self::midnight(),'apls_faext_custom',self::HOOK);
    }
    public static function ensure(): void {
        if (!wp_next_scheduled(self::HOOK)) { self::reschedule(); }
        // Schema installation belongs to plugin activation, not every ordinary admin request.
        // This prevents repeated dbDelta() calls when the version option is missing.
    }
    public static function unschedule(): void { wp_clear_scheduled_hook(self::HOOK); }
}
