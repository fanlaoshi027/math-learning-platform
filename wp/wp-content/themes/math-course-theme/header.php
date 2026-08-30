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
        <a class="mc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="樊老师数学首页">
            <span class="mc-brand__mark" aria-hidden="true">樊</span>
            <span class="mc-brand__text">樊老师数学</span>
        </a>

        <nav class="mc-site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a class="<?php echo isset( $_GET['course_type'] ) && 'topic' === sanitize_key( wp_unslash( $_GET['course_type'] ) ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', home_url( '/course-center/' ) ) ); ?>">专题课</a>
            <a class="<?php echo isset( $_GET['course_type'] ) && 'supplementary' === sanitize_key( wp_unslash( $_GET['course_type'] ) ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', home_url( '/course-center/' ) ) ); ?>">教辅配套</a>
            <?php if ( is_user_logged_in() ) : ?>
                <a class="<?php echo is_page( 'learning-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">我的学习</a>
            <?php endif; ?>
        </nav>

        <div class="mc-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <a class="mc-user-pill" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>"><span class="mc-user-pill__dot" aria-hidden="true"></span> 我的学习 <span class="mc-user-pill__arrow" aria-hidden="true">→</span></a>
            <?php else : ?>
                <?php $login_url = wp_login_url( home_url( '/learning-center/' ) ); ?>
                <a class="mc-header-login" href="<?php echo esc_url( $login_url ); ?>">登录</a>
            <?php endif; ?>
        </div>
    </div>
</header>