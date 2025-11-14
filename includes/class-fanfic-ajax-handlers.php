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

		// Bookmark pagination (authenticated only)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_load_user_bookmarks',
			array( __CLASS__, 'ajax_load_user_bookmarks' ),
			true, // Require login
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Check rating eligibility (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_check_rating_eligibility',
			array( __CLASS__, 'ajax_check_rating_eligibility' ),
			false, // Allow anonymous
			array(
				'rate_limit'  => true,
				'capability'  => 'read',
			)
		);

		// Check like status (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_check_like_status',
			array( __CLASS__, 'ajax_check_like_status' ),
			false, // Allow anonymous
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

		// Verify email subscription (public + authenticated)
		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_verify_subscription',
			array( __CLASS__, 'ajax_verify_subscription' ),
			false, // Allow anonymous
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

	/**
	 * AJAX: Load user bookmarks with pagination
	 *
	 * Retrieves paginated bookmarks for the current user.
	 * Authenticated users only.
	 *
	 * @since 1.0.15
	 * @return void Sends JSON response.
	 */
	public static function ajax_load_user_bookmarks() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'offset' ),
			array( 'bookmark_type' )
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$offset = absint( $params['offset'] );
		$bookmark_type = isset( $params['bookmark_type'] ) ? sanitize_text_field( $params['bookmark_type'] ) : 'all';
		$user_id = get_current_user_id();

		// Validate bookmark_type
		$allowed_types = array( 'all', 'story', 'chapter' );
		if ( ! in_array( $bookmark_type, $allowed_types, true ) ) {
			Fanfic_AJAX_Security::send_error_response(
				'invalid_bookmark_type',
				__( 'Invalid bookmark type.', 'fanfiction-manager' ),
				400
			);
		}

		// Convert 'all' to null for get_user_bookmarks function
		$bookmark_type_param = ( 'all' === $bookmark_type ) ? null : $bookmark_type;

		// Get bookmarks (20 per page)
		$per_page = 20;
		$bookmarks = Fanfic_Bookmarks::get_user_bookmarks( $user_id, $bookmark_type_param, $per_page, $offset );

		// Get total count for has_more calculation
		$total_count = Fanfic_Bookmarks::get_bookmarks_count( $user_id, $bookmark_type_param );

		// Build HTML for each bookmark
		$html_output = '';
		foreach ( $bookmarks as $bookmark ) {
			if ( 'chapter' === $bookmark['bookmark_type'] ) {
				$html_output .= Fanfic_Bookmarks::render_chapter_bookmark_item( $bookmark );
			} else {
				$html_output .= Fanfic_Bookmarks::render_story_bookmark_item( $bookmark );
			}
		}

		// Calculate has_more
		$has_more = ( $offset + $per_page ) < $total_count;

		// Return success response
		Fanfic_AJAX_Security::send_success_response(
			array(
				'html'        => $html_output,
				'count'       => count( $bookmarks ),
				'total_count' => $total_count,
				'has_more'    => $has_more,
			),
			__( 'Bookmarks loaded successfully.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Check rating eligibility
	 *
	 * Checks if user can rate and returns existing rating if any.
	 * Public endpoint - allows anonymous checks.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_check_rating_eligibility() {
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

		$user_id = is_user_logged_in() ? get_current_user_id() : null;
		$existing_rating = Fanfic_Rating_System::user_has_rated( $chapter_id, $user_id );

		Fanfic_AJAX_Security::send_success_response(
			array(
				'can_vote'        => true,
				'existing_rating' => $existing_rating ?: null,
			),
			__( 'Eligibility checked.', 'fanfiction-manager' )
		);
	}

	/**
	 * AJAX: Check like status
	 *
	 * Checks if user/anonymous has liked a chapter.
	 * Public endpoint - uses cookies for anonymous users.
	 *
	 * @since 1.0.16
	 * @return void Sends JSON response.
	 */
	public static function ajax_check_like_status() {
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

		$user_id = is_user_logged_in() ? get_current_user_id() : null;
		$is_liked = Fanfic_Like_System::user_has_liked( $chapter_id, $user_id );

		Fanfic_AJAX_Security::send_success_response(
			array(
				'is_liked'    => $is_liked,
				'like_count'  => Fanfic_Like_System::get_chapter_likes( $chapter_id ),
			),
			__( 'Like status retrieved.', 'fanfiction-manager' )
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
	public static function ajax_verify_subscription() {
		// Get and validate parameters
		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'token' ),
			array()
		);

		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$params->get_error_code(),
				$params->get_error_message(),
				400
			);
		}

		$token = sanitize_text_field( $params['token'] );

		// Verify subscription
		$result = Fanfic_Email_Subscriptions::verify_subscription( $token );

		if ( is_wp_error( $result ) ) {
			Fanfic_AJAX_Security::send_error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		Fanfic_AJAX_Security::send_success_response(
			$result,
			__( 'Subscription verified successfully.', 'fanfiction-manager' )
		);
	}

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
