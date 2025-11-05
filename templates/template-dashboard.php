<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<main id="main-content" role="main">
	<article>
		<header>
			<h1><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></h1>

			<p><?php esc_html_e( 'Welcome to your personal dashboard!', 'fanfiction-manager' ); ?></p>
		</header>

		<section>
			<h2><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>

			<p><a href="[url-dashboard]/create-story/" class="button"><?php esc_html_e( 'Create New Story', 'fanfiction-manager' ); ?></a></p>
		</section>

		<section>
			<h2><?php esc_html_e( 'Your Stories', 'fanfiction-manager' ); ?></h2>

			<p>[user-story-list]</p>
		</section>

		<section>
			<h2><?php esc_html_e( 'Favorites', 'fanfiction-manager' ); ?></h2>

			<p>[user-favorites]</p>
		</section>

		<section>
			<h2><?php esc_html_e( 'Followed Authors', 'fanfiction-manager' ); ?></h2>

			<p>[user-followed-authors]</p>
		</section>

		<section>
			<h2><?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?></h2>

			<p>[user-notifications]</p>
		</section>

		<section class="fanfic-dashboard-popular">
			<h2><?php esc_html_e( 'What\'s Popular', 'fanfiction-manager' ); ?></h2>

			<div class="fanfic-popular-container">
				<div class="fanfic-popular-stories">
					<h3><?php esc_html_e( 'Popular Stories This Week', 'fanfiction-manager' ); ?></h3>
					<?php echo do_shortcode( '[most-bookmarked-stories limit="5" timeframe="week"]' ); ?>
				</div>

				<div class="fanfic-popular-authors">
					<h3><?php esc_html_e( 'Trending Authors', 'fanfiction-manager' ); ?></h3>
					<?php echo do_shortcode( '[most-followed-authors limit="5" timeframe="week"]' ); ?>
				</div>
			</div>
		</section>
	</article>
</main>
