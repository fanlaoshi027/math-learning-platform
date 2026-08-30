<?php
/**
 * Tutor Pro learning area webinar.
 *
 * @package TutorPro\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;


use Tutor\Components\Constants\Size;
use Tutor\Components\EmptyState;
use Tutor\Components\Pagination;
use Tutor\Components\SearchFilter;
use TUTOR\Course_List;
use Tutor\Helpers\UrlHelper;
use tutor\Icon;
use Tutor\Components\SvgIcon;
use TUTOR\Input;
use Tutor\Models\EnrollmentModel;
use TUTOR_PRO\Webinar;

$current_user_id            = get_current_user_id();
$tutor_current_post_type    = get_post_type();
$tutor_current_post         = get_post();
$tutor_current_content_id   = get_the_ID();
$tutor_course_id            = tutor()->course_post_type === $tutor_current_post_type ? $tutor_current_content_id : tutor_utils()->get_course_id_by_subcontent( $tutor_current_content_id );
$tutor_course               = get_post( $tutor_course_id );
$tutor_course_list_url      = tutor_utils()->course_archive_page_url();
$tutor_is_enrolled          = EnrollmentModel::is_enrolled( $tutor_course_id );
$tutor_is_public_course     = Course_List::is_public( $tutor_course_id );
$tutor_is_course_instructor = tutor_utils()->has_user_course_content_access( $current_user_id, $tutor_course_id );

$subpage       = Input::get( 'subpage', '' );
$date          = Input::get( 'date' );
$search_filter = Input::get( 'search', '' );

$item_per_page = tutor_utils()->get_option( 'pagination_per_page' );
$current_page  = max( 1, Input::get( 'current_page', 1, Input::TYPE_INT ) );
$offset        = ( $current_page - 1 ) * $item_per_page;

$zoom_meetings   = array();
$google_meetings = array();
$webinars        = array();

$course_id = $tutor_course_id;
$topics    = get_posts(
	array(
		'post_parent' => $course_id,
		'post_type'   => tutor()->topics_post_type,
		'fields'      => 'ids',
	)
);
$user_id   = get_current_user_id();


$webinar = new Webinar();

$webinar->webinar_google_meetings(
	$course_id,
	$topics,
	array(
		'search' => $search_filter,
		'date'   => $date,
	)
);
$webinar->webinar_zoom_meetings(
	$user_id,
	$course_id,
	array(
		'search' => $search_filter,
		'date'   => $date,
	)
);

$base_url = UrlHelper::add_query_params( get_permalink( $tutor_course_id ), array( 'subpage' => $subpage ) );

$webinars       = $webinar->get_webinars_list();
$webinars_count = count( $webinars );
$webinars       = array_slice( $webinars, $offset, $item_per_page, true );
$base_url       = $search_filter ? UrlHelper::add_query_params( $base_url, array( 'search' => $search_filter ) ) : $base_url;
?>
<div class="tutor-webinar tutor-py-8">
	<h4 class="tutor-h4 tutor-mb-5 tutor-flex tutor-items-center tutor-gap-4">
		<?php SvgIcon::make()->name( Icon::VIDEO_CAMERA_2 )->size( 24 )->render(); ?>
		<?php esc_html_e( 'Live Classes', 'tutor-pro' ); ?>
	</h4>
	<div class="tutor-card tutor-webinar-card">
		<div class="tutor-card-header tutor-space-y-4">
			<?php
				tutor_load_template(
					'components.learning-area.webinar.webinar-header',
					array(
						'base_url' => $base_url,
						'date'     => $date,
					),
					true
				)
				?>
			<div class="tutor-webinar-search">
				<?php
					SearchFilter::make()
						->form_id( 'webinar-search' )
						->placeholder( __( 'Search', 'tutor-pro' ) )
						->action( get_permalink( $tutor_course_id ) )
						->hidden_inputs(
							array(
								'subpage' => $subpage,
								'date'    => $date,
							)
						)
						->size( Size::MEDIUM )
						->render();
					?>
			</div>
			<div class="tutor-webinar-divider" aria-hidden="true"></div>
		</div>

		<div class="tutor-webinar-body tutor-space-y-8">
			<?php if ( ! tutor_utils()->count( $webinars ) || ! $webinar->is_addon_enabled() ) : ?>
				<?php
				EmptyState::make()
					->title( __( 'No Live Classes Available', 'tutor-pro' ) )
					->icon( tutor_utils()->get_themed_svg( 'images/illustrations/calendar-empty.svg' ) )
					->render();
				?>
			<?php else : ?>
				<?php
				$current_group = '';
				$group_open    = false;

				foreach ( $webinars as $lesson ) :
					$lesson_group = isset( $lesson['group_heading'] ) ? $lesson['group_heading'] : '';

					if ( $lesson_group && $lesson_group !== $current_group ) :
						if ( $group_open ) :
							?>
						</div>
							<?php
						endif;

						$current_group = $lesson_group;
						$group_open    = true;
						?>
					<div class="tutor-webinar-group tutor-space-y-3">
						<div class="tutor-webinar-group-heading">
							<?php echo esc_html( $lesson_group ); ?>
						</div>
						<?php
					elseif ( ! $group_open ) :
						$group_open = true;
						?>
					<div class="tutor-webinar-group tutor-space-y-3">
						<?php
					endif;

					tutor_load_template(
						'components.learning-area.webinar.webinar-lesson-card',
						array(
							'date_text'         => isset( $lesson['date_text'] ) ? $lesson['date_text'] : '',
							'time_text'         => isset( $lesson['time_text'] ) ? $lesson['time_text'] : '',
							'lesson_title'      => isset( $lesson['lesson_title'] ) ? $lesson['lesson_title'] : '',
							'course_id'         => isset( $tutor_course_id ) ? $tutor_course_id : '',
							'show_live_tag'     => isset( $lesson['show_live_tag'] ) ? $lesson['show_live_tag'] : true,
							'event_tag_text'    => isset( $lesson['event_tag_text'] ) ? $lesson['event_tag_text'] : '',
							'event_tag_icon'    => isset( $lesson['event_tag_icon'] ) ? $lesson['event_tag_icon'] : Icon::ZOOM_COLORIZE,
							'event_tag_variant' => isset( $lesson['event_tag_variant'] ) ? $lesson['event_tag_variant'] : '',
							'action_text'       => isset( $lesson['action_text'] ) ? $lesson['action_text'] : '',
							'action_url'        => isset( $lesson['action_url'] ) ? $lesson['action_url'] : '',
						),
						true
					);
					endforeach;

				if ( $group_open ) :
					?>
					</div>
					<?php
					endif;
				?>
			<?php endif ?>
		</div>
		<?php
		Pagination::make()
			->current( $current_page )
			->limit( $item_per_page )
			->total( $webinars_count )
			->attr( 'class', 'tutor-pt-6' )
			->render();
		?>
	</div>
</div>
