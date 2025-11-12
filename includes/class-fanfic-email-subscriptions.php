<?php
/**
 * Email Subscriptions System Class
 *
 * Manages email-only subscriptions for both logged-in and anonymous users.
 * Handles subscription creation, verification, unsubscribe, and token management.
 *
 * @package FanfictionManager
 * @subpackage EmailSubscriptions
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Email_Subscriptions
 *
 * Handles email subscription management for story and author updates.
 * Supports both anonymous users (email-only) and logged-in users.
 *
 * @since 1.0.0
 */
class Fanfic_Email_Subscriptions {

	/**
	 * Initialize the class
	 *
	 * Sets up WordPress hooks for email subscription functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Hook into chapter publish event
		add_action( 'transition_post_status', array( __CLASS__, 'handle_chapter_publish' ), 10, 3 );

		// Hook into comment notifications
		add_action( 'wp_insert_comment', array( __CLASS__, 'handle_comment_notify' ), 10, 2 );

		// Hook into follow notifications
		add_action( 'fanfic_toggle_follow', array( __CLASS__, 'handle_follow_notify' ), 10, 4 );

		// AJAX handlers
		add_action( 'wp_ajax_fanfic_subscribe_email', array( __CLASS__, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_fanfic_subscribe_email', array( __CLASS__, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_fanfic_verify_subscription', array( __CLASS__, 'ajax_verify_subscription' ) );
		add_action( 'wp_ajax_nopriv_fanfic_verify_subscription', array( __CLASS__, 'ajax_verify_subscription' ) );

		// Handle unsubscribe from query parameters
		add_action( 'template_redirect', array( __CLASS__, 'handle_unsubscribe_link' ) );
	}

	/**
	 * Subscribe user to story or author
	 *
	 * @since 1.0.0
	 * @param string $email             Email address.
	 * @param int    $target_id         Story ID or Author ID.
	 * @param string $subscription_type Subscription type: 'story' or 'author'.
	 * @param string $source            Source of subscription: 'form', 'api', etc.
	 * @return array|WP_Error Result array on success, WP_Error on failure.
	 */
	public static function subscribe( $email, $target_id, $subscription_type, $source = 'form' ) {
		global $wpdb;

		// Validate email
		$email = Fanfic_Input_Validation::sanitize_email_for_subscription( $email );
		if ( is_wp_error( $email ) ) {
			return $email;
		}

		// Validate target_id
		$target_id = absint( $target_id );
		if ( ! $target_id ) {
			return new WP_Error( 'invalid_target_id', __( 'Invalid target ID.', 'fanfiction-manager' ) );
		}

		// Validate subscription type
		$subscription_type = Fanfic_Input_Validation::validate_follow_type( $subscription_type );
		if ( is_wp_error( $subscription_type ) ) {
			return $subscription_type;
		}

		// Check if already subscribed
		$existing = self::get_subscription( $email, $target_id, $subscription_type );
		if ( $existing ) {
			if ( $existing->verified ) {
				return new WP_Error( 'already_subscribed', __( 'You are already subscribed.', 'fanfiction-manager' ) );
			} else {
				// Resend verification email
				$result = self::send_verification_email( $email, $existing->token, $target_id, $subscription_type );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'A verification email has been resent. Please check your inbox.', 'fanfiction-manager' ),
				);
			}
		}

		// Generate token
		$token = Fanfic_Input_Validation::generate_unsubscribe_token();

		// Insert subscription
		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';
		$result = $wpdb->insert(
			$table_name,
			array(
				'email'             => $email,
				'target_id'         => $target_id,
				'subscription_type' => $subscription_type,
				'token'             => $token,
				'verified'          => 0,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'subscription_failed', __( 'Failed to create subscription. Please try again.', 'fanfiction-manager' ) );
		}

		// Send verification email
		$verification_result = self::send_verification_email( $email, $token, $target_id, $subscription_type );
		if ( is_wp_error( $verification_result ) ) {
			// Delete subscription if verification email fails
			$wpdb->delete(
				$table_name,
				array(
					'email'             => $email,
					'target_id'         => $target_id,
					'subscription_type' => $subscription_type,
				),
				array( '%s', '%d', '%s' )
			);
			return $verification_result;
		}

		// Fire action hook
		do_action( 'fanfic_email_subscribed', $email, $target_id, $subscription_type, $source );

		return array(
			'status'  => 'success',
			'message' => __( 'Subscription created! Please check your email to verify your subscription.', 'fanfiction-manager' ),
		);
	}

	/**
	 * Verify email subscription
	 *
	 * @since 1.0.0
	 * @param string $token Verification token.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_subscription( $token ) {
		global $wpdb;

		// Validate token
		$token = sanitize_text_field( $token );
		if ( empty( $token ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid verification token.', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		// Find subscription by token
		$subscription = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE token = %s LIMIT 1",
			$token
		) );

		if ( ! $subscription ) {
			return new WP_Error( 'subscription_not_found', __( 'Subscription not found or already verified.', 'fanfiction-manager' ) );
		}

		// Check if already verified
		if ( $subscription->verified ) {
			return new WP_Error( 'already_verified', __( 'This subscription has already been verified.', 'fanfiction-manager' ) );
		}

		// Set verified = 1
		$result = $wpdb->update(
			$table_name,
			array( 'verified' => 1 ),
			array( 'id' => $subscription->id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'verification_failed', __( 'Failed to verify subscription. Please try again.', 'fanfiction-manager' ) );
		}

		// Fire action hook
		do_action( 'fanfic_email_verified', $subscription->email, $subscription->target_id, $subscription->subscription_type );

		return true;
	}

	/**
	 * Unsubscribe email from target
	 *
	 * @since 1.0.0
	 * @param string $email             Email address.
	 * @param int    $target_id         Story ID or Author ID.
	 * @param string $subscription_type Subscription type.
	 * @param string $token             Unsubscribe token for verification.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function unsubscribe( $email, $target_id, $subscription_type, $token ) {
		global $wpdb;

		// Verify token
		$verification = Fanfic_Input_Validation::verify_unsubscribe_token( $token, $email, $target_id, $subscription_type );
		if ( is_wp_error( $verification ) ) {
			return $verification;
		}

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		// Delete subscription
		$result = $wpdb->delete(
			$table_name,
			array(
				'email'             => $email,
				'target_id'         => $target_id,
				'subscription_type' => $subscription_type,
			),
			array( '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'unsubscribe_failed', __( 'Failed to unsubscribe. Please try again.', 'fanfiction-manager' ) );
		}

		// Fire action hook
		do_action( 'fanfic_email_unsubscribed', $email, $target_id, $subscription_type );

		return true;
	}

	/**
	 * Unsubscribe all subscriptions for an email address
	 *
	 * @since 1.0.0
	 * @param string $email Email address.
	 * @param string $token Token for verification.
	 * @return int|WP_Error Count of deleted subscriptions on success, WP_Error on failure.
	 */
	public static function unsubscribe_all( $email, $token ) {
		global $wpdb;

		// Validate email
		$email = sanitize_email( $email );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'fanfiction-manager' ) );
		}

		// Validate token - check that at least one subscription exists with this token
		$token = sanitize_text_field( $token );
		if ( empty( $token ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid token.', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		// Verify token matches any subscription for this email
		$valid_subscription = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE email = %s AND token = %s LIMIT 1",
			$email,
			$token
		) );

		if ( ! $valid_subscription ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired token.', 'fanfiction-manager' ) );
		}

		// Delete all subscriptions for this email
		$result = $wpdb->delete(
			$table_name,
			array( 'email' => $email ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'unsubscribe_all_failed', __( 'Failed to unsubscribe. Please try again.', 'fanfiction-manager' ) );
		}

		// Fire action hook
		do_action( 'fanfic_email_unsubscribed_all', $email, $result );

		return absint( $result );
	}

	/**
	 * Get subscriptions
	 *
	 * @since 1.0.0
	 * @param string $email             Email address (optional).
	 * @param int    $target_id         Target ID (optional).
	 * @param string $subscription_type Subscription type (optional).
	 * @return array Array of subscription objects.
	 */
	public static function get_subscriptions( $email = null, $target_id = null, $subscription_type = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';
		$sql = "SELECT * FROM {$table_name} WHERE 1=1";
		$params = array();

		if ( $email ) {
			$sql .= ' AND email = %s';
			$params[] = sanitize_email( $email );
		}

		if ( $target_id ) {
			$sql .= ' AND target_id = %d';
			$params[] = absint( $target_id );
		}

		if ( $subscription_type ) {
			$sql .= ' AND subscription_type = %s';
			$params[] = sanitize_text_field( $subscription_type );
		}

		$sql .= ' ORDER BY created_at DESC';

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$subscriptions = $wpdb->get_results( $sql );

		return $subscriptions ? $subscriptions : array();
	}

	/**
	 * Get single subscription
	 *
	 * @since 1.0.0
	 * @param string $email             Email address.
	 * @param int    $target_id         Target ID.
	 * @param string $subscription_type Subscription type.
	 * @return object|null Subscription object or null.
	 */
	public static function get_subscription( $email, $target_id, $subscription_type ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		$subscription = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name}
			WHERE email = %s AND target_id = %d AND subscription_type = %s
			LIMIT 1",
			sanitize_email( $email ),
			absint( $target_id ),
			sanitize_text_field( $subscription_type )
		) );

		return $subscription;
	}

	/**
	 * Get verified subscribers for a target
	 *
	 * @since 1.0.0
	 * @param int    $target_id         Target ID (story or author).
	 * @param string $subscription_type Subscription type: 'story' or 'author'.
	 * @return array Array of verified email addresses.
	 */
	public static function get_verified_subscribers( $target_id, $subscription_type = 'story' ) {
		global $wpdb;

		$target_id = absint( $target_id );
		if ( ! $target_id ) {
			return array();
		}

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		$emails = $wpdb->get_col( $wpdb->prepare(
			"SELECT email FROM {$table_name}
			WHERE target_id = %d AND subscription_type = %s AND verified = 1
			ORDER BY created_at ASC",
			$target_id,
			sanitize_text_field( $subscription_type )
		) );

		return $emails ? $emails : array();
	}

	/**
	 * Send verification email
	 *
	 * Sends immediately (not via WP-Cron) because user needs to verify.
	 *
	 * @since 1.0.0
	 * @param string $email             Email address.
	 * @param string $token             Verification token.
	 * @param int    $target_id         Target ID (story or author).
	 * @param string $subscription_type Subscription type.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function send_verification_email( $email, $token, $target_id, $subscription_type ) {
		// Get target information
		$target_name = '';
		if ( 'story' === $subscription_type ) {
			$story = get_post( $target_id );
			if ( $story ) {
				$target_name = $story->post_title;
			}
		} else {
			$author = get_userdata( $target_id );
			if ( $author ) {
				$target_name = $author->display_name;
			}
		}

		// Build verification URL
		$verify_url = add_query_arg(
			array(
				'action' => 'verify_subscription',
				'token'  => $token,
			),
			home_url( '/' )
		);

		// Email subject
		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Verify your email subscription - %s', 'fanfiction-manager' ),
			get_bloginfo( 'name' )
		);

		// Email body
		$message = sprintf(
			/* translators: 1: target name, 2: subscription type, 3: verification URL */
			__(
				"You requested to subscribe to updates for %1\$s.\n\n" .
				"Please click the link below to verify your email address:\n\n" .
				"%3\$s\n\n" .
				"If you did not request this subscription, you can safely ignore this email.\n\n" .
				"Thank you,\n" .
				"%4\$s",
				'fanfiction-manager'
			),
			$target_name,
			$subscription_type,
			$verify_url,
			get_bloginfo( 'name' )
		);

		// Send email (synchronously - user needs immediate verification)
		$result = wp_mail( $email, $subject, $message );

		if ( ! $result ) {
			return new WP_Error( 'email_send_failed', __( 'Failed to send verification email. Please try again.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Handle chapter publish event
	 *
	 * Triggered when chapter transitions to publish status.
	 * Passes to Email Queue for async processing.
	 *
	 * @since 1.0.0
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public static function handle_chapter_publish( $new_status, $old_status, $post ) {
		// Only handle chapter posts
		if ( 'fanfiction_chapter' !== $post->post_type ) {
			return;
		}

		// Only trigger on publish (not on updates)
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Let Email Queue handle the async processing
		if ( class_exists( 'Fanfic_Email_Queue' ) ) {
			Fanfic_Email_Queue::handle_chapter_publish( $new_status, $old_status, $post );
		}
	}

	/**
	 * Handle comment notification
	 *
	 * @since 1.0.0
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Comment approval status.
	 * @return void
	 */
	public static function handle_comment_notify( $comment_id, $approved ) {
		// Only process approved comments
		if ( 1 !== $approved && 'approved' !== $approved ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Create notification for post author (handled by Notifications class)
		if ( class_exists( 'Fanfic_Notifications' ) ) {
			Fanfic_Notifications::create_comment_notification( $comment_id );
		}
	}

	/**
	 * Handle follow notification
	 *
	 * @since 1.0.0
	 * @param int    $user_id     Follower user ID.
	 * @param int    $target_id   Target ID (story or author).
	 * @param string $follow_type Follow type: 'story' or 'author'.
	 * @param bool   $is_follow   True if following, false if unfollowing.
	 * @return void
	 */
	public static function handle_follow_notify( $user_id, $target_id, $follow_type, $is_follow ) {
		// Only process follows (not unfollows)
		if ( ! $is_follow ) {
			return;
		}

		// Create notification (handled by Notifications class)
		if ( class_exists( 'Fanfic_Notifications' ) ) {
			Fanfic_Notifications::create_follow_notification( $user_id, $target_id, $follow_type, $target_id );
		}
	}

	/**
	 * Handle unsubscribe link from email
	 *
	 * Processes unsubscribe actions from query parameters.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_unsubscribe_link() {
		// Check if unsubscribe action
		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['action'] );

		// Handle global unsubscribe
		if ( 'unsubscribe_all' === $action ) {
			if ( ! isset( $_GET['email'] ) || ! isset( $_GET['token'] ) ) {
				wp_die( __( 'Invalid unsubscribe link.', 'fanfiction-manager' ) );
			}

			$email = sanitize_email( $_GET['email'] );
			$token = sanitize_text_field( $_GET['token'] );

			$result = self::unsubscribe_all( $email, $token );

			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}

			wp_die( sprintf(
				/* translators: %d: number of subscriptions */
				__( 'Successfully unsubscribed from %d subscription(s).', 'fanfiction-manager' ),
				$result
			) );
		}

		// Handle single unsubscribe
		if ( 'unsubscribe' === $action ) {
			if ( ! isset( $_GET['email'] ) || ! isset( $_GET['token'] ) || ! isset( $_GET['target_id'] ) || ! isset( $_GET['type'] ) ) {
				wp_die( __( 'Invalid unsubscribe link.', 'fanfiction-manager' ) );
			}

			$email = sanitize_email( $_GET['email'] );
			$token = sanitize_text_field( $_GET['token'] );
			$target_id = absint( $_GET['target_id'] );
			$type = sanitize_text_field( $_GET['type'] );

			$result = self::unsubscribe( $email, $target_id, $type, $token );

			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}

			wp_die( __( 'Successfully unsubscribed from email notifications.', 'fanfiction-manager' ) );
		}

		// Handle verification
		if ( 'verify_subscription' === $action ) {
			if ( ! isset( $_GET['token'] ) ) {
				wp_die( __( 'Invalid verification link.', 'fanfiction-manager' ) );
			}

			$token = sanitize_text_field( $_GET['token'] );
			$result = self::verify_subscription( $token );

			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}

			wp_die( __( 'Your email subscription has been verified! You will now receive email notifications.', 'fanfiction-manager' ) );
		}
	}

	/**
	 * AJAX handler for email subscription
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_subscribe() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_ajax_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Get parameters
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$subscription_type = isset( $_POST['subscription_type'] ) ? sanitize_text_field( $_POST['subscription_type'] ) : '';

		// Subscribe
		$result = self::subscribe( $email, $target_id, $subscription_type, 'ajax' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for verification
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_verify_subscription() {
		// Get token
		$token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

		// Verify
		$result = self::verify_subscription( $token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Subscription verified!', 'fanfiction-manager' ) ) );
	}
}
