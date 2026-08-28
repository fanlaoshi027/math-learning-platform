<?php

namespace MathCourse;

defined('ABSPATH') || exit;


class Autoloader
{

    public static function register()
    {
        spl_autoload_register(
            function ($class) {

                $prefix = 'MathCourse\\';

                if (strpos($class, $prefix) !== 0) {
                    return;
                }

                $relative = substr($class, strlen($prefix));

                $parts = explode('\\', $relative);
                $class_name = array_pop($parts);

                // MathCourse\\Admin\\Menu
                // -> includes/Admin/class-menu.php
                $directory = MATHCOURSE_PATH . 'includes/';

                if (!empty($parts)) {
                    $directory .= implode('/', $parts) . '/';
                }

                $file = 'class-' . strtolower(
                    str_replace('_', '-', $class_name)
                ) . '.php';

                $path = $directory . $file;

                if (file_exists($path)) {
                    require_once $path;
                }
            }
        );
    }
}
