# User Interactions System - Complete Implementation & Optimization Guide

## Executive Summary

This document provides the complete user interaction system implementation for the Fanfiction Manager plugin, covering ratings, likes, follows, follows (with optional email subscriptions), reading progress, notifications, and email delivery. It includes **all original audit information** plus **pragmatic optimization recommendations** tailored to medium communities (100-500 DAU) on shared hosting with minimal memory constraints.

**Key Principle**: Build for **simplicity and clarity** first, optimize with **batch operations and proper indexing**, avoid **over-caching and unnecessary complexity**.

**Critical Optimizations Added**:
- Cookie-based anonymous actions (replacing IP tracking)
- Batch query operations to eliminate N+1 problems
- WordPress native functions instead of custom solutions
- Async email processing via WP-Cron
- Incremental cache updates for ratings/likes

**Terminology Clarification**:
- **Follow**: Logged-in users track stories/authors. Generates in-app dashboard notifications + optional email notifications. Users can toggle email notifications per follow. Notifies content creators.
- **Subscribe**: Email-only subscription for ANY user (logged-in or anonymous via email). Users can unsubscribe via email link or unsubscribe page with email parameter.
- ** Follow** - generates a static link to the story or chapter, on the user dashboard
- **Single Follow Table**: Tracks both story and author follows (unified under one "follow" action type).

---

## Part 1: Understanding Your Full System Scope

### The Complete Interaction Model

Your plugin manages **two distinct notification flows**, all of these actions are outputed on notification section of the user dashboard:

#### **Flow 1: Author/Content Creator Notifications**
```
User Action                          → Author Gets Notified (In-App + Email)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Chapter receives rating              → ✅ Rating received (count + notification)
Chapter receives like                → ✅ Like received (count + notification)
Chapter/Story receives comment       → ✅ Comment received (notification + email reply)
Story/Chapter updated                → ✅ Manual action (creator updates content)
User follows author                  → ✅ "User Marcelo is following you" (notification + email)
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

#### **Flow 3: Subscriber Notifications (Any User, logged in or not - Email Only)**
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
| **Ratings** (chapter) | ✅ | ✅ (Cookie) | ✅ | Author only | No | Count + avg stars |
| **Likes** (chapter) | ✅ | ✅ (Cookie) | ✅ | Author only | No | Count |
| **Comments** (story/chapter) | ✅ | ❌ | ✅ | Author + repliers | No | Thread |
| **Reading Progress** (mark as read) | ✅ Only | - | ✅ | No | No | "Read" badge |
| **Follows** (story) | ✅ Only | - | ✅ | No | No | User library |
| **Chapter Follows** | ✅ Only | - | ✅ | No | No | User library |
| **Follow Story** (logged-in) | ✅ Only | - | ✅ | ✅ | ✅ Toggle | Follower count |
| **Follow Author** (logged-in) | ✅ Only | - | ✅ | ✅ | ✅ Toggle | Follower count |
| **Subscribe Story** (email) | Both | ✅ | ✅ | No | ✅ Unsubscribe link | - |
| **Subscribe Author** (email) | Both | ✅ | ✅ | No | ✅ Unsubscribe link | - |
| **View Counts** | Both | ✅ (Cookie) | ✅ (1/day) | No | No | Story + chapters |

### Key System Insights

**CRITICAL CHANGE: Cookie-Based Anonymous Actions**

Anonymous users can rate and like chapters using cookies instead of IP tracking:
- **Cookie Duration**: until user deletes it from the browser
- **Cookie Names**: `fanfic_rate_{chapter_id}`, `fanfic_like_{chapter_id}`
- **No Database Cleanup Needed**: Cookies expire automatically
- **Better Privacy**: No IP storage, GDPR compliant
- **Better UX**: Works properly for users behind same NAT

1. **Follow vs Subscribe**:
   - **Follow**: Logged-in users only. Tracked in DB, generates in-app dashboard notifications. Email is OPTIONAL per-follow toggle. Creator gets notified.
   - **Subscribe**: Anyone (logged-in or anonymous email). Email only, no in-app notification. Unsubscribe via email link or unsubscribe page.

2. **Two-Tier Follow System**:
   - Single `wp_fanfic_follows` table tracks both story and author follows (distinguish by `follow_type` column: 'story' or 'author')
   - Same table for both; reduces complexity vs separate tables

3. **Email Preferences (Follow)**:
   - Logged-in user follows story/author
   - Can toggle email notifications ON/OFF per follow (stored in `email_enabled` column)
   - Default: Email disabled (user can opt-in on dashboard)

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

8. **Counters**: Rating count, like count, follow count, follower count all displayed on story/chapter pages

9. **Unified Path**: All actions (rate, like, follow, read, follow) go through same recording system for consistency

---

## Part 2: Current Implementation Analysis

### 1. Reading Progress (Mark as Read)

**Current Implementation**: `wp_fanfic_reading_progress` table + `ajax_mark_as_read()` in `class-fanfic-shortcodes-actions.php`

**Current Performance Issues**:
- ❌ **N+1 query problem**: Story view with 50 chapters requires 50+ DB queries to check read status for display
- ❌ **No batch loading**: Cannot efficiently fetch read status for multiple chapters in one query
- ✅ **Data stored correctly**: UNIQUE key on (story_id, user_id) prevents duplicates

**OPTIMIZATION REQUIRED**:
```php
// WRONG WAY (current):
foreach ($chapters as $chapter) {
    $is_read = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM wp_fanfic_reading_progress 
         WHERE user_id = %d AND story_id = %d AND chapter_number = %d",
        $user_id, $story_id, $chapter->number
    ));
}

// RIGHT WAY (optimized):
// Load ALL at once before loop
$read_chapters = $wpdb->get_col($wpdb->prepare(
    "SELECT chapter_number FROM wp_fanfic_reading_progress
     WHERE user_id = %d AND story_id = %d",
    $user_id, $story_id
));
$read_map = array_flip($read_chapters); // O(1) lookup

// In loop:
foreach ($chapters as $chapter) {
    $is_read = isset($read_map[$chapter->number]);
}
```

### 2. Follows (Story)

**Current Implementation**: `wp_fanfic_follows` table + `class-fanfic-follows.php`

**Current Strengths**:
- ✅ Simple schema with proper indexes on (user_id, created_at)
- ✅ UNIQUE constraint prevents duplicate follows

**Current Gaps**:
- ❌ **Story-only**: No chapter follows (user requested feature)
- ❌ **Caching not optimized**: Uses transient cache but invalidates on every change

**Implementation Strategy**:
- Add `follow_type` column to support chapter follows
- Remove aggressive caching; rely on optimized queries + WordPress page cache
- Keep data simple, let database indexes do the work

### 3. Story Follows (Logged-In Users)

**Current Implementation**: **PARTIALLY EXISTS** (author follows exist, but not story follows)

**User Request**:
> "Users can follow a story to receive notifications on their dashboard when new chapters are added, updated"

**Needed for**:
- Logged-in users to track specific stories (different from following author)
- In-app notifications when followed story gets new chapter or when story changes taxonomy or status.
- Email notifications (optional, per-user dashboard) when followed story updates
- Author gets notified when someone follows their story

**Implementation Strategy**:
- Extend existing `wp_fanfic_follows` table with `follow_type` column ('story' or 'author')
- Add `email_enabled` column to track per-follow email preference
- Batch notification creation when chapter published
- Trigger: `transition_post_status` hook on chapter publish

### 4. Email Subscriptions (Any User - Anonymous Friendly)

**Current Implementation**: **NOT FULLY IMPLEMENTED**

**Needed for**:
- Anonymous users to subscribe via email only
- Secure unsubscribe links in emails
- Token-based verification

---

## Part 3: Database Schema (Complete)

### 3.1 Ratings Table
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_ratings` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `chapter_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL, -- NULL for anonymous
    `rating` tinyint(1) UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_rating` (`chapter_id`, `user_id`),
    KEY `idx_chapter_rating` (`chapter_id`, `rating`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Likes Table
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_likes` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `chapter_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL, -- NULL for anonymous
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_like` (`chapter_id`, `user_id`),
    KEY `idx_chapter` (`chapter_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Reading Progress Table
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_reading_progress` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `story_id` bigint(20) UNSIGNED NOT NULL,
    `chapter_number` int(11) UNSIGNED NOT NULL,
    `marked_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_progress` (`user_id`, `story_id`, `chapter_number`),
    KEY `idx_user_story` (`user_id`, `story_id`),
    KEY `idx_story` (`story_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Follows Table (Updated)
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_follows` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `post_id` bigint(20) UNSIGNED NOT NULL,
    `follow_type` enum('story','chapter') NOT NULL DEFAULT 'story',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_follow` (`user_id`, `post_id`, `follow_type`),
    KEY `idx_user_type` (`user_id`, `follow_type`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 Follows Table (Unified)
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_follows` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `target_id` bigint(20) UNSIGNED NOT NULL, -- story_id or author_id
    `follow_type` enum('story','author') NOT NULL,
    `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_follow` (`user_id`, `target_id`, `follow_type`),
    KEY `idx_target_type` (`target_id`, `follow_type`),
    KEY `idx_user_type` (`user_id`, `follow_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.6 Email Subscriptions Table
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_email_subscriptions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `target_id` bigint(20) UNSIGNED NOT NULL,
    `subscription_type` enum('story','author') NOT NULL,
    `token` varchar(64) NOT NULL,
    `verified` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_subscription` (`email`, `target_id`, `subscription_type`),
    KEY `idx_token` (`token`),
    KEY `idx_target_type` (`target_id`, `subscription_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.7 Notifications Table
```sql
CREATE TABLE IF NOT EXISTS `wp_fanfic_notifications` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `type` varchar(50) NOT NULL,
    `message` text NOT NULL,
    `data` longtext DEFAULT NULL, -- JSON encoded
    `is_read` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`),
    KEY `idx_created` (`created_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Part 4: Implementation Details

### 4.1 Rating System Implementation

**Core Functionality:**
```php
class Fanfic_Rating_System {
    
    /**
     * Submit rating with cookie-based anonymous support
     */
    public static function submit_rating($chapter_id, $rating, $user_id = null) {
        global $wpdb;
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            return new WP_Error('invalid_rating', 'Rating must be between 1 and 5');
        }
        
        // Check if anonymous ratings allowed
        if (!$user_id && !get_option('fanfic_allow_anonymous_ratings', true)) {
            return new WP_Error('login_required', 'You must be logged in to rate');
        }
        
        // Check cookie for anonymous users
        if (!$user_id) {
            $cookie_name = 'fanfic_rate_' . $chapter_id;
            if (isset($_COOKIE[$cookie_name])) {
                return new WP_Error('already_rated', 'You have already rated this chapter');
            }
        }
        
        $table = $wpdb->prefix . 'fanfic_ratings';
        
        if ($user_id) {
            // Logged-in user - use REPLACE for upsert
            $result = $wpdb->replace(
                $table,
                [
                    'chapter_id' => $chapter_id,
                    'user_id' => $user_id,
                    'rating' => $rating,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%s']
            );
        } else {
            // Anonymous user - insert only
            $result = $wpdb->insert(
                $table,
                [
                    'chapter_id' => $chapter_id,
                    'rating' => $rating,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s']
            );
            
            // Set cookie
            $expire = time() + (30 * DAY_IN_SECONDS);
            setcookie($cookie_name, $rating, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        
        // Update cache incrementally
        self::update_rating_cache_incrementally($chapter_id, $rating);
        
        // Notify author
        self::notify_author_of_rating($chapter_id, $rating, $user_id);
        
        return true;
    }
    
    /**
     * Incremental cache update (v2.0 approach)
     */
    private static function update_rating_cache_incrementally($chapter_id, $new_rating) {
        $cache_key = "fanfic_chapter_{$chapter_id}_ratings";
        $cache = wp_cache_get($cache_key, 'fanfic');
        
        if (false !== $cache && is_array($cache)) {
            // Update incrementally using math
            $old_total = $cache['total_votes'];
            $old_avg = $cache['average'];
            
            $cache['total_votes']++;
            $cache['average'] = (($old_total * $old_avg) + $new_rating) / $cache['total_votes'];
            $cache["star_{$new_rating}"]++;
            
            wp_cache_set($cache_key, $cache, 'fanfic', DAY_IN_SECONDS);
        } else {
            // Rebuild cache from database
            self::rebuild_rating_cache($chapter_id);
        }
    }
}
```

### 4.2 Batch Loading for Display

**Optimized Chapter Display:**
```php
class Fanfic_Display_Optimizer {
    
    /**
     * Load all interaction data for multiple chapters in ONE query
     */
    public static function batch_load_chapter_data($chapter_ids, $user_id = null) {
        global $wpdb;
        
        if (empty($chapter_ids)) {
            return [];
        }
        
        $chapter_ids = array_map('intval', $chapter_ids);
        $chapter_ids_str = implode(',', $chapter_ids);
        
        // Build the mega-query
        $query = "
            SELECT 
                c.id as chapter_id,
                
                -- Rating stats
                COUNT(DISTINCT r.id) as rating_count,
                AVG(r.rating) as avg_rating,
                
                -- Like stats
                COUNT(DISTINCT l.id) as like_count
        ";
        
        // Add user-specific data if logged in
        if ($user_id) {
            $query .= ",
                MAX(CASE WHEN r_user.user_id = %d THEN r_user.rating ELSE NULL END) as user_rating,
                MAX(CASE WHEN l_user.user_id = %d THEN 1 ELSE 0 END) as user_liked
            ";
        }
        
        $query .= "
            FROM (
                SELECT id FROM {$wpdb->posts} 
                WHERE id IN ($chapter_ids_str)
            ) c
            LEFT JOIN {$wpdb->prefix}fanfic_ratings r ON r.chapter_id = c.id
            LEFT JOIN {$wpdb->prefix}fanfic_likes l ON l.chapter_id = c.id
        ";
        
        if ($user_id) {
            $query .= "
                LEFT JOIN {$wpdb->prefix}fanfic_ratings r_user 
                    ON r_user.chapter_id = c.id AND r_user.user_id = %d
                LEFT JOIN {$wpdb->prefix}fanfic_likes l_user 
                    ON l_user.chapter_id = c.id AND l_user.user_id = %d
            ";
        }
        
        $query .= " GROUP BY c.id";
        
        // Prepare and execute
        if ($user_id) {
            $query = $wpdb->prepare($query, $user_id, $user_id, $user_id, $user_id);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Index by chapter_id
        $data = [];
        foreach ($results as $row) {
            $data[$row['chapter_id']] = $row;
        }
        
        return $data;
    }
}
```

### 4.3 Email Queue System with WP-Cron

**Async Email Processing:**
```php
class Fanfic_Email_Queue {
    
    /**
     * Hook into chapter publish event
     */
    public static function init() {
        add_action('transition_post_status', [__CLASS__, 'handle_chapter_publish'], 10, 3);
        add_action('fanfic_batch_notify_followers', [__CLASS__, 'process_batch_notifications'], 10, 2);
        add_action('fanfic_send_email_batch', [__CLASS__, 'send_email_batch'], 10, 2);
    }
    
    /**
     * Handle chapter publish - schedule notifications
     */
    public static function handle_chapter_publish($new_status, $old_status, $post) {
        if ($post->post_type !== 'chapter') return;
        if ($new_status !== 'publish' || $old_status === 'publish') return;
        
        // Schedule batch notification (don't process immediately!)
        wp_schedule_single_event(
            time() + 10, 
            'fanfic_batch_notify_followers',
            [$post->ID, $post->post_parent]
        );
    }
    
    /**
     * Process notifications in batches
     */
    public static function process_batch_notifications($chapter_id, $story_id) {
        global $wpdb;
        
        $story = get_post($story_id);
        $author_id = $story->post_author;
        
        // Get all followers with email enabled (ONE query)
        $followers = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT u.ID, u.user_email, u.display_name, f.email_enabled
            FROM {$wpdb->prefix}fanfic_follows f
            INNER JOIN {$wpdb->users} u ON f.user_id = u.ID
            WHERE (
                (f.target_id = %d AND f.follow_type = 'story') OR
                (f.target_id = %d AND f.follow_type = 'author')
            )
        ", $story_id, $author_id));
        
        // Create in-app notifications for all followers
        foreach ($followers as $follower) {
            Fanfic_Notifications::create_notification(
                $follower->ID,
                'new_chapter',
                sprintf('New chapter in "%s"', $story->post_title),
                ['chapter_id' => $chapter_id, 'story_id' => $story_id]
            );
        }
        
        // Filter for email recipients
        $email_recipients = array_filter($followers, function($f) {
            return $f->email_enabled == 1;
        });
        
        // Schedule email sending in batches of 50
        $chunks = array_chunk($email_recipients, 50);
        foreach ($chunks as $i => $chunk) {
            wp_schedule_single_event(
                time() + ($i * 60), // Space out by 1 minute
                'fanfic_send_email_batch',
                [$chunk, $chapter_id]
            );
        }
    }
}
```

### 4.4 AJAX Handlers

**Optimized AJAX Implementation:**
```php
class Fanfic_Ajax {
    
    public static function init() {
        // Public actions (logged-in and anonymous)
        add_action('wp_ajax_fanfic_rate_chapter', [__CLASS__, 'ajax_rate_chapter']);
        add_action('wp_ajax_nopriv_fanfic_rate_chapter', [__CLASS__, 'ajax_rate_chapter']);
        add_action('wp_ajax_fanfic_like_chapter', [__CLASS__, 'ajax_like_chapter']);
        add_action('wp_ajax_nopriv_fanfic_like_chapter', [__CLASS__, 'ajax_like_chapter']);
        
        // Logged-in only actions
        add_action('wp_ajax_fanfic_mark_as_read', [__CLASS__, 'ajax_mark_as_read']);
        add_action('wp_ajax_fanfic_toggle_follow', [__CLASS__, 'ajax_toggle_follow']);
        add_action('wp_ajax_fanfic_toggle_follow', [__CLASS__, 'ajax_toggle_follow']);
    }
    
    /**
     * Handle rating submission
     */
    public static function ajax_rate_chapter() {
        // Always verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fanfic_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $chapter_id = intval($_POST['chapter_id']);
        $rating = intval($_POST['rating']);
        $user_id = get_current_user_id();
        
        $result = Fanfic_Rating_System::submit_rating(
            $chapter_id, 
            $rating, 
            $user_id ?: null
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Return updated stats
        $stats = Fanfic_Database_Operations::get_chapter_stats($chapter_id);
        wp_send_json_success($stats);
    }
}
```

---

## Part 5: Frontend JavaScript

```javascript
/**
 * Fanfic Interactions JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize on document ready
    $(document).ready(function() {
        initRatings();
        initLikes();
        initFollows();
        initFollows();
        initReadingProgress();
    });
    
    /**
     * Rating System
     */
    function initRatings() {
        $('.fanfic-rating-widget').on('click', '.star', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var $widget = $this.closest('.fanfic-rating-widget');
            var rating = $this.data('rating');
            var chapterId = $widget.data('chapter-id');
            
            // Visual feedback
            $widget.addClass('loading');
            
            $.post(fanfic_ajax.ajax_url, {
                action: 'fanfic_rate_chapter',
                chapter_id: chapterId,
                rating: rating,
                nonce: fanfic_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    updateRatingDisplay($widget, response.data);
                    showNotification('Rating submitted!', 'success');
                } else {
                    showNotification(response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Network error. Please try again.', 'error');
            })
            .always(function() {
                $widget.removeClass('loading');
            });
        });
    }
    
    /**
     * Like System
     */
    function initLikes() {
        $('.fanfic-like-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var chapterId = $button.data('chapter-id');
            
            // Optimistic UI update
            $button.toggleClass('liked');
            
            $.post(fanfic_ajax.ajax_url, {
                action: 'fanfic_like_chapter',
                chapter_id: chapterId,
                nonce: fanfic_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $button.find('.count').text(response.data.count);
                    if (response.data.liked) {
                        $button.addClass('liked');
                    } else {
                        $button.removeClass('liked');
                    }
                } else {
                    // Revert optimistic update
                    $button.toggleClass('liked');
                    showNotification(response.data, 'error');
                }
            })
            .fail(function() {
                // Revert optimistic update
                $button.toggleClass('liked');
                showNotification('Network error', 'error');
            });
        });
    }
    
    /**
     * Reading Progress
     */
    function initReadingProgress() {
        $('.fanfic-chapter').on('click', '.mark-as-read', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var storyId = $button.data('story-id');
            var chapterNumber = $button.data('chapter-number');
            
            $.post(fanfic_ajax.ajax_url, {
                action: 'fanfic_mark_as_read',
                story_id: storyId,
                chapter_number: chapterNumber,
                nonce: fanfic_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $button.addClass('read');
                    updateProgressBar(storyId);
                }
            });
        });
    }
    
    /**
     * Helper: Show notification
     */
    function showNotification(message, type) {
        var $notification = $('<div>')
            .addClass('fanfic-notification')
            .addClass('fanfic-notification-' + type)
            .text(message)
            .appendTo('body');
        
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
})(jQuery);
```

---

## Part 6: Cron Jobs and Maintenance

### Daily Cleanup Tasks

```php
class Fanfic_Maintenance {
    
    /**
     * Initialize maintenance tasks
     */
    public static function init() {
        // Get user-defined cron hour
        $cron_hour = get_option('fanfic_cron_hour', 3); // Default 3 AM
        
        // Schedule daily cleanup at user-defined hour
        if (!wp_next_scheduled('fanfic_daily_cleanup')) {
            $timestamp = strtotime("today {$cron_hour}:00");
            if ($timestamp < time()) {
                $timestamp = strtotime("tomorrow {$cron_hour}:00");
            }
            wp_schedule_event($timestamp, 'daily', 'fanfic_daily_cleanup');
        }
        
        // Hook the cleanup function
        add_action('fanfic_daily_cleanup', [__CLASS__, 'run_daily_cleanup']);
    }
    
    /**
     * Run daily cleanup tasks
     */
    public static function run_daily_cleanup() {
        global $wpdb;
        
        // 1. Clean old read notifications (30+ days)
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}fanfic_notifications 
            WHERE is_read = 1 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // 2. Remove orphaned follows (user deleted)
        $wpdb->query("
            DELETE f FROM {$wpdb->prefix}fanfic_follows f
            LEFT JOIN {$wpdb->users} u ON f.user_id = u.ID
            WHERE u.ID IS NULL
        ");
        
        // 3. Remove orphaned follows
        $wpdb->query("
            DELETE b FROM {$wpdb->prefix}fanfic_follows b
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            WHERE u.ID IS NULL
        ");
        
        // 4. Clean unverified email subscriptions (7+ days old)
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}fanfic_email_subscriptions
            WHERE verified = 0 
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        // 5. Optimize tables (monthly, on the 1st)
        if (date('j') == 1) {
            $tables = [
                'fanfic_ratings',
                'fanfic_likes',
                'fanfic_follows',
                'fanfic_follows',
                'fanfic_reading_progress',
                'fanfic_notifications'
            ];
            
            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}{$table}");
            }
        }
        
        // Log cleanup completion
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Fanfic daily cleanup completed at ' . current_time('mysql'));
        }
    }
}
```

---

## Part 7: Performance Monitoring

### Key Metrics to Track

```php
class Fanfic_Performance_Monitor {
    
    /**
     * Log slow queries for optimization
     */
    public static function log_slow_query($query, $time) {
        if ($time > 0.5) { // Queries taking > 500ms
            $log = [
                'query' => $query,
                'time' => $time,
                'date' => current_time('mysql'),
                'page' => $_SERVER['REQUEST_URI'] ?? ''
            ];
            
            // Store in option (limited to last 100)
            $slow_queries = get_option('fanfic_slow_queries', []);
            array_unshift($slow_queries, $log);
            $slow_queries = array_slice($slow_queries, 0, 100);
            update_option('fanfic_slow_queries', $slow_queries);
        }
    }
    
    /**
     * Get performance stats
     */
    public static function get_stats() {
        global $wpdb;
        
        return [
            'total_stories' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'story'"),
            'total_chapters' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'chapter'"),
            'total_ratings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_ratings"),
            'total_likes' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_likes"),
            'total_follows' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fanfic_follows"),
            'active_users_30d' => $wpdb->get_var("
                SELECT COUNT(DISTINCT user_id) 
                FROM {$wpdb->prefix}fanfic_reading_progress 
                WHERE marked_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ")
        ];
    }
}
```

---

## Part 8: Security Considerations

### Critical Security Measures

1. **Always use nonces for AJAX**:
```php
wp_verify_nonce($_POST['nonce'], 'fanfic_ajax_nonce')
```

2. **Sanitize all inputs**:
```php
$chapter_id = intval($_POST['chapter_id']);
$email = sanitize_email($_POST['email']);
$message = sanitize_textarea_field($_POST['message']);
```

3. **Use prepared statements**:
```php
$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id)
```

4. **Check capabilities**:
```php
if (!current_user_can('read')) {
    wp_die('Unauthorized');
}
```

5. **Cookie security**:
```php
setcookie($name, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
//                                                            ^^SSL  ^^HttpOnly
```

---

## Part 9: Migration from Current System

### Migration Steps

2. **Create database schema** (add missing columns to table creation logic, no legacy code, plugin is on dev. We assume we activate always the plugin)
3. **Delete IP-based anonymous actions and migrate to cookies cookies** 
4. **Update all query loops to batch loading**
5. **Replace synchronous email with WP-Cron**
6. **Test on staging first**


---

## Part 10: Testing Checklist

### Pre-Launch Testing

- [ ] **Performance Tests**:
  - [ ] Load story with 50+ chapters - should be < 3 queries
  - [ ] Test with 1000+ stories in archive
  - [ ] Verify batch loading works
  
- [ ] **Anonymous Actions**:
  - [ ] Can rate without login (cookie set)
  - [ ] Cannot rate twice (cookie blocks)
  - [ ] Cookies expire after 30 days
  
- [ ] **Email System**:
  - [ ] New chapter triggers async notifications
  - [ ] Unsubscribe links work
  - [ ] No timeout with 100+ followers
  
- [ ] **Security**:
  - [ ] All AJAX has nonce verification
  - [ ] SQL injection tests pass
  - [ ] XSS prevention works
  
- [ ] **Cron Jobs**:
  - [ ] Daily cleanup runs at configured time
  - [ ] Old data properly cleaned
  - [ ] Tables optimized monthly

---

## Part 11: Incremental Cache Updates Explained

### Why Incremental Updates Matter

**Traditional Cache (BAD for high traffic)**:
```php
// User rates → Delete cache → Next visitor rebuilds from DB
submit_rating() → wp_cache_delete() → next_load() → SELECT AVG(rating)...
```
**Problem**: Cache miss on EVERY rating = database thrashing

**Incremental Cache (GOOD for high traffic)**:
```php
// User rates → Update cache mathematically → No DB query needed
submit_rating() → cache['average'] = recalculate() → next_load() → cache hit!
```
**Benefit**: Cache stays valid, 99%+ hit rate

### Mathematical Formula for Ratings

```php
// When new rating comes in:
$new_average = (($old_total * $old_average) + $new_rating) / ($old_total + 1);

// Example:
// Before: 10 ratings, average 3.5
// New rating: 5 stars
// Math: ((10 * 3.5) + 5) / 11 = 40/11 = 3.64
```

### When to Use Incremental vs Invalidation

**Use Incremental For**:
- ✅ Ratings (mathematical average)
- ✅ Likes (simple counter)
- ✅ View counts (increment only)

**Use Invalidation For**:
- ❌ Follows (list changes)
- ❌ Follows (complex queries)
- ❌ Comments (threaded structure)
