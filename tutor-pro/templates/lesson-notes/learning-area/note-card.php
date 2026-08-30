<?php
/**
 * Lesson Note Item for Dashboard
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Helpers\DateTimeHelper;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;
use Tutor\Components\Constants\Color;
use TUTOR_PRO\LessonNotes;

$lesson_id = $note->comment_post_ID;
$course_id = tutor_utils()->get_course_id_by_lesson( $lesson_id );

$video_start_time = null;
if ( LessonNotes::has_video_time( $note->video_start_time ) ) {
	$video_start_time = tutor_utils()->playtime_string( $note->video_start_time );
}
$poster_url = tutor_pro()->url . 'assets/images/video-placeholder.svg';
?>
<div class="tutor-lesson-note" id="tutor-lesson-note-<?php echo esc_attr( $note->comment_ID ); ?>" style="min-height: 100px;">
	<div 
		class="tutor-lesson-note-view"
		x-show="editingId !== <?php echo esc_attr( $note->comment_ID ); ?>" 
		x-cloak
	>
		<div class="tutor-flex tutor-justify-between">
			<div class="tutor-flex tutor-gap-3 tutor-items-center tutor-tiny tutor-text-secondary">
				<?php SvgIcon::make()->name( Icon::NOTEBOOK )->size( 16 )->render(); ?>
				<?php echo esc_html( tutor_i18n_get_formated_date( DateTimeHelper::get_gmt_to_user_timezone_date( $note->comment_date_gmt ) ) ); ?>
			</div>
			<div class="tutor-lesson-note-actions">
				<div class="tutor-flex tutor-items-center tutor-gap-3 tutor-sm-hidden">
					<button 
						type="button" 
						class="tutor-btn tutor-btn-secondary tutor-btn-x-small tutor-btn-icon"
						@click="handleEditNote(<?php echo esc_attr( $note->comment_ID ); ?>, '<?php echo esc_js( $note->comment_content ); ?>')"
					>
						<?php SvgIcon::make()->name( Icon::EDIT_2 )->render(); ?>
					</button>
					<button 
						type="button" 
						class="tutor-btn tutor-btn-secondary tutor-btn-x-small tutor-btn-icon"
						@click="handleDeleteNote(<?php echo esc_attr( $note->comment_ID ); ?>, <?php echo esc_attr( $lesson_id ); ?>)"
					>
						<?php SvgIcon::make()->name( Icon::DELETE_2 )->render(); ?>
					</button>
				</div>
				<!-- Mobile Popover -->
				<div x-data="tutorPopover({ placement: 'bottom-end' })" class="tutor-hidden tutor-sm-block">
					<button x-ref="trigger" @click="toggle()" class="tutor-btn tutor-btn-text tutor-btn-x-small tutor-btn-icon">
						<?php SvgIcon::make()->name( Icon::ELLIPSES )->size( 16 )->color( Color::SECONDARY )->render(); ?>
					</button>
					<div x-ref="content" x-show="open" x-cloak @click.outside="handleClickOutside()" class="tutor-popover">
						<div class="tutor-popover-menu" style="min-width: 104px;">
							<button class="tutor-popover-menu-item" @click="handleEditNote(<?php echo esc_attr( $note->comment_ID ); ?>, '<?php echo esc_js( $note->comment_content ); ?>'); hide()">
								<?php SvgIcon::make()->name( Icon::EDIT_2 )->render(); ?>
								<?php esc_html_e( 'Edit', 'tutor-pro' ); ?>
							</button>
							<button class="tutor-popover-menu-item" @click="handleDeleteNote(<?php echo esc_attr( $note->comment_ID ); ?>, <?php echo esc_attr( $lesson_id ); ?>); hide()">
								<?php SvgIcon::make()->name( Icon::DELETE_2 )->render(); ?>
								<?php esc_html_e( 'Delete', 'tutor-pro' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="tutor-flex tutor-sm-flex-column tutor-gap-5 tutor-sm-gap-4 tutor-mt-5">
			<?php if ( ! empty( $video_start_time ) ) : ?>
			<div class="tutor-lesson-note-thumb">
				<img src="<?php echo esc_url( $poster_url ); ?>" alt="<?php esc_html_e( 'Video poster', 'tutor-pro' ); ?>" />
				<button 
					class="tutor-lesson-note-play-button"
					data-start-time="<?php echo esc_attr( $note->video_start_time ); ?>"
					@click="handlePlayVideoClip($event)"
				>
					<span class="tutor-lesson-note-play-icon">
						<?php SvgIcon::make()->name( Icon::PLAY_2 )->size( 12 )->render(); ?>
					</span>
					<?php
					// translators: %s: video start time.
					echo esc_html( sprintf( __( 'Play Video Clip %s', 'tutor-pro' ), $video_start_time ) );
					?>
				</button>
			</div>
			<?php endif; ?>

			<div class="tutor-flex tutor-flex-column tutor-justify-between tutor-gap-4">
				<?php if ( $note->highlight_text ) : ?>
				<div class="tutor-p3 tutor-sm-p3 tutor-text-subdued">
					<?php echo wp_kses_post( $note->highlight_text ); ?>
				</div>
				<?php endif; ?>
				<?php $read_more_lines = ! empty( $video_start_time ) ? 4 : 2; ?>
				<div x-data="tutorReadMore({ lines: <?php echo esc_attr( $read_more_lines ); ?> })">
					<div class="tutor-p1 tutor-sm-p2 tutor-font-medium" id="tutor-note-content-<?php echo esc_attr( $note->comment_ID ); ?>" x-ref="content" style="display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: <?php echo esc_attr( $read_more_lines ); ?>; overflow: hidden;">
						<?php echo wp_kses_post( $note->comment_content ); ?>
					</div>
					<button
						type="button"
						class="tutor-btn tutor-btn-link tutor-p-0 tutor-font-medium"
						x-ref="readMore"
						x-cloak
						x-show="hasOverflow && ! expanded"
						@click="toggle()"
						:aria-expanded="expanded.toString()"
					>
						<?php
						/* translators: ellipsis followed by Read more */
						esc_html_e( '… Read more', 'tutor-pro' );
						?>
					</button>
					<button
						type="button"
						class="tutor-btn tutor-btn-link tutor-p-0 tutor-font-medium"
						x-cloak
						x-show="expanded"
						@click="toggle()"
						:aria-expanded="expanded.toString()"
					>
						<?php esc_html_e( 'Read less', 'tutor-pro' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<?php
	tutor_load_template(
		'lesson-notes.learning-area.note-form',
		array(
			'id'             => 'tutor-edit-lesson-note-form-' . $note->comment_ID,
			'submit_handler' => '(data) => updateMutation?.mutate({ ...data, note_id: ' . esc_js( $note->comment_ID ) . ' })',
			'cancel_handler' => 'editingId = null',
			'is_pending'     => 'updateMutation?.isPending',
			'default_value'  => $note->comment_content,
			'show_binding'   => 'editingId === ' . $note->comment_ID,
		),
		true
	);
	?>
</div>
