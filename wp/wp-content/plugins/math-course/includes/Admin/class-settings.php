<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;


class Settings
{


    public function render()
    {

        if(
            !current_user_can('manage_options')
        ){

            return;

        }


        ?>

        <div class="wrap">

            <h1>
                MathCourse 设置
            </h1>


            <table class="form-table">


                <tr>

                    <th>
                        插件版本
                    </th>

                    <td>
                        <?php
                        echo esc_html(
                            MATHCOURSE_VERSION
                        );
                        ?>
                    </td>

                </tr>


                <tr>

                    <th>
                        系统状态
                    </th>

                    <td>
                        正常运行
                    </td>

                </tr>


            </table>


        </div>


        <?php


    }


}