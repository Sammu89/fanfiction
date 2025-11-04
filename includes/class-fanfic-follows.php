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
 * Comprehensive follow system for authors.
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
		// AJAX handlers already registered in class-fanfic-shortcodes-actions.php
		// This class provides helper methods for follow functionality

		// Hook into story publication to notify followers
		add_action( 'transition_post_status', array( __CLASS__, 'notify_followers_on_publish' ), 10, 3 );
	}

	/**
	 * Follow an author
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return bool|int Follow ID on success, false on failure.
	 */
	public static function follow_author( $author_id, $follower_id ) {
		global $wpdb;

		// Validate inputs
		$author_id = absint( $author_id );
		$follower_id = absint( $follower_id );

		if ( ! $author_id || ! $follower_id ) {
			return false;
		}

		// Prevent self-following
		if ( $author_id === $follower_id ) {
			return false;
		}

		// Verify author exists
		$author = get_userdata( $author_id );
		if ( ! $author ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Check if already following
		if ( self::is_following( $author_id, $follower_id ) ) {
			return false;
		}

		// Insert follow
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'author_id'   => $author_id,
				'follower_id' => $follower_id,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		if ( false !== $inserted ) {
			// Clear caches
			self::clear_follow_cache( $author_id, $follower_id );

			// Create notification for author
			self::create_follow_notification( $author_id, $follower_id );

			// Trigger action for extensibility
			do_action( 'fanfic_author_followed', $author_id, $follower_id );

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Unfollow an author
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return bool True on success, false on failure.
	 */
	public static function unfollow_author( $author_id, $follower_id ) {
		global $wpdb;

		// Validate inputs
		$author_id = absint( $author_id );
		$follower_id = absint( $follower_id );

		if ( ! $author_id || ! $follower_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Delete follow
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'author_id'   => $author_id,
				'follower_id' => $follower_id,
			),
			array( '%d', '%d' )
		);

		if ( false !== $deleted ) {
			// Clear caches
			self::clear_follow_cache( $author_id, $follower_id );

			// Trigger action for extensibility
			do_action( 'fanfic_author_unfollowed', $author_id, $follower_id );

			return true;
		}

		return false;
	}

	/**
	 * Check if user is following an author
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return bool True if following, false otherwise.
	 */
	public static function is_following( $author_id, $follower_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_follows';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE author_id = %d AND follower_id = %d LIMIT 1",
			absint( $author_id ),
			absint( $follower_id )
		) );

		return ! empty( $exists );
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
			"SELECT COUNT(*) FROM {$table_name} WHERE author_id = %d",
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
			"SELECT author_id, created_at FROM {$table_name}
			WHERE follower_id = %d
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
			"SELECT COUNT(*) FROM {$table_name} WHERE follower_id = %d",
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
			"SELECT f.author_id, COUNT(*) as follower_count
			FROM {$table_name} f
			INNER JOIN {$wpdb->users} u ON f.author_id = u.ID
			GROUP BY f.author_id
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
			"SELECT follower_id, created_at FROM {$table_name}
			WHERE author_id = %d
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
		$stats['unique_authors'] = absint( $wpdb->get_var( "SELECT COUNT(DISTINCT author_id) FROM {$table_name}" ) );

		// Unique users who follow
		$stats['unique_followers'] = absint( $wpdb->get_var( "SELECT COUNT(DISTINCT follower_id) FROM {$table_name}" ) );

		// Average followers per author
		if ( $stats['unique_authors'] > 0 ) {
			$stats['avg_followers_per_author'] = round( $stats['total_follows'] / $stats['unique_authors'], 1 );
		}

		// Cache for 1 hour
		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Create notification when user follows an author
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return void
	 */
	private static function create_follow_notification( $author_id, $follower_id ) {
		$follower = get_userdata( $follower_id );
		if ( ! $follower ) {
			return;
		}

		$follower_name = $follower->display_name;
		$follower_url = get_author_posts_url( $follower_id );

		// Create notification message
		$message = sprintf(
			/* translators: %s: follower name */
			__( '%s started following you.', 'fanfiction-manager' ),
			$follower_name
		);

		// Create in-app notification if user preferences allow
		if ( Fanfic_Notification_Preferences::should_create_inapp( $author_id, Fanfic_Notifications::TYPE_NEW_FOLLOWER ) ) {
			Fanfic_Notifications::create_notification(
				$author_id,
				Fanfic_Notifications::TYPE_NEW_FOLLOWER,
				$message,
				$follower_url
			);
		}

		// Queue email notification if user preferences allow
		if ( Fanfic_Notification_Preferences::should_send_email( $author_id, Fanfic_Notifications::TYPE_NEW_FOLLOWER ) ) {
			Fanfic_Email_Sender::queue_email(
				$author_id,
				Fanfic_Notifications::TYPE_NEW_FOLLOWER,
				array(
					'follower_name' => $follower_name,
					'follower_url'  => $follower_url,
				)
			);
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
			"SELECT follower_id FROM {$table_name} WHERE author_id = %d",
			absint( $author_id )
		) );

		return array_map( 'absint', $results );
	}

	/**
	 * Clear follow cache
	 *
	 * @since 1.0.0
	 * @param int $author_id   Author user ID.
	 * @param int $follower_id Follower user ID.
	 * @return void
	 */
	private static function clear_follow_cache( $author_id, $follower_id ) {
		$author_id = absint( $author_id );
		$follower_id = absint( $follower_id );

		// Clear author-specific cache
		delete_transient( 'fanfic_follower_count_' . $author_id );

		// Clear follower-specific caches
		delete_transient( 'fanfic_user_follow_count_' . $follower_id );

		// Clear paginated caches (up to 10 pages)
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_transient( 'fanfic_followed_authors_' . $follower_id . '_20_' . $i );
			delete_transient( 'fanfic_followed_authors_' . $follower_id . '_15_' . $i );
			delete_transient( 'fanfic_followed_authors_' . $follower_id . '_10_' . $i );
			delete_transient( 'fanfic_author_followers_' . $author_id . '_20_' . $i );
			delete_transient( 'fanfic_author_followers_' . $author_id . '_15_' . $i );
			delete_transient( 'fanfic_author_followers_' . $author_id . '_10_' . $i );
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
