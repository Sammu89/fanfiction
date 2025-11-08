<?php
/**
 * Template for single chapter display
 *
 * This template is used when viewing a single chapter.
 * The template content is loaded from the Page Templates settings tab.
 * Theme developers can override this by copying to their theme directory.
 *
 * Handles action parameters:
 * - ?action=edit → Load template-edit-chapter.php
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

get_header();

while ( have_posts() ) :
	the_post();

	// Check for action parameter
	$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

	if ( 'edit' === $action ) {
		// Load edit chapter template
		$edit_template = locate_template( array(
			'fanfiction-manager/template-edit-chapter.php',
			'template-edit-chapter.php',
		) );

		if ( ! $edit_template ) {
			$edit_template = FANFIC_PLUGIN_DIR . 'templates/template-edit-chapter.php';
		}

		if ( file_exists( $edit_template ) ) {
			// Set query parameters for the template
			$chapter = get_post();
			$_GET['story_id'] = $chapter->post_parent; // Parent story ID
			$_GET['chapter_id'] = get_the_ID(); // Current chapter ID
			include $edit_template;
		}
	} else {
		// Default chapter view
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
	}

endwhile;

get_footer(); ?>
