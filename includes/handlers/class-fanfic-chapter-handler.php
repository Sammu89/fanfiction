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
	 * Registration flag to prevent duplicate registration
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Check whether the current user can manually edit publication dates.
	 *
	 * Allowed: fanfiction_author, fanfiction_admin, WordPress administrator.
	 * Disallowed: fanfiction_moderator-only users.
	 *
	 * @since 2.1.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function user_can_edit_publish_date( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = (array) $user->roles;
		return in_array( 'administrator', $roles, true )
			|| in_array( 'fanfiction_admin', $roles, true )
			|| in_array( 'fanfiction_author', $roles, true );
	}

	/**
	 * Parse and validate a YYYY-MM-DD publication date input.
	 *
	 * @since 2.1.0
	 * @param string $raw_date Raw request value.
	 * @param array  $errors Errors array by reference.
	 * @return array|null|false Array with local/gmt values, null for empty, false for invalid.
	 */
	private static function parse_publication_date_input( $raw_date, &$errors ) {
		$raw_date = trim( (string) $raw_date );
		if ( '' === $raw_date ) {
			return null;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
			$errors[] = __( 'Publication date format is invalid.', 'fanfiction-manager' );
			return false;
		}

		$timezone = wp_timezone();
		$date_obj = DateTime::createFromFormat( '!Y-m-d', $raw_date, $timezone );
		if ( ! $date_obj ) {
			$errors[] = __( 'Publication date is invalid.', 'fanfiction-manager' );
			return false;
		}

		$date_errors = DateTime::getLastErrors();
		if ( ( $date_errors && ! empty( $date_errors['warning_count'] ) ) || ( $date_errors && ! empty( $date_errors['error_count'] ) ) ) {
			$errors[] = __( 'Publication date is invalid.', 'fanfiction-manager' );
			return false;
		}

		$local_datetime = $date_obj->format( 'Y-m-d 00:00:00' );
		$local_ts       = strtotime( $local_datetime );
		if ( false === $local_ts ) {
			$errors[] = __( 'Publication date is invalid.', 'fanfiction-manager' );
			return false;
		}

		if ( $local_ts > current_time( 'timestamp' ) ) {
			$errors[] = __( 'Publication date cannot be in the future.', 'fanfiction-manager' );
			return false;
		}

		return array(
			'local' => $local_datetime,
			'gmt'   => get_gmt_from_date( $local_datetime ),
		);
	}

	/**
	 * Check whether content changed significantly (same threshold as search index logic).
	 *
	 * @since 2.1.0
	 * @param string $old_content Previous content.
	 * @param string $new_content New content.
	 * @return bool
	 */
	private static function is_content_significantly_changed( $old_content, $new_content ) {
		$old_content = trim( preg_replace( '/\s+/', ' ', (string) $old_content ) );
		$new_content = trim( preg_replace( '/\s+/', ' ', (string) $new_content ) );

		if ( '' === $old_content || '' === $new_content ) {
			return true;
		}

		if ( $old_content === $new_content ) {
			return false;
		}

		$old_len = strlen( $old_content );
		$new_len = strlen( $new_content );

		if ( $old_len < 5000 && $new_len < 5000 ) {
			similar_text( $old_content, $new_content, $percent );
			return $percent < 90;
		}

		$max_len = max( $old_len, $new_len );
		$diff    = abs( $old_len - $new_len );
		$change_percent = ( $diff / $max_len ) * 100;
		return $change_percent >= 10;
	}

	/**
	 * Register chapter handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Prevent duplicate registration
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

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
		$is_ajax = wp_doing_ajax();

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_chapter_nonce'], 'fanfic_create_chapter_action_' . $story_id ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'Security check failed.', 'fanfiction-manager' ),
					'errors'  => array( __( 'Security check failed.', 'fanfiction-manager' ) ),
				) );
			}
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'You must be logged in to create chapters.', 'fanfiction-manager' ),
					'errors'  => array( __( 'You must be logged in to create chapters.', 'fanfiction-manager' ) ),
				) );
			}
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || 'fanfiction_story' !== $story->post_type || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'You do not have permission to create chapters for this story.', 'fanfiction-manager' ),
					'errors'  => array( __( 'You do not have permission to create chapters for this story.', 'fanfiction-manager' ) ),
				) );
			}
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
		$publish_date_raw = isset( $_POST['fanfic_chapter_publish_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fanfic_chapter_publish_date'] ) ) : '';
		$publish_date_data = null;
		if ( '' !== $publish_date_raw && self::user_can_edit_publish_date( get_current_user_id() ) ) {
			$publish_date_data = self::parse_publication_date_input( $publish_date_raw, $errors );
		}

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

		// Note: Chapter title is optional - chapters can have no title
		// and will display as "Prologue", "Chapter X", "Epilogue" instead

		if ( empty( $content ) ) {
			$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				) );
			}
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Create chapter as draft first if publishing, so we can validate
		$initial_status = ( 'publish' === $chapter_status ) ? 'draft' : $chapter_status;
		$insert_data = array(
			'post_type'    => 'fanfiction_chapter',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $initial_status,
			'post_author'  => $current_user->ID,
			'post_parent'  => $story_id,
		);
		if ( is_array( $publish_date_data ) ) {
			$insert_data['post_date'] = $publish_date_data['local'];
			$insert_data['post_date_gmt'] = $publish_date_data['gmt'];
		}

		$chapter_id = wp_insert_post( $insert_data );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Chapter created with ID: ' . $chapter_id . ', Initial Status: ' . $initial_status );
		}

		if ( is_wp_error( $chapter_id ) ) {
			$errors[] = $chapter_id->get_error_message();
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				) );
			}
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Set chapter metadata
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );

		// If user wants to publish, validate chapter first
		if ( 'publish' === $chapter_status ) {
			$validation = Fanfic_Validation::can_publish_chapter( $chapter_id );

			if ( ! $validation['can_publish'] ) {
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => __( 'Chapter could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ),
						'errors'  => array_values( $validation['missing_fields'] ),
					) );
				}
				// Validation failed - keep as draft, show errors
				$validation_errors = array_values( $validation['missing_fields'] );
				set_transient( 'fanfic_chapter_validation_errors_' . $current_user->ID . '_' . $chapter_id, $validation_errors, 60 );
				Fanfic_Flash_Messages::add_message( 'error', __( 'Chapter could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ) );

				// Redirect to chapter edit page
				$chapter_url = get_permalink( $chapter_id );
				$edit_url = add_query_arg( 'action', 'edit', $chapter_url );
				wp_redirect( $edit_url );
				exit;
			}

			// Validation passed - now publish
			wp_update_post( array(
				'ID'          => $chapter_id,
				'post_status' => 'publish',
			) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Chapter ' . $chapter_id . ' published after validation' );
			}
		}

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

		// Add message if this is first published chapter
		if ( $is_first_published_chapter && ! $is_ajax ) {
			Fanfic_Flash_Messages::add_message( 'info', __( 'This is your first published chapter! Consider publishing your story now.', 'fanfiction-manager' ) );
		}

		// Check if this is an AJAX request
		if ( wp_doing_ajax() ) {
			// Return JSON response for AJAX
			$chapter_edit_url = add_query_arg( 'action', 'edit', get_permalink( $chapter_id ) );
			wp_send_json_success( array(
				'message'            => __( 'Chapter saved successfully.', 'fanfiction-manager' ),
				'redirect_url'       => $edit_url,
				'edit_url'           => $chapter_edit_url,
				'chapter_id'         => $chapter_id,
				'chapter_type'       => $chapter_type,
				'chapter_number' => $chapter_number,
				'chapter_status' => get_post_status( $chapter_id ),
				'form_mode'          => 'edit',
				'edit_nonce'         => wp_create_nonce( 'fanfic_edit_chapter_action_' . $chapter_id ),
				'is_first_published_chapter' => $is_first_published_chapter,
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
		$is_ajax = wp_doing_ajax();

		$chapter_id = isset( $_POST['fanfic_chapter_id'] ) ? absint( $_POST['fanfic_chapter_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_chapter_nonce'], 'fanfic_edit_chapter_action_' . $chapter_id ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'Security check failed.', 'fanfiction-manager' ),
					'errors'  => array( __( 'Security check failed.', 'fanfiction-manager' ) ),
				) );
			}
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'You must be logged in to edit chapters.', 'fanfiction-manager' ),
					'errors'  => array( __( 'You must be logged in to edit chapters.', 'fanfiction-manager' ) ),
				) );
			}
			return;
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );
		$story_id = $chapter ? (int) $chapter->post_parent : 0;

		// Check permissions
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type || $story_id <= 0 || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'You do not have permission to edit this chapter.', 'fanfiction-manager' ),
					'errors'  => array( __( 'You do not have permission to edit this chapter.', 'fanfiction-manager' ) ),
				) );
			}
			return;
		}

		$errors = array();

		// Get and sanitize form data
		$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
		$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
		$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
		$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
		$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';
		$publish_date_raw = isset( $_POST['fanfic_chapter_publish_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fanfic_chapter_publish_date'] ) ) : '';
		$publish_date_data = null;
		if ( '' !== $publish_date_raw && self::user_can_edit_publish_date( get_current_user_id() ) ) {
			$publish_date_data = self::parse_publication_date_input( $publish_date_raw, $errors );
		}

		// Get story ID from chapter
		$story_id = $chapter->post_parent;
		$existing_content_updated_date = (string) get_post_meta( $story_id, '_fanfic_content_updated_date', true );
		$current_publish_date = isset( $chapter->post_date ) ? substr( (string) $chapter->post_date, 0, 10 ) : '';
		$new_publish_date = is_array( $publish_date_data ) ? substr( (string) $publish_date_data['local'], 0, 10 ) : $current_publish_date;
		$publish_date_changed = is_array( $publish_date_data ) && $new_publish_date !== $current_publish_date;
		$content_unchanged = ( (string) $chapter->post_content === (string) $content );
		$preserve_content_updated_meta = $publish_date_changed && $content_unchanged && '' !== $existing_content_updated_date;
		$is_forward_publish_date_change = false;
		if ( is_array( $publish_date_data ) ) {
			$current_publish_ts = strtotime( (string) $chapter->post_date );
			$new_publish_ts = strtotime( (string) $publish_date_data['local'] );
			if ( false !== $current_publish_ts && false !== $new_publish_ts ) {
				$is_forward_publish_date_change = $new_publish_ts > $current_publish_ts;
			}
		}

		// Anti-cheat validation: forward date changes require qualifying chapter content update.
		if ( $is_forward_publish_date_change && ! self::is_content_significantly_changed( (string) $chapter->post_content, (string) $content ) ) {
			$errors[] = __( 'To set a newer publication date, first make a substantial update to the chapter content. Date-only changes are not allowed.', 'fanfiction-manager' );
		}

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

		// Note: Chapter title is optional - chapters can have no title
		// and will display as "Prologue", "Chapter X", "Epilogue" instead

		if ( empty( $content ) ) {
			$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				) );
			}
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Get old status BEFORE updating
		$old_status = get_post_status( $chapter_id );
		$story = get_post( $story_id );

		// Update chapter - keep as draft if trying to publish, so we can validate first
		$update_status = ( 'publish' === $chapter_status && 'publish' !== $old_status ) ? 'draft' : $chapter_status;
		$update_data = array(
			'ID'           => $chapter_id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $update_status,
			'edit_date'    => true,
		);
		if ( is_array( $publish_date_data ) ) {
			$update_data['post_date'] = $publish_date_data['local'];
			$update_data['post_date_gmt'] = $publish_date_data['gmt'];
		}

		$result = wp_update_post( $update_data );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				) );
			}
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Update chapter metadata
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );

		// If user wants to publish (and it wasn't already published), validate chapter first
		if ( 'publish' === $chapter_status && 'publish' !== $old_status ) {
			$validation = Fanfic_Validation::can_publish_chapter( $chapter_id );

			if ( ! $validation['can_publish'] ) {
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => __( 'Chapter could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ),
						'errors'  => array_values( $validation['missing_fields'] ),
					) );
				}
				// Validation failed - keep as draft, show errors
				$validation_errors = array_values( $validation['missing_fields'] );
				set_transient( 'fanfic_chapter_validation_errors_' . $current_user->ID . '_' . $chapter_id, $validation_errors, 60 );
				Fanfic_Flash_Messages::add_message( 'error', __( 'Chapter could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ) );

				// Redirect back with validation error
				$chapter_url = get_permalink( $chapter_id );
				$edit_url = add_query_arg( 'action', 'edit', $chapter_url );
				wp_redirect( $edit_url );
				exit;
			}

			// Validation passed - now publish
			wp_update_post( array(
				'ID'          => $chapter_id,
				'post_status' => 'publish',
			) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Chapter ' . $chapter_id . ' published after validation' );
			}
		}

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

		if ( ! $is_ajax ) {
			// Add success message
			Fanfic_Flash_Messages::add_message( 'success', __( 'Chapter updated successfully!', 'fanfiction-manager' ) );

			// Add warning message if story was auto-drafted
			if ( $was_story_auto_drafted ) {
				Fanfic_Flash_Messages::add_message( 'warning', __( 'Story was automatically set to draft status because this was its last published chapter/prologue.', 'fanfiction-manager' ) );
			}

			// Add info message if this is first published chapter
			if ( $is_first_published_chapter ) {
				Fanfic_Flash_Messages::add_message( 'info', __( 'This is your first published chapter! Consider publishing your story now.', 'fanfiction-manager' ) );
			}
		}

		// Anti-cheat guard: chapter publish date edits must not count as content updates.
		if ( $preserve_content_updated_meta ) {
			update_post_meta( $story_id, '_fanfic_content_updated_date', $existing_content_updated_date );
		}

		if ( $is_ajax ) {
			$edit_url = add_query_arg( 'action', 'edit', get_permalink( $chapter_id ) );
			wp_send_json_success( array(
				'message' => __( 'Chapter updated successfully!', 'fanfiction-manager' ),
				'chapter_id' => $chapter_id,
				'chapter_type' => $chapter_type,
				'chapter_number' => $chapter_number,
				'chapter_status' => get_post_status( $chapter_id ),
				'story_auto_drafted' => $was_story_auto_drafted,
				'story_id' => $story_id,
				'redirect_url' => $edit_url,
				'edit_url' => $edit_url,
				'form_mode' => 'edit',
				'edit_nonce' => wp_create_nonce( 'fanfic_edit_chapter_action_' . $chapter_id ),
				'is_first_published_chapter' => $is_first_published_chapter,
			) );
		}

		wp_redirect( wp_get_referer() );
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
		$story_id = $chapter ? (int) $chapter->post_parent : 0;

		// Check permissions
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type || $story_id <= 0 || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			return;
		}

		// Get page URL helper - need to access Story Handler
		require_once plugin_dir_path( __FILE__ ) . 'class-fanfic-story-handler.php';

		// Delete the chapter
		wp_delete_post( $chapter_id, true );

		// Add success message
		Fanfic_Flash_Messages::add_message( 'success', __( 'Chapter deleted successfully.', 'fanfiction-manager' ) );

		// Redirect to manage stories
		$redirect_url = Fanfic_Story_Handler::get_page_url_with_fallback( 'manage-stories' );
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

		// Check permissions - accepted co-authors can manage chapters as well.
		if ( ! current_user_can( 'edit_fanfiction_story', $story->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this chapter.', 'fanfiction-manager' ) ) );
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Chapter Delete AJAX: Starting deletion for chapter ID ' . $chapter_id . ' (Story ID: ' . $story->ID . ')' );
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

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Chapter Delete AJAX: Auto-drafted story ' . $story->ID . ' because last chapter/prologue was deleted' );
				}
			}
		}

		// Delete the chapter
		$result = wp_delete_post( $chapter_id, true );

		// Check for errors
		if ( ! $result || is_wp_error( $result ) ) {
			$error_message = is_wp_error( $result ) ? $result->get_error_message() : __( 'wp_delete_post returned false', 'fanfiction-manager' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Chapter Delete AJAX: Failed to delete chapter ' . $chapter_id . ': ' . $error_message );
			}
			wp_send_json_error( array(
				'message' => sprintf( __( 'Failed to delete chapter: %s', 'fanfiction-manager' ), $error_message )
			) );
		}

		// Verify the post was actually deleted
		$verify = get_post( $chapter_id );
		if ( $verify && 'trash' !== $verify->post_status ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Chapter Delete AJAX: Chapter still exists after delete attempt. Status: ' . $verify->post_status );
			}
			wp_send_json_error( array(
				'message' => __( 'Delete operation completed but chapter still exists in database.', 'fanfiction-manager' )
			) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Chapter Delete AJAX: Successfully deleted chapter ' . $chapter_id );
		}

		wp_send_json_success( array(
			'message' => __( 'Chapter deleted successfully.', 'fanfiction-manager' ),
			'chapter_id' => $chapter_id,
			'story_auto_drafted' => $is_last_publishable_chapter,
		) );
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

		// Use centralized function to check if removing this chapter will auto-draft the story
		$will_auto_draft = Fanfic_Validation::will_story_auto_draft_if_chapter_removed( $chapter_id );

		wp_send_json_success( array(
			'is_last_chapter' => $will_auto_draft,
			'chapter_type'    => get_post_meta( $chapter_id, '_fanfic_chapter_type', true ),
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
