<?php
/**
 * Maintenance Page Template
 *
 * Displays maintenance mode information with details from settings.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if maintenance mode is actually enabled
$maintenance_mode = false;
if ( class_exists( 'Fanfic_Settings' ) ) {
	$maintenance_mode = Fanfic_Settings::get_setting( 'maintenance_mode', false );
}

// Only show if maintenance mode is enabled or if we're previewing as admin
$is_admin = current_user_can( 'manage_options' );

// Get maintenance details from options
$estimated_time = get_option( 'fanfic_maintenance_estimated_time', '' );
$maintenance_reason = get_option( 'fanfic_maintenance_reason', '' );
$admin_email = get_option( 'admin_email' );
?>
<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

	<article class="fanfic-page-content">
		<header class="fanfic-page-header">
			<h1 class="fanfic-title fanfic-page-title"><?php esc_html_e( 'Maintenance Mode', 'fanfiction-manager' ); ?></h1>
		</header>

		<section class="fanfic-content-section fanfic-fullpage-message" id="fanfic-main-content" role="alert" aria-live="polite">
			<?php if ( $maintenance_mode || $is_admin ) : ?>
				<div class="fanfic-message fanfic-message-warning fanfic-message-fullpage">
					<span class="fanfic-message-icon" aria-hidden="true">&#128736;</span>
					<span class="fanfic-message-content">
						<strong class="fanfic-message-title"><?php esc_html_e( 'Site Maintenance', 'fanfiction-manager' ); ?></strong>
						<span class="fanfic-message-text"><?php esc_html_e( 'We are currently performing scheduled maintenance to improve your experience.', 'fanfiction-manager' ); ?></span>

						<?php if ( ! empty( $maintenance_reason ) ) : ?>
							<span class="fanfic-message-detail">
								<strong><?php esc_html_e( 'Reason:', 'fanfiction-manager' ); ?></strong>
								<?php echo esc_html( $maintenance_reason ); ?>
							</span>
						<?php endif; ?>

						<?php if ( ! empty( $estimated_time ) ) : ?>
							<span class="fanfic-message-detail">
								<strong><?php esc_html_e( 'Estimated completion:', 'fanfiction-manager' ); ?></strong>
								<?php echo esc_html( $estimated_time ); ?>
							</span>
						<?php endif; ?>

						<?php if ( ! empty( $admin_email ) ) : ?>
							<span class="fanfic-message-detail">
								<?php esc_html_e( 'If you have urgent questions, please contact:', 'fanfiction-manager' ); ?>
								<a href="mailto:<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_html( $admin_email ); ?></a>
							</span>
						<?php endif; ?>

						<?php if ( $is_admin && $maintenance_mode ) : ?>
							<span class="fanfic-message-admin-notice">
								<strong><?php esc_html_e( 'Admin Notice:', 'fanfiction-manager' ); ?></strong>
								<?php esc_html_e( 'Maintenance mode is currently active. Regular users cannot access the site. Turn off maintenance mode in Settings > General.', 'fanfiction-manager' ); ?>
							</span>
						<?php endif; ?>
					</span>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'We are currently performing scheduled maintenance. Please check back soon.', 'fanfiction-manager' ); ?></p>
			<?php endif; ?>
		</section>
	</article>

</div>
