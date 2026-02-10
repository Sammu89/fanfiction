<?php
/**
 * Translations Class
 *
 * Handles story translation group linking: bidirectional translation groups,
 * REST API for searching author's stories, and display helpers.
 *
 * @package FanfictionManager
 * @since 1.5.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Translations
 */
class Fanfic_Translations {

	const REST_NAMESPACE = 'fanfic/v1';

	/**
	 * Runtime cache for preloaded translation data.
	 *
	 * @var array
	 */
	private static $preloaded = array();

	/**
	 * Runtime cache for chapter translation matches.
	 *
	 * @var array
	 */
	private static $chapter_matches = array();

	/**
	 * Initialize translations feature
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_relations' ) );
	}

	/**
	 * Check if translation linking is enabled (requires language classification)
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function is_enabled() {
		return class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled();
	}

	// =========================================================================
	// REST API
	// =========================================================================

	/**
	 * Register REST routes
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/translations/search-stories',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_story_search' ),
				'permission_callback' => array( __CLASS__, 'can_search' ),
				'args'                => array(
					'q'        => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'story_id' => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
					),
					'current_language_id' => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
					),
					'limit'    => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/translations/inheritance-data',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_inheritance_data' ),
				'permission_callback' => array( __CLASS__, 'can_search' ),
				'args'                => array(
					'source_story_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission callback for search
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function can_search() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Handle story search for translation linking
	 *
	 * Returns the current user's published stories with language info,
	 * excluding: the current story, stories without a language, stories
	 * with the same language as the current story.
	 *
	 * @since 1.5.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_story_search( $request ) {
		$query_text = trim( (string) $request->get_param( 'q' ) );
		$story_id   = absint( $request->get_param( 'story_id' ) );
		$current_language_id = absint( $request->get_param( 'current_language_id' ) );
		$limit      = min( 50, max( 1, absint( $request->get_param( 'limit' ) ) ) );

		$current_user_id = get_current_user_id();

		// Get current story's language (edit mode), or fallback to provided form language (create mode).
		$current_lang_id = null;
		if ( $story_id && class_exists( 'Fanfic_Languages' ) ) {
			$current_lang_id = Fanfic_Languages::get_story_language_id( $story_id );
		} elseif ( $current_language_id > 0 ) {
			$current_lang_id = $current_language_id;
		}

		// Get current group members (if any) to identify already-linked stories
		$current_group_id   = self::get_group_id( $story_id );
		$current_group_lang_ids = array();
		if ( $story_id && $current_group_id ) {
			$group_stories = self::get_group_stories( $current_group_id );
			foreach ( $group_stories as $gs_id ) {
				if ( (int) $gs_id === $story_id ) {
					continue;
				}
				$lang = Fanfic_Languages::get_story_language_id( $gs_id );
				if ( $lang ) {
					$current_group_lang_ids[ $gs_id ] = $lang;
				}
			}
		}

		// Query user's published stories
		$args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'author'         => $current_user_id,
			'posts_per_page' => $limit * 2, // Fetch extra to compensate for filtering
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $story_id ) {
			$args['post__not_in'] = array( $story_id );
		}

		if ( ! empty( $query_text ) ) {
			$args['s'] = $query_text;
		}

		$query   = new WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() && count( $results ) < $limit ) {
				$query->the_post();
				$sid = get_the_ID();

				// Get this story's language
				$lang = Fanfic_Languages::get_story_language( $sid );

				// Skip stories without a language
				if ( ! $lang ) {
					continue;
				}

				$lang_id = (int) $lang['id'];

				// Skip stories with same language as current story
				if ( $current_lang_id && $lang_id === (int) $current_lang_id ) {
					continue;
				}

				// Skip stories whose language already exists in the current group
				// (from a different story)
				if ( ! empty( $current_group_lang_ids ) && in_array( $lang_id, array_values( $current_group_lang_ids ), true ) ) {
					// But allow if this story IS the one already in the group with that language
					if ( ! array_key_exists( $sid, $current_group_lang_ids ) ) {
						continue;
					}
				}

				// Check if story is already in a DIFFERENT translation group
				$other_group = self::get_group_id( $sid );
				if ( $story_id && $other_group && $current_group_id && $other_group !== $current_group_id ) {
					continue; // Already belongs to another group
				}
				if ( $story_id && $other_group && ! $current_group_id ) {
					continue; // Story is in a group but current story isn't - would need merge logic
				}

				$lang_label = $lang['name'];
				if ( ! empty( $lang['native_name'] ) && $lang['native_name'] !== $lang['name'] ) {
					$lang_label .= ' (' . $lang['native_name'] . ')';
				}

				$results[] = array(
					'id'             => $sid,
					'title'          => get_the_title( $sid ),
					'label'          => get_the_title( $sid ) . ' - ' . $lang_label,
					'language_id'    => $lang_id,
					'language_name'  => $lang['name'],
					'language_native' => $lang['native_name'],
					'language_slug'  => $lang['slug'],
					'language_label' => $lang_label,
				);
			}
			wp_reset_postdata();
		}

		return rest_ensure_response( $results );
	}

	/**
	 * Get source story classification data for translation inheritance.
	 *
	 * Used by the story form to prefill classification fields when the user
	 * marks a story as translated from another story.
	 *
	 * @since 1.5.2
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_inheritance_data( $request ) {
		$source_story_id = absint( $request->get_param( 'source_story_id' ) );
		if ( ! $source_story_id ) {
			return new WP_Error( 'invalid_story', __( 'Invalid source story.', 'fanfiction-manager' ), array( 'status' => 400 ) );
		}

		$source_story = get_post( $source_story_id );
		if ( ! $source_story || 'fanfiction_story' !== $source_story->post_type ) {
			return new WP_Error( 'story_not_found', __( 'Source story not found.', 'fanfiction-manager' ), array( 'status' => 404 ) );
		}

		$current_user_id = get_current_user_id();
		$can_edit_story  = current_user_can( 'edit_fanfiction_story', $source_story_id );
		$is_owner        = (int) $source_story->post_author === (int) $current_user_id;
		$has_override    = current_user_can( 'edit_others_fanfiction_stories' ) || current_user_can( 'manage_options' ) || current_user_can( 'moderate_fanfiction' );
		if ( ! $can_edit_story && ! $is_owner && ! $has_override ) {
			return new WP_Error( 'forbidden', __( 'You cannot access inheritance data for this story.', 'fanfiction-manager' ), array( 'status' => 403 ) );
		}

		$genre_ids = wp_get_object_terms( $source_story_id, 'fanfiction_genre', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $genre_ids ) ) {
			$genre_ids = array();
		}
		$genre_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $genre_ids ) ) ) );

		$status_ids = wp_get_object_terms( $source_story_id, 'fanfiction_status', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $status_ids ) ) {
			$status_ids = array();
		}
		$status_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $status_ids ) ) ) );
		$status_id  = ! empty( $status_ids ) ? $status_ids[0] : 0;

		$is_original_work = false;
		$fandom_ids       = array();
		$fandoms          = array();
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			$fandom_ids = Fanfic_Fandoms::get_story_fandom_ids( $source_story_id );
			$fandom_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $fandom_ids ) ) ) );
			$fandom_rows = Fanfic_Fandoms::get_story_fandom_labels( $source_story_id, true );
			foreach ( (array) $fandom_rows as $row ) {
				$fandom_id = absint( $row['id'] ?? 0 );
				$label     = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				if ( $fandom_id && '' !== $label ) {
					$fandoms[] = array(
						'id'    => $fandom_id,
						'label' => $label,
					);
				}
			}
			$is_original_work = (bool) get_post_meta( $source_story_id, Fanfic_Fandoms::META_ORIGINAL, true );
		}

		$warning_ids = array();
		if ( class_exists( 'Fanfic_Warnings' ) && method_exists( 'Fanfic_Warnings', 'get_story_warning_ids' ) ) {
			$warning_ids = Fanfic_Warnings::get_story_warning_ids( $source_story_id );
			$warning_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $warning_ids ) ) ) );
		}

		$custom_taxonomies = array();
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$active_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( (array) $active_taxonomies as $taxonomy ) {
				$taxonomy_id = absint( $taxonomy['id'] ?? 0 );
				$slug        = sanitize_key( (string) ( $taxonomy['slug'] ?? '' ) );
				if ( ! $taxonomy_id || '' === $slug ) {
					continue;
				}

				$term_ids = Fanfic_Custom_Taxonomies::get_story_term_ids( $source_story_id, $taxonomy_id );
				$term_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $term_ids ) ) ) );

				$selection_type = ( isset( $taxonomy['selection_type'] ) && 'single' === $taxonomy['selection_type'] ) ? 'single' : 'multi';

				$custom_taxonomies[] = array(
					'id'             => $taxonomy_id,
					'slug'           => $slug,
					'selection_type' => $selection_type,
					'term_ids'       => $term_ids,
				);
			}
		}

		return rest_ensure_response(
			array(
				'story_id'         => $source_story_id,
				'genre_ids'        => $genre_ids,
				'status_id'        => absint( $status_id ),
				'status_ids'       => $status_ids,
				'is_original_work' => $is_original_work ? 1 : 0,
				'fandom_ids'       => $fandom_ids,
				'fandoms'          => $fandoms,
				'warning_ids'      => $warning_ids,
				'custom_taxonomies'=> $custom_taxonomies,
			)
		);
	}

	// =========================================================================
	// GROUP MANAGEMENT
	// =========================================================================

	/**
	 * Get translation group ID for a story
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID.
	 * @return int|null Group ID or null if not in a group.
	 */
	public static function get_group_id( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::table_ready() ) {
			return null;
		}

		// Check preloaded cache
		if ( isset( self::$preloaded[ $story_id ] ) ) {
			return self::$preloaded[ $story_id ]['group_id'];
		}

		global $wpdb;
		$table = self::get_translations_table();

		$group_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT group_id FROM {$table} WHERE story_id = %d LIMIT 1",
				$story_id
			)
		);

		return $group_id ? absint( $group_id ) : null;
	}

	/**
	 * Get all story IDs in a translation group
	 *
	 * @since 1.5.0
	 * @param int $group_id Group ID.
	 * @return int[] Story IDs.
	 */
	public static function get_group_stories( $group_id ) {
		$group_id = absint( $group_id );
		if ( ! $group_id || ! self::table_ready() ) {
			return array();
		}

		global $wpdb;
		$table = self::get_translations_table();

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT story_id FROM {$table} WHERE group_id = %d",
				$group_id
			)
		);

		return array_map( 'absint', (array) $results );
	}

	/**
	 * Get translation sibling stories (all other stories in the same group)
	 *
	 * Returns rich data for each sibling: story_id, title, permalink, language info.
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID.
	 * @return array Array of sibling data arrays.
	 */
	public static function get_translation_siblings( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		$group_id = self::get_group_id( $story_id );
		if ( ! $group_id ) {
			return array();
		}

		$group_stories = self::get_group_stories( $group_id );
		$siblings = array();

		foreach ( $group_stories as $sibling_id ) {
			if ( $sibling_id === $story_id ) {
				continue;
			}

			$sibling_post = get_post( $sibling_id );
			if ( ! $sibling_post || 'publish' !== $sibling_post->post_status ) {
				continue;
			}

			$lang = Fanfic_Languages::get_story_language( $sibling_id );
			$lang_label = '';
			$lang_slug  = '';
			if ( $lang ) {
				$lang_label = $lang['name'];
				if ( ! empty( $lang['native_name'] ) && $lang['native_name'] !== $lang['name'] ) {
					$lang_label .= ' (' . $lang['native_name'] . ')';
				}
				$lang_slug = $lang['slug'];
			}

			$siblings[] = array(
				'story_id'       => $sibling_id,
				'title'          => get_the_title( $sibling_id ),
				'permalink'      => get_permalink( $sibling_id ),
				'language_label' => $lang_label,
				'language_slug'  => $lang_slug,
				'language_name'  => $lang ? $lang['name'] : '',
				'language_native' => $lang ? $lang['native_name'] : '',
			);
		}

		return $siblings;
	}

	/**
	 * Get translated sibling chapters matching current chapter structure.
	 *
	 * Matches are based on chapter structure identity:
	 * - _fanfic_chapter_type
	 * - _fanfic_chapter_number
	 *
	 * Only published matched chapters are returned.
	 *
	 * @since 1.5.1
	 * @param int $chapter_id Chapter ID.
	 * @return array[] Array of matched chapter data.
	 */
	public static function get_chapter_translation_siblings( $chapter_id ) {
		$chapter_id = absint( $chapter_id );
		if ( ! $chapter_id || ! self::is_enabled() ) {
			return array();
		}

		$chapter_post = get_post( $chapter_id );
		if ( ! $chapter_post || 'fanfiction_chapter' !== $chapter_post->post_type ) {
			return array();
		}

		$story_id = absint( $chapter_post->post_parent );
		if ( ! $story_id ) {
			return array();
		}

		$chapter_type = sanitize_key( (string) get_post_meta( $chapter_id, '_fanfic_chapter_type', true ) );
		if ( '' === $chapter_type ) {
			$chapter_type = 'chapter';
		}
		$chapter_number = absint( get_post_meta( $chapter_id, '_fanfic_chapter_number', true ) );
		if ( 'chapter' === $chapter_type && $chapter_number < 1 ) {
			return array();
		}

		$cache_key = $story_id . '|' . $chapter_type . '|' . $chapter_number;
		if ( isset( self::$chapter_matches[ $cache_key ] ) ) {
			return self::$chapter_matches[ $cache_key ];
		}

		$story_siblings = self::get_translation_siblings( $story_id );
		if ( empty( $story_siblings ) ) {
			self::$chapter_matches[ $cache_key ] = array();
			return array();
		}

		$sibling_story_ids = array_map( 'absint', wp_list_pluck( $story_siblings, 'story_id' ) );
		$sibling_story_ids = array_values( array_filter( array_unique( $sibling_story_ids ) ) );
		if ( empty( $sibling_story_ids ) ) {
			self::$chapter_matches[ $cache_key ] = array();
			return array();
		}

		$matched_chapter_ids = get_posts(
			array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent__in'=> $sibling_story_ids,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_fanfic_chapter_type',
						'value' => $chapter_type,
					),
					array(
						'key'   => '_fanfic_chapter_number',
						'value' => $chapter_number,
						'type'  => 'NUMERIC',
					),
				),
			)
		);

		if ( empty( $matched_chapter_ids ) ) {
			self::$chapter_matches[ $cache_key ] = array();
			return array();
		}

		$chapter_by_story = array();
		foreach ( (array) $matched_chapter_ids as $matched_chapter_id ) {
			$matched_chapter_id = absint( $matched_chapter_id );
			if ( ! $matched_chapter_id ) {
				continue;
			}
			$matched_story_id = absint( get_post_field( 'post_parent', $matched_chapter_id ) );
			if ( ! $matched_story_id || isset( $chapter_by_story[ $matched_story_id ] ) ) {
				continue;
			}
			$chapter_by_story[ $matched_story_id ] = $matched_chapter_id;
		}

		$results = array();
		foreach ( $story_siblings as $sibling ) {
			$sibling_story_id = absint( $sibling['story_id'] );
			if ( ! isset( $chapter_by_story[ $sibling_story_id ] ) ) {
				continue;
			}
			$matched_chapter_id = $chapter_by_story[ $sibling_story_id ];
			$results[] = array(
				'story_id'       => $sibling_story_id,
				'chapter_id'     => $matched_chapter_id,
				'permalink'      => get_permalink( $matched_chapter_id ),
				'story_title'    => $sibling['title'],
				'language_label' => $sibling['language_label'],
				'language_slug'  => $sibling['language_slug'],
			);
		}

		self::$chapter_matches[ $cache_key ] = $results;

		return $results;
	}

	/**
	 * Save story translations from form submission
	 *
	 * Compares current group members with desired linked_story_ids.
	 * Adds new links, removes unlinked stories.
	 *
	 * @since 1.5.0
	 * @param int   $story_id        Current story ID.
	 * @param int[] $linked_story_ids Desired linked story IDs (from form).
	 * @return true|WP_Error
	 */
	public static function save_story_translations( $story_id, $linked_story_ids ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::table_ready() ) {
			return true;
		}

		$linked_story_ids = array_map( 'absint', array_filter( (array) $linked_story_ids ) );
		$current_user_id  = get_current_user_id();
		$group_id_before = self::get_group_id( $story_id );

		// Get current siblings
		$current_siblings = self::get_translation_siblings( $story_id );
		$current_sibling_ids = wp_list_pluck( $current_siblings, 'story_id' );
		$current_sibling_ids = array_map( 'absint', $current_sibling_ids );

		// Determine additions and removals
		$to_add    = array_diff( $linked_story_ids, $current_sibling_ids );
		$to_remove = array_diff( $current_sibling_ids, $linked_story_ids );

		// Process removals first
		foreach ( $to_remove as $remove_id ) {
			self::remove_from_group( $remove_id );
		}

		// If all siblings removed and no new ones, remove current story from group too
		if ( empty( $linked_story_ids ) ) {
			self::remove_from_group( $story_id );
			do_action( 'fanfic_translations_updated', $story_id, absint( $group_id_before ) );
			return true;
		}

		// Process additions
		foreach ( $to_add as $add_id ) {
			$result = self::add_to_group( $story_id, $add_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$group_id_after = self::get_group_id( $story_id );
		do_action( 'fanfic_translations_updated', $story_id, absint( $group_id_after ) );

		return true;
	}

	/**
	 * Add a story to another story's translation group
	 *
	 * @since 1.5.0
	 * @param int $story_a_id First story ID.
	 * @param int $story_b_id Second story ID to add.
	 * @return true|WP_Error
	 */
	public static function add_to_group( $story_a_id, $story_b_id ) {
		$story_a_id = absint( $story_a_id );
		$story_b_id = absint( $story_b_id );

		if ( ! $story_a_id || ! $story_b_id || ! self::table_ready() ) {
			return new WP_Error( 'invalid_input', __( 'Invalid story IDs.', 'fanfiction-manager' ) );
		}

		// Validate both are fanfiction_story posts
		$post_a = get_post( $story_a_id );
		$post_b = get_post( $story_b_id );
		if ( ! $post_a || 'fanfiction_story' !== $post_a->post_type || ! $post_b || 'fanfiction_story' !== $post_b->post_type ) {
			return new WP_Error( 'invalid_post', __( 'One or both stories not found.', 'fanfiction-manager' ) );
		}

		// Validate same author
		$current_user_id = get_current_user_id();
		if ( (int) $post_a->post_author !== $current_user_id || (int) $post_b->post_author !== $current_user_id ) {
			return new WP_Error( 'not_author', __( 'You can only link your own stories.', 'fanfiction-manager' ) );
		}

		// Validate both have languages
		$lang_a = Fanfic_Languages::get_story_language_id( $story_a_id );
		$lang_b = Fanfic_Languages::get_story_language_id( $story_b_id );
		if ( ! $lang_a || ! $lang_b ) {
			return new WP_Error( 'no_language', __( 'Both stories must have a language set.', 'fanfiction-manager' ) );
		}

		// Validate different languages
		if ( (int) $lang_a === (int) $lang_b ) {
			return new WP_Error( 'same_language', __( 'Stories in the same language cannot be linked as translations.', 'fanfiction-manager' ) );
		}

		global $wpdb;
		$table = self::get_translations_table();

		$group_a = self::get_group_id( $story_a_id );
		$group_b = self::get_group_id( $story_b_id );

		if ( $group_a && $group_b && $group_a === $group_b ) {
			// Already in the same group
			return true;
		}

		if ( ! $group_a && ! $group_b ) {
			// Neither has a group - create new group
			$new_group_id = self::get_next_group_id();

			$wpdb->insert( $table, array( 'group_id' => $new_group_id, 'story_id' => $story_a_id ), array( '%d', '%d' ) );
			$wpdb->insert( $table, array( 'group_id' => $new_group_id, 'story_id' => $story_b_id ), array( '%d', '%d' ) );

		} elseif ( $group_a && ! $group_b ) {
			// A has group, B doesn't - validate language not duplicate in group
			$error = self::validate_language_unique_in_group( $group_a, $lang_b, $story_b_id );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$wpdb->insert( $table, array( 'group_id' => $group_a, 'story_id' => $story_b_id ), array( '%d', '%d' ) );

		} elseif ( ! $group_a && $group_b ) {
			// B has group, A doesn't - validate language not duplicate in group
			$error = self::validate_language_unique_in_group( $group_b, $lang_a, $story_a_id );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$wpdb->insert( $table, array( 'group_id' => $group_b, 'story_id' => $story_a_id ), array( '%d', '%d' ) );

		} else {
			// Both have different groups - merge: move all from group_b into group_a
			// First validate no language conflicts
			$stories_in_a = self::get_group_stories( $group_a );
			$stories_in_b = self::get_group_stories( $group_b );

			$langs_in_a = array();
			foreach ( $stories_in_a as $sid ) {
				$l = Fanfic_Languages::get_story_language_id( $sid );
				if ( $l ) {
					$langs_in_a[] = (int) $l;
				}
			}

			foreach ( $stories_in_b as $sid ) {
				$l = Fanfic_Languages::get_story_language_id( $sid );
				if ( $l && in_array( (int) $l, $langs_in_a, true ) ) {
					return new WP_Error( 'duplicate_language', __( 'Cannot merge: both groups contain a story in the same language.', 'fanfiction-manager' ) );
				}
			}

			// Merge: update all group_b stories to group_a
			$wpdb->update(
				$table,
				array( 'group_id' => $group_a ),
				array( 'group_id' => $group_b ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Clear preload cache
		self::$preloaded = array();

		$final_group_id = self::get_group_id( $story_a_id );
		do_action( 'fanfic_translations_updated', $story_a_id, absint( $final_group_id ) );
		do_action( 'fanfic_translations_updated', $story_b_id, absint( $final_group_id ) );

		return true;
	}

	/**
	 * Remove a story from its translation group
	 *
	 * If the group then has fewer than 2 members, delete remaining entries.
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function remove_from_group( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id || ! self::table_ready() ) {
			return;
		}

		$group_id = self::get_group_id( $story_id );
		if ( ! $group_id ) {
			return;
		}

		$affected_story_ids = self::get_group_stories( $group_id );

		global $wpdb;
		$table = self::get_translations_table();

		// Remove this story
		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		// Check remaining members
		$remaining = self::get_group_stories( $group_id );
		if ( count( $remaining ) < 2 ) {
			// Group has 0 or 1 members - delete remaining entries
			$wpdb->delete( $table, array( 'group_id' => $group_id ), array( '%d' ) );
		}

		// Clear preload cache
		self::$preloaded = array();

		foreach ( (array) $affected_story_ids as $affected_story_id ) {
			$affected_story_id = absint( $affected_story_id );
			if ( $affected_story_id ) {
				do_action( 'fanfic_translations_updated', $affected_story_id, absint( $group_id ) );
			}
		}
	}

	/**
	 * Cleanup story relations on deletion
	 *
	 * @since 1.5.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function cleanup_story_relations( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			return;
		}
		self::remove_from_group( $post_id );
	}

	// =========================================================================
	// BATCH PRELOADING (performance for search results)
	// =========================================================================

	/**
	 * Preload translation group data for multiple story IDs
	 *
	 * Call this before rendering a list of story cards to avoid N+1 queries.
	 *
	 * @since 1.5.0
	 * @param int[] $story_ids Array of story IDs.
	 * @return void
	 */
	public static function preload_groups( $story_ids ) {
		if ( ! self::table_ready() || empty( $story_ids ) ) {
			return;
		}

		$story_ids = array_map( 'absint', array_filter( (array) $story_ids ) );
		if ( empty( $story_ids ) ) {
			return;
		}

		global $wpdb;
		$table = self::get_translations_table();
		$placeholders = implode( ',', array_fill( 0, count( $story_ids ), '%d' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT story_id, group_id FROM {$table} WHERE story_id IN ({$placeholders})",
				$story_ids
			),
			ARRAY_A
		);

		// Store in preload cache
		foreach ( $story_ids as $sid ) {
			self::$preloaded[ $sid ] = array( 'group_id' => null );
		}
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				self::$preloaded[ (int) $row['story_id'] ] = array(
					'group_id' => absint( $row['group_id'] ),
				);
			}
		}
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Validate that a language is unique within a group
	 *
	 * @since 1.5.0
	 * @param int $group_id    Group ID.
	 * @param int $language_id Language ID to check.
	 * @param int $exclude_story_id Story ID to exclude from check.
	 * @return true|WP_Error
	 */
	private static function validate_language_unique_in_group( $group_id, $language_id, $exclude_story_id = 0 ) {
		$group_stories = self::get_group_stories( $group_id );

		foreach ( $group_stories as $existing_id ) {
			if ( $exclude_story_id && (int) $existing_id === (int) $exclude_story_id ) {
				continue;
			}
			$existing_lang = Fanfic_Languages::get_story_language_id( $existing_id );
			if ( $existing_lang && (int) $existing_lang === (int) $language_id ) {
				return new WP_Error(
					'duplicate_language',
					__( 'A story in this language already exists in this translation group.', 'fanfiction-manager' )
				);
			}
		}

		return true;
	}

	/**
	 * Get next available group ID
	 *
	 * @since 1.5.0
	 * @return int
	 */
	private static function get_next_group_id() {
		global $wpdb;
		$table = self::get_translations_table();

		$max = $wpdb->get_var( "SELECT COALESCE(MAX(group_id), 0) FROM {$table}" );

		return absint( $max ) + 1;
	}

	/**
	 * Check if translations table exists
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public static function table_ready() {
		global $wpdb;
		$table = self::get_translations_table();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Get translations table name
	 *
	 * @since 1.5.0
	 * @return string
	 */
	private static function get_translations_table() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_story_translations';
	}
}
