<?php
/**
 * Template for single story display
 *
 * This template contains two parts:
 * 1. Default content function (for admin settings)
 * 2. PHP logic and rendering (for frontend display)
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get default user-editable content template
 *
 * This function returns the default HTML/shortcode template.
 * This content gets saved to the database and is editable by users.
 * This function can be called safely from admin settings.
 *
 * @return string Default template HTML
 */
function fanfic_get_default_story_view_template() {
	ob_start();
	?>
<div class="fanfic-story-single">
	<header class="fanfic-story-header">
		<h1>[story-title]</h1>
		<div class="fanfic-story-meta">
			<span class="fanfic-story-author"><?php esc_html_e( 'by', 'fanfiction-manager' ); ?> [story-author-link]</span>
			<span class="fanfic-story-status">[story-status]</span>
		</div>
	</header>

	<div class="fanfic-story-featured-image">
		[story-featured-image]
	</div>

	<div class="fanfic-story-content">
		<div class="fanfic-story-intro">
			<h2><?php esc_html_e( 'Summary', 'fanfiction-manager' ); ?></h2>
			[story-intro]
		</div>

		<div class="fanfic-story-taxonomies">
			<div class="fanfic-story-genres">
				<strong><?php esc_html_e( 'Genres:', 'fanfiction-manager' ); ?></strong> [story-genres]
			</div>
		</div>

		<div class="fanfic-story-stats">
			<span class="fanfic-story-word-count"><strong><?php esc_html_e( 'Words:', 'fanfiction-manager' ); ?></strong> [story-word-count-estimate]</span>
			<span class="fanfic-story-chapters-count"><strong><?php esc_html_e( 'Chapters:', 'fanfiction-manager' ); ?></strong> [story-chapters]</span>
			<span class="fanfic-story-views"><strong><?php esc_html_e( 'Views:', 'fanfiction-manager' ); ?></strong> [story-views]</span>
			<span class="fanfic-story-rating"><strong><?php esc_html_e( 'Rating:', 'fanfiction-manager' ); ?></strong> [story-rating-form]</span>
		</div>
	</div>

	<div class="fanfic-story-actions">
		[content-actions]
	</div>

	<div class="fanfic-story-navigation">
		<div class="fanfic-story-chapters-dropdown">
			[story-chapters-dropdown]
		</div>
	</div>

	<div class="fanfic-story-chapters-list">
		<h2><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
		[chapters-list]
	</div>

	<div class="fanfic-story-comments">
		[story-comments]
	</div>
</div>
<?php
	return ob_get_clean();
}

// Stop here if we're just loading the function definition (e.g., in admin settings)
// The rest of this file is the actual template rendering logic
if ( ! isset( $fanfic_load_template ) || ! $fanfic_load_template ) {
	return;
}

/**
 * =========================================
 * TEMPLATE RENDERING (Frontend Only)
 * =========================================
 * This section only runs when displaying the story on frontend.
 */

// Load user-customized template from database, or use default
$template = get_option( 'fanfic_shortcode_story_view', '' );

if ( empty( $template ) ) {
	$template = fanfic_get_default_story_view_template();
}

// Process shortcodes in the template
echo do_shortcode( $template );
