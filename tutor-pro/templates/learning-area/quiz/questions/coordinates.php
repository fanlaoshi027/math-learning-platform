<?php
/**
 * Coordinates question (learning-area quiz attempt).
 *
 * Pro-only UI: integer grid (-10…10). Field names match coordinates-question.js.
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea\Quiz\Questions
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use TUTOR\Icon;
use Tutor\Components\SvgIcon;

$question_id = (int) ( $question['question_id'] ?? 0 );
if ( $question_id > 0 ) {
	if ( ! isset( $GLOBALS['tutor_learning_area_coordinates_rendered'] ) || ! is_array( $GLOBALS['tutor_learning_area_coordinates_rendered'] ) ) {
		$GLOBALS['tutor_learning_area_coordinates_rendered'] = array();
	}
	$GLOBALS['tutor_learning_area_coordinates_rendered'][ $question_id ] = true;
}

$answers = isset( $question['question_answers'] ) && is_array( $question['question_answers'] ) ? $question['question_answers'] : array();
$answer  = ! empty( $answers ) ? reset( $answers ) : null;

if ( ! $answer ) {
	return;
}

$answer = is_array( $answer ) ? (object) $answer : $answer;

$question_settings  = isset( $question['question_settings'] ) && is_array( $question['question_settings'] ) ? $question['question_settings'] : array();
$axis_range         = isset( $question_settings['coordinates_axis_range'] ) ? (int) $question_settings['coordinates_axis_range'] : 10;
$axis_range         = 20 === $axis_range ? 20 : 10;
$answer_is_required = isset( $question_settings['answer_required'] ) && '1' === (string) $question_settings['answer_required'];
$required_message   = isset( $required_message ) ? (string) $required_message : __( 'The answer for this question is required', 'tutor-pro' );

$wrapper_id          = 'tutor-coordinates-question-' . $question_id;
$canvas_id           = 'tutor-coordinates-canvas-' . $question_id;
$input_points_id     = 'tutor-coordinates-points-' . $question_id;
$hover_display_id    = 'tutor-coordinates-hover-' . $question_id;
$instruction_id      = 'tutor-coordinates-instruction-' . $question_id;
$live_region_id      = 'tutor-coordinates-live-region-' . $question_id;
$marker_hover_url    = tutor_utils()->get_svg_icon_url( 'graph-marker-hover' );
$marker_selected_url = tutor_utils()->get_svg_icon_url( 'graph-marker-selected' );
$marker_wrong_url    = tutor_utils()->get_svg_icon_url( 'graph-marker-wrong' );

$coord_points_field_name = sprintf( '%s[answers][coordinates][points]', $question_field_name_base ?? '' );

$register_rules = '';
if ( $answer_is_required ) {
	$register_rules = ", { required: '" . esc_js( $required_message ) . "' }";
}
$coord_points_register_attr = "register('{$coord_points_field_name}'{$register_rules})";

/**
 * Fires when the learning-area coordinates template is rendered; Tutor Pro enqueues coordinates-question.js.
 *
 * @since 4.0.0
 */
do_action( 'tutor_enqueue_coordinates_question_script' );
?>

<div
	id="<?php echo esc_attr( $wrapper_id ); ?>"
	class="quiz-question-ans-choice-area tutor-mt-40 tutor-coordinates-question question-type-coordinates <?php echo esc_attr( $answer_is_required ? 'quiz-answer-required' : '' ); ?>"
	data-question-type="coordinates"
	data-question-id="<?php echo esc_attr( (string) $question_id ); ?>"
	data-axis-range="<?php echo esc_attr( (string) $axis_range ); ?>"
	data-marker-hover="<?php echo esc_url( $marker_hover_url ); ?>"
	data-marker-selected="<?php echo esc_url( $marker_selected_url ); ?>"
	data-marker-wrong="<?php echo esc_url( $marker_wrong_url ); ?>"
>
	<div class="tutor-coordinates-actions">
		<button type="button" class="tutor-coordinates-clear-prev tutor-coordinates-clear-button tutor-hidden">
			<?php SvgIcon::make()->name( Icon::ERASER )->size( 18 )->attr( 'class', 'tutor-coordinates-clear-button-icon' )->render(); ?>
			<?php esc_html_e( 'Clear', 'tutor-pro' ); ?>
		</button>
	</div>
	<div class="tutor-coordinates-grid-container">
		<canvas
			id="<?php echo esc_attr( $canvas_id ); ?>"
			class="tutor-coordinates-canvas"
			tabindex="0"
			role="application"
			aria-describedby="<?php echo esc_attr( $instruction_id . ' ' . $hover_display_id ); ?>"
			aria-label="<?php esc_attr_e( 'Coordinate grid: click or use arrow keys and Enter to select grid points.', 'tutor-pro' ); ?>"
			width="420"
			height="420"
		></canvas>
	</div>
	<p id="<?php echo esc_attr( $instruction_id ); ?>" class="tutor-quiz-a11y-sr-only">
		<?php esc_html_e( 'Use arrow keys to move the active grid point, Enter to add it, and Backspace or Delete to remove the last point.', 'tutor-pro' ); ?>
	</p>
	<p id="<?php echo esc_attr( $hover_display_id ); ?>" class="tutor-coordinates-hover-display tutor-fs-7 tutor-color-secondary tutor-mb-12" aria-live="polite"></p>
	<input
		type="hidden"
		id="<?php echo esc_attr( $input_points_id ); ?>"
		name="<?php echo esc_attr( $coord_points_field_name ); ?>"
		value=""
		x-bind="<?php echo esc_attr( $coord_points_register_attr ); ?>"
	/>
</div>

<div
	class="tutor-quiz-questions-error"
	x-cloak
	x-show="errors?.['<?php echo esc_attr( $coord_points_field_name ); ?>']?.message"
	x-text="errors?.['<?php echo esc_attr( $coord_points_field_name ); ?>']?.message"
></div>
