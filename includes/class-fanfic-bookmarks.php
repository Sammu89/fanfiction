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
 * Comprehensive bookmark system for stories and chapters.
 * Supports both story and chapter bookmarks with type differentiation.
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
	 * Toggle bookmark for a post (story or chapter)
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param int    $post_id       Post ID (story or chapter).
	 * @param string $bookmark_type Type: 'story' or 'chapter'.
	 * @return array Result with is_bookmarked status.
	 */
	public static function toggle_bookmark( $user_id, $post_id, $bookmark_type = 'story' ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$post_id = absint( $post_id );
		$bookmark_type = sanitize_text_field( $bookmark_type );

		if ( ! $user_id || ! $post_id ) {
			return array( 'success' => false, 'error' => 'Invalid parameters' );
		}

		// Validate bookmark type
		if ( ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return array( 'success' => false, 'error' => 'Invalid bookmark type' );
		}

		// Verify post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'Post not found' );
		}

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Check if already bookmarked
		$is_bookmarked = self::is_bookmarked( $user_id, $post_id, $bookmark_type );

		if ( $is_bookmarked ) {
			// Remove bookmark
			$deleted = $wpdb->delete(
				$table_name,
				array(
					'user_id'       => $user_id,
					'post_id'       => $post_id,
					'bookmark_type' => $bookmark_type,
				),
				array( '%d', '%d', '%s' )
			);

			if ( false !== $deleted ) {
				self::clear_bookmark_cache( $post_id, $user_id, $bookmark_type );
				do_action( 'fanfic_bookmark_removed', $post_id, $user_id, $bookmark_type );
				return array( 'success' => true, 'is_bookmarked' => false );
			}
		} else {
			// Add bookmark
			$inserted = $wpdb->insert(
				$table_name,
				array(
					'user_id'       => $user_id,
					'post_id'       => $post_id,
					'bookmark_type' => $bookmark_type,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);

			if ( false !== $inserted ) {
				self::clear_bookmark_cache( $post_id, $user_id, $bookmark_type );
				do_action( 'fanfic_bookmark_added', $post_id, $user_id, $bookmark_type );
				return array( 'success' => true, 'is_bookmarked' => true );
			}
		}

		return array( 'success' => false, 'error' => 'Database operation failed' );
	}

	/**
	 * Add bookmark for a post (legacy method - maintained for backwards compatibility)
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool|int Bookmark ID on success, false on failure.
	 */
	public static function add_bookmark( $story_id, $user_id ) {
		$result = self::toggle_bookmark( $user_id, $story_id, 'story' );
		return $result['success'] ? true : false;
	}

	/**
	 * Remove bookmark for a post
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param int    $post_id       Post ID.
	 * @param string $bookmark_type Type: 'story' or 'chapter'.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_bookmark( $user_id, $post_id, $bookmark_type = 'story' ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$post_id = absint( $post_id );
		$bookmark_type = sanitize_text_field( $bookmark_type );

		if ( ! $user_id || ! $post_id ) {
			return false;
		}

		// Validate bookmark type
		if ( ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Delete bookmark
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'user_id'       => $user_id,
				'post_id'       => $post_id,
				'bookmark_type' => $bookmark_type,
			),
			array( '%d', '%d', '%s' )
		);

		if ( false !== $deleted ) {
			self::clear_bookmark_cache( $post_id, $user_id, $bookmark_type );
			do_action( 'fanfic_bookmark_removed', $post_id, $user_id, $bookmark_type );
			return true;
		}

		return false;
	}

	/**
	 * Check if post is bookmarked by user
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param int    $post_id       Post ID.
	 * @param string $bookmark_type Type: 'story' or 'chapter'.
	 * @return bool True if bookmarked, false otherwise.
	 */
	public static function is_bookmarked( $user_id, $post_id, $bookmark_type = 'story' ) {
		global $wpdb;

		// Validate bookmark type
		if ( ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return false;
		}

		// Try cache first
		$cache_key = 'fanfic_is_bookmarked_' . $user_id . '_' . $post_id . '_' . $bookmark_type;
		$cached = wp_cache_get( $cache_key, 'fanfic_bookmarks' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE user_id = %d AND post_id = %d AND bookmark_type = %s LIMIT 1",
			absint( $user_id ),
			absint( $post_id ),
			$bookmark_type
		) );

		$is_bookmarked = ! empty( $exists );

		// Cache result for 5 minutes
		wp_cache_set( $cache_key, $is_bookmarked, 'fanfic_bookmarks', 5 * MINUTE_IN_SECONDS );

		return $is_bookmarked;
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
	 * Get user's bookmarked posts
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param string $bookmark_type Type: 'story', 'chapter', or null for all.
	 * @param int    $limit         Number of bookmarks to retrieve.
	 * @param int    $offset        Offset for pagination.
	 * @return array Array of post IDs with metadata.
	 */
	public static function get_user_bookmarks( $user_id, $bookmark_type = null, $limit = 50, $offset = 0 ) {
		$user_id = absint( $user_id );
		$limit = absint( $limit );
		$offset = absint( $offset );

		// Validate bookmark type if provided
		if ( null !== $bookmark_type && ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return array();
		}

		// Try to get from transient cache
		$cache_key = 'fanfic_user_bookmarks_' . $user_id . '_' . ( $bookmark_type ?? 'all' ) . '_' . $limit . '_' . $offset;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		if ( null === $bookmark_type ) {
			// Get all bookmarks
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT post_id, bookmark_type, created_at FROM {$table_name}
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			), ARRAY_A );
		} else {
			// Get bookmarks filtered by type
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT post_id, bookmark_type, created_at FROM {$table_name}
				WHERE user_id = %d AND bookmark_type = %s
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$bookmark_type,
				$limit,
				$offset
			), ARRAY_A );
		}

		// Cache for 5 minutes
		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get total bookmark count for a user
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param string $bookmark_type Type: 'story', 'chapter', or null for all.
	 * @return int Number of bookmarks.
	 */
	public static function get_bookmarks_count( $user_id, $bookmark_type = null ) {
		$user_id = absint( $user_id );

		// Validate bookmark type if provided
		if ( null !== $bookmark_type && ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return 0;
		}

		// Try to get from transient cache
		$cache_key = 'fanfic_user_bookmark_count_' . $user_id . '_' . ( $bookmark_type ?? 'all' );
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		if ( null === $bookmark_type ) {
			// Count all bookmarks
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
				$user_id
			) );
		} else {
			// Count bookmarks filtered by type
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND bookmark_type = %s",
				$user_id,
				$bookmark_type
			) );
		}

		$count = absint( $count );

		// Cache for 10 minutes
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Legacy method - maintained for backwards compatibility
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Number of bookmarks.
	 */
	public static function get_user_bookmark_count( $user_id ) {
		return self::get_bookmarks_count( $user_id, 'story' );
	}

	/**
	 * Batch get bookmark status for multiple posts
	 *
	 * Efficient method to check bookmark status for multiple posts at once.
	 * Prevents N+1 query problems.
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param array  $post_ids      Array of post IDs to check.
	 * @param string $bookmark_type Type: 'story' or 'chapter'.
	 * @return array Array with post_id => boolean (is_bookmarked).
	 */
	public static function batch_get_bookmark_status( $user_id, $post_ids, $bookmark_type = 'story' ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$post_ids = array_map( 'absint', (array) $post_ids );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) || ! $user_id ) {
			return array();
		}

		// Validate bookmark type
		if ( ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return array();
		}

		// Initialize result array with all false
		$result = array_fill_keys( $post_ids, false );

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// Prepare query parameters
		$query_params = array_merge( array( $user_id, $bookmark_type ), $post_ids );

		// Single query to get all bookmarked posts
		$bookmarked = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$table_name}
			WHERE user_id = %d AND bookmark_type = %s AND post_id IN ($placeholders)",
			$query_params
		) );

		// Mark bookmarked posts as true
		foreach ( $bookmarked as $post_id ) {
			$result[ absint( $post_id ) ] = true;
		}

		return $result;
	}

	/**
	 * Get user's recent bookmarks
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param int    $limit         Number of bookmarks to retrieve.
	 * @param string $bookmark_type Type: 'story', 'chapter', or null for all.
	 * @return array Array of post IDs with metadata.
	 */
	public static function get_user_recent_bookmarks( $user_id, $limit = 10, $bookmark_type = null ) {
		return self::get_user_bookmarks( $user_id, $bookmark_type, $limit, 0 );
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
	 * @param int    $post_id       Post ID.
	 * @param int    $user_id       User ID.
	 * @param string $bookmark_type Bookmark type.
	 * @return void
	 */
	private static function clear_bookmark_cache( $post_id, $user_id, $bookmark_type = 'story' ) {
		$post_id = absint( $post_id );
		$user_id = absint( $user_id );

		// Clear post-specific cache
		delete_transient( 'fanfic_bookmark_count_' . $post_id );

		// Clear user-specific caches for all types
		delete_transient( 'fanfic_user_bookmark_count_' . $user_id . '_all' );
		delete_transient( 'fanfic_user_bookmark_count_' . $user_id . '_story' );
		delete_transient( 'fanfic_user_bookmark_count_' . $user_id . '_chapter' );
		delete_transient( 'fanfic_user_bookmark_count_' . $user_id ); // Legacy

		// Clear object cache for is_bookmarked checks
		wp_cache_delete( 'fanfic_is_bookmarked_' . $user_id . '_' . $post_id . '_' . $bookmark_type, 'fanfic_bookmarks' );

		// Clear paginated user bookmark caches - use wildcard-like approach
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fanfic_user_bookmarks_{$user_id}_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fanfic_user_bookmarks_{$user_id}_%'" );

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
