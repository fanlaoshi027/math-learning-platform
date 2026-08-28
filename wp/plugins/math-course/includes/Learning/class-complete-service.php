<?php

namespace MathCourse\Learning;

defined('ABSPATH') || exit;


class Complete_Service
{


    public function save(
        $user_id,
        $course_id,
        $lesson_id
    )
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_learning';



        return $wpdb->replace(

            $table,

            array(

                'user_id'=>$user_id,

                'course_id'=>$course_id,

                'lesson_id'=>$lesson_id,

                'completed_time'=>current_time(
                    'mysql'
                )

            ),

            array(

                '%d',

                '%d',

                '%d',

                '%s'

            )

        );


    }




    public function is_complete(
        $user_id,
        $lesson_id
    )
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_learning';



        $count =
        $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT COUNT(*)
                FROM {$table}
                WHERE user_id=%d
                AND lesson_id=%d
                ",

                $user_id,

                $lesson_id

            )

        );



        return $count > 0;


    }


}