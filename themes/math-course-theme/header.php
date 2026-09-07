<?php
defined( 'ABSPATH' ) || exit;
if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) { show_admin_bar( false ); }
$mathcourse_logo_id = absint( get_option( 'mathcourse_site_logo_id', 0 ) );
$mathcourse_logo_url = $mathcourse_logo_id && wp_attachment_is_image( $mathcourse_logo_id ) ? wp_get_attachment_image_url( $mathcourse_logo_id, 'medium' ) : '';
$mathcourse_login_pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_key' => '_mathcourse_student_login', 'meta_value' => 'yes' ) );
if ( ! empty( $mathcourse_login_pages ) ) {
    $mathcourse_login_url = get_permalink( $mathcourse_login_pages[0]->ID );
} else {
    $mathcourse_login_page = get_page_by_path( 'student-login', OBJECT, 'page' );
    $mathcourse_login_url = $mathcourse_login_page ? get_permalink( $mathcourse_login_page->ID ) : home_url( '/student-login/' );
}
$mathcourse_logout_url = wp_logout_url( home_url( '/' ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'mathcourse-site' ); ?>>
<?php wp_body_open(); ?>
<style id="mc-site-logo-v4">
.mc-site-header .mc-brand__mark{position:relative;display:flex;align-items:center;justify-content:center;flex:0 0 40px;width:40px;height:34px;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:transparent!important;font-size:0!important;overflow:hidden}
.mc-site-header .mc-brand__mark img{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain}
.mc-site-header .mc-brand__mark.is-custom{width:44px;height:38px;flex-basis:44px}
.mc-site-header .mc-brand__mark:before,.mc-site-header .mc-brand__mark:after{content:"";position:absolute;display:block;box-sizing:border-box}
.mc-site-header .mc-brand__mark:before{left:2px;top:6px;width:17px;height:25px;border:3px solid #1769d8;border-right-width:2px;border-radius:4px 2px 2px 8px;transform:skewY(5deg);background:#fff}
.mc-site-header .mc-brand__mark:after{right:2px;top:6px;width:17px;height:25px;border:3px solid #1769d8;border-left-width:2px;border-radius:2px 4px 8px 2px;transform:skewY(-5deg);background:#fff}
.mc-site-header .mc-brand__mark i{position:absolute;z-index:2;left:18px;top:5px;width:4px;height:27px;border-radius:99px;background:#ff9418}
.mc-site-header .mc-brand__mark.has-custom:before,.mc-site-header .mc-brand__mark.has-custom:after,.mc-site-header .mc-brand__mark.has-custom i{display:none!important}
@media(max-width:700px){.mc-site-header .mc-brand__mark{flex-basis:36px;width:36px;height:31px}.mc-site-header .mc-brand__mark.is-custom{width:40px;height:34px;flex-basis:40px}}
.mc-site-footer .mc-footer-brand>span:first-child{position:relative;width:40px;height:34px;display:flex;align-items:center;justify-content:center;flex:0 0 40px;font-size:0;color:transparent;border:0;border-radius:0;background:transparent;overflow:hidden}
.mc-site-footer .mc-footer-brand>span:first-child img{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain}
</style>
<header class="mc-site-header">
    <div class="mc-container mc-site-header__inner">
        <a class="mc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="樊老师数学首页">
            <span class="mc-brand__mark <?php echo $mathcourse_logo_url ? 'is-custom has-custom' : ''; ?>" aria-hidden="true"><?php if ( $mathcourse_logo_url ) : ?><img src="<?php echo esc_url( $mathcourse_logo_url ); ?>" alt=""><?php else : ?><i></i><?php endif; ?></span>
            <span class="mc-brand__text">樊老师数学<small>好方法 · 学得更轻松</small></span>
        </a>
        <button class="mc-mobile-menu" type="button" aria-expanded="false" aria-controls="mc-mobile-nav"><span></span><span></span><span></span><b>菜单</b></button>
        <nav id="mc-mobile-nav" class="mc-site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">首页</a>
            <a class="<?php echo is_page( 'course-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">课程中心</a>
            <a class="<?php echo is_page( 'learning-center' ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">学习中心</a>
        </nav>
        <div class="mc-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <?php $current_user = wp_get_current_user(); $student_name = $current_user->display_name ?: $current_user->user_login; $student_initial = function_exists( 'mb_substr' ) ? mb_substr( $student_name, 0, 1 ) : substr( $student_name, 0, 1 ); ?>
                <a class="mc-user-pill" href="<?php echo esc_url( $mathcourse_logout_url ); ?>"><b><?php echo esc_html( $student_initial ); ?></b><span><?php echo esc_html( $student_name ); ?></span><i>|</i><span>退出</span></a>
            <?php else : ?>
                <a class="mc-header-login" href="<?php echo esc_url( $mathcourse_login_url ); ?>">学生登录</a>
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
