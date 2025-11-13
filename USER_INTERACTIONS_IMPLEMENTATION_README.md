# User Interactions System - Implementation Guide
## Fanfiction Manager WordPress Plugin

**Version:** 1.0.15
**Last Updated:** 2025-11-13
**Status:** Production Ready

---

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Installation & Setup](#installation--setup)
4. [Features](#features)
5. [How It Works](#how-it-works)
6. [Configuration](#configuration)
7. [Usage Examples](#usage-examples)
8. [Testing Guide](#testing-guide)
9. [Troubleshooting](#troubleshooting)
10. [Performance Optimization](#performance-optimization)
11. [Security Best Practices](#security-best-practices)
12. [Future Enhancements](#future-enhancements)

---

## Overview

The User Interactions System is a comprehensive feature set for the Fanfiction Manager plugin that enables rich user engagement with stories and authors. It was implemented in 5 phases over multiple iterations.

### What Was Implemented

This system provides:

- **Ratings & Likes:** Star ratings (1-5) and simple likes for chapters
- **Bookmarks:** Save favorite stories and chapters for later
- **Follows:** Follow stories and authors to get updates
- **Reading Progress:** Track which chapters have been read
- **Email Notifications:** Get email alerts for new content
- **In-App Notifications:** Real-time notifications within WordPress
- **Anonymous Support:** Non-logged-in users can rate and like (with cookie tracking)
- **Security:** Rate limiting, nonce verification, capability checks
- **Performance:** Caching, batch loading, query optimization
- **Email Queue:** Batched email sending via WP-Cron

### Why This System

The User Interactions System transforms Fanfiction Manager from a simple publishing platform into a fully-featured community platform with:

1. **User Engagement:** Readers can interact with content meaningfully
2. **Author Feedback:** Authors get valuable feedback via ratings and likes
3. **Discovery:** Follow systems help readers discover new content
4. **Retention:** Bookmarks and reading progress keep users coming back
5. **Communication:** Email notifications keep community engaged
6. **Privacy:** Anonymous interactions respect user privacy
7. **Performance:** Optimized for high-traffic sites

---

## System Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     FANFICTION MANAGER                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │            Phase 5: Frontend Integration                │    │
│  │  • AJAX Handlers (unified endpoints)                    │    │
│  │  • JavaScript (interactions.js)                         │    │
│  │  • Security wrapper (nonce, rate limit)                 │    │
│  └────────────────────────────────────────────────────────┘    │
│                            ↓                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │         Phase 4: Security & Performance                 │    │
│  │  • Rate Limiting (10 req/min)                          │    │
│  │  • Security Checks (nonce, capabilities)               │    │
│  │  • Cache Manager (enhanced caching)                    │    │
│  │  • Performance Monitor (optional)                      │    │
│  └────────────────────────────────────────────────────────┘    │
│                            ↓                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │         Phase 3: Email & Notifications                  │    │
│  │  • Notifications (in-app)                              │    │
│  │  • Email Queue (batched sending)                       │    │
│  │  • Email Subscriptions (email-only)                    │    │
│  │  • Email Templates (customizable)                      │    │
│  └────────────────────────────────────────────────────────┘    │
│                            ↓                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │         Phase 2: Core Interaction Logic                 │    │
│  │  • Rating System (1-5 stars)                           │    │
│  │  • Like System (boolean)                               │    │
│  │  • Bookmarks (stories/chapters)                        │    │
│  │  • Follows (stories/authors)                           │    │
│  │  • Reading Progress (tracking)                         │    │
│  │  • User Identifier (anonymous support)                 │    │
│  └────────────────────────────────────────────────────────┘    │
│                            ↓                                     │
│  ┌────────────────────────────────────────────────────────┐    │
│  │         Phase 1: Database Infrastructure                │    │
│  │  • 7 Custom Tables                                     │    │
│  │  • Indexes & Constraints                               │    │
│  │  • Version Tracking                                    │    │
│  │  • Multisite Support                                   │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Data Flow

```
User Action (Click Like Button)
         ↓
JavaScript Event Handler
         ↓
AJAX Request (with nonce)
         ↓
AJAX Security Wrapper
         ↓
Rate Limit Check
         ↓
Nonce Verification
         ↓
Capability Check
         ↓
Core System (Like System)
         ↓
Database Update
         ↓
Cache Invalidation
         ↓
Notification Created
         ↓
Response to Frontend
         ↓
UI Update
```

---

## Installation & Setup

### Prerequisites

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+
- Pretty Permalinks enabled
- WP-Cron enabled (for email queue)

### Automatic Setup (Recommended)

The system is automatically installed when you activate the Fanfiction Manager plugin:

1. Navigate to **WordPress Admin → Plugins**
2. Activate **Fanfiction Manager**
3. Database tables are created automatically
4. All systems are initialized automatically

### Manual Setup (If Needed)

If tables weren't created during activation:

```php
// Run in WordPress console or functions.php (temporary)
Fanfic_Database_Setup::init();

// Verify tables exist
$exists = Fanfic_Database_Setup::tables_exist();
if ($exists) {
    echo "All tables created successfully!";
}
```

### Verify Installation

Check that all components are working:

1. **Database Tables:** Go to phpMyAdmin and verify 7 tables with prefix `wp_fanfic_*`
2. **AJAX Endpoints:** Check browser console for AJAX errors
3. **JavaScript:** Verify `fanfiction-interactions.js` is loaded
4. **WP-Cron:** Run `wp cron event list` to see scheduled jobs

---

## Features

### 1. Rating System

**What:** Users can rate chapters on a 1-5 star scale

**Who Can Use:**
- Logged-in users
- Anonymous users (tracked via cookie)

**Limitations:**
- One rating per chapter per user
- Can update existing rating
- Anonymous ratings expire after 60 days (anonymized)

**Database:** `wp_fanfic_ratings`

**AJAX Endpoint:** `fanfic_rate_chapter`

**JavaScript Function:** `rateChapter(chapterId, rating)`

### 2. Like System

**What:** Simple like/unlike for chapters (similar to Facebook likes)

**Who Can Use:**
- Logged-in users
- Anonymous users (tracked via cookie)

**Limitations:**
- One like per chapter per user
- Toggle on/off
- Anonymous likes expire after 60 days (anonymized)

**Database:** `wp_fanfic_likes`

**AJAX Endpoint:** `fanfic_like_chapter`

**JavaScript Function:** `likeChapter(chapterId)`

### 3. Bookmarks

**What:** Save stories and chapters for later reading

**Who Can Use:**
- Logged-in users only

**Limitations:**
- One bookmark per story/chapter per user
- Toggle on/off

**Database:** `wp_fanfic_bookmarks`

**AJAX Endpoint:** `fanfic_bookmark_story`

**JavaScript Function:** `bookmarkStory(storyId)`

### 4. Follow Stories

**What:** Follow stories to get notifications when new chapters are posted

**Who Can Use:**
- Logged-in users only

**Limitations:**
- One follow per story per user
- Can enable/disable email notifications
- Toggle on/off

**Database:** `wp_fanfic_follows`

**AJAX Endpoint:** `fanfic_follow_story`

**JavaScript Function:** `followStory(storyId)`

### 5. Follow Authors

**What:** Follow authors to get notifications when they publish new stories

**Who Can Use:**
- Logged-in users only

**Limitations:**
- One follow per author per user
- Can enable/disable email notifications
- Toggle on/off

**Database:** `wp_fanfic_follows`

**AJAX Endpoint:** `fanfic_follow_author`

**JavaScript Function:** `followAuthor(authorId)`

### 6. Reading Progress

**What:** Track which chapters have been read

**Who Can Use:**
- Logged-in users only

**Limitations:**
- Tracks last chapter read per story
- Can be manually reset

**Database:** `wp_fanfic_reading_progress`

**AJAX Endpoint:** `fanfic_mark_chapter_read`

**JavaScript Function:** `markChapterRead(storyId, chapterNumber)`

### 7. Email Subscriptions

**What:** Subscribe to stories/authors via email (no login required)

**Who Can Use:**
- Anyone with an email address

**Limitations:**
- Requires email verification
- One subscription per email per story/author
- Can unsubscribe via unique token link

**Database:** `wp_fanfic_email_subscriptions`

**AJAX Endpoint:** `fanfic_subscribe_email`

**JavaScript Function:** `subscribeEmail(email, targetId, type)`

### 8. In-App Notifications

**What:** Notifications within WordPress admin/frontend

**Who Can Use:**
- Logged-in users only

**Types:**
- New chapter published
- New comment on followed story
- New story from followed author
- Story completed

**Database:** `wp_fanfic_notifications`

**AJAX Endpoint:** `fanfic_get_notifications`

**JavaScript Function:** `getNotifications()`

---

## How It Works

### Rating a Chapter (Step-by-Step)

#### Frontend (User Perspective)

1. User clicks on a star (1-5)
2. JavaScript sends AJAX request
3. Loading indicator shows
4. Success message appears
5. Rating updates visually

#### Backend (Technical Flow)

```php
// 1. User clicks star (3 stars)
// JavaScript: fanficInteractions.rateChapter(123, 3)

// 2. AJAX request sent
jQuery.post(fanficAjax.ajaxUrl, {
    action: 'fanfic_rate_chapter',
    nonce: fanficAjax.nonce,
    chapter_id: 123,
    rating: 3
});

// 3. AJAX Security Wrapper (class-fanfic-ajax-security.php)
// - Verifies nonce
// - Checks rate limit (10 req/min)
// - Sanitizes input

// 4. Rating System (class-fanfic-rating-system.php)
$user_id = get_current_user_id();
$identifier = Fanfic_User_Identifier::get_identifier(); // Cookie-based if anonymous

// 5. Database Insert/Update
global $wpdb;
$wpdb->replace($wpdb->prefix . 'fanfic_ratings', [
    'chapter_id' => 123,
    'user_id' => $user_id ?: 0,
    'rating' => 3,
    'identifier_hash' => $identifier,
    'created_at' => current_time('mysql')
]);

// 6. Cache Invalidation
Fanfic_Cache_Manager::invalidate_chapter_cache(123);
Fanfic_Cache_Manager::invalidate_story_cache($story_id);

// 7. Response
wp_send_json_success([
    'message' => __('Rating submitted!'),
    'average_rating' => $new_average,
    'total_ratings' => $total_count
]);
```

### Email Notification (Step-by-Step)

#### When New Chapter Published

```php
// 1. Author publishes new chapter
// WordPress Hook: transition_post_status (draft → publish)

// 2. Notification System Triggered
do_action('fanfic_new_chapter', $chapter_id, $story_id);

// 3. Find All Followers
$followers = Fanfic_Follows::get_story_followers($story_id, [
    'email_enabled' => true
]);

// 4. Create Notifications
foreach ($followers as $follower) {
    // In-app notification
    Fanfic_Notifications::create([
        'user_id' => $follower->user_id,
        'type' => 'new_chapter',
        'message' => "New chapter posted: {$chapter_title}",
        'data' => json_encode([
            'story_id' => $story_id,
            'chapter_id' => $chapter_id
        ])
    ]);

    // Queue email
    Fanfic_Email_Queue::add([
        'recipient' => $follower->email,
        'subject' => "New chapter: {$chapter_title}",
        'template' => 'new_chapter',
        'data' => [
            'story_title' => $story_title,
            'chapter_title' => $chapter_title,
            'chapter_url' => $chapter_url,
            'unsubscribe_url' => $unsubscribe_url
        ]
    ]);
}

// 5. WP-Cron Processes Queue (every 5 minutes)
// Hook: fanfic_email_queue
$batch = Fanfic_Email_Queue::get_batch(50); // Get 50 emails
foreach ($batch as $email) {
    $sent = wp_mail(
        $email['recipient'],
        $email['subject'],
        $email['body']
    );

    if ($sent) {
        Fanfic_Email_Queue::mark_sent($email['id']);
    } else {
        Fanfic_Email_Queue::mark_failed($email['id']);
    }
}
```

### Anonymous User Tracking

The system uses cookies to track anonymous users:

```php
// 1. Generate Identifier (class-fanfic-user-identifier.php)
$identifier = Fanfic_User_Identifier::get_identifier();

// For logged-in users: uses user_id
// For anonymous: generates unique hash based on:
//   - IP address
//   - User agent
//   - Random salt (stored in cookie)

// 2. Store Cookie
setcookie('fanfic_user_id', $identifier, time() + YEAR_IN_SECONDS);

// 3. Use in Database
// Logged-in: user_id = 123, identifier_hash = NULL
// Anonymous: user_id = 0, identifier_hash = 'abc123...'

// 4. Automatic Anonymization (after 60 days)
// WP-Cron daily job: fanfic_cleanup_anonymous_data
// Deletes all anonymous data older than 60 days
```

---

## Configuration

### Admin Settings

Navigate to **WordPress Admin → Fanfiction → Settings → Interactions**

#### Available Options

| Setting | Default | Options | Description |
|---------|---------|---------|-------------|
| Enable Ratings | ✅ | On/Off | Allow users to rate chapters |
| Enable Likes | ✅ | On/Off | Allow users to like chapters |
| Enable Bookmarks | ✅ | On/Off | Allow users to bookmark |
| Enable Follows | ✅ | On/Off | Allow users to follow |
| Enable Email Notifications | ✅ | On/Off | Send email notifications |
| Email Queue Batch Size | 50 | 10-200 | Emails per cron run |
| Rate Limit | 10 | 5-100 | Requests per minute |
| Anonymous Data Retention | 60 | 7-365 | Days before anonymization |
| Performance Monitoring | ❌ | On/Off | Enable performance tracking |

#### Programmatic Configuration

```php
// Enable/disable features
update_option('fanfic_enable_ratings', true);
update_option('fanfic_enable_likes', true);
update_option('fanfic_enable_bookmarks', true);

// Configure email queue
update_option('fanfic_email_queue_batch_size', 100);

// Configure rate limiting
update_option('fanfic_rate_limit_requests', 20);

// Configure data retention
update_option('fanfic_anonymous_data_retention', 90); // days
```

---

## Usage Examples

### Frontend Implementation

#### HTML Structure

```html
<!-- Rating Widget -->
<div class="fanfic-rating" data-chapter-id="123">
    <div class="fanfic-rating-stars">
        <span class="star" data-rating="1">★</span>
        <span class="star" data-rating="2">★</span>
        <span class="star" data-rating="3">★</span>
        <span class="star" data-rating="4">★</span>
        <span class="star" data-rating="5">★</span>
    </div>
    <div class="fanfic-rating-info">
        <span class="average">4.5</span>
        <span class="count">(42 ratings)</span>
    </div>
</div>

<!-- Like Button -->
<button class="fanfic-like-btn" data-chapter-id="123" data-liked="false">
    <span class="icon">♥</span>
    <span class="count">15</span> Likes
</button>

<!-- Bookmark Button -->
<button class="fanfic-bookmark-btn" data-story-id="456" data-bookmarked="false">
    <span class="icon">🔖</span>
    Bookmark
</button>

<!-- Follow Story Button -->
<button class="fanfic-follow-story-btn" data-story-id="456" data-following="false">
    <span class="icon">👁️</span>
    Follow Story
</button>

<!-- Follow Author Button -->
<button class="fanfic-follow-author-btn" data-author-id="789" data-following="false">
    <span class="icon">👤</span>
    Follow Author
</button>
```

#### JavaScript Implementation

```javascript
// The fanfiction-interactions.js file handles everything automatically
// Just ensure your HTML has the correct data attributes and classes

// Example: Manual implementation (if needed)
jQuery(document).ready(function($) {
    // Rate chapter
    $('.fanfic-rating .star').on('click', function() {
        var chapterId = $(this).closest('.fanfic-rating').data('chapter-id');
        var rating = $(this).data('rating');

        $.post(fanficInteractions.ajaxUrl, {
            action: 'fanfic_rate_chapter',
            nonce: fanficInteractions.nonce,
            chapter_id: chapterId,
            rating: rating
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                // Update UI
                $('.fanfic-rating-info .average').text(response.data.average_rating);
                $('.fanfic-rating-info .count').text('(' + response.data.total_ratings + ' ratings)');
            }
        });
    });

    // Like chapter
    $('.fanfic-like-btn').on('click', function() {
        var chapterId = $(this).data('chapter-id');
        var isLiked = $(this).data('liked');

        $.post(fanficInteractions.ajaxUrl, {
            action: 'fanfic_like_chapter',
            nonce: fanficInteractions.nonce,
            chapter_id: chapterId
        }, function(response) {
            if (response.success) {
                // Toggle liked state
                $('.fanfic-like-btn').data('liked', !isLiked);
                $('.fanfic-like-btn .count').text(response.data.like_count);

                // Update icon
                if (response.data.liked) {
                    $('.fanfic-like-btn').addClass('liked');
                } else {
                    $('.fanfic-like-btn').removeClass('liked');
                }
            }
        });
    });
});
```

#### PHP Integration (in Theme)

```php
<?php
// In single-fanfiction_chapter.php or template

// Get chapter rating
$chapter_id = get_the_ID();
$rating_data = Fanfic_Rating_System::get_chapter_rating($chapter_id);
?>

<div class="fanfic-chapter-meta">
    <!-- Rating Display -->
    <div class="fanfic-rating" data-chapter-id="<?php echo esc_attr($chapter_id); ?>">
        <div class="fanfic-rating-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star <?php echo $i <= $rating_data['average'] ? 'filled' : ''; ?>" data-rating="<?php echo $i; ?>">★</span>
            <?php endfor; ?>
        </div>
        <div class="fanfic-rating-info">
            <span class="average"><?php echo number_format($rating_data['average'], 1); ?></span>
            <span class="count">(<?php echo $rating_data['count']; ?> ratings)</span>
        </div>
    </div>

    <!-- Like Button -->
    <?php
    $like_data = Fanfic_Like_System::get_chapter_likes($chapter_id);
    $user_liked = Fanfic_Like_System::has_user_liked($chapter_id);
    ?>
    <button class="fanfic-like-btn <?php echo $user_liked ? 'liked' : ''; ?>"
            data-chapter-id="<?php echo esc_attr($chapter_id); ?>"
            data-liked="<?php echo $user_liked ? 'true' : 'false'; ?>">
        <span class="icon">♥</span>
        <span class="count"><?php echo $like_data['count']; ?></span> Likes
    </button>

    <!-- Bookmark Button (logged-in only) -->
    <?php if (is_user_logged_in()): ?>
        <?php
        $story_id = wp_get_post_parent_id($chapter_id);
        $is_bookmarked = Fanfic_Bookmarks::is_bookmarked($story_id);
        ?>
        <button class="fanfic-bookmark-btn <?php echo $is_bookmarked ? 'bookmarked' : ''; ?>"
                data-story-id="<?php echo esc_attr($story_id); ?>"
                data-bookmarked="<?php echo $is_bookmarked ? 'true' : 'false'; ?>">
            <span class="icon">🔖</span>
            <?php echo $is_bookmarked ? 'Bookmarked' : 'Bookmark'; ?>
        </button>
    <?php endif; ?>
</div>
```

---

## Testing Guide

### Manual Testing Checklist

#### Database Tests

```bash
# Check tables exist
wp db query "SHOW TABLES LIKE 'wp_fanfic_%'"

# Check table statistics
SELECT
    COUNT(*) as total_ratings,
    AVG(rating) as avg_rating
FROM wp_fanfic_ratings;

SELECT COUNT(*) as total_likes FROM wp_fanfic_likes;
SELECT COUNT(*) as total_bookmarks FROM wp_fanfic_bookmarks;
SELECT COUNT(*) as total_follows FROM wp_fanfic_follows;
```

#### Rating System Tests

1. **Logged-in User:**
   - Navigate to a chapter
   - Click on a star (e.g., 4 stars)
   - Verify success message
   - Verify rating saved (check database)
   - Try changing rating
   - Verify rating updated

2. **Anonymous User:**
   - Log out
   - Navigate to a chapter
   - Click on a star
   - Verify cookie created (`fanfic_user_id`)
   - Verify rating saved with `identifier_hash`
   - Close browser and reopen
   - Verify rating persists (cookie-based)

#### Like System Tests

1. **Like a Chapter:**
   - Click like button
   - Verify button changes state
   - Verify count increases
   - Click again (unlike)
   - Verify count decreases

2. **Anonymous Likes:**
   - Log out
   - Like a chapter
   - Verify cookie tracking
   - Close and reopen browser
   - Verify like persists

#### Bookmark Tests

1. **Add Bookmark:**
   - Click bookmark button (logged-in only)
   - Verify success message
   - Navigate to bookmarks page
   - Verify story appears

2. **Remove Bookmark:**
   - Click bookmark button again
   - Verify removed from list

#### Follow Tests

1. **Follow Story:**
   - Click follow story button
   - Verify success message
   - Go to followed stories page
   - Verify story appears

2. **Follow Author:**
   - Click follow author button
   - Verify success message
   - Go to followed authors page
   - Verify author appears

#### Email Notification Tests

1. **Email Queue:**
   - Follow a story
   - Publish new chapter (as different user)
   - Check database: `wp_fanfic_notifications`
   - Wait 5 minutes (or manually trigger cron)
   - Verify email received

2. **Email-Only Subscription:**
   - Enter email (not logged in)
   - Verify verification email sent
   - Click verification link
   - Publish new chapter
   - Verify notification email received

#### Security Tests

1. **Rate Limiting:**
   - Make 10 AJAX requests rapidly
   - Verify 11th request fails with rate limit error

2. **Nonce Verification:**
   - Modify nonce in browser console
   - Make AJAX request
   - Verify request fails

3. **Anonymous Anonymization:**
   - Create anonymous rating (as guest)
   - Manually run cleanup: `wp cron event run fanfic_cleanup_anonymous_data`
   - Verify old data (60+ days) deleted

#### Performance Tests

1. **Cache Verification:**
   - Rate a chapter
   - Check cache: `wp transient list`
   - Verify rating cached
   - Wait for cache expiration (or delete manually)
   - Verify cache regenerates

2. **Batch Loading:**
   - Load a page with many stories
   - Check database queries
   - Verify batch loading reduces queries

---

## Troubleshooting

### Issue: AJAX Requests Return 403 Forbidden

**Cause:** Nonce verification failing or rate limit exceeded

**Solutions:**
1. Check nonce is being passed correctly in JavaScript
2. Verify nonce is valid (not expired)
3. Check if rate limit is being hit (slow down requests)
4. Disable rate limiting temporarily for testing:
   ```php
   update_option('fanfic_rate_limit_enabled', false);
   ```

### Issue: Database Tables Not Created

**Cause:** Activation hook didn't run or database error

**Solutions:**
1. Manually create tables:
   ```php
   Fanfic_Database_Setup::init();
   ```
2. Check database permissions
3. Check for SQL errors in debug log
4. Verify `$wpdb->prefix` is correct

### Issue: Emails Not Being Sent

**Cause:** WP-Cron not running or email configuration issue

**Solutions:**
1. Check if WP-Cron is enabled:
   ```php
   defined('DISABLE_WP_CRON') // Should be false or undefined
   ```
2. Manually trigger cron:
   ```bash
   wp cron event run fanfic_email_queue
   ```
3. Check email queue:
   ```sql
   SELECT * FROM wp_fanfic_notifications WHERE is_read = 0 LIMIT 10;
   ```
4. Test WordPress email:
   ```php
   wp_mail('test@example.com', 'Test', 'Test message');
   ```

### Issue: Anonymous Users Can't Rate/Like

**Cause:** Cookie not being set or privacy mode blocking cookies

**Solutions:**
1. Check cookies in browser developer tools
2. Verify cookie domain is correct
3. Check if browser is blocking third-party cookies
4. Test in incognito mode (without extensions)
5. Enable fallback to IP-based tracking (less reliable):
   ```php
   update_option('fanfic_anonymous_use_ip', true);
   ```

### Issue: Cache Not Invalidating

**Cause:** Cache hooks not registered or cache persistence issue

**Solutions:**
1. Manually clear cache:
   ```php
   Fanfic_Cache_Manager::clear_all_cache();
   ```
2. Check cache hooks are registered:
   ```php
   has_action('transition_post_status', 'Fanfic_Cache_Hooks::invalidate_on_post_save');
   ```
3. Verify object cache is working:
   ```php
   wp_cache_get('test_key'); // Should return false if empty
   wp_cache_set('test_key', 'test_value', '', 60);
   wp_cache_get('test_key'); // Should return 'test_value'
   ```

### Issue: Rate Limiting Too Strict

**Cause:** Default rate limit (10 req/min) too low for your use case

**Solutions:**
1. Increase rate limit:
   ```php
   update_option('fanfic_rate_limit_requests', 50);
   ```
2. Whitelist trusted users:
   ```php
   // In class-fanfic-rate-limit.php
   if (current_user_can('manage_options')) {
       return; // Skip rate limiting for admins
   }
   ```
3. Disable rate limiting (not recommended for production):
   ```php
   update_option('fanfic_rate_limit_enabled', false);
   ```

### Issue: Performance Issues on High-Traffic Sites

**Cause:** Database queries not optimized or cache not working

**Solutions:**
1. Enable object cache (Redis/Memcached)
2. Optimize database:
   ```php
   Fanfic_Database_Setup::optimize_tables();
   ```
3. Increase cache TTL:
   ```php
   // In class-fanfic-cache-manager.php
   define('FANFIC_CACHE_TTL', HOUR_IN_SECONDS * 2); // 2 hours instead of 1
   ```
4. Enable performance monitoring:
   ```php
   update_option('fanfic_enable_performance_monitor', true);
   ```

---

## Performance Optimization

### Database Optimization

#### Indexes

All tables have proper indexes for common queries:

```sql
-- Ratings
KEY idx_chapter_rating (chapter_id, rating)
KEY idx_created (created_at)
UNIQUE KEY unique_user_rating (chapter_id, user_id)

-- Likes
KEY idx_chapter (chapter_id)
KEY idx_user (user_id)
UNIQUE KEY unique_user_like (chapter_id, user_id)

-- Bookmarks
KEY idx_user_type (user_id, bookmark_type)
KEY idx_created (created_at)
UNIQUE KEY unique_bookmark (user_id, post_id, bookmark_type)

-- Follows
KEY idx_target_type (target_id, follow_type)
KEY idx_user_type (user_id, follow_type)
UNIQUE KEY unique_follow (user_id, target_id, follow_type)
```

#### Query Optimization

Use batch loading to reduce N+1 queries:

```php
// Bad: N+1 query problem
foreach ($stories as $story) {
    $rating = Fanfic_Rating_System::get_story_rating($story->ID);
    echo "Rating: {$rating['average']}";
}

// Good: Batch loading
$story_ids = wp_list_pluck($stories, 'ID');
$ratings = Fanfic_Batch_Loader::get_story_ratings($story_ids);

foreach ($stories as $story) {
    $rating = $ratings[$story->ID];
    echo "Rating: {$rating['average']}";
}
```

### Caching Strategy

#### Object Cache (Recommended)

Install Redis or Memcached for best performance:

```bash
# Install Redis
apt-get install redis-server php-redis

# Install WordPress Redis plugin
wp plugin install redis-cache --activate
wp redis enable
```

#### Transient Cache (Default)

The system uses WordPress transients by default:

```php
// Cache story rating for 1 hour
$cache_key = 'fanfic_story_rating_' . $story_id;
$rating = get_transient($cache_key);

if (false === $rating) {
    $rating = Fanfic_Rating_System::calculate_story_rating($story_id);
    set_transient($cache_key, $rating, HOUR_IN_SECONDS);
}
```

#### Cache Invalidation

Cache is automatically invalidated when:

- New rating/like added
- Story/chapter updated
- Chapter published/unpublished
- Follow added/removed

Manual cache clearing:

```php
// Clear all caches
Fanfic_Cache_Manager::clear_all_cache();

// Clear specific cache
Fanfic_Cache_Manager::invalidate_story_cache($story_id);
Fanfic_Cache_Manager::invalidate_chapter_cache($chapter_id);
```

### Email Queue Optimization

#### Batch Size

Adjust batch size based on your server:

```php
// Low-traffic site (shared hosting): 20-50 emails/batch
update_option('fanfic_email_queue_batch_size', 20);

// Medium-traffic site (VPS): 50-100 emails/batch
update_option('fanfic_email_queue_batch_size', 50);

// High-traffic site (dedicated server): 100-200 emails/batch
update_option('fanfic_email_queue_batch_size', 100);
```

#### Cron Frequency

Adjust cron frequency based on your needs:

```php
// Default: Every 5 minutes
wp_schedule_event(time(), 'fanfic_every_5_minutes', 'fanfic_email_queue');

// More frequent: Every 1 minute (requires custom cron schedule)
add_filter('cron_schedules', function($schedules) {
    $schedules['fanfic_every_1_minute'] = [
        'interval' => 60,
        'display' => __('Every Minute')
    ];
    return $schedules;
});

wp_schedule_event(time(), 'fanfic_every_1_minute', 'fanfic_email_queue');
```

### Anonymous Data Cleanup

#### Retention Period

Balance privacy and data preservation:

```php
// Aggressive: 7 days
update_option('fanfic_anonymous_data_retention', 7);

// Balanced: 30 days
update_option('fanfic_anonymous_data_retention', 30);

// Conservative: 90 days (default: 60)
update_option('fanfic_anonymous_data_retention', 90);
```

#### Manual Cleanup

Run cleanup manually when needed:

```bash
# Via WP-CLI
wp cron event run fanfic_cleanup_anonymous_data

# Via PHP
do_action('fanfic_cleanup_anonymous_data');
```

---

## Security Best Practices

### 1. Nonce Verification

All AJAX requests require nonce verification:

```php
// In AJAX handler
$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

if (!wp_verify_nonce($nonce, 'fanfic_ajax_nonce')) {
    wp_send_json_error(['message' => 'Invalid nonce']);
}
```

### 2. Capability Checks

Check user capabilities before allowing actions:

```php
// Only logged-in users can bookmark
if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Login required']);
}

// Only authors can delete their own stories
if (!current_user_can('delete_fanfiction_story', $story_id)) {
    wp_send_json_error(['message' => 'Permission denied']);
}
```

### 3. Input Validation

Always validate and sanitize input:

```php
// Validate chapter ID
$chapter_id = isset($_POST['chapter_id']) ? absint($_POST['chapter_id']) : 0;
if ($chapter_id <= 0) {
    wp_send_json_error(['message' => 'Invalid chapter ID']);
}

// Validate rating (1-5)
$rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;
if ($rating < 1 || $rating > 5) {
    wp_send_json_error(['message' => 'Rating must be between 1 and 5']);
}

// Validate email
$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
if (!is_email($email)) {
    wp_send_json_error(['message' => 'Invalid email address']);
}
```

### 4. Rate Limiting

Prevent abuse with rate limiting:

```php
// Check rate limit before processing
$rate_limit = Fanfic_Rate_Limit::check();
if (!$rate_limit['allowed']) {
    wp_send_json_error([
        'message' => 'Too many requests. Please wait ' . $rate_limit['retry_after'] . ' seconds.',
        'retry_after' => $rate_limit['retry_after']
    ]);
}
```

### 5. SQL Injection Prevention

Always use prepared statements:

```php
// Bad: Direct SQL (vulnerable)
$wpdb->query("SELECT * FROM {$wpdb->prefix}fanfic_ratings WHERE chapter_id = {$chapter_id}");

// Good: Prepared statement (safe)
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}fanfic_ratings WHERE chapter_id = %d",
    $chapter_id
));
```

### 6. XSS Prevention

Always escape output:

```php
// Bad: Direct output (vulnerable)
echo $user_input;

// Good: Escaped output (safe)
echo esc_html($user_input);
echo esc_attr($user_input); // For attributes
echo esc_url($url); // For URLs
```

### 7. CSRF Prevention

Use nonces for all form submissions:

```html
<!-- In form -->
<?php wp_nonce_field('fanfic_bookmark_action', 'fanfic_bookmark_nonce'); ?>

<!-- In handler -->
<?php
if (!isset($_POST['fanfic_bookmark_nonce']) ||
    !wp_verify_nonce($_POST['fanfic_bookmark_nonce'], 'fanfic_bookmark_action')) {
    wp_die('Security check failed');
}
?>
```

### 8. Anonymous User Tracking

Use secure, privacy-respecting methods:

```php
// Generate identifier based on:
// - IP address (hashed)
// - User agent (hashed)
// - Random salt (stored in cookie)

$identifier = hash('sha256',
    $_SERVER['REMOTE_ADDR'] .
    $_SERVER['HTTP_USER_AGENT'] .
    $salt
);

// Store in secure, httponly cookie
setcookie('fanfic_user_id', $identifier, [
    'expires' => time() + YEAR_IN_SECONDS,
    'path' => '/',
    'secure' => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

---

## Future Enhancements

### Planned Features (Not Yet Implemented)

1. **REST API Endpoints**
   - RESTful API in addition to AJAX
   - Better for mobile apps and third-party integrations
   - OAuth authentication

2. **WebSocket Support**
   - Real-time notifications
   - Live rating/like updates
   - No page refresh needed

3. **Advanced Analytics**
   - Author dashboard with charts
   - Story performance metrics
   - Reader engagement analytics
   - Trending stories

4. **Batch Operations**
   - Bulk bookmark management
   - Mass notification sending
   - Batch import/export

5. **User Data Export**
   - GDPR compliance
   - Export all user interactions
   - Delete account with data

6. **Multi-Language Email Templates**
   - Localized emails
   - User language preferences
   - Template translation interface

7. **Advanced Notification Filters**
   - Fine-grained notification preferences
   - Quiet hours (no emails at night)
   - Digest mode (daily/weekly summary)

8. **Social Sharing**
   - Share stories to social media
   - Social login integration
   - Social proof (X users liked this)

9. **Gamification**
   - Badges for readers (e.g., "Read 100 chapters")
   - Leaderboards (top raters, most active)
   - Achievement system

10. **AI-Powered Features**
    - Content recommendations
    - Similar story suggestions
    - Automated tagging

---

## Support & Documentation

### Documentation Files

- **INTEGRATION_STATUS.md** - Technical integration details
- **docs/overview.md** - High-level plugin overview
- **docs/data-models.md** - Database schema
- **docs/features.md** - Feature documentation
- **docs/coding.md** - Coding guidelines

### Code Comments

All classes and methods are thoroughly commented:

```php
/**
 * Rate a chapter
 *
 * @param int $chapter_id Chapter post ID
 * @param int $rating Rating value (1-5)
 * @param int|null $user_id User ID (null for current user)
 * @return array|WP_Error Array with rating data on success, WP_Error on failure
 */
public static function rate_chapter($chapter_id, $rating, $user_id = null) {
    // Implementation...
}
```

### Getting Help

1. **Check Documentation** - Read this file and other docs
2. **Enable Debug Mode** - Set `WP_DEBUG` to `true`
3. **Check Error Logs** - Look in `wp-content/debug.log`
4. **Browser Console** - Check for JavaScript errors
5. **Database Queries** - Use Query Monitor plugin
6. **Community Support** - Post in WordPress forums

### Reporting Issues

When reporting issues, include:

1. **WordPress Version** - `wp --version`
2. **Plugin Version** - Check `fanfiction-manager.php` header
3. **PHP Version** - `php --version`
4. **Error Messages** - Copy exact error text
5. **Steps to Reproduce** - How to trigger the issue
6. **Expected vs Actual** - What should happen vs what does happen
7. **Environment** - Shared hosting, VPS, localhost, etc.

---

## Appendix A: Database Schema

### wp_fanfic_ratings

```sql
CREATE TABLE wp_fanfic_ratings (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    chapter_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED DEFAULT NULL,
    rating tinyint(1) UNSIGNED NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_rating (chapter_id, user_id),
    KEY idx_chapter_rating (chapter_id, rating),
    KEY idx_created (created_at)
);
```

### wp_fanfic_likes

```sql
CREATE TABLE wp_fanfic_likes (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    chapter_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_like (chapter_id, user_id),
    KEY idx_chapter (chapter_id),
    KEY idx_user (user_id)
);
```

### wp_fanfic_reading_progress

```sql
CREATE TABLE wp_fanfic_reading_progress (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    story_id bigint(20) UNSIGNED NOT NULL,
    chapter_number int(11) UNSIGNED NOT NULL,
    marked_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_progress (user_id, story_id, chapter_number),
    KEY idx_user_story (user_id, story_id),
    KEY idx_story (story_id)
);
```

### wp_fanfic_bookmarks

```sql
CREATE TABLE wp_fanfic_bookmarks (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    post_id bigint(20) UNSIGNED NOT NULL,
    bookmark_type enum('story','chapter') NOT NULL DEFAULT 'story',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_bookmark (user_id, post_id, bookmark_type),
    KEY idx_user_type (user_id, bookmark_type),
    KEY idx_created (created_at)
);
```

### wp_fanfic_follows

```sql
CREATE TABLE wp_fanfic_follows (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    target_id bigint(20) UNSIGNED NOT NULL,
    follow_type enum('story','author') NOT NULL,
    email_enabled tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_follow (user_id, target_id, follow_type),
    KEY idx_target_type (target_id, follow_type),
    KEY idx_user_type (user_id, follow_type)
);
```

### wp_fanfic_email_subscriptions

```sql
CREATE TABLE wp_fanfic_email_subscriptions (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    email varchar(255) NOT NULL,
    target_id bigint(20) UNSIGNED NOT NULL,
    subscription_type enum('story','author') NOT NULL,
    token varchar(64) NOT NULL,
    verified tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_subscription (email, target_id, subscription_type),
    KEY idx_token (token),
    KEY idx_target_type (target_id, subscription_type)
);
```

### wp_fanfic_notifications

```sql
CREATE TABLE wp_fanfic_notifications (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    type varchar(50) NOT NULL,
    message text NOT NULL,
    data longtext DEFAULT NULL,
    is_read tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_read (user_id, is_read),
    KEY idx_created (created_at),
    KEY idx_type (type)
);
```

---

## Appendix B: AJAX Endpoint Reference

### fanfic_rate_chapter

**Description:** Rate a chapter on a scale of 1-5 stars

**Parameters:**
- `chapter_id` (int, required) - Chapter post ID
- `rating` (int, required) - Rating value (1-5)
- `nonce` (string, required) - Security nonce

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Rating submitted!",
        "average_rating": 4.2,
        "total_ratings": 15,
        "user_rating": 4
    }
}
```

**Errors:**
- Invalid nonce: `403 Forbidden`
- Rate limited: `429 Too Many Requests`
- Invalid chapter ID: `400 Bad Request`
- Invalid rating value: `400 Bad Request`

---

### fanfic_like_chapter

**Description:** Like or unlike a chapter

**Parameters:**
- `chapter_id` (int, required) - Chapter post ID
- `nonce` (string, required) - Security nonce

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Liked!",
        "like_count": 23,
        "liked": true
    }
}
```

---

### fanfic_bookmark_story

**Description:** Add or remove a story bookmark

**Parameters:**
- `story_id` (int, required) - Story post ID
- `nonce` (string, required) - Security nonce

**Requires:** Logged-in user

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Bookmark added!",
        "bookmarked": true
    }
}
```

---

### fanfic_follow_story

**Description:** Follow or unfollow a story

**Parameters:**
- `story_id` (int, required) - Story post ID
- `email_enabled` (bool, optional) - Enable email notifications (default: true)
- `nonce` (string, required) - Security nonce

**Requires:** Logged-in user

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Now following!",
        "following": true,
        "email_enabled": true
    }
}
```

---

### fanfic_follow_author

**Description:** Follow or unfollow an author

**Parameters:**
- `author_id` (int, required) - Author user ID
- `email_enabled` (bool, optional) - Enable email notifications (default: true)
- `nonce` (string, required) - Security nonce

**Requires:** Logged-in user

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Now following!",
        "following": true,
        "email_enabled": true
    }
}
```

---

## Changelog

### Version 1.0.15 (2025-11-13)

- ✅ Integration complete (Phases 1-5)
- ✅ All systems operational
- ✅ Documentation complete
- ✅ Ready for production

### Version 1.0.14 (2025-11-12)

- Phase 5 complete: Frontend integration
- AJAX handlers unified
- JavaScript interactions implemented

### Version 1.0.13 (2025-11-11)

- Phase 4 complete: Security & performance
- Rate limiting implemented
- Cache manager enhanced

### Version 1.0.12 (2025-11-10)

- Phase 3 complete: Email & notifications
- Email queue system implemented
- In-app notifications working

### Version 1.0.11 (2025-11-09)

- Phase 2 complete: Core interaction logic
- All interaction systems implemented
- Anonymous user support added

### Version 1.0.10 (2025-11-08)

- Phase 1 complete: Database infrastructure
- 7 custom tables created
- Version tracking implemented

---

**END OF DOCUMENT**

For the most up-to-date information, refer to:
- INTEGRATION_STATUS.md
- docs/ directory
- Code comments in source files
