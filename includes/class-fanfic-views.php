<?php
/**
 * View Tracking System Class
 *
 * Handles view counting for stories and chapters with session-based tracking.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Views
 *
 * Session-based view tracking system.
 *
 * @since 1.0.0
 */
class Fanfic_Views {

	/**
	 * Initialize view tracking system
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Track views on single pages
		add_action( 'template_redirect', array( __CLASS__, 'track_view' ) );

		// Start session if not started
		add_action( 'init', array( __CLASS__, 'start_session' ) );
	}

	/**
	 * Start PHP session for view tracking
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function start_session() {
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
		}
	}

	/**
	 * Track view on single story/chapter pages
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function track_view() {
		if ( ! is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) {
			return;
		}

		$post_id = get_the_ID();
		$post_type = get_post_type( $post_id );

		// Check if already viewed in this session
		if ( self::is_viewed_in_session( $post_id ) ) {
			return;
		}

		// Increment view count
		if ( 'fanfiction_story' === $post_type ) {
			self::increment_story_views( $post_id );
		} elseif ( 'fanfiction_chapter' === $post_type ) {
			self::increment_chapter_views( $post_id );
		}

		// Mark as viewed in session
		self::mark_viewed_in_session( $post_id );
	}

	/**
	 * Check if post was viewed in current session
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return bool True if viewed, false otherwise.
	 */
	private static function is_viewed_in_session( $post_id ) {
		if ( ! isset( $_SESSION['fanfic_views'] ) ) {
			$_SESSION['fanfic_views'] = array();
		}

		return in_array( $post_id, $_SESSION['fanfic_views'], true );
	}

	/**
	 * Mark post as viewed in session
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function mark_viewed_in_session( $post_id ) {
		if ( ! isset( $_SESSION['fanfic_views'] ) ) {
			$_SESSION['fanfic_views'] = array();
		}

		$_SESSION['fanfic_views'][] = $post_id;

		// Limit session array to last 100 items to prevent memory issues
		if ( count( $_SESSION['fanfic_views'] ) > 100 ) {
			$_SESSION['fanfic_views'] = array_slice( $_SESSION['fanfic_views'], -100 );
		}
	}

	/**
	 * Increment story view count
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int New view count.
	 */
	public static function increment_story_views( $story_id ) {
		$story_id = absint( $story_id );

		$current_views = self::get_story_views( $story_id );
		$new_views = $current_views + 1;

		update_post_meta( $story_id, '_fanfic_views', $new_views );

		// Clear cache
		delete_transient( 'fanfic_story_views_' . $story_id );

		return $new_views;
	}

	/**
	 * Increment chapter view count
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return int New view count.
	 */
	public static function increment_chapter_views( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		$current_views = self::get_chapter_views( $chapter_id );
		$new_views = $current_views + 1;

		update_post_meta( $chapter_id, '_fanfic_views', $new_views );

		// Also increment parent story views
		$story_id = get_post_field( 'post_parent', $chapter_id );
		if ( $story_id ) {
			// Clear story view cache (it's calculated from chapters)
			delete_transient( 'fanfic_story_views_' . $story_id );
		}

		// Clear cache
		delete_transient( 'fanfic_chapter_views_' . $chapter_id );

		return $new_views;
	}

	/**
	 * Get story view count (sum of all chapter views)
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int View count.
	 */
	public static function get_story_views( $story_id ) {
		$story_id = absint( $story_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_story_views_' . $story_id;
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

		$total_views = 0;

		foreach ( $chapters as $chapter_id ) {
			$chapter_views = absint( get_post_meta( $chapter_id, '_fanfic_views', true ) );
			$total_views += $chapter_views;
		}

		// Cache for 5 minutes
		set_transient( $cache_key, $total_views, 5 * MINUTE_IN_SECONDS );

		return $total_views;
	}

	/**
	 * Get chapter view count
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return int View count.
	 */
	public static function get_chapter_views( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		// Try to get from transient cache
		$cache_key = 'fanfic_chapter_views_' . $chapter_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		$views = absint( get_post_meta( $chapter_id, '_fanfic_views', true ) );

		// Cache for 5 minutes
		set_transient( $cache_key, $views, 5 * MINUTE_IN_SECONDS );

		return $views;
	}

	/**
	 * Get most viewed stories
	 *
	 * @since 1.0.0
	 * @param int $limit     Number of stories to retrieve.
	 * @param int $min_views Minimum number of views required.
	 * @return array Array of story objects with view counts.
	 */
	public static function get_most_viewed_stories( $limit = 10, $min_views = 1 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_most_viewed_stories_' . $limit . '_' . $min_views;
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

		// Calculate views for each story
		$story_views = array();
		foreach ( $stories as $story_id ) {
			$views = self::get_story_views( $story_id );

			if ( $views >= $min_views ) {
				$story_views[] = array(
					'story_id' => $story_id,
					'views'    => $views,
				);
			}
		}

		// Sort by views (descending)
		usort( $story_views, function( $a, $b ) {
			return $b['views'] - $a['views'];
		} );

		// Limit results
		$story_views = array_slice( $story_views, 0, absint( $limit ) );

		// Cache for 30 minutes
		set_transient( $cache_key, $story_views, 30 * MINUTE_IN_SECONDS );

		return $story_views;
	}

	/**
	 * Get trending stories (most views in last 7 days)
	 *
	 * @since 1.0.0
	 * @param int $limit Number of stories to retrieve.
	 * @return array Array of story IDs.
	 */
	public static function get_trending_stories( $limit = 10 ) {
		// Try to get from transient cache
		$cache_key = 'fanfic_trending_stories_' . $limit;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get stories published or updated in last 7 days
		$stories = get_posts( array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after'     => '7 days ago',
					'inclusive' => true,
				),
			),
		) );

		if ( empty( $stories ) ) {
			return array();
		}

		// Calculate views for each story
		$story_views = array();
		foreach ( $stories as $story_id ) {
			$views = self::get_story_views( $story_id );
			$story_views[ $story_id ] = $views;
		}

		// Sort by views (descending)
		arsort( $story_views );

		// Get top story IDs
		$trending_stories = array_keys( array_slice( $story_views, 0, absint( $limit ), true ) );

		// Cache for 1 hour
		set_transient( $cache_key, $trending_stories, HOUR_IN_SECONDS );

		return $trending_stories;
	}

	/**
	 * Get view statistics
	 *
	 * @since 1.0.0
	 * @return array Array with view statistics.
	 */
	public static function get_view_stats() {
		// Try to get from transient cache
		$cache_key = 'fanfic_view_stats';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$stats = array(
			'total_story_views'   => 0,
			'total_chapter_views' => 0,
			'avg_views_per_story' => 0,
		);

		// Total chapter views
		$stats['total_chapter_views'] = absint( $wpdb->get_var(
			"SELECT SUM(meta_value) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'fanfiction_chapter'
			AND p.post_status = 'publish'
			AND pm.meta_key = '_fanfic_views'"
		) );

		// Count published stories
		$story_count = absint( $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'fanfiction_story'
			AND post_status = 'publish'"
		) );

		// Total story views (same as chapter views since they're counted per chapter)
		$stats['total_story_views'] = $stats['total_chapter_views'];

		// Average views per story
		if ( $story_count > 0 ) {
			$stats['avg_views_per_story'] = round( $stats['total_chapter_views'] / $story_count, 1 );
		}

		// Cache for 1 hour
		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}
}
