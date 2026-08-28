<?php

namespace MathCourse;

defined('ABSPATH') || exit;

class DB_Upgrader
{
    private const VERSION = '1.1.0';

    public function maybe_upgrade()
    {
        $installed = get_option('mathcourse_db_version', '1.0.0');
        if (version_compare($installed, self::VERSION, '>=')) {
            return;
        }

        $this->upgrade_v110();
        update_option('mathcourse_db_version', self::VERSION, false);
    }

    private function upgrade_v110()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mathcourse_videos';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) {
            return;
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $columns = array_map('strval', $columns);

        if (!in_array('hls_path', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN hls_path text NULL AFTER video_url");
        }

        if (!in_array('aes_key_path', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN aes_key_path text NULL AFTER hls_path");
        }
    }
}
