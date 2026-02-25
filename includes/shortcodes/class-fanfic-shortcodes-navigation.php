<?php
/**
 * Navigation Shortcodes Class
 *
 * Handles all navigation-related shortcodes for chapters.
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
 * Class Fanfic_Shortcodes_Navigation
 *
 * Chapter navigation and breadcrumb shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Navigation {

	/**
	 * Register navigation shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'chapters-nav', array( __CLASS__, 'chapters_nav' ) );
		add_shortcode( 'chapters-list', array( __CLASS__, 'chapters_list' ) );
		add_shortcode( 'first-chapter', array( __CLASS__, 'first_chapter' ) );
		add_shortcode( 'latest-chapter', array( __CLASS__, 'latest_chapter' ) );
		add_shortcode( 'chapter-breadcrumb', array( __CLASS__, 'chapter_breadcrumb' ) );
		add_shortcode( 'chapter-story', array( __CLASS__, 'chapter_story' ) );
		add_shortcode( 'story-chapters-dropdown', array( __CLASS__, 'story_chapters_dropdown' ) );
	}

	/**
	 * Get all chapters for a story, sorted correctly
	 *
	 * Returns chapters in order: Prologue (0) -> Chapters (1-999) -> Epilogue (1000+)
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return array Array of chapter posts sorted by chapter number.
	 */
	private static function get_story_chapters( $story_id ) {
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		) );

		if ( empty( $chapters ) ) {
			return array();
		}

		// Sort chapters by chapter number
		usort( $chapters, function( $a, $b ) {
			$number_a = get_post_meta( $a->ID, '_fanfic_chapter_number', true );
			$number_b = get_post_meta( $b->ID, '_fanfic_chapter_number', true );

			// Convert to integers for proper comparison
			$number_a = absint( $number_a );
			$number_b = absint( $number_b );

			// Prologue (0) comes first, then regular chapters (1-999), then epilogue (1000+)
			return $number_a - $number_b;
		} );

		return $chapters;
	}

	/**
	 * Chapters navigation shortcode
	 *
	 * [chapters-nav]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Navigation HTML.
	 */
	public static function chapters_nav( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$story_id = get_post_field( 'post_parent', $chapter_id );
		$chapters = self::get_story_chapters( $story_id );

		if ( empty( $chapters ) ) {
			return '';
		}

		// Find current chapter position
		$current_index = null;
		foreach ( $chapters as $index => $chapter ) {
			if ( $chapter->ID === $chapter_id ) {
				$current_index = $index;
				break;
			}
		}

		if ( $current_index === null ) {
			return '';
		}

		$output = '<nav class="chapters-navigation" role="navigation" aria-label="' . esc_attr__( 'Chapter navigation', 'fanfiction-manager' ) . '">';

		// Previous button
		if ( $current_index > 0 ) {
			$prev_chapter = $chapters[ $current_index - 1 ];
			$output .= sprintf(
				'<a href="%s" class="chapter-nav-prev" rel="prev" aria-label="%s">&larr; %s</a>',
				esc_url( get_permalink( $prev_chapter->ID ) ),
				esc_attr__( 'Previous chapter', 'fanfiction-manager' ),
				esc_html__( 'Previous', 'fanfiction-manager' )
			);
		} else {
			$output .= '<span class="chapter-nav-prev disabled" aria-disabled="true">&larr; ' . esc_html__( 'Previous', 'fanfiction-manager' ) . '</span>';
		}

		// Chapter dropdown
		$output .= '<select class="chapter-selector" onchange="if(this.value) window.location.href=this.value" aria-label="' . esc_attr__( 'Jump to chapter', 'fanfiction-manager' ) . '">';
		$output .= '<option value="">' . esc_html__( 'Select Chapter', 'fanfiction-manager' ) . '</option>';

		foreach ( $chapters as $chapter ) {
			$selected = ( $chapter->ID === $chapter_id ) ? ' selected' : '';
			$aria_current = ( $chapter->ID === $chapter_id ) ? ' aria-current="page"' : '';
			$chapter_label = self::get_chapter_label( $chapter->ID );
			$output .= sprintf(
				'<option value="%s"%s%s>%s: %s</option>',
				esc_url( get_permalink( $chapter->ID ) ),
				$selected,
				$aria_current,
				esc_html( $chapter_label ),
				esc_html( $chapter->post_title )
			);
		}

		$output .= '</select>';

		// Next button
		if ( $current_index < count( $chapters ) - 1 ) {
			$next_chapter = $chapters[ $current_index + 1 ];
			$output .= sprintf(
				'<a href="%s" class="chapter-nav-next" rel="next" aria-label="%s">%s &rarr;</a>',
				esc_url( get_permalink( $next_chapter->ID ) ),
				esc_attr__( 'Next chapter', 'fanfiction-manager' ),
				esc_html__( 'Next', 'fanfiction-manager' )
			);
		} else {
			$output .= '<span class="chapter-nav-next disabled" aria-disabled="true">' . esc_html__( 'Next', 'fanfiction-manager' ) . ' &rarr;</span>';
		}

		$output .= '</nav>';

		return $output;
	}

	/**
	 * Get chapter label based on type and number
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter ID.
	 * @return string Chapter label (e.g., "Prologue", "Chapter 1", "Epilogue").
	 */
	private static function get_chapter_label( $chapter_id ) {
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

		if ( 'prologue' === $chapter_type ) {
			return __( 'Prologue', 'fanfiction-manager' );
		} elseif ( 'epilogue' === $chapter_type ) {
			return __( 'Epilogue', 'fanfiction-manager' );
		} else {
			return sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $chapter_number );
		}
	}

	/**
	 * Get chapter display title
	 *
	 * Returns the full display title for a chapter. If the chapter has a custom title,
	 * returns "Label: Title" (e.g., "Chapter 1: Lorem Ipsum"). If no title, returns just
	 * the label (e.g., "Chapter 1", "Prologue").
	 *
	 * @since 1.0.0
	 * @param int    $chapter_id    Chapter ID.
	 * @param string $chapter_title Optional. Chapter title (if already fetched).
	 * @return string Full display title.
	 */
	private static function get_chapter_display_title( $chapter_id, $chapter_title = '' ) {
		if ( empty( $chapter_title ) ) {
			$chapter_title = get_the_title( $chapter_id );
		}

		// Get chapter label
		$label = self::get_chapter_label( $chapter_id );

		// If title is empty, return just the label
		if ( empty( $chapter_title ) ) {
			return $label;
		}

		// Has a title, return label + title
		return sprintf( '%s: %s', $label, $chapter_title );
	}

	/**
	 * Chapters list shortcode
	 *
	 * Renders a table of chapters with stats: views, words, rating, likes,
	 * comments, and updated date. Badges (read, bookmarked) are driven by JS.
	 *
	 * [chapters-list]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Chapters table HTML.
	 */
	public static function chapters_list( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$chapters = self::get_story_chapters( $story_id );

		if ( empty( $chapters ) ) {
			return '<p class="no-chapters">' . esc_html__( 'No chapters available.', 'fanfiction-manager' ) . '</p>';
		}

		// Batch-load all chapter stats in one query.
		$chapter_ids = wp_list_pluck( $chapters, 'ID' );
		$all_stats   = class_exists( 'Fanfic_Interactions' )
			? Fanfic_Interactions::batch_get_chapter_stats( $chapter_ids )
			: array();

		$date_format = get_option( 'date_format' );
		$enable_likes = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_likes', true ) : true;
		$enable_comments = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_comments', true ) : true;

		$output  = '<div class="fanfic-chapters-table-wrap">';
		$output .= '<table class="fanfic-chapters-table" role="table">';
		$output .= '<thead><tr>';
		$output .= '<th class="fanfic-col-chapter" scope="col">' . esc_html__( 'Chapter', 'fanfiction-manager' ) . '</th>';
		$output .= '<th class="fanfic-col-stat fanfic-col-views" scope="col" title="' . esc_attr__( 'Views', 'fanfiction-manager' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Views', 'fanfiction-manager' ) . '</span></th>';
		$output .= '<th class="fanfic-col-stat fanfic-col-words" scope="col" title="' . esc_attr__( 'Words', 'fanfiction-manager' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Words', 'fanfiction-manager' ) . '</span></th>';
		$output .= '<th class="fanfic-col-stat fanfic-col-rating" scope="col" title="' . esc_attr__( 'Rating', 'fanfiction-manager' ) . '">&#9733;<span class="screen-reader-text">' . esc_html__( 'Rating', 'fanfiction-manager' ) . '</span></th>';
		if ( $enable_likes ) {
			$output .= '<th class="fanfic-col-stat fanfic-col-likes" scope="col" title="' . esc_attr__( 'Likes', 'fanfiction-manager' ) . '"><span class="dashicons dashicons-thumbs-up" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Likes', 'fanfiction-manager' ) . '</span></th>';
		}
		if ( $enable_comments ) {
			$output .= '<th class="fanfic-col-stat fanfic-col-comments" scope="col" title="' . esc_attr__( 'Comments', 'fanfiction-manager' ) . '"><span class="dashicons dashicons-admin-comments" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Comments', 'fanfiction-manager' ) . '</span></th>';
		}
		$output .= '<th class="fanfic-col-date" scope="col">' . esc_html__( 'Updated', 'fanfiction-manager' ) . '</th>';
		$output .= '</tr></thead>';
		$output .= '<tbody>';

		foreach ( $chapters as $chapter ) {
			$chapter_id    = $chapter->ID;
			$display_title = self::get_chapter_display_title( $chapter_id, $chapter->post_title );
			$permalink     = get_permalink( $chapter_id );

			$stats = isset( $all_stats[ $chapter_id ] ) ? $all_stats[ $chapter_id ] : array(
				'views'        => 0,
				'likes'        => 0,
				'rating_avg'   => 0.0,
				'rating_count' => 0,
			);

			// Word count from raw content.
			$word_count = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $chapter_id ) ) );

			// Comment count.
			$comment_count = absint( $chapter->comment_count );

			// Updated date.
			$modified_datetime = get_the_modified_date( 'c', $chapter );
			$modified_date     = get_the_modified_date( $date_format, $chapter );

			// Rating display.
			if ( $stats['rating_count'] > 0 ) {
				$rating_html = '<span class="fanfic-cht-rating">' . esc_html( number_format_i18n( (float) $stats['rating_avg'], 1 ) ) . '</span>';
			} else {
				$rating_html = '<span class="fanfic-cht-rating fanfic-cht-rating--none">—</span>';
			}

			$output .= '<tr class="fanfic-chapter-row">';

			// Chapter title + inline badges (read checkmark + bookmarked, JS-driven).
			$output .= '<td class="fanfic-col-chapter"><div class="fanfic-cht-cell">';
			$output .= sprintf(
				'<span class="fanfic-badge fanfic-badge-bookmarked fanfic-badge-table" data-badge-story-id="%1$d" data-badge-chapter-id="%2$d" style="display:none;" aria-label="%3$s" title="%3$s"><span class="dashicons dashicons-heart" aria-hidden="true"></span><span class="screen-reader-text">%4$s</span></span>',
				absint( $story_id ),
				absint( $chapter_id ),
				esc_attr__( 'Bookmarked', 'fanfiction-manager' ),
				esc_html__( 'Bookmarked', 'fanfiction-manager' )
			);
			$output .= sprintf(
				'<a href="%s" class="fanfic-cht-link">%s</a>',
				esc_url( $permalink ),
				esc_html( $display_title )
			);
			$output .= sprintf(
				'<span class="fanfic-read-indicator" data-story-id="%1$d" data-chapter-id="%2$d" aria-label="%3$s" title="%3$s"></span>',
				absint( $story_id ),
				absint( $chapter_id ),
				esc_attr__( 'Read', 'fanfiction-manager' )
			);
			$output .= '</div></td>';

			// Stats columns.
			$output .= '<td class="fanfic-col-stat fanfic-col-views">'    . esc_html( Fanfic_Shortcodes::format_number( $stats['views'] ) ) . '</td>';
			$output .= '<td class="fanfic-col-stat fanfic-col-words">'    . esc_html( Fanfic_Shortcodes::format_number( $word_count ) ) . '</td>';
			$output .= '<td class="fanfic-col-stat fanfic-col-rating">'   . $rating_html . '</td>';
			if ( $enable_likes ) {
				$output .= '<td class="fanfic-col-stat fanfic-col-likes">' . esc_html( Fanfic_Shortcodes::format_number( $stats['likes'] ) ) . '</td>';
			}
			if ( $enable_comments ) {
				$chapter_comments_open = comments_open( $chapter_id );
				$comment_cell_value = $chapter_comments_open ? Fanfic_Shortcodes::format_number( $comment_count ) : '';
				$output .= '<td class="fanfic-col-stat fanfic-col-comments">' . esc_html( $comment_cell_value ) . '</td>';
			}

			// Updated date.
			$output .= sprintf(
				'<td class="fanfic-col-date"><time datetime="%s">%s</time></td>',
				esc_attr( $modified_datetime ),
				esc_html( $modified_date )
			);

			$output .= '</tr>';
		}

		$output .= '</tbody></table>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * First chapter shortcode
	 *
	 * [first-chapter]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string First chapter link HTML.
	 */
	public static function first_chapter( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$chapters = self::get_story_chapters( $story_id );

		if ( empty( $chapters ) ) {
			return '';
		}

		$first_chapter = reset( $chapters );

		return sprintf(
			'<a href="%s" class="first-chapter-link">%s</a>',
			esc_url( get_permalink( $first_chapter->ID ) ),
			esc_html__( 'Start Reading', 'fanfiction-manager' )
		);
	}

	/**
	 * Latest chapter shortcode
	 *
	 * [latest-chapter]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Latest chapter link HTML.
	 */
	public static function latest_chapter( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$chapters = self::get_story_chapters( $story_id );

		if ( empty( $chapters ) ) {
			return '';
		}

		$latest_chapter = end( $chapters );

		return sprintf(
			'<a href="%s" class="latest-chapter-link">%s</a>',
			esc_url( get_permalink( $latest_chapter->ID ) ),
			esc_html__( 'Latest Chapter', 'fanfiction-manager' )
		);
	}

	/**
	 * Chapter breadcrumb shortcode
	 *
	 * [chapter-breadcrumb]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Breadcrumb HTML.
	 */
	public static function chapter_breadcrumb( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$story_id = get_post_field( 'post_parent', $chapter_id );
		$story_title = get_the_title( $story_id );
		$story_url = get_permalink( $story_id );
		$chapter_title = get_the_title( $chapter_id );

		// If chapter has no title, use the label instead
		if ( empty( $chapter_title ) ) {
			$chapter_title = self::get_chapter_label( $chapter_id );
		}

		$output = '<nav class="chapter-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'fanfiction-manager' ) . '">';
		$output .= '<ol>';
		$output .= sprintf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $story_url ),
			esc_html( $story_title )
		);
		$output .= '<li>' . esc_html( $chapter_title ) . '</li>';
		$output .= '</ol>';
		$output .= '</nav>';

		return $output;
	}

	/**
	 * Chapter story link shortcode
	 *
	 * [chapter-story]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story link HTML.
	 */
	public static function chapter_story( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$story_id = get_post_field( 'post_parent', $chapter_id );
		$story_title = get_the_title( $story_id );
		$story_url = get_permalink( $story_id );

		return sprintf(
			'<a href="%s" class="chapter-story-link">%s</a>',
			esc_url( $story_url ),
			esc_html( $story_title )
		);
	}

	/**
	 * Story chapters dropdown shortcode
	 *
	 * [story-chapters-dropdown story_id="123"]
	 *
	 * Displays a dropdown menu of all chapters for a story.
	 * Includes prologue, numbered chapters, and epilogue.
	 * Uses JavaScript to navigate on selection change.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Dropdown HTML.
	 */
	public static function story_chapters_dropdown( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'story_id' => 0,
			),
			'story-chapters-dropdown'
		);

		// Get story ID from attribute or current context
		$story_id = absint( $atts['story_id'] );
		if ( empty( $story_id ) ) {
			// Try to get from current post
			global $post;
			if ( $post ) {
				if ( 'fanfiction_story' === $post->post_type ) {
					$story_id = $post->ID;
				} elseif ( 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
					$story_id = $post->post_parent;
				}
			}
		}

		// If no story ID found, return empty
		if ( empty( $story_id ) ) {
			return '';
		}

		// Get all chapters for the story
		$chapters = self::get_story_chapters( $story_id );

		// If no chapters, return empty
		if ( empty( $chapters ) ) {
			return '';
		}

		// Get current chapter ID if we're on a chapter page
		$current_chapter_id = 0;
		global $post;
		if ( $post && 'fanfiction_chapter' === $post->post_type ) {
			$current_chapter_id = $post->ID;
		}

		// Build dropdown HTML
		$output = '<select class="fanfic-chapters-dropdown" onchange="if(this.value) window.location.href=this.value" aria-label="' . esc_attr__( 'Jump to chapter', 'fanfiction-manager' ) . '">';
		$output .= '<option value="">' . esc_html__( 'Jump to Chapter', 'fanfiction-manager' ) . '</option>';

		foreach ( $chapters as $chapter ) {
			$selected = ( $chapter->ID === $current_chapter_id ) ? ' selected' : '';
			$aria_current = ( $chapter->ID === $current_chapter_id ) ? ' aria-current="page"' : '';
			$display_title = self::get_chapter_display_title( $chapter->ID, $chapter->post_title );
			$output .= sprintf(
				'<option value="%s"%s%s>%s</option>',
				esc_url( get_permalink( $chapter->ID ) ),
				$selected,
				$aria_current,
				esc_html( $display_title )
			);
		}

		$output .= '</select>';

		return $output;
	}
}
