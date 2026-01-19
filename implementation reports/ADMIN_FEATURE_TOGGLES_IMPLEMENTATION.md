# Admin Feature Toggles Implementation Guide

This guide explains how to make all user interaction features (Bookmark, Follow, Rating, Like, Subscribe) toggleable by WordPress admin via the General Settings tab.

## Architecture

- **Settings Storage**: `fanfic_settings` WordPress option (array-based)
- **Getter**: `Fanfic_Settings::get_setting($key, $default)`
- **All Features ON by default** - Admin can turn OFF individually
- **Buttons not rendered** if feature is disabled (via shortcode checks)

---

## Step 1: Add Missing Settings to Default Settings

**File**: `includes/class-fanfic-settings.php`

**Location**: Line 145-162 (the `get_default_settings()` method)

**Current Code**:
```php
private static function get_default_settings() {
    return array(
        'featured_mode'                  => 'manual',
        'featured_rating_min'            => 4,
        'featured_votes_min'             => 10,
        'featured_comments_min'          => 5,
        'featured_max_count'             => 6,
        'maintenance_mode'               => false,
        'cron_hour'                      => 3,
        'recaptcha_require_logged_in'    => false,
        'enable_likes'                   => true,
        'enable_subscribe'               => true,
        'enable_share'                   => true,
        'enable_report'                  => true,
        'allow_anonymous_likes'          => false,
        'allow_anonymous_reports'        => false,
    );
}
```

**Add These Lines** (after 'enable_report'):
```php
'enable_rating'                  => true,      // NEW: Chapter ratings (1-5 stars)
'enable_bookmarks'               => true,      // NEW: Story/chapter bookmarks
'enable_follows'                 => true,      // NEW: Story/author follows
'enable_reading_progress'        => true,      // NEW: Mark as read tracking
```

**Result** - Line 161 becomes:
```php
        'allow_anonymous_reports'        => false,
        'enable_rating'                  => true,
        'enable_bookmarks'               => true,
        'enable_follows'                 => true,
        'enable_reading_progress'        => true,
    );
}
```

### Sanitization

Also add sanitization for these new options in the `sanitize_settings()` method (around line 213-248):

**Add Before Line 248** (before `return $sanitized;`):
```php
// New feature toggles (checkboxes - true/false)
$sanitized['enable_rating'] = isset( $settings['enable_rating'] ) && $settings['enable_rating'];
$sanitized['enable_bookmarks'] = isset( $settings['enable_bookmarks'] ) && $settings['enable_bookmarks'];
$sanitized['enable_follows'] = isset( $settings['enable_follows'] ) && $settings['enable_follows'];
$sanitized['enable_reading_progress'] = isset( $settings['enable_reading_progress'] ) && $settings['enable_reading_progress'];
```

---

## Step 2: Update content-actions Shortcode

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`

**Location**: Line 135-145 (start of `content_actions()` method)

**Current Code**:
```php
public static function content_actions( $atts = array() ) {
    global $post;

    // Get admin settings
    $settings = get_option( 'fanfic_settings', array() );
    $enable_likes = isset( $settings['enable_likes'] ) ? $settings['enable_likes'] : true;
    $enable_subscribe = isset( $settings['enable_subscribe'] ) ? $settings['enable_subscribe'] : true;
    $enable_share = isset( $settings['enable_share'] ) ? $settings['enable_share'] : true;
    $enable_report = isset( $settings['enable_report'] ) ? $settings['enable_report'] : true;
    $allow_anonymous_likes = isset( $settings['allow_anonymous_likes'] ) ? $settings['allow_anonymous_likes'] : false;
    $allow_anonymous_reports = isset( $settings['allow_anonymous_reports'] ) ? $settings['allow_anonymous_reports'] : false;
```

**Add These Lines** (after line 145):
```php
    // New feature toggles
    $enable_rating = isset( $settings['enable_rating'] ) ? $settings['enable_rating'] : true;
    $enable_bookmarks = isset( $settings['enable_bookmarks'] ) ? $settings['enable_bookmarks'] : true;
    $enable_follows = isset( $settings['enable_follows'] ) ? $settings['enable_follows'] : true;
    $enable_reading_progress = isset( $settings['enable_reading_progress'] ) ? $settings['enable_reading_progress'] : true;
```

### Conditionally Render Buttons

**1. Bookmark Button** (Line 318)
- **Before**: Rendered unconditionally
- **Fix**: Wrap with `if ( $enable_bookmarks ) {` ... `}`

**2. Follow Button (Author Context)** (Line 241)
- **Before**: Rendered unconditionally
- **Fix**: Wrap with `if ( $enable_follows ) {` ... `}`

**3. Mark as Read Button** (Line 396)
- **Before**: Rendered unconditionally
- **Fix**: Wrap with `if ( $enable_reading_progress ) {` ... `}`

**Example for Bookmark Button** (Line 312-334):

**Current Code**:
```php
    // Bookmark button (bookmarks the story)
    $bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
    $bookmark_text = $is_bookmarked
        ? esc_html__( 'Bookmarked', 'fanfiction-manager' )
        : esc_html__( 'Bookmark', 'fanfiction-manager' );
    $bookmark_disabled = ! $is_logged_in ? 'disabled' : '';

    $output .= sprintf(
        // ... button HTML
    );
```

**Updated Code**:
```php
    // Bookmark button (bookmarks the story) - only if enabled
    if ( $enable_bookmarks ) {
        $bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
        $bookmark_text = $is_bookmarked
            ? esc_html__( 'Bookmarked', 'fanfiction-manager' )
            : esc_html__( 'Bookmark', 'fanfiction-manager' );
        $bookmark_disabled = ! $is_logged_in ? 'disabled' : '';

        $output .= sprintf(
            // ... button HTML (same as before)
        );
    }
```

**Do the same for**:
- Line 241 (Follow button) - wrap with `if ( $enable_follows ) {`
- Line 396 (Mark as Read button) - wrap with `if ( $enable_reading_progress ) {`
- Line 369 (Read List button) - wrap with `if ( $enable_reading_progress ) {` (optional, or combine with Mark as Read)

---

## Step 3: Update chapter-rating-form Shortcode

**File**: `includes/shortcodes/class-fanfic-shortcodes-forms.php`

**Location**: Line 467 (start of `chapter_rating_form()` method)

**Current Code**:
```php
public static function chapter_rating_form( $atts ) {
    $chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

    if ( ! $chapter_id ) {
        return '';
    }

    // Get chapter rating data from new rating system
    $rating_data = Fanfic_Rating_System::get_chapter_rating( $chapter_id );
```

**Add Check** (after `if ( ! $chapter_id ) {`):
```php
    // Check if ratings are enabled
    $settings = get_option( 'fanfic_settings', array() );
    $enable_rating = isset( $settings['enable_rating'] ) ? $settings['enable_rating'] : true;

    if ( ! $enable_rating ) {
        return ''; // Ratings disabled - don't render widget
    }
```

---

## Step 4: Update AJAX Handlers

All AJAX handlers in `includes/shortcodes/class-fanfic-shortcodes-actions.php` should check if the feature is enabled before processing.

### Pattern for Each Handler

**Example: `ajax_bookmark_story()`**

**Current Code** (somewhere in the file):
```php
public static function ajax_bookmark_story() {
    // Verify nonce
    check_ajax_referer( 'fanfic_actions_nonce' );

    // Get story ID
    $story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

    // Add bookmark logic...
}
```

**Updated Code**:
```php
public static function ajax_bookmark_story() {
    // Verify nonce
    check_ajax_referer( 'fanfic_actions_nonce' );

    // Check if bookmarks are enabled
    $settings = get_option( 'fanfic_settings', array() );
    $enable_bookmarks = isset( $settings['enable_bookmarks'] ) ? $settings['enable_bookmarks'] : true;

    if ( ! $enable_bookmarks ) {
        wp_send_json_error( array(
            'message' => __( 'Bookmarks are disabled', 'fanfiction-manager' ),
        ) );
    }

    // Get story ID
    $story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

    // Add bookmark logic...
}
```

### Handlers to Update

- `ajax_bookmark_story()` - add `$enable_bookmarks` check
- `ajax_unbookmark_story()` - add `$enable_bookmarks` check
- `ajax_follow_author()` - add `$enable_follows` check
- `ajax_unfollow_author()` - add `$enable_follows` check
- `ajax_mark_as_read()` - add `$enable_reading_progress` check
- `ajax_unmark_as_read()` - add `$enable_reading_progress` check
- Rating AJAX handler - add `$enable_rating` check (if exists)
- Like AJAX handler - add `$enable_likes` check (likely already done)

---

## Step 5: Update JavaScript Handlers

**File**: `assets/js/fanfiction-actions.js` (or the interaction JS file)

The JavaScript should:
1. Only attach click handlers to enabled buttons (buttons that exist in the DOM)
2. Pass feature status from PHP via `wp_localize_script()`

**Add to wp_localize_script() call**:

If your enqueue script looks like:
```php
wp_localize_script(
    'fanfic-actions',
    'fanficActions',
    array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'fanfic_actions_nonce' ),
        'strings'   => array( ... ),
    )
);
```

**Update to include enabled features**:
```php
wp_localize_script(
    'fanfic-actions',
    'fanficActions',
    array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'fanfic_actions_nonce' ),
        'strings'   => array( ... ),
        // NEW: Feature toggles
        'features' => array(
            'rating'            => Fanfic_Settings::get_setting( 'enable_rating', true ),
            'likes'             => Fanfic_Settings::get_setting( 'enable_likes', true ),
            'bookmarks'         => Fanfic_Settings::get_setting( 'enable_bookmarks', true ),
            'follows'           => Fanfic_Settings::get_setting( 'enable_follows', true ),
            'reading_progress'  => Fanfic_Settings::get_setting( 'enable_reading_progress', true ),
            'subscribe'         => Fanfic_Settings::get_setting( 'enable_subscribe', true ),
        ),
    )
);
```

**In JavaScript**, handlers already check if buttons exist before attaching:
```javascript
$(document).on('click', '.fanfic-bookmark-button', function() {
    // This only fires if the button exists
    // So no extra check needed
});
```

However, you could optionally show a message if a feature is disabled:
```javascript
// If bookmarks button doesn't exist but feature is disabled, user sees nothing
// If button doesn't exist for other reasons, user also sees nothing (which is fine)
```

---

## Step 6: Update Admin Settings UI

**File**: The plugin's admin settings page template (likely in `admin/` directory or within `class-fanfic-settings.php`)

**Add Checkboxes** in the General Settings tab:

```html
<tr>
    <th scope="row">
        <label for="enable_rating"><?php esc_html_e( 'Enable Ratings', 'fanfiction-manager' ); ?></label>
    </th>
    <td>
        <input type="checkbox"
               id="enable_rating"
               name="fanfic_settings[enable_rating]"
               value="1"
               <?php checked( Fanfic_Settings::get_setting( 'enable_rating', true ), true ); ?> />
        <p class="description"><?php esc_html_e( 'Allow users to rate chapters (1-5 stars)', 'fanfiction-manager' ); ?></p>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="enable_bookmarks"><?php esc_html_e( 'Enable Bookmarks', 'fanfiction-manager' ); ?></label>
    </th>
    <td>
        <input type="checkbox"
               id="enable_bookmarks"
               name="fanfic_settings[enable_bookmarks]"
               value="1"
               <?php checked( Fanfic_Settings::get_setting( 'enable_bookmarks', true ), true ); ?> />
        <p class="description"><?php esc_html_e( 'Allow users to bookmark stories and chapters', 'fanfiction-manager' ); ?></p>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="enable_follows"><?php esc_html_e( 'Enable Follows', 'fanfiction-manager' ); ?></label>
    </th>
    <td>
        <input type="checkbox"
               id="enable_follows"
               name="fanfic_settings[enable_follows]"
               value="1"
               <?php checked( Fanfic_Settings::get_setting( 'enable_follows', true ), true ); ?> />
        <p class="description"><?php esc_html_e( 'Allow users to follow stories and authors', 'fanfiction-manager' ); ?></p>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="enable_reading_progress"><?php esc_html_e( 'Enable Reading Progress', 'fanfiction-manager' ); ?></label>
    </th>
    <td>
        <input type="checkbox"
               id="enable_reading_progress"
               name="fanfic_settings[enable_reading_progress]"
               value="1"
               <?php checked( Fanfic_Settings::get_setting( 'enable_reading_progress', true ), true ); ?> />
        <p class="description"><?php esc_html_e( 'Allow users to mark chapters as read and track reading progress', 'fanfiction-manager' ); ?></p>
    </td>
</tr>
```

---

## Testing Checklist

After making these changes:

- [ ] All 4 new settings appear in admin settings page
- [ ] Toggling each setting ON/OFF works
- [ ] When disabled, buttons DON'T render in the shortcode
- [ ] When enabled, buttons render as normal
- [ ] AJAX requests from disabled features are rejected
- [ ] JavaScript doesn't error when buttons don't exist
- [ ] Settings persist after page reload
- [ ] Default value is TRUE for all features (backward compatible)

---

## Summary of Changes

| Feature | Default | Where Checked | Location |
|---------|---------|---------------|----------|
| **Rating** | ON | `chapter_rating_form()` shortcode | `class-fanfic-shortcodes-forms.php:467` |
| **Bookmarks** | ON | `content_actions()` shortcode | `class-fanfic-shortcodes-actions.php:318` |
| **Follows** | ON | `content_actions()` shortcode (author context) | `class-fanfic-shortcodes-actions.php:241` |
| **Reading Progress** | ON | `content_actions()` shortcode | `class-fanfic-shortcodes-actions.php:396,369` |
| **Likes** | ON | Already implemented ✓ | `class-fanfic-shortcodes-actions.php:337` |
| **Subscribe** | ON | Already implemented ✓ | `class-fanfic-shortcodes-actions.php:429` |

---

## Code Examples

### Quick Copy-Paste: Settings Default

```php
'enable_rating'                  => true,
'enable_bookmarks'               => true,
'enable_follows'                 => true,
'enable_reading_progress'        => true,
```

### Quick Copy-Paste: Settings Retrieval

```php
$settings = get_option( 'fanfic_settings', array() );
$enable_rating = isset( $settings['enable_rating'] ) ? $settings['enable_rating'] : true;
$enable_bookmarks = isset( $settings['enable_bookmarks'] ) ? $settings['enable_bookmarks'] : true;
$enable_follows = isset( $settings['enable_follows'] ) ? $settings['enable_follows'] : true;
$enable_reading_progress = isset( $settings['enable_reading_progress'] ) ? $settings['enable_reading_progress'] : true;
```

### Quick Copy-Paste: Sanitization

```php
$sanitized['enable_rating'] = isset( $settings['enable_rating'] ) && $settings['enable_rating'];
$sanitized['enable_bookmarks'] = isset( $settings['enable_bookmarks'] ) && $settings['enable_bookmarks'];
$sanitized['enable_follows'] = isset( $settings['enable_follows'] ) && $settings['enable_follows'];
$sanitized['enable_reading_progress'] = isset( $settings['enable_reading_progress'] ) && $settings['enable_reading_progress'];
```

---

## Notes

- **Backward Compatibility**: All features default to `true`, so existing installations won't break
- **Database**: Settings stored in `fanfic_settings` WordPress option (already exists)
- **Admin Control**: Complete control via General Settings tab
- **No Data Deletion**: Disabling a feature just hides the button; data remains in the database
- **Re-enabling**: If disabled then re-enabled, data comes back immediately

---

Generated: 2025-11-13
Status: Ready for Implementation
