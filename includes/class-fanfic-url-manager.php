<?php
/**
 * Comprehensive URL Manager
 *
 * Handles ALL URL-related functionality in one place:
 * - Slug management with caching
 * - URL building for stories, chapters, pages
 * - Rewrite rules registration
 * - Dynamic pages system
 * - Template loading
 * - Permalink filtering
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fanfic URL Manager class
 *
 * Singleton service that centralizes all URL management.
 * Replaces: Fanfic_Rewrite, Fanfic_Dynamic_Pages, and scattered URL building logic.
 */
class Fanfic_URL_Manager {

	/**
	 * Singleton instance
	 *
	 * @var Fanfic_URL_Manager|null
	 */
	private static $instance = null;

	/**
	 * Cached slug data (loaded once per request)
	 *
	 * @var array|null
	 */
	private $slugs = null;

	/**
	 * Default base slug
	 *
	 * @var string
	 */
	const DEFAULT_BASE_SLUG = 'fanfiction';

	/**
	 * Maximum slug length
	 *
	 * @var int
	 */
	const MAX_SLUG_LENGTH = 50;

	/**
	 * Get singleton instance
	 *
	 * @return Fanfic_URL_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - loads all slugs into cache and sets up hooks
	 */
	private function __construct() {
		$this->slugs = $this->load_all_slugs();
		$this->init_hooks();
	}

	/**
	 * Initialize all hooks
	 */
	private function init_hooks() {
		// Rewrite rules.
		add_action( 'init', array( $this, 'register_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );

		// Permalink filtering.
		add_filter( 'post_type_link', array( $this, 'filter_story_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'filter_chapter_permalink' ), 10, 2 );
		add_filter( 'preview_post_link', array( $this, 'filter_preview_link' ), 10, 2 );

		// Virtual page system - creates fake pages that work with any theme.
		add_action( 'template_redirect', array( $this, 'setup_virtual_pages' ), 1 );
		add_filter( 'the_posts', array( $this, 'create_virtual_page_post' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'inject_virtual_page_content' ) );

		// Setup virtual page postdata at proper time (after query is resolved).
		add_action( 'template_redirect', array( $this, 'setup_virtual_page_postdata' ), 5 );

		// Redirects.
		add_action( 'template_redirect', array( $this, 'handle_old_slug_redirects' ) );
	}

	/**
	 * Load all slugs from database (called once per request)
	 *
	 * @return array All slug data.
	 */
	private function load_all_slugs() {
		$chapter_defaults = array(
			'chapter'  => 'chapter',
			'prologue' => 'prologue',
			'epilogue' => 'epilogue',
		);

		// Load dynamic page slugs from individual options (same pattern as base and story_path)
		$dynamic_slugs = array(
			'dashboard'    => $this->sanitize_slug( get_option( 'fanfic_dashboard_slug', 'dashboard' ) ),
			'create-story' => $this->sanitize_slug( get_option( 'fanfic_create_story_slug', 'create-story' ) ),
			'search'       => $this->sanitize_slug( get_option( 'fanfic_search_slug', 'search' ) ),
			'members'      => $this->sanitize_slug( get_option( 'fanfic_members_slug', 'members' ) ),
		);

		return array(
			'base'       => $this->sanitize_slug( get_option( 'fanfic_base_slug', self::DEFAULT_BASE_SLUG ) ),
			'story_path' => $this->sanitize_slug( get_option( 'fanfic_story_path', 'stories' ) ),
			'chapters'   => wp_parse_args( get_option( 'fanfic_chapter_slugs', array() ), $chapter_defaults ),
			'dynamic'    => $dynamic_slugs,
			'system'     => get_option( 'fanfic_system_page_slugs', array() ),
		);
	}

	// ========================================================================
	// REWRITE RULES
	// ========================================================================

	/**
	 * Register all rewrite rules
	 */
	public function register_rewrite_rules() {
		$this->register_story_rules();
		$this->register_dynamic_page_rules();
	}

	/**
	 * Register story and chapter rewrite rules
	 */
	private function register_story_rules() {
		$base         = $this->slugs['base'];
		$story_path   = $this->slugs['story_path'];
		$chapter_slugs = $this->slugs['chapters'];

		// Prologue: /fanfiction/stories/{story-slug}/prologue/
		add_rewrite_rule(
			'^' . $base . '/' . $story_path . '/([^/]+)/' . $chapter_slugs['prologue'] . '/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_type=prologue',
			'top'
		);

		// Epilogue: /fanfiction/stories/{story-slug}/epilogue/
		add_rewrite_rule(
			'^' . $base . '/' . $story_path . '/([^/]+)/' . $chapter_slugs['epilogue'] . '/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_type=epilogue',
			'top'
		);

		// Chapter: /fanfiction/stories/{story-slug}/chapter-{number}/
		add_rewrite_rule(
			'^' . $base . '/' . $story_path . '/([^/]+)/' . $chapter_slugs['chapter'] . '-([0-9]+)/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_number=$matches[2]',
			'top'
		);

		// Story: /fanfiction/stories/{story-slug}/
		add_rewrite_rule(
			'^' . $base . '/' . $story_path . '/([^/]+)/?$',
			'index.php?fanfiction_story=$matches[1]&post_type=fanfiction_story',
			'top'
		);
	}

	/**
	 * Register dynamic page rewrite rules
	 */
	private function register_dynamic_page_rules() {
		$base  = $this->slugs['base'];
		$slugs = $this->slugs['dynamic'];

		// Dashboard: /fanfiction/dashboard/
		if ( isset( $slugs['dashboard'] ) ) {
			add_rewrite_rule(
				'^' . $base . '/' . $slugs['dashboard'] . '/?$',
				'index.php?fanfic_page=dashboard',
				'top'
			);
		}

		// Create Story: /fanfiction/create-story/
		if ( isset( $slugs['create-story'] ) ) {
			add_rewrite_rule(
				'^' . $base . '/' . $slugs['create-story'] . '/?$',
				'index.php?fanfic_page=create-story',
				'top'
			);
		}

		// Search: /fanfiction/search/
		if ( isset( $slugs['search'] ) ) {
			add_rewrite_rule(
				'^' . $base . '/' . $slugs['search'] . '/?$',
				'index.php?fanfic_page=search',
				'top'
			);
		}

		// Members: /fanfiction/members/ or /fanfiction/members/username/
		if ( isset( $slugs['members'] ) ) {
			add_rewrite_rule(
				'^' . $base . '/' . $slugs['members'] . '/([^/]+)/?$',
				'index.php?fanfic_page=members&member_name=$matches[1]',
				'top'
			);
			add_rewrite_rule(
				'^' . $base . '/' . $slugs['members'] . '/?$',
				'index.php?fanfic_page=members',
				'top'
			);
		}
	}

	/**
	 * Register custom query variables
	 *
	 * @param array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'fanfiction_story';
		$vars[] = 'fanfiction_chapter';
		$vars[] = 'chapter_number';
		$vars[] = 'chapter_type';
		$vars[] = 'fanfic_page';
		$vars[] = 'fanfic_user';
		$vars[] = 'story_action';
		$vars[] = 'chapter_action';
		$vars[] = 'member_name';
		return $vars;
	}

	// ========================================================================
	// URL BUILDING
	// ========================================================================

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

	// ========================================================================
	// PERMALINK FILTERING
	// ========================================================================

	/**
	 * Filter the permalink for story posts
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post object.
	 * @return string The filtered permalink.
	 */
	public function filter_story_permalink( $permalink, $post ) {
		if ( 'fanfiction_story' !== $post->post_type ) {
			return $permalink;
		}

		// For draft/pending posts or posts without pretty permalinks, build one.
		if ( false !== strpos( $permalink, '?' ) || false !== strpos( $permalink, 'post_type=' ) ) {
			return $this->get_story_url( $post );
		}

		// Handle placeholder replacement.
		if ( false !== strpos( $permalink, '%fanfiction_story%' ) ) {
			$slug = $post->post_name;
			if ( empty( $slug ) ) {
				$slug = 'story-' . $post->ID;
			}
			$permalink = str_replace(
				'%fanfiction_story%',
				$this->slugs['base'] . '/' . $this->slugs['story_path'] . '/' . $slug,
				$permalink
			);
		}

		return $permalink;
	}

	/**
	 * Filter the permalink for chapter posts
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post object.
	 * @return string The filtered permalink.
	 */
	public function filter_chapter_permalink( $permalink, $post ) {
		if ( 'fanfiction_chapter' !== $post->post_type ) {
			return $permalink;
		}

		// For draft/pending posts or posts without pretty permalinks, build one.
		if ( false !== strpos( $permalink, '?' ) || false !== strpos( $permalink, 'post_type=' ) ) {
			$built_url = $this->get_chapter_url( $post );
			if ( ! empty( $built_url ) ) {
				return $built_url;
			}
		}

		return $permalink;
	}

	/**
	 * Filter preview links
	 *
	 * @param string  $preview_link The preview link URL.
	 * @param WP_Post $post         The post object.
	 * @return string The filtered preview link.
	 */
	public function filter_preview_link( $preview_link, $post ) {
		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return $preview_link;
		}

		$permalink = get_permalink( $post );

		if ( ! $permalink || false !== strpos( $permalink, '?' ) ) {
			if ( 'fanfiction_story' === $post->post_type ) {
				$permalink = $this->get_story_url( $post );
			} elseif ( 'fanfiction_chapter' === $post->post_type ) {
				$permalink = $this->get_chapter_url( $post );
			}
		}

		if ( $permalink ) {
			$preview_link = add_query_arg( 'preview', 'true', $permalink );
		}

		return $preview_link;
	}

	// ========================================================================
	// TEMPLATE LOADING
	// ========================================================================

	// ========================================================================
	// VIRTUAL PAGE SYSTEM
	// ========================================================================

	/**
	 * Setup virtual pages for dynamic content
	 *
	 * Detects when we're on a dynamic page and prepares WordPress to treat it
	 * as a normal page that will work with any theme's page.php template.
	 *
	 * @since 2.0.0
	 */
	public function setup_virtual_pages() {
		$fanfic_page = get_query_var( 'fanfic_page' );

		if ( empty( $fanfic_page ) ) {
			return;
		}

		// Tell WordPress this is a page request.
		global $wp_query;
		$wp_query->is_page        = true;
		$wp_query->is_singular    = true;
		$wp_query->is_home        = false;
		$wp_query->is_archive     = false;
		$wp_query->is_category    = false;
		$wp_query->is_404         = false;
	}

	/**
	 * Setup virtual page postdata
	 *
	 * Sets up the global $post and all WordPress postdata variables at the proper time.
	 * This runs after the query is resolved but before template loading.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_virtual_page_postdata() {
		$fanfic_page = get_query_var( 'fanfic_page' );

		if ( empty( $fanfic_page ) ) {
			return;
		}

		global $wp_query, $post;

		// Get the virtual post that was created in the_posts filter.
		if ( empty( $wp_query->posts ) || ! isset( $wp_query->posts[0]->fanfic_page_key ) ) {
			return;
		}

		$virtual_post = $wp_query->posts[0];

		// Set all WordPress globals properly.
		// This is CRITICAL for theme compatibility (breadcrumbs, nav menus, etc.).
		$post = $virtual_post;
		$wp_query->post = $virtual_post;
		$wp_query->posts = array( $virtual_post );

		// These two are essential for get_queried_object() to work.
		// Without these, breadcrumbs and other theme features will fail.
		$wp_query->queried_object = $virtual_post;
		$wp_query->queried_object_id = $virtual_post->ID;

		// Setup postdata for template tags (the_title, the_content, etc.).
		setup_postdata( $virtual_post );
	}

	/**
	 * Create a virtual WP_Post object for dynamic pages
	 *
	 * This creates a fake post that WordPress and themes will treat as a real page.
	 *
	 * @since 2.0.0
	 * @param array    $posts  Array of posts.
	 * @param WP_Query $query  The WP_Query object.
	 * @return array Modified posts array.
	 */
	public function create_virtual_page_post( $posts, $query ) {
		// Only modify main query.
		if ( ! $query->is_main_query() ) {
			return $posts;
		}

		$fanfic_page = get_query_var( 'fanfic_page' );

		if ( empty( $fanfic_page ) ) {
			return $posts;
		}

		// Get page configuration.
		$page_config = $this->get_virtual_page_config( $fanfic_page );

		if ( ! $page_config ) {
			return $posts;
		}

		// Create fake post object.
		$post = new stdClass();
		$post->ID                    = -999; // Negative ID to avoid conflicts.
		$post->post_author           = 1;
		$post->post_date             = current_time( 'mysql' );
		$post->post_date_gmt         = current_time( 'mysql', 1 );
		$post->post_content          = ''; // Content will be injected via filter.
		$post->post_title            = $page_config['title'];
		$post->post_excerpt          = '';
		$post->post_status           = 'publish';
		$post->comment_status        = 'closed';
		$post->ping_status           = 'closed';
		$post->post_password         = '';
		$post->post_name             = $fanfic_page;
		$post->to_ping               = '';
		$post->pinged                = '';
		$post->post_modified         = current_time( 'mysql' );
		$post->post_modified_gmt     = current_time( 'mysql', 1 );
		$post->post_content_filtered = '';
		$post->post_parent           = 0;
		$post->guid                  = get_home_url( '/' . $fanfic_page );
		$post->menu_order            = 0;
		$post->post_type             = 'page';
		$post->post_mime_type        = '';
		$post->comment_count         = 0;
		$post->filter                = 'raw';

		// Convert to WP_Post object.
		$wp_post = new WP_Post( $post );

		// Store page key AFTER conversion (custom properties must be set on WP_Post object).
		// Setting it before conversion causes it to be lost, as WP_Post only copies known properties.
		$wp_post->fanfic_page_key = $fanfic_page;

		// For FSE themes, use default template (theme will provide page.html or similar)
		// For classic themes, virtual pages will use our custom template via template_include filter
		$wp_post->page_template = 'default';

		$posts = array( $wp_post );

		// Update query object (postdata will be set later in template_redirect).
		global $wp_query;
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		$wp_query->max_num_pages = 1;

		return $posts;
	}

	/**
	 * Inject content into virtual pages
	 *
	 * Replaces the empty content with the appropriate shortcode.
	 *
	 * @since 2.0.0
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function inject_virtual_page_content( $content ) {
		global $post;

		// Only process our virtual pages.
		if ( ! isset( $post->fanfic_page_key ) ) {
			return $content;
		}

		// Only process in the main loop.
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$page_config = $this->get_virtual_page_config( $post->fanfic_page_key );

		if ( ! $page_config || empty( $page_config['shortcode'] ) ) {
			return $content;
		}

		// Return the shortcode - WordPress will process it automatically.
		return do_shortcode( '[' . $page_config['shortcode'] . ']' );
	}

	/**
	 * Get configuration for a virtual page
	 *
	 * @since 2.0.0
	 * @param string $page_key Page key (dashboard, create-story, etc.).
	 * @return array|false Page configuration or false if not found.
	 */
	private function get_virtual_page_config( $page_key ) {
		$pages = array(
			'dashboard'    => array(
				'title'     => __( 'Dashboard', 'fanfiction-manager' ),
				'shortcode' => 'user-dashboard',
			),
			'create-story' => array(
				'title'     => __( 'Create Story', 'fanfiction-manager' ),
				'shortcode' => 'author-create-story-form',
			),
			'search'       => array(
				'title'     => __( 'Search', 'fanfiction-manager' ),
				'shortcode' => 'search-results',
			),
			'members'      => array(
				'title'     => __( 'Members', 'fanfiction-manager' ),
				'shortcode' => 'user-profile',
			),
		);

		return isset( $pages[ $page_key ] ) ? $pages[ $page_key ] : false;
	}

	// ========================================================================
	// SLUG MANAGEMENT
	// ========================================================================

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
	 * Get dynamic page slugs
	 *
	 * @return array Dynamic page slugs.
	 */
	public function get_dynamic_slugs() {
		return $this->slugs['dynamic'];
	}

	/**
	 * Get all slugs
	 *
	 * @return array All slug data.
	 */
	public function get_all_slugs() {
		return $this->slugs;
	}

	/**
	 * Validate a slug
	 *
	 * @param string $slug The slug to validate.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_slug( $slug ) {
		if ( empty( $slug ) ) {
			return new WP_Error(
				'empty_slug',
				__( 'Slug cannot be empty.', 'fanfiction-manager' )
			);
		}

		if ( strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			return new WP_Error(
				'slug_too_long',
				sprintf( __( 'Slug cannot exceed %d characters.', 'fanfiction-manager' ), self::MAX_SLUG_LENGTH )
			);
		}

		if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			return new WP_Error(
				'invalid_slug_format',
				__( 'Slug can only contain lowercase letters, numbers, and hyphens.', 'fanfiction-manager' )
			);
		}

		// Check against reserved WordPress slugs.
		$reserved_slugs = array(
			'wp-admin',
			'wp-content',
			'wp-includes',
			'admin',
			'login',
			'register',
			'wp-login',
			'feed',
			'rss',
			'rss2',
			'atom',
			'rdf',
		);

		if ( in_array( $slug, $reserved_slugs, true ) ) {
			return new WP_Error(
				'reserved_slug',
				__( 'This slug is reserved by WordPress and cannot be used.', 'fanfiction-manager' )
			);
		}

		return true;
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
	 * Check if a page is a dynamic page
	 *
	 * @param string $page_key Page key.
	 * @return bool Whether page is dynamic.
	 */
	public function is_dynamic_page( $page_key ) {
		return isset( $this->slugs['dynamic'][ $page_key ] );
	}

	// ========================================================================
	// REDIRECTS & CACHE
	// ========================================================================

	/**
	 * Handle redirects from old slugs
	 */
	public function handle_old_slug_redirects() {
		$previous_slugs = get_option( 'fanfic_previous_slugs', array() );

		if ( empty( $previous_slugs ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_uri = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

		foreach ( $previous_slugs as $old_slug => $new_slug ) {
			if ( 0 === strpos( $request_uri, $old_slug . '/' ) ) {
				$new_url = str_replace( $old_slug . '/', $new_slug . '/', $request_uri );
				$new_url = home_url( '/' . $new_url );
				wp_safe_redirect( $new_url, 301 );
				exit;
			}
		}
	}

	/**
	 * Invalidate cache (call when slugs are updated)
	 */
	public function flush_cache() {
		$this->slugs = $this->load_all_slugs();
	}

	/**
	 * Flush rewrite rules
	 */
	public function flush_rules() {
		flush_rewrite_rules();
	}

	// ========================================================================
	// DYNAMIC PAGE MANAGEMENT
	// ========================================================================

	/**
	 * Get list of dynamic page keys
	 *
	 * @return array Array of dynamic page keys.
	 */
	public function get_dynamic_pages() {
		return array_keys( $this->slugs['dynamic'] );
	}

	/**
	 * Get dynamic page slugs
	 *
	 * @return array Dynamic page slugs.
	 */
	public function get_slugs() {
		return $this->slugs['dynamic'];
	}

	/**
	 * Update dynamic page slugs
	 *
	 * @param array $slugs Array of slug values.
	 * @return bool Whether update was successful.
	 */
	public function update_slugs( $slugs ) {
		$result = update_option( 'fanfic_dynamic_page_slugs', $slugs );
		if ( $result ) {
			$this->flush_cache();
		}
		return $result;
	}
}
