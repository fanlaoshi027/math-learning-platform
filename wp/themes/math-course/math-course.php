<?php
/*
Plugin Name: MathCourse
Plugin URI:
Description: 数学课程管理系统
Version: 1.0.0
Author:
Author URI:
Text Domain: mathcourse
*/

defined('ABSPATH') || exit;


/*
|--------------------------------------------------------------------------
| 基础常量
|--------------------------------------------------------------------------
*/

define(
    'MATHCOURSE_VERSION',
    '1.0.0'
);


define(
    'MATHCOURSE_PATH',
    plugin_dir_path(__FILE__)
);


define(
    'MATHCOURSE_URL',
    plugin_dir_url(__FILE__)
);



/*
|--------------------------------------------------------------------------
| 自动加载
|--------------------------------------------------------------------------
*/

require_once MATHCOURSE_PATH .
'includes/class-autoloader.php';


\MathCourse\Autoloader::register();



/*
|--------------------------------------------------------------------------
| 插件激活
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    function(){

        if(
            class_exists(
                '\MathCourse\Database\Install'
            )
        ){

            \MathCourse\Database\Install::activate();

        }

    }
);



/*
|--------------------------------------------------------------------------
| 插件启动
|--------------------------------------------------------------------------
*/

add_action(
    'plugins_loaded',
    function(){

        if(
            class_exists(
                '\MathCourse\Plugin'
            )
        ){

            $plugin = new \MathCourse\Plugin();

            $plugin->run();

        }

    }
);