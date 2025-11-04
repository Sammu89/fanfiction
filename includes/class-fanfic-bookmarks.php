<?php
/**
 * Bookmarks System Class
 *
 * Handles story bookmark functionality with comprehensive features.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Bookmarks
 *
 * Comprehensive bookmark system for stories.
 *
 * @since 1.0.0
 */
class Fanfic_Bookmarks {

	/**
	 * Initialize bookmarks system
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// AJAX handlers already registered in class-fanfic-shortcodes-actions.php
		// This class provides helper methods for bookmark functionality
	}

	/**
	 * Add bookmark for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool|int Bookmark ID on success, false on failure.
	 */
	public static function add_bookmark( $story_id, $user_id ) {
		global $wpdb;

		// Validate inputs
		$story_id = absint( $story_id );
		$user_id = absint( $user_id );

		if ( ! $story_id || ! $user_id ) {
			return false;
		}

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Check if already bookmarked
		if ( self::is_bookmarked( $story_id, $user_id ) ) {
			return false;
		}

		// Insert bookmark
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'story_id'   => $story_id,
				'user_id'    => $user_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		if ( false !== $inserted ) {
			// Clear caches
			self::clear_bookmark_cache( $story_id, $user_id );

			// Trigger action for extensibility
			do_action( 'fanfic_story_bookmarked', $story_id, $user_id );

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Remove bookmark for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_bookmark( $story_id, $user_id ) {
		global $wpdb;

		// Validate inputs
		$story_id = absint( $story_id );
		$user_id = absint( $user_id );

		if ( ! $story_id || ! $user_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Delete bookmark
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false !== $deleted ) {
			// Clear caches
			self::clear_bookmark_cache( $story_id, $user_id );

			// Trigger action for extensibility
			do_action( 'fanfic_story_unbookmarked', $story_id, $user_id );

			return true;
		}

		return false;
	}

	/**
	 * Check if story is bookmarked by user
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool True if bookmarked, false otherwise.
	 */
	public static function is_bookmarked( $story_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND user_id = %d LIMIT 1",
			absint( $story_id ),
			absint( $user_id )
		) );

		return ! empty( $exists );
	}

	/**
	 * Get bookmark count for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Number of bookmarks.
	 */
	public static function get_bookmark_count( $story_id ) {
		$story_id = absint( $story_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_bookmark_count_' . $story_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE story_id = %d",
			$story_id
		) );

		$count = absint( $count );

		// Cache for 10 minutes
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get user's bookmarked stories
	 *
	 * @since 1.0.0
	 * @param int $user_id  User ID.
	 * @param int $per_page Stories per page.
	 * @param int $page     Page number.
	 * @return array Array of story IDs.
	 */
	public static function get_user_bookmarks( $user_id, $per_page = 20, $page = 1 ) {
		$user_id = absint( $user_id );
		$per_page = absint( $per_page );
		$page = absint( $page );
		$offset = ( $page - 1 ) * $per_page;

		// Try to get from transient cache
		$cache_key = 'fanfic_user_bookmarks_' . $user_id . '_' . $per_page . '_' . $page;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT story_id, created_at FROM {$table_name}
			WHERE user_id = %d
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$user_id,
			$per_page,
			$offset
		) );

		// Cache for 5 minutes
		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get total bookmark count for a user
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Number of bookmarks.
	 */
	public static function get_user_bookmark_count( $user_id ) {
		$user_id = absint( $user_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_user_bookmark_count_' . $user_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
			$user_id
		) );

		$count = absint( $count );

		// Cache for 10 minutes
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get most bookmarked stories
	 *
	 * @since 1.0.0
	 * @param int $limit      Number of stories to retrieve.
	 * @param int $min_bookmarks Minimum number of bookmarks required.
	 * @return array Array of story objects with bookmark counts.
	 */
	public static function get_most_bookmarked_stories( $limit = 10, $min_bookmarks = 1 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_most_bookmarked_stories_' . $limit . '_' . $min_bookmarks;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT b.story_id, COUNT(*) as bookmark_count
			FROM {$table_name} b
			INNER JOIN {$wpdb->posts} p ON b.story_id = p.ID
			WHERE p.post_type = 'fanfiction_story'
			AND p.post_status = 'publish'
			GROUP BY b.story_id
			HAVING bookmark_count >= %d
			ORDER BY bookmark_count DESC
			LIMIT %d",
			absint( $min_bookmarks ),
			absint( $limit )
		) );

		// Cache for 30 minutes
		set_transient( $cache_key, $results, 30 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get recently bookmarked stories
	 *
	 * @since 1.0.0
	 * @param int $limit Number of stories to retrieve.
	 * @return array Array of story IDs.
	 */
	public static function get_recently_bookmarked_stories( $limit = 10 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_recently_bookmarked_stories_' . $limit;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT b.story_id
			FROM {$table_name} b
			INNER JOIN {$wpdb->posts} p ON b.story_id = p.ID
			WHERE p.post_type = 'fanfiction_story'
			AND p.post_status = 'publish'
			ORDER BY b.created_at DESC
			LIMIT %d",
			absint( $limit )
		) );

		$story_ids = array_map( 'absint', $results );

		// Cache for 5 minutes
		set_transient( $cache_key, $story_ids, 5 * MINUTE_IN_SECONDS );

		return $story_ids;
	}

	/**
	 * Get bookmark statistics
	 *
	 * @since 1.0.0
	 * @return array Array with bookmark statistics.
	 */
	public static function get_bookmark_stats() {
		// Try to get from transient cache
		$cache_key = 'fanfic_bookmark_stats';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$stats = array(
			'total_bookmarks'      => 0,
			'unique_stories'       => 0,
			'unique_users'         => 0,
			'avg_bookmarks_per_story' => 0,
		);

		// Total bookmarks
		$stats['total_bookmarks'] = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) );

		// Unique stories bookmarked
		$stats['unique_stories'] = absint( $wpdb->get_var( "SELECT COUNT(DISTINCT story_id) FROM {$table_name}" ) );

		// Unique users who bookmarked
		$stats['unique_users'] = absint( $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table_name}" ) );

		// Average bookmarks per story
		if ( $stats['unique_stories'] > 0 ) {
			$stats['avg_bookmarks_per_story'] = round( $stats['total_bookmarks'] / $stats['unique_stories'], 1 );
		}

		// Cache for 1 hour
		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Clear bookmark cache
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return void
	 */
	private static function clear_bookmark_cache( $story_id, $user_id ) {
		$story_id = absint( $story_id );
		$user_id = absint( $user_id );

		// Clear story-specific cache
		delete_transient( 'fanfic_bookmark_count_' . $story_id );

		// Clear user-specific caches
		delete_transient( 'fanfic_user_bookmark_count_' . $user_id );

		// Clear paginated user bookmark caches (up to 10 pages)
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_transient( 'fanfic_user_bookmarks_' . $user_id . '_20_' . $i );
			delete_transient( 'fanfic_user_bookmarks_' . $user_id . '_15_' . $i );
			delete_transient( 'fanfic_user_bookmarks_' . $user_id . '_10_' . $i );
		}

		// Clear list caches
		delete_transient( 'fanfic_recently_bookmarked_stories_10' );
		delete_transient( 'fanfic_recently_bookmarked_stories_20' );

		// Clear most bookmarked caches
		for ( $i = 5; $i <= 20; $i += 5 ) {
			for ( $j = 1; $j <= 5; $j++ ) {
				delete_transient( 'fanfic_most_bookmarked_stories_' . $i . '_' . $j );
			}
		}

		// Clear stats cache
		delete_transient( 'fanfic_bookmark_stats' );
	}
}
