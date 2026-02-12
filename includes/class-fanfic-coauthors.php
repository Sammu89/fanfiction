<?php
/**
 * Co-Authors System Class
 *
 * Handles collaborative authorship for fanfiction stories.
 *
 * @package FanfictionManager
 * @since 1.5.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Coauthors
 *
 * @since 1.5.3
 */
class Fanfic_Coauthors {

	const STATUS_PENDING  = 'pending';
	const STATUS_ACCEPTED = 'accepted';
	const STATUS_REFUSED  = 'refused';
	const MAX_COAUTHORS   = 5;
	const REST_NAMESPACE  = 'fanfic/v1';

	/**
	 * Request-level cache for accepted co-author checks.
	 *
	 * @var array<string,bool>
	 */
	private static $coauthor_cache = array();

	/**
	 * Request-level cache for pending co-author checks.
	 *
	 * @var array<string,bool>
	 */
	private static $pending_cache = array();

	/**
	 * Initialize hooks.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function init() {
		// Data should remain queryable even when the feature is disabled.
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_story_coauthors' ) );
		add_action( 'profile_update', array( __CLASS__, 'on_coauthor_profile_update' ), 10, 2 );

		if ( ! self::is_enabled() ) {
			return;
		}

		add_filter( 'posts_where', array( __CLASS__, 'filter_dashboard_query' ), 10, 2 );

		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_invite_coauthor',
			array( __CLASS__, 'ajax_invite_coauthor' ),
			true,
			array(
				'rate_limit' => true,
				'capability' => 'edit_fanfiction_stories',
			)
		);

		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_respond_coauthor',
			array( __CLASS__, 'ajax_respond_invitation' ),
			true,
			array(
				'rate_limit' => true,
				'capability' => 'read',
			)
		);

		Fanfic_AJAX_Security::register_ajax_handler(
			'fanfic_remove_coauthor',
			array( __CLASS__, 'ajax_remove_coauthor' ),
			true,
			array(
				'rate_limit' => true,
				'capability' => 'edit_fanfiction_stories',
			)
		);
	}

	/**
	 * Whether co-authors feature is enabled.
	 *
	 * @since 1.5.3
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! class_exists( 'Fanfic_Settings' ) ) {
			return false;
		}

		return (bool) Fanfic_Settings::get_setting( 'enable_coauthors', false );
	}

	/**
	 * Invite user as a co-author.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id Invited user ID.
	 * @param int $invited_by Inviter user ID.
	 * @return array{success:bool,message:string}
	 */
	public static function invite_coauthor( $story_id, $user_id, $invited_by ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'Co-author feature is currently disabled.', 'fanfiction-manager' ),
			);
		}

		if ( ! self::table_ready() ) {
			return array(
				'success' => false,
				'message' => __( 'Co-author table is not available.', 'fanfiction-manager' ),
			);
		}

		$story_id   = absint( $story_id );
		$user_id    = absint( $user_id );
		$invited_by = absint( $invited_by );

		if ( ! $story_id || ! $user_id || ! $invited_by ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid invitation parameters.', 'fanfiction-manager' ),
			);
		}

		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Story not found.', 'fanfiction-manager' ),
			);
		}

		$invited_user = get_user_by( 'ID', $user_id );
		if ( ! $invited_user ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'fanfiction-manager' ),
			);
		}

		if ( $user_id === $invited_by ) {
			return array(
				'success' => false,
				'message' => __( 'You cannot invite yourself as co-author.', 'fanfiction-manager' ),
			);
		}

		if ( self::is_original_author( $story_id, $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'The original author is already assigned to this story.', 'fanfiction-manager' ),
			);
		}

		if ( ! self::can_manage_coauthors( $story_id, $invited_by ) ) {
			return array(
				'success' => false,
				'message' => __( 'You cannot manage co-authors for this story.', 'fanfiction-manager' ),
			);
		}

		$table = self::get_table_name();

		$current_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE story_id = %d AND status IN (%s, %s)",
				$story_id,
				self::STATUS_PENDING,
				self::STATUS_ACCEPTED
			)
		);

		if ( $current_count >= self::MAX_COAUTHORS ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: max allowed co-authors. */
					__( 'Maximum of %d co-authors reached for this story.', 'fanfiction-manager' ),
					self::MAX_COAUTHORS
				),
			);
		}

		$existing_status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE story_id = %d AND user_id = %d LIMIT 1",
				$story_id,
				$user_id
			)
		);

		if ( self::STATUS_PENDING === $existing_status ) {
			return array(
				'success' => false,
				'message' => __( 'This user already has a pending invitation.', 'fanfiction-manager' ),
			);
		}
		if ( self::STATUS_ACCEPTED === $existing_status ) {
			return array(
				'success' => false,
				'message' => __( 'This user is already a co-author of this story.', 'fanfiction-manager' ),
			);
		}
		if ( self::STATUS_REFUSED === $existing_status ) {
			return array(
				'success' => false,
				'message' => __( 'This user has already refused a co-author invitation for this story.', 'fanfiction-manager' ),
			);
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'story_id'     => $story_id,
				'user_id'      => $user_id,
				'invited_by'   => $invited_by,
				'status'       => self::STATUS_PENDING,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create co-author invitation.', 'fanfiction-manager' ),
			);
		}

		self::clear_caches();
		self::notify_user(
			$user_id,
			'TYPE_COAUTHOR_INVITE',
			'coauthor_invite',
			sprintf(
				/* translators: 1: inviter display name, 2: story title. */
				__( '%1$s is asking you to co-author "%2$s".', 'fanfiction-manager' ),
				self::get_user_display_name( $invited_by ),
				get_the_title( $story_id )
			),
			array(
				'story_id'    => $story_id,
				'story_title' => get_the_title( $story_id ),
				'invited_by'  => $invited_by,
			)
		);

		return array(
			'success' => true,
			'message' => __( 'Co-author invitation sent.', 'fanfiction-manager' ),
		);
	}

	/**
	 * Accept pending invitation.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function accept_invitation( $story_id, $user_id ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		$user_id  = absint( $user_id );
		if ( ! $story_id || ! $user_id ) {
			return false;
		}

		$table = self::get_table_name();
		$updated = $wpdb->update(
			$table,
			array(
				'status'       => self::STATUS_ACCEPTED,
				'responded_at' => current_time( 'mysql' ),
			),
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
				'status'   => self::STATUS_PENDING,
			),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( false === $updated || 0 === $updated ) {
			return false;
		}

		$original_author = (int) get_post_field( 'post_author', $story_id );
		if ( $original_author > 0 && $original_author !== $user_id ) {
			self::notify_user(
				$original_author,
				'TYPE_COAUTHOR_ACCEPTED',
				'coauthor_accepted',
				sprintf(
					/* translators: 1: user display name, 2: story title. */
					__( '%1$s accepted your co-author invitation for "%2$s".', 'fanfiction-manager' ),
					self::get_user_display_name( $user_id ),
					get_the_title( $story_id )
				),
				array(
					'story_id'    => $story_id,
					'user_id'     => $user_id,
					'user_name'   => self::get_user_display_name( $user_id ),
					'story_title' => get_the_title( $story_id ),
				)
			);
		}

		self::clear_caches();
		self::reindex_story( $story_id );

		return true;
	}

	/**
	 * Refuse pending invitation.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function refuse_invitation( $story_id, $user_id ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		$user_id  = absint( $user_id );
		if ( ! $story_id || ! $user_id ) {
			return false;
		}

		$table = self::get_table_name();
		$updated = $wpdb->update(
			$table,
			array(
				'status'       => self::STATUS_REFUSED,
				'responded_at' => current_time( 'mysql' ),
			),
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
				'status'   => self::STATUS_PENDING,
			),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( false === $updated || 0 === $updated ) {
			return false;
		}

		$original_author = (int) get_post_field( 'post_author', $story_id );
		if ( $original_author > 0 && $original_author !== $user_id ) {
			self::notify_user(
				$original_author,
				'TYPE_COAUTHOR_REFUSED',
				'coauthor_refused',
				sprintf(
					/* translators: 1: user display name, 2: story title. */
					__( '%1$s refused your co-author invitation for "%2$s".', 'fanfiction-manager' ),
					self::get_user_display_name( $user_id ),
					get_the_title( $story_id )
				),
				array(
					'story_id'    => $story_id,
					'user_id'     => $user_id,
					'user_name'   => self::get_user_display_name( $user_id ),
					'story_title' => get_the_title( $story_id ),
				)
			);
		}

		self::clear_caches();

		return true;
	}

	/**
	 * Remove co-author from story.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id Removed user ID.
	 * @param int $removed_by Actor user ID.
	 * @return array{success:bool,message:string}
	 */
	public static function remove_coauthor( $story_id, $user_id, $removed_by ) {
		global $wpdb;

		if ( ! self::is_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'Co-author feature is currently disabled.', 'fanfiction-manager' ),
			);
		}

		if ( ! self::table_ready() ) {
			return array(
				'success' => false,
				'message' => __( 'Co-author table is not available.', 'fanfiction-manager' ),
			);
		}

		$story_id   = absint( $story_id );
		$user_id    = absint( $user_id );
		$removed_by = absint( $removed_by );

		if ( ! $story_id || ! $user_id || ! $removed_by ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid removal parameters.', 'fanfiction-manager' ),
			);
		}

		if ( ! self::can_manage_coauthors( $story_id, $removed_by ) ) {
			return array(
				'success' => false,
				'message' => __( 'You cannot manage co-authors for this story.', 'fanfiction-manager' ),
			);
		}

		$original_author = (int) get_post_field( 'post_author', $story_id );
		if ( $original_author === $user_id ) {
			return array(
				'success' => false,
				'message' => __( 'The original author cannot be removed.', 'fanfiction-manager' ),
			);
		}

		$deleted = $wpdb->delete(
			self::get_table_name(),
			array(
				'story_id' => $story_id,
				'user_id'  => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted || 0 === $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Co-author not found or could not be removed.', 'fanfiction-manager' ),
			);
		}

		self::clear_caches();
		self::notify_user(
			$user_id,
			'TYPE_COAUTHOR_REMOVED',
			'coauthor_removed',
			sprintf(
				/* translators: %s: story title. */
				__( 'You have been removed as co-author from "%s".', 'fanfiction-manager' ),
				get_the_title( $story_id )
			),
			array(
				'story_id'    => $story_id,
				'story_title' => get_the_title( $story_id ),
				'removed_by'  => $removed_by,
			)
		);

		self::reindex_story( $story_id );

		return array(
			'success' => true,
			'message' => __( 'Co-author removed.', 'fanfiction-manager' ),
		);
	}

	/**
	 * Get story co-authors by status.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param string $status Status filter.
	 * @return array<int,object>
	 */
	public static function get_story_coauthors( $story_id, $status = self::STATUS_ACCEPTED ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return array();
		}

		$story_id = absint( $story_id );
		$status   = self::sanitize_status( $status, self::STATUS_ACCEPTED );
		if ( ! $story_id ) {
			return array();
		}

		$table = self::get_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.user_id, c.status, u.display_name, u.user_email
				FROM {$table} c
				INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
				WHERE c.story_id = %d AND c.status = %s
				ORDER BY c.created_at ASC",
				$story_id,
				$status
			)
		);

		return self::normalize_user_rows( $rows );
	}

	/**
	 * Get all non-refused co-authors (pending + accepted).
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @return array<int,object>
	 */
	public static function get_all_story_coauthors( $story_id ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return array();
		}

		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return array();
		}

		$table = self::get_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.user_id, c.status, u.display_name, u.user_email
				FROM {$table} c
				INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
				WHERE c.story_id = %d AND c.status IN (%s, %s)
				ORDER BY c.created_at ASC",
				$story_id,
				self::STATUS_PENDING,
				self::STATUS_ACCEPTED
			)
		);

		return self::normalize_user_rows( $rows );
	}

	/**
	 * Get story IDs where user is a co-author.
	 *
	 * @since 1.5.3
	 * @param int $user_id User ID.
	 * @param string $status Status filter.
	 * @return int[]
	 */
	public static function get_user_coauthored_stories( $user_id, $status = self::STATUS_ACCEPTED ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return array();
		}

		$user_id = absint( $user_id );
		$status  = self::sanitize_status( $status, self::STATUS_ACCEPTED );
		if ( ! $user_id ) {
			return array();
		}

		$table = self::get_table_name();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT story_id FROM {$table} WHERE user_id = %d AND status = %s",
				$user_id,
				$status
			)
		);

		return array_values( array_unique( array_map( 'absint', (array) $rows ) ) );
	}

	/**
	 * Get pending invitations for a user.
	 *
	 * @since 1.5.3
	 * @param int $user_id User ID.
	 * @return array<int,object>
	 */
	public static function get_pending_invitations( $user_id ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return array();
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$table = self::get_table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.story_id, c.invited_by, c.created_at,
					p.post_title AS story_title,
					u.display_name AS inviter_name
				FROM {$table} c
				INNER JOIN {$wpdb->posts} p ON c.story_id = p.ID
				INNER JOIN {$wpdb->users} u ON c.invited_by = u.ID
				WHERE c.user_id = %d
					AND c.status = %s
					AND p.post_type = 'fanfiction_story'
				ORDER BY c.created_at DESC",
				$user_id,
				self::STATUS_PENDING
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Whether user is accepted co-author of story.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_coauthor( $story_id, $user_id ) {
		if ( ! self::is_enabled() || ! self::table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		$user_id  = absint( $user_id );
		if ( ! $story_id || ! $user_id ) {
			return false;
		}

		$key = $story_id . ':' . $user_id;
		if ( isset( self::$coauthor_cache[ $key ] ) ) {
			return self::$coauthor_cache[ $key ];
		}

		global $wpdb;
		$table  = self::get_table_name();
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE story_id = %d AND user_id = %d AND status = %s LIMIT 1",
				$story_id,
				$user_id,
				self::STATUS_ACCEPTED
			)
		);

		self::$coauthor_cache[ $key ] = ! empty( $exists );
		return self::$coauthor_cache[ $key ];
	}

	/**
	 * Whether user has a pending invitation to story.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_pending_coauthor( $story_id, $user_id ) {
		if ( ! self::is_enabled() || ! self::table_ready() ) {
			return false;
		}

		$story_id = absint( $story_id );
		$user_id  = absint( $user_id );
		if ( ! $story_id || ! $user_id ) {
			return false;
		}

		$key = $story_id . ':' . $user_id;
		if ( isset( self::$pending_cache[ $key ] ) ) {
			return self::$pending_cache[ $key ];
		}

		global $wpdb;
		$table  = self::get_table_name();
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE story_id = %d AND user_id = %d AND status = %s LIMIT 1",
				$story_id,
				$user_id,
				self::STATUS_PENDING
			)
		);

		self::$pending_cache[ $key ] = ! empty( $exists );
		return self::$pending_cache[ $key ];
	}

	/**
	 * Whether user is original story author.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_original_author( $story_id, $user_id ) {
		return (int) get_post_field( 'post_author', absint( $story_id ) ) === absint( $user_id );
	}

	/**
	 * Whether user can manage co-authors on story.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_manage_coauthors( $story_id, $user_id ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		return self::is_original_author( $story_id, $user_id ) || self::is_coauthor( $story_id, $user_id );
	}

	/**
	 * Cleanup co-author relations when story is deleted.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @return void
	 */
	public static function cleanup_story_coauthors( $story_id ) {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return;
		}

		$post = get_post( $story_id );
		if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
			return;
		}

		$wpdb->delete(
			self::get_table_name(),
			array( 'story_id' => absint( $story_id ) ),
			array( '%d' )
		);

		self::clear_caches();
	}

	/**
	 * Notify users when feature is disabled.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function notify_feature_disabled() {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return;
		}

		$table = self::get_table_name();
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$table} WHERE status IN ('pending', 'accepted')
			UNION
			SELECT DISTINCT invited_by FROM {$table} WHERE status IN ('pending', 'accepted')"
		);

		$message = __( 'Co-author functionality has been disabled by the site administrator. Your co-author relationships are preserved and will be restored if the feature is re-enabled.', 'fanfiction-manager' );
		foreach ( (array) $user_ids as $user_id ) {
			self::notify_user(
				absint( $user_id ),
				'TYPE_COAUTHOR_DISABLED',
				'coauthor_disabled',
				$message
			);
		}

		$story_ids = $wpdb->get_col(
			"SELECT DISTINCT story_id FROM {$table} WHERE status IN ('pending', 'accepted')"
		);
		self::reindex_stories( $story_ids );
	}

	/**
	 * Notify users when feature is enabled.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function notify_feature_enabled() {
		global $wpdb;

		if ( ! self::table_ready() ) {
			return;
		}

		$table = self::get_table_name();
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$table} WHERE status = 'accepted'
			UNION
			SELECT DISTINCT invited_by FROM {$table} WHERE status = 'accepted'"
		);

		$message = __( 'Co-author functionality has been re-enabled by the site administrator. Your co-author relationships have been restored.', 'fanfiction-manager' );
		foreach ( (array) $user_ids as $user_id ) {
			self::notify_user(
				absint( $user_id ),
				'TYPE_COAUTHOR_ENABLED',
				'coauthor_enabled',
				$message
			);
		}

		$story_ids = $wpdb->get_col(
			"SELECT DISTINCT story_id FROM {$table} WHERE status IN ('pending', 'accepted')"
		);
		self::reindex_stories( $story_ids );
	}

	/**
	 * Reindex stories when co-author display name changes.
	 *
	 * @since 1.5.3
	 * @param int     $user_id User ID.
	 * @param WP_User $old_user_data Old user data.
	 * @return void
	 */
	public static function on_coauthor_profile_update( $user_id, $old_user_data ) {
		if ( ! self::is_enabled() || ! class_exists( 'Fanfic_Search_Index' ) ) {
			return;
		}

		$user_id = absint( $user_id );
		if ( ! $user_id || ! ( $old_user_data instanceof WP_User ) ) {
			return;
		}

		$new_name = (string) get_the_author_meta( 'display_name', $user_id );
		$old_name = (string) $old_user_data->display_name;

		if ( $new_name === $old_name ) {
			return;
		}

		$story_ids = self::get_user_coauthored_stories( $user_id, self::STATUS_ACCEPTED );
		self::reindex_stories( $story_ids );
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/users/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_user_search' ),
				'permission_callback' => array( __CLASS__, 'can_search_users' ),
				'args'                => array(
					'q'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'story_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'limit'    => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST permission callback for user search.
	 *
	 * @since 1.5.3
	 * @return bool
	 */
	public static function can_search_users() {
		return is_user_logged_in() && current_user_can( 'edit_fanfiction_stories' );
	}

	/**
	 * REST callback for user search.
	 *
	 * @since 1.5.3
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_user_search( $request ) {
		global $wpdb;

		$query    = trim( (string) $request->get_param( 'q' ) );
		$story_id = absint( $request->get_param( 'story_id' ) );
		$limit    = max( 1, min( 50, absint( $request->get_param( 'limit' ) ) ) );

		if ( ! self::is_enabled() || strlen( $query ) < 2 ) {
			return new WP_REST_Response( array(), 200 );
		}

		$exclude = array( get_current_user_id() );
		if ( $story_id ) {
			$original_author = (int) get_post_field( 'post_author', $story_id );
			if ( $original_author > 0 ) {
				$exclude[] = $original_author;
			}

			if ( self::table_ready() ) {
				$existing = $wpdb->get_col(
					$wpdb->prepare(
						'SELECT user_id FROM ' . self::get_table_name() . ' WHERE story_id = %d',
						$story_id
					)
				);
				foreach ( (array) $existing as $existing_id ) {
					$exclude[] = absint( $existing_id );
				}
			}
		}

		$user_query = new WP_User_Query(
			array(
				'search'         => '*' . $query . '*',
				'search_columns' => array( 'display_name', 'user_login' ),
				'exclude'        => array_values( array_unique( array_filter( array_map( 'absint', $exclude ) ) ) ),
				'number'         => $limit,
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);

		$results = array();
		foreach ( (array) $user_query->get_results() as $user ) {
			$results[] = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
			);
		}

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * AJAX: Invite co-author.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function ajax_invite_coauthor() {
		if ( ! self::is_enabled() ) {
			Fanfic_AJAX_Security::send_error_response( 'feature_disabled', __( 'Co-author feature is currently disabled.', 'fanfiction-manager' ), 400 );
		}

		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'story_id', 'user_id' ),
			array()
		);
		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_params', $params->get_error_message(), 400 );
		}

		$story_id = absint( $params['story_id'] );
		$user_id  = absint( $params['user_id'] );
		$current  = get_current_user_id();

		if ( ! self::can_manage_coauthors( $story_id, $current ) ) {
			Fanfic_AJAX_Security::send_error_response( 'forbidden', __( 'You cannot manage co-authors for this story.', 'fanfiction-manager' ), 403 );
		}

		$result = self::invite_coauthor( $story_id, $user_id, $current );
		if ( ! empty( $result['success'] ) ) {
			Fanfic_AJAX_Security::send_success_response( $result, __( 'Co-author invitation sent.', 'fanfiction-manager' ) );
		}

		Fanfic_AJAX_Security::send_error_response( 'invite_failed', $result['message'], 400 );
	}

	/**
	 * AJAX: Respond to co-author invitation.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function ajax_respond_invitation() {
		if ( ! self::is_enabled() ) {
			Fanfic_AJAX_Security::send_error_response( 'feature_disabled', __( 'Co-author feature is currently disabled.', 'fanfiction-manager' ), 400 );
		}

		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'story_id', 'response' ),
			array()
		);
		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_params', $params->get_error_message(), 400 );
		}

		$story_id = absint( $params['story_id'] );
		$response = sanitize_text_field( $params['response'] );
		$user_id  = get_current_user_id();

		if ( 'accept' === $response ) {
			$ok = self::accept_invitation( $story_id, $user_id );
		} elseif ( 'refuse' === $response ) {
			$ok = self::refuse_invitation( $story_id, $user_id );
		} else {
			Fanfic_AJAX_Security::send_error_response( 'invalid_response', __( 'Invalid response.', 'fanfiction-manager' ), 400 );
		}

		if ( $ok ) {
			Fanfic_AJAX_Security::send_success_response( array(), __( 'Response recorded.', 'fanfiction-manager' ) );
		}

		Fanfic_AJAX_Security::send_error_response( 'response_failed', __( 'Failed to process response.', 'fanfiction-manager' ), 500 );
	}

	/**
	 * AJAX: Remove co-author.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	public static function ajax_remove_coauthor() {
		if ( ! self::is_enabled() ) {
			Fanfic_AJAX_Security::send_error_response( 'feature_disabled', __( 'Co-author feature is currently disabled.', 'fanfiction-manager' ), 400 );
		}

		$params = Fanfic_AJAX_Security::get_ajax_parameters(
			array( 'story_id', 'user_id' ),
			array()
		);
		if ( is_wp_error( $params ) ) {
			Fanfic_AJAX_Security::send_error_response( 'invalid_params', $params->get_error_message(), 400 );
		}

		$story_id = absint( $params['story_id'] );
		$user_id  = absint( $params['user_id'] );
		$current  = get_current_user_id();

		if ( ! self::can_manage_coauthors( $story_id, $current ) ) {
			Fanfic_AJAX_Security::send_error_response( 'forbidden', __( 'You cannot manage co-authors for this story.', 'fanfiction-manager' ), 403 );
		}

		$result = self::remove_coauthor( $story_id, $user_id, $current );
		if ( ! empty( $result['success'] ) ) {
			Fanfic_AJAX_Security::send_success_response( $result, __( 'Co-author removed.', 'fanfiction-manager' ) );
		}

		Fanfic_AJAX_Security::send_error_response( 'remove_failed', $result['message'], 400 );
	}

	/**
	 * Include co-authored IDs in dashboard story query.
	 *
	 * @since 1.5.3
	 * @param string   $where Existing where SQL.
	 * @param WP_Query $query Query object.
	 * @return string
	 */
	public static function filter_dashboard_query( $where, $query ) {
		if ( ! self::is_enabled() ) {
			return $where;
		}

		$coauthored_ids = $query->get( 'fanfic_include_coauthored' );
		if ( empty( $coauthored_ids ) ) {
			return $where;
		}

		$coauthored_ids = array_values( array_filter( array_map( 'absint', (array) $coauthored_ids ) ) );
		if ( empty( $coauthored_ids ) ) {
			return $where;
		}

		$author_id = absint( $query->get( 'author' ) );
		if ( ! $author_id ) {
			return $where;
		}

		global $wpdb;

		$author_clause = "{$wpdb->posts}.post_author = {$author_id}";
		$ids_clause    = implode( ',', $coauthored_ids );
		$replacement   = "({$author_clause} OR {$wpdb->posts}.ID IN ({$ids_clause}))";

		if ( false !== strpos( $where, $author_clause ) ) {
			return str_replace( $author_clause, $replacement, $where );
		}

		return $where . " AND {$replacement}";
	}

	/**
	 * Return table name.
	 *
	 * @since 1.5.3
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_coauthors';
	}

	/**
	 * Whether co-authors table exists.
	 *
	 * @since 1.5.3
	 * @return bool
	 */
	private static function table_ready() {
		global $wpdb;

		$table = self::get_table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Sanitize status value.
	 *
	 * @since 1.5.3
	 * @param string $status Raw status.
	 * @param string $default Default status.
	 * @return string
	 */
	private static function sanitize_status( $status, $default ) {
		$status = sanitize_key( $status );
		if ( in_array( $status, array( self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REFUSED ), true ) ) {
			return $status;
		}
		return $default;
	}

	/**
	 * Convert DB rows to user-like objects.
	 *
	 * @since 1.5.3
	 * @param array<int,object> $rows Rows.
	 * @return array<int,object>
	 */
	private static function normalize_user_rows( $rows ) {
		$results = array();

		foreach ( (array) $rows as $row ) {
			$results[] = (object) array(
				'ID'           => absint( $row->user_id ?? 0 ),
				'display_name' => (string) ( $row->display_name ?? '' ),
				'user_email'   => (string) ( $row->user_email ?? '' ),
				'status'       => (string) ( $row->status ?? '' ),
			);
		}

		return $results;
	}

	/**
	 * Reset static caches.
	 *
	 * @since 1.5.3
	 * @return void
	 */
	private static function clear_caches() {
		self::$coauthor_cache = array();
		self::$pending_cache  = array();
	}

	/**
	 * Reindex one story if search index class is available.
	 *
	 * @since 1.5.3
	 * @param int $story_id Story ID.
	 * @return void
	 */
	private static function reindex_story( $story_id ) {
		$story_id = absint( $story_id );
		if ( $story_id && class_exists( 'Fanfic_Search_Index' ) ) {
			Fanfic_Search_Index::update_index( $story_id );
		}
	}

	/**
	 * Reindex multiple stories.
	 *
	 * @since 1.5.3
	 * @param array<int,mixed> $story_ids Story IDs.
	 * @return void
	 */
	private static function reindex_stories( $story_ids ) {
		if ( ! class_exists( 'Fanfic_Search_Index' ) ) {
			return;
		}

		$story_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $story_ids ) ) ) );
		foreach ( $story_ids as $story_id ) {
			Fanfic_Search_Index::update_index( $story_id );
		}
	}

	/**
	 * Get display name for user.
	 *
	 * @since 1.5.3
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function get_user_display_name( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( $user && ! empty( $user->display_name ) ) {
			return $user->display_name;
		}

		return __( 'A user', 'fanfiction-manager' );
	}

	/**
	 * Create notification if notifications class is available.
	 *
	 * @since 1.5.3
	 * @param int    $user_id Recipient user ID.
	 * @param string $type_const Notification constant name.
	 * @param string $fallback_type Fallback type.
	 * @param string $message Notification message.
	 * @param array  $data Notification data.
	 * @return void
	 */
	private static function notify_user( $user_id, $type_const, $fallback_type, $message, $data = array() ) {
		if ( ! class_exists( 'Fanfic_Notifications' ) ) {
			return;
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		$const_name = 'Fanfic_Notifications::' . preg_replace( '/[^A-Z0-9_]/', '', (string) $type_const );
		$type = defined( $const_name ) ? constant( $const_name ) : $fallback_type;

		Fanfic_Notifications::create_notification( $user_id, $type, $message, $data );
	}
}
