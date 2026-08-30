<?php
/**
 * Lesson Note Item
 *
 * @package Tutor\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Tutor\Helpers\DateTimeHelper;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;
use TUTOR_PRO\LessonNotes;

$note_text  = $note->comment_content;
$note_short = substr( $note_text, 0, 90 );

$lines = explode( "\n", $note_short );
if ( count( $lines ) > 2 ) {
	$lines      = array_slice( $lines, 0, 2 );
	$note_short = rtrim( implode( "\n", $lines ) );
}

$video_start_time = null;
$video_end_time   = null;

if ( LessonNotes::has_video_time( $note->video_start_time ) ) {
	$video_start_time = tutor_utils()->playtime_string( $note->video_start_time );
}

if ( LessonNotes::has_video_time( $note->video_end_time ) ) {
	$video_end_time = tutor_utils()->playtime_string( $note->video_end_time );
}

$poster_url = tutor_pro()->url . '/assets/images/video-placeholder.svg';

$highlight_text = tutor_decode_unicode_sequences( $note->highlight_text );

?>
<div class="tutor-lesson-note-list-item-container">
	<div id="tutor-lesson-note-list-item-<?php echo esc_attr( $note->comment_ID ); ?>" class="tutor-lesson-note-list-item">
		<div class="tutor-lesson-note-list-item-time tutor-d-sm-none">
			<div class="tutor-d-flex tutor-align-center tutor-gap-1">
				<?php SvgIcon::make()->name( Icon::NOTEBOOK )->size( 16 )->render(); ?>
				<?php echo esc_html( tutor_i18n_get_formated_date( DateTimeHelper::get_gmt_to_user_timezone_date( $note->comment_date_gmt ) ) ); ?>
			</div>
			<div class="tutor-dropdown-parent">
				<button class="tutor-btn tutor-iconic-btn" action-tutor-dropdown="toggle">
					<?php SvgIcon::make()->name( Icon::ELLIPSES )->size( 20 )->render(); ?>
				</button>
				<div class="tutor-dropdown">
					<a class="tutor-dropdown-item tutor-edit-note-button">
						<?php SvgIcon::make()->name( Icon::EDIT )->render(); ?>
						<?php esc_html_e( 'Edit', 'tutor-pro' ); ?>
					</a>
					<a class="tutor-dropdown-item tutor-delete-note-button" data-note-id="<?php echo esc_attr( $note->comment_ID ); ?>" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
						<?php SvgIcon::make()->name( Icon::DELETE )->render(); ?>
						<?php esc_html_e( 'Delete', 'tutor-pro' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php if ( $video_start_time && $video_end_time ) : ?>
		<div class="tutor-lesson-note-list-item-left">
			<img src="<?php echo esc_url( $poster_url ); ?>" alt="<?php esc_html_e( 'Video poster', 'tutor-pro' ); ?>" />
			<button class="tutor-lesson-note-list-item-video-action tutor-btn" data-start-time="<?php echo esc_attr( $note->video_start_time ); ?>">
				<?php SvgIcon::make()->name( Icon::PLAY )->size( 20 )->render(); ?>
				<?php echo esc_html( $video_start_time . ' - ' . $video_end_time ); ?>
			</button>
		</div>
		<?php endif; ?>
		<div class="tutor-lesson-note-list-item-right">
			<div class="tutor-lesson-note-list-item-time tutor-d-none tutor-d-sm-flex">
				<div class="tutor-d-flex tutor-align-center tutor-gap-1">
					<?php SvgIcon::make()->name( Icon::NOTEBOOK )->size( 16 )->render(); ?>
					<?php echo esc_html( tutor_i18n_get_formated_date( DateTimeHelper::get_gmt_to_user_timezone_date( $note->comment_date_gmt ) ) ); ?>
				</div>
				<div class="tutor-lesson-note-list-item-actions">
					<button class="tutor-btn tutor-btn-tertiary tutor-delete-note-button" data-note-id="<?php echo esc_attr( $note->comment_ID ); ?>" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
						<?php SvgIcon::make()->name( Icon::DELETE )->size( 18 )->render(); ?>
					</button>
					<button class="tutor-btn tutor-btn-tertiary tutor-edit-note-button">
						<?php SvgIcon::make()->name( Icon::EDIT )->size( 18 )->render(); ?>
					</button>
				</div>
			</div>
			<?php if ( $note->highlight_text ) : ?>
			<div class="tutor-lesson-note-list-item-highlight" title="<?php echo esc_attr( $highlight_text ); ?>">
				<?php echo esc_html( $highlight_text ); ?>
			</div>
			<?php endif; ?>
			<div class="tutor-lesson-note-list-item-content">
				<?php if ( ! empty( $note_text ) ) : ?>
					<?php if ( $note_text !== $note_short ) : ?>
					<div class="tutor-lesson-note-content-short">
						<span><?php echo esc_html( $note_short ); ?>...</span>
						<button type="button" class="tutor-btn tutor-btn-link tutor-read-more-less">
							<?php esc_html_e( 'Read more', 'tutor-pro' ); ?>
						</button>
					</div>
					<div class="tutor-lesson-note-content-long tutor-d-none">
						<span><?php echo esc_html( $note_text ); ?></span>
						<button type="button" class="tutor-btn tutor-btn-link tutor-read-more-less">
							<?php esc_html_e( 'Read less', 'tutor-pro' ); ?>
						</button>
					</div>
					<?php else : ?>
						<span><?php echo esc_html( $note_text ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php
	tutor_load_template(
		'lesson-notes/form',
		array(
			'lesson_id' => $lesson_id,
			'note'      => $note,
		),
		true
	);
	?>
</div>
