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
		add_shortcode( 'story-genres-pills', array( __CLASS__, 'story_genres_pills' ) );
		add_shortcode( 'story-fandoms', array( __CLASS__, 'story_fandoms' ) );
		add_shortcode( 'story-language', array( __CLASS__, 'story_language' ) );
		add_shortcode( 'story-status', array( __CLASS__, 'story_status' ) );
		add_shortcode( 'story-publication-date', array( __CLASS__, 'story_publication_date' ) );
		add_shortcode( 'story-last-updated', array( __CLASS__, 'story_last_updated' ) );
		add_shortcode( 'story-word-count-estimate', array( __CLASS__, 'story_word_count_estimate' ) );
		add_shortcode( 'story-chapters', array( __CLASS__, 'story_chapters' ) );
		add_shortcode( 'story-views', array( __CLASS__, 'story_views' ) );
		add_shortcode( 'story-likes', array( __CLASS__, 'story_likes' ) );
		add_shortcode( 'story-is-featured', array( __CLASS__, 'story_is_featured' ) );
		add_shortcode( 'fanfic-story-image', array( __CLASS__, 'fanfic_story_image' ) );

		// Phase 4.4: Warnings, tags, and age badges
		add_shortcode( 'story-warnings', array( __CLASS__, 'story_warnings' ) );
		add_shortcode( 'story-visible-tags', array( __CLASS__, 'story_visible_tags' ) );
		add_shortcode( 'story-age-badge', array( __CLASS__, 'story_age_badge' ) );
		add_shortcode( 'story-translations', array( __CLASS__, 'story_translations' ) );
	}

	/**
	 * Custom Story Image shortcode
	 *
	 * [fanfic-story-image]
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
				'alt'   => get_the_title( $story_id ),
				'size'  => 'full', // Allow size attribute for thumbnail
			),
			$atts,
			'fanfic-story-image'
		);

		// Sanitize attributes
		$class = 'fanfic-story-image';
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
		$links = array();
		$links[] = sprintf(
			'<a href="%s" class="story-author-link">%s</a>',
			esc_url( $author_url ),
			esc_html( $author_name )
		);

		if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
			$coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id, Fanfic_Coauthors::STATUS_ACCEPTED );
			foreach ( (array) $coauthors as $coauthor ) {
				if ( empty( $coauthor->ID ) || empty( $coauthor->display_name ) ) {
					continue;
				}
				$links[] = sprintf(
					'<a href="%s" class="story-author-link story-coauthor-link">%s</a>',
					esc_url( fanfic_get_user_profile_url( $coauthor->ID ) ),
					esc_html( $coauthor->display_name )
				);
			}
		}

		return implode( ', ', $links );
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
		$heading = '<h3 class="fanfic-story-intro-title">' . esc_html__( 'Summary', 'fanfiction-manager' ) . '</h3>';
		$intro = empty( $excerpt )
			? '<p class="story-no-intro">' . esc_html__( 'No introduction available.', 'fanfiction-manager' ) . '</p>'
			: '<div class="story-intro">' . wp_kses_post( wpautop( $excerpt ) ) . '</div>';

		// Author's Notes
		$notes_enabled  = get_post_meta( $story_id, '_fanfic_author_notes_enabled', true );
		$notes_position = get_post_meta( $story_id, '_fanfic_author_notes_position', true ) ?: 'below';
		$notes_raw      = get_post_meta( $story_id, '_fanfic_author_notes', true );

		if ( '1' === $notes_enabled && ! empty( $notes_raw ) ) {
			$notes_html = '<aside class="fanfic-author-notes">'
				. '<h4 class="fanfic-author-notes-title">' . esc_html__( "Author's Notes", 'fanfiction-manager' ) . '</h4>'
				. '<div class="fanfic-author-notes-content">' . wp_kses_post( wpautop( $notes_raw ) ) . '</div>'
				. '</aside>';
			return $heading . ( ( 'above' === $notes_position ) ? $notes_html . $intro : $intro . $notes_html );
		}

		return $heading . $intro;
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
	 * Story genres pills shortcode
	 *
	 * [story-genres-pills]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Genres as card-style pills.
	 */
	public static function story_genres_pills( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$genres = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( ! $genres || is_wp_error( $genres ) ) {
			return '';
		}

		$pills = array();
		foreach ( $genres as $genre ) {
			$term_link = get_term_link( $genre );
			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			$pills[] = sprintf(
				'<span class="fanfic-botaozinho genre"><a href="%1$s" class="story-genre-link">%2$s</a></span>',
				esc_url( $term_link ),
				esc_html( $genre->name )
			);
		}

		if ( empty( $pills ) ) {
			return '';
		}

		return '<div class="fanfic-story-genres-pills" aria-label="' . esc_attr__( 'Story genres', 'fanfiction-manager' ) . '">' . implode( '', $pills ) . '</div>';
	}

	/**
	 * Story fandoms shortcode
	 *
	 * [story-fandoms]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Fandom list HTML.
	 */
	public static function story_fandoms( $atts ) {
		if ( ! class_exists( 'Fanfic_Fandoms' ) || ! Fanfic_Fandoms::is_enabled() ) {
			return '';
		}

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$is_original = get_post_meta( $story_id, Fanfic_Fandoms::META_ORIGINAL, true );
		if ( $is_original ) {
			return '<div class="fanfic-story-fandoms"><strong>' . esc_html__( 'Fandoms:', 'fanfiction-manager' ) . '</strong> <span class="story-fandoms story-fandoms-original">' . esc_html__( 'Original Work', 'fanfiction-manager' ) . '</span></div>';
		}

		$fandoms = Fanfic_Fandoms::get_story_fandom_labels( $story_id );
		if ( empty( $fandoms ) ) {
			return '';
		}

		$labels = array();
		foreach ( $fandoms as $fandom ) {
			$labels[] = esc_html( $fandom['label'] );
		}

		return '<div class="fanfic-story-fandoms"><strong>' . esc_html__( 'Fandoms:', 'fanfiction-manager' ) . '</strong> <span class="story-fandoms" aria-label="' . esc_attr__( 'Story fandoms', 'fanfiction-manager' ) . '">' . implode( ', ', $labels ) . '</span></div>';
	}

	/**
	 * Story language shortcode
	 *
	 * [story-language]
	 *
	 * Displays the language associated with the story.
	 * Always shows label with native name. Returns empty if no language set.
	 *
	 * @since 1.3.0
	 * @return string Language HTML or empty string.
	 */
	public static function story_language() {
		if ( ! class_exists( 'Fanfic_Languages' ) || ! Fanfic_Languages::is_enabled() ) {
			return '';
		}

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$language = Fanfic_Languages::get_story_language( $story_id );

		if ( ! $language ) {
			return '';
		}

		$label = esc_html( $language['name'] );
		if ( ! empty( $language['native_name'] ) && $language['native_name'] !== $language['name'] ) {
			$label .= ' <span class="story-language-native">(' . esc_html( $language['native_name'] ) . ')</span>';
		}

		return '<div class="fanfic-story-language"><strong>' . esc_html__( 'Language:', 'fanfiction-manager' ) . '</strong> <span class="story-language" aria-label="' . esc_attr__( 'Story language', 'fanfiction-manager' ) . '">' . $label . '</span></div>';
	}

	/**
	 * Story translations shortcode
	 *
	 * [story-translations]
	 *
	 * Displays available translations as inline link (1 translation)
	 * or dropdown (2+ translations) with globe icon.
	 *
	 * @since 1.5.0
	 * @return string Translations HTML or empty string.
	 */
	public static function story_translations() {
		if ( ! class_exists( 'Fanfic_Translations' ) || ! Fanfic_Translations::is_enabled() ) {
			return '';
		}

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$siblings = Fanfic_Translations::get_translation_siblings( $story_id );
		if ( empty( $siblings ) ) {
			return '';
		}

		$count = count( $siblings );

		ob_start();
		?>
		<div class="fanfic-story-translations" aria-label="<?php esc_attr_e( 'Available translations', 'fanfiction-manager' ); ?>">
			<span class="fanfic-translations-icon" aria-hidden="true">&#127760;</span>
			<strong><?php esc_html_e( 'Also available in:', 'fanfiction-manager' ); ?></strong>
			<?php if ( 1 === $count ) : ?>
				<?php $sibling = $siblings[0]; ?>
				<a href="<?php echo esc_url( $sibling['permalink'] ); ?>" class="fanfic-translation-link">
					<?php echo esc_html( $sibling['language_label'] ); ?>
				</a>
			<?php else : ?>
				<select class="fanfic-translations-dropdown" onchange="if(this.value)window.location.href=this.value">
					<option value=""><?php esc_html_e( 'Select language...', 'fanfiction-manager' ); ?></option>
					<?php foreach ( $siblings as $sibling ) : ?>
						<option value="<?php echo esc_url( $sibling['permalink'] ); ?>">
							<?php echo esc_html( $sibling['language_label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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
				return class_exists( 'Fanfic_Interactions' ) ? Fanfic_Interactions::get_story_views( $story_id ) : 0;
			},
			Fanfic_Cache::SHORT
		);

		return '<span class="story-views">' . Fanfic_Shortcodes::format_number( $total_views ) . '</span>';
	}

	/**
	 * Story likes shortcode
	 *
	 * [story-likes]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Likes count.
	 */
	public static function story_likes( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$total_likes = class_exists( 'Fanfic_Interactions' ) ? absint( Fanfic_Interactions::get_story_likes( $story_id ) ) : 0;
		return '<span class="story-likes">' . Fanfic_Shortcodes::format_number( $total_likes ) . '</span>';
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

	/**
	 * Story warnings shortcode
	 *
	 * [story-warnings]
	 *
	 * Displays content warnings associated with the story.
	 * Shows "None declared" if no warnings are set.
	 *
	 * @since 1.2.0
	 * @param array $atts Shortcode attributes.
	 * @return string Warnings HTML.
	 */
	public static function story_warnings( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_none'    => 'true',  // Show "None declared" if no warnings
				'show_label'   => 'true',  // Show "Warnings:" label
				'show_age'     => 'true',  // Show age badge next to each warning
				'link'         => 'false', // Whether to link to archive (not implemented yet)
			),
			$atts,
			'story-warnings'
		);

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			return '';
		}

		// Check if warnings class exists
		if ( ! class_exists( 'Fanfic_Warnings' ) ) {
			return '';
		}

		$warnings = Fanfic_Warnings::get_story_warnings( $story_id );
		$show_label = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );
		$show_none = filter_var( $atts['show_none'], FILTER_VALIDATE_BOOLEAN );
		$show_age = filter_var( $atts['show_age'], FILTER_VALIDATE_BOOLEAN );

		$output = '<div class="fanfic-story-warnings">';

		if ( $show_label ) {
			$output .= '<strong>' . esc_html__( 'Content Warnings:', 'fanfiction-manager' ) . '</strong> ';
		}

		if ( empty( $warnings ) ) {
			if ( $show_none ) {
				$output .= '<span class="story-warnings story-warnings-none">' . esc_html__( 'None declared', 'fanfiction-manager' ) . '</span>';
			}
		} else {
			$warning_items = array();
			foreach ( $warnings as $warning ) {
				$item = '<span class="story-warning-item">';
				$item .= '<span class="story-warning-name">' . esc_html( $warning['name'] ) . '</span>';
				if ( $show_age && ! empty( $warning['min_age'] ) ) {
					$warning_age_label = function_exists( 'fanfic_get_age_display_label' ) ? fanfic_get_age_display_label( $warning['min_age'], false ) : (string) $warning['min_age'];
					if ( '' === $warning_age_label ) {
						$warning_age_label = (string) $warning['min_age'];
					}
					$warning_age_class = function_exists( 'fanfic_get_age_badge_class' ) ? fanfic_get_age_badge_class( $warning['min_age'] ) : 'fanfic-age-badge-18-plus';
					$item .= ' <span class="story-warning-age-badge ' . esc_attr( $warning_age_class ) . '">' . esc_html( $warning_age_label ) . '</span>';
				}
				$item .= '</span>';
				$warning_items[] = $item;
			}
			$output .= '<span class="story-warnings" aria-label="' . esc_attr__( 'Content warnings', 'fanfiction-manager' ) . '">' . implode( ', ', $warning_items ) . '</span>';
		}

		$output .= '</div>';
		return $output;
	}

	/**
	 * Story visible tags shortcode
	 *
	 * [story-visible-tags]
	 *
	 * Displays visible tags associated with the story.
	 *
	 * @since 1.2.0
	 * @param array $atts Shortcode attributes.
	 * @return string Tags HTML.
	 */
	public static function story_visible_tags( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_label' => 'true',  // Show "Tags:" label
				'show_none'  => 'false', // Show message when no tags
				'separator'  => ', ',    // Separator between tags
			),
			$atts,
			'story-visible-tags'
		);

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_tags', true ) ) {
			return '';
		}

		// Get visible tags
		if ( ! function_exists( 'fanfic_get_visible_tags' ) ) {
			return '';
		}

		$tags = fanfic_get_visible_tags( $story_id );
		$show_label = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );
		$show_none = filter_var( $atts['show_none'], FILTER_VALIDATE_BOOLEAN );

		if ( empty( $tags ) ) {
			if ( $show_none ) {
				$output = '<div class="fanfic-story-tags">';
				if ( $show_label ) {
					$output .= '<strong>' . esc_html__( 'Tags:', 'fanfiction-manager' ) . '</strong> ';
				}
				$output .= '<span class="story-tags story-tags-none">' . esc_html__( 'No tags', 'fanfiction-manager' ) . '</span>';
				$output .= '</div>';
				return $output;
			}
			return '';
		}

		$output = '<div class="fanfic-story-tags">';

		if ( $show_label ) {
			$output .= '<strong>' . esc_html__( 'Tags:', 'fanfiction-manager' ) . '</strong> ';
		}

		$tag_items = array();
		foreach ( $tags as $tag ) {
			$tag_items[] = '<span class="story-tag-item">' . esc_html( $tag ) . '</span>';
		}

		$output .= '<span class="story-tags" aria-label="' . esc_attr__( 'Story tags', 'fanfiction-manager' ) . '">' . implode( esc_html( $atts['separator'] ), $tag_items ) . '</span>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Story age badge shortcode
	 *
	 * [story-age-badge]
	 *
	 * Displays the derived age rating badge based on the story's warnings.
	 * The age rating is determined by the highest min_age from all warnings.
	 *
	 * @since 1.2.0
	 * @param array $atts Shortcode attributes.
	 * @return string Age badge HTML.
	 */
	public static function story_age_badge( $atts ) {
		$atts = shortcode_atts(
			array(
				'default'    => '',       // Default age rating if no warnings
				'show_label' => 'false',  // Show "Age Rating:" label
			),
			$atts,
			'story-age-badge'
		);

		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			return '';
		}

		// Check if warnings class exists
		if ( ! class_exists( 'Fanfic_Warnings' ) ) {
			return '';
		}

		$warnings = Fanfic_Warnings::get_story_warnings( $story_id );
		$show_label = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );
		$default_age = trim( (string) $atts['default'] );
		if ( '' === $default_age ) {
			$default_age = Fanfic_Warnings::get_default_age_label( false );
		}
		$warning_ids = array_map( 'absint', wp_list_pluck( (array) $warnings, 'id' ) );
		$highest_age = ! empty( $warning_ids ) ? Fanfic_Warnings::calculate_derived_age( $warning_ids ) : $default_age;
		if ( '' === $highest_age ) {
			return '';
		}

		$output = '<span class="fanfic-age-rating">';

		if ( $show_label ) {
			$output .= '<span class="age-rating-label">' . esc_html__( 'Age Rating:', 'fanfiction-manager' ) . '</span> ';
		}

		$highest_age_label = function_exists( 'fanfic_get_age_display_label' ) ? fanfic_get_age_display_label( $highest_age, true ) : (string) $highest_age;
		if ( '' === $highest_age_label ) {
			$highest_age_label = (string) $highest_age;
		}
		$highest_age_class = function_exists( 'fanfic_get_age_badge_class' ) ? fanfic_get_age_badge_class( $highest_age ) : 'fanfic-age-badge-18-plus';
		$age_class = 'fanfic-age-badge ' . $highest_age_class;
		$output .= '<span class="' . esc_attr( $age_class ) . '" aria-label="' . esc_attr( sprintf( __( 'Age rating: %s', 'fanfiction-manager' ), $highest_age_label ) ) . '">' . esc_html( $highest_age_label ) . '</span>';

		$output .= '</span>';

		return $output;
	}

}
