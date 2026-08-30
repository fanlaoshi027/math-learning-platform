<?php
/**
 * Theme functions and definitions.
 *
 * @package MathCourseTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue theme assets.
 *
 * @return void
 */
function mc_theme_assets() {
	$style_path       = get_stylesheet_directory() . '/style.css';
	$ui_path          = get_stylesheet_directory() . '/assets/css/reference-ui.css';
	$detail_path      = get_stylesheet_directory() . '/assets/css/course-detail.css';
	$learning_path    = get_stylesheet_directory() . '/assets/css/learning.css';
	$learning_states_path = get_stylesheet_directory() . '/assets/css/learning-states.css';
	$learning_nav_path = get_stylesheet_directory() . '/assets/css/learning-nav.css';
	$version          = file_exists( $style_path ) ? (string) filemtime( $style_path ) : '0.2.0';
	$ui_version       = file_exists( $ui_path ) ? (string) filemtime( $ui_path ) : '0.3.0';
	$detail_version   = file_exists( $detail_path ) ? (string) filemtime( $detail_path ) : '1.0.0';
	$learning_version = file_exists( $learning_path ) ? (string) filemtime( $learning_path ) : '1.0.0';
	$learning_states_version = file_exists( $learning_states_path ) ? (string) filemtime( $learning_states_path ) : '1.0.0';
	$learning_nav_version = file_exists( $learning_nav_path ) ? (string) filemtime( $learning_nav_path ) : '1.0.0';

	wp_enqueue_style( 'mc-theme-style', get_stylesheet_uri(), array(), $version );

	$queried_content = get_post_field( 'post_content', get_queried_object_id() );
	$load_course_ui = is_page( array( 'course-center', 'xueyuan-denglu', '学习课程' ) );
	if ( ! $load_course_ui ) {
		$load_course_ui = has_shortcode( $queried_content, 'mathcourse_course_player' )
			|| has_shortcode( $queried_content, 'math_course_center_v82' )
			|| has_shortcode( $queried_content, 'math_student_login' );
	}

	if ( $load_course_ui ) {
		wp_enqueue_style( 'mc-reference-ui', get_stylesheet_directory_uri() . '/assets/css/reference-ui.css', array( 'mc-theme-style' ), $ui_version );
	}

	if ( $load_course_ui && ( isset( $_GET['course_id'] ) || has_shortcode( $queried_content, 'mathcourse_course_directory' ) ) ) {
		wp_enqueue_style( 'mc-course-detail', get_stylesheet_directory_uri() . '/assets/css/course-detail.css', array( 'mc-reference-ui' ), $detail_version );
	}

	if ( is_page_template( 'page-learning.php' ) ) {
		wp_enqueue_style( 'mc-learning', get_stylesheet_directory_uri() . '/assets/css/learning.css', array( 'mc-theme-style' ), $learning_version );
		wp_enqueue_style( 'mc-learning-states', get_stylesheet_directory_uri() . '/assets/css/learning-states.css', array( 'mc-learning' ), $learning_states_version );
		wp_enqueue_style( 'mc-learning-nav', get_stylesheet_directory_uri() . '/assets/css/learning-nav.css', array( 'mc-learning-states' ), $learning_nav_version );
	}
}
add_action( 'wp_enqueue_scripts', 'mc_theme_assets' );

/**
 * Set up theme features.
 *
 * @return void
 */
function mc_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'mc_theme_setup' );
