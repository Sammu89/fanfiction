<?php
/**
 * Cache Manager Class
 *
 * Enhanced caching strategy with incremental updates for ratings, likes,
 * and other interaction data. Provides unified cache management with
 * automatic invalidation and dual-layer caching support.
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Cache_Manager
 *
 * Provides enhanced caching functionality including:
 * - Incremental cache updates (no full rebuild)
 * - Dual-layer caching (object cache + transients)
 * - Automatic cache invalidation
 * - Cache statistics and monitoring
 *
 * @since 1.0.15
 */
class Fanfic_Cache_Manager {

	/**
	 * Cache group for object caching
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'fanfic';

	/**
	 * Initialize cache manager
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function init() {
		// Hook for automatic cache invalidation
		add_action( 'transition_post_status', array( __CLASS__, 'invalidate_post_cache' ), 10, 3 );
		add_action( 'delete_user', array( __CLASS__, 'invalidate_user_cache' ) );
		add_action( 'fanfic_rating_submitted', array( __CLASS__, 'update_rating_cache_on_submit' ), 10, 3 );
		add_action( 'fanfic_like_submitted', array( __CLASS__, 'update_like_cache_on_submit' ), 10, 2 );
		add_action( 'fanfic_rating_updated', array( __CLASS__, 'update_rating_cache_on_update' ), 10, 4 );
		add_action( 'fanfic_rating_deleted', array( __CLASS__, 'update_rating_cache_on_delete' ), 10, 3 );
	}

	/**
	 * Set transient with dual-layer support
	 *
	 * @since 1.0.15
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool True on success.
	 */
	public static function set_transient( $key, $value, $ttl = HOUR_IN_SECONDS ) {
		$ttl = absint( $ttl );

		// Try object cache first
		if ( self::is_object_cache_available() ) {
			wp_cache_set( $key, $value, self::CACHE_GROUP, $ttl );
		}

		// Always set transient as fallback
		return set_transient( $key, $value, $ttl );
	}

	/**
	 * Get transient with dual-layer support
	 *
	 * @since 1.0.15
	 * @param string $key Cache key.
	 * @return mixed Cached value or false.
	 */
	public static function get_transient( $key ) {
		// Try object cache first
		if ( self::is_object_cache_available() ) {
			$value = wp_cache_get( $key, self::CACHE_GROUP );

			if ( false !== $value ) {
				return $value;
			}
		}

		// Fallback to transient
		return get_transient( $key );
	}

	/**
	 * Delete transient with dual-layer support
	 *
	 * @since 1.0.15
	 * @param string $key Cache key.
	 * @return bool True on success.
	 */
	public static function delete_transient( $key ) {
		// Delete from object cache
		if ( self::is_object_cache_available() ) {
			wp_cache_delete( $key, self::CACHE_GROUP );
		}

		// Delete transient
		return delete_transient( $key );
	}

	/**
	 * Invalidate cache for a post
	 *
	 * @since 1.0.15
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return int Number of caches invalidated.
	 */
	public static function invalidate_post_cache( $new_status, $old_status, $post ) {
		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return 0;
		}

		return self::invalidate_post_cache_by_id( $post->ID );
	}

	/**
	 * Invalidate cache for a post by ID
	 *
	 * @since 1.0.15
	 * @param int $post_id Post ID.
	 * @return int Number of caches invalidated.
	 */
	public static function invalidate_post_cache_by_id( $post_id ) {
		$post_id = absint( $post_id );
		$count = 0;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}

		$post_type = $post->post_type;

		if ( 'fanfiction_chapter' === $post_type ) {
			// Invalidate chapter caches
			$cache_keys = array(
				'fanfic_chapter_' . $post_id . '_stats',
				'fanfic_chapter_' . $post_id . '_comments',
				'fanfic_chapter_' . $post_id . '_ratings',
			);

			foreach ( $cache_keys as $key ) {
				self::delete_transient( $key );
				$count++;
			}

			// Also invalidate parent story
			if ( $post->post_parent ) {
				$count += self::invalidate_post_cache_by_id( $post->post_parent );
			}
		} elseif ( 'fanfiction_story' === $post_type ) {
			// Invalidate story caches
			$cache_keys = array(
				'fanfic_story_' . $post_id . '_stats',
				'fanfic_story_' . $post_id . '_chapters',
				'fanfic_story_' . $post_id . '_ratings',
			);

			foreach ( $cache_keys as $key ) {
				self::delete_transient( $key );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Invalidate cache for a user
	 *
	 * @since 1.0.15
	 * @param int $user_id User ID.
	 * @return int Number of caches invalidated.
	 */
	public static function invalidate_user_cache( $user_id ) {
		$user_id = absint( $user_id );
		$count = 0;

		$cache_keys = array(
			'fanfic_user_' . $user_id . '_notifications',
			'fanfic_user_' . $user_id . '_follows',
			'fanfic_user_' . $user_id . '_bookmarks',
			'fanfic_user_' . $user_id . '_reading_progress',
			'fanfic_author_' . $user_id . '_followers',
		);

		foreach ( $cache_keys as $key ) {
			self::delete_transient( $key );
			$count++;
		}

		return $count;
	}

	/**
	 * Update rating cache incrementally
	 *
	 * @since 1.0.15
	 * @param int   $chapter_id  Chapter ID.
	 * @param int   $new_rating  New rating value (1-5).
	 * @param int   $old_rating  Optional. Old rating value if updating.
	 * @param bool  $is_new      Whether this is a new rating.
	 * @return array Updated cache array.
	 */
	public static function update_rating_cache_incrementally( $chapter_id, $new_rating, $old_rating = null, $is_new = false ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_stats';
		$stats = self::get_transient( $cache_key );

		if ( false === $stats ) {
			// No cache exists, build from database
			global $wpdb;

			$result = $wpdb->get_row( $wpdb->prepare(
				"SELECT COUNT(*) as count, AVG(value) as average, SUM(value) as sum
				FROM {$wpdb->prefix}fanfic_interactions
				WHERE chapter_id = %d AND interaction_type = 'rating'",
				$chapter_id
			), ARRAY_A );

			$stats = array(
				'rating_count'   => absint( $result['count'] ),
				'rating_average' => round( floatval( $result['average'] ), 2 ),
				'rating_sum'     => absint( $result['sum'] ),
			);
		} else {
			// Update cache incrementally
			if ( $is_new ) {
				// New rating
				$old_count = $stats['rating_count'];
				$old_sum = $stats['rating_sum'];

				$stats['rating_count'] = $old_count + 1;
				$stats['rating_sum'] = $old_sum + $new_rating;
				$stats['rating_average'] = round( $stats['rating_sum'] / $stats['rating_count'], 2 );
			} else {
				// Update existing rating
				if ( null !== $old_rating ) {
					$stats['rating_sum'] = $stats['rating_sum'] - $old_rating + $new_rating;
					$stats['rating_average'] = round( $stats['rating_sum'] / $stats['rating_count'], 2 );
				}
			}
		}

		// Store updated cache
		self::set_transient( $cache_key, $stats, DAY_IN_SECONDS );

		return $stats;
	}

	/**
	 * Update like cache incrementally
	 *
	 * @since 1.0.15
	 * @param int $chapter_id Chapter ID.
	 * @param int $increment  Increment (+1 for add, -1 for remove).
	 * @return int Updated like count.
	 */
	public static function update_like_cache_incrementally( $chapter_id, $increment ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_stats';
		$stats = self::get_transient( $cache_key );

		if ( false === $stats ) {
			// No cache exists, build from database
			global $wpdb;

			$like_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_interactions WHERE chapter_id = %d AND interaction_type = 'like'",
				$chapter_id
			) );

			$stats = array(
				'like_count' => absint( $like_count ),
			);
		} else {
			// Update cache incrementally
			if ( ! isset( $stats['like_count'] ) ) {
				$stats['like_count'] = 0;
			}

			$stats['like_count'] = max( 0, $stats['like_count'] + $increment );
		}

		// Store updated cache
		self::set_transient( $cache_key, $stats, DAY_IN_SECONDS );

		return $stats['like_count'];
	}

	/**
	 * Hook: Update rating cache when rating is submitted
	 *
	 * @since 1.0.15
	 * @param int $chapter_id Chapter ID.
	 * @param int $user_id    User ID.
	 * @param int $rating     Rating value.
	 * @return void
	 */
	public static function update_rating_cache_on_submit( $chapter_id, $user_id, $rating ) {
		self::update_rating_cache_incrementally( $chapter_id, $rating, null, true );
	}

	/**
	 * Hook: Update rating cache when rating is updated
	 *
	 * @since 1.0.15
	 * @param int $chapter_id  Chapter ID.
	 * @param int $user_id     User ID.
	 * @param int $new_rating  New rating value.
	 * @param int $old_rating  Old rating value.
	 * @return void
	 */
	public static function update_rating_cache_on_update( $chapter_id, $user_id, $new_rating, $old_rating ) {
		self::update_rating_cache_incrementally( $chapter_id, $new_rating, $old_rating, false );
	}

	/**
	 * Hook: Update rating cache when rating is deleted
	 *
	 * @since 1.0.15
	 * @param int $chapter_id Chapter ID.
	 * @param int $user_id    User ID.
	 * @param int $rating     Rating value that was deleted.
	 * @return void
	 */
	public static function update_rating_cache_on_delete( $chapter_id, $user_id, $rating ) {
		// Subtract the deleted rating
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_stats';
		$stats = self::get_transient( $cache_key );

		if ( false !== $stats && isset( $stats['rating_count'] ) ) {
			$stats['rating_count'] = max( 0, $stats['rating_count'] - 1 );
			$stats['rating_sum'] = max( 0, $stats['rating_sum'] - $rating );

			if ( $stats['rating_count'] > 0 ) {
				$stats['rating_average'] = round( $stats['rating_sum'] / $stats['rating_count'], 2 );
			} else {
				$stats['rating_average'] = 0;
			}

			self::set_transient( $cache_key, $stats, DAY_IN_SECONDS );
		}
	}

	/**
	 * Hook: Update like cache when like is submitted
	 *
	 * @since 1.0.15
	 * @param int  $chapter_id Chapter ID.
	 * @param bool $is_add     True if adding like, false if removing.
	 * @return void
	 */
	public static function update_like_cache_on_submit( $chapter_id, $is_add ) {
		$increment = $is_add ? 1 : -1;
		self::update_like_cache_incrementally( $chapter_id, $increment );
	}

	/**
	 * Get cache statistics
	 *
	 * @since 1.0.15
	 * @return array Cache statistics.
	 */
	public static function get_cache_stats() {
		global $wpdb;

		$stats = array(
			'object_cache_enabled' => self::is_object_cache_available(),
			'cache_group'          => self::CACHE_GROUP,
		);

		// Count transients
		$transient_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fanfic_%'"
		);

		$stats['transient_count'] = absint( $transient_count );

		// Get total transient size (approximate)
		$transient_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fanfic_%'"
		);

		$stats['transient_size_bytes'] = absint( $transient_size );
		$stats['transient_size_human'] = size_format( $transient_size );

		// Get oldest transient age
		$oldest_transient = $wpdb->get_var(
			"SELECT MIN(option_value) FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_fanfic_%'"
		);

		if ( $oldest_transient ) {
			$age = time() - absint( $oldest_transient );
			$stats['oldest_transient_age'] = $age;
			$stats['oldest_transient_age_human'] = human_time_diff( absint( $oldest_transient ) );
		}

		return $stats;
	}

	/**
	 * Clear all plugin caches
	 *
	 * @since 1.0.15
	 * @return int Number of caches cleared.
	 */
	public static function clear_all_caches() {
		global $wpdb;

		// Log action
		Fanfic_Security::log_security_event( 'cache_cleared_all', array(
			'user_id' => get_current_user_id(),
		) );

		// Delete all fanfic transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_fanfic_%'
			OR option_name LIKE '_transient_timeout_fanfic_%'"
		);

		$count = $wpdb->rows_affected / 2; // Each transient has a timeout entry

		// Clear object cache group
		if ( self::is_object_cache_available() ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}

		return absint( $count );
	}

	/**
	 * Check if object cache is available
	 *
	 * @since 1.0.15
	 * @return bool True if object cache is available.
	 */
	private static function is_object_cache_available() {
		if ( function_exists( 'wp_using_ext_object_cache' ) ) {
			return wp_using_ext_object_cache();
		}

		return defined( 'WP_CACHE' ) && WP_CACHE;
	}

	/**
	 * Warm up cache for a chapter
	 *
	 * @since 1.0.15
	 * @param int $chapter_id Chapter ID.
	 * @return bool True on success.
	 */
	public static function warm_chapter_cache( $chapter_id ) {
		global $wpdb;

		// Get stats from interactions table
		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(CASE WHEN interaction_type = 'rating' THEN id END) as rating_count,
				AVG(CASE WHEN interaction_type = 'rating' THEN value END) as rating_average,
				SUM(CASE WHEN interaction_type = 'rating' THEN value END) as rating_sum,
				COUNT(CASE WHEN interaction_type = 'like' THEN id END) as like_count
			FROM {$wpdb->prefix}fanfic_interactions
			WHERE chapter_id = %d AND interaction_type IN ('rating','like')",
			$chapter_id
		), ARRAY_A );

		$cache_data = array(
			'rating_count'   => absint( $stats['rating_count'] ),
			'rating_average' => round( floatval( $stats['rating_average'] ), 2 ),
			'rating_sum'     => absint( $stats['rating_sum'] ),
			'like_count'     => absint( $stats['like_count'] ),
		);

		$cache_key = 'fanfic_chapter_' . $chapter_id . '_stats';
		return self::set_transient( $cache_key, $cache_data, DAY_IN_SECONDS );
	}
}
