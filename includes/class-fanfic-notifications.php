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
	const TYPE_NEW_FOLLOWER = 'new_follower';
	const TYPE_NEW_CHAPTER = 'new_chapter';
	const TYPE_NEW_STORY = 'new_story';

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

		// Schedule cron job on init if not already scheduled
		if ( ! wp_next_scheduled( 'fanfic_cleanup_old_notifications' ) ) {
			self::schedule_cleanup_cron();
		}
	}

	/**
	 * Create a new notification
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID to receive notification.
	 * @param string $type    Notification type (use class constants).
	 * @param string $message Notification message.
	 * @param string $link    URL link for notification.
	 * @return int|false Notification ID on success, false on failure.
	 */
	public static function create_notification( $user_id, $type, $message, $link = '' ) {
		global $wpdb;

		// Validate inputs
		$user_id = absint( $user_id );
		if ( ! $user_id || ! get_user_by( 'ID', $user_id ) ) {
			return false;
		}

		$valid_types = array(
			self::TYPE_NEW_COMMENT,
			self::TYPE_NEW_FOLLOWER,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
		);

		if ( ! in_array( $type, $valid_types, true ) ) {
			return false;
		}

		$message = sanitize_text_field( $message );
		$link = esc_url_raw( $link );

		// Insert notification
		$table_name = $wpdb->prefix . 'fanfic_notifications';
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'type'       => $type,
				'message'    => $message,
				'link'       => $link,
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
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_notifications';

		// Delete notifications older than 90 days
		$result = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			90
		) );

		// Log cleanup
		if ( $result > 0 ) {
			error_log( sprintf( 'Fanfiction Manager: Cleaned up %d old notifications', $result ) );
		}

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

		// Calculate next run time
		$next_run = strtotime( sprintf( 'tomorrow %d:00:00', $cron_hour ) );

		// Schedule event
		wp_schedule_event( $next_run, 'daily', 'fanfic_cleanup_old_notifications' );
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
			self::TYPE_NEW_FOLLOWER => __( 'New Follower', 'fanfiction-manager' ),
			self::TYPE_NEW_CHAPTER => __( 'New Chapter', 'fanfiction-manager' ),
			self::TYPE_NEW_STORY => __( 'New Story', 'fanfiction-manager' ),
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
}
