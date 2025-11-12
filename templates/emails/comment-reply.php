<?php
/**
 * Email Template: Comment Reply
 *
 * Sent when someone replies to a user's comment.
 *
 * Available variables:
 * - {recipient_name} - Recipient's display name
 * - {replier_name}   - Person who replied
 * - {comment_text}   - Reply comment text (excerpt)
 * - {post_title}     - Story/chapter title
 * - {comment_url}    - URL to view the comment thread
 * - {unsubscribe_url} - Unsubscribe link
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
	<title><?php esc_html_e( 'New Reply to Your Comment', 'fanfiction-manager' ); ?></title>
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
			background-color: #e67e22;
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
		.comment-box {
			background-color: #fef5e7;
			border-left: 4px solid #e67e22;
			padding: 15px;
			margin: 20px 0;
			font-style: italic;
		}
		.comment-box .author {
			font-weight: bold;
			color: #e67e22;
			font-style: normal;
		}
		.cta-button {
			display: inline-block;
			background-color: #e67e22;
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
		.email-footer a {
			color: #e67e22;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php esc_html_e( 'New Reply to Your Comment', 'fanfiction-manager' ); ?></h1>
		</div>

		<div class="email-body">
			<p><?php printf( esc_html__( 'Hello %s,', 'fanfiction-manager' ), '<strong>{recipient_name}</strong>' ); ?></p>

			<p><?php printf( esc_html__( '%s replied to your comment on "%s":', 'fanfiction-manager' ), '<strong>{replier_name}</strong>', '{post_title}' ); ?></p>

			<div class="comment-box">
				<p class="author">{replier_name}:</p>
				<p>{comment_text}</p>
			</div>

			<center>
				<a href="{comment_url}" class="cta-button"><?php esc_html_e( 'View Discussion', 'fanfiction-manager' ); ?></a>
			</center>

			<p><?php esc_html_e( 'Join the conversation and continue the discussion!', 'fanfiction-manager' ); ?></p>
		</div>

		<div class="email-footer">
			<p><?php printf( esc_html__( 'You are receiving this email because you commented on "%s" at %s.', 'fanfiction-manager' ), '{post_title}', '{site_name}' ); ?></p>
			<p><a href="{unsubscribe_url}"><?php esc_html_e( 'Unsubscribe from comment notifications', 'fanfiction-manager' ); ?></a></p>
		</div>
	</div>
</body>
</html>
