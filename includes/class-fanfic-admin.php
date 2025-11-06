<?php
/**
 * Admin Class
 *
 * Handles all WordPress admin interface functionality for the Fanfiction Manager plugin.
 *
 * @package FanfictionManager
 * @subpackage Admin
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Admin
 *
 * Main admin class that handles WordPress admin interface functionality.
 *
 * @since 1.0.0
 */
class Fanfic_Admin {

	/**
	 * Initialize the admin class
	 *
	 * Sets up WordPress hooks for admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu items
	 *
	 * Registers the Fanfiction top-level menu and all submenu pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_admin_menu() {
		// Add top-level menu
		add_menu_page(
			__( 'Fanfiction', 'fanfiction-manager' ),
			__( 'Fanfiction', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-manager',
			array( __CLASS__, 'render_stories_page' ),
			'dashicons-book',
			30
		);

		// Add Stories submenu (replaces the default first submenu)
		add_submenu_page(
			'fanfiction-manager',
			__( 'Stories', 'fanfiction-manager' ),
			__( 'Stories', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-manager',
			array( __CLASS__, 'render_stories_page' )
		);

		// Add Settings submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'Settings', 'fanfiction-manager' ),
			__( 'Settings', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-settings',
			array( __CLASS__, 'render_settings_page' )
		);

		// Add Users submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'Users', 'fanfiction-manager' ),
			__( 'Users', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-users',
			array( __CLASS__, 'render_users_page' )
		);

		// Add Taxonomies submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'Taxonomies', 'fanfiction-manager' ),
			__( 'Taxonomies', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-taxonomies',
			array( __CLASS__, 'render_taxonomies_page' )
		);

		// Add URL Name Rules submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'URL Name Rules', 'fanfiction-manager' ),
			__( 'URL Name Rules', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-url-rules',
			array( __CLASS__, 'render_url_rules_page' )
		);

		// Add Moderation Queue submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'Moderation Queue', 'fanfiction-manager' ),
			__( 'Moderation Queue', 'fanfiction-manager' ),
			'moderate_fanfiction',
			'fanfiction-moderation',
			array( __CLASS__, 'render_moderation_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads CSS and JavaScript files for admin pages.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		// Only load on fanfiction admin pages
		if ( strpos( $hook, 'fanfiction' ) === false ) {
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'fanfiction-admin',
			FANFIC_PLUGIN_URL . 'assets/css/fanfiction-admin.css',
			array(),
			FANFIC_VERSION,
			'all'
		);

		// Enqueue admin JavaScript
		wp_enqueue_script(
			'fanfiction-admin',
			FANFIC_PLUGIN_URL . 'assets/js/fanfiction-admin.js',
			array( 'jquery' ),
			FANFIC_VERSION,
			true
		);

		// Prepare localization data
		$localize_data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fanfiction_admin_nonce' ),
		);

		// Add moderation-specific nonce if on moderation page
		if ( strpos( $hook, 'fanfiction-moderation' ) !== false ) {
			$localize_data['moderationNonce'] = wp_create_nonce( 'fanfic_moderation_action' );
		}

		// Localize script with data
		wp_localize_script(
			'fanfiction-admin',
			'fanfictionAdmin',
			$localize_data
		);
	}

	/**
	 * Render Stories page
	 *
	 * Displays the list of all fanfiction stories.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_stories_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Display notices
		Fanfic_Stories_Table::display_notices();

		// Create instance of list table
		$stories_table = new Fanfic_Stories_Table();
		$stories_table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="get">
				<input type="hidden" name="page" value="fanfiction-manager" />
				<?php
				$stories_table->search_box( __( 'Search Stories', 'fanfiction-manager' ), 'story' );
				$stories_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Settings page
	 *
	 * Displays the settings page with tabs: Dashboard, General, Email Templates, Custom CSS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current tab with validation
		$allowed_tabs = array( 'dashboard', 'general', 'email-templates', 'page-templates', 'custom-css' );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$current_tab = in_array( $current_tab, $allowed_tabs, true ) ? $current_tab : 'dashboard';

		// Display admin notices
		Fanfic_Settings::display_admin_notices();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-settings&tab=dashboard" class="nav-tab <?php echo $current_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=email-templates" class="nav-tab <?php echo $current_tab === 'email-templates' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Email Templates', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=page-templates" class="nav-tab <?php echo $current_tab === 'page-templates' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Page Templates', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=custom-css" class="nav-tab <?php echo $current_tab === 'custom-css' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Custom CSS', 'fanfiction-manager' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $current_tab ) {
					case 'general':
						Fanfic_Settings::render_general_settings_tab();
						break;
					case 'email-templates':
						Fanfic_Settings::render_email_templates_tab();
						break;
					case 'page-templates':
						Fanfic_Settings::render_page_templates_tab();
						break;
					case 'custom-css':
						Fanfic_Settings::render_custom_css_tab();
						break;
					case 'dashboard':
					default:
						Fanfic_Settings::render_dashboard_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Users page
	 *
	 * Displays the list of fanfiction users with actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_users_page() {
		// Delegate to the Users admin class
		Fanfic_Users_Admin::render();
	}

	/**
	 * Render Taxonomies page
	 *
	 * Displays the interface to manage genres, status, and custom taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_taxonomies_page() {
		// Delegate to the Taxonomies admin class
		Fanfic_Taxonomies_Admin::render();
	}

	/**
	 * Render URL Name Rules page
	 *
	 * Displays the interface to configure URL slugs and rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_url_rules_page() {
		// Delegate to the URL configuration class
		Fanfic_URL_Config::render();
	}

	/**
	 * Render Moderation Queue page
	 *
	 * Displays the moderation queue with reported content.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_moderation_page() {
		// Delegate to the Moderation class
		Fanfic_Moderation::render();
	}
}
