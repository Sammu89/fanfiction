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

		// AJAX handlers are now registered in class-fanfic-ajax-handlers.php

		// Handle unsubscribe from query parameters
		add_action( 'template_redirect', array( __CLASS__, 'handle_unsubscribe_link' ) );
	}

	/**
	 * Subscribe user to story or author
	 *
	 * No email verification required — subscriptions are immediately active.
	 *
	 * @since 1.0.0
	 * @param string $email             Email address.
	 * @param int    $target_id         Story ID or Author ID.
	 * @param string $subscription_type Subscription type: 'story' or 'author'.
	 * @param string $source            Source of subscription: 'form', 'api', 'follow', etc.
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
		$subscription_type = Fanfic_Input_Validation::validate_subscription_type( $subscription_type );
		if ( is_wp_error( $subscription_type ) ) {
			return $subscription_type;
		}

		// Check if already subscribed
		$existing = self::get_subscription( $email, $target_id, $subscription_type );
		if ( $existing ) {
			// Ensure legacy unverified subscriptions are activated.
			if ( ! $existing->verified ) {
				$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';
				$wpdb->update(
					$table_name,
					array( 'verified' => 1 ),
					array( 'id' => $existing->id ),
					array( '%d' ),
					array( '%d' )
				);
			}
			return array(
				'status'  => 'success',
				'message' => __( 'You are already subscribed to email updates.', 'fanfiction-manager' ),
			);
		}

		// Generate token (used for unsubscribe links)
		$token = Fanfic_Input_Validation::generate_unsubscribe_token();

		// Insert subscription — immediately active (verified = 1).
		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';
		$result = $wpdb->insert(
			$table_name,
			array(
				'email'             => $email,
				'target_id'         => $target_id,
				'subscription_type' => $subscription_type,
				'token'             => $token,
				'verified'          => 1,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'subscription_failed', __( 'Failed to create subscription. Please try again.', 'fanfiction-manager' ) );
		}

		// Fire action hook
		do_action( 'fanfic_email_subscribed', $email, $target_id, $subscription_type, $source );

		return array(
			'status'  => 'success',
			'message' => __( 'You will now receive email updates.', 'fanfiction-manager' ),
		);
	}

	/**
	 * Subscribe from follow modal (streamlined for follow flow).
	 *
	 * Creates an email subscription for a story from the follow modal.
	 * Immediately active — no verification required.
	 *
	 * @since 1.8.0
	 * @param string $email    Email address.
	 * @param int    $story_id Story ID.
	 * @return array|WP_Error Result array on success, WP_Error on failure.
	 */
	public static function subscribe_from_follow( $email, $story_id ) {
		return self::subscribe( $email, $story_id, 'story', 'follow' );
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
	 * Unsubscribe email from story during unfollow (no token required)
	 *
	 * Used when a user actively unfollows a story via the UI.
	 * Since this is an intentional user action (not an email link), no token is needed.
	 *
	 * @since 1.8.0
	 * @param string $email    Email address.
	 * @param int    $story_id Story ID.
	 * @return bool True if a subscription was removed, false if none existed.
	 */
	public static function unsubscribe_on_unfollow( $email, $story_id ) {
		global $wpdb;

		$email    = sanitize_email( $email );
		$story_id = absint( $story_id );

		if ( ! is_email( $email ) || ! $story_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		$result = $wpdb->delete(
			$table_name,
			array(
				'email'             => $email,
				'target_id'         => $story_id,
				'subscription_type' => 'story',
			),
			array( '%s', '%d', '%s' )
		);

		if ( $result > 0 ) {
			do_action( 'fanfic_email_unsubscribed', $email, $story_id, 'story' );
			return true;
		}

		return false;
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

			$email     = sanitize_email( $_GET['email'] );
			$token     = sanitize_text_field( $_GET['token'] );
			$target_id = absint( $_GET['target_id'] );
			$type      = sanitize_text_field( $_GET['type'] );

			$result = self::unsubscribe( $email, $target_id, $type, $token );

			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}

			// Build a descriptive confirmation message.
			$story_name = '';
			if ( 'story' === $type ) {
				$story = get_post( $target_id );
				if ( $story ) {
					$story_name = $story->post_title;
				}
			}

			if ( $story_name ) {
				$message = sprintf(
					/* translators: %s: story title */
					__( 'You will no longer receive email updates for "%s".', 'fanfiction-manager' ),
					$story_name
				);
			} else {
				$message = __( 'You have been successfully unsubscribed from email updates.', 'fanfiction-manager' );
			}

			wp_die( $message );
		}
	}

}
