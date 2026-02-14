<?php
/**
 * Cache Hooks Class
 *
 * Handles automatic cache invalidation when content changes.
 * Ensures fresh data is always served by clearing relevant transients
 * when stories, chapters, taxonomies, or user interactions are modified.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Cache_Hooks
 *
 * Centralized cache invalidation system that hooks into WordPress
 * and plugin actions to automatically clear stale cached data.
 *
 * @since 1.0.0
 */
class Fanfic_Cache_Hooks {

	/**
	 * Initialize cache hooks
	 *
	 * Registers all action hooks for automatic cache invalidation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		// Story and chapter save/delete hooks
		add_action( 'save_post_fanfiction_story', array( __CLASS__, 'on_story_save' ), 10, 3 );
		add_action( 'save_post_fanfiction_chapter', array( __CLASS__, 'on_chapter_save' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_delete' ), 10, 2 );

		// Taxonomy change hooks
		add_action( 'edited_term', array( __CLASS__, 'on_taxonomy_change' ), 10, 3 );
		add_action( 'created_term', array( __CLASS__, 'on_taxonomy_change' ), 10, 3 );
		add_action( 'deleted_term', array( __CLASS__, 'on_taxonomy_change' ), 10, 3 );

		// Plugin update hook
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_plugin_update' ), 10, 2 );

		// Comment hooks
		add_action( 'wp_insert_comment', array( __CLASS__, 'on_comment_posted' ), 10, 2 );
		add_action( 'edit_comment', array( __CLASS__, 'on_comment_edited' ), 10, 2 );
		add_action( 'deleted_comment', array( __CLASS__, 'on_comment_deleted' ), 10, 2 );

		// Plugin-specific interaction hooks
		add_action( 'fanfic_follow_added', array( __CLASS__, 'on_follow_add' ), 10, 2 );
		add_action( 'fanfic_follow_removed', array( __CLASS__, 'on_follow_remove' ), 10, 2 );
		add_action( 'fanfic_translations_updated', array( __CLASS__, 'invalidate_translation_caches' ), 10, 2 );
	}

	/**
	 * Handle story save event
	 *
	 * Invalidates caches when a story is created or updated.
	 * Skips autosaves and revisions to prevent unnecessary cache clearing.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public static function on_story_save( $post_id, $post, $update ) {
		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Clear story-specific caches
		self::clear_story_caches( $post_id );

		// Clear author-related caches
		if ( $post->post_author ) {
			self::clear_author_caches( $post->post_author );
		}

		// Clear list caches (archives, search results)
		self::clear_list_caches();

		// If story was just published, clear more extensive caches
		if ( 'publish' === $post->post_status ) {
			self::clear_archive_caches();
		}
	}

	/**
	 * Handle chapter save event
	 *
	 * Invalidates caches when a chapter is created or updated.
	 * Skips autosaves and revisions to prevent unnecessary cache clearing.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public static function on_chapter_save( $post_id, $post, $update ) {
		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Clear chapter-specific caches
		self::clear_chapter_caches( $post_id );

		// Clear parent story caches
		if ( $post->post_parent ) {
			self::clear_story_caches( $post->post_parent );

			// Get story author and clear their caches
			$story = get_post( $post->post_parent );
			if ( $story && $story->post_author ) {
				self::clear_author_caches( $story->post_author );
			}
		}

		// If chapter was just published, clear archive caches
		if ( 'publish' === $post->post_status ) {
			self::clear_archive_caches();
		}
	}

	/**
	 * Handle post deletion event
	 *
	 * Invalidates caches when a story or chapter is deleted.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function on_post_delete( $post_id, $post ) {
		// Only handle fanfiction post types
		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		if ( 'fanfiction_story' === $post->post_type ) {
			// Clear story caches
			self::clear_story_caches( $post_id );

			// Clear author caches
			if ( $post->post_author ) {
				self::clear_author_caches( $post->post_author );
			}

			// Clear follow caches for this story
			delete_transient( 'fanfic_follow_count_' . $post_id );
		} elseif ( 'fanfiction_chapter' === $post->post_type ) {
			// Clear chapter caches
			self::clear_chapter_caches( $post_id );

			// Clear parent story caches
			if ( $post->post_parent ) {
				self::clear_story_caches( $post->post_parent );
			}
		}

		// Clear list and archive caches
		self::clear_list_caches();
		self::clear_archive_caches();
	}

	/**
	 * Invalidate caches after translation group updates.
	 *
	 * @since 1.5.0
	 * @param int $story_id Story ID that triggered the update.
	 * @param int $group_id Translation group ID.
	 * @return void
	 */
	public static function invalidate_translation_caches( $story_id, $group_id ) {
		$story_id = absint( $story_id );
		$group_id = absint( $group_id );

		if ( class_exists( 'Fanfic_Translations' ) && $group_id ) {
			$stories = Fanfic_Translations::get_group_stories( $group_id );
			foreach ( $stories as $sid ) {
				clean_post_cache( $sid );
			}
		}

		if ( $story_id ) {
			clean_post_cache( $story_id );
		}
	}

	/**
	 * Handle taxonomy term changes
	 *
	 * Invalidates caches when genre, status, or custom taxonomy terms
	 * are created, edited, or deleted.
	 *
	 * @since 1.0.0
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public static function on_taxonomy_change( $term_id, $tt_id, $taxonomy ) {
		// Only handle fanfiction taxonomies
		if ( ! self::is_fanfiction_taxonomy( $taxonomy ) ) {
			return;
		}

		// Clear taxonomy-specific caches
		delete_transient( 'fanfic_taxonomy_' . $taxonomy . '_count' );
		delete_transient( 'fanfic_taxonomy_' . $taxonomy . '_terms' );

		// Clear all list and archive caches as taxonomy filters affect them
		self::clear_list_caches();
		self::clear_archive_caches();

		// Clear story list caches that might be filtered by this taxonomy
		self::clear_filtered_list_caches( $taxonomy );
	}

	/**
	 * Handle plugin update
	 *
	 * Clears all plugin-related caches when the plugin is updated.
	 *
	 * @since 1.0.0
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Array of bulk item update data.
	 * @return void
	 */
	public static function on_plugin_update( $upgrader, $options ) {
		// Check if this is a plugin update
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		// Check if our plugin was updated
		if ( isset( $options['plugins'] ) ) {
			$our_plugin = plugin_basename( FANFIC_PLUGIN_FILE );
			if ( in_array( $our_plugin, $options['plugins'], true ) ) {
				self::clear_all_caches();
			}
		}
	}

	/**
	 * Handle comment posted event
	 *
	 * Invalidates caches when a new comment is posted on a story or chapter.
	 *
	 * @since 1.0.0
	 * @param int        $comment_id Comment ID.
	 * @param int|object $comment    Comment object or ID.
	 * @return void
	 */
	public static function on_comment_posted( $comment_id, $comment ) {
		$comment = is_object( $comment ) ? $comment : get_comment( $comment );

		if ( ! $comment ) {
			return;
		}

		// Get the post this comment is on
		$post = get_post( $comment->comment_post_ID );

		if ( ! $post ) {
			return;
		}

		// Only handle comments on fanfiction post types
		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Clear comment count caches
		self::clear_comment_caches( $post->ID );

		// If chapter comment, also clear parent story caches
		if ( 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
			self::clear_comment_caches( $post->post_parent );
			self::clear_story_caches( $post->post_parent );
		}
	}

	/**
	 * Handle comment edited event
	 *
	 * Invalidates caches when a comment is edited.
	 *
	 * @since 1.0.0
	 * @param int        $comment_id Comment ID.
	 * @param array|null $data       Comment data.
	 * @return void
	 */
	public static function on_comment_edited( $comment_id, $data = null ) {
		self::on_comment_posted( $comment_id, $comment_id );
	}

	/**
	 * Handle comment deleted event
	 *
	 * Invalidates caches when a comment is deleted.
	 *
	 * @since 1.0.0
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment    Comment object.
	 * @return void
	 */
	public static function on_comment_deleted( $comment_id, $comment ) {
		if ( ! $comment ) {
			return;
		}

		// Get the post this comment was on
		$post = get_post( $comment->comment_post_ID );

		if ( ! $post ) {
			return;
		}

		// Only handle comments on fanfiction post types
		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return;
		}

		// Clear comment count caches
		self::clear_comment_caches( $post->ID );

		// If chapter comment, also clear parent story caches
		if ( 'fanfiction_chapter' === $post->post_type && $post->post_parent ) {
			self::clear_comment_caches( $post->post_parent );
			self::clear_story_caches( $post->post_parent );
		}
	}

	/**
	 * Handle follow add event
	 *
	 * Invalidates caches when a user follows a story.
	 * Note: The actual follow cache clearing is handled by
	 * Fanfic_Follows::clear_follow_cache(), but we clear
	 * additional related caches here.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return void
	 */
	public static function on_follow_add( $story_id, $user_id ) {
		// Clear popular stories lists that might be affected
		delete_transient( 'fanfic_most_followed_stories_10_1' );
		delete_transient( 'fanfic_most_followed_stories_20_1' );
		delete_transient( 'fanfic_recently_followed_stories_10' );
		delete_transient( 'fanfic_recently_followed_stories_20' );
	}

	/**
	 * Handle follow remove event
	 *
	 * Invalidates caches when a user unfollows a story.
	 * Note: The actual follow cache clearing is handled by
	 * Fanfic_Follows::clear_follow_cache(), but we clear
	 * additional related caches here.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $user_id  User ID.
	 * @return void
	 */
	public static function on_follow_remove( $story_id, $user_id ) {
		// Clear popular stories lists that might be affected
		delete_transient( 'fanfic_most_followed_stories_10_1' );
		delete_transient( 'fanfic_most_followed_stories_20_1' );
	}

	/**
	 * Clear story-specific caches
	 *
	 * Clears all transients related to a specific story.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story post ID.
	 * @return void
	 */
	private static function clear_story_caches( $story_id ) {
		$story_id = absint( $story_id );

		// Clear story metadata caches
		delete_transient( 'fanfic_story_meta_' . $story_id );
		delete_transient( 'fanfic_story_chapters_' . $story_id );
		delete_transient( 'fanfic_story_chapter_count_' . $story_id );
		delete_transient( 'fanfic_story_word_count_' . $story_id );
		delete_transient( 'fanfic_story_validity_' . $story_id );

		// Clear view counts
		wp_cache_delete( 'fanfic_story_views_' . $story_id, 'fanfiction' );

		// Clear follow count
		delete_transient( 'fanfic_follow_count_' . $story_id );

		// Clear average rating
		delete_transient( 'fanfic_story_avg_rating_' . $story_id );

		// Clear comment count
		delete_transient( 'fanfic_story_comment_count_' . $story_id );
	}

	/**
	 * Clear chapter-specific caches
	 *
	 * Clears all transients related to a specific chapter.
	 *
	 * @since 1.0.0
	 * @param int $chapter_id Chapter post ID.
	 * @return void
	 */
	private static function clear_chapter_caches( $chapter_id ) {
		$chapter_id = absint( $chapter_id );

		// Clear chapter metadata caches
		delete_transient( 'fanfic_chapter_meta_' . $chapter_id );
		delete_transient( 'fanfic_chapter_word_count_' . $chapter_id );

		// Clear view counts
		wp_cache_delete( 'fanfic_chapter_views_' . $chapter_id, 'fanfiction' );

		// Clear rating caches
		delete_transient( 'fanfic_chapter_avg_rating_' . $chapter_id );
		delete_transient( 'fanfic_chapter_rating_count_' . $chapter_id );

		// Clear comment count
		delete_transient( 'fanfic_chapter_comment_count_' . $chapter_id );
	}

	/**
	 * Clear author-specific caches
	 *
	 * Clears all transients related to a specific author.
	 *
	 * @since 1.0.0
	 * @param int $author_id Author user ID.
	 * @return void
	 */
	private static function clear_author_caches( $author_id ) {
		$author_id = absint( $author_id );

		// Clear author story lists (multiple pages)
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_transient( 'fanfic_author_stories_' . $author_id . '_page_' . $i );
			delete_transient( 'fanfic_author_stories_' . $author_id . '_' . $i );
		}

		// Clear author stats
		delete_transient( 'fanfic_author_stats_' . $author_id );
		delete_transient( 'fanfic_author_story_count_' . $author_id );
		delete_transient( 'fanfic_author_total_words_' . $author_id );

	}

	/**
	 * Clear comment-related caches
	 *
	 * Clears comment count transients for a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function clear_comment_caches( $post_id ) {
		$post_id = absint( $post_id );

		delete_transient( 'fanfic_story_comment_count_' . $post_id );
		delete_transient( 'fanfic_chapter_comment_count_' . $post_id );
		delete_transient( 'fanfic_comments_' . $post_id );
	}

	/**
	 * Clear list and search result caches
	 *
	 * Clears transients used for story lists and search results.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_list_caches() {
		// Clear paginated list caches (up to 20 pages)
		for ( $i = 1; $i <= 20; $i++ ) {
			delete_transient( 'fanfic_story_list_page_' . $i );
			delete_transient( 'fanfic_latest_stories_' . $i );
			delete_transient( 'fanfic_popular_stories_' . $i );
		}

		// Clear search result caches (up to 10 pages)
		// Note: Search caches typically include query hash in key
		// We clear common patterns here
		delete_transient( 'fanfic_search_results' );
	}

	/**
	 * Clear archive and taxonomy filter caches
	 *
	 * Clears transients used for archive pages and filtered lists.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_archive_caches() {
		// Clear archive page caches
		for ( $i = 1; $i <= 20; $i++ ) {
			delete_transient( 'fanfic_archive_page_' . $i );
		}

		// Clear genre and status filtered lists
		delete_transient( 'fanfic_stories_by_genre' );
		delete_transient( 'fanfic_stories_by_status' );

		// Clear featured/promoted lists
		delete_transient( 'fanfic_featured_stories' );
		delete_transient( 'fanfic_trending_stories' );
	}

	/**
	 * Clear filtered list caches for a specific taxonomy
	 *
	 * Clears list caches that are filtered by the given taxonomy.
	 *
	 * @since 1.0.0
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	private static function clear_filtered_list_caches( $taxonomy ) {
		// Get all terms for this taxonomy
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// Clear caches for each term (up to 10 pages per term)
		foreach ( $terms as $term_id ) {
			for ( $i = 1; $i <= 10; $i++ ) {
				delete_transient( 'fanfic_' . $taxonomy . '_' . $term_id . '_page_' . $i );
			}
		}
	}

	/**
	 * Clear all plugin-related caches
	 *
	 * Nuclear option: Clears ALL transients with fanfic_ prefix.
	 * Use sparingly (e.g., plugin updates, manual cache clear).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function clear_all_caches() {
		global $wpdb;

		// Delete all transients with fanfic_ prefix
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_fanfic_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_fanfic_' ) . '%'
			)
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'fanfiction' );
		}

		// Trigger action for extensions
		do_action( 'fanfic_all_caches_cleared' );
	}

	/**
	 * Check if taxonomy is a fanfiction taxonomy
	 *
	 * Determines whether a taxonomy slug belongs to the fanfiction plugin.
	 *
	 * @since 1.0.0
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool True if fanfiction taxonomy, false otherwise.
	 */
	private static function is_fanfiction_taxonomy( $taxonomy ) {
		// Core fanfiction taxonomies
		$core_taxonomies = array(
			'fanfiction_genre',
			'fanfiction_status',
		);

		// Check if it's a core taxonomy
		if ( in_array( $taxonomy, $core_taxonomies, true ) ) {
			return true;
		}

		// Check if it's a custom fanfiction taxonomy (starts with fanfiction_custom_)
		if ( strpos( $taxonomy, 'fanfiction_custom_' ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Manual cache clear utility
	 *
	 * Provides a way to manually clear specific cache types.
	 * Used by admin settings page for manual cache management.
	 *
	 * @since 1.0.0
	 * @param string $type Cache type to clear: 'all', 'stories', 'lists', 'authors', 'stats'.
	 * @return bool True on success, false on failure.
	 */
	public static function clear_cache_by_type( $type ) {
		switch ( $type ) {
			case 'all':
				self::clear_all_caches();
				return true;

			case 'stories':
				// Clear all story-related caches
				self::clear_list_caches();
				self::clear_archive_caches();
				return true;

			case 'lists':
				self::clear_list_caches();
				return true;

			case 'stats':
				delete_transient( 'fanfic_follow_stats' );
				delete_transient( 'fanfic_rating_stats' );
				return true;

			default:
				return false;
		}
	}
}
