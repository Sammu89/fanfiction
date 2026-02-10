<?php
/**
 * Caching System Class
 *
 * Provides comprehensive transient caching functionality with versioning,
 * automatic invalidation, and support for object caching (Redis, Memcached).
 *
 * This class manages all caching operations for the Fanfiction Manager plugin,
 * including story data, chapter counts, user bookmarks, ratings, and lists.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fanfic_Cache class
 *
 * Handles all transient caching operations with versioning and invalidation.
 *
 * @since 1.0.0
 */
class Fanfic_Cache {

	/**
	 * Cache version
	 *
	 * Increment this to invalidate all caches when data structures change.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CACHE_VERSION = '1.0.0';

	/**
	 * Base prefix for all transients
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BASE_PREFIX = 'ffm_';

	/**
	 * Cache duration: Realtime (1 minute)
	 *
	 * Use for highly volatile data that changes frequently.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const REALTIME = 60;

	/**
	 * Cache duration: Short (5 minutes)
	 *
	 * Use for data that changes moderately often (e.g., ratings, views).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const SHORT = 300;

	/**
	 * Cache duration: Medium (15 minutes)
	 *
	 * Use for data that changes occasionally (e.g., bookmark counts).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MEDIUM = 900;

	/**
	 * Cache duration: Long (30 minutes)
	 *
	 * Use for relatively stable data (e.g., chapter lists).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const LONG = 1800;

	/**
	 * Cache duration: Day (24 hours)
	 *
	 * Use for stable data that rarely changes (e.g., author statistics).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DAY = 86400;

	/**
	 * Cache duration: Week (7 days)
	 *
	 * Use for very stable data (e.g., story metadata).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const WEEK = 604800;

	/**
	 * Generate a versioned transient key
	 *
	 * Creates a unique cache key with versioning support to allow
	 * selective invalidation when data structures change.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type     Type of cached data (e.g., 'story', 'chapter', 'user').
	 * @param string $subtype  Subtype or action (e.g., 'validity', 'count', 'bookmarks').
	 * @param int    $id       Optional. ID of the item being cached.
	 * @param string $version  Optional. Custom version string. Defaults to CACHE_VERSION.
	 * @return string The generated cache key.
	 */
	public static function get_key( $type, $subtype, $id = 0, $version = '' ) {
		$version = $version ? $version : self::CACHE_VERSION;

		// Sanitize components
		$type    = sanitize_key( $type );
		$subtype = sanitize_key( $subtype );
		$id      = absint( $id );
		$version = sanitize_key( $version );

		// Build key
		$key = self::BASE_PREFIX . $type . '_' . $subtype;

		if ( $id > 0 ) {
			$key .= '_' . $id;
		}

		$key .= '_v' . $version;

		// WordPress transient names are limited to 172 characters
		// (191 chars max for varchar, minus 11 for '_transient_' prefix)
		if ( strlen( $key ) > 172 ) {
			// Hash long keys to ensure uniqueness
			$key = self::BASE_PREFIX . md5( $key );
		}

		return $key;
	}

	/**
	 * Get cached data or regenerate it
	 *
	 * Retrieves data from cache if available, otherwise calls the callback
	 * to regenerate the data and stores it in cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $key      The cache key.
	 * @param callable $callback Function to generate data if cache miss.
	 * @param int      $ttl      Time to live in seconds. Use class constants.
	 * @return mixed The cached or freshly generated data.
	 */
	public static function get( $key, $callback, $ttl = self::MEDIUM ) {
		// Try to get from cache
		$data = get_transient( $key );

		// Cache hit
		if ( false !== $data ) {
			return $data;
		}

		// Cache miss - regenerate data
		if ( is_callable( $callback ) ) {
			$data = call_user_func( $callback );

			// Store in cache
			self::set( $key, $data, $ttl );

			return $data;
		}

		// Callback not callable
		return false;
	}

	/**
	 * Store data in cache
	 *
	 * Sets a transient with the specified TTL. If object caching is available
	 * (Redis, Memcached), it will be used automatically.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The data to cache.
	 * @param int    $ttl   Time to live in seconds. Use class constants.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value, $ttl = self::MEDIUM ) {
		// Sanitize TTL
		$ttl = absint( $ttl );

		// Set transient
		return set_transient( $key, $value, $ttl );
	}

	/**
	 * Delete a single cached item
	 *
	 * Removes a specific transient from the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		return delete_transient( $key );
	}

	/**
	 * Delete all transients matching a prefix
	 *
	 * Removes all transients that start with the specified prefix.
	 * Useful for bulk invalidation (e.g., all story-related caches).
	 *
	 * Note: This queries the options table directly and may be slow
	 * on sites with many options. Use sparingly.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix The prefix to match (e.g., 'ffm_story_').
	 * @return int Number of transients deleted.
	 */
	public static function delete_by_prefix( $prefix ) {
		global $wpdb;

		// Sanitize prefix
		$prefix = sanitize_key( $prefix );

		// If prefix doesn't start with base prefix, prepend it
		if ( 0 !== strpos( $prefix, self::BASE_PREFIX ) ) {
			$prefix = self::BASE_PREFIX . $prefix;
		}

		// Build SQL pattern
		$pattern = $wpdb->esc_like( '_transient_' . $prefix ) . '%';

		// Count deleted transients for return value
		$count_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			$pattern
		);
		$count = $wpdb->get_var( $count_sql );

		// Delete matching transients
		$delete_sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$pattern
		);
		$wpdb->query( $delete_sql );

		// Also delete timeout entries
		$timeout_pattern = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$timeout_sql = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$timeout_pattern
		);
		$wpdb->query( $timeout_sql );

		// If using object cache, clear it too
		if ( self::is_object_cache_active() ) {
			wp_cache_flush();
		}

		return intval( $count );
	}

	/**
	 * Invalidate all caches related to a specific story
	 *
	 * Clears story validity, chapter counts, ratings, bookmarks,
	 * and related list caches when a story is updated.
	 *
	 * @since 1.0.0
	 *
	 * @param int $story_id The story post ID.
	 * @return void
	 */
	public static function invalidate_story( $story_id ) {
		$story_id = absint( $story_id );

		if ( $story_id <= 0 ) {
			return;
		}

		// Story-specific caches
		$types = array(
			'validity',
			'chapter_count',
			'chapters',
			'rating',
			'bookmark_count',
			'view_count',
			'metadata',
		);

		foreach ( $types as $type ) {
			$key = self::get_key( 'story', $type, $story_id );
			self::delete( $key );
		}

		// Also invalidate author's story list
		$author_id = get_post_field( 'post_author', $story_id );
		if ( $author_id ) {
			self::invalidate_user( $author_id );
		}

		// Invalidate list caches (story appears in archives)
		self::invalidate_lists();

		/**
		 * Fires after story caches are invalidated
		 *
		 * @since 1.0.0
		 *
		 * @param int $story_id The story ID.
		 */
		do_action( 'fanfic_cache_invalidate_story', $story_id );
	}

	/**
	 * Invalidate all caches related to a specific chapter
	 *
	 * Clears chapter data and updates parent story caches.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chapter_id The chapter post ID.
	 * @return void
	 */
	public static function invalidate_chapter( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		if ( $chapter_id <= 0 ) {
			return;
		}

		// Chapter-specific caches
		$types = array(
			'content',
			'rating',
			'view_count',
			'metadata',
		);

		foreach ( $types as $type ) {
			$key = self::get_key( 'chapter', $type, $chapter_id );
			self::delete( $key );
		}

		// Invalidate parent story
		$story_id = wp_get_post_parent_id( $chapter_id );
		if ( $story_id ) {
			self::invalidate_story( $story_id );
		}

		/**
		 * Fires after chapter caches are invalidated
		 *
		 * @since 1.0.0
		 *
		 * @param int $chapter_id The chapter ID.
		 */
		do_action( 'fanfic_cache_invalidate_chapter', $chapter_id );
	}

	/**
	 * Invalidate all caches related to a specific user
	 *
	 * Clears user-specific data like bookmarks, follows, notifications,
	 * and authored stories.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	public static function invalidate_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return;
		}

		// User-specific caches
		$types = array(
			'bookmarks',
			'follows',
			'followers',
			'stories',
			'notifications',
			'statistics',
			'profile',
		);

		foreach ( $types as $type ) {
			$key = self::get_key( 'user', $type, $user_id );
			self::delete( $key );
		}

		/**
		 * Fires after user caches are invalidated
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'fanfic_cache_invalidate_user', $user_id );
	}

	/**
	 * Invalidate all list and archive caches
	 *
	 * Clears caches for story lists, search results, trending stories,
	 * and archive pages. Use when content changes that affect listings.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of caches cleared.
	 */
	public static function invalidate_lists() {
		$prefixes = array(
			'list_',      // General lists
			'archive_',   // Archive pages
			'search_',    // Search results
			'trending_',  // Trending stories
			'featured_',  // Featured stories
			'recent_',    // Recent updates
			'top_',       // Top rated/viewed
		);

		$total = 0;
		foreach ( $prefixes as $prefix ) {
			$total += self::delete_by_prefix( $prefix );
		}

		/**
		 * Fires after list caches are invalidated
		 *
		 * @since 1.0.0
		 *
		 * @param int $total Number of caches cleared.
		 */
		do_action( 'fanfic_cache_invalidate_lists', $total );

		return $total;
	}

	/**
	 * Invalidate all taxonomy-related caches
	 *
	 * Clears caches for genre pages, status filters, and custom taxonomies.
	 * Use when taxonomies are added, updated, or deleted.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of caches cleared.
	 */
	public static function invalidate_taxonomies() {
		$prefixes = array(
			'taxonomy_',  // General taxonomy
			'genre_',     // Genre pages
			'status_',    // Status filters
			'term_',      // Term pages
		);

		$total = 0;
		foreach ( $prefixes as $prefix ) {
			$total += self::delete_by_prefix( $prefix );
		}

		// Also invalidate all lists since they use taxonomies
		$total += self::invalidate_lists();

		/**
		 * Fires after taxonomy caches are invalidated
		 *
		 * @since 1.0.0
		 *
		 * @param int $total Number of caches cleared.
		 */
		do_action( 'fanfic_cache_invalidate_taxonomies', $total );

		return $total;
	}

	/**
	 * Clean up expired transients from database
	 *
	 * WordPress doesn't automatically delete expired transients, which can
	 * bloat the options table. This method removes all expired transients
	 * for the plugin.
	 *
	 * This is resource-intensive and should be run via cron or manually
	 * by administrators.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of expired transients deleted.
	 */
	public static function cleanup_expired() {
		$total = 0;
		$batch_size = 1000;

		do {
			$count = self::cleanup_expired_batch( $batch_size );
			$total += $count;
		} while ( $count === $batch_size );

		/**
		 * Fires after expired transients are cleaned up
		 *
		 * @since 1.0.0
		 *
		 * @param int $count Number of transients cleaned up.
		 */
		do_action( 'fanfic_cache_cleanup_expired', $total );

		return $total;
	}

	/**
	 * Clean up one batch of expired transients.
	 *
	 * @since 2.0.0
	 * @param int $limit Batch size.
	 * @return int Number of expired transients deleted in this batch.
	 */
	public static function cleanup_expired_batch( $limit = 500 ) {
		global $wpdb;

		$current_time = time();
		$limit = max( 1, absint( $limit ) );

		// Find expired transients with our prefix
		$sql = $wpdb->prepare(
			"SELECT option_name
			FROM {$wpdb->options}
			WHERE option_name LIKE %s
			AND option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' . self::BASE_PREFIX ) . '%',
			$current_time
		);
		$sql .= $wpdb->prepare( ' ORDER BY option_name ASC LIMIT %d', $limit );

		$expired = $wpdb->get_col( $sql );
		$count   = 0;

		if ( empty( $expired ) ) {
			return 0;
		}

		foreach ( $expired as $transient ) {
			// Extract transient name (remove '_transient_timeout_' prefix)
			$name = str_replace( '_transient_timeout_', '', $transient );

			// Delete the transient
			if ( delete_transient( $name ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Clear all plugin transients
	 *
	 * Nuclear option: removes ALL transients created by the plugin.
	 * Use for troubleshooting or after major updates.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of transients deleted.
	 */
	public static function clear_all() {
		$count = self::delete_by_prefix( self::BASE_PREFIX );

		/**
		 * Fires after all plugin caches are cleared
		 *
		 * @since 1.0.0
		 *
		 * @param int $count Number of transients cleared.
		 */
		do_action( 'fanfic_cache_clear_all', $count );

		return $count;
	}

	/**
	 * Check if object caching is active
	 *
	 * Detects if the site is using persistent object caching
	 * (Redis, Memcached, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if object cache is active, false otherwise.
	 */
	public static function is_object_cache_active() {
		// Check if wp_using_ext_object_cache function exists
		if ( function_exists( 'wp_using_ext_object_cache' ) ) {
			return wp_using_ext_object_cache();
		}

		// Fallback: check global variable
		return ( defined( 'WP_CACHE' ) && WP_CACHE );
	}

	/**
	 * Get cache statistics
	 *
	 * Returns information about cached data for monitoring and debugging.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Cache statistics.
	 *
	 *     @type int    $total_transients   Total number of plugin transients.
	 *     @type int    $expired_transients Number of expired transients.
	 *     @type bool   $object_cache       Whether object cache is active.
	 *     @type string $cache_version      Current cache version.
	 * }
	 */
	public static function get_stats() {
		global $wpdb;

		// Count total transients
		$total_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . self::BASE_PREFIX ) . '%'
		);
		$total = $wpdb->get_var( $total_sql );

		// Count expired transients
		$expired_sql = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->options}
			WHERE option_name LIKE %s
			AND option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' . self::BASE_PREFIX ) . '%',
			time()
		);
		$expired = $wpdb->get_var( $expired_sql );

		return array(
			'total_transients'   => intval( $total ),
			'expired_transients' => intval( $expired ),
			'object_cache'       => self::is_object_cache_active(),
			'cache_version'      => self::CACHE_VERSION,
		);
	}

	/**
	 * Get or set with custom cache key generation
	 *
	 * Convenience method that combines key generation and get operations.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $type     Type of cached data.
	 * @param string   $subtype  Subtype or action.
	 * @param int      $id       Optional. Item ID.
	 * @param callable $callback Function to generate data if cache miss.
	 * @param int      $ttl      Time to live in seconds.
	 * @return mixed The cached or freshly generated data.
	 */
	public static function get_or_set( $type, $subtype, $id = 0, $callback = null, $ttl = self::MEDIUM ) {
		$key = self::get_key( $type, $subtype, $id );
		return self::get( $key, $callback, $ttl );
	}

	/**
	 * Warm up cache for a specific item
	 *
	 * Pre-generates cache for an item even if not requested yet.
	 * Useful after bulk operations or during maintenance.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $type     Type of cached data.
	 * @param string   $subtype  Subtype or action.
	 * @param int      $id       Item ID.
	 * @param callable $callback Function to generate data.
	 * @param int      $ttl      Time to live in seconds.
	 * @return bool True if cache was warmed, false on failure.
	 */
	public static function warm( $type, $subtype, $id, $callback, $ttl = self::MEDIUM ) {
		if ( ! is_callable( $callback ) ) {
			return false;
		}

		$key  = self::get_key( $type, $subtype, $id );
		$data = call_user_func( $callback );

		return self::set( $key, $data, $ttl );
	}

	/**
	 * Check if a cache key exists and is not expired
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key to check.
	 * @return bool True if cache exists and is valid, false otherwise.
	 */
	public static function exists( $key ) {
		return ( false !== get_transient( $key ) );
	}

	/**
	 * Get remaining TTL for a cached item
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key.
	 * @return int|false Seconds remaining, or false if expired/not found.
	 */
	public static function get_ttl( $key ) {
		global $wpdb;

		$timeout_key = '_transient_timeout_' . $key;
		$timeout     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$timeout_key
			)
		);

		if ( ! $timeout ) {
			return false;
		}

		$remaining = intval( $timeout ) - time();

		return $remaining > 0 ? $remaining : false;
	}
}
