<?php
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/css/ui-v2.css' ); ?>">
</head>
<body <?php body_class( 'mathcourse-site' ); ?>>
<?php wp_body_open(); ?>
<header class="mc-site-header">
    <div class="mc-container mc-site-header__inner">
        <a class="mc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="樊老师数学课堂首页"><span class="mc-brand__text">樊老师<span>数学课堂</span></span></a>
        <nav class="mc-site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a class="<?php echo is_page( 'course-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">课程中心</a>
            <?php if ( is_user_logged_in() ) : ?><a class="<?php echo is_page( 'learning-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">学习中心</a><?php endif; ?>
            <a class="<?php echo is_page( 'about-teacher' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/about-teacher/' ) ); ?>">关于老师</a>
        </nav>
        <form class="mc-header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"><input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="搜索数学知识点" aria-label="搜索数学知识点"><button type="submit">搜索</button></form>
        <div class="mc-header-actions">
            <?php if ( is_user_logged_in() ) : ?><a class="mc-user-pill" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">我的学习 <span class="mc-user-pill__arrow" aria-hidden="true">→</span></a><?php else : ?><?php $login_url = wp_login_url( home_url( '/learning-center/' ) ); ?><a class="mc-header-login" href="<?php echo esc_url( $login_url ); ?>">登录</a><?php if ( get_option( 'users_can_register' ) ) : ?><a class="mc-header-register" href="<?php echo esc_url( wp_registration_url() ); ?>">注册</a><?php endif; ?><?php endif; ?>
        </div>
    </div>
</header>