<?php
/**
 * Action Shortcodes Class
 *
 * Handles all interactive action shortcodes (bookmarks, follows, reports, shares).
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Actions
 *
 * Interactive action buttons and AJAX handlers.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Actions {

	/**
	 * Register action shortcodes and AJAX handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Register shortcodes
		add_shortcode( 'content-actions', array( __CLASS__, 'content_actions' ) );
		add_shortcode( 'story-actions', array( __CLASS__, 'story_actions' ) );
		add_shortcode( 'chapter-actions', array( __CLASS__, 'chapter_actions' ) );
		add_shortcode( 'author-actions', array( __CLASS__, 'author_actions' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_bookmark_story', array( __CLASS__, 'ajax_bookmark_story' ) );
		add_action( 'wp_ajax_fanfic_unbookmark_story', array( __CLASS__, 'ajax_unbookmark_story' ) );
		add_action( 'wp_ajax_fanfic_follow_author', array( __CLASS__, 'ajax_follow_author' ) );
		add_action( 'wp_ajax_fanfic_unfollow_author', array( __CLASS__, 'ajax_unfollow_author' ) );
		add_action( 'wp_ajax_fanfic_report_content', array( __CLASS__, 'ajax_report_content' ) );
		add_action( 'wp_ajax_fanfic_like_content', array( __CLASS__, 'ajax_like_content' ) );
		add_action( 'wp_ajax_fanfic_unlike_content', array( __CLASS__, 'ajax_unlike_content' ) );
		add_action( 'wp_ajax_fanfic_add_to_read_list', array( __CLASS__, 'ajax_add_to_read_list' ) );
		add_action( 'wp_ajax_fanfic_remove_from_read_list', array( __CLASS__, 'ajax_remove_from_read_list' ) );
		add_action( 'wp_ajax_fanfic_mark_as_read', array( __CLASS__, 'ajax_mark_as_read' ) );
		add_action( 'wp_ajax_fanfic_unmark_as_read', array( __CLASS__, 'ajax_unmark_as_read' ) );
		add_action( 'wp_ajax_fanfic_subscribe_to_story', array( __CLASS__, 'ajax_subscribe_to_story' ) );

		// Register AJAX handlers for non-logged-in users (when settings allow)
		add_action( 'wp_ajax_nopriv_fanfic_like_content', array( __CLASS__, 'ajax_like_content' ) );
		add_action( 'wp_ajax_nopriv_fanfic_report_content', array( __CLASS__, 'ajax_report_content' ) );
		add_action( 'wp_ajax_nopriv_fanfic_subscribe_to_story', array( __CLASS__, 'ajax_subscribe_to_story' ) );

		// Register cron job for syncing anonymous likes
		add_action( 'fanfic_daily_sync_anonymous_likes', array( __CLASS__, 'sync_anonymous_likes_to_database' ) );

		// Schedule cron job if not already scheduled
		if ( ! wp_next_scheduled( 'fanfic_daily_sync_anonymous_likes' ) ) {
			$settings = get_option( 'fanfic_settings', array() );
			$cron_hour = isset( $settings['cron_hour'] ) ? absint( $settings['cron_hour'] ) : 3;

			// Calculate next run time
			$current_time = current_time( 'timestamp' );
			$scheduled_time = strtotime( gmdate( 'Y-m-d' ) . ' ' . $cron_hour . ':00:00' );

			// If the scheduled time for today has already passed, schedule for tomorrow
			if ( $scheduled_time <= $current_time ) {
				$scheduled_time = strtotime( '+1 day', $scheduled_time );
			}

			wp_schedule_event( $scheduled_time, 'daily', 'fanfic_daily_sync_anonymous_likes' );
		}

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue action scripts and styles
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'fanfic-actions',
			FANFIC_PLUGIN_URL . 'assets/js/fanfiction-actions.js',
			array( 'jquery' ),
			FANFIC_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script(
			'fanfic-actions',
			'fanficActions',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'fanfic_actions_nonce' ),
				'loginUrl'      => wp_login_url( get_permalink() ),
				'isLoggedIn'    => is_user_logged_in(),
				'recaptchaSite' => get_option( 'fanfic_recaptcha_site_key', '' ),
			)
		);

		// Enqueue reCAPTCHA if configured
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );
		if ( ! empty( $recaptcha_site_key ) ) {
			wp_enqueue_script(
				'google-recaptcha',
				'https://www.google.com/recaptcha/api.js',
				array(),
				null,
				true
			);
		}
	}

	/**
	 * Unified content actions shortcode with auto-detection
	 *
	 * [content-actions]
	 *
	 * Auto-detects context (story/chapter/author) and displays appropriate buttons.
	 * Adapts based on user permissions and admin settings.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes (unused - auto-detection only).
	 * @return string Action buttons HTML.
	 */
	public static function content_actions( $atts = array() ) {
		global $post;

		// Get admin settings
		$settings = get_option( 'fanfic_settings', array() );
		$enable_likes = isset( $settings['enable_likes'] ) ? $settings['enable_likes'] : true;
		$enable_subscribe = isset( $settings['enable_subscribe'] ) ? $settings['enable_subscribe'] : true;
		$enable_report = isset( $settings['enable_report'] ) ? $settings['enable_report'] : true;
		$allow_anonymous_likes = isset( $settings['allow_anonymous_likes'] ) ? $settings['allow_anonymous_likes'] : false;
		$allow_anonymous_reports = isset( $settings['allow_anonymous_reports'] ) ? $settings['allow_anonymous_reports'] : false;

		// Check if reCAPTCHA is configured (required for anonymous reports)
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );
		$recaptcha_secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );
		$has_recaptcha = ! empty( $recaptcha_site_key ) && ! empty( $recaptcha_secret_key );

		// Disable anonymous reports if reCAPTCHA is not configured
		if ( $allow_anonymous_reports && ! $has_recaptcha ) {
			$allow_anonymous_reports = false;
		}

		$is_logged_in = is_user_logged_in();
		$user_id = get_current_user_id();

		// Auto-detect context
		$context = 'unknown';
		$item_id = 0;
		$story_id = 0;
		$chapter_id = 0;
		$author_id = 0;

		// Try to detect chapter
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();
		if ( $chapter_id ) {
			$context = 'chapter';
			$item_id = $chapter_id;
			$story_id = get_post_field( 'post_parent', $chapter_id );
		} else {
			// Try to detect story
			$story_id = Fanfic_Shortcodes::get_current_story_id();
			if ( $story_id ) {
				$context = 'story';
				$item_id = $story_id;
			} else {
				// Try to detect author profile (author archive or single fanfiction post)
				if ( is_author() ) {
					$context = 'author';
					$author_id = get_queried_object_id();
					$item_id = $author_id;
				} elseif ( is_singular( array( 'fanfiction_story', 'fanfiction_chapter' ) ) && $post ) {
					// On single story/chapter page, could show author actions
					$context = 'author';
					$author_id = absint( $post->post_author );
					$item_id = $author_id;
				}
			}
		}

		// If no context detected, return empty
		if ( 'unknown' === $context ) {
			return '';
		}

		// Get user states
		$is_bookmarked = false;
		$is_liked = false;
		$is_in_read_list = false;
		$is_marked_read = false;
		$is_subscribed = false;
		$is_following = false;

		if ( $is_logged_in ) {
			if ( 'author' === $context ) {
				$is_following = self::is_following_author( $author_id, $user_id );
			} else {
				$is_bookmarked = self::is_story_bookmarked( $story_id, $user_id );
				$is_liked = self::is_content_liked( $item_id, $context, $user_id );
				$is_in_read_list = self::is_in_read_list( $story_id, $user_id );
				if ( 'chapter' === $context ) {
					$is_marked_read = self::is_chapter_marked_read( $chapter_id, $user_id );
				}
				$is_subscribed = self::is_subscribed_to_story( $story_id, $user_id );
			}
		} elseif ( $allow_anonymous_likes && 'author' !== $context ) {
			// Check if anonymous user (by IP) has already liked this content today
			$is_liked = self::has_anonymous_user_liked( $item_id, $context );
		}

		// Get like count (includes both logged-in and anonymous likes)
		$like_count = ( 'author' !== $context ) ? self::get_like_count( $item_id, $context ) : 0;

		// Build output
		$output = '<div class="fanfic-content-actions fanfic-' . esc_attr( $context ) . '-actions">';

		// === AUTHOR/PROFILE CONTEXT ===
		if ( 'author' === $context ) {
			// Don't show follow button to the author themselves
			if ( ! $is_logged_in || $user_id !== $author_id ) {
				$follow_class = $is_following ? 'following' : 'not-following';
				$follow_text = $is_following
					? esc_html__( 'Following', 'fanfiction-manager' )
					: esc_html__( 'Follow', 'fanfiction-manager' );
				$follow_disabled = ! $is_logged_in ? 'disabled' : '';

				$output .= sprintf(
					'<button class="fanfic-action-btn fanfic-follow-btn %s %s" data-author-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s" %s>
						<span class="fanfic-icon" aria-hidden="true">%s</span>
						<span class="fanfic-text">%s</span>
						%s
					</button>',
					esc_attr( $follow_class ),
					esc_attr( $follow_disabled ),
					absint( $author_id ),
					$is_following ? 'unfollow' : 'follow',
					esc_attr( $is_following ? __( 'Unfollow author', 'fanfiction-manager' ) : __( 'Follow author', 'fanfiction-manager' ) ),
					$is_following ? 'true' : 'false',
					! $is_logged_in ? 'disabled="disabled"' : '',
					$is_following ? '&#10003;' : '&#43;',
					$follow_text,
					! $is_logged_in ? '<span class="fanfic-lock-icon" aria-hidden="true">&#128274;</span>' : ''
				);
			}

			// Share button for author profile
			$author_url = get_author_posts_url( $author_id );
			$author_name = get_the_author_meta( 'display_name', $author_id );

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-share-btn" data-url="%s" data-title="%s" aria-label="%s">
					<span class="fanfic-icon" aria-hidden="true">&#128279;</span>
					<span class="fanfic-text">%s</span>
				</button>',
				esc_url( $author_url ),
				esc_attr( $author_name ),
				esc_attr__( 'Share author profile', 'fanfiction-manager' ),
				esc_html__( 'Share', 'fanfiction-manager' )
			);

			$output .= '</div>';
			return $output;
		}

		// === PRIMARY ACTIONS GROUP ===

		// Edit button (shown if user has permission)
		if ( 'chapter' === $context && current_user_can( 'edit_fanfiction_chapter', $chapter_id ) ) {
			$edit_url = fanfic_get_edit_chapter_url( $chapter_id, $story_id );
			if ( ! empty( $edit_url ) ) {
				$output .= sprintf(
					'<a href="%s" class="fanfic-action-btn fanfic-edit-btn" aria-label="%s">
						<span class="fanfic-icon" aria-hidden="true">&#9998;</span>
						<span class="fanfic-text">%s</span>
					</a>',
					esc_url( $edit_url ),
					esc_attr__( 'Edit this chapter', 'fanfiction-manager' ),
					esc_html__( 'Edit Chapter', 'fanfiction-manager' )
				);
			}
		} elseif ( 'story' === $context && current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			$edit_url = get_edit_post_link( $story_id );
			if ( ! empty( $edit_url ) ) {
				$output .= sprintf(
					'<a href="%s" class="fanfic-action-btn fanfic-edit-btn" aria-label="%s">
						<span class="fanfic-icon" aria-hidden="true">&#9998;</span>
						<span class="fanfic-text">%s</span>
					</a>',
					esc_url( $edit_url ),
					esc_attr__( 'Edit this story', 'fanfiction-manager' ),
					esc_html__( 'Edit Story', 'fanfiction-manager' )
				);
			}
		}

		// Bookmark button (bookmarks the story)
		$bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
		$bookmark_text = $is_bookmarked
			? esc_html__( 'Bookmarked', 'fanfiction-manager' )
			: esc_html__( 'Bookmark', 'fanfiction-manager' );
		$bookmark_disabled = ! $is_logged_in ? 'disabled' : '';

		$output .= sprintf(
			'<button class="fanfic-action-btn fanfic-bookmark-btn %s %s" data-story-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s" %s>
				<span class="fanfic-icon" aria-hidden="true">%s</span>
				<span class="fanfic-text">%s</span>
				%s
			</button>',
			esc_attr( $bookmark_class ),
			esc_attr( $bookmark_disabled ),
			absint( $story_id ),
			$is_bookmarked ? 'unbookmark' : 'bookmark',
			esc_attr( $is_bookmarked ? __( 'Remove bookmark', 'fanfiction-manager' ) : __( 'Bookmark story', 'fanfiction-manager' ) ),
			$is_bookmarked ? 'true' : 'false',
			! $is_logged_in ? 'disabled="disabled"' : '',
			$is_bookmarked ? '&#9733;' : '&#9734;',
			$bookmark_text,
			! $is_logged_in ? '<span class="fanfic-lock-icon" aria-hidden="true">&#128274;</span>' : ''
		);

		// Like button (with counter)
		if ( $enable_likes ) {
			$like_class = $is_liked ? 'liked' : 'not-liked';
			$like_text = $is_liked
				? esc_html__( 'Liked', 'fanfiction-manager' )
				: esc_html__( 'Like', 'fanfiction-manager' );
			// Disable only if user is not logged in AND anonymous likes are not allowed
			$like_disabled = ( ! $is_logged_in && ! $allow_anonymous_likes ) ? 'disabled' : '';
			$like_data_anonymous = $allow_anonymous_likes ? ' data-allow-anonymous="1"' : '';

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-like-btn %s %s" data-item-id="%d" data-item-type="%s" data-action="%s"%s aria-label="%s" aria-pressed="%s" %s>
					<span class="fanfic-icon" aria-hidden="true">%s</span>
					<span class="fanfic-text">%s <span class="fanfic-like-count">(%d)</span></span>
					%s
				</button>',
				esc_attr( $like_class ),
				esc_attr( $like_disabled ),
				absint( $item_id ),
				esc_attr( $context ),
				$is_liked ? 'unlike' : 'like',
				$like_data_anonymous,
				esc_attr( $is_liked ? __( 'Unlike', 'fanfiction-manager' ) : __( 'Like this', 'fanfiction-manager' ) ),
				$is_liked ? 'true' : 'false',
				( ! $is_logged_in && ! $allow_anonymous_likes ) ? 'disabled="disabled"' : '',
				$is_liked ? '&#10084;' : '&#129293;',
				$like_text,
				absint( $like_count ),
				( ! $is_logged_in && ! $allow_anonymous_likes ) ? '<span class="fanfic-lock-icon" aria-hidden="true">&#128274;</span>' : ''
			);
		}

		// Read List button (story view only)
		if ( 'story' === $context ) {
			$read_list_class = $is_in_read_list ? 'in-read-list' : 'not-in-read-list';
			$read_list_text = $is_in_read_list
				? esc_html__( 'In Read List', 'fanfiction-manager' )
				: esc_html__( 'Read List', 'fanfiction-manager' );
			$read_list_disabled = ! $is_logged_in ? 'disabled' : '';

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-read-list-btn %s %s" data-story-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s" %s>
					<span class="fanfic-icon" aria-hidden="true">%s</span>
					<span class="fanfic-text">%s</span>
					%s
				</button>',
				esc_attr( $read_list_class ),
				esc_attr( $read_list_disabled ),
				absint( $story_id ),
				$is_in_read_list ? 'remove' : 'add',
				esc_attr( $is_in_read_list ? __( 'Remove from read list', 'fanfiction-manager' ) : __( 'Add to read list', 'fanfiction-manager' ) ),
				$is_in_read_list ? 'true' : 'false',
				! $is_logged_in ? 'disabled="disabled"' : '',
				$is_in_read_list ? '&#128218;' : '&#128214;',
				$read_list_text,
				! $is_logged_in ? '<span class="fanfic-lock-icon" aria-hidden="true">&#128274;</span>' : ''
			);
		}

		// Mark as Read button (chapter view only)
		if ( 'chapter' === $context ) {
			$mark_read_class = $is_marked_read ? 'marked-read' : 'not-marked-read';
			$mark_read_text = $is_marked_read
				? esc_html__( 'Marked', 'fanfiction-manager' )
				: esc_html__( 'Mark as Read', 'fanfiction-manager' );
			$mark_read_disabled = ! $is_logged_in ? 'disabled' : '';

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-mark-read-btn %s %s" data-chapter-id="%d" data-story-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s" %s>
					<span class="fanfic-icon" aria-hidden="true">%s</span>
					<span class="fanfic-text">%s</span>
					%s
				</button>',
				esc_attr( $mark_read_class ),
				esc_attr( $mark_read_disabled ),
				absint( $chapter_id ),
				absint( $story_id ),
				$is_marked_read ? 'unmark' : 'mark',
				esc_attr( $is_marked_read ? __( 'Unmark as read', 'fanfiction-manager' ) : __( 'Mark as read', 'fanfiction-manager' ) ),
				$is_marked_read ? 'true' : 'false',
				! $is_logged_in ? 'disabled="disabled"' : '',
				$is_marked_read ? '&#9989;' : '&#9744;',
				$mark_read_text,
				! $is_logged_in ? '<span class="fanfic-lock-icon" aria-hidden="true">&#128274;</span>' : ''
			);
		}

		// Separator
		$output .= '<span class="fanfic-actions-separator"></span>';

		// === SECONDARY ACTIONS GROUP ===

		// Subscribe button (available to all users)
		if ( $enable_subscribe ) {
			$subscribe_class = $is_subscribed ? 'subscribed' : 'not-subscribed';
			$subscribe_text = $is_subscribed
				? esc_html__( 'Subscribed', 'fanfiction-manager' )
				: esc_html__( 'Subscribe', 'fanfiction-manager' );

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-subscribe-btn %s" data-story-id="%d" data-action="%s" aria-label="%s">
					<span class="fanfic-icon" aria-hidden="true">&#128276;</span>
					<span class="fanfic-text">%s</span>
				</button>',
				esc_attr( $subscribe_class ),
				absint( $story_id ),
				$is_subscribed ? 'unsubscribe' : 'subscribe',
				esc_attr( $is_subscribed ? __( 'Unsubscribe from updates', 'fanfiction-manager' ) : __( 'Subscribe to updates', 'fanfiction-manager' ) ),
				$subscribe_text
			);
		}

		// Share button (available to all users)
		$share_url = get_permalink( $item_id );
		$share_title = get_the_title( $item_id );

		$output .= sprintf(
			'<button class="fanfic-action-btn fanfic-share-btn" data-url="%s" data-title="%s" aria-label="%s">
				<span class="fanfic-icon" aria-hidden="true">&#128279;</span>
				<span class="fanfic-text">%s</span>
			</button>',
			esc_url( $share_url ),
			esc_attr( $share_title ),
			esc_attr__( 'Share content', 'fanfiction-manager' ),
			esc_html__( 'Share', 'fanfiction-manager' )
		);

		// Report button (requires login OR anonymous reporting with reCAPTCHA)
		if ( $enable_report ) {
			// Disable only if user is not logged in AND anonymous reports are not allowed
			$report_disabled = ( ! $is_logged_in && ! $allow_anonymous_reports ) ? 'disabled' : '';
			$report_data_anonymous = $allow_anonymous_reports ? ' data-allow-anonymous="1"' : '';

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-report-btn %s" data-item-id="%d" data-item-type="%s"%s aria-label="%s" %s>
					<span class="fanfic-icon" aria-hidden="true">&#9888;</span>
					<span class="fanfic-text">%s</span>
					%s
				</button>',
				esc_attr( $report_disabled ),
				absint( $item_id ),
				esc_attr( $context ),
				$report_data_anonymous,
				esc_attr__( 'Report this content', 'fanfiction-manager' ),
				( ! $is_logged_in && ! $allow_anonymous_reports ) ? 'disabled="disabled"' : '',
				esc_html__( 'Report', 'fanfiction-manager' ),
				( ! $is_logged_in && ! $allow_anonymous_reports ) ? '<span class="fanfic-lock-icon" aria-hidden="true">&#128274;</span>' : ''
			);
		}

		$output .= '</div>';

		// Add report modal if report is enabled
		if ( $enable_report ) {
			$output .= self::get_enhanced_report_modal( $item_id, $context, $story_id );
		}

		// Add subscribe modal
		if ( $enable_subscribe ) {
			$output .= self::get_subscribe_modal( $story_id );
		}

		return $output;
	}

	/**
	 * Story actions shortcode
	 *
	 * [story-actions]
	 *
	 * Displays action buttons for stories: Bookmark, Share, Report
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Action buttons HTML.
	 */
	public static function story_actions( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'show_bookmark' => 'yes',
				'show_share'    => 'yes',
				'show_report'   => 'yes',
			),
			'story-actions'
		);

		$is_logged_in = is_user_logged_in();
		$user_id = get_current_user_id();
		$is_bookmarked = false;

		if ( $is_logged_in ) {
			$is_bookmarked = self::is_story_bookmarked( $story_id, $user_id );
		}

		$output = '<div class="fanfic-story-actions">';

		// Bookmark button
		if ( 'yes' === $atts['show_bookmark'] ) {
			$bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
			$bookmark_text = $is_bookmarked
				? esc_html__( 'Bookmarked', 'fanfiction-manager' )
				: esc_html__( 'Bookmark', 'fanfiction-manager' );

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-bookmark-btn %s" data-story-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s">
					<span class="fanfic-icon" aria-hidden="true">%s</span>
					<span class="fanfic-text">%s</span>
				</button>',
				esc_attr( $bookmark_class ),
				absint( $story_id ),
				$is_bookmarked ? 'unbookmark' : 'bookmark',
				esc_attr( $is_bookmarked ? __( 'Remove bookmark', 'fanfiction-manager' ) : __( 'Bookmark story', 'fanfiction-manager' ) ),
				$is_bookmarked ? 'true' : 'false',
				$is_bookmarked ? '&#9733;' : '&#9734;',
				$bookmark_text
			);
		}

		// Share button
		if ( 'yes' === $atts['show_share'] ) {
			$share_url = get_permalink( $story_id );
			$share_title = get_the_title( $story_id );

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-share-btn" data-url="%s" data-title="%s" aria-label="%s">
					<span class="fanfic-icon" aria-hidden="true">&#128279;</span>
					<span class="fanfic-text">%s</span>
				</button>',
				esc_url( $share_url ),
				esc_attr( $share_title ),
				esc_attr__( 'Share content', 'fanfiction-manager' ),
				esc_html__( 'Share', 'fanfiction-manager' )
			);
		}

		// Report button
		if ( 'yes' === $atts['show_report'] ) {
			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-report-btn" data-item-id="%d" data-item-type="story" aria-label="%s">
					<span class="fanfic-icon" aria-hidden="true">&#9888;</span>
					<span class="fanfic-text">%s</span>
				</button>',
				absint( $story_id ),
				esc_attr__( 'Report this content', 'fanfiction-manager' ),
				esc_html__( 'Report', 'fanfiction-manager' )
			);
		}

		$output .= '</div>';

		// Add report modal
		if ( 'yes' === $atts['show_report'] ) {
			$output .= self::get_report_modal( $story_id, 'story' );
		}

		return $output;
	}

	/**
	 * Chapter actions shortcode
	 *
	 * [chapter-actions]
	 *
	 * Displays action buttons for chapters: Bookmark (parent story), Share, Report
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Action buttons HTML.
	 */
	public static function chapter_actions( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$story_id = get_post_field( 'post_parent', $chapter_id );

		if ( ! $story_id ) {
			return '';
		}

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'show_bookmark' => 'yes',
				'show_share'    => 'yes',
				'show_report'   => 'yes',
			),
			'chapter-actions'
		);

		$is_logged_in = is_user_logged_in();
		$user_id = get_current_user_id();
		$is_bookmarked = false;

		if ( $is_logged_in ) {
			$is_bookmarked = self::is_story_bookmarked( $story_id, $user_id );
		}

		$output = '<div class="fanfic-chapter-actions">';

		// Edit button (shown automatically if user has permission)
		if ( current_user_can( 'edit_fanfiction_chapter', $chapter_id ) ) {
			$edit_url = fanfic_get_edit_chapter_url( $chapter_id, $story_id );
			if ( ! empty( $edit_url ) ) {
				$output .= sprintf(
					'<a href="%s" class="fanfic-action-btn fanfic-edit-btn" aria-label="%s">
						<span class="fanfic-icon" aria-hidden="true">&#9998;</span>
						<span class="fanfic-text">%s</span>
					</a>',
					esc_url( $edit_url ),
					esc_attr__( 'Edit this chapter', 'fanfiction-manager' ),
					esc_html__( 'Edit Chapter', 'fanfiction-manager' )
				);
			}
		}

		// Bookmark button (bookmarks the parent story)
		if ( 'yes' === $atts['show_bookmark'] ) {
			$bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
			$bookmark_text = $is_bookmarked
				? esc_html__( 'Story Bookmarked', 'fanfiction-manager' )
				: esc_html__( 'Bookmark Story', 'fanfiction-manager' );

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-bookmark-btn %s" data-story-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s">
					<span class="fanfic-icon" aria-hidden="true">%s</span>
					<span class="fanfic-text">%s</span>
				</button>',
				esc_attr( $bookmark_class ),
				absint( $story_id ),
				$is_bookmarked ? 'unbookmark' : 'bookmark',
				esc_attr( $is_bookmarked ? __( 'Remove bookmark', 'fanfiction-manager' ) : __( 'Bookmark story', 'fanfiction-manager' ) ),
				$is_bookmarked ? 'true' : 'false',
				$is_bookmarked ? '&#9733;' : '&#9734;',
				$bookmark_text
			);
		}

		// Share button (shares the chapter)
		if ( 'yes' === $atts['show_share'] ) {
			$share_url = get_permalink( $chapter_id );
			$share_title = get_the_title( $chapter_id );

			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-share-btn" data-url="%s" data-title="%s" aria-label="%s">
					<span class="fanfic-icon" aria-hidden="true">&#128279;</span>
					<span class="fanfic-text">%s</span>
				</button>',
				esc_url( $share_url ),
				esc_attr( $share_title ),
				esc_attr__( 'Share content', 'fanfiction-manager' ),
				esc_html__( 'Share', 'fanfiction-manager' )
			);
		}

		// Report button (reports the chapter)
		if ( 'yes' === $atts['show_report'] ) {
			$output .= sprintf(
				'<button class="fanfic-action-btn fanfic-report-btn" data-item-id="%d" data-item-type="chapter" aria-label="%s">
					<span class="fanfic-icon" aria-hidden="true">&#9888;</span>
					<span class="fanfic-text">%s</span>
				</button>',
				absint( $chapter_id ),
				esc_attr__( 'Report this content', 'fanfiction-manager' ),
				esc_html__( 'Report', 'fanfiction-manager' )
			);
		}

		$output .= '</div>';

		// Add report modal
		if ( 'yes' === $atts['show_report'] ) {
			$output .= self::get_report_modal( $chapter_id, 'chapter' );
		}

		return $output;
	}

	/**
	 * Author actions shortcode
	 *
	 * [author-actions]
	 *
	 * Displays follow/unfollow button for authors
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Follow button HTML.
	 */
	public static function author_actions( $atts ) {
		global $post;

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id' => 0,
			),
			'author-actions'
		);

		// Get author ID from attribute or from current post
		$author_id = absint( $atts['author_id'] );

		if ( ! $author_id && $post ) {
			$author_id = absint( $post->post_author );
		}

		if ( ! $author_id ) {
			return '';
		}

		// Don't show follow button to the author themselves
		if ( is_user_logged_in() && get_current_user_id() === $author_id ) {
			return '';
		}

		$is_logged_in = is_user_logged_in();
		$user_id = get_current_user_id();
		$is_following = false;

		if ( $is_logged_in ) {
			$is_following = self::is_following_author( $author_id, $user_id );
		}

		$follow_class = $is_following ? 'following' : 'not-following';
		$follow_text = $is_following
			? esc_html__( 'Following', 'fanfiction-manager' )
			: esc_html__( 'Follow', 'fanfiction-manager' );

		return sprintf(
			'<div class="fanfic-author-actions">
				<button class="fanfic-action-btn fanfic-follow-btn %s" data-author-id="%d" data-action="%s" aria-label="%s" aria-pressed="%s">
					<span class="fanfic-icon" aria-hidden="true">%s</span>
					<span class="fanfic-text">%s</span>
				</button>
			</div>',
			esc_attr( $follow_class ),
			absint( $author_id ),
			$is_following ? 'unfollow' : 'follow',
			esc_attr( $is_following ? __( 'Unfollow author', 'fanfiction-manager' ) : __( 'Follow author', 'fanfiction-manager' ) ),
			$is_following ? 'true' : 'false',
			$is_following ? '&#10003;' : '&#43;',
			$follow_text
		);
	}

	/**
	 * Get report modal HTML
	 *
	 * @since 1.0.0
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type (story/chapter).
	 * @return string Modal HTML.
	 */
	private static function get_report_modal( $item_id, $item_type ) {
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );

		$output = sprintf(
			'<div class="fanfic-report-modal" id="fanfic-report-modal-%d-%s" style="display:none;">
				<div class="fanfic-modal-overlay"></div>
				<div class="fanfic-modal-content">
					<div class="fanfic-modal-header">
						<h3>%s</h3>
						<button class="fanfic-modal-close">&times;</button>
					</div>
					<div class="fanfic-modal-body">
						<form class="fanfic-report-form" data-item-id="%d" data-item-type="%s">
							<label for="report-reason-%d">%s</label>
							<textarea id="report-reason-%d" name="reason" required maxlength="1000" rows="5" placeholder="%s"></textarea>',
			absint( $item_id ),
			esc_attr( $item_type ),
			esc_html__( 'Report Content', 'fanfiction-manager' ),
			absint( $item_id ),
			esc_attr( $item_type ),
			absint( $item_id ),
			esc_html__( 'Reason for reporting:', 'fanfiction-manager' ),
			absint( $item_id ),
			esc_attr__( 'Please describe why you are reporting this content...', 'fanfiction-manager' )
		);

		// Add reCAPTCHA if configured
		if ( ! empty( $recaptcha_site_key ) ) {
			$output .= sprintf(
				'<div class="fanfic-recaptcha-container">
					<div class="g-recaptcha" data-sitekey="%s"></div>
				</div>',
				esc_attr( $recaptcha_site_key )
			);
		}

		$output .= sprintf(
			'<div class="fanfic-modal-actions">
								<button type="submit" class="fanfic-btn-primary">%s</button>
								<button type="button" class="fanfic-btn-secondary fanfic-modal-cancel">%s</button>
							</div>
							<div class="fanfic-report-message"></div>
						</form>
					</div>
				</div>
			</div>',
			esc_html__( 'Submit Report', 'fanfiction-manager' ),
			esc_html__( 'Cancel', 'fanfiction-manager' )
		);

		return $output;
	}

	/**
	 * Check if user has bookmarked a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool True if bookmarked, false otherwise.
	 */
	private static function is_story_bookmarked( $story_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND user_id = %d LIMIT 1",
			$story_id,
			$user_id
		) );

		return ! empty( $exists );
	}

	/**
	 * Check if user is following an author
	 *
	 * @since 1.0.0
	 * @param int $author_id Author ID.
	 * @param int $user_id   Follower user ID.
	 * @return bool True if following, false otherwise.
	 */
	private static function is_following_author( $author_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_follows';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE author_id = %d AND follower_id = %d LIMIT 1",
			$author_id,
			$user_id
		) );

		return ! empty( $exists );
	}

	/**
	 * AJAX handler: Bookmark story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_bookmark_story() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in to bookmark stories.', 'fanfiction-manager' ),
			) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $story_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid story ID.', 'fanfiction-manager' ),
			) );
		}

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Story not found.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Check if already bookmarked
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND user_id = %d",
			$story_id,
			$user_id
		) );

		if ( $exists ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Story is already bookmarked.', 'fanfiction-manager' ),
			) );
		}

		// Insert bookmark
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to bookmark story.', 'fanfiction-manager' ),
			) );
		}

		// Clear user's bookmarks cache
		delete_transient( 'fanfic_bookmarks_' . $user_id );

		wp_send_json_success( array(
			'message'       => esc_html__( 'Story bookmarked successfully.', 'fanfiction-manager' ),
			'is_bookmarked' => true,
		) );
	}

	/**
	 * AJAX handler: Unbookmark story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_unbookmark_story() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in.', 'fanfiction-manager' ),
			) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $story_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid story ID.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Delete bookmark
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to remove bookmark.', 'fanfiction-manager' ),
			) );
		}

		// Clear user's bookmarks cache
		delete_transient( 'fanfic_bookmarks_' . $user_id );

		wp_send_json_success( array(
			'message'       => esc_html__( 'Bookmark removed successfully.', 'fanfiction-manager' ),
			'is_bookmarked' => false,
		) );
	}

	/**
	 * AJAX handler: Follow author
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_follow_author() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in to follow authors.', 'fanfiction-manager' ),
			) );
		}

		$author_id = isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $author_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid author ID.', 'fanfiction-manager' ),
			) );
		}

		// Prevent self-following
		if ( $author_id === $user_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You cannot follow yourself.', 'fanfiction-manager' ),
			) );
		}

		// Verify author exists
		$author = get_userdata( $author_id );
		if ( ! $author ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Author not found.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Check if already following
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE author_id = %d AND follower_id = %d",
			$author_id,
			$user_id
		) );

		if ( $exists ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You are already following this author.', 'fanfiction-manager' ),
			) );
		}

		// Insert follow
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'author_id'   => $author_id,
				'follower_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to follow author.', 'fanfiction-manager' ),
			) );
		}

		// Clear user's follows cache
		delete_transient( 'fanfic_follows_' . $user_id );

		// Trigger notification for the author
		do_action( 'fanfic_author_followed', $author_id, $user_id );

		wp_send_json_success( array(
			'message'      => esc_html__( 'You are now following this author.', 'fanfiction-manager' ),
			'is_following' => true,
		) );
	}

	/**
	 * AJAX handler: Unfollow author
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_unfollow_author() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in.', 'fanfiction-manager' ),
			) );
		}

		$author_id = isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $author_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid author ID.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Delete follow
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'author_id'   => $author_id,
				'follower_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to unfollow author.', 'fanfiction-manager' ),
			) );
		}

		// Clear user's follows cache
		delete_transient( 'fanfic_follows_' . $user_id );

		wp_send_json_success( array(
			'message'      => esc_html__( 'You have unfollowed this author.', 'fanfiction-manager' ),
			'is_following' => false,
		) );
	}

	/**
	 * AJAX handler: Report content
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_report_content() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in to report content.', 'fanfiction-manager' ),
			) );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( $_POST['reason'] ) : '';
		$recaptcha_response = isset( $_POST['recaptcha_response'] ) ? sanitize_text_field( $_POST['recaptcha_response'] ) : '';

		if ( ! $item_id || ! $item_type || ! $reason ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Missing required fields.', 'fanfiction-manager' ),
			) );
		}

		// Validate item type
		if ( ! in_array( $item_type, array( 'story', 'chapter', 'comment' ), true ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid item type.', 'fanfiction-manager' ),
			) );
		}

		// Verify item exists
		if ( 'comment' === $item_type ) {
			$comment = get_comment( $item_id );
			if ( ! $comment ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'Invalid comment.', 'fanfiction-manager' ),
				) );
			}

			// Prevent users from reporting their own comments
			if ( absint( $comment->user_id ) === get_current_user_id() ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'You cannot report your own comment.', 'fanfiction-manager' ),
				) );
			}
		} else {
			$post_type = ( 'story' === $item_type ) ? 'fanfiction_story' : 'fanfiction_chapter';
			$item = get_post( $item_id );
			if ( ! $item || $post_type !== $item->post_type ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'Content not found.', 'fanfiction-manager' ),
				) );
			}
		}

		// Verify reCAPTCHA if configured
		$recaptcha_secret = get_option( 'fanfic_recaptcha_secret_key', '' );
		if ( ! empty( $recaptcha_secret ) ) {
			if ( empty( $recaptcha_response ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'Please complete the reCAPTCHA verification.', 'fanfiction-manager' ),
				) );
			}

			$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
			$response = wp_remote_post( $verify_url, array(
				'body' => array(
					'secret'   => $recaptcha_secret,
					'response' => $recaptcha_response,
				),
			) );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'reCAPTCHA verification failed.', 'fanfiction-manager' ),
				) );
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $response_body['success'] ) ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'reCAPTCHA verification failed.', 'fanfiction-manager' ),
				) );
			}
		}

		$user_id = get_current_user_id();

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_reports';

		// Check if user has already reported this item
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE reported_item_id = %d AND reported_item_type = %s AND reporter_id = %d",
			$item_id,
			$item_type,
			$user_id
		) );

		if ( $existing ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You have already reported this content.', 'fanfiction-manager' ),
			) );
		}

		// Insert report
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'reported_item_id'   => $item_id,
				'reported_item_type' => $item_type,
				'reporter_id'        => $user_id,
				'reason'             => $reason,
				'status'             => 'pending',
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to submit report.', 'fanfiction-manager' ),
			) );
		}

		// Trigger notification for moderators
		do_action( 'fanfic_content_reported', $item_id, $item_type, $user_id, $reason );

		wp_send_json_success( array(
			'message' => esc_html__( 'Report submitted successfully. Thank you for helping keep our community safe.', 'fanfiction-manager' ),
		) );
	}

	/**
	 * AJAX handler: Like content
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_like_content() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : '';
		$allow_anonymous = isset( $_POST['allow_anonymous'] ) && '1' === $_POST['allow_anonymous'];

		if ( ! $item_id || ! $item_type ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid request.', 'fanfiction-manager' ),
			) );
		}

		// Validate item type
		if ( ! in_array( $item_type, array( 'story', 'chapter' ), true ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid item type.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in or anonymous is allowed
		$is_logged_in = is_user_logged_in();
		if ( ! $is_logged_in && ! $allow_anonymous ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in to like content.', 'fanfiction-manager' ),
			) );
		}

		if ( $is_logged_in ) {
			// Logged-in user like
			$user_id = get_current_user_id();

			global $wpdb;
			$table_name = $wpdb->prefix . 'fanfic_likes';

			// Check if already liked
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE item_id = %d AND item_type = %s AND user_id = %d",
				$item_id,
				$item_type,
				$user_id
			) );

			if ( $exists ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'You have already liked this.', 'fanfiction-manager' ),
				) );
			}

			// Insert like
			$inserted = $wpdb->insert(
				$table_name,
				array(
					'item_id'   => $item_id,
					'item_type' => $item_type,
					'user_id'   => $user_id,
				),
				array( '%d', '%s', '%d' )
			);

			if ( false === $inserted ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'Failed to like content.', 'fanfiction-manager' ),
				) );
			}
		} else {
			// Anonymous user like
			$recorded = self::record_anonymous_like( $item_id, $item_type );

			if ( ! $recorded ) {
				wp_send_json_error( array(
					'message' => esc_html__( 'You have already liked this today.', 'fanfiction-manager' ),
				) );
			}
		}

		// Get updated like count
		$like_count = self::get_like_count( $item_id, $item_type );

		wp_send_json_success( array(
			'message'    => esc_html__( 'Liked successfully.', 'fanfiction-manager' ),
			'is_liked'   => true,
			'like_count' => $like_count,
		) );
	}

	/**
	 * AJAX handler: Unlike content
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_unlike_content() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Only logged-in users can unlike
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in.', 'fanfiction-manager' ),
			) );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : '';
		$user_id = get_current_user_id();

		if ( ! $item_id || ! $item_type ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid request.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_likes';

		// Delete like
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'item_id'   => $item_id,
				'item_type' => $item_type,
				'user_id'   => $user_id,
			),
			array( '%d', '%s', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to unlike content.', 'fanfiction-manager' ),
			) );
		}

		// Get updated like count
		$like_count = self::get_like_count( $item_id, $item_type );

		wp_send_json_success( array(
			'message'    => esc_html__( 'Like removed successfully.', 'fanfiction-manager' ),
			'is_liked'   => false,
			'like_count' => $like_count,
		) );
	}

	/**
	 * AJAX handler: Add story to read list
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_add_to_read_list() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in to add to read list.', 'fanfiction-manager' ),
			) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $story_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid story ID.', 'fanfiction-manager' ),
			) );
		}

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Story not found.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_read_lists';

		// Check if already in read list
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND user_id = %d",
			$story_id,
			$user_id
		) );

		if ( $exists ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Story is already in your read list.', 'fanfiction-manager' ),
			) );
		}

		// Insert to read list
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to add to read list.', 'fanfiction-manager' ),
			) );
		}

		wp_send_json_success( array(
			'message'         => esc_html__( 'Added to read list successfully.', 'fanfiction-manager' ),
			'is_in_read_list' => true,
		) );
	}

	/**
	 * AJAX handler: Remove story from read list
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_remove_from_read_list() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in.', 'fanfiction-manager' ),
			) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $story_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid story ID.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_read_lists';

		// Delete from read list
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to remove from read list.', 'fanfiction-manager' ),
			) );
		}

		wp_send_json_success( array(
			'message'         => esc_html__( 'Removed from read list successfully.', 'fanfiction-manager' ),
			'is_in_read_list' => false,
		) );
	}

	/**
	 * AJAX handler: Mark chapter as read
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_mark_as_read() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in to mark as read.', 'fanfiction-manager' ),
			) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $chapter_id || ! $story_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid request.', 'fanfiction-manager' ),
			) );
		}

		// Get chapter number
		$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
		if ( ! $chapter_number ) {
			$chapter_number = 1;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		// Check if progress record exists
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND user_id = %d",
			$story_id,
			$user_id
		) );

		if ( $exists ) {
			// Update existing progress
			$updated = $wpdb->update(
				$table_name,
				array(
					'chapter_id'     => $chapter_id,
					'chapter_number' => $chapter_number,
				),
				array(
					'story_id' => $story_id,
					'user_id'  => $user_id,
				),
				array( '%d', '%d' ),
				array( '%d', '%d' )
			);
		} else {
			// Insert new progress
			$updated = $wpdb->insert(
				$table_name,
				array(
					'story_id'       => $story_id,
					'user_id'        => $user_id,
					'chapter_id'     => $chapter_id,
					'chapter_number' => $chapter_number,
				),
				array( '%d', '%d', '%d', '%d' )
			);
		}

		if ( false === $updated ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to mark as read.', 'fanfiction-manager' ),
			) );
		}

		wp_send_json_success( array(
			'message'       => esc_html__( 'Marked as read successfully.', 'fanfiction-manager' ),
			'is_marked_read' => true,
		) );
	}

	/**
	 * AJAX handler: Unmark chapter as read
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_unmark_as_read() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'You must be logged in.', 'fanfiction-manager' ),
			) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;
		$user_id = get_current_user_id();

		if ( ! $chapter_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid chapter ID.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		// Delete progress record
		$deleted = $wpdb->delete(
			$table_name,
			array(
				'chapter_id' => $chapter_id,
				'user_id'    => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to unmark as read.', 'fanfiction-manager' ),
			) );
		}

		wp_send_json_success( array(
			'message'        => esc_html__( 'Unmarked as read successfully.', 'fanfiction-manager' ),
			'is_marked_read' => false,
		) );
	}

	/**
	 * AJAX handler: Subscribe to story updates
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_subscribe_to_story() {
		// Verify nonce
		if ( ! check_ajax_referer( 'fanfic_actions_nonce', 'nonce', false ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Security check failed.', 'fanfiction-manager' ),
			) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		if ( ! $story_id ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid story ID.', 'fanfiction-manager' ),
			) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Invalid email address.', 'fanfiction-manager' ),
			) );
		}

		// Verify story exists
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Story not found.', 'fanfiction-manager' ),
			) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fanfic_subscriptions';
		$user_id = is_user_logged_in() ? get_current_user_id() : null;

		// Check if already subscribed
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND email = %s",
			$story_id,
			$email
		) );

		if ( $exists ) {
			// Reactivate if inactive
			$wpdb->update(
				$table_name,
				array( 'is_active' => 1 ),
				array(
					'story_id' => $story_id,
					'email'    => $email,
				),
				array( '%d' ),
				array( '%d', '%s' )
			);

			wp_send_json_success( array(
				'message'       => esc_html__( 'Subscription reactivated successfully.', 'fanfiction-manager' ),
				'is_subscribed' => true,
			) );
		}

		// Generate unique token for unsubscribe
		$token = wp_generate_password( 32, false );

		// Insert subscription
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'story_id'  => $story_id,
				'user_id'   => $user_id,
				'email'     => $email,
				'token'     => $token,
				'is_active' => 1,
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to subscribe.', 'fanfiction-manager' ),
			) );
		}

		wp_send_json_success( array(
			'message'       => esc_html__( 'Subscribed successfully! You will receive email notifications for new chapters.', 'fanfiction-manager' ),
			'is_subscribed' => true,
		) );
	}

	/**
	 * Check if content is liked by user
	 *
	 * @since 1.0.0
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type (story/chapter).
	 * @param int    $user_id   User ID.
	 * @return bool True if liked, false otherwise.
	 */
	private static function is_content_liked( $item_id, $item_type, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_likes';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE item_id = %d AND item_type = %s AND user_id = %d LIMIT 1",
			$item_id,
			$item_type,
			$user_id
		) );

		return ! empty( $exists );
	}

	/**
	 * Get like count for content
	 *
	 * @since 1.0.0
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type (story/chapter).
	 * @return int Like count.
	 */
	private static function get_like_count( $item_id, $item_type ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_likes';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE item_id = %d AND item_type = %s",
			$item_id,
			$item_type
		) );

		return absint( $count );
	}

	/**
	 * Check if story is in user's read list
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool True if in read list, false otherwise.
	 */
	private static function is_in_read_list( $story_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_read_lists';

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND user_id = %d LIMIT 1",
			$story_id,
			$user_id
		) );

		return ! empty( $exists );
	}

	/**
	 * Check if chapter is marked as read by user
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $user_id    User ID.
	 * @return bool True if marked as read, false otherwise.
	 */
	private static function is_chapter_marked_read( $chapter_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		$progress = $wpdb->get_row( $wpdb->prepare(
			"SELECT chapter_id, is_completed FROM {$table_name} WHERE chapter_id = %d AND user_id = %d LIMIT 1",
			$chapter_id,
			$user_id
		) );

		return ! empty( $progress );
	}

	/**
	 * Check if user is subscribed to story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return bool True if subscribed, false otherwise.
	 */
	private static function is_subscribed_to_story( $story_id, $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_subscriptions';

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE story_id = %d AND email = %s AND is_active = 1 LIMIT 1",
			$story_id,
			$user->user_email
		) );

		return ! empty( $exists );
	}

	/**
	 * Get enhanced report modal HTML with metadata
	 *
	 * @since 1.0.0
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type (story/chapter).
	 * @param int    $story_id  Story ID.
	 * @return string Modal HTML.
	 */
	private static function get_enhanced_report_modal( $item_id, $item_type, $story_id ) {
		$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );

		// Get metadata for report
		$item_title = get_the_title( $item_id );
		$item_url = get_permalink( $item_id );
		$author_id = get_post_field( 'post_author', $story_id );

		$output = sprintf(
			'<div class="fanfic-report-modal" id="fanfic-report-modal-%d-%s" style="display:none;">
				<div class="fanfic-modal-overlay"></div>
				<div class="fanfic-modal-content">
					<div class="fanfic-modal-header">
						<h3>%s</h3>
						<button class="fanfic-modal-close">&times;</button>
					</div>
					<div class="fanfic-modal-body">
						<form class="fanfic-report-form" data-item-id="%d" data-item-type="%s" data-item-title="%s" data-item-url="%s" data-author-id="%d" data-story-id="%d">
							<label for="report-reason-%d">%s</label>
							<textarea id="report-reason-%d" name="reason" required maxlength="1000" rows="5" placeholder="%s"></textarea>',
			absint( $item_id ),
			esc_attr( $item_type ),
			esc_html__( 'Report Content', 'fanfiction-manager' ),
			absint( $item_id ),
			esc_attr( $item_type ),
			esc_attr( $item_title ),
			esc_url( $item_url ),
			absint( $author_id ),
			absint( $story_id ),
			absint( $item_id ),
			esc_html__( 'Reason for reporting:', 'fanfiction-manager' ),
			absint( $item_id ),
			esc_attr__( 'Please describe why you are reporting this content...', 'fanfiction-manager' )
		);

		// Add reCAPTCHA if configured
		if ( ! empty( $recaptcha_site_key ) ) {
			$output .= sprintf(
				'<div class="fanfic-recaptcha-container">
					<div class="g-recaptcha" data-sitekey="%s"></div>
				</div>',
				esc_attr( $recaptcha_site_key )
			);
		}

		$output .= sprintf(
			'<div class="fanfic-modal-actions">
								<button type="submit" class="fanfic-btn-primary">%s</button>
								<button type="button" class="fanfic-btn-secondary fanfic-modal-cancel">%s</button>
							</div>
							<div class="fanfic-report-message"></div>
						</form>
					</div>
				</div>
			</div>',
			esc_html__( 'Submit Report', 'fanfiction-manager' ),
			esc_html__( 'Cancel', 'fanfiction-manager' )
		);

		return $output;
	}

	/**
	 * Get subscribe modal HTML
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return string Modal HTML.
	 */
	private static function get_subscribe_modal( $story_id ) {
		$story_title = get_the_title( $story_id );

		$output = sprintf(
			'<div class="fanfic-subscribe-modal" id="fanfic-subscribe-modal-%d" style="display:none;">
				<div class="fanfic-modal-overlay"></div>
				<div class="fanfic-modal-content">
					<div class="fanfic-modal-header">
						<h3>%s</h3>
						<button class="fanfic-modal-close">&times;</button>
					</div>
					<div class="fanfic-modal-body">
						<p>%s</p>
						<form class="fanfic-subscribe-form" data-story-id="%d">
							<label for="subscribe-email-%d">%s</label>
							<input type="email" id="subscribe-email-%d" name="email" required placeholder="%s">
							<div class="fanfic-modal-actions">
								<button type="submit" class="fanfic-btn-primary">%s</button>
								<button type="button" class="fanfic-btn-secondary fanfic-modal-cancel">%s</button>
							</div>
							<div class="fanfic-subscribe-message"></div>
						</form>
					</div>
				</div>
			</div>',
			absint( $story_id ),
			esc_html__( 'Subscribe to Story Updates', 'fanfiction-manager' ),
			sprintf(
				/* translators: %s: story title */
				esc_html__( 'Get email notifications when new chapters are published for "%s".', 'fanfiction-manager' ),
				esc_html( $story_title )
			),
			absint( $story_id ),
			absint( $story_id ),
			esc_html__( 'Your email address:', 'fanfiction-manager' ),
			absint( $story_id ),
			esc_attr__( 'you@example.com', 'fanfiction-manager' ),
			esc_html__( 'Subscribe', 'fanfiction-manager' ),
			esc_html__( 'Cancel', 'fanfiction-manager' )
		);

		return $output;
	}

	/**
	 * Check if anonymous user (by IP) has liked content today
	 *
	 * @since 1.0.0
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type (story/chapter).
	 * @return bool True if liked, false otherwise.
	 */
	private static function has_anonymous_user_liked( $item_id, $item_type ) {
		$user_ip = self::get_user_ip();
		if ( empty( $user_ip ) ) {
			return false;
		}

		// Create transient key: fanfic_anon_like_{item_type}_{item_id}_{ip_hash}_{date}
		$ip_hash = md5( $user_ip );
		$date = gmdate( 'Y-m-d' );
		$transient_key = "fanfic_anon_like_{$item_type}_{$item_id}_{$ip_hash}_{$date}";

		return (bool) get_transient( $transient_key );
	}

	/**
	 * Record anonymous like in transient
	 *
	 * @since 1.0.0
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type (story/chapter).
	 * @return bool True on success, false on failure.
	 */
	public static function record_anonymous_like( $item_id, $item_type ) {
		$user_ip = self::get_user_ip();
		if ( empty( $user_ip ) ) {
			return false;
		}

		// Create transient key for rate limiting
		$ip_hash = md5( $user_ip );
		$date = gmdate( 'Y-m-d' );
		$transient_key = "fanfic_anon_like_{$item_type}_{$item_id}_{$ip_hash}_{$date}";

		// Check if already liked today
		if ( get_transient( $transient_key ) ) {
			return false;
		}

		// Set transient (expires in 24 hours)
		set_transient( $transient_key, 1, DAY_IN_SECONDS );

		// Add to pending sync queue
		$queue_key = 'fanfic_anon_likes_queue';
		$queue = get_option( $queue_key, array() );

		$queue[] = array(
			'item_id'   => $item_id,
			'item_type' => $item_type,
			'ip'        => $user_ip,
			'timestamp' => current_time( 'mysql' ),
		);

		update_option( $queue_key, $queue );

		return true;
	}

	/**
	 * Get user IP address
	 *
	 * @since 1.0.0
	 * @return string User IP address.
	 */
	private static function get_user_ip() {
		$ip = '';

		// Check for shared internet/ISP IP
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Check for IP passed from proxy
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Validate IP
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '';
		}

		return $ip;
	}

	/**
	 * Sync anonymous likes from transients to database (called by cron)
	 *
	 * @since 1.0.0
	 * @return int Number of likes synced.
	 */
	public static function sync_anonymous_likes_to_database() {
		global $wpdb;

		$queue_key = 'fanfic_anon_likes_queue';
		$queue = get_option( $queue_key, array() );

		if ( empty( $queue ) ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'fanfic_likes';
		$synced_count = 0;

		foreach ( $queue as $like ) {
			// Check if this IP+item combination already exists in database
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE item_id = %d AND item_type = %s AND user_id = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
				$like['item_id'],
				$like['item_type']
			) );

			if ( ! $exists ) {
				// Insert as anonymous like (user_id = 0)
				$inserted = $wpdb->insert(
					$table_name,
					array(
						'item_id'    => $like['item_id'],
						'item_type'  => $like['item_type'],
						'user_id'    => 0, // 0 indicates anonymous like
						'created_at' => $like['timestamp'],
					),
					array( '%d', '%s', '%d', '%s' )
				);

				if ( $inserted ) {
					$synced_count++;
				}
			}
		}

		// Clear the queue
		delete_option( $queue_key );

		return $synced_count;
	}
}
