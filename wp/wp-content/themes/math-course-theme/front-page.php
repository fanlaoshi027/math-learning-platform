<?php
/**
 * Math Course Theme - Front Page.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<main class="mc-home">
    <section class="mc-hero mc-hero--reference">
        <div class="mc-container mc-hero__inner">
            <div class="mc-hero__copy">
                <p class="mc-eyebrow">樊老师数学课堂 · 初中数学系统学习</p>
                <h1>把初中数学，<br><strong>学成一套体系</strong></h1>
                <p class="mc-hero__lead">按知识体系拆解知识点，从基础到综合应用，循序渐进，构建扎实的数学思维与解题能力。</p>
                <div class="mc-hero__actions">
                    <a class="mc-hero__button" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">浏览课程中心 <span>→</span></a>
                    <a class="mc-hero__button mc-hero__button--secondary" href="<?php echo esc_url( is_user_logged_in() ? home_url( '/learning-center/' ) : wp_login_url( home_url( '/learning-center/' ) ) ); ?>">开始学习之旅</a>
                </div>
                <div class="mc-hero__points">
                    <span>✓ 体系完整</span><span>✓ 内容精讲</span><span>✓ 方法总结</span><span>✓ 持续更新</span>
                </div>
            </div>
            <div class="mc-hero-map" aria-label="初中数学知识体系示意图">
                <div class="mc-hero-map__orbit"></div>
                <div class="mc-hero-map__center"><strong>初中数学</strong><span>三大知识体系</span></div>
                <div class="mc-hero-map__node mc-hero-map__node--algebra"><b>x²</b><span>代数</span><small>方程 · 代数式 · 不等式</small></div>
                <div class="mc-hero-map__node mc-hero-map__node--geometry"><b>△</b><span>几何</span><small>图形 · 证明 · 性质</small></div>
                <div class="mc-hero-map__node mc-hero-map__node--function"><b>y</b><span>函数</span><small>一次函数 · 二次函数</small></div>
                <span class="mc-hero-map__formula mc-hero-map__formula--a">a²+b²=c²</span>
                <span class="mc-hero-map__formula mc-hero-map__formula--b">y=kx+b</span>
            </div>
        </div>
    </section>

    <section class="mc-home-section mc-home-section--knowledge">
        <div class="mc-container">
            <div class="mc-home-section-title">
                <div><h2>初中数学专题</h2><p>按照知识体系，系统整理初中数学课程</p></div>
                <a href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">进入课程中心 →</a>
            </div>
            <?php
            if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
                echo do_shortcode( '[mathcourse_course_directory]' );
            } else {
                echo '<p class="mathcourse-directory__empty">课程中心正在加载。</p>';
            }
            ?>
        </div>
    </section>

    <section class="mc-home-section mc-home-section--support">
        <div class="mc-container">
            <div class="mc-home-section-title"><div><h2>配套课程</h2><p>针对不同学习需求，提供教材与中考配套课程</p></div><a href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', home_url( '/course-center/' ) ) ); ?>">查看全部课程 →</a></div>
            <?php
            if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
                echo do_shortcode( '[mathcourse_course_directory course_type="supplementary" limit="3"]' );
            }
            ?>
        </div>
    </section>

    <section class="mc-home-section mc-home-section--why">
        <div class="mc-container">
            <div class="mc-home-section-title mc-home-section-title--center"><div><h2>为什么选择樊老师数学课堂</h2><p>不是简单刷题，而是帮助孩子建立可迁移的数学体系</p></div></div>
            <div class="mc-feature-grid">
                <article><span>★</span><div><h3>教学经验丰富</h3><p>长期专注中学数学教学，深入研究知识体系与常见题型。</p></div></article>
                <article><span>▦</span><div><h3>体系清晰完整</h3><p>按照知识体系组织课程，让零散知识形成完整结构。</p></div></article>
                <article><span>▶</span><div><h3>讲解深入浅出</h3><p>重点难点层层拆解，让学生真正学会而不是记住。</p></div></article>
                <article><span>♥</span><div><h3>持续更新迭代</h3><p>根据教学反馈与考试变化，持续优化课程内容。</p></div></article>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
