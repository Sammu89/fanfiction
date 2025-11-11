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
		add_shortcode( 'story-actions', array( __CLASS__, 'story_actions' ) );
		add_shortcode( 'chapter-actions', array( __CLASS__, 'chapter_actions' ) );
		add_shortcode( 'author-actions', array( __CLASS__, 'author_actions' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_bookmark_story', array( __CLASS__, 'ajax_bookmark_story' ) );
		add_action( 'wp_ajax_fanfic_unbookmark_story', array( __CLASS__, 'ajax_unbookmark_story' ) );
		add_action( 'wp_ajax_fanfic_follow_author', array( __CLASS__, 'ajax_follow_author' ) );
		add_action( 'wp_ajax_fanfic_unfollow_author', array( __CLASS__, 'ajax_unfollow_author' ) );
		add_action( 'wp_ajax_fanfic_report_content', array( __CLASS__, 'ajax_report_content' ) );

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
}
