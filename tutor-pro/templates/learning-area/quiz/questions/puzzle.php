<?php
/**
 * Puzzle question (learning-area quiz attempt).
 *
 * Pro-only UI. Students solve a dynamic jigsaw puzzle generated from one image.
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea\Quiz\Questions
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Models\QuizModel;
use TUTOR_PRO\QuizImageStorage;

if ( ! isset( $question ) || ! is_array( $question ) ) {
	return;
}

$question_id = (int) ( $question['question_id'] ?? 0 );
if ( $question_id <= 0 ) {
	return;
}

if ( ! isset( $GLOBALS['tutor_learning_area_puzzle_rendered'] ) || ! is_array( $GLOBALS['tutor_learning_area_puzzle_rendered'] ) ) {
	$GLOBALS['tutor_learning_area_puzzle_rendered'] = array();
}
$GLOBALS['tutor_learning_area_puzzle_rendered'][ $question_id ] = true;

do_action( 'tutor_enqueue_puzzle_question_script' );

$answers = isset( $question['question_answers'] ) && is_array( $question['question_answers'] ) ? $question['question_answers'] : array();
$answer  = ! empty( $answers ) ? reset( $answers ) : null;

if ( empty( $answer ) ) {
	return;
}

if ( ! is_array( $answer ) ) {
	$answer = (array) $answer;
}

$bg_image_url = '';
if ( isset( $answer['image_id'] ) ) {
	$bg_image_url = QuizModel::get_answer_image_url( (object) $answer );
}

if ( ! $bg_image_url && ! empty( $answer['answer_two_gap_match'] ) ) {
	$bg_image_url = QuizImageStorage::quiz_image_stored_value_to_url( (string) $answer['answer_two_gap_match'] );
}

$question_type      = (string) ( $question['question_type'] ?? 'puzzle' );
$question_settings  = isset( $question['question_settings'] ) && is_array( $question['question_settings'] ) ? $question['question_settings'] : array();
$answer_is_required = isset( $question_settings['answer_required'] ) && '1' === (string) $question_settings['answer_required'];
$grid_size          = isset( $question_settings['puzzle_grid_size'] ) ? (int) $question_settings['puzzle_grid_size'] : 4;
$grid_size          = max( 2, min( 7, $grid_size ) );

$wrapper_id       = 'tutor-puzzle-question-' . $question_id;
$hidden_input     = 'tutor-puzzle-state-' . $question_id;
$instruction_id   = 'tutor-puzzle-instruction-' . $question_id;
$status_id        = 'tutor-puzzle-status-' . $question_id;
$playground_desc  = $instruction_id . ' ' . $status_id;
$scatter_desc     = $instruction_id . ' ' . $status_id;
$field_name    = sprintf( '%s[answers][puzzle][value]', $question_field_name_base ?? '' );
$register_rule = '';
if ( $answer_is_required ) {
	$register_rule = ", { required: '" . esc_js( $required_message ) . "' }";
}
$register_attr = "register('{$field_name}'{$register_rule})";
$puzzle_token  = isset( $puzzle_session_token ) ? (string) $puzzle_session_token : '';
?>

<div
	id="<?php echo esc_attr( $wrapper_id ); ?>"
	class="quiz-question-ans-choice-area tutor-mt-40 tutor-puzzle-question question-type-<?php echo esc_attr( $question_type ); ?> <?php echo esc_attr( $answer_is_required ? 'quiz-answer-required' : '' ); ?>"
	data-question-type="<?php echo esc_attr( $question_type ); ?>"
	data-question-id="<?php echo esc_attr( (string) $question_id ); ?>"
	data-grid-size="<?php echo esc_attr( (string) $grid_size ); ?>"
	data-image-url="<?php echo esc_url( $bg_image_url ); ?>"
	data-puzzle-token="<?php echo esc_attr( $puzzle_token ); ?>"
>
	<?php if ( $bg_image_url ) : ?>
		<div
			class="tutor-puzzle-playground tutor-quiz-interaction-focus-target"
			tabindex="0"
			role="application"
			aria-describedby="<?php echo esc_attr( $playground_desc ); ?>"
			aria-label="<?php esc_attr_e( 'Puzzle board: use arrow keys to choose a slot, then press Enter to place the selected piece.', 'tutor-pro' ); ?>"
		>
			<img
				class="tutor-puzzle-reference-image"
				src="<?php echo esc_url( $bg_image_url ); ?>"
				alt="<?php esc_attr_e( 'Puzzle reference image', 'tutor-pro' ); ?>"
				style="opacity: 0.3;"
			/>
			<div class="tutor-puzzle-slots" aria-hidden="true"></div>
		</div>
		<div
			class="tutor-puzzle-scatter tutor-quiz-interaction-focus-target"
			tabindex="0"
			role="listbox"
			aria-describedby="<?php echo esc_attr( $scatter_desc ); ?>"
			aria-label="<?php esc_attr_e( 'Puzzle pieces: use arrow keys to choose a piece, then Tab to the board and press Enter to place it.', 'tutor-pro' ); ?>"
		></div>
		<p id="<?php echo esc_attr( $instruction_id ); ?>" class="tutor-quiz-a11y-sr-only">
			<?php esc_html_e( 'In the piece pool, use arrow keys to select a piece. Tab to the puzzle board, use arrow keys to select a slot, and press Enter to place the piece at that slot. Pieces snap only when correct; wrong placements stay on the board like drag-and-drop. Press Backspace or Delete on the board to return a piece to the pool. Progress counts locked pieces only.', 'tutor-pro' ); ?>
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
			<?php esc_html_e( 'No source image configured for this Puzzle question.', 'tutor-pro' ); ?>
		</p>
	<?php endif; ?>

	<input
		type="hidden"
		id="<?php echo esc_attr( $hidden_input ); ?>"
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
