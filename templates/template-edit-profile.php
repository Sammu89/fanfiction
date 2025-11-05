<?php
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-error-notice" role="alert">
		<p>Please log in to edit your profile.</p>
		<p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button">Login</a></p>
	</div>
	<?php
	return;
}
?>

<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<h1><?php esc_html_e( 'Edit Profile', 'fanfiction-manager' ); ?></h1>

			<p><?php esc_html_e( 'Update your author profile information.', 'fanfiction-manager' ); ?></p>
		</header>

		<section role="region" aria-label="<?php esc_attr_e( 'Profile edit form', 'fanfiction-manager' ); ?>">
			<p>[author-edit-profile-form]</p>
		</section>

		<nav aria-label="<?php esc_attr_e( 'Profile navigation', 'fanfiction-manager' ); ?>">
			<p><a href="[url-dashboard]"><?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?></a></p>
		</nav>
	</article>
</main>
