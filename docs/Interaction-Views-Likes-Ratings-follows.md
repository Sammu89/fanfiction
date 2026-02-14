# Interaction System: Views, Likes, Dislikes, Ratings, and Follows

This document describes how interaction writes and ranking counters work in Fanfiction Manager, including immediate anonymous persistence.

## 1. Core Model

The system has two layers:

- **Per-interaction rows** in `wp_fanfic_interactions`.
- **Aggregated counters** in `wp_fanfic_chapter_search_index` and `wp_fanfic_story_search_index`.

All ranking/listing features read from aggregate tables. Interaction rows are used for dedupe, identity attribution, and user state.

## 2. Interactions Table (`wp_fanfic_interactions`)

Current schema is identity-flexible:

- `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id` BIGINT UNSIGNED NULL
- `anon_hash` BINARY(32) NULL
- `chapter_id` BIGINT UNSIGNED NOT NULL
- `interaction_type` ENUM('like','dislike','rating','view','read','follow') NOT NULL
- `value` DECIMAL(3,1) NULL (used by `rating`)
- `created_at`, `updated_at`

Indexes:

- `UNIQUE uq_user_chapter_type (user_id, chapter_id, interaction_type)`
- `UNIQUE uq_anon_chapter_type (anon_hash, chapter_id, interaction_type)`
- `KEY idx_chapter (chapter_id)`
- `KEY idx_gc_anon (user_id, updated_at, id)`
- `KEY idx_anon_hash_updated (anon_hash, updated_at)`

Identity rules:

- Logged-in interactions use `user_id` and `anon_hash = NULL`.
- Anonymous interactions use `anon_hash` and `user_id = NULL`.

## 3. Anonymous Interaction Flow

### 3.1 Frontend

- Browser keeps a persistent anonymous UUID in `localStorage` (`fanfic_anonymous_uuid`).
- Like/dislike/rating AJAX requests send `anonymous_uuid` when user is logged out.
- Local storage still mirrors chapter interaction state for UI and cross-tab consistency.

### 3.2 Backend

- UUID is never stored raw in DB.
- Server hashes UUID using HMAC-SHA256 (`wp_salt('auth')`) and stores binary hash in `anon_hash`.
- Writes are upserts keyed by either:
  - `(user_id, chapter_id, interaction_type)` or
  - `(anon_hash, chapter_id, interaction_type)`.

This allows immediate anonymous persistence and real-time aggregate updates.

## 4. Aggregate Counter Updates

- `record_like` / `record_dislike` update interaction row(s) and call `apply_like_increment` / `apply_dislike_increment`.
- `record_rating` updates the interaction row and calls `apply_rating_update`.
- Weekly/monthly stamps (`*_week_stamp`, `*_month_stamp`) control rolling period resets.

Views remain aggregate-first (`record_view`) and are not deduped through anonymous interaction rows.

## 5. Login Sync and Attribution

`Fanfic_Interactions::sync_on_login($user_id, $local_data, $anonymous_uuid)` performs:

1. Hash incoming `anonymous_uuid`.
2. Re-attribute matching anonymous rows to the logged-in `user_id`.
3. If duplicate chapter/type already exists for user, keep user row and drop anon duplicate.
4. Continue normal local payload merge for any missing newer local state.

This avoids replaying all anonymous writes as new inserts and prevents sync-time double counting.

## 6. Public AJAX Actions

`fanfic_record_interaction` is public (nonce + rate limit still enforced), so anonymous users can write likes/dislikes/ratings immediately.

Security public-action whitelist includes:

- `fanfic_record_interaction`
- `fanfic_record_view`
- `fanfic_get_chapter_stats`
- `fanfic_subscribe_email`
- `fanfic_verify_subscription`
- `fanfic_report_content`
- `fanfic_toggle_follow`
- `fanfic_search`

## 7. Follows

Follows live in `wp_fanfic_interactions` with `interaction_type = 'follow'`. There is **no separate follows table** — the old `wp_fanfic_follows` table was removed entirely.

### 7.1 How `chapter_id` Is Used for Follows

For likes/dislikes/ratings, `chapter_id` always stores a chapter post ID. For follows, `chapter_id` stores the **post ID being followed**, which can be either:

- A `fanfiction_story` post ID (story-level follow, localStorage key: `story_N_chapter_0`)
- A `fanfiction_chapter` post ID (chapter-level follow, localStorage key: `story_N_chapter_M`)

The post type is determined at query time via `JOIN wp_posts` to distinguish story follows from chapter follows.

### 7.2 No Aggregate Counters

Unlike likes/ratings, follows do **not** update any aggregate counter columns in search index tables. Follow counts are computed on-the-fly via `COUNT(*)` queries against the interactions table (with transient caching).

### 7.3 Frontend Flow (Same Pattern as Likes)

1. User clicks follow button (works for both anonymous and logged-in users).
2. `FanficLocalStore.toggleFollow(storyId, chapterId)` toggles `entry.follow` in localStorage immediately.
3. UI updates optimistically via `updateFollowDisplay()`.
4. AJAX request to `fanfic_toggle_follow` with `anonymous_uuid` if not logged in.
5. On AJAX error: localStorage and UI are reverted via `revertFollowLocal()`.

### 7.4 Button Data Attributes

Follow buttons carry these attributes (set in `class-fanfic-shortcodes-buttons.php`):

- `data-post-id` — the actual post ID sent to AJAX (story ID or chapter ID)
- `data-story-id` — always the parent story ID (used to build localStorage key)
- `data-chapter-id` — the chapter ID, or `0` for story-level follows

### 7.5 PHP Class Hierarchy

- **`Fanfic_Interactions`** — low-level methods: `record_follow()`, `remove_follow()`, `has_follow()`. These call `upsert_interaction()` / `delete_interaction()` / `has_interaction()` with `type = 'follow'`. Fire `do_action('fanfic_follow_added')` / `do_action('fanfic_follow_removed')`.
- **`Fanfic_Follows`** — high-level facade: `toggle_follow()`, `is_followed()`, `get_user_follows()`, `get_follow_count()`, `get_most_followed_stories()`, render methods for dashboard cards. All queries target `wp_fanfic_interactions WHERE interaction_type = 'follow'`.

### 7.6 Login Sync

Follows participate in the same `apply_local_entry_to_db()` sync path as likes:

- If `entry.follow` is truthy in local data → `record_follow()`.
- If `entry.follow` is absent but a follow row exists in DB → `remove_follow()`.
- Anonymous follow rows are re-attributed to the user on login (same as likes).

### 7.7 Dashboard Display

The dashboard (`template-dashboard.php`) shows two separate follow sections:

- **"Followed Stories"** — `render_user_follows_dashboard($user_id, 'story', 20, 0)`
- **"Followed Chapters"** — `render_user_follows_dashboard($user_id, 'chapter', 20, 0)`

Each section has its own "Load More" button with `data-follow-type="story"` or `"chapter"`. The AJAX handler `fanfic_load_user_follows` requires `follow_type` as a required param ('story' or 'chapter', no 'all').

## 8. Table Size Control (No Aggregate Decrement for Cleanup)

Cleanup policy is cap-based for anonymous rows in `wp_fanfic_interactions`:

- Hard cap: **150,000** anonymous rows (`user_id IS NULL`).
- Target after trimming: **100,000** rows.
- Deletion batch: **1,000 rows/job**.
- Order: oldest first (`updated_at`, `id` ascending).
- Mechanism: cron continuation jobs until target is reached.

Important: cleanup deletes interaction rows only; aggregate counters are intentionally not decremented.

## 9. Practical Implications

- Anonymous interactions count globally immediately.
- After very old anonymous rows are trimmed, those users may no longer be able to edit previous anonymous interactions server-side.
- LocalStorage continues to prevent duplicate actions in the same browser/profile while data is present.
