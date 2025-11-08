<?php
/**
 * Template for single story display
 *
 * This template is used when viewing a single fanfiction story.
 * The template content is loaded from the Page Templates settings tab.
 * Theme developers can override this by copying to their theme directory.
 *
 * Handles action parameters:
 * - ?action=edit → Load template-edit-story.php
 * - ?action=add-chapter → Load template-edit-chapter.php (new chapter)
 * - ?action=create-chapter → Load template-edit-chapter.php (new chapter)
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
		// Load edit story template
		$edit_template = locate_template( array(
			'fanfiction-manager/template-edit-story.php',
			'template-edit-story.php',
		) );

		if ( ! $edit_template ) {
			$edit_template = FANFIC_PLUGIN_DIR . 'templates/template-edit-story.php';
		}

		if ( file_exists( $edit_template ) ) {
			// Set $_GET['story_id'] for the template
			$_GET['story_id'] = get_the_ID();
			include $edit_template;
		}
	} elseif ( 'add-chapter' === $action || 'create-chapter' === $action ) {
		// Load edit chapter template for creating new chapter
		$chapter_template = locate_template( array(
			'fanfiction-manager/template-edit-chapter.php',
			'template-edit-chapter.php',
		) );

		if ( ! $chapter_template ) {
			$chapter_template = FANFIC_PLUGIN_DIR . 'templates/template-edit-chapter.php';
		}

		if ( file_exists( $chapter_template ) ) {
			// Set query parameters for the template
			$_GET['story_id'] = get_the_ID();
			$_GET['chapter_id'] = 0; // 0 means new chapter
			include $chapter_template;
		}
	} else {
		// Default story view
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
	}

endwhile;

get_footer(); ?>
