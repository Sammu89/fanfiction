<?php
/**
 * Fandoms Class
 *
 * Handles fandom classification tables, import, search, and story relations.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Fandoms
 */
class Fanfic_Fandoms {

	const META_ORIGINAL = '_fanfic_is_original_work';
	const MAX_FANDOMS = 5;
	const REST_NAMESPACE = 'fanfic/v1';

	/**
	 * Initialize fandoms feature
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'init', array( __CLASS__, 'maybe_seed_fandoms' ), 12 );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_relations' ) );
	}

	/**
	 * Check if fandom classification is enabled
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'Fanfic_Settings' ) ) {
			return false;
		}
		return (bool) Fanfic_Settings::get_setting( 'enable_fandom_classification', false );
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/fandoms/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_search' ),
				'permission_callback' => array( __CLASS__, 'can_search' ),
				'args'                => array(
					'q'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);
	}

	/**
	 * Permission callback for search
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function can_search() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Handle search request
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_search( $request ) {
		if ( ! self::tables_ready() ) {
			return rest_ensure_response( array() );
		}

		$query = trim( (string) $request->get_param( 'q' ) );
		$limit = absint( $request->get_param( 'limit' ) );
		$limit = min( 20, max( 1, $limit ) );

		if ( strlen( $query ) < 2 ) {
			return rest_ensure_response( array() );
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$like = $wpdb->esc_like( $query ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name
				FROM {$table}
				WHERE is_active = 1
				  AND name LIKE %s
				ORDER BY name ASC
				LIMIT %d",
				$like,
				$limit
			),
			ARRAY_A
		);

		$results = is_array( $results ) ? $results : array();

		if ( count( $results ) < $limit && strlen( $query ) >= 3 && self::has_fulltext_index() ) {
			$remaining = $limit - count( $results );
			$existing_ids = wp_list_pluck( $results, 'id' );
			$exclude = ! empty( $existing_ids ) ? 'AND id NOT IN (' . implode( ',', array_map( 'absint', $existing_ids ) ) . ')' : '';

			$fulltext = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name
					FROM {$table}
					WHERE is_active = 1
					  {$exclude}
					  AND MATCH(name) AGAINST (%s IN NATURAL LANGUAGE MODE)
					ORDER BY name ASC
					LIMIT %d",
					$query,
					$remaining
				),
				ARRAY_A
			);

			if ( is_array( $fulltext ) && ! empty( $fulltext ) ) {
				$results = array_merge( $results, $fulltext );
			}
		}

		$response = array();
		foreach ( $results as $row ) {
			$response[] = array(
				'id'    => (int) $row['id'],
				'label' => $row['name'],
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Import fandoms.json into the database if empty
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_seed_fandoms() {
		if ( ! self::tables_ready() ) {
			return;
		}

		if ( self::has_fandoms() ) {
			return;
		}

		self::import_from_json();
	}

	/**
	 * Import fandoms from json file
	 *
	 * @since 1.0.0
	 * @return int Number of rows inserted.
	 */
	public static function import_from_json() {
		if ( ! self::tables_ready() ) {
			return 0;
		}

		$data = self::load_fandoms_json();
		if ( empty( $data ) || ! is_array( $data ) ) {
			return 0;
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$inserted = 0;
		$seen = array();

		foreach ( $data as $category => $names ) {
			if ( empty( $category ) || ! is_array( $names ) ) {
				continue;
			}

			$category_slug = sanitize_title( $category );
			foreach ( $names as $name ) {
				$name = trim( (string) $name );
				if ( '' === $name ) {
					continue;
				}

				$slug = self::unique_slug( $name, $seen );
				$result = $wpdb->insert(
					$table,
					array(
						'slug'      => $slug,
						'name'      => $name,
						'category'  => $category_slug,
						'is_active' => 1,
					),
					array( '%s', '%s', '%s', '%d' )
				);

				if ( false !== $result ) {
					$inserted++;
				}
			}
		}

		return $inserted;
	}

	/**
	 * Get fandom ids for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int[]
	 */
	public static function get_story_fandom_ids( $story_id ) {
		global $wpdb;
		$story_id = absint( $story_id );

		if ( ! $story_id || ! self::tables_ready() ) {
			return array();
		}

		$table = self::get_story_fandoms_table();
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT fandom_id FROM {$table} WHERE story_id = %d",
				$story_id
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Get fandom labels for a story (with Unknown placeholder)
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param bool $include_inactive Whether to include inactive fandoms.
	 * @return array<int, array{id:int,label:string,slug:string}>
	 */
	public static function get_story_fandom_labels( $story_id, $include_inactive = false ) {
		global $wpdb;
		$story_id = absint( $story_id );

		if ( ! $story_id || ! self::tables_ready() ) {
			return array();
		}

		$fandoms_table = self::get_fandoms_table();
		$relations_table = self::get_story_fandoms_table();

		$active_clause = $include_inactive ? '' : 'AND (f.is_active = 1 OR f.id IS NULL)';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sf.fandom_id, f.name, f.slug
				FROM {$relations_table} sf
				LEFT JOIN {$fandoms_table} f ON f.id = sf.fandom_id
				WHERE sf.story_id = %d
				  {$active_clause}
				ORDER BY f.name ASC",
				$story_id
			),
			ARRAY_A
		);

		$labels = array();
		foreach ( $rows as $row ) {
			$labels[] = array(
				'id'    => (int) $row['fandom_id'],
				'label' => $row['name'] ? $row['name'] : __( 'Unknown', 'fanfiction-manager' ),
				'slug'  => $row['slug'] ? $row['slug'] : '',
			);
		}

		return $labels;
	}

	/**
	 * Save story fandom relations and original flag
	 *
	 * @since 1.0.0
	 * @param int   $story_id Story ID.
	 * @param int[] $fandom_ids Fandom IDs.
	 * @param bool  $is_original Whether story is original work.
	 * @return void
	 */
	public static function save_story_fandoms( $story_id, $fandom_ids, $is_original ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::tables_ready() ) {
			return;
		}

		if ( $is_original ) {
			update_post_meta( $story_id, self::META_ORIGINAL, '1' );
			self::delete_story_relations( $story_id );
			return;
		}

		delete_post_meta( $story_id, self::META_ORIGINAL );

		$validated = self::filter_valid_fandom_ids( $fandom_ids, self::MAX_FANDOMS );
		self::delete_story_relations( $story_id );

		if ( empty( $validated ) ) {
			return;
		}

		global $wpdb;
		$table = self::get_story_fandoms_table();
		foreach ( $validated as $fandom_id ) {
			$wpdb->insert(
				$table,
				array(
					'story_id'  => $story_id,
					'fandom_id' => $fandom_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Filter and validate fandom IDs
	 *
	 * @since 1.0.0
	 * @param int[] $fandom_ids Raw fandom IDs.
	 * @param int   $limit Maximum number of items.
	 * @return int[]
	 */
	public static function filter_valid_fandom_ids( $fandom_ids, $limit = 5 ) {
		$ids = array_map( 'absint', (array) $fandom_ids );
		$ids = array_values( array_filter( array_unique( $ids ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		if ( ! self::tables_ready() ) {
			return array();
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$valid = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE is_active = 1 AND id IN ({$placeholders})",
				$ids
			)
		);

		return array_map( 'absint', $valid );
	}

	/**
	 * Delete story relations
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function delete_story_relations( $story_id ) {
		global $wpdb;
		$table = self::get_story_fandoms_table();
		$wpdb->delete( $table, array( 'story_id' => absint( $story_id ) ), array( '%d' ) );
	}

	/**
	 * Cleanup story relations on deletion
	 *
	 * @since 1.0.0
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
	 * Check if fandom tables exist
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function tables_ready() {
		global $wpdb;
		$fandoms_table = self::get_fandoms_table();
		$relations_table = self::get_story_fandoms_table();

		$fandoms_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fandoms_table ) ) === $fandoms_table;
		$relations_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $relations_table ) ) === $relations_table;

		return $fandoms_ready && $relations_ready;
	}

	/**
	 * Check if fandoms already exist
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function has_fandoms() {
		if ( ! self::tables_ready() ) {
			return false;
		}

		global $wpdb;
		$table = self::get_fandoms_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return (int) $count > 0;
	}

	/**
	 * Check for fulltext index on name
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private static function has_fulltext_index() {
		global $wpdb;
		$table = self::get_fandoms_table();
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Index_type = 'FULLTEXT'" );
		if ( empty( $indexes ) ) {
			return false;
		}

		foreach ( $indexes as $index ) {
			if ( isset( $index->Column_name ) && 'name' === $index->Column_name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Load fandoms.json data
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private static function load_fandoms_json() {
		$file = FANFIC_PLUGIN_DIR . 'database/fandoms.json';
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return array();
		}

		$contents = file_get_contents( $file );
		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Generate a unique slug for fandoms
	 *
	 * @since 1.0.0
	 * @param string $name Fandom name.
	 * @param array  $seen Reference array of used slugs.
	 * @return string
	 */
	private static function unique_slug( $name, &$seen ) {
		$base = sanitize_title( $name );
		if ( '' === $base ) {
			$base = 'fandom';
		}

		$slug = $base;
		$counter = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '-' . $counter;
			$counter++;
		}

		$seen[ $slug ] = true;

		return $slug;
	}

	/**
	 * Get fandoms table name
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function get_fandoms_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_fandoms';
	}

	/**
	 * Get story_fandoms table name
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function get_story_fandoms_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_story_fandoms';
	}
}
