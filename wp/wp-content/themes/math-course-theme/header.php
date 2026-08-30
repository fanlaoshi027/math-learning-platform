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
            <span class="mc-brand__mark" aria-hidden="true">∑</span>
            <span class="mc-brand__text">樊老师数学</span>
        </a>

        <nav class="mc-site-nav" aria-label="主导航">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', home_url( '/course-center/' ) ) ); ?>">专题课程</a>
            <a href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', home_url( '/course-center/' ) ) ); ?>">教辅配套</a>
            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">我的课程</a>
            <?php endif; ?>
        </nav>

        <div class="mc-header-actions">
            <form class="mc-header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="搜索课程、知识点…" aria-label="搜索课程">
                <button type="submit" aria-label="搜索">⌕</button>
            </form>
            <?php if ( is_user_logged_in() ) : ?>
                <a class="mc-user-pill" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>"><span aria-hidden="true">●</span> 我的课程</a>
            <?php else : ?>
                <?php $login_url = wp_login_url( home_url( '/learning-center/' ) ); ?>
                <a class="mc-header-login" href="<?php echo esc_url( $login_url ); ?>">登录</a>
                <a class="mc-header-register" href="<?php echo esc_url( wp_registration_url() ); ?>">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>
