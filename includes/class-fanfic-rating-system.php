<?php
/**
 * Rating System Class
 *
 * Handles chapter and story ratings with incremental cache updates.
 * Optimized for high-volume sites with minimal database queries.
 *
 * SYSTEM DESIGN:
 * - Users can rate chapters (1-5 stars)
 * - Stories get derived rating (mean of all chapter ratings)
 * - Anonymous users: Tracked by IP + fingerprint hash, 30-day window
 * - Logged-in users: Tracked by user_id, can change rating anytime
 * - Cache: Incremental updates (no rebuild on each vote)
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Rating_System
 *
 * Comprehensive rating system with high-performance caching.
 *
 * @since 2.0.0
 */
class Fanfic_Rating_System {

	/**
	 * Cache duration for rating aggregates (24 hours)
	 *
	 * @var int
	 */
	const CACHE_DURATION = 86400; // 24 hours in seconds

	/**
	 * Initialize rating system
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function init() {
		// Register AJAX handlers
		add_action( 'wp_ajax_fanfic_submit_rating', array( __CLASS__, 'ajax_submit_rating' ) );
		add_action( 'wp_ajax_nopriv_fanfic_submit_rating', array( __CLASS__, 'ajax_submit_rating' ) );

		add_action( 'wp_ajax_fanfic_check_rating_eligibility', array( __CLASS__, 'ajax_check_eligibility' ) );
		add_action( 'wp_ajax_nopriv_fanfic_check_rating_eligibility', array( __CLASS__, 'ajax_check_eligibility' ) );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue rating scripts and styles
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'fanfic-rating',
			FANFIC_PLUGIN_URL . 'assets/js/fanfiction-rating.js',
			array( 'jquery' ),
			FANFIC_VERSION,
			true
		);

		wp_localize_script(
			'fanfic-rating',
			'fanficRating',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fanfic_rating_nonce' ),
				'strings' => array(
					'ratingSubmitted' => __( 'Rating submitted!', 'fanfiction-manager' ),
					'ratingUpdated'   => __( 'Rating updated!', 'fanfiction-manager' ),
					'error'           => __( 'An error occurred. Please try again.', 'fanfiction-manager' ),
				),
			)
		);
	}

	/**
	 * Record vote on chapter with incremental cache update
	 *
	 * @since 2.0.0
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $rating     Rating value (1-5).
	 * @param array $identifier User identifier from Fanfic_User_Identifier::get_identifier().
	 * @return array|WP_Error Result array or error.
	 */
	public static function record_vote( $chapter_id, $rating, $identifier ) {
		global $wpdb;

		// Validate inputs
		$chapter_id = absint( $chapter_id );
		$rating = intval( $rating );

		if ( ! $identifier || ! is_array( $identifier ) ) {
			return new WP_Error( 'invalid_identifier', __( 'Invalid user identifier', 'fanfiction-manager' ) );
		}

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5', 'fanfiction-manager' ) );
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return new WP_Error( 'invalid_chapter', __( 'Invalid chapter', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_ratings';
		$old_rating = null;
		$is_new_vote = true;

		// Check for existing vote
		if ( 'logged_in' === $identifier['type'] ) {
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, rating FROM {$table_name} WHERE chapter_id = %d AND user_id = %d",
				$chapter_id,
				$identifier['user_id']
			) );
		} else {
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, rating FROM {$table_name} WHERE chapter_id = %d AND identifier_hash = %s",
				$chapter_id,
				$identifier['hash']
			) );
		}

		// Process vote
		if ( $existing ) {
			$old_rating = intval( $existing->rating );

			// Same rating - no change needed
			if ( $old_rating === $rating ) {
				return array(
					'success' => true,
					'action'  => 'unchanged',
					'rating'  => $rating,
				);
			}

			$is_new_vote = false;

			// Update existing vote
			$wpdb->update(
				$table_name,
				array( 'rating' => $rating ),
				array( 'id' => $existing->id ),
				array( '%d' ),
				array( '%d' )
			);

			$action = 'updated';

		} else {
			// Insert new vote
			$wpdb->insert(
				$table_name,
				array(
					'chapter_id'      => $chapter_id,
					'user_id'         => $identifier['user_id'],
					'rating'          => $rating,
					'identifier_hash' => $identifier['hash'],
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);

			$action = 'created';
		}

		// Update chapter cache incrementally
		self::update_chapter_rating_cache_incremental( $chapter_id, $rating, $old_rating, $is_new_vote );

		// Update story cache incrementally
		$story_id = wp_get_post_parent_id( $chapter_id );
		if ( $story_id ) {
			self::update_story_rating_cache_incremental( $story_id, $rating, $old_rating, $is_new_vote );
		}

		return array(
			'success' => true,
			'action'  => $action,
			'rating'  => $rating,
		);
	}

	/**
	 * Update chapter rating cache incrementally
	 *
	 * @since 2.0.0
	 * @param int      $chapter_id  Chapter ID.
	 * @param int      $new_rating  New rating value.
	 * @param int|null $old_rating  Previous rating (null if new vote).
	 * @param bool     $is_new_vote True if new vote, false if changed.
	 * @return void
	 */
	private static function update_chapter_rating_cache_incremental( $chapter_id, $new_rating, $old_rating, $is_new_vote ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_rating';
		$data = get_transient( $cache_key );

		// Cache miss - rebuild from DB
		if ( false === $data ) {
			$data = self::rebuild_chapter_rating_from_db( $chapter_id );
			set_transient( $cache_key, $data, self::CACHE_DURATION );
			return;
		}

		// Update incrementally
		if ( $is_new_vote ) {
			// New vote - increment total and star count
			$data->total_votes++;
			$star_key = 'star_' . $new_rating;
			$data->$star_key = ( isset( $data->$star_key ) ? $data->$star_key : 0 ) + 1;
		} else {
			// Changed vote - move from old to new
			$old_star_key = 'star_' . $old_rating;
			$new_star_key = 'star_' . $new_rating;

			$data->$old_star_key = max( 0, ( isset( $data->$old_star_key ) ? $data->$old_star_key : 0 ) - 1 );
			$data->$new_star_key = ( isset( $data->$new_star_key ) ? $data->$new_star_key : 0 ) + 1;
		}

		// Recalculate average
		if ( $data->total_votes > 0 ) {
			$total_points = 0;
			for ( $i = 1; $i <= 5; $i++ ) {
				$star_key = 'star_' . $i;
				$total_points += $i * ( isset( $data->$star_key ) ? $data->$star_key : 0 );
			}
			$data->average_rating = round( $total_points / $data->total_votes, 2 );
		} else {
			$data->average_rating = 0;
		}

		// Update cache
		set_transient( $cache_key, $data, self::CACHE_DURATION );
	}

	/**
	 * Update story rating cache incrementally
	 *
	 * @since 2.0.0
	 * @param int      $story_id    Story ID.
	 * @param int      $new_rating  New rating value.
	 * @param int|null $old_rating  Previous rating (null if new vote).
	 * @param bool     $is_new_vote True if new vote, false if changed.
	 * @return void
	 */
	private static function update_story_rating_cache_incremental( $story_id, $new_rating, $old_rating, $is_new_vote ) {
		$cache_key = 'fanfic_story_' . $story_id . '_rating';
		$data = get_transient( $cache_key );

		// Cache miss - rebuild from DB
		if ( false === $data ) {
			$data = self::rebuild_story_rating_from_db( $story_id );
			set_transient( $cache_key, $data, self::CACHE_DURATION );
			return;
		}

		// Update incrementally (same logic as chapter)
		if ( $is_new_vote ) {
			$data->total_votes++;
			$star_key = 'star_' . $new_rating;
			$data->$star_key = ( isset( $data->$star_key ) ? $data->$star_key : 0 ) + 1;
		} else {
			$old_star_key = 'star_' . $old_rating;
			$new_star_key = 'star_' . $new_rating;

			$data->$old_star_key = max( 0, ( isset( $data->$old_star_key ) ? $data->$old_star_key : 0 ) - 1 );
			$data->$new_star_key = ( isset( $data->$new_star_key ) ? $data->$new_star_key : 0 ) + 1;
		}

		// Recalculate average
		if ( $data->total_votes > 0 ) {
			$total_points = 0;
			for ( $i = 1; $i <= 5; $i++ ) {
				$star_key = 'star_' . $i;
				$total_points += $i * ( isset( $data->$star_key ) ? $data->$star_key : 0 );
			}
			$data->average_rating = round( $total_points / $data->total_votes, 2 );
		} else {
			$data->average_rating = 0;
		}

		// Update cache
		set_transient( $cache_key, $data, self::CACHE_DURATION );
	}

	/**
	 * Rebuild chapter rating from database
	 *
	 * @since 2.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return object Rating data object.
	 */
	private static function rebuild_chapter_rating_from_db( $chapter_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_ratings';

		$data = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as total_votes,
				ROUND(AVG(rating), 2) as average_rating,
				SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
				SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
				SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
				SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
				SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
			FROM {$table_name}
			WHERE chapter_id = %d",
			$chapter_id
		) );

		// Ensure all properties exist
		if ( ! $data ) {
			$data = new stdClass();
		}

		$data->total_votes = isset( $data->total_votes ) ? intval( $data->total_votes ) : 0;
		$data->average_rating = isset( $data->average_rating ) ? floatval( $data->average_rating ) : 0;

		for ( $i = 1; $i <= 5; $i++ ) {
			$key = 'star_' . $i;
			$data->$key = isset( $data->$key ) ? intval( $data->$key ) : 0;
		}

		return $data;
	}

	/**
	 * Rebuild story rating from database (mean of all chapters)
	 *
	 * @since 2.0.0
	 * @param int $story_id Story ID.
	 * @return object Rating data object.
	 */
	private static function rebuild_story_rating_from_db( $story_id ) {
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
			$data = new stdClass();
			$data->total_votes = 0;
			$data->average_rating = 0;
			for ( $i = 1; $i <= 5; $i++ ) {
				$key = 'star_' . $i;
				$data->$key = 0;
			}
			return $data;
		}

		// Get aggregate ratings across all chapters
		$table_name = $wpdb->prefix . 'fanfic_ratings';
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		$data = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as total_votes,
				ROUND(AVG(rating), 2) as average_rating,
				SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
				SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
				SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
				SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
				SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
			FROM {$table_name}
			WHERE chapter_id IN ($placeholders)",
			...$chapter_ids
		) );

		// Ensure all properties exist
		if ( ! $data ) {
			$data = new stdClass();
		}

		$data->total_votes = isset( $data->total_votes ) ? intval( $data->total_votes ) : 0;
		$data->average_rating = isset( $data->average_rating ) ? floatval( $data->average_rating ) : 0;

		for ( $i = 1; $i <= 5; $i++ ) {
			$key = 'star_' . $i;
			$data->$key = isset( $data->$key ) ? intval( $data->$key ) : 0;
		}

		return $data;
	}

	/**
	 * Get chapter rating (cached)
	 *
	 * @since 2.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return object Rating data object.
	 */
	public static function get_chapter_rating( $chapter_id ) {
		$cache_key = 'fanfic_chapter_' . $chapter_id . '_rating';
		$data = get_transient( $cache_key );

		if ( false === $data ) {
			$data = self::rebuild_chapter_rating_from_db( $chapter_id );
			set_transient( $cache_key, $data, self::CACHE_DURATION );
		}

		return $data;
	}

	/**
	 * Get story rating (cached, derived from chapters)
	 *
	 * @since 2.0.0
	 * @param int $story_id Story ID.
	 * @return object Rating data object.
	 */
	public static function get_story_rating( $story_id ) {
		$cache_key = 'fanfic_story_' . $story_id . '_rating';
		$data = get_transient( $cache_key );

		if ( false === $data ) {
			$data = self::rebuild_story_rating_from_db( $story_id );
			set_transient( $cache_key, $data, self::CACHE_DURATION );
		}

		return $data;
	}

	/**
	 * AJAX: Check rating eligibility
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function ajax_check_eligibility() {
		check_ajax_referer( 'fanfic_rating_nonce', 'nonce' );

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

		// Get existing rating
		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_ratings';
		$existing_rating = null;

		if ( 'logged_in' === $identifier['type'] ) {
			$existing_rating = $wpdb->get_var( $wpdb->prepare(
				"SELECT rating FROM {$table_name} WHERE chapter_id = %d AND user_id = %d",
				$chapter_id,
				$identifier['user_id']
			) );
		} else {
			$existing_rating = $wpdb->get_var( $wpdb->prepare(
				"SELECT rating FROM {$table_name} WHERE chapter_id = %d AND identifier_hash = %s",
				$chapter_id,
				$identifier['hash']
			) );
		}

		wp_send_json_success( array(
			'can_vote'        => true,
			'existing_rating' => $existing_rating ? intval( $existing_rating ) : null,
		) );
	}

	/**
	 * AJAX: Submit rating
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function ajax_submit_rating() {
		check_ajax_referer( 'fanfic_rating_nonce', 'nonce' );

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$rating = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;
		$fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( $_POST['fingerprint'] ) : '';

		// Get user identifier
		$identifier = Fanfic_User_Identifier::get_identifier( $fingerprint );

		if ( ! $identifier ) {
			wp_send_json_error( array( 'message' => __( 'Could not identify user', 'fanfiction-manager' ) ) );
		}

		// Record vote
		$result = self::record_vote( $chapter_id, $rating, $identifier );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Get updated rating data
		$rating_data = self::get_chapter_rating( $chapter_id );

		wp_send_json_success( array(
			'action'         => $result['action'],
			'rating'         => $result['rating'],
			'total_votes'    => $rating_data->total_votes,
			'average_rating' => $rating_data->average_rating,
			'message'        => 'updated' === $result['action']
				? __( 'Rating updated!', 'fanfiction-manager' )
				: __( 'Rating submitted!', 'fanfiction-manager' ),
		) );
	}
}
