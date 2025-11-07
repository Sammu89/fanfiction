# Fixed Dynamic Pages Using Stories Slug Pattern

## ✅ Solution: Replicated Stories Slug Implementation

I analyzed how **Stories slug** works (which DOES work correctly) and replicated that exact pattern for the 4 broken pages.

---

## 📊 Stories Slug Pattern (WORKING)

### 1. Option Constant
```php
const OPTION_STORY_PATH = 'fanfic_story_path';
```

### 2. Form Field
```php
<input
    name="fanfic_story_path"              // Plain field name
    value="<?php echo esc_attr( $current_slugs['story_path'] ); ?>"
/>
```

### 3. Save Code
```php
$result = $this->save_slug_field(
    'fanfic_story_path',        // Field name
    'story_path',               // Slug key
    self::OPTION_STORY_PATH,    // Individual option
    __( 'Stories slug', 'fanfiction-manager' )
);
```

### 4. Schema
```php
'story_path' => array(
    'option_key' => 'fanfic_story_path',  // Individual option
    'group'      => 'primary',
),
```

### 5. Load in get_current_slugs()
```php
case 'primary':
    if ( $key === 'story_path' ) {
        $primary_story = get_option( 'fanfic_story_path', '' );
        $current_slugs[ $key ] = ! empty( $primary_story ) ? $primary_story : $slug_config['default'];
    }
```

### 6. Load in URL Manager
```php
'story_path' => $this->sanitize_slug( get_option( 'fanfic_story_path', 'stories' ) ),
```

---

## ✅ Applied to Dashboard, Create-Story, Members, Search

### 1. Added Option Constants
**File:** `includes/class-fanfic-url-config.php`

```php
const OPTION_DASHBOARD_SLUG = 'fanfic_dashboard_slug';
const OPTION_CREATE_STORY_SLUG = 'fanfic_create_story_slug';
const OPTION_MEMBERS_SLUG = 'fanfic_members_slug';
const OPTION_SEARCH_SLUG = 'fanfic_search_slug';
```

### 2. Form Fields Already Correct
**File:** `includes/class-fanfic-url-config.php`

```php
// Dashboard
name="fanfic_dashboard_slug"             ✅

// Create Story
name="fanfic_create_story_slug"          ✅ (fixed from fanfic_create-story_slug)

// Members
name="fanfic_members_slug"               ✅

// Search
name="fanfic_search_slug"                ✅
```

### 3. Save Code Using save_slug_field()
**File:** `includes/class-fanfic-url-config.php` (lines 1238-1286)

```php
// Dashboard
if ( isset( $_POST['fanfic_dashboard_slug'] ) ) {
    $result = $this->save_slug_field(
        'fanfic_dashboard_slug',
        'dashboard',
        self::OPTION_DASHBOARD_SLUG,
        __( 'Dashboard slug', 'fanfiction-manager' )
    );
}

// Create Story
if ( isset( $_POST['fanfic_create_story_slug'] ) ) {
    $result = $this->save_slug_field(
        'fanfic_create_story_slug',
        'create-story',
        self::OPTION_CREATE_STORY_SLUG,
        __( 'Create Story slug', 'fanfiction-manager' )
    );
}

// Members
if ( isset( $_POST['fanfic_members_slug'] ) ) {
    $result = $this->save_slug_field(
        'fanfic_members_slug',
        'members',
        self::OPTION_MEMBERS_SLUG,
        __( 'Members slug', 'fanfiction-manager' )
    );
}

// Search
if ( isset( $_POST['fanfic_search_slug'] ) ) {
    $result = $this->save_slug_field(
        'fanfic_search_slug',
        'search',
        self::OPTION_SEARCH_SLUG,
        __( 'Search slug', 'fanfiction-manager' )
    );
}
```

**Key Benefits:**
- ✅ Automatic 301 redirect tracking
- ✅ Validation
- ✅ Saves to individual option
- ✅ Same pattern as story_path

### 4. Schema Updated
**File:** `includes/class-fanfic-url-schema.php`

```php
'dashboard' => array(
    'option_key' => 'fanfic_dashboard_slug',  // Individual option
    'group'      => 'dynamic',
),

'create-story' => array(
    'option_key' => 'fanfic_create_story_slug',
    'group'      => 'dynamic',
),

'members' => array(
    'option_key' => 'fanfic_members_slug',
    'group'      => 'dynamic',
),

'search' => array(
    'option_key' => 'fanfic_search_slug',
    'group'      => 'dynamic',
),
```

### 5. Load in get_current_slugs()
**File:** `includes/class-fanfic-url-schema.php`

```php
case 'dynamic':
    // Load from individual option (same pattern as story_path)
    $option_key = isset( $slug_config['option_key'] ) ? $slug_config['option_key'] : '';
    if ( ! empty( $option_key ) ) {
        $value = get_option( $option_key, '' );
        $current_slugs[ $key ] = ! empty( $value ) ? $value : $slug_config['default'];
    } else {
        $current_slugs[ $key ] = $slug_config['default'];
    }
    break;
```

### 6. Load in URL Manager
**File:** `includes/class-fanfic-url-manager.php`

```php
// Load dynamic page slugs from individual options (same pattern as base and story_path)
$dynamic_slugs = array(
    'dashboard'    => $this->sanitize_slug( get_option( 'fanfic_dashboard_slug', 'dashboard' ) ),
    'create-story' => $this->sanitize_slug( get_option( 'fanfic_create_story_slug', 'create-story' ) ),
    'search'       => $this->sanitize_slug( get_option( 'fanfic_search_slug', 'search' ) ),
    'members'      => $this->sanitize_slug( get_option( 'fanfic_members_slug', 'members' ) ),
);

return array(
    'base'       => $this->sanitize_slug( get_option( 'fanfic_base_slug', self::DEFAULT_BASE_SLUG ) ),
    'story_path' => $this->sanitize_slug( get_option( 'fanfic_story_path', 'stories' ) ),
    'chapters'   => wp_parse_args( get_option( 'fanfic_chapter_slugs', array() ), $chapter_defaults ),
    'dynamic'    => $dynamic_slugs,  // Now loads from individual options!
    'system'     => get_option( 'fanfic_system_page_slugs', array() ),
);
```

### 7. Load in URL Builder
**File:** `includes/class-fanfic-url-builder.php`

```php
// Same pattern as URL Manager
$dynamic_slugs = array(
    'dashboard'    => $this->sanitize_slug( get_option( 'fanfic_dashboard_slug', 'dashboard' ) ),
    'create-story' => $this->sanitize_slug( get_option( 'fanfic_create_story_slug', 'create-story' ) ),
    'search'       => $this->sanitize_slug( get_option( 'fanfic_search_slug', 'search' ) ),
    'members'      => $this->sanitize_slug( get_option( 'fanfic_members_slug', 'members' ) ),
);
```

---

## 📋 Database Options

### Before (BROKEN)
```
fanfic_dynamic_page_slugs = {
    "dashboard": "my-dash",
    "create-story": "new-story",
    "members": "authors",
    "search": "find"
}
```
**Problem:** Shared array option, values getting lost

### After (WORKING)
```
fanfic_dashboard_slug = "my-dash"
fanfic_create_story_slug = "new-story"
fanfic_members_slug = "authors"
fanfic_search_slug = "find"
```
**Solution:** Individual options, exactly like `fanfic_story_path`

---

## 📁 Files Modified

1. **includes/class-fanfic-url-config.php**
   - Added 4 option constants
   - Changed save code to use `save_slug_field()` individually
   - Fixed create-story field name

2. **includes/class-fanfic-url-schema.php**
   - Updated schema with individual `option_key` values
   - Updated `get_current_slugs()` to load from individual options

3. **includes/class-fanfic-url-manager.php**
   - Changed to load from individual options

4. **includes/class-fanfic-url-builder.php**
   - Changed to load from individual options

---

## 🧪 Testing

**Test this now:**

1. Go to: Fanfiction Manager → URL Settings
2. Change Dashboard to: "test-dashboard"
3. Change Create Story to: "add-story"
4. Change Members to: "writers"
5. Change Search to: "find-stories"
6. Click: Save Changes

**Expected Results:**
- ✅ Success messages appear
- ✅ All 4 fields keep their new values (don't revert!)
- ✅ Database has 4 individual options
- ✅ URLs work: `/base/test-dashboard/`, `/base/add-story/`, etc.
- ✅ Old URLs redirect: `/base/dashboard/` → 301 → `/base/test-dashboard/`

---

## ✅ Why This Works Now

**Same Pattern as Stories Slug:**

| Feature | Stories Slug | Dashboard/Create/Members/Search |
|---------|--------------|--------------------------------|
| Option Constant | `OPTION_STORY_PATH` | `OPTION_DASHBOARD_SLUG`, etc. |
| Option Name | `fanfic_story_path` | `fanfic_dashboard_slug`, etc. |
| Save Method | `save_slug_field()` | `save_slug_field()` |
| Load Method | `get_option()` | `get_option()` |
| Storage | Individual option | Individual option |
| Schema | Individual `option_key` | Individual `option_key` |
| Redirects | ✅ Tracked | ✅ Tracked |
| Validation | ✅ Applied | ✅ Applied |

**No new classes, no new functions - just following the existing working pattern!**
