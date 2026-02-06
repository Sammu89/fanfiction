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
		add_action( 'admin_footer', array( __CLASS__, 'add_external_link_target_blank' ) );
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
		// Don't register admin menus if Pretty Permalinks are disabled
		if ( Fanfic_Permalinks_Check::should_disable_plugin() ) {
			return;
		}

		// Hide the admin menu until the setup wizard is completed.
		if ( ! Fanfic_Wizard::is_completed() ) {
			return;
		}

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

		// Add Stories submenu (replaces the default first submenu) - renamed to "Story list"
		add_submenu_page(
			'fanfiction-manager',
			__( 'Story list', 'fanfiction-manager' ),
			__( 'Story list', 'fanfiction-manager' ),
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

		// Add Layout submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'Layout', 'fanfiction-manager' ),
			__( 'Layout', 'fanfiction-manager' ),
			'manage_options',
			'fanfiction-layout',
			array( __CLASS__, 'render_layout_page' )
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

		// Note: Fandoms menu item removed - now accessed via Taxonomies > Fandoms tab
		// Note: URL Name Rules menu item removed - now accessed via Settings > URL Name tab

		// Add Moderation Queue submenu
		add_submenu_page(
			'fanfiction-manager',
			__( 'Moderation Queue', 'fanfiction-manager' ),
			__( 'Moderation Queue', 'fanfiction-manager' ),
			'moderate_fanfiction',
			'fanfiction-moderation',
			array( __CLASS__, 'render_moderation_page' )
		);

		// Add My Dashboard submenu (links to frontend dashboard - opens in new tab)
		$dashboard_url = fanfic_get_dashboard_url();
		if ( $dashboard_url ) {
			add_submenu_page(
				'fanfiction-manager',
				__( 'My Dashboard', 'fanfiction-manager' ),
				__( 'My Dashboard', 'fanfiction-manager' ),
				'read',
				$dashboard_url,
				''
			);
		}

		// Add My Profile submenu (links to frontend profile page - opens in new tab)
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->ID ) {
			$profile_url = fanfic_get_user_profile_url( $current_user->ID );
			if ( $profile_url ) {
				add_submenu_page(
					'fanfiction-manager',
					__( 'My Profile', 'fanfiction-manager' ),
					__( 'My Profile', 'fanfiction-manager' ),
					'read',
					$profile_url,
					''
				);
			}
		}
	}

	/**
	 * Add target="_blank" to external admin menu links
	 *
	 * Uses JavaScript to add target="_blank" to submenu links that start with "My "
	 * since WordPress doesn't natively support this for admin menu items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_external_link_target_blank() {
		// Only run on admin pages
		if ( ! is_admin() ) {
			return;
		}
		?>
<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#toplevel_page_fanfiction-manager .wp-submenu a').each(function() {
		var linkText = $(this).text().trim();
		if (linkText.indexOf('My ') === 0) {
			$(this).attr('target', '_blank');
			$(this).attr('rel', 'noopener noreferrer');
		}
	});
});
</script>
<?php
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
		$script_deps = array( 'jquery' );
		wp_enqueue_script(
			'fanfiction-admin',
			FANFIC_PLUGIN_URL . 'assets/js/fanfiction-admin.js',
			$script_deps,
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

		// Create instance of list table
		$stories_table = new Fanfic_Stories_Table();
		$stories_table->prepare_items();
		Fanfic_Stories_Table::display_notices();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=fanfiction_story' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add Story', 'fanfiction-manager' ); ?></a>

			<hr class="wp-header-end">

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
	 * Displays the settings page with tabs: General, URL Name, Stats and Status.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Reorganized tabs - moved Email Templates, Page Templates, Custom CSS to Layout page.
	 * @return void
	 */
	public static function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current tab with validation
		$allowed_tabs = array( 'general', 'url-name', 'stats-status', 'homepage-diagnostics' );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		$current_tab = in_array( $current_tab, $allowed_tabs, true ) ? $current_tab : 'general';

		// Display admin notices
		Fanfic_Settings::display_admin_notices();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=url-name" class="nav-tab <?php echo $current_tab === 'url-name' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'URL Name', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=stats-status" class="nav-tab <?php echo $current_tab === 'stats-status' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Stats and Status', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-settings&tab=homepage-diagnostics" class="nav-tab <?php echo $current_tab === 'homepage-diagnostics' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Homepage Diagnostics', 'fanfiction-manager' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $current_tab ) {
					case 'url-name':
						Fanfic_URL_Config::render();
						break;
					case 'stats-status':
						Fanfic_Settings::render_dashboard_tab();
						break;
					case 'homepage-diagnostics':
						Fanfic_Settings::render_homepage_diagnostics_tab();
						break;
					case 'general':
					default:
						Fanfic_Settings::render_general_settings_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Layout page
	 *
	 * Displays the page template and layout settings with tabs:
	 * General, Page Templates, Email Templates, Custom CSS.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added tabs for Page Templates, Email Templates, Custom CSS.
	 * @return void
	 */
	public static function render_layout_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current tab with validation
		$allowed_tabs = array( 'general', 'page-templates', 'email-templates', 'custom-css' );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		$current_tab = in_array( $current_tab, $allowed_tabs, true ) ? $current_tab : 'general';

		// Display admin notices
		Fanfic_Settings::display_admin_notices();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=fanfiction-layout&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-layout&tab=page-templates" class="nav-tab <?php echo $current_tab === 'page-templates' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Page Templates', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-layout&tab=email-templates" class="nav-tab <?php echo $current_tab === 'email-templates' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Email Templates', 'fanfiction-manager' ); ?>
				</a>
				<a href="?page=fanfiction-layout&tab=custom-css" class="nav-tab <?php echo $current_tab === 'custom-css' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Custom CSS', 'fanfiction-manager' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $current_tab ) {
					case 'page-templates':
						Fanfic_Settings::render_page_templates_tab();
						break;
					case 'email-templates':
						Fanfic_Settings::render_email_templates_tab();
						break;
					case 'custom-css':
						Fanfic_Settings::render_custom_css_tab();
						break;
					case 'general':
					default:
						self::render_layout_general_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Layout General tab
	 *
	 * Displays the general layout settings (sidebar, breadcrumbs, content width).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function render_layout_general_tab() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fanfic_save_layout_settings">
			<?php wp_nonce_field( 'fanfic_save_layout_settings_nonce', 'fanfic_layout_settings_nonce' ); ?>

			<!-- Page Template Layout Settings -->
			<h2><?php esc_html_e( 'Page Template & Layout Settings', 'fanfiction-manager' ); ?></h2>
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'These settings control the layout and appearance of Fanfiction plugin pages. They are synchronized with the Customizer settings.', 'fanfiction-manager' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<!-- Show Sidebar -->
					<tr>
						<th scope="row">
							<label for="fanfic_show_sidebar"><?php esc_html_e( 'Show Sidebar on Fanfiction Pages', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<?php $show_sidebar = get_option( 'fanfic_show_sidebar', '1' ); ?>
							<label>
								<input type="checkbox" id="fanfic_show_sidebar" name="fanfic_show_sidebar" value="1" <?php checked( '1', $show_sidebar ); ?>>
								<?php esc_html_e( 'Display the Fanfiction Sidebar widget area on plugin pages', 'fanfiction-manager' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to widgets page */
									esc_html__( 'Manage sidebar widgets in %s', 'fanfiction-manager' ),
									'<a href="' . esc_url( admin_url( 'widgets.php' ) ) . '">' . esc_html__( 'Appearance → Widgets', 'fanfiction-manager' ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>

					<!-- Show Breadcrumbs -->
					<tr>
						<th scope="row">
							<label for="fanfic_show_breadcrumbs"><?php esc_html_e( 'Show Breadcrumbs', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<?php $show_breadcrumbs = get_option( 'fanfic_show_breadcrumbs', '1' ); ?>
							<label>
								<input type="checkbox" id="fanfic_show_breadcrumbs" name="fanfic_show_breadcrumbs" value="1" <?php checked( '1', $show_breadcrumbs ); ?>>
								<?php esc_html_e( 'Display breadcrumb navigation on plugin pages', 'fanfiction-manager' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Breadcrumbs help users understand their location and navigate through the site hierarchy. You can also use the [fanfic-breadcrumbs] shortcode to display breadcrumbs anywhere.', 'fanfiction-manager' ); ?>
							</p>
						</td>
					</tr>

					<!-- Content Width -->
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Content Width', 'fanfiction-manager' ); ?>
						</th>
						<td>
							<?php
							$page_width = get_option( 'fanfic_page_width', '1200px' );
							$is_automatic = ( 'automatic' === $page_width );
							$custom_value = '';
							$custom_unit = 'px';

							if ( ! $is_automatic && preg_match( '/^(\d+)(px|%)$/', $page_width, $matches ) ) {
								$custom_value = $matches[1];
								$custom_unit = $matches[2];
							}
							?>

							<fieldset>
								<label style="display: block; margin-bottom: 10px;">
									<input type="radio" name="fanfic_page_width_mode" value="automatic" <?php checked( $is_automatic ); ?>>
									<?php esc_html_e( 'Automatic (adapt to theme)', 'fanfiction-manager' ); ?>
								</label>

								<label style="display: block; margin-bottom: 5px;">
									<input type="radio" name="fanfic_page_width_mode" value="custom" <?php checked( ! $is_automatic ); ?>>
									<?php esc_html_e( 'Custom width:', 'fanfiction-manager' ); ?>
								</label>

								<div style="margin-left: 25px;">
									<input type="number"
										id="fanfic_page_width_value"
										name="fanfic_page_width_value"
										value="<?php echo esc_attr( $custom_value ? $custom_value : '1200' ); ?>"
										min="1"
										style="width: 100px;">

									<select id="fanfic_page_width_unit" name="fanfic_page_width_unit" style="width: 70px;">
										<option value="px" <?php selected( $custom_unit, 'px' ); ?>>px</option>
										<option value="%" <?php selected( $custom_unit, '%' ); ?>>%</option>
									</select>
								</div>
							</fieldset>

							<p class="description">
								<?php esc_html_e( 'Automatic uses theme\'s default width. Custom width: pixels are responsive (max-width), percentages are exact proportions.', 'fanfiction-manager' ); ?>
							</p>
							<p class="description" style="margin-top: 8px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107;">
								<strong><?php esc_html_e( 'Note:', 'fanfiction-manager' ); ?></strong>
								<?php esc_html_e( 'Custom width settings can work differently depending on your theme. Some themes apply their own container widths that may constrain the plugin content.', 'fanfiction-manager' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<?php submit_button( __( 'Save Layout Settings', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
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
	 * Render Fandoms page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_fandoms_page() {
		if ( class_exists( 'Fanfic_Fandoms_Admin' ) ) {
			Fanfic_Fandoms_Admin::render();
		}
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
