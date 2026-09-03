<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

use MathCourse\Tutor\Adapter;

/**
 * Handles drag-and-drop ordering for MathCourse topics and Tutor LMS lessons.
 */
class Course_Sort {

    const ACTION = 'mathcourse_save_order';
    const NONCE_ACTION = 'mathcourse_save_order';

    private $tutor;

    public function __construct() {
        $this->tutor = new Adapter();
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'save_order' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'mathcourse-course-edit' !== $page ) {
            return;
        }

        wp_enqueue_script(
            'mathcourse-admin-course-sort',
            MATHCOURSE_URL . 'assets/admin-course-sort.js',
            array(),
            MATHCOURSE_VERSION,
            true
        );

        wp_localize_script(
            'mathcourse-admin-course-sort',
            'MathCourseOrder',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
                'saving'  => '正在保存排序…',
                'saved'   => '排序已保存。',
                'error'   => '排序保存失败。',
            )
        );
    }

    public function save_order() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '没有保存排序的权限。' ), 403 );
        }

        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! $this->tutor->is_available() ) {
            wp_send_json_error( array( 'message' => 'Tutor LMS 未加载。' ), 400 );
        }

        $course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
        if ( ! $course_id || ! $this->tutor->get_course( $course_id ) ) {
            wp_send_json_error( array( 'message' => '课程不存在。' ), 400 );
        }

        if ( ! current_user_can( 'edit_post', $course_id ) ) {
            wp_send_json_error( array( 'message' => '没有编辑这个课程的权限。' ), 403 );
        }

        $topic_order = isset( $_POST['topic_order'] ) && is_array( $_POST['topic_order'] )
            ? array_map( 'absint', wp_unslash( $_POST['topic_order'] ) )
            : array();

        $lesson_order = isset( $_POST['lesson_order'] ) && is_array( $_POST['lesson_order'] )
            ? wp_unslash( $_POST['lesson_order'] )
            : array();

        $valid_topics = $this->tutor->get_topics( $course_id, true );
        $valid_topic_map = array();
        foreach ( $valid_topics as $topic ) {
            $valid_topic_map[ (int) $topic->ID ] = true;
        }

        $position = 0;
        foreach ( $topic_order as $topic_id ) {
            if ( ! isset( $valid_topic_map[ $topic_id ] ) || ! current_user_can( 'edit_post', $topic_id ) ) {
                continue;
            }

            wp_update_post(
                array(
                    'ID'         => $topic_id,
                    'menu_order' => $position,
                )
            );
            $position++;
        }

        foreach ( $lesson_order as $topic_key => $lesson_ids ) {
            $topic_id = absint( $topic_key );
            if ( ! isset( $valid_topic_map[ $topic_id ] ) || ! is_array( $lesson_ids ) || ! current_user_can( 'edit_post', $topic_id ) ) {
                continue;
            }

            $valid_lessons = $this->tutor->get_lessons( $topic_id, true );
            $valid_lesson_map = array();
            foreach ( $valid_lessons as $lesson ) {
                $valid_lesson_map[ (int) $lesson->ID ] = true;
            }

            $position = 0;
            foreach ( $lesson_ids as $lesson_id ) {
                $lesson_id = absint( $lesson_id );
                if ( ! isset( $valid_lesson_map[ $lesson_id ] ) || ! current_user_can( 'edit_post', $lesson_id ) ) {
                    continue;
                }

                wp_update_post(
                    array(
                        'ID'         => $lesson_id,
                        'menu_order' => $position,
                    )
                );
                $position++;
            }
        }

        wp_send_json_success( array( 'message' => '排序已保存。' ) );
    }
}
