<?php
/**
 * Author Dashboard Class
 *
 * Manages author dashboard deletion, chapter ordering, and author promotion.
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
 * Handles the remaining author dashboard operations after consolidating
 * create and edit flows into the dedicated handler classes.
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
		add_action( 'wp_ajax_fanfic_story_delete', array( $this, 'handle_delete_story' ) );

		// AJAX handlers for chapter operations
		add_action( 'wp_ajax_fanfic_chapter_delete', array( $this, 'handle_delete_chapter' ) );
		add_action( 'wp_ajax_fanfic_chapters_reorder', array( $this, 'handle_reorder_chapters' ) );

		// Hook to automatically promote reader to author on first published story
		add_action( 'fanfic_story_validated', array( $this, 'maybe_promote_to_author' ), 10, 2 );
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
