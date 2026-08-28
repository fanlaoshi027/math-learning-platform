<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;


class Hooks
{


    public function __construct()
    {


        add_filter(

            'the_content',

            array(

                $this,

                'protect_content'

            )

        );


    }



    public function protect_content(
        $content
    )
    {


        if(
            !is_singular()
        ){

            return $content;

        }



        global $post;



        if(
            !$post
        ){

            return $content;

        }



        /*
        |--------------------------------------------------------------------------
        | 试听直接放行
        |--------------------------------------------------------------------------
        */


        $preview =
        get_post_meta(

            $post->ID,

            '_mathcourse_preview',

            true

        );



        if(
            $preview === 'yes'
        ){

            return $content;

        }



        /*
        |--------------------------------------------------------------------------
        | 正式课程授权检查
        |--------------------------------------------------------------------------
        */


        if(
            is_user_logged_in()
        ){


            $user_id =
            get_current_user_id();



            $course_id =
            get_post_meta(

                $post->ID,

                '_tutor_course_id',

                true

            );



            if(
                class_exists(
                    'MathCourse\Access\Access_Service'
                )
            ){


                $service =
                new \MathCourse\Access\Access_Service();



                if(
                    $service->has_access(

                        $user_id,

                        $course_id

                    )
                ){

                    return $content;

                }


            }


        }



        return '

        <div class="mathcourse-lock">

        本课程需要授权后学习，请联系老师开通。

        </div>';



    }


}