<?php
/**
 * User Dashboard Shortcodes Class
 *
 * Handles all user dashboard-related shortcodes.
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
 * Class Fanfic_Shortcodes_User
 *
 * User dashboard and personal library shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_User {

	/**
	 * Initialize and register AJAX handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Register AJAX handlers for notifications
		add_action( 'wp_ajax_fanfic_mark_notification_read', array( __CLASS__, 'ajax_mark_notification_read' ) );
		add_action( 'wp_ajax_fanfic_delete_notification', array( __CLASS__, 'ajax_delete_notification' ) );
		add_action( 'wp_ajax_fanfic_mark_all_notifications_read', array( __CLASS__, 'ajax_mark_all_read' ) );
		add_action( 'wp_ajax_fanfic_get_unread_count', array( __CLASS__, 'ajax_get_unread_count' ) );
	}

	/**
	 * Register user dashboard shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Note: [user-dashboard] shortcode removed - dashboard now uses template-dashboard.php
		add_shortcode( 'user-favorites', array( __CLASS__, 'user_favorites' ) );
		add_shortcode( 'user-favorites-count', array( __CLASS__, 'user_favorites_count' ) );
		add_shortcode( 'user-followed-authors', array( __CLASS__, 'user_followed_authors' ) );
		add_shortcode( 'user-reading-history', array( __CLASS__, 'user_reading_history' ) );
		add_shortcode( 'user-notifications', array( __CLASS__, 'user_notifications' ) );
		add_shortcode( 'user-story-list', array( __CLASS__, 'user_story_list' ) );
		add_shortcode( 'user-notification-settings', array( __CLASS__, 'user_notification_settings' ) );
		add_shortcode( 'notification-bell-icon', array( __CLASS__, 'notification_bell_icon' ) );
		add_shortcode( 'user-ban', array( __CLASS__, 'user_ban' ) );
		add_shortcode( 'user-moderator', array( __CLASS__, 'user_moderator' ) );
		add_shortcode( 'user-demoderator', array( __CLASS__, 'user_demoderator' ) );
	}

	/**
	 * User favorites (bookmarked stories) shortcode
	 *
	 * [user-favorites]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string User favorites list HTML.
	 */
	public static function user_favorites( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return self::login_prompt( __( 'Please log in to view your bookmarked stories.', 'fanfiction-manager' ) );
		}

		$atts = shortcode_atts( array(
			'per_page' => 15,
		), $atts, 'user-favorites' );

		$user_id = get_current_user_id();
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		// Try to get from transient cache first
		$cache_key = 'fanfic_bookmarks_' . $user_id . '_page_' . $paged;
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		global $wpdb;
		$interactions_table = $wpdb->prefix . 'fanfic_interactions';
		$offset = ( $paged - 1 ) * absint( $atts['per_page'] );

		// Get total count for pagination (story bookmarks only)
		$total_bookmarks = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$interactions_table} i
			INNER JOIN {$wpdb->posts} p ON i.chapter_id = p.ID
			WHERE i.user_id = %d AND i.interaction_type = 'bookmark'
			AND p.post_type = 'fanfiction_story' AND p.post_status = 'publish'",
			$user_id
		) );

		if ( ! $total_bookmarks ) {
			return '<div class="fanfic-user-favorites fanfic-empty-state"><p>' . esc_html__( 'No bookmarks yet. Start exploring stories!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Get bookmarked stories
		$bookmarks = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.chapter_id AS story_id, i.created_at FROM {$interactions_table} i
			INNER JOIN {$wpdb->posts} p ON i.chapter_id = p.ID
			WHERE i.user_id = %d AND i.interaction_type = 'bookmark'
			AND p.post_type = 'fanfiction_story' AND p.post_status = 'publish'
			ORDER BY i.created_at DESC
			LIMIT %d OFFSET %d",
			$user_id,
			absint( $atts['per_page'] ),
			$offset
		) );

		if ( ! $bookmarks ) {
			return '<div class="fanfic-user-favorites fanfic-empty-state"><p>' . esc_html__( 'No bookmarks yet. Start exploring stories!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="fanfic-user-favorites" role="region" aria-label="' . esc_attr__( 'My bookmarked stories', 'fanfiction-manager' ) . '">';
		$output .= '<h2>' . esc_html__( 'My Bookmarked Stories', 'fanfiction-manager' ) . '</h2>';
		$output .= '<ul class="fanfic-favorites-list">';

		foreach ( $bookmarks as $bookmark ) {
			$story = get_post( $bookmark->story_id );

			if ( ! $story || 'fanfiction_story' !== $story->post_type || 'publish' !== $story->post_status ) {
				continue;
			}

			$author_id = $story->post_author;
			$author_name = get_the_author_meta( 'display_name', $author_id );
			$author_url = fanfic_get_user_profile_url( $author_id );
			$story_url = get_permalink( $story->ID );
			$bookmarked_date = mysql2date( get_option( 'date_format' ), $bookmark->created_at );

			$output .= '<li class="fanfic-favorite-item" data-story-id="' . esc_attr( $story->ID ) . '">';
			$output .= '<div class="fanfic-favorite-info">';
			$output .= '<h3><a href="' . esc_url( $story_url ) . '">' . esc_html( $story->post_title ) . '</a></h3>';
			$output .= '<p class="fanfic-favorite-meta">';
			$output .= sprintf(
				/* translators: 1: author name with link, 2: bookmarked date */
				esc_html__( 'by %1$s &middot; Bookmarked on %2$s', 'fanfiction-manager' ),
				'<a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a>',
				esc_html( $bookmarked_date )
			);
			$output .= '</p>';
			$output .= '</div>';
			$output .= '<button class="fanfic-remove-bookmark" data-story-id="' . esc_attr( $story->ID ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'fanfic_remove_bookmark_' . $story->ID ) ) . '" aria-label="' . esc_attr__( 'Remove from bookmarks', 'fanfiction-manager' ) . '">';
			$output .= esc_html__( 'Remove', 'fanfiction-manager' );
			$output .= '</button>';
			$output .= '</li>';
		}

		$output .= '</ul>';

		// Pagination
		if ( $total_bookmarks > $atts['per_page'] ) {
			$total_pages = ceil( $total_bookmarks / $atts['per_page'] );
			$output .= self::pagination( $paged, $total_pages );
		}

		$output .= '</div>';

		// Cache the output for 5 minutes
		set_transient( $cache_key, $output, 5 * MINUTE_IN_SECONDS );

		return $output;
	}

	/**
	 * User favorites count shortcode
	 *
	 * Displays the count of bookmarked stories for the current user.
	 * [user-favorites-count]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Bookmarked stories count.
	 */
	public static function user_favorites_count( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return '<span class="user-favorites-count">0</span>';
		}

		$user_id = get_current_user_id();

		// Use cached bookmarks count (5 minute cache)
		$cache_key = 'fanfic_bookmarks_count_' . $user_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return '<span class="user-favorites-count">' . Fanfic_Shortcodes::format_number( $cached ) . '</span>';
		}

		global $wpdb;
		$interactions_table = $wpdb->prefix . 'fanfic_interactions';

		// Get total count
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$interactions_table} WHERE user_id = %d AND interaction_type = 'bookmark'",
			$user_id
		) );

		$count = absint( $count );

		// Cache for 5 minutes
		set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );

		return '<span class="user-favorites-count">' . Fanfic_Shortcodes::format_number( $count ) . '</span>';
	}

	/**
	 * User followed authors shortcode
	 *
	 * [user-followed-authors]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Followed authors list HTML.
	 */
	public static function user_followed_authors( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return self::login_prompt( __( 'Please log in to view your followed authors.', 'fanfiction-manager' ) );
		}

		$atts = shortcode_atts( array(
			'per_page' => 20,
		), $atts, 'user-followed-authors' );

		$user_id = get_current_user_id();
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		global $wpdb;
		$follows_table = $wpdb->prefix . 'fanfic_follows';
		$offset = ( $paged - 1 ) * absint( $atts['per_page'] );

		// Get total count
		$total_follows = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$follows_table} WHERE follower_id = %d",
			$user_id
		) );

		if ( ! $total_follows ) {
			return '<div class="fanfic-user-follows fanfic-empty-state"><p>' . esc_html__( 'Not following anyone yet. Discover authors to follow!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Get followed authors
		$follows = $wpdb->get_results( $wpdb->prepare(
			"SELECT author_id, created_at FROM {$follows_table}
			WHERE follower_id = %d
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$user_id,
			absint( $atts['per_page'] ),
			$offset
		) );

		if ( ! $follows ) {
			return '<div class="fanfic-user-follows fanfic-empty-state"><p>' . esc_html__( 'Not following anyone yet. Discover authors to follow!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="fanfic-user-follows" role="region" aria-label="' . esc_attr__( 'Authors I follow', 'fanfiction-manager' ) . '">';
		$output .= '<h2>' . esc_html__( 'Authors I Follow', 'fanfiction-manager' ) . '</h2>';
		$output .= '<ul class="fanfic-follows-list">';

		foreach ( $follows as $follow ) {
			$author_id = $follow->author_id;
			$author_name = get_the_author_meta( 'display_name', $author_id );
			$author_url = fanfic_get_user_profile_url( $author_id );
			$followed_date = mysql2date( get_option( 'date_format' ), $follow->created_at );

			// Get author's latest story
			$latest_story = get_posts( array(
				'post_type'      => 'fanfiction_story',
				'posts_per_page' => 1,
				'author'         => $author_id,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );

			$output .= '<li class="fanfic-follow-item" data-author-id="' . esc_attr( $author_id ) . '">';
			$output .= '<div class="fanfic-follow-info">';
			$output .= '<h3><a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a></h3>';

			if ( ! empty( $latest_story ) ) {
				$story = $latest_story[0];
				$output .= '<p class="fanfic-latest-story">';
				$output .= sprintf(
					/* translators: %s: story title with link */
					esc_html__( 'Latest: %s', 'fanfiction-manager' ),
					'<a href="' . esc_url( get_permalink( $story->ID ) ) . '">' . esc_html( $story->post_title ) . '</a>'
				);
				$output .= '</p>';
			}

			$output .= '<p class="fanfic-follow-meta">';
			$output .= sprintf(
				/* translators: %s: followed date */
				esc_html__( 'Following since %s', 'fanfiction-manager' ),
				esc_html( $followed_date )
			);
			$output .= '</p>';
			$output .= '</div>';
			$output .= '<button class="fanfic-unfollow-author" data-author-id="' . esc_attr( $author_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'fanfic_unfollow_author_' . $author_id ) ) . '">';
			$output .= esc_html__( 'Unfollow', 'fanfiction-manager' );
			$output .= '</button>';
			$output .= '</li>';
		}

		$output .= '</ul>';

		// Pagination
		if ( $total_follows > $atts['per_page'] ) {
			$total_pages = ceil( $total_follows / $atts['per_page'] );
			$output .= self::pagination( $paged, $total_pages );
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * User reading history shortcode
	 *
	 * [user-reading-history]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Reading history list HTML.
	 */
	public static function user_reading_history( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return self::login_prompt( __( 'Please log in to view your reading history.', 'fanfiction-manager' ) );
		}

		$atts = shortcode_atts( array(
			'limit' => 20,
		), $atts, 'user-reading-history' );

		$user_id = get_current_user_id();

		// Get reading history from user meta (session-based tracking)
		$reading_history = get_user_meta( $user_id, 'fanfic_reading_history', true );

		if ( ! $reading_history || ! is_array( $reading_history ) ) {
			return '<div class="fanfic-reading-history fanfic-empty-state"><p>' . esc_html__( 'No reading history yet. Start reading stories!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Sort by timestamp (most recent first)
		usort( $reading_history, function( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		} );

		// Limit results
		$reading_history = array_slice( $reading_history, 0, absint( $atts['limit'] ) );

		// Build output
		$output = '<div class="fanfic-reading-history">';
		$output .= '<h2>' . esc_html__( 'Recently Read', 'fanfiction-manager' ) . '</h2>';
		$output .= '<ul class="fanfic-history-list">';

		foreach ( $reading_history as $item ) {
			$chapter_id = isset( $item['chapter_id'] ) ? absint( $item['chapter_id'] ) : 0;
			$timestamp = isset( $item['timestamp'] ) ? absint( $item['timestamp'] ) : 0;

			if ( ! $chapter_id || ! $timestamp ) {
				continue;
			}

			$chapter = get_post( $chapter_id );

			if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type || 'publish' !== $chapter->post_status ) {
				continue;
			}

			$story_id = $chapter->post_parent;
			$story = get_post( $story_id );

			if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
				continue;
			}

			$chapter_url = get_permalink( $chapter->ID );
			$story_url = get_permalink( $story->ID );
			$time_ago = human_time_diff( $timestamp, current_time( 'timestamp' ) );

			$output .= '<li class="fanfic-history-item">';
			$output .= '<div class="fanfic-history-info">';
			$output .= '<h3><a href="' . esc_url( $story_url ) . '">' . esc_html( $story->post_title ) . '</a></h3>';
			$output .= '<p class="fanfic-history-chapter">';
			$output .= '<a href="' . esc_url( $chapter_url ) . '">' . esc_html( $chapter->post_title ) . '</a>';
			$output .= '</p>';
			$output .= '<p class="fanfic-history-meta">';
			$output .= sprintf(
				/* translators: %s: time ago */
				esc_html__( 'Read %s ago', 'fanfiction-manager' ),
				esc_html( $time_ago )
			);
			$output .= '</p>';
			$output .= '</div>';
			$output .= '</li>';
		}

		$output .= '</ul>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * User notifications shortcode
	 *
	 * [user-notifications]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Notifications list HTML.
	 */
	public static function user_notifications( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return self::login_prompt( __( 'Please log in to view your notifications.', 'fanfiction-manager' ) );
		}

		$atts = shortcode_atts( array(
			'limit' => 20,
			'page'  => 1,
		), $atts, 'user-notifications' );

		$user_id = get_current_user_id();
		$limit   = absint( $atts['limit'] );
		$page    = absint( $atts['page'] );
		$offset  = ( $page - 1 ) * $limit;

		// Use Phase 9 methods to get notifications and counts
		$notifications  = Fanfic_Notifications::get_user_notifications( $user_id, false, $limit, $offset );
		$unread_count   = Fanfic_Notifications::get_unread_count( $user_id );
		$total_count    = Fanfic_Notifications::get_total_count( $user_id );

		// Generate nonce for actions
		$nonce = wp_create_nonce( 'fanfic_notification_action' );

		// Build output
		$output = '<div class="fanfic-user-notifications" role="region" aria-label="' . esc_attr__( 'Notifications', 'fanfiction-manager' ) . '">';

		// Header with unread count and "Mark All as Read" button
		$output .= '<div class="fanfic-notifications-header">';
		$output .= '<h2>' . esc_html__( 'Notifications', 'fanfiction-manager' );

		if ( $unread_count > 0 ) {
			$output .= ' <span class="fanfic-unread-badge">' . esc_html( $unread_count ) . '</span>';
		}

		$output .= '</h2>';

		if ( $unread_count > 0 ) {
			$output .= '<button type="button" class="fanfic-mark-all-read" data-nonce="' . esc_attr( $nonce ) . '">';
			$output .= esc_html__( 'Mark All as Read', 'fanfiction-manager' );
			$output .= '</button>';
		}

		$output .= '</div>'; // .fanfic-notifications-header

		// Check if there are any notifications
		if ( empty( $notifications ) ) {
			$output .= '<div class="fanfic-empty-state">';
			$output .= '<p>' . esc_html__( 'No notifications yet.', 'fanfiction-manager' ) . '</p>';
			$output .= '</div>';
			$output .= '</div>'; // .fanfic-user-notifications
			return $output;
		}

		// Notifications list
		$output .= '<ul class="fanfic-notifications-list">';

		foreach ( $notifications as $notification ) {
			$created_timestamp = strtotime( $notification->created_at );
			$is_unread = ! $notification->is_read; // Phase 9 uses is_read field
			$time_ago = human_time_diff( $created_timestamp, current_time( 'timestamp' ) );
			$date_formatted = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $notification->created_at );

			// Build CSS classes
			$classes = array( 'fanfic-notification-item' );
			if ( $is_unread ) {
				$classes[] = 'fanfic-notification-unread';
			} else {
				$classes[] = 'fanfic-notification-read';
			}

			$output .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '" data-notification-id="' . esc_attr( $notification->id ) . '">';

			// Unread indicator dot
			if ( $is_unread ) {
				$output .= '<span class="fanfic-notification-dot" aria-label="' . esc_attr__( 'Unread', 'fanfiction-manager' ) . '"></span>';
			}

			// Notification content
			$output .= '<div class="fanfic-notification-content">';

			// Message text (with optional link)
			if ( ! empty( $notification->link ) ) {
				$output .= '<a href="' . esc_url( $notification->link ) . '" class="fanfic-notification-link">';
				$output .= '<p class="fanfic-notification-text">' . wp_kses_post( $notification->message ) . '</p>';
				$output .= '</a>';
			} else {
				$output .= '<p class="fanfic-notification-text">' . wp_kses_post( $notification->message ) . '</p>';
			}

			// Meta information (date and actions)
			$output .= '<div class="fanfic-notification-meta">';
			$output .= '<span class="fanfic-notification-date" title="' . esc_attr( $date_formatted ) . '">';
			$output .= sprintf(
				/* translators: %s: time ago */
				esc_html__( '%s ago', 'fanfiction-manager' ),
				esc_html( $time_ago )
			);
			$output .= '</span>';

			// Action buttons
			$output .= '<span class="fanfic-notification-actions">';

			if ( $is_unread ) {
				$output .= '<button type="button" class="fanfic-notification-mark-read" ';
				$output .= 'data-notification-id="' . esc_attr( $notification->id ) . '" ';
				$output .= 'data-nonce="' . esc_attr( $nonce ) . '" ';
				$output .= 'aria-label="' . esc_attr__( 'Mark as read', 'fanfiction-manager' ) . '" ';
				$output .= 'title="' . esc_attr__( 'Mark as read', 'fanfiction-manager' ) . '">';
				$output .= esc_html__( 'Mark as read', 'fanfiction-manager' );
				$output .= '</button>';
			}

			$output .= '<button type="button" class="fanfic-notification-delete" ';
			$output .= 'data-notification-id="' . esc_attr( $notification->id ) . '" ';
			$output .= 'data-nonce="' . esc_attr( $nonce ) . '" ';
			$output .= 'aria-label="' . esc_attr__( 'Delete', 'fanfiction-manager' ) . '" ';
			$output .= 'title="' . esc_attr__( 'Delete notification', 'fanfiction-manager' ) . '">';
			$output .= esc_html__( 'Delete', 'fanfiction-manager' );
			$output .= '</button>';

			$output .= '</span>'; // .fanfic-notification-actions
			$output .= '</div>'; // .fanfic-notification-meta
			$output .= '</div>'; // .fanfic-notification-content
			$output .= '</li>';
		}

		$output .= '</ul>'; // .fanfic-notifications-list

		// Pagination (if needed)
		if ( $total_count > $limit ) {
			$total_pages = ceil( $total_count / $limit );

			if ( $total_pages > 1 ) {
				$output .= '<div class="fanfic-notifications-pagination">';

				for ( $i = 1; $i <= $total_pages; $i++ ) {
					$class = ( $i === $page ) ? 'current' : '';
					$output .= '<a href="' . esc_url( add_query_arg( 'notification_page', $i ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $i ) . '</a> ';
				}

				$output .= '</div>';
			}
		}

		$output .= '</div>'; // .fanfic-user-notifications

		return $output;
	}

	/**
	 * User story list shortcode (for author dashboard)
	 *
	 * [user-story-list]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string User's stories list HTML.
	 */
	public static function user_story_list( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return self::login_prompt( __( 'Please log in to view your stories.', 'fanfiction-manager' ) );
		}

		$atts = shortcode_atts( array(
			'status'   => 'any', // publish, draft, any
			'per_page' => 10,
		), $atts, 'user-story-list' );

		$user_id = get_current_user_id();
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		// Query user's stories
		$args = array(
			'post_type'      => 'fanfiction_story',
			'author'         => $user_id,
			'post_status'    => $atts['status'],
			'posts_per_page' => absint( $atts['per_page'] ),
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<div class="fanfic-user-stories fanfic-empty-state"><p>' . esc_html__( 'You haven\'t created any stories yet. Start writing!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="fanfic-user-stories">';
		$output .= '<h2>' . esc_html__( 'My Stories', 'fanfiction-manager' ) . '</h2>';
		$output .= '<ul class="fanfic-stories-list">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$story_id = get_the_ID();
			$story_url = get_permalink( $story_id );
			$edit_url = fanfic_get_edit_story_url( $story_id );
			$status = get_post_status( $story_id );
			$status_label = ( 'publish' === $status ) ? __( 'Published', 'fanfiction-manager' ) : __( 'Draft', 'fanfiction-manager' );

			// Get chapter count
			$chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );
			$chapter_count = count( $chapters );

			// Get story status (taxonomy)
			$story_statuses = wp_get_post_terms( $story_id, 'fanfiction_status', array( 'fields' => 'names' ) );
			$story_status = ! empty( $story_statuses ) ? $story_statuses[0] : '';

			$output .= '<li class="fanfic-story-item fanfic-story-status-' . esc_attr( $status ) . '">';
			$output .= '<div class="fanfic-story-info">';
			$output .= '<h3><a href="' . esc_url( $story_url ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
			$output .= '<p class="fanfic-story-meta">';
			$output .= sprintf(
				/* translators: 1: post status, 2: chapter count, 3: story status */
				esc_html__( '%1$s &middot; %2$d chapters', 'fanfiction-manager' ),
				'<span class="fanfic-post-status">' . esc_html( $status_label ) . '</span>',
				absint( $chapter_count )
			);
			if ( $story_status ) {
				$output .= ' &middot; <span class="fanfic-story-status">' . esc_html( $story_status ) . '</span>';
			}
			$output .= '</p>';
			$output .= '</div>';
			$output .= '<div class="fanfic-story-actions">';
			$output .= '<a href="' . esc_url( $edit_url ) . '" class="fanfic-button fanfic-edit-button">' . esc_html__( 'Edit', 'fanfiction-manager' ) . '</a>';
			$output .= '</div>';
			$output .= '</li>';
		}

		$output .= '</ul>';

		// Pagination
		if ( $query->max_num_pages > 1 ) {
			$output .= self::pagination( $paged, $query->max_num_pages );
		}

		wp_reset_postdata();

		$output .= '</div>';

		return $output;
	}

	/**
	 * User notification settings shortcode
	 *
	 * [user-notification-settings]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Notification settings form HTML.
	 */
	public static function user_notification_settings( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return self::login_prompt( __( 'Please log in to manage notification settings.', 'fanfiction-manager' ) );
		}

		$user_id = get_current_user_id();

		// Handle form submission
		if ( isset( $_POST['fanfic_save_notification_settings'] ) &&
		     check_admin_referer( 'fanfic_notification_settings_' . $user_id, 'fanfic_notification_settings_nonce' ) ) {
			self::save_notification_settings( $user_id );
		}

		// Get current settings
		$settings = get_user_meta( $user_id, 'fanfic_notification_settings', true );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Default settings
		$defaults = array(
			'email_new_chapter'      => true,
			'email_new_comment'      => true,
			'email_new_follower'     => true,
			'email_author_update'    => true,
			'inapp_new_chapter'      => true,
			'inapp_new_comment'      => true,
			'inapp_new_follower'     => true,
			'inapp_author_update'    => true,
			'email_frequency'        => 'instant', // instant, daily, weekly
		);

		$settings = wp_parse_args( $settings, $defaults );

		// Build output
		$output = '<div class="fanfic-notification-settings">';
		$output .= '<h2>' . esc_html__( 'Notification Settings', 'fanfiction-manager' ) . '</h2>';

		if ( isset( $_POST['fanfic_save_notification_settings'] ) &&
		     check_admin_referer( 'fanfic_notification_settings_' . $user_id, 'fanfic_notification_settings_nonce' ) ) {
			$output .= '<div class="fanfic-notice fanfic-notice-success"><p>' . esc_html__( 'Settings saved successfully!', 'fanfiction-manager' ) . '</p></div>';
		}

		$output .= '<form method="post" class="fanfic-settings-form">';
		$output .= wp_nonce_field( 'fanfic_notification_settings_' . $user_id, 'fanfic_notification_settings_nonce', true, false );

		$output .= '<h3>' . esc_html__( 'Email Notifications', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-settings-group">';

		$output .= self::checkbox_field(
			'email_new_chapter',
			__( 'New chapter from followed authors', 'fanfiction-manager' ),
			$settings['email_new_chapter']
		);

		$output .= self::checkbox_field(
			'email_new_comment',
			__( 'New comments on my stories', 'fanfiction-manager' ),
			$settings['email_new_comment']
		);

		$output .= self::checkbox_field(
			'email_new_follower',
			__( 'New followers', 'fanfiction-manager' ),
			$settings['email_new_follower']
		);

		$output .= self::checkbox_field(
			'email_author_update',
			__( 'Updates from followed authors', 'fanfiction-manager' ),
			$settings['email_author_update']
		);

		$output .= '</div>';

		$output .= '<h3>' . esc_html__( 'In-App Notifications', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-settings-group">';

		$output .= self::checkbox_field(
			'inapp_new_chapter',
			__( 'New chapter from followed authors', 'fanfiction-manager' ),
			$settings['inapp_new_chapter']
		);

		$output .= self::checkbox_field(
			'inapp_new_comment',
			__( 'New comments on my stories', 'fanfiction-manager' ),
			$settings['inapp_new_comment']
		);

		$output .= self::checkbox_field(
			'inapp_new_follower',
			__( 'New followers', 'fanfiction-manager' ),
			$settings['inapp_new_follower']
		);

		$output .= self::checkbox_field(
			'inapp_author_update',
			__( 'Updates from followed authors', 'fanfiction-manager' ),
			$settings['inapp_author_update']
		);

		$output .= '</div>';

		$output .= '<h3>' . esc_html__( 'Email Frequency', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-settings-group">';
		$output .= '<label>';
		$output .= '<input type="radio" name="email_frequency" value="instant" ' . checked( $settings['email_frequency'], 'instant', false ) . '>';
		$output .= ' ' . esc_html__( 'Instant (receive emails immediately)', 'fanfiction-manager' );
		$output .= '</label><br>';
		$output .= '<label>';
		$output .= '<input type="radio" name="email_frequency" value="daily" ' . checked( $settings['email_frequency'], 'daily', false ) . '>';
		$output .= ' ' . esc_html__( 'Daily digest (once per day)', 'fanfiction-manager' );
		$output .= '</label><br>';
		$output .= '<label>';
		$output .= '<input type="radio" name="email_frequency" value="weekly" ' . checked( $settings['email_frequency'], 'weekly', false ) . '>';
		$output .= ' ' . esc_html__( 'Weekly digest (once per week)', 'fanfiction-manager' );
		$output .= '</label>';
		$output .= '</div>';

		$output .= '<p class="fanfic-submit-wrapper">';
		$output .= '<input type="submit" name="fanfic_save_notification_settings" class="fanfic-button" value="' . esc_attr__( 'Save Settings', 'fanfiction-manager' ) . '">';
		$output .= '</p>';

		$output .= '</form>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Save notification settings
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function save_notification_settings( $user_id ) {
		$settings = array(
			'email_new_chapter'   => isset( $_POST['email_new_chapter'] ),
			'email_new_comment'   => isset( $_POST['email_new_comment'] ),
			'email_new_follower'  => isset( $_POST['email_new_follower'] ),
			'email_author_update' => isset( $_POST['email_author_update'] ),
			'inapp_new_chapter'   => isset( $_POST['inapp_new_chapter'] ),
			'inapp_new_comment'   => isset( $_POST['inapp_new_comment'] ),
			'inapp_new_follower'  => isset( $_POST['inapp_new_follower'] ),
			'inapp_author_update' => isset( $_POST['inapp_author_update'] ),
			'email_frequency'     => isset( $_POST['email_frequency'] ) ? sanitize_text_field( $_POST['email_frequency'] ) : 'instant',
		);

		update_user_meta( $user_id, 'fanfic_notification_settings', $settings );
	}

	/**
	 * Generate checkbox field HTML
	 *
	 * @since 1.0.0
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param bool   $checked Whether checked.
	 * @return string Checkbox HTML.
	 */
	private static function checkbox_field( $name, $label, $checked ) {
		$html = '<label>';
		$html .= '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . '>';
		$html .= ' ' . esc_html( $label );
		$html .= '</label><br>';
		return $html;
	}

	/**
	 * Generate pagination HTML
	 *
	 * @since 1.0.0
	 * @param int $current_page Current page number.
	 * @param int $total_pages Total number of pages.
	 * @return string Pagination HTML.
	 */
	private static function pagination( $current_page, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$output = '<div class="fanfic-pagination">';

		// Previous button
		if ( $current_page > 1 ) {
			$output .= '<a href="' . esc_url( get_pagenum_link( $current_page - 1 ) ) . '" class="fanfic-pagination-prev">' . esc_html__( '&laquo; Previous', 'fanfiction-manager' ) . '</a>';
		}

		// Page numbers
		$output .= '<span class="fanfic-pagination-pages">';
		$output .= sprintf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Page %1$d of %2$d', 'fanfiction-manager' ),
			absint( $current_page ),
			absint( $total_pages )
		);
		$output .= '</span>';

		// Next button
		if ( $current_page < $total_pages ) {
			$output .= '<a href="' . esc_url( get_pagenum_link( $current_page + 1 ) ) . '" class="fanfic-pagination-next">' . esc_html__( 'Next &raquo;', 'fanfiction-manager' ) . '</a>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Generate login prompt HTML
	 *
	 * @since 1.0.0
	 * @param string $message Message to display.
	 * @return string Login prompt HTML.
	 */
	private static function login_prompt( $message ) {
		$login_url = wp_login_url( fanfic_get_current_url() );
		$register_url = wp_registration_url();

		$output = '<div class="fanfic-login-prompt">';
		$output .= '<p>' . esc_html( $message ) . '</p>';
		$output .= '<p>';
		$output .= '<a href="' . esc_url( $login_url ) . '" class="fanfic-button">' . esc_html__( 'Log In', 'fanfiction-manager' ) . '</a> ';

		if ( get_option( 'users_can_register' ) ) {
			$output .= '<a href="' . esc_url( $register_url ) . '" class="fanfic-button secondary">' . esc_html__( 'Register', 'fanfiction-manager' ) . '</a>';
		}

		$output .= '</p>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Get current user's favorites count
	 *
	 * Public helper method for templates to get the favorites count.
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional user ID (defaults to current user).
	 * @return int Count of favorited stories.
	 */
	public static function get_favorites_count( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return 0;
		}

		// Use cached bookmarks count (5 minute cache)
		$cache_key = 'fanfic_bookmarks_count_' . $user_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$interactions_table = $wpdb->prefix . 'fanfic_interactions';

		// Get total count
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$interactions_table} WHERE user_id = %d AND interaction_type = 'bookmark'",
			$user_id
		) );

		$count = absint( $count );

		// Cache for 5 minutes
		set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );

		return max( 0, $count );
	}

	/**
	 * Render reading history
	 *
	 * Public helper method for templates to render reading history.
	 *
	 * @since 1.0.0
	 * @param array $args Arguments (limit, etc).
	 * @return string HTML output of reading history.
	 */
	public static function render_reading_history( $args = array() ) {
		$defaults = array(
			'limit' => 5,
		);
		$args = wp_parse_args( $args, $defaults );

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return '<div class="fanfic-reading-history fanfic-empty-state"><p>' . esc_html__( 'Please log in to view your reading history.', 'fanfiction-manager' ) . '</p></div>';
		}

		$user_id = get_current_user_id();

		// Get reading history from user meta (session-based tracking)
		$reading_history = get_user_meta( $user_id, 'fanfic_reading_history', true );

		if ( ! $reading_history || ! is_array( $reading_history ) ) {
			return '<div class="fanfic-reading-history fanfic-empty-state"><p>' . esc_html__( 'No reading history yet. Start reading stories!', 'fanfiction-manager' ) . '</p></div>';
		}

		// Sort by timestamp (most recent first)
		usort( $reading_history, function( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		} );

		// Limit results
		$reading_history = array_slice( $reading_history, 0, absint( $args['limit'] ) );

		// Build output
		$output = '<div class="fanfic-reading-history">';
		$output .= '<h2>' . esc_html__( 'Recently Read', 'fanfiction-manager' ) . '</h2>';
		$output .= '<ul class="fanfic-history-list">';

		foreach ( $reading_history as $item ) {
			$chapter_id = isset( $item['chapter_id'] ) ? absint( $item['chapter_id'] ) : 0;
			$timestamp = isset( $item['timestamp'] ) ? absint( $item['timestamp'] ) : 0;

			if ( ! $chapter_id || ! $timestamp ) {
				continue;
			}

			$chapter = get_post( $chapter_id );

			if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type || 'publish' !== $chapter->post_status ) {
				continue;
			}

			$story_id = $chapter->post_parent;
			$story = get_post( $story_id );

			if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
				continue;
			}

			$chapter_url = get_permalink( $chapter->ID );
			$story_url = get_permalink( $story->ID );
			$time_ago = human_time_diff( $timestamp, current_time( 'timestamp' ) );

			$output .= '<li class="fanfic-history-item">';
			$output .= '<div class="fanfic-history-info">';
			$output .= '<h3><a href="' . esc_url( $story_url ) . '">' . esc_html( $story->post_title ) . '</a></h3>';
			$output .= '<p class="fanfic-history-chapter">';
			$output .= '<a href="' . esc_url( $chapter_url ) . '">' . esc_html( $chapter->post_title ) . '</a>';
			$output .= '</p>';
			$output .= '<p class="fanfic-history-meta">';
			$output .= sprintf(
				/* translators: %s: time ago */
				esc_html__( 'Read %s ago', 'fanfiction-manager' ),
				esc_html( $time_ago )
			);
			$output .= '</p>';
			$output .= '</div>';
			$output .= '</li>';
		}

		$output .= '</ul>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * AJAX: Mark notification as read
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_mark_notification_read() {
		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		// Get notification ID
		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;

		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification ID.', 'fanfiction-manager' ) ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_mark_read_' . $notification_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Mark as read using Phase 9 class
		$result = Fanfic_Notifications::mark_as_read( $notification_id, get_current_user_id() );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Notification marked as read.', 'fanfiction-manager' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to mark notification as read.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX: Delete notification
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_delete_notification() {
		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		// Get notification ID
		$notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;

		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification ID.', 'fanfiction-manager' ) ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_delete_notification_' . $notification_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Delete notification using Phase 9 class
		$result = Fanfic_Notifications::delete_notification( $notification_id, get_current_user_id() );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Notification deleted.', 'fanfiction-manager' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete notification.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX: Mark all notifications as read
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_mark_all_read() {
		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_notifications' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Mark all as read using Phase 9 class
		$result = Fanfic_Notifications::mark_all_as_read( get_current_user_id() );

		if ( $result !== false ) {
			wp_send_json_success( array(
				'message' => __( 'All notifications marked as read.', 'fanfiction-manager' ),
				'count'   => $result,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to mark all notifications as read.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX: Get unread notification count
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_get_unread_count() {
		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		// Get unread count using Phase 9 class
		$count = Fanfic_Notifications::get_unread_count( get_current_user_id() );

		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * Notification bell icon shortcode
	 *
	 * Displays a notification bell icon with unread count badge and dropdown panel.
	 * [notification-bell-icon]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Notification bell HTML.
	 */
	public static function notification_bell_icon( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id = get_current_user_id();

		// Get unread count using Phase 9 class
		$unread_count = Fanfic_Notifications::get_unread_count( $user_id );

		// Get recent notifications (last 5) using Phase 9 class
		$recent_notifications = Fanfic_Notifications::get_user_notifications( $user_id, false, 5, 0 );

		// Build output
		$output = '<div class="fanfic-notification-bell" role="button" tabindex="0" aria-label="' . esc_attr__( 'Notifications', 'fanfiction-manager' ) . '" aria-haspopup="true" aria-expanded="false">';

		// Bell icon
		$output .= '<span class="fanfic-notification-bell-icon" aria-hidden="true">&#128276;</span>';

		// Badge with unread count (hidden if 0)
		if ( $unread_count > 0 ) {
			$output .= '<span class="fanfic-notification-bell-badge" aria-label="' . esc_attr( sprintf(
				/* translators: %d: unread notification count */
				_n( '%d unread notification', '%d unread notifications', $unread_count, 'fanfiction-manager' ),
				$unread_count
			) ) . '">' . absint( $unread_count ) . '</span>';
		} else {
			$output .= '<span class="fanfic-notification-bell-badge" style="display: none;" aria-label="' . esc_attr__( 'No unread notifications', 'fanfiction-manager' ) . '">0</span>';
		}

		// Dropdown panel
		$output .= '<div class="fanfic-notification-dropdown" role="menu" aria-label="' . esc_attr__( 'Recent notifications', 'fanfiction-manager' ) . '">';

		if ( empty( $recent_notifications ) ) {
			// No notifications
			$output .= '<div class="fanfic-notification-empty">';
			$output .= '<p>' . esc_html__( 'No notifications yet.', 'fanfiction-manager' ) . '</p>';
			$output .= '</div>';
		} else {
			// Display notifications
			$output .= '<ul class="fanfic-notification-list">';

			foreach ( $recent_notifications as $notification ) {
				$is_unread = ( 0 === intval( $notification->is_read ) );
				$time_ago = human_time_diff( strtotime( $notification->created_at ), current_time( 'timestamp' ) );

				$item_class = 'fanfic-notification-item';
				if ( $is_unread ) {
					$item_class .= ' fanfic-notification-unread';
				}

				$output .= '<li class="' . esc_attr( $item_class ) . '" role="menuitem">';

				// If notification has a link, make it clickable
				if ( ! empty( $notification->link ) ) {
					$output .= '<a href="' . esc_url( $notification->link ) . '" class="fanfic-notification-link">';
				} else {
					$output .= '<div class="fanfic-notification-content">';
				}

				// Unread indicator
				if ( $is_unread ) {
					$output .= '<span class="fanfic-notification-dot" aria-hidden="true"></span>';
				}

				// Notification message
				$output .= '<div class="fanfic-notification-text">';
				$output .= wp_kses_post( $notification->message );
				$output .= '</div>';

				// Time ago
				$output .= '<div class="fanfic-notification-time">';
				$output .= sprintf(
					/* translators: %s: time ago */
					esc_html__( '%s ago', 'fanfiction-manager' ),
					esc_html( $time_ago )
				);
				$output .= '</div>';

				// Close link or div
				if ( ! empty( $notification->link ) ) {
					$output .= '</a>';
				} else {
					$output .= '</div>';
				}

				$output .= '</li>';
			}

			$output .= '</ul>';
		}

		// View All link
		$notifications_url = home_url( '/dashboard/notifications/' );
		$output .= '<div class="fanfic-notification-footer">';
		$output .= '<a href="' . esc_url( $notifications_url ) . '" class="fanfic-view-all-notifications">' . esc_html__( 'View All Notifications', 'fanfiction-manager' ) . '</a>';
		$output .= '</div>';

		$output .= '</div>'; // .fanfic-notification-dropdown
		$output .= '</div>'; // .fanfic-notification-bell

		return $output;
	}

	/**
	 * User ban shortcode
	 *
	 * Displays a button to ban a user (moderators and admins only).
	 * [user-ban user_id="123" button_text="Ban User"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Ban user form HTML.
	 */
	public static function user_ban( $atts ) {
		// Parse attributes
		$atts = shortcode_atts( array(
			'user_id'     => 0,
			'button_text' => __( 'Ban User', 'fanfiction-manager' ),
		), $atts, 'user-ban' );

		$target_user_id = absint( $atts['user_id'] );

		// Validate user ID
		if ( ! $target_user_id ) {
			return '<div class="fanfic-error">' . esc_html__( 'Invalid user ID.', 'fanfiction-manager' ) . '</div>';
		}

		// Check if current user has capability
		if ( ! current_user_can( 'moderate_fanfiction' ) ) {
			return '';
		}

		// Check if user exists
		$user = get_userdata( $target_user_id );
		if ( ! $user ) {
			return '<div class="fanfic-error">' . esc_html__( 'User not found.', 'fanfiction-manager' ) . '</div>';
		}

		// Prevent users from banning themselves
		if ( get_current_user_id() === $target_user_id ) {
			return '<div class="fanfic-error">' . esc_html__( 'You cannot ban yourself.', 'fanfiction-manager' ) . '</div>';
		}

		// Handle form submission
		if ( isset( $_POST['fanfic_action'] ) && 'ban_user' === $_POST['fanfic_action'] &&
		     isset( $_POST['fanfic_user_id'] ) && absint( $_POST['fanfic_user_id'] ) === $target_user_id ) {

			// Verify nonce
			if ( ! isset( $_POST['fanfic_ban_nonce'] ) ||
			     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_ban_nonce'] ) ), 'fanfic_ban_user_' . $target_user_id ) ) {
				return '<div class="fanfic-error">' . esc_html__( 'Security check failed.', 'fanfiction-manager' ) . '</div>';
			}

			// Check capability again
			if ( ! current_user_can( 'moderate_fanfiction' ) ) {
				return '<div class="fanfic-error">' . esc_html__( 'You do not have permission to ban users.', 'fanfiction-manager' ) . '</div>';
			}

			// Ensure banned role exists
			self::ensure_banned_role_exists();

			// Get user object
			$target_user = new WP_User( $target_user_id );

			// Change user role to banned
			$target_user->set_role( 'fanfiction_banned_user' );

			// Log action to moderation log
			self::log_moderation_action( $target_user_id, 'ban', array(
				'moderator_id' => get_current_user_id(),
				'timestamp'    => current_time( 'mysql' ),
				'reason'       => __( 'User banned by moderator', 'fanfiction-manager' ),
			) );

			// Redirect with success message
			Fanfic_Flash_Messages::add_message( 'success', __( 'User banned successfully.', 'fanfiction-manager' ) );
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Display flash messages
		$output = self::render_flash_messages();

		// Display form
		ob_start();
		?>
		<form method="post" class="fanfic-user-action-form" id="fanfic-ban-user-<?php echo esc_attr( $target_user_id ); ?>">
			<?php wp_nonce_field( 'fanfic_ban_user_' . $target_user_id, 'fanfic_ban_nonce' ); ?>
			<input type="hidden" name="fanfic_action" value="ban_user" />
			<input type="hidden" name="fanfic_user_id" value="<?php echo esc_attr( $target_user_id ); ?>" />
			<button type="submit" class="fanfic-button secondary fanfic-ban-user-button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to ban this user? Their content will be preserved.', 'fanfiction-manager' ) ); ?>')">
				<?php echo esc_html( $atts['button_text'] ); ?>
			</button>
		</form>
		<?php
		return $output . ob_get_clean();
	}

	/**
	 * User promote to moderator shortcode
	 *
	 * Displays a button to promote a user to moderator (admins only).
	 * [user-moderator user_id="123" button_text="Promote to Moderator"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Promote user form HTML.
	 */
	public static function user_moderator( $atts ) {
		// Parse attributes
		$atts = shortcode_atts( array(
			'user_id'     => 0,
			'button_text' => __( 'Promote to Moderator', 'fanfiction-manager' ),
		), $atts, 'user-moderator' );

		$target_user_id = absint( $atts['user_id'] );

		// Validate user ID
		if ( ! $target_user_id ) {
			return '<div class="fanfic-error">' . esc_html__( 'Invalid user ID.', 'fanfiction-manager' ) . '</div>';
		}

		// Check if current user is admin
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// Check if user exists
		$user = get_userdata( $target_user_id );
		if ( ! $user ) {
			return '<div class="fanfic-error">' . esc_html__( 'User not found.', 'fanfiction-manager' ) . '</div>';
		}

		// Handle form submission
		if ( isset( $_POST['fanfic_action'] ) && 'promote_moderator' === $_POST['fanfic_action'] &&
		     isset( $_POST['fanfic_user_id'] ) && absint( $_POST['fanfic_user_id'] ) === $target_user_id ) {

			// Verify nonce
			if ( ! isset( $_POST['fanfic_moderator_nonce'] ) ||
			     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_moderator_nonce'] ) ), 'fanfic_promote_moderator_' . $target_user_id ) ) {
				return '<div class="fanfic-error">' . esc_html__( 'Security check failed.', 'fanfiction-manager' ) . '</div>';
			}

			// Check capability again
			if ( ! current_user_can( 'manage_options' ) ) {
				return '<div class="fanfic-error">' . esc_html__( 'You do not have permission to promote users.', 'fanfiction-manager' ) . '</div>';
			}

			// Get user object
			$target_user = new WP_User( $target_user_id );

			// Change user role to moderator
			$target_user->set_role( 'fanfiction_moderator' );

			// Log action to moderation log
			self::log_moderation_action( $target_user_id, 'promote_moderator', array(
				'moderator_id' => get_current_user_id(),
				'timestamp'    => current_time( 'mysql' ),
				'reason'       => __( 'User promoted to moderator', 'fanfiction-manager' ),
			) );

			// Redirect with success message
			Fanfic_Flash_Messages::add_message( 'success', __( 'User promoted to moderator successfully.', 'fanfiction-manager' ) );
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Display flash messages
		$output = self::render_flash_messages();

		// Display form
		ob_start();
		?>
		<form method="post" class="fanfic-user-action-form" id="fanfic-promote-moderator-<?php echo esc_attr( $target_user_id ); ?>">
			<?php wp_nonce_field( 'fanfic_promote_moderator_' . $target_user_id, 'fanfic_moderator_nonce' ); ?>
			<input type="hidden" name="fanfic_action" value="promote_moderator" />
			<input type="hidden" name="fanfic_user_id" value="<?php echo esc_attr( $target_user_id ); ?>" />
			<button type="submit" class="fanfic-button fanfic-promote-moderator-button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to promote this user to moderator?', 'fanfiction-manager' ) ); ?>')">
				<?php echo esc_html( $atts['button_text'] ); ?>
			</button>
		</form>
		<?php
		return $output . ob_get_clean();
	}

	/**
	 * User demote from moderator shortcode
	 *
	 * Displays a button to demote a moderator to author (admins only).
	 * [user-demoderator user_id="123" button_text="Demote to Author"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Demote user form HTML.
	 */
	public static function user_demoderator( $atts ) {
		// Parse attributes
		$atts = shortcode_atts( array(
			'user_id'     => 0,
			'button_text' => __( 'Demote to Author', 'fanfiction-manager' ),
		), $atts, 'user-demoderator' );

		$target_user_id = absint( $atts['user_id'] );

		// Validate user ID
		if ( ! $target_user_id ) {
			return '<div class="fanfic-error">' . esc_html__( 'Invalid user ID.', 'fanfiction-manager' ) . '</div>';
		}

		// Check if current user is admin
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// Check if user exists
		$user = get_userdata( $target_user_id );
		if ( ! $user ) {
			return '<div class="fanfic-error">' . esc_html__( 'User not found.', 'fanfiction-manager' ) . '</div>';
		}

		// Prevent admins from demoting themselves
		if ( get_current_user_id() === $target_user_id ) {
			return '<div class="fanfic-error">' . esc_html__( 'You cannot demote yourself.', 'fanfiction-manager' ) . '</div>';
		}

		// Handle form submission
		if ( isset( $_POST['fanfic_action'] ) && 'demote_moderator' === $_POST['fanfic_action'] &&
		     isset( $_POST['fanfic_user_id'] ) && absint( $_POST['fanfic_user_id'] ) === $target_user_id ) {

			// Verify nonce
			if ( ! isset( $_POST['fanfic_demoderator_nonce'] ) ||
			     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_demoderator_nonce'] ) ), 'fanfic_demote_moderator_' . $target_user_id ) ) {
				return '<div class="fanfic-error">' . esc_html__( 'Security check failed.', 'fanfiction-manager' ) . '</div>';
			}

			// Check capability again
			if ( ! current_user_can( 'manage_options' ) ) {
				return '<div class="fanfic-error">' . esc_html__( 'You do not have permission to demote users.', 'fanfiction-manager' ) . '</div>';
			}

			// Get user object
			$target_user = new WP_User( $target_user_id );

			// Change user role to author
			$target_user->set_role( 'fanfiction_author' );

			// Log action to moderation log
			self::log_moderation_action( $target_user_id, 'demote_moderator', array(
				'moderator_id' => get_current_user_id(),
				'timestamp'    => current_time( 'mysql' ),
				'reason'       => __( 'User demoted from moderator to author', 'fanfiction-manager' ),
			) );

			// Redirect with success message
			Fanfic_Flash_Messages::add_message( 'success', __( 'User demoted to author successfully.', 'fanfiction-manager' ) );
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// Display flash messages
		$output = self::render_flash_messages();

		// Display form
		ob_start();
		?>
		<form method="post" class="fanfic-user-action-form" id="fanfic-demote-moderator-<?php echo esc_attr( $target_user_id ); ?>">
			<?php wp_nonce_field( 'fanfic_demote_moderator_' . $target_user_id, 'fanfic_demoderator_nonce' ); ?>
			<input type="hidden" name="fanfic_action" value="demote_moderator" />
			<input type="hidden" name="fanfic_user_id" value="<?php echo esc_attr( $target_user_id ); ?>" />
			<button type="submit" class="fanfic-button secondary fanfic-demote-moderator-button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to demote this moderator to author?', 'fanfiction-manager' ) ); ?>')">
				<?php echo esc_html( $atts['button_text'] ); ?>
			</button>
		</form>
		<?php
		return $output . ob_get_clean();
	}

	/**
	 * Ensure banned user role exists
	 *
	 * Creates the banned user role if it doesn't exist.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function ensure_banned_role_exists() {
		if ( ! get_role( 'fanfiction_banned_user' ) ) {
			add_role(
				'fanfiction_banned_user',
				__( 'Fanfiction Banned User', 'fanfiction-manager' ),
				array( 'read' => true )
			);
		}
	}

	/**
	 * Log moderation action to user meta
	 *
	 * Logs a moderation action (ban, promote, demote) to user meta.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID who was affected.
	 * @param string $action Action type (ban, promote_moderator, demote_moderator).
	 * @param array  $data Additional data to log.
	 * @return void
	 */
	private static function log_moderation_action( $user_id, $action, $data ) {
		// Get existing log
		$log = get_user_meta( $user_id, 'fanfic_moderation_log', true );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Add new log entry
		$log[] = array_merge( array(
			'action' => $action,
		), $data );

		// Save log
		update_user_meta( $user_id, 'fanfic_moderation_log', $log );
	}
}
