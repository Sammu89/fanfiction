<?php
/**
 * Author Shortcodes Class
 *
 * Handles all author-related shortcodes.
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
 * Class Fanfic_Shortcodes_Author
 *
 * Author profile and information shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Author {

	/**
	 * Register author shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'author-display-name', array( __CLASS__, 'author_display_name' ) );
		add_shortcode( 'author-username', array( __CLASS__, 'author_username' ) );
		add_shortcode( 'author-bio', array( __CLASS__, 'author_bio' ) );
		add_shortcode( 'author-avatar', array( __CLASS__, 'author_avatar' ) );
		add_shortcode( 'author-registration-date', array( __CLASS__, 'author_registration_date' ) );
		add_shortcode( 'author-story-count', array( __CLASS__, 'author_story_count' ) );
		add_shortcode( 'author-total-chapters', array( __CLASS__, 'author_total_chapters' ) );
		add_shortcode( 'author-total-words', array( __CLASS__, 'author_total_words' ) );
		add_shortcode( 'author-total-views', array( __CLASS__, 'author_total_views' ) );
		add_shortcode( 'author-average-rating', array( __CLASS__, 'author_average_rating' ) );
		add_shortcode( 'author-story-list', array( __CLASS__, 'author_story_list' ) );
		add_shortcode( 'author-coauthored-stories', array( __CLASS__, 'author_coauthored_stories' ) );
		add_shortcode( 'author-stories-grid', array( __CLASS__, 'author_stories_grid' ) );
		add_shortcode( 'author-completed-stories', array( __CLASS__, 'author_completed_stories' ) );
		add_shortcode( 'author-ongoing-stories', array( __CLASS__, 'author_ongoing_stories' ) );
		add_shortcode( 'author-featured-stories', array( __CLASS__, 'author_featured_stories' ) );
		add_shortcode( 'author-follow-list', array( __CLASS__, 'author_follow_list' ) );
	}

	/**
	 * Get author ID from context
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return int|false Author ID or false.
	 */
	private static function get_author_id( $atts ) {
		// Check if author_id is provided in attributes
		if ( isset( $atts['author_id'] ) && absint( $atts['author_id'] ) ) {
			return absint( $atts['author_id'] );
		}

		// Check if author_username is provided
		if ( isset( $atts['author_username'] ) && ! empty( $atts['author_username'] ) ) {
			$user = get_user_by( 'login', sanitize_text_field( $atts['author_username'] ) );
			if ( $user ) {
				return $user->ID;
			}
		}

		// Check if we're on an author archive
		if ( is_author() ) {
			return get_queried_object_id();
		}

		// Get author from current story/chapter
		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( $story_id ) {
			return get_post_field( 'post_author', $story_id );
		}

		return false;
	}

	/**
	 * Author display name shortcode
	 *
	 * [author-display-name]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Author display name.
	 */
	public static function author_display_name( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		return '<span class="author-display-name">' . esc_html( get_the_author_meta( 'display_name', $author_id ) ) . '</span>';
	}

	/**
	 * Author username shortcode
	 *
	 * [author-username]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Author username.
	 */
	public static function author_username( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		return '<span class="author-username">' . esc_html( get_the_author_meta( 'user_login', $author_id ) ) . '</span>';
	}

	/**
	 * Author bio shortcode
	 *
	 * [author-bio]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Author biography.
	 */
	public static function author_bio( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		$bio = get_the_author_meta( 'description', $author_id );

		if ( empty( $bio ) ) {
			return '<p class="author-no-bio">' . esc_html__( 'No biography available.', 'fanfiction-manager' ) . '</p>';
		}

		return '<div class="author-bio" role="region" aria-label="' . esc_attr__( 'Author biography', 'fanfiction-manager' ) . '">' . wp_kses_post( wpautop( $bio ) ) . '</div>';
	}

	/**
	 * Author avatar shortcode
	 *
	 * [author-avatar]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Avatar HTML.
	 */
	public static function author_avatar( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'size'      => 96,
				'author_id' => 0,
			),
			'author-avatar'
		);

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Add lazy loading attribute for performance optimization
		$avatar_args = array(
			'class'   => 'author-avatar',
			'loading' => 'lazy',
		);

		return get_avatar( $author_id, $atts['size'], '', '', $avatar_args );
	}

	/**
	 * Author registration date shortcode
	 *
	 * [author-registration-date]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Registration date.
	 */
	public static function author_registration_date( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'format'    => get_option( 'date_format' ),
				'author_id' => 0,
			),
			'author-registration-date'
		);

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		$registered = get_the_author_meta( 'user_registered', $author_id );

		if ( empty( $registered ) ) {
			return '';
		}

		$date = mysql2date( $atts['format'], $registered );

		return '<time class="author-registration-date" datetime="' . esc_attr( mysql2date( 'c', $registered ) ) . '">' .
			esc_html( $date ) .
			'</time>';
	}

	/**
	 * Author story count shortcode
	 *
	 * [author-story-count]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story count.
	 */
	public static function author_story_count( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Use cached story count (30 minute cache)
		$cache_key = Fanfic_Cache::get_key( 'user', 'story_count', $author_id );
		$count = Fanfic_Cache::get(
			$cache_key,
			function() use ( $author_id ) {
				return count_user_posts( $author_id, 'fanfiction_story', true );
			},
			Fanfic_Cache::LONG
		);

		return '<span class="author-story-count">' . Fanfic_Shortcodes::format_number( $count ) . '</span>';
	}

	/**
	 * Author total chapters shortcode
	 *
	 * [author-total-chapters]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Total chapter count.
	 */
	public static function author_total_chapters( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Use cached total chapters (15 minute cache - expensive nested query)
		$cache_key = Fanfic_Cache::get_key( 'user', 'total_chapters', $author_id );
		$total_chapters = Fanfic_Cache::get(
			$cache_key,
			function() use ( $author_id ) {
				// Get all stories by author
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $author_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_chapters = 0;

				foreach ( $stories as $story_id ) {
					$chapters = get_posts( array(
						'post_type'      => 'fanfiction_chapter',
						'post_parent'    => $story_id,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					) );
					$total_chapters += count( $chapters );
				}

				return $total_chapters;
			},
			Fanfic_Cache::MEDIUM
		);

		return '<span class="author-total-chapters">' . Fanfic_Shortcodes::format_number( $total_chapters ) . '</span>';
	}

	/**
	 * Author total words shortcode
	 *
	 * [author-total-words]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Total word count.
	 */
	public static function author_total_words( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Use cached total words (15 minute cache - EXTREME bottleneck with triple nested loop)
		$cache_key = Fanfic_Cache::get_key( 'user', 'total_words', $author_id );
		$total_words = Fanfic_Cache::get(
			$cache_key,
			function() use ( $author_id ) {
				// Get all stories by author
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $author_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_words = 0;

				foreach ( $stories as $story_id ) {
					$chapters = get_posts( array(
						'post_type'      => 'fanfiction_chapter',
						'post_parent'    => $story_id,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					) );

					foreach ( $chapters as $chapter_id ) {
						$content = get_post_field( 'post_content', $chapter_id );
						$content = wp_strip_all_tags( $content );
						$word_count = str_word_count( $content );
						$total_words += $word_count;
					}
				}

				return $total_words;
			},
			Fanfic_Cache::MEDIUM
		);

		return '<span class="author-total-words">' . Fanfic_Shortcodes::format_number( $total_words ) . '</span>';
	}

	/**
	 * Author total views shortcode
	 *
	 * [author-total-views]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Total view count.
	 */
	public static function author_total_views( $atts ) {
		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Use cached total views (15 minute cache - expensive nested query)
		$cache_key = Fanfic_Cache::get_key( 'user', 'total_views', $author_id );
		$total_views = Fanfic_Cache::get(
			$cache_key,
			function() use ( $author_id ) {
				// Get all stories by author
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $author_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_views = 0;

				foreach ( $stories as $story_id ) {
					// Use Fanfic_Views class to get story views (which sums chapter views)
					if ( class_exists( 'Fanfic_Views' ) ) {
						$total_views += Fanfic_Views::get_story_views( $story_id );
					}
				}

				return $total_views;
			},
			Fanfic_Cache::MEDIUM
		);

		return '<span class="author-total-views">' . Fanfic_Shortcodes::format_number( $total_views ) . '</span>';
	}

	/**
	 * Author average rating shortcode
	 *
	 * [author-average-rating author_id="5" author_username="john" display="number"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Average rating.
	 */
	public static function author_average_rating( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'display'         => 'number',
			),
			'author-average-rating'
		);

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Use cached average rating (1 hour cache)
		$cache_key = Fanfic_Cache::get_key( 'user', 'average_rating', $author_id );
		$average_rating = Fanfic_Cache::get(
			$cache_key,
			function() use ( $author_id ) {
				global $wpdb;

				// Get all stories by this author
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $author_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				if ( empty( $stories ) ) {
					return 0;
				}

				// Get all chapters for these stories
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent__in' => $stories,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				if ( empty( $chapters ) ) {
					return 0;
				}

				// Get average rating from wp_fanfic_ratings table
				$chapter_ids = implode( ',', array_map( 'absint', $chapters ) );
				$table_name = $wpdb->prefix . 'fanfic_ratings';

				// Check if table exists
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
					return 0;
				}

				$average = $wpdb->get_var(
					"SELECT AVG(rating) FROM $table_name WHERE chapter_id IN ($chapter_ids)"
				);

				return $average ? floatval( $average ) : 0;
			},
			HOUR_IN_SECONDS
		);

		if ( $average_rating <= 0 ) {
			return '';
		}

		// Display as stars or number
		if ( 'stars' === $atts['display'] ) {
			$output = '<span class="author-average-rating author-rating-stars">';
			$full_stars = floor( $average_rating );
			$half_star = ( $average_rating - $full_stars ) >= 0.5;

			for ( $i = 0; $i < $full_stars; $i++ ) {
				$output .= '<span class="star star-full">&#9733;</span>';
			}
			if ( $half_star ) {
				$output .= '<span class="star star-half">&#9733;</span>';
			}
			for ( $i = ceil( $average_rating ); $i < 5; $i++ ) {
				$output .= '<span class="star star-empty">&#9734;</span>';
			}
			$output .= '</span>';
			return $output;
		}

		return '<span class="author-average-rating">' . number_format( $average_rating, 1 ) . '</span>';
	}

	/**
	 * Author story list shortcode
	 *
	 * [author-story-list author_id="5" author_username="john" limit="10" status="ongoing" paginate="true"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story list HTML.
	 */
	public static function author_story_list( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'limit'           => 10,
				'status'          => '',
				'paginate'        => 'true',
			),
			'author-story-list'
		);

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		return self::render_story_list( $author_id, $atts, 'list' );
	}

	/**
	 * Author co-authored stories shortcode.
	 *
	 * [author-coauthored-stories author_id="5" author_username="john" limit="10" paginate="true"]
	 *
	 * @since 1.5.3
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function author_coauthored_stories( $atts ) {
		if ( ! class_exists( 'Fanfic_Coauthors' ) || ! Fanfic_Coauthors::is_enabled() ) {
			return '';
		}

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'limit'           => 10,
				'paginate'        => 'true',
			),
			'author-coauthored-stories'
		);

		$author_id = self::get_author_id( $atts );
		if ( ! $author_id ) {
			return '';
		}

		$coauthored_ids = Fanfic_Coauthors::get_user_coauthored_stories( $author_id, 'accepted' );
		if ( empty( $coauthored_ids ) ) {
			return '';
		}

		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
		$query_args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'post__in'       => array_map( 'absint', $coauthored_ids ),
			'posts_per_page' => absint( $atts['limit'] ),
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$stories = new WP_Query( $query_args );
		if ( ! $stories->have_posts() ) {
			wp_reset_postdata();
			return '';
		}

		$output = '<h2>' . esc_html__( 'Co-Authored Stories', 'fanfiction-manager' ) . '</h2>';
		$output .= '<div class="author-stories-list author-coauthored-stories-list">';
		while ( $stories->have_posts() ) {
			$stories->the_post();
			$output .= self::render_story_item( get_the_ID() );
		}
		$output .= '</div>';

		if ( 'true' === $atts['paginate'] ) {
			$output .= self::render_story_pagination( $stories );
		}

		wp_reset_postdata();
		return $output;
	}

	/**
	 * Author stories grid shortcode
	 *
	 * [author-stories-grid author_id="5" author_username="john" limit="10" status="ongoing" paginate="true"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story grid HTML.
	 */
	public static function author_stories_grid( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'limit'           => 10,
				'status'          => '',
				'paginate'        => 'true',
			),
			'author-stories-grid'
		);

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		return self::render_story_list( $author_id, $atts, 'grid' );
	}

	/**
	 * Author completed stories shortcode
	 *
	 * [author-completed-stories author_id="5" author_username="john" limit="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story list HTML.
	 */
	public static function author_completed_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'limit'           => 10,
			),
			'author-completed-stories'
		);

		// Force status to 'finished'
		$atts['status'] = 'finished';
		$atts['paginate'] = 'true';

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		return self::render_story_list( $author_id, $atts, 'list' );
	}

	/**
	 * Author ongoing stories shortcode
	 *
	 * [author-ongoing-stories author_id="5" author_username="john" limit="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story list HTML.
	 */
	public static function author_ongoing_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'limit'           => 10,
			),
			'author-ongoing-stories'
		);

		// Force status to 'ongoing'
		$atts['status'] = 'ongoing';
		$atts['paginate'] = 'true';

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		return self::render_story_list( $author_id, $atts, 'list' );
	}

	/**
	 * Author featured stories shortcode
	 *
	 * [author-featured-stories author_id="5" author_username="john" limit="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story list HTML.
	 */
	public static function author_featured_stories( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'author_id'       => 0,
				'author_username' => '',
				'limit'           => 10,
			),
			'author-featured-stories'
		);

		$author_id = self::get_author_id( $atts );

		if ( ! $author_id ) {
			return '';
		}

		// Get current page for pagination
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		// Build query args for featured stories
		$query_args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'author'         => $author_id,
			'posts_per_page' => absint( $atts['limit'] ),
			'paged'          => $paged,
			'meta_query'     => array(
				array(
					'key'     => 'fanfic_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		// Use caching for the query
		$cache_key = Fanfic_Cache::get_key( 'user', 'featured_stories', $author_id, md5( serialize( $query_args ) ) );
		$stories = Fanfic_Cache::get(
			$cache_key,
			function() use ( $query_args ) {
				return new WP_Query( $query_args );
			},
			HOUR_IN_SECONDS
		);

		if ( ! $stories->have_posts() ) {
			return '<div class="author-featured-stories author-no-stories"><p>' . esc_html__( 'No featured stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="author-featured-stories" role="region" aria-label="' . esc_attr__( 'Featured stories', 'fanfiction-manager' ) . '">';

		while ( $stories->have_posts() ) {
			$stories->the_post();
			$story_id = get_the_ID();

			$output .= self::render_story_item( $story_id );
		}

		$output .= '</div>';

		// Add pagination
		$output .= self::render_story_pagination( $stories );

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Author follow list shortcode
	 *
	 * [author-follow-list user_id="5"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Author follow list HTML.
	 */
	public static function author_follow_list( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'user_id' => 0,
			),
			'author-follow-list'
		);

		// Get user ID
		$user_id = absint( $atts['user_id'] );
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return '';
		}

		// Use cached follow list (1 hour cache)
		$cache_key = Fanfic_Cache::get_key( 'user', 'follow_list', $user_id );
		$followed_authors = Fanfic_Cache::get(
			$cache_key,
			function() use ( $user_id ) {
				global $wpdb;

				$table_name = $wpdb->prefix . 'fanfic_follows';

				// Check if table exists
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
					return array();
				}

				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT author_id FROM $table_name WHERE user_id = %d ORDER BY followed_at DESC",
						$user_id
					)
				);

				return $results ? wp_list_pluck( $results, 'author_id' ) : array();
			},
			HOUR_IN_SECONDS
		);

		if ( empty( $followed_authors ) ) {
			return '<div class="author-follow-list author-no-follows"><p>' . esc_html__( 'Not following any authors yet.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="author-follow-list" role="region" aria-label="' . esc_attr__( 'Followed authors', 'fanfiction-manager' ) . '">';
		$output .= '<ul class="followed-authors-list">';

		foreach ( $followed_authors as $author_id ) {
			$author_id = absint( $author_id );
			$author = get_userdata( $author_id );

			if ( ! $author ) {
				continue;
			}

			$author_name = esc_html( $author->display_name );
			$author_url = esc_url( fanfic_get_user_profile_url( $author_id ) );
			$avatar = get_avatar( $author_id, 48, '', $author_name, array( 'class' => 'followed-author-avatar', 'loading' => 'lazy' ) );

			$output .= '<li class="followed-author-item">';
			$output .= '<a href="' . $author_url . '" class="followed-author-link">';
			$output .= $avatar;
			$output .= '<span class="followed-author-name">' . $author_name . '</span>';
			$output .= '</a>';
			$output .= '</li>';
		}

		$output .= '</ul>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Helper: Render story list/grid for author
	 *
	 * @since 1.0.0
	 * @param int    $author_id Author ID.
	 * @param array  $atts Shortcode attributes.
	 * @param string $display Display type (list or grid).
	 * @return string Story list HTML.
	 */
	private static function render_story_list( $author_id, $atts, $display = 'list' ) {
		// Get current page for pagination
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		// Build query args
		$query_args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'author'         => $author_id,
			'posts_per_page' => absint( $atts['limit'] ),
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Add status filter if provided
		if ( ! empty( $atts['status'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'fanfiction_status',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $atts['status'] ),
				),
			);
		}

		// Use caching for the query
		$cache_key = Fanfic_Cache::get_key( 'user', 'story_list', $author_id, md5( serialize( $query_args ) ) );
		$stories = Fanfic_Cache::get(
			$cache_key,
			function() use ( $query_args ) {
				return new WP_Query( $query_args );
			},
			HOUR_IN_SECONDS
		);

		if ( ! $stories->have_posts() ) {
			return '<div class="author-story-list author-no-stories"><p>' . esc_html__( 'No stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$container_class = 'grid' === $display ? 'author-stories-grid' : 'author-story-list';
		$output = '<div class="' . esc_attr( $container_class ) . '" role="region" aria-label="' . esc_attr__( 'Author stories', 'fanfiction-manager' ) . '">';

		while ( $stories->have_posts() ) {
			$stories->the_post();
			$story_id = get_the_ID();

			$output .= self::render_story_item( $story_id );
		}

		$output .= '</div>';

		// Add pagination if enabled
		if ( 'true' === $atts['paginate'] ) {
			$output .= self::render_story_pagination( $stories );
		}

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Helper: Render a single story item
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return string Story item HTML.
	 */
	private static function render_story_item( $story_id ) {
		$output = '<article class="author-story-item" id="story-' . esc_attr( $story_id ) . '">';

		// Story title
		$output .= '<h3 class="story-title"><a href="' . esc_url( get_permalink( $story_id ) ) . '">' . esc_html( get_the_title( $story_id ) ) . '</a></h3>';

		// Story excerpt
		$excerpt = get_post_field( 'post_excerpt', $story_id );
		if ( ! empty( $excerpt ) ) {
			$output .= '<div class="story-excerpt">' . wp_kses_post( wpautop( $excerpt ) ) . '</div>';
		}

		// Story metadata
		$output .= '<div class="story-meta">';

		// Genres
		$genres = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( $genres && ! is_wp_error( $genres ) ) {
			$genre_names = wp_list_pluck( $genres, 'name' );
			$output .= '<span class="story-genres"><strong>' . esc_html__( 'Genres:', 'fanfiction-manager' ) . '</strong> ' . esc_html( implode( ', ', $genre_names ) ) . '</span>';
		}

		// Status
		$statuses = get_the_terms( $story_id, 'fanfiction_status' );
		if ( $statuses && ! is_wp_error( $statuses ) ) {
			$status = reset( $statuses );
			$status_slug = sanitize_html_class( $status->slug );
			$output .= '<span class="story-status story-status-' . esc_attr( $status_slug ) . '"><strong>' . esc_html__( 'Status:', 'fanfiction-manager' ) . '</strong> ' . esc_html( $status->name ) . '</span>';
		}

		$output .= '</div>';

		$output .= '</article>';

		return $output;
	}

	/**
	 * Helper: Render pagination for story lists
	 *
	 * @since 1.0.0
	 * @param WP_Query $query Query object.
	 * @return string Pagination HTML.
	 */
	private static function render_story_pagination( $query ) {
		if ( $query->max_num_pages <= 1 ) {
			return '';
		}

		$output = '<nav class="author-story-pagination" role="navigation" aria-label="' . esc_attr__( 'Story pagination', 'fanfiction-manager' ) . '">';

		$pagination_args = array(
			'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
			'format'    => '?paged=%#%',
			'current'   => max( 1, get_query_var( 'paged' ) ),
			'total'     => $query->max_num_pages,
			'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'fanfiction-manager' ),
			'next_text' => esc_html__( 'Next', 'fanfiction-manager' ) . ' &raquo;',
		);

		$output .= paginate_links( $pagination_args );

		$output .= '</nav>';

		return $output;
	}

	/**
	 * Get current user's story count
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional user ID (defaults to current user).
	 * @return int Story count.
	 */
	public static function get_story_count( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return 0;
		}

		// Use cached story count (30 minute cache) - same logic as shortcode
		$cache_key = Fanfic_Cache::get_key( 'user', 'story_count', $user_id );
		$count = Fanfic_Cache::get(
			$cache_key,
			function() use ( $user_id ) {
				return count_user_posts( $user_id, 'fanfiction_story', true );
			},
			Fanfic_Cache::LONG
		);

		return (int) $count;
	}

	/**
	 * Get current user's total chapters count
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional user ID (defaults to current user).
	 * @return int Chapter count.
	 */
	public static function get_total_chapters( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return 0;
		}

		// Use cached total chapters (15 minute cache - expensive nested query) - same logic as shortcode
		$cache_key = Fanfic_Cache::get_key( 'user', 'total_chapters', $user_id );
		$total_chapters = Fanfic_Cache::get(
			$cache_key,
			function() use ( $user_id ) {
				// Get all stories by author
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $user_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_chapters = 0;

				foreach ( $stories as $story_id ) {
					$chapters = get_posts( array(
						'post_type'      => 'fanfiction_chapter',
						'post_parent'    => $story_id,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					) );
					$total_chapters += count( $chapters );
				}

				return $total_chapters;
			},
			Fanfic_Cache::MEDIUM
		);

		return (int) $total_chapters;
	}

	/**
	 * Get current user's total views count
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional user ID (defaults to current user).
	 * @return int View count.
	 */
	public static function get_total_views( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return 0;
		}

		// Use cached total views (15 minute cache - expensive nested query) - same logic as shortcode
		$cache_key = Fanfic_Cache::get_key( 'user', 'total_views', $user_id );
		$total_views = Fanfic_Cache::get(
			$cache_key,
			function() use ( $user_id ) {
				// Get all stories by author
				$stories = get_posts( array(
					'post_type'      => 'fanfiction_story',
					'author'         => $user_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_views = 0;

				foreach ( $stories as $story_id ) {
					// Use Fanfic_Views class to get story views (which sums chapter views)
					if ( class_exists( 'Fanfic_Views' ) ) {
						$total_views += Fanfic_Views::get_story_views( $story_id );
					}
				}

				return $total_views;
			},
			Fanfic_Cache::MEDIUM
		);

		return (int) $total_views;
	}
}
