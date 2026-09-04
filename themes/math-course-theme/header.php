<?php
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'mathcourse-site' ); ?>>
<?php wp_body_open(); ?>
<header class="mc-site-header">
    <div class="mc-container mc-site-header__inner">
        <a class="mc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="樊老师数学课堂首页">
            <span class="mc-brand__mark" aria-hidden="true">□</span>
            <span class="mc-brand__text">樊老师数学课堂<small>跟中考名师 学扎实数学</small></span>
        </a>

        <nav class="mc-site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a class="<?php echo is_page( 'course-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">课程中心</a>
            <?php if ( is_user_logged_in() ) : ?>
                <a class="<?php echo is_page( 'learning-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">学习中心</a>
            <?php endif; ?>
        </nav>

        <div class="mc-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <?php $current_user = wp_get_current_user(); $student_name = $current_user->display_name ?: $current_user->user_login; $student_initial = function_exists( 'mb_substr' ) ? mb_substr( $student_name, 0, 1 ) : substr( $student_name, 0, 1 ); ?>
                <a class="mc-user-pill" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>"><b><?php echo esc_html( $student_initial ); ?></b><span><?php echo esc_html( $student_name ); ?></span><i>|</i><span>退出</span></a>
            <?php else : ?>
                <?php $login_url = wp_login_url( home_url( '/learning-center/' ) ); ?>
                <a class="mc-header-login" href="<?php echo esc_url( $login_url ); ?>">学生登录</a>
            <?php endif; ?>
        </div>
    </div>
</header>