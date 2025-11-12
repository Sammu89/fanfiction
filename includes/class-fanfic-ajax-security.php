<?php
/**
 * AJAX Security Wrapper Class
 *
 * Provides standardized security checks and error handling for all AJAX endpoints.
 * Wraps AJAX handlers with automatic nonce verification, capability checks,
 * rate limiting, and consistent response formatting.
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_AJAX_Security
 *
 * Provides comprehensive AJAX security including:
 * - Automatic nonce verification
 * - Capability checks
 * - Rate limiting
 * - Request logging
 * - Standardized responses
 *
 * @since 1.0.15
 */
class Fanfic_AJAX_Security {

	/**
	 * Registered AJAX handlers
	 *
	 * @var array
	 */
	private static $handlers = array();

	/**
	 * Request log option name
	 *
	 * @var string
	 */
	const REQUEST_LOG_OPTION = 'fanfic_ajax_request_log';

	/**
	 * Initialize AJAX security
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function init() {
		// Hook for logging slow AJAX requests
		add_action( 'shutdown', array( __CLASS__, 'log_ajax_performance' ) );
	}

	/**
	 * Register AJAX handler with security wrapper
	 *
	 * @since 1.0.15
	 * @param string   $action        AJAX action name.
	 * @param callable $callback      Callback function.
	 * @param bool     $require_login Whether login is required. Default true.
	 * @param array    $options       Optional. Additional options.
	 * @return void
	 */
	public static function register_ajax_handler( $action, $callback, $require_login = true, $options = array() ) {
		if ( ! is_callable( $callback ) ) {
			return;
		}

		// Store handler info
		self::$handlers[ $action ] = array(
			'callback'      => $callback,
			'require_login' => $require_login,
			'options'       => wp_parse_args( $options, array(
				'rate_limit'     => true,
				'log_requests'   => true,
				'check_referer'  => true,
				'capability'     => 'read',
			) ),
		);

		// Register WordPress AJAX hooks
		if ( $require_login ) {
			add_action( 'wp_ajax_' . $action, function() use ( $action ) {
				self::execute_ajax_action( $action );
			} );
		} else {
			add_action( 'wp_ajax_' . $action, function() use ( $action ) {
				self::execute_ajax_action( $action );
			} );
			add_action( 'wp_ajax_nopriv_' . $action, function() use ( $action ) {
				self::execute_ajax_action( $action );
			} );
		}
	}

	/**
	 * Execute AJAX action with security wrapper
	 *
	 * @since 1.0.15
	 * @param string $action      AJAX action name.
	 * @param bool   $require_login Optional. Override require_login setting.
	 * @return void
	 */
	public static function execute_ajax_action( $action, $require_login = null ) {
		$start_time = microtime( true );

		// Check if handler is registered
		if ( ! isset( self::$handlers[ $action ] ) ) {
			self::send_error_response(
				'unregistered_action',
				__( 'Invalid AJAX action.', 'fanfiction-manager' ),
				400
			);
		}

		$handler = self::$handlers[ $action ];
		$options = $handler['options'];

		// Override require_login if specified
		if ( null !== $require_login ) {
			$handler['require_login'] = $require_login;
		}

		// 1. Verify nonce
		$nonce_check = Fanfic_Security::verify_ajax_request( $action, 'nonce' );

		if ( is_wp_error( $nonce_check ) ) {
			self::send_error_response(
				$nonce_check->get_error_code(),
				$nonce_check->get_error_message(),
				403
			);
		}

		// 2. Check if login required
		if ( $handler['require_login'] && ! is_user_logged_in() ) {
			self::send_error_response(
				'login_required',
				__( 'You must be logged in to perform this action.', 'fanfiction-manager' ),
				401
			);
		}

		// 3. Check capabilities
		if ( is_user_logged_in() && isset( $options['capability'] ) ) {
			$capability_check = Fanfic_Security::verify_capabilities( get_current_user_id(), $options['capability'] );

			if ( is_wp_error( $capability_check ) ) {
				self::send_error_response(
					$capability_check->get_error_code(),
					$capability_check->get_error_message(),
					403
				);
			}
		}

		// 4. Check rate limiting
		if ( $options['rate_limit'] ) {
			$identifier = is_user_logged_in() ? get_current_user_id() : Fanfic_Rate_Limit::get_ip_address();

			if ( Fanfic_Rate_Limit::is_rate_limited( $identifier, $action ) ) {
				$wait_time = Fanfic_Rate_Limit::get_wait_time( $identifier, $action );

				self::send_error_response(
					'rate_limited',
					sprintf(
						/* translators: %d: seconds to wait */
						__( 'Too many requests. Please wait %d seconds and try again.', 'fanfiction-manager' ),
						$wait_time
					),
					429
				);
			}

			// Increment rate limit counter
			Fanfic_Rate_Limit::increment_counter( $identifier, $action );

			// Check for suspicious activity
			if ( Fanfic_Rate_Limit::is_suspicious_activity( $identifier, $action ) ) {
				self::send_error_response(
					'suspicious_activity',
					__( 'Suspicious activity detected. Please try again later.', 'fanfiction-manager' ),
					429
				);
			}
		}

		// 5. Execute callback
		try {
			$result = call_user_func( $handler['callback'] );

			// Log performance
			$elapsed = microtime( true ) - $start_time;

			if ( $options['log_requests'] ) {
				self::log_ajax_request( $action, $elapsed, true );
			}

			// If callback didn't send response, send success
			if ( ! empty( $result ) ) {
				self::send_success_response( $result );
			}
		} catch ( Exception $e ) {
			// Log error
			Fanfic_Security::log_security_event( 'ajax_exception', array(
				'action'  => $action,
				'message' => $e->getMessage(),
				'trace'   => $e->getTraceAsString(),
			) );

			self::send_error_response(
				'execution_error',
				__( 'An error occurred while processing your request.', 'fanfiction-manager' ),
				500
			);
		}
	}

	/**
	 * Send standardized success response
	 *
	 * @since 1.0.15
	 * @param mixed  $data    Response data.
	 * @param string $message Optional. Success message.
	 * @return void
	 */
	public static function send_success_response( $data = array(), $message = 'Success' ) {
		wp_send_json_success( array(
			'data'      => $data,
			'message'   => $message,
			'timestamp' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Send standardized error response
	 *
	 * @since 1.0.15
	 * @param string $error_code Error code.
	 * @param string $message    Error message.
	 * @param int    $http_code  Optional. HTTP status code. Default 400.
	 * @return void
	 */
	public static function send_error_response( $error_code, $message, $http_code = 400 ) {
		status_header( $http_code );

		wp_send_json_error( array(
			'error_code' => $error_code,
			'message'    => $message,
			'timestamp'  => current_time( 'mysql' ),
		) );
	}

	/**
	 * Get and validate AJAX parameters
	 *
	 * @since 1.0.15
	 * @param array $required_keys Required POST keys.
	 * @param array $optional_keys Optional POST keys.
	 * @return array|WP_Error Sanitized data or WP_Error on failure.
	 */
	public static function get_ajax_parameters( $required_keys = array(), $optional_keys = array() ) {
		$data = array();

		// Check required keys
		foreach ( $required_keys as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				return new WP_Error(
					'missing_parameter',
					sprintf(
						/* translators: %s: parameter name */
						__( 'Missing required parameter: %s', 'fanfiction-manager' ),
						$key
					)
				);
			}

			$data[ $key ] = $_POST[ $key ];
		}

		// Get optional keys
		foreach ( $optional_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$data[ $key ] = $_POST[ $key ];
			}
		}

		// Sanitize all data
		return Fanfic_Security::sanitize_ajax_post_data( $data );
	}

	/**
	 * Log AJAX request
	 *
	 * @since 1.0.15
	 * @param string $action  Action name.
	 * @param float  $elapsed Execution time in seconds.
	 * @param bool   $success Whether request succeeded.
	 * @return void
	 */
	private static function log_ajax_request( $action, $elapsed, $success ) {
		// Only log slow requests (> 1 second)
		if ( $elapsed < 1.0 ) {
			return;
		}

		$log = get_option( self::REQUEST_LOG_OPTION, array() );

		$entry = array(
			'action'    => $action,
			'elapsed'   => round( $elapsed, 4 ),
			'success'   => $success,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'ip'        => Fanfic_Rate_Limit::get_ip_address(),
		);

		array_unshift( $log, $entry );

		// Keep only last 50 entries
		if ( count( $log ) > 50 ) {
			$log = array_slice( $log, 0, 50 );
		}

		update_option( self::REQUEST_LOG_OPTION, $log, false );
	}

	/**
	 * Log AJAX performance on shutdown
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function log_ajax_performance() {
		if ( ! wp_doing_ajax() ) {
			return;
		}

		// Get current action
		$action = isset( $_POST['action'] ) ? sanitize_key( $_POST['action'] ) : '';

		if ( empty( $action ) || strpos( $action, 'fanfic_' ) !== 0 ) {
			return;
		}

		// Log memory usage for AJAX requests
		if ( Fanfic_Performance_Monitor::is_monitoring_enabled() ) {
			Fanfic_Performance_Monitor::log_memory_usage();
		}
	}

	/**
	 * Get AJAX request log
	 *
	 * @since 1.0.15
	 * @param int $limit Maximum entries to return.
	 * @return array Request log.
	 */
	public static function get_request_log( $limit = 50 ) {
		$log = get_option( self::REQUEST_LOG_OPTION, array() );

		if ( $limit > 0 && count( $log ) > $limit ) {
			return array_slice( $log, 0, $limit );
		}

		return $log;
	}

	/**
	 * Clear AJAX request log
	 *
	 * @since 1.0.15
	 * @return bool True on success.
	 */
	public static function clear_request_log() {
		return delete_option( self::REQUEST_LOG_OPTION );
	}

	/**
	 * Get registered handlers
	 *
	 * @since 1.0.15
	 * @return array Registered handlers.
	 */
	public static function get_registered_handlers() {
		return self::$handlers;
	}

	/**
	 * Check if handler is registered
	 *
	 * @since 1.0.15
	 * @param string $action Action name.
	 * @return bool True if registered.
	 */
	public static function is_handler_registered( $action ) {
		return isset( self::$handlers[ $action ] );
	}
}
