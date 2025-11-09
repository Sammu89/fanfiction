<?php
/**
 * Profile Handler Class
 *
 * Handles all user profile-related operations including editing and updating.
 *
 * @package FanfictionManager
 * @subpackage Handlers
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Profile_Handler
 *
 * Profile management operations.
 *
 * @since 1.0.0
 */
class Fanfic_Profile_Handler {

	/**
	 * Register profile handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Register form submission handlers
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_profile_submission' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_edit_profile', array( __CLASS__, 'handle_edit_profile_submission' ) );
	}

	/**
	 * Handle edit profile form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_edit_profile_submission() {
		if ( ! isset( $_POST['fanfic_edit_profile_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_profile_nonce'] ) ) {
			error_log( 'Nonce not set' );
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				set_transient( 'fanfic_profile_errors_' . $current_user->ID, array( __( 'Security verification failed (missing nonce). Please try again.', 'fanfiction-manager' ) ), 60 );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		if ( ! wp_verify_nonce( $_POST['fanfic_edit_profile_nonce'], 'fanfic_edit_profile_action' ) ) {
			error_log( 'Nonce verification failed' );
			// Nonce verification failed - add error
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				set_transient( 'fanfic_profile_errors_' . $current_user->ID, array( __( 'Security verification failed. Please try again.', 'fanfiction-manager' ) ), 60 );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$current_user = wp_get_current_user();
		$errors = array();

		// Get and sanitize form data
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( trim( $_POST['display_name'] ) ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( trim( $_POST['user_email'] ) ) : '';
		$user_url = isset( $_POST['user_url'] ) ? esc_url_raw( trim( $_POST['user_url'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
		$avatar_url = isset( $_POST['fanfic_avatar_url'] ) ? esc_url_raw( trim( $_POST['fanfic_avatar_url'] ) ) : '';

		// Validate required fields
		if ( empty( $display_name ) ) {
			$errors[] = __( 'Display name is required.', 'fanfiction-manager' );
		}

		if ( empty( $user_email ) ) {
			$errors[] = __( 'Email address is required.', 'fanfiction-manager' );
		}

		// Validate email format
		if ( ! empty( $user_email ) && ! is_email( $user_email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'fanfiction-manager' );
		}

		// Check if email is already in use by another user
		if ( ! empty( $user_email ) && $user_email !== $current_user->user_email ) {
			$email_exists = email_exists( $user_email );
			if ( $email_exists && $email_exists !== $current_user->ID ) {
				$errors[] = __( 'This email address is already in use by another user.', 'fanfiction-manager' );
			}
		}

		// Validate bio length
		if ( ! empty( $description ) && strlen( $description ) > 5000 ) {
			$errors[] = __( 'Biographical info must be less than 5000 characters.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			error_log( 'Validation errors found: ' . implode( ', ', $errors ) );
			set_transient( 'fanfic_profile_errors_' . $current_user->ID, $errors, 60 );

			// Check if AJAX request
			$is_ajax = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

			if ( $is_ajax ) {
				wp_send_json_error(
					array(
						'message' => implode( ' ', $errors ),
					)
				);
			} else {
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
		}

		// Update user using native WordPress function
		$user_data = array(
			'ID'           => $current_user->ID,
			'display_name' => $display_name,  // Native WP field
			'user_email'   => $user_email,    // Native WP field
			'user_url'     => $user_url,      // Native WP field
			'description'  => $description,   // Native WP field (bio)
		);

		// Log the data we're trying to save
		error_log( 'Attempting to update user with data: ' . print_r( $user_data, true ) );

		// Update user profile
		$updated = wp_update_user( $user_data );

		// Log the result
		error_log( 'Update result: ' . print_r( $updated, true ) );

		if ( is_wp_error( $updated ) ) {
			error_log( 'Update failed with error: ' . $updated->get_error_message() );
			$errors[] = $updated->get_error_message();
			set_transient( 'fanfic_profile_errors_' . $current_user->ID, $errors, 60 );

			// Check if AJAX request
			$is_ajax = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

			if ( $is_ajax ) {
				wp_send_json_error(
					array(
						'message' => $updated->get_error_message(),
					)
				);
			} else {
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
		}

		error_log( 'User updated successfully. User ID: ' . $updated );

		// Update custom avatar URL (non-native field)
		if ( ! empty( $avatar_url ) ) {
			update_user_meta( $current_user->ID, '_fanfic_avatar_url', $avatar_url );
		} else {
			delete_user_meta( $current_user->ID, '_fanfic_avatar_url' );
		}

		// Clear any cached user data
		clean_user_cache( $current_user->ID );

		// Check if this is an AJAX request
		$is_ajax = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

		if ( $is_ajax ) {
			// Return JSON response for AJAX
			error_log( 'AJAX request detected - returning JSON' );
			wp_send_json_success(
				array(
					'message' => __( 'Profile updated successfully!', 'fanfiction-manager' ),
				)
			);
		} else {
			// Regular form submission - redirect
			$redirect_url = add_query_arg( 'updated', 'success', wp_get_referer() ? wp_get_referer() : home_url() );
			error_log( 'Regular submission - Redirecting to: ' . $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * AJAX handler for editing user profile
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_edit_profile() {
		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_profile_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_profile_nonce'], 'fanfic_edit_profile_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'fanfiction-manager' )
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in to edit your profile.', 'fanfiction-manager' )
			) );
		}

		$current_user = wp_get_current_user();
		$errors = array();

		// Get and sanitize form data
		$display_name = isset( $_POST['fanfic_display_name'] ) ? sanitize_text_field( $_POST['fanfic_display_name'] ) : '';
		$bio = isset( $_POST['fanfic_bio'] ) ? wp_kses_post( $_POST['fanfic_bio'] ) : '';
		$website = isset( $_POST['fanfic_website'] ) ? esc_url_raw( $_POST['fanfic_website'] ) : '';

		// Validate
		if ( empty( $display_name ) ) {
			$errors[] = __( 'Display name is required.', 'fanfiction-manager' );
		}

		// If errors, return error response
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $errors )
			) );
		}

		// Update user
		$updated = wp_update_user( array(
			'ID'           => $current_user->ID,
			'display_name' => $display_name,
			'user_url'     => $website,
		) );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array(
				'message' => $updated->get_error_message()
			) );
		}

		// Update bio
		update_user_meta( $current_user->ID, 'description', $bio );

		// Return success response (no redirect for profile edit)
		wp_send_json_success( array(
			'message' => __( 'Profile updated successfully!', 'fanfiction-manager' )
		) );
	}

}
