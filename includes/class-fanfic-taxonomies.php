<?php
/**
 * Register Custom Taxonomies for Fanfiction Manager
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Taxonomies
 *
 * Handles registration of custom taxonomies for fanfiction stories.
 * Registers built-in taxonomies (Genre and Status) and supports dynamic custom taxonomies.
 */
class Fanfic_Taxonomies {
	/**
	 * Default terms used when taxonomy is empty.
	 *
	 * @var string[]
	 */
	const DEFAULT_GENRES = array(
		'Romance',
		'Adventure',
		'Drama',
		'Horror',
		'Mystery',
		'Thriller',
		'Crime',
		'Fantasy',
		'Science Fiction',
		'Paranormal',
		'Historical Fiction',
		'Contemporary Lit',
		'Comedy',
		'Non-Fiction',
		'Children',
		'Teen',
		'Young Adult',
		'Short Story',
		'Poetry',
		'LGBTQ+',
	);

	/**
	 * Default genre descriptions keyed by canonical slug.
	 *
	 * @var string[]
	 */
	const DEFAULT_GENRE_DESCRIPTIONS = array(
		'romance'            => 'Stories centered on romantic relationships, emotional bonds, and love in its many forms.',
		'adventure'          => 'Action-driven stories focused on journeys, quests, danger, and discovery.',
		'drama'              => 'Character-focused narratives exploring conflict, relationships, and emotional tension.',
		'horror'             => 'Stories meant to frighten, disturb, or unsettle through fear, suspense, or the macabre.',
		'mystery'            => 'Plots built around secrets, investigations, puzzles, or unanswered questions.',
		'thriller'           => 'Fast-paced, high-tension stories involving danger, urgency, and escalating stakes.',
		'crime'              => 'Stories dealing with criminal acts, investigations, justice, or the underworld.',
		'fantasy'            => 'Fiction set in worlds with magic, mythical creatures, or supernatural systems.',
		'science-fiction'    => 'Stories exploring technology, science, space, or speculative futures.',
		'paranormal'         => 'Fiction involving ghosts, spirits, supernatural phenomena, or unexplained forces.',
		'historical-fiction' => 'Stories set in real past periods, blending fictional narratives with historical settings.',
		'contemporary-lit'   => 'Fiction set in the modern world, focused on realistic characters and present-day issues.',
		'comedy'             => 'Stories written to amuse, entertain, or provoke laughter through humor or satire.',
		'non-fiction'        => 'Works based on real events, facts, personal experiences, or true stories.',
		'children'           => 'Stories written for young readers, with age-appropriate themes and language. Any non-PG story will automatically remove this genre.',
		'teen'               => 'Stories aimed at teenagers, often exploring identity, relationships, and growing up. 18+ warnings will automatically remove this genre.',
		'young-adult'        => 'Narratives focused on young adult characters dealing with complex choices and transitions.',
		'short-story'        => 'Self-contained stories told in a brief format, usually focused on a single plot or idea. More than 10 chapters will automatically remove this genre.',
		'poetry'             => 'Works written in verse, emphasizing rhythm, imagery, and emotional expression.',
		'lgbtq'              => 'Stories centered on LGBTQ+ characters, relationships, or themes of identity and representation.',
	);

	/**
	 * Canonical status defaults keyed by stable slug.
	 *
	 * @var string[]
	 */
	const DEFAULT_STATUSES = array(
		'ongoing'   => 'Ongoing',
		'finished'  => 'Finished',
		'on-hiatus' => 'On Hiatus',
		'abandoned' => 'Abandoned',
	);

	/**
	 * Register all taxonomies.
	 *
	 * @since 1.0.0
	 */
	public static function register() {
		self::register_genre_taxonomy();
		self::register_status_taxonomy();
		self::ensure_default_terms();
		self::register_genre_constraint_hooks();

		// Hook for registering dynamic custom taxonomies (to be implemented)
		do_action( 'fanfic_register_custom_taxonomies' );
	}

	/**
	 * Ensure taxonomy defaults exist.
	 *
	 * Genres follow "empty => seed, non-empty => keep".
	 * Status terms always ensure canonical defaults exist.
	 *
	 * @return void
	 */
	public static function ensure_default_terms() {
		self::ensure_default_genres_if_empty();
		self::ensure_default_statuses();
	}

	/**
	 * Register the Genre taxonomy.
	 *
	 * Hierarchical taxonomy for categorizing stories by genre.
	 * Allows multiple selections (e.g., Romance, Adventure, Drama).
	 *
	 * @since 1.0.0
	 */
	private static function register_genre_taxonomy() {
		$labels = array(
			'name'                       => __( 'Genres', 'fanfiction-manager' ),
			'singular_name'              => __( 'Genre', 'fanfiction-manager' ),
			'menu_name'                  => __( 'Genres', 'fanfiction-manager' ),
			'all_items'                  => __( 'All Genres', 'fanfiction-manager' ),
			'parent_item'                => __( 'Parent Genre', 'fanfiction-manager' ),
			'parent_item_colon'          => __( 'Parent Genre:', 'fanfiction-manager' ),
			'new_item_name'              => __( 'New Genre Name', 'fanfiction-manager' ),
			'add_new_item'               => __( 'Add New Genre', 'fanfiction-manager' ),
			'edit_item'                  => __( 'Edit Genre', 'fanfiction-manager' ),
			'update_item'                => __( 'Update Genre', 'fanfiction-manager' ),
			'view_item'                  => __( 'View Genre', 'fanfiction-manager' ),
			'separate_items_with_commas' => __( 'Separate genres with commas', 'fanfiction-manager' ),
			'add_or_remove_items'        => __( 'Add or remove genres', 'fanfiction-manager' ),
			'choose_from_most_used'      => __( 'Choose from the most used genres', 'fanfiction-manager' ),
			'popular_items'              => __( 'Popular Genres', 'fanfiction-manager' ),
			'search_items'               => __( 'Search Genres', 'fanfiction-manager' ),
			'not_found'                  => __( 'No genres found', 'fanfiction-manager' ),
			'no_terms'                   => __( 'No genres', 'fanfiction-manager' ),
			'items_list'                 => __( 'Genres list', 'fanfiction-manager' ),
			'items_list_navigation'      => __( 'Genres list navigation', 'fanfiction-manager' ),
		);

		$args = array(
			'labels'                     => $labels,
			'description'                => __( 'Story genres for categorizing fanfiction by theme and style', 'fanfiction-manager' ),
			'hierarchical'               => true,
			'public'                     => true,
			'publicly_queryable'         => true,
			'show_ui'                    => true,
			'show_in_menu'               => true,
			'show_in_nav_menus'          => true,
			'show_in_rest'               => true,
			'show_tagcloud'              => true,
			'show_in_quick_edit'         => true,
			'show_admin_column'          => true,
			'query_var'                  => true,
			'rewrite'                    => array(
				'slug'                   => 'genre',
				'with_front'             => false,
				'hierarchical'           => true,
			),
			'meta_box_cb'                => 'post_categories_meta_box',
		);

		register_taxonomy( 'fanfiction_genre', array( 'fanfiction_story' ), $args );
	}

	/**
	 * Register the Status taxonomy.
	 *
	 * Hierarchical taxonomy for story publication status.
	 * Single selection only (e.g., Finished, Ongoing, On Hiatus, Abandoned).
	 *
	 * @since 1.0.0
	 */
	private static function register_status_taxonomy() {
		$labels = array(
			'name'                       => __( 'Story Status', 'fanfiction-manager' ),
			'singular_name'              => __( 'Status', 'fanfiction-manager' ),
			'menu_name'                  => __( 'Status', 'fanfiction-manager' ),
			'all_items'                  => __( 'All Statuses', 'fanfiction-manager' ),
			'parent_item'                => __( 'Parent Status', 'fanfiction-manager' ),
			'parent_item_colon'          => __( 'Parent Status:', 'fanfiction-manager' ),
			'new_item_name'              => __( 'New Status Name', 'fanfiction-manager' ),
			'add_new_item'               => __( 'Add New Status', 'fanfiction-manager' ),
			'edit_item'                  => __( 'Edit Status', 'fanfiction-manager' ),
			'update_item'                => __( 'Update Status', 'fanfiction-manager' ),
			'view_item'                  => __( 'View Status', 'fanfiction-manager' ),
			'separate_items_with_commas' => __( 'Separate statuses with commas', 'fanfiction-manager' ),
			'add_or_remove_items'        => __( 'Add or remove statuses', 'fanfiction-manager' ),
			'choose_from_most_used'      => __( 'Choose from the most used statuses', 'fanfiction-manager' ),
			'popular_items'              => __( 'Popular Statuses', 'fanfiction-manager' ),
			'search_items'               => __( 'Search Statuses', 'fanfiction-manager' ),
			'not_found'                  => __( 'No statuses found', 'fanfiction-manager' ),
			'no_terms'                   => __( 'No statuses', 'fanfiction-manager' ),
			'items_list'                 => __( 'Statuses list', 'fanfiction-manager' ),
			'items_list_navigation'      => __( 'Statuses list navigation', 'fanfiction-manager' ),
		);

		$args = array(
			'labels'                     => $labels,
			'description'                => __( 'Publication status of fanfiction stories', 'fanfiction-manager' ),
			'hierarchical'               => true,
			'public'                     => true,
			'publicly_queryable'         => true,
			'show_ui'                    => true,
			'show_in_menu'               => true,
			'show_in_nav_menus'          => true,
			'show_in_rest'               => true,
			'show_tagcloud'              => false,
			'show_in_quick_edit'         => true,
			'show_admin_column'          => true,
			'query_var'                  => true,
			'rewrite'                    => array(
				'slug'                   => 'status',
				'with_front'             => false,
				'hierarchical'           => true,
			),
			'meta_box_cb'                => 'post_categories_meta_box',
		);

		register_taxonomy( 'fanfiction_status', array( 'fanfiction_story' ), $args );
	}

	/**
	 * Seed default genres only when taxonomy has no terms.
	 *
	 * @return void
	 */
	private static function ensure_default_genres_if_empty() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'fanfiction_genre',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || ! empty( $terms ) ) {
			return;
		}

		foreach ( self::DEFAULT_GENRES as $term_name ) {
			if ( ! term_exists( $term_name, 'fanfiction_genre' ) ) {
				$description = self::get_default_genre_description( $term_name );
				$args = array();
				if ( '' !== $description ) {
					$args['description'] = $description;
				}
				wp_insert_term( $term_name, 'fanfiction_genre', $args );
			}
		}
	}

	/**
	 * Get default description for a default genre label.
	 *
	 * @since 1.0.0
	 * @param string $genre_name Genre display name.
	 * @return string
	 */
	public static function get_default_genre_description( $genre_name ) {
		$slug = sanitize_title( (string) $genre_name );
		return isset( self::DEFAULT_GENRE_DESCRIPTIONS[ $slug ] ) ? (string) self::DEFAULT_GENRE_DESCRIPTIONS[ $slug ] : '';
	}

	/**
	 * Register hooks that enforce built-in genre constraints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_genre_constraint_hooks() {
		add_action( 'save_post_fanfiction_story', array( __CLASS__, 'enforce_constraints_after_story_save' ), 20, 3 );
		add_action( 'save_post_fanfiction_chapter', array( __CLASS__, 'enforce_constraints_after_chapter_save' ), 20, 3 );
	}

	/**
	 * Enforce constrained genres when a story is saved.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Story post ID.
	 * @param WP_Post $post    Story post object.
	 * @return void
	 */
	public static function enforce_constraints_after_story_save( $post_id, $post, $update = false ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! $post || 'fanfiction_story' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		self::enforce_story_genre_constraints( $post_id );
	}

	/**
	 * Enforce constrained genres when a chapter is saved.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Chapter post ID.
	 * @param WP_Post $post    Chapter post object.
	 * @return void
	 */
	public static function enforce_constraints_after_chapter_save( $post_id, $post, $update = false ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! $post || 'fanfiction_chapter' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$story_id = absint( $post->post_parent );
		if ( ! $story_id ) {
			return;
		}

		self::enforce_story_genre_constraints( $story_id );
	}

	/**
	 * Enforce automatic constrained-genre removals for one story.
	 *
	 * Rules:
	 * - Remove "Children" when age rating is non-PG.
	 * - Remove "Teen" when age rating is 18+.
	 * - Remove "Short Story" when chapter count is greater than 10.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story post ID.
	 * @return bool True when terms changed, false otherwise.
	 */
	public static function enforce_story_genre_constraints( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || 'fanfiction_story' !== get_post_type( $story_id ) ) {
			return false;
		}

		$current_genre_ids = wp_get_post_terms(
			$story_id,
			'fanfiction_genre',
			array(
				'fields' => 'ids',
			)
		);

		if ( is_wp_error( $current_genre_ids ) || empty( $current_genre_ids ) ) {
			return false;
		}

		$remove_slugs = array();
		if ( self::story_is_non_pg( $story_id ) ) {
			$remove_slugs[] = 'children';
		}
		if ( self::story_is_18_plus( $story_id ) ) {
			$remove_slugs[] = 'teen';
		}
		if ( self::story_exceeds_short_story_limit( $story_id ) ) {
			$remove_slugs[] = 'short-story';
		}

		if ( empty( $remove_slugs ) ) {
			return false;
		}

		$remove_genre_ids = self::get_genre_term_ids_by_slug( $remove_slugs );
		if ( empty( $remove_genre_ids ) ) {
			return false;
		}

		$current_genre_ids = array_values( array_unique( array_map( 'absint', (array) $current_genre_ids ) ) );
		$remaining_genre_ids = array_values( array_diff( $current_genre_ids, $remove_genre_ids ) );
		if ( count( $remaining_genre_ids ) === count( $current_genre_ids ) ) {
			return false;
		}

		$result = wp_set_post_terms( $story_id, $remaining_genre_ids, 'fanfiction_genre', false );
		if ( is_wp_error( $result ) ) {
			return false;
		}

		if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
			Fanfic_Search_Index::update_index( $story_id );
		}

		return true;
	}

	/**
	 * Determine whether a story's age rating is stricter than default "PG".
	 *
	 * @since 1.0.0
	 * @param int $story_id Story post ID.
	 * @return bool
	 */
	private static function story_is_non_pg( $story_id ) {
		$age_label = self::get_story_age_label( $story_id );
		return 'PG' !== $age_label;
	}

	/**
	 * Determine whether a story age rating is 18+.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story post ID.
	 * @return bool
	 */
	private static function story_is_18_plus( $story_id ) {
		$age_label = self::get_story_age_label( $story_id );
		if ( 'PG' === $age_label || '' === $age_label ) {
			return false;
		}

		$numeric = str_replace( '+', '', $age_label );
		return is_numeric( $numeric ) && absint( $numeric ) >= 18;
	}

	/**
	 * Get normalized story age label (defaults to PG when unset).
	 *
	 * @since 1.0.0
	 * @param int $story_id Story post ID.
	 * @return string
	 */
	private static function get_story_age_label( $story_id ) {
		$age_label = trim( (string) get_post_meta( $story_id, '_fanfic_age_rating', true ) );

		$age_label = strtoupper( $age_label );
		return '' !== $age_label ? $age_label : 'PG';
	}

	/**
	 * Check whether chapter count violates short-story limit.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story post ID.
	 * @return bool
	 */
	private static function story_exceeds_short_story_limit( $story_id ) {
		global $wpdb;

		$chapter_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1)
				FROM {$wpdb->posts}
				WHERE post_parent = %d
				AND post_type = %s
				AND post_status NOT IN ('trash', 'auto-draft')",
				$story_id,
				'fanfiction_chapter'
			)
		);

		return $chapter_count > 10;
	}

	/**
	 * Get genre term IDs for canonical slugs.
	 *
	 * @since 1.0.0
	 * @param string[] $slugs Genre slugs.
	 * @return int[]
	 */
	private static function get_genre_term_ids_by_slug( $slugs ) {
		$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $slugs ) ) ) );
		if ( empty( $slugs ) ) {
			return array();
		}

		$term_ids = get_terms(
			array(
				'taxonomy'   => 'fanfiction_genre',
				'hide_empty' => false,
				'slug'       => $slugs,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'absint', (array) $term_ids ) ) );
	}

	/**
	 * Ensure canonical status defaults exist.
	 *
	 * Keeps renamed terms by tracking IDs in an option map.
	 *
	 * @return void
	 */
	private static function ensure_default_statuses() {
		$map = get_option( 'fanfic_default_status_term_ids', array() );
		$map = is_array( $map ) ? $map : array();

		foreach ( self::DEFAULT_STATUSES as $slug => $label ) {
			$term_id = isset( $map[ $slug ] ) ? absint( $map[ $slug ] ) : 0;

			// If we already track a term ID and it still exists, keep it (renamed is fine).
			if ( $term_id > 0 ) {
				$term = get_term( $term_id, 'fanfiction_status' );
				if ( $term && ! is_wp_error( $term ) ) {
					continue;
				}
			}

			// Try to recover by canonical slug.
			$existing = get_term_by( 'slug', $slug, 'fanfiction_status' );
			if ( $existing && ! is_wp_error( $existing ) ) {
				$map[ $slug ] = (int) $existing->term_id;
				continue;
			}

			// Create missing default term.
			$created = wp_insert_term(
				$label,
				'fanfiction_status',
				array(
					'slug' => $slug,
				)
			);

			if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
				$map[ $slug ] = (int) $created['term_id'];
			}
		}

		update_option( 'fanfic_default_status_term_ids', $map );
	}

	/**
	 * Get all registered genre terms.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Array of term objects or WP_Error on failure.
	 */
	public static function get_genres() {
		return get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );
	}

	/**
	 * Get all registered status terms.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Array of term objects or WP_Error on failure.
	 */
	public static function get_statuses() {
		return get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );
	}

	/**
	 * Get the primary status for a story.
	 *
	 * Since status should be single selection, this returns the first status term.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return WP_Term|false|WP_Error Term object, false if no terms, or WP_Error on failure.
	 */
	public static function get_story_status( $story_id ) {
		$terms = wp_get_post_terms( $story_id, 'fanfiction_status' );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		return ! empty( $terms ) ? $terms[0] : false;
	}

	/**
	 * Get all genres for a story.
	 *
	 * @since 1.0.0
	 * @param int $story_id The story post ID.
	 * @return array|WP_Error Array of term objects or WP_Error on failure.
	 */
	public static function get_story_genres( $story_id ) {
		return wp_get_post_terms( $story_id, 'fanfiction_genre' );
	}

	/**
	 * Set the status for a story.
	 *
	 * Replaces any existing status with the new one (single selection).
	 *
	 * @since 1.0.0
	 * @param int        $story_id   The story post ID.
	 * @param int|string $status     The status term ID or slug.
	 * @return array|false|WP_Error Affected term IDs, false on failure, or WP_Error.
	 */
	public static function set_story_status( $story_id, $status ) {
		return wp_set_post_terms( $story_id, $status, 'fanfiction_status', false );
	}

	/**
	 * Set genres for a story.
	 *
	 * @since 1.0.0
	 * @param int          $story_id The story post ID.
	 * @param array|string $genres   Array of genre term IDs or slugs, or comma-separated string.
	 * @param bool         $append   Whether to append genres or replace existing ones. Default false.
	 * @return array|false|WP_Error Affected term IDs, false on failure, or WP_Error.
	 */
	public static function set_story_genres( $story_id, $genres, $append = false ) {
		return wp_set_post_terms( $story_id, $genres, 'fanfiction_genre', $append );
	}

	/**
	 * Check if a story has a specific status.
	 *
	 * @since 1.0.0
	 * @param int        $story_id The story post ID.
	 * @param int|string $status   The status term ID, name, or slug.
	 * @return bool True if story has the status, false otherwise.
	 */
	public static function story_has_status( $story_id, $status ) {
		return has_term( $status, 'fanfiction_status', $story_id );
	}

	/**
	 * Check if a story has a specific genre.
	 *
	 * @since 1.0.0
	 * @param int        $story_id The story post ID.
	 * @param int|string $genre    The genre term ID, name, or slug.
	 * @return bool True if story has the genre, false otherwise.
	 */
	public static function story_has_genre( $story_id, $genre ) {
		return has_term( $genre, 'fanfiction_genre', $story_id );
	}

	/**
	 * Get stories by status.
	 *
	 * @since 1.0.0
	 * @param string|int $status The status term slug or ID.
	 * @param array      $args   Optional. Additional query arguments.
	 * @return WP_Query Query object containing stories with the specified status.
	 */
	public static function get_stories_by_status( $status, $args = array() ) {
		$default_args = array(
			'post_type'      => 'fanfiction_story',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'fanfiction_status',
					'field'    => is_numeric( $status ) ? 'term_id' : 'slug',
					'terms'    => $status,
				),
			),
		);

		$query_args = wp_parse_args( $args, $default_args );
		return new WP_Query( $query_args );
	}

	/**
	 * Get stories by genre.
	 *
	 * @since 1.0.0
	 * @param string|int|array $genres The genre term slug(s) or ID(s).
	 * @param array            $args   Optional. Additional query arguments.
	 * @return WP_Query Query object containing stories with the specified genre(s).
	 */
	public static function get_stories_by_genre( $genres, $args = array() ) {
		$default_args = array(
			'post_type'      => 'fanfiction_story',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'fanfiction_genre',
					'field'    => is_numeric( $genres ) || ( is_array( $genres ) && is_numeric( $genres[0] ) ) ? 'term_id' : 'slug',
					'terms'    => $genres,
				),
			),
		);

		$query_args = wp_parse_args( $args, $default_args );
		return new WP_Query( $query_args );
	}
}
