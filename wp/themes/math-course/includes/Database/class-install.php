<?php

namespace MathCourse\Database;

defined('ABSPATH') || exit;


class Install
{


    public static function activate()
    {


        global $wpdb;


        $charset =
        $wpdb->get_charset_collate();



        /*
        |--------------------------------------------------------------------------
        | 课程授权表
        |--------------------------------------------------------------------------
        */


        $access =
        $wpdb->prefix .
        'mathcourse_access';



        $sql1 = "

        CREATE TABLE {$access} (

            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            user_id bigint(20) unsigned NOT NULL,

            course_id bigint(20) unsigned NOT NULL,

            status varchar(20) NOT NULL DEFAULT 'active',

            created_at datetime NOT NULL,

            PRIMARY KEY(id),

            UNIQUE KEY user_course(user_id,course_id)

        ) {$charset};

        ";



        /*
        |--------------------------------------------------------------------------
        | 完成记录表
        |--------------------------------------------------------------------------
        */


        $learning =
        $wpdb->prefix .
        'mathcourse_learning';



        $sql2 = "

        CREATE TABLE {$learning} (

            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            user_id bigint(20) unsigned NOT NULL,

            course_id bigint(20) unsigned NOT NULL,

            lesson_id bigint(20) unsigned NOT NULL,

            completed_time datetime NOT NULL,

            PRIMARY KEY(id),

            UNIQUE KEY user_lesson(user_id,lesson_id)

        ) {$charset};

        ";



        /*
        |--------------------------------------------------------------------------
        | 视频表
        |--------------------------------------------------------------------------
        */


        $video =
        $wpdb->prefix .
        'mathcourse_videos';



        $sql3 = "

        CREATE TABLE {$video} (

            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            lesson_id bigint(20) unsigned NOT NULL,

            video_url text NOT NULL,

            status varchar(20) NOT NULL DEFAULT 'active',

            created_at datetime NOT NULL,

            PRIMARY KEY(id),

            UNIQUE KEY lesson_id(lesson_id)

        ) {$charset};

        ";



        require_once ABSPATH .
        'wp-admin/includes/upgrade.php';



        dbDelta($sql1);

        dbDelta($sql2);

        dbDelta($sql3);


    }


}