<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Progress\Progress_Service;
use MathCourse\Tutor\Adapter;

class Progress_Page {

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'mathcourse' ) );
        }

        $tutor = new Adapter();
        if ( ! $tutor->is_available() ) {
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

        $course_post_type = $tutor->get_course_post_type();
        if ( ! $course_post_type ) {
            $this->notice( '无法获取 Tutor LMS 课程类型。' );
            return;
        }

        $courses = get_posts(
            array(
                'post_type'      => $course_post_type,
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'posts_per_page' => 100,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $progress_service = new Progress_Service();
        $access_service   = new Access_Service();
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-progress-page">
            <div class="mathcourse-admin-header">
                <div>
                    <div class="mathcourse-admin-eyebrow">MathCourse · 数据中心</div>
                    <h1>学习进度</h1>
                    <p>查看已授权学员的课程完成情况，掌握每位学员的学习进展。</p>
                </div>
            </div>

            <form method="get" class="mathcourse-progress-toolbar">
                <input type="hidden" name="page" value="mathcourse-progress">
                <div class="mathcourse-progress-search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input class="mathcourse-search-input" type="search" name="s" value="<?php echo esc_attr( $keyword ); ?>" placeholder="搜索学员用户名、姓名或邮箱">
                </div>
                <select name="course_id" class="mathcourse-progress-select">
                    <option value="0">全部课程</option>
                    <?php foreach ( $courses as $course ) : ?>
                        <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>><?php echo esc_html( $course->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button mathcourse-primary" type="submit">筛选</button>
            </form>

            <div class="mathcourse-progress-card">
                <div class="mathcourse-card-title">
                    <div>
                        <h2>学员学习情况</h2>
                        <p>只统计已授权课程中已经完成的课时。</p>
                    </div>
                    <?php if ( $keyword || $course_id ) : ?>
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mathcourse-progress' ) ); ?>">清除筛选</a>
                    <?php endif; ?>
                </div>

                <div class="mathcourse-progress-table-wrap">
                    <table class="mathcourse-progress-table">
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
                                $percent     = min( 100, max( 0, (int) $progress['percent'] ) );
                                $avatar_name = trim( (string) $user->display_name );
                                $avatar_char = function_exists( 'mb_substr' ) ? mb_substr( $avatar_name, 0, 1 ) : substr( $avatar_name, 0, 1 );
                                ?>
                                <tr>
                                    <td>
                                        <div class="mathcourse-user-cell">
                                            <div class="mathcourse-user-avatar"><?php echo esc_html( $avatar_char ? $avatar_char : '学' ); ?></div>
                                            <div>
                                                <strong><?php echo esc_html( $user->display_name ); ?></strong>
                                                <span><?php echo esc_html( $user->user_email ); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mathcourse-progress-course"><?php echo esc_html( $course->post_title ); ?></div>
                                        <small>课程 ID <?php echo esc_html( $course->ID ); ?></small>
                                    </td>
                                    <td>
                                        <strong class="mathcourse-progress-value"><?php echo esc_html( $progress['completed'] . ' / ' . $progress['total'] ); ?></strong>
                                        <span class="mathcourse-progress-unit">课时</span>
                                    </td>
                                    <td>
                                        <div class="mathcourse-progress-percent">
                                            <div class="mathcourse-progress-track">
                                                <div class="mathcourse-progress-fill" style="width:<?php echo esc_attr( $percent ); ?>%;"></div>
                                            </div>
                                            <strong><?php echo esc_html( $percent ); ?>%</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="mathcourse-last-lesson"><?php echo $last_lesson ? esc_html( $last_lesson->post_title ) : '尚未完成课时'; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ( ! $rows ) : ?>
                            <tr class="mathcourse-progress-empty-row">
                                <td colspan="5">
                                    <div class="mathcourse-progress-empty">
                                        <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                                        <strong>暂无学习记录</strong>
                                        <span>当前筛选条件下没有找到已授权学员的学习进度。</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function notice( $message, $type = 'error' ) {
        echo '<div class="wrap mathcourse-admin-wrap mathcourse-progress-page"><div class="mathcourse-admin-header"><div><div class="mathcourse-admin-eyebrow">MathCourse · 数据中心</div><h1>学习进度</h1></div></div><div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $message ) . '</p></div></div>';
    }
}
