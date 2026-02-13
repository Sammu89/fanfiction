<?php
/**
 * Author Dashboard Class
 *
 * Manages all author dashboard functionality including story management,
 * chapter management, and profile editing.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Author_Dashboard
 *
 * Handles all author dashboard operations including CRUD operations
 * for stories and chapters, profile updates, and data validation.
 *
 * @since 1.0.0
 */
class Fanfic_Author_Dashboard {

	/**
	 * Initialize the class and register hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$instance = new self();
		$instance->register_hooks();
	}

	/**
	 * Register action and filter hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		// AJAX handlers for story operations
		add_action( 'wp_ajax_fanfic_story_create', array( $this, 'handle_story_form_submit' ) );
		add_action( 'wp_ajax_fanfic_story_update', array( $this, 'handle_story_form_submit' ) );
		add_action( 'wp_ajax_fanfic_story_delete', array( $this, 'handle_delete_story' ) );

		// AJAX handlers for chapter operations
		add_action( 'wp_ajax_fanfic_chapter_create', array( $this, 'handle_chapter_form_submit' ) );
		add_action( 'wp_ajax_fanfic_chapter_update', array( $this, 'handle_chapter_form_submit' ) );
		add_action( 'wp_ajax_fanfic_chapter_delete', array( $this, 'handle_delete_chapter' ) );
		add_action( 'wp_ajax_fanfic_chapters_reorder', array( $this, 'handle_reorder_chapters' ) );

		// AJAX handler for profile operations
		add_action( 'wp_ajax_fanfic_profile_update', array( $this, 'handle_profile_form_submit' ) );

		// Hook to automatically promote reader to author on first published story
		add_action( 'fanfic_story_validated', array( $this, 'maybe_promote_to_author' ), 10, 2 );
	}

	/**
	 * Handle story creation and update form submission.
	 *
	 * Processes POST data for creating or updating stories with full validation
	 * and security checks.
	 *
	 * @since 1.0.0
	 */
	public function handle_story_form_submit() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_story_form' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Check user capability
		if ( ! current_user_can( 'edit_fanfiction_stories' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Determine if this is create or update
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$is_update = $story_id > 0;

		// If updating, verify ownership
		if ( $is_update ) {
			$story = get_post( $story_id );
			if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
				wp_send_json_error(
					array(
						'message' => __( 'Story not found.', 'fanfiction-manager' ),
					),
					404
				);
			}

			// Check if user owns this story or has mod/admin capabilities
			if ( absint( $story->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_others_fanfiction_stories' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'You do not have permission to edit this story.', 'fanfiction-manager' ),
					),
					403
				);
			}
		}

		// Collect and sanitize form data
		$data = array(
			'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'intro'   => isset( $_POST['intro'] ) ? wp_kses_post( wp_unslash( $_POST['intro'] ) ) : '',
			'genres'  => isset( $_POST['genres'] ) && is_array( $_POST['genres'] ) ? array_map( 'absint', wp_unslash( $_POST['genres'] ) ) : array(),
			'status'  => isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 0,
			'post_status' => isset( $_POST['post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['post_status'] ) ) : 'draft',
		);

		// Validate story data
		$validation = $this->validate_story_data( $data );
		if ( ! $validation['valid'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Validation failed. Please check the form and try again.', 'fanfiction-manager' ),
					'errors'  => $validation['errors'],
				),
				400
			);
		}

		// Prepare post data
		$post_data = array(
			'post_title'   => $data['title'],
			'post_excerpt' => $data['intro'],
			'post_status'  => in_array( $data['post_status'], array( 'draft', 'publish' ), true ) ? $data['post_status'] : 'draft',
			'post_type'    => 'fanfiction_story',
		);

		// Add ID for updates
		if ( $is_update ) {
			$post_data['ID'] = $story_id;
		} else {
			$post_data['post_author'] = get_current_user_id();
		}

		// Insert or update post
		if ( $is_update ) {
			$result = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		// Check for errors
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to save story: %s', 'fanfiction-manager' ),
						$result->get_error_message()
					),
				),
				500
			);
		}

		$story_id = $result;

		// Update taxonomies
		if ( ! empty( $data['genres'] ) ) {
			wp_set_post_terms( $story_id, $data['genres'], 'fanfiction_genre' );
		} else {
			wp_set_post_terms( $story_id, array(), 'fanfiction_genre' );
		}

		if ( ! empty( $data['status'] ) ) {
			wp_set_post_terms( $story_id, array( $data['status'] ), 'fanfiction_status' );
		} else {
			wp_set_post_terms( $story_id, array(), 'fanfiction_status' );
		}

		// Get the story URL
		$story_url = get_permalink( $story_id );
		$edit_url = fanfic_get_edit_story_url( $story_id );

		// Prepare success response
		$response = array(
			'message' => $is_update
				? __( 'Story updated successfully.', 'fanfiction-manager' )
				: __( 'Story created successfully.', 'fanfiction-manager' ),
			'data'    => array(
				'post_id'      => $story_id,
				'redirect_url' => $edit_url,
				'view_url'     => $story_url,
			),
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle chapter creation and update form submission.
	 *
	 * Processes POST data for creating or updating chapters with full validation
	 * and security checks.
	 *
	 * @since 1.0.0
	 */
	public function handle_chapter_form_submit() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_chapter_form' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Check user capability
		if ( ! current_user_can( 'edit_fanfiction_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Get story ID
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		if ( ! $story_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Story ID is required.', 'fanfiction-manager' ),
				),
				400
			);
		}

		// Verify story exists and user has permission
		$story = get_post( $story_id );
		if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Story not found.', 'fanfiction-manager' ),
				),
				404
			);
		}

		if ( absint( $story->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_others_fanfiction_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to edit chapters for this story.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Determine if this is create or update
		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$is_update = $chapter_id > 0;

		// If updating, verify chapter exists and belongs to this story
		if ( $is_update ) {
			$chapter = get_post( $chapter_id );
			if ( ! $chapter || $chapter->post_type !== 'fanfiction_chapter' || absint( $chapter->post_parent ) !== $story_id ) {
				wp_send_json_error(
					array(
						'message' => __( 'Chapter not found or does not belong to this story.', 'fanfiction-manager' ),
					),
					404
				);
			}
		}

		// Collect and sanitize form data
		$data = array(
			'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'content'        => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			'chapter_number' => isset( $_POST['chapter_number'] ) ? absint( $_POST['chapter_number'] ) : 0,
			'chapter_type'   => isset( $_POST['chapter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['chapter_type'] ) ) : 'chapter',
			'post_status'    => isset( $_POST['post_status'] ) ? sanitize_text_field( wp_unslash( $_POST['post_status'] ) ) : 'publish',
		);

		// Validate chapter data
		$validation = $this->validate_chapter_data( $data, $story_id, $chapter_id );
		if ( ! $validation['valid'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Validation failed. Please check the form and try again.', 'fanfiction-manager' ),
					'errors'  => $validation['errors'],
				),
				400
			);
		}

		// Determine menu_order based on chapter type
		$menu_order = 0;
		if ( 'prologue' === $data['chapter_type'] ) {
			$menu_order = 0;
		} elseif ( 'epilogue' === $data['chapter_type'] ) {
			// Find the highest menu_order and add 1
			$highest = $this->get_highest_chapter_order( $story_id );
			$menu_order = $highest + 1;
		} else {
			// Regular chapter - use chapter number
			$menu_order = $data['chapter_number'];
		}

		// Prepare post data
		$post_data = array(
			'post_title'   => $data['title'],
			'post_content' => $data['content'],
			'post_status'  => in_array( $data['post_status'], array( 'draft', 'publish' ), true ) ? $data['post_status'] : 'publish',
			'post_type'    => 'fanfiction_chapter',
			'post_parent'  => $story_id,
			'menu_order'   => $menu_order,
		);

		// Add ID for updates
		if ( $is_update ) {
			$post_data['ID'] = $chapter_id;
		} else {
			$post_data['post_author'] = get_current_user_id();
		}

		// Insert or update post
		if ( $is_update ) {
			$result = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		// Check for errors
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to save chapter: %s', 'fanfiction-manager' ),
						$result->get_error_message()
					),
				),
				500
			);
		}

		$chapter_id = $result;

		// Update chapter type meta
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $data['chapter_type'] );
		if ( 'chapter' === $data['chapter_type'] ) {
			update_post_meta( $chapter_id, '_fanfic_chapter_number', $data['chapter_number'] );
		}

		// Get URLs
		$chapter_url = get_permalink( $chapter_id );
		$edit_url = fanfic_get_edit_chapter_url( $story_id, $chapter_id );

		// Prepare success response
		$response = array(
			'message' => $is_update
				? __( 'Chapter updated successfully.', 'fanfiction-manager' )
				: __( 'Chapter created successfully.', 'fanfiction-manager' ),
			'data'    => array(
				'post_id'      => $chapter_id,
				'redirect_url' => $edit_url,
				'view_url'     => $chapter_url,
			),
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle profile update form submission.
	 *
	 * Processes POST data for updating user profile information.
	 *
	 * @since 1.0.0
	 */
	public function handle_profile_form_submit() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_profile_form' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to update your profile.', 'fanfiction-manager' ),
				),
				403
			);
		}

		$user_id = get_current_user_id();

		// Collect and sanitize form data
		$data = array(
			'display_name' => isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '',
			'bio'          => isset( $_POST['bio'] ) ? wp_kses_post( wp_unslash( $_POST['bio'] ) ) : '',
			'avatar_url'   => isset( $_POST['avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['avatar_url'] ) ) : '',
		);

		// Validate profile data
		$validation = $this->validate_profile_data( $data );
		if ( ! $validation['valid'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Validation failed. Please check the form and try again.', 'fanfiction-manager' ),
					'errors'  => $validation['errors'],
				),
				400
			);
		}

		// Update user display name if provided
		if ( ! empty( $data['display_name'] ) ) {
			$result = wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $data['display_name'],
				)
			);

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: error message */
							__( 'Failed to update display name: %s', 'fanfiction-manager' ),
							$result->get_error_message()
						),
					),
					500
				);
			}
		}

		// Update user meta
		update_user_meta( $user_id, 'fanfic_bio', $data['bio'] );
		if ( ! empty( $data['avatar_url'] ) ) {
			update_user_meta( $user_id, 'fanfic_avatar_url', $data['avatar_url'] );
		} else {
			delete_user_meta( $user_id, 'fanfic_avatar_url' );
		}

		// Prepare success response
		$response = array(
			'message' => __( 'Profile updated successfully.', 'fanfiction-manager' ),
			'data'    => array(
				'user_id' => $user_id,
			),
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle story deletion.
	 *
	 * Permanently deletes a story and all its chapters.
	 *
	 * @since 1.0.0
	 */
	public function handle_delete_story() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_delete_story' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Check user capability
		if ( ! current_user_can( 'delete_fanfiction_stories' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to delete stories.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Get story ID
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		if ( ! $story_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Story ID is required.', 'fanfiction-manager' ),
				),
				400
			);
		}

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Story not found.', 'fanfiction-manager' ),
				),
				404
			);
		}

		// Check if user owns this story or has mod/admin capabilities
		if ( absint( $story->post_author ) !== get_current_user_id() && ! current_user_can( 'delete_others_fanfiction_stories' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to delete this story.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Delete all chapters first
		$chapters = get_children(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Story Delete: Starting deletion for story ID ' . $story_id );
			error_log( 'Story Delete: Found ' . count( $chapters ) . ' chapters to delete' );
		}

		// Delete chapters with error checking
		$chapter_errors = array();
		foreach ( $chapters as $chapter_id ) {
			$chapter_result = wp_delete_post( $chapter_id, true );
			if ( ! $chapter_result || is_wp_error( $chapter_result ) ) {
				$error_message = is_wp_error( $chapter_result ) ? $chapter_result->get_error_message() : 'Unknown error';
				$chapter_errors[] = sprintf( __( 'Failed to delete chapter %d: %s', 'fanfiction-manager' ), $chapter_id, $error_message );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Story Delete: Failed to delete chapter ' . $chapter_id . ': ' . $error_message );
				}
			}
		}

		// If any chapters failed to delete, report error
		if ( ! empty( $chapter_errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to delete some chapters.', 'fanfiction-manager' ),
					'errors'  => $chapter_errors,
				),
				500
			);
		}

		// Delete the story permanently
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Story Delete: Deleting story post ' . $story_id );
		}

		$result = wp_delete_post( $story_id, true );

		// Check for errors
		if ( ! $result || is_wp_error( $result ) ) {
			$error_message = is_wp_error( $result ) ? $result->get_error_message() : __( 'wp_delete_post returned false', 'fanfiction-manager' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Story Delete: Failed to delete story ' . $story_id . ': ' . $error_message );
			}
			wp_send_json_error(
				array(
					'message' => sprintf( __( 'Failed to delete story: %s', 'fanfiction-manager' ), $error_message ),
				),
				500
			);
		}

		// Verify the post was actually deleted
		$verify = get_post( $story_id );
		if ( $verify && 'trash' !== $verify->post_status ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Story Delete: Story still exists after delete attempt. Status: ' . $verify->post_status );
			}
			wp_send_json_error(
				array(
					'message' => __( 'Delete operation completed but story still exists in database.', 'fanfiction-manager' ),
				),
				500
			);
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Story Delete: Successfully deleted story ' . $story_id );
		}

		// Prepare success response
		$response = array(
			'message' => __( 'Story and all chapters deleted successfully.', 'fanfiction-manager' ),
			'data'    => array(
				'redirect_url' => fanfic_get_dashboard_url(),
			),
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle chapter deletion.
	 *
	 * Permanently deletes a chapter.
	 *
	 * @since 1.0.0
	 */
	public function handle_delete_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_delete_chapter' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Check user capability
		if ( ! current_user_can( 'delete_fanfiction_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to delete chapters.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Get chapter ID
		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		if ( ! $chapter_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Chapter ID is required.', 'fanfiction-manager' ),
				),
				400
			);
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || $chapter->post_type !== 'fanfiction_chapter' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Chapter not found.', 'fanfiction-manager' ),
				),
				404
			);
		}

		$story_id = $chapter->post_parent;

		// Verify story exists and user has permission
		$story = get_post( $story_id );
		if ( ! $story ) {
			wp_send_json_error(
				array(
					'message' => __( 'Parent story not found.', 'fanfiction-manager' ),
				),
				404
			);
		}

		if ( absint( $story->post_author ) !== get_current_user_id() && ! current_user_can( 'delete_others_fanfiction_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to delete chapters for this story.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Chapter Delete: Starting deletion for chapter ID ' . $chapter_id . ' (Story ID: ' . $story_id . ')' );
		}

		// Delete the chapter permanently
		$result = wp_delete_post( $chapter_id, true );

		// Check for errors
		if ( ! $result || is_wp_error( $result ) ) {
			$error_message = is_wp_error( $result ) ? $result->get_error_message() : __( 'wp_delete_post returned false', 'fanfiction-manager' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Chapter Delete: Failed to delete chapter ' . $chapter_id . ': ' . $error_message );
			}
			wp_send_json_error(
				array(
					'message' => sprintf( __( 'Failed to delete chapter: %s', 'fanfiction-manager' ), $error_message ),
				),
				500
			);
		}

		// Verify the post was actually deleted
		$verify = get_post( $chapter_id );
		if ( $verify && 'trash' !== $verify->post_status ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Chapter Delete: Chapter still exists after delete attempt. Status: ' . $verify->post_status );
			}
			wp_send_json_error(
				array(
					'message' => __( 'Delete operation completed but chapter still exists in database.', 'fanfiction-manager' ),
				),
				500
			);
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Chapter Delete: Successfully deleted chapter ' . $chapter_id );
		}

		// Prepare success response
		$response = array(
			'message' => __( 'Chapter deleted successfully.', 'fanfiction-manager' ),
			'data'    => array(
				'redirect_url' => fanfic_get_edit_story_url( $story_id ),
			),
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle chapter reordering.
	 *
	 * Updates the menu_order for multiple chapters.
	 *
	 * @since 1.0.0
	 */
	public function handle_reorder_chapters() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_reorder_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Check user capability
		if ( ! current_user_can( 'edit_fanfiction_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to reorder chapters.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Get story ID and chapter order
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$chapter_order = isset( $_POST['chapter_order'] ) && is_array( $_POST['chapter_order'] ) ? array_map( 'absint', wp_unslash( $_POST['chapter_order'] ) ) : array();

		if ( ! $story_id || empty( $chapter_order ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Story ID and chapter order are required.', 'fanfiction-manager' ),
				),
				400
			);
		}

		// Verify story exists and user has permission
		$story = get_post( $story_id );
		if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Story not found.', 'fanfiction-manager' ),
				),
				404
			);
		}

		if ( absint( $story->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_others_fanfiction_chapters' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to reorder chapters for this story.', 'fanfiction-manager' ),
				),
				403
			);
		}

		// Reorder chapters
		$result = $this->reorder_chapters( $story_id, $chapter_order );

		if ( ! $result ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to reorder chapters.', 'fanfiction-manager' ),
				),
				500
			);
		}

		// Prepare success response
		$response = array(
			'message' => __( 'Chapters reordered successfully.', 'fanfiction-manager' ),
		);

		wp_send_json_success( $response );
	}

	/**
	 * Validate story data.
	 *
	 * Checks story title, introduction, genres, and status against validation rules.
	 *
	 * @since 1.0.0
	 * @param array $data Story data to validate.
	 * @return array Array with 'valid' boolean and 'errors' array.
	 */
	public function validate_story_data( $data ) {
		$errors = array();

		// Validate title
		if ( empty( $data['title'] ) ) {
			$errors['title'] = __( 'Story title is required.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['title'] ) < 3 ) {
			$errors['title'] = __( 'Story title must be at least 3 characters long.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['title'] ) > 200 ) {
			$errors['title'] = __( 'Story title must not exceed 200 characters.', 'fanfiction-manager' );
		}

		// Validate introduction
		if ( empty( $data['intro'] ) ) {
			$errors['intro'] = __( 'Story introduction is required.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['intro'] ) < 10 ) {
			$errors['intro'] = __( 'Story introduction must be at least 10 characters long.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['intro'] ) > 5000 ) {
			$errors['intro'] = __( 'Story introduction must not exceed 5000 characters.', 'fanfiction-manager' );
		}

		// Validate genres
		if ( empty( $data['genres'] ) ) {
			$errors['genres'] = __( 'At least one genre is required.', 'fanfiction-manager' );
		} else {
			// Verify all genres exist
			foreach ( $data['genres'] as $genre_id ) {
				$term = get_term( $genre_id, 'fanfiction_genre' );
				if ( ! $term || is_wp_error( $term ) ) {
					$errors['genres'] = __( 'One or more selected genres are invalid.', 'fanfiction-manager' );
					break;
				}
			}
		}

		// Validate status
		if ( empty( $data['status'] ) ) {
			$errors['status'] = __( 'Story status is required.', 'fanfiction-manager' );
		} else {
			// Verify status exists
			$term = get_term( $data['status'], 'fanfiction_status' );
			if ( ! $term || is_wp_error( $term ) ) {
				$errors['status'] = __( 'Selected status is invalid.', 'fanfiction-manager' );
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate chapter data.
	 *
	 * Checks chapter title, content, and number against validation rules.
	 *
	 * @since 1.0.0
	 * @param array $data       Chapter data to validate.
	 * @param int   $story_id   Story ID the chapter belongs to.
	 * @param int   $chapter_id Chapter ID (0 for new chapters).
	 * @return array Array with 'valid' boolean and 'errors' array.
	 */
	public function validate_chapter_data( $data, $story_id, $chapter_id = 0 ) {
		$errors = array();

		// Validate title
		if ( empty( $data['title'] ) ) {
			$errors['title'] = __( 'Chapter title is required.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['title'] ) < 3 ) {
			$errors['title'] = __( 'Chapter title must be at least 3 characters long.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['title'] ) > 200 ) {
			$errors['title'] = __( 'Chapter title must not exceed 200 characters.', 'fanfiction-manager' );
		}

		// Validate content
		if ( empty( $data['content'] ) ) {
			$errors['content'] = __( 'Chapter content is required.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['content'] ) < 10 ) {
			$errors['content'] = __( 'Chapter content must be at least 10 characters long.', 'fanfiction-manager' );
		} elseif ( mb_strlen( $data['content'] ) > 100000 ) {
			$errors['content'] = __( 'Chapter content must not exceed 100,000 characters.', 'fanfiction-manager' );
		}

		// Validate chapter type
		if ( ! in_array( $data['chapter_type'], array( 'prologue', 'chapter', 'epilogue' ), true ) ) {
			$errors['chapter_type'] = __( 'Invalid chapter type.', 'fanfiction-manager' );
		}

		// Validate chapter number for regular chapters
		if ( 'chapter' === $data['chapter_type'] ) {
			if ( empty( $data['chapter_number'] ) || $data['chapter_number'] < 1 ) {
				$errors['chapter_number'] = __( 'Chapter number must be a positive integer.', 'fanfiction-manager' );
			} else {
				// Check if chapter number is already used
				$is_available = $this->check_chapter_number_availability( $story_id, $data['chapter_number'], $chapter_id );
				if ( ! $is_available ) {
					$errors['chapter_number'] = __( 'This chapter number is already used in this story.', 'fanfiction-manager' );
				}
			}
		}

		// Check for prologue/epilogue uniqueness
		if ( 'prologue' === $data['chapter_type'] || 'epilogue' === $data['chapter_type'] ) {
			$existing = $this->get_story_chapters( $story_id );
			foreach ( $existing as $existing_chapter ) {
				// Skip if checking the same chapter (update scenario)
				if ( $chapter_id > 0 && absint( $existing_chapter->ID ) === $chapter_id ) {
					continue;
				}

				$existing_type = get_post_meta( $existing_chapter->ID, '_fanfic_chapter_type', true );
				if ( $existing_type === $data['chapter_type'] ) {
					$type_label = ( 'prologue' === $data['chapter_type'] ) ? __( 'Prologue', 'fanfiction-manager' ) : __( 'Epilogue', 'fanfiction-manager' );
					$errors['chapter_type'] = sprintf(
						/* translators: %s: chapter type label */
						__( 'This story already has a %s.', 'fanfiction-manager' ),
						$type_label
					);
					break;
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate profile data.
	 *
	 * Checks display name, bio, and avatar URL against validation rules.
	 *
	 * @since 1.0.0
	 * @param array $data Profile data to validate.
	 * @return array Array with 'valid' boolean and 'errors' array.
	 */
	public function validate_profile_data( $data ) {
		$errors = array();

		// Validate display name
		if ( ! empty( $data['display_name'] ) && mb_strlen( $data['display_name'] ) > 100 ) {
			$errors['display_name'] = __( 'Display name must not exceed 100 characters.', 'fanfiction-manager' );
		}

		// Validate bio
		if ( ! empty( $data['bio'] ) && mb_strlen( $data['bio'] ) > 3000 ) {
			$errors['bio'] = __( 'Bio must not exceed 3000 characters.', 'fanfiction-manager' );
		}

		// Validate avatar URL
		if ( ! empty( $data['avatar_url'] ) && ! filter_var( $data['avatar_url'], FILTER_VALIDATE_URL ) ) {
			$errors['avatar_url'] = __( 'Avatar URL must be a valid URL.', 'fanfiction-manager' );
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Get author statistics.
	 *
	 * Returns total stories, chapters, views, and recent activity for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID to get statistics for.
	 * @return array Array of statistics.
	 */
	public function get_author_stats( $user_id ) {
		// Get total stories
		$stories = get_posts(
			array(
				'author'         => $user_id,
				'post_type'      => 'fanfiction_story',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$total_stories = count( $stories );

		// Get total chapters across all stories
		$total_chapters = 0;
		$total_views = 0;
		foreach ( $stories as $story_id ) {
			$chapters = get_children(
				array(
					'post_parent'    => $story_id,
					'post_type'      => 'fanfiction_chapter',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			$total_chapters += count( $chapters );

			// Get total views from unified interactions index.
			$total_views += class_exists( 'Fanfic_Interactions' ) ? Fanfic_Interactions::get_story_views( $story_id ) : 0;
		}

		// Get recent activity (last 5 published chapters)
		$recent_activity = get_posts(
			array(
				'author'         => $user_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array(
			'total_stories'   => $total_stories,
			'total_chapters'  => $total_chapters,
			'total_views'     => $total_views,
			'recent_activity' => $recent_activity,
		);
	}

	/**
	 * Check if a chapter number is available for a story.
	 *
	 * @since 1.0.0
	 * @param int $story_id       Story ID.
	 * @param int $chapter_number Chapter number to check.
	 * @param int $chapter_id     Chapter ID to exclude from check (for updates).
	 * @return bool True if available, false if already used.
	 */
	public function check_chapter_number_availability( $story_id, $chapter_number, $chapter_id = 0 ) {
		$args = array(
			'post_parent'    => $story_id,
			'post_type'      => 'fanfiction_chapter',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_fanfic_chapter_number',
					'value' => $chapter_number,
				),
			),
		);

		$chapters = get_posts( $args );

		// If no chapters found, number is available
		if ( empty( $chapters ) ) {
			return true;
		}

		// If updating a chapter, exclude it from the check
		if ( $chapter_id > 0 ) {
			foreach ( $chapters as $chapter ) {
				if ( absint( $chapter->ID ) === $chapter_id ) {
					return true;
				}
			}
		}

		// Number is already used
		return false;
	}

	/**
	 * Get all chapters for a story.
	 *
	 * Returns an array of chapter objects with metadata, ordered by menu_order.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return array Array of chapter post objects.
	 */
	public function get_story_chapters( $story_id ) {
		$chapters = get_children(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		// Add chapter type and number metadata
		foreach ( $chapters as $chapter ) {
			$chapter->chapter_type = get_post_meta( $chapter->ID, '_fanfic_chapter_type', true );
			$chapter->chapter_number = get_post_meta( $chapter->ID, '_fanfic_chapter_number', true );
		}

		return $chapters;
	}

	/**
	 * Reorder chapters for a story.
	 *
	 * Updates the menu_order for chapters based on the provided order array.
	 *
	 * @since 1.0.0
	 * @param int   $story_id          Story ID.
	 * @param array $chapter_order_array Array of chapter IDs in desired order.
	 * @return bool True on success, false on failure.
	 */
	public function reorder_chapters( $story_id, $chapter_order_array ) {
		// Verify all chapters belong to this story
		foreach ( $chapter_order_array as $index => $chapter_id ) {
			$chapter = get_post( $chapter_id );
			if ( ! $chapter || $chapter->post_type !== 'fanfiction_chapter' || absint( $chapter->post_parent ) !== $story_id ) {
				return false;
			}

			// Update menu_order
			wp_update_post(
				array(
					'ID'         => $chapter_id,
					'menu_order' => $index,
				)
			);
		}

		return true;
	}

	/**
	 * Get the highest chapter menu_order for a story.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Highest menu_order value.
	 */
	private function get_highest_chapter_order( $story_id ) {
		$chapters = get_children(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
			)
		);

		if ( empty( $chapters ) ) {
			return 0;
		}

		$chapter = array_values( $chapters )[0];
		return absint( $chapter->menu_order );
	}

	/**
	 * Maybe promote user to author role.
	 *
	 * Automatically promotes a user to Fanfic_Author role when they publish
	 * their first validated story.
	 *
	 * @since 1.0.0
	 * @param int  $story_id Story ID.
	 * @param bool $is_valid Whether the story is valid.
	 */
	public function maybe_promote_to_author( $story_id, $is_valid ) {
		if ( ! $is_valid ) {
			return;
		}

		$story = get_post( $story_id );
		if ( ! $story || $story->post_status !== 'publish' ) {
			return;
		}

		$user_id = absint( $story->post_author );
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		// Check if user is currently a reader
		if ( in_array( 'fanfiction_reader', (array) $user->roles, true ) ) {
			// Check if they have any other published stories
			$published_stories = get_posts(
				array(
					'author'         => $user_id,
					'post_type'      => 'fanfiction_story',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			// If this is their first published story, promote to author
			if ( count( $published_stories ) === 1 ) {
				$user->remove_role( 'fanfiction_reader' );
				$user->add_role( 'fanfiction_author' );
			}
		}
	}

}
