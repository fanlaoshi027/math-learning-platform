<?php
/**
 * Template for showing  upcoming live lessons.
 *
 * @package TutorPro\Templates
 * @subpackage Dashboard
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

use Tutor\Components\PreviewTrigger;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;

defined( 'ABSPATH' ) || exit;

$is_calendar_enabled = tutor_utils()->is_addon_enabled( 'calendar' );

?>
<div class="tutor-mt-8">
	<div class="tutor-flex tutor-items-center tutor-justify-between tutor-mb-4">
		<div class="tutor-small tutor-font-medium">
			<?php esc_html_e( 'Upcoming Live Classes', 'tutor-pro' ); ?>
		</div>
		<?php if ( $is_calendar_enabled ) : ?>
		<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'calendar' ) ); ?>" class="tutor-btn tutor-btn-link tutor-btn-x-small tutor-text-brand tutor-p-none tutor-min-h-0">
			<?php esc_html_e( 'See All', 'tutor-pro' ); ?>
		</a>
		<?php endif; ?>
	</div>

	<?php if ( tutor_utils()->count( $upcoming_meetings ) ) : ?>
		<div class="tutor-upcoming-lessons tutor-grid tutor-grid-cols-2 tutor-sm-grid-cols-1 tutor-gap-4">
			<?php foreach ( $upcoming_meetings as $meeting ) : ?>
				<?php
				if ( empty( $meeting['start_at'] ) ) {
					continue;
				}

				$timestamp = strtotime( $meeting['start_at'] );
				if ( ! $timestamp ) {
					continue;
				}

				$date_label = date_i18n( 'M j', $timestamp );
				$time_label = date_i18n( get_option( 'time_format' ), $timestamp );

				$badge_text  = __( 'Live Session', 'tutor-pro' );
				$meeting_url = $meeting['url'] ?? '';
				?>
				<div class="tutor-upcoming-lesson-card">
					<div class="tutor-upcoming-lesson-card-header">
						<div
							class="tutor-upcoming-lesson-card-meta tutor-utc-datetime"
							data-utc-datetime="<?php echo esc_attr( $meeting['start_at'] ); ?>"
						>
							<span class="tutor-upcoming-lesson-card-icon">
								<?php SvgIcon::make()->name( Icon::CALENDAR_2 )->render(); ?>
							</span>
							<span class="tutor-upcoming-lesson-card-date" data-utc-datetime-date>
								<?php echo esc_html( $date_label ); ?>
							</span>
							<span class="tutor-upcoming-lesson-card-separator">•</span>
							<span class="tutor-upcoming-lesson-card-time" data-utc-datetime-time>
								<?php echo esc_html( $time_label ); ?>
							</span>
						</div>
						<div class="tutor-upcoming-lesson-card-live-tag">
							<div class="tutor-upcoming-lesson-card-live-tag-badge">
								<div class="tutor-event-badge">
									<span class="tutor-event-badge-icon">
										<?php
										if ( 'zoom' === $meeting['source'] ) {
											SvgIcon::make()->name( Icon::ZOOM_COLORIZE )->render();
										} else {
											SvgIcon::make()->name( Icon::GOOGLE_MEET_COLORIZE )->render();
										}
										?>
									</span>
									<span class="tutor-event-badge-text">
										<?php echo esc_html( $badge_text ); ?>
									</span>
								</div>
							</div>
							<?php if ( $meeting_url ) : ?>
								<a class="tutor-btn tutor-btn-primary tutor-btn-x-small tutor-upcoming-lesson-card-action" href="<?php echo esc_url( $meeting_url ); ?>">
									<?php esc_html_e( 'Open', 'tutor-pro' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
					<a tabindex="-1" href="<?php echo esc_url( $meeting_url ); ?>" class="tutor-upcoming-lesson-card-title">
						<?php echo esc_html( $meeting['title'] ); ?>
					</a>
					<?php if ( isset( $meeting['course_id'] ) ) : ?>
					<div class="tutor-upcoming-lesson-card-course">
						<span class="tutor-upcoming-lesson-card-course-label">
							<?php echo esc_html__( 'Course:', 'tutor-pro' ); ?>
						</span>
						<?php PreviewTrigger::make()->id( $meeting['course_id'] )->render(); ?>
					</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="tutor-color-muted">
			<?php esc_html_e( 'No upcoming live lessons available.', 'tutor-pro' ); ?>
		</p>
	<?php endif; ?>
</div>
