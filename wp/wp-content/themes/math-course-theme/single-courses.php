<?php
/**
 * Math Course Theme - Single Course Learning Page
 */
get_header();

while ( have_posts() ) : the_post();
$course_id = get_the_ID();
?>
<main class="mc-single-course">
<section class="mc-course-header">
<div class="mc-course-cover">
<?php if ( has_post_thumbnail() ) { the_post_thumbnail('large'); } ?>
</div>
<div class="mc-course-info">
<h1><?php the_title(); ?></h1>
<div class="mc-course-desc"><?php the_excerpt(); ?></div>
<a class="mc-primary-button" href="#course-outline">进入学习</a>
</div>
</section>
<section id="course-outline" class="mc-course-outline">
<h2>课程目录</h2>
<?php
if ( function_exists('tutor_utils') ) {
$topics = tutor_utils()->get_course_topics($course_id);
if ( $topics ) {
foreach ( $topics as $topic ) {
?>
<div class="mc-topic-card">
<h3><?php echo esc_html($topic->post_title); ?></h3>
<?php
$lessons = tutor_utils()->get_course_contents_by_topic($topic->ID);
foreach ( $lessons as $lesson ) {
?>
<div class="mc-lesson-item">
<a href="<?php echo esc_url(get_permalink($lesson->ID)); ?>"><?php echo esc_html($lesson->post_title); ?></a>
</div>
<?php }
?>
</div>
<?php }
}
}
?>
</section>
</main>
<?php endwhile; get_footer();
