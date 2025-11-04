<?php
/**
 * Template for story archive display
 *
 * This template is used for the main story archive page.
 * Theme developers can override this by copying to their theme directory.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

get_header(); ?>

<div class="fanfic-archive">
	<header class="archive-header">
		<h1 class="archive-title">
			<?php esc_html_e( 'Story Archive', 'fanfiction-manager' ); ?>
		</h1>
		<p class="archive-description">
			<?php esc_html_e( 'Browse all published fanfiction stories', 'fanfiction-manager' ); ?>
		</p>
	</header>

	<div class="archive-content">
		[story-list]
	</div>
</div>

<?php get_footer(); ?>
