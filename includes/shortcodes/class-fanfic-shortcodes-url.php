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
		add_shortcode( 'url-password-reset', array( __CLASS__, 'url_password_reset' ) );
		add_shortcode( 'url-create-story', array( __CLASS__, 'url_create_story' ) );
		add_shortcode( 'url-edit-story', array( __CLASS__, 'url_edit_story' ) );
		add_shortcode( 'url-edit-chapter', array( __CLASS__, 'url_edit_chapter' ) );
		add_shortcode( 'url-edit-profile', array( __CLASS__, 'url_edit_profile' ) );
		add_shortcode( 'url-members', array( __CLASS__, 'url_members' ) );
		add_shortcode( 'url-main', array( __CLASS__, 'url_main' ) );
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
		$url = fanfic_get_story_archive_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/stories/' );
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
		$url = fanfic_get_story_archive_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/stories/' );
		}

		return esc_url( $url );
	}

	/**
	 * Password Reset URL shortcode
	 *
	 * [url-password-reset]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Password reset page URL.
	 */
	public static function url_password_reset( $atts ) {
		$url = fanfic_get_password_reset_url();

		if ( empty( $url ) ) {
			$url = wp_lostpassword_url();
		}

		return esc_url( $url );
	}

	/**
	 * Create Story URL shortcode
	 *
	 * [url-create-story]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Create story page URL.
	 */
	public static function url_create_story( $atts ) {
		$url = fanfic_get_create_story_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/create-story/' );
		}

		return esc_url( $url );
	}

	/**
	 * Edit Story URL shortcode
	 *
	 * [url-edit-story]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Edit story page URL.
	 */
	public static function url_edit_story( $atts ) {
		$url = fanfic_get_edit_story_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/edit-story/' );
		}

		return esc_url( $url );
	}

	/**
	 * Edit Chapter URL shortcode
	 *
	 * [url-edit-chapter]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Edit chapter page URL.
	 */
	public static function url_edit_chapter( $atts ) {
		$url = fanfic_get_edit_chapter_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/edit-chapter/' );
		}

		return esc_url( $url );
	}

	/**
	 * Edit Profile URL shortcode
	 *
	 * [url-edit-profile]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Edit profile page URL.
	 */
	public static function url_edit_profile( $atts ) {
		$url = fanfic_get_edit_profile_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/edit-profile/' );
		}

		return esc_url( $url );
	}

	/**
	 * Members URL shortcode
	 *
	 * [url-members]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Members page URL.
	 */
	public static function url_members( $atts ) {
		$url = fanfic_get_members_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/members/' );
		}

		return esc_url( $url );
	}

	/**
	 * Main page URL shortcode
	 *
	 * [url-main]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Main page URL.
	 */
	public static function url_main( $atts ) {
		$url = fanfic_get_main_url();

		if ( empty( $url ) ) {
			$url = home_url( '/fanfiction/' );
		}

		return esc_url( $url );
	}
}
