<?php
/**
 * Notification Preferences System Class
 *
 * Handles user notification preferences for email and in-app notifications.
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
 * Class Fanfic_Notification_Preferences
 *
 * Manages user preferences for notifications.
 *
 * @since 1.0.0
 */
class Fanfic_Notification_Preferences {

	/**
	 * Preference key prefixes
	 */
	const PREFIX_EMAIL = 'fanfic_email_';
	const PREFIX_INAPP = 'fanfic_inapp_';

	/**
	 * Notification types
	 */
	const TYPE_NEW_COMMENT = 'new_comment';
	const TYPE_NEW_CHAPTER = 'new_chapter';
	const TYPE_NEW_STORY = 'new_story';

	/**
	 * Initialize the preferences class
	 *
	 * Sets up WordPress hooks for preference functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register AJAX handlers for preference saving
		add_action( 'wp_ajax_fanfic_save_notification_preferences', array( __CLASS__, 'ajax_save_preferences' ) );
	}

	/**
	 * Get single preference
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $key     Preference key.
	 * @return bool Preference value (true = enabled, false = disabled).
	 */
	public static function get_preference( $user_id, $key ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return true; // Default to enabled
		}

		$value = get_user_meta( $user_id, $key, true );

		// If not set, return default (enabled)
		if ( '' === $value ) {
			return true;
		}

		return (bool) $value;
	}

	/**
	 * Set single preference
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $key     Preference key.
	 * @param bool   $value   Preference value.
	 * @return bool True on success, false on failure.
	 */
	public static function set_preference( $user_id, $key, $value ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$value = (bool) $value;

		return update_user_meta( $user_id, $key, $value ? 1 : 0 );
	}

	/**
	 * Get all preferences for user
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array Associative array of preferences.
	 */
	public static function get_all_preferences( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return self::get_default_preferences();
		}

		$preferences = array();
		$types = array(
			self::TYPE_NEW_COMMENT,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
		);

		foreach ( $types as $type ) {
			$preferences[ self::PREFIX_EMAIL . $type ] = self::get_preference( $user_id, self::PREFIX_EMAIL . $type );
			$preferences[ self::PREFIX_INAPP . $type ] = self::get_preference( $user_id, self::PREFIX_INAPP . $type );
		}

		return $preferences;
	}

	/**
	 * Set all preferences for user
	 *
	 * @since 1.0.0
	 * @param int   $user_id          User ID.
	 * @param array $preferences_array Associative array of preferences.
	 * @return bool True on success, false on failure.
	 */
	public static function set_all_preferences( $user_id, $preferences_array ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! is_array( $preferences_array ) ) {
			return false;
		}

		foreach ( $preferences_array as $key => $value ) {
			self::set_preference( $user_id, $key, $value );
		}

		return true;
	}

	/**
	 * Get default preferences
	 *
	 * All notifications enabled by default.
	 *
	 * @since 1.0.0
	 * @return array Default preferences array.
	 */
	public static function get_default_preferences() {
		$types = array(
			self::TYPE_NEW_COMMENT,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
		);

		$defaults = array();
		foreach ( $types as $type ) {
			$defaults[ self::PREFIX_EMAIL . $type ] = true;
			$defaults[ self::PREFIX_INAPP . $type ] = true;
		}

		return $defaults;
	}

	/**
	 * Check if email should be sent for notification type
	 *
	 * @since 1.0.0
	 * @param int    $user_id          User ID.
	 * @param string $notification_type Notification type.
	 * @return bool True if email should be sent, false otherwise.
	 */
	public static function should_send_email( $user_id, $notification_type ) {
		$key = self::PREFIX_EMAIL . $notification_type;
		return self::get_preference( $user_id, $key );
	}

	/**
	 * Check if in-app notification should be created
	 *
	 * @since 1.0.0
	 * @param int    $user_id          User ID.
	 * @param string $notification_type Notification type.
	 * @return bool True if in-app notification should be created, false otherwise.
	 */
	public static function should_create_inapp( $user_id, $notification_type ) {
		$key = self::PREFIX_INAPP . $notification_type;
		return self::get_preference( $user_id, $key );
	}

	/**
	 * AJAX handler for saving notification preferences
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_save_preferences() {
		// Verify nonce
		check_ajax_referer( 'fanfic_notification_preferences', 'nonce' );

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		$user_id = get_current_user_id();

		// Get preferences from POST data
		$preferences = array();
		$types = array(
			self::TYPE_NEW_COMMENT,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
		);

		foreach ( $types as $type ) {
			$email_key = self::PREFIX_EMAIL . $type;
			$inapp_key = self::PREFIX_INAPP . $type;

			// Get values from POST (checkboxes are only sent if checked)
			$preferences[ $email_key ] = isset( $_POST[ $email_key ] ) ? 1 : 0;
			$preferences[ $inapp_key ] = isset( $_POST[ $inapp_key ] ) ? 1 : 0;
		}

		// Save preferences
		$result = self::set_all_preferences( $user_id, $preferences );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Preferences saved successfully!', 'fanfiction-manager' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save preferences.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * Get preference labels
	 *
	 * Returns human-readable labels for preference types.
	 *
	 * @since 1.0.0
	 * @return array Preference labels array.
	 */
	public static function get_preference_labels() {
		return array(
			self::TYPE_NEW_COMMENT => __( 'New comments on my stories', 'fanfiction-manager' ),
			self::TYPE_NEW_CHAPTER => __( 'New chapter updates', 'fanfiction-manager' ),
			self::TYPE_NEW_STORY => __( 'New story updates', 'fanfiction-manager' ),
		);
	}
}
