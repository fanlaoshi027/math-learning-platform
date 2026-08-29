<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

class Access_Page {


    /**
     * 渲染授权页面
     */
    public function render() {


        if (!current_user_can('manage_options')) {

            return;

        }


        $service = new Access_Service();


        /*
         * 处理授权操作
         */
        $this->handle_action($service);



        echo '<div class="wrap">';

        echo '<h1>学员授权管理</h1>';



        echo '<form method="get">';

        echo '<input type="hidden" name="page" value="mathcourse-access">';


        echo '<input 
            type="text"
            name="s"
            value="' .
            esc_attr(
                $_GET['s'] ?? ''
            )
            . '"
            placeholder="搜索用户名/邮箱">';



        echo '<button class="button">搜索</button>';


        echo '</form>';



        echo '<hr>';



        $this->render_table($service);



        echo '</div>';

    }




    /**
     * 处理授权动作
     */
    private function handle_action($service) {



        if (
            empty($_GET['action_type']) ||
            empty($_GET['user_id']) ||
            empty($_GET['course_id'])
        ) {

            return;

        }



        if (
            !isset($_GET['_wpnonce']) ||
            !wp_verify_nonce(
                $_GET['_wpnonce'],
                'mathcourse_access_action'
            )
        ) {

            return;

        }



        $user_id =
            absint($_GET['user_id']);


        $course_id =
            absint($_GET['course_id']);



        if (
            $_GET['action_type']
            ===
            'grant'
        ) {


            $service->grant(
                $user_id,
                $course_id
            );


        }



        if (
            $_GET['action_type']
            ===
            'revoke'
        ) {


            $service->revoke(
                $user_id,
                $course_id
            );


        }


    }





    /**
     * 表格
     */
    private function render_table($service) {



        $keyword =
            sanitize_text_field(
                $_GET['s'] ?? ''
            );



        $users =
            get_users(
                array(
                    'search' =>
                        $keyword
                        ?
                        '*' . $keyword . '*'
                        :
                        '*',

                    'number'=>20
                )
            );



        echo '<table class="widefat striped">';


        echo '<thead>';

        echo '<tr>';

        echo '<th>学员</th>';

        echo '<th>课程</th>';

        echo '<th>状态</th>';

        echo '<th>操作</th>';

        echo '</tr>';

        echo '</thead>';



        echo '<tbody>';



        foreach ($users as $user) {


            $courses =
                get_posts(
                    array(
                        'post_type'=>'courses',
                        'posts_per_page'=>20
                    )
                );



            foreach ($courses as $course) {



                $info =
                    $service->get_access_info(
                        $user->ID,
                        $course->ID
                    );



                echo '<tr>';



                echo '<td>';

                echo esc_html(
                    $user->display_name
                );

                echo '<br>';

                echo '<small>';

                echo esc_html(
                    $user->user_email
                );

                echo '</small>';

                echo '</td>';




                echo '<td>';

                echo esc_html(
                    $course->post_title
                );

                echo '</td>';




                echo '<td>';

                echo esc_html(
                    $info['status']
                );

                echo '</td>';




                echo '<td>';



                $nonce =
                    wp_create_nonce(
                        'mathcourse_access_action'
                    );



                if (
                    $info['access']
                ) {


                    echo '<a class="button" href="' .
                        esc_url(
                            add_query_arg(
                                array(
                                    'page'
                                    =>
                                    'mathcourse-access',

                                    'action_type'
                                    =>
                                    'revoke',

                                    'user_id'
                                    =>
                                    $user->ID,

                                    'course_id'
                                    =>
                                    $course->ID,

                                    '_wpnonce'
                                    =>
                                    $nonce
                                )
                            )
                        )
                        .
                        '">
                        取消授权
                        </a>';



                } else {



                    echo '<a class="button button-primary" href="' .
                        esc_url(
                            add_query_arg(
                                array(
                                    'page'
                                    =>
                                    'mathcourse-access',

                                    'action_type'
                                    =>
                                    'grant',

                                    'user_id'
                                    =>
                                    $user->ID,

                                    'course_id'
                                    =>
                                    $course->ID,

                                    '_wpnonce'
                                    =>
                                    $nonce
                                )
                            )
                        )
                        .
                        '">
                        授权
                        </a>';



                }



                echo '</td>';



                echo '</tr>';

            }

        }



        echo '</tbody>';

        echo '</table>';

    }


}
