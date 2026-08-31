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
            <span class="mc-brand__text"><strong>樊老师数学</strong><small>初中数学在线课堂</small></span>
        </a>

        <nav class="mc-site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a class="<?php echo is_page( 'course-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">课程中心</a>
            <a class="<?php echo is_page( 'learning-center' ) || is_page( 'learning' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">学习中心</a>
            <a href="<?php echo esc_url( home_url( '/#about-teacher' ) ); ?>">关于老师</a>
        </nav>

        <div class="mc-header-actions">
            <form class="mc-header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="搜索课程或专题" aria-label="搜索课程或专题">
                <button type="submit" aria-label="搜索">⌕</button>
            </form>
            <?php if ( is_user_logged_in() ) : ?>
                <a class="mc-header-login" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">退出</a>
            <?php else : ?>
                <?php $login_url = wp_login_url( home_url( '/learning-center/' ) ); ?>
                <a class="mc-header-login" href="<?php echo esc_url( $login_url ); ?>">登录 / 注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>