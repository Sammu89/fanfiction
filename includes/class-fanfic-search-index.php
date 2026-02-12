<?php
/**
 * Search Index Class
 *
 * Handles pre-computed search indexing for stories.
 * Indexes: title, intro, author name, chapter titles, visible tags, invisible tags.
 *
 * @package Fanfiction_Manager
 * @since 1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Search_Index
 *
 * @since 1.2.0
 */
class Fanfic_Search_Index {

	/**
	 * Initialize search index hooks
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init() {
		// Story hooks
		add_action( 'save_post_fanfiction_story', array( __CLASS__, 'on_story_save' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_story_delete' ) );

		// Chapter hooks
		add_action( 'save_post_fanfiction_chapter', array( __CLASS__, 'on_chapter_save' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_chapter_delete' ) );

		// Author profile update hook
		add_action( 'profile_update', array( __CLASS__, 'on_author_profile_update' ), 10, 2 );

		// Tag update hook (custom action that should be fired when tags are saved)
		add_action( 'fanfic_tags_updated', array( __CLASS__, 'on_tags_updated' ), 10, 1 );
		add_action( 'fanfic_translations_updated', array( __CLASS__, 'on_translations_updated' ), 10, 2 );
	}

	/**
	 * Check if search index table exists
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private static function table_ready() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Check if search filter map table exists.
	 *
	 * @since 1.5.2
	 * @return bool
	 */
	private static function filter_map_table_ready() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_filter_map';
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Normalize facet values into unique slug list.
	 *
	 * @since 1.5.2
	 * @param string[] $values Raw values.
	 * @return string[] Normalized slugs.
	 */
	private static function normalize_facet_values( $values ) {
		$normalized = array();

		foreach ( (array) $values as $value ) {
			$value = sanitize_title( (string) $value );
			if ( '' !== $value ) {
				$normalized[] = $value;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Add a facet row to sync payload.
	 *
	 * @since 1.5.2
	 * @param array  $rows Facet rows map.
	 * @param string $facet_type Facet type.
	 * @param string $facet_value Facet value.
	 * @return void
	 */
	private static function add_facet_row( &$rows, $facet_type, $facet_value ) {
		$facet_type = strtolower( trim( sanitize_text_field( (string) $facet_type ) ) );
		$facet_value = sanitize_title( (string) $facet_value );
		if ( '' === $facet_type || '' === $facet_value ) {
			return;
		}

		$key = $facet_type . '|' . $facet_value;
		$rows[ $key ] = array(
			'facet_type'  => $facet_type,
			'facet_value' => $facet_value,
		);
	}

	/**
	 * Build facet rows for a story.
	 *
	 * @since 1.5.2
	 * @param int $story_id Story ID.
	 * @return array[] Rows with facet_type and facet_value.
	 */
	private static function build_filter_map_rows( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		$post = get_post( $story_id );
		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			return array();
		}

		$rows = array();

		$language_slug = sanitize_title( self::get_language_slug( $story_id ) );
		if ( '' !== $language_slug ) {
			self::add_facet_row( $rows, 'language', $language_slug );
		}

		$age_rating = sanitize_title( self::get_age_rating( $story_id ) );
		if ( '' !== $age_rating ) {
			self::add_facet_row( $rows, 'age', $age_rating );
		}

		$fandom_slugs = self::normalize_facet_values( explode( ',', (string) self::get_fandom_slugs( $story_id ) ) );
		foreach ( $fandom_slugs as $slug ) {
			self::add_facet_row( $rows, 'fandom', $slug );
		}

		$warning_slugs = self::normalize_facet_values( explode( ',', (string) self::get_warning_slugs( $story_id ) ) );
		foreach ( $warning_slugs as $slug ) {
			self::add_facet_row( $rows, 'warning', $slug );
		}

		$genre_terms = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( $genre_terms && ! is_wp_error( $genre_terms ) ) {
			foreach ( $genre_terms as $term ) {
				self::add_facet_row( $rows, 'genre', $term->slug ?? '' );
			}
		}

		$status_terms = get_the_terms( $story_id, 'fanfiction_status' );
		if ( $status_terms && ! is_wp_error( $status_terms ) ) {
			foreach ( $status_terms as $term ) {
				self::add_facet_row( $rows, 'status', $term->slug ?? '' );
			}
		}

		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( (array) $custom_taxonomies as $taxonomy ) {
				$taxonomy_slug = sanitize_title( (string) ( $taxonomy['slug'] ?? '' ) );
				$taxonomy_id   = absint( $taxonomy['id'] ?? 0 );
				if ( ! $taxonomy_id || '' === $taxonomy_slug ) {
					continue;
				}

				$terms = Fanfic_Custom_Taxonomies::get_story_terms( $story_id, $taxonomy_id );
				foreach ( (array) $terms as $term ) {
					self::add_facet_row( $rows, 'custom:' . $taxonomy_slug, $term['slug'] ?? '' );
				}
			}
		}

		return array_values( $rows );
	}

	/**
	 * Sync search filter map rows for one story.
	 *
	 * @since 1.5.2
	 * @param int $story_id Story ID.
	 * @return bool
	 */
	public static function sync_filter_map( $story_id ) {
		if ( ! self::filter_map_table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_filter_map';

		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

		$rows = self::build_filter_map_rows( $story_id );
		foreach ( $rows as $row ) {
			$wpdb->insert(
				$table,
				array(
					'story_id'    => $story_id,
					'facet_type'  => $row['facet_type'],
					'facet_value' => $row['facet_value'],
				),
				array( '%d', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Build search index text for a story
	 *
	 * Aggregates: title, introduction, author name, chapter titles, tags
	 *
	 * @since 1.2.0
	 * @param int $story_id Story post ID.
	 * @return string Indexed text
	 */
	public static function build_index_text( $story_id ) {
		$parts = array();

		// Get story
		$story = get_post( $story_id );
		if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
			return '';
		}

		// 1. Story title
		if ( ! empty( $story->post_title ) ) {
			$parts[] = $story->post_title;
		}

		// 2. Story introduction (excerpt)
		if ( ! empty( $story->post_excerpt ) ) {
			$parts[] = $story->post_excerpt;
		}

		// 3. Author display name
		$author = get_userdata( $story->post_author );
		if ( $author ) {
			$parts[] = $author->display_name;
		}

		// 3b. Co-author display names (when enabled).
		if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
			$coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id, Fanfic_Coauthors::STATUS_ACCEPTED );
			foreach ( (array) $coauthors as $coauthor ) {
				if ( ! empty( $coauthor->display_name ) ) {
					$parts[] = $coauthor->display_name;
				}
			}
		}

		// 4. Chapter titles
		$chapters = get_posts(
			array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $chapters ) ) {
			foreach ( $chapters as $chapter_id ) {
				$chapter = get_post( $chapter_id );
				if ( $chapter && ! empty( $chapter->post_title ) ) {
					$parts[] = $chapter->post_title;
				}
			}
		}

		// 5. Visible tags
		if ( function_exists( 'fanfic_get_visible_tags' ) ) {
			$visible_tags = fanfic_get_visible_tags( $story_id );
			if ( ! empty( $visible_tags ) ) {
				$parts[] = implode( ' ', $visible_tags );
			}
		}

		// 6. Invisible tags
		if ( function_exists( 'fanfic_get_invisible_tags' ) ) {
			$invisible_tags = fanfic_get_invisible_tags( $story_id );
			if ( ! empty( $invisible_tags ) ) {
				$parts[] = implode( ' ', $invisible_tags );
			}
		}

		// 7. Genres
		$genre_terms = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( $genre_terms && ! is_wp_error( $genre_terms ) ) {
			$genre_names = wp_list_pluck( $genre_terms, 'name' );
			if ( ! empty( $genre_names ) ) {
				$parts[] = implode( ' ', $genre_names );
			}
		}

		// 8. Status
		$status_terms = get_the_terms( $story_id, 'fanfiction_status' );
		if ( $status_terms && ! is_wp_error( $status_terms ) ) {
			$status_names = wp_list_pluck( $status_terms, 'name' );
			if ( ! empty( $status_names ) ) {
				$parts[] = implode( ' ', $status_names );
			}
		}

		// 9. Warnings
		if ( class_exists( 'Fanfic_Warnings' ) ) {
			$warnings = Fanfic_Warnings::get_story_warnings( $story_id );
			if ( ! empty( $warnings ) ) {
				$warning_names = wp_list_pluck( $warnings, 'name' );
				if ( ! empty( $warning_names ) ) {
					$parts[] = implode( ' ', $warning_names );
				}
			}
		}

		// 10. Fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			$fandoms = Fanfic_Fandoms::get_story_fandom_labels( $story_id );
			if ( ! empty( $fandoms ) ) {
				$fandom_names = wp_list_pluck( $fandoms, 'label' );
				if ( ! empty( $fandom_names ) ) {
					$parts[] = implode( ' ', $fandom_names );
				}
			}
		}

		// 11. Languages
		if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
			$language = Fanfic_Languages::get_story_language_label( $story_id, true );
			if ( '' !== $language ) {
				$parts[] = $language;
			}
		}

		// 12. Custom taxonomies
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $custom_taxonomies as $taxonomy ) {
				$terms = Fanfic_Custom_Taxonomies::get_story_terms( $story_id, $taxonomy['id'] );
				if ( empty( $terms ) ) {
					continue;
				}
				$term_names = wp_list_pluck( $terms, 'name' );
				if ( ! empty( $term_names ) ) {
					$parts[] = implode( ' ', $term_names );
				}
			}
		}

		// Combine all parts
		$indexed_text = implode( ' ', $parts );

		// Normalize whitespace
		$indexed_text = preg_replace( '/\s+/', ' ', $indexed_text );
		$indexed_text = trim( $indexed_text );

		return $indexed_text;
	}

	/**
	 * Get story title
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Story title
	 */
	private static function get_story_title( $story_id ) {
		$post = get_post( $story_id );
		return $post ? $post->post_title : '';
	}

	/**
	 * Get story slug
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Story slug
	 */
	private static function get_story_slug( $story_id ) {
		$post = get_post( $story_id );
		return $post ? $post->post_name : '';
	}

	/**
	 * Get story summary
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Story summary
	 */
	private static function get_story_summary( $story_id ) {
		$post = get_post( $story_id );
		return $post ? $post->post_excerpt : '';
	}

	/**
	 * Get story status
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Story post status
	 */
	private static function get_story_status( $story_id ) {
		$post = get_post( $story_id );
		return $post ? $post->post_status : 'publish';
	}

	/**
	 * Get author ID
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return int Author ID
	 */
	private static function get_author_id( $story_id ) {
		$post = get_post( $story_id );
		return $post ? absint( $post->post_author ) : 0;
	}

	/**
	 * Get published date
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string|null Published date
	 */
	private static function get_published_date( $story_id ) {
		$post = get_post( $story_id );
		return $post ? $post->post_date : null;
	}

	/**
	 * Get updated date
	 *
	 * Reflects chapter-driven content updates only.
	 * Story summary edits do not affect this value.
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string|null Content updated date
	 */
	private static function get_updated_date( $story_id ) {
		// Use custom content update date if available
		$content_updated = get_post_meta( $story_id, '_fanfic_content_updated_date', true );

		if ( $content_updated ) {
			return $content_updated;
		}

		// Fallback to publication date for legacy stories without content update meta.
		$post = get_post( $story_id );
		return $post ? $post->post_date : null;
	}

	/**
	 * Get chapter count
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return int Chapter count
	 */
	private static function get_chapter_count( $story_id ) {
		return absint( get_post_meta( $story_id, '_fanfic_chapter_count', true ) );
	}

	/**
	 * Get word count
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return int Word count
	 */
	private static function get_word_count( $story_id ) {
		return absint( get_post_meta( $story_id, '_fanfic_word_count', true ) );
	}

	/**
	 * Get view count
	 *
	 * @since 1.5.1
	 * @param int $story_id Story post ID.
	 * @return int View count
	 */
	private static function get_view_count( $story_id ) {
		return absint( get_post_meta( $story_id, '_fanfic_views', true ) );
	}

	/**
	 * Get fandom slugs as comma-separated string
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Comma-separated fandom slugs
	 */
	private static function get_fandom_slugs( $story_id ) {
		if ( class_exists( 'Fanfic_Fandoms' ) ) {
			$fandoms = Fanfic_Fandoms::get_story_fandom_labels( $story_id );
			if ( ! empty( $fandoms ) ) {
				return implode( ',', array_column( $fandoms, 'slug' ) );
			}
		}
		// Fallback to meta
		$fandom = get_post_meta( $story_id, '_fanfic_fandom', true );
		return is_string( $fandom ) ? $fandom : '';
	}

	/**
	 * Get language slug
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Language slug
	 */
	private static function get_language_slug( $story_id ) {
		if ( class_exists( 'Fanfic_Languages' ) ) {
			$language = Fanfic_Languages::get_story_language( $story_id );
			return $language && isset( $language['slug'] ) ? $language['slug'] : '';
		}
		return get_post_meta( $story_id, '_fanfic_language', true );
	}

	/**
	 * Get translation metadata for search index.
	 *
	 * @since 1.5.1
	 * @param int $story_id Story post ID.
	 * @return array{group_id:int,count:int}
	 */
	private static function get_translation_meta( $story_id ) {
		$meta = array(
			'group_id' => 0,
			'count'    => 0,
		);

		if ( ! class_exists( 'Fanfic_Translations' ) || ! Fanfic_Translations::is_enabled() ) {
			return $meta;
		}

		$group_id = absint( Fanfic_Translations::get_group_id( $story_id ) );
		if ( ! $group_id ) {
			return $meta;
		}

		$meta['group_id'] = $group_id;
		$meta['count']    = count( Fanfic_Translations::get_translation_siblings( $story_id ) );

		return $meta;
	}

	/**
	 * Get warning slugs as comma-separated string
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Comma-separated warning slugs
	 */
	private static function get_warning_slugs( $story_id ) {
		if ( class_exists( 'Fanfic_Warnings' ) ) {
			$warnings = Fanfic_Warnings::get_story_warnings( $story_id );
			if ( ! empty( $warnings ) ) {
				return implode( ',', array_column( $warnings, 'slug' ) );
			}
		}
		return '';
	}

	/**
	 * Get age rating
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Age rating
	 */
	private static function get_age_rating( $story_id ) {
		$age_rating = trim( (string) get_post_meta( $story_id, '_fanfic_age_rating', true ) );
		if ( '' !== $age_rating ) {
			return $age_rating;
		}

		if ( class_exists( 'Fanfic_Warnings' ) ) {
			$warning_ids = Fanfic_Warnings::get_story_warning_ids( $story_id );
			return Fanfic_Warnings::calculate_derived_age( $warning_ids );
		}

		return '';
	}

	/**
	 * Get visible tags as comma-separated string
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Comma-separated visible tags
	 */
	private static function get_visible_tags_string( $story_id ) {
		if ( function_exists( 'fanfic_get_visible_tags' ) ) {
			$tags = fanfic_get_visible_tags( $story_id );
			return is_array( $tags ) ? implode( ',', $tags ) : '';
		}
		return '';
	}

	/**
	 * Get invisible tags as comma-separated string
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Comma-separated invisible tags
	 */
	private static function get_invisible_tags_string( $story_id ) {
		if ( function_exists( 'fanfic_get_invisible_tags' ) ) {
			$tags = fanfic_get_invisible_tags( $story_id );
			return is_array( $tags ) ? implode( ',', $tags ) : '';
		}
		return '';
	}

	/**
	 * Get genre names as comma-separated string
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Comma-separated genre names
	 */
	private static function get_genre_names( $story_id ) {
		$terms = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$names = wp_list_pluck( $terms, 'name' );
			return implode( ',', $names );
		}
		return '';
	}

	/**
	 * Get status name
	 *
	 * @since 1.5.0
	 * @param int $story_id Story post ID.
	 * @return string Status name
	 */
	private static function get_status_name( $story_id ) {
		$terms = get_the_terms( $story_id, 'fanfiction_status' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$term = reset( $terms );
			return $term->name;
		}
		return '';
	}

	/**
	 * Update search index for a story
	 *
	 * @since 1.2.0
	 * @param int $story_id Story post ID.
	 * @return bool True on success, false on failure
	 */
	public static function update_index( $story_id ) {
		if ( ! self::table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		// Build all data
		$translation_meta = self::get_translation_meta( $story_id );
		$coauthor_ids_str = '';
		$coauthor_names_str = '';
		if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
			$coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id, Fanfic_Coauthors::STATUS_ACCEPTED );
			if ( ! empty( $coauthors ) ) {
				$coauthor_ids_str = implode( ',', array_map( 'absint', wp_list_pluck( $coauthors, 'ID' ) ) );
				$coauthor_names_str = implode( ', ', array_map( 'sanitize_text_field', wp_list_pluck( $coauthors, 'display_name' ) ) );
			}
		}

		$data = array(
			'story_id'        => $story_id,
			'indexed_text'    => self::build_index_text( $story_id ),
			'story_title'     => self::get_story_title( $story_id ),
			'story_slug'      => self::get_story_slug( $story_id ),
			'story_summary'   => self::get_story_summary( $story_id ),
			'story_status'    => self::get_story_status( $story_id ),
			'author_id'       => self::get_author_id( $story_id ),
			'coauthor_ids'    => $coauthor_ids_str,
			'coauthor_names'  => $coauthor_names_str,
			'published_date'  => self::get_published_date( $story_id ),
			'updated_date'    => self::get_updated_date( $story_id ),
			'chapter_count'   => self::get_chapter_count( $story_id ),
			'word_count'      => self::get_word_count( $story_id ),
			'view_count'      => self::get_view_count( $story_id ),
			'fandom_slugs'    => self::get_fandom_slugs( $story_id ),
			'language_slug'   => self::get_language_slug( $story_id ),
			'translation_group_id' => absint( $translation_meta['group_id'] ),
			'translation_count'    => absint( $translation_meta['count'] ),
			'warning_slugs'   => self::get_warning_slugs( $story_id ),
			'age_rating'      => self::get_age_rating( $story_id ),
			'visible_tags'    => self::get_visible_tags_string( $story_id ),
			'invisible_tags'  => self::get_invisible_tags_string( $story_id ),
			'genre_names'     => self::get_genre_names( $story_id ),
			'status_name'     => self::get_status_name( $story_id ),
		);

		// Use REPLACE to insert or update
		$wpdb->replace( $table, $data );

		// Keep runtime filter facets in sync for table-driven search.
		self::sync_filter_map( $story_id );

		if ( class_exists( 'Fanfic_Cache' ) ) {
			$cache_key = Fanfic_Cache::get_key( 'search', 'global_filter_counts' );
			Fanfic_Cache::delete( $cache_key );
		}

		return true;
	}

	/**
	 * Delete search index for a story
	 *
	 * @since 1.2.0
	 * @param int $story_id Story post ID.
	 * @return bool True on success
	 */
	public static function delete_index( $story_id ) {
		if ( ! self::table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );
		if ( self::filter_map_table_ready() ) {
			$filter_table = $wpdb->prefix . 'fanfic_story_filter_map';
			$wpdb->delete( $filter_table, array( 'story_id' => $story_id ), array( '%d' ) );
		}

		return true;
	}

	/**
	 * Hook: On story save
	 *
	 * @since 1.2.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public static function on_story_save( $post_id, $post, $update ) {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Story summary edits do not change content updated date.
		// New stories receive an initial baseline timestamp until chapter activity occurs.
		if ( ! $update ) {
			// New story - set initial content update date
			update_post_meta( $post_id, '_fanfic_content_updated_date', current_time( 'mysql' ) );
		}

		// Update index
		self::update_index( $post_id );
	}

	/**
	 * Hook: On story delete
	 *
	 * @since 1.2.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_story_delete( $post_id ) {
		if ( get_post_type( $post_id ) !== 'fanfiction_story' ) {
			return;
		}

		self::delete_index( $post_id );
	}

	/**
	 * Hook: On chapter save
	 *
	 * Triggers parent story index update.
	 *
	 * @since 1.2.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public static function on_chapter_save( $post_id, $post, $update ) {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get parent story
		$story_id = $post->post_parent;
		if ( ! $story_id ) {
			return;
		}

		// Chapter creation counts as a content update baseline.
		if ( ! $update ) {
			update_post_meta( $story_id, '_fanfic_content_updated_date', current_time( 'mysql' ) );
		} else {
			// On edits, only significant chapter content changes update the timestamp.
			self::check_chapter_content_change( $post_id, $post, $story_id );
		}

		// Update index
		self::update_index( $story_id );
	}

	/**
	 * Hook: On chapter delete
	 *
	 * Triggers parent story index update.
	 *
	 * @since 1.2.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_chapter_delete( $post_id ) {
		if ( get_post_type( $post_id ) !== 'fanfiction_chapter' ) {
			return;
		}

		$chapter = get_post( $post_id );
		if ( $chapter && $chapter->post_parent ) {
			self::update_index( $chapter->post_parent );
		}
	}

	/**
	 * Hook: On author profile update
	 *
	 * Updates indexes for all stories by this author.
	 *
	 * @since 1.2.0
	 * @param int   $user_id User ID.
	 * @param array $old_user_data Old user data.
	 * @return void
	 */
	public static function on_author_profile_update( $user_id, $old_user_data ) {
		// Check if display_name changed
		$new_user = get_userdata( $user_id );
		if ( ! $new_user || ! isset( $old_user_data['display_name'] ) ) {
			return;
		}

		if ( $new_user->display_name === $old_user_data['display_name'] ) {
			return; // No change
		}

		// Update all stories by this author
		$stories = get_posts(
			array(
				'post_type'      => 'fanfiction_story',
				'author'         => $user_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $stories ) ) {
			foreach ( $stories as $story_id ) {
				self::update_index( $story_id );
			}
		}
	}

	/**
	 * Hook: On tags updated
	 *
	 * This should be fired by tag save functions.
	 *
	 * @since 1.2.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function on_tags_updated( $story_id ) {
		self::update_index( $story_id );
	}

	/**
	 * Hook: On translation links updated
	 *
	 * Re-indexes the changed story and all stories in the affected group so
	 * translation metadata in search index remains current.
	 *
	 * @since 1.5.1
	 * @param int $story_id Story ID that triggered update.
	 * @param int $group_id Translation group ID.
	 * @return void
	 */
	public static function on_translations_updated( $story_id, $group_id ) {
		$story_id = absint( $story_id );
		$group_id = absint( $group_id );

		if ( $story_id ) {
			self::update_index( $story_id );
		}

		if ( $group_id && class_exists( 'Fanfic_Translations' ) ) {
			$group_story_ids = Fanfic_Translations::get_group_stories( $group_id );
			foreach ( (array) $group_story_ids as $group_story_id ) {
				$group_story_id = absint( $group_story_id );
				if ( $group_story_id && $group_story_id !== $story_id ) {
					self::update_index( $group_story_id );
				}
			}
		}
	}

	/**
	 * Search stories by keyword
	 *
	 * Uses FULLTEXT index for fast searching.
	 *
	 * @since 1.2.0
	 * @param string $keyword Search keyword.
	 * @param int    $limit   Maximum results (default: 100).
	 * @return array Array of story IDs
	 */
	public static function search( $keyword, $limit = 100 ) {
		if ( ! self::table_ready() ) {
			return array();
		}

		if ( empty( $keyword ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		// Sanitize keyword for FULLTEXT search
		$keyword = trim( sanitize_text_field( $keyword ) );
		if ( '' === $keyword ) {
			return array();
		}

		$limit = max( 1, absint( $limit ) );
		$cache_key = '';
		if ( class_exists( 'Fanfic_Cache' ) ) {
			$cache_key = Fanfic_Cache::get_key( 'search', 'index_' . md5( $keyword . '|' . $limit ) );
			$cached = Fanfic_Cache::get( $cache_key, null, Fanfic_Cache::SHORT );
			if ( false !== $cached ) {
				return array_map( 'intval', (array) $cached );
			}
		}

		// Use FULLTEXT search with MATCH...AGAINST
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT story_id
				FROM {$table}
				WHERE MATCH(indexed_text) AGAINST(%s IN NATURAL LANGUAGE MODE)
				ORDER BY MATCH(indexed_text) AGAINST(%s IN NATURAL LANGUAGE MODE) DESC
				LIMIT %d",
				$keyword,
				$keyword,
				$limit
			)
		);

		$results = is_array( $results ) ? array_map( 'intval', $results ) : array();

		if ( ! empty( $cache_key ) ) {
			Fanfic_Cache::set( $cache_key, $results, Fanfic_Cache::SHORT );
		}

		return $results;
	}

	/**
	 * Rebuild index for all stories
	 *
	 * Use sparingly - this is resource-intensive.
	 *
	 * @since 1.2.0
	 * @param int $batch_size Number of stories to process per batch (default: 50).
	 * @param int $offset     Offset for batching (default: 0).
	 * @return array Status with 'processed' count and 'total' count
	 */
	public static function rebuild_all( $batch_size = 50, $offset = 0 ) {
		if ( ! self::table_ready() ) {
			return array(
				'success'   => false,
				'message'   => 'Search index table not ready',
				'processed' => 0,
				'total'     => 0,
			);
		}

		// Get total count
		$total = wp_count_posts( 'fanfiction_story' );
		$total_count = $total->publish + $total->draft + $total->private + $total->pending;

		// Get batch of stories
		$stories = get_posts(
			array(
				'post_type'      => 'fanfiction_story',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$processed = 0;

		if ( ! empty( $stories ) ) {
			foreach ( $stories as $story_id ) {
				self::update_index( $story_id );
				$processed++;
			}
		}

		return array(
			'success'   => true,
			'processed' => $processed,
			'total'     => $total_count,
			'offset'    => $offset,
			'remaining' => max( 0, $total_count - ( $offset + $processed ) ),
		);
	}

	/**
	 * Get index statistics
	 *
	 * @since 1.2.0
	 * @return array Statistics
	 */
	public static function get_stats() {
		if ( ! self::table_ready() ) {
			return array(
				'indexed_stories' => 0,
				'total_stories'   => 0,
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		$indexed_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$total = wp_count_posts( 'fanfiction_story' );
		$total_count = $total->publish + $total->draft + $total->private + $total->pending;

		return array(
			'indexed_stories' => $indexed_count,
			'total_stories'   => $total_count,
			'coverage'        => $total_count > 0 ? round( ( $indexed_count / $total_count ) * 100, 2 ) : 0,
		);
	}

	/**
	 * Check if chapter content changed significantly (10%+ change)
	 *
	 * Updates parent story's _fanfic_content_updated_date meta if significant change detected.
	 *
	 * @since 1.5.0
	 * @param int     $chapter_id Chapter post ID.
	 * @param WP_Post $post       New post object.
	 * @param int     $story_id   Parent story ID.
	 * @return void
	 */
	private static function check_chapter_content_change( $chapter_id, $post, $story_id ) {
		// Get old chapter content from database
		global $wpdb;
		$old_content = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
				$chapter_id
			)
		);

		if ( ! $old_content ) {
			return;
		}

		$new_content = $post->post_content;

		// Check if content changed significantly
		if ( self::is_content_significantly_changed( $old_content, $new_content ) ) {
			update_post_meta( $story_id, '_fanfic_content_updated_date', current_time( 'mysql' ) );
		}
	}

	/**
	 * Check if content changed by 10% or more
	 *
	 * Uses character count difference for speed on long texts.
	 * Falls back to similar_text() for short texts (more accurate).
	 *
	 * @since 1.5.0
	 * @param string $old_content Old content.
	 * @param string $new_content New content.
	 * @return bool True if content changed significantly.
	 */
	private static function is_content_significantly_changed( $old_content, $new_content ) {
		// Normalize whitespace for comparison
		$old_content = trim( preg_replace( '/\s+/', ' ', $old_content ) );
		$new_content = trim( preg_replace( '/\s+/', ' ', $new_content ) );

		// If either is empty, consider it a significant change
		if ( empty( $old_content ) || empty( $new_content ) ) {
			return true;
		}

		// If content is identical, no change
		if ( $old_content === $new_content ) {
			return false;
		}

		$old_len = strlen( $old_content );
		$new_len = strlen( $new_content );

		// For short texts (< 5000 chars), use similar_text for accuracy
		if ( $old_len < 5000 && $new_len < 5000 ) {
			similar_text( $old_content, $new_content, $percent );
			// If similarity is less than 90%, it's a significant change
			return $percent < 90;
		}

		// For long texts, use character count difference (much faster)
		$max_len = max( $old_len, $new_len );
		$diff    = abs( $old_len - $new_len );

		// If difference is 10% or more, it's significant
		$change_percent = ( $diff / $max_len ) * 100;

		return $change_percent >= 10;
	}
}
