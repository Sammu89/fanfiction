# 100% Search-Table-Driven Story Listing

## Goal
Make the story search/listing view fully driven by search tables, with no listing-data dependency on non-search tables at render time.

For this document, **100% search-table-driven** means:

1. The listing card content is read only from search tables.
2. Sorting/pagination inputs are driven by search tables.
3. No per-card queries to posts/meta/taxonomy/language/fandom/user tables.
4. Optional fields remain optional and render gracefully when missing.

## Scope
This applies to:

- Story cards rendered in search/listing results.
- Data needed to compose those cards (including translations).
- Sort behavior used by listing (`updated`, `created`, `alphabetical`).

This does **not** require changing chapter view or single story view.

## Current State (after layout phase)
Already table-driven in listing card:

- `story_title`, `story_summary`, `status_name`
- `chapter_count`, `word_count`, `view_count`, `likes_total`
- `genre_names`, `warning_slugs`, `fandom_slugs`, `age_rating`
- `language_slug`, `translation_group_id`, `translation_count`
- `published_date`, `updated_date`
- coauthor IDs/names

Still not fully table-driven:

- Featured image is still read through WordPress thumbnail APIs.
- Author display name/profile URL still depends on user/post URL helpers.
- Language human label is not persisted in search index (slug is mapped at runtime).
- `sort=updated` is still not guaranteed to use `fanfic_story_search_index.updated_date` ordering.
- Listing still initializes from `WP_Query` posts, not a pure search-table row set.

## What Must Be Added to Search Tables

## 1) `fanfic_story_search_index` new columns
Add columns required so card rendering does not need posts/meta/users/languages tables:

- `featured_image_url` `varchar(1000)` default `''`
- `featured_image_alt` `varchar(500)` default `''`
- `author_display_name` `varchar(255)` default `''`
- `author_profile_url` `varchar(1000)` default `''`
- `language_name` `varchar(120)` default `''`
- `language_native_name` `varchar(120)` default `''`
- `warning_names` `text` (comma-separated or JSON)
- `fandom_names` `text` (comma-separated or JSON)
- `translation_links_json` `longtext` (optional optimization; see section 3)

Recommended sorting/perf columns:

- `updated_date` index (`KEY idx_updated_date (updated_date)`)
- `published_date` index (`KEY idx_published_date (published_date)`)
- optional `story_title` index for alphabetical ordering if needed by SQL path

## 2) Keep existing columns used by cards
These should remain and continue to be maintained:

- `story_title`, `story_slug`, `story_summary`, `story_status`
- `author_id`, `coauthor_ids`, `coauthor_names`
- `published_date`, `updated_date`
- `chapter_count`, `word_count`
- `view_count`, `likes_total`, `follow_count` (if needed later)
- `genre_names`, `status_name`, `warning_slugs`, `fandom_slugs`
- `language_slug`, `translation_group_id`, `translation_count`
- `age_rating`

## 3) Translation block options
Requirement: “Also available in …” must come from same search table data.

Two valid approaches:

1. **On-page group preload (current direction)**
- One batched query by `translation_group_id` from `fanfic_story_search_index`.
- Build links from indexed story slug/language fields.
- No external table lookups.

2. **Precomputed JSON per story**
- Store `translation_links_json` for each story during indexing.
- Card rendering does zero extra translation queries.
- Heavier write/update cost when a translation group changes.

Either approach is compliant as long as data source is search tables only.

## 4) Sorting must be index-backed
To be fully table-driven, sort logic should not rely on WP post fields for listing order:

- `updated` => `fanfic_story_search_index.updated_date DESC`
- `created` => `fanfic_story_search_index.published_date DESC`
- `alphabetical` => `fanfic_story_search_index.story_title ASC`

Implementation note:
- Either run ID selection directly from search tables and pass ordered IDs to renderer,
- or join/index-order IDs first, then render by preloaded index cache.

## 5) Rendering contract for optional fields
Card renderer must handle missing values without fallback queries:

- No image: show placeholder block.
- No warnings/fandoms/genres/translations: hide that segment.
- No `updated_date`: hide updated label.
- No coauthors: render author only.

## Indexer Changes Required
Update `Fanfic_Search_Index::update_index()` data payload to populate new columns:

- Featured image URL/alt
- Author display name/profile URL
- Language label/native label
- Warning/fandom display names
- Optional `translation_links_json`

Also ensure update triggers cover:

- Story save/update
- Chapter save (already updates `updated_date` behavior)
- Featured image set/remove
- Author profile/display name change (reindex author stories)
- Language/fandom/warning rename changes (reindex affected stories)
- Translation group link/unlink updates (reindex all stories in group)

## Migration/Backfill Plan
1. Add new columns and indexes via `dbDelta`.
2. Bump plugin DB/index schema version.
3. Run full search index rebuild/backfill.
4. Verify listing works with mixed data during rollout (null-safe rendering).

## Validation Checklist
After implementation, confirm:

1. Story cards render all rows with DB query profiler showing no per-card external lookups.
2. `updated` sort matches `search_index.updated_date`, not post modified.
3. Translation links resolve for all group members from search tables only.
4. Optional fields missing do not break layout.
5. URLs remain correct after slug configuration changes (if URLs are persisted, reindex is required).

## Definition of Done
Listing is “100% search-table-driven” when:

1. Every card datum is available in search tables.
2. Card rendering does not call runtime lookups for listing data outside search tables.
3. Sort order uses search-index columns.
4. Translation links/languages are sourced from search tables only.
5. Full rebuild produces identical/expected listing output for all existing stories.
