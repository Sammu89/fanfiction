<?php
/**
 * Unified Interactions Class
 *
 * @package FanfictionManager
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Interactions
 *
 * Unified interaction service for likes, dislikes, ratings, views and read state.
 *
 * @since 1.6.0
 */
class Fanfic_Interactions {

	/**
	 * Singleton instance.
	 *
	 * @var Fanfic_Interactions|null
	 */
	private static $instance = null;

	/**
	 * Table existence cache.
	 *
	 * @var array<string,bool>
	 */
	private static $table_exists_cache = array();

	/**
	 * Get singleton instance.
	 *
	 * @since 1.6.0
	 * @return Fanfic_Interactions
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 */
	private function __construct() {
	}

	/**
	 * Init hooks.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public static function init() {
		self::get_instance();
		add_action( 'wp_login', array( __CLASS__, 'flag_sync_needed' ), 10, 2 );
	}

	/**
	 * Flag sync needed on login.
	 *
	 * @since 1.6.0
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 * @return void
	 */
	public static function flag_sync_needed( $user_login, $user ) {
		$user_id = isset( $user->ID ) ? absint( $user->ID ) : 0;
		if ( $user_id > 0 ) {
			set_transient( 'fanfic_needs_sync_' . $user_id, true, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Record like.
	 *
	 * @since 1.6.0
	 * @param int         $chapter_id      Chapter ID.
	 * @param int         $user_id         User ID.
	 * @param string|null $anonymous_uuid  Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function record_like( $chapter_id, $user_id = 0, $anonymous_uuid = '' ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		$actor      = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $chapter_id || ! $story_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_like_payload', __( 'Invalid chapter or identity for like interaction.', 'fanfiction-manager' ) );
		}

		$current     = self::get_user_chapter_interactions_raw( $actor['user_id'], $chapter_id, $actor['anonymous_hash'] );
		$has_like = isset( $current['like'] );
		$has_dislike = isset( $current['dislike'] );

		if ( ! $has_like ) {
			$ok = self::upsert_interaction( $actor['user_id'], $chapter_id, 'like', null, $actor['anonymous_hash'] );
			if ( ! $ok ) {
				return new WP_Error( 'like_write_failed', __( 'Could not save like interaction.', 'fanfiction-manager' ) );
			}
			self::apply_like_increment( $chapter_id, $story_id, 1 );
		}

		if ( $has_dislike ) {
			self::delete_interaction( $actor['user_id'], $chapter_id, 'dislike', $actor['anonymous_hash'] );
			self::apply_dislike_increment( $chapter_id, $story_id, -1 );
		}

		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'changed' => ( ! $has_like || $has_dislike ),
			'stats'   => self::get_chapter_stats( $chapter_id ),
		);
	}

	/**
	 * Remove like.
	 *
	 * @since 1.6.0
	 * @param int         $chapter_id      Chapter ID.
	 * @param int         $user_id         User ID.
	 * @param string|null $anonymous_uuid  Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function remove_like( $chapter_id, $user_id = 0, $anonymous_uuid = '' ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		$actor      = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $chapter_id || ! $story_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_like_remove_payload', __( 'Invalid chapter or identity for like removal.', 'fanfiction-manager' ) );
		}

		if ( ! self::has_interaction( $actor['user_id'], $chapter_id, 'like', $actor['anonymous_hash'] ) ) {
			return array(
				'success' => true,
				'changed' => false,
				'stats'   => self::get_chapter_stats( $chapter_id ),
			);
		}

		self::delete_interaction( $actor['user_id'], $chapter_id, 'like', $actor['anonymous_hash'] );
		self::apply_like_increment( $chapter_id, $story_id, -1 );
		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'changed' => true,
			'stats'   => self::get_chapter_stats( $chapter_id ),
		);
	}

	/**
	 * Record dislike.
	 *
	 * @since 1.6.0
	 * @param int         $chapter_id      Chapter ID.
	 * @param int         $user_id         User ID.
	 * @param string|null $anonymous_uuid  Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function record_dislike( $chapter_id, $user_id = 0, $anonymous_uuid = '' ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		$actor      = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $chapter_id || ! $story_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_dislike_payload', __( 'Invalid chapter or identity for dislike interaction.', 'fanfiction-manager' ) );
		}

		$current     = self::get_user_chapter_interactions_raw( $actor['user_id'], $chapter_id, $actor['anonymous_hash'] );
		$has_like = isset( $current['like'] );
		$has_dislike = isset( $current['dislike'] );

		if ( ! $has_dislike ) {
			$ok = self::upsert_interaction( $actor['user_id'], $chapter_id, 'dislike', null, $actor['anonymous_hash'] );
			if ( ! $ok ) {
				return new WP_Error( 'dislike_write_failed', __( 'Could not save dislike interaction.', 'fanfiction-manager' ) );
			}
			self::apply_dislike_increment( $chapter_id, $story_id, 1 );
		}

		if ( $has_like ) {
			self::delete_interaction( $actor['user_id'], $chapter_id, 'like', $actor['anonymous_hash'] );
			self::apply_like_increment( $chapter_id, $story_id, -1 );
		}

		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'changed' => ( ! $has_dislike || $has_like ),
			'stats'   => self::get_chapter_stats( $chapter_id ),
		);
	}

	/**
	 * Remove dislike.
	 *
	 * @since 1.6.0
	 * @param int         $chapter_id      Chapter ID.
	 * @param int         $user_id         User ID.
	 * @param string|null $anonymous_uuid  Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function remove_dislike( $chapter_id, $user_id = 0, $anonymous_uuid = '' ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		$actor      = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $chapter_id || ! $story_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_dislike_remove_payload', __( 'Invalid chapter or identity for dislike removal.', 'fanfiction-manager' ) );
		}

		if ( ! self::has_interaction( $actor['user_id'], $chapter_id, 'dislike', $actor['anonymous_hash'] ) ) {
			return array(
				'success' => true,
				'changed' => false,
				'stats'   => self::get_chapter_stats( $chapter_id ),
			);
		}

		self::delete_interaction( $actor['user_id'], $chapter_id, 'dislike', $actor['anonymous_hash'] );
		self::apply_dislike_increment( $chapter_id, $story_id, -1 );
		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'changed' => true,
			'stats'   => self::get_chapter_stats( $chapter_id ),
		);
	}

	/**
	 * Record rating.
	 *
	 * @since 1.6.0
	 * @param int         $chapter_id      Chapter ID.
	 * @param float       $rating          Rating value.
	 * @param int         $user_id         User ID.
	 * @param string|null $anonymous_uuid  Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function record_rating( $chapter_id, $rating, $user_id = 0, $anonymous_uuid = '' ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		$rating     = self::normalize_rating( $rating );
		$actor      = self::resolve_interaction_actor( $user_id, $anonymous_uuid );

		if ( ! $chapter_id || ! $story_id || false === $rating || empty( $actor ) ) {
			return new WP_Error( 'invalid_rating_payload', __( 'Invalid chapter, rating, or identity for rating interaction.', 'fanfiction-manager' ) );
		}

		$current    = self::get_user_chapter_interactions_raw( $actor['user_id'], $chapter_id, $actor['anonymous_hash'] );
		$old_rating = isset( $current['rating']['value'] ) ? floatval( $current['rating']['value'] ) : null;
		if ( null !== $old_rating && abs( $old_rating - $rating ) < 0.001 ) {
			return array(
				'success' => true,
				'changed' => false,
				'stats'   => self::get_chapter_stats( $chapter_id ),
			);
		}

		$ok = self::upsert_interaction( $actor['user_id'], $chapter_id, 'rating', $rating, $actor['anonymous_hash'] );
		if ( ! $ok ) {
			return new WP_Error( 'rating_write_failed', __( 'Could not save rating interaction.', 'fanfiction-manager' ) );
		}

		self::apply_rating_update(
			$chapter_id,
			$story_id,
			$rating,
			null === $old_rating ? 0.0 : $old_rating,
			null === $old_rating,
			false
		);

		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'changed' => true,
			'stats'   => self::get_chapter_stats( $chapter_id ),
		);
	}

	/**
	 * Remove rating.
	 *
	 * @since 1.6.0
	 * @param int         $chapter_id      Chapter ID.
	 * @param int         $user_id         User ID.
	 * @param string|null $anonymous_uuid  Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function remove_rating( $chapter_id, $user_id = 0, $anonymous_uuid = '' ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		$actor      = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $chapter_id || ! $story_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_rating_remove_payload', __( 'Invalid chapter or identity for rating removal.', 'fanfiction-manager' ) );
		}

		$current = self::get_user_chapter_interactions_raw( $actor['user_id'], $chapter_id, $actor['anonymous_hash'] );
		if ( ! isset( $current['rating']['value'] ) ) {
			return array(
				'success' => true,
				'changed' => false,
				'stats'   => self::get_chapter_stats( $chapter_id ),
			);
		}

		$old_rating = floatval( $current['rating']['value'] );
		self::delete_interaction( $actor['user_id'], $chapter_id, 'rating', $actor['anonymous_hash'] );
		self::apply_rating_update( $chapter_id, $story_id, 0.0, $old_rating, false, true );
		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'changed' => true,
			'stats'   => self::get_chapter_stats( $chapter_id ),
		);
	}

	/**
	 * Record view.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return array|WP_Error
	 */
	public static function record_view( $chapter_id, $story_id = 0 ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = absint( $story_id );

		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type || 'publish' !== $chapter->post_status ) {
			return new WP_Error( 'invalid_view_chapter', __( 'Invalid chapter for view interaction.', 'fanfiction-manager' ) );
		}

		if ( ! $story_id ) {
			$story_id = absint( $chapter->post_parent );
		}

		if ( ! $story_id ) {
			return new WP_Error( 'invalid_view_story', __( 'Invalid story for view interaction.', 'fanfiction-manager' ) );
		}

		if ( self::is_author_related_view( $story_id ) ) {
			return array(
				'success' => true,
				'skipped' => true,
			);
		}

		self::apply_view_increment( $chapter_id, $story_id );
		self::delete_stats_cache( $chapter_id, $story_id );

		return array(
			'success' => true,
			'skipped' => false,
		);
	}

	/**
	 * Record read.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $user_id    User ID.
	 * @return array|WP_Error
	 */
	public static function record_read( $chapter_id, $user_id ) {
		$chapter_id = absint( $chapter_id );
		$user_id    = absint( $user_id );
		$story_id   = self::get_story_id_from_chapter( $chapter_id );
		if ( ! $chapter_id || ! $user_id || ! $story_id ) {
			return new WP_Error( 'invalid_read_payload', __( 'Invalid chapter or user for read interaction.', 'fanfiction-manager' ) );
		}

		$ok = self::upsert_interaction( $user_id, $chapter_id, 'read', null );
		if ( ! $ok ) {
			return new WP_Error( 'read_write_failed', __( 'Could not save read interaction.', 'fanfiction-manager' ) );
		}

		return array(
			'success' => true,
			'changed' => true,
		);
	}

	/**
	 * Record follow.
	 *
	 * @since 1.8.0
	 * @param int         $post_id        Post ID (story or chapter).
	 * @param int         $user_id        User ID.
	 * @param string|null $anonymous_uuid Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function record_follow( $post_id, $user_id = 0, $anonymous_uuid = '' ) {
		$post_id = absint( $post_id );
		$actor   = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $post_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_follow_payload', __( 'Invalid post or identity for follow.', 'fanfiction-manager' ) );
		}

		if ( self::has_interaction( $actor['user_id'], $post_id, 'follow', $actor['anonymous_hash'] ) ) {
			return array( 'success' => true, 'changed' => false );
		}

		$ok = self::upsert_interaction( $actor['user_id'], $post_id, 'follow', null, $actor['anonymous_hash'] );
		if ( ! $ok ) {
			return new WP_Error( 'follow_write_failed', __( 'Could not save follow.', 'fanfiction-manager' ) );
		}

		self::apply_follow_increment( $post_id, 1 );
		do_action( 'fanfic_follow_added', $post_id, $actor['user_id'] );

		return array( 'success' => true, 'changed' => true );
	}

	/**
	 * Remove follow.
	 *
	 * @since 1.8.0
	 * @param int         $post_id        Post ID (story or chapter).
	 * @param int         $user_id        User ID.
	 * @param string|null $anonymous_uuid Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function remove_follow( $post_id, $user_id = 0, $anonymous_uuid = '' ) {
		$post_id = absint( $post_id );
		$actor   = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $post_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_follow_remove_payload', __( 'Invalid post or identity for follow removal.', 'fanfiction-manager' ) );
		}

		if ( ! self::has_interaction( $actor['user_id'], $post_id, 'follow', $actor['anonymous_hash'] ) ) {
			return array( 'success' => true, 'changed' => false );
		}

		self::delete_interaction( $actor['user_id'], $post_id, 'follow', $actor['anonymous_hash'] );

		self::apply_follow_increment( $post_id, -1 );
		do_action( 'fanfic_follow_removed', $post_id, $actor['user_id'] );

		return array( 'success' => true, 'changed' => true );
	}

	/**
	 * Toggle follow with minimal queries.
	 *
	 * Uses a delete-first strategy:
	 * - If a follow row existed, delete succeeds and follow is removed.
	 * - If no row existed, create follow via upsert.
	 *
	 * This avoids pre-check + re-check query duplication in toggle flows.
	 *
	 * @since 1.8.0
	 * @param int         $post_id        Post ID (story or chapter).
	 * @param int         $user_id        User ID.
	 * @param string|null $anonymous_uuid Anonymous UUID.
	 * @return array|WP_Error
	 */
	public static function toggle_follow( $post_id, $user_id = 0, $anonymous_uuid = '' ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$actor   = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $post_id || empty( $actor ) ) {
			return new WP_Error( 'invalid_follow_toggle_payload', __( 'Invalid post or identity for follow toggle.', 'fanfiction-manager' ) );
		}

		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return new WP_Error( 'follow_table_missing', __( 'Follow storage is unavailable.', 'fanfiction-manager' ) );
		}

		if ( $actor['user_id'] > 0 ) {
			$deleted = $wpdb->delete(
				$table,
				array(
					'user_id'          => $actor['user_id'],
					'chapter_id'       => $post_id,
					'interaction_type' => 'follow',
				),
				array( '%d', '%d', '%s' )
			);
		} else {
			$deleted = $wpdb->delete(
				$table,
				array(
					'anon_hash'        => $actor['anonymous_hash'],
					'chapter_id'       => $post_id,
					'interaction_type' => 'follow',
				),
				array( '%s', '%d', '%s' )
			);
		}

		if ( false === $deleted ) {
			return new WP_Error( 'follow_toggle_delete_failed', __( 'Could not update follow state.', 'fanfiction-manager' ) );
		}

		// Existing follow removed.
		if ( $deleted > 0 ) {
			self::apply_follow_increment( $post_id, -1 );
			do_action( 'fanfic_follow_removed', $post_id, $actor['user_id'] );
			return array(
				'success'       => true,
				'changed'       => true,
				'is_followed' => false,
			);
		}

		// No existing follow: create it.
		$ok = self::upsert_interaction( $actor['user_id'], $post_id, 'follow', null, $actor['anonymous_hash'] );
		if ( ! $ok ) {
			return new WP_Error( 'follow_toggle_insert_failed', __( 'Could not save follow.', 'fanfiction-manager' ) );
		}

		self::apply_follow_increment( $post_id, 1 );
		do_action( 'fanfic_follow_added', $post_id, $actor['user_id'] );

		return array(
			'success'       => true,
			'changed'       => true,
			'is_followed' => true,
		);
	}

	/**
	 * Check if follow exists.
	 *
	 * @since 1.8.0
	 * @param int         $post_id        Post ID (story or chapter).
	 * @param int         $user_id        User ID.
	 * @param string|null $anonymous_uuid Anonymous UUID.
	 * @return bool
	 */
	public static function has_follow( $post_id, $user_id = 0, $anonymous_uuid = '' ) {
		$post_id = absint( $post_id );
		$actor   = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $post_id || empty( $actor ) ) {
			return false;
		}

		return self::has_interaction( $actor['user_id'], $post_id, 'follow', $actor['anonymous_hash'] );
	}

	/**
	 * Idempotent follow insert (does not remove if already exists).
	 *
	 * Used by auto_follow_parent_story to ensure the parent story
	 * is followed without toggling off an existing follow.
	 *
	 * @since 1.8.0
	 * @param int    $post_id        Post ID (story or chapter).
	 * @param int    $user_id        User ID.
	 * @param string $anonymous_uuid Anonymous UUID.
	 * @return bool True if follow exists after call (new or already existed).
	 */
	public static function upsert_follow( $post_id, $user_id = 0, $anonymous_uuid = '' ) {
		$post_id = absint( $post_id );
		$actor   = self::resolve_interaction_actor( $user_id, $anonymous_uuid );
		if ( ! $post_id || empty( $actor ) ) {
			return false;
		}

		// Already followed — nothing to do.
		if ( self::has_interaction( $actor['user_id'], $post_id, 'follow', $actor['anonymous_hash'] ) ) {
			return true;
		}

		$ok = self::upsert_interaction( $actor['user_id'], $post_id, 'follow', null, $actor['anonymous_hash'] );
		if ( ! $ok ) {
			return false;
		}

		self::apply_follow_increment( $post_id, 1 );
		do_action( 'fanfic_follow_added', $post_id, $actor['user_id'] );

		return true;
	}

	/**
	 * Get chapter stats.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @return array{views:int,likes:int,dislikes:int,rating_avg:float,rating_count:int}
	 */
	public static function get_chapter_stats( $chapter_id ) {
		$chapter_id = absint( $chapter_id );
		$defaults = array(
			'views'        => 0,
			'likes'        => 0,
			'dislikes'     => 0,
			'rating_avg'   => 0.0,
			'rating_count' => 0,
		);

		if ( ! $chapter_id ) {
			return $defaults;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_chapter_search_index';
		if ( ! self::table_exists( $table ) ) {
			return $defaults;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT views_total, likes_total, dislikes_total, rating_avg_total, rating_count_total
				FROM {$table}
				WHERE chapter_id = %d",
				$chapter_id
			),
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return $defaults;
		}

		return array(
			'views'        => absint( $row['views_total'] ?? 0 ),
			'likes'        => absint( $row['likes_total'] ?? 0 ),
			'dislikes'     => absint( $row['dislikes_total'] ?? 0 ),
			'rating_avg'   => round( floatval( $row['rating_avg_total'] ?? 0 ), 2 ),
			'rating_count' => absint( $row['rating_count_total'] ?? 0 ),
		);
	}

	/**
	 * Get story stats.
	 *
	 * @since 1.6.0
	 * @param int $story_id Story ID.
	 * @return array{views:int,likes:int,dislikes:int,rating_avg:float,rating_count:int,follow_count:int}
	 */
	public static function get_story_stats( $story_id ) {
		$story_id = absint( $story_id );
		$defaults = array(
			'views'          => 0,
			'likes'          => 0,
			'dislikes'       => 0,
			'rating_avg'     => 0.0,
			'rating_count'   => 0,
			'follow_count' => 0,
		);

		if ( ! $story_id ) {
			return $defaults;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $table ) ) {
			return $defaults;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT view_count, likes_total, dislikes_total, rating_avg_total, rating_count_total, follow_count
				FROM {$table}
				WHERE story_id = %d",
				$story_id
			),
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return $defaults;
		}

		return array(
			'views'          => absint( $row['view_count'] ?? 0 ),
			'likes'          => absint( $row['likes_total'] ?? 0 ),
			'dislikes'       => absint( $row['dislikes_total'] ?? 0 ),
			'rating_avg'     => round( floatval( $row['rating_avg_total'] ?? 0 ), 2 ),
			'rating_count'   => absint( $row['rating_count_total'] ?? 0 ),
			'follow_count' => absint( $row['follow_count'] ?? 0 ),
		);
	}

	/**
	 * Batch get chapter stats.
	 *
	 * @since 1.6.0
	 * @param array<int> $chapter_ids Chapter IDs.
	 * @return array<int,array{views:int,likes:int,dislikes:int,rating_avg:float,rating_count:int}>
	 */
	public static function batch_get_chapter_stats( $chapter_ids ) {
		$chapter_ids = array_values( array_filter( array_map( 'absint', (array) $chapter_ids ) ) );
		if ( empty( $chapter_ids ) ) {
			return array();
		}

		$defaults = array(
			'views'        => 0,
			'likes'        => 0,
			'dislikes'     => 0,
			'rating_avg'   => 0.0,
			'rating_count' => 0,
		);

		$indexed = array();
		foreach ( $chapter_ids as $chapter_id ) {
			$indexed[ $chapter_id ] = $defaults;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_chapter_search_index';
		if ( ! self::table_exists( $table ) ) {
			return $indexed;
		}

		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT chapter_id, views_total, likes_total, dislikes_total, rating_avg_total, rating_count_total
				FROM {$table}
				WHERE chapter_id IN ({$placeholders})",
				...$chapter_ids
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$chapter_id = absint( $row['chapter_id'] ?? 0 );
			if ( ! $chapter_id ) {
				continue;
			}

			$indexed[ $chapter_id ] = array(
				'views'        => absint( $row['views_total'] ?? 0 ),
				'likes'        => absint( $row['likes_total'] ?? 0 ),
				'dislikes'     => absint( $row['dislikes_total'] ?? 0 ),
				'rating_avg'   => round( floatval( $row['rating_avg_total'] ?? 0 ), 2 ),
				'rating_count' => absint( $row['rating_count_total'] ?? 0 ),
			);
		}

		return $indexed;
	}

	/**
	 * Get all user interactions for sync payload.
	 *
	 * @since 1.6.0
	 * @param int $user_id User ID.
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_all_user_interactions( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return array();
		}

		$posts_table = $wpdb->posts;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.chapter_id, i.interaction_type, i.`value`, i.updated_at, p.post_parent AS story_id
				FROM {$table} i
				LEFT JOIN {$posts_table} p
					ON p.ID = i.chapter_id
					AND p.post_type = 'fanfiction_chapter'
				WHERE i.user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		$data = array();
		foreach ( (array) $rows as $row ) {
			$chapter_id = absint( $row['chapter_id'] ?? 0 );
			$story_id   = absint( $row['story_id'] ?? 0 );
			$type       = sanitize_key( $row['interaction_type'] ?? '' );
			$updated_at = isset( $row['updated_at'] ) ? strtotime( (string) $row['updated_at'] ) : 0;
			$timestamp  = $updated_at > 0 ? ( $updated_at * 1000 ) : (int) round( microtime( true ) * 1000 );

			if ( ! $chapter_id || ! $type ) {
				continue;
			}

			// Follows: chapter_id is actually a post ID (story or chapter).
			if ( 'follow' === $type ) {
				$post      = get_post( $chapter_id );
				$post_type = $post ? $post->post_type : '';
				if ( 'fanfiction_story' === $post_type ) {
					$key = self::build_local_key( $chapter_id, 0 );
				} elseif ( 'fanfiction_chapter' === $post_type ) {
					$parent_id = absint( $post->post_parent );
					$key = self::build_local_key( $parent_id, $chapter_id );
				} else {
					continue;
				}
				if ( ! isset( $data[ $key ] ) ) {
					$data[ $key ] = array( 'timestamp' => $timestamp );
				}
				if ( $timestamp > absint( $data[ $key ]['timestamp'] ?? 0 ) ) {
					$data[ $key ]['timestamp'] = $timestamp;
				}
				$data[ $key ]['follow'] = true;
				continue;
			}

			if ( ! $story_id ) {
				continue;
			}

			$key = self::build_local_key( $story_id, $chapter_id );
			if ( ! isset( $data[ $key ] ) ) {
				$data[ $key ] = array( 'timestamp' => $timestamp );
			}

			if ( $timestamp > absint( $data[ $key ]['timestamp'] ?? 0 ) ) {
				$data[ $key ]['timestamp'] = $timestamp;
			}

			if ( 'like' === $type ) {
				$data[ $key ]['like'] = true;
			} elseif ( 'dislike' === $type ) {
				$data[ $key ]['dislike'] = true;
			} elseif ( 'rating' === $type ) {
				$data[ $key ]['rating'] = round( floatval( $row['value'] ?? 0 ), 1 );
			} elseif ( 'read' === $type ) {
				$data[ $key ]['read'] = true;
			} elseif ( 'view' === $type ) {
				$data[ $key ]['view'] = true;
			}
		}

		return $data;
	}

	/**
	 * Sync localStorage payload on login.
	 *
	 * @since 1.6.0
	 * @param int         $user_id         User ID.
	 * @param array       $local_data      Local payload.
	 * @param string|null $anonymous_uuid  Anonymous UUID from client.
	 * @return array{merged:array<string,array<string,mixed>>}
	 */
	public static function sync_on_login( $user_id, $local_data, $anonymous_uuid = '' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array( 'merged' => array() );
		}

		$anonymous_hash = self::hash_anonymous_uuid( $anonymous_uuid );
		if ( ! empty( $anonymous_hash ) ) {
			self::reattribute_anonymous_rows_to_user( $user_id, $anonymous_hash );
		}

		$local_data = is_array( $local_data ) ? $local_data : array();
		$db_data = self::get_all_user_interactions( $user_id );
		$merged = array();

		$all_keys = array_unique( array_merge( array_keys( $db_data ), array_keys( $local_data ) ) );
		foreach ( $all_keys as $raw_key ) {
			$key = sanitize_text_field( (string) $raw_key );
			$parsed = self::parse_local_key( $key );
			if ( empty( $parsed ) ) {
				continue;
			}

			$story_id   = absint( $parsed['story_id'] );
			$chapter_id = absint( $parsed['chapter_id'] );
			$has_local  = isset( $local_data[ $key ] ) && is_array( $local_data[ $key ] );
			$has_db     = isset( $db_data[ $key ] ) && is_array( $db_data[ $key ] );

			$local_entry = $has_local ? self::sanitize_local_entry( $local_data[ $key ] ) : array();
			$db_entry    = $has_db ? self::sanitize_local_entry( $db_data[ $key ] ) : array();
			$local_ts    = absint( $local_entry['timestamp'] ?? 0 );
			$db_ts       = absint( $db_entry['timestamp'] ?? 0 );

			if ( $has_local && ! $has_db ) {
				self::apply_local_entry_to_db( $user_id, $chapter_id, $story_id, $local_entry );
				$merged[ $key ] = $local_entry;
				continue;
			}

			if ( ! $has_local && $has_db ) {
				$merged[ $key ] = $db_entry;
				continue;
			}

			if ( ! $has_local || ! $has_db ) {
				continue;
			}

			if ( $local_ts > $db_ts ) {
				self::apply_local_entry_to_db( $user_id, $chapter_id, $story_id, $local_entry );
				$merged[ $key ] = $local_entry;
			} else {
				$merged[ $key ] = $db_entry;
			}
		}

		delete_transient( 'fanfic_needs_sync_' . $user_id );

		return array( 'merged' => $merged );
	}

	/**
	 * Get story views.
	 *
	 * @since 1.6.0
	 * @param int $story_id Story ID.
	 * @return int
	 */
	public static function get_story_views( $story_id ) {
		$stats = self::get_story_stats( $story_id );
		return absint( $stats['views'] ?? 0 );
	}

	/**
	 * Get chapter views.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @return int
	 */
	public static function get_chapter_views( $chapter_id ) {
		$stats = self::get_chapter_stats( $chapter_id );
		return absint( $stats['views'] ?? 0 );
	}

	/**
	 * Get story likes.
	 *
	 * @since 1.6.0
	 * @param int $story_id Story ID.
	 * @return int
	 */
	public static function get_story_likes( $story_id ) {
		$stats = self::get_story_stats( $story_id );
		return absint( $stats['likes'] ?? 0 );
	}

	/**
	 * Get chapter likes.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @return int
	 */
	public static function get_chapter_likes( $chapter_id ) {
		$stats = self::get_chapter_stats( $chapter_id );
		return absint( $stats['likes'] ?? 0 );
	}

	/**
	 * Get story rating object.
	 *
	 * @since 1.6.0
	 * @param int $story_id Story ID.
	 * @return stdClass
	 */
	public static function get_story_rating( $story_id ) {
		$stats = self::get_story_stats( $story_id );
		$data = new stdClass();
		$data->average_rating = round( floatval( $stats['rating_avg'] ?? 0 ), 2 );
		$data->total_votes    = absint( $stats['rating_count'] ?? 0 );
		return $data;
	}

	/**
	 * Get chapter rating object.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @return stdClass
	 */
	public static function get_chapter_rating( $chapter_id ) {
		$stats = self::get_chapter_stats( $chapter_id );
		$data = new stdClass();
		$data->average_rating = round( floatval( $stats['rating_avg'] ?? 0 ), 2 );
		$data->total_votes    = absint( $stats['rating_count'] ?? 0 );
		return $data;
	}

	/**
	 * Get chapter rating stats.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @return stdClass
	 */
	public static function get_chapter_rating_stats( $chapter_id ) {
		return self::get_chapter_rating( $chapter_id );
	}

	/**
	 * Generate stars HTML with half-star support.
	 *
	 * @since 1.6.0
	 * @param float  $rating      Rating value.
	 * @param bool   $interactive Interactive mode.
	 * @param string $size        Size class.
	 * @return string
	 */
	public static function get_stars_html( $rating, $interactive = false, $size = 'medium' ) {
		$rating = max( 0, min( 5, floatval( $rating ) ) );
		$interactive_class = $interactive ? 'fanfic-rating-interactive' : 'fanfic-rating-readonly';
		$size_class = 'fanfic-rating-' . sanitize_html_class( $size );

		$html  = '<div class="fanfic-rating-stars fanfic-rating-stars-half ' . esc_attr( $interactive_class ) . ' ' . esc_attr( $size_class ) . '" data-rating="' . esc_attr( $rating ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$start    = $i - 1;
			$fill     = max( 0.0, min( 1.0, $rating - $start ) );
			$fill_pct = round( $fill * 100, 2 );
			$html .= '<span class="fanfic-star-wrap" data-star="' . esc_attr( $i ) . '">';
			$html .= '<span class="fanfic-star fanfic-star-empty" aria-hidden="true">&#9733;</span>';
			$html .= '<span class="fanfic-star fanfic-star-fill" aria-hidden="true" style="width:' . esc_attr( $fill_pct ) . '%">&#9733;</span>';
			if ( $interactive ) {
				$left_value  = $i - 0.5;
				$right_value = $i;
				$html .= '<button type="button" class="fanfic-star-hit fanfic-star-hit-left" data-value="' . esc_attr( $left_value ) . '" aria-label="' . esc_attr( sprintf( __( 'Rate %s stars', 'fanfiction-manager' ), $left_value ) ) . '"></button>';
				$html .= '<button type="button" class="fanfic-star-hit fanfic-star-hit-right" data-value="' . esc_attr( $right_value ) . '" aria-label="' . esc_attr( sprintf( __( 'Rate %s stars', 'fanfiction-manager' ), $right_value ) ) . '"></button>';
			}
			$html .= '</span>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Get top-rated stories.
	 *
	 * @since 1.6.0
	 * @param int    $limit       Limit.
	 * @param int    $min_ratings Minimum votes.
	 * @param string $period      total|week|month.
	 * @return array<int,array{story_id:int,rating:float,count:int}>
	 */
	public static function get_top_rated_stories( $limit = 10, $min_ratings = 5, $period = 'total' ) {
		$limit       = max( 1, absint( $limit ) );
		$min_ratings = max( 0, absint( $min_ratings ) );
		$period      = self::normalize_period( $period );

		$avg_col   = 'rating_avg_total';
		$count_col = 'rating_count_total';
		if ( 'week' === $period ) {
			$avg_col   = 'rating_avg_week';
			$count_col = 'rating_count_week';
		} elseif ( 'month' === $period ) {
			$avg_col   = 'rating_avg_month';
			$count_col = 'rating_count_month';
		}

		global $wpdb;
		$table_story = $wpdb->prefix . 'fanfic_story_search_index';
		$posts_table = $wpdb->posts;
		if ( ! self::table_exists( $table_story ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT idx.story_id, idx.{$avg_col} AS rating, idx.{$count_col} AS rating_count
				FROM {$table_story} idx
				INNER JOIN {$posts_table} p ON p.ID = idx.story_id
				WHERE p.post_type = 'fanfiction_story'
				  AND p.post_status = 'publish'
				  AND idx.{$count_col} >= %d
				ORDER BY idx.{$avg_col} DESC, idx.{$count_col} DESC
				LIMIT %d",
				$min_ratings,
				$limit
			),
			ARRAY_A
		);

		$data = array();
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'story_id' => absint( $row['story_id'] ?? 0 ),
				'rating'   => round( floatval( $row['rating'] ?? 0 ), 2 ),
				'count'    => absint( $row['rating_count'] ?? 0 ),
			);
		}

		return $data;
	}

	/**
	 * Get recently rated stories.
	 *
	 * @since 1.6.0
	 * @param int $limit Limit.
	 * @return int[]
	 */
	public static function get_recently_rated_stories( $limit = 10 ) {
		$limit = max( 1, absint( $limit ) );

		global $wpdb;
		$table_interactions = $wpdb->prefix . 'fanfic_interactions';
		$posts_table        = $wpdb->posts;

		if ( ! self::table_exists( $table_interactions ) ) {
			return array();
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.post_parent AS story_id
				FROM {$table_interactions} i
				INNER JOIN {$posts_table} p
					ON p.ID = i.chapter_id
				INNER JOIN {$posts_table} s
					ON s.ID = p.post_parent
				WHERE i.interaction_type = 'rating'
				  AND p.post_type = 'fanfiction_chapter'
				  AND p.post_status = 'publish'
				  AND s.post_type = 'fanfiction_story'
				  AND s.post_status = 'publish'
				GROUP BY p.post_parent
				ORDER BY MAX(i.updated_at) DESC
				LIMIT %d",
				$limit
			)
		);

		return array_map( 'absint', (array) $rows );
	}

	/**
	 * Get most viewed stories by period.
	 *
	 * @since 1.6.0
	 * @param int    $limit  Limit.
	 * @param string $period total|week|month.
	 * @return array<int,array{story_id:int,views:int}>
	 */
	public static function get_most_viewed_stories( $limit = 10, $period = 'total' ) {
		$limit = max( 1, absint( $limit ) );
		$period = self::normalize_period( $period );

		$views_col = 'view_count';
		if ( 'week' === $period ) {
			$views_col = 'views_week';
		} elseif ( 'month' === $period ) {
			$views_col = 'views_month';
		}

		global $wpdb;
		$table_story = $wpdb->prefix . 'fanfic_story_search_index';
		$posts_table = $wpdb->posts;
		if ( ! self::table_exists( $table_story ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT idx.story_id, idx.{$views_col} AS views
				FROM {$table_story} idx
				INNER JOIN {$posts_table} p ON p.ID = idx.story_id
				WHERE p.post_type = 'fanfiction_story'
				  AND p.post_status = 'publish'
				ORDER BY idx.{$views_col} DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$data = array();
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'story_id' => absint( $row['story_id'] ?? 0 ),
				'views'    => absint( $row['views'] ?? 0 ),
			);
		}

		return $data;
	}

	/**
	 * Get trending stories by period.
	 *
	 * @since 1.6.0
	 * @param int    $limit  Limit.
	 * @param string $period total|week|month.
	 * @return int[]
	 */
	public static function get_trending_stories( $limit = 10, $period = 'week' ) {
		$limit = max( 1, absint( $limit ) );
		$period = self::normalize_period( $period );

		$order_col = 'trending_week';
		if ( 'month' === $period ) {
			$order_col = 'trending_month';
		} elseif ( 'total' === $period ) {
			$order_col = 'view_count';
		}

		global $wpdb;
		$table_story = $wpdb->prefix . 'fanfic_story_search_index';
		$posts_table = $wpdb->posts;
		if ( ! self::table_exists( $table_story ) ) {
			return array();
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT idx.story_id
				FROM {$table_story} idx
				INNER JOIN {$posts_table} p ON p.ID = idx.story_id
				WHERE p.post_type = 'fanfiction_story'
				  AND p.post_status = 'publish'
				ORDER BY idx.{$order_col} DESC, idx.view_count DESC
				LIMIT %d",
				$limit
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * Check whether a user has liked a chapter.
	 *
	 * @since 1.6.0
	 * @param int|null $chapter_id Chapter ID.
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	public static function user_has_liked( $chapter_id, $user_id ) {
		$chapter_id = absint( $chapter_id );
		$user_id    = absint( $user_id );
		if ( ! $chapter_id || ! $user_id ) {
			return false;
		}

		return self::has_interaction( $user_id, $chapter_id, 'like' );
	}

	/**
	 * Get user's rating for a chapter.
	 *
	 * @since 1.6.0
	 * @param int|null $chapter_id Chapter ID.
	 * @param int|null $user_id User ID.
	 * @return float|false
	 */
	public static function user_has_rated( $chapter_id, $user_id ) {
		$chapter_id = absint( $chapter_id );
		$user_id    = absint( $user_id );
		if ( ! $chapter_id || ! $user_id ) {
			return false;
		}

		$rows = self::get_user_chapter_interactions_raw( $user_id, $chapter_id );
		if ( empty( $rows['rating'] ) ) {
			return false;
		}

		return floatval( $rows['rating']['value'] ?? 0 );
	}

	/**
	 * Apply like increment/decrement to search index.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @param int $delta      +1 or -1.
	 * @return void
	 */
	private static function apply_like_increment( $chapter_id, $story_id, $delta ) {
		global $wpdb;

		$chapter_id = absint( $chapter_id );
		$story_id   = absint( $story_id );
		$delta      = (int) $delta;
		if ( ! $chapter_id || ! $story_id || 0 === $delta ) {
			return;
		}

		$chapter_table = $wpdb->prefix . 'fanfic_chapter_search_index';
		$story_table   = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $chapter_table ) || ! self::table_exists( $story_table ) ) {
			return;
		}

		$stamps      = self::get_period_stamps();
		$week_stamp  = $stamps['week'];
		$month_stamp = $stamps['month'];

		if ( $delta > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$chapter_table}
						(chapter_id, story_id, likes_total, likes_week, likes_month, likes_week_stamp, likes_month_stamp)
					VALUES (%d, %d, 1, 1, 1, %d, %d)
					ON DUPLICATE KEY UPDATE
						story_id = VALUES(story_id),
						likes_total = likes_total + 1,
						likes_week = IF(likes_week_stamp = %d, likes_week + 1, 1),
						likes_month = IF(likes_month_stamp = %d, likes_month + 1, 1),
						likes_week_stamp = %d,
						likes_month_stamp = %d",
					$chapter_id,
					$story_id,
					$week_stamp,
					$month_stamp,
					$week_stamp,
					$month_stamp,
					$week_stamp,
					$month_stamp
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$story_table}
						(story_id, indexed_text, likes_total, likes_week, likes_month, likes_week_stamp, likes_month_stamp)
					VALUES (%d, '', 1, 1, 1, %d, %d)
					ON DUPLICATE KEY UPDATE
						likes_total = likes_total + 1,
						likes_week = IF(likes_week_stamp = %d, likes_week + 1, 1),
						likes_month = IF(likes_month_stamp = %d, likes_month + 1, 1),
						likes_week_stamp = %d,
						likes_month_stamp = %d",
					$story_id,
					$week_stamp,
					$month_stamp,
					$week_stamp,
					$month_stamp,
					$week_stamp,
					$month_stamp
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$chapter_table}
					SET likes_total = GREATEST(0, likes_total - 1),
						likes_week = IF(likes_week_stamp = %d, GREATEST(0, likes_week - 1), likes_week),
						likes_month = IF(likes_month_stamp = %d, GREATEST(0, likes_month - 1), likes_month)
					WHERE chapter_id = %d",
					$week_stamp,
					$month_stamp,
					$chapter_id
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$story_table}
					SET likes_total = GREATEST(0, likes_total - 1),
						likes_week = IF(likes_week_stamp = %d, GREATEST(0, likes_week - 1), likes_week),
						likes_month = IF(likes_month_stamp = %d, GREATEST(0, likes_month - 1), likes_month)
					WHERE story_id = %d",
					$week_stamp,
					$month_stamp,
					$story_id
				)
			);
		}
	}

	/**
	 * Apply dislike increment/decrement to search index.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @param int $delta      +1 or -1.
	 * @return void
	 */
	private static function apply_dislike_increment( $chapter_id, $story_id, $delta ) {
		global $wpdb;

		$chapter_id = absint( $chapter_id );
		$story_id   = absint( $story_id );
		$delta      = (int) $delta;
		if ( ! $chapter_id || ! $story_id || 0 === $delta ) {
			return;
		}

		$chapter_table = $wpdb->prefix . 'fanfic_chapter_search_index';
		$story_table   = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $chapter_table ) || ! self::table_exists( $story_table ) ) {
			return;
		}

		if ( $delta > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$chapter_table} (chapter_id, story_id, dislikes_total)
					VALUES (%d, %d, 1)
					ON DUPLICATE KEY UPDATE
						story_id = VALUES(story_id),
						dislikes_total = dislikes_total + 1",
					$chapter_id,
					$story_id
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$story_table} (story_id, indexed_text, dislikes_total)
					VALUES (%d, '', 1)
					ON DUPLICATE KEY UPDATE
						dislikes_total = dislikes_total + 1",
					$story_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$chapter_table}
					SET dislikes_total = GREATEST(0, dislikes_total - 1)
					WHERE chapter_id = %d",
					$chapter_id
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$story_table}
					SET dislikes_total = GREATEST(0, dislikes_total - 1)
					WHERE story_id = %d",
					$story_id
				)
			);
		}
	}

	/**
	 * Apply follow increment/decrement to story search index.
	 *
	 * Follows are tracked at the story level only (chapter follows still
	 * credit the parent story's follow_count for sort/filter purposes).
	 *
	 * @since 1.9.0
	 * @param int $post_id Post ID (story or chapter).
	 * @param int $delta   +1 to add, -1 to remove.
	 * @return void
	 */
	private static function apply_follow_increment( $post_id, $delta ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$delta   = (int) $delta;
		if ( ! $post_id || 0 === $delta ) {
			return;
		}

		// Determine the story_id: follows can be on a story or a chapter.
		$post_type = get_post_type( $post_id );
		if ( 'fanfiction_story' === $post_type ) {
			$story_id = $post_id;
		} elseif ( 'fanfiction_chapter' === $post_type ) {
			$story_id = absint( wp_get_post_parent_id( $post_id ) );
		} else {
			return;
		}

		if ( ! $story_id ) {
			return;
		}

		$story_table = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $story_table ) ) {
			return;
		}

		if ( $delta > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$story_table} (story_id, indexed_text, follow_count)
					VALUES (%d, '', 1)
					ON DUPLICATE KEY UPDATE
						follow_count = follow_count + 1",
					$story_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$story_table}
					SET follow_count = GREATEST(0, follow_count - 1)
					WHERE story_id = %d",
					$story_id
				)
			);
		}
	}

	/**
	 * Apply rating updates to chapter and story rollups.
	 *
	 * @since 1.6.0
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $story_id   Story ID.
	 * @param float $new        New rating.
	 * @param float $old        Old rating.
	 * @param bool  $is_new     New vote.
	 * @param bool  $is_remove  Remove vote.
	 * @return void
	 */
	private static function apply_rating_update( $chapter_id, $story_id, $new, $old, $is_new, $is_remove ) {
		global $wpdb;

		$chapter_id = absint( $chapter_id );
		$story_id   = absint( $story_id );
		if ( ! $chapter_id || ! $story_id ) {
			return;
		}

		$chapter_table = $wpdb->prefix . 'fanfic_chapter_search_index';
		$story_table   = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $chapter_table ) || ! self::table_exists( $story_table ) ) {
			return;
		}

		self::ensure_index_rows( $chapter_id, $story_id );
		$stamps      = self::get_period_stamps();
		$week_stamp  = $stamps['week'];
		$month_stamp = $stamps['month'];

		$chapter_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rating_sum_total, rating_count_total, rating_avg_total,
						rating_sum_week, rating_count_week, rating_avg_week, rating_week_stamp,
						rating_sum_month, rating_count_month, rating_avg_month, rating_month_stamp
				FROM {$chapter_table}
				WHERE chapter_id = %d",
				$chapter_id
			),
			ARRAY_A
		);

		$chapter_values = self::compute_rating_rollup_values(
			$chapter_row,
			floatval( $new ),
			floatval( $old ),
			(bool) $is_new,
			(bool) $is_remove,
			$week_stamp,
			$month_stamp
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$chapter_table}
				SET story_id = %d,
					rating_sum_total = %f,
					rating_count_total = %d,
					rating_avg_total = %f,
					rating_sum_week = %f,
					rating_count_week = %d,
					rating_avg_week = %f,
					rating_week_stamp = %d,
					rating_sum_month = %f,
					rating_count_month = %d,
					rating_avg_month = %f,
					rating_month_stamp = %d
				WHERE chapter_id = %d",
				$story_id,
				$chapter_values['rating_sum_total'],
				$chapter_values['rating_count_total'],
				$chapter_values['rating_avg_total'],
				$chapter_values['rating_sum_week'],
				$chapter_values['rating_count_week'],
				$chapter_values['rating_avg_week'],
				$chapter_values['rating_week_stamp'],
				$chapter_values['rating_sum_month'],
				$chapter_values['rating_count_month'],
				$chapter_values['rating_avg_month'],
				$chapter_values['rating_month_stamp'],
				$chapter_id
			)
		);

		$story_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rating_sum_total, rating_count_total, rating_avg_total,
						rating_sum_week, rating_count_week, rating_avg_week, rating_week_stamp,
						rating_sum_month, rating_count_month, rating_avg_month, rating_month_stamp
				FROM {$story_table}
				WHERE story_id = %d",
				$story_id
			),
			ARRAY_A
		);

		$story_values = self::compute_rating_rollup_values(
			$story_row,
			floatval( $new ),
			floatval( $old ),
			(bool) $is_new,
			(bool) $is_remove,
			$week_stamp,
			$month_stamp
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$story_table}
				SET rating_sum_total = %f,
					rating_count_total = %d,
					rating_avg_total = %f,
					rating_sum_week = %f,
					rating_count_week = %d,
					rating_avg_week = %f,
					rating_week_stamp = %d,
					rating_sum_month = %f,
					rating_count_month = %d,
					rating_avg_month = %f,
					rating_month_stamp = %d
				WHERE story_id = %d",
				$story_values['rating_sum_total'],
				$story_values['rating_count_total'],
				$story_values['rating_avg_total'],
				$story_values['rating_sum_week'],
				$story_values['rating_count_week'],
				$story_values['rating_avg_week'],
				$story_values['rating_week_stamp'],
				$story_values['rating_sum_month'],
				$story_values['rating_count_month'],
				$story_values['rating_avg_month'],
				$story_values['rating_month_stamp'],
				$story_id
			)
		);
	}

	/**
	 * Apply chapter view increment.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return void
	 */
	private static function apply_view_increment( $chapter_id, $story_id ) {
		global $wpdb;

		$chapter_table = $wpdb->prefix . 'fanfic_chapter_search_index';
		$story_table   = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $chapter_table ) || ! self::table_exists( $story_table ) ) {
			return;
		}

		$stamps      = self::get_period_stamps();
		$week_stamp  = $stamps['week'];
		$month_stamp = $stamps['month'];

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$chapter_table}
					(chapter_id, story_id, views_total, views_week, views_month, views_week_stamp, views_month_stamp, trending_week, trending_month)
				VALUES (%d, %d, 1, 1, 1, %d, %d, 1, 1)
				ON DUPLICATE KEY UPDATE
					story_id = VALUES(story_id),
					views_total = views_total + 1,
					trending_week = IF(views_week_stamp = %d, views_week + 1, 1),
					trending_month = IF(views_month_stamp = %d, views_month + 1, 1),
					views_week = IF(views_week_stamp = %d, views_week + 1, 1),
					views_month = IF(views_month_stamp = %d, views_month + 1, 1),
					views_week_stamp = %d,
					views_month_stamp = %d",
				$chapter_id,
				$story_id,
				$week_stamp,
				$month_stamp,
				$week_stamp,
				$month_stamp,
				$week_stamp,
				$month_stamp,
				$week_stamp,
				$month_stamp
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$story_table}
					(story_id, indexed_text, view_count, views_week, views_month, views_week_stamp, views_month_stamp, trending_week, trending_month)
				VALUES (%d, '', 1, 1, 1, %d, %d, 1, 1)
				ON DUPLICATE KEY UPDATE
					view_count = view_count + 1,
					trending_week = IF(views_week_stamp = %d, views_week + 1, 1),
					trending_month = IF(views_month_stamp = %d, views_month + 1, 1),
					views_week = IF(views_week_stamp = %d, views_week + 1, 1),
					views_month = IF(views_month_stamp = %d, views_month + 1, 1),
					views_week_stamp = %d,
					views_month_stamp = %d",
				$story_id,
				$week_stamp,
				$month_stamp,
				$week_stamp,
				$month_stamp,
				$week_stamp,
				$month_stamp,
				$week_stamp,
				$month_stamp
			)
		);
	}

	/**
	 * Compute rating rollup values.
	 *
	 * @since 1.6.0
	 * @param array|null $row         Current row values.
	 * @param float      $new         New rating.
	 * @param float      $old         Old rating.
	 * @param bool       $is_new      New vote.
	 * @param bool       $is_remove   Removed vote.
	 * @param int        $week_stamp  Current week stamp.
	 * @param int        $month_stamp Current month stamp.
	 * @return array<string,float|int>
	 */
	private static function compute_rating_rollup_values( $row, $new, $old, $is_new, $is_remove, $week_stamp, $month_stamp ) {
		$row = is_array( $row ) ? $row : array();

		$total_sum   = floatval( $row['rating_sum_total'] ?? 0 );
		$total_count = absint( $row['rating_count_total'] ?? 0 );
		$week_sum    = floatval( $row['rating_sum_week'] ?? 0 );
		$week_count  = absint( $row['rating_count_week'] ?? 0 );
		$week_old    = absint( $row['rating_week_stamp'] ?? 0 );
		$month_sum   = floatval( $row['rating_sum_month'] ?? 0 );
		$month_count = absint( $row['rating_count_month'] ?? 0 );
		$month_old   = absint( $row['rating_month_stamp'] ?? 0 );

		if ( $is_new ) {
			$total_sum   += $new;
			$total_count += 1;
		} elseif ( $is_remove ) {
			$total_sum   = max( 0, $total_sum - $old );
			$total_count = max( 0, $total_count - 1 );
		} else {
			$total_sum = max( 0, $total_sum + ( $new - $old ) );
		}

		if ( $week_old !== $week_stamp ) {
			if ( $is_remove ) {
				$week_sum = 0;
				$week_count = 0;
			} else {
				$week_sum = $new;
				$week_count = 1;
			}
		} else {
			if ( $is_new ) {
				$week_sum += $new;
				$week_count += 1;
			} elseif ( $is_remove ) {
				$week_sum = max( 0, $week_sum - $old );
				$week_count = max( 0, $week_count - 1 );
			} else {
				$week_sum = max( 0, $week_sum + ( $new - $old ) );
			}
		}

		if ( $month_old !== $month_stamp ) {
			if ( $is_remove ) {
				$month_sum = 0;
				$month_count = 0;
			} else {
				$month_sum = $new;
				$month_count = 1;
			}
		} else {
			if ( $is_new ) {
				$month_sum += $new;
				$month_count += 1;
			} elseif ( $is_remove ) {
				$month_sum = max( 0, $month_sum - $old );
				$month_count = max( 0, $month_count - 1 );
			} else {
				$month_sum = max( 0, $month_sum + ( $new - $old ) );
			}
		}

		$total_avg = $total_count > 0 ? ( $total_sum / $total_count ) : 0;
		$week_avg  = $week_count > 0 ? ( $week_sum / $week_count ) : 0;
		$month_avg = $month_count > 0 ? ( $month_sum / $month_count ) : 0;

		return array(
			'rating_sum_total'   => round( $total_sum, 4 ),
			'rating_count_total' => $total_count,
			'rating_avg_total'   => round( $total_avg, 4 ),
			'rating_sum_week'    => round( $week_sum, 4 ),
			'rating_count_week'  => $week_count,
			'rating_avg_week'    => round( $week_avg, 4 ),
			'rating_week_stamp'  => $week_stamp,
			'rating_sum_month'   => round( $month_sum, 4 ),
			'rating_count_month' => $month_count,
			'rating_avg_month'   => round( $month_avg, 4 ),
			'rating_month_stamp' => $month_stamp,
		);
	}

	/**
	 * Ensure chapter/story index rows exist.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return void
	 */
	private static function ensure_index_rows( $chapter_id, $story_id ) {
		global $wpdb;

		$chapter_table = $wpdb->prefix . 'fanfic_chapter_search_index';
		$story_table   = $wpdb->prefix . 'fanfic_story_search_index';
		if ( ! self::table_exists( $chapter_table ) || ! self::table_exists( $story_table ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$chapter_table} (chapter_id, story_id)
				VALUES (%d, %d)
				ON DUPLICATE KEY UPDATE story_id = VALUES(story_id)",
				$chapter_id,
				$story_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$story_table} (story_id, indexed_text)
				VALUES (%d, '')
				ON DUPLICATE KEY UPDATE story_id = VALUES(story_id)",
				$story_id
			)
		);
	}

	/**
	 * Check if current user is author/co-author of the story.
	 *
	 * @since 1.6.0
	 * @param int $story_id Story ID.
	 * @return bool
	 */
	private static function is_author_related_view( $story_id ) {
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return false;
		}

		$story_author_id = absint( get_post_field( 'post_author', $story_id ) );
		if ( $story_author_id && $story_author_id === (int) $current_user_id ) {
			return true;
		}

		if (
			class_exists( 'Fanfic_Coauthors' )
			&& method_exists( 'Fanfic_Coauthors', 'is_enabled' )
			&& Fanfic_Coauthors::is_enabled()
			&& method_exists( 'Fanfic_Coauthors', 'is_coauthor' )
			&& Fanfic_Coauthors::is_coauthor( $story_id, $current_user_id )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve interaction actor.
	 *
	 * @since 1.7.0
	 * @param int         $user_id        User ID.
	 * @param string|null $anonymous_uuid Anonymous UUID.
	 * @return array{user_id:int,anonymous_hash:string}|array{}
	 */
	private static function resolve_interaction_actor( $user_id, $anonymous_uuid = '' ) {
		$user_id = absint( $user_id );
		if ( $user_id > 0 ) {
			return array(
				'user_id'        => $user_id,
				'anonymous_hash' => '',
			);
		}

		$anonymous_hash = self::hash_anonymous_uuid( $anonymous_uuid );
		if ( empty( $anonymous_hash ) ) {
			return array();
		}

		return array(
			'user_id'        => 0,
			'anonymous_hash' => $anonymous_hash,
		);
	}

	/**
	 * Hash anonymous UUID for storage.
	 *
	 * @since 1.7.0
	 * @param string|null $anonymous_uuid Anonymous UUID.
	 * @return string Binary hash.
	 */
	private static function hash_anonymous_uuid( $anonymous_uuid ) {
		$anonymous_uuid = sanitize_text_field( (string) $anonymous_uuid );
		$anonymous_uuid = trim( $anonymous_uuid );
		if ( '' === $anonymous_uuid || strlen( $anonymous_uuid ) > 128 ) {
			return '';
		}

		return hash_hmac( 'sha256', $anonymous_uuid, wp_salt( 'auth' ), true );
	}

	/**
	 * Re-attribute anonymous rows to user on login.
	 *
	 * This method does not touch aggregate counters because anonymous writes already
	 * contributed to totals when originally recorded.
	 *
	 * @since 1.7.0
	 * @param int    $user_id        User ID.
	 * @param string $anonymous_hash Binary anonymous hash.
	 * @return void
	 */
	private static function reattribute_anonymous_rows_to_user( $user_id, $anonymous_hash ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || empty( $anonymous_hash ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return;
		}

		// If a user row already exists for the same chapter/type, keep user row and drop anon duplicate.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE anon
				FROM {$table} anon
				INNER JOIN {$table} usr
					ON usr.user_id = %d
					AND usr.chapter_id = anon.chapter_id
					AND usr.interaction_type = anon.interaction_type
				WHERE anon.anon_hash = %s
				  AND anon.user_id IS NULL",
				$user_id,
				$anonymous_hash
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET user_id = %d,
					anon_hash = NULL
				WHERE anon_hash = %s
				  AND user_id IS NULL",
				$user_id,
				$anonymous_hash
			)
		);
	}

	/**
	 * Apply one local entry to DB.
	 *
	 * @since 1.6.0
	 * @param int   $user_id    User ID.
	 * @param int   $chapter_id Chapter ID.
	 * @param int   $story_id   Story ID.
	 * @param array $entry      Entry data.
	 * @return void
	 */
	private static function apply_local_entry_to_db( $user_id, $chapter_id, $story_id, $entry ) {
		$user_id    = absint( $user_id );
		$chapter_id = absint( $chapter_id );
		$story_id   = absint( $story_id );
		$entry      = self::sanitize_local_entry( $entry );

		if ( ! $user_id || ! $chapter_id || ! $story_id ) {
			return;
		}

		$current = self::get_user_chapter_interactions_raw( $user_id, $chapter_id );
		$active_types = array();

		if ( ! empty( $entry['like'] ) ) {
			self::record_like( $chapter_id, $user_id );
			$active_types[] = 'like';
		} elseif ( isset( $current['like'] ) ) {
			self::remove_like( $chapter_id, $user_id );
		}

		if ( ! empty( $entry['dislike'] ) ) {
			self::record_dislike( $chapter_id, $user_id );
			$active_types[] = 'dislike';
		} elseif ( isset( $current['dislike'] ) ) {
			self::remove_dislike( $chapter_id, $user_id );
		}

		if ( isset( $entry['rating'] ) && false !== self::normalize_rating( $entry['rating'] ) ) {
			self::record_rating( $chapter_id, floatval( $entry['rating'] ), $user_id );
			$active_types[] = 'rating';
		} elseif ( isset( $current['rating'] ) ) {
			self::remove_rating( $chapter_id, $user_id );
		}

		if ( ! empty( $entry['read'] ) ) {
			self::record_read( $chapter_id, $user_id );
			$active_types[] = 'read';
		} elseif ( isset( $current['read'] ) ) {
			self::delete_interaction( $user_id, $chapter_id, 'read' );
		}

		// Follow sync: chapter_id=0 means story follow, else chapter follow.
		if ( ! empty( $entry['follow'] ) ) {
			$follow_post_id = ( $chapter_id > 0 ) ? $chapter_id : $story_id;
			self::record_follow( $follow_post_id, $user_id );
		} else {
			// Check if DB has a follow for this key and remove it.
			$follow_post_id = ( $chapter_id > 0 ) ? $chapter_id : $story_id;
			if ( self::has_interaction( $user_id, $follow_post_id, 'follow' ) ) {
				self::remove_follow( $follow_post_id, $user_id );
			}
		}

		// Entry existence means chapter was viewed.
		if ( $chapter_id > 0 ) {
			self::upsert_interaction( $user_id, $chapter_id, 'view', null );
			$active_types[] = 'view';
		}

		$timestamp = absint( $entry['timestamp'] ?? 0 );
		if ( $timestamp > 0 ) {
			self::apply_entry_timestamp( $user_id, $chapter_id, $active_types, $timestamp );
		}
	}

	/**
	 * Apply timestamp to active interaction rows.
	 *
	 * @since 1.6.0
	 * @param int   $user_id      User ID.
	 * @param int   $chapter_id   Chapter ID.
	 * @param array $active_types Types.
	 * @param int   $timestamp_ms Timestamp in ms.
	 * @return void
	 */
	private static function apply_entry_timestamp( $user_id, $chapter_id, $active_types, $timestamp_ms ) {
		$user_id      = absint( $user_id );
		$chapter_id   = absint( $chapter_id );
		$timestamp_ms = absint( $timestamp_ms );
		$active_types = array_values( array_filter( array_map( 'sanitize_key', (array) $active_types ) ) );

		if ( ! $user_id || ! $chapter_id || $timestamp_ms < 1 || empty( $active_types ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$timestamp_s = (int) floor( $timestamp_ms / 1000 );
		if ( $timestamp_s < 1 ) {
			return;
		}
		$timestamp_mysql = gmdate( 'Y-m-d H:i:s', $timestamp_s );

		$placeholders = implode( ',', array_fill( 0, count( $active_types ), '%s' ) );
		$args = array_merge( array( $timestamp_mysql, $user_id, $chapter_id ), $active_types );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET updated_at = %s
				WHERE user_id = %d
				  AND chapter_id = %d
				  AND interaction_type IN ({$placeholders})",
				...$args
			)
		);
	}

	/**
	 * Normalize period string.
	 *
	 * @since 1.6.0
	 * @param string $period Period.
	 * @return string
	 */
	private static function normalize_period( $period ) {
		$period = strtolower( trim( (string) $period ) );
		if ( ! in_array( $period, array( 'total', 'week', 'month' ), true ) ) {
			return 'total';
		}
		return $period;
	}

	/**
	 * Normalize rating to half-star.
	 *
	 * @since 1.6.0
	 * @param float|int|string $rating Rating input.
	 * @return float|false
	 */
	private static function normalize_rating( $rating ) {
		$rating = floatval( $rating );
		if ( $rating < 0.5 || $rating > 5 ) {
			return false;
		}
		$rating = round( $rating * 2 ) / 2;
		$rating = max( 0.5, min( 5.0, $rating ) );
		return floatval( $rating );
	}

	/**
	 * Check table existence.
	 *
	 * @since 1.6.0
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	private static function table_exists( $table_name ) {
		if ( isset( self::$table_exists_cache[ $table_name ] ) ) {
			return self::$table_exists_cache[ $table_name ];
		}

		global $wpdb;
		$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name );
		self::$table_exists_cache[ $table_name ] = $exists;
		return $exists;
	}

	/**
	 * Upsert one interaction.
	 *
	 * @since 1.6.0
	 * @param int         $user_id         User ID.
	 * @param int         $chapter_id      Chapter ID.
	 * @param string      $type            Interaction type.
	 * @param float|null  $value           Optional value.
	 * @param string|null $anonymous_hash  Binary anonymous hash.
	 * @return bool
	 */
	private static function upsert_interaction( $user_id, $chapter_id, $type, $value = null, $anonymous_hash = '' ) {
		global $wpdb;

		$user_id      = absint( $user_id );
		$chapter_id   = absint( $chapter_id );
		$anonymous_hash = is_string( $anonymous_hash ) ? $anonymous_hash : '';

		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		$type = sanitize_key( $type );
		if ( ! in_array( $type, array( 'like', 'dislike', 'rating', 'view', 'read', 'follow' ), true ) ) {
			return false;
		}

		if ( ! $chapter_id || ( ! $user_id && empty( $anonymous_hash ) ) ) {
			return false;
		}

		if ( $user_id > 0 ) {
			if ( null === $value ) {
				$ok = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table}
							(user_id, anon_hash, chapter_id, interaction_type, `value`, created_at, updated_at)
						VALUES (%d, NULL, %d, %s, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
						ON DUPLICATE KEY UPDATE
							anon_hash = NULL,
							`value` = NULL,
							updated_at = CURRENT_TIMESTAMP",
						$user_id,
						$chapter_id,
						$type
					)
				);
			} else {
				$ok = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table}
							(user_id, anon_hash, chapter_id, interaction_type, `value`, created_at, updated_at)
						VALUES (%d, NULL, %d, %s, %f, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
						ON DUPLICATE KEY UPDATE
							anon_hash = NULL,
							`value` = VALUES(`value`),
							updated_at = CURRENT_TIMESTAMP",
						$user_id,
						$chapter_id,
						$type,
						floatval( $value )
					)
				);
			}
		} else {
			if ( null === $value ) {
				$ok = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table}
							(user_id, anon_hash, chapter_id, interaction_type, `value`, created_at, updated_at)
						VALUES (NULL, %s, %d, %s, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
						ON DUPLICATE KEY UPDATE
							user_id = NULL,
							`value` = NULL,
							updated_at = CURRENT_TIMESTAMP",
						$anonymous_hash,
						$chapter_id,
						$type
					)
				);
			} else {
				$ok = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table}
							(user_id, anon_hash, chapter_id, interaction_type, `value`, created_at, updated_at)
						VALUES (NULL, %s, %d, %s, %f, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
						ON DUPLICATE KEY UPDATE
							user_id = NULL,
							`value` = VALUES(`value`),
							updated_at = CURRENT_TIMESTAMP",
						$anonymous_hash,
						$chapter_id,
						$type,
						floatval( $value )
					)
				);
			}
		}

		return false !== $ok;
	}

	/**
	 * Delete interaction row.
	 *
	 * @since 1.6.0
	 * @param int         $user_id         User ID.
	 * @param int         $chapter_id      Chapter ID.
	 * @param string      $type            Interaction type.
	 * @param string|null $anonymous_hash  Binary anonymous hash.
	 * @return bool
	 */
	private static function delete_interaction( $user_id, $chapter_id, $type, $anonymous_hash = '' ) {
		global $wpdb;

		$user_id      = absint( $user_id );
		$chapter_id   = absint( $chapter_id );
		$anonymous_hash = is_string( $anonymous_hash ) ? $anonymous_hash : '';

		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		if ( ! $chapter_id || ( ! $user_id && empty( $anonymous_hash ) ) ) {
			return false;
		}

		if ( $user_id > 0 ) {
			$deleted = $wpdb->delete(
				$table,
				array(
					'user_id'          => $user_id,
					'chapter_id'       => $chapter_id,
					'interaction_type' => sanitize_key( $type ),
				),
				array( '%d', '%d', '%s' )
			);
		} else {
			$deleted = $wpdb->delete(
				$table,
				array(
					'anon_hash'        => $anonymous_hash,
					'chapter_id'       => $chapter_id,
					'interaction_type' => sanitize_key( $type ),
				),
				array( '%s', '%d', '%s' )
			);
		}

		return false !== $deleted;
	}

	/**
	 * Check interaction row exists.
	 *
	 * @since 1.6.0
	 * @param int         $user_id         User ID.
	 * @param int         $chapter_id      Chapter ID.
	 * @param string      $type            Type.
	 * @param string|null $anonymous_hash  Binary anonymous hash.
	 * @return bool
	 */
	private static function has_interaction( $user_id, $chapter_id, $type, $anonymous_hash = '' ) {
		global $wpdb;

		$user_id      = absint( $user_id );
		$chapter_id   = absint( $chapter_id );
		$anonymous_hash = is_string( $anonymous_hash ) ? $anonymous_hash : '';

		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		if ( ! $chapter_id || ( ! $user_id && empty( $anonymous_hash ) ) ) {
			return false;
		}

		if ( $user_id > 0 ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$table}
					WHERE user_id = %d
					  AND chapter_id = %d
					  AND interaction_type = %s",
					$user_id,
					$chapter_id,
					sanitize_key( $type )
				)
			);
		} else {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$table}
					WHERE anon_hash = %s
					  AND chapter_id = %d
					  AND interaction_type = %s",
					$anonymous_hash,
					$chapter_id,
					sanitize_key( $type )
				)
			);
		}

		return ! empty( $exists );
	}

	/**
	 * Get raw interaction rows indexed by type.
	 *
	 * @since 1.6.0
	 * @param int         $user_id         User ID.
	 * @param int         $chapter_id      Chapter ID.
	 * @param string|null $anonymous_hash  Binary anonymous hash.
	 * @return array<string,array<string,mixed>>
	 */
	private static function get_user_chapter_interactions_raw( $user_id, $chapter_id, $anonymous_hash = '' ) {
		$user_id      = absint( $user_id );
		$chapter_id   = absint( $chapter_id );
		$anonymous_hash = is_string( $anonymous_hash ) ? $anonymous_hash : '';
		if ( ! $chapter_id || ( ! $user_id && empty( $anonymous_hash ) ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';
		if ( ! self::table_exists( $table ) ) {
			return array();
		}

		if ( $user_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT interaction_type, `value`, updated_at
					FROM {$table}
					WHERE user_id = %d
					  AND chapter_id = %d",
					$user_id,
					$chapter_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT interaction_type, `value`, updated_at
					FROM {$table}
					WHERE anon_hash = %s
					  AND chapter_id = %d",
					$anonymous_hash,
					$chapter_id
				),
				ARRAY_A
			);
		}

		$indexed = array();
		foreach ( (array) $rows as $row ) {
			$type = sanitize_key( $row['interaction_type'] ?? '' );
			if ( '' === $type ) {
				continue;
			}
			$indexed[ $type ] = $row;
		}

		return $indexed;
	}

	/**
	 * Get raw interaction rows indexed by type for anonymous UUID + chapter.
	 *
	 * @since 1.7.0
	 * @param string $anonymous_uuid Anonymous UUID.
	 * @param int    $chapter_id     Chapter ID.
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_user_chapter_interactions_raw_by_uuid( $anonymous_uuid, $chapter_id ) {
		$chapter_id = absint( $chapter_id );
		if ( ! $chapter_id ) {
			return array();
		}

		$anonymous_hash = self::hash_anonymous_uuid( $anonymous_uuid );
		if ( empty( $anonymous_hash ) ) {
			return array();
		}

		return self::get_user_chapter_interactions_raw( 0, $chapter_id, $anonymous_hash );
	}

	/**
	 * Resolve story ID from chapter ID.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @return int
	 */
	private static function get_story_id_from_chapter( $chapter_id ) {
		$chapter_id = absint( $chapter_id );
		if ( ! $chapter_id ) {
			return 0;
		}

		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return 0;
		}

		return absint( $chapter->post_parent );
	}

	/**
	 * Build local storage key.
	 *
	 * @since 1.6.0
	 * @param int $story_id   Story ID.
	 * @param int $chapter_id Chapter ID.
	 * @return string
	 */
	private static function build_local_key( $story_id, $chapter_id ) {
		return 'story_' . absint( $story_id ) . '_chapter_' . absint( $chapter_id );
	}

	/**
	 * Parse local storage key.
	 *
	 * @since 1.6.0
	 * @param string $key Local key.
	 * @return array<string,int>
	 */
	private static function parse_local_key( $key ) {
		$key = trim( (string) $key );
		if ( ! preg_match( '/^story_(\d+)_chapter_(\d+)$/', $key, $matches ) ) {
			return array();
		}

		return array(
			'story_id'   => absint( $matches[1] ?? 0 ),
			'chapter_id' => absint( $matches[2] ?? 0 ),
		);
	}

	/**
	 * Sanitize one local entry.
	 *
	 * @since 1.6.0
	 * @param array $entry Raw entry.
	 * @return array<string,mixed>
	 */
	private static function sanitize_local_entry( $entry ) {
		$entry = is_array( $entry ) ? $entry : array();
		$data = array(
			'timestamp' => absint( $entry['timestamp'] ?? 0 ),
		);

		if ( ! empty( $entry['like'] ) ) {
			$data['like'] = true;
		}

		if ( ! empty( $entry['dislike'] ) ) {
			$data['dislike'] = true;
		}

		if ( ! empty( $entry['read'] ) ) {
			$data['read'] = true;
		}

		if ( ! empty( $entry['view'] ) ) {
			$data['view'] = true;
		}

		if ( ! empty( $entry['follow'] ) ) {
			$data['follow'] = true;
		}

		if ( array_key_exists( 'rating', $entry ) ) {
			$rating = self::normalize_rating( $entry['rating'] );
			if ( false !== $rating ) {
				$data['rating'] = $rating;
			}
		}

		return $data;
	}

	/**
	 * Get current week/month stamp pair.
	 *
	 * @since 1.6.0
	 * @return array{week:int,month:int}
	 */
	private static function get_period_stamps() {
		$timestamp = (int) current_time( 'timestamp', true );
		return array(
			'week'  => (int) wp_date( 'oW', $timestamp ),
			'month' => (int) wp_date( 'Ym', $timestamp ),
		);
	}

	/**
	 * Delete known interaction/stats transients.
	 *
	 * @since 1.6.0
	 * @param int $chapter_id Chapter ID.
	 * @param int $story_id   Story ID.
	 * @return void
	 */
	private static function delete_stats_cache( $chapter_id, $story_id ) {
		$chapter_id = absint( $chapter_id );
		$story_id   = absint( $story_id );

		if ( $chapter_id > 0 ) {
			delete_transient( 'fanfic_chapter_' . $chapter_id . '_likes' );
			delete_transient( 'fanfic_chapter_' . $chapter_id . '_rating' );
			delete_transient( 'fanfic_chapter_views_' . $chapter_id );
			delete_transient( 'fanfic_chapter_stats_' . $chapter_id );
		}

		if ( $story_id > 0 ) {
			delete_transient( 'fanfic_story_' . $story_id . '_likes' );
			delete_transient( 'fanfic_story_' . $story_id . '_rating' );
			delete_transient( 'fanfic_story_views_' . $story_id );
			delete_transient( 'fanfic_story_stats_' . $story_id );
		}
	}
}
