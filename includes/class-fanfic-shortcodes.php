<?php
/**
 * Main Shortcodes Class
 *
 * Handles registration and initialization of all fanfiction shortcodes.
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
 * Class Fanfic_Shortcodes
 *
 * Main shortcode registration and management class.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes {

	/**
	 * Initialize shortcodes
	 *
	 * Loads all shortcode handler classes and registers shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Load shortcode handler classes
		self::load_shortcode_handlers();

		// Initialize each handler
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
	}

	/**
	 * Load shortcode handler classes
	 *
	 * Includes all shortcode handler files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function load_shortcode_handlers() {
		$handlers = array(
			'story',
			'author',
			'navigation',
			'url',
			'taxonomy',
			'search',
			'actions',
			'user',
			'forms',
			'author-forms',
			'comments',
			'stats',
			'utility',
		);

		foreach ( $handlers as $handler ) {
			$file = FANFIC_INCLUDES_DIR . 'shortcodes/class-fanfic-shortcodes-' . $handler . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Register all shortcodes
	 *
	 * Registers shortcodes from all handler classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_shortcodes() {
		// Initialize each handler class
		if ( class_exists( 'Fanfic_Shortcodes_Story' ) ) {
			Fanfic_Shortcodes_Story::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Author' ) ) {
			Fanfic_Shortcodes_Author::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Navigation' ) ) {
			Fanfic_Shortcodes_Navigation::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_URL' ) ) {
			Fanfic_Shortcodes_URL::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Taxonomy' ) ) {
			Fanfic_Shortcodes_Taxonomy::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Search' ) ) {
			Fanfic_Shortcodes_Search::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Actions' ) ) {
			Fanfic_Shortcodes_Actions::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_User' ) ) {
			Fanfic_Shortcodes_User::init();
			Fanfic_Shortcodes_User::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Forms' ) ) {
			Fanfic_Shortcodes_Forms::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Author_Forms' ) ) {
			error_log( 'Fanfic_Shortcodes_Author_Forms class exists - calling register()' );
			Fanfic_Shortcodes_Author_Forms::register();
		} else {
			error_log( 'ERROR: Fanfic_Shortcodes_Author_Forms class does NOT exist!' );
		}

		if ( class_exists( 'Fanfic_Shortcodes_Comments' ) ) {
			Fanfic_Shortcodes_Comments::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Stats' ) ) {
			Fanfic_Shortcodes_Stats::register();
		}

		if ( class_exists( 'Fanfic_Shortcodes_Utility' ) ) {
			Fanfic_Shortcodes_Utility::register();
		}
	}

	/**
	 * Helper: Get current story ID
	 *
	 * Gets the story ID from current context (post, query var, or parent).
	 *
	 * @since 1.0.0
	 * @return int|false Story ID or false if not found.
	 */
	public static function get_current_story_id() {
		global $post;

		// If we're viewing a story post
		if ( $post && 'fanfiction_story' === $post->post_type ) {
			return $post->ID;
		}

		// If we're viewing a chapter, get the parent story
		if ( $post && 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
			return $post->post_parent;
		}

		return false;
	}

	/**
	 * Helper: Get current chapter ID
	 *
	 * Gets the chapter ID from current context.
	 *
	 * @since 1.0.0
	 * @return int|false Chapter ID or false if not found.
	 */
	public static function get_current_chapter_id() {
		global $post;

		if ( $post && 'fanfiction_chapter' === $post->post_type ) {
			return $post->ID;
		}

		return false;
	}

	/**
	 * Helper: Format number with separators
	 *
	 * Formats numbers with thousand separators.
	 *
	 * @since 1.0.0
	 * @param int|string $number The number to format.
	 * @return string Formatted number.
	 */
	public static function format_number( $number ) {
		return number_format_i18n( absint( $number ) );
	}

	/**
	 * Helper: Sanitize shortcode attributes
	 *
	 * Sanitizes and validates shortcode attributes.
	 *
	 * @since 1.0.0
	 * @param array  $atts     User-defined attributes.
	 * @param array  $defaults Default attributes.
	 * @param string $shortcode Shortcode name.
	 * @return array Sanitized attributes.
	 */
	public static function sanitize_atts( $atts, $defaults, $shortcode ) {
		$atts = shortcode_atts( $defaults, $atts, $shortcode );

		// Sanitize each attribute
		foreach ( $atts as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$atts[ $key ] = absint( $value );
			} else {
				$atts[ $key ] = sanitize_text_field( $value );
			}
		}

		return $atts;
	}
}
