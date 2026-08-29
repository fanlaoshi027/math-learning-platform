<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles course actions before the admin page outputs anything.
 *
 * Redirects must happen before WordPress (or another plugin) sends output.
 * This keeps wp_safe_redirect() from triggering "headers already sent".
 */
class Course_Actions {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ), 1 );
	}

	public function handle() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'tutor' ) ) {
			return;
		}

		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'mathcourse-courses' !== $page ) {
			return;
		}

		if ( 'new' === $action ) {
			check_admin_referer( 'mathcourse_new_course' );

			$course_id = wp_insert_post(
				array(
					'post_title'  => '新课程',
					'post_type'   => tutor()->course_post_type,
					'post_status' => 'draft',
					'post_author' => get_current_user_id(),
				),
				true
			);

			if ( is_wp_error( $course_id ) ) {
				wp_die( esc_html( $course_id->get_error_message() ) );
			}

			update_post_meta( $course_id, '_mathcourse_type', 'topic' );
			update_post_meta( $course_id, '_mathcourse_grade', '' );

			$url = add_query_arg(
				array(
					'page'      => 'create-course',
					'course_id' => $course_id,
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $url );
			exit;
		}

		if ( 'trash' === $action ) {
			$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

			if ( ! $course_id ) {
				return;
			}

			check_admin_referer( 'mathcourse_trash_course_' . $course_id );

			if (
				tutor()->course_post_type === get_post_type( $course_id ) &&
				current_user_can( 'delete_post', $course_id )
			) {
				wp_trash_post( $course_id );
			}
		}
	}
}
