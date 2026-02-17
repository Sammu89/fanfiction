# Search Index Architecture Review — What to Keep, What to Fix

## Section 1: Context

The plugin uses a denormalized `fanfic_story_search_index` table to make story card rendering fully table-driven (no per-card DB lookups). A recent implementation extended this table with new columns. This document reviews which decisions are sound and which introduce fragility or unnecessary write amplification.

---

## Section 2: Correct Decisions (Keep As-Is)

These columns/features are genuinely worth the denormalization cost:

| Column/Feature | Reason |
|---|---|
| `chapter_count`, `word_count` | Require expensive `COUNT()`/`SUM()` aggregations without index |
| `view_count`, `likes_total`, trending metrics | Require aggregation across `wp_fanfic_ratings` and interaction tables |
| `genre_names`, `status_name` | Taxonomy term queries per card without index |
| `warning_names`, `fandom_names` | Same — custom table joins per card |
| `language_name`, `language_native_name` | JOIN to language table per card without index |
| `fandom_slugs`, `warning_slugs` | Needed by filter map for faceted search |
| `indexed_text` FULLTEXT | No viable alternative for search |
| Sorting via `updated_date`/`published_date`/`story_title` | Eliminates `ORDER BY wp_posts.post_modified` on large table |
| `author_display_name` | User display name is stable; saves `get_the_author_meta()` call |
| `coauthor_names` | Same — comma-separated coauthor display names |

---

## Section 3: Over-Engineered / Fragile Decisions (Needs Fixing)

### 3A. Persisted Absolute URLs — The Main Problem

Three columns store **absolute URLs** instead of stable primitives:

- `author_profile_url` — built from `fanfic_members_slug` option + `user_login`
- `coauthor_profile_urls` — same
- `featured_image_url` — WordPress attachment URL (domain + path)

**Why this is wrong:**

1. **Domain/CDN migration** — all stored image URLs become stale. Every story needs reindexing just to serve images.
2. **`fanfic_members_slug` change** — all `author_profile_url` / `coauthor_profile_urls` values become wrong, triggering a full reindex of every story in the database.
3. **WordPress image regeneration or attachment move** — stored `featured_image_url` becomes a 404.
4. **Write amplification on slug change** — a single option update causes N×`update_index()` calls (one per story), each doing a full row replace including re-fetching all other data.

**Performance argument is weak** — the supposed savings are small because:

- WordPress object-caches `get_userdata()` in `wp_usermeta` cache group (in-memory after first call per request).
- `get_post_thumbnail_id()` hits post meta object cache — not a DB query after the first load.
- URL construction from `user_login` + base slug is pure PHP string concatenation — zero DB cost.

### 3B. `featured_image_alt` — Minor But Same Problem

Stored alt text is stable (it is post meta on the attachment), so this is less fragile than the URL, but it still adds a write dependency: if the alt text is updated on the attachment, stories are not reindexed. The alt could be wrong indefinitely.

---

## Section 4: Recommended Replacements

Replace the fragile URL columns with stable primitive columns:

| Remove | Add Instead | Render-Time Cost |
|---|---|---|
| `author_profile_url varchar(1000)` | `author_login varchar(60)` | String concat: `$slug . '/' . $login` — zero DB |
| `coauthor_profile_urls varchar(2000)` | `coauthor_logins varchar(1000)` | Same per coauthor |
| `featured_image_url varchar(1000)` | `featured_image_id bigint(20)` | `wp_get_attachment_image_url($id, 'medium')` — object-cached |
| `featured_image_alt varchar(500)` | *(remove entirely)* | Read from `get_post_meta($thumb_id, '_wp_attachment_image_alt')` — object-cached |

**Why `author_login` instead of `author_id`?**
`author_id` already exists in the index. `author_login` is the slug used in the URL. Storing it avoids `get_userdata()` at render time. It is stable — `user_login` almost never changes, and when it does, the existing `on_author_profile_update()` hook already catches it and reindexes.

**Why `featured_image_id` instead of URL?**

- Attachment IDs never change.
- `wp_get_attachment_image_url()` is object-cached by WordPress per request.
- Works correctly after domain changes, CDN migrations, and image regeneration.
- No reindex needed when an image is regenerated or CDN is changed.

---

## Section 5: Hook Changes Required

With the URL columns removed, the following hooks added in the recent implementation become unnecessary and should be removed:

- `update_option_fanfic_base_slug` → `on_url_slug_changed()` *(no longer needed)*
- `update_option_fanfic_members_slug` → `on_url_slug_changed()` *(no longer needed)*
- The `on_url_slug_changed()` method itself *(remove)*

The `on_author_profile_update()` extension for `user_login` change detection **should be kept** — it ensures `author_login` stays current if a login is changed.

These hooks **should be kept** (they protect other index data):

- `updated_post_meta` / `deleted_post_meta` for `_thumbnail_id` → reindexes `featured_image_id`
- `edited_fanfiction_genre` / `edited_fanfiction_status` → reindexes term names
- `fanfic_fandom_updated` / `fanfic_warning_updated` → reindexes display names

---

## Section 6: Database Schema Delta

**Remove these columns:**
```sql
ALTER TABLE wp_fanfic_story_search_index
  DROP COLUMN author_profile_url,
  DROP COLUMN coauthor_profile_urls,
  DROP COLUMN featured_image_url,
  DROP COLUMN featured_image_alt;
```

**Add these columns:**
```sql
ALTER TABLE wp_fanfic_story_search_index
  ADD COLUMN author_login varchar(60) DEFAULT '' AFTER author_display_name,
  ADD COLUMN coauthor_logins varchar(1000) DEFAULT '' AFTER coauthor_names,
  ADD COLUMN featured_image_id bigint(20) UNSIGNED DEFAULT 0 AFTER coauthor_logins;
```

In `class-fanfic-database-setup.php`: bump `DB_VERSION` to `2.0.0`, update `CREATE TABLE`, add migration.

---

## Section 7: Render-Time Changes

In `fanfic_get_story_card_html()` (`includes/functions.php`):

**Author profile URL** — replace index lookup with:
```php
$author_login       = trim( (string) $card_index_data['author_login'] );
$author_profile_url = '' !== $author_login
    ? fanfic_get_page_url( 'members' ) . $author_login . '/'
    : fanfic_get_user_profile_url( $author_id ); // fallback
```

**Coauthor profile URLs** — replace index lookup with:
```php
$coauthor_login_parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $card_index_data['coauthor_logins'] ) ) ) );
// In loop:
$coauthor_url = '' !== ( $coauthor_login_parts[$i] ?? '' )
    ? fanfic_get_page_url( 'members' ) . $coauthor_login_parts[$i] . '/'
    : fanfic_get_user_profile_url( $coauthor_ids[$i] );
```

**Featured image** — replace stored URL with ID-based lookup:
```php
$featured_image_id  = absint( $card_index_data['featured_image_id'] );
$featured_image_url = $featured_image_id ? (string) wp_get_attachment_image_url( $featured_image_id, 'medium' ) : '';
$featured_image_alt = $featured_image_id ? (string) get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true ) : '';
// fallback: if no indexed ID, try get_post_thumbnail_id()
if ( '' === $featured_image_url ) {
    $thumb_id = get_post_thumbnail_id( $story_id );
    if ( $thumb_id ) {
        $featured_image_url = (string) wp_get_attachment_image_url( $thumb_id, 'medium' );
        $featured_image_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
    }
}
```

---

## Section 8: Index Population Changes

In `class-fanfic-search-index.php`, `update_index()`:

**Remove helpers:**
- `get_author_profile_url()`
- `get_coauthor_profile_urls()`
- `get_featured_image_url()`
- `get_featured_image_alt()`

**Add helpers:**
```php
private static function get_author_login( $story_id ) {
    $author_id = self::get_author_id( $story_id );
    if ( ! $author_id ) return '';
    $user = get_userdata( $author_id );
    return $user ? (string) $user->user_login : '';
}

private static function get_coauthor_logins( $story_id ) {
    if ( ! class_exists( 'Fanfic_Coauthors' ) || ! Fanfic_Coauthors::is_enabled() ) return '';
    $coauthors = Fanfic_Coauthors::get_story_coauthors( $story_id );
    if ( empty( $coauthors ) ) return '';
    $logins = array();
    foreach ( $coauthors as $coauthor ) {
        if ( isset( $coauthor->user_login ) && '' !== $coauthor->user_login ) {
            $logins[] = $coauthor->user_login;
        }
    }
    return implode( ',', $logins );
}

private static function get_featured_image_id( $story_id ) {
    $thumb_id = get_post_thumbnail_id( $story_id );
    return $thumb_id ? absint( $thumb_id ) : 0;
}
```

**Update `$data` array** — replace old keys with new ones:
```php
'author_login'       => self::get_author_login( $story_id ),
'coauthor_logins'    => self::get_coauthor_logins( $story_id ),
'featured_image_id'  => self::get_featured_image_id( $story_id ),
// Remove: author_profile_url, coauthor_profile_urls, featured_image_url, featured_image_alt
```

---