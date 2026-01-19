<?php
/**
 * Story Shortcodes Class
 *
 * Handles all story-related shortcodes.
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
 * Class Fanfic_Shortcodes_Story
 *
 * Story information and display shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Story {

	/**
	 * Register story shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'story-author-link', array( __CLASS__, 'story_author_link' ) );
		add_shortcode( 'story-intro', array( __CLASS__, 'story_intro' ) );
		add_shortcode( 'story-featured-image', array( __CLASS__, 'story_featured_image' ) );
		add_shortcode( 'story-genres', array( __CLASS__, 'story_genres' ) );
		add_shortcode( 'story-status', array( __CLASS__, 'story_status' ) );
		add_shortcode( 'story-publication-date', array( __CLASS__, 'story_publication_date' ) );
		add_shortcode( 'story-last-updated', array( __CLASS__, 'story_last_updated' ) );
		add_shortcode( 'story-word-count-estimate', array( __CLASS__, 'story_word_count_estimate' ) );
		add_shortcode( 'story-chapters', array( __CLASS__, 'story_chapters' ) );
		add_shortcode( 'story-views', array( __CLASS__, 'story_views' ) );
		add_shortcode( 'story-is-featured', array( __CLASS__, 'story_is_featured' ) );
		add_shortcode( 'fanfic-story-image', array( __CLASS__, 'fanfic_story_image' ) );
	}

	/**
	 * Custom Story Image shortcode
	 *
	 * [fanfic-story-image class="my-class" alt="My alt text"]
	 *
	 * @since 2.1.0
	 * @param array $atts Shortcode attributes.
	 * @return string Image HTML tag.
	 */
	public static function fanfic_story_image( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'class' => 'fanfic-story-image',
				'alt'   => get_the_title( $story_id ),
				'size'  => 'full', // Allow size attribute for thumbnail
			),
			$atts,
			'fanfic-story-image'
		);

		// Sanitize attributes
		$class = esc_attr( $atts['class'] );
		$alt = esc_attr( $atts['alt'] );
		$size = sanitize_key( $atts['size'] );

		// 1. Prioritize the custom URL meta field
		$image_url = get_post_meta( $story_id, '_fanfic_featured_image', true );

		// 2. Fallback to the standard post thumbnail
		if ( empty( $image_url ) && has_post_thumbnail( $story_id ) ) {
			$image_url = get_the_post_thumbnail_url( $story_id, $size );
		}

		if ( empty( $image_url ) ) {
			return ''; // No image found
		}

		return sprintf(
			'<img src="%s" class="%s" alt="%s" loading="lazy" />',
			esc_url( $image_url ),
			esc_attr( $class ),
			esc_attr( $alt )
		);
	}

	/**
	 * Story title shortcode
	 *
	 * [story-title]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story title.
	 */
	public static function story_title( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		return esc_html( get_the_title( $story_id ) );
	}

	/**
	 * Story author link shortcode
	 *
	 * [story-author-link]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Author link HTML.
	 */
	public static function story_author_link( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$author_id = get_post_field( 'post_author', $story_id );
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = fanfic_get_user_profile_url( $author_id );

		return sprintf(
			'<a href="%s" class="story-author-link">%s</a>',
			esc_url( $author_url ),
			esc_html( $author_name )
		);
	}

	/**
	 * Story introduction shortcode
	 *
	 * [story-intro]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story introduction/excerpt.
	 */
	public static function story_intro( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$excerpt = get_post_field( 'post_excerpt', $story_id );

		if ( empty( $excerpt ) ) {
			return '<p class="story-no-intro">' . esc_html__( 'No introduction available.', 'fanfiction-manager' ) . '</p>';
		}

		return '<div class="story-intro">' . wp_kses_post( wpautop( $excerpt ) ) . '</div>';
	}

	/**
	 * Story featured image shortcode
	 *
	 * [story-featured-image]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Featured image HTML.
	 */
	public static function story_featured_image( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'size' => 'large',
			),
			'story-featured-image'
		);

		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		if ( ! has_post_thumbnail( $story_id ) ) {
			return '';
		}

		// Add lazy loading attribute for performance optimization
		$image_attrs = array(
			'class'   => 'story-featured-image',
			'loading' => 'lazy',
		);

		return get_the_post_thumbnail( $story_id, $atts['size'], $image_attrs );
	}

	/**
	 * Story genres shortcode
	 *
	 * [story-genres]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Genres list HTML.
	 */
	public static function story_genres( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$genres = get_the_terms( $story_id, 'fanfiction_genre' );

		if ( ! $genres || is_wp_error( $genres ) ) {
			return '<span class="story-no-genres">' . esc_html__( 'No genres', 'fanfiction-manager' ) . '</span>';
		}

		$genre_links = array();
		foreach ( $genres as $genre ) {
			$genre_links[] = sprintf(
				'<a href="%s" class="story-genre-link">%s</a>',
				esc_url( get_term_link( $genre ) ),
				esc_html( $genre->name )
			);
		}

		return '<span class="story-genres" aria-label="' . esc_attr__( 'Story genres', 'fanfiction-manager' ) . '">' . implode( ', ', $genre_links ) . '</span>';
	}

	/**
	 * Story status shortcode
	 *
	 * [story-status]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Status badge HTML.
	 */
	public static function story_status( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$statuses = get_the_terms( $story_id, 'fanfiction_status' );

		if ( ! $statuses || is_wp_error( $statuses ) ) {
			return '<span class="story-status story-status-unknown">' . esc_html__( 'Unknown', 'fanfiction-manager' ) . '</span>';
		}

		$status = reset( $statuses );
		$status_slug = sanitize_html_class( $status->slug );

		return sprintf(
			'<span class="story-status story-status-%s" role="status" aria-label="%s">%s</span>',
			esc_attr( $status_slug ),
			esc_attr( sprintf( __( 'Story status: %s', 'fanfiction-manager' ), $status->name ) ),
			esc_html( $status->name )
		);
	}

	/**
	 * Story publication date shortcode
	 *
	 * [story-publication-date]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Publication date.
	 */
	public static function story_publication_date( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'format' => get_option( 'date_format' ),
			),
			'story-publication-date'
		);

		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		return '<time class="story-publication-date" datetime="' . esc_attr( get_the_date( 'c', $story_id ) ) . '">' .
			esc_html( get_the_date( $atts['format'], $story_id ) ) .
			'</time>';
	}

	/**
	 * Story last updated shortcode
	 *
	 * [story-last-updated]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Last updated date.
	 */
	public static function story_last_updated( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'format' => get_option( 'date_format' ),
			),
			'story-last-updated'
		);

		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		return '<time class="story-last-updated" datetime="' . esc_attr( get_the_modified_date( 'c', $story_id ) ) . '">' .
			esc_html( get_the_modified_date( $atts['format'], $story_id ) ) .
			'</time>';
	}

	/**
	 * Story word count estimate shortcode
	 *
	 * [story-word-count-estimate]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Word count.
	 */
	public static function story_word_count_estimate( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		// Use cached word count calculation (6 hour cache - critical bottleneck)
		$cache_key = Fanfic_Cache::get_key( 'story', 'word_count', $story_id );
		$total_words = Fanfic_Cache::get(
			$cache_key,
			function() use ( $story_id ) {
				// Get all chapters
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_words = 0;

				foreach ( $chapters as $chapter_id ) {
					$content = get_post_field( 'post_content', $chapter_id );
					$content = wp_strip_all_tags( $content );
					$word_count = str_word_count( $content );
					$total_words += $word_count;
				}

				return $total_words;
			},
			6 * HOUR_IN_SECONDS
		);

		return '<span class="story-word-count">' . Fanfic_Shortcodes::format_number( $total_words ) . '</span>';
	}

	/**
	 * Story chapters count shortcode
	 *
	 * [story-chapters]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter count.
	 */
	public static function story_chapters( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		// Use cached chapter count (6 hour cache)
		$cache_key = Fanfic_Cache::get_key( 'story', 'chapter_count', $story_id );
		$count = Fanfic_Cache::get(
			$cache_key,
			function() use ( $story_id ) {
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				return count( $chapters );
			},
			6 * HOUR_IN_SECONDS
		);

		return '<span class="story-chapters-count">' . Fanfic_Shortcodes::format_number( $count ) . '</span>';
	}

	/**
	 * Story views shortcode
	 *
	 * [story-views]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string View count.
	 */
	public static function story_views( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		// Use cached view count (5 minute cache - frequently changing data)
		$cache_key = Fanfic_Cache::get_key( 'story', 'view_count', $story_id );
		$total_views = Fanfic_Cache::get(
			$cache_key,
			function() use ( $story_id ) {
				// Get all chapters
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_views = 0;

				foreach ( $chapters as $chapter_id ) {
					$views = get_post_meta( $chapter_id, 'fanfic_views', true );
					$total_views += absint( $views );
				}

				return $total_views;
			},
			Fanfic_Cache::SHORT
		);

		return '<span class="story-views">' . Fanfic_Shortcodes::format_number( $total_views ) . '</span>';
	}

	/**
	 * Story is featured shortcode
	 *
	 * [story-is-featured]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Featured badge HTML or empty string.
	 */
	public static function story_is_featured( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$is_featured = get_post_meta( $story_id, 'fanfic_is_featured', true );

		if ( ! $is_featured ) {
			return '';
		}

		return '<span class="story-featured-badge" aria-label="' . esc_attr__( 'Featured story', 'fanfiction-manager' ) . '">' . esc_html__( 'Featured', 'fanfiction-manager' ) . '</span>';
	}
}
