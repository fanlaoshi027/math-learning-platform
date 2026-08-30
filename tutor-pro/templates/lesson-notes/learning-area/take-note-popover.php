<?php
/**
 * Floating note form template
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\Size;
use Tutor\Components\Constants\Variant;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;
?>
<div class="tutor-lesson-note-form-wrapper tutor-d-none">
	<div class="tutor-flex tutor-justify-between tutor-items-center tutor-px-6 tutor-py-5">
		<div class="tutor-lesson-note-form-title-wrapper">
			<?php SvgIcon::make()->name( Icon::QUILL )->size( 20 )->render(); ?>
			<div class="tutor-lesson-note-form-title">
				<?php esc_html_e( 'Take Note', 'tutor-pro' ); ?>
			</div>
		</div>
		<div class="tutor-flex tutor-items-center tutor-gap-2">
			<span class="tutor-lesson-note-start-time tutor-d-none">00:00 -</span>
			<?php
			Button::make()
				->icon( Icon::CROSS_2 )
				->variant( Variant::GHOST )
				->size( Size::X_SMALL )
				->attr( 'data-action-close', '' )
				->render();
			?>
		</div>
	</div>
	<div class="tutor-px-6 tutor-pb-5">
		<form method="post" class="tutor-lesson-notes-form">
			<?php tutor_nonce_field(); ?>
			<input type="hidden" name="action" value="tutor_pro_save_lesson_note" />
			<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson_id ); ?>" />
			<input type="hidden" name="note_id" value="" />
			<input type="hidden" name="highlight_text" value="" />
			<input type="hidden" name="highlight_serialized" value="" />
			<input type="hidden" name="video_start_time" value="" />

			<div class="tutor-input-field tutor-mb-4">
				<textarea
					name="note_text"
					class="tutor-input tutor-text-area"
					rows="5"
					placeholder="<?php esc_html_e( 'Type your note here to save for later', 'tutor-pro' ); ?>"></textarea>
			</div>

			<div class="tutor-lesson-notes-form-buttons tutor-flex tutor-justify-end tutor-items-center tutor-mt-5">
				<div class="tutor-lesson-notes-form-key-hint tutor-d-none tutor-tiny tutor-text-subdued tutor-flex tutor-items-center tutor-gap-2 tutor-sm-hidden">
					<?php SvgIcon::make()->name( Icon::COMMAND )->size( 12 )->render(); ?>
					<?php esc_html_e( 'Cmd/Ctrl +', 'tutor-pro' ); ?>
					<?php SvgIcon::make()->name( Icon::ENTER )->size( 12 )->render(); ?>
					<?php esc_html_e( 'Enter to Save', 'tutor-pro' ); ?>
				</div>
				<div class="tutor-flex tutor-gap-4 tutor-sm-justify-between tutor-sm-w-full">
					<?php
					Button::make()
						->label( __( 'Cancel', 'tutor-pro' ) )
						->variant( Variant::GHOST )
						->size( Size::X_SMALL )
						->attr( 'type', 'button' )
						->attr( 'data-action-close', '' )
						->render();

					Button::make()
						->label( __( 'Save Note', 'tutor-pro' ) )
						->variant( Variant::PRIMARY )
						->size( Size::X_SMALL )
						->attr( 'type', 'submit' )
						->render();
					?>
				</div>
			</div>
		</form>
	</div>
</div>
