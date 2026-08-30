<?php
/**
 * Lesson note form template
 *
 * @package TutorPro\Templates
 * @subpackage LearningArea
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\Button;
use Tutor\Components\Constants\InputType;
use Tutor\Components\Constants\Size;
use Tutor\Components\Constants\Variant;
use Tutor\Components\InputField;
use TUTOR\Icon;
use Tutor\Components\SvgIcon;

$form_id        = $id ?? 'tutor-lesson-note-form';
$submit_handler = $submit_handler ?? '';
$cancel_handler = $cancel_handler ?? '';
$is_pending     = $is_pending ?? 'false';
$default_value  = $default_value ?? '';
$focused_state  = $focused_state ?? null;
$show_binding   = $show_binding ?? null;
?>

<form 
	class="tutor-lesson-note-form" 
	x-data="tutorForm({ id: '<?php echo esc_attr( $form_id ); ?>', mode: 'onSubmit', defaultValues: { note_text: '<?php echo esc_js( $default_value ); ?>' } })"
	x-bind="getFormBindings()"
	@submit.prevent="handleSubmit(<?php echo esc_js( $submit_handler ); ?>)($event)"
	<?php echo $show_binding ? 'x-show="' . esc_attr( $show_binding ) . '"' : ''; ?>
	x-cloak
>
	<?php
	$input = InputField::make()
		->type( InputType::TEXTAREA )
		->name( 'note_text' )
		->placeholder( __( 'Type your note here to save for later', 'tutor-pro' ) )
		->attr( 'x-bind', "register('note_text', { required: '" . esc_js( __( 'Please enter a note', 'tutor-pro' ) ) . "' })" )
		->attr( '@keydown', 'handleKeydown($event)' );

	if ( $focused_state ) {
		$input->attr( '@focus', $focused_state . ' = true' );
	}

	$input->render();
	?>
	<div 
		class="tutor-flex tutor-items-center tutor-justify-between tutor-mt-4 tutor-sm-justify-end"
		<?php echo $focused_state ? 'x-cloak :class="{ \'tutor-hidden\': !' . esc_attr( $focused_state ) . ' }"' : ''; ?>
	>
		<div class="tutor-tiny tutor-text-subdued tutor-flex tutor-items-center tutor-gap-2 tutor-sm-hidden">
			<?php SvgIcon::make()->name( Icon::COMMAND )->size( 12 )->render(); ?> 
			<?php esc_html_e( 'Cmd/Ctrl +', 'tutor-pro' ); ?>
			<?php SvgIcon::make()->name( Icon::ENTER )->size( 12 )->render(); ?> 
			<?php esc_html_e( 'Enter to Save', 'tutor-pro' ); ?>
		</div>
		<div class="tutor-flex tutor-items-center tutor-gap-2">
			<?php
			Button::make()
				->label( __( 'Cancel', 'tutor-pro' ) )
				->variant( Variant::GHOST )
				->size( Size::X_SMALL )
				->attr( 'type', 'button' )
				->attr( '@click', 'reset(); ' . $cancel_handler )
				->attr( ':disabled', $is_pending )
				->render();

			Button::make()
				->label( __( 'Save', 'tutor-pro' ) )
				->variant( Variant::PRIMARY_SOFT )
				->size( Size::X_SMALL )
				->attr( 'type', 'submit' )
				->attr( ':disabled', $is_pending )
				->attr( ':class', $is_pending . " ? 'tutor-btn-loading' : ''" )
				->render();
			?>
		</div>
	</div>
</form>
