<?php
/**
 * Chapter Handler Class
 *
 * Handles all chapter-related operations including creation, editing, deletion, and validation.
 *
 * @package FanfictionManager
 * @subpackage Handlers
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Chapter_Handler
 *
 * Chapter management operations.
 *
 * @since 1.0.0
 */
class Fanfic_Chapter_Handler {

	/**
	 * Register chapter handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Register form submission handlers
		add_action( 'template_redirect', array( __CLASS__, 'handle_create_chapter_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_chapter_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_delete_chapter' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_create_chapter', array( __CLASS__, 'handle_create_chapter_submission' ) );
		add_action( 'wp_ajax_fanfic_edit_chapter', array( __CLASS__, 'handle_edit_chapter_submission' ) );
		add_action( 'wp_ajax_fanfic_delete_chapter', array( __CLASS__, 'ajax_delete_chapter' ) );
		add_action( 'wp_ajax_fanfic_check_last_chapter', array( __CLASS__, 'ajax_check_last_chapter' ) );

		// Filter chapters by parent story status (hide chapters of draft stories on frontend)
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_chapters_by_story_status' ) );
		add_filter( 'posts_join', array( __CLASS__, 'filter_chapters_join' ), 10, 2 );
		add_filter( 'posts_where', array( __CLASS__, 'filter_chapters_where' ), 10, 2 );
	}

	/**
	 * Handle create chapter form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_create_chapter_submission() {
		if ( ! isset( $_POST['fanfic_create_chapter_submit'] ) ) {
			return;
		}

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_chapter_nonce'], 'fanfic_create_chapter_action_' . $story_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || ( $story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) ) {
			return;
		}

		$errors = array();

		// Get and sanitize form data
		$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
		$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
		error_log( 'Create chapter - Action: ' . $chapter_action . ', Status: ' . $chapter_status );
		$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
		$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
		$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';

		// Validate chapter type restrictions
		if ( 'prologue' === $chapter_type && self::story_has_prologue( $story_id ) ) {
			$errors[] = __( 'This story already has a prologue. Only one prologue is allowed per story.', 'fanfiction-manager' );
		}

		if ( 'epilogue' === $chapter_type && self::story_has_epilogue( $story_id ) ) {
			$errors[] = __( 'This story already has an epilogue. Only one epilogue is allowed per story.', 'fanfiction-manager' );
		}

		// Auto-calculate chapter number based on type
		$chapter_number = 0;
		if ( 'prologue' === $chapter_type ) {
			$chapter_number = self::get_next_prologue_number( $story_id );
		} elseif ( 'epilogue' === $chapter_type ) {
			$chapter_number = self::get_next_epilogue_number( $story_id );
		} else {
			// For regular chapters, get from form input
			$chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
		}

		// Validate
		if ( 'chapter' === $chapter_type && ! $chapter_number ) {
			$errors[] = __( 'Chapter number is required.', 'fanfiction-manager' );
		}

		if ( empty( $title ) ) {
			$errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
		}

		if ( empty( $content ) ) {
			$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

			error_log( 'Chapter created with ID: ' . $chapter_id . ', Status: ' . $chapter_status );
		// Create chapter
		$chapter_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_chapter',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $chapter_status,
			'post_author'  => $current_user->ID,
			'post_parent'  => $story_id,
		) );

		if ( is_wp_error( $chapter_id ) ) {
			$errors[] = $chapter_id->get_error_message();
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Set chapter metadata
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );

		// Check if this is the first published chapter and story is a draft
		error_log( 'Checking for first published chapter. Chapter status: ' . $chapter_status . ', Story status: ' . $story->post_status . ', Chapter type: ' . $chapter_type );
		$is_first_published_chapter = false;
		if ( 'publish' === $chapter_status && 'draft' === $story->post_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count published chapters (excluding the one we just created)
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
			) );
			error_log( 'Other published chapters count: ' . count( $published_chapters ) );

			// If there are no other published chapters, this is the first
			if ( empty( $published_chapters ) ) {
				error_log( 'This is the FIRST published chapter! Will show prompt.' );
				$is_first_published_chapter = true;
			}
		}



	// Redirect to chapter edit page
	$chapter_url = get_permalink( $chapter_id );
	$edit_url = add_query_arg( 'action', 'edit', $chapter_url );

	// Add parameter if story was auto-drafted
	if ( $was_story_auto_drafted ) {
		$redirect_url = add_query_arg( 'story_auto_drafted', '1', $redirect_url );
	}

	// Add parameter to show publication prompt if this is first chapter
	if ( $is_first_published_chapter ) {
		error_log( 'Redirecting to: ' . $edit_url );
		$edit_url = add_query_arg( 'show_publish_prompt', '1', $edit_url );
	}

		// Check if this is an AJAX request
		if ( wp_doing_ajax() ) {
			// Return JSON response for AJAX
			wp_send_json_success( array(
				'redirect_url' => $edit_url,
				'chapter_id' => $chapter_id,
			) );
		} else {
			// Regular form submission - redirect
			wp_redirect( $edit_url );
			exit;
		}
	}

	/**
	 * Handle edit chapter form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_edit_chapter_submission() {
		if ( ! isset( $_POST['fanfic_edit_chapter_submit'] ) ) {
			return;
		}

		$chapter_id = isset( $_POST['fanfic_chapter_id'] ) ? absint( $_POST['fanfic_chapter_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_chapter_nonce'], 'fanfic_edit_chapter_action_' . $chapter_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );

		// Check permissions
		if ( ! $chapter || ( $chapter->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) ) {
			return;
		}

		$errors = array();

		// Get and sanitize form data
		$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
		$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
		$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
		$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
		$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';

		// Get story ID from chapter
		$story_id = $chapter->post_parent;

		// Get current chapter type
		$old_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		if ( empty( $old_type ) ) {
			$old_type = 'chapter';
		}

		// Validate chapter type restrictions when changing type
		if ( 'prologue' === $chapter_type && 'prologue' !== $old_type && self::story_has_prologue( $story_id, $chapter_id ) ) {
			$errors[] = __( 'This story already has a prologue. Only one prologue is allowed per story.', 'fanfiction-manager' );
		}

		if ( 'epilogue' === $chapter_type && 'epilogue' !== $old_type && self::story_has_epilogue( $story_id, $chapter_id ) ) {
			$errors[] = __( 'This story already has an epilogue. Only one epilogue is allowed per story.', 'fanfiction-manager' );
		}

		// Auto-calculate chapter number based on type
		$chapter_number = 0;
		if ( 'prologue' === $chapter_type ) {
			// When editing, preserve the existing prologue number if it was already a prologue
			if ( 'prologue' === $old_type ) {
				$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			} else {
				$chapter_number = self::get_next_prologue_number( $story_id );
			}
		} elseif ( 'epilogue' === $chapter_type ) {
			// When editing, preserve the existing epilogue number if it was already an epilogue
			if ( 'epilogue' === $old_type ) {
				$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			} else {
				$chapter_number = self::get_next_epilogue_number( $story_id );
			}
		} else {
			// For regular chapters, get from form input
			$chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
		}

		// Validate
		if ( 'chapter' === $chapter_type && ! $chapter_number ) {
			$errors[] = __( 'Chapter number is required.', 'fanfiction-manager' );
		}

		if ( empty( $title ) ) {
			$errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
		}

		if ( empty( $content ) ) {
			$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Get old status BEFORE updating
		$old_status = get_post_status( $chapter_id );
		$story = get_post( $story_id );

		// Update chapter
		$result = wp_update_post( array(
			'ID'           => $chapter_id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $chapter_status,
		) );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Update chapter metadata
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );


		// Check if this is becoming the first published chapter
		$is_first_published_chapter = false;

		// Only check if we're publishing a chapter that was draft and story is also draft
		if ( 'publish' === $chapter_status && 'draft' === $old_status && 'draft' === $story->post_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other published chapters
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
			) );

			// If there are no other published chapters, this is the first
			if ( empty( $published_chapters ) ) {
				$is_first_published_chapter = true;
			}
		}

		// Check if we're drafting the last published chapter/prologue
		$was_story_auto_drafted = false;
		if ( 'draft' === $chapter_status && 'publish' === $old_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other published chapters/prologues (excluding this one)
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
			) );

			// If no other published chapters/prologues, auto-draft the story
			if ( empty( $published_chapters ) ) {
				wp_update_post( array(
					'ID'          => $story_id,
					'post_status' => 'draft',
				) );
				$was_story_auto_drafted = true;
				error_log( 'Auto-drafted story ' . $story_id . ' because last published chapter/prologue was drafted' );
			}
		}

		// Redirect back with success message
		$redirect_url = add_query_arg(
			array(
				'chapter_id' => $chapter_id,
				'updated' => 'success'
			),
			wp_get_referer()
		);

	// Add parameter if story was auto-drafted
	if ( $was_story_auto_drafted ) {
		$redirect_url = add_query_arg( 'story_auto_drafted', '1', $redirect_url );
	}

		// Add parameter to show publication prompt if this is first chapter
		if ( $is_first_published_chapter ) {
			$redirect_url = add_query_arg( 'show_publish_prompt', '1', $redirect_url );
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle delete chapter action
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_delete_chapter() {
		if ( ! isset( $_POST['fanfic_delete_chapter_submit'] ) ) {
			return;
		}

		$chapter_id = isset( $_POST['fanfic_chapter_id'] ) ? absint( $_POST['fanfic_chapter_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_delete_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_delete_chapter_nonce'], 'fanfic_delete_chapter_' . $chapter_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );

		// Check permissions
		if ( ! $chapter || ( $chapter->post_author != $current_user->ID && ! current_user_can( 'delete_others_posts' ) ) ) {
			return;
		}

		// Get page URL helper - need to access Story Handler
		require_once plugin_dir_path( __FILE__ ) . 'class-fanfic-story-handler.php';

		// Delete the chapter
		wp_delete_post( $chapter_id, true );

		// Redirect to manage stories with success message
		$redirect_url = add_query_arg( 'chapter_deleted', 'success', Fanfic_Story_Handler::get_page_url_with_fallback( 'manage-stories' ) );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for deleting a chapter
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_delete_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_delete_chapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to delete chapters.', 'fanfiction-manager' ) ) );
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

		// Check permissions - must be author or have delete_others_posts capability
		if ( $story->post_author != $current_user->ID && ! current_user_can( 'delete_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this chapter.', 'fanfiction-manager' ) ) );
		}

		// Get chapter type
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );

		// Check if this is the last chapter/prologue (epilogues don't count)
		$is_last_publishable_chapter = false;
		if ( in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other chapters/prologues (exclude epilogues and the one being deleted)
			$other_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story->ID,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
			) );

			if ( empty( $other_chapters ) ) {
				$is_last_publishable_chapter = true;

				// Auto-draft the story
				wp_update_post( array(
					'ID'          => $story->ID,
					'post_status' => 'draft',
				) );

				error_log( 'Auto-drafted story ' . $story->ID . ' because last chapter/prologue was deleted' );
			}
		}

		// Delete the chapter
		$result = wp_delete_post( $chapter_id, true );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Chapter deleted successfully.', 'fanfiction-manager' ),
				'chapter_id' => $chapter_id,
				'story_auto_drafted' => $is_last_publishable_chapter,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete chapter.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX handler to check if chapter is the last publishable one
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_check_last_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! ( wp_verify_nonce( $_POST['nonce'], 'fanfic_delete_chapter' ) || wp_verify_nonce( $_POST['nonce'], 'fanfic_check_last_chapter' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter ID.', 'fanfiction-manager' ) ) );
		}

		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Chapter not found.', 'fanfiction-manager' ) ) );
		}

		// Get chapter type
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		$is_last = false;

		// Only check for prologue/chapter (epilogues don't count)
		if ( in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other PUBLISHED chapters/prologues
			$other_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $chapter->post_parent,
				'post_status'    => 'publish',  // Only count published chapters
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
			) );

			$is_last = empty( $other_chapters );
		}

		wp_send_json_success( array(
			'is_last_chapter' => $is_last,
			'chapter_type' => $chapter_type,
		) );
	}

	/**
	 * Get available chapter numbers for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $exclude_chapter_id Optional. Chapter ID to exclude (for editing).
	 * @return array Available chapter numbers.
	 */
	private static function get_available_chapter_numbers( $story_id, $exclude_chapter_id = 0 ) {
		// Get existing chapters
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( $exclude_chapter_id ) {
			$args['post__not_in'] = array( $exclude_chapter_id );
		}

		$chapters = get_posts( $args );

		// Get used chapter numbers
		$used_numbers = array();
		foreach ( $chapters as $chapter_id ) {
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			if ( $chapter_number ) {
				$used_numbers[] = absint( $chapter_number );
			}
		}

		// Generate available numbers (1-100)
		$available_numbers = array();
		for ( $i = 1; $i <= 100; $i++ ) {
			if ( ! in_array( $i, $used_numbers ) || ( $exclude_chapter_id && get_post_meta( $exclude_chapter_id, '_fanfic_chapter_number', true ) == $i ) ) {
				$available_numbers[] = $i;
			}
		}

		return $available_numbers;
	}

	/**
	 * Get prologue number for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Prologue number (always 0).
	 */
	private static function get_next_prologue_number( $story_id ) {
		// Prologue is always number 0
		return 0;
	}

	/**
	 * Get epilogue number for a story
	 *
	 * Epilogues start from 1000. If there's a conflict with an existing chapter
	 * (e.g., a story with 1000+ chapters), the epilogue number is incremented
	 * until a free number is found.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Epilogue number (1000 or higher if conflict exists).
	 */
	private static function get_next_epilogue_number( $story_id ) {
		// Get all existing chapter numbers
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$chapters = get_posts( $args );

		// Get used chapter numbers
		$used_numbers = array();
		foreach ( $chapters as $chapter_id ) {
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			if ( $chapter_number ) {
				$used_numbers[] = absint( $chapter_number );
			}
		}

		// Start from 1000 and find the first available number
		$epilogue_number = 1000;
		while ( in_array( $epilogue_number, $used_numbers ) ) {
			$epilogue_number++;
		}

		return $epilogue_number;
	}

	/**
	 * Check if a story already has a prologue
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $exclude_chapter_id Optional. Chapter ID to exclude (for editing).
	 * @return bool True if prologue exists, false otherwise.
	 */
	private static function story_has_prologue( $story_id, $exclude_chapter_id = 0 ) {
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_fanfic_chapter_type',
					'value' => 'prologue',
				),
			),
		);

		if ( $exclude_chapter_id ) {
			$args['post__not_in'] = array( $exclude_chapter_id );
		}

		$prologues = get_posts( $args );
		return ! empty( $prologues );
	}

	/**
	 * Check if a story already has an epilogue
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $exclude_chapter_id Optional. Chapter ID to exclude (for editing).
	 * @return bool True if epilogue exists, false otherwise.
	 */
	private static function story_has_epilogue( $story_id, $exclude_chapter_id = 0 ) {
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_fanfic_chapter_type',
					'value' => 'epilogue',
				),
			),
		);

		if ( $exclude_chapter_id ) {
			$args['post__not_in'] = array( $exclude_chapter_id );
		}

		$epilogues = get_posts( $args );
		return ! empty( $epilogues );
	}

	/**
	 * Filter chapters by parent story status
	 *
	 * Hides chapters whose parent story is in draft status from frontend queries.
	 * This ensures that chapters are automatically hidden when their story is drafted
	 * and automatically visible when the story is republished.
	 *
	 * Uses SQL JOIN for optimal performance instead of multiple get_posts() calls.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public static function filter_chapters_by_story_status( $query ) {
		// Only filter on frontend (not admin)
		if ( is_admin() ) {
			return;
		}

		// Only filter main queries for fanfiction_chapter post type
		if ( ! $query->is_main_query() || $query->get( 'post_type' ) !== 'fanfiction_chapter' ) {
			return;
		}

		// Set a flag so our JOIN and WHERE filters know to apply
		$query->set( 'fanfic_filter_by_parent_status', true );
	}

	/**
	 * Add JOIN clause to check parent story status
	 *
	 * Joins the posts table with itself to access parent post data.
	 * Only applies when fanfic_filter_by_parent_status flag is set.
	 *
	 * @since 1.0.0
	 * @param string   $join  The JOIN clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 * @return string Modified JOIN clause.
	 */
	public static function filter_chapters_join( $join, $query ) {
		global $wpdb;

		// Only apply if our flag is set
		if ( ! $query->get( 'fanfic_filter_by_parent_status' ) ) {
			return $join;
		}

		// JOIN with parent posts table to check parent status
		$join .= " INNER JOIN {$wpdb->posts} AS parent_story ON {$wpdb->posts}.post_parent = parent_story.ID";

		return $join;
	}

	/**
	 * Add WHERE clause to exclude chapters with draft parent stories
	 *
	 * Filters out chapters whose parent story has post_status = 'draft'.
	 * Only applies when fanfic_filter_by_parent_status flag is set.
	 *
	 * @since 1.0.0
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 * @return string Modified WHERE clause.
	 */
	public static function filter_chapters_where( $where, $query ) {
		// Only apply if our flag is set
		if ( ! $query->get( 'fanfic_filter_by_parent_status' ) ) {
			return $where;
		}

		// Exclude chapters whose parent story is draft
		$where .= " AND parent_story.post_status = 'publish'";

		return $where;
	}

}
