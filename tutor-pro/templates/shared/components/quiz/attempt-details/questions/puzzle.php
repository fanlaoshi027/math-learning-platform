<?php
/**
 * Attempt details Puzzle (read-only).
 *
 * @package TutorPro\Templates
 * @subpackage Shared\Quiz\AttemptDetails
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Models\QuizModel;
use TUTOR_PRO\QuizImageStorage;

if ( ! isset( $question ) || ! is_object( $question ) ) {
	return;
}

$answers      = QuizModel::get_answers_by_quiz_question( (int) $question->question_id, false );
$answers      = is_array( $answers ) ? $answers : array();
$first_answer = ! empty( $answers ) ? reset( $answers ) : null;
$image_url    = $first_answer ? QuizModel::get_answer_image_url( $first_answer ) : '';

$payload = array();
if ( ! empty( $question->given_answer ) ) {
	$decoded = json_decode( stripslashes( (string) $question->given_answer ), true );
	if ( is_array( $decoded ) ) {
		$payload = $decoded;
	}
}

$locked_count  = isset( $payload['locked_count'] ) ? (int) $payload['locked_count'] : 0;
$total_pieces  = isset( $payload['total_pieces'] ) ? (int) $payload['total_pieces'] : 0;
$grid_size     = isset( $payload['grid_size'] ) ? (int) $payload['grid_size'] : 0;
$snapshot_file = isset( $payload['playground_snapshot_file'] ) ? trim( (string) $payload['playground_snapshot_file'] ) : '';
$snapshot_url  = '' !== $snapshot_file ? QuizImageStorage::quiz_image_filename_to_url( $snapshot_file ) : '';
$has_snapshot  = '' !== $snapshot_url;
$review_column = isset( $puzzle_review_column ) ? (string) $puzzle_review_column : '';

// Single-column admin cells (given/correct); dual-column review keeps full-width wraps.
$image_wrap_class = 'tutor-puzzle-attempt-image-wrap';
if ( '' === $review_column ) {
	$image_wrap_class .= ' tutor-w-full';
}

$is_skipped    = QuizModel::is_attempt_answer_skipped( $question );
$review_status = QuizModel::get_attempt_answer_status( $question );
$given_option  = 'neutral';

if ( ! $has_snapshot && ! $is_skipped ) {
	if ( 'correct' === $review_status ) {
		$given_option = 'correct';
	} elseif ( 'pending' === $review_status ) {
		$given_option = 'pending';
	} elseif ( 'incorrect' === $review_status ) {
		$given_option = 'incorrect';
	}
}
?>

<div class="tutor-quiz-question-options">
	<?php if ( 'given' === $review_column ) : ?>
		<div class="<?php echo esc_attr( $image_wrap_class ); ?>">
			<?php if ( $has_snapshot ) : ?>
				<img src="<?php echo esc_url( $snapshot_url ); ?>" alt="<?php esc_attr_e( 'Puzzle given answer snapshot', 'tutor-pro' ); ?>" class="tutor-puzzle-attempt-image" />
			<?php else : ?>
				<div
					class="tutor-quiz-review-item tutor-quiz-review-given tutor-puzzle-attempt-given-placeholder"
					data-option="<?php echo esc_attr( $given_option ); ?>"
					aria-label="<?php esc_attr_e( 'No answer submitted', 'tutor-pro' ); ?>"
				></div>
			<?php endif; ?>
		</div>
	<?php elseif ( 'correct' === $review_column ) : ?>
		<?php if ( $image_url ) : ?>
			<div class="<?php echo esc_attr( $image_wrap_class ); ?>">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Puzzle correct answer image', 'tutor-pro' ); ?>" class="tutor-puzzle-attempt-image" />
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="tutor-puzzle-attempt-review tutor-grid tutor-grid-cols-2 tutor-gap-6 tutor-items-stretch">
			<div class="<?php echo esc_attr( $image_wrap_class ); ?>">
				<div class="tutor-puzzle-attempt-image-title"><?php esc_html_e( 'Given Answer', 'tutor-pro' ); ?></div>
				<?php if ( $has_snapshot ) : ?>
					<img src="<?php echo esc_url( $snapshot_url ); ?>" alt="<?php esc_attr_e( 'Puzzle given answer snapshot', 'tutor-pro' ); ?>" class="tutor-puzzle-attempt-image" />
				<?php else : ?>
					<div
						class="tutor-quiz-review-item tutor-quiz-review-given tutor-puzzle-attempt-given-placeholder"
						data-option="<?php echo esc_attr( $given_option ); ?>"
						aria-label="<?php esc_attr_e( 'No answer submitted', 'tutor-pro' ); ?>"
					></div>
				<?php endif; ?>
			</div>
			<?php if ( $image_url ) : ?>
				<div class="<?php echo esc_attr( $image_wrap_class ); ?>">
					<div class="tutor-puzzle-attempt-image-title"><?php esc_html_e( 'Correct Answer', 'tutor-pro' ); ?></div>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Puzzle correct answer image', 'tutor-pro' ); ?>" class="tutor-puzzle-attempt-image" />
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
