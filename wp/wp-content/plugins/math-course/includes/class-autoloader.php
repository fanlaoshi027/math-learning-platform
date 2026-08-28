<?php

namespace MathCourse;

defined('ABSPATH') || exit;


class Autoloader
{


    public static function register()
    {

        spl_autoload_register(
            function($class){

                $prefix = 'MathCourse\\';


                if(
                    strpos($class,$prefix)!==0
                ){

                    return;

                }


                $relative = substr(
                    $class,
                    strlen($prefix)
                );


                $file = str_replace(
                    '\\',
                    '/',
                    $relative
                );


                $file = strtolower(
                    str_replace(
                        '_',
                        '-',
                        $file
                    )
                );


                $path =
                MATHCOURSE_PATH .
                'includes/class-' .
                $file .
                '.php';


                if(
                    file_exists($path)
                ){

                    require_once $path;

                }

            }
        );

    }

}