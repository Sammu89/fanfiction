<?php
/**
 * Template for single story display
 *
 * This template is used when viewing a single fanfiction story.
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
	$template = get_option( 'fanfic_story_view_template', '' );

	// If no custom template, use default from settings class
	if ( empty( $template ) && class_exists( 'Fanfic_Settings' ) ) {
		// Get default via reflection since the method is private
		$reflection = new ReflectionClass( 'Fanfic_Settings' );
		if ( $reflection->hasMethod( 'get_default_story_template' ) ) {
			$method = $reflection->getMethod( 'get_default_story_template' );
			$method->setAccessible( true );
			$template = $method->invoke( null );
		}
	}

	// Process shortcodes in the template
	echo do_shortcode( $template );

endwhile;

get_footer(); ?>
