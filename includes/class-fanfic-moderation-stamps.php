<?php
/**
 * Moderation Stamps Class
 *
 * Tracks all moderation actions on stories, chapters, and comments.
 * Creates an audit trail of who moderated content, when, and what action was taken.
 *
 * @package FanfictionManager
 * @subpackage Moderation
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Moderation_Stamps
 *
 * Handles moderation stamp tracking for audit trails.
 *
 * @since 1.0.0
 */
class Fanfic_Moderation_Stamps {

	/**
	 * Initialize the moderation stamps system
	 *
	 * Sets up WordPress hooks for tracking moderation actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Track when posts are edited by moderators/admins
		add_action( 'post_updated', array( __CLASS__, 'track_post_edit' ), 10, 3 );

		// Track when posts are trashed/deleted by moderators
		add_action( 'wp_trash_post', array( __CLASS__, 'track_post_trash' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'track_post_deletion' ) );

		// Track when comments are moderated
		add_action( 'wp_set_comment_status', array( __CLASS__, 'track_comment_moderation' ), 10, 2 );
		add_action( 'edit_comment', array( __CLASS__, 'track_comment_edit' ) );
		add_action( 'delete_comment', array( __CLASS__, 'track_comment_deletion' ) );

		// Add meta boxes to display moderation history
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_moderation_history_meta_box' ) );

		// Display moderation stamps in admin columns
		add_filter( 'manage_fanfiction_story_posts_columns', array( __CLASS__, 'add_moderation_column' ) );
		add_filter( 'manage_fanfiction_chapter_posts_columns', array( __CLASS__, 'add_moderation_column' ) );
		add_action( 'manage_fanfiction_story_posts_custom_column', array( __CLASS__, 'display_moderation_column' ), 10, 2 );
		add_action( 'manage_fanfiction_chapter_posts_custom_column', array( __CLASS__, 'display_moderation_column' ), 10, 2 );
	}

	/**
	 * Track when a post is edited
	 *
	 * Records moderation stamp if edited by moderator/admin who is not the author.
	 *
	 * @since 1.0.0
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 * @return void
	 */
	public static function track_post_edit( $post_id, $post_after, $post_before ) {
		// Only track fanfiction post types
		if ( ! in_array( $post_after->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Get current user
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return;
		}

		// Check if current user is a moderator or admin
		if ( ! self::is_moderator( $current_user_id ) ) {
			return;
		}

		// Don't track if user is editing their own content
		$post_author = (int) $post_after->post_author;
		if ( $current_user_id === $post_author ) {
			return;
		}

		// Check if content actually changed
		if ( $post_before->post_title === $post_after->post_title &&
		     $post_before->post_content === $post_after->post_content &&
		     $post_before->post_status === $post_after->post_status ) {
			return; // No actual changes
		}

		// Add moderation stamp
		self::add_stamp( $post_id, 'edited', $current_user_id, array(
			'previous_status' => $post_before->post_status,
			'new_status'      => $post_after->post_status,
		) );
	}

	/**
	 * Track when a post is trashed
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function track_post_trash( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id || ! self::is_moderator( $current_user_id ) ) {
			return;
		}

		// Don't track if user is trashing their own content
		if ( $current_user_id === (int) $post->post_author ) {
			return;
		}

		self::add_stamp( $post_id, 'trashed', $current_user_id );
	}

	/**
	 * Track when a post is permanently deleted
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function track_post_deletion( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id || ! self::is_moderator( $current_user_id ) ) {
			return;
		}

		// Don't track if user is deleting their own content
		if ( $current_user_id === (int) $post->post_author ) {
			return;
		}

		self::add_stamp( $post_id, 'deleted', $current_user_id );
	}

	/**
	 * Track when a comment is moderated (approved, held, spammed, etc.)
	 *
	 * @since 1.0.0
	 * @param int    $comment_id     Comment ID.
	 * @param string $comment_status New comment status.
	 * @return void
	 */
	public static function track_comment_moderation( $comment_id, $comment_status ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		// Only track fanfiction post types
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id || ! self::is_moderator( $current_user_id ) ) {
			return;
		}

		// Don't track if user is moderating their own comment
		if ( $comment->user_id && $current_user_id === (int) $comment->user_id ) {
			return;
		}

		// Add stamp to comment meta
		update_comment_meta( $comment_id, 'fanfic_moderated_at', current_time( 'mysql' ) );
		update_comment_meta( $comment_id, 'fanfic_moderated_by', $current_user_id );
		update_comment_meta( $comment_id, 'fanfic_moderation_action', sanitize_text_field( $comment_status ) );
	}

	/**
	 * Track when a comment is edited
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public static function track_comment_edit( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		// Only track fanfiction post types
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return;
		}

		// Track if moderator/admin edited someone else's comment
		if ( self::is_moderator( $current_user_id ) && ( ! $comment->user_id || $current_user_id !== (int) $comment->user_id ) ) {
			update_comment_meta( $comment_id, 'fanfic_edited_at', current_time( 'mysql' ) );
			update_comment_meta( $comment_id, 'fanfic_edited_by', $current_user_id );
			update_comment_meta( $comment_id, 'fanfic_edit_reason', 'moderator_edit' );
		}
	}

	/**
	 * Track when a comment is deleted
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public static function track_comment_deletion( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		// Only track fanfiction post types
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id || ! self::is_moderator( $current_user_id ) ) {
			return;
		}

		// Don't track if user is deleting their own comment
		if ( $comment->user_id && $current_user_id === (int) $comment->user_id ) {
			return;
		}

		// Log deletion to post meta (can't use comment meta as comment will be deleted)
		self::add_stamp( $post->ID, 'comment_deleted', $current_user_id, array(
			'comment_id'     => $comment_id,
			'comment_author' => $comment->comment_author,
			'comment_date'   => $comment->comment_date,
		) );
	}

	/**
	 * Add a moderation stamp to a post
	 *
	 * @since 1.0.0
	 * @param int    $post_id      Post ID.
	 * @param string $action       Action taken (edited, trashed, deleted, etc.).
	 * @param int    $moderator_id User ID of moderator.
	 * @param array  $data         Additional data to store (optional).
	 * @return void
	 */
	public static function add_stamp( $post_id, $action, $moderator_id, $data = array() ) {
		// Get existing stamps
		$stamps = get_post_meta( $post_id, 'fanfic_moderation_stamps', true );
		if ( ! is_array( $stamps ) ) {
			$stamps = array();
		}

		// Add new stamp
		$stamps[] = array(
			'action'       => sanitize_text_field( $action ),
			'moderator_id' => absint( $moderator_id ),
			'timestamp'    => current_time( 'mysql' ),
			'data'         => $data,
		);

		// Save stamps
		update_post_meta( $post_id, 'fanfic_moderation_stamps', $stamps );

		// Also update latest moderation info for quick access
		update_post_meta( $post_id, 'fanfic_last_moderated_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, 'fanfic_last_moderated_by', absint( $moderator_id ) );
		update_post_meta( $post_id, 'fanfic_last_moderation_action', sanitize_text_field( $action ) );
	}

	/**
	 * Get all moderation stamps for a post
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return array Array of moderation stamps.
	 */
	public static function get_stamps( $post_id ) {
		$stamps = get_post_meta( $post_id, 'fanfic_moderation_stamps', true );
		return is_array( $stamps ) ? $stamps : array();
	}

	/**
	 * Check if user is a moderator or admin
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool True if user can moderate.
	 */
	private static function is_moderator( $user_id ) {
		return user_can( $user_id, 'moderate_fanfiction' ) || user_can( $user_id, 'manage_options' );
	}

	/**
	 * Add moderation history meta box to post edit screen
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_moderation_history_meta_box() {
		$post_types = array( 'fanfiction_story', 'fanfiction_chapter' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fanfic_moderation_history',
				__( 'Moderation History', 'fanfiction-manager' ),
				array( __CLASS__, 'render_moderation_history_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render moderation history meta box
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_moderation_history_meta_box( $post ) {
		$stamps = self::get_stamps( $post->ID );

		if ( empty( $stamps ) ) {
			echo '<p>' . esc_html__( 'No moderation actions recorded.', 'fanfiction-manager' ) . '</p>';
			return;
		}

		echo '<div class="fanfic-moderation-stamps">';
		echo '<style>
			.fanfic-moderation-stamps { font-size: 12px; }
			.fanfic-stamp { padding: 8px; border-left: 3px solid #ddd; margin-bottom: 8px; background: #f9f9f9; }
			.fanfic-stamp-action { font-weight: bold; color: #d63638; }
			.fanfic-stamp-moderator { color: #2271b1; }
			.fanfic-stamp-time { color: #646970; font-size: 11px; }
		</style>';

		// Reverse to show newest first
		$stamps = array_reverse( $stamps );

		foreach ( $stamps as $stamp ) {
			$moderator = get_userdata( $stamp['moderator_id'] );
			$moderator_name = $moderator ? $moderator->display_name : __( 'Unknown', 'fanfiction-manager' );
			$action_label = self::get_action_label( $stamp['action'] );

			echo '<div class="fanfic-stamp">';
			echo '<div class="fanfic-stamp-action">' . esc_html( $action_label ) . '</div>';
			echo '<div class="fanfic-stamp-moderator">' . esc_html__( 'By:', 'fanfiction-manager' ) . ' ' . esc_html( $moderator_name ) . '</div>';
			echo '<div class="fanfic-stamp-time">' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $stamp['timestamp'] ) ) . '</div>';

			// Display additional data if present
			if ( ! empty( $stamp['data'] ) ) {
				if ( isset( $stamp['data']['previous_status'] ) && isset( $stamp['data']['new_status'] ) ) {
					echo '<div class="fanfic-stamp-details" style="margin-top: 4px; font-size: 11px;">';
					echo esc_html( sprintf( __( 'Status changed: %s → %s', 'fanfiction-manager' ), $stamp['data']['previous_status'], $stamp['data']['new_status'] ) );
					echo '</div>';
				}
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Get human-readable action label
	 *
	 * @since 1.0.0
	 * @param string $action Action key.
	 * @return string Translated action label.
	 */
	private static function get_action_label( $action ) {
		$labels = array(
			'edited'          => __( 'Content Edited', 'fanfiction-manager' ),
			'trashed'         => __( 'Moved to Trash', 'fanfiction-manager' ),
			'deleted'         => __( 'Permanently Deleted', 'fanfiction-manager' ),
			'comment_deleted' => __( 'Comment Deleted', 'fanfiction-manager' ),
			'status_changed'  => __( 'Status Changed', 'fanfiction-manager' ),
			'restored'        => __( 'Restored from Trash', 'fanfiction-manager' ),
		);

		return isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( $action );
	}

	/**
	 * Add moderation column to admin posts list
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_moderation_column( $columns ) {
		// Only show to moderators/admins
		if ( ! current_user_can( 'moderate_fanfiction' ) && ! current_user_can( 'manage_options' ) ) {
			return $columns;
		}

		// Add column before date
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( 'date' === $key ) {
				$new_columns['fanfic_moderated'] = __( 'Last Moderated', 'fanfiction-manager' );
			}
			$new_columns[ $key ] = $value;
		}

		return $new_columns;
	}

	/**
	 * Display moderation column content
	 *
	 * @since 1.0.0
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function display_moderation_column( $column, $post_id ) {
		if ( 'fanfic_moderated' !== $column ) {
			return;
		}

		$last_moderated_at = get_post_meta( $post_id, 'fanfic_last_moderated_at', true );

		if ( ! $last_moderated_at ) {
			echo '<span style="color: #646970;">—</span>';
			return;
		}

		$moderator_id = get_post_meta( $post_id, 'fanfic_last_moderated_by', true );
		$action = get_post_meta( $post_id, 'fanfic_last_moderation_action', true );

		$moderator = $moderator_id ? get_userdata( $moderator_id ) : null;
		$moderator_name = $moderator ? $moderator->display_name : __( 'Unknown', 'fanfiction-manager' );
		$action_label = $action ? self::get_action_label( $action ) : __( 'Moderated', 'fanfiction-manager' );

		$time_diff = human_time_diff( strtotime( $last_moderated_at ), current_time( 'timestamp' ) );

		echo '<div style="font-size: 12px;">';
		echo '<strong style="color: #d63638;">' . esc_html( $action_label ) . '</strong><br>';
		echo '<span style="color: #2271b1;">' . esc_html( $moderator_name ) . '</span><br>';
		echo '<span style="color: #646970;">' . esc_html( sprintf( __( '%s ago', 'fanfiction-manager' ), $time_diff ) ) . '</span>';
		echo '</div>';
	}
}
