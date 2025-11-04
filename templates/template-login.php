<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<!-- wp:heading -->
			<h1><?php esc_html_e( 'Login', 'fanfiction-manager' ); ?></h1>
			<!-- /wp:heading -->
		</header>

		<section role="region" aria-label="<?php esc_attr_e( 'Login form', 'fanfiction-manager' ); ?>">
			<!-- wp:paragraph -->
			<p>[fanfic-login-form]</p>
			<!-- /wp:paragraph -->
		</section>

		<!-- wp:paragraph -->
		<p><?php
		printf(
			/* translators: %s: registration page URL */
			esc_html__( 'Don\'t have an account? %s', 'fanfiction-manager' ),
			'<a href="[url-register]">' . esc_html__( 'Register here', 'fanfiction-manager' ) . '</a>'
		);
		?></p>
		<!-- /wp:paragraph -->
	</article>
</main>
