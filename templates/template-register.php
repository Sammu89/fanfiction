<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<!-- wp:heading -->
			<h1><?php esc_html_e( 'Register', 'fanfiction-manager' ); ?></h1>
			<!-- /wp:heading -->
		</header>

		<section role="region" aria-label="<?php esc_attr_e( 'Registration form', 'fanfiction-manager' ); ?>">
			<!-- wp:paragraph -->
			<p>[fanfic-register-form]</p>
			<!-- /wp:paragraph -->
		</section>

		<!-- wp:paragraph -->
		<p><?php
		printf(
			/* translators: %s: login page URL */
			esc_html__( 'Already have an account? %s', 'fanfiction-manager' ),
			'<a href="[url-login]">' . esc_html__( 'Login here', 'fanfiction-manager' ) . '</a>'
		);
		?></p>
		<!-- /wp:paragraph -->
	</article>
</main>
