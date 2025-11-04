<?php
/**
 * Fanfiction Validation Class
 *
 * Handles story validation logic to ensure stories meet publishing requirements.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Validation
 *
 * Validates stories against publishing requirements and automatically
 * manages post status based on validation state.
 *
 * @since 1.0.0
 */
class Fanfic_Validation {

	/**
	 * Initialize the validation class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'save_post_fanfiction_story', array( __CLASS__, 'validate_and_update_status' ), 10, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'handle_chapter_deletion' ), 10, 1 );
		add_action( 'set_object_terms', array( __CLASS__, 'handle_taxonomy_update' ), 10, 6 );
	}

	/**
	 * Check if a story meets all validation criteria.
	 *
	 * A story is valid when it has:
	 * 1. An introduction (excerpt field is not empty)
	 * 2. At least one chapter
	 * 3. At least one genre AND status assigned
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return bool True if story is valid, false otherwise.
	 */
	public static function is_story_valid( $story_id ) {
		// Verify this is a fanfiction story
		if ( get_post_type( $story_id ) !== 'fanfiction_story' ) {
			return false;
		}

		// Check all three validation criteria
		$has_excerpt = self::check_story_excerpt( $story_id );
		$has_chapters = self::check_story_chapters( $story_id );
		$has_taxonomies = self::check_story_taxonomies( $story_id );

		// All three must be true
		return $has_excerpt && $has_chapters && $has_taxonomies;
	}

	/**
	 * Validate story and update post_status if needed.
	 *
	 * If a story becomes invalid, automatically reverts to 'draft' status.
	 * Fires action hooks for validation events.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return bool True if story is valid, false if invalidated.
	 */
	public static function validate_and_update_status( $story_id ) {
		// Prevent infinite loops during auto-save
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Verify this is a fanfiction story
		if ( get_post_type( $story_id ) !== 'fanfiction_story' ) {
			return false;
		}

		$story = get_post( $story_id );
		if ( ! $story ) {
			return false;
		}

		$is_valid = self::is_story_valid( $story_id );
		$current_status = $story->post_status;

		// Public statuses that require validation
		$public_statuses = array( 'publish', 'future', 'private' );

		// If story is published but invalid, revert to draft
		if ( ! $is_valid && in_array( $current_status, $public_statuses, true ) ) {
			// Remove this hook temporarily to prevent infinite loop
			remove_action( 'save_post_fanfiction_story', array( __CLASS__, 'validate_and_update_status' ), 10 );

			wp_update_post(
				array(
					'ID'          => $story_id,
					'post_status' => 'draft',
				)
			);

			// Re-add the hook
			add_action( 'save_post_fanfiction_story', array( __CLASS__, 'validate_and_update_status' ), 10, 1 );

			/**
			 * Fires when a story becomes invalid and is reverted to draft.
			 *
			 * @since 1.0.0
			 * @param int   $story_id       The story post ID.
			 * @param array $validation_errors Array of validation error messages.
			 */
			do_action( 'fanfic_story_invalidated', $story_id, self::get_validation_errors( $story_id ) );

			return false;
		}

		/**
		 * Fires after story validation is complete.
		 *
		 * @since 1.0.0
		 * @param int  $story_id The story post ID.
		 * @param bool $is_valid Whether the story is valid.
		 */
		do_action( 'fanfic_story_validated', $story_id, $is_valid );

		return $is_valid;
	}

	/**
	 * Get validation error messages for a story.
	 *
	 * Returns an array of human-readable error messages for each
	 * failed validation criterion.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return array Array of validation error messages.
	 */
	public static function get_validation_errors( $story_id ) {
		$errors = array();

		// Verify this is a fanfiction story
		if ( get_post_type( $story_id ) !== 'fanfiction_story' ) {
			$errors[] = __( 'Invalid post type.', 'fanfiction-manager' );
			return $errors;
		}

		// Check excerpt
		if ( ! self::check_story_excerpt( $story_id ) ) {
			$errors[] = __( 'Story must have an introduction (excerpt).', 'fanfiction-manager' );
		}

		// Check chapters
		if ( ! self::check_story_chapters( $story_id ) ) {
			$errors[] = __( 'Story must have at least one chapter.', 'fanfiction-manager' );
		}

		// Check taxonomies
		if ( ! self::check_story_taxonomies( $story_id ) ) {
			$errors[] = __( 'Story must be categorized with at least one genre and status.', 'fanfiction-manager' );
		}

		return $errors;
	}

	/**
	 * Check if story has an introduction (excerpt).
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return bool True if excerpt exists and is not empty, false otherwise.
	 */
	public static function check_story_excerpt( $story_id ) {
		$story = get_post( $story_id );
		if ( ! $story ) {
			return false;
		}

		$excerpt = trim( $story->post_excerpt );
		return ! empty( $excerpt );
	}

	/**
	 * Check if story has at least one chapter.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return bool True if at least one chapter exists, false otherwise.
	 */
	public static function check_story_chapters( $story_id ) {
		$chapters = get_children(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		return ! empty( $chapters );
	}

	/**
	 * Check if story has required taxonomies (genre and status).
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return bool True if both genre and status are assigned, false otherwise.
	 */
	public static function check_story_taxonomies( $story_id ) {
		// Check for at least one genre
		$genres = wp_get_post_terms( $story_id, 'fanfiction_genre', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $genres ) || empty( $genres ) ) {
			return false;
		}

		// Check for at least one status
		$statuses = wp_get_post_terms( $story_id, 'fanfiction_status', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $statuses ) || empty( $statuses ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle chapter deletion and check if it's the last chapter.
	 *
	 * If deleting the last chapter of a published story, invalidate the story
	 * and trigger a notification.
	 *
	 * @since 1.0.0
	 * @param int $post_id The post ID being deleted.
	 */
	public static function handle_chapter_deletion( $post_id ) {
		// Check if this is a chapter
		if ( get_post_type( $post_id ) !== 'fanfiction_chapter' ) {
			return;
		}

		$chapter = get_post( $post_id );
		if ( ! $chapter || empty( $chapter->post_parent ) ) {
			return;
		}

		$story_id = $chapter->post_parent;

		// Check if this is the last chapter
		$chapters = get_children(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		// If only one chapter exists and we're deleting it
		if ( count( $chapters ) === 1 && in_array( $post_id, $chapters, true ) ) {
			$story = get_post( $story_id );

			// If story is currently published, invalidate it
			if ( $story && $story->post_status === 'publish' ) {
				/**
				 * Fires when the last chapter is being deleted from a published story.
				 *
				 * This hook is used to trigger JavaScript notifications to the user.
				 *
				 * @since 1.0.0
				 * @param int $story_id The story post ID.
				 * @param int $chapter_id The chapter post ID being deleted.
				 */
				do_action( 'fanfic_last_chapter_deleted', $story_id, $post_id );

				// Store a transient to show notification on next page load
				set_transient( 'fanfic_story_invalidated_' . get_current_user_id() . '_' . $story_id, true, 60 );
			}
		}
	}

	/**
	 * Handle taxonomy updates and revalidate story.
	 *
	 * @since 1.0.0
	 * @param int    $object_id  The object ID.
	 * @param array  $terms      An array of object terms.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   The taxonomy slug.
	 * @param bool   $append     Whether to append new terms or replace existing.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 */
	public static function handle_taxonomy_update( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		// Only handle fanfiction taxonomies
		if ( ! in_array( $taxonomy, array( 'fanfiction_genre', 'fanfiction_status' ), true ) ) {
			return;
		}

		// Verify this is a fanfiction story
		if ( get_post_type( $object_id ) !== 'fanfiction_story' ) {
			return;
		}

		// Revalidate the story
		self::validate_and_update_status( $object_id );
	}

	/**
	 * Get a human-readable validation summary for a story.
	 *
	 * Useful for displaying validation status in admin interfaces.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return string HTML formatted validation summary.
	 */
	public static function get_validation_summary( $story_id ) {
		$is_valid = self::is_story_valid( $story_id );

		if ( $is_valid ) {
			return '<span class="fanfic-validation-status valid">' .
				   esc_html__( 'Story is valid and can be published.', 'fanfiction-manager' ) .
				   '</span>';
		}

		$errors = self::get_validation_errors( $story_id );
		$output = '<span class="fanfic-validation-status invalid">' .
				  esc_html__( 'Story cannot be published:', 'fanfiction-manager' ) .
				  '</span><ul class="fanfic-validation-errors">';

		foreach ( $errors as $error ) {
			$output .= '<li>' . esc_html( $error ) . '</li>';
		}

		$output .= '</ul>';

		return $output;
	}
}
