<?php
/**
 * Email Template: Email Verification
 *
 * Sent to verify email subscription.
 *
 * Available variables:
 * - {target_name}     - Story title or author name
 * - {subscription_type} - 'story' or 'author'
 * - {verification_url} - URL to verify subscription
 * - {site_name}       - Site name
 *
 * @package FanfictionManager
 * @subpackage EmailTemplates
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Verify Your Email Subscription', 'fanfiction-manager' ); ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			line-height: 1.6;
			color: #333;
			background-color: #f4f4f4;
			margin: 0;
			padding: 0;
		}
		.email-container {
			max-width: 600px;
			margin: 20px auto;
			background-color: #ffffff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		}
		.email-header {
			background-color: #3498db;
			color: #ffffff;
			padding: 20px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
		}
		.email-body {
			padding: 30px;
		}
		.verification-box {
			background-color: #e8f4fd;
			border: 2px solid #3498db;
			border-radius: 8px;
			padding: 20px;
			margin: 20px 0;
			text-align: center;
		}
		.cta-button {
			display: inline-block;
			background-color: #3498db;
			color: #ffffff;
			text-decoration: none;
			padding: 15px 40px;
			border-radius: 4px;
			margin: 20px 0;
			font-weight: bold;
			font-size: 16px;
		}
		.cta-button:hover {
			background-color: #2980b9;
		}
		.email-footer {
			background-color: #f4f4f4;
			padding: 20px;
			text-align: center;
			font-size: 12px;
			color: #666;
		}
		.security-note {
			background-color: #fff9e6;
			border-left: 4px solid #f39c12;
			padding: 10px 15px;
			margin: 20px 0;
			font-size: 14px;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php esc_html_e( 'Verify Your Email Subscription', 'fanfiction-manager' ); ?></h1>
		</div>

		<div class="email-body">
			<p><?php esc_html_e( 'Hello,', 'fanfiction-manager' ); ?></p>

			<p><?php esc_html_e( 'You requested to subscribe to email notifications for:', 'fanfiction-manager' ); ?></p>

			<div class="verification-box">
				<h2 style="color: #3498db; margin: 0;">{target_name}</h2>
				<p style="margin: 10px 0 0 0; color: #666;"><?php esc_html_e( 'Type:', 'fanfiction-manager' ); ?> {subscription_type}</p>
			</div>

			<p><?php esc_html_e( 'Please click the button below to verify your email address and activate your subscription:', 'fanfiction-manager' ); ?></p>

			<center>
				<a href="{verification_url}" class="cta-button"><?php esc_html_e( 'Verify Email Address', 'fanfiction-manager' ); ?></a>
			</center>

			<div class="security-note">
				<strong><?php esc_html_e( 'Security Note:', 'fanfiction-manager' ); ?></strong>
				<?php esc_html_e( 'If you did not request this subscription, you can safely ignore this email. Your email address will not be subscribed without verification.', 'fanfiction-manager' ); ?>
			</div>

			<p><?php esc_html_e( 'After verification, you will receive email notifications for new updates.', 'fanfiction-manager' ); ?></p>
		</div>

		<div class="email-footer">
			<p><?php printf( esc_html__( 'This verification email was sent from %s.', 'fanfiction-manager' ), '{site_name}' ); ?></p>
			<p><?php esc_html_e( 'This link will expire in 7 days.', 'fanfiction-manager' ); ?></p>
		</div>
	</div>
</body>
</html>
