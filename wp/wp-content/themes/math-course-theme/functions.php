<?php
/**
 * Theme functions and definitions.
 *
 * @package MathCourseTheme
 */

defined( 'ABSPATH' ) || exit;

function mc_ensure_learning_page() {
	$page = get_page_by_path( 'learning', OBJECT, 'page' );
	if ( $page instanceof WP_Post ) { return; }
	$page_id = wp_insert_post(array('post_title'=>'学习课程','post_name'=>'learning','post_content'=>'','post_status'=>'publish','post_type'=>'page'),true);
	if ( ! is_wp_error( $page_id ) && $page_id ) { flush_rewrite_rules( false ); }
}
add_action( 'init', 'mc_ensure_learning_page', 5 );

function mc_get_learning_page_url() {
	$page = get_page_by_path( 'learning', OBJECT, 'page' );
	return $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/learning/' );
}

function mc_theme_assets() {
	$style_path=get_stylesheet_directory().'/style.css';
	$ui_path=get_stylesheet_directory().'/assets/css/reference-ui.css';
	$scale_path=get_stylesheet_directory().'/assets/css/ui-scale.css';
	$detail_path=get_stylesheet_directory().'/assets/css/course-detail.css';
	$learning_path=get_stylesheet_directory().'/assets/css/learning.css';
	$learning_states_path=get_stylesheet_directory().'/assets/css/learning-states.css';
	$learning_nav_path=get_stylesheet_directory().'/assets/css/learning-nav.css';
	$polish_path=get_stylesheet_directory().'/assets/css/course-ui-polish.css';
	$learning_polish_path=get_stylesheet_directory().'/assets/css/learning-ui-polish.css';
	$home_path=get_stylesheet_directory().'/assets/css/home-ui-v2.css';
	$reference_override_path=get_stylesheet_directory().'/assets/css/ui-reference-override.css';
	$header_polish_path=get_stylesheet_directory().'/assets/css/header-ui-polish.css';
	$cover_path=get_stylesheet_directory().'/assets/css/course-cover.css';
	$version=file_exists($style_path)?(string)filemtime($style_path):'0.2.0';
	$ui_version=file_exists($ui_path)?(string)filemtime($ui_path):'0.3.0';
	$scale_version=file_exists($scale_path)?(string)filemtime($scale_path):'1.0.0';
	$detail_version=file_exists($detail_path)?(string)filemtime($detail_path):'1.0.0';
	$learning_version=file_exists($learning_path)?(string)filemtime($learning_path):'1.0.0';
	$states_version=file_exists($learning_states_path)?(string)filemtime($learning_states_path):'1.0.0';
	$nav_version=file_exists($learning_nav_path)?(string)filemtime($learning_nav_path):'1.0.0';
	$polish_version=file_exists($polish_path)?(string)filemtime($polish_path):'1.0.0';
	$learning_polish_version=file_exists($learning_polish_path)?(string)filemtime($learning_polish_path):'1.0.0';
	$home_version=file_exists($home_path)?(string)filemtime($home_path):'1.0.0';
	$reference_override_version=file_exists($reference_override_path)?(string)filemtime($reference_override_path):'1.0.0';
	$header_polish_version=file_exists($header_polish_path)?(string)filemtime($header_polish_path):'1.0.0';
	$cover_version=file_exists($cover_path)?(string)filemtime($cover_path):'1.0.0';
	wp_enqueue_style('mc-theme-style',get_stylesheet_uri(),array(),$version);
	$queried_content=get_post_field('post_content',get_queried_object_id());
	$load_course_ui=is_front_page()||is_page(array('course-center','xueyuan-denglu','learning'));
	if(!$load_course_ui){$load_course_ui=has_shortcode($queried_content,'mathcourse_course_player')||has_shortcode($queried_content,'mathcourse_course_learning')||has_shortcode($queried_content,'math_course_center_v82')||has_shortcode($queried_content,'math_student_login');}
	if($load_course_ui){
		wp_enqueue_style('mc-reference-ui',get_stylesheet_directory_uri().'/assets/css/reference-ui.css',array('mc-theme-style'),$ui_version);
		wp_enqueue_style('mc-ui-scale',get_stylesheet_directory_uri().'/assets/css/ui-scale.css',array('mc-reference-ui'),$scale_version);
		wp_enqueue_style('mc-course-ui-polish',get_stylesheet_directory_uri().'/assets/css/course-ui-polish.css',array('mc-ui-scale'),$polish_version);
		wp_enqueue_style('mc-course-cover',get_stylesheet_directory_uri().'/assets/css/course-cover.css',array('mc-course-ui-polish'),$cover_version);
	}
	wp_enqueue_style('mc-header-ui-polish',get_stylesheet_directory_uri().'/assets/css/header-ui-polish.css',array('mc-theme-style'),$header_polish_version);
	if(is_front_page()){
		wp_enqueue_style('mc-home-ui-v2',get_stylesheet_directory_uri().'/assets/css/home-ui-v2.css',array('mc-course-ui-polish'),$home_version);
	}
	if($load_course_ui&&(isset($_GET['course_id'])||has_shortcode($queried_content,'mathcourse_course_directory')||is_front_page())){
		wp_enqueue_style('mc-course-detail',get_stylesheet_directory_uri().'/assets/css/course-detail.css',array('mc-ui-scale'),$detail_version);
	}
	if(is_page('learning')||is_page_template('page-learning.php')){
		wp_enqueue_style('mc-learning',get_stylesheet_directory_uri().'/assets/css/learning.css',array('mc-ui-scale'),$learning_version);
		wp_enqueue_style('mc-learning-states',get_stylesheet_directory_uri().'/assets/css/learning-states.css',array('mc-learning'),$states_version);
		wp_enqueue_style('mc-learning-nav',get_stylesheet_directory_uri().'/assets/css/learning-nav.css',array('mc-learning-states'),$nav_version);
		wp_enqueue_style('mc-learning-ui-polish',get_stylesheet_directory_uri().'/assets/css/learning-ui-polish.css',array('mc-learning-nav'),$learning_polish_version);
	}
	wp_enqueue_style('mc-ui-reference-override',get_stylesheet_directory_uri().'/assets/css/ui-reference-override.css',array('mc-course-ui-polish','mc-learning-ui-polish'),$reference_override_version);
}
add_action('wp_enqueue_scripts','mc_theme_assets');

function mc_route_course_to_learning_player() {
	if(!is_page('course-center')||empty($_GET['course_id']))return;
	$course_id=absint($_GET['course_id']); if(!$course_id)return;
	$args=array('course_id'=>$course_id); $lesson_id=isset($_GET['lesson_id'])?absint($_GET['lesson_id']):0; if($lesson_id)$args['lesson_id']=$lesson_id;
	wp_safe_redirect(add_query_arg($args,mc_get_learning_page_url()),302); exit;
}
add_action('template_redirect','mc_route_course_to_learning_player',1);

function mc_theme_setup() {
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('html5',array('search-form','comment-form','comment-list','gallery','caption','style','script'));
}
add_action('after_setup_theme','mc_theme_setup');