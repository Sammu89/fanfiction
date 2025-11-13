# Admin Feature Toggles - Implementation Checklist

Use this checklist to implement admin feature toggles step-by-step.

---

## ✅ Step 1: Update Default Settings

**File**: `includes/class-fanfic-settings.php`
**Method**: `get_default_settings()` (around line 145)

**Action**: Add 4 new lines to the return array (after `'allow_anonymous_reports'`):

```php
'enable_rating'                  => true,
'enable_bookmarks'               => true,
'enable_follows'                 => true,
'enable_reading_progress'        => true,
```

**Status**: [ ] Complete

---

## ✅ Step 2: Update Settings Sanitization

**File**: `includes/class-fanfic-settings.php`
**Method**: `sanitize_settings()` (around line 213)

**Action**: Add 4 new lines before `return $sanitized;` (around line 247):

```php
// New feature toggles (checkboxes - true/false)
$sanitized['enable_rating'] = isset( $settings['enable_rating'] ) && $settings['enable_rating'];
$sanitized['enable_bookmarks'] = isset( $settings['enable_bookmarks'] ) && $settings['enable_bookmarks'];
$sanitized['enable_follows'] = isset( $settings['enable_follows'] ) && $settings['enable_follows'];
$sanitized['enable_reading_progress'] = isset( $settings['enable_reading_progress'] ) && $settings['enable_reading_progress'];
```

**Status**: [ ] Complete

---

## ✅ Step 3: Add Settings to content_actions Shortcode

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`
**Method**: `content_actions()` (around line 135)

**Action**: Add 4 new lines after existing setting retrievals (after line 145):

```php
// New feature toggles
$enable_rating = isset( $settings['enable_rating'] ) ? $settings['enable_rating'] : true;
$enable_bookmarks = isset( $settings['enable_bookmarks'] ) ? $settings['enable_bookmarks'] : true;
$enable_follows = isset( $settings['enable_follows'] ) ? $settings['enable_follows'] : true;
$enable_reading_progress = isset( $settings['enable_reading_progress'] ) ? $settings['enable_reading_progress'] : true;
```

**Status**: [ ] Complete

---

## ✅ Step 4: Conditionally Render Bookmark Button

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`
**Line**: Around 312

**Current Code**:
```php
// Bookmark button (bookmarks the story)
$bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
// ... more code
$output .= sprintf( /* ... */ );
```

**Updated Code**:
```php
// Bookmark button (bookmarks the story) - only if enabled
if ( $enable_bookmarks ) {
    $bookmark_class = $is_bookmarked ? 'bookmarked' : 'not-bookmarked';
    // ... more code (SAME as before)
    $output .= sprintf( /* ... */ );
}
```

**Action**: Wrap the entire bookmark button code block with `if ( $enable_bookmarks ) {`  and closing `}`

**Status**: [ ] Complete

---

## ✅ Step 5: Conditionally Render Follow Button (Author Context)

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`
**Line**: Around 231

**Current Code**:
```php
if ( 'author' === $context ) {
    // Don't show follow button to the author themselves
    if ( ! $is_logged_in || $user_id !== $author_id ) {
        // ... Follow button HTML
    }
```

**Updated Code**:
```php
if ( 'author' === $context && $enable_follows ) {  // ADD THIS CONDITION
    // Don't show follow button to the author themselves
    if ( ! $is_logged_in || $user_id !== $author_id ) {
        // ... Follow button HTML (NO CHANGES HERE)
    }
```

**Action**: Change condition from `if ( 'author' === $context ) {` to `if ( 'author' === $context && $enable_follows ) {`

**Status**: [ ] Complete

---

## ✅ Step 6: Conditionally Render Read List Button

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`
**Line**: Around 369

**Current Code**:
```php
// Read List button (story view only)
if ( 'story' === $context ) {
    // ... button HTML
}
```

**Updated Code**:
```php
// Read List button (story view only)
if ( 'story' === $context && $enable_reading_progress ) {  // ADD THIS CONDITION
    // ... button HTML (NO CHANGES)
}
```

**Action**: Change condition to include `&& $enable_reading_progress`

**Status**: [ ] Complete

---

## ✅ Step 7: Conditionally Render Mark as Read Button

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`
**Line**: Around 396

**Current Code**:
```php
// Mark as Read button (chapter view only)
if ( 'chapter' === $context ) {
    // ... button HTML
}
```

**Updated Code**:
```php
// Mark as Read button (chapter view only)
if ( 'chapter' === $context && $enable_reading_progress ) {  // ADD THIS CONDITION
    // ... button HTML (NO CHANGES)
}
```

**Action**: Change condition to include `&& $enable_reading_progress`

**Status**: [ ] Complete

---

## ✅ Step 8: Add Check to Rating Shortcode

**File**: `includes/shortcodes/class-fanfic-shortcodes-forms.php`
**Method**: `chapter_rating_form()` (around line 467)

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

**Updated Code**:
```php
public static function chapter_rating_form( $atts ) {
    $chapter_id = Fanfic_Shortcodes::get_current_chapter_id();

    if ( ! $chapter_id ) {
        return '';
    }

    // NEW: Check if ratings are enabled
    $settings = get_option( 'fanfic_settings', array() );
    $enable_rating = isset( $settings['enable_rating'] ) ? $settings['enable_rating'] : true;

    if ( ! $enable_rating ) {
        return ''; // Ratings disabled - don't render widget
    }

    // Get chapter rating data from new rating system
    $rating_data = Fanfic_Rating_System::get_chapter_rating( $chapter_id );
```

**Status**: [ ] Complete

---

## ✅ Step 9: Add Checks to AJAX Handlers

**File**: `includes/shortcodes/class-fanfic-shortcodes-actions.php`

**For each handler** (find these methods and add the check):

### 9a. `ajax_bookmark_story()`
```php
public static function ajax_bookmark_story() {
    check_ajax_referer( 'fanfic_actions_nonce' );

    // NEW: Check if bookmarks are enabled
    $settings = get_option( 'fanfic_settings', array() );
    $enable_bookmarks = isset( $settings['enable_bookmarks'] ) ? $settings['enable_bookmarks'] : true;

    if ( ! $enable_bookmarks ) {
        wp_send_json_error( array(
            'message' => __( 'Bookmarks are disabled', 'fanfiction-manager' ),
        ) );
    }

    // ... rest of handler code (NO CHANGES)
}
```

**Status**: [ ] Complete

### 9b. `ajax_unbookmark_story()`
Add same check (look for the method and add the feature check)

**Status**: [ ] Complete

### 9c. `ajax_follow_author()`
```php
// NEW: Check if follows are enabled
$settings = get_option( 'fanfic_settings', array() );
$enable_follows = isset( $settings['enable_follows'] ) ? $settings['enable_follows'] : true;

if ( ! $enable_follows ) {
    wp_send_json_error( array(
        'message' => __( 'Follows are disabled', 'fanfiction-manager' ),
    ) );
}
```

**Status**: [ ] Complete

### 9d. `ajax_unfollow_author()`
Add same check as follow_author

**Status**: [ ] Complete

### 9e. `ajax_mark_as_read()`
```php
// NEW: Check if reading progress is enabled
$settings = get_option( 'fanfic_settings', array() );
$enable_reading_progress = isset( $settings['enable_reading_progress'] ) ? $settings['enable_reading_progress'] : true;

if ( ! $enable_reading_progress ) {
    wp_send_json_error( array(
        'message' => __( 'Reading progress tracking is disabled', 'fanfiction-manager' ),
    ) );
}
```

**Status**: [ ] Complete

### 9f. `ajax_unmark_as_read()`
Add same check as mark_as_read

**Status**: [ ] Complete

---

## ✅ Step 10: Update Admin Settings UI

**File**: Find the admin settings template (likely in `admin/` folder or inline in `class-fanfic-settings.php`)

**Action**: Add 4 new checkbox rows in the General Settings tab:

```html
<!-- Enable Rating -->
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

<!-- Enable Bookmarks -->
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

<!-- Enable Follows -->
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

<!-- Enable Reading Progress -->
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

**Status**: [ ] Complete

---

## ✅ Step 11: Test All Changes

**Testing Steps**:

1. [ ] Navigate to plugin settings
2. [ ] Verify all 4 new checkboxes appear
3. [ ] Toggle rating OFF, verify rating widget doesn't render on chapter
4. [ ] Toggle rating ON, verify rating widget renders again
5. [ ] Toggle bookmarks OFF, verify bookmark button doesn't render
6. [ ] Toggle bookmarks ON, verify bookmark button renders again
7. [ ] Toggle follows OFF, verify follow button doesn't render
8. [ ] Toggle follows ON, verify follow button renders again
9. [ ] Toggle reading progress OFF, verify mark as read button doesn't render
10. [ ] Toggle reading progress ON, verify mark as read button renders again
11. [ ] Try to submit data via AJAX when feature disabled (should fail)
12. [ ] Try to submit data via AJAX when feature enabled (should succeed)
13. [ ] Clear cache and reload to verify settings persist
14. [ ] Test on different pages to ensure consistent behavior

---

## ✅ Step 12: Documentation & Commit

**Actions**:

1. [ ] Update plugin version (e.g., 1.0.15 → 1.0.16)
2. [ ] Add CHANGELOG entry
3. [ ] Test on staging environment
4. [ ] Create git commit:
   ```
   "Add admin feature toggles for ratings, bookmarks, follows, reading progress"
   ```

---

## Summary of Files Changed

| File | Changes |
|------|---------|
| `includes/class-fanfic-settings.php` | 2 locations (defaults + sanitization) |
| `includes/shortcodes/class-fanfic-shortcodes-actions.php` | 6 locations (settings retrieval + 5 button renders + 6 AJAX handlers) |
| `includes/shortcodes/class-fanfic-shortcodes-forms.php` | 1 location (rating widget check) |
| Admin UI template | 4 new checkboxes added |

**Total Changes**: ~15 code locations across 3 files

---

## Estimated Time

- **Reading & Understanding**: 15 minutes
- **Code Changes**: 30 minutes
- **Testing**: 20 minutes
- **Documentation**: 10 minutes

**Total**: ~75 minutes (1.25 hours)

---

## Risk Assessment

**Risk Level**: ✅ **LOW**

- No database schema changes
- No data migrations required
- 100% backward compatible (all default to ON)
- Easy to revert if needed
- No breaking changes to existing code

---

## Success Criteria

- [ ] All 4 new settings appear in admin panel
- [ ] Toggling each setting ON/OFF works correctly
- [ ] Buttons don't render when feature disabled
- [ ] Buttons render when feature enabled
- [ ] AJAX requests fail gracefully when feature disabled
- [ ] JavaScript doesn't error when buttons don't exist
- [ ] Settings persist after page reload
- [ ] Default value is TRUE (backward compatible)
- [ ] All existing functionality still works
- [ ] No console errors in browser

---

## Next Steps

Once complete:

1. **Deploy to staging**
2. **Run through testing checklist**
3. **Get approval from product owner**
4. **Deploy to production**
5. **Monitor for issues**
6. **Gather user feedback**

---

**Created**: 2025-11-13
**Status**: Ready to Implement
**Difficulty**: Easy ⭐
**Time Required**: ~1.25 hours
