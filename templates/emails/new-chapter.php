<?php
/**
 * Email Template: New Chapter Published
 *
 * Sent when author publishes a new chapter in a followed story.
 *
 * Available variables:
 * - {display_name}   - Recipient's display name
 * - {story_title}    - Story title
 * - {author_name}    - Author's display name
 * - {chapter_number} - Chapter number
 * - {chapter_title}  - Chapter title
 * - {chapter_url}    - URL to read the chapter
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
	<title><?php esc_html_e( 'New Chapter Published', 'fanfiction-manager' ); ?></title>
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
			background-color: #0073aa;
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
		.email-body h2 {
			color: #0073aa;
			font-size: 20px;
			margin-top: 0;
		}
		.chapter-info {
			background-color: #f9f9f9;
			border-left: 4px solid #0073aa;
			padding: 15px;
			margin: 20px 0;
		}
		.chapter-info strong {
			color: #0073aa;
		}
		.cta-button {
			display: inline-block;
			background-color: #0073aa;
			color: #ffffff;
			text-decoration: none;
			padding: 12px 30px;
			border-radius: 4px;
			margin: 20px 0;
			font-weight: bold;
		}
		.cta-button:hover {
			background-color: #005a87;
		}
		.email-footer {
			background-color: #f4f4f4;
			padding: 20px;
			text-align: center;
			font-size: 12px;
			color: #666;
		}
		.email-footer a {
			color: #0073aa;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php esc_html_e( 'New Chapter Published!', 'fanfiction-manager' ); ?></h1>
		</div>

		<div class="email-body">
			<p><?php printf( esc_html__( 'Hello %s,', 'fanfiction-manager' ), '<strong>{display_name}</strong>' ); ?></p>

			<p><?php printf( esc_html__( 'Great news! A new chapter has been published in "%s" by %s.', 'fanfiction-manager' ), '<strong>{story_title}</strong>', '{author_name}' ); ?></p>

			<div class="chapter-info">
				<p><strong><?php esc_html_e( 'Chapter:', 'fanfiction-manager' ); ?></strong> {chapter_number}</p>
				<p><strong><?php esc_html_e( 'Title:', 'fanfiction-manager' ); ?></strong> {chapter_title}</p>
			</div>

			<p><?php esc_html_e( 'Click the button below to start reading:', 'fanfiction-manager' ); ?></p>

			<center>
				<a href="{chapter_url}" class="cta-button"><?php esc_html_e( 'Read Now', 'fanfiction-manager' ); ?></a>
			</center>

			<p><?php esc_html_e( 'Thank you for being part of our community!', 'fanfiction-manager' ); ?></p>
		</div>

		<div class="email-footer">
			<p><?php printf( esc_html__( 'You are receiving this email because you are following "%s" on %s.', 'fanfiction-manager' ), '{story_title}', '{site_name}' ); ?></p>
			<p><a href="{unsubscribe_url}"><?php esc_html_e( 'Unsubscribe from this story', 'fanfiction-manager' ); ?></a></p>
		</div>
	</div>
</body>
</html>
