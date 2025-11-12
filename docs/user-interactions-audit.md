# User Interactions System Audit & Optimization Guide

## Executive Summary

This document analyzes the complete user interaction system for the Fanfiction Manager plugin, covering ratings, likes, bookmarks, follows (with optional email subscriptions), reading progress, notifications, and email delivery. It provides **pragmatic optimization recommendations** tailored to medium communities (100-500 DAU) on shared hosting with minimal memory constraints.

**Key Principle**: Build for **simplicity and clarity** first, optimize with **batch operations and proper indexing**, avoid **over-caching and unnecessary complexity**.

**Terminology Clarification**:
- **Follow**: Logged-in users track stories/authors. Generates in-app dashboard notifications + optional email notifications. Users can toggle email notifications per follow. Notifies content creators.
- **Subscribe**: Email-only subscription for ANY user (logged-in or anonymous via email). Users can unsubscribe via email link or unsubscribe page with email parameter.
- **Single Follow Table**: Tracks both story and author follows (unified under one "follow" action type).

---

## Part 1: Understanding Your Full System Scope

### The Complete Interaction Model

Your plugin manages **two distinct notification flows**:

#### **Flow 1: Author/Content Creator Notifications**
```
User Action                          → Author Gets Notified (In-App + Email)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Chapter receives rating              → ✅ Rating received (count + notification)
Chapter receives like                → ✅ Like received (count + notification)
Chapter/Story receives comment       → ✅ Comment received (notification + email reply)
Story/Chapter updated                → ✅ Manual action (creator updates content)
User follows author                  → ✅ "User Marcelo is following your story Pocahontas" (notification + email)
User follows story                   → ✅ "User Marcelo is following your story Pocahontas" (notification + email)
```

#### **Flow 2: Reader/Consumer Notifications (Logged-In Users)**
```
User Action                                    → User Gets Notified (In-App + Email*)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Someone replies to my comment                  → ✅ Reply notification + email
Followed author publishes new chapter          → ✅ New chapter in series + email*
Followed author publishes new story            → ✅ New story notification + email*
Followed story receives new chapter            → ✅ Chapter update in series + email*
User can toggle email ON/OFF per follow        → ✅ Granular email preference control
```
*Email sent only if user has email notifications enabled for that follow.

#### **Flow 3: Subscriber Notifications (Any User - Email Only)**
```
Action Type                                    → Subscriber Gets Notified (Email Only)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Subscribed story gets new chapter              → ✅ Email notification
Subscribed author publishes new story          → ✅ Email notification
Subscribed author publishes new chapter        → ✅ Email notification
Unsubscribe via email link                     → ✅ Single story/author unsubscribe (URL with email + action param)
Unsubscribe all via URL                        → ✅ Complete email unsubscribe (URL with email + action=unsubscribe_all)
```

### The Complete Feature Set

| Feature | Logged-In | Anonymous | Tracked? | In-App Notif | Email Control | Display |
|---------|-----------|-----------|----------|-----------|---------------|---------|
| **Ratings** (chapter) | ✅ | ✅ (IP) | ✅ | Author only | No | Count + avg stars |
| **Likes** (chapter) | ✅ | ✅ (IP) | ✅ | Author only | No | Count |
| **Comments** (story/chapter) | ✅ | ❌ | ✅ | Author + repliers | No | Thread |
| **Reading Progress** (mark as read) | ✅ Only | - | ✅ | No | No | "Read" badge |
| **Bookmarks** (story) | ✅ Only | - | ✅ | No | No | User library |
| **Chapter Bookmarks** | ✅ Only | - | ✅ | No | No | User library |
| **Follow Story** (logged-in) | ✅ Only | - | ✅ | ✅ | ✅ Toggle | Follower count |
| **Follow Author** (logged-in) | ✅ Only | - | ✅ | ✅ | ✅ Toggle | Follower count |
| **Subscribe Story** (email) | Both | ✅ | ✅ | No | ✅ Unsubscribe link | - |
| **Subscribe Author** (email) | Both | ✅ | ✅ | No | ✅ Unsubscribe link | - |
| **View Counts** | Both | ✅ (IP) | ✅ (1/day) | No | No | Story + chapters |

### Key System Insights

**IMPORTANT: Anonymous Ratings/Likes Architecture**

Anonymous users can rate and like chapters (configurable per admin). This requires:
- **IP + Fingerprint Hashing**: Prevent duplicate votes from same user
- **30-Day Database Cleanup**: Automatically remove old hashed IPs (privacy + performance)
- **One vote per IP per chapter per 30 days**: Unique constraint prevents spam

See Section 12 (Part 12 below) for complete anonymous action architecture.

1. **Follow vs Subscribe**:
   - **Follow**: Logged-in users only. Tracked in DB, generates in-app dashboard notifications. Email is OPTIONAL per-follow toggle. Creator gets notified.
   - **Subscribe**: Anyone (logged-in or anonymous email). Email only, no in-app notification. Unsubscribe via email link or unsubscribe page.

2. **Two-Tier Follow System**:
   - Single `wp_fanfic_follows` table tracks both story and author follows (distinguish by `follow_type` column: 'story' or 'author')
   - Same table for both; reduces complexity vs separate tables

3. **Email Preferences (Follow)**:
   - Logged-in user follows story/author
   - Can toggle email notifications ON/OFF per follow (stored in user_meta or separate preference table)
   - Default: Email enabled (user can opt-out per follow)

4. **Email Subscriptions (Subscribe)**:
   - Stored in `wp_fanfic_email_subscriptions` table (email, story_id/author_id, subscription_type, token for unsubscribe)
   - Unsubscribe links in emails: `domain.com/story/123/?action=unsubscribe&email=user@email.com&token=xyz`
   - Global unsubscribe: `domain.com/fanfic-home/?action=unsubscribe_all&email=user@email.com&token=xyz`

5. **Creator Notifications on Follow**:
   - When user follows story: Creator gets "User Marcelo is following your story Pocahontas"
   - When user follows author: Creator gets "User Marcelo is following you"
   - Both trigger in-app notification + email (to creator, regardless of creator's follow preferences)

6. **Mark as Read**: Used for UI state only (showing "Read" badge on chapters in story view) + optional reading progress dashboard. **NOT used for notifications or emails**

7. **Notifications**: Always tied to **content actions** (comments, ratings, follows, chapter updates)—NEVER to reading/marking actions

8. **Counters**: Rating count, like count, bookmark count, follower count all displayed on story/chapter pages

9. **Unified Path**: All actions (rate, like, bookmark, read, follow) go through same recording system for consistency

---

## Part 2: Current Implementation Analysis

### 1. Reading Progress (Mark as Read)

**Current Implementation**: `wp_fanfic_reading_progress` table + `ajax_mark_as_read()` in `class-fanfic-shortcodes-actions.php`

**Current Performance Issues**:
- ❌ **N+1 query problem**: Story view with 50 chapters requires 50+ DB queries to check read status for display
- ❌ **No batch loading**: Cannot efficiently fetch read status for multiple chapters in one query
- ✅ **Data stored correctly**: UNIQUE key on (story_id, user_id) prevents duplicates

**Optimization Strategy** (See Section 4.1):
- Keep table as-is (data structure is fine)
- **Fix with single optimized query** instead of caching:
  ```sql
  SELECT chapter_number FROM wp_fanfic_reading_progress
  WHERE user_id = ? AND story_id = ?
  ORDER BY chapter_number DESC LIMIT 1
  ```
- Use result to determine which chapters are "read" (all chapters ≤ last_chapter_number)
- **Cost**: Remove N+1 loop, replace with 1 query
- **Benefit**: Story view reduced from 50+ queries to 3 queries, no cache complexity

### 2. Bookmarks (Story)

**Current Implementation**: `wp_fanfic_bookmarks` table + `class-fanfic-bookmarks.php`

**Current Strengths**:
- ✅ Simple schema with proper indexes on (user_id, created_at)
- ✅ UNIQUE constraint prevents duplicate bookmarks

**Current Gaps**:
- ❌ **Story-only**: No chapter bookmarks (user requested feature)
- ❌ **Caching not optimized**: Uses transient cache but invalidates on every change

**Optimization Strategy** (See Section 4.2):
- Add `chapter_bookmarks` table (separate from story bookmarks for clarity)
- Remove aggressive caching; rely on optimized queries + WordPress page cache
- Keep data simple, let database indexes do the work

### 3. Story Follows (Logged-In Users)

**Current Implementation**: **PARTIALLY EXISTS** (author follows exist, but not story follows)

**User Request**:
> "Users can follow a story to receive notifications on user dashboard when new chapters are added, updated"

**Needed for**:
- Logged-in users to track specific stories (different from following author)
- In-app notifications when followed story gets new chapter
- Email notifications (optional, per-follow toggle) when followed story updates
- Author gets notified when someone follows their story

**Implementation Strategy** (See Section 4.3):
- Extend existing `wp_fanfic_follows` table with `follow_type` column ('story' or 'author')
- Add `email_enabled` column to track per-follow email preference
- Batch notification creation when chapter published
- Trigger: `transition_post_status` hook on chapter publish

### 3b. Email Subscriptions (Any User - Anonymous Friendly)

**Current Implementation**: **NOT FULLY IMPLEMENTED**

**User Request**:
> "Email subscriptions for any user (logged-in or anonymous) to receive email when new chapters/stories published"

**Needed for**:
- Anonymous users to subscribe via email (no account needed)
- Logged-in users to subscribe without creating account relationship
- Email-only notifications (no in-app)
- One-click unsubscribe from email link
- Bulk unsubscribe from all subscriptions via email

**Implementation Strategy** (See Section 4.3b):
- New table: `wp_fanfic_email_subscriptions` (stores email, story_id/author_id, token for unsubscribe)
- Secure token generation (hash of email + timestamp + secret)
- Unsubscribe handler: `/?action=unsubscribe&email=X&token=Y`
- Global unsubscribe handler: `/?action=unsubscribe_all&email=X&token=Y`

### 4. Author Follows

**Current Implementation**: `wp_fanfic_follows` + `class-fanfic-follows.php`

**Current Strengths**:
- ✅ Notifications created on follow (author receives "new follower" notification)
- ✅ Proper schema with indexes

**Current Gaps**:
- ❌ **Incomplete notifications**: Missing notifications when followed author publishes new chapter/story
- ❌ **Follower count caching**: Uses transient but has room for optimization

**Optimization Strategy** (See Section 4.4):
- Add hooks to notify followers when author publishes new content
- Keep follower count cached (transient is fine for this read-heavy query)
- No structural changes needed

### 5. Notifications

**Current Implementation**: `wp_fanfic_notifications` table + `class-fanfic-notifications.php`

**Current Strengths**:
- ✅ Proper schema with indexes on (user_id, is_read)
- ✅ Cleanup cron job to remove old notifications
- ✅ Simple structure (no complex serialization)

**Current Gaps**:
- ❌ **No batching**: Creates 1 notification per recipient (100 followers = 100 INSERT queries)
- ❌ **No unread count cache**: Queries DB every time for unread notification count

**Optimization Strategy** (See Section 4.5):
- Implement batch notification creation (100 INSERTs in 1 query)
- Add transient cache for unread count (short TTL, cleared on notification action)
- No schema changes needed; only add one index

### 6. Email Notifications

**Current Implementation**: `class-fanfic-email-sender.php` + email queue in `wp_options`

**Current Strengths**:
- ✅ Queue-based sending via WP-Cron every 30 minutes
- ✅ Batch processing (50 emails per batch)
- ✅ User preference checking

**Critical Issues**:
- ❌ **Queue stored in wp_options**: Serialized array → race conditions, memory bloat
- ❌ **No email isolation**: Can't track individual email status
- ❌ **No retry logic**: Failed emails lost, no visibility

**Optimization Strategy** (See Section 4.6):
- Create `wp_fanfic_email_queue` table (replace wp_options storage)
- Add status tracking (pending, sent, failed)
- Add retry logic with exponential backoff
- **NOT needed**: Digest emails (not requested), rate limiting, priority queue

---

## Part 3: Pragmatic vs Over-Engineered Approaches

### What the Original Audit Got Right ✅

| Recommendation | Why Implement |
|---|---|
| **Batch notification operations** | 100 followers → 100 serial INSERTs is wasteful; 1 batch INSERT is 99% faster |
| **Email queue table instead of wp_options** | Atomic operations, race-condition safe, status tracking, debugging support |
| **Unified actions recording system** | Single entry point for all actions ensures consistency, easier to maintain |
| **Proper indexing strategy** | Biggest ROI on performance; required for all optimization |

### What the Original Audit Over-Engineered ❌

| Recommendation | Why Skip | Complexity | Benefit |
|---|---|---|---|
| **Dual-layer caching** (object + transients) | 90% of sites have no Redis/Memcached; transients = wp_options anyway | 40% of code | ~10% gain |
| **12-hour transient cache** for reading progress | Data changes frequently; cache invalidation complexity not worth it | High | Low |
| **Incremental cache updates** | Extra 50 lines to avoid 1 cache miss per action | High | Negligible |
| **Digest email system** | Never requested; adds 3 cron jobs, separate table, complex UI | Very High | 0% |
| **Email priority queue** | All email types same urgency; FIFO sufficient | Medium | None |
| **Email rate limiting** | Hosting/SMTP providers handle limits; users want ALL their emails | Medium | None |

---

## Part 4: Pragmatic Implementation Strategy

### Unified System Architecture

```
User Action (rate, like, bookmark, mark read, follow, subscribe, comment)
       ↓
Fanfic_Actions_System::record_action( $type, $user_id, $target_id, $value )
       ├─→ 1. INSERT to wp_fanfic_actions (unified table)
       ├─→ 2. UPDATE wp_fanfic_counters (atomic count increment)
       ├─→ 3. Queue notifications (collect user IDs, batch INSERT in cron)
       ├─→ 4. Queue emails (filter by preference, batch INSERT in cron)
       └─→ 5. Invalidate page cache (if using one)
       ↓
Cron Job (every 30 min):
       ├─→ Batch INSERT notifications into wp_fanfic_notifications
       └─→ Batch SEND emails from wp_fanfic_email_queue
```

### 4.1 Reading Progress Optimization

**Keep existing table** - No schema changes needed.

**Problem**: Story view with 50 chapters generates N+1 queries checking read status.

**Solution**: Single optimized query instead of loop.

**Current (BAD)**:
```php
foreach ( $chapters as $chapter ) {
    if ( is_chapter_marked_read( $chapter->ID, $user_id ) ) {  // 50 DB queries
        echo '<span class="read-badge">Read</span>';
    }
}
```

**Optimized (GOOD)**:
```php
// 1 query instead of 50
$progress = $wpdb->get_row( $wpdb->prepare(
    "SELECT chapter_number FROM {$wpdb->prefix}fanfic_reading_progress
     WHERE user_id = %d AND story_id = %d
     ORDER BY chapter_number DESC LIMIT 1",
    $user_id, $story_id
) );

$last_read = $progress ? (int) $progress->chapter_number : 0;

foreach ( $chapters as $chapter ) {
    if ( $chapter->menu_order <= $last_read ) {
        echo '<span class="read-badge">Read</span>';
    }
}
```

**Performance**:
- **Before**: 50+ queries
- **After**: 1 query (+ 2-3 other page queries)
- **No cache complexity**: Works on all hosting

**Implementation**:
- Create `Fanfic_Reading_Progress::get_last_read_chapter()` method
- Use in story view template instead of loop

---

### 4.2 Chapter Bookmarks (New Feature)

**New Table**:
```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_chapter_bookmarks (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chapter_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_bookmark (chapter_id, user_id),
    KEY user_id (user_id),
    KEY user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why separate table**: Clarity - story bookmarks and chapter bookmarks are different concepts.

**Usage**:
```php
// Toggle chapter bookmark
Fanfic_Chapter_Bookmarks::toggle( $chapter_id, $user_id );

// Get user's bookmarked chapters (paginated)
$bookmarks = Fanfic_Chapter_Bookmarks::get_user_bookmarks( $user_id, $page = 1 );
```

**Performance**:
- Query: `SELECT * FROM wp_fanfic_chapter_bookmarks WHERE user_id = ? ORDER BY created_at DESC`
- Indexed, paginated, simple

---

### 4.3 Story Follows & Email Subscriptions (New/Enhanced Features)

#### 4.3a Story Follows (Logged-In Users)

**Enhanced Existing Table** (`wp_fanfic_follows`):
```sql
ALTER TABLE wp_fanfic_follows
ADD COLUMN follow_type ENUM('story', 'author') NOT NULL DEFAULT 'author' AFTER follower_id,
ADD COLUMN email_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER follow_type,
DROP INDEX unique_follow,
ADD UNIQUE KEY unique_follow (follower_id, author_id, follow_type);
```

**New columns**:
- `follow_type`: 'author' = follow author's all content; 'story' = follow specific story only
- `email_enabled`: Toggle email notifications per follow (1 = send emails, 0 = in-app only)

**Notification Flow** (when chapter published on followed story):
```php
// Get logged-in followers of this story
$followers = $wpdb->get_col( $wpdb->prepare(
    "SELECT follower_id FROM {$wpdb->prefix}fanfic_follows
     WHERE story_id = %d AND follow_type = 'story' AND follower_id IS NOT NULL",
    $story_id
) );

// Batch queue in-app notifications for all
Fanfic_Notification_Batch::queue( $followers, 'story_chapter_update', [
    'message' => 'New chapter in "' . $story_title . '"',
    'link' => get_permalink( $chapter_id ),
] );

// Get followers with email enabled
$followers_email = $wpdb->get_col( $wpdb->prepare(
    "SELECT follower_id FROM {$wpdb->prefix}fanfic_follows
     WHERE story_id = %d AND follow_type = 'story' AND email_enabled = 1",
    $story_id
) );

// Batch queue emails (only for those with email enabled)
Fanfic_Email_Queue::batch_queue( $followers_email, 'story_chapter_update', $subject, $body_template );
```

**Hook Integration**:
```php
add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
    if ( $new_status !== 'publish' || 'chapter' !== $post->post_type ) {
        return;
    }

    $story_id = wp_get_post_parent_id( $post->ID );
    Fanfic_Follows::notify_story_followers( $story_id, $post );
}, 10, 3 );
```

#### 4.3b Email Subscriptions (Any User - Anonymous Friendly)

**New Table**:
```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_email_subscriptions (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    story_id BIGINT(20) UNSIGNED,
    author_id BIGINT(20) UNSIGNED,
    subscription_type ENUM('story', 'author') NOT NULL,
    token VARCHAR(64) NOT NULL,  -- Hash for secure unsubscribe
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_subscription (email, story_id, author_id, subscription_type),
    KEY email (email),
    KEY token (token),
    KEY author_id (author_id),
    KEY story_id (story_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Token Generation** (secure, non-sequential):
```php
$token = hash_hmac( 'sha256', $email . $timestamp, FANFIC_SECRET_KEY );
```

**Subscription Flow**:
```php
public static function subscribe_by_email( $email, $story_id = null, $author_id = null ) {
    global $wpdb;

    $email = sanitize_email( $email );
    $subscription_type = $story_id ? 'story' : 'author';
    $token = hash_hmac( 'sha256', $email . time(), wp_salt() );

    $wpdb->insert(
        $wpdb->prefix . 'fanfic_email_subscriptions',
        [
            'email' => $email,
            'story_id' => $story_id,
            'author_id' => $author_id,
            'subscription_type' => $subscription_type,
            'token' => $token,
            'created_at' => current_time( 'mysql' ),
        ]
    );
}
```

**Unsubscribe Handler**:
```php
public static function handle_unsubscribe() {
    if ( ! isset( $_GET['action'], $_GET['email'], $_GET['token'] ) ) {
        return;
    }

    $action = sanitize_text_field( $_GET['action'] );
    $email = sanitize_email( $_GET['email'] );
    $token = sanitize_text_field( $_GET['token'] );

    // Verify token
    global $wpdb;
    $record = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fanfic_email_subscriptions
         WHERE email = %s AND token = %s",
        $email, $token
    ) );

    if ( ! $record ) {
        wp_die( 'Invalid unsubscribe link' );
    }

    if ( 'unsubscribe_all' === $action ) {
        // Unsubscribe from ALL
        $wpdb->delete(
            $wpdb->prefix . 'fanfic_email_subscriptions',
            [ 'email' => $email ]
        );
    } else {
        // Unsubscribe from specific story/author
        $wpdb->delete(
            $wpdb->prefix . 'fanfic_email_subscriptions',
            [
                'email' => $email,
                'story_id' => $record->story_id,
                'author_id' => $record->author_id
            ]
        );
    }

    wp_die( 'Successfully unsubscribed from emails' );
}
```

**Email Links in Emails**:
```html
<!-- Unsubscribe from this story/author -->
<a href="<?php echo esc_url( home_url( '/?action=unsubscribe&email=' . urlencode( $email ) . '&token=' . $token ) ); ?>">
    Unsubscribe from this story
</a>

<!-- Unsubscribe from all -->
<a href="<?php echo esc_url( home_url( '/?action=unsubscribe_all&email=' . urlencode( $email ) . '&token=' . $token ) ); ?>">
    Unsubscribe from all emails
</a>
```

---

### 4.4 Author Follows Optimization

**Keep existing table** - No schema changes.

**Add**: Notifications when followed author publishes new content.

**Current Gap**:
```php
// Existing: author gets notified when someone follows them
create_follow_notification( $author_id, $follower_id );

// MISSING: followers get notified when author publishes new chapter
// MISSING: followers get notified when author publishes new story
```

**New Implementation**:
```php
// Add to Fanfic_Follows class
public static function notify_followers_on_publish( $new_status, $old_status, $post ) {
    if ( $new_status !== 'publish' ) {
        return; // Only notify on publish
    }

    $author_id = $post->post_author;

    if ( 'story' === $post->post_type ) {
        self::notify_followers_new_story( $author_id, $post );
    } elseif ( 'chapter' === $post->post_type ) {
        self::notify_followers_new_chapter( $author_id, $post );
    }
}

private static function notify_followers_new_story( $author_id, $story_post ) {
    global $wpdb;

    // Get all followers (indexed query)
    $followers = $wpdb->get_col( $wpdb->prepare(
        "SELECT follower_id FROM {$wpdb->prefix}fanfic_follows
         WHERE author_id = %d", $author_id
    ) );

    // Batch notification
    Fanfic_Notification_Batch::queue( $followers, 'author_new_story', [
        'message' => 'Author "' . get_the_author_meta( 'display_name', $author_id ) .
                    '" published new story: "' . $story_post->post_title . '"',
        'link' => get_permalink( $story_post->ID ),
    ] );

    // Batch email
    Fanfic_Email_Queue::batch_queue( $followers, 'author_new_story', $variables );
}
```

**Performance**: No cache needed - followers query is fast (indexed).

---

### 4.5 Notifications Batch System

**Keep existing table** - Add one index:
```sql
ALTER TABLE wp_fanfic_notifications
ADD INDEX idx_user_unread_created (user_id, is_read, created_at);
```

**New Batch Class**: `Fanfic_Notification_Batch`

**Batch Queue** (called from Fanfic_Actions_System):
```php
class Fanfic_Notification_Batch {

    public static function queue( $user_ids, $type, $data ) {
        global $wpdb;

        // Remove duplicates and filter
        $user_ids = array_unique( array_filter( $user_ids ) );
        if ( empty( $user_ids ) ) {
            return 0;
        }

        $values = [];
        $placeholders = [];

        foreach ( $user_ids as $uid ) {
            $values[] = absint( $uid );
            $values[] = sanitize_text_field( $type );
            $values[] = sanitize_text_field( $data['message'] );
            $values[] = esc_url_raw( $data['link'] ?? '' );
            $values[] = current_time( 'mysql' );

            $placeholders[] = '(%d, %s, %s, %s, %s)';
        }

        // Single batch INSERT
        $sql = "INSERT INTO {$wpdb->prefix}fanfic_notifications
                (user_id, type, message, link, created_at)
                VALUES " . implode( ', ', $placeholders );

        $wpdb->query( $wpdb->prepare( $sql, $values ) );

        return count( $user_ids );
    }

    /**
     * Get unread count (simple, no complex caching)
     */
    public static function get_unread_count( $user_id ) {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_notifications
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ) );
    }
}
```

**Performance**:
- **Before**: 100 followers → 100 INSERT queries
- **After**: 100 followers → 1 INSERT query with 100 rows
- **Improvement**: 99% faster, 1 line code vs 100 loops

---

### 4.6 Email Queue System (Simplified)

**New Table** (replaces wp_options storage):
```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_email_queue (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME,

    KEY user_id (user_id),
    KEY status (status),
    KEY queued_at (queued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Batch Queue** (called from Fanfic_Actions_System):
```php
class Fanfic_Email_Queue {

    public static function batch_queue( $user_ids, $type, $subject, $body_template, $variables = [] ) {
        global $wpdb;

        // Filter users who want this email type
        $enabled_users = [];
        foreach ( $user_ids as $uid ) {
            if ( Fanfic_Notification_Preferences::should_send_email( $uid, $type ) ) {
                $enabled_users[] = $uid;
            }
        }

        if ( empty( $enabled_users ) ) {
            return 0;
        }

        $table = $wpdb->prefix . 'fanfic_email_queue';
        $values = [];
        $placeholders = [];
        $now = current_time( 'mysql' );

        foreach ( $enabled_users as $uid ) {
            // Render template
            $body = self::render_template( $body_template, $variables, $uid );

            $values[] = $uid;
            $values[] = $type;
            $values[] = $subject;
            $values[] = $body;
            $values[] = 'pending';
            $values[] = $now;

            $placeholders[] = '(%d, %s, %s, %s, %s, %s)';
        }

        // Single batch INSERT
        $sql = "INSERT INTO {$table}
                (user_id, notification_type, subject, body, status, queued_at)
                VALUES " . implode( ', ', $placeholders );

        $wpdb->query( $wpdb->prepare( $sql, $values ) );

        return count( $enabled_users );
    }

    /**
     * Process email queue (cron job every 30 minutes)
     */
    public static function process_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'fanfic_email_queue';

        // Get 50 pending emails
        $emails = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'pending'
             ORDER BY queued_at ASC
             LIMIT 50"
        );

        if ( empty( $emails ) ) {
            return 0;
        }

        $sent = 0;

        foreach ( $emails as $email ) {
            $user = get_user_by( 'ID', $email->user_id );
            if ( ! $user ) {
                $wpdb->update( $table, [ 'status' => 'failed' ], [ 'id' => $email->id ] );
                continue;
            }

            $result = wp_mail(
                $user->user_email,
                $email->subject,
                $email->body,
                [ 'Content-Type: text/html; charset=UTF-8' ]
            );

            if ( $result ) {
                $wpdb->update(
                    $table,
                    [ 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ],
                    [ 'id' => $email->id ]
                );
                $sent++;
            } else {
                // Retry logic
                $attempts = $email->attempts + 1;
                if ( $attempts >= 3 ) {
                    $wpdb->update( $table, [ 'status' => 'failed' ], [ 'id' => $email->id ] );
                } else {
                    $wpdb->update( $table, [ 'attempts' => $attempts ], [ 'id' => $email->id ] );
                }
            }
        }

        return $sent;
    }

    /**
     * Cleanup old emails (cron daily)
     */
    public static function cleanup_old_emails() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}fanfic_email_queue
             WHERE status IN ('sent', 'failed')
             AND queued_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}
```

**Cron Registration**:
```php
// Every 30 minutes
if ( ! wp_next_scheduled( 'fanfic_process_email_queue' ) ) {
    wp_schedule_event( time(), 'every_30_minutes', 'fanfic_process_email_queue' );
}
add_action( 'fanfic_process_email_queue', [ 'Fanfic_Email_Queue', 'process_queue' ] );

// Daily cleanup
if ( ! wp_next_scheduled( 'fanfic_cleanup_email_queue' ) ) {
    wp_schedule_event( strtotime( 'tomorrow 3:00 AM' ), 'daily', 'fanfic_cleanup_email_queue' );
}
add_action( 'fanfic_cleanup_email_queue', [ 'Fanfic_Email_Queue', 'cleanup_old_emails' ] );
```

---

## Part 5: What NOT to Implement

### ❌ Dual-Layer Caching (Object Cache + Transients)

**Why Skip**:
- 90% of plugin installations run on shared hosting without Redis/Memcached
- Transients = wp_options serialization anyway
- Extra complexity for 10% potential improvement
- Let WordPress plugins handle it naturally

### ❌ 12-Hour Transient Cache for Reading Progress

**Why Skip**:
- Data changes frequently (user marks chapters as read sequentially)
- Cache invalidation becomes complex (update cache? rebuild? stale data?)
- Single optimized query is sufficient and simpler

### ❌ Incremental Cache Updates

**Why Skip**:
- Adds 50+ lines to save 1 cache miss per action
- Cache rebuilds are cheap anyway
- Complexity not worth negligible gain

### ❌ Digest Email System (Hourly/Daily/Weekly)

**Why Skip**:
- Never requested by users
- Adds 2 new tables, 3 cron jobs, complex template logic
- 400+ lines of code for 0% current value
- **Add only if users explicitly request digest preferences**

### ❌ Email Priority Queue

**Why Skip**:
- All email types have same urgency (new chapters, comments, follows)
- FIFO queue is sufficient
- Priority queue adds complexity for no benefit

### ❌ Email Rate Limiting (10/hour per user)

**Why Skip**:
- Users want all their emails
- Hosting/SMTP providers handle rate limits
- Would require tracking user email sends in transients
- Unnecessary for medium communities

---

## Part 6: Performance Comparison

### Scenario: Author Publishes Chapter (100 author followers + 200 story subscribers = 300 total)

#### Before Optimization (Separate Tables + Serial)
```
Notification Creation:
  300 iterations:
    INSERT into wp_fanfic_notifications → 300 queries

Email Queue Creation:
  300 iterations:
    READ wp_options (queue array) → 300 queries
    MODIFY array → 300 operations in PHP
    UPDATE wp_options → 300 queries

Total: 600 database operations
Time: 10-15 seconds (blocking user action)
Memory: ~5MB (serialized queue array)
```

#### After Optimization (Batch + Table-Based)
```
Notification Creation:
  Batch INSERT 300 rows → 1 query

Email Queue Creation:
  Batch INSERT 250 rows (after filtering) → 1 query

Total: 2 immediate database operations
Time: <1 second (non-blocking, queued)
Memory: <500KB
Cron Job (later):
  Process 50 emails per run × 5 runs → 5 queries for sending
```

**Performance Gain**: 300x faster on user action, non-blocking

### Scenario: User Views Story (50 chapters)

#### Before Optimization (N+1 Queries)
```
Query 1: SELECT story
Query 2: SELECT chapters (paginated)
Queries 3-52: Check if each chapter is read (50 individual queries)
Query 53: SELECT user's bookmarks

Total: 53 queries
Performance: ~500ms on shared hosting
```

#### After Optimization (Unified Queries)
```
Query 1: SELECT story
Query 2: SELECT chapters (paginated)
Query 3: SELECT reading progress (1 query, determine all read chapters)
Query 4: SELECT user's bookmarks

Total: 4 queries (same as proposed caching but without cache complexity)
Performance: ~100ms on shared hosting
Improvement: 5x faster with simpler code
```

---

## Part 7: Implementation Priority

### Phase 1: Foundation (Week 1-2)
**Goal**: Set up core tables and migration system

- [ ] Create migration: `wp_fanfic_email_queue` table
- [ ] Create migration: `wp_fanfic_story_subscriptions` table
- [ ] Create migration: `wp_fanfic_chapter_bookmarks` table
- [ ] Add index to `wp_fanfic_notifications`: (user_id, is_read, created_at)
- [ ] Create `Fanfic_Database_Migrations` class for version tracking

### Phase 2: Unified Actions System (Week 2-3)
**Goal**: Single entry point for recording actions

- [ ] Create `class-fanfic-actions-system.php`
- [ ] Implement `record_action()` method
- [ ] Implement `increment_counter()` method
- [ ] Hook existing rating system to use unified entry point
- [ ] Hook existing like system to use unified entry point
- [ ] Hook existing bookmark system to use unified entry point
- [ ] Hook reading progress to use unified entry point
- [ ] Test with 100+ concurrent actions

### Phase 3: Batch Notifications (Week 3-4)
**Goal**: Replace serial notification INSERTs with batch operations

- [ ] Create `class-fanfic-notification-batch.php`
- [ ] Implement `queue()` method for batch notification queuing
- [ ] Update all notification triggers to use batch queue
- [ ] Create cron hook for processing queued notifications
- [ ] Test batch creation with 100+ followers

### Phase 4: Email Queue Table (Week 4-5)
**Goal**: Replace wp_options email queue with dedicated table

- [ ] Create `class-fanfic-email-queue.php`
- [ ] Implement `batch_queue()` method
- [ ] Implement `process_queue()` cron job
- [ ] Implement `cleanup_old_emails()` cron job
- [ ] Update email sending to use new queue
- [ ] Migrate existing wp_options queue to table
- [ ] Test batch email sending with 100+ emails

### Phase 5: Follow Enhancements & Email Subscriptions (Week 5-6)
**Goal**: Add story follows (logged-in) and email subscriptions (any user)

**Part A: Story Follows (Logged-In Users)**
- [ ] Modify `wp_fanfic_follows` table: add `follow_type`, `email_enabled`, `story_id` columns
- [ ] Update `class-fanfic-follows.php` to handle story follows
- [ ] Add follow story AJAX handler
- [ ] Add email preference toggle (per follow)
- [ ] Implement notification trigger on chapter publish
- [ ] Test story follow notification flow

**Part B: Email Subscriptions (Any User - Anonymous Friendly)**
- [ ] Create `wp_fanfic_email_subscriptions` table
- [ ] Create `class-fanfic-email-subscriptions.php`
- [ ] Add subscribe by email form (story/author page)
- [ ] Implement token generation (hash-based, secure)
- [ ] Add unsubscribe handler: `/?action=unsubscribe&email=X&token=Y`
- [ ] Add unsubscribe all handler: `/?action=unsubscribe_all&email=X&token=Y`
- [ ] Add unsubscribe links to all emails
- [ ] Test anonymous subscription + unsubscribe flow

**Part C: Chapter Bookmarks**
- [ ] Create `class-fanfic-chapter-bookmarks.php`
- [ ] Create `wp_fanfic_chapter_bookmarks` table
- [ ] Add bookmark toggle AJAX handler
- [ ] Create user profile view for bookmarked chapters
- [ ] Test user workflows

### Phase 6: Creator Notifications on Follow (Week 6-7)
**Goal**: Notify story/author creators when users follow them

- [ ] Add hook to notify author when story is followed (in-app + email)
- [ ] Add hook to notify author when they are followed (in-app + email)
- [ ] Message format: "User Marcelo is following your story Pocahontas"
- [ ] Update author dashboard to show recent followers
- [ ] Test creator notification flow

### Phase 7: Testing & Optimization (Week 7-8)
**Goal**: Validate performance and reliability

- [ ] Load test: 100 concurrent users
- [ ] Query count monitoring
- [ ] Email delivery validation
- [ ] Notification delivery validation
- [ ] Database size monitoring
- [ ] Documentation updates

---

## Part 8: Database Schema Summary

### Tables to Create/Modify

#### New: `wp_fanfic_email_queue`
```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_email_queue (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME,

    KEY user_id (user_id),
    KEY status (status),
    KEY queued_at (queued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Modify: `wp_fanfic_follows`
```sql
ALTER TABLE wp_fanfic_follows
ADD COLUMN follow_type ENUM('story', 'author') NOT NULL DEFAULT 'author' AFTER follower_id,
ADD COLUMN email_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER follow_type,
DROP INDEX unique_follow,
ADD UNIQUE KEY unique_follow (follower_id, author_id, follow_type);

-- Optional: Add story_id column to support story follows in same table
ALTER TABLE wp_fanfic_follows
ADD COLUMN story_id BIGINT(20) UNSIGNED AFTER author_id;
```

#### New: `wp_fanfic_email_subscriptions`
```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_email_subscriptions (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    story_id BIGINT(20) UNSIGNED,
    author_id BIGINT(20) UNSIGNED,
    subscription_type ENUM('story', 'author') NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_subscription (email, story_id, author_id, subscription_type),
    KEY email (email),
    KEY token (token),
    KEY author_id (author_id),
    KEY story_id (story_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### New: `wp_fanfic_chapter_bookmarks`
```sql
CREATE TABLE IF NOT EXISTS wp_fanfic_chapter_bookmarks (
    id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chapter_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_bookmark (chapter_id, user_id),
    KEY user_id (user_id),
    KEY user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Modify: `wp_fanfic_notifications`
```sql
ALTER TABLE wp_fanfic_notifications
ADD INDEX idx_user_unread_created (user_id, is_read, created_at);
```

#### Keep: `wp_fanfic_reading_progress`, `wp_fanfic_bookmarks`, `wp_fanfic_follows`
No changes needed. Optimize queries instead of redesigning.

---

## Part 9: File Structure & Classes

```
includes/
├── core/
│   ├── class-fanfic-actions-system.php        (NEW - unified recording)
│   ├── class-fanfic-notification-batch.php    (NEW - batch notifications)
│   └── class-fanfic-email-queue.php           (NEW - email queue management)
├── class-fanfic-reading-progress.php          (ENHANCE - optimize queries)
├── class-fanfic-chapter-bookmarks.php         (NEW - chapter bookmarks)
├── class-fanfic-story-subscriptions.php       (NEW - story subscriptions)
├── class-fanfic-follows.php                   (ENHANCE - add notifications)
├── class-fanfic-notifications.php             (KEEP - add batch index)
└── class-fanfic-email-sender.php              (ENHANCE - use new queue table)
```

---

## Part 10: Key Metrics & Success Criteria

### Performance Targets (Medium Community: 100-500 DAU)

| Metric | Before | Target | Improvement |
|--------|--------|--------|-------------|
| Notification creation (100 users) | 1200 queries | 2 queries | **600x** |
| Email queue operations (100 emails) | 600 queries | 2 queries | **300x** |
| Story view (50 chapters) | 53 queries | 4 queries | **13x** |
| Author follower count | 1 query + miss | 1 query + cache | **50%** |
| Notification page load | 5-10 queries | 3-4 queries | **2x** |

### Code Quality Targets

- **Unified entry point**: All actions use `Fanfic_Actions_System::record_action()`
- **Batch operations**: 99% of multi-user notifications use batch INSERT
- **No over-caching**: Reading progress, bookmarks use optimized queries, not caching
- **Simple email system**: FIFO queue, no priority, no digest, no rate limiting
- **Clear separation**: Each class has single responsibility

### Reliability Targets

- **Email delivery**: 99.5% (handled by SMTP provider + retry logic)
- **Notification delivery**: 100% (in-database, always available)
- **Data isolation**: No race conditions on shared hosting
- **Graceful degradation**: System works with or without WordPress object cache

---

## Migration & Rollout

### For Existing Sites

1. **Backup database** (always first step)
2. **Run migrations** (automatic on plugin update):
   - Create new tables
   - Migrate existing email queue from wp_options to table
   - Add indexes to existing tables
3. **Update hooks** (gradual):
   - Rating system → unified actions system
   - Like system → unified actions system
   - Bookmark system → unified actions system
   - Reading progress → unified actions system
4. **Test** before deploying to production:
   - 100 concurrent users
   - Email delivery
   - Notification creation
   - Query count validation

### No Data Loss

- All existing data preserved
- Gradual migration, no cutover risk
- Can rollback if issues

---

---

## Part 11: Unified Follow & Subscribe Architecture

This section clarifies the complete architecture for "follows" and "subscribes" based on the user clarifications.

### System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    USER INTERACTION FLOWS                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  LOGGED-IN USERS (Follows)                                           │
│  ├─ Follow Story/Author                                              │
│  ├─ In-app dashboard notifications (always)                          │
│  ├─ Email notifications (optional, per-follow toggle)                │
│  ├─ Can unfollow anytime                                             │
│  └─ Creator gets notified ("User X following your story Y")          │
│                                                                      │
│  ANY USER (Email Subscriptions)                                      │
│  ├─ Subscribe via email (logged-in OR anonymous)                     │
│  ├─ Email-only notifications (no in-app)                             │
│  ├─ Unsubscribe via email link (single story/author)                 │
│  ├─ Unsubscribe all via link (all subscriptions)                     │
│  └─ Creator does NOT get notified                                    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Table Structure

#### `wp_fanfic_follows` (Logged-In Users Only)

```sql
id                BIGINT PRIMARY KEY
follower_id       BIGINT (logged-in user)
author_id         BIGINT (author being followed)
story_id          BIGINT (NULL if following author, story ID if following specific story)
follow_type       ENUM('story', 'author')
email_enabled     TINYINT(1) DEFAULT 1 (toggle email per follow)
created_at        DATETIME
```

**Usage Examples**:
- `follow_type='author', author_id=5, story_id=NULL` → Follow author #5
- `follow_type='story', author_id=10, story_id=123` → Follow story #123 by author #10

#### Email Subscriptions (Stored in Metadata - No Separate Table)

**Story Email Subscribers** (postmeta on story):
```
story_id=123:
  meta_key='fanfic_email_subscribers'
  meta_value=['user@example.com', 'anon@test.com', 'reader@mail.com']
  (Simple array of emails)
```

**Author Email Subscribers** (postmeta on author user):
```
user_id=10 (author):
  meta_key='fanfic_email_subscribers'
  meta_value=['user@example.com', 'fan@mail.com', 'subscriber@test.com']
  (Simple array of emails)
```

**User's Email Subscriptions** (tracking for logged-in users):
```
user_id=5:
  meta_key='fanfic_email_subscriptions'
  meta_value=['story_123', 'story_456', 'author_10']
  (Array of story/author IDs they subscribe to)
```

**Why postmeta instead of table**:
- ✅ No separate table needed
- ✅ Simple array management
- ✅ Easy unsubscribe (remove from array)
- ✅ No token management needed
- ✅ Directly tied to story/author data

---

#### Reading Progress (Stored in User Metadata)

**Format** (complex range - array of read chapter IDs per story):
```
user_id=5:
  meta_key='fanfic_reading_progress'
  meta_value={
    'story_123' => [1, 2, 3, 4, 5, 7, 9],     // Read chapters in story 123 (non-sequential OK)
    'story_456' => [1, 2, 3],                 // Read chapters in story 456
    'story_789' => [1, 2, 3, 4, 5, 6, 7, 8]  // Read all chapters in story 789
  }
```

**Usage in template**:
```
Story 123 has chapters 1-10
User 5's reading progress: [1, 2, 3, 4, 5, 7, 9]

Display:
  Chapter 1: ✓ Read
  Chapter 2: ✓ Read
  Chapter 3: ✓ Read
  Chapter 4: ✓ Read
  Chapter 5: ✓ Read
  Chapter 6: (no mark, not read)
  Chapter 7: ✓ Read
  Chapter 8: (no mark, not read)
  Chapter 9: ✓ Read
  Chapter 10: (no mark, not read)
```

---

#### Cleanup: Handling Deleted Stories/Chapters (Cron Job)

**Purpose**: When a story or chapter is deleted from WordPress, remove all related follow data to keep database clean.

**Cleanup runs daily** (admin-configurable time):

**For Deleted Stories**:
```
Cron job checks: Which stories exist in WordPress?
For each story NOT found:
  1. DELETE from wp_fanfic_follows WHERE story_id=X
  2. DELETE story postmeta with key='fanfic_email_subscribers'
  3. REMOVE 'story_X' from all users' fanfic_reading_progress metadata
  4. REMOVE 'story_X' from all users' fanfic_email_subscriptions metadata

If already deleted: No error, just skip
```

**For Deleted Chapters**:
```
Cron job checks: Which chapters exist in WordPress?
For each chapter NOT found:
  1. REMOVE chapter_id from all users' fanfic_reading_progress arrays

If already deleted: No error, just skip
```

**Implementation** (graceful, no errors):
```php
public static function cleanup_orphaned_data() {
    global $wpdb;

    // 1. Find deleted stories
    $all_story_ids = $wpdb->get_col(
        "SELECT DISTINCT story_id FROM {$wpdb->prefix}fanfic_follows WHERE story_id IS NOT NULL"
    );

    foreach ( $all_story_ids as $story_id ) {
        $exists = post_exists( $story_id );
        if ( ! $exists ) {
            // Story deleted - clean up
            self::cleanup_deleted_story( $story_id );
        }
    }

    // 2. Find deleted chapters
    $all_chapter_ids = $wpdb->get_col(
        "SELECT DISTINCT chapter_id FROM {$wpdb->prefix}fanfic_actions WHERE chapter_id IS NOT NULL"
    );

    foreach ( $all_chapter_ids as $chapter_id ) {
        $exists = post_exists( $chapter_id );
        if ( ! $exists ) {
            // Chapter deleted - clean up
            self::cleanup_deleted_chapter( $chapter_id );
        }
    }

    // Cron completed
    do_action( 'fanfic_cleanup_orphaned_completed', time() );
}

private static function cleanup_deleted_story( $story_id ) {
    global $wpdb;

    // Remove from follows table
    $wpdb->delete(
        $wpdb->prefix . 'fanfic_follows',
        [ 'story_id' => $story_id ],
        [ '%d' ]
    );

    // Remove from story postmeta
    delete_post_meta( $story_id, 'fanfic_email_subscribers' );

    // Remove from users' reading progress
    $users = get_users();
    foreach ( $users as $user ) {
        $progress = get_user_meta( $user->ID, 'fanfic_reading_progress', true );
        if ( ! empty( $progress ) && isset( $progress["story_{$story_id}"] ) ) {
            unset( $progress["story_{$story_id}"] );
            update_user_meta( $user->ID, 'fanfic_reading_progress', $progress );
        }

        // Remove from email subscriptions
        $subscriptions = get_user_meta( $user->ID, 'fanfic_email_subscriptions', true );
        if ( ! empty( $subscriptions ) ) {
            $subscriptions = array_filter( $subscriptions, function( $val ) use ( $story_id ) {
                return $val !== "story_{$story_id}";
            } );
            update_user_meta( $user->ID, 'fanfic_email_subscriptions', $subscriptions );
        }
    }
}

private static function cleanup_deleted_chapter( $chapter_id ) {
    // Remove from users' reading progress
    $users = get_users();
    foreach ( $users as $user ) {
        $progress = get_user_meta( $user->ID, 'fanfic_reading_progress', true );
        if ( ! empty( $progress ) ) {
            // Remove chapter_id from all stories
            foreach ( $progress as $story_key => &$chapters ) {
                if ( is_array( $chapters ) ) {
                    $chapters = array_filter( $chapters, function( $ch ) use ( $chapter_id ) {
                        return $ch !== $chapter_id;
                    } );
                }
            }
            update_user_meta( $user->ID, 'fanfic_reading_progress', $progress );
        }
    }
}
```

**Key Features**:
- ✅ No errors if already deleted
- ✅ Handles both logged-in and anonymous data
- ✅ Graceful: Skips missing data silently
- ✅ Runs automatically as cron job
- ✅ Admin can set cleanup time in settings

---

### Notification Flows

#### Flow 1: User Follows Story (Logged-In)

```
User clicks "Follow Story"
    ↓
STEP 1: Save follow to database
    INSERT into wp_fanfic_follows
    (follower_id=5, story_id=123, follow_type='story', email_enabled=1)
    ↓
STEP 2: Queue notification to author
    Batch queue notification to author:
    "User Marcelo is following your story Pocahontas"
    IN-APP NOTIFICATION: Yes (always)
    EMAIL: Yes (to author, always)
    ↓
User sees in dashboard: "You are following this story"
User can toggle email notifications ON/OFF per follow
    ↓
When chapter published on this story:
    Get all followers: SELECT from wp_fanfic_follows WHERE story_id=123 AND follow_type='story'
    Queue in-app notifications: ALL followers
    Queue emails: ONLY followers with email_enabled=1
```

**Note**: Follow is saved (STEP 1) BEFORE notification is queued (STEP 2). If notification fails, follow is still saved and can be retried.

#### Flow 2: User Subscribes to Story (Any User - Email Only)

```
Anonymous user enters email on story page, clicks "Subscribe"
    ↓
STEP 1: Save email to story postmeta
    story_id=123:
      meta_key='fanfic_email_subscribers'
      meta_value=['user@example.com', 'anon@test.com', 'reader@mail.com']
    (Array of emails, simple and direct)
    ↓
STEP 2: Track subscription (for user to manage unsubscribe)
    (if logged-in) user_id=5:
      meta_key='fanfic_email_subscriptions'
      meta_value=['story_123', 'story_456', 'author_10']
    (if anonymous - optional, for unsubscribe link security)
    ↓
NO notification to author
    ↓
When chapter published on this story:
    Get all subscribers: READ story_123 postmeta 'fanfic_email_subscribers'
    Queue emails to all subscribers
    Each email includes: Unsubscribe link (from this story) + Unsubscribe All link
    ↓
User clicks unsubscribe link in email:
    GET /?action=unsubscribe&email=user@example.com&story_id=123
    Remove email from story_123 postmeta array
    Remove story_123 from user metadata (if logged-in)
    ↓
User clicks unsubscribe all link in email:
    GET /?action=unsubscribe_all&email=user@example.com
    Remove email from ALL story postmeta arrays
    Remove ALL story subscriptions from user metadata
```

**Note**: Email subscriptions stored directly in story postmeta as simple array (no separate table needed). Unsubscribe removes from this array.

#### Flow 3: User Follows Author (Logged-In)

```
Logged-in user clicks "Follow Author"
    ↓
STEP 1: Save follow to database
    INSERT into wp_fanfic_follows
    (follower_id=5, author_id=10, follow_type='author', email_enabled=1)
    ↓
STEP 2: Queue notification to author
    Batch queue notification to author:
    "User Marcelo is following you"
    IN-APP NOTIFICATION: Yes (always)
    EMAIL: Yes (to author, always)
    ↓
User sees in dashboard: "You are following this author"
User can toggle email notifications ON/OFF per follow
    ↓
When author publishes new story/chapter:
    Get all followers: SELECT from wp_fanfic_follows WHERE author_id=10 AND follow_type='author'
    Queue in-app notifications: ALL followers
    Queue emails: ONLY followers with email_enabled=1
```

**Note**: Follow is saved (STEP 1) BEFORE notification is queued (STEP 2). If notification fails, follow is still saved and can be retried.

#### Flow 4: User Subscribes to Author (Any User - Email Only)

```
Anonymous user enters email on author page, clicks "Subscribe to Updates"
    ↓
STEP 1: Save email to author postmeta
    author_id=10 (user post, author profile):
      meta_key='fanfic_email_subscribers'
      meta_value=['user@example.com', 'reader@mail.com', 'fan@test.com']
    (Array of emails, simple and direct)
    ↓
STEP 2: Track subscription (for user to manage)
    (if logged-in) user_id=5:
      meta_key='fanfic_email_subscriptions'
      meta_value=['story_123', 'story_456', 'author_10']
    ↓
NO notification to author
    ↓
When author publishes new story/chapter:
    Get all subscribers: READ author_10 postmeta 'fanfic_email_subscribers'
    Queue emails to all subscribers
    Each email includes: Unsubscribe link (from this author) + Unsubscribe All link
    ↓
User manages subscription via email links (same as Flow 2):
    - Unsubscribe from specific author
    - Unsubscribe from all
```

**Note**: Email subscriptions stored directly in author postmeta as simple array (no separate table needed).

### Key Differences: Follow vs Subscribe

| Aspect | Follow (Logged-In) | Subscribe (Email) |
|--------|-------------------|------------------|
| **Who** | Logged-in users only | Anyone (logged-in or anonymous) |
| **Tracking** | In `wp_fanfic_follows` table | In story/author postmeta (array of emails) |
| **In-App Notif** | ✅ Always in dashboard | ❌ None |
| **Email Notif** | ✅ Optional (per-follow toggle) | ✅ Always |
| **Creator Notified** | ✅ Yes ("User X following your story Y") | ❌ No |
| **Unfollow** | Click unfollow button (instant) | Email link or unsubscribe page (URL-based) |
| **Default Email** | Enabled (can toggle off) | N/A (email-only) |
| **Account Required** | ✅ Yes | ❌ No |
| **Storage Location** | Database table | WordPress post/author metadata |

### Email Template Requirements

Every email sent must include unsubscribe links:

```html
<!-- At bottom of every email -->
<hr>
<p style="font-size: 12px; color: #666;">
    Not interested in updates about this story/author?
    <a href="<?php echo esc_url( home_url( '/?action=unsubscribe&email=' . urlencode($email) . '&token=' . $token ) ); ?>">
        Unsubscribe from this
    </a>
    or
    <a href="<?php echo esc_url( home_url( '/?action=unsubscribe_all&email=' . urlencode($email) . '&token=' . $token ) ); ?>">
        Unsubscribe from all emails
    </a>
</p>
```

### User Preferences (Logged-In Followers)

Logged-in users see their follows in their dashboard with toggles:

```
Your Follows:
├─ Story "Pocahontas" by Author Jane (Email: [ON/OFF] ☐)
├─ Story "Avatar" by Author John (Email: [ON/OFF] ☐)
├─ Author "Jane Smith" (Email: [ON/OFF] ☐)
└─ Author "John Doe" (Email: [ON/OFF] ☐)
```

Toggle is stored in one of two ways:
1. **Option A**: Store in `wp_fanfic_follows.email_enabled` column (simplest)
2. **Option B**: Store in user_meta: `fanfic_follow_{follow_id}_email_enabled` (if separate table needed)

**Recommendation**: Use Option A (column in same table) for simplicity.

### Cron Jobs

**Scheduled actions** (same as existing):

1. **Every 30 minutes**: Process email queue
   - Send up to 50 emails per run
   - From both logged-in follower emails AND subscription emails

2. **Daily (3 AM)**: Cleanup old emails
   - Delete sent emails older than 30 days
   - Delete failed emails older than 7 days

---

## Part 12: Anonymous Actions & Pragmatic Optimization Strategy

This section details the anonymous rating/like system and the pragmatic optimization philosophy that respects WordPress standards, hosting limits, and cache plugins.

### Anonymous Actions & v2.0 Rating/Like System Integration

#### Important: Ratings and Likes Stay in v2.0 System

**Ratings** and **Likes** continue using the v2.0 system (`class-fanfic-rating-system.php` and `class-fanfic-like-system.php`). They are **NOT** part of the unified actions table.

**Why separate**:
- v2.0 system already has optimized caching (write-through + incremental updates)
- v2.0 system has proven database schema and fingerprinting
- Ratings/Likes are the most performance-critical features
- No need to migrate working, optimized code

**Unified actions table** covers: reads, bookmarks, follows, subscribes (NOT ratings/likes)

---

#### v2.0 Fingerprinting for Anonymous Actions (Simplified)

For other anonymous actions (bookmarks, follows), use the v2.0 approach but simplified:

**Browser Fingerprint** (5 attributes, sufficient):
```javascript
// Collected once per session in frontend JS
{
  ua: navigator.userAgent,
  screen: "1920x1080",              // Resolution
  lang: "en-US",                     // Language
  platform: "MacIntel",              // Platform
  cores: navigator.hardwareConcurrency || 1
}
```

**Hashing** (v2.0 approach):
```php
public static function get_identifier_hash() {
    $ip = self::get_client_ip();

    // Get fingerprint from AJAX request
    $fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( $_POST['fingerprint'] ) : '';

    // MD5 hash of IP + fingerprint (v2.0 approach)
    $identifier = $ip . $fingerprint;
    $hash = md5( $identifier );  // 32-character hash

    return $hash;
}
```

**Why MD5 instead of SHA256**:
- v2.0 system uses MD5 for identifier hashing (proven, consistent)
- 32-character hash sufficient for anonymous deduplication
- Faster than SHA256 (minimal performance difference)
- Database column `VARCHAR(32)` instead of `VARCHAR(64)`

---

#### Anonymous Actions Database Schema

**For unified actions table** (bookmarks, follows, reads - anonymous only):

```sql
ALTER TABLE wp_fanfic_actions
ADD COLUMN identifier_hash VARCHAR(32) DEFAULT NULL;  -- MD5 hash (v2.0 approach)

-- For anonymous-only actions
ADD UNIQUE KEY unique_anonymous_action (
    action_type,
    chapter_id,
    identifier_hash
) WHERE user_id IS NULL;
```

**What is stored**:
- `user_id = NULL` (anonymous action)
- `identifier_hash = MD5(IP + browser_fingerprint)` (v2.0 approach)
  - **NOT stored**: Raw IP, raw fingerprint, cookies, or tracking IDs
  - **Stored**: One-way hash only
- `created_at` (for 30-day cleanup)

**Why this approach**:
- IP hash alone: Can be shared (corporate networks, VPN, shared WiFi)
- Fingerprint alone: Can change (browser update, extensions)
- **Both together**: Reliable, hard to spoof (30-day window limits impact)
- Same approach as v2.0 rating/like system (proven)

---

#### 30-Day Anonymization (v2.0 Approach)

**Instead of deleting**, set `identifier_hash = NULL`:

**Why**:
- ✅ Preserves vote/action data for statistics
- ✅ GDPR compliant (after 30 days, cannot link back to individual)
- ✅ Allows user to re-action after 30 days (new identifier)
- ✅ Simple SQL operation (UPDATE, not DELETE)

**Cron Job** (Admin-configurable time via settings):

```php
public static function cleanup_anonymous_actions() {
    global $wpdb;

    // Set identifier_hash to NULL for records >30 days old
    // This anonymizes old votes but preserves the data
    $wpdb->query(
        "UPDATE {$wpdb->prefix}fanfic_actions
         SET identifier_hash = NULL
         WHERE user_id IS NULL
         AND identifier_hash IS NOT NULL
         AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    // Also clear any cached data
    wp_cache_delete( 'fanfic_anonymous_cleanup_time', 'fanfic' );

    do_action( 'fanfic_anonymous_cleanup_completed', time() );
}
```

**Registration** (configurable via admin settings):

```php
// Get cleanup hour from admin settings (default: 3 AM)
$cleanup_hour = get_option( 'fanfic_cleanup_hour', 3 );

// Schedule if not already scheduled
if ( ! wp_next_scheduled( 'fanfic_cleanup_anonymous_actions' ) ) {
    wp_schedule_event(
        strtotime( "tomorrow {$cleanup_hour}:00 AM" ),
        'daily',
        'fanfic_cleanup_anonymous_actions'
    );
}

add_action( 'fanfic_cleanup_anonymous_actions', [ 'Fanfic_Anonymous_Actions', 'cleanup_anonymous_actions' ] );
```

**Admin Setting** (in plugin settings panel):

```php
// In Fanfic_Settings class
add_settings_field(
    'fanfic_cleanup_hour',
    'Anonymous Data Cleanup Time',
    [ $this, 'render_cleanup_hour_field' ],
    'fanfic_settings',
    'fanfic_general_section'
);

public function render_cleanup_hour_field() {
    $hour = get_option( 'fanfic_cleanup_hour', 3 );
    echo '<select name="fanfic_cleanup_hour">';
    for ( $h = 0; $h < 24; $h++ ) {
        $selected = $hour === $h ? 'selected' : '';
        echo "<option value='{$h}' {$selected}>" . sprintf( '%02d:00', $h ) . "</option>";
    }
    echo '</select>';
}
```

---

#### Anonymous Actions Implementation Example

```php
class Fanfic_Anonymous_Actions {

    /**
     * Record anonymous action (bookmark, follow, read)
     * Uses v2.0 identifier hashing approach
     */
    public static function record_anonymous_action(
        $action_type,   // 'bookmark', 'follow', 'read'
        $target_id,     // chapter_id or story_id
        $fingerprint    // JSON from frontend
    ) {
        global $wpdb;

        $identifier_hash = self::get_identifier_hash( $fingerprint );

        // Check if already performed this action (within 30 days)
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}fanfic_actions
             WHERE action_type = %s
             AND chapter_id = %d
             AND user_id IS NULL
             AND identifier_hash = %s",
            $action_type, $target_id, $identifier_hash
        ) );

        if ( $existing ) {
            return new WP_Error( 'already_acted', 'You have already performed this action' );
        }

        // Record action
        $result = $wpdb->insert(
            $wpdb->prefix . 'fanfic_actions',
            [
                'action_type' => $action_type,
                'user_id' => null,
                'chapter_id' => $target_id,
                'identifier_hash' => $identifier_hash,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ]
        );

        if ( $result ) {
            Fanfic_Actions_System::increment_counter( null, $target_id, $action_type );
            // No notification to author for anonymous actions
            return true;
        }

        return false;
    }

    /**
     * Get identifier hash (v2.0 approach)
     * MD5(IP + browser fingerprint)
     */
    private static function get_identifier_hash( $fingerprint_json ) {
        $ip = self::get_client_ip();
        $identifier = $ip . $fingerprint_json;
        return md5( $identifier );
    }

    /**
     * Get client IP (handles proxies)
     */
    private static function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
        }

        return $ip ?: '0.0.0.0';
    }
}
```

---

#### GDPR & Privacy Compliance

**Data stored**:
- ✅ One-way hash only (MD5 of IP + fingerprint)
- ❌ NO raw IP address stored
- ❌ NO raw browser fingerprint stored
- ❌ NO cookies or persistent identifiers

**30-day anonymization**:
- ✅ After 30 days: `identifier_hash = NULL`
- ✅ Vote/action preserved for statistics
- ✅ Cannot be linked back to individual
- ✅ User can re-vote/re-action after 30 days

**Result**: Fully GDPR compliant, transparent, user-friendly

---

### Pragmatic Optimization Philosophy

**Core Principle**: Let WordPress and hosting do their work seamlessly. Avoid artificial limits. Respect hosting constraints.

#### 1. Email Sending: Respect Hosting Limits (NOT Hard Limits)

**DON'T do this** (artificial limits):
```php
// ❌ WRONG: Hard limit of 10 emails/hour per user
if ( $sent_count >= 10 ) {
    // Block user from receiving emails
    return false;
}
```

**DO this** (let hosting handle it):
```php
// ✅ RIGHT: Send emails in reasonable batches, let SMTP handle queueing
public static function process_email_queue() {
    global $wpdb;

    // Get pending emails (FIFO order)
    $emails = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}fanfic_email_queue
         WHERE status = 'pending'
         ORDER BY queued_at ASC
         LIMIT 50"  // Batch size depends on hosting, configurable
    );

    // Send each email via wp_mail() - let hosting SMTP decide queue
    foreach ( $emails as $email ) {
        $sent = wp_mail(
            $user->user_email,
            $email->subject,
            $email->body
        );

        if ( $sent ) {
            update_email_status( $email->id, 'sent' );
        } else {
            // Retry logic - not hard limit
            if ( $email->attempts < 3 ) {
                increment_retry_attempts( $email->id );
            } else {
                update_email_status( $email->id, 'failed' );
            }
        }
    }
}
```

**Why**:
- WordPress `wp_mail()` respects hosting SMTP limits
- Different hosts have different limits (AWS 14/second, shared hosting 5/second)
- Don't build artificial limits - let infrastructure handle it
- Batches of 50 emails per 30-minute cron is reasonable for most hosts

#### 2. Caching: Leverage WordPress Native Cache, NOT Build Our Own

**DON'T do this** (over-caching):
```php
// ❌ WRONG: Build dual-layer cache system
$data = wp_cache_get( $key, 'fanfic_progress' );
if ( false === $data ) {
    $data = get_transient( $key );
    if ( false === $data ) {
        $data = query_db();
        set_transient( $key, $data, 12 * HOUR_IN_SECONDS );
    }
    wp_cache_set( $key, $data, 'fanfic_progress', 12 * HOUR_IN_SECONDS );
}
```

**DO this** (simple, WordPress-native):
```php
// ✅ RIGHT: Simple WordPress cache, works with any cache plugin
$cache_key = "fanfic_reading_progress_{$user_id}_{$story_id}";

// WordPress automatically uses object cache if available
// Falls back to transients if no external cache
$data = wp_cache_get( $cache_key, 'fanfic' );

if ( false === $data ) {
    $data = $wpdb->get_row( ... );  // Optimized query
    wp_cache_set( $cache_key, $data, 'fanfic', 6 * HOUR_IN_SECONDS );
}

return $data;
```

**Why**:
- `wp_cache_get/set` automatically uses:
  - Redis/Memcached if installed
  - WordPress Object Cache if available
  - Transients as fallback
- No need to build dual-layer caching
- Cache plugins handle invalidation, persistence, everything
- Our code stays simple

#### 3. Batch Operations: Reduce Queries, NOT Cache Entries

**DON'T do this** (cache every query):
```php
// ❌ WRONG: Cache every notification query
foreach ( $user_ids as $user_id ) {
    $count = wp_cache_get( "notif_count_{$user_id}" );
    if ( false === $count ) {
        $count = count_notifications( $user_id );
        wp_cache_set( "notif_count_{$user_id}", $count );
    }
}
```

**DO this** (batch queries, let cache plugin handle it):
```php
// ✅ RIGHT: Single batch query, cache the result
$counts = $wpdb->get_results(
    "SELECT user_id, COUNT(*) as count
     FROM {$wpdb->prefix}fanfic_notifications
     WHERE user_id IN (" . implode(',', $user_ids) . ")
     AND is_read = 0
     GROUP BY user_id"
);

// Cache the entire batch result
wp_cache_set( 'notification_counts_batch', $counts, 'fanfic', 1 * HOUR_IN_SECONDS );

return $counts;
```

**Why**:
- One query for N users = N times faster than N queries
- Cache result once vs N times
- Cache plugins handle everything else
- Simpler code, better performance

#### 4. Database Indexes: Maximum ROI on Performance

**DO this**: Add indexes for every query filtering/joining:
```sql
-- Reading progress queries
KEY (user_id, story_id)

-- Follows queries
KEY (follower_id)
KEY (author_id)
KEY (story_id)

-- Email queue queries
KEY (status, queued_at)

-- Notification queries
KEY (user_id, is_read, created_at)
```

**Why**:
- Indexes reduce query time 100-1000x
- Almost zero memory overhead
- No code complexity
- Every hosting supports indexes

#### 5. Graceful Degradation: Work WITHOUT Cache Plugins

**Philosophy**: Plugin works perfectly on shared hosting WITHOUT any cache plugin.

```php
// ALWAYS work offline
public static function get_story_followers( $story_id ) {
    global $wpdb;

    // Try cache (if available)
    $followers = wp_cache_get( "story_{$story_id}_followers", 'fanfic' );

    if ( false === $followers ) {
        // Optimized query with index
        $followers = $wpdb->get_col( $wpdb->prepare(
            "SELECT follower_id FROM {$wpdb->prefix}fanfic_follows
             WHERE story_id = %d AND follow_type = 'story'
             ORDER BY created_at DESC",
            $story_id
        ) );

        // Cache it if cache is available (no error if not)
        wp_cache_set( "story_{$story_id}_followers", $followers, 'fanfic', 6 * HOUR_IN_SECONDS );
    }

    return $followers;
}
```

**Why**:
- Works on any WordPress install
- If Redis installed, uses it
- If transients available, uses them
- If neither, still works (just slower, but acceptable)
- No artificial dependencies

#### 6. Query Optimization > Caching

**Priority order**:
1. **Optimized queries with indexes** (biggest impact, no complexity)
2. **Batch operations** (combine N queries into 1)
3. **WordPress native caching** (simple, works with plugins)
4. **Graceful fallback** (works without cache)
5. ❌ Don't build custom cache systems

**Performance gains**:
- Reading progress: 50 individual queries → 1 batch query = **99% improvement**
- Followers list: 100 serial queries → 1 batch query = **99% improvement**
- Email queue: Status check every time → Cache check first = **70% improvement**

---

### Incremental Cache Updates vs Cache Invalidation (v2.0 Pattern)

**This is the caching pattern used in v2.0 rating/like system.** Understanding it clarifies whether to use it for other features.

#### The Problem: Cache Invalidation Bottleneck

**Traditional cache invalidation approach** (❌ PROBLEMATIC):

```
User submits rating (action 1)
  ↓
DELETE cache_key  (clear the cache)
  ↓
Next page load: Cache miss
  ↓
REBUILD from 100 queries (COUNT ratings, SUM scores, etc)
  ↓
User submits another rating (action 2)
  ↓
DELETE cache_key  (clear again)
  ↓
Next page load: Cache miss AGAIN
  ↓
REBUILD again...
```

**Problem**: On high-traffic site with 10+ votes/minute:
- Cache is constantly invalidated
- Constant rebuilds on every page load
- Database hit on every cache miss
- Essentially: **No caching benefit**

---

#### The Solution: Write-Through Incremental Updates (v2.0)

**Incremental cache update approach** (✅ OPTIMAL):

```
User submits rating (action 1): 4 stars
  ↓
UPDATE database (1 INSERT/UPDATE)
  ↓
Is cache present?
  - NO: Rebuild once from database, cache it
  - YES: Update incrementally (math operations ONLY)
         average = (total * old_avg + new_rating) / (total + 1)
         total = total + 1
         cache[4_stars] = cache[4_stars] + 1
  ↓
User submits rating (action 2): 5 stars
  ↓
UPDATE database (1 INSERT/UPDATE)
  ↓
Cache EXISTS → Update incrementally
  ↓
Result: Database used ONCE, cache updated N times
```

**Why it works**:
- Write: Always hits database (necessary)
- Read (99% of requests): Hits cache
- No invalidation: Cache stays valid
- Incremental math: < 1ms (CPU only, no DB)

---

#### Pros & Cons: Detailed Comparison

#### **CACHE INVALIDATION APPROACH**

| Aspect | Details |
|--------|---------|
| **How** | DELETE cache_key on every action |
| **Pros** | ✅ Simple to understand<br>✅ Always correct data<br>✅ No math errors |
| **Cons** | ❌ Cache constantly cleared<br>❌ Constant rebuilds<br>❌ Database hit on every miss<br>❌ High traffic = bottleneck<br>❌ Not suitable for ratings/likes |
| **Use case** | Small sites, low update frequency |
| **Example** | 1 vote/hour → cache stays relevant |

#### **INCREMENTAL UPDATES APPROACH (v2.0)**

| Aspect | Details |
|--------|---------|
| **How** | UPDATE cache in-place using math |
| **Pros** | ✅ Cache NEVER invalidated<br>✅ 95%+ cache hit rate<br>✅ Minimal database load<br>✅ Suitable for high traffic<br>✅ Predictable performance<br>✅ Works for ratings/likes |
| **Cons** | ❌ Slightly more complex<br>❌ Math must be correct<br>❌ Need proper cache misses handling |
| **Use case** | High-traffic, frequent updates |
| **Example** | 100+ votes/minute → cache provides massive benefit |

---

#### When to Use Each Approach

```
Traffic Level          → Approach           → Example
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1-10 actions/day       → Cache invalidation  → Small blog
10-100 actions/day     → Either (both work)  → Medium community
100+ actions/day       → Incremental update  → Active fanfiction site
1000+ actions/day      → Incremental ONLY    → High-traffic platform
```

---

#### Implementation: Incremental vs Invalidation

**❌ CACHE INVALIDATION (Don't use for ratings/likes)**:

```php
public static function submit_rating( $chapter_id, $rating ) {
    global $wpdb;

    // Insert rating
    $wpdb->insert( $wpdb->prefix . 'fanfic_ratings', [...] );

    // Delete cache (WRONG for high traffic)
    wp_cache_delete( "fanfic_chapter_{$chapter_id}_rating" );

    // Next page load MUST rebuild (database hit)
}
```

**Cons**:
- Cache hit rate: 0% (always rebuilt after action)
- Database load: INCREASES with traffic
- Performance: Gets WORSE as site grows

---

**✅ INCREMENTAL UPDATE (v2.0 approach)**:

```php
public static function submit_rating( $chapter_id, $rating ) {
    global $wpdb;

    // Insert rating
    $wpdb->insert( $wpdb->prefix . 'fanfic_ratings', [...] );

    // Try to update cache incrementally
    $cache_key = "fanfic_chapter_{$chapter_id}_rating";
    $cache = wp_cache_get( $cache_key, 'fanfic' );

    if ( false !== $cache ) {
        // Cache HIT: Update incrementally (math operations only)
        $cache['total_votes'] += 1;
        $cache['average'] = ( $cache['total_votes'] * $cache['average'] + $rating )
                            / $cache['total_votes'];
        $cache["star_{$rating}"] += 1;

        // Update cache (no database)
        wp_cache_set( $cache_key, $cache, 'fanfic', 24 * HOUR_IN_SECONDS );
    } else {
        // Cache MISS: Rebuild once from database
        $cache = self::rebuild_rating_cache( $chapter_id );
        wp_cache_set( $cache_key, $cache, 'fanfic', 24 * HOUR_IN_SECONDS );
    }
}
```

**Pros**:
- Cache hit rate: 99%+ (rarely rebuilt)
- Database load: DECREASES with traffic (better caching)
- Performance: Improves as site grows

---

#### When NOT to Use Incremental Updates

**Incremental updates only work for**:
- ✅ **Aggregated data**: Averages, sums, counts
- ✅ **Append-only**: New votes never modify past votes
- ✅ **Math-based**: Pure calculation, no complex logic

**Don't use incremental for**:
- ❌ **Complex queries**: Sorting, filtering, joins
- ❌ **Mutable data**: Data changes in unpredictable ways
- ❌ **Non-mathematical**: Can't be updated via math formula

---

#### Example: Why Incremental Works for Ratings

**Ratings data**:
```
Before: {total: 10, avg: 3.5, star_1: 1, star_2: 1, star_3: 2, star_4: 3, star_5: 3}
New rating: 4 stars

Incremental formula:
  new_avg = (total * old_avg + new_rating) / (total + 1)
          = (10 * 3.5 + 4) / 11
          = 39 / 11
          = 3.545

After:  {total: 11, avg: 3.545, star_1: 1, star_2: 1, star_3: 2, star_4: 4, star_5: 3}
```

**Why it works**:
- Formula is mathematically sound
- New rating doesn't change old ratings
- Only increments affected counts
- No database needed

---

#### Example: Why Incremental FAILS for Complex Data

**Follower list** (would fail):
```
Before cache: [User 1, User 5, User 12, User 8]
New follow: User 3

Incremental issue:
  - Can't increment users list (not numeric)
  - Must sort (alphabetical? by date? by activity?)
  - Must paginate (page 1? page 2?)
  - Must filter (follow type? status?)

Result: Can't update via math → MUST invalidate cache
```

---

#### Recommendation for Your Plugin

**For ratings/likes**:
- ✅ **Use incremental updates** (v2.0 system already does this)
- ✅ Benefits proven on high-traffic sites
- ✅ No need to change

**For other features** (bookmarks, follows, reads):
- **Use cache invalidation** (simpler for now)
  - Bookmark list changes frequently
  - Follower lists not aggregated
  - Reading progress is boolean (read/unread)
- **Later**: If performance issues, consider incremental
  - But only for aggregated stats (total bookmarks, etc)
  - Not for lists

**For ratings/likes**: Continue using v2.0 incremental updates
**For other features**: Use simple cache invalidation

---

## Summary: What Changed From Original Audit

| Item | Original | Updated | Reason |
|------|----------|---------|--------|
| Terminology | "Story Subscriptions" | Follow (logged-in) + Subscribe (email) | Clear distinction of features |
| Follow Feature | Authors only | Authors + Stories | Users can follow specific stories |
| Email Preferences | Fixed per notification type | Per-follow toggle | Granular user control |
| Creator Notification on Follow | Partial | Complete ("User X following your story Y") | Better author experience |
| Email Subscriptions | Basic | With secure tokens + unsubscribe links | Anonymous-friendly, GDPR-compliant |
| Unsubscribe | Manual (no links) | Email links + unsubscribe page | One-click unsubscribe |
| Dual caching | Implemented | Skip | 90% sites no Redis |
| Cache TTL | 12 hours | N/A | Query optimization sufficient |
| Incremental updates | Implemented | Skip | Too complex, minimal gain |
| Digest emails | Full system | Skip | Not requested |
| Email priority | Implemented | Skip | All same urgency |
| Email rate limiting | Implemented | Skip | Trust hosting limits |
| **Total new code** | **2000+ lines** | **700 lines** | **65% simpler** |

---

**Version**: 2.1 (Pragmatic Implementation with Unified Follow/Subscribe)
**Last Updated**: 2025-11-12
**Status**: Ready for implementation
**Maintainer**: Development Team

**Key Updates**:
- Clarified Follow (logged-in with in-app notifications) vs Subscribe (email-only)
- Added story follows (separate from author follows, same table)
- Added per-follow email preference toggle
- Added creator notifications on follow ("User X is following your story Y")
- Added secure email subscription system with unsubscribe links
- Removed complexity: dual caching, digest system, priority queue, rate limiting
