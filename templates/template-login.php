<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>


	<article class="fanfic-page-content">
		<header class="fanfic-page-header">
			<h1 class="fanfic-page-title"><?php esc_html_e( 'Login', 'fanfiction-manager' ); ?></h1>
		</header>

		<section class="fanfic-content-section" role="region" aria-label="<?php esc_attr_e( 'Login form', 'fanfiction-manager' ); ?>">
			<p>[fanfic-login-form]</p>
		</section>

		<p><?php
		printf(
			/* translators: %s: registration page URL */
			esc_html__( 'Don\'t have an account? %s', 'fanfiction-manager' ),
			'<a href="[url-register]">' . esc_html__( 'Register here', 'fanfiction-manager' ) . '</a>'
		);
		?></p>
	</article>

</div>
