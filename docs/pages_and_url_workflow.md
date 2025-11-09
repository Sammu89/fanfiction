# Pages and URL Workflow Documentation

**Last Updated:** 2025-11-06
**Plugin Version:** 1.0.0
**Purpose:** Complete reference for all page types, URL structures, and routing logic in Fanfiction Manager

---

## Table of Contents
1. [Page Type Definitions](#page-type-definitions)
2. [Complete Page Inventory](#complete-page-inventory)
3. [URL Structure & Routing](#url-structure--routing)
4. [Slug Management System](#slug-management-system)
5. [Template Loading Workflow](#template-loading-workflow)
6. [Rewrite Rules Reference](#rewrite-rules-reference)
7. [Options Reference](#options-reference)
8. [WordPress Native Features Used](#wordpress-native-features-used)

---

## Page Type Definitions

The plugin uses **two distinct approaches** to handle pages:

### WordPress Pages (Physical Pages in Database)
- Stored in `wp_posts` table with `post_type='page'`
- Have page IDs that can be referenced
- Editable in WordPress admin (Pages menu)
- Content stored in database (can contain shortcodes)
- Page IDs stored in: `fanfic_system_page_ids` option
- Custom slugs stored in: `fanfic_system_page_slugs` option

### Dynamic Pages (URL Routes via Rewrite Rules)
- **NO database entry** in wp_posts
- Created via `add_rewrite_rule()` mapping to query vars
- Not visible in WordPress Pages admin
- Content comes from PHP template files only
- Slugs stored in: `fanfic_dynamic_page_slugs` option
- Template loading handled by `Fanfic_URL_Manager::template_loader()`

---

## Complete Page Inventory

### 1. WordPress Pages (6 Total)

| Page Key | Title | Purpose | Template File | Shortcode-Based |
|----------|-------|---------|---------------|-----------------|
| `main` | Fanfiction | Main landing page (can be archive or custom) | Varies by mode | YES |
| `login` | Login | User authentication | (WordPress page content) | YES |
| `register` | Register | User registration | (WordPress page content) | YES |
| `password-reset` | Password Reset | Password recovery | (WordPress page content) | YES |
| `error` | Error | Generic error display | (WordPress page content) | YES |
| `maintenance` | Maintenance | Site maintenance mode | (WordPress page content) | YES |

**Creation:** Created by `Fanfic_Templates::create_system_pages()` during wizard completion
**Storage:** Page IDs in `fanfic_system_page_ids`, custom slugs in `fanfic_system_page_slugs`

---

### 2. Dynamic Pages (8 Total)

| Page Key | Purpose | URL Pattern | Query Var | Template File |
|----------|---------|-------------|-----------|---------------|
| `dashboard` | User personal dashboard | `/[base]/dashboard/` | `fanfic_page=dashboard` | `template-dashboard.php` |
| `create-story` | Story creation form | `/[base]/create-story/` | `fanfic_page=create-story` | `template-create-story.php` |
| `search` | Story search interface | `/[base]/search/` | `fanfic_page=search` | `template-search.php` |
| `members` | Author directory & profiles | `/[base]/members/` OR `/[base]/members/{username}/` | `fanfic_page=members` + `member_name={username}` | `template-members.php` |
| `edit-story` | Edit story form | `/[base]/stories_slug/{slug}/?action=edit` | `fanfiction_story` + `action=edit` | `template-edit-story.php` |
| `edit-chapter` | Edit chapter form | `/[base]/stories_slug/{slug}/chapter-1/?action=edit` | `fanfiction_chapter` + `action=edit` | `template-edit-chapter.php` |
| `create-chapter` | Create new chapter form | `/[base]/stories_slug/{slug}/?action=create-chapter` | `fanfiction_story` + `action=create-chapter` | `template-create-chapter.php` |
| `edit-profile` | Edit user profile | `/[base]/members/{username}/?action=edit` | `fanfic_page=members` + `member_name` + `action=edit` | `template-edit-profile.php` |

**Creation:** Rewrite rules registered by `Fanfic_URL_Manager::register_dynamic_page_rules()`
**Storage:** Slugs in `fanfic_dynamic_page_slugs` option

---

### 3. Custom Post Types & Archives (WordPress Native)

| Type | Purpose | URL Pattern | Handled By |
|------|---------|-------------|------------|
| **Single Story** | View story details | `/[base]/stories_slug/{story-slug}/` | WordPress + `single-fanfiction_story.php` |
| **Single Chapter** | Read chapter content | `/[base]/stories_slug/{story-slug}/chapter-{number}/` | WordPress + `template-chapter-view.php` |
| **Story Archive** | Browse all stories | `/[base]/stories_slug/` | WordPress + `archive-fanfiction_story.php` |
| **Genre Taxonomy** | Filter by genre | `/[base]/stories_slug/genre/{genre-slug}/` | WordPress native (no custom template) |
| **Status Taxonomy** | Filter by status | `/[base]/stories_slug/status/{status-slug}/` | WordPress native (no custom template) |

**Note:** Taxonomy archives use WordPress default template hierarchy (fallback to `archive-fanfiction_story.php`)

---

## URL Structure & Routing

### Base URL Components

```
https://example.com/[base]/[path]/[slug]/[action]
                     └─┬─┘  └──┬──┘ └──┬──┘ └───┬───┘
                  Base Slug  Path   Content  Query Param
```

### Base Slug
- Option: `fanfic_base_slug`
- Default: `fanfiction`
- User-configurable via wizard or settings
- Example: `fanfiction`, `fic`, `stories`

### Story Path
- Option: `fanfic_story_path`
- Default: `stories`
- Used for story/chapter URLs
- Example: `stories`, `works`, `fics`

### Dynamic Page Slugs
- Option: `fanfic_dynamic_page_slugs`
- Keys: `dashboard`, `create-story`, `search`, `members`
- User-configurable
- Example: `dashboard1`, `criare`, `search`, `members`

---

## Complete URL Examples (With User's Custom Slugs)

**Assuming:**
- Base slug: `base_slug`
- Story path: `stories`
- Dashboard slug: `dashboard1`
- Create-story slug: `criare`

### WordPress Pages
```
/base_slug/                          → Main page (if main_page_mode = 'custom_homepage')
/base_slug/login/                    → Login page
/base_slug/register/                 → Register page
/base_slug/password-reset/           → Password reset
```

### Dynamic Pages
```
/base_slug/dashboard1/               → User dashboard
/base_slug/criare/                   → Create story form
/base_slug/search/                   → Search interface
/base_slug/members/                  → Author directory (uses WordPress native list/query)
/base_slug/members/john/             → John's profile
/base_slug/members/john/?action=edit → Edit John's profile
```

### Stories & Chapters
```
/base_slug/stories_slug/                  → Story archive
/base_slug/stories_slug/my-story/         → View story
/base_slug/stories_slug/my-story/?action=edit → Edit story
/base_slug/stories_slug/my-story/chapter-1/   → Read chapter
/base_slug/stories_slug/my-story/chapter-1/?action=edit → Edit chapter
/base_slug/stories_slug/my-story/?action=create-chapter → Create new chapter
```

### Taxonomies (WordPress Native)
```
/base_slug/stories_slug/genre/romance/    → Genre archive
/base_slug/stories_slug/status/ongoing/   → Status archive
```

---

## Slug Management System

### Single Source of Truth Architecture

To eliminate confusion and sync issues, slugs are stored in **distinct, non-overlapping options**:

| Option Name | Stores | Used For |
|------------|--------|----------|
| `fanfic_base_slug` | String: Base URL path | All URLs start with this |
| `fanfic_story_path` | String: Story subdirectory | Story/chapter URLs |
| `fanfic_chapter_slugs` | Array: `prologue`, `chapter`, `epilogue` | Chapter type URLs |
| `fanfic_dynamic_page_slugs` | Array: `dashboard`, `create-story`, `search`, `members` | Dynamic page URLs |
| `fanfic_system_page_ids` | Array: Page keys → WordPress page IDs | Physical page references |
| `fanfic_system_page_slugs` | Array: Page keys → Custom slugs | WordPress page slug overrides |

### ❌ Deprecated Options (To Be Removed)
- `fanfic_secondary_paths` - Redundant with `fanfic_dynamic_page_slugs`

---

## Slug Update Workflow

When user changes a slug in admin settings:

```
1. User submits form (Fanfic_URL_Config::save_url_config())
   ↓
2. Validate slug format and uniqueness
   ↓
3. Update appropriate option in database
   - For dynamic pages: update_option('fanfic_dynamic_page_slugs', $slugs)
   - For WordPress pages: update page slug + update_option('fanfic_system_page_slugs')
   ↓
4. Flush URL Manager cache
   - Fanfic_URL_Manager::flush_cache()
   - Reloads $this->slugs from database
   ↓
5. Re-register ALL rewrite rules
   - Fanfic_URL_Manager::register_rewrite_rules()
   - Uses fresh cached slugs
   ↓
6. Flush WordPress rewrite rules
   - flush_rewrite_rules()
   - Regenerates .htaccess / nginx rules
   ↓
7. Track old slug for 301 redirects (optional)
   - Fanfic_Slug_Tracker::add_manual_redirect($old, $new)
```

### Critical: Cache Flush Timing

**MUST HAPPEN IMMEDIATELY** - No transient delays:
```php
// ✅ CORRECT
update_option('fanfic_dynamic_page_slugs', $new_slugs);
Fanfic_URL_Manager::get_instance()->flush_cache();
flush_rewrite_rules();

// ❌ WRONG
update_option('fanfic_dynamic_page_slugs', $new_slugs);
set_transient('fanfic_flush_rewrite_rules', 1, 60); // Delayed flush = old slugs cached
```

---

## Template Loading Workflow

### Priority-Based Template Loader Chain

WordPress processes template loaders in priority order:

```
PRIORITY 10: Fanfic_Templates::template_loader()
    ↓ Handles: Custom Post Types, Taxonomies, Main Page

PRIORITY 99: Fanfic_URL_Manager::template_loader()
    ↓ Handles: Dynamic Pages

If no custom template: WordPress default hierarchy
```

### Template Selection Decision Tree

```
INCOMING REQUEST
│
├─ Has fanfic_page query var? (e.g., fanfic_page=dashboard)
│  ├─ YES: Load dynamic page template
│  │  ├─ dashboard → template-dashboard.php
│  │  ├─ create-story → template-create-story.php
│  │  ├─ search → template-search.php
│  │  └─ members → template-members.php
│  │
│  └─ NO: Continue to CPT checks
│
├─ is_singular('fanfiction_story')?
│  └─ YES: single-fanfiction_story.php
│
├─ is_singular('fanfiction_chapter')?
│  └─ YES: template-chapter-view.php
│
├─ is_post_type_archive('fanfiction_story')?
│  └─ YES: archive-fanfiction_story.php
│
├─ is_tax('fanfiction_genre') OR is_tax('fanfiction_status')?
│  └─ YES: WordPress native taxonomy template (archive.php fallback)
│
└─ WordPress page?
   └─ YES: page.php with shortcode content from database
```

### Template File Locations

**Priority 1: Theme Override**
```
/wp-content/themes/{active-theme}/fanfiction-manager/{template-name}.php
/wp-content/themes/{active-theme}/{template-name}.php
```

**Priority 2: Plugin Templates**
```
/wp-content/plugins/fanfiction-manager/templates/{template-name}.php
```

---

## Rewrite Rules Reference

### Rule Registration

All rewrite rules are registered by `Fanfic_URL_Manager` on `init` hook (priority 20).

### Dynamic Page Rules

**Dashboard:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $dynamic['dashboard'] . '/?$',
    'index.php?fanfic_page=dashboard',
    'top'
);
```

**Create Story:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $dynamic['create-story'] . '/?$',
    'index.php?fanfic_page=create-story',
    'top'
);
```

**Search:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $dynamic['search'] . '/?$',
    'index.php?fanfic_page=search',
    'top'
);
```

**Members (List & Profile):**
```php
// Members list
add_rewrite_rule(
    '^' . $base . '/' . $dynamic['members'] . '/?$',
    'index.php?fanfic_page=members',
    'top'
);

// Individual member profile
add_rewrite_rule(
    '^' . $base . '/' . $dynamic['members'] . '/([^/]+)/?$',
    'index.php?fanfic_page=members&member_name=$matches[1]',
    'top'
);
```

### Story & Chapter Rules

**Story:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $story_path . '/([^/]+)/?$',
    'index.php?fanfiction_story=$matches[1]&post_type=fanfiction_story',
    'top'
);
```

**Regular Chapter:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $story_path . '/([^/]+)/' . $chapters['chapter'] . '-([0-9]+)/?$',
    'index.php?fanfiction_chapter=$matches[1]&chapter_number=$matches[2]',
    'top'
);
```

**Prologue:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $story_path . '/([^/]+)/' . $chapters['prologue'] . '/?$',
    'index.php?fanfiction_chapter=$matches[1]&chapter_type=prologue',
    'top'
);
```

**Epilogue:**
```php
add_rewrite_rule(
    '^' . $base . '/' . $story_path . '/([^/]+)/' . $chapters['epilogue'] . '/?$',
    'index.php?fanfiction_chapter=$matches[1]&chapter_type=epilogue',
    'top'
);
```

### Query Variables Registered

```php
// Dynamic pages
fanfic_page         // Values: dashboard, create-story, search, members
member_name         // Username for member profile

// Stories & chapters
fanfiction_story    // Story slug
fanfiction_chapter  // Chapter slug (actually story slug + chapter info)
chapter_number      // Integer chapter number
chapter_type        // Values: prologue, epilogue

// Edit actions (query parameters, not rewrite rules)
action              // Values: edit, create-chapter
```

---

## Options Reference

### Core URL Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `fanfic_base_slug` | string | `'fanfiction'` | Base path for all plugin URLs |
| `fanfic_story_path` | string | `'stories'` | Subdirectory for story/chapter URLs |
| `fanfic_chapter_slugs` | array | `['chapter'=>'chapter', 'prologue'=>'prologue', 'epilogue'=>'epilogue']` | Chapter type URL slugs |
| `fanfic_dynamic_page_slugs` | array | `['dashboard'=>'dashboard', 'create-story'=>'create-story', 'search'=>'search', 'members'=>'members']` | Dynamic page URL slugs |
| `fanfic_main_page_mode` | string | `'custom_homepage'` | `'custom_homepage'` or `'stories_homepage'` |

### Page Management Options

| Option | Type | Description |
|--------|------|-------------|
| `fanfic_system_page_ids` | array | Maps page keys to WordPress page IDs (only physical pages) |
| `fanfic_system_page_slugs` | array | Custom slugs for system pages (both physical and dynamic) |

### Example Values

```php
// Base URL configuration
get_option('fanfic_base_slug') = 'base_slug';
get_option('fanfic_story_path') = 'stories';

// Dynamic page slugs
get_option('fanfic_dynamic_page_slugs') = [
    'dashboard' => 'dashboard1',
    'create-story' => 'criare',
    'search' => 'search',
    'members' => 'members',
];

// WordPress page IDs
get_option('fanfic_system_page_ids') = [
    'main' => 123,
    'login' => 124,
    'register' => 125,
    'password-reset' => 126,
    'error' => 127,
    'maintenance' => 128,
];
```

---

## WordPress Native Features Used

The plugin leverages WordPress core functionality wherever possible:

### 1. Custom Post Types
- Uses `register_post_type()` for stories and chapters
- Hierarchical relationship (chapters are children of stories)
- Supports all WordPress post features (revisions, trash, etc.)

### 2. Taxonomies
- Uses `register_taxonomy()` for genres and statuses
- WordPress handles archive pages natively
- Uses default template hierarchy (`taxonomy.php` → `archive.php`)

### 3. Rewrite API
- Uses `add_rewrite_rule()` for URL routing
- Uses `add_query_vars()` for custom variables
- Uses `flush_rewrite_rules()` for regeneration

### 4. Template Hierarchy
- Respects theme template overrides
- Uses `template_include` filter for custom templates
- Falls back to WordPress defaults when no custom template exists

### 5. User System
- Custom user roles built on WordPress roles API
- Uses `current_user_can()` for permission checks
- Uses WordPress login/logout functions

### 6. Menu System
- Creates menus via `wp_create_nav_menu()`
- Uses `wp_update_nav_menu_item()` for menu items
- Conditional menu items filtered by user login status

### 7. Permalink System
- Respects WordPress permalink structure
- Uses `get_permalink()` filtered by plugin
- Maintains compatibility with permalink settings

### 8. Template Parts
- `get_header()` / `get_footer()` for theme integration
- `do_shortcode()` for processing shortcode content
- Theme compatibility maintained throughout

---

## Members Page: WordPress Native Author Listing

### Directory View (`/members/`)

The members list should use WordPress native functionality:

```php
// In template-members.php when member_name is empty
$args = array(
    'role__in' => array('fanfiction_author', 'fanfiction_moderator', 'administrator'),
    'orderby'  => 'registered',
    'order'    => 'DESC',
);

$user_query = new WP_User_Query($args);
```

This approach:
- ✅ Uses WordPress `WP_User_Query` class
- ✅ No custom database tables needed
- ✅ Respects WordPress user meta
- ✅ Can be extended with custom sorting/filtering
- ✅ Pagination via WordPress standards

### Profile View (`/members/{username}/`)

When `member_name` query var is present:
- Load user by `get_user_by('login', $member_name)`
- Display profile using template from `fanfic_profile_view_template` option
- Process shortcodes with `do_shortcode()`

---

## Edit Pages: Query Parameter Approach

Edit functionality does NOT use separate pages. Instead, it uses query parameters on existing URLs:

### Edit Story
- URL: `/stories_slug/my-story/?action=edit`
- Detection: `is_singular('fanfiction_story') && $_GET['action'] === 'edit'`
- Template: `template-edit-story.php` (conditionally loaded)

### Edit Chapter
- URL: `/stories_slug/my-story/chapter-1/?action=edit`
- Detection: `is_singular('fanfiction_chapter') && $_GET['action'] === 'edit'`
- Template: `template-edit-chapter.php` (conditionally loaded)

### Create Chapter
- URL: `/stories_slug/my-story/?action=create-chapter`
- Detection: `is_singular('fanfiction_story') && $_GET['action'] === 'create-chapter'`
- Template: `template-create-chapter.php` (conditionally loaded)

### Edit Profile
- URL: `/members/username/?action=edit`
- Detection: `fanfic_page=members && member_name && $_GET['action'] === 'edit'`
- Template: `template-edit-profile.php` (conditionally loaded)

**Benefits:**
- ✅ No additional rewrite rules needed
- ✅ Inherits permissions from parent page
- ✅ Cleaner URL structure
- ✅ No duplicate content issues

---

## Troubleshooting Common Issues

### Issue: Dynamic page returns 404

**Checklist:**
1. Verify slug in `fanfic_dynamic_page_slugs` option
2. Check query var appears in request: `get_query_var('fanfic_page')`
3. Flush rewrite rules: Settings > Permalinks > Save Changes
4. Check URL Manager cache is fresh: `Fanfic_URL_Manager::get_instance()->flush_cache()`
5. Verify rewrite rule exists: `global $wp_rewrite; print_r($wp_rewrite->rules);`

### Issue: Slug change doesn't take effect

**Solution:**
1. Ensure cache is flushed IMMEDIATELY after option update
2. Do NOT use transient-based delayed flush
3. Call `Fanfic_URL_Manager::flush_cache()` before `flush_rewrite_rules()`

### Issue: Template not loading

**Checklist:**
1. Verify query var is set correctly
2. Check template file exists in `templates/` directory
3. Verify template_map in `Fanfic_URL_Manager::template_loader()` includes the page
4. Check for theme override in `theme/fanfiction-manager/`

---

## Future Improvements

### Performance Optimization
- Consider object caching for slug retrieval
- Implement transient caching for expensive queries
- Lazy load templates when needed

### Code Cleanup
- Remove deprecated `Fanfic_Dynamic_Pages` class
- Eliminate redundant rewrite rule registrations
- Consolidate slug storage (single source per type)

### WordPress Standards
- Follow WordPress VIP coding standards
- Use WordPress native features wherever possible
- Maintain backward compatibility for major version updates

---

## Appendix: File Locations

### Core Files
- **URL Manager:** `includes/class-fanfic-url-manager.php`
- **Template Loader:** `includes/class-fanfic-templates.php`
- **URL Config (Admin):** `includes/class-fanfic-url-config.php`
- **Wizard:** `includes/class-fanfic-wizard.php`

### Template Files
- **Directory:** `templates/`
- **Dynamic Pages:** `template-{page-key}.php`
- **Custom Post Types:** `single-fanfiction_{type}.php`, `archive-fanfiction_{type}.php`

### Options Storage
- All options use `wp_options` table
- Prefix: `fanfic_`
- Autoload: Yes (for frequently accessed options)

**End of Documentation**
