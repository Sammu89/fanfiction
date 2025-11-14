<?php
/**
 * Migration Runner Script
 *
 * Execute database migration from old to new schema
 *
 * Usage: php run-migration.php
 *
 * @package Fanfiction_Manager
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Check if we're running from command line
if ( php_sapi_name() !== 'cli' ) {
	die( "This script can only be run from command line.\n" );
}

echo "========================================\n";
echo "Fanfiction Manager - Database Migration\n";
echo "========================================\n\n";

// Load migration class
require_once __DIR__ . '/includes/migrations/migrate-to-new-system.php';

echo "Starting migration...\n\n";

// Run migration
$result = Fanfic_Database_Migration::run_migration();

if ( is_wp_error( $result ) ) {
	echo "❌ MIGRATION FAILED!\n";
	echo "Error: " . $result->get_error_message() . "\n\n";
	exit( 1 );
} else {
	echo "\n✅ MIGRATION COMPLETED SUCCESSFULLY!\n\n";

	// Get migration status
	$status = get_option( 'fanfic_migration_status', array() );

	echo "Migration Details:\n";
	echo "- Version: " . ( $status['completed'] ?? 'N/A' ) . "\n";
	echo "- Completed at: " . ( $status['completed_at'] ?? 'N/A' ) . "\n";

	if ( isset( $status['stats'] ) ) {
		echo "\nMigration Statistics:\n";
		foreach ( $status['stats'] as $key => $value ) {
			echo "- " . ucfirst( str_replace( '_', ' ', $key ) ) . ": {$value}\n";
		}
	}

	echo "\n";
	exit( 0 );
}
