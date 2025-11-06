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
 * Check if current request is in edit mode
 *
 * Checks for ?action=edit or ?edit query parameter.
 *
 * @since 1.0.0
 * @return bool True if in edit mode
 */
function fanfic_is_edit_mode() {
	// Check for ?action=edit
	if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) {
		return true;
	}

	// Check for ?edit (with or without value)
	if ( isset( $_GET['edit'] ) ) {
		return true;
	}

	return false;
}

/**
 * Get edit URL for a story
 *
 * Appends ?action=edit to the story URL.
 *
 * @since 1.0.0
 * @param int|WP_Post $story Story ID or post object
 * @return string|false Edit URL or false on failure
 */
function fanfic_get_story_edit_url( $story ) {
	$story = get_post( $story );

	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return false;
	}

	$story_url = get_permalink( $story->ID );
	return add_query_arg( 'action', 'edit', $story_url );
}

/**
 * Get edit URL for a chapter
 *
 * Appends ?action=edit to the chapter URL.
 *
 * @since 1.0.0
 * @param int|WP_Post $chapter Chapter ID or post object
 * @return string|false Edit URL or false on failure
 */
function fanfic_get_chapter_edit_url( $chapter ) {
	$chapter = get_post( $chapter );

	if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
		return false;
	}

	$chapter_url = get_permalink( $chapter->ID );
	return add_query_arg( 'action', 'edit', $chapter_url );
}

/**
 * Get edit URL for a user profile
 *
 * Appends ?action=edit to the profile URL.
 *
 * @since 1.0.0
 * @param int $user_id User ID (defaults to current user)
 * @return string Edit profile URL
 */
function fanfic_get_profile_edit_url( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	// Get base slug and user slug
	$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
	$secondary_paths = get_option( 'fanfic_secondary_paths', array() );
	$user_slug = isset( $secondary_paths['user'] ) ? $secondary_paths['user'] : 'user';

	// Build profile URL: /base_slug/user_slug/username
	$profile_url = home_url( '/' . $base_slug . '/' . $user_slug . '/' . $user->user_login . '/' );

	return add_query_arg( 'action', 'edit', $profile_url );
}

/**
 * Check if current user can edit the given content
 *
 * Verifies permissions for editing stories, chapters, or profiles.
 *
 * @since 1.0.0
 * @param string $content_type Type of content: 'story', 'chapter', or 'profile'
 * @param int    $content_id   ID of the content to edit
 * @return bool True if user can edit
 */
function fanfic_current_user_can_edit( $content_type, $content_id ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	// Administrators and moderators can edit anything
	if ( current_user_can( 'manage_options' ) || current_user_can( 'moderate_fanfiction' ) ) {
		return true;
	}

	switch ( $content_type ) {
		case 'story':
		case 'chapter':
			$post = get_post( $content_id );
			if ( ! $post ) {
				return false;
			}
			// Authors can edit their own content
			return absint( $post->post_author ) === $user_id;

		case 'profile':
			// Users can edit their own profile
			return absint( $content_id ) === $user_id;

		default:
			return false;
	}
}
