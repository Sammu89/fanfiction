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

		$table_chapter_index = $wpdb->prefix . 'fanfic_chapter_search_index';
		$table_interactions  = $wpdb->prefix . 'fanfic_interactions';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		// Query 1: Pre-computed aggregate stats from chapter search index (single PK lookup).
		$index_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT chapter_id, likes_total, rating_avg_total, rating_count_total, views_total
			FROM {$table_chapter_index}
			WHERE chapter_id IN ($placeholders)",
			$chapter_ids
		), ARRAY_A );

		foreach ( $index_rows as $row ) {
			$chapter_id = absint( $row['chapter_id'] );
			$result[ $chapter_id ]['like_count']   = absint( $row['likes_total'] );
			$result[ $chapter_id ]['avg_rating']   = $row['rating_avg_total'] ? round( floatval( $row['rating_avg_total'] ), 1 ) : 0.0;
			$result[ $chapter_id ]['rating_count'] = absint( $row['rating_count_total'] );
		}

		// Query 2 (only when user provided): User-specific like + rating in one pass.
		// Uses idx_type_chapter composite index for efficient filtering.
		if ( $user_id ) {
			$user_interactions = $wpdb->get_results( $wpdb->prepare(
				"SELECT chapter_id, interaction_type, value
				FROM {$table_interactions}
				WHERE user_id = %d AND chapter_id IN ($placeholders)
				AND interaction_type IN ('rating','like')",
				array_merge( array( $user_id ), $chapter_ids )
			), ARRAY_A );

			foreach ( $user_interactions as $row ) {
				$chapter_id = absint( $row['chapter_id'] );
				if ( 'rating' === $row['interaction_type'] ) {
					$result[ $chapter_id ]['user_rating'] = $row['value'] ? floatval( $row['value'] ) : null;
				} elseif ( 'like' === $row['interaction_type'] ) {
					$result[ $chapter_id ]['user_liked'] = true;
				}
			}
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
				'bookmark_count' => 0,
				'is_bookmarked'  => false,
			);
		}

		$table_interactions  = $wpdb->prefix . 'fanfic_interactions';
		$table_story_index   = $wpdb->prefix . 'fanfic_story_search_index';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $story_ids ), '%d' ) );

		// Query 1a: Bookmark counts from pre-computed story search index (PK lookup — fast).
		$index_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT story_id, bookmark_count
			FROM {$table_story_index}
			WHERE story_id IN ($placeholders)",
			$story_ids
		), ARRAY_A );

		foreach ( $index_rows as $row ) {
			$story_id = absint( $row['story_id'] );
			$result[ $story_id ]['bookmark_count'] = absint( $row['bookmark_count'] );
		}

		// If user_id provided, load user-specific data
		if ( $user_id ) {
			// Query 2: User bookmarks (targeted IN query — uses idx_type_chapter index).
			$user_bookmarks = $wpdb->get_col( $wpdb->prepare(
				"SELECT chapter_id FROM {$table_interactions}
				WHERE user_id = %d AND chapter_id IN ($placeholders) AND interaction_type = 'bookmark'",
				array_merge( array( $user_id ), $story_ids )
			) );

			foreach ( $user_bookmarks as $story_id ) {
				$story_id = absint( $story_id );
				$result[ $story_id ]['is_bookmarked'] = true;
			}

			// Deprecated relationship flags are intentionally disabled.
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
				'story_count'    => 0,
			);
		}

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );

		// Query: Load story counts
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

		$table_interactions = $wpdb->prefix . 'fanfic_interactions';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		// Single query to get rating stats from interactions table
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT chapter_id, COUNT(*) as rating_count, AVG(value) as avg_rating
			FROM {$table_interactions}
			WHERE chapter_id IN ($placeholders) AND interaction_type = 'rating'
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

		$table_interactions = $wpdb->prefix . 'fanfic_interactions';

		// Build placeholders for IN clause
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		// Single query to get like counts from interactions table
		$stats = $wpdb->get_results( $wpdb->prepare(
			"SELECT chapter_id, COUNT(*) as like_count
			FROM {$table_interactions}
			WHERE chapter_id IN ($placeholders) AND interaction_type = 'like'
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
