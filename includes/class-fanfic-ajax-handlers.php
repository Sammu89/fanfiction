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

		// Toggle featured story (moderators/admins only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_featured',
			array( __CLASS__, 'ajax_toggle_featured' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'moderate_fanfiction',
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

		// Clear all notifications except invites (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_clear_all_notifications',
			array( __CLASS__, 'ajax_clear_all_notifications' ),
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

		// Author: send a message to moderation about a restriction (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_send_moderation_message',
			array( __CLASS__, 'ajax_send_moderation_message' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'read',
			)
		);

		// Load moderation thread chat data (author + moderator)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_get_moderation_thread',
			array( __CLASS__, 'ajax_get_moderation_thread' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'read',
			)
		);

		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_get_block_comparison',
			array( __CLASS__, 'ajax_get_block_comparison' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'moderate_fanfiction',
			)
		);

		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_chapter_block_ajax',
			array( __CLASS__, 'ajax_toggle_chapter_block' ),
			true, // Require login
			array(
				'rate_limit' => true,
				'capability' => 'moderate_fanfiction',
			)
		);

		// Moderator: take action on a moderation message (mod/admin only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_mod_message_action',
			array( __CLASS__, 'ajax_mod_message_action' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'moderate_fanfiction',
			)
		);

		// Moderator: send chat reply in a moderation message thread.
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_send_moderation_reply',
			array( __CLASS__, 'ajax_send_moderation_reply' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'moderate_fanfiction',
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
			if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
				Fanfic_Translations::sync_story_classification( $story_id );
			}
			if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
				Fanfic_Search_Index::update_index( $story_id );
			}
			if ( class_exists( 'Fanfic_Cache' ) && method_exists( 'Fanfic_Cache', 'invalidate_story' ) ) {
				Fanfic_Cache::invalidate_story( $story_id );
			}
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
			if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
				Fanfic_Search_Index::update_index( $story_id );
			}
			if ( class_exists( 'Fanfic_Cache' ) && method_exists( 'Fanfic_Cache', 'invalidate_story' ) ) {
				Fanfic_Cache::invalidate_story( $story_id );
			}
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
	 * Supports: like, remove_like, dislike, remove_dislike, rating, remove_rating, read, remove_read.
	 * Public endpoint (anonymous + authenticated).
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
				if ( ! $user_id ) {
					$result = array(
						'success' => true,
						'changed' => false,
						'stats'   => Fanfic_Interactions::get_chapter_stats( $chapter_id ),
					);
					break;
				}
				$result = Fanfic_Interactions::record_read( $chapter_id, $user_id );
				break;
			case 'remove_read':
				if ( ! $user_id ) {
					$result = array(
						'success' => true,
						'changed' => false,
						'stats'   => Fanfic_Interactions::get_chapter_stats( $chapter_id ),
					);
					break;
				}
				$result = Fanfic_Interactions::remove_read( $chapter_id, $user_id );
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

		$query_result  = fanfic_build_stories_query_args( $normalized, $paged, $per_page );
		$query_args    = is_array( $query_result ) && isset( $query_result['args'] ) ? $query_result['args'] : $query_result;
		$found_posts   = is_array( $query_result ) && isset( $query_result['found_posts'] ) ? (int) $query_result['found_posts'] : -1;
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

		$total_found = $found_posts >= 0 ? $found_posts : (int) $stories_query->found_posts;
		$total_pages = $found_posts >= 0
			? (int) ceil( $total_found / max( 1, $per_page ) )
			: (int) $stories_query->max_num_pages;

		$pagination_html = '';
		if ( $total_pages > 1 ) {
			$pagination_base = fanfic_build_stories_url( $base_url, $normalized, array( 'paged' => null ) );
			$pagination_html = paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%', $pagination_base ),
				'format'    => '',
				'current'   => max( 1, $paged ),
				'total'     => $total_pages,
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
					$total_found,
					'fanfiction-manager'
				)
			),
			absint( $total_found )
		);

		Fanfic_AJAX_Security::send_success_response(
			array(
				'html'           => $html,
				'pagination'     => $pagination_html,
				'active_filters' => $active_html,
				'found'          => absint( $total_found ),
				'count_label'    => $count_label,
				'total_pages'    => absint( $total_pages ),
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
			array(),
			array( 'offset' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$offset   = isset( $params['offset'] ) ? absint( $params['offset'] ) : 0;
		$per_page = 30;
		$user_id  = get_current_user_id();

		// Get notifications
		$notifications = Fanfic_Notifications::get_user_notifications( $user_id, false, $per_page, $offset );

		// Format notifications for frontend
		$formatted_notifications = array();
		foreach ( $notifications as $notification ) {
			$formatted_notifications[] = array(
				'id'            => $notification->id,
				'type'          => $notification->type,
				'message'       => $notification->message,
				'created_at'    => $notification->created_at,
				'relative_time' => Fanfic_Notifications::get_relative_time( $notification->created_at ),
				'is_read'       => (bool) $notification->is_read,
				'data'          => ! empty( $notification->data ) ? json_decode( $notification->data, true ) : array(),
			);
		}

		$total_count  = Fanfic_Notifications::get_total_count( $user_id );
		$unread_count = Fanfic_Notifications::get_unread_count( $user_id );
		$next_offset  = $offset + $per_page;

		// Return notifications
		Fanfic_AJAX_Security::send_success_response(
			array(
				'notifications' => $formatted_notifications,
				'offset'        => $offset,
				'next_offset'   => $next_offset,
				'total_count'   => $total_count,
				'unread_count'  => $unread_count,
				'has_more'      => $next_offset < $total_count,
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

		if ( 'story' === $follow_type && function_exists( 'fanfic_preload_story_card_index_data' ) ) {
			$preload_ids = array();
			foreach ( $follows as $follow ) {
				$story_id = isset( $follow['post_id'] ) ? absint( $follow['post_id'] ) : 0;
				if ( $story_id ) {
					$preload_ids[] = $story_id;
				}
			}

			if ( ! empty( $preload_ids ) ) {
				fanfic_preload_story_card_index_data( array_values( array_unique( $preload_ids ) ) );
			}
		}

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
	 * AJAX: Clear all notifications except co-author invites
	 *
	 * Deletes all notifications for the current user, preserving
	 * coauthor_invite notifications that require a response.
	 * Authenticated users only.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_clear_all_notifications() {
		$user_id = get_current_user_id();

		Fanfic_Notifications::delete_clearable_notifications( $user_id );

		$unread_count = Fanfic_Notifications::get_unread_count( $user_id );
		$total_count  = Fanfic_Notifications::get_total_count( $user_id );

		Fanfic_AJAX_Security::send_success_response(
			array(
				'unread_count' => $unread_count,
				'total_count'  => $total_count,
			),
			__( 'Notifications cleared.', 'fanfiction-manager' )
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
		$reporting_enabled = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_report', true ) : true;
		if ( ! $reporting_enabled ) {
			Fanfic_AJAX_Security::send_error_response(
				'reporting_disabled',
				__( 'Content reporting is currently disabled.', 'fanfiction-manager' ),
				403
			);
		}

		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'post_id', 'post_type' ),
			array( 'reason', 'details', 'recaptcha_token', 'anonymous_uuid' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$post_id   = absint( $params['post_id'] );
		$post_type = sanitize_key( $params['post_type'] );
		$allowed_post_types = array( 'fanfiction_story', 'fanfiction_chapter', 'comment' );
		if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_post_type',
				__( 'Invalid content type.', 'fanfiction-manager' ),
				400
			);
		}

		$reason = isset( $params['reason'] ) ? sanitize_key( $params['reason'] ) : 'other';
		$valid_reasons = array( 'spam', 'harassment', 'inappropriate', 'inappropriate_content', 'copyright', 'other' );
		if ( ! in_array( $reason, $valid_reasons, true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_reason',
				__( 'Invalid report reason.', 'fanfiction-manager' ),
				400
			);
		}

		$details = isset( $params['details'] ) ? sanitize_textarea_field( $params['details'] ) : '';
		if ( strlen( $details ) > 2000 ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_details',
				__( 'Details must be less than 2000 characters.', 'fanfiction-manager' ),
				400
			);
		}
		if ( 'other' === $reason && '' === trim( $details ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'missing_details',
				__( 'Please provide details when selecting Other.', 'fanfiction-manager' ),
				400
			);
		}

		// Validate content exists and matches expected type.
		if ( 'comment' === $post_type ) {
			$comment = get_comment( $post_id );
			if ( ! $comment ) {
				Fanfic_AJAX_Security::send_error_response(
					'invalid_post',
					__( 'Content not found.', 'fanfiction-manager' ),
					404
				);
			}
		} else {
			$post = get_post( $post_id );
			if ( ! $post || $post_type !== $post->post_type ) {
				Fanfic_AJAX_Security::send_error_response(
					'invalid_post',
					__( 'Content not found.', 'fanfiction-manager' ),
					404
				);
			}
		}

		$reporter_id = is_user_logged_in() ? get_current_user_id() : 0;
		$reporter_ip = Fanfic_Rate_Limit::get_ip_address();
		$anonymous_uuid = isset( $params['anonymous_uuid'] ) ? sanitize_text_field( $params['anonymous_uuid'] ) : '';
		$is_anonymous = ( 0 === $reporter_id );

		if ( $reporter_id > 0 && Fanfic_Blacklist::is_reporter_blacklisted( $reporter_id ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'report_not_allowed',
				__( 'You are unable to submit reports at this time.', 'fanfiction-manager' ),
				403
			);
		}

		if ( $is_anonymous && '' !== $reporter_ip && Fanfic_Blacklist::is_reporter_blacklisted_by_ip( $reporter_ip ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'report_not_allowed',
				__( 'You are unable to submit reports at this time.', 'fanfiction-manager' ),
				403
			);
		}

		$allow_anonymous_reports = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'allow_anonymous_reports', false ) : false;
		if ( $is_anonymous && ! $allow_anonymous_reports ) {
			Fanfic_AJAX_Security::send_error_response(
				'login_required',
				__( 'Anonymous reports are disabled. Please log in to report content.', 'fanfiction-manager' ),
				401
			);
		}

		$settings = get_option( 'fanfic_settings', array() );
		$recaptcha_require_logged_in = ! empty( $settings['recaptcha_require_logged_in'] );
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );
		$recaptcha_secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );
		$should_verify_recaptcha = $is_anonymous || $recaptcha_require_logged_in;

		if ( $should_verify_recaptcha ) {
			if ( empty( $recaptcha_site_key ) || empty( $recaptcha_secret_key ) ) {
				Fanfic_AJAX_Security::send_error_response(
					'recaptcha_not_configured',
					__( 'reCAPTCHA is not configured. Please contact the site administrator.', 'fanfiction-manager' ),
					500
				);
			}

			$recaptcha_token = isset( $params['recaptcha_token'] ) ? sanitize_text_field( $params['recaptcha_token'] ) : '';
			$recaptcha_result = self::verify_report_recaptcha( $recaptcha_token, $recaptcha_secret_key, $reporter_ip );
			if ( is_wp_error( $recaptcha_result ) ) {
				Fanfic_AJAX_Security::send_error_response(
					$recaptcha_result->get_error_code(),
					$recaptcha_result->get_error_message(),
					400
				);
			}
		}

		if ( class_exists( 'Fanfic_Database_Setup' ) ) {
			$table_result = Fanfic_Database_Setup::ensure_reports_table();
			if ( is_wp_error( $table_result ) ) {
				Fanfic_AJAX_Security::send_error_response(
					'report_failed',
					__( 'Failed to submit report. Please try again.', 'fanfiction-manager' ),
					500
				);
			}
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_reports';
		$content_revision = fanfic_get_report_revision_token( $post_id, $post_type );

		if ( $reporter_id > 0 ) {
			$duplicate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name}
					WHERE reported_item_id = %d
					AND reported_item_type = %s
					AND reporter_id = %d
					AND content_revision = %s",
					$post_id,
					$post_type,
					$reporter_id,
					$content_revision
				)
			);
		} else {
			$duplicate_sql = "SELECT id FROM {$table_name}
				WHERE reported_item_id = %d
				AND reported_item_type = %s
				AND content_revision = %s";
			$duplicate_values = array(
				$post_id,
				$post_type,
				$content_revision,
			);

			if ( '' !== $anonymous_uuid ) {
				$duplicate_sql .= ' AND anonymous_uuid = %s';
				$duplicate_values[] = $anonymous_uuid;
			} else {
				$duplicate_sql .= ' AND reporter_ip = %s';
				$duplicate_values[] = $reporter_ip;
			}

			$duplicate = $wpdb->get_var(
				$wpdb->prepare( $duplicate_sql, $duplicate_values )
			);
		}

		if ( $duplicate ) {
			Fanfic_AJAX_Security::send_error_response(
				'duplicate_report',
				__( 'You have already reported this version of the content. You can report it again after it receives a qualifying update.', 'fanfiction-manager' ),
				409
			);
		}

		$reason_label = self::get_report_reason_label( $reason );

		$result = $wpdb->insert(
			$table_name,
			array(
				'reported_item_id'   => $post_id,
				'reported_item_type' => $post_type,
				'reporter_id'        => $reporter_id,
				'anonymous_uuid'     => $anonymous_uuid,
				'reporter_ip'        => $reporter_ip,
				'content_revision'   => $content_revision,
				'reason'             => $reason_label,
				'details'            => $details,
				'status'             => 'pending',
				'created_at'         => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			Fanfic_AJAX_Security::send_error_response(
				'report_failed',
				__( 'Failed to submit report. Please try again.', 'fanfiction-manager' ),
				500
			);
		}

		do_action( 'fanfic_content_reported', $post_id, $post_type, $reason, $details, $reporter_id );

		Fanfic_AJAX_Security::send_success_response(
			array( 'report_id' => $wpdb->insert_id ),
			__( 'Report submitted successfully. Thank you for helping keep our community safe.', 'fanfiction-manager' )
		);
	}

	/**
	 * Verify report reCAPTCHA token with Google.
	 *
	 * @since 1.0.16
	 * @param string $token      reCAPTCHA token.
	 * @param string $secret_key reCAPTCHA secret key.
	 * @param string $remote_ip  User IP.
	 * @return true|WP_Error
	 */
	private static function verify_report_recaptcha( $token, $secret_key, $remote_ip ) {
		if ( empty( $token ) ) {
			return new WP_Error( 'missing_recaptcha_token', __( 'Please complete the reCAPTCHA verification.', 'fanfiction-manager' ) );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => $remote_ip,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'recaptcha_request_failed', __( 'reCAPTCHA verification failed. Please try again.', 'fanfiction-manager' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'invalid_recaptcha', __( 'reCAPTCHA verification failed. Please try again.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Get human-readable label for report reason.
	 *
	 * @since 1.0.16
	 * @param string $reason Reason code.
	 * @return string
	 */
	private static function get_report_reason_label( $reason ) {
		$labels = array(
			'spam'                 => __( 'Spam', 'fanfiction-manager' ),
			'harassment'           => __( 'Harassment or Bullying', 'fanfiction-manager' ),
			'inappropriate'        => __( 'Inappropriate Content', 'fanfiction-manager' ),
			'inappropriate_content' => __( 'Inappropriate Content', 'fanfiction-manager' ),
			'copyright'            => __( 'Copyright Violation', 'fanfiction-manager' ),
			'other'                => __( 'Other', 'fanfiction-manager' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : __( 'Other', 'fanfiction-manager' );
	}

	/**
	 * AJAX handler: Toggle featured status on a story.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_toggle_featured() {
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid story ID.', 'fanfiction-manager' ) ) );
		}

		$post = get_post( $story_id );
		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Story not found.', 'fanfiction-manager' ) ) );
		}

		$mode = Fanfic_Settings::get_setting( 'featured_mode', 'manual' );
		if ( 'automatic' === $mode ) {
			wp_send_json_error( array( 'message' => __( 'Manual featuring is not available in automatic mode.', 'fanfiction-manager' ) ) );
		}

		$result = Fanfic_Featured_Stories::toggle_featured( $story_id );

		wp_send_json_success( $result );
	}

	/**
	 * Check whether current user owns the requested moderation target.
	 *
	 * @since 2.4.0
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @param int    $user_id     Current user ID.
	 * @return bool
	 */
	private static function user_owns_moderation_target( $target_type, $target_id, $user_id ) {
		$target_type = sanitize_key( $target_type );
		$target_id   = absint( $target_id );
		$user_id     = absint( $user_id );

		if ( ! $target_id || ! $user_id ) {
			return false;
		}

		if ( 'story' === $target_type || 'chapter' === $target_type ) {
			$post = get_post( $target_id );
			return ( $post && absint( $post->post_author ) === $user_id );
		}

		if ( 'user' === $target_type ) {
			return $target_id === $user_id;
		}

		return false;
	}

	/**
	 * Build a normalized payload entry for moderation chat responses.
	 *
	 * @since 2.4.0
	 * @param array $entry     Raw DB entry row.
	 * @param int   $author_id Thread author ID.
	 * @return array
	 */
	private static function build_thread_entry_payload( $entry, $author_id ) {
		$sender_id   = isset( $entry['sender_id'] ) ? absint( $entry['sender_id'] ) : 0;
		$sender_role = isset( $entry['sender_role'] ) ? sanitize_key( $entry['sender_role'] ) : 'system';
		$sender_name = __( 'System', 'fanfiction-manager' );

		if ( 'author' === $sender_role ) {
			$author_user = get_userdata( $author_id );
			$sender_name = $author_user ? $author_user->display_name : __( 'Author', 'fanfiction-manager' );
		} elseif ( 'moderator' === $sender_role ) {
			$moderator = $sender_id ? get_userdata( $sender_id ) : null;
			$sender_name = $moderator ? $moderator->display_name : __( 'Moderator', 'fanfiction-manager' );
		}

		$created_at = isset( $entry['created_at'] ) ? (string) $entry['created_at'] : '';
		$created_ts = $created_at ? strtotime( $created_at ) : 0;

		return array(
			'id'         => isset( $entry['id'] ) ? absint( $entry['id'] ) : 0,
			'sender_id'  => $sender_id,
			'sender_role'=> $sender_role,
			'sender_name'=> $sender_name,
			'message'    => isset( $entry['message'] ) ? (string) $entry['message'] : '',
			'created_at' => $created_at,
			'created_human' => $created_ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_ts ) : '',
		);
	}

	/**
	 * Load moderation chat thread data for author or moderator view.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	public static function ajax_get_moderation_thread() {
		if ( ! class_exists( 'Fanfic_Moderation_Messages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Messages system is not available.', 'fanfiction-manager' ) ) );
		}

		$current_user_id = get_current_user_id();
		$message_id      = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$target_type     = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( $_POST['target_type'] ) ) : '';
		$target_id       = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$is_moderator    = current_user_can( 'manage_options' ) || current_user_can( 'moderate_fanfiction' );
		$is_moderator_view = false;
		$thread          = null;

		if ( $message_id > 0 ) {
			if ( ! $is_moderator ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to access this thread.', 'fanfiction-manager' ) ) );
			}
			$is_moderator_view = true;

			$thread = Fanfic_Moderation_Messages::get_message( $message_id );
			if ( ! $thread ) {
				wp_send_json_error( array( 'message' => __( 'Message thread not found.', 'fanfiction-manager' ) ) );
			}
		} else {
			$valid_types = array( 'story', 'chapter', 'user' );
			if ( ! in_array( $target_type, $valid_types, true ) || ! $target_id ) {
				wp_send_json_error( array( 'message' => __( 'Invalid moderation target.', 'fanfiction-manager' ) ) );
			}

			if ( ! self::user_owns_moderation_target( $target_type, $target_id, $current_user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not own this content.', 'fanfiction-manager' ) ) );
			}

			$thread = Fanfic_Moderation_Messages::get_active_message( $current_user_id, $target_type, $target_id );
			if ( ! $thread ) {
				$restriction_context = function_exists( 'fanfic_get_restriction_context' )
					? fanfic_get_restriction_context( $target_type, $target_id )
					: array( 'is_restricted' => false );
				$can_send = ! empty( $restriction_context['is_restricted'] );
				if ( $can_send && class_exists( 'Fanfic_Blacklist' ) && Fanfic_Blacklist::is_message_sender_blacklisted( $current_user_id ) ) {
					$can_send = false;
				}

				wp_send_json_success(
					array(
						'thread_id'            => 0,
						'status'               => '',
						'target_type'          => $target_type,
						'target_id'            => $target_id,
						'is_open'              => false,
						'can_send'             => $can_send,
						'is_restricted'        => ! empty( $restriction_context['is_restricted'] ),
						'unread_for_author'    => false,
						'unread_for_moderator' => false,
						'entries'              => array(),
					)
				);
			}
		}

		$thread_id = absint( $thread['id'] );
		$entries   = Fanfic_Moderation_Messages::get_message_entries( $thread_id, false );
		$author_id = absint( $thread['author_id'] );

		$payload_entries = array();
		foreach ( $entries as $entry ) {
			$payload_entries[] = self::build_thread_entry_payload( $entry, $author_id );
		}

		if ( $is_moderator_view ) {
			if ( ! empty( $thread['unread_for_moderator'] ) ) {
				Fanfic_Moderation_Messages::mark_thread_read_for_moderator( $thread_id );
				$thread['unread_for_moderator'] = 0;
			}
		} elseif ( $author_id === $current_user_id && ! empty( $thread['unread_for_author'] ) ) {
			Fanfic_Moderation_Messages::mark_thread_read_for_author( $thread_id );
			$thread['unread_for_author'] = 0;
		}

		$target_type = isset( $thread['target_type'] ) ? sanitize_key( $thread['target_type'] ) : '';
		$target_id   = isset( $thread['target_id'] ) ? absint( $thread['target_id'] ) : 0;
		$restriction_context = function_exists( 'fanfic_get_restriction_context' )
			? fanfic_get_restriction_context( $target_type, $target_id )
			: array( 'is_restricted' => false );
		$author_can_send = ! empty( $restriction_context['is_restricted'] );
		if ( $author_can_send && class_exists( 'Fanfic_Blacklist' ) && Fanfic_Blacklist::is_message_sender_blacklisted( $current_user_id ) ) {
			$author_can_send = false;
		}

		wp_send_json_success(
			array(
				'thread_id'            => $thread_id,
				'status'               => isset( $thread['status'] ) ? $thread['status'] : '',
				'target_type'          => $target_type,
				'target_id'            => $target_id,
				'is_open'              => 'unread' === ( isset( $thread['status'] ) ? $thread['status'] : '' ),
				'can_send'             => $is_moderator_view ? ( 'unread' === ( isset( $thread['status'] ) ? $thread['status'] : '' ) ) : $author_can_send,
				'is_restricted'        => ! empty( $restriction_context['is_restricted'] ),
				'unread_for_author'    => ! empty( $thread['unread_for_author'] ),
				'unread_for_moderator' => ! empty( $thread['unread_for_moderator'] ),
				'entries'              => $payload_entries,
			)
		);
	}

	/**
	 * Handle author submitting a moderation message about a restriction.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_send_moderation_message() {
		$target_type = isset( $_POST['target_type'] ) ? sanitize_text_field( wp_unslash( $_POST['target_type'] ) ) : '';
		$target_id   = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$message     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		$valid_types = array( 'story', 'chapter', 'user' );
		if ( ! in_array( $target_type, $valid_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target type.', 'fanfiction-manager' ) ) );
		}

		if ( ! $target_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target.', 'fanfiction-manager' ) ) );
		}

		$current_user_id = get_current_user_id();
		if ( class_exists( 'Fanfic_Blacklist' ) && Fanfic_Blacklist::is_message_sender_blacklisted( $current_user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are unable to send messages at this time.', 'fanfiction-manager' ) ) );
		}

		// Validate ownership.
		if ( 'story' === $target_type || 'chapter' === $target_type ) {
			$post = get_post( $target_id );
			if ( ! $post || absint( $post->post_author ) !== $current_user_id ) {
				wp_send_json_error( array( 'message' => __( 'You do not own this content.', 'fanfiction-manager' ) ) );
			}
		} elseif ( 'user' === $target_type ) {
			if ( $target_id !== $current_user_id ) {
				wp_send_json_error( array( 'message' => __( 'You can only message about your own account.', 'fanfiction-manager' ) ) );
			}
		}

		// Validate target is still restricted.
		$ctx = function_exists( 'fanfic_get_restriction_context' ) ? fanfic_get_restriction_context( $target_type, $target_id ) : array( 'is_restricted' => false );
		if ( empty( $ctx['is_restricted'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This item is no longer restricted.', 'fanfiction-manager' ) ) );
		}

		$message_length = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );
		if ( $message_length < 1 || $message_length > 1000 ) {
			wp_send_json_error( array( 'message' => __( 'Message must be between 1 and 1000 characters.', 'fanfiction-manager' ) ) );
		}

		if ( ! class_exists( 'Fanfic_Moderation_Messages' ) ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred. Please try again.', 'fanfiction-manager' ) ) );
		}

		$result = Fanfic_Moderation_Messages::send_author_message( $current_user_id, $target_type, $target_id, $message );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send message. Please try again.', 'fanfiction-manager' ) ) );
		}

		$thread_id = isset( $result['message_id'] ) ? absint( $result['message_id'] ) : 0;
		wp_send_json_success(
			array(
				'message'   => __( 'Message sent successfully.', 'fanfiction-manager' ),
				'thread_id' => $thread_id,
			)
		);
	}

	/**
	 * Render a comparison cell value.
	 *
	 * @since 2.3.0
	 * @param array  $row Row definition from fanfic_get_block_comparison_rows().
	 * @param string $side Either old or new.
	 * @return string
	 */
	private static function render_block_comparison_cell( $row, $side ) {
		$value_key = 'old' === $side ? 'old_value' : 'new_value';
		$value     = isset( $row[ $value_key ] ) ? (string) $row[ $value_key ] : '';

		if ( 'image' === $row['type'] ) {
			if ( '' === $value ) {
				return '<span class="fanfic-comparison-empty">' . esc_html__( 'No cover image', 'fanfiction-manager' ) . '</span>';
			}

			return sprintf(
				'<div class="fanfic-comparison-cover"><img src="%1$s" alt="%2$s"></div><div class="fanfic-comparison-url"><a href="%1$s" target="_blank" rel="noopener noreferrer">%3$s</a></div>',
				esc_url( $value ),
				esc_attr__( 'Cover image preview', 'fanfiction-manager' ),
				esc_html__( 'Open image', 'fanfiction-manager' )
			);
		}

		if ( '' === $value ) {
			return '<span class="fanfic-comparison-empty">' . esc_html__( 'None', 'fanfiction-manager' ) . '</span>';
		}

		if ( 'longtext' === $row['type'] ) {
			return '<div class="fanfic-comparison-text">' . nl2br( esc_html( $value ) ) . '</div>';
		}

		return '<span>' . esc_html( $value ) . '</span>';
	}

	/**
	 * Build the moderator comparison HTML for a blocked story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story ID.
	 * @return string
	 */
	private static function build_block_comparison_html( $story_id ) {
		$rows         = function_exists( 'fanfic_get_block_comparison_rows' ) ? fanfic_get_block_comparison_rows( $story_id ) : array();
		$snapshot     = function_exists( 'fanfic_get_block_snapshot' ) ? fanfic_get_block_snapshot( $story_id ) : array();
		$revision_url = function_exists( 'fanfic_get_revision_compare_url' ) ? fanfic_get_revision_compare_url( $story_id ) : '';
		$changed_rows = array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return ! empty( $row['changed'] );
				}
			)
		);

		$changed_count = count( $changed_rows );

		ob_start();
		?>
		<div class="fanfic-block-comparison">
			<div class="fanfic-block-comparison-header">
				<h4><?php esc_html_e( 'Blocked Story Comparison', 'fanfiction-manager' ); ?></h4>
				<?php if ( ! empty( $snapshot['snapshot_time'] ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: formatted snapshot date */
							esc_html__( 'Baseline captured on %s.', 'fanfiction-manager' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $snapshot['snapshot_time'] ) )
						);
						?>
					</p>
				<?php endif; ?>
				<?php if ( $revision_url ) : ?>
					<p><a href="<?php echo esc_url( $revision_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open WordPress revision comparison', 'fanfiction-manager' ); ?></a></p>
				<?php endif; ?>
			</div>

			<table class="widefat striped fanfic-comparison-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'fanfiction-manager' ); ?></th>
						<th><?php esc_html_e( 'Blocked Snapshot', 'fanfiction-manager' ); ?></th>
						<th><?php esc_html_e( 'Current Version', 'fanfiction-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $changed_rows as $row ) : ?>
						<?php
						$row_class = 'changed';
						$old_class = 'snapshot-value';
						$new_class = 'current-value';
						$show_native_diff = isset( $row['key'] ) && 'post_excerpt' === $row['key'];
						?>
						<?php if ( $show_native_diff ) : ?>
							<?php
							$native_diff = wp_text_diff( (string) $row['old_value'], (string) $row['new_value'] );
							?>
							<tr class="comparison-row <?php echo esc_attr( $row_class ); ?> fanfic-native-diff-row">
								<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
								<td colspan="2" class="fanfic-native-diff-cell">
									<?php
									if ( $native_diff ) {
										echo wp_kses_post( $native_diff );
									} else {
										echo '<p class="description">' . esc_html__( 'No text diff output is available for this field.', 'fanfiction-manager' ) . '</p>';
									}
									?>
								</td>
							</tr>
						<?php else : ?>
							<tr class="comparison-row <?php echo esc_attr( $row_class ); ?>">
								<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
								<td class="<?php echo esc_attr( $old_class ); ?>"><?php echo wp_kses_post( self::render_block_comparison_cell( $row, 'old' ) ); ?></td>
								<td class="<?php echo esc_attr( $new_class ); ?>"><?php echo wp_kses_post( self::render_block_comparison_cell( $row, 'new' ) ); ?></td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( 0 === $changed_count ) : ?>
				<p class="description"><?php esc_html_e( 'No changes were detected between the snapshot and the current story state.', 'fanfiction-manager' ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Handle loading the blocked-story comparison for moderators.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_get_block_comparison() {
		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message.', 'fanfiction-manager' ) ) );
		}

		if ( ! class_exists( 'Fanfic_Moderation_Messages' ) ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred.', 'fanfiction-manager' ) ) );
		}

		$message = Fanfic_Moderation_Messages::get_message( $message_id );
		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Message not found.', 'fanfiction-manager' ) ) );
		}

		$story_id = absint( $message['target_id'] );
		if ( 'story' !== $message['target_type'] || ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'No blocked-story comparison is available for this message.', 'fanfiction-manager' ) ) );
		}

		$snapshot_rows = function_exists( 'fanfic_get_block_snapshot' ) ? fanfic_get_block_snapshot( $story_id ) : array();

		if ( empty( $snapshot_rows ) ) {
			wp_send_json_error( array( 'message' => __( 'No block snapshot was found for this story.', 'fanfiction-manager' ) ) );
		}

		$has_reviewable_changes = false;
		if ( function_exists( 'fanfic_story_has_reviewable_modifications' ) ) {
			$has_reviewable_changes = fanfic_story_has_reviewable_modifications( $story_id );
		} elseif ( function_exists( 'fanfic_story_has_block_snapshot_changes' ) ) {
			$has_reviewable_changes = fanfic_story_has_block_snapshot_changes( $story_id );
		}

		if ( ! $has_reviewable_changes ) {
			wp_send_json_error( array( 'message' => __( 'No saved modifications were detected yet.', 'fanfiction-manager' ) ) );
		}

		wp_send_json_success(
			array(
				'message_id' => $message_id,
				'html'       => self::build_block_comparison_html( $story_id ),
			)
		);
	}

	/**
	 * Handle moderator blocking or unblocking a chapter from frontend controls.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_toggle_chapter_block() {
		$chapter_id         = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$block_reason       = isset( $_POST['block_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['block_reason'] ) ) : 'manual';
		$block_reason_text  = isset( $_POST['block_reason_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['block_reason_text'] ) ) : '';
		$moderator_id       = get_current_user_id();

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter.', 'fanfiction-manager' ) ) );
		}

		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Chapter not found.', 'fanfiction-manager' ) ) );
		}

		$is_blocked = function_exists( 'fanfic_is_chapter_blocked' ) ? fanfic_is_chapter_blocked( $chapter_id ) : false;

		if ( $is_blocked ) {
			if ( function_exists( 'fanfic_unblock_chapter' ) ) {
				fanfic_unblock_chapter( $chapter_id, $moderator_id );
			}

			$post_status  = get_post_status( $chapter_id );
			$status_class = 'publish' === $post_status ? 'published' : 'draft';
			$status_label = 'publish' === $post_status ? __( 'Visible', 'fanfiction-manager' ) : __( 'Hidden', 'fanfiction-manager' );

			wp_send_json_success(
				array(
					'message'      => __( 'Chapter unblocked successfully.', 'fanfiction-manager' ),
					'chapter_id'   => $chapter_id,
					'is_blocked'   => false,
					'post_status'  => $post_status,
					'status_class' => $status_class,
					'status_label' => $status_label,
				)
			);
		}

		if ( '' === $block_reason ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a block reason.', 'fanfiction-manager' ) ) );
		}

		$normalized_reason = function_exists( 'fanfic_normalize_block_reason_code' ) ? fanfic_normalize_block_reason_code( $block_reason, '' ) : trim( $block_reason );
		if ( '' === $normalized_reason ) {
			wp_send_json_error( array( 'message' => __( 'Invalid block reason.', 'fanfiction-manager' ) ) );
		}

		if ( function_exists( 'fanfic_block_reason_text_exceeds_limit' ) && fanfic_block_reason_text_exceeds_limit( $block_reason_text ) ) {
			wp_send_json_error( array( 'message' => __( 'Additional block details must be 500 characters or fewer.', 'fanfiction-manager' ) ) );
		}

		$normalized_reason_text = function_exists( 'fanfic_normalize_block_reason_text' ) ? fanfic_normalize_block_reason_text( $block_reason_text ) : sanitize_textarea_field( $block_reason_text );

		if ( function_exists( 'fanfic_block_chapter' ) ) {
			fanfic_block_chapter( $chapter_id, $normalized_reason, $moderator_id, $normalized_reason_text );
		}

		wp_send_json_success(
			array(
				'message'         => __( 'Chapter blocked successfully.', 'fanfiction-manager' ),
				'chapter_id'      => $chapter_id,
				'is_blocked'      => true,
				'post_status'     => get_post_status( $chapter_id ),
				'status_class'    => 'blocked',
				'status_label'    => __( 'Blocked', 'fanfiction-manager' ),
				'reason_label'    => function_exists( 'fanfic_get_block_reason_label' ) ? fanfic_get_block_reason_label( $normalized_reason ) : $normalized_reason,
				'reason_text'     => $normalized_reason_text,
				'reason_message'  => function_exists( 'fanfic_get_blocked_chapter_message' ) ? fanfic_get_blocked_chapter_message( $chapter_id ) : '',
			)
		);
	}

	/**
	 * Handle moderator taking action on a moderation message.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function ajax_mod_message_action() {
		$message_id    = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$action_type   = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$internal_note = isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '';
		$author_reply  = isset( $_POST['author_reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['author_reply'] ) ) : '';

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message ID.', 'fanfiction-manager' ) ) );
		}

		$valid_actions = array( 'unblock', 'ignore', 'delete' );
		if ( ! in_array( $action_type, $valid_actions, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'fanfiction-manager' ) ) );
		}

		if ( ! class_exists( 'Fanfic_Moderation_Messages' ) ) {
			wp_send_json_error( array( 'message' => __( 'An error occurred.', 'fanfiction-manager' ) ) );
		}

		$msg = Fanfic_Moderation_Messages::get_message( $message_id );
		if ( ! $msg ) {
			wp_send_json_error( array( 'message' => __( 'Message not found.', 'fanfiction-manager' ) ) );
		}

		if ( in_array( $msg['status'], array( 'resolved', 'deleted' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This message has already been closed.', 'fanfiction-manager' ) ) );
		}

		$mod_id      = get_current_user_id();
		$target_type = $msg['target_type'];
		$target_id   = absint( $msg['target_id'] );
		$author_id   = absint( $msg['author_id'] );

		$is_restricted = false;
		if ( 'story' === $target_type ) {
			$is_restricted = function_exists( 'fanfic_is_story_blocked' ) ? fanfic_is_story_blocked( $target_id ) : false;
		} elseif ( 'chapter' === $target_type ) {
			$is_restricted = function_exists( 'fanfic_is_chapter_blocked' ) ? fanfic_is_chapter_blocked( $target_id ) : false;
		} elseif ( 'user' === $target_type ) {
			$is_restricted = ( '1' === get_user_meta( $target_id, 'fanfic_banned', true ) );
		}

		$new_status = $msg['status'];

		switch ( $action_type ) {
			case 'unblock':
				if ( ! $is_restricted ) {
					wp_send_json_error( array( 'message' => __( 'This target is already unblocked.', 'fanfiction-manager' ) ) );
				}

				// Unblock the target.
				if ( 'story' === $target_type ) {
					if ( function_exists( 'fanfic_unblock_story' ) ) {
						fanfic_unblock_story( $target_id, $mod_id );
					}
				} elseif ( 'chapter' === $target_type ) {
					if ( function_exists( 'fanfic_unblock_chapter' ) ) {
						fanfic_unblock_chapter( $target_id, $mod_id );
					}
				} elseif ( 'user' === $target_type ) {
					// Reverse the ban.
					$user = get_userdata( $target_id );
					if ( $user ) {
						$original_role = get_user_meta( $target_id, 'fanfic_original_role', true );
						$user->set_role( $original_role ?: 'fanfiction_author' );
						delete_user_meta( $target_id, 'fanfic_banned' );
						delete_user_meta( $target_id, 'fanfic_banned_by' );
						delete_user_meta( $target_id, 'fanfic_banned_at' );
						delete_user_meta( $target_id, 'fanfic_original_role' );
						delete_user_meta( $target_id, 'fanfic_suspension_reason' );
						delete_user_meta( $target_id, 'fanfic_suspension_reason_text' );
						update_user_meta( $target_id, 'fanfic_unbanned_by', $mod_id );
						update_user_meta( $target_id, 'fanfic_unbanned_at', current_time( 'mysql' ) );
						do_action( 'fanfic_user_unbanned', $target_id, $mod_id );
					}
				}
				Fanfic_Moderation_Messages::update_status( $message_id, 'resolved', $mod_id, $internal_note, $author_reply );
				if ( function_exists( 'fanfic_clear_restriction_reply_message' ) ) {
					fanfic_clear_restriction_reply_message( $target_type, $target_id );
				}
				$new_status = 'resolved';

				// Notify author.
				if ( class_exists( 'Fanfic_Notifications' ) ) {
					$title = 'user' === $target_type ? '' : get_the_title( $target_id );
					$reply_suffix = $author_reply
						? ' ' . sprintf(
							/* translators: %s: moderator reply text */
							__( 'Moderator reply: %s', 'fanfiction-manager' ),
							$author_reply
						)
						: '';
					$notification_msg = $title
						? sprintf(
							__( 'Your %1$s "%2$s" has been unblocked.%3$s', 'fanfiction-manager' ),
							$target_type,
							$title,
							$reply_suffix
						)
						: sprintf( __( 'Your account has been unsuspended.%s', 'fanfiction-manager' ), $reply_suffix );
					Fanfic_Notifications::create_notification(
						$author_id,
						Fanfic_Notifications::TYPE_MOD_MESSAGE_UNBLOCKED,
						$notification_msg,
						array(),
						true
					);
				}
				break;

			case 'ignore':
				if ( 'ignored' === $msg['status'] ) {
					wp_send_json_error( array( 'message' => __( 'This message is already ignored.', 'fanfiction-manager' ) ) );
				}

				Fanfic_Moderation_Messages::update_status( $message_id, 'ignored', $mod_id, $internal_note, $author_reply );
				if ( function_exists( 'fanfic_set_restriction_reply_message' ) ) {
					fanfic_set_restriction_reply_message( $target_type, $target_id, $author_reply );
				}
				$new_status = 'ignored';
				if ( 'story' === $target_type ) {
					delete_post_meta( $target_id, '_fanfic_re_review_requested' );
				}

				// Notify author.
				if ( class_exists( 'Fanfic_Notifications' ) ) {
					$title = 'user' === $target_type ? '' : get_the_title( $target_id );
					$reply_suffix = $author_reply
						? ' ' . sprintf(
							/* translators: %s: moderator reply text */
							__( 'Moderator reply: %s', 'fanfiction-manager' ),
							$author_reply
						)
						: '';
					$notification_msg = $title
						? sprintf(
							__( 'Your message regarding "%1$s" has been reviewed. The restriction remains in place.%2$s', 'fanfiction-manager' ),
							$title,
							$reply_suffix
						)
						: sprintf(
							__( 'Your message regarding your account suspension has been reviewed. The restriction remains in place.%s', 'fanfiction-manager' ),
							$reply_suffix
						);
					Fanfic_Notifications::create_notification(
						$author_id,
						Fanfic_Notifications::TYPE_MOD_MESSAGE_IGNORED,
						$notification_msg
					);
				}

				// Log the action.
				if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
					Fanfic_Moderation_Log::insert( $mod_id, 'message_ignored', $target_type, $target_id, $internal_note );
				}
				break;

			case 'delete':
				Fanfic_Moderation_Messages::update_status( $message_id, 'deleted', $mod_id, $internal_note, $author_reply );
				if ( function_exists( 'fanfic_set_restriction_reply_message' ) ) {
					fanfic_set_restriction_reply_message( $target_type, $target_id, $author_reply );
				}
				$new_status = 'deleted';
				if ( 'story' === $target_type ) {
					delete_post_meta( $target_id, '_fanfic_re_review_requested' );
				}

				if ( $author_reply && class_exists( 'Fanfic_Notifications' ) ) {
					$title = 'user' === $target_type ? '' : get_the_title( $target_id );
					$notification_msg = $title
						? sprintf(
							__( 'A moderator replied regarding "%1$s": %2$s', 'fanfiction-manager' ),
							$title,
							$author_reply
						)
						: sprintf(
							__( 'A moderator replied regarding your account suspension: %s', 'fanfiction-manager' ),
							$author_reply
						);
					Fanfic_Notifications::create_notification(
						$author_id,
						Fanfic_Notifications::TYPE_MOD_MESSAGE_DELETED,
						$notification_msg
					);
				}

				// Log the action.
				if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
					Fanfic_Moderation_Log::insert( $mod_id, 'message_deleted', $target_type, $target_id, $internal_note );
				}
				break;
		}

		$restriction_context = function_exists( 'fanfic_get_restriction_context' ) ? fanfic_get_restriction_context( $target_type, $target_id ) : array();
		$status_labels = array(
			'unread'   => __( 'Unread', 'fanfiction-manager' ),
			'ignored'  => __( 'Ignored', 'fanfiction-manager' ),
			'resolved' => __( 'Resolved', 'fanfiction-manager' ),
			'deleted'  => __( 'Deleted', 'fanfiction-manager' ),
		);

		wp_send_json_success( array(
			'message'            => __( 'Action completed successfully.', 'fanfiction-manager' ),
			'action_type'        => $action_type,
			'message_id'         => $message_id,
			'new_status'         => $new_status,
			'status_label'       => isset( $status_labels[ $new_status ] ) ? $status_labels[ $new_status ] : ucfirst( $new_status ),
			'target_type'        => $target_type,
			'target_id'          => $target_id,
			'is_restricted'      => ! empty( $restriction_context['is_restricted'] ),
			'reason_message'     => isset( $restriction_context['reason_message'] ) ? $restriction_context['reason_message'] : '',
			'internal_note'      => $internal_note,
			'author_reply'       => $author_reply,
		) );
	}

	/**
	 * Handle moderator sending a chat reply on an open moderation thread.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	public static function ajax_send_moderation_reply() {
		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$reply      = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';
		$moderator_id = get_current_user_id();

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message thread.', 'fanfiction-manager' ) ) );
		}

		if ( ! class_exists( 'Fanfic_Moderation_Messages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Messages system is not available.', 'fanfiction-manager' ) ) );
		}

		$result = Fanfic_Moderation_Messages::send_moderator_message( $message_id, $moderator_id, $reply );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$thread = Fanfic_Moderation_Messages::get_message( $message_id );
		if ( ! $thread ) {
			wp_send_json_error( array( 'message' => __( 'Message thread not found.', 'fanfiction-manager' ) ) );
		}

		$target_type = isset( $thread['target_type'] ) ? sanitize_key( $thread['target_type'] ) : '';
		$target_id   = isset( $thread['target_id'] ) ? absint( $thread['target_id'] ) : 0;
		$author_id   = isset( $thread['author_id'] ) ? absint( $thread['author_id'] ) : 0;

		if ( function_exists( 'fanfic_set_restriction_reply_message' ) ) {
			fanfic_set_restriction_reply_message( $target_type, $target_id, $reply );
		}

		if ( $author_id > 0 && class_exists( 'Fanfic_Notifications' ) ) {
			$title = 'user' === $target_type ? '' : get_the_title( $target_id );
			$notification_msg = $title
				? sprintf(
					__( 'Moderator replied regarding "%1$s": %2$s', 'fanfiction-manager' ),
					$title,
					$reply
				)
				: sprintf(
					__( 'Moderator replied regarding your account suspension: %s', 'fanfiction-manager' ),
					$reply
				);

			Fanfic_Notifications::create_notification(
				$author_id,
				Fanfic_Notifications::TYPE_MOD_MESSAGE_REPLY,
				$notification_msg
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Reply sent successfully.', 'fanfiction-manager' ),
				'message_id' => $message_id,
			)
		);
	}
}
