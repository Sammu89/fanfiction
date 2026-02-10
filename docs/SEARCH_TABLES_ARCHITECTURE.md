# Search Tables Architecture (Search + Sync Guide)

## Purpose of This Document

This document explains how the plugin search system works after the search-table optimization.

Goal: an agent or developer can understand:
- what each search table stores,
- how data stays synchronized,
- how browse/search filters are executed,
- where each piece of data lives,
- why there are two search tables (and why that scales better).

This guide is based on the current code paths in:
- `includes/class-fanfic-database-setup.php`
- `includes/class-fanfic-search-index.php`
- `includes/functions.php`
- `includes/class-fanfic-fandoms.php`
- `includes/class-fanfic-languages.php`
- `includes/class-fanfic-custom-taxonomies.php`

Current DB schema version: `1.5.2`.

---

## High-Level Model

The search system uses two runtime tables:

1. `wp_fanfic_story_search_index`
- One row per story.
- Stores full-text/search-card metadata.
- Optimized for:
  - text search (`MATCH ... AGAINST`),
  - card rendering data preload,
  - published-story gating for search/browse.

2. `wp_fanfic_story_filter_map`
- Many rows per story (one per facet value).
- Stores normalized filter facets as key-value rows.
- Optimized for:
  - complex filter combinations,
  - match-any and match-all behavior,
  - low-cost indexed lookups.

Think of it as:
- `search_index` = "story profile row",
- `filter_map` = "search tags ledger".

---

## Table Definitions

## `wp_fanfic_story_search_index`

One row per story (`PRIMARY KEY (story_id)`), includes:
- search text: `indexed_text` (FULLTEXT),
- card/basic metadata: title, slug, summary, status, author, dates, counts,
- serialized legacy/helper fields: `fandom_slugs`, `warning_slugs`, `genre_names`,
- language and translation metadata:
  - `language_slug`
  - `translation_group_id`
  - `translation_count`
  - `view_count`,
- age and tags:
  - `age_rating`
  - `visible_tags`
  - `invisible_tags`.

Important indexes:
- `FULLTEXT idx_search_fulltext (indexed_text)`
- `idx_language (language_slug)`
- `idx_translation_group (translation_group_id)`
- `idx_age_rating (age_rating)`

## `wp_fanfic_story_filter_map`

Row format:
- `story_id`
- `facet_type` (examples: `genre`, `language`, `warning`, `fandom`, `status`, `age`, `custom:pairing`)
- `facet_value` (normalized slug/value)

Primary key:
- `(story_id, facet_type, facet_value)` (prevents duplicates)

Indexes:
- `idx_facet_lookup (facet_type, facet_value, story_id)` for fast filter lookups
- `idx_story_facet (story_id, facet_type)` for fast story sync/rebuild operations

---

## Data Ownership (Where Data Is Stored)

Canonical source-of-truth is still the main story/taxonomy/relation storage:
- WordPress posts/taxonomies/meta
- plugin relation tables (`story_fandoms`, `story_languages`, `story_warnings`, `story_custom_terms`)

Search runtime data is denormalized into search tables:

- Text and card metadata is stored in `search_index`.
- Filter facets are stored in `filter_map`.

Search/browse execution should use search tables, not canonical relation joins at runtime.

---

## Sync Architecture

## Central sync function

`Fanfic_Search_Index::update_index($story_id)` is the core sync entrypoint.

It does:
1. Rebuild the `search_index` row for the story.
2. Rebuild the same story's `filter_map` rows via `sync_filter_map($story_id)`.
3. Invalidate global filter count cache (`global_filter_counts`).

## Filter-map rebuild behavior

`sync_filter_map($story_id)` uses a safe rebuild strategy:
1. Delete all existing filter rows for the story.
2. Recompute facets from current canonical/story data.
3. Insert new rows.

Facets generated:
- `language`
- `age`
- `fandom` (0..n)
- `warning` (0..n)
- `genre` (0..n)
- `status` (usually 1)
- `custom:{taxonomy_slug}` (0..n for each custom taxonomy)

## Delete behavior

`Fanfic_Search_Index::delete_index($story_id)` removes:
- story row from `search_index`
- all rows for story from `filter_map`

---

## Sync Triggers (Write Paths)

Main hooks in `Fanfic_Search_Index::init()`:
- `save_post_fanfiction_story` -> `on_story_save` -> `update_index`
- `save_post_fanfiction_chapter` -> parent story `update_index`
- `before_delete_post` -> `delete_index` (story), parent reindex (chapter)
- `profile_update` -> reindex all stories by that author (if display name changed)
- `fanfic_tags_updated` -> reindex story
- `fanfic_translations_updated` -> reindex changed story + affected group stories

Additional explicit sync-safe saves:
- `Fanfic_Fandoms::save_story_fandoms()` calls `Fanfic_Search_Index::update_index()`
- `Fanfic_Languages::save_story_language()` calls `Fanfic_Search_Index::update_index()`
- `Fanfic_Custom_Taxonomies::save_story_terms()` calls `Fanfic_Search_Index::update_index()`
- Warnings age sync path already calls search-index update.

Why these extra calls exist:
- story relation writes can happen after story post save,
- explicit reindex after relation save prevents stale search facets.

---

## Search Request Pipeline

Entry function:
- `fanfic_build_stories_query_args($params, $paged, $per_page)`

Pipeline:

1. Start from published stories query skeleton.

2. If user typed text search:
- call `Fanfic_Search_Index::search(keyword, limit)` (FULLTEXT in `search_index`)
- get candidate IDs ranked by relevance.

3. Apply facet filters using `filter_map`:
- helper: `fanfic_get_story_ids_by_filter_map_facet(facet_type, values, require_all_values)`
- each filter returns published story IDs by joining:
  - `filter_map m`
  - `search_index idx` with `idx.story_status = 'publish'`

4. Intersect candidate sets:
- helper: `fanfic_intersect_story_id_sets(current, incoming)`
- used for:
  - include warnings
  - genre
  - status
  - fandom
  - language
  - age
  - each custom taxonomy

5. Apply exclude warnings:
- fetch IDs with excluded warnings
- push them to `post__not_in`.

6. Build final `WP_Query` args:
- `post__in` for positive candidates
- `post__not_in` for exclusions
- sorting:
  - explicit sort options,
  - otherwise `post__in` ordering for text relevance,
  - fallback to modified date desc.

Important:
- runtime filtering now avoids direct canonical relation-table scans for these facets.

---

## Match-Any vs Match-All Semantics

Global toggle: `match_all_filters`.

Behavior by facet group:
- `genre`: supports all-values match when toggle enabled.
- `fandom`: supports all-values match when toggle enabled.
- custom taxonomy:
  - multi-select taxonomies support all-values match when toggle enabled.
  - single-select custom taxonomies remain any/one semantics.
- `status`, `language`, `age`, warnings include/exclude:
  - handled with their specific expected behavior (not forced to all-values).

Implementation detail:
- all-values matching uses SQL `HAVING COUNT(DISTINCT facet_value) >= selected_count`.

---

## Filter Count Pipeline (Search Form Counts)

Global counts function:
- `fanfic_get_search_filter_option_counts()`

Now reads from `filter_map` (+ publish gating via `search_index`) for:
- statuses
- genres
- ages
- languages
- warnings
- fandoms
- custom taxonomy terms (`facet_type LIKE 'custom:%'`)

Helpers:
- `fanfic_get_filter_map_counts_by_facet($facet_type)`
- `fanfic_get_custom_taxonomy_term_count(...)` now resolves taxonomy slug and reads `custom:{slug}` counts.

Cache:
- global counts cache key: `global_filter_counts` (via `Fanfic_Cache`)
- invalidated on reindex (`update_index`).

---

## Story Card Rendering Path

Card metadata preload:
- `fanfic_preload_story_card_index_data($story_ids)` reads from `search_index`.

Per-card accessor:
- `fanfic_get_story_card_index_data($story_id)` returns:
  - `language_slug`
  - `translation_group_id`
  - `translation_count`
  - `view_count`

This avoids N+1 relation queries when rendering grids.

Card HTML `data-*` attributes use preloaded index data:
- `data-language`
- `data-translation-group`
- `data-views`

Used by frontend dedup logic and UI display.

---

## Migration and Backfill

Schema migration:
- `migrate_story_filter_map_schema()` in database setup.

Backfill runs when:
- `filter_map` exists,
- `search_index` exists,
- `filter_map` is still empty,
- `search_index` has rows.

Backfill sources:
- language and age from `search_index`
- fandoms and warnings from canonical relation tables when available
- fallback to CSV split from `search_index` columns if relation tables unavailable
- genre/status from WP term relationships
- custom taxonomies from custom relation tables

This gives existing installs immediate filter-map data without full manual rebuild.

---

## Practical "Where Is Data?" Examples

Example 1: "Show all Portuguese stories"
- Runtime reads `filter_map` rows where:
  - `facet_type = 'language'`
  - `facet_value = 'pt'` (or `pt-br`, `pt-pt` as selected)
- Then gates to published via `search_index.story_status`.

Example 2: "Show stories with warning A, but not warning B"
- Include uses `facet_type='warning'`, `facet_value='warning-a'`
- Exclude uses `facet_type='warning'`, `facet_value='warning-b'` into `post__not_in`
- No per-story warning table lookup during search request.

Example 3: "Custom taxonomy Pairing: Harry/Draco OR Hermione/Ron"
- Facet type is `custom:pairing`
- Values are term slugs.
- Query is one indexed lookup group in `filter_map`.

Example 4: Story card translation badge
- card preload reads `translation_count` from `search_index`
- no group lookup per card at render time.

---

## Why Two Tables (Non-Programmer Summary)

If we used only one giant table:
- each story row would need many repeated columns or comma-separated fields for every filter value,
- filtering combinations get slower and harder as data grows,
- adding custom taxonomies gets messy fast.

With two tables:
- table 1 (`search_index`) keeps one clean "story profile row",
- table 2 (`filter_map`) keeps one row per filter value, which databases index very efficiently.

Practical analogy:
- `search_index` is a book's cover sheet.
- `filter_map` is the library index cards for subjects/languages/warnings.
- Searching by text reads the cover sheet index.
- Filtering by facets reads index cards.
- Combined search stays fast even with many stories and many custom filters.

This is why it scales better, especially on shared hosting:
- less heavy join logic at request time,
- fewer expensive scans across multiple canonical relation tables,
- predictable indexed lookups for advanced filter combinations.

---

## Operational Notes

- Canonical data still matters; search tables are denormalized runtime copies.
- If search output ever looks stale, run a full search index rebuild so both tables are regenerated story-by-story.
- Keep using `Fanfic_Search_Index::update_index($story_id)` after any future write path that changes searchable/filterable story data.

---

## Agent Quick Checklist

When changing search/filter behavior, verify:
- `search_index` schema still includes card/text fields needed.
- `filter_map` has facet rows for all filter dimensions.
- `update_index()` still calls `sync_filter_map()`.
- all write paths that change facets trigger `update_index()`.
- `fanfic_build_stories_query_args()` keeps using map helpers for facets.
- `fanfic_get_search_filter_option_counts()` reflects the same facet logic.
- caches invalidated where needed (`global_filter_counts` minimum).

