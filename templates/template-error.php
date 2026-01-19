<?php
/**
 * Error Page Template
 *
 * Displays error messages passed via URL parameters or session.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get error message from URL parameter (error code)
$error_message = '';
if ( isset( $_GET['error'] ) ) {
	$error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
	$error_message = fanfic_get_error_message_by_code( $error_code );
}

// Get error message from URL parameter (custom message)
if ( empty( $error_message ) && isset( $_GET['error_message'] ) ) {
	$error_message = sanitize_text_field( wp_unslash( $_GET['error_message'] ) );
}

// Check for session error (if WordPress sessions are available)
if ( empty( $error_message ) && isset( $_SESSION['fanfic_error'] ) ) {
	$error_message = sanitize_text_field( $_SESSION['fanfic_error'] );
	// Clear session error after displaying
	unset( $_SESSION['fanfic_error'] );
}

// Use generic message if no specific error provided
if ( empty( $error_message ) ) {
	$error_message = __( 'Something went wrong. Please try again.', 'fanfiction-manager' );
}

// Get main page URL for back link
$main_page_url = fanfic_get_main_url();
if ( empty( $main_page_url ) ) {
	$main_page_url = home_url( '/' );
}
?>
<section class="fanfic-content-section" role="alert" aria-live="assertive">
	<div class="fanfic-error-message fanfic-info-box">
		<div class="fanfic-info-box-icon" aria-hidden="true">&#9888;</div>
		<div class="fanfic-info-box-content">
			<h2 class="fanfic-info-box-title"><?php esc_html_e( 'Error', 'fanfiction-manager' ); ?></h2>
			<p class="fanfic-info-box-text"><?php echo esc_html( $error_message ); ?></p>
			<p class="fanfic-info-box-action">
				<a href="<?php echo esc_url( $main_page_url ); ?>" class="fanfic-button fanfic-button-primary">
					<?php esc_html_e( 'Go to Main Page', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
	</div>
</section>
