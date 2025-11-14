<?php
/**
 * Like System Class
 *
 * Handles chapter likes with cookie-based anonymous support and incremental cache updates.
 * Story likes are derived as sum of all chapter likes.
 *
 * SYSTEM DESIGN:
 * - Users can like/unlike chapters (binary toggle)
 * - Stories get derived likes (sum of all chapter likes)
 * - Anonymous users: Cookie-based tracking (30 days, no IP storage)
 * - Logged-in users: Tracked by user_id
 * - Cache: Incremental updates (no rebuild on each like/unlike)
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Like_System
 *
 * High-performance like system with incremental caching.
 *
 * @since 2.0.0
 */
class Fanfic_Like_System {

	/**
	 * Cache duration for like counts (24 hours)
	 *
	 * @var int
	 */
	const CACHE_DURATION = 86400; // 24 hours in seconds

	/**
	 * Cookie duration for anonymous likes (30 days)
	 *
	 * @var int
	 */
	const COOKIE_DURATION = 2592000; // 30 days in seconds

	/**
	 * Initialize like system
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		// AJAX handlers are now registered in class-fanfic-ajax-handlers.php

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue like scripts
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'fanfic-likes',
			FANFIC_PLUGIN_URL . 'assets/js/fanfiction-likes.js',
			array( 'jquery' ),
			FANFIC_VERSION,
			true
		);

		wp_localize_script(
			'fanfic-likes',
			'fanficAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fanfic_ajax_nonce' ),
			)
		);
	}

	/**
	 * Toggle like on chapter with cookie-based anonymous support
	 *
	 * @since 2.0.0
	 * @param int      $chapter_id Chapter ID.
	 * @param int|null $user_id    User ID (null for anonymous).
	 * @return array|WP_Error Result array or error.
	 */
	public static function toggle_like( $chapter_id, $user_id = null ) {
		global $wpdb;

		// Validate inputs
		$chapter_id = absint( $chapter_id );

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return new WP_Error( 'invalid_chapter', __( 'Invalid chapter', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_likes';
		$cookie_name = 'fanfic_like_' . $chapter_id;

		// Check existing like
		$existing = false;
		if ( $user_id ) {
			// Logged-in user
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE chapter_id = %d AND user_id = %d",
				$chapter_id,
				$user_id
			) );
		} else {
			// Anonymous user - check cookie
			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$existing = true;
			}
		}

		if ( $existing ) {
			// Already liked - UNLIKE (DELETE)
			if ( $user_id ) {
				$wpdb->delete(
					$table_name,
					array( 'id' => $existing ),
					array( '%d' )
				);
			} else {
				// Delete cookie by setting past expiration
				setcookie( $cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}

			// Update caches (decrement)
			self::update_like_cache_incrementally( $chapter_id, -1 );

			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				self::update_story_like_cache_incrementally( $story_id, -1 );
			}

			return array(
				'success'  => true,
				'action'   => 'unliked',
				'is_liked' => false,
			);

		} else {
			// Not liked - LIKE (INSERT)
			if ( $user_id ) {
				$wpdb->insert(
					$table_name,
					array(
						'chapter_id' => $chapter_id,
						'user_id'    => $user_id,
						'created_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
			} else {
				// Set cookie
				$expire = time() + self::COOKIE_DURATION;
				setcookie( $cookie_name, '1', $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

				// Also insert anonymous record in DB for counting
				$wpdb->insert(
					$table_name,
					array(
						'chapter_id' => $chapter_id,
						'user_id'    => null,
						'created_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
			}

			// Update caches (increment)
			self::update_like_cache_incrementally( $chapter_id, +1 );

			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				self::update_story_like_cache_incrementally( $story_id, +1 );
			}

			return array(
				'success'  => true,
				'action'   => 'liked',
				'is_liked' => true,
			);
		}
	}

	/**
	 * Update chapter like count incrementally
	 *
	 * @since 2.0.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $increment  +1 for like, -1 for unlike.
	 * @return void
	 */
	public static function update_like_cache_incrementally( $chapter_id, $increment ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_likes';
		$count = get_transient( $cache_key );

		// Cache miss - rebuild
		if ( false === $count ) {
			$count = self::rebuild_chapter_likes_from_db( $chapter_id );
		}

		// Update incrementally
		$count = max( 0, intval( $count ) + $increment );

		// Update cache
		set_transient( $cache_key, $count, self::CACHE_DURATION );
	}

	/**
	 * Update story like count incrementally
	 *
	 * @since 2.0.0
	 * @param int $story_id  Story ID.
	 * @param int $increment +1 for like, -1 for unlike.
	 * @return void
	 */
	private static function update_story_like_cache_incrementally( $story_id, $increment ) {
		$cache_key = 'fanfic_story_' . $story_id . '_likes';
		$count = get_transient( $cache_key );

		// Cache miss - rebuild
		if ( false === $count ) {
			$count = self::rebuild_story_likes_from_db( $story_id );
		}

		// Update incrementally
		$count = max( 0, intval( $count ) + $increment );

		// Update cache
		set_transient( $cache_key, $count, self::CACHE_DURATION );
	}

	/**
	 * Rebuild chapter like count from database
	 *
	 * @since 2.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return int Like count.
	 */
	private static function rebuild_chapter_likes_from_db( $chapter_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_likes';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE chapter_id = %d",
			$chapter_id
		) );

		return intval( $count );
	}

	/**
	 * Rebuild story like count from database (sum of chapters)
	 *
	 * @since 2.0.0
	 * @param int $story_id Story ID.
	 * @return int Like count.
	 */
	private static function rebuild_story_likes_from_db( $story_id ) {
		global $wpdb;

		// Get published chapters
		$chapter_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_parent = %d
			AND post_type = 'fanfiction_chapter'
			AND post_status = 'publish'",
			$story_id
		) );

		if ( empty( $chapter_ids ) ) {
			return 0;
		}

		// Count likes across all chapters
		$table_name = $wpdb->prefix . 'fanfic_likes';
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE chapter_id IN ($placeholders)",
			...$chapter_ids
		) );

		return intval( $count );
	}

	/**
	 * Get chapter like count (cached)
	 *
	 * @since 2.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return int Like count.
	 */
	public static function get_chapter_likes( $chapter_id ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_likes';
		$count = get_transient( $cache_key );

		if ( false === $count ) {
			$count = self::rebuild_chapter_likes_from_db( $chapter_id );
			set_transient( $cache_key, $count, self::CACHE_DURATION );
		}

		return intval( $count );
	}

	/**
	 * Get story like count (cached, sum of chapters)
	 *
	 * @since 2.0.0
	 * @param int $story_id Story ID.
	 * @return int Like count.
	 */
	public static function get_story_likes( $story_id ) {
		$cache_key = 'fanfic_story_' . $story_id . '_likes';
		$count = get_transient( $cache_key );

		if ( false === $count ) {
			$count = self::rebuild_story_likes_from_db( $story_id );
			set_transient( $cache_key, $count, self::CACHE_DURATION );
		}

		return intval( $count );
	}

	/**
	 * Batch get likes for multiple chapters (ONE query)
	 *
	 * @since 2.0.0
	 * @param array $chapter_ids Array of chapter IDs.
	 * @return array Indexed array of like counts by chapter_id.
	 */
	public static function batch_get_likes( $chapter_ids ) {
		if ( empty( $chapter_ids ) ) {
			return array();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_likes';
		$chapter_ids = array_map( 'intval', $chapter_ids );
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT chapter_id, COUNT(*) as like_count
			FROM {$table_name}
			WHERE chapter_id IN ($placeholders)
			GROUP BY chapter_id",
			...$chapter_ids
		), ARRAY_A );

		// Index by chapter_id
		$indexed = array();
		foreach ( $results as $row ) {
			$indexed[ $row['chapter_id'] ] = intval( $row['like_count'] );
		}

		// Fill in missing chapters with zeros
		foreach ( $chapter_ids as $chapter_id ) {
			if ( ! isset( $indexed[ $chapter_id ] ) ) {
				$indexed[ $chapter_id ] = 0;
			}
		}

		return $indexed;
	}

	/**
	 * Check if user has liked chapter
	 *
	 * @since 2.0.0
	 * @param int      $chapter_id Chapter ID.
	 * @param int|null $user_id    User ID (null for anonymous).
	 * @return bool True if liked, false otherwise.
	 */
	public static function user_has_liked( $chapter_id, $user_id = null ) {
		if ( ! $user_id ) {
			// Check cookie for anonymous
			$cookie_name = 'fanfic_like_' . $chapter_id;
			return isset( $_COOKIE[ $cookie_name ] );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_likes';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$table_name} WHERE chapter_id = %d AND user_id = %d",
			$chapter_id,
			$user_id
		) );

		return (bool) $exists;
	}

	/**
	 * AJAX: Check like status
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function ajax_check_like_status() {
		check_ajax_referer( 'fanfic_like_nonce', 'nonce' );

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter ID', 'fanfiction-manager' ) ) );
		}

		$user_id = get_current_user_id();
		$has_liked = self::user_has_liked( $chapter_id, $user_id ?: null );
		$count = self::get_chapter_likes( $chapter_id );

		wp_send_json_success( array(
			'is_liked' => $has_liked,
			'count'    => $count,
		) );
	}

	/**
	 * AJAX: Toggle like
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function ajax_toggle_like() {
		check_ajax_referer( 'fanfic_like_nonce', 'nonce' );

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$user_id = get_current_user_id();

		// Toggle like
		$result = self::toggle_like( $chapter_id, $user_id ?: null );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Get updated counts
		$chapter_count = self::get_chapter_likes( $chapter_id );

		$story_id = wp_get_post_parent_id( $chapter_id );
		$story_count = $story_id ? self::get_story_likes( $story_id ) : 0;

		wp_send_json_success( array(
			'action'        => $result['action'],
			'is_liked'      => $result['is_liked'],
			'chapter_count' => $chapter_count,
			'story_count'   => $story_count,
			'message'       => 'liked' === $result['action']
				? __( 'Liked!', 'fanfiction-manager' )
				: __( 'Unliked', 'fanfiction-manager' ),
		) );
	}
}
