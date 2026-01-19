<?php
/**
 * Pretty Permalinks Check and Management
 *
 * Handles detection, enforcement, and automatic fixing of WordPress Pretty Permalinks requirement.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Fanfic_Permalinks_Check
 *
 * Manages Pretty Permalinks dependency for the Fanfiction Manager plugin.
 */
class Fanfic_Permalinks_Check {

    /**
     * Singleton instance
     *
     * @var Fanfic_Permalinks_Check
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Fanfic_Permalinks_Check
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Private to enforce singleton
     */
    private function __construct() {
        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notice'));

        // Handle Fix button action
        add_action('admin_init', array($this, 'handle_fix_permalinks'));

        // Add admin styles for the notice
        add_action('admin_head', array($this, 'add_admin_styles'));
    }

    /**
     * Check if Pretty Permalinks are enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function are_permalinks_enabled() {
        $permalink_structure = get_option('permalink_structure');
        return !empty($permalink_structure);
    }

    /**
     * Activation check - Called during plugin activation
     *
     * Allows plugin to activate even if Pretty Permalinks are disabled.
     * The plugin will show admin notices and disable functionality until permalinks are enabled.
     *
     * @since 1.0.0
     */
    public static function check_on_activation() {
        // Allow activation to proceed even if permalinks are disabled
        // The admin notice system will alert users and plugin functionality
        // will be disabled via should_disable_plugin() checks throughout the codebase

        // Set a transient to show the permalink warning immediately after activation
        if (!self::are_permalinks_enabled()) {
            set_transient('fanfic_show_permalink_warning', true, 60);
        }
    }

    /**
     * Show admin notice if permalinks are disabled
     */
    public function show_admin_notice() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Don't show on permalink settings page (they're already there)
        $screen = get_current_screen();
        if ($screen && $screen->id === 'options-permalink') {
            return;
        }

        // Check if this is right after activation
        $just_activated = get_transient('fanfic_show_permalink_warning');
        if ($just_activated) {
            delete_transient('fanfic_show_permalink_warning');
        }

        // Check if permalinks are disabled
        if (!self::are_permalinks_enabled()) {
            $fix_url = wp_nonce_url(
                admin_url('admin.php?action=fanfic_fix_permalinks'),
                'fanfic_fix_permalinks',
                'fanfic_permalinks_nonce'
            );

            ?>
            <div class="notice error-message fanfic-permalinks-notice">
                <p>
                    <strong><?php echo $just_activated ? '⚠️ ' : ''; ?>Fanfiction Manager requires Pretty Permalinks to be enabled to function correctly.</strong>
                </p>
                <p>
                    Your WordPress site is currently using "Plain" permalinks, which are not compatible with this plugin.
                    The plugin functionality is disabled until this is resolved.
                </p>
                <p>
                    <a href="<?php echo esc_url($fix_url); ?>" class="button button-primary fanfic-fix-button">
                        Auto-Fix Permalinks
                    </a>
                    <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" class="button button-secondary">
                        Configure Manually
                    </a>
                </p>
                <p class="description">
                    <strong>Note:</strong> The "Auto-Fix Permalinks" button will set your permalink structure to <code>/%postname%/</code>.
                </p>
            </div>
            <?php
        } elseif (isset($_GET['fanfic_permalinks_fixed']) && $_GET['fanfic_permalinks_fixed'] === '1') {
            // Show success notice after fixing
            ?>
            <div class="notice notice-success is-dismissible">
                <h2>Success!</h2>
                <p>
                    <strong>Pretty Permalinks have been successfully enabled.</strong>
                </p>
                <p>
                    Your permalink structure is now set to <code>/%postname%/</code>.
                    The Fanfiction Manager plugin is now fully active and ready to use.
                </p>
            </div>
            <?php
        }
    }

    /**
     * Handle the Fix Permalinks action
     */
    public function handle_fix_permalinks() {
        // Check if this is our action
        if (!isset($_GET['action']) || $_GET['action'] !== 'fanfic_fix_permalinks') {
            return;
        }

        // Verify nonce
        if (!isset($_GET['fanfic_permalinks_nonce']) ||
            !wp_verify_nonce($_GET['fanfic_permalinks_nonce'], 'fanfic_fix_permalinks')) {
            wp_die('Security check failed. Please try again.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action.');
        }

        // Set permalink structure to /%postname%/ (multilingual-friendly)
        update_option('permalink_structure', '/%postname%/');

        // Flush rewrite rules immediately
        flush_rewrite_rules();

        // Redirect back to admin with success message
        wp_redirect(add_query_arg(
            array('fanfic_permalinks_fixed' => '1'),
            admin_url('index.php')
        ));
        exit;
    }

    /**
     * Add inline styles for the admin notice
     */
    public function add_admin_styles() {
        if (!self::are_permalinks_enabled()) {
            ?>
            <style>
                .fanfic-permalinks-notice {
                    border-left-color: #dc3232;
                }
                .fanfic-permalinks-notice h2 {
                    margin-top: 0;
                    color: #dc3232;
                }
                .fanfic-permalinks-notice .button-primary.fanfic-fix-button {
                    background: #2271b1;
                    border-color: #2271b1;
                }
                .fanfic-permalinks-notice .button-primary.fanfic-fix-button:hover {
                    background: #135e96;
                    border-color: #135e96;
                }
                .fanfic-permalinks-notice .description {
                    margin-top: 10px;
                    font-size: 13px;
                }
            </style>
            <?php
        }
    }

    /**
     * Check if plugin functionality should be disabled
     *
     * Returns true if permalinks are disabled and plugin should not function.
     *
     * @return bool
     */
    public static function should_disable_plugin() {
        return !self::are_permalinks_enabled();
    }
}

// Initialize the permalinks check
Fanfic_Permalinks_Check::get_instance();
