<?php
/**
 * Attempt details Pin on Image (read-only).
 *
 * @package TutorPro\Templates
 * @subpackage Shared\Quiz\AttemptDetails
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Models\QuizModel;
use TUTOR_PRO\QuizImageStorage;

if ( ! isset( $question ) || ! is_object( $question ) ) {
	return;
}


$pin_answers    = QuizModel::get_answers_by_quiz_question( (int) $question->question_id, false );
$pin_answers    = is_array( $pin_answers ) ? $pin_answers : array();
$review_column  = isset( $pin_image_review_column ) ? (string) $pin_image_review_column : '';

$answer_with_bg   = null;
$answer_with_mask = null;
foreach ( $pin_answers as $pin_answer_row ) {
	if ( ! $answer_with_bg ) {
		$maybe_bg = QuizModel::get_answer_image_url( $pin_answer_row );
		if ( $maybe_bg ) {
			$answer_with_bg = $pin_answer_row;
		}
	}

	if ( ! $answer_with_mask && ! empty( $pin_answer_row->answer_two_gap_match ) ) {
		$answer_with_mask = $pin_answer_row;
	}

	if ( $answer_with_bg && $answer_with_mask ) {
		break;
	}
}

$background_url     = $answer_with_bg ? QuizModel::get_answer_image_url( $answer_with_bg ) : '';
$reference_mask     = $answer_with_mask && ! empty( $answer_with_mask->answer_two_gap_match ) ? (string) $answer_with_mask->answer_two_gap_match : '';
$reference_mask     = trim( $reference_mask );
$reference_mask_css = '';
if ( '' !== $reference_mask ) {
	$resolved = QuizImageStorage::quiz_image_stored_value_to_url( $reference_mask );
	if ( '' !== $resolved ) {
		$reference_is_data  = 0 === strpos( $resolved, 'data:image/' ) && false !== strpos( $resolved, ';base64,' );
		$reference_mask_css = $reference_is_data ? $resolved : esc_url_raw( $resolved );
	}
}
$has_reference = '' !== $reference_mask_css;
$wrapper_id    = 'tutor-pin-image-attempt-' . (int) $question->question_id;

$coords = null;
if ( ! empty( $question->given_answer ) ) {
	$given_answer = maybe_unserialize( $question->given_answer );
	$decoded      = null;

	if ( is_array( $given_answer ) ) {
		$decoded = $given_answer;
	} elseif ( is_string( $given_answer ) ) {
		$decoded = json_decode( stripslashes( $given_answer ), true );
		if ( ! is_array( $decoded ) ) {
			$decoded = json_decode( $given_answer, true );
		}
	}

	if ( is_array( $decoded ) && isset( $decoded['answers']['pin'] ) && is_array( $decoded['answers']['pin'] ) ) {
		$decoded = $decoded['answers']['pin'];
	}

	if ( is_array( $decoded ) && isset( $decoded['pin'] ) && is_array( $decoded['pin'] ) ) {
		$decoded = $decoded['pin'];
	}

	if ( is_array( $decoded ) && isset( $decoded['x'], $decoded['y'] ) ) {
		$coords = array(
			'x' => max( 0.0, min( 1.0, (float) $decoded['x'] ) ),
			'y' => max( 0.0, min( 1.0, (float) $decoded['y'] ) ),
		);
	}
}
?>

<div id="<?php echo esc_attr( $wrapper_id ); ?>" class="tutor-quiz-question-options">
	<div class="tutor-pin-image-given-answer">

		<?php if ( $background_url ) : ?>
			<div class="tutor-pin-image-layered">
				<img src="<?php echo esc_url( $background_url ); ?>" alt="<?php esc_attr_e( 'Pin question background image', 'tutor-pro' ); ?>" class="tutor-pin-image-bg" />
				<?php if ( ( '' === $review_column || 'correct' === $review_column ) && $has_reference ) : ?>
					<span
						class="tutor-pin-image-overlay tutor-pin-image-overlay-tint"
						style="<?php echo esc_attr( '--tutor-pin-mask-url: url("' . $reference_mask_css . '"); --tutor-pin-mask-bg: #04C98633;' ); ?>"
						role="presentation"
					></span>
				<?php endif; ?>
				<?php if ( ( '' === $review_column || 'given' === $review_column ) && $coords ) : ?>
					<span
						class="tutor-pin-image-marker"
						style="--tutor-pin-x: <?php echo esc_attr( $coords['x'] * 100 ); ?>%; --tutor-pin-y: <?php echo esc_attr( $coords['y'] * 100 ); ?>%;"
					></span>
				<?php endif; ?>
			</div>
		<?php elseif ( $has_reference ) : ?>
			<div class="tutor-pin-image-single tutor-pin-image-single-tint">
				<span
					class="tutor-pin-image-single-mask"
					style="<?php echo esc_attr( '--tutor-pin-mask-url: url("' . $reference_mask_css . '"); --tutor-pin-mask-bg: #04C98633;' ); ?>"
					role="presentation"
				></span>
			</div>
			<div class="tutor-fs-7 tutor-color-secondary tutor-mt-8">
				<?php esc_html_e( 'Background image not available; showing only the correct zone mask.', 'tutor-pro' ); ?>
			</div>
		<?php elseif ( $coords ) : ?>
			<div class="tutor-fs-7 tutor-color-secondary">
				<?php esc_html_e( 'Pin submitted, but no background image found.', 'tutor-pro' ); ?>
			</div>
		<?php else : ?>
			<div class="tutor-fs-7 tutor-color-secondary">
				<?php esc_html_e( 'No pin submitted.', 'tutor-pro' ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
