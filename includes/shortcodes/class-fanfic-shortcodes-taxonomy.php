<?php
/**
 * Taxonomy Shortcodes Class
 *
 * Handles taxonomy-related shortcodes.
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
	 * Register taxonomy shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'story-taxonomies', array( __CLASS__, 'story_taxonomies' ) );
	}

	/**
	 * Global taxonomy shortcode.
	 *
	 * [story-taxonomies]
	 *
	 * Outputs a combined list of warnings, fandoms, languages, and custom taxonomies.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function story_taxonomies( $atts ) {
		$story_id = Fanfic_Shortcodes::get_current_story_id();
		if ( ! $story_id ) {
			return '';
		}

		$rows = array();

		// Warnings.
		$warnings = array();
		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			$warnings = array();
		} elseif ( class_exists( 'Fanfic_Warnings' ) ) {
			$warnings = Fanfic_Warnings::get_story_warnings( $story_id );
		}
		if ( ! empty( $warnings ) ) {
			$warning_names = array_map(
				function( $warning ) {
					return $warning['name'] ?? '';
				},
				$warnings
			);
			$warning_names = array_filter( array_map( 'trim', $warning_names ) );
			if ( ! empty( $warning_names ) ) {
				$rows[] = array(
					'label'  => __( 'Warnings', 'fanfiction-manager' ),
					'values' => $warning_names,
				);
			}
		}

		// Fandoms.
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			$fandoms = Fanfic_Fandoms::get_story_fandom_labels( $story_id );
			if ( ! empty( $fandoms ) ) {
				$fandom_names = array_map(
					function( $row ) {
						return $row['label'] ?? '';
					},
					$fandoms
				);
				$fandom_names = array_filter( array_map( 'trim', $fandom_names ) );
				if ( ! empty( $fandom_names ) ) {
					$rows[] = array(
						'label'  => __( 'Fandoms', 'fanfiction-manager' ),
						'values' => $fandom_names,
					);
				}
			}
		}

		// Languages.
		if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
			$language = Fanfic_Languages::get_story_language_label( $story_id, true );
			if ( '' !== $language ) {
				$rows[] = array(
					'label'  => __( 'Language', 'fanfiction-manager' ),
					'values' => array( $language ),
				);
			}
		}

		// Custom taxonomies (only searchable ones shown in story view).
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $custom_taxonomies as $taxonomy ) {
				if ( isset( $taxonomy['is_searchable'] ) && empty( $taxonomy['is_searchable'] ) ) {
					continue;
				}
				$terms = Fanfic_Custom_Taxonomies::get_story_terms( $story_id, $taxonomy['id'] );
				if ( empty( $terms ) ) {
					continue;
				}
				$term_names = array_map(
					function( $term ) {
						return $term['name'] ?? '';
					},
					$terms
				);
				$term_names = array_filter( array_map( 'trim', $term_names ) );
				if ( empty( $term_names ) ) {
					continue;
				}
				$rows[] = array(
					'label'  => $taxonomy['name'],
					'values' => $term_names,
				);
			}
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$output = '<div class="fanfic-story-taxonomies-group">';
		foreach ( $rows as $row ) {
			$output .= '<div class="fanfic-story-taxonomy-row">';
			$output .= '<strong>' . esc_html( $row['label'] ) . ':</strong> ';
			$output .= '<span>' . esc_html( implode( ', ', $row['values'] ) ) . '</span>';
			$output .= '</div>';
		}
		$output .= '</div>';

		return $output;
	}
}
