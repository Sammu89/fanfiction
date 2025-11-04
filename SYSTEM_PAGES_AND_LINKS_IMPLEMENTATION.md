# System Pages and Links Implementation Documentation

**Version:** 1.0.0
**Last Updated:** November 2024
**Status:** Comprehensive Implementation Guide

---

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Modifiable Elements](#modifiable-elements)
4. [Helper Functions](#helper-functions)
5. [Core Classes](#core-classes)
6. [User Profile Page](#user-profile-page)
7. [Wizard Integration](#wizard-integration)
8. [URL Settings Page](#url-settings-page)
9. [Migration Guide](#migration-guide)
10. [Developer Guide](#developer-guide)
11. [Troubleshooting](#troubleshooting)

---

## Overview

The Fanfiction Manager plugin implements a robust system for managing URLs, pages, and links throughout the fanfiction platform. The system uses a **two-tier approach** combining WordPress Page IDs (for permanence) and 301 redirects (for SEO preservation) to ensure flexible URL customization while maintaining data integrity.

### Key Features

- **18 Modifiable Page/Path Names** - Configure every major URL in the system
- **19 Helper Functions** - Easy-to-use functions for URL generation throughout the plugin
- **Automatic 301 Redirects** - Old URLs redirect automatically to new ones
- **3-Month Redirect Expiry** - Automatically cleans up old redirect mappings
- **SEO Preservation** - 301 redirects maintain search engine rankings
- **Query Parameter Support** - Dynamic URLs using `?member`, `?story_id`, etc.
- **Admin Settings Page** - Unified interface for all URL configuration
- **Setup Wizard Integration** - Initial configuration during plugin activation

---

## System Architecture

### Two-System Approach

The plugin uses a dual system for maximum flexibility:

#### System 1: Page IDs (Primary, Permanent)

- Stores WordPress page IDs in WordPress options table
- Example: `fanfic_system_page_ids` option
- **Advantage:** Permanent, always points to correct page regardless of slug changes
- **Used for:** Direct URL generation via `get_permalink()`

```php
// Option structure
$page_ids = array(
    'login'          => 42,
    'register'       => 43,
    'dashboard'      => 44,
    'create-story'   => 45,
    'edit-story'     => 46,
    'edit-chapter'   => 47,
    'edit-profile'   => 48,
    'password-reset' => 49,
    'search'         => 50,
    'members'        => 51,
    'error'          => 52,
    'maintenance'    => 53,
    'main'           => 54,
);
```

#### System 2: Slug Mapping (Secondary, Flexible)

- Stores page slugs in WordPress options table
- Example: `fanfic_system_page_slugs` option
- **Advantage:** Allows slug customization, creates redirects automatically
- **Used for:** Display and redirect management

```php
// Option structure
$page_slugs = array(
    'login'          => 'login',
    'register'       => 'register',
    'dashboard'      => 'dashboard',
    'create-story'   => 'create-story',
    // ... etc
);
```

### 301 Redirect System

When a slug is changed, the system automatically:

1. **Detects the change** via `Fanfic_Slug_Tracker` class
2. **Stores the mapping** in `fanfic_slug_redirects` option
3. **Creates 301 redirect** via `Fanfic_Redirects` class
4. **Expires after 3 months** via scheduled action

**Redirect Mapping Example:**

```php
$redirects = array(
    'old-login' => array(
        'new_slug'  => 'signin',
        'timestamp' => 1699000000,
    ),
    'old-dashboard' => array(
        'new_slug'  => 'my-dashboard',
        'timestamp' => 1699001000,
    ),
);
```

### Request Flow Diagram

```
User Request
    ↓
[Fanfic_Redirects::handle_redirects()] → Check for old slugs
    ↓ (if old slug found)
[301 Redirect to new URL]
    ↓ (if current slug)
[Continue to page]
    ↓
[Load page via Page ID]
```

---

## Modifiable Elements

The system manages 18 customizable elements organized in 4 categories:

### Category 1: Base URLs (2 elements)

| Element | Default | Use Case | Example URL |
|---------|---------|----------|-------------|
| **Base Slug** | `fanfiction` | Root path for all fanfiction content | `/fanfiction/` |
| **Archive Slug** | `archive` | Browse all stories page | `/fanfiction/archive/` |

**Configuration:** Primary URL Settings section in Admin Settings

### Category 2: System Page Slugs (12 elements)

| Element | Default | Page Type | Example URL |
|---------|---------|-----------|-------------|
| **Login** | `login` | User authentication | `/fanfiction/login/` |
| **Register** | `register` | New user signup | `/fanfiction/register/` |
| **Password Reset** | `password-reset` | Password recovery | `/fanfiction/password-reset/` |
| **Dashboard** | `dashboard` | Author dashboard | `/fanfiction/dashboard/` |
| **Create Story** | `create-story` | New story form | `/fanfiction/create-story/` |
| **Edit Story** | `edit-story` | Story editor | `/fanfiction/edit-story/` |
| **Edit Chapter** | `edit-chapter` | Chapter editor | `/fanfiction/edit-chapter/` |
| **Edit Profile** | `edit-profile` | User profile editor | `/fanfiction/edit-profile/` |
| **Search** | `search` | Story search page | `/fanfiction/search/` |
| **Members/Profiles** | `members` | User profiles (with `?member` param) | `/fanfiction/members/?member=username` |
| **Error** | `error` | Error page | `/fanfiction/error/` |
| **Maintenance** | `maintenance` | Maintenance mode page | `/fanfiction/maintenance/` |

**Configuration:** System Page Slugs section in Admin Settings

### Category 3: Chapter Type Slugs (3 elements)

| Element | Default | Format | Example URL |
|---------|---------|--------|-------------|
| **Prologue** | `prologue` | Single occurrence | `/fanfiction/story-title/prologue/` |
| **Chapter** | `chapter` | Numbered (1, 2, 3...) | `/fanfiction/story-title/chapter-1/` |
| **Epilogue** | `epilogue` | Single occurrence | `/fanfiction/story-title/epilogue/` |

**Configuration:** Chapter URLs section in Admin Settings

### Category 4: Secondary Paths (1 element)

| Element | Default | Use Case | Example URL |
|---------|---------|----------|-------------|
| **Dashboard** | `dashboard` | Author control panel | `/fanfiction/dashboard/` |
| **User** | `user` | Individual user profile path | `/fanfiction/user/username/` |
| **Search** | `search` | Search functionality | `/fanfiction/search/` |

**Configuration:** User & System URLs section in Admin Settings

### Total Modifiable Elements: 18

---

## Helper Functions

The plugin provides 19 helper functions for URL generation throughout the codebase. All functions are defined in `/includes/functions.php`.

### Core Function: Get Page URL

#### `fanfic_get_page_url( $page_key )`

**Description:** Get URL for any system page by its key

**Parameters:**
- `$page_key` (string) - The page key (e.g., 'dashboard', 'login')

**Returns:** (string) The page URL or empty string if not found

**Uses:** Page IDs stored in `fanfic_system_page_ids` option

**Example:**
```php
$url = fanfic_get_page_url( 'dashboard' );
// Returns: https://example.com/fanfiction/dashboard/
```

**How It Works:**
```php
function fanfic_get_page_url( $page_key ) {
    $page_ids = get_option( 'fanfic_system_page_ids', array() );

    if ( empty( $page_ids[ $page_key ] ) ) {
        return '';
    }

    $url = get_permalink( $page_ids[ $page_key ] );
    return $url ? $url : '';
}
```

---

### Helper Functions by Category

#### Authentication Pages (3 functions)

**1. `fanfic_get_login_url()`**

Returns the login page URL

```php
$login_url = fanfic_get_login_url();
// Returns: https://example.com/fanfiction/login/
```

**2. `fanfic_get_register_url()`**

Returns the registration page URL

```php
$register_url = fanfic_get_register_url();
// Returns: https://example.com/fanfiction/register/
```

**3. `fanfic_get_password_reset_url()`**

Returns the password reset page URL

```php
$reset_url = fanfic_get_password_reset_url();
// Returns: https://example.com/fanfiction/password-reset/
```

---

#### Dashboard & Profile (4 functions)

**4. `fanfic_get_dashboard_url()`**

Returns the author dashboard URL

```php
$dashboard = fanfic_get_dashboard_url();
// Returns: https://example.com/fanfiction/dashboard/
```

**5. `fanfic_get_edit_profile_url()`**

Returns the profile edit page URL

```php
$edit_profile = fanfic_get_edit_profile_url();
// Returns: https://example.com/fanfiction/edit-profile/
```

**6. `fanfic_get_user_profile_url( $user )`**

Returns a specific user's profile URL with query parameter

**Parameters:**
- `$user` (mixed) - User ID, username string, or WP_User object

**Returns:** (string) User profile URL with `?member=username` parameter

```php
// Using user ID
$profile = fanfic_get_user_profile_url( 123 );
// Returns: https://example.com/fanfiction/members/?member=johndoe

// Using username
$profile = fanfic_get_user_profile_url( 'johndoe' );
// Returns: https://example.com/fanfiction/members/?member=johndoe

// Using WP_User object
$user = get_user_by( 'login', 'johndoe' );
$profile = fanfic_get_user_profile_url( $user );
// Returns: https://example.com/fanfiction/members/?member=johndoe
```

**Important:** This function automatically converts user IDs to usernames to avoid conflicts with page IDs.

**7. `fanfic_get_stories_archive_url()`**

Returns the main stories archive/listing URL

```php
$archive = fanfic_get_stories_archive_url();
// Returns: https://example.com/fanfiction/archive/
```

---

#### Story Management (3 functions)

**8. `fanfic_get_create_story_url()`**

Returns the create new story page URL

```php
$create = fanfic_get_create_story_url();
// Returns: https://example.com/fanfiction/create-story/
```

**9. `fanfic_get_edit_story_url( $story_id )`**

Returns the edit story page URL with optional story ID query parameter

**Parameters:**
- `$story_id` (int) - Optional story ID (appends as `?story_id=123`)

**Returns:** (string) Edit story URL

```php
// Without story ID
$edit = fanfic_get_edit_story_url();
// Returns: https://example.com/fanfiction/edit-story/

// With story ID
$edit = fanfic_get_edit_story_url( 456 );
// Returns: https://example.com/fanfiction/edit-story/?story_id=456
```

**10. `fanfic_get_edit_chapter_url( $chapter_id )`**

Returns the edit chapter page URL with optional chapter ID query parameter

**Parameters:**
- `$chapter_id` (int) - Optional chapter ID (appends as `?chapter_id=789`)

**Returns:** (string) Edit chapter URL

```php
// Without chapter ID
$edit = fanfic_get_edit_chapter_url();
// Returns: https://example.com/fanfiction/edit-chapter/

// With chapter ID
$edit = fanfic_get_edit_chapter_url( 789 );
// Returns: https://example.com/fanfiction/edit-chapter/?chapter_id=789
```

---

#### Search & Discovery (2 functions)

**11. `fanfic_get_search_url()`**

Returns the search page URL

```php
$search = fanfic_get_search_url();
// Returns: https://example.com/fanfiction/search/
```

**12. `fanfic_get_main_url()`**

Returns the main archive/stories listing URL

```php
$main = fanfic_get_main_url();
// Returns: https://example.com/fanfiction/archive/
```

---

#### Error Pages (1 function)

**13. `fanfic_get_error_url()`**

Returns the error page URL

```php
$error = fanfic_get_error_url();
// Returns: https://example.com/fanfiction/error/
```

---

#### User Role Checks (2 functions)

**14. `fanfic_is_author( $user_id )`**

Check if user has the fanfiction_author role

**Parameters:**
- `$user_id` (int) - Optional user ID (defaults to current user)

**Returns:** (bool) True if user is an author

```php
if ( fanfic_is_author() ) {
    // Current user is an author
}

if ( fanfic_is_author( 123 ) ) {
    // User 123 is an author
}
```

**15. `fanfic_is_moderator( $user_id )`**

Check if user has the fanfiction_moderator role

**Parameters:**
- `$user_id` (int) - Optional user ID (defaults to current user)

**Returns:** (bool) True if user is a moderator

```php
if ( fanfic_is_moderator() ) {
    // Current user is a moderator
}

if ( fanfic_is_moderator( 123 ) ) {
    // User 123 is a moderator
}
```

---

#### Utility Functions (2 functions)

**16. `fanfic_get_version()`**

Get the plugin version

**Returns:** (string) Version number

```php
$version = fanfic_get_version();
// Returns: "1.0.0"
```

**17. `fanfic_get_table_name( $table_name )`**

Get custom table name with proper WordPress prefix

**Parameters:**
- `$table_name` (string) - Table name without prefix (e.g., 'ratings')

**Returns:** (string) Full table name with prefix (e.g., 'wp_fanfic_ratings')

```php
$table = fanfic_get_table_name( 'ratings' );
// Returns: "wp_fanfic_ratings"
```

**18. `fanfic_sanitize_content( $content )`**

Sanitize story content allowing only basic HTML

**Parameters:**
- `$content` (string) - Content to sanitize

**Returns:** (string) Sanitized content

**Allowed HTML Tags:** `<p>`, `<br>`, `<strong>`, `<em>`, `<b>`, `<i>`

```php
$clean = fanfic_sanitize_content( $user_input );
// Removes all HTML except allowed tags
```

---

### Function Location Reference

All helper functions are located in:

```
C:\xampp\htdocs\smpt\wp-content\plugins\fanfiction\includes\functions.php
```

### Function Usage Best Practices

**1. Always Use Helper Functions**

Do NOT hardcode URLs:

```php
// WRONG - hardcoded URL
$login_url = '/fanfiction/login/';

// CORRECT - uses helper function
$login_url = fanfic_get_login_url();
```

**2. Build URLs with Query Parameters**

Use `add_query_arg()` for query parameters:

```php
$url = fanfic_get_edit_story_url();
$url_with_params = add_query_arg( array(
    'story_id' => 123,
    'tab'      => 'settings',
), $url );
```

**3. Escape URLs in Output**

Always escape when outputting URLs:

```php
// In HTML attributes
echo 'href="' . esc_url( fanfic_get_dashboard_url() ) . '"';

// In JavaScript
wp_localize_script( 'my-script', 'myData', array(
    'dashboard_url' => esc_url( fanfic_get_dashboard_url() ),
) );
```

---

## Core Classes

### 1. Fanfic_Slug_Tracker

**File:** `/includes/class-fanfic-slug-tracker.php`

**Purpose:** Detects when system page slugs change and creates 301 redirect mappings

**Key Methods:**

#### `init()`

Initialize the slug tracking system

```php
Fanfic_Slug_Tracker::init();
// Hooks into: post_updated, daily cleanup action
// Schedules: Daily cleanup of expired redirects
```

#### `detect_slug_change( $post_id, $post_after, $post_before )`

Automatically called when a page is updated. Detects if slug changed.

```php
// Called automatically via post_updated hook
// Do not call manually - WordPress handles this
```

**How It Works:**
1. Checks if post is a page and its ID is in `fanfic_system_page_ids`
2. Compares `post_name` (slug) before and after
3. If changed, calls `add_redirect()`
4. Flushes rewrite rules

#### `add_redirect( $old_slug, $new_slug )`

Store a redirect mapping

```php
// Called automatically by detect_slug_change()
Fanfic_Slug_Tracker::add_redirect( 'old-slug', 'new-slug' );

// Stores in: fanfic_slug_redirects option
// Includes: Timestamp for 3-month expiry
```

**Data Structure:**
```php
$redirects = array(
    'old-slug' => array(
        'new_slug'  => 'new-slug',
        'timestamp' => 1699000000,
    ),
);
```

#### `add_manual_redirect( $old_slug, $new_slug )`

Manually add a redirect (used by settings page)

```php
Fanfic_Slug_Tracker::add_manual_redirect( 'old-login', 'signin' );
// Does not add if slugs are identical
```

#### `get_redirects()`

Get all active redirect mappings

```php
$redirects = Fanfic_Slug_Tracker::get_redirects();
// Returns: array of active redirects with timestamps
```

#### `cleanup_expired_redirects()`

Remove redirects older than 3 months

```php
Fanfic_Slug_Tracker::cleanup_expired_redirects();
// Called automatically by daily scheduled action
// Checks: timestamp < 3 months ago
// Cleans: Only expired entries
```

#### `clear_all_redirects()`

Remove all redirect mappings (admin function)

```php
Fanfic_Slug_Tracker::clear_all_redirects();
// Caution: Removes all active redirects
// Use: For debugging or complete reset
```

---

### 2. Fanfic_Redirects

**File:** `/includes/class-fanfic-redirects.php`

**Purpose:** Handles actual 301 redirects for old slugs to new ones

**Key Methods:**

#### `init()`

Initialize the redirect handler

```php
Fanfic_Redirects::init();
// Hooks into: template_redirect (priority 1, very early)
```

#### `handle_redirects()`

Process 301 redirects for old slugs

```php
// Called automatically on every page load
// Do not call manually - WordPress handles this
```

**How It Works:**
1. Gets current URL from `$_SERVER['REQUEST_URI']`
2. Checks each URL segment against redirect mappings
3. If old slug found, replaces with new slug
4. Performs 301 redirect with preserved query string
5. Exits to prevent further processing

**Example:**
```
Request: /fanfiction/old-login/?redirect=/dashboard
Mapping: old-login → signin
Result:  301 redirect to /fanfiction/signin/?redirect=/dashboard
```

#### `get_redirect_count()`

Get count of active redirects

```php
$count = Fanfic_Redirects::get_redirect_count();
// Returns: 5 (example)
```

#### `get_redirect_info()`

Get formatted info about all active redirects

```php
$info = Fanfic_Redirects::get_redirect_info();
// Returns array like:
// array(
//     array(
//         'old_slug'  => 'old-login',
//         'new_slug'  => 'signin',
//         'created'   => '2024-11-04 10:30:45',
//         'expires'   => '2025-02-02',
//     ),
// )
```

**Used By:** Admin Settings page to display active redirects

---

### 3. Fanfic_URL_Config

**File:** `/includes/class-fanfic-url-config.php`

**Purpose:** Manages URL configuration page and settings

**Key Methods:**

#### `init()`

Initialize URL configuration system

```php
Fanfic_URL_Config::init();
// Hooks into: admin_post, template_redirect
```

#### `render()`

Display the URL configuration admin page

```php
// Called automatically via WordPress admin menu
Fanfic_URL_Config::render();
```

**Page Sections:**
1. **Site Organization** - Choose between stories homepage or custom homepage
2. **Primary URLs** - Base slug and archive slug
3. **User & System URLs** - Dashboard, user, search paths
4. **Chapter URLs** - Prologue, chapter, epilogue slugs
5. **Redirect Information** - Display active redirects

#### `save_url_config()`

Handle form submission from settings page

```php
// Called automatically via admin_post_fanfic_save_url_config
// Validates all inputs
// Updates all options
// Triggers slug tracker if changes detected
// Flushes rewrite rules
```

**Validation Process:**
1. Verify nonce
2. Validate base slug
3. Check secondary paths for duplicates
4. Validate chapter slugs for uniqueness
5. Sanitize all inputs
6. Check conflicts with existing slugs
7. Check WordPress reserved slugs
8. Save all options
9. Create redirect mappings if slugs changed
10. Flush rewrite rules

#### `validate_slug( $slug, $exclude )`

Validate a slug against rules and conflicts

```php
$result = Fanfic_URL_Config::validate_slug( 'my-slug', array( 'base' ) );
// Returns: true or WP_Error

// Use in custom code
if ( is_wp_error( $result ) ) {
    echo $result->get_error_message();
}
```

**Validation Rules:**
- Not empty
- Max 50 characters
- Only lowercase letters, numbers, hyphens
- No conflicts with other fanfiction slugs
- Not in WordPress reserved list

#### `get_current_slugs()`

Get all current slug settings with defaults

```php
$slugs = Fanfic_URL_Config::get_current_slugs();
// Returns: array(
//     'base'      => 'fanfiction',
//     'prologue'  => 'prologue',
//     'chapter'   => 'chapter',
//     'epilogue'  => 'epilogue',
//     'dashboard' => 'dashboard',
//     'user'      => 'user',
//     'archive'   => 'archive',
//     'search'    => 'search',
// )
```

#### `display_notices()`

Show success/error messages from transients

```php
// Called automatically in render()
Fanfic_URL_Config::display_notices();
```

---

## User Profile Page

The user profile page uses a special `?member` query parameter to display different user profiles dynamically on a single page.

### How It Works

**Page Setup:**
- Single WordPress page with `members` key in system pages
- Example URL: `/fanfiction/members/`

**Dynamic Profile Access:**
- Add `?member=username` query parameter
- Example: `/fanfiction/members/?member=johndoe`

### Helper Function

```php
function fanfic_get_user_profile_url( $user ) {
    $members_url = fanfic_get_page_url( 'members' );

    if ( ! $members_url ) {
        return '';
    }

    // Get username from various input types
    if ( is_numeric( $user ) ) {
        $user_obj = get_user_by( 'id', $user );
        $username = $user_obj ? $user_obj->user_login : '';
    } elseif ( is_object( $user ) && isset( $user->user_login ) ) {
        $username = $user->user_login;
    } else {
        $username = $user;
    }

    if ( empty( $username ) ) {
        return $members_url;
    }

    return add_query_arg( 'member', $username, $members_url );
}
```

### Usage Examples

```php
// Link to specific user profile
$profile_url = fanfic_get_user_profile_url( 'johndoe' );
// Returns: /fanfiction/members/?member=johndoe

// Using user ID (auto-converts to username)
$profile_url = fanfic_get_user_profile_url( 123 );
// Returns: /fanfiction/members/?member=johndoe

// Using WP_User object
$user = get_user_by( 'login', 'johndoe' );
$profile_url = fanfic_get_user_profile_url( $user );
// Returns: /fanfiction/members/?member=johndoe

// Link to members page without specific user
$members_url = fanfic_get_user_profile_url( '' );
// Returns: /fanfiction/members/
```

### In Templates

```php
// Display link to user profile
echo '<a href="' . esc_url( fanfic_get_user_profile_url( $user_id ) ) . '">';
echo esc_html( get_the_author_meta( 'display_name', $user_id ) );
echo '</a>';
```

### Retrieving Query Parameter

In the members page template:

```php
$member = isset( $_GET['member'] ) ? sanitize_key( $_GET['member'] ) : '';

if ( ! empty( $member ) ) {
    $user = get_user_by( 'login', $member );
    if ( $user ) {
        // Display this user's profile
    }
} else {
    // Display members list
}
```

---

## Wizard Integration

The setup wizard (`Fanfic_Wizard` class) integrates with the URL system during initial plugin configuration.

### Wizard Steps

**Step 1: Welcome**
- Introduction to fanfiction manager
- Overview of setup process

**Step 2: URL Settings** ← URL System Integration
- Configure base slug
- Choose site organization (stories homepage vs. custom)
- Set chapter type slugs
- Configure system page slugs

**Step 3: User Roles**
- Assign moderators
- Set up user roles

**Step 4: Taxonomy Terms**
- Configure genres
- Set default story status

**Step 5: Complete**
- Review settings
- Create system pages
- Display completion message

### How Wizard Creates Pages

When wizard completes:

1. **System Page Creation:**
   - Creates WordPress pages for each system page type
   - Stores page IDs in `fanfic_system_page_ids` option
   - Sets pages to private (not indexed)
   - Assigns pages to author user

2. **Slug Configuration:**
   - Saves all slugs from wizard input to options
   - Creates `fanfic_system_page_slugs` option
   - Updates page slugs to match configuration

3. **Rewrite Rules:**
   - Flushes rewrite rules
   - Regenerates permalinks
   - Registers custom post type rules

### Wizard Configuration Options

The wizard sets initial values for:

```php
// Option: fanfic_base_slug
'fanfiction'

// Option: fanfic_chapter_slugs
array(
    'prologue' => 'prologue',
    'chapter'  => 'chapter',
    'epilogue' => 'epilogue',
)

// Option: fanfic_secondary_paths
array(
    'dashboard' => 'dashboard',
    'user'      => 'user',
    'archive'   => 'archive',
    'search'    => 'search',
)

// Option: fanfic_system_page_slugs
array(
    'login'          => 'login',
    'register'       => 'register',
    'password-reset' => 'password-reset',
    'dashboard'      => 'dashboard',
    // ... 12 total
)

// Option: fanfic_system_page_ids
array(
    'login'          => 42,
    'register'       => 43,
    // ... 12 total with actual page IDs
)
```

### Extending Wizard

To add custom URL settings:

```php
// In your custom class
add_filter( 'fanfic_wizard_steps', array( $this, 'add_custom_step' ) );

public function add_custom_step( $steps ) {
    $steps[6] = array(
        'id'    => 'custom_urls',
        'title' => 'Custom URLs',
    );
    return $steps;
}
```

---

## URL Settings Page

The URL Settings page provides a unified admin interface for all URL configuration.

### Page Location

**WordPress Admin:** Fanfiction → Settings → URL Configuration

**Direct URL:** `wp-admin/admin.php?page=fanfic-url-rules`

### Page Sections

#### Section 1: Site Organization

**Question:** How do you want your site organized?

**Options:**

1. **Stories Archive as Homepage**
   - Main page displays story archive directly
   - Option: `fanfic_main_page_mode` = `stories_homepage`

2. **Custom Homepage**
   - Create custom homepage with separate archive page
   - Option: `fanfic_main_page_mode` = `custom_homepage`

#### Section 2: Primary URLs

**Configurable:**
- Base Slug (required)
- Archive Slug (required, when using custom homepage)

**Validation:**
- Checked for conflicts
- Max 50 characters
- Lowercase, numbers, hyphens only

**Live Preview:**
Shows example URLs as you type

#### Section 3: User & System URLs

**Fieldset 1: User-Facing Paths**
- Dashboard Slug
- User Profile Slug
- Search Slug

**Fieldset 2: System Page Slugs** (12 items)
- Login Page
- Register Page
- Password Reset
- Dashboard Page
- Create Story
- Edit Story
- Edit Chapter
- Edit Profile
- Search Page
- Profile/Members Page
- Error Page
- Maintenance Page

#### Section 4: Chapter URLs

**Configurable:**
- Prologue Slug
- Chapter Slug
- Epilogue Slug

**Validation:**
- All must be unique from each other
- Checked for format

**Note:** Numbers are auto-appended to chapters (chapter-1, chapter-2)

#### Section 5: Redirect Information

**Displays:**
- Count of active redirects
- Table with:
  - Old Slug
  - New Slug
  - Created Date
  - Expiry Date

### Workflow Example

**User Changes Base Slug:**

1. Admin navigates to URL Configuration page
2. Changes "Base Slug" from `fanfiction` to `fiction`
3. Clicks "Save All URL Settings"
4. System:
   - Validates new slug
   - Calls `Fanfic_Slug_Tracker::add_manual_redirect( 'fanfiction', 'fiction' )`
   - Updates `fanfic_base_slug` option
   - Flushes rewrite rules
   - Creates 301 redirect mapping
5. Success message displayed
6. Old URLs redirect: `/fanfiction/*` → `/fiction/*`

---

## Migration Guide

### Handling Existing URLs

When migrating from another system or reconfiguring URLs:

#### Scenario 1: Fresh Installation

1. Activate plugin
2. Run setup wizard
3. Configure initial URLs
4. System pages created with configured slugs
5. No migration needed

#### Scenario 2: Changing URL Slugs

**Process:**

1. Go to URL Configuration page
2. Modify desired slugs
3. Click "Save All URL Settings"
4. System automatically:
   - Detects changes via `detect_slug_change()`
   - Creates 301 redirect mappings
   - Updates page permalinks
   - Flushes rewrite rules

**Example:**
```
Old URL: /fanfiction/create-story/
New URL: /fanfiction/write-new-story/

Result: 301 redirect from old to new for 3 months
SEO: Google follows 301 and transfers ranking signals
Users: Old bookmarks still work (transparent redirect)
```

#### Scenario 3: Changing Base Slug

**Process:**

1. Go to URL Configuration page
2. Change "Base Slug"
3. ALL URLs automatically update:
   - `/fanfiction/login/` → `/fiction/login/`
   - `/fanfiction/stories/` → `/fiction/stories/`
   - `/fanfiction/story-title/` → `/fiction/story-title/`

4. 301 redirects created for all affected URLs
5. Existing bookmarks and external links work

#### Scenario 4: Recovering from Accidental Changes

**If user accidentally changes slugs:**

1. Go to URL Configuration page
2. Look at "Active URL Redirects" section
3. See old → new mappings
4. Change slug back to original
5. New redirect created (opposite direction)
6. Both directions work via 301 redirects

### Handling External Links

**When URLs change:**

1. **External websites** linking to old URLs:
   - 301 redirect handles transparently
   - Links continue to work
   - No action needed

2. **Email notifications** with old URLs:
   - Users can still click old links
   - 301 redirect sends them to new location

3. **User bookmarks**:
   - Browser bookmarks still work
   - 301 redirect sends to new URL

4. **SEO/Search engines**:
   - Crawlers follow 301 redirects
   - Update index gradually
   - Preserve ranking signals

### Best Practices for Migration

**1. Plan Your URL Structure**

Decide on all slugs before going live:

```
Base Slug: fanfiction
Archive Slug: archive
Chapter Slug: chapter
etc.
```

**2. Test Thoroughly**

Before deploying to production:
- Test all URLs work
- Test redirects work
- Check email links
- Verify search functionality

**3. Announce Changes (if necessary)**

If URL structure changes significantly:
- Update footer links
- Update navigation menus
- Send email to users
- Update documentation

**4. Monitor Redirects**

Check redirect count regularly:
- New redirects indicate accidental changes
- Old redirects (3+ months) are auto-removed
- Monitor for unusual patterns

**5. Update Backups**

If migrating from another system:
- Export old URLs
- Create mapping table
- Use `Fanfic_Slug_Tracker::add_manual_redirect()` to set up legacy redirects

---

## Developer Guide

### Using Helper Functions in Plugins

#### Example 1: Custom Shortcode with Links

```php
function my_fanfic_shortcode() {
    $dashboard_url = fanfic_get_dashboard_url();
    $create_url = fanfic_get_create_story_url();

    return sprintf(
        '<div class="my-fanfic-menu">
            <a href="%s">Dashboard</a>
            <a href="%s">Create Story</a>
        </div>',
        esc_url( $dashboard_url ),
        esc_url( $create_url )
    );
}
add_shortcode( 'my_fanfic_menu', 'my_fanfic_shortcode' );
```

#### Example 2: Custom Template with User Profile

```php
// In custom template
$author_id = get_post_field( 'post_author', get_the_ID() );
$author_profile_url = fanfic_get_user_profile_url( $author_id );

?>
<div class="story-header">
    <h1><?php the_title(); ?></h1>
    <p>
        By <a href="<?php echo esc_url( $author_profile_url ); ?>">
            <?php the_author(); ?>
        </a>
    </p>
</div>
```

#### Example 3: Custom Widget

```php
class My_Story_Links_Widget extends WP_Widget {
    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        echo $args['before_title'] . 'Story Links' . $args['after_title'];

        echo '<ul>';
        echo '<li><a href="' . esc_url( fanfic_get_search_url() ) . '">Search Stories</a></li>';
        echo '<li><a href="' . esc_url( fanfic_get_stories_archive_url() ) . '">All Stories</a></li>';

        if ( fanfic_is_author() ) {
            echo '<li><a href="' . esc_url( fanfic_get_create_story_url() ) . '">Write Story</a></li>';
        }
        echo '</ul>';

        echo $args['after_widget'];
    }
}
register_widget( 'My_Story_Links_Widget' );
```

### Checking User Roles

```php
// Check current user
if ( fanfic_is_author() ) {
    // Show author features
}

if ( fanfic_is_moderator() ) {
    // Show moderation features
}

// Check specific user
if ( fanfic_is_author( 123 ) ) {
    // User 123 is an author
}

// Conditional display
if ( fanfic_is_author() && current_user_can( 'edit_posts' ) ) {
    // Show editor features
}
```

### Custom URL Validation

```php
// Validate custom slug
$custom_slug = 'my-custom-path';
$validation = Fanfic_URL_Config::validate_slug( $custom_slug, array( 'base' ) );

if ( is_wp_error( $validation ) ) {
    echo 'Error: ' . $validation->get_error_message();
} else {
    // Slug is valid
}
```

### Managing Redirects Programmatically

```php
// Create redirect
Fanfic_Slug_Tracker::add_manual_redirect( 'old-path', 'new-path' );

// Get all redirects
$redirects = Fanfic_Slug_Tracker::get_redirects();
foreach ( $redirects as $old => $data ) {
    echo "$old → {$data['new_slug']}";
}

// Get redirect count
$count = Fanfic_Redirects::get_redirect_count();
if ( $count > 10 ) {
    // Alert: Too many active redirects
}

// Clear all redirects
Fanfic_Slug_Tracker::clear_all_redirects();
```

### Custom Admin Pages with URLs

```php
// In your custom admin page
$dashboard_url = fanfic_get_dashboard_url();
$search_url = fanfic_get_search_url();

?>
<div class="notice notice-info inline">
    <p>
        <?php printf(
            __( 'Frontend links: <a href="%s">Dashboard</a> | <a href="%s">Search</a>', 'my-domain' ),
            esc_url( $dashboard_url ),
            esc_url( $search_url )
        ); ?>
    </p>
</div>
```

### Debugging URL Issues

```php
// Get all current slugs
$all_slugs = Fanfic_URL_Config::get_current_slugs();
var_dump( $all_slugs );

// Get all page IDs
$page_ids = get_option( 'fanfic_system_page_ids' );
var_dump( $page_ids );

// Check redirect mappings
$redirects = Fanfic_Slug_Tracker::get_redirects();
var_dump( $redirects );

// Test a specific page URL
$url = fanfic_get_page_url( 'dashboard' );
echo "Dashboard URL: " . esc_url( $url );
```

### Custom Slug Generation

```php
// Build custom URL with query parameters
function get_story_edit_url( $story_id, $tab = 'general' ) {
    $base_url = fanfic_get_edit_story_url( $story_id );

    return add_query_arg( array(
        'tab'      => $tab,
        'version'  => 2,
    ), $base_url );
}

// Usage
$url = get_story_edit_url( 456, 'advanced' );
// Returns: /fanfiction/edit-story/?story_id=456&tab=advanced&version=2
```

---

## Troubleshooting

### Common Issues and Solutions

#### Issue 1: URLs Not Working After Slug Change

**Symptoms:**
- 404 errors on new URLs
- Old URLs redirect but new ones don't work

**Solutions:**

1. **Flush Rewrite Rules**
   ```php
   flush_rewrite_rules();
   ```

2. **Check Page IDs**
   ```php
   $page_ids = get_option( 'fanfic_system_page_ids' );
   if ( empty( $page_ids[ 'dashboard' ] ) ) {
       // Page ID missing
   }
   ```

3. **Verify Permalinks Settings**
   - Go to Settings → Permalinks
   - Ensure custom structure is selected
   - Save settings (triggers flush)

#### Issue 2: Redirects Not Working

**Symptoms:**
- Old URLs don't redirect to new ones
- 404 instead of 301 redirect

**Solutions:**

1. **Check Redirect Mappings**
   ```php
   $redirects = Fanfic_Slug_Tracker::get_redirects();
   var_dump( $redirects );
   ```

2. **Verify Fanfic_Redirects::init() Called**
   ```php
   // Should be in main plugin file
   if ( class_exists( 'Fanfic_Redirects' ) ) {
       Fanfic_Redirects::init();
   }
   ```

3. **Check .htaccess/Server Rules**
   - Ensure WordPress rewrite rules not blocked
   - Check server doesn't have conflicting rules

#### Issue 3: Helper Functions Return Empty URLs

**Symptoms:**
- `fanfic_get_dashboard_url()` returns empty string
- Links don't display

**Solutions:**

1. **Check Page IDs Exist**
   ```php
   $page_ids = get_option( 'fanfic_system_page_ids' );
   if ( empty( $page_ids ) ) {
       echo "System pages not created";
   }
   ```

2. **Recreate System Pages**
   - Go to admin
   - Re-run setup wizard OR
   - Click "Rebuild Pages" button

3. **Check Page Permissions**
   - System pages should not be:
     - Deleted
     - Trashed
     - Private (if showing to logged-in users)

#### Issue 4: Redirect Expiry Issues

**Symptoms:**
- Very old redirects still active
- Too many redirects accumulating

**Solutions:**

1. **Check Cleanup Schedule**
   ```php
   // Verify action scheduled
   $next_run = wp_next_scheduled( 'fanfic_cleanup_expired_redirects' );
   if ( ! $next_run ) {
       // Schedule if missing
       wp_schedule_event( time(), 'daily', 'fanfic_cleanup_expired_redirects' );
   }
   ```

2. **Manual Cleanup**
   ```php
   Fanfic_Slug_Tracker::cleanup_expired_redirects();
   ```

3. **Clear All Redirects** (last resort)
   ```php
   Fanfic_Slug_Tracker::clear_all_redirects();
   ```

#### Issue 5: Member Profile Not Working

**Symptoms:**
- User profile links return 404
- `?member=` parameter not working

**Solutions:**

1. **Check Members Page Exists**
   ```php
   $members_url = fanfic_get_page_url( 'members' );
   if ( empty( $members_url ) ) {
       echo "Members page not found";
   }
   ```

2. **Verify Username in URL**
   ```php
   // Make sure using username, not display name
   $user = get_user_by( 'login', 'johndoe' ); // Correct
   $url = fanfic_get_user_profile_url( $user );
   ```

3. **Check Template Handles Query Param**
   ```php
   // In members page template
   $member = isset( $_GET['member'] ) ? sanitize_key( $_GET['member'] ) : '';
   if ( ! empty( $member ) ) {
       $user = get_user_by( 'login', $member );
   }
   ```

### Debug Information for Support

When requesting help, provide:

```php
// Collect debug info
echo "=== Fanfiction Manager Debug Info ===\n\n";

// Base configuration
echo "Base Slug: " . get_option( 'fanfic_base_slug' ) . "\n";

// All system page IDs
echo "System Page IDs:\n";
var_export( get_option( 'fanfic_system_page_ids' ) );

// All system page slugs
echo "\nSystem Page Slugs:\n";
var_export( get_option( 'fanfic_system_page_slugs' ) );

// Active redirects
echo "\nActive Redirects:\n";
var_export( Fanfic_Slug_Tracker::get_redirects() );

// Test URL generation
echo "\nTest URLs:\n";
echo "Dashboard: " . fanfic_get_dashboard_url() . "\n";
echo "Login: " . fanfic_get_login_url() . "\n";

// Rewrite rules status
echo "\nPermalinks: " . get_option( 'permalink_structure' ) . "\n";
```

---

## Quick Reference

### All Modifiable Elements (18 Total)

1. Base Slug
2. Archive Slug
3. Login Slug
4. Register Slug
5. Password Reset Slug
6. Dashboard Slug
7. Create Story Slug
8. Edit Story Slug
9. Edit Chapter Slug
10. Edit Profile Slug
11. Search Slug
12. Members/Profiles Slug
13. Error Page Slug
14. Maintenance Page Slug
15. Dashboard Path (User URLs)
16. User Path (User URLs)
17. Prologue Chapter Type
18. Chapter Chapter Type
19. Epilogue Chapter Type

**Note:** Categories are: Base (1), Chapter Types (3), System Pages (12), Secondary Paths (3)

### All Helper Functions (19 Total)

**URL Generation (13):**
1. `fanfic_get_page_url()` - Core function
2. `fanfic_get_dashboard_url()`
3. `fanfic_get_create_story_url()`
4. `fanfic_get_edit_story_url()`
5. `fanfic_get_edit_chapter_url()`
6. `fanfic_get_edit_profile_url()`
7. `fanfic_get_login_url()`
8. `fanfic_get_register_url()`
9. `fanfic_get_password_reset_url()`
10. `fanfic_get_search_url()`
11. `fanfic_get_error_url()`
12. `fanfic_get_main_url()`
13. `fanfic_get_user_profile_url()` - Special: uses `?member` parameter
14. `fanfic_get_stories_archive_url()`

**User Checks (2):**
15. `fanfic_is_author()`
16. `fanfic_is_moderator()`

**Utility (2):**
17. `fanfic_get_version()`
18. `fanfic_get_table_name()`
19. `fanfic_sanitize_content()`

---

## Additional Resources

- **Main Plugin File:** `/fanfiction-manager.php`
- **Functions File:** `/includes/functions.php`
- **URL Config Class:** `/includes/class-fanfic-url-config.php`
- **Slug Tracker Class:** `/includes/class-fanfic-slug-tracker.php`
- **Redirects Class:** `/includes/class-fanfic-redirects.php`
- **Templates Class:** `/includes/class-fanfic-templates.php`
- **Wizard Class:** `/includes/class-fanfic-wizard.php`

---

**Documentation Complete**

For additional information or updates, refer to the plugin's CLAUDE.md file or the implementation checklist.
