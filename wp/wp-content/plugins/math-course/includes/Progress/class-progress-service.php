<?php

namespace MathCourse\Progress;

defined( 'ABSPATH' ) || exit;

use MathCourse\Access\Access_Service;

/**
 * 学习进度服务。
 *
 * 服务端只保存“课时是否完成”和“完成时间”；视频播放位置仍由前端
 * localStorage 保存，不上传服务器。
 */
class Progress_Service {

    private $access;

    public function __construct() {
        $this->access = new Access_Service();
    }

    /**
     * 获取指定学员的课程进度。
     */
    public function get_course_progress( $course_id, $user_id = 0 ) {
        $course_id = absint( $course_id );
        $user_id   = absint( $user_id );

        if ( ! $course_id || ! $user_id || ! $this->access->has_access( $user_id, $course_id ) ) {
            return $this->empty_progress();
        }

        return $this->calculate_progress( $course_id, $user_id, false );
    }

    /**
     * 后台使用：不要求学员拥有当前课程授权，统计全部已创建课时。
     */
    public function get_admin_course_progress( $course_id, $user_id ) {
        $course_id = absint( $course_id );
        $user_id   = absint( $user_id );

        if ( ! $course_id || ! $user_id ) {
            return $this->empty_progress();
        }

        return $this->calculate_progress( $course_id, $user_id, true );
    }

    /**
     * 标记课时完成。
     *
     * 重复提交同一个课时视为成功，避免 update_user_meta() 在数据未变化时
     * 返回 false 导致前端误判为“保存失败”。
     */
    public function complete_lesson( $user_id, $lesson_id ) {
        $user_id   = absint( $user_id );
        $lesson_id = absint( $lesson_id );

        if ( ! $user_id || ! $lesson_id ) {
            return false;
        }

        $completed = $this->get_completed_lessons( $user_id );

        // 已完成：幂等处理，直接视为成功。
        if ( in_array( $lesson_id, $completed, true ) ) {
            return true;
        }

        $completed[] = $lesson_id;
        $completed   = array_values( array_unique( array_map( 'intval', $completed ) ) );

        $saved = update_user_meta(
            $user_id,
            'mc_completed_lessons',
            $completed
        );

        if ( false === $saved ) {
            return false;
        }

        $times = $this->get_completed_times( $user_id );
        $times[ $lesson_id ] = current_time( 'timestamp' );

        update_user_meta(
            $user_id,
            'mc_lesson_completed_time',
            $times
        );

        return true;
    }

    /**
     * 取消完成状态。
     */
    public function uncomplete_lesson( $user_id, $lesson_id ) {
        $user_id   = absint( $user_id );
        $lesson_id = absint( $lesson_id );

        if ( ! $user_id || ! $lesson_id ) {
            return false;
        }

        $completed = $this->get_completed_lessons( $user_id );

        if ( ! in_array( $lesson_id, $completed, true ) ) {
            return true;
        }

        $completed = array_values(
            array_diff( $completed, array( $lesson_id ) )
        );

        $saved = update_user_meta(
            $user_id,
            'mc_completed_lessons',
            array_values( array_map( 'intval', $completed ) )
        );

        if ( false === $saved ) {
            return false;
        }

        $times = $this->get_completed_times( $user_id );
        unset( $times[ $lesson_id ] );

        if ( empty( $times ) ) {
            delete_user_meta( $user_id, 'mc_lesson_completed_time' );
        } else {
            update_user_meta( $user_id, 'mc_lesson_completed_time', $times );
        }

        return true;
    }

    /**
     * 判断课时是否完成。
     */
    public function is_completed( $user_id, $lesson_id ) {
        return in_array(
            absint( $lesson_id ),
            $this->get_completed_lessons( $user_id ),
            true
        );
    }

    /**
     * 获取指定用户最后完成的课时 ID。
     */
    public function get_last_completed_lesson( $user_id, $course_id = 0 ) {
        $completed = $this->get_completed_lessons( $user_id );

        if ( empty( $completed ) ) {
            return 0;
        }

        if ( ! $course_id ) {
            $times = $this->get_completed_times( $user_id );
            return $this->find_latest_lesson( $completed, $times );
        }

        $course_lessons = $this->get_course_lessons( absint( $course_id ), false );
        $course_ids     = array_map( 'intval', wp_list_pluck( $course_lessons, 'ID' ) );
        $course_completed = array();

        foreach ( $completed as $lesson_id ) {
            if ( in_array( (int) $lesson_id, $course_ids, true ) ) {
                $course_completed[] = (int) $lesson_id;
            }
        }

        if ( empty( $course_completed ) ) {
            return 0;
        }

        $times = $this->get_completed_times( $user_id );
        return $this->find_latest_lesson( $course_completed, $times );
    }

    /**
     * 获取指定课时的完成时间戳。
     */
    public function get_lesson_completed_time( $user_id, $lesson_id ) {
        $times = $this->get_completed_times( $user_id );
        $lesson_id = absint( $lesson_id );

        return isset( $times[ $lesson_id ] ) ? absint( $times[ $lesson_id ] ) : 0;
    }

    private function calculate_progress( $course_id, $user_id, $include_unpublished = false ) {
        $lessons           = $this->get_course_lessons( $course_id, $include_unpublished );
        $completed_lessons = $this->get_completed_lessons( $user_id );
        $completed_times   = $this->get_completed_times( $user_id );

        $total          = count( $lessons );
        $completed      = 0;
        $last_lesson_id = 0;
        $last_time      = 0;

        foreach ( $lessons as $lesson ) {
            $lesson_id = (int) $lesson->ID;

            if ( in_array( $lesson_id, $completed_lessons, true ) ) {
                $completed++;

                $lesson_time = isset( $completed_times[ $lesson_id ] )
                    ? absint( $completed_times[ $lesson_id ] )
                    : 0;

                if ( $lesson_time >= $last_time ) {
                    $last_time      = $lesson_time;
                    $last_lesson_id = $lesson_id;
                } elseif ( ! $last_lesson_id ) {
                    $last_lesson_id = $lesson_id;
                }
            }
        }

        return array(
            'completed'       => $completed,
            'total'           => $total,
            'percent'         => $total ? round( ( $completed / $total ) * 100 ) : 0,
            'last_lesson_id' => $last_lesson_id,
            'last_time'      => $last_time,
        );
    }

    private function get_completed_lessons( $user_id ) {
        $data = get_user_meta(
            absint( $user_id ),
            'mc_completed_lessons',
            true
        );

        return is_array( $data ) ? array_values( array_unique( array_map( 'intval', $data ) ) ) : array();
    }

    private function get_completed_times( $user_id ) {
        $data = get_user_meta(
            absint( $user_id ),
            'mc_lesson_completed_time',
            true
        );

        if ( ! is_array( $data ) ) {
            return array();
        }

        $times = array();

        foreach ( $data as $lesson_id => $timestamp ) {
            $lesson_id = absint( $lesson_id );
            $timestamp = absint( $timestamp );

            if ( $lesson_id && $timestamp ) {
                $times[ $lesson_id ] = $timestamp;
            }
        }

        return $times;
    }

    private function find_latest_lesson( $lesson_ids, $times ) {
        $latest_lesson = 0;
        $latest_time   = -1;

        foreach ( $lesson_ids as $lesson_id ) {
            $lesson_id = absint( $lesson_id );
            $time      = isset( $times[ $lesson_id ] ) ? absint( $times[ $lesson_id ] ) : -1;

            if ( $time >= $latest_time ) {
                $latest_time   = $time;
                $latest_lesson = $lesson_id;
            }
        }

        return $latest_lesson;
    }

    private function empty_progress() {
        return array(
            'completed'       => 0,
            'total'           => 0,
            'percent'         => 0,
            'last_lesson_id' => 0,
            'last_time'       => 0,
        );
    }

    /**
     * 按当前 MathCourse 编辑器实际使用的结构获取课程课时：
     * Course → topics → Tutor Lesson。
     *
     * 学员前台只统计已发布课时；后台可以选择把草稿/私密课时一起统计。
     */
    private function get_course_lessons( $course_id, $include_unpublished = false ) {
        $course_id = absint( $course_id );

        if ( ! $course_id || ! function_exists( 'tutor' ) || empty( tutor()->lesson_post_type ) ) {
            return array();
        }

        $topic_status = $include_unpublished
            ? array( 'publish', 'draft', 'private' )
            : array( 'publish' );

        $lesson_status = $include_unpublished
            ? array( 'publish', 'draft', 'private' )
            : array( 'publish' );

        $topics = get_posts(
            array(
                'post_type'      => 'topics',
                'post_parent'    => $course_id,
                'post_status'    => $topic_status,
                'posts_per_page' => -1,
                'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
            )
        );

        if ( empty( $topics ) ) {
            return array();
        }

        $lessons = array();

        foreach ( $topics as $topic ) {
            $topic_lessons = get_posts(
                array(
                    'post_type'      => tutor()->lesson_post_type,
                    'post_parent'    => $topic->ID,
                    'post_status'    => $lesson_status,
                    'posts_per_page' => -1,
                    'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
                )
            );

            foreach ( $topic_lessons as $lesson ) {
                $lessons[] = $lesson;
            }
        }

        return $lessons;
    }
}
