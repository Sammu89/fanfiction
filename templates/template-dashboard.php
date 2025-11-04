<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<!-- wp:heading -->
			<h1><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'Welcome to your personal dashboard!', 'fanfiction-manager' ); ?></p>
			<!-- /wp:paragraph -->
		</header>

		<section>
			<!-- wp:heading {"level":2} -->
			<h2><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><a href="[url-dashboard]/create-story/" class="button"><?php esc_html_e( 'Create New Story', 'fanfiction-manager' ); ?></a></p>
			<!-- /wp:paragraph -->
		</section>

		<section>
			<!-- wp:heading {"level":2} -->
			<h2><?php esc_html_e( 'Your Stories', 'fanfiction-manager' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>[user-story-list]</p>
			<!-- /wp:paragraph -->
		</section>

		<section>
			<!-- wp:heading {"level":2} -->
			<h2><?php esc_html_e( 'Favorites', 'fanfiction-manager' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>[user-favorites]</p>
			<!-- /wp:paragraph -->
		</section>

		<section>
			<!-- wp:heading {"level":2} -->
			<h2><?php esc_html_e( 'Followed Authors', 'fanfiction-manager' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>[user-followed-authors]</p>
			<!-- /wp:paragraph -->
		</section>

		<section>
			<!-- wp:heading {"level":2} -->
			<h2><?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>[user-notifications]</p>
			<!-- /wp:paragraph -->
		</section>
	</article>
</main>
