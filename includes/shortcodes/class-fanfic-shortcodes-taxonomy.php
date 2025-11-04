<?php
/**
 * Taxonomy Shortcodes Class
 *
 * Handles all taxonomy-related shortcodes including custom taxonomies.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Taxonomy
 *
 * Taxonomy display shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Taxonomy {

	/**
	 * Register taxonomy shortcodes
	 *
	 * Registers static shortcodes and dynamically creates custom taxonomy shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Register dynamic custom taxonomy shortcodes
		self::register_custom_taxonomy_shortcodes();
	}

	/**
	 * Register custom taxonomy shortcodes dynamically
	 *
	 * Creates shortcodes for all custom taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_custom_taxonomy_shortcodes() {
		// Get all registered taxonomies
		$taxonomies = get_taxonomies( array(), 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			// Only process fanfiction taxonomies
			if ( strpos( $taxonomy->name, 'fanfic_' ) !== 0 ) {
				continue;
			}

			// Skip built-in taxonomies (already handled by story shortcodes)
			if ( in_array( $taxonomy->name, array( 'fanfiction_genre', 'fanfiction_status' ), true ) ) {
				continue;
			}

			// Create shortcode name from taxonomy slug
			$shortcode_name = str_replace( 'fanfic_', 'fanfic-custom-taxo-', $taxonomy->name );

			// Register the shortcode
			add_shortcode( $shortcode_name, function( $atts ) use ( $taxonomy ) {
				return Fanfic_Shortcodes_Taxonomy::custom_taxonomy_terms( $atts, $taxonomy->name );
			} );

			// Register the title shortcode
			$title_shortcode = $shortcode_name . '-title';
			add_shortcode( $title_shortcode, function( $atts ) use ( $taxonomy ) {
				return Fanfic_Shortcodes_Taxonomy::custom_taxonomy_title( $atts, $taxonomy->name );
			} );
		}
	}

	/**
	 * Custom taxonomy terms shortcode
	 *
	 * [fanfic-custom-taxo-{slug}]
	 *
	 * @since 1.0.0
	 * @param array  $atts Shortcode attributes.
	 * @param string $taxonomy_name Taxonomy name.
	 * @return string Taxonomy terms HTML.
	 */
	public static function custom_taxonomy_terms( $atts, $taxonomy_name ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();

		if ( ! $story_id ) {
			return '';
		}

		$terms = get_the_terms( $story_id, $taxonomy_name );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$term_links = array();
		foreach ( $terms as $term ) {
			$term_links[] = sprintf(
				'<a href="%s" class="taxonomy-term-link">%s</a>',
				esc_url( get_term_link( $term ) ),
				esc_html( $term->name )
			);
		}

		return '<span class="custom-taxonomy-terms" role="navigation" aria-label="' . esc_attr__( 'Taxonomy terms', 'fanfiction-manager' ) . '">' . implode( ', ', $term_links ) . '</span>';
	}

	/**
	 * Custom taxonomy title shortcode
	 *
	 * [fanfic-custom-taxo-{slug}-title]
	 *
	 * @since 1.0.0
	 * @param array  $atts Shortcode attributes.
	 * @param string $taxonomy_name Taxonomy name.
	 * @return string Taxonomy label/title.
	 */
	public static function custom_taxonomy_title( $atts, $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( ! $taxonomy ) {
			return '';
		}

		return '<span class="custom-taxonomy-title">' . esc_html( $taxonomy->label ) . '</span>';
	}
}
