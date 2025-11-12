<?php
/**
 * User Identifier Class
 *
 * Handles user identification for ratings and likes using browser fingerprint + IP hashing.
 * Caches identifiers for 2 hours to avoid repeated generation.
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_User_Identifier
 *
 * Manages user identification for anonymous and logged-in users.
 *
 * @since 2.0.0
 */
class Fanfic_User_Identifier {

	/**
	 * Cache duration for user identifiers (2 hours)
	 *
	 * @var int
	 */
	const CACHE_DURATION = 7200; // 2 hours in seconds

	/**
	 * Get or generate user identifier
	 *
	 * @since 2.0.0
	 * @param string|null $fingerprint_data Browser fingerprint from client (JSON string).
	 * @return array|false User identifier array or false on failure.
	 *                     Format: ['type' => 'logged_in'|'anonymous', 'user_id' => int, 'hash' => string|null]
	 */
	public static function get_identifier( $fingerprint_data = null ) {
		$user_id = get_current_user_id();

		// Logged-in user - simple identifier
		if ( $user_id > 0 ) {
			return array(
				'type'    => 'logged_in',
				'user_id' => $user_id,
				'hash'    => null,
			);
		}

		// Anonymous user - need fingerprint
		if ( empty( $fingerprint_data ) ) {
			return false; // Client must provide fingerprint
		}

		// Create cache key from fingerprint
		$fingerprint_key = md5( $fingerprint_data );
		$cache_key = 'fanfic_uid_' . $fingerprint_key;

		// Check cache first
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Generate new identifier
		$ip = self::get_client_ip();
		$hash = md5( $ip . $fingerprint_data );

		$identifier = array(
			'type'    => 'anonymous',
			'user_id' => 0,
			'hash'    => $hash,
		);

		// Cache for 2 hours
		set_transient( $cache_key, $identifier, self::CACHE_DURATION );

		return $identifier;
	}

	/**
	 * Get client IP address
	 *
	 * Handles various proxy headers and IPv6 addresses.
	 *
	 * @since 2.0.0
	 * @return string Client IP address.
	 */
	private static function get_client_ip() {
		// Check for proxy headers in order of reliability
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header
			'REMOTE_ADDR',           // Direct connection
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = $_SERVER[ $header ];

				// Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		// Fallback to REMOTE_ADDR
		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}

	/**
	 * Clear cached identifier
	 *
	 * Useful for testing or when user identity changes.
	 *
	 * @since 2.0.0
	 * @param string $fingerprint_data Browser fingerprint.
	 * @return bool True if cache was deleted, false otherwise.
	 */
	public static function clear_cache( $fingerprint_data ) {
		if ( empty( $fingerprint_data ) ) {
			return false;
		}

		$fingerprint_key = md5( $fingerprint_data );
		$cache_key = 'fanfic_uid_' . $fingerprint_key;

		return delete_transient( $cache_key );
	}
}
