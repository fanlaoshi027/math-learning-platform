<?php
defined( 'ABSPATH' ) || exit;
if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) { show_admin_bar( false ); }
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
            <span class="mc-brand__mark" aria-hidden="true"><i></i></span>
            <span class="mc-brand__text">樊老师数学<small>好方法 · 学得更轻松</small></span>
        </a>
        <button class="mc-mobile-menu" type="button" aria-expanded="false" aria-controls="mc-mobile-nav"><span></span><span></span><span></span><b>菜单</b></button>
        <nav id="mc-mobile-nav" class="mc-site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a class="<?php echo is_page( 'course-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">课程中心</a>
            <?php if ( is_user_logged_in() ) : ?>
                <a class="<?php echo is_page( 'learning-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">樊同学的学习中心</a>
            <?php endif; ?>
        </nav>
        <div class="mc-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <?php $current_user = wp_get_current_user(); $student_name = $current_user->display_name ?: $current_user->user_login; $student_initial = function_exists( 'mb_substr' ) ? mb_substr( $student_name, 0, 1 ) : substr( $student_name, 0, 1 ); ?>
                <a class="mc-user-pill" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>"><b><?php echo esc_html( $student_initial ); ?></b><span><?php echo esc_html( $student_name ); ?></span><i>|</i><span>退出</span></a>
            <?php else : ?>
                <a class="mc-header-login" href="<?php echo esc_url( wp_login_url( home_url( '/learning-center/' ) ) ); ?>">学生登录</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var btn=document.querySelector('.mc-mobile-menu'),nav=document.getElementById('mc-mobile-nav');
    if(!btn||!nav)return;
    btn.addEventListener('click',function(){var open=btn.getAttribute('aria-expanded')==='true';btn.setAttribute('aria-expanded',String(!open));nav.classList.toggle('is-open',!open);});
});
</script>