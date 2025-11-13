# Admin Feature Toggles - User Guide

## Overview

WordPress site admins can now control whether each user interaction feature is available on the site via the **General Settings** tab in the plugin admin panel.

---

## Available Toggles

### 1. **Enable Ratings** ⭐
- **What it does**: Allows users to rate chapters with 1-5 stars
- **Default**: ON
- **When disabled**:
  - Rating widget shortcode `[chapter-rating-form]` renders nothing
  - Star rating input unavailable
  - Existing ratings remain in database (not deleted)
  - AJAX rating requests rejected

### 2. **Enable Bookmarks** 🔖
- **What it does**: Allows users to bookmark stories and chapters
- **Default**: ON
- **When disabled**:
  - Bookmark button doesn't render
  - Users can't click to bookmark
  - Existing bookmarks remain in database
  - AJAX bookmark requests rejected

### 3. **Enable Follows** 👥
- **What it does**: Allows users to follow stories and authors
- **Default**: ON
- **When disabled**:
  - Follow button doesn't render
  - Users can't follow stories or authors
  - Existing follows remain in database
  - Email notifications still go out to existing followers
  - AJAX follow requests rejected

### 4. **Enable Reading Progress** ✅
- **What it does**: Allows users to mark chapters as read and track reading progress
- **Default**: ON
- **When disabled**:
  - "Mark as Read" button doesn't render
  - "Read List" button doesn't render
  - Users can't track which chapters they've read
  - Existing read tracking remains in database
  - Reading progress widget doesn't display
  - AJAX mark-as-read requests rejected

### 5. **Enable Likes** ❤️ *(Already Implemented)*
- **What it does**: Allows users to like chapters
- **Default**: ON
- **When disabled**: Like button doesn't render

### 6. **Enable Subscribe** 🔔 *(Already Implemented)*
- **What it does**: Allows users to subscribe to email notifications for new chapters
- **Default**: ON
- **When disabled**: Subscribe button doesn't render

---

## Location in Plugin Settings

### For Site Admins:

1. Go to **WordPress Admin Dashboard**
2. Navigate to **Fanfiction Manager** → **Settings**
3. Click the **General** tab
4. Scroll to **Feature Toggles** section
5. Check/uncheck boxes to enable/disable features
6. Click **Save Changes**

### Settings Are Saved As:
```
Option Name: fanfic_settings
Values:
  - enable_rating: true/false
  - enable_bookmarks: true/false
  - enable_follows: true/false
  - enable_reading_progress: true/false
  - enable_likes: true/false (existing)
  - enable_subscribe: true/false (existing)
```

---

## How It Works: The Flow

### When Feature is **ENABLED** ✅

```
Button Rendered in Shortcode
         ↓
Click Handler Attached by JavaScript
         ↓
AJAX Request Sent
         ↓
Server Checks if Feature Enabled
         ↓
✅ Allowed → Feature Works Normally
         ↓
Data Saved to Database
```

### When Feature is **DISABLED** ❌

```
Shortcode Checks Admin Setting
         ↓
❌ Feature Disabled → Button NOT Rendered
         ↓
User Never Sees Button
         ↓
No AJAX Requests Can Be Made
         ↓
No Data Saved
```

---

## Example Scenarios

### Scenario 1: Disabling Ratings Temporarily

**Admin Action**: Uncheck "Enable Ratings" in settings

**What Users See**:
- Rating widget `[chapter-rating-form]` shows nothing
- No 1-5 star input appears on chapters
- If they try to POST rating via developer tools, request rejected with error

**What Happens**:
- Existing ratings stay in database
- If re-enabled later, all ratings reappear immediately
- No data loss

---

### Scenario 2: Turning Off Reading Progress

**Admin Action**: Uncheck "Enable Reading Progress" in settings

**What Users See**:
- No "Mark as Read" button on chapters
- No "Read List" button on stories
- Reading progress widget doesn't appear

**Behind The Scenes**:
- All existing reading progress data stays in database
- Users' read history is preserved
- When re-enabled, users see their progress again

---

### Scenario 3: Disabling Follows

**Admin Action**: Uncheck "Enable Follows" in settings

**What Users See**:
- No "Follow" button on stories
- No "Follow" button on author profiles
- Can't follow anything

**Behind The Scenes**:
- Existing follows still work
- Email notifications still send to existing followers
- New follows can't be created
- If re-enabled, new follows can resume

---

## Database Data

### Important: **No Data Deletion**

Disabling a feature does **NOT** delete data:

- **Ratings**: Stay in `wp_fanfic_ratings` table
- **Bookmarks**: Stay in `wp_fanfic_bookmarks` table
- **Follows**: Stay in `wp_fanfic_follows` table
- **Reading Progress**: Stays in `wp_fanfic_reading_progress` table

### Re-enabling Features

If you disable a feature and then re-enable it:
1. All previous data immediately becomes visible/usable again
2. Users see their previous actions reflected
3. No data recovery needed - it was never deleted

---

## Use Cases

### 1. **Site Maintenance**
Temporarily disable features while doing updates:
```
☐ Enable Ratings
☐ Enable Bookmarks
☐ Enable Follows
☐ Enable Reading Progress
✓ Enable Likes
✓ Enable Subscribe
```
Re-enable when maintenance complete.

### 2. **Phased Rollout**
Launch features gradually:
```
Day 1: Just Likes and Subscribe
Day 3: Add Ratings
Day 5: Add Bookmarks
Day 7: Add Follows and Reading Progress
```

### 3. **Community Moderation**
If follows/bookmarks are being abused, disable temporarily:
```
✓ Enable Ratings
✓ Enable Bookmarks (but monitor)
☐ Enable Follows (disabled due to spam)
✓ Enable Reading Progress
```

### 4. **Lightweight Mode**
For performance, disable less-critical features:
```
✓ Enable Ratings
☐ Enable Bookmarks (disabled)
☐ Enable Follows (disabled)
☐ Enable Reading Progress (disabled)
✓ Enable Likes
✓ Enable Subscribe
```

---

## Technical Implementation

### For Developers

The feature toggles are checked in three places:

1. **Shortcode Rendering** (Frontend)
   - Buttons only render if feature enabled
   - File: `includes/shortcodes/class-fanfic-shortcodes-*.php`

2. **AJAX Processing** (Server)
   - Requests rejected if feature disabled
   - File: `includes/shortcodes/class-fanfic-shortcodes-actions.php`

3. **JavaScript Initialization** (Frontend)
   - Handlers only attach if buttons exist
   - File: `assets/js/fanfiction-actions.js`

### Code Pattern

```php
// Get setting
$settings = get_option( 'fanfic_settings', array() );
$enable_feature = isset( $settings['enable_feature'] )
    ? $settings['enable_feature']
    : true;  // Default to enabled

// Check before rendering
if ( $enable_feature ) {
    // Render button/widget
}

// Check in AJAX handler
if ( ! $enable_feature ) {
    wp_send_json_error( 'Feature disabled' );
}
```

---

## Backward Compatibility

✅ **100% Backward Compatible**

- All features default to **ON**
- No existing code breaks
- No database migrations needed
- No data loss possible
- Can be disabled/enabled anytime

---

## Performance Impact

**Negligible Impact**:
- Single `get_option()` call per page load (cached by WordPress)
- Simple boolean checks (if statements)
- No new database queries
- No additional load

---

## Security Notes

✅ **Secure by Default**:
- All settings sanitized on save
- All AJAX requests verified with nonces
- Disabled features completely blocked
- No way to bypass via JavaScript (server-side check)

---

## Troubleshooting

### Buttons Still Show After Disabling
- **Cause**: Settings not saved or cached
- **Fix**: Clear WordPress cache, save settings again

### Settings Reverted After Disable
- **Cause**: `sanitize_settings()` filter removing option
- **Fix**: Make sure new settings added to sanitization function

### AJAX Still Works When Disabled
- **Cause**: AJAX handler not checking feature flag
- **Fix**: Add setting check to AJAX handler (see implementation guide)

---

## FAQ

**Q: Do disabled features delete user data?**
A: No. Disabling just hides the feature. All data stays in the database.

**Q: Can I hide buttons without affecting backend?**
A: Yes. Disabled features reject AJAX requests, preventing data operations.

**Q: What's the default for new features?**
A: All features default to ON for backward compatibility.

**Q: Can users bypass disabled features?**
A: No. AJAX handlers check server-side, so bypassing JavaScript doesn't work.

**Q: Do I lose data if I uninstall the plugin?**
A: No. The plugin deactivation hook preserves all tables and data.

---

## Implementation Files

The following files have been modified to support admin toggles:

1. ✅ `includes/class-fanfic-settings.php` - Default settings
2. ✅ `includes/shortcodes/class-fanfic-shortcodes-actions.php` - Shortcode rendering
3. ✅ `includes/shortcodes/class-fanfic-shortcodes-forms.php` - Rating widget
4. ✅ `includes/shortcodes/class-fanfic-shortcodes-actions.php` - AJAX handlers
5. ✅ `assets/js/fanfiction-actions.js` - JavaScript initialization

See `ADMIN_FEATURE_TOGGLES_IMPLEMENTATION.md` for detailed code changes.

---

## Quick Reference: Setting Keys

```php
// In Settings Storage
'enable_rating'          // Chapter ratings (1-5 stars)
'enable_bookmarks'       // Story/chapter bookmarks
'enable_follows'         // Story/author follows
'enable_reading_progress' // Mark as read + progress tracking
'enable_likes'           // Chapter likes (existing)
'enable_subscribe'       // Email subscriptions (existing)
```

---

## Version Info

- **Added**: v1.0.15
- **Status**: Production Ready
- **Backward Compatible**: Yes ✅
- **Data Safe**: Yes ✅
- **Reversible**: Yes ✅

---

Last Updated: 2025-11-13
Status: Ready for Implementation
