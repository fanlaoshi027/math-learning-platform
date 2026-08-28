<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;


class Complete
{


    public function __construct()
    {


        add_action(

            'wp_ajax_mathcourse_complete',

            array(

                $this,

                'complete'

            )

        );


    }



    public function complete()
    {


        check_ajax_referer(

            'mathcourse_complete',

            'nonce'

        );



        if(
            !is_user_logged_in()
        ){

            wp_send_json_error(
                'not_login'
            );

        }



        $user_id =
        get_current_user_id();



        $lesson_id =
        absint(
            $_POST['lesson_id']
        );



        $course_id =
        absint(
            $_POST['course_id']
        );



        if(
            class_exists(
                'MathCourse\Learning\Complete_Service'
            )
        )
        {


            $service =
            new \MathCourse\Learning\Complete_Service();



            $service->save(

                $user_id,

                $course_id,

                $lesson_id

            );


        }



        wp_send_json_success(
            'completed'
        );


    }


}