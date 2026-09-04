<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;


class Video_Service
{


    public function save_video(
        $lesson_id,
        $video_url
    )
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_videos';



        return $wpdb->replace(

            $table,

            array(

                'lesson_id'=>$lesson_id,

                'video_url'=>$video_url,

                'status'=>'active',

                'created_at'=>current_time(
                    'mysql'
                )

            ),

            array(

                '%d',

                '%s',

                '%s',

                '%s'

            )

        );


    }



    public function get_video(
        $lesson_id
    )
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_videos';



        return $wpdb->get_var(

            $wpdb->prepare(

                "
                SELECT video_url
                FROM {$table}
                WHERE lesson_id=%d
                AND status='active'
                ",

                $lesson_id

            )

        );


    }


}