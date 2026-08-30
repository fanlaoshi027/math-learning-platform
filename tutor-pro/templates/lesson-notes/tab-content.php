<?php
/**
 * Lesson note tab content template
 *
 * @package Tutor\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TUTOR\Icon;
use Tutor\Components\SvgIcon;

?>
<div id="tutor-course-spotlight-notes" class="tutor-tab-item<?php echo esc_attr( $is_active ? ' is-active' : '' ); ?>">
	<div class="tutor-container">
		<div class="tutor-course-spotlight-notes">
			<div class="tutor-notes-tab-content-form">
				<?php
				tutor_load_template(
					'lesson-notes/form',
					array(
						'lesson_id' => $lesson_id,
						'main_form' => true,
					),
					true
				);
				?>
			</div>

			<div class="tutor-lesson-note-list-wrapper">
				<?php
				tutor_load_template(
					'lesson-notes/note-list',
					array(
						'lesson_id' => $lesson_id,
					),
					true
				);
				?>
			</div>
		</div>
	</div>
</div>
<button id="tutor-take-lesson-note-btn" class="tutor-btn tutor-btn-primary tutor-btn-sm tutor-d-none">
	<?php SvgIcon::make()->name( Icon::FEATHER )->size( 20 )->render(); ?>
	<?php esc_html_e( 'Take Note', 'tutor-pro' ); ?>
</button>
<div class="tutor-lesson-note-form-wrapper tutor-d-none">
	<div class="tutor-d-flex tutor-justify-between tutor-align-center tutor-px-16 tutor-py-12">
		<div class="tutor-lesson-note-form-title-wrapper">
			<?php SvgIcon::make()->name( Icon::FEATHER )->size( 20 )->render(); ?>
			<div class="tutor-lesson-note-form-title">
				<?php esc_html_e( 'Take Note', 'tutor-pro' ); ?>
			</div>
		</div>
		<div>
			<span class="tutor-lesson-note-start-time tutor-d-none">00:00 -</span>
			<button class="tutor-btn tutor-iconic-btn" data-action-close>
				<span class="tutor-icon-times"></span>
			</button>
		</div>
	</div>
	<div class="tutor-px-16 tutor-pb-16">
		<?php
			tutor_load_template(
				'lesson-notes/form',
				array(
					'lesson_id' => $lesson_id,
				),
				true
			);
			?>
	</div>
</div>
