<?php
/**
 * Draw on Image (learning-area quiz attempt).
 *
 * Pro-only question UI. POST field names match draw-image-question.js and grading.
 * Reveal-mode reference uses the same CSS-mask tint pattern as Pin on Image.
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea\Quiz
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Models\QuizModel;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;

global $tutor_is_started_quiz;

$question_id = (int) ( $question['question_id'] ?? 0 );
if ( $question_id > 0 ) {
	$GLOBALS['tutor_learning_area_draw_image_rendered'][ $question_id ] = true;
}

$answers = isset( $question['question_answers'] ) && is_array( $question['question_answers'] ) ? $question['question_answers'] : array();
$answer  = ! empty( $answers ) ? reset( $answers ) : null;

if ( ! $answer ) {
	return;
}

$answer_obj = is_array( $answer ) ? (object) $answer : $answer;

$bg_image_url = QuizModel::get_answer_image_url( $answer_obj );

$question_type = 'draw_image';

$wrapper_id      = 'tutor-draw-image-question-' . $question_id;
$image_id        = 'tutor-draw-image-bg-' . $question_id;
$canvas_id       = 'tutor-draw-image-canvas-' . $question_id;
$hidden_input_id = 'tutor-draw-image-mask-' . $question_id;
$instruction_id  = 'tutor-draw-image-instruction-' . $question_id;
$status_id       = 'tutor-draw-image-status-' . $question_id;

$field_name          = ( $question_field_name_base ?? '' ) . '[answers][mask]';
$register_rules      = '';
$required_message_js = isset( $required_message ) ? (string) $required_message : __( 'The answer for this question is required', 'tutor-pro' );
if ( $answer_is_required ) {
	$register_rules = ", { required: '" . esc_js( $required_message_js ) . "' }";
}
$register_attr = "register('{$field_name}'{$register_rules})";

/**
 * Fires when the learning-area draw-image template is rendered; Tutor Pro
 * hooks this to enqueue draw-image-question.js.
 *
 * @since 4.0.0
 */
do_action( 'tutor_enqueue_draw_image_question_script' );
?>

<div
	id="<?php echo esc_attr( $wrapper_id ); ?>"
	class="quiz-question-ans-choice-area tutor-mt-40 tutor-draw-image-question question-type-<?php echo esc_attr( $question_type ); ?> <?php echo $answer_is_required ? esc_attr( 'quiz-answer-required' ) : ''; ?>"
	data-question-type="<?php echo esc_attr( $question_type ); ?>"
	data-question-id="<?php echo esc_attr( (string) $question_id ); ?>"
>
	<?php if ( $bg_image_url ) : ?>
		<div class="tutor-draw-image-actions tutor-mb-12">
			<button
				type="button"
				class="tutor-draw-image-clear-button tutor-hidden"
				aria-label="<?php esc_attr_e( 'Clear drawing', 'tutor-pro' ); ?>"
			>
				<?php SvgIcon::make()->name( Icon::ERASER )->size( 18 )->attr( 'class', 'tutor-draw-image-clear-button-icon' )->render(); ?>
				<?php esc_html_e( 'Clear', 'tutor-pro' ); ?>
			</button>
		</div>
		<div class="tutor-draw-image-wrapper">
			<img
				id="<?php echo esc_attr( $image_id ); ?>"
				src="<?php echo esc_url( $bg_image_url ); ?>"
				alt="<?php esc_attr_e( 'Draw on image question', 'tutor-pro' ); ?>"
			/>
			<canvas
				id="<?php echo esc_attr( $canvas_id ); ?>"
				class="tutor-draw-image-canvas tutor-quiz-interaction-focus-target"
				tabindex="0"
				role="application"
				aria-describedby="<?php echo esc_attr( $instruction_id . ' ' . $status_id ); ?>"
				aria-label="<?php esc_attr_e( 'Draw on image: use pointer or keyboard to mark your selection area.', 'tutor-pro' ); ?>"
			></canvas>
		</div>
		<p id="<?php echo esc_attr( $instruction_id ); ?>" class="tutor-quiz-a11y-sr-only">
			<?php esc_html_e( 'Use arrow keys to move the drawing pointer. Press Space or Enter to start a freehand stroke, trace with arrow keys, then press Space or Enter again to finish and fill the selection. Escape cancels an in-progress stroke. C clears the saved selection.', 'tutor-pro' ); ?>
		</p>
		<div
			id="<?php echo esc_attr( $status_id ); ?>"
			class="tutor-quiz-a11y-live-region tutor-quiz-a11y-sr-only"
			aria-live="polite"
			aria-atomic="true"
			role="status"
		></div>
	<?php else : ?>
		<p class="tutor-fs-7 tutor-color-secondary">
			<?php esc_html_e( 'No background image configured for this Image Marking question.', 'tutor-pro' ); ?>
		</p>
	<?php endif; ?>

	<input
		type="hidden"
		id="<?php echo esc_attr( $hidden_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		value=""
		x-bind="<?php echo esc_attr( $register_attr ); ?>"
	/>
</div>

<div
	class="tutor-quiz-questions-error"
	x-cloak
	x-show="errors?.['<?php echo esc_attr( $field_name ); ?>']?.message"
	x-text="errors?.['<?php echo esc_attr( $field_name ); ?>']?.message"
></div>
