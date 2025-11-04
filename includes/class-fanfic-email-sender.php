<?php
/**
 * Email Sending System Class
 *
 * Handles email queue management and batch sending via WP-Cron.
 *
 * @package FanfictionManager
 * @subpackage Notifications
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Email_Sender
 *
 * Manages email queue and batch sending.
 *
 * @since 1.0.0
 */
class Fanfic_Email_Sender {

	/**
	 * Option name for email queue
	 */
	const QUEUE_OPTION = 'fanfic_email_queue';

	/**
	 * Option name for email log
	 */
	const LOG_OPTION = 'fanfic_email_log';

	/**
	 * Max emails per batch
	 */
	const BATCH_SIZE = 50;

	/**
	 * Max retry attempts
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Initialize the email sender class
	 *
	 * Sets up WordPress hooks and cron schedules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register cron hooks
		add_action( 'fanfic_process_email_queue', array( __CLASS__, 'process_email_queue' ) );
		add_action( 'fanfic_retry_failed_emails', array( __CLASS__, 'retry_failed_emails' ) );

		// Hook into notification creation to queue emails
		add_action( 'fanfic_notification_created', array( __CLASS__, 'on_notification_created' ), 10, 3 );

		// Register custom cron schedules
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		// Schedule cron jobs if not already scheduled
		if ( ! wp_next_scheduled( 'fanfic_process_email_queue' ) ) {
			self::schedule_cron_jobs();
		}
	}

	/**
	 * Add custom cron schedules
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public static function add_cron_schedules( $schedules ) {
		$schedules['every_30_minutes'] = array(
			'interval' => 1800, // 30 minutes in seconds
			'display'  => __( 'Every 30 Minutes', 'fanfiction-manager' ),
		);

		return $schedules;
	}

	/**
	 * Schedule cron jobs
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule_cron_jobs() {
		// Schedule email queue processing every 30 minutes
		wp_schedule_event( time(), 'every_30_minutes', 'fanfic_process_email_queue' );

		// Schedule failed email retry hourly
		wp_schedule_event( time(), 'hourly', 'fanfic_retry_failed_emails' );
	}

	/**
	 * Queue email for sending
	 *
	 * @since 1.0.0
	 * @param int    $user_id          User ID to send email to.
	 * @param string $notification_type Notification type.
	 * @param array  $variables         Variables for template substitution.
	 * @return bool True on success, false on failure.
	 */
	public static function queue_email( $user_id, $notification_type, $variables = array() ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		// Get user data
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Check if user has email notifications enabled for this type
		if ( ! Fanfic_Notification_Preferences::should_send_email( $user_id, $notification_type ) ) {
			return false; // User has disabled email notifications for this type
		}

		// Get current queue
		$queue = get_option( self::QUEUE_OPTION, array() );

		// Add email to queue
		$queue[] = array(
			'user_id'          => $user_id,
			'notification_type' => $notification_type,
			'variables'        => $variables,
			'attempts'         => 0,
			'queued_at'        => current_time( 'timestamp' ),
			'next_retry'       => current_time( 'timestamp' ), // Process immediately
		);

		return update_option( self::QUEUE_OPTION, $queue );
	}

	/**
	 * Process email queue (batch sending)
	 *
	 * @since 1.0.0
	 * @return int Number of emails processed.
	 */
	public static function process_email_queue() {
		$queue = get_option( self::QUEUE_OPTION, array() );

		if ( empty( $queue ) ) {
			return 0;
		}

		$processed = 0;
		$new_queue = array();
		$current_time = current_time( 'timestamp' );

		foreach ( $queue as $email ) {
			// Check if we've reached batch limit
			if ( $processed >= self::BATCH_SIZE ) {
				$new_queue[] = $email;
				continue;
			}

			// Check if it's time to retry this email
			if ( $email['next_retry'] > $current_time ) {
				$new_queue[] = $email;
				continue;
			}

			// Attempt to send email
			$result = self::send_queued_email( $email );

			if ( $result ) {
				// Email sent successfully
				$processed++;
			} else {
				// Email failed, check if we should retry
				$email['attempts']++;

				if ( $email['attempts'] < self::MAX_ATTEMPTS ) {
					// Calculate next retry time (exponential backoff)
					$backoff_minutes = pow( 2, $email['attempts'] ) * 30; // 30 min, 1 hr, 2 hr
					$email['next_retry'] = $current_time + ( $backoff_minutes * 60 );
					$new_queue[] = $email;
				} else {
					// Max attempts reached, log and discard
					self::log_email( $email['user_id'], $email['notification_type'], 'failed', __( 'Max retry attempts reached', 'fanfiction-manager' ) );
				}
			}

			// Add small delay between emails to prevent spam flags
			usleep( 100000 ); // 0.1 second delay
		}

		// Update queue
		update_option( self::QUEUE_OPTION, $new_queue );

		return $processed;
	}

	/**
	 * Send a queued email
	 *
	 * @since 1.0.0
	 * @param array $email Email data from queue.
	 * @return bool True on success, false on failure.
	 */
	private static function send_queued_email( $email ) {
		$user = get_user_by( 'ID', $email['user_id'] );
		if ( ! $user ) {
			return false;
		}

		// Add user name to variables
		$variables = $email['variables'];
		$variables['user_name'] = $user->display_name;

		// Render template
		$rendered = Fanfic_Email_Templates::render_template( $email['notification_type'], $variables );

		// Send email
		$result = self::send_email(
			$user->user_email,
			$rendered['subject'],
			$rendered['body']
		);

		// Log result
		if ( $result ) {
			self::log_email( $email['user_id'], $email['notification_type'], 'success' );
		} else {
			self::log_email( $email['user_id'], $email['notification_type'], 'failed', __( 'wp_mail() returned false', 'fanfiction-manager' ) );
		}

		return $result;
	}

	/**
	 * Send email using wp_mail()
	 *
	 * @since 1.0.0
	 * @param string $to_email   Recipient email address.
	 * @param string $subject    Email subject.
	 * @param string $html_body  Email HTML body.
	 * @return bool True on success, false on failure.
	 */
	public static function send_email( $to_email, $subject, $html_body ) {
		// Validate email
		if ( ! is_email( $to_email ) ) {
			return false;
		}

		// Prepare headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
			'Reply-To: ' . get_option( 'admin_email' ),
			'X-Mailer: Fanfiction Manager',
		);

		// Generate plain text fallback
		$plain_text = Fanfic_Email_Templates::generate_plain_text( $html_body );

		// Send email
		$result = wp_mail( $to_email, $subject, $html_body, $headers );

		return $result;
	}

	/**
	 * Log email delivery
	 *
	 * @since 1.0.0
	 * @param int    $user_id          User ID.
	 * @param string $notification_type Notification type.
	 * @param string $status            Status ('success' or 'failed').
	 * @param string $error_message     Error message if failed.
	 * @return bool True on success.
	 */
	public static function log_email( $user_id, $notification_type, $status, $error_message = '' ) {
		$log = get_option( self::LOG_OPTION, array() );

		// Add new log entry
		$log[] = array(
			'timestamp'        => current_time( 'timestamp' ),
			'user_id'          => absint( $user_id ),
			'notification_type' => sanitize_text_field( $notification_type ),
			'status'           => sanitize_text_field( $status ),
			'error_message'    => sanitize_text_field( $error_message ),
		);

		// Keep only last 1000 entries
		if ( count( $log ) > 1000 ) {
			$log = array_slice( $log, -1000 );
		}

		return update_option( self::LOG_OPTION, $log );
	}

	/**
	 * Get email log
	 *
	 * @since 1.0.0
	 * @param int|null $user_id User ID to filter by, or null for all.
	 * @param int      $limit   Number of entries to retrieve.
	 * @return array Email log entries.
	 */
	public static function get_email_log( $user_id = null, $limit = 50 ) {
		$log = get_option( self::LOG_OPTION, array() );

		// Filter by user if specified
		if ( null !== $user_id ) {
			$user_id = absint( $user_id );
			$log = array_filter( $log, function( $entry ) use ( $user_id ) {
				return isset( $entry['user_id'] ) && $entry['user_id'] === $user_id;
			} );
		}

		// Sort by timestamp descending
		usort( $log, function( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		} );

		// Limit results
		if ( $limit > 0 ) {
			$log = array_slice( $log, 0, $limit );
		}

		return $log;
	}

	/**
	 * Retry failed emails
	 *
	 * Called by hourly cron job.
	 *
	 * @since 1.0.0
	 * @return int Number of emails retried.
	 */
	public static function retry_failed_emails() {
		// This is handled by process_email_queue() with exponential backoff
		return self::process_email_queue();
	}

	/**
	 * Clear email queue
	 *
	 * Admin utility to clear all queued emails.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public static function clear_queue() {
		return delete_option( self::QUEUE_OPTION );
	}

	/**
	 * Clear email log
	 *
	 * Admin utility to clear email log.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public static function clear_log() {
		return delete_option( self::LOG_OPTION );
	}

	/**
	 * Get queue size
	 *
	 * @since 1.0.0
	 * @return int Number of emails in queue.
	 */
	public static function get_queue_size() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return count( $queue );
	}

	/**
	 * Send test email
	 *
	 * Admin utility to send test email.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID to send test email to.
	 * @return bool True on success, false on failure.
	 */
	public static function send_test_email( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Prepare test variables
		$variables = array(
			'user_name'     => $user->display_name,
			'author_name'   => 'Test Author',
			'story_title'   => 'Test Story Title',
			'story_url'     => home_url( '/test-story' ),
			'chapter_title' => 'Test Chapter Title',
			'chapter_url'   => home_url( '/test-chapter' ),
			'follower_name' => 'Test Follower',
			'comment_text'  => 'This is a test comment to demonstrate the email template.',
		);

		// Render template (use new_comment as test)
		$rendered = Fanfic_Email_Templates::render_template( Fanfic_Email_Templates::TYPE_NEW_COMMENT, $variables );

		// Send email
		$result = self::send_email( $user->user_email, '[TEST] ' . $rendered['subject'], $rendered['body'] );

		// Log result
		if ( $result ) {
			self::log_email( $user_id, 'test_email', 'success' );
		} else {
			self::log_email( $user_id, 'test_email', 'failed', __( 'Test email failed', 'fanfiction-manager' ) );
		}

		return $result;
	}

	/**
	 * Hook callback when notification is created
	 *
	 * Queues email if user has email notifications enabled.
	 *
	 * @since 1.0.0
	 * @param int    $notification_id Notification ID.
	 * @param int    $user_id         User ID.
	 * @param string $type            Notification type.
	 * @return void
	 */
	public static function on_notification_created( $notification_id, $user_id, $type ) {
		// Get notification data
		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_notifications';
		$notification = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d",
			$notification_id
		) );

		if ( ! $notification ) {
			return;
		}

		// Prepare variables based on notification type
		$variables = array();

		// Parse link to extract IDs and build variables
		// This is a simplified version - you may need to enhance this based on your URL structure
		if ( $notification->link ) {
			$variables['link_url'] = $notification->link;
		}

		// Queue email
		self::queue_email( $user_id, $type, $variables );
	}
}
