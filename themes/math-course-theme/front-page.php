<?php
defined('ABSPATH') || exit;
get_header();
?>
<link rel="stylesheet" id="mc-home-reference-mobile" href="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/home-reference-mobile.css?ver=' . rawurlencode((string) filemtime(get_stylesheet_directory() . '/assets/home-reference-mobile.css'))); ?>">
<?php
$course_center_url = home_url('/course-center/');
$system_url = add_query_arg('course_type', 'topic', $course_center_url);
$supplementary_url = add_query_arg('course_type', 'supplementary', $course_center_url);
$learning_url = home_url('/learning-center/');

$courses = get_posts(array(
    'post_type'      => 'courses',
    'post_status'    => 'publish',
    'posts_per_page' => 4,
    'orderby'        => 'date',
    'order'          => 'DESC',
));
?>

<main class="mc-home-v3">
    <header class="mc-home-v3__header">
        <div class="mc-home-v3__header-inner">
            <a class="mc-home-v3__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="樊老师数学首页">
                <span class="mc-home-v3__brand-mark" aria-hidden="true"><i></i><b></b></span>
                <span class="mc-home-v3__brand-copy"><strong>樊老师数学</strong><small>好方法 · 学得更轻松</small></span>
            </a>
            <nav class="mc-home-v3__nav" aria-label="主导航">
                <a class="is-active" href="<?php echo esc_url(home_url('/')); ?>">首页</a>
                <a href="<?php echo esc_url($course_center_url); ?>">课程中心</a>
                <a href="<?php echo esc_url(home_url('/learning-center/')); ?>">学习中心</a>
                <a href="#about">关于我们</a>
            </nav>
            <div class="mc-home-v3__header-tools">
                <div class="mc-home-v3__search"><span>搜索课程、知识点...</span><b>⌕</b></div>
                <?php if (is_user_logged_in()) : ?>
                    <a class="mc-home-v3__account" href="<?php echo esc_url($learning_url); ?>"><i>●</i><span>学习中心</span></a>
                <?php else : ?>
                    <a class="mc-home-v3__account" href="<?php echo esc_url(wp_login_url($learning_url)); ?>"><i>●</i><span>登录 / 注册</span></a>
                <?php endif; ?>
            </div>
            <button class="mc-home-v3__menu" type="button" aria-expanded="false" aria-controls="mc-home-mobile-nav"><span></span><span></span><span></span></button>
        </div>
        <nav id="mc-home-mobile-nav" class="mc-home-v3__mobile-nav" aria-label="移动端导航">
            <a href="<?php echo esc_url(home_url('/')); ?>">首页</a>
            <a href="<?php echo esc_url($course_center_url); ?>">课程中心</a>
            <a href="<?php echo esc_url(home_url('/learning-center/')); ?>">学习中心</a>
            <a href="#about">关于我们</a>
        </nav>
    </header>

    <section class="mc-home-v3__hero" aria-label="樊老师数学">
        <div class="mc-home-v3__hero-bg" aria-hidden="true">
            <div class="mc-home-v3__chalkboard"><span>a² + b² = c²</span><span>x = −b ± √b²−4ac</span><span>数学不难</span><span>方法很重要！</span></div>
            <div class="mc-home-v3__teacher"><i class="head"></i><i class="hair"></i><i class="body"></i><i class="arm arm-a"></i><i class="arm arm-b"></i></div>
            <div class="mc-home-v3__hero-line"></div>
        </div>
        <div class="mc-home-v3__hero-inner">
            <div class="mc-home-v3__hero-copy">
                <h1><span>让更多学生</span><strong><em>爱上数学，</em>学好数学</strong></h1>
                <p>系统的课程 · 清晰的讲解 · 实用的方法</p>
                <a class="mc-home-v3__hero-button" href="<?php echo esc_url($course_center_url); ?>">浏览课程 <b>→</b></a>
            </div>
        </div>
        <div class="mc-home-v3__hero-features" aria-label="课程特色">
            <div><i>◇</i><span><b>专业专注</b><small>深耕初中数学</small></span></div>
            <div><i>▣</i><span><b>循序渐进</b><small>由浅入深，逐步提升</small></span></div>
            <div><i>⊞</i><span><b>实战实用</b><small>紧扣中考，直击考点</small></span></div>
            <div><i>♧</i><span><b>服务贴心</b><small>学习路上不孤单</small></span></div>
        </div>
    </section>

    <section class="mc-home-v3__section mc-home-v3__types">
        <div class="mc-home-v3__section-heading">
            <div><span class="accent"></span><h2>两大课程体系 · 满足不同学习需求</h2></div>
            <a href="<?php echo esc_url($course_center_url); ?>">了解更多 →</a>
        </div>
        <div class="mc-home-v3__type-grid">
            <a class="mc-home-v3__type-card mc-home-v3__type-card--system" href="<?php echo esc_url($system_url); ?>">
                <div class="mc-home-v3__book-stack" aria-hidden="true"><i></i><i></i><i></i><b></b></div>
                <div class="mc-home-v3__type-copy"><h3>系统课</h3><p>构建完整的知识体系</p><p>从基础到提升，系统掌握初中数学</p><span>进入系统课 →</span></div>
            </a>
            <a class="mc-home-v3__type-card mc-home-v3__type-card--supplementary" href="<?php echo esc_url($supplementary_url); ?>">
                <div class="mc-home-v3__open-book" aria-hidden="true"><i></i><b></b><em></em></div>
                <div class="mc-home-v3__type-copy"><h3>教辅配套课</h3><p>紧扣教材与教辅</p><p>逐题精讲，吃透每一道题</p><span>进入配套课 →</span></div>
            </a>
        </div>
    </section>

    <section class="mc-home-v3__section mc-home-v3__popular">
        <div class="mc-home-v3__section-heading">
            <div><span class="accent"></span><h2>热门课程</h2><p>精选优质课程，助你高效提升</p></div>
            <a href="<?php echo esc_url($course_center_url); ?>">查看更多课程 →</a>
        </div>
        <div class="mc-home-v3__course-grid">
            <?php if ($courses) : foreach ($courses as $index => $course) :
                $course_url = add_query_arg('course_id', $course->ID, home_url('/learning/'));
                $cover = get_post_meta($course->ID, '_mathcourse_cover', true);
                $title = get_the_title($course->ID);
                $grade = (string) get_post_meta($course->ID, '_mathcourse_grade', true);
                if (!$grade) { $grade = '8'; }
                $cover_classes = 'mc-home-v3__course-cover mc-home-v3__course-cover--' . ($index + 1);
            ?>
                <article class="mc-home-v3__course-card">
                    <a class="<?php echo esc_attr($cover_classes); ?>" href="<?php echo esc_url($course_url); ?>">
                        <?php if ($cover) : ?><img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy"><?php endif; ?>
                        <span class="mc-home-v3__cover-copy"><b><?php echo esc_html($title); ?></b><small><?php echo esc_html('八年级' === $grade || '8' === $grade ? '八年级上册' : '初中数学系统课'); ?></small><em><?php echo esc_html($index === 0 ? '热门推荐' : '系统精讲'); ?></em></span>
                    </a>
                    <div class="mc-home-v3__course-body">
                        <h3><?php echo esc_html($title); ?></h3>
                        <div class="mc-home-v3__tags"><span><?php echo esc_html('8' === $grade ? '八年级' : ('9' === $grade ? '九年级' : '初中')); ?></span><span>视频精讲</span></div>
                        <a class="mc-home-v3__course-button" href="<?php echo esc_url($course_url); ?>">查看课程</a>
                    </div>
                </article>
            <?php endforeach; else : ?>
                <div class="mc-home-v3__course-empty">课程正在整理中，敬请期待。</div>
            <?php endif; ?>
        </div>
    </section>

    <section id="about" class="mc-home-v3__section mc-home-v3__reasons">
        <div class="mc-home-v3__section-heading"><div><span class="accent"></span><h2>为什么选择樊老师数学</h2><p>用心做教育，帮助每一位学生稳步提升</p></div></div>
        <div class="mc-home-v3__reason-grid">
            <div><i>◆</i><b>内容系统</b><span>覆盖初中数学全部知识点</span></div>
            <div><i>●</i><b>讲解清晰</b><span>复杂问题简单化</span></div>
            <div><i>▮</i><b>紧扣考点</b><span>直击中考重难点</span></div>
            <div><i>♥</i><b>学生好评</b><span>已帮助数千名学生提升</span></div>
        </div>
    </section>

    <section class="mc-home-v3__cta">
        <div class="mc-home-v3__cta-art" aria-hidden="true"><i></i><b></b><em></em></div>
        <div><strong>从现在开始，让数学成为你的优势！</strong><span>选择适合自己的课程，开启高效学习之旅</span></div>
        <a href="<?php echo esc_url($course_center_url); ?>">立即开始学习 →</a>
    </section>

    <footer class="mc-home-v3__footer">
        <div class="mc-home-v3__footer-inner">
            <a class="mc-home-v3__footer-brand" href="<?php echo esc_url(home_url('/')); ?>"><i>↗</i><span><b>樊老师数学</b><small>好方法 · 学得更轻松</small></span></a>
            <nav><a href="<?php echo esc_url(home_url('/')); ?>">首页</a><a href="<?php echo esc_url($course_center_url); ?>">课程中心</a><a href="<?php echo esc_url(home_url('/learning-center/')); ?>">学习中心</a><a href="#about">关于我们</a></nav>
            <p>用数学，点亮更大的可能！</p>
        </div>
        <div class="mc-home-v3__copyright">© <?php echo esc_html(wp_date('Y')); ?> 樊老师数学 · 专注初中数学系统学习 · 鄂ICP备2026043242号</div>
    </footer>
</main>

<script>
document.addEventListener('DOMContentLoaded',function(){
    var btn=document.querySelector('.mc-home-v3__menu'),nav=document.getElementById('mc-home-mobile-nav');
    if(!btn||!nav)return;
    btn.addEventListener('click',function(){var open=btn.getAttribute('aria-expanded')==='true';btn.setAttribute('aria-expanded',String(!open));nav.classList.toggle('is-open',!open);});
});
</script>
<?php get_footer(); ?>