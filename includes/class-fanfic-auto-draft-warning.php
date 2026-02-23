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
		// Register AJAX handlers for chapter status changes
		add_action( 'wp_ajax_fanfic_unpublish_chapter', array( __CLASS__, 'ajax_unpublish_chapter' ) );
		add_action( 'wp_ajax_fanfic_publish_chapter', array( __CLASS__, 'ajax_publish_chapter' ) );
		add_action( 'wp_ajax_fanfic_update_chapter', array( __CLASS__, 'ajax_update_chapter' ) );
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

		// Check if story will auto-draft BEFORE unpublishing
		$story_will_auto_draft = Fanfic_Validation::will_story_auto_draft_if_chapter_removed( $chapter_id );

		// Update chapter status to draft
		$result = wp_update_post( array(
			'ID'          => $chapter_id,
			'post_status' => 'draft',
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to unpublish chapter.', 'fanfiction-manager' ) ) );
		}

		wp_send_json_success( array(
			'message'               => __( 'Chapter unpublished.', 'fanfiction-manager' ),
			'story_auto_drafted'    => $story_will_auto_draft,
			'story_id'              => $story->ID,
			'story_title'           => $story->post_title,
			'chapter_title'         => $chapter->post_title,
		) );
	}

	/**
	 * AJAX handler for publishing a draft chapter
	 *
	 * @since 1.0.8
	 */
	public static function ajax_publish_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_publish_chapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to publish chapters.', 'fanfiction-manager' ) ) );
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
			wp_send_json_error( array( 'message' => __( 'You do not have permission to publish this chapter.', 'fanfiction-manager' ) ) );
		}

		// Check if chapter is draft
		if ( 'draft' !== $chapter->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Only draft chapters can be published.', 'fanfiction-manager' ) ) );
		}

		// Validate chapter using validation helper
		$validation_result = Fanfic_Validation::can_publish_chapter( $chapter_id );

		if ( ! $validation_result['can_publish'] ) {
			wp_send_json_error( array(
				'message'         => __( 'Cannot publish chapter. Missing required fields:', 'fanfiction-manager' ),
				'missing_fields'  => $validation_result['missing_fields'], // Array of field => message pairs
				'errors'          => array_values( $validation_result['missing_fields'] ), // Just the messages
			) );
		}

		// Update chapter status to publish
		$result = wp_update_post( array(
			'ID'          => $chapter_id,
			'post_status' => 'publish',
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to publish chapter.', 'fanfiction-manager' ) ) );
		}

		// Check if story just became publishable
		$story_became_publishable = Fanfic_Validation::did_story_become_publishable( $story->ID );

		wp_send_json_success( array(
			'message'                  => __( 'Chapter published successfully!', 'fanfiction-manager' ),
			'chapter_url'              => get_permalink( $chapter_id ),
			'chapter_id'               => $chapter_id,
			'story_became_publishable' => $story_became_publishable,
			'story_id'                 => $story->ID,
		) );
	}

	/**
	 * AJAX handler for updating a chapter
	 *
	 * @since 1.0.8
	 */
	public static function ajax_update_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_update_chapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to update chapters.', 'fanfiction-manager' ) ) );
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
			wp_send_json_error( array( 'message' => __( 'You do not have permission to update this chapter.', 'fanfiction-manager' ) ) );
		}

		// Get form data
		$chapter_title = isset( $_POST['chapter_title'] ) ? sanitize_text_field( $_POST['chapter_title'] ) : '';
		$chapter_content = isset( $_POST['chapter_content'] ) ? wp_kses_post( $_POST['chapter_content'] ) : '';
		$chapter_type = isset( $_POST['chapter_type'] ) ? sanitize_text_field( $_POST['chapter_type'] ) : 'chapter';
		$chapter_number = isset( $_POST['chapter_number'] ) ? absint( $_POST['chapter_number'] ) : 0;
		$notes_enabled = isset( $_POST['fanfic_author_notes_enabled'] ) ? '1' : '0';
		$notes_position = ( isset( $_POST['fanfic_author_notes_position'] ) && 'above' === sanitize_text_field( wp_unslash( $_POST['fanfic_author_notes_position'] ) ) ) ? 'above' : 'below';
		$notes_content = isset( $_POST['fanfic_author_notes'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) ) : '';

		// Temporarily update post meta for validation
		$old_title = $chapter->post_title;
		$old_content = $chapter->post_content;
		$old_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		$old_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

		// Update meta for validation
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );

		// Temporarily update title and content for validation
		wp_update_post( array(
			'ID'           => $chapter_id,
			'post_title'   => $chapter_title,
			'post_content' => $chapter_content,
		), true );

		// Validate chapter
		$validation_result = Fanfic_Validation::can_publish_chapter( $chapter_id );

		// If validation fails, rollback changes
		if ( ! $validation_result['can_publish'] ) {
			// Rollback
			update_post_meta( $chapter_id, '_fanfic_chapter_type', $old_type );
			update_post_meta( $chapter_id, '_fanfic_chapter_number', $old_number );
			wp_update_post( array(
				'ID'           => $chapter_id,
				'post_title'   => $old_title,
				'post_content' => $old_content,
			), true );

			wp_send_json_error( array(
				'message'         => __( 'Cannot update chapter. Missing required fields:', 'fanfiction-manager' ),
				'missing_fields'  => $validation_result['missing_fields'],
				'errors'          => array_values( $validation_result['missing_fields'] ),
			) );
		}

		// Persist notes fields sent from quick update action.
		update_post_meta( $chapter_id, '_fanfic_author_notes_enabled', $notes_enabled );
		update_post_meta( $chapter_id, '_fanfic_author_notes_position', $notes_position );
		update_post_meta( $chapter_id, '_fanfic_author_notes', $notes_content );

		// Save comments enabled meta
		$chapter_comments_enabled = isset( $_POST['fanfic_chapter_comments_enabled'] ) ? '1' : '0';
		update_post_meta( $chapter_id, '_fanfic_chapter_comments_enabled', $chapter_comments_enabled );

		// Update successful - changes are already saved
		wp_send_json_success( array(
			'message'         => __( 'Chapter updated successfully!', 'fanfiction-manager' ),
			'chapter_id'      => $chapter_id,
			'chapter_title'   => $chapter_title,
			'chapter_type'    => $chapter_type,
			'chapter_number'  => $chapter_number,
		) );
	}

}
