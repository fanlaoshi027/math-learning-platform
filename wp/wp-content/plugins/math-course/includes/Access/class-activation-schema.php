<?php
namespace MathCourse\Access;
defined('ABSPATH') || exit;

class Activation_Schema {
    const VERSION = '1.0.0';
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'mathcourse_activation_codes';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code_hash char(64) NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'unused',
            created_at datetime NOT NULL,
            expires_at datetime NULL,
            used_by bigint(20) unsigned NULL,
            used_at datetime NULL,
            PRIMARY KEY (id), UNIQUE KEY code_hash (code_hash), KEY course_id (course_id), KEY status (status), KEY used_by (used_by)
        ) {$charset};";
        dbDelta($sql);
        update_option('mathcourse_activation_db_version', self::VERSION, false);
    }
}
