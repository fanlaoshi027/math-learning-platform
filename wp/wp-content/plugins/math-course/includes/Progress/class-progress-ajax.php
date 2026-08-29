<?php

namespace MathCourse\Progress;

defined( 'ABSPATH' ) || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Course\Course_Service;

/**
 * Lesson completion AJAX endpoint.
 *
 * Server stores completion only. Playback position remains local.
 */
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

		if ( ! $lesson_id || ! $course_id ) {
			wp_send_json_error( array( 'message' => 'invalid lesson or course' ), 400 );
		}

		if ( ! function_exists( 'tutor' ) ) {
			wp_send_json_error( array( 'message' => 'Tutor LMS unavailable' ), 500 );
		}

		$lesson = get_post( $lesson_id );
		if ( ! $lesson || tutor()->lesson_post_type !== $lesson->post_type || 'publish' !== $lesson->post_status ) {
			wp_send_json_error( array( 'message' => 'invalid or unpublished lesson' ), 400 );
		}

		$topic = get_post( $lesson->post_parent );
		if ( ! $topic || 'topics' !== $topic->post_type || (int) $topic->post_parent !== $course_id ) {
			wp_send_json_error( array( 'message' => 'lesson does not belong to course' ), 403 );
		}

		$course = get_post( $course_id );
		if ( ! $course || tutor()->course_post_type !== $course->post_type || 'publish' !== $course->post_status ) {
			wp_send_json_error( array( 'message' => 'invalid or unpublished course' ), 400 );
		}

		$access = new Access_Service();
		// 免费课程也属于可学习课程，不能只检查 MathCourse 的付费授权表。
		if ( ! $access->can_access_course( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => 'course access required' ), 403 );
		}

		$progress = new Progress_Service();
		$updated  = $progress->complete_lesson( $user_id, $lesson_id );

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'unable to save progress' ), 500 );
		}

		update_user_meta( $user_id, 'mc_last_completed_course', $course_id );

		$course_progress = $progress->get_course_progress( $course_id, $user_id );

		wp_send_json_success(
			array(
				'lesson_id' => $lesson_id,
				'course_id' => $course_id,
				'completed' => true,
				'progress'  => $course_progress,
			)
		);
	}
}
