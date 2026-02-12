# Unified localStorage Interaction System — Full Implementation Plan

## Context

The plugin currently has three separate, legacy interaction systems: views (server-side dedupe via `template_redirect`), likes (cookie-based, `wp_fanfic_likes` table), and ratings (cookie-based, `wp_fanfic_ratings` table). A prepared but unused `wp_fanfic_interactions` table exists.

This plan replaces all three with a **unified localStorage-based system** where:
- **Logged-out users**: interactions stored ONLY in localStorage
- **Logged-in users**: stored in BOTH localStorage AND database simultaneously
- **User logs out, interacts, logs back in**: localStorage changes are synced to DB by comparing timestamps (newer wins, both directions)
- **No legacy code**: single path for views, likes, dislikes, ratings, read status

### Key Decisions
- **Views**: Move to localStorage + AJAX. One-time-ever per browser. JS-only (bots excluded).
- **Like + Dislike**: Mutually exclusive. Dislike OFF by default, toggled in settings + wizard step 4.
- **Chapter-level only**: All interactions at chapter level. Story totals aggregated from chapters.
- **Ratings**: Half-star increments (0.5 to 5.0). `decimal(3,1)` already in schema.
- **Read flag**: Auto-set after 2 minutes of active tab time (Page Visibility API). Distinct from "view".
- **Rolling time-period counters**: Views, likes, and ratings all have all-time, weekly, and monthly counters using stamp-reset pattern (no cron needed).
- **Replace entirely**: Remove old like/rating systems. Clean break.

---

## Data Architecture Overview

### Three Storage Layers

```
┌─────────────────────────────────────────────────────────┐
│  LAYER 1: localStorage (browser)                        │
│                                                         │
│  Key: 'fanfic_interactions'                             │
│  Value: {                                               │
│    "story_123_chapter_789": {                           │
│      "like": true,          // or absent                │
│      "dislike": true,       // mutually exclusive       │
│      "rating": 3.5,         // 0.5-5.0 or absent       │
│      "read": true,          // auto after 2min          │
│      "timestamp": 1707700000000                         │
│    },                                                   │
│    ...                                                  │
│  }                                                      │
│  Entry existence = chapter was VIEWED                   │
│                                                         │
│  ● ALL users read/write here for immediate UI state     │
│  ● Logged-out: this is the ONLY store                   │
│  ● Logged-in: this + AJAX to server                     │
├─────────────────────────────────────────────────────────┤
│                    │                                     │
│                    │ AJAX (logged-in: all interactions)  │
│                    │       (all users: views only)       │
│                    ▼                                     │
├─────────────────────────────────────────────────────────┤
│  LAYER 2: wp_fanfic_interactions (per-user DB)          │
│                                                         │
│  One row per user per chapter per interaction type.      │
│  Source of truth for "what did THIS user do?"            │
│                                                         │
│  PK: (user_id, chapter_id, interaction_type)            │
│                                                         │
│  user_id         = 42  (WP user ID, integer FK)        │
│  chapter_id      = 789                                  │
│  interaction_type = like|dislike|rating|view|read        │
│  value           = 3.5 (for ratings, NULL otherwise)   │
│  updated_at      = timestamp for sync comparison        │
│                                                         │
│  ● Only written for logged-in users                     │
│  ● No target_type column (always chapter-level)         │
│  ● Used for sync-on-login (timestamp comparison)        │
│  ● On every write, ALSO atomically update Layer 3 ↓     │
├─────────────────────────────────────────────────────────┤
│                    │                                     │
│                    │ Atomic increment/decrement          │
│                    │ in same PHP method call             │
│                    ▼                                     │
├─────────────────────────────────────────────────────────┤
│  LAYER 3: Search Index Tables (pre-computed totals)     │
│                                                         │
│  wp_fanfic_chapter_search_index (one row per chapter)   │
│  wp_fanfic_story_search_index   (one row per story)     │
│                                                         │
│  Pre-computed counters:                                  │
│  ┌─────────────┬──────────┬──────────┬──────────┐      │
│  │             │ All-time │  Weekly  │ Monthly  │      │
│  ├─────────────┼──────────┼──────────┼──────────┤      │
│  │ Views       │ total    │ +stamp   │ +stamp   │      │
│  │ Likes       │ total    │ +stamp   │ +stamp   │      │
│  │ Dislikes    │ total    │   —      │   —      │      │
│  │ Rating sum  │ total    │ +stamp   │ +stamp   │      │
│  │ Rating count│ total    │ +stamp   │ +stamp   │      │
│  │ Rating avg  │ total    │ computed │ computed │      │
│  │ Trending    │   —      │ value    │ value    │      │
│  └─────────────┴──────────┴──────────┴──────────┘      │
│                                                         │
│  ● Used for: story cards, search results, rankings,     │
│    dashboard stats, "Top Rated", "Trending" queries     │
│  ● NEVER queried per-user — only aggregated totals      │
│  ● Story row = aggregate of all its chapters            │
└─────────────────────────────────────────────────────────┘
```

---

### How Totals Stay In Sync (Layer 2 → Layer 3)

When a user performs an interaction, the write to `wp_fanfic_interactions` (Layer 2) and the update to the search index (Layer 3) happen **in the same PHP method, atomically**. There is no background job, no delayed sync, no cron.

#### Example: User likes chapter 789 (belongs to story 123)

```php
// Step 1: Write individual interaction (Layer 2)
INSERT INTO wp_fanfic_interactions (user_id, chapter_id, interaction_type)
VALUES (42, 789, 'like')
ON DUPLICATE KEY UPDATE updated_at = NOW();

// Step 2: If user had a dislike, remove it (mutual exclusion)
DELETE FROM wp_fanfic_interactions
WHERE user_id = 42 AND chapter_id = 789 AND interaction_type = 'dislike';

// Step 3: Atomically increment CHAPTER search index (Layer 3)
INSERT INTO wp_fanfic_chapter_search_index
    (chapter_id, story_id, likes_total, likes_week, likes_month, likes_week_stamp, likes_month_stamp)
VALUES (789, 123, 1, 1, 1, 202607, 202602)
ON DUPLICATE KEY UPDATE
    likes_total = likes_total + 1,
    likes_week  = IF(likes_week_stamp = 202607, likes_week + 1, 1),
    likes_month = IF(likes_month_stamp = 202602, likes_month + 1, 1),
    likes_week_stamp = 202607,
    likes_month_stamp = 202602;

// Step 4: Atomically increment STORY search index (Layer 3)
// (same pattern, targeting story_id = 123)
```

If the user **removes** a like, the same flow runs with `GREATEST(0, likes_total - 1)` (never goes below 0).

#### Example: User rates chapter 789 as 3.5 stars (first time)

```php
// Step 1: Write individual interaction (Layer 2)
INSERT INTO wp_fanfic_interactions (user_id, chapter_id, interaction_type, value)
VALUES (42, 789, 'rating', 3.5)
ON DUPLICATE KEY UPDATE value = 3.5, updated_at = NOW();

// Step 2: Update chapter search index — all three periods (Layer 3)
// IMPORTANT: MySQL evaluates SET left-to-right, so after updating sum/count,
// the avg line sees the ALREADY-UPDATED values. Compute avg LAST from new values.
UPDATE wp_fanfic_chapter_search_index SET
    -- All-time (sum and count update first, avg computed from their new values)
    rating_sum_total   = rating_sum_total + 3.5,
    rating_count_total = rating_count_total + 1,
    rating_avg_total   = rating_sum_total / rating_count_total,
    -- Weekly (stamp-reset: sum/count reset or accumulate, then avg from new values)
    rating_sum_week    = IF(rating_week_stamp = 202607, rating_sum_week + 3.5, 3.5),
    rating_count_week  = IF(rating_week_stamp = 202607, rating_count_week + 1, 1),
    rating_avg_week    = rating_sum_week / rating_count_week,
    rating_week_stamp  = 202607,
    -- Monthly (same pattern)
    rating_sum_month   = IF(rating_month_stamp = 202602, rating_sum_month + 3.5, 3.5),
    rating_count_month = IF(rating_month_stamp = 202602, rating_count_month + 1, 1),
    rating_avg_month   = rating_sum_month / rating_count_month,
    rating_month_stamp = 202602
WHERE chapter_id = 789;

// Step 3: Same for story search index (story_id = 123)
```

If the user **changes** rating from 3.5 to 5.0: delta applied (`sum += 5.0 - 3.5`), count unchanged, avg recalculated.
If the user **removes** rating: `sum -= old`, `count -= 1`, avg = `IF(count > 0, sum/count, 0)`.

---

### The Week/Month Rolling Counter Trick (No Cron Needed)

Each counter has a companion `_stamp` column. The stamp is computed from the current date:
- Week stamp: `wp_date('oW')` → e.g. `202607` (ISO year + week number)
- Month stamp: `wp_date('Ym')` → e.g. `202602` (year + month)

On every increment, SQL checks whether the stamp matches:

```sql
likes_week = IF(likes_week_stamp = 202607, likes_week + 1, 1)
--           ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
--           "Same week? Add 1. New week? RESET to 1."
likes_week_stamp = 202607
--                 "Update stamp to current week."
```

**Monday of a new week (202608)**:
```
First interaction: stamp was 202607 ≠ 202608 → reset to 1
Second interaction: stamp is 202608 = 202608 → 1 + 1 = 2
```

**Key properties:**
- No cron needed — reset happens lazily on first write of new period
- Atomic — single SQL statement, no race conditions
- If nobody interacts in a week, old count lingers until next interaction resets it (fine for rankings — inactive content drops off naturally)
- Same pattern for views, likes, and ratings (sum/count/avg all reset together)

**Ranking queries enabled:**
- Top rated this week: `ORDER BY rating_avg_week DESC WHERE rating_count_week >= 3`
- Top rated this month: `ORDER BY rating_avg_month DESC WHERE rating_count_month >= 5`
- Top rated all-time: `ORDER BY rating_avg_total DESC WHERE rating_count_total >= 10`
- Most liked this week: `ORDER BY likes_week DESC`
- Trending this week: `ORDER BY trending_week DESC`

---

### Sync-on-Login: The Full Lifecycle

A user can be logged in, log out, interact anonymously (localStorage only), then log back in. Here's the complete lifecycle:

```
TIMELINE:
─────────────────────────────────────────────────────────────

1. USER IS LOGGED IN (Device A, user_id = 42)
   ● Likes chapter 789 at timestamp T1
   ● localStorage: { story_123_chapter_789: { like: true, timestamp: T1 } }
   ● Database:     wp_fanfic_interactions row (user_id=42, chapter_id=789, like) created
   ● Search index: likes_total incremented

2. USER LOGS OUT
   ● localStorage persists (browser retains it)
   ● Database retains all rows

3. USER INTERACTS WHILE LOGGED OUT
   ● Changes mind: removes like, adds dislike on chapter 789 at timestamp T2
   ● localStorage: { story_123_chapter_789: { dislike: true, timestamp: T2 } }
   ● Database: UNCHANGED (still has like from T1)
   ● Search index: UNCHANGED (no AJAX fired for logged-out)
   ● Also rates chapter 456 as 4.0 at timestamp T3
   ● localStorage: { ..., story_100_chapter_456: { rating: 4.0, timestamp: T3 } }

4. USER LOGS BACK IN (same Device A)
   ● PHP sets transient: fanfic_needs_sync_{user_id} = true
   ● Next page load: JS detects needsSync flag
   ● JS sends FULL localStorage to server via AJAX

5. SYNC PROCESS (server-side):
   ┌──────────────────────────────────────────────────────┐
   │ For each chapter key, compare timestamps:            │
   │                                                      │
   │ Chapter 789:                                         │
   │   localStorage: { dislike: true, timestamp: T2 }     │
   │   Database:     { like: true, updated_at: T1 }       │
   │   T2 > T1 → localStorage WINS                        │
   │   → Delete like row from DB                          │
   │   → Insert dislike row in DB (updated_at = T2)       │
   │   → Search index: likes_total -1, dislikes_total +1  │
   │                                                      │
   │ Chapter 456:                                         │
   │   localStorage: { rating: 4.0, timestamp: T3 }       │
   │   Database:     (no rows)                             │
   │   → Insert rating row in DB (updated_at = T3)        │
   │   → Search index: rating_sum +4.0, count +1          │
   └──────────────────────────────────────────────────────┘

6. SERVER RETURNS merged data → JS writes to localStorage
   ● Both are now in sync
   ● Transient cleared (no re-sync on next page load)

─────────────────────────────────────────────────────────────

SCENARIO: USER LOGS IN ON DEVICE B (empty localStorage)

1. PHP sets transient: fanfic_needs_sync_{user_id} = true
2. JS sends empty localStorage to server
3. Server fetches all DB rows for this user_id
4. For each DB entry: no local counterpart → DB WINS
   → All entries returned to JS
5. JS populates localStorage from server response
6. UI immediately shows correct like/rating/read state
```

---

## Phase 1: Database Schema Updates

**File**: `includes/class-fanfic-database-setup.php`

1. Rebuild `wp_fanfic_interactions` table:
   - Replace `visitor_hash char(64)` with `user_id bigint(20) UNSIGNED NOT NULL`
   - Replace `target_type enum + target_id` with just `chapter_id bigint(20) UNSIGNED NOT NULL` (chapter-level only, no target_type)
   - New PK: `(user_id, chapter_id, interaction_type)` — 3-column integer PK, maximally compact
   - Add `KEY idx_chapter (chapter_id)` for aggregation queries
   - Enum: `enum('like','dislike','rating','view','read')`
   - `value` column: `decimal(3,1) DEFAULT NULL` (only meaningful for rating; NULL for like/dislike/view/read)
   - `created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`
   - `updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
2. Add to both chapter + story search index tables:
   - Likes rolling: `likes_week_stamp int(11) NOT NULL DEFAULT 0`, `likes_month_stamp int(11) NOT NULL DEFAULT 0`
   - Dislikes: `dislikes_total bigint(20) NOT NULL DEFAULT 0`
   - Ratings rolling (8 new columns):
     - `rating_sum_week double NOT NULL DEFAULT 0`
     - `rating_count_week bigint(20) NOT NULL DEFAULT 0`
     - `rating_avg_week double NOT NULL DEFAULT 0`
     - `rating_week_stamp int(11) NOT NULL DEFAULT 0`
     - `rating_sum_month double NOT NULL DEFAULT 0`
     - `rating_count_month bigint(20) NOT NULL DEFAULT 0`
     - `rating_avg_month double NOT NULL DEFAULT 0`
     - `rating_month_stamp int(11) NOT NULL DEFAULT 0`
3. Add indexes on story search index: `idx_rating_avg_week (rating_avg_week)`, `idx_rating_avg_month (rating_avg_month)`, `idx_likes_week (likes_week)`, `idx_likes_month (likes_month)`
4. Bump `DB_VERSION` to `'1.6.0'`
5. Add `migrate_interactions_schema()` for ALTER TABLE on existing installs
6. Drop old tables immediately: `wp_fanfic_likes`, `wp_fanfic_ratings`, `wp_fanfic_daily_views` (dev phase, no data to preserve)

---

## Phase 2: New Unified Server-Side Class

**New file**: `includes/class-fanfic-interactions.php`

Singleton class `Fanfic_Interactions` replacing `class-fanfic-like-system.php` + `class-fanfic-rating-system.php` + parts of `class-fanfic-views.php`.

### Write methods (logged-in users only — anonymous users stay in localStorage):
All write methods take `$user_id` (WP user ID integer), not a hash.
- `record_like($chapter_id, $user_id)` — inserts like, removes dislike if exists, increments search index
- `remove_like($chapter_id, $user_id)` — deletes like, decrements search index
- `record_dislike($chapter_id, $user_id)` — inserts dislike, removes like if exists
- `remove_dislike($chapter_id, $user_id)`
- `record_rating($chapter_id, $rating, $user_id)` — upsert rating (0.5-5.0), update search index sum/count/avg for all three periods
- `remove_rating($chapter_id, $user_id)` — delete rating, adjust search index
- `record_view($chapter_id, $story_id)` — AJAX from all users (even anonymous); server increments search index counters only (no DB row for anonymous). Reuses `apply_view_increment()` pattern.
- `record_read($chapter_id, $user_id)` — marks chapter as read in interactions table (logged-in only)

### Read methods:
- `get_chapter_stats($chapter_id)` → `{ views, likes, dislikes, rating_avg, rating_count }`
- `get_story_stats($story_id)` → same, from story search index
- `get_all_user_interactions($user_id)` → all rows for sync
- `batch_get_chapter_stats($chapter_ids)` → N+1 prevention

### Query methods (all read from search index — zero JOINs, zero subqueries):
- `get_story_views($story_id)` — `wp_fanfic_story_search_index.view_count`
- `get_chapter_views($chapter_id)` — `wp_fanfic_chapter_search_index.views_total`
- `get_story_likes($story_id)` — `wp_fanfic_story_search_index.likes_total`
- `get_chapter_likes($chapter_id)` — `wp_fanfic_chapter_search_index.likes_total`
- `get_story_rating($story_id)` — `rating_avg_total`, `rating_count_total` from story search index
- `get_stars_html($rating, $interactive, $size)` — renders half-star HTML
- `get_top_rated_stories($limit, $min_ratings, $period)` — `$period` = 'total'|'week'|'month'. Single indexed query on story search index.
- `get_most_viewed_stories($limit, $period)` — same pattern
- `get_trending_stories($limit, $period)` — `ORDER BY trending_{period} DESC`

### Search index update helpers (private, using `INSERT...ON DUPLICATE KEY UPDATE` with stamp-reset):
- `apply_like_increment($chapter_id, $story_id, $delta)` — rolling week/month stamp counters for likes_total/week/month
- `apply_dislike_increment($chapter_id, $story_id, $delta)` — dislikes_total only (no weekly/monthly)
- `apply_rating_update($chapter_id, $story_id, $new, $old, $is_new, $is_remove)` — atomic sum/count/avg for all three periods (total, week, month) using same stamp-reset pattern

### Sync method:
- `sync_on_login($user_id, $local_data)` — compares timestamps per chapter entry, newer wins both directions. See Phase 9 for full detail.

---

## Phase 3: JavaScript localStorage Architecture

**File**: `assets/js/fanfiction-interactions.js` (complete rewrite)

### 3a. `FanficLocalStore` object — localStorage manager

```
Storage key: 'fanfic_interactions'
Entry key format: "story_{storyId}_chapter_{chapterId}"
Entry structure: { like?, dislike?, rating?, read?, timestamp }
Entry existence = chapter was viewed
```

Methods: `getAll()`, `saveAll(data)`, `makeKey(storyId, chapterId)`, `getChapter()`, `hasViewed()`, `recordView()`, `toggleLike()`, `toggleDislike()`, `setRating()`, `setRead()`, `mergeFromServer()`, `getNewerThanServer()`

### 3b. View tracking flow (on chapter page load):
1. Check `FanficLocalStore.hasViewed(storyId, chapterId)`
2. If NOT viewed → create localStorage entry → fire AJAX `fanfic_record_view` (ALL users, including anonymous — server needs to count total views)
3. If already viewed → no action

### 3c. Read tracking flow (automatic, 2-minute active timer):
1. On chapter page load, start a timer tracking cumulative active tab time
2. Use `document.addEventListener('visibilitychange', ...)`:
   - `document.hidden = true` → pause timer, record elapsed
   - `document.hidden = false` → resume timer from where it left off
3. When cumulative active time >= 120 seconds AND `!entry.read`:
   - Set `read: true` in localStorage entry, update timestamp
   - If logged in → fire AJAX `fanfic_record_interaction` with `type: 'read'`
4. If entry already has `read: true` → skip timer entirely, don't even start it

### 3d. Like/Dislike flow:
- **All users**: Update localStorage immediately (optimistic UI)
- **Logged-in only**: Also fire AJAX `fanfic_record_interaction`
- **Logged-out**: No AJAX, localStorage only. Changes are synced when user logs back in.

### 3e. Rating flow:
- Same pattern as like/dislike. Half-star widget with left/right click zones per star.

### 3f. Sync-on-login trigger:
- If `fanficAjax.needsSync` is true, POST full localStorage data to `fanfic_sync_interactions` on page load
- On response: `FanficLocalStore.mergeFromServer(response.merged)` overwrites localStorage with canonical merged result

### 3g. Cross-tab safety:
- Listen to `window.addEventListener('storage', ...)` to detect writes from other tabs and refresh local state

### 3h. UI state on page load:
- Read from localStorage to set initial button/rating states (no server round-trip needed)
- Like button: check `entry.like` → add `.fanfic-button-liked` class
- Dislike button: check `entry.dislike` → add `.fanfic-button-disliked` class
- Rating widget: check `entry.rating` → fill stars to that value
- Read indicator: check `entry.read` → mark as read

---

## Phase 4: AJAX Endpoint Redesign

**File**: `includes/class-fanfic-ajax-handlers.php`

### Remove old handlers:
- `fanfic_submit_rating` / `ajax_submit_rating()`
- `fanfic_toggle_like` / `ajax_toggle_like()`
- `fanfic_check_rating_eligibility` / `ajax_check_rating_eligibility()`
- `fanfic_check_like_status` / `ajax_check_like_status()`

### Add new handlers:

| Action | Anonymous? | Rate Limited | Purpose |
|--------|-----------|-------------|---------|
| `fanfic_record_interaction` | No (login required) | Yes | Like/dislike/rating/read writes to DB + search index |
| `fanfic_record_view` | Yes | Yes | View counter increment (all users, since server counts totals) |
| `fanfic_sync_interactions` | No (login required) | Yes | Bulk sync localStorage <-> DB on login |
| `fanfic_get_chapter_stats` | Yes | Yes | Get aggregated stats (keep existing, update implementation) |

### Handler implementations:
- `ajax_record_interaction()`: Gets `$user_id = get_current_user_id()`. Receives `chapter_id`, `type` (like/remove_like/dislike/remove_dislike/rating/remove_rating/read), `value` (for ratings). Routes to `Fanfic_Interactions` methods. Returns updated stats.
- `ajax_record_view()`: Receives `chapter_id`, `story_id`. Validates chapter exists + published. Skips author self-views (reuse `is_author_related_view()` logic). Increments search index only — no DB row for anonymous users. Calls `Fanfic_Interactions::record_view()`.
- `ajax_sync_interactions()`: Gets `$user_id = get_current_user_id()`. Receives `local_data` (JSON). Calls `Fanfic_Interactions::sync_on_login($user_id, $local_data)`. Returns merged data for localStorage to adopt.

---

## Phase 5: Eliminate `Fanfic_Views` Entirely

**File**: `includes/class-fanfic-views.php` → **DELETE**

No legacy facade. All methods move into `Fanfic_Interactions`:
- `get_story_views($story_id)` → `Fanfic_Interactions::get_story_views()` (reads `wp_fanfic_story_search_index.view_count`)
- `get_chapter_views($chapter_id)` → `Fanfic_Interactions::get_chapter_views()` (reads chapter search index)
- `get_most_viewed_stories()` → `Fanfic_Interactions::get_most_viewed_stories()` (query story search index)
- `get_trending_stories()` → `Fanfic_Interactions::get_trending_stories()` (query story search index)
- `apply_view_increment()` → private method in `Fanfic_Interactions`
- `is_author_related_view()` → private method in `Fanfic_Interactions`

**Also delete**: Cron cleanup for `wp_fanfic_daily_views` — table dropped in Phase 1.

---

## Phase 6: Search Index Updates

**File**: `includes/class-fanfic-search-index.php`

1. Add ALL new counter columns to `counter_columns` array (preserved during re-index):
   - `dislikes_total`
   - `likes_week_stamp`, `likes_month_stamp`
   - `rating_sum_week`, `rating_count_week`, `rating_avg_week`, `rating_week_stamp`
   - `rating_sum_month`, `rating_count_month`, `rating_avg_month`, `rating_month_stamp`
2. `apply_like_increment()` uses same `INSERT...ON DUPLICATE KEY UPDATE` with week/month stamp rolling pattern as views
3. `apply_rating_update()` uses atomic SQL for sum/count/avg across all three time periods (total, week, month) — each with its own stamp column for auto-reset
4. Story-level aggregation: when chapter interaction recorded, also update story search index row
5. Add indexes for ranking queries: `idx_rating_avg_week`, `idx_rating_avg_month`, `idx_likes_week`, `idx_likes_month` on story search index

---

## Phase 7: Settings & Wizard Changes

**File**: `includes/class-fanfic-settings.php`
1. Add `enable_dislikes` setting (default: `false`)
2. Add checkbox in settings UI after "Enable Likes" row
3. Remove `allow_anonymous_likes` and `fanfic_allow_anonymous_ratings` settings (no longer relevant — localStorage handles anonymous interactions)

**File**: `includes/class-fanfic-wizard.php`
1. Add "Enable Dislikes" checkbox to step 4 (taxonomies/features step)
2. Show dislike setting status in step 5 review
3. Update `commit_draft()` to persist `enable_dislikes`

---

## Phase 8: Frontend UI Updates

### Shortcode files:

**`includes/shortcodes/class-fanfic-shortcodes-buttons.php`**:
- Replace `Fanfic_Like_System` calls with `Fanfic_Interactions`
- Add conditional dislike button (based on `enable_dislikes` setting)
- Add `data-story-id` + `data-chapter-id` attributes for JS localStorage keys
- Remove server-side state checks for like/rating — JS handles initial state from localStorage on page load

**`includes/shortcodes/class-fanfic-shortcodes-stats.php`**:
- Replace all `Fanfic_Rating_System::get_story_rating()` → `Fanfic_Interactions::get_story_rating()`
- Replace `get_stars_html()` → `Fanfic_Interactions::get_stars_html()` (half-star support)
- Replace `get_top_rated_stories()` → `Fanfic_Interactions::get_top_rated_stories($limit, $min, $period)`
- Add period support to top-rated/most-liked shortcodes: `[top-rated-stories period="week"]`

**`includes/shortcodes/class-fanfic-shortcodes-story.php`**: Update view/rating/like display calls

**`includes/cache/story-cache.php`**: Rewrite `ffm_get_story_rating()` to read from `wp_fanfic_story_search_index` instead of `wp_fanfic_ratings`

**`includes/class-fanfic-stories-table.php`** (admin): Replace `Fanfic_Rating_System` references

**`includes/class-fanfic-export.php`**: Replace class references

**Half-star rating widget HTML**: 5 stars with left/right halves. CSS `clip-path` or overlapping spans for half-star display. Interactive mode: clicking left half of star N = (N-0.5), right half = N. Display mode: fill percentage based on average.

---

## Phase 9: Sync-on-Login Mechanism (Full Detail)

### Trigger
1. Hook `wp_login` action in `Fanfic_Interactions::init()`:
   ```php
   add_action( 'wp_login', array( __CLASS__, 'flag_sync_needed' ), 10, 2 );
   ```
   This sets a short-lived transient: `set_transient( 'fanfic_needs_sync_' . $user_id, true, HOUR_IN_SECONDS )`

2. In `enqueue_frontend_assets()`, pass flags to JS:
   ```php
   'isLoggedIn' => is_user_logged_in(),
   'needsSync'  => (bool) get_transient( 'fanfic_needs_sync_' . get_current_user_id() ),
   ```

### JS Flow (on page load when needsSync = true)
```javascript
if (config.isLoggedIn && config.needsSync) {
    const localData = FanficLocalStore.getAll();
    $.post(config.ajaxUrl, {
        action: 'fanfic_sync_interactions',
        nonce: config.nonce,
        local_data: JSON.stringify(localData)
    }).done(function(response) {
        if (response.success) {
            FanficLocalStore.saveAll(response.data.merged);
            // UI refreshes from new localStorage state
        }
    });
}
```

### Server-Side Sync Logic
```
sync_on_login( $user_id, $local_data ):

1. Fetch ALL DB rows for this user_id
2. Index DB rows by chapter key ("story_X_chapter_Y")
3. Collect ALL unique chapter keys from both local + DB
4. For each chapter key:
   a. If ONLY in localStorage → push to DB (new interactions made while logged out)
      → Call record_like/record_rating/etc (which updates search index)
   b. If ONLY in DB → include in merged result (will populate localStorage)
   c. If in BOTH → compare timestamps:
      - localStorage timestamp > DB updated_at → localStorage WINS
        → Update DB rows + search index to match localStorage
      - DB updated_at > localStorage timestamp → DB WINS
        → Include DB version in merged result
      - Equal → keep DB as canonical
5. Build merged result (the canonical state for all chapters)
6. Delete sync transient
7. Return merged data to JS
```

### Important: Search Index Corrections During Sync
When sync pushes localStorage→DB (e.g., user removed a like while logged out), the sync method must also adjust search index counters. This means sync calls the same `record_like()` / `remove_like()` / `record_rating()` methods, which atomically update both Layer 2 and Layer 3.

---

## Phase 10: Old Code Cleanup

### Delete files:
- `includes/class-fanfic-like-system.php`
- `includes/class-fanfic-rating-system.php`
- `includes/class-fanfic-views.php`
- `includes/class-fanfic-visitor-identity.php`
- `assets/js/fanfiction-likes.js`
- `assets/js/fanfiction-rating.js`

### Update `includes/class-fanfic-core.php`:
- Remove `require_once` for: `class-fanfic-like-system.php`, `class-fanfic-rating-system.php`, `class-fanfic-views.php`, `class-fanfic-visitor-identity.php`
- Add `require_once` for: `class-fanfic-interactions.php`
- Remove `Fanfic_Rating_System::init()`, `Fanfic_Like_System::init()`, `Fanfic_Views::init()`
- Add `Fanfic_Interactions::init()`
- Update `enqueue_frontend_assets()`: remove old JS enqueues, add `isLoggedIn`/`needsSync` to localized data

### Update `includes/class-fanfic-database-setup.php`:
- Remove creation SQL for: `wp_fanfic_ratings`, `wp_fanfic_likes`, `wp_fanfic_daily_views`
- Drop all three old tables (no migration needed — dev phase, no data)
- Rewrite `wp_fanfic_interactions` schema: `user_id` + `chapter_id` PK (no visitor_hash, no target_type)

### Update `includes/class-fanfic-cache-hooks.php`:
- Remove old likes/ratings invalidation hooks
- Add new hooks for interaction cache invalidation if needed

---

## Implementation Order

```
Phase 1  (DB schema)                  ← no dependencies, safe first
Phase 2  (Fanfic_Interactions class)  ← depends on 1 (absorbs views, likes, ratings)
Phase 7  (Settings/Wizard)            ← independent, parallel with 2
Phase 6  (Search index preserve-list) ← depends on 2
Phase 4  (AJAX endpoints)             ← depends on 2
Phase 3  (JS rewrite)                 ← depends on 4
Phase 8  (Frontend UI)                ← depends on 2, 3, 4
Phase 9  (Sync-on-login)              ← depends on 2, 3, 4
Phase 5+10 (Delete old files + tables) ← LAST, after testing
```

---

## Files Summary

**New (1)**: `includes/class-fanfic-interactions.php`

**Delete (6)**: `includes/class-fanfic-like-system.php`, `includes/class-fanfic-rating-system.php`, `includes/class-fanfic-views.php`, `includes/class-fanfic-visitor-identity.php`, `assets/js/fanfiction-likes.js`, `assets/js/fanfiction-rating.js`

**Heavy modifications (11)**:
- `includes/class-fanfic-database-setup.php` — new schema, drop old tables, new columns
- `includes/class-fanfic-ajax-handlers.php` — replace 4 old handlers with 4 new ones
- `includes/class-fanfic-core.php` — loading, init_hooks, enqueue_frontend_assets
- `includes/class-fanfic-search-index.php` — add counter columns to preserve list
- `assets/js/fanfiction-interactions.js` — complete rewrite with localStorage manager
- `includes/shortcodes/class-fanfic-shortcodes-buttons.php` — like/dislike buttons
- `includes/shortcodes/class-fanfic-shortcodes-stats.php` — rating/like displays + period support
- `includes/class-fanfic-settings.php` — add dislike toggle, remove anonymous toggles
- `includes/class-fanfic-wizard.php` — add dislike to step 4
- `includes/cache/story-cache.php` — read from search index
- `includes/class-fanfic-author-dashboard.php` — update stats methods

**Light modifications (5)**:
- `includes/shortcodes/class-fanfic-shortcodes-story.php` — class reference swaps
- `includes/shortcodes/class-fanfic-shortcodes-forms.php` — class reference swaps
- `includes/class-fanfic-stories-table.php` — admin column uses new class
- `includes/class-fanfic-export.php` — class reference swaps
- `includes/class-fanfic-cache-hooks.php` — remove old invalidation hooks

---

## Edge Cases

| Scenario | Behavior |
|---|---|
| **localStorage cleared** | Logged-out: all interaction history lost. Logged-in: next sync restores from DB. Views may re-increment (acceptable). |
| **Multiple tabs** | localStorage is shared across tabs. `window.addEventListener('storage')` detects cross-tab writes. |
| **Private/incognito** | localStorage cleared on session end. Anonymous interactions are ephemeral by design. |
| **localStorage quota exceeded** | `try/catch` around `setItem` — fails silently, UI degrades gracefully. |
| **Rapid clicks (like spam)** | localStorage is idempotent. Server uses `INSERT...ON DUPLICATE KEY UPDATE`. Rate limiter prevents AJAX abuse. |
| **Bot exclusion** | Views require JS + localStorage — bots never trigger increments. Feature, not bug. |
| **Dislike feature toggled off** | Existing dislike data remains in DB/localStorage. Button hidden. If re-enabled, data reappears. |
| **User logs out → interacts → logs back in** | Sync compares timestamps. localStorage changes (made while logged out) pushed to DB if newer. |
| **User on Device A and B** | Each device syncs independently on login. Both converge to same state through timestamp comparison. |

---

## Verification

1. **Chapter page**: Visit a chapter → view count increments once → revisit → no increment. After 2 min active tab → `read: true` in localStorage.
2. **Like/Dislike (logged-out)**: Click like → localStorage updates, button toggles. Click dislike → like removed, dislike set. Refresh page → state persists from localStorage. No AJAX fired.
3. **Like/Dislike (logged-in)**: Same as above + AJAX fires, `wp_fanfic_interactions` row created, search index updated.
4. **Logout → interact → login**: Like a chapter while logged out → log in → sync fires → DB updated → search index corrected.
5. **Rating**: Click half-star → localStorage + (if logged-in) AJAX. Change rating → old delta removed, new applied. Remove rating → reverts.
6. **Sync on new device**: Log in on new browser → sync fires → DB data populates localStorage → UI shows correct state.
7. **Dashboard**: Author stats show correct views, likes, ratings from search index.
8. **Search results**: Story cards show aggregated ratings, likes, views from search index.
9. **Top rated shortcodes**: `[top-rated-stories period="week"]` returns stories with highest `rating_avg_week` above minimum threshold.
10. **Settings**: Toggle dislike on/off → button appears/disappears on chapter pages.
