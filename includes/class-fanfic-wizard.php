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
				'title'       => __( 'Welcome', 'fanfiction-manager' ),
				'description' => __( 'Welcome to Fanfiction Manager setup wizard', 'fanfiction-manager' ),
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
				'title'       => __( 'Taxonomy Terms', 'fanfiction-manager' ),
				'description' => __( 'Select default terms for Genre and Status', 'fanfiction-manager' ),
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

		// Skip if current page is already the wizard
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			return;
		}

		// Check if we should show the wizard
		$show_wizard = get_option( 'fanfic_show_wizard', false );

		// Redirect if wizard flag is set (regardless of completion status)
		// The wizard page will handle showing choice screen if already completed
		if ( $show_wizard && current_user_can( 'manage_options' ) ) {
			// Delete the show_wizard flag to prevent infinite redirects
			delete_option( 'fanfic_show_wizard' );

			// Redirect to wizard
			wp_safe_redirect( admin_url( 'admin.php?page=fanfic-setup-wizard' ) );
			exit;
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
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'admin_url'        => admin_url(),
				'nonce'            => wp_create_nonce( 'fanfic_wizard_nonce' ),
				'current_step'     => $this->get_current_step(),
				'total_steps'      => $this->total_steps,
				'strings'          => array(
					'error'          => __( 'An error occurred. Please try again.', 'fanfiction-manager' ),
					'saving'         => __( 'Saving...', 'fanfiction-manager' ),
					'completing'     => __( 'Completing setup...', 'fanfiction-manager' ),
					'success'        => __( 'Settings saved successfully!', 'fanfiction-manager' ),
					'complete_setup' => __( 'Complete Setup', 'fanfiction-manager' ),
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
	 * Check if all required system pages exist
	 *
	 * @since 1.0.0
	 * @return bool True if all pages exist, false otherwise.
	 */
	private function all_pages_exist() {
		$page_ids = get_option( 'fanfic_system_page_ids', array() );

		// Only check physical WordPress pages (not dynamic pages)
		$required_pages = array(
			'main',
			'login',
			'register',
			'password-reset',
			'error',
			'maintenance',
		);

		// Check if all required pages exist and are published
		foreach ( $required_pages as $page_key ) {
			if ( empty( $page_ids[ $page_key ] ) ) {
				return false;
			}

			$page = get_post( $page_ids[ $page_key ] );
			if ( ! $page || 'publish' !== $page->post_status ) {
				return false;
			}
		}

		// Also verify dynamic page slugs are configured
		$dynamic_slugs = Fanfic_Dynamic_Pages::get_slugs();
		$dynamic_pages = Fanfic_Dynamic_Pages::get_dynamic_pages();
		foreach ( $dynamic_pages as $page_key ) {
			if ( empty( $dynamic_slugs[ $page_key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Render wizard page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_wizard_page() {
		// Set WordPress page title to prevent deprecation warnings
		global $title, $parent_file, $admin_title, $hook_suffix;

		// Set all possible WordPress admin page variables
		$title = __( 'Setup Wizard', 'fanfiction-manager' );
		$admin_title = __( 'Setup Wizard', 'fanfiction-manager' );
		$parent_file = '';
		$hook_suffix = 'admin_page_fanfic-setup-wizard';

		// Check if user wants to skip wizard (from choice screen)
		if ( isset( $_GET['action'] ) && 'skip' === $_GET['action'] ) {
			delete_option( 'fanfic_show_wizard' );
			wp_safe_redirect( admin_url( 'admin.php?page=fanfiction-settings' ) );
			exit;
		}

		// Check if wizard already completed and pages exist
		$wizard_completed = get_option( 'fanfic_wizard_completed', false );
		$all_pages_exist = $this->all_pages_exist();
		$force_run = isset( $_GET['force'] ) && 'true' === $_GET['force'];

		// Show choice screen if wizard was completed and pages exist, unless forced
		if ( $wizard_completed && $all_pages_exist && ! $force_run ) {
			$this->render_choice_screen();
			return;
		}

		$this->current_step = $this->get_current_step();
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
			<p style="font-size: 16px;"><?php esc_html_e( 'Would you like to run the wizard again or skip to the plugin settings?', 'fanfiction-manager' ); ?></p>
			<div class="notice notice-warning inline" style="margin: 20px 0; text-align: left;"><p><strong><?php esc_html_e( 'Warning:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Running the wizard again is not recommended. If you change URL settings, 301 redirects will be created from old URLs to new ones.', 'fanfiction-manager' ); ?></p></div>
			<div class="fanfic-wizard-choice-buttons" style="margin-top: 30px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfic-setup-wizard&action=skip' ) ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Skip to Settings', 'fanfiction-manager' ); ?></a> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfic-setup-wizard&force=true&step=1' ) ); ?>" class="button button-secondary button-hero"><?php esc_html_e( 'Run Wizard Again', 'fanfiction-manager' ); ?></a></div>
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
		<div class="fanfic-wizard-welcome">
			<div class="fanfic-wizard-welcome-icon">
				<span class="dashicons dashicons-book-alt" style="font-size: 80px; width: 80px; height: 80px;"></span>
			</div>
			<h3><?php esc_html_e( 'Welcome to Fanfiction Manager!', 'fanfiction-manager' ); ?></h3>
			<p><?php esc_html_e( 'This wizard will help you set up your fanfiction platform in just a few steps.', 'fanfiction-manager' ); ?></p>
			<p><?php esc_html_e( 'We will configure:', 'fanfiction-manager' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><?php esc_html_e( 'Base URL slug for your fanfiction pages', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Story subdirectory to avoid conflicts with system pages', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Secondary path customization (dashboard, user, search)', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'User roles (moderators and administrators)', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Default taxonomy terms (Genre and Status)', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'System pages creation', 'fanfiction-manager' ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Click "Next" to begin the setup process.', 'fanfiction-manager' ); ?></strong></p>
		</div>
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
			// Use the same form fields from URL Config class
			// This ensures consistency between wizard and settings page
			Fanfic_URL_Config::render_form_fields( true );
			?>
		</form>
		<?php
		// Include the CSS and JavaScript for URL previews
		Fanfic_URL_Config::render_styles();
		Fanfic_URL_Config::render_scripts();
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
		// Get saved term selections
		$selected_genres = get_option( 'fanfic_wizard_genre_terms', array(
			'Romance', 'Adventure', 'Drama', 'Horror', 'Mystery', 'Sci-Fi', 'Fantasy', 'Comedy'
		) );
		$selected_statuses = get_option( 'fanfic_wizard_status_terms', array(
			'Finished', 'Ongoing', 'On Hiatus', 'Abandoned'
		) );

		// Define available terms
		$available_genres = array(
			'Romance', 'Adventure', 'Drama', 'Horror', 'Mystery', 'Sci-Fi', 'Fantasy',
			'Comedy', 'Thriller', 'Action', 'Angst', 'Fluff', 'Hurt/Comfort', 'Tragedy'
		);
		$available_statuses = array(
			'Finished', 'Ongoing', 'On Hiatus', 'Abandoned'
		);
		?>
		<form id="fanfic-wizard-form-step-4" class="fanfic-wizard-form">
			<?php wp_nonce_field( 'fanfic_wizard_step_4', 'fanfic_wizard_nonce_step_4' ); ?>

			<div class="fanfic-wizard-taxonomy-info">
				<p><?php esc_html_e( 'Select which default terms to create for your Genre and Status taxonomies. These are commonly used terms that will help authors categorize their stories.', 'fanfiction-manager' ); ?></p>
				<p class="description"><?php esc_html_e( 'You can add, remove, or modify these terms later from the WordPress admin area.', 'fanfiction-manager' ); ?></p>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Genre Terms', 'fanfiction-manager' ); ?></label>
						<p class="description"><?php esc_html_e( '(Hierarchical, multiple selection)', 'fanfiction-manager' ); ?></p>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Select Genre Terms', 'fanfiction-manager' ); ?></legend>
							<p><button type="button" class="button button-small fanfic-select-all-genres"><?php esc_html_e( 'Select All', 'fanfiction-manager' ); ?></button> <button type="button" class="button button-small fanfic-deselect-all-genres"><?php esc_html_e( 'Deselect All', 'fanfiction-manager' ); ?></button></p>
							<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">
								<?php foreach ( $available_genres as $genre ) : ?>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" name="fanfic_genre_terms[]" value="<?php echo esc_attr( $genre ); ?>" <?php checked( in_array( $genre, $selected_genres, true ) ); ?> />
										<?php echo esc_html( $genre ); ?>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'Stories can be assigned multiple genres.', 'fanfiction-manager' ); ?></p>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Status Terms', 'fanfiction-manager' ); ?></label>
						<p class="description"><?php esc_html_e( '(Required for story validation)', 'fanfiction-manager' ); ?></p>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Select Status Terms', 'fanfiction-manager' ); ?></legend>
							<p class="notice notice-info inline"><strong><?php esc_html_e( 'Recommended:', 'fanfiction-manager' ); ?></strong> <?php esc_html_e( 'Keep all status terms selected. Stories must have a status assigned.', 'fanfiction-manager' ); ?></p>
							<?php foreach ( $available_statuses as $status ) : ?>
								<label style="display: block; margin-bottom: 8px;">
									<input type="checkbox" name="fanfic_status_terms[]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, $selected_statuses, true ) ); ?> />
									<strong><?php echo esc_html( $status ); ?></strong>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Stories can only have one status at a time.', 'fanfiction-manager' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>
		</form>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.fanfic-select-all-genres').on('click', function(e) {
				e.preventDefault();
				$('input[name="fanfic_genre_terms[]"]').prop('checked', true);
			});
			$('.fanfic-deselect-all-genres').on('click', function(e) {
				e.preventDefault();
				$('input[name="fanfic_genre_terms[]"]').prop('checked', false);
			});
		});
		</script>
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
				<p><?php esc_html_e( 'Click the "Complete Setup" button below to:', 'fanfiction-manager' ); ?></p>
				<ul style="list-style: disc; margin-left: 2em;">
					<li><?php esc_html_e( 'Save all your configuration settings', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Create all system pages (Login, Register, Archive, Dashboard, etc.)', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Create selected taxonomy terms for Genre and Status', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Assign user roles to selected users', 'fanfiction-manager' ); ?></li>
					<li><?php esc_html_e( 'Initialize the fanfiction platform', 'fanfiction-manager' ); ?></li>
				</ul>
				<p><strong><?php esc_html_e( 'This process may take a few seconds.', 'fanfiction-manager' ); ?></strong></p>
			</div>

			<div id="fanfic-wizard-completion-status" style="display: none; margin-top: 20px;">
				<div class="fanfic-wizard-progress-text">
					<p><span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span><?php esc_html_e( 'Setting up your fanfiction platform...', 'fanfiction-manager' ); ?></p>
				</div>
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
				// Steps 1 and 5 don't require saving
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
	 * Save URL settings step data
	 *
	 * Refactored to use Fanfic_URL_Schema for validation and consistency.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_url_settings_step() {
		// 1. Save main page mode
		if ( isset( $_POST['fanfic_main_page_mode'] ) ) {
			$main_page_mode = sanitize_text_field( wp_unslash( $_POST['fanfic_main_page_mode'] ) );
			if ( in_array( $main_page_mode, array( 'stories_homepage', 'custom_homepage' ), true ) ) {
				update_option( 'fanfic_main_page_mode', $main_page_mode );
			}
		}

		// 2. Validate and save base slug
		if ( isset( $_POST['fanfic_base_slug'] ) ) {
			$base_slug = sanitize_title( wp_unslash( $_POST['fanfic_base_slug'] ) );
			$validation = Fanfic_URL_Schema::validate_slug( $base_slug, array( 'base' ) );

			if ( is_wp_error( $validation ) ) {
				wp_send_json_error( array( 'message' => __( 'Base Slug: ', 'fanfiction-manager' ) . $validation->get_error_message() ) );
			}

			update_option( 'fanfic_base_slug', $base_slug );
		}

		// 3. Validate and save story path
		if ( isset( $_POST['fanfic_story_path'] ) ) {
			$story_path = sanitize_title( wp_unslash( $_POST['fanfic_story_path'] ) );
			$validation = Fanfic_URL_Schema::validate_slug( $story_path, array( 'story_path' ) );

			if ( is_wp_error( $validation ) ) {
				wp_send_json_error( array( 'message' => __( 'Story Path: ', 'fanfiction-manager' ) . $validation->get_error_message() ) );
			}

			update_option( 'fanfic_story_path', $story_path );
		}

		// 4. Validate and save secondary paths (dashboard, user, search)
		$secondary_slugs_input = array();
		$secondary_config = Fanfic_URL_Schema::get_slugs_by_group( 'secondary' );

		foreach ( $secondary_config as $key => $config ) {
			$field_name = 'fanfic_' . $key . '_slug';
			if ( isset( $_POST[ $field_name ] ) ) {
				$secondary_slugs_input[ $key ] = sanitize_title( wp_unslash( $_POST[ $field_name ] ) );
			}
		}

		// Check for duplicates
		if ( Fanfic_URL_Schema::has_duplicates( $secondary_slugs_input ) ) {
			wp_send_json_error( array( 'message' => __( 'User & System URLs must be unique from each other.', 'fanfiction-manager' ) ) );
		}

		// Validate each secondary path
		foreach ( $secondary_slugs_input as $key => $slug ) {
			$validation = Fanfic_URL_Schema::validate_slug( $slug, array( $key ) );
			if ( is_wp_error( $validation ) ) {
				wp_send_json_error( array( 'message' => ucfirst( $key ) . ': ' . $validation->get_error_message() ) );
			}
		}

		// Save secondary paths
		if ( ! empty( $secondary_slugs_input ) ) {
			update_option( 'fanfic_secondary_paths', $secondary_slugs_input );

			// Update dynamic pages if applicable
			if ( class_exists( 'Fanfic_Dynamic_Pages' ) ) {
				$dynamic_updates = array();
				foreach ( $secondary_config as $key => $config ) {
					if ( isset( $config['is_dynamic_page'] ) && $config['is_dynamic_page'] && ! empty( $secondary_slugs_input[ $key ] ) ) {
						$dynamic_updates[ $key ] = $secondary_slugs_input[ $key ];
					}
				}
				if ( ! empty( $dynamic_updates ) ) {
					$current_dynamic = Fanfic_Dynamic_Pages::get_slugs();
					Fanfic_Dynamic_Pages::update_slugs( array_merge( $current_dynamic, $dynamic_updates ) );
				}
			}
		}

		// 5. Validate and save chapter slugs (prologue, chapter, epilogue)
		$chapter_slugs_input = array();
		$chapter_config = Fanfic_URL_Schema::get_slugs_by_group( 'chapters' );

		foreach ( $chapter_config as $key => $config ) {
			$field_name = 'fanfic_' . $key . '_slug';
			$default_value = $config['default'];

			if ( isset( $_POST[ $field_name ] ) ) {
				$chapter_slugs_input[ $key ] = sanitize_title( wp_unslash( $_POST[ $field_name ] ) );
			} else {
				$chapter_slugs_input[ $key ] = $default_value;
			}
		}

		// Check for duplicates
		if ( Fanfic_URL_Schema::has_duplicates( $chapter_slugs_input ) ) {
			wp_send_json_error( array( 'message' => __( 'Chapter type slugs must be unique from each other.', 'fanfiction-manager' ) ) );
		}

		// Validate each chapter slug
		foreach ( $chapter_slugs_input as $key => $slug ) {
			$validation = Fanfic_URL_Schema::validate_slug( $slug, array( $key ) );
			if ( is_wp_error( $validation ) ) {
				wp_send_json_error( array( 'message' => ucfirst( $key ) . ': ' . $validation->get_error_message() ) );
			}
		}

		// Save chapter slugs
		update_option( 'fanfic_chapter_slugs', $chapter_slugs_input );

		// 6. Validate and save system page slugs
		if ( isset( $_POST['fanfic_system_page_slugs'] ) && is_array( $_POST['fanfic_system_page_slugs'] ) ) {
			$slugs = array_map( 'sanitize_title', wp_unslash( $_POST['fanfic_system_page_slugs'] ) );

			// Check for duplicates
			$slug_counts = array_count_values( array_filter( $slugs ) );
			foreach ( $slug_counts as $slug => $count ) {
				if ( $count > 1 ) {
					wp_send_json_error( array(
						'message' => sprintf(
							/* translators: %s: duplicate slug */
							__( 'Duplicate page slug detected: "%s". Each page must have a unique slug.', 'fanfiction-manager' ),
							$slug
						),
					) );
				}
			}

			// Save page slugs and handle dynamic pages
			$page_slugs = array();
			$dynamic_page_slugs = array();

			if ( class_exists( 'Fanfic_Dynamic_Pages' ) ) {
				$dynamic_pages = Fanfic_Dynamic_Pages::get_dynamic_pages();
				$current_dynamic_slugs = Fanfic_Dynamic_Pages::get_slugs();
			} else {
				$dynamic_pages = array();
				$current_dynamic_slugs = array();
			}

			foreach ( $slugs as $key => $slug ) {
				if ( ! empty( $slug ) ) {
					$page_slugs[ $key ] = $slug;

					// Handle dynamic pages
					if ( in_array( $key, $dynamic_pages, true ) ) {
						// Skip dashboard/search if already set from secondary paths
						if ( ( $key === 'dashboard' || $key === 'search' ) && ! empty( $current_dynamic_slugs[ $key ] ) && $current_dynamic_slugs[ $key ] !== $slug ) {
							continue;
						}
						$dynamic_page_slugs[ $key ] = $slug;
					}
				}
			}

			update_option( 'fanfic_system_page_slugs', $page_slugs );

			// Update dynamic pages
			if ( ! empty( $dynamic_page_slugs ) && class_exists( 'Fanfic_Dynamic_Pages' ) ) {
				Fanfic_Dynamic_Pages::update_slugs( array_merge( $current_dynamic_slugs, $dynamic_page_slugs ) );
			}
		}

		// 7. Create/update system pages with new slugs
		if ( class_exists( 'Fanfic_Templates' ) ) {
			$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
			Fanfic_Templates::create_system_pages( $base_slug );
		}

		// 8. Flush rewrite rules
		$this->flush_rewrite_rules();
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
		// Register all rewrite rules before flushing
		if ( class_exists( 'Fanfic_Post_Types' ) ) {
			Fanfic_Post_Types::register_post_types();
		}
		if ( class_exists( 'Fanfic_Taxonomies' ) ) {
			Fanfic_Taxonomies::register_taxonomies();
		}
		if ( class_exists( 'Fanfic_Rewrite' ) ) {
			Fanfic_Rewrite::add_rewrite_rules();
		}
		if ( class_exists( 'Fanfic_Dynamic_Pages' ) ) {
			Fanfic_Dynamic_Pages::add_rewrite_rules();
		}

		flush_rewrite_rules();
		set_transient( 'fanfic_flush_rewrite_rules', 1, 60 );
	}

	/**
	 * Save user roles step data
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_user_roles_step() {
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
	}

	/**
	 * Save taxonomy terms step data
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function save_taxonomy_terms_step() {
		// Save genre terms
		$genre_terms = array();
		if ( isset( $_POST['fanfic_genre_terms'] ) && is_array( $_POST['fanfic_genre_terms'] ) ) {
			$genre_terms = array_map( 'sanitize_text_field', wp_unslash( $_POST['fanfic_genre_terms'] ) );
		}
		update_option( 'fanfic_wizard_genre_terms', $genre_terms );

		// Save status terms
		$status_terms = array();
		if ( isset( $_POST['fanfic_status_terms'] ) && is_array( $_POST['fanfic_status_terms'] ) ) {
			$status_terms = array_map( 'sanitize_text_field', wp_unslash( $_POST['fanfic_status_terms'] ) );
		}
		update_option( 'fanfic_wizard_status_terms', $status_terms );
	}

	/**
	 * AJAX handler for completing the wizard
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

		// Get saved settings
		$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );

		// Create system pages with error handling
		$page_result = Fanfic_Templates::create_system_pages( $base_slug );

		// Check if page creation was successful
		if ( ! $page_result['success'] ) {
			// Build detailed error message
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

			wp_send_json_error( array(
				'message' => $page_result['message'],
				'details' => $error_details,
			) );
		}

		// Create taxonomy terms
		$this->create_taxonomy_terms();

		// Assign user roles
		$this->assign_user_roles();

		// Flush rewrite rules using shared helper method
		$this->flush_rewrite_rules();

		// Double-check that all pages exist using existing validation method
		if ( ! $this->all_pages_exist() ) {
			wp_send_json_error( array(
				'message' => __( 'Page validation failed. Some required pages are missing or not published.', 'fanfiction-manager' ),
				'details' => array( __( 'Please check the Pages section in WordPress admin.', 'fanfiction-manager' ) ),
			) );
		}

		// Mark wizard as completed
		update_option( 'fanfic_wizard_completed', true );
		delete_option( 'fanfic_show_wizard' );

		// Clean up temporary wizard data
		delete_option( 'fanfic_wizard_moderators' );
		delete_option( 'fanfic_wizard_admins' );

		wp_send_json_success( array(
			'message'      => __( 'Setup completed successfully! Redirecting...', 'fanfiction-manager' ),
			'redirect_url' => admin_url( 'admin.php?page=fanfiction-manager' ),
		) );
	}

	/**
	 * Create taxonomy terms based on wizard selections
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function create_taxonomy_terms() {
		// Get selected terms
		$genre_terms = get_option( 'fanfic_wizard_genre_terms', array() );
		$status_terms = get_option( 'fanfic_wizard_status_terms', array() );

		// Create genre terms
		if ( ! empty( $genre_terms ) && is_array( $genre_terms ) ) {
			foreach ( $genre_terms as $term ) {
				if ( ! empty( $term ) ) {
					// Check if term already exists
					$existing_term = term_exists( $term, 'fanfiction_genre' );
					if ( ! $existing_term ) {
						wp_insert_term( $term, 'fanfiction_genre' );
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
						wp_insert_term( $term, 'fanfiction_status' );
					}
				}
			}
		}

		// Clean up temporary wizard data
		delete_option( 'fanfic_wizard_genre_terms' );
		delete_option( 'fanfic_wizard_status_terms' );
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

		// Assign moderator role
		foreach ( $moderators as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && ! in_array( $user_id, $admins, true ) ) { // Don't assign moderator if also admin
				$user->add_role( 'fanfiction_moderator' );
			}
		}

		// Assign admin role (also gets moderator capabilities)
		foreach ( $admins as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				// Add fanfiction capabilities to their existing role
				$user->add_cap( 'manage_fanfiction' );
				$user->add_cap( 'moderate_fanfiction' );
			}
		}
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
		update_option( 'fanfic_show_wizard', true );
	}
}
