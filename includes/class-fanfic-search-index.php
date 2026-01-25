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

		// Combine all parts
		$indexed_text = implode( ' ', $parts );

		// Normalize whitespace
		$indexed_text = preg_replace( '/\s+/', ' ', $indexed_text );
		$indexed_text = trim( $indexed_text );

		return $indexed_text;
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

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		$indexed_text = self::build_index_text( $story_id );

		// Check if entry exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT story_id FROM {$table} WHERE story_id = %d",
				$story_id
			)
		);

		if ( $exists ) {
			// Update existing
			$result = $wpdb->update(
				$table,
				array( 'indexed_text' => $indexed_text ),
				array( 'story_id' => $story_id ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			// Insert new
			$result = $wpdb->insert(
				$table,
				array(
					'story_id'     => $story_id,
					'indexed_text' => $indexed_text,
				),
				array( '%d', '%s' )
			);
		}

		return $result !== false;
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

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		$wpdb->delete( $table, array( 'story_id' => $story_id ), array( '%d' ) );

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
		if ( $story_id ) {
			self::update_index( $story_id );
		}
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
		$keyword = sanitize_text_field( $keyword );

		// Use FULLTEXT search with MATCH...AGAINST
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT story_id
				FROM {$table}
				WHERE MATCH(indexed_text) AGAINST(%s IN NATURAL LANGUAGE MODE)
				LIMIT %d",
				$keyword,
				$limit
			)
		);

		return is_array( $results ) ? array_map( 'intval', $results ) : array();
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
}
