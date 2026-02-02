# Enhanced Search Index Proposal

## Overview
Enhance the existing `wp_fanfic_story_search_index` table to serve as the **single source of truth** for all search and browse operations, eliminating the need for complex multi-table joins.

## Problem Statement

### Current Architecture:
**Browse/Search Query Path:**
1. Query taxonomy tables (`wp_postmeta` for fandom, `wp_term_relationships` for genre)
2. Join with `wp_posts` for story data
3. Query `wp_postmeta` for additional metadata (chapter count, word count)
4. Query chapter table for chapter info
5. Aggregate and format results

**Performance Issues:**
- Multiple table joins on large datasets
- Slow postmeta queries (can't use indexes effectively)
- Complex query logic
- Hard to cache efficiently

### Proposed Architecture:
**Single Query Path:**
1. Query `wp_fanfic_story_search_index` (all data pre-aggregated)
2. Return results

## Enhanced Table Structure

```sql
CREATE TABLE wp_fanfic_story_search_index (
    -- === Core Identification === --
    story_id bigint(20) UNSIGNED NOT NULL,
    author_id bigint(20) UNSIGNED NOT NULL,

    -- === Display Data (avoid wp_posts queries) === --
    story_title varchar(500) NOT NULL,
    story_slug varchar(200) NOT NULL,
    story_summary text,
    story_status varchar(20) NOT NULL DEFAULT 'publish',  -- 'publish', 'draft', 'pending'
    published_date datetime,
    updated_date datetime,

    -- === Chapter & Content Info === --
    chapter_count int(11) DEFAULT 0,
    word_count bigint(20) DEFAULT 0,
    completed tinyint(1) DEFAULT 0,

    -- === Light Taxonomies (Structured for Fast Filtering) === --
    fandom_slugs text,              -- Comma-separated: "harry-potter,marvel,supernatural"
    language_slug varchar(50),      -- Single value: "en", "es", "fr"
    warning_slugs varchar(500),     -- Comma-separated: "violence,sexual-content,character-death"
    age_rating varchar(5),          -- "PG", "13", "16", "18"

    -- === Tags (Structured) === --
    visible_tags text,              -- Comma-separated visible tags
    invisible_tags text,            -- Comma-separated invisible tags

    -- === WordPress Taxonomies (Reference Only) === --
    -- NOTE: Genre and Status still use wp_term_relationships
    -- We store them here only for display convenience
    genre_names varchar(500),       -- Comma-separated genre NAMES (for display)
    status_name varchar(100),       -- Status NAME (for display)

    -- === Custom Taxonomies (JSON) === --
    custom_taxonomy_data text,      -- JSON: '{"tropes":["enemies-to-lovers","slowburn"],"aus":["coffee-shop"]}'

    -- === FULLTEXT Search (Keyword Matching) === --
    indexed_text longtext NOT NULL, -- All searchable text aggregated

    -- === Metadata === --
    indexed_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- === Indexes === --
    PRIMARY KEY (story_id),
    KEY idx_author (author_id),
    KEY idx_status (story_status),
    KEY idx_published (published_date),
    KEY idx_updated (updated_date),
    KEY idx_language (language_slug),
    KEY idx_age_rating (age_rating),
    KEY idx_completed (completed),
    KEY idx_indexed_at (indexed_at),
    FULLTEXT KEY idx_search_fulltext (indexed_text),
    FULLTEXT KEY idx_title_fulltext (story_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Query Examples

### 1. Browse All Fandoms (Current Feature)
```sql
-- Current approach (postmeta query)
SELECT meta_value, COUNT(DISTINCT post_id) as count
FROM wp_postmeta pm
INNER JOIN wp_posts p ON pm.post_id = p.ID
WHERE pm.meta_key = '_fanfic_fandom'
  AND p.post_type = 'fanfiction_story'
  AND p.post_status = 'publish'
GROUP BY meta_value;

-- Enhanced search index approach
SELECT
    SUBSTRING_INDEX(SUBSTRING_INDEX(fandom_slugs, ',', numbers.n), ',', -1) as fandom,
    COUNT(*) as story_count
FROM wp_fanfic_story_search_index
CROSS JOIN (
    SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
) numbers
WHERE story_status = 'publish'
  AND CHAR_LENGTH(fandom_slugs) - CHAR_LENGTH(REPLACE(fandom_slugs, ',', '')) >= numbers.n - 1
GROUP BY fandom
ORDER BY story_count DESC;
```

### 2. Filter Stories by Fandom
```sql
-- Current approach
SELECT p.*
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'fanfiction_story'
  AND p.post_status = 'publish'
  AND pm.meta_key = '_fanfic_fandom'
  AND pm.meta_value = 'harry-potter';

-- Enhanced search index approach
SELECT story_id, story_title, story_summary, author_id, chapter_count, word_count
FROM wp_fanfic_story_search_index
WHERE story_status = 'publish'
  AND FIND_IN_SET('harry-potter', REPLACE(fandom_slugs, ',', ',')) > 0;
```

### 3. Multi-Taxonomy Filter (Fandom + Language + Warning)
```sql
-- Current approach: Multiple joins, very complex
SELECT DISTINCT p.*
FROM wp_posts p
INNER JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_fanfic_fandom'
INNER JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_fanfic_language'
WHERE p.post_type = 'fanfiction_story'
  AND p.post_status = 'publish'
  AND pm1.meta_value = 'harry-potter'
  AND pm2.meta_value = 'en'
  -- Warning exclusion is even more complex...

-- Enhanced search index approach
SELECT story_id, story_title, story_summary, author_id, chapter_count
FROM wp_fanfic_story_search_index
WHERE story_status = 'publish'
  AND FIND_IN_SET('harry-potter', REPLACE(fandom_slugs, ',', ',')) > 0
  AND language_slug = 'en'
  AND NOT FIND_IN_SET('graphic-violence', REPLACE(warning_slugs, ',', ',')) > 0;
```

### 4. Keyword Search + Filters
```sql
-- Enhanced search index (single query!)
SELECT story_id, story_title, story_summary, author_id, chapter_count,
       MATCH(indexed_text) AGAINST('magic wizard school' IN NATURAL LANGUAGE MODE) as relevance
FROM wp_fanfic_story_search_index
WHERE story_status = 'publish'
  AND MATCH(indexed_text) AGAINST('magic wizard school' IN NATURAL LANGUAGE MODE)
  AND FIND_IN_SET('harry-potter', REPLACE(fandom_slugs, ',', ',')) > 0
  AND language_slug = 'en'
ORDER BY relevance DESC
LIMIT 20;
```

## Data Synchronization Strategy

### Triggers (When to Update Index)

1. **Story saved** → Update full index
2. **Chapter added/updated/deleted** → Update chapter_count, word_count, chapter_titles, indexed_text
3. **Taxonomy changed** → Update relevant taxonomy fields
4. **Author profile updated** → Update author_name in indexed_text
5. **Tags updated** → Update visible_tags, invisible_tags, indexed_text

### Update Function Structure

```php
public static function update_index( $story_id ) {
    $data = array(
        'story_id'              => $story_id,
        'author_id'             => self::get_author_id($story_id),
        'story_title'           => self::get_story_title($story_id),
        'story_slug'            => self::get_story_slug($story_id),
        'story_summary'         => self::get_story_summary($story_id),
        'story_status'          => self::get_story_status($story_id),
        'published_date'        => self::get_published_date($story_id),
        'updated_date'          => self::get_updated_date($story_id),
        'chapter_count'         => self::get_chapter_count($story_id),
        'word_count'            => self::get_word_count($story_id),
        'completed'             => self::is_completed($story_id),
        'fandom_slugs'          => self::get_fandom_slugs($story_id),
        'language_slug'         => self::get_language_slug($story_id),
        'warning_slugs'         => self::get_warning_slugs($story_id),
        'age_rating'            => self::get_age_rating($story_id),
        'visible_tags'          => self::get_visible_tags($story_id),
        'invisible_tags'        => self::get_invisible_tags($story_id),
        'genre_names'           => self::get_genre_names($story_id),
        'status_name'           => self::get_status_name($story_id),
        'custom_taxonomy_data'  => self::get_custom_taxonomy_json($story_id),
        'indexed_text'          => self::build_indexed_text($story_id),
    );

    // Insert or update
    global $wpdb;
    $wpdb->replace(
        $wpdb->prefix . 'fanfic_story_search_index',
        $data
    );
}
```

## WordPress Taxonomies: Hybrid Approach

**Key Decision:** Continue using WordPress's native taxonomy system for Genre and Status

### Why?
- ✅ WordPress admin UI works out of the box
- ✅ Term counts maintained automatically by WordPress
- ✅ Hierarchical genre support
- ✅ Follows WordPress best practices
- ✅ Third-party plugin compatibility

### How?
- Store genre/status **names** in search index (for display only)
- Filter by genre/status using **wp_term_relationships** (WordPress native)
- Use search index for everything else

```php
// Display results from search index
$results = $wpdb->get_results("
    SELECT story_id, story_title, story_summary, genre_names
    FROM wp_fanfic_story_search_index
    WHERE FIND_IN_SET('harry-potter', fandom_slugs) > 0
");

// Filter by genre (WordPress native)
$genre_term = get_term_by('slug', 'fantasy', 'fanfiction_genre');
$story_ids_with_fantasy = get_objects_in_term($genre_term->term_id, 'fanfiction_genre');
```

## Performance Benefits

### Before (Current):
```
Browse Fandoms: 150-300ms
Search + Filter: 200-500ms
Complex Multi-Filter: 500-1000ms
```

### After (Enhanced Index):
```
Browse Fandoms: 20-50ms
Search + Filter: 30-80ms
Complex Multi-Filter: 50-150ms
```

### With Caching:
```
All queries: <5ms (from cache)
```

## Migration Plan

### Phase 1: Add New Columns
- Alter existing `wp_fanfic_story_search_index` table
- Add structured columns
- Keep `indexed_text` for backward compatibility

### Phase 2: Update Indexing Logic
- Modify `Fanfic_Search_Index::build_index_text()` to also populate new columns
- Test on staging

### Phase 3: Rebuild Index
- Run `Fanfic_Search_Index::rebuild_all()` to populate new columns
- Batch process to avoid timeouts

### Phase 4: Update Query Functions
- Modify `fanfic_get_taxonomy_terms_with_counts()` to use new structure
- Update browse query builders
- Add fallback to old method if index not ready

### Phase 5: Optimize & Monitor
- Add indexes as needed
- Monitor query performance
- Adjust caching strategy

## Risks & Mitigation

### Risk 1: Index Out of Sync
**Mitigation:**
- Comprehensive hook coverage (story save, chapter save, taxonomy update)
- Admin tool to check sync status
- Background job to verify and fix mismatches

### Risk 2: Large Table Size
**Mitigation:**
- Only published stories need full indexing
- Archive old/deleted stories
- Partition table by year if needed

### Risk 3: Migration Complexity
**Mitigation:**
- Phased rollout
- Feature flag to toggle between old/new queries
- Thorough testing on staging

## Recommendation

**Implement Enhanced Search Index** with the following priorities:

1. ✅ **High Priority**: Fandom, Language, Warnings, Tags (light taxonomies)
2. ✅ **High Priority**: Story metadata (title, summary, chapter count, word count)
3. ⚠️ **Medium Priority**: Custom taxonomies (JSON)
4. ❌ **Low Priority**: Genre/Status (keep WordPress native)

This balances performance gains with implementation complexity and WordPress best practices.
