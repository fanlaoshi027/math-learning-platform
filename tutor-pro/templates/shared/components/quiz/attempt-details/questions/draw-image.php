<?php
/**
 * Attempt details Draw on Image (read-only).
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


$draw_image_answers = QuizModel::get_answers_by_quiz_question( (int) $question->question_id, false );

$instructor_answer_bg = null;

$instructor_answer_mask = null;

$ref_bg = '';

$ref_mask_raw = '';

if ( is_array( $draw_image_answers ) && ! empty( $draw_image_answers ) ) {
	foreach ( $draw_image_answers as $answer_row ) {
		if ( ! $instructor_answer_mask && ! empty( $answer_row->answer_two_gap_match ) ) {
			$instructor_answer_mask = $answer_row;
		}

		if ( ! $instructor_answer_bg ) {
			$maybe_bg_url = QuizModel::get_answer_image_url( $answer_row );
			if ( $maybe_bg_url ) {
				$instructor_answer_bg = $answer_row;
				$ref_bg               = $maybe_bg_url;
			}
		}

		if ( $instructor_answer_bg && $instructor_answer_mask ) {
			break;
		}
	}
}

$given_mask_raw = '';
if ( isset( $question->given_answer ) ) {
	$given_mask_raw = trim( stripslashes( (string) $question->given_answer ) );
}

$ref_mask_raw = $instructor_answer_mask && ! empty( $instructor_answer_mask->answer_two_gap_match )
	? trim( stripslashes( (string) $instructor_answer_mask->answer_two_gap_match ) )
	: '';

/**
 * Normalize stored mask value for use in CSS mask-image url().
 *
 * @param string $mask Mask basename, uploads-relative path, data URI, or URL.
 * @return string Fragment for url("...") or empty.
 */
$mask_to_css_url = static function ( $mask ) {
	$mask = trim( (string) $mask );
	if ( '' === $mask ) {
		return '';
	}
	$resolved = QuizImageStorage::quiz_image_stored_value_to_url( $mask );
	if ( '' === $resolved ) {
		return '';
	}
	$is_data_uri = 0 === strpos( $resolved, 'data:image/' ) && false !== strpos( $resolved, ';base64,' );
	return $is_data_uri ? $resolved : esc_url_raw( $resolved );
};

$given_mask_css = $mask_to_css_url( $given_mask_raw );

$ref_mask_css = $mask_to_css_url( $ref_mask_raw );

$has_correct_mask = '' !== $ref_mask_css;

$has_student_drawn = '' !== $given_mask_css;

$has_bg = is_string( $ref_bg ) && '' !== trim( $ref_bg );

$correct_mask_style = '';
if ( $has_correct_mask ) {
	$correct_mask_style = '--tutor-draw-mask-url: url("' . $ref_mask_css . '"); --tutor-draw-mask-bg: rgba(4, 201, 134, 0.28);';
}
$student_mask_style = '';
if ( $has_student_drawn ) {
	// Match the "correct" mask approach: fill color via --tutor-draw-mask-bg,
	// while the border outline is handled in SCSS via drop-shadow.
	// Inner tint should be faint (outline-only look). Derived alpha: 0.1608 * 0.16 ~= 0.0257.
	$student_mask_style = '--tutor-draw-mask-url: url("' . $given_mask_css . '"); --tutor-draw-mask-bg: rgba(248, 0, 0, 0.0257);';
}

/**
 * Same pattern as pin-image attempt review: inclusive conditions so admin split columns
 * show only the instructor mask in "Correct answer" and only the student mask in "Given answer".
 * Empty string = combined layout (frontend quiz summary).
 */
$review_column = isset( $draw_image_review_column ) ? strtolower( trim( (string) $draw_image_review_column ) ) : '';

$show_instructor_overlay = ( '' === $review_column || 'correct' === $review_column ) && $has_correct_mask;
$show_student_overlay    = ( '' === $review_column || 'given' === $review_column ) && $has_student_drawn;

?>

<div class="tutor-quiz-question-options tutor-quiz-draw-image-review">
	<?php if ( '' === $review_column ) : ?>
		<?php if ( $has_bg && ( $has_correct_mask || $has_student_drawn ) ) : ?>
		<div class="tutor-draw-image-review-inner">
			<img src="<?php echo esc_url( $ref_bg ); ?>" alt="<?php esc_attr_e( 'Question background image', 'tutor-pro' ); ?>" class="tutor-draw-image-bg" />
			<?php if ( $has_correct_mask ) : ?>
				<span
					class="tutor-draw-image-review-mask tutor-draw-image-review-mask-correct"
					style="<?php echo esc_attr( $correct_mask_style ); ?>"
					role="presentation"
				></span>
			<?php endif; ?>
			<?php if ( $has_student_drawn ) : ?>
				<span
					class="tutor-draw-image-review-mask tutor-draw-image-review-mask-student"
					style="<?php echo esc_attr( $student_mask_style ); ?>"
					role="presentation"
				></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	<?php elseif ( $has_bg && ( $show_instructor_overlay || $show_student_overlay ) ) : ?>
		<div class="tutor-draw-image-review-inner">
			<img src="<?php echo esc_url( $ref_bg ); ?>" alt="<?php esc_attr_e( 'Question background image', 'tutor-pro' ); ?>" class="tutor-draw-image-bg" />
			<?php if ( $show_instructor_overlay ) : ?>
				<span
					class="tutor-draw-image-review-mask tutor-draw-image-review-mask-correct"
					style="<?php echo esc_attr( $correct_mask_style ); ?>"
					role="presentation"
				></span>
			<?php endif; ?>
			<?php if ( $show_student_overlay ) : ?>
				<span
					class="tutor-draw-image-review-mask tutor-draw-image-review-mask-student"
					style="<?php echo esc_attr( $student_mask_style ); ?>"
					role="presentation"
				></span>
			<?php endif; ?>
		</div>
	<?php elseif ( $show_student_overlay ) : ?>
		<img src="<?php echo 0 === strpos( $given_mask_css, 'data:image/' ) ? esc_attr( $given_mask_css ) : esc_url( $given_mask_css ); ?>" alt="<?php esc_attr_e( 'Your drawn answer', 'tutor-pro' ); ?>" class="tutor-draw-image-single" />
	<?php elseif ( $show_instructor_overlay ) : ?>
		<img src="<?php echo 0 === strpos( $ref_mask_css, 'data:image/' ) ? esc_attr( $ref_mask_css ) : esc_url( $ref_mask_css ); ?>" alt="<?php esc_attr_e( 'Correct drawn answer', 'tutor-pro' ); ?>" class="tutor-draw-image-single" />
	<?php endif; ?>
</div>
