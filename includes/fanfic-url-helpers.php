<?php
/**
 * URL Helper Functions
 *
 * Centralized functions for generating all plugin URLs.
 * These functions respect user's URL configuration and never break when slugs change.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get URL for a system page by key
 *
 * Retrieves the permalink for plugin-created pages like dashboard, login, etc.
 * Always returns the correct URL even if user changes page slugs.
 *
 * @since 1.0.0
 * @param string $page_key The page key (e.g., 'dashboard', 'login', 'create-story').
 * @return string The page URL, or empty string if page not found.
 */
function fanfic_get_page_url( $page_key ) {
	$page_ids = get_option( 'fanfic_system_page_ids', array() );

	if ( isset( $page_ids[ $page_key ] ) && $page_ids[ $page_key ] > 0 ) {
		$url = get_permalink( $page_ids[ $page_key ] );
		return $url ? $url : '';
	}

	return '';
}

/**
 * Get URL for the story archive (all stories)
 *
 * Returns the post type archive URL which respects user's URL configuration.
 *
 * @since 1.0.0
 * @return string The story archive URL.
 */
function fanfic_get_story_archive_url() {
	return get_post_type_archive_link( 'fanfiction_story' );
}

/**
 * Get URL for a single story
 *
 * @since 1.0.0
 * @param int $story_id The story post ID.
 * @return string The story URL, or empty string if invalid.
 */
function fanfic_get_story_url( $story_id ) {
	if ( ! $story_id || get_post_type( $story_id ) !== 'fanfiction_story' ) {
		return '';
	}

	$url = get_permalink( $story_id );
	return $url ? $url : '';
}

/**
 * Get URL for a single chapter
 *
 * @since 1.0.0
 * @param int $chapter_id The chapter post ID.
 * @return string The chapter URL, or empty string if invalid.
 */
function fanfic_get_chapter_url( $chapter_id ) {
	if ( ! $chapter_id || get_post_type( $chapter_id ) !== 'fanfiction_chapter' ) {
		return '';
	}

	$url = get_permalink( $chapter_id );
	return $url ? $url : '';
}

/**
 * Get URL for a taxonomy archive
 *
 * @since 1.0.0
 * @param string $taxonomy The taxonomy name (e.g., 'fanfiction_genre').
 * @param string|int|object $term The term slug, ID, or object.
 * @return string The taxonomy archive URL, or empty string if invalid.
 */
function fanfic_get_taxonomy_url( $taxonomy, $term ) {
	$term_link = get_term_link( $term, $taxonomy );

	if ( is_wp_error( $term_link ) ) {
		return '';
	}

	return $term_link;
}

/**
 * Get URL for an author's story archive
 *
 * @since 1.0.0
 * @param int $author_id The author user ID.
 * @return string The author archive URL.
 */
function fanfic_get_author_url( $author_id ) {
	if ( ! $author_id ) {
		return '';
	}

	// Get author archive URL for fanfiction_story post type
	return add_query_arg(
		array(
			'post_type' => 'fanfiction_story',
			'author'    => $author_id,
		),
		home_url( '/' )
	);
}

/**
 * Get URL for the main/home page
 *
 * @since 1.0.0
 * @return string The main page URL.
 */
function fanfic_get_main_url() {
	return fanfic_get_page_url( 'main' );
}

/**
 * Get URL for the dashboard page
 *
 * @since 1.0.0
 * @return string The dashboard URL.
 */
function fanfic_get_dashboard_url() {
	return fanfic_get_page_url( 'dashboard' );
}

/**
 * Get URL for the login page
 *
 * @since 1.0.0
 * @return string The login page URL.
 */
function fanfic_get_login_url() {
	return fanfic_get_page_url( 'login' );
}

/**
 * Get URL for the register page
 *
 * @since 1.0.0
 * @return string The register page URL.
 */
function fanfic_get_register_url() {
	return fanfic_get_page_url( 'register' );
}

/**
 * Get URL for the password reset page
 *
 * @since 1.0.0
 * @return string The password reset page URL.
 */
function fanfic_get_password_reset_url() {
	return fanfic_get_page_url( 'password-reset' );
}

/**
 * Get URL for the create story page
 *
 * @since 1.0.0
 * @return string The create story page URL.
 */
function fanfic_get_create_story_url() {
	return fanfic_get_page_url( 'create-story' );
}

/**
 * Get URL for the edit story page
 *
 * @since 1.0.0
 * @param int $story_id Optional. The story ID to edit.
 * @return string The edit story page URL.
 */
function fanfic_get_edit_story_url( $story_id = 0 ) {
	$url = fanfic_get_page_url( 'edit-story' );

	if ( $story_id > 0 && $url ) {
		$url = add_query_arg( 'story_id', $story_id, $url );
	}

	return $url;
}

/**
 * Get URL for the edit chapter page
 *
 * @since 1.0.0
 * @param int $chapter_id Optional. The chapter ID to edit.
 * @return string The edit chapter page URL.
 */
function fanfic_get_edit_chapter_url( $chapter_id = 0 ) {
	$url = fanfic_get_page_url( 'edit-chapter' );

	if ( $chapter_id > 0 && $url ) {
		$url = add_query_arg( 'chapter_id', $chapter_id, $url );
	}

	return $url;
}

/**
 * Get URL for the edit profile page
 *
 * @since 1.0.0
 * @return string The edit profile page URL.
 */
function fanfic_get_edit_profile_url() {
	return fanfic_get_page_url( 'edit-profile' );
}

/**
 * Get URL for the search page
 *
 * @since 1.0.0
 * @return string The search page URL.
 */
function fanfic_get_search_url() {
	return fanfic_get_page_url( 'search' );
}

/**
 * Get URL for the members page
 *
 * @since 1.0.0
 * @return string The members page URL.
 */
function fanfic_get_members_url() {
	return fanfic_get_page_url( 'members' );
}

/**
 * Get URL for the error page
 *
 * @since 1.0.0
 * @return string The error page URL.
 */
function fanfic_get_error_url() {
	return fanfic_get_page_url( 'error' );
}

/**
 * Get URL for the maintenance page
 *
 * @since 1.0.0
 * @return string The maintenance page URL.
 */
function fanfic_get_maintenance_url() {
	return fanfic_get_page_url( 'maintenance' );
}

/**
 * Get user profile URL
 *
 * @since 1.0.0
 * @param mixed $user User ID, username, or WP_User object.
 * @return string User profile URL.
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
 * Get breadcrumb parent URL
 *
 * Returns the appropriate parent URL based on context.
 * For stories/chapters, returns the archive. For pages, returns the main page.
 *
 * @since 1.0.0
 * @param int $post_id Optional. The post ID to get parent for.
 * @return string The parent URL.
 */
function fanfic_get_parent_url( $post_id = 0 ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return fanfic_get_main_url();
	}

	$post_type = get_post_type( $post_id );

	switch ( $post_type ) {
		case 'fanfiction_story':
			return fanfic_get_story_archive_url();

		case 'fanfiction_chapter':
			$story_id = wp_get_post_parent_id( $post_id );
			return $story_id ? fanfic_get_story_url( $story_id ) : fanfic_get_story_archive_url();

		case 'page':
			$parent_id = wp_get_post_parent_id( $post_id );
			return $parent_id ? get_permalink( $parent_id ) : fanfic_get_main_url();

		default:
			return fanfic_get_main_url();
	}
}
