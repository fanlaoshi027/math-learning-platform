<?php
/**
 * Lesson note tab content template
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\ConfirmationModal;
use Tutor\Components\DropdownFilter;
use Tutor\Components\EmptyState;
use Tutor\Components\Sorting;
use Tutor\Helpers\QueryHelper;
use TUTOR\Icon;
use TUTOR\Input;
use TUTOR_PRO\LessonNotes;

$user_id       = get_current_user_id();
$item_per_page = tutor_utils()->get_option( 'pagination_per_page', 10 );
$current_page  = max( 1, Input::post( 'current_page', 0, Input::TYPE_INT ) );
$order_filter  = QueryHelper::get_valid_sort_order( Input::get( 'order', 'DESC' ) );
$type_filter   = Input::get( 'type', '' );

$lesson_notes   = new LessonNotes();
$note_list      = $lesson_notes->get_lesson_notes( $lesson_id, $user_id, 0, $item_per_page, $order_filter, $type_filter );
$note_count     = $lesson_notes->get_lesson_notes_count( $lesson_id, $user_id );
$filter_options = $lesson_notes->get_learning_area_filter_options( $lesson_id, $user_id );
?>
<div class="tutor-tab-panel tutor-learning-area-notes" x-data="tutorLearningAreaNotes(<?php echo esc_js( $lesson_id ); ?>, <?php echo esc_js( $note_count ); ?>)" x-show="activeTab === 'notes'" x-cloak role="tabpanel"> 
	<div class="tutor-p-6" x-data="{ focused: false }">
		<?php
		tutor_load_template(
			'lesson-notes.learning-area.note-form',
			array(
				'id'             => 'tutor-create-lesson-note-form',
				'submit_handler' => '(data) => createMutation?.mutate({ ...data, lesson_id: ' . esc_js( $lesson_id ) . ' })',
				'cancel_handler' => 'focused = false',
				'is_pending'     => 'createMutation?.isPending',
				'focused_state'  => 'focused',
			),
			true
		);
		?>
	</div>

	<div>
		<div 
			class="tutor-flex tutor-items-center tutor-justify-between tutor-px-6 tutor-py-5 tutor-border-t"
			:class="{ 'tutor-loading-spinner': isReloading }"
		>
			<?php
			DropdownFilter::make()
				->options( $filter_options )
				->query_param( 'type' )
				->on_change( 'handleChangeType' )
				->bind_active_value( 'currentType' )
				->render();
			?>
			<?php
			Sorting::make()
				->order( $order_filter )
				->on_change( 'handleChangeOrder' )
				->bind_active_order( 'currentOrder' )
				->render();
			?>
		</div>

		<div x-ref="noteList" class="tutor-lesson-note-list tutor-border-t">
			<?php
			tutor_load_template(
				'lesson-notes.learning-area.note-list',
				array(
					'lesson_id' => $lesson_id,
					'note_list' => $note_list,
				),
				true
			);
			?>
		</div>

		<?php
		EmptyState::make()
			->title( __( 'No Notes Have Been Added!', 'tutor-pro' ) )
			->icon( tutor_utils()->get_themed_svg( 'images/illustrations/notes-text-empty.svg' ) )
			->attr( 'x-show', 'isEmpty' )
			->attr( 'x-cloak', '' )
			->render();
		?>

		<div x-ref="loadMoreTrigger" x-show="hasMore && !isReloading" aria-hidden="true">
			<span x-show="loading" class="tutor-loading-spinner tutor-border-t"></span>
		</div>
	</div>

	<?php
	ConfirmationModal::make()
		->id( 'delete-note-modal' )
		->title( 'Delete This Note?' )
		->message( 'This action cannot be undone.' )
		->mutation_state( 'deleteMutation' )
		->confirm_handler( 'deleteMutation?.mutate(payload)' )
		->render();
	?>
</div>

<?php
tutor_load_template( 'lesson-notes.learning-area.take-note-button', array(), true );
tutor_load_template(
	'lesson-notes.learning-area.take-note-popover',
	array(
		'lesson_id' => $lesson_id,
	),
	true
);
?>
