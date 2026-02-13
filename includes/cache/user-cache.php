<?php
/**
 * User and Interaction Cache Functions
 *
 * Provides caching functions for user-specific operations including profiles,
 * bookmarks, and notifications. Uses WordPress transients API with
 * appropriate TTL values optimized for different data types.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache TTL Constants (imported from story-cache.php for consistency)
 */
if ( ! defined( 'FANFIC_CACHE_1_MINUTE' ) ) {
	define( 'FANFIC_CACHE_1_MINUTE', 1 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'FANFIC_CACHE_2_MINUTES' ) ) {
	define( 'FANFIC_CACHE_2_MINUTES', 2 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'FANFIC_CACHE_5_MINUTES' ) ) {
	define( 'FANFIC_CACHE_5_MINUTES', 5 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'FANFIC_CACHE_10_MINUTES' ) ) {
	define( 'FANFIC_CACHE_10_MINUTES', 10 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'FANFIC_CACHE_30_MINUTES' ) ) {
	define( 'FANFIC_CACHE_30_MINUTES', 30 * MINUTE_IN_SECONDS );
}

/* ============================================================================
 * USER DATA FUNCTIONS
 * ========================================================================= */

/**
 * Get cached user profile data
 *
 * Retrieves comprehensive user profile information including WordPress user
 * data and fanfiction-specific metadata. Cached for 5 minutes as profile
 * data changes infrequently but should remain reasonably fresh.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return array|false {
 *     User profile data array on success, false on failure.
 *
 *     @type int    $ID              User ID.
 *     @type string $user_login      User login name.
 *     @type string $user_nicename   User nicename.
 *     @type string $display_name    User display name.
 *     @type string $user_email      User email address.
 *     @type string $user_registered Registration date.
 *     @type string $description     User bio/description.
 *     @type string $user_url        User website URL.
 *     @type int    $story_count     Number of published stories.
 * }
 */
function ffm_get_user_profile( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return false;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_user_profile_' . $user_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Get WordPress user data
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		// Cache the negative result briefly to prevent repeated queries
		set_transient( $cache_key, false, FANFIC_CACHE_1_MINUTE );
		return false;
	}

	// Build profile data array
	$profile = array(
		'ID'              => $user->ID,
		'user_login'      => $user->user_login,
		'user_nicename'   => $user->user_nicename,
		'display_name'    => $user->display_name,
		'user_email'      => $user->user_email,
		'user_registered' => $user->user_registered,
		'description'     => get_user_meta( $user_id, 'description', true ),
		'user_url'        => $user->user_url,
		'story_count'     => ffm_get_user_story_count( $user_id ),
	);

	// Cache for 5 minutes
	set_transient( $cache_key, $profile, FANFIC_CACHE_5_MINUTES );

	return $profile;
}

/**
 * Get cached user story count
 *
 * Returns the number of published stories authored by a user.
 * Cached for 30 minutes as story counts change infrequently.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return int Story count, 0 on error or if no stories.
 */
function ffm_get_user_story_count( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return 0;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_user_story_count_' . $user_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return absint( $cached );
	}

	global $wpdb;

	// Direct count query for efficiency
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts}
			WHERE post_author = %d
			AND post_type = 'fanfiction_story'
			AND post_status = 'publish'",
			$user_id
		)
	);

	$count = absint( $count );

	// Cache for 30 minutes
	set_transient( $cache_key, $count, FANFIC_CACHE_30_MINUTES );

	return $count;
}

/* ============================================================================
 * NOTIFICATION FUNCTIONS
 * ========================================================================= */

/**
 * Get user notifications with pagination (cached)
 *
 * Returns paginated list of notifications for a user, ordered by creation
 * date (most recent first). Cached for 2 minutes for near real-time updates.
 *
 * @since 1.0.0
 * @param int $user_id  User ID.
 * @param int $page     Page number (1-indexed).
 * @param int $per_page Number of notifications per page (10, 15, or 20).
 * @return array Array of notification objects, empty array on error.
 */
function ffm_get_user_notifications( $user_id, $page = 1, $per_page = 10 ) {
	$user_id  = absint( $user_id );
	$page     = absint( $page );
	$per_page = absint( $per_page );

	if ( ! $user_id ) {
		return array();
	}
	if ( ! $page ) {
		$page = 1;
	}
	if ( ! in_array( $per_page, array( 10, 15, 20 ), true ) ) {
		$per_page = 10;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_user_notifications_' . $user_id . '_' . $page . '_' . $per_page;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;

	// Calculate offset
	$offset = ( $page - 1 ) * $per_page;

	// Query notifications
	$notifications = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, type, message, link, is_read, created_at
			FROM {$wpdb->prefix}fanfic_notifications
			WHERE user_id = %d
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$user_id,
			$per_page,
			$offset
		)
	);

	if ( empty( $notifications ) ) {
		$notifications = array();
	}

	// Cache for 2 minutes (short TTL for real-time feel)
	set_transient( $cache_key, $notifications, FANFIC_CACHE_2_MINUTES );

	return $notifications;
}

/**
 * Get cached unread notification count
 *
 * Returns the number of unread notifications for a user.
 * Cached for 1 minute as this is frequently checked and needs to be current.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return int Unread notification count, 0 on error or if no unread notifications.
 */
function ffm_get_unread_count( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return 0;
	}

	// Try to get from transient cache
	$cache_key = 'fanfic_unread_count_' . $user_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return absint( $cached );
	}

	global $wpdb;

	// Direct count query for unread notifications
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->prefix}fanfic_notifications
			WHERE user_id = %d
			AND is_read = 0",
			$user_id
		)
	);

	$count = absint( $count );

	// Cache for 1 minute
	set_transient( $cache_key, $count, FANFIC_CACHE_1_MINUTE );

	return $count;
}

/**
 * Mark notifications as read and clear cache
 *
 * Updates notification read status and clears related caches to ensure
 * immediate reflection of the change. This is a write operation that
 * also handles cache invalidation.
 *
 * @since 1.0.0
 * @param int   $user_id          User ID.
 * @param array $notification_ids Array of notification IDs to mark as read.
 * @return int|false Number of notifications updated, false on error.
 */
function ffm_mark_notifications_read( $user_id, $notification_ids ) {
	$user_id = absint( $user_id );

	if ( ! $user_id || empty( $notification_ids ) ) {
		return false;
	}

	// Sanitize notification IDs
	$notification_ids = array_map( 'absint', (array) $notification_ids );
	$notification_ids = array_filter( $notification_ids );

	if ( empty( $notification_ids ) ) {
		return false;
	}

	global $wpdb;

	// Build placeholders for IN clause
	$placeholders = implode( ',', array_fill( 0, count( $notification_ids ), '%d' ) );

	// Update notifications
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}fanfic_notifications
			SET is_read = 1
			WHERE user_id = %d
			AND id IN ($placeholders)",
			array_merge( array( $user_id ), $notification_ids )
		)
	);

	if ( false !== $updated ) {
		// Clear notification-related caches for this user
		ffm_clear_notification_cache( $user_id );

		return $updated;
	}

	return false;
}

/* ============================================================================
 * CACHE CLEARING FUNCTIONS
 * ========================================================================= */

/**
 * Clear all user-related caches
 *
 * Clears all cached data for a specific user including profile,
 * bookmarks and notifications.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return void
 */
function ffm_clear_user_cache( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return;
	}

	// Clear profile and stats caches
	delete_transient( 'fanfic_user_profile_' . $user_id );
	delete_transient( 'fanfic_user_story_count_' . $user_id );

	// Clear bookmark caches
	ffm_clear_bookmark_cache( $user_id );

	// Clear notification caches
	ffm_clear_notification_cache( $user_id );
}

/**
 * Clear bookmark-related caches for a user
 *
 * Clears all paginated bookmark caches for a user.
 * Also clears site-wide most bookmarked stories cache.
 *
 * @since 1.0.0
 * @param int $user_id  User ID.
 * @param int $story_id Optional. Specific story ID to clear bookmark status for.
 * @return void
 */
function ffm_clear_bookmark_cache( $user_id, $story_id = 0 ) {
	$user_id  = absint( $user_id );
	$story_id = absint( $story_id );

	if ( ! $user_id ) {
		return;
	}

	// Clear bookmark count
	delete_transient( 'fanfic_user_bookmark_count_' . $user_id );

	// Clear paginated bookmark lists for common pagination sizes
	$pagination_sizes = array( 10, 15, 20 );
	foreach ( $pagination_sizes as $per_page ) {
		for ( $page = 1; $page <= 10; $page++ ) {
			delete_transient( 'fanfic_user_bookmarks_' . $user_id . '_' . $page . '_' . $per_page );
		}
	}

	// Clear specific story bookmark status if provided
	if ( $story_id ) {
		delete_transient( 'fanfic_is_bookmarked_' . $user_id . '_' . $story_id );
	}

	// Clear site-wide most bookmarked stories cache
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_fanfic_most_bookmarked_stories_%'
		OR option_name LIKE '_transient_timeout_fanfic_most_bookmarked_stories_%'"
	);
}

/**
 * Clear notification-related caches for a user
 *
 * Clears all paginated notification caches and unread count for a user.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return void
 */
function ffm_clear_notification_cache( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! $user_id ) {
		return;
	}

	// Clear unread count
	delete_transient( 'fanfic_unread_count_' . $user_id );

	// Clear paginated notification lists for common pagination sizes
	$pagination_sizes = array( 10, 15, 20 );
	foreach ( $pagination_sizes as $per_page ) {
		for ( $page = 1; $page <= 10; $page++ ) {
			delete_transient( 'fanfic_user_notifications_' . $user_id . '_' . $page . '_' . $per_page );
		}
	}
}

/**
 * Clear paginated caches helper
 *
 * Helper function to clear all paginated transients matching a pattern.
 * Useful for bulk cache invalidation of list-based caches.
 *
 * @since 1.0.0
 * @param string $prefix Cache key prefix (e.g., 'fanfic_user_bookmarks_123_').
 * @return int Number of transients deleted.
 */
function ffm_clear_paginated_cache( $prefix ) {
	global $wpdb;

	$prefix = sanitize_key( $prefix );

	if ( empty( $prefix ) ) {
		return 0;
	}

	// Build SQL pattern
	$pattern = $wpdb->esc_like( '_transient_' . $prefix ) . '%';

	// Count deleted transients
	$count_sql = $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
		$pattern
	);
	$count = $wpdb->get_var( $count_sql );

	// Delete matching transients
	$delete_sql = $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$pattern
	);
	$wpdb->query( $delete_sql );

	// Also delete timeout entries
	$timeout_pattern = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
	$timeout_sql = $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$timeout_pattern
	);
	$wpdb->query( $timeout_sql );

	return intval( $count );
}

