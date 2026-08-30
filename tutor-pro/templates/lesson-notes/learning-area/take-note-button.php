<?php
/**
 * Take note button template
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

use TUTOR\Icon;
use Tutor\Components\SvgIcon;

defined( 'ABSPATH' ) || exit;
?>
<button id="tutor-take-lesson-note-btn" class="tutor-btn tutor-btn-primary tutor-btn-x-small tutor-transition-none tutor-d-none">
	<?php SvgIcon::make()->name( Icon::QUILL )->render(); ?>
	<?php esc_html_e( 'Take Note', 'tutor-pro' ); ?>
</button>
