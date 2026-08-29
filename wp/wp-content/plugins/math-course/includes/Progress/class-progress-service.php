<?php

namespace MathCourse\Progress;

defined( 'ABSPATH' ) || exit;

use MathCourse\Access\Access_Service;

/**
 * 学习进度服务。
 *
 * 服务端只保存“课时是否完成”；视频播放位置仍由前端 localStorage 保存。
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
            return array(
                'completed'       => 0,
                'total'           => 0,
                'percent'         => 0,
                'last_lesson_id' => 0,
            );
        }

        return $this->calculate_progress( $course_id, $user_id );
    }

    /**
     * 后台使用：不要求学员拥有当前课程授权，直接统计其完成记录。
     */
    public function get_admin_course_progress( $course_id, $user_id ) {
        $course_id = absint( $course_id );
        $user_id   = absint( $user_id );

        if ( ! $course_id || ! $user_id ) {
            return array(
                'completed'       => 0,
                'total'           => 0,
                'percent'         => 0,
                'last_lesson_id' => 0,
            );
        }

        return $this->calculate_progress( $course_id, $user_id );
    }

    /**
     * 标记课时完成。
     */
    public function complete_lesson( $user_id, $lesson_id ) {
        $user_id   = absint( $user_id );
        $lesson_id = absint( $lesson_id );

        if ( ! $user_id || ! $lesson_id ) {
            return false;
        }

        $completed = $this->get_completed_lessons( $user_id );

        if ( ! in_array( $lesson_id, $completed, true ) ) {
            $completed[] = $lesson_id;
        }

        return update_user_meta(
            $user_id,
            'mc_completed_lessons',
            array_values( array_unique( array_map( 'intval', $completed ) ) )
        );
    }

    /**
     * 取消完成状态。
     */
    public function uncomplete_lesson( $user_id, $lesson_id ) {
        $completed = $this->get_completed_lessons( $user_id );
        $completed = array_diff( $completed, array( absint( $lesson_id ) ) );

        return update_user_meta(
            $user_id,
            'mc_completed_lessons',
            array_values( array_map( 'intval', $completed ) )
        );
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
            return (int) end( $completed );
        }

        $course_lessons = $this->get_course_lessons( absint( $course_id ) );
        $course_ids     = wp_list_pluck( $course_lessons, 'ID' );
        $course_ids     = array_map( 'intval', $course_ids );

        $last = 0;
        foreach ( $completed as $lesson_id ) {
            if ( in_array( (int) $lesson_id, $course_ids, true ) ) {
                $last = (int) $lesson_id;
            }
        }

        return $last;
    }

    private function calculate_progress( $course_id, $user_id ) {
        $lessons           = $this->get_course_lessons( $course_id );
        $completed_lessons = $this->get_completed_lessons( $user_id );

        $total           = count( $lessons );
        $completed       = 0;
        $last_lesson_id  = 0;

        foreach ( $lessons as $lesson ) {
            $lesson_id = (int) $lesson->ID;

            if ( in_array( $lesson_id, $completed_lessons, true ) ) {
                $completed++;
                $last_lesson_id = $lesson_id;
            }
        }

        return array(
            'completed'       => $completed,
            'total'           => $total,
            'percent'         => $total ? round( ( $completed / $total ) * 100 ) : 0,
            'last_lesson_id' => $last_lesson_id,
        );
    }

    private function get_completed_lessons( $user_id ) {
        $data = get_user_meta(
            absint( $user_id ),
            'mc_completed_lessons',
            true
        );

        return is_array( $data ) ? array_map( 'intval', $data ) : array();
    }

    /**
     * 按当前 MathCourse 编辑器实际使用的结构获取课程课时：
     * Course → topics → Tutor Lesson。
     */
    private function get_course_lessons( $course_id ) {
        $course_id = absint( $course_id );

        if ( ! $course_id || ! function_exists( 'tutor' ) || empty( tutor()->lesson_post_type ) ) {
            return array();
        }

        $topics = get_posts(
            array(
                'post_type'      => 'topics',
                'post_parent'    => $course_id,
                'post_status'    => array( 'publish', 'draft', 'private' ),
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
                    'post_status'    => array( 'publish', 'draft', 'private' ),
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
