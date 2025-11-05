<?php
/**
 * Statistics Shortcodes Class
 *
 * Handles all statistics display shortcodes (ratings, bookmarks, views, trending).
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

		// Bookmark shortcodes
		add_shortcode( 'story-bookmark-button', array( __CLASS__, 'story_bookmark_button' ) );
		add_shortcode( 'story-bookmark-count', array( __CLASS__, 'story_bookmark_count' ) );
		add_shortcode( 'most-bookmarked-stories', array( __CLASS__, 'most_bookmarked_stories' ) );

		// Follow shortcodes
		add_shortcode( 'author-follow-button', array( __CLASS__, 'author_follow_button' ) );
		add_shortcode( 'author-follower-count', array( __CLASS__, 'author_follower_count' ) );
		add_shortcode( 'top-authors', array( __CLASS__, 'top_authors' ) );
		add_shortcode( 'most-followed-authors', array( __CLASS__, 'most_followed_authors' ) );

		// View shortcodes
		add_shortcode( 'story-view-count', array( __CLASS__, 'story_view_count' ) );
		add_shortcode( 'chapter-view-count', array( __CLASS__, 'chapter_view_count' ) );
		add_shortcode( 'most-viewed-stories', array( __CLASS__, 'most_viewed_stories' ) );

		// Trending shortcode
		add_shortcode( 'trending-stories', array( __CLASS__, 'trending_stories' ) );

		// Author stats shortcode
		add_shortcode( 'author-stats', array( __CLASS__, 'author_stats' ) );
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

		$rating = Fanfic_Ratings::get_story_rating( $story_id );
		$count = Fanfic_Ratings::get_story_rating_count( $story_id );

		if ( $count === 0 ) {
			return '<div class="fanfic-story-rating-display fanfic-no-ratings"><p>' . esc_html__( 'No ratings yet', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-story-rating-display">';
		$output .= Fanfic_Ratings::get_stars_html( $rating, false, $atts['size'] );
		$output .= '<div class="fanfic-rating-info">';
		$output .= '<span class="fanfic-rating-average">' . esc_html( number_format_i18n( $rating, 1 ) ) . '</span>';
		$output .= '<span class="fanfic-rating-count">';
		$output .= sprintf(
			/* translators: %s: number of ratings */
			esc_html( _n( '(%s rating)', '(%s ratings)', $count, 'fanfiction-manager' ) ),
			esc_html( number_format_i18n( $count ) )
		);
		$output .= '</span>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Top rated stories shortcode
	 *
	 * [top-rated-stories limit="10" min_ratings="5"]
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
			),
			'top-rated-stories'
		);

		$stories = Fanfic_Ratings::get_top_rated_stories( absint( $atts['limit'] ), absint( $atts['min_ratings'] ) );

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

		$story_ids = Fanfic_Ratings::get_recently_rated_stories( absint( $atts['limit'] ) );

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

			$rating = Fanfic_Ratings::get_story_rating( $story_id );
			$count = Fanfic_Ratings::get_story_rating_count( $story_id );

			$output .= self::render_story_card( $story, $rating, $count );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Story bookmark button shortcode
	 *
	 * [story-bookmark-button story_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Bookmark button HTML.
	 */
	public static function story_bookmark_button( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-bookmark-button'
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
		$is_bookmarked = false;

		if ( $is_logged_in ) {
			$is_bookmarked = Fanfic_Bookmarks::is_bookmarked( $story_id, $user_id );
		}

		$bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
		$bookmark_text = $is_bookmarked
			? esc_html__( 'Bookmarked', 'fanfiction-manager' )
			: esc_html__( 'Bookmark', 'fanfiction-manager' );

		return sprintf(
			'<button class="fanfic-bookmark-btn %s" data-story-id="%d" data-action="%s">
				<span class="fanfic-icon">%s</span>
				<span class="fanfic-text">%s</span>
			</button>',
			esc_attr( $bookmark_class ),
			absint( $story_id ),
			$is_bookmarked ? 'unbookmark' : 'bookmark',
			$is_bookmarked ? '&#9733;' : '&#9734;',
			$bookmark_text
		);
	}

	/**
	 * Story bookmark count shortcode
	 *
	 * [story-bookmark-count story_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Bookmark count HTML.
	 */
	public static function story_bookmark_count( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-bookmark-count'
		);

		$story_id = absint( $atts['story_id'] );

		if ( ! $story_id ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		$count = Fanfic_Bookmarks::get_bookmark_count( $story_id );

		return sprintf(
			'<span class="fanfic-bookmark-count">%s %s</span>',
			esc_html( number_format_i18n( $count ) ),
			esc_html( _n( 'bookmark', 'bookmarks', $count, 'fanfiction-manager' ) )
		);
	}

	/**
	 * Most bookmarked stories shortcode
	 *
	 * [most-bookmarked-stories limit="10" timeframe="all-time"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Most bookmarked stories list HTML.
	 */
	public static function most_bookmarked_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'         => 10,
				'min_bookmarks' => 1,
				'timeframe'     => 'all-time',
			),
			'most-bookmarked-stories'
		);

		// Build transient cache key including timeframe
		$cache_key = 'fanfic_most_bookmarked_' . absint( $atts['limit'] ) . '_' . sanitize_key( $atts['timeframe'] );
		$stories = get_transient( $cache_key );

		if ( false === $stories ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fanfic_bookmarks';

			// Calculate date filter based on timeframe
			$date_filter = '';
			$timeframe = sanitize_key( $atts['timeframe'] );

			if ( 'all-time' !== $timeframe ) {
				$date_threshold = self::get_date_threshold( $timeframe );
				if ( $date_threshold ) {
					$date_filter = $wpdb->prepare( ' AND b.created_at >= %s', $date_threshold );
				}
			}

			// Query for most bookmarked stories with optional timeframe filter
			$query = $wpdb->prepare(
				"SELECT b.story_id, COUNT(b.id) as bookmark_count
				FROM {$table_name} b
				INNER JOIN {$wpdb->posts} p ON b.story_id = p.ID
				WHERE p.post_type = 'fanfiction_story'
				AND p.post_status = 'publish'
				{$date_filter}
				GROUP BY b.story_id
				HAVING bookmark_count >= %d
				ORDER BY bookmark_count DESC
				LIMIT %d",
				absint( $atts['min_bookmarks'] ),
				absint( $atts['limit'] )
			);

			$stories = $wpdb->get_results( $query );

			// Cache for 1 hour
			set_transient( $cache_key, $stories, HOUR_IN_SECONDS );
		}

		if ( empty( $stories ) ) {
			return '<div class="fanfic-most-bookmarked fanfic-empty-state"><p>' . esc_html__( 'No bookmarked stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build timeframe heading
		$timeframe_label = self::get_timeframe_label( $atts['timeframe'] );
		$heading = sprintf(
			/* translators: %s: timeframe label */
			esc_html__( 'Most Bookmarked Stories %s', 'fanfiction-manager' ),
			$timeframe_label
		);

		$output = '<div class="fanfic-most-bookmarked" role="region" aria-label="' . esc_attr( $heading ) . '">';
		$output .= '<h3>' . esc_html( $heading ) . '</h3>';
		$output .= '<div class="fanfic-story-cards">';

		foreach ( $stories as $story_data ) {
			$story_id = $story_data->story_id;
			$bookmark_count = $story_data->bookmark_count;

			$story = get_post( $story_id );
			if ( ! $story ) {
				continue;
			}

			// Get featured image if available
			$thumbnail = '';
			if ( has_post_thumbnail( $story_id ) ) {
				$thumbnail = get_the_post_thumbnail( $story_id, 'thumbnail', array( 'loading' => 'lazy' ) );
			}

			$output .= self::render_story_card_with_thumbnail( $story, null, null, $bookmark_count, null, $thumbnail );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Author follow button shortcode
	 *
	 * [author-follow-button author_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Follow button HTML.
	 */
	public static function author_follow_button( $atts ) {
		global $post;

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id' => 0,
			),
			'author-follow-button'
		);

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
			$is_following = Fanfic_Follows::is_following( $author_id, $user_id );
		}

		$follow_class = $is_following ? 'following' : 'not-following';
		$follow_text = $is_following
			? esc_html__( 'Following', 'fanfiction-manager' )
			: esc_html__( 'Follow', 'fanfiction-manager' );

		return sprintf(
			'<button class="fanfic-follow-btn %s" data-author-id="%d" data-action="%s">
				<span class="fanfic-icon">%s</span>
				<span class="fanfic-text">%s</span>
			</button>',
			esc_attr( $follow_class ),
			absint( $author_id ),
			$is_following ? 'unfollow' : 'follow',
			$is_following ? '&#10003;' : '&#43;',
			$follow_text
		);
	}

	/**
	 * Author follower count shortcode
	 *
	 * [author-follower-count author_id="123"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Follower count HTML.
	 */
	public static function author_follower_count( $atts ) {
		global $post;

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id' => 0,
			),
			'author-follower-count'
		);

		$author_id = absint( $atts['author_id'] );

		if ( ! $author_id && $post ) {
			$author_id = absint( $post->post_author );
		}

		if ( ! $author_id ) {
			return '';
		}

		$count = Fanfic_Follows::get_follower_count( $author_id );

		return sprintf(
			'<span class="fanfic-follower-count">%s %s</span>',
			esc_html( number_format_i18n( $count ) ),
			esc_html( _n( 'follower', 'followers', $count, 'fanfiction-manager' ) )
		);
	}

	/**
	 * Top authors shortcode
	 *
	 * [top-authors limit="10" min_followers="5"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Top authors list HTML.
	 */
	public static function top_authors( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'         => 10,
				'min_followers' => 5,
			),
			'top-authors'
		);

		$authors = Fanfic_Follows::get_top_authors( absint( $atts['limit'] ), absint( $atts['min_followers'] ) );

		if ( empty( $authors ) ) {
			return '<div class="fanfic-top-authors fanfic-empty-state"><p>' . esc_html__( 'No authors found.', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-top-authors">';
		$output .= '<h3>' . esc_html__( 'Top Authors', 'fanfiction-manager' ) . '</h3>';
		$output .= '<div class="fanfic-author-cards">';

		foreach ( $authors as $author_data ) {
			$author_id = $author_data->author_id;
			$follower_count = $author_data->follower_count;

			$author = get_userdata( $author_id );
			if ( ! $author ) {
				continue;
			}

			$output .= self::render_author_card( $author, $follower_count );
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Most followed authors shortcode with timeframe support
	 *
	 * [most-followed-authors limit="10" timeframe="all-time"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Most followed authors list HTML.
	 */
	public static function most_followed_authors( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit'         => 10,
				'min_followers' => 1,
				'timeframe'     => 'all-time',
			),
			'most-followed-authors'
		);

		// Build transient cache key including timeframe
		$cache_key = 'fanfic_most_followed_' . absint( $atts['limit'] ) . '_' . sanitize_key( $atts['timeframe'] );
		$authors = get_transient( $cache_key );

		if ( false === $authors ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fanfic_follows';

			// Calculate date filter based on timeframe
			$date_filter = '';
			$timeframe = sanitize_key( $atts['timeframe'] );

			if ( 'all-time' !== $timeframe ) {
				$date_threshold = self::get_date_threshold( $timeframe );
				if ( $date_threshold ) {
					$date_filter = $wpdb->prepare( ' AND f.created_at >= %s', $date_threshold );
				}
			}

			// Query for most followed authors with optional timeframe filter
			$query = $wpdb->prepare(
				"SELECT f.author_id, COUNT(f.id) as follower_count
				FROM {$table_name} f
				INNER JOIN {$wpdb->users} u ON f.author_id = u.ID
				WHERE 1=1
				{$date_filter}
				GROUP BY f.author_id
				HAVING follower_count >= %d
				ORDER BY follower_count DESC
				LIMIT %d",
				absint( $atts['min_followers'] ),
				absint( $atts['limit'] )
			);

			$authors = $wpdb->get_results( $query );

			// Cache for 1 hour
			set_transient( $cache_key, $authors, HOUR_IN_SECONDS );
		}

		if ( empty( $authors ) ) {
			return '<div class="fanfic-most-followed-authors fanfic-empty-state"><p>' . esc_html__( 'No authors found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build timeframe heading
		$timeframe_label = self::get_timeframe_label( $atts['timeframe'] );
		$heading = sprintf(
			/* translators: %s: timeframe label */
			esc_html__( 'Most Followed Authors %s', 'fanfiction-manager' ),
			$timeframe_label
		);

		$output = '<div class="fanfic-most-followed-authors" role="region" aria-label="' . esc_attr( $heading ) . '">';
		$output .= '<h3>' . esc_html( $heading ) . '</h3>';
		$output .= '<div class="fanfic-author-cards">';

		foreach ( $authors as $author_data ) {
			$author_id = $author_data->author_id;
			$follower_count = $author_data->follower_count;

			$author = get_userdata( $author_id );
			if ( ! $author ) {
				continue;
			}

			// Get story count for this author
			$story_count = count_user_posts( $author_id, 'fanfiction_story' );

			$output .= self::render_author_card_with_story_count( $author, $follower_count, $story_count );
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

		$count = Fanfic_Views::get_story_views( $story_id );

		return sprintf(
			'<span class="fanfic-view-count">%s %s</span>',
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

		$count = Fanfic_Views::get_chapter_views( $chapter_id );

		return sprintf(
			'<span class="fanfic-view-count">%s %s</span>',
			esc_html( number_format_i18n( $count ) ),
			esc_html( _n( 'view', 'views', $count, 'fanfiction-manager' ) )
		);
	}

	/**
	 * Most viewed stories shortcode
	 *
	 * [most-viewed-stories limit="10" min_views="100"]
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
			),
			'most-viewed-stories'
		);

		$stories = Fanfic_Views::get_most_viewed_stories( absint( $atts['limit'] ), absint( $atts['min_views'] ) );

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
	 * [trending-stories limit="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Trending stories list HTML.
	 */
	public static function trending_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'limit' => 10,
			),
			'trending-stories'
		);

		$story_ids = Fanfic_Views::get_trending_stories( absint( $atts['limit'] ) );

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

			$views = Fanfic_Views::get_story_views( $story_id );
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

				// Get follower count
				$follower_count = Fanfic_Follows::get_follower_count( $author_id );

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
					$total_views += Fanfic_Views::get_story_views( $story_id );
				}

				// Return all stats as an object
				return array(
					'story_count'    => $story_count,
					'chapter_count'  => $chapter_count,
					'follower_count' => $follower_count,
					'total_views'    => $total_views,
				);
			},
			Fanfic_Cache::MEDIUM
		);

		// Extract stats from cached object
		$story_count = $stats['story_count'];
		$chapter_count = $stats['chapter_count'];
		$follower_count = $stats['follower_count'];
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
		$output .= '<span class="fanfic-stat-value">' . esc_html( number_format_i18n( $follower_count ) ) . '</span>';
		$output .= '<span class="fanfic-stat-label">' . esc_html( _n( 'Follower', 'Followers', $follower_count, 'fanfiction-manager' ) ) . '</span>';
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
	 * @param int     $bookmark_count Bookmark count (optional).
	 * @param int     $views          View count (optional).
	 * @return string Story card HTML.
	 */
	private static function render_story_card( $story, $rating = null, $rating_count = null, $bookmark_count = null, $views = null ) {
		$story_url = get_permalink( $story->ID );
		$author_id = $story->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = get_author_posts_url( $author_id );

		$output = '<div class="fanfic-story-card">';
		$output .= '<h4><a href="' . esc_url( $story_url ) . '">' . esc_html( $story->post_title ) . '</a></h4>';
		$output .= '<p class="fanfic-author">by <a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a></p>';

		$output .= '<div class="fanfic-story-meta">';

		if ( null !== $rating ) {
			$output .= '<span class="fanfic-meta-item">';
			$output .= Fanfic_Ratings::get_stars_html( $rating, false, 'small' );
			$output .= ' ' . esc_html( number_format_i18n( $rating, 1 ) );
			if ( null !== $rating_count ) {
				$output .= ' (' . esc_html( number_format_i18n( $rating_count ) ) . ')';
			}
			$output .= '</span>';
		}

		if ( null !== $bookmark_count ) {
			$output .= '<span class="fanfic-meta-item">&#9733; ' . esc_html( number_format_i18n( $bookmark_count ) ) . ' ' . esc_html( _n( 'bookmark', 'bookmarks', $bookmark_count, 'fanfiction-manager' ) ) . '</span>';
		}

		if ( null !== $views ) {
			$output .= '<span class="fanfic-meta-item">&#128065; ' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html( _n( 'view', 'views', $views, 'fanfiction-manager' ) ) . '</span>';
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render author card helper
	 *
	 * @since 1.0.0
	 * @param WP_User $author         Author user object.
	 * @param int     $follower_count Follower count.
	 * @return string Author card HTML.
	 */
	private static function render_author_card( $author, $follower_count ) {
		$author_url = get_author_posts_url( $author->ID );
		$story_count = count_user_posts( $author->ID, 'fanfiction_story' );

		$output = '<div class="fanfic-author-card">';
		// Add lazy loading to avatar for performance optimization
		$output .= '<div class="fanfic-author-avatar">' . get_avatar( $author->ID, 64, '', '', array( 'loading' => 'lazy' ) ) . '</div>';
		$output .= '<div class="fanfic-author-info">';
		$output .= '<h4><a href="' . esc_url( $author_url ) . '">' . esc_html( $author->display_name ) . '</a></h4>';
		$output .= '<p class="fanfic-author-meta">';
		$output .= esc_html( number_format_i18n( $story_count ) ) . ' ' . esc_html( _n( 'story', 'stories', $story_count, 'fanfiction-manager' ) );
		$output .= ' &middot; ';
		$output .= esc_html( number_format_i18n( $follower_count ) ) . ' ' . esc_html( _n( 'follower', 'followers', $follower_count, 'fanfiction-manager' ) );
		$output .= '</p>';
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
	 * @param int     $bookmark_count Bookmark count (optional).
	 * @param int     $views          View count (optional).
	 * @param string  $thumbnail      Thumbnail HTML (optional).
	 * @return string Story card HTML.
	 */
	private static function render_story_card_with_thumbnail( $story, $rating = null, $rating_count = null, $bookmark_count = null, $views = null, $thumbnail = '' ) {
		$story_url = get_permalink( $story->ID );
		$author_id = $story->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = get_author_posts_url( $author_id );

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
			$output .= Fanfic_Ratings::get_stars_html( $rating, false, 'small' );
			$output .= ' ' . esc_html( number_format_i18n( $rating, 1 ) );
			if ( null !== $rating_count ) {
				$output .= ' (' . esc_html( number_format_i18n( $rating_count ) ) . ')';
			}
			$output .= '</span>';
		}

		if ( null !== $bookmark_count ) {
			$output .= '<span class="fanfic-meta-item">&#9733; ' . esc_html( number_format_i18n( $bookmark_count ) ) . ' ' . esc_html( _n( 'bookmark', 'bookmarks', $bookmark_count, 'fanfiction-manager' ) ) . '</span>';
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
	 * Render author card with story count helper
	 *
	 * @since 1.0.0
	 * @param WP_User $author         Author user object.
	 * @param int     $follower_count Follower count.
	 * @param int     $story_count    Story count.
	 * @return string Author card HTML.
	 */
	private static function render_author_card_with_story_count( $author, $follower_count, $story_count ) {
		$author_url = get_author_posts_url( $author->ID );

		$output = '<div class="fanfic-author-card">';
		// Add lazy loading to avatar for performance optimization
		$output .= '<div class="fanfic-author-avatar">' . get_avatar( $author->ID, 64, '', '', array( 'loading' => 'lazy' ) ) . '</div>';
		$output .= '<div class="fanfic-author-info">';
		$output .= '<h4><a href="' . esc_url( $author_url ) . '">' . esc_html( $author->display_name ) . '</a></h4>';
		$output .= '<p class="fanfic-author-meta">';
		$output .= '<span class="fanfic-meta-item">' . esc_html( number_format_i18n( $story_count ) ) . ' ' . esc_html( _n( 'story', 'stories', $story_count, 'fanfiction-manager' ) ) . '</span>';
		$output .= ' &middot; ';
		$output .= '<span class="fanfic-meta-item">' . esc_html( number_format_i18n( $follower_count ) ) . ' ' . esc_html( _n( 'follower', 'followers', $follower_count, 'fanfiction-manager' ) ) . '</span>';
		$output .= '</p>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
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
}
