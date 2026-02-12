<?php
/**
 * Setup Wizard Class
 *
 * Handles the initial setup wizard for the Fanfiction Manager plugin.
 * Runs on first activation to configure base settings, paths, and create system pages.
 *
 * @package FanfictionManager
 * @subpackage Wizard
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Wizard
 *
 * Manages the setup wizard flow with step management, validation, and configuration.
 *
 * @since 1.0.0
 */
class Fanfic_Wizard {

	/**
	 * Single instance of the class
	 *
	 * @var Fanfic_Wizard
	 */
	private static $instance = null;

	/**
	 * Current wizard step
	 *
	 * @var int
	 */
	private $current_step = 1;

	/**
	 * Total number of wizard steps
	 *
	 * @var int
	 */
	private $total_steps = 5;

	/**
	 * Wizard steps configuration
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return Fanfic_Wizard
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->define_steps();
		$this->init_hooks();
	}

	/**
	 * Define wizard steps
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function define_steps() {
		$this->steps = array(
			1 => array(
				'id'          => 'welcome',
				'title'       => __( 'Welcome to Fanfiction Manager!', 'fanfiction-manager' ),
				'description' => __( 'This wizard will help you set up your fanfiction platform in just a few steps.

', 'fanfiction-manager' ),
			),
			2 => array(
				'id'          => 'url_settings',
				'title'       => __( 'URL Settings', 'fanfiction-manager' ),
				'description' => __( 'Configure your plugin URLs and paths', 'fanfiction-manager' ),
			),
			3 => array(
				'id'          => 'user_roles',
				'title'       => __( 'User Roles', 'fanfiction-manager' ),
				'description' => __( 'Assign moderators and administrators', 'fanfiction-manager' ),
			),
			4 => array(
				'id'          => 'taxonomy_terms',
				'title'       => __( 'Taxonomy and Classification', 'fanfiction-manager' ),
				'description' => __( 'Configure how stories are organized', 'fanfiction-manager' ),
			),
			5 => array(
				'id'          => 'complete',
				'title'       => __( 'Complete', 'fanfiction-manager' ),
				'description' => __( 'Finish setup and create pages', 'fanfiction-manager' ),
			),
		);
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		// Set title VERY EARLY - even before admin_menu
		add_action( 'admin_init', array( $this, 'set_wizard_page_title_very_early' ), 1 );

		// Add admin menu for wizard
		add_action( 'admin_menu', array( $this, 'add_wizard_menu' ) );

		// Check if wizard should be shown
		add_action( 'admin_init', array( $this, 'check_wizard_redirect' ), 10 );

		// Set page title EARLY before admin-header.php runs
		add_action( 'current_screen', array( $this, 'set_wizard_page_title_early' ) );

		// Set admin page title for wizard to prevent deprecation warnings
		add_filter( 'admin_title', array( $this, 'set_wizard_admin_title' ), 10, 2 );

		// AJAX handlers
		add_action( 'wp_ajax_fanfic_wizard_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_fanfic_wizard_complete', array( $this, 'ajax_complete_wizard' ) );

		// Enqueue wizard assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
	}

	/**
	 * Add wizard menu page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_wizard_menu() {
		// Don't register wizard if Pretty Permalinks are disabled
		if ( Fanfic_Permalinks_Check::should_disable_plugin() ) {
			return;
		}

		// Use empty string instead of null to avoid deprecation warnings
		// Empty string makes it hidden just like null, but doesn't trigger wp_normalize_path(null)
		$hook = add_submenu_page(
			'', // Empty string - hidden page (avoids NULL deprecation)
			__( 'Setup Wizard', 'fanfiction-manager' ),
			__( 'Setup Wizard', 'fanfiction-manager' ),
			'manage_options',
			'fanfic-setup-wizard',
			array( $this, 'render_wizard_page' )
		);

		// Set title immediately when registering the page
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			global $title;
			$title = __( 'Setup Wizard', 'fanfiction-manager' );
		}
	}

	/**
	 * Set page title VERY early on admin_init (priority 1)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function set_wizard_page_title_very_early() {
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			global $title, $parent_file, $submenu_file;
			$title = __( 'Setup Wizard', 'fanfiction-manager' );
			$parent_file = '';
			$submenu_file = 'fanfic-setup-wizard';
		}
	}

	/**
	 * Set page title early before admin-header.php runs
	 *
	 * @since 1.0.0
	 * @param object $screen Current screen object.
	 * @return void
	 */
	public function set_wizard_page_title_early( $screen ) {
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			global $title, $parent_file, $submenu_file;
			$title = __( 'Setup Wizard', 'fanfiction-manager' );
			$parent_file = '';
			$submenu_file = 'fanfic-setup-wizard';
		}
	}

	/**
	 * Set wizard admin page title to prevent deprecation warnings
	 *
	 * @since 1.0.0
	 * @param string $admin_title The page title.
	 * @param string $title The title tag content.
	 * @return string Modified admin title.
	 */
	public function set_wizard_admin_title( $admin_title, $title ) {
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			return __( 'Setup Wizard', 'fanfiction-manager' ) . ' - ' . get_bloginfo( 'name' );
		}
		return $admin_title;
	}

	/**
	 * Check if wizard should redirect on admin load
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_wizard_redirect() {
		// Skip if not admin or doing AJAX
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Don't redirect to wizard if Pretty Permalinks are disabled
		if ( Fanfic_Permalinks_Check::should_disable_plugin() ) {
			return;
		}

		// Skip if current page is already the wizard
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			return;
		}

	}

	/**
	 * Enqueue wizard assets
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_wizard_assets( $hook ) {
		// Only load on wizard page
		if ( 'admin_page_fanfic-setup-wizard' !== $hook ) {
			return;
		}

		// Enqueue wizard CSS
		wp_enqueue_style(
			'fanfic-wizard',
			FANFIC_PLUGIN_URL . 'assets/css/fanfic-wizard.css',
			array(),
			FANFIC_VERSION
		);

		// Enqueue wizard JavaScript
		wp_enqueue_script(
			'fanfic-wizard',
			FANFIC_PLUGIN_URL . 'assets/js/fanfic-wizard.js',
			array( 'jquery' ),
			time(), // Force cache bust for debugging
			true
		);

		// Localize script with AJAX URL and nonces
		wp_localize_script(
			'fanfic-wizard',
			'fanficWizard',
			array(
				'ajax_url'                    => admin_url( 'admin-ajax.php' ),
				'admin_url'                   => admin_url(),
				'home_url'                    => home_url(),
				'nonce'                       => wp_create_nonce( 'fanfic_wizard_nonce' ),
				'current_step'                => $this->get_current_step(),
				'total_steps'                 => $this->total_steps,
				'strings'                     => array(
					'error'           => __( 'An error occurred. Please try again.', 'fanfiction-manager' ),
					'saving'          => __( 'Saving...', 'fanfiction-manager' ),
					'completing'      => __( 'Completing setup...', 'fanfiction-manager' ),
					'success'         => __( 'Settings saved successfully!', 'fanfiction-manager' ),
					'complete_setup'  => __( 'Complete Setup', 'fanfiction-manager' ),
				),
			)
		);
	}

	/**
	 * Get current wizard step
	 *
	 * @since 1.0.0
	 * @return int Current step number.
	 */
	private function get_current_step() {
		if ( isset( $_GET['step'] ) ) {
			$step = absint( $_GET['step'] );
			return ( $step >= 1 && $step <= $this->total_steps ) ? $step : 1;
		}
		return 1;
	}

	/**
	 * Log lightweight diagnostics after a delete-data flow.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Optional context.
	 * @return void
	 */
	private function log_post_delete_debug( $message, $context = array() ) {
		if ( ! get_transient( 'fanfic_post_delete_debug' ) ) {
			return;
		}

		$memory_mb = round( memory_get_usage( true ) / 1024 / 1024 );
		$peak_mb   = round( memory_get_peak_usage( true ) / 1024 / 1024 );
		$line      = 'Fanfic Wizard Debug: ' . $message . ' | mem=' . $memory_mb . 'MB peak=' . $peak_mb . 'MB';

		if ( ! empty( $context ) ) {
			$line .= ' | context=' . wp_json_encode( $context );
		}

		error_log( $line );
	}

	/**
	 * Check if all required system pages exist
	 *
	 * @since 1.0.0
	 * @return bool True if all pages exist, false otherwise.
	 */
	private function all_pages_exist() {
		$this->log_post_delete_debug( 'all_pages_exist:start' );

		$page_ids = get_option( 'fanfic_system_page_ids', array() );

		// Only check physical WordPress pages (not dynamic pages)
		// Main page is always required — it is created in every scenario
		$required_pages = array(
			'main',
			'login',
			'register',
			'password-reset',
			'error',
			'maintenance',
		);

		error_log( 'Fanfic Wizard: Checking pages exist - required: ' . wp_json_encode( $required_pages ) );
		error_log( 'Fanfic Wizard: Page IDs from option: ' . wp_json_encode( $page_ids ) );

		// Check if all required pages exist and are published
		foreach ( $required_pages as $page_key ) {
			if ( empty( $page_ids[ $page_key ] ) ) {
				error_log( 'Fanfic Wizard: Missing page ID for: ' . $page_key );
				return false;
			}

			$page = get_post( $page_ids[ $page_key ] );
			if ( ! $page || 'publish' !== $page->post_status ) {
				error_log( 'Fanfic Wizard: Page check failed for: ' . $page_key . ' (ID: ' . $page_ids[ $page_key ] . ')' );
				if ( $page ) {
					error_log( 'Fanfic Wizard: Page status: ' . $page->post_status );
				} else {
					error_log( 'Fanfic Wizard: Page does not exist' );
				}
				return false;
			}
		}

		// Also verify dynamic page slugs are configured
		$dynamic_slugs = Fanfic_URL_Manager::get_instance()->get_slugs();
		$dynamic_pages = Fanfic_URL_Manager::get_instance()->get_dynamic_pages();
		$this->log_post_delete_debug(
			'all_pages_exist:dynamic_pages_loaded',
			array(
				'dynamic_page_count' => is_array( $dynamic_pages ) ? count( $dynamic_pages ) : 0,
				'dynamic_slug_count' => is_array( $dynamic_slugs ) ? count( $dynamic_slugs ) : 0,
			)
		);

		error_log( 'Fanfic Wizard: Checking dynamic pages: ' . wp_json_encode( $dynamic_pages ) );
		error_log( 'Fanfic Wizard: Dynamic slugs: ' . wp_json_encode( $dynamic_slugs ) );

		foreach ( $dynamic_pages as $page_key ) {
			if ( empty( $dynamic_slugs[ $page_key ] ) ) {
				error_log( 'Fanfic Wizard: Missing dynamic slug for: ' . $page_key );
				return false;
			}
		}

		error_log( 'Fanfic Wizard: All pages exist check passed' );
		$this->log_post_delete_debug( 'all_pages_exist:done' );
		return true;
	}

	/**
	 * Render wizard page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_wizard_page() {
		$this->log_post_delete_debug( 'render_wizard_page:start' );

		// Set WordPress page title to prevent deprecation warnings
		global $title, $parent_file, $admin_title, $hook_suffix;

		// Set all possible WordPress admin page variables
		$title = __( 'Setup Wizard', 'fanfiction-manager' );
		$admin_title = __( 'Setup Wizard', 'fanfiction-manager' );
		$parent_file = '';
		$hook_suffix = 'admin_page_fanfic-setup-wizard';

		// Check if wizard already completed and pages exist
		$wizard_completed = get_option( 'fanfic_wizard_completed', false );
		$all_pages_exist = $this->all_pages_exist();
		$force_run = isset( $_GET['force'] ) && 'true' === $_GET['force'];
		$this->log_post_delete_debug(
			'render_wizard_page:state_loaded',
			array(
				'wizard_completed' => (bool) $wizard_completed,
				'all_pages_exist'  => (bool) $all_pages_exist,
				'force_run'        => (bool) $force_run,
			)
		);

		// Show choice screen if wizard was completed and pages exist, unless forced
		if ( $wizard_completed && $all_pages_exist && ! $force_run ) {
			$this->render_choice_screen();
			return;
		}

		$this->current_step = $this->get_current_step();
		$this->log_post_delete_debug( 'render_wizard_page:render_step', array( 'step' => (int) $this->current_step ) );
		?>
		<div class="wrap fanfic-wizard-wrap">
			<h1><?php esc_html_e( 'Fanfiction Manager Setup Wizard', 'fanfiction-manager' ); ?></h1>

			<!-- Progress Bar -->
			<div class="fanfic-wizard-progress">
				<div class="fanfic-wizard-progress-bar" style="width: <?php echo esc_attr( ( $this->current_step / $this->total_steps ) * 100 ); ?>%;"></div>
			</div>

			<!-- Step Indicator -->
			<div class="fanfic-wizard-steps">
				<?php foreach ( $this->steps as $step_num => $step_data ) : ?>
					<div class="fanfic-wizard-step <?php echo $step_num === $this->current_step ? 'active' : ''; ?> <?php echo $step_num < $this->current_step ? 'completed' : ''; ?>">
						<div class="fanfic-wizard-step-number"><?php echo esc_html( $step_num ); ?></div>
						<div class="fanfic-wizard-step-title"><?php echo esc_html( $step_data['title'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Wizard Content -->
			<div class="fanfic-wizard-content">
				<?php $this->render_step_content( $this->current_step ); ?>
			</div>

			<!-- Navigation Buttons -->
			<div class="fanfic-wizard-navigation">
				<?php if ( $this->current_step > 1 && $this->current_step < $this->total_steps ) : ?>
					<?php
					$prev_url = admin_url( 'admin.php?page=fanfic-setup-wizard&step=' . ( $this->current_step - 1 ) );
					if ( $force_run ) {
						$prev_url .= '&force=true';
					}
					?>
					<a href="<?php echo esc_url( $prev_url ); ?>" class="button button-secondary fanfic-wizard-prev">
						<?php esc_html_e( 'Previous', 'fanfiction-manager' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $this->current_step < $this->total_steps ) : ?>
					<button type="button" class="button button-primary fanfic-wizard-next">
						<?php esc_html_e( 'Next', 'fanfiction-manager' ); ?>
					</button>

					<?php if ( $this->current_step === 3 ) : // Add skip button for user roles step ?>
						<?php
						$skip_url = admin_url( 'admin.php?page=fanfic-setup-wizard&step=' . ( $this->current_step + 1 ) );
						if ( $force_run ) {
							$skip_url .= '&force=true';
						}
						?>
						<a href="<?php echo esc_url( $skip_url ); ?>" class="button button-secondary fanfic-wizard-skip" style="margin-left: 10px;">
							<?php esc_html_e( 'Skip (Optional)', 'fanfiction-manager' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $this->current_step === $this->total_steps ) : ?>
					<button type="button" class="button button-primary fanfic-wizard-complete">
						<?php esc_html_e( 'Complete Setup', 'fanfiction-manager' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<!-- Messages -->
			<div class="fanfic-wizard-messages"></div>
		</div>
		<?php
	}
/**
 * Render choice screen for re-activation
 *
 * @since 1.0.0
 * @return void
 */
	private function render_choice_screen() {
	?>
	<div class="wrap fanfic-wizard-wrap">
		<h1><?php esc_html_e( 'Setup Wizard', 'fanfiction-manager' ); ?></h1>
			<div class="fanfic-wizard-choice" style="max-width: 600px; margin: 40px auto; text-align: center;">
			<div class="fanfic-wizard-choice-icon"><span class="dashicons dashicons-admin-tools" style="font-size: 80px; width: 80px; height: 80px; color: #2271b1;"></span></div>
			<h2><?php esc_html_e( 'Wizard Already Completed', 'fanfiction-manager' ); ?></h2>
			<p style="font-size: 16px;"><?php esc_html_e( 'The setup wizard has already been completed and all required pages exist.', 'fanfiction-manager' ); ?></p>
			<p style="font-size: 16px;"><?php esc_html_e( 'Would you like to run the wizard again or go to the plugin settings?', 'fanfiction-manager' ); ?></p>
			<div class="notice notice-warning inline" style="margin: 20px 0; text-align: left;"><p><strong><?php esc_html_e( 'Warning:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Running the wizard again is not recommended. If you change URL settings, 301 redirects will be created from old URLs to new ones.', 'fanfiction-manager' ); ?></p></div>
			<div class="fanfic-wizard-choice-buttons" style="margin-top: 30px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-settings' ) ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Go to Settings', 'fanfiction-manager' ); ?></a> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfic-setup-wizard&force=true&step=1' ) ); ?>" class="button button-secondary button-hero"><?php esc_html_e( 'Run Wizard Again', 'fanfiction-manager' ); ?></a></div>
		</div>
	</div>
	<?php
}


	/**
	 * Render step content
	 *
	 * @since 1.0.0
	 * @param int $step Step number.
	 * @return void
	 */
	private function render_step_content( $step ) {
		$step_data = $this->steps[ $step ];
		?>
		<div class="fanfic-wizard-step-content" data-step="<?php echo esc_attr( $step ); ?>">
			<h2><?php echo esc_html( $step_data['title'] ); ?></h2>
			<p class="description"><?php echo esc_html( $step_data['description'] ); ?></p>

			<?php
			switch ( $step ) {
				case 1:
					$this->render_welcome_step();
					break;
				case 2:
					$this->render_url_settings_step();
					break;
				case 3:
					$this->render_user_roles_step();
					break;
				case 4:
					$this->render_taxonomy_terms_step();
					break;
				case 5:
					$this->render_complete_step();
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render welcome step
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_welcome_step() {
		?>
		<form id="fanfic-wizard-form-step-1" class="fanfic-wizard-form">
			<?php
			if ( class_exists( 'Fanfic_URL_Config' ) ) {
				Fanfic_URL_Config::render_homepage_settings( true );
			}
			?>
			<!-- Statistical Data Notice -->
			<div class="notice notice-info inline" style="margin: 20px 0;">
				<p>
					<strong><?php esc_html_e( 'Anonymous Usage Statistics:', 'fanfiction-manager' ); ?></strong>
					<?php esc_html_e( 'This plugin collects anonymous statistical data to help improve and maintain the plugin. This includes basic usage metrics such as plugin version, WordPress version, and feature usage. No personal or sensitive information is collected.', 'fanfiction-manager' ); ?>
				</p>
			</div>
			<p style="margin-top: 20px;"><strong><?php esc_html_e( 'By clicking "Next", you accept the collection of anonymous usage statistics and agree to proceed with the setup process.', 'fanfiction-manager' ); ?></strong></p>
		</form>
		<?php
	}

	/**
	 * Render URL settings step
	 *
	 * Uses the same form fields as the URL Configuration admin page
	 * to ensure consistency and maintainability.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_url_settings_step() {
		?>
		<form id="fanfic-wizard-form-step-2" class="fanfic-wizard-form">
			<?php
			if ( class_exists( 'Fanfic_URL_Config' ) ) {
				// Render the radio buttons for URL mode
				Fanfic_URL_Config::render_url_mode_selection( true );
				// Render the rest of the URL slug fields
				Fanfic_URL_Config::render_form_fields( true );
			}
			?>
		</form>
		<?php
		// Scripts and styles are still needed for the previews
		if ( class_exists( 'Fanfic_URL_Config' ) ) {
			Fanfic_URL_Config::render_styles();
			Fanfic_URL_Config::render_scripts();
		}
	}

	/**
	 * Render user roles step
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_user_roles_step() {
		// Get all WordPress users (excluding current user)
		$current_user_id = get_current_user_id();
		$users = get_users( array(
			'exclude' => array( $current_user_id ),
			'orderby' => 'display_name',
		) );

		$assigned_moderators = get_option( 'fanfic_wizard_moderators', array() );
		$assigned_admins = get_option( 'fanfic_wizard_admins', array() );
		?>
		<form id="fanfic-wizard-form-step-3" class="fanfic-wizard-form">
			<?php wp_nonce_field( 'fanfic_wizard_step_3', 'fanfic_wizard_nonce_step_3' ); ?>

			<div class="fanfic-wizard-roles-info">
				<h3><?php esc_html_e( 'Assign User Roles', 'fanfiction-manager' ); ?></h3>
				<p><?php esc_html_e( 'You can assign existing WordPress users to fanfiction-specific roles:', 'fanfiction-manager' ); ?></p>

				<div class="fanfic-wizard-role-descriptions">
					<div class="fanfic-role-description">
						<h4><?php esc_html_e( 'Fanfiction Moderator', 'fanfiction-manager' ); ?></h4>
						<ul style="list-style: disc; margin-left: 2em;">
							<li><?php esc_html_e( 'Can moderate all content (approve, reject, delete)', 'fanfiction-manager' ); ?></li>
							<li><?php esc_html_e( 'Can manage reports and user suspensions', 'fanfiction-manager' ); ?></li>
							<li><?php esc_html_e( 'Has access to moderation queue', 'fanfiction-manager' ); ?></li>
							<li><?php esc_html_e( 'Cannot change plugin settings', 'fanfiction-manager' ); ?></li>
						</ul>
					</div>

					<div class="fanfic-role-description">
						<h4><?php esc_html_e( 'Fanfiction Administrator', 'fanfiction-manager' ); ?></h4>
						<ul style="list-style: disc; margin-left: 2em;">
							<li><?php esc_html_e( 'Has all moderator capabilities', 'fanfiction-manager' ); ?></li>
							<li><?php esc_html_e( 'Can configure plugin settings', 'fanfiction-manager' ); ?></li>
							<li><?php esc_html_e( 'Can manage taxonomies and URL rules', 'fanfiction-manager' ); ?></li>
							<li><?php esc_html_e( 'Full access to all plugin features', 'fanfiction-manager' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<?php if ( empty( $users ) ) : ?>
				<p class="description"><?php esc_html_e( 'No other users found. You can assign roles later from the Users page.', 'fanfiction-manager' ); ?></p>
			<?php else : ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Assign Moderators', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<select name="fanfic_moderators[]" id="fanfic_moderators" multiple size="10" style="width: 100%; max-width: 500px;">
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( in_array( $user->ID, $assigned_moderators, true ) ); ?>>
										<?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple users. You can skip this step and assign roles later.', 'fanfiction-manager' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Assign Administrators', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<select name="fanfic_admins[]" id="fanfic_admins" multiple size="10" style="width: 100%; max-width: 500px;">
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( in_array( $user->ID, $assigned_admins, true ) ); ?>>
										<?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple users. You can skip this step and assign roles later.', 'fanfiction-manager' ); ?></p>
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Render taxonomy terms step
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_taxonomy_terms_step() {
		$settings = get_option( Fanfic_Settings::OPTION_NAME, array() );
		$draft = get_option( 'fanfic_wizard_draft', array() );
		$step_4 = isset( $draft['step_4'] ) && is_array( $draft['step_4'] ) ? $draft['step_4'] : array();
		$enable_fandoms = isset( $settings['enable_fandom_classification'] ) ? (bool) $settings['enable_fandom_classification'] : true;
		$enable_warnings = isset( $settings['enable_warnings'] ) ? (bool) $settings['enable_warnings'] : true;
		$enable_languages = isset( $settings['enable_language_classification'] ) ? (bool) $settings['enable_language_classification'] : true;
		$enable_coauthors = array_key_exists( 'enable_coauthors', $step_4 )
			? (bool) $step_4['enable_coauthors']
			: true;
		$enable_dislikes = array_key_exists( 'enable_dislikes', $step_4 )
			? (bool) $step_4['enable_dislikes']
			: ( isset( $settings['enable_dislikes'] ) ? (bool) $settings['enable_dislikes'] : false );
		$create_samples = array_key_exists( 'create_samples', $step_4 )
			? (bool) $step_4['create_samples']
			: true;
		$allow_sexual = isset( $settings['allow_sexual_content'] ) ? (bool) $settings['allow_sexual_content'] : false;
		$allow_pornographic = isset( $settings['allow_pornographic_content'] ) ? (bool) $settings['allow_pornographic_content'] : false;
		?>
		<form id="fanfic-wizard-form-step-4" class="fanfic-wizard-form">
			<?php wp_nonce_field( 'fanfic_wizard_step_4', 'fanfic_wizard_nonce_step_4' ); ?>

			<div class="fanfic-wizard-taxonomy-info">
				<h3 style="margin: 0 0 8px;"><?php esc_html_e( 'Taxonomy and Classification', 'fanfiction-manager' ); ?></h3>
				<p><?php esc_html_e( 'Select the options that will help you organize your stories.', 'fanfiction-manager' ); ?></p>
				<p class="description"><?php esc_html_e( 'You can individually manage each term later in the Taxonomy menu.', 'fanfiction-manager' ); ?></p>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_genres"><?php esc_html_e( 'Genre Terms', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_genres" checked disabled>
							<?php esc_html_e( 'Enabled (mandatory)', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Classifies stories by genre, such as Romance, Adventure, Drama, etc.', 'fanfiction-manager' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_status"><?php esc_html_e( 'Status Terms', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_status" checked disabled>
							<?php esc_html_e( 'Enabled (mandatory)', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Lets users know if the story is ongoing, finished, on hiatus, etc.', 'fanfiction-manager' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_fandoms"><?php esc_html_e( 'Fandom Classification', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_fandoms" name="fanfic_enable_fandom_classification" value="1" <?php checked( $enable_fandoms, true ); ?>>
							<?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Lets users choose from several pre-filled fandoms to classify their story.', 'fanfiction-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_warnings"><?php esc_html_e( 'Warnings/Age System', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_warnings" name="fanfic_enable_warnings" value="1" <?php checked( $enable_warnings, true ); ?>>
							<?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Lets users choose warnings for story content, enabling the auto age-rating system.', 'fanfiction-manager' ); ?></p>
						<div id="fanfic-wizard-warning-suboptions" style="margin-top: 10px; margin-left: 22px;">
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" id="fanfic_wizard_allow_sexual_content" name="fanfic_allow_sexual_content" value="1" <?php checked( $allow_sexual, true ); ?>>
								<?php esc_html_e( 'Allow sexual content', 'fanfiction-manager' ); ?>
							</label>
							<label style="display: block;">
								<input type="checkbox" id="fanfic_wizard_allow_pornographic_content" name="fanfic_allow_pornographic_content" value="1" <?php checked( $allow_pornographic, true ); ?>>
								<?php esc_html_e( 'Allow pornographic content', 'fanfiction-manager' ); ?>
							</label>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_languages"><?php esc_html_e( 'Languages', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_languages" name="fanfic_enable_language_classification" value="1" <?php checked( $enable_languages, true ); ?>>
							<?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Lets users choose story language and enables linking between versions of the same story in different languages.', 'fanfiction-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_coauthors"><?php esc_html_e( 'Co-author Functionality', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_coauthors" name="fanfic_enable_coauthors" value="1" <?php checked( $enable_coauthors, true ); ?>>
							<?php esc_html_e( 'Enable co-author functionality', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Allow authors to invite co-authors to collaborate on stories.', 'fanfiction-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fanfic_wizard_enable_dislikes"><?php esc_html_e( 'Dislikes', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_enable_dislikes" name="fanfic_enable_dislikes" value="1" <?php checked( $enable_dislikes, true ); ?>>
							<?php esc_html_e( 'Enable dislike interactions', 'fanfiction-manager' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Adds a dislike action for chapters. Likes and dislikes are mutually exclusive.', 'fanfiction-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fanfic_wizard_create_samples"><?php esc_html_e( 'Sample Stories', 'fanfiction-manager' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fanfic_wizard_create_samples" name="fanfic_create_samples" value="1" <?php checked( $create_samples, true ); ?>>
							<?php esc_html_e( 'Create 2 sample stories for testing', 'fanfiction-manager' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</form>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			function togglePornographicDependency() {
				var pornChecked = $('#fanfic_wizard_allow_pornographic_content').is(':checked');
				if ( pornChecked ) {
					$('#fanfic_wizard_allow_sexual_content').prop('checked', true).prop('disabled', true);
				} else {
					var warningsEnabled = $('#fanfic_wizard_enable_warnings').is(':checked');
					$('#fanfic_wizard_allow_sexual_content').prop('disabled', !warningsEnabled);
				}
			}

			function toggleWarningSubOptions() {
				var warningsEnabled = $('#fanfic_wizard_enable_warnings').is(':checked');
				$('#fanfic-wizard-warning-suboptions').toggle(warningsEnabled);
				if ( !warningsEnabled ) {
					$('#fanfic_wizard_allow_sexual_content, #fanfic_wizard_allow_pornographic_content').prop('disabled', true);
				} else {
					$('#fanfic_wizard_allow_pornographic_content').prop('disabled', false);
					togglePornographicDependency();
				}
			}

			$('#fanfic_wizard_enable_warnings').on('change', toggleWarningSubOptions);
			$('#fanfic_wizard_allow_pornographic_content').on('change', togglePornographicDependency);
			toggleWarningSubOptions();
		});
		</script>
		<?php
	}

	/**
	 * Render configuration synthesis table
	 *
	 * Shows a summary of all configuration settings from the wizard draft.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_configuration_synthesis() {
		// Get draft data
		$draft = get_option( 'fanfic_wizard_draft', array() );
		$step_1 = isset( $draft['step_1'] ) ? $draft['step_1'] : array();
		$step_2 = isset( $draft['step_2'] ) ? $draft['step_2'] : array();
		$step_4 = isset( $draft['step_4'] ) ? $draft['step_4'] : array();

		// Extract key settings early so they're available to all sections
		$use_base_slug = isset( $step_2['use_base_slug'] ) ? $step_2['use_base_slug'] : 1;

		// Get saved role data (step 3 saves directly)
		$moderators = get_option( 'fanfic_wizard_moderators', array() );
		$admins = get_option( 'fanfic_wizard_admins', array() );
		?>
		<div class="fanfic-wizard-synthesis" style="margin: 20px 0; padding: 15px; border: 2px solid #2271b1; background: #f0f6fc; border-radius: 4px;">
			<h4 style="margin-top: 0; color: #2271b1;">
				<span class="dashicons dashicons-visibility" style="font-size: 20px; width: 20px; height: 20px; vertical-align: text-bottom;"></span>
				<?php esc_html_e( 'Configuration Summary', 'fanfiction-manager' ); ?>
			</h4>
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Please review your configuration carefully before proceeding.', 'fanfiction-manager' ); ?>
			</p>

			<table class="widefat" style="background: white;">
				<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'Setting', 'fanfiction-manager' ); ?></th>
						<th><?php esc_html_e( 'Value', 'fanfiction-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Step 1: Homepage Settings -->
					<tr>
						<td colspan="2" style="background: #f9f9f9; font-weight: bold;">
							<?php esc_html_e( 'Homepage Settings', 'fanfiction-manager' ); ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Main Page Mode', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							$main_page_mode = isset( $step_1['main_page_mode'] ) ? $step_1['main_page_mode'] : 'custom_homepage';
							if ( 'stories_homepage' === $main_page_mode ) {
								esc_html_e( 'Stories as Homepage', 'fanfiction-manager' );
							} else {
								esc_html_e( 'Custom Homepage', 'fanfiction-manager' );
							}
							?>
						</td>
					</tr>
					<?php if ( 'custom_homepage' === $main_page_mode ) : ?>
						<tr>
							<td><?php esc_html_e( 'Homepage Source', 'fanfiction-manager' ); ?></td>
							<td>
								<?php
								$homepage_source = isset( $step_1['homepage_source'] ) ? $step_1['homepage_source'] : 'fanfiction_page';
								switch ( $homepage_source ) {
									case 'fanfiction_page':
										esc_html_e( 'Fanfiction Custom Page', 'fanfiction-manager' );
										break;
									case 'existing_page':
										$page_id = isset( $step_1['homepage_source_id'] ) ? $step_1['homepage_source_id'] : 0;
										if ( $page_id > 0 ) {
											$page = get_post( $page_id );
											printf( esc_html__( 'Existing Page: %s', 'fanfiction-manager' ), $page ? esc_html( $page->post_title ) : esc_html__( 'Unknown', 'fanfiction-manager' ) );
										} else {
											esc_html_e( 'Existing Page (Not Selected)', 'fanfiction-manager' );
										}
										break;
									case 'wordpress_archive':
										esc_html_e( 'WordPress Posts Archive', 'fanfiction-manager' );
										break;
									default:
										echo esc_html( $homepage_source );
								}
								?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( ! $use_base_slug ) : ?>
						<tr>
							<td><?php esc_html_e( 'Expected WordPress Settings', 'fanfiction-manager' ); ?></td>
							<td>
								<?php
								// Resolve expected WP homepage settings
								$expected_state = array(
									'use_base_slug'      => $use_base_slug,
									'main_page_mode'     => $main_page_mode,
									'homepage_source'    => isset( $step_1['homepage_source'] ) ? $step_1['homepage_source'] : 'fanfiction_page',
									'homepage_source_id' => isset( $step_1['homepage_source_id'] ) ? $step_1['homepage_source_id'] : 0,
									'main_page_id'       => 0, // Will be set after page creation
									'stories_page_id'    => 0, // Will be set after page creation
								);

								if ( class_exists( 'Fanfic_Homepage_State' ) ) {
									$target = Fanfic_Homepage_State::resolve_wp_front_page_target( $expected_state );
									if ( $target ) {
										printf(
											'<code>show_on_front = %s</code>, <code>page_on_front = %d</code>',
											esc_html( $target['show_on_front'] ),
											(int) $target['page_on_front']
										);
									} else {
										esc_html_e( 'Not applicable (Base Slug Mode enabled)', 'fanfiction-manager' );
									}
								} else {
									esc_html_e( 'Unable to resolve (Homepage State class not loaded)', 'fanfiction-manager' );
								}
								?>
							</td>
						</tr>
					<?php endif; ?>

					<!-- Step 2: URL Settings -->
					<tr>
						<td colspan="2" style="background: #f9f9f9; font-weight: bold;">
							<?php esc_html_e( 'URL Settings', 'fanfiction-manager' ); ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Base Slug Mode', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo $use_base_slug ? esc_html__( 'Enabled', 'fanfiction-manager' ) : esc_html__( 'Disabled (Root URL Mode)', 'fanfiction-manager' );
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Base Slug', 'fanfiction-manager' ); ?></td>
						<td>
							<?php if ( ! $use_base_slug ) : ?>
								<?php esc_html_e( 'N/A (Root URL Mode)', 'fanfiction-manager' ); ?>
							<?php else : ?>
								<code><?php echo esc_html( array_key_exists( 'base_slug', $step_2 ) ? ( $step_2['base_slug'] !== '' ? $step_2['base_slug'] : '(none)' ) : 'fanfiction' ); ?></code>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Story Path', 'fanfiction-manager' ); ?></td>
						<td><code><?php echo esc_html( isset( $step_2['story_path'] ) ? $step_2['story_path'] : 'story' ); ?></code></td>
					</tr>

					<!-- Step 4: Feature Toggles -->
					<tr>
						<td colspan="2" style="background: #f9f9f9; font-weight: bold;">
							<?php esc_html_e( 'Feature Toggles', 'fanfiction-manager' ); ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Fandom Classification', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo isset( $step_4['enable_fandoms'] ) && $step_4['enable_fandoms']
								? '<span style="color: #46b450;">&#10004; ' . esc_html__( 'Enabled', 'fanfiction-manager' ) . '</span>'
								: '<span style="color: #999;">&#10006; ' . esc_html__( 'Disabled', 'fanfiction-manager' ) . '</span>';
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Warnings/Age System', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo isset( $step_4['enable_warnings'] ) && $step_4['enable_warnings']
								? '<span style="color: #46b450;">&#10004; ' . esc_html__( 'Enabled', 'fanfiction-manager' ) . '</span>'
								: '<span style="color: #999;">&#10006; ' . esc_html__( 'Disabled', 'fanfiction-manager' ) . '</span>';
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Language Classification', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo isset( $step_4['enable_languages'] ) && $step_4['enable_languages']
								? '<span style="color: #46b450;">&#10004; ' . esc_html__( 'Enabled', 'fanfiction-manager' ) . '</span>'
								: '<span style="color: #999;">&#10006; ' . esc_html__( 'Disabled', 'fanfiction-manager' ) . '</span>';
							?>
						</td>
					</tr>

					<tr>
						<td><?php esc_html_e( 'Co-author Functionality', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo isset( $step_4['enable_coauthors'] ) && $step_4['enable_coauthors']
								? '<span style="color: #46b450;">&#10004; ' . esc_html__( 'Enabled', 'fanfiction-manager' ) . '</span>'
								: '<span style="color: #999;">&#10006; ' . esc_html__( 'Disabled', 'fanfiction-manager' ) . '</span>';
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Dislikes', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo isset( $step_4['enable_dislikes'] ) && $step_4['enable_dislikes']
								? '<span style="color: #46b450;">&#10004; ' . esc_html__( 'Enabled', 'fanfiction-manager' ) . '</span>'
								: '<span style="color: #999;">&#10006; ' . esc_html__( 'Disabled', 'fanfiction-manager' ) . '</span>';
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Sample Stories', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							echo ! empty( $step_4['create_samples'] )
								? '<span style="color: #46b450;">&#10004; ' . esc_html__( 'Yes', 'fanfiction-manager' ) . '</span>'
								: '<span style="color: #999;">&#10006; ' . esc_html__( 'No', 'fanfiction-manager' ) . '</span>';
							?>
						</td>
					</tr>

					<!-- Step 3: User Roles -->
					<tr>
						<td colspan="2" style="background: #f9f9f9; font-weight: bold;">
							<?php esc_html_e( 'User Roles', 'fanfiction-manager' ); ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Moderators', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							if ( ! empty( $moderators ) ) {
								$mod_names = array();
								foreach ( $moderators as $user_id ) {
									$user = get_user_by( 'id', $user_id );
									if ( $user ) {
										$mod_names[] = esc_html( $user->display_name );
									}
								}
								echo implode( ', ', $mod_names );
							} else {
								esc_html_e( 'None', 'fanfiction-manager' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Administrators', 'fanfiction-manager' ); ?></td>
						<td>
							<?php
							if ( ! empty( $admins ) ) {
								$admin_names = array();
								foreach ( $admins as $user_id ) {
									$user = get_user_by( 'id', $user_id );
									if ( $user ) {
										$admin_names[] = esc_html( $user->display_name );
									}
								}
								echo implode( ', ', $admin_names );
							} else {
								esc_html_e( 'None', 'fanfiction-manager' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render complete step
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_complete_step() {
		$wizard_completed = get_option( 'fanfic_wizard_completed', false );

		if ( $wizard_completed ) :
			?>
			<div class="fanfic-wizard-complete fanfic-wizard-success">
				<div class="fanfic-wizard-complete-icon">
					<span class="dashicons dashicons-yes-alt" style="font-size: 80px; width: 80px; height: 80px; color: #46b450;"></span>
				</div>
				<h3><?php esc_html_e( 'Setup Already Completed!', 'fanfiction-manager' ); ?></h3>
				<p><?php esc_html_e( 'The wizard has already been completed for this installation.', 'fanfiction-manager' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Go to Settings', 'fanfiction-manager' ); ?>
					</a>
				</p>
			</div>
		<?php else : ?>
			<div class="fanfic-wizard-complete">
				<div class="fanfic-wizard-complete-icon">
					<span class="dashicons dashicons-admin-tools" style="font-size: 80px; width: 80px; height: 80px;"></span>
				</div>
				<h3><?php esc_html_e( 'Ready to Complete Setup', 'fanfiction-manager' ); ?></h3>

				<?php
				// Display configuration synthesis
				$this->render_configuration_synthesis();
				?>
			</div>

		<?php endif;
	}

	/**
	 * AJAX handler for saving wizard steps
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_save_step() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_wizard_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Get step number
		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;

		if ( $step < 1 || $step > $this->total_steps ) {
			wp_send_json_error( array( 'message' => __( 'Invalid step.', 'fanfiction-manager' ) ) );
		}

		// Save step data based on step number
		switch ( $step ) {
			case 1:
				$this->save_welcome_step();
				break;
			case 2:
				$this->save_url_settings_step();
				break;
			case 3:
				$this->save_user_roles_step();
				break;
			case 4:
				$this->save_taxonomy_terms_step();
				break;
			default:
				// Step 5 doesn't require saving
				break;
		}

		// Build next URL and preserve force parameter if present
		$next_url = admin_url( 'admin.php?page=fanfic-setup-wizard&step=' . ( $step + 1 ) );
		// Check for force parameter in POST data (from AJAX request)
		if ( isset( $_POST['force'] ) && 'true' === $_POST['force'] ) {
			$next_url .= '&force=true';
		}

		wp_send_json_success( array(
			'message'  => __( 'Settings saved successfully!', 'fanfiction-manager' ),
			'next_url' => $next_url,
		) );
	}

	/**
	 * Save welcome step data
	 *
	 * Saves the main page mode and base slug choice from step 1 to draft.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_welcome_step() {
		error_log( '[Fanfic Wizard Step 1] Starting save_welcome_step' );
		error_log( '[Fanfic Wizard Step 1] POST data: ' . wp_json_encode( $_POST ) );

		// Get draft
		$draft = get_option( 'fanfic_wizard_draft', array() );

		// Collect step 1 data
		$step_data = array();

		// Main page mode
		if ( isset( $_POST['fanfic_main_page_mode'] ) ) {
			$main_page_mode = sanitize_text_field( wp_unslash( $_POST['fanfic_main_page_mode'] ) );
			if ( in_array( $main_page_mode, array( 'stories_homepage', 'custom_homepage' ), true ) ) {
				$step_data['main_page_mode'] = $main_page_mode;
			}
		}

		// Homepage source (for custom_homepage mode)
		// The form uses fanfic_homepage_source_select
		if ( isset( $_POST['fanfic_main_page_mode'] ) && 'custom_homepage' === $_POST['fanfic_main_page_mode'] ) {
			if ( isset( $_POST['fanfic_homepage_source_select'] ) ) {
				$source = sanitize_text_field( wp_unslash( $_POST['fanfic_homepage_source_select'] ) );
				if ( 'fanfiction_page' === $source ) {
					$step_data['homepage_source'] = 'fanfiction_page';
					$step_data['homepage_source_id'] = 0;
				} elseif ( 'wordpress_archive' === $source ) {
					$step_data['homepage_source'] = 'wordpress_archive';
					$step_data['homepage_source_id'] = 0;
				} elseif ( preg_match( '/^page:(\d+)$/', $source, $matches ) ) {
					$post_id = (int) $matches[1];
					$post = get_post( $post_id );
					if ( $post && 'publish' === $post->post_status && 'page' === $post->post_type ) {
						$step_data['homepage_source'] = 'existing_page';
						$step_data['homepage_source_id'] = $post_id;
					}
				}
			}
		} elseif ( isset( $_POST['fanfic_main_page_mode'] ) && 'stories_homepage' === $_POST['fanfic_main_page_mode'] ) {
			// If stories_homepage, set source to fanfiction_page
			$step_data['homepage_source'] = 'fanfiction_page';
			$step_data['homepage_source_id'] = 0;
		}

		error_log( '[Fanfic Wizard Step 1] Collected step data: ' . wp_json_encode( $step_data ) );

		// Save to draft
		$draft['step_1'] = $step_data;
		update_option( 'fanfic_wizard_draft', $draft );

		error_log( '[Fanfic Wizard Step 1] Saved to draft. Full draft: ' . wp_json_encode( $draft ) );
	}

	/**
	 * Save URL settings step data
	 *
	 * Saves all URL slug configuration from step 2 to draft.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_url_settings_step() {
		error_log( '[Fanfic Wizard Step 2] Starting save_url_settings_step' );
		error_log( '[Fanfic Wizard Step 2] POST data: ' . wp_json_encode( $_POST ) );

		// Get draft
		$draft = get_option( 'fanfic_wizard_draft', array() );

		// Collect step 2 data
		$step_data = array();

		// Use base slug (radio button: 1 = use base slug, 0 = no base slug)
		if ( isset( $_POST['fanfic_use_base_slug'] ) ) {
			$step_data['use_base_slug'] = '1' === $_POST['fanfic_use_base_slug'] ? 1 : 0;
		}

		// Base slug value (only relevant when use_base_slug = 1)
		if ( isset( $_POST['fanfic_base_slug'] ) ) {
			$step_data['base_slug'] = sanitize_title( wp_unslash( $_POST['fanfic_base_slug'] ) );
		}

		// Story path
		if ( isset( $_POST['fanfic_story_path'] ) ) {
			$step_data['story_path'] = sanitize_title( wp_unslash( $_POST['fanfic_story_path'] ) );
		}

		// Chapter slugs
		$chapter_slugs = array();
		if ( isset( $_POST['fanfic_chapter_slug_prologue'] ) ) {
			$chapter_slugs['prologue'] = sanitize_title( wp_unslash( $_POST['fanfic_chapter_slug_prologue'] ) );
		}
		if ( isset( $_POST['fanfic_chapter_slug_chapter'] ) ) {
			$chapter_slugs['chapter'] = sanitize_title( wp_unslash( $_POST['fanfic_chapter_slug_chapter'] ) );
		}
		if ( isset( $_POST['fanfic_chapter_slug_epilogue'] ) ) {
			$chapter_slugs['epilogue'] = sanitize_title( wp_unslash( $_POST['fanfic_chapter_slug_epilogue'] ) );
		}
		if ( ! empty( $chapter_slugs ) ) {
			$step_data['chapter_slugs'] = $chapter_slugs;
		}

		// System page slugs
		$system_page_slugs = array();
		$system_page_keys = array( 'login', 'register', 'password-reset', 'search', 'error', 'maintenance' );
		foreach ( $system_page_keys as $key ) {
			$post_key = 'fanfic_page_slug_' . str_replace( '-', '_', $key );
			if ( isset( $_POST[ $post_key ] ) ) {
				$system_page_slugs[ $key ] = sanitize_title( wp_unslash( $_POST[ $post_key ] ) );
			}
		}
		if ( ! empty( $system_page_slugs ) ) {
			$step_data['system_page_slugs'] = $system_page_slugs;
		}

		// Dynamic page slugs
		if ( isset( $_POST['fanfic_dashboard_slug'] ) ) {
			$step_data['dashboard_slug'] = sanitize_title( wp_unslash( $_POST['fanfic_dashboard_slug'] ) );
		}
		if ( isset( $_POST['fanfic_members_slug'] ) ) {
			$step_data['members_slug'] = sanitize_title( wp_unslash( $_POST['fanfic_members_slug'] ) );
		}

		error_log( '[Fanfic Wizard Step 2] Collected step data: ' . wp_json_encode( $step_data ) );

		// Save to draft
		$draft['step_2'] = $step_data;
		update_option( 'fanfic_wizard_draft', $draft );

		error_log( '[Fanfic Wizard Step 2] Saved to draft. Full draft: ' . wp_json_encode( $draft ) );
	}

	/**
	 * Flush all rewrite rules
	 *
	 * Helper method to register and flush rewrite rules.
	 * Shared logic to avoid duplication.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function flush_rewrite_rules() {
		// Flush URL Manager cache first to ensure fresh slugs
		if ( class_exists( 'Fanfic_URL_Manager' ) ) {
			Fanfic_URL_Manager::get_instance()->flush_cache();
		}

		// Register all rewrite rules before flushing
		if ( class_exists( 'Fanfic_Post_Types' ) ) {
			Fanfic_Post_Types::register();
		}
		if ( class_exists( 'Fanfic_Taxonomies' ) ) {
			Fanfic_Taxonomies::register();
		}
		if ( class_exists( 'Fanfic_URL_Manager' ) ) {
			Fanfic_URL_Manager::get_instance()->register_rewrite_rules();
		}

		flush_rewrite_rules();
	}

	/**
	 * Save user roles step data
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_user_roles_step() {
		error_log( '[Fanfic Wizard] Step 3: Saving user roles' );

		// Save moderators
		$moderators = array();
		if ( isset( $_POST['fanfic_moderators'] ) && is_array( $_POST['fanfic_moderators'] ) ) {
			$moderators = array_map( 'absint', wp_unslash( $_POST['fanfic_moderators'] ) );
		}
		update_option( 'fanfic_wizard_moderators', $moderators );

		// Save admins
		$admins = array();
		if ( isset( $_POST['fanfic_admins'] ) && is_array( $_POST['fanfic_admins'] ) ) {
			$admins = array_map( 'absint', wp_unslash( $_POST['fanfic_admins'] ) );
		}
		update_option( 'fanfic_wizard_admins', $admins );

		error_log( '[Fanfic Wizard] Step 3: Saved moderators=' . wp_json_encode( $moderators ) . ', admins=' . wp_json_encode( $admins ) );
	}

	/**
	 * Save taxonomy terms step data
	 *
	 * Saves taxonomy configuration from step 4 to draft.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_taxonomy_terms_step() {
		error_log( '[Fanfic Wizard Step 4] Starting save_taxonomy_terms_step' );
		error_log( '[Fanfic Wizard Step 4] POST data: ' . wp_json_encode( $_POST ) );

		// Get draft
		$draft = get_option( 'fanfic_wizard_draft', array() );

		// Collect step 4 data
		$step_data = array();

		// Genre and Status are mandatory - use constants from Fanfic_Taxonomies
		$step_data['genre_terms'] = Fanfic_Taxonomies::DEFAULT_GENRES;
		$step_data['status_terms'] = array_values( Fanfic_Taxonomies::DEFAULT_STATUSES );

		// Feature toggles
		$step_data['enable_fandoms'] = isset( $_POST['fanfic_enable_fandom_classification'] ) && '1' === $_POST['fanfic_enable_fandom_classification'];
		$step_data['enable_warnings'] = isset( $_POST['fanfic_enable_warnings'] ) && '1' === $_POST['fanfic_enable_warnings'];
		$step_data['enable_languages'] = isset( $_POST['fanfic_enable_language_classification'] ) && '1' === $_POST['fanfic_enable_language_classification'];
		$step_data['enable_coauthors'] = isset( $_POST['fanfic_enable_coauthors'] ) && '1' === $_POST['fanfic_enable_coauthors'];
		$step_data['enable_dislikes'] = isset( $_POST['fanfic_enable_dislikes'] ) && '1' === $_POST['fanfic_enable_dislikes'];
		$step_data['allow_sexual'] = $step_data['enable_warnings'] && isset( $_POST['fanfic_allow_sexual_content'] ) && '1' === $_POST['fanfic_allow_sexual_content'];
		$step_data['allow_pornographic'] = $step_data['enable_warnings'] && isset( $_POST['fanfic_allow_pornographic_content'] ) && '1' === $_POST['fanfic_allow_pornographic_content'];

		// Pornographic content requires sexual content
		if ( $step_data['allow_pornographic'] ) {
			$step_data['allow_sexual'] = true;
		}

		// Sample stories toggle
		$step_data['create_samples'] = isset( $_POST['fanfic_create_samples'] ) && '1' === $_POST['fanfic_create_samples'];

		error_log( '[Fanfic Wizard Step 4] Collected step data: ' . wp_json_encode( $step_data ) );

		// Save to draft
		$draft['step_4'] = $step_data;
		update_option( 'fanfic_wizard_draft', $draft );

		error_log( '[Fanfic Wizard Step 4] Saved to draft. Full draft: ' . wp_json_encode( $draft ) );
	}

	/**
	 * AJAX handler for completing the wizard
	 *
	 * Commits draft configuration, creates pages, and validates setup.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_complete_wizard() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_wizard_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) ) );
		}

		// Wrap everything in try-catch for debugging
		try {
			error_log( '[Fanfic Wizard Complete] ========== STARTING WIZARD COMPLETION ==========' );
			error_log( sprintf(
				'[Fanfic Wizard Complete] Memory: %dMB used, %dMB peak',
				(int) round( memory_get_usage( true ) / 1048576 ),
				(int) round( memory_get_peak_usage( true ) / 1048576 )
			) );

			// Read draft
			error_log( '[Fanfic Wizard Complete] Reading draft configuration' );
			$draft = get_option( 'fanfic_wizard_draft', array() );
			error_log( '[Fanfic Wizard Complete] Draft contents: ' . wp_json_encode( $draft ) );

			if ( empty( $draft ) ) {
				error_log( '[Fanfic Wizard Complete] WARNING: Draft is empty!' );
			}

			// Commit draft to options
			error_log( '[Fanfic Wizard Complete] ========== COMMITTING DRAFT TO OPTIONS ==========' );
			$this->commit_draft( $draft );

			// Ensure classification tables/data are prepared at completion time.
			// This keeps Step 1 fast and moves heavy work to the final step.
			error_log( '[Fanfic Wizard Complete] ========== PREPARING CLASSIFICATION TABLES/DATA ==========' );
			$classification_result = $this->prepare_classification_for_completion( $draft );
			if ( is_wp_error( $classification_result ) ) {
				error_log( '[Fanfic Wizard Complete] ERROR: Classification preparation failed - ' . $classification_result->get_error_message() );
				wp_send_json_error(
					array(
						'message' => __( 'Failed to prepare classification data.', 'fanfiction-manager' ),
						'details' => array( $classification_result->get_error_message() ),
					)
				);
			}

			// Sync homepage settings AFTER committing options
			error_log( '[Fanfic Wizard Complete] ========== SYNCING HOMEPAGE SETTINGS ==========' );
			if ( class_exists( 'Fanfic_Homepage_State' ) ) {
				Fanfic_Homepage_State::sync_homepage_settings();
			} else {
				error_log( '[Fanfic Wizard Complete] WARNING: Fanfic_Homepage_State class not found!' );
			}

			// Create system pages
			error_log( '[Fanfic Wizard Complete] ========== CREATING SYSTEM PAGES ==========' );
			$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
			error_log( '[Fanfic Wizard Complete] Using base slug: ' . $base_slug );

			$page_result = Fanfic_Templates::create_system_pages( $base_slug );
			error_log( sprintf(
				'[Fanfic Wizard Complete] Page creation result - success: %s',
				$page_result['success'] ? 'YES' : 'NO'
			) );

			if ( ! empty( $page_result['created'] ) ) {
				error_log( '[Fanfic Wizard Complete] Pages created: ' . wp_json_encode( $page_result['created'] ) );
			}
			if ( ! empty( $page_result['failed'] ) ) {
				error_log( '[Fanfic Wizard Complete] Pages failed: ' . wp_json_encode( $page_result['failed'] ) );
			}

			// Check if page creation was successful
			if ( ! $page_result['success'] ) {
				$error_details = array();
				if ( ! empty( $page_result['failed'] ) ) {
					foreach ( $page_result['failed'] as $failed_page ) {
						$error_details[] = sprintf(
							/* translators: 1: page title, 2: error message */
							__( '%1$s: %2$s', 'fanfiction-manager' ),
							$failed_page['title'],
							$failed_page['message']
						);
					}
				}

				error_log( '[Fanfic Wizard Complete] ERROR: Page creation failed - ' . wp_json_encode( $error_details ) );

				wp_send_json_error( array(
					'message' => $page_result['message'],
					'details' => $error_details,
				) );
			}

			// Sync homepage settings AGAIN after pages are created
			error_log( '[Fanfic Wizard Complete] ========== SYNCING HOMEPAGE SETTINGS (POST-PAGES) ==========' );
			if ( class_exists( 'Fanfic_Homepage_State' ) ) {
				Fanfic_Homepage_State::sync_homepage_settings();
			}

			// Create taxonomy terms
			error_log( '[Fanfic Wizard Complete] ========== CREATING TAXONOMY TERMS ==========' );
			$this->create_taxonomy_terms();

			// Assign user roles
			error_log( '[Fanfic Wizard Complete] ========== ASSIGNING USER ROLES ==========' );
			$this->assign_user_roles();

			// Create sample stories if requested (saved in step 4 draft)
			$step_4 = isset( $draft['step_4'] ) ? $draft['step_4'] : array();
			if ( ! empty( $step_4['create_samples'] ) ) {
				error_log( '[Fanfic Wizard Complete] ========== CREATING SAMPLE STORIES ==========' );
				$this->create_sample_stories();
			} else {
				error_log( '[Fanfic Wizard Complete] Skipping sample stories (not requested)' );
			}

			// Flush rewrite rules
			error_log( '[Fanfic Wizard Complete] ========== FLUSHING REWRITE RULES ==========' );
			$this->flush_rewrite_rules();

			// ========== VERIFICATION GATES ==========
			error_log( '[Fanfic Wizard Complete] ========== RUNNING VERIFICATION GATES ==========' );

			$gates_passed = true;
			$gate_errors = array();

			// Gate 1: Base slug persisted correctly
			error_log( '[Fanfic Wizard Complete] Gate 1: Verifying base slug persistence' );

			// Check if user chose "no base slug" mode in step 2 (use_base_slug is set in URL settings step)
			$use_base_slug = isset( $draft['step_2']['use_base_slug'] ) ? (int) $draft['step_2']['use_base_slug'] : 1;

			if ( 0 === $use_base_slug ) {
				// No base slug mode - expect empty string
				$expected_base_slug = '';
			} else {
				// Base slug mode - use the value from step 2 or default to 'fanfiction'
				$expected_base_slug = array_key_exists( 'base_slug', $draft['step_2'] ) ? $draft['step_2']['base_slug'] : 'fanfiction';
			}

			$actual_base_slug = get_option( 'fanfic_base_slug', '' );
			error_log( sprintf(
				'[Fanfic Wizard Complete] Gate 1: Expected=%s, Actual=%s',
				$expected_base_slug,
				$actual_base_slug
			) );
			if ( $expected_base_slug !== $actual_base_slug ) {
				$gates_passed = false;
				$gate_errors[] = sprintf(
					__( 'Base slug mismatch: expected "%s", got "%s"', 'fanfiction-manager' ),
					$expected_base_slug,
					$actual_base_slug
				);
				error_log( '[Fanfic Wizard Complete] Gate 1: FAILED' );
			} else {
				error_log( '[Fanfic Wizard Complete] Gate 1: PASSED' );
			}

			// Gate 2: Homepage settings match expected
			error_log( '[Fanfic Wizard Complete] Gate 2: Verifying homepage settings' );
			if ( class_exists( 'Fanfic_Homepage_State' ) ) {
				$is_in_sync = Fanfic_Homepage_State::is_wp_front_page_in_sync();
				$state = Fanfic_Homepage_State::get_current_state();
				$target = Fanfic_Homepage_State::resolve_wp_front_page_target( $state );

				error_log( '[Fanfic Wizard Complete] Gate 2: Current state: ' . wp_json_encode( $state ) );
				error_log( '[Fanfic Wizard Complete] Gate 2: Expected target: ' . wp_json_encode( $target ) );
				error_log( sprintf(
					'[Fanfic Wizard Complete] Gate 2: WP show_on_front=%s, page_on_front=%d',
					get_option( 'show_on_front' ),
					(int) get_option( 'page_on_front' )
				) );
				error_log( sprintf(
					'[Fanfic Wizard Complete] Gate 2: In sync? %s',
					$is_in_sync ? 'YES' : 'NO'
				) );

				if ( ! $is_in_sync && $state['use_base_slug'] == 0 ) {
					$gates_passed = false;
					$gate_errors[] = __( 'Homepage settings do not match expected configuration', 'fanfiction-manager' );
					error_log( '[Fanfic Wizard Complete] Gate 2: FAILED (settings out of sync)' );
				} else {
					error_log( '[Fanfic Wizard Complete] Gate 2: PASSED' );
				}
			} else {
				error_log( '[Fanfic Wizard Complete] Gate 2: SKIPPED (Homepage State class not available)' );
			}

			// Gate 3: All required pages exist
			error_log( '[Fanfic Wizard Complete] Gate 3: Verifying all pages exist' );
			if ( ! $this->all_pages_exist() ) {
				$gates_passed = false;
				$gate_errors[] = __( 'Some required pages are missing or not published', 'fanfiction-manager' );
				error_log( '[Fanfic Wizard Complete] Gate 3: FAILED (missing pages)' );

				// Log detailed page status
				$page_ids = get_option( 'fanfic_system_page_ids', array() );
				foreach ( $page_ids as $key => $id ) {
					$page = get_post( $id );
					if ( $page ) {
						error_log( sprintf(
							'[Fanfic Wizard Complete] Gate 3: Page %s (ID %d) status: %s',
							$key,
							$id,
							$page->post_status
						) );
					} else {
						error_log( sprintf(
							'[Fanfic Wizard Complete] Gate 3: Page %s (ID %d) NOT FOUND',
							$key,
							$id
						) );
					}
				}
			} else {
				error_log( '[Fanfic Wizard Complete] Gate 3: PASSED' );
			}

			// Check if all gates passed
			if ( ! $gates_passed ) {
				error_log( '[Fanfic Wizard Complete] ========== VERIFICATION FAILED ==========' );
				error_log( '[Fanfic Wizard Complete] Gate errors: ' . wp_json_encode( $gate_errors ) );

				wp_send_json_error( array(
					'message' => __( 'Setup verification failed. Please review the configuration.', 'fanfiction-manager' ),
					'details' => $gate_errors,
				) );
			}

			error_log( '[Fanfic Wizard Complete] ========== ALL GATES PASSED ==========' );

			// Mark wizard as completed
			error_log( '[Fanfic Wizard Complete] Marking wizard as completed' );
			update_option( 'fanfic_wizard_completed', true );
			delete_option( 'fanfic_show_wizard' );

			// Clean up temporary wizard data
			delete_option( 'fanfic_wizard_moderators' );
			delete_option( 'fanfic_wizard_admins' );

			// Delete draft on success
			error_log( '[Fanfic Wizard Complete] Deleting wizard draft' );
			delete_option( 'fanfic_wizard_draft' );

			// Log completion timestamp
			$completion_time = current_time( 'mysql' );
			error_log( '[Fanfic Wizard Complete] Completion timestamp: ' . $completion_time );
			update_option( 'fanfic_wizard_completed_at', $completion_time );

			error_log( '[Fanfic Wizard Complete] ========== WIZARD COMPLETION SUCCESS ==========' );
			error_log( sprintf(
				'[Fanfic Wizard Complete] Final memory: %dMB used, %dMB peak',
				(int) round( memory_get_usage( true ) / 1048576 ),
				(int) round( memory_get_peak_usage( true ) / 1048576 )
			) );

			wp_send_json_success( array(
				'message'      => __( 'Setup completed successfully! Redirecting...', 'fanfiction-manager' ),
				'redirect_url' => admin_url( 'admin.php?page=fanfiction-settings&tab=general' ),
			) );

		} catch ( Exception $e ) {
			error_log( '[Fanfic Wizard Complete] ========== EXCEPTION CAUGHT ==========' );
			error_log( '[Fanfic Wizard Complete] ERROR: ' . $e->getMessage() );
			error_log( '[Fanfic Wizard Complete] FILE: ' . $e->getFile() . ' on line ' . $e->getLine() );
			error_log( '[Fanfic Wizard Complete] TRACE: ' . $e->getTraceAsString() );

			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Wizard completion failed: %s', 'fanfiction-manager' ),
					$e->getMessage()
				),
				'details' => array(
					__( 'Check the WordPress debug log for more details.', 'fanfiction-manager' ),
					$e->getFile() . ' on line ' . $e->getLine(),
				),
			) );
		}
	}

	/**
	 * Create classification tables and seed enabled datasets during wizard completion.
	 *
	 * @since 1.0.0
	 * @param array $draft Wizard draft configuration.
	 * @return true|WP_Error
	 */
	private function prepare_classification_for_completion( $draft ) {
		if ( ! class_exists( 'Fanfic_Database_Setup' ) ) {
			return new WP_Error( 'fanfic_missing_db_setup', __( 'Database setup class is unavailable.', 'fanfiction-manager' ) );
		}

		$step_4 = isset( $draft['step_4'] ) && is_array( $draft['step_4'] ) ? $draft['step_4'] : array();

		$enable_fandoms = array_key_exists( 'enable_fandoms', $step_4 )
			? (bool) $step_4['enable_fandoms']
			: ( class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_fandom_classification', true ) : true );
		$enable_warnings = array_key_exists( 'enable_warnings', $step_4 )
			? (bool) $step_4['enable_warnings']
			: ( class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_warnings', true ) : true );
		$enable_languages = array_key_exists( 'enable_languages', $step_4 )
			? (bool) $step_4['enable_languages']
			: ( class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_language_classification', true ) : true );

		error_log(
			'[Fanfic Wizard Complete] Classification flags: ' . wp_json_encode(
				array(
					'enable_fandoms'   => $enable_fandoms,
					'enable_warnings'  => $enable_warnings,
					'enable_languages' => $enable_languages,
				)
			)
		);

		if ( ! Fanfic_Database_Setup::classification_tables_exist() ) {
			error_log( '[Fanfic Wizard Complete] Classification tables missing, creating now...' );
			$result = Fanfic_Database_Setup::create_classification_tables();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			error_log( '[Fanfic Wizard Complete] Classification tables created successfully.' );
		} else {
			error_log( '[Fanfic Wizard Complete] Classification tables already exist.' );
		}

		// Wizard is completing now; allow normal DB bootstrap behavior after this point.
		delete_transient( 'fanfic_skip_classification' );

		if ( ! Fanfic_Database_Setup::classification_tables_exist() ) {
			return new WP_Error( 'fanfic_classification_tables_missing', __( 'Classification tables are still missing after creation attempt.', 'fanfiction-manager' ) );
		}

		if ( $enable_fandoms && class_exists( 'Fanfic_Fandoms' ) ) {
			error_log( '[Fanfic Wizard Complete] Seeding fandoms (enabled).' );
			Fanfic_Fandoms::maybe_seed_fandoms();
		} else {
			error_log( '[Fanfic Wizard Complete] Skipping fandom seed (disabled).' );
		}

		if ( $enable_warnings && class_exists( 'Fanfic_Warnings' ) ) {
			error_log( '[Fanfic Wizard Complete] Seeding warnings (enabled).' );
			Fanfic_Warnings::maybe_seed_warnings();
		} else {
			error_log( '[Fanfic Wizard Complete] Skipping warning seed (disabled).' );
		}

		if ( $enable_languages && class_exists( 'Fanfic_Languages' ) ) {
			error_log( '[Fanfic Wizard Complete] Seeding languages (enabled).' );
			Fanfic_Languages::maybe_seed_languages();
		} else {
			error_log( '[Fanfic Wizard Complete] Skipping language seed (disabled).' );
		}

		return true;
	}

	/**
	 * Commit draft configuration to WordPress options
	 *
	 * Writes all draft data atomically to their respective options.
	 *
	 * @since 1.0.0
	 * @param array $draft Draft data from fanfic_wizard_draft option.
	 * @return void
	 */
	private function commit_draft( $draft ) {
		error_log( '[Fanfic Wizard Commit] Starting draft commit' );

		// Commit Step 1: Homepage settings
		if ( isset( $draft['step_1'] ) ) {
			error_log( '[Fanfic Wizard Commit] Committing Step 1 (Homepage Settings)' );
			$step_1 = $draft['step_1'];

			if ( isset( $step_1['main_page_mode'] ) ) {
				$old = get_option( 'fanfic_main_page_mode', '' );
				update_option( 'fanfic_main_page_mode', $step_1['main_page_mode'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_main_page_mode: %s → %s',
					$old,
					$step_1['main_page_mode']
				) );
			}

			if ( isset( $step_1['homepage_source'] ) ) {
				$old = get_option( 'fanfic_homepage_source', '' );
				update_option( 'fanfic_homepage_source', $step_1['homepage_source'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_homepage_source: %s → %s',
					$old,
					$step_1['homepage_source']
				) );
			}

			if ( isset( $step_1['homepage_source_id'] ) ) {
				$old = get_option( 'fanfic_homepage_source_id', '' );
				update_option( 'fanfic_homepage_source_id', $step_1['homepage_source_id'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_homepage_source_id: %s → %s',
					$old,
					$step_1['homepage_source_id']
				) );
			}
		}

		// Commit Step 2: URL settings
		if ( isset( $draft['step_2'] ) ) {
			error_log( '[Fanfic Wizard Commit] Committing Step 2 (URL Settings)' );
			$step_2 = $draft['step_2'];

			// Save use_base_slug setting (set in step 2 URL settings)
			if ( isset( $step_2['use_base_slug'] ) ) {
				$old = get_option( 'fanfic_use_base_slug', '' );
				update_option( 'fanfic_use_base_slug', $step_2['use_base_slug'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_use_base_slug: %s → %s',
					$old,
					$step_2['use_base_slug']
				) );
			}

			// Check if user chose "no base slug" mode
			$use_base_slug = isset( $step_2['use_base_slug'] ) ? (int) $step_2['use_base_slug'] : 1;

			if ( 0 === $use_base_slug ) {
				// No base slug mode - explicitly set base_slug to empty string
				$old = get_option( 'fanfic_base_slug', '' );
				update_option( 'fanfic_base_slug', '' );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_base_slug: %s → (empty - no base slug mode)',
					$old
				) );
			} elseif ( array_key_exists( 'base_slug', $step_2 ) ) {
				// Base slug mode - use the value from step 2
				$old = get_option( 'fanfic_base_slug', '' );
				update_option( 'fanfic_base_slug', $step_2['base_slug'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_base_slug: %s → %s',
					$old,
					$step_2['base_slug']
				) );
			}

			if ( isset( $step_2['story_path'] ) ) {
				$old = get_option( 'fanfic_story_path', '' );
				update_option( 'fanfic_story_path', $step_2['story_path'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_story_path: %s → %s',
					$old,
					$step_2['story_path']
				) );
			}

			if ( isset( $step_2['chapter_slugs'] ) ) {
				$old = get_option( 'fanfic_chapter_slugs', array() );
				update_option( 'fanfic_chapter_slugs', $step_2['chapter_slugs'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_chapter_slugs: %s → %s',
					wp_json_encode( $old ),
					wp_json_encode( $step_2['chapter_slugs'] )
				) );
			}

			if ( isset( $step_2['system_page_slugs'] ) ) {
				$old = get_option( 'fanfic_system_page_slugs', array() );
				$new_system_page_slugs = is_array( $step_2['system_page_slugs'] ) ? $step_2['system_page_slugs'] : array();
				if ( isset( $step_2['story_path'] ) ) {
					$new_system_page_slugs['story_path'] = $step_2['story_path'];
				}
				update_option( 'fanfic_system_page_slugs', $new_system_page_slugs );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_system_page_slugs: %s → %s',
					wp_json_encode( $old ),
					wp_json_encode( $new_system_page_slugs )
				) );
			} elseif ( isset( $step_2['story_path'] ) ) {
				$existing_system_page_slugs = get_option( 'fanfic_system_page_slugs', array() );
				if ( ! is_array( $existing_system_page_slugs ) ) {
					$existing_system_page_slugs = array();
				}
				$existing_system_page_slugs['story_path'] = $step_2['story_path'];
				update_option( 'fanfic_system_page_slugs', $existing_system_page_slugs );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_system_page_slugs[story_path] synced to %s',
					$step_2['story_path']
				) );
			}

			if ( isset( $step_2['dashboard_slug'] ) ) {
				$old = get_option( 'fanfic_dashboard_slug', '' );
				update_option( 'fanfic_dashboard_slug', $step_2['dashboard_slug'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_dashboard_slug: %s → %s',
					$old,
					$step_2['dashboard_slug']
				) );
			}

			if ( isset( $step_2['members_slug'] ) ) {
				$old = get_option( 'fanfic_members_slug', '' );
				update_option( 'fanfic_members_slug', $step_2['members_slug'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] fanfic_members_slug: %s → %s',
					$old,
					$step_2['members_slug']
				) );
			}
		}

		// Commit Step 4: Taxonomy settings
		if ( isset( $draft['step_4'] ) ) {
			error_log( '[Fanfic Wizard Commit] Committing Step 4 (Taxonomy Settings)' );
			$step_4 = $draft['step_4'];

			// Store taxonomy terms for later creation
			if ( isset( $step_4['genre_terms'] ) ) {
				update_option( 'fanfic_wizard_genre_terms', $step_4['genre_terms'] );
				error_log( '[Fanfic Wizard Commit] Stored genre terms: ' . wp_json_encode( $step_4['genre_terms'] ) );
			}

			if ( isset( $step_4['status_terms'] ) ) {
				update_option( 'fanfic_wizard_status_terms', $step_4['status_terms'] );
				error_log( '[Fanfic Wizard Commit] Stored status terms: ' . wp_json_encode( $step_4['status_terms'] ) );
			}

			// Feature toggles
			if ( isset( $step_4['enable_fandoms'] ) ) {
				Fanfic_Settings::update_setting( 'enable_fandom_classification', $step_4['enable_fandoms'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] enable_fandom_classification → %s',
					$step_4['enable_fandoms'] ? 'true' : 'false'
				) );
			}

			if ( isset( $step_4['enable_warnings'] ) ) {
				Fanfic_Settings::update_setting( 'enable_warnings', $step_4['enable_warnings'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] enable_warnings → %s',
					$step_4['enable_warnings'] ? 'true' : 'false'
				) );
			}

			if ( isset( $step_4['enable_languages'] ) ) {
				Fanfic_Settings::update_setting( 'enable_language_classification', $step_4['enable_languages'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] enable_language_classification → %s',
					$step_4['enable_languages'] ? 'true' : 'false'
				) );
			}

			if ( isset( $step_4['enable_coauthors'] ) ) {
				Fanfic_Settings::update_setting( 'enable_coauthors', $step_4['enable_coauthors'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] enable_coauthors → %s',
					$step_4['enable_coauthors'] ? 'true' : 'false'
				) );
			}

			if ( isset( $step_4['enable_dislikes'] ) ) {
				Fanfic_Settings::update_setting( 'enable_dislikes', $step_4['enable_dislikes'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] enable_dislikes → %s',
					$step_4['enable_dislikes'] ? 'true' : 'false'
				) );
			}

			if ( isset( $step_4['allow_sexual'] ) ) {
				Fanfic_Settings::update_setting( 'allow_sexual_content', $step_4['allow_sexual'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] allow_sexual_content → %s',
					$step_4['allow_sexual'] ? 'true' : 'false'
				) );
			}

			if ( isset( $step_4['allow_pornographic'] ) ) {
				Fanfic_Settings::update_setting( 'allow_pornographic_content', $step_4['allow_pornographic'] );
				error_log( sprintf(
					'[Fanfic Wizard Commit] allow_pornographic_content → %s',
					$step_4['allow_pornographic'] ? 'true' : 'false'
				) );
			}
		}

		error_log( '[Fanfic Wizard Commit] Draft commit completed' );
	}

	/**
	 * Create taxonomy terms based on wizard selections
	 *
	 * Uses committed draft data to create taxonomy terms.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function create_taxonomy_terms() {
		// Get selected terms (from committed draft)
		$genre_terms = get_option( 'fanfic_wizard_genre_terms', array() );
		$status_terms = get_option( 'fanfic_wizard_status_terms', array() );

		error_log( '[Fanfic Wizard Taxonomy] Creating genre terms: ' . wp_json_encode( $genre_terms ) );
		error_log( '[Fanfic Wizard Taxonomy] Creating status terms: ' . wp_json_encode( $status_terms ) );

		// Create genre terms
		if ( ! empty( $genre_terms ) && is_array( $genre_terms ) ) {
			foreach ( $genre_terms as $term ) {
				if ( ! empty( $term ) ) {
					// Check if term already exists
					$existing_term = term_exists( $term, 'fanfiction_genre' );
					if ( ! $existing_term ) {
						$insert_args = array();
						if ( class_exists( 'Fanfic_Taxonomies' ) && method_exists( 'Fanfic_Taxonomies', 'get_default_genre_description' ) ) {
							$description = Fanfic_Taxonomies::get_default_genre_description( $term );
							if ( '' !== $description ) {
								$insert_args['description'] = $description;
							}
						}

						$result = wp_insert_term( $term, 'fanfiction_genre', $insert_args );
						if ( is_wp_error( $result ) ) {
							error_log( '[Fanfic Wizard Taxonomy] Failed to create genre term "' . $term . '": ' . $result->get_error_message() );
						} else {
							error_log( '[Fanfic Wizard Taxonomy] Created genre term "' . $term . '" (ID: ' . $result['term_id'] . ')' );
						}
					} else {
						error_log( '[Fanfic Wizard Taxonomy] Genre term "' . $term . '" already exists' );
					}
				}
			}
		}

		// Create status terms
		if ( ! empty( $status_terms ) && is_array( $status_terms ) ) {
			foreach ( $status_terms as $term ) {
				if ( ! empty( $term ) ) {
					// Check if term already exists
					$existing_term = term_exists( $term, 'fanfiction_status' );
					if ( ! $existing_term ) {
						$result = wp_insert_term( $term, 'fanfiction_status' );
						if ( is_wp_error( $result ) ) {
							error_log( '[Fanfic Wizard Taxonomy] Failed to create status term "' . $term . '": ' . $result->get_error_message() );
						} else {
							error_log( '[Fanfic Wizard Taxonomy] Created status term "' . $term . '" (ID: ' . $result['term_id'] . ')' );
						}
					} else {
						error_log( '[Fanfic Wizard Taxonomy] Status term "' . $term . '" already exists' );
					}
				}
			}
		}

		// Clean up temporary wizard data
		delete_option( 'fanfic_wizard_genre_terms' );
		delete_option( 'fanfic_wizard_status_terms' );

		error_log( '[Fanfic Wizard Taxonomy] Taxonomy term creation completed' );
	}

	/**
	 * Assign user roles based on wizard selections
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function assign_user_roles() {
		// Get selected users
		$moderators = get_option( 'fanfic_wizard_moderators', array() );
		$admins = get_option( 'fanfic_wizard_admins', array() );

		error_log( '[Fanfic Wizard Roles] Moderators to assign: ' . wp_json_encode( $moderators ) );
		error_log( '[Fanfic Wizard Roles] Admins to assign: ' . wp_json_encode( $admins ) );

		// Assign moderator role
		foreach ( $moderators as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && ! in_array( $user_id, $admins, true ) ) { // Don't assign moderator if also admin
				$user->add_role( 'fanfiction_moderator' );
				error_log( sprintf(
					'[Fanfic Wizard Roles] Added moderator role to user %d (%s)',
					$user_id,
					$user->user_login
				) );
			} elseif ( ! $user ) {
				error_log( '[Fanfic Wizard Roles] User ID ' . $user_id . ' not found' );
			} elseif ( in_array( $user_id, $admins, true ) ) {
				error_log( '[Fanfic Wizard Roles] Skipping moderator role for user ' . $user_id . ' (is admin)' );
			}
		}

		// Assign admin role (also gets moderator capabilities)
		foreach ( $admins as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				// Add fanfiction capabilities to their existing role
				$user->add_cap( 'manage_fanfiction' );
				$user->add_cap( 'moderate_fanfiction' );
				error_log( sprintf(
					'[Fanfic Wizard Roles] Added admin capabilities to user %d (%s)',
					$user_id,
					$user->user_login
				) );
			} else {
				error_log( '[Fanfic Wizard Roles] User ID ' . $user_id . ' not found' );
			}
		}

		error_log( '[Fanfic Wizard Roles] User role assignment completed' );
	}

	/**
	 * Create sample stories for testing
	 *
	 * Creates 3 sample stories with chapters, including a linked translation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function get_sample_story_tag_pool() {
		return array(
			'Lorem Ipsum',
			'Dolor Sit Amet',
			'Consectetur Adipiscing',
			'Elit Sed Do',
			'Eiusmod Tempor',
			'Incididunt Ut Labore',
			'Et Dolore Magna',
			'Aliqua Ut Enim',
			'Ad Minim Veniam',
			'Quis Nostrud Exercitation',
			'Ullamco Laboris',
			'Nisi Ut Aliquip',
			'Ex Ea Commodo',
			'Consequat Duis',
			'Aute Irure Dolor',
		);
	}

	/**
	 * Build randomized visible/invisible tags for sample stories.
	 *
	 * Selects 1-5 visible and 1-5 invisible tags without repetition.
	 *
	 * @since 1.0.0
	 * @return array{visible: string[], invisible: string[]}
	 */
	private function build_random_sample_story_tags() {
		$pool = $this->get_sample_story_tag_pool();
		$pool = array_values( array_unique( array_filter( array_map( 'trim', (array) $pool ) ) ) );

		if ( empty( $pool ) ) {
			return array(
				'visible'   => array(),
				'invisible' => array(),
			);
		}

		shuffle( $pool );
		$visible_count = rand( 1, min( 5, count( $pool ) ) );
		$visible_tags  = array_slice( $pool, 0, $visible_count );
		$remaining     = array_slice( $pool, $visible_count );

		if ( empty( $remaining ) ) {
			$remaining = $pool;
		}

		shuffle( $remaining );
		$invisible_count = rand( 1, min( 5, count( $remaining ) ) );
		$invisible_tags  = array_slice( $remaining, 0, $invisible_count );

		return array(
			'visible'   => array_values( $visible_tags ),
			'invisible' => array_values( $invisible_tags ),
		);
	}

	/**
	 * Save sample story tags and trigger tag sync hooks.
	 *
	 * @since 1.0.0
	 * @param int      $story_id        Story ID.
	 * @param string[] $visible_tags    Visible tags.
	 * @param string[] $invisible_tags  Invisible tags.
	 * @return void
	 */
	private function apply_sample_story_tags( $story_id, $visible_tags, $invisible_tags ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}

		if ( function_exists( 'fanfic_save_all_tags' ) ) {
			fanfic_save_all_tags( $story_id, (array) $visible_tags, (array) $invisible_tags );
			return;
		}

		update_post_meta( $story_id, '_fanfic_visible_tags', (array) $visible_tags );
		update_post_meta( $story_id, '_fanfic_invisible_tags', (array) $invisible_tags );
		do_action( 'fanfic_tags_updated', $story_id );
	}

	private function create_sample_stories() {
		error_log( '[Fanfic Wizard Samples] Starting sample story creation' );
		$current_user_id = get_current_user_id();
		error_log( '[Fanfic Wizard Samples] Current user ID: ' . $current_user_id );

		// --- Get available terms/items ---

		// Genres (WordPress taxonomy)
		$genre_terms = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );
		if ( empty( $genre_terms ) || is_wp_error( $genre_terms ) ) {
			error_log( '[Fanfic Wizard Samples] No genre terms available, aborting sample creation.' );
			return;
		}

		// Statuses (WordPress taxonomy) - Filter to only 'ongoing', 'abandoned', 'hiatus'
		$all_status_terms = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );
		$filtered_status_terms = array();
		if ( ! empty( $all_status_terms ) && ! is_wp_error( $all_status_terms ) ) {
			foreach ( $all_status_terms as $term ) {
				if ( in_array( $term->slug, array( 'ongoing', 'abandoned', 'on-hiatus' ), true ) ) {
					$filtered_status_terms[] = $term;
				}
			}
		}
		if ( empty( $filtered_status_terms ) ) {
			error_log( '[Fanfic Wizard Samples] No filtered status terms (ongoing, abandoned, hiatus) available, aborting sample creation.' );
			return;
		}

		// Warnings (Custom table)
		// Check if Fanfic_Warnings class exists and get_all method is callable
		$warnings_available = array();
		if ( class_exists( 'Fanfic_Warnings' ) && method_exists( 'Fanfic_Warnings', 'get_all' ) ) {
			$warnings_all = Fanfic_Warnings::get_all( true ); // Get all enabled warnings.
			$warnings_available = array_values(
				array_filter(
					(array) $warnings_all,
					function( $warning ) {
						$is_sexual = ! empty( $warning['is_sexual'] );
						$is_pornographic = ! empty( $warning['is_pornographic'] );
						return ! $is_sexual && ! $is_pornographic;
					}
				)
			);
		}
		if ( empty( $warnings_available ) ) {
			error_log( '[Fanfic Wizard Samples] No non-sexual/non-pornographic warnings available.' );
		}

		// Languages (Custom table)
		// Check if Fanfic_Languages class exists and get_active_languages method is callable
		$languages_available = array();
		$esperanto_language_id = null; // Story 2 preferred language.
		$latin_language_id = null; // Story 1 preferred language.
		$english_language_id = null; // Story 3 preferred language.
		$portuguese_language_id = null; // Fallback if English is unavailable.
		if ( class_exists( 'Fanfic_Languages' ) && method_exists( 'Fanfic_Languages', 'get_active_languages' ) ) {
			$languages_available = Fanfic_Languages::get_active_languages();
			// Find Esperanto and Latin IDs.
			foreach ( $languages_available as $lang ) {
				if ( ! isset( $lang['slug'], $lang['id'] ) ) {
					continue;
				}

				if ( 'eo' === $lang['slug'] ) {
					$esperanto_language_id = $lang['id'];
				}

				if ( 'la' === $lang['slug'] ) {
					$latin_language_id = $lang['id'];
				}

				if ( 'en' === $lang['slug'] ) {
					$english_language_id = $lang['id'];
				}

				if ( in_array( $lang['slug'], array( 'pt', 'pt-pt', 'pt-br' ), true ) && is_null( $portuguese_language_id ) ) {
					$portuguese_language_id = $lang['id'];
				}
			}
		}
		if ( empty( $languages_available ) ) {
			error_log( '[Fanfic Wizard Samples] No languages available.' );
		}

		// Fandoms (Custom table)
		// Check if Fanfic_Fandoms class exists and get_all_active method is callable
		$fandoms_available = array();
		if ( class_exists( 'Fanfic_Fandoms' ) && method_exists( 'Fanfic_Fandoms', 'get_all_active' ) ) {
			$fandoms_available = Fanfic_Fandoms::get_all_active();
		}
		if ( empty( $fandoms_available ) ) {
			error_log( '[Fanfic Wizard Samples] No fandoms available.' );
		}

		// --- Prepare data for stories ---

		// Random statuses
		$random_status_1 = $filtered_status_terms[ array_rand( $filtered_status_terms ) ];
		$random_status_2 = $filtered_status_terms[ array_rand( $filtered_status_terms ) ];

		// Random genres (always 2 if possible)
		$random_genres_1 = array();
		$random_genres_2 = array();
		if ( count( $genre_terms ) >= 2 ) {
			shuffle( $genre_terms );
			$random_genres_1 = array_slice( $genre_terms, 0, 2 );
			shuffle( $genre_terms );
			$random_genres_2 = array_slice( $genre_terms, 0, 2 );
		} elseif ( count( $genre_terms ) === 1 ) {
			$random_genres_1 = array( $genre_terms[0] );
			$random_genres_2 = array( $genre_terms[0] );
		}

		// Random warnings (1 or 2 if available)
		$random_warnings_1 = array();
		$random_warnings_2 = array();
		if ( ! empty( $warnings_available ) ) {
			shuffle( $warnings_available );
			$random_warnings_1 = array_slice( $warnings_available, 0, rand( 1, min( 2, count( $warnings_available ) ) ) );
			shuffle( $warnings_available );
			$random_warnings_2 = array_slice( $warnings_available, 0, rand( 1, min( 2, count( $warnings_available ) ) ) );
		}

		// Story language selection with fallback.
		$random_language_1_id = null;
		// Story 2 will be Esperanto if available, otherwise random or null.
		$random_language_2_id = $esperanto_language_id;
		// Story 3 should be English if available.
		$random_language_3_id = $english_language_id;

		if ( ! empty( $languages_available ) ) {
			// Story 1 should be Latin when available.
			$random_language_1_id = $latin_language_id;
			if ( is_null( $random_language_1_id ) ) {
				$random_language_1_id = $languages_available[ array_rand( $languages_available ) ]['id'];
			}

			// If Esperanto is unavailable, fallback to random for story 2.
			if ( is_null( $esperanto_language_id ) ) {
				$random_language_2_id = $languages_available[ array_rand( $languages_available ) ]['id'];
			}

			// Story 3 fallback order: English -> Portuguese -> random.
			if ( is_null( $random_language_3_id ) ) {
				$random_language_3_id = $portuguese_language_id;
			}
			if ( is_null( $random_language_3_id ) ) {
				$random_language_3_id = $languages_available[ array_rand( $languages_available ) ]['id'];
			}
		}

		// Random fandoms (1 if available)
		$random_fandom_1_id = null;
		$random_fandom_2_id = null;
		if ( ! empty( $fandoms_available ) ) {
			$random_fandom_1_id = $fandoms_available[ array_rand( $fandoms_available ) ]['id'];
			$random_fandom_2_id = $fandoms_available[ array_rand( $fandoms_available ) ]['id'];
		}

		// Random tag sets for sample stories.
		$story1_tag_sets = $this->build_random_sample_story_tags();
		$story2_tag_sets = $this->build_random_sample_story_tags(); // Story 3 inherits this exact set.

		// --- Descriptions ---
		// Lorem ipsum content (keep for Story 1)
		$lorem_intro = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.\n\nDuis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
		$lorem_chapter = "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.\n\nNemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.\n\nUt enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?";
		$story2_summary = "Ĉi tiu rakonto rakontas mian personan sagao­n pri mia koramiko, kiu dormas kiel anĝelo… sed ronkas kiel furioza besto. Nokte, dum mi provas trovi pacon kaj silenton, li transformiĝas en vivantan motoron, miksitan kun urso kaj rompita traktor­o. Ĉiu nokto estas nova aventuro: ĉu mi sukcesos dormi, aŭ ĉu mi denove migrados al la sofo kun kuseno kaj rezignacio? Amo estas forta, sed liaj ronkoj estas pli fortaj.";
		$story2_prologue_content = "En la komenco, estis silento. Bela, fragila silento. Tiu speco de silento, kiu promesas ripozon, revojn, kaj la dolĉan iluzion de ok horoj da dormo. Kaj tiam… li enspiris.\n\nTio ne estis ordinara ronko. Ne. Tio estis la sono de praa estaĵo, vekiĝanta el vintra dormo. La lito tremis. La aero vibris. Ie, najbara hundo ekdubis sian ekziston. Kaj mi, senkulpa animo kun kovrilo ĝis la nazo, komprenis ke mia nokto estis perdita.\n\nĈi tiu estas ne historio pri milito, sed pri bataloj. Ne pri drakoj, sed pri ronkado. Ne pri herooj kun glavoj, sed pri mi, armita nur per orelŝtopiloj, kusenoj, kaj senfina amo. Ĉar kiam oni amas iun, oni akceptas liajn mankojn… eĉ kiam tiuj mankoj sonas kiel demon-urso kun spirproblemoj.\n\nJen komenciĝas mia sagao. La sagao de amo, laceco, kaj noktoj sen kompatemo.";
		$story2_chapter1_content = "Mi amas mian koramikon. Vere. Dumtage li estas ĉarma, dolĉa, preskaŭ silenta. Sed nokte… nokte li fariĝas porko. Ne metafore. Akustike.\n\nLa momento kiam li endormiĝas estas trompa. Li spiradas trankvile, kvazaŭ li estus sendanĝera estaĵo. Mi pensas: “Jen, ĉi-nokte mi dormos.” Naiva eraro. Post kvin minutoj venas la unua ronko. Profunda. Malseka. Decidema. La speco de sono, kiun vi atendus en farmo je la tria matene.\n\nMi provas ignori ĝin. Mi turniĝas. Mi metas kusenon super la kapon. Nenio helpas. La ronkoj venas en ondoj: foje rapidaj, foje longaj, foje kun paŭzo tiel longa ke mi pensas li mortis… ĝis li subite eksplodas per ronko kiu preskaŭ rompas la liton.\n\nMi rigardas lin dormantan, tute feliĉan, dum mi kalkulas minutojn ĝis la sunleviĝo. Amo estas pacienco, oni diras. Sed neniu avertis min, ke amo ankaŭ estas dormi apud porko kun supernatura pulmo-kapacito.\n\nKaj tiel finiĝas mia unua nokta batalo. Spoiler: ĝi ne estos la lasta.";
		$story3_title = 'The Saga of My Snoring Partner';
		$story3_summary = "This story tells my personal saga about my boyfriend, who sleeps like an angel... but snores like a furious beast. At night, while I try to find peace and silence, he turns into a living engine mixed with a bear and a broken tractor. Every night is a new adventure: will I manage to sleep, or will I migrate to the couch again with a pillow and resignation? Love is strong, but his snores are stronger.";
		$story3_prologue_content = "In the beginning, there was silence. Beautiful, fragile silence. The kind of silence that promises rest, dreams, and the sweet illusion of eight hours of sleep. And then... he inhaled.\n\nThat was not an ordinary snore. No. It was the sound of a primordial creature waking from hibernation. The bed trembled. The air vibrated. Somewhere, a neighbor's dog questioned its own existence. And I, an innocent soul with the blanket up to my nose, understood that my night was lost.\n\nThis is not a story about war, but about battles. Not about dragons, but about snoring. Not about heroes with swords, but about me, armed only with earplugs, pillows, and endless love. Because when you love someone, you accept their flaws... even when those flaws sound like a demon bear with breathing problems.\n\nHere begins my saga. The saga of love, exhaustion, and merciless nights.";
		$story3_chapter1_title = 'My Boyfriend Snores Like a Pig';
		$story3_chapter1_content = "I love my boyfriend. Truly. During the day he is charming, sweet, almost silent. But at night... at night he becomes a pig. Not metaphorically. Acoustically.\n\nThe moment he falls asleep is deceptive. He breathes peacefully, as if he were a harmless creature. I think: \"Tonight, I will sleep.\" Naive mistake. Five minutes later comes the first snore. Deep. Wet. Determined. The kind of sound you would expect on a farm at three in the morning.\n\nI try to ignore it. I turn over. I put a pillow over my head. Nothing helps. The snores come in waves: sometimes fast, sometimes long, sometimes with a pause so long that I think he died... until he suddenly explodes with a snore that almost breaks the bed.\n\nI watch him sleeping, completely happy, while I count minutes until sunrise. Love is patience, they say. But no one warned me that love also means sleeping beside a pig with supernatural lung capacity.\n\nAnd so ends my first nightly battle. Spoiler: it will not be the last.";


		// ===== STORY 1: Lorem Ipsum (Published) =====
		error_log( '[Fanfic Wizard Samples] Creating Story 1: Lorem Ipsum (Published)' );
		$story1_id = wp_insert_post( array(
			'post_title'   => 'Lorem Ipsum',
			'post_content' => $lorem_intro, // Keep original Lorem Ipsum.
			'post_excerpt' => $lorem_intro, // Story intro UI and search summary read from excerpt.
			'post_status'  => 'publish',
			'post_type'    => 'fanfiction_story',
			'post_author'  => $current_user_id,
		) );

		if ( ! is_wp_error( $story1_id ) && $story1_id > 0 ) {
			error_log( '[Fanfic Wizard Samples] Story 1 created with ID: ' . $story1_id );
			// Set status taxonomy
			wp_set_object_terms( $story1_id, $random_status_1->term_id, 'fanfiction_status', false );

			// Set genre taxonomy (2 genres)
			if ( ! empty( $random_genres_1 ) ) {
				$genre_ids = wp_list_pluck( $random_genres_1, 'term_id' );
				wp_set_object_terms( $story1_id, $genre_ids, 'fanfiction_genre', false );
			}

			// Set warnings
			if ( ! empty( $random_warnings_1 ) && class_exists( 'Fanfic_Warnings' ) ) {
				$warning_ids = wp_list_pluck( $random_warnings_1, 'id' );
				Fanfic_Warnings::save_story_warnings( $story1_id, $warning_ids );
			}

			// Set language
			if ( ! is_null( $random_language_1_id ) && class_exists( 'Fanfic_Languages' ) ) {
				Fanfic_Languages::save_story_language( $story1_id, $random_language_1_id );
			}

			// Set fandom
			if ( ! is_null( $random_fandom_1_id ) && class_exists( 'Fanfic_Fandoms' ) ) {
				Fanfic_Fandoms::save_story_fandoms( $story1_id, array( $random_fandom_1_id ), false );
			}

			// Set sample tags.
			$this->apply_sample_story_tags(
				$story1_id,
				$story1_tag_sets['visible'],
				$story1_tag_sets['invisible']
			);


			// Chapter 1 (Published)
			$chapter1_id = wp_insert_post( array(
				'post_title'   => '1 - De Initio Verborum',
				'post_content' => $lorem_chapter,
				'post_status'  => 'publish',
				'post_type'    => 'fanfiction_chapter',
				'post_parent'  => $story1_id,
				'post_author'  => $current_user_id,
			) );

			if ( ! is_wp_error( $chapter1_id ) && $chapter1_id > 0 ) {
				update_post_meta( $chapter1_id, '_fanfic_chapter_type', 'chapter' );
				update_post_meta( $chapter1_id, '_fanfic_chapter_number', 1 );
			}

			// Chapter 2 (Draft)
			$chapter2_id = wp_insert_post( array(
				'post_title'   => '2 - Fragmenta Sine Sensu',
				'post_content' => $lorem_chapter,
				'post_status'  => 'draft',
				'post_type'    => 'fanfiction_chapter',
				'post_parent'  => $story1_id,
				'post_author'  => $current_user_id,
			) );

			if ( ! is_wp_error( $chapter2_id ) && $chapter2_id > 0 ) {
				update_post_meta( $chapter2_id, '_fanfic_chapter_type', 'chapter' );
				update_post_meta( $chapter2_id, '_fanfic_chapter_number', 2 );
			}

			if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
				Fanfic_Search_Index::update_index( $story1_id );
			}
		}

		// ===== STORY 2: La Sagao de Mia Ronkanta Kunulo (Published) =====
		error_log( '[Fanfic Wizard Samples] Creating Story 2: La Sagao de Mia Ronkanta Kunulo (Published)' );
		$story2_id = wp_insert_post( array(
			'post_title'   => 'La Sagao de Mia Ronkanta Kunulo',
			'post_content' => $story2_summary,
			'post_excerpt' => $story2_summary,
			'post_status'  => 'publish',
			'post_type'    => 'fanfiction_story',
			'post_author'  => $current_user_id,
		) );

		if ( ! is_wp_error( $story2_id ) && $story2_id > 0 ) {
			error_log( '[Fanfic Wizard Samples] Story 2 created with ID: ' . $story2_id );
			// Set status taxonomy
			wp_set_object_terms( $story2_id, $random_status_2->term_id, 'fanfiction_status', false );

			// Set genre taxonomy (2 genres)
			if ( ! empty( $random_genres_2 ) ) {
				$genre_ids = wp_list_pluck( $random_genres_2, 'term_id' );
				wp_set_object_terms( $story2_id, $genre_ids, 'fanfiction_genre', false );
			}

			// Set warnings
			if ( ! empty( $random_warnings_2 ) && class_exists( 'Fanfic_Warnings' ) ) {
				$warning_ids = wp_list_pluck( $random_warnings_2, 'id' );
				Fanfic_Warnings::save_story_warnings( $story2_id, $warning_ids );
			}

			// Set language
			if ( ! is_null( $random_language_2_id ) && class_exists( 'Fanfic_Languages' ) ) {
				Fanfic_Languages::save_story_language( $story2_id, $random_language_2_id );
			}

			// Set fandom
			if ( ! is_null( $random_fandom_2_id ) && class_exists( 'Fanfic_Fandoms' ) ) {
				Fanfic_Fandoms::save_story_fandoms( $story2_id, array( $random_fandom_2_id ), false );
			}

			// Set sample tags.
			$this->apply_sample_story_tags(
				$story2_id,
				$story2_tag_sets['visible'],
				$story2_tag_sets['invisible']
			);

			// Prologue (Published, NO TITLE - blank title)
			$prologue_id = wp_insert_post( array(
				'post_title'   => '', // Blank title as requested
				'post_content' => $story2_prologue_content,
				'post_status'  => 'publish',
				'post_type'    => 'fanfiction_chapter',
				'post_parent'  => $story2_id,
				'post_author'  => $current_user_id,
			) );

			if ( ! is_wp_error( $prologue_id ) && $prologue_id > 0 ) {
				update_post_meta( $prologue_id, '_fanfic_chapter_type', 'prologue' );
				update_post_meta( $prologue_id, '_fanfic_chapter_number', 0 );
			}

			// Chapter 1 (Published)
			$chapter1_s2_id = wp_insert_post( array(
				'post_title'   => 'Mia koramiko ronkas kiel porko', // "My boyfriend snores like a pig" in Esperanto
				'post_content' => $story2_chapter1_content,
				'post_status'  => 'publish',
				'post_type'    => 'fanfiction_chapter',
				'post_parent'  => $story2_id,
				'post_author'  => $current_user_id,
			) );

			if ( ! is_wp_error( $chapter1_s2_id ) && $chapter1_s2_id > 0 ) {
				update_post_meta( $chapter1_s2_id, '_fanfic_chapter_type', 'chapter' );
				update_post_meta( $chapter1_s2_id, '_fanfic_chapter_number', 1 );
			}

			if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
				Fanfic_Search_Index::update_index( $story2_id );
			}

			// ===== STORY 3: English Translation of Story 2 =====
			error_log( '[Fanfic Wizard Samples] Creating Story 3 translation: The Saga of My Snoring Partner (Published)' );
			$story3_id = wp_insert_post( array(
				'post_title'   => $story3_title,
				'post_content' => $story3_summary,
				'post_excerpt' => $story3_summary,
				'post_status'  => 'publish',
				'post_type'    => 'fanfiction_story',
				'post_author'  => $current_user_id,
			) );

			if ( ! is_wp_error( $story3_id ) && $story3_id > 0 ) {
				error_log( '[Fanfic Wizard Samples] Story 3 created with ID: ' . $story3_id );

				// Copy WordPress taxonomies from Story 2.
				$story2_status_term_ids = wp_get_object_terms( $story2_id, 'fanfiction_status', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $story2_status_term_ids ) && ! empty( $story2_status_term_ids ) ) {
					wp_set_object_terms( $story3_id, array_map( 'absint', $story2_status_term_ids ), 'fanfiction_status', false );
				}

				$story2_genre_term_ids = wp_get_object_terms( $story2_id, 'fanfiction_genre', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $story2_genre_term_ids ) && ! empty( $story2_genre_term_ids ) ) {
					wp_set_object_terms( $story3_id, array_map( 'absint', $story2_genre_term_ids ), 'fanfiction_genre', false );
				}

				// Copy warning relations from Story 2.
				if ( class_exists( 'Fanfic_Warnings' ) && method_exists( 'Fanfic_Warnings', 'get_story_warning_ids' ) && method_exists( 'Fanfic_Warnings', 'save_story_warnings' ) ) {
					$story2_warning_ids = Fanfic_Warnings::get_story_warning_ids( $story2_id );
					if ( ! empty( $story2_warning_ids ) ) {
						Fanfic_Warnings::save_story_warnings( $story3_id, array_map( 'absint', $story2_warning_ids ) );
					}
				}

				// Copy fandom relations from Story 2.
				if ( class_exists( 'Fanfic_Fandoms' ) && method_exists( 'Fanfic_Fandoms', 'get_story_fandom_ids' ) && method_exists( 'Fanfic_Fandoms', 'save_story_fandoms' ) ) {
					$story2_fandom_ids = Fanfic_Fandoms::get_story_fandom_ids( $story2_id );
					if ( ! empty( $story2_fandom_ids ) ) {
						Fanfic_Fandoms::save_story_fandoms( $story3_id, array_map( 'absint', $story2_fandom_ids ), false );
					}
				}

				// Copy custom taxonomy relations from Story 2.
				if ( class_exists( 'Fanfic_Custom_Taxonomies' ) && method_exists( 'Fanfic_Custom_Taxonomies', 'get_active_taxonomies' ) && method_exists( 'Fanfic_Custom_Taxonomies', 'get_story_term_ids' ) && method_exists( 'Fanfic_Custom_Taxonomies', 'save_story_terms' ) ) {
					$active_custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
					foreach ( (array) $active_custom_taxonomies as $custom_taxonomy ) {
						$taxonomy_id = absint( $custom_taxonomy['id'] ?? 0 );
						if ( ! $taxonomy_id ) {
							continue;
						}

						$story2_term_ids = Fanfic_Custom_Taxonomies::get_story_term_ids( $story2_id, $taxonomy_id );
						if ( ! empty( $story2_term_ids ) ) {
							Fanfic_Custom_Taxonomies::save_story_terms( $story3_id, $taxonomy_id, array_map( 'absint', $story2_term_ids ) );
						}
					}
				}

				// Set translation language for Story 3.
				if ( ! is_null( $random_language_3_id ) && class_exists( 'Fanfic_Languages' ) ) {
					Fanfic_Languages::save_story_language( $story3_id, $random_language_3_id );
				}

				// Inherit Story 2 tags for translation consistency.
				$this->apply_sample_story_tags(
					$story3_id,
					$story2_tag_sets['visible'],
					$story2_tag_sets['invisible']
				);

				// Prologue (Published, blank title, translated content).
				$prologue3_id = wp_insert_post( array(
					'post_title'   => '',
					'post_content' => $story3_prologue_content,
					'post_status'  => 'publish',
					'post_type'    => 'fanfiction_chapter',
					'post_parent'  => $story3_id,
					'post_author'  => $current_user_id,
				) );

				if ( ! is_wp_error( $prologue3_id ) && $prologue3_id > 0 ) {
					update_post_meta( $prologue3_id, '_fanfic_chapter_type', 'prologue' );
					update_post_meta( $prologue3_id, '_fanfic_chapter_number', 0 );
				}

				// Chapter 1 (Published, translated title/content).
				$chapter1_s3_id = wp_insert_post( array(
					'post_title'   => $story3_chapter1_title,
					'post_content' => $story3_chapter1_content,
					'post_status'  => 'publish',
					'post_type'    => 'fanfiction_chapter',
					'post_parent'  => $story3_id,
					'post_author'  => $current_user_id,
				) );

				if ( ! is_wp_error( $chapter1_s3_id ) && $chapter1_s3_id > 0 ) {
					update_post_meta( $chapter1_s3_id, '_fanfic_chapter_type', 'chapter' );
					update_post_meta( $chapter1_s3_id, '_fanfic_chapter_number', 1 );
				}

				// Link Story 2 and Story 3 directly in translation groups.
				if ( class_exists( 'Fanfic_Translations' ) && method_exists( 'Fanfic_Translations', 'add_to_group' ) ) {
					$link_result = Fanfic_Translations::add_to_group( $story2_id, $story3_id );
					if ( is_wp_error( $link_result ) ) {
						error_log( '[Fanfic Wizard Samples] Failed linking Story 2 and Story 3 translations: ' . $link_result->get_error_message() );
					} else {
						error_log( '[Fanfic Wizard Samples] Story 2 and Story 3 linked as translations.' );
					}
				}

				if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
					Fanfic_Search_Index::update_index( $story3_id );
				}
			}
		}

		error_log( '[Fanfic Wizard Samples] Sample story creation completed' );
	}
	/**
	 * Check if wizard is completed
	 *
	 * @since 1.0.0
	 * @return bool True if wizard completed, false otherwise.
	 */
	public static function is_completed() {
		return (bool) get_option( 'fanfic_wizard_completed', false );
	}

	/**
	 * Reset wizard (for re-running)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function reset() {
		delete_option( 'fanfic_wizard_completed' );
	}
}
