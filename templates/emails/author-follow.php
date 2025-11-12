<?php
/**
 * Email Template: New Author Follower
 *
 * Sent to author when someone follows them.
 *
 * Available variables:
 * - {author_name}    - Author's display name
 * - {follower_name}  - Follower's display name
 * - {profile_url}    - URL to author's profile
 * - {site_name}      - Site name
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
	<title><?php esc_html_e( 'New Follower', 'fanfiction-manager' ); ?></title>
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
			background-color: #46b450;
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
		.follower-badge {
			background-color: #f0f9f0;
			border: 2px solid #46b450;
			border-radius: 8px;
			padding: 20px;
			margin: 20px 0;
			text-align: center;
		}
		.follower-badge .follower-name {
			font-size: 24px;
			color: #46b450;
			font-weight: bold;
			margin: 10px 0;
		}
		.cta-button {
			display: inline-block;
			background-color: #46b450;
			color: #ffffff;
			text-decoration: none;
			padding: 12px 30px;
			border-radius: 4px;
			margin: 20px 0;
			font-weight: bold;
		}
		.email-footer {
			background-color: #f4f4f4;
			padding: 20px;
			text-align: center;
			font-size: 12px;
			color: #666;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php esc_html_e( 'You Have a New Follower!', 'fanfiction-manager' ); ?></h1>
		</div>

		<div class="email-body">
			<p><?php printf( esc_html__( 'Hello %s,', 'fanfiction-manager' ), '<strong>{author_name}</strong>' ); ?></p>

			<div class="follower-badge">
				<p><?php esc_html_e( 'New Follower', 'fanfiction-manager' ); ?></p>
				<div class="follower-name">{follower_name}</div>
				<p><?php esc_html_e( 'is now following you!', 'fanfiction-manager' ); ?></p>
			</div>

			<p><?php esc_html_e( 'They will receive notifications when you publish new stories and chapters.', 'fanfiction-manager' ); ?></p>

			<center>
				<a href="{profile_url}" class="cta-button"><?php esc_html_e( 'View Your Followers', 'fanfiction-manager' ); ?></a>
			</center>

			<p><?php esc_html_e( 'Keep up the great work!', 'fanfiction-manager' ); ?></p>
		</div>

		<div class="email-footer">
			<p><?php printf( esc_html__( 'This notification was sent from %s.', 'fanfiction-manager' ), '{site_name}' ); ?></p>
		</div>
	</div>
</body>
</html>
