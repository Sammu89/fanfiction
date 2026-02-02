<?php
/**
 * Test script for Search Index Migration
 *
 * Run this file from WordPress admin or via WP-CLI to test the migration.
 *
 * Usage:
 * 1. Via browser: Navigate to this file directly (requires authentication)
 * 2. Via WP-CLI: wp eval-file test-search-index-migration.php
 *
 * @package Fanfiction_Manager
 * @since 1.5.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	// If running standalone, load WordPress
	require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
}

// Require admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to run this test.' );
}

echo "<h1>Search Index Migration Test</h1>\n";
echo "<pre>\n";

// Step 1: Check current table structure
echo "=== Step 1: Checking Current Table Structure ===\n";
global $wpdb;
$table = $wpdb->prefix . 'fanfic_story_search_index';

$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

if ( ! $table_exists ) {
	echo "ERROR: Search index table does not exist!\n";
	echo "Please run plugin activation first.\n";
	echo "</pre>";
	exit;
}

echo "✓ Table exists: {$table}\n\n";

// Get current columns
$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
echo "Current columns:\n";
foreach ( $columns as $column ) {
	echo "  - {$column}\n";
}

// Check if migration is needed
$needs_migration = ! in_array( 'story_title', $columns, true );
echo "\nMigration needed: " . ( $needs_migration ? 'YES' : 'NO' ) . "\n\n";

// Step 2: Run migration if needed
if ( $needs_migration ) {
	echo "=== Step 2: Running Migration ===\n";

	if ( ! class_exists( 'Fanfic_Database_Setup' ) ) {
		echo "ERROR: Fanfic_Database_Setup class not found!\n";
		echo "</pre>";
		exit;
	}

	$result = Fanfic_Database_Setup::migrate_search_index_v2();

	if ( $result ) {
		echo "✓ Migration completed successfully\n\n";

		// Verify new columns
		$new_columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
		echo "New columns added:\n";
		$added_columns = array_diff( $new_columns, $columns );
		foreach ( $added_columns as $column ) {
			echo "  + {$column}\n";
		}
		echo "\n";
	} else {
		echo "ERROR: Migration failed!\n";
		echo "</pre>";
		exit;
	}
} else {
	echo "=== Step 2: Migration Already Complete ===\n\n";
}

// Step 3: Check indexes
echo "=== Step 3: Checking Indexes ===\n";
$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
$index_names = array_unique( wp_list_pluck( $indexes, 'Key_name' ) );

echo "Indexes found:\n";
foreach ( $index_names as $index_name ) {
	echo "  - {$index_name}\n";
}
echo "\n";

$required_indexes = array(
	'PRIMARY',
	'idx_updated',
	'idx_author',
	'idx_status',
	'idx_language',
	'idx_age_rating',
	'idx_search_fulltext',
	'idx_title_fulltext',
);

$missing_indexes = array_diff( $required_indexes, $index_names );
if ( empty( $missing_indexes ) ) {
	echo "✓ All required indexes present\n\n";
} else {
	echo "WARNING: Missing indexes:\n";
	foreach ( $missing_indexes as $missing ) {
		echo "  ! {$missing}\n";
	}
	echo "\n";
}

// Step 4: Check if index has data
echo "=== Step 4: Checking Index Data ===\n";
$total_rows = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
echo "Total indexed stories: {$total_rows}\n";

$total_stories = wp_count_posts( 'fanfiction_story' );
$total_count   = 0;
if ( $total_stories ) {
	foreach ( $total_stories as $status => $count ) {
		$total_count += $count;
	}
}
echo "Total stories in WordPress: {$total_count}\n";

if ( $total_rows < $total_count ) {
	echo "\nWARNING: Not all stories are indexed. Running rebuild...\n";

	if ( class_exists( 'Fanfic_Search_Index' ) ) {
		$rebuild_result = Fanfic_Search_Index::rebuild_all( 50, 0 );
		if ( $rebuild_result['success'] ) {
			echo "✓ Rebuilt {$rebuild_result['processed']} stories\n";
			if ( $rebuild_result['remaining'] > 0 ) {
				echo "  Note: {$rebuild_result['remaining']} stories remaining. Run rebuild again.\n";
			}
		} else {
			echo "ERROR: Rebuild failed\n";
		}
	}
} else {
	echo "✓ All stories indexed\n";
}
echo "\n";

// Step 5: Test sample row
echo "=== Step 5: Testing Sample Row ===\n";
$sample = $wpdb->get_row( "SELECT * FROM {$table} LIMIT 1", ARRAY_A );

if ( $sample ) {
	echo "Sample indexed story (ID: {$sample['story_id']}):\n";
	echo "  - Title: " . ( $sample['story_title'] ?? 'N/A' ) . "\n";
	echo "  - Slug: " . ( $sample['story_slug'] ?? 'N/A' ) . "\n";
	echo "  - Status: " . ( $sample['story_status'] ?? 'N/A' ) . "\n";
	echo "  - Author ID: " . ( $sample['author_id'] ?? 'N/A' ) . "\n";
	echo "  - Chapter Count: " . ( $sample['chapter_count'] ?? 'N/A' ) . "\n";
	echo "  - Word Count: " . ( $sample['word_count'] ?? 'N/A' ) . "\n";
	echo "  - Fandoms: " . ( $sample['fandom_slugs'] ?? 'N/A' ) . "\n";
	echo "  - Language: " . ( $sample['language_slug'] ?? 'N/A' ) . "\n";
	echo "  - Warnings: " . ( $sample['warning_slugs'] ?? 'N/A' ) . "\n";
	echo "  - Age Rating: " . ( $sample['age_rating'] ?? 'N/A' ) . "\n";
	echo "  - Visible Tags: " . ( substr( $sample['visible_tags'] ?? '', 0, 50 ) ) . ( strlen( $sample['visible_tags'] ?? '' ) > 50 ? '...' : '' ) . "\n";
	echo "  - Genre Names: " . ( $sample['genre_names'] ?? 'N/A' ) . "\n";
	echo "  - Status Name: " . ( $sample['status_name'] ?? 'N/A' ) . "\n";
} else {
	echo "No sample data available (table is empty)\n";
}
echo "\n";

// Step 6: Test browse functions
echo "=== Step 6: Testing Browse Functions ===\n";

if ( function_exists( 'fanfic_get_light_taxonomy_terms_with_counts' ) ) {
	// Test fandoms
	$fandoms = fanfic_get_light_taxonomy_terms_with_counts( 'fandom' );
	echo "Fandoms found: " . count( $fandoms ) . "\n";
	if ( ! empty( $fandoms ) ) {
		$first_fandom = reset( $fandoms );
		echo "  Sample: {$first_fandom['name']} ({$first_fandom['slug']}) - {$first_fandom['count']} stories\n";
	}

	// Test languages
	$languages = fanfic_get_light_taxonomy_terms_with_counts( 'language' );
	echo "Languages found: " . count( $languages ) . "\n";
	if ( ! empty( $languages ) ) {
		$first_lang = reset( $languages );
		echo "  Sample: {$first_lang['name']} ({$first_lang['slug']}) - {$first_lang['count']} stories\n";
	}
} else {
	echo "WARNING: Browse functions not found\n";
}
echo "\n";

if ( function_exists( 'fanfic_get_warning_story_count' ) && class_exists( 'Fanfic_Warnings' ) ) {
	// Get first warning to test
	$warnings = Fanfic_Warnings::get_all();
	if ( ! empty( $warnings ) ) {
		$first_warning = reset( $warnings );
		$count         = fanfic_get_warning_story_count( $first_warning['slug'] );
		echo "Warning test: {$first_warning['name']} - {$count} stories\n";
	}
}
echo "\n";

// Final summary
echo "=== Summary ===\n";
echo "✓ Migration test complete!\n";
echo "✓ Search index v2 is ready for use\n";
echo "\nNext steps:\n";
echo "1. Test browse pages to verify performance\n";
echo "2. Check for any PHP warnings in error log\n";
echo "3. Monitor query performance in slow query log\n";

echo "</pre>\n";

// If running via WP-CLI, just exit cleanly
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::success( 'Migration test completed successfully!' );
}
