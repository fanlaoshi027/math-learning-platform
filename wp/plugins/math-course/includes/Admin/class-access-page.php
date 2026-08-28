<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;


class Access_Page
{


    public function render()
    {


        if(
            !current_user_can('manage_options')
        ){

            return;

        }



        if(
            isset($_POST['mathcourse_access_save'])
        ){

            check_admin_referer(
                'mathcourse_access_action',
                'mathcourse_access_nonce'
            );


            $this->save();

        }


        ?>

        <div class="wrap">

            <h1>
                课程授权
            </h1>


            <form method="post">


                <?php

                wp_nonce_field(
                    'mathcourse_access_action',
                    'mathcourse_access_nonce'
                );

                ?>


                <table class="form-table">


                    <tr>

                        <th>
                            学生ID
                        </th>

                        <td>

                            <input
                            type="number"
                            name="user_id"
                            required>

                        </td>

                    </tr>



                    <tr>

                        <th>
                            课程ID
                        </th>

                        <td>

                            <input
                            type="number"
                            name="course_id"
                            required>

                        </td>

                    </tr>


                </table>



                <?php

                submit_button(
                    '开通课程',
                    'primary',
                    'mathcourse_access_save'
                );

                ?>


            </form>


        </div>


        <?php


    }



    private function save()
    {


        global $wpdb;


        $table =
        $wpdb->prefix .
        'mathcourse_access';



        $wpdb->replace(

            $table,

            array(

                'user_id'=>absint(
                    $_POST['user_id']
                ),

                'course_id'=>absint(
                    $_POST['course_id']
                ),

                'status'=>'active',

                'created_at'=>current_time(
                    'mysql'
                )

            ),

            array(
                '%d',
                '%d',
                '%s',
                '%s'
            )

        );



        echo '<div class="notice notice-success">';

        echo '课程授权成功';

        echo '</div>';


    }


}