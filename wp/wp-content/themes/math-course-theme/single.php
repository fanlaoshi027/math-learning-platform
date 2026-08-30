<?php
/**
 * Math Course Theme - single fallback.
 * Tutor lessons are rendered through the MathCourse player instead of Tutor's
 * native lesson template.
 */
defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) : the_post();
	$post_id = get_the_ID();
	$is_lesson = function_exists( 'tutor' ) && tutor()->lesson_post_type === get_post_type( $post_id );
	?>
	<main class="mc-page mc-single">
		<section class="mc-container">
			<?php if ( $is_lesson ) : ?>
				<?php
				$service = new \MathCourse\Course\Course_Service();
				$video   = $service->get_lesson_video( $post_id, get_current_user_id() );
				$course_id = ! empty( $video['course_id'] ) ? absint( $video['course_id'] ) : 0;
				?>
				<article class="mc-lesson-page" data-lesson-id="<?php echo esc_attr( $post_id ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>">
					<a class="mc-back-link" href="<?php echo esc_url( $course_id ? get_permalink( $course_id ) : home_url( '/' ) ); ?>">← 返回课程</a>
					<header class="mc-lesson-header">
						<h1><?php the_title(); ?></h1>
						<?php if ( $video['preview'] && empty( $video['accessible'] ) ) : ?><span class="mc-lesson-badge">试看</span><?php endif; ?>
					</header>
					<?php if ( ! empty( $video['accessible'] ) && ! empty( $video['hls_url'] ) ) : ?>
						<div class="mc-lesson-player">
							<?php echo do_shortcode( '[mathcourse_video lesson_id="' . absint( $post_id ) . '" course_id="' . absint( $course_id ) . '"]' ); ?>
						</div>
						<?php if ( get_the_content() ) : ?><div class="mc-lesson-description"><?php the_content(); ?></div><?php endif; ?>
					<?php else : ?>
						<div class="mc-lesson-locked">
							<strong>本课时需要课程授权</strong>
							<p>你可以返回课程目录继续试看其他开放课时；获得课程授权后即可观看本课时。</p>
							<a class="mc-primary-button" href="<?php echo esc_url( $course_id ? get_permalink( $course_id ) : home_url( '/' ) ); ?>">返回课程</a>
						</div>
					<?php endif; ?>
				</article>
			<?php else : ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<h1><?php the_title(); ?></h1>
					<div><?php the_content(); ?></div>
				</article>
			<?php endif; ?>
		</section>
	</main>
	<?php
endwhile;

get_footer();
