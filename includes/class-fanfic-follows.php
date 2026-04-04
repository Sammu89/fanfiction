<?php
/**
 * Follows System Class
 *
 * Handles story, chapter, and author follow functionality via the unified interactions table.
 *
 * @package FanfictionManager
 * @since 1.8.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Follows
 *
 * Follow system backed by wp_fanfic_interactions WHERE interaction_type = 'follow'.
 * Supports story, chapter, and author follows for anonymous and authenticated users.
 *
 * @since 1.8.0
 */
class Fanfic_Follows {

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
	 * Check whether a logged-in user wants email updates for followed stories.
	 *
	 * @since 2.2.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function should_email_followed_stories( $user_id ) {
		return self::get_follow_email_setting( $user_id, 'email_followed_stories' );
	}

	/**
	 * Check whether a logged-in user wants email updates for followed chapters.
	 *
	 * @since 2.2.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function should_email_followed_chapters( $user_id ) {
		return self::get_follow_email_setting( $user_id, 'email_followed_chapters' );
	}

	/**
	 * Check whether a logged-in user wants email updates for followed authors.
	 *
	 * @since 2.2.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function should_email_followed_authors( $user_id ) {
		return self::get_follow_email_setting( $user_id, 'email_followed_authors' );
	}

	/**
	 * Read follow-email settings from the private notification settings panel.
	 *
	 * @since 2.2.0
	 * @param int    $user_id User ID.
	 * @param string $key     Settings key.
	 * @return bool
	 */
	private static function get_follow_email_setting( $user_id, $key ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$settings = get_user_meta( $user_id, 'fanfic_notification_settings', true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$defaults = array(
			'email_followed_stories'  => true,
			'email_followed_chapters' => true,
			'email_followed_authors'  => true,
		);

		$settings = wp_parse_args( $settings, $defaults );

		return ! empty( $settings[ $key ] );
	}

	/**
	 * Toggle follow for a post (story or chapter).
	 *
	 * @since 1.8.0
	 * @param int    $user_id        User ID (0 for anonymous).
	 * @param int    $post_id        Post ID (story or chapter).
	 * @param string $anonymous_uuid Anonymous UUID (empty for logged-in users).
	 * @return array Result with is_followed status.
	 */
	public static function toggle_follow( $user_id, $post_id, $anonymous_uuid = '' ) {
		$user_id = absint( $user_id );
		$post_id = absint( $post_id );

		if ( ! $post_id && ! $user_id && empty( $anonymous_uuid ) ) {
			return array( 'success' => false, 'error' => 'Invalid parameters' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'Post not found' );
		}

		$result = Fanfic_Interactions::toggle_follow( $post_id, $user_id, $anonymous_uuid );
		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'error' => $result->get_error_message() );
		}

		self::clear_follow_cache( $post_id, $user_id );

		// Auto-follow parent story when following a chapter.
		if ( ! empty( $result['is_followed'] ) && 'fanfiction_chapter' === $post->post_type ) {
			self::auto_follow_parent_story( $user_id, $post_id, $anonymous_uuid );
		}

		return array(
			'success'       => true,
			'is_followed' => ! empty( $result['is_followed'] ),
		);
	}

	/**
	 * Toggle follow for an author profile.
	 *
	 * @since 2.2.0
	 * @param int    $user_id        User ID (0 for anonymous).
	 * @param int    $author_id      Author user ID.
	 * @param string $anonymous_uuid Anonymous UUID (empty for logged-in users).
	 * @return array Result with is_followed status.
	 */
	public static function toggle_author_follow( $user_id, $author_id, $anonymous_uuid = '' ) {
		$user_id   = absint( $user_id );
		$author_id = absint( $author_id );

		if ( ! $author_id && ! $user_id && empty( $anonymous_uuid ) ) {
			return array( 'success' => false, 'error' => 'Invalid parameters' );
		}

		$author = get_userdata( $author_id );
		if ( ! $author instanceof WP_User ) {
			return array( 'success' => false, 'error' => 'Author not found' );
		}

		$result = Fanfic_Interactions::toggle_author_follow( $author_id, $user_id, $anonymous_uuid );
		if ( is_wp_error( $result ) ) {
			return array( 'success' => false, 'error' => $result->get_error_message() );
		}

		self::clear_author_follow_cache( $author_id, $user_id );

		return array(
			'success'     => true,
			'is_followed' => ! empty( $result['is_followed'] ),
		);
	}

	/**
	 * Auto-follow parent story when a chapter is followed.
	 *
	 * Ensures the parent story is followed (idempotent) and clears caches.
	 *
	 * @since 1.8.0
	 * @param int    $user_id        User ID (0 for anonymous).
	 * @param int    $chapter_id     Chapter post ID.
	 * @param string $anonymous_uuid Anonymous UUID.
	 * @return void
	 */
	public static function auto_follow_parent_story( $user_id, $chapter_id, $anonymous_uuid = '' ) {
		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type || ! $chapter->post_parent ) {
			return;
		}

		$story_id = absint( $chapter->post_parent );
		$was_new  = Fanfic_Interactions::upsert_follow( $story_id, $user_id, $anonymous_uuid );

		if ( $was_new ) {
			self::clear_follow_cache( $story_id, $user_id );
		}
	}

	/**
	 * Check if post is followed.
	 *
	 * @since 1.8.0
	 * @param int    $user_id        User ID.
	 * @param int    $post_id        Post ID.
	 * @param string $anonymous_uuid Anonymous UUID.
	 * @return bool
	 */
	public static function is_followed( $user_id, $post_id, $anonymous_uuid = '' ) {
		return Fanfic_Interactions::has_follow( $post_id, $user_id, $anonymous_uuid );
	}

	/**
	 * Check if an author is followed.
	 *
	 * @since 2.2.0
	 * @param int    $user_id        User ID.
	 * @param int    $author_id      Author user ID.
	 * @param string $anonymous_uuid Anonymous UUID.
	 * @return bool
	 */
	public static function is_author_followed( $user_id, $author_id, $anonymous_uuid = '' ) {
		return Fanfic_Interactions::has_author_follow( $author_id, $user_id, $anonymous_uuid );
	}

	/**
	 * Get follow count for a post.
	 *
	 * @since 1.8.0
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function get_follow_count( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 0;
		}

		$cache_key = 'fanfic_follow_count_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE chapter_id = %d AND interaction_type = 'follow'",
				$post_id
			)
		);

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get follow count for an author.
	 *
	 * @since 2.2.0
	 * @param int $author_id Author user ID.
	 * @return int
	 */
	public static function get_author_follow_count( $author_id ) {
		$author_id = absint( $author_id );
		if ( ! $author_id ) {
			return 0;
		}

		$cache_key = 'fanfic_author_follow_count_' . $author_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE chapter_id = %d AND interaction_type = 'follow_author'",
				$author_id
			)
		);

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Get user's followed posts.
	 *
	 * @since 1.8.0
	 * @param int    $user_id       User ID.
	 * @param string $follow_type 'story' or 'chapter'.
	 * @param int    $limit         Limit.
	 * @param int    $offset        Offset.
	 * @return array Array of follow data.
	 */
	public static function get_user_follows( $user_id, $follow_type = 'story', $limit = 50, $offset = 0 ) {
		$user_id = absint( $user_id );
		$limit   = absint( $limit );
		$offset  = absint( $offset );

		if ( ! $user_id || ! in_array( $follow_type, array( 'story', 'chapter', 'author' ), true ) ) {
			return array();
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;
		$users_table = $wpdb->users;

		if ( 'author' === $follow_type ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT i.chapter_id AS post_id, i.created_at
					FROM {$table} i
					INNER JOIN {$users_table} u ON u.ID = i.chapter_id
					WHERE i.user_id = %d
					  AND i.interaction_type = 'follow_author'
					ORDER BY i.created_at DESC
					LIMIT %d OFFSET %d",
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);

			foreach ( $results as &$row ) {
				$row['follow_type'] = 'author';
			}

			return $results;
		}

		$post_type = ( 'story' === $follow_type ) ? 'fanfiction_story' : 'fanfiction_chapter';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.chapter_id AS post_id, i.created_at
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.user_id = %d
				  AND i.interaction_type = 'follow'
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

		// Add follow_type to each result for rendering.
		foreach ( $results as &$row ) {
			$row['follow_type'] = $follow_type;
		}

		return $results;
	}

	/**
	 * Get user's follow count by type.
	 *
	 * @since 1.8.0
	 * @param int    $user_id       User ID.
	 * @param string $follow_type 'story' or 'chapter'.
	 * @return int
	 */
	public static function get_follows_count( $user_id, $follow_type = 'story' ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! in_array( $follow_type, array( 'story', 'chapter', 'author' ), true ) ) {
			return 0;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;

		if ( 'author' === $follow_type ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$table}
					WHERE user_id = %d
					  AND interaction_type = 'follow_author'",
					$user_id
				)
			);
		}

		$post_type = ( 'story' === $follow_type ) ? 'fanfiction_story' : 'fanfiction_chapter';

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.user_id = %d
				  AND i.interaction_type = 'follow'
				  AND p.post_type = %s
				  AND p.post_status = 'publish'",
				$user_id,
				$post_type
			)
		);

		return $count;
	}

	/**
	 * Batch get follow status for multiple posts.
	 *
	 * @since 1.8.0
	 * @param int   $user_id  User ID.
	 * @param array $post_ids Post IDs.
	 * @return array post_id => bool
	 */
	public static function batch_get_follow_status( $user_id, $post_ids ) {
		$user_id  = absint( $user_id );
		$post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );

		if ( empty( $post_ids ) || ! $user_id ) {
			return array();
		}

		$result = array_fill_keys( $post_ids, false );

		global $wpdb;
		$table        = $wpdb->prefix . 'fanfic_interactions';
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$followed = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT chapter_id FROM {$table}
				WHERE user_id = %d
				  AND interaction_type = 'follow'
				  AND chapter_id IN ({$placeholders})",
				array_merge( array( $user_id ), $post_ids )
			)
		);

		foreach ( $followed as $post_id ) {
			$result[ absint( $post_id ) ] = true;
		}

		return $result;
	}

	/**
	 * Get most followed stories.
	 *
	 * @since 1.8.0
	 * @param int $limit         Limit.
	 * @param int $min_follows Minimum follows.
	 * @return array
	 */
	public static function get_most_followed_stories( $limit = 10, $min_follows = 1 ) {
		$cache_key = 'fanfic_most_followed_stories_' . $limit . '_' . $min_follows;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'fanfic_interactions';
		$posts_table = $wpdb->posts;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.chapter_id AS story_id, COUNT(*) AS follow_count
				FROM {$table} i
				INNER JOIN {$posts_table} p ON p.ID = i.chapter_id
				WHERE i.interaction_type = 'follow'
				  AND p.post_type = 'fanfiction_story'
				  AND p.post_status = 'publish'
				GROUP BY i.chapter_id
				HAVING follow_count >= %d
				ORDER BY follow_count DESC
				LIMIT %d",
				absint( $min_follows ),
				absint( $limit )
			)
		);

		set_transient( $cache_key, $results, 30 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get recently followed stories.
	 *
	 * @since 1.8.0
	 * @param int $limit Limit.
	 * @return array Story IDs.
	 */
	public static function get_recently_followed_stories( $limit = 10 ) {
		$cache_key = 'fanfic_recently_followed_stories_' . $limit;
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
				WHERE i.interaction_type = 'follow'
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
	 * Get follow statistics.
	 *
	 * @since 1.8.0
	 * @return array
	 */
	public static function get_follow_stats() {
		$cache_key = 'fanfic_follow_stats';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';

		$stats = array(
			'total_follows'         => 0,
			'unique_stories'          => 0,
			'unique_users'            => 0,
			'avg_follows_per_story' => 0,
		);

		$stats['total_follows'] = absint( $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE interaction_type = 'follow'"
		) );

		$stats['unique_stories'] = absint( $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT i.chapter_id)
				FROM {$table} i
				INNER JOIN {$wpdb->posts} p ON p.ID = i.chapter_id
				WHERE i.interaction_type = 'follow'
				  AND p.post_type = %s",
				'fanfiction_story'
			)
		) );

		$stats['unique_users'] = absint( $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$table}
			WHERE interaction_type = 'follow' AND user_id IS NOT NULL AND user_id > 0"
		) );

		if ( $stats['unique_stories'] > 0 ) {
			$stats['avg_follows_per_story'] = round( $stats['total_follows'] / $stats['unique_stories'], 1 );
		}

		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Render user follows dashboard for one type.
	 *
	 * @since 1.8.0
	 * @param int    $user_id       User ID.
	 * @param string $type          'story' or 'chapter'.
	 * @param int    $limit         Limit.
	 * @param int    $offset        Offset.
	 * @return string HTML.
	 */
	public static function render_user_follows_dashboard( $user_id, $type = 'story', $limit = 20, $offset = 0 ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}

		$follows = self::get_user_follows( $user_id, $type, $limit, $offset );

		if ( empty( $follows ) ) {
			return '<p class="fanfic-user-follows-empty">' . esc_html__( 'No follows found.', 'fanfiction-manager' ) . '</p>';
		}

		if ( 'story' === $type ) {
			self::preload_story_card_data_for_follows( $follows );
		}

		$output = '';

		foreach ( $follows as $follow ) {
			if ( 'chapter' === $type ) {
				$output .= self::render_chapter_follow_item( $follow );
			} elseif ( 'author' === $type ) {
				$output .= self::render_author_follow_item( $follow );
			} else {
				$output .= self::render_story_follow_item( $follow );
			}
		}

		return $output;
	}

	/**
	 * Render story follow item.
	 *
	 * @since 1.8.0
	 * @param array $follow_data Follow data with 'post_id'.
	 * @return string HTML.
	 */
	public static function render_story_follow_item( $follow_data ) {
		if ( empty( $follow_data['post_id'] ) ) {
			return '';
		}

		$story = get_post( $follow_data['post_id'] );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return '';
		}

		if ( ! function_exists( 'fanfic_get_story_card_html' ) ) {
			return '';
		}

		$output = fanfic_get_story_card_html( $story->ID );
		if ( '' === $output ) {
			return '';
		}

		$output = preg_replace(
			'/class="([^"]*\bfanfic-story-card\b[^"]*)"/',
			'class="$1 fanfic-follow-story-card"',
			$output,
			1
		);

		$latest_update_html = self::build_story_follow_latest_update_html( $story->ID );
		if ( '' !== $latest_update_html ) {
			$output = preg_replace( '/<\/article>\s*$/', $latest_update_html . '</article>', $output, 1 );
		}

		return $output;
	}

	/**
	 * Render chapter follow item.
	 *
	 * @since 1.8.0
	 * @param array $follow_data Follow data with 'post_id'.
	 * @return string HTML.
	 */
	public static function render_chapter_follow_item( $follow_data ) {
		if ( empty( $follow_data['post_id'] ) ) {
			return '';
		}

		$chapter = get_post( $follow_data['post_id'] );
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

		$display_date   = fanfic_get_chapter_content_updated_date( $chapter->ID );
		$display_ts     = $display_date ? strtotime( $display_date ) : false;
		$formatted_date = $display_ts ? wp_date( get_option( 'date_format' ), $display_ts ) : '';

		$current_user_id = get_current_user_id();
		$is_read         = false;
		if ( $current_user_id && class_exists( 'Fanfic_Interactions' ) ) {
			$is_read = Fanfic_Interactions::is_chapter_read_current( $current_user_id, $chapter->ID );
		}

		$output  = '<div class="fanfic-follow-item fanfic-follow-chapter">';
		$output .= '<h4><a href="' . esc_url( get_permalink( $chapter->ID ) ) . '">' . esc_html( $chapter_label ) . '</a></h4>';
		$output .= '<p class="fanfic-follow-meta">';
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
	 * Render author follow item.
	 *
	 * @since 2.2.0
	 * @param array $follow_data Follow data with 'post_id'.
	 * @return string HTML.
	 */
	public static function render_author_follow_item( $follow_data ) {
		$author_id = isset( $follow_data['post_id'] ) ? absint( $follow_data['post_id'] ) : 0;
		if ( ! $author_id ) {
			return '';
		}

		$author = get_userdata( $author_id );
		if ( ! $author instanceof WP_User ) {
			return '';
		}

		$profile_url = function_exists( 'fanfic_get_user_profile_url' ) ? fanfic_get_user_profile_url( $author_id ) : get_author_posts_url( $author_id );
		$bio         = wp_html_excerpt( wp_strip_all_tags( (string) get_user_meta( $author_id, 'description', true ) ), 180, '...' );

		$output  = '<div class="fanfic-follow-item fanfic-follow-author">';
		$output .= '<h4><a href="' . esc_url( $profile_url ) . '">' . esc_html( $author->display_name ) . '</a></h4>';
		if ( '' !== $bio ) {
			$output .= '<p class="fanfic-follow-meta">' . esc_html( $bio ) . '</p>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Get logged-in followers of an author.
	 *
	 * @since 2.2.0
	 * @param int $author_id Author user ID.
	 * @return array<int>
	 */
	public static function get_author_follower_user_ids( $author_id ) {
		$author_id = absint( $author_id );
		if ( ! $author_id ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_interactions';

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$table}
				WHERE chapter_id = %d
				  AND interaction_type = 'follow_author'
				  AND user_id > 0
				  AND user_id != %d",
				$author_id,
				$author_id
			)
		);

		return $ids ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * Clear follow cache.
	 *
	 * @since 1.8.0
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function clear_follow_cache( $post_id, $user_id ) {
		$post_id = absint( $post_id );
		$user_id = absint( $user_id );

		delete_transient( 'fanfic_follow_count_' . $post_id );
		delete_transient( 'fanfic_follow_stats' );
		delete_transient( 'fanfic_recently_followed_stories_10' );
		delete_transient( 'fanfic_recently_followed_stories_20' );

		for ( $i = 5; $i <= 20; $i += 5 ) {
			for ( $j = 1; $j <= 5; $j++ ) {
				delete_transient( 'fanfic_most_followed_stories_' . $i . '_' . $j );
			}
		}

		// Reset in-request follow rendering caches.
		self::$read_chapters_cache = array();
		self::$latest_chapter_cache = array();
	}

	/**
	 * Clear author-follow cache.
	 *
	 * @since 2.2.0
	 * @param int $author_id Author user ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function clear_author_follow_cache( $author_id, $user_id ) {
		$author_id = absint( $author_id );
		$user_id   = absint( $user_id );

		delete_transient( 'fanfic_author_follow_count_' . $author_id );
		delete_transient( 'fanfic_follow_stats' );

		self::$read_chapters_cache   = array();
		self::$latest_chapter_cache  = array();
	}

	/**
	 * Preload story card metadata for a follow set.
	 *
	 * @since 1.8.0
	 * @param array $follows Follow rows.
	 * @return void
	 */
	private static function preload_story_card_data_for_follows( $follows ) {
		if ( ! function_exists( 'fanfic_preload_story_card_index_data' ) ) {
			return;
		}

		$story_ids = array();
		foreach ( (array) $follows as $follow ) {
			$story_id = isset( $follow['post_id'] ) ? absint( $follow['post_id'] ) : 0;
			if ( $story_id ) {
				$story_ids[] = $story_id;
			}
		}

		if ( empty( $story_ids ) ) {
			return;
		}

		fanfic_preload_story_card_index_data( array_values( array_unique( $story_ids ) ) );
	}

	/**
	 * Build the latest chapter footer displayed on followed story cards.
	 *
	 * @since 1.8.0
	 * @param int $story_id Story ID.
	 * @return string
	 */
	private static function build_story_follow_latest_update_html( $story_id ) {
		$latest_chapter = self::get_latest_story_chapter_helper( $story_id );
		if ( ! $latest_chapter ) {
			return '';
		}

		$latest_chapter_label = self::get_chapter_label_helper( $latest_chapter->ID );
		$display_date        = fanfic_get_chapter_content_updated_date( $latest_chapter->ID );
		$display_ts          = $display_date ? strtotime( $display_date ) : false;
		$latest_chapter_date = $display_ts ? wp_date( get_option( 'date_format' ), $display_ts ) : '';

		return sprintf(
			'<p class="fanfic-follow-story-latest-update">%1$s <a href="%2$s">%3$s</a> %4$s</p>',
			esc_html__( 'Latest update:', 'fanfiction-manager' ),
			esc_url( get_permalink( $latest_chapter->ID ) ),
			esc_html( $latest_chapter_label ),
			esc_html(
				sprintf(
					/* translators: %s: chapter date */
					__( 'on %s', 'fanfiction-manager' ),
					$latest_chapter_date
				)
			)
		);
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
