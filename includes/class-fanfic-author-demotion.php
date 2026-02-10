<?php
/**
 * Author Demotion Automation Class
 *
 * Handles automatic demotion of authors with 0 published stories via daily WP-Cron.
 * Authors are demoted to 'fanfiction_reader' (subscriber) role and metadata is tracked.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Author_Demotion
 *
 * Manages automatic demotion of inactive authors.
 *
 * @since 1.0.0
 */
class Fanfic_Author_Demotion {

	/**
	 * Batch size for automated cron processing
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * WP-Cron hook name
	 *
	 * @var string
	 */
	const CRON_HOOK = 'fanfic_daily_author_demotion';

	/**
	 * Continuation hook name
	 *
	 * @var string
	 */
	const CONTINUATION_HOOK = 'fanfic_daily_author_demotion_continue';

	/**
	 * Daily start offset in minutes from cron_hour.
	 *
	 * @var int
	 */
	const CRON_OFFSET_MINUTES = 0;

	/**
	 * Soft time limit per batch worker request.
	 *
	 * @var int
	 */
	const MAX_RUNTIME_SECONDS = 45;

	/**
	 * Lock key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'fanfic_lock_author_demotion';

	/**
	 * State option key.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'fanfic_author_demotion_state';

	/**
	 * Initialize the author demotion class
	 *
	 * Sets up WordPress hooks and cron schedules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register cron hook
		add_action( self::CRON_HOOK, array( __CLASS__, 'start_demotion_run' ) );
		add_action( self::CONTINUATION_HOOK, array( __CLASS__, 'run_demotion_batch' ) );

		// Re-schedule when cron hour setting changes
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		// Schedule cron if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_cron();
		}
	}

	/**
	 * Schedule the daily author demotion cron job
	 *
	 * Schedules based on the cron_hour setting from plugin settings.
	 *
	 * @since 1.0.0
	 * @return bool True if scheduled successfully, false otherwise.
	 */
	public static function schedule_cron() {
		// Get cron hour from settings (default 3am)
		$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );

		// Calculate next run time
		$next_run = self::calculate_next_run_time( $cron_hour, self::CRON_OFFSET_MINUTES );

		// Schedule the event
		return wp_schedule_event( $next_run, 'daily', self::CRON_HOOK );
	}

	/**
	 * Calculate next cron execution time based on hour setting
	 *
	 * @since 1.0.0
	 * @param int $hour Hour of day (0-23).
	 * @return int Unix timestamp of next execution.
	 */
	private static function calculate_next_run_time( $hour, $offset_minutes = 0 ) {
		$hour = absint( $hour );
		$hour = min( 23, max( 0, $hour ) ); // Ensure 0-23 range
		$offset_minutes = max( 0, absint( $offset_minutes ) );

		// Get current time
		$current_time = current_time( 'timestamp' );
		$current_hour = (int) date( 'G', $current_time );

		// Calculate today's run time
		$today_run = strtotime( date( 'Y-m-d', $current_time ) . ' ' . sprintf( '%02d:00:00', $hour ) );
		$today_run = strtotime( '+' . $offset_minutes . ' minutes', $today_run );

		// If today's run time has passed, schedule for tomorrow
		if ( $current_time >= $today_run ) {
			return strtotime( '+1 day', $today_run );
		}

		return $today_run;
	}

	/**
	 * Re-schedule cron when settings change
	 *
	 * Triggered when fanfic_settings option is updated.
	 *
	 * @since 1.0.0
	 * @param mixed $old_value Old settings value.
	 * @param mixed $new_value New settings value.
	 * @return void
	 */
	public static function reschedule_on_settings_change( $old_value, $new_value ) {
		// Check if cron_hour changed
		$old_hour = isset( $old_value['cron_hour'] ) ? absint( $old_value['cron_hour'] ) : 3;
		$new_hour = isset( $new_value['cron_hour'] ) ? absint( $new_value['cron_hour'] ) : 3;

		if ( $old_hour !== $new_hour ) {
			// Unschedule old event
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );

			// Schedule new event with updated hour
			self::schedule_cron();
		}
	}

	/**
	 * Run automatic author demotion (called by WP-Cron)
	 *
	 * Processes up to BATCH_SIZE authors per run. Authors with 0 published stories
	 * are demoted to 'fanfiction_reader' (subscriber) role.
	 *
	 * @since 1.0.0
	 * @return array Array with 'processed' and 'demoted' counts.
	 */
	public static function run_demotion() {
		return self::start_demotion_run();
	}

	/**
	 * Start daily demotion run by resetting state and processing first batch.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function start_demotion_run() {
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		update_option(
			self::STATE_OPTION,
			array(
				'last_user_id' => 0,
			),
			false
		);

		return self::run_demotion_batch();
	}

	/**
	 * Process one demotion batch and schedule continuation if needed.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function run_demotion_batch() {
		$result = array(
			'processed' => 0,
			'demoted'   => 0,
		);

		if ( ! self::acquire_lock() ) {
			return $result;
		}

		$state = get_option( self::STATE_OPTION, array( 'last_user_id' => 0 ) );
		$last_user_id = isset( $state['last_user_id'] ) ? max( 0, absint( $state['last_user_id'] ) ) : 0;
		$start = microtime( true );
		$time_budget = self::get_time_budget_seconds();
		$has_more = false;

		do {
			$authors = self::get_author_batch( $last_user_id, self::BATCH_SIZE );

			if ( empty( $authors ) ) {
				$has_more = false;
				break;
			}

			foreach ( $authors as $author_id ) {
				$result['processed']++;
				$last_user_id = max( $last_user_id, absint( $author_id ) );

				$story_count = count_user_posts( $author_id, 'fanfiction_story', true );
				if ( 0 === $story_count && self::demote_author( $author_id ) ) {
					$result['demoted']++;
				}
			}

			$has_more = count( $authors ) === self::BATCH_SIZE;
		} while ( $has_more && ( microtime( true ) - $start ) < $time_budget );

		if ( $has_more ) {
			update_option( self::STATE_OPTION, array( 'last_user_id' => $last_user_id ), false );
			self::schedule_continuation();
		} else {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		}

		self::release_lock();

		return $result;
	}

	/**
	 * Run manual author demotion (triggered by admin button)
	 *
	 * Processes ALL authors without batch limit. Used for manual cleanup.
	 *
	 * @since 1.0.0
	 * @return array Array with 'processed' and 'demoted' counts.
	 */
	public static function run_manual() {
		wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
		delete_option( self::STATE_OPTION );

		// Get ALL authors (no batch limit)
		$authors = get_users(
			array(
				'role'   => 'fanfiction_author',
				'fields' => 'ID',
			)
		);

		if ( empty( $authors ) ) {
			return array(
				'processed' => 0,
				'demoted'   => 0,
			);
		}

		$processed = 0;
		$demoted = 0;

		foreach ( $authors as $author_id ) {
			$processed++;

			// Count published stories
			$story_count = count_user_posts( $author_id, 'fanfiction_story', true );

			// Demote if no published stories
			if ( 0 === $story_count ) {
				$result = self::demote_author( $author_id );
				if ( $result ) {
					$demoted++;
				}
			}
		}

		return array(
			'processed' => $processed,
			'demoted'   => $demoted,
		);
	}

	/**
	 * Demote a single author to 'fanfiction_reader' role
	 *
	 * Changes role to subscriber and stores tracking metadata.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID to demote.
	 * @return bool True on success, false on failure.
	 */
	private static function demote_author( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		// Get user object
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Verify user is currently an author
		if ( ! in_array( 'fanfiction_author', $user->roles, true ) ) {
			return false;
		}

		// Demote to subscriber (fanfiction_reader)
		$user->set_role( 'subscriber' );

		// Store metadata
		update_user_meta( $user_id, '_fanfic_auto_demoted', '1' );
		update_user_meta( $user_id, '_fanfic_auto_demoted_at', current_time( 'mysql' ) );

		// Log the demotion
		self::log_demotion( $user_id );

		// Send notification email
		self::send_demotion_notification( $user_id );

		// Fire action hook for extensibility
		do_action( 'fanfic_author_demoted', $user_id );

		return true;
	}

	/**
	 * Log author demotion event
	 *
	 * Creates a simple log entry for tracking purposes.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID that was demoted.
	 * @return void
	 */
	private static function log_demotion( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Log to PHP error log (optional, can be disabled)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[Fanfiction Manager] Auto-demoted author #%d (%s) to Reader role (0 published stories)',
					$user_id,
					$user->user_login
				)
			);
		}
	}

	/**
	 * Send demotion notification email to user
	 *
	 * Queues an email notification informing the user of their demotion.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID to notify.
	 * @return void
	 */
	private static function send_demotion_notification( $user_id ) {
		// Check if email sender class is available
		if ( ! class_exists( 'Fanfic_Email_Sender' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Prepare notification variables
		$variables = array(
			'user_name'    => $user->display_name,
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'dashboard_url' => home_url( '/dashboard' ),
		);

		// Queue email (if notification system is implemented)
		// For now, we'll send a simple email directly
		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Your author status on %s has been updated', 'fanfiction-manager' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: User display name, 2: Site name */
			__( 'Hi %1$s,

Your account on %2$s has been updated to Reader status because you don\'t currently have any published stories.

This is an automatic process to keep our user roles accurate. If you publish a new story, you will automatically be promoted back to Author status.

You can still:
- Read and bookmark stories
- Comment on stories
- Follow your favorite authors
- Manage your reading preferences

To regain Author status, simply publish a new story!

Best regards,
The %2$s Team', 'fanfiction-manager' ),
			$user->display_name,
			get_bloginfo( 'name' )
		);

		// Send email using WordPress wp_mail
		wp_mail(
			$user->user_email,
			$subject,
			$message,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Get demotion statistics
	 *
	 * Returns counts of auto-demoted users and candidates for demotion.
	 *
	 * @since 1.0.0
	 * @return array Array with 'total_demoted' and 'candidates' counts.
	 */
	public static function get_statistics() {
		// Count users who have been auto-demoted
		$demoted_users = get_users(
			array(
				'meta_key'   => '_fanfic_auto_demoted',
				'meta_value' => '1',
				'count_total' => true,
				'fields'     => 'ID',
			)
		);

		// Count current authors with 0 published stories (candidates for demotion)
		$all_authors = get_users(
			array(
				'role'   => 'fanfiction_author',
				'fields' => 'ID',
			)
		);

		$candidates = 0;
		foreach ( $all_authors as $author_id ) {
			$story_count = count_user_posts( $author_id, 'fanfiction_story', true );
			if ( 0 === $story_count ) {
				$candidates++;
			}
		}

		return array(
			'total_demoted' => count( $demoted_users ),
			'candidates'    => $candidates,
		);
	}

	/**
	 * Clear auto-demotion metadata for a user
	 *
	 * Useful when user is manually promoted back to author.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function clear_demotion_metadata( $user_id ) {
		delete_user_meta( $user_id, '_fanfic_auto_demoted' );
		delete_user_meta( $user_id, '_fanfic_auto_demoted_at' );
	}

	/**
	 * Schedule continuation soon for background processing.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function schedule_continuation() {
		if ( ! wp_next_scheduled( self::CONTINUATION_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CONTINUATION_HOOK );
		}
	}

	/**
	 * Acquire lightweight lock.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private static function acquire_lock() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::LOCK_KEY, 1, self::MAX_RUNTIME_SECONDS + 120 );
		return true;
	}

	/**
	 * Release lock.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function release_lock() {
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Fetch author IDs in stable ID-ordered batches.
	 *
	 * Uses direct SQL to avoid offset drift while roles are being updated mid-run.
	 *
	 * @since 2.0.0
	 * @param int $after_user_id Only IDs greater than this value.
	 * @param int $limit Batch size.
	 * @return int[] User IDs.
	 */
	private static function get_author_batch( $after_user_id, $limit ) {
		global $wpdb;

		$after_user_id = max( 0, absint( $after_user_id ) );
		$limit = max( 1, absint( $limit ) );
		$cap_key = $wpdb->prefix . 'capabilities';
		$role_like = '%"fanfiction_author"%';

		$sql = $wpdb->prepare(
			"SELECT u.ID
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um
				ON um.user_id = u.ID
			WHERE u.ID > %d
				AND um.meta_key = %s
				AND um.meta_value LIKE %s
			ORDER BY u.ID ASC
			LIMIT %d",
			$after_user_id,
			$cap_key,
			$role_like,
			$limit
		);

		$rows = $wpdb->get_col( $sql );
		return is_array( $rows ) ? array_map( 'absint', $rows ) : array();
	}

	/**
	 * Get safe worker budget based on server max_execution_time.
	 *
	 * @since 2.0.0
	 * @return int Seconds.
	 */
	private static function get_time_budget_seconds() {
		$budget = self::MAX_RUNTIME_SECONDS;
		$max_exec = (int) ini_get( 'max_execution_time' );
		if ( $max_exec > 0 ) {
			$budget = max( 10, min( $budget, $max_exec - 5 ) );
		}
		return $budget;
	}
}
