<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

use MathCourse\Tutor\Adapter;

/**
 * Handles course actions before the admin page outputs anything.
 */
class Course_Actions {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ), 1 );
	}

	public function handle() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'mathcourse-courses' !== $page ) {
			return;
		}

		$adapter = new Adapter();
		if ( ! $adapter->is_available() ) {
			return;
		}

		if ( 'new' === $action ) {
			check_admin_referer( 'mathcourse_new_course' );

			$course_id = wp_insert_post(
				array(
					'post_title'  => '新课程',
					'post_type'   => $this->get_course_post_type( $adapter ),
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
			$this->apply_mathcourse_tutor_defaults( $course_id );

			$url = add_query_arg(
				array( 'page' => 'mathcourse-course-edit', 'course_id' => $course_id ),
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

			$course = $adapter->get_course( $course_id );
			if ( $course && current_user_can( 'delete_post', $course_id ) ) {
				wp_trash_post( $course_id );
			}
		}
	}

	/**
	 * Return the Tutor course post type through the adapter rather than calling Tutor directly.
	 */
	private function get_course_post_type( Adapter $adapter ) {
		$courses = $adapter->get_courses( true, 1 );
		if ( ! empty( $courses ) ) {
			return $courses[0]->post_type;
		}

		// Tutor LMS uses courses as its course post type. Keep this fallback
		// only for an installed Tutor version with no existing course posts.
		return 'courses';
	}

	/**
	 * MathCourse 自己负责授权，因此新建课程时把 Tutor LMS 的价格类型固定为 paid。
	 * 课程是否公开展示由 MathCourse 课程中心决定，不依赖 Tutor 的 Free Course 权限。
	 */
	private function apply_mathcourse_tutor_defaults( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return;
		}

		update_post_meta( $course_id, '_tutor_course_price_type', 'paid' );
	}
}
