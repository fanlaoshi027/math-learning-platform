<?php
/**
 * Pin on Image (learning-area quiz attempt).
 *
 * Pro-only question UI. Field names match pin-image-question.js and grading.
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea\Quiz
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Models\QuizModel;

if ( ! isset( $question ) || ! is_array( $question ) ) {
	return;
}

$question_id = (int) ( $question['question_id'] ?? 0 );
if ( $question_id <= 0 ) {
	return;
}

$answers = isset( $question['question_answers'] ) && is_array( $question['question_answers'] ) ? $question['question_answers'] : array();
$answer  = ! empty( $answers ) ? reset( $answers ) : null;

if ( empty( $answer ) ) {
	return;
}

// get_answers_by_quiz_question() returns row objects; core may pass arrays — match draw-image.php handling.
if ( ! is_array( $answer ) ) {
	$answer = (array) $answer;
}

if ( ! isset( $GLOBALS['tutor_learning_area_pin_image_rendered'] ) || ! is_array( $GLOBALS['tutor_learning_area_pin_image_rendered'] ) ) {
	$GLOBALS['tutor_learning_area_pin_image_rendered'] = array();
}
$GLOBALS['tutor_learning_area_pin_image_rendered'][ $question_id ] = true;

do_action( 'tutor_enqueue_pin_image_question_script' );

$bg_image_url = '';
if ( isset( $answer['image_id'] ) ) {
	$bg_image_url = QuizModel::get_answer_image_url( (object) $answer );
}

$question_type      = (string) ( $question['question_type'] ?? 'pin_image' );
$question_settings  = isset( $question['question_settings'] ) && is_array( $question['question_settings'] ) ? $question['question_settings'] : array();
$answer_is_required = isset( $question_settings['answer_required'] ) && '1' === (string) $question_settings['answer_required'];

$wrapper_id     = 'tutor-pin-image-question-' . $question_id;
$image_id       = 'tutor-pin-image-bg-' . $question_id;
$pin_x_input_id = 'tutor-pin-image-x-' . $question_id;
$pin_y_input_id = 'tutor-pin-image-y-' . $question_id;
$instruction_id = 'tutor-pin-image-instruction-' . $question_id;
$status_id      = 'tutor-pin-image-status-' . $question_id;

$pin_x_field_name = sprintf( '%s[answers][pin][x]', $question_field_name_base ?? '' );
$pin_y_field_name = sprintf( '%s[answers][pin][y]', $question_field_name_base ?? '' );
$register_rules   = '';
if ( $answer_is_required ) {
	$register_rules = ", { required: '" . esc_js( $required_message ) . "' }";
}
$pin_x_register_attr = "register('{$pin_x_field_name}'{$register_rules})";
$pin_y_register_attr = "register('{$pin_y_field_name}'{$register_rules})";
?>

<div
	id="<?php echo esc_attr( $wrapper_id ); ?>"
	class="quiz-question-ans-choice-area tutor-mt-40 tutor-pin-image-question question-type-<?php echo esc_attr( $question_type ); ?> <?php echo esc_attr( $answer_is_required ? 'quiz-answer-required' : '' ); ?>"
	data-question-type="<?php echo esc_attr( $question_type ); ?>"
	data-question-id="<?php echo esc_attr( (string) $question_id ); ?>"
>
	<?php if ( $bg_image_url ) : ?>
		<div
			class="tutor-pin-image-wrapper"
			tabindex="0"
			role="application"
			aria-describedby="<?php echo esc_attr( $instruction_id . ' ' . $status_id ); ?>"
			aria-label="<?php esc_attr_e( 'Pin on image: click or use keyboard to place and move your pin.', 'tutor-pro' ); ?>"
		>
			<img
				id="<?php echo esc_attr( $image_id ); ?>"
				src="<?php echo esc_url( $bg_image_url ); ?>"
				alt="<?php esc_attr_e( 'Pin on image question', 'tutor-pro' ); ?>"
			/>
			<span class="tutor-pin-image-marker" aria-hidden="true"></span>
		</div>
		<p id="<?php echo esc_attr( $instruction_id ); ?>" class="tutor-quiz-a11y-sr-only">
			<?php esc_html_e( 'Press Enter to place the pin. Use arrow keys to nudge the position, Shift with arrow keys for larger steps, and C to clear the pin.', 'tutor-pro' ); ?>
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
			<?php esc_html_e( 'No background image configured for this Pin question.', 'tutor-pro' ); ?>
		</p>
	<?php endif; ?>

	<input
		type="hidden"
		id="<?php echo esc_attr( $pin_x_input_id ); ?>"
		name="<?php echo esc_attr( $pin_x_field_name ); ?>"
		value=""
		x-bind="<?php echo esc_attr( $pin_x_register_attr ); ?>"
	/>
	<input
		type="hidden"
		id="<?php echo esc_attr( $pin_y_input_id ); ?>"
		name="<?php echo esc_attr( $pin_y_field_name ); ?>"
		value=""
		x-bind="<?php echo esc_attr( $pin_y_register_attr ); ?>"
	/>
</div>
