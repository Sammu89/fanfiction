<?php
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-error-notice" role="alert">
		<p>Please log in to edit your profile.</p>
		<p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">Login</a></p>
	</div>
	<?php
	return;
}
?>

<p><?php esc_html_e( 'Update your author profile information.', 'fanfiction-manager' ); ?></p>

<section class="fanfic-content-section" role="region" aria-label="<?php esc_attr_e( 'Profile edit form', 'fanfiction-manager' ); ?>">
	<?php echo Fanfic_Shortcodes_Author_Forms::render_profile_form(); ?>
</section>

<nav aria-label="<?php esc_attr_e( 'Profile navigation', 'fanfiction-manager' ); ?>">
	<p><a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?></a></p>
</nav>
