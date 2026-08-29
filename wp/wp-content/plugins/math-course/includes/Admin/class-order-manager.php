<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

class Order_Manager {
	const NONCE_ACTION = 'mathcourse_save_order';
	public function __construct() { add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) ); add_action( 'wp_ajax_mathcourse_save_order', array( $this, 'save_order' ) ); }
	public function enqueue_assets() {
		if ( ! is_admin() || empty( $_GET['page'] ) || 'mathcourse-course-edit' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
		wp_enqueue_script( 'mathcourse-order', MATHCOURSE_URL . 'assets/admin-course-sort-v2.js', array(), MATHCOURSE_VERSION . '-v2', true );
		wp_localize_script( 'mathcourse-order', 'MathCourseOrder', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( self::NONCE_ACTION ), 'saving' => '正在保存排序…', 'saved' => '排序已保存。', 'error' => '排序保存失败，请刷新页面后重试。' ) );
	}
	public function save_order() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => '无权操作。' ), 403 );
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		if ( ! $course_id || ! function_exists( 'tutor' ) || tutor()->course_post_type !== get_post_type( $course_id ) ) wp_send_json_error( array( 'message' => '课程不存在。' ), 400 );
		$topic_order = isset( $_POST['topic_order'] ) && is_array( $_POST['topic_order'] ) ? array_map( 'absint', wp_unslash( $_POST['topic_order'] ) ) : array();
		$lesson_order = isset( $_POST['lesson_order'] ) && is_array( $_POST['lesson_order'] ) ? wp_unslash( $_POST['lesson_order'] ) : array();
		foreach ( $topic_order as $position => $topic_id ) { $topic = get_post( $topic_id ); if ( ! $topic || 'topics' !== $topic->post_type || (int) $topic->post_parent !== $course_id ) continue; wp_update_post( array( 'ID' => $topic_id, 'menu_order' => (int) $position ) ); }
		foreach ( $lesson_order as $topic_id => $ids ) {
			$topic_id = absint( $topic_id ); $topic = get_post( $topic_id );
			if ( ! $topic || 'topics' !== $topic->post_type || (int) $topic->post_parent !== $course_id || ! is_array( $ids ) ) continue;
			foreach ( array_values( array_map( 'absint', $ids ) ) as $position => $lesson_id ) {
				$lesson = get_post( $lesson_id );
				if ( ! $lesson || tutor()->lesson_post_type !== $lesson->post_type || (int) $lesson->post_parent !== $topic_id ) continue;
				wp_update_post( array( 'ID' => $lesson_id, 'menu_order' => (int) $position ) );
			}
		}
		wp_send_json_success( array( 'message' => '排序已保存。' ) );
	}
}
