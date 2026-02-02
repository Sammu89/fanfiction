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
 * Creates and manages 16 custom tables:
 * - wp_fanfic_ratings: Chapter ratings (1-5 stars)
 * - wp_fanfic_likes: Chapter likes
 * - wp_fanfic_reading_progress: Mark chapters as read
 * - wp_fanfic_bookmarks: Story and chapter bookmarks
 * - wp_fanfic_follows: Unified story and author follows
 * - wp_fanfic_email_subscriptions: Email-only subscriptions
 * - wp_fanfic_notifications: In-app notifications
 * - wp_fanfic_fandoms: Fandom definitions
 * - wp_fanfic_story_fandoms: Story-fandom relations
 * - wp_fanfic_warnings: Warning definitions (NEW in 1.2.0)
 * - wp_fanfic_story_warnings: Story-warning relations (NEW in 1.2.0)
 * - wp_fanfic_languages: Language definitions (NEW in 1.3.0)
 * - wp_fanfic_story_languages: Story-language relations (NEW in 1.3.0)
 * - wp_fanfic_custom_taxonomies: Custom taxonomy definitions (NEW in 1.4.0)
 * - wp_fanfic_custom_terms: Custom taxonomy terms (NEW in 1.4.0)
 * - wp_fanfic_story_custom_terms: Story-custom term relations (NEW in 1.4.0)
 * - wp_fanfic_story_search_index: Pre-computed search index (NEW in 1.2.0)
 * - wp_fanfic_moderation_log: Moderation action log (NEW in 1.2.0)
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
	const DB_VERSION = '1.4.0';

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
	 * Creates all 13 tables with proper schema, indexes, and constraints.
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

		// 8. Fandoms Table
		$table_fandoms = $prefix . 'fanfic_fandoms';
		$sql_fandoms   = "CREATE TABLE IF NOT EXISTS {$table_fandoms} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			category varchar(191) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_slug (slug),
			KEY idx_active (is_active),
			KEY idx_category (category),
			KEY idx_name (name),
			FULLTEXT KEY idx_name_fulltext (name)
		) $charset_collate;";

		$result = dbDelta( $sql_fandoms );
		if ( empty( $result ) || ! self::verify_table_exists( $table_fandoms ) ) {
			$errors[] = 'Failed to create fandoms table';
		}

		// 9. Story-Fandom Relations Table
		$table_story_fandoms = $prefix . 'fanfic_story_fandoms';
		$sql_story_fandoms   = "CREATE TABLE IF NOT EXISTS {$table_story_fandoms} (
			story_id bigint(20) UNSIGNED NOT NULL,
			fandom_id bigint(20) UNSIGNED NOT NULL,
			UNIQUE KEY unique_story_fandom (story_id, fandom_id),
			KEY idx_story (story_id),
			KEY idx_fandom_story (fandom_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_fandoms );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_fandoms ) ) {
			$errors[] = 'Failed to create story_fandoms table';
		}

		// 10. Warnings Table
		$table_warnings = $prefix . 'fanfic_warnings';
		$sql_warnings   = "CREATE TABLE IF NOT EXISTS {$table_warnings} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			min_age enum('PG','13+','16+','18+') NOT NULL DEFAULT 'PG',
			description text NOT NULL,
			is_sexual tinyint(1) NOT NULL DEFAULT 0,
			is_pornographic tinyint(1) NOT NULL DEFAULT 0,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_slug (slug),
			KEY idx_enabled (enabled),
			KEY idx_min_age (min_age)
		) $charset_collate;";

		$result = dbDelta( $sql_warnings );
		if ( empty( $result ) || ! self::verify_table_exists( $table_warnings ) ) {
			$errors[] = 'Failed to create warnings table';
		}

		// 11. Story-Warning Relations Table
		$table_story_warnings = $prefix . 'fanfic_story_warnings';
		$sql_story_warnings   = "CREATE TABLE IF NOT EXISTS {$table_story_warnings} (
			story_id bigint(20) UNSIGNED NOT NULL,
			warning_id bigint(20) UNSIGNED NOT NULL,
			UNIQUE KEY unique_story_warning (story_id, warning_id),
			KEY idx_story (story_id),
			KEY idx_warning_story (warning_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_warnings );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_warnings ) ) {
			$errors[] = 'Failed to create story_warnings table';
		}

		// 12. Languages Table
		$table_languages = $prefix . 'fanfic_languages';
		$sql_languages   = "CREATE TABLE IF NOT EXISTS {$table_languages} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(10) NOT NULL,
			name varchar(255) NOT NULL,
			native_name varchar(255) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_slug (slug),
			KEY idx_active (is_active),
			KEY idx_name (name)
		) $charset_collate;";

		$result = dbDelta( $sql_languages );
		if ( empty( $result ) || ! self::verify_table_exists( $table_languages ) ) {
			$errors[] = 'Failed to create languages table';
		}

		// 13. Story-Language Relations Table
		$table_story_languages = $prefix . 'fanfic_story_languages';
		$sql_story_languages   = "CREATE TABLE IF NOT EXISTS {$table_story_languages} (
			story_id bigint(20) UNSIGNED NOT NULL,
			language_id bigint(20) UNSIGNED NOT NULL,
			UNIQUE KEY unique_story_language (story_id, language_id),
			KEY idx_story (story_id),
			KEY idx_language_story (language_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_languages );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_languages ) ) {
			$errors[] = 'Failed to create story_languages table';
		}

		// 14. Custom Taxonomies Table
		$table_custom_taxonomies = $prefix . 'fanfic_custom_taxonomies';
		$sql_custom_taxonomies   = "CREATE TABLE IF NOT EXISTS {$table_custom_taxonomies} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			selection_type enum('single','multi') NOT NULL DEFAULT 'single',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_slug (slug),
			KEY idx_active (is_active),
			KEY idx_sort (sort_order)
		) $charset_collate;";

		$result = dbDelta( $sql_custom_taxonomies );
		if ( empty( $result ) || ! self::verify_table_exists( $table_custom_taxonomies ) ) {
			$errors[] = 'Failed to create custom_taxonomies table';
		}

		// 15. Custom Terms Table
		$table_custom_terms = $prefix . 'fanfic_custom_terms';
		$sql_custom_terms   = "CREATE TABLE IF NOT EXISTS {$table_custom_terms} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			taxonomy_id bigint(20) UNSIGNED NOT NULL,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_taxonomy_slug (taxonomy_id, slug),
			KEY idx_taxonomy (taxonomy_id),
			KEY idx_active (is_active),
			KEY idx_name (name),
			FULLTEXT KEY idx_name_fulltext (name)
		) $charset_collate;";

		$result = dbDelta( $sql_custom_terms );
		if ( empty( $result ) || ! self::verify_table_exists( $table_custom_terms ) ) {
			$errors[] = 'Failed to create custom_terms table';
		}

		// 16. Story-Custom Terms Relations Table
		$table_story_custom_terms = $prefix . 'fanfic_story_custom_terms';
		$sql_story_custom_terms   = "CREATE TABLE IF NOT EXISTS {$table_story_custom_terms} (
			story_id bigint(20) UNSIGNED NOT NULL,
			term_id bigint(20) UNSIGNED NOT NULL,
			UNIQUE KEY unique_story_term (story_id, term_id),
			KEY idx_story (story_id),
			KEY idx_term_story (term_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_custom_terms );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_custom_terms ) ) {
			$errors[] = 'Failed to create story_custom_terms table';
		}

		// 17. Story Search Index Table
		$table_search_index = $prefix . 'fanfic_story_search_index';
		$sql_search_index   = "CREATE TABLE IF NOT EXISTS {$table_search_index} (
			story_id bigint(20) UNSIGNED NOT NULL,
			indexed_text longtext NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			story_title varchar(500) DEFAULT '',
			story_slug varchar(200) DEFAULT '',
			story_summary text,
			story_status varchar(20) DEFAULT 'publish',
			author_id bigint(20) UNSIGNED DEFAULT 0,
			published_date datetime DEFAULT NULL,
			updated_date datetime DEFAULT NULL,
			chapter_count int(11) DEFAULT 0,
			word_count bigint(20) DEFAULT 0,
			fandom_slugs text,
			language_slug varchar(50) DEFAULT '',
			warning_slugs varchar(500) DEFAULT '',
			age_rating varchar(5) DEFAULT '',
			visible_tags text,
			invisible_tags text,
			genre_names varchar(500) DEFAULT '',
			status_name varchar(100) DEFAULT '',
			PRIMARY KEY  (story_id),
			KEY idx_updated (updated_at),
			KEY idx_author (author_id),
			KEY idx_status (story_status),
			KEY idx_language (language_slug),
			KEY idx_age_rating (age_rating),
			FULLTEXT KEY idx_search_fulltext (indexed_text),
			FULLTEXT KEY idx_title_fulltext (story_title)
		) $charset_collate;";

		$result = dbDelta( $sql_search_index );
		if ( empty( $result ) || ! self::verify_table_exists( $table_search_index ) ) {
			$errors[] = 'Failed to create story_search_index table';
		}

		// 16. Moderation Log Table
		$table_moderation_log = $prefix . 'fanfic_moderation_log';
		$sql_moderation_log   = "CREATE TABLE IF NOT EXISTS {$table_moderation_log} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) UNSIGNED NOT NULL,
			action varchar(50) NOT NULL,
			target_type enum('user','story') NOT NULL,
			target_id bigint(20) UNSIGNED NOT NULL,
			reason text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_actor (actor_id),
			KEY idx_target (target_type, target_id),
			KEY idx_created (created_at),
			KEY idx_action (action)
		) $charset_collate;";

		$result = dbDelta( $sql_moderation_log );
		if ( empty( $result ) || ! self::verify_table_exists( $table_moderation_log ) ) {
			$errors[] = 'Failed to create moderation_log table';
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
	 * Migrate search index table to v2 with structured columns
	 *
	 * Adds metadata and taxonomy columns for faster filtering without joins.
	 *
	 * @since 1.5.0
	 * @return bool True on success
	 */
	public static function migrate_search_index_v2() {
		global $wpdb;
		$table = $wpdb->prefix . 'fanfic_story_search_index';

		// Check if migration is needed
		$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
		if ( in_array( 'story_title', $columns, true ) ) {
			return true; // Already migrated
		}

		// Add new columns one by one (safer than ALTER TABLE with multiple columns)
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN story_title varchar(500) DEFAULT '' AFTER indexed_text" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN story_slug varchar(200) DEFAULT '' AFTER story_title" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN story_summary text AFTER story_slug" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN story_status varchar(20) DEFAULT 'publish' AFTER story_summary" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN author_id bigint(20) UNSIGNED DEFAULT 0 AFTER story_status" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN published_date datetime DEFAULT NULL AFTER author_id" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN updated_date datetime DEFAULT NULL AFTER published_date" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN chapter_count int(11) DEFAULT 0 AFTER updated_date" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN word_count bigint(20) DEFAULT 0 AFTER chapter_count" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN fandom_slugs text AFTER word_count" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN language_slug varchar(50) DEFAULT '' AFTER fandom_slugs" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN warning_slugs varchar(500) DEFAULT '' AFTER language_slug" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN age_rating varchar(5) DEFAULT '' AFTER warning_slugs" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN visible_tags text AFTER age_rating" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN invisible_tags text AFTER visible_tags" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN genre_names varchar(500) DEFAULT '' AFTER invisible_tags" );
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN status_name varchar(100) DEFAULT '' AFTER genre_names" );

		// Add indexes
		$wpdb->query( "ALTER TABLE {$table} ADD KEY idx_author (author_id)" );
		$wpdb->query( "ALTER TABLE {$table} ADD KEY idx_status (story_status)" );
		$wpdb->query( "ALTER TABLE {$table} ADD KEY idx_language (language_slug)" );
		$wpdb->query( "ALTER TABLE {$table} ADD KEY idx_age_rating (age_rating)" );
		$wpdb->query( "ALTER TABLE {$table} ADD FULLTEXT KEY idx_title_fulltext (story_title)" );

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
			$prefix . 'fanfic_moderation_log',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_ratings',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_fandoms',
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
	 * @return bool True if all 15 tables exist, false if any missing.
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
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_moderation_log',
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
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_moderation_log',
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
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_moderation_log',
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
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_moderation_log',
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
			$prefix . 'fanfic_moderation_log',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_follows',
			$prefix . 'fanfic_bookmarks',
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_likes',
			$prefix . 'fanfic_ratings',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_fandoms',
		);

		// Truncate in reverse order to avoid foreign key issues
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		return true;
	}
}
