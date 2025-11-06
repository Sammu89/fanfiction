<?php
/**
 * URL Helper Functions
 *
 * Thin wrapper functions around Fanfic_URL_Manager for easy access from templates.
 * All logic is centralized in class-fanfic-url-manager.php.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get URL for a system or dynamic page by key
 *
 * @param string $page_key The page key (e.g., 'dashboard', 'login', 'create-story').
 * @param array  $args Optional. Query parameters to add to the URL.
 * @return string The page URL, or empty string if page not found.
 */
function fanfic_get_page_url( $page_key, $args = array() ) {
	return Fanfic_URL_Manager::get_instance()->get_page_url( $page_key, $args );
}

/**
 * Get URL for the story archive (all stories)
 *
 * @return string The story archive URL.
 */
function fanfic_get_story_archive_url() {
	return get_post_type_archive_link( 'fanfiction_story' );
}

/**
 * Get URL for a single story
 *
 * @param int $story_id The story post ID.
 * @return string The story URL, or empty string if invalid.
 */
function fanfic_get_story_url( $story_id ) {
	return Fanfic_URL_Manager::get_instance()->get_story_url( $story_id );
}

/**
 * Get URL for a single chapter
 *
 * @param int $chapter_id The chapter post ID.
 * @return string The chapter URL, or empty string if invalid.
 */
function fanfic_get_chapter_url( $chapter_id ) {
	return Fanfic_URL_Manager::get_instance()->get_chapter_url( $chapter_id );
}

/**
 * Get URL for a taxonomy archive
 *
 * @param string         $taxonomy The taxonomy name (e.g., 'fanfiction_genre').
 * @param string|int|object $term The term slug, ID, or object.
 * @return string The taxonomy archive URL, or empty string if invalid.
 */
function fanfic_get_taxonomy_url( $taxonomy, $term ) {
	$term_link = get_term_link( $term, $taxonomy );
	return is_wp_error( $term_link ) ? '' : $term_link;
}

/**
 * Get URL for an author's story archive
 *
 * @param int $author_id The author user ID.
 * @return string The author archive URL.
 */
function fanfic_get_author_url( $author_id ) {
	if ( ! $author_id ) {
		return '';
	}

	return add_query_arg(
		array(
			'post_type' => 'fanfiction_story',
			'author'    => $author_id,
		),
		home_url( '/' )
	);
}

/**
 * Get user profile URL
 *
 * @param mixed $user User ID, username, or WP_User object.
 * @return string User profile URL.
 */
function fanfic_get_user_profile_url( $user ) {
	if ( empty( $user ) ) {
		return fanfic_get_page_url( 'members' );
	}

	return Fanfic_URL_Manager::get_instance()->get_user_profile_url( $user );
}

// ============================================================================
// CONVENIENCE FUNCTIONS FOR SPECIFIC PAGES
// ============================================================================

/**
 * Get URL for the main/home page
 *
 * @return string The main page URL.
 */
function fanfic_get_main_url() {
	return fanfic_get_page_url( 'main' );
}

/**
 * Get URL for the dashboard page
 *
 * @return string The dashboard URL.
 */
function fanfic_get_dashboard_url() {
	return fanfic_get_page_url( 'dashboard' );
}

/**
 * Get URL for the login page
 *
 * @return string The login page URL.
 */
function fanfic_get_login_url() {
	return fanfic_get_page_url( 'login' );
}

/**
 * Get URL for the register page
 *
 * @return string The register page URL.
 */
function fanfic_get_register_url() {
	return fanfic_get_page_url( 'register' );
}

/**
 * Get URL for the password reset page
 *
 * @return string The password reset page URL.
 */
function fanfic_get_password_reset_url() {
	return fanfic_get_page_url( 'password-reset' );
}

/**
 * Get URL for the create story page
 *
 * @return string The create story page URL.
 */
function fanfic_get_create_story_url() {
	return fanfic_get_page_url( 'create-story' );
}

/**
 * Get URL for the search page
 *
 * @return string The search page URL.
 */
function fanfic_get_search_url() {
	return fanfic_get_page_url( 'search' );
}

/**
 * Get URL for the members page
 *
 * @return string The members page URL.
 */
function fanfic_get_members_url() {
	return fanfic_get_page_url( 'members' );
}

/**
 * Get URL for the error page
 *
 * @return string The error page URL.
 */
function fanfic_get_error_url() {
	return fanfic_get_page_url( 'error' );
}

/**
 * Get URL for the maintenance page
 *
 * @return string The maintenance page URL.
 */
function fanfic_get_maintenance_url() {
	return fanfic_get_page_url( 'maintenance' );
}

/**
 * Get URL for the edit profile page
 *
 * @return string The edit profile page URL.
 */
function fanfic_get_edit_profile_url() {
	return fanfic_get_page_url( 'edit-profile' );
}

// ============================================================================
// EDIT URL FUNCTIONS
// ============================================================================

/**
 * Get edit story URL
 *
 * @param int $story_id The story ID to edit.
 * @return string The edit story URL with ?action=edit.
 */
function fanfic_get_edit_story_url( $story_id = 0 ) {
	if ( empty( $story_id ) ) {
		return fanfic_get_page_url( 'edit-story' );
	}

	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'story', $story_id );
}

/**
 * Get edit chapter URL
 *
 * @param int    $chapter_id     The chapter ID to edit.
 * @param int    $story_id       Optional. The story ID (for backwards compatibility).
 * @param int    $chapter_number Optional. Chapter number (for backwards compatibility).
 * @param string $chapter_type   Optional. Chapter type (for backwards compatibility).
 * @return string The edit chapter URL with ?action=edit.
 */
function fanfic_get_edit_chapter_url( $chapter_id = 0, $story_id = 0, $chapter_number = 1, $chapter_type = '' ) {
	if ( empty( $chapter_id ) ) {
		return fanfic_get_page_url( 'edit-chapter' );
	}

	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'chapter', $chapter_id );
}

/**
 * Get edit profile URL for a user
 *
 * @param int $user_id Optional. The user ID. Defaults to current user.
 * @return string The edit profile URL with ?action=edit.
 */
function fanfic_get_profile_edit_url( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return '';
	}

	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'profile', $user_id );
}

// ============================================================================
// BREADCRUMB & NAVIGATION FUNCTIONS
// ============================================================================

/**
 * Get breadcrumb parent URL
 *
 * Returns the appropriate parent URL based on context.
 *
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
