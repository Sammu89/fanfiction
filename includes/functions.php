<?php
/**
 * Helper Functions
 *
 * Global helper functions for the fanfiction manager plugin.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin version
 *
 * @return string Plugin version
 */
function fanfic_get_version() {
	return FANFIC_VERSION;
}

/**
 * Check if user is a fanfiction author
 *
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user is a fanfiction author
 */
function fanfic_is_author( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user = get_userdata( $user_id );
	return $user && in_array( 'fanfiction_author', (array) $user->roles, true );
}

/**
 * Check if user is a fanfiction moderator
 *
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user is a fanfiction moderator
 */
function fanfic_is_moderator( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user = get_userdata( $user_id );
	return $user && in_array( 'fanfiction_moderator', (array) $user->roles, true );
}

/**
 * Get custom table name with proper prefix
 *
 * @param string $table_name Table name without prefix
 * @return string Full table name with prefix
 */
function fanfic_get_table_name( $table_name ) {
	global $wpdb;
	return $wpdb->prefix . 'fanfic_' . $table_name;
}

/**
 * Sanitize story content (allow basic HTML)
 *
 * @param string $content Content to sanitize
 * @return string Sanitized content
 */
function fanfic_sanitize_content( $content ) {
	$allowed_html = array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
	);

	return wp_kses( $content, $allowed_html );
}

/**
 * Get URL for a system page by its key
 * Uses stored page IDs to always return current permalink
 *
 * @param string $page_key The page key (e.g., 'dashboard', 'login', etc.)
 * @return string The page URL or empty string if not found
 */
function fanfic_get_page_url( $page_key ) {
    $page_ids = get_option( 'fanfic_system_page_ids', array() );

    if ( empty( $page_ids[ $page_key ] ) ) {
        return '';
    }

    $url = get_permalink( $page_ids[ $page_key ] );
    return $url ? $url : '';
}

/**
 * Get dashboard URL
 */
function fanfic_get_dashboard_url() {
    return fanfic_get_page_url( 'dashboard' );
}

/**
 * Get create story URL
 */
function fanfic_get_create_story_url() {
    return fanfic_get_page_url( 'create-story' );
}

/**
 * Get edit story URL
 *
 * @param int $story_id Optional story ID to append
 */
function fanfic_get_edit_story_url( $story_id = 0 ) {
    $url = fanfic_get_page_url( 'edit-story' );

    if ( $story_id > 0 && $url ) {
        $url = add_query_arg( 'story_id', $story_id, $url );
    }

    return $url;
}

/**
 * Get edit chapter URL
 *
 * @param int $chapter_id Optional chapter ID to append
 */
function fanfic_get_edit_chapter_url( $chapter_id = 0 ) {
    $url = fanfic_get_page_url( 'edit-chapter' );

    if ( $chapter_id > 0 && $url ) {
        $url = add_query_arg( 'chapter_id', $chapter_id, $url );
    }

    return $url;
}

/**
 * Get edit profile URL
 */
function fanfic_get_edit_profile_url() {
    return fanfic_get_page_url( 'edit-profile' );
}

/**
 * Get login URL
 */
function fanfic_get_login_url() {
    return fanfic_get_page_url( 'login' );
}

/**
 * Get register URL
 */
function fanfic_get_register_url() {
    return fanfic_get_page_url( 'register' );
}

/**
 * Get password reset URL
 */
function fanfic_get_password_reset_url() {
    return fanfic_get_page_url( 'password-reset' );
}

/**
 * Get search URL
 */
function fanfic_get_search_url() {
    return fanfic_get_page_url( 'search' );
}

/**
 * Get error page URL
 */
function fanfic_get_error_url() {
    return fanfic_get_page_url( 'error' );
}

/**
 * Get main/archive URL
 */
function fanfic_get_main_url() {
    return fanfic_get_page_url( 'main' );
}

/**
 * Get user profile URL
 *
 * @param mixed $user User ID, username, or WP_User object
 * @return string User profile URL
 */
function fanfic_get_user_profile_url( $user ) {
    $members_url = fanfic_get_page_url( 'members' );

    if ( ! $members_url ) {
        return '';
    }

    // Get username
    if ( is_numeric( $user ) ) {
        $user_obj = get_user_by( 'id', $user );
        $username = $user_obj ? $user_obj->user_login : '';
    } elseif ( is_object( $user ) && isset( $user->user_login ) ) {
        $username = $user->user_login;
    } else {
        $username = $user;
    }

    if ( empty( $username ) ) {
        return $members_url;
    }

    return add_query_arg( 'member', $username, $members_url );
}

/**
 * Get stories archive URL (CPT archive)
 *
 * @return string Stories archive URL
 */
function fanfic_get_stories_archive_url() {
    $base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
    $story_path = get_option( 'fanfic_story_path', 'stories' );

    return home_url( '/' . $base_slug . '/' . $story_path . '/' );
}
