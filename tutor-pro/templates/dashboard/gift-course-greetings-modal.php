<?php
/**
 * Gift course greetings modal content template
 *
 * @package TutorPro\Templates
 * @subpackage Dashboard
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\Variant;
use Tutor\Components\StarRating;

if ( ! isset( $data ) ) {
	return;
}
?>
<div class="tutor-greetings-modal">
	<div class="tutor-greetings-card-wrapper">
		<div class="tutor-greetings-card">
			<span class="tutor-greetings-circle tutor-greetings-circle-top-left"></span>
			<span class="tutor-greetings-circle tutor-greetings-circle-top-right"></span>
			<span class="tutor-greetings-circle tutor-greetings-circle-bottom-left"></span>
			<span class="tutor-greetings-circle tutor-greetings-circle-bottom-right"></span>

			<div class="tutor-medium tutor-mb-8">
				<?php esc_html_e( 'Hey', 'tutor-pro' ); ?> <span class="tutor-font-semibold"><?php echo esc_html( $data['recipient_name'] ); ?> </span>!
			</div>
			<div class="tutor-greetings-content">
				<img class="tutor-greetings-ribbon" src="<?php echo esc_attr( tutor_pro()->url . 'assets/images/gift-ribbon.svg' ); ?>" alt="<?php esc_html_e( 'Gix ribbon', 'tutor-pro' ); ?>" />
				<div class="tutor-p1 tutor-text-justify">
					<?php echo esc_html( $data['message'] ); ?>
				</div>
				<div class="tutor-small tutor-mt-6 tutor-text-right">
					<?php echo esc_html( "- {$data['purchaser_name']}" ); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="tutor-gifted-course-wrapper">
		<div class="tutor-gifted-course">
			<h5 class="tutor-h5 tutor-mb-5">
				<?php esc_html_e( 'Gifted Course', 'tutor-pro' ); ?>
			</h5>
			<div class="tutor-card tutor-card-rounded-2xl tutor-gifted-course-card">
				<div class="tutor-gifted-course-image tutor-flex-center tutor-w-s">
					<img src="<?php echo esc_url( get_tutor_course_thumbnail_src( 'post-thumbnail', $data['course']->ID ) ); ?>" alt="<?php echo esc_attr( $data['course']->post_title ); ?>" />
				</div>
				<div class="tutor-gifted-course-content">
					<a href="<?php echo esc_url( $data['learning_url'] ); ?>" class="tutor-gifted-course-name">
						<?php echo esc_html( $data['course']->post_title ); ?>
					</a>

					<div class="tutor-gifted-course-author">
						<?php esc_html_e( 'By', 'tutor-pro' ); ?> <a href="<?php echo esc_attr( tutor_utils()->profile_url( $data['course_author']->ID, true ) ); ?>"><?php echo esc_html( tutor_utils()->display_name( $data['course_author']->ID ) ); ?></a>
					</div>

					<?php if ( $data['course_rating'] ) : ?>
						<div class="tutor-flex tutor-items-center tutor-gap-2 tutor-mt-6">
							<div class="tutor-tiny tutor-font-semibold">
								<?php echo esc_html( number_format( $data['course_rating']->rating_avg, 2 ) ); ?>
							</div>
							<?php StarRating::make()->rating( $data['course_rating']->rating_avg )->render(); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
				Button::make()
					->label( __( 'Start Learning', 'tutor-pro' ) )
					->variant( Variant::PRIMARY )
					->tag( 'a' )
					->attr( 'href', $data['learning_url'] )
					->block()
					->render();
			?>
		</div>
	</div>
</div>
