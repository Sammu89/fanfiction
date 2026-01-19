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
		// Handle chapter deletion/unpublish to auto-draft story if it becomes invalid
		add_action( 'before_delete_post', array( __CLASS__, 'handle_chapter_deletion' ), 10, 1 );
		add_action( 'transition_post_status', array( __CLASS__, 'handle_chapter_status_change' ), 10, 3 );
	}

	/**
	 * Check if a story can be published.
	 *
	 * A story can be published when it has:
	 * 1. A title
	 * 2. An introduction (excerpt field is not empty)
	 * 3. At least one published chapter or prologue (epilogues don't count)
	 * 4. At least one genre assigned
	 * 5. At least one status assigned
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return array Array with 'can_publish' (bool) and 'missing_fields' (array of field => message pairs).
	 */
	public static function can_publish_story( $story_id ) {
		$missing_fields = array();

		// Verify this is a fanfiction story
		if ( get_post_type( $story_id ) !== 'fanfiction_story' ) {
			return array(
				'can_publish'     => false,
				'missing_fields'  => array(
					'invalid_post_type' => __( 'Invalid content type.', 'fanfiction-manager' )
				),
			);
		}

		$story = get_post( $story_id );

		// Check title
		if ( ! $story || empty( trim( $story->post_title ) ) ) {
			$missing_fields['title'] = __( 'Your story must have a title.', 'fanfiction-manager' );
		}

		// Check excerpt
		if ( ! self::check_story_excerpt( $story_id ) ) {
			$missing_fields['excerpt'] = __( 'Your story must have an introduction.', 'fanfiction-manager' );
		}

		// Check published chapters
		if ( ! self::check_story_published_chapters( $story_id ) ) {
			$missing_fields['published_chapters'] = __( 'Your story must have at least one published chapter or prologue.', 'fanfiction-manager' );
		}

		// Check genre
		$genres = wp_get_post_terms( $story_id, 'fanfiction_genre', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $genres ) || empty( $genres ) ) {
			$missing_fields['genre'] = __( 'Your story must be classified in at least one genre.', 'fanfiction-manager' );
		}

		// Check status
		$statuses = wp_get_post_terms( $story_id, 'fanfiction_status', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $statuses ) || empty( $statuses ) ) {
			$missing_fields['status'] = __( 'Your story must have a status set (Ongoing, Completed, etc.).', 'fanfiction-manager' );
		}

		return array(
			'can_publish'    => empty( $missing_fields ),
			'missing_fields' => $missing_fields,
		);
	}

	/**
	 * Check if a chapter can be published.
	 *
	 * A chapter can be published when it has:
	 * 1. A title
	 * 2. Content
	 * 3. A parent story
	 * 4. A type (prologue, chapter, or epilogue)
	 * 5. If type is chapter: a number that's not equal to other chapters of the same story
	 *
	 * @since 1.0.0
	 * @param int $chapter_id The chapter post ID.
	 * @return array Array with 'can_publish' (bool) and 'missing_fields' (array of field => message pairs).
	 */
	public static function can_publish_chapter( $chapter_id ) {
		$missing_fields = array();

		// Verify this is a fanfiction chapter
		if ( get_post_type( $chapter_id ) !== 'fanfiction_chapter' ) {
			return array(
				'can_publish'     => false,
				'missing_fields'  => array(
					'invalid_post_type' => __( 'Invalid content type.', 'fanfiction-manager' )
				),
			);
		}

		$chapter = get_post( $chapter_id );

		// Check title - NO LONGER REQUIRED FOR CHAPTERS
		// if ( ! $chapter || empty( trim( $chapter->post_title ) ) ) {
		//	$missing_fields['title'] = __( 'Your chapter must have a title.', 'fanfiction-manager' );
		// }

		// Check content
		if ( ! $chapter || empty( trim( strip_tags( $chapter->post_content ) ) ) ) {
			$missing_fields['content'] = __( 'Your chapter must have content.', 'fanfiction-manager' );
		}

		// Check parent story
		if ( ! $chapter || empty( $chapter->post_parent ) ) {
			$missing_fields['parent_story'] = __( 'This chapter must be attached to a story.', 'fanfiction-manager' );
		}

		// Check chapter type
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		if ( empty( $chapter_type ) ) {
			$missing_fields['chapter_type'] = __( 'Your chapter must be classified as a Chapter, Prologue, or Epilogue.', 'fanfiction-manager' );
		} elseif ( 'chapter' === $chapter_type ) {
			// For regular chapters, check chapter number
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			if ( empty( $chapter_number ) || $chapter_number < 1 ) {
				$missing_fields['chapter_number'] = __( 'Your chapter must have a number assigned.', 'fanfiction-manager' );
			} else {
				// Check if another chapter has the same number
				$duplicate_chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $chapter->post_parent,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post__not_in'   => array( $chapter_id ),
					'meta_query'     => array(
						array(
							'key'   => '_fanfic_chapter_number',
							'value' => $chapter_number,
						),
					),
				) );

				if ( ! empty( $duplicate_chapters ) ) {
					$missing_fields['duplicate_chapter_number'] = __( 'Another chapter in this story already has this number.', 'fanfiction-manager' );
				}
			}
		}

		return array(
			'can_publish'    => empty( $missing_fields ),
			'missing_fields' => $missing_fields,
		);
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
			error_log( 'check_story_excerpt: Story not found for ID ' . $story_id );
			return false;
		}

		$excerpt = trim( $story->post_excerpt );
		error_log( 'check_story_excerpt: Story ID ' . $story_id . ', excerpt length: ' . strlen( $excerpt ) . ', has excerpt: ' . ( ! empty( $excerpt ) ? 'YES' : 'NO' ) );
		error_log( 'check_story_excerpt: Excerpt content (first 50 chars): ' . substr( $excerpt, 0, 50 ) );
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
	 * Check if story has at least one published chapter or prologue.
	 *
	 * Epilogues don't count for this validation - a story must have
	 * at least one published prologue or chapter to be publishable.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return bool True if at least one published chapter/prologue exists, false otherwise.
	 */
	public static function check_story_published_chapters( $story_id ) {
		$chapters = get_posts(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
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
	 * Handle chapter deletion and auto-draft story if it becomes invalid.
	 *
	 * When the last chapter or prologue is deleted, automatically set the story to draft
	 * if it becomes invalid (no published chapters remaining).
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
		$story = get_post( $story_id );

		if ( ! $story || $story->post_status !== 'publish' ) {
			return;
		}

		// Check if story will have any chapters left after deletion
		// (excluding the one being deleted)
		$remaining_chapters = get_children(
			array(
				'post_parent'    => $story_id,
				'post_type'      => 'fanfiction_chapter',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'exclude'        => array( $post_id ),
			)
		);

		// If no chapters will remain, auto-draft the story
		if ( empty( $remaining_chapters ) ) {
			wp_update_post(
				array(
					'ID'          => $story_id,
					'post_status' => 'draft',
				)
			);

			/**
			 * Fires when a story is auto-drafted due to chapter deletion.
			 *
			 * @since 1.0.0
			 * @param int $story_id The story post ID.
			 * @param int $chapter_id The chapter post ID being deleted.
			 */
			do_action( 'fanfic_story_auto_drafted_on_chapter_delete', $story_id, $post_id );
		}
	}

	/**
	 * Handle chapter status changes (e.g., unpublishing a chapter).
	 *
	 * When a chapter is unpublished, check if the story becomes invalid and auto-draft it.
	 *
	 * @since 1.0.0
	 * @param string  $new_status The new post status.
	 * @param string  $old_status The old post status.
	 * @param WP_Post $post       The post object.
	 */
	public static function handle_chapter_status_change( $new_status, $old_status, $post ) {
		// Only care about chapters
		if ( $post->post_type !== 'fanfiction_chapter' ) {
			return;
		}

		// Only care if a chapter is being unpublished
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$story_id = $post->post_parent;
			$story = get_post( $story_id );

			if ( ! $story || $story->post_status !== 'publish' ) {
				return;
			}

			// Check if story still has any published chapters
			$published_chapters = get_posts(
				array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			// If no published chapters remain, auto-draft the story
			if ( empty( $published_chapters ) ) {
				wp_update_post(
					array(
						'ID'          => $story_id,
						'post_status' => 'draft',
					)
				);

				/**
				 * Fires when a story is auto-drafted due to chapter unpublishing.
				 *
				 * @since 1.0.0
				 * @param int $story_id The story post ID.
				 * @param int $chapter_id The chapter post ID being unpublished.
				 */
				do_action( 'fanfic_story_auto_drafted_on_chapter_unpublish', $story_id, $post->ID );
			}
		}
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

	/**
	 * Check if removing a specific chapter will cause the story to auto-draft.
	 *
	 * This centralizes the logic for determining whether deleting or unpublishing
	 * a chapter will trigger auto-drafting of its parent story.
	 *
	 * @since 1.0.8
	 * @param int $chapter_id The chapter post ID to check.
	 * @return bool True if removing this chapter will auto-draft the story, false otherwise.
	 */
	public static function will_story_auto_draft_if_chapter_removed( $chapter_id ) {
		$chapter = get_post( $chapter_id );

		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return false;
		}

		$story = get_post( $chapter->post_parent );

		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return false;
		}

		// If story is already draft, no change will occur
		if ( 'draft' === $story->post_status ) {
			return false;
		}

		// Check chapter type - only chapters/prologues matter for auto-draft
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );

		// Epilogues don't count - story can exist with only epilogues in draft
		if ( ! in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			return false;
		}

		// If chapter is already draft, removing it won't change story status
		if ( 'draft' === $chapter->post_status ) {
			return false;
		}

		// Count OTHER published chapters/prologues (excluding this one)
		$other_published_chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story->ID,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
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

		// If no other published chapters/prologues exist, story will auto-draft
		return empty( $other_published_chapters );
	}

	/**
	 * Check if a story just became publishable after publishing a chapter.
	 *
	 * This checks if the story was previously in draft and is now valid
	 * for publishing, typically called after publishing a chapter.
	 *
	 * @since 1.0.8
	 * @param int $story_id The story post ID.
	 * @return bool True if story was draft and is now publishable, false otherwise.
	 */
	public static function did_story_become_publishable( $story_id ) {
		$story = get_post( $story_id );

		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return false;
		}

		// Only relevant if story is currently draft
		if ( 'draft' !== $story->post_status ) {
			return false;
		}

		// Check if story is now valid for publishing
		$validation = self::can_publish_story( $story_id );

		return $validation['can_publish'];
	}
}
