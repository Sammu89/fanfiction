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
	$enable_likes = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_likes', true ) : true;
	$enable_comments = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_comments', true ) : true;
	ob_start();
	?>
<div class="fanfic-story-single">
	<header class="fanfic-story-header">
		[fanfic-story-title with-badge]
		<div class="fanfic-story-meta-details">
			<div class="fanfic-story-meta-details-left">[story-genres-pills]</div>
			<div class="fanfic-story-meta-details-right">[story-language] [story-translations]</div>
		</div>
	</header>

	<div class="fanfic-story-media-grid fanfic-story-media-grid--portrait" data-fanfic-story-media-grid>
		<figure class="fanfic-story-featured-image" data-fanfic-story-featured-image>[fanfic-story-image]</figure>

		<div class="fanfic-story-stats" role="contentinfo" aria-label="<?php esc_attr_e( 'Story statistics', 'fanfiction-manager' ); ?>">
			<div class="fanfic-story-metric fanfic-story-metric-views">
				<span class="fanfic-story-metric-value">[story-views]</span>
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
			</div>
			<div class="fanfic-story-metric fanfic-story-metric-chapters">
				<span class="dashicons dashicons-text" aria-hidden="true"></span>
				<span class="fanfic-story-metric-value">[story-chapters]</span>
				<span class="fanfic-story-metric-label"><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></span>
			</div>
			<div class="fanfic-story-metric fanfic-story-metric-words">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<span class="fanfic-story-metric-value">[story-word-count-estimate]</span>
				<span class="fanfic-story-metric-label"><?php esc_html_e( 'Words', 'fanfiction-manager' ); ?></span>
			</div>
			<?php if ( $enable_likes ) : ?>
				<div class="fanfic-story-metric fanfic-story-metric-likes">
					<span class="fanfic-story-metric-value">[story-likes]</span>
					<span class="dashicons dashicons-thumbs-up" aria-hidden="true"></span>
				</div>
			<?php endif; ?>
			<div class="fanfic-story-metric fanfic-story-metric-rating">
				[story-rating-display]
			</div>
		</div>
	</div>

	<section class="fanfic-story-content">
		<div class="fanfic-story-intro">
			[story-intro]
			<div class="fanfic-story-taxonomies">
				[story-taxonomies]
			</div>
			<div class="fanfic-story-tags-row">
				[story-visible-tags show_label="false" format="pills"]
			</div>
		</div>
	</section>

	<section class="fanfic-story-chapters-list" aria-labelledby="chapters-heading">
		<h2 id="chapters-heading"><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
		[chapters-list]
	</section>

	<div class="fanfic-story-actions">
		[fanfiction-action-buttons]
	</div>

	<?php if ( $enable_comments ) : ?>
		<section class="fanfic-story-comments" aria-labelledby="comments-heading">
			[story-comments]
		</section>
	<?php endif; ?>
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

$enable_likes = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_likes', true ) : true;
$enable_comments = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_comments', true ) : true;

if ( ! $enable_likes ) {
	$template = preg_replace( '/<div[^>]*fanfic-story-metric-likes[^>]*>.*?<\/div>/is', '', (string) $template );
	$template = str_replace( '[story-likes]', '', (string) $template );
}

if ( ! $enable_comments ) {
	$template = preg_replace( '/<section[^>]*fanfic-story-comments[^>]*>.*?<\/section>/is', '', (string) $template );
	$template = str_replace( '[story-comments]', '', (string) $template );
}

// Show a discreet warning if this story is not published
// Show a discreet warning if this story is not published
$story_post = get_post();

if ( $story_post && 'fanfiction_story' === $story_post->post_type && 'publish' !== $story_post->post_status ) {
	?>
	<div class="fanfic-message fanfic-message-warning fanfic-draft-warning" role="status" aria-live="polite">
		<span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'This story is hidden from the public.', 'fanfiction-manager' ); ?>
		</span>
	</div>
	<?php
}

// Render breadcrumb navigation
fanfic_render_breadcrumb( 'view-story', array( 'story_id' => get_the_ID() ) );

// Process shortcodes in the template
echo do_shortcode( $template );
