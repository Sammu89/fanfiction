<?php
/**
 * Story and Chapter Cache Functions
 *
 * Provides caching functions for story and chapter operations to optimize performance.
 * Uses WordPress transients API with appropriate TTL values for different data types.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache TTL Constants
 */
define( 'FANFIC_CACHE_5_MINUTES', 5 * MINUTE_IN_SECONDS );
define( 'FANFIC_CACHE_30_MINUTES', 30 * MINUTE_IN_SECONDS );
define( 'FANFIC_CACHE_1_HOUR', HOUR_IN_SECONDS );
define( 'FANFIC_CACHE_6_HOURS', 6 * HOUR_IN_SECONDS );

/**
 * Get cached story view count from unified views index.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return int Total view count, 0 if no views or on error.
 */
function ffm_get_story_views( $story_id ) {
	return class_exists( 'Fanfic_Interactions' ) ? Fanfic_Interactions::get_story_views( $story_id ) : 0;
}

/**
 * Get cached story chapter count
 *
 * Returns the number of published chapters for a story.
 * Cached for 6 hours as chapter counts change infrequently.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return int Chapter count, 0 if no chapters or on error.
 */
function ffm_get_story_chapter_count( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return 0;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_chapter_count_' . $story_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return absint( $cached );
	}

	// Query chapter count directly
	$count = ffm_get_chapter_count( $story_id );

	// Cache for 6 hours
	set_transient( $cache_key, $count, FANFIC_CACHE_6_HOURS );

	return $count;
}

/**
 * Get cached story word count (sum of all chapter word counts)
 *
 * Calculates total word count for a story by summing all chapter word counts.
 * Cached for 6 hours since word counts change infrequently.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return int Total word count, 0 if no chapters or on error.
 */
function ffm_get_story_word_count( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return 0;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_word_count_' . $story_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return absint( $cached );
	}

	global $wpdb;

	// Query sum of word counts for all published chapters
	$total_words = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_parent = %d
			AND p.post_type = 'fanfiction_chapter'
			AND p.post_status = 'publish'
			AND pm.meta_key = '_fanfic_word_count'",
			$story_id
		)
	);

	$total_words = absint( $total_words );

	// Cache for 6 hours
	set_transient( $cache_key, $total_words, FANFIC_CACHE_6_HOURS );

	return $total_words;
}

/**
 * Check if story is valid (cached)
 *
 * A story is valid if it has:
 * - An introduction (excerpt)
 * - At least one chapter
 * - At least one genre and status
 *
 * Cached for 6 hours to optimize validation checks.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return bool True if valid, false otherwise.
 */
function ffm_is_story_valid( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return false;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_story_valid_' . $story_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return (bool) $cached;
	}

	// Get the story
	$story = get_post( $story_id );

	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		set_transient( $cache_key, 0, FANFIC_CACHE_6_HOURS );
		return false;
	}

	// Check for introduction (excerpt)
	$has_introduction = ! empty( $story->post_excerpt );

	// Check for at least one chapter
	$chapter_count = ffm_get_chapter_count( $story_id );
	$has_chapters  = $chapter_count > 0;

	// Check for at least one genre
	$genres     = wp_get_post_terms( $story_id, 'fanfiction_genre', array( 'fields' => 'ids' ) );
	$has_genres = ! empty( $genres ) && ! is_wp_error( $genres );

	// Check for status
	$status     = wp_get_post_terms( $story_id, 'fanfiction_status', array( 'fields' => 'ids' ) );
	$has_status = ! empty( $status ) && ! is_wp_error( $status );

	// Story is valid if all conditions are met
	$is_valid = $has_introduction && $has_chapters && $has_genres && $has_status;

	// Cache for 6 hours
	set_transient( $cache_key, (int) $is_valid, FANFIC_CACHE_6_HOURS );

	return $is_valid;
}

/**
 * Get average story rating (cached)
 *
 * Calculates average rating across all chapters in a story.
 * Cached for 5 minutes to reflect recent rating changes.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return float Average rating (0-5), 0 if no ratings.
 */
function ffm_get_story_rating( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return 0.0;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_story_rating_' . $story_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return floatval( $cached );
	}

	$avg_rating = 0.0;
	if ( class_exists( 'Fanfic_Interactions' ) ) {
		$rating_data = Fanfic_Interactions::get_story_rating( $story_id );
		$avg_rating  = $rating_data ? floatval( $rating_data->average_rating ) : 0.0;
	}

	// Cache for 5 minutes
	set_transient( $cache_key, $avg_rating, FANFIC_CACHE_5_MINUTES );

	return $avg_rating;
}

/**
 * Get recent published stories (cached)
 *
 * Returns paginated list of recent stories ordered by publication date.
 * Cached for 30 minutes to reduce database load on popular pages.
 *
 * @since 1.0.0
 * @param int $page     Page number (1-indexed).
 * @param int $per_page Number of stories per page.
 * @return array Array of story IDs, empty array on error.
 */
function ffm_get_recent_stories( $page = 1, $per_page = 10 ) {
	$page     = absint( $page );
	$per_page = absint( $per_page );

	if ( ! $page ) {
		$page = 1;
	}
	if ( ! $per_page ) {
		$per_page = 10;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_recent_stories_' . $page . '_' . $per_page;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Calculate offset
	$offset = ( $page - 1 ) * $per_page;

	// Query recent stories
	$stories = get_posts(
		array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	if ( empty( $stories ) ) {
		$stories = array();
	}

	// Cache for 30 minutes
	set_transient( $cache_key, $stories, FANFIC_CACHE_30_MINUTES );

	return $stories;
}

/**
 * Get stories by genre (cached)
 *
 * Returns paginated list of stories filtered by genre.
 * Cached for 1 hour as genre listings are relatively stable.
 *
 * @since 1.0.0
 * @param int $genre_id Genre term ID.
 * @param int $page     Page number (1-indexed).
 * @param int $per_page Number of stories per page.
 * @return array Array of story IDs, empty array on error.
 */
function ffm_get_stories_by_genre( $genre_id, $page = 1, $per_page = 10 ) {
	$genre_id = absint( $genre_id );
	$page     = absint( $page );
	$per_page = absint( $per_page );

	if ( ! $genre_id ) {
		return array();
	}
	if ( ! $page ) {
		$page = 1;
	}
	if ( ! $per_page ) {
		$per_page = 10;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_genre_stories_' . $genre_id . '_' . $page . '_' . $per_page;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Calculate offset
	$offset = ( $page - 1 ) * $per_page;

	// Query stories by genre
	$stories = get_posts(
		array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => 'fanfiction_genre',
					'field'    => 'term_id',
					'terms'    => $genre_id,
				),
			),
		)
	);

	if ( empty( $stories ) ) {
		$stories = array();
	}

	// Cache for 1 hour
	set_transient( $cache_key, $stories, FANFIC_CACHE_1_HOUR );

	return $stories;
}

/**
 * Get stories by status (cached)
 *
 * Returns paginated list of stories filtered by status.
 * Cached for 1 hour as status listings are relatively stable.
 *
 * @since 1.0.0
 * @param int $status_id Status term ID.
 * @param int $page      Page number (1-indexed).
 * @param int $per_page  Number of stories per page.
 * @return array Array of story IDs, empty array on error.
 */
function ffm_get_stories_by_status( $status_id, $page = 1, $per_page = 10 ) {
	$status_id = absint( $status_id );
	$page      = absint( $page );
	$per_page  = absint( $per_page );

	if ( ! $status_id ) {
		return array();
	}
	if ( ! $page ) {
		$page = 1;
	}
	if ( ! $per_page ) {
		$per_page = 10;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_status_stories_' . $status_id . '_' . $page . '_' . $per_page;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Calculate offset
	$offset = ( $page - 1 ) * $per_page;

	// Query stories by status
	$stories = get_posts(
		array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => 'fanfiction_status',
					'field'    => 'term_id',
					'terms'    => $status_id,
				),
			),
		)
	);

	if ( empty( $stories ) ) {
		$stories = array();
	}

	// Cache for 1 hour
	set_transient( $cache_key, $stories, FANFIC_CACHE_1_HOUR );

	return $stories;
}

/**
 * Get cached chapter views
 *
 * Returns view count for a specific chapter from the unified index.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter post ID.
 * @return int View count, 0 if no views or on error.
 */
function ffm_get_chapter_views( $chapter_id ) {
	return class_exists( 'Fanfic_Interactions' ) ? Fanfic_Interactions::get_chapter_views( $chapter_id ) : 0;
}

/**
 * Get chapter rating (cached)
 *
 * Returns average rating for a specific chapter.
 * Cached for 5 minutes to reflect recent rating changes.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter post ID.
 * @return float Average rating (0-5), 0 if no ratings.
 */
function ffm_get_chapter_rating( $chapter_id ) {
	$chapter_id = absint( $chapter_id );

	if ( ! $chapter_id ) {
		return 0.0;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_chapter_rating_' . $chapter_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return floatval( $cached );
	}

	$avg_rating = 0.0;
	if ( class_exists( 'Fanfic_Interactions' ) ) {
		$stats      = Fanfic_Interactions::get_chapter_stats( $chapter_id );
		$avg_rating = isset( $stats['rating_avg'] ) ? floatval( $stats['rating_avg'] ) : 0.0;
	}

	// Cache for 5 minutes
	set_transient( $cache_key, $avg_rating, FANFIC_CACHE_5_MINUTES );

	return $avg_rating;
}

/**
 * Get efficient chapter count for a story
 *
 * Direct database query to count published chapters efficiently.
 * This is a helper function used by caching functions.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return int Chapter count, 0 on error.
 */
function ffm_get_chapter_count( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return 0;
	}

	global $wpdb;

	// Direct count query for efficiency
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts}
			WHERE post_parent = %d
			AND post_type = 'fanfiction_chapter'
			AND post_status = 'publish'",
			$story_id
		)
	);

	return absint( $count );
}

/**
 * Get ordered chapter list (cached)
 *
 * Returns array of chapter objects ordered by menu_order.
 * Cached for 1 hour as chapter order changes infrequently.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return array Array of chapter post objects, empty array on error.
 */
function ffm_get_chapter_list( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return array();
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_chapter_list_' . $story_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Query chapters ordered by menu_order
	$chapters = get_posts(
		array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	if ( empty( $chapters ) ) {
		$chapters = array();
	}

	// Cache for 1 hour
	set_transient( $cache_key, $chapters, FANFIC_CACHE_1_HOUR );

	return $chapters;
}

/**
 * Clear all cache for a specific story
 *
 * Deletes all transients related to a story, including views,
 * ratings, chapter counts, and related listings.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID.
 * @return void
 */
function ffm_clear_story_cache( $story_id ) {
	$story_id = absint( $story_id );

	if ( ! $story_id ) {
		return;
	}

	// Clear story-specific caches
	delete_transient( 'fanfic_story_views_' . $story_id );
	delete_transient( 'fanfic_chapter_count_' . $story_id );
	delete_transient( 'fanfic_word_count_' . $story_id );
	delete_transient( 'fanfic_story_valid_' . $story_id );
	delete_transient( 'fanfic_story_rating_' . $story_id );
	delete_transient( 'fanfic_chapter_list_' . $story_id );

	// Clear listing caches that may include this story
	global $wpdb;

	// Clear recent stories cache (multiple pages)
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_fanfic_recent_stories_%'
		OR option_name LIKE '_transient_timeout_fanfic_recent_stories_%'"
	);

	// Clear genre stories cache
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_fanfic_genre_stories_%'
		OR option_name LIKE '_transient_timeout_fanfic_genre_stories_%'"
	);

	// Clear status stories cache
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_fanfic_status_stories_%'
		OR option_name LIKE '_transient_timeout_fanfic_status_stories_%'"
	);
}

/**
 * Clear all cache for a specific chapter
 *
 * Deletes all transients related to a chapter and its parent story.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter post ID.
 * @return void
 */
function ffm_clear_chapter_cache( $chapter_id ) {
	$chapter_id = absint( $chapter_id );

	if ( ! $chapter_id ) {
		return;
	}

	// Clear chapter-specific caches
	delete_transient( 'fanfic_chapter_views_' . $chapter_id );
	delete_transient( 'fanfic_chapter_rating_' . $chapter_id );

	// Get parent story and clear its caches too
	$story_id = get_post_field( 'post_parent', $chapter_id );
	if ( $story_id ) {
		ffm_clear_story_cache( $story_id );
	}
}

/**
 * Clear all fanfiction-related caches
 *
 * Nuclear option: Clears all story and chapter caches site-wide.
 * Use sparingly, typically only when taxonomy changes occur.
 *
 * @since 1.0.0
 * @return void
 */
function ffm_clear_all_fanfiction_cache() {
	global $wpdb;

	// Clear all fanfiction transients
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_fanfic_%'
		OR option_name LIKE '_transient_timeout_fanfic_%'"
	);
}
