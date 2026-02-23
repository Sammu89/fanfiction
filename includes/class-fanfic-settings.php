<?php
/**
 * Settings Management Class
 *
 * Handles all settings functionality including Dashboard statistics,
 * General settings, Email Templates, and Custom CSS.
 *
 * @package FanfictionManager
 * @subpackage Settings
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Settings
 *
 * Manages plugin settings across multiple tabs.
 *
 * @since 1.0.0
 */
class Fanfic_Settings {

	/**
	 * Option name for storing settings
	 *
	 * @var string
	 */
	const OPTION_NAME = 'fanfic_settings';

	/**
	 * Option name for storing custom CSS
	 *
	 * @var string
	 */
	const CSS_OPTION_NAME = 'fanfic_custom_css';

	/**
	 * Option name for storing email templates
	 *
	 * @var string
	 */
	const EMAIL_TEMPLATES_OPTION = 'fanfic_email_templates';

	/**
	 * Initialize the settings class
	 *
	 * Sets up WordPress hooks for settings functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_reset_page_template' ) );
		add_action( 'admin_post_fanfic_save_general_settings', array( __CLASS__, 'save_general_settings' ) );
		add_action( 'admin_post_fanfic_save_layout_settings', array( __CLASS__, 'save_layout_settings' ) );
		add_action( 'admin_post_fanfic_save_email_templates', array( __CLASS__, 'save_email_templates' ) );
		add_action( 'admin_post_fanfic_save_page_templates', array( __CLASS__, 'save_page_templates' ) );
		add_action( 'admin_post_fanfic_save_custom_css', array( __CLASS__, 'save_custom_css' ) );
		add_action( 'admin_post_fanfic_run_cron_now', array( __CLASS__, 'run_cron_now' ) );
		add_action( 'admin_post_fanfic_run_demotion_now', array( __CLASS__, 'run_demotion_now' ) );
		add_action( 'admin_post_fanfic_delete_data', array( __CLASS__, 'handle_delete_data' ) );
		add_action( 'admin_post_fanfic_sync_homepage_settings', array( __CLASS__, 'sync_homepage_settings' ) );

		// AJAX handlers for email templates
		add_action( 'wp_ajax_fanfic_preview_email_template', array( __CLASS__, 'ajax_preview_email_template' ) );
		add_action( 'wp_ajax_fanfic_send_test_email', array( __CLASS__, 'ajax_send_test_email' ) );
		add_action( 'wp_ajax_fanfic_reset_email_template', array( __CLASS__, 'ajax_reset_email_template' ) );

		// AJAX handlers for cache management
		add_action( 'wp_ajax_fanfic_clear_all_cache', array( __CLASS__, 'ajax_clear_all_cache' ) );
		add_action( 'wp_ajax_fanfic_cleanup_expired_cache', array( __CLASS__, 'ajax_cleanup_expired_cache' ) );
		add_action( 'wp_ajax_fanfic_get_cache_stats', array( __CLASS__, 'ajax_get_cache_stats' ) );
	}

	/**
	 * Handle request to reset page template to default
	 *
	 * @since 1.0.0
	 */
	public static function handle_reset_page_template() {
		if ( isset( $_GET['reset_template'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanfic_reset_page_template' ) ) {
				wp_die( 'Security check failed.' );
			}

			$template_to_reset = sanitize_key( $_GET['reset_template'] );
			switch ( $template_to_reset ) {
				case 'story':
					delete_option( 'fanfic_shortcode_story_view' );
					break;
				case 'chapter':
					delete_option( 'fanfic_shortcode_chapter_view' );
					break;
				case 'profile':
					delete_option( 'fanfic_shortcode_profile_view' );
					break;
			}

			wp_safe_redirect( admin_url( 'admin.php?page=fanfiction-settings&tab=page-templates&template_reset=true' ) );
			exit;
		}
	}

	/**
	 * Register settings using WordPress Settings API
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_settings() {
		// Register main settings
		register_setting(
			'fanfic_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);

		// Register custom CSS
		register_setting(
			'fanfic_css_group',
			self::CSS_OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_strip_all_tags',
				'default'           => '',
			)
		);

		// Register email templates
		register_setting(
			'fanfic_email_templates_group',
			self::EMAIL_TEMPLATES_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_email_templates' ),
				'default'           => self::get_default_email_templates(),
			)
		);

		// Register Page Template & Layout Settings (synced with Customizer)
		register_setting(
			'fanfic_settings_group',
			'fanfic_show_sidebar',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'Fanfic_Templates', 'sanitize_checkbox_option' ),
				'default'           => '1',
			)
		);

		register_setting(
			'fanfic_settings_group',
			'fanfic_page_width',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'theme-default',
			)
		);
	}

	/**
	 * Get default settings
	 *
	 * @since 1.0.0
	 * @return array Default settings array
	 */
	private static function get_default_settings() {
		return array(
			'featured_mode'                  => 'manual',
			'featured_rating_min'            => 4,
			'featured_votes_min'             => 10,
			'featured_comments_min'          => 5,
			'featured_max_count'             => 6,
			'maintenance_mode'               => false,
			'cron_hour'                      => 3,
			'recaptcha_require_logged_in'    => false,
			'enable_likes'                   => true,
			'enable_dislikes'                => false,
			'enable_share'                   => true,
			'enable_report'                  => true,
			'enable_warnings'                => true,
			'enable_tags'                    => true,
			'enable_comments'                => true,
			'enable_coauthors'               => false,
			'allow_anonymous_reports'        => false,
			'enable_fandom_classification'   => false,
			'enable_language_classification' => false,
			'enable_image_uploads'           => false,
			'image_upload_max_value'         => 1,
			'image_upload_max_unit'          => 'mb',
			'allow_sexual_content'           => true,
			'allow_pornographic_content'     => false,
			'hiatus_threshold_value'         => 4,
			'hiatus_threshold_unit'          => 'months',
			'abandoned_threshold_value'      => 10,
			'abandoned_threshold_unit'       => 'months',
		);
	}

	/**
	 * Get default email templates
	 *
	 * @since 1.0.0
	 * @return array Default email templates
	 */
	private static function get_default_email_templates() {
		return array(
			'new_comment' => "Hi {user_name},\n\n{commenter_name} left a comment on your story \"{story_title}\":\n\n{comment_content}\n\nView the comment: {comment_url}\n\nBest regards,\n{site_name}",
			'new_story_from_author' => "Hi {user_name},\n\n{author_name}, an author you subscribed to, has published a new story: \"{story_title}\"\n\n{story_summary}\n\nRead it now: {story_url}\n\nBest regards,\n{site_name}",
			'new_chapter' => "Hi {user_name},\n\nA new chapter has been published in \"{story_title}\", a story you subscribed to.\n\nChapter {chapter_number}: {chapter_title}\n\nRead it now: {chapter_url}\n\nBest regards,\n{site_name}",
		);
	}

	/**
	 * Get a specific setting value
	 *
	 * @since 1.0.0
	 * @param string $key     Setting key
	 * @param mixed  $default Default value if setting doesn't exist
	 * @return mixed Setting value
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = get_option( self::OPTION_NAME, self::get_default_settings() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update a specific setting value
	 *
	 * @since 1.0.0
	 * @param string $key   Setting key
	 * @param mixed  $value Setting value
	 * @return bool True on success, false on failure
	 */
	public static function update_setting( $key, $value ) {
		$settings = get_option( self::OPTION_NAME, self::get_default_settings() );
		$settings[ $key ] = $value;
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Sanitize settings array
	 *
	 * @since 1.0.0
	 * @param array $settings Settings array to sanitize
	 * @return array Sanitized settings
	 */
	public static function sanitize_settings( $settings ) {
		$sanitized = array();

		// Featured mode
		$sanitized['featured_mode'] = isset( $settings['featured_mode'] ) && in_array( $settings['featured_mode'], array( 'manual', 'automatic' ), true )
			? $settings['featured_mode']
			: 'manual';

		// Featured criteria (integers)
		$sanitized['featured_rating_min'] = isset( $settings['featured_rating_min'] )
			? absint( $settings['featured_rating_min'] )
			: 4;

		$sanitized['featured_votes_min'] = isset( $settings['featured_votes_min'] )
			? absint( $settings['featured_votes_min'] )
			: 10;

		$sanitized['featured_comments_min'] = isset( $settings['featured_comments_min'] )
			? absint( $settings['featured_comments_min'] )
			: 5;

		$sanitized['featured_max_count'] = isset( $settings['featured_max_count'] )
			? absint( $settings['featured_max_count'] )
			: 6;

		// Maintenance mode
		$sanitized['maintenance_mode'] = isset( $settings['maintenance_mode'] ) && $settings['maintenance_mode'];

		// Cron hour (0-23)
		$cron_hour = isset( $settings['cron_hour'] ) ? absint( $settings['cron_hour'] ) : 3;
		$sanitized['cron_hour'] = min( 23, max( 0, $cron_hour ) );

		// reCAPTCHA require logged in users
		$sanitized['recaptcha_require_logged_in'] = isset( $settings['recaptcha_require_logged_in'] ) && $settings['recaptcha_require_logged_in'];

		// Image uploads
		$sanitized['enable_image_uploads'] = isset( $settings['enable_image_uploads'] ) && $settings['enable_image_uploads'];
		$sanitized['enable_fandom_classification'] = isset( $settings['enable_fandom_classification'] ) && $settings['enable_fandom_classification'];
		$sanitized['enable_language_classification'] = isset( $settings['enable_language_classification'] ) && $settings['enable_language_classification'];
		$max_value = isset( $settings['image_upload_max_value'] ) ? absint( $settings['image_upload_max_value'] ) : 1;
		if ( $max_value < 1 ) {
			$max_value = 1;
		}
		$max_unit = isset( $settings['image_upload_max_unit'] ) ? sanitize_key( $settings['image_upload_max_unit'] ) : 'mb';
		if ( ! in_array( $max_unit, array( 'kb', 'mb' ), true ) ) {
			$max_unit = 'mb';
		}
		$sanitized['image_upload_max_value'] = $max_value;
		$sanitized['image_upload_max_unit'] = $max_unit;

		// Feature toggles (booleans)
		$sanitized['enable_likes']     = isset( $settings['enable_likes'] ) && $settings['enable_likes'];
		$sanitized['enable_dislikes']  = isset( $settings['enable_dislikes'] ) && $settings['enable_dislikes'];
		$sanitized['enable_share']     = isset( $settings['enable_share'] ) && $settings['enable_share'];
		$sanitized['enable_report']    = isset( $settings['enable_report'] ) && $settings['enable_report'];
		$sanitized['enable_warnings']  = isset( $settings['enable_warnings'] ) && $settings['enable_warnings'];
		$sanitized['enable_tags']      = isset( $settings['enable_tags'] ) && $settings['enable_tags'];
		$sanitized['enable_comments']  = isset( $settings['enable_comments'] ) && $settings['enable_comments'];
		$sanitized['enable_coauthors'] = isset( $settings['enable_coauthors'] ) && $settings['enable_coauthors'];

		// Anonymous user permissions
		$sanitized['allow_anonymous_reports'] = isset( $settings['allow_anonymous_reports'] ) && $settings['allow_anonymous_reports'];

		// Content restrictions (NEW in 1.2.0)
		$sanitized['allow_sexual_content']       = isset( $settings['allow_sexual_content'] ) && $settings['allow_sexual_content'];
		$sanitized['allow_pornographic_content'] = isset( $settings['allow_pornographic_content'] ) && $settings['allow_pornographic_content'];

		// Inactivity thresholds
		$sanitized['hiatus_threshold_value']    = max( 1, absint( $settings['hiatus_threshold_value'] ?? 4 ) );
		$sanitized['abandoned_threshold_value'] = max( 1, absint( $settings['abandoned_threshold_value'] ?? 10 ) );
		$allowed_units = array( 'days', 'weeks', 'months' );
		$sanitized['hiatus_threshold_unit']    = in_array( $settings['hiatus_threshold_unit'] ?? '', $allowed_units, true )
			? $settings['hiatus_threshold_unit'] : 'months';
		$sanitized['abandoned_threshold_unit'] = in_array( $settings['abandoned_threshold_unit'] ?? '', $allowed_units, true )
			? $settings['abandoned_threshold_unit'] : 'months';

		return $sanitized;
	}

	/**
	 * Sanitize email templates
	 *
	 * @since 1.0.0
	 * @param array $templates Email templates array
	 * @return array Sanitized templates
	 */
	public static function sanitize_email_templates( $templates ) {
		$sanitized = array();
		$valid_keys = array( 'new_comment', 'new_story_from_author', 'new_chapter' );

		foreach ( $valid_keys as $key ) {
			$sanitized[ $key ] = isset( $templates[ $key ] )
				? wp_kses_post( $templates[ $key ] )
				: '';
		}

		return $sanitized;
	}

	/**
	 * Calculate dashboard statistics
	 *
	 * @since 1.0.0
	 * @param string $period Time period: 'all-time', '30-days', '1-year'
	 * @return array Statistics array
	 */
	public static function get_statistics( $period = 'all-time' ) {
		global $wpdb;

		$stats = array();
		$date_query = array();

		// Set date query based on period
		switch ( $period ) {
			case '30-days':
				$date_query = array(
					'after' => '30 days ago',
				);
				break;
			case '1-year':
				$date_query = array(
					'after' => '1 year ago',
				);
				break;
			case 'all-time':
			default:
				// No date filter for all-time
				break;
		}

		// Total Stories (published only)
		$story_args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( ! empty( $date_query ) ) {
			$story_args['date_query'] = array( $date_query );
		}

		$stories_query = new WP_Query( $story_args );
		$stats['total_stories'] = $stories_query->found_posts;

		// Total Chapters (across all published stories)
		$chapter_args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( ! empty( $date_query ) ) {
			$chapter_args['date_query'] = array( $date_query );
		}

		$chapters_query = new WP_Query( $chapter_args );
		$stats['total_chapters'] = $chapters_query->found_posts;

		// Total Authors (users with at least 1 published story)
		$authors_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_author)
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s",
				'fanfiction_story',
				'publish'
			)
		);
		$stats['total_authors'] = (int) $authors_count;

		// Active Readers (total registered users)
		$user_args = array(
			'count_total' => true,
		);

		if ( 'all-time' !== $period ) {
			$date_created = '';
			switch ( $period ) {
				case '30-days':
					$date_created = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
					break;
				case '1-year':
					$date_created = date( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
					break;
			}

			if ( $date_created ) {
				$user_args['date_query'] = array(
					array(
						'after'     => $date_created,
						'inclusive' => true,
						'column'    => 'user_registered',
					),
				);
			}
		}

		$user_query = new WP_User_Query( $user_args );
		$stats['active_readers'] = $user_query->get_total();

		// Pending Reports (unreviewed reports count)
		$reports_table = $wpdb->prefix . 'fanfic_reports';
		$pending_where = "status = 'pending'";

		if ( 'all-time' !== $period ) {
			$date_limit = '';
			switch ( $period ) {
				case '30-days':
					$date_limit = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
					break;
				case '1-year':
					$date_limit = date( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
					break;
			}

			if ( $date_limit ) {
				$pending_where .= $wpdb->prepare( " AND created_at >= %s", $date_limit );
			}
		}

		$stats['pending_reports'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$reports_table} WHERE {$pending_where}"
		);

		// Suspended Users (count)
		$suspended_users_query = new WP_User_Query(
			array(
				'meta_key'   => 'fanfic_suspended',
				'meta_value' => '1',
				'count_total' => true,
			)
		);
		$stats['suspended_users'] = $suspended_users_query->get_total();

		return $stats;
	}

	/**
	 * Render System Status info box
	 *
	 * Displays current status of:
	 * - Pretty Permalinks
	 * - Page Template file existence
	 * - Page Template registration
	 * - System pages with template assignment
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_system_status_box() {
		// Handle cache clear request
		if ( isset( $_GET['fanfic_clear_cache'] ) && $_GET['fanfic_clear_cache'] === '1' &&
		     isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'fanfic_clear_cache' ) ) {
			if ( class_exists( 'Fanfic_Templates' ) ) {
				Fanfic_Templates::clear_theme_cache();
				echo '<div class="notice notice-success is-dismissible"><p><strong>Theme cache cleared!</strong> Please refresh this page to see updated template status.</p></div>';
			}
		}

		// Handle fix system pages request
		if ( isset( $_GET['fanfic_fix_pages'] ) && $_GET['fanfic_fix_pages'] === '1' &&
		     isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'fanfic_fix_pages' ) ) {
			$fixed_count = self::fix_system_pages_templates();
			if ( $fixed_count > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . sprintf( __( '%d pages updated!', 'fanfiction-manager' ), $fixed_count ) . '</strong> ' . __( 'System pages now have the correct template assigned.', 'fanfiction-manager' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-info is-dismissible"><p>' . __( 'No pages needed updating.', 'fanfiction-manager' ) . '</p></div>';
			}
		}

		// Check permalinks status
		$permalinks_enabled = Fanfic_Permalinks_Check::are_permalinks_enabled();

		// Check if template file exists
		$template_file = FANFIC_PLUGIN_DIR . 'templates/fanfiction-page-template.php';
		$template_exists = file_exists( $template_file ) && is_readable( $template_file );

		// Check if template is registered
		$template_registered = false;
		$templates = wp_get_theme()->get_page_templates();
		if ( isset( $templates['fanfiction-page-template.php'] ) ) {
			$template_registered = true;
		}

		// Also check if filter is registered
		global $wp_filter;
		$filter_registered = false;
		$filter_callbacks = array();
		if ( isset( $wp_filter['template_include'] ) ) {
			foreach ( $wp_filter['template_include']->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $idx => $callback ) {
					$filter_callbacks[] = array(
						'priority' => $priority,
						'callback' => $callback,
					);
					// Check if this is our callback
					if ( is_array( $callback['function'] ) &&
					     isset( $callback['function'][0] ) &&
					     ( $callback['function'][0] === 'Fanfic_Templates' ||
					       ( is_object( $callback['function'][0] ) && get_class( $callback['function'][0] ) === 'Fanfic_Templates' ) ) &&
					     isset( $callback['function'][1] ) &&
					     'template_loader' === $callback['function'][1] ) {
						$filter_registered = true;
					}
				}
			}
		}
		if ( $filter_registered ) {
			$template_registered = true;
		}

		// Get system pages and check their template assignment
		$system_page_ids = get_option( 'fanfic_system_page_ids', array() );
		$pages_with_template = array();
		$pages_without_template = array();

		foreach ( $system_page_ids as $page_key => $page_id ) {
			if ( ! $page_id || get_post_status( $page_id ) !== 'publish' ) {
				continue;
			}

			$page_template = get_post_meta( $page_id, '_wp_page_template', true );
			$page_title = get_the_title( $page_id );

			if ( $page_template === 'fanfiction-page-template.php' ) {
				$pages_with_template[] = $page_title;
			} else {
				$pages_without_template[] = array(
					'title' => $page_title,
					'id'    => $page_id,
				);
			}
		}

		// Overall status
		$overall_status = 'success';
		if ( ! $permalinks_enabled || ! $template_exists ) {
			$overall_status = 'error';
		} elseif ( ! $template_registered || ! empty( $pages_without_template ) ) {
			$overall_status = 'warning';
		}

		?>
		<div class="fanfic-system-status-box" style="background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid <?php echo $overall_status === 'success' ? '#46b450' : ( $overall_status === 'warning' ? '#ffb900' : '#dc3232' ); ?>; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
				<span class="dashicons dashicons-admin-tools" style="font-size: 24px;"></span>
				<?php esc_html_e( 'System Status', 'fanfiction-manager' ); ?>
			</h3>

			<table class="widefat" style="margin-top: 15px;">
				<tbody>
					<!-- Permalinks Status -->
					<tr>
						<td style="width: 30%; font-weight: 600;">
							<?php esc_html_e( 'Pretty Permalinks', 'fanfiction-manager' ); ?>
						</td>
						<td>
							<?php if ( $permalinks_enabled ) : ?>
								<span style="color: #46b450;">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Enabled', 'fanfiction-manager' ); ?>
								</span>
							<?php else : ?>
								<span style="color: #dc3232;">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e( 'Disabled', 'fanfiction-manager' ); ?>
								</span>
								<p style="margin: 5px 0 0 0; color: #646970;">
									<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Fix Permalinks', 'fanfiction-manager' ); ?>
									</a>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<!-- Template File Status -->
					<tr>
						<td style="font-weight: 600;">
							<?php esc_html_e( 'Page Template File', 'fanfiction-manager' ); ?>
						</td>
						<td>
							<?php if ( $template_exists ) : ?>
								<span style="color: #46b450;">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Exists', 'fanfiction-manager' ); ?>
								</span>
								<code style="margin-left: 10px; font-size: 11px; color: #646970;">fanfiction-page-template.php</code>
							<?php else : ?>
								<span style="color: #dc3232;">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e( 'Missing', 'fanfiction-manager' ); ?>
								</span>
								<p style="margin: 5px 0 0 0; color: #646970; font-size: 12px;">
									<?php esc_html_e( 'The template file is missing from the templates directory.', 'fanfiction-manager' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<!-- Template Registration Status -->
					<tr>
						<td style="font-weight: 600;">
							<?php esc_html_e( 'Template Registration', 'fanfiction-manager' ); ?>
						</td>
						<td>
							<?php if ( $template_registered ) : ?>
								<span style="color: #46b450;">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Registered', 'fanfiction-manager' ); ?>
								</span>
							<?php else : ?>
								<span style="color: #ffb900;">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Not Registered', 'fanfiction-manager' ); ?>
								</span>
								<p style="margin: 5px 0 0 0; color: #646970; font-size: 12px;">
									<?php esc_html_e( 'Template may not appear in page template dropdown.', 'fanfiction-manager' ); ?>
								</p>
							<?php endif; ?>
							<p style="margin: 10px 0 0 0;">
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'fanfic_clear_cache', '1' ), 'fanfic_clear_cache' ) ); ?>" class="button button-small">
									<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Clear Theme Cache', 'fanfiction-manager' ); ?>
								</a>
								<span style="font-size: 11px; color: #646970; margin-left: 10px;">
									<?php esc_html_e( '(Click if template doesn\'t appear in dropdown)', 'fanfiction-manager' ); ?>
								</span>
							</p>
						</td>
					</tr>

					<!-- System Pages Status -->
					<tr>
						<td style="font-weight: 600; vertical-align: top;">
							<?php esc_html_e( 'System Pages', 'fanfiction-manager' ); ?>
						</td>
						<td>
							<?php if ( empty( $system_page_ids ) ) : ?>
								<span style="color: #ffb900;">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'No system pages created', 'fanfiction-manager' ); ?>
								</span>
								<p style="margin: 5px 0 0 0; color: #646970; font-size: 12px;">
									<?php esc_html_e( 'Run the setup wizard to create system pages.', 'fanfiction-manager' ); ?>
								</p>
							<?php else : ?>
								<?php if ( empty( $pages_without_template ) ) : ?>
									<span style="color: #46b450;">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php
										printf(
											/* translators: %d: number of pages */
											esc_html__( 'All %d pages have template assigned', 'fanfiction-manager' ),
											count( $pages_with_template )
										);
										?>
									</span>
								<?php else : ?>
									<span style="color: #ffb900;">
										<span class="dashicons dashicons-warning"></span>
										<?php
										printf(
											/* translators: 1: pages with template, 2: total pages */
											esc_html__( '%1$d of %2$d pages have template', 'fanfiction-manager' ),
											count( $pages_with_template ),
											count( $pages_with_template ) + count( $pages_without_template )
										);
										?>
									</span>
									<p style="margin: 10px 0 0 0;">
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'fanfic_fix_pages', '1' ), 'fanfic_fix_pages' ) ); ?>" class="button button-primary button-small">
											<span class="dashicons dashicons-admin-tools" style="margin-top: 3px;"></span>
											<?php esc_html_e( 'Fix All System Pages', 'fanfiction-manager' ); ?>
										</a>
										<span style="font-size: 11px; color: #646970; margin-left: 10px;">
											<?php esc_html_e( '(Assign template to all pages at once)', 'fanfiction-manager' ); ?>
										</span>
									</p>
									<details style="margin-top: 10px;">
										<summary style="cursor: pointer; color: #2271b1; font-size: 12px;">
											<?php esc_html_e( 'View pages without template', 'fanfiction-manager' ); ?>
										</summary>
										<ul style="margin: 10px 0 0 20px; list-style: disc;">
											<?php foreach ( $pages_without_template as $page ) : ?>
												<li>
													<a href="<?php echo esc_url( get_edit_post_link( $page['id'] ) ); ?>">
														<?php echo esc_html( $page['title'] ); ?>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Debug Information -->
			<details style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
				<summary style="cursor: pointer; font-weight: 600; color: #2271b1;">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Debug Information (Click to expand)', 'fanfiction-manager' ); ?>
				</summary>
				<div style="margin-top: 15px;">

					<!-- Filter Registration -->
					<h4 style="margin-top: 0;"><?php esc_html_e( 'Filter Registration Status', 'fanfiction-manager' ); ?></h4>
					<p>
						<strong>Filter Registered:</strong>
						<code><?php echo $filter_registered ? 'YES' : 'NO'; ?></code>
					</p>

					<!-- All Available Templates -->
					<h4><?php esc_html_e( 'All Available Templates from WordPress', 'fanfiction-manager' ); ?></h4>
					<?php if ( empty( $templates ) ) : ?>
						<p style="color: #dc3232;">
							<strong><?php esc_html_e( 'No templates found!', 'fanfiction-manager' ); ?></strong>
							<?php esc_html_e( 'This means WordPress is not detecting any page templates.', 'fanfiction-manager' ); ?>
						</p>
					<?php else : ?>
						<table class="widefat striped" style="margin-top: 10px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Template File', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Template Name', 'fanfiction-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $templates as $template_file => $template_name ) : ?>
									<tr<?php echo $template_file === 'fanfiction-page-template.php' ? ' style="background: #d4edda;"' : ''; ?>>
										<td><code><?php echo esc_html( $template_file ); ?></code></td>
										<td><?php echo esc_html( $template_name ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p style="margin-top: 10px; font-size: 12px; color: #646970;">
							<?php
							printf(
								/* translators: %s: our template file name */
								esc_html__( 'Looking for: %s', 'fanfiction-manager' ),
								'<code>fanfiction-page-template.php</code>'
							);
							?>
						</p>
					<?php endif; ?>

					<!-- Filter Callbacks -->
					<h4><?php esc_html_e( 'Registered Filter Callbacks for "template_include"', 'fanfiction-manager' ); ?></h4>
					<?php if ( empty( $filter_callbacks ) ) : ?>
						<p style="color: #dc3232;">
							<strong><?php esc_html_e( 'No callbacks registered!', 'fanfiction-manager' ); ?></strong>
						</p>
					<?php else : ?>
						<table class="widefat striped" style="margin-top: 10px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Priority', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Callback', 'fanfiction-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $filter_callbacks as $cb ) : ?>
									<tr>
										<td><code><?php echo esc_html( $cb['priority'] ); ?></code></td>
										<td>
											<code>
												<?php
												if ( is_array( $cb['callback']['function'] ) ) {
													$class = is_object( $cb['callback']['function'][0] ) ? get_class( $cb['callback']['function'][0] ) : $cb['callback']['function'][0];
													echo esc_html( $class . '::' . $cb['callback']['function'][1] );
												} elseif ( is_string( $cb['callback']['function'] ) ) {
													echo esc_html( $cb['callback']['function'] );
												} else {
													echo esc_html( 'Closure or unknown' );
												}
												?>
											</code>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<!-- System Pages Detail -->
					<h4><?php esc_html_e( 'System Pages Template Assignment', 'fanfiction-manager' ); ?></h4>
					<?php if ( empty( $system_page_ids ) ) : ?>
						<p style="color: #ffb900;">
							<strong><?php esc_html_e( 'No system pages created yet.', 'fanfiction-manager' ); ?></strong>
						</p>
					<?php else : ?>
						<table class="widefat striped" style="margin-top: 10px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Page Key', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Page ID', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Page Title', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
									<th><?php esc_html_e( 'Assigned Template', 'fanfiction-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $system_page_ids as $page_key => $page_id ) : ?>
									<?php
									$page_status = get_post_status( $page_id );
									$page_title = get_the_title( $page_id );
									$assigned_template = get_post_meta( $page_id, '_wp_page_template', true );
									$has_correct_template = $assigned_template === 'fanfiction-page-template.php';
									?>
									<tr<?php echo $has_correct_template ? ' style="background: #d4edda;"' : ''; ?>>
										<td><code><?php echo esc_html( $page_key ); ?></code></td>
										<td><?php echo esc_html( $page_id ); ?></td>
										<td>
											<a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>" target="_blank">
												<?php echo esc_html( $page_title ?: '(No title)' ); ?>
											</a>
										</td>
										<td><?php echo esc_html( $page_status ?: 'not found' ); ?></td>
										<td>
											<code><?php echo esc_html( $assigned_template ?: 'default' ); ?></code>
											<?php if ( $has_correct_template ) : ?>
												<span style="color: #46b450;">✓</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<!-- Class Check -->
					<h4><?php esc_html_e( 'Class Availability', 'fanfiction-manager' ); ?></h4>
					<table class="widefat" style="margin-top: 10px;">
						<tbody>
							<tr>
								<td style="width: 40%;"><strong>Fanfic_Templates class exists:</strong></td>
								<td>
									<code><?php echo class_exists( 'Fanfic_Templates' ) ? 'YES' : 'NO'; ?></code>
								</td>
							</tr>
							<tr>
								<td><strong>Template file path:</strong></td>
								<td><code><?php echo esc_html( $template_file ); ?></code></td>
							</tr>
							<tr>
								<td><strong>Template file exists:</strong></td>
								<td><code><?php echo $template_exists ? 'YES' : 'NO'; ?></code></td>
							</tr>
							<tr>
								<td><strong>Current theme:</strong></td>
								<td><code><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></code></td>
							</tr>
							<tr>
								<td><strong>TEMPLATE_FILE constant:</strong></td>
								<td>
									<code>
										<?php
										if ( class_exists( 'Fanfic_Templates' ) ) {
											$reflection = new ReflectionClass( 'Fanfic_Templates' );
											$constants = $reflection->getConstants();
											echo isset( $constants['TEMPLATE_FILE'] ) ? esc_html( $constants['TEMPLATE_FILE'] ) : 'NOT DEFINED';
										} else {
											echo 'Class not loaded';
										}
										?>
									</code>
								</td>
							</tr>
						</tbody>
					</table>

					<!-- Manual Filter Test -->
					<h4><?php esc_html_e( 'Manual Filter Test', 'fanfiction-manager' ); ?></h4>
					<p style="font-size: 12px; color: #646970; margin-bottom: 10px;">
						<?php esc_html_e( 'This simulates calling the filter to see what WordPress would return:', 'fanfiction-manager' ); ?>
					</p>
					<?php
					// Manually apply the filter to see what comes back
					$test_template_input = '';
					$test_result = apply_filters( 'template_include', $test_template_input );
					?>
					<table class="widefat" style="margin-top: 10px;">
						<tbody>
							<tr>
								<td style="width: 40%;"><strong>Input to filter:</strong></td>
								<td><code><?php echo esc_html( $test_template_input ); ?></code></td>
							</tr>
							<tr>
								<td><strong>Output from filter:</strong></td>
								<td>
									<code><?php echo esc_html( $test_result ); ?></code>
								</td>
							</tr>
							<tr>
								<td><strong>Wrapper selected:</strong></td>
								<td>
									<code>
										<?php
										echo ( is_string( $test_result ) && false !== strpos( $test_result, 'fanfiction-page-template.php' ) ) ? 'YES' : 'NO';
										?>
									</code>
								</td>
							</tr>
						</tbody>
					</table>

					<!-- WordPress Template Discovery -->
					<h4><?php esc_html_e( 'WordPress Template Discovery Method', 'fanfiction-manager' ); ?></h4>
					<p style="font-size: 12px; color: #646970; margin-bottom: 10px;">
						<?php esc_html_e( 'WordPress finds templates in two ways:', 'fanfiction-manager' ); ?>
					</p>
					<ol style="font-size: 12px; color: #646970; margin-left: 20px;">
						<li><?php esc_html_e( 'Scanning theme directory for files with "Template Name:" header', 'fanfiction-manager' ); ?></li>
						<li><?php esc_html_e( 'Using the "template_include" filter to choose the final template', 'fanfiction-manager' ); ?></li>
					</ol>
					<?php
					// Check if our template file is in the plugin directory and has correct headers
					// Use the full path from FANFIC_PLUGIN_DIR
					$full_template_path = FANFIC_PLUGIN_DIR . 'templates/fanfiction-page-template.php';
					$template_headers = array(
						'Template Name' => 'NOT FOUND',
						'Template Post Type' => 'NOT FOUND',
					);
					if ( file_exists( $full_template_path ) ) {
						$template_headers = get_file_data(
							$full_template_path,
							array(
								'Template Name' => 'Template Name',
								'Template Post Type' => 'Template Post Type',
							)
						);
					}
					?>
					<table class="widefat" style="margin-top: 10px;">
						<tbody>
							<tr>
								<td style="width: 40%;"><strong>Full template path used:</strong></td>
								<td><code style="font-size: 10px;"><?php echo esc_html( $full_template_path ); ?></code></td>
							</tr>
							<tr>
								<td><strong>Template Name header:</strong></td>
								<td><code><?php echo esc_html( $template_headers['Template Name'] ?: 'NOT FOUND' ); ?></code></td>
							</tr>
							<tr>
								<td><strong>Template Post Type header:</strong></td>
								<td><code><?php echo esc_html( $template_headers['Template Post Type'] ?: 'NOT FOUND' ); ?></code></td>
							</tr>
						</tbody>
					</table>

				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * Fix system pages templates
	 *
	 * Assigns the fanfiction page template to all system pages that don't have it.
	 *
	 * @since 1.0.0
	 * @return int Number of pages fixed
	 */
	public static function fix_system_pages_templates() {
		$system_page_ids = get_option( 'fanfic_system_page_ids', array() );
		$fixed_count = 0;

		foreach ( $system_page_ids as $page_key => $page_id ) {
			// Skip invalid pages
			if ( ! $page_id || get_post_status( $page_id ) !== 'publish' ) {
				continue;
			}

			// Check current template
			$current_template = get_post_meta( $page_id, '_wp_page_template', true );

			// If not using our template, update it
			if ( $current_template !== 'fanfiction-page-template.php' ) {
				update_post_meta( $page_id, '_wp_page_template', 'fanfiction-page-template.php' );
				$fixed_count++;
			}
		}

		return $fixed_count;
	}

	/**
	 * Render Dashboard tab
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_dashboard_tab() {
		// Get selected period
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all-time';
		$allowed_periods = array( 'all-time', '30-days', '1-year' );
		$period = in_array( $period, $allowed_periods, true ) ? $period : 'all-time';

		// Get statistics
		$stats = self::get_statistics( $period );

		?>
		<div class="fanfiction-settings-tab fanfiction-dashboard-tab">

			<?php self::render_system_status_box(); ?>

			<h2><?php esc_html_e( 'Dashboard Statistics', 'fanfiction-manager' ); ?></h2>

			<div class="fanfic-period-selector">
				<label for="stats-period"><?php esc_html_e( 'Time Period:', 'fanfiction-manager' ); ?></label>
				<select id="stats-period" onchange="window.location.href='?page=fanfiction-settings&tab=dashboard&period=' + this.value;">
					<option value="all-time" <?php selected( $period, 'all-time' ); ?>><?php esc_html_e( 'All Time', 'fanfiction-manager' ); ?></option>
					<option value="30-days" <?php selected( $period, '30-days' ); ?>><?php esc_html_e( 'Last 30 Days', 'fanfiction-manager' ); ?></option>
					<option value="1-year" <?php selected( $period, '1-year' ); ?>><?php esc_html_e( 'Last Year', 'fanfiction-manager' ); ?></option>
				</select>
			</div>

			<div class="fanfic-stats-grid">
				<div class="fanfic-stat-box">
					<h3><?php esc_html_e( 'Total Stories', 'fanfiction-manager' ); ?></h3>
					<p><?php echo esc_html( number_format_i18n( $stats['total_stories'] ) ); ?></p>
				</div>

				<div class="fanfic-stat-box">
					<h3><?php esc_html_e( 'Total Chapters', 'fanfiction-manager' ); ?></h3>
					<p><?php echo esc_html( number_format_i18n( $stats['total_chapters'] ) ); ?></p>
				</div>

				<div class="fanfic-stat-box">
					<h3><?php esc_html_e( 'Total Authors', 'fanfiction-manager' ); ?></h3>
					<p><?php echo esc_html( number_format_i18n( $stats['total_authors'] ) ); ?></p>
				</div>

				<div class="fanfic-stat-box">
					<h3><?php esc_html_e( 'Active Readers', 'fanfiction-manager' ); ?></h3>
					<p><?php echo esc_html( number_format_i18n( $stats['active_readers'] ) ); ?></p>
				</div>

				<div class="fanfic-stat-box">
					<h3><?php esc_html_e( 'Pending Reports', 'fanfiction-manager' ); ?></h3>
					<p><?php echo esc_html( number_format_i18n( $stats['pending_reports'] ) ); ?></p>
				</div>

				<div class="fanfic-stat-box">
					<h3><?php esc_html_e( 'Suspended Users', 'fanfiction-manager' ); ?></h3>
					<p><?php echo esc_html( number_format_i18n( $stats['suspended_users'] ) ); ?></p>
				</div>
			</div>

			<div class="fanfic-chart-placeholder">
				<h3><?php esc_html_e( 'Activity Chart', 'fanfiction-manager' ); ?></h3>
				<p><?php esc_html_e( 'Chart visualization will be implemented in a future version.', 'fanfiction-manager' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Homepage Diagnostics tab
	 *
	 * Displays comprehensive diagnostic information about homepage configuration
	 * and synchronization status between plugin settings and WordPress front page settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_homepage_diagnostics_tab() {
		// Get current state
		$state = Fanfic_Homepage_State::get_current_state();

		// Resolve expected target
		$target = Fanfic_Homepage_State::resolve_wp_front_page_target( $state );

		// Get actual WordPress settings
		$current_show_on_front = get_option( 'show_on_front', 'posts' );
		$current_page_on_front = (int) get_option( 'page_on_front', 0 );

		// Get page title if set
		$page_title = '';
		if ( $current_page_on_front > 0 ) {
			$page = get_post( $current_page_on_front );
			if ( $page ) {
				$page_title = $page->post_title;
			}
		}

		// Check sync status
		$in_sync = Fanfic_Homepage_State::is_wp_front_page_in_sync( $state );

		// Build explanation for expected values
		$explanation = '';
		if ( $state['use_base_slug'] == 1 ) {
			$explanation = __( 'Base slug is enabled. WordPress homepage is independent of fanfiction plugin.', 'fanfiction-manager' );
		} else {
			if ( 'stories_homepage' === $state['main_page_mode'] || 'stories' === $state['homepage_source'] ) {
				$explanation = __( 'Stories are set as homepage. WordPress will show posts archive.', 'fanfiction-manager' );
			} elseif ( 'existing_page' === $state['homepage_source'] && $state['homepage_source_id'] > 0 ) {
				$existing_page = get_post( $state['homepage_source_id'] );
				$existing_page_title = $existing_page ? $existing_page->post_title : __( 'Unknown', 'fanfiction-manager' );
				$explanation = sprintf(
					/* translators: %s: page title */
					__( 'Existing page "%s" is set as homepage.', 'fanfiction-manager' ),
					$existing_page_title
				);
			} elseif ( 'wordpress_archive' === $state['homepage_source'] ) {
				$explanation = __( 'WordPress posts archive is set as homepage.', 'fanfiction-manager' );
			} elseif ( 'fanfiction_page' === $state['homepage_source'] && $state['main_page_id'] > 0 ) {
				$main_page = get_post( $state['main_page_id'] );
				$main_page_title = $main_page ? $main_page->post_title : __( 'Unknown', 'fanfiction-manager' );
				$explanation = sprintf(
					/* translators: %s: page title */
					__( 'Fanfiction main page "%s" is set as homepage.', 'fanfiction-manager' ),
					$main_page_title
				);
			} else {
				$explanation = __( 'Configuration unclear or incomplete.', 'fanfiction-manager' );
			}
		}

		// Determine differences if not in sync
		$differences = array();
		if ( ! $in_sync && $target !== null ) {
			if ( $current_show_on_front !== $target['show_on_front'] ) {
				$differences[] = sprintf(
					/* translators: 1: current value, 2: expected value */
					__( 'show_on_front: current = "%1$s", expected = "%2$s"', 'fanfiction-manager' ),
					$current_show_on_front,
					$target['show_on_front']
				);
			}
			if ( $current_page_on_front !== $target['page_on_front'] ) {
				$differences[] = sprintf(
					/* translators: 1: current value, 2: expected value */
					__( 'page_on_front: current = %1$d, expected = %2$d', 'fanfiction-manager' ),
					$current_page_on_front,
					$target['page_on_front']
				);
			}
		}
		?>
		<div class="fanfiction-settings-tab fanfiction-homepage-diagnostics-tab">
			<h2><?php esc_html_e( 'Homepage Diagnostics', 'fanfiction-manager' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'This page shows detailed information about your homepage configuration and the synchronization status between plugin settings and WordPress front page settings.', 'fanfiction-manager' ); ?>
			</p>

			<!-- Stored Intent Section -->
			<div class="fanfic-diagnostics-section" style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Stored Intent (Plugin Options)', 'fanfiction-manager' ); ?></h3>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'fanfic_use_base_slug', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo $state['use_base_slug'] == 1 ? 'on (1)' : 'off (0)'; ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'fanfic_main_page_mode', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo esc_html( $state['main_page_mode'] ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'fanfic_homepage_source', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo esc_html( $state['homepage_source'] ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'fanfic_homepage_source_id', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo esc_html( $state['homepage_source_id'] ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'fanfic_system_page_ids[\'main\']', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo esc_html( $state['main_page_id'] ); ?></code></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Resolved Expected Section -->
			<div class="fanfic-diagnostics-section" style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Resolved Expected (What WordPress Should Be)', 'fanfiction-manager' ); ?></h3>
				<?php if ( $target === null ) : ?>
					<p style="padding: 10px; background: #e8f4f8; border-left: 4px solid #00a0d2;">
						<strong><?php esc_html_e( 'Independent Mode:', 'fanfiction-manager' ); ?></strong>
						<?php esc_html_e( 'WordPress homepage is independent (base slug is enabled).', 'fanfiction-manager' ); ?>
					</p>
				<?php else : ?>
					<table class="widefat striped">
						<tbody>
							<tr>
								<th style="width: 30%;"><?php esc_html_e( 'Expected show_on_front', 'fanfiction-manager' ); ?></th>
								<td><code><?php echo esc_html( $target['show_on_front'] ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Expected page_on_front', 'fanfiction-manager' ); ?></th>
								<td><code><?php echo esc_html( $target['page_on_front'] ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Explanation', 'fanfiction-manager' ); ?></th>
								<td><?php echo esc_html( $explanation ); ?></td>
							</tr>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Actual WordPress Settings Section -->
			<div class="fanfic-diagnostics-section" style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Actual WordPress Settings', 'fanfiction-manager' ); ?></h3>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'Current show_on_front', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo esc_html( $current_show_on_front ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Current page_on_front', 'fanfiction-manager' ); ?></th>
							<td><code><?php echo esc_html( $current_page_on_front ); ?></code></td>
						</tr>
						<?php if ( $page_title ) : ?>
							<tr>
								<th><?php esc_html_e( 'Page Title', 'fanfiction-manager' ); ?></th>
								<td><?php echo esc_html( $page_title ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Sync Status Section -->
			<div class="fanfic-diagnostics-section" style="margin-top: 20px; padding: 15px; border: 1px solid <?php echo $in_sync ? '#46b450' : '#dc3232'; ?>; background: <?php echo $in_sync ? '#ecf7ed' : '#f9e9e9'; ?>;">
				<h3 style="margin-top: 0; color: <?php echo $in_sync ? '#46b450' : '#dc3232'; ?>;">
					<?php esc_html_e( 'Synchronization Status', 'fanfiction-manager' ); ?>
				</h3>

				<?php if ( $in_sync ) : ?>
					<p style="font-size: 16px; margin: 10px 0;">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px; vertical-align: middle;"></span>
						<strong style="color: #46b450;"><?php esc_html_e( 'IN SYNC', 'fanfiction-manager' ); ?></strong>
					</p>
					<p><?php esc_html_e( 'WordPress front page settings match the expected configuration from plugin settings.', 'fanfiction-manager' ); ?></p>
				<?php else : ?>
					<p style="font-size: 16px; margin: 10px 0;">
						<span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 20px; vertical-align: middle;"></span>
						<strong style="color: #dc3232;"><?php esc_html_e( 'MISMATCH', 'fanfiction-manager' ); ?></strong>
					</p>
					<p><?php esc_html_e( 'WordPress front page settings do not match the expected configuration from plugin settings.', 'fanfiction-manager' ); ?></p>

					<?php if ( ! empty( $differences ) ) : ?>
						<div style="margin-top: 15px;">
							<h4><?php esc_html_e( 'Differences:', 'fanfiction-manager' ); ?></h4>
							<ul style="list-style: disc; margin-left: 20px;">
								<?php foreach ( $differences as $difference ) : ?>
									<li><code><?php echo esc_html( $difference ); ?></code></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div style="margin-top: 15px;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<input type="hidden" name="action" value="fanfic_sync_homepage_settings">
							<?php wp_nonce_field( 'fanfic_sync_homepage_settings_nonce', 'fanfic_sync_nonce' ); ?>
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Sync Homepage Settings', 'fanfiction-manager' ); ?>
							</button>
						</form>
						<p class="description" style="margin-top: 5px;">
							<?php esc_html_e( 'Click this button to synchronize WordPress front page settings with the plugin configuration.', 'fanfiction-manager' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render General settings tab
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_general_settings_tab() {
		$settings = get_option( self::OPTION_NAME, self::get_default_settings() );
		?>
		<div class="fanfiction-settings-tab">
			<h2><?php esc_html_e( 'General Settings', 'fanfiction-manager' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_save_general_settings">
				<?php wp_nonce_field( 'fanfic_general_settings_nonce', 'fanfic_general_settings_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Featured Stories Mode -->
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Featured Stories Mode', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="fanfic_settings[featured_mode]" value="manual" <?php checked( $settings['featured_mode'], 'manual' ); ?>>
										<?php esc_html_e( 'Manual Selection', 'fanfiction-manager' ); ?>
									</label><br>
									<label>
										<input type="radio" name="fanfic_settings[featured_mode]" value="automatic" <?php checked( $settings['featured_mode'], 'automatic' ); ?>>
										<?php esc_html_e( 'Automatic Based on Criteria', 'fanfiction-manager' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

						<!-- Featured Stories Criteria -->
						<tr id="featured-criteria" style="<?php echo 'automatic' !== $settings['featured_mode'] ? 'display:none;' : ''; ?>">
							<th scope="row">
								<label><?php esc_html_e( 'Featured Stories Criteria', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<fieldset>
									<label>
										<?php esc_html_e( 'Minimum Rating:', 'fanfiction-manager' ); ?>
										<input type="number" name="fanfic_settings[featured_rating_min]" value="<?php echo esc_attr( $settings['featured_rating_min'] ); ?>" min="1" max="5" style="width: 80px;">
										<span class="description"><?php esc_html_e( '(1-5 stars)', 'fanfiction-manager' ); ?></span>
									</label><br>

									<label>
										<?php esc_html_e( 'Minimum Votes:', 'fanfiction-manager' ); ?>
										<input type="number" name="fanfic_settings[featured_votes_min]" value="<?php echo esc_attr( $settings['featured_votes_min'] ); ?>" min="0" style="width: 80px;">
									</label><br>

									<label>
										<?php esc_html_e( 'Minimum Comments:', 'fanfiction-manager' ); ?>
										<input type="number" name="fanfic_settings[featured_comments_min]" value="<?php echo esc_attr( $settings['featured_comments_min'] ); ?>" min="0" style="width: 80px;">
									</label>
								</fieldset>
							</td>
						</tr>

						<!-- Maximum Featured Stories -->
						<tr>
							<th scope="row">
								<label for="featured_max_count"><?php esc_html_e( 'Maximum Featured Stories', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<input type="number" id="featured_max_count" name="fanfic_settings[featured_max_count]" value="<?php echo esc_attr( $settings['featured_max_count'] ); ?>" min="1" max="20" style="width: 80px;">
								<p class="description"><?php esc_html_e( 'Maximum number of stories to display as featured.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

						<!-- Maintenance Mode -->
						<tr>
							<th scope="row">
								<label for="maintenance_mode"><?php esc_html_e( 'Maintenance Mode', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="maintenance_mode" name="fanfic_settings[maintenance_mode]" value="1" <?php checked( $settings['maintenance_mode'], true ); ?>>
									<?php esc_html_e( 'Enable maintenance mode (frontend will show a maintenance notice)', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>

						<!-- Image Uploads -->
						<tr>
							<th scope="row">
								<label for="enable_image_uploads"><?php esc_html_e( 'Image Uploads', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="enable_image_uploads" name="fanfic_settings[enable_image_uploads]" value="1" <?php checked( isset( $settings['enable_image_uploads'] ) ? $settings['enable_image_uploads'] : false, true ); ?>>
									<?php esc_html_e( 'Enable users to upload images for story covers and profile avatars', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When disabled, users can only provide image URLs.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

						<!-- WP-Cron Hour -->
						<tr>
							<th scope="row">
								<label for="cron_hour"><?php esc_html_e( 'Daily Cron Hour', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<select id="cron_hour" name="fanfic_settings[cron_hour]">
									<?php for ( $hour = 0; $hour < 24; $hour++ ) : ?>
										<option value="<?php echo esc_attr( $hour ); ?>" <?php selected( $settings['cron_hour'], $hour ); ?>>
											<?php echo esc_html( sprintf( '%02d:00', $hour ) ); ?>
										</option>
									<?php endfor; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Hour of the day (server time) when daily maintenance tasks should run.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

					</tbody>
				</table>

				<hr style="margin: 30px 0;">

				<!-- Inactivity Thresholds Section -->
				<h3><?php esc_html_e( 'Inactivity Thresholds', 'fanfiction-manager' ); ?></h3>
				<p class="description" style="margin-bottom: 15px;">
					<?php esc_html_e( 'Control how long a story must be inactive before its status changes automatically.', 'fanfiction-manager' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tbody>
						<?php
						$hiatus_value    = isset( $settings['hiatus_threshold_value'] ) ? absint( $settings['hiatus_threshold_value'] ) : 4;
						$hiatus_unit     = isset( $settings['hiatus_threshold_unit'] ) ? $settings['hiatus_threshold_unit'] : 'months';
						$abandoned_value = isset( $settings['abandoned_threshold_value'] ) ? absint( $settings['abandoned_threshold_value'] ) : 10;
						$abandoned_unit  = isset( $settings['abandoned_threshold_unit'] ) ? $settings['abandoned_threshold_unit'] : 'months';
						?>

						<!-- Ongoing → On Hiatus threshold -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Ongoing → On Hiatus after', 'fanfiction-manager' ); ?>
							</th>
							<td>
								<input type="number" name="fanfic_settings[hiatus_threshold_value]"
									value="<?php echo esc_attr( $hiatus_value ); ?>" min="1" style="width:70px">
								<select name="fanfic_settings[hiatus_threshold_unit]">
									<option value="days" <?php selected( $hiatus_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'fanfiction-manager' ); ?></option>
									<option value="weeks" <?php selected( $hiatus_unit, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'fanfiction-manager' ); ?></option>
									<option value="months" <?php selected( $hiatus_unit, 'months' ); ?>><?php esc_html_e( 'Months', 'fanfiction-manager' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Default: 4 months of inactivity.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

						<!-- On Hiatus → Abandoned threshold -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'On Hiatus → Abandoned after', 'fanfiction-manager' ); ?>
							</th>
							<td>
								<input type="number" name="fanfic_settings[abandoned_threshold_value]"
									value="<?php echo esc_attr( $abandoned_value ); ?>" min="1" style="width:70px">
								<select name="fanfic_settings[abandoned_threshold_unit]">
									<option value="days" <?php selected( $abandoned_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'fanfiction-manager' ); ?></option>
									<option value="weeks" <?php selected( $abandoned_unit, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'fanfiction-manager' ); ?></option>
									<option value="months" <?php selected( $abandoned_unit, 'months' ); ?>><?php esc_html_e( 'Months', 'fanfiction-manager' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Default: 10 months of inactivity.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

					</tbody>
				</table>

				<hr style="margin: 30px 0;">

				<!-- reCAPTCHA Settings Section -->
				<h3><?php esc_html_e( 'Google reCAPTCHA v2 Settings', 'fanfiction-manager' ); ?></h3>
				<p class="description" style="margin-bottom: 15px;">
					<?php
					printf(
						/* translators: %s: URL to Google reCAPTCHA admin page */
						esc_html__( 'Configure Google reCAPTCHA v2 to protect forms from spam and abuse. Get your keys from %s', 'fanfiction-manager' ),
						'<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">https://www.google.com/recaptcha/admin</a>'
					);
					?>
				</p>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- reCAPTCHA Site Key -->
						<tr>
							<th scope="row">
								<label for="fanfic_recaptcha_site_key"><?php esc_html_e( 'reCAPTCHA Site Key', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								$site_key = get_option( 'fanfic_recaptcha_site_key', '' );
								?>
								<input type="text" id="fanfic_recaptcha_site_key" name="fanfic_recaptcha_site_key" value="<?php echo esc_attr( $site_key ); ?>" class="regular-text" placeholder="6Lc...">
								<p class="description"><?php esc_html_e( 'Enter your reCAPTCHA Site Key (public key)', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

						<!-- reCAPTCHA Secret Key -->
						<tr>
							<th scope="row">
								<label for="fanfic_recaptcha_secret_key"><?php esc_html_e( 'reCAPTCHA Secret Key', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								$secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );
								?>
								<input type="password" id="fanfic_recaptcha_secret_key" name="fanfic_recaptcha_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text" placeholder="6Lc...">
								<p class="description"><?php esc_html_e( 'Enter your reCAPTCHA Secret Key (private key)', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

						<!-- reCAPTCHA for Logged-in Users -->
						<tr>
							<th scope="row">
								<label for="recaptcha_require_logged_in"><?php esc_html_e( 'Enable for Logged-in Users', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="recaptcha_require_logged_in" name="fanfic_settings[recaptcha_require_logged_in]" value="1" <?php checked( $settings['recaptcha_require_logged_in'], true ); ?>>
									<?php esc_html_e( 'Require reCAPTCHA verification for logged-in users too (recommended for high-security sites)', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<hr style="margin: 30px 0;">

				<!-- Content Actions Features Section -->
				<h3><?php esc_html_e( 'Content Actions Features', 'fanfiction-manager' ); ?></h3>
				<p class="description" style="margin-bottom: 15px;">
					<?php esc_html_e( 'Enable or disable specific features for story and chapter interactions. All features are enabled by default.', 'fanfiction-manager' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Enable Comments -->
						<tr>
							<th scope="row">
								<label for="enable_comments"><?php esc_html_e( 'Enable Comments', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="enable_comments" name="fanfic_settings[enable_comments]" value="1" <?php checked( isset( $settings['enable_comments'] ) ? $settings['enable_comments'] : true, true ); ?>>
									<?php esc_html_e( 'Allow comments on stories and chapters. Authors can control this per-story.', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>

						<!-- Enable Likes -->
						<tr>
							<th scope="row">
								<label for="enable_likes"><?php esc_html_e( 'Enable Likes', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="enable_likes" name="fanfic_settings[enable_likes]" value="1" <?php checked( isset( $settings['enable_likes'] ) ? $settings['enable_likes'] : true, true ); ?>>
									<?php esc_html_e( 'Allow users to like stories and chapters (shows like count)', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>

						<!-- Enable Share -->
						<tr>
							<th scope="row">
								<label for="enable_share"><?php esc_html_e( 'Enable Share Button', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="enable_share" name="fanfic_settings[enable_share]" value="1" <?php checked( isset( $settings['enable_share'] ) ? $settings['enable_share'] : true, true ); ?>>
									<?php esc_html_e( 'Show share button on stories, chapters, and author profiles (available to all visitors)', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>

						<!-- Enable Report -->
						<tr>
							<th scope="row">
								<label for="enable_report"><?php esc_html_e( 'Enable Content Reporting', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="enable_report" name="fanfic_settings[enable_report]" value="1" <?php checked( isset( $settings['enable_report'] ) ? $settings['enable_report'] : true, true ); ?>>
									<?php esc_html_e( 'Allow logged-in users to report inappropriate content', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>

						<!-- Enable Dislikes -->
						<tr>
							<th scope="row">
								<label for="enable_dislikes"><?php esc_html_e( 'Enable Dislikes', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="enable_dislikes" name="fanfic_settings[enable_dislikes]" value="1" <?php checked( isset( $settings['enable_dislikes'] ) ? $settings['enable_dislikes'] : false, true ); ?>>
									<?php esc_html_e( 'Allow users to dislike chapters (mutually exclusive with likes)', 'fanfiction-manager' ); ?>
								</label>
							</td>
						</tr>

						<!-- Enable Co-Authors -->
						<tr>
							<th scope="row">
								<label for="fanfic_enable_coauthors"><?php esc_html_e( 'Co-Authors', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="fanfic_enable_coauthors" name="fanfic_settings[enable_coauthors]" value="1" <?php checked( isset( $settings['enable_coauthors'] ) ? $settings['enable_coauthors'] : false, true ); ?>>
									<?php esc_html_e( 'Enable co-author functionality', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Allow authors to invite co-authors to collaborate on stories.', 'fanfiction-manager' ); ?></p>
							</td>
						</tr>

						<!-- Allow Anonymous Reports -->
						<tr>
							<th scope="row">
								<label for="allow_anonymous_reports"><?php esc_html_e( 'Allow Anonymous Reports', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								$recaptcha_site_key = get_option( 'fanfic_recaptcha_site_key', '' );
								$recaptcha_secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );
								$has_recaptcha = ! empty( $recaptcha_site_key ) && ! empty( $recaptcha_secret_key );
								$anonymous_reports_disabled = ! $has_recaptcha;
								?>
								<label>
									<input type="checkbox" id="allow_anonymous_reports" name="fanfic_settings[allow_anonymous_reports]" value="1" <?php checked( isset( $settings['allow_anonymous_reports'] ) && $has_recaptcha ? $settings['allow_anonymous_reports'] : false, true ); ?> <?php disabled( $anonymous_reports_disabled ); ?>>
									<?php esc_html_e( 'Allow non-logged users to report content (requires reCAPTCHA)', 'fanfiction-manager' ); ?>
								</label>
								<?php if ( ! $has_recaptcha ) : ?>
									<p class="description" style="color: #d63638; font-weight: 600;">
										<span class="dashicons dashicons-warning" style="color: #d63638;"></span>
										<?php esc_html_e( 'REQUIRED: You must configure reCAPTCHA keys above to enable anonymous reporting. This prevents spam and abuse.', 'fanfiction-manager' ); ?>
									</p>
								<?php else : ?>
									<p class="description">
										<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
										<?php esc_html_e( 'reCAPTCHA is configured. Anonymous reports will be protected against spam.', 'fanfiction-manager' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save General Settings', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
				</p>
			</form>

			<hr>

			<!-- Cache Management Section -->
			<?php
			// Include cache admin class if not already loaded
			if ( class_exists( 'Fanfic_Cache_Admin' ) ) {
				Fanfic_Cache_Admin::render_cache_management();
			}
			?>

			<hr>

			<!-- Utility Actions -->
			<h3><?php esc_html_e( 'Maintenance Actions', 'fanfiction-manager' ); ?></h3>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Run Cron Now', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
								<input type="hidden" name="action" value="fanfic_run_cron_now">
								<?php wp_nonce_field( 'fanfic_run_cron_now_nonce', 'fanfic_run_cron_now_nonce' ); ?>
								<?php submit_button( __( 'Run Cron Tasks Now', 'fanfiction-manager' ), 'secondary', 'submit', false ); ?>
							</form>
							<p class="description"><?php esc_html_e( 'Manually trigger all scheduled maintenance tasks.', 'fanfiction-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Run Author Demotion Now', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<?php
							// Get demotion statistics
							if ( class_exists( 'Fanfic_Author_Demotion' ) ) {
								$stats = Fanfic_Author_Demotion::get_statistics();
								?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
									<input type="hidden" name="action" value="fanfic_run_demotion_now">
									<?php wp_nonce_field( 'fanfic_run_demotion_now_nonce', 'fanfic_run_demotion_now_nonce' ); ?>
									<?php submit_button( __( 'Run Author Demotion Now', 'fanfiction-manager' ), 'secondary', 'submit', false ); ?>
								</form>
								<p class="description">
									<?php
									printf(
										/* translators: 1: Number of candidates, 2: Total demoted */
										esc_html__( 'Demote authors with 0 published stories to Reader role. Current candidates: %1$d. Total auto-demoted: %2$d', 'fanfiction-manager' ),
										esc_html( $stats['candidates'] ),
										esc_html( $stats['total_demoted'] )
									);
									?>
								</p>
								<?php
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Re-run Setup Wizard', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfic-setup-wizard' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Run Setup Wizard', 'fanfiction-manager' ); ?>
							</a>
							<p class="description">
								<?php esc_html_e( 'Re-run the initial setup wizard to reconfigure base settings, paths, and user roles. This will not delete existing pages or content.', 'fanfiction-manager' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Delete Data', 'fanfiction-manager' ); ?></label>
						</th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
								<input type="hidden" name="action" value="fanfic_delete_data">
								<?php wp_nonce_field( 'fanfic_delete_data_nonce', 'fanfic_delete_data_nonce' ); ?>
								<?php submit_button( __( 'Delete Data', 'fanfiction-manager' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('" . __( 'Are you sure you want to delete all fanfiction data? This cannot be undone.', 'fanfiction-manager' ) . "')" ) ); ?>
							</form>
							<p class="description"><?php esc_html_e( 'Deletes all fanfiction stories, chapters, plugin-created pages, and fanfic tables/options (except users and roles), then resets the plugin to the setup wizard.', 'fanfiction-manager' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('input[name="fanfic_settings[featured_mode]"]').on('change', function() {
				if ($(this).val() === 'automatic') {
					$('#featured-criteria').show();
				} else {
					$('#featured-criteria').hide();
				}
			});

			var $coauthorCheckbox = $('#fanfic_enable_coauthors');
			if ($coauthorCheckbox.length) {
				var wasChecked = $coauthorCheckbox.is(':checked');

				$coauthorCheckbox.on('change', function() {
					if (wasChecked && !$(this).is(':checked')) {
						var confirmed = confirm('<?php echo esc_js( __( 'Warning: Turning off co-authors will remove all co-author access to their stories. Every affected author and co-author will receive a notification. Co-author data will be preserved and can be restored by re-enabling this feature.', 'fanfiction-manager' ) ); ?>');
						if (!confirmed) {
							$(this).prop('checked', true);
						}
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render Email Templates tab
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_email_templates_tab() {
		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Get current active template type
		$active_template = isset( $_GET['template_type'] ) ? sanitize_text_field( wp_unslash( $_GET['template_type'] ) ) : 'new_comment';
		$allowed_types = array( 'new_comment', 'new_chapter', 'new_story' );
		$active_template = in_array( $active_template, $allowed_types, true ) ? $active_template : 'new_comment';

		// Get current user for test email
		$current_user = wp_get_current_user();

		// Template type labels
		$template_labels = array(
			'new_comment'  => __( 'New Comment', 'fanfiction-manager' ),
			'new_chapter'  => __( 'New Chapter', 'fanfiction-manager' ),
			'new_story'    => __( 'New Story', 'fanfiction-manager' ),
		);

		// Get templates from Phase 9 class
		$template_data = Fanfic_Email_Templates::get_template( $active_template );
		$available_vars = Fanfic_Email_Templates::get_available_variables( $active_template );

		?>
		<div class="fanfiction-settings-tab fanfic-email-templates-admin">
			<h2><?php esc_html_e( 'Email Templates', 'fanfiction-manager' ); ?></h2>
			<p><?php esc_html_e( 'Customize email notification templates. Use the available variables (in {{double_braces}}) to personalize messages.', 'fanfiction-manager' ); ?></p>

			<!-- Template Type Navigation -->
			<h3 class="nav-tab-wrapper" style="margin-bottom: 20px;">
				<?php foreach ( $template_labels as $type => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fanfiction-settings&tab=email-templates&template_type=' . $type ) ); ?>"
					   class="nav-tab <?php echo $active_template === $type ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h3>

			<!-- Template Editor Form -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fanfic-email-template-form">
				<input type="hidden" name="action" value="fanfic_save_email_templates">
				<input type="hidden" name="template_type" value="<?php echo esc_attr( $active_template ); ?>">
				<?php wp_nonce_field( 'fanfic_email_templates_nonce', 'fanfic_email_templates_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Subject Line -->
						<tr>
							<th scope="row">
								<label for="email_subject"><?php esc_html_e( 'Email Subject', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<input type="text"
								       id="email_subject"
								       name="email_subject"
								       value="<?php echo esc_attr( $template_data['subject'] ); ?>"
								       class="large-text">
								<p class="description">
									<?php esc_html_e( 'Subject line for this email type. You can use the same variables as in the body.', 'fanfiction-manager' ); ?>
								</p>
							</td>
						</tr>

						<!-- Email Body -->
						<tr>
							<th scope="row">
								<label for="email_body"><?php esc_html_e( 'Email Body (HTML)', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								wp_editor(
									$template_data['body'],
									'email_body',
									array(
										'textarea_name' => 'email_body',
										'textarea_rows' => 20,
										'media_buttons' => false,
										'teeny'         => false,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,forecolor,backcolor',
											'toolbar2' => 'alignleft,aligncenter,alignright,alignjustify,outdent,indent,undo,redo,removeformat,code',
										),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'You can use HTML tags and the variables listed below.', 'fanfiction-manager' ); ?>
								</p>
							</td>
						</tr>

						<!-- Available Variables -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Available Variables', 'fanfiction-manager' ); ?>
							</th>
							<td>
								<div style="background: #f8f8f8; padding: 15px; border-left: 4px solid #0073aa; border-radius: 4px;">
									<p><strong><?php esc_html_e( 'Use these variables in both subject and body:', 'fanfiction-manager' ); ?></strong></p>
									<table class="widefat" style="background: white;">
										<thead>
											<tr>
												<th style="width: 200px;"><?php esc_html_e( 'Variable', 'fanfiction-manager' ); ?></th>
												<th><?php esc_html_e( 'Description', 'fanfiction-manager' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $available_vars as $var => $description ) : ?>
												<tr>
													<td><code>{{<?php echo esc_html( $var ); ?>}}</code></td>
													<td><?php echo esc_html( $description ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Action Buttons -->
				<p class="submit" style="display: flex; gap: 10px; align-items: center;">
					<?php submit_button( __( 'Save Template', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>

					<button type="button"
					        class="button button-secondary"
					        id="fanfic-preview-template">
						<?php esc_html_e( 'Preview Template', 'fanfiction-manager' ); ?>
					</button>

					<button type="button"
					        class="button button-secondary"
					        id="fanfic-send-test-email"
					        data-template-type="<?php echo esc_attr( $active_template ); ?>">
						<?php esc_html_e( 'Send Test Email to Me', 'fanfiction-manager' ); ?>
					</button>

					<button type="button"
					        class="button button-secondary"
					        id="fanfic-reset-template"
					        data-template-type="<?php echo esc_attr( $active_template ); ?>"
					        style="margin-left: auto;">
						<?php esc_html_e( 'Reset to Default', 'fanfiction-manager' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Preview Modal -->
		<div id="fanfic-email-preview-modal" style="display: none;">
			<div id="fanfic-email-preview-backdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;"></div>
			<div id="fanfic-email-preview-container" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 90%; max-width: 800px; max-height: 90vh; overflow: auto; z-index: 100001; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
				<div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
					<h3 style="margin: 0;"><?php esc_html_e( 'Email Preview', 'fanfiction-manager' ); ?></h3>
					<button type="button" id="fanfic-close-preview" style="background: none; border: none; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
				</div>
				<div style="padding: 20px;">
					<div style="margin-bottom: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
						<strong><?php esc_html_e( 'Subject:', 'fanfiction-manager' ); ?></strong>
						<span id="fanfic-preview-subject"></span>
					</div>
					<div id="fanfic-preview-body" style="border: 1px solid #ddd; padding: 20px; background: #fafafa; border-radius: 4px;"></div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Preview Template
			$('#fanfic-preview-template').on('click', function(e) {
				e.preventDefault();

				// Get editor content
				var subject = $('#email_subject').val();
				var body = '';

				// Get content from TinyMCE or textarea
				if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_body')) {
					body = tinyMCE.get('email_body').getContent();
				} else {
					body = $('#email_body').val();
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_preview_email_template',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_preview_email' ) ); ?>',
						template_type: '<?php echo esc_js( $active_template ); ?>',
						subject: subject,
						body: body
					},
					success: function(response) {
						if (response.success) {
							$('#fanfic-preview-subject').text(response.data.subject);
							$('#fanfic-preview-body').html(response.data.body);
							$('#fanfic-email-preview-modal').fadeIn(200);
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Preview failed.', 'fanfiction-manager' ); ?>');
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Preview request failed.', 'fanfiction-manager' ); ?>');
					}
				});
			});

			// Close Preview Modal
			$('#fanfic-close-preview, #fanfic-email-preview-backdrop').on('click', function() {
				$('#fanfic-email-preview-modal').fadeOut(200);
			});

			// Send Test Email
			$('#fanfic-send-test-email').on('click', function(e) {
				e.preventDefault();
				var $button = $(this);
				var templateType = $button.data('template-type');

				if (!confirm('<?php echo esc_js( sprintf( __( 'Send a test email to %s?', 'fanfiction-manager' ), $current_user->user_email ) ); ?>')) {
					return;
				}

				$button.prop('disabled', true).text('<?php esc_html_e( 'Sending...', 'fanfiction-manager' ); ?>');

				// Get editor content
				var subject = $('#email_subject').val();
				var body = '';

				if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email_body')) {
					body = tinyMCE.get('email_body').getContent();
				} else {
					body = $('#email_body').val();
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_send_test_email',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_send_test_email' ) ); ?>',
						template_type: templateType,
						subject: subject,
						body: body
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message || '<?php esc_html_e( 'Test email sent successfully!', 'fanfiction-manager' ); ?>');
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Failed to send test email.', 'fanfiction-manager' ); ?>');
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Test email request failed.', 'fanfiction-manager' ); ?>');
					},
					complete: function() {
						$button.prop('disabled', false).text('<?php esc_html_e( 'Send Test Email to Me', 'fanfiction-manager' ); ?>');
					}
				});
			});

			// Reset Template
			$('#fanfic-reset-template').on('click', function(e) {
				e.preventDefault();
				var $button = $(this);
				var templateType = $button.data('template-type');

				if (!confirm('<?php esc_html_e( 'Are you sure you want to reset this template to default? This cannot be undone.', 'fanfiction-manager' ); ?>')) {
					return;
				}

				$button.prop('disabled', true);

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fanfic_reset_email_template',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_reset_template' ) ); ?>',
						template_type: templateType
					},
					success: function(response) {
						if (response.success) {
							// Reload page to show default template
							window.location.reload();
						} else {
							alert(response.data.message || '<?php esc_html_e( 'Failed to reset template.', 'fanfiction-manager' ); ?>');
							$button.prop('disabled', false);
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Reset request failed.', 'fanfiction-manager' ); ?>');
						$button.prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Custom CSS tab
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_custom_css_tab() {
		$custom_css = get_option( self::CSS_OPTION_NAME, '' );
		?>
		<div class="fanfiction-settings-tab">
			<h2><?php esc_html_e( 'Custom CSS', 'fanfiction-manager' ); ?></h2>
			<p><?php esc_html_e( 'Add custom CSS styles that will be applied only to fanfiction plugin pages.', 'fanfiction-manager' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_save_custom_css">
				<?php wp_nonce_field( 'fanfic_custom_css_nonce', 'fanfic_custom_css_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="custom_css"><?php esc_html_e( 'Custom CSS Code', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<textarea id="custom_css" name="fanfic_custom_css" rows="20" class="large-text code" style="font-family: Consolas, Monaco, monospace;"><?php echo esc_textarea( $custom_css ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Enter your custom CSS here. This CSS will only apply to fanfiction plugin pages. Do not include <style> tags.', 'fanfiction-manager' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Custom CSS', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Page Templates settings tab
	 *
	 * Allows admins to customize templates for viewing stories, chapters, and profiles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_page_templates_tab() {
		// Get current templates
		$story_template = get_option( 'fanfic_shortcode_story_view', self::get_default_story_template() );
		$chapter_template = get_option( 'fanfic_shortcode_chapter_view', self::get_default_chapter_template() );
		$profile_template = get_option( 'fanfic_shortcode_profile_view', self::get_default_profile_template() );
		?>
		<div class="fanfiction-settings-tab">
			<h2><?php esc_html_e( 'Page Templates', 'fanfiction-manager' ); ?></h2>
			<p><?php esc_html_e( 'Customize the templates used to display stories, chapters, and user profiles. Use shortcodes to build your templates.', 'fanfiction-manager' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fanfic_save_page_templates">
				<?php wp_nonce_field( 'fanfic_page_templates_nonce', 'fanfic_page_templates_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Story View Template -->
						<tr>
							<th scope="row">
								<label for="story_view_template"><?php esc_html_e( 'Story View Shortcode Template', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								wp_editor(
									$story_template,
									'story_view_template',
									array(
										'textarea_name' => 'fanfic_shortcode_story_view',
										'textarea_rows' => 15,
										'media_buttons' => false,
										'teeny'         => false,
										'wpautop'       => false,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,forecolor,backcolor,undo,redo,removeformat,code',
											'toolbar2' => '',
										),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Customize how individual stories are displayed. Uses shortcodes to build the layout. Available shortcodes:', 'fanfiction-manager' ); ?>
									<br><code>[fanfic-story-title]</code> <code>[story-author-link]</code> <code>[story-intro]</code> <code>[story-genres]</code>
									<code>[story-fandoms]</code> <code>[story-status]</code> <code>[story-word-count-estimate]</code> <code>[story-chapters]</code> <code>[story-views]</code>
									<code>[story-rating-form]</code> <code>[story-actions]</code> <code>[edit-story-button]</code> <code>[chapters-list]</code>
									<code>[story-chapters-dropdown]</code> <code>[story-featured-image]</code> <code>[story-comments]</code>
								</p>
								<p>
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array('page' => 'fanfiction-settings', 'tab' => 'page-templates', 'reset_template' => 'story'), admin_url( 'admin.php' ) ), 'fanfic_reset_page_template' ) ); ?>" class="button">
										<?php esc_html_e( 'Reset to Default', 'fanfiction-manager' ); ?>
									</a>
								</p>
							</td>
						</tr>

						<!-- Chapter View Template -->
						<tr>
							<th scope="row">
								<label for="chapter_view_template"><?php esc_html_e( 'Chapter View Shortcode Template', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								wp_editor(
									$chapter_template,
									'chapter_view_template',
									array(
										'textarea_name' => 'fanfic_shortcode_chapter_view',
										'textarea_rows' => 15,
										'media_buttons' => false,
										'teeny'         => false,
										'wpautop'       => false,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,forecolor,backcolor,undo,redo,removeformat,code',
											'toolbar2' => '',
										),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Customize how individual chapters are displayed. Uses shortcodes to build the layout. Available shortcodes:', 'fanfiction-manager' ); ?>
									<br><code>[chapter-breadcrumb]</code> <code>[chapter-story]</code> <code>[chapters-nav]</code> <code>[chapter-actions]</code>
									<code>[chapter-rating-form]</code> <code>[chapter-comments]</code>
									<br><?php esc_html_e( 'Note: Chapter content is automatically displayed. Do not add it manually.', 'fanfiction-manager' ); ?>
								</p>
								<p>
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array('page' => 'fanfiction-settings', 'tab' => 'page-templates', 'reset_template' => 'chapter'), admin_url( 'admin.php' ) ), 'fanfic_reset_page_template' ) ); ?>" class="button">
										<?php esc_html_e( 'Reset to Default', 'fanfiction-manager' ); ?>
									</a>
								</p>
							</td>
						</tr>

						<!-- Profile View Template -->
						<tr>
							<th scope="row">
								<label for="profile_view_template"><?php esc_html_e( 'Profile View Shortcode Template', 'fanfiction-manager' ); ?></label>
							</th>
							<td>
								<?php
								wp_editor(
									$profile_template,
									'profile_view_template',
									array(
										'textarea_name' => 'fanfic_shortcode_profile_view',
										'textarea_rows' => 15,
										'media_buttons' => false,
										'teeny'         => false,
										'wpautop'       => false,
										'tinymce'       => array(
											'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,link,unlink,forecolor,backcolor,undo,redo,removeformat,code',
											'toolbar2' => '',
										),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Customize how user profiles are displayed. Uses shortcodes to build the layout. Use {user_id} placeholder in shortcodes. Available shortcodes:', 'fanfiction-manager' ); ?>
									<br><code>[author-display-name]</code> <code>[author-bio]</code> <code>[author-story-list]</code>
									<code>[author-actions]</code> <code>[edit-profile-button]</code> <code>[author-avatar]</code>
									<code>[author-joined-date]</code> <code>[author-story-count]</code>
								</p>
								<p>
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array('page' => 'fanfiction-settings', 'tab' => 'page-templates', 'reset_template' => 'profile'), admin_url( 'admin.php' ) ), 'fanfic_reset_page_template' ) ); ?>" class="button">
										<?php esc_html_e( 'Reset to Default', 'fanfiction-manager' ); ?>
									</a>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Page Templates', 'fanfiction-manager' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Get default story view template
	 *
	 * Loads the default content from template-story-view.php.
	 * The template file contains a function that returns the default user-editable content.
	 *
	 * @since 1.0.0
	 * @return string Default template HTML
	 */
	private static function get_default_story_template() {
		$template_path = FANFIC_PLUGIN_DIR . 'templates/template-story-view.php';
		if ( file_exists( $template_path ) ) {
			// Include the template file to load the function
			require_once $template_path;
			// Call the function that returns default content
			if ( function_exists( 'fanfic_get_default_story_view_template' ) ) {
				return fanfic_get_default_story_view_template();
			}
		}
		return '';
	}

	/**
	 * Get default chapter view template
	 *
	 * Loads the default content from template-chapter-view.php.
	 * The template file contains a function that returns the default user-editable content.
	 *
	 * @since 1.0.0
	 * @return string Default template HTML
	 */
	private static function get_default_chapter_template() {
		$template_path = FANFIC_PLUGIN_DIR . 'templates/template-chapter-view.php';
		if ( file_exists( $template_path ) ) {
			// Include the template file to load the function
			require_once $template_path;
			// Call the function that returns default content
			if ( function_exists( 'fanfic_get_default_chapter_view_template' ) ) {
				return fanfic_get_default_chapter_view_template();
			}
		}
		return '';
	}

	/**
	 * Get default profile view template
	 *
	 * Loads the default content from template-profile-view.php.
	 * The template file contains a function that returns the default user-editable content.
	 *
	 * @since 1.0.0
	 * @return string Default template HTML
	 */
	private static function get_default_profile_template() {
		$template_path = FANFIC_PLUGIN_DIR . 'templates/template-profile-view.php';
		if ( file_exists( $template_path ) ) {
			// Include the template file to load the function
			require_once $template_path;
			// Call the function that returns default content
			if ( function_exists( 'fanfic_get_default_profile_view_template' ) ) {
				return fanfic_get_default_profile_view_template();
			}
		}
		return '';
	}

	/**
	 * Save General Settings handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function save_general_settings() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_general_settings_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_general_settings_nonce'] ) ), 'fanfic_general_settings_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		$old_settings = get_option( self::OPTION_NAME, self::get_default_settings() );
		$was_coauthors_enabled = ! empty( $old_settings['enable_coauthors'] );

		// Get and sanitize settings
		$settings = isset( $_POST['fanfic_settings'] ) ? wp_unslash( $_POST['fanfic_settings'] ) : array();
		$sanitized_settings = self::sanitize_settings( $settings );

		// Update settings
		update_option( self::OPTION_NAME, $sanitized_settings );
		$is_coauthors_enabled = ! empty( $sanitized_settings['enable_coauthors'] );
		self::maybe_dispatch_coauthor_toggle_notifications( $was_coauthors_enabled, $is_coauthors_enabled );

		// Handle reCAPTCHA keys (stored as separate options)
		if ( isset( $_POST['fanfic_recaptcha_site_key'] ) ) {
			$site_key = sanitize_text_field( wp_unslash( $_POST['fanfic_recaptcha_site_key'] ) );
			update_option( 'fanfic_recaptcha_site_key', $site_key );
		}

		if ( isset( $_POST['fanfic_recaptcha_secret_key'] ) ) {
			$secret_key = sanitize_text_field( wp_unslash( $_POST['fanfic_recaptcha_secret_key'] ) );
			update_option( 'fanfic_recaptcha_secret_key', $secret_key );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-settings',
					'tab'     => 'general',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Dispatch co-author feature toggle notifications.
	 *
	 * @since 1.5.3
	 * @param bool $was_enabled Previous toggle state.
	 * @param bool $is_enabled  New toggle state.
	 * @return void
	 */
	private static function maybe_dispatch_coauthor_toggle_notifications( $was_enabled, $is_enabled ) {
		if ( ! class_exists( 'Fanfic_Coauthors' ) ) {
			return;
		}

		if ( $was_enabled && ! $is_enabled ) {
			Fanfic_Coauthors::notify_feature_disabled();
		} elseif ( ! $was_enabled && $is_enabled ) {
			Fanfic_Coauthors::notify_feature_enabled();
		}
	}

	/**
	 * Save Layout Settings handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function save_layout_settings() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_layout_settings_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_layout_settings_nonce'] ) ), 'fanfic_save_layout_settings_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Handle Show Sidebar setting
		if ( isset( $_POST['fanfic_show_sidebar'] ) && '1' === $_POST['fanfic_show_sidebar'] ) {
			update_option( 'fanfic_show_sidebar', '1' );
		} else {
			update_option( 'fanfic_show_sidebar', '0' );
		}

		// Handle Show Breadcrumbs setting
		if ( isset( $_POST['fanfic_show_breadcrumbs'] ) && '1' === $_POST['fanfic_show_breadcrumbs'] ) {
			update_option( 'fanfic_show_breadcrumbs', '1' );
		} else {
			update_option( 'fanfic_show_breadcrumbs', '0' );
		}

		// Handle Content Width setting
		if ( isset( $_POST['fanfic_page_width_mode'] ) ) {
			$width_mode = sanitize_text_field( wp_unslash( $_POST['fanfic_page_width_mode'] ) );

			if ( 'automatic' === $width_mode ) {
				update_option( 'fanfic_page_width', 'automatic' );
			} elseif ( 'custom' === $width_mode && isset( $_POST['fanfic_page_width_value'] ) && isset( $_POST['fanfic_page_width_unit'] ) ) {
				$width_value = absint( $_POST['fanfic_page_width_value'] );
				$width_unit = sanitize_text_field( wp_unslash( $_POST['fanfic_page_width_unit'] ) );

				// Validate unit
				if ( ! in_array( $width_unit, array( 'px', '%' ), true ) ) {
					$width_unit = 'px';
				}

				// Validate percentage (max 100%)
				if ( '%' === $width_unit && $width_value > 100 ) {
					$width_value = 100;
				}

				// Ensure reasonable minimum for pixels
				if ( 'px' === $width_unit && $width_value < 300 ) {
					$width_value = 300;
				}

				// Ensure value is not 0
				if ( $width_value > 0 ) {
					update_option( 'fanfic_page_width', $width_value . $width_unit );
				}
			}
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-layout',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save Email Templates handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function save_email_templates() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_email_templates_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_email_templates_nonce'] ) ), 'fanfic_email_templates_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Get template type
		$template_type = isset( $_POST['template_type'] ) ? sanitize_text_field( wp_unslash( $_POST['template_type'] ) ) : '';
		$allowed_types = array( 'new_comment', 'new_chapter', 'new_story' );

		if ( ! in_array( $template_type, $allowed_types, true ) ) {
			wp_die( __( 'Invalid template type.', 'fanfiction-manager' ) );
		}

		// Get and sanitize subject and body
		$subject = isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '';
		$body = isset( $_POST['email_body'] ) ? wp_kses_post( wp_unslash( $_POST['email_body'] ) ) : '';

		// Save template using Phase 9 class
		$result = Fanfic_Email_Templates::save_template( $template_type, $subject, $body );

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'fanfiction-settings',
					'tab'           => 'email-templates',
					'template_type' => $template_type,
					'updated'       => $result ? 'true' : 'false',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save Custom CSS handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function save_custom_css() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_custom_css_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_custom_css_nonce'] ) ), 'fanfic_custom_css_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Get and sanitize CSS
		$custom_css = isset( $_POST['fanfic_custom_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['fanfic_custom_css'] ) ) : '';

		// Update CSS
		update_option( self::CSS_OPTION_NAME, $custom_css );

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-settings',
					'tab'     => 'custom-css',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save Page Templates handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function save_page_templates() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_page_templates_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_page_templates_nonce'] ) ), 'fanfic_page_templates_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Get and sanitize templates - allow HTML but escape unsafe tags
		$story_template = isset( $_POST['fanfic_shortcode_story_view'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_shortcode_story_view'] ) ) : '';
		$chapter_template = isset( $_POST['fanfic_shortcode_chapter_view'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_shortcode_chapter_view'] ) ) : '';
		$profile_template = isset( $_POST['fanfic_shortcode_profile_view'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_shortcode_profile_view'] ) ) : '';

		// Update templates
		update_option( 'fanfic_shortcode_story_view', $story_template );
		update_option( 'fanfic_shortcode_chapter_view', $chapter_template );
		update_option( 'fanfic_shortcode_profile_view', $profile_template );

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'fanfiction-settings',
					'tab'     => 'page-templates',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Run Cron Now handler
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function run_cron_now() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_run_cron_now_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_run_cron_now_nonce'] ) ), 'fanfic_run_cron_now_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Schedule single event to run immediately
		wp_schedule_single_event( time(), 'fanfic_daily_maintenance' );

		// Spawn cron to run the event
		spawn_cron();

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'fanfiction-settings',
					'tab'        => 'general',
					'cron_run'   => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}


	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function display_admin_notices() {
		// Success message for settings updated
		if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved successfully.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Success message for template reset
		if ( isset( $_GET['template_reset'] ) && 'true' === $_GET['template_reset'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Template reset to default.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Success message for cron run
		if ( isset( $_GET['cron_run'] ) && 'true' === $_GET['cron_run'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Cron tasks have been scheduled to run immediately.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Success message for author demotion
		if ( isset( $_GET['demotion_run'] ) && 'true' === $_GET['demotion_run'] ) {
			$processed = isset( $_GET['demotion_processed'] ) ? absint( $_GET['demotion_processed'] ) : 0;
			$demoted = isset( $_GET['demotion_demoted'] ) ? absint( $_GET['demotion_demoted'] ) : 0;
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: Number of authors processed, 2: Number of authors demoted */
						esc_html__( 'Author demotion completed. Processed %1$d authors, demoted %2$d to Reader role.', 'fanfiction-manager' ),
						esc_html( $processed ),
						esc_html( $demoted )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Error message for author demotion
		if ( isset( $_GET['demotion_run'] ) && 'false' === $_GET['demotion_run'] ) {
			?>
			<div class="notice error-message is-dismissible">
				<p><?php esc_html_e( 'Author demotion failed. Class not found.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Success message for cache cleaned
		if ( isset( $_GET['cache_cleaned'] ) && 'true' === $_GET['cache_cleaned'] ) {
			$cleaned_count = isset( $_GET['cleaned_count'] ) ? absint( $_GET['cleaned_count'] ) : 0;
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: number of items cleaned */
						esc_html__( 'Successfully cleaned up %s expired cache items.', 'fanfiction-manager' ),
						esc_html( number_format_i18n( $cleaned_count ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Success message for homepage settings synced
		if ( isset( $_GET['synced'] ) && '1' === $_GET['synced'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Homepage settings synchronized successfully.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Error message for homepage settings sync failure
		if ( isset( $_GET['sync_failed'] ) && '1' === $_GET['sync_failed'] ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'Failed to synchronize homepage settings. Please check the error log for details.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Success message for page shortcode fixed
		if ( isset( $_GET['shortcode_fixed'] ) && '1' === $_GET['shortcode_fixed'] ) {
			$fixed_page = isset( $_GET['fixed_page'] ) ? sanitize_text_field( wp_unslash( $_GET['fixed_page'] ) ) : '';
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					if ( ! empty( $fixed_page ) ) {
						printf(
							/* translators: %s: page title */
							esc_html__( 'Page "%s" has been restored to default content with required shortcodes.', 'fanfiction-manager' ),
							esc_html( $fixed_page )
						);
					} else {
						esc_html_e( 'Page has been restored to default content with required shortcodes.', 'fanfiction-manager' );
					}
					?>
				</p>
			</div>
			<?php
		}

		// Error message for page shortcode fix
		if ( isset( $_GET['shortcode_fix_error'] ) && '1' === $_GET['shortcode_fix_error'] ) {
			?>
			<div class="notice error-message is-dismissible">
				<p><?php esc_html_e( 'Failed to restore page content. Please try again or contact support.', 'fanfiction-manager' ); ?></p>
			</div>
			<?php
		}

		// Warning for missing reCAPTCHA keys (only show on settings page)
		if ( isset( $_GET['page'] ) && 'fanfiction-settings' === $_GET['page'] ) {
			$site_key = get_option( 'fanfic_recaptcha_site_key', '' );
			$secret_key = get_option( 'fanfic_recaptcha_secret_key', '' );

			if ( empty( $site_key ) || empty( $secret_key ) ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %s: URL to settings page */
							esc_html__( 'Google reCAPTCHA is not configured. Report forms will work but without spam protection. Configure reCAPTCHA keys in %s to enable protection.', 'fanfiction-manager' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=fanfiction-settings&tab=general' ) ) . '#fanfic_recaptcha_site_key">' . esc_html__( 'General Settings', 'fanfiction-manager' ) . '</a>'
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Get an email template
	 *
	 * @since 1.0.0
	 * @param string $template_key Template key
	 * @return string Template content
	 */
	public static function get_email_template( $template_key ) {
		$templates = get_option( self::EMAIL_TEMPLATES_OPTION, self::get_default_email_templates() );
		return isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : '';
	}

	/**
	 * Get custom CSS
	 *
	 * @since 1.0.0
	 * @return string Custom CSS content
	 */
	public static function get_custom_css() {
		return get_option( self::CSS_OPTION_NAME, '' );
	}

	/**
	 * AJAX handler for email template preview
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_preview_email_template() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_preview_email' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// Get template type
		$template_type = isset( $_POST['template_type'] ) ? sanitize_text_field( wp_unslash( $_POST['template_type'] ) ) : '';
		$allowed_types = array( 'new_comment', 'new_chapter', 'new_story' );

		if ( ! in_array( $template_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template type.', 'fanfiction-manager' ) ) );
		}

		// Get subject and body
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		// Get sample variables for preview
		$sample_vars = self::get_sample_template_variables( $template_type );

		// Replace variables in subject
		$preview_subject = $subject;
		foreach ( $sample_vars as $key => $value ) {
			$preview_subject = str_replace( '{{' . $key . '}}', $value, $preview_subject );
		}

		// Replace variables in body
		$preview_body = $body;
		foreach ( $sample_vars as $key => $value ) {
			$preview_body = str_replace( '{{' . $key . '}}', $value, $preview_body );
		}

		wp_send_json_success(
			array(
				'subject' => $preview_subject,
				'body'    => $preview_body,
			)
		);
	}

	/**
	 * AJAX handler for sending test email
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_send_test_email() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_send_test_email' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// Get template type
		$template_type = isset( $_POST['template_type'] ) ? sanitize_text_field( wp_unslash( $_POST['template_type'] ) ) : '';
		$allowed_types = array( 'new_comment', 'new_chapter', 'new_story' );

		if ( ! in_array( $template_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template type.', 'fanfiction-manager' ) ) );
		}

		// Get subject and body
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		// Get current user
		$current_user = wp_get_current_user();

		// Get sample variables for test
		$sample_vars = self::get_sample_template_variables( $template_type );
		$sample_vars['user_name'] = $current_user->display_name;

		// Replace variables in subject
		$test_subject = '[TEST] ' . $subject;
		foreach ( $sample_vars as $key => $value ) {
			$test_subject = str_replace( '{{' . $key . '}}', $value, $test_subject );
		}

		// Replace variables in body
		$test_body = $body;
		foreach ( $sample_vars as $key => $value ) {
			$test_body = str_replace( '{{' . $key . '}}', $value, $test_body );
		}

		// Send email using Phase 9 class
		$result = Fanfic_Email_Sender::send_email( $current_user->user_email, $test_subject, $test_body );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: email address */
						__( 'Test email sent successfully to %s!', 'fanfiction-manager' ),
						$current_user->user_email
					),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to send test email. Please check your email configuration.', 'fanfiction-manager' ),
				)
			);
		}
	}

	/**
	 * AJAX handler for resetting email template to default
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_reset_email_template() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fanfic_reset_template' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		// Get template type
		$template_type = isset( $_POST['template_type'] ) ? sanitize_text_field( wp_unslash( $_POST['template_type'] ) ) : '';
		$allowed_types = array( 'new_comment', 'new_chapter', 'new_story' );

		if ( ! in_array( $template_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template type.', 'fanfiction-manager' ) ) );
		}

		// Reset template using Phase 9 class
		$result = Fanfic_Email_Templates::reset_to_defaults( $template_type );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Template reset to default successfully.', 'fanfiction-manager' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to reset template.', 'fanfiction-manager' ),
				)
			);
		}
	}

	/**
	 * Get sample template variables for preview/testing
	 *
	 * @since 1.0.0
	 * @param string $template_type Template type.
	 * @return array Sample variables.
	 */
	private static function get_sample_template_variables( $template_type ) {
		$common = array(
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'user_name'    => 'John Doe',
			'settings_url' => home_url( '/dashboard?tab=notifications' ),
		);

		$type_specific = array(
			'new_comment' => array(
				'author_name'   => 'Jane Smith',
				'story_title'   => 'Sample Story Title',
				'story_url'     => home_url( '/story/sample-story' ),
				'chapter_title' => 'Chapter 5: The Adventure Begins',
				'chapter_url'   => home_url( '/story/sample-story/chapter-5' ),
				'comment_text'  => 'This is a great chapter! I really enjoyed the character development and can\'t wait to see what happens next.',
			),
			'new_chapter' => array(
				'author_name'   => 'Bob Williams',
				'story_title'   => 'Epic Adventure',
				'story_url'     => home_url( '/story/epic-adventure' ),
				'chapter_title' => 'Chapter 12: The Final Battle',
				'chapter_url'   => home_url( '/story/epic-adventure/chapter-12' ),
			),
			'new_story' => array(
				'author_name' => 'Emma Davis',
				'story_title' => 'A New Beginning',
				'story_url'   => home_url( '/story/a-new-beginning' ),
			),
		);

		$variables = $common;
		if ( isset( $type_specific[ $template_type ] ) ) {
			$variables = array_merge( $variables, $type_specific[ $template_type ] );
		}

		return $variables;
	}

	/**
	 * Render Cache Management tab
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_cache_management_tab() {
		?>
		<div class="fanfiction-settings-tab fanfiction-cache-tab">
			<h2><?php esc_html_e( 'Cache Management', 'fanfiction-manager' ); ?></h2>
			<p><?php esc_html_e( 'Manage transient caches to improve plugin performance. Transient caches are automatically invalidated when content changes.', 'fanfiction-manager' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Clear All Caches', 'fanfiction-manager' ); ?></th>
					<td>
						<button type="button" class="button button-primary" id="ffm-clear-all-cache">
							<?php esc_html_e( 'Clear All Transients', 'fanfiction-manager' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Clears all cached data. Use this if you see stale data after making changes.', 'fanfiction-manager' ); ?>
						</p>
						<div id="ffm-cache-result" class="notice" style="display: none; margin-top: 10px;"></div>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Clean Up Expired Transients', 'fanfiction-manager' ); ?></th>
					<td>
						<button type="button" class="button" id="ffm-cleanup-expired">
							<?php esc_html_e( 'Clean Up Expired', 'fanfiction-manager' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Removes expired transients from the database. Runs automatically daily via cron.', 'fanfiction-manager' ); ?>
						</p>
						<div id="ffm-cleanup-result" class="notice" style="display: none; margin-top: 10px;"></div>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Cache Statistics', 'fanfiction-manager' ); ?></th>
					<td>
						<button type="button" class="button" id="ffm-cache-stats">
							<?php esc_html_e( 'Get Cache Stats', 'fanfiction-manager' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'View information about transients stored in the database.', 'fanfiction-manager' ); ?>
						</p>
						<div id="ffm-stats-result" class="notice" style="display: none; margin-top: 10px;"></div>
					</td>
				</tr>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Clear all caches
			$('#ffm-clear-all-cache').on('click', function() {
				var $button = $(this);
				var $result = $('#ffm-cache-result');

				$button.prop('disabled', true).text('<?php esc_js( _e( 'Clearing...', 'fanfiction-manager' ) ); ?>');
				$result.hide();

				$.post(ajaxurl, {
					action: 'fanfic_clear_all_cache',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_cache_action' ) ); ?>'
				}, function(response) {
					$button.prop('disabled', false).text('<?php esc_js( _e( 'Clear All Transients', 'fanfiction-manager' ) ); ?>');

					if (response.success) {
						$result.removeClass('error-message').addClass('notice-success');
						$result.html('<p>' + response.data.message + '</p>').show();
					} else {
						$result.removeClass('notice-success').addClass('error-message');
						$result.html('<p>' + response.data.message + '</p>').show();
					}
				});
			});

			// Clean up expired transients
			$('#ffm-cleanup-expired').on('click', function() {
				var $button = $(this);
				var $result = $('#ffm-cleanup-result');

				$button.prop('disabled', true).text('<?php esc_js( _e( 'Cleaning...', 'fanfiction-manager' ) ); ?>');
				$result.hide();

				$.post(ajaxurl, {
					action: 'fanfic_cleanup_expired_cache',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_cache_action' ) ); ?>'
				}, function(response) {
					$button.prop('disabled', false).text('<?php esc_js( _e( 'Clean Up Expired', 'fanfiction-manager' ) ); ?>');

					if (response.success) {
						$result.removeClass('error-message').addClass('notice-success');
						$result.html('<p>' + response.data.message + '</p>').show();
					} else {
						$result.removeClass('notice-success').addClass('error-message');
						$result.html('<p>' + response.data.message + '</p>').show();
					}
				});
			});

			// Get cache statistics
			$('#ffm-cache-stats').on('click', function() {
				var $button = $(this);
				var $result = $('#ffm-stats-result');

				$button.prop('disabled', true).text('<?php esc_js( _e( 'Loading...', 'fanfiction-manager' ) ); ?>');
				$result.hide();

				$.post(ajaxurl, {
					action: 'fanfic_get_cache_stats',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_cache_action' ) ); ?>'
				}, function(response) {
					$button.prop('disabled', false).text('<?php esc_js( _e( 'Get Cache Stats', 'fanfiction-manager' ) ); ?>');

					if (response.success) {
						var stats = response.data.stats;
						var html = '<p><strong><?php esc_js( _e( 'Cache Statistics', 'fanfiction-manager' ) ); ?></strong></p>';
						html += '<ul>';
						html += '<li><?php esc_js( _e( 'Total Transients:', 'fanfiction-manager' ) ); ?> ' + stats.total_transients + '</li>';
						html += '<li><?php esc_js( _e( 'Database Size:', 'fanfiction-manager' ) ); ?> ' + stats.db_size + '</li>';
						html += '</ul>';

						$result.removeClass('error-message').addClass('notice-info');
						$result.html(html).show();
					} else {
						$result.removeClass('notice-info').addClass('error-message');
						$result.html('<p>' + response.data.message + '</p>').show();
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for clearing all caches
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_clear_all_cache() {
		check_ajax_referer( 'fanfic_cache_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		if ( class_exists( 'Fanfic_Cache' ) ) {
			$deleted = Fanfic_Cache::clear_all();
			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Successfully cleared %d transients.', 'fanfiction-manager' ),
					$deleted
				)
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Cache class not found.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX handler for cleaning up expired transients
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_cleanup_expired_cache() {
		check_ajax_referer( 'fanfic_cache_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		if ( class_exists( 'Fanfic_Cache' ) ) {
			$deleted = Fanfic_Cache::cleanup_expired();
			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Successfully cleaned up %d expired transients.', 'fanfiction-manager' ),
					$deleted
				)
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Cache class not found.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX handler for getting cache statistics
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_get_cache_stats() {
		check_ajax_referer( 'fanfic_cache_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fanfiction-manager' ) ) );
		}

		global $wpdb;

		// Count total transients
		$total_transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ffm_' ) . '%'
			)
		);

		// Get database size (approximate)
		$db_size_bytes = 0;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) as total_size FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ffm_' ) . '%'
			)
		);

		if ( ! empty( $results ) && isset( $results[0]->total_size ) ) {
			$db_size_bytes = (int) $results[0]->total_size;
		}

		// Format size
		$db_size = size_format( $db_size_bytes, 2 );

		wp_send_json_success( array(
			'stats' => array(
				'total_transients' => $total_transients,
				'db_size'          => $db_size,
			)
		) );
	}

	/**
	 * Run Author Demotion Now handler
	 *
	 * Manually triggers author demotion for all authors with 0 published stories.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function run_demotion_now() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_run_demotion_now_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fanfic_run_demotion_now_nonce'] ) ), 'fanfic_run_demotion_now_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Run manual demotion (no batch limit)
		if ( class_exists( 'Fanfic_Author_Demotion' ) ) {
			$result = Fanfic_Author_Demotion::run_manual();

			// Redirect with success message
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'              => 'fanfiction-settings',
						'tab'               => 'general',
						'demotion_run'      => 'true',
						'demotion_processed' => $result['processed'],
						'demotion_demoted'   => $result['demoted'],
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// If class not found, redirect with error
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'fanfiction-settings',
					'tab'          => 'general',
					'demotion_run' => 'false',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete all posts for a specific post type.
	 *
	 * @since 1.0.0
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	private static function delete_posts_by_type( $post_type ) {
		$max_loops     = 10000;
		$loop          = 0;
		$total_deleted = 0;

		do {
			$loop++;
			$post_ids = get_posts( array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => 50, // Reduced from 200 to 50 for memory efficiency.
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( empty( $post_ids ) ) {
				break;
			}

			// Use direct SQL deletion for better memory efficiency.
			self::hard_delete_posts_by_ids( $post_ids );
			$total_deleted += count( $post_ids );

			// Clean up memory after each batch.
			self::cleanup_memory();

			// Log progress every 10 batches.
			if ( 0 === $loop % 10 ) {
				self::log_delete_data_progress( sprintf( '%s: %d posts deleted', $post_type, $total_deleted ) );
			}
		} while ( $loop < $max_loops );
	}

	/**
	 * Hard delete posts and core relations by IDs.
	 *
	 * Fallback used only when wp_delete_post() is unable to remove a batch.
	 *
	 * @since 1.0.0
	 * @param array<int> $post_ids Post IDs.
	 * @return void
	 */
	private static function hard_delete_posts_by_ids( $post_ids ) {
		global $wpdb;

		$post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		// Process in smaller chunks if the array is very large.
		if ( count( $post_ids ) > 100 ) {
			$chunks = array_chunk( $post_ids, 100 );
			foreach ( $chunks as $chunk ) {
				self::hard_delete_posts_by_ids( $chunk );
			}
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders)",
				$post_ids
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($placeholders)",
				$post_ids
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN (
					SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN ($placeholders)
				)",
				$post_ids
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ($placeholders)",
				$post_ids
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts} WHERE ID IN ($placeholders)",
				$post_ids
			)
		);

		// Clean up memory after SQL operations.
		self::cleanup_memory();
	}

	/**
	 * Clean up memory between operations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function cleanup_memory() {
		// Clear object cache.
		wp_cache_flush();

		// Clear wpdb queries log.
		global $wpdb;
		$wpdb->queries = array();

		// Force garbage collection.
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}

	/**
	 * Log delete-data progress with memory usage for debugging.
	 *
	 * @since 1.0.0
	 * @param string $step Progress step label.
	 * @return void
	 */
	private static function log_delete_data_progress( $step ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'Fanfic Delete Data: %s | mem=%dMB peak=%dMB',
					$step,
					(int) round( memory_get_usage( true ) / 1048576 ),
					(int) round( memory_get_peak_usage( true ) / 1048576 )
				)
			);
		}
	}

	/**
	 * Handle delete data request
	 *
	 * @since 1.0.0
	 */
	/**
	 * Handle homepage settings sync request
	 *
	 * Synchronizes WordPress front page settings with plugin homepage configuration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function sync_homepage_settings() {
		// Verify nonce
		$nonce = isset( $_POST['fanfic_sync_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['fanfic_sync_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'fanfic_sync_homepage_settings_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Log sync attempt
		error_log( '[Fanfic Homepage] Manual sync requested by admin' );

		// Perform sync using homepage state helper
		if ( class_exists( 'Fanfic_Homepage_State' ) ) {
			$result = Fanfic_Homepage_State::sync_homepage_settings();

			if ( $result ) {
				// Success - redirect back with success message
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'fanfiction-settings',
							'tab'     => 'homepage-diagnostics',
							'synced'  => '1',
						),
						admin_url( 'admin.php' )
					)
				);
			} else {
				// Failure - redirect back with error message
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'        => 'fanfiction-settings',
							'tab'         => 'homepage-diagnostics',
							'sync_failed' => '1',
						),
						admin_url( 'admin.php' )
					)
				);
			}
		} else {
			// Class not found - redirect back with error
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'fanfiction-settings',
						'tab'         => 'homepage-diagnostics',
						'sync_failed' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}

	public static function handle_delete_data() {
		$nonce = isset( $_POST['fanfic_delete_data_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['fanfic_delete_data_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'fanfic_delete_data_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'fanfiction-manager' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'fanfiction-manager' ) );
		}

		// Temporarily increase memory limit for deletion.
		$original_memory_limit = ini_get( 'memory_limit' );
		if ( (int) $original_memory_limit < 512 ) {
			@ini_set( 'memory_limit', '512M' );
		}

		// Disable timeout.
		@set_time_limit( 0 );

		self::log_delete_data_progress( 'start' );

		// Delete all fanfiction content posts first so cleanup hooks can run while tables still exist.
		self::log_delete_data_progress( 'deleting_chapters_start' );
		self::delete_posts_by_type( 'fanfiction_chapter' );
		self::log_delete_data_progress( 'chapters_deleted' );

		self::log_delete_data_progress( 'deleting_stories_start' );
		self::delete_posts_by_type( 'fanfiction_story' );
		self::log_delete_data_progress( 'stories_deleted' );

		// Delete system pages by their saved IDs.
		self::log_delete_data_progress( 'deleting_system_pages' );
		$system_page_ids = get_option( 'fanfic_system_page_ids', array() );
		foreach ( (array) $system_page_ids as $page_id ) {
			wp_delete_post( absint( $page_id ), true );
		}
		self::cleanup_memory();
		self::log_delete_data_progress( 'system_pages_deleted' );

		// Explicitly delete the navigation menu and its items.
		self::log_delete_data_progress( 'deleting_nav_menu' );
		$menu_name = 'Fanfiction Automatic Menu';
		$menu = wp_get_nav_menu_object( $menu_name );

		if ( $menu ) {
			// Get the full menu item objects, as 'fields' => 'ids' is buggy in wp_get_nav_menu_items.
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! empty( $menu_items ) ) {
				// Pluck just the IDs from the objects.
				$menu_item_ids = wp_list_pluck( $menu_items, 'ID' );
				// Use the efficient hard delete function to remove menu item posts in bulk.
				self::hard_delete_posts_by_ids( $menu_item_ids );
			}
			// Now that the items are gone, it's safe and fast to delete the menu term.
			wp_delete_nav_menu( $menu->term_id );
		}
		self::cleanup_memory();
		self::log_delete_data_progress( 'nav_menu_deleted' );

		global $wpdb;

		// Drop known plugin tables directly (avoid loading large result sets in memory).
		self::log_delete_data_progress( 'dropping_tables' );
		$table_names = array(
			// Current tables
			'fanfic_interactions',
			'fanfic_reading_progress',
			'fanfic_notifications',
			'fanfic_email_subscriptions',
			'fanfic_moderation_log',
			'fanfic_story_filter_map',
			'fanfic_story_search_index',
			'fanfic_chapter_search_index',
			'fanfic_daily_views',
			'fanfic_story_custom_terms',
			'fanfic_custom_terms',
			'fanfic_custom_taxonomies',
			'fanfic_story_languages',
			'fanfic_languages',
			'fanfic_story_translations',
			'fanfic_story_warnings',
			'fanfic_warnings',
			'fanfic_story_fandoms',
			'fanfic_fandoms',
			// Legacy tables — safe to drop if they somehow exist.
			'fanfic_likes',
			'fanfic_ratings',
			'fanfic_follows',
			'fanfic_reports',
			'fanfic_read_lists',
			'fanfic_subscriptions',
		);

		foreach ( $table_names as $table_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$table_name}`" );
		}
		self::cleanup_memory();
		self::log_delete_data_progress( 'tables_dropped' );

		// Remove terms from built-in plugin taxonomies so wizard rerun starts from an empty state.
		self::log_delete_data_progress( 'deleting_builtin_taxonomy_terms' );
		$builtin_taxonomies = array(
			'fanfiction_genre',
			'fanfiction_status',
		);
		$taxonomies = array_values(
			array_filter(
				$builtin_taxonomies,
				'taxonomy_exists'
			)
		);

		if ( ! empty( $taxonomies ) ) {
			$in_clause = "'" . implode( "','", array_map( 'esc_sql', $taxonomies ) ) . "'";

			// Remove taxonomy relationships first.
			$wpdb->query(
				"DELETE tr
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tt.taxonomy IN ({$in_clause})"
			);

			// Remove term meta for terms used only by plugin taxonomies.
			if ( ! empty( $wpdb->termmeta ) ) {
				$wpdb->query(
					"DELETE tm
					FROM {$wpdb->termmeta} tm
					INNER JOIN {$wpdb->term_taxonomy} tt_plugin
						ON tt_plugin.term_id = tm.term_id
					LEFT JOIN {$wpdb->term_taxonomy} tt_other
						ON tt_other.term_id = tm.term_id
						AND tt_other.taxonomy NOT IN ({$in_clause})
					WHERE tt_plugin.taxonomy IN ({$in_clause})
						AND tt_other.term_taxonomy_id IS NULL"
				);
			}

			// Remove terms used only by plugin taxonomies.
			$wpdb->query(
				"DELETE t
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt_plugin
					ON tt_plugin.term_id = t.term_id
				LEFT JOIN {$wpdb->term_taxonomy} tt_other
					ON tt_other.term_id = t.term_id
					AND tt_other.taxonomy NOT IN ({$in_clause})
				WHERE tt_plugin.taxonomy IN ({$in_clause})
					AND tt_other.term_taxonomy_id IS NULL"
			);

			// Remove taxonomy rows for plugin taxonomies.
			$wpdb->query(
				"DELETE FROM {$wpdb->term_taxonomy}
				WHERE taxonomy IN ({$in_clause})"
			);

			foreach ( $taxonomies as $taxonomy ) {
				clean_taxonomy_cache( $taxonomy );
			}
		}
		self::cleanup_memory();
		self::log_delete_data_progress( 'builtin_taxonomy_terms_deleted' );

		// Delete all options and transients with "fanfic" in the name in one query (memory-safe).
		self::log_delete_data_progress( 'deleting_options' );
		$options_like    = $wpdb->esc_like( 'fanfic' ) . '%';
		$transient_like  = $wpdb->esc_like( '_transient_fanfic' ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$options_like,
				$transient_like
			)
		);
		self::cleanup_memory();
		self::log_delete_data_progress( 'options_deleted' );

		// Keep classification creation/seeding deferred until wizard step 1 explicitly runs.
		// This prevents background admin requests from triggering heavy rebuilds right after deletion.
		set_transient( 'fanfic_skip_classification', true, HOUR_IN_SECONDS * 12 );
		// Enable short-lived post-delete diagnostics to trace any subsequent fatals.
		set_transient( 'fanfic_post_delete_debug', true, HOUR_IN_SECONDS );

		// Restore original memory limit if it completes.
		@ini_set( 'memory_limit', $original_memory_limit );

		self::log_delete_data_progress( 'completed' );

		// Redirect to the wizard
		wp_safe_redirect( admin_url( 'admin.php?page=fanfic-setup-wizard' ) );
		exit;
	}
}
