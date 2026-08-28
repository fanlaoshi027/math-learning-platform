<?php

namespace MathCourse\Course;

defined('ABSPATH') || exit;


class Card
{


    public function render(
        $course_id
    )
    {


        $title =
        get_the_title(
            $course_id
        );



        $image =
        get_post_meta(

            $course_id,

            '_mathcourse_cover',

            true

        );



        ob_start();

        ?>


        <div class="mathcourse-card">


            <?php if($image): ?>


                <img

                src="<?php echo esc_url($image); ?>"

                class="mathcourse-cover">


            <?php else: ?>


                <div class="mathcourse-color-cover">

                    <?php echo esc_html($title); ?>

                </div>


            <?php endif; ?>



            <h3>

                <?php echo esc_html($title); ?>

            </h3>


        </div>



        <?php


        return ob_get_clean();


    }


}