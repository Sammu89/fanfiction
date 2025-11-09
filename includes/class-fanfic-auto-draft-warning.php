<?php
/**
 * Auto-Draft Warning Handler
 *
 * Handles displaying warnings to users when their story is automatically drafted
 * due to having no visible chapters. Warning is shown only on chapter form pages
 * after unpublish/delete actions.
 *
 * @package FanfictionManager
 * @since 1.0.8
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Auto_Draft_Warning
 *
 * Manages warnings when stories are auto-drafted via chapter actions.
 *
 * @since 1.0.8
 */
class Fanfic_Auto_Draft_Warning {

	/**
	 * Initialize the warning handler
	 *
	 * @since 1.0.8
	 */
	public static function init() {
		// Register AJAX handler for chapter unpublish
		add_action( 'wp_ajax_fanfic_unpublish_chapter', array( __CLASS__, 'ajax_unpublish_chapter' ) );
	}

	/**
	 * AJAX handler for unpublishing a chapter
	 *
	 * @since 1.0.8
	 */
	public static function ajax_unpublish_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_unpublish_chapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to unpublish chapters.', 'fanfiction-manager' ) ) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter ID.', 'fanfiction-manager' ) ) );
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );

		// Check if chapter exists
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Chapter not found.', 'fanfiction-manager' ) ) );
		}

		// Get the story to check permissions
		$story = get_post( $chapter->post_parent );
		if ( ! $story ) {
			wp_send_json_error( array( 'message' => __( 'Parent story not found.', 'fanfiction-manager' ) ) );
		}

		// Check permissions - must be author or have edit_others_posts capability
		if ( $story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to unpublish this chapter.', 'fanfiction-manager' ) ) );
		}

		// Check if chapter is published
		if ( 'publish' !== $chapter->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Only published chapters can be unpublished.', 'fanfiction-manager' ) ) );
		}

		// Update chapter status to draft
		$result = wp_update_post( array(
			'ID'          => $chapter_id,
			'post_status' => 'draft',
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to unpublish chapter.', 'fanfiction-manager' ) ) );
		}

		// Check if story was auto-drafted
		$story_was_auto_drafted = false;
		if ( 'draft' === $story->post_status ) {
			// Story is already draft, no change
			$story_was_auto_drafted = false;
		} else {
			// Check if story still has published chapters
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story->ID,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );

			if ( empty( $published_chapters ) ) {
				// No published chapters left, story will be auto-drafted
				$story_was_auto_drafted = true;
			}
		}

		wp_send_json_success( array(
			'message'               => __( 'Chapter unpublished.', 'fanfiction-manager' ),
			'story_auto_drafted'    => $story_was_auto_drafted,
			'story_id'              => $story->ID,
			'story_title'           => $story->post_title,
			'chapter_title'         => $chapter->post_title,
		) );
	}

}
