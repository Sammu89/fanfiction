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
 * Creates and manages custom tables:
 * - wp_fanfic_reading_progress: Mark chapters as read
 * - wp_fanfic_email_subscriptions: Email-only subscriptions
 * - wp_fanfic_notifications: In-app notifications
 * - wp_fanfic_coauthors: Story co-author relationships
 * - wp_fanfic_fandoms: Fandom definitions
 * - wp_fanfic_story_fandoms: Story-fandom relations
 * - wp_fanfic_warnings: Warning definitions (NEW in 1.2.0)
 * - wp_fanfic_story_warnings: Story-warning relations (NEW in 1.2.0)
 * - wp_fanfic_languages: Language definitions (NEW in 1.3.0)
 * - wp_fanfic_story_languages: Story-language relations (NEW in 1.3.0)
 * - wp_fanfic_custom_taxonomies: Custom taxonomy definitions (NEW in 1.4.0)
 * - wp_fanfic_custom_terms: Custom taxonomy terms (NEW in 1.4.0)
 * - wp_fanfic_story_custom_terms: Story-custom term relations (NEW in 1.4.0)
 * - wp_fanfic_interactions: Unified interactions storage (NEW in 1.6.0)
 * - wp_fanfic_chapter_search_index: Chapter-level ranking counters (NEW in 1.5.4)
 * - wp_fanfic_story_search_index: Pre-computed search index (NEW in 1.2.0)
 * - wp_fanfic_story_filter_map: Pre-computed filter facets map (NEW in 1.5.2)
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
	const DB_VERSION = '2.0.0';

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
	public static function init( $include_classification = true ) {
		$result = self::create_tables( $include_classification );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Run migrations for version upgrades.
		self::maybe_run_migrations();

		// Set database version
		self::set_db_version( self::DB_VERSION );

		return true;
	}

	/**
	 * Create all custom tables
	 *
	 * Uses dbDelta for safe table creation and updates.
	 * Creates all plugin tables with proper schema, indexes, and constraints.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_tables( $include_classification = true ) {
		global $wpdb;

		// Require upgrade.php for dbDelta function
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		// Track if any table creation fails
		$errors = array();

		// 1. Reading Progress Table
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

		// 4. Email Subscriptions Table
		$table_subscriptions = $prefix . 'fanfic_email_subscriptions';
		$sql_subscriptions   = "CREATE TABLE IF NOT EXISTS {$table_subscriptions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			target_id bigint(20) UNSIGNED NOT NULL,
			subscription_type enum('story','author') NOT NULL,
			token varchar(64) NOT NULL,
			verified tinyint(1) NOT NULL DEFAULT 1,
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

		// 5. Notifications Table
		$table_notifications = $prefix . 'fanfic_notifications';
		$sql_notifications   = "CREATE TABLE IF NOT EXISTS {$table_notifications} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			type varchar(50) NOT NULL,
			message text NOT NULL,
			data longtext DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			is_persistent tinyint(1) NOT NULL DEFAULT 0,
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

		// 6. Co-Authors Table
		$table_coauthors = $prefix . 'fanfic_coauthors';
		$sql_coauthors   = "CREATE TABLE IF NOT EXISTS {$table_coauthors} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			story_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			invited_by bigint(20) UNSIGNED NOT NULL,
			status enum('pending','accepted','refused') NOT NULL DEFAULT 'pending',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			responded_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_story_user (story_id, user_id),
			KEY idx_user_status (user_id, status),
			KEY idx_story_status (story_id, status)
		) $charset_collate;";

		$result = dbDelta( $sql_coauthors );
		if ( empty( $result ) || ! self::verify_table_exists( $table_coauthors ) ) {
			$errors[] = 'Failed to create coauthors table';
		}

		// 9-14. Classification tables (fandoms, warnings, languages).
		// Skipped on activation when $include_classification is false;
		// the setup wizard creates them on demand in step 1.
		if ( $include_classification ) {

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
			min_age tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
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
			slug varchar(191) NOT NULL,
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

		// 14. Story Translation Groups Table
		$table_story_translations = $prefix . 'fanfic_story_translations';
		$sql_story_translations   = "CREATE TABLE IF NOT EXISTS {$table_story_translations} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id bigint(20) UNSIGNED NOT NULL,
			story_id bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_story (story_id),
			KEY idx_group (group_id),
			KEY idx_group_story (group_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_translations );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_translations ) ) {
			$errors[] = 'Failed to create story_translations table';
		}

		} // end if ( $include_classification )

		// 15. Custom Taxonomies Table
		$table_custom_taxonomies = $prefix . 'fanfic_custom_taxonomies';
		$sql_custom_taxonomies   = "CREATE TABLE IF NOT EXISTS {$table_custom_taxonomies} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			selection_type enum('single','multi') NOT NULL DEFAULT 'single',
			display_format varchar(20) NOT NULL DEFAULT 'grid',
			is_searchable tinyint(1) NOT NULL DEFAULT 1,
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

		// 16. Custom Terms Table
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

		// 17. Story-Custom Terms Relations Table
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

		// 17. Unified Interactions Table
		$table_interactions = $prefix . 'fanfic_interactions';
		$sql_interactions   = "CREATE TABLE IF NOT EXISTS {$table_interactions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NULL DEFAULT NULL,
			anon_hash binary(32) DEFAULT NULL,
			chapter_id bigint(20) UNSIGNED NOT NULL,
			interaction_type enum('like','dislike','rating','view','read','follow') NOT NULL,
			`value` decimal(3,1) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_user_chapter_type (user_id, chapter_id, interaction_type),
			UNIQUE KEY uq_anon_chapter_type (anon_hash, chapter_id, interaction_type),
			KEY idx_chapter (chapter_id),
			KEY idx_type_chapter (interaction_type, chapter_id),
			KEY idx_gc_anon (user_id, updated_at, id),
			KEY idx_anon_hash_updated (anon_hash, updated_at)
		) $charset_collate;";

		$result = dbDelta( $sql_interactions );
		if ( empty( $result ) || ! self::verify_table_exists( $table_interactions ) ) {
			$errors[] = 'Failed to create interactions table';
		}

		// 18. Chapter Search Index Table
		$table_chapter_index = $prefix . 'fanfic_chapter_search_index';
		$sql_chapter_index   = "CREATE TABLE IF NOT EXISTS {$table_chapter_index} (
			chapter_id bigint(20) UNSIGNED NOT NULL,
			story_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			views_total bigint(20) NOT NULL DEFAULT 0,
			views_week bigint(20) NOT NULL DEFAULT 0,
			views_month bigint(20) NOT NULL DEFAULT 0,
			views_week_stamp int(11) NOT NULL DEFAULT 0,
			views_month_stamp int(11) NOT NULL DEFAULT 0,
			likes_total bigint(20) NOT NULL DEFAULT 0,
			likes_week bigint(20) NOT NULL DEFAULT 0,
			likes_month bigint(20) NOT NULL DEFAULT 0,
			likes_week_stamp int(11) NOT NULL DEFAULT 0,
			likes_month_stamp int(11) NOT NULL DEFAULT 0,
			dislikes_total bigint(20) NOT NULL DEFAULT 0,
			rating_sum_total double NOT NULL DEFAULT 0,
			rating_count_total bigint(20) NOT NULL DEFAULT 0,
			rating_avg_total double NOT NULL DEFAULT 0,
			rating_sum_week double NOT NULL DEFAULT 0,
			rating_count_week bigint(20) NOT NULL DEFAULT 0,
			rating_avg_week double NOT NULL DEFAULT 0,
			rating_week_stamp int(11) NOT NULL DEFAULT 0,
			rating_sum_month double NOT NULL DEFAULT 0,
			rating_count_month bigint(20) NOT NULL DEFAULT 0,
			rating_avg_month double NOT NULL DEFAULT 0,
			rating_month_stamp int(11) NOT NULL DEFAULT 0,
			trending_week double NOT NULL DEFAULT 0,
			trending_month double NOT NULL DEFAULT 0,
			PRIMARY KEY  (chapter_id),
			KEY idx_story_id (story_id),
			KEY idx_views_week (views_week),
			KEY idx_views_month (views_month),
			KEY idx_trending_week (trending_week),
			KEY idx_trending_month (trending_month)
		) $charset_collate;";

		$result = dbDelta( $sql_chapter_index );
		if ( empty( $result ) || ! self::verify_table_exists( $table_chapter_index ) ) {
			$errors[] = 'Failed to create chapter_search_index table';
		}

		// 19. Story Search Index Table
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
			coauthor_ids varchar(500) DEFAULT '',
			coauthor_names varchar(1000) DEFAULT '',
			coauthor_logins varchar(1000) DEFAULT '',
			featured_image_id bigint(20) UNSIGNED DEFAULT 0,
			published_date datetime DEFAULT NULL,
			updated_date datetime DEFAULT NULL,
			chapter_count int(11) DEFAULT 0,
			word_count bigint(20) DEFAULT 0,
			view_count bigint(20) DEFAULT 0,
			views_week bigint(20) NOT NULL DEFAULT 0,
			views_month bigint(20) NOT NULL DEFAULT 0,
			views_week_stamp int(11) NOT NULL DEFAULT 0,
			views_month_stamp int(11) NOT NULL DEFAULT 0,
			likes_total bigint(20) NOT NULL DEFAULT 0,
			likes_week bigint(20) NOT NULL DEFAULT 0,
			likes_month bigint(20) NOT NULL DEFAULT 0,
			likes_week_stamp int(11) NOT NULL DEFAULT 0,
			likes_month_stamp int(11) NOT NULL DEFAULT 0,
			dislikes_total bigint(20) NOT NULL DEFAULT 0,
			rating_sum_total double NOT NULL DEFAULT 0,
			rating_count_total bigint(20) NOT NULL DEFAULT 0,
			rating_avg_total double NOT NULL DEFAULT 0,
			rating_sum_week double NOT NULL DEFAULT 0,
			rating_count_week bigint(20) NOT NULL DEFAULT 0,
			rating_avg_week double NOT NULL DEFAULT 0,
			rating_week_stamp int(11) NOT NULL DEFAULT 0,
			rating_sum_month double NOT NULL DEFAULT 0,
			rating_count_month bigint(20) NOT NULL DEFAULT 0,
			rating_avg_month double NOT NULL DEFAULT 0,
			rating_month_stamp int(11) NOT NULL DEFAULT 0,
			trending_week double NOT NULL DEFAULT 0,
			trending_month double NOT NULL DEFAULT 0,
			follow_count bigint(20) NOT NULL DEFAULT 0,
			fandom_slugs text,
			language_slug varchar(50) DEFAULT '',
			translation_group_id bigint(20) UNSIGNED DEFAULT 0,
			translation_count int(11) DEFAULT 0,
			warning_slugs varchar(500) DEFAULT '',
			age_rating varchar(50) DEFAULT '',
			visible_tags text,
			invisible_tags text,
			genre_names varchar(500) DEFAULT '',
			status_name varchar(100) DEFAULT '',
			author_display_name varchar(255) DEFAULT '',
			author_login varchar(60) DEFAULT '',
			language_name varchar(120) DEFAULT '',
			language_native_name varchar(120) DEFAULT '',
			warning_names text,
			fandom_names text,
			PRIMARY KEY  (story_id),
			KEY idx_updated (updated_at),
			KEY idx_author (author_id),
			KEY idx_status (story_status),
			KEY idx_view_count (view_count),
			KEY idx_views_week (views_week),
			KEY idx_views_month (views_month),
			KEY idx_likes_week (likes_week),
			KEY idx_likes_month (likes_month),
			KEY idx_rating_avg_week (rating_avg_week),
			KEY idx_rating_avg_month (rating_avg_month),
			KEY idx_trending_week (trending_week),
			KEY idx_trending_month (trending_month),
			KEY idx_follow_count (follow_count),
			KEY idx_updated_date (updated_date),
			KEY idx_published_date (published_date),
			KEY idx_story_title (story_title(191)),
			KEY idx_language (language_slug),
			KEY idx_translation_group (translation_group_id),
			KEY idx_age_rating (age_rating),
			FULLTEXT KEY idx_search_fulltext (indexed_text),
			FULLTEXT KEY idx_title_fulltext (story_title)
		) $charset_collate;";

		$result = dbDelta( $sql_search_index );
		if ( empty( $result ) || ! self::verify_table_exists( $table_search_index ) ) {
			$errors[] = 'Failed to create story_search_index table';
		}

		// 20. Story Filter Map Table
		$table_filter_map = $prefix . 'fanfic_story_filter_map';
		$sql_filter_map   = "CREATE TABLE IF NOT EXISTS {$table_filter_map} (
			story_id bigint(20) UNSIGNED NOT NULL,
			facet_type varchar(64) NOT NULL,
			facet_value varchar(191) NOT NULL,
			PRIMARY KEY  (story_id, facet_type, facet_value),
			KEY idx_facet_lookup (facet_type, facet_value, story_id),
			KEY idx_story_facet (story_id, facet_type)
		) $charset_collate;";

		$result = dbDelta( $sql_filter_map );
		if ( empty( $result ) || ! self::verify_table_exists( $table_filter_map ) ) {
			$errors[] = 'Failed to create story_filter_map table';
		}

		// 21. Moderation Log Table
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

		// All tables created successfully, proceed to seed default data if needed.

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
	 * Create only the classification tables (fandoms, warnings, languages).
	 *
	 * Called by the setup wizard step 1 AJAX handler so that these tables
	 * (and their seed data) are ready before the user clicks Next.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_classification_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;
		$errors          = array();

		// 1. Fandoms Table
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

		// 2. Story-Fandom Relations Table
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

		// 3. Warnings Table
		$table_warnings = $prefix . 'fanfic_warnings';
		$sql_warnings   = "CREATE TABLE IF NOT EXISTS {$table_warnings} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			min_age tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
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

		// 4. Story-Warning Relations Table
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

		// 5. Languages Table
		$table_languages = $prefix . 'fanfic_languages';
		$sql_languages   = "CREATE TABLE IF NOT EXISTS {$table_languages} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slug varchar(191) NOT NULL,
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

		// 6. Story-Language Relations Table
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

		// 7. Story Translation Groups Table
		$table_story_translations = $prefix . 'fanfic_story_translations';
		$sql_story_translations   = "CREATE TABLE IF NOT EXISTS {$table_story_translations} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id bigint(20) UNSIGNED NOT NULL,
			story_id bigint(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_story (story_id),
			KEY idx_group (group_id),
			KEY idx_group_story (group_id, story_id)
		) $charset_collate;";

		$result = dbDelta( $sql_story_translations );
		if ( empty( $result ) || ! self::verify_table_exists( $table_story_translations ) ) {
			$errors[] = 'Failed to create story_translations table';
		}



		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'classification_tables_failed',
				implode( ', ', $errors )
			);
		}

		return true;
	}

	/**
	 * Check whether all seven classification tables exist.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function classification_tables_exist() {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
		);

		foreach ( $tables as $table ) {
			if ( ! self::verify_table_exists( $table ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Track missing classification tables for admin notice.
	 *
	 * Sets a transient when any classification table is missing so we can
	 * surface a rebuild prompt in the admin UI.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function check_missing_classification_tables() {
		// Only run in admin for users who can fix issues.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Skip while the setup wizard is running or classification creation is paused.
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			return;
		}

		if ( get_transient( 'fanfic_skip_classification' ) ) {
			return;
		}

		if ( self::classification_tables_exist() ) {
			delete_transient( 'fanfic_missing_classification_tables' );
			return;
		}

		set_transient( 'fanfic_missing_classification_tables', true, MINUTE_IN_SECONDS * 10 );
	}

	/**
	 * Display admin notice for missing classification tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function missing_classification_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show during wizard run or while classification is intentionally skipped.
		if ( isset( $_GET['page'] ) && 'fanfic-setup-wizard' === $_GET['page'] ) {
			return;
		}

		if ( get_transient( 'fanfic_skip_classification' ) ) {
			return;
		}

		// Show rebuild result messages if present.
		$rebuild_result = get_transient( 'fanfic_classification_rebuild_result' );
		if ( is_array( $rebuild_result ) && ! empty( $rebuild_result['message'] ) && ! empty( $rebuild_result['status'] ) ) {
			delete_transient( 'fanfic_classification_rebuild_result' );
			$notice_class = ( 'success' === $rebuild_result['status'] ) ? 'notice notice-success is-dismissible' : 'notice error-message is-dismissible';
			?>
			<div class="<?php echo esc_attr( $notice_class ); ?>">
				<p>
					<strong><?php esc_html_e( 'Fanfiction Manager:', 'fanfiction-manager' ); ?></strong>
					<?php echo esc_html( $rebuild_result['message'] ); ?>
				</p>
			</div>
			<?php
		}

		if ( ! get_transient( 'fanfic_missing_classification_tables' ) ) {
			return;
		}

		$rebuild_url = admin_url( 'admin-post.php?action=fanfic_rebuild_classification' );
		$rebuild_url = wp_nonce_url( $rebuild_url, 'fanfic_rebuild_classification' );
		?>
		<div class="notice error-message is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Fanfiction Manager:', 'fanfiction-manager' ); ?></strong>
				<?php esc_html_e( 'Some data is missing on database. Click the button to rebuild.', 'fanfiction-manager' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $rebuild_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Rebuild classification tables', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle rebuild request for missing classification tables.
	 *
	 * Recreates classification tables and seeds default data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function rebuild_classification_tables() {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fanfic_rebuild_classification' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'fanfiction-manager' ) );
		}

		$result = self::create_classification_tables();
		$notice = array(
			'status'  => 'success',
			'message' => __( 'Classification tables rebuilt successfully.', 'fanfiction-manager' ),
		);

		if ( is_wp_error( $result ) ) {
			$notice = array(
				'status'  => 'error',
				'message' => $result->get_error_message(),
			);
		} elseif ( ! self::classification_tables_exist() ) {
			$notice = array(
				'status'  => 'error',
				'message' => __( 'Classification tables are still missing after the rebuild attempt.', 'fanfiction-manager' ),
			);
		} else {
			// Seed default datasets and taxonomy terms.
			if ( class_exists( 'Fanfic_Fandoms' ) ) {
				Fanfic_Fandoms::maybe_seed_fandoms();
			}
			if ( class_exists( 'Fanfic_Warnings' ) ) {
				Fanfic_Warnings::maybe_seed_warnings();
			}
			if ( class_exists( 'Fanfic_Languages' ) ) {
				Fanfic_Languages::maybe_seed_languages();
			}
			if ( class_exists( 'Fanfic_Taxonomies' ) && taxonomy_exists( 'fanfiction_genre' ) && taxonomy_exists( 'fanfiction_status' ) ) {
				Fanfic_Taxonomies::ensure_default_terms();
			}

			delete_transient( 'fanfic_missing_classification_tables' );
			delete_transient( 'fanfic_skip_classification' );
		}

		set_transient( 'fanfic_classification_rebuild_result', $notice, MINUTE_IN_SECONDS * 5 );

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=fanfiction-settings' );
		wp_safe_redirect( $redirect );
		exit;
	}

















	/**
	 * Run database migrations for version upgrades.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	private static function maybe_run_migrations() {
		$current_version = self::get_db_version();

		// v1.8.0: Email subscriptions no longer require verification.
		if ( version_compare( $current_version, '1.8.0', '<' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'fanfic_email_subscriptions';
			if ( self::verify_table_exists( $table ) ) {
				$wpdb->query( "UPDATE {$table} SET verified = 1 WHERE verified = 0" );
			}
		}

		// v1.9.0: New search index columns require full rebuild.
		if ( version_compare( $current_version, '1.9.0', '<' ) ) {
			set_transient( 'fanfic_index_rebuild_needed', '1', 0 );
		}

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
			$prefix . 'fanfic_story_filter_map',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_chapter_search_index',
			$prefix . 'fanfic_interactions',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_coauthors',
			$prefix . 'fanfic_email_subscriptions',

			$prefix . 'fanfic_reading_progress',
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
	 * @return bool True if all required tables exist, false if any missing.
	 */
	public static function tables_exist() {
		global $wpdb;

		$prefix = $wpdb->prefix;
		$tables = array(
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_coauthors',
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_interactions',
			$prefix . 'fanfic_chapter_search_index',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_story_filter_map',
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
			'total_follows'     => 0,
			'total_reads'         => 0,
			'total_notifications' => 0,
			'total_coauthors'     => 0,
			'total_subscriptions' => 0,
		);

		// Get counts from each table
		$stats['total_ratings'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_interactions WHERE interaction_type = 'rating'"
		);

		$stats['total_likes'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_interactions WHERE interaction_type = 'like'"
		);

		$stats['total_follows'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_interactions WHERE interaction_type = 'follow'"
		);

		$stats['total_reads'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_reading_progress"
		);

		$stats['total_notifications'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_notifications"
		);

		$stats['total_coauthors'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}fanfic_coauthors"
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
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_coauthors',
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_interactions',
			$prefix . 'fanfic_chapter_search_index',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_story_filter_map',
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
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_coauthors',
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_interactions',
			$prefix . 'fanfic_chapter_search_index',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_story_filter_map',
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
			$prefix . 'fanfic_reading_progress',
			$prefix . 'fanfic_email_subscriptions',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_coauthors',
			$prefix . 'fanfic_fandoms',
			$prefix . 'fanfic_story_fandoms',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_interactions',
			$prefix . 'fanfic_chapter_search_index',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_story_filter_map',
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
			$prefix . 'fanfic_story_filter_map',
			$prefix . 'fanfic_story_search_index',
			$prefix . 'fanfic_chapter_search_index',
			$prefix . 'fanfic_interactions',
			$prefix . 'fanfic_story_custom_terms',
			$prefix . 'fanfic_custom_terms',
			$prefix . 'fanfic_custom_taxonomies',
			$prefix . 'fanfic_story_languages',
			$prefix . 'fanfic_story_translations',
			$prefix . 'fanfic_languages',
			$prefix . 'fanfic_story_warnings',
			$prefix . 'fanfic_warnings',
			$prefix . 'fanfic_notifications',
			$prefix . 'fanfic_coauthors',
			$prefix . 'fanfic_email_subscriptions',

			$prefix . 'fanfic_reading_progress',
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
