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
