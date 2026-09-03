<?php
/**
 * Math Course Theme — theme setup and front-end assets.
 */
defined( 'ABSPATH' ) || exit;

function mc_ensure_learning_page() {
    $page = get_page_by_path( 'learning', OBJECT, 'page' );
    if ( $page instanceof WP_Post ) {
        return;
    }

    $page_id = wp_insert_post(
        array(
            'post_title'   => '学习课程',
            'post_name'    => 'learning',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ),
        true
    );

    if ( ! is_wp_error( $page_id ) && $page_id ) {
        flush_rewrite_rules( false );
    }
}
add_action( 'init', 'mc_ensure_learning_page', 5 );

function mc_get_learning_page_url() {
    $page = get_page_by_path( 'learning', OBJECT, 'page' );
    return $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/learning/' );
}

function mc_theme_assets() {
    wp_enqueue_script( 'mc-tailwindcss-cdn', 'https://cdn.tailwindcss.com', array(), null, false );

    $base = get_stylesheet_directory();
    $uri  = get_stylesheet_directory_uri();

    $files = array(
        'style'           => 'style.css',
        'home'            => 'assets/css/home.css',
        'design'          => 'assets/css/design-system-v2.css',
        'ui'              => 'assets/css/reference-ui.css',
        'scale'           => 'assets/css/ui-scale.css',
        'detail'          => 'assets/css/course-detail.css',
        'learning'        => 'assets/css/learning.css',
        'states'          => 'assets/css/learning-states.css',
        'nav'             => 'assets/css/learning-nav.css',
        'polish'          => 'assets/css/course-ui-polish.css',
        'learning_polish' => 'assets/css/learning-ui-polish.css',
        'learning_center' => 'assets/css/learning-center-ui-v1.css',
        'override'        => 'assets/css/ui-reference-override.css',
        'cover'           => 'assets/css/course-cover.css',
        'learning_v2'     => 'assets/css/learning-v2.css',
        'learning_layout' => 'assets/css/learning-layout-fix.css',
        'player_wide'     => 'assets/css/player-wide.css',
        'course_card'     => 'assets/css/course-card-v2.css',
        'solid'           => 'assets/css/solid-color-overrides-v2.css',
        'course_center'   => 'assets/css/course-center.css',
    );

    $version = array();
    foreach ( $files as $key => $file ) {
        $version[ $key ] = file_exists( $base . '/' . $file )
            ? (string) filemtime( $base . '/' . $file )
            : '1.0.0';
    }

    wp_enqueue_style( 'mc-theme-style', $uri . '/style.css', array(), $version['style'] );
    wp_enqueue_style( 'mc-design-system-v2', $uri . '/assets/css/design-system-v2.css', array( 'mc-theme-style' ), $version['design'] );

    if ( is_front_page() ) {
        wp_enqueue_style( 'mc-home', $uri . '/assets/css/home.css', array( 'mc-design-system-v2' ), $version['home'] );
    }

    $content = get_post_field( 'post_content', get_queried_object_id() );
    $load_course_ui = is_front_page() || is_page( array( 'course-center', 'xueyuan-denglu', 'learning', 'learning-center' ) );

    if ( ! $load_course_ui ) {
        $load_course_ui =
            has_shortcode( $content, 'mathcourse_course_player' ) ||
            has_shortcode( $content, 'mathcourse_course_learning' ) ||
            has_shortcode( $content, 'math_course_center_v82' ) ||
            has_shortcode( $content, 'math_student_login' );
    }

    if ( $load_course_ui ) {
        wp_enqueue_style( 'mc-reference-ui', $uri . '/assets/css/reference-ui.css', array( 'mc-design-system-v2' ), $version['ui'] );
        wp_enqueue_style( 'mc-ui-scale', $uri . '/assets/css/ui-scale.css', array( 'mc-reference-ui' ), $version['scale'] );
        wp_enqueue_style( 'mc-course-ui-polish', $uri . '/assets/css/course-ui-polish.css', array( 'mc-ui-scale' ), $version['polish'] );
        wp_enqueue_style( 'mc-course-cover', $uri . '/assets/css/course-cover.css', array( 'mc-course-ui-polish' ), $version['cover'] );
    }

    wp_enqueue_style( 'mc-ui-reference-override', $uri . '/assets/css/ui-reference-override.css', array( 'mc-course-ui-polish' ), $version['override'] );

    if ( $load_course_ui ) {
        wp_enqueue_style( 'mc-course-card-v2', $uri . '/assets/css/course-card-v2.css', array( 'mc-ui-reference-override' ), $version['course_card'] );
    }

    if ( is_page( 'learning-center' ) ) {
        wp_enqueue_style( 'mc-learning-center-ui-v1', $uri . '/assets/css/learning-center-ui-v1.css', array( 'mc-course-card-v2' ), $version['learning_center'] );
    }

    if ( is_page( 'course-center' ) ) {
        wp_enqueue_style( 'mc-course-center', $uri . '/assets/css/course-center.css', array( 'mc-ui-reference-override' ), $version['course_center'] );
    }

    if ( $load_course_ui && ( isset( $_GET['course_id'] ) || has_shortcode( $content, 'mathcourse_course_directory' ) || is_front_page() ) ) {
        wp_enqueue_style( 'mc-course-detail', $uri . '/assets/css/course-detail.css', array( 'mc-course-card-v2' ), $version['detail'] );
    }

    if ( is_page( 'learning' ) || is_page_template( 'page-learning.php' ) ) {
        wp_enqueue_style( 'mc-learning', $uri . '/assets/css/learning.css', array( 'mc-ui-scale' ), $version['learning'] );
        wp_enqueue_style( 'mc-learning-states', $uri . '/assets/css/learning-states.css', array( 'mc-learning' ), $version['states'] );
        wp_enqueue_style( 'mc-learning-nav', $uri . '/assets/css/learning-nav.css', array( 'mc-learning-states' ), $version['nav'] );
        wp_enqueue_style( 'mc-learning-ui-polish', $uri . '/assets/css/learning-ui-polish.css', array( 'mc-learning-nav' ), $version['learning_polish'] );
        wp_enqueue_style(
            'mc-learning-v2',
            $uri . '/assets/css/learning-v2.css',
            array( 'mc-learning-ui-polish', 'mc-ui-reference-override' ),
            $version['learning_v2']
        );
        wp_enqueue_style( 'mc-learning-layout-fix', $uri . '/assets/css/learning-layout-fix.css', array( 'mc-learning-v2' ), $version['learning_layout'] );
        wp_enqueue_style( 'mc-player-wide', $uri . '/assets/css/player-wide.css', array( 'mc-learning-layout-fix' ), $version['player_wide'] );
    }

    // Global color overrides must not depend on a learning-page-only handle.
    wp_enqueue_style( 'mc-solid-color-v2', $uri . '/assets/css/solid-color-overrides-v2.css', array( 'mc-ui-reference-override' ), $version['solid'] );
}
add_action( 'wp_enqueue_scripts', 'mc_theme_assets' );

function mc_route_course_to_learning_player() {
    if ( ! is_page( 'course-center' ) || empty( $_GET['course_id'] ) ) {
        return;
    }

    $course_id = absint( $_GET['course_id'] );
    if ( ! $course_id ) {
        return;
    }

    $args = array( 'course_id' => $course_id );
    $lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
    if ( $lesson_id ) {
        $args['lesson_id'] = $lesson_id;
    }

    wp_safe_redirect( add_query_arg( $args, mc_get_learning_page_url() ), 302 );
    exit;
}
add_action( 'template_redirect', 'mc_route_course_to_learning_player', 1 );

function mc_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'mc_theme_setup' );
