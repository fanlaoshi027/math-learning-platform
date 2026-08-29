<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Progress\Progress_Service;

class Progress_Page {

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) );
        }

        if ( ! function_exists( 'tutor' ) ) {
            $this->notice( 'MathCourse 需要 Tutor LMS 4.0.4。' );
            return;
        }

        $keyword   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

        $users = get_users(
            array(
                'number'  => 50,
                'search'  => $keyword ? '*' . $keyword . '*' : '*',
                'orderby' => 'display_name',
                'order'   => 'ASC',
            )
        );

        $courses = get_posts(
            array(
                'post_type'      => tutor()->course_post_type,
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'posts_per_page' => 100,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $progress_service = new Progress_Service();
        $access_service   = new Access_Service();
        ?>
        <div class="wrap">
            <h1>学习进度</h1>
            <p style="color:#646970;">查看学员课程完成情况。视频播放位置仍保存在学员浏览器，本页面只统计已完成课时。</p>

            <form method="get" style="margin:18px 0;display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="page" value="mathcourse-progress">
                <input type="search" name="s" value="<?php echo esc_attr( $keyword ); ?>" placeholder="搜索学员用户名、姓名或邮箱" style="min-width:300px;">
                <select name="course_id">
                    <option value="0">全部课程</option>
                    <?php foreach ( $courses as $course ) : ?>
                        <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button">筛选</button>
            </form>

            <div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;overflow:hidden;max-width:1180px;">
                <table class="widefat striped" style="border:0;">
                    <thead>
                        <tr>
                            <th>学员</th>
                            <th>课程</th>
                            <th>学习进度</th>
                            <th>完成率</th>
                            <th>最后完成课时</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rows = 0;
                    foreach ( $users as $user ) :
                        foreach ( $courses as $course ) :
                            if ( $course_id && (int) $course->ID !== $course_id ) {
                                continue;
                            }

                            $access = $access_service->get_access_info( $user->ID, $course->ID );
                            if ( empty( $access['access'] ) ) {
                                continue;
                            }

                            $progress = $progress_service->get_admin_course_progress( $course->ID, $user->ID );
                            $rows++;
                            $last_lesson = $progress['last_lesson_id'] ? get_post( $progress['last_lesson_id'] ) : null;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $user->display_name ); ?></strong><br>
                                    <small style="color:#646970;"><?php echo esc_html( $user->user_email ); ?></small>
                                </td>
                                <td><?php echo esc_html( $course->post_title ); ?></td>
                                <td><?php echo esc_html( $progress['completed'] . ' / ' . $progress['total'] . ' 课时' ); ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;min-width:150px;">
                                        <div style="height:8px;background:#e5e7eb;border-radius:99px;overflow:hidden;flex:1;">
                                            <div style="height:100%;width:<?php echo esc_attr( min( 100, max( 0, (int) $progress['percent'] ) ) ); ?>%;background:#2271b1;border-radius:99px;"></div>
                                        </div>
                                        <strong><?php echo esc_html( $progress['percent'] ); ?>%</strong>
                                    </div>
                                </td>
                                <td><?php echo $last_lesson ? esc_html( $last_lesson->post_title ) : '尚未完成课时'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if ( ! $rows ) : ?>
                        <tr><td colspan="5" style="padding:30px;text-align:center;color:#646970;">暂无符合条件的学习记录。</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function notice( $message, $type = 'error' ) {
        echo '<div class="wrap"><h1>学习进度</h1><div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $message ) . '</p></div></div>';
    }
}
