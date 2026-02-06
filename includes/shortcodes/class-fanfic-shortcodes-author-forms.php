<?php
/**
 * Author Forms Shortcodes Class
 *
 * Main coordinator class that delegates to specialized handlers for story/chapter/profile operations.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Author_Forms
 *
 * Coordinator for author dashboard forms. Delegates to specialized handler classes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Author_Forms {

	/**
	 * Register author forms shortcodes and handlers
	 *
	 * Loads and registers all specialized handler classes for different domains.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		error_log( 'Fanfic_Shortcodes_Author_Forms::register() called' );

		// Load handler classes
		require_once plugin_dir_path( __FILE__ ) . '../handlers/class-fanfic-story-handler.php';
		require_once plugin_dir_path( __FILE__ ) . '../handlers/class-fanfic-chapter-handler.php';
		require_once plugin_dir_path( __FILE__ ) . '../handlers/class-fanfic-profile-handler.php';

		// Load helper classes
		require_once plugin_dir_path( __FILE__ ) . '../helpers/class-fanfic-form-helpers.php';

		// Register all handlers
		error_log( 'Registering Fanfic_Story_Handler...' );
		Fanfic_Story_Handler::register();
		error_log( 'Registering Fanfic_Chapter_Handler...' );
		Fanfic_Chapter_Handler::register();
		error_log( 'Registering Fanfic_Profile_Handler...' );
		Fanfic_Profile_Handler::register();
		error_log( 'All author form handlers registered.' );
	}

}
