<?php
/**
 * Warnings Class
 *
 * Handles warning definitions, story relations, and age rating logic.
 * Mirrors the fandoms pattern for consistency.
 *
 * @package Fanfiction_Manager
 * @since 1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Warnings
 *
 * @since 1.2.0
 */
class Fanfic_Warnings {

	/**
	 * Maximum number of warnings per story
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const MAX_WARNINGS = 10;

	/**
	 * Valid age rating values
	 *
	 * @since 1.2.0
	 * @var array
	 */
	const VALID_AGES = array( 'PG', '13+', '16+', '18+' );

	/**
	 * Initialize warnings feature
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_seed_warnings' ), 12 );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_relations' ) );
	}

	/**
	 * Seed default warnings if table is empty
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function maybe_seed_warnings() {
		if ( ! self::tables_ready() ) {
			return;
		}

		if ( self::has_warnings() ) {
			return;
		}

		self::seed_default_warnings();
	}

	/**
	 * Check if warnings table exists and is ready
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private static function tables_ready() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Check if warnings table has any data
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private static function has_warnings() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return $count > 0;
	}

	/**
	 * Seed default warnings into the database
	 *
	 * @since 1.2.0
	 * @return int Number of warnings inserted
	 */
	public static function seed_default_warnings() {
		if ( ! self::tables_ready() ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		// Default warnings as defined in requirements
		$warnings = array(
			array(
				'slug'             => 'graphic_violence',
				'name'             => 'Graphic Violence',
				'min_age'          => '16+',
				'description'      => 'Detailed, bloody, or intense depictions of fighting or injury',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'character_death',
				'name'             => 'Character Death',
				'min_age'          => '13+',
				'description'      => 'Death of a major or important character (can be emotional)',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'sexual_violence',
				'name'             => 'Sexual Violence',
				'min_age'          => '18+',
				'description'      => 'Rape, sexual assault, or coercion (non-consensual)',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'underage_sexual',
				'name'             => 'Underage (sexual content)',
				'min_age'          => '18+',
				'description'      => 'Sexual activity involving characters under 18',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'incest',
				'name'             => 'Incest',
				'min_age'          => '18+',
				'description'      => 'Sexual content between family members',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'non_explicit_sexual',
				'name'             => 'Non-explicit Sexual Content',
				'min_age'          => '13+',
				'description'      => 'Implied or non-explicit sexual scenes: fade-to-black, heavy kissing and touching, sensuality without graphic detail',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'explicit_sexual',
				'name'             => 'Explicit Sexual Descriptions',
				'min_age'          => '16+',
				'description'      => 'Detailed, graphic descriptions of sexual acts (not necessarily pornographic)',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'pornographic',
				'name'             => 'Pornographic Sexual Content',
				'min_age'          => '18+',
				'description'      => 'Focus on explicit sex as main purpose, highly detailed and erotic',
				'is_sexual'        => 1,
				'is_pornographic'  => 1,
			),
			array(
				'slug'             => 'self_harm',
				'name'             => 'Self-Harm',
				'min_age'          => '16+',
				'description'      => 'Depictions or discussions of cutting, burning, etc.',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'suicide',
				'name'             => 'Suicide',
				'min_age'          => '16+',
				'description'      => 'Suicide attempts, completion, or heavy ideation',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'abuse',
				'name'             => 'Abuse',
				'min_age'          => '16+',
				'description'      => 'Physical, emotional, or psychological domestic/family abuse',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'substance_abuse',
				'name'             => 'Substance Abuse',
				'min_age'          => '16+',
				'description'      => 'Heavy or addictive drug or alcohol use, addiction portrayal',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'eating_disorders',
				'name'             => 'Eating Disorders',
				'min_age'          => '16+',
				'description'      => 'Anorexia, bulimia, binge eating, or body dysmorphia',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'miscarriage_abortion',
				'name'             => 'Miscarriage / Abortion',
				'min_age'          => '16+',
				'description'      => 'Detailed pregnancy loss, termination, or traumatic birth',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'torture',
				'name'             => 'Torture',
				'min_age'          => '18+',
				'description'      => 'Prolonged, sadistic infliction of pain or injury',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'blood_gore',
				'name'             => 'Blood / Gore',
				'min_age'          => '16+',
				'description'      => 'Extreme blood, mutilation, or detailed injury descriptions',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'animal_cruelty',
				'name'             => 'Animal Cruelty',
				'min_age'          => '16+',
				'description'      => 'Abuse, killing, or suffering of animals',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'homophobia',
				'name'             => 'Homophobia',
				'min_age'          => '16+',
				'description'      => 'Depictions of hate speech, discrimination, or slurs',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
		);

		$inserted = 0;

		foreach ( $warnings as $warning ) {
			$result = $wpdb->insert(
				$table,
				array(
					'slug'            => $warning['slug'],
					'name'            => $warning['name'],
					'min_age'         => $warning['min_age'],
					'description'     => $warning['description'],
					'is_sexual'       => $warning['is_sexual'],
					'is_pornographic' => $warning['is_pornographic'],
					'enabled'         => 1,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
			);

			if ( $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Get all warnings (optionally only enabled)
	 *
	 * @since 1.2.0
	 * @param bool $enabled_only Whether to return only enabled warnings.
	 * @return array Array of warning objects
	 */
	public static function get_all( $enabled_only = true ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		$where = $enabled_only ? 'WHERE enabled = 1' : '';

		$results = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY name ASC",
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get warning by ID
	 *
	 * @since 1.2.0
	 * @param int $warning_id Warning ID.
	 * @return array|null Warning data or null
	 */
	public static function get_by_id( $warning_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $warning_id ),
			ARRAY_A
		);
	}

	/**
	 * Get warning by slug
	 *
	 * @since 1.2.0
	 * @param string $slug Warning slug.
	 * @return array|null Warning data or null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ),
			ARRAY_A
		);
	}

	/**
	 * Get warnings for a story
	 *
	 * @since 1.2.0
	 * @param int $story_id Story post ID.
	 * @return array Array of warning objects
	 */
	public static function get_story_warnings( $story_id ) {
		global $wpdb;
		$warnings_table  = $wpdb->prefix . 'fanfic_warnings';
		$relations_table = $wpdb->prefix . 'fanfic_story_warnings';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.* FROM {$warnings_table} w
				INNER JOIN {$relations_table} r ON w.id = r.warning_id
				WHERE r.story_id = %d
				ORDER BY w.name ASC",
				$story_id
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Save warnings for a story
	 *
	 * Replaces all existing warnings with the new set.
	 *
	 * @since 1.2.0
	 * @param int   $story_id   Story post ID.
	 * @param array $warning_ids Array of warning IDs.
	 * @return bool True on success
	 */
	public static function save_story_warnings( $story_id, $warning_ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_warnings';

		// Remove existing warnings
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		// Add new warnings
		if ( empty( $warning_ids ) || ! is_array( $warning_ids ) ) {
			return true;
		}

		// Limit to MAX_WARNINGS
		$warning_ids = array_slice( $warning_ids, 0, self::MAX_WARNINGS );

		foreach ( $warning_ids as $warning_id ) {
			$wpdb->insert(
				$table,
				array(
					'story_id'   => $story_id,
					'warning_id' => (int) $warning_id,
				),
				array( '%d', '%d' )
			);
		}

		return true;
	}

	/**
	 * Calculate derived age rating from warnings
	 *
	 * Returns the maximum age from all selected warnings.
	 *
	 * @since 1.2.0
	 * @param array $warning_ids Array of warning IDs.
	 * @return string Age rating (PG, 13+, 16+, 18+)
	 */
	public static function calculate_derived_age( $warning_ids ) {
		if ( empty( $warning_ids ) || ! is_array( $warning_ids ) ) {
			return 'PG';
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'fanfic_warnings';
		$placeholders = implode( ',', array_fill( 0, count( $warning_ids ), '%d' ) );

		$max_age = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT min_age FROM {$table} WHERE id IN ({$placeholders}) ORDER BY FIELD(min_age, 'PG', '13+', '16+', '18+') DESC LIMIT 1",
				$warning_ids
			)
		);

		return $max_age ? $max_age : 'PG';
	}

	/**
	 * Get enabled warnings filtered by content restrictions
	 *
	 * Respects allow_sexual_content and allow_pornographic_content settings.
	 *
	 * @since 1.2.0
	 * @return array Array of enabled warning objects
	 */
	public static function get_available_warnings() {
		$warnings = self::get_all( true );

		if ( ! class_exists( 'Fanfic_Settings' ) ) {
			return $warnings;
		}

		$allow_sexual       = Fanfic_Settings::get_setting( 'allow_sexual_content', true );
		$allow_pornographic = Fanfic_Settings::get_setting( 'allow_pornographic_content', false );

		$filtered = array();

		foreach ( $warnings as $warning ) {
			// Filter pornographic content
			if ( $warning['is_pornographic'] && ! $allow_pornographic ) {
				continue;
			}

			// Filter sexual content
			if ( $warning['is_sexual'] && ! $allow_sexual ) {
				continue;
			}

			$filtered[] = $warning;
		}

		return $filtered;
	}

	/**
	 * Check if a warning is available based on content restrictions
	 *
	 * @since 1.2.0
	 * @param int $warning_id Warning ID.
	 * @return bool True if available, false if restricted
	 */
	public static function is_warning_available( $warning_id ) {
		$warning = self::get_by_id( $warning_id );

		if ( ! $warning || ! $warning['enabled'] ) {
			return false;
		}

		if ( ! class_exists( 'Fanfic_Settings' ) ) {
			return true;
		}

		$allow_sexual       = Fanfic_Settings::get_setting( 'allow_sexual_content', true );
		$allow_pornographic = Fanfic_Settings::get_setting( 'allow_pornographic_content', false );

		// Check pornographic content restriction
		if ( $warning['is_pornographic'] && ! $allow_pornographic ) {
			return false;
		}

		// Check sexual content restriction
		if ( $warning['is_sexual'] && ! $allow_sexual ) {
			return false;
		}

		return true;
	}

	/**
	 * Clean up story-warning relations when a story is deleted
	 *
	 * @since 1.2.0
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public static function cleanup_story_relations( $post_id ) {
		if ( get_post_type( $post_id ) !== 'fanfiction_story' ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_warnings';
		$wpdb->delete( $table, array( 'story_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Update a warning
	 *
	 * @since 1.2.0
	 * @param int   $warning_id Warning ID.
	 * @param array $data       Warning data to update.
	 * @return bool True on success, false on failure
	 */
	public static function update_warning( $warning_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		$allowed_fields = array( 'name', 'min_age', 'description', 'is_sexual', 'is_pornographic', 'enabled' );
		$update_data    = array();
		$formats        = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				$update_data[ $key ] = $value;

				if ( in_array( $key, array( 'is_sexual', 'is_pornographic', 'enabled' ), true ) ) {
					$formats[] = '%d';
				} else {
					$formats[] = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $warning_id ),
			$formats,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Create a new custom warning
	 *
	 * @since 1.2.0
	 * @param array $data Warning data (name, min_age, description, is_sexual, is_pornographic).
	 * @return int|false Warning ID on success, false on failure
	 */
	public static function create_warning( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';

		// Generate slug from name
		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $data['name'] );

		// Check if slug already exists
		$exists = self::get_by_slug( $slug );
		if ( $exists ) {
			$slug .= '_' . time();
		}

		$result = $wpdb->insert(
			$table,
			array(
				'slug'            => $slug,
				'name'            => $data['name'],
				'min_age'         => isset( $data['min_age'] ) ? $data['min_age'] : 'PG',
				'description'     => isset( $data['description'] ) ? $data['description'] : '',
				'is_sexual'       => isset( $data['is_sexual'] ) ? (int) $data['is_sexual'] : 0,
				'is_pornographic' => isset( $data['is_pornographic'] ) ? (int) $data['is_pornographic'] : 0,
				'enabled'         => 1,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}
}
