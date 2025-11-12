# Security & Performance Implementation Guide

**Version:** 1.0.15
**Phase:** 4 - Security Hardening & Performance Monitoring
**Status:** Complete

## Overview

This document describes the security hardening and performance monitoring systems implemented in Phase 4 of the Fanfiction Manager plugin. These systems provide comprehensive protection against abuse, spam, and performance issues while maintaining usability.

---

## Architecture

### 1. Performance Monitoring (`class-fanfic-performance-monitor.php`)

**Purpose:** Track and log performance metrics, identify slow queries, and monitor system health.

**Key Features:**
- Slow query logging with configurable threshold
- Performance statistics dashboard
- Memory usage tracking
- Database table size monitoring
- Page load metrics

**Configuration Options:**
- `fanfic_performance_settings` option:
  - `enabled` (bool): Enable/disable monitoring
  - `threshold` (float): Slow query threshold in seconds (default: 0.5)

**Public Methods:**

```php
// Initialize monitoring
Fanfic_Performance_Monitor::init();

// Check if monitoring is enabled
$enabled = Fanfic_Performance_Monitor::is_monitoring_enabled();

// Start timing an operation
$timer_id = Fanfic_Performance_Monitor::start_timer( 'Database Query' );

// End timing and log if slow
$elapsed = Fanfic_Performance_Monitor::end_timer( $timer_id );

// Manually log slow query
Fanfic_Performance_Monitor::log_slow_query( $query, $time, $result_count );

// Get slow queries (last 50 by default)
$slow_queries = Fanfic_Performance_Monitor::get_slow_queries( 50 );

// Get performance statistics
$stats = Fanfic_Performance_Monitor::get_performance_stats();

// Get database table sizes
$table_sizes = Fanfic_Performance_Monitor::get_table_sizes();

// Get memory statistics
$memory_stats = Fanfic_Performance_Monitor::get_memory_stats();

// Clear slow query log
Fanfic_Performance_Monitor::clear_slow_queries();
```

**Storage:**
- `fanfic_slow_queries` - Last 100 slow queries
- `fanfic_memory_log` - Last 50 memory usage snapshots
- `fanfic_page_metrics` - Last 20 page load metrics

---

### 2. Rate Limiting (`class-fanfic-rate-limit.php`)

**Purpose:** Prevent spam and abuse via configurable rate limits on user actions.

**Default Rate Limits:**

| Action     | Limit | Window  | Purpose                    |
|------------|-------|---------|----------------------------|
| rate       | 10    | 60s     | Chapter ratings            |
| like       | 10    | 60s     | Chapter likes              |
| bookmark   | 5     | 60s     | Story bookmarks            |
| follow     | 3     | 60s     | Author/story follows       |
| subscribe  | 2     | 60s     | Email subscriptions        |
| comment    | 5     | 300s    | Comments (5 min window)    |
| view       | 100   | 60s     | View tracking              |
| search     | 30    | 60s     | Search queries             |
| ajax       | 60    | 60s     | Generic AJAX calls         |

**Public Methods:**

```php
// Check if rate limited
$is_limited = Fanfic_Rate_Limit::is_rate_limited( $identifier, 'rate', 10, 60 );

// Increment counter
$count = Fanfic_Rate_Limit::increment_counter( $identifier, 'rate', 60 );

// Get remaining requests
$remaining = Fanfic_Rate_Limit::get_remaining_requests( $identifier, 'rate' );

// Get wait time until reset
$wait_seconds = Fanfic_Rate_Limit::get_wait_time( $identifier, 'rate' );

// Reset limit manually
Fanfic_Rate_Limit::reset_limit( $identifier, 'rate' );

// Check for suspicious activity
$is_suspicious = Fanfic_Rate_Limit::is_suspicious_activity( $identifier, 'rate' );

// Get all rate limits
$limits = Fanfic_Rate_Limit::get_all_limits();

// Update rate limits
Fanfic_Rate_Limit::update_limits( $custom_limits );

// Get suspicious activity log
$log = Fanfic_Rate_Limit::get_suspicious_activity_log( 50 );
```

**Suspicious Activity Detection:**
- Rapid actions: >20 actions in 10 seconds
- Unique actions: >5 different action types in 10 seconds
- Failed attempts: >10 rate-limited attempts in 5 minutes

**Storage:**
- Transients: `fanfic_rl_{md5(identifier_action)}`
- `fanfic_suspicious_activity` - Last 100 suspicious activity logs

---

### 3. Security Checks (`class-fanfic-security.php`)

**Purpose:** Centralized security validation, capability checks, and security event logging.

**Key Features:**
- AJAX request verification
- User ban/suspension checking
- Capability verification
- Security event logging
- Post ownership verification

**Public Methods:**

```php
// Verify AJAX request (nonce + capabilities)
$result = Fanfic_Security::verify_ajax_request( 'fanfic_rate_chapter', 'nonce' );

// Verify user capabilities
$result = Fanfic_Security::verify_capabilities( $user_id, 'rate' );

// Check if user is banned
$is_banned = Fanfic_Security::is_user_banned( $user_id );

// Check if user is suspended
$is_suspended = Fanfic_Security::is_user_suspended( $user_id );

// Sanitize AJAX POST data
$clean_data = Fanfic_Security::sanitize_ajax_post_data( $_POST );

// Log security event
Fanfic_Security::log_security_event( 'rate_limit_exceeded', array(
    'user_id' => $user_id,
    'action'  => 'rate',
) );

// Get security logs
$logs = Fanfic_Security::get_security_logs( 50, 'invalid_nonce' );

// Check post ownership
$result = Fanfic_Security::check_post_ownership( $user_id, $post_id );

// Require login
Fanfic_Security::require_login( true );

// Get security statistics
$stats = Fanfic_Security::get_security_stats();
```

**Security Events:**
- `invalid_nonce` - Nonce verification failed
- `invalid_ajax_context` - Non-AJAX request to AJAX handler
- `unauthorized_access` - Non-logged-in user accessing protected action
- `banned_user_attempt` - Banned user attempted action
- `suspended_user_attempt` - Suspended user attempted action
- `insufficient_capabilities` - User lacks required capability
- `ownership_check_failed` - User tried to modify content they don't own

**Storage:**
- `fanfic_security_log` - Last 100 security events
- User meta: `fanfic_banned`, `fanfic_ban_expires`, `fanfic_suspended`, `fanfic_suspension_expires`

---

### 4. Cache Manager (`class-fanfic-cache-manager.php`)

**Purpose:** Enhanced caching with incremental updates (no full rebuilds).

**Key Features:**
- Dual-layer caching (object cache + transients)
- Incremental cache updates for ratings/likes
- Automatic cache invalidation
- Cache statistics

**Public Methods:**

```php
// Set transient with dual-layer support
Fanfic_Cache_Manager::set_transient( $key, $value, HOUR_IN_SECONDS );

// Get transient
$value = Fanfic_Cache_Manager::get_transient( $key );

// Delete transient
Fanfic_Cache_Manager::delete_transient( $key );

// Invalidate post cache
$count = Fanfic_Cache_Manager::invalidate_post_cache_by_id( $post_id );

// Invalidate user cache
$count = Fanfic_Cache_Manager::invalidate_user_cache( $user_id );

// Update rating cache incrementally (no DB rebuild)
$stats = Fanfic_Cache_Manager::update_rating_cache_incrementally(
    $chapter_id,
    $new_rating,
    $old_rating,
    $is_new
);

// Update like cache incrementally
$count = Fanfic_Cache_Manager::update_like_cache_incrementally( $chapter_id, +1 );

// Get cache statistics
$stats = Fanfic_Cache_Manager::get_cache_stats();

// Clear all caches
$count = Fanfic_Cache_Manager::clear_all_caches();

// Warm up chapter cache
Fanfic_Cache_Manager::warm_chapter_cache( $chapter_id );
```

**Cache Keys:**
- `fanfic_chapter_{id}_stats` - Rating/like stats (24h TTL)
- `fanfic_story_{id}_stats` - Story stats (24h TTL)
- `fanfic_user_{id}_notifications` - User notifications (1h TTL)
- `fanfic_user_{id}_follows` - User follows (6h TTL)
- `fanfic_user_{id}_bookmarks` - User bookmarks (6h TTL)
- `fanfic_author_{id}_followers` - Author followers (12h TTL)

**Automatic Invalidation Hooks:**
- `transition_post_status` - Invalidates post/story caches
- `delete_user` - Invalidates user caches
- `fanfic_rating_submitted` - Updates rating cache incrementally
- `fanfic_like_submitted` - Updates like cache incrementally

---

### 5. AJAX Security Wrapper (`class-fanfic-ajax-security.php`)

**Purpose:** Standardized security wrapper for all AJAX endpoints.

**Key Features:**
- Automatic nonce verification
- Capability checking
- Rate limiting
- Request logging
- Standardized response formatting

**Usage Example:**

```php
// Register AJAX handler with security wrapper
Fanfic_AJAX_Security::register_ajax_handler(
    'fanfic_rate_chapter',
    'my_rating_callback',
    true, // require login
    array(
        'rate_limit'    => true,
        'log_requests'  => true,
        'check_referer' => true,
        'capability'    => 'read',
    )
);

// In callback, get validated parameters
$params = Fanfic_AJAX_Security::get_ajax_parameters(
    array( 'chapter_id', 'rating' ), // required
    array( 'comment' )                // optional
);

if ( is_wp_error( $params ) ) {
    Fanfic_AJAX_Security::send_error_response(
        'invalid_params',
        $params->get_error_message()
    );
}

// Process request...

// Send success response
Fanfic_AJAX_Security::send_success_response(
    array( 'rating_average' => 4.5 ),
    'Rating submitted successfully'
);
```

**Public Methods:**

```php
// Register handler
Fanfic_AJAX_Security::register_ajax_handler( $action, $callback, $require_login, $options );

// Execute with security wrapper
Fanfic_AJAX_Security::execute_ajax_action( $action );

// Send responses
Fanfic_AJAX_Security::send_success_response( $data, $message );
Fanfic_AJAX_Security::send_error_response( $error_code, $message, $http_code );

// Get validated parameters
$params = Fanfic_AJAX_Security::get_ajax_parameters( $required_keys, $optional_keys );

// Get request log
$log = Fanfic_AJAX_Security::get_request_log( 50 );

// Get registered handlers
$handlers = Fanfic_AJAX_Security::get_registered_handlers();
```

---

## Integration Guide

### Example: Securing an AJAX Endpoint

**Old approach (manual security):**

```php
add_action( 'wp_ajax_fanfic_rate_chapter', 'my_rating_handler' );

function my_rating_handler() {
    // Manual nonce check
    check_ajax_referer( 'fanfic_rate_chapter', 'nonce' );

    // Manual login check
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Login required' );
    }

    // Manual rate limiting
    // ... custom code ...

    // Manual capability check
    // ... custom code ...

    // Process request
    // ...
}
```

**New approach (with security wrapper):**

```php
Fanfic_AJAX_Security::register_ajax_handler(
    'fanfic_rate_chapter',
    'my_rating_handler',
    true,
    array( 'capability' => 'read' )
);

function my_rating_handler() {
    // Security checks already done automatically

    // Get validated parameters
    $params = Fanfic_AJAX_Security::get_ajax_parameters(
        array( 'chapter_id', 'rating' )
    );

    if ( is_wp_error( $params ) ) {
        Fanfic_AJAX_Security::send_error_response(
            'invalid_params',
            $params->get_error_message()
        );
    }

    // Process request
    // ...

    Fanfic_AJAX_Security::send_success_response( $result );
}
```

---

## Performance Best Practices

### 1. Use Incremental Cache Updates

**Bad:**
```php
// Full cache rebuild on every rating
function update_rating( $chapter_id, $rating ) {
    global $wpdb;

    // Invalidate cache
    delete_transient( 'chapter_' . $chapter_id . '_stats' );

    // Next request will rebuild from DB
}
```

**Good:**
```php
// Incremental update (no DB query)
function update_rating( $chapter_id, $new_rating, $old_rating, $is_new ) {
    Fanfic_Cache_Manager::update_rating_cache_incrementally(
        $chapter_id,
        $new_rating,
        $old_rating,
        $is_new
    );
}
```

### 2. Monitor Slow Queries

```php
// Start timer
$timer = Fanfic_Performance_Monitor::start_timer( 'Complex story query' );

// Run query
$stories = $wpdb->get_results( $complex_query );

// End timer (auto-logs if slow)
Fanfic_Performance_Monitor::end_timer( $timer );
```

### 3. Use Rate Limiting

```php
// Check rate limit before expensive operation
$user_id = get_current_user_id();

if ( Fanfic_Rate_Limit::is_rate_limited( $user_id, 'search' ) ) {
    $wait_time = Fanfic_Rate_Limit::get_wait_time( $user_id, 'search' );

    return new WP_Error(
        'rate_limited',
        sprintf( 'Please wait %d seconds', $wait_time )
    );
}

// Increment counter
Fanfic_Rate_Limit::increment_counter( $user_id, 'search' );

// Proceed with operation
```

---

## Monitoring & Debugging

### Performance Dashboard (Admin)

Access performance metrics:

```php
// Get slow queries
$slow_queries = Fanfic_Performance_Monitor::get_slow_queries( 20 );

// Get performance stats
$stats = Fanfic_Performance_Monitor::get_performance_stats();
/*
Array (
    [total_slow_queries] => 15
    [monitoring_enabled] => true
    [slow_query_threshold] => 0.5
    [total_stories] => 1234
    [total_chapters] => 5678
    [total_ratings] => 9012
    [total_likes] => 3456
    [active_users_30d] => 789
)
*/

// Get table sizes
$table_sizes = Fanfic_Performance_Monitor::get_table_sizes();
/*
Array (
    [fanfic_ratings] => Array (
        [size_bytes] => 1048576
        [size_human] => 1 MB
        [rows] => 5000
    )
)
*/
```

### Security Dashboard (Admin)

Monitor security events:

```php
// Get security statistics
$stats = Fanfic_Security::get_security_stats();
/*
Array (
    [total_events] => 50
    [events_by_type] => Array (
        [invalid_nonce] => 12
        [rate_limit_exceeded] => 8
        [banned_user_attempt] => 3
    )
    [recent_events] => Array ( ... )
    [banned_users] => 5
    [suspended_users] => 2
)
*/

// Get suspicious activity
$suspicious = Fanfic_Rate_Limit::get_suspicious_activity_log( 20 );
```

---

## Configuration

### Admin Settings (Future Enhancement)

Recommended settings page structure:

**Performance Monitoring:**
- Enable/disable monitoring
- Slow query threshold (seconds)
- Clear slow query log

**Rate Limiting:**
- Per-action limits configuration
- Global rate limit multiplier
- Whitelist IPs

**Security:**
- Auto-ban thresholds
- Security log retention (days)
- Email alerts for security events

---

## Hooks & Filters

### Performance Monitoring

```php
// After slow query is logged
do_action( 'fanfic_slow_query_logged', $query, $time, $result_count );
```

### Rate Limiting

```php
// When suspicious activity is detected
do_action( 'fanfic_suspicious_activity_detected', $identifier, $type, $count );
```

### Security

```php
// When security event is logged
do_action( 'fanfic_security_event_logged', $event_type, $details, $entry );
```

### Cache Manager

```php
// When rating cache is updated
do_action( 'fanfic_rating_cache_updated', $chapter_id, $stats );

// When cache is cleared
do_action( 'fanfic_cache_cleared', $count );
```

---

## Testing Checklist

- [ ] Rate limiting prevents spam (10+ rapid requests)
- [ ] Banned users cannot access AJAX endpoints
- [ ] Suspended users see suspension messages
- [ ] Slow queries are logged (>0.5s threshold)
- [ ] Cache updates incrementally (no full rebuild)
- [ ] Security events are logged correctly
- [ ] Performance stats display accurate data
- [ ] AJAX responses are standardized
- [ ] Nonce verification works on all endpoints
- [ ] Memory usage is tracked

---

## Production Deployment

### Performance Tuning

1. **Disable monitoring after optimization:**
   ```php
   Fanfic_Performance_Monitor::update_settings( array(
       'enabled' => false, // Disable in production
   ) );
   ```

2. **Adjust rate limits for traffic:**
   ```php
   Fanfic_Rate_Limit::update_limits( array(
       'rate' => array( 'limit' => 20, 'window' => 60 ), // More lenient
   ) );
   ```

3. **Enable object caching (Redis/Memcached)** for best performance

### Security Hardening

1. **Review security logs regularly**
2. **Set up automated alerts for suspicious activity**
3. **Whitelist trusted IPs from rate limiting**
4. **Monitor ban/suspension statistics**

---

## File Locations

```
includes/
├── class-fanfic-performance-monitor.php   (595 lines)
├── class-fanfic-rate-limit.php            (444 lines)
├── class-fanfic-security.php              (508 lines)
├── class-fanfic-cache-manager.php         (432 lines)
└── class-fanfic-ajax-security.php         (404 lines)
```

**Total:** 2,383 lines of security and performance code

---

## Support & Maintenance

**Version:** 1.0.15
**Last Updated:** 2025-11-13
**Maintainer:** Fanfiction Manager Development Team

For issues or questions, consult the main plugin documentation at `/docs/overview.md`.
