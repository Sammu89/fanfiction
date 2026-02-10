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
		add_filter( 'body_class', array( $this, 'add_body_classes' ) );
		add_filter( 'pre_get_shortlink', array( $this, 'shortlink_for_virtual_pages' ), 10, 4 );

		// Setup virtual page postdata using 'wp' hook (most stable for persisting globals).
		// Priority 999 ensures it runs AFTER other plugins/code that might modify globals.
		add_action( 'wp', array( $this, 'setup_virtual_page_postdata' ), 999 );

		// Redirects.
		add_action( 'template_redirect', array( $this, 'handle_old_slug_redirects' ) );
	}

	/**
	 * Load all slugs from database (called once per request)
	 *
	 * @return array All slug data.
	 */
	private function load_all_slugs() {
		$use_base_slug = get_option( 'fanfic_use_base_slug', true );

		$chapter_defaults = array(
			'chapter'  => 'chapter',
			'prologue' => 'prologue',
			'epilogue' => 'epilogue',
		);

		// Load dynamic page slugs from individual options (same pattern as base and story_path)
		$dynamic_slugs = array(
			'dashboard'    => $this->sanitize_slug( get_option( 'fanfic_dashboard_slug', 'dashboard' ) ),
			'members'      => $this->sanitize_slug( get_option( 'fanfic_members_slug', 'members' ) ),
		);

		return array(
			// Base is empty when base slug mode is off — rewrite rules and URL builders
			// use array_filter / prefix helpers to omit it automatically.
			'base'       => $use_base_slug ? $this->sanitize_slug( get_option( 'fanfic_base_slug', self::DEFAULT_BASE_SLUG ) ) : '',
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
		$base          = $this->slugs['base'];
		$story_path    = $this->slugs['story_path'];
		$chapter_slugs = $this->slugs['chapters'];
		$page_ids      = get_option( 'fanfic_system_page_ids', array() );
		$stories_page_id = isset( $page_ids['stories'] ) ? absint( $page_ids['stories'] ) : 0;
		// When base slug is off, $base is '' — prefix becomes '' so rules start at root.
		$prefix = empty( $base ) ? '' : $base . '/';

		// Stories listing endpoint: /[base/]stories/
		// Route explicitly to the configured stories page so this URL works even
		// when the story CPT archive is disabled.
		if ( $stories_page_id > 0 ) {
			add_rewrite_rule(
				'^' . $prefix . $story_path . '/?$',
				'index.php?page_id=' . $stories_page_id,
				'top'
			);

			add_rewrite_rule(
				'^' . $prefix . $story_path . '/page/([0-9]{1,})/?$',
				'index.php?page_id=' . $stories_page_id . '&paged=$matches[1]',
				'top'
			);
		}

		// Prologue: /[base/]stories/{story-slug}/prologue/
		add_rewrite_rule(
			'^' . $prefix . $story_path . '/([^/]+)/' . $chapter_slugs['prologue'] . '/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_type=prologue',
			'top'
		);

		// Epilogue: /[base/]stories/{story-slug}/epilogue/
		add_rewrite_rule(
			'^' . $prefix . $story_path . '/([^/]+)/' . $chapter_slugs['epilogue'] . '/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_type=epilogue',
			'top'
		);

		// Chapter: /[base/]stories/{story-slug}/chapter-{number}/
		add_rewrite_rule(
			'^' . $prefix . $story_path . '/([^/]+)/' . $chapter_slugs['chapter'] . '-([0-9]+)/?$',
			'index.php?fanfiction_chapter=$matches[1]&chapter_number=$matches[2]',
			'top'
		);

		// Story: /[base/]stories/{story-slug}/
		add_rewrite_rule(
			'^' . $prefix . $story_path . '/([^/]+)/?$',
			'index.php?fanfiction_story=$matches[1]&post_type=fanfiction_story',
			'top'
		);
	}

	/**
	 * Register dynamic page rewrite rules
	 */
	private function register_dynamic_page_rules() {
		$base   = $this->slugs['base'];
		$slugs  = $this->slugs['dynamic'];
		$prefix = empty( $base ) ? '' : $base . '/';

		// Dashboard: /[base/]dashboard/
		if ( isset( $slugs['dashboard'] ) ) {
			add_rewrite_rule(
				'^' . $prefix . $slugs['dashboard'] . '/?$',
				'index.php?fanfic_page=dashboard',
				'top'
			);
		}

		// Members: /[base/]members/ or /[base/]members/username/
		if ( isset( $slugs['members'] ) ) {
			add_rewrite_rule(
				'^' . $prefix . $slugs['members'] . '/([^/]+)/?$',
				'index.php?fanfic_page=members&member_name=$matches[1]',
				'top'
			);
			add_rewrite_rule(
				'^' . $prefix . $slugs['members'] . '/?$',
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
		// Special handling for create-story: use main page with ?action=create-story
		if ( 'create-story' === $page_key ) {
			$page_ids = get_option( 'fanfic_system_page_ids', array() );
			if ( isset( $page_ids['main'] ) && $page_ids['main'] > 0 ) {
				$url = get_permalink( $page_ids['main'] );
				if ( $url ) {
					$args['action'] = 'create-story';
					return add_query_arg( $args, $url );
				}
			}
			return '';
		}

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
	 * @param int|string|WP_User $user User ID, username, or WP_User object.
	 * @return string Profile URL.
	 */
	public function get_user_profile_url( $user ) {
		// Get user object.
		if ( $user instanceof WP_User ) {
			$user_obj = $user;
		} elseif ( is_numeric( $user ) ) {
			$user_obj = get_userdata( $user );
		} else {
			$user_obj = get_user_by( 'login', $user );
		}

		if ( ! $user_obj ) {
			return '';
		}

		// Use the members slug (same slug for member directory and individual profiles)
		$members_slug = isset( $this->slugs['dynamic']['members'] ) ? $this->slugs['dynamic']['members'] : 'members';

		return $this->build_url( array(
			$this->slugs['base'],
			$members_slug,
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
			$base_prefix = empty( $this->slugs['base'] ) ? '' : $this->slugs['base'] . '/';
			$permalink   = str_replace(
				'%fanfiction_story%',
				$base_prefix . $this->slugs['story_path'] . '/' . $slug,
				$permalink
			);
		}

		return $permalink;
	}

	/**
	 * Filter the permalink for chapter posts
	 *
	 * Always use custom URL structure based on chapter number/type metadata,
	 * not WordPress's default hierarchical permalink structure.
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post object.
	 * @return string The filtered permalink.
	 */
	public function filter_chapter_permalink( $permalink, $post ) {
		if ( 'fanfiction_chapter' !== $post->post_type ) {
			return $permalink;
		}

		// Always build chapter URLs using our custom structure (story-slug/chapter-N)
		// instead of WordPress's hierarchical structure (story-slug/chapter-slug)
		$built_url = $this->get_chapter_url( $post );

		return ! empty( $built_url ) ? $built_url : $permalink;
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
	 * Detects when we're on a dynamic page and sets WordPress query flags.
	 * The actual WP_Post object is created in the_posts filter.
	 *
	 * @since 2.0.0
	 */
	public function setup_virtual_pages() {
		$fanfic_page = get_query_var( 'fanfic_page' );

		// Differentiate between members archive and single profile
		if ( 'members' === $fanfic_page && get_query_var( 'member_name' ) ) {
			set_query_var( 'fanfic_page', 'member_profile' );
			$fanfic_page = 'member_profile'; // Update for current function scope
		}

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
	 * Backup function that ensures global $post is set on the 'wp' hook.
	 * This serves as a safety net in case the_posts filter doesn't run or
	 * something resets the globals between the_posts and template_redirect.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_virtual_page_postdata() {
		$fanfic_page = get_query_var( 'fanfic_page' );

		if ( empty( $fanfic_page ) ) {
			return;
		}

		global $wp_query;

		// Get the virtual post that was created in the_posts filter.
		if ( empty( $wp_query->posts ) || ! isset( $wp_query->posts[0]->fanfic_page_key ) ) {
			return;
		}

		$virtual_post = $wp_query->posts[0];

		// Set all WordPress globals using $GLOBALS directly.
		// Using $GLOBALS is more reliable than 'global' keyword for superglobals.
		$GLOBALS['post'] = $virtual_post;
		$wp_query->post = $virtual_post;
		$wp_query->posts = array( $virtual_post );

		// Essential for get_queried_object() to work with breadcrumbs and nav menus.
		$wp_query->queried_object = $virtual_post;
		$wp_query->queried_object_id = $virtual_post->ID;

		// Setup postdata for template tags (the_title, the_content, etc.).
		setup_postdata( $virtual_post );
	}

	/**
	 * Create a virtual WP_Post object for dynamic pages
	 *
	 * This creates a fake post that WordPress and themes will treat as a real page.
	 * Uses wp_cache_set to prevent WordPress from querying the database.
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
		if ( 'members' === $fanfic_page && get_query_var( 'member_name' ) ) {
			// Convert member list to profile view before building the virtual page post.
			set_query_var( 'fanfic_page', 'member_profile' );
			$fanfic_page = 'member_profile';
		}

		if ( empty( $fanfic_page ) ) {
			return $posts;
		}

		// Get page configuration.
		$page_config = $this->get_virtual_page_config( $fanfic_page );

		if ( ! $page_config ) {
			return $posts;
		}

		// Dynamically set title for member_profile
		if ( 'member_profile' === $fanfic_page ) {
			$member_name = get_query_var( 'member_name' );
			$user = get_user_by( 'login', $member_name );
			if ( $user ) {
				$page_config['title'] = $user->display_name;
			} else {
				$page_config['title'] = __( 'User not found', 'fanfiction-manager' );
			}
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

		// Add to cache to prevent WordPress from firing database queries for this fake post.
		// This is critical for performance and prevents WordPress from trying to fetch post ID -999.
		wp_cache_set( $wp_post->ID, $wp_post, 'posts' );
		wp_cache_set( $wp_post->ID, $wp_post, 'post_meta' );

		$posts = array( $wp_post );

		// Update query object.
		$query->post_count = 1;
		$query->found_posts = 1;
		$query->max_num_pages = 1;

		// Set query objects immediately for breadcrumbs and other early theme features.
		// Using $GLOBALS directly is more reliable than 'global' keyword for superglobals.
		$GLOBALS['post'] = $wp_post;
		$query->post = $wp_post;
		$query->queried_object = $wp_post;
		$query->queried_object_id = $wp_post->ID;

		return $posts;
	}

	/**
	 * Inject content into virtual pages
	 *
	 * Replaces the empty content with the appropriate shortcode or template.
	 *
	 * @since 2.0.0
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function inject_virtual_page_content( $content ) {
		// Use $GLOBALS for more reliable access to superglobals.
		$post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		// Only process our virtual pages.
		if ( ! $post || ! isset( $post->fanfic_page_key ) ) {
			return $content;
		}

		// Only process in the main loop.
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$page_config = $this->get_virtual_page_config( $post->fanfic_page_key );

		if ( ! $page_config ) {
			return $content;
		}

		// Handle edit profile view on member profile URLs.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'member_profile' === $post->fanfic_page_key && 'edit' === $action ) {
			$member_name = get_query_var( 'member_name' );
			$user = $member_name ? get_user_by( 'login', $member_name ) : false;

			if ( ! $user ) {
				return '<div class="fanfic-error-notice" role="alert"><p>' .
					esc_html__( 'User not found.', 'fanfiction-manager' ) .
				'</p></div>';
			}

			if ( ! function_exists( 'fanfic_current_user_can_edit' ) || ! fanfic_current_user_can_edit( 'profile', $user->ID ) ) {
				return '<div class="fanfic-error-notice" role="alert"><p>' .
					esc_html__( 'You do not have permission to edit this profile.', 'fanfiction-manager' ) .
				'</p></div>';
			}

			$template_path = FANFIC_PLUGIN_DIR . 'templates/template-edit-profile.php';
			if ( file_exists( $template_path ) ) {
				ob_start();
				global $fanfic_load_template;
				$fanfic_load_template = true;
				include $template_path;
				return ob_get_clean();
			}
		}

		// Check if we should load a template directly
		if ( ! empty( $page_config['template'] ) ) {
			// Load template file
			$template_name = 'template-' . $page_config['template'] . '.php';
			$template_path = FANFIC_PLUGIN_DIR . 'templates/' . $template_name;

			if ( file_exists( $template_path ) ) {
				ob_start();
				global $fanfic_load_template;
				$fanfic_load_template = true;
				include $template_path;
				return ob_get_clean();
			}
		}

		// Fallback to shortcode-based content
		if ( ! empty( $page_config['shortcode'] ) ) {
			return do_shortcode( '[' . $page_config['shortcode'] . ']' );
		}

		return $content;
	}

	/**
	 * Add body classes for dynamic pages
	 *
	 * @since 2.0.0
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes.
	 */
	public function add_body_classes( $classes ) {
		$fanfic_page = get_query_var( 'fanfic_page' );
		if ( ! empty( $fanfic_page ) ) {
			$classes[] = 'fanfic-page';
			$classes[] = 'fanfic-page-' . sanitize_html_class( $fanfic_page );
		}
		if ( 'member_profile' === $fanfic_page ) {
			$classes[] = 'fanfic-member-profile';
		}
		if ( is_singular( 'fanfiction_story' ) ) {
			$classes[] = 'fanfic-story';
			$classes[] = 'fanfic-story-view';
		}
		if ( is_singular( 'fanfiction_chapter' ) ) {
			$classes[] = 'fanfic-chapter';
			$classes[] = 'fanfic-chapter-view';
		}
		if ( is_post_type_archive( 'fanfiction_story' ) ) {
			$classes[] = 'fanfic-story-archive';
		}
		if ( is_tax( array( 'fanfiction_genre', 'fanfiction_status', 'fanfiction_rating', 'fanfiction_character', 'fanfiction_relationship' ) ) ) {
			$tax = get_queried_object();
			if ( $tax && ! empty( $tax->taxonomy ) ) {
				$classes[] = 'fanfic-tax';
				$classes[] = 'fanfic-tax-' . sanitize_html_class( $tax->taxonomy );
			}
		}
		return $classes;
	}

	/**
	 * Provide shortlinks for virtual pages to avoid core null post warnings
	 *
	 * @since 2.0.0
	 * @param false|string $shortlink   Short-circuit return value.
	 * @param int          $id          Post ID or 0 for current post.
	 * @param string       $context     The context for the link.
	 * @param bool         $allow_slugs Whether to allow post slugs in the shortlink.
	 * @return false|string Shortlink or false to allow default handling.
	 */
	public function shortlink_for_virtual_pages( $shortlink, $id, $context, $allow_slugs ) {
		if ( get_query_var( 'fanfic_page' ) ) {
			if ( function_exists( 'fanfic_get_current_url' ) ) {
				return fanfic_get_current_url();
			}
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
			return $request_uri ? home_url( $request_uri ) : home_url( '/' );
		}

		return $shortlink;
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
			'dashboard' => array(
				'title'    => __( 'Dashboard', 'fanfiction-manager' ),
				'template' => 'dashboard',
			),
			'members' => array(
				'title'    => __( 'Members', 'fanfiction-manager' ),
				'template' => 'user-list',
			),
			'member_profile' => array(
				'title'    => '', // Will be dynamically set
				'template' => 'profile-view',
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
		// Save to individual options (matching load pattern in load_all_slugs() lines 120-124)
		$result = true;

		if ( isset( $slugs['dashboard'] ) ) {
			$result = update_option( 'fanfic_dashboard_slug', $slugs['dashboard'] ) && $result;
		}

		if ( isset( $slugs['members'] ) ) {
			$result = update_option( 'fanfic_members_slug', $slugs['members'] ) && $result;
		}

		if ( $result ) {
			$this->flush_cache();
		}
		return $result;
	}
}
