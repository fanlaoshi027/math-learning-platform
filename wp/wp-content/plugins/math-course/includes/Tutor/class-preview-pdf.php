<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;


class Preview_PDF
{


    public function get_pdf(
        $lesson_id
    )
    {


        return get_post_meta(

            $lesson_id,

            '_mathcourse_preview_pdf',

            true

        );


    }



    public function save_pdf(
        $lesson_id,
        $url
    )
    {


        update_post_meta(

            $lesson_id,

            '_mathcourse_preview_pdf',

            esc_url_raw(
                $url
            )

        );


    }


}