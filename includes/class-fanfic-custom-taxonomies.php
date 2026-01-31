<?php
/**
 * Custom Taxonomies Class
 *
 * Handles dynamic custom taxonomy management for fanfiction stories.
 * Allows administrators to create their own lightweight taxonomies.
 *
 * @package FanfictionManager
 * @since 1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Custom_Taxonomies
 *
 * Manages custom taxonomies, terms, and story relations.
 *
 * @since 1.4.0
 */
class Fanfic_Custom_Taxonomies {

	/**
	 * Cache group for custom taxonomies.
	 *
	 * @var string
	 */
	private static $cache_group = 'fanfic_custom_taxonomies';

	/**
	 * Cache expiration in seconds (1 hour).
	 *
	 * @var int
	 */
	private static $cache_expiration = 3600;

	/**
	 * Registered shortcodes for cleanup.
	 *
	 * @var array
	 */
	private static $registered_shortcodes = array();

	/**
	 * Initialize the class.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init() {
		// Register REST API endpoints.
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// Register dynamic shortcodes for all active taxonomies.
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ), 20 );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function register_rest_routes() {
		// Search terms endpoint.
		register_rest_route(
			'fanfic/v1',
			'/custom-terms/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_search_terms' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'taxonomy' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'q'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST API callback for term search.
	 *
	 * @since 1.4.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function rest_search_terms( $request ) {
		$taxonomy_slug = $request->get_param( 'taxonomy' );
		$query         = $request->get_param( 'q' );

		$taxonomy = self::get_taxonomy_by_slug( $taxonomy_slug );
		if ( ! $taxonomy ) {
			return new WP_REST_Response( array(), 200 );
		}

		$terms = self::search_terms( $taxonomy['id'], $query );

		return new WP_REST_Response( $terms, 200 );
	}

	/**
	 * Check if custom tables are ready.
	 *
	 * @since 1.4.0
	 * @return bool True if tables exist.
	 */
	public static function tables_ready() {
		global $wpdb;

		static $ready = null;
		if ( null !== $ready ) {
			return $ready;
		}

		$table = $wpdb->prefix . 'fanfic_custom_taxonomies';
		$ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

		return $ready;
	}

	// =========================================================================
	// Taxonomy CRUD
	// =========================================================================

	/**
	 * Get all taxonomies.
	 *
	 * @since 1.4.0
	 * @param bool $active_only Whether to return only active taxonomies.
	 * @return array Array of taxonomy data.
	 */
	public static function get_all_taxonomies( $active_only = false ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$cache_key = $active_only ? 'all_taxonomies_active' : 'all_taxonomies';
		$cached    = wp_cache_get( $cache_key, self::$cache_group );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_taxonomies';

		$sql = "SELECT * FROM {$table}";
		if ( $active_only ) {
			$sql .= ' WHERE is_active = 1';
		}
		$sql .= ' ORDER BY sort_order ASC, name ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! $results ) {
			$results = array();
		}

		wp_cache_set( $cache_key, $results, self::$cache_group, self::$cache_expiration );

		return $results;
	}

	/**
	 * Get active taxonomies.
	 *
	 * @since 1.4.0
	 * @return array Array of active taxonomy data.
	 */
	public static function get_active_taxonomies() {
		return self::get_all_taxonomies( true );
	}

	/**
	 * Get taxonomy by ID.
	 *
	 * @since 1.4.0
	 * @param int $id Taxonomy ID.
	 * @return array|null Taxonomy data or null if not found.
	 */
	public static function get_taxonomy_by_id( $id ) {
		if ( ! self::tables_ready() ) {
			return null;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$cache_key = 'taxonomy_' . $id;
		$cached    = wp_cache_get( $cache_key, self::$cache_group );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_taxonomies';

		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( $result ) {
			wp_cache_set( $cache_key, $result, self::$cache_group, self::$cache_expiration );
		}

		return $result;
	}

	/**
	 * Get taxonomy by slug.
	 *
	 * @since 1.4.0
	 * @param string $slug Taxonomy slug.
	 * @return array|null Taxonomy data or null if not found.
	 */
	public static function get_taxonomy_by_slug( $slug ) {
		if ( ! self::tables_ready() ) {
			return null;
		}

		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return null;
		}

		$cache_key = 'taxonomy_slug_' . $slug;
		$cached    = wp_cache_get( $cache_key, self::$cache_group );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_taxonomies';

		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ),
			ARRAY_A
		);

		if ( $result ) {
			wp_cache_set( $cache_key, $result, self::$cache_group, self::$cache_expiration );
		}

		return $result;
	}

	/**
	 * Create a new taxonomy.
	 *
	 * @since 1.4.0
	 * @param array $data Taxonomy data (name, slug, selection_type).
	 * @return int|WP_Error New taxonomy ID or error.
	 */
	public static function create_taxonomy( $data ) {
		if ( ! self::tables_ready() ) {
			return new WP_Error( 'tables_not_ready', __( 'Database tables not ready.', 'fanfiction-manager' ) );
		}

		$name           = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$slug           = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
		$selection_type = isset( $data['selection_type'] ) && 'multi' === $data['selection_type'] ? 'multi' : 'single';

		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_Error( 'invalid_data', __( 'Name and slug are required.', 'fanfiction-manager' ) );
		}

		// Check for reserved slugs.
		$reserved = array( 'fandom', 'fandoms', 'warning', 'warnings', 'language', 'languages', 'genre', 'genres', 'status', 'search', 'sort', 'age' );
		if ( in_array( $slug, $reserved, true ) ) {
			return new WP_Error( 'reserved_slug', __( 'This slug is reserved and cannot be used.', 'fanfiction-manager' ) );
		}

		// Check for duplicate slug.
		if ( self::get_taxonomy_by_slug( $slug ) ) {
			return new WP_Error( 'duplicate_slug', __( 'A taxonomy with this slug already exists.', 'fanfiction-manager' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_taxonomies';

		$result = $wpdb->insert(
			$table,
			array(
				'slug'           => $slug,
				'name'           => $name,
				'selection_type' => $selection_type,
				'is_active'      => 1,
				'sort_order'     => 0,
			),
			array( '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create taxonomy.', 'fanfiction-manager' ) );
		}

		self::clear_cache();

		return $wpdb->insert_id;
	}

	/**
	 * Update a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int   $id   Taxonomy ID.
	 * @param array $data Taxonomy data to update.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function update_taxonomy( $id, $data ) {
		if ( ! self::tables_ready() ) {
			return new WP_Error( 'tables_not_ready', __( 'Database tables not ready.', 'fanfiction-manager' ) );
		}

		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid taxonomy ID.', 'fanfiction-manager' ) );
		}

		$existing = self::get_taxonomy_by_id( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Taxonomy not found.', 'fanfiction-manager' ) );
		}

		$update = array();
		$format = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}

		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) (bool) $data['is_active'];
			$format[]            = '%d';
		}

		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = absint( $data['sort_order'] );
			$format[]             = '%d';
		}

		if ( empty( $update ) ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_taxonomies';

		$result = $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update taxonomy.', 'fanfiction-manager' ) );
		}

		self::clear_cache();

		return true;
	}

	/**
	 * Delete a taxonomy and all its terms/relations.
	 *
	 * @since 1.4.0
	 * @param int $id Taxonomy ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function delete_taxonomy( $id ) {
		if ( ! self::tables_ready() ) {
			return new WP_Error( 'tables_not_ready', __( 'Database tables not ready.', 'fanfiction-manager' ) );
		}

		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid taxonomy ID.', 'fanfiction-manager' ) );
		}

		global $wpdb;

		$table_taxonomies = $wpdb->prefix . 'fanfic_custom_taxonomies';
		$table_terms      = $wpdb->prefix . 'fanfic_custom_terms';
		$table_relations  = $wpdb->prefix . 'fanfic_story_custom_terms';

		// Get all term IDs for this taxonomy.
		$term_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$table_terms} WHERE taxonomy_id = %d", $id )
		);

		// Delete story relations for these terms.
		if ( ! empty( $term_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_relations} WHERE term_id IN ({$placeholders})", $term_ids ) );
		}

		// Delete terms.
		$wpdb->delete( $table_terms, array( 'taxonomy_id' => $id ), array( '%d' ) );

		// Delete taxonomy.
		$result = $wpdb->delete( $table_taxonomies, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete taxonomy.', 'fanfiction-manager' ) );
		}

		self::clear_cache();

		return true;
	}

	// =========================================================================
	// Term CRUD
	// =========================================================================

	/**
	 * Get all terms for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int  $taxonomy_id Taxonomy ID.
	 * @param bool $active_only Whether to return only active terms.
	 * @return array Array of term data.
	 */
	public static function get_taxonomy_terms( $taxonomy_id, $active_only = false ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$taxonomy_id = absint( $taxonomy_id );
		if ( ! $taxonomy_id ) {
			return array();
		}

		$cache_key = 'terms_' . $taxonomy_id . ( $active_only ? '_active' : '' );
		$cached    = wp_cache_get( $cache_key, self::$cache_group );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE taxonomy_id = %d", $taxonomy_id );
		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}
		$sql .= ' ORDER BY sort_order ASC, name ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! $results ) {
			$results = array();
		}

		wp_cache_set( $cache_key, $results, self::$cache_group, self::$cache_expiration );

		return $results;
	}

	/**
	 * Get active terms for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int $taxonomy_id Taxonomy ID.
	 * @return array Array of active term data.
	 */
	public static function get_active_terms( $taxonomy_id ) {
		return self::get_taxonomy_terms( $taxonomy_id, true );
	}

	/**
	 * Search terms within a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int    $taxonomy_id Taxonomy ID.
	 * @param string $query       Search query.
	 * @param int    $limit       Maximum results.
	 * @return array Array of matching terms.
	 */
	public static function search_terms( $taxonomy_id, $query, $limit = 20 ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$taxonomy_id = absint( $taxonomy_id );
		if ( ! $taxonomy_id ) {
			return array();
		}

		$query = sanitize_text_field( $query );
		if ( empty( $query ) ) {
			return self::get_active_terms( $taxonomy_id );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE taxonomy_id = %d
				AND is_active = 1
				AND name LIKE %s
				ORDER BY name ASC
				LIMIT %d",
				$taxonomy_id,
				'%' . $wpdb->esc_like( $query ) . '%',
				$limit
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get term by ID.
	 *
	 * @since 1.4.0
	 * @param int $id Term ID.
	 * @return array|null Term data or null if not found.
	 */
	public static function get_term_by_id( $id ) {
		if ( ! self::tables_ready() ) {
			return null;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Get term by slug within a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int    $taxonomy_id Taxonomy ID.
	 * @param string $slug        Term slug.
	 * @return array|null Term data or null if not found.
	 */
	public static function get_term_by_slug( $taxonomy_id, $slug ) {
		if ( ! self::tables_ready() ) {
			return null;
		}

		$taxonomy_id = absint( $taxonomy_id );
		$slug        = sanitize_title( $slug );

		if ( ! $taxonomy_id || empty( $slug ) ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE taxonomy_id = %d AND slug = %s",
				$taxonomy_id,
				$slug
			),
			ARRAY_A
		);
	}

	/**
	 * Create a new term.
	 *
	 * @since 1.4.0
	 * @param int   $taxonomy_id Taxonomy ID.
	 * @param array $data        Term data (name, slug).
	 * @return int|WP_Error New term ID or error.
	 */
	public static function create_term( $taxonomy_id, $data ) {
		if ( ! self::tables_ready() ) {
			return new WP_Error( 'tables_not_ready', __( 'Database tables not ready.', 'fanfiction-manager' ) );
		}

		$taxonomy_id = absint( $taxonomy_id );
		if ( ! $taxonomy_id ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy ID.', 'fanfiction-manager' ) );
		}

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );

		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_Error( 'invalid_data', __( 'Name and slug are required.', 'fanfiction-manager' ) );
		}

		// Check for duplicate slug within taxonomy.
		if ( self::get_term_by_slug( $taxonomy_id, $slug ) ) {
			return new WP_Error( 'duplicate_slug', __( 'A term with this slug already exists in this taxonomy.', 'fanfiction-manager' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$result = $wpdb->insert(
			$table,
			array(
				'taxonomy_id' => $taxonomy_id,
				'slug'        => $slug,
				'name'        => $name,
				'is_active'   => 1,
				'sort_order'  => 0,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create term.', 'fanfiction-manager' ) );
		}

		self::clear_cache();

		return $wpdb->insert_id;
	}

	/**
	 * Update a term.
	 *
	 * @since 1.4.0
	 * @param int   $id   Term ID.
	 * @param array $data Term data to update.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function update_term( $id, $data ) {
		if ( ! self::tables_ready() ) {
			return new WP_Error( 'tables_not_ready', __( 'Database tables not ready.', 'fanfiction-manager' ) );
		}

		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid term ID.', 'fanfiction-manager' ) );
		}

		$update = array();
		$format = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}

		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) (bool) $data['is_active'];
			$format[]            = '%d';
		}

		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = absint( $data['sort_order'] );
			$format[]             = '%d';
		}

		if ( empty( $update ) ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$result = $wpdb->update( $table, $update, array( 'id' => $id ), $format, array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update term.', 'fanfiction-manager' ) );
		}

		self::clear_cache();

		return true;
	}

	/**
	 * Delete a term and its story relations.
	 *
	 * @since 1.4.0
	 * @param int $id Term ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function delete_term( $id ) {
		if ( ! self::tables_ready() ) {
			return new WP_Error( 'tables_not_ready', __( 'Database tables not ready.', 'fanfiction-manager' ) );
		}

		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid term ID.', 'fanfiction-manager' ) );
		}

		global $wpdb;

		$table_terms     = $wpdb->prefix . 'fanfic_custom_terms';
		$table_relations = $wpdb->prefix . 'fanfic_story_custom_terms';

		// Delete story relations.
		$wpdb->delete( $table_relations, array( 'term_id' => $id ), array( '%d' ) );

		// Delete term.
		$result = $wpdb->delete( $table_terms, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete term.', 'fanfiction-manager' ) );
		}

		self::clear_cache();

		return true;
	}

	// =========================================================================
	// Story Relations
	// =========================================================================

	/**
	 * Get story terms for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int $story_id    Story ID.
	 * @param int $taxonomy_id Taxonomy ID.
	 * @return array Array of term data.
	 */
	public static function get_story_terms( $story_id, $taxonomy_id ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$story_id    = absint( $story_id );
		$taxonomy_id = absint( $taxonomy_id );

		if ( ! $story_id || ! $taxonomy_id ) {
			return array();
		}

		global $wpdb;
		$table_terms     = $wpdb->prefix . 'fanfic_custom_terms';
		$table_relations = $wpdb->prefix . 'fanfic_story_custom_terms';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.* FROM {$table_terms} t
				INNER JOIN {$table_relations} r ON t.id = r.term_id
				WHERE r.story_id = %d AND t.taxonomy_id = %d
				ORDER BY t.sort_order ASC, t.name ASC",
				$story_id,
				$taxonomy_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Get story term IDs for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int $story_id    Story ID.
	 * @param int $taxonomy_id Taxonomy ID.
	 * @return array Array of term IDs.
	 */
	public static function get_story_term_ids( $story_id, $taxonomy_id ) {
		$terms = self::get_story_terms( $story_id, $taxonomy_id );
		return array_column( $terms, 'id' );
	}

	/**
	 * Save story terms for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int   $story_id    Story ID.
	 * @param int   $taxonomy_id Taxonomy ID.
	 * @param array $term_ids    Array of term IDs.
	 * @return bool True on success.
	 */
	public static function save_story_terms( $story_id, $taxonomy_id, $term_ids ) {
		if ( ! self::tables_ready() ) {
			return false;
		}

		$story_id    = absint( $story_id );
		$taxonomy_id = absint( $taxonomy_id );

		if ( ! $story_id || ! $taxonomy_id ) {
			return false;
		}

		$taxonomy = self::get_taxonomy_by_id( $taxonomy_id );
		if ( ! $taxonomy ) {
			return false;
		}

		// For single-select, only keep one term.
		if ( 'single' === $taxonomy['selection_type'] && ! empty( $term_ids ) ) {
			$term_ids = array( reset( $term_ids ) );
		}

		// Filter valid term IDs.
		$term_ids = array_filter( array_map( 'absint', (array) $term_ids ) );
		if ( ! empty( $term_ids ) ) {
			$term_ids = self::filter_valid_term_ids( $taxonomy_id, $term_ids );
		}

		global $wpdb;
		$table_terms     = $wpdb->prefix . 'fanfic_custom_terms';
		$table_relations = $wpdb->prefix . 'fanfic_story_custom_terms';

		// Get current term IDs for this taxonomy.
		$current_term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT r.term_id FROM {$table_relations} r
				INNER JOIN {$table_terms} t ON r.term_id = t.id
				WHERE r.story_id = %d AND t.taxonomy_id = %d",
				$story_id,
				$taxonomy_id
			)
		);

		// Delete old relations for this taxonomy.
		if ( ! empty( $current_term_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $current_term_ids ), '%d' ) );
			$args         = array_merge( array( $story_id ), $current_term_ids );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_relations} WHERE story_id = %d AND term_id IN ({$placeholders})", $args ) );
		}

		// Insert new relations.
		foreach ( $term_ids as $term_id ) {
			$wpdb->insert(
				$table_relations,
				array(
					'story_id' => $story_id,
					'term_id'  => $term_id,
				),
				array( '%d', '%d' )
			);
		}

		return true;
	}

	/**
	 * Filter valid term IDs for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param int   $taxonomy_id Taxonomy ID.
	 * @param array $term_ids    Array of term IDs to filter.
	 * @return array Array of valid term IDs.
	 */
	public static function filter_valid_term_ids( $taxonomy_id, $term_ids ) {
		if ( ! self::tables_ready() || empty( $term_ids ) ) {
			return array();
		}

		$taxonomy_id = absint( $taxonomy_id );
		$term_ids    = array_filter( array_map( 'absint', (array) $term_ids ) );

		if ( ! $taxonomy_id || empty( $term_ids ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_custom_terms';

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		$args         = array_merge( array( $taxonomy_id ), $term_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE taxonomy_id = %d AND id IN ({$placeholders}) AND is_active = 1", $args ) );
	}

	/**
	 * Delete all story relations.
	 *
	 * @since 1.4.0
	 * @param int $story_id Story ID.
	 * @return bool True on success.
	 */
	public static function delete_story_relations( $story_id ) {
		if ( ! self::tables_ready() ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_custom_terms';

		$wpdb->delete( $table, array( 'story_id' => absint( $story_id ) ), array( '%d' ) );

		return true;
	}

	/**
	 * Get story IDs by term slugs for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param string       $taxonomy_slug Taxonomy slug.
	 * @param array|string $term_slugs    Term slug(s).
	 * @return array Array of story IDs.
	 */
	public static function get_story_ids_by_term_slugs( $taxonomy_slug, $term_slugs ) {
		if ( ! self::tables_ready() ) {
			return array();
		}

		$taxonomy = self::get_taxonomy_by_slug( $taxonomy_slug );
		if ( ! $taxonomy ) {
			return array();
		}

		$term_slugs = array_filter( array_map( 'sanitize_title', (array) $term_slugs ) );
		if ( empty( $term_slugs ) ) {
			return array();
		}

		global $wpdb;
		$table_terms     = $wpdb->prefix . 'fanfic_custom_terms';
		$table_relations = $wpdb->prefix . 'fanfic_story_custom_terms';

		$placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );
		$args         = array_merge( array( $taxonomy['id'] ), $term_slugs );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$story_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT r.story_id FROM {$table_relations} r
				INNER JOIN {$table_terms} t ON r.term_id = t.id
				WHERE t.taxonomy_id = %d AND t.slug IN ({$placeholders})",
				$args
			)
		);

		return array_map( 'absint', $story_ids );
	}

	// =========================================================================
	// Shortcodes
	// =========================================================================

	/**
	 * Register shortcodes for all active taxonomies.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function register_shortcodes() {
		$taxonomies = self::get_active_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			$tag = 'story-' . $taxonomy['slug'];

			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, array( __CLASS__, 'render_shortcode' ) );
				self::$registered_shortcodes[] = $tag;
			}
		}
	}

	/**
	 * Render shortcode for a custom taxonomy.
	 *
	 * @since 1.4.0
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts, $content, $tag ) {
		// Extract taxonomy slug from tag (story-{slug}).
		$taxonomy_slug = str_replace( 'story-', '', $tag );
		$taxonomy      = self::get_taxonomy_by_slug( $taxonomy_slug );

		if ( ! $taxonomy || ! $taxonomy['is_active'] ) {
			return '';
		}

		$story_id = 0;
		if ( class_exists( 'Fanfic_Shortcodes' ) && method_exists( 'Fanfic_Shortcodes', 'get_current_story_id' ) ) {
			$story_id = Fanfic_Shortcodes::get_current_story_id();
		}

		if ( ! $story_id ) {
			return '';
		}

		$terms = self::get_story_terms( $story_id, $taxonomy['id'] );
		if ( empty( $terms ) ) {
			return '';
		}

		$label = esc_html( $taxonomy['name'] ) . ':';

		if ( 'single' === $taxonomy['selection_type'] ) {
			// Single select - display like Language.
			$term = reset( $terms );
			return sprintf(
				'<div class="fanfic-story-%s"><strong>%s</strong> <span class="story-%s">%s</span></div>',
				esc_attr( $taxonomy_slug ),
				$label,
				esc_attr( $taxonomy_slug ),
				esc_html( $term['name'] )
			);
		} else {
			// Multi select - display like Fandoms.
			$term_links = array();
			foreach ( $terms as $term ) {
				$term_links[] = sprintf(
					'<span class="story-%s-item">%s</span>',
					esc_attr( $taxonomy_slug ),
					esc_html( $term['name'] )
				);
			}
			return sprintf(
				'<div class="fanfic-story-%s"><strong>%s</strong> %s</div>',
				esc_attr( $taxonomy_slug ),
				$label,
				implode( ', ', $term_links )
			);
		}
	}

	// =========================================================================
	// Utility
	// =========================================================================

	/**
	 * Clear all caches.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function clear_cache() {
		wp_cache_delete( 'all_taxonomies', self::$cache_group );
		wp_cache_delete( 'all_taxonomies_active', self::$cache_group );

		// Clear individual taxonomy caches.
		$taxonomies = self::get_all_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			wp_cache_delete( 'taxonomy_' . $taxonomy['id'], self::$cache_group );
			wp_cache_delete( 'taxonomy_slug_' . $taxonomy['slug'], self::$cache_group );
			wp_cache_delete( 'terms_' . $taxonomy['id'], self::$cache_group );
			wp_cache_delete( 'terms_' . $taxonomy['id'] . '_active', self::$cache_group );
		}
	}

	/**
	 * Get taxonomy stats.
	 *
	 * @since 1.4.0
	 * @param int $taxonomy_id Taxonomy ID.
	 * @return array Stats array with term_count and story_count.
	 */
	public static function get_taxonomy_stats( $taxonomy_id ) {
		if ( ! self::tables_ready() ) {
			return array(
				'term_count'  => 0,
				'story_count' => 0,
			);
		}

		$taxonomy_id = absint( $taxonomy_id );
		if ( ! $taxonomy_id ) {
			return array(
				'term_count'  => 0,
				'story_count' => 0,
			);
		}

		global $wpdb;
		$table_terms     = $wpdb->prefix . 'fanfic_custom_terms';
		$table_relations = $wpdb->prefix . 'fanfic_story_custom_terms';

		$term_count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table_terms} WHERE taxonomy_id = %d", $taxonomy_id )
		);

		$story_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT r.story_id) FROM {$table_relations} r
				INNER JOIN {$table_terms} t ON r.term_id = t.id
				WHERE t.taxonomy_id = %d",
				$taxonomy_id
			)
		);

		return array(
			'term_count'  => $term_count,
			'story_count' => $story_count,
		);
	}
}
