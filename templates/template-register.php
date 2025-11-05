<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<h1><?php esc_html_e( 'Register', 'fanfiction-manager' ); ?></h1>
		</header>

		<section role="region" aria-label="<?php esc_attr_e( 'Registration form', 'fanfiction-manager' ); ?>">
			<p>[fanfic-register-form]</p>
		</section>

		<p><?php
		printf(
			/* translators: %s: login page URL */
			esc_html__( 'Already have an account? %s', 'fanfiction-manager' ),
			'<a href="[url-login]">' . esc_html__( 'Login here', 'fanfiction-manager' ) . '</a>'
		);
		?></p>
	</article>
</main>
