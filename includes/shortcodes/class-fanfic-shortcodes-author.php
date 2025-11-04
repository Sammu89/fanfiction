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
}
