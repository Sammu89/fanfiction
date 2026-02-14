<?php
/**
 * Notifications Core System Class
 *
 * Handles all notification functionality including creating, reading,
 * marking as read, and deleting notifications.
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
 * Class Fanfic_Notifications
 *
 * Core notification system for in-app and email notifications.
 *
 * @since 1.0.0
 */
class Fanfic_Notifications {

	/**
	 * Notification type constants
	 */
	const TYPE_NEW_COMMENT = 'new_comment';
	const TYPE_NEW_CHAPTER = 'new_chapter';
	const TYPE_NEW_STORY = 'new_story';
	const TYPE_COMMENT_REPLY = 'comment_reply';
	const TYPE_STORY_UPDATE = 'story_update';
	const TYPE_COAUTHOR_INVITE = 'coauthor_invite';
	const TYPE_COAUTHOR_ACCEPTED = 'coauthor_accepted';
	const TYPE_COAUTHOR_REFUSED = 'coauthor_refused';
	const TYPE_COAUTHOR_REMOVED = 'coauthor_removed';
	const TYPE_COAUTHOR_DISABLED = 'coauthor_disabled';
	const TYPE_COAUTHOR_ENABLED = 'coauthor_enabled';
	const TYPE_CHAPTER_UPDATE = 'chapter_update';
	const TYPE_STORY_STATUS_CHANGE = 'story_status_change';

	/**
	 * Cron continuation hook for old-notification cleanup.
	 *
	 * @var string
	 */
	const CLEANUP_CONTINUATION_HOOK = 'fanfic_cleanup_old_notifications_continue';

	/**
	 * Cleanup batch size.
	 *
	 * @var int
	 */
	const CLEANUP_BATCH_SIZE = 1000;

	/**
	 * Cleanup worker time limit.
	 *
	 * @var int
	 */
	const CLEANUP_MAX_RUNTIME_SECONDS = 45;

	/**
	 * Cleanup lock key.
	 *
	 * @var string
	 */
	const CLEANUP_LOCK_KEY = 'fanfic_lock_cleanup_old_notifications';

	/**
	 * Daily start offset from cron_hour (minutes).
	 *
	 * @var int
	 */
	const CLEANUP_CRON_OFFSET_MINUTES = 40;

	/**
	 * Initialize the notifications class
	 *
	 * Sets up WordPress hooks for notification functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register cron hooks
		add_action( 'fanfic_cleanup_old_notifications', array( __CLASS__, 'delete_old_notifications' ) );
		add_action( self::CLEANUP_CONTINUATION_HOOK, array( __CLASS__, 'delete_old_notifications' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		// Schedule cron job on init if not already scheduled
		if ( ! wp_next_scheduled( 'fanfic_cleanup_old_notifications' ) ) {
			self::schedule_cleanup_cron();
		}

		// Hook into WordPress comment system
		add_action( 'wp_insert_comment', array( __CLASS__, 'handle_new_comment' ), 10, 2 );

		// Hook into chapter publish
		add_action( 'transition_post_status', array( __CLASS__, 'handle_post_transition' ), 10, 3 );

		// Hook into taxonomy changes
		add_action( 'set_object_terms', array( __CLASS__, 'handle_taxonomy_change' ), 10, 6 );

		// Hook into post updates (for title changes)
		add_action( 'post_updated', array( __CLASS__, 'handle_title_change' ), 10, 3 );
	}

	/**
	 * Create a new notification
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID to receive notification.
	 * @param string $type    Notification type (use class constants).
	 * @param string $message Notification message.
	 * @param array  $data    Additional data (stored as JSON).
	 * @return int|false Notification ID on success, false on failure.
	 */
	public static function create_notification( $user_id, $type, $message, $data = array() ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		if ( ! $user_id || ! get_user_by( 'ID', $user_id ) ) {
			return false;
		}

		$valid_types = array(
			self::TYPE_NEW_COMMENT,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
			self::TYPE_COMMENT_REPLY,
			self::TYPE_STORY_UPDATE,
			self::TYPE_COAUTHOR_INVITE,
			self::TYPE_COAUTHOR_ACCEPTED,
			self::TYPE_COAUTHOR_REFUSED,
			self::TYPE_COAUTHOR_REMOVED,
			self::TYPE_COAUTHOR_DISABLED,
			self::TYPE_COAUTHOR_ENABLED,
			self::TYPE_CHAPTER_UPDATE,
			self::TYPE_STORY_STATUS_CHANGE,
		);

		if ( ! in_array( $type, $valid_types, true ) ) {
			return false;
		}

		$message = sanitize_text_field( $message );

		// Encode data as JSON
		$data_json = ! empty( $data ) ? wp_json_encode( $data ) : null;

		// Insert notification
		$table_name = $wpdb->prefix . 'fanfic_notifications';
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'type'       => $type,
				'message'    => $message,
				'data'       => $data_json,
				'is_read'    => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$notification_id = $wpdb->insert_id;

		// Fire action hook for extensibility
		do_action( 'fanfic_notification_created', $notification_id, $user_id, $type );

		return $notification_id;
	}

	/**
	 * Get user notifications
	 *
	 * @since 1.0.0
	 * @param int  $user_id      User ID.
	 * @param bool $unread_only  Only get unread notifications.
	 * @param int  $limit        Number of notifications to retrieve.
	 * @param int  $offset       Offset for pagination.
	 * @return array Array of notification objects.
	 */
	public static function get_user_notifications( $user_id, $unread_only = false, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		$sql = "SELECT * FROM {$table_name} WHERE user_id = %d";
		$params = array( $user_id );

		if ( $unread_only ) {
			$sql .= ' AND is_read = 0';
		}

		$sql .= ' ORDER BY created_at DESC';

		if ( $limit > 0 ) {
			$sql .= ' LIMIT %d OFFSET %d';
			$params[] = absint( $limit );
			$params[] = absint( $offset );
		}

		$notifications = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		return $notifications ? $notifications : array();
	}

	/**
	 * Get unread notification count for user
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Unread notification count.
	 */
	public static function get_unread_count( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND is_read = 0",
			$user_id
		) );

		return absint( $count );
	}

	/**
	 * Get total notification count for user
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int Total notification count.
	 */
	public static function get_total_count( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
			$user_id
		) );

		return absint( $count );
	}

	/**
	 * Mark a notification as read
	 *
	 * @since 1.0.0
	 * @param int $notification_id Notification ID.
	 * @param int $user_id         User ID (for verification).
	 * @return bool True on success, false on failure.
	 */
	public static function mark_as_read( $notification_id, $user_id ) {
		global $wpdb;

		$notification_id = absint( $notification_id );
		$user_id = absint( $user_id );

		if ( ! $notification_id || ! $user_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		// Verify notification belongs to user
		$notification = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
			$notification_id,
			$user_id
		) );

		if ( ! $notification ) {
			return false;
		}

		// Update notification
		$result = $wpdb->update(
			$table_name,
			array( 'is_read' => 1 ),
			array( 'id' => $notification_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Fire action hook
			do_action( 'fanfic_notification_marked_read', $notification_id, $user_id );
			return true;
		}

		return false;
	}

	/**
	 * Mark all user notifications as read
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function mark_all_as_read( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		$result = $wpdb->update(
			$table_name,
			array( 'is_read' => 1 ),
			array( 'user_id' => $user_id, 'is_read' => 0 ),
			array( '%d' ),
			array( '%d', '%d' )
		);

		if ( false !== $result ) {
			// Fire action hook
			do_action( 'fanfic_all_notifications_marked_read', $user_id );
			return true;
		}

		return false;
	}

	/**
	 * Delete a notification
	 *
	 * @since 1.0.0
	 * @param int $notification_id Notification ID.
	 * @param int $user_id         User ID (for verification).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_notification( $notification_id, $user_id ) {
		global $wpdb;

		$notification_id = absint( $notification_id );
		$user_id = absint( $user_id );

		if ( ! $notification_id || ! $user_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		// Verify notification belongs to user
		$notification = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
			$notification_id,
			$user_id
		) );

		if ( ! $notification ) {
			return false;
		}

		// Delete notification
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $notification_id ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Fire action hook
			do_action( 'fanfic_notification_deleted', $notification_id, $user_id );
			return true;
		}

		return false;
	}

	/**
	 * Delete all user notifications
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_all_notifications( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Fire action hook
			do_action( 'fanfic_all_notifications_deleted', $user_id );
			return true;
		}

		return false;
	}

	/**
	 * Delete old notifications (90+ days)
	 *
	 * Called by cron job to clean up old notifications.
	 *
	 * @since 1.0.0
	 * @return int Number of notifications deleted.
	 */
	public static function delete_old_notifications() {
		if ( ! self::acquire_cleanup_lock() ) {
			return 0;
		}

		$total_deleted = 0;
		$start = microtime( true );
		$time_budget = self::get_cleanup_time_budget_seconds();
		$has_more = false;

		do {
			$deleted = self::delete_old_notifications_batch( self::CLEANUP_BATCH_SIZE );
			$total_deleted += $deleted;
			$has_more = ( self::CLEANUP_BATCH_SIZE === $deleted );
		} while ( $has_more && ( microtime( true ) - $start ) < $time_budget );

		if ( $has_more ) {
			self::schedule_cleanup_continuation();
		} else {
			wp_clear_scheduled_hook( self::CLEANUP_CONTINUATION_HOOK );
		}

		self::release_cleanup_lock();

		if ( $total_deleted > 0 ) {
			error_log( sprintf( 'Fanfiction Manager: Cleaned up %d old notifications', $total_deleted ) );
		}

		return $total_deleted;
	}

	/**
	 * Delete one old-notification batch.
	 *
	 * @since 2.0.0
	 * @param int $limit Batch size.
	 * @return int Deleted row count.
	 */
	private static function delete_old_notifications_batch( $limit ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_notifications';
		$limit = max( 1, absint( $limit ) );

		// Delete oldest rows first in bounded chunks.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name}
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				ORDER BY id ASC
				LIMIT %d",
				90,
				$limit
			)
		);

		return $result ? $result : 0;
	}

	/**
	 * Schedule cleanup cron job
	 *
	 * Schedules daily cron job to delete old notifications.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule_cleanup_cron() {
		// Get configured cron hour from settings (default: 3 AM)
		$settings = get_option( 'fanfic_settings', array() );
		$cron_hour = isset( $settings['cron_hour'] ) ? absint( $settings['cron_hour'] ) : 3;
		$cron_hour = min( 23, max( 0, $cron_hour ) );

		// Calculate next run time
		$next_run = self::calculate_next_run_time( $cron_hour, self::CLEANUP_CRON_OFFSET_MINUTES );

		// Schedule event
		wp_schedule_event( $next_run, 'daily', 'fanfic_cleanup_old_notifications' );
	}

	/**
	 * Unschedule cleanup cron job.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unschedule_cleanup_cron() {
		wp_clear_scheduled_hook( 'fanfic_cleanup_old_notifications' );
		wp_clear_scheduled_hook( self::CLEANUP_CONTINUATION_HOOK );
	}

	/**
	 * Re-schedule cleanup cron when cron hour setting changes.
	 *
	 * @since 1.0.0
	 * @param array $old_value Previous settings.
	 * @param array $new_value New settings.
	 * @return void
	 */
	public static function reschedule_on_settings_change( $old_value, $new_value ) {
		$old_hour = isset( $old_value['cron_hour'] ) ? absint( $old_value['cron_hour'] ) : 3;
		$new_hour = isset( $new_value['cron_hour'] ) ? absint( $new_value['cron_hour'] ) : 3;

		if ( $old_hour === $new_hour ) {
			return;
		}

		self::unschedule_cleanup_cron();
		self::schedule_cleanup_cron();
	}

	/**
	 * Calculate next run time based on cron hour and offset.
	 *
	 * @since 2.0.0
	 * @param int $cron_hour Hour (0-23).
	 * @param int $offset_minutes Offset in minutes.
	 * @return int Timestamp.
	 */
	private static function calculate_next_run_time( $cron_hour, $offset_minutes = 0 ) {
		$cron_hour = min( 23, max( 0, absint( $cron_hour ) ) );
		$offset_minutes = max( 0, absint( $offset_minutes ) );

		$current_time = current_time( 'timestamp' );
		$today = date_i18n( 'Y-m-d', $current_time );
		$scheduled_time = strtotime( sprintf( '%s %02d:00:00', $today, $cron_hour ) );
		$scheduled_time = strtotime( '+' . $offset_minutes . ' minutes', $scheduled_time );

		if ( $scheduled_time <= $current_time ) {
			$scheduled_time = strtotime( '+1 day', $scheduled_time );
		}

		return $scheduled_time;
	}

	/**
	 * Schedule continuation worker.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function schedule_cleanup_continuation() {
		if ( ! wp_next_scheduled( self::CLEANUP_CONTINUATION_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CLEANUP_CONTINUATION_HOOK );
		}
	}

	/**
	 * Acquire cleanup lock.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	private static function acquire_cleanup_lock() {
		if ( get_transient( self::CLEANUP_LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::CLEANUP_LOCK_KEY, 1, self::CLEANUP_MAX_RUNTIME_SECONDS + 120 );
		return true;
	}

	/**
	 * Release cleanup lock.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private static function release_cleanup_lock() {
		delete_transient( self::CLEANUP_LOCK_KEY );
	}

	/**
	 * Get safe cleanup budget based on server max_execution_time.
	 *
	 * @since 2.0.0
	 * @return int Seconds.
	 */
	private static function get_cleanup_time_budget_seconds() {
		$budget = self::CLEANUP_MAX_RUNTIME_SECONDS;
		$max_exec = (int) ini_get( 'max_execution_time' );
		if ( $max_exec > 0 ) {
			$budget = max( 10, min( $budget, $max_exec - 5 ) );
		}
		return $budget;
	}

	/**
	 * Get notification types
	 *
	 * Returns array of all notification types with labels.
	 *
	 * @since 1.0.0
	 * @return array Notification types array.
	 */
	public static function get_notification_types() {
		return array(
			self::TYPE_NEW_COMMENT => __( 'New Comment', 'fanfiction-manager' ),
			self::TYPE_NEW_CHAPTER => __( 'New Chapter', 'fanfiction-manager' ),
			self::TYPE_NEW_STORY => __( 'New Story', 'fanfiction-manager' ),
			self::TYPE_COMMENT_REPLY => __( 'Comment Reply', 'fanfiction-manager' ),
			self::TYPE_STORY_UPDATE => __( 'Story Update', 'fanfiction-manager' ),
			self::TYPE_COAUTHOR_INVITE => __( 'Co-Author Invite', 'fanfiction-manager' ),
			self::TYPE_COAUTHOR_ACCEPTED => __( 'Co-Author Accepted', 'fanfiction-manager' ),
			self::TYPE_COAUTHOR_REFUSED => __( 'Co-Author Refused', 'fanfiction-manager' ),
			self::TYPE_COAUTHOR_REMOVED => __( 'Co-Author Removed', 'fanfiction-manager' ),
			self::TYPE_COAUTHOR_DISABLED => __( 'Co-Authors Disabled', 'fanfiction-manager' ),
			self::TYPE_COAUTHOR_ENABLED => __( 'Co-Authors Enabled', 'fanfiction-manager' ),
			self::TYPE_CHAPTER_UPDATE => __( 'Chapter Update', 'fanfiction-manager' ),
			self::TYPE_STORY_STATUS_CHANGE => __( 'Story Status Change', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get relative time string
	 *
	 * Converts timestamp to relative time (e.g., "2 hours ago").
	 *
	 * @since 1.0.0
	 * @param string $timestamp MySQL timestamp.
	 * @return string Relative time string.
	 */
	public static function get_relative_time( $timestamp ) {
		$time_diff = current_time( 'timestamp' ) - strtotime( $timestamp );

		if ( $time_diff < 60 ) {
			return __( 'Just now', 'fanfiction-manager' );
		} elseif ( $time_diff < 3600 ) {
			$minutes = floor( $time_diff / 60 );
			return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'fanfiction-manager' ), $minutes );
		} elseif ( $time_diff < 86400 ) {
			$hours = floor( $time_diff / 3600 );
			return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'fanfiction-manager' ), $hours );
		} elseif ( $time_diff < 604800 ) {
			$days = floor( $time_diff / 86400 );
			return sprintf( _n( '%d day ago', '%d days ago', $days, 'fanfiction-manager' ), $days );
		} else {
			return date_i18n( get_option( 'date_format' ), strtotime( $timestamp ) );
		}
	}

	/**
	 * Batch create notifications for multiple users
	 *
	 * More efficient than looping create_notification() - uses single multi-row INSERT.
	 *
	 * @since 1.0.0
	 * @param array  $user_ids Array of user IDs to notify.
	 * @param string $type     Notification type.
	 * @param string $message  Notification message.
	 * @param array  $data     Additional data (stored as JSON).
	 * @return int Count of created notifications.
	 */
	public static function batch_create_notifications( $user_ids, $type, $message, $data = array() ) {
		global $wpdb;

		if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
			return 0;
		}

		// Validate type
		$valid_types = array(
			self::TYPE_NEW_COMMENT,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
			self::TYPE_COMMENT_REPLY,
			self::TYPE_STORY_UPDATE,
			self::TYPE_COAUTHOR_INVITE,
			self::TYPE_COAUTHOR_ACCEPTED,
			self::TYPE_COAUTHOR_REFUSED,
			self::TYPE_COAUTHOR_REMOVED,
			self::TYPE_COAUTHOR_DISABLED,
			self::TYPE_COAUTHOR_ENABLED,
		);

		if ( ! in_array( $type, $valid_types, true ) ) {
			return 0;
		}

		$message = sanitize_text_field( $message );
		$data_json = ! empty( $data ) ? wp_json_encode( $data ) : null;
		$created_at = current_time( 'mysql' );

		// Build multi-row INSERT query
		$table_name = $wpdb->prefix . 'fanfic_notifications';
		$values = array();
		$placeholders = array();

		foreach ( $user_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id || ! get_user_by( 'ID', $user_id ) ) {
				continue;
			}

			$placeholders[] = '(%d, %s, %s, %s, 0, %s)';
			$values[] = $user_id;
			$values[] = $type;
			$values[] = $message;
			$values[] = $data_json;
			$values[] = $created_at;
		}

		if ( empty( $placeholders ) ) {
			return 0;
		}

		// Execute multi-row INSERT
		$sql = "INSERT INTO {$table_name} (user_id, type, message, data, is_read, created_at) VALUES " . implode( ', ', $placeholders );
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		// Fire action hook
		do_action( 'fanfic_batch_notifications_created', $user_ids, $type );

		return $result ? $result : 0;
	}

	/**
	 * Create chapter notification
	 *
	 * Legacy chapter notifications are disabled.
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return int Count of notifications created.
	 */
	public static function create_chapter_notification( $chapter_id, $story_id ) {
		return 0;
	}

	/**
	 * Create comment notification
	 *
	 * Notifies post author when comment is posted.
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @return int|false Notification ID on success, false on failure.
	 */
	public static function create_comment_notification( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return false;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return false;
		}

		$commenter = get_userdata( $comment->user_id );
		$commenter_name = $commenter ? $commenter->display_name : $comment->comment_author;

		$data = array(
			'comment_id'     => $comment_id,
			'post_id'        => $post->ID,
			'post_title'     => $post->post_title,
			'commenter_name' => $commenter_name,
			'comment_text'   => wp_trim_words( $comment->comment_content, 20 ),
		);

		$notified_users = array();

		// 1. If this is a reply to another comment, notify the parent comment author
		if ( $comment->comment_parent > 0 ) {
			$parent_comment = get_comment( $comment->comment_parent );
			if ( $parent_comment && $parent_comment->user_id > 0 ) {
				// Don't notify if replying to own comment
				if ( $parent_comment->user_id != $comment->user_id ) {
					$reply_message = sprintf(
						/* translators: 1: commenter name, 2: post title */
						__( '%1$s replied to your comment on "%2$s"', 'fanfiction-manager' ),
						$commenter_name,
						$post->post_title
					);

					self::create_notification(
						$parent_comment->user_id,
						self::TYPE_COMMENT_REPLY,
						$reply_message,
						$data
					);

					$notified_users[] = $parent_comment->user_id;
				}
			}
		}

		// 2. Notify the post author (story/chapter author) of ALL comments on their content
		// Don't notify author of their own comments
		// Don't notify if already notified as parent comment author
		if ( $post->post_author > 0 && $post->post_author != $comment->user_id && ! in_array( $post->post_author, $notified_users, true ) ) {
			$author_message = sprintf(
				/* translators: 1: commenter name, 2: post title */
				__( '%1$s commented on "%2$s"', 'fanfiction-manager' ),
				$commenter_name,
				$post->post_title
			);

			self::create_notification(
				$post->post_author,
				self::TYPE_NEW_COMMENT,
				$author_message,
				$data
			);
		}

		return true;
	}

	/**
	 * Create chapter update notification for story followers
	 *
	 * Notifies users who follow the parent story when a chapter is updated.
	 *
	 * @since 1.8.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return int Count of notifications created.
	 */
	public static function create_chapter_update_notification( $chapter_id, $story_id ) {
		$chapter = get_post( $chapter_id );
		$story   = get_post( $story_id );

		if ( ! $chapter || ! $story ) {
			return 0;
		}

		$chapter_number = get_post_meta( $chapter_id, '_chapter_number', true );
		$chapter_label  = $chapter_number ? sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $chapter_number ) : $chapter->post_title;

		$message = sprintf(
			/* translators: 1: chapter label, 2: story title */
			__( '%1$s of "%2$s" has been updated', 'fanfiction-manager' ),
			$chapter_label,
			$story->post_title
		);

		$data = array(
			'chapter_id' => $chapter_id,
			'story_id'   => $story_id,
			'post_url'   => get_permalink( $chapter_id ),
		);

		// Get story followers (logged-in users only)
		$follower_ids = self::get_story_follower_user_ids( $story_id, $story->post_author );

		if ( empty( $follower_ids ) ) {
			return 0;
		}

		return self::batch_create_notifications( $follower_ids, self::TYPE_CHAPTER_UPDATE, $message, $data );
	}

	/**
	 * Create story status change notification
	 *
	 * Notifies story followers when story status changes (e.g., ongoing → completed).
	 *
	 * @since 1.8.0
	 * @param int    $story_id   Story ID.
	 * @param string $new_status New status term name.
	 * @param string $old_status Old status term name.
	 * @return int Count of notifications created.
	 */
	public static function create_story_status_notification( $story_id, $new_status, $old_status ) {
		$story = get_post( $story_id );
		if ( ! $story ) {
			return 0;
		}

		$message = sprintf(
			/* translators: 1: story title, 2: new status */
			__( '"%1$s" is now %2$s', 'fanfiction-manager' ),
			$story->post_title,
			$new_status
		);

		$data = array(
			'story_id'   => $story_id,
			'old_status' => $old_status,
			'new_status' => $new_status,
			'post_url'   => get_permalink( $story_id ),
		);

		// Get story followers (logged-in users only)
		$follower_ids = self::get_story_follower_user_ids( $story_id, $story->post_author );

		if ( empty( $follower_ids ) ) {
			return 0;
		}

		return self::batch_create_notifications( $follower_ids, self::TYPE_STORY_STATUS_CHANGE, $message, $data );
	}

	/**
	 * Get logged-in user IDs who follow a story
	 *
	 * Queries the interactions table for users who have followed the story.
	 * Excludes the story author to avoid self-notifications.
	 *
	 * @since 1.8.0
	 * @param int $story_id  Story ID.
	 * @param int $author_id Story author ID to exclude.
	 * @return array Array of user IDs.
	 */
	private static function get_story_follower_user_ids( $story_id, $author_id = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_interactions';
		$sql   = $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$table}
			WHERE post_id = %d AND interaction_type = 'follow' AND user_id > 0",
			absint( $story_id )
		);

		if ( $author_id ) {
			$sql .= $wpdb->prepare( ' AND user_id != %d', absint( $author_id ) );
		}

		$ids = $wpdb->get_col( $sql );

		return $ids ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * Handle new comment
	 *
	 * WordPress hook handler for wp_insert_comment.
	 *
	 * @since 1.0.0
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Comment approval status.
	 * @return void
	 */
	public static function handle_new_comment( $comment_id, $approved ) {
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

		// Create notification
		self::create_comment_notification( $comment_id );
	}

	/**
	 * Handle post status transition
	 *
	 * WordPress hook handler for transition_post_status.
	 *
	 * @since 1.0.0
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public static function handle_post_transition( $new_status, $old_status, $post ) {
		// Handle chapter publish
		if ( 'fanfiction_chapter' === $post->post_type ) {
			if ( 'publish' !== $new_status ) {
				return;
			}

			if ( 'publish' !== $old_status ) {
				// New publish — create chapter notification
				self::create_chapter_notification( $post->ID, $post->post_parent );
			} else {
				// Re-publish (update) — notify chapter bookmarkers and story followers
				self::create_chapter_update_notification( $post->ID, $post->post_parent );
			}
			return;
		}

		// Handle story status changes
		if ( 'fanfiction_story' === $post->post_type ) {
			// Only notify if both old and new status are publish (status updated while live)
			if ( 'publish' !== $new_status || 'publish' !== $old_status ) {
				return;
			}

			// Check if story status taxonomy changed (e.g., ongoing → completed)
			// This is handled by taxonomy change hook, not status transition
			return;
		}
	}

	/**
	 * Handle taxonomy changes for stories
	 *
	 * Handles story taxonomy change events.
	 *
	 * @since 1.0.16
	 * @param int    $object_id  Object ID (post ID).
	 * @param array  $terms      Term IDs being set.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append or replace terms.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 * @return void
	 */
	public static function handle_taxonomy_change( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		// Get the post
		$post = get_post( $object_id );

		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			return;
		}

		// Only notify for published stories
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check if terms actually changed
		if ( $tt_ids === $old_tt_ids ) {
			return;
		}

		// Get relevant taxonomies (genre, status, and custom taxonomies)
		$relevant_taxonomies = array( 'fanfiction_genre', 'fanfiction_status' );

		// Add custom taxonomies registered for stories
		$custom_taxonomies = get_object_taxonomies( 'fanfiction_story', 'names' );
		foreach ( $custom_taxonomies as $custom_tax ) {
			if ( ! in_array( $custom_tax, $relevant_taxonomies, true ) ) {
				$relevant_taxonomies[] = $custom_tax;
			}
		}

		// Only notify for relevant taxonomies
		if ( ! in_array( $taxonomy, $relevant_taxonomies, true ) ) {
			return;
		}

		// For fanfiction_status changes, notify story followers immediately
		if ( 'fanfiction_status' === $taxonomy ) {
			$old_terms = array();
			if ( ! empty( $old_tt_ids ) ) {
				foreach ( $old_tt_ids as $tt_id ) {
					$term = get_term_by( 'term_taxonomy_id', $tt_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$old_terms[] = $term->name;
					}
				}
			}

			$new_terms = array();
			if ( ! empty( $tt_ids ) ) {
				foreach ( $tt_ids as $tt_id ) {
					$term = get_term_by( 'term_taxonomy_id', $tt_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$new_terms[] = $term->name;
					}
				}
			}

			$old_status_name = ! empty( $old_terms ) ? implode( ', ', $old_terms ) : __( 'none', 'fanfiction-manager' );
			$new_status_name = ! empty( $new_terms ) ? implode( ', ', $new_terms ) : __( 'none', 'fanfiction-manager' );

			if ( $old_status_name !== $new_status_name ) {
				self::create_story_status_notification( $object_id, $new_status_name, $old_status_name );
			}
			return;
		}

		// Queue debounced notification (waits 20 minutes, consolidates multiple changes)
		self::queue_debounced_notification(
			$object_id,
			'taxonomy_' . $taxonomy,
			array(
				'taxonomy' => $taxonomy,
				'tt_ids'   => $tt_ids,
			)
		);
	}

	/**
	 * Handle title changes for stories and chapters
	 *
	 * Handles story/chapter title change events.
	 *
	 * @since 1.0.16
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 * @return void
	 */
	public static function handle_title_change( $post_id, $post_after, $post_before ) {
		// Only handle stories and chapters
		if ( ! in_array( $post_after->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Only notify for published content
		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		// Check if title actually changed
		if ( $post_before->post_title === $post_after->post_title ) {
			return;
		}

		// Handle story title changes - queue debounced notification
		if ( 'fanfiction_story' === $post_after->post_type ) {
			self::queue_debounced_notification(
				$post_id,
				'title',
				array(
					'old_title' => $post_before->post_title,
					'new_title' => $post_after->post_title,
				)
			);
		}

		// Handle chapter title changes - queue debounced notification for parent story
		if ( 'fanfiction_chapter' === $post_after->post_type ) {
			$story_id = $post_after->post_parent;

			if ( $story_id ) {
				self::queue_debounced_notification(
					$story_id,
					'chapter_title_' . $post_id,
					array(
						'chapter_id' => $post_id,
						'old_title'  => $post_before->post_title,
						'new_title'  => $post_after->post_title,
					)
				);
			}
		}
	}

	/**
	 * Queue a debounced notification for story updates
	 *
	 * Instead of sending notifications immediately, this queues the notification
	 * and waits 20 minutes. If more changes happen, the timer resets. Only the
	 * final net changes are notified.
	 *
	 * @since 1.0.16
	 * @param int    $story_id Story ID.
	 * @param string $change_type Type of change: 'title', 'taxonomy', 'chapter_title'.
	 * @param array  $change_data Data about the change.
	 * @return void
	 */
	private static function queue_debounced_notification( $story_id, $change_type, $change_data ) {
		return;
	}

	/**
	 * Process debounced notification
	 *
	 * This is called 20 minutes after the last change. It compares the current
	 * state with the original state and sends notifications only for net changes.
	 *
	 * @since 1.0.16
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function process_debounced_notification( $story_id ) {
		$story_id = absint( $story_id );
		delete_transient( 'fanfic_debounced_notify_' . $story_id );
	}

	/**
	 * Capture current state of a story
	 *
	 * @since 1.0.16
	 * @param int $story_id Story ID.
	 * @return array Story state data.
	 */
	private static function capture_story_state( $story_id ) {
		$story = get_post( $story_id );

		if ( ! $story ) {
			return array();
		}

		$state = array(
			'title'      => $story->post_title,
			'taxonomies' => array(),
			'chapters'   => array(),
		);

		// Capture standard taxonomies
		$taxonomies = array( 'fanfiction_genre', 'fanfiction_status' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $story_id, $taxonomy, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$state['taxonomies'][ $taxonomy ] = $terms;
			}
		}

		// Capture custom taxonomies
		$custom_taxonomies = get_object_taxonomies( 'fanfiction_story', 'names' );
		$state['custom_taxonomies'] = array();

		foreach ( $custom_taxonomies as $custom_tax ) {
			if ( ! in_array( $custom_tax, $taxonomies, true ) ) {
				$state['custom_taxonomies'][] = $custom_tax;
				$terms = wp_get_object_terms( $story_id, $custom_tax, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) ) {
					$state['taxonomies'][ $custom_tax ] = $terms;
				}
			}
		}

		// Capture chapter titles
		$chapters = get_posts(
			array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		foreach ( $chapters as $chapter ) {
			$state['chapters'][ $chapter->ID ] = $chapter->post_title;
		}

		return $state;
	}

	/**
	 * Build consolidated notification message
	 *
	 * Creates a human-readable message describing multiple changes.
	 *
	 * @since 1.0.16
	 * @param int   $story_id    Story ID.
	 * @param array $net_changes Array of net changes.
	 * @return string Notification message.
	 */
	private static function build_consolidated_message( $story_id, $net_changes ) {
		$story_title = get_the_title( $story_id );
		$change_descriptions = array();

		foreach ( $net_changes as $change_type => $change_data ) {
			if ( 'title' === $change_type ) {
				// Title change
				$change_descriptions[] = sprintf(
					/* translators: 1: Old title, 2: New title */
					__( 'title changed from "%1$s" to "%2$s"', 'fanfiction-manager' ),
					$change_data['old'],
					$change_data['new']
				);
			} elseif ( strpos( $change_type, 'chapter_title_' ) === 0 ) {
				// Chapter title change
				$chapter_id = $change_data['chapter_id'];
				$old_title = $change_data['old'];
				$new_title = $change_data['new'];

				// Get chapter label (Prologue, Chapter X, Epilogue)
				$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
				$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );

				if ( 'prologue' === $chapter_type || 'prologue' === $chapter_number ) {
					$chapter_label = __( 'Prologue', 'fanfiction-manager' );
				} elseif ( 'epilogue' === $chapter_type || 'epilogue' === $chapter_number ) {
					$chapter_label = __( 'Epilogue', 'fanfiction-manager' );
				} else {
					$chapter_label = sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $chapter_number );
				}

				// Handle empty titles (optional titles)
				$old_display = ! empty( $old_title ) ? $old_title : $chapter_label;
				$new_display = ! empty( $new_title ) ? $new_title : $chapter_label;

				$change_descriptions[] = sprintf(
					/* translators: 1: Chapter label, 2: Old title, 3: New title */
					__( '%1$s title changed from "%2$s" to "%3$s"', 'fanfiction-manager' ),
					$chapter_label,
					$old_display,
					$new_display
				);
			} elseif ( strpos( $change_type, 'taxonomy_' ) === 0 ) {
				// Taxonomy change
				$taxonomy = $change_data['taxonomy'];
				$tax_obj = get_taxonomy( $taxonomy );
				$tax_label = $tax_obj ? $tax_obj->labels->singular_name : ucfirst( str_replace( array( 'fanfiction_', '_' ), array( '', ' ' ), $taxonomy ) );

				$old_terms = $change_data['old'];
				$new_terms = $change_data['new'];

				if ( empty( $old_terms ) && ! empty( $new_terms ) ) {
					// Terms added
					$change_descriptions[] = sprintf(
						/* translators: 1: Taxonomy label, 2: New terms */
						__( '%1$s set to %2$s', 'fanfiction-manager' ),
						$tax_label,
						implode( ', ', $new_terms )
					);
				} elseif ( ! empty( $old_terms ) && empty( $new_terms ) ) {
					// Terms removed
					$change_descriptions[] = sprintf(
						/* translators: %s: Taxonomy label */
						__( '%s removed', 'fanfiction-manager' ),
						$tax_label
					);
				} else {
					// Terms changed
					$change_descriptions[] = sprintf(
						/* translators: 1: Taxonomy label, 2: Old terms, 3: New terms */
						__( '%1$s changed from %2$s to %3$s', 'fanfiction-manager' ),
						$tax_label,
						implode( ', ', $old_terms ),
						implode( ', ', $new_terms )
					);
				}
			}
		}

		// Build final message
		if ( count( $change_descriptions ) === 1 ) {
			// Single change
			return sprintf(
				/* translators: 1: Story title, 2: Change description */
				__( 'Story "%1$s" updated: %2$s', 'fanfiction-manager' ),
				$story_title,
				$change_descriptions[0]
			);
		} else {
			// Multiple changes
			return sprintf(
				/* translators: 1: Story title, 2: List of changes */
				__( 'Story "%1$s" updated: %2$s', 'fanfiction-manager' ),
				$story_title,
				implode( ', ', $change_descriptions )
			);
		}
	}
}
