<?php
/**
 * Webinar lesson card for learning area webinar page.
 *
 * @package TutorPro\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\PreviewTrigger;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;

// Optional props with defaults.
$show_live_tag     = isset( $show_live_tag ) ? $show_live_tag : true;
$event_tag_text    = isset( $event_tag_text ) ? $event_tag_text : __( 'Live Session', 'tutor-pro' );
$event_tag_icon    = isset( $event_tag_icon ) ? $event_tag_icon : Icon::ZOOM_COLORIZE;
$event_tag_variant = isset( $event_tag_variant ) ? $event_tag_variant : '';
$action_url        = isset( $action_url ) ? $action_url : '';
$action_text       = isset( $action_text ) ? $action_text : __( 'Open', 'tutor-pro' );

$badge_classes = array( 'tutor-event-badge' );

if ( ! empty( $event_tag_variant ) ) {
	$badge_classes[] = 'tutor-event-badge-' . $event_tag_variant;
}

$badge_classes = array_filter( array_map( 'sanitize_html_class', $badge_classes ) );

?>
<div class="tutor-upcoming-lesson-card">
	<div class="tutor-upcoming-lesson-card-header">
		<div class="tutor-upcoming-lesson-card-meta">
			<span class="tutor-upcoming-lesson-card-icon">
				<?php SvgIcon::make()->name( Icon::CALENDAR_2 )->size( 16 )->render(); ?>
			</span>
			<span class="tutor-upcoming-lesson-card-date"><?php echo esc_html( $date_text ); ?></span>
			<?php if ( ! empty( $time_text ) ) : ?>
				<span class="tutor-upcoming-lesson-card-separator">•</span>
				<span class="tutor-upcoming-lesson-card-time"><?php echo esc_html( $time_text ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $show_live_tag ) : ?>
			<div class="tutor-upcoming-lesson-card-live-tag">
				<div class="tutor-upcoming-lesson-card-live-tag-badge">
					<div class="<?php echo esc_attr( implode( ' ', $badge_classes ) ); ?>">
						<?php if ( ! empty( $event_tag_icon ) ) : ?>
							<span class="tutor-event-badge-icon">
								<?php SvgIcon::make()->name( $event_tag_icon )->size( 16 )->render(); ?>
							</span>
						<?php endif; ?>
						<span class="tutor-event-badge-text"><?php echo esc_html( $event_tag_text ); ?></span>
					</div>
				</div>
				<a class="tutor-btn tutor-btn-primary tutor-btn-x-small tutor-upcoming-lesson-card-action" href="<?php echo esc_url( $action_url ); ?>">
					<?php echo esc_html( $action_text ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $lesson_title ) ) : ?>
		<a href="<?php echo esc_url( $action_url ); ?>" class="tutor-upcoming-lesson-card-title"><?php echo esc_html( $lesson_title ); ?></a>
	<?php endif; ?>
	<?php if ( $course_id ) : ?>
	<div class="tutor-upcoming-lesson-card-course">
		<span class="tutor-upcoming-lesson-card-course-label">
			<?php echo esc_html__( 'Course:', 'tutor-pro' ); ?>
		</span>
		<?php PreviewTrigger::make()->id( $course_id )->render(); ?>
	</div>
	<?php endif; ?>
</div>

