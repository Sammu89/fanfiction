<?php
/**
 * Follows System Class
 *
 * Handles author follow functionality with comprehensive features.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Follows
 *
 * Comprehensive unified follow system for stories and authors.
 * Supports email notifications with user preferences.
 *
 * @since 1.0.0
 */
class Fanfic_Follows {

	/**
	 * Initialize follows system
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// AJAX handlers registered in class-fanfic-ajax-handlers.php (unified endpoint system)
		// This class provides helper methods for follow functionality

		// Hook into story and chapter publication to notify followers
		add_action( 'transition_post_status', array( __CLASS__, 'notify_followers_on_publish' ), 10, 3 );
	}

	/**
	 * Toggle follow for a target (story or author)
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param int    $target_id   Target ID (story ID or author ID).
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return array Result with is_following and email_enabled status.
	 */
	public static function toggle_follow( $user_id, $target_id, $follow_type = 'story' ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$target_id = absint( $target_id );
		$follow_type = sanitize_text_field( $follow_type );

		if ( ! $user_id || ! $target_id ) {
			return array( 'success' => false, 'error' => 'Invalid parameters' );
		}

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return array( 'success' => false, 'error' => 'Invalid follow type' );
		}

		// Prevent self-following for authors
		if ( 'author' === $follow_type && $user_id === $target_id ) {
			return array( 'success' => false, 'error' => 'Cannot follow yourself' );
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Check if already following
		$is_following = self::is_following( $user_id, $target_id, $follow_type );

		if ( $is_following ) {
			// Unfollow
			$deleted = $wpdb->delete(
				$table_name,
				array(
					'user_id'     => $user_id,
					'target_id'   => $target_id,
					'follow_type' => $follow_type,
				),
				array( '%d', '%d', '%s' )
			);

			if ( false !== $deleted ) {
				self::clear_follow_cache( $target_id, $user_id, $follow_type );
				do_action( 'fanfic_unfollowed', $target_id, $user_id, $follow_type );
				return array( 'success' => true, 'is_following' => false, 'email_enabled' => false );
			}
		} else {
			// Follow with email enabled by default
			$inserted = $wpdb->insert(
				$table_name,
				array(
					'user_id'       => $user_id,
					'target_id'     => $target_id,
					'follow_type'   => $follow_type,
					'email_enabled' => 1,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s' )
			);

			if ( false !== $inserted ) {
				self::clear_follow_cache( $target_id, $user_id, $follow_type );

				// Create notification
				self::create_follow_notification( $target_id, $user_id, $follow_type );

				do_action( 'fanfic_followed', $target_id, $user_id, $follow_type );
				return array( 'success' => true, 'is_following' => true, 'email_enabled' => true );
			}
		}

		return array( 'success' => false, 'error' => 'Database operation failed' );
	}

	/**
	 * Legacy method - follow an author (backwards compatibility)
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return bool|int Follow ID on success, false on failure.
	 */
	public static function follow_author( $author_id, $follower_id ) {
		$result = self::toggle_follow( $follower_id, $author_id, 'author' );
		return $result['success'] ? true : false;
	}

	/**
	 * Legacy method - unfollow an author (backwards compatibility)
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return bool True on success, false on failure.
	 */
	public static function unfollow_author( $author_id, $follower_id ) {
		$result = self::toggle_follow( $follower_id, $author_id, 'author' );
		return $result['success'];
	}

	/**
	 * Check if user is following a target
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param int    $target_id   Target ID.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return bool True if following, false otherwise.
	 */
	public static function is_following( $user_id, $target_id, $follow_type = 'story' ) {
		global $wpdb;

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return false;
		}

		// Try cache first
		$cache_key = 'fanfic_is_following_' . $user_id . '_' . $target_id . '_' . $follow_type;
		$cached = wp_cache_get( $cache_key, 'fanfic_follows' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE user_id = %d AND target_id = %d AND follow_type = %s LIMIT 1",
			absint( $user_id ),
			absint( $target_id ),
			$follow_type
		) );

		$is_following = ! empty( $exists );

		// Cache result for 5 minutes
		wp_cache_set( $cache_key, $is_following, 'fanfic_follows', 5 * MINUTE_IN_SECONDS );

		return $is_following;
	}

	/**
	 * Check if email notifications are enabled for a follow
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param int    $target_id   Target ID.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return bool True if email enabled, false otherwise.
	 */
	public static function is_email_enabled( $user_id, $target_id, $follow_type ) {
		global $wpdb;

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return false;
		}

		// Try cache first
		$cache_key = 'fanfic_email_enabled_' . $user_id . '_' . $target_id . '_' . $follow_type;
		$cached = wp_cache_get( $cache_key, 'fanfic_follows' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		$enabled = $wpdb->get_var( $wpdb->prepare(
			"SELECT email_enabled FROM {$table_name} WHERE user_id = %d AND target_id = %d AND follow_type = %s LIMIT 1",
			absint( $user_id ),
			absint( $target_id ),
			$follow_type
		) );

		$is_enabled = ! empty( $enabled );

		// Cache result for 5 minutes
		wp_cache_set( $cache_key, $is_enabled, 'fanfic_follows', 5 * MINUTE_IN_SECONDS );

		return $is_enabled;
	}

	/**
	 * Toggle email notifications for a follow
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param int    $target_id   Target ID.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @param bool   $enabled     Whether to enable or disable email notifications.
	 * @return bool New state on success, false on failure.
	 */
	public static function toggle_email_notifications( $user_id, $target_id, $follow_type, $enabled ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$target_id = absint( $target_id );
		$enabled = (bool) $enabled;

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Update email_enabled status
		$updated = $wpdb->update(
			$table_name,
			array( 'email_enabled' => $enabled ? 1 : 0 ),
			array(
				'user_id'     => $user_id,
				'target_id'   => $target_id,
				'follow_type' => $follow_type,
			),
			array( '%d' ),
			array( '%d', '%d', '%s' )
		);

		if ( false !== $updated ) {
			// Clear cache
			wp_cache_delete( 'fanfic_email_enabled_' . $user_id . '_' . $target_id . '_' . $follow_type, 'fanfic_follows' );
			return $enabled;
		}

		return false;
	}

	/**
	 * Get user's follows (stories or authors they follow)
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param string $follow_type Type: 'story', 'author', or null for all.
	 * @param int    $limit       Number of follows to retrieve.
	 * @return array Array of target_ids with metadata.
	 */
	public static function get_user_follows( $user_id, $follow_type = null, $limit = 50 ) {
		$user_id = absint( $user_id );
		$limit = absint( $limit );

		// Validate follow type if provided
		if ( null !== $follow_type && ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return array();
		}

		// Try to get from transient cache
		$cache_key = 'fanfic_user_follows_' . $user_id . '_' . ( $follow_type ?? 'all' ) . '_' . $limit;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		if ( null === $follow_type ) {
			// Get all follows
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT target_id, follow_type, email_enabled, created_at FROM {$table_name}
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$user_id,
				$limit
			), ARRAY_A );
		} else {
			// Get follows filtered by type
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT target_id, follow_type, email_enabled, created_at FROM {$table_name}
				WHERE user_id = %d AND follow_type = %s
				ORDER BY created_at DESC
				LIMIT %d",
				$user_id,
				$follow_type,
				$limit
			), ARRAY_A );
		}

		// Cache for 5 minutes
		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get followers for a target (story or author)
	 *
	 * @since 1.0.0
	 * @param int    $target_id   Target ID.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @param int    $limit       Number of followers to retrieve.
	 * @return array Array of user objects (ID, display_name, email).
	 */
	public static function get_target_followers( $target_id, $follow_type = 'story', $limit = 50 ) {
		$target_id = absint( $target_id );
		$limit = absint( $limit );

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return array();
		}

		// Try to get from transient cache
		$cache_key = 'fanfic_target_followers_' . $target_id . '_' . $follow_type . '_' . $limit;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.user_id, f.email_enabled, u.display_name, u.user_email as email
			FROM {$table_name} f
			INNER JOIN {$wpdb->users} u ON f.user_id = u.ID
			WHERE f.target_id = %d AND f.follow_type = %s
			ORDER BY f.created_at DESC
			LIMIT %d",
			$target_id,
			$follow_type,
			$limit
		), ARRAY_A );

		// Cache for 10 minutes
		set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get followers with email enabled for a target
	 *
	 * Used for sending email notifications when new content is published.
	 *
	 * @since 1.0.0
	 * @param int    $target_id   Target ID.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return array Array of user objects with email and email_enabled status.
	 */
	public static function get_followers_with_email_enabled( $target_id, $follow_type = 'story' ) {
		$target_id = absint( $target_id );

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return array();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.user_id, u.display_name, u.user_email as email
			FROM {$table_name} f
			INNER JOIN {$wpdb->users} u ON f.user_id = u.ID
			WHERE f.target_id = %d AND f.follow_type = %s AND f.email_enabled = 1
			ORDER BY f.created_at DESC",
			$target_id,
			$follow_type
		), ARRAY_A );

		return $results;
	}

	/**
	 * Batch get follow status for multiple targets
	 *
	 * Efficient method to check follow status for multiple targets at once.
	 * Prevents N+1 query problems.
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param array  $target_ids  Array of target IDs to check.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return array Array with target_id => [is_following, email_enabled].
	 */
	public static function batch_get_follow_status( $user_id, $target_ids, $follow_type = 'story' ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$target_ids = array_map( 'absint', (array) $target_ids );
		$target_ids = array_filter( $target_ids );

		if ( empty( $target_ids ) || ! $user_id ) {
			return array();
		}

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			return array();
		}

		// Initialize result array with all false
		$result = array();
		foreach ( $target_ids as $target_id ) {
			$result[ $target_id ] = array( 'is_following' => false, 'email_enabled' => false );
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $target_ids ), '%d' ) );

		// Prepare query parameters
		$query_params = array_merge( array( $user_id, $follow_type ), $target_ids );

		// Single query to get all followed targets
		$follows = $wpdb->get_results( $wpdb->prepare(
			"SELECT target_id, email_enabled FROM {$table_name}
			WHERE user_id = %d AND follow_type = %s AND target_id IN ($placeholders)",
			$query_params
		), ARRAY_A );

		// Update result array with actual follow status
		foreach ( $follows as $follow ) {
			$target_id = absint( $follow['target_id'] );
			$result[ $target_id ] = array(
				'is_following'  => true,
				'email_enabled' => (bool) $follow['email_enabled'],
			);
		}

		return $result;
	}

	/**
	 * Get follower count for an author
	 *
	 * @since 1.0.0
	 * @param int $author_id Author user ID.
	 * @return int Number of followers.
	 */
	public static function get_follower_count( $author_id ) {
		$author_id = absint( $author_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_follower_count_' . $author_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE target_id = %d AND follow_type = 'author'",
			$author_id
		) );

		$count = absint( $count );

		// Cache for 10 minutes
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get authors followed by a user
	 *
	 * @since 1.0.0
	 * @param int $follower_id User ID.
	 * @param int $per_page    Authors per page.
	 * @param int $page        Page number.
	 * @return array Array of author IDs with follow data.
	 */
	public static function get_followed_authors( $follower_id, $per_page = 20, $page = 1 ) {
		$follower_id = absint( $follower_id );
		$per_page = absint( $per_page );
		$page = absint( $page );
		$offset = ( $page - 1 ) * $per_page;

		// Try to get from transient cache
		$cache_key = 'fanfic_followed_authors_' . $follower_id . '_' . $per_page . '_' . $page;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT target_id as author_id, created_at FROM {$table_name}
			WHERE user_id = %d AND follow_type = 'author'
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$follower_id,
			$per_page,
			$offset
		) );

		// Cache for 5 minutes
		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get total follow count for a user
	 *
	 * @since 1.0.0
	 * @param int $follower_id User ID.
	 * @return int Number of authors followed.
	 */
	public static function get_user_follow_count( $follower_id ) {
		$follower_id = absint( $follower_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_user_follow_count_' . $follower_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND follow_type = 'author'",
			$follower_id
		) );

		$count = absint( $count );

		// Cache for 10 minutes
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get top authors by follower count
	 *
	 * @since 1.0.0
	 * @param int $limit        Number of authors to retrieve.
	 * @param int $min_followers Minimum number of followers required.
	 * @return array Array of author objects with follower counts.
	 */
	public static function get_top_authors( $limit = 10, $min_followers = 1 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_top_authors_' . $limit . '_' . $min_followers;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.target_id as author_id, COUNT(*) as follower_count
			FROM {$table_name} f
			INNER JOIN {$wpdb->users} u ON f.target_id = u.ID
			WHERE f.follow_type = 'author'
			GROUP BY f.target_id
			HAVING follower_count >= %d
			ORDER BY follower_count DESC
			LIMIT %d",
			absint( $min_followers ),
			absint( $limit )
		) );

		// Cache for 30 minutes
		set_transient( $cache_key, $results, 30 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get followers for an author
	 *
	 * @since 1.0.0
	 * @param int $author_id Author user ID.
	 * @param int $per_page  Followers per page.
	 * @param int $page      Page number.
	 * @return array Array of follower IDs with follow data.
	 */
	public static function get_author_followers( $author_id, $per_page = 20, $page = 1 ) {
		$author_id = absint( $author_id );
		$per_page = absint( $per_page );
		$page = absint( $page );
		$offset = ( $page - 1 ) * $per_page;

		// Try to get from transient cache
		$cache_key = 'fanfic_author_followers_' . $author_id . '_' . $per_page . '_' . $page;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id as follower_id, created_at FROM {$table_name}
			WHERE target_id = %d AND follow_type = 'author'
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$author_id,
			$per_page,
			$offset
		) );

		// Cache for 10 minutes
		set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get follow statistics
	 *
	 * @since 1.0.0
	 * @return array Array with follow statistics.
	 */
	public static function get_follow_stats() {
		// Try to get from transient cache
		$cache_key = 'fanfic_follow_stats';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$stats = array(
			'total_follows'         => 0,
			'unique_authors'        => 0,
			'unique_followers'      => 0,
			'avg_followers_per_author' => 0,
		);

		// Total follows
		$stats['total_follows'] = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) );

		// Unique authors followed
		$stats['unique_authors'] = absint( $wpdb->get_var( "SELECT COUNT(DISTINCT target_id) FROM {$table_name} WHERE follow_type = 'author'" ) );

		// Unique users who follow
		$stats['unique_followers'] = absint( $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE follow_type = 'author'" ) );

		// Average followers per author
		if ( $stats['unique_authors'] > 0 ) {
			$stats['avg_followers_per_author'] = round( $stats['total_follows'] / $stats['unique_authors'], 1 );
		}

		// Cache for 1 hour
		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Create notification when user follows a target (story or author)
	 *
	 * @since 1.0.0
	 * @param int    $target_id   Target ID (story or author ID).
	 * @param int    $user_id     User ID (follower).
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return void
	 */
	private static function create_follow_notification( $target_id, $user_id, $follow_type ) {
		$follower = get_userdata( $user_id );
		if ( ! $follower ) {
			return;
		}

		// Check if we recently created this same notification (within 20 minutes)
		// This prevents duplicate notifications when users rapidly toggle follow/unfollow
		$cooldown_key = 'fanfic_follow_notify_' . $user_id . '_' . $target_id . '_' . $follow_type;
		if ( get_transient( $cooldown_key ) ) {
			// Recent notification exists, skip creating duplicate
			return;
		}

		// Set cooldown transient for 20 minutes
		set_transient( $cooldown_key, true, 20 * MINUTE_IN_SECONDS );

		$follower_name = $follower->display_name;
		$follower_url = fanfic_get_user_profile_url( $user_id );

		if ( 'author' === $follow_type ) {
			// Author follow notification
			$message = sprintf(
				/* translators: %s: follower name */
				__( '%s started following you.', 'fanfiction-manager' ),
				$follower_name
			);

			// Create in-app notification if user preferences allow
			if ( class_exists( 'Fanfic_Notification_Preferences' ) && class_exists( 'Fanfic_Notifications' ) ) {
				if ( Fanfic_Notification_Preferences::should_create_inapp( $target_id, Fanfic_Notifications::TYPE_NEW_FOLLOWER ) ) {
					Fanfic_Notifications::create_notification(
						$target_id,
						Fanfic_Notifications::TYPE_NEW_FOLLOWER,
						$message,
						$follower_url
					);
				}

				// Queue email notification if user preferences allow
				if ( Fanfic_Notification_Preferences::should_send_email( $target_id, Fanfic_Notifications::TYPE_NEW_FOLLOWER ) ) {
					if ( class_exists( 'Fanfic_Email_Sender' ) ) {
						Fanfic_Email_Sender::queue_email(
							$target_id,
							Fanfic_Notifications::TYPE_NEW_FOLLOWER,
							array(
								'follower_name' => $follower_name,
								'follower_url'  => $follower_url,
							)
						);
					}
				}
			}
		} elseif ( 'story' === $follow_type ) {
			// Story follow notification - notify story author
			$story = get_post( $target_id );
			if ( ! $story ) {
				return;
			}

			$author_id = $story->post_author;
			$story_title = get_the_title( $target_id );
			$story_url = get_permalink( $target_id );

			$message = sprintf(
				/* translators: 1: follower name, 2: story title */
				__( '%1$s started following your story "%2$s".', 'fanfiction-manager' ),
				$follower_name,
				$story_title
			);

			// Create in-app notification if user preferences allow
			if ( class_exists( 'Fanfic_Notification_Preferences' ) && class_exists( 'Fanfic_Notifications' ) ) {
				if ( Fanfic_Notification_Preferences::should_create_inapp( $author_id, Fanfic_Notifications::TYPE_NEW_FOLLOWER ) ) {
					Fanfic_Notifications::create_notification(
						$author_id,
						Fanfic_Notifications::TYPE_NEW_FOLLOWER,
						$message,
						$story_url
					);
				}
			}
		}
	}

	/**
	 * Notify followers when author publishes a new story
	 *
	 * @since 1.0.0
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public static function notify_followers_on_publish( $new_status, $old_status, $post ) {
		// Only proceed if this is a story being published
		if ( 'fanfiction_story' !== $post->post_type ) {
			return;
		}

		// Only notify if transitioning to publish (not if already published)
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		$author_id = $post->post_author;

		// Get all followers of this author
		$followers = self::get_all_followers( $author_id );

		if ( empty( $followers ) ) {
			return;
		}

		$story_title = get_the_title( $post->ID );
		$story_url = get_permalink( $post->ID );
		$author = get_userdata( $author_id );
		$author_name = $author->display_name;

		// Create notification message
		$message = sprintf(
			/* translators: 1: author name, 2: story title */
			__( '%1$s published a new story: "%2$s"', 'fanfiction-manager' ),
			$author_name,
			$story_title
		);

		foreach ( $followers as $follower_id ) {
			// Create in-app notification if user preferences allow
			if ( Fanfic_Notification_Preferences::should_create_inapp( $follower_id, Fanfic_Notifications::TYPE_NEW_STORY ) ) {
				Fanfic_Notifications::create_notification(
					$follower_id,
					Fanfic_Notifications::TYPE_NEW_STORY,
					$message,
					$story_url
				);
			}

			// Queue email notification if user preferences allow
			if ( Fanfic_Notification_Preferences::should_send_email( $follower_id, Fanfic_Notifications::TYPE_NEW_STORY ) ) {
				Fanfic_Email_Sender::queue_email(
					$follower_id,
					Fanfic_Notifications::TYPE_NEW_STORY,
					array(
						'author_name'   => $author_name,
						'content_title' => $story_title,
						'content_url'   => $story_url,
					)
				);
			}
		}

		// Trigger action for extensibility
		do_action( 'fanfic_author_published_story', $author_id, $post->ID, $followers );
	}

	/**
	 * Get all followers for an author (no pagination)
	 *
	 * @since 1.0.0
	 * @param int $author_id Author user ID.
	 * @return array Array of follower IDs.
	 */
	private static function get_all_followers( $author_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$table_name} WHERE target_id = %d AND follow_type = 'author'",
			absint( $author_id )
		) );

		return array_map( 'absint', $results );
	}

	/**
	 * Clear follow cache
	 *
	 * @since 1.0.0
	 * @param int    $target_id   Target ID.
	 * @param int    $user_id     User ID.
	 * @param string $follow_type Follow type.
	 * @return void
	 */
	private static function clear_follow_cache( $target_id, $user_id, $follow_type = 'story' ) {
		$target_id = absint( $target_id );
		$user_id = absint( $user_id );

		// Clear target-specific cache (for both author and story follows)
		delete_transient( 'fanfic_follower_count_' . $target_id );

		// Clear user-specific caches
		delete_transient( 'fanfic_user_follow_count_' . $user_id );

		// Clear object cache for is_following and is_email_enabled checks
		wp_cache_delete( 'fanfic_is_following_' . $user_id . '_' . $target_id . '_' . $follow_type, 'fanfic_follows' );
		wp_cache_delete( 'fanfic_email_enabled_' . $user_id . '_' . $target_id . '_' . $follow_type, 'fanfic_follows' );

		// Clear user follows caches - use wildcard-like approach
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_fanfic_user_follows_' . $user_id . '_%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_fanfic_user_follows_' . $user_id . '_%' ) );

		// Clear target followers caches
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_fanfic_target_followers_' . $target_id . '_%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_fanfic_target_followers_' . $target_id . '_%' ) );

		// Clear paginated caches (legacy - for backwards compatibility)
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_transient( 'fanfic_followed_authors_' . $user_id . '_20_' . $i );
			delete_transient( 'fanfic_followed_authors_' . $user_id . '_15_' . $i );
			delete_transient( 'fanfic_followed_authors_' . $user_id . '_10_' . $i );
			delete_transient( 'fanfic_author_followers_' . $target_id . '_20_' . $i );
			delete_transient( 'fanfic_author_followers_' . $target_id . '_15_' . $i );
			delete_transient( 'fanfic_author_followers_' . $target_id . '_10_' . $i );
		}

		// Clear top authors caches
		for ( $i = 5; $i <= 20; $i += 5 ) {
			for ( $j = 1; $j <= 5; $j++ ) {
				delete_transient( 'fanfic_top_authors_' . $i . '_' . $j );
			}
		}

		// Clear stats cache
		delete_transient( 'fanfic_follow_stats' );
	}
}
