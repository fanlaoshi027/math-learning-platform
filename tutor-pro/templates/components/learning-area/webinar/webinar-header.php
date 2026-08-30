<?php
/**
 * Webinar header for learning area webinar page.
 *
 * @package TutorPro\Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\Size;
use Tutor\Components\Constants\Variant;
use Tutor\Helpers\DateTimeHelper;
use Tutor\Helpers\UrlHelper;
use TUTOR\Icon;


$today = $date ?? DateTimeHelper::now()->to_date_time_string();

$webinar_month_text = DateTimeHelper::get_gmt_to_user_timezone_date( $today, 'F' );
$webinar_year_text  = DateTimeHelper::get_gmt_to_user_timezone_date( $today, 'Y' );

$previous = DateTimeHelper::create( $today )->sub( 1, 'month' )->to_date_time_string();
$next     = DateTimeHelper::create( $today )->add( 1, 'month' )->to_date_time_string();

$previous_url = UrlHelper::add_query_params( $base_url ?? '', array( 'date' => $previous ) );
$next_url     = UrlHelper::add_query_params( $base_url ?? '', array( 'date' => $next ) );
?>
<div class="tutor-webinar-header">
	<h3 class="tutor-h3 tutor-sm-text-h4">
		<?php echo esc_html( $webinar_month_text ); ?>
		<?php if ( ! empty( $webinar_year_text ) ) : ?>
		<span class="tutor-font-regular"><?php echo esc_html( $webinar_year_text ); ?></span>
		<?php endif; ?>
	</h3>
	<div class="tutor-webinar-actions tutor-flex tutor-items-center tutor-gap-2">
		<?php
			Button::make()
				->label( __( 'Previous Month', 'tutor-pro' ) )
				->icon( Icon::CHEVRON_LEFT_2 )
				->flip_rtl()
				->icon_only()
				->variant( Variant::SECONDARY )
				->size( Size::X_SMALL )
				->tag( 'a' )
				->attr( 'href', $previous_url )
				->render();

			Button::make()
				->label( __( 'This Month', 'tutor-pro' ) )
				->variant( Variant::SECONDARY )
				->size( Size::X_SMALL )
				->tag( 'a' )
				->attr( 'href', $base_url )
				->render();

			Button::make()
				->label( __( 'Next Month', 'tutor-pro' ) )
				->icon( Icon::CHEVRON_RIGHT_2 )
				->flip_rtl()
				->icon_only()
				->variant( Variant::SECONDARY )
				->size( Size::X_SMALL )
				->tag( 'a' )
				->attr( 'href', $next_url )
				->render();
		?>
	</div>
</div>
