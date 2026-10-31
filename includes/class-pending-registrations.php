<?php
/** Temporary storage for verified-registration data. */
namespace APLS\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }
final class Pending_Registrations {
    public static function table(): string { global $wpdb; return $wpdb->prefix.'apls_faext_pending'; }
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=self::table(); $charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n transaction_hash char(64) NOT NULL,\n email_hash char(64) NOT NULL,\n schema_hash char(64) NOT NULL,\n payload longtext NOT NULL,\n expires_at datetime NOT NULL,\n created_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY transaction_hash (transaction_hash),\n KEY expires_at (expires_at)\n) $charset;");
        update_option('apls_faext_db_version','1',false);
    }
    private static function key(string $transaction): string { return hash_hmac('sha256',$transaction,wp_salt('auth')); }
    private static function email(string $email): string { return hash_hmac('sha256',$email,wp_salt('secure_auth')); }
    public static function store(string $transaction,string $email,string $schema,array $payload): bool {
        global $wpdb;
        // Reject unexpectedly large or deeply nested public registration payloads.
        // Do not persist personally identifying values in debug logs.
        if (count($payload)>64) { return false; }
        $encoded=wp_json_encode($payload);
        if (!is_string($encoded) || strlen($encoded)>16384) { return false; }
        $table=self::table();
        $data=['transaction_hash'=>self::key($transaction),'email_hash'=>self::email($email),'schema_hash'=>$schema,'payload'=>$encoded,'expires_at'=>gmdate('Y-m-d H:i:s',time()+900),'created_at'=>gmdate('Y-m-d H:i:s')];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dedicated transient registration table; atomic storage and expiration operations must use the database.
        $started=microtime(true); $result=$wpdb->replace($table,$data,['%s','%s','%s','%s','%s','%s']);
        Diagnostics::log('pending.insert',['operation'=>'REPLACE','table'=>'pending','result'=>$result===false?'failed':'ok','rows'=>$result===false?0:$result,'elapsed_ms'=>round((microtime(true)-$started)*1000,2)]);
        return false!==$result;
    }
    public static function read(string $transaction,string $email,string $schema): ?array {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/',$transaction)) { return null; }
        $table=self::table();
        $started=microtime(true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dedicated transient registration table; atomic storage and expiration operations must use the database.
        $row=$wpdb->get_row($wpdb->prepare("SELECT payload FROM %i WHERE transaction_hash = %s AND email_hash = %s AND schema_hash = %s AND expires_at > %s LIMIT 1",$table,self::key($transaction),self::email($email),$schema,gmdate('Y-m-d H:i:s')));
        Diagnostics::log('pending.select',['operation'=>'SELECT','table'=>'pending','found'=>(bool)$row,'elapsed_ms'=>round((microtime(true)-$started)*1000,2)]);
        if (!$row) { return null; }
        $value=json_decode($row->payload,true);
        return is_array($value) ? $value : null;
    }
    public static function valid(string $transaction,string $email,string $schema): bool { return null!==self::read($transaction,$email,$schema); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dedicated transient registration table; atomic storage and expiration operations must use the database.
    public static function delete(string $transaction): void { global $wpdb; $n=$wpdb->delete(self::table(),['transaction_hash'=>self::key($transaction)],['%s']); Diagnostics::log('pending.delete',['operation'=>'DELETE','result'=>$n===false?'failed':'ok','rows'=>$n===false?0:$n]); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dedicated transient registration table; atomic storage and expiration operations must use the database.
    public static function cleanup(): void { global $wpdb; $table=self::table(); $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE expires_at < %s",$table,gmdate('Y-m-d H:i:s'))); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dedicated transient registration table; atomic storage and expiration operations must use the database.
    public static function drop(): void { global $wpdb; $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', self::table())); }
}
