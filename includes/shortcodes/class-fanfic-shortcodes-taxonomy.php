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
			$warning_items = array();
			foreach ( (array) $warnings as $warning ) {
				$warning_name = trim( sanitize_text_field( (string) ( $warning['name'] ?? '' ) ) );
				if ( '' === $warning_name ) {
					continue;
				}

				$warning_slug = sanitize_title( (string) ( $warning['slug'] ?? '' ) );
				$warning_url = '';
				if ( '' !== $warning_slug && function_exists( 'fanfic_story_card_build_clean_filter_url' ) ) {
					$warning_url = fanfic_story_card_build_clean_filter_url( 'warning', $warning_slug );
				}

				$warning_items[] = array(
					'label' => $warning_name,
					'url'   => $warning_url,
				);
			}

			if ( ! empty( $warning_items ) ) {
				$rows[] = array(
					'label'  => __( 'Warnings', 'fanfiction-manager' ),
					'values' => $warning_items,
				);
			}
		}

		// Fandoms.
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			$fandoms = Fanfic_Fandoms::get_story_fandom_labels( $story_id );
			if ( ! empty( $fandoms ) ) {
				$fandom_items = array();
				foreach ( (array) $fandoms as $fandom ) {
					$fandom_name = trim( sanitize_text_field( (string) ( $fandom['label'] ?? '' ) ) );
					if ( '' === $fandom_name ) {
						continue;
					}

					$fandom_slug = sanitize_title( (string) ( $fandom['slug'] ?? '' ) );
					$fandom_url = '';
					if ( '' !== $fandom_slug && function_exists( 'fanfic_story_card_build_clean_filter_url' ) ) {
						$fandom_url = fanfic_story_card_build_clean_filter_url( 'fandom', $fandom_slug );
					}

					$fandom_items[] = array(
						'label' => $fandom_name,
						'url'   => $fandom_url,
					);
				}

				if ( ! empty( $fandom_items ) ) {
					$rows[] = array(
						'label'  => __( 'Fandoms', 'fanfiction-manager' ),
						'values' => $fandom_items,
					);
				}
			}
		}

		// Languages.
		if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
			$language = Fanfic_Languages::get_story_language( $story_id );
			if ( ! empty( $language['name'] ) ) {
				$language_label = trim( sanitize_text_field( (string) $language['name'] ) );
				if ( ! empty( $language['native_name'] ) && $language['native_name'] !== $language['name'] ) {
					$language_label .= ' (' . trim( sanitize_text_field( (string) $language['native_name'] ) ) . ')';
				}

				$language_slug = sanitize_title( (string) ( $language['slug'] ?? '' ) );
				$language_url = '';
				if ( '' !== $language_slug && function_exists( 'fanfic_story_card_build_clean_filter_url' ) ) {
					$language_url = fanfic_story_card_build_clean_filter_url( 'language', $language_slug );
				}

				$rows[] = array(
					'label'  => __( 'Language', 'fanfiction-manager' ),
					'values' => array(
						array(
							'label' => $language_label,
							'url'   => $language_url,
						),
					),
				);
			}
		}

		// Custom taxonomies (all active taxonomies shown; non-searchable rendered as plain text).
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $custom_taxonomies as $taxonomy ) {
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

				$term_items = array();
				foreach ( (array) $terms as $term ) {
					$term_name = trim( sanitize_text_field( (string) ( $term['name'] ?? '' ) ) );
					if ( '' === $term_name ) {
						continue;
					}

					$term_slug = sanitize_title( (string) ( $term['slug'] ?? '' ) );
					$term_url = '';
					if ( ! empty( $taxonomy['is_searchable'] ) && '' !== $term_slug && function_exists( 'fanfic_story_card_build_clean_filter_url' ) ) {
						$term_url = fanfic_story_card_build_clean_filter_url( 'custom', $term_slug, (string) $taxonomy['slug'] );
					}

					$term_items[] = array(
						'label' => $term_name,
						'url'   => $term_url,
					);
				}

				if ( empty( $term_items ) ) {
					continue;
				}

				$rows[] = array(
					'label'         => $taxonomy['name'],
					'values'        => $term_items,
					'is_searchable' => ! empty( $taxonomy['is_searchable'] ),
				);
			}
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$output = '<div class="fanfic-story-taxonomies-group">';
		foreach ( $rows as $row ) {
			$searchable_class = isset( $row['is_searchable'] ) && ! $row['is_searchable'] ? ' fanfic-taxonomy-not-searchable' : '';
			$value_markup = array();
			foreach ( (array) ( $row['values'] ?? array() ) as $value_item ) {
				$value_label = trim( sanitize_text_field( (string) ( $value_item['label'] ?? '' ) ) );
				if ( '' === $value_label ) {
					continue;
				}

				$value_url = trim( (string) ( $value_item['url'] ?? '' ) );
				if ( '' !== $value_url ) {
					$value_markup[] = '<a href="' . esc_url( $value_url ) . '" class="fanfic-story-taxonomy-link">' . esc_html( $value_label ) . '</a>';
				} else {
					$value_markup[] = '<span class="fanfic-story-taxonomy-value">' . esc_html( $value_label ) . '</span>';
				}
			}

			if ( empty( $value_markup ) ) {
				continue;
			}

			$output .= '<div class="fanfic-story-taxonomy-row' . $searchable_class . '">';
			$output .= '<strong>' . esc_html( $row['label'] ) . ':</strong> ';
			$output .= '<span>' . implode( ', ', $value_markup ) . '</span>';
			$output .= '</div>';
		}
		$output .= '</div>';

		return $output;
	}
}
