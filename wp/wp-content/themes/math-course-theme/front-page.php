<?php
defined( 'ABSPATH' ) || exit;
get_header();
$course_center_url = home_url( '/course-center/' );
$learning_url = home_url( '/learning-center/' );
?>
<main class="mc-home">
<section class="mc-home-hero">
  <div class="mc-container mc-home-hero__inner">
    <div class="mc-home-hero__copy">
      <div class="mc-kicker"><span></span>两大核心分类：初中系统课 + 教辅配套课</div>
      <h1>把初中数学，<strong>学成一套体系</strong></h1>
      <p>按数学知识体系组织课程，从基础到综合应用，循序渐进，构建扎实的数学基本功。</p>
      <div class="mc-home-hero__actions">
        <a class="mc-btn mc-btn--primary" href="<?php echo esc_url( $course_center_url ); ?>">浏览课程中心 <span>→</span></a>
        <?php if ( is_user_logged_in() ) : ?><a class="mc-btn mc-btn--ghost" href="<?php echo esc_url( $learning_url ); ?>">▶ 开始学习之旅</a><?php else : ?><a class="mc-btn mc-btn--ghost" href="<?php echo esc_url( wp_login_url( $learning_url ) ); ?>">▶ 开始学习之旅</a><?php endif; ?>
      </div>
      <div class="mc-home-trust"><span>✓ 体系完整</span><span>✓ 内容精讲</span><span>✓ 方法实用</span><span>✓ 持续更新</span></div>
    </div>
    <div class="mc-home-types-mini" aria-label="课程体系">
      <a href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', $course_center_url ) ); ?>"><b>×²</b><strong>代数体系</strong><small>方程 · 不等式</small></a>
      <a href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', $course_center_url ) ); ?>"><b>△</b><strong>几何体系</strong><small>图形 · 证明</small></a>
      <a href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', $course_center_url ) ); ?>"><b>⌁</b><strong>函数体系</strong><small>一次 · 二次</small></a>
      <a href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', $course_center_url ) ); ?>"><b>▤</b><strong>教辅配套</strong><small>大培优 · 中考</small></a>
    </div>
  </div>
</section>

<section class="mc-home-system">
  <div class="mc-container">
    <div class="mc-home-section-title"><h2><span></span>课程体系分类</h2><p>可按「初中系统课」构建知识网络，或按「教辅配套课」进行专项强化提升</p></div>
    <div class="mc-home-category-tabs"><a class="is-active" href="<?php echo esc_url( $course_center_url ); ?>">▱ 初中系统课 <small>3大体系 · 26专题</small></a><a href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', $course_center_url ) ); ?>">▤ 教辅配套课 <small>大培优 · 中考在线</small></a></div>
    <div class="mc-home-category-grid">
      <a class="mc-home-category-card mc-home-category-card--green" href="<?php echo esc_url( add_query_arg( array( 'course_type' => 'topic', 'course_grade' => '7' ), $course_center_url ) ); ?>"><b>×²</b><div><h3>代数体系</h3><p>有理数 · 整式 · 一元一次方程 · 一元二次方程 · 因式分解 · 分式</p></div><em>共 10 个专题</em></a>
      <a class="mc-home-category-card mc-home-category-card--blue" href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', $course_center_url ) ); ?>"><b>△</b><div><h3>几何体系</h3><p>线段与角 · 三角形 · 全等三角形 · 相似三角形 · 几何证明</p></div><em>共 9 个专题</em></a>
      <a class="mc-home-category-card mc-home-category-card--purple" href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', $course_center_url ) ); ?>"><b>⌁</b><div><h3>函数体系</h3><p>一次函数 · 反比例函数 · 二次函数 · 函数与几何</p></div><em>共 7 个专题</em></a>
    </div>
  </div>
</section>

<section class="mc-home-why">
  <div class="mc-container">
    <div class="mc-home-centered-title"><h2>为什么选择樊老师数学课堂</h2><p>专注初中数学知识体系研发，让每一个知识点清晰可见</p></div>
    <div class="mc-home-benefits">
      <article><b>☆</b><h3>教学经验丰富</h3><p>多年初中数学一线教学经验，深谙中考命题规律与学生易错瓶颈。</p></article>
      <article><b>▦</b><h3>两大课程分类清晰</h3><p>系统专题课体系基础，教辅配套课紧扣练习与逐题精讲。</p></article>
      <article><b>▷</b><h3>讲解深入浅出</h3><p>黑板板书主动直观，注重辅助线作法与法通法总结，学会举一反三。</p></article>
      <article><b>♡</b><h3>持续更新迭代</h3><p>紧跟新课标与各地中考改革动态，定期更新补充最新真题与例题。</p></article>
    </div>
  </div>
</section>

<section class="mc-home-teacher"><div class="mc-container"><div class="mc-home-teacher__inner"><div class="mc-home-teacher__avatar">樊</div><div><h2>主讲名师介绍 · 执教理念</h2><p>樊老师深耕初中数学培优与中考命题研究多年，独创“模型化归纳与知识体系图谱”教学法。善于严谨推导演绎数学逻辑，帮助学生告别盲目刷题，将零碎考点串联为清晰的代数、几何与函数思维网络。</p><div class="mc-home-teacher__tags"><span><strong>两大分类体系</strong><small>初中系统课 + 教辅配套课</small></span><span><strong>体系化模型精讲</strong><small>推演逻辑 · 典型例题 · 解法规律</small></span></div></div></div></div></section>

<section class="mc-home-courses"><div class="mc-container"><div class="mc-section-heading mc-section-heading--courses"><div><h2>热门课程</h2><p>从一个专题开始，把知识真正学扎实。</p></div><a href="<?php echo esc_url( $course_center_url ); ?>">查看全部课程 <span>→</span></a></div><?php if ( shortcode_exists( 'mathcourse_course_directory' ) ) { echo do_shortcode( '[mathcourse_course_directory]' ); } elseif ( shortcode_exists( 'mathcourse_course_center' ) ) { echo do_shortcode( '[mathcourse_course_center]' ); } ?></div></section>
</main>
<?php get_footer(); ?>