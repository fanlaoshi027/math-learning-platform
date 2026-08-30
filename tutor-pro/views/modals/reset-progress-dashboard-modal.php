<?php
/**
 * Dashboard: Reset course progress modal template
 *
 * @author themeum
 * @link https://themeum.com
 * @package TutorLMS/Templates
 *
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\ConfirmationModal;
use Tutor\Components\Constants\Variant;

$modal_id = $data['modal_id'] ?? '';

$confirm_btn = Button::make()
	->variant( Variant::PRIMARY )
	->label( __( 'Yes, Reset', 'tutor-pro' ) )
	->attr( 'class', 'tutor-reset-progress-action' )
	->attr( 'data-modal-data', esc_attr( wp_json_encode( $data ) ) )
	->get();

ConfirmationModal::make()
	->id( $modal_id )
	->title( __( 'Reset Course Progress?', 'tutor-pro' ) )
	->message( __( 'Resetting will erase this student\'s completed lessons, quizzes, and assignment records for the selected course.', 'tutor-pro' ) )
	->icon( tutor_utils()->get_themed_svg( 'images/illustrations/reset-course.svg' ), 80, 80, ConfirmationModal::ICON_TYPE_HTML )
	->confirm_button( $confirm_btn )
	->render();
