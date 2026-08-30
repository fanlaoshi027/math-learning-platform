<?php
/**
 * Lesson Notes Dashboard Template
 *
 * @package Tutor\Pro\Templates
 * @author Themeum
 * @since 4.0.0
 */

use Tutor\Components\Constants\Size;
use Tutor\Components\Constants\Variant;
use Tutor\Components\ConfirmationModal;
use Tutor\Components\CourseFilter;
use Tutor\Components\EmptyState;
use Tutor\Components\Nav;
use Tutor\Components\Pagination;
use Tutor\Components\SearchFilter;
use Tutor\Components\Sorting;
use TUTOR\Icon;
use TUTOR\Input;
use TUTOR_PRO\LessonNotes;

$note_type    = Input::get( 'type', '' );
$search_term  = Input::get( 'search', '' );
$order_filter = Input::get( 'order', 'DESC' );
$current_page = Input::get( 'current_page', 1, Input::TYPE_INT );
$course_id    = Input::get( 'course_id', 0, Input::TYPE_INT );
$limit        = tutor_utils()->get_option( 'pagination_per_page', 10 );
$offset       = max( 0, ( $current_page - 1 ) * $limit );

$notes = LessonNotes::get_dashboard_notes(
	array(
		'user_id'   => get_current_user_id(),
		'offset'    => $offset,
		'limit'     => $limit,
		'type'      => $note_type,
		'search'    => $search_term,
		'course_id' => $course_id,
		'order'     => $order_filter,
	)
);

$total_notes = LessonNotes::get_dashboard_notes(
	array(
		'user_id'   => get_current_user_id(),
		'type'      => $note_type,
		'search'    => $search_term,
		'course_id' => $course_id,
		'count'     => true,
	)
);

$courses = LessonNotes::get_courses_with_notes_by_user( get_current_user_id(), $note_type, $search_term );

$current_url = tutor_utils()->get_tutor_dashboard_page_permalink( 'notes' );

$nav_items = array(
	array(
		'type'   => 'link',
		'label'  => __( 'All', 'tutor-pro' ),
		'icon'   => Icon::ALL,
		'url'    => $current_url,
		'active' => empty( $note_type ),
	),
	array(
		'type'   => 'link',
		'label'  => __( 'Notes', 'tutor-pro' ),
		'icon'   => Icon::NOTES,
		'url'    => add_query_arg( 'type', LessonNotes::PARAM_NOTES, $current_url ),
		'active' => LessonNotes::PARAM_NOTES === $note_type,
	),
	array(
		'type'   => 'link',
		'label'  => __( 'Video Notes', 'tutor-pro' ),
		'icon'   => Icon::VIDEO_CAMERA_2,
		'url'    => add_query_arg( 'type', LessonNotes::PARAM_VIDEO_NOTES, $current_url ),
		'active' => LessonNotes::PARAM_VIDEO_NOTES === $note_type,
	),
);
?>

<div class="tutor-dashboard-lesson-notes" x-data="tutorDashboardNotes">
	<h4 class="tutor-h4 tutor-mb-5 tutor-hidden tutor-sm-block">
		<?php esc_html_e( 'Notes', 'tutor-pro' ); ?>
	</h4>

	<div class="tutor-dashboard-page-card">
		<div class="tutor-dashboard-page-card-header">
			<?php Nav::make()->items( $nav_items )->variant( Variant::PRIMARY )->size( Size::SMALL )->render(); ?>
		</div>
		<div class="tutor-dashboard-page-card-body">
			<div class="tutor-p-6 tutor-sm-p-5 tutor-border-b">
				<?php
				$hidden_inputs = array();
				if ( ! empty( $course_id ) ) {
					$hidden_inputs['course_id'] = $course_id;
				}

				SearchFilter::make()
					->form_id( 'tutor-dashboard-notes-search-form' )
					->placeholder( __( 'Search notes...', 'tutor-pro' ) )
					->action( $current_url )
					->hidden_inputs( $hidden_inputs )
					->render();
				?>
			</div>
			<div class="tutor-flex tutor-flex-wrap tutor-gap-4 tutor-items-center tutor-justify-between tutor-py-5 tutor-sm-py-4 tutor-px-6 tutor-sm-px-5 tutor-border-b">
				<?php CourseFilter::make()->courses( $courses )->count( $total_notes )->variant( Variant::LINK )->render(); ?>
				<?php Sorting::make()->order( $order_filter )->render(); ?>
			</div>

			<?php if ( empty( $notes ) ) : ?>
				<?php
					EmptyState::make()
						->title( __( 'No Notes Found!', 'tutor-pro' ) )
						->icon( tutor_utils()->get_themed_svg( 'images/illustrations/notes-text-empty.svg' ) )
						->render();
				?>
			<?php else : ?>
				<div class="tutor-lesson-note-list">
					<?php
					foreach ( $notes as $note ) :
						tutor_load_template(
							'lesson-notes.dashboard.note-item',
							array(
								'note' => $note,
							),
							true
						);
					endforeach;
					?>
				</div>
			<?php endif; ?>

			<?php if ( $total_notes > $limit ) : ?>
			<div class="tutor-p-6 tutor-sm-p-5 tutor-border-t">
				<?php Pagination::make()->current( $current_page )->total( $total_notes )->limit( $limit )->render(); ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php
	ConfirmationModal::make()
		->id( 'tutor-dashboard-note-delete-modal' )
		->title( __( 'Delete This Note?', 'tutor-pro' ) )
		->message( __( 'Are you sure you want to delete this note permanently? Please confirm your choice.', 'tutor-pro' ) )
		->mutation_state( 'deleteMutation' )
		->confirm_handler( 'handleDeleteNote(payload?.noteId, payload?.lessonId)' )
		->confirm_text( __( 'Yes, Delete This', 'tutor-pro' ) )
		->render();
	?>
</div>
