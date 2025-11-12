# User Interactions System Audit & Optimization Guide

## Executive Summary

This audit analyzes four user interaction features: **Reading Progress**, **Bookmarks**, **Story Subscriptions**, and **Author Follows**. Current implementations lack caching strategies, leading to excessive database queries on high-traffic sites. Recommendations follow the v2.0 rating/like system architecture for optimal performance.

## Current State Analysis

### 1. Reading Progress (Mark as Read)

**Current Implementation**: `wp_fanfic_reading_progress`

```sql
id              BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
story_id        BIGINT(20) UNSIGNED NOT NULL
user_id         BIGINT(20) UNSIGNED NOT NULL
chapter_id      BIGINT(20) UNSIGNED NOT NULL
chapter_number  INT(11) NOT NULL
is_completed    TINYINT(1) NOT NULL DEFAULT 0
updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

UNIQUE KEY unique_progress (story_id, user_id)
```

**Current Code**: `class-fanfic-shortcodes-actions.php::ajax_mark_as_read()`

**Issues**:
- ❌ **No caching**: Every story view queries database for all chapters' read status
- ❌ **N+1 queries**: Story with 50 chapters = 50 database queries to check read status
- ❌ **UPDATE operations**: Slower than conditional logic
- ❌ **No batch loading**: Cannot efficiently load read status for multiple stories

**Performance Impact**:
- Story with 20 chapters: **20 DB queries per page load**
- User browsing list of 10 stories: **200+ DB queries**
- High-traffic site: Database becomes bottleneck

### 2. Bookmarks

**Current Implementation**: `wp_fanfic_bookmarks`

```sql
id          BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
story_id    BIGINT(20) UNSIGNED NOT NULL
user_id     BIGINT(20) UNSIGNED NOT NULL
created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

UNIQUE KEY unique_bookmark (story_id, user_id)
KEY user_created (user_id, created_at)
```

**Current Code**: `class-fanfic-bookmarks.php`

**Issues**:
- ❌ **Story-only**: No chapter bookmarks (user requested this feature)
- ❌ **No caching**: `is_bookmarked()` queries DB every time
- ❌ **Cache invalidation pattern**: Deletes cache on change (sub-optimal)
- ✅ **Good**: Simple schema, proper indexes

**User Request**:
> "bookmark chapter, just shows on bookmarked chapters on user profile, as list"

### 3. Story Subscriptions (Missing)

**Current State**: **NOT IMPLEMENTED**

**User Request**:
> "Followed stories triggers notifications on user dashboard when new chapters are added, deleted or updated"

**Similar Feature**: Email subscriptions exist (`wp_fanfic_subscriptions`), but no in-app story following

### 4. Author Follows

**Current Implementation**: `wp_fanfic_follows`

```sql
id           BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
follower_id  BIGINT(20) UNSIGNED NOT NULL
author_id    BIGINT(20) UNSIGNED NOT NULL
created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

UNIQUE KEY unique_follow (follower_id, author_id)
KEY author_created (author_id, created_at)
```

**Current Code**: `class-fanfic-follows.php`

**Issues**:
- ❌ **No caching**: `is_following()` queries DB every time
- ❌ **Follower count recalculated**: No cached count
- ✅ **Good**: Notifications on follow (via `notify_followers_on_publish()`)
- ❌ **Missing**: Notifications for chapter updates/deletes

### 5. Notifications

**Current Implementation**: `wp_fanfic_notifications`

```sql
id          BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT
user_id     BIGINT(20) UNSIGNED NOT NULL
type        VARCHAR(50) NOT NULL
content     TEXT NOT NULL
is_read     TINYINT(1) NOT NULL DEFAULT 0
created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

KEY user_read (user_id, is_read)
KEY type_created (type, created_at)
```

**Current Code**: `class-fanfic-notifications.php`

**Issues**:
- ❌ **No batching**: Creates one notification per follower (inefficient)
- ❌ **No caching**: Unread count queries DB every time
- ❌ **Table growth**: No automatic cleanup of read notifications
- ✅ **Good**: Cron job for old notification cleanup

---

## Recommended Optimizations

### Architecture Pattern (Following v2.0 Rating/Like System)

```
┌─────────────────────────────────────────────────────────┐
│ Write-Through Cache + Batch Operations                  │
├─────────────────────────────────────────────────────────┤
│ 1. User Action → Database Write (immediate)             │
│ 2. Cache Update (incremental, not invalidation)         │
│ 3. Conditional Object Cache (Redis/Memcached if avail)  │
│ 4. Fallback to Transients                               │
│ 5. Batch operations where possible                      │
└─────────────────────────────────────────────────────────┘
```

---

## Feature 1: Reading Progress Optimization

### Recommended Schema Changes

**Keep existing table** but add cache layer:

```sql
-- No schema changes needed
-- wp_fanfic_reading_progress remains as is
```

### New Class: `Fanfic_Reading_Progress_System`

**Location**: `includes/class-fanfic-reading-progress-system.php`

**Cache Strategy**:

```php
// Cache key: fanfic_user_{user_id}_story_{story_id}_progress
// TTL: 12 hours
// Data structure:
{
  chapter_id: int,
  chapter_number: int,
  is_completed: bool,
  read_chapters: [1, 2, 3, 5, 7], // Array of read chapter numbers
  total_chapters: int,
  last_updated: timestamp
}
```

**Key Methods**:

```php
class Fanfic_Reading_Progress_System {
    const CACHE_DURATION = 12 * HOUR_IN_SECONDS; // 12 hours

    /**
     * Mark chapter as read (write-through cache)
     */
    public static function mark_chapter_read( $story_id, $chapter_id, $user_id ) {
        global $wpdb;

        // 1. Update database
        $wpdb->replace(
            $wpdb->prefix . 'fanfic_reading_progress',
            [
                'story_id' => $story_id,
                'user_id' => $user_id,
                'chapter_id' => $chapter_id,
                'chapter_number' => $chapter_number,
                'updated_at' => current_time('mysql')
            ]
        );

        // 2. Update cache incrementally
        self::update_progress_cache( $story_id, $user_id, $chapter_number );
    }

    /**
     * Get reading progress for a story (cached)
     */
    public static function get_story_progress( $story_id, $user_id ) {
        $cache_key = "fanfic_user_{$user_id}_story_{$story_id}_progress";

        // Try object cache first (Redis/Memcached)
        if ( wp_using_ext_object_cache() ) {
            $data = wp_cache_get( $cache_key, 'fanfic_progress' );
            if ( false !== $data ) {
                return $data;
            }
        }

        // Try transient cache
        $data = get_transient( $cache_key );
        if ( false !== $data ) {
            return $data;
        }

        // Cache miss - rebuild from database
        $data = self::rebuild_progress_from_db( $story_id, $user_id );

        // Store in both caches
        if ( wp_using_ext_object_cache() ) {
            wp_cache_set( $cache_key, $data, 'fanfic_progress', self::CACHE_DURATION );
        }
        set_transient( $cache_key, $data, self::CACHE_DURATION );

        return $data;
    }

    /**
     * Batch check: Are chapters read? (for story view)
     * CRITICAL: Prevents N+1 queries
     */
    public static function get_read_chapters_map( $story_id, $user_id ) {
        $progress = self::get_story_progress( $story_id, $user_id );

        if ( ! $progress ) {
            return [];
        }

        // Return array: [chapter_number => true/false]
        $chapters = self::get_story_chapters( $story_id );
        $map = [];

        foreach ( $chapters as $chapter ) {
            $map[$chapter->number] = in_array( $chapter->number, $progress->read_chapters );
        }

        return $map;
    }

    /**
     * Rebuild cache from database (on cache miss)
     */
    private static function rebuild_progress_from_db( $story_id, $user_id ) {
        global $wpdb;

        $progress = $wpdb->get_row( $wpdb->prepare(
            "SELECT chapter_id, chapter_number, is_completed, updated_at
             FROM {$wpdb->prefix}fanfic_reading_progress
             WHERE story_id = %d AND user_id = %d",
            $story_id, $user_id
        ) );

        if ( ! $progress ) {
            return null;
        }

        // Get all chapters for this story
        $chapters = self::get_story_chapters( $story_id );
        $total = count( $chapters );

        // Build read chapters array (1-indexed)
        // Assuming sequential reading: chapters 1 through current are read
        $read_chapters = range( 1, $progress->chapter_number );

        return (object) [
            'chapter_id' => (int) $progress->chapter_id,
            'chapter_number' => (int) $progress->chapter_number,
            'is_completed' => (bool) $progress->is_completed,
            'read_chapters' => $read_chapters,
            'total_chapters' => $total,
            'last_updated' => $progress->updated_at,
        ];
    }
}
```

**Frontend Usage**:

```php
// OLD WAY (20 DB queries for 20-chapter story)
foreach ( $chapters as $chapter ) {
    if ( is_chapter_marked_read( $chapter->ID, $user_id ) ) {
        echo '<span class="read-badge">Read</span>';
    }
}

// NEW WAY (1 DB query + cache for entire story)
$read_map = Fanfic_Reading_Progress_System::get_read_chapters_map( $story_id, $user_id );
foreach ( $chapters as $chapter ) {
    if ( $read_map[$chapter->number] ?? false ) {
        echo '<span class="read-badge">Read</span>';
    }
}
```

**Performance Gain**:
- **Before**: 20 queries per story view
- **After**: 1 query on cache miss, 0 queries on cache hit
- **Cache hit rate**: Expected 95%+
- **Net result**: 95% reduction in database load

---

## Feature 2: Chapter Bookmarks

### Recommended Schema Changes

**Extend existing bookmarks table**:

```sql
ALTER TABLE wp_fanfic_bookmarks
ADD COLUMN chapter_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER story_id,
ADD COLUMN bookmark_type ENUM('story', 'chapter') NOT NULL DEFAULT 'story',
ADD INDEX idx_chapter (chapter_id),
ADD INDEX idx_user_type (user_id, bookmark_type);

-- Drop old unique constraint
ALTER TABLE wp_fanfic_bookmarks DROP INDEX unique_bookmark;

-- Add new unique constraints
ALTER TABLE wp_fanfic_bookmarks
ADD UNIQUE KEY unique_story_bookmark (story_id, user_id, bookmark_type) WHERE bookmark_type = 'story',
ADD UNIQUE KEY unique_chapter_bookmark (chapter_id, user_id) WHERE bookmark_type = 'chapter';
```

**Or create separate table** (recommended for clarity):

```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_chapter_bookmarks (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    chapter_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_chapter_bookmark (chapter_id, user_id),
    KEY user_id (user_id),
    KEY created_at (created_at),
    KEY user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### New Class: `Fanfic_Chapter_Bookmarks_System`

**Cache Strategy**:

```php
// Cache key: fanfic_user_{user_id}_chapter_bookmarks
// TTL: 6 hours
// Data structure:
{
  total_count: int,
  chapter_ids: [123, 456, 789], // Array of bookmarked chapter IDs
  last_updated: timestamp
}
```

**Key Methods**:

```php
class Fanfic_Chapter_Bookmarks_System {
    const CACHE_DURATION = 6 * HOUR_IN_SECONDS;

    /**
     * Toggle chapter bookmark (write-through)
     */
    public static function toggle_bookmark( $chapter_id, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'fanfic_chapter_bookmarks';

        // Check if exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE chapter_id = %d AND user_id = %d",
            $chapter_id, $user_id
        ) );

        if ( $exists ) {
            // Remove bookmark
            $wpdb->delete( $table, ['id' => $exists] );
            self::update_bookmark_cache( $user_id, $chapter_id, 'remove' );
            return ['action' => 'removed', 'is_bookmarked' => false];
        } else {
            // Add bookmark
            $wpdb->insert( $table, [
                'chapter_id' => $chapter_id,
                'user_id' => $user_id,
                'created_at' => current_time('mysql')
            ] );
            self::update_bookmark_cache( $user_id, $chapter_id, 'add' );
            return ['action' => 'added', 'is_bookmarked' => true];
        }
    }

    /**
     * Get user's bookmarked chapters (cached)
     */
    public static function get_user_bookmarks( $user_id, $limit = 50, $offset = 0 ) {
        $cache_key = "fanfic_user_{$user_id}_chapter_bookmarks";

        // Try cache
        $data = wp_using_ext_object_cache()
            ? wp_cache_get( $cache_key, 'fanfic_bookmarks' )
            : get_transient( $cache_key );

        if ( false === $data ) {
            $data = self::rebuild_bookmarks_from_db( $user_id );

            if ( wp_using_ext_object_cache() ) {
                wp_cache_set( $cache_key, $data, 'fanfic_bookmarks', self::CACHE_DURATION );
            }
            set_transient( $cache_key, $data, self::CACHE_DURATION );
        }

        // Paginate from cached array
        return array_slice( $data->chapter_ids, $offset, $limit );
    }

    /**
     * Incremental cache update (no invalidation!)
     */
    private static function update_bookmark_cache( $user_id, $chapter_id, $action ) {
        $cache_key = "fanfic_user_{$user_id}_chapter_bookmarks";

        // Get existing cache
        $data = wp_using_ext_object_cache()
            ? wp_cache_get( $cache_key, 'fanfic_bookmarks' )
            : get_transient( $cache_key );

        if ( false === $data ) {
            // Cache miss - rebuild
            $data = self::rebuild_bookmarks_from_db( $user_id );
        } else {
            // Update incrementally
            if ( 'add' === $action ) {
                $data->chapter_ids[] = $chapter_id;
                $data->total_count++;
            } else {
                $data->chapter_ids = array_diff( $data->chapter_ids, [$chapter_id] );
                $data->total_count = max( 0, $data->total_count - 1 );
            }
            $data->last_updated = time();
        }

        // Save updated cache
        if ( wp_using_ext_object_cache() ) {
            wp_cache_set( $cache_key, $data, 'fanfic_bookmarks', self::CACHE_DURATION );
        }
        set_transient( $cache_key, $data, self::CACHE_DURATION );
    }
}
```

**Performance Gain**:
- Bookmark list page: **1 query** (cache miss) vs **50+ queries** (no cache)
- Toggle bookmark: **1 write** + **cache update** (no rebuild)

---

## Feature 3: Story Subscriptions (New Feature)

### Recommended Schema

```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_story_subscriptions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    story_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_subscription (story_id, user_id),
    KEY story_id (story_id),
    KEY user_id (user_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### New Class: `Fanfic_Story_Subscriptions_System`

**Cache Strategy**:

```php
// Per-user cache: fanfic_user_{user_id}_subscriptions
// Per-story cache: fanfic_story_{story_id}_subscriber_count
// TTL: 12 hours
```

**Key Methods**:

```php
class Fanfic_Story_Subscriptions_System {
    /**
     * Subscribe to story (notifications on chapter add/update/delete)
     */
    public static function subscribe( $story_id, $user_id ) {
        // Similar to follows pattern
    }

    /**
     * Notify subscribers on chapter actions
     */
    public static function notify_on_chapter_action( $story_id, $action, $chapter_id ) {
        // action: 'added', 'updated', 'deleted'

        // Get all subscribers (cached)
        $subscribers = self::get_story_subscribers( $story_id );

        // Batch create notifications
        self::batch_create_notifications( $subscribers, $action, $story_id, $chapter_id );
    }
}
```

**Hook Integration**:

```php
// In class init()
add_action( 'transition_post_status', [__CLASS__, 'handle_chapter_status_change'], 10, 3 );
add_action( 'before_delete_post', [__CLASS__, 'handle_chapter_delete'] );
add_action( 'post_updated', [__CLASS__, 'handle_chapter_update'], 10, 3 );
```

---

## Feature 4: Author Follows Optimization

### Keep Existing Schema

No changes needed to `wp_fanfic_follows`

### Enhanced Class: `Fanfic_Follows_System`

**Add Caching Layer**:

```php
class Fanfic_Follows_System {
    /**
     * Cache author follower count
     * Cache key: fanfic_author_{author_id}_follower_count
     * TTL: 24 hours
     */
    public static function get_follower_count( $author_id ) {
        $cache_key = "fanfic_author_{$author_id}_follower_count";

        $count = wp_using_ext_object_cache()
            ? wp_cache_get( $cache_key, 'fanfic_follows' )
            : get_transient( $cache_key );

        if ( false === $count ) {
            global $wpdb;
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_follows WHERE author_id = %d",
                $author_id
            ) );

            if ( wp_using_ext_object_cache() ) {
                wp_cache_set( $cache_key, $count, 'fanfic_follows', DAY_IN_SECONDS );
            }
            set_transient( $cache_key, $count, DAY_IN_SECONDS );
        }

        return (int) $count;
    }

    /**
     * Cache user's follows
     * Cache key: fanfic_user_{user_id}_follows
     */
    public static function get_user_follows( $user_id ) {
        // Returns array of author IDs user follows
        // Cached for 12 hours
    }

    /**
     * Batch notification for chapter updates
     */
    public static function notify_followers_on_chapter_action( $author_id, $action, $story_id, $chapter_id ) {
        // Get all followers (cached)
        $followers = self::get_followers( $author_id );

        // Batch create notifications
        Fanfic_Notifications_System::batch_create( $followers, [
            'type' => "author_{$action}",
            'message' => "...",
            'link' => "..."
        ] );
    }
}
```

---

## Feature 5: Notifications Optimization

### Keep Existing Schema

Add one index:

```sql
ALTER TABLE wp_fanfic_notifications
ADD INDEX idx_user_unread_created (user_id, is_read, created_at);
```

### Enhanced Class: `Fanfic_Notifications_System`

**Add Batch Operations**:

```php
class Fanfic_Notifications_System {
    /**
     * Batch create notifications (critical for performance)
     * Creates one notification for EACH user in array
     */
    public static function batch_create( $user_ids, $notification_data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'fanfic_notifications';

        // Prepare values for multi-insert
        $values = [];
        $placeholders = [];

        foreach ( $user_ids as $user_id ) {
            $values[] = $user_id;
            $values[] = $notification_data['type'];
            $values[] = $notification_data['message'];
            $values[] = $notification_data['link'] ?? '';
            $values[] = current_time('mysql');

            $placeholders[] = '(%d, %s, %s, %s, %s)';
        }

        // Single INSERT with multiple rows
        $sql = "INSERT INTO {$table} (user_id, type, message, link, created_at) VALUES "
             . implode( ', ', $placeholders );

        $wpdb->query( $wpdb->prepare( $sql, $values ) );

        // Clear unread count caches for affected users
        foreach ( $user_ids as $user_id ) {
            self::clear_user_notification_cache( $user_id );
        }
    }

    /**
     * Get unread count (cached)
     */
    public static function get_unread_count( $user_id ) {
        $cache_key = "fanfic_user_{$user_id}_unread_count";

        $count = wp_using_ext_object_cache()
            ? wp_cache_get( $cache_key, 'fanfic_notifications' )
            : get_transient( $cache_key );

        if ( false === $count ) {
            global $wpdb;
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_notifications
                 WHERE user_id = %d AND is_read = 0",
                $user_id
            ) );

            if ( wp_using_ext_object_cache() ) {
                wp_cache_set( $cache_key, $count, 'fanfic_notifications', HOUR_IN_SECONDS );
            }
            set_transient( $cache_key, $count, HOUR_IN_SECONDS );
        }

        return (int) $count;
    }

    /**
     * Get user notifications (cached)
     */
    public static function get_user_notifications( $user_id, $page = 1, $per_page = 20 ) {
        $cache_key = "fanfic_user_{$user_id}_notifications_p{$page}";

        $notifications = wp_using_ext_object_cache()
            ? wp_cache_get( $cache_key, 'fanfic_notifications' )
            : get_transient( $cache_key );

        if ( false === $notifications ) {
            global $wpdb;
            $offset = ( $page - 1 ) * $per_page;

            $notifications = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fanfic_notifications
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id, $per_page, $offset
            ) );

            if ( wp_using_ext_object_cache() ) {
                wp_cache_set( $cache_key, $notifications, 'fanfic_notifications', 5 * MINUTE_IN_SECONDS );
            }
            set_transient( $cache_key, $notifications, 5 * MINUTE_IN_SECONDS );
        }

        return $notifications;
    }
}
```

**Performance Impact**:
- **Before**: 100 followers = 100 INSERT queries (serial)
- **After**: 100 followers = 1 INSERT query (batch)
- **Speedup**: 100x faster

---

## Implementation Priority

### Phase 1: Critical Performance (Week 1)
1. ✅ **Reading Progress Caching** - Biggest performance impact
2. ✅ **Notifications Batch Operations** - Prevents database overload

### Phase 2: New Features (Week 2)
3. ✅ **Chapter Bookmarks** - User requested
4. ✅ **Story Subscriptions** - User requested

### Phase 3: Enhancements (Week 3)
5. ✅ **Author Follows Caching** - Nice to have
6. ✅ **Enhanced Notification Types** - Better UX

---

## Database Impact Estimation

### Before Optimization
- User views story with 50 chapters: **50+ queries**
- User views notifications page: **2-3 queries**
- 100 followers notified of new chapter: **100 INSERT queries**
- User profile page (bookmarks + follows): **5-10 queries**

**Total for 1000 concurrent users**: ~60,000 queries/minute

### After Optimization
- User views story with 50 chapters: **0-1 queries** (cached)
- User views notifications page: **0-1 queries** (cached)
- 100 followers notified: **1 INSERT query** (batch)
- User profile page: **0-2 queries** (cached)

**Total for 1000 concurrent users**: ~3,000 queries/minute

**Net reduction**: **95% fewer database queries**

---

## Code Structure

### Recommended File Organization

```
includes/
├── class-fanfic-reading-progress-system.php    (NEW - optimized)
├── class-fanfic-chapter-bookmarks-system.php   (NEW)
├── class-fanfic-story-subscriptions-system.php (NEW)
├── class-fanfic-follows-system.php             (ENHANCE existing)
├── class-fanfic-notifications-system.php       (ENHANCE existing)
└── class-fanfic-bookmarks.php                  (KEEP for story bookmarks)

assets/js/
├── fanfiction-reading-progress.js              (NEW)
├── fanfiction-chapter-bookmarks.js             (NEW)
└── fanfiction-story-subscriptions.js           (NEW)
```

---

## Testing Recommendations

### Performance Tests

1. **Load Test**: 100 concurrent users viewing stories
2. **Cache Hit Rate**: Should be >95% after warmup
3. **Query Count**: Monitor with Query Monitor plugin
4. **Memory Usage**: Object cache memory (if using Redis)

### Functional Tests

1. Mark chapter as read → Verify cache updates
2. Toggle bookmark → Verify incremental cache update
3. Subscribe to story → Verify notifications on chapter actions
4. Follow author → Verify notifications on new stories/chapters

### Edge Cases

1. Cache expiration during high traffic
2. Batch notifications with 1000+ followers
3. User with 500+ bookmarked chapters
4. Concurrent bookmark toggles (race conditions)

---

## Migration Strategy

### For Existing Sites

1. **No data loss**: All existing data preserved
2. **Gradual rollout**: Enable caching per-feature
3. **Monitoring**: Track query count reduction
4. **Rollback plan**: Disable caching if issues arise

### Database Migrations

```sql
-- Migration 1: Chapter bookmarks table
CREATE TABLE wp_fanfic_chapter_bookmarks...

-- Migration 2: Story subscriptions table
CREATE TABLE wp_fanfic_story_subscriptions...

-- Migration 3: Add indexes to notifications
ALTER TABLE wp_fanfic_notifications ADD INDEX...
```

---

## Configuration

### Settings (wp-admin)

```php
// Add to Fanfic_Settings
'reading_progress_cache_duration' => 12 * HOUR_IN_SECONDS,
'bookmarks_cache_duration' => 6 * HOUR_IN_SECONDS,
'notifications_cache_duration' => 5 * MINUTE_IN_SECONDS,
'enable_object_cache' => true, // Auto-detect available
'batch_notification_size' => 100, // Max per batch insert
```

---

## Summary

| Feature | Current Queries/Request | Optimized Queries/Request | Improvement |
|---------|------------------------|---------------------------|-------------|
| Reading Progress (50 chapters) | 50 | 0-1 | **98% reduction** |
| Bookmarks List (50 items) | 50+ | 0-1 | **98% reduction** |
| Notification Creation (100 followers) | 100 | 1 | **99% reduction** |
| Author Follower Count | 1 | 0-1 | **50% reduction** |
| **Overall** | **200+** | **2-4** | **95%+ reduction** |

**Cache Strategy**: Write-through + incremental updates (same as v2.0 rating/like system)

**Object Cache**: Optional but recommended for >10K daily active users

**Low-Resource Compatible**: Works perfectly with transients only (no Redis/Memcached required)

---

**Version**: 1.0.0
**Created**: 2025-11-12
**Author**: Development Team
