<?php
/**
 * Utility Shortcodes Class
 *
 * Handles utility shortcodes for error messages, maintenance messages, etc.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Utility
 *
 * Utility shortcodes for system messages and status displays.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Utility {

	/**
	 * Register utility shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'fanfic-error-message', array( __CLASS__, 'error_message' ) );
		add_shortcode( 'fanfic-maintenance-message', array( __CLASS__, 'maintenance_message' ) );
	}

	/**
	 * Error message shortcode
	 *
	 * [fanfic-error-message]
	 *
	 * Displays error messages passed via URL parameters or session.
	 * Shows a generic "Something went wrong" message if no specific error.
	 * Includes a link back to the main page.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Error message HTML.
	 */
	public static function error_message( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'default_message' => '',
				'show_back_link'  => 'yes',
				'back_link_text'  => '',
			),
			'fanfic-error-message'
		);

		// Get error message from URL parameter
		$error_message = '';
		if ( isset( $_GET['error'] ) ) {
			$error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$error_message = self::get_error_message( $error_code );
		}

		// Get error message from URL parameter (custom message)
		if ( empty( $error_message ) && isset( $_GET['error_message'] ) ) {
			$error_message = sanitize_text_field( wp_unslash( $_GET['error_message'] ) );
		}

		// Check for session error (if WordPress sessions are available)
		if ( empty( $error_message ) && isset( $_SESSION['fanfic_error'] ) ) {
			$error_message = sanitize_text_field( $_SESSION['fanfic_error'] );
			// Clear session error after displaying
			unset( $_SESSION['fanfic_error'] );
		}

		// Use default message from attribute or generic message
		if ( empty( $error_message ) ) {
			$error_message = ! empty( $atts['default_message'] )
				? $atts['default_message']
				: esc_html__( 'Something went wrong. Please try again.', 'fanfiction-manager' );
		}

		// Build output
		$output = '<div class="fanfic-error-message fanfic-message" role="alert" aria-live="assertive">';
		$output .= '<div class="fanfic-message-icon" aria-hidden="true">&#9888;</div>';
		$output .= '<div class="fanfic-message-content">';
		$output .= '<h3 class="fanfic-message-title">' . esc_html__( 'Error', 'fanfiction-manager' ) . '</h3>';
		$output .= '<p class="fanfic-message-text">' . esc_html( $error_message ) . '</p>';

		// Add back link if enabled
		if ( 'yes' === $atts['show_back_link'] ) {
			$back_link_text = ! empty( $atts['back_link_text'] )
				? $atts['back_link_text']
				: esc_html__( 'Go to Main Page', 'fanfiction-manager' );

			$main_page_url = self::get_main_page_url();

			$output .= sprintf(
				'<p class="fanfic-message-action"><a href="%s" class="fanfic-btn fanfic-btn-primary">%s</a></p>',
				esc_url( $main_page_url ),
				esc_html( $back_link_text )
			);
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Maintenance message shortcode
	 *
	 * [fanfic-maintenance-message]
	 *
	 * Displays a maintenance mode message.
	 * Shows estimated time if available in settings.
	 * Includes admin contact information.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Maintenance message HTML.
	 */
	public static function maintenance_message( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'show_estimate'     => 'yes',
				'show_contact'      => 'yes',
				'custom_message'    => '',
			),
			'fanfic-maintenance-message'
		);

		// Check if maintenance mode is actually enabled
		$maintenance_mode = false;
		if ( class_exists( 'Fanfic_Settings' ) ) {
			$maintenance_mode = Fanfic_Settings::get_setting( 'maintenance_mode', false );
		}

		// Only show if maintenance mode is enabled or if we're previewing
		$is_admin = current_user_can( 'manage_options' );
		if ( ! $maintenance_mode && ! $is_admin ) {
			return '';
		}

		// Get maintenance details
		$estimated_time = get_option( 'fanfic_maintenance_estimated_time', '' );
		$maintenance_reason = get_option( 'fanfic_maintenance_reason', '' );

		// Default message
		$main_message = ! empty( $atts['custom_message'] )
			? $atts['custom_message']
			: esc_html__( 'We are currently performing scheduled maintenance to improve your experience.', 'fanfiction-manager' );

		// Build output
		$output = '<div class="fanfic-maintenance-message fanfic-message" role="alert" aria-live="polite">';
		$output .= '<div class="fanfic-message-icon" aria-hidden="true">&#128736;</div>';
		$output .= '<div class="fanfic-message-content">';
		$output .= '<h3 class="fanfic-message-title">' . esc_html__( 'Site Maintenance', 'fanfiction-manager' ) . '</h3>';
		$output .= '<p class="fanfic-message-text">' . esc_html( $main_message ) . '</p>';

		// Add maintenance reason if available
		if ( ! empty( $maintenance_reason ) ) {
			$output .= '<p class="fanfic-message-reason"><strong>' . esc_html__( 'Reason:', 'fanfiction-manager' ) . '</strong> ' . esc_html( $maintenance_reason ) . '</p>';
		}

		// Add estimated time if enabled and available
		if ( 'yes' === $atts['show_estimate'] && ! empty( $estimated_time ) ) {
			$output .= sprintf(
				'<p class="fanfic-message-estimate"><strong>%s</strong> %s</p>',
				esc_html__( 'Estimated completion:', 'fanfiction-manager' ),
				esc_html( $estimated_time )
			);
		}

		// Add contact information if enabled
		if ( 'yes' === $atts['show_contact'] ) {
			$admin_email = get_option( 'admin_email' );
			$site_name = get_bloginfo( 'name' );

			if ( ! empty( $admin_email ) ) {
				$output .= sprintf(
					'<p class="fanfic-message-contact">%s <a href="mailto:%s">%s</a></p>',
					esc_html__( 'If you have urgent questions, please contact:', 'fanfiction-manager' ),
					esc_attr( $admin_email ),
					esc_html( $admin_email )
				);
			}
		}

		// Add admin preview notice
		if ( $is_admin && $maintenance_mode ) {
			$output .= '<p class="fanfic-message-admin-notice" style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 0.9em;">';
			$output .= '<strong>' . esc_html__( 'Admin Notice:', 'fanfiction-manager' ) . '</strong> ';
			$output .= esc_html__( 'Maintenance mode is currently active. Regular users cannot access the site. Turn off maintenance mode in Settings > General.', 'fanfiction-manager' );
			$output .= '</p>';
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Get error message by error code
	 *
	 * @since 1.0.0
	 * @param string $error_code Error code.
	 * @return string Error message.
	 */
	private static function get_error_message( $error_code ) {
		$error_messages = array(
			'invalid_story'      => __( 'The requested story could not be found.', 'fanfiction-manager' ),
			'invalid_chapter'    => __( 'The requested chapter could not be found.', 'fanfiction-manager' ),
			'permission_denied'  => __( 'You do not have permission to access this page.', 'fanfiction-manager' ),
			'not_logged_in'      => __( 'You must be logged in to access this page.', 'fanfiction-manager' ),
			'invalid_user'       => __( 'The requested user profile could not be found.', 'fanfiction-manager' ),
			'validation_failed'  => __( 'The submitted data failed validation. Please check your input and try again.', 'fanfiction-manager' ),
			'save_failed'        => __( 'Failed to save your changes. Please try again.', 'fanfiction-manager' ),
			'delete_failed'      => __( 'Failed to delete the item. Please try again.', 'fanfiction-manager' ),
			'suspended'          => __( 'Your account has been suspended. Please contact the site administrator for more information.', 'fanfiction-manager' ),
			'banned'             => __( 'Your account has been banned. If you believe this is an error, please contact the site administrator.', 'fanfiction-manager' ),
			'invalid_nonce'      => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
			'session_expired'    => __( 'Your session has expired. Please log in again.', 'fanfiction-manager' ),
			'database_error'     => __( 'A database error occurred. Please try again later or contact the site administrator.', 'fanfiction-manager' ),
			'file_upload_failed' => __( 'File upload failed. Please check the file size and format, then try again.', 'fanfiction-manager' ),
			'invalid_request'    => __( 'Invalid request. Please check your input and try again.', 'fanfiction-manager' ),
			'rate_limit'         => __( 'You have exceeded the rate limit. Please wait a few minutes and try again.', 'fanfiction-manager' ),
			'maintenance'        => __( 'The site is currently under maintenance. Please try again later.', 'fanfiction-manager' ),
		);

		return isset( $error_messages[ $error_code ] )
			? $error_messages[ $error_code ]
			: '';
	}

	/**
	 * Get main page URL
	 *
	 * @since 1.0.0
	 * @return string Main page URL.
	 */
	private static function get_main_page_url() {
		// Try to get the configured main page URL
		if ( class_exists( 'Fanfic_Templates' ) && method_exists( 'Fanfic_Templates', 'get_page_url' ) ) {
			$url = Fanfic_Templates::get_page_url( 'main' );
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		// Fall back to archive page
		if ( class_exists( 'Fanfic_Templates' ) && method_exists( 'Fanfic_Templates', 'get_page_url' ) ) {
			$url = Fanfic_Templates::get_page_url( 'archive' );
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		// Final fallback to story post type archive
		$archive_url = get_post_type_archive_link( 'fanfiction_story' );
		if ( ! empty( $archive_url ) ) {
			return $archive_url;
		}

		// Ultimate fallback to home page
		return home_url( '/' );
	}
}
