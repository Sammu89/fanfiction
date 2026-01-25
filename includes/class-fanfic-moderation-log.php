<?php
/**
 * Moderation Log Class
 *
 * Handles logging of all moderation actions (bans, unbans, blocks, unblocks).
 *
 * @package Fanfiction_Manager
 * @since 1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Moderation_Log
 *
 * @since 1.2.0
 */
class Fanfic_Moderation_Log {

	/**
	 * Initialize moderation log hooks
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init() {
		// User ban/unban hooks
		add_action( 'fanfic_user_banned', array( __CLASS__, 'log_user_banned' ), 10, 2 );
		add_action( 'fanfic_user_unbanned', array( __CLASS__, 'log_user_unbanned' ), 10, 2 );

		// Story block/unblock hooks
		add_action( 'fanfic_story_blocked', array( __CLASS__, 'log_story_blocked' ), 10, 4 );
		add_action( 'fanfic_story_unblocked', array( __CLASS__, 'log_story_unblocked' ), 10, 2 );
	}

	/**
	 * Check if moderation log table exists
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private static function table_ready() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_moderation_log';
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Insert a log entry
	 *
	 * @since 1.2.0
	 * @param int    $actor_id    User ID who performed the action.
	 * @param string $action      Action type (ban, unban, block, unblock).
	 * @param string $target_type Target type (user, story).
	 * @param int    $target_id   Target ID.
	 * @param string $reason      Optional reason.
	 * @return bool|int Log ID on success, false on failure
	 */
	public static function insert( $actor_id, $action, $target_type, $target_id, $reason = '' ) {
		if ( ! self::table_ready() ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_moderation_log';

		$result = $wpdb->insert(
			$table,
			array(
				'actor_id'    => (int) $actor_id,
				'action'      => sanitize_key( $action ),
				'target_type' => sanitize_key( $target_type ),
				'target_id'   => (int) $target_id,
				'reason'      => sanitize_text_field( $reason ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get log entries
	 *
	 * @since 1.2.0
	 * @param array $args Query arguments.
	 * @return array Array of log entries
	 */
	public static function get_logs( $args = array() ) {
		if ( ! self::table_ready() ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_moderation_log';

		$defaults = array(
			'limit'       => 50,
			'offset'      => 0,
			'actor_id'    => null,
			'action'      => null,
			'target_type' => null,
			'target_id'   => null,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where_clauses = array();
		$prepare_args  = array();

		if ( $args['actor_id'] ) {
			$where_clauses[] = 'actor_id = %d';
			$prepare_args[]  = $args['actor_id'];
		}

		if ( $args['action'] ) {
			$where_clauses[] = 'action = %s';
			$prepare_args[]  = $args['action'];
		}

		if ( $args['target_type'] ) {
			$where_clauses[] = 'target_type = %s';
			$prepare_args[]  = $args['target_type'];
		}

		if ( $args['target_id'] ) {
			$where_clauses[] = 'target_id = %d';
			$prepare_args[]  = $args['target_id'];
		}

		$where = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Build ORDER BY clause
		$allowed_orderby = array( 'id', 'created_at', 'action', 'actor_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Build query
		$prepare_args[] = (int) $args['limit'];
		$prepare_args[] = (int) $args['offset'];

		if ( ! empty( $prepare_args ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$prepare_args
			);
		} else {
			$query = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT 50 OFFSET 0";
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get log entry by ID
	 *
	 * @since 1.2.0
	 * @param int $log_id Log entry ID.
	 * @return array|null Log entry or null
	 */
	public static function get_by_id( $log_id ) {
		if ( ! self::table_ready() ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_moderation_log';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ),
			ARRAY_A
		);
	}

	/**
	 * Count total log entries
	 *
	 * @since 1.2.0
	 * @param array $args Query arguments (same as get_logs).
	 * @return int Total count
	 */
	public static function count( $args = array() ) {
		if ( ! self::table_ready() ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_moderation_log';

		// Build WHERE clause (same logic as get_logs)
		$where_clauses = array();
		$prepare_args  = array();

		if ( isset( $args['actor_id'] ) && $args['actor_id'] ) {
			$where_clauses[] = 'actor_id = %d';
			$prepare_args[]  = $args['actor_id'];
		}

		if ( isset( $args['action'] ) && $args['action'] ) {
			$where_clauses[] = 'action = %s';
			$prepare_args[]  = $args['action'];
		}

		if ( isset( $args['target_type'] ) && $args['target_type'] ) {
			$where_clauses[] = 'target_type = %s';
			$prepare_args[]  = $args['target_type'];
		}

		if ( isset( $args['target_id'] ) && $args['target_id'] ) {
			$where_clauses[] = 'target_id = %d';
			$prepare_args[]  = $args['target_id'];
		}

		$where = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		if ( ! empty( $prepare_args ) ) {
			$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $prepare_args );
			$count = (int) $wpdb->get_var( $query );
		} else {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return $count;
	}

	/**
	 * Delete old log entries
	 *
	 * @since 1.2.0
	 * @param int $days_old Delete entries older than this many days.
	 * @return int Number of entries deleted
	 */
	public static function cleanup_old_entries( $days_old = 90 ) {
		if ( ! self::table_ready() ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_moderation_log';

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		return $deleted ? (int) $deleted : 0;
	}

	// ========================================================================
	// Hook Handlers
	// ========================================================================

	/**
	 * Hook: Log user banned
	 *
	 * @since 1.2.0
	 * @param int $user_id User ID.
	 * @param int $moderator_id Moderator ID.
	 * @return void
	 */
	public static function log_user_banned( $user_id, $moderator_id ) {
		if ( ! $moderator_id ) {
			$moderator_id = get_current_user_id();
		}

		$reason = get_user_meta( $user_id, 'fanfic_suspension_reason', true );
		if ( ! $reason ) {
			$reason = 'No reason provided';
		}

		self::insert( $moderator_id, 'ban', 'user', $user_id, $reason );
	}

	/**
	 * Hook: Log user unbanned
	 *
	 * @since 1.2.0
	 * @param int $user_id User ID.
	 * @param int $moderator_id Moderator ID.
	 * @return void
	 */
	public static function log_user_unbanned( $user_id, $moderator_id ) {
		if ( ! $moderator_id ) {
			$moderator_id = get_current_user_id();
		}

		self::insert( $moderator_id, 'unban', 'user', $user_id, 'User unbanned' );
	}

	/**
	 * Hook: Log story blocked
	 *
	 * @since 1.2.0
	 * @param int    $story_id Story ID.
	 * @param int    $actor_id Actor ID.
	 * @param string $block_type Block type (manual, ban, rule).
	 * @param string $reason Reason.
	 * @return void
	 */
	public static function log_story_blocked( $story_id, $actor_id, $block_type, $reason ) {
		if ( ! $actor_id ) {
			$actor_id = get_current_user_id();
		}

		$action = 'block_' . $block_type;
		self::insert( $actor_id, $action, 'story', $story_id, $reason );
	}

	/**
	 * Hook: Log story unblocked
	 *
	 * @since 1.2.0
	 * @param int $story_id Story ID.
	 * @param int $actor_id Actor ID.
	 * @return void
	 */
	public static function log_story_unblocked( $story_id, $actor_id ) {
		if ( ! $actor_id ) {
			$actor_id = get_current_user_id();
		}

		self::insert( $actor_id, 'unblock', 'story', $story_id, 'Story unblocked' );
	}

	/**
	 * Get formatted log entry for display
	 *
	 * @since 1.2.0
	 * @param array $log_entry Log entry from database.
	 * @return array Formatted entry with human-readable data
	 */
	public static function format_log_entry( $log_entry ) {
		if ( empty( $log_entry ) ) {
			return array();
		}

		// Get actor name
		$actor = get_userdata( $log_entry['actor_id'] );
		$actor_name = $actor ? $actor->display_name : __( 'Unknown', 'fanfiction-manager' );

		// Get target name
		$target_name = '';
		if ( $log_entry['target_type'] === 'user' ) {
			$target_user = get_userdata( $log_entry['target_id'] );
			$target_name = $target_user ? $target_user->display_name : __( 'Unknown User', 'fanfiction-manager' );
		} elseif ( $log_entry['target_type'] === 'story' ) {
			$story = get_post( $log_entry['target_id'] );
			$target_name = $story ? $story->post_title : __( 'Unknown Story', 'fanfiction-manager' );
		}

		// Format action
		$action_text = self::get_action_label( $log_entry['action'] );

		return array(
			'id'          => $log_entry['id'],
			'actor_name'  => $actor_name,
			'actor_id'    => $log_entry['actor_id'],
			'action'      => $log_entry['action'],
			'action_text' => $action_text,
			'target_type' => $log_entry['target_type'],
			'target_name' => $target_name,
			'target_id'   => $log_entry['target_id'],
			'reason'      => $log_entry['reason'],
			'created_at'  => $log_entry['created_at'],
			'timestamp'   => strtotime( $log_entry['created_at'] ),
		);
	}

	/**
	 * Get human-readable label for action
	 *
	 * @since 1.2.0
	 * @param string $action Action type.
	 * @return string Human-readable label
	 */
	private static function get_action_label( $action ) {
		$labels = array(
			'ban'          => __( 'Banned User', 'fanfiction-manager' ),
			'unban'        => __( 'Unbanned User', 'fanfiction-manager' ),
			'block_manual' => __( 'Blocked Story (Manual)', 'fanfiction-manager' ),
			'block_ban'    => __( 'Blocked Story (User Ban)', 'fanfiction-manager' ),
			'block_rule'   => __( 'Blocked Story (Rule Change)', 'fanfiction-manager' ),
			'unblock'      => __( 'Unblocked Story', 'fanfiction-manager' ),
		);

		return isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( str_replace( '_', ' ', $action ) );
	}
}
