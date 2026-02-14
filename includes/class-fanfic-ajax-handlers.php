<?php
/**
 * AJAX Handlers Class
 *
 * Centralized AJAX endpoint registration and handling for all user interactions.
 * Uses Fanfic_AJAX_Security wrapper for automatic security, rate limiting, and logging.
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_AJAX_Handlers
 *
 * Registers and handles all AJAX endpoints for user interactions including:
 * - Chapter ratings (anonymous + authenticated)
 * - Chapter likes (anonymous + authenticated)
 * - Reading progress (authenticated only)
 * - Story/chapter follows (anonymous + authenticated)
 * - Email subscriptions (anonymous + authenticated)
 * - Batch stats loading
 *
 * @since 1.0.15
 */
class Fanfic_AJAX_Handlers {

	/**
	 * Initialize AJAX handlers
	 *
	 * Registers all AJAX endpoints with security wrapper.
	 *
	 * @since 1.0.15
	 * @return void
	 */
	public static function init() {
		// Reading progress (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_mark_as_read',
			array( __CLASS__, 'ajax_mark_as_read' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Follow endpoints (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_follow',
			array( __CLASS__, 'ajax_toggle_follow' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Legacy: email subscription handler removed in 1.8.0.
		// Email subscription is now handled via the follow toggle with optional email param.

		// Batch stats loading (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_get_chapter_stats',
			array( __CLASS__, 'ajax_get_chapter_stats' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Unified interactions (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_record_interaction',
			array( __CLASS__, 'ajax_record_interaction' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// View tracking (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_record_view',
			array( __CLASS__, 'ajax_record_view' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Sync localStorage interactions on login (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_sync_interactions',
			array( __CLASS__, 'ajax_sync_interactions' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Browse/search results (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_search',
			array( __CLASS__, 'ajax_stories_search' ),
			false, // Allow anonymous
			array(
				'rate_limit' => true,
				'capability' => 'read',
			)
		);

		// Notification endpoints (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_delete_notification',
			array( __CLASS__, 'ajax_delete_notification' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_get_notifications',
			array( __CLASS__, 'ajax_get_notifications' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Follow pagination (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_load_user_follows',
			array( __CLASS__, 'ajax_load_user_follows' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Get read status (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_get_read_status',
			array( __CLASS__, 'ajax_get_read_status' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Unmark as read (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_unmark_as_read',
			array( __CLASS__, 'ajax_unmark_as_read' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Mark notification as read (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_mark_notification_read',
			array( __CLASS__, 'ajax_mark_notification_read' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Mark all notifications as read (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_mark_all_notifications_read',
			array( __CLASS__, 'ajax_mark_all_notifications_read' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Get unread notification count (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_get_unread_count',
			array( __CLASS__, 'ajax_get_unread_count' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Report content (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_report_content',
			array( __CLASS__, 'ajax_report_content' ),
			false, // Allow anonymous (with stricter rate limits)
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// AJAX Image Upload (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_ajax_image_upload',
			array( __CLASS__, 'ajax_image_upload' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'upload_files', // Users must have upload capability
			)
		);

		// Admin: Bulk change story author
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_bulk_change_author',
			array( __CLASS__, 'ajax_bulk_change_author' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'edit_others_posts',
			)
		);

		// Admin: Bulk apply genre
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_bulk_apply_genre',
			array( __CLASS__, 'ajax_bulk_apply_genre' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'edit_others_posts',
			)
		);

		// Admin: Bulk change story status
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_bulk_change_status',
			array( __CLASS__, 'ajax_bulk_change_status' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'edit_others_posts',
			)
		);
	}

	/**
	 * AJAX: Bulk change the author for multiple stories.
	 *
	 * @since 1.2.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_bulk_change_author() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'nonce', 'story_ids', 'new_author_id' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( $params->get_error_code(), $params->get_error_message(), 400 );
		}

		// Verify nonce
		$nonce = sanitize_text_field( $params['nonce'] );
		if ( ! wp_verify_nonce( $nonce, 'fanfic_bulk_change_author_nonce' ) && ! wp_verify_nonce( $nonce, 'fanfic_bulk_change_author' ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_nonce', __( 'Security check failed.', 'fanfiction-manager' ), 403 );
		}

		$story_ids = is_array( $params['story_ids'] ) ? array_map( 'absint', $params['story_ids'] ) : array();
		$new_author_id = absint( $params['new_author_id'] );
		$moderator_id = get_current_user_id();

		if ( empty( $story_ids ) ) {
			Fanfic_AJAX_Security::send_error_response( 'no_stories', __( 'No stories were selected.', 'fanfiction-manager' ), 400 );
		}

		// Verify the new author is a valid user
		$new_author = get_user_by( 'id', $new_author_id );
		if ( ! $new_author ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_user', __( 'The selected new author is not a valid user.', 'fanfiction-manager' ), 404 );
		}

		$updated_count = 0;
		$errors = array();

		foreach ( $story_ids as $story_id ) {
			$story = get_post( $story_id );
			if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
				$errors[] = sprintf( 'Story ID %d not found.', $story_id );
				continue;
			}

			$original_author_id = absint( $story->post_author );

			// Don't do anything if the author is already correct
			if ( $original_author_id === $new_author_id ) {
				$updated_count++;
				continue;
			}

			$result = wp_update_post( array(
				'ID' => $story_id,
				'post_author' => $new_author_id,
			), true );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( 'Failed to update story %d: %s', $story_id, $result->get_error_message() );
			} else {
				// Add to audit log
				$log_entry = array(
					'timestamp'      => current_time( 'timestamp', true ),
					'moderator_id'   => $moderator_id,
					'original_author_id' => $original_author_id,
					'new_author_id'  => $new_author_id,
				);
				add_post_meta( $story_id, '_fanfic_author_change_log', $log_entry );
				$updated_count++;
			}
		}

		if ( $updated_count > 0 && empty( $errors ) ) {
			Fanfic_AJAX_Security::send_success_response(
				array( 'updated_count' => $updated_count ),
				sprintf(
					/* translators: %d: number of stories updated. */
					_n( '%d story has been successfully updated.', '%d stories have been successfully updated.', $updated_count, 'fanfiction-manager' ),
					$updated_count
				)
			);
		} else {
			$error_message = sprintf( 'Processed %d stories with %d errors. ', $updated_count, count( $errors ) );
			$error_message .= implode( ' ', $errors );
			Fanfic_AJAX_Security::send_error_response( 'bulk_update_errors', $error_message, 500 );
		}
	}

	/**
	 * AJAX: Bulk apply a genre to multiple stories.
	 *
	 * @since 1.2.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_bulk_apply_genre() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'nonce', 'story_ids' ),
			array( 'genre_id', 'genre_ids' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( $params->get_error_code(), $params->get_error_message(), 400 );
		}

		// Verify nonce
		$nonce = sanitize_text_field( $params['nonce'] );
		if ( ! wp_verify_nonce( $nonce, 'fanfic_bulk_apply_genre_nonce' ) && ! wp_verify_nonce( $nonce, 'fanfic_bulk_apply_genre' ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_nonce', __( 'Security check failed.', 'fanfiction-manager' ), 403 );
		}

		$story_ids = is_array( $params['story_ids'] ) ? array_map( 'absint', $params['story_ids'] ) : array();
		$genre_ids = array();
		if ( ! empty( $params['genre_ids'] ) && is_array( $params['genre_ids'] ) ) {
			$genre_ids = array_filter( array_map( 'absint', $params['genre_ids'] ) );
		} elseif ( ! empty( $params['genre_id'] ) ) {
			$genre_ids = array( absint( $params['genre_id'] ) );
		}

		if ( empty( $story_ids ) ) {
			Fanfic_AJAX_Security::send_error_response( 'no_stories', __( 'No stories were selected.', 'fanfiction-manager' ), 400 );
		}

		if ( empty( $genre_ids ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_genre', __( 'Please select at least one genre.', 'fanfiction-manager' ), 400 );
		}

		$genres = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
			'include'    => $genre_ids,
		) );

		if ( empty( $genres ) || is_wp_error( $genres ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_genre', __( 'The selected genre is not valid.', 'fanfiction-manager' ), 404 );
		}

		$updated_count = 0;
		foreach ( $story_ids as $story_id ) {
			// true for append
			wp_set_post_terms( $story_id, $genre_ids, 'fanfiction_genre', true );
			$updated_count++;
		}

		$genre_names = implode( ', ', wp_list_pluck( $genres, 'name' ) );

		Fanfic_AJAX_Security::send_success_response(
			array( 'updated_count' => $updated_count ),
			sprintf(
				/* translators: %1$s: genre names, %2$d: number of stories. */
				_n( 'Genre "%1$s" has been applied to %2$d story.', 'Genres "%1$s" have been applied to %2$d stories.', $updated_count, 'fanfiction-manager' ),
				$genre_names,
				$updated_count
			)
		);
	}

	/**
	 * AJAX: Bulk change the status for multiple stories.
	 *
	 * @since 1.2.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_bulk_change_status() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'nonce', 'story_ids', 'status_id' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( $params->get_error_code(), $params->get_error_message(), 400 );
		}

		// Verify nonce
		$nonce = sanitize_text_field( $params['nonce'] );
		if ( ! wp_verify_nonce( $nonce, 'fanfic_bulk_change_status_nonce' ) && ! wp_verify_nonce( $nonce, 'fanfic_bulk_change_status' ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_nonce', __( 'Security check failed.', 'fanfiction-manager' ), 403 );
		}

		$story_ids = is_array( $params['story_ids'] ) ? array_map( 'absint', $params['story_ids'] ) : array();
		$status_id = absint( $params['status_id'] );

		if ( empty( $story_ids ) ) {
			Fanfic_AJAX_Security::send_error_response( 'no_stories', __( 'No stories were selected.', 'fanfiction-manager' ), 400 );
		}

		// Verify the status exists
		$status = get_term( $status_id, 'fanfiction_status' );
		if ( ! $status || is_wp_error( $status ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_status', __( 'The selected status is not valid.', 'fanfiction-manager' ), 404 );
		}

		$updated_count = 0;
		foreach ( $story_ids as $story_id ) {
			// false for append, to overwrite existing status
			wp_set_post_terms( $story_id, $status_id, 'fanfiction_status', false );
			$updated_count++;
		}

		Fanfic_AJAX_Security::send_success_response(
			array( 'updated_count' => $updated_count ),
			sprintf(
				/* translators: %1$s: status name, %2$d: number of stories. */
				_n( 'Status "%1$s" has been set for %2$d story.', 'Status "%1$s" has been set for %2$d stories.', $updated_count, 'fanfiction-manager' ),
				$status->name,
				$updated_count
			)
		);
	}

	/**
	 * AJAX: Handle asynchronous image uploads.
	 *
	 * Processes an image upload, resizes and converts it, and returns the URL.
	 *
	 * @since 2.1.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_image_upload() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'upload_file_key', 'context' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$file_key = sanitize_key( $params['upload_file_key'] );
		$context = sanitize_text_field( $params['context'] );

		// Double-check that a file was actually sent
		if ( empty( $_FILES[ $file_key ] ) || UPLOAD_ERR_NO_FILE === $_FILES[ $file_key ]['error'] ) {
			Fanfic_AJAX_Security::send_error_response(
				'no_file_uploaded',
				__( 'No file was uploaded. Please select a file and try again.', 'fanfiction-manager' ),
				400
			);
		}

		// Use the existing image upload handler function
		$errors = array();
		$upload_result = fanfic_handle_image_upload( $file_key, $context, $errors );

		// Check for errors from the handler
		if ( ! empty( $errors ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'upload_error',
				implode( ' ', $errors ),
				400
			);
		}

		// Check for a valid result
		if ( is_null( $upload_result ) || empty( $upload_result['url'] ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'upload_failed',
				__( 'An unknown error occurred during upload.', 'fanfiction-manager' ),
				500
			);
		}

		// Success! Send back the URL.
		Fanfic_AJAX_Security::send_success_response(
			array(
				'url' => $upload_result['url'],
			),
			__( 'Image uploaded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Record unified interaction.
	 *
	 * Supports: like, remove_like, dislike, remove_dislike, rating, remove_rating, read.
	 * Authenticated users only.
	 *
	 * @since 1.6.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_record_interaction() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'chapter_id', 'type' ),
			array( 'value', 'anonymous_uuid' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$user_id    = get_current_user_id();
		$chapter_id = absint( $params['chapter_id'] );
		$type       = sanitize_key( $params['type'] );
		$value      = isset( $params['value'] ) ? floatval( $params['value'] ) : 0;
		$anonymous_uuid = isset( $params['anonymous_uuid'] ) ? sanitize_text_field( $params['anonymous_uuid'] ) : '';

		if ( ! $chapter_id ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_chapter',
				__( 'Invalid chapter.', 'fanfiction-manager' ),
				400
			);
		}

		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			Fanfic_AJAX_Security::send_error_response(
				'chapter_not_found',
				__( 'Chapter not found.', 'fanfiction-manager' ),
				404
			);
		}

		$result = null;

		switch ( $type ) {
			case 'like':
				$result = Fanfic_Interactions::record_like( $chapter_id, $user_id, $anonymous_uuid );
				break;
			case 'remove_like':
				$result = Fanfic_Interactions::remove_like( $chapter_id, $user_id, $anonymous_uuid );
				break;
			case 'dislike':
				$result = Fanfic_Interactions::record_dislike( $chapter_id, $user_id, $anonymous_uuid );
				break;
			case 'remove_dislike':
				$result = Fanfic_Interactions::remove_dislike( $chapter_id, $user_id, $anonymous_uuid );
				break;
			case 'rating':
				$result = Fanfic_Interactions::record_rating( $chapter_id, $value, $user_id, $anonymous_uuid );
				break;
			case 'remove_rating':
				$result = Fanfic_Interactions::remove_rating( $chapter_id, $user_id, $anonymous_uuid );
				break;
			case 'read':
				$result = Fanfic_Interactions::record_read( $chapter_id, $user_id );
				break;
			default:
				Fanfic_AJAX_Security::send_error_response(
					'invalid_interaction_type',
					__( 'Invalid interaction type.', 'fanfiction-manager' ),
					400
				);
		}

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		// Interaction methods already return stats for like/dislike/rating paths.
		// Reuse those to avoid an extra DB read on every request.
		$stats = ( isset( $result['stats'] ) && is_array( $result['stats'] ) )
			? $result['stats']
			: Fanfic_Interactions::get_chapter_stats( $chapter_id );

		Fanfic_AJAX_Security::send_success_response(
			array(
				'type'    => $type,
				'stats'   => $stats,
				'changed' => isset( $result['changed'] ) ? (bool) $result['changed'] : true,
			),
			__( 'Interaction recorded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Record chapter view.
	 *
	 * Public endpoint (used by both anonymous and authenticated users).
	 *
	 * @since 1.6.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_record_view() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'chapter_id' ),
			array( 'story_id' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$chapter_id = absint( $params['chapter_id'] );
		$story_id   = isset( $params['story_id'] ) ? absint( $params['story_id'] ) : 0;
		if ( ! $chapter_id ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_chapter',
				__( 'Invalid chapter.', 'fanfiction-manager' ),
				400
			);
		}

		$result = Fanfic_Interactions::record_view( $chapter_id, $story_id );
		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		$stats = Fanfic_Interactions::get_chapter_stats( $chapter_id );
		Fanfic_AJAX_Security::send_success_response(
			array(
				'stats'   => $stats,
				'skipped' => isset( $result['skipped'] ) ? (bool) $result['skipped'] : false,
			),
			__( 'View recorded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Sync localStorage interactions with database.
	 *
	 * Authenticated users only.
	 *
	 * @since 1.6.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_sync_interactions() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array(),
			array( 'local_data', 'anonymous_uuid' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$user_id = get_current_user_id();
		$local_raw = isset( $params['local_data'] ) ? $params['local_data'] : array();
		$local_data = array();

		if ( is_string( $local_raw ) && '' !== $local_raw ) {
			$decoded = json_decode( wp_unslash( $local_raw ), true );
			$local_data = is_array( $decoded ) ? $decoded : array();
		} elseif ( is_array( $local_raw ) ) {
			$local_data = $local_raw;
		}

		$anonymous_uuid = isset( $params['anonymous_uuid'] ) ? sanitize_text_field( $params['anonymous_uuid'] ) : '';
		$result = Fanfic_Interactions::sync_on_login( $user_id, $local_data, $anonymous_uuid );
		Fanfic_AJAX_Security::send_success_response(
			$result,
			__( 'Interactions synchronized successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Mark chapter as read
	 *
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_mark_as_read() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'story_id', 'chapter_number' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$story_id = absint( $params['story_id'] );
		$chapter_number = absint( $params['chapter_number'] );
		$user_id = get_current_user_id();

		// Mark as read
		$result = Fanfic_Reading_Progress::mark_as_read( $user_id, $story_id, $chapter_number );

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		// Return success
		Fanfic_AJAX_Security::send_success_response(
			array( 'is_read' => true ),
			__( 'Chapter marked as read.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Toggle follow
	 *
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_toggle_follow() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'post_id' ),
			array( 'follow_type', 'anonymous_uuid', 'email' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$post_id        = absint( $params['post_id'] );
		$user_id        = get_current_user_id();
		$anonymous_uuid = isset( $params['anonymous_uuid'] ) ? sanitize_text_field( $params['anonymous_uuid'] ) : '';

		// Toggle follow
		$result = Fanfic_Follows::toggle_follow( $user_id, $post_id, $anonymous_uuid );

		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			Fanfic_AJAX_Security::send_error_response(
				'follow_failed',
				isset( $result['error'] ) ? $result['error'] : __( 'Failed to toggle follow.', 'fanfiction-manager' ),
				400
			);
		}

		// Determine the story ID (for email subscription operations).
		$post = get_post( $post_id );
		$story_id = $post_id;
		if ( $post && 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
			$story_id = absint( $post->post_parent );
		}

		// Handle email subscription based on follow state.
		$email_subscribed   = false;
		$email_unsubscribed = false;
		$unsubscribed_email = '';

		if ( ! empty( $params['email'] ) && class_exists( 'Fanfic_Email_Subscriptions' ) ) {
			$email = sanitize_email( $params['email'] );

			if ( is_email( $email ) ) {
				if ( $result['is_followed'] ) {
					// Follow: subscribe email to story updates.
					$sub_result = Fanfic_Email_Subscriptions::subscribe_from_follow( $email, $story_id );
					$email_subscribed = ! is_wp_error( $sub_result );
				} else {
					// Unfollow: remove email subscription if it exists.
					$email_unsubscribed = Fanfic_Email_Subscriptions::unsubscribe_on_unfollow( $email, $story_id );
					if ( $email_unsubscribed ) {
						$unsubscribed_email = $email;
					}
				}
			}
		}

		// Build response message.
		if ( $result['is_followed'] ) {
			$message = __( 'Follow added!', 'fanfiction-manager' );
		} elseif ( $email_unsubscribed ) {
			$message = sprintf(
				/* translators: %s: email address */
				__( 'Unfollow successful. "%s" will no longer receive email alerts.', 'fanfiction-manager' ),
				$unsubscribed_email
			);
		} else {
			$message = __( 'Follow removed.', 'fanfiction-manager' );
		}

		// Get updated follow count for the story.
		$follow_count = Fanfic_Follows::get_follow_count( $story_id );

		// Return success
		Fanfic_AJAX_Security::send_success_response(
			array(
				'is_followed'        => $result['is_followed'],
				'email_subscribed'   => $email_subscribed,
				'email_unsubscribed' => $email_unsubscribed,
				'follow_count'       => $follow_count,
				'story_id'           => $story_id,
			),
			$message
		);
	}

	// ajax_subscribe_email() removed in 1.8.0 — email subscription
	// is now handled via the follow toggle with optional email param.

	/**
	 * AJAX: Get chapter statistics in batch
	 *
	 * Optimized batch loading to prevent N+1 queries.
	 * Returns ratings, likes, and user-specific states for multiple chapters.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_get_chapter_stats() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'chapter_ids' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		// Parse chapter IDs (should be comma-separated string or array)
		$chapter_ids = $params['chapter_ids'];
		if ( is_string( $chapter_ids ) ) {
			$chapter_ids = explode( ',', $chapter_ids );
		}
		$chapter_ids = array_map( 'absint', (array) $chapter_ids );
		$chapter_ids = array_filter( $chapter_ids );

		if ( empty( $chapter_ids ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'no_chapter_ids',
				__( 'No chapter IDs provided.', 'fanfiction-manager' ),
				400
			);
		}

		// Limit to 50 chapters per request
		if ( count( $chapter_ids ) > 50 ) {
			$chapter_ids = array_slice( $chapter_ids, 0, 50 );
		}

		$stats = Fanfic_Interactions::batch_get_chapter_stats( $chapter_ids );
		$user_states = array();

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$all_user_interactions = Fanfic_Interactions::get_all_user_interactions( $user_id );

			foreach ( (array) $all_user_interactions as $key => $entry ) {
				if ( ! is_array( $entry ) || ! preg_match( '/^story_(\d+)_chapter_(\d+)$/', (string) $key, $matches ) ) {
					continue;
				}

				$chapter_id = absint( $matches[2] ?? 0 );
				if ( ! $chapter_id || ! in_array( $chapter_id, $chapter_ids, true ) ) {
					continue;
				}

				$user_states[ $chapter_id ] = array(
					'like'    => ! empty( $entry['like'] ),
					'dislike' => ! empty( $entry['dislike'] ),
					'rating'  => isset( $entry['rating'] ) ? floatval( $entry['rating'] ) : null,
					'read'    => ! empty( $entry['read'] ),
					'viewed'  => true,
				);
			}
		}

		// Return stats
		Fanfic_AJAX_Security::send_success_response(
			array(
				'stats'       => $stats,
				'user_states' => $user_states,
			),
			__( 'Stats loaded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Browse/search stories with filters
	 *
	 * Returns HTML fragments for results + pagination.
	 *
	 * @since 1.2.0
	 * @return void Sends JSON response.
	 */
	public static function ajax_stories_search() {
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'nonce' ),
			array( 'search', 's', 'genre', 'status', 'fandom', 'warning', 'age', 'sort', 'paged', 'base_url' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( $params->get_error_code(), $params->get_error_message(), 400 );
		}

		if ( ! function_exists( 'fanfic_get_stories_params' ) || ! function_exists( 'fanfic_build_stories_query_args' ) ) {
			Fanfic_AJAX_Security::send_error_response( 'missing_dependencies', __( 'Stories helpers are not available.', 'fanfiction-manager' ), 500 );
		}

		$normalized = fanfic_get_stories_params( $params );
		$paged = isset( $params['paged'] ) ? absint( $params['paged'] ) : 1;
		$per_page = (int) get_option( 'posts_per_page', 10 );

		$query_args = fanfic_build_stories_query_args( $normalized, $paged, $per_page );
		$stories_query = new WP_Query( $query_args );

		// Preload story-card search-index metadata to avoid per-card queries.
		if ( $stories_query->have_posts() && function_exists( 'fanfic_preload_story_card_index_data' ) ) {
			$preload_ids = wp_list_pluck( $stories_query->posts, 'ID' );
			fanfic_preload_story_card_index_data( $preload_ids );
		}

		$html = '';
		if ( $stories_query->have_posts() ) {
			while ( $stories_query->have_posts() ) {
				$stories_query->the_post();
				$html .= fanfic_get_story_card_html( get_the_ID() );
			}
			wp_reset_postdata();
		} else {
			$html = '<div class="fanfic-no-results"><p>' . esc_html__( 'No stories found matching your criteria.', 'fanfiction-manager' ) . '</p></div>';
		}

		$base_url = isset( $params['base_url'] ) ? esc_url_raw( $params['base_url'] ) : '';
		if ( empty( $base_url ) ) {
			$base_url = function_exists( 'fanfic_get_story_archive_url' ) ? fanfic_get_story_archive_url() : home_url( '/' );
		}

		$pagination_html = '';
		if ( $stories_query->max_num_pages > 1 ) {
			$pagination_base = fanfic_build_stories_url( $base_url, $normalized, array( 'paged' => null ) );
			$pagination_html = paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%', $pagination_base ),
				'format'    => '',
				'current'   => max( 1, $paged ),
				'total'     => (int) $stories_query->max_num_pages,
				'prev_text' => esc_html__( '&laquo; Previous', 'fanfiction-manager' ),
				'next_text' => esc_html__( 'Next &raquo;', 'fanfiction-manager' ),
			) );
		}

		$active_html = '';
		if ( function_exists( 'fanfic_build_active_filters' ) ) {
			$active_filters = fanfic_build_active_filters( $normalized, $base_url );
			if ( ! empty( $active_filters ) ) {
				ob_start();
				?>
				<div class="fanfic-active-filters">
					<p class="fanfic-filters-label"><strong><?php esc_html_e( 'Active Filters:', 'fanfiction-manager' ); ?></strong></p>
					<ul class="fanfic-filter-list">
						<?php foreach ( $active_filters as $filter ) : ?>
							<li class="fanfic-filter-item">
								<span class="fanfic-filter-label"><?php echo esc_html( $filter['label'] ); ?></span>
								<a href="<?php echo esc_url( $filter['url'] ); ?>" class="fanfic-filter-remove" aria-label="<?php esc_attr_e( 'Remove filter', 'fanfiction-manager' ); ?>">
									&times;
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<a href="<?php echo esc_url( $base_url ); ?>" class="fanfic-clear-filters">
						<?php esc_html_e( 'Clear All Filters', 'fanfiction-manager' ); ?>
					</a>
				</div>
				<?php
				$active_html = ob_get_clean();
			}
		}

		$count_label = sprintf(
			esc_html(
				_n(
					'Found %d story',
					'Found %d stories',
					$stories_query->found_posts,
					'fanfiction-manager'
				)
			),
			absint( $stories_query->found_posts )
		);

		Fanfic_AJAX_Security::send_success_response(
			array(
				'html'           => $html,
				'pagination'     => $pagination_html,
				'active_filters' => $active_html,
				'found'          => absint( $stories_query->found_posts ),
				'count_label'    => $count_label,
				'total_pages'    => absint( $stories_query->max_num_pages ),
				'current_page'   => max( 1, $paged ),
			),
			__( 'Results loaded.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Delete notification
	 *
	 * Permanently deletes a notification from the database.
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_delete_notification() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'notification_id' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$notification_id = absint( $params['notification_id'] );
		$user_id = get_current_user_id();

		if ( ! $notification_id ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_notification',
				__( 'Invalid notification ID.', 'fanfiction-manager' ),
				400
			);
		}

		// Delete notification
		$result = Fanfic_Notifications::delete_notification( $notification_id, $user_id );

		if ( ! $result ) {
			Fanfic_AJAX_Security::send_error_response(
				'delete_failed',
				__( 'Failed to delete notification. It may not exist or belong to another user.', 'fanfiction-manager' ),
				400
			);
		}

		// Get updated unread count
		$unread_count = Fanfic_Notifications::get_unread_count( $user_id );

		// Return success with updated count
		Fanfic_AJAX_Security::send_success_response(
			array( 'unread_count' => $unread_count ),
			__( 'Notification dismissed.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Get notifications with pagination
	 *
	 * Retrieves paginated notifications for the current user.
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_get_notifications() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'page' ),
			array( 'unread_only' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$page = absint( $params['page'] );
		$unread_only = isset( $params['unread_only'] ) ? (bool) $params['unread_only'] : true;
		$user_id = get_current_user_id();

		// Validate page number
		if ( $page < 1 ) {
			$page = 1;
		}

		// Max 5 pages (50 notifications)
		if ( $page > 5 ) {
			$page = 5;
		}

		// Calculate offset (10 per page)
		$per_page = 10;
		$offset = ( $page - 1 ) * $per_page;

		// Get notifications
		$notifications = Fanfic_Notifications::get_user_notifications( $user_id, $unread_only, $per_page, $offset );

		// Format notifications for frontend
		$formatted_notifications = array();
		foreach ( $notifications as $notification ) {
			$formatted_notifications[] = array(
				'id'              => $notification->id,
				'type'            => $notification->type,
				'message'         => $notification->message,
				'created_at'      => $notification->created_at,
				'relative_time'   => Fanfic_Notifications::get_relative_time( $notification->created_at ),
				'is_read'         => (bool) $notification->is_read,
				'data'            => ! empty( $notification->data ) ? json_decode( $notification->data, true ) : array(),
			);
		}

		// Get total count and pages
		$total_count = Fanfic_Notifications::get_unread_count( $user_id );
		$total_pages = min( ceil( $total_count / $per_page ), 5 );

		// Return notifications
		Fanfic_AJAX_Security::send_success_response(
			array(
				'notifications'   => $formatted_notifications,
				'page'            => $page,
				'total_pages'     => $total_pages,
				'total_count'     => $total_count,
				'unread_count'    => $total_count,
				'has_more'        => $page < $total_pages,
			),
			__( 'Notifications loaded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Load user follows with pagination
	 *
	 * Retrieves paginated follows for the current user.
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_load_user_follows() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'offset', 'follow_type' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$offset        = absint( $params['offset'] );
		$follow_type = sanitize_text_field( $params['follow_type'] );
		$user_id       = get_current_user_id();

		// Validate follow_type — must be 'story' or 'chapter'
		$allowed_types = array( 'story', 'chapter' );
		if ( ! in_array( $follow_type, $allowed_types, true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_follow_type',
				__( 'Invalid follow type. Must be "story" or "chapter".', 'fanfiction-manager' ),
				400
			);
		}

		// Get follows (20 per page)
		$per_page  = 20;
		$follows = Fanfic_Follows::get_user_follows( $user_id, $follow_type, $per_page, $offset );

		// Get total count for has_more calculation
		$total_count = Fanfic_Follows::get_follows_count( $user_id, $follow_type );

		// Build HTML for each follow
		$html_output = '';
		foreach ( $follows as $follow ) {
			if ( 'chapter' === $follow_type ) {
				$html_output .= Fanfic_Follows::render_chapter_follow_item( $follow );
			} else {
				$html_output .= Fanfic_Follows::render_story_follow_item( $follow );
			}
		}

		// Calculate has_more
		$has_more = ( $offset + $per_page ) < $total_count;

		// Return success response
		Fanfic_AJAX_Security::send_success_response(
			array(
				'html'        => $html_output,
				'count'       => count( $follows ),
				'total_count' => $total_count,
				'has_more'    => $has_more,
			),
			__( 'Follows loaded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Get read status for chapters
	 *
	 * Retrieves all read chapter numbers for a story.
	 * Authenticated users only.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_get_read_status() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'story_id' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$story_id = absint( $params['story_id'] );
		$user_id = get_current_user_id();

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_story',
				__( 'Story not found.', 'fanfiction-manager' ),
				404
			);
		}

		$read_chapters = Fanfic_Reading_Progress::batch_load_read_chapters( $user_id, $story_id );

		Fanfic_AJAX_Security::send_success_response(
			array(
				'read_chapters' => $read_chapters,
			),
			__( 'Read status retrieved.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Unmark chapter as read
	 *
	 * Removes a chapter from user's reading progress.
	 * Authenticated users only.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_unmark_as_read() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'story_id', 'chapter_number' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$story_id = absint( $params['story_id'] );
		$chapter_number = absint( $params['chapter_number'] );
		$user_id = get_current_user_id();

		// Unmark as read
		$result = Fanfic_Reading_Progress::unmark_as_read( $user_id, $story_id, $chapter_number );

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		Fanfic_AJAX_Security::send_success_response(
			array( 'is_read' => false ),
			__( 'Chapter unmarked as read.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Verify email subscription
	 *
	 * Verifies an email subscription using a token.
	 * Public endpoint.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	/**
	 * AJAX: Mark notification as read
	 *
	 * Marks a single notification as read.
	 * Authenticated users only.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_mark_notification_read() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'notification_id' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$notification_id = absint( $params['notification_id'] );
		$user_id = get_current_user_id();

		if ( ! $notification_id ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_notification',
				__( 'Invalid notification ID.', 'fanfiction-manager' ),
				400
			);
		}

		// Mark as read
		$result = Fanfic_Notifications::mark_as_read( $notification_id, $user_id );

		if ( ! $result ) {
			Fanfic_AJAX_Security::send_error_response(
				'mark_failed',
				__( 'Failed to mark notification as read.', 'fanfiction-manager' ),
				400
			);
		}

		// Get updated unread count
		$unread_count = Fanfic_Notifications::get_unread_count( $user_id );

		Fanfic_AJAX_Security::send_success_response(
			array( 'unread_count' => $unread_count ),
			__( 'Notification marked as read.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Mark all notifications as read
	 *
	 * Marks all notifications as read for the current user.
	 * Authenticated users only.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_mark_all_notifications_read() {
		$user_id = get_current_user_id();

		// Mark all as read
		$result = Fanfic_Notifications::mark_all_as_read( $user_id );

		if ( ! $result ) {
			Fanfic_AJAX_Security::send_error_response(
				'mark_failed',
				__( 'Failed to mark notifications as read.', 'fanfiction-manager' ),
				400
			);
		}

		Fanfic_AJAX_Security::send_success_response(
			array( 'unread_count' => 0 ),
			__( 'All notifications marked as read.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Get unread notification count
	 *
	 * Retrieves the count of unread notifications.
	 * Authenticated users only.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_get_unread_count() {
		$user_id = get_current_user_id();
		$unread_count = Fanfic_Notifications::get_unread_count( $user_id );

		Fanfic_AJAX_Security::send_success_response(
			array( 'unread_count' => $unread_count ),
			__( 'Unread count retrieved.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Report content
	 *
	 * Allows users to report inappropriate content.
	 * Public endpoint with stricter rate limits.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_report_content() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'post_id', 'post_type', 'reason' ),
			array( 'details' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$post_id = absint( $params['post_id'] );
		$post_type = sanitize_text_field( $params['post_type'] );
		$reason = sanitize_text_field( $params['reason'] );
		$details = isset( $params['details'] ) ? sanitize_textarea_field( $params['details'] ) : '';

		// Validate post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_post',
				__( 'Content not found.', 'fanfiction-manager' ),
				404
			);
		}

		// Validate post type
		$allowed_post_types = array( 'fanfiction_story', 'fanfiction_chapter', 'comment' );
		if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_post_type',
				__( 'Invalid content type.', 'fanfiction-manager' ),
				400
			);
		}

		// Validate reason
		$valid_reasons = array(
			'spam',
			'harassment',
			'inappropriate_content',
			'copyright',
			'other',
		);

		if ( ! in_array( $reason, $valid_reasons, true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_reason',
				__( 'Invalid report reason.', 'fanfiction-manager' ),
				400
			);
		}

		// Get reporter info
		$reporter_id = is_user_logged_in() ? get_current_user_id() : 0;
		$reporter_ip = $_SERVER['REMOTE_ADDR'];

		// Store report (you may want to create a dedicated table for this)
		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_reports';

		// Check if reports table exists (it may not, so we'll handle gracefully)
		$result = $wpdb->insert(
			$table_name,
			array(
				'post_id'     => $post_id,
				'post_type'   => $post_type,
				'reporter_id' => $reporter_id,
				'reporter_ip' => $reporter_ip,
				'reason'      => $reason,
				'details'     => $details,
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			Fanfic_AJAX_Security::send_error_response(
				'report_failed',
				__( 'Failed to submit report. Please try again.', 'fanfiction-manager' ),
				500
			);
		}

		// Send notification to admin
		do_action( 'fanfic_content_reported', $post_id, $post_type, $reason, $details, $reporter_id );

		Fanfic_AJAX_Security::send_success_response(
			array( 'report_id' => $wpdb->insert_id ),
			__( 'Report submitted successfully. Thank you for helping keep our community safe.', 'fanfiction-manager' )
		);
	}
}
