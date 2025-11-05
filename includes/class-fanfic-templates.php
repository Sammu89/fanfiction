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
		add_action( 'admin_notices', array( __CLASS__, 'missing_pages_notice' ) );
		add_action( 'admin_post_fanfic_rebuild_pages', array( __CLASS__, 'rebuild_pages' ) );
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
				$custom_template = self::locate_template( 'archive-fanfiction_story.php' );
				if ( $custom_template ) {
					return $custom_template;
				}
			}
		}

		// Check if this is a fanfiction post type
		if ( is_singular( 'fanfiction_story' ) ) {
			$custom_template = self::locate_template( 'single-fanfiction_story.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		if ( is_singular( 'fanfiction_chapter' ) ) {
			$custom_template = self::locate_template( 'single-fanfiction_chapter.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		if ( is_post_type_archive( 'fanfiction_story' ) ) {
			$custom_template = self::locate_template( 'archive-fanfiction_story.php' );
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
		$missing_pages = array();

		foreach ( $page_ids as $slug => $page_id ) {
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
		// Only show notice if wizard has been completed
		$wizard_completed = get_option( 'fanfic_wizard_completed', false );
		if ( ! $wizard_completed ) {
			return;
		}

		$missing_pages = get_transient( 'fanfic_missing_pages' );

		if ( ! $missing_pages || ! current_user_can( 'manage_options' ) ) {
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
			// Custom homepage - create as editable page
			$main_page_id = self::create_or_update_page(
				'main',
				__( 'Fanfiction', 'fanfiction-manager' ),
				'',
				'<!-- wp:paragraph --><p>' . __( 'Welcome to the Fanfiction Archive', 'fanfiction-manager' ) . '</p><!-- /wp:paragraph -->',
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
			'edit-story'      => array(
				'title'    => __( 'Edit Story', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['edit-story'] ) ? $custom_slugs['edit-story'] : 'edit-story',
				'template' => 'edit-story',
			),
			'edit-chapter'    => array(
				'title'    => __( 'Edit Chapter', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['edit-chapter'] ) ? $custom_slugs['edit-chapter'] : 'edit-chapter',
				'template' => 'edit-chapter',
			),
			'edit-profile'    => array(
				'title'    => __( 'Edit Profile', 'fanfiction-manager' ),
				'slug'     => isset( $custom_slugs['edit-profile'] ) ? $custom_slugs['edit-profile'] : 'edit-profile',
				'template' => 'edit-profile',
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

		// Create child pages
		foreach ( $pages as $key => $page_data ) {
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

		// Save page IDs
		update_option( 'fanfic_system_page_ids', $page_ids );

		// Flush rewrite rules
		flush_rewrite_rules();

		// Validate all required pages exist and are published
		$required_pages = array(
			'main',
			'login',
			'register',
			'password-reset',
			'dashboard',
			'create-story',
			'edit-story',
			'edit-chapter',
			'edit-profile',
			'search',
			'members',
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
	 * Create or update the Fanfiction navigation menu
	 *
	 * Creates a WordPress navigation menu with all plugin pages.
	 * Menu includes public pages and conditional items for logged-in/logged-out users.
	 * Note: This menu is automatically rebuilt and any manual changes will be lost.
	 *
	 * @since 1.0.0
	 * @param array $page_ids Array of page IDs keyed by page key.
	 * @return int|false Menu ID on success, false on failure.
	 */
	public static function create_fanfiction_menu( $page_ids ) {
		// Check if menu already exists
		$menu_name = 'Fanfiction Automatic Menu';
		$menu_exists = wp_get_nav_menu_object( $menu_name );

		// Create menu if it doesn't exist
		if ( ! $menu_exists ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( is_wp_error( $menu_id ) ) {
				return false;
			}
		} else {
			$menu_id = $menu_exists->term_id;
			// Clear existing menu items to rebuild
			$menu_items = wp_get_nav_menu_items( $menu_id );
			if ( $menu_items ) {
				foreach ( $menu_items as $menu_item ) {
					wp_delete_post( $menu_item->ID, true );
				}
			}
		}

		// Define menu structure
		// Order determines the menu item position
		$menu_structure = array(
			array(
				'page_key'   => 'main',
				'title'      => __( 'Home', 'fanfiction-manager' ),
				'visibility' => 'all', // visible to everyone
				'type'       => 'page',
			),
			array(
				'page_key'   => 'archive',
				'title'      => __( 'Stories', 'fanfiction-manager' ),
				'visibility' => 'all',
				'type'       => 'custom',
				'url'        => get_post_type_archive_link( 'fanfiction_story' ),
			),
			array(
				'page_key'   => 'search',
				'title'      => __( 'Search', 'fanfiction-manager' ),
				'visibility' => 'all',
				'type'       => 'page',
			),
			array(
				'page_key'   => 'members',
				'title'      => __( 'Members', 'fanfiction-manager' ),
				'visibility' => 'all',
				'type'       => 'page',
			),
			array(
				'page_key'   => 'dashboard',
				'title'      => __( 'Dashboard', 'fanfiction-manager' ),
				'visibility' => 'logged_in', // only for logged-in users
				'type'       => 'page',
			),
			array(
				'page_key'   => 'create-story',
				'title'      => __( 'Add Story', 'fanfiction-manager' ),
				'visibility' => 'logged_in', // only for logged-in users
				'type'       => 'page',
			),
			array(
				'page_key'   => 'register',
				'title'      => __( 'Register', 'fanfiction-manager' ),
				'visibility' => 'logged_out', // only for logged-out users
				'type'       => 'page',
			),
			array(
				'page_key'   => 'login',
				'title'      => __( 'Login', 'fanfiction-manager' ),
				'visibility' => 'logged_out', // only for logged-out users
				'type'       => 'page',
			),
		);

		// Add menu items
		$position = 0;
		foreach ( $menu_structure as $item_config ) {
			$page_key = $item_config['page_key'];
			$item_type = isset( $item_config['type'] ) ? $item_config['type'] : 'page';

			// Skip if page doesn't exist (for page type items)
			if ( 'page' === $item_type && empty( $page_ids[ $page_key ] ) ) {
				continue;
			}

			$position++;

			// Add CSS classes based on visibility
			$classes = array();
			if ( 'logged_in' === $item_config['visibility'] ) {
				$classes[] = 'fanfic-menu-logged-in';
			} elseif ( 'logged_out' === $item_config['visibility'] ) {
				$classes[] = 'fanfic-menu-logged-out';
			}

			// Build menu item data based on type
			if ( 'custom' === $item_type ) {
				// Custom URL menu item (e.g., post type archive)
				$menu_item_data = array(
					'menu-item-url'         => $item_config['url'],
					'menu-item-type'        => 'custom',
					'menu-item-status'      => 'publish',
					'menu-item-title'       => $item_config['title'],
					'menu-item-position'    => $position,
					'menu-item-classes'     => implode( ' ', $classes ),
				);
			} else {
				// Page menu item
				$menu_item_data = array(
					'menu-item-object-id'   => $page_ids[ $page_key ],
					'menu-item-object'      => 'page',
					'menu-item-type'        => 'post_type',
					'menu-item-status'      => 'publish',
					'menu-item-title'       => $item_config['title'],
					'menu-item-position'    => $position,
					'menu-item-classes'     => implode( ' ', $classes ),
				);
			}

			$result = wp_update_nav_menu_item( $menu_id, 0, $menu_item_data );

			if ( is_wp_error( $result ) ) {
				error_log( 'Failed to add menu item: ' . $page_key . ' - ' . $result->get_error_message() );
			}
		}

		// Store menu ID in options for reference
		update_option( 'fanfic_menu_id', $menu_id );

		return $menu_id;
	}

	/**
	 * Filter menu items based on user login status
	 *
	 * Removes menu items that should only be visible to logged-in or logged-out users.
	 *
	 * @since 1.0.0
	 * @param array $items Array of menu item objects.
	 * @param object $args Menu arguments.
	 * @return array Filtered array of menu item objects.
	 */
	public static function filter_menu_items_by_login_status( $items, $args ) {
		$is_logged_in = is_user_logged_in();

		foreach ( $items as $key => $item ) {
			$classes = (array) $item->classes;

			// Remove items that should only be visible to logged-in users
			if ( ! $is_logged_in && in_array( 'fanfic-menu-logged-in', $classes, true ) ) {
				unset( $items[ $key ] );
				continue;
			}

			// Remove items that should only be visible to logged-out users
			if ( $is_logged_in && in_array( 'fanfic-menu-logged-out', $classes, true ) ) {
				unset( $items[ $key ] );
				continue;
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
		$templates = array(
			'login'          => '<!-- wp:paragraph --><p>[fanfic-login-form]</p><!-- /wp:paragraph -->',
			'register'       => '<!-- wp:paragraph --><p>[fanfic-register-form]</p><!-- /wp:paragraph -->',
			'password-reset' => '<!-- wp:paragraph --><p>[fanfic-password-reset-form]</p><!-- /wp:paragraph -->',
			'dashboard'      => '<!-- wp:paragraph --><p>[user-dashboard]</p><!-- /wp:paragraph -->',
			'create-story'   => '<!-- wp:paragraph --><p>[author-create-story-form]</p><!-- /wp:paragraph -->',
			'edit-story'     => '<!-- wp:paragraph --><p>[author-edit-story-form]</p><!-- /wp:paragraph -->',
			'edit-chapter'   => '<!-- wp:paragraph --><p>[author-edit-chapter-form]</p><!-- /wp:paragraph -->',
			'edit-profile'   => '<!-- wp:paragraph --><p>[author-edit-profile-form]</p><!-- /wp:paragraph -->',
			'search'         => '<!-- wp:paragraph --><p>[search-results]</p><!-- /wp:paragraph -->',
			'members'        => '<!-- wp:paragraph --><p>[user-profile]</p><!-- /wp:paragraph -->',
			'error'          => '<!-- wp:paragraph --><p>[fanfic-error-message]</p><!-- /wp:paragraph -->',
			'maintenance'    => '<!-- wp:paragraph --><p>[fanfic-maintenance-message]</p><!-- /wp:paragraph -->',
		);

		return isset( $templates[ $page_slug ] ) ? $templates[ $page_slug ] : '';
	}
}
