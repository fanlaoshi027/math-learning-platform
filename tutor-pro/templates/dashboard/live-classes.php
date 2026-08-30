<?php
/**
 * Template for handling live classes.
 *
 * @package TutorPro\Templates
 * @subpackage Dashboard
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\Size;
use Tutor\Components\Nav;
use Tutor\Helpers\UrlHelper;
use TUTOR\Icon;
use TUTOR\Input;
use TUTOR_PRO\Dashboard;
use TutorPro\GoogleMeet\GoogleMeet;

$nav_data = array();

$current_nav = Input::get( 'nav', tutor_utils()->is_addon_enabled( 'google-meet' ) ? 'google-meet' : 'zoom' );
$current_tab = Input::get( 'tab' );
$current_url = tutor_utils()->tutor_dashboard_url( Dashboard::LIVE_CLASSES_MENU );
$meet_nav    = array(
	'type'   => 'link',
	'label'  => __( 'Google Meet', 'tutor-pro' ),
	'icon'   => Icon::GOOGLE_MEET_COLORIZE,
	'url'    => UrlHelper::add_query_params( $current_url, array( 'nav' => 'google-meet' ) ),
	'active' => 'google-meet' === $current_nav,
);

$zoom_nav = array(
	'type'   => 'link',
	'label'  => __( 'Zoom', 'tutor-pro' ),
	'icon'   => Icon::ZOOM_COLORIZE,
	'url'    => UrlHelper::add_query_params(
		$current_url,
		array(
			'nav' => 'zoom',
			'tab' => tutor_zoom_check_api_connection() ? 'meetings' : 'set-api',
		)
	),
	'active' => 'zoom' === $current_nav,
);

$meet_template_path = '';
$zoom_template_path = '';

if ( tutor_utils()->is_addon_enabled( 'google-meet' ) ) {
	$plugin_data        = GoogleMeet::meta_data();
	$meet_template_path = $plugin_data['templates'] . 'dashboard/main.php';
	array_push( $nav_data, $meet_nav );
}

if ( tutor_utils()->is_addon_enabled( 'tutor-zoom' ) ) {
	$zoom_template_path = TUTOR_ZOOM()->path . '/templates/main.php';
	array_push( $nav_data, $zoom_nav );
}

?>

<div class="tutor-dashboard-live-classes">
	<div class="tutor-dashboard-page-card">
		<h4 class="tutor-h4 tutor-mb-5 tutor-hidden tutor-sm-block">
			<?php esc_html_e( 'Live Classes', 'tutor-pro' ); ?>
		</h4>
		<div class="tutor-dashboard-page-card-header">
		<?php
			Nav::make()
				->items( $nav_data )
				->size( Size::SMALL )
				->render();
		?>
		</div>
		<div class="tutor-dashboard-page-card-body">
		<?php if ( 'google-meet' === $current_nav ) : ?>
			<?php tutor_load_template_from_custom_path( $meet_template_path ); ?>
		<?php else : ?>
			<?php tutor_load_template_from_custom_path( $zoom_template_path ); ?>
		<?php endif; ?>
		</div>
	</div>
</div>
<div class="tutor-flex tutor-align-center tutor-justify-end">
<?php

if ( 'zoom' === $current_nav && 'set-api' === $current_tab ) {
	Button::make()
		->label( __( 'Save & Check Connection', 'tutor-pro' ) )
		->size( Size::SMALL )
		->attr( 'class', 'tutor-mt-8' )
		->attr( 'type', 'submit' )
		->attr( 'form', 'tutor-zoom-frontend-set-api-form' )
		->attr( 'id', 'save_zoom_api_settings_btn' )
		->render();
}
?>
</div>