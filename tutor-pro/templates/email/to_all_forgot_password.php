<?php
/**
 * E-mail template for forgot password.
 *
 * @package TutorPro
 * @subpackage Templates\Email
 *
 * @since 4.0.0
 */

?>
<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html charset=UTF-8" />
	<?php require TUTOR()->path . 'templates/email/email_styles.php'; ?>
</head>

<body>
	<div class="tutor-email-body">
		<div class="tutor-email-wrapper" style="background-color: #fff;">
			<?php require TUTOR()->path . 'templates/email/email_header.php'; ?>
			<div class="tutor-email-content">
				<div style="margin-bottom: 30px">
					<h6 data-source="email-heading" class="tutor-email-heading">{email_heading}</h6>
				</div>

				<div class="tutor-greetings-content">
					<div class="email-user-content" data-source="email-additional-message">{email_message}</div>
				</div>

				<div class="tutor-email-buttons">
					<a target="_blank" class="tutor-email-button" href="{reset_password_url}" data-source="email-btn-url"><?php esc_html_e( 'Reset Password', 'tutor-pro' ); ?></a>
				</div>

				<div style="margin-top: 24px;"><?php esc_html_e( 'If the button doesn’t work, copy and paste the following link into your browser:', 'tutor-pro' ); ?></div>
				<div style="margin-top: 10px;padding:10px;border:1px solid #F0F0F0;border-radius:6px;">
					<a href="{reset_password_url}">{reset_password_url}</a>
				</div>
				<div style="margin-top: 24px;">
					<?php esc_html_e( 'If you didn\'t request this change, you can safely ignore this email. Your account will remain the same. Thank you.', 'tutor-pro' ); ?>
				</div>

			</div>
		</div>
	</div>
</body>
</html>
