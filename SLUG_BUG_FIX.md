# Dashboard & Search Slug Bug - Root Cause & Fix

## 🐛 The Problem

Dashboard and Search slug changes don't stick, and URL previews don't work for these fields.

---

## 🔍 Root Cause Analysis

### The Architectural Flaw

Dashboard and Search were **orphaned slugs** - they existed in a limbo state between different parts of the URL management system:

```
┌────────────────────────────────────────────────────────────────┐
│ WHAT WAS BROKEN                                                │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│ 1. SCHEMA (class-fanfic-url-schema.php)                       │
│    ❌ Dashboard and Search were NOT defined                   │
│    ✅ All other slugs were defined                            │
│                                                                │
│ 2. SAVING (class-fanfic-url-config.php lines 1211-1259)      │
│    ✅ Dashboard and Search saved to fanfic_dynamic_page_slugs │
│    ✅ Saved correctly when form submitted                     │
│                                                                │
│ 3. LOADING (class-fanfic-url-schema.php get_current_slugs())  │
│    ❌ Only loaded slugs defined in schema                     │
│    ❌ Dashboard/Search not in schema → not loaded             │
│    ❌ Form always showed default values                       │
│                                                                │
│ 4. JAVASCRIPT (class-fanfic-url-config.php lines 936-943)    │
│    ❌ currentSlugs didn't include dashboard/search            │
│    ❌ URL preview couldn't update                             │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

### The Data Flow (Before Fix)

```
USER CHANGES SLUG
       ↓
Form submits: fanfic_dashboard_slug = "test-dash"
       ↓
Save code: Correctly saves to fanfic_dynamic_page_slugs['dashboard'] ✅
       ↓
Database: {"dashboard":"test-dash"} ✅
       ↓
Form reloads: Calls Fanfic_URL_Schema::get_current_slugs()
       ↓
get_current_slugs(): Loops through schema entries only
       ↓
Schema has: base, story_path, prologue, chapter, epilogue,
            login, register, password-reset, create-story,
            members, error, maintenance
       ↓
Schema MISSING: dashboard, search ❌
       ↓
Form displays: Default value "dashboard" (not "test-dash") ❌
       ↓
JavaScript: currentSlugs = {...} (no 'dashboard' key) ❌
       ↓
URL Preview: Falls back to default, doesn't update ❌
```

### Why Other Fields Worked

**Story/Chapter Slugs:**
- ✅ Defined in schema with correct option_key
- ✅ Loaded by get_current_slugs()
- ✅ Included in JavaScript config
- ✅ URL previews worked

**Members/Create-Story:**
- ⚠️ Defined in schema BUT with WRONG option_key
- ⚠️ Members had its own issues (saved to dynamic, schema said system)
- ⚠️ Create-story had field name mismatch (array notation vs plain)

---

## ✅ The Fix

### Changes Made

#### 1. **Added Dashboard & Search to URL Schema** (`class-fanfic-url-schema.php`)

```php
// BEFORE: Schema ended with just system pages (login, register, etc.)
// No dashboard or search entries

// AFTER: Added new "DYNAMIC PAGE SLUGS" section
'dashboard' => array(
    'type'             => 'dynamic',
    'default'          => __( 'dashboard', 'fanfiction-manager' ),
    'label'            => __( 'Dashboard', 'fanfiction-manager' ),
    'description'      => __( 'URL for the author dashboard.', 'fanfiction-manager' ),
    'preview_template' => '{home}{base}/{dashboard}/',
    'option_key'       => 'fanfic_dynamic_page_slugs',
    'group'            => 'dynamic',
),

'search' => array(
    'type'             => 'dynamic',
    'default'          => __( 'search', 'fanfiction-manager' ),
    'label'            => __( 'Search Page', 'fanfiction-manager' ),
    'description'      => __( 'URL for the search page.', 'fanfiction-manager' ),
    'preview_template' => '{home}{base}/{search}/',
    'option_key'       => 'fanfic_dynamic_page_slugs',
    'group'            => 'dynamic',
),
```

#### 2. **Updated Members & Create-Story** (`class-fanfic-url-schema.php`)

Changed from `group => 'system'` to `group => 'dynamic'` to match where they're actually saved:

```php
'create-story' => array(
    'type'             => 'dynamic',  // Changed from 'system'
    'option_key'       => 'fanfic_dynamic_page_slugs',  // Changed from 'fanfic_system_page_slugs'
    'group'            => 'dynamic',  // Changed from 'system'
),

'members' => array(
    'type'             => 'dynamic',  // Changed from 'system'
    'option_key'       => 'fanfic_dynamic_page_slugs',  // Changed from 'fanfic_system_page_slugs'
    'group'            => 'dynamic',  // Changed from 'system'
),
```

#### 3. **Added 'dynamic' Case to get_current_slugs()** (`class-fanfic-url-schema.php`)

```php
// BEFORE: No case for 'dynamic' group
switch ( $group ) {
    case 'primary': ...
    case 'chapters': ...
    case 'secondary': ...
    case 'system': ...
}

// AFTER: Added dynamic group handler
$dynamic_page_slugs = get_option( 'fanfic_dynamic_page_slugs', array() );

switch ( $group ) {
    case 'primary': ...
    case 'chapters': ...
    case 'secondary': ...
    case 'dynamic':
        $current_slugs[ $key ] = isset( $dynamic_page_slugs[ $key ] ) && ! empty( $dynamic_page_slugs[ $key ] )
            ? $dynamic_page_slugs[ $key ]
            : $slug_config['default'];
        break;
    case 'system': ...
}
```

#### 4. **Fixed Create-Story Field Name** (`class-fanfic-url-config.php`)

```php
// BEFORE: Array notation didn't match save code
'name' => 'fanfic_system_page_slugs[create-story]',
'value' => isset( $page_slugs['create-story'] ) ? ...,

// AFTER: Plain field name matches save code
'name' => 'fanfic_create-story_slug',
'value' => isset( $current_slugs['create-story'] ) ? $current_slugs['create-story'] : 'create-story',
```

#### 5. **Updated Docblocks** (`class-fanfic-url-schema.php`)

Added 'dynamic' to method documentation:
```php
@param string $type The type of slugs to retrieve (primary, chapter, secondary, dynamic, system).
@param string $group The group to retrieve (primary, chapters, secondary, dynamic, system).
```

---

## 🎯 The Result

### Data Flow (After Fix)

```
USER CHANGES SLUG
       ↓
Form submits: fanfic_dashboard_slug = "test-dash"
       ↓
Save code: Correctly saves to fanfic_dynamic_page_slugs['dashboard'] ✅
       ↓
Database: {"dashboard":"test-dash"} ✅
       ↓
Form reloads: Calls Fanfic_URL_Schema::get_current_slugs()
       ↓
get_current_slugs(): Loops through schema entries (NOW includes dashboard!)
       ↓
case 'dynamic': Loads from fanfic_dynamic_page_slugs ✅
       ↓
Returns: $current_slugs['dashboard'] = "test-dash" ✅
       ↓
Form displays: "test-dash" in input field ✅
       ↓
JavaScript: currentSlugs = {dashboard: "test-dash", ...} ✅
       ↓
URL Preview: Updates to show /base/test-dash/ ✅
```

### All Dynamic Pages Now Consistent

| Slug | Form Field Name | Saved To | Loaded From | Schema Group |
|------|----------------|----------|-------------|--------------|
| dashboard | `fanfic_dashboard_slug` | `fanfic_dynamic_page_slugs` | `$current_slugs['dashboard']` | dynamic |
| create-story | `fanfic_create-story_slug` | `fanfic_dynamic_page_slugs` | `$current_slugs['create-story']` | dynamic |
| members | `fanfic_members_slug` | `fanfic_dynamic_page_slugs` | `$current_slugs['members']` | dynamic |
| search | `fanfic_search_slug` | `fanfic_dynamic_page_slugs` | `$current_slugs['search']` | dynamic |

---

## 📝 Files Modified

1. **`includes/class-fanfic-url-schema.php`**
   - Added dashboard, search, create-story, members to schema with `group => 'dynamic'`
   - Added `case 'dynamic':` to `get_current_slugs()` method
   - Updated docblocks for `get_slugs_by_type()` and `get_slugs_by_group()`

2. **`includes/class-fanfic-url-config.php`**
   - Fixed create-story field name from array notation to plain field name
   - Fixed create-story value loading to use `$current_slugs`

---

## 🧪 Testing

### Before Fix
- ❌ Change dashboard to "test-dash" → Doesn't stick, reverts to "dashboard"
- ❌ URL preview shows /base/dashboard/ (doesn't update)
- ❌ Database has correct value but form doesn't load it
- ❌ JavaScript console shows `currentSlugs` without dashboard/search keys

### After Fix
- ✅ Change dashboard to "test-dash" → Saves and sticks
- ✅ URL preview updates to /base/test-dash/
- ✅ Database has correct value and form loads it
- ✅ JavaScript console shows `currentSlugs` with all dynamic page keys
- ✅ All four dynamic pages work identically

---

## 💡 Lessons Learned

**The Problem:** Scattered data management across multiple systems without a single source of truth.

**The Solution:** Unified all dynamic pages (dashboard, create-story, members, search) under:
- Single storage location: `fanfic_dynamic_page_slugs`
- Single schema group: `dynamic`
- Consistent field naming: Plain field names (not array notation)
- Consistent value loading: Through `$current_slugs` from schema

**Key Principle:** When a value is saved one way, it must be loaded the same way. The schema must reflect the actual storage mechanism.
