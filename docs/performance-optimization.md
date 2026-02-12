# Performance Optimization

## Database Query Optimization
**Pagination:**
All list displays ([story-list], [author-story-list], [chapters-list]) use offset-based pagination with LIMIT:
- Default: 10 items per page.
- Query structure: `SELECT * FROM posts LIMIT 10 OFFSET 0`.
- Allows infinite scroll via AJAX by incrementing offset.

**Database Indexes:**
Create on plugin activation for optimal query performance:

**On wp_posts table:**
- `(post_author, post_type, post_status)` - For author story queries.
- `(post_parent, post_type, menu_order)` - For chapter queries.
- `(post_status, post_type)` - For public listings.

**On view index tables:**
- `wp_fanfic_chapter_search_index`: `(story_id)`, `(views_week)`, `(views_month)`.
- `wp_fanfic_story_search_index`: `(view_count)`, `(views_week)`, `(views_month)`.

**On custom tables:**
- `wp_fanfic_bookmarks`: `(user_id)`, `(story_id)`.
- `wp_fanfic_follows`: `(user_id)`, `(author_id)`.
- `wp_fanfic_ratings`: `(chapter_id)`, `(user_id, user_ip)`.

## Query Optimization Strategies
**N+1 Problem Prevention:**
- When displaying list of stories, don't load author details in loop.
- Use `WP_Query` with `fields => 'ids'` then batch-fetch metadata.
- Cache author info to reduce multiple author queries.

**Lazy Loading (Heavy Shortcodes):**
Shortcodes like [story-comments] and [story-list] can load via AJAX:
- Initial page load: Button appears ("Load Comments" or "Show More Stories").
- On user click: AJAX request fetches content via REST endpoint.
- Response: HTML fragment inserted into page.

**Configurable:** Admins toggle lazy loading per-shortcode in Settings > General.

## Caching Beyond Transients
**Object Cache:**
If site uses object cache (Redis, Memcached), the plugin automatically uses it for transients, dramatically reducing database load.

**Page Cache:**
Site admins should use page caching (WP Super Cache, W3 Total Cache):
- Public story/chapter pages are fully cacheable (no user-specific content).
- Author dashboard pages use `nocache_headers()` to prevent caching (user-specific content).
- Archive/search pages cache for 1 hour (refreshed on new chapter publish).

**Browser Cache:**
Plugin sets appropriate cache headers:
- Static resources (CSS, JS): `Cache-Control: max-age=604800` (1 week).
- Story pages: `Cache-Control: max-age=3600` (1 hour, or full page cache).

## Transient System
- Indefinite-lifetime transients for story validity, chapter counts, etc.
- Hybrid invalidation: Individual (on single change) vs. bulk (on taxonomy changes, etc.).
- Manual cleanup utility in Settings > General.

For large-scale data (e.g., bookmarks), see features.md.
