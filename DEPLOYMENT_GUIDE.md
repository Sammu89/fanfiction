# Deployment Guide - Fresh Install Approach

## Overview

Since this is a development environment, you can deploy the updated plugin using a **fresh install approach** instead of running database migrations.

---

## Fresh Install Process

### Step 1: Backup (Optional but Recommended)
If you have any test data you want to keep, export it first:
```bash
wp db export fanfiction_backup.sql
```

### Step 2: Deactivate Plugin
```bash
wp plugin deactivate fanfiction
```

Or via WordPress Admin:
- Go to Plugins
- Find "Fanfiction Manager"
- Click "Deactivate"

### Step 3: Drop Old Tables (Optional)
If you want to start completely fresh:
```sql
DROP TABLE IF EXISTS wp_fanfic_ratings;
DROP TABLE IF EXISTS wp_fanfic_likes;
DROP TABLE IF EXISTS wp_fanfic_bookmarks;
DROP TABLE IF EXISTS wp_fanfic_follows;
DROP TABLE IF EXISTS wp_fanfic_reading_progress;
DROP TABLE IF EXISTS wp_fanfic_email_subscriptions;
DROP TABLE IF EXISTS wp_fanfic_subscriptions;
DROP TABLE IF EXISTS wp_fanfic_notifications;
DROP TABLE IF EXISTS wp_fanfic_email_queue;
DROP TABLE IF EXISTS wp_fanfic_reports;
```

Or via WP-CLI:
```bash
wp db query "DROP TABLE IF EXISTS wp_fanfic_ratings, wp_fanfic_likes, wp_fanfic_bookmarks, wp_fanfic_follows, wp_fanfic_reading_progress, wp_fanfic_email_subscriptions, wp_fanfic_subscriptions, wp_fanfic_notifications, wp_fanfic_email_queue, wp_fanfic_reports"
```

### Step 4: Delete Plugin Options (Optional)
```bash
wp option delete fanfic_db_version
wp option delete fanfic_migration_status
```

### Step 5: Update Plugin Code
Make sure you have the latest code from the branch:
```bash
cd wp-content/plugins/fanfiction
git fetch origin
git checkout claude/implement-step-7a-017sLPDvYm9jnihXYypbPBoY
git pull origin claude/implement-step-7a-017sLPDvYm9jnihXYypbPBoY
```

### Step 6: Reactivate Plugin
```bash
wp plugin activate fanfiction
```

Or via WordPress Admin:
- Go to Plugins
- Find "Fanfiction Manager"
- Click "Activate"

### Step 7: Verify New Schema Created
Check that the new schema was created correctly:
```bash
wp db query "SHOW TABLES LIKE 'wp_fanfic%'"
```

Expected tables:
- `wp_fanfic_ratings` ✅
- `wp_fanfic_likes` ✅
- `wp_fanfic_bookmarks` ✅ (with `post_id`, `bookmark_type`)
- `wp_fanfic_follows` ✅ (with `user_id`, `target_id`, `follow_type`)
- `wp_fanfic_reading_progress` ✅ (with `chapter_number`)
- `wp_fanfic_email_subscriptions` ✅
- `wp_fanfic_notifications` ✅
- `wp_fanfic_email_queue` ✅

### Step 8: Verify Schema Structure
```bash
wp db query "SHOW COLUMNS FROM wp_fanfic_bookmarks"
```

Expected columns:
- `id` - Primary key
- `user_id` - User who bookmarked
- `post_id` - Story or chapter ID
- `bookmark_type` - ENUM('story', 'chapter')
- `created_at` - Timestamp

```bash
wp db query "SHOW COLUMNS FROM wp_fanfic_follows"
```

Expected columns:
- `id` - Primary key
- `user_id` - User who is following
- `target_id` - Story or author ID
- `follow_type` - ENUM('story', 'author')
- `email_enabled` - TINYINT(1)
- `created_at` - Timestamp

```bash
wp db query "SHOW COLUMNS FROM wp_fanfic_reading_progress"
```

Expected columns:
- `id` - Primary key
- `user_id` - User ID
- `story_id` - Story ID
- `chapter_number` - Chapter number (not position!)
- `marked_at` - Timestamp

---

## Post-Deployment Verification

### 1. Check Plugin Status
```bash
wp plugin list | grep fanfiction
```
Should show: `active`

### 2. Check Database Version
```bash
wp option get fanfic_db_version
```
Should show: `1.0.15` or higher

### 3. Test Frontend
Visit your site and test:
- [ ] Story pages load correctly
- [ ] Chapter pages load correctly
- [ ] Action buttons appear (follow, bookmark, like, etc.)
- [ ] Rating widget appears
- [ ] No PHP errors

### 4. Test AJAX Endpoints
Open browser console and test a simple interaction:
- Click a star rating
- Click like button
- Click bookmark button
- Check console for errors
- Check network tab for successful responses (200 OK)

### 5. Test Anonymous Interactions
- Log out
- Rate a chapter
- Like a chapter
- Check browser cookies:
  - Should see `fanfic_rate_{chapter_id}`
  - Should see `fanfic_like_{chapter_id}`

### 6. Test Authenticated Interactions
- Log in
- Follow a story (NEW!)
- Bookmark a chapter
- Mark chapter as read (NEW!)
- Check database:
```bash
wp db query "SELECT * FROM wp_fanfic_follows WHERE user_id = YOUR_ID"
wp db query "SELECT * FROM wp_fanfic_reading_progress WHERE user_id = YOUR_ID"
```

---

## Advantages of Fresh Install

✅ **No Migration Needed:** Skips Phase 8 entirely
✅ **Clean State:** No old data or schema conflicts
✅ **Faster:** No migration script execution time
✅ **Simpler:** Just deactivate/reactivate
✅ **Less Risk:** No data migration errors possible
✅ **Development Friendly:** Easy to reset and test

---

## What Happens on Activation?

When you activate the plugin, `Fanfic_Database_Setup::create_tables()` runs automatically:

1. **Creates all tables** with new schema
2. **Sets up indexes** and unique constraints
3. **Configures charset** (utf8mb4_unicode_ci)
4. **Stores DB version** in wp_options
5. **Ready to use** immediately!

No migration script needed because:
- No old data to migrate
- New schema created from scratch
- All code already using new schema (Phases 1-7 complete)

---

## Rollback (If Needed)

If you need to go back to old version:

1. Deactivate current plugin
2. Restore old plugin code
3. Restore database backup:
```bash
wp db import fanfiction_backup.sql
```
4. Reactivate old plugin

---

## Files You Can Ignore

Since you're doing a fresh install, these files are not needed:

- ❌ `run-migration.php` - Not needed
- ❌ `MIGRATION_INSTRUCTIONS.md` - Not needed
- ❌ `includes/migrations/migrate-to-new-system.php` - Not needed (but keep for reference)

These files are only useful if you were migrating an existing production database with real user data.

---

## Final Checklist

- [ ] Code updated to latest branch
- [ ] Old tables dropped (optional)
- [ ] Plugin deactivated
- [ ] Plugin reactivated
- [ ] New schema created successfully
- [ ] All tables have correct structure
- [ ] Frontend works (stories, chapters display)
- [ ] Interactions work (rate, like, bookmark, follow)
- [ ] Anonymous cookies working
- [ ] Authenticated database storage working
- [ ] No errors in PHP/JS logs

---

**You're ready to go!** 🚀

Just deactivate and reactivate the plugin. The new schema will be created automatically.
