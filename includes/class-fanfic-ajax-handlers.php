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
 * - Story/chapter bookmarks (authenticated only)
 * - Story/author follows (authenticated only)
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
		// Rating endpoints (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_submit_rating',
			array( __CLASS__, 'ajax_submit_rating' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Like endpoints (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_like',
			array( __CLASS__, 'ajax_toggle_like' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

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

		// Bookmark endpoints (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_bookmark',
			array( __CLASS__, 'ajax_toggle_bookmark' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Follow endpoints (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_follow',
			array( __CLASS__, 'ajax_toggle_follow' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Email notification toggle (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_toggle_email_notifications',
			array( __CLASS__, 'ajax_toggle_email_notifications' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Email subscription (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_subscribe_email',
			array( __CLASS__, 'ajax_subscribe_email' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

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
	}

	/**
	 * AJAX: Submit or update chapter rating
	 *
	 * Handles both anonymous (cookie-based) and authenticated ratings.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_submit_rating() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'chapter_id', 'rating' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$chapter_id = absint( $params['chapter_id'] );
		$rating = absint( $params['rating'] );

		// Validate rating value
		if ( $rating < 1 || $rating > 5 ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_rating',
				__( 'Rating must be between 1 and 5 stars.', 'fanfiction-manager' ),
				400
			);
		}

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_chapter',
				__( 'Chapter not found.', 'fanfiction-manager' ),
				404
			);
		}

		// Get user ID (null for anonymous - cookies handled by rating system)
		$user_id = is_user_logged_in() ? get_current_user_id() : null;

		// Submit rating
		$result = Fanfic_Rating_System::submit_rating( $chapter_id, $rating, $user_id );

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		// Return success with updated stats
		Fanfic_AJAX_Security::send_success_response(
			$result,
			__( 'Rating submitted successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Toggle chapter like
	 *
	 * Handles both anonymous (cookie-based) and authenticated likes.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_toggle_like() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'chapter_id' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$chapter_id = absint( $params['chapter_id'] );

		// Verify chapter exists
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_chapter',
				__( 'Chapter not found.', 'fanfiction-manager' ),
				404
			);
		}

		// Get user ID (null for anonymous - cookies handled by like system)
		$user_id = is_user_logged_in() ? get_current_user_id() : null;

		// Toggle like
		$result = Fanfic_Like_System::toggle_like( $chapter_id, $user_id );

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		// Return success with updated stats
		Fanfic_AJAX_Security::send_success_response(
			$result,
			$result['is_liked']
				? __( 'Chapter liked!', 'fanfiction-manager' )
				: __( 'Like removed.', 'fanfiction-manager' )
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
	 * AJAX: Toggle bookmark
	 *
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_toggle_bookmark() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'post_id', 'bookmark_type' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$post_id = absint( $params['post_id'] );
		$bookmark_type = sanitize_text_field( $params['bookmark_type'] );
		$user_id = get_current_user_id();

		// Validate bookmark type
		if ( ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_bookmark_type',
				__( 'Invalid bookmark type.', 'fanfiction-manager' ),
				400
			);
		}

		// Toggle bookmark
		$result = Fanfic_Bookmarks::toggle_bookmark( $user_id, $post_id, $bookmark_type );

		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			Fanfic_AJAX_Security::send_error_response(
				'bookmark_failed',
				isset( $result['error'] ) ? $result['error'] : __( 'Failed to toggle bookmark.', 'fanfiction-manager' ),
				400
			);
		}

		// Return success
		Fanfic_AJAX_Security::send_success_response(
			array( 'is_bookmarked' => $result['is_bookmarked'] ),
			$result['is_bookmarked']
				? __( 'Bookmark added!', 'fanfiction-manager' )
				: __( 'Bookmark removed.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Toggle follow (story or author)
	 *
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_toggle_follow() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'target_id', 'follow_type' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$target_id = absint( $params['target_id'] );
		$follow_type = sanitize_text_field( $params['follow_type'] );
		$user_id = get_current_user_id();

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_follow_type',
				__( 'Invalid follow type.', 'fanfiction-manager' ),
				400
			);
		}

		// Toggle follow
		$result = Fanfic_Follows::toggle_follow( $user_id, $target_id, $follow_type );

		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			Fanfic_AJAX_Security::send_error_response(
				'follow_failed',
				isset( $result['error'] ) ? $result['error'] : __( 'Failed to toggle follow.', 'fanfiction-manager' ),
				400
			);
		}

		// Return success
		Fanfic_AJAX_Security::send_success_response(
			array(
				'is_following' => $result['is_following'],
				'email_enabled' => $result['email_enabled'] ?? false,
			),
			$result['is_following']
				? __( 'Now following!', 'fanfiction-manager' )
				: __( 'Unfollowed.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Toggle email notifications for a follow
	 *
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_toggle_email_notifications() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'target_id', 'follow_type' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$target_id = absint( $params['target_id'] );
		$follow_type = sanitize_text_field( $params['follow_type'] );
		$user_id = get_current_user_id();

		// Validate follow type
		if ( ! in_array( $follow_type, array( 'story', 'author' ), true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_follow_type',
				__( 'Invalid follow type.', 'fanfiction-manager' ),
				400
			);
		}

		// Get current state
		$current_state = Fanfic_Follows::is_email_enabled( $user_id, $target_id, $follow_type );

		// Toggle to opposite state
		$new_state = ! $current_state;
		$result = Fanfic_Follows::toggle_email_notifications( $user_id, $target_id, $follow_type, $new_state );

		if ( false === $result ) {
			Fanfic_AJAX_Security::send_error_response(
				'toggle_failed',
				__( 'Failed to toggle email notifications.', 'fanfiction-manager' ),
				400
			);
		}

		// Return success
		Fanfic_AJAX_Security::send_success_response(
			array( 'email_enabled' => $new_state ),
			$new_state
				? __( 'Email notifications enabled.', 'fanfiction-manager' )
				: __( 'Email notifications disabled.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Subscribe to story/author via email
	 *
	 * Public endpoint - allows anonymous email subscriptions.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_subscribe_email() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'email', 'target_id', 'subscription_type' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$email = sanitize_email( $params['email'] );
		$target_id = absint( $params['target_id'] );
		$subscription_type = sanitize_text_field( $params['subscription_type'] );

		// Validate email
		if ( ! is_email( $email ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_email',
				__( 'Please enter a valid email address.', 'fanfiction-manager' ),
				400
			);
		}

		// Subscribe
		$result = Fanfic_Email_Subscriptions::subscribe( $email, $target_id, $subscription_type, 'form' );

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		// Return success
		Fanfic_AJAX_Security::send_success_response(
			$result,
			isset( $result['message'] )
				? $result['message']
				: __( 'Subscription successful!', 'fanfiction-manager' )
		);
	}

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

		// Get user ID if logged in
		$user_id = is_user_logged_in() ? get_current_user_id() : null;

		// Batch load stats
		$stats = Fanfic_Batch_Loader::batch_load_chapter_stats( $chapter_ids, $user_id );

		// Return stats
		Fanfic_AJAX_Security::send_success_response(
			array( 'stats' => $stats ),
			__( 'Stats loaded successfully.', 'fanfiction-manager' )
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
}
