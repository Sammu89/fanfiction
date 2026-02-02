# Browse All Terms Feature

## Overview
The "Browse All Terms" feature allows users to view a complete directory of taxonomy terms with story counts. This is especially useful for taxonomies with many terms (like fandoms) where users want to see what's available before filtering.

## Usage

### URL Format
Add `?[taxonomy]=all` to your search/browse page URL:

- **Genres**: `/search/?genre=all`
- **Statuses**: `/search/?status=all`
- **Fandoms**: `/search/?fandom=all`
- **Languages**: `/search/?language=all`
- **Warnings**: `/search/?warning=all`
- **Custom taxonomies**: `/search/?[taxonomy-slug]=all`

### Behavior
When a user accesses one of these URLs:

1. Instead of showing filtered story results, the page displays a directory of all terms in that taxonomy
2. Only terms with at least 1 published story are shown
3. Each term displays:
   - Term name
   - Story count (e.g., "12 stories")
   - Clickable link to filter by that term
4. Terms are displayed in alphabetical order (by name)

### Display
The directory is shown in a responsive grid layout:
- Desktop: 3-4 columns
- Tablet: 2 columns
- Mobile: 1 column

Each term is displayed as a card with:
- Term name (left side)
- Story count badge (right side)
- Hover effect with color transition

## Examples

### Example 1: Browse All Genres
**URL**: `/search/?genre=all`

**Display**:
```
Browse by Genres
4 terms with stories available.

┌─────────────────────────────┐
│ Comedy            12 stories │
├─────────────────────────────┤
│ Drama              8 stories │
├─────────────────────────────┤
│ Romance           25 stories │
├─────────────────────────────┤
│ Sci-Fi             5 stories │
└─────────────────────────────┘
```

### Example 2: Browse All Fandoms
**URL**: `/search/?fandom=all`

**Display**:
```
Browse by Fandoms
156 terms with stories available.

┌─────────────────────────────┐
│ Harry Potter      342 stories│
├─────────────────────────────┤
│ Marvel             89 stories│
├─────────────────────────────┤
│ Star Wars          67 stories│
├─────────────────────────────┤
│ ...                          │
└─────────────────────────────┘
```

This is particularly useful for fandoms because:
- You have thousands of potential fandoms in the system
- Most won't have stories yet
- Users can see which fandoms are active
- Only fandoms with stories are shown

## Supported Taxonomies

### Built-in Taxonomies
- ✅ **genre** (fanfiction_genre) - WordPress taxonomy
- ✅ **status** (fanfiction_status) - WordPress taxonomy
- ✅ **fandom** - Light taxonomy (stored in postmeta)
- ✅ **language** - Light taxonomy (stored in postmeta)
- ✅ **warning** - Warnings system

### Custom Taxonomies
- ✅ All active custom taxonomies created in admin
- Uses the taxonomy slug as the URL parameter

## Why Not Use the Search Index?

Your `wp_fanfic_story_search_index` table is optimized for **keyword/text searching**, not taxonomy counting:

| Feature | Search Index | Browse All Terms |
|---------|-------------|------------------|
| **Purpose** | Find stories matching keywords | List all taxonomy terms with counts |
| **Query type** | FULLTEXT MATCH...AGAINST | COUNT + GROUP BY on taxonomy relations |
| **What it stores** | Aggregated text (titles, names, tags) | N/A - queries live taxonomy data |
| **Use case** | "Find stories about 'magic wizard'" | "Show all fandoms with story counts" |
| **Example** | `MATCH(indexed_text) AGAINST('harry potter')` | `SELECT meta_value, COUNT(*) FROM postmeta GROUP BY meta_value` |

The search index stores term **names as searchable text**, but doesn't maintain the **taxonomy-to-story relationships** needed for counting.

**Example:**
```
Search Index:
  story_id: 123
  indexed_text: "Harry Potter Adventure Fantasy Magic..."
  ↓ Can answer: "Which stories contain 'Fantasy'?"
  ✗ Cannot answer: "How many stories are tagged with the Fantasy genre?"

Taxonomy Tables:
  term_id: 5, name: "Fantasy", count: 25
  ↓ Can answer: "How many stories have the Fantasy term?"
  ✓ This is what we need!
```

## Technical Details

### Functions Added
1. `fanfic_is_browse_all_terms_mode()` - Detects if in "browse all" mode
2. `fanfic_get_browse_all_taxonomy()` - Gets the taxonomy being browsed
3. `fanfic_get_taxonomy_terms_with_counts()` - Gets terms with story counts
4. `fanfic_get_light_taxonomy_terms_with_counts()` - Handles light taxonomies
5. `fanfic_get_warning_story_count()` - Gets warning story count
6. `fanfic_get_custom_taxonomy_term_count()` - Gets custom taxonomy term count

### Files Modified
- `includes/functions.php` - Added helper functions
- `includes/shortcodes/class-fanfic-shortcodes-search.php` - Updated browse shortcode
- `assets/css/fanfiction-frontend.css` - Added taxonomy directory styles

### Performance & Caching
- **Cached**: Results are cached for 1 hour using your existing Fanfic_Cache system
- **Optimized queries**:
  - WordPress taxonomies use `hide_empty => true` (built-in indexes)
  - Light taxonomies use `COUNT(DISTINCT post_id)` with GROUP BY
  - Custom taxonomies use optimized joins on indexed columns
- **No search index needed**: The search index is for keyword searching; this feature needs taxonomy counts
- **Database indexes used**: All queries leverage existing indexes on `meta_key`, `post_type`, and taxonomy tables
- **No pagination needed**: Unless you have 1000+ active terms (unlikely)

## Integration with Existing System

### Shortcodes
The feature works automatically with:
- `[fanfic-story-archive]` - Main browse shortcode
- `[fanfic-search-bar]` - Search bar updates page title/description

### Templates
Also works with:
- `templates/template-story-archive.php` - If you use the template directly

### Navigation
You can add links to your site navigation:
```php
<ul>
  <li><a href="<?php echo fanfic_get_page_url('search'); ?>?genre=all">Browse Genres</a></li>
  <li><a href="<?php echo fanfic_get_page_url('search'); ?>?fandom=all">Browse Fandoms</a></li>
  <li><a href="<?php echo fanfic_get_page_url('search'); ?>?status=all">Browse Status</a></li>
</ul>
```

## Customization

### CSS
All styles use CSS custom properties and can be overridden in your theme:

```css
/* Change directory grid columns */
.fanfic-taxonomy-directory {
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
}

/* Change item colors */
.fanfic-taxonomy-directory-item {
  background: #f9f9f9;
  border-color: #ddd;
}

/* Change hover effects */
.fanfic-taxonomy-directory-item:hover {
  transform: scale(1.02);
}
```

### Filters
Future: Add filters to customize term display:
- `fanfic_browse_all_terms` - Modify terms array
- `fanfic_browse_all_term_display` - Customize term HTML

## Benefits

### For Users
1. **Discoverability**: See what's available before searching
2. **Navigation**: Easy way to browse large taxonomies
3. **Visual feedback**: Story counts show activity level
4. **Mobile-friendly**: Responsive grid adapts to screen size

### For Site Admins
1. **Scalability**: Works with thousands of fandom terms
2. **Performance**: Only shows terms with stories
3. **Automatic**: No manual curation needed
4. **Extensible**: Works with custom taxonomies

## Notes

- Terms with 0 stories are automatically hidden
- The "all" keyword is case-insensitive (`all`, `All`, `ALL` all work)
- Clicking a term navigates to the normal filtered view
- You can combine with other filters (though not recommended)
- Light taxonomies (fandom, language) query postmeta directly
- Custom taxonomies query the custom taxonomy table
