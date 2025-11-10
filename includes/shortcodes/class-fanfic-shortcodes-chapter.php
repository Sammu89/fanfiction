<?php
/**
 * Chapter Shortcodes Class
 *
 * Handles all chapter display-related shortcodes.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.13
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Chapter
 *
 * Chapter information and display shortcodes.
 *
 * @since 1.0.13
 */
class Fanfic_Shortcodes_Chapter {

	/**
	 * Register chapter shortcodes
	 *
	 * @since 1.0.13
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'fanfic-story-title', array( __CLASS__, 'story_title' ) );
		add_shortcode( 'fanfic-chapter-title', array( __CLASS__, 'chapter_title' ) );
		add_shortcode( 'fanfic-chapter-published', array( __CLASS__, 'chapter_published' ) );
		add_shortcode( 'fanfic-chapter-updated', array( __CLASS__, 'chapter_updated' ) );
		add_shortcode( 'fanfic-chapter-content', array( __CLASS__, 'chapter_content' ) );
	}

	/**
	 * Story title shortcode (for chapter view context)
	 *
	 * Displays the parent story title without a link
	 *
	 * [fanfic-story-title]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Story title.
	 */
	public static function story_title( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$story_id = get_post_field( 'post_parent', $chapter_id );

		if ( ! $story_id ) {
			return '';
		}

		$story_title = get_the_title( $story_id );
		return esc_html( $story_title );
	}

	/**
	 * Chapter title shortcode
	 *
	 * Displays the chapter title without a link
	 *
	 * [fanfic-chapter-title]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter title.
	 */
	public static function chapter_title( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$chapter_title = get_the_title( $chapter_id );
		return esc_html( $chapter_title );
	}

	/**
	 * Chapter published date shortcode
	 *
	 * Displays the chapter publication date (without time)
	 *
	 * [fanfic-chapter-published]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Published date HTML.
	 */
	public static function chapter_published( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$published_date = get_the_date( 'F j, Y', $chapter_id );
		$published_datetime = get_the_date( 'Y-m-d', $chapter_id );

		return sprintf(
			'<time class="fanfic-published-date" datetime="%s" itemprop="datePublished"><span class="fanfic-meta-label">%s</span> %s</time>',
			esc_attr( $published_datetime ),
			esc_html__( 'Published:', 'fanfiction-manager' ),
			esc_html( $published_date )
		);
	}

	/**
	 * Chapter updated date shortcode
	 *
	 * Displays the chapter last modified date (only if different from published date)
	 *
	 * [fanfic-chapter-updated]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Updated date HTML or empty string.
	 */
	public static function chapter_updated( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$published_timestamp = get_post_time( 'U', false, $chapter_id );
		$modified_timestamp  = get_post_modified_time( 'U', false, $chapter_id );

		// Only show if modified date is different from published date (more than 1 day difference)
		if ( abs( $modified_timestamp - $published_timestamp ) < DAY_IN_SECONDS ) {
			return '';
		}

		$modified_date = get_the_modified_date( 'F j, Y', $chapter_id );
		$modified_datetime = get_the_modified_date( 'Y-m-d', $chapter_id );

		return sprintf(
			'<time class="fanfic-updated-date" datetime="%s" itemprop="dateModified"><span class="fanfic-meta-label">%s</span> %s</time>',
			esc_attr( $modified_datetime ),
			esc_html__( 'Updated:', 'fanfiction-manager' ),
			esc_html( $modified_date )
		);
	}

	/**
	 * Chapter content shortcode
	 *
	 * Displays the chapter content
	 *
	 * [fanfic-chapter-content]
	 *
	 * @since 1.0.13
	 * @param array $atts Shortcode attributes.
	 * @return string Chapter content.
	 */
	public static function chapter_content( $atts ) {
		$chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

		if ( ! $chapter_id ) {
			return '';
		}

		$chapter = get_post( $chapter_id );

		if ( ! $chapter ) {
			return '';
		}

		// Apply the_content filters (including wpautop, embeds, etc.)
		$content = apply_filters( 'the_content', $chapter->post_content );

		return $content;
	}
}
