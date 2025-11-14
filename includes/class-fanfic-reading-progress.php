<?php
/**
 * Reading Progress Class
 *
 * Handles "mark as read" functionality with batch loading optimization.
 * Used for UI state only (showing "Read" badge on chapters).
 *
 * @package Fanfiction_Manager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Reading_Progress
 *
 * High-performance reading progress tracking with batch operations.
 */
class Fanfic_Reading_Progress {

	/**
	 * Initialize reading progress system
	 */
	public static function init() {
		// Register AJAX handlers
		add_action( 'wp_ajax_fanfic_mark_as_read', array( __CLASS__, 'ajax_mark_as_read' ) );
		add_action( 'wp_ajax_fanfic_get_read_status', array( __CLASS__, 'ajax_get_read_status' ) );
	}

	/**
	 * Mark chapter as read
	 *
	 * @param int $user_id User ID.
	 * @param int $story_id Story ID.
	 * @param int $chapter_number Chapter number.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function mark_as_read( $user_id, $story_id, $chapter_number ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		$story_id = absint( $story_id );
		$chapter_number = absint( $chapter_number );

		if ( ! $user_id || ! $story_id || ! $chapter_number ) {
			return new WP_Error( 'invalid_params', __( 'Invalid parameters', 'fanfiction-manager' ) );
		}

		// Verify user exists
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user', 'fanfiction-manager' ) );
		}

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return new WP_Error( 'invalid_story', __( 'Invalid story', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		// Use REPLACE to handle both insert and update
		$result = $wpdb->replace(
			$table_name,
			array(
				'user_id'        => $user_id,
				'story_id'       => $story_id,
				'chapter_number' => $chapter_number,
				'marked_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to mark chapter as read', 'fanfiction-manager' ) );
		}

		// Clear cache for this story/user combo
		wp_cache_delete( "read_chapters_{$user_id}_{$story_id}", 'fanfic' );

		return true;
	}

	/**
	 * Batch load read status for multiple chapters (OPTIMIZED)
	 *
	 * This eliminates N+1 query problem when displaying story with many chapters.
	 *
	 * @param int $user_id User ID.
	 * @param int $story_id Story ID.
	 * @return array Array of chapter numbers that have been read.
	 */
	public static function batch_load_read_chapters( $user_id, $story_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$story_id = absint( $story_id );

		if ( ! $user_id || ! $story_id ) {
			return array();
		}

		// Try cache first
		$cache_key = "read_chapters_{$user_id}_{$story_id}";
		$cached = wp_cache_get( $cache_key, 'fanfic' );

		if ( false !== $cached ) {
			return $cached;
		}

		// Load all read chapters for this story in ONE query
		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		$read_chapters = $wpdb->get_col( $wpdb->prepare(
			"SELECT chapter_number
			FROM {$table_name}
			WHERE user_id = %d AND story_id = %d",
			$user_id,
			$story_id
		) );

		$read_chapters = array_map( 'intval', $read_chapters );

		// Cache for 1 hour
		wp_cache_set( $cache_key, $read_chapters, 'fanfic', HOUR_IN_SECONDS );

		return $read_chapters;
	}

	/**
	 * Get read progress for a story (percentage)
	 *
	 * @param int $user_id User ID.
	 * @param int $story_id Story ID.
	 * @return float Progress percentage (0-100).
	 */
	public static function get_progress_percentage( $user_id, $story_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$story_id = absint( $story_id );

		if ( ! $user_id || ! $story_id ) {
			return 0.0;
		}

		// Get total published chapters
		$total_chapters = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_parent = %d
			AND post_type = 'fanfiction_chapter'
			AND post_status = 'publish'",
			$story_id
		) );

		if ( ! $total_chapters ) {
			return 0.0;
		}

		// Get read chapters
		$read_chapters = self::batch_load_read_chapters( $user_id, $story_id );
		$read_count = count( $read_chapters );

		return round( ( $read_count / $total_chapters ) * 100, 1 );
	}

	/**
	 * Get all stories with reading progress for a user
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Limit results.
	 * @return array Array of stories with progress data.
	 */
	public static function get_user_reading_list( $user_id, $limit = 50 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		// Get unique stories with most recent read timestamp
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT story_id, MAX(marked_at) as last_read
			FROM {$table_name}
			WHERE user_id = %d
			GROUP BY story_id
			ORDER BY last_read DESC
			LIMIT %d",
			$user_id,
			absint( $limit )
		) );

		if ( empty( $results ) ) {
			return array();
		}

		$reading_list = array();

		foreach ( $results as $row ) {
			$story_id = absint( $row->story_id );
			$story = get_post( $story_id );

			if ( ! $story || 'publish' !== $story->post_status ) {
				continue;
			}

			$progress = self::get_progress_percentage( $user_id, $story_id );

			$reading_list[] = array(
				'story_id'   => $story_id,
				'story'      => $story,
				'progress'   => $progress,
				'last_read'  => $row->last_read,
			);
		}

		return $reading_list;
	}

	/**
	 * Check if chapter is read
	 *
	 * @param int $user_id User ID.
	 * @param int $story_id Story ID.
	 * @param int $chapter_number Chapter number.
	 * @return bool True if read, false otherwise.
	 */
	public static function is_chapter_read( $user_id, $story_id, $chapter_number ) {
		$read_chapters = self::batch_load_read_chapters( $user_id, $story_id );
		return in_array( $chapter_number, $read_chapters, true );
	}

	/**
	 * Unmark chapter as read
	 *
	 * @param int $user_id User ID.
	 * @param int $story_id Story ID.
	 * @param int $chapter_number Chapter number.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function unmark_as_read( $user_id, $story_id, $chapter_number ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$story_id = absint( $story_id );
		$chapter_number = absint( $chapter_number );

		if ( ! $user_id || ! $story_id || ! $chapter_number ) {
			return new WP_Error( 'invalid_params', __( 'Invalid parameters', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		$result = $wpdb->delete(
			$table_name,
			array(
				'user_id'        => $user_id,
				'story_id'       => $story_id,
				'chapter_number' => $chapter_number,
			),
			array( '%d', '%d', '%d' )
		);

		// Clear cache
		wp_cache_delete( "read_chapters_{$user_id}_{$story_id}", 'fanfic' );

		return true;
	}

	/**
	 * AJAX: Toggle mark chapter as read/unread
	 */
	public static function ajax_mark_as_read() {
		// Verify nonce
		check_ajax_referer( 'fanfic_interactions_nonce', 'nonce' );

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'fanfiction-manager' ) ) );
		}

		$user_id = get_current_user_id();
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$chapter_number = isset( $_POST['chapter_number'] ) ? absint( $_POST['chapter_number'] ) : 0;

		// Check if chapter is already marked as read
		$is_read = self::is_chapter_read( $user_id, $story_id, $chapter_number );

		if ( $is_read ) {
			// Unmark as read
			$result = self::unmark_as_read( $user_id, $story_id, $chapter_number );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$progress = self::get_progress_percentage( $user_id, $story_id );

			wp_send_json_success( array(
				'message'  => __( 'Chapter unmarked as read', 'fanfiction-manager' ),
				'progress' => $progress,
				'is_read'  => false,
			) );
		} else {
			// Mark as read
			$result = self::mark_as_read( $user_id, $story_id, $chapter_number );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$progress = self::get_progress_percentage( $user_id, $story_id );

			wp_send_json_success( array(
				'message'  => __( 'Chapter marked as read', 'fanfiction-manager' ),
				'progress' => $progress,
				'is_read'  => true,
			) );
		}
	}

	/**
	 * AJAX: Get read status for chapters
	 */
	public static function ajax_get_read_status() {
		// Verify nonce
		check_ajax_referer( 'fanfic_interactions_nonce', 'nonce' );

		// Must be logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_success( array( 'read_chapters' => array() ) );
		}

		$user_id = get_current_user_id();
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid story ID', 'fanfiction-manager' ) ) );
		}

		$read_chapters = self::batch_load_read_chapters( $user_id, $story_id );

		wp_send_json_success( array(
			'read_chapters' => $read_chapters,
		) );
	}
}
