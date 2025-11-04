# Fanfic_Cache - Quick Reference Card

## One-Line Caching

```php
// Most common usage - cache with automatic regeneration
$data = Fanfic_Cache::get_or_set( 'type', 'subtype', $id, $callback, Fanfic_Cache::TTL );
```

---

## TTL Constants (Use These!)

```php
Fanfic_Cache::REALTIME  // 60s    - Live data
Fanfic_Cache::SHORT     // 5min   - Ratings, views
Fanfic_Cache::MEDIUM    // 15min  - Bookmarks, comments
Fanfic_Cache::LONG      // 30min  - Chapter lists
Fanfic_Cache::DAY       // 24hrs  - Author stats
Fanfic_Cache::WEEK      // 7days  - Validation
```

---

## Common Patterns

### Cache Story Data
```php
$chapters = Fanfic_Cache::get_or_set(
    'story',
    'chapters',
    $story_id,
    fn() => get_posts( [ 'post_type' => 'fanfiction_chapter', 'post_parent' => $story_id ] ),
    Fanfic_Cache::LONG
);
```

### Cache User Data
```php
$bookmarks = Fanfic_Cache::get_or_set(
    'user',
    'bookmarks',
    $user_id,
    fn() => $wpdb->get_results( "SELECT * FROM {$table} WHERE user_id = {$user_id}" ),
    Fanfic_Cache::MEDIUM
);
```

### Cache Lists
```php
$trending = Fanfic_Cache::get_or_set(
    'trending',
    'stories',
    0,
    fn() => get_posts( [ 'post_type' => 'fanfiction_story', 'meta_key' => 'views' ] ),
    Fanfic_Cache::LONG
);
```

---

## Invalidation

### When Data Changes
```php
// Story updated
Fanfic_Cache::invalidate_story( $story_id );

// Chapter updated
Fanfic_Cache::invalidate_chapter( $chapter_id );

// User data changed
Fanfic_Cache::invalidate_user( $user_id );

// New content published
Fanfic_Cache::invalidate_lists();

// Taxonomy changed
Fanfic_Cache::invalidate_taxonomies();
```

### Hooks
```php
add_action( 'save_post_fanfiction_story', fn($id) => Fanfic_Cache::invalidate_story($id) );
add_action( 'save_post_fanfiction_chapter', fn($id) => Fanfic_Cache::invalidate_chapter($id) );
```

---

## Admin Operations

### Statistics
```php
$stats = Fanfic_Cache::get_stats();
// Returns: [ 'total_transients', 'expired_transients', 'object_cache', 'cache_version' ]
```

### Clear All
```php
$count = Fanfic_Cache::clear_all();  // Nuclear option
```

### Cleanup Expired
```php
$count = Fanfic_Cache::cleanup_expired();  // Run daily via cron
```

---

## Manual Control

### Check Existence
```php
if ( Fanfic_Cache::exists( $key ) ) {
    // Cache is available
}
```

### Get TTL
```php
$seconds = Fanfic_Cache::get_ttl( $key );  // Returns false if expired
```

### Direct Set/Delete
```php
Fanfic_Cache::set( $key, $value, Fanfic_Cache::SHORT );
Fanfic_Cache::delete( $key );
```

---

## Real-World Examples

### Story Rating
```php
public static function get_story_rating( $story_id ) {
    return Fanfic_Cache::get_or_set(
        'story', 'rating', $story_id,
        fn() => calculate_average_rating( $story_id ),
        Fanfic_Cache::SHORT
    );
}
```

### Archive Page
```php
public static function get_archive_stories( $page, $genre ) {
    return Fanfic_Cache::get_or_set(
        'archive', "genre_{$genre}_p{$page}", 0,
        fn() => fetch_stories_from_db( $genre, $page ),
        Fanfic_Cache::LONG
    );
}
```

### User Bookmarks
```php
public static function get_bookmarks( $user_id ) {
    return Fanfic_Cache::get_or_set(
        'user', 'bookmarks', $user_id,
        fn() => fetch_bookmarks_from_db( $user_id ),
        Fanfic_Cache::MEDIUM
    );
}
```

---

## Best Practices

1. **Always use TTL constants** (not magic numbers)
2. **Invalidate aggressively** when data changes
3. **Use hooks** for automatic invalidation
4. **Cache expensive queries only** (not simple lookups)
5. **Use appropriate TTL** based on data volatility

---

## Troubleshooting

### Not Working?
```php
// Test it
$key = Fanfic_Cache::get_key( 'test', 'debug', 1 );
Fanfic_Cache::set( $key, 'works', Fanfic_Cache::SHORT );
var_dump( get_transient( $key ) );  // Should show 'works'
```

### Stale Data?
```php
// Force refresh
Fanfic_Cache::clear_all();
```

### Too Many Transients?
```php
// Check
$stats = Fanfic_Cache::get_stats();
echo $stats['total_transients'];

// Cleanup
Fanfic_Cache::cleanup_expired();
```

---

## Object Cache

Automatically uses Redis/Memcached when available:

```php
if ( Fanfic_Cache::is_object_cache_active() ) {
    // Using in-memory cache (fast!)
} else {
    // Using database (still good)
}
```

No configuration needed - WordPress handles it!

---

## Files

- **Main Class:** `includes/class-fanfic-cache.php`
- **Usage Guide:** `includes/CACHE_USAGE_EXAMPLES.md`
- **Full Docs:** `includes/CACHE_IMPLEMENTATION_SUMMARY.md`
- **This Card:** `includes/CACHE_QUICK_REFERENCE.md`

---

## Summary

```php
// 1. Cache data with automatic regeneration
$data = Fanfic_Cache::get_or_set( $type, $subtype, $id, $callback, $ttl );

// 2. Invalidate when data changes
Fanfic_Cache::invalidate_story( $story_id );

// 3. Clean up regularly
Fanfic_Cache::cleanup_expired();  // Via cron

// That's it!
```
