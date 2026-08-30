<?php
/**
 * Attempt details: Coordinates (read-only).
 *
 * @package TutorPro\Templates
 * @subpackage Shared\Quiz\AttemptDetails
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Models\QuizModel;

if ( ! isset( $question ) || ! is_object( $question ) ) {
	return;
}

$question_id = (int) ( $question->question_id ?? 0 );
if ( $question_id <= 0 ) {
	return;
}


$question_settings = isset( $question->question_settings ) ? maybe_unserialize( $question->question_settings ) : array();
if ( is_object( $question_settings ) ) {
	$question_settings = (array) $question_settings;
}
$axis_range = isset( $question_settings['coordinates_axis_range'] ) ? (int) $question_settings['coordinates_axis_range'] : 10;
$axis_range = 20 === $axis_range ? 20 : 10;
$min_coord  = -1 * $axis_range;
$max_coord  = $axis_range;

$review_column = isset( $coordinates_review_column ) ? (string) $coordinates_review_column : '';

$rows        = QuizModel::get_question_answers( $question_id, 'coordinates' );
$correct_raw = '';
if ( ! empty( $rows ) && ! empty( $rows[0]->answer_two_gap_match ) ) {
	$correct_raw = (string) $rows[0]->answer_two_gap_match;
}

$normalize_points = static function ( $payload ) use ( $min_coord, $max_coord ) {
	$points = array();
	if ( ! is_array( $payload ) ) {
		return $points;
	}
	if ( isset( $payload['points'] ) && is_array( $payload['points'] ) ) {
		$points = $payload['points'];
	} else {
		$is_list = array_keys( $payload ) === range( 0, count( $payload ) - 1 );
		if ( $is_list ) {
			$points = $payload;
		}
	}

	$normalized = array();
	$seen       = array();
	foreach ( $points as $point ) {
		if ( ! is_array( $point ) || ! isset( $point['x'], $point['y'] ) ) {
			continue;
		}
		$x   = max( $min_coord, min( $max_coord, (int) $point['x'] ) );
		$y   = max( $min_coord, min( $max_coord, (int) $point['y'] ) );
		$key = $x . ',' . $y;
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$normalized[] = array(
			'x' => $x,
			'y' => $y,
		);
	}

	return $normalized;
};

$correct_points = array();
if ( '' !== $correct_raw ) {
	$decoded = json_decode( stripslashes( $correct_raw ), true );
	if ( is_array( $decoded ) ) {
		$correct_points = $normalize_points( $decoded );
	}
}

$student_points = array();
if ( isset( $question->given_answer ) ) {
	$given = $question->given_answer;
	if ( is_string( $given ) && '' !== trim( $given ) ) {
		$decoded_given = json_decode( stripslashes( $given ), true );
		if ( is_array( $decoded_given ) ) {
			$student_points = $normalize_points( $decoded_given );
		}
	}
}

$show_given   = '' === $review_column || 'given' === $review_column;
$show_correct = '' === $review_column || 'correct' === $review_column;

if ( '' !== $review_column ) {
	$points_for_column = 'given' === $review_column ? $student_points : $correct_points;
	?>
<div class="tutor-quiz-question-options tutor-coordinates-attempt-review">
	<div class="tutor-fs-6 tutor-color-black tutor-fw-medium">
		<?php
		if ( ! empty( $points_for_column ) ) {
			$labels = array();
			foreach ( $points_for_column as $point ) {
				$labels[] = sprintf(
					'(%1$d, %2$d)',
					(int) ( $point['x'] ?? 0 ),
					(int) ( $point['y'] ?? 0 )
				);
			}

			echo esc_html( implode( ', ', $labels ) );
		} else {
			esc_html_e( 'No coordinates submitted.', 'tutor-pro' );
		}
		?>
	</div>
</div>
	<?php
	return;
}

/**
 * Enqueue coordinates canvas script (shared with learning-area; adds attempt-review graph).
 *
 * @since 4.0.0
 */
do_action( 'tutor_enqueue_coordinates_question_script' );

$graph_show_student  = (bool) ( $show_given && ! empty( $student_points ) );
$graph_show_correct  = (bool) ( $show_correct && ! empty( $correct_points ) );
$has_graph           = $graph_show_student || $graph_show_correct;
$marker_hover_url    = tutor_utils()->get_svg_icon_url( 'graph-marker-hover' );
$marker_selected_url = tutor_utils()->get_svg_icon_url( 'graph-marker-selected' );
$marker_correct_url  = tutor_utils()->get_svg_icon_url( 'graph-marker-correct' );
$marker_wrong_url    = tutor_utils()->get_svg_icon_url( 'graph-marker-wrong' );

$review_canvas_id = 'tutor-coordinates-review-canvas-' . $question_id;
?>

<div class="tutor-quiz-question-options tutor-coordinates-attempt-review">
	<?php if ( $has_graph ) : ?>
		<div
			class="tutor-coordinates-review-graph"
			data-tutor-coordinates-review="1"
			data-axis-range="<?php echo esc_attr( (string) $axis_range ); ?>"
			data-show-student="<?php echo $graph_show_student ? '1' : '0'; ?>"
			data-show-correct="<?php echo $graph_show_correct ? '1' : '0'; ?>"
			data-marker-hover="<?php echo esc_url( $marker_hover_url ); ?>"
			data-marker-selected="<?php echo esc_url( $marker_selected_url ); ?>"
			data-marker-correct="<?php echo esc_url( $marker_correct_url ); ?>"
			data-marker-wrong="<?php echo esc_url( $marker_wrong_url ); ?>"
			<?php if ( $graph_show_student ) : ?>
				data-student-points="<?php echo esc_attr( wp_json_encode( $student_points ) ); ?>"
			<?php endif; ?>
			<?php if ( $graph_show_correct ) : ?>
				data-correct-points="<?php echo esc_attr( wp_json_encode( $correct_points ) ); ?>"
			<?php endif; ?>
		>
			<div class="tutor-coordinates-grid-container tutor-coordinates-review-grid-container">
				<canvas
					id="<?php echo esc_attr( $review_canvas_id ); ?>"
					class="tutor-coordinates-canvas tutor-coordinates-review-canvas"
					width="420"
					height="420"
					aria-label="<?php esc_attr_e( 'Coordinate grid showing your answer and the correct answer.', 'tutor-pro' ); ?>"
				></canvas>
			</div>
			<div class="tutor-coordinates-review-tooltip" role="status" aria-live="polite" hidden></div>
		</div>
	<?php endif; ?>
</div>
