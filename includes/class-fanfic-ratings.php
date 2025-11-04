<?php
/**
 * Ratings System Class
 *
 * Handles chapter and story ratings with comprehensive functionality.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Ratings
 *
 * Comprehensive rating system for chapters and stories.
 *
 * @since 1.0.0
 */
class Fanfic_Ratings {

	/**
	 * Initialize ratings system
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register AJAX handlers
		add_action( 'wp_ajax_fanfic_submit_rating', array( __CLASS__, 'ajax_submit_rating' ) );
		add_action( 'wp_ajax_nopriv_fanfic_submit_rating', array( __CLASS__, 'ajax_submit_rating' ) );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue rating scripts and styles
	 *
	 * @since 1.0.0
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
					'thankYou'   => __( 'Thank you for rating!', 'fanfiction-manager' ),
					'error'      => __( 'An error occurred. Please try again.', 'fanfiction-manager' ),
					'loginFirst' => __( 'Please log in to rate.', 'fanfiction-manager' ),
				),
			)
		);
	}

	/**
	 * Save chapter rating to database
	 *
	 * @since 1.0.0
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $user_id    User ID (0 for anonymous).
	 * @param float $rating     Rating value (0.5-5.0).
	 * @return bool|int Rating ID on success, false on failure.
	 */
	public static function save_rating( $chapter_id, $user_id, $rating ) {
		global $wpdb;

		// Validate inputs
		$chapter_id = absint( $chapter_id );
		$user_id = absint( $user_id );
		$rating = floatval( $rating );

		if ( ! $chapter_id || $rating < 0.5 || $rating > 5.0 ) {
			return false;
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return false;
		}

		// Round to nearest 0.5
		$rating = round( $rating * 2 ) / 2;

		$table_name = $wpdb->prefix . 'fanfic_ratings';

		// Check for existing rating
		$existing_id = self::get_user_rating_id( $chapter_id, $user_id );

		if ( $existing_id ) {
			// Update existing rating
			$updated = $wpdb->update(
				$table_name,
				array( 'rating' => $rating ),
				array( 'id' => $existing_id ),
				array( '%f' ),
				array( '%d' )
			);

			if ( false !== $updated ) {
				// Clear transients
				self::clear_rating_cache( $chapter_id );
				return $existing_id;
			}
		} else {
			// Insert new rating
			$inserted = $wpdb->insert(
				$table_name,
				array(
					'chapter_id' => $chapter_id,
					'user_id'    => $user_id,
					'rating'     => $rating,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%f', '%s' )
			);

			if ( false !== $inserted ) {
				// Clear transients
				self::clear_rating_cache( $chapter_id );
				return $wpdb->insert_id;
			}
		}

		return false;
	}

	/**
	 * Get user's rating for a chapter
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $user_id    User ID (0 for anonymous).
	 * @return float|null Rating value or null if not rated.
	 */
	public static function get_user_rating( $chapter_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_ratings';

		$rating = $wpdb->get_var( $wpdb->prepare(
			"SELECT rating FROM {$table_name} WHERE chapter_id = %d AND user_id = %d LIMIT 1",
			absint( $chapter_id ),
			absint( $user_id )
		) );

		return $rating ? floatval( $rating ) : null;
	}

	/**
	 * Get user's rating ID for a chapter
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $user_id    User ID.
	 * @return int|null Rating ID or null if not found.
	 */
	private static function get_user_rating_id( $chapter_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_ratings';

		$rating_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE chapter_id = %d AND user_id = %d LIMIT 1",
			absint( $chapter_id ),
			absint( $user_id )
		) );

		return $rating_id ? absint( $rating_id ) : null;
	}

	/**
	 * Get average rating for a chapter
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return float Average rating (0.0-5.0).
	 */
	public static function get_chapter_rating( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_chapter_rating_' . $chapter_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return floatval( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_ratings';

		$avg_rating = $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(rating) FROM {$table_name} WHERE chapter_id = %d",
			$chapter_id
		) );

		$rating = $avg_rating ? round( floatval( $avg_rating ), 1 ) : 0.0;

		// Cache for 5 minutes
		set_transient( $cache_key, $rating, 5 * MINUTE_IN_SECONDS );

		return $rating;
	}

	/**
	 * Get total rating count for a chapter
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return int Number of ratings.
	 */
	public static function get_chapter_rating_count( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_chapter_rating_count_' . $chapter_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_ratings';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE chapter_id = %d",
			$chapter_id
		) );

		$count = absint( $count );

		// Cache for 5 minutes
		set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Calculate story rating (mean of all chapter ratings)
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return float Average story rating (0.0-5.0).
	 */
	public static function get_story_rating( $story_id ) {
		$story_id = absint( $story_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_story_rating_' . $story_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return floatval( $cached );
		}

		// Get all published chapters for this story
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $chapters ) ) {
			return 0.0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_ratings';

		// Get average rating across all chapters
		$chapter_ids = implode( ',', array_map( 'absint', $chapters ) );
		$avg_rating = $wpdb->get_var(
			"SELECT AVG(rating) FROM {$table_name} WHERE chapter_id IN ({$chapter_ids})"
		);

		$rating = $avg_rating ? round( floatval( $avg_rating ), 1 ) : 0.0;

		// Cache for 10 minutes
		set_transient( $cache_key, $rating, 10 * MINUTE_IN_SECONDS );

		return $rating;
	}

	/**
	 * Get total rating count for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Number of ratings across all chapters.
	 */
	public static function get_story_rating_count( $story_id ) {
		$story_id = absint( $story_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_story_rating_count_' . $story_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		// Get all published chapters for this story
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $chapters ) ) {
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_ratings';

		// Get total count across all chapters
		$chapter_ids = implode( ',', array_map( 'absint', $chapters ) );
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE chapter_id IN ({$chapter_ids})"
		);

		$count = absint( $count );

		// Cache for 10 minutes
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get top rated stories
	 *
	 * @since 1.0.0
	 * @param int $limit Number of stories to retrieve.
	 * @param int $min_ratings Minimum number of ratings required.
	 * @return array Array of story objects with rating data.
	 */
	public static function get_top_rated_stories( $limit = 10, $min_ratings = 5 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_top_rated_stories_' . $limit . '_' . $min_ratings;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get all published stories
		$stories = get_posts( array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $stories ) ) {
			return array();
		}

		// Calculate ratings for each story
		$story_ratings = array();
		foreach ( $stories as $story_id ) {
			$rating = self::get_story_rating( $story_id );
			$count = self::get_story_rating_count( $story_id );

			if ( $count >= $min_ratings ) {
				$story_ratings[] = array(
					'story_id' => $story_id,
					'rating'   => $rating,
					'count'    => $count,
				);
			}
		}

		// Sort by rating (descending)
		usort( $story_ratings, function( $a, $b ) {
			if ( $a['rating'] === $b['rating'] ) {
				return $b['count'] - $a['count']; // If equal rating, sort by count
			}
			return $b['rating'] - $a['rating'] > 0 ? 1 : -1;
		} );

		// Limit results
		$story_ratings = array_slice( $story_ratings, 0, absint( $limit ) );

		// Cache for 30 minutes
		set_transient( $cache_key, $story_ratings, 30 * MINUTE_IN_SECONDS );

		return $story_ratings;
	}

	/**
	 * Get recently rated stories
	 *
	 * @since 1.0.0
	 * @param int $limit Number of stories to retrieve.
	 * @return array Array of story IDs.
	 */
	public static function get_recently_rated_stories( $limit = 10 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_recently_rated_stories_' . $limit;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$ratings_table = $wpdb->prefix . 'fanfic_ratings';

		// Get recently rated chapters with their parent stories
		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.post_parent
			FROM {$ratings_table} r
			INNER JOIN {$wpdb->posts} p ON r.chapter_id = p.ID
			WHERE p.post_type = 'fanfiction_chapter'
			AND p.post_status = 'publish'
			AND p.post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'fanfiction_story' AND post_status = 'publish')
			ORDER BY r.created_at DESC
			LIMIT %d",
			absint( $limit )
		) );

		$story_ids = array_map( 'absint', $results );

		// Cache for 5 minutes
		set_transient( $cache_key, $story_ids, 5 * MINUTE_IN_SECONDS );

		return $story_ids;
	}

	/**
	 * Clear rating cache for a chapter and its parent story
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return void
	 */
	private static function clear_rating_cache( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		// Clear chapter caches
		delete_transient( 'fanfic_chapter_rating_' . $chapter_id );
		delete_transient( 'fanfic_chapter_rating_count_' . $chapter_id );

		// Clear parent story caches
		$story_id = get_post_field( 'post_parent', $chapter_id );
		if ( $story_id ) {
			delete_transient( 'fanfic_story_rating_' . $story_id );
			delete_transient( 'fanfic_story_rating_count_' . $story_id );
		}

		// Clear list caches
		delete_transient( 'fanfic_recently_rated_stories_10' );
		// Clear all top rated caches (multiple combinations possible)
		for ( $i = 5; $i <= 20; $i += 5 ) {
			for ( $j = 3; $j <= 10; $j++ ) {
				delete_transient( 'fanfic_top_rated_stories_' . $i . '_' . $j );
			}
		}
	}

	/**
	 * Generate star rating HTML
	 *
	 * @since 1.0.0
	 * @param float  $rating     Rating value (0.0-5.0).
	 * @param bool   $interactive Whether stars are interactive.
	 * @param string $size       Size class (small, medium, large).
	 * @return string Star rating HTML.
	 */
	public static function get_stars_html( $rating, $interactive = false, $size = 'medium' ) {
		$rating = floatval( $rating );
		$interactive_class = $interactive ? 'fanfic-rating-interactive' : 'fanfic-rating-readonly';
		$size_class = 'fanfic-rating-' . sanitize_html_class( $size );

		$html = '<div class="fanfic-rating-stars ' . esc_attr( $interactive_class ) . ' ' . esc_attr( $size_class ) . '" data-rating="' . esc_attr( $rating ) . '"';

		if ( $interactive ) {
			$html .= ' role="slider" aria-label="' . esc_attr__( 'Rate from 1 to 5 stars', 'fanfiction-manager' ) . '" aria-valuemin="0" aria-valuemax="5" aria-valuenow="' . esc_attr( $rating ) . '" tabindex="0"';
		}

		$html .= '>';

		for ( $i = 1; $i <= 5; $i++ ) {
			$star_class = 'fanfic-star';

			if ( $i <= floor( $rating ) ) {
				$star_class .= ' fanfic-star-full';
			} elseif ( $i - 0.5 <= $rating ) {
				$star_class .= ' fanfic-star-half';
			} else {
				$star_class .= ' fanfic-star-empty';
			}

			$html .= '<span class="' . esc_attr( $star_class ) . '" data-value="' . esc_attr( $i ) . '" aria-hidden="true">&#9733;</span>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * AJAX handler: Submit rating
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_submit_rating() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_rating_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$rating = isset( $_POST['rating'] ) ? floatval( $_POST['rating'] ) : 0;

		// Validate
		if ( ! $chapter_id || $rating < 0.5 || $rating > 5 ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid rating data.', 'fanfiction-manager' ),
			) );
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid chapter.', 'fanfiction-manager' ),
			) );
		}

		// Get user ID (0 for anonymous/guest)
		$user_id = get_current_user_id();

		// Save rating
		$rating_id = self::save_rating( $chapter_id, $user_id, $rating );

		if ( false === $rating_id ) {
			wp_send_json_error( array(
				'message' => __( 'Failed to save rating.', 'fanfiction-manager' ),
			) );
		}

		// Get updated stats
		$avg_rating = self::get_chapter_rating( $chapter_id );
		$total_ratings = self::get_chapter_rating_count( $chapter_id );

		wp_send_json_success( array(
			'message'       => __( 'Thank you for rating!', 'fanfiction-manager' ),
			'user_rating'   => round( $rating * 2 ) / 2,
			'avg_rating'    => $avg_rating,
			'total_ratings' => $total_ratings,
		) );
	}
}
