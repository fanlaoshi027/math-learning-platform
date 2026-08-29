<?php
defined('ABSPATH') || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('mathcourse-site'); ?>>
<?php wp_body_open(); ?>
<header class="mc-site-header">
    <div class="mc-container mc-site-header__inner">
        <a class="mc-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="樊老师数学首页">
            <span class="mc-brand__mark">樊</span>
            <span class="mc-brand__text">樊老师数学</span>
        </a>
        <nav class="mc-site-nav" aria-label="主导航">
            <?php
            $course_center_url = home_url('/course-center/');
            ?>
            <a href="<?php echo esc_url($course_center_url); ?>">课程中心</a>
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">退出登录</a>
            <?php else : ?>
                <?php $login_url = wp_login_url(home_url('/')); ?>
                <a class="mc-site-nav__login" href="<?php echo esc_url($login_url); ?>">学员登录</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
