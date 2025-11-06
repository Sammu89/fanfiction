<?php
/**
 * Centralized URL Builder Service
 *
 * Provides a single point of URL construction with cached slug data.
 * Replaces scattered URL building logic across multiple files.
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fanfic URL Builder class
 *
 * Singleton service that handles all URL building with cached slug retrieval.
 */
class Fanfic_URL_Builder {

	/**
	 * Singleton instance
	 *
	 * @var Fanfic_URL_Builder|null
	 */
	private static $instance = null;

	/**
	 * Cached slug data (loaded once per request)
	 *
	 * @var array|null
	 */
	private $slugs = null;

	/**
	 * Get singleton instance
	 *
	 * @return Fanfic_URL_Builder
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - loads all slugs into cache
	 */
	private function __construct() {
		$this->slugs = $this->load_all_slugs();
	}

	/**
	 * Load all slugs from database (called once per request)
	 *
	 * @return array All slug data
	 */
	private function load_all_slugs() {
		$chapter_defaults = array(
			'chapter'  => 'chapter',
			'prologue' => 'prologue',
			'epilogue' => 'epilogue',
		);

		$secondary_defaults = array(
			'dashboard' => 'dashboard',
			'user'      => 'user',
			'search'    => 'search',
		);

		return array(
			'base'       => $this->sanitize_slug( get_option( 'fanfic_base_slug', 'fanfiction' ) ),
			'story_path' => $this->sanitize_slug( get_option( 'fanfic_story_path', 'stories' ) ),
			'chapters'   => wp_parse_args( get_option( 'fanfic_chapter_slugs', array() ), $chapter_defaults ),
			'secondary'  => wp_parse_args( get_option( 'fanfic_secondary_paths', array() ), $secondary_defaults ),
			'dynamic'    => get_option( 'fanfic_dynamic_page_slugs', array() ),
			'system'     => get_option( 'fanfic_system_page_slugs', array() ),
		);
	}

	/**
	 * Sanitize a slug value
	 *
	 * @param string $slug Slug to sanitize.
	 * @return string Sanitized slug.
	 */
	private function sanitize_slug( $slug ) {
		return sanitize_title( $slug );
	}

	/**
	 * Build story URL
	 *
	 * @param int|WP_Post $story Story ID or post object.
	 * @return string Story URL.
	 */
	public function get_story_url( $story ) {
		$story = get_post( $story );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return '';
		}

		$slug = $story->post_name;
		if ( empty( $slug ) ) {
			$slug = 'story-' . $story->ID;
		}

		return $this->build_url( array(
			$this->slugs['base'],
			$this->slugs['story_path'],
			$slug,
		) );
	}

	/**
	 * Build chapter URL
	 *
	 * @param int|WP_Post $chapter Chapter ID or post object.
	 * @return string Chapter URL.
	 */
	public function get_chapter_url( $chapter ) {
		$chapter = get_post( $chapter );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return '';
		}

		// Get parent story.
		$story = get_post( $chapter->post_parent );
		if ( ! $story ) {
			return '';
		}

		$story_slug = $story->post_name;
		if ( empty( $story_slug ) ) {
			$story_slug = 'story-' . $story->ID;
		}

		$chapter_type = get_post_meta( $chapter->ID, '_fanfic_chapter_type', true );

		$parts = array(
			$this->slugs['base'],
			$this->slugs['story_path'],
			$story_slug,
		);

		// Add chapter-specific part.
		if ( 'prologue' === $chapter_type ) {
			$parts[] = $this->slugs['chapters']['prologue'];
		} elseif ( 'epilogue' === $chapter_type ) {
			$parts[] = $this->slugs['chapters']['epilogue'];
		} else {
			$chapter_number = get_post_meta( $chapter->ID, '_fanfic_chapter_number', true );
			if ( empty( $chapter_number ) ) {
				$chapter_number = 1;
			}
			$parts[] = $this->slugs['chapters']['chapter'] . '-' . $chapter_number;
		}

		return $this->build_url( $parts );
	}

	/**
	 * Build system or dynamic page URL
	 *
	 * @param string $page_key Page key (dashboard, login, etc.).
	 * @param array  $args     Query parameters.
	 * @return string Page URL.
	 */
	public function get_page_url( $page_key, $args = array() ) {
		// Check if it's a dynamic page.
		if ( isset( $this->slugs['dynamic'][ $page_key ] ) ) {
			return $this->get_dynamic_page_url( $page_key, $args );
		}

		// Check if it's a WordPress page.
		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		if ( isset( $page_ids[ $page_key ] ) && $page_ids[ $page_key ] > 0 ) {
			$url = get_permalink( $page_ids[ $page_key ] );
			return $url ? add_query_arg( $args, $url ) : '';
		}

		return '';
	}

	/**
	 * Build dynamic page URL
	 *
	 * @param string $page_key Dynamic page key.
	 * @param array  $args     Query parameters.
	 * @return string URL.
	 */
	private function get_dynamic_page_url( $page_key, $args = array() ) {
		if ( ! isset( $this->slugs['dynamic'][ $page_key ] ) ) {
			return '';
		}

		$parts = array(
			$this->slugs['base'],
			$this->slugs['dynamic'][ $page_key ],
		);

		// Special handling for members page.
		if ( 'members' === $page_key && isset( $args['member_name'] ) ) {
			$parts[]        = $args['member_name'];
			unset( $args['member_name'] );
		}

		$url = $this->build_url( $parts );

		return ! empty( $args ) ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Build user profile URL
	 *
	 * @param int|string $user User ID or username.
	 * @return string Profile URL.
	 */
	public function get_user_profile_url( $user ) {
		// Get user object.
		if ( is_numeric( $user ) ) {
			$user_obj = get_userdata( $user );
		} else {
			$user_obj = get_user_by( 'login', $user );
		}

		if ( ! $user_obj ) {
			return '';
		}

		$user_slug = isset( $this->slugs['secondary']['user'] ) ? $this->slugs['secondary']['user'] : 'user';

		return $this->build_url( array(
			$this->slugs['base'],
			$user_slug,
			$user_obj->user_login,
		) );
	}

	/**
	 * Build edit URL for any content type
	 *
	 * @param string $type Content type (story, chapter, profile).
	 * @param int    $id   Content ID.
	 * @return string Edit URL with ?action=edit.
	 */
	public function get_edit_url( $type, $id ) {
		$base_url = '';

		switch ( $type ) {
			case 'story':
				$base_url = $this->get_story_url( $id );
				break;

			case 'chapter':
				$base_url = $this->get_chapter_url( $id );
				break;

			case 'profile':
				$base_url = $this->get_user_profile_url( $id );
				break;

			default:
				return '';
		}

		return $base_url ? add_query_arg( 'action', 'edit', $base_url ) : '';
	}

	/**
	 * Generic URL builder from parts
	 *
	 * @param array $parts URL path parts.
	 * @return string Complete URL.
	 */
	public function build_url( $parts ) {
		$path = implode( '/', array_filter( $parts ) );
		return home_url( '/' . $path . '/' );
	}

	/**
	 * Get slug value by key
	 *
	 * @param string $key Slug key (base, story_path, chapters, secondary, dynamic, system).
	 * @return string|array Slug value.
	 */
	public function get_slug( $key ) {
		return isset( $this->slugs[ $key ] ) ? $this->slugs[ $key ] : '';
	}

	/**
	 * Get base slug
	 *
	 * @return string Base slug.
	 */
	public function get_base_slug() {
		return $this->slugs['base'];
	}

	/**
	 * Get story path slug
	 *
	 * @return string Story path slug.
	 */
	public function get_story_path() {
		return $this->slugs['story_path'];
	}

	/**
	 * Get chapter type slugs
	 *
	 * @return array Chapter slugs (chapter, prologue, epilogue).
	 */
	public function get_chapter_slugs() {
		return $this->slugs['chapters'];
	}

	/**
	 * Get secondary path slugs
	 *
	 * @return array Secondary slugs (dashboard, user, search).
	 */
	public function get_secondary_slugs() {
		return $this->slugs['secondary'];
	}

	/**
	 * Get dynamic page slugs
	 *
	 * @return array Dynamic page slugs.
	 */
	public function get_dynamic_slugs() {
		return $this->slugs['dynamic'];
	}

	/**
	 * Invalidate cache (call when slugs are updated)
	 */
	public function flush_cache() {
		$this->slugs = $this->load_all_slugs();
	}
}
