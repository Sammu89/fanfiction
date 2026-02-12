# Fanfiction Plugin — Unified Interaction, View & Ranking System Refactor Plan

## Objective

Redesign the entire interaction system (views, likes, loves, thumbs up/down, ratings 1–5 in 0.5 increments) into a unified, scalable ranking architecture that:

* Handles 50,000+ hits/day
* Uses deterministic server-side deduplication
* Centralizes ranking data in search index tables
* Avoids heavy runtime queries (no SUM, no aggregation loops)
* Supports weekly, monthly, all-time, and trending rankings
* Is consistent with the existing "no IP storage" philosophy
* Is extensible for future metrics without schema redesign

This document defines the unified view and interaction system architecture.

---

# 1. Architectural Principles

1. Search index tables are the **single source of truth** for listing and ordering.
2. No runtime aggregation queries for ranking.
3. All ranking metrics are precomputed at write time.
4. Dedupe is server-side and deterministic.
5. All counter increments are atomic SQL updates.
6. Views, likes, votes, ratings share a unified interaction engine.

---

# 2. Interaction Types (Unified Model)

We support the following interaction signals:

* 👁 View (chapter-based, story aggregates from chapters)
* 👍 Thumbs Up / ❤ Love (love is an alias of Thumbs Up if Thumbs down is disabled, story aggregates from chapters)
* 👎 Thumbs Down (story aggregates from chapters)
* ⭐ Rating (1–5 in 0.5 increments) (its the average off all classifications accross all chapters, not an average of the average of all chapters)

Each interaction contributes to ranking (trending) via configurable weights.

---

# 3. New Core Tables

## 3.1 Daily Dedupe Table (Views Only)

`wp_fanfic_daily_views`

Purpose: enforce "1 view per chapter per visitor per day".

Columns:

* `visitor_hash CHAR(64) NOT NULL`
* `chapter_id BIGINT(20) NOT NULL`
* `view_date DATE NOT NULL`

Indexes:

* `PRIMARY KEY (visitor_hash, chapter_id, view_date)`
* `KEY idx_chapter_date (chapter_id, view_date)`

Notes:

* No IP storage.
* Visitor hash derived from logged user ID or anonymous UUID cookie.
* Old rows cleaned via daily cron (delete older than ~40 days).

---

## 3.2 Interaction Events Table (Likes / Votes / Ratings)

`wp_fanfic_interactions`

Purpose: store single authoritative record per visitor per target.

Columns:

* `visitor_hash CHAR(64) NOT NULL`
* `target_type ENUM('story','chapter') NOT NULL`
* `target_id BIGINT(20) NOT NULL`
* `interaction_type ENUM('like','dislike','love','rating') NOT NULL`
* `value DECIMAL(3,1) NOT NULL`

  * For like/love/dislike: store 1 or -1 or 0
  * For rating: store 0.5–5.0
* `created_at DATETIME NOT NULL`
* `updated_at DATETIME NOT NULL`

Primary key:

* `PRIMARY KEY (visitor_hash, target_type, target_id, interaction_type)`

Indexes:

* `KEY idx_target (target_type, target_id)`

This ensures:

* One interaction per visitor per item per type
* Easy update (INSERT ... ON DUPLICATE KEY UPDATE)
* No aggregation scans needed at listing time

---

## 3.3 Chapter Search Index Table

`wp_fanfic_chapter_search_index`

Purpose: chapter-level popularity and trending.

Core columns:

* `chapter_id BIGINT(20) PRIMARY KEY`
* `story_id BIGINT(20) NOT NULL`

Views buckets:

* `views_total BIGINT(20) NOT NULL DEFAULT 0`
* `views_week BIGINT(20) NOT NULL DEFAULT 0`
* `views_month BIGINT(20) NOT NULL DEFAULT 0`
* `views_week_stamp INT NOT NULL DEFAULT 0`
* `views_month_stamp INT NOT NULL DEFAULT 0`

Votes / likes buckets:

* `likes_total BIGINT(20) NOT NULL DEFAULT 0`
* `likes_week BIGINT(20) NOT NULL DEFAULT 0`
* `likes_month BIGINT(20) NOT NULL DEFAULT 0`

Ratings:

* `rating_sum_total DOUBLE NOT NULL DEFAULT 0`
* `rating_count_total BIGINT(20) NOT NULL DEFAULT 0`
* `rating_avg_total DOUBLE NOT NULL DEFAULT 0`

Trending:

* `trending_week DOUBLE NOT NULL DEFAULT 0`
* `trending_month DOUBLE NOT NULL DEFAULT 0`

Indexes:

* `KEY idx_views_week (views_week)`
* `KEY idx_views_month (views_month)`
* `KEY idx_trending_week (trending_week)`
* `KEY idx_trending_month (trending_month)`
* `KEY idx_story_id (story_id)`

---

# 4. Story Search Index Extension

Extend `wp_fanfic_story_search_index` with symmetrical fields:

Views:

* `views_week`
* `views_month`
* `views_week_stamp`
* `views_month_stamp`

Likes / Votes:

* `likes_total`
* `likes_week`
* `likes_month`

Ratings:

* `rating_sum_total`
* `rating_count_total`
* `rating_avg_total`

Trending:

* `trending_week`
* `trending_month`

Add proper indexes on sortable fields.

---

# 5. Visitor Identity System (Unified)

Create `Fanfic_Visitor_Identity` class.

Rules:

* Logged-in → hash("u:{user_id}")
* Anonymous → persistent UUID cookie → hash("a:{uuid}")
* No IP storage

This identity is reused by:

* View dedupe
* Likes
* Votes
* Ratings

---

# 6. View Tracking Flow (Chapters)

1. Validate request (singular chapter, not author).
2. Resolve visitor_hash.
3. INSERT IGNORE into daily dedupe table.
4. If new → atomic UPDATE story + chapter index tables.

Bucket logic:

* week_stamp = date('oW')
* month_stamp = date('Ym')

Atomic reset pattern:

```
views_week = IF(views_week_stamp = :week, views_week + 1, 1)
```

---

# 7. Likes / Votes / Ratings Flow

On interaction submit:

1. Resolve visitor_hash.
2. INSERT ... ON DUPLICATE KEY UPDATE in `wp_fanfic_interactions`.
3. Compute delta (old value vs new value).
4. Apply atomic UPDATE to story or chapter index counters.
5. Recompute rating_avg_total if rating changed.
6. Recompute trending buckets.

No aggregation at listing time.

---

# 8. Trending Formula

Trending is precomputed at write time.

Example:

```
trending_week =
    (views_week * weight_views)
  + (likes_week * weight_likes)
  + (rating_avg_total * weight_rating)
```

Weights configurable via settings.

No ORDER BY formulas in queries.

---

# 9. Listing Rules

All listings must read from:

* `wp_fanfic_story_search_index`
* `wp_fanfic_chapter_search_index`

Never:

* SUM postmeta
* Loop through chapters to compute totals
* Join interaction tables for counts

---

# 10. Migration Strategy

Phase 1 — Schema creation

* Add new tables
* Add new index columns

Phase 2 — Write path refactor

* Consolidate all view writes into one deterministic chapter-view path
* Consolidate ratings/likes into the unified interaction model
* Centralize through RankingService

Phase 3 — Read path cleanup

* Remove meta-based view reads
* Remove O(n) story sum logic

---

# 11. Scalability Expectations

At 50,000 hits/day:

* ~50k INSERT IGNORE (views)
* ~50k story index UPDATE
* ~50k chapter index UPDATE
* Interaction writes only on user action (far less frequent)

All operations indexed and O(1).

---

# 12. Final Requirement for Implementation

The AI agent must:

* Enforce deterministic daily view dedupe in indexed tables.
* Implement atomic SQL updates.
* Ensure ISO-week (Monday) resets.
* Centralize all ranking counters in search index tables.
* Maintain backwards compatibility during migration.
* Prepare schema for likes, dislikes, loves, ratings even if not immediately enabled.

This document defines the full unified interaction and ranking system architecture.
