<?php
/**
 * Batch Loader Utility Class
 *
 * Consolidates batch loading queries to prevent N+1 problems across the plugin.
 * Provides efficient methods for loading interaction statistics in bulk.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Batch_Loader
 *
 * Efficient batch loading utilities for preventing N+1 query problems.
 * All methods use single queries with JOINs or IN clauses.
 *
 * @since 1.0.0
 */
class Fanfic_Batch_Loader {

	/**
	 * Batch load chapter statistics
	 *
	 * Loads all chapter stats in a single query with LEFT JOINs.
	 * Optionally includes user-specific data.
	 *
	 * @since 1.0.0
	 * @param array    $chapter_ids Array of chapter IDs.
	 * @param int|null $user_id     Optional user ID for user-specific stats.
	 * @return array Array[ chapter_id => [ stats ] ].
	 */
	public static function batch_load_chapter_stats( $chapter_ids, $user_id = null ) {
		global $wpdb;

		// Validate inputs
		$chapter_ids = array_map( 'absint', (array) $chapter_ids );
		$chapter_ids = array_filter( $chapter_ids );

		if ( empty( $chapter_ids ) ) {
			return array();
		}

		$user_id = $user_id ? absint( $user_id ) : null;

		// Initialize result array
		$result = array();
		foreach ( $chapter_ids as $chapter_id ) {
			$result[ $chapter_id ] = array(
				'rating_count' => 0,
				'avg_rating'   => 0.0,
				'like_count'   => 0,
				'user_rating'  => null,
				'user_liked'   => false,
				'is_read'      => false,
			);
		}

		$table_ratings = $wpdb->prefix . 'fanfic_ratings';
		$table_likes   = $wpdb->prefix . 'fanfic_likes';
		$table_reading = $wpdb->prefix . 'fanfic_reading_progress';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		// Query 1: Load aggregate rating and like stats
		$query = "
			SELECT
				c.chapter_id,
				COUNT(DISTINCT r.id) as rating_count,
				AVG(r.rating) as avg_rating,
				COUNT(DISTINCT l.id) as like_count
			FROM (SELECT " . implode( ' AS chapter_id UNION SELECT ', $chapter_ids ) . " AS chapter_id) c
			LEFT JOIN {$table_ratings} r ON c.chapter_id = r.chapter_id
			LEFT JOIN {$table_likes} l ON c.chapter_id = l.chapter_id
			GROUP BY c.chapter_id
		";

		$stats = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $stats as $stat ) {
			$chapter_id = absint( $stat['chapter_id'] );
			$result[ $chapter_id ]['rating_count'] = absint( $stat['rating_count'] );
			$result[ $chapter_id ]['avg_rating']   = $stat['avg_rating'] ? round( floatval( $stat['avg_rating'] ), 1 ) : 0.0;
			$result[ $chapter_id ]['like_count']   = absint( $stat['like_count'] );
		}

		// If user_id provided, load user-specific data
		if ( $user_id ) {
			// Query 2: User ratings
			$user_ratings = $wpdb->get_results( $wpdb->prepare(
				"SELECT chapter_id, rating FROM {$table_ratings}
				WHERE chapter_id IN ($placeholders) AND user_id = %d",
				array_merge( $chapter_ids, array( $user_id ) )
			), ARRAY_A );

			foreach ( $user_ratings as $rating ) {
				$chapter_id = absint( $rating['chapter_id'] );
				$result[ $chapter_id ]['user_rating'] = absint( $rating['rating'] );
			}

			// Query 3: User likes
			$user_likes = $wpdb->get_col( $wpdb->prepare(
				"SELECT chapter_id FROM {$table_likes}
				WHERE chapter_id IN ($placeholders) AND user_id = %d",
				array_merge( $chapter_ids, array( $user_id ) )
			) );

			foreach ( $user_likes as $chapter_id ) {
				$chapter_id = absint( $chapter_id );
				$result[ $chapter_id ]['user_liked'] = true;
			}

			// Query 4: Reading progress (which chapters are marked as read)
			// Note: We need story_id to query reading_progress, so this is optional
			// For now, we'll skip this unless we have story context
		}

		return $result;
	}

	/**
	 * Batch load story statistics
	 *
	 * Loads all story stats in a single query with LEFT JOINs.
	 * Optionally includes user-specific data.
	 *
	 * @since 1.0.0
	 * @param array    $story_ids Array of story IDs.
	 * @param int|null $user_id   Optional user ID for user-specific stats.
	 * @return array Array[ story_id => [ stats ] ].
	 */
	public static function batch_load_story_stats( $story_ids, $user_id = null ) {
		global $wpdb;

		// Validate inputs
		$story_ids = array_map( 'absint', (array) $story_ids );
		$story_ids = array_filter( $story_ids );

		if ( empty( $story_ids ) ) {
			return array();
		}

		$user_id = $user_id ? absint( $user_id ) : null;

		// Initialize result array
		$result = array();
		foreach ( $story_ids as $story_id ) {
			$result[ $story_id ] = array(
				'follower_count'  => 0,
				'bookmark_count'  => 0,
				'is_bookmarked'   => false,
				'is_following'    => false,
				'email_enabled'   => false,
			);
		}

		$table_follows   = $wpdb->prefix . 'fanfic_follows';
		$table_bookmarks = $wpdb->prefix . 'fanfic_bookmarks';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $story_ids ), '%d' ) );

		// Query 1: Load aggregate follow and bookmark stats
		$query = "
			SELECT
				s.story_id,
				COUNT(DISTINCT f.id) as follower_count,
				COUNT(DISTINCT b.id) as bookmark_count
			FROM (SELECT " . implode( ' AS story_id UNION SELECT ', $story_ids ) . " AS story_id) s
			LEFT JOIN {$table_follows} f ON s.story_id = f.target_id AND f.follow_type = 'story'
			LEFT JOIN {$table_bookmarks} b ON s.story_id = b.post_id AND b.bookmark_type = 'story'
			GROUP BY s.story_id
		";

		$stats = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $stats as $stat ) {
			$story_id = absint( $stat['story_id'] );
			$result[ $story_id ]['follower_count'] = absint( $stat['follower_count'] );
			$result[ $story_id ]['bookmark_count'] = absint( $stat['bookmark_count'] );
		}

		// If user_id provided, load user-specific data
		if ( $user_id ) {
			// Query 2: User bookmarks
			$user_bookmarks = $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$table_bookmarks}
				WHERE post_id IN ($placeholders) AND user_id = %d AND bookmark_type = 'story'",
				array_merge( $story_ids, array( $user_id ) )
			) );

			foreach ( $user_bookmarks as $story_id ) {
				$story_id = absint( $story_id );
				$result[ $story_id ]['is_bookmarked'] = true;
			}

			// Query 3: User follows
			$user_follows = $wpdb->get_results( $wpdb->prepare(
				"SELECT target_id, email_enabled FROM {$table_follows}
				WHERE target_id IN ($placeholders) AND user_id = %d AND follow_type = 'story'",
				array_merge( $story_ids, array( $user_id ) )
			), ARRAY_A );

			foreach ( $user_follows as $follow ) {
				$story_id = absint( $follow['target_id'] );
				$result[ $story_id ]['is_following']  = true;
				$result[ $story_id ]['email_enabled'] = (bool) $follow['email_enabled'];
			}
		}

		return $result;
	}

	/**
	 * Batch load author statistics
	 *
	 * Loads all author stats in a single query with LEFT JOINs.
	 *
	 * @since 1.0.0
	 * @param array $author_ids Array of author (user) IDs.
	 * @return array Array[ author_id => [ stats ] ].
	 */
	public static function batch_load_author_stats( $author_ids ) {
		global $wpdb;

		// Validate inputs
		$author_ids = array_map( 'absint', (array) $author_ids );
		$author_ids = array_filter( $author_ids );

		if ( empty( $author_ids ) ) {
			return array();
		}

		// Initialize result array
		$result = array();
		foreach ( $author_ids as $author_id ) {
			$result[ $author_id ] = array(
				'follower_count' => 0,
				'story_count'    => 0,
			);
		}

		$table_follows = $wpdb->prefix . 'fanfic_follows';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );

		// Query 1: Load follower counts
		$follower_stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT target_id as author_id, COUNT(*) as follower_count
			FROM {$table_follows}
			WHERE target_id IN ($placeholders) AND follow_type = 'author'
			GROUP BY target_id",
			$author_ids
		), ARRAY_A );

		foreach ( $follower_stats as $stat ) {
			$author_id = absint( $stat['author_id'] );
			$result[ $author_id ]['follower_count'] = absint( $stat['follower_count'] );
		}

		// Query 2: Load story counts
		$story_stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_author as author_id, COUNT(*) as story_count
			FROM {$wpdb->posts}
			WHERE post_author IN ($placeholders)
			AND post_type = 'fanfiction_story'
			AND post_status = 'publish'
			GROUP BY post_author",
			$author_ids
		), ARRAY_A );

		foreach ( $story_stats as $stat ) {
			$author_id = absint( $stat['author_id'] );
			$result[ $author_id ]['story_count'] = absint( $stat['story_count'] );
		}

		return $result;
	}

	/**
	 * Batch load follow status for multiple targets
	 *
	 * Efficient method to check if user is following multiple targets.
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param array  $target_ids  Array of target IDs.
	 * @param string $follow_type Type: 'story' or 'author'.
	 * @return array Array[ target_id => [ is_following, email_enabled ] ].
	 */
	public static function batch_load_follow_status( $user_id, $target_ids, $follow_type = 'story' ) {
		// Delegate to Fanfic_Follows class
		if ( class_exists( 'Fanfic_Follows' ) ) {
			return Fanfic_Follows::batch_get_follow_status( $user_id, $target_ids, $follow_type );
		}

		return array();
	}

	/**
	 * Batch load bookmark status for multiple posts
	 *
	 * Efficient method to check if user has bookmarked multiple posts.
	 *
	 * @since 1.0.0
	 * @param int    $user_id       User ID.
	 * @param array  $post_ids      Array of post IDs.
	 * @param string $bookmark_type Type: 'story' or 'chapter'.
	 * @return array Array[ post_id => boolean ].
	 */
	public static function batch_load_bookmark_status( $user_id, $post_ids, $bookmark_type = 'story' ) {
		// Delegate to Fanfic_Bookmarks class
		if ( class_exists( 'Fanfic_Bookmarks' ) ) {
			return Fanfic_Bookmarks::batch_get_bookmark_status( $user_id, $post_ids, $bookmark_type );
		}

		return array();
	}

	/**
	 * Batch load chapter ratings for multiple chapters
	 *
	 * Loads rating counts and averages efficiently.
	 *
	 * @since 1.0.0
	 * @param array $chapter_ids Array of chapter IDs.
	 * @return array Array[ chapter_id => [ rating_count, avg_rating ] ].
	 */
	public static function batch_load_chapter_ratings( $chapter_ids ) {
		global $wpdb;

		// Validate inputs
		$chapter_ids = array_map( 'absint', (array) $chapter_ids );
		$chapter_ids = array_filter( $chapter_ids );

		if ( empty( $chapter_ids ) ) {
			return array();
		}

		// Initialize result array
		$result = array();
		foreach ( $chapter_ids as $chapter_id ) {
			$result[ $chapter_id ] = array(
				'rating_count' => 0,
				'avg_rating'   => 0.0,
			);
		}

		$table_ratings = $wpdb->prefix . 'fanfic_ratings';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		// Single query to get rating stats
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT chapter_id, COUNT(*) as rating_count, AVG(rating) as avg_rating
			FROM {$table_ratings}
			WHERE chapter_id IN ($placeholders)
			GROUP BY chapter_id",
			$chapter_ids
		), ARRAY_A );

		foreach ( $stats as $stat ) {
			$chapter_id = absint( $stat['chapter_id'] );
			$result[ $chapter_id ]['rating_count'] = absint( $stat['rating_count'] );
			$result[ $chapter_id ]['avg_rating']   = $stat['avg_rating'] ? round( floatval( $stat['avg_rating'] ), 1 ) : 0.0;
		}

		return $result;
	}

	/**
	 * Batch load chapter likes for multiple chapters
	 *
	 * Loads like counts efficiently.
	 *
	 * @since 1.0.0
	 * @param array $chapter_ids Array of chapter IDs.
	 * @return array Array[ chapter_id => like_count ].
	 */
	public static function batch_load_chapter_likes( $chapter_ids ) {
		global $wpdb;

		// Validate inputs
		$chapter_ids = array_map( 'absint', (array) $chapter_ids );
		$chapter_ids = array_filter( $chapter_ids );

		if ( empty( $chapter_ids ) ) {
			return array();
		}

		// Initialize result array
		$result = array_fill_keys( $chapter_ids, 0 );

		$table_likes = $wpdb->prefix . 'fanfic_likes';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		// Single query to get like counts
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT chapter_id, COUNT(*) as like_count
			FROM {$table_likes}
			WHERE chapter_id IN ($placeholders)
			GROUP BY chapter_id",
			$chapter_ids
		), ARRAY_A );

		foreach ( $stats as $stat ) {
			$chapter_id = absint( $stat['chapter_id'] );
			$result[ $chapter_id ] = absint( $stat['like_count'] );
		}

		return $result;
	}
}
