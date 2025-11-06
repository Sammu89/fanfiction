<?php
/**
 * Fanfiction Manager - Dynamic Pages System
 *
 * Handles dynamic page templates that don't require WordPress pages in the database.
 * These pages are generated via rewrite rules and PHP templates, preventing users from
 * accidentally breaking functionality by editing or deleting pages.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Dynamic_Pages
 *
 * @deprecated 1.1.0 This class is deprecated. All functionality moved to Fanfic_URL_Manager.
 * File kept for backward compatibility only. Will be removed in version 2.0.0.
 *
 * Manages dynamic pages that use rewrite rules and templates instead of WordPress pages.
 * This is used for action pages (edit story, edit chapter) and functional pages (dashboard,
 * search, etc.) that don't need customizable content.
 *
 * @since 1.0.0
 */
class Fanfic_Dynamic_Pages {

	/**
	 * Option name for storing dynamic page slugs
	 *
	 * @var string
	 */
	const OPTION_DYNAMIC_SLUGS = 'fanfic_dynamic_page_slugs';

	/**
	 * Initialize the dynamic pages system
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 20 );
		add_action( 'init', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 25 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_loader' ), 99 );
	}

	/**
	 * Maybe flush rewrite rules if flag is set
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( get_transient( 'fanfic_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'fanfic_flush_rewrite_rules' );
		}
	}

	/**
	 * Get list of dynamic pages
	 *
	 * Returns array of pages that should be dynamic (not WordPress pages)
	 *
	 * @since 1.0.0
	 * @return array Array of dynamic page keys
	 */
	public static function get_dynamic_pages() {
		return array(
			'dashboard',     // User dashboard - dynamic data
			'create-story',  // Create story form - pure form
			'search',        // Search results - dynamic results
			'members',       // Members/profiles - dynamic listings
		);
		// Note: Edit pages removed - now using query parameters (?action=edit) instead
		// Old: 'edit-story', 'edit-chapter', 'edit-profile'
	}

	/**
	 * Get default slugs for dynamic pages
	 *
	 * @since 1.0.0
	 * @return array Default slug values
	 */
	public static function get_default_slugs() {
		return array(
			'dashboard'     => 'dashboard',
			'create-story'  => 'create-story',
			'search'        => 'search',
			'members'       => 'members',
		);
		// Note: Edit page defaults removed - now using query parameters (?action=edit) instead
		// Old: 'edit-story' => 'edit-story', 'edit-chapter' => 'edit-chapter', 'edit-profile' => 'edit-profile'
	}

	/**
	 * Get current dynamic page slugs
	 *
	 * @since 1.0.0
	 * @return array Current slug values
	 */
	public static function get_slugs() {
		$defaults = self::get_default_slugs();
		$saved    = get_option( self::OPTION_DYNAMIC_SLUGS, array() );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Update dynamic page slugs
	 *
	 * @since 1.0.0
	 * @param array $slugs Array of slug values.
	 * @return bool Whether update was successful
	 */
	public static function update_slugs( $slugs ) {
		return update_option( self::OPTION_DYNAMIC_SLUGS, $slugs );
	}

	/**
	 * Add rewrite rules for dynamic pages
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_rewrite_rules() {
		$base  = get_option( 'fanfic_base_slug', 'fanfiction' );
		$slugs = self::get_slugs();

		// Dashboard: /fanfiction/dashboard/
		add_rewrite_rule(
			'^' . $base . '/' . $slugs['dashboard'] . '/?$',
			'index.php?fanfic_page=dashboard',
			'top'
		);

		// Create Story: /fanfiction/create-story/
		add_rewrite_rule(
			'^' . $base . '/' . $slugs['create-story'] . '/?$',
			'index.php?fanfic_page=create-story',
			'top'
		);

		// REMOVED: Edit Profile now uses query parameters (?action=edit)
		// Old: /fanfiction/edit-profile/ → Now: /fanfiction/user/username/?action=edit

		// Search: /fanfiction/search/
		// Note: This also handles query params like ?s=keyword&fandom=123
		add_rewrite_rule(
			'^' . $base . '/' . $slugs['search'] . '/?$',
			'index.php?fanfic_page=search',
			'top'
		);

		// Members: /fanfiction/members/ or /fanfiction/members/username/
		add_rewrite_rule(
			'^' . $base . '/' . $slugs['members'] . '/([^/]+)/?$',
			'index.php?fanfic_page=members&member_name=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/' . $slugs['members'] . '/?$',
			'index.php?fanfic_page=members',
			'top'
		);

		// REMOVED: Edit Story and Edit Chapter now use query parameters (?action=edit)
		// Old approach used separate edit URLs:
		//   - Edit Story: /fanfiction/stories/story-slug/edit/
		//   - Edit Chapter: /fanfiction/stories/story-slug/chapter-1/edit/
		//   - Edit Prologue: /fanfiction/stories/story-slug/prologue/edit/
		//   - Edit Epilogue: /fanfiction/stories/story-slug/epilogue/edit/
		//
		// New approach uses query parameters on the same URL:
		//   - Edit Story: /fanfiction/stories/story-slug/?action=edit
		//   - Edit Chapter: /fanfiction/stories/story-slug/chapter-1/?action=edit
		//   - Edit Prologue: /fanfiction/stories/story-slug/prologue/?action=edit
		//   - Edit Epilogue: /fanfiction/stories/story-slug/epilogue/?action=edit
		//
		// This is simpler and uses the helper functions:
		//   - fanfic_get_story_edit_url($story_id)
		//   - fanfic_get_chapter_edit_url($chapter_id)
	}

	/**
	 * Register custom query vars
	 *
	 * @since 1.0.0
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'fanfic_page';
		$vars[] = 'story_action';
		$vars[] = 'chapter_action';
		$vars[] = 'chapter_number';
		$vars[] = 'chapter_type';
		$vars[] = 'member_name';
		return $vars;
	}

	/**
	 * Add query vars (legacy support)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_query_vars() {
		// This method exists for compatibility but the actual registration
		// is done via the register_query_vars filter
	}

	/**
	 * Load appropriate template for dynamic pages
	 *
	 * @since 1.0.0
	 * @param string $template Default template path.
	 * @return string Modified template path
	 */
	public static function template_loader( $template ) {
		$fanfic_page    = get_query_var( 'fanfic_page' );
		$story_action   = get_query_var( 'story_action' );
		$chapter_action = get_query_var( 'chapter_action' );

		// Dashboard page
		if ( 'dashboard' === $fanfic_page ) {
			$new_template = self::locate_template( 'template-dashboard.php' );
			if ( $new_template ) {
				return $new_template;
			}
		}

		// Create Story page
		if ( 'create-story' === $fanfic_page ) {
			$new_template = self::locate_template( 'template-create-story.php' );
			if ( $new_template ) {
				return $new_template;
			}
		}

		// REMOVED: Edit Profile now handled via query parameter on profile page
		// Templates should check fanfic_is_edit_mode() instead
		// See functions.php for helper functions

		// Search page
		if ( 'search' === $fanfic_page ) {
			$new_template = self::locate_template( 'template-search.php' );
			if ( $new_template ) {
				return $new_template;
			}
		}

		// Members page
		if ( 'members' === $fanfic_page ) {
			$new_template = self::locate_template( 'template-members.php' );
			if ( $new_template ) {
				return $new_template;
			}
		}

		// REMOVED: Edit Story and Edit Chapter now handled via query parameter
		// Single-story and single-chapter templates should check fanfic_is_edit_mode() instead
		// Use helper functions:
		//   - fanfic_get_story_edit_url($story_id) to generate edit URLs
		//   - fanfic_get_chapter_edit_url($chapter_id) to generate edit URLs
		//   - fanfic_is_edit_mode() to detect edit mode in templates
		//   - fanfic_current_user_can_edit($type, $id) to check permissions

		return $template;
	}

	/**
	 * Locate template file
	 *
	 * Checks theme directory first, then plugin directory
	 *
	 * @since 1.0.0
	 * @param string $template_name Template file name.
	 * @return string|false Template path or false if not found
	 */
	public static function locate_template( $template_name ) {
		// Check if theme has override
		$theme_template = locate_template( array(
			'fanfiction-manager/' . $template_name,
			$template_name,
		) );

		if ( $theme_template ) {
			return $theme_template;
		}

		// Use plugin template
		$plugin_template = FANFIC_PLUGIN_DIR . 'templates/' . $template_name;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Get URL for a dynamic page
	 *
	 * @since 1.0.0
	 * @param string $page_key Dynamic page key (dashboard, edit-profile, etc.).
	 * @param array  $args Additional URL parameters.
	 * @return string Page URL
	 */
	public static function get_page_url( $page_key, $args = array() ) {
		$base  = get_option( 'fanfic_base_slug', 'fanfiction' );
		$slugs = self::get_slugs();

		if ( ! isset( $slugs[ $page_key ] ) ) {
			return '';
		}

		// Special handling for members page with member_name
		if ( 'members' === $page_key && isset( $args['member_name'] ) ) {
			$member_name = $args['member_name'];
			unset( $args['member_name'] ); // Remove from args so it's not added as query param
			$url = home_url( '/' . $base . '/' . $slugs[ $page_key ] . '/' . $member_name . '/' );
		} else {
			$url = home_url( '/' . $base . '/' . $slugs[ $page_key ] . '/' );
		}

		// Add remaining query parameters if provided
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	/**
	 * Get edit story URL for a story
	 *
	 * @deprecated Use fanfic_get_story_edit_url() instead (see functions.php)
	 * @since 1.0.0
	 * @param int|WP_Post $story Story ID or post object.
	 * @return string Edit story URL
	 */
	public static function get_edit_story_url( $story ) {
		// Redirect to new helper function that uses query parameters
		return function_exists( 'fanfic_get_story_edit_url' )
			? fanfic_get_story_edit_url( $story )
			: '';
	}

	/**
	 * Get edit chapter URL for a chapter
	 *
	 * @deprecated Use fanfic_get_chapter_edit_url() instead (see functions.php)
	 * @since 1.0.0
	 * @param int|WP_Post $story Story ID or post object (now treated as chapter).
	 * @param int         $chapter_number Chapter number (IGNORED - kept for backwards compatibility).
	 * @param string      $chapter_type Optional chapter type (IGNORED - kept for backwards compatibility).
	 * @return string Edit chapter URL
	 */
	public static function get_edit_chapter_url( $story, $chapter_number = 1, $chapter_type = '' ) {
		// Note: $story parameter is actually expected to be a chapter post object or ID
		// Redirect to new helper function that uses query parameters
		return function_exists( 'fanfic_get_chapter_edit_url' )
			? fanfic_get_chapter_edit_url( $story )
			: '';
	}

	/**
	 * Check if a page should be dynamic
	 *
	 * @since 1.0.0
	 * @param string $page_key Page key to check.
	 * @return bool Whether page should be dynamic
	 */
	public static function is_dynamic_page( $page_key ) {
		return in_array( $page_key, self::get_dynamic_pages(), true );
	}
}
