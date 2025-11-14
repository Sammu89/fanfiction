<?php
/**
 * Database Migration Script: Old Schema to New Unified Schema
 *
 * Migrates data from the old database schema (class-fanfic-core.php) to the
 * new unified schema (class-fanfic-database-setup.php).
 *
 * IMPORTANT: This script should only be run ONCE during the plugin upgrade.
 * It handles column name changes and schema unification.
 *
 * @package Fanfiction_Manager
 * @since 1.0.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Database_Migration
 *
 * Handles one-time migration from old to new database schema.
 */
class Fanfic_Database_Migration {

	/**
	 * Migration version
	 *
	 * @var string
	 */
	const MIGRATION_VERSION = '1.0.1';

	/**
	 * Option name for tracking migration status
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'fanfic_migration_status';

	/**
	 * Run the complete migration
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function run_migration() {
		global $wpdb;

		// Check if migration already completed
		$migration_status = get_option( self::MIGRATION_OPTION, array() );
		if ( isset( $migration_status['completed'] ) && $migration_status['completed'] === self::MIGRATION_VERSION ) {
			return new WP_Error(
				'already_migrated',
				'Migration already completed for version ' . self::MIGRATION_VERSION
			);
		}

		// Log migration start
		self::log_migration( 'Starting database migration to version ' . self::MIGRATION_VERSION );

		// Start transaction for safety
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Step 1: Migrate bookmarks (story_id → post_id + bookmark_type)
			$bookmarks_result = self::migrate_bookmarks();
			if ( is_wp_error( $bookmarks_result ) ) {
				throw new Exception( $bookmarks_result->get_error_message() );
			}

			// Step 2: Migrate follows (author_id/follower_id → target_id/user_id + follow_type)
			$follows_result = self::migrate_follows();
			if ( is_wp_error( $follows_result ) ) {
				throw new Exception( $follows_result->get_error_message() );
			}

			// Step 3: Migrate reading progress (single position → multiple chapters)
			$reading_result = self::migrate_reading_progress();
			if ( is_wp_error( $reading_result ) ) {
				throw new Exception( $reading_result->get_error_message() );
			}

			// Step 4: Migrate subscriptions (wp_fanfic_subscriptions → wp_fanfic_email_subscriptions)
			$subscriptions_result = self::migrate_subscriptions();
			if ( is_wp_error( $subscriptions_result ) ) {
				throw new Exception( $subscriptions_result->get_error_message() );
			}

			// Step 5: Handle orphaned anonymous data (identifier_hash)
			$anonymous_result = self::handle_anonymous_data();
			if ( is_wp_error( $anonymous_result ) ) {
				throw new Exception( $anonymous_result->get_error_message() );
			}

			// Commit transaction
			$wpdb->query( 'COMMIT' );

			// Mark migration as complete
			update_option( self::MIGRATION_OPTION, array(
				'completed' => self::MIGRATION_VERSION,
				'completed_at' => current_time( 'mysql' ),
			) );

			self::log_migration( 'Migration completed successfully' );

			return true;

		} catch ( Exception $e ) {
			// Rollback on error
			$wpdb->query( 'ROLLBACK' );

			self::log_migration( 'Migration failed: ' . $e->getMessage(), 'error' );

			return new WP_Error(
				'migration_failed',
				'Migration failed: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Migrate bookmarks: story_id → post_id + add bookmark_type = 'story'
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function migrate_bookmarks() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_bookmarks';

		// Check if old column exists
		$columns = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'story_id'
		) );

		if ( empty( $columns ) ) {
			// Old column doesn't exist, migration already done or not needed
			self::log_migration( 'Bookmarks: No migration needed (story_id column not found)' );
			return true;
		}

		self::log_migration( 'Starting bookmarks migration...' );

		// Count existing records
		$total_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		self::log_migration( "Bookmarks: Found {$total_records} records to migrate" );

		// Check if new columns exist
		$post_id_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'post_id'
		) );

		$bookmark_type_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'bookmark_type'
		) );

		// Add new columns if they don't exist
		if ( empty( $post_id_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER user_id" );
			self::log_migration( 'Bookmarks: Added post_id column' );
		}

		if ( empty( $bookmark_type_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN bookmark_type enum('story','chapter') NOT NULL DEFAULT 'story' AFTER post_id" );
			self::log_migration( 'Bookmarks: Added bookmark_type column' );
		}

		// Migrate data: copy story_id to post_id
		$migrated = $wpdb->query( "UPDATE {$table_name} SET post_id = story_id, bookmark_type = 'story' WHERE post_id = 0" );

		self::log_migration( "Bookmarks: Migrated {$migrated} records" );

		// Verify migration
		$verification = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE post_id = 0" );
		if ( $verification > 0 ) {
			return new WP_Error(
				'bookmarks_migration_failed',
				"Bookmarks migration verification failed: {$verification} records still have post_id = 0"
			);
		}

		// Remove old column and constraints
		$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX IF EXISTS unique_bookmark" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX IF EXISTS story_id" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN story_id" );

		// Add new unique constraint
		$wpdb->query( "ALTER TABLE {$table_name} ADD UNIQUE KEY unique_bookmark (user_id, post_id, bookmark_type)" );
		$wpdb->query( "ALTER TABLE {$table_name} ADD KEY idx_user_type (user_id, bookmark_type)" );

		self::log_migration( 'Bookmarks: Migration completed successfully' );

		return true;
	}

	/**
	 * Migrate follows: author_id/follower_id → target_id/user_id + add follow_type = 'author'
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function migrate_follows() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_follows';

		// Check if old columns exist
		$author_id_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'author_id'
		) );

		$follower_id_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'follower_id'
		) );

		if ( empty( $author_id_exists ) && empty( $follower_id_exists ) ) {
			// Old columns don't exist, migration already done or not needed
			self::log_migration( 'Follows: No migration needed (old columns not found)' );
			return true;
		}

		self::log_migration( 'Starting follows migration...' );

		// Count existing records
		$total_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		self::log_migration( "Follows: Found {$total_records} records to migrate" );

		// Check if new columns exist
		$user_id_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'user_id'
		) );

		$target_id_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'target_id'
		) );

		$follow_type_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'follow_type'
		) );

		$email_enabled_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'email_enabled'
		) );

		// Add new columns if they don't exist
		if ( empty( $user_id_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id" );
			self::log_migration( 'Follows: Added user_id column' );
		}

		if ( empty( $target_id_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN target_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER user_id" );
			self::log_migration( 'Follows: Added target_id column' );
		}

		if ( empty( $follow_type_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN follow_type enum('story','author') NOT NULL DEFAULT 'author' AFTER target_id" );
			self::log_migration( 'Follows: Added follow_type column' );
		}

		if ( empty( $email_enabled_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN email_enabled tinyint(1) NOT NULL DEFAULT 1 AFTER follow_type" );
			self::log_migration( 'Follows: Added email_enabled column' );
		}

		// Migrate data: follower_id → user_id, author_id → target_id
		$migrated = $wpdb->query( "UPDATE {$table_name} SET user_id = follower_id, target_id = author_id, follow_type = 'author', email_enabled = 1 WHERE user_id = 0" );

		self::log_migration( "Follows: Migrated {$migrated} records" );

		// Verify migration
		$verification = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE user_id = 0 OR target_id = 0" );
		if ( $verification > 0 ) {
			return new WP_Error(
				'follows_migration_failed',
				"Follows migration verification failed: {$verification} records still have user_id or target_id = 0"
			);
		}

		// Remove old columns and constraints
		$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX IF EXISTS unique_follow" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX IF EXISTS follower_id" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX IF EXISTS author_id" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX IF EXISTS author_created" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN IF EXISTS follower_id" );
		$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN IF EXISTS author_id" );

		// Add new constraints
		$wpdb->query( "ALTER TABLE {$table_name} ADD UNIQUE KEY unique_follow (user_id, target_id, follow_type)" );
		$wpdb->query( "ALTER TABLE {$table_name} ADD KEY idx_target_type (target_id, follow_type)" );
		$wpdb->query( "ALTER TABLE {$table_name} ADD KEY idx_user_type (user_id, follow_type)" );

		self::log_migration( 'Follows: Migration completed successfully' );

		return true;
	}

	/**
	 * Migrate reading progress: single position (chapter_id) → multiple chapters (chapter_number)
	 *
	 * Old schema: One row per user/story with chapter_id and chapter_number
	 * New schema: One row per user/story/chapter_number combination
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function migrate_reading_progress() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fanfic_reading_progress';

		// Check if old columns exist (chapter_id, is_completed)
		$chapter_id_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'chapter_id'
		) );

		$is_completed_exists = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			'is_completed'
		) );

		if ( empty( $chapter_id_exists ) && empty( $is_completed_exists ) ) {
			// Old columns don't exist, migration already done or not needed
			self::log_migration( 'Reading Progress: No migration needed (old columns not found)' );
			return true;
		}

		self::log_migration( 'Starting reading progress migration...' );

		// Count existing records
		$total_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		self::log_migration( "Reading Progress: Found {$total_records} records to migrate" );

		// Old schema: user was on chapter X of story Y
		// New schema: mark all chapters 1 through X as read

		// Get all reading progress records
		$records = $wpdb->get_results( "SELECT user_id, story_id, chapter_number FROM {$table_name}", ARRAY_A );

		// Drop old table and recreate with new schema
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}_backup" );
		$wpdb->query( "CREATE TABLE {$table_name}_backup LIKE {$table_name}" );
		$wpdb->query( "INSERT INTO {$table_name}_backup SELECT * FROM {$table_name}" );

		self::log_migration( 'Reading Progress: Created backup table' );

		// Recreate table with new schema
		$wpdb->query( "DROP TABLE {$table_name}" );

		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
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

		$wpdb->query( $sql );

		self::log_migration( 'Reading Progress: Recreated table with new schema' );

		// Migrate records: for each user/story combination, mark all chapters up to chapter_number as read
		$migrated_count = 0;
		foreach ( $records as $record ) {
			$user_id = absint( $record['user_id'] );
			$story_id = absint( $record['story_id'] );
			$chapter_number = absint( $record['chapter_number'] );

			// Mark all chapters from 1 to chapter_number as read
			for ( $i = 1; $i <= $chapter_number; $i++ ) {
				$wpdb->insert(
					$table_name,
					array(
						'user_id' => $user_id,
						'story_id' => $story_id,
						'chapter_number' => $i,
						'marked_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%s' )
				);
				$migrated_count++;
			}
		}

		self::log_migration( "Reading Progress: Migrated {$total_records} records into {$migrated_count} chapter reads" );

		return true;
	}

	/**
	 * Migrate subscriptions: wp_fanfic_subscriptions → wp_fanfic_email_subscriptions
	 * Add subscription_type = 'story'
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function migrate_subscriptions() {
		global $wpdb;

		$old_table = $wpdb->prefix . 'fanfic_subscriptions';
		$new_table = $wpdb->prefix . 'fanfic_email_subscriptions';

		// Check if old table exists
		$old_table_exists = $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$old_table
		) );

		if ( ! $old_table_exists ) {
			self::log_migration( 'Subscriptions: No migration needed (old table not found)' );
			return true;
		}

		self::log_migration( 'Starting subscriptions migration...' );

		// Count existing records
		$total_records = $wpdb->get_var( "SELECT COUNT(*) FROM {$old_table}" );
		self::log_migration( "Subscriptions: Found {$total_records} records to migrate" );

		// Migrate data: story_id → target_id, add subscription_type = 'story', is_active → verified
		$migrated = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$new_table} (email, target_id, subscription_type, token, verified, created_at)
			SELECT email, story_id, 'story', token, is_active, created_at
			FROM {$old_table}
			WHERE email NOT IN (SELECT email FROM {$new_table} WHERE subscription_type = 'story')"
		) );

		self::log_migration( "Subscriptions: Migrated {$migrated} records" );

		// Rename old table to backup
		$wpdb->query( "RENAME TABLE {$old_table} TO {$old_table}_backup" );

		self::log_migration( 'Subscriptions: Renamed old table to backup' );

		return true;
	}

	/**
	 * Handle orphaned anonymous data (identifier_hash column)
	 *
	 * Old schema had identifier_hash for anonymous ratings/likes.
	 * New schema doesn't support anonymous actions.
	 * We'll log the count but NOT migrate this data.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function handle_anonymous_data() {
		global $wpdb;

		self::log_migration( 'Checking for orphaned anonymous data...' );

		// Check ratings table for anonymous data
		$ratings_table = $wpdb->prefix . 'fanfic_ratings';
		$anonymous_ratings = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$ratings_table} WHERE identifier_hash IS NOT NULL AND identifier_hash != %s",
			''
		) );

		if ( $anonymous_ratings > 0 ) {
			self::log_migration( "Anonymous Data: Found {$anonymous_ratings} anonymous ratings (NOT migrated - new schema requires user accounts)" );
		}

		// Check likes table for anonymous data
		$likes_table = $wpdb->prefix . 'fanfic_likes';
		$likes_check = $wpdb->get_var( "SHOW TABLES LIKE '{$likes_table}'" );

		if ( $likes_check ) {
			$anonymous_likes = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$likes_table} WHERE identifier_hash IS NOT NULL AND identifier_hash != %s",
				''
			) );

			if ( $anonymous_likes > 0 ) {
				self::log_migration( "Anonymous Data: Found {$anonymous_likes} anonymous likes (NOT migrated - new schema requires user accounts)" );
			}
		}

		// Remove identifier_hash columns from new schema if they still exist
		$columns = $wpdb->get_col( $wpdb->prepare(
			"SHOW COLUMNS FROM {$ratings_table} LIKE %s",
			'identifier_hash'
		) );

		if ( ! empty( $columns ) ) {
			$wpdb->query( "ALTER TABLE {$ratings_table} DROP COLUMN identifier_hash" );
			$wpdb->query( "ALTER TABLE {$ratings_table} DROP INDEX IF EXISTS unique_rating_anonymous" );
			$wpdb->query( "ALTER TABLE {$ratings_table} DROP INDEX IF EXISTS identifier_hash" );
			self::log_migration( 'Anonymous Data: Removed identifier_hash column from ratings table' );
		}

		if ( $likes_check ) {
			$columns = $wpdb->get_col( $wpdb->prepare(
				"SHOW COLUMNS FROM {$likes_table} LIKE %s",
				'identifier_hash'
			) );

			if ( ! empty( $columns ) ) {
				$wpdb->query( "ALTER TABLE {$likes_table} DROP COLUMN identifier_hash" );
				$wpdb->query( "ALTER TABLE {$likes_table} DROP INDEX IF EXISTS unique_like_anonymous" );
				$wpdb->query( "ALTER TABLE {$likes_table} DROP INDEX IF EXISTS identifier_hash" );
				self::log_migration( 'Anonymous Data: Removed identifier_hash column from likes table' );
			}
		}

		return true;
	}

	/**
	 * Log migration message
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level (info, warning, error).
	 */
	private static function log_migration( $message, $level = 'info' ) {
		$timestamp = current_time( 'mysql' );
		$log_entry = "[{$timestamp}] [{$level}] {$message}\n";

		// Write to debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'Fanfic Migration: ' . $message );
		}

		// Also store in option for admin review
		$log = get_option( 'fanfic_migration_log', '' );
		$log .= $log_entry;
		update_option( 'fanfic_migration_log', $log );
	}

	/**
	 * Get migration log
	 *
	 * @return string Migration log contents.
	 */
	public static function get_migration_log() {
		return get_option( 'fanfic_migration_log', '' );
	}

	/**
	 * Clear migration log
	 */
	public static function clear_migration_log() {
		delete_option( 'fanfic_migration_log' );
	}

	/**
	 * Check if migration is needed
	 *
	 * @return bool True if migration needed, false otherwise.
	 */
	public static function is_migration_needed() {
		$migration_status = get_option( self::MIGRATION_OPTION, array() );
		return ! isset( $migration_status['completed'] ) || $migration_status['completed'] !== self::MIGRATION_VERSION;
	}

	/**
	 * Reset migration status (for testing)
	 *
	 * WARNING: Only use for testing purposes.
	 */
	public static function reset_migration_status() {
		delete_option( self::MIGRATION_OPTION );
		delete_option( 'fanfic_migration_log' );
	}
}
