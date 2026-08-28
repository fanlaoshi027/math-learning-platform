<?php

namespace MathCourse\Access;

defined('ABSPATH') || exit;


class Access_Service
{


    public function has_access(
        $user_id,
        $course_id
    )
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_access';



        $result =
        $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT COUNT(*)
                FROM {$table}
                WHERE user_id=%d
                AND course_id=%d
                AND status='active'
                ",

                $user_id,

                $course_id

            )

        );



        return $result > 0;


    }



    public function grant(
        $user_id,
        $course_id
    )
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_access';



        return $wpdb->replace(

            $table,

            array(

                'user_id'=>$user_id,

                'course_id'=>$course_id,

                'status'=>'active',

                'created_at'=>current_time('mysql')

            )

        );


    }


}