<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<div class="fanfic-template-wrapper">
	<main id="fanfic-main-content" class="fanfic-main-content" role="main">
		<article class="fanfic-page-content">
			<header class="fanfic-page-header">
				<h1 class="fanfic-page-title"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></h1>
				<p class="fanfic-page-description"><?php esc_html_e( 'Welcome to your personal dashboard!', 'fanfiction-manager' ); ?></p>
			</header>

			<section class="fanfic-dashboard-section fanfic-quick-actions">
				<h2 class="fanfic-section-title"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
				<div class="fanfic-section-content">
					<a href="[url-dashboard]/create-story/" class="fanfic-button fanfic-button-primary"><?php esc_html_e( 'Create New Story', 'fanfiction-manager' ); ?></a>
				</div>
			</section>

			<section class="fanfic-dashboard-section fanfic-user-stories">
				<h2 class="fanfic-section-title"><?php esc_html_e( 'Your Stories', 'fanfiction-manager' ); ?></h2>
				<div class="fanfic-section-content">
					[user-story-list]
				</div>
			</section>

			<section class="fanfic-dashboard-section fanfic-user-favorites">
				<h2 class="fanfic-section-title"><?php esc_html_e( 'Favorites', 'fanfiction-manager' ); ?></h2>
				<div class="fanfic-section-content">
					[user-favorites]
				</div>
			</section>

			<section class="fanfic-dashboard-section fanfic-user-follows">
				<h2 class="fanfic-section-title"><?php esc_html_e( 'Followed Authors', 'fanfiction-manager' ); ?></h2>
				<div class="fanfic-section-content">
					[user-followed-authors]
				</div>
			</section>

			<section class="fanfic-dashboard-section fanfic-user-notifications">
				<h2 class="fanfic-section-title"><?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?></h2>
				<div class="fanfic-section-content">
					[user-notifications]
				</div>
			</section>

			<section class="fanfic-dashboard-section fanfic-dashboard-popular">
				<h2 class="fanfic-section-title"><?php esc_html_e( 'What\'s Popular', 'fanfiction-manager' ); ?></h2>
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
</div>
