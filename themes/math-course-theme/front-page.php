<?php
defined('ABSPATH') || exit;
get_header();

$course_center_url = home_url('/course-center/');
$system_url = add_query_arg('course_type', 'topic', $course_center_url);
$supplementary_url = add_query_arg('course_type', 'supplementary', $course_center_url);
?>
<main class="mc-home-v3">
    <section class="mc-home-v3__hero" aria-label="樊老师数学">
        <div class="mc-home-v3__hero-art" aria-hidden="true">
            <div class="mc-home-v3__chalkboard">
                <span class="eq eq-1">a² + b² = c²</span>
                <span class="eq eq-2">x = −b ± √b²−4ac</span>
                <span class="eq eq-3">y</span><span class="eq eq-4">x</span>
                <span class="triangle"></span>
            </div>
            <div class="mc-home-v3__desk"></div>
        </div>
        <div class="mc-home-v3__hero-content">
            <div class="mc-home-v3__hero-copy">
                <h1><span>让更多学生</span><strong><em>爱上数学，</em>学好数学</strong></h1>
                <p>系统的课程 · 清晰的讲解 · 实用的方法</p>
                <a class="mc-home-v3__hero-button" href="<?php echo esc_url($course_center_url); ?>">浏览课程 <span>→</span></a>
            </div>
        </div>
    </section>

    <section class="mc-home-v3__types" aria-label="课程体系">
        <div class="mc-home-v3__type-grid">
            <a class="mc-home-v3__type-card mc-home-v3__type-card--system" href="<?php echo esc_url($system_url); ?>">
                <div class="mc-home-v3__type-art" aria-hidden="true"><i></i><i></i><i></i></div>
                <div class="mc-home-v3__type-copy">
                    <h2>系统课</h2>
                    <p>构建完整的知识体系</p>
                    <p>从基础到提升，系统掌握初中数学</p>
                    <span>进入系统课 →</span>
                </div>
            </a>
            <a class="mc-home-v3__type-card mc-home-v3__type-card--supplementary" href="<?php echo esc_url($supplementary_url); ?>">
                <div class="mc-home-v3__type-art" aria-hidden="true"><i></i><i></i><i></i></div>
                <div class="mc-home-v3__type-copy">
                    <h2>教辅配套课</h2>
                    <p>紧扣教材与教辅</p>
                    <p>逐题精讲，吃透每一道题</p>
                    <span>进入配套课 →</span>
                </div>
            </a>
        </div>
    </section>
</main>
<?php get_footer(); ?>