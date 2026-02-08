# Search Bar System Audit

## Overview

The search bar is a comprehensive filtering system that allows users to browse stories with multiple filter options. It currently uses **form submission** (not AJAX), rendering results via URL parameters and full page reload.

---

## Current Architecture

### 1. Shortcode Output Layer
**File:** `includes/shortcodes/class-fanfic-shortcodes-search.php`

#### Main Shortcode: `[fanfic-search-bar]`
Renders the complete search interface with 3 sections:

**Section 1: Header & Title**
```html
<header class="fanfic-archive-header">
  <h1>Browse Stories</h1>
  <p>Description</p>
</header>
```

**Section 2: Basic Search Row** (Always visible)
- Text input: `#fanfic-search-input` (name="search")
- Status dropdown: `#fanfic-status-filter` (name="status")
- Sort dropdown: `#fanfic-sort-filter` (name="sort")
- Clear filters button: `#fanfic-clear-filters-button`
- Search submit button

**Section 3: Advanced Filters** (Hidden by default, toggle to expand)
```html
<div class="fanfic-advanced-search-filters" style="display: none;">
```
Contains:
- Match ALL filters toggle (smart mode)
- Genres multi-select
- Age rating dropdown
- Fandoms search/autocomplete
- Languages multi-select
- Custom taxonomies (single or multi-select)
- Warnings mode (include/exclude) + multi-select

**Section 4: Active Filters Display** (Below form)
```html
<div data-fanfic-active-filters>
  <div class="fanfic-active-filters">
    <!-- Pills showing current filters with remove buttons -->
    <!-- "Clear All Filters" link at right -->
  </div>
</div>
```

#### Secondary Shortcode: `[fanfic-story-archive]`
Renders the story results grid:
- Query built via `fanfic_build_stories_query_args()`
- Story cards displayed in grid
- Pagination with custom URL building
- "No results" message if empty

### 2. Data Flow

#### Getting Current Filters
```
fanfic_get_stories_context()
  ├─ Gets base URL (page permalink)
  ├─ Calls fanfic_get_stories_params() → normalizes $_GET params
  ├─ Gets all taxonomies (genres, statuses, etc.)
  ├─ Calls fanfic_build_active_filters() → generates pill display
  └─ Returns context array to template
```

#### URL Parameter Structure
Current form submits to itself with query string:
```
?search=query&status=completed&genre=action&genre=adventure&age=13&sort=updated&fandoms[]=1&warnings_slugs[]=violence&warnings_mode=exclude&match_all_filters=1
```

### 3. JavaScript Handling
**File:** `assets/js/fanfic-search-bar-frontend.js`

#### Current Features:
1. **Advanced search toggle**
   - Expands/collapses `.fanfic-advanced-search-filters`
   - Updates `aria-expanded` attribute
   - Animates icon (plus/minus)

2. **Multi-select dropdowns**
   - Custom implementation (not Select2)
   - Tracks checked items
   - Updates trigger button text (e.g., "2 selected" or "Action, Adventure")
   - Click outside to close

3. **Clear filters button**
   - Resets all inputs to default
   - Clears multi-select checked states
   - Updates trigger labels back to placeholders
   - Does NOT submit form automatically

4. **Smart toggle (match_all_filters)**
   - Visual feedback when active

5. **Warnings mode logic**
   - "Include" mode clears age rating automatically

6. **Enter key in search input**
   - Submits form on Enter

#### Current Limitations:
- **No AJAX**: Form must be submitted via button click or Enter key
- **No live updates**: Changes don't reflect until form submission
- **No debouncing**: No performance optimization for rapid clicks
- **Form submission only**: Full page reload required

### 4. Backend Search Engine

#### Query Building: `fanfic_build_stories_query_args()`
Processes normalized params and builds WP_Query args with multiple data sources:

**Text Search** (via search index):
```php
if ( !empty( $params['search'] ) && class_exists( 'Fanfic_Search_Index' ) ) {
  $search_ids = Fanfic_Search_Index::search( $params['search'], $limit );
}
```
- Full-text search across: title, intro, author name, chapter titles, visible/invisible tags, genres, status

**Taxonomy Filters** (via get_terms):
```php
$args['tax_query'] = array(
  'genre'  => fanfiction_genre,
  'status' => fanfiction_status,
  ...
)
```

**Custom Table Filters**:
- Fandoms (via `Fanfic_Fandoms::get_story_ids_by_fandom_slugs()`)
- Languages (via `Fanfic_Languages::get_story_ids_by_language_slugs()`)
- Warnings (via `fanfic_get_story_ids_with_warnings()`)
- Custom taxonomies (via `Fanfic_Custom_Taxonomies::get_story_ids_by_term_slugs()`)

**Special Logic**:
- Age rating → excluded warnings list (PG = exclude adult warnings)
- Warnings include/exclude mode
- Match ALL filters (AND vs OR logic)
- Intelligent intersection of `post__in` arrays

#### Search Index Class: `Fanfic_Search_Index`
Pre-indexed searchable text for stories:
- Story title, intro
- Author display name
- All chapter titles
- Visible tags (user-entered)
- Invisible tags (system-generated)
- Genre names, status names
- Warning names

Maintains index on:
- Story save/delete
- Chapter save/delete
- Author profile update
- Tag updates

---

## Current Limitations & Issues

### UX Issues
1. **Advanced filters hidden by default** - Users must click toggle to see options
2. **No real-time feedback** - Filter changes don't show results until form submission
3. **Full page reload** - Slow for large result sets
4. **URL not updated** - No browser history as user adjusts filters
5. **Unclear feedback** - No loading indicator during search

### Technical Issues
1. **AJAX endpoint exists but unused** - `ajax_stories_search()` registered but search bar doesn't use it
2. **No debouncing** - Rapid filter changes could cause multiple searches
3. **Scroll position lost** - Full page reload resets scroll to top
4. **Browser back button broken** - Can't navigate through filter history
5. **Accessibility** - No live region updates for screen readers

---

## AJAX Endpoint Already Available

The plugin has a built-in AJAX search handler ready to use:

```php
// Registered in class-fanfic-ajax-handlers.php
Fanfic_AJAX_Security::register_ajax_handler(
  'fanfic_search',
  array( __CLASS__, 'ajax_stories_search' ),
  false, // Allow anonymous
  array( 'rate_limit' => true )
);
```

**Endpoint:** `/wp-admin/admin-ajax.php?action=fanfic_search`

**Input Parameters Accepted:**
- `search`, `s` (text search)
- `genre[]` (array)
- `status` (single value)
- `fandom` (array)
- `warning` (array)
- `age` (single value)
- `sort` (sort order)
- `paged` (page number)
- `base_url` (for pagination links)

**Output Returned:**
```json
{
  "success": true,
  "data": {
    "html": "<div class='story-grid'>...</div>",
    "pagination": "<nav class='pagination'>...</nav>",
    "active_filters": "<div class='active-filters'>...</div>",
    "found": 25,
    "count_label": "Found 25 stories",
    "total_pages": 3,
    "current_page": 1
  },
  "message": "Results loaded."
}
```

---

## Recommended Implementation Plan

### Phase 1: Live Filter Updates (Quick Win)
- Add AJAX listener to all filter changes (checkboxes, selects, multi-select)
- Auto-trigger search on filter change
- Update results HTML in-place without page reload
- Keep URL in sync via `history.pushState()`

### Phase 2: Visual Improvements
- Move filters below search bar but keep them visible (no toggle needed for common filters)
- Add loading indicator during AJAX search
- Keep scroll position on results update
- Show result count above results

### Phase 3: Advanced Enhancements
- Debounce rapid filter changes
- Lazy load results as user scrolls
- Save filter preferences to localStorage
- Show "Did you mean?" suggestions for no-results searches

---

## Files to Modify

1. **`assets/js/fanfic-search-bar-frontend.js`**
   - Add AJAX event listeners to all filters
   - Auto-submit via AJAX on change
   - Update results in-place
   - Update URL with `history.pushState()`

2. **`includes/shortcodes/class-fanfic-shortcodes-search.php`**
   - Reorganize filter layout (if moving advanced filters)
   - Add result container with proper IDs

3. **`assets/css/fanfic-search-bar.css`**
   - Add loading state styles
   - Adjust filter visibility/layout

---

## Browser Compatibility

Current implementation:
- jQuery (already enqueued)
- ES5 JavaScript (broad compatibility)
- Modern CSS (flexbox, grid)

AJAX approach:
- Fetch API with jQuery fallback (already available)
- History API (IE10+)
- FormData API (IE10+)

---

## Performance Considerations

**Current Search:**
- Full page reload (expensive)
- All shortcodes re-render
- Database query per page load

**AJAX Search:**
- Only results HTML generated (cheaper)
- Single targeted database query
- No page re-render overhead
- Network request faster than full page load

**Optimization Needed:**
- Debounce filter changes (500ms)
- Cache search results (transients)
- Rate limit AJAX requests (already implemented via `Fanfic_AJAX_Security`)

