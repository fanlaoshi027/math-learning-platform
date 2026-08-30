<?php
/**
 * Backend: Reset course progress modal template
 *
 * @author themeum
 * @link https://themeum.com
 * @package TutorLMS/Templates
 *
 * @since 4.0.0
 *
 * @var array $data array which contain required data for the modal.
 */

defined( 'ABSPATH' ) || exit;

$modal_id = $data['modal_id'] ?? 'tutor-reset-progress-modal';
?>
<div id="<?php echo esc_attr( $modal_id ); ?>" class="tutor-modal" role="dialog" aria-modal="true" aria-labelledby="tutor-reset-progress-modal-title" aria-hidden="true">
	<div class="tutor-modal-overlay"></div>
	<div class="tutor-modal-window">
		<div class="tutor-modal-content tutor-modal-content-white">
			<button class="tutor-iconic-btn tutor-modal-close-o" data-tutor-modal-close aria-label="<?php esc_attr_e( 'Close', 'tutor-pro' ); ?>">
				<span class="tutor-icon-times" aria-hidden="true"></span>
			</button>

			<div class="tutor-modal-body tutor-text-center">
				<div class="tutor-mt-48">
					<img class="tutor-d-inline-block" src="<?php echo esc_url( trailingslashit( tutor()->url ) . 'assets/images/icon-gear.svg' ); ?>" alt="<?php esc_attr_e( 'Reset Course Progress?', 'tutor-pro' ); ?>" aria-hidden="true" />
				</div>

				<div id="tutor-reset-progress-modal-title" class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-mb-12"><?php esc_html_e( 'Reset Course Progress?', 'tutor-pro' ); ?></div>
				<div class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Resetting will erase this student’s completed lessons, quizzes, and assignment records for the selected course.', 'tutor-pro' ); ?></div>

				<div class="tutor-d-flex tutor-justify-center tutor-gap-2 tutor-my-48">
					<button data-tutor-modal-close class="tutor-btn tutor-btn-outline-primary">
						<?php esc_html_e( 'Cancel', 'tutor-pro' ); ?>
					</button>
					<button data-modal-data="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" class="tutor-btn tutor-btn-primary tutor-reset-progress-action">
						<?php esc_html_e( 'Yes, Reset', 'tutor-pro' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
