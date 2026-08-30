<?php
/**
 * Math Course Theme - Learning Player Page.
 *
 * Theme controls layout only. Course, access, video and progress logic
 * are provided by the MathCourse plugin.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
$course_id = 0;
$lesson_title = '';
$access_error = '';

if ( $lesson_id && function_exists( 'tutor' ) ) {
    $lesson = get_post( $lesson_id );
    if ( $lesson && tutor()->lesson_post_type === $lesson->post_type ) {
        $topic = get_post( $lesson->post_parent );
        if ( $topic && 'topics' === $topic->post_type ) {
            $course_id = absint( $topic->post_parent );
            $lesson_title = get_the_title( $lesson_id );
        }
    }
}
if ( ! $lesson_id || ! $course_id ) $access_error = '课时不存在或链接无效。';
?>

<main class="mc-learning-page mc-learning-page--reference">
    <div class="mc-learning-container">
        <?php if ( $access_error ) : ?>
            <section class="mc-lesson-card mc-learning-error"><h1><?php echo esc_html( $access_error ); ?></h1><p><a href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">返回课程中心</a></p></section>
        <?php else : ?>
            <div class="mc-learning-breadcrumb">课程中心 <span>›</span> 学习课程 <span>›</span> <?php echo esc_html( $lesson_title ); ?></div>
            <div class="mc-learning-layout">
                <section class="mc-learning-main">
                    <div class="mc-video-card">
                        <div class="mc-video-box">
                            <?php if ( shortcode_exists( 'mathcourse_video' ) ) : echo do_shortcode( '[mathcourse_video lesson_id="' . esc_attr( $lesson_id ) . '" course_id="' . esc_attr( $course_id ) . '"]' ); else : ?><p>播放器暂不可用，请联系管理员。</p><?php endif; ?>
                        </div>
                    </div>
                    <section class="mc-lesson-card mc-learning-content">
                        <div class="mc-lesson-header"><div><span>当前课时</span><h1><?php echo esc_html( $lesson_title ); ?></h1></div><button type="button" class="mc-note-button">下载讲义</button></div>
                        <div class="mc-learning-divider"></div>
                        <h2>本节内容</h2>
                        <?php if ( get_the_content() ) : ?><div class="mc-lesson-description"><?php the_content(); ?></div><?php else : ?><p>本节主要学习 <?php echo esc_html( $lesson_title ); ?> 的核心概念、表示方法与典型应用。</p><?php endif; ?>
                    </section>
                </section>
                <aside class="mc-outline-card mc-learning-outline">
                    <div class="mc-outline-head"><strong>课程目录</strong><span>学习中</span></div>
                    <?php if ( shortcode_exists( 'mathcourse_course_learning' ) ) : ?>
                        <?php echo do_shortcode( '[mathcourse_course_learning course_id="' . esc_attr( $course_id ) . '"]' ); ?>
                    <?php endif; ?>
                    <div class="mc-learning-nav"><a href="#" class="is-disabled">← 上一课</a><span>课程学习</span><a href="#">下一课 →</a></div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
