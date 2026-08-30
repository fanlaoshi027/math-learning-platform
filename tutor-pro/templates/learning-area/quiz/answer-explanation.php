<?php
/**
 * Tutor Pro quiz answer explanation.
 *
 * @package TutorPro\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use TUTOR\Icon;
use Tutor\Components\SvgIcon;

$answer_explanation = $answer_explanation ?? '';
$answer_explanation = is_string( $answer_explanation ) ? trim( $answer_explanation ) : '';
$question_id        = $question_id ?? 0;
$panel_id           = 'tutor-quiz-explanation-panel-' . (int) $question_id;
$trigger_id         = 'tutor-quiz-explanation-trigger-' . (int) $question_id;

$encoded_explanation = bin2hex( rawurlencode( wp_kses_post( $answer_explanation ) ) );

?>

<div
	class="tutor-quiz-explanation"
	data-quiz-explanation
	x-data="{ open: false }"
	:class="{ 'is-open': open }"
	data-quiz-explanation-content="<?php echo esc_attr( $encoded_explanation ); ?>"
>
	<button
		id="<?php echo esc_attr( $trigger_id ); ?>"
		type="button"
		class="tutor-quiz-explanation-trigger"
		data-quiz-explanation-toggle
		:aria-expanded="open"
		aria-controls="<?php echo esc_attr( $panel_id ); ?>"
		@click="open = !open"
	>
		<span>
			<?php SvgIcon::make()->name( Icon::BULB_LINE )->size( 24 )->render(); ?>
		</span>
		<span class="tutor-quiz-explanation-title">
			<?php esc_html_e( 'Answer Explanation', 'tutor-pro' ); ?>
		</span>
		<span class="tutor-quiz-explanation-chevron" aria-hidden="true">
			<?php SvgIcon::make()->name( Icon::CHEVRON_DOWN )->size( 20 )->render(); ?>
		</span>
	</button>
	<div
		id="<?php echo esc_attr( $panel_id ); ?>"
		class="tutor-quiz-explanation-panel"
		role="region"
		aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
		x-show="open"
		x-collapse.duration.300ms
	>
		<div class="tutor-quiz-explanation-body"></div>
	</div>
</div>
