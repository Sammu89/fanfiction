<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="fanfic-main-content" class="fanfic-main-content" role="main">
	<article class="fanfic-page-content">
		<header class="fanfic-page-header">
			<h1 class="fanfic-page-title"><?php esc_html_e( 'Register', 'fanfiction-manager' ); ?></h1>
		</header>

		<section class="fanfic-content-section" role="region" aria-label="<?php esc_attr_e( 'Registration form', 'fanfiction-manager' ); ?>">
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
</div>
