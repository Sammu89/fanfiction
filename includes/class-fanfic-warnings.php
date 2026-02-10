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
	 * Minimum allowed warning age.
	 *
	 * @var int
	 */
	const MIN_WARNING_AGE = 3;

	/**
	 * Maximum allowed warning age.
	 *
	 * @var int
	 */
	const MAX_WARNING_AGE = 99;

	/**
	 * Inferred age rating when no warnings are attached.
	 *
	 * @var string
	 */
	const DEFAULT_NO_WARNING_AGE = 'PG';

	/**
	 * Runtime cache for age labels/order within a request.
	 *
	 * @var array
	 */
	private static $age_runtime_cache = array(
		'distinct' => array(),
		'priority' => array(),
	);

	/**
	 * Initialize warnings feature
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_relations' ) );
	}

	/**
	 * Seed default warnings if table is empty
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function maybe_seed_warnings() {
		static $checked_this_request = false;
		if ( $checked_this_request ) {
			return;
		}
		$checked_this_request = true;

		// Seed warnings as soon as the warnings table exists.
		// Do not depend on every plugin table being present.
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
		if ( ! self::tables_ready() ) {
			return false;
		}

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
				'min_age'          => '16',
				'description'      => 'Detailed, bloody, or intense depictions of fighting or injury',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'character_death',
				'name'             => 'Character Death',
				'min_age'          => '13',
				'description'      => 'Death of a major or important character (can be emotional)',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'sexual_violence',
				'name'             => 'Sexual Violence',
				'min_age'          => '18',
				'description'      => 'Rape, sexual assault, or coercion (non-consensual)',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'underage_sexual',
				'name'             => 'Underage (sexual content)',
				'min_age'          => '18',
				'description'      => 'Sexual activity involving characters under 18',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'incest',
				'name'             => 'Incest',
				'min_age'          => '18',
				'description'      => 'Sexual content between family members',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'non_explicit_sexual',
				'name'             => 'Non-explicit Sexual Content',
				'min_age'          => '13',
				'description'      => 'Implied or non-explicit sexual scenes: fade-to-black, heavy kissing and touching, sensuality without graphic detail',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'explicit_sexual',
				'name'             => 'Explicit Sexual Descriptions',
				'min_age'          => '16',
				'description'      => 'Detailed, graphic descriptions of sexual acts (not necessarily pornographic)',
				'is_sexual'        => 1,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'pornographic',
				'name'             => 'Pornographic Sexual Content',
				'min_age'          => '18',
				'description'      => 'Focus on explicit sex as main purpose, highly detailed and erotic',
				'is_sexual'        => 1,
				'is_pornographic'  => 1,
			),
			array(
				'slug'             => 'self_harm',
				'name'             => 'Self-Harm',
				'min_age'          => '16',
				'description'      => 'Depictions or discussions of cutting, burning, etc.',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'suicide',
				'name'             => 'Suicide',
				'min_age'          => '16',
				'description'      => 'Suicide attempts, completion, or heavy ideation',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'abuse',
				'name'             => 'Abuse',
				'min_age'          => '16',
				'description'      => 'Physical, emotional, or psychological domestic/family abuse',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'substance_abuse',
				'name'             => 'Substance Abuse',
				'min_age'          => '16',
				'description'      => 'Heavy or addictive drug or alcohol use, addiction portrayal',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'eating_disorders',
				'name'             => 'Eating Disorders',
				'min_age'          => '16',
				'description'      => 'Anorexia, bulimia, binge eating, or body dysmorphia',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'miscarriage_abortion',
				'name'             => 'Miscarriage / Abortion',
				'min_age'          => '16',
				'description'      => 'Detailed pregnancy loss, termination, or traumatic birth',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'torture',
				'name'             => 'Torture',
				'min_age'          => '18',
				'description'      => 'Prolonged, sadistic infliction of pain or injury',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'blood_gore',
				'name'             => 'Blood / Gore',
				'min_age'          => '16',
				'description'      => 'Extreme blood, mutilation, or detailed injury descriptions',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'animal_cruelty',
				'name'             => 'Animal Cruelty',
				'min_age'          => '16',
				'description'      => 'Abuse, killing, or suffering of animals',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
			array(
				'slug'             => 'homophobia',
				'name'             => 'Homophobia',
				'min_age'          => '16',
				'description'      => 'Depictions of hate speech, discrimination, or slurs',
				'is_sexual'        => 0,
				'is_pornographic'  => 0,
			),
		);

		$inserted = 0;

		foreach ( $warnings as $warning ) {
			$min_age = self::sanitize_age_label( $warning['min_age'] );
			$result = $wpdb->insert(
				$table,
				array(
					'slug'            => $warning['slug'],
					'name'            => $warning['name'],
					'min_age'         => absint( $min_age ),
					'description'     => $warning['description'],
					'is_sexual'       => $warning['is_sexual'],
					'is_pornographic' => $warning['is_pornographic'],
					'enabled'         => 1,
				),
				array( '%s', '%s', '%d', '%s', '%d', '%d', '%d' )
			);

			if ( false === $result ) {
				error_log( '[Fanfic Warnings] Error inserting warning "' . $warning['name'] . '": ' . $wpdb->last_error );
			} elseif ( $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Sanitize and validate a warning age value.
	 *
	 * @since 1.2.0
	 * @param string $value Raw age input.
	 * @return string Sanitized numeric age as string, or empty string if not numeric.
	 */
	public static function sanitize_age_label( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strcasecmp( $value, self::DEFAULT_NO_WARNING_AGE ) ) {
			return '';
		}

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		$age = (int) round( (float) $value );
		if ( $age < self::MIN_WARNING_AGE ) {
			$age = self::MIN_WARNING_AGE;
		} elseif ( $age > self::MAX_WARNING_AGE ) {
			$age = self::MAX_WARNING_AGE;
		}

		return (string) $age;
	}

	/**
	 * Format an age label for UI output.
	 *
	 * Numeric age values are rendered as "13+" while no-warning remains "PG".
	 *
	 * @since 1.2.0
	 * @param string $value Age value.
	 * @param bool   $infer_default Whether to infer the default no-warning age for empty values.
	 * @return string Display label.
	 */
	public static function format_age_label_for_display( $value, $infer_default = true ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return $infer_default ? self::DEFAULT_NO_WARNING_AGE : '';
		}

		if ( 0 === strcasecmp( $value, self::DEFAULT_NO_WARNING_AGE ) ) {
			return self::DEFAULT_NO_WARNING_AGE;
		}

		$age = self::sanitize_age_label( $value );
		if ( '' === $age ) {
			return '';
		}

		return $age . '+';
	}

	/**
	 * Get age badge range key for UI styling.
	 *
	 * @since 1.2.0
	 * @param string $value Age value.
	 * @return string Range key.
	 */
	public static function get_age_badge_range_key( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || 0 === strcasecmp( $value, self::DEFAULT_NO_WARNING_AGE ) ) {
			return '3-9';
		}

		$age = self::sanitize_age_label( $value );
		if ( '' === $age ) {
			return '3-9';
		}

		$age = absint( $age );
		if ( $age <= 9 ) {
			return '3-9';
		}
		if ( $age <= 12 ) {
			return '10-12';
		}
		if ( $age <= 15 ) {
			return '13-15';
		}
		if ( $age <= 17 ) {
			return '16-17';
		}

		return '18-plus';
	}

	/**
	 * Get age badge CSS class.
	 *
	 * @since 1.2.0
	 * @param string $value Age value.
	 * @param string $prefix Class prefix.
	 * @return string CSS class.
	 */
	public static function get_age_badge_class( $value, $prefix = 'fanfic-age-badge-' ) {
		$prefix = trim( (string) $prefix );
		if ( '' === $prefix ) {
			$prefix = 'fanfic-age-badge-';
		}

		return $prefix . self::get_age_badge_range_key( $value );
	}

	/**
	 * Build cache key for boolean flags.
	 *
	 * @since 1.2.0
	 * @param bool $enabled_only Enabled-only flag.
	 * @return string Cache key.
	 */
	private static function get_age_cache_key( $enabled_only ) {
		return $enabled_only ? '1' : '0';
	}

	/**
	 * Reset runtime age caches.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function reset_age_runtime_cache() {
		self::$age_runtime_cache = array(
			'distinct' => array(),
			'priority' => array(),
		);
	}

	/**
	 * Get numeric sort weight for an age label.
	 *
	 * @since 1.2.0
	 * @param string $value Age label.
	 * @return float Sort weight.
	 */
	private static function get_age_sort_weight( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 1000;
		}

		if ( 0 === strcasecmp( $value, self::DEFAULT_NO_WARNING_AGE ) ) {
			return 0;
		}

		$age = self::sanitize_age_label( $value );
		if ( '' !== $age ) {
			return (float) absint( $age );
		}

		return 1000;
	}

	/**
	 * Build a stable age priority map from numeric warning ages.
	 *
	 * @since 1.2.0
	 * @param string[] $age_values Age labels.
	 * @return array<string,int> Priority map.
	 */
	private static function build_age_priority_map_from_values( $age_values ) {
		$unique = array();
		$seen   = array();

		foreach ( (array) $age_values as $age_value ) {
			$age_value = self::sanitize_age_label( $age_value );
			if ( '' === $age_value ) {
				continue;
			}

			$lookup = strtoupper( $age_value );
			if ( isset( $seen[ $lookup ] ) ) {
				continue;
			}

			$seen[ $lookup ] = true;
			$unique[]        = $age_value;
		}

		if ( empty( $unique ) ) {
			return array();
		}

		usort(
			$unique,
			function( $left, $right ) {
				$left_weight  = self::get_age_sort_weight( $left );
				$right_weight = self::get_age_sort_weight( $right );
				if ( $left_weight === $right_weight ) {
					return strcasecmp( (string) $left, (string) $right );
				}

				return ( $left_weight < $right_weight ) ? -1 : 1;
			}
		);

		$priority = array();
		$rank = 1;
		foreach ( $unique as $age_value ) {
			$priority[ $age_value ] = $rank;
			$rank++;
		}

		return $priority;
	}

	/**
	 * Get distinct age labels from warnings.
	 *
	 * @since 1.2.0
	 * @param bool $enabled_only Whether to use only enabled warnings.
	 * @return string[] Age labels.
	 */
	private static function get_distinct_age_labels( $enabled_only = false ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$cache_key = self::get_age_cache_key( $enabled_only );
		if ( isset( self::$age_runtime_cache['distinct'][ $cache_key ] ) ) {
			return self::$age_runtime_cache['distinct'][ $cache_key ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_warnings';
		$where = $enabled_only ? "WHERE enabled = 1 AND min_age != ''" : "WHERE min_age != ''";

		$sql = "SELECT DISTINCT min_age FROM {$table} {$where}";
		$ages = $wpdb->get_col( $sql );

		$normalized = array();
		foreach ( (array) $ages as $age ) {
			$age = self::sanitize_age_label( $age );
			if ( '' !== $age ) {
				$normalized[] = $age;
			}
		}

		$priority = self::build_age_priority_map_from_values( $normalized );
		$distinct = array_keys( $priority );
		self::$age_runtime_cache['distinct'][ $cache_key ] = $distinct;

		return $distinct;
	}

	/**
	 * Get the default derived age label.
	 *
	 * @since 1.2.0
	 * @param bool $enabled_only Whether to use only enabled warnings.
	 * @return string Default age label.
	 */
	public static function get_default_age_label( $enabled_only = false ) {
		return self::DEFAULT_NO_WARNING_AGE;
	}

	/**
	 * Get age priority map.
	 *
	 * @since 1.2.0
	 * @param bool $enabled_only Whether to use only enabled warnings.
	 * @return array<string,int> Priority map.
	 */
	public static function get_age_priority_map( $enabled_only = false ) {
		$cache_key = self::get_age_cache_key( $enabled_only );
		if ( isset( self::$age_runtime_cache['priority'][ $cache_key ] ) ) {
			return self::$age_runtime_cache['priority'][ $cache_key ];
		}

		$ages = self::get_distinct_age_labels( $enabled_only );
		$priority = self::build_age_priority_map_from_values( $ages );
		$priority = array( self::DEFAULT_NO_WARNING_AGE => 0 ) + $priority;
		self::$age_runtime_cache['priority'][ $cache_key ] = $priority;
		return $priority;
	}

	/**
	 * Normalize an age label to a canonical configured value.
	 *
	 * @since 1.2.0
	 * @param string $value Age value.
	 * @param bool   $enabled_only Whether to use only enabled warnings.
	 * @return string Canonical label or empty string when not recognized.
	 */
	public static function normalize_age_label( $value, $enabled_only = false ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( 0 === strcasecmp( $value, self::DEFAULT_NO_WARNING_AGE ) ) {
			return self::DEFAULT_NO_WARNING_AGE;
		}

		$value = self::sanitize_age_label( $value );
		if ( '' === $value ) {
			return '';
		}

		$priority = self::get_age_priority_map( $enabled_only );
		return isset( $priority[ $value ] ) ? $value : '';
	}

	/**
	 * Legacy compatibility hook for deprecated lowest-age flagging.
	 *
	 * Deprecated: kept as no-op for backward compatibility.
	 *
	 * @since 1.2.0
	 * @param int $warning_id Warning ID.
	 * @return void
	 */
	public static function set_lowest_age_warning( $warning_id ) {
		return;
	}

	/**
	 * Legacy compatibility hook for deprecated lowest-age assignment.
	 *
	 * Deprecated: now only flushes runtime age cache.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function ensure_lowest_age_assignment() {
		self::reset_age_runtime_cache();
		return;
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
		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			return array();
		}

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
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return false;
		}

		// Remove existing warnings
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		// Add new warnings
		if ( empty( $warning_ids ) || ! is_array( $warning_ids ) ) {
			self::sync_story_age_rating( $story_id, array() );
			if ( class_exists( 'Fanfic_Taxonomies' ) && method_exists( 'Fanfic_Taxonomies', 'enforce_story_genre_constraints' ) ) {
				Fanfic_Taxonomies::enforce_story_genre_constraints( $story_id );
			}
			return true;
		}

		$warning_ids = array_values( array_unique( array_filter( array_map( 'absint', $warning_ids ) ) ) );

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

		self::sync_story_age_rating( $story_id, $warning_ids );
		if ( class_exists( 'Fanfic_Taxonomies' ) && method_exists( 'Fanfic_Taxonomies', 'enforce_story_genre_constraints' ) ) {
			Fanfic_Taxonomies::enforce_story_genre_constraints( $story_id );
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
	 * @return string Age rating label.
	 */
	public static function calculate_derived_age( $warning_ids ) {
		$default_age = self::get_default_age_label( false );

		if ( empty( $warning_ids ) || ! is_array( $warning_ids ) ) {
			return $default_age;
		}

		$warning_ids = array_values( array_unique( array_filter( array_map( 'absint', $warning_ids ) ) ) );
		if ( empty( $warning_ids ) ) {
			return $default_age;
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'fanfic_warnings';
		$placeholders = implode( ',', array_fill( 0, count( $warning_ids ), '%d' ) );
		$age_rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT min_age FROM {$table} WHERE id IN ({$placeholders})", $warning_ids )
		);
		if ( empty( $age_rows ) ) {
			return $default_age;
		}

		$priority = self::get_age_priority_map( false );
		$max_rank = 0;
		$max_age  = '';

		foreach ( (array) $age_rows as $age_value ) {
			$age_value = self::normalize_age_label( $age_value, false );
			if ( '' === $age_value ) {
				continue;
			}

			$rank = isset( $priority[ $age_value ] ) ? (int) $priority[ $age_value ] : 0;
			if ( $rank > $max_rank ) {
				$max_rank = $rank;
				$max_age  = $age_value;
			}
		}

		return '' !== $max_age ? $max_age : $default_age;
	}

	/**
	 * Get warning IDs attached to a story.
	 *
	 * @since 1.2.0
	 * @param int $story_id Story ID.
	 * @return int[] Warning IDs.
	 */
	public static function get_story_warning_ids( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_warnings';

		$warning_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT warning_id FROM {$table} WHERE story_id = %d", $story_id )
		);

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $warning_ids ) ) ) );
	}

	/**
	 * Sync derived age rating for a story and refresh its search index row.
	 *
	 * @since 1.2.0
	 * @param int        $story_id Story ID.
	 * @param int[]|null $warning_ids Optional warning IDs.
	 * @return string Derived age label.
	 */
	public static function sync_story_age_rating( $story_id, $warning_ids = null ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return '';
		}

		if ( null === $warning_ids ) {
			$warning_ids = self::get_story_warning_ids( $story_id );
		}

		$derived_age = self::calculate_derived_age( (array) $warning_ids );
		if ( '' === $derived_age ) {
			delete_post_meta( $story_id, '_fanfic_age_rating' );
		} else {
			update_post_meta( $story_id, '_fanfic_age_rating', $derived_age );
		}

		if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
			Fanfic_Search_Index::update_index( $story_id );
		}

		if ( class_exists( 'Fanfic_Cache' ) ) {
			$cache_key = Fanfic_Cache::get_key( 'search', 'global_filter_counts' );
			Fanfic_Cache::delete( $cache_key );
		}

		return $derived_age;
	}

	/**
	 * Sync derived age rating for a list of stories.
	 *
	 * @since 1.2.0
	 * @param int[] $story_ids Story IDs.
	 * @return void
	 */
	public static function sync_story_age_ratings( $story_ids ) {
		$story_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $story_ids ) ) ) );
		if ( empty( $story_ids ) ) {
			return;
		}

		foreach ( $story_ids as $story_id ) {
			self::sync_story_age_rating( $story_id );
		}
	}

	/**
	 * Sync age ratings for stories affected by one warning.
	 *
	 * @since 1.2.0
	 * @param int  $warning_id Warning ID.
	 * @param bool $sync_all Whether to sync all stories.
	 * @return void
	 */
	public static function sync_age_ratings_for_warning( $warning_id, $sync_all = false ) {
		if ( $sync_all ) {
			self::sync_all_story_age_ratings();
			return;
		}

		$warning_id = absint( $warning_id );
		if ( ! $warning_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_warnings';
		$story_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT DISTINCT story_id FROM {$table} WHERE warning_id = %d", $warning_id )
		);

		self::sync_story_age_ratings( $story_ids );
	}

	/**
	 * Sync age ratings for all fanfiction stories.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function sync_all_story_age_ratings() {
		$story_ids = get_posts(
			array(
				'post_type'      => 'fanfiction_story',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		self::sync_story_age_ratings( $story_ids );
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
		if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) {
			return array();
		}

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
				if ( 'min_age' === $key ) {
					$value = self::sanitize_age_label( $value );
					if ( '' === $value ) {
						return false;
					}
				}
				if ( in_array( $key, array( 'min_age', 'is_sexual', 'is_pornographic', 'enabled' ), true ) ) {
					$value = (int) $value;
				}
				$update_data[ $key ] = $value;

				if ( in_array( $key, array( 'min_age', 'is_sexual', 'is_pornographic', 'enabled' ), true ) ) {
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

		if ( false !== $result ) {
			self::reset_age_runtime_cache();
		}

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

		$min_age = isset( $data['min_age'] ) ? self::sanitize_age_label( $data['min_age'] ) : (string) self::MIN_WARNING_AGE;
		if ( '' === $min_age ) {
			return false;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'slug'            => $slug,
				'name'            => $data['name'],
				'min_age'         => (int) $min_age,
				'description'     => isset( $data['description'] ) ? $data['description'] : '',
				'is_sexual'       => isset( $data['is_sexual'] ) ? (int) $data['is_sexual'] : 0,
				'is_pornographic' => isset( $data['is_pornographic'] ) ? (int) $data['is_pornographic'] : 0,
				'enabled'         => 1,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%d', '%d' )
		);

		if ( ! $result ) {
			return false;
		}

		self::reset_age_runtime_cache();
		$new_warning_id = (int) $wpdb->insert_id;

		return $new_warning_id;
	}
}

