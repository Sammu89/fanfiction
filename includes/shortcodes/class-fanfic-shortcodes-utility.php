<?php
/**
 * Utility Shortcodes Class
 *
 * Handles utility shortcodes for error messages, maintenance messages, etc.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Utility
 *
 * Utility shortcodes for system messages and status displays.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Utility {

	/**
	 * Register utility shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'edit-story-button', array( __CLASS__, 'edit_story_button' ) );
		add_shortcode( 'fanfic-story-status', array( __CLASS__, 'story_status' ) );
		add_shortcode( 'fanfic-chapter-status', array( __CLASS__, 'chapter_status' ) );
		add_shortcode( 'edit-author-button', array( __CLASS__, 'edit_author_button' ) );
	}

	/**
	 * Edit story button shortcode
	 *
	 * [edit-story-button story_id="123"]
	 *
	 * Displays an edit button for a story if the current user has permission to edit it.
	 * Only shows if user can edit the story. Returns empty string otherwise.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Edit button HTML or empty string.
	 */
	public static function edit_story_button( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'edit-story-button'
		);

		// Get story ID from attribute or current post
		$story_id = absint( $atts['story_id'] );
		error_log( '=== EDIT STORY BUTTON DEBUG ===' );
		error_log( 'story_id from attributes: ' . $story_id );

		if ( empty( $story_id ) ) {
			global $post;
			error_log( 'No story_id in attributes, checking global $post' );
			if ( $post && 'fanfiction_story' === $post->post_type ) {
				$story_id = $post->ID;
				error_log( 'Using global post ID: ' . $story_id );
			} else {
				error_log( 'Global post is not a fanfiction_story or is null' );
			}
		}

		// If no story ID, return empty
		if ( empty( $story_id ) ) {
			error_log( 'No story_id found - button will not be shown' );
			return '';
		}

		// Check if user can edit this story
		error_log( 'Checking current_user_can(edit_fanfiction_story, ' . $story_id . ')' );
		if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			error_log( 'User cannot edit - button will not be shown' );
			return '';
		}
		error_log( 'User CAN edit - generating button' );

		// Get edit story URL
		$edit_url = fanfic_get_edit_story_url( $story_id );
		error_log( 'Edit URL generated: ' . ( $edit_url ? $edit_url : 'EMPTY' ) );
		if ( empty( $edit_url ) ) {
			return '';
		}

		// Return button HTML
		return sprintf(
			'<a href="%s" class="button fanfic-edit-button">%s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit Story', 'fanfiction-manager' )
		);
	}

	/**
	 * Edit author button shortcode
	 *
	 * [edit-author-button user_id="789"]
	 *
	 * Displays an edit profile button if the current user is viewing their own profile
	 * or if they have admin capabilities.
	 * Only shows if current user matches user_id OR has manage_options capability.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Edit button HTML or empty string.
	 */
	public static function edit_author_button( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'user_id' => 0,
			),
			'edit-author-button'
		);

		// Get user ID from attribute or current user
		$user_id = absint( $atts['user_id'] );
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// If no user ID, return empty
		if ( empty( $user_id ) ) {
			return '';
		}

		// Get current user ID
		$current_user_id = get_current_user_id();

		// Check if current user can edit this profile
		// User can edit if: it's their own profile OR they have manage_options capability
		if ( $current_user_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// Get edit profile URL
		$edit_url = fanfic_get_edit_profile_url();
		if ( empty( $edit_url ) ) {
			return '';
		}

		// Return button HTML
		return sprintf(
			'<a href="%s" class="button fanfic-edit-button">%s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit Profile', 'fanfiction-manager' )
		);

	}
	/**
	 * Display status indicator for current story
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes (story_id optional)
	 * @return string Status badge HTML
	 */
	public static function story_status( $atts ) {
		$atts = shortcode_atts( array(
			'story_id' => 0,
		), $atts );

		// Get story
		$story_id = $atts['story_id'] ? absint( $atts['story_id'] ) : get_the_ID();
		$story = get_post( $story_id );
		
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return '';
		}

		$status = $story->post_status;
		$status_class = 'fanfic-status-badge fanfic-status-' . esc_attr( $status );
		
		// Only Draft or Published
		$status_label = ( 'publish' === $status ) 
			? __( 'Published', 'fanfiction-manager' )
			: __( 'Draft', 'fanfiction-manager' );

		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $status_class ),
			esc_html( $status_label )
		);
	}

	/**
	 * Display status indicator for current chapter
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes (chapter_id optional)
	 * @return string Status badge HTML
	 */
	public static function chapter_status( $atts ) {
		$atts = shortcode_atts( array(
			'chapter_id' => 0,
		), $atts );

		// Get chapter
		$chapter_id = $atts['chapter_id'] ? absint( $atts['chapter_id'] ) : get_the_ID();
		$chapter = get_post( $chapter_id );
		
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return '';
		}

		$status = $chapter->post_status;
		$status_class = 'fanfic-status-badge fanfic-status-' . esc_attr( $status );
		
		// Only Draft or Published
		$status_label = ( 'publish' === $status ) 
			? __( 'Published', 'fanfiction-manager' )
			: __( 'Draft', 'fanfiction-manager' );

		return sprintf(
			'<span class="%s">%s</span>',
			esc_attr( $status_class ),
			esc_html( $status_label )
		);
	}
}
