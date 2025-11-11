<?php
/**
 * Template for single chapter display
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
function fanfic_get_default_chapter_view_template() {
	ob_start();
	?>
<!-- Breadcrumb navigation for context -->
[fanfic-breadcrumbs]

<!-- Chapter header with hierarchical titles -->
<header class="fanfic-chapter-header">
	<!-- Story title as primary heading (parent context) -->
	<h1 class="fanfic-story-title">[fanfic-story-title]</h1>

	<!-- Chapter title as secondary heading -->
	<h2 class="fanfic-chapter-title">[fanfic-chapter-title]</h2>

	<!-- Meta information (dates) -->
	<div class="fanfic-chapter-meta">
		<?php esc_html_e( 'Published:', 'fanfiction-manager' ); ?> [fanfic-chapter-published]
		<?php esc_html_e( 'Updated:', 'fanfiction-manager' ); ?> [fanfic-chapter-updated]
	</div>
</header>

<!-- Main chapter content -->
<div class="fanfic-chapter-content" itemprop="text">
	[fanfic-chapter-content]
</div>

<!-- Visual separator -->
<hr class="fanfic-content-separator" aria-hidden="true">

<!-- Action buttons (edit, bookmark, share, report) -->
[fanfic-content-actions]

<!-- Rating section -->
<section class="fanfic-chapter-rating" aria-labelledby="rating-heading">
	<h3 id="rating-heading"><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
	[chapter-rating-form]
</section>

<!-- Chapter navigation (previous/next) -->
<nav class="fanfic-chapter-navigation" aria-label="<?php esc_attr_e( 'Chapter navigation', 'fanfiction-manager' ); ?>">
	[chapters-nav]
</nav>

<!-- Comments section -->
<section class="fanfic-chapter-comments" aria-labelledby="comments-heading">
	<h3 id="comments-heading"><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h3>
	[chapter-comments]
</section>
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
 * This section only runs when displaying the chapter on frontend.
 * It handles filters, permissions, and rendering.
 *
 * Note: This template is loaded through fanfiction-page-template.php,
 * so get_header() and get_footer() are NOT needed here.
 */

// Check if parent story is draft and user has permission to view
$chapter_post = get_post();
if ( $chapter_post && 'fanfiction_chapter' === $chapter_post->post_type ) {
	$story_id = $chapter_post->post_parent;

	if ( $story_id ) {
		$story = get_post( $story_id );

		// If story is draft, check if user has permission
		if ( $story && 'draft' === $story->post_status ) {
			// Check if user can edit this story (uses your custom role permissions)
			if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
				// Show access denied message using your template style
				?>
				<div class="fanfic-content-wrapper">
					<div class="fanfic-error-notice" role="alert" aria-live="assertive">
						<h1><?php esc_html_e( 'Access Denied', 'fanfiction-manager' ); ?></h1>
						<p><?php esc_html_e( 'This chapter is part of a story that is currently in draft status and not publicly available.', 'fanfiction-manager' ); ?></p>
						<p>
							<a href="<?php echo esc_url( home_url( '/fanfiction/' ) ); ?>" class="fanfic-button fanfic-button-primary">
								<?php esc_html_e( 'Back to Stories', 'fanfiction-manager' ); ?>
							</a>
						</p>
					</div>
				</div>
				<?php
				return;
			}
		}
	}
}

// Load user-customized template from database, or use default
$template = get_option( 'fanfic_shortcode_chapter_view', '' );

if ( empty( $template ) ) {
	$template = fanfic_get_default_chapter_view_template();
}

// Process shortcodes in the template
echo do_shortcode( $template );
