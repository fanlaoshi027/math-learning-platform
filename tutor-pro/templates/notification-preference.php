<?php
/**
 * Notifications settings
 *
 * @package Tutor\Templates
 * @author Themeum <support@themeum.com>
 * @link https://www.themeum.com/
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

use TUTOR\Icon;
use Tutor\Components\SvgIcon;
use Tutor\Components\InputField;
use Tutor\Components\Constants\InputType;
use Tutor\Components\Constants\Size;
use Tutor\Components\EmptyState;
use TUTOR_PRO\NotificationPreference;

$preferences = NotificationPreference::prepare_notification_preferences_data( get_current_user_id() );
$disable_all = 'on' === $preferences['disable_all']['value'];

?>
<section class="tutor-profile-notification">
	<h5 class="tutor-h5 tutor-md-hidden tutor-my-none">
		<?php esc_html_e( 'Notifications', 'tutor-pro' ); ?>
	</h5>
	<form
		id="<?php echo esc_attr( $form_id ); ?>"
		x-data='
			tutorForm({
				id: "<?php echo esc_attr( $form_id ); ?>",
				mode: "onChange",
				shouldFocusError: true,
			})
		'
		x-bind="getFormBindings()"
		@submit="handleSubmit((data) => { handleUpdateNotification?.mutate({...data, formId: '<?php echo esc_attr( $form_id ); ?>'}); })($event)"
	>
		<?php
		if ( count( $preferences['email'] ) ) :
			foreach ( $preferences['email'] as $trigger_group_key => $trigger_group ) :
				?>
					<div
						x-data="{ expanded: <?php echo ! $disable_all ? 'true' : 'false'; ?> }"
						class="tutor-profile-notification-card tutor-card-rounded-2xl tutor-mt-4">
						<div class="tutor-flex tutor-items-center tutor-justify-between tutor-gap-8 tutor-p-6">
							<div class="tutor-flex tutor-items-center tutor-gap-5">
								<?php SvgIcon::make()->name( Icon::NOTIFICATION_2 )->size( 20 )->render(); ?>
								<?php if ( tutor_utils()->count( $preferences['email'] ) ) : ?>
								<div>
									<div class="tutor-text-small tutor-font-medium tutor-text-primary">
										<?php echo esc_html__( 'Email Notifications', 'tutor-pro' ); ?>
									</div>
									<div class="tutor-text-small tutor-text-secondary">
										<?php esc_html_e( 'Configure custom notifications settings for Email.', 'tutor-pro' ); ?>
									</div>
								</div>
								<?php endif; ?>
							</div>
							<div class="tutor-flex tutor-gap-4">
								<div 
									class="tutor-profile-notification-toggle tutor-text-subdued"
									:class="{
										'is-expanded': expanded,
										'is-disabled': getValue('disable_all')
									}"
									@click="getValue('disable_all') && (expanded = !expanded)"
								>
									<?php SvgIcon::make()->name( Icon::CHEVRON_DOWN )->size( 20 )->render(); ?>
								</div>
								<?php
								InputField::make()
									->type( InputType::SWITCH )
									->name( 'disable_all' )
									->checked( ! $disable_all ? true : false )
									->attr( 'x-bind', "register('disable_all')" )
									->attr( '@click', "!getValue('disable_all') ? (expanded = true) : (expanded = false)" )
									->size( Size::SM )
									->render();
								?>
							</div>
						</div>
						<div class="tutor-profile-notification-content" x-show="expanded" x-collapse.duration.200ms>
							<?php
							$group_labels   = NotificationPreference::get_student_email_group_labels();
							$rendered_count = 0;
							foreach ( $group_labels as $group_key => $group_label ) {
								$group_triggers = $trigger_group[ $group_key ] ?? array();
								if ( ! count( $group_triggers ) ) {
									continue;
								}
								$trigger_field_keys = array_map(
									static function ( $trigger_key ) use ( $trigger_group_key ) {
										return "{$trigger_group_key}__{$trigger_key}";
									},
									array_keys( $group_triggers )
								);
								?>
								<div
									x-data='
									<?php
									echo wp_json_encode(
										array(
											'triggerKeys' => $trigger_field_keys,
											'selectAllText' => __( 'Select all', 'tutor-pro' ),
											'deselectAllText' => __( 'Deselect all', 'tutor-pro' ),
										)
									);
									?>
									'
									class="<?php echo 0 === $rendered_count ? 'tutor-pb-5' : 'tutor-pt-6 tutor-pb-5'; ?> tutor-flex tutor-items-center tutor-justify-between tutor-gap-4 tutor-text-subdued tutor-text-medium"
								>
									<span><?php echo esc_html( $group_label ); ?></span>
									<button
										type="button"
										class="tutor-btn tutor-btn-link tutor-text-small tutor-text-brand tutor-p-0 tutor-text-medium"
										x-text="triggerKeys.every((name) => Boolean(getValue(name))) ? deselectAllText : selectAllText"
										@click="const shouldEnable = !triggerKeys.every((name) => Boolean(getValue(name))); triggerKeys.forEach((name) => setValue(name, shouldEnable, { shouldValidate: true, shouldDirty: true }));"
									>
									</button>
								</div>
								<?php
								foreach ( $group_triggers as $key => $item ) {
									InputField::make()
									->type( InputType::CHECKBOX )
									->name( "{$trigger_group_key}_{$key}" )
									->checked( 'on' === $item['value'] ? true : false )
									->label( $item['label'] )
									->value( $item['value'] )
									->attr( 'x-bind', "register('{$trigger_group_key}__{$key}')" )
									->size( Size::SM )
									->render();
								}

								++$rendered_count;
							}
							?>
						</div>
					</div>
				<?php
				endforeach;
			else :
				EmptyState::make()
				->title( __( 'No Notification Preferences Found!', 'tutor-pro' ) )
				->render();
		endif;
			?>
	</form>
</section>
