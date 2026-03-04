<?php
/**
 * Moderation Messages CRUD utility class.
 *
 * Provides static methods for creating, reading, and updating moderation
 * message threads and their chat entries.
 *
 * @package FanfictionManager
 * @since   2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Moderation_Messages
 *
 * Static utility class for CRUD operations on moderation message threads.
 *
 * @package FanfictionManager
 * @since   2.3.0
 */
class Fanfic_Moderation_Messages {

	/**
	 * Valid target types for a moderation message.
	 *
	 * @since 2.3.0
	 * @var string[]
	 */
	private static $valid_target_types = array( 'story', 'chapter', 'user' );

	/**
	 * Valid status values for a moderation message thread.
	 *
	 * @since 2.3.0
	 * @var string[]
	 */
	private static $valid_statuses = array( 'unread', 'ignored', 'resolved', 'deleted' );

	/**
	 * Valid sender roles for thread entries.
	 *
	 * @since 2.4.0
	 * @var string[]
	 */
	private static $valid_sender_roles = array( 'author', 'moderator', 'system' );

	/**
	 * Get moderation messages table name.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	private static function get_messages_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_moderation_messages';
	}

	/**
	 * Get moderation message entries table name.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	private static function get_entries_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_moderation_message_entries';
	}

	/**
	 * Validate author and target context.
	 *
	 * @since 2.4.0
	 * @param int    $author_id   Author ID.
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @return true|WP_Error
	 */
	private static function validate_author_target_context( $author_id, $target_type, $target_id ) {
		$author_id = (int) $author_id;
		if ( $author_id <= 0 ) {
			return new WP_Error(
				'invalid_author_id',
				__( 'Author ID must be a positive integer.', 'fanfiction-manager' )
			);
		}

		if ( ! in_array( $target_type, self::$valid_target_types, true ) ) {
			return new WP_Error(
				'invalid_target_type',
				__( 'Target type must be one of: story, chapter, user.', 'fanfiction-manager' )
			);
		}

		$target_id = (int) $target_id;
		if ( $target_id <= 0 ) {
			return new WP_Error(
				'invalid_target_id',
				__( 'Target ID must be a positive integer.', 'fanfiction-manager' )
			);
		}

		if ( class_exists( 'Fanfic_Blacklist' ) && Fanfic_Blacklist::is_message_sender_blacklisted( $author_id ) ) {
			return new WP_Error(
				'author_blacklisted',
				__( 'You are unable to send messages at this time.', 'fanfiction-manager' )
			);
		}

		if ( function_exists( 'fanfic_get_restriction_context' ) ) {
			$context = fanfic_get_restriction_context( $target_type, $target_id );
			if ( empty( $context['is_restricted'] ) ) {
				return new WP_Error(
					'target_not_restricted',
					__( 'This item is no longer restricted.', 'fanfiction-manager' )
				);
			}

			if ( (int) $context['owner_id'] !== $author_id ) {
				return new WP_Error(
					'ownership_mismatch',
					__( 'Only the restricted author can message moderation about this item.', 'fanfiction-manager' )
				);
			}
		}

		return true;
	}

	/**
	 * Insert a single entry in a moderation thread.
	 *
	 * @since 2.4.0
	 * @param int    $message_id   Thread ID.
	 * @param int    $sender_id    Sender user ID.
	 * @param string $sender_role  author|moderator|system.
	 * @param string $message_text Message body.
	 * @param bool   $is_internal  Internal-only note flag.
	 * @return int|false
	 */
	private static function insert_thread_entry( $message_id, $sender_id, $sender_role, $message_text, $is_internal = false ) {
		global $wpdb;

		$message_id = (int) $message_id;
		$sender_id  = (int) $sender_id;

		if ( $message_id <= 0 || $sender_id <= 0 ) {
			return false;
		}

		if ( ! in_array( $sender_role, self::$valid_sender_roles, true ) ) {
			return false;
		}

		$message_text = sanitize_textarea_field( $message_text );
		$length       = function_exists( 'mb_strlen' ) ? mb_strlen( $message_text ) : strlen( $message_text );
		if ( $length < 1 || $length > 1000 ) {
			return false;
		}

		$entries_table = self::get_entries_table_name();
		$result        = $wpdb->insert(
			$entries_table,
			array(
				'message_id'  => $message_id,
				'sender_id'   => $sender_id,
				'sender_role' => $sender_role,
				'message'     => $message_text,
				'is_internal' => $is_internal ? 1 : 0,
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Create a new moderation message thread with an initial author message.
	 *
	 * @since 2.3.0
	 * @param int    $author_id    Author ID.
	 * @param string $target_type  Target type.
	 * @param int    $target_id    Target ID.
	 * @param string $message_text Initial message body.
	 * @return int|WP_Error
	 */
	public static function create_message( $author_id, $target_type, $target_id, $message_text ) {
		global $wpdb;

		$validation = self::validate_author_target_context( $author_id, $target_type, $target_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$message_text = sanitize_textarea_field( $message_text );
		$length       = function_exists( 'mb_strlen' ) ? mb_strlen( $message_text ) : strlen( $message_text );
		if ( $length < 1 || $length > 1000 ) {
			return new WP_Error(
				'invalid_message_length',
				__( 'Message must be between 1 and 1000 characters.', 'fanfiction-manager' )
			);
		}

		if ( self::has_active_message( $author_id, $target_type, $target_id ) ) {
			return new WP_Error(
				'already_messaged',
				__( 'An active moderation message from this author for this target already exists.', 'fanfiction-manager' )
			);
		}

		$table       = self::get_messages_table_name();
		$current_sql = current_time( 'mysql' );

		$result = $wpdb->insert(
			$table,
			array(
				'author_id'            => (int) $author_id,
				'target_type'          => $target_type,
				'target_id'            => (int) $target_id,
				'message'              => $message_text,
				'status'               => 'unread',
				'unread_for_moderator' => 1,
				'unread_for_author'    => 0,
				'last_message_at'      => $current_sql,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_failed',
				__( 'Failed to insert moderation message into the database.', 'fanfiction-manager' )
			);
		}

		$message_id = (int) $wpdb->insert_id;
		$entry_id   = self::insert_thread_entry( $message_id, (int) $author_id, 'author', $message_text, false );

		if ( false === $entry_id ) {
			$wpdb->delete( $table, array( 'id' => $message_id ), array( '%d' ) );
			return new WP_Error(
				'db_entry_insert_failed',
				__( 'Failed to insert moderation thread entry.', 'fanfiction-manager' )
			);
		}

		if ( function_exists( 'fanfic_clear_restriction_reply_message' ) ) {
			fanfic_clear_restriction_reply_message( $target_type, $target_id );
		}

		return $message_id;
	}

	/**
	 * Append an author message to an active thread or create a new thread.
	 *
	 * @since 2.4.0
	 * @param int    $author_id    Author ID.
	 * @param string $target_type  Target type.
	 * @param int    $target_id    Target ID.
	 * @param string $message_text Message body.
	 * @return array|WP_Error {
	 *     @type int  $message_id Thread ID.
	 *     @type bool $created    True if a new thread was created.
	 * }
	 */
	public static function send_author_message( $author_id, $target_type, $target_id, $message_text ) {
		global $wpdb;

		$validation = self::validate_author_target_context( $author_id, $target_type, $target_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$message_text = sanitize_textarea_field( $message_text );
		$length       = function_exists( 'mb_strlen' ) ? mb_strlen( $message_text ) : strlen( $message_text );
		if ( $length < 1 || $length > 1000 ) {
			return new WP_Error(
				'invalid_message_length',
				__( 'Message must be between 1 and 1000 characters.', 'fanfiction-manager' )
			);
		}

		$active = self::get_active_message( $author_id, $target_type, $target_id );
		if ( ! empty( $active['id'] ) ) {
			$message_id = (int) $active['id'];
			$entry_id   = self::insert_thread_entry( $message_id, (int) $author_id, 'author', $message_text, false );
			if ( false === $entry_id ) {
				return new WP_Error(
					'db_entry_insert_failed',
					__( 'Failed to send message. Please try again.', 'fanfiction-manager' )
				);
			}

			$table       = self::get_messages_table_name();
			$current_sql = current_time( 'mysql' );
			$wpdb->update(
				$table,
				array(
					'message'              => $message_text,
					'updated_at'           => $current_sql,
					'last_message_at'      => $current_sql,
					'unread_for_moderator' => 1,
					'unread_for_author'    => 0,
				),
				array( 'id' => $message_id ),
				array( '%s', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);

			if ( function_exists( 'fanfic_clear_restriction_reply_message' ) ) {
				fanfic_clear_restriction_reply_message( $target_type, $target_id );
			}

			return array(
				'message_id' => $message_id,
				'created'    => false,
			);
		}

		$new_thread_id = self::create_message( $author_id, $target_type, $target_id, $message_text );
		if ( is_wp_error( $new_thread_id ) ) {
			return $new_thread_id;
		}

		return array(
			'message_id' => (int) $new_thread_id,
			'created'    => true,
		);
	}

	/**
	 * Append a moderator-visible message to a thread.
	 *
	 * @since 2.4.0
	 * @param int    $message_id   Thread ID.
	 * @param int    $moderator_id Moderator ID.
	 * @param string $message_text Message body.
	 * @return bool|WP_Error
	 */
	public static function send_moderator_message( $message_id, $moderator_id, $message_text ) {
		global $wpdb;

		$message_id   = (int) $message_id;
		$moderator_id = (int) $moderator_id;
		if ( $message_id <= 0 || $moderator_id <= 0 ) {
			return new WP_Error( 'invalid_message', __( 'Invalid message thread.', 'fanfiction-manager' ) );
		}

		$thread = self::get_message( $message_id );
		if ( ! $thread ) {
			return new WP_Error( 'thread_not_found', __( 'Message thread not found.', 'fanfiction-manager' ) );
		}

		if ( 'unread' !== $thread['status'] ) {
			return new WP_Error( 'thread_closed', __( 'This message thread is closed.', 'fanfiction-manager' ) );
		}

		$message_text = sanitize_textarea_field( $message_text );
		$length       = function_exists( 'mb_strlen' ) ? mb_strlen( $message_text ) : strlen( $message_text );
		if ( $length < 1 || $length > 1000 ) {
			return new WP_Error(
				'invalid_message_length',
				__( 'Message must be between 1 and 1000 characters.', 'fanfiction-manager' )
			);
		}

		$entry_id = self::insert_thread_entry( $message_id, $moderator_id, 'moderator', $message_text, false );
		if ( false === $entry_id ) {
			return new WP_Error(
				'db_entry_insert_failed',
				__( 'Failed to send moderator reply.', 'fanfiction-manager' )
			);
		}

		$table       = self::get_messages_table_name();
		$current_sql = current_time( 'mysql' );
		$wpdb->update(
			$table,
			array(
				'moderator_id'         => $moderator_id,
				'author_reply'         => $message_text,
				'updated_at'           => $current_sql,
				'last_message_at'      => $current_sql,
				'unread_for_moderator' => 0,
				'unread_for_author'    => 1,
			),
			array( 'id' => $message_id ),
			array( '%d', '%s', '%s', '%s', '%d', '%d' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Checks whether an active unread thread already exists from a given author
	 * for a given target.
	 *
	 * @since 2.3.0
	 * @param int    $author_id   Author ID.
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @return bool
	 */
	public static function has_active_message( $author_id, $target_type, $target_id ) {
		global $wpdb;

		$table = self::get_messages_table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE author_id = %d
				  AND target_type = %s
				  AND target_id = %d
				  AND status = 'unread'",
				(int) $author_id,
				$target_type,
				(int) $target_id
			)
		);

		return ( (int) $count ) > 0;
	}

	/**
	 * Check if the active thread has unread moderator replies for author.
	 *
	 * @since 2.4.0
	 * @param int    $author_id   Author ID.
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @return bool
	 */
	public static function active_thread_has_unread_for_author( $author_id, $target_type, $target_id ) {
		$active = self::get_active_message( $author_id, $target_type, $target_id );
		return ! empty( $active['unread_for_author'] );
	}

	/**
	 * Retrieve the current unread thread for an author/target pair.
	 *
	 * @since 2.3.0
	 * @param int    $author_id   Author ID.
	 * @param string $target_type Target type.
	 * @param int    $target_id   Target ID.
	 * @return array|null
	 */
	public static function get_active_message( $author_id, $target_type, $target_id ) {
		global $wpdb;

		$table = self::get_messages_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE author_id = %d
				  AND target_type = %s
				  AND target_id = %d
				  AND status = 'unread'
				ORDER BY COALESCE(last_message_at, created_at) DESC, id DESC
				LIMIT 1",
				(int) $author_id,
				$target_type,
				(int) $target_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Retrieve a single moderation thread by ID.
	 *
	 * @since 2.3.0
	 * @param int $id Thread ID.
	 * @return array|null
	 */
	public static function get_message( $id ) {
		global $wpdb;

		$table = self::get_messages_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				(int) $id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Get thread entries (chat messages).
	 *
	 * @since 2.4.0
	 * @param int  $message_id       Thread ID.
	 * @param bool $include_internal Whether to include internal moderator notes.
	 * @return array
	 */
	public static function get_message_entries( $message_id, $include_internal = false ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id <= 0 ) {
			return array();
		}

		$entries_table = self::get_entries_table_name();
		$where_sql     = 'WHERE message_id = %d';
		$prepare_args  = array( $message_id );

		if ( ! $include_internal ) {
			$where_sql .= ' AND is_internal = %d';
			$prepare_args[] = 0;
		}

		$sql = "SELECT * FROM {$entries_table} {$where_sql} ORDER BY id ASC";
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $prepare_args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Mark a thread as read for moderator.
	 *
	 * @since 2.4.0
	 * @param int $message_id Thread ID.
	 * @return bool
	 */
	public static function mark_thread_read_for_moderator( $message_id ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id <= 0 ) {
			return false;
		}

		$table  = self::get_messages_table_name();
		$result = $wpdb->update(
			$table,
			array( 'unread_for_moderator' => 0 ),
			array( 'id' => $message_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark a thread as read for author.
	 *
	 * @since 2.4.0
	 * @param int $message_id Thread ID.
	 * @return bool
	 */
	public static function mark_thread_read_for_author( $message_id ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id <= 0 ) {
			return false;
		}

		$table  = self::get_messages_table_name();
		$result = $wpdb->update(
			$table,
			array( 'unread_for_author' => 0 ),
			array( 'id' => $message_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Count open threads that need moderator attention.
	 *
	 * @since 2.4.0
	 * @return int
	 */
	public static function count_needing_moderator() {
		global $wpdb;

		$table = self::get_messages_table_name();
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'unread' AND unread_for_moderator = 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return (int) $count;
	}

	/**
	 * Retrieve moderation threads based on filter arguments.
	 *
	 * @since 2.3.0
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_messages( $args = array() ) {
		global $wpdb;

		$table = self::get_messages_table_name();

		$defaults = array(
			'status'               => '',
			'target_type'          => '',
			'author_id'            => 0,
			'unread_for_moderator' => null,
			'unread_for_author'    => null,
			'limit'                => 25,
			'offset'               => 0,
			'orderby'              => 'last_message_at',
			'order'                => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$prepare_args  = array();

		if ( ! empty( $args['status'] ) ) {
			if ( is_array( $args['status'] ) ) {
				$placeholders    = implode( ', ', array_fill( 0, count( $args['status'] ), '%s' ) );
				$where_clauses[] = "status IN ({$placeholders})";
				foreach ( $args['status'] as $s ) {
					$prepare_args[] = $s;
				}
			} else {
				$where_clauses[] = 'status = %s';
				$prepare_args[]  = $args['status'];
			}
		}

		if ( ! empty( $args['target_type'] ) ) {
			$where_clauses[] = 'target_type = %s';
			$prepare_args[]  = $args['target_type'];
		}

		if ( ! empty( $args['author_id'] ) ) {
			$where_clauses[] = 'author_id = %d';
			$prepare_args[]  = (int) $args['author_id'];
		}

		if ( null !== $args['unread_for_moderator'] ) {
			$where_clauses[] = 'unread_for_moderator = %d';
			$prepare_args[]  = (int) ! empty( $args['unread_for_moderator'] );
		}

		if ( null !== $args['unread_for_author'] ) {
			$where_clauses[] = 'unread_for_author = %d';
			$prepare_args[]  = (int) ! empty( $args['unread_for_author'] );
		}

		$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$allowed_orderby = array( 'id', 'author_id', 'target_type', 'target_id', 'status', 'created_at', 'updated_at', 'last_message_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_message_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$limit  = max( 1, (int) $args['limit'] );
		$offset = max( 0, (int) $args['offset'] );

		$prepare_args[] = $limit;
		$prepare_args[] = $offset;

		if ( 'last_message_at' === $orderby ) {
			$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY COALESCE(last_message_at, created_at) {$order}, id {$order} LIMIT %d OFFSET %d";
		} else {
			$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $prepare_args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Count moderation threads matching the given filter arguments.
	 *
	 * @since 2.3.0
	 * @param array $args Query args.
	 * @return int
	 */
	public static function count_messages( $args = array() ) {
		global $wpdb;

		$table = self::get_messages_table_name();

		$defaults = array(
			'status'               => '',
			'target_type'          => '',
			'author_id'            => 0,
			'unread_for_moderator' => null,
			'unread_for_author'    => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$prepare_args  = array();

		if ( ! empty( $args['status'] ) ) {
			if ( is_array( $args['status'] ) ) {
				$placeholders    = implode( ', ', array_fill( 0, count( $args['status'] ), '%s' ) );
				$where_clauses[] = "status IN ({$placeholders})";
				foreach ( $args['status'] as $s ) {
					$prepare_args[] = $s;
				}
			} else {
				$where_clauses[] = 'status = %s';
				$prepare_args[]  = $args['status'];
			}
		}

		if ( ! empty( $args['target_type'] ) ) {
			$where_clauses[] = 'target_type = %s';
			$prepare_args[]  = $args['target_type'];
		}

		if ( ! empty( $args['author_id'] ) ) {
			$where_clauses[] = 'author_id = %d';
			$prepare_args[]  = (int) $args['author_id'];
		}

		if ( null !== $args['unread_for_moderator'] ) {
			$where_clauses[] = 'unread_for_moderator = %d';
			$prepare_args[]  = (int) ! empty( $args['unread_for_moderator'] );
		}

		if ( null !== $args['unread_for_author'] ) {
			$where_clauses[] = 'unread_for_author = %d';
			$prepare_args[]  = (int) ! empty( $args['unread_for_author'] );
		}

		$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$sql       = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		if ( $prepare_args ) {
			$count = $wpdb->get_var(
				$wpdb->prepare( $sql, $prepare_args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		} else {
			$count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $count;
	}

	/**
	 * Returns a summary of thread counts grouped by status.
	 *
	 * @since 2.3.0
	 * @return array
	 */
	public static function get_status_counts() {
		global $wpdb;

		$table = self::get_messages_table_name();

		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$counts = array(
			'unread'   => 0,
			'ignored'  => 0,
			'resolved' => 0,
			'all'      => 0,
		);

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$status = $row['status'];
				$n      = (int) $row['cnt'];

				if ( array_key_exists( $status, $counts ) ) {
					$counts[ $status ] = $n;
				}

				$counts['all'] += $n;
			}
		}

		return $counts;
	}

	/**
	 * Update thread status and optional metadata.
	 *
	 * @since 2.3.0
	 * @param int    $message_id   Thread ID.
	 * @param string $new_status   unread|ignored|resolved|deleted.
	 * @param int    $moderator_id Moderator ID.
	 * @param string $note         Internal note.
	 * @param string $author_reply Optional author-visible reply.
	 * @return bool
	 */
	public static function update_status( $message_id, $new_status, $moderator_id = 0, $note = '', $author_reply = '' ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id <= 0 ) {
			return false;
		}

		if ( ! in_array( $new_status, self::$valid_statuses, true ) ) {
			return false;
		}

		$table       = self::get_messages_table_name();
		$current_sql = current_time( 'mysql' );

		$data   = array(
			'status'               => $new_status,
			'updated_at'           => $current_sql,
			'unread_for_moderator' => 'unread' === $new_status ? 1 : 0,
		);
		$format = array( '%s', '%s', '%d' );

		$moderator_id = (int) $moderator_id;
		if ( $moderator_id > 0 ) {
			$data['moderator_id'] = $moderator_id;
			$format[]             = '%d';
		}

		$note = sanitize_textarea_field( $note );
		if ( '' !== $note ) {
			$data['moderator_note'] = $note;
			$format[]               = '%s';
		}

		$author_reply = sanitize_textarea_field( $author_reply );
		if ( '' !== $author_reply ) {
			$data['author_reply']      = $author_reply;
			$data['unread_for_author'] = 1;
			$data['last_message_at']   = $current_sql;
			$format[]                  = '%s';
			$format[]                  = '%d';
			$format[]                  = '%s';

			if ( $moderator_id > 0 ) {
				self::insert_thread_entry( $message_id, $moderator_id, 'moderator', $author_reply, false );
			}
		} else {
			$data['unread_for_author'] = 0;
			$format[]                  = '%d';
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $message_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update thread message preview text and latest public author entry text.
	 *
	 * @since 2.3.0
	 * @param int    $message_id   Message ID.
	 * @param string $message_text New preview text.
	 * @return bool
	 */
	public static function update_message_text( $message_id, $message_text ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id <= 0 ) {
			return false;
		}

		$message_text = sanitize_textarea_field( $message_text );
		$length       = function_exists( 'mb_strlen' ) ? mb_strlen( $message_text ) : strlen( $message_text );
		if ( $length < 1 || $length > 1000 ) {
			return false;
		}

		$table       = self::get_messages_table_name();
		$current_sql = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			array(
				'message'              => $message_text,
				'updated_at'           => $current_sql,
				'last_message_at'      => $current_sql,
				'unread_for_moderator' => 1,
				'unread_for_author'    => 0,
			),
			array( 'id' => $message_id ),
			array( '%s', '%s', '%s', '%d', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		$entries_table   = self::get_entries_table_name();
		$latest_entry_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$entries_table} WHERE message_id = %d AND sender_role = %s AND is_internal = %d ORDER BY id DESC LIMIT 1",
				$message_id,
				'author',
				0
			)
		);

		if ( $latest_entry_id ) {
			$wpdb->update(
				$entries_table,
				array( 'message' => $message_text ),
				array( 'id' => (int) $latest_entry_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return true;
	}

	/**
	 * Delete old resolved and deleted threads and their entries.
	 *
	 * @since 2.3.0
	 * @param int $days_old Minimum age in days.
	 * @return int Number of deleted thread rows.
	 */
	public static function cleanup_old_messages( $days_old = 90 ) {
		global $wpdb;

		$days_old      = max( 1, (int) $days_old );
		$table         = self::get_messages_table_name();
		$entries_table = self::get_entries_table_name();

		$thread_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE status IN ('resolved', 'deleted')
				  AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days_old
			)
		);

		if ( empty( $thread_ids ) ) {
			return 0;
		}

		$thread_ids = array_map( 'absint', $thread_ids );
		$thread_ids = array_filter( $thread_ids );
		if ( empty( $thread_ids ) ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $thread_ids ), '%d' ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$entries_table} WHERE message_id IN ({$placeholders})",
				$thread_ids
			)
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders})",
				$thread_ids
			)
		);

		return ( false === $result ) ? 0 : (int) $result;
	}
}
