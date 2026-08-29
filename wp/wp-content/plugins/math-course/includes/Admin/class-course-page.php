<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;


class Course_Page {


    /**
     * 后台课程列表
     */
    public function render() {


        if (!current_user_can('manage_options')) {

            return;

        }


        echo '<div class="wrap">';

        echo '<h1>课程管理</h1>';


        echo '<table class="widefat striped">';


        echo '<thead>';

        echo '<tr>';

        echo '<th>课程</th>';

        echo '<th>封面</th>';

        echo '<th>课时</th>';

        echo '<th>状态</th>';

        echo '<th>操作</th>';

        echo '</tr>';

        echo '</thead>';



        echo '<tbody>';



        foreach ($this->get_courses() as $course) {


            $course_id =
                $course->ID;



            echo '<tr>';



            /**
             * 课程名称
             */
            echo '<td>';

            echo '<strong>';

            echo esc_html(
                $course->post_title
            );

            echo '</strong>';

            echo '</td>';




            /**
             * 封面
             */
            echo '<td>';


            $cover =
                get_the_post_thumbnail_url(
                    $course_id,
                    'thumbnail'
                );


            if ($cover) {


                echo '<img src="' .
                    esc_url($cover)
                    .
                    '" width="80">';


            } else {


                echo '-';


            }


            echo '</td>';





            /**
             * 课时数量
             */
            echo '<td>';


            echo esc_html(
                $this->lesson_count(
                    $course_id
                )
            );


            echo '</td>';





            /**
             * 状态
             */
            echo '<td>';

            echo esc_html(
                ucfirst(
                    $course->post_status
                )
            );

            echo '</td>';






            /**
             * 编辑
             */
            echo '<td>';


            echo '<a class="button" href="' .
                esc_url(
                    admin_url(
                        'admin.php?page=mathcourse-course-edit&course_id=' .
                        $course_id
                    )
                )
                .
                '">编辑</a>';



            echo '</td>';



            echo '</tr>';


        }




        echo '</tbody>';

        echo '</table>';



        echo '</div>';

    }





    /**
     * 获取 Tutor LMS课程
     */
    private function get_courses() {


        $args = array(

            'post_type'=>array(

                'courses',

                'tutor_course'

            ),

            'post_status'=>array(

                'publish',

                'draft'

            ),

            'posts_per_page'=>50

        );



        return get_posts($args);


    }





    /**
     * 统计课时
     */
    private function lesson_count($course_id) {



        $lessons =
            get_posts(

                array(

                    'post_type'=>'lesson',

                    'post_parent'=>$course_id,

                    'numberposts'=>-1

                )

            );



        return count($lessons);


    }


}
