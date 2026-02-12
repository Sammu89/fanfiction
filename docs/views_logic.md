# Views Logic (Unified)

## Goal
This plugin now uses one unified view system:
- A **story view is the sum of chapter views**.
- Views are counted only on **chapter page views**.
- Duplicate views are blocked deterministically per visitor/chapter/day.
- Story/chapter listing counters come from search index tables (no runtime chapter loops for listing/ranking).

## Canonical Source of Truth
Canonical write/read tables for views are:
- `wp_fanfic_chapter_search_index`
  - `views_total`, `views_week`, `views_month`, stamps, trending.
- `wp_fanfic_story_search_index`
  - `view_count` (all-time story total), `views_week`, `views_month`, stamps, trending.

`view_count` in `wp_fanfic_story_search_index` is the canonical story total used by cards, dashboard, shortcodes, and ranking queries.

## Dedupe + Identity
### Visitor identity (`includes/class-fanfic-visitor-identity.php`)
- Logged-in user: `sha256("u:{user_id}")`
- Anonymous user: persistent UUID cookie `fanfic_vid`, hashed as `sha256("a:{uuid}")`
- No IP storage.

### Daily dedupe table (`wp_fanfic_daily_views`)
- PK: `(visitor_hash, chapter_id, view_date)`
- Rule: one accepted view per chapter/visitor/day.

## View Tracking Flow
Implemented in `includes/class-fanfic-views.php`:
1. Hook: `template_redirect`.
2. Only runs on `is_singular('fanfiction_chapter')` and published chapter.
3. Resolves parent story.
4. Skips author/co-author self-views.
5. Resolves `visitor_hash`.
6. `INSERT IGNORE` into `wp_fanfic_daily_views`.
7. If inserted (new daily view): atomically increment chapter + story index counters.

## Counter Semantics
### Chapter (`wp_fanfic_chapter_search_index`)
- `views_total`: lifetime chapter views.
- `views_week` and `views_month`: rolling bucket counters with stamp reset pattern:
  - `views_week = IF(views_week_stamp = current_iso_week, views_week + 1, 1)`
  - `views_month = IF(views_month_stamp = current_ym, views_month + 1, 1)`

### Story (`wp_fanfic_story_search_index`)
- `view_count`: all-time story views (incremented once per accepted chapter view).
- `views_week` / `views_month`: same stamp-reset pattern.

Because story increments happen in the same accepted chapter-view write path, story totals are chapter-derived by design.

## Anti-Double Rules
A view is ignored when:
- Not a chapter singular request.
- Chapter is not published.
- Visitor is story author or accepted co-author.
- Same visitor already viewed the same chapter on the same date.

## Read Paths (Unified)
All main readers use `Fanfic_Views`:
- `Fanfic_Views::get_story_views($story_id)`
- `Fanfic_Views::get_chapter_views($chapter_id)`

Used by:
- Dashboard/template stats.
- Author dashboard totals.
- Story/chapter stats shortcodes.
- Story card helpers and cache wrappers.

## Search Index Safety
`Fanfic_Search_Index::update_index()` preserves ranking/view counter columns when reindexing metadata so content reindex operations do not reset counters.

## Schema + Migration
`includes/class-fanfic-database-setup.php` now ensures:
- `wp_fanfic_daily_views`
- `wp_fanfic_interactions` (prepared for unified likes/dislikes/love/rating writes)
- `wp_fanfic_chapter_search_index`
- Extended `wp_fanfic_story_search_index` ranking columns/indexes.

It also synchronizes story ranking counters from chapter index counters during schema migration so one unified model is maintained.
For legacy installs where only story totals existed, migration seeds a primary chapter with that story total first, so `story = SUM(chapters)` remains consistent.

## Cleanup
Daily cleanup removes stale dedupe rows from `wp_fanfic_daily_views` (default retention: 40 days):
- Cron hook: `fanfic_cleanup_daily_views`
- Implemented in `Fanfic_Views::cleanup_old_daily_views()`

## Consolidation Status
- Runtime view reads are unified through `Fanfic_Views` and index tables.
- View writes are centralized in one chapter-page tracking flow with daily dedupe.

## Key Files
- `includes/class-fanfic-views.php`
- `includes/class-fanfic-visitor-identity.php`
- `includes/class-fanfic-database-setup.php`
- `includes/class-fanfic-search-index.php`
- `includes/cache/story-cache.php`
- `includes/class-fanfic-author-dashboard.php`
- `templates/template-dashboard.php`
