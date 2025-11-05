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
	 * Get all chapters for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return array Array of chapter posts.
	 */
	private static function get_story_chapters( $story_id ) {
		return get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );
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
			$output .= sprintf(
				'<option value="%s"%s%s>%s</option>',
				esc_url( get_permalink( $chapter->ID ) ),
				$selected,
				$aria_current,
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
	 * Chapters list shortcode
	 *
	 * [chapters-list]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Chapters list HTML.
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

		$output = '<ol class="chapters-list">';

		foreach ( $chapters as $chapter ) {
			$output .= sprintf(
				'<li class="chapter-item"><a href="%s" class="chapter-link">%s</a></li>',
				esc_url( get_permalink( $chapter->ID ) ),
				esc_html( $chapter->post_title )
			);
		}

		$output .= '</ol>';

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
			$output .= sprintf(
				'<option value="%s"%s%s>%s</option>',
				esc_url( get_permalink( $chapter->ID ) ),
				$selected,
				$aria_current,
				esc_html( $chapter->post_title )
			);
		}

		$output .= '</select>';

		return $output;
	}
}
