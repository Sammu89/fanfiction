<?php
/**
 * Statistics Shortcodes Class
 *
 * Handles all statistics display shortcodes (ratings, follows, views, trending).
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
 * Class Fanfic_Shortcodes_Stats
 *
 * Statistics and leaderboard shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Stats {

	/**
	 * Register statistics shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Rating shortcodes
		add_shortcode( 'story-rating-display', array( __CLASS__, 'story_rating_display' ) );
		add_shortcode( 'top-rated-stories', array( __CLASS__, 'top_rated_stories' ) );
		add_shortcode( 'recently-rated-stories', array( __CLASS__, 'recently_rated_stories' ) );

		// Follow shortcodes
		add_shortcode( 'story-follow-button', array( __CLASS__, 'story_follow_button' ) );
		add_shortcode( 'story-follow-count', array( __CLASS__, 'story_follow_count' ) );
		add_shortcode( 'most-followed-stories', array( __CLASS__, 'most_followed_stories' ) );

		// View shortcodes
		add_shortcode( 'story-view-count', array( __CLASS__, 'story_view_count' ) );
		add_shortcode( 'chapter-view-count', array( __CLASS__, 'chapter_view_count' ) );
		add_shortcode( 'most-viewed-stories', array( __CLASS__, 'most_viewed_stories' ) );

		// Trending shortcode
		add_shortcode( 'trending-stories', array( __CLASS__, 'trending_stories' ) );

		// Author stats shortcode
		add_shortcode( 'author-stats', array( __CLASS__, 'author_stats' ) );

		// NEW: Like system shortcodes (v2.0)
		add_shortcode( 'fanfiction-story-like-count', array( __CLASS__, 'story_like_count' ) );

		// Story rating (v2.0)
		add_shortcode( 'fanfiction-story-rating', array( __CLASS__, 'story_rating' ) );
	}

	/**
	 * Story rating display shortcode (read-only stars with count)
	 *
	 * [story-rating-display story_id="123" size="medium"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rating display HTML.
	 */
	public static function story_rating_display( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
				'size'     => 'medium',
			),
			'story-rating-display'
		);

		$story_id = absint( $atts['story_id'] );

		if ( ! $story_id ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		$rating_data = Fanfic_Interactions::get_story_rating( $story_id );

		if ( ! $rating_data || $rating_data->total_votes === 0 ) {
			return '<div class="fanfic-story-rating-display fanfic-no-ratings"><p>' . esc_html__( 'No ratings yet', 'fanfiction-manager' ) . '</p></div>';
		}

		$rating = $rating_data->average_rating;
		$count = $rating_data->total_votes;

		$output = '<div class="fanfic-story-rating-display" data-avg="' . esc_attr( $rating ) . '" data-count="' . esc_attr( $count ) . '">';
		$output .= '<span class="fanfic-rating-average">' . esc_html( number_format_i18n( $rating, 1 ) ) . '</span>';
		$output .= Fanfic_Interactions::get_star_icon_html( 'full' );
		$output .= '<span class="fanfic-rating-count">(' . esc_html( number_format_i18n( $count ) ) . ')</span>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Top rated stories shortcode
	 *
	 * [top-rated-stories limit="10" min_ratings="5" period="total"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Top rated stories list HTML.
	 */
	public static function top_rated_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'       => 10,
				'min_ratings' => 5,
				'period'      => 'total',
			),
			'top-rated-stories'
		);

		$stories = Fanfic_Interactions::get_top_rated_stories(
			absint( $atts['limit'] ),
			absint( $atts['min_ratings'] ),
			sanitize_key( $atts['period'] )
		);

		if ( empty( $stories ) ) {
			return '<div class="fanfic-top-rated fanfic-empty-state"><p>' . esc_html__( 'No rated stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-top-rated" role="region" aria-label="' . esc_attr__( 'Top rated stories', 'fanfiction-manager' ) . '">';
		$output .= '<h3>' . esc_html__( 'Top Rated Stories', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-story-cards">';

		foreach ( $stories as $story_data ) {
			$story_id = $story_data['story_id'];
			$rating = $story_data['rating'];
			$count = $story_data['count'];

			$story = get_post( $story_id );
			if ( ! $story ) {
				continue;
			}

			$output .= self::render_story_card( $story, $rating, $count );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Recently rated stories shortcode
	 *
	 * [recently-rated-stories limit="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Recently rated stories list HTML.
	 */
	public static function recently_rated_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit' => 10,
			),
			'recently-rated-stories'
		);

		$story_ids = Fanfic_Interactions::get_recently_rated_stories( absint( $atts['limit'] ) );

		if ( empty( $story_ids ) ) {
			return '<div class="fanfic-recently-rated fanfic-empty-state"><p>' . esc_html__( 'No recently rated stories.', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-recently-rated">';
		$output .= '<h3>' . esc_html__( 'Recently Rated', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-story-cards">';

		foreach ( $story_ids as $story_id ) {
			$story = get_post( $story_id );
			if ( ! $story ) {
				continue;
			}

			$rating_data = Fanfic_Interactions::get_story_rating( $story_id );
			$rating = $rating_data ? $rating_data->average_rating : 0;
			$count = $rating_data ? $rating_data->total_votes : 0;

			$output .= self::render_story_card( $story, $rating, $count );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Story follow button shortcode
	 *
	 * [story-follow-button story_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Follow button HTML.
	 */
	public static function story_follow_button( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-follow-button'
		);

		$story_id = absint( $atts['story_id'] );

		if ( ! $story_id ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		$is_logged_in = is_user_logged_in();
		$user_id = get_current_user_id();
		$is_followed = false;

		if ( $is_logged_in ) {
			$is_followed = Fanfic_Follows::is_followed( $user_id, $story_id );
		}

		$follow_class = $is_followed ? 'fanfic-button-followed' : 'not-followed';
		$follow_text = $is_followed
			? esc_html__( 'Followed', 'fanfiction-manager' )
			: esc_html__( 'Follow', 'fanfiction-manager' );

		return sprintf(
			'<button type="button" class="fanfic-button fanfic-button-follow fanfic-follow-button %s" data-post-id="%d" data-story-id="%d" data-chapter-id="0" data-follow-text="%s" data-followed-text="%s" data-action="%s">
				<span class="fanfic-icon">%s</span>
				<span class="fanfic-text follow-text">%s</span>
			</button>',
			esc_attr( $follow_class ),
			absint( $story_id ),
			absint( $story_id ),
			esc_attr__( 'Follow', 'fanfiction-manager' ),
			esc_attr__( 'Followed', 'fanfiction-manager' ),
			$is_followed ? 'unfollow' : 'follow',
			$is_followed ? '&#9733;' : '&#9734;',
			$follow_text
		);
	}

	/**
	 * Story follow count shortcode
	 *
	 * [story-follow-count story_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Follow count HTML.
	 */
	public static function story_follow_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-follow-count'
		);

		$story_id = absint( $atts['story_id'] );

		if ( ! $story_id ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		$count = Fanfic_Follows::get_follow_count( $story_id );

		return sprintf(
			'<span class="fanfic-follow-count" data-story-id="%d" data-count="%d">%s %s</span>',
			$story_id,
			$count,
			esc_html( number_format_i18n( $count ) ),
			esc_html( _n( 'follow', 'follows', $count, 'fanfiction-manager' ) )
		);
	}

	/**
	 * Most followed stories shortcode
	 *
	 * [most-followed-stories limit="10" timeframe="all-time"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Most followed stories list HTML.
	 */
	public static function most_followed_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'         => 10,
				'min_follows' => 1,
				'timeframe'     => 'all-time',
			),
			'most-followed-stories'
		);

		// Build transient cache key including timeframe
		$cache_key = 'fanfic_most_followed_' . absint( $atts['limit'] ) . '_' . sanitize_key( $atts['timeframe'] );
		$stories = get_transient( $cache_key );

		if ( false === $stories ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fanfic_interactions';

			// Calculate date filter based on timeframe
			$date_filter = '';
			$timeframe = sanitize_key( $atts['timeframe'] );

			if ( 'all-time' !== $timeframe ) {
				$date_threshold = self::get_date_threshold( $timeframe );
				if ( $date_threshold ) {
					$date_filter = $wpdb->prepare( ' AND b.created_at >= %s', $date_threshold );
				}
			}

			// Query for most followed stories with optional timeframe filter
			$query = $wpdb->prepare(
				"SELECT b.chapter_id AS story_id, COUNT(b.id) as follow_count
				FROM {$table_name} b
				INNER JOIN {$wpdb->posts} p ON b.chapter_id = p.ID
				WHERE b.interaction_type = 'follow'
				AND p.post_type = 'fanfiction_story'
				AND p.post_status = 'publish'
				{$date_filter}
				GROUP BY b.chapter_id
				HAVING follow_count >= %d
				ORDER BY follow_count DESC
				LIMIT %d",
				absint( $atts['min_follows'] ),
				absint( $atts['limit'] )
			);

			$stories = $wpdb->get_results( $query );

			// Cache for 1 hour
			set_transient( $cache_key, $stories, HOUR_IN_SECONDS );
		}

		if ( empty( $stories ) ) {
			return '<div class="fanfic-most-followed fanfic-empty-state"><p>' . esc_html__( 'No followed stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build timeframe heading
		$timeframe_label = self::get_timeframe_label( $atts['timeframe'] );
		$heading = sprintf(
			/* translators: %s: timeframe label */
			esc_html__( 'Most Followed Stories %s', 'fanfiction-manager' ),
			$timeframe_label
		);

		$output = '<div class="fanfic-most-followed" role="region" aria-label="' . esc_attr( $heading ) . '">';
		$output .= '<h3>' . esc_html( $heading ) . '</h3>';
		$output .= '<div class="fanfic-story-cards">';

		foreach ( $stories as $story_data ) {
			$story_id = $story_data->story_id;
			$follow_count = $story_data->follow_count;

			$story = get_post( $story_id );
			if ( ! $story ) {
				continue;
			}

			// Get featured image if available
			$thumbnail = '';
			if ( has_post_thumbnail( $story_id ) ) {
				$thumbnail = get_the_post_thumbnail( $story_id, 'thumbnail', array( 'loading' => 'lazy' ) );
			}

			$output .= self::render_story_card_with_thumbnail( $story, null, null, $follow_count, null, $thumbnail );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Story view count shortcode
	 *
	 * [story-view-count story_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string View count HTML.
	 */
	public static function story_view_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-view-count'
		);

		$story_id = absint( $atts['story_id'] );

		if ( ! $story_id ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		$count = Fanfic_Interactions::get_story_views( $story_id );

		return sprintf(
			'<span class="fanfic-view-count" data-story-id="%d" data-count="%d">%s %s</span>',
			$story_id,
			$count,
			esc_html( number_format_i18n( $count ) ),
			esc_html( _n( 'view', 'views', $count, 'fanfiction-manager' ) )
		);
	}

	/**
	 * Chapter view count shortcode
	 *
	 * [chapter-view-count chapter_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string View count HTML.
	 */
	public static function chapter_view_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'chapter_id' => 0,
			),
			'chapter-view-count'
		);

		$chapter_id = absint( $atts['chapter_id'] );

		if ( ! $chapter_id ) {
			$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();
		}

		if ( ! $chapter_id ) {
			return '';
		}

		$count = Fanfic_Interactions::get_chapter_views( $chapter_id );

		return sprintf(
			'<span class="fanfic-view-count" data-chapter-id="%d" data-count="%d">%s %s</span>',
			$chapter_id,
			$count,
			esc_html( number_format_i18n( $count ) ),
			esc_html( _n( 'view', 'views', $count, 'fanfiction-manager' ) )
		);
	}

	/**
	 * Most viewed stories shortcode
	 *
	 * [most-viewed-stories limit="10" min_views="100" period="total"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Most viewed stories list HTML.
	 */
	public static function most_viewed_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'     => 10,
				'min_views' => 1,
				'period'    => 'total',
			),
			'most-viewed-stories'
		);

		$stories = Fanfic_Interactions::get_most_viewed_stories( absint( $atts['limit'] ), sanitize_key( $atts['period'] ) );
		$stories = array_values(
			array_filter(
				(array) $stories,
				function( $story_data ) use ( $atts ) {
					return isset( $story_data['views'] ) && absint( $story_data['views'] ) >= absint( $atts['min_views'] );
				}
			)
		);

		if ( empty( $stories ) ) {
			return '<div class="fanfic-most-viewed fanfic-empty-state"><p>' . esc_html__( 'No viewed stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-most-viewed">';
		$output .= '<h3>' . esc_html__( 'Most Viewed Stories', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-story-cards">';

		foreach ( $stories as $story_data ) {
			$story_id = $story_data['story_id'];
			$views = $story_data['views'];

			$story = get_post( $story_id );
			if ( ! $story ) {
				continue;
			}

			$output .= self::render_story_card( $story, null, null, null, $views );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Trending stories shortcode
	 *
	 * [trending-stories limit="10" period="week"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Trending stories list HTML.
	 */
	public static function trending_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'  => 10,
				'period' => 'week',
			),
			'trending-stories'
		);

		$story_ids = Fanfic_Interactions::get_trending_stories( absint( $atts['limit'] ), sanitize_key( $atts['period'] ) );

		if ( empty( $story_ids ) ) {
			return '<div class="fanfic-trending fanfic-empty-state"><p>' . esc_html__( 'No trending stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-trending">';
		$output .= '<h3>' . esc_html__( 'Trending Stories', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-story-cards">';

		foreach ( $story_ids as $story_id ) {
			$story = get_post( $story_id );
			if ( ! $story ) {
				continue;
			}

			$views = Fanfic_Interactions::get_story_views( $story_id );
			$output .= self::render_story_card( $story, null, null, null, $views );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Author statistics shortcode
	 *
	 * [author-stats author_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Author stats HTML.
	 */
	public static function author_stats( $atts ) {
		global $post;

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id' => 0,
			),
			'author-stats'
		);

		$author_id = absint( $atts['author_id'] );

		if ( ! $author_id && $post ) {
			$author_id = absint( $post->post_author );
		}

		if ( ! $author_id ) {
			return '';
		}

		$author = get_userdata( $author_id );
		if ( ! $author ) {
			return '';
		}

		// Cache entire stats object together (15 minute cache to avoid N+1 queries)
		$cache_key = Fanfic_Cache::get_key( 'user', 'statistics', $author_id );
		$stats = Fanfic_Cache::get(
			$cache_key,
			function() use ( $author_id ) {
				// Get story count
				$story_count = count_user_posts( $author_id, 'fanfiction_story' );

				// Get chapter count
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'author'         => $author_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );
				$chapter_count = count( $chapters );

				// Get total views
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $author_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_views = 0;
				foreach ( $stories as $story_id ) {
					$total_views += Fanfic_Interactions::get_story_views( $story_id );
				}

				// Return all stats as an object
				return array(
					'story_count'   => $story_count,
					'chapter_count' => $chapter_count,
					'total_views'   => $total_views,
				);
			},
			Fanfic_Cache::MEDIUM
		);

		// Extract stats from cached object
		$story_count = $stats['story_count'];
		$chapter_count = $stats['chapter_count'];
		$total_views = $stats['total_views'];

		$output = '<div class="fanfic-author-stats" role="region" aria-label="' . esc_attr__( 'Author statistics', 'fanfiction-manager' ) . '">';
		$output .= '<h3>' . esc_html__( 'Author Statistics', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-stats-grid">';

		$output .= '<div class="fanfic-stat-item">';
		$output .= '<span class="fanfic-stat-value">' . esc_html( number_format_i18n( $story_count ) ) . '</span>';
		$output .= '<span class="fanfic-stat-label">' . esc_html( _n( 'Story', 'Stories', $story_count, 'fanfiction-manager' ) ) . '</span>';
		$output .= '</div>';

		$output .= '<div class="fanfic-stat-item">';
		$output .= '<span class="fanfic-stat-value">' . esc_html( number_format_i18n( $chapter_count ) ) . '</span>';
		$output .= '<span class="fanfic-stat-label">' . esc_html( _n( 'Chapter', 'Chapters', $chapter_count, 'fanfiction-manager' ) ) . '</span>';
		$output .= '</div>';

		$output .= '<div class="fanfic-stat-item">';
		$output .= '<span class="fanfic-stat-value">' . esc_html( number_format_i18n( $total_views ) ) . '</span>';
		$output .= '<span class="fanfic-stat-label">' . esc_html( _n( 'View', 'Views', $total_views, 'fanfiction-manager' ) ) . '</span>';
		$output .= '</div>';

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render story card helper
	 *
	 * @since 1.0.0
	 * @param WP_Post $story          Story post object.
	 * @param float   $rating         Story rating (optional).
	 * @param int     $rating_count   Rating count (optional).
	 * @param int     $follow_count Follow count (optional).
	 * @param int     $views          View count (optional).
	 * @return string Story card HTML.
	 */
	private static function render_story_card( $story, $rating = null, $rating_count = null, $follow_count = null, $views = null ) {
		$story_url = get_permalink( $story->ID );
		$author_id = $story->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = fanfic_get_user_profile_url( $author_id );

		$output = '<div class="fanfic-story-card">';
		$output .= '<h4><a href="' . esc_url( $story_url ) . '">' . esc_html( $story->post_title ) . '</a></h4>';
		$output .= '<p class="fanfic-author">by <a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a></p>';

		$output .= '<div class="fanfic-story-meta">';

		if ( null !== $rating ) {
			$output .= '<span class="fanfic-meta-item">';
			$output .= Fanfic_Interactions::get_stars_html( $rating, false, 'small' );
			$output .= ' ' . esc_html( number_format_i18n( $rating, 1 ) );
			if ( null !== $rating_count ) {
				$output .= ' (' . esc_html( number_format_i18n( $rating_count ) ) . ')';
			}
			$output .= '</span>';
		}

		if ( null !== $follow_count ) {
			$output .= '<span class="fanfic-meta-item">&#9733; ' . esc_html( number_format_i18n( $follow_count ) ) . ' ' . esc_html( _n( 'follow', 'follows', $follow_count, 'fanfiction-manager' ) ) . '</span>';
		}

		if ( null !== $views ) {
			$output .= '<span class="fanfic-meta-item">&#128065; ' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html( _n( 'view', 'views', $views, 'fanfiction-manager' ) ) . '</span>';
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render story card with thumbnail helper
	 *
	 * @since 1.0.0
	 * @param WP_Post $story          Story post object.
	 * @param float   $rating         Story rating (optional).
	 * @param int     $rating_count   Rating count (optional).
	 * @param int     $follow_count Follow count (optional).
	 * @param int     $views          View count (optional).
	 * @param string  $thumbnail      Thumbnail HTML (optional).
	 * @return string Story card HTML.
	 */
	private static function render_story_card_with_thumbnail( $story, $rating = null, $rating_count = null, $follow_count = null, $views = null, $thumbnail = '' ) {
		$story_url = get_permalink( $story->ID );
		$author_id = $story->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = fanfic_get_user_profile_url( $author_id );

		$output = '<div class="fanfic-story-card">';

		// Add thumbnail if available
		if ( ! empty( $thumbnail ) ) {
			$output .= '<div class="fanfic-story-thumbnail"><a href="' . esc_url( $story_url ) . '">' . $thumbnail . '</a></div>';
		}

		$output .= '<div class="fanfic-story-content">';
		$output .= '<h4><a href="' . esc_url( $story_url ) . '">' . esc_html( $story->post_title ) . '</a></h4>';
		$output .= '<p class="fanfic-author">by <a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a></p>';

		$output .= '<div class="fanfic-story-meta">';

		if ( null !== $rating ) {
			$output .= '<span class="fanfic-meta-item">';
			$output .= Fanfic_Interactions::get_stars_html( $rating, false, 'small' );
			$output .= ' ' . esc_html( number_format_i18n( $rating, 1 ) );
			if ( null !== $rating_count ) {
				$output .= ' (' . esc_html( number_format_i18n( $rating_count ) ) . ')';
			}
			$output .= '</span>';
		}

		if ( null !== $follow_count ) {
			$output .= '<span class="fanfic-meta-item">&#9733; ' . esc_html( number_format_i18n( $follow_count ) ) . ' ' . esc_html( _n( 'follow', 'follows', $follow_count, 'fanfiction-manager' ) ) . '</span>';
		}

		if ( null !== $views ) {
			$output .= '<span class="fanfic-meta-item">&#128065; ' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html( _n( 'view', 'views', $views, 'fanfiction-manager' ) ) . '</span>';
		}

		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render most followed stories (direct call helper)
	 *
	 * Helper method for direct template calls without shortcode processing overhead.
	 *
	 * @since 1.0.0
	 * @param array $args Arguments array.
	 * @return string HTML output.
	 */
	public static function render_most_followed( $args = array() ) {
		$defaults = array(
			'limit'         => 5,
			'timeframe'     => 'week',
			'min_follows' => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		// Call the existing shortcode method directly
		return self::most_followed_stories( $args );
	}

	/**
	 * Get date threshold for timeframe filtering
	 *
	 * @since 1.0.0
	 * @param string $timeframe Timeframe (week, month, year, all-time).
	 * @return string|false MySQL datetime string or false if invalid timeframe.
	 */
	private static function get_date_threshold( $timeframe ) {
		$timeframe = sanitize_key( $timeframe );

		switch ( $timeframe ) {
			case 'week':
				return date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
			case 'month':
				return date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
			case 'year':
				return date( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
			case 'all-time':
			default:
				return false;
		}
	}

	/**
	 * Get human-readable label for timeframe
	 *
	 * @since 1.0.0
	 * @param string $timeframe Timeframe (week, month, year, all-time).
	 * @return string Human-readable timeframe label.
	 */
	private static function get_timeframe_label( $timeframe ) {
		$timeframe = sanitize_key( $timeframe );

		switch ( $timeframe ) {
			case 'week':
				return esc_html__( '(This Week)', 'fanfiction-manager' );
			case 'month':
				return esc_html__( '(This Month)', 'fanfiction-manager' );
			case 'year':
				return esc_html__( '(This Year)', 'fanfiction-manager' );
			case 'all-time':
			default:
				return esc_html__( '(All Time)', 'fanfiction-manager' );
		}
	}

	/**
	 * Story like count shortcode (NEW v2.0)
	 *
	 * [fanfiction-story-like-count id="123"]
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Like count HTML.
	 */
	public static function story_like_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'id' => 0,
			),
			'fanfiction-story-like-count'
		);

		$story_id = absint( $atts['id'] );

		// Auto-detect story ID from context if not provided
		if ( ! $story_id ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		// Get like count from Like System
		$count = Fanfic_Interactions::get_story_likes( $story_id );

		// Return empty string if no likes
		if ( $count === 0 ) {
			return '';
		}

		// Use _n() for proper translation (singular/plural)
		return sprintf(
			'<span class="fanfic-like-count" data-story-id="%d" data-count="%d">%s</span>',
			$story_id,
			$count,
			sprintf(
				esc_html( _n( '%d like', '%d likes', $count, 'fanfiction-manager' ) ),
				number_format_i18n( $count )
			)
		);
	}

	/**
	 * Story rating shortcode
	 *
	 * [fanfiction-story-rating]
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes (ignored).
	 * @return string Rating HTML.
	 */
	public static function story_rating( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		// Get rating data from Rating System
		$rating_data = Fanfic_Interactions::get_story_rating( $story_id );

		// Return "Not rated" if no ratings
		if ( ! $rating_data || $rating_data->total_votes === 0 ) {
			return '<span class="fanfic-rating-compact fanfic-no-rating">' . esc_html__( 'Not rated', 'fanfiction-manager' ) . '</span>';
		}

		$rating = $rating_data->average_rating;
		$count = $rating_data->total_votes;

		return sprintf(
			'<span class="fanfic-rating-compact fanfic-rating-short"><span class="fanfic-rating-value">%s</span><span class="fanfic-rating-star">&#9733;</span><span class="fanfic-rating-count">(%s)</span></span>',
			esc_html( number_format_i18n( $rating, 2 ) ),
			esc_html( number_format_i18n( $count ) )
		);
	}
}
