<?php
/**
 * Email Templates System Class
 *
 * Handles email template management with variable substitution.
 *
 * @package FanfictionManager
 * @subpackage Notifications
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Email_Templates
 *
 * Manages email templates for notifications.
 *
 * @since 1.0.0
 */
class Fanfic_Email_Templates {

	/**
	 * Option name for storing email templates
	 */
	const OPTION_NAME = 'fanfic_email_templates';

	/**
	 * Template types
	 */
	const TYPE_NEW_COMMENT = 'new_comment';
	const TYPE_NEW_CHAPTER = 'new_chapter';
	const TYPE_NEW_STORY = 'new_story';

	/**
	 * Initialize the email templates class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Initialization if needed
	}

	/**
	 * Get email template for notification type
	 *
	 * @since 1.0.0
	 * @param string $type Template type.
	 * @return array Template array with 'subject' and 'body' keys.
	 */
	public static function get_template( $type ) {
		$templates = get_option( self::OPTION_NAME, array() );

		if ( isset( $templates[ $type ] ) ) {
			return $templates[ $type ];
		}

		// Return default if not found
		return self::get_default_template( $type );
	}

	/**
	 * Save email template
	 *
	 * @since 1.0.0
	 * @param string $type         Template type.
	 * @param string $subject      Email subject line.
	 * @param string $template_html Email HTML body.
	 * @return bool True on success, false on failure.
	 */
	public static function save_template( $type, $subject, $template_html ) {
		$templates = get_option( self::OPTION_NAME, array() );

		$templates[ $type ] = array(
			'subject' => sanitize_text_field( $subject ),
			'body'    => wp_kses_post( $template_html ),
		);

		return update_option( self::OPTION_NAME, $templates );
	}

	/**
	 * Get default email template
	 *
	 * @since 1.0.0
	 * @param string $type Template type.
	 * @return array Default template array with 'subject' and 'body' keys.
	 */
	public static function get_default_template( $type ) {
		$defaults = array(
			self::TYPE_NEW_COMMENT => array(
				'subject' => __( 'New comment on "{{story_title}}"', 'fanfiction-manager' ),
				'body'    => self::get_default_comment_template(),
			),
			self::TYPE_NEW_CHAPTER => array(
				'subject' => __( 'New chapter: "{{chapter_title}}" by {{author_name}}', 'fanfiction-manager' ),
				'body'    => self::get_default_new_chapter_template(),
			),
			self::TYPE_NEW_STORY => array(
				'subject' => __( 'New story: "{{story_title}}" by {{author_name}}', 'fanfiction-manager' ),
				'body'    => self::get_default_new_story_template(),
			),
		);

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : array( 'subject' => '', 'body' => '' );
	}

	/**
	 * Render email template with variable substitution
	 *
	 * @since 1.0.0
	 * @param string $type      Template type.
	 * @param array  $variables Associative array of variables to substitute.
	 * @return array Rendered template with 'subject' and 'body' keys.
	 */
	public static function render_template( $type, $variables = array() ) {
		$template = self::get_template( $type );

		// Default variables
		$defaults = array(
			'site_name'      => get_bloginfo( 'name' ),
			'site_url'       => home_url(),
			'user_name'      => '',
			'author_name'    => '',
			'story_title'    => '',
			'story_url'      => '',
			'chapter_title'  => '',
			'chapter_url'    => '',
			'comment_text'   => '',
			'settings_url'   => '', // Will be set below
		);

		$variables = array_merge( $defaults, $variables );

		// Add settings URL
		$dashboard_page_id = get_option( 'fanfic_page_dashboard' );
		if ( $dashboard_page_id ) {
			$variables['settings_url'] = get_permalink( $dashboard_page_id ) . '?tab=notifications';
		}

		// Replace variables in subject
		$subject = $template['subject'];
		foreach ( $variables as $key => $value ) {
			$subject = str_replace( '{{' . $key . '}}', $value, $subject );
		}

		// Replace variables in body
		$body = $template['body'];
		foreach ( $variables as $key => $value ) {
			$body = str_replace( '{{' . $key . '}}', $value, $body );
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Get available variables for template type
	 *
	 * @since 1.0.0
	 * @param string $type Template type.
	 * @return array Available variables for template type.
	 */
	public static function get_available_variables( $type ) {
		$common = array(
			'site_name'    => __( 'Site name', 'fanfiction-manager' ),
			'site_url'     => __( 'Site URL', 'fanfiction-manager' ),
			'user_name'    => __( 'Recipient\'s display name', 'fanfiction-manager' ),
			'settings_url' => __( 'Notification settings URL', 'fanfiction-manager' ),
		);

		$type_specific = array(
			self::TYPE_NEW_COMMENT => array(
				'author_name'   => __( 'Comment author name', 'fanfiction-manager' ),
				'story_title'   => __( 'Story title', 'fanfiction-manager' ),
				'story_url'     => __( 'Story URL', 'fanfiction-manager' ),
				'chapter_title' => __( 'Chapter title', 'fanfiction-manager' ),
				'chapter_url'   => __( 'Chapter URL', 'fanfiction-manager' ),
				'comment_text'  => __( 'Comment text (truncated)', 'fanfiction-manager' ),
			),
			self::TYPE_NEW_CHAPTER => array(
				'author_name'   => __( 'Story author name', 'fanfiction-manager' ),
				'story_title'   => __( 'Story title', 'fanfiction-manager' ),
				'story_url'     => __( 'Story URL', 'fanfiction-manager' ),
				'chapter_title' => __( 'Chapter title', 'fanfiction-manager' ),
				'chapter_url'   => __( 'Chapter URL', 'fanfiction-manager' ),
			),
			self::TYPE_NEW_STORY => array(
				'author_name' => __( 'Story author name', 'fanfiction-manager' ),
				'story_title' => __( 'Story title', 'fanfiction-manager' ),
				'story_url'   => __( 'Story URL', 'fanfiction-manager' ),
			),
		);

		$variables = $common;
		if ( isset( $type_specific[ $type ] ) ) {
			$variables = array_merge( $variables, $type_specific[ $type ] );
		}

		return $variables;
	}

	/**
	 * Reset templates to defaults
	 *
	 * @since 1.0.0
	 * @param string|null $type Specific template type to reset, or null for all.
	 * @return bool True on success, false on failure.
	 */
	public static function reset_to_defaults( $type = null ) {
		if ( null === $type ) {
			// Reset all templates
			return delete_option( self::OPTION_NAME );
		}

		// Reset specific template
		$templates = get_option( self::OPTION_NAME, array() );
		unset( $templates[ $type ] );

		return update_option( self::OPTION_NAME, $templates );
	}

	/**
	 * Generate plain text version from HTML
	 *
	 * @since 1.0.0
	 * @param string $html HTML content.
	 * @return string Plain text version.
	 */
	public static function generate_plain_text( $html ) {
		// Strip HTML tags
		$text = wp_strip_all_tags( $html );

		// Convert HTML entities
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// Remove multiple line breaks
		$text = preg_replace( '/\n\s*\n\s*\n/', "\n\n", $text );

		// Trim whitespace
		$text = trim( $text );

		return $text;
	}

	/**
	 * Default template for new comment notification
	 *
	 * @since 1.0.0
	 * @return string HTML template.
	 */
	private static function get_default_comment_template() {
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'New Comment', 'fanfiction-manager' ); ?></title>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
		.container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
		.content { padding: 30px; }
		.footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
		.button { display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff !important; text-decoration: none; border-radius: 4px; margin: 10px 0; }
		.comment-box { background: #f8f8f8; padding: 15px; border-left: 4px solid #0073aa; margin: 15px 0; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php esc_html_e( 'New Comment', 'fanfiction-manager' ); ?></h1>
		</div>
		<div class="content">
			<p><?php esc_html_e( 'Hello {{user_name}},', 'fanfiction-manager' ); ?></p>
			<p><?php esc_html_e( '{{author_name}} left a new comment on your story:', 'fanfiction-manager' ); ?></p>
			<h2><a href="{{story_url}}" style="color: #0073aa;">{{story_title}}</a></h2>
			<div class="comment-box">
				<strong><?php esc_html_e( 'Comment:', 'fanfiction-manager' ); ?></strong><br>
				{{comment_text}}
			</div>
			<p style="text-align: center;">
				<a href="{{chapter_url}}" class="button"><?php esc_html_e( 'Read Full Comment', 'fanfiction-manager' ); ?></a>
			</p>
		</div>
		<div class="footer">
			<p><?php esc_html_e( 'You received this email because you have notifications enabled for {{site_name}}.', 'fanfiction-manager' ); ?></p>
			<p><a href="{{settings_url}}" style="color: #0073aa;"><?php esc_html_e( 'Manage your notification preferences', 'fanfiction-manager' ); ?></a></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Default template for new chapter notification
	 *
	 * @since 1.0.0
	 * @return string HTML template.
	 */
	private static function get_default_new_chapter_template() {
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'New Chapter', 'fanfiction-manager' ); ?></title>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
		.container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
		.content { padding: 30px; }
		.footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
		.button { display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff !important; text-decoration: none; border-radius: 4px; margin: 10px 0; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php esc_html_e( 'New Chapter Published', 'fanfiction-manager' ); ?></h1>
		</div>
		<div class="content">
			<p><?php esc_html_e( 'Hello {{user_name}},', 'fanfiction-manager' ); ?></p>
			<p><?php esc_html_e( '{{author_name}} just published a new chapter:', 'fanfiction-manager' ); ?></p>
			<h2><a href="{{chapter_url}}" style="color: #0073aa;">{{chapter_title}}</a></h2>
			<p><?php esc_html_e( 'From the story:', 'fanfiction-manager' ); ?> <strong><a href="{{story_url}}" style="color: #0073aa;">{{story_title}}</a></strong></p>
			<p style="text-align: center;">
				<a href="{{chapter_url}}" class="button"><?php esc_html_e( 'Read Now', 'fanfiction-manager' ); ?></a>
			</p>
		</div>
		<div class="footer">
			<p><?php esc_html_e( 'You received this email because you subscribed to updates from {{author_name}} on {{site_name}}.', 'fanfiction-manager' ); ?></p>
			<p><a href="{{settings_url}}" style="color: #0073aa;"><?php esc_html_e( 'Manage your notification preferences', 'fanfiction-manager' ); ?></a></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Default template for new story notification
	 *
	 * @since 1.0.0
	 * @return string HTML template.
	 */
	private static function get_default_new_story_template() {
		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'New Story', 'fanfiction-manager' ); ?></title>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
		.container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
		.content { padding: 30px; }
		.footer { background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; }
		.button { display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff !important; text-decoration: none; border-radius: 4px; margin: 10px 0; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php esc_html_e( 'New Story Published', 'fanfiction-manager' ); ?></h1>
		</div>
		<div class="content">
			<p><?php esc_html_e( 'Hello {{user_name}},', 'fanfiction-manager' ); ?></p>
			<p><?php esc_html_e( '{{author_name}} just published a new story:', 'fanfiction-manager' ); ?></p>
			<h2><a href="{{story_url}}" style="color: #0073aa;">{{story_title}}</a></h2>
			<p style="text-align: center;">
				<a href="{{story_url}}" class="button"><?php esc_html_e( 'Start Reading', 'fanfiction-manager' ); ?></a>
			</p>
		</div>
		<div class="footer">
			<p><?php esc_html_e( 'You received this email because you subscribed to updates from {{author_name}} on {{site_name}}.', 'fanfiction-manager' ); ?></p>
			<p><a href="{{settings_url}}" style="color: #0073aa;"><?php esc_html_e( 'Manage your notification preferences', 'fanfiction-manager' ); ?></a></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
