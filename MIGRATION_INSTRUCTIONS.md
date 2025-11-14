# Database Migration Execution Instructions

## Phase 8: Data Migration Execution

The migration script is located at: `includes/migrations/migrate-to-new-system.php`

### Option 1: Execute via WordPress Admin (Recommended)

1. Log in to your WordPress admin panel
2. Create a temporary admin page or use WP-CLI:

```php
<?php
// Add to your theme's functions.php temporarily or create an admin page
add_action( 'admin_init', function() {
    if ( isset( $_GET['run_fanfic_migration'] ) && current_user_can( 'manage_options' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'fanfiction/includes/migrations/migrate-to-new-system.php';
        $result = Fanfic_Database_Migration::run_migration();

        if ( is_wp_error( $result ) ) {
            wp_die( 'Migration failed: ' . $result->get_error_message() );
        } else {
            wp_die( 'Migration completed successfully!' );
        }
    }
});
```

Then visit: `your-site.com/wp-admin/?run_fanfic_migration=1`

### Option 2: Execute via WP-CLI

```bash
wp eval-file run-migration.php
```

Or directly:

```bash
wp eval '
require_once "wp-content/plugins/fanfiction/includes/migrations/migrate-to-new-system.php";
$result = Fanfic_Database_Migration::run_migration();
if ( is_wp_error( $result ) ) {
    WP_CLI::error( $result->get_error_message() );
} else {
    WP_CLI::success( "Migration completed!" );
}
'
```

### Option 3: Manual Database Execution

If you prefer to run the SQL manually, here are the key changes:

#### 1. Bookmarks Table
```sql
-- Add new columns
ALTER TABLE wp_fanfic_bookmarks
    ADD COLUMN post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER user_id,
    ADD COLUMN bookmark_type enum('story','chapter') NOT NULL DEFAULT 'story' AFTER post_id;

-- Migrate data
UPDATE wp_fanfic_bookmarks SET post_id = story_id, bookmark_type = 'story' WHERE post_id = 0;

-- Update constraints
ALTER TABLE wp_fanfic_bookmarks DROP INDEX IF EXISTS unique_bookmark;
ALTER TABLE wp_fanfic_bookmarks DROP COLUMN story_id;
ALTER TABLE wp_fanfic_bookmarks ADD UNIQUE KEY unique_bookmark (user_id, post_id, bookmark_type);
```

#### 2. Follows Table
```sql
-- Add new columns
ALTER TABLE wp_fanfic_follows
    ADD COLUMN user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id,
    ADD COLUMN target_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER user_id,
    ADD COLUMN follow_type enum('story','author') NOT NULL DEFAULT 'author' AFTER target_id,
    ADD COLUMN email_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER follow_type;

-- Migrate data
UPDATE wp_fanfic_follows SET user_id = follower_id, target_id = author_id, follow_type = 'author' WHERE user_id = 0;

-- Update constraints
ALTER TABLE wp_fanfic_follows DROP INDEX IF EXISTS unique_follow;
ALTER TABLE wp_fanfic_follows DROP COLUMN follower_id, DROP COLUMN author_id;
ALTER TABLE wp_fanfic_follows ADD UNIQUE KEY unique_follow (user_id, target_id, follow_type);
```

#### 3. Reading Progress Table
```sql
-- This requires more complex migration - recommend using the PHP script
-- The script handles converting single position to multiple chapter reads
```

#### 4. Subscriptions Table
```sql
-- Migrate from wp_fanfic_subscriptions to wp_fanfic_email_subscriptions
INSERT INTO wp_fanfic_email_subscriptions (email, target_id, subscription_type, token, verified, created_at)
SELECT email, story_id, 'story', token, is_active, created_at
FROM wp_fanfic_subscriptions
WHERE NOT EXISTS (
    SELECT 1 FROM wp_fanfic_email_subscriptions e
    WHERE e.email = wp_fanfic_subscriptions.email
    AND e.target_id = wp_fanfic_subscriptions.story_id
);
```

## What the Migration Does

### Tables Affected:
1. **wp_fanfic_bookmarks** - Column rename + type addition
2. **wp_fanfic_follows** - Column rename + type addition
3. **wp_fanfic_reading_progress** - Complete schema change
4. **wp_fanfic_email_subscriptions** - Data migration from old table

### Safety Features:
- ✅ Uses SQL transactions (COMMIT/ROLLBACK)
- ✅ Idempotent (won't run twice)
- ✅ Verification checks after each step
- ✅ Comprehensive error logging
- ✅ Rollback on failure

### After Migration:
The script sets an option: `fanfic_migration_status` with:
- Migration version
- Completion timestamp
- Migration statistics

## Verification

After running the migration, verify:

```sql
-- Check bookmarks structure
SHOW COLUMNS FROM wp_fanfic_bookmarks;
-- Should show: user_id, post_id, bookmark_type, created_at

-- Check follows structure
SHOW COLUMNS FROM wp_fanfic_follows;
-- Should show: id, user_id, target_id, follow_type, email_enabled, created_at

-- Check reading progress
SELECT * FROM wp_fanfic_reading_progress LIMIT 5;
-- Should show: user_id, story_id, chapter_number, marked_at

-- Check migration status
SELECT option_value FROM wp_options WHERE option_name = 'fanfic_migration_status';
```

## Rollback (if needed)

Since you mentioned you'll delete the old database, no rollback is needed. The script includes automatic rollback on errors via SQL transactions.
