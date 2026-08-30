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
 * Keep the base stylesheet global, but load the larger reference UI styles only
 * on pages that actually use the course UI shortcodes.
 *
 * @return void
 */
function mc_theme_assets() {
	$style_path = get_stylesheet_directory() . '/style.css';
	$ui_path    = get_stylesheet_directory() . '/assets/css/reference-ui.css';
	$version    = file_exists( $style_path ) ? (string) filemtime( $style_path ) : '0.2.0';
	$ui_version = file_exists( $ui_path ) ? (string) filemtime( $ui_path ) : '0.3.0';

	wp_enqueue_style( 'mc-theme-style', get_stylesheet_uri(), array(), $version );

	$load_course_ui = is_page( array( 'course-center', 'xueyuan-denglu', '学习课程' ) );
	if ( ! $load_course_ui ) {
		$load_course_ui = has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'mathcourse_course_player' )
			|| has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'math_course_center_v82' )
			|| has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'math_student_login' );
	}

	if ( $load_course_ui ) {
		wp_enqueue_style(
			'mc-reference-ui',
			get_stylesheet_directory_uri() . '/assets/css/reference-ui.css',
			array( 'mc-theme-style' ),
			$ui_version
		);
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
