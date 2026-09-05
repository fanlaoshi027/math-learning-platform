<?php
defined('ABSPATH') || exit;
get_header();

$course_center_url = home_url('/course-center/');
$system_url = add_query_arg('course_type', 'topic', $course_center_url);
$supplementary_url = add_query_arg('course_type', 'supplementary', $course_center_url);
$theme_uri = get_stylesheet_directory_uri();
?>
<main class="mc-home-v3">
    <section class="mc-home-v3__hero" aria-label="樊老师数学">
        <picture class="mc-home-v3__hero-media">
            <source media="(max-width: 700px)" srcset="<?php echo esc_url($theme_uri . '/assets/images/home-banner-mobile.svg'); ?>">
            <img src="<?php echo esc_url($theme_uri . '/assets/images/home-banner-desktop.svg'); ?>" alt="樊老师数学：让更多学生爱上数学，学好数学" fetchpriority="high">
        </picture>
        <a class="mc-home-v3__hero-link" href="<?php echo esc_url($course_center_url); ?>" aria-label="浏览课程中心"></a>
    </section>

    <section class="mc-home-v3__types mc-container" aria-labelledby="mc-home-types-title">
        <div class="mc-home-v3__heading">
            <div>
                <span class="mc-home-v3__eyebrow">COURSE PATHS</span>
                <h2 id="mc-home-types-title">两大课程体系 · 满足不同学习需求</h2>
                <p>系统课负责建立知识体系，教辅配套课负责紧跟教材与题型强化。</p>
            </div>
            <a href="<?php echo esc_url($course_center_url); ?>">了解全部课程 <span>→</span></a>
        </div>
        <div class="mc-home-v3__type-grid">
            <a class="mc-home-v3__type-card mc-home-v3__type-card--system" href="<?php echo esc_url($system_url); ?>">
                <img src="<?php echo esc_url($theme_uri . '/assets/images/home-system.svg'); ?>" alt="系统课" loading="lazy">
                <span class="mc-home-v3__type-overlay"><b>进入系统课 →</b></span>
            </a>
            <a class="mc-home-v3__type-card mc-home-v3__type-card--supplementary" href="<?php echo esc_url($supplementary_url); ?>">
                <img src="<?php echo esc_url($theme_uri . '/assets/images/home-supplementary.svg'); ?>" alt="教辅配套课" loading="lazy">
                <span class="mc-home-v3__type-overlay"><b>进入配套课 →</b></span>
            </a>
        </div>
    </section>

    <section class="mc-home-v3__popular mc-container" aria-labelledby="mc-home-popular-title">
        <div class="mc-home-v3__heading mc-home-v3__heading--popular">
            <div>
                <span class="mc-home-v3__eyebrow">POPULAR COURSES</span>
                <h2 id="mc-home-popular-title">热门课程</h2>
                <p>从两套课程体系中精选当前值得优先学习的课程。</p>
            </div>
            <a href="<?php echo esc_url($course_center_url); ?>">查看全部课程 <span>→</span></a>
        </div>
        <div class="mc-home-v3__popular-wrap">
            <?php
            if (shortcode_exists('mathcourse_course_directory')) {
                echo do_shortcode('[mathcourse_course_directory show_filters="0"]');
            } elseif (shortcode_exists('mathcourse_course_center')) {
                echo do_shortcode('[mathcourse_course_center show_filters="0"]');
            } else {
                echo '<p class="mc-home-v3__empty">课程系统暂不可用。</p>';
            }
            ?>
        </div>
    </section>

    <section class="mc-home-v3__features mc-container" aria-label="课程特色">
        <div class="mc-home-v3__feature"><span>◆</span><div><b>内容系统</b><small>覆盖初中数学核心知识点</small></div></div>
        <div class="mc-home-v3__feature"><span>●</span><div><b>讲解清晰</b><small>复杂问题一步一步讲透</small></div></div>
        <div class="mc-home-v3__feature"><span>▮</span><div><b>紧扣考点</b><small>直击中考重点与难点</small></div></div>
        <div class="mc-home-v3__feature"><span>♥</span><div><b>持续更新</b><small>跟随教材与考试变化更新</small></div></div>
    </section>

    <section class="mc-home-v3__cta mc-container">
        <div class="mc-home-v3__cta-inner">
            <div><strong>从现在开始，让数学成为你的优势！</strong><span>选择适合自己的课程，开启高效学习之旅。</span></div>
            <a href="<?php echo esc_url($course_center_url); ?>">立即开始学习 <span>→</span></a>
        </div>
    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var wrap=document.querySelector('.mc-home-v3__popular-wrap');
    if(!wrap) return;
    var cards=wrap.querySelectorAll('.mathcourse-center__card');
    cards.forEach(function(card,index){ if(index>=4) card.classList.add('mc-home-v3__course-card--extra'); });
});
</script>
<?php get_footer(); ?>