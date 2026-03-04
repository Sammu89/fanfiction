<?php
/**
 * Moderation Blacklist CRUD utility class.
 *
 * Provides static methods for creating, reading, and deleting
 * entries in the wp_fanfic_blacklist table.
 *
 * @package FanfictionManager
 * @since   2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Blacklist
 *
 * Static utility class for blacklist operations.
 *
 * @package FanfictionManager
 * @since   2.4.0
 */
class Fanfic_Blacklist {

	/**
	 * Valid blacklist types.
	 *
	 * @since 2.4.0
	 * @var string[]
	 */
	private static $valid_types = array( 'report', 'message' );

	/**
	 * Table readiness cache.
	 *
	 * @since 2.4.0
	 * @var bool|null
	 */
	private static $table_ready = null;

	/**
	 * In-request blacklist lookup cache.
	 *
	 * @since 2.4.0
	 * @var array<string,bool>
	 */
	private static $lookup_cache = array();

	/**
	 * Get full blacklist table name.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fanfic_blacklist';
	}

	/**
	 * Ensure blacklist table is available.
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	private static function table_ready() {
		if ( null !== self::$table_ready ) {
			return self::$table_ready;
		}

		global $wpdb;
		$table_name = self::get_table_name();
		$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( $exists !== $table_name && class_exists( 'Fanfic_Database_Setup' ) && method_exists( 'Fanfic_Database_Setup', 'ensure_blacklist_table' ) ) {
			Fanfic_Database_Setup::ensure_blacklist_table();
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		}

		self::$table_ready = ( $exists === $table_name );
		return self::$table_ready;
	}

	/**
	 * Validate blacklist type.
	 *
	 * @since 2.4.0
	 * @param string $type Blacklist type.
	 * @return bool
	 */
	private static function is_valid_type( $type ) {
		return in_array( sanitize_key( (string) $type ), self::$valid_types, true );
	}

	/**
	 * Check whether a registered reporter is blacklisted.
	 *
	 * @since 2.4.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_reporter_blacklisted( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		return self::is_blacklisted( 'report', $user_id, '' );
	}

	/**
	 * Check whether an anonymous reporter IP is blacklisted.
	 *
	 * @since 2.4.0
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_reporter_blacklisted_by_ip( $ip ) {
		$ip = trim( sanitize_text_field( (string) $ip ) );
		if ( '' === $ip ) {
			return false;
		}

		return self::is_blacklisted( 'report', 0, $ip );
	}

	/**
	 * Check whether an author is blacklisted from moderation messages.
	 *
	 * @since 2.4.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_message_sender_blacklisted( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		return self::is_blacklisted( 'message', $user_id, '' );
	}

	/**
	 * Generic blacklist lookup.
	 *
	 * @since 2.4.0
	 * @param string $type Blacklist type.
	 * @param int    $user_id User ID.
	 * @param string $ip IP address.
	 * @return bool
	 */
	private static function is_blacklisted( $type, $user_id = 0, $ip = '' ) {
		global $wpdb;

		$type    = sanitize_key( (string) $type );
		$user_id = absint( $user_id );
		$ip      = trim( sanitize_text_field( (string) $ip ) );

		if ( ! self::is_valid_type( $type ) || ! self::table_ready() ) {
			return false;
		}

		$cache_key = $type . '|' . $user_id . '|' . $ip;
		if ( isset( self::$lookup_cache[ $cache_key ] ) ) {
			return self::$lookup_cache[ $cache_key ];
		}

		$where_parts = array( 'blacklist_type = %s' );
		$args        = array( $type );

		if ( $user_id > 0 ) {
			$where_parts[] = 'user_id = %d';
			$args[]        = $user_id;
		} elseif ( '' !== $ip ) {
			$where_parts[] = 'ip_address = %s';
			$args[]        = $ip;
		} else {
			return false;
		}

		$table = self::get_table_name();
		$sql   = "SELECT id FROM {$table} WHERE " . implode( ' AND ', $where_parts ) . ' LIMIT 1';
		$found = $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		$is_blacklisted = ! empty( $found );
		self::$lookup_cache[ $cache_key ] = $is_blacklisted;

		return $is_blacklisted;
	}

	/**
	 * Add a blacklist entry.
	 *
	 * @since 2.4.0
	 * @param string $type Blacklist type: report|message.
	 * @param int    $user_id User ID.
	 * @param string $ip IP address.
	 * @param int    $moderator_id Moderator ID.
	 * @param string $reason Optional reason.
	 * @return int|WP_Error Inserted row ID or WP_Error.
	 */
	public static function add( $type, $user_id = 0, $ip = '', $moderator_id = 0, $reason = '' ) {
		global $wpdb;

		$type         = sanitize_key( (string) $type );
		$user_id      = absint( $user_id );
		$ip           = trim( sanitize_text_field( (string) $ip ) );
		$moderator_id = absint( $moderator_id );
		$reason       = sanitize_textarea_field( (string) $reason );

		if ( ! self::is_valid_type( $type ) ) {
			return new WP_Error( 'invalid_blacklist_type', __( 'Invalid blacklist type.', 'fanfiction-manager' ) );
		}

		if ( ! self::table_ready() ) {
			return new WP_Error( 'table_not_ready', __( 'Blacklist table is not available.', 'fanfiction-manager' ) );
		}

		if ( ! $moderator_id ) {
			return new WP_Error( 'invalid_moderator', __( 'Invalid moderator.', 'fanfiction-manager' ) );
		}

		if ( ! $user_id && '' === $ip ) {
			return new WP_Error( 'invalid_target', __( 'No blacklist target was provided.', 'fanfiction-manager' ) );
		}

		if ( self::is_blacklisted( $type, $user_id, $ip ) ) {
			return new WP_Error( 'already_blacklisted', __( 'This target is already blacklisted.', 'fanfiction-manager' ) );
		}

		$table  = self::get_table_name();
		$result = $wpdb->insert(
			$table,
			array(
				'blacklist_type' => $type,
				'user_id'        => $user_id,
				'ip_address'     => $ip,
				'reason'         => $reason,
				'moderator_id'   => $moderator_id,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', __( 'Failed to add blacklist entry.', 'fanfiction-manager' ) );
		}

		self::$lookup_cache = array();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Remove a blacklist entry by ID.
	 *
	 * @since 2.4.0
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public static function remove( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id || ! self::table_ready() ) {
			return false;
		}

		$table  = self::get_table_name();
		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( ! empty( $deleted ) ) {
			self::$lookup_cache = array();
		}

		return ! empty( $deleted );
	}

	/**
	 * Get one blacklist entry by ID.
	 *
	 * @since 2.4.0
	 * @param int $id Entry ID.
	 * @return array|null
	 */
	public static function get_entry( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id || ! self::table_ready() ) {
			return null;
		}

		$table = self::get_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Get paginated blacklist entries for a type.
	 *
	 * @since 2.4.0
	 * @param string $type Blacklist type.
	 * @param array  $args Query args.
	 * @return array
	 */
	public static function get_entries( $type, $args = array() ) {
		global $wpdb;

		$type = sanitize_key( (string) $type );
		if ( ! self::is_valid_type( $type ) || ! self::table_ready() ) {
			return array();
		}

		$defaults = array(
			'limit'   => 20,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'created_at', 'user_id', 'moderator_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$limit           = max( 1, (int) $args['limit'] );
		$offset          = max( 0, (int) $args['offset'] );

		$table = self::get_table_name();
		$sql   = "SELECT * FROM {$table} WHERE blacklist_type = %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $type, $limit, $offset ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count blacklist entries for a type.
	 *
	 * @since 2.4.0
	 * @param string $type Blacklist type.
	 * @return int
	 */
	public static function count_entries( $type ) {
		global $wpdb;

		$type = sanitize_key( (string) $type );
		if ( ! self::is_valid_type( $type ) || ! self::table_ready() ) {
			return 0;
		}

		$table = self::get_table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE blacklist_type = %s",
				$type
			)
		);

		return (int) $count;
	}
}
