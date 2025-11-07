<?php
/**
 * Fanfiction Page Template Handler
 *
 * Manages the plugin's custom page template that integrates with themes.
 * Handles template registration, auto-assignment, widget areas, and customizer controls.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Page_Template
 *
 * Manages custom page template functionality.
 *
 * @since 1.0.0
 */
class Fanfic_Page_Template {

	/**
	 * Template file name
	 *
	 * @var string
	 */
	const TEMPLATE_FILE = 'fanfiction-page-template.php';

	/**
	 * Initialize the page template system
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Clear theme cache to ensure our template appears
		self::clear_theme_cache();

		// Register classic PHP template (not used for FSE themes)
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ), 10, 3 );

		// Load template for pages and virtual pages (classic themes only)
		add_filter( 'template_include', array( __CLASS__, 'load_page_template' ), 99 );

		// Auto-assign template to plugin pages
		add_action( 'save_post_page', array( __CLASS__, 'auto_assign_template' ), 10, 3 );

		// Register widget area
		add_action( 'widgets_init', array( __CLASS__, 'register_widget_area' ) );

		// Register Customizer controls
		add_action( 'customize_register', array( __CLASS__, 'register_customizer_controls' ) );

		// Add body class for easier styling
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );

		// Handle theme switches
		add_action( 'after_switch_theme', array( __CLASS__, 'handle_theme_switch' ) );

		// Admin notices
		add_action( 'admin_notices', array( __CLASS__, 'display_admin_notices' ) );

		// AJAX handler for fixing pages after theme switch
		add_action( 'wp_ajax_fanfic_fix_pages_after_theme_switch', array( __CLASS__, 'ajax_fix_pages_after_theme_switch' ) );
	}

	/**
	 * Register the page template in WordPress
	 *
	 * Makes the template appear in the Page Template dropdown.
	 *
	 * @since 1.0.0
	 * @param array  $templates Array of page templates.
	 * @param object $theme     Theme object.
	 * @param object $post      Post object.
	 * @return array Modified templates array.
	 */
	public static function register_page_template( $templates, $theme = null, $post = null ) {
		$templates[ self::TEMPLATE_FILE ] = __( 'Fanfiction Page Template', 'fanfiction-manager' );
		return $templates;
	}

	/**
	 * Load the page template
	 *
	 * Loads the plugin template for:
	 * 1. Pages that have selected it
	 * 2. Plugin system pages
	 * 3. Virtual dynamic pages (classic themes only)
	 *
	 * @since 1.0.0
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public static function load_page_template( $template ) {
		global $post;

		// For FSE themes, don't intercept template loading
		// Let WordPress handle block templates naturally
		if ( self::is_block_theme() ) {
			return $template;
		}

		// Check if this is a virtual dynamic page (classic themes only)
		if ( isset( $post->fanfic_page_key ) ) {
			return self::locate_template();
		}

		// Check if post exists and is a page
		if ( ! is_singular( 'page' ) || ! $post ) {
			return $template;
		}

		// Check if page has our template assigned
		$page_template = get_post_meta( $post->ID, '_wp_page_template', true );

		if ( self::TEMPLATE_FILE === $page_template ) {
			$plugin_template = self::locate_template();
			if ( $plugin_template ) {
				return $plugin_template;
			}
		}

		// Check if this is a plugin system page
		if ( self::is_plugin_page( $post->ID ) ) {
			$plugin_template = self::locate_template();
			if ( $plugin_template ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Locate the template file
	 *
	 * Checks theme first (for overrides), then plugin directory.
	 *
	 * @since 1.0.0
	 * @return string|false Template path or false if not found.
	 */
	private static function locate_template() {
		// Check if theme has override
		$theme_template = locate_template( array(
			'fanfiction-manager/' . self::TEMPLATE_FILE,
			self::TEMPLATE_FILE,
		) );

		if ( $theme_template ) {
			return $theme_template;
		}

		// Use plugin template
		$plugin_template = FANFIC_PLUGIN_DIR . 'templates/' . self::TEMPLATE_FILE;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Auto-assign template to plugin pages
	 *
	 * When a plugin system page is created or updated, automatically assign our template.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public static function auto_assign_template( $post_id, $post, $update ) {
		// Skip autosave, revisions, and trashed posts
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'trash' === $post->post_status ) {
			return;
		}

		// Only process plugin pages
		if ( ! self::is_plugin_page( $post_id ) ) {
			return;
		}

		// Check if template is already assigned
		$current_template = get_post_meta( $post_id, '_wp_page_template', true );

		// Get the correct template identifier for current theme type
		$template_identifier = self::get_template_identifier();

		// Only assign if no template is set or if it's the default
		if ( empty( $current_template ) || 'default' === $current_template ) {
			update_post_meta( $post_id, '_wp_page_template', $template_identifier );
		}
	}

	/**
	 * Check if a page ID is a plugin system page
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return bool True if plugin page, false otherwise.
	 */
	private static function is_plugin_page( $post_id ) {
		$page_ids = get_option( 'fanfic_system_page_ids', array() );
		return in_array( $post_id, $page_ids, true );
	}

	/**
	 * Register widget area for Fanfiction pages
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_widget_area() {
		register_sidebar(
			array(
				'name'          => __( 'Fanfiction Sidebar', 'fanfiction-manager' ),
				'id'            => 'fanfiction-sidebar',
				'description'   => __( 'Widget area for Fanfiction Manager plugin pages (Dashboard, Create Story, Search, Members, etc.).', 'fanfiction-manager' ),
				'before_widget' => '<section id="%1$s" class="widget fanfiction-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}

	/**
	 * Register Customizer controls
	 *
	 * @since 1.0.0
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register_customizer_controls( $wp_customize ) {
		// Add section for Fanfiction settings
		$wp_customize->add_section(
			'fanfiction_layout',
			array(
				'title'       => __( 'Fanfiction Pages', 'fanfiction-manager' ),
				'description' => __( 'Customize the layout and appearance of Fanfiction plugin pages.', 'fanfiction-manager' ),
				'priority'    => 160,
			)
		);

		// Sidebar visibility setting (stored as global option, not theme_mod)
		$wp_customize->add_setting(
			'fanfic_show_sidebar',
			array(
				'default'           => '1',
				'type'              => 'option',
				'capability'        => 'manage_options',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox_option' ),
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'fanfic_show_sidebar',
			array(
				'label'       => __( 'Show Sidebar on Fanfiction Pages', 'fanfiction-manager' ),
				'description' => __( 'Display the Fanfiction Sidebar widget area on plugin pages.', 'fanfiction-manager' ),
				'section'     => 'fanfiction_layout',
				'type'        => 'checkbox',
			)
		);

		// Page width setting (stored as global option, not theme_mod)
		$wp_customize->add_setting(
			'fanfic_page_width',
			array(
				'default'           => '1200px',
				'type'              => 'option',
				'capability'        => 'manage_options',
				'sanitize_callback' => array( __CLASS__, 'sanitize_page_width' ),
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'fanfic_page_width',
			array(
				'label'       => __( 'Content Width', 'fanfiction-manager' ),
				'description' => __( 'Set content width. Use "automatic" for theme default, or specify width like "1200px" or "90%". Pixels are responsive (max-width), percentages are exact.', 'fanfiction-manager' ),
				'section'     => 'fanfiction_layout',
				'type'        => 'text',
				'input_attrs' => array(
					'placeholder' => '1200px',
				),
			)
		);
	}

	/**
	 * Sanitize checkbox value (for theme_mod - backward compatibility)
	 *
	 * @since 1.0.0
	 * @param bool $checked Checkbox value.
	 * @return bool Sanitized value.
	 */
	public static function sanitize_checkbox( $checked ) {
		return ( isset( $checked ) && true === $checked ) ? true : false;
	}

	/**
	 * Sanitize checkbox value for option storage
	 *
	 * Options store as strings '1' or '0' instead of boolean
	 *
	 * @since 1.0.0
	 * @param mixed $value Checkbox value.
	 * @return string '1' for checked, '0' for unchecked.
	 */
	public static function sanitize_checkbox_option( $value ) {
		return ( ! empty( $value ) && '1' === $value ) ? '1' : '0';
	}

	/**
	 * Sanitize page width value
	 *
	 * Accepts "automatic" or values like "1200px" or "90%"
	 *
	 * @since 1.0.0
	 * @param string $value Width value.
	 * @return string Sanitized width value.
	 */
	public static function sanitize_page_width( $value ) {
		$value = trim( $value );

		// Allow "automatic"
		if ( 'automatic' === strtolower( $value ) ) {
			return 'automatic';
		}

		// Validate format: number + unit (px or %)
		if ( preg_match( '/^(\d+)(px|%)$/i', $value, $matches ) ) {
			$number = absint( $matches[1] );
			$unit = strtolower( $matches[2] );

			// Validate percentage (max 100%)
			if ( '%' === $unit && $number > 100 ) {
				$number = 100;
			}

			// Ensure reasonable minimum
			if ( 'px' === $unit && $number < 300 ) {
				$number = 300;
			}

			return $number . $unit;
		}

		// Invalid format, return default
		return '1200px';
	}

	/**
	 * Add body class for Fanfiction pages
	 *
	 * Makes it easier to style plugin pages with CSS.
	 *
	 * @since 1.0.0
	 * @param array $classes Body classes.
	 * @return array Modified body classes.
	 */
	public static function add_body_class( $classes ) {
		global $post;

		// Check if we're on a plugin page or virtual page
		if ( isset( $post->fanfic_page_key ) ) {
			$classes[] = 'fanfiction-page';
			$classes[] = 'fanfiction-' . sanitize_html_class( $post->fanfic_page_key );
		} elseif ( is_singular( 'page' ) && $post && self::is_plugin_page( $post->ID ) ) {
			$classes[] = 'fanfiction-page';

			// Add specific class based on page slug
			$classes[] = 'fanfiction-' . sanitize_html_class( $post->post_name );
		}

		// Add layout class
		$show_sidebar = get_option( 'fanfic_show_sidebar', '1' );
		if ( in_array( 'fanfiction-page', $classes, true ) ) {
			$classes[] = ( '1' === $show_sidebar ) ? 'fanfiction-with-sidebar' : 'fanfiction-no-sidebar';
		}

		return $classes;
	}

	/**
	 * Get template file path
	 *
	 * @since 1.0.0
	 * @return string Template file path.
	 */
	public static function get_template_file() {
		return FANFIC_PLUGIN_DIR . 'templates/' . self::TEMPLATE_FILE;
	}

	/**
	 * Clear theme cache
	 *
	 * WordPress caches page templates. This clears the cache to ensure our
	 * template appears in the dropdown immediately after plugin activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_theme_cache() {
		// Clear WordPress theme cache
		$theme = wp_get_theme();

		// Method 1: Clear the theme's cache directly
		if ( method_exists( $theme, 'cache_delete' ) ) {
			$theme->cache_delete();
		}

		// Method 2: Clear using wp_cache
		wp_cache_delete( 'theme_files', 'themes' );

		// Method 3: Clear specific cache groups
		$stylesheet = get_stylesheet();
		$template = get_template();

		wp_cache_delete( $stylesheet . '-' . 'page', 'themes' );
		wp_cache_delete( $template . '-' . 'page', 'themes' );

		// Method 4: Force a theme cache flush
		delete_transient( 'theme_roots' );

		// Clear any cached templates from the current theme
		$cache_key = 'page_templates-' . md5( $theme->get_stylesheet() );
		wp_cache_delete( $cache_key, 'themes' );
	}

	/**
	 * Check if the current theme is a block (FSE) theme
	 *
	 * @since 1.0.0
	 * @return bool True if block theme, false if classic theme.
	 */
	public static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Get the template file name
	 *
	 * @since 1.0.0
	 * @return string Template file name.
	 */
	public static function get_template_identifier() {
		return self::TEMPLATE_FILE;
	}

	/**
	 * Handle theme switch
	 *
	 * Detects when the theme changes and updates page templates accordingly.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_theme_switch() {
		// Only run for administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get the previous theme type
		$previous_theme_type = get_option( 'fanfic_theme_type', null );
		$current_theme_type = self::is_block_theme() ? 'block' : 'classic';

		// Store current theme type
		update_option( 'fanfic_theme_type', $current_theme_type );

		// If this is the first time, just store it and return
		if ( null === $previous_theme_type ) {
			return;
		}

		// If theme type hasn't changed, no action needed
		if ( $previous_theme_type === $current_theme_type ) {
			return;
		}

		// Try to auto-fix pages
		$auto_fix_result = self::auto_fix_pages_for_theme_type( $current_theme_type );

		if ( ! $auto_fix_result['success'] ) {
			// Store notice for display
			set_transient( 'fanfic_theme_switch_notice', array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: theme type (Classic or Block) */
					__( 'Your theme has changed to a %s theme. Some pages may need template reassignment.', 'fanfiction-manager' ),
					ucfirst( $current_theme_type )
				),
				'details' => $auto_fix_result['message'],
			), HOUR_IN_SECONDS );
		} else {
			// Store success notice
			set_transient( 'fanfic_theme_switch_notice', array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %s: theme type (Classic or Block) */
					__( 'Successfully updated all Fanfiction pages for the new %s theme.', 'fanfiction-manager' ),
					ucfirst( $current_theme_type )
				),
				'details' => $auto_fix_result['message'],
			), HOUR_IN_SECONDS );
		}

		// Clear theme cache
		self::clear_theme_cache();
	}

	/**
	 * Auto-fix pages for current theme type
	 *
	 * For classic themes, assigns the plugin template.
	 * For FSE themes, sets to 'default' (theme handles it).
	 *
	 * @since 1.0.0
	 * @param string $theme_type Theme type ('classic' or 'block').
	 * @return array Result with success status and message.
	 */
	private static function auto_fix_pages_for_theme_type( $theme_type ) {
		$page_ids = get_option( 'fanfic_system_page_ids', array() );

		if ( empty( $page_ids ) ) {
			return array(
				'success' => false,
				'message' => __( 'No plugin pages found to update.', 'fanfiction-manager' ),
			);
		}

		$updated_count = 0;
		$failed_count = 0;
		// Classic themes use plugin template, FSE themes use default (theme's page template)
		$template_value = ( 'classic' === $theme_type ) ? self::TEMPLATE_FILE : 'default';

		foreach ( $page_ids as $page_id ) {
			if ( ! is_numeric( $page_id ) ) {
				continue;
			}

			$page = get_post( $page_id );
			if ( ! $page || 'page' !== $page->post_type ) {
				$failed_count++;
				continue;
			}

			// Update the page template
			$result = update_post_meta( $page_id, '_wp_page_template', $template_value );

			if ( false !== $result ) {
				$updated_count++;
			} else {
				$failed_count++;
			}
		}

		$success = ( $updated_count > 0 && $failed_count === 0 );

		return array(
			'success' => $success,
			'message' => sprintf(
				/* translators: 1: number of pages updated, 2: number of pages failed */
				__( 'Updated %1$d page(s). Failed: %2$d.', 'fanfiction-manager' ),
				$updated_count,
				$failed_count
			),
			'updated' => $updated_count,
			'failed'  => $failed_count,
		);
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function display_admin_notices() {
		// Check for theme switch notice
		$notice = get_transient( 'fanfic_theme_switch_notice' );

		if ( $notice && is_array( $notice ) ) {
			$type = isset( $notice['type'] ) ? $notice['type'] : 'info';
			$message = isset( $notice['message'] ) ? $notice['message'] : '';
			$details = isset( $notice['details'] ) ? $notice['details'] : '';

			if ( $message ) {
				printf(
					'<div class="notice notice-%s is-dismissible">',
					esc_attr( $type )
				);
				echo '<p><strong>' . esc_html__( 'Fanfiction Manager:', 'fanfiction-manager' ) . '</strong> ' . esc_html( $message ) . '</p>';

				if ( $details ) {
					echo '<p>' . esc_html( $details ) . '</p>';
				}

				// Add fix button if it's a warning
				if ( 'warning' === $type ) {
					echo '<p><button class="button button-primary" id="fanfic-fix-pages-theme-switch">' . esc_html__( 'Fix Pages Now', 'fanfiction-manager' ) . '</button></p>';

					// Add inline script for AJAX
					?>
					<script type="text/javascript">
					jQuery(document).ready(function($) {
						$('#fanfic-fix-pages-theme-switch').on('click', function(e) {
							e.preventDefault();
							var $button = $(this);
							$button.prop('disabled', true).text('<?php echo esc_js( __( 'Fixing...', 'fanfiction-manager' ) ); ?>');

							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'fanfic_fix_pages_after_theme_switch',
									nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_fix_pages_nonce' ) ); ?>'
								},
								success: function(response) {
									if (response.success) {
										$button.closest('.notice').removeClass('notice-warning').addClass('notice-success');
										$button.closest('.notice').find('p:first').html('<strong><?php echo esc_js( __( 'Fanfiction Manager:', 'fanfiction-manager' ) ); ?></strong> ' + response.data.message);
										$button.remove();
									} else {
										alert(response.data.message || '<?php echo esc_js( __( 'Failed to fix pages. Please try again.', 'fanfiction-manager' ) ); ?>');
										$button.prop('disabled', false).text('<?php echo esc_js( __( 'Fix Pages Now', 'fanfiction-manager' ) ); ?>');
									}
								},
								error: function() {
									alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'fanfiction-manager' ) ); ?>');
									$button.prop('disabled', false).text('<?php echo esc_js( __( 'Fix Pages Now', 'fanfiction-manager' ) ); ?>');
								}
							});
						});
					});
					</script>
					<?php
				}

				echo '</div>';

				// Delete transient after displaying
				delete_transient( 'fanfic_theme_switch_notice' );
			}
		}
	}

	/**
	 * AJAX handler for fixing pages after theme switch
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_fix_pages_after_theme_switch() {
		// Verify nonce
		check_ajax_referer( 'fanfic_fix_pages_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'fanfiction-manager' ),
			) );
		}

		// Get current theme type
		$current_theme_type = self::is_block_theme() ? 'block' : 'classic';

		// Attempt to fix pages
		$result = self::auto_fix_pages_for_theme_type( $current_theme_type );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of pages updated */
					__( 'Successfully updated %d page(s) for the new theme.', 'fanfiction-manager' ),
					$result['updated']
				),
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result['message'],
			) );
		}
	}
}
