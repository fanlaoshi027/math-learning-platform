<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;


class Menu
{


    public function __construct()
    {

        add_action(
            'admin_menu',
            array(
                $this,
                'register_menu'
            )
        );

    }



    public function register_menu()
    {


        add_menu_page(

            'MathCourse',

            'MathCourse',

            'manage_options',

            'mathcourse',

            array(
                $this,
                'dashboard'
            ),

            'dashicons-welcome-learn-more',

            30

        );



        add_submenu_page(

            'mathcourse',

            '课程授权',

            '课程授权',

            'manage_options',

            'mathcourse-access',

            array(
                $this,
                'access_page'
            )

        );



        add_submenu_page(

            'mathcourse',

            '设置',

            '设置',

            'manage_options',

            'mathcourse-settings',

            array(
                $this,
                'settings_page'
            )

        );


    }



    public function dashboard()
    {

        echo '<div class="wrap">';

        echo '<h1>MathCourse</h1>';

        echo '<p>数学课程管理系统</p>';

        echo '</div>';

    }



    public function access_page()
    {

        if(
            class_exists(
                'MathCourse\Admin\Access_Page'
            )
        ){

            (new Access_Page())->render();

        }

    }



    public function settings_page()
    {

        if(
            class_exists(
                'MathCourse\Admin\Settings'
            )
        ){

            (new Settings())->render();

        }

    }


}