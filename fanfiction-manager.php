<?php
/**
 * Plugin Name: Fanfiction Manager
 * Plugin URI: https://example.com/fanfiction-manager
 * Description: A comprehensive WordPress plugin that transforms WordPress into a dedicated fanfiction publishing platform with frontend-only interface for authors and readers.
 * Version: 1.0.17
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: fanfiction-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'FANFIC_VERSION', '1.0.19' );
define( 'FANFIC_PLUGIN_FILE', __FILE__ );
define( 'FANFIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FANFIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FANFIC_INCLUDES_DIR', FANFIC_PLUGIN_DIR . 'includes/' );

// Load core class
require_once FANFIC_INCLUDES_DIR . 'class-fanfic-core.php';

// Load permalinks check class (must be loaded early for admin notices)
require_once FANFIC_INCLUDES_DIR . 'class-fanfic-permalinks-check.php';

// Load flash messages handler
require_once FANFIC_INCLUDES_DIR . 'class-fanfic-flash-messages.php';

// Initialize the plugin on 'init' hook (WordPress 6.7+ requires textdomain loading at init or later)
function fanfic_init() {
	Fanfic_Core::get_instance();
}
add_action( 'init', 'fanfic_init', 0 );

// Activation hook
register_activation_hook( __FILE__, array( 'Fanfic_Core', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'Fanfic_Core', 'deactivate' ) );
