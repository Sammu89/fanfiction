<?php
/**
 * Template Loader Class
 *
 * Handles loading and managing frontend templates for the Fanfiction Manager plugin.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Templates
 *
 * Manages template loading with fallback to theme templates.
 *
 * @since 1.0.0
 */
class Fanfic_Templates {

	/**
	 * Initialize the template loader
	 *
	 * Sets up WordPress hooks for template loading.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_loader' ) );
		add_action( 'init', array( __CLASS__, 'check_missing_pages' ) );
		add_action( 'init', array( __CLASS__, 'check_missing_shortcodes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'missing_pages_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'missing_shortcodes_notice' ) );
		add_action( 'admin_post_fanfic_rebuild_pages', array( __CLASS__, 'rebuild_pages' ) );
		add_action( 'admin_post_fanfic_fix_page_shortcodes', array( __CLASS__, 'fix_page_shortcodes' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'filter_menu_items_by_login_status' ), 10, 2 );
	}

	/**
	 * Template loader
	 *
	 * Loads custom templates for fanfiction post types.
	 *
	 * @since 1.0.0
	 * @param string $template The template path.
	 * @return string Modified template path.
	 */
	public static function template_loader( $template ) {
		// Check if viewing main page in stories_homepage mode
		$main_page_mode = get_option( 'fanfic_main_page_mode', 'custom_homepage' );
		if ( 'stories_homepage' === $main_page_mode && is_page() ) {
			$page_ids = get_option( 'fanfic_system_page_ids', array() );
			if ( isset( $page_ids['main'] ) && is_page( $page_ids['main'] ) ) {
				// Load archive template for main page
				$custom_template = self::locate_template( 'template-story-archive.php' );
				if ( $custom_template ) {
					return $custom_template;
				}
			}
		}

		// Check if this is the error page - use template-error.php instead of shortcode
		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		if ( is_page() && isset( $page_ids['error'] ) && is_page( $page_ids['error'] ) ) {
			global $fanfic_content_template;
			$fanfic_content_template = 'template-error.php';
		}

		// Check if this is the maintenance page - use template-maintenance.php instead of shortcode
		if ( is_page() && isset( $page_ids['maintenance'] ) && is_page( $page_ids['maintenance'] ) ) {
			global $fanfic_content_template;
			$fanfic_content_template = 'template-maintenance.php';
		}

		// Check for action-based templates on stories and chapters
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// Check if this is a fanfiction post type
		if ( is_singular( 'fanfiction_story' ) ) {
			// Determine which content template to load
			$content_template = 'single-fanfiction_story.php';

			if ( ! empty( $action ) ) {
				switch ( $action ) {
					case 'edit':
						$content_template = 'template-story-form.php';  // Use unified story form
						break;
					case 'add-chapter':
						$content_template = 'template-chapter-form.php';
						break;
				}
			}

			// Set global variable for wrapper template to use
			global $fanfic_content_template;
			$fanfic_content_template = $content_template;

			// Always use the wrapper template
			$custom_template = self::locate_template( 'fanfiction-page-template.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		if ( is_singular( 'fanfiction_chapter' ) ) {
			// Determine which content template to load
			$content_template = 'single-fanfiction_chapter.php';

			if ( ! empty( $action ) && 'edit' === $action ) {
				$content_template = 'template-chapter-form.php';
			}

			// Set global variable for wrapper template to use
			global $fanfic_content_template;
			$fanfic_content_template = $content_template;

			// Always use the wrapper template
			$custom_template = self::locate_template( 'fanfiction-page-template.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		if ( is_post_type_archive( 'fanfiction_story' ) ) {
			$custom_template = self::locate_template( 'template-story-archive.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		if ( is_tax( 'fanfiction_genre' ) ) {
			$custom_template = self::locate_template( 'taxonomy-fanfiction_genre.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		if ( is_tax( 'fanfiction_status' ) ) {
			$custom_template = self::locate_template( 'taxonomy-fanfiction_status.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Locate a template file
	 *
	 * Searches for template in theme first, then plugin.
	 *
	 * @since 1.0.0
	 * @param string $template_name The template file name.
	 * @return string|false Template path or false if not found.
	 */
	public static function locate_template( $template_name ) {
		// Check theme directory first
		$theme_template = locate_template( array(
			'fanfiction-manager/' . $template_name,
			$template_name,
		) );

		if ( $theme_template ) {
			return $theme_template;
		}

		// Check plugin templates directory
		$plugin_template = FANFIC_PLUGIN_DIR . 'templates/' . $template_name;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Load a template part
	 *
	 * Loads a specific template file with variables.
	 *
	 * @since 1.0.0
	 * @param string $slug The template slug.
	 * @param array  $args Variables to pass to the template.
	 * @return void
	 */
	public static function get_template_part( $slug, $args = array() ) {
		$template = self::locate_template( "template-{$slug}.php" );

		if ( $template ) {
			// Extract args to make them available in the template
			if ( ! empty( $args ) ) {
				extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			}

			include $template;
		}
	}

	/**
	 * Get page ID by template slug
	 *
	 * Retrieves the WordPress page ID for a system page.
	 *
	 * @since 1.0.0
	 * @param string $slug The page slug (e.g., 'login', 'register').
	 * @return int|false Page ID or false if not found.
	 */
	public static function get_page_id_by_slug( $slug ) {
		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		return isset( $page_ids[ $slug ] ) ? absint( $page_ids[ $slug ] ) : false;
	}

	/**
	 * Get page URL by slug
	 *
	 * Retrieves the URL for a system page.
	 *
	 * @since 1.0.0
	 * @param string $slug The page slug.
	 * @return string Page URL or empty string if not found.
	 */
	public static function get_page_url( $slug ) {
		$page_id = self::get_page_id_by_slug( $slug );
		if ( $page_id ) {
			return get_permalink( $page_id );
		}
		return '';
	}

	/**
	 * Check for missing system pages
	 *
	 * Verifies all required system pages exist.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function check_missing_pages() {
		// Only check for logged-in admins
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only check if wizard has been completed
		$wizard_completed = get_option( 'fanfic_wizard_completed', false );
		if ( ! $wizard_completed ) {
			return;
		}

		$page_ids = get_option( 'fanfic_system_page_ids', array() );

		// Get list of dynamic pages (don't check these as they don't have WordPress pages)
		$dynamic_pages = Fanfic_URL_Manager::get_instance()->get_dynamic_pages();

		$missing_pages = array();

		foreach ( $page_ids as $slug => $page_id ) {
			// Skip dynamic pages (they don't have WordPress page entries)
			if ( in_array( $slug, $dynamic_pages, true ) ) {
				continue;
			}

			$page = get_post( $page_id );
			if ( ! $page || $page->post_status === 'trash' ) {
				$missing_pages[] = $slug;
			}
		}

		// Store missing pages for admin notice
		if ( ! empty( $missing_pages ) ) {
			set_transient( 'fanfic_missing_pages', $missing_pages, DAY_IN_SECONDS );
		} else {
			delete_transient( 'fanfic_missing_pages' );
		}
	}

	/**
	 * Display admin notice for missing pages
	 *
	 * Shows a notice with rebuild button if pages are missing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function missing_pages_notice() {
		// Don't show during wizard run
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			return;
		}

		// Don't show if Pretty Permalinks are disabled (permalink notice takes priority)
		if ( Fanfic_Permalinks_Check::should_disable_plugin() ) {
			return;
		}

		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wizard_completed = get_option( 'fanfic_wizard_completed', false );

		// If wizard hasn't been completed, show yellow notice to run wizard
		if ( ! $wizard_completed ) {
			$wizard_url = admin_url( 'admin.php?page=fanfic-setup-wizard' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Fanfiction Manager:', 'fanfiction-manager' ); ?></strong>
					<?php esc_html_e( 'Please run the setup wizard to configure the plugin and create system pages.', 'fanfiction-manager' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( $wizard_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Run Setup Wizard', 'fanfiction-manager' ); ?>
					</a>
				</p>
			</div>
			<?php
			return;
		}

		// Wizard completed, check for missing pages
		$missing_pages = get_transient( 'fanfic_missing_pages' );

		if ( ! $missing_pages ) {
			return;
		}

		$rebuild_url = admin_url( 'admin-post.php?action=fanfic_rebuild_pages' );
		$rebuild_url = wp_nonce_url( $rebuild_url, 'fanfic_rebuild_pages' );

		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Fanfiction Manager:', 'fanfiction-manager' ); ?></strong>
				<?php
				printf(
					/* translators: %d: number of missing pages */
					esc_html( _n(
						'%d system page is missing or has been deleted.',
						'%d system pages are missing or have been deleted.',
						count( $missing_pages ),
						'fanfiction-manager'
					) ),
					count( $missing_pages )
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $rebuild_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Rebuild Missing Pages', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Rebuild missing pages
	 *
	 * Recreates all system pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function rebuild_pages() {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanfic_rebuild_pages' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'fanfiction-manager' ) );
		}

		// Get base slug from settings
		$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );

		// Rebuild pages with error handling
		$result = self::create_system_pages( $base_slug );

		// Prepare redirect arguments
		$redirect_args = array(
			'page' => 'fanfiction-settings',
		);

		if ( $result['success'] ) {
			$redirect_args['rebuilt'] = '1';
		} else {
			$redirect_args['rebuild_error'] = '1';
		}

		// Redirect back with success or error message
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Get required shortcodes for a system page
	 *
	 * Returns array of shortcodes that must be present in the page content.
	 *
	 * @since 1.0.0
	 * @param string $page_slug The page slug.
	 * @return array Required shortcodes (empty array if page doesn't need specific shortcodes).
	 */
	public static function get_required_shortcodes_for_page( $page_slug ) {
		$required_shortcodes = array(
			'login'          => array( 'fanfic-login-form' ),
			'register'       => array( 'fanfic-register-form' ),
			'password-reset' => array( 'fanfic-password-reset-form' ),
			'dashboard'      => array( 'user-dashboard' ),
			'create-story'   => array( 'author-create-story-form' ),
			'search'         => array( 'search-results' ),
			'members'        => array( 'user-profile' ),
			// 'error' page no longer uses shortcodes - it loads template-error.php directly
			// 'maintenance' page no longer uses shortcodes - it loads template-maintenance.php directly
		);

		return isset( $required_shortcodes[ $page_slug ] ) ? $required_shortcodes[ $page_slug ] : array();
	}

	/**
	 * Check for missing shortcodes in system pages
	 *
	 * Checks all system pages to ensure they contain their required shortcodes.
	 * Stores results in a transient for use in admin notices.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function check_missing_shortcodes() {
		// Only run for admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only check if wizard is completed
		if ( ! get_option( 'fanfic_wizard_completed', false ) ) {
			return;
		}

		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		if ( empty( $page_ids ) ) {
			return;
		}

		// Get list of dynamic pages (don't check these as they don't have WordPress pages)
		$dynamic_pages = Fanfic_URL_Manager::get_instance()->get_dynamic_pages();

		$pages_with_missing_shortcodes = array();

		foreach ( $page_ids as $slug => $page_id ) {
			// Skip dynamic pages (they don't have WordPress page content)
			if ( in_array( $slug, $dynamic_pages, true ) ) {
				continue;
			}

			$page = get_post( $page_id );

			// Skip if page doesn't exist
			if ( ! $page || $page->post_status === 'trash' ) {
				continue;
			}

			$required_shortcodes = self::get_required_shortcodes_for_page( $slug );

			// Skip if no required shortcodes for this page
			if ( empty( $required_shortcodes ) ) {
				continue;
			}

			// Check if page content contains all required shortcodes
			$page_content = $page->post_content;
			$missing_shortcodes = array();

			foreach ( $required_shortcodes as $shortcode ) {
				// Check for shortcode in brackets
				if ( strpos( $page_content, '[' . $shortcode ) === false ) {
					$missing_shortcodes[] = $shortcode;
				}
			}

			// If any shortcodes are missing, add to list
			if ( ! empty( $missing_shortcodes ) ) {
				$pages_with_missing_shortcodes[ $slug ] = array(
					'page_id'            => $page_id,
					'page_title'         => $page->post_title,
					'missing_shortcodes' => $missing_shortcodes,
				);
			}
		}

		// Store in transient (expires in 24 hours)
		set_transient( 'fanfic_missing_shortcodes', $pages_with_missing_shortcodes, DAY_IN_SECONDS );
	}

	/**
	 * Display admin notice for pages with missing shortcodes
	 *
	 * Shows a warning notice to admins when system pages are missing required shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function missing_shortcodes_notice() {
		// Don't show if Pretty Permalinks are disabled (permalink notice takes priority)
		if ( Fanfic_Permalinks_Check::should_disable_plugin() ) {
			return;
		}

		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pages_with_issues = get_transient( 'fanfic_missing_shortcodes' );

		if ( empty( $pages_with_issues ) ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Fanfiction Manager:', 'fanfiction-manager' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of pages with missing shortcodes */
						_n(
							'%d system page is missing required shortcodes.',
							'%d system pages are missing required shortcodes.',
							count( $pages_with_issues ),
							'fanfiction-manager'
						),
						count( $pages_with_issues )
					)
				);
				?>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<?php foreach ( $pages_with_issues as $slug => $info ) : ?>
					<li>
						<strong><?php echo esc_html( $info['page_title'] ); ?></strong>
						<?php
						/* translators: %s: comma-separated list of missing shortcode names */
						echo esc_html( sprintf( __( 'missing: [%s]', 'fanfiction-manager' ), implode( '], [', $info['missing_shortcodes'] ) ) );
						?>
						-
						<?php
						$fix_url = admin_url( 'admin-post.php?action=fanfic_fix_page_shortcodes&page_slug=' . $slug );
						$fix_url = wp_nonce_url( $fix_url, 'fanfic_fix_page_' . $slug );
						?>
						<a href="<?php echo esc_url( $fix_url ); ?>" class="button button-small">
							<?php esc_html_e( 'Restore Default Content', 'fanfiction-manager' ); ?>
						</a>
						<a href="<?php echo esc_url( get_edit_post_link( $info['page_id'] ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Edit Page', 'fanfiction-manager' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<em><?php esc_html_e( 'Note: You can customize page content, but required shortcodes must be present for the page to function properly.', 'fanfiction-manager' ); ?></em>
			</p>
		</div>
		<?php
	}

	/**
	 * Fix page shortcodes by restoring default content
	 *
	 * Handles the admin action to restore a page's content to include required shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function fix_page_shortcodes() {
		// Get page slug from request
		$page_slug = isset( $_GET['page_slug'] ) ? sanitize_text_field( wp_unslash( $_GET['page_slug'] ) ) : '';

		if ( empty( $page_slug ) ) {
			wp_die( esc_html__( 'Invalid page specified.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanfic_fix_page_' . $page_slug ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'fanfiction-manager' ) );
		}

		// Get page ID
		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		if ( ! isset( $page_ids[ $page_slug ] ) ) {
			wp_die( esc_html__( 'Page not found.', 'fanfiction-manager' ) );
		}

		$page_id = $page_ids[ $page_slug ];
		$page = get_post( $page_id );

		if ( ! $page ) {
			wp_die( esc_html__( 'Page not found.', 'fanfiction-manager' ) );
		}

		// Get default content for this page
		$default_content = self::get_default_template_content( $page_slug );

		if ( empty( $default_content ) ) {
			wp_die( esc_html__( 'No default content available for this page.', 'fanfiction-manager' ) );
		}

		// Update page content
		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $default_content,
			)
		);

		// Clear the transient so notice updates
		delete_transient( 'fanfic_missing_shortcodes' );

		// Prepare redirect arguments
		$redirect_args = array(
			'page' => 'fanfiction-settings',
		);

		if ( $updated && ! is_wp_error( $updated ) ) {
			$redirect_args['shortcode_fixed'] = '1';
			$redirect_args['fixed_page'] = urlencode( $page->post_title );
		} else {
			$redirect_args['shortcode_fix_error'] = '1';
		}

		// Redirect back with success or error message
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Create system pages
	 *
	 * Creates all required plugin pages on activation.
	 *
	 * @since 1.0.0
	 * @param string $base_slug The base slug for the main page (default: 'fanfiction').
	 * @return array Array with success status, created/existing/failed pages, and summary message.
	 */
	public static function create_system_pages( $base_slug = 'fanfiction' ) {
		$page_ids = get_option( 'fanfic_system_page_ids', array() );

		// Initialize result tracking array
		$result = array(
			'created'  => array(),
			'existing' => array(),
			'failed'   => array(),
		);

		// Get custom page slugs from settings
		$custom_slugs = get_option( 'fanfic_system_page_slugs', array() );

		// Separate dynamic page slugs (don't create WordPress pages for these)
		$dynamic_slugs = array();
		$dynamic_pages = Fanfic_URL_Manager::get_instance()->get_dynamic_pages();

		// Get main page mode
		$main_page_mode = get_option( 'fanfic_main_page_mode', 'custom_homepage' );

		// Create main page based on mode
		if ( $main_page_mode === 'stories_homepage' ) {
			// Main page will use post type archive template (handled in template_loader)
			$main_page_id = self::create_or_update_page(
				'main',
				__( 'Fanfiction', 'fanfiction-manager' ),
				'',
				'<!-- wp:paragraph --><p>' . __( 'Loading stories...', 'fanfiction-manager' ) . '</p><!-- /wp:paragraph -->',
				$base_slug,
				0,
				$result
			);
		} else {
			// Custom homepage - create as editable page with translatable content
			$main_page_id = self::create_or_update_page(
				'main',
				__( 'Fanfiction', 'fanfiction-manager' ),
				'',
				self::get_default_template_content( 'main' ),
				$base_slug,
				0,
				$result
			);
		}

		if ( false !== $main_page_id ) {
			$page_ids['main'] = $main_page_id;
		}

		// Define all system pages with custom slugs
		$pages = array(
			'login'           => array(
				'title'    => __( 'Login', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['login'] ) ? $custom_slugs['login'] : 'login',
				'template' => 'login',
			),
			'register'        => array(
				'title'    => __( 'Register', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['register'] ) ? $custom_slugs['register'] : 'register',
				'template' => 'register',
			),
			'password-reset'  => array(
				'title'    => __( 'Password Reset', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['password-reset'] ) ? $custom_slugs['password-reset'] : 'password-reset',
				'template' => 'password-reset',
			),
			'dashboard'       => array(
				'title'    => __( 'Dashboard', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['dashboard'] ) ? $custom_slugs['dashboard'] : 'dashboard',
				'template' => 'dashboard',
			),
			'create-story'    => array(
				'title'    => __( 'Create Story', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['create-story'] ) ? $custom_slugs['create-story'] : 'create-story',
				'template' => 'create-story',
			),
			'search'          => array(
				'title'    => __( 'Search', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['search'] ) ? $custom_slugs['search'] : 'search',
				'template' => 'search',
			),
			'members'         => array(
				'title'    => __( 'Members', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['members'] ) ? $custom_slugs['members'] : 'members',
				'template' => 'members',
			),
			'error'           => array(
				'title'    => __( 'Error', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['error'] ) ? $custom_slugs['error'] : 'error',
				'template' => 'error',
			),
			'maintenance'     => array(
				'title'    => __( 'Maintenance', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['maintenance'] ) ? $custom_slugs['maintenance'] : 'maintenance',
				'template' => 'maintenance',
			),
		);

		// Create child pages (only for non-dynamic pages)
		foreach ( $pages as $key => $page_data ) {
			// Check if this page should be dynamic
			if ( in_array( $key, $dynamic_pages, true ) ) {
				// Store slug for dynamic page but don't create WordPress page
				$dynamic_slugs[ $key ] = $page_data['slug'];
				continue;
			}

			// Use shortcode-based content (WordPress will process on each page view)
			$content = self::get_default_template_content( $key );
			$page_id = self::create_or_update_page(
				$key,
				$page_data['title'],
				$page_data['slug'],
				$content,
				$page_data['slug'],
				$main_page_id,
				$result
			);

			if ( false !== $page_id ) {
				$page_ids[ $key ] = $page_id;
			}
		}

		// Save dynamic page slugs separately
		if ( ! empty( $dynamic_slugs ) ) {
			Fanfic_URL_Manager::get_instance()->update_slugs( $dynamic_slugs );
		}

		// Save page IDs
		update_option( 'fanfic_system_page_ids', $page_ids );

		// Flush rewrite rules
		flush_rewrite_rules();

		// Validate only physical WordPress pages (not dynamic pages)
		$required_pages = array(
			'main',
			'login',
			'register',
			'password-reset',
			'error',
			'maintenance',
		);

		$validation_failed = array();
		foreach ( $required_pages as $page_key ) {
			if ( empty( $page_ids[ $page_key ] ) ) {
				$validation_failed[] = $page_key;
				continue;
			}

			$page = get_post( $page_ids[ $page_key ] );
			if ( ! $page || 'publish' !== $page->post_status ) {
				$validation_failed[] = $page_key;
			}
		}

		// Validate dynamic page slugs are saved
		$saved_dynamic_slugs = Fanfic_URL_Manager::get_instance()->get_slugs();
		foreach ( $dynamic_pages as $dynamic_page ) {
			if ( empty( $saved_dynamic_slugs[ $dynamic_page ] ) ) {
				// This shouldn't happen, but log it
				error_log( "Missing dynamic page slug for: {$dynamic_page}" );
			}
		}

		// Build result array
		$success = empty( $result['failed'] ) && empty( $validation_failed );

		// Create navigation menu if pages were created successfully
		if ( $success ) {
			self::create_fanfiction_menu( $page_ids );
		}

		// Build summary message
		if ( $success ) {
			if ( ! empty( $result['created'] ) ) {
				$message = sprintf(
					/* translators: %d: number of pages created */
					_n(
						'Successfully created %d system page.',
						'Successfully created %d system pages.',
						count( $result['created'] ),
						'fanfiction-manager'
					),
					count( $result['created'] )
				);
			} else {
				$message = __( 'All system pages already exist.', 'fanfiction-manager' );
			}
		} else {
			// Add validation failures to the failed array
			foreach ( $validation_failed as $failed_key ) {
				// Get title for the failed page
				$page_title = $failed_key;
				if ( 'main' === $failed_key ) {
					$page_title = __( 'Fanfiction', 'fanfiction-manager' );
				} elseif ( isset( $pages[ $failed_key ]['title'] ) ) {
					$page_title = $pages[ $failed_key ]['title'];
				}

				$result['failed'][] = array(
					'key'     => $failed_key,
					'title'   => $page_title,
					'message' => __( 'Page validation failed - page does not exist or is not published.', 'fanfiction-manager' ),
				);
			}

			$failed_count = count( $result['failed'] );
			$message = sprintf(
				/* translators: %d: number of pages that failed */
				_n(
					'Failed to create %d system page.',
					'Failed to create %d system pages.',
					$failed_count,
					'fanfiction-manager'
				),
				$failed_count
			);
		}

		return array(
			'success'  => $success,
			'created'  => $result['created'],
			'existing' => $result['existing'],
			'failed'   => $result['failed'],
			'message'  => $message,
		);
	}

	/**
	 * Create or recreate the Fanfiction Automatic Menu
	 *
	 * @since 1.0.0
	 * @param array $page_ids Array of system page IDs.
	 * @return int|WP_Error Menu ID on success, WP_Error on failure.
	 */
	public static function create_fanfiction_menu( $page_ids ) {
		$menu_name = 'Fanfiction Automatic Menu';

		// Delete existing menu if it exists
		$existing_menu = wp_get_nav_menu_object( $menu_name );
		if ( $existing_menu ) {
			wp_delete_nav_menu( $existing_menu->term_id );
		}

		// Create new menu
		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		// Get URL Manager instance for dynamic URLs
		$url_manager = Fanfic_URL_Manager::get_instance();

		// Menu item position counter
		$position = 0;

		// 1. HOME (always visible)
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'   => __( 'Home', 'fanfiction-manager' ),
			'menu-item-url'     => home_url( '/' ),
			'menu-item-status'  => 'publish',
			'menu-item-position' => ++$position,
			'menu-item-classes' => 'fanfic-menu-home',
		) );

		// 2. DASHBOARD (logged in only)
		$dashboard_url = $url_manager->get_page_url( 'dashboard' );
		if ( ! empty( $dashboard_url ) ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'   => __( 'Dashboard', 'fanfiction-manager' ),
				'menu-item-url'     => $dashboard_url,
				'menu-item-status'  => 'publish',
				'menu-item-position' => ++$position,
				'menu-item-classes' => 'fanfic-menu-dashboard menu-item-logged-in',
			) );
		}

		// 3. ADD STORY (logged in only)
		$create_story_url = $url_manager->get_page_url( 'create-story' );
		if ( ! empty( $create_story_url ) ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'   => __( 'Add Story', 'fanfiction-manager' ),
				'menu-item-url'     => $create_story_url,
				'menu-item-status'  => 'publish',
				'menu-item-position' => ++$position,
				'menu-item-classes' => 'fanfic-menu-add-story menu-item-logged-in',
			) );
		}

		// 4. STORIES ARCHIVE (always visible)
		$archive_url = get_post_type_archive_link( 'fanfiction_story' );
		if ( ! empty( $archive_url ) ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'   => __( 'Stories Archive', 'fanfiction-manager' ),
				'menu-item-url'     => $archive_url,
				'menu-item-status'  => 'publish',
				'menu-item-position' => ++$position,
				'menu-item-classes' => 'fanfic-menu-archive',
			) );
		}

		// 5. MEMBERS (always visible)
		$members_url = $url_manager->get_page_url( 'members' );
		if ( ! empty( $members_url ) ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'   => __( 'Members', 'fanfiction-manager' ),
				'menu-item-url'     => $members_url,
				'menu-item-status'  => 'publish',
				'menu-item-position' => ++$position,
				'menu-item-classes' => 'fanfic-menu-members',
			) );
		}

		// 6. LOGIN (logged out only)
		if ( isset( $page_ids['login'] ) && $page_ids['login'] > 0 ) {
			$login_url = get_permalink( $page_ids['login'] );
			if ( ! empty( $login_url ) ) {
				wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-title'   => __( 'Login', 'fanfiction-manager' ),
					'menu-item-url'     => $login_url,
					'menu-item-status'  => 'publish',
					'menu-item-position' => ++$position,
					'menu-item-classes' => 'fanfic-menu-login menu-item-logged-out',
				) );
			}
		}

		// 7. LOGOUT (logged in only)
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'   => __( 'Logout', 'fanfiction-manager' ),
			'menu-item-url'     => wp_logout_url( home_url( '/' ) ),
			'menu-item-status'  => 'publish',
			'menu-item-position' => ++$position,
			'menu-item-classes' => 'fanfic-menu-logout menu-item-logged-in',
		) );

		return $menu_id;
	}

	/**
	 * Filter menu items based on user login status
	 *
	 * Hides items with 'menu-item-logged-in' class if user is logged out
	 * Hides items with 'menu-item-logged-out' class if user is logged in
	 *
	 * @since 1.0.0
	 * @param array $items Menu items.
	 * @param mixed $menu Menu object.
	 * @return array Filtered menu items.
	 */
	public static function filter_menu_items_by_login_status( $items, $menu ) {
		$is_logged_in = is_user_logged_in();

		foreach ( $items as $key => $item ) {
			// Hide logged-in items if user is logged out
			if ( ! $is_logged_in && in_array( 'menu-item-logged-in', $item->classes, true ) ) {
				unset( $items[ $key ] );
			}

			// Hide logged-out items if user is logged in
			if ( $is_logged_in && in_array( 'menu-item-logged-out', $item->classes, true ) ) {
				unset( $items[ $key ] );
			}
		}

		return $items;
	}

	/**
	 * Create or update a page
	 *
	 * Creates a new page if it doesn't exist, or updates if it does.
	 *
	 * @since 1.0.0
	 * @param string $key         The page key.
	 * @param string $title       The page title.
	 * @param string $slug        The page slug.
	 * @param string $content     The page content.
	 * @param string $post_name   The post name.
	 * @param int    $parent_id   The parent page ID.
	 * @param array  $result      Reference to result tracking array.
	 * @return int|false Page ID on success, false on failure.
	 */
	private static function create_or_update_page( $key, $title, $slug, $content, $post_name, $parent_id = 0, &$result = null ) {
		$page_ids = get_option( 'fanfic_system_page_ids', array() );

		// Check for existing page with this key
		$existing_page_id = isset( $page_ids[ $key ] ) ? $page_ids[ $key ] : 0;

		if ( $existing_page_id > 0 ) {
			$existing_page = get_post( $existing_page_id );
			if ( $existing_page && $existing_page->post_name !== $post_name ) {
				// Slug is changing - store redirect
				if ( class_exists( 'Fanfic_Slug_Tracker' ) ) {
					Fanfic_Slug_Tracker::add_manual_redirect( $existing_page->post_name, $post_name );
				}
			}
		}

		// Check if page already exists
		if ( isset( $page_ids[ $key ] ) ) {
			$existing_page = get_post( $page_ids[ $key ] );
			if ( $existing_page && $existing_page->post_status !== 'trash' ) {
				// Page exists - check if updates are needed
				$needs_update = false;
				$update_data = array( 'ID' => $page_ids[ $key ] );

				// Check if slug needs updating
				if ( $existing_page->post_name !== $post_name ) {
					$update_data['post_name'] = $post_name;
					$needs_update = true;
				}

				// Check if title needs updating
				if ( $existing_page->post_title !== $title ) {
					$update_data['post_title'] = $title;
					$needs_update = true;
				}

				// Check if parent needs updating
				if ( $existing_page->post_parent != $parent_id ) {
					$update_data['post_parent'] = $parent_id;
					$needs_update = true;
				}

				// Perform update if needed
				if ( $needs_update ) {
					wp_update_post( $update_data );
				}

				// Page exists (or was updated), add to existing array
				if ( is_array( $result ) ) {
					$result['existing'][] = $key;
				}
				return $page_ids[ $key ];
			}
		}

		// Use default template content if content is empty
		if ( empty( $content ) ) {
			$content = self::get_default_template_content( $slug );
		}

		// Create new page
		$page_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => $post_name,
			'post_parent'  => $parent_id,
		);

		$page_id = wp_insert_post( $page_data );

		// Check for WP_Error
		if ( is_wp_error( $page_id ) ) {
			// Add to failed array with error message
			if ( is_array( $result ) ) {
				$result['failed'][] = array(
					'key'     => $key,
					'title'   => $title,
					'message' => $page_id->get_error_message(),
				);
			}
			return false;
		}

		// Assign the appropriate template based on theme type
		if ( class_exists( 'Fanfic_Page_Template' ) ) {
			$template_identifier = Fanfic_Page_Template::get_template_identifier();
			update_post_meta( $page_id, '_wp_page_template', $template_identifier );
		}

		// Successfully created, add to created array
		if ( is_array( $result ) ) {
			$result['created'][] = $key;
		}

		return $page_id;
	}

	/**
	 * Load template content
	 *
	 * Loads the content from a template file.
	 *
	 * @since 1.0.0
	 * @param string $template_name The template name.
	 * @return string Template content.
	 */
	private static function load_template_content( $template_name ) {
		$template_path = FANFIC_PLUGIN_DIR . 'templates/template-' . $template_name . '.php';

		if ( file_exists( $template_path ) ) {
			ob_start();
			include $template_path;
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Get template content for a specific page
	 *
	 * Returns the default template content for system pages.
	 *
	 * @since 1.0.0
	 * @param string $page_slug The page slug.
	 * @return string Template content with shortcodes.
	 */
	public static function get_default_template_content( $page_slug ) {
		// Special handling for main page - load from template file
		if ( 'main' === $page_slug ) {
			$template_path = FANFIC_PLUGIN_DIR . 'templates/template-main-page.php';
			if ( file_exists( $template_path ) ) {
				ob_start();
				include $template_path;
				return ob_get_clean();
			}
			// Fallback if template file doesn't exist
			return '<!-- wp:paragraph --><p>' . __( 'Welcome to the Fanfiction Archive', 'fanfiction-manager' ) . '</p><!-- /wp:paragraph -->';
		}

		$templates = array(
			'login'          => '<!-- wp:paragraph --><p>[fanfic-login-form]</p><!-- /wp:paragraph -->',
			'register'       => '<!-- wp:paragraph --><p>[fanfic-register-form]</p><!-- /wp:paragraph -->',
			'password-reset' => '<!-- wp:paragraph --><p>[fanfic-password-reset-form]</p><!-- /wp:paragraph -->',
			'dashboard'      => '<!-- wp:paragraph --><p>[user-dashboard]</p><!-- /wp:paragraph -->',
			'create-story'   => '<!-- wp:paragraph --><p>[author-create-story-form]</p><!-- /wp:paragraph -->',
			'search'         => '<!-- wp:paragraph --><p>[search-results]</p><!-- /wp:paragraph -->',
			'members'        => '<!-- wp:paragraph --><p>[user-profile]</p><!-- /wp:paragraph -->',
			'error'          => '<!-- wp:paragraph --><p>' . esc_html__( 'Error messages will be displayed here.', 'fanfiction-manager' ) . '</p><!-- /wp:paragraph -->',
			'maintenance'    => '', // Template file handles all content directly
		);

		return isset( $templates[ $page_slug ] ) ? $templates[ $page_slug ] : '';
	}
}
