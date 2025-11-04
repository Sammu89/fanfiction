# Cache Functions

This directory contains caching functions for the Fanfiction Manager plugin.

## Files

### story-cache.php
Provides caching functions for story and chapter operations to optimize performance.

## Cache Functions

### Story Functions

- **ffm_get_story_views($story_id)** - Get cached story view count (sum of all chapter views) - 5 min TTL
- **ffm_get_story_chapter_count($story_id)** - Get cached chapter count - 6 hour TTL
- **ffm_get_story_word_count($story_id)** - Get cached total word count - 6 hour TTL
- **ffm_is_story_valid($story_id)** - Check if story is valid (has intro, chapters, genres, status) - 6 hour TTL
- **ffm_get_story_rating($story_id)** - Get average story rating - 5 min TTL
- **ffm_get_recent_stories($page, $per_page)** - Get recent published stories - 30 min TTL
- **ffm_get_stories_by_genre($genre_id, $page, $per_page)** - Get stories by genre - 1 hour TTL
- **ffm_get_stories_by_status($status_id, $page, $per_page)** - Get stories by status - 1 hour TTL

### Chapter Functions

- **ffm_get_chapter_views($chapter_id)** - Get cached chapter views - 5 min TTL
- **ffm_get_chapter_rating($chapter_id)** - Get chapter rating - 5 min TTL
- **ffm_get_chapter_count($story_id)** - Efficient chapter count query (helper function)
- **ffm_get_chapter_list($story_id)** - Get ordered chapter list - 1 hour TTL

### Cache Management Functions

- **ffm_clear_story_cache($story_id)** - Clear all cache for a specific story
- **ffm_clear_chapter_cache($chapter_id)** - Clear all cache for a specific chapter and its parent story
- **ffm_clear_all_fanfiction_cache()** - Clear all fanfiction-related caches site-wide

## Cache TTL Constants

- `FANFIC_CACHE_5_MINUTES` - 5 minutes (300 seconds)
- `FANFIC_CACHE_30_MINUTES` - 30 minutes (1800 seconds)
- `FANFIC_CACHE_1_HOUR` - 1 hour (3600 seconds)
- `FANFIC_CACHE_6_HOURS` - 6 hours (21600 seconds)

## Usage

All functions use WordPress transients API for caching. They automatically:
1. Try to retrieve from transient cache first
2. If cache miss or expired, execute database query
3. Cache result with appropriate TTL
4. Return data or fallback value (0, empty array, false)

## Integration

The cache functions are automatically loaded by the core plugin class (`class-fanfic-core.php`) and are available globally throughout the plugin.

## Best Practices

- Use appropriate cache clearing functions when stories/chapters are updated
- For high-traffic operations, prefer cached functions over direct queries
- Monitor transient table size if site has many stories
- Consider object cache (Redis/Memcached) for production sites
