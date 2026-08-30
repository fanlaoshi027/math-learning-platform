<?php

namespace MathCourse\Progress;

defined( 'ABSPATH' ) || exit;

use MathCourse\Access\Access_Service;

/** Lesson completion AJAX endpoint. */
class Progress_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_mathcourse_complete_lesson', array( $this, 'complete' ) );
	}

	public function complete() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'login required' ), 403 );
		}

		check_ajax_referer( 'mathcourse_progress_nonce', 'nonce' );

		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$user_id   = get_current_user_id();

		if ( ! $lesson_id || ! $course_id || ! function_exists( 'tutor' ) ) {
			wp_send_json_error( array( 'message' => 'invalid request' ), 400 );
		}

		$lesson = get_post( $lesson_id );
		$topic  = $lesson ? get_post( $lesson->post_parent ) : false;
		$course = get_post( $course_id );

		if ( ! $lesson || tutor()->lesson_post_type !== $lesson->post_type || 'publish' !== $lesson->post_status ||
			! $topic || 'topics' !== $topic->post_type || (int) $topic->post_parent !== $course_id ||
			! $course || tutor()->course_post_type !== $course->post_type || 'publish' !== $course->post_status ) {
			wp_send_json_error( array( 'message' => 'invalid lesson or course' ), 400 );
		}

		$access = new Access_Service();
		if ( ! $access->can_access_course( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => 'course access required' ), 403 );
		}

		$progress = new Progress_Service();
		$updated  = $progress->complete_lesson( $user_id, $lesson_id );
		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'unable to save progress' ), 500 );
		}

		update_user_meta( $user_id, 'mc_last_completed_course', $course_id );

		wp_send_json_success( array(
			'lesson_id' => $lesson_id,
			'course_id' => $course_id,
			'completed' => true,
			'progress'  => $progress->get_course_progress( $course_id, $user_id ),
		) );
	}
}
