<?php
/**
 * Template for single chapter display
 *
 * This template is used when viewing a single chapter.
 * The template content is loaded from the Page Templates settings tab.
 * Theme developers can override this by copying to their theme directory.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

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
				get_footer();
				return;
			}
		}
	}
}

// Get custom template from settings
$template = get_option( 'fanfic_chapter_view_template', '' );

// If no custom template, use default from settings class
if ( empty( $template ) && class_exists( 'Fanfic_Settings' ) ) {
	// Get default via reflection since the method is private
	$reflection = new ReflectionClass( 'Fanfic_Settings' );
	if ( $reflection->hasMethod( 'get_default_chapter_template' ) ) {
		$method = $reflection->getMethod( 'get_default_chapter_template' );
		$method->setAccessible( true );
		$template = $method->invoke( null );
	}
}

// Get chapter content
ob_start();
the_content();
$chapter_content = ob_get_clean();

// Replace the comment placeholder with actual content
$template = str_replace( '<!-- Chapter content is automatically displayed here -->', $chapter_content, $template );

// Process shortcodes in the template
echo do_shortcode( $template );

get_footer();
