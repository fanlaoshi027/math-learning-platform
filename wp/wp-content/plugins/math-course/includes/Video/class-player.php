<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;


class Player
{


    public function __construct()
    {


        add_shortcode(

            'mathcourse_video',

            array(

                $this,

                'render'

            )

        );


    }



    public function render(
        $atts
    )
    {


        $atts =
        shortcode_atts(

            array(

                'url'=>''

            ),

            $atts

        );



        if(
            empty($atts['url'])
        ){

            return '';

        }



        ob_start();


        ?>

        <video

        class="mathcourse-player"

        controls

        preload="metadata">


            <source

            src="<?php echo esc_url($atts['url']); ?>"

            type="application/x-mpegURL">


        </video>


        <?php


        return ob_get_clean();


    }


}