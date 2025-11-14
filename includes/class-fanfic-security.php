<?php
/**
 * Security Checks Class
 *
 * Centralized security validation and checks for all plugin operations.
 * Provides capability checks, user status verification, and security logging.
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Security
 *
 * Provides comprehensive security functionality including:
 * - AJAX request verification
 * - User capability checks
 * - Ban/suspension management
 * - Security event logging
 * - Post ownership verification
 *
 * @since 1.0.15
 */
class Fanfic_Security {

	/**
	 * Security log option name
	 *
	 * @var string
	 */
	const SECURITY_LOG_OPTION = 'fanfic_security_log';

	/**
	 * Maximum security log entries
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 100;

	/**
	 * Initialize security
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function init() {
		// Set security headers
		add_action( 'send_headers', array( __CLASS__, 'set_security_headers' ) );

		// Hook for cleaning old security logs
		add_action( 'fanfic_daily_cleanup', array( __CLASS__, 'cleanup_old_logs' ) );
	}

	/**
	 * Set security headers
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function set_security_headers() {
		// Only set headers for plugin pages
		if ( ! is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) && ! is_admin() ) {
			return;
		}

		// Prevent MIME type sniffing
		header( 'X-Content-Type-Options: nosniff' );

		// Enable XSS protection
		header( 'X-XSS-Protection: 1; mode=block' );

		// Referrer policy
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}

	/**
	 * Verify AJAX request security
	 *
	 * @since 1.0.15
	 * @param string $action       Nonce action name.
	 * @param string $nonce_field  Optional. Nonce field name. Default 'nonce'.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_ajax_request( $action, $nonce_field = 'nonce' ) {
		// Check if AJAX request
		if ( ! wp_doing_ajax() ) {
			self::log_security_event( 'invalid_ajax_context', array(
				'action' => $action,
			) );
			return new WP_Error( 'invalid_context', __( 'Invalid request context.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		$nonce = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( $_POST[ $nonce_field ] ) : '';

		// Try verifying against action-specific nonce first, then fall back to unified nonce
		$nonce_valid = wp_verify_nonce( $nonce, $action ) || wp_verify_nonce( $nonce, 'fanfic_ajax_nonce' );

		if ( ! $nonce_valid ) {
			self::log_security_event( 'invalid_nonce', array(
				'action'      => $action,
				'nonce_field' => $nonce_field,
			) );
			return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh the page and try again.', 'fanfiction-manager' ) );
		}

		// Check if user is logged in for non-public actions
		if ( ! is_user_logged_in() && ! self::is_public_action( $action ) ) {
			self::log_security_event( 'unauthorized_access', array(
				'action' => $action,
			) );
			return new WP_Error( 'unauthorized', __( 'You must be logged in to perform this action.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Check if action is public (doesn't require login)
	 *
	 * @since 1.0.15
	 * @param string $action Action name.
	 * @return bool True if public action.
	 */
	private static function is_public_action( $action ) {
		$public_actions = array(
			'fanfic_track_view',
			'fanfic_search',
		);

		return in_array( $action, $public_actions, true );
	}

	/**
	 * Verify user capabilities
	 *
	 * @since 1.0.15
	 * @param int    $user_id User ID to check.
	 * @param string $action  Action type: 'read', 'rate', 'like', 'bookmark', 'follow', 'comment', 'subscribe'.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_capabilities( $user_id, $action ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID.', 'fanfiction-manager' ) );
		}

		// Get user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User not found.', 'fanfiction-manager' ) );
		}

		// Check if user is banned
		$is_banned = self::is_user_banned( $user_id );
		if ( $is_banned ) {
			self::log_security_event( 'banned_user_attempt', array(
				'user_id' => $user_id,
				'action'  => $action,
			) );
			return new WP_Error( 'user_banned', __( 'Your account has been banned from interacting.', 'fanfiction-manager' ) );
		}

		// Check if user is suspended
		$is_suspended = self::is_user_suspended( $user_id );
		if ( $is_suspended ) {
			self::log_security_event( 'suspended_user_attempt', array(
				'user_id' => $user_id,
				'action'  => $action,
			) );
			return new WP_Error( 'user_suspended', __( 'Your account has been temporarily suspended.', 'fanfiction-manager' ) );
		}

		// Check capabilities based on action
		$required_cap = self::get_required_capability( $action );

		if ( ! user_can( $user, $required_cap ) ) {
			self::log_security_event( 'insufficient_capabilities', array(
				'user_id'      => $user_id,
				'action'       => $action,
				'required_cap' => $required_cap,
			) );
			return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Get required capability for action
	 *
	 * @since 1.0.15
	 * @param string $action Action name.
	 * @return string Required capability.
	 */
	private static function get_required_capability( $action ) {
		$capabilities = array(
			'read'       => 'read',
			'rate'       => 'read',
			'like'       => 'read',
			'bookmark'   => 'read',
			'follow'     => 'read',
			'subscribe'  => 'read',
			'comment'    => 'read',
			'edit_story' => 'edit_fanfiction_stories',
			'moderate'   => 'moderate_fanfiction',
		);

		return isset( $capabilities[ $action ] ) ? $capabilities[ $action ] : 'read';
	}

	/**
	 * Check if user is banned
	 *
	 * @since 1.0.15
	 * @param int $user_id User ID.
	 * @return bool True if banned.
	 */
	public static function is_user_banned( $user_id ) {
		$banned = get_user_meta( $user_id, 'fanfic_banned', true );

		if ( ! $banned ) {
			return false;
		}

		// Check if ban has expiration
		$ban_expires = get_user_meta( $user_id, 'fanfic_ban_expires', true );

		if ( $ban_expires && strtotime( $ban_expires ) < time() ) {
			// Ban expired, clear it
			delete_user_meta( $user_id, 'fanfic_banned' );
			delete_user_meta( $user_id, 'fanfic_ban_expires' );
			delete_user_meta( $user_id, 'fanfic_ban_reason' );

			self::log_security_event( 'ban_expired', array( 'user_id' => $user_id ) );

			return false;
		}

		return true;
	}

	/**
	 * Check if user is suspended
	 *
	 * @since 1.0.15
	 * @param int $user_id User ID.
	 * @return bool True if suspended.
	 */
	public static function is_user_suspended( $user_id ) {
		$suspended = get_user_meta( $user_id, 'fanfic_suspended', true );

		if ( ! $suspended ) {
			return false;
		}

		// Check if suspension has expiration
		$suspension_expires = get_user_meta( $user_id, 'fanfic_suspension_expires', true );

		if ( $suspension_expires && strtotime( $suspension_expires ) < time() ) {
			// Suspension expired, clear it
			delete_user_meta( $user_id, 'fanfic_suspended' );
			delete_user_meta( $user_id, 'fanfic_suspension_expires' );
			delete_user_meta( $user_id, 'fanfic_suspension_reason' );

			self::log_security_event( 'suspension_expired', array( 'user_id' => $user_id ) );

			return false;
		}

		return true;
	}

	/**
	 * Sanitize AJAX POST data
	 *
	 * @since 1.0.15
	 * @param array $data POST data to sanitize.
	 * @return array Sanitized data.
	 */
	public static function sanitize_ajax_post_data( $data ) {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_ajax_post_data( $value );
			} else {
				// Apply appropriate sanitization based on key
				switch ( $key ) {
					case 'email':
						$sanitized[ $key ] = sanitize_email( $value );
						break;

					case 'url':
						$sanitized[ $key ] = esc_url_raw( $value );
						break;

					case 'content':
					case 'comment':
					case 'message':
						$sanitized[ $key ] = wp_kses_post( $value );
						break;

					default:
						if ( is_numeric( $value ) ) {
							$sanitized[ $key ] = absint( $value );
						} else {
							$sanitized[ $key ] = sanitize_text_field( $value );
						}
						break;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Log security event
	 *
	 * @since 1.0.15
	 * @param string $event_type Event type.
	 * @param array  $details    Optional. Event details.
	 * @return void
	 */
	public static function log_security_event( $event_type, $details = array() ) {
		$log = get_option( self::SECURITY_LOG_OPTION, array() );

		$entry = array(
			'event_type' => sanitize_key( $event_type ),
			'details'    => $details,
			'timestamp'  => current_time( 'mysql' ),
			'user_id'    => get_current_user_id(),
			'ip'         => Fanfic_Rate_Limit::get_ip_address(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
		);

		// Add to beginning of array
		array_unshift( $log, $entry );

		// Keep only last MAX_LOG_ENTRIES
		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( self::SECURITY_LOG_OPTION, $log, false );

		/**
		 * Fires when security event is logged
		 *
		 * @since 1.0.15
		 *
		 * @param string $event_type Event type.
		 * @param array  $details    Event details.
		 * @param array  $entry      Full log entry.
		 */
		do_action( 'fanfic_security_event_logged', $event_type, $details, $entry );
	}

	/**
	 * Get security logs
	 *
	 * @since 1.0.15
	 * @param int    $limit      Maximum entries to return.
	 * @param string $event_type Optional. Filter by event type.
	 * @return array Security logs.
	 */
	public static function get_security_logs( $limit = 50, $event_type = null ) {
		$log = get_option( self::SECURITY_LOG_OPTION, array() );

		// Filter by event type if specified
		if ( $event_type ) {
			$log = array_filter( $log, function( $entry ) use ( $event_type ) {
				return isset( $entry['event_type'] ) && $entry['event_type'] === $event_type;
			} );
		}

		// Limit results
		if ( $limit > 0 && count( $log ) > $limit ) {
			return array_slice( $log, 0, $limit );
		}

		return $log;
	}

	/**
	 * Clear security logs
	 *
	 * @since 1.0.15
	 * @return bool True on success.
	 */
	public static function clear_security_logs() {
		return delete_option( self::SECURITY_LOG_OPTION );
	}

	/**
	 * Check post ownership
	 *
	 * @since 1.0.15
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return true|WP_Error True if user owns post, WP_Error otherwise.
	 */
	public static function check_post_ownership( $user_id, $post_id ) {
		$user_id = absint( $user_id );
		$post_id = absint( $post_id );

		if ( ! $user_id || ! $post_id ) {
			return new WP_Error( 'invalid_parameters', __( 'Invalid user or post ID.', 'fanfiction-manager' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'fanfiction-manager' ) );
		}

		$post_author = absint( $post->post_author );

		if ( $user_id !== $post_author ) {
			self::log_security_event( 'ownership_check_failed', array(
				'user_id'     => $user_id,
				'post_id'     => $post_id,
				'post_author' => $post_author,
			) );

			return new WP_Error( 'not_post_owner', __( 'You do not own this content.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Require user login
	 *
	 * @since 1.0.15
	 * @param bool $redirect Whether to redirect to login page. Default true.
	 * @return true|void True if logged in, void if redirect.
	 */
	public static function require_login( $redirect = true ) {
		if ( is_user_logged_in() ) {
			return true;
		}

		self::log_security_event( 'login_required', array(
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
		) );

		if ( $redirect ) {
			$login_url = wp_login_url( home_url( $_SERVER['REQUEST_URI'] ?? '' ) );
			wp_safe_redirect( $login_url );
			exit;
		}

		return new WP_Error( 'login_required', __( 'You must be logged in to access this page.', 'fanfiction-manager' ) );
	}

	/**
	 * Get security statistics
	 *
	 * @since 1.0.15
	 * @return array Security statistics.
	 */
	public static function get_security_stats() {
		$log = get_option( self::SECURITY_LOG_OPTION, array() );

		$stats = array(
			'total_events'     => count( $log ),
			'events_by_type'   => array(),
			'recent_events'    => array_slice( $log, 0, 10 ),
			'banned_users'     => 0,
			'suspended_users'  => 0,
		);

		// Count events by type
		foreach ( $log as $entry ) {
			$type = $entry['event_type'] ?? 'unknown';

			if ( ! isset( $stats['events_by_type'][ $type ] ) ) {
				$stats['events_by_type'][ $type ] = 0;
			}

			$stats['events_by_type'][ $type ]++;
		}

		// Count banned/suspended users
		global $wpdb;

		$stats['banned_users'] = $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id)
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'fanfic_banned'
			AND meta_value = '1'"
		);

		$stats['suspended_users'] = $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id)
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'fanfic_suspended'
			AND meta_value = '1'"
		);

		return $stats;
	}

	/**
	 * Clean up old security logs
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function cleanup_old_logs() {
		$log = get_option( self::SECURITY_LOG_OPTION, array() );

		// Keep only logs from last 30 days
		$cutoff = strtotime( '-30 days' );

		$log = array_filter( $log, function( $entry ) use ( $cutoff ) {
			if ( ! isset( $entry['timestamp'] ) ) {
				return false;
			}

			return strtotime( $entry['timestamp'] ) > $cutoff;
		} );

		// Reindex array
		$log = array_values( $log );

		update_option( self::SECURITY_LOG_OPTION, $log, false );
	}
}
