<?php
/**
 * Template for displaying gift course card
 *
 * @package TutorPro\Templates
 * @subpackage Dashboard
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.8.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\Variant;
use Tutor\Components\Modal;
use TutorPro\GiftCourse\GiftCourse;

if ( ! defined( 'TUTOR_PRO_VERSION' ) ) {
	return;
}

if ( ! $course_id || ! $reference_id ) {
	return;
}

$course = get_post( $course_id );
if ( ! $course ) {
	return;
}

$tutor_course_img = get_tutor_course_thumbnail_src( 'post-thumbnail', $course_id );
$course_rating    = tutor_utils()->get_course_rating( $course_id );
$course_author    = get_userdata( $course->post_author );
$learning_url     = tutor_utils()->get_course_first_lesson( $course_id );
if ( ! $learning_url ) {
	$learning_url = get_the_permalink( $course->ID );
}

$gift_data = GiftCourse::get_gift_data_by_reference_id( $reference_id );
if ( ! $gift_data ) {
	return;
}

$recipient_name = $gift_data->recipient_name;
$purchaser      = get_userdata( $gift_data->purchaser_id );
$purchaser_name = tutor_utils()->display_name( $purchaser->ID );
$message        = $gift_data->message;

$modal_id = 'tutor-greetings-popup-' . $course->ID;

?>

<div class="tutor-card tutor-card-rounded-2xl tutor-gift-course-card">
	<!-- Left decorative element -->
	<svg width="32" height="12" viewBox="0 0 32 12" fill="none" xmlns="http://www.w3.org/2000/svg" class="tutor-gift-card-decoration-left">
		<path d="M0 0H31.583L30.0012 2.99L31.583 5.98L30.0012 8.97L31.583 11.96H0V0Z" fill="#FE621E"/>
	</svg>

	<!-- Gift box icon -->
	<img src="<?php echo esc_attr( tutor_pro()->url . 'assets/images/gift-box.svg' ); ?>" alt="<?php esc_html_e( 'Gix box', 'tutor-pro' ); ?>" />

	<!-- Main content -->
	<div class="tutor-sm-text-center">
		<h4 class="tutor-h4 tutor-text-brand tutor-mb-1">
			<?php esc_html_e( 'Congratulations!', 'tutor-pro' ); ?>
		</h4>
		<p class="tutor-small tutor-mb-6">
			<?php esc_html_e( 'You have received a Gift from', 'tutor-pro' ); ?> <span class="tutor-font-bold"><?php echo esc_html( $purchaser_name ); ?></span>
		</p>
		<?php
			Button::make()
				->label( __( 'Reveal Gift', 'tutor-pro' ) )
				->variant( Variant::PRIMARY )
				->size( 'small' )
				->attr( 'class', 'tutor-gift-card-button' )
				->attr( 'data-course-id', $course->ID )
				->attr( 'data-reference-id', $reference_id )
				->render();
		?>
	</div>

	<!-- Right decorative element -->
	<svg width="103" height="152" viewBox="0 0 103 152" fill="none" xmlns="http://www.w3.org/2000/svg" class="tutor-gift-card-decoration-right">
		<rect x="28.9849" width="11.358" height="152" fill="#FE621E"/>
		<rect x="25.7561" y="70.0195" width="77.1302" height="11.9571" fill="#FE621E"/>
		<rect x="60.686" y="70.0195" width="12.6723" height="11.8622" fill="#C93E01"/>
		<rect x="28.6868" y="69.8164" width="11.9558" height="13.0825" fill="#C93E01"/>
		<path d="M7.03616 59.8209L28.6864 69.818V82.9006L6.59367 92.4471C5.21277 93.0438 3.62076 92.8779 2.39252 92.0093C0.892055 90.9483 0 89.2249 0 87.3872V65.0934C0 63.3204 0.779323 61.637 2.13121 60.4898C3.49663 59.3311 5.41035 59.0701 7.03616 59.8209Z" fill="#FE621E"/>
		<path d="M2.39252 92.0071L2.06544 91.7758C0.770102 90.8599 0 89.3721 0 87.7856C0 85.0865 2.18803 82.8985 4.8871 82.8985L28.6864 82.8984L6.59367 92.4448C5.21277 93.0415 3.62076 92.8757 2.39252 92.0071Z" fill="#C93E01"/>
		<path d="M62.2923 59.8209L40.6421 69.818V82.9006L62.7348 92.4471C64.1157 93.0438 65.7077 92.8779 66.936 92.0093C68.4364 90.9483 69.3285 89.2249 69.3285 87.3872V65.0934C69.3285 63.3204 68.5492 61.637 67.1973 60.4898C65.8319 59.3311 63.9181 59.0701 62.2923 59.8209Z" fill="#FE621E"/>
		<path d="M66.936 92.0071L67.2631 91.7758C68.5584 90.8599 69.3285 89.3721 69.3285 87.7856C69.3285 85.0865 67.1405 82.8985 64.4414 82.8985L40.6421 82.8984L62.7348 92.4448C64.1157 93.0415 65.7077 92.8757 66.936 92.0071Z" fill="#C93E01"/>
	</svg>
</div>

<?php
Modal::make()
	->id( $modal_id )
	->template(
		tutor_pro()->path . 'templates/dashboard/gift-course-greetings-modal.php',
		array(
			'recipient_name' => $recipient_name,
			'message'        => $message,
			'purchaser_name' => $purchaser_name,
			'course'         => $course,
			'learning_url'   => $learning_url,
			'course_rating'  => $course_rating,
			'course_author'  => $course_author,
		)
	)
	->width( '600px' )
	->render();
?>
