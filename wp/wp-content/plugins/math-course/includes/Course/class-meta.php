<?php

namespace MathCourse\Course;

defined('ABSPATH') || exit;


class Meta
{


    public function __construct()
    {


        add_action(

            'add_meta_boxes',

            array(

                $this,

                'add'

            )

        );


        add_action(

            'save_post',

            array(

                $this,

                'save'

            )

        );


    }



    public function add()
    {


        add_meta_box(

            'mathcourse_cover',

            '课程封面',

            array(

                $this,

                'box'

            ),

            'course'

        );


    }



    public function box(
        $post
    )
    {


        $value =
        get_post_meta(

            $post->ID,

            '_mathcourse_cover',

            true

        );



        ?>

        <input

        type="text"

        name="mathcourse_cover"

        value="<?php echo esc_attr($value); ?>"

        style="width:100%">


        <?php


    }



    public function save(
        $post_id
    )
    {


        if(
            isset($_POST['mathcourse_cover'])
        ){

            update_post_meta(

                $post_id,

                '_mathcourse_cover',

                esc_url_raw(
                    $_POST['mathcourse_cover']
                )

            );

        }


    }


}