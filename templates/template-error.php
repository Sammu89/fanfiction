<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<!-- wp:heading -->
			<h1><?php esc_html_e( 'Error', 'fanfiction-manager' ); ?></h1>
			<!-- /wp:heading -->
		</header>

		<section role="alert" aria-live="assertive">
			<!-- wp:paragraph -->
			<p>[fanfic-error-message]</p>
			<!-- /wp:paragraph -->
		</section>

		<nav aria-label="<?php esc_attr_e( 'Error page navigation', 'fanfiction-manager' ); ?>">
			<!-- wp:paragraph -->
			<p><a href="[url-parent]"><?php esc_html_e( 'Go to Homepage', 'fanfiction-manager' ); ?></a></p>
			<!-- /wp:paragraph -->
		</nav>
	</article>
</main>
