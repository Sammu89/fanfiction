<?php
/**
 * Database Setup Class
 *
 * Handles creation, management, and maintenance of all custom database tables
 * for the Fanfiction Manager plugin.
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Database_Setup
 *
 * Creates and manages 7 custom tables:
 * - wp_fanfic_ratings: Chapter ratings (1-5 stars)
 * - wp_fanfic_likes: Chapter likes
 * - wp_fanfic_reading_progress: Mark chapters as read
 * - wp_fanfic_bookmarks: Story and chapter bookmarks
 * - wp_fanfic_follows: Unified story and author follows
 * - wp_fanfic_email_subscriptions: Email-only subscriptions
 * - wp_fanfic_notifications: In-app notifications
 *
 * @since 1.0.0
 */
class Fanfic_Database_Setup {

	/**
	 * Database version constant
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option name for database version tracking
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const VERSION_OPTION = 'fanfic_db_version';

	/**
	 * Initialize the class and create tables
	 *
	 * Called on plugin activation.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function init() {
		$result = self::create_tables();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set database version
		self::set_db_version( self::DB_VERSION );

		return true;
	}

	/**
	 * Create all custom tables
	 *
	 * Uses dbDelta for safe table creation and updates.
	 * Creates all 7 tables with proper schema, indexes, and constraints.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_tables() {
		global $wpdb;

		// Require upgrade.php for dbDelta function
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		// Track if any table creation fails
		$errors = array();

		// 1. Ratings Table
		$table_ratings = $prefix . 'fanfic_ratings';
		$sql_ratings   = "CREATE TABLE IF NOT EXISTS {$table_ratings} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			chapter_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			rating tinyint(1) UNSIGNED NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_user_rating (chapter_id, user_id),
			KEY idx_chapter_rating (chapter_id, rating),
			KEY idx_created (created_at)
		) $charset_collate;";

		$result = dbDelta( $sql_ratings );
		if ( empty( $result ) || ! self::verify_table_exists( $table_ratings ) ) {
			$errors[] = 'Failed to create ratings table';
		}

		// 2. Likes Table
		$table_likes = $prefix . 'fanfic_likes';
		$sql_likes   = "CREATE TABLE IF NOT EXISTS {$table_likes} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			chapter_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_user_like (chapter_id, user_id),
			KEY idx_chapter (chapter_id),
			KEY idx_user (user_id)
		) $charset_collate;";

		$result = dbDelta( $sql_likes );
		if ( empty( $result ) || ! self::verify_table_exists( $table_likes ) ) {
			$errors[] = 'Failed to create likes table';
		}

		// 3. Reading Progress Table
		$table_reading = $prefix . 'fanfic_reading_progress';
		$sql_reading   = "CREATE TABLE IF NOT EXISTS {$table_reading} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			story_id bigint(20) UNSIGNED NOT NULL,
			chapter_number int(11) UNSIGNED NOT NULL,
			marked_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_progress (user_id, story_id, chapter_number),
			KEY idx_user_story (user_id, story_id),
			KEY idx_story (story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_reading );
		if ( empty( $result ) || ! self::verify_table_exists( $table_reading ) ) {
			$errors[] = 'Failed to create reading progress table';
		}

		// 4. Bookmarks Table (Updated with bookmark_type)
		$table_bookmarks = $prefix . 'fanfic_bookmarks';
		$sql_bookmarks   = "CREATE TABLE IF NOT EXISTS {$table_bookmarks} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			post_id bigint(20) UNSIGNED NOT NULL,
			bookmark_type enum('story','chapter') NOT NULL DEFAULT 'story',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_bookmark (user_id, post_id, bookmark_type),
			KEY idx_user_type (user_id, bookmark_type),
			KEY idx_created (created_at)
		) $charset_collate;";

		$result = dbDelta( $sql_bookmarks );
		if ( empty( $result ) || ! self::verify_table_exists( $table_bookmarks ) ) {
			$errors[] = 'Failed to create bookmarks table';
		}

		// 5. Follows Table (Unified for stories and authors)
		$table_follows = $prefix . 'fanfic_follows';
		$sql_follows   = "CREATE TABLE IF NOT EXISTS {$table_follows} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			target_id bigint(20) UNSIGNED NOT NULL,
			follow_type enum('story','author') NOT NULL,
			email_enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_follow (user_id, target_id, follow_type),
			KEY idx_target_type (target_id, follow_type),
			KEY idx_user_type (user_id, follow_type)
		) $charset_collate;";

		$result = dbDelta( $sql_follows );
		if ( empty( $result ) || ! self::verify_table_exists( $table_follows ) ) {
			$errors[] = 'Failed to create follows table';
		}

		// 6. Email Subscriptions Table
		$table_subscriptions = $prefix . 'fanfic_email_subscriptions';
		$sql_subscriptions   = "CREATE TABLE IF NOT EXISTS {$table_subscriptions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			target_id bigint(20) UNSIGNED NOT NULL,
			subscription_type enum('story','author') NOT NULL,
			token varchar(64) NOT NULL,
			verified tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_subscription (email, target_id, subscription_type),
			KEY idx_token (token),
			KEY idx_target_type (target_id, subscription_type)
		) $charset_collate;";

		$result = dbDelta( $sql_subscriptions );
		if ( empty( $result ) || ! self::verify_table_exists( $table_subscriptions ) ) {
			$errors[] = 'Failed to create email subscriptions table';
		}

		// 7. Notifications Table
		$table_notifications = $prefix . 'fanfic_notifications';
		$sql_notifications   = "CREATE TABLE IF NOT EXISTS {$table_notifications} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			type varchar(50) NOT NULL,
			message text NOT NULL,
			data longtext DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_read (user_id, is_read),
			KEY idx_created (created_at),
			KEY idx_type (type)
		) $charset_collate;";

		$result = dbDelta( $sql_notifications );
		if ( empty( $result ) || ! self::verify_table_exists( $table_notifications ) ) {
			$errors[] = 'Failed to create notifications table';
		}

		// Return errors if any
		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'table_creation_failed',
				implode( ', ', $errors )
			);
		}

		return true;
	}

	/**
	 * Verify that a table exists in the database
	 *
	 * @since 1.0.0
	 * @param string $table_name Full table name with prefix.
	 * @return bool True if table exists, false otherwise.
	 */
	private static function verify_table_exists( $table_name ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
	}

	/**
	 * Drop all custom tables
	 *
	 * DANGER: This permanently deletes all data in custom tables.
	 * Requires explicit confirmation via $confirm parameter.
	 *
	 * @since 1.0.0
	 * @param bool $confirm Must be true to execute. Safety check.
	 * @return bool|WP_Error True on success, WP_Error on failure or missing confirmation.
	 */
	public static function drop_tables( $confirm = false ) {
		if ( ! $confirm ) {
			return new WP_Error(
				'confirmation_required',
				'You must explicitly confirm table deletion by passing $confirm = true'
			);
		}

		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_ratings',
		);

		// Drop tables in reverse order to avoid foreign key issues
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Remove database version option
		delete_option( self::VERSION_OPTION );

		return true;
	}

	/**
	 * Check if all tables exist
	 *
	 * @since 1.0.0
	 * @return bool True if all 7 tables exist, false if any missing.
	 */
	public static function tables_exist() {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_ratings',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
		);

		foreach ( $tables as $table ) {
			if ( ! self::verify_table_exists( $table ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get table statistics
	 *
	 * Returns row counts for all custom tables.
	 *
	 * @since 1.0.0
	 * @return array Associative array with table statistics.
	 */
	public static function get_table_stats() {
		global $wpdb;

		$prefix = $wpdb->prefix;

		$stats = array(
			'total_ratings'       => 0,
			'total_likes'         => 0,
			'total_bookmarks'     => 0,
			'total_follows'       => 0,
			'total_reads'         => 0,
			'total_notifications' => 0,
			'total_subscriptions' => 0,
		);

		// Get counts from each table
		$stats['total_ratings'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_ratings"
		);

		$stats['total_likes'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_likes"
		);

		$stats['total_bookmarks'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_bookmarks"
		);

		$stats['total_follows'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_follows"
		);

		$stats['total_reads'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_reading_progress"
		);

		$stats['total_notifications'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_notifications"
		);

		$stats['total_subscriptions'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_email_subscriptions"
		);

		return $stats;
	}

	/**
	 * Get database version
	 *
	 * @since 1.0.0
	 * @return string Current database version, defaults to '1.0.0'.
	 */
	public static function get_db_version() {
		return get_option( self::VERSION_OPTION, '1.0.0' );
	}

	/**
	 * Update database version
	 *
	 * @since 1.0.0
	 * @param string $version Version number to set.
	 * @return bool True if version was updated, false otherwise.
	 */
	public static function set_db_version( $version ) {
		return update_option( self::VERSION_OPTION, $version );
	}

	/**
	 * Get detailed table information
	 *
	 * Returns structure information for all custom tables.
	 *
	 * @since 1.0.0
	 * @return array Array of table information including name, engine, rows, size.
	 */
	public static function get_table_info() {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_ratings',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
		);

		$table_info = array();

		foreach ( $tables as $table ) {
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SHOW TABLE STATUS LIKE %s",
					$table
				),
				ARRAY_A
			);

			if ( $result ) {
				$table_info[] = array(
					'name'    => $result['Name'],
					'engine'  => $result['Engine'],
					'rows'    => (int) $result['Rows'],
					'size'    => self::format_bytes( $result['Data_length'] + $result['Index_length'] ),
					'created' => $result['Create_time'],
				);
			}
		}

		return $table_info;
	}

	/**
	 * Format bytes to human-readable size
	 *
	 * @since 1.0.0
	 * @param int $bytes Size in bytes.
	 * @return string Formatted size string (e.g., "1.5 MB").
	 */
	private static function format_bytes( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return number_format( $bytes / 1024, 2 ) . ' KB';
		} else {
			return $bytes . ' bytes';
		}
	}

	/**
	 * Optimize all custom tables
	 *
	 * Runs OPTIMIZE TABLE on all custom tables to reclaim unused space
	 * and improve query performance.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public static function optimize_tables() {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_ratings',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "OPTIMIZE TABLE {$table}" );
		}

		return true;
	}

	/**
	 * Repair all custom tables
	 *
	 * Runs REPAIR TABLE on all custom tables to fix corrupted data.
	 * Use only when tables show corruption errors.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public static function repair_tables() {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_ratings',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "REPAIR TABLE {$table}" );
		}

		return true;
	}

	/**
	 * Truncate all custom tables
	 *
	 * DANGER: This deletes all data but keeps table structure.
	 * Requires explicit confirmation via $confirm parameter.
	 *
	 * @since 1.0.0
	 * @param bool $confirm Must be true to execute. Safety check.
	 * @return bool|WP_Error True on success, WP_Error on failure or missing confirmation.
	 */
	public static function truncate_tables( $confirm = false ) {
		if ( ! $confirm ) {
			return new WP_Error(
				'confirmation_required',
				'You must explicitly confirm table truncation by passing $confirm = true'
			);
		}

		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_ratings',
		);

		// Truncate in reverse order to avoid foreign key issues
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		return true;
	}
}
