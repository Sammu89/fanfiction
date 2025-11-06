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

get_header();

while ( have_posts() ) :
	the_post();

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

endwhile;

get_footer(); ?>
