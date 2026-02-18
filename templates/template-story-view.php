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
		[fanfic-story-title]
		<div class="fanfic-story-meta">
			<span class="fanfic-story-author"><?php esc_html_e( 'by', 'fanfiction-manager' ); ?> [story-author-link]</span>
			<span class="fanfic-story-status">[story-status]</span>
			<span class="fanfic-story-age">[story-age-badge]</span>
		</div>
		[story-translations]
	</header>

	<figure class="fanfic-story-featured-image">
		[fanfic-story-image]
	</figure>

	<section class="fanfic-story-content">
		<div class="fanfic-story-intro">
			<h2><?php esc_html_e( 'Summary', 'fanfiction-manager' ); ?></h2>
			[story-intro]
		</div>

		<div class="fanfic-story-taxonomies">
			<div class="fanfic-story-genres">
				<strong><?php esc_html_e( 'Genres:', 'fanfiction-manager' ); ?></strong> [story-genres]
			</div>
			[story-taxonomies]
		</div>

		<div class="fanfic-story-stats" role="contentinfo" aria-label="<?php esc_attr_e( 'Story statistics', 'fanfiction-manager' ); ?>">
			<span class="fanfic-story-word-count"><strong><?php esc_html_e( 'Words:', 'fanfiction-manager' ); ?></strong> [story-word-count-estimate]</span>
			<span class="fanfic-story-chapters-count"><strong><?php esc_html_e( 'Chapters:', 'fanfiction-manager' ); ?></strong> [story-chapters]</span>
			<span class="fanfic-story-views"><strong><?php esc_html_e( 'Views:', 'fanfiction-manager' ); ?></strong> [story-views]</span>
			<span class="fanfic-story-likes">[fanfiction-story-like-count]</span>
			<span class="fanfic-story-rating">[fanfiction-story-rating]</span>
		</div>
	</section>

	<div class="fanfic-story-actions">
		[fanfiction-action-buttons]
	</div>

	<nav class="fanfic-story-navigation" aria-label="<?php esc_attr_e( 'Chapter navigation', 'fanfiction-manager' ); ?>">
		<div class="fanfic-story-chapters-dropdown">
			[story-chapters-dropdown]
		</div>
	</nav>

	<section class="fanfic-story-chapters-list" aria-labelledby="chapters-heading">
		<h2 id="chapters-heading"><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
		[chapters-list]
	</section>

	<section class="fanfic-story-comments" aria-labelledby="comments-heading">
		<h2 id="comments-heading"><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h2>
		[story-comments]
	</section>
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

// Show a discreet warning if this story is not published
$story_post = get_post();
if ( $story_post && 'fanfiction_story' === $story_post->post_type && 'publish' !== $story_post->post_status ) {
	$status_obj = get_post_status_object( $story_post->post_status );
	$status_label = $status_obj && ! empty( $status_obj->label ) ? $status_obj->label : $story_post->post_status;
	?>
	<div class="fanfic-message fanfic-message-warning fanfic-draft-warning" role="status" aria-live="polite">
		<span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
		<span class="fanfic-message-content">
			<?php
			printf(
				esc_html__( 'This story is not visible to the public because its status is %s.', 'fanfiction-manager' ),
				esc_html( $status_label )
			);
			?>
		</span>
	</div>
	<?php
}

// Render breadcrumb navigation
fanfic_render_breadcrumb( 'view-story', array( 'story_id' => get_the_ID() ) );

// Process shortcodes in the template
echo do_shortcode( $template );
