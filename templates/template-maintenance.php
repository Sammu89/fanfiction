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
			<h1 class="fanfic-page-title"><?php esc_html_e( 'Maintenance Mode', 'fanfiction-manager' ); ?></h1>
		</header>

		<section class="fanfic-content-section" id="fanfic-main-content" role="alert" aria-live="polite">
			<?php if ( $maintenance_mode || $is_admin ) : ?>
				<div class="fanfic-maintenance-message fanfic-message">
					<div class="fanfic-message-icon" aria-hidden="true">&#128736;</div>
					<div class="fanfic-message-content">
						<h3 class="fanfic-message-title"><?php esc_html_e( 'Site Maintenance', 'fanfiction-manager' ); ?></h3>
						<p class="fanfic-message-text"><?php esc_html_e( 'We are currently performing scheduled maintenance to improve your experience.', 'fanfiction-manager' ); ?></p>

						<?php if ( ! empty( $maintenance_reason ) ) : ?>
							<p class="fanfic-message-reason">
								<strong><?php esc_html_e( 'Reason:', 'fanfiction-manager' ); ?></strong>
								<?php echo esc_html( $maintenance_reason ); ?>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $estimated_time ) ) : ?>
							<p class="fanfic-message-estimate">
								<strong><?php esc_html_e( 'Estimated completion:', 'fanfiction-manager' ); ?></strong>
								<?php echo esc_html( $estimated_time ); ?>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $admin_email ) ) : ?>
							<p class="fanfic-message-contact">
								<?php esc_html_e( 'If you have urgent questions, please contact:', 'fanfiction-manager' ); ?>
								<a href="mailto:<?php echo esc_attr( $admin_email ); ?>"><?php echo esc_html( $admin_email ); ?></a>
							</p>
						<?php endif; ?>

						<?php if ( $is_admin && $maintenance_mode ) : ?>
							<p class="fanfic-message-admin-notice" style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; font-size: 0.9em;">
								<strong><?php esc_html_e( 'Admin Notice:', 'fanfiction-manager' ); ?></strong>
								<?php esc_html_e( 'Maintenance mode is currently active. Regular users cannot access the site. Turn off maintenance mode in Settings > General.', 'fanfiction-manager' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'We are currently performing scheduled maintenance. Please check back soon.', 'fanfiction-manager' ); ?></p>
			<?php endif; ?>
		</section>
	</article>

</div>
