<?php
defined( 'ABSPATH' ) || exit;
get_header();
$course_center_url = home_url( '/course-center/' );
$topic_url = add_query_arg( 'course_type', 'topic', $course_center_url );
$supplementary_url = add_query_arg( 'course_type', 'supplementary', $course_center_url );
$learning_url = home_url( '/learning-center/' );
?>
<main class="mc-home">
<section class="mc-home-hero">
    <div class="mc-container mc-home-hero__inner">
        <div class="mc-home-hero__copy">
            <span class="mc-kicker"><span></span>樊老师数学 · 初中数学在线课堂</span>
            <h1>把初中数学，<br><strong>学成一套体系</strong></h1>
            <p>按照知识体系重新梳理，从基础概念到综合应用，循序渐进，把每一个知识点真正学明白。</p>
            <div class="mc-home-hero__actions">
                <a class="mc-btn mc-btn--primary" href="<?php echo esc_url( $course_center_url ); ?>">浏览课程中心 <span>→</span></a>
                <a class="mc-btn mc-btn--ghost" href="<?php echo esc_url( is_user_logged_in() ? $learning_url : wp_login_url( $learning_url ) ); ?>">开始学习之旅</a>
            </div>
            <div class="mc-home-hero__points" aria-label="课程特点">
                <span>✓ 体系完整</span><span>✓ 内容精讲</span><span>✓ 方法实用</span><span>✓ 持续更新</span>
            </div>
        </div>
        <div class="mc-home-hero__visual" aria-hidden="true">
            <div class="mc-hero-orbit mc-hero-orbit--one"></div>
            <div class="mc-hero-orbit mc-hero-orbit--two"></div>
            <div class="mc-hero-subject mc-hero-subject--algebra"><strong>x²</strong><small>代数</small></div>
            <div class="mc-hero-subject mc-hero-subject--geometry"><strong>△</strong><small>几何</small></div>
            <div class="mc-hero-subject mc-hero-subject--function"><strong>ƒ</strong><small>函数</small></div>
            <div class="mc-hero-center"><strong>初中数学</strong><span>三大知识体系</span></div>
            <span class="mc-hero-formula mc-hero-formula--one">a² + b² = c²</span>
            <span class="mc-hero-formula mc-hero-formula--two">y = kx + b</span>
        </div>
    </div>
</section>

<section class="mc-home-types">
    <div class="mc-container">
        <div class="mc-section-heading">
            <div><span class="mc-section-heading__eyebrow">KNOWLEDGE SYSTEM</span><h2>初中数学专题</h2></div>
            <p>按照知识体系，系统整理初中数学课程</p>
        </div>
        <div class="mc-type-grid mc-type-grid--three">
            <a class="mc-type-card mc-type-card--topic mc-subject-card mc-subject-card--algebra" href="<?php echo esc_url( $topic_url ); ?>">
                <div class="mc-subject-card__visual"><strong>x²</strong><span>代数</span></div>
                <div class="mc-subject-card__body"><div class="mc-type-card__tag">代数</div><h3>代数</h3><p>有理数、整式、方程、不等式等重点内容。</p><span class="mc-subject-card__link">查看全部 →</span></div>
            </a>
            <a class="mc-type-card mc-type-card--topic mc-subject-card mc-subject-card--geometry" href="<?php echo esc_url( $topic_url ); ?>">
                <div class="mc-subject-card__visual"><strong>△</strong><span>几何</span></div>
                <div class="mc-subject-card__body"><div class="mc-type-card__tag">几何</div><h3>几何</h3><p>线段、角、三角形、全等与相似等核心专题。</p><span class="mc-subject-card__link">查看全部 →</span></div>
            </a>
            <a class="mc-type-card mc-type-card--topic mc-subject-card mc-subject-card--function" href="<?php echo esc_url( $topic_url ); ?>">
                <div class="mc-subject-card__visual"><strong>ƒ</strong><span>函数</span></div>
                <div class="mc-subject-card__body"><div class="mc-type-card__tag">函数</div><h3>函数</h3><p>一次函数、反比例函数、二次函数等重点内容。</p><span class="mc-subject-card__link">查看全部 →</span></div>
            </a>
        </div>
    </div>
</section>

<section class="mc-home-courses">
    <div class="mc-container">
        <div class="mc-section-heading mc-section-heading--courses">
            <div><span class="mc-section-heading__eyebrow">COURSES</span><h2>配套课程</h2><p>针对不同学习需求，提供教辅配套视频课程</p></div>
            <a href="<?php echo esc_url( $supplementary_url ); ?>">查看全部课程 <span>→</span></a>
        </div>
        <?php if ( shortcode_exists( 'mathcourse_course_directory' ) ) { echo do_shortcode( '[mathcourse_course_directory]' ); } elseif ( shortcode_exists( 'mathcourse_course_center' ) ) { echo do_shortcode( '[mathcourse_course_center]' ); } else { echo '<p class="mathcourse-directory__empty">课程中心正在加载。</p>'; } ?>
    </div>
</section>

<section class="mc-home-note">
    <div class="mc-container">
        <div class="mc-home-note__inner">
            <div><h2>为什么选择樊老师数学课堂</h2><p>体系清晰、内容精讲、方法实用，让每一次学习都有明确的方向。</p></div>
            <div class="mc-home-note__features"><span>★<b>经验总结</b></span><span>▦<b>体系完整</b></span><span>▶<b>讲解深入</b></span><span>♥<b>持续更新</b></span></div>
        </div>
    </div>
</section>
</main>
<?php get_footer(); ?>