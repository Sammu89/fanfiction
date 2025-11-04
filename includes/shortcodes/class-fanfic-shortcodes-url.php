<?php
/**
 * URL Shortcodes Class
 *
 * Handles all URL navigation shortcodes.
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
 * Class Fanfic_Shortcodes_URL
 *
 * URL and link shortcodes for system pages.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_URL {

	/**
	 * Register URL shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'url-login', array( __CLASS__, 'url_login' ) );
		add_shortcode( 'url-register', array( __CLASS__, 'url_register' ) );
		add_shortcode( 'url-archive', array( __CLASS__, 'url_archive' ) );
		add_shortcode( 'url-dashboard', array( __CLASS__, 'url_dashboard' ) );
		add_shortcode( 'url-parent', array( __CLASS__, 'url_parent' ) );
		add_shortcode( 'url-error', array( __CLASS__, 'url_error' ) );
		add_shortcode( 'url-search', array( __CLASS__, 'url_search' ) );
		add_shortcode( 'url-stories', array( __CLASS__, 'url_stories' ) );
	}

	/**
	 * Login URL shortcode
	 *
	 * [url-login]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Login page URL.
	 */
	public static function url_login( $atts ) {
		$url = fanfic_get_login_url();

		if ( empty( $url ) ) {
			$url = wp_login_url();
		}

		return esc_url( $url );
	}

	/**
	 * Register URL shortcode
	 *
	 * [url-register]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Register page URL.
	 */
	public static function url_register( $atts ) {
		$url = fanfic_get_register_url();

		if ( empty( $url ) ) {
			$url = wp_registration_url();
		}

		return esc_url( $url );
	}

	/**
	 * Archive URL shortcode
	 *
	 * [url-archive]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Archive page URL.
	 */
	public static function url_archive( $atts ) {
		$url = fanfic_get_main_url();

		if ( empty( $url ) ) {
			$url = get_post_type_archive_link( 'fanfiction_story' );
		}

		return esc_url( $url );
	}

	/**
	 * Dashboard URL shortcode
	 *
	 * [url-dashboard]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Dashboard page URL.
	 */
	public static function url_dashboard( $atts ) {
		$url = fanfic_get_dashboard_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/dashboard/' );
		}

		return esc_url( $url );
	}

	/**
	 * Parent (main) page URL shortcode
	 *
	 * [url-parent]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Main page URL.
	 */
	public static function url_parent( $atts ) {
		$url = fanfic_get_main_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/' );
		}

		return esc_url( $url );
	}

	/**
	 * Error page URL shortcode
	 *
	 * [url-error]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Error page URL.
	 */
	public static function url_error( $atts ) {
		$url = fanfic_get_error_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/error/' );
		}

		return esc_url( $url );
	}

	/**
	 * Search page URL shortcode
	 *
	 * [url-search]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Search page URL.
	 */
	public static function url_search( $atts ) {
		$url = fanfic_get_search_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/search/' );
		}

		return esc_url( $url );
	}

	/**
	 * Stories archive URL
	 *
	 * [url-stories]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Stories archive URL.
	 */
	public static function url_stories( $atts ) {
		$url = fanfic_get_stories_archive_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/stories/' );
		}

		return esc_url( $url );
	}
}
