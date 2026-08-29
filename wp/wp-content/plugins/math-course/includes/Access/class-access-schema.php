<?php
namespace MathCourse\Access;
defined('ABSPATH') || exit;

class Access_Schema {
    const VERSION = '1.0.0';

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'mathcourse_access';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            granted_at datetime NOT NULL,
            expires_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_course (user_id,course_id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset};";
        dbDelta($sql);
        update_option('mathcourse_access_db_version', self::VERSION, false);
    }
}
