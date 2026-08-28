<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;


class Preview
{


    public function is_preview(
        $lesson_id
    )
    {


        /*
        |--------------------------------------------------------------------------
        | 试听标记
        |--------------------------------------------------------------------------
        |
        | 后续可连接 Tutor LMS meta
        |
        */


        $preview =
        get_post_meta(

            $lesson_id,

            '_mathcourse_preview',

            true

        );



        return $preview === 'yes';


    }



    public function enable_preview(
        $lesson_id
    )
    {


        update_post_meta(

            $lesson_id,

            '_mathcourse_preview',

            'yes'

        );


    }


}