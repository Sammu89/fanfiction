<?php
/**
 * Like System Class
 *
 * Handles chapter likes with incremental cache updates.
 * Story likes are derived as sum of all chapter likes.
 *
 * SYSTEM DESIGN:
 * - Users can like/unlike chapters (binary toggle)
 * - Stories get derived likes (sum of all chapter likes)
 * - Anonymous users: Tracked by IP + fingerprint hash, 30-day window
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
	 * Initialize like system
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		// Register AJAX handlers
		add_action( 'wp_ajax_fanfic_toggle_like', array( __CLASS__, 'ajax_toggle_like' ) );
		add_action( 'wp_ajax_nopriv_fanfic_toggle_like', array( __CLASS__, 'ajax_toggle_like' ) );

		add_action( 'wp_ajax_fanfic_check_like_status', array( __CLASS__, 'ajax_check_like_status' ) );
		add_action( 'wp_ajax_nopriv_fanfic_check_like_status', array( __CLASS__, 'ajax_check_like_status' ) );

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
			'fanficLikes',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fanfic_like_nonce' ),
				'strings' => array(
					'liked'   => __( 'Liked!', 'fanfiction-manager' ),
					'unliked' => __( 'Unliked', 'fanfiction-manager' ),
					'error'   => __( 'An error occurred. Please try again.', 'fanfiction-manager' ),
				),
			)
		);
	}

	/**
	 * Toggle like on chapter
	 *
	 * @since 2.0.0
	 * @param int   $chapter_id Chapter ID.
	 * @param array $identifier User identifier.
	 * @return array|WP_Error Result array or error.
	 */
	public static function toggle_like( $chapter_id, $identifier ) {
		global $wpdb;

		// Validate inputs
		$chapter_id = absint( $chapter_id );

		if ( ! $identifier || ! is_array( $identifier ) ) {
			return new WP_Error( 'invalid_identifier', __( 'Invalid user identifier', 'fanfiction-manager' ) );
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return new WP_Error( 'invalid_chapter', __( 'Invalid chapter', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_likes';

		// Check if already liked
		if ( 'logged_in' === $identifier['type'] ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE chapter_id = %d AND user_id = %d",
				$chapter_id,
				$identifier['user_id']
			) );
		} else {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE chapter_id = %d AND identifier_hash = %s",
				$chapter_id,
				$identifier['hash']
			) );
		}

		if ( $existing ) {
			// Already liked - UNLIKE (DELETE)
			$wpdb->delete(
				$table_name,
				array( 'id' => $existing ),
				array( '%d' )
			);

			// Update caches (decrement)
			self::update_like_cache_incremental( $chapter_id, -1 );

			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				self::update_story_like_cache_incremental( $story_id, -1 );
			}

			return array(
				'success'  => true,
				'action'   => 'unliked',
				'is_liked' => false,
			);

		} else {
			// Not liked - LIKE (INSERT)
			$wpdb->insert(
				$table_name,
				array(
					'chapter_id'      => $chapter_id,
					'user_id'         => $identifier['user_id'],
					'identifier_hash' => $identifier['hash'],
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);

			// Update caches (increment)
			self::update_like_cache_incremental( $chapter_id, +1 );

			$story_id = wp_get_post_parent_id( $chapter_id );
			if ( $story_id ) {
				self::update_story_like_cache_incremental( $story_id, +1 );
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
	 * @param int $delta      +1 for like, -1 for unlike.
	 * @return void
	 */
	private static function update_like_cache_incremental( $chapter_id, $delta ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_likes';
		$count = get_transient( $cache_key );

		// Cache miss - rebuild
		if ( false === $count ) {
			$count = self::rebuild_chapter_likes_from_db( $chapter_id );
		}

		// Update incrementally
		$count = max( 0, intval( $count ) + $delta );

		// Update cache
		set_transient( $cache_key, $count, self::CACHE_DURATION );
	}

	/**
	 * Update story like count incrementally
	 *
	 * @since 2.0.0
	 * @param int $story_id Story ID.
	 * @param int $delta    +1 for like, -1 for unlike.
	 * @return void
	 */
	private static function update_story_like_cache_incremental( $story_id, $delta ) {
		$cache_key = 'fanfic_story_' . $story_id . '_likes';
		$count = get_transient( $cache_key );

		// Cache miss - rebuild
		if ( false === $count ) {
			$count = self::rebuild_story_likes_from_db( $story_id );
		}

		// Update incrementally
		$count = max( 0, intval( $count ) + $delta );

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
			"SELECT id FROM {$wpdb->posts}
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
	 * Check if user has liked chapter
	 *
	 * @since 2.0.0
	 * @param int   $chapter_id Chapter ID.
	 * @param array $identifier User identifier.
	 * @return bool True if liked, false otherwise.
	 */
	public static function user_has_liked( $chapter_id, $identifier ) {
		if ( ! $identifier || ! is_array( $identifier ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_likes';

		if ( 'logged_in' === $identifier['type'] ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM {$table_name} WHERE chapter_id = %d AND user_id = %d",
				$chapter_id,
				$identifier['user_id']
			) );
		} else {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT 1 FROM {$table_name} WHERE chapter_id = %d AND identifier_hash = %s",
				$chapter_id,
				$identifier['hash']
			) );
		}

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
		$fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( $_POST['fingerprint'] ) : '';

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter ID', 'fanfiction-manager' ) ) );
		}

		// Get user identifier
		$identifier = Fanfic_User_Identifier::get_identifier( $fingerprint );

		if ( ! $identifier ) {
			wp_send_json_error( array( 'message' => __( 'Could not identify user', 'fanfiction-manager' ) ) );
		}

		$has_liked = self::user_has_liked( $chapter_id, $identifier );
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
		$fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( $_POST['fingerprint'] ) : '';

		// Get user identifier
		$identifier = Fanfic_User_Identifier::get_identifier( $fingerprint );

		if ( ! $identifier ) {
			wp_send_json_error( array( 'message' => __( 'Could not identify user', 'fanfiction-manager' ) ) );
		}

		// Toggle like
		$result = self::toggle_like( $chapter_id, $identifier );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) ) );
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
