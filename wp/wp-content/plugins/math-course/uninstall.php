<?php

defined('WP_UNINSTALL_PLUGIN') || exit;


/*
|--------------------------------------------------------------------------
| 删除插件数据
|--------------------------------------------------------------------------
|
| 默认保守模式：
| 不删除课程授权和学习记录
|
| 防止误删学生数据
|
*/


// 如以后需要彻底清理，可开启下面代码


/*
global $wpdb;


$tables = array(

    $wpdb->prefix . 'mathcourse_access',

    $wpdb->prefix . 'mathcourse_learning',

    $wpdb->prefix . 'mathcourse_videos'

);



foreach($tables as $table){

    $wpdb->query(
        "DROP TABLE IF EXISTS {$table}"
    );

}
*/