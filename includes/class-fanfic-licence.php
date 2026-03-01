<?php
/**
 * Licence management for fanfiction stories.
 *
 * Provides hardcoded licence definitions, validation, and display helpers.
 * Licence is stored as a single post meta slug per story.
 *
 * @package Fanfiction
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fanfic_Licence {

	/**
	 * Post meta key for storing the licence slug.
	 *
	 * @var string
	 */
	const META_KEY = '_fanfic_licence';

	/**
	 * Default licence slug.
	 *
	 * @var string
	 */
	const DEFAULT_LICENCE = 'all-rights-reserved';

	/**
	 * All valid licence slugs.
	 *
	 * @var array
	 */
	const VALID_SLUGS = array(
		'all-rights-reserved',
		'cc-by',
		'cc-by-sa',
		'cc-by-nc',
		'cc-by-nc-sa',
		'cc-by-nd',
		'cc-by-nc-nd',
		'public-domain',
	);

	/**
	 * Check if the licence feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return class_exists( 'Fanfic_Settings' )
			&& Fanfic_Settings::get_setting( 'enable_licence', false );
	}

	/**
	 * Get all licence definitions with translatable labels and descriptions.
	 *
	 * @return array Associative array of slug => definition.
	 */
	public static function get_licence_definitions() {
		return array(
			'all-rights-reserved' => array(
				'label'       => __( 'All Rights Reserved', 'fanfiction-manager' ),
				'description' => __( 'The author retains all rights. No reuse or modification allowed without permission.', 'fanfiction-manager' ),
				'group'       => 'standalone',
			),
			'cc-by'              => array(
				'label'       => __( 'CC BY', 'fanfiction-manager' ),
				'description' => __( 'Credit required. Reuse and modifications allowed. Commercial use allowed.', 'fanfiction-manager' ),
				'group'       => 'creative-commons',
			),
			'cc-by-sa'           => array(
				'label'       => __( 'CC BY-SA', 'fanfiction-manager' ),
				'description' => __( 'Credit required. Modifications allowed. Must keep same licence. Commercial use allowed.', 'fanfiction-manager' ),
				'group'       => 'creative-commons',
			),
			'cc-by-nc'           => array(
				'label'       => __( 'CC BY-NC', 'fanfiction-manager' ),
				'description' => __( 'Credit required. Modifications allowed. No commercial use.', 'fanfiction-manager' ),
				'group'       => 'creative-commons',
			),
			'cc-by-nc-sa'        => array(
				'label'       => __( 'CC BY-NC-SA', 'fanfiction-manager' ),
				'description' => __( 'Credit required. Modifications allowed. Must keep same licence. No commercial use.', 'fanfiction-manager' ),
				'group'       => 'creative-commons',
			),
			'cc-by-nd'           => array(
				'label'       => __( 'CC BY-ND', 'fanfiction-manager' ),
				'description' => __( 'Credit required. No modifications. Commercial use allowed.', 'fanfiction-manager' ),
				'group'       => 'creative-commons',
			),
			'cc-by-nc-nd'        => array(
				'label'       => __( 'CC BY-NC-ND', 'fanfiction-manager' ),
				'description' => __( 'Credit required. No modifications. No commercial use.', 'fanfiction-manager' ),
				'group'       => 'creative-commons',
			),
			'public-domain'      => array(
				'label'       => __( 'Public Domain / CC0', 'fanfiction-manager' ),
				'description' => __( 'No rights reserved. Anyone can use, modify, or distribute this work for any purpose.', 'fanfiction-manager' ),
				'group'       => 'standalone',
			),
		);
	}

	/**
	 * Get the licence slug for a story.
	 *
	 * @param int $story_id Story post ID.
	 * @return string Licence slug.
	 */
	public static function get_story_licence( $story_id ) {
		$slug = get_post_meta( $story_id, self::META_KEY, true );
		if ( empty( $slug ) || ! in_array( $slug, self::VALID_SLUGS, true ) ) {
			return self::DEFAULT_LICENCE;
		}
		return $slug;
	}

	/**
	 * Save a licence slug for a story.
	 *
	 * @param int    $story_id Story post ID.
	 * @param string $slug     Licence slug.
	 */
	public static function save_story_licence( $story_id, $slug ) {
		if ( ! in_array( $slug, self::VALID_SLUGS, true ) ) {
			$slug = self::DEFAULT_LICENCE;
		}
		update_post_meta( $story_id, self::META_KEY, $slug );
	}

	/**
	 * Get the display label for a licence slug.
	 *
	 * @param string $slug Licence slug.
	 * @return string Translatable label.
	 */
	public static function get_label( $slug ) {
		$definitions = self::get_licence_definitions();
		return isset( $definitions[ $slug ] ) ? $definitions[ $slug ]['label'] : $slug;
	}

	/**
	 * Get the description/tooltip for a licence slug.
	 *
	 * @param string $slug Licence slug.
	 * @return string Translatable description.
	 */
	public static function get_description( $slug ) {
		$definitions = self::get_licence_definitions();
		return isset( $definitions[ $slug ] ) ? $definitions[ $slug ]['description'] : '';
	}

	/**
	 * Resolve CC toggle states to a licence slug.
	 *
	 * @param bool $commercial    Allow commercial use.
	 * @param bool $modifications Allow modifications.
	 * @param bool $share_alike   Require ShareAlike.
	 * @return string CC licence slug.
	 */
	public static function resolve_cc_slug( $commercial, $modifications, $share_alike ) {
		if ( ! $modifications ) {
			return $commercial ? 'cc-by-nd' : 'cc-by-nc-nd';
		}
		if ( $share_alike ) {
			return $commercial ? 'cc-by-sa' : 'cc-by-nc-sa';
		}
		return $commercial ? 'cc-by' : 'cc-by-nc';
	}
}
