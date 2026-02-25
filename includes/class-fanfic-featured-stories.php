<?php
/**
 * Featured Stories Class
 *
 * Manages the featured stories system: manual featuring by admins/moderators,
 * automatic featuring via a daily cron scoring formula, or both simultaneously.
 *
 * @package FanfictionManager
 * @since 2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Featured_Stories
 *
 * @since 2.3.0
 */
class Fanfic_Featured_Stories {

	/**
	 * Cron hook name for auto-featuring.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'fanfic_auto_feature_stories';

	/**
	 * Post meta keys.
	 *
	 * @var string
	 */
	const META_IS_FEATURED   = 'fanfic_is_featured';
	const META_FEATURED_TYPE = 'fanfic_featured_type';
	const META_FEATURED_SCORE = 'fanfic_featured_score';
	const META_FEATURED_AT   = 'fanfic_featured_at';

	/**
	 * Daily start offset in minutes from cron_hour.
	 * 20 minutes to avoid collision with status automation (10 min offset).
	 *
	 * @var int
	 */
	const CRON_OFFSET_MINUTES = 20;

	/**
	 * Base weights for the scoring formula.
	 *
	 * @var array
	 */
	const BASE_WEIGHTS = array(
		'comments' => 5,
		'ratings'  => 4,
		'likes'    => 3,
		'follows'  => 1,
		'views'    => 0.5,
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_auto_featuring' ) );
		add_action( 'update_option_fanfic_settings', array( __CLASS__, 'reschedule_on_settings_change' ), 10, 2 );

		$mode = Fanfic_Settings::get_setting( 'featured_mode', 'manual' );
		if ( in_array( $mode, array( 'automatic', 'both' ), true ) ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				self::schedule_cron();
			}
		}
	}

	/**
	 * Schedule daily auto-featuring at configured cron hour + offset.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	public static function schedule_cron() {
		$cron_hour = Fanfic_Settings::get_setting( 'cron_hour', 3 );
		$next_run  = self::calculate_next_run_time( $cron_hour, self::CRON_OFFSET_MINUTES );
		return wp_schedule_event( $next_run, 'daily', self::CRON_HOOK );
	}

	/**
	 * Unschedule auto-featuring cron.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function unschedule_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Re-schedule when settings change (cron hour or featured mode).
	 *
	 * @since 2.3.0
	 * @param array $old_value Previous settings.
	 * @param array $new_value New settings.
	 * @return void
	 */
	public static function reschedule_on_settings_change( $old_value, $new_value ) {
		$old_hour = isset( $old_value['cron_hour'] ) ? absint( $old_value['cron_hour'] ) : 3;
		$new_hour = isset( $new_value['cron_hour'] ) ? absint( $new_value['cron_hour'] ) : 3;
		$old_mode = isset( $old_value['featured_mode'] ) ? $old_value['featured_mode'] : 'manual';
		$new_mode = isset( $new_value['featured_mode'] ) ? $new_value['featured_mode'] : 'manual';

		$needs_cron = in_array( $new_mode, array( 'automatic', 'both' ), true );

		if ( ! $needs_cron ) {
			self::unschedule_cron();
			return;
		}

		if ( $old_hour !== $new_hour || $old_mode !== $new_mode ) {
			self::unschedule_cron();
			self::schedule_cron();
		}
	}

	/**
	 * Calculate next run timestamp for a target hour.
	 *
	 * @since 2.3.0
	 * @param int $hour           Hour (0-23).
	 * @param int $offset_minutes Offset in minutes.
	 * @return int Timestamp.
	 */
	private static function calculate_next_run_time( $hour, $offset_minutes = 0 ) {
		$hour           = min( 23, max( 0, absint( $hour ) ) );
		$offset_minutes = max( 0, absint( $offset_minutes ) );

		$current_time   = current_time( 'timestamp' );
		$today          = date_i18n( 'Y-m-d', $current_time );
		$scheduled_time = strtotime( sprintf( '%s %02d:00:00', $today, $hour ) );
		$scheduled_time = strtotime( '+' . $offset_minutes . ' minutes', $scheduled_time );

		if ( $scheduled_time <= $current_time ) {
			$scheduled_time = strtotime( '+1 day', $scheduled_time );
		}

		return $scheduled_time;
	}

	/**
	 * Main cron entry: calculate scores, select top stories, update meta.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function run_auto_featuring() {
		$mode = Fanfic_Settings::get_setting( 'featured_mode', 'manual' );
		if ( ! in_array( $mode, array( 'automatic', 'both' ), true ) ) {
			return;
		}

		$max_count = absint( Fanfic_Settings::get_setting( 'featured_max_count', 6 ) );
		if ( $max_count < 1 ) {
			$max_count = 6;
		}

		// Calculate scores for all published stories.
		$scores = self::calculate_scores();
		if ( empty( $scores ) ) {
			return;
		}

		// In 'both' mode, manual picks stay forever and don't count toward the cap.
		$manual_ids = array();
		if ( 'both' === $mode ) {
			$manual_ids = self::get_manual_featured_ids();
		}

		// Select top stories excluding manual picks.
		$auto_ids = self::select_top_stories( $scores, $max_count, $manual_ids );

		// Get current auto-featured IDs.
		$current_auto = self::get_auto_featured_ids();

		// Remove auto-featured status from stories no longer in top list.
		$to_remove = array_diff( $current_auto, $auto_ids );
		foreach ( $to_remove as $story_id ) {
			delete_post_meta( $story_id, self::META_IS_FEATURED );
			delete_post_meta( $story_id, self::META_FEATURED_TYPE );
			delete_post_meta( $story_id, self::META_FEATURED_SCORE );
			delete_post_meta( $story_id, self::META_FEATURED_AT );
		}

		// Add or update auto-featured stories.
		$now = current_time( 'mysql' );
		foreach ( $auto_ids as $story_id ) {
			$score = isset( $scores[ $story_id ] ) ? $scores[ $story_id ] : 0;
			update_post_meta( $story_id, self::META_IS_FEATURED, 1 );
			update_post_meta( $story_id, self::META_FEATURED_TYPE, 'automatic' );
			update_post_meta( $story_id, self::META_FEATURED_SCORE, round( $score, 4 ) );
			if ( ! in_array( $story_id, $current_auto, true ) ) {
				update_post_meta( $story_id, self::META_FEATURED_AT, $now );
			}
		}

		// In 'automatic' mode, remove any stale manual picks.
		if ( 'automatic' === $mode ) {
			$stale_manual = self::get_manual_featured_ids();
			foreach ( $stale_manual as $story_id ) {
				if ( ! in_array( $story_id, $auto_ids, true ) ) {
					delete_post_meta( $story_id, self::META_IS_FEATURED );
					delete_post_meta( $story_id, self::META_FEATURED_TYPE );
					delete_post_meta( $story_id, self::META_FEATURED_SCORE );
					delete_post_meta( $story_id, self::META_FEATURED_AT );
				}
			}
		}

		// Clear cached featured stories.
		self::clear_featured_cache();
	}

	/**
	 * Calculate scores for all published stories from the search index.
	 *
	 * @since 2.3.0
	 * @return array Associative array of story_id => score.
	 */
	public static function calculate_scores() {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_story_search_index';
		$weights = self::get_active_weights();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT story_id, comment_count, rating_avg_total, rating_count_total,
					likes_total, view_count, follow_count
			 FROM {$table}
			 WHERE story_status = 'publish'",
			OBJECT
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$scores = array();
		foreach ( $rows as $row ) {
			$score = 0;

			if ( isset( $weights['comments'] ) ) {
				$score += $weights['comments'] * log( 1 + (int) $row->comment_count, 2 );
			}
			if ( isset( $weights['ratings'] ) ) {
				$score += $weights['ratings'] * (float) $row->rating_avg_total * log( 1 + (int) $row->rating_count_total, 2 );
			}
			if ( isset( $weights['likes'] ) ) {
				$score += $weights['likes'] * log( 1 + (int) $row->likes_total, 2 );
			}
			if ( isset( $weights['views'] ) ) {
				$score += $weights['views'] * log( 1 + (int) $row->view_count, 2 );
			}
			if ( isset( $weights['follows'] ) ) {
				$score += $weights['follows'] * log( 1 + (int) $row->follow_count, 2 );
			}

			$scores[ (int) $row->story_id ] = $score;
		}

		return $scores;
	}

	/**
	 * Get active weights, scaling proportionally when features are disabled.
	 *
	 * @since 2.3.0
	 * @return array Weight map with only active features.
	 */
	public static function get_active_weights() {
		$active = array();
		$total_all    = 0;
		$total_active = 0;

		// Comments can be disabled.
		$comments_enabled = (bool) Fanfic_Settings::get_setting( 'enable_comments', true );
		// Likes can be disabled.
		$likes_enabled = (bool) Fanfic_Settings::get_setting( 'enable_likes', true );

		// Ratings and views are always active (no settings toggle).
		$feature_active = array(
			'comments' => $comments_enabled,
			'ratings'  => true,
			'likes'    => $likes_enabled,
			'follows'  => true,
			'views'    => true,
		);

		foreach ( self::BASE_WEIGHTS as $feature => $weight ) {
			$total_all += $weight;
			if ( $feature_active[ $feature ] ) {
				$total_active += $weight;
			}
		}

		if ( $total_active <= 0 ) {
			return array( 'views' => 1.0 );
		}

		$scale = $total_all / $total_active;

		foreach ( self::BASE_WEIGHTS as $feature => $weight ) {
			if ( $feature_active[ $feature ] ) {
				$active[ $feature ] = $weight * $scale;
			}
		}

		return $active;
	}

	/**
	 * Select top stories by score, handling tie overflow at the cutoff.
	 *
	 * @since 2.3.0
	 * @param array $scores      Associative array of story_id => score.
	 * @param int   $max         Maximum auto slots.
	 * @param array $exclude_ids Story IDs to exclude (manual picks).
	 * @return array Array of story IDs to auto-feature.
	 */
	public static function select_top_stories( $scores, $max, $exclude_ids = array() ) {
		// Remove excluded stories.
		foreach ( $exclude_ids as $id ) {
			unset( $scores[ $id ] );
		}

		if ( empty( $scores ) ) {
			return array();
		}

		// If total published stories <= max, all are auto-featured.
		if ( count( $scores ) <= $max ) {
			return array_keys( $scores );
		}

		// Sort by score descending.
		arsort( $scores );

		$sorted_ids    = array_keys( $scores );
		$selected      = array_slice( $sorted_ids, 0, $max );
		$cutoff_score  = $scores[ end( $selected ) ];

		// Include any tied stories at the cutoff score.
		foreach ( $sorted_ids as $story_id ) {
			if ( in_array( $story_id, $selected, true ) ) {
				continue;
			}
			if ( abs( $scores[ $story_id ] - $cutoff_score ) < 0.0001 ) {
				$selected[] = $story_id;
			}
		}

		return $selected;
	}

	/**
	 * Toggle manual featured status on a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 * @return array {featured: bool, type: string}
	 */
	public static function toggle_featured( $story_id ) {
		$story_id = absint( $story_id );
		$current  = get_post_meta( $story_id, self::META_IS_FEATURED, true );

		if ( $current ) {
			// Unfeaturing.
			delete_post_meta( $story_id, self::META_IS_FEATURED );
			delete_post_meta( $story_id, self::META_FEATURED_TYPE );
			delete_post_meta( $story_id, self::META_FEATURED_SCORE );
			delete_post_meta( $story_id, self::META_FEATURED_AT );
			self::clear_featured_cache();

			return array(
				'featured' => false,
				'type'     => '',
			);
		}

		// Featuring.
		$now = current_time( 'mysql' );
		update_post_meta( $story_id, self::META_IS_FEATURED, 1 );
		update_post_meta( $story_id, self::META_FEATURED_TYPE, 'manual' );
		update_post_meta( $story_id, self::META_FEATURED_AT, $now );
		self::clear_featured_cache();

		return array(
			'featured' => true,
			'type'     => 'manual',
		);
	}

	/**
	 * Check if a story is featured.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 * @return bool
	 */
	public static function is_featured( $story_id ) {
		return (bool) get_post_meta( absint( $story_id ), self::META_IS_FEATURED, true );
	}

	/**
	 * Get the featured type for a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 * @return string|false 'manual', 'automatic', or false.
	 */
	public static function get_featured_type( $story_id ) {
		$type = get_post_meta( absint( $story_id ), self::META_FEATURED_TYPE, true );
		return $type ? $type : false;
	}

	/**
	 * Get featured stories via WP_Query.
	 *
	 * @since 2.3.0
	 * @param int $limit Max stories to return.
	 * @return WP_Post[] Array of story posts.
	 */
	public static function get_featured_stories( $limit = 6 ) {
		$cache_key = 'fanfic_featured_stories_' . absint( $limit );
		$stories   = get_transient( $cache_key );

		if ( false !== $stories ) {
			return $stories;
		}

		$stories = get_posts( array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $limit ),
			'no_found_rows'  => true,
			'meta_key'       => self::META_IS_FEATURED,
			'meta_value'     => '1',
			'orderby'        => 'meta_value_num',
			'meta_query'     => array(
				array(
					'key'   => self::META_IS_FEATURED,
					'value' => '1',
				),
			),
		) );

		set_transient( $cache_key, $stories, 30 * MINUTE_IN_SECONDS );

		return $stories;
	}

	/**
	 * Render the star badge HTML for a story.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 * @return string HTML or empty string.
	 */
	public static function render_star_badge( $story_id ) {
		if ( ! self::is_featured( $story_id ) ) {
			return '';
		}

		return '<span class="fanfic-featured-star" aria-label="' . esc_attr__( 'Featured story', 'fanfiction-manager' ) . '" title="' . esc_attr__( 'Featured', 'fanfiction-manager' ) . '">' .
			'<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>' .
		'</span>';
	}

	/**
	 * Render the feature/unfeature toggle button for authorized users.
	 *
	 * @since 2.3.0
	 * @param int $story_id Story post ID.
	 * @return string HTML or empty string.
	 */
	public static function render_feature_button( $story_id ) {
		if ( ! self::can_user_feature() ) {
			return '';
		}

		$mode = Fanfic_Settings::get_setting( 'featured_mode', 'manual' );
		if ( 'automatic' === $mode ) {
			return '';
		}

		$is_featured = self::is_featured( $story_id );
		$class       = 'fanfic-button fanfic-feature-toggle-btn' . ( $is_featured ? ' is-featured' : '' );
		$label       = $is_featured
			? __( 'Unfeature story', 'fanfiction-manager' )
			: __( 'Feature story', 'fanfiction-manager' );

		return sprintf(
			'<button type="button" class="%s" data-story-id="%d" aria-label="%s" title="%s">' .
				'<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>' .
				'<span class="fanfic-button-text">%s</span>' .
			'</button>',
			esc_attr( $class ),
			absint( $story_id ),
			esc_attr( $label ),
			esc_attr( $label ),
			$is_featured ? esc_html__( 'Featured', 'fanfiction-manager' ) : esc_html__( 'Feature', 'fanfiction-manager' )
		);
	}

	/**
	 * Check if current user can feature/unfeature stories.
	 *
	 * @since 2.3.0
	 * @return bool
	 */
	public static function can_user_feature() {
		return current_user_can( 'moderate_fanfiction' );
	}

	/**
	 * Get IDs of manually featured stories.
	 *
	 * @since 2.3.0
	 * @return int[]
	 */
	public static function get_manual_featured_ids() {
		$query = new WP_Query( array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => self::META_FEATURED_TYPE,
					'value' => 'manual',
				),
			),
		) );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Get IDs of auto-featured stories.
	 *
	 * @since 2.3.0
	 * @return int[]
	 */
	public static function get_auto_featured_ids() {
		$query = new WP_Query( array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => self::META_FEATURED_TYPE,
					'value' => 'automatic',
				),
			),
		) );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Get count of manually featured stories.
	 *
	 * @since 2.3.0
	 * @return int
	 */
	public static function get_manual_featured_count() {
		return count( self::get_manual_featured_ids() );
	}

	/**
	 * Get count of auto-featured stories.
	 *
	 * @since 2.3.0
	 * @return int
	 */
	public static function get_auto_featured_count() {
		return count( self::get_auto_featured_ids() );
	}

	/**
	 * Clear all featured-related transient caches.
	 *
	 * @since 2.3.0
	 * @return void
	 */
	public static function clear_featured_cache() {
		global $wpdb;

		// Delete all featured stories transients (varying limits).
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_fanfic_featured_stories_%'
			    OR option_name LIKE '_transient_timeout_fanfic_featured_stories_%'
			    OR option_name LIKE '_transient_fanfic_widget_featured_stories_%'
			    OR option_name LIKE '_transient_timeout_fanfic_widget_featured_stories_%'"
		);
	}
}
