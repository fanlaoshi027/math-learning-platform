<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;


class Lesson_Access
{


    public function can_view(
        $user_id,
        $course_id
    )
    {


        /*
        |--------------------------------------------------------------------------
        | 登录检查
        |--------------------------------------------------------------------------
        */


        if(
            !$user_id
        ){

            return false;

        }



        /*
        |--------------------------------------------------------------------------
        | 调用授权服务
        |--------------------------------------------------------------------------
        */


        if(
            class_exists(
                'MathCourse\Access\Access_Service'
            )
        ){


            $service =
            new \MathCourse\Access\Access_Service();


            return $service->has_access(

                $user_id,

                $course_id

            );


        }



        return false;


    }


}