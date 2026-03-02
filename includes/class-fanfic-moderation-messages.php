<?php
/**
 * Moderation Messages CRUD utility class.
 *
 * Provides static methods for creating, reading, updating, and deleting
 * records in the wp_fanfic_moderation_messages table.
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
 * Static utility class for CRUD operations on the fanfic_moderation_messages table.
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
	 * Valid status values for a moderation message.
	 *
	 * @since 2.3.0
	 * @var string[]
	 */
	private static $valid_statuses = array( 'unread', 'ignored', 'resolved', 'deleted' );

	/**
	 * Creates a new moderation message.
	 *
	 * Validates all input, checks for an existing active message from the same
	 * author against the same target, and inserts a new row with status 'unread'.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $author_id    ID of the user submitting the message.
	 * @param string $target_type  One of 'story', 'chapter', or 'user'.
	 * @param int    $target_id    ID of the target entity.
	 * @param string $message_text The message body (1–1000 chars after sanitization).
	 *
	 * @return int|WP_Error New message ID on success, WP_Error on failure.
	 */
	public static function create_message( $author_id, $target_type, $target_id, $message_text ) {
		global $wpdb;

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

		$message_text = sanitize_textarea_field( $message_text );
		$length       = mb_strlen( $message_text );
		if ( $length < 1 || $length > 1000 ) {
			return new WP_Error(
				'invalid_message_length',
				__( 'Message must be between 1 and 1000 characters.', 'fanfiction-manager' )
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

		if ( self::has_active_message( $author_id, $target_type, $target_id ) ) {
			return new WP_Error(
				'already_messaged',
				__( 'An active moderation message from this author for this target already exists.', 'fanfiction-manager' )
			);
		}

		$table  = $wpdb->prefix . 'fanfic_moderation_messages';
		$result = $wpdb->insert(
			$table,
			array(
				'author_id'   => $author_id,
				'target_type' => $target_type,
				'target_id'   => $target_id,
				'message'     => $message_text,
				'status'      => 'unread',
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_failed',
				__( 'Failed to insert moderation message into the database.', 'fanfiction-manager' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Checks whether an active (unread or ignored) message already exists
	 * from a given author for a given target.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $author_id   ID of the author.
	 * @param string $target_type One of 'story', 'chapter', or 'user'.
	 * @param int    $target_id   ID of the target entity.
	 *
	 * @return bool True if an active message exists, false otherwise.
	 */
	public static function has_active_message( $author_id, $target_type, $target_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_moderation_messages';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE author_id = %d
				  AND target_type = %s
				  AND target_id = %d
				  AND status IN ('unread', 'ignored')",
				(int) $author_id,
				$target_type,
				(int) $target_id
			)
		);

		return ( (int) $count ) > 0;
	}

	/**
	 * Retrieves a single moderation message by its ID.
	 *
	 * @since 2.3.0
	 *
	 * @param int $id The message ID.
	 *
	 * @return array|null Associative array of the row, or null if not found.
	 */
	public static function get_message( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_moderation_messages';

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
	 * Retrieves a list of moderation messages based on filter arguments.
	 *
	 * @since 2.3.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string|string[] $status      Filter by status value(s).
	 *     @type string          $target_type Filter by target type.
	 *     @type int             $author_id   Filter by author ID.
	 *     @type int             $limit       Number of rows to return. Default 25.
	 *     @type int             $offset      Row offset for pagination. Default 0.
	 *     @type string          $orderby     Column to order by. Default 'created_at'.
	 *     @type string          $order       Sort direction, 'ASC' or 'DESC'. Default 'DESC'.
	 * }
	 *
	 * @return array Array of associative arrays, one per row.
	 */
	public static function get_messages( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_moderation_messages';

		$defaults = array(
			'status'      => '',
			'target_type' => '',
			'author_id'   => 0,
			'limit'       => 25,
			'offset'      => 0,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$prepare_args  = array();

		// Status filter — supports single string or array.
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

		$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Sanitize orderby to an allowlist.
		$allowed_orderby = array( 'id', 'author_id', 'target_type', 'target_id', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$limit  = max( 1, (int) $args['limit'] );
		$offset = max( 0, (int) $args['offset'] );

		$prepare_args[] = $limit;
		$prepare_args[] = $offset;

		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $prepare_args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Counts moderation messages matching the given filter arguments.
	 *
	 * Accepts the same filter args as get_messages() but ignores
	 * limit, offset, orderby, and order.
	 *
	 * @since 2.3.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string|string[] $status      Filter by status value(s).
	 *     @type string          $target_type Filter by target type.
	 *     @type int             $author_id   Filter by author ID.
	 * }
	 *
	 * @return int Number of matching messages.
	 */
	public static function count_messages( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_moderation_messages';

		$defaults = array(
			'status'      => '',
			'target_type' => '',
			'author_id'   => 0,
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

		$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

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
	 * Returns a summary of message counts grouped by status.
	 *
	 * @since 2.3.0
	 *
	 * @return array {
	 *     Associative array of status counts.
	 *
	 *     @type int $unread   Number of unread messages.
	 *     @type int $ignored  Number of ignored messages.
	 *     @type int $resolved Number of resolved messages.
	 *     @type int $all      Total count across all statuses.
	 * }
	 */
	public static function get_status_counts() {
		global $wpdb;

		$table = $wpdb->prefix . 'fanfic_moderation_messages';

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
	 * Updates the status of a moderation message.
	 *
	 * Optionally records which moderator made the change and any accompanying note.
	 * Always sets updated_at to the current MySQL time.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $message_id  ID of the message to update.
	 * @param string $new_status  New status: 'unread', 'ignored', 'resolved', or 'deleted'.
	 * @param int    $moderator_id Optional. ID of the moderator performing the action. Default 0.
	 * @param string $note         Optional. A moderator note. Default empty string.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function update_status( $message_id, $new_status, $moderator_id = 0, $note = '' ) {
		global $wpdb;

		$message_id = (int) $message_id;
		if ( $message_id <= 0 ) {
			return false;
		}

		if ( ! in_array( $new_status, self::$valid_statuses, true ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'fanfic_moderation_messages';

		$data   = array(
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s' );

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
	 * Deletes old resolved and deleted messages from the database.
	 *
	 * Removes rows where status is 'resolved' or 'deleted' and created_at
	 * is older than the specified number of days.
	 *
	 * @since 2.3.0
	 *
	 * @param int $days_old Minimum age in days. Default 90.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_messages( $days_old = 90 ) {
		global $wpdb;

		$days_old = max( 1, (int) $days_old );
		$table    = $wpdb->prefix . 'fanfic_moderation_messages';

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE status IN ('resolved', 'deleted')
				  AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days_old
			)
		);

		return ( false === $result ) ? 0 : (int) $result;
	}
}
