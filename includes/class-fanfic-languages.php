<?php
/**
 * Languages Class
 *
 * Handles language classification tables, import, search, and story relations.
 *
 * @package FanfictionManager
 * @since 1.3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Languages
 */
class Fanfic_Languages {

	const REST_NAMESPACE = 'fanfic/v1';

	/**
	 * Initialize languages feature
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'init', array( __CLASS__, 'maybe_seed_languages' ), 12 );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_relations' ) );
	}

	/**
	 * Check if language classification is enabled
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'Fanfic_Settings' ) ) {
			return false;
		}
		return (bool) Fanfic_Settings::get_setting( 'enable_language_classification', false );
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/languages/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_search' ),
				'permission_callback' => array( __CLASS__, 'can_search' ),
				'args'                => array(
					'q'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);
	}

	/**
	 * Permission callback for search
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function can_search() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Handle search request
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_search( $request ) {
		if ( ! self::tables_ready() ) {
			return rest_ensure_response( array() );
		}

		$query = trim( (string) $request->get_param( 'q' ) );
		$limit = absint( $request->get_param( 'limit' ) );
		$limit = min( 100, max( 1, $limit ) );

		global $wpdb;
		$table = self::get_languages_table();

		// If no query, return all active languages
		if ( empty( $query ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, native_name, slug
					FROM {$table}
					WHERE is_active = 1
					ORDER BY name ASC
					LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		} else {
			$like = '%' . $wpdb->esc_like( $query ) . '%';

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, native_name, slug
					FROM {$table}
					WHERE is_active = 1
					  AND (name LIKE %s OR native_name LIKE %s OR slug LIKE %s)
					ORDER BY name ASC
					LIMIT %d",
					$like,
					$like,
					$like,
					$limit
				),
				ARRAY_A
			);
		}

		$results = is_array( $results ) ? $results : array();

		$response = array();
		foreach ( $results as $row ) {
			$label = $row['name'];
			if ( ! empty( $row['native_name'] ) && $row['native_name'] !== $row['name'] ) {
				$label .= ' (' . $row['native_name'] . ')';
			}
			$response[] = array(
				'id'          => (int) $row['id'],
				'label'       => $label,
				'name'        => $row['name'],
				'native_name' => $row['native_name'],
				'slug'        => $row['slug'],
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Import languages.json into the database if empty
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function maybe_seed_languages() {
		if ( ! self::tables_ready() ) {
			return;
		}

		if ( self::has_languages() ) {
			return;
		}

		self::import_from_json();
	}

	/**
	 * Import languages from json file
	 *
	 * @since 1.3.0
	 * @return int Number of rows inserted.
	 */
	public static function import_from_json() {
		if ( ! self::tables_ready() ) {
			return 0;
		}

		$data = self::load_languages_json();
		if ( empty( $data ) || ! is_array( $data ) ) {
			return 0;
		}

		global $wpdb;
		$table = self::get_languages_table();
		$inserted = 0;

		foreach ( $data as $lang ) {
			if ( empty( $lang['slug'] ) || empty( $lang['name'] ) ) {
				continue;
			}

			$slug = sanitize_title( $lang['slug'] );
			$name = sanitize_text_field( $lang['name'] );
			$native_name = isset( $lang['native_name'] ) ? sanitize_text_field( $lang['native_name'] ) : null;

			// Check if slug already exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE slug = %s",
					$slug
				)
			);

			if ( $exists ) {
				continue;
			}

			$result = $wpdb->insert(
				$table,
				array(
					'slug'        => $slug,
					'name'        => $name,
					'native_name' => $native_name,
					'is_active'   => 1,
				),
				array( '%s', '%s', '%s', '%d' )
			);

			if ( false !== $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Get all active languages
	 *
	 * @since 1.3.0
	 * @return array
	 */
	public static function get_all_languages() {
		if ( ! self::tables_ready() ) {
			return array();
		}

		global $wpdb;
		$table = self::get_languages_table();

		$results = $wpdb->get_results(
			"SELECT id, slug, name, native_name, is_active
			FROM {$table}
			ORDER BY name ASC",
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get active languages for selection
	 *
	 * @since 1.3.0
	 * @return array
	 */
	public static function get_active_languages() {
		if ( ! self::tables_ready() ) {
			return array();
		}

		global $wpdb;
		$table = self::get_languages_table();

		$results = $wpdb->get_results(
			"SELECT id, slug, name, native_name
			FROM {$table}
			WHERE is_active = 1
			ORDER BY name ASC",
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get language by ID
	 *
	 * @since 1.3.0
	 * @param int $language_id Language ID.
	 * @return array|null
	 */
	public static function get_by_id( $language_id ) {
		if ( ! self::tables_ready() ) {
			return null;
		}

		global $wpdb;
		$table = self::get_languages_table();

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, slug, name, native_name, is_active
				FROM {$table}
				WHERE id = %d",
				absint( $language_id )
			),
			ARRAY_A
		);

		return $result;
	}

	/**
	 * Get language by slug
	 *
	 * @since 1.3.0
	 * @param string $slug Language slug.
	 * @return array|null
	 */
	public static function get_by_slug( $slug ) {
		if ( ! self::tables_ready() ) {
			return null;
		}

		global $wpdb;
		$table = self::get_languages_table();

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, slug, name, native_name, is_active
				FROM {$table}
				WHERE slug = %s",
				sanitize_title( $slug )
			),
			ARRAY_A
		);

		return $result;
	}

	/**
	 * Get language ID for a story
	 *
	 * @since 1.3.0
	 * @param int $story_id Story ID.
	 * @return int|null Language ID or null if not set.
	 */
	public static function get_story_language_id( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );

		if ( ! $story_id || ! self::tables_ready() ) {
			return null;
		}

		$table = self::get_story_languages_table();
		$language_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT language_id FROM {$table} WHERE story_id = %d LIMIT 1",
				$story_id
			)
		);

		return $language_id ? absint( $language_id ) : null;
	}

	/**
	 * Get language data for a story
	 *
	 * @since 1.3.0
	 * @param int $story_id Story ID.
	 * @return array|null Language data or null if not set.
	 */
	public static function get_story_language( $story_id ) {
		$language_id = self::get_story_language_id( $story_id );

		if ( ! $language_id ) {
			return null;
		}

		return self::get_by_id( $language_id );
	}

	/**
	 * Get language label for a story
	 *
	 * @since 1.3.0
	 * @param int  $story_id Story ID.
	 * @param bool $include_native Whether to include native name.
	 * @return string Language label or empty string.
	 */
	public static function get_story_language_label( $story_id, $include_native = false ) {
		$language = self::get_story_language( $story_id );

		if ( ! $language ) {
			return '';
		}

		$label = $language['name'];
		if ( $include_native && ! empty( $language['native_name'] ) && $language['native_name'] !== $language['name'] ) {
			$label .= ' (' . $language['native_name'] . ')';
		}

		return $label;
	}

	/**
	 * Get story IDs by language slug
	 *
	 * @since 1.3.0
	 * @param string $slug Language slug.
	 * @return int[] Story IDs.
	 */
	public static function get_story_ids_by_language_slug( $slug ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return array();
		}

		$cache_key = '';
		if ( class_exists( 'Fanfic_Cache' ) ) {
			$cache_key = Fanfic_Cache::get_key( 'search', 'language_' . $slug );
			$cached = Fanfic_Cache::get( $cache_key, null, Fanfic_Cache::SHORT );
			if ( false !== $cached ) {
				return array_map( 'absint', (array) $cached );
			}
		}

		global $wpdb;
		$languages_table = self::get_languages_table();
		$relations_table = self::get_story_languages_table();

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT sl.story_id
				FROM {$relations_table} sl
				INNER JOIN {$languages_table} l ON l.id = sl.language_id
				WHERE l.is_active = 1
				  AND l.slug = %s",
				$slug
			)
		);

		$results = array_map( 'absint', (array) $results );

		if ( ! empty( $cache_key ) ) {
			Fanfic_Cache::set( $cache_key, $results, Fanfic_Cache::SHORT );
		}

		return $results;
	}

	/**
	 * Get story IDs by multiple language slugs
	 *
	 * Returns stories matching ANY of the provided language slugs.
	 *
	 * @since 1.3.0
	 * @param array $slugs Array of language slugs.
	 * @return int[] Story IDs.
	 */
	public static function get_story_ids_by_language_slugs( $slugs ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$slugs = array_filter( array_map( 'sanitize_title', (array) $slugs ) );
		if ( empty( $slugs ) ) {
			return array();
		}

		// For single slug, use the optimized single method
		if ( 1 === count( $slugs ) ) {
			return self::get_story_ids_by_language_slug( reset( $slugs ) );
		}

		$cache_key = '';
		if ( class_exists( 'Fanfic_Cache' ) ) {
			$hash = md5( implode( '|', $slugs ) );
			$cache_key = Fanfic_Cache::get_key( 'search', 'languages_' . $hash );
			$cached = Fanfic_Cache::get( $cache_key, null, Fanfic_Cache::SHORT );
			if ( false !== $cached ) {
				return array_map( 'absint', (array) $cached );
			}
		}

		global $wpdb;
		$languages_table = self::get_languages_table();
		$relations_table = self::get_story_languages_table();

		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT sl.story_id
				FROM {$relations_table} sl
				INNER JOIN {$languages_table} l ON l.id = sl.language_id
				WHERE l.is_active = 1
				  AND l.slug IN ({$placeholders})",
				$slugs
			)
		);

		$results = array_map( 'absint', (array) $results );

		if ( ! empty( $cache_key ) ) {
			Fanfic_Cache::set( $cache_key, $results, Fanfic_Cache::SHORT );
		}

		return $results;
	}

	/**
	 * Save story language relation
	 *
	 * @since 1.3.0
	 * @param int $story_id    Story ID.
	 * @param int $language_id Language ID (0 to clear).
	 * @return void
	 */
	public static function save_story_language( $story_id, $language_id ) {
		$story_id = absint( $story_id );
		$language_id = absint( $language_id );

		if ( ! $story_id || ! self::tables_ready() ) {
			return;
		}

		// Delete existing relation
		self::delete_story_relations( $story_id );

		// If no language selected, we're done
		if ( ! $language_id ) {
			return;
		}

		// Validate language exists and is active
		$validated = self::filter_valid_language_id( $language_id );
		if ( ! $validated ) {
			return;
		}

		global $wpdb;
		$table = self::get_story_languages_table();
		$wpdb->insert(
			$table,
			array(
				'story_id'    => $story_id,
				'language_id' => $validated,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Filter and validate language ID
	 *
	 * @since 1.3.0
	 * @param int $language_id Raw language ID.
	 * @return int|null Valid language ID or null.
	 */
	public static function filter_valid_language_id( $language_id ) {
		$language_id = absint( $language_id );

		if ( ! $language_id ) {
			return null;
		}

		if ( ! self::tables_ready() ) {
			return null;
		}

		global $wpdb;
		$table = self::get_languages_table();

		$valid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE is_active = 1 AND id = %d",
				$language_id
			)
		);

		return $valid ? absint( $valid ) : null;
	}

	/**
	 * Delete story relations
	 *
	 * @since 1.3.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function delete_story_relations( $story_id ) {
		global $wpdb;
		$table = self::get_story_languages_table();
		$wpdb->delete( $table, array( 'story_id' => absint( $story_id ) ), array( '%d' ) );
	}

	/**
	 * Cleanup story relations on deletion
	 *
	 * @since 1.3.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function cleanup_story_relations( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			return;
		}

		self::delete_story_relations( $post_id );
	}

	/**
	 * Check if language tables exist
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function tables_ready() {
		global $wpdb;
		$languages_table = self::get_languages_table();
		$relations_table = self::get_story_languages_table();

		$languages_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $languages_table ) ) === $languages_table;
		$relations_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $relations_table ) ) === $relations_table;

		return $languages_ready && $relations_ready;
	}

	/**
	 * Check if languages already exist
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function has_languages() {
		if ( ! self::tables_ready() ) {
			return false;
		}

		global $wpdb;
		$table = self::get_languages_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return (int) $count > 0;
	}

	/**
	 * Load languages.json data
	 *
	 * @since 1.3.0
	 * @return array
	 */
	private static function load_languages_json() {
		$file = FANFIC_PLUGIN_DIR . 'database/languages.json';
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return array();
		}

		$contents = file_get_contents( $file );
		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get languages table name
	 *
	 * @since 1.3.0
	 * @return string
	 */
	private static function get_languages_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_languages';
	}

	/**
	 * Get story_languages table name
	 *
	 * @since 1.3.0
	 * @return string
	 */
	private static function get_story_languages_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_story_languages';
	}
}
