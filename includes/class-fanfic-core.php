<?php
/**
 * Core Plugin Class
 *
 * Handles plugin initialization, activation, and deactivation.
 *
 * @package FanfictionManager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Fanfic_Core class
 */
class Fanfic_Core {

	/**
	 * Single instance of the class
	 *
	 * @var Fanfic_Core
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Fanfic_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		error_log( '=== FANFIC PLUGIN INITIALIZING ===' );
		$this->load_dependencies();

		// Load text domain immediately before any hooks (WordPress 6.7+ compatibility)
		$this->load_textdomain();

		$this->init_hooks();
		error_log( '=== FANFIC PLUGIN INITIALIZED ===' );
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		// Phase 1: Database Setup (must be available for activation hook)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-database-setup.php';

		// Load cache class first (used by many other classes)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cache.php';

		// Load cache functions first (used by many other classes)
		require_once FANFIC_INCLUDES_DIR . 'cache/story-cache.php';

		// Load cache hooks class (handles automatic cache invalidation)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cache-hooks.php';

		// Load security and performance classes (Phase 4: Security Hardening)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-performance-monitor.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-rate-limit.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-security.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cache-manager.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-ajax-security.php';

		// Load AJAX handlers (Phase 5: Unified AJAX endpoints)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-ajax-handlers.php';

		// Load URL Manager (centralized URL management - replaces Rewrite, Dynamic_Pages, URL_Builder)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-url-manager.php';

		// Load Page Template (handles custom template that integrates with themes)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-page-template.php';

		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-post-types.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-taxonomies.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-roles-caps.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-validation.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-auto-draft-warning.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-slug-tracker.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-redirects.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-templates.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-shortcodes.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-author-dashboard.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-comments.php';

		// Optimized rating and like system with cookie-based anonymous tracking
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-rating-system.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-like-system.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cron-cleanup.php';

		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-bookmarks.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-follows.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-views.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-reading-progress.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-batch-loader.php';

		// Load input validation class (used by email/notification systems)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-input-validation.php';

		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-notifications.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-notification-preferences.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-email-templates.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-email-sender.php';

		// Load new email subscription and queue systems
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-email-subscriptions.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-email-queue.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-author-demotion.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-export.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-import.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-widgets.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-seo.php';
		require_once FANFIC_INCLUDES_DIR . 'functions.php';

		// Load Settings class (needed by cron jobs in all contexts)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-settings.php';

		// Load URL Schema (shared by admin and frontend)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-url-schema.php';

		// Load admin classes
		if ( is_admin() ) {
			require_once FANFIC_INCLUDES_DIR . 'admin/class-fanfic-cache-admin.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-wizard.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-stories-table.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-url-config.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-taxonomies-admin.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-moderation.php';
			// Load WP_List_Table implementation for moderation queue
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-moderation-table.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-moderation-stamps.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-users-admin.php';
			require_once FANFIC_INCLUDES_DIR . 'admin/class-fanfic-export-import-admin.php';
			require_once FANFIC_INCLUDES_DIR . 'class-fanfic-admin.php';
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Initialize post types
		add_action( 'init', array( 'Fanfic_Post_Types', 'register' ) );

		// Initialize taxonomies
		add_action( 'init', array( 'Fanfic_Taxonomies', 'register' ) );

		// Initialize roles and capabilities filter
		Fanfic_Roles_Caps::init();

		// Update role names with translations after init hook
		add_action( 'init', array( 'Fanfic_Roles_Caps', 'update_role_names' ), 1 );

		// Initialize template system
		Fanfic_Templates::init();

		// Initialize page template system
		Fanfic_Page_Template::init();

		// Initialize shortcodes
		Fanfic_Shortcodes::init();

		// Initialize admin interface (only in admin)
		if ( is_admin() ) {
			Fanfic_Cache_Admin::init();
			Fanfic_Wizard::get_instance(); // Initialize wizard singleton
			Fanfic_Settings::init();

			// Add admin notices for activation issues
			add_action( 'admin_notices', array( __CLASS__, 'show_activation_warnings' ) );
			Fanfic_URL_Config::init();
			Fanfic_Taxonomies_Admin::init();
			Fanfic_Moderation::init();
			Fanfic_Moderation_Stamps::init();
			Fanfic_Users_Admin::init();
			Fanfic_Export_Import_Admin::init();
			Fanfic_Admin::init();
		}

		// Initialize validation
		Fanfic_Validation::init();

		// Initialize auto-draft warning system
		Fanfic_Auto_Draft_Warning::init();

		// Initialize URL Manager (handles rewrite rules, dynamic pages, URL building)
		Fanfic_URL_Manager::get_instance();

		// Initialize slug tracker
		Fanfic_Slug_Tracker::init();

		// Initialize redirects
		Fanfic_Redirects::init();

		// Initialize author dashboard
		Fanfic_Author_Dashboard::init();

		// Initialize comments system
		Fanfic_Comments::init();

		// Initialize new rating and like system (v2.0)
		Fanfic_Rating_System::init();
		Fanfic_Like_System::init();
		Fanfic_Cron_Cleanup::init();

		// Initialize bookmarks system
		Fanfic_Bookmarks::init();

		// Initialize follows system
		Fanfic_Follows::init();

		// Initialize views tracking
		Fanfic_Views::init();

		// Initialize notifications system
		Fanfic_Notifications::init();

		// Initialize notification preferences
		Fanfic_Notification_Preferences::init();

		// Initialize email templates
		Fanfic_Email_Templates::init();

		// Initialize email sender
		Fanfic_Email_Sender::init();

		// Initialize new email subscription and queue systems
		Fanfic_Email_Subscriptions::init();
		Fanfic_Email_Queue::init();

		// Initialize author demotion
		Fanfic_Author_Demotion::init();

		// Initialize cache hooks (automatic cache invalidation)
		Fanfic_Cache_Hooks::init();

		// Initialize cache manager (Phase 4: Enhanced caching with incremental updates)
		Fanfic_Cache_Manager::init();

		// Initialize security and performance monitoring (Phase 4: Security Hardening)
		Fanfic_Performance_Monitor::init();
		Fanfic_Rate_Limit::init();
		Fanfic_Security::init();
		Fanfic_AJAX_Security::init();

		// Initialize AJAX handlers (Phase 5: Unified AJAX endpoints)
		Fanfic_AJAX_Handlers::init();

		// Initialize reading progress tracking
		Fanfic_Reading_Progress::init();

		// Initialize SEO
		Fanfic_SEO::init();

		// Register widgets
		add_action( 'widgets_init', array( 'Fanfic_Widgets', 'register_widgets' ) );

		// Block admin access for banned users
		add_action( 'admin_init', array( $this, 'block_banned_users_from_admin' ) );

		// Hide content from banned users in public views
		add_action( 'pre_get_posts', array( $this, 'hide_banned_users_content' ) );

		// Modify archive query for multi-taxonomy filtering via URL parameters
		add_action( 'pre_get_posts', array( $this, 'modify_archive_query' ) );

		// Allow authors to view their own draft stories
		add_action( 'pre_get_posts', array( $this, 'allow_author_draft_access' ) );

		// Handle fanfiction_story query var for draft post access
		add_action( 'parse_query', array( $this, 'handle_fanfiction_query_var' ) );

		// Filter posts_where to allow draft access for authors
		add_filter( 'posts_where', array( $this, 'filter_posts_where_for_drafts' ), 10, 2 );

		// Handle chapter_number and chapter_type query vars
		add_action( 'template_redirect', array( $this, 'handle_chapter_query_vars' ) );

		// Display suspension notice to banned users on frontend
		add_action( 'wp_footer', array( $this, 'display_suspension_notice' ) );

		// Enqueue frontend styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Block banned users from accessing the admin dashboard
	 *
	 * Redirects banned users to the frontend with a notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function block_banned_users_from_admin() {
		$current_user = wp_get_current_user();

		// Check if user has the banned role
		if ( in_array( 'fanfiction_banned_user', $current_user->roles, true ) ) {
			// Redirect to home page with notice
			wp_safe_redirect( home_url( '/?suspended=1' ) );
			exit;
		}
	}

	/**
	 * Hide content from banned users in public views
	 *
	 * Excludes stories and chapters from banned users in archives, search, and lists.
	 * Admins and moderators can still see banned users' content.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function hide_banned_users_content( $query ) {
		// Only apply to main query on frontend
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only apply to fanfiction post types
		$post_type = $query->get( 'post_type' );
		if ( ! in_array( $post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Don't apply filter for admins and moderators
		if ( current_user_can( 'moderate_fanfiction' ) ) {
			return;
		}

		// Get all users with banned role
		$banned_users = get_users( array(
			'role'   => 'fanfiction_banned_user',
			'fields' => 'ID',
		) );

		// If there are banned users, exclude their content
		if ( ! empty( $banned_users ) ) {
			$query->set( 'author__not_in', $banned_users );
		}
	}

	/**
	 * Modify archive query for multi-taxonomy filtering via URL parameters
	 *
	 * Allows filtering by multiple taxonomies using URL parameters like:
	 * ?genre=romance&status=completed
	 * ?genre=romance,fantasy (multiple values in same taxonomy)
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function modify_archive_query( $query ) {
		// Only modify main query on frontend for fanfiction_story archives
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if this is the main page in stories_homepage mode
		$main_page_mode = get_option( 'fanfic_main_page_mode', 'custom_homepage' );
		$is_main_page_archive = false;
		if ( 'stories_homepage' === $main_page_mode && is_page() ) {
			$page_ids = get_option( 'fanfic_system_page_ids', array() );
			if ( isset( $page_ids['main'] ) && is_page( $page_ids['main'] ) ) {
				$is_main_page_archive = true;
				// Set query to show fanfiction_story posts
				$query->set( 'post_type', 'fanfiction_story' );
				$query->set( 'post_status', 'publish' );
			}
		}

		// Only apply to post type archive, taxonomy archives, or main page in stories_homepage mode
		if ( ! $is_main_page_archive &&
		     ! is_post_type_archive( 'fanfiction_story' ) &&
		     ! is_tax( array( 'fanfiction_genre', 'fanfiction_status', 'fanfiction_rating', 'fanfiction_character', 'fanfiction_relationship' ) ) ) {
			return;
		}

		// Check for URL parameters
		$valid_taxonomies = array( 'fanfiction_genre', 'fanfiction_status', 'fanfiction_rating', 'fanfiction_character', 'fanfiction_relationship' );
		$tax_query = array();

		foreach ( $valid_taxonomies as $taxonomy ) {
			$param_name = str_replace( 'fanfiction_', '', $taxonomy );

			if ( isset( $_GET[ $param_name ] ) && ! empty( $_GET[ $param_name ] ) ) {
				$values = explode( ',', sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) ) );

				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array_map( 'trim', $values ),
				);
			}
		}

		// If URL filters exist, apply them to the query
		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND'; // All filters must match
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Allow authors to view their own draft stories and chapters
	 *
	 * Modifies the main query to include draft posts when viewing singular
	 * fanfiction stories or chapters if the current user is the author.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function allow_author_draft_access( $query ) {
		// Only modify main query on frontend
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if this is a singular fanfiction query
		$is_story = ! empty( $query->get( 'fanfiction_story' ) ) ||
		            ( $query->is_singular() && $query->get( 'post_type' ) === 'fanfiction_story' );
		$is_chapter = ! empty( $query->get( 'fanfiction_chapter' ) ) ||
		              ( $query->is_singular() && $query->get( 'post_type' ) === 'fanfiction_chapter' );

		// Only apply to fanfiction post types
		if ( ! $is_story && ! $is_chapter ) {
			return;
		}

		// If user is not logged in, don't modify the query
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if user has permission to view drafts
		// This includes authors (who can view their own drafts) and moderators/admins
		$can_view_drafts = current_user_can( 'edit_fanfiction_stories' ) ||
		                   current_user_can( 'edit_others_fanfiction_stories' );

		if ( $can_view_drafts ) {
			// Include draft and publish statuses in the query
			// WordPress will still check read_post capability before displaying
			$query->set( 'post_status', array( 'publish', 'draft', 'private' ) );
		}
	}

	/**
	 * Handle fanfiction_story and fanfiction_chapter query vars
	 *
	 * WordPress uses these custom query vars to look up posts by slug.
	 * This ensures draft posts are queryable by authorized users.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function handle_fanfiction_query_var( $query ) {
		// Only modify main query on frontend
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if we're querying by fanfiction_story or fanfiction_chapter slug
		$story_slug = $query->get( 'fanfiction_story' );
		$chapter_slug = $query->get( 'fanfiction_chapter' );

		if ( empty( $story_slug ) && empty( $chapter_slug ) ) {
			return;
		}

		// If user is logged in and can edit stories, allow draft access
		if ( is_user_logged_in() ) {
			$can_view_drafts = current_user_can( 'edit_fanfiction_stories' ) ||
			                   current_user_can( 'edit_others_fanfiction_stories' );

			if ( $can_view_drafts ) {
				// Set query to look for both published and draft posts
				$query->set( 'post_status', array( 'publish', 'draft', 'private' ) );
			}
		}
	}

	/**
	 * Filter posts WHERE clause to allow draft access for authorized users
	 *
	 * WordPress by default filters out draft posts in the SQL WHERE clause.
	 * This filter ensures draft posts are queryable for authorized users.
	 *
	 * @since 1.0.0
	 * @param string   $where The WHERE clause.
	 * @param WP_Query $query The WordPress query object.
	 * @return string Modified WHERE clause.
	 */
	public function filter_posts_where_for_drafts( $where, $query ) {
		// Only modify main query on frontend
		if ( is_admin() || ! $query->is_main_query() ) {
			return $where;
		}

		// Check if this is a fanfiction query
		$story_slug = $query->get( 'fanfiction_story' );
		$chapter_slug = $query->get( 'fanfiction_chapter' );

		if ( empty( $story_slug ) && empty( $chapter_slug ) ) {
			return $where;
		}

		// Only apply for logged in users with edit capability
		if ( ! is_user_logged_in() ) {
			return $where;
		}

		$can_view_drafts = current_user_can( 'edit_fanfiction_stories' ) ||
		                   current_user_can( 'edit_others_fanfiction_stories' );

		if ( ! $can_view_drafts ) {
			return $where;
		}

		// WordPress adds "AND (post_status = 'publish')" to the WHERE clause
		// We need to modify this to include draft posts
		global $wpdb;

		// Replace the post_status restriction to include draft, private, and publish
		$where = str_replace(
			"{$wpdb->posts}.post_status = 'publish'",
			"({$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_status = 'draft' OR {$wpdb->posts}.post_status = 'private')",
			$where
		);

		return $where;
	}

	/**
	 * Handle chapter_number and chapter_type query vars for chapter URLs
	 *
	 * When accessing chapter URLs like /stories/{story-slug}/chapter-3/,
	 * the rewrite rules set fanfiction_chapter={story-slug} and chapter_number=3.
	 * This method queries for the actual chapter post by finding the parent story
	 * and then the chapter with the matching number/type, then sets up global post data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_chapter_query_vars() {
		// Check if we have chapter query vars
		$story_slug = get_query_var( 'fanfiction_chapter' );
		$chapter_number = get_query_var( 'chapter_number' );
		$chapter_type = get_query_var( 'chapter_type' );

		// Need story slug and either chapter number or type
		if ( empty( $story_slug ) || ( empty( $chapter_number ) && empty( $chapter_type ) ) ) {
			return;
		}

		// Find the parent story by slug
		$story_query = new WP_Query( array(
			'post_type'      => 'fanfiction_story',
			'name'           => $story_slug,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'no_found_rows'  => true,
		) );

		if ( ! $story_query->have_posts() ) {
			// Story not found - let WordPress show 404
			return;
		}

		$story = $story_query->posts[0];

		// Build meta query for chapter number or type
		$meta_query = array();

		if ( ! empty( $chapter_number ) ) {
			$meta_query[] = array(
				'key'   => '_fanfic_chapter_number',
				'value' => absint( $chapter_number ),
				'compare' => '=',
			);
		}

		if ( ! empty( $chapter_type ) ) {
			$meta_query[] = array(
				'key'   => '_fanfic_chapter_type',
				'value' => sanitize_text_field( $chapter_type ),
				'compare' => '=',
			);
		}

		// Query for the chapter
		$chapter_query = new WP_Query( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story->ID,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
		) );

		if ( ! $chapter_query->have_posts() ) {
			// Chapter not found - let WordPress show 404
			return;
		}

		// Get the chapter post
		$chapter = $chapter_query->posts[0];

		// Access global $wp_query
		global $wp_query;

		// Set the posts array with our chapter
		$wp_query->posts = array( $chapter );
		$wp_query->post = $chapter;
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		$wp_query->max_num_pages = 1;

		// Set query flags for singular post
		$wp_query->is_singular = true;
		$wp_query->is_single = true;
		$wp_query->is_404 = false;
		$wp_query->is_page = false;
		$wp_query->is_archive = false;

		// Set queried object
		$wp_query->queried_object = $chapter;
		$wp_query->queried_object_id = $chapter->ID;

		// Set global $post
		$GLOBALS['post'] = $chapter;

		// Setup postdata for template tags
		setup_postdata( $chapter );

		// Send 200 OK status
		status_header( 200 );
	}

	/**
	 * Display suspension notice to banned users on frontend
	 *
	 * Shows a persistent notice to banned users on all frontend pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_suspension_notice() {
		// Only show to logged-in users
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();

		// Check if user has the banned role
		if ( ! in_array( 'fanfiction_banned_user', $current_user->roles, true ) ) {
			return;
		}

		// Get suspension details
		$suspended_at = get_user_meta( $current_user->ID, 'fanfic_suspended_at', true );
		$suspended_date = $suspended_at ? date_i18n( get_option( 'date_format' ), strtotime( $suspended_at ) ) : 'recently';

		?>
		<div id="fanfic-suspension-notice" style="position: fixed; top: 0; left: 0; right: 0; background: #dc3232; color: #fff; padding: 15px 20px; text-align: center; z-index: 999999; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
			<p style="margin: 0; font-size: 16px; font-weight: 600;">
				<?php
				printf(
					'Your account has been suspended as of %s. You can view your content but cannot create or edit stories.',
					esc_html( $suspended_date )
				);
				?>
			</p>
		</div>
		<style>
			/* Add padding to body to account for fixed notice */
			body {
				padding-top: 60px !important;
			}
			#fanfic-suspension-notice {
				animation: fanfic-notice-slide-down 0.3s ease-out;
			}
			@keyframes fanfic-notice-slide-down {
				from {
					transform: translateY(-100%);
				}
				to {
					transform: translateY(0);
				}
			}
		</style>
		<?php
	}

	/**
	 * Enqueue frontend styles and scripts
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue on frontend
		if ( is_admin() ) {
			return;
		}

		// Enqueue frontend CSS
		wp_enqueue_style(
			'fanfiction-frontend',
			FANFIC_PLUGIN_URL . 'assets/css/fanfiction-frontend.css',
			array(),
			FANFIC_VERSION,
			'all'
		);

		// Add inline styles for custom width settings
		$page_width = get_option( 'fanfic_page_width', '1200px' );
		if ( 'automatic' !== $page_width && preg_match( '/^(\d+)(px|%)$/', $page_width, $matches ) ) {
			$value = $matches[1];
			$unit = $matches[2];

			// Handle px and % differently for proper responsive behavior
			// margin: 0 auto centers the element horizontally
			// padding provides breathing room on mobile devices
			// box-sizing ensures padding is included in width calculation
			if ( 'px' === $unit ) {
				// Pixels: use max-width with width: 100% for responsive shrinking
				$custom_css = "#fanfiction-wrapper {
					max-width: {$value}px;
					width: 100%;
					margin: 0 auto;
					padding-left: 1rem;
					padding-right: 1rem;
					box-sizing: border-box;
				}";
			} else {
				// Percentages: use width only (user's explicit choice)
				$custom_css = "#fanfiction-wrapper {
					width: {$value}%;
					margin: 0 auto;
					padding-left: 1rem;
					padding-right: 1rem;
					box-sizing: border-box;
				}";
			}

			wp_add_inline_style( 'fanfiction-frontend', $custom_css );
		}

		// Enqueue frontend JS (if exists)
		$js_file = FANFIC_PLUGIN_DIR . 'assets/js/fanfiction-frontend.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'fanfiction-frontend',
				FANFIC_PLUGIN_URL . 'assets/js/fanfiction-frontend.js',
				array( 'jquery' ),
				FANFIC_VERSION,
				true
			);

			// Localize script with AJAX URL and nonce
			wp_localize_script(
				'fanfiction-frontend',
				'fanficAjax',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'fanfic_ajax_nonce' ),
				)
			);
		}

		// Enqueue unified interactions JS (Phase 5)
		$interactions_js_file = FANFIC_PLUGIN_DIR . 'assets/js/fanfiction-interactions.js';
		if ( file_exists( $interactions_js_file ) ) {
			wp_enqueue_script(
				'fanfiction-interactions',
				FANFIC_PLUGIN_URL . 'assets/js/fanfiction-interactions.js',
				array( 'jquery' ),
				FANFIC_VERSION,
				true
			);

			// Localize script with unified nonce name (same as frontend and likes/rating)
			wp_localize_script(
				'fanfiction-interactions',
				'fanficAjax',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'fanfic_ajax_nonce' ),
					'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'strings' => array(
						'ratingSubmitted'   => __( 'Rating submitted!', 'fanfiction-manager' ),
						'ratingUpdated'     => __( 'Rating updated!', 'fanfiction-manager' ),
						'liked'             => __( 'Liked!', 'fanfiction-manager' ),
						'unliked'           => __( 'Like removed', 'fanfiction-manager' ),
						'bookmarkAdded'     => __( 'Bookmark added!', 'fanfiction-manager' ),
						'bookmarkRemoved'   => __( 'Bookmark removed', 'fanfiction-manager' ),
						'followAdded'       => __( 'Now following!', 'fanfiction-manager' ),
						'followRemoved'     => __( 'Unfollowed', 'fanfiction-manager' ),
						'emailEnabled'      => __( 'Email notifications enabled', 'fanfiction-manager' ),
						'emailDisabled'     => __( 'Email notifications disabled', 'fanfiction-manager' ),
						'markedRead'        => __( 'Marked as read', 'fanfiction-manager' ),
						'subscribed'        => __( 'Subscription successful!', 'fanfiction-manager' ),
						'error'             => __( 'An error occurred. Please try again.', 'fanfiction-manager' ),
						'rateLimited'       => __( 'Too many requests. Please wait a moment.', 'fanfiction-manager' ),
						'loginRequired'     => __( 'You must be logged in to do that.', 'fanfiction-manager' ),
					),
				)
			);
		}
	}

	/**
	 * Load plugin text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'fanfiction-manager',
			false,
			dirname( plugin_basename( FANFIC_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Plugin activation callback
	 */
	public static function activate() {
		global $wpdb;

		// Get the current blog ID for multisite support
		$blog_id = get_current_blog_id();

		// Check Pretty Permalinks requirement BEFORE doing anything else
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-permalinks-check.php';
		Fanfic_Permalinks_Check::check_on_activation();

		// Verify template files exist
		self::verify_template_files();

		// Load all required classes for activation
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-post-types.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-taxonomies.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-roles-caps.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-settings.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-templates.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-page-template.php';
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cache.php';
		require_once FANFIC_INCLUDES_DIR . 'admin/class-fanfic-cache-admin.php';

		// Initialize and verify page template system
		self::verify_page_template_system();

		// Clear theme cache so template appears immediately
		if ( class_exists( 'Fanfic_Page_Template' ) ) {
			Fanfic_Page_Template::clear_theme_cache();

			// Store initial theme type for future theme switch detection
			$theme_type = Fanfic_Page_Template::is_block_theme() ? 'block' : 'classic';
			update_option( 'fanfic_theme_type', $theme_type );
		}

		// Create database tables using Database Setup class (Phase 1)
		$db_result = Fanfic_Database_Setup::init();
		if ( is_wp_error( $db_result ) ) {
			// Log error but don't halt activation
			error_log( 'Fanfiction Manager: Database setup error - ' . $db_result->get_error_message() );
		}

		// Also create legacy tables for backward compatibility
		self::create_tables();

		// Register post types and taxonomies for rewrite rules
		Fanfic_Post_Types::register();
		Fanfic_Taxonomies::register();

		// Create user roles and capabilities
		Fanfic_Roles_Caps::create_roles();

		// Always show wizard on activation (even if previously completed)
		update_option( 'fanfic_show_wizard', true );

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation flag
		update_option( 'fanfic_activated', true );
		update_option( 'fanfic_version', FANFIC_VERSION );

		// Schedule cache cleanup cron
		Fanfic_Cache_Admin::schedule_cleanup();

		// Schedule rating/like anonymization cron (v2.0)
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cron-cleanup.php';
		Fanfic_Cron_Cleanup::schedule_cron();

		// For multisite, store blog-specific activation
		if ( is_multisite() ) {
			update_option( 'fanfic_activated_blog_' . $blog_id, true );
		}
	}

	/**
	 * Plugin deactivation callback
	 */
	public static function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();

		// Unschedule cron jobs
		require_once FANFIC_INCLUDES_DIR . 'class-fanfic-cron-cleanup.php';
		Fanfic_Cron_Cleanup::unschedule_cron();

		// Unschedule old cron jobs from previous versions (pre-v2.0)
		wp_clear_scheduled_hook( 'fanfic_daily_sync_anonymous_likes' );

		// Check if user wants to delete all data
		$delete_data = get_option( 'fanfic_delete_on_deactivate', false );

		if ( $delete_data ) {
			self::delete_plugin_data();
		}

		// Remove activation flag
		delete_option( 'fanfic_activated' );
	}

	/**
	 * Verify that required template files exist
	 *
	 * Checks for critical template files during activation.
	 * Sets a transient for admin notice if files are missing.
	 *
	 * @since 1.0.0
	 */
	private static function verify_template_files() {
		$template_dir = FANFIC_PLUGIN_DIR . 'templates/';
		$missing_files = array();

		// Critical template files that must exist
		$required_templates = array(
			'fanfiction-page-template.php' => 'Main page template',
			'template-story-archive.php' => 'Story archive template',
			'template-story-view.php'  => 'Single story template',
			'template-chapter-view.php' => 'Single chapter template',
		);

		// Check if template directory exists
		if ( ! file_exists( $template_dir ) || ! is_dir( $template_dir ) ) {
			set_transient( 'fanfic_template_dir_missing', true, 60 );
			return;
		}

		// Check each required template file
		foreach ( $required_templates as $file => $label ) {
			$file_path = $template_dir . $file;
			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				$missing_files[ $file ] = $label;
			}
		}

		// Set transient if any files are missing
		if ( ! empty( $missing_files ) ) {
			set_transient( 'fanfic_missing_templates', $missing_files, 60 );
		}
	}

	/**
	 * Initialize and verify page template system
	 *
	 * Ensures the page template system is properly initialized and registered.
	 * Sets a transient for admin notice if registration fails.
	 *
	 * @since 1.0.0
	 */
	private static function verify_page_template_system() {
		// Initialize the page template system
		if ( class_exists( 'Fanfic_Page_Template' ) ) {
			Fanfic_Page_Template::init();

			// Verify the template filter was registered
			// We check if our filter callback is registered for 'theme_page_templates'
			global $wp_filter;

			$template_registered = false;
			if ( isset( $wp_filter['theme_page_templates'] ) ) {
				foreach ( $wp_filter['theme_page_templates']->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						// Check if this is our callback
						if ( is_array( $callback['function'] ) &&
						     isset( $callback['function'][0] ) &&
						     $callback['function'][0] === 'Fanfic_Page_Template' &&
						     isset( $callback['function'][1] ) &&
						     $callback['function'][1] === 'register_page_template' ) {
							$template_registered = true;
							break 2;
						}
					}
				}
			}

			// Set transient if template registration failed
			if ( ! $template_registered ) {
				set_transient( 'fanfic_template_not_registered', true, 60 );
			}
		} else {
			// Class doesn't exist - serious error
			set_transient( 'fanfic_template_class_missing', true, 60 );
		}
	}

	/**
	 * Show admin notices for activation warnings
	 *
	 * Displays warnings if template files are missing or template system failed to register.
	 *
	 * @since 1.0.0
	 */
	public static function show_activation_warnings() {
		// Only show to administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for template directory missing
		if ( get_transient( 'fanfic_template_dir_missing' ) ) {
			delete_transient( 'fanfic_template_dir_missing' );
			?>
			<div class="notice notice-error">
				<p>
					<strong>Fanfiction Manager:</strong> The template directory is missing or not readable.
					Please ensure the <code>templates/</code> directory exists in the plugin folder.
				</p>
			</div>
			<?php
		}

		// Check for missing template files
		$missing_templates = get_transient( 'fanfic_missing_templates' );
		if ( $missing_templates ) {
			delete_transient( 'fanfic_missing_templates' );
			?>
			<div class="notice notice-warning">
				<p><strong>Fanfiction Manager:</strong> Some template files are missing:</p>
				<ul style="list-style: disc; margin-left: 20px;">
					<?php foreach ( $missing_templates as $file => $label ) : ?>
						<li><code><?php echo esc_html( $file ); ?></code> - <?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p>
					The plugin will attempt to use fallback templates, but functionality may be limited.
					Please ensure all template files are present in the <code>templates/</code> directory.
				</p>
			</div>
			<?php
		}

		// Check for template class missing
		if ( get_transient( 'fanfic_template_class_missing' ) ) {
			delete_transient( 'fanfic_template_class_missing' );
			?>
			<div class="notice notice-error">
				<p>
					<strong>Fanfiction Manager:</strong> The page template system class is missing.
					Please reinstall the plugin or contact support.
				</p>
			</div>
			<?php
		}

		// Check for template not registered
		if ( get_transient( 'fanfic_template_not_registered' ) ) {
			delete_transient( 'fanfic_template_not_registered' );
			?>
			<div class="notice notice-warning">
				<p>
					<strong>Fanfiction Manager:</strong> The page template failed to register with WordPress.
					This may indicate a theme compatibility issue. The plugin will still function, but page templates may not display correctly.
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Create custom database tables
	 *
	 * Database tables now managed by Fanfic_Database_Setup class
	 * @see class-fanfic-database-setup.php
	 */
	private static function create_tables() {
		// Database table creation delegated to Fanfic_Database_Setup::init()
		// This method is kept for backwards compatibility but no longer creates tables
		// All table management is now centralized in class-fanfic-database-setup.php
	}

	/**
	 * Delete all plugin data (stories, chapters, custom tables)
	 */
	private static function delete_plugin_data() {
		global $wpdb;

		// Delete all stories
		$stories = get_posts( array(
			'post_type'      => 'fanfiction_story',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		foreach ( $stories as $story_id ) {
			wp_delete_post( $story_id, true );
		}

		// Delete all chapters
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		foreach ( $chapters as $chapter_id ) {
			wp_delete_post( $chapter_id, true );
		}

		// Delete system pages
		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		foreach ( $page_ids as $page_id ) {
			wp_delete_post( $page_id, true );
		}

		// Drop custom tables
		$table_prefix = $wpdb->prefix . 'fanfic_';
		$tables = array(
			'ratings',
			'bookmarks',
			'follows',
			'notifications',
			'reports',
			'likes',
			'reading_progress',
			'read_lists',
			'subscriptions',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table_prefix}{$table}" );
		}

		// Delete plugin options
		delete_option( 'fanfic_version' );
		delete_option( 'fanfic_settings' );
		delete_option( 'fanfic_delete_on_deactivate' );
		delete_option( 'fanfic_system_page_ids' );
		delete_option( 'fanfic_wizard_completed' );
		delete_option( 'fanfic_show_wizard' );

		// Remove user roles
		Fanfic_Roles_Caps::remove_roles();
	}
}
