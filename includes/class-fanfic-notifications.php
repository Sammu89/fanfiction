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
	const TYPE_COMMENT_REPLY = 'comment_reply';
	const TYPE_STORY_UPDATE = 'story_update';
	const TYPE_FOLLOW_STORY = 'follow_story';

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
			self::TYPE_NEW_FOLLOWER,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
			self::TYPE_COMMENT_REPLY,
			self::TYPE_STORY_UPDATE,
			self::TYPE_FOLLOW_STORY,
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
			self::TYPE_NEW_FOLLOWER,
			self::TYPE_NEW_CHAPTER,
			self::TYPE_NEW_STORY,
			self::TYPE_COMMENT_REPLY,
			self::TYPE_STORY_UPDATE,
			self::TYPE_FOLLOW_STORY,
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
	 * Create follow notification
	 *
	 * Notifies content creator when someone follows them or their story.
	 *
	 * @since 1.0.0
	 * @param int    $follower_id Follower user ID.
	 * @param int    $creator_id  Content creator user ID.
	 * @param string $follow_type Follow type: 'story' or 'author'.
	 * @param int    $target_id   Target ID (story ID or author ID).
	 * @return int|false Notification ID on success, false on failure.
	 */
	public static function create_follow_notification( $follower_id, $creator_id, $follow_type, $target_id ) {
		$follower = get_userdata( $follower_id );
		if ( ! $follower ) {
			return false;
		}

		if ( 'story' === $follow_type ) {
			$story = get_post( $target_id );
			if ( ! $story ) {
				return false;
			}

			$message = sprintf(
				/* translators: 1: follower name, 2: story title */
				__( '%1$s is now following your story "%2$s"', 'fanfiction-manager' ),
				$follower->display_name,
				$story->post_title
			);

			$data = array(
				'follower_id'   => $follower_id,
				'follower_name' => $follower->display_name,
				'story_id'      => $target_id,
				'story_title'   => $story->post_title,
			);
		} else {
			$message = sprintf(
				/* translators: %s: follower name */
				__( '%s is now following you', 'fanfiction-manager' ),
				$follower->display_name
			);

			$data = array(
				'follower_id'   => $follower_id,
				'follower_name' => $follower->display_name,
			);
		}

		return self::create_notification( $creator_id, self::TYPE_NEW_FOLLOWER, $message, $data );
	}

	/**
	 * Create chapter notification
	 *
	 * Called when new chapter is published. Creates in-app notifications for followers.
	 * Email notifications are handled by Email Queue class.
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return int Count of notifications created.
	 */
	public static function create_chapter_notification( $chapter_id, $story_id ) {
		global $wpdb;

		$chapter = get_post( $chapter_id );
		$story = get_post( $story_id );

		if ( ! $chapter || ! $story ) {
			return 0;
		}

		$author_id = $story->post_author;

		// Get all followers (both story and author follows)
		$followers = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}fanfic_follows
			WHERE (target_id = %d AND follow_type = 'story')
			OR (target_id = %d AND follow_type = 'author')",
			$story_id,
			$author_id
		) );

		if ( empty( $followers ) ) {
			return 0;
		}

		$message = sprintf(
			/* translators: %s: story title */
			__( 'New chapter published in "%s"', 'fanfiction-manager' ),
			$story->post_title
		);

		$data = array(
			'chapter_id'     => $chapter_id,
			'story_id'       => $story_id,
			'story_title'    => $story->post_title,
			'chapter_title'  => $chapter->post_title,
			'chapter_number' => get_post_meta( $chapter_id, '_chapter_number', true ),
		);

		return self::batch_create_notifications( $followers, self::TYPE_NEW_CHAPTER, $message, $data );
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

		// Don't notify author of their own comments
		if ( $post->post_author == $comment->user_id ) {
			return false;
		}

		$commenter = get_userdata( $comment->user_id );
		$commenter_name = $commenter ? $commenter->display_name : $comment->comment_author;

		$message = sprintf(
			/* translators: 1: commenter name, 2: post title */
			__( '%1$s commented on "%2$s"', 'fanfiction-manager' ),
			$commenter_name,
			$post->post_title
		);

		$data = array(
			'comment_id'     => $comment_id,
			'post_id'        => $post->ID,
			'post_title'     => $post->post_title,
			'commenter_name' => $commenter_name,
			'comment_text'   => wp_trim_words( $comment->comment_content, 20 ),
		);

		return self::create_notification( $post->post_author, self::TYPE_NEW_COMMENT, $message, $data );
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
			// Only trigger on new publish
			if ( 'publish' !== $new_status || 'publish' === $old_status ) {
				return;
			}

			// Create chapter notification
			self::create_chapter_notification( $post->ID, $post->post_parent );
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
	 * Notifies story followers when taxonomies change (genre, status, custom taxonomies).
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

		// Get story followers (people who follow this specific story)
		$followers = Fanfic_Follows::get_target_followers( $object_id, 'story', 999 );

		if ( empty( $followers ) ) {
			return;
		}

		// Get taxonomy label
		$tax_obj = get_taxonomy( $taxonomy );
		$tax_label = $tax_obj ? $tax_obj->labels->singular_name : ucfirst( str_replace( array( 'fanfiction_', '_' ), array( '', ' ' ), $taxonomy ) );

		// Get old and new term names
		$old_term_names = array();
		if ( ! empty( $old_tt_ids ) ) {
			foreach ( $old_tt_ids as $tt_id ) {
				$term = get_term_by( 'term_taxonomy_id', $tt_id );
				if ( $term ) {
					$old_term_names[] = $term->name;
				}
			}
		}

		$new_term_names = array();
		if ( ! empty( $tt_ids ) ) {
			foreach ( $tt_ids as $tt_id ) {
				$term = get_term_by( 'term_taxonomy_id', $tt_id );
				if ( $term ) {
					$new_term_names[] = $term->name;
				}
			}
		}

		// Build notification message
		$story_title = get_the_title( $object_id );
		$story_url = get_permalink( $object_id );

		if ( empty( $old_term_names ) && ! empty( $new_term_names ) ) {
			// Terms added
			$message = sprintf(
				/* translators: 1: Story title, 2: Taxonomy label, 3: New terms */
				__( 'Story "%1$s" %2$s updated to: %3$s', 'fanfiction-manager' ),
				$story_title,
				$tax_label,
				implode( ', ', $new_term_names )
			);
		} elseif ( ! empty( $old_term_names ) && empty( $new_term_names ) ) {
			// Terms removed
			$message = sprintf(
				/* translators: 1: Story title, 2: Taxonomy label */
				__( 'Story "%1$s" %2$s removed', 'fanfiction-manager' ),
				$story_title,
				$tax_label
			);
		} else {
			// Terms changed
			$message = sprintf(
				/* translators: 1: Story title, 2: Taxonomy label, 3: Old terms, 4: New terms */
				__( 'Story "%1$s" %2$s changed from %3$s to %4$s', 'fanfiction-manager' ),
				$story_title,
				$tax_label,
				implode( ', ', $old_term_names ),
				implode( ', ', $new_term_names )
			);
		}

		// Notify all followers
		foreach ( $followers as $follower ) {
			$follower_id = absint( $follower['user_id'] );

			// Don't notify the story author
			if ( $follower_id === absint( $post->post_author ) ) {
				continue;
			}

			// Create notification
			self::create_notification(
				$follower_id,
				self::TYPE_STORY_UPDATE,
				$message,
				array(
					'story_id'    => $object_id,
					'story_title' => $story_title,
					'story_url'   => $story_url,
					'taxonomy'    => $taxonomy,
					'old_terms'   => $old_term_names,
					'new_terms'   => $new_term_names,
				)
			);

			// Queue email if user has email notifications enabled
			if ( ! empty( $follower['email_enabled'] ) ) {
				Fanfic_Email_Queue::queue_email(
					$follower_id,
					'story_taxonomy_update',
					$message,
					array(
						'story_id'    => $object_id,
						'story_title' => $story_title,
						'story_url'   => $story_url,
						'taxonomy'    => $tax_label,
						'old_terms'   => implode( ', ', $old_term_names ),
						'new_terms'   => implode( ', ', $new_term_names ),
					)
				);
			}
		}
	}

	/**
	 * Handle title changes for stories and chapters
	 *
	 * Notifies story followers when story or chapter titles change.
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

		// Handle story title changes
		if ( 'fanfiction_story' === $post_after->post_type ) {
			self::notify_story_title_change( $post_id, $post_before->post_title, $post_after->post_title, $post_after->post_author );
		}

		// Handle chapter title changes
		if ( 'fanfiction_chapter' === $post_after->post_type ) {
			self::notify_chapter_title_change( $post_id, $post_before->post_title, $post_after->post_title, $post_after->post_parent, $post_after->post_author );
		}
	}

	/**
	 * Notify followers when story title changes
	 *
	 * @since 1.0.16
	 * @param int    $story_id  Story ID.
	 * @param string $old_title Old title.
	 * @param string $new_title New title.
	 * @param int    $author_id Author ID.
	 * @return void
	 */
	private static function notify_story_title_change( $story_id, $old_title, $new_title, $author_id ) {
		// Get story followers
		$followers = Fanfic_Follows::get_target_followers( $story_id, 'story', 999 );

		if ( empty( $followers ) ) {
			return;
		}

		$story_url = get_permalink( $story_id );

		// Build notification message
		$message = sprintf(
			/* translators: 1: Old title, 2: New title */
			__( 'Story title changed from "%1$s" to "%2$s"', 'fanfiction-manager' ),
			$old_title,
			$new_title
		);

		// Notify all followers
		foreach ( $followers as $follower ) {
			$follower_id = absint( $follower['user_id'] );

			// Don't notify the story author
			if ( $follower_id === absint( $author_id ) ) {
				continue;
			}

			// Create notification
			self::create_notification(
				$follower_id,
				self::TYPE_STORY_UPDATE,
				$message,
				array(
					'story_id'  => $story_id,
					'story_url' => $story_url,
					'old_title' => $old_title,
					'new_title' => $new_title,
				)
			);

			// Queue email if user has email notifications enabled
			if ( ! empty( $follower['email_enabled'] ) ) {
				Fanfic_Email_Queue::queue_email(
					$follower_id,
					'story_title_update',
					$message,
					array(
						'story_id'  => $story_id,
						'story_url' => $story_url,
						'old_title' => $old_title,
						'new_title' => $new_title,
					)
				);
			}
		}
	}

	/**
	 * Notify followers when chapter title changes
	 *
	 * @since 1.0.16
	 * @param int    $chapter_id Chapter ID.
	 * @param string $old_title  Old title.
	 * @param string $new_title  New title.
	 * @param int    $story_id   Parent story ID.
	 * @param int    $author_id  Author ID.
	 * @return void
	 */
	private static function notify_chapter_title_change( $chapter_id, $old_title, $new_title, $story_id, $author_id ) {
		// Get story followers (followers follow the story, not individual chapters)
		$followers = Fanfic_Follows::get_target_followers( $story_id, 'story', 999 );

		if ( empty( $followers ) ) {
			return;
		}

		$story_title = get_the_title( $story_id );
		$chapter_url = get_permalink( $chapter_id );

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

		// Build notification message
		$message = sprintf(
			/* translators: 1: Story title, 2: Chapter label, 3: Old title, 4: New title */
			__( 'Story "%1$s" - %2$s title changed from "%3$s" to "%4$s"', 'fanfiction-manager' ),
			$story_title,
			$chapter_label,
			$old_display,
			$new_display
		);

		// Notify all followers
		foreach ( $followers as $follower ) {
			$follower_id = absint( $follower['user_id'] );

			// Don't notify the story author
			if ( $follower_id === absint( $author_id ) ) {
				continue;
			}

			// Create notification
			self::create_notification(
				$follower_id,
				self::TYPE_STORY_UPDATE,
				$message,
				array(
					'story_id'      => $story_id,
					'story_title'   => $story_title,
					'chapter_id'    => $chapter_id,
					'chapter_url'   => $chapter_url,
					'chapter_label' => $chapter_label,
					'old_title'     => $old_title,
					'new_title'     => $new_title,
				)
			);

			// Queue email if user has email notifications enabled
			if ( ! empty( $follower['email_enabled'] ) ) {
				Fanfic_Email_Queue::queue_email(
					$follower_id,
					'chapter_title_update',
					$message,
					array(
						'story_id'      => $story_id,
						'story_title'   => $story_title,
						'chapter_id'    => $chapter_id,
						'chapter_url'   => $chapter_url,
						'chapter_label' => $chapter_label,
						'old_title'     => $old_title,
						'new_title'     => $new_title,
					)
				);
			}
		}
	}
}
