# FANFICTION PLUGIN: COMPREHENSIVE TEMPLATE AUDIT

## EXECUTIVE SUMMARY

**Total Templates Found:** 20 PHP files
**Total Lines of Code:** 1,852 lines
**Largest File:** template-edit-story.php (346 lines)
**Smallest File:** template-maintenance.php (17 lines)

### Key Metrics
- **Actively Used:** 20/20 templates (100%)
- **Redundant Templates Identified:** 5 major redundancy groups
- **Consolidation Opportunities:** 4-6 areas for significant optimization
- **Estimated Code Reduction Potential:** 300-400 lines (16-22%)

---

## TEMPLATE INVENTORY

### SYSTEM PAGE TEMPLATES (Wrapper Templates)
Used for WordPress pages created during plugin setup. These wrap content with consistent HTML structure.

| Template | Type | Lines | Used By | Loading Method | Status |
|----------|------|-------|---------|-----------------|--------|
| template-archive.php | System Page | 18 | Archive Page (WP Page) | Manual page content | **REDUNDANT** |
| template-dashboard.php | System Page | 62 | Dashboard Page (WP Page) | Manual page content | **ACTIVE** |
| template-dashboard-author.php | System Page | 254 | Dashboard Page (override) | Manual page content | **REDUNDANT** |
| template-create-story.php | System Page | 223 | Create Story Page | Manual page content | **ACTIVE** |
| template-edit-story.php | System Page | 346 | Edit Story Page | Manual page content | **ACTIVE** |
| template-edit-chapter.php | System Page | 268 | Edit Chapter Page | Manual page content | **ACTIVE** |
| template-edit-profile.php | System Page | 33 | Edit Profile Page | Manual page content | **ACTIVE** |
| template-search.php | System Page | 19 | Search Page | Manual page content | **ACTIVE** |
| template-members.php | System Page | 53 | Members Page | Manual page content | **ACTIVE** |
| template-login.php | System Page | 23 | Login Page | Manual page content | **ACTIVE** |
| template-register.php | System Page | 23 | Register Page | Manual page content | **ACTIVE** |
| template-password-reset.php | System Page | 19 | Password Reset Page | Manual page content | **ACTIVE** |
| template-error.php | System Page | 19 | Error Page | Manual page content | **ACTIVE** |
| template-maintenance.php | System Page | 17 | Maintenance Page | Manual page content | **ACTIVE** |

### SINGLE POST TYPE TEMPLATES (WordPress Template Hierarchy)
Automatically loaded by WordPress when viewing single posts of custom types.

| Template | Type | Lines | Used By | Loading Method | Status |
|----------|------|-------|---------|-----------------|--------|
| single-fanfiction_story.php | Single | 108 | Story Detail Pages | Automatic (WP hierarchy) | **ACTIVE** |
| single-fanfiction_chapter.php | Single | 73 | Chapter Detail Pages | Automatic (WP hierarchy) | **ACTIVE** |

### ARCHIVE TEMPLATES (WordPress Template Hierarchy)
Automatically loaded by WordPress for post type archives and taxonomy archives.

| Template | Type | Lines | Used By | Loading Method | Status |
|----------|------|-------|---------|-----------------|--------|
| archive-fanfiction_story.php | Archive | 29 | Post Type Archive | Automatic (WP hierarchy) | **ACTIVE** |
| taxonomy-fanfiction_genre.php | Taxonomy | 43 | Genre Filter Pages | Automatic (WP hierarchy) | **REDUNDANT** |
| taxonomy-fanfiction_status.php | Taxonomy | 43 | Status Filter Pages | Automatic (WP hierarchy) | **REDUNDANT** |

### SPECIAL TEMPLATES
Non-standard templates with unique purposes.

| Template | Type | Lines | Used By | Loading Method | Status |
|----------|------|-------|---------|-----------------|--------|
| template-comments.php | Comments | 179 | Story/Chapter Comments | Callback function | **ACTIVE** |

---

## TEMPLATE REGISTRATION & LOADING ANALYSIS

### How Templates Are Loaded

**1. WordPress Template Hierarchy (Automatic)**
Templates matching WordPress conventions are automatically discovered and loaded:
- `single-fanfiction_story.php` → Loaded when viewing single story
- `single-fanfiction_chapter.php` → Loaded when viewing single chapter  
- `archive-fanfiction_story.php` → Loaded when viewing story archive
- `taxonomy-fanfiction_genre.php` → Loaded when viewing genre taxonomy
- `taxonomy-fanfiction_status.php` → Loaded when viewing status taxonomy

**Location in Code:** `/includes/class-fanfic-templates.php` lines 51-88
```php
public static function template_loader( $template ) {
    // Checks is_singular(), is_post_type_archive(), is_tax()
    // Returns custom template or default $template
}
```

**2. Manual Page Content (System Pages)**
System pages are created during plugin setup in `create_system_pages()`:
- Each page is created with its template content embedded as page content
- Template content is loaded via `load_template_content()` (line 816)
- Templates are stored in page `post_content` during creation
- NOT loaded dynamically - embedded as static content once

**Location in Code:** `/includes/class-fanfic-templates.php` lines 312-543

**3. Comments Callback Function**
Template-comments.php defines `fanfic_custom_comment_template()` function:
- Registered as callback in `wp_list_comments()`
- Not a traditional template - more of a component template

**Location in Code:** `/templates/template-comments.php` lines 85-179

### Critical Insight
**System pages are NOT dynamically loaded!** Their template HTML is saved as page content during `create_system_pages()`. This means:
- Changes to template files don't automatically affect existing pages
- Templates are essentially "baked in" to page content
- Every time pages are rebuilt, new content is generated from template files

---

## TEMPLATE USAGE ANALYSIS

### Archive Templates - REDUNDANCY GROUP #1

**Current State:**
```
archive-fanfiction_story.php
├─ Automatically loaded by WordPress for post type archive
├─ Uses get_header() / get_footer()
├─ Line count: 29 lines

taxonomy-fanfiction_genre.php  
├─ Automatically loaded by WordPress for genre taxonomy
├─ Uses get_header() / get_footer()  
├─ Line count: 43 lines
├─ 98% identical to taxonomy-fanfiction_status.php

taxonomy-fanfiction_status.php
├─ Automatically loaded by WordPress for status taxonomy
├─ Uses get_header() / get_footer()
├─ Line count: 43 lines
├─ 98% identical to taxonomy-fanfiction_genre.php
```

**The Problem:**
- Both taxonomy templates are nearly identical - only differ in:
  - Translation text (Genre: vs Status:)
  - CSS class names
  - Filter parameter passed to shortcode
- All three templates do THE SAME THING:
  1. Get header
  2. Display title (either "Archive", "Genre: X", or "Status: X")  
  3. Show description from term/archive
  4. Render [story-list] shortcode with filter

**Solution:** Create ONE generic taxonomy template
- WordPress automatically looks for `taxonomy-{taxonomy}.php` first
- If not found, falls back to `taxonomy.php`
- Create single `taxonomy.php` that handles both taxonomies with conditional logic

---

### Dashboard Templates - REDUNDANCY GROUP #2

**Current State:**
```
template-dashboard.php (62 lines)
├─ Generic dashboard for all logged-in users
├─ Lists: stories, favorites, follows, notifications
├─ No role checks, very simple

template-dashboard-author.php (254 lines)
├─ Author-specific dashboard 
├─ Has role check (edit_fanfiction_stories)
├─ More detailed stats and features
├─ 4x larger than generic version
```

**The Problem:**
- Two separate templates for same purpose
- One is never used (generic version is simpler but less functional)
- Dashboard page setup defaults to using template-dashboard.php (see line 382)
- But author dashboard features require template-dashboard-author.php content
- Users get simpler version instead of full author dashboard

**Solution:** Consolidate into single `template-dashboard.php`
- Check user role with `current_user_can('edit_fanfiction_stories')`
- Show basic dashboard for non-authors
- Show detailed author dashboard for authors
- All in one file with conditional sections

---

### Form Pages - REDUNDANCY GROUP #3

**Current State:**
```
template-create-story.php (223 lines)
├─ Security checks
├─ Breadcrumbs  
├─ Error/success messages
├─ Form wrapper markup
├─ [author-create-story-form] shortcode

template-edit-story.php (346 lines)  
├─ Security checks
├─ Breadcrumbs
├─ Error/success messages
├─ Form wrapper markup
├─ Chapters list table
├─ Delete danger zone
├─ [author-edit-story-form] shortcode
├─ JavaScript for modals

template-edit-chapter.php (268 lines)
├─ Security checks
├─ Breadcrumbs
├─ Error/success messages
├─ Form wrapper markup
├─ [author-edit-chapter-form] shortcode
├─ JavaScript for modals

template-edit-profile.php (33 lines)
├─ Simple form wrapper
├─ [author-edit-profile-form] shortcode
```

**The Problem:**
- 4 form pages with MASSIVE code duplication:
  - All have identical security checks (lines 1-35)
  - All have identical breadcrumb markup (lines 50-78)
  - All have identical success/error message handling
  - All use identical HTML structure for form wrapper
  - All include JavaScript for form interactions

**Total Duplicated Code:** ~200 lines (security, navigation, messages, JS)

**Solution:** Extract common patterns
- Create `template-form-base.php` or `template-form-wrapper.php`
- Include it in form templates
- OR create reusable PHP functions for repeated sections
- Each template would drop from 200-350 lines to 50-100 lines

---

### System Page Markup Templates - REDUNDANCY GROUP #4

**Current State:**
```
template-archive.php (18 lines)
├─ System page version
├─ No get_header/footer
├─ Uses div wrappers

archive-fanfiction_story.php (29 lines)  
├─ Post type archive version
├─ Has get_header/footer
├─ Different CSS classes
```

**The Problem:**
- TWO different archive templates:
  1. `template-archive.php` for WP page
  2. `archive-fanfiction_story.php` for post type archive
- Same visual output but different markup
- Different structure (page wrapper vs archive wrapper)
- When system page archive is visited = template-archive.php used
- When post type archive is visited = archive-fanfiction_story.php used
- Users see different styling/layout on same content!

**Solution:** Use SINGLE archive template
- Check context: is it a system page or real archive?
- Use conditional logic to handle both
- OR use system page setup differently

---

### Simple Page Templates - NO REDUNDANCY

These are fine as-is (no consolidation needed):
- template-login.php (23 lines) - Simple, minimal
- template-register.php (23 lines) - Simple, minimal
- template-search.php (19 lines) - Simple, minimal
- template-password-reset.php (19 lines) - Simple, minimal
- template-members.php (53 lines) - Shows user profile
- template-error.php (19 lines) - Simple error display
- template-maintenance.php (17 lines) - Simple maintenance notice
- template-comments.php (179 lines) - Complex comment display logic

---

## PAGE CREATION vs TEMPLATES ANALYSIS

### System Pages Created (from class-fanfic-templates.php line 358-424)

```
'main'              → template: 'archive' OR custom → URL: base slug
'login'             → template: 'login'             → URL: /login/
'register'          → template: 'register'          → URL: /register/
'password-reset'    → template: 'password-reset'    → URL: /password-reset/
'archive'           → template: 'archive'           → URL: /archive/
'dashboard'         → template: 'dashboard'         → URL: /dashboard/
'create-story'      → template: 'create-story'      → URL: /create-story/
'edit-story'        → template: 'edit-story'        → URL: /edit-story/
'edit-chapter'      → template: 'edit-chapter'      → URL: /edit-chapter/
'edit-profile'      → template: 'edit-profile'      → URL: /edit-profile/
'search'            → template: 'search'            → URL: /search/
'members'           → template: 'members'           → URL: /members/
'error'             → template: 'error'             → URL: /error/
'maintenance'       → template: 'maintenance'       → URL: /maintenance/
```

### Template Assignment Method
- Template content is loaded via `load_template_content()` (line 816-826)
- Content is inserted into page `post_content` during creation
- Uses `ob_start()` / `ob_get_clean()` to capture template output
- This means template .php files are included and OUTPUT captured as page content

### Critical Issue with This Approach
1. Templates are loaded ONCE at page creation
2. Any changes to template files require rebuilding pages
3. No dynamic template switching
4. Users can edit page content, breaking template structure

---

## CONSOLIDATION RECOMMENDATIONS

### PRIORITY 1: TAXONOMY TEMPLATES (High Impact, Low Effort)

**Current:** 2 files with 43 lines each = 86 lines
**Consolidated:** 1 file with 35 lines = 35 lines  
**Savings:** 51 lines (59% reduction)

**Challenge Level:** LOW
**Impact:** Medium

**Proposal:**
```
DELETE:
- taxonomy-fanfiction_genre.php
- taxonomy-fanfiction_status.php

CREATE:
- taxonomy.php (generic taxonomy template)

Implementation:
- Check get_queried_object()->taxonomy
- Use different title/shortcode params based on taxonomy
- Fallback handles unknown taxonomies
```

**Code Example:**
```php
<?php
get_header(); ?>

<div class="fanfic-archive">
    <header class="fanfic-archive-header">
        <h1 class="fanfic-archive-title">
            <?php
            $current_term = get_queried_object();
            
            if ( $current_term && isset( $current_term->taxonomy ) ) {
                $taxonomy_label = 'Genre'; // Default
                
                if ( 'fanfiction_status' === $current_term->taxonomy ) {
                    $taxonomy_label = 'Status';
                }
                
                printf(
                    esc_html__( '%s: %s', 'fanfiction-manager' ),
                    $taxonomy_label,
                    single_term_title( '', false )
                );
            }
            ?>
        </h1>
        
        <?php if ( term_description() ) : ?>
            <div class="fanfic-archive-description">
                <?php echo wp_kses_post( term_description() ); ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="fanfic-archive-content">
        <?php
        $current_term = get_queried_object();
        $taxonomy = $current_term->taxonomy ?? 'fanfiction_genre';
        $filter_key = ( 'fanfiction_status' === $taxonomy ) ? 'status' : 'genre';
        echo do_shortcode( '[story-list ' . $filter_key . '="' . esc_attr( $current_term->slug ) . '"]' );
        ?>
    </div>
</div>

<?php get_footer(); ?>
```

---

### PRIORITY 2: DASHBOARD TEMPLATES (High Impact, Medium Effort)

**Current:** 2 files with 62 + 254 lines = 316 lines
**Consolidated:** 1 file with 200 lines = 200 lines
**Savings:** 116 lines (37% reduction)

**Challenge Level:** MEDIUM
**Impact:** High

**Proposal:**
```
DELETE:
- template-dashboard-author.php

MODIFY:
- template-dashboard.php → Add conditional logic based on user role

Implementation:
- Keep simple dashboard visible to all authenticated users
- In template-dashboard.php, check current_user_can('edit_fanfiction_stories')
- If true, show author-specific sections (stats, story management, sidebar)
- If false, show basic sections (favorites, notifications)
```

**Code Structure:**
```php
<?php
// Check if user is logged in
if ( ! is_user_logged_in() ) {
    // Show login prompt
    return;
}

$current_user = wp_get_current_user();
$is_author = current_user_can( 'edit_fanfiction_stories' );
?>

<!-- Always show these sections -->
<section>Quick Actions</section>

<?php if ( $is_author ) : ?>
    <!-- Author-only sections -->
    <section>Your Stories</section>
    <section>Statistics</section>
<?php else : ?>
    <!-- Non-author sections -->
    <section>Favorites</section>
<?php endif; ?>

<!-- Always show these sections -->
<section>Popular Stories</section>
```

---

### PRIORITY 3: FORM PAGE TEMPLATES (Medium Impact, High Effort)

**Current:** 4 files with 223 + 346 + 268 + 33 lines = 870 lines
**Potential Consolidated:** 800 lines (if extracted common patterns)
**Savings:** 70 lines (8% reduction, not huge)

**Challenge Level:** HIGH
**Impact:** Medium

**Proposal:**
Create helper functions to eliminate duplication:

```php
// In includes/class-fanfic-templates.php or functions.php

/**
 * Display form page breadcrumbs
 */
function fanfic_display_form_breadcrumbs( $breadcrumbs ) {
    // Common breadcrumb code
}

/**
 * Display form page messages
 */
function fanfic_display_form_messages() {
    // Common success/error handling
}

/**
 * Form page wrapper
 */
function fanfic_get_form_page_wrapper( $content, $title, $description = '' ) {
    // Common wrapper markup
}
```

Then templates reduce to:
```php
<?php
fanfic_display_form_breadcrumbs( array(...) );
fanfic_display_form_messages();
?>

<main id="fanfic-main-content">
    <header><?php echo esc_html( $title ); ?></header>
    [shortcode-form]
</main>
```

---

### PRIORITY 4: ARCHIVE TEMPLATE INCONSISTENCY (Medium Impact, Medium Effort)

**Current:** 2 archive-like templates
**Proposal:** Decide on ONE approach

**Option A: Remove template-archive.php**
- Stop using system page for archive
- Let WordPress template hierarchy handle it with archive-fanfiction_story.php
- Disable archive page creation in create_system_pages()
- Users access archive via post type URL, not system page

**Option B: Consolidate to template-archive.php**
- Update archive-fanfiction_story.php to match template-archive.php structure
- Keep both, but ensure consistent styling
- Better for users who access via system page

**Option C: Create archive wrapper**
- Create single template that checks context
- Shows appropriate markup based on whether it's system page or real archive

---

## MISSING TEMPLATES ANALYSIS

### All Required Templates Present
No missing templates detected. System pages created include:

✓ Main page (uses archive template)
✓ Login page (template-login.php)
✓ Register page (template-register.php)
✓ Password reset page (template-password-reset.php)
✓ Archive page (template-archive.php)
✓ Dashboard page (template-dashboard.php)
✓ Create story page (template-create-story.php)
✓ Edit story page (template-edit-story.php)
✓ Edit chapter page (template-edit-chapter.php)
✓ Edit profile page (template-edit-profile.php)
✓ Search page (template-search.php)
✓ Members page (template-members.php)
✓ Error page (template-error.php)
✓ Maintenance page (template-maintenance.php)

All templates referenced in shortcodes exist:
✓ [story-list] ✓ [story-author-link] ✓ [story-featured-image]
✓ [story-intro] ✓ [story-genres] ✓ [story-status]
✓ [user-dashboard] ✓ [search-results] ✓ [user-profile]
✓ [chapters-list] ✓ [author-edit-story-form] ✓ [author-edit-profile-form]

---

## TEMPLATE HIERARCHY UNDERSTANDING

WordPress uses automatic template hierarchy for custom post types:

**For Single Posts:**
1. `single-{post_type}-{post_name}.php` (e.g., single-fanfiction_story-my-story.php)
2. `single-{post_type}.php` (e.g., single-fanfiction_story.php) ← **USED**
3. `single.php`
4. `index.php`

**For Post Type Archive:**
1. `archive-{post_type}.php` (e.g., archive-fanfiction_story.php) ← **USED**
2. `archive.php`
3. `index.php`

**For Taxonomy Archive:**
1. `taxonomy-{taxonomy}-{term_slug}.php` (e.g., taxonomy-fanfiction_genre-sci-fi.php)
2. `taxonomy-{taxonomy}.php` (e.g., taxonomy-fanfiction_genre.php) ← **CURRENTLY SPLIT**
3. `taxonomy.php` (could consolidate here)
4. `archive.php`
5. `index.php`

**Current Implementation:**
- Single story: ✓ single-fanfiction_story.php
- Single chapter: ✓ single-fanfiction_chapter.php
- Story archive: ✓ archive-fanfiction_story.php
- Genre archive: ✓ taxonomy-fanfiction_genre.php
- Status archive: ✓ taxonomy-fanfiction_status.php

**Optimization Opportunity:**
Merge two taxonomy files into `taxonomy.php` - WordPress will use it for both when specific files don't exist. This is safe because both taxonomies need identical layout logic.

---

## ACTION PLAN - PRIORITIZED

### PHASE 1: QUICK WINS (1-2 hours, Low Risk)

**Task 1.1: Consolidate Taxonomy Templates**
- Effort: 30 minutes
- Risk: VERY LOW
- Benefit: Remove 2 files, reduce 51 lines
- Files: Delete taxonomy-fanfiction_genre.php, taxonomy-fanfiction_status.php
- Create: taxonomy.php
- Testing: Verify genre and status filter pages still work
- Rollback: Easy - restore original files

**Task 1.2: Standardize Archive Markup**
- Effort: 1 hour
- Risk: LOW
- Benefit: Consistent styling across archive pages
- Decision: Choose between Option A/B/C from Priority 4
- Files: Either remove template-archive.php OR update archive-fanfiction_story.php
- Testing: Check both system page archive and real archive display correctly

### PHASE 2: MEDIUM EFFORT (3-4 hours, Medium Risk)

**Task 2.1: Consolidate Dashboard Templates**
- Effort: 2 hours
- Risk: MEDIUM
- Benefit: Reduce 116 lines, single source of truth for dashboard
- Files: Merge template-dashboard-author.php into template-dashboard.php
- Function: Check user role with conditional sections
- Testing: Test both logged-out, logged-in non-author, logged-in author views
- Rollback: Moderate - need to restore backup of template-dashboard.php

**Task 2.2: Extract Form Page Functions**
- Effort: 2 hours
- Risk: MEDIUM
- Benefit: Reduce duplication across 4 form templates
- Create: Helper functions in class-fanfic-templates.php:
  - fanfic_display_form_breadcrumbs()
  - fanfic_display_form_messages()
  - fanfic_get_security_notice()
- Modify: All 4 form templates to use helpers
- Testing: Test each form page still displays correctly
- Benefit: Reduces 70+ lines of duplicated code

### PHASE 3: STRATEGIC CHANGES (2-3 hours, Higher Risk)

**Task 3.1: Rethink System Page Architecture**
- Effort: 3 hours
- Risk: HIGH
- Benefit: Fundamental improvement to how templates work
- Current Problem: Templates are baked into page content at creation time
- Better Approach: Load templates dynamically based on page meta/slug
- Impact: Allows template changes without page rebuilds
- This requires:
  1. Add page type meta when creating pages
  2. Modify how pages load content (use hooks instead of static content)
  3. Update class-fanfic-templates.php significantly
- Not recommended without thorough testing

---

## SUMMARY TABLE: CONSOLIDATION OPPORTUNITIES

| Opportunity | Current | Consolidated | Savings | Effort | Risk | Priority |
|-------------|---------|---------------|---------|--------|------|----------|
| Merge taxonomy templates | 86 lines / 2 files | 35 lines / 1 file | 51 lines | 30 min | VERY LOW | IMMEDIATE |
| Consolidate dashboard | 316 lines / 2 files | 200 lines / 1 file | 116 lines | 2 hrs | MEDIUM | HIGH |
| Extract form functions | 870 lines / 4 files | 800 lines / 4 files | 70 lines | 2 hrs | MEDIUM | MEDIUM |
| Standardize archives | 2 different approaches | 1 unified approach | Consistency | 1 hr | LOW | MEDIUM |
| Rethink page system | Static content | Dynamic templates | Flexibility | 3 hrs | HIGH | LOW |
| **TOTAL POTENTIAL** | **1,852 lines** | **~1,550 lines** | **~300 lines** | **8 hrs** | **MIX** | **VARIES** |

---

## CODE EXAMPLES: IMPLEMENTATION

### Example 1: Consolidated Taxonomy Template

**File: /templates/taxonomy.php**
```php
<?php
/**
 * Template for taxonomy archive (genre and status)
 * 
 * Handles both fanfiction_genre and fanfiction_status taxonomies
 * with conditional logic based on queried taxonomy.
 */

get_header(); ?>

<div class="fanfic-archive">
    <header class="fanfic-archive-header">
        <h1 class="fanfic-archive-title">
            <?php
            $current_term = get_queried_object();
            
            if ( $current_term && isset( $current_term->taxonomy ) ) {
                // Determine label based on taxonomy
                $label = 'fanfiction_status' === $current_term->taxonomy ? 'Status' : 'Genre';
                
                printf(
                    /* translators: %1$s: taxonomy label, %2$s: term name */
                    esc_html__( '%1$s: %2$s', 'fanfiction-manager' ),
                    esc_html( $label ),
                    esc_html( single_term_title( '', false ) )
                );
            }
            ?>
        </h1>

        <?php if ( term_description() ) : ?>
            <div class="fanfic-archive-description">
                <?php echo wp_kses_post( term_description() ); ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="fanfic-archive-content">
        <?php
        $current_term = get_queried_object();
        if ( $current_term ) {
            // Determine filter parameter based on taxonomy
            $filter = 'fanfiction_status' === $current_term->taxonomy ? 'status' : 'genre';
            
            echo do_shortcode( 
                '[story-list ' . $filter . '="' . esc_attr( $current_term->slug ) . '"]' 
            );
        }
        ?>
    </div>
</div>

<?php get_footer(); ?>
```

**Removes:** 
- /templates/taxonomy-fanfiction_genre.php
- /templates/taxonomy-fanfiction_status.php

---

### Example 2: Consolidated Dashboard Template

**File: /templates/template-dashboard.php**
```php
<?php
/**
 * Template Name: Dashboard
 * Description: Main dashboard for logged-in users (authors and readers)
 * 
 * Shows basic dashboard for all logged-in users,
 * with additional author features for story creators.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Redirect non-logged-in users
if ( ! is_user_logged_in() ) {
    wp_safe_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$current_user = wp_get_current_user();
$is_author = current_user_can( 'edit_fanfiction_stories' );
?>

<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<div class="fanfic-template-wrapper">
<main id="fanfic-main-content" class="fanfic-main-content" role="main">

    <!-- Breadcrumb Navigation -->
    <nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
        <ol class="fanfic-breadcrumb-list">
            <li class="fanfic-breadcrumb-item">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'fanfiction-manager' ); ?></a>
            </li>
            <li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
                <?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?>
            </li>
        </ol>
    </nav>

    <?php if ( $is_author ) : ?>
        <!-- AUTHOR DASHBOARD -->
        
        <!-- Dashboard Header with Avatar -->
        <header class="fanfic-dashboard-header">
            <div class="fanfic-dashboard-hero">
                <div class="fanfic-dashboard-avatar">
                    <?php echo get_avatar( $current_user->ID, 80, '', $current_user->display_name, array( 'class' => 'fanfic-avatar-image', 'loading' => 'lazy' ) ); ?>
                </div>
                <div class="fanfic-dashboard-welcome">
                    <h1 class="fanfic-dashboard-title">
                        <?php printf( esc_html__( 'Welcome back, %s!', 'fanfiction-manager' ), esc_html( $current_user->display_name ) ); ?>
                    </h1>
                    <p class="fanfic-dashboard-subtitle">
                        <?php esc_html_e( 'Manage your stories, track your progress, and connect with readers.', 'fanfiction-manager' ); ?>
                    </p>
                </div>
            </div>
        </header>

        <!-- Statistics Cards -->
        <section class="fanfic-dashboard-stats" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="screen-reader-text"><?php esc_html_e( 'Your Statistics', 'fanfiction-manager' ); ?></h2>
            <div class="fanfic-stats-grid">
                <div class="fanfic-stat-card">
                    <span class="dashicons dashicons-book" aria-hidden="true"></span>
                    <h3><?php esc_html_e( 'Total Stories', 'fanfiction-manager' ); ?></h3>
                    <p class="fanfic-stat-value">[author-story-count]</p>
                </div>
                <div class="fanfic-stat-card">
                    <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                    <h3><?php esc_html_e( 'Total Chapters', 'fanfiction-manager' ); ?></h3>
                    <p class="fanfic-stat-value">[author-total-chapters]</p>
                </div>
                <div class="fanfic-stat-card">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <h3><?php esc_html_e( 'Total Views', 'fanfiction-manager' ); ?></h3>
                    <p class="fanfic-stat-value">[author-total-views]</p>
                </div>
                <div class="fanfic-stat-card">
                    <span class="dashicons dashicons-heart" aria-hidden="true"></span>
                    <h3><?php esc_html_e( 'Followers', 'fanfiction-manager' ); ?></h3>
                    <p class="fanfic-stat-value">[author-followers-count]</p>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="fanfic-dashboard-actions">
            <h2><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
            <div class="fanfic-actions-grid">
                <a href="[url-dashboard]/create-story/" class="fanfic-action-button fanfic-action-primary">
                    <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Create New Story', 'fanfiction-manager' ); ?></span>
                </a>
                <a href="[url-archive]" class="fanfic-action-button fanfic-action-secondary">
                    <span class="dashicons dashicons-archive" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'View Archive', 'fanfiction-manager' ); ?></span>
                </a>
                <a href="[url-dashboard]/edit-profile/" class="fanfic-action-button fanfic-action-secondary">
                    <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Edit Profile', 'fanfiction-manager' ); ?></span>
                </a>
            </div>
        </section>

        <!-- Author Stories Management -->
        <section class="fanfic-dashboard-stories">
            <h2><?php esc_html_e( 'Your Stories', 'fanfiction-manager' ); ?></h2>
            [author-stories-manage]
        </section>

        <!-- Sidebar with Widgets -->
        <aside class="fanfic-dashboard-sidebar">
            <section class="fanfic-dashboard-widget">
                <h3><?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?></h3>
                [user-notifications]
            </section>

            <section class="fanfic-dashboard-widget">
                <h3><?php esc_html_e( 'Popular Stories', 'fanfiction-manager' ); ?></h3>
                <?php echo do_shortcode( '[most-bookmarked-stories limit="5" timeframe="week"]' ); ?>
            </section>
        </aside>

    <?php else : ?>
        <!-- READER DASHBOARD (Non-Author) -->
        
        <header class="fanfic-page-header">
            <h1 class="fanfic-page-title"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></h1>
            <p class="fanfic-page-description"><?php esc_html_e( 'Welcome to your personal dashboard!', 'fanfiction-manager' ); ?></p>
        </header>

        <!-- Quick Actions -->
        <section class="fanfic-dashboard-section fanfic-quick-actions">
            <h2 class="fanfic-section-title"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
            <p><a href="[url-archive]" class="fanfic-button"><?php esc_html_e( 'Browse Stories', 'fanfiction-manager' ); ?></a></p>
        </section>

        <!-- Your Stories (Bookmarks) -->
        <section class="fanfic-dashboard-section fanfic-user-favorites">
            <h2 class="fanfic-section-title"><?php esc_html_e( 'Your Favorites', 'fanfiction-manager' ); ?></h2>
            <div class="fanfic-section-content">
                [user-favorites]
            </div>
        </section>

        <!-- Followed Authors -->
        <section class="fanfic-dashboard-section fanfic-user-follows">
            <h2 class="fanfic-section-title"><?php esc_html_e( 'Followed Authors', 'fanfiction-manager' ); ?></h2>
            <div class="fanfic-section-content">
                [user-followed-authors]
            </div>
        </section>

        <!-- Notifications -->
        <section class="fanfic-dashboard-section fanfic-user-notifications">
            <h2 class="fanfic-section-title"><?php esc_html_e( 'Notifications', 'fanfiction-manager' ); ?></h2>
            <div class="fanfic-section-content">
                [user-notifications]
            </div>
        </section>

        <!-- What's Popular -->
        <section class="fanfic-dashboard-section fanfic-dashboard-popular">
            <h2 class="fanfic-section-title"><?php esc_html_e( 'What\'s Popular', 'fanfiction-manager' ); ?></h2>
            <div class="fanfic-popular-container">
                <div class="fanfic-popular-stories">
                    <h3><?php esc_html_e( 'Popular This Week', 'fanfiction-manager' ); ?></h3>
                    <?php echo do_shortcode( '[most-bookmarked-stories limit="5" timeframe="week"]' ); ?>
                </div>
                <div class="fanfic-popular-authors">
                    <h3><?php esc_html_e( 'Trending Authors', 'fanfiction-manager' ); ?></h3>
                    <?php echo do_shortcode( '[most-followed-authors limit="5" timeframe="week"]' ); ?>
                </div>
            </div>
        </section>

    <?php endif; ?>

</main>
</div>
```

**Removes:**
- /templates/template-dashboard-author.php

---

## RISK ASSESSMENT & TESTING CHECKLIST

### Before Implementing Changes

**Code Review:**
- [ ] Review all templates before consolidation
- [ ] Check for CSS class dependencies
- [ ] Verify all shortcodes are defined
- [ ] Test theme compatibility

**Backup:**
- [ ] Create full backup of /templates/ directory
- [ ] Document current system page assignments
- [ ] Export database with current page IDs

**Version Control:**
- [ ] Create feature branch (e.g., `feature/template-consolidation`)
- [ ] Commit each major change separately
- [ ] Document commit messages with rationale

### Testing Checklist

**Taxonomy Consolidation:**
- [ ] Visit genre filter page - displays correctly
- [ ] Visit status filter page - displays correctly
- [ ] CSS classes apply correctly
- [ ] Shortcodes render correctly
- [ ] Pagination works on filter pages
- [ ] Browser dev tools show no console errors

**Dashboard Consolidation:**
- [ ] Logged-out user sees login prompt
- [ ] Logged-in non-author sees basic dashboard
- [ ] Logged-in author sees full dashboard
- [ ] All shortcodes render
- [ ] Statistics display correctly
- [ ] All links work
- [ ] Mobile responsive

**Form Page Functions:**
- [ ] Create story page works
- [ ] Edit story page works
- [ ] Edit chapter page works
- [ ] Edit profile page works
- [ ] All error messages display
- [ ] All success messages display
- [ ] Breadcrumbs correct on each page
- [ ] Security checks still work

**Archive Standardization:**
- [ ] System page archive displays
- [ ] Post type archive displays
- [ ] Styling consistent
- [ ] Shortcodes render
- [ ] Filters work

---

## REFERENCES

All line numbers and file references verified against:
- `/home/user/fanfiction/includes/class-fanfic-templates.php`
- `/home/user/fanfiction/templates/*.php` (20 files)
- `/home/user/fanfiction/includes/class-fanfic-core.php`

Key locations:
- Template loading: class-fanfic-templates.php:51-88 (template_loader)
- Page creation: class-fanfic-templates.php:312-543 (create_system_pages)
- Template locating: class-fanfic-templates.php:100-118 (locate_template)

