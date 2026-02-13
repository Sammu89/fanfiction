<?php
/**
 * Bookmarks System Class
 *
 * Handles story and chapter bookmark functionality via the unified interactions table.
 *
 * @package FanfictionManager
 * @since 1.8.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Bookmarks
 *
 * Bookmark system backed by wp_fanfic_interactions WHERE interaction_type = 'bookmark'.
 * Supports both story and chapter bookmarks, anonymous + authenticated users.
 *
 * @since 1.8.0
 */
class Fanfic_Bookmarks {

	/**
	 * In-request cache for read chapters by user/story.
	 *
	 * @var array<string,array<int>>
	 */
	private static $read_chapters_cache = array();

	/**
	 * In-request cache for latest chapter by story.
	 *
	 * @var array<int,WP_Post|false>
	 */
	private static $latest_chapter_cache = array();

	/**
	 * Toggle bookmark for a post (story or chapter).
	 *
	 * @since 1.8.0
	 * @param int    $user_id        User ID (0 for anonymous).
	 * @param int    $post_id        Post ID (story or chapter).
	 * @param string $anonymous_uuid Anonymous UUID (empty for logged-in users).
	 * @return array Result with is_bookmarked status.
	 */
	public static function toggle_bookmark( $user_id, $post_id, $anonymous_uuid = '' ) {
		$user_id = absint( $user_id );
		$post_id = absint( $post_id );

		if ( ! $post_id && ! $user_id && empty( $anonymous_uuid ) ) {
			return array( 'success' => false, 'error' => 'Invalid parameters' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'Post not found' );
		}

		$result = Fanfic_Interactions::toggle_bookmark( $post_id, $user_id, $anonymous_uuid );
		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'error' => $result->get_error_message() );
		}

		self::clear_bookmark_cache( $post_id, $user_id );

		return array(
			'success'       => true,
			'is_bookmarked' => ! empty( $result['is_bookmarked'] ),
		);
	}

	/**
	 * Check if post is bookmarked.
	 *
	 * @since 1.8.0
	 * @param int    $user_id        User ID.
	 * @param int    $post_id        Post ID.
	 * @param string $anonymous_uuid Anonymous UUID.
	 * @return bool
	 */
	public static function is_bookmarked( $user_id, $post_id, $anonymous_uuid = '' ) {
		return Fanfic_Interactions::has_bookmark( $post_id, $user_id, $anonymous_uuid );
	}

	/**
	 * Get bookmark count for a post.
	 *
	 * @since 1.8.0
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function get_bookmark_count( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 0;
		}

		$cache_key = 'fanfic_bookmark_count_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE chapter_id = %d AND interaction_type = 'bookmark'",
				$post_id
			)
		);

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get user's bookmarked posts.
	 *
	 * @since 1.8.0
	 * @param int    $user_id       User ID.
	 * @param string $bookmark_type 'story' or 'chapter'.
	 * @param int    $limit         Limit.
	 * @param int    $offset        Offset.
	 * @return array Array of bookmark data.
	 */
	public static function get_user_bookmarks( $user_id, $bookmark_type = 'story', $limit = 50, $offset = 0 ) {
		$user_id = absint( $user_id );
		$limit   = absint( $limit );
		$offset  = absint( $offset );

		if ( ! $user_id || ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return array();
		}

		$post_type = ( 'story' === $bookmark_type ) ? 'fanfiction_story' : 'fanfiction_chapter';

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.chapter_id AS post_id, i.created_at
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.user_id = %d
				  AND i.interaction_type = 'bookmark'
				  AND p.post_type = %s
				  AND p.post_status = 'publish'
				ORDER BY i.created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$post_type,
				$limit,
				$offset
			),
			ARRAY_A
		);

		// Add bookmark_type to each result for rendering.
		foreach ( $results as &$row ) {
			$row['bookmark_type'] = $bookmark_type;
		}

		return $results;
	}

	/**
	 * Get user's bookmark count by type.
	 *
	 * @since 1.8.0
	 * @param int    $user_id       User ID.
	 * @param string $bookmark_type 'story' or 'chapter'.
	 * @return int
	 */
	public static function get_bookmarks_count( $user_id, $bookmark_type = 'story' ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! in_array( $bookmark_type, array( 'story', 'chapter' ), true ) ) {
			return 0;
		}

		$post_type = ( 'story' === $bookmark_type ) ? 'fanfiction_story' : 'fanfiction_chapter';

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.user_id = %d
				  AND i.interaction_type = 'bookmark'
				  AND p.post_type = %s
				  AND p.post_status = 'publish'",
				$user_id,
				$post_type
			)
		);

		return $count;
	}

	/**
	 * Batch get bookmark status for multiple posts.
	 *
	 * @since 1.8.0
	 * @param int   $user_id  User ID.
	 * @param array $post_ids Post IDs.
	 * @return array post_id => bool
	 */
	public static function batch_get_bookmark_status( $user_id, $post_ids ) {
		$user_id  = absint( $user_id );
		$post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );

		if ( empty( $post_ids ) || ! $user_id ) {
			return array();
		}

		$result = array_fill_keys( $post_ids, false );

		global $wpdb;
		$table        = $wpdb->prefix . 'fanfic_interactions';
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$bookmarked = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT chapter_id FROM {$table}
				WHERE user_id = %d
				  AND interaction_type = 'bookmark'
				  AND chapter_id IN ({$placeholders})",
				array_merge( array( $user_id ), $post_ids )
			)
		);

		foreach ( $bookmarked as $post_id ) {
			$result[ absint( $post_id ) ] = true;
		}

		return $result;
	}

	/**
	 * Get most bookmarked stories.
	 *
	 * @since 1.8.0
	 * @param int $limit         Limit.
	 * @param int $min_bookmarks Minimum bookmarks.
	 * @return array
	 */
	public static function get_most_bookmarked_stories( $limit = 10, $min_bookmarks = 1 ) {
		$cache_key = 'fanfic_most_bookmarked_stories_' . $limit . '_' . $min_bookmarks;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.chapter_id AS story_id, COUNT(*) AS bookmark_count
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.interaction_type = 'bookmark'
				  AND p.post_type = 'fanfiction_story'
				  AND p.post_status = 'publish'
				GROUP BY i.chapter_id
				HAVING bookmark_count >= %d
				ORDER BY bookmark_count DESC
				LIMIT %d",
				absint( $min_bookmarks ),
				absint( $limit )
			)
		);

		set_transient( $cache_key, $results, 30 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get recently bookmarked stories.
	 *
	 * @since 1.8.0
	 * @param int $limit Limit.
	 * @return array Story IDs.
	 */
	public static function get_recently_bookmarked_stories( $limit = 10 ) {
		$cache_key = 'fanfic_recently_bookmarked_stories_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT i.chapter_id
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.interaction_type = 'bookmark'
				  AND p.post_type = 'fanfiction_story'
				  AND p.post_status = 'publish'
				ORDER BY i.created_at DESC
				LIMIT %d",
				absint( $limit )
			)
		);

		$story_ids = array_map( 'absint', $results );
		set_transient( $cache_key, $story_ids, 5 * MINUTE_IN_SECONDS );

		return $story_ids;
	}

	/**
	 * Get bookmark statistics.
	 *
	 * @since 1.8.0
	 * @return array
	 */
	public static function get_bookmark_stats() {
		$cache_key = 'fanfic_bookmark_stats';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';

		$stats = array(
			'total_bookmarks'         => 0,
			'unique_stories'          => 0,
			'unique_users'            => 0,
			'avg_bookmarks_per_story' => 0,
		);

		$stats['total_bookmarks'] = absint( $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE interaction_type = 'bookmark'"
		) );

		$stats['unique_stories'] = absint( $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT i.chapter_id)
				FROM {$table} i
				INNER JOIN {$wpdb->posts} p ON p.ID = i.chapter_id
				WHERE i.interaction_type = 'bookmark'
				  AND p.post_type = %s",
				'fanfiction_story'
			)
		) );

		$stats['unique_users'] = absint( $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$table}
			WHERE interaction_type = 'bookmark' AND user_id IS NOT NULL AND user_id > 0"
		) );

		if ( $stats['unique_stories'] > 0 ) {
			$stats['avg_bookmarks_per_story'] = round( $stats['total_bookmarks'] / $stats['unique_stories'], 1 );
		}

		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Render user bookmarks dashboard for one type.
	 *
	 * @since 1.8.0
	 * @param int    $user_id       User ID.
	 * @param string $type          'story' or 'chapter'.
	 * @param int    $limit         Limit.
	 * @param int    $offset        Offset.
	 * @return string HTML.
	 */
	public static function render_user_bookmarks_dashboard( $user_id, $type = 'story', $limit = 20, $offset = 0 ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}

		$bookmarks = self::get_user_bookmarks( $user_id, $type, $limit, $offset );

		if ( empty( $bookmarks ) ) {
			return '<div class="fanfic-user-bookmarks-list"><p>' . esc_html__( 'No bookmarks found.', 'fanfiction-manager' ) . '</p></div>';
		}

		$output = '<div class="fanfic-user-bookmarks-list">';

		foreach ( $bookmarks as $bookmark ) {
			if ( 'chapter' === $type ) {
				$output .= self::render_chapter_bookmark_item( $bookmark );
			} else {
				$output .= self::render_story_bookmark_item( $bookmark );
			}
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render story bookmark item.
	 *
	 * @since 1.8.0
	 * @param array $bookmark_data Bookmark data with 'post_id'.
	 * @return string HTML.
	 */
	public static function render_story_bookmark_item( $bookmark_data ) {
		if ( empty( $bookmark_data['post_id'] ) ) {
			return '';
		}

		$story = get_post( $bookmark_data['post_id'] );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return '';
		}

		$author      = get_userdata( $story->post_author );
		$author_name = $author ? $author->display_name : __( 'Unknown Author', 'fanfiction-manager' );

		$formatted_date = wp_date( get_option( 'date_format' ), strtotime( $story->post_date ) );

		$latest_chapter = self::get_latest_story_chapter_helper( $story->ID );
		$is_read        = false;

		if ( $latest_chapter ) {
			$latest_chapter_number = get_post_meta( $latest_chapter->ID, '_fanfic_chapter_number', true );
			$latest_chapter_label  = self::get_chapter_label_helper( $latest_chapter->ID );

			$post_date     = strtotime( $latest_chapter->post_date );
			$post_modified = strtotime( $latest_chapter->post_modified );
			$display_date  = ( $post_modified > $post_date ) ? $latest_chapter->post_modified : $latest_chapter->post_date;
			$latest_chapter_date = wp_date( get_option( 'date_format' ), strtotime( $display_date ) );

			$current_user_id = get_current_user_id();
			if ( $current_user_id && class_exists( 'Fanfic_Reading_Progress' ) ) {
				$read_chapters = self::get_cached_read_chapters_helper( $current_user_id, $story->ID );
				$is_read       = in_array( absint( $latest_chapter_number ), $read_chapters, true );
			}
		}

		$output  = '<div class="fanfic-bookmark-item fanfic-bookmark-story">';
		$output .= '<h4><a href="' . esc_url( get_permalink( $story->ID ) ) . '">' . esc_html( $story->post_title ) . '</a></h4>';
		$output .= '<p class="fanfic-bookmark-meta">';
		$output .= esc_html__( 'by', 'fanfiction-manager' ) . ' ' . esc_html( $author_name ) . ', ';
		$output .= esc_html__( 'published on', 'fanfiction-manager' ) . ' ' . esc_html( $formatted_date );
		$output .= '</p>';

		if ( $latest_chapter ) {
			$output .= '<p class="fanfic-bookmark-last-chapter">';
			$output .= esc_html__( 'Last chapter:', 'fanfiction-manager' ) . ' ';
			$output .= '<a href="' . esc_url( get_permalink( $latest_chapter->ID ) ) . '">' . esc_html( $latest_chapter_label ) . '</a>, ';
			$output .= esc_html( $latest_chapter_date );
			$output .= '</p>';
		}

		if ( $is_read ) {
			$output .= '<span class="fanfic-badge read">' . esc_html__( '✓ Read', 'fanfiction-manager' ) . '</span>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render chapter bookmark item.
	 *
	 * @since 1.8.0
	 * @param array $bookmark_data Bookmark data with 'post_id'.
	 * @return string HTML.
	 */
	public static function render_chapter_bookmark_item( $bookmark_data ) {
		if ( empty( $bookmark_data['post_id'] ) ) {
			return '';
		}

		$chapter = get_post( $bookmark_data['post_id'] );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			return '';
		}

		$story = get_post( $chapter->post_parent );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return '';
		}

		$author      = get_userdata( $story->post_author );
		$author_name = $author ? $author->display_name : __( 'Unknown Author', 'fanfiction-manager' );

		$chapter_number = get_post_meta( $chapter->ID, '_fanfic_chapter_number', true );
		$chapter_label  = self::get_chapter_label_helper( $chapter->ID );

		$post_date     = strtotime( $chapter->post_date );
		$post_modified = strtotime( $chapter->post_modified );
		$display_date  = ( $post_modified > $post_date ) ? $chapter->post_modified : $chapter->post_date;
		$formatted_date = wp_date( get_option( 'date_format' ), strtotime( $display_date ) );

		$current_user_id = get_current_user_id();
		$is_read         = false;
		if ( $current_user_id && class_exists( 'Fanfic_Reading_Progress' ) ) {
			$read_chapters = self::get_cached_read_chapters_helper( $current_user_id, $story->ID );
			$is_read       = in_array( absint( $chapter_number ), $read_chapters, true );
		}

		$output  = '<div class="fanfic-bookmark-item fanfic-bookmark-chapter">';
		$output .= '<h4><a href="' . esc_url( get_permalink( $chapter->ID ) ) . '">' . esc_html( $chapter_label ) . '</a></h4>';
		$output .= '<p class="fanfic-bookmark-meta">';
		$output .= esc_html__( 'part of', 'fanfiction-manager' ) . ' ';
		$output .= '<a href="' . esc_url( get_permalink( $story->ID ) ) . '">' . esc_html( $story->post_title ) . '</a>, ';
		$output .= esc_html__( 'by', 'fanfiction-manager' ) . ' ' . esc_html( $author_name ) . ', ';
		$output .= esc_html__( 'updated on', 'fanfiction-manager' ) . ' ' . esc_html( $formatted_date );
		$output .= '</p>';

		if ( $is_read ) {
			$output .= '<span class="fanfic-badge read">' . esc_html__( '✓ Read', 'fanfiction-manager' ) . '</span>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Clear bookmark cache.
	 *
	 * @since 1.8.0
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function clear_bookmark_cache( $post_id, $user_id ) {
		$post_id = absint( $post_id );
		$user_id = absint( $user_id );

		delete_transient( 'fanfic_bookmark_count_' . $post_id );
		delete_transient( 'fanfic_bookmark_stats' );
		delete_transient( 'fanfic_recently_bookmarked_stories_10' );
		delete_transient( 'fanfic_recently_bookmarked_stories_20' );

		for ( $i = 5; $i <= 20; $i += 5 ) {
			for ( $j = 1; $j <= 5; $j++ ) {
				delete_transient( 'fanfic_most_bookmarked_stories_' . $i . '_' . $j );
			}
		}

		// Reset in-request bookmark rendering caches.
		self::$read_chapters_cache = array();
		self::$latest_chapter_cache = array();
	}

	/**
	 * Get read chapters from in-request cache.
	 *
	 * @since 1.8.0
	 * @param int $user_id  User ID.
	 * @param int $story_id Story ID.
	 * @return array
	 */
	private static function get_cached_read_chapters_helper( $user_id, $story_id ) {
		$user_id  = absint( $user_id );
		$story_id = absint( $story_id );
		if ( ! $user_id || ! $story_id || ! class_exists( 'Fanfic_Reading_Progress' ) ) {
			return array();
		}

		$cache_key = $user_id . ':' . $story_id;
		if ( isset( self::$read_chapters_cache[ $cache_key ] ) ) {
			return self::$read_chapters_cache[ $cache_key ];
		}

		$read_chapters = Fanfic_Reading_Progress::batch_load_read_chapters( $user_id, $story_id );
		self::$read_chapters_cache[ $cache_key ] = is_array( $read_chapters ) ? $read_chapters : array();

		return self::$read_chapters_cache[ $cache_key ];
	}

	/**
	 * Get latest published chapter for a story.
	 *
	 * @since 1.8.0
	 * @param int $story_id Story ID.
	 * @return WP_Post|null
	 */
	private static function get_latest_story_chapter_helper( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return null;
		}

		if ( array_key_exists( $story_id, self::$latest_chapter_cache ) ) {
			return self::$latest_chapter_cache[ $story_id ] ?: null;
		}

		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_fanfic_chapter_number',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		) );

		if ( empty( $chapters ) ) {
			self::$latest_chapter_cache[ $story_id ] = false;
			return null;
		}

		self::$latest_chapter_cache[ $story_id ] = $chapters[0];
		return $chapters[0];
	}

	/**
	 * Get chapter label (helper).
	 *
	 * @since 1.8.0
	 * @param int $chapter_id Chapter ID.
	 * @return string
	 */
	private static function get_chapter_label_helper( $chapter_id ) {
		$chapter_type   = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

		if ( 'prologue' === $chapter_type ) {
			return __( 'Prologue', 'fanfiction-manager' );
		} elseif ( 'epilogue' === $chapter_type ) {
			return __( 'Epilogue', 'fanfiction-manager' );
		} else {
			return sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $chapter_number );
		}
	}
}
