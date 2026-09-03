<?php

namespace MathCourse\Demo;

defined('ABSPATH') || exit;


class Demo_Import
{


    public function __construct()
    {

        add_action(
            'admin_init',
            array(
                $this,
                'register'
            )
        );

    }



    public function register()
    {


        add_action(
            'admin_post_mathcourse_demo_import',
            array(
                $this,
                'import'
            )
        );


    }



    public function import()
    {


        if(
            !current_user_can('manage_options')
        ){

            wp_die(
                '权限不足'
            );

        }



        check_admin_referer(
            'mathcourse_demo_import'
        );



        /*
        |--------------------------------------------------------------------------
        | 演示课程数据
        |--------------------------------------------------------------------------
        */


        $course_id =
        wp_insert_post(

            array(

                'post_title'=>'八年级数学 全等三角形',

                'post_type'=>'course',

                'post_status'=>'publish'

            )

        );



        if(
            $course_id
        ){

            update_post_meta(

                $course_id,

                '_mathcourse_cover',

                ''

            );

        }



        wp_redirect(
            admin_url(
                'admin.php?page=mathcourse'
            )
        );

        exit;


    }


}