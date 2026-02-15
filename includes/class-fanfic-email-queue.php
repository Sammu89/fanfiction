<?php
/**
 * Email Queue System Class
 *
 * Handles asynchronous email processing via WP-Cron to prevent blocking operations.
 * Implements batching (50 emails per event) with 1-minute spacing between batches.
 *
 * @package FanfictionManager
 * @subpackage EmailQueue
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Email_Queue
 *
 * Manages async email sending via WP-Cron with batching to prevent timeouts.
 * No blocking operations - all emails sent via scheduled cron events.
 *
 * @since 1.0.0
 */
class Fanfic_Email_Queue {

	/**
	 * Batch size for email sending
	 */
	const BATCH_SIZE = 50;

	/**
	 * Delay between batches (seconds)
	 */
	const BATCH_DELAY = 60;

	/**
	 * Initialize the class
	 *
	 * Register WP-Cron hooks and actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register WP-Cron actions
		add_action( 'fanfic_send_email_batch', array( __CLASS__, 'send_batch' ), 10, 3 );
		add_action( 'fanfic_send_single_email', array( __CLASS__, 'send_single_email' ), 10, 4 );

		// Hook into chapter publish (this is the main trigger)
		add_action( 'transition_post_status', array( __CLASS__, 'handle_chapter_publish' ), 10, 3 );
	}

	/**
	 * Handle chapter publish event
	 *
	 * CRITICAL: Implements async notification via WP-Cron batching.
	 * Does NOT send emails immediately - schedules batches for async processing.
	 *
	 * @since 1.0.0
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return int|false Count of scheduled notifications or false on failure.
	 */
	public static function handle_chapter_publish( $new_status, $old_status, $post ) {
		// Only handle chapter posts
		if ( 'fanfiction_chapter' !== $post->post_type ) {
			return false;
		}

		// Only handle publish transitions
		if ( 'publish' !== $new_status ) {
			return false;
		}

		// Chapter update (publish → publish): delegate to update handler
		if ( 'publish' === $old_status ) {
			return self::handle_chapter_update( $post->ID, $post->post_parent );
		}

		// Prevent processing during auto-save
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		global $wpdb;

		$chapter_id = $post->ID;
		$story_id = $post->post_parent;

		if ( ! $story_id ) {
			return false;
		}

		$story = get_post( $story_id );
		if ( ! $story ) {
			return false;
		}

		$author_id = $story->post_author;

		// Step 1: Get verified email subscribers (anonymous + logged-out users).
		$email_subscribers = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT email, subscription_type
			FROM {$wpdb->prefix}fanfic_email_subscriptions
			WHERE (
				(target_id = %d AND subscription_type = 'story') OR
				(target_id = %d AND subscription_type = 'author')
			)
			AND verified = 1",
			$story_id,
			$author_id
		) );

		// Step 2: Prepare email recipient list.
		$email_recipients = array();
		$seen_emails = array();

		// Add email subscribers.
		foreach ( $email_subscribers as $subscriber ) {
			$email_lower = strtolower( $subscriber->email );
			if ( isset( $seen_emails[ $email_lower ] ) ) {
				continue;
			}
			$seen_emails[ $email_lower ] = true;
			$email_recipients[] = array(
				'type'  => 'subscriber',
				'email' => $subscriber->email,
			);
		}

		// Step 3: Get logged-in story followers with email preference enabled.
		$follower_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}fanfic_interactions
			WHERE chapter_id = %d AND interaction_type = 'follow' AND user_id > 0 AND user_id != %d",
			$story_id,
			$author_id
		) );

		if ( $follower_ids ) {
			foreach ( $follower_ids as $follower_id ) {
				// Check if user wants email notifications for new chapters
				if ( class_exists( 'Fanfic_Notification_Preferences' ) &&
					! Fanfic_Notification_Preferences::should_send_email( $follower_id, 'new_chapter' ) ) {
					continue;
				}

				$user = get_userdata( $follower_id );
				if ( ! $user ) {
					continue;
				}

				// Deduplicate against email subscribers
				$email_lower = strtolower( $user->user_email );
				if ( isset( $seen_emails[ $email_lower ] ) ) {
					continue;
				}
				$seen_emails[ $email_lower ] = true;

				$email_recipients[] = array(
					'type'         => 'user',
					'email'        => $user->user_email,
					'display_name' => $user->display_name,
				);
			}
		}

		// Step 4: Batch and schedule emails
		if ( empty( $email_recipients ) ) {
			return 0;
		}

		$total_recipients = count( $email_recipients );
		$chunks = array_chunk( $email_recipients, self::BATCH_SIZE );
		$batch_count = count( $chunks );

		// Schedule each batch with delay
		foreach ( $chunks as $i => $chunk ) {
			$delay = $i * self::BATCH_DELAY;

			wp_schedule_single_event(
				time() + $delay,
				'fanfic_send_email_batch',
				array( $chunk, $chapter_id, $story_id )
			);
		}

		// Log scheduling
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'Fanfic Email Queue: Scheduled %d batches for %d recipients (Chapter ID: %d)',
				$batch_count,
				$total_recipients,
				$chapter_id
			) );
		}

		return $total_recipients;
	}

	/**
	 * Handle chapter update (publish → publish transition)
	 *
	 * Notifies chapter bookmarkers and story followers when chapter content is updated.
	 *
	 * @since 1.8.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return int|false Count of scheduled notifications or false.
	 */
	public static function handle_chapter_update( $chapter_id, $story_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		global $wpdb;

		$story = get_post( $story_id );
		if ( ! $story ) {
			return false;
		}

		$author_id = $story->post_author;
		$email_recipients = array();
		$seen_emails = array();

		// Get logged-in story followers + chapter bookmarkers with email pref enabled
		$follower_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}fanfic_interactions
			WHERE (chapter_id = %d OR chapter_id = %d) AND interaction_type = 'follow' AND user_id > 0 AND user_id != %d",
			$story_id,
			$chapter_id,
			$author_id
		) );

		if ( $follower_ids ) {
			foreach ( $follower_ids as $follower_id ) {
				if ( class_exists( 'Fanfic_Notification_Preferences' ) &&
					! Fanfic_Notification_Preferences::should_send_email( $follower_id, 'chapter_update' ) ) {
					continue;
				}

				$user = get_userdata( $follower_id );
				if ( ! $user ) {
					continue;
				}

				$email_lower = strtolower( $user->user_email );
				if ( isset( $seen_emails[ $email_lower ] ) ) {
					continue;
				}
				$seen_emails[ $email_lower ] = true;

				$email_recipients[] = array(
					'type'         => 'user',
					'email'        => $user->user_email,
					'display_name' => $user->display_name,
				);
			}
		}

		if ( empty( $email_recipients ) ) {
			return 0;
		}

		$chunks = array_chunk( $email_recipients, self::BATCH_SIZE );
		foreach ( $chunks as $i => $chunk ) {
			wp_schedule_single_event(
				time() + ( $i * self::BATCH_DELAY ),
				'fanfic_send_email_batch',
				array( $chunk, $chapter_id, $story_id )
			);
		}

		return count( $email_recipients );
	}

	/**
	 * Handle story status change
	 *
	 * Notifies story followers when the story status taxonomy changes.
	 *
	 * @since 1.8.0
	 * @param int    $story_id   Story ID.
	 * @param string $new_status New status name.
	 * @param string $old_status Old status name.
	 * @return int|false Count of scheduled notifications or false.
	 */
	public static function handle_story_status_change( $story_id, $new_status, $old_status ) {
		global $wpdb;

		$story = get_post( $story_id );
		if ( ! $story ) {
			return false;
		}

		$author_id = $story->post_author;
		$email_recipients = array();
		$seen_emails = array();

		// Get logged-in story followers with email pref enabled
		$follower_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}fanfic_interactions
			WHERE chapter_id = %d AND interaction_type = 'follow' AND user_id > 0 AND user_id != %d",
			$story_id,
			$author_id
		) );

		if ( $follower_ids ) {
			foreach ( $follower_ids as $follower_id ) {
				if ( class_exists( 'Fanfic_Notification_Preferences' ) &&
					! Fanfic_Notification_Preferences::should_send_email( $follower_id, 'story_status' ) ) {
					continue;
				}

				$user = get_userdata( $follower_id );
				if ( ! $user ) {
					continue;
				}

				$email_lower = strtolower( $user->user_email );
				if ( isset( $seen_emails[ $email_lower ] ) ) {
					continue;
				}
				$seen_emails[ $email_lower ] = true;

				$email_recipients[] = array(
					'type'         => 'user',
					'email'        => $user->user_email,
					'display_name' => $user->display_name,
				);
			}
		}

		if ( empty( $email_recipients ) ) {
			return 0;
		}

		// Schedule email batches — reuse the batch system with story_id for both params
		$chunks = array_chunk( $email_recipients, self::BATCH_SIZE );
		foreach ( $chunks as $i => $chunk ) {
			wp_schedule_single_event(
				time() + ( $i * self::BATCH_DELAY ),
				'fanfic_send_email_batch',
				array( $chunk, $story_id, $story_id )
			);
		}

		return count( $email_recipients );
	}

	/**
	 * Send batch of emails
	 *
	 * WP-Cron action handler. Sends emails to batch of recipients.
	 *
	 * @since 1.0.0
	 * @param array $recipients  Array of recipient objects.
	 * @param int   $chapter_id  Chapter ID.
	 * @param int   $story_id    Story ID.
	 * @return array Result with sent_count, failed_count, errors.
	 */
	public static function send_batch( $recipients, $chapter_id, $story_id ) {
		$sent_count = 0;
		$failed_count = 0;
		$errors = array();

		// Get chapter and story info
		$chapter = get_post( $chapter_id );
		$story = get_post( $story_id );

		if ( ! $chapter || ! $story ) {
			return array(
				'sent_count'   => 0,
				'failed_count' => count( $recipients ),
				'errors'       => array( 'Chapter or story not found' ),
			);
		}

		$author = get_userdata( $story->post_author );
		$author_name = $author ? $author->display_name : __( 'Unknown Author', 'fanfiction-manager' );

		// Get chapter number
		$chapter_number = get_post_meta( $chapter_id, '_chapter_number', true );
		$chapter_title = $chapter->post_title;

		// Build chapter URL
		$chapter_url = get_permalink( $chapter_id );

		// Process each recipient
		foreach ( $recipients as $recipient ) {
			$email = $recipient['email'];
			$is_user = ( 'user' === $recipient['type'] );

			// Build email subject
			$subject = sprintf(
				/* translators: 1: story title, 2: author name */
				__( 'New chapter in "%1$s" by %2$s', 'fanfiction-manager' ),
				$story->post_title,
				$author_name
			);

			// Build email body
			$display_name = $is_user ? $recipient['display_name'] : __( 'Reader', 'fanfiction-manager' );

			$message = sprintf(
				/* translators: 1: display name, 2: story title, 3: chapter number, 4: chapter title, 5: chapter URL */
				__(
					"Hello %1\$s,\n\n" .
					"A new chapter has been published in \"%2\$s\"!\n\n" .
					"Chapter %3\$s: %4\$s\n\n" .
					"Read it now:\n%5\$s\n\n" .
					"Thank you for reading!\n" .
					"%6\$s",
					'fanfiction-manager'
				),
				$display_name,
				$story->post_title,
				$chapter_number,
				$chapter_title,
				$chapter_url,
				get_bloginfo( 'name' )
			);

			// Add unsubscribe link
			if ( $is_user ) {
				// Logged-in user - link to preferences
				$unsubscribe_url = home_url( '/dashboard/notifications/' ); // Adjust based on your URL structure
			} else {
				// Email subscriber - token-based unsubscribe
				$subscription = Fanfic_Email_Subscriptions::get_subscription( $email, $story_id, 'story' );
				if ( ! $subscription ) {
					// Try author subscription
					$subscription = Fanfic_Email_Subscriptions::get_subscription( $email, $story->post_author, 'author' );
				}

				if ( $subscription ) {
					$unsubscribe_url = add_query_arg(
						array(
							'action'    => 'unsubscribe',
							'email'     => rawurlencode( $email ),
							'token'     => $subscription->token,
							'target_id' => $subscription->target_id,
							'type'      => $subscription->subscription_type,
						),
						home_url( '/' )
					);

					$message .= "\n\n" . sprintf(
						/* translators: %s: unsubscribe URL */
						__( "To unsubscribe from these emails, click here:\n%s", 'fanfiction-manager' ),
						$unsubscribe_url
					);
				}
			}

			// Send email
			$result = wp_mail( $email, $subject, $message );

			if ( $result ) {
				$sent_count++;
			} else {
				$failed_count++;
				$errors[] = sprintf( 'Failed to send to %s', $email );
			}
		}

		// Log results
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'Fanfic Email Batch: Sent %d/%d emails for Chapter ID %d (Failed: %d)',
				$sent_count,
				count( $recipients ),
				$chapter_id,
				$failed_count
			) );
		}

		return array(
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'errors'       => $errors,
		);
	}

	/**
	 * Queue email to single user
	 *
	 * Schedules a single email via WP-Cron.
	 *
	 * @since 1.0.0
	 * @param int    $user_id    User ID.
	 * @param int    $chapter_id Chapter ID.
	 * @param int    $story_id   Story ID.
	 * @param string $template   Email template type.
	 * @return bool True on success, false on failure.
	 */
	public static function queue_email_to_user( $user_id, $chapter_id, $story_id, $template = 'new_chapter' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Schedule single email
		$result = wp_schedule_single_event(
			time() + 10, // 10 second delay
			'fanfic_send_single_email',
			array( $user->user_email, $chapter_id, $story_id, $template )
		);

		return false !== $result;
	}

	/**
	 * Send single email
	 *
	 * WP-Cron action handler for single email.
	 *
	 * @since 1.0.0
	 * @param string $email      Recipient email.
	 * @param int    $chapter_id Chapter ID.
	 * @param int    $story_id   Story ID.
	 * @param string $template   Email template type.
	 * @return bool True on success, false on failure.
	 */
	public static function send_single_email( $email, $chapter_id, $story_id, $template ) {
		// Create recipient array
		$recipients = array(
			array(
				'type'  => 'user',
				'email' => $email,
			),
		);

		// Use batch sender
		$result = self::send_batch( $recipients, $chapter_id, $story_id );

		return $result['sent_count'] > 0;
	}

	/**
	 * Get queue statistics
	 *
	 * Returns statistics about pending/failed emails.
	 *
	 * @since 1.0.0
	 * @return array Queue stats.
	 */
	public static function get_queue_stats() {
		// Get scheduled cron events
		$cron_events = _get_cron_array();
		$pending_batches = 0;
		$pending_emails = 0;

		if ( is_array( $cron_events ) ) {
			foreach ( $cron_events as $timestamp => $cron ) {
				if ( isset( $cron['fanfic_send_email_batch'] ) ) {
					foreach ( $cron['fanfic_send_email_batch'] as $event ) {
						$pending_batches++;
						if ( isset( $event['args'][0] ) && is_array( $event['args'][0] ) ) {
							$pending_emails += count( $event['args'][0] );
						}
					}
				}
			}
		}

		return array(
			'pending_batches' => $pending_batches,
			'pending_emails'  => $pending_emails,
			'last_sync_time'  => get_option( 'fanfic_last_email_sync', null ),
		);
	}

	/**
	 * Retry failed emails
	 *
	 * Re-schedules failed emails for retry.
	 *
	 * @since 1.0.0
	 * @param int $limit Maximum number of emails to retry.
	 * @return int Number of emails retried.
	 */
	public static function retry_failed_emails( $limit = 50 ) {
		// Get failed emails from transient/option
		$failed_emails = get_option( 'fanfic_failed_emails', array() );

		if ( empty( $failed_emails ) ) {
			return 0;
		}

		$retried = 0;
		$remaining = array();

		foreach ( $failed_emails as $failed ) {
			if ( $retried >= $limit ) {
				$remaining[] = $failed;
				continue;
			}

			// Check retry count
			$retry_count = isset( $failed['retry_count'] ) ? $failed['retry_count'] : 0;
			if ( $retry_count >= 3 ) {
				// Max retries reached, skip
				$remaining[] = $failed;
				continue;
			}

			// Re-schedule
			wp_schedule_single_event(
				time() + ( $retried * 10 ), // Space out retries
				'fanfic_send_single_email',
				array(
					$failed['email'],
					$failed['chapter_id'],
					$failed['story_id'],
					$failed['template'],
				)
			);

			// Update retry count
			$failed['retry_count'] = $retry_count + 1;
			$remaining[] = $failed;
			$retried++;
		}

		// Update failed emails list
		update_option( 'fanfic_failed_emails', $remaining );

		return $retried;
	}

	/**
	 * Process unsubscribe link
	 *
	 * Handles unsubscribe from email link.
	 *
	 * @since 1.0.0
	 * @param string $email             Email address.
	 * @param int    $target_id         Target ID (story or author).
	 * @param string $subscription_type Subscription type.
	 * @param string $token             Unsubscribe token.
	 * @return string Success message or error.
	 */
	public static function process_unsubscribe_link( $email, $target_id, $subscription_type, $token ) {
		// Delegate to Email Subscriptions class
		if ( class_exists( 'Fanfic_Email_Subscriptions' ) ) {
			$result = Fanfic_Email_Subscriptions::unsubscribe( $email, $target_id, $subscription_type, $token );

			if ( is_wp_error( $result ) ) {
				return $result->get_error_message();
			}

			return __( 'Successfully unsubscribed from email notifications.', 'fanfiction-manager' );
		}

		return __( 'Email subscription system not available.', 'fanfiction-manager' );
	}

	/**
	 * Clear all scheduled email events
	 *
	 * Admin utility to clear pending email queue.
	 *
	 * @since 1.0.0
	 * @return int Number of events cleared.
	 */
	public static function clear_queue() {
		$cron_events = _get_cron_array();
		$cleared = 0;

		if ( is_array( $cron_events ) ) {
			foreach ( $cron_events as $timestamp => $cron ) {
				if ( isset( $cron['fanfic_send_email_batch'] ) ) {
					foreach ( $cron['fanfic_send_email_batch'] as $key => $event ) {
						wp_unschedule_event( $timestamp, 'fanfic_send_email_batch', $event['args'] );
						$cleared++;
					}
				}

				if ( isset( $cron['fanfic_send_single_email'] ) ) {
					foreach ( $cron['fanfic_send_single_email'] as $key => $event ) {
						wp_unschedule_event( $timestamp, 'fanfic_send_single_email', $event['args'] );
						$cleared++;
					}
				}
			}
		}

		return $cleared;
	}
}
