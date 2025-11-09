<?php
/**
 * Form Helpers Class
 *
 * Shared utility methods for form operations across the plugin.
 *
 * @package FanfictionManager
 * @subpackage Helpers
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Form_Helpers
 *
 * Shared form utility methods.
 *
 * @since 1.0.0
 */
class Fanfic_Form_Helpers {

	/**
	 * Get error message HTML
	 *
	 * @since 1.0.0
	 * @param string $message Error message
	 * @return string HTML error message
	 */
	public static function get_error_message( $message ) {
		return sprintf(
			'<div class="fanfic-message fanfic-error" role="alert">%s</div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Get success message HTML
	 *
	 * @since 1.0.0
	 * @param string $message Success message
	 * @return string HTML success message
	 */
	public static function get_success_message( $message ) {
		return sprintf(
			'<div class="fanfic-message fanfic-success" role="alert">%s</div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Render error display list
	 *
	 * @since 1.0.0
	 * @param array $errors Array of error messages
	 * @return void (echoes HTML)
	 */
	public static function render_error_display( $errors ) {
		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}
		?>
		<div class="fanfic-message fanfic-error" role="alert">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

}
