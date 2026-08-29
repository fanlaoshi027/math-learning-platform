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
	$style_path = get_stylesheet_directory() . '/style.css';
	$version    = file_exists( $style_path ) ? (string) filemtime( $style_path ) : '0.1.0';

	wp_enqueue_style(
		'mc-theme-style',
		get_stylesheet_uri(),
		array(),
		$version
	);
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
