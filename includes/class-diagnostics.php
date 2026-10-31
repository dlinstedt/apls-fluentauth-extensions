<?php
/** Privacy-minimized, administrator-controlled registration diagnostics. */
namespace APLS\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Diagnostics {
    private const MAX_SIZE = 2097152;
    private const MAX_VIEW = 131072;
    public static function enabled(): bool { return !empty(Plugin::options()['debug_registration']); }
    private static function directory(): string { return WP_CONTENT_DIR . '/apls-faext-logs'; }
    private static function path(): string { return self::directory() . '/events.php'; }
    /** Initialize WordPress filesystem API without interactive credential prompts. */
    private static function filesystem() {
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!$wp_filesystem && !WP_Filesystem()) { return null; }
        return $wp_filesystem;
    }
    private static function prepare_directory($fs): bool {
        $dir = self::directory();
        if (!$fs->is_dir($dir) && !$fs->mkdir($dir, FS_CHMOD_DIR)) { return false; }
        $guards = [
            '/.htaccess' => "Require all denied\nDeny from all\n",
            '/web.config' => '<configuration><system.webServer><security><authorization><add accessType="Deny" users="*" /></authorization></security></system.webServer></configuration>',
            '/index.php' => '<?php exit;',
        ];
        foreach ($guards as $name => $contents) {
            if (!$fs->exists($dir . $name) && !$fs->put_contents($dir . $name, $contents, FS_CHMOD_FILE)) { return false; }
        }
        return true;
    }
    public static function log(string $event, array $metadata=[]): void {
        if (!self::enabled()) { return; }
        if ((Plugin::options()['debug_detail'] ?? 'basic') !== 'detailed' && (str_starts_with($event,'pending.') || $event==='acf.persist_result')) { return; }
        $allowed=['login_hook','acf_selected','marketing','group_count','active','errors','html_bytes','fields_count','verification_step','user_created','acf_enabled','transaction_present','mode','operation','result','rows','found','field_count','saved_count','failed_count','provider','status','source','cache','contact_created','tags_count','reason','elapsed_ms','table','id','user_id','field_key','field_name','field_type','marketing_opt_in','previous_status','requested_status','subscription_status'];
        $metadata=array_intersect_key($metadata,array_flip($allowed));
        foreach ($metadata as $key=>$value) {
            if (!is_scalar($value) && $value!==null) { unset($metadata[$key]); continue; }
            if (is_string($value)) { $metadata[$key]=substr(sanitize_text_field($value),0,100); }
        }
        $fs = self::filesystem();
        if (!$fs || !self::prepare_directory($fs)) { return; }
        $file = self::path();
        $header = "<?php exit; ?>\n";
        $line = wp_json_encode(['time'=>gmdate('c'),'event'=>sanitize_key(str_replace('.','_',$event)),'details'=>$metadata],JSON_UNESCAPED_SLASHES) . "\n";
        if (!is_string($line)) { return; }
        $existing = $fs->exists($file) ? $fs->get_contents($file) : $header;
        if (!is_string($existing) || !str_starts_with($existing, $header)) { $existing = $header; }
        if (strlen($existing) + strlen($line) > self::MAX_SIZE) {
            $fs->move($file, self::directory() . '/events.previous.php', true);
            $existing = $header;
        }
        // WP_Filesystem does not provide atomic append. Diagnostics are best-effort,
        // bounded, and must never interrupt registration if a write fails.
        $fs->put_contents($file, $existing . $line, FS_CHMOD_FILE);
    }
    public static function status(): array {
        $fs = self::filesystem();
        if (!$fs) { return ['exists'=>false,'writable'=>false,'bytes'=>0]; }
        $file = self::path();
        $dir = self::directory();
        $exists = $fs->is_file($file);
        return ['exists'=>$exists,'writable'=>$exists ? $fs->is_writable($file) : ($fs->is_dir($dir) ? $fs->is_writable($dir) : $fs->is_writable(WP_CONTENT_DIR)), 'bytes'=>$exists ? (int)$fs->size($file) : 0];
    }
    public static function read(): string {
        $fs = self::filesystem();
        if (!$fs || !$fs->is_file(self::path())) { return ''; }
        $contents = $fs->get_contents(self::path());
        if (!is_string($contents)) { return ''; }
        if (strlen($contents)>self::MAX_VIEW) {
            $contents=substr($contents,-self::MAX_VIEW);
            $end=strpos($contents,"\n");
            if ($end!==false) { $contents=substr($contents,$end+1); }
        }
        return (string) preg_replace('/^<\?php exit; \?>\n?/', '', $contents);
    }
    public static function clear(): bool {
        $fs = self::filesystem();
        if (!$fs) { return false; }
        if (!$fs->is_file(self::path())) { return true; }
        return (bool) $fs->put_contents(self::path(), "<?php exit; ?>\n", FS_CHMOD_FILE);
    }
    public static function register_routes(): void {
        register_rest_route('apls-fluentauth/v1','/diagnostics',[
            ['methods'=>\WP_REST_Server::READABLE,'callback'=>static function () { return rest_ensure_response(['log'=>self::read(),'status'=>self::status()]); },'permission_callback'=>static function () {return current_user_can('manage_options');}],
            ['methods'=>\WP_REST_Server::DELETABLE,'callback'=>static function () {return rest_ensure_response(['success'=>self::clear(),'log'=>self::read(),'status'=>self::status()]);},'permission_callback'=>static function () {return current_user_can('manage_options');}]
        ]);
    }
}
