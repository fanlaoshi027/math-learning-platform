<?php
/**
 * Lesson Notes Form
 *
 * @package Tutor\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.9.0
 */

use TUTOR\Icon;
use Tutor\Components\SvgIcon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit          = isset( $note ) && ! empty( $note );
$is_main_form     = isset( $main_form ) && $main_form;
$cancel_btn_class = '';
if ( $is_edit ) {
	$cancel_btn_class = 'tutor-cancel-edit-note-button';
} elseif ( $is_main_form ) {
	$cancel_btn_class = 'tutor-cancel-note-button';
}

?>
<form method="post" class="tutor-lesson-notes-form<?php echo esc_attr( $is_edit ? ' tutor-d-none' : '' ); ?>" <?php echo esc_attr( $is_main_form ? 'is-main-form' : '' ); ?>>
	<?php tutor_nonce_field(); ?>
	<input type="hidden" name="action" value="<?php echo $is_edit ? 'tutor_pro_update_lesson_note' : 'tutor_pro_save_lesson_note'; ?>" />
	<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson_id ); ?>" />
	<input type="hidden" name="note_id" value="<?php echo esc_attr( $is_edit ? $note->comment_ID : '' ); ?>" />
	<input type="hidden" name="highlight_text" value="" />
	<input type="hidden" name="highlight_serialized" value="" />
	<input type="hidden" name="video_start_time" value="" />

	<!-- Note: note_content is used to reset the edit form -->
	<?php if ( $is_edit ) : ?>
		<input type="hidden" name="note_content" value="<?php echo $is_edit ? esc_attr( $note->comment_content ) : ''; ?>" />
	<?php endif; ?>

	<textarea name="note_text" class="tutor-form-control" rows="5" placeholder="<?php esc_html_e( 'Type your note here to save for later', 'tutor-pro' ); ?>"><?php echo $is_edit ? esc_textarea( $note->comment_content ) : ''; ?></textarea>

	<div class="tutor-lesson-notes-form-buttons tutor-d-flex tutor-justify-end tutor-align-center tutor-mt-12 <?php echo esc_attr( $is_main_form ? 'tutor-d-none' : '' ); ?>">
		<div class="tutor-lesson-notes-form-key-hint tutor-d-none">
			<?php SvgIcon::make()->name( Icon::ENTER )->size( 14 )->render(); ?> 
			<?php esc_html_e( 'Press Ctrl/Cmd + Enter to Save', 'tutor-pro' ); ?>
		</div>
		<div class="tutor-d-flex tutor-gap-1">
			<button type="button" class="tutor-btn tutor-btn-sm tutor-color-subdued <?php echo esc_attr( $cancel_btn_class ); ?>" data-action-close>
				<?php esc_html_e( 'Cancel', 'tutor-pro' ); ?>
			</button>
			<button type="submit" class="tutor-btn tutor-btn-primary tutor-btn-sm">
				<?php esc_html_e( 'Save', 'tutor-pro' ); ?>
			</button>
		</div>
	</div>
</form>
