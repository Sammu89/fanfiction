<?php
/**
 * Rate Limiting Class
 *
 * Prevents spam and abuse via rate limiting on AJAX endpoints and user actions.
 * Uses WordPress transients for storage with fallback to database.
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Rate_Limit
 *
 * Provides comprehensive rate limiting functionality including:
 * - Per-user and per-IP rate limiting
 * - Configurable limits per action
 * - Suspicious activity detection
 * - Automatic cleanup of expired limits
 *
 * @since 1.0.15
 */
class Fanfic_Rate_Limit {

	/**
	 * Default rate limits per action (requests per window)
	 *
	 * @var array
	 */
	private static $default_limits = array(
		'rate'       => array( 'limit' => 10, 'window' => 60 ),      // 10 ratings per minute
		'like'       => array( 'limit' => 10, 'window' => 60 ),      // 10 likes per minute
		'follow'   => array( 'limit' => 5, 'window' => 60 ),       // 5 follows per minute
		'subscribe'  => array( 'limit' => 2, 'window' => 60 ),       // 2 subscriptions per minute
		'comment'    => array( 'limit' => 5, 'window' => 300 ),      // 5 comments per 5 minutes
		'view'       => array( 'limit' => 100, 'window' => 60 ),     // 100 views per minute
		'search'     => array( 'limit' => 30, 'window' => 60 ),      // 30 searches per minute
		'ajax'       => array( 'limit' => 60, 'window' => 60 ),      // 60 AJAX calls per minute
		'fanfic_record_interaction' => array( 'limit' => 120, 'window' => 60 ), // 120 interaction writes per minute
	);

	/**
	 * Suspicious activity thresholds
	 *
	 * @var array
	 */
	private static $suspicious_thresholds = array(
		'rapid_actions'     => 20,  // More than 20 actions in 10 seconds
		'unique_actions'    => 5,   // More than 5 different action types in 10 seconds
		'failed_attempts'   => 10,  // More than 10 rate-limited attempts in 5 minutes
	);

	/**
	 * Initialize rate limiting
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function init() {
		// No initialization needed - uses transients
	}

	/**
	 * Check if identifier is rate limited for action
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Action name (rate, like, follow, etc.).
	 * @param int    $limit      Optional. Override default limit.
	 * @param int    $window     Optional. Override default window (seconds).
	 * @return bool True if rate limited, false if allowed.
	 */
	public static function is_rate_limited( $identifier, $action, $limit = null, $window = null ) {
		// Get limits for action
		$limits = self::get_action_limits( $action );

		// Override with custom values if provided
		if ( null !== $limit ) {
			$limits['limit'] = absint( $limit );
		}
		if ( null !== $window ) {
			$limits['window'] = absint( $window );
		}

		// Get current counter
		$transient_key = self::get_transient_key( $identifier, $action );
		$counter = get_transient( $transient_key );

		// If no counter exists, not limited
		if ( false === $counter ) {
			return false;
		}

		// Check if over limit
		return ( absint( $counter ) >= $limits['limit'] );
	}

	/**
	 * Increment rate limit counter
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Action name.
	 * @param int    $window     Optional. Override default window (seconds).
	 * @return int New counter value.
	 */
	public static function increment_counter( $identifier, $action, $window = null ) {
		// Get window for action
		$limits = self::get_action_limits( $action );
		$window = ( null !== $window ) ? absint( $window ) : $limits['window'];

		// Get or create counter
		$transient_key = self::get_transient_key( $identifier, $action );
		$counter = get_transient( $transient_key );

		if ( false === $counter ) {
			// Initialize counter
			$counter = 1;
		} else {
			// Increment counter
			$counter = absint( $counter ) + 1;
		}

		// Set transient with window expiration
		set_transient( $transient_key, $counter, $window );

		return $counter;
	}

	/**
	 * Get remaining requests for identifier
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Action name.
	 * @param int    $limit      Optional. Override default limit.
	 * @return int Remaining requests (0 if exceeded).
	 */
	public static function get_remaining_requests( $identifier, $action, $limit = null ) {
		// Get limits
		$limits = self::get_action_limits( $action );
		$max_limit = ( null !== $limit ) ? absint( $limit ) : $limits['limit'];

		// Get current counter
		$transient_key = self::get_transient_key( $identifier, $action );
		$counter = get_transient( $transient_key );

		if ( false === $counter ) {
			return $max_limit;
		}

		$remaining = $max_limit - absint( $counter );

		return max( 0, $remaining );
	}

	/**
	 * Get wait time until rate limit resets
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Action name.
	 * @return int Seconds until reset (0 if not limited).
	 */
	public static function get_wait_time( $identifier, $action ) {
		global $wpdb;

		$transient_key = self::get_transient_key( $identifier, $action );
		$timeout_key = '_transient_timeout_' . $transient_key;

		// Get timeout from database
		$timeout = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			$timeout_key
		) );

		if ( ! $timeout ) {
			return 0;
		}

		$wait_time = absint( $timeout ) - time();

		return max( 0, $wait_time );
	}

	/**
	 * Reset rate limit for identifier
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Action name.
	 * @return bool True on success.
	 */
	public static function reset_limit( $identifier, $action ) {
		$transient_key = self::get_transient_key( $identifier, $action );
		return delete_transient( $transient_key );
	}

	/**
	 * Check for suspicious activity patterns
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Current action.
	 * @return bool True if suspicious, false otherwise.
	 */
	public static function is_suspicious_activity( $identifier, $action ) {
		if ( ! self::should_track_suspicious_action( $action ) ) {
			return false;
		}

		// Track rapid actions (10 second window)
		$rapid_key = self::get_transient_key( $identifier, 'rapid_check' );
		$rapid_count = get_transient( $rapid_key );

		if ( false === $rapid_count ) {
			set_transient( $rapid_key, 1, 10 );
			$rapid_count = 1;
		} else {
			$rapid_count = absint( $rapid_count ) + 1;
			set_transient( $rapid_key, $rapid_count, 10 );
		}

		// Check if too many rapid actions
		if ( $rapid_count > self::$suspicious_thresholds['rapid_actions'] ) {
			self::log_suspicious_activity( $identifier, 'rapid_actions', $rapid_count );
			return true;
		}

		// Track unique action types
		$actions_key = self::get_transient_key( $identifier, 'action_types' );
		$action_types = get_transient( $actions_key );

		if ( false === $action_types ) {
			$action_types = array( $action );
		} else {
			if ( ! in_array( $action, $action_types, true ) ) {
				$action_types[] = $action;
			}
		}

		set_transient( $actions_key, $action_types, 10 );

		// Check if too many unique actions
		if ( count( $action_types ) > self::$suspicious_thresholds['unique_actions'] ) {
			self::log_suspicious_activity( $identifier, 'unique_actions', count( $action_types ) );
			return true;
		}

		// Track failed rate limit attempts
		$failed_key = self::get_transient_key( $identifier, 'failed_attempts' );
		$failed_count = get_transient( $failed_key );

		if ( self::is_rate_limited( $identifier, $action ) ) {
			if ( false === $failed_count ) {
				set_transient( $failed_key, 1, 300 ); // 5 minutes
				$failed_count = 1;
			} else {
				$failed_count = absint( $failed_count ) + 1;
				set_transient( $failed_key, $failed_count, 300 );

				if ( $failed_count > self::$suspicious_thresholds['failed_attempts'] ) {
					self::log_suspicious_activity( $identifier, 'failed_attempts', $failed_count );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine whether an action should participate in suspicious-activity checks.
	 *
	 * Suspicious heuristics should focus on mutation/high-risk actions. Read-only
	 * and background fetch actions are rate-limited separately, but excluded here
	 * to avoid false positives during normal page activity.
	 *
	 * @since 2.2.1
	 * @param string $action Action name.
	 * @return bool
	 */
	private static function should_track_suspicious_action( $action ) {
		$action = sanitize_key( $action );

		$tracked_actions = array(
			// Canonical short action names.
			'rate',
			'like',
			'follow',
			'subscribe',
			'comment',
			'ajax',

			// AJAX endpoint names that mutate state.
			'fanfic_toggle_follow',
			'fanfic_record_interaction',
			'fanfic_sync_interactions',
			'fanfic_report_content',
			'fanfic_mark_notification_read',
			'fanfic_mark_all_notifications_read',
			'fanfic_delete_notification',
			'fanfic_clear_all_notifications',
			'fanfic_toggle_featured',
			'fanfic_ajax_image_upload',
			'fanfic_bulk_change_author',
			'fanfic_bulk_apply_genre',
			'fanfic_bulk_change_status',
		);

		return in_array( $action, $tracked_actions, true );
	}

	/**
	 * Log suspicious activity
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $type       Type of suspicious activity.
	 * @param int    $count      Count of suspicious actions.
	 * @return void
	 */
	private static function log_suspicious_activity( $identifier, $type, $count ) {
		$log = get_option( 'fanfic_suspicious_activity', array() );

		$entry = array(
			'identifier' => $identifier,
			'type'       => $type,
			'count'      => $count,
			'timestamp'  => current_time( 'mysql' ),
			'ip'         => self::get_ip_address(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
		);

		array_unshift( $log, $entry );

		// Keep only last 100 entries
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, 0, 100 );
		}

		update_option( 'fanfic_suspicious_activity', $log, false );

		/**
		 * Fires when suspicious activity is detected
		 *
		 * @since 1.0.15
		 *
		 * @param string $identifier Identifier (user ID or IP).
		 * @param string $type       Type of suspicious activity.
		 * @param int    $count      Count of actions.
		 */
		do_action( 'fanfic_suspicious_activity_detected', $identifier, $type, $count );
	}

	/**
	 * Get action limits
	 *
	 * @since 1.0.15
	 * @param string $action Action name.
	 * @return array Limit and window.
	 */
	private static function get_action_limits( $action ) {
		// Check if custom limits are set
		$custom_limits = get_option( 'fanfic_rate_limits', array() );

		if ( isset( $custom_limits[ $action ] ) ) {
			return $custom_limits[ $action ];
		}

		// Return default limits
		if ( isset( self::$default_limits[ $action ] ) ) {
			return self::$default_limits[ $action ];
		}

		// Fallback to generic limit
		return array( 'limit' => 10, 'window' => 60 );
	}

	/**
	 * Generate transient key
	 *
	 * @since 1.0.15
	 * @param string $identifier User ID or IP address.
	 * @param string $action     Action name.
	 * @return string Transient key.
	 */
	private static function get_transient_key( $identifier, $action ) {
		$identifier = sanitize_key( $identifier );
		$action = sanitize_key( $action );

		return 'fanfic_rl_' . md5( $identifier . '_' . $action );
	}

	/**
	 * Get IP address
	 *
	 * @since 1.0.15
	 * @return string IP address.
	 */
	public static function get_ip_address() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		} elseif ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( $_SERVER['HTTP_X_REAL_IP'] );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
		}

		return $ip;
	}

	/**
	 * Update rate limits
	 *
	 * @since 1.0.15
	 * @param array $limits Rate limits configuration.
	 * @return bool True on success.
	 */
	public static function update_limits( $limits ) {
		// Validate limits
		foreach ( $limits as $action => $config ) {
			if ( ! isset( $config['limit'], $config['window'] ) ) {
				return false;
			}

			$limits[ $action ] = array(
				'limit'  => absint( $config['limit'] ),
				'window' => absint( $config['window'] ),
			);
		}

		return update_option( 'fanfic_rate_limits', $limits );
	}

	/**
	 * Get all rate limits
	 *
	 * @since 1.0.15
	 * @return array Rate limits.
	 */
	public static function get_all_limits() {
		$custom_limits = get_option( 'fanfic_rate_limits', array() );
		return wp_parse_args( $custom_limits, self::$default_limits );
	}

	/**
	 * Get suspicious activity log
	 *
	 * @since 1.0.15
	 * @param int $limit Maximum entries to return.
	 * @return array Suspicious activity log.
	 */
	public static function get_suspicious_activity_log( $limit = 50 ) {
		$log = get_option( 'fanfic_suspicious_activity', array() );

		if ( $limit > 0 && count( $log ) > $limit ) {
			return array_slice( $log, 0, $limit );
		}

		return $log;
	}

	/**
	 * Clear suspicious activity log
	 *
	 * @since 1.0.15
	 * @return bool True on success.
	 */
	public static function clear_suspicious_activity_log() {
		return delete_option( 'fanfic_suspicious_activity' );
	}
}
