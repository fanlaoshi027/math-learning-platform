<?php
/**
 * Lesson Notes List
 *
 * @package Tutor\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.9.0
 */

use TUTOR_PRO\LessonNotes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id        = get_current_user_id();
$items_per_page = tutor_utils()->get_option( 'pagination_per_page' );

$lesson_notes = new LessonNotes();
$note_list    = $lesson_notes->get_lesson_notes( $lesson_id, $user_id, 0, $items_per_page );
$note_count   = $lesson_notes->get_lesson_notes_count( $lesson_id, $user_id );

if ( empty( $note_list ) ) {
	return;
}

?>
<div class="tutor-lesson-note-list-title">
	<?php
	echo esc_html(
		sprintf(
			// translators: %d is the number of notes taken.
			_n( '%d Note taken', '%d Notes taken', $note_count, 'tutor-pro' ),
			$note_count
		)
	);
	?>
</div>

<div class="tutor-lesson-note-list-container">
	<div class="tutor-lesson-note-list">
		<?php foreach ( $note_list as $note ) : ?>
			<?php
			tutor_load_template(
				'lesson-notes/note-item',
				array(
					'lesson_id' => $lesson_id,
					'note'      => $note,
				),
				true
			);
			?>
		<?php endforeach; ?>
	</div>

	<?php if ( $note_count > $items_per_page ) : ?>
	<div class="tutor-text-center tutor-mt-24">
		<button 
			type="button"
			id="tutor-load-more-notes-button"
			class="tutor-btn tutor-btn-secondary tutor-btn-sm"
			data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
			data-items-per-page="<?php echo esc_attr( $items_per_page ); ?>"
		>
			<?php esc_html_e( 'Load More', 'tutor-pro' ); ?>
		</button>
	</div>	
	<?php endif; ?>
</div>
