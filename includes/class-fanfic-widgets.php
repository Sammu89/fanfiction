<?php
/**
 * Widgets Manager Class
 *
 * Handles registration and loading of all custom widgets.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Widgets
 *
 * Central manager for all fanfiction widgets.
 *
 * @since 1.0.0
 */
class Fanfic_Widgets {

	/**
	 * Cache time constants (in seconds)
	 */
	const CACHE_RECENT_STORIES = 600;     // 10 minutes
	const CACHE_FEATURED_STORIES = 1800;  // 30 minutes
	const CACHE_BOOKMARKED = 300;         // 5 minutes
	const CACHE_TOP_AUTHORS = 900;        // 15 minutes

	/**
	 * Register all widgets
	 *
	 * Loads widget classes and registers them with WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_widgets() {
		// Load widget base class if needed
		if ( ! class_exists( 'WP_Widget' ) ) {
			return;
		}

		// Load widget classes
		require_once FANFIC_INCLUDES_DIR . 'widgets/class-fanfic-widget-recent-stories.php';
		require_once FANFIC_INCLUDES_DIR . 'widgets/class-fanfic-widget-featured-stories.php';
		require_once FANFIC_INCLUDES_DIR . 'widgets/class-fanfic-widget-most-bookmarked.php';
		require_once FANFIC_INCLUDES_DIR . 'widgets/class-fanfic-widget-top-authors.php';

		// Register widgets with WordPress
		register_widget( 'Fanfic_Widget_Recent_Stories' );
		register_widget( 'Fanfic_Widget_Featured_Stories' );
		register_widget( 'Fanfic_Widget_Most_Bookmarked' );
		register_widget( 'Fanfic_Widget_Top_Authors' );
	}

	/**
	 * Clear all widget caches
	 *
	 * Clears all transients used by widgets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_all_caches() {
		// Clear recent stories cache
		for ( $i = 5; $i <= 20; $i++ ) {
			delete_transient( "fanfic_widget_recent_stories_{$i}" );
		}

		// Clear featured stories cache
		for ( $i = 5; $i <= 20; $i++ ) {
			delete_transient( "fanfic_widget_featured_stories_{$i}" );
		}

		// Note: Most bookmarked and top authors use their class caches
	}

	/**
	 * Clear cache on story publish/update
	 *
	 * Clears relevant widget caches when stories change.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function clear_cache_on_story_change( $story_id ) {
		// Clear recent stories cache
		for ( $i = 5; $i <= 20; $i++ ) {
			delete_transient( "fanfic_widget_recent_stories_{$i}" );
		}
	}

	/**
	 * Clear cache on featured stories update
	 *
	 * Clears featured stories widget cache.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_featured_cache() {
		// Clear featured stories cache
		for ( $i = 5; $i <= 20; $i++ ) {
			delete_transient( "fanfic_widget_featured_stories_{$i}" );
		}
	}

	/**
	 * Get formatted story link
	 *
	 * Returns HTML for a story link with title.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return string HTML link.
	 */
	public static function get_story_link( $story_id ) {
		$story = get_post( $story_id );
		if ( ! $story ) {
			return '';
		}

		$permalink = get_permalink( $story_id );
		$title = esc_html( get_the_title( $story_id ) );

		return sprintf(
			'<a href="%s" class="fanfic-widget-link">%s</a>',
			esc_url( $permalink ),
			$title
		);
	}

	/**
	 * Get formatted author link
	 *
	 * Returns HTML for an author link with name.
	 *
	 * @since 1.0.0
	 * @param int $author_id Author user ID.
	 * @return string HTML link.
	 */
	public static function get_author_link( $author_id ) {
		$author = get_userdata( $author_id );
		if ( ! $author ) {
			return '';
		}

		$author_url = fanfic_get_user_profile_url( $author_id );
		$author_name = esc_html( $author->display_name );

		return sprintf(
			'<a href="%s" class="fanfic-widget-link fanfic-widget-author">%s</a>',
			esc_url( $author_url ),
			$author_name
		);
	}

	/**
	 * Get story author name
	 *
	 * Returns the author display name for a story.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return string Author name.
	 */
	public static function get_story_author_name( $story_id ) {
		$story = get_post( $story_id );
		if ( ! $story ) {
			return '';
		}

		$author = get_userdata( $story->post_author );
		if ( ! $author ) {
			return '';
		}

		return esc_html( $author->display_name );
	}

	/**
	 * Get formatted date
	 *
	 * Returns formatted date string.
	 *
	 * @since 1.0.0
	 * @param string $date MySQL date string.
	 * @return string Formatted date.
	 */
	public static function get_formatted_date( $date ) {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return '';
		}

		return esc_html( date_i18n( get_option( 'date_format' ), $timestamp ) );
	}

	/**
	 * Render empty state
	 *
	 * Outputs empty state HTML for widgets with no content.
	 *
	 * @since 1.0.0
	 * @param string $message Empty state message.
	 * @return void
	 */
	public static function render_empty_state( $message ) {
		printf(
			'<div class="fanfic-widget-empty"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Sanitize widget count input
	 *
	 * Ensures count is within valid range.
	 *
	 * @since 1.0.0
	 * @param int $count Input count.
	 * @param int $min   Minimum allowed value.
	 * @param int $max   Maximum allowed value.
	 * @param int $default Default value.
	 * @return int Sanitized count.
	 */
	public static function sanitize_count( $count, $min = 5, $max = 20, $default = 5 ) {
		$count = absint( $count );

		if ( $count < $min || $count > $max ) {
			return $default;
		}

		return $count;
	}

	/**
	 * Render story count badge
	 *
	 * Outputs story count badge HTML.
	 *
	 * @since 1.0.0
	 * @param int $count Story count.
	 * @return string Badge HTML.
	 */
	public static function get_story_count_badge( $count ) {
		return sprintf(
			'<span class="fanfic-widget-count">%s</span>',
			sprintf(
				/* translators: %d: number of stories */
				_n( '%d story', '%d stories', $count, 'fanfiction-manager' ),
				absint( $count )
			)
		);
	}

	/**
	 * Render follower count badge
	 *
	 * Outputs follower count badge HTML.
	 *
	 * @since 1.0.0
	 * @param int $count Follower count.
	 * @return string Badge HTML.
	 */
	public static function get_follower_count_badge( $count ) {
		return sprintf(
			'<span class="fanfic-widget-count">%s</span>',
			sprintf(
				/* translators: %d: number of followers */
				_n( '%d follower', '%d followers', $count, 'fanfiction-manager' ),
				absint( $count )
			)
		);
	}

	/**
	 * Render bookmark count badge
	 *
	 * Outputs bookmark count badge HTML.
	 *
	 * @since 1.0.0
	 * @param int $count Bookmark count.
	 * @return string Badge HTML.
	 */
	public static function get_bookmark_count_badge( $count ) {
		return sprintf(
			'<span class="fanfic-widget-count">%s</span>',
			sprintf(
				/* translators: %d: number of bookmarks */
				_n( '%d bookmark', '%d bookmarks', $count, 'fanfiction-manager' ),
				absint( $count )
			)
		);
	}
}
