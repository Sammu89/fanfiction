<?php
/**
 * Comments System Class
 *
 * Handles WordPress native comments configuration and custom features for fanfiction content.
 *
 * @package FanfictionManager
 * @subpackage Comments
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Comments
 *
 * Manages comment functionality for stories and chapters.
 *
 * @since 1.0.0
 */
class Fanfic_Comments {

	/**
	 * Grace period duration in minutes
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const GRACE_PERIOD_MINUTES = 30;

	/**
	 * Initialize the comments system
	 *
	 * Sets up WordPress hooks for comment functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Enable comments on fanfiction post types
		add_filter( 'comments_open', array( __CLASS__, 'enable_comments' ), 10, 2 );

		// Set comment threading depth
		add_filter( 'thread_comments_depth', array( __CLASS__, 'set_thread_depth' ) );

		// Add edit/delete links within grace period
		add_filter( 'comment_text', array( __CLASS__, 'add_grace_period_actions' ), 10, 2 );

		// Register AJAX handlers for edit/delete
		add_action( 'wp_ajax_fanfic_edit_comment', array( __CLASS__, 'ajax_edit_comment' ) );
		add_action( 'wp_ajax_fanfic_delete_comment', array( __CLASS__, 'ajax_delete_comment' ) );

		// Enqueue comment scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_comment_scripts' ) );

		// Customize comment form defaults
		add_filter( 'comment_form_defaults', array( __CLASS__, 'customize_comment_form' ) );

		// Add comment moderation integration
		add_action( 'wp_insert_comment', array( __CLASS__, 'on_comment_inserted' ), 10, 2 );

		// Block banned users from posting comments.
		add_filter( 'preprocess_comment', array( __CLASS__, 'block_banned_user_comment_submission' ) );
	}

	/**
	 * Check if a user has banned role.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function is_user_banned( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( 'fanfiction_banned_user', (array) $user->roles, true );
	}

	/**
	 * Enable comments on fanfiction post types
	 *
	 * @since 1.0.0
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool Whether comments are open.
	 */
	public static function enable_comments( $open, $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return $open;
		}

		// Level 1: Global setting
		$global_enabled = class_exists( 'Fanfic_Settings' )
			? Fanfic_Settings::get_setting( 'enable_comments', true )
			: true;
		if ( ! $global_enabled ) {
			return false;
		}

		// Level 2: Story-level setting
		if ( 'fanfiction_story' === $post_type ) {
			$story_meta = get_post_meta( $post_id, '_fanfic_comments_enabled', true );
			return '' === $story_meta || '1' === $story_meta;
		}

		// Level 3: Chapter - check parent story first, then chapter meta
		if ( 'fanfiction_chapter' === $post_type ) {
			$story_id = wp_get_post_parent_id( $post_id );
			if ( $story_id ) {
				$story_meta = get_post_meta( $story_id, '_fanfic_comments_enabled', true );
				if ( '1' !== $story_meta && '' !== $story_meta ) {
					return false;
				}
			}
			$chapter_meta = get_post_meta( $post_id, '_fanfic_chapter_comments_enabled', true );
			return '' === $chapter_meta || '1' === $chapter_meta;
		}

		return $open;
	}

	/**
	 * Set comment threading depth to 4 levels
	 *
	 * @since 1.0.0
	 * @param int $depth Threading depth.
	 * @return int Threading depth.
	 */
	public static function set_thread_depth( $depth ) {
		// Check if we're on a fanfiction post type
		if ( is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) {
			return 4;
		}

		return $depth;
	}

	/**
	 * Add edit/delete action buttons within grace period
	 *
	 * @since 1.0.0
	 * @param string     $comment_text Comment text.
	 * @param WP_Comment $comment      Comment object.
	 * @return string Modified comment text.
	 */
	public static function add_grace_period_actions( $comment_text, $comment ) {
		// Only for logged-in users on fanfiction posts
		if ( ! is_user_logged_in() || ! is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) {
			return $comment_text;
		}

		$current_user_id = get_current_user_id();
		if ( self::is_user_banned( $current_user_id ) ) {
			return $comment_text;
		}

		$comment_user_id = absint( $comment->user_id );

		// Check if user owns the comment or is moderator/admin
		$is_owner = ( $current_user_id === $comment_user_id );
		$is_moderator = current_user_can( 'moderate_fanfiction' ) || current_user_can( 'manage_options' );

		if ( ! $is_owner && ! $is_moderator ) {
			return $comment_text;
		}

		// Check if within grace period (30 minutes)
		$comment_time = strtotime( $comment->comment_date_gmt );
		$current_time = current_time( 'timestamp', true );
		$elapsed_minutes = ( $current_time - $comment_time ) / 60;
		$within_grace_period = ( $elapsed_minutes <= self::GRACE_PERIOD_MINUTES );

		// Moderators can always edit/delete
		if ( $is_moderator || ( $is_owner && $within_grace_period ) ) {
			$remaining_minutes = max( 0, self::GRACE_PERIOD_MINUTES - floor( $elapsed_minutes ) );

			// Build action buttons
			$actions = '<div class="fanfic-comment-actions" data-comment-id="' . esc_attr( $comment->comment_ID ) . '">';

			// Edit button
			$actions .= '<button class="fanfic-comment-edit-button" data-comment-id="' . esc_attr( $comment->comment_ID ) . '" aria-label="' . esc_attr__( 'Edit comment', 'fanfiction-manager' ) . '">';
			$actions .= esc_html__( 'Edit', 'fanfiction-manager' );
			$actions .= '</button>';

			// Delete button
			$actions .= '<button class="fanfic-comment-delete-button" data-comment-id="' . esc_attr( $comment->comment_ID ) . '" aria-label="' . esc_attr__( 'Delete comment', 'fanfiction-manager' ) . '">';
			$actions .= esc_html__( 'Delete', 'fanfiction-manager' );
			$actions .= '</button>';

			// Grace period timer (only for owners, not moderators)
			if ( $is_owner && ! $is_moderator && $within_grace_period && $remaining_minutes > 0 ) {
				$actions .= '<span class="fanfic-comment-timer" data-remaining="' . esc_attr( $remaining_minutes ) . '">';
				$actions .= sprintf(
					/* translators: %d: Remaining minutes */
					esc_html__( '(%d min left)', 'fanfiction-manager' ),
					absint( $remaining_minutes )
				);
				$actions .= '</span>';
			}

			$actions .= '</div>';

			$comment_text .= $actions;
		}

		return $comment_text;
	}

	/**
	 * AJAX handler for editing comments
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_edit_comment() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_comment_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		if ( self::is_user_banned( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account is suspended. You cannot edit comments.', 'fanfiction-manager' ) ) );
		}

		// Get comment ID and new content
		$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		$new_content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( empty( $comment_id ) || empty( $new_content ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid comment data.', 'fanfiction-manager' ) ) );
		}

		// Get comment
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			wp_send_json_error( array( 'message' => __( 'Comment not found.', 'fanfiction-manager' ) ) );
		}

		// Verify permissions
		$current_user_id = get_current_user_id();
		$is_owner = ( $current_user_id === absint( $comment->user_id ) );
		$is_moderator = current_user_can( 'moderate_fanfiction' ) || current_user_can( 'manage_options' );

		if ( ! $is_owner && ! $is_moderator ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this comment.', 'fanfiction-manager' ) ) );
		}

		// Check grace period for non-moderators
		if ( ! $is_moderator ) {
			$comment_time = strtotime( $comment->comment_date_gmt );
			$current_time = current_time( 'timestamp', true );
			$elapsed_minutes = ( $current_time - $comment_time ) / 60;

			if ( $elapsed_minutes > self::GRACE_PERIOD_MINUTES ) {
				wp_send_json_error( array( 'message' => __( 'Grace period has expired. You can no longer edit this comment.', 'fanfiction-manager' ) ) );
			}
		}

		// Update comment
		$result = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => $new_content,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update comment.', 'fanfiction-manager' ) ) );
		}

		// Add edit stamp metadata
		update_comment_meta( $comment_id, 'fanfic_edited_at', current_time( 'mysql' ) );
		update_comment_meta( $comment_id, 'fanfic_edited_by', $current_user_id );

		wp_send_json_success( array( 'message' => __( 'Comment updated successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * AJAX handler for deleting comments
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_delete_comment() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_comment_action' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		if ( self::is_user_banned( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account is suspended. You cannot delete comments.', 'fanfiction-manager' ) ) );
		}

		// Get comment ID
		$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;

		if ( empty( $comment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid comment ID.', 'fanfiction-manager' ) ) );
		}

		// Get comment
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			wp_send_json_error( array( 'message' => __( 'Comment not found.', 'fanfiction-manager' ) ) );
		}

		// Verify permissions
		$current_user_id = get_current_user_id();
		$is_owner = ( $current_user_id === absint( $comment->user_id ) );
		$is_moderator = current_user_can( 'moderate_fanfiction' ) || current_user_can( 'manage_options' );

		if ( ! $is_owner && ! $is_moderator ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this comment.', 'fanfiction-manager' ) ) );
		}

		// Check grace period for non-moderators
		if ( ! $is_moderator ) {
			$comment_time = strtotime( $comment->comment_date_gmt );
			$current_time = current_time( 'timestamp', true );
			$elapsed_minutes = ( $current_time - $comment_time ) / 60;

			if ( $elapsed_minutes > self::GRACE_PERIOD_MINUTES ) {
				wp_send_json_error( array( 'message' => __( 'Grace period has expired. You can no longer delete this comment.', 'fanfiction-manager' ) ) );
			}
		}

		// Delete comment permanently
		$result = wp_delete_comment( $comment_id, true );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete comment.', 'fanfiction-manager' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Comment deleted successfully.', 'fanfiction-manager' ) ) );
	}

	/**
	 * Enqueue comment-related scripts
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function enqueue_comment_scripts() {
		// Only on fanfiction single pages
		if ( ! is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) {
			return;
		}

		// Localize script with AJAX data
		wp_localize_script(
			'fanfiction-frontend',
			'fanficComments',
			array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'fanfic_comment_action' ),
				'gracePeriod'   => self::GRACE_PERIOD_MINUTES,
				'confirmDelete' => __( 'Are you sure you want to delete this comment? This action cannot be undone.', 'fanfiction-manager' ),
				'editLabel'     => __( 'Edit your comment:', 'fanfiction-manager' ),
				'saveLabel'     => __( 'Save', 'fanfiction-manager' ),
				'cancelLabel'   => __( 'Cancel', 'fanfiction-manager' ),
			)
		);
	}

	/**
	 * Customize comment form defaults
	 *
	 * @since 1.0.0
	 * @param array $defaults Comment form defaults.
	 * @return array Modified defaults.
	 */
	public static function customize_comment_form( $defaults ) {
		// Only on fanfiction post types
		if ( ! is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) ) {
			return $defaults;
		}

		if ( is_user_logged_in() && self::is_user_banned( get_current_user_id() ) ) {
			$defaults['title_reply']          = __( 'Comments Disabled', 'fanfiction-manager' );
			$defaults['comment_field']        = '<p class="fanfic-no-comments">' . esc_html__( 'Your account is suspended. You cannot post comments.', 'fanfiction-manager' ) . '</p>';
			$defaults['comment_notes_before'] = '';
			$defaults['comment_notes_after']  = '';
			$defaults['submit_button']        = '';
			$defaults['submit_field']         = '%1$s';
			$defaults['logged_in_as']         = '';

			return $defaults;
		}

		$defaults['title_reply']          = __( 'Leave a Comment', 'fanfiction-manager' );
		$defaults['title_reply_to']       = __( 'Reply to %s', 'fanfiction-manager' );
		$defaults['cancel_reply_link']    = __( 'Cancel Reply', 'fanfiction-manager' );
		$defaults['label_submit']         = __( 'Post Comment', 'fanfiction-manager' );
		$defaults['comment_field']        = '<p class="comment-form-comment"><label for="comment">' . __( 'Comment', 'fanfiction-manager' ) . ' <span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required" aria-required="true"></textarea></p>';
		$defaults['must_log_in']          = '<p class="must-log-in">' . sprintf(
			/* translators: %s: Login URL */
			__( 'You must be <a href="%s">logged in</a> to post a comment.', 'fanfiction-manager' ),
			esc_url( wp_login_url( fanfic_get_current_url() ) )
		) . '</p>';
		$defaults['logged_in_as']         = '<p class="logged-in-as">' . sprintf(
			/* translators: 1: User profile URL, 2: User name, 3: Logout URL */
			__( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s">Log out?</a>', 'fanfiction-manager' ),
			esc_url( get_edit_user_link() ),
			esc_html( wp_get_current_user()->display_name ),
			esc_url( wp_logout_url( fanfic_get_current_url() ) )
		) . '</p>';
		$defaults['comment_notes_before'] = '';
		$defaults['comment_notes_after']  = '<p class="comment-notes">' . __( 'Your comment will be visible immediately after posting.', 'fanfiction-manager' ) . '</p>';

		return $defaults;
	}

	/**
	 * Handle comment insertion
	 *
	 * Triggered when a new comment is posted.
	 *
	 * @since 1.0.0
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment    Comment object.
	 * @return void
	 */
	public static function on_comment_inserted( $comment_id, $comment ) {
		// Only handle fanfiction comments
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Send notification to story/chapter author
		$author_id = absint( $post->post_author );
		$commenter_id = absint( $comment->user_id );

		// Don't notify if author comments on their own content
		if ( $author_id === $commenter_id ) {
			return;
		}

		// Get post URL
		$post_url = get_permalink( $post->ID );
		$post_url = add_query_arg( 'comment_id', $comment_id, $post_url ) . '#comment-' . $comment_id;

		// Create notification message
		$message = sprintf(
			/* translators: 1: Commenter name, 2: Post type, 3: Post title */
			__( '%1$s commented on your %2$s: "%3$s"', 'fanfiction-manager' ),
			$comment->comment_author,
			'fanfiction_story' === $post->post_type ? __( 'story', 'fanfiction-manager' ) : __( 'chapter', 'fanfiction-manager' ),
			get_the_title( $post )
		);

		// Create in-app notification if user preferences allow
		if ( Fanfic_Notification_Preferences::should_create_inapp( $author_id, Fanfic_Notifications::TYPE_NEW_COMMENT ) ) {
			Fanfic_Notifications::create_notification(
				$author_id,
				Fanfic_Notifications::TYPE_NEW_COMMENT,
				$message,
				$post_url
			);
		}

		// Queue email notification if user preferences allow
		if ( Fanfic_Notification_Preferences::should_send_email( $author_id, Fanfic_Notifications::TYPE_NEW_COMMENT ) ) {
			Fanfic_Email_Sender::queue_email(
				$author_id,
				Fanfic_Notifications::TYPE_NEW_COMMENT,
				array(
					'commenter_name' => $comment->comment_author,
					'content_title'  => get_the_title( $post ),
					'content_url'    => $post_url,
					'comment_text'   => wp_trim_words( $comment->comment_content, 50 ),
				)
			);
		}
	}

	/**
	 * Check if user can edit comment (within grace period or moderator)
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @param int $user_id    User ID to check.
	 * @return bool Whether user can edit.
	 */
	public static function can_edit_comment( $comment_id, $user_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return false;
		}

		if ( self::is_user_banned( $user_id ) ) {
			return false;
		}

		// Check if moderator
		$user = get_userdata( $user_id );
		if ( $user && ( $user->has_cap( 'moderate_fanfiction' ) || $user->has_cap( 'manage_options' ) ) ) {
			return true;
		}

		// Check if owner within grace period
		if ( absint( $comment->user_id ) === absint( $user_id ) ) {
			$comment_time = strtotime( $comment->comment_date_gmt );
			$current_time = current_time( 'timestamp', true );
			$elapsed_minutes = ( $current_time - $comment_time ) / 60;

			return ( $elapsed_minutes <= self::GRACE_PERIOD_MINUTES );
		}

		return false;
	}

	/**
	 * Block comment submissions for banned users on fanfiction content.
	 *
	 * @since 1.0.0
	 * @param array $commentdata Raw comment payload.
	 * @return array
	 */
	public static function block_banned_user_comment_submission( $commentdata ) {
		if ( ! is_user_logged_in() ) {
			return $commentdata;
		}

		$current_user_id = get_current_user_id();
		if ( ! self::is_user_banned( $current_user_id ) ) {
			return $commentdata;
		}

		$post_id = isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0;
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return $commentdata;
		}

		wp_die(
			esc_html__( 'Your account is suspended. You cannot post comments.', 'fanfiction-manager' ),
			esc_html__( 'Commenting Disabled', 'fanfiction-manager' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Get remaining grace period in minutes
	 *
	 * @since 1.0.0
	 * @param int $comment_id Comment ID.
	 * @return int Remaining minutes (0 if expired).
	 */
	public static function get_remaining_grace_period( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return 0;
		}

		$comment_time = strtotime( $comment->comment_date_gmt );
		$current_time = current_time( 'timestamp', true );
		$elapsed_minutes = ( $current_time - $comment_time ) / 60;
		$remaining = self::GRACE_PERIOD_MINUTES - $elapsed_minutes;

		return max( 0, floor( $remaining ) );
	}
}
