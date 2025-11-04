# Fanfiction Manager Plugin - Phases 12 & 13 Implementation Plan

**Document Created:** October 29, 2025
**Status:** Ready for Implementation
**Total Phases:** 2 (Phase 12: Additional Features, Phase 13: Accessibility & SEO)
**Overall Estimated Timeline:** 5-7 weeks (approximately 8-12 hours per week)

---

## EXECUTIVE SUMMARY

### Current Status
- **Phase 1-11:** 100% Complete ✅
- **Phase 12:** 50% Complete (3 of 6 features done)
  - ✅ Story Validation Logic
  - ✅ View Tracking (session-based)
  - ✅ Custom CSS Textarea
  - 🔧 Author Demotion Cron (TO DO)
  - 🔧 Custom Widgets (TO DO)
  - 🔧 Export/Import CSV (TO DO)
- **Phase 13:** 0% Complete (6 features to implement)
  - 🔧 ARIA Roles & Labels
  - 🔧 Keyboard Navigation
  - 🔧 Meta Tags (Basic, OG, Twitter, Schema.org)
  - 🔧 Canonical Tags
  - 🔧 Screen Reader Compatibility
  - 🔧 Color Contrast & Responsive Design

### High-Level Breakdown

**Phase 12 - Additional Features (Remaining 3 items)**
- 3 cron/automation features
- 2 user-facing features (widgets)
- 1 admin feature (export/import)
- **Estimated Effort:** 14-21 hours
- **Priority:** MEDIUM

**Phase 13 - Accessibility & SEO (6 items)**
- 2 new classes to create
- 15+ files to update
- Comprehensive ARIA implementation
- Complete SEO meta tag system
- **Estimated Effort:** 20-30 hours
- **Priority:** HIGH (critical for compliance)

---

## PHASE 12: ADDITIONAL FEATURES - DETAILED BREAKDOWN

### Overview
Implement the 3 remaining features to complete Phase 12. These features enhance functionality but are not critical to core operations.

### Feature 1: Author Demotion Cron Job

**Purpose:** Automatically demote authors with zero published stories to Reader role.

**Complexity:** MEDIUM | **Estimated Time:** 2-3 hours | **Files:** 3 (1 new, 2 modified)

**What It Does:**
1. Runs daily at admin-configured time (from Settings)
2. Checks all users with "Fanfiction Author" role
3. Counts their published stories (drafts don't count)
4. If count = 0, demotes user to "Fanfic Reader" role
5. Preserves their stories (doesn't delete content)
6. User auto-promotes back to author when they publish a valid story

**Technical Details:**
- Uses WordPress WP-Cron system (already used in Phase 9 for emails)
- Reads scheduled time from `fanfic_settings` option
- Batch processes up to 100 users per cron run (prevents timeouts)
- Logs demotion events for admin audit trail
- Notifies affected users via email (optional, Phase 9 system)

**Files to Create:**
- `includes/class-fanfic-author-demotion.php` (250-350 lines)
  - Methods: `init()`, `schedule_cron()`, `run_demotion()`, `count_published_stories()`, `get_scheduled_hour()`
  - Hooks: `wp_loaded` (for scheduling), `fanfic_daily_demotion` (custom cron hook)

**Files to Modify:**
- `includes/class-fanfic-core.php` (add initialization)
- `includes/class-fanfic-validation.php` (integrate auto-promotion logic if not present)

**Dependencies:**
- ✅ WP-Cron system (already available)
- ✅ User roles system (Phase 1)
- ✅ Settings page (Phase 2)

**Database Changes:** None

**Security Measures:**
- No direct user input (only reads configuration)
- No database manipulation from user input
- Capability checks on cron scheduling

**Testing Checklist:**
- [ ] Cron job executes at configured time
- [ ] Authors with 0 published stories are demoted
- [ ] Authors with draft stories (but 0 published) are demoted
- [ ] Authors with ≥1 published story are NOT demoted
- [ ] Email notification sent (if enabled)
- [ ] Manual "Run Cron Now" button works
- [ ] Author auto-promotes on story publish

---

### Feature 2: Custom Widgets

**Purpose:** Display fanfiction content in WordPress widget areas (sidebars).

**Complexity:** MEDIUM | **Estimated Time:** 4-6 hours | **Files:** 6 (5 new, 1 modified)

**What It Does:**
1. Creates 4 WordPress-standard widgets:
   - Recent Stories Widget (configurable count, shows title/author/date)
   - Featured Stories Widget (shows manually featured stories)
   - Most Bookmarked Stories Widget (shows bookmark counts)
   - Top Authors Widget (shows follower counts)
2. Each widget has admin configuration form
3. Widgets use transient caching (5-30 minute TTL)
4. Responsive design, WCAG AA accessible
5. Mobile-optimized with touch-friendly buttons

**Technical Details:**
- Extends WordPress `WP_Widget` class (standard widget API)
- Registers on `widgets_init` hook
- Each widget is independent and modular
- Uses existing `Fanfic_Cache` system (Phase 11)
- Queries use existing bookmark/follow/view functions
- CSS styling integrated with `fanfiction-frontend.css`

**Files to Create:**
- `includes/class-fanfic-widgets.php` (300-400 lines)
  - Registration manager for all widgets
  - Initialization on `widgets_init` hook

- `includes/widgets/class-fanfic-widget-recent-stories.php` (200-250 lines)
  - Extends `WP_Widget`
  - Methods: `widget()`, `form()`, `update()`
  - Config: number of stories, show date/author toggle

- `includes/widgets/class-fanfic-widget-featured-stories.php` (200-250 lines)
  - Similar to recent stories
  - Uses featured stories from Settings

- `includes/widgets/class-fanfic-widget-most-bookmarked.php` (200-250 lines)
  - Query function: uses `Fanfic_Bookmarks::get_most_bookmarked()`
  - Config: number of stories, minimum bookmark threshold

- `includes/widgets/class-fanfic-widget-top-authors.php` (200-250 lines)
  - Query function: uses `Fanfic_Follows::get_top_authors()`
  - Config: number of authors, minimum follower threshold

**Files to Modify:**
- `includes/class-fanfic-core.php` (add widget class initialization ~5 lines)

**CSS Additions:**
- Add to `assets/css/fanfiction-frontend.css` (~150-200 lines)
- BEM naming: `.fanfic-widget`, `.fanfic-widget__item`, etc.
- Responsive breakpoints for mobile (min touch target 44x44px)
- Focus indicators for accessibility

**Dependencies:**
- ✅ WordPress Widget API (built-in)
- ✅ `Fanfic_Cache` class (Phase 11)
- ✅ `Fanfic_Bookmarks`, `Fanfic_Follows`, `Fanfic_Views` classes (Phase 8-9)

**Database Changes:** None (uses existing tables)

**Security Measures:**
- Nonce verification in form handlers
- Sanitization of all option values (absint for counts/thresholds)
- Escaping of all output (esc_html, esc_url)
- Capability checks: None required (widgets are public-facing)

**Caching Strategy:**
- Recent Stories: 10-minute transient (frequent changes)
- Featured Stories: 30-minute transient (manual updates)
- Most Bookmarked: 5-minute transient (reactive to bookmarks)
- Top Authors: 15-minute transient (changes frequently)

**Testing Checklist:**
- [ ] All 4 widgets appear in Widgets admin panel
- [ ] Each widget can be added to widget area
- [ ] Configuration form displays properly
- [ ] Options save correctly
- [ ] Widget displays content correctly
- [ ] Widget title/count settings work
- [ ] Caching works (verify transients created)
- [ ] Cache clears on events (story publish, bookmark added, etc.)
- [ ] Mobile responsive (test on <768px)
- [ ] Accessibility (keyboard nav, ARIA labels, color contrast)
- [ ] Empty state handling (no stories, no authors)

---

### Feature 3: Export/Import (CSV Format)

**Purpose:** Allow admins to bulk export/import stories and taxonomies.

**Complexity:** HIGH | **Estimated Time:** 8-12 hours | **Files:** 5 (3 new, 2 modified)

**What It Does:**
1. **Export Functionality:**
   - Export stories with all metadata (title, author, genres, status, dates, stats)
   - Export chapters with story associations
   - Export taxonomies (terms and hierarchies)
   - CSV format with proper headers
   - Handles special characters (UTF-8)
   - Chunked processing for large datasets (>1000 rows)

2. **Import Functionality:**
   - Upload CSV files with validation
   - Map CSV columns to story/chapter fields
   - Validate data before import (dry-run preview)
   - Handle duplicates (append Roman numerals to titles)
   - Create missing taxonomies automatically
   - Preserve/assign author IDs
   - Show success/error summary after import
   - Cache invalidation after bulk operations

3. **Admin Interface:**
   - New tab in Settings page: "Export/Import"
   - Export buttons (Stories, Chapters, Taxonomies)
   - Import file upload form
   - Preview before import confirmation
   - Progress tracking for large imports
   - Success notification with count summary

**Technical Details:**
- Uses PHP built-in `fgetcsv()` and `fputcsv()` functions
- Admin-only access (requires `manage_options` capability)
- CSV file handling: temporary files, cleanup on completion
- Validation: column headers, data types, foreign key constraints
- Security: nonce verification, file upload validation, sanitization
- Performance: Batch processing (100 rows per iteration), memory management

**Files to Create:**
- `includes/class-fanfic-export.php` (400-500 lines)
  - Methods: `export_stories()`, `export_chapters()`, `export_taxonomies()`
  - CSV file generation with headers
  - Streaming output to client
  - Character encoding handling (UTF-8)

- `includes/class-fanfic-import.php` (600-800 lines)
  - Methods: `import_stories()`, `import_chapters()`, `import_taxonomies()`
  - CSV parsing and validation
  - Duplicate detection
  - Dry-run mode
  - Error collection and reporting
  - Cache invalidation after import

- `includes/admin/class-fanfic-export-import-admin.php` (500-700 lines)
  - Admin page rendering
  - Form handlers for uploads
  - Preview display
  - Import progress tracking
  - Success/error messages

**Files to Modify:**
- `includes/class-fanfic-admin.php` (add menu item or tab, ~20-30 lines)
- `includes/class-fanfic-core.php` (initialize export/import classes, ~5 lines)

**CSV Format Specifications:**

**Stories Export CSV:**
```
ID,Title,Author ID,Author Name,Introduction,Genres,Status,Publication Date,Last Updated,Views,Average Rating,Featured
1,"My Story",2,"John Doe","A great story","Fantasy,Romance","Finished","2025-01-01","2025-10-29",1250,4.5,Yes
```

**Chapters Export CSV:**
```
ID,Story ID,Story Title,Chapter Number,Chapter Type,Title,Content,Publication Date,Views,Average Rating
1,1,"My Story",1,"prologue","Prologue","Once upon a time...","2025-01-01",500,4.8
```

**Taxonomies Export CSV:**
```
Taxonomy,Term ID,Term Name,Slug,Parent ID,Parent Name,Description
fanfiction_genre,1,"Fantasy","fantasy","","","Fictional magical worlds"
fanfiction_status,5,"Finished","finished","","","Story is complete"
```

**CSV Import Specifications:**
- Column headers required (no data type inference)
- Date format: ISO 8601 (YYYY-MM-DD)
- UTF-8 encoding required
- Maximum file size: 10MB (configurable)
- Row limit per batch: 100 (configurable)
- Unknown columns are ignored (no error)
- Missing required columns: Error (prevent import)
- Data validation: Type checking, foreign key constraints

**Dependencies:**
- ✅ Story validation system (Phase 2, for import validation)
- ✅ Taxonomy management (Phase 1)
- ✅ Cache system (Phase 11, for invalidation after import)

**Database Changes:** None (uses existing tables)

**Security Measures:**
- Nonce verification on all forms
- File upload validation (MIME type, extension, size)
- CSV parsing without shell execution (use `fgetcsv()`)
- All imported data sanitized before database insertion
- Capability check: `manage_options` required
- No SQL injection (use prepared statements)
- No path traversal (temporary file handling)
- Dry-run preview before final import
- Audit log: Track import source, timestamp, row count

**Error Handling:**
- Invalid CSV format: Show error, allow file correction
- Missing columns: Prevent import, show required columns
- Duplicate titles: Append Roman numerals (Story 1, Story II, Story III, etc.)
- Invalid author IDs: Use current admin as author (with warning)
- Missing parent stories: Skip chapter, log error
- Encoding issues: Convert to UTF-8 automatically

**Testing Checklist:**
- [ ] Export stories CSV generates valid file
- [ ] Export chapters CSV includes all fields
- [ ] Export taxonomies CSV preserves hierarchy
- [ ] Import accepts valid CSV files
- [ ] Import validates required columns
- [ ] Import handles duplicate titles (appends Roman numerals)
- [ ] Import handles special characters (UTF-8)
- [ ] Import handles missing parent stories (error message)
- [ ] Import caches are invalidated
- [ ] Large file handling (1000+ rows, no timeout)
- [ ] Dry-run preview shows correct data
- [ ] File upload security (no executable files, size limits)
- [ ] Success message shows row count
- [ ] Error messages are clear and actionable
- [ ] Admin audit log created

---

## PHASE 13: ACCESSIBILITY & SEO - DETAILED BREAKDOWN

### Overview
Implement comprehensive WCAG 2.1 AA accessibility compliance and SEO optimization. This is a HIGH priority phase that affects all frontend-facing content.

### Structure
Phase 13 has 6 major areas to implement:
1. ARIA Roles & Labels (interactive components)
2. Keyboard Navigation (tab order, arrow keys)
3. Meta Tags (SEO, social sharing)
4. Canonical Tags (duplicate prevention)
5. Screen Reader Compatibility (semantic HTML)
6. Color Contrast & Responsive Design (visual accessibility)

### Area 1: ARIA Roles & Labels

**Purpose:** Enable screen reader users to understand interactive components and page structure.

**Complexity:** MEDIUM | **Estimated Time:** 6-8 hours | **Files:** 12+ (all shortcodes + templates)

**What It Does:**
1. Adds semantic ARIA roles to navigation, interactive elements, and dynamic content
2. Implements ARIA labels on icon-only buttons
3. Adds `aria-expanded`, `aria-haspopup`, `aria-controls` for dropdowns
4. Implements `aria-required`, `aria-invalid`, `aria-describedby` for forms
5. Adds `aria-live`, `aria-busy` for dynamic content updates
6. Implements `aria-pressed`, `aria-disabled` for toggle buttons

**Implementation Details:**

**Navigation Components:**
```html
<!-- Chapter navigation with ARIA -->
<nav role="navigation" aria-label="Chapter navigation">
    <a href="..." aria-label="Previous chapter">← Previous</a>
    <select aria-label="Jump to chapter">
        <option selected aria-current="page">Chapter 5</option>
    </select>
    <a href="..." aria-label="Next chapter">Next →</a>
</nav>
```

**Interactive Dropdowns:**
```html
<button aria-expanded="false" aria-haspopup="listbox" aria-controls="genre-list">
    Filter by Genre
</button>
<ul id="genre-list" role="listbox" hidden>
    <li role="option">Fantasy</li>
    <li role="option">Romance</li>
</ul>
```

**Form Fields:**
```html
<label for="username">Username <span aria-label="required">*</span></label>
<input id="username" aria-required="true" aria-invalid="false"
       aria-describedby="username-error">
<div id="username-error" role="alert" aria-live="polite"></div>
```

**Dynamic Content:**
```html
<div aria-live="polite" aria-busy="true" aria-atomic="true">
    Loading stories...
</div>
```

**Files to Modify:**
- `includes/shortcodes/class-fanfic-shortcodes-navigation.php` (40-60 lines)
- `includes/shortcodes/class-fanfic-shortcodes-lists.php` (60-80 lines)
- `includes/shortcodes/class-fanfic-shortcodes-interactive.php` (100-150 lines)
- `includes/shortcodes/class-fanfic-shortcodes-forms.php` (80-120 lines)
- `templates/template-single-chapter.php` (20-30 lines)
- `templates/template-archive.php` (30-40 lines)
- `templates/template-comments.php` (40-60 lines)
- `assets/js/fanfiction-frontend.js` (100-150 lines for state management)

**JavaScript Requirements:**
- Toggle `aria-expanded` on dropdown open/close
- Set `aria-invalid` when validation fails
- Add `aria-busy` during AJAX requests, remove on completion
- Manage `aria-checked`, `aria-pressed` for toggle states
- Focus management after dynamic content loads

**Testing Requirements:**
- Validate with axe DevTools browser extension
- Test with NVDA screen reader (Windows)
- Test with JAWS screen reader
- Test with VoiceOver (Mac)
- Validate with WAVE tool

---

### Area 2: Keyboard Navigation

**Purpose:** Allow users to navigate the entire site using keyboard only.

**Complexity:** MEDIUM | **Estimated Time:** 4-6 hours | **Files:** 3 (CSS + JS + templates)

**What It Does:**
1. Ensures logical tab order (visual flow: top-to-bottom, left-to-right)
2. Visible focus indicators on all interactive elements
3. Skip-to-content link for screen reader users
4. Arrow key navigation for chapter pages (left/right arrows)
5. Escape key closes modals/dropdowns
6. No keyboard traps (users can always tab out)

**Implementation Details:**

**Skip-to-Content Link:**
```html
<a href="#main-content" class="skip-link">Skip to content</a>
<!-- Later in page -->
<main id="main-content" role="main">
    <!-- Page content -->
</main>
```

**CSS for Skip Link:**
```css
.skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: #0073aa;
    color: #fff;
    padding: 8px;
    z-index: 100;
}

.skip-link:focus {
    top: 0;
}

/* Visible focus indicators */
*:focus {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}
```

**Arrow Key Navigation:**
```javascript
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft' && window.fanficPrevChapter) {
        window.location.href = window.fanficPrevChapter;
    }
    if (e.key === 'ArrowRight' && window.fanficNextChapter) {
        window.location.href = window.fanficNextChapter;
    }
});
```

**Escape Key for Dropdowns/Modals:**
```javascript
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Close any open dropdowns/modals
        document.querySelectorAll('[aria-expanded="true"]').forEach(el => {
            el.setAttribute('aria-expanded', 'false');
            el.nextElementSibling?.setAttribute('hidden', '');
        });
    }
});
```

**Tab Order Management:**
- Remove `tabindex` from semantic elements (they're tabbable by default)
- Use positive `tabindex` ONLY when absolutely necessary (deprecated but sometimes needed)
- Place interactive elements in source order
- Use CSS to change visual order without changing tab order (`flex-direction: row-reverse`)

**Files to Modify:**
- `assets/css/fanfiction-frontend.css` (+200-250 lines)
  - Skip link styles
  - Focus indicator styles
  - Touch target sizes (44x44px minimum)
  - High contrast mode support (`@media (prefers-contrast: more)`)

- `assets/js/fanfiction-frontend.js` (+150-200 lines)
  - Arrow key handlers for chapter navigation
  - Escape key handlers for dropdowns/modals
  - Focus management after dynamic content

- `templates/template-single-chapter.php` (add skip link, 5-10 lines)
- `templates/template-archive.php` (add skip link, 5-10 lines)
- `templates/template-dashboard.php` (add skip link, 5-10 lines)

**Testing Requirements:**
- Tab through entire site (no traps, logical order)
- Arrow left/right on chapter pages
- Escape key closes modals/dropdowns
- Focus indicators visible on all elements
- Test with keyboard only (no mouse)
- Test on mobile (virtual keyboard)

---

### Area 3: Meta Tags (SEO)

**Purpose:** Optimize for search engines and social media sharing.

**Complexity:** MEDIUM-HIGH | **Estimated Time:** 6-8 hours | **Files:** 3 (new class, templates, modifications)

**What It Does:**
1. Generates basic SEO meta tags (title, description, keywords)
2. Implements OpenGraph tags (Facebook, Pinterest, etc.)
3. Implements Twitter Card tags
4. Generates Schema.org structured data (JSON-LD format)
5. Implements robots meta tags (index/noindex based on status)
6. Integrates with WordPress sitemap system

**Files to Create:**
- `includes/class-fanfic-seo.php` (900-1200 lines)
  - Methods: `output_meta_tags()`, `output_canonical_tag()`, `output_structured_data()`, `get_og_image()`, `get_description()`, `filter_sitemap_entries()`
  - Hooks: `wp_head` (for meta tag output), `wp_sitemaps_posts_entry` (for sitemap filtering)

**Meta Tags to Implement:**

**Basic Meta Tags (for Stories):**
```html
<meta name="description" content="Story introduction excerpt, max 160 characters">
<meta name="keywords" content="genre1, genre2, custom-taxonomy-terms">
<meta name="author" content="Author Name">
<meta name="robots" content="index, follow">
```

**For Drafts/Private Stories:**
```html
<meta name="robots" content="noindex, nofollow">
```

**OpenGraph Tags:**
```html
<meta property="og:title" content="Story Title">
<meta property="og:description" content="Story introduction">
<meta property="og:image" content="https://example.com/featured-image.jpg">
<meta property="og:image:alt" content="Story featured image">
<meta property="og:url" content="https://example.com/plugin_base_name/story-slug/">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Site Name">
<meta property="article:published_time" content="2025-01-01T12:00:00Z">
<meta property="article:modified_time" content="2025-10-29T12:00:00Z">
<meta property="article:author" content="https://example.com/user/author-username/">
```

**Twitter Card Tags:**
```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Story Title">
<meta name="twitter:description" content="Story introduction">
<meta name="twitter:image" content="https://example.com/featured-image.jpg">
<meta name="twitter:site" content="@yourhandle">
```

**Schema.org Structured Data (JSON-LD):**
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Story Title",
  "description": "Story introduction",
  "image": ["https://example.com/featured-image.jpg"],
  "datePublished": "2025-01-01",
  "dateModified": "2025-10-29",
  "author": {
    "@type": "Person",
    "name": "Author Name"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Site Name",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/logo.png"
    }
  },
  "keywords": "genre1, genre2"
}
</script>
```

**Dependencies:**
- ✅ Story and chapter post types (Phase 1)
- ✅ Template system (Phase 3)
- ✅ Shortcodes (Phase 4-5)

**Database Changes:** None (uses post meta, options)

**Testing Checklist:**
- [ ] Meta tags output on story pages
- [ ] Meta tags output on chapter pages
- [ ] Meta tags NOT output on draft/private pages (robots: noindex)
- [ ] OG tags validated with Facebook Sharing Debugger
- [ ] Twitter Card tags validated with Twitter Card Validator
- [ ] Schema.org JSON-LD validated with Google Structured Data Tester
- [ ] Canonical tags present
- [ ] Image fallback works when featured image missing
- [ ] Character encoding correct (UTF-8)
- [ ] Special characters escaped properly

---

### Area 4: Canonical Tags

**Purpose:** Prevent duplicate content issues.

**Complexity:** LOW | **Estimated Time:** 1-2 hours | **Files:** 1 (integrated in SEO class)

**What It Does:**
1. Outputs canonical tag on all story pages
2. Outputs canonical tag on all chapter pages
3. Uses absolute URLs (with domain)
4. Removes query parameters (except pagination)
5. Handles URL slug customization from URL Rules

**Implementation:**
```html
<link rel="canonical" href="https://example.com/plugin_base_name/story-slug/">
<link rel="canonical" href="https://example.com/plugin_base_name/story-slug/chapter-1/">
```

**Integrated into:** `includes/class-fanfic-seo.php`

**Dependencies:**
- `class-fanfic-rewrite.php` (for URL slug configuration)

---

### Area 5: Screen Reader Compatibility

**Purpose:** Ensure content is understandable to screen reader users.

**Complexity:** MEDIUM | **Estimated Time:** 4-6 hours | **Files:** 10+ (templates + CSS)

**What It Does:**
1. Adds alternative text to all images
2. Uses semantic HTML5 elements (`<header>`, `<nav>`, `<main>`, `<article>`, `<section>`, `<footer>`)
3. Creates screen reader-only text for icon labels
4. Implements proper heading hierarchy (one H1 per page)
5. Adds `<caption>` to tables with descriptions
6. Implements `.sr-only` CSS class for hidden text

**Semantic HTML Structure:**

**Story Page:**
```html
<header role="banner"><!-- Site header --></header>
<main role="main" id="main-content">
    <article>
        <h1>Story Title</h1>
        <nav role="navigation" aria-label="Story navigation"><!-- Links --></nav>
        <section><!-- Story introduction --></section>
    </article>
</main>
<footer role="contentinfo"><!-- Site footer --></footer>
```

**Chapter Page:**
```html
<main role="main" id="main-content">
    <article>
        <h1>Story Title - Chapter 1</h1>
        <nav role="navigation" aria-label="Chapter navigation"><!-- Prev/Next --></nav>
        <section><!-- Chapter content --></section>
        <section><!-- Comments --></section>
    </article>
</main>
```

**Semantic Element Usage:**
- `<header>` - Site header area
- `<nav>` - Navigation sections
- `<main>` - Primary page content
- `<article>` - Self-contained story/chapter
- `<section>` - Distinct content sections
- `<aside>` - Sidebars, supplementary content
- `<footer>` - Site footer

**Screen Reader-Only CSS:**
```css
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border-width: 0;
}
```

**Usage Examples:**
```html
<!-- Icon-only button -->
<button><span class="sr-only">Bookmark this story</span>⭐</button>

<!-- Loading state -->
<div class="loader">
    <span class="sr-only">Loading stories...</span>
    <div class="spinner"></div>
</div>

<!-- Required field indicator -->
<label>Username <span aria-label="required" class="sr-only">(required)</span>*</label>
```

**Files to Modify:**
- All `templates/*.php` files (add semantic structure, 20-40 lines each)
- `assets/css/fanfiction-frontend.css` (add `.sr-only` class, 20-30 lines)
- All shortcode files (add semantic elements, 10-20 lines each)

**Testing Checklist:**
- [ ] All images have alt attributes (or `alt=""` if decorative)
- [ ] One H1 per page (no skipped heading levels)
- [ ] Semantic HTML structure valid
- [ ] Screen reader correctly announces landmarks
- [ ] Screen reader announces form field requirements
- [ ] Screen reader announces validation errors
- [ ] Table captions present and descriptive
- [ ] Test with NVDA, VoiceOver, JAWS

---

### Area 6: Color Contrast & Responsive Design

**Purpose:** Ensure visual accessibility and mobile compatibility.

**Complexity:** MEDIUM | **Estimated Time:** 4-6 hours | **Files:** 2 CSS files

**What It Does:**
1. Defines accessible color palette (WCAG AA contrast ratios)
2. Implements minimum touch target sizes (44x44px)
3. Ensures responsive design on all devices
4. Supports high contrast mode
5. Supports reduced motion preferences
6. Ensures readability at all font sizes

**Color Contrast Requirements:**
- Normal text (< 18pt): 4.5:1 ratio
- Large text (≥ 18pt or ≥ 14pt bold): 3:1 ratio
- UI components and icons: 3:1 ratio

**Accessible Color Palette:**
```css
:root {
    /* Core colors */
    --color-text: #23282d;           /* 14.3:1 on white */
    --color-text-light: #50575e;     /* 7.6:1 on white */
    --color-background: #ffffff;
    --color-border: #dcdcde;

    /* Primary brand colors */
    --color-primary: #0073aa;        /* 4.5:1 on white */
    --color-primary-dark: #005177;   /* 5.6:1 on white */

    /* Status colors */
    --color-success: #007017;        /* 4.5:1 on white */
    --color-warning: #826200;        /* 4.6:1 on white */
    --color-error: #d63638;          /* 4.5:1 on white */
    --color-info: #0073aa;           /* 4.5:1 on white */

    /* Links */
    --color-link: #0073aa;           /* 4.5:1 on white */
    --color-link-visited: #6f42c1;   /* 4.5:1 on white */
}
```

**Minimum Touch Targets:**
```css
button,
a,
input[type="submit"],
input[type="button"],
input[type="checkbox"],
input[type="radio"],
.touch-target {
    min-height: 44px;
    min-width: 44px;
    padding: 8px 16px;
}

/* More generous on mobile */
@media (max-width: 768px) {
    button,
    a.button,
    input[type="submit"] {
        min-height: 48px;
        min-width: 48px;
    }
}
```

**Responsive Design Breakpoints:**
```css
/* Mobile first approach */
/* Mobile: 320px - 767px */
@media (max-width: 767px) {
    /* Mobile styles */
}

/* Tablet: 768px - 1024px */
@media (min-width: 768px) {
    /* Tablet styles */
}

/* Desktop: 1025px+ */
@media (min-width: 1025px) {
    /* Desktop styles */
}

/* Large desktop: 1440px+ */
@media (min-width: 1440px) {
    /* Large screen styles */
}
```

**High Contrast Mode Support:**
```css
@media (prefers-contrast: more) {
    /* Increase contrast ratios */
    :root {
        --color-text: #000000;        /* Maximum contrast */
        --color-primary: #0000ff;     /* Brighter primary */
    }
}
```

**Reduced Motion Support:**
```css
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

**Font Size Accessibility:**
```css
html {
    font-size: 16px; /* Base size, don't change */
}

body {
    font-size: 1rem;        /* 16px */
    line-height: 1.6;       /* For readability */
}

h1 { font-size: 2rem; }     /* 32px */
h2 { font-size: 1.75rem; }  /* 28px */
h3 { font-size: 1.5rem; }   /* 24px */

/* Support user font size preferences */
@media (prefers-reduced-motion: no-preference) {
    html {
        scroll-behavior: smooth;
    }
}
```

**Files to Modify:**
- `assets/css/fanfiction-frontend.css` (+300-400 lines)
  - Color palette variables
  - Focus indicators
  - Touch target sizes
  - Responsive breakpoints
  - High contrast mode
  - Reduced motion support

- `assets/css/fanfiction-admin.css` (+200-250 lines)
  - Same color palette
  - Same focus indicators
  - Same touch target sizes

**Testing Checklist:**
- [ ] All color combinations meet WCAG AA (4.5:1 for normal text)
- [ ] Touch targets are 44x44px minimum
- [ ] Responsive design works at 320px, 768px, 1024px, 1440px
- [ ] High contrast mode tested
- [ ] Reduced motion respected
- [ ] Font sizes readable at all zoom levels (200%)
- [ ] No color-only information (use text + color)
- [ ] Test with WebAIM Contrast Checker
- [ ] Test on actual devices (not just DevTools)

---

## IMPLEMENTATION ROADMAP

### Phase 12 Implementation Order

```
WEEK 1 (15-20 hours total)
├─ Feature 1: Author Demotion Cron (2-3 hours)
│  └─ Create class-fanfic-author-demotion.php
│  └─ Integrate with core.php
│  └─ Test with manual cron trigger
│
├─ Feature 2: Custom Widgets (4-6 hours)
│  ├─ Create class-fanfic-widgets.php (manager)
│  ├─ Create 4 widget classes
│  ├─ Add CSS styling (150-200 lines)
│  ├─ Update core.php initialization
│  └─ Test all widgets in widget panel
│
└─ Feature 3: Export/Import (8-12 hours)
   ├─ Create class-fanfic-export.php
   ├─ Create class-fanfic-import.php
   ├─ Create class-fanfic-export-import-admin.php
   ├─ Add admin UI tab
   ├─ Test with sample CSV files
   └─ Document CSV format
```

### Phase 13 Implementation Order

```
WEEK 2-3 (25-35 hours total)
├─ Area 1: ARIA Roles & Labels (6-8 hours)
│  ├─ Update all shortcode files (~12 files)
│  ├─ Add ARIA attributes to templates (~8 files)
│  ├─ Update JavaScript for state management
│  └─ Test with axe DevTools
│
├─ Area 2: Keyboard Navigation (4-6 hours)
│  ├─ Add skip-link to templates
│  ├─ Add CSS for focus indicators
│  ├─ Implement arrow key handlers
│  ├─ Implement escape key handlers
│  └─ Test with keyboard only
│
├─ Area 3: Meta Tags (6-8 hours)
│  ├─ Create class-fanfic-seo.php
│  ├─ Implement basic meta tags
│  ├─ Implement OG tags
│  ├─ Implement Twitter Card tags
│  ├─ Implement Schema.org JSON-LD
│  └─ Test with validators
│
├─ Area 4: Canonical Tags (1-2 hours)
│  └─ Integrate into class-fanfic-seo.php
│
├─ Area 5: Screen Reader Compatibility (4-6 hours)
│  ├─ Update all templates with semantic HTML
│  ├─ Add table captions
│  ├─ Add sr-only CSS class
│  └─ Test with NVDA/VoiceOver
│
└─ Area 6: Color Contrast & Responsive (4-6 hours)
   ├─ Define accessible color palette
   ├─ Update CSS files
   ├─ Add responsive breakpoints
   ├─ Test on real devices
   └─ Test with WebAIM Contrast Checker
```

---

## FILE MODIFICATION MATRIX

### Phase 12 Files

| File | Action | Lines | Complexity |
|------|--------|-------|-----------|
| `includes/class-fanfic-author-demotion.php` | CREATE | 300-350 | MEDIUM |
| `includes/class-fanfic-widgets.php` | CREATE | 300-400 | MEDIUM |
| `includes/widgets/class-fanfic-widget-recent-stories.php` | CREATE | 200-250 | LOW |
| `includes/widgets/class-fanfic-widget-featured-stories.php` | CREATE | 200-250 | LOW |
| `includes/widgets/class-fanfic-widget-most-bookmarked.php` | CREATE | 200-250 | LOW |
| `includes/widgets/class-fanfic-widget-top-authors.php` | CREATE | 200-250 | LOW |
| `includes/class-fanfic-export.php` | CREATE | 400-500 | HIGH |
| `includes/class-fanfic-import.php` | CREATE | 600-800 | HIGH |
| `includes/admin/class-fanfic-export-import-admin.php` | CREATE | 500-700 | HIGH |
| `includes/class-fanfic-core.php` | MODIFY | +30-50 | LOW |
| `includes/class-fanfic-admin.php` | MODIFY | +20-30 | LOW |
| `includes/class-fanfic-settings.php` | MODIFY | +50-100 | LOW |
| `assets/css/fanfiction-frontend.css` | MODIFY | +150-200 | LOW |
| **TOTAL** | | ~4,300-5,900 | |

### Phase 13 Files

| File | Action | Lines | Complexity |
|------|--------|-------|-----------|
| `includes/class-fanfic-seo.php` | CREATE | 900-1200 | MEDIUM |
| `includes/class-fanfic-accessibility.php` | CREATE | 400-600 | MEDIUM |
| `includes/shortcodes/class-fanfic-shortcodes-navigation.php` | MODIFY | +40-60 | LOW |
| `includes/shortcodes/class-fanfic-shortcodes-lists.php` | MODIFY | +60-80 | LOW |
| `includes/shortcodes/class-fanfic-shortcodes-interactive.php` | MODIFY | +100-150 | LOW |
| `includes/shortcodes/class-fanfic-shortcodes-forms.php` | MODIFY | +80-120 | LOW |
| `templates/template-single-chapter.php` | MODIFY | +40-60 | LOW |
| `templates/template-archive.php` | MODIFY | +40-60 | LOW |
| `templates/template-comments.php` | MODIFY | +40-60 | LOW |
| `templates/template-dashboard.php` | MODIFY | +30-50 | LOW |
| `templates/template-dashboard-author.php` | MODIFY | +30-50 | LOW |
| `templates/template-login.php` | MODIFY | +20-30 | LOW |
| `templates/template-register.php` | MODIFY | +20-30 | LOW |
| `templates/template-create-story.php` | MODIFY | +20-30 | LOW |
| `templates/template-edit-chapter.php` | MODIFY | +20-30 | LOW |
| `assets/css/fanfiction-frontend.css` | MODIFY | +500-700 | LOW |
| `assets/css/fanfiction-admin.css` | MODIFY | +200-250 | LOW |
| `assets/js/fanfiction-frontend.js` | MODIFY | +250-350 | MEDIUM |
| `includes/class-fanfic-core.php` | MODIFY | +10-15 | LOW |
| **TOTAL** | | ~4,300-6,500 | |

---

## ESTIMATED TIMELINE

### Phase 12: Additional Features
- Author Demotion Cron: 2-3 hours (quick implementation)
- Custom Widgets: 4-6 hours (4 independent widgets)
- Export/Import: 8-12 hours (complex validation and error handling)
- Documentation & Testing: 2-3 hours
- **TOTAL: 16-24 hours (2-3 days intensive work)**

### Phase 13: Accessibility & SEO
- ARIA Implementation: 6-8 hours (affects many files)
- Keyboard Navigation: 4-6 hours (JavaScript + CSS)
- Meta Tags (SEO): 6-8 hours (new SEO class)
- Canonical Tags: 1-2 hours (integrated with SEO)
- Screen Reader Compatibility: 4-6 hours (semantic HTML)
- Color Contrast & Responsive: 4-6 hours (CSS refinement)
- Testing & Validation: 5-7 hours (with various tools)
- Documentation: 3-5 hours (accessibility statement, guides)
- **TOTAL: 33-48 hours (4-6 days intensive work)**

### Combined Estimated Effort
- **Grand Total: 49-72 hours (~6-9 days of full-time work)**
- **Part-time estimate: 5-7 weeks at 8-12 hours/week**

---

## SUCCESS CRITERIA

### Phase 12 Completion Checklist
- ✅ Author demotion cron runs daily
- ✅ Custom widgets appear in widget panel
- ✅ Export generates valid CSV files
- ✅ Import accepts and validates CSV files
- ✅ All unit tests pass
- ✅ Security audit passes (sanitization, nonces, escaping)
- ✅ No PHP syntax errors
- ✅ Integration tests pass
- ✅ Documentation complete
- ✅ IMPLEMENTATION_STATUS.md updated

### Phase 13 Completion Checklist
- ✅ WCAG 2.1 AA compliance (verified with axe, WAVE)
- ✅ All interactive elements keyboard accessible
- ✅ Meta tags output correctly (verified with validators)
- ✅ Sitemap integration working
- ✅ Screen reader compatible (tested with NVDA/VoiceOver)
- ✅ Color contrast ratios meet requirements (4.5:1 minimum)
- ✅ Responsive design works on all breakpoints
- ✅ Focus indicators visible on all elements
- ✅ No keyboard traps
- ✅ Canonical tags present
- ✅ All tests pass (unit, integration, accessibility)
- ✅ Documentation complete (accessibility statement, SEO guide)
- ✅ IMPLEMENTATION_STATUS.md updated

---

## NEXT STEPS

### Immediate Actions:
1. **Confirm this plan with user** - Ensure alignment on scope, timeline, and priorities
2. **Create GitHub issues** for each feature (if using GitHub)
3. **Set up development environment** - Ensure all dependencies available
4. **Code review process** - Establish standards for Phase 12 & 13 code

### For Implementation:
1. **Phase 12 First** - Easier to implement, provides value quickly
2. **Parallel Development** - Multiple developers can work on different widgets simultaneously
3. **Agile Approach** - Complete and test each feature before moving to next
4. **Early Testing** - Start testing Phase 13 components as Phase 12 nears completion

### Documentation:
1. Create PHASE_12_IMPLEMENTATION_COMPLETE.md after Phase 12
2. Create PHASE_13_IMPLEMENTATION_COMPLETE.md after Phase 13
3. Update IMPLEMENTATION_STATUS.md with progress after each major milestone
4. Create user guide for Phase 12 export/import feature
5. Create accessibility statement for Phase 13 compliance

---

## RISK MITIGATION

### Potential Risks & Solutions

**Risk 1: Phase 13 ARIA implementation takes longer than estimated**
- *Solution:* Focus on critical components first (navigation, interactive elements)
- *Fallback:* Complete basic ARIA, then add advanced features in Phase 14

**Risk 2: Export/Import CSV handling edge cases**
- *Solution:* Build comprehensive test cases for special characters, large files
- *Fallback:* Start with basic CSV support, add advanced features later

**Risk 3: Keyboard navigation conflicts with existing JavaScript**
- *Solution:* Thorough testing before deployment, careful event handling
- *Fallback:* Use event delegation and proper stopping of propagation

**Risk 4: SEO meta tag performance impact**
- *Solution:* Use transients for meta tag caching
- *Fallback:* Only generate on first page load, cache subsequent requests

---

## CONCLUSION

This comprehensive plan provides a clear roadmap for completing Phase 12 (Additional Features) and Phase 13 (Accessibility & SEO) of the Fanfiction Manager WordPress plugin.

**Key Takeaways:**
- Phase 12 adds valuable features (widgets, export/import, cron automation)
- Phase 13 ensures WCAG 2.1 AA compliance and comprehensive SEO
- Both phases can be partially parallelized (multiple features simultaneously)
- Total estimated effort: 6-9 days of full-time work
- Clear success criteria and testing procedures provided
- Risk mitigation strategies in place

**Ready to begin implementation when user gives go-ahead.**

---

**Document Version:** 1.0
**Last Updated:** October 29, 2025
**Author:** Claude Code (AI Agent)
**Status:** READY FOR IMPLEMENTATION
