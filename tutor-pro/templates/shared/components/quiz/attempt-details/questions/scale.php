<?php
/**
 * Attempt details Scale (read-only) with visual scale.
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea\Quiz\AttemptDetails
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

// Allow Tutor Pro to enqueue the interactive scale script when available.
do_action( 'tutor_enqueue_scale_question_script' );

$review_column = isset( $scale_review_column ) ? (string) $scale_review_column : '';

// Resolve student-selected value.
$student_value = null;
if ( isset( $question->given_answer ) ) {
	$given_raw = $question->given_answer ?? '';
	if ( is_string( $given_raw ) && '' !== $given_raw ) {
		$student_data = json_decode( stripslashes( $given_raw ), true );
		if ( is_array( $student_data ) && isset( $student_data['value'] ) ) {
			$student_value = (float) $student_data['value'];
		}
	}
}

// Resolve correct value and scale configuration from question answers.
$correct_value = null;
$scale_config  = array();

if ( $question_id > 0 ) {
	$answers = QuizModel::get_question_answers( $question_id, 'scale' );
	if ( ! empty( $answers ) && ! empty( $answers[0]->answer_two_gap_match ) ) {
		$target_json = $answers[0]->answer_two_gap_match;
		$target      = json_decode( stripslashes( (string) $target_json ), true );
		if ( is_array( $target ) ) {
			if ( isset( $target['value'] ) ) {
				$correct_value = (float) $target['value'];
			}
			if ( isset( $target['config'] ) && is_array( $target['config'] ) ) {
				$scale_config = $target['config'];
			}
		}
	}
}

// Mirror defaults from Pro scale-question.js for consistent UI.
$min_value        = (float) ( $scale_config['min'] ?? 0 );
$max_value        = (float) ( $scale_config['max'] ?? 100 );
$step             = (float) ( $scale_config['step'] ?? 1 );
$px_per_unit      = (float) ( $scale_config['pxPerUnit'] ?? 10 );
$label_every      = (float) ( $scale_config['labelEvery'] ?? max( 1, ( $max_value - $min_value ) / 10 ) );
$minor_tick_every = (float) ( $scale_config['minorTickEvery'] ?? max( 1, ( $max_value - $min_value ) / 50 ) );
$precision        = (int) ( $scale_config['precision'] ?? ( ( $step < 1 ) ? 2 : 0 ) );

// Center the track on the correct answer so the bubble aligns with the green (correct) tick; student (red) remains on the scale.
if ( null !== $correct_value ) {
	$default_val = (float) $correct_value;
} elseif ( null !== $student_value ) {
	$default_val = (float) $student_value;
} else {
	$default_val = (float) ( ( $min_value + $max_value ) / 2 );
}

// Determine correctness status for styling (Tutor Pro sets status for scale).
$status_cls = 'tutor-scale-question-summary-wrong';
if ( isset( $question->given_answer ) ) {
	$answer_status = QuizModel::get_attempt_answer_status( $question );
	if ( 'correct' === $answer_status ) {
		$status_cls = 'tutor-scale-question-summary-correct';
	}
}

// Unique DOM IDs for this question instance.
$wrapper_id   = 'tutor-scale-question-summary-core-' . $question_id;
$container_id = 'tutor-scale-container-summary-core-' . $question_id;
$scale_id     = 'tutor-scale-summary-core-' . $question_id;
$bubble_id    = 'tutor-scale-bubble-summary-core-' . $question_id;
$input_id     = 'tutor-scale-value-' . $question_id;

$format_scale_value = static function ( $value ) use ( $precision ) {
	if ( null === $value ) {
		return '';
	}

	if ( $precision > 0 ) {
		return number_format( (float) $value, $precision, '.', '' );
	}

	return (string) (int) round( (float) $value );
};

$show_given_text_only   = 'given' === $review_column;
$show_correct_text_only = 'correct' === $review_column;

if ( $show_given_text_only || $show_correct_text_only ) {
	$display_value = $show_given_text_only ? $student_value : $correct_value;
	$display_text  = $format_scale_value( $display_value );
	?>
<div class="tutor-quiz-question-options">
	<div class="tutor-fs-6 tutor-color-black tutor-fw-medium">
		<?php
		if ( '' !== $display_text ) {
			echo esc_html( $display_text );
		} else {
			esc_html_e( 'No answer submitted.', 'tutor-pro' );
		}
		?>
	</div>
</div>
	<?php
	return;
}
?>

<div class="tutor-quiz-question-options">
	<div class="tutor-quiz-review-scale-wrapper">

		<div
			id="<?php echo esc_attr( $wrapper_id ); ?>"
			class="tutor-scale-question tutor-scale-question-summary <?php echo esc_attr( $status_cls ); ?>"
			data-question-type="scale"
			data-question-id="<?php echo esc_attr( (string) $question_id ); ?>"
			data-scale-config="
			<?php
			echo esc_attr(
				wp_json_encode(
					array(
						'min'            => $min_value,
						'max'            => $max_value,
						'step'           => $step,
						'defaultValue'   => $default_val,
						'pxPerUnit'      => $px_per_unit,
						'labelEvery'     => $label_every,
						'minorTickEvery' => $minor_tick_every,
						'precision'      => $precision,
						'readOnly'       => true,
						'summaryMode'    => true,
						// defaultValue centers viewport on correct tick; selectedValue = student (red); correctValue = green tick.
						'selectedValue'  => null !== $student_value ? (float) $student_value : null,
						'correctValue'   => null !== $correct_value ? (float) $correct_value : null,
					)
				)
			);
			?>
			"
		>
			<div class="tutor-scale-slider-wrapper">
				<div class="tutor-scale-container" id="<?php echo esc_attr( $container_id ); ?>" aria-label="<?php esc_attr_e( 'Scale answer review', 'tutor-pro' ); ?>">
					<div class="tutor-scale" id="<?php echo esc_attr( $scale_id ); ?>">
						<!-- Ticks will be generated by JavaScript -->
					</div>
				</div>

				<div class="tutor-scale-bubble" id="<?php echo esc_attr( $bubble_id ); ?>">
					<div class="tutor-scale-bubble-value">
						<?php
						$bubble_val = null !== $correct_value ? (float) $correct_value : $student_value;
						if ( null !== $bubble_val ) {
							echo esc_html( $precision > 0 ? number_format( $bubble_val, $precision, '.', '' ) : (string) (int) round( $bubble_val ) );
						} else {
							esc_html_e( '—', 'tutor-pro' );
						}
						?>
					</div>
					<div class="tutor-scale-bubble-pointer"></div>
				</div>
			</div>

			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" value="" />
		</div>
	</div>
</div>

