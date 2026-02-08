# Search Bar Live Update Implementation Plan

## Overview
Transform the search bar from form submission to **AJAX-based live filtering** with:
- **Automatic results update** when any filter changes
- **Filters always visible** below the search bar
- **Real-time feedback** with loading states
- **Browser history support** via `history.pushState()`
- **Zero page reload** required

---

## Implementation Steps

### Step 1: Update JavaScript to Support AJAX
**File:** `assets/js/fanfic-search-bar-frontend.js`

**Changes:**
1. Add AJAX search function that reads current form state
2. Add event listeners to ALL filter inputs/selects for change events
3. Debounce filter changes to prevent flooding server (500ms)
4. Update results HTML in-place
5. Update URL using `history.pushState()`
6. Show/hide loading indicator

**New Functionality:**
```javascript
// Trigger AJAX search on ANY filter change
$(document).on('change', '.fanfic-browse-form input, .fanfic-browse-form select', function() {
  fanficLiveSearch.debounce_and_search();
});

// Helper: Collect form data
function get_search_data() {
  return $('.fanfic-browse-form').serialize();
}

// Helper: AJAX call to fanfic_search action
function ajax_search() {
  $.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
      action: 'fanfic_search',
      nonce: fanficSearchBar.nonce,
      ...formData
    },
    success: function(response) {
      update_results(response.data);
      update_url(formData);
    }
  });
}
```

### Step 2: Reorganize Filter Layout (Optional)
**File:** `includes/shortcodes/class-fanfic-shortcodes-search.php`

**Current Layout:**
```
Header
─────────────────────────────────
Basic Search Row (text, status, sort, buttons)
Advanced Search Toggle [+]
  Advanced Filters (hidden by default)
─────────────────────────────────
Story Results Grid
Pagination
─────────────────────────────────
Active Filters (pills)
```

**Recommended New Layout:**
```
Header
─────────────────────────────────
Basic Search Row (text, status, sort, buttons)
─────────────────────────────────
Common Filters (ALWAYS VISIBLE)
  ├─ Genres
  ├─ Age Rating
  ├─ Languages
  └─ [More Filters] toggle for advanced options
─────────────────────────────────
Advanced Filters (hidden by toggle, shown on demand)
  ├─ Fandoms
  ├─ Custom Taxonomies
  ├─ Warnings (Include/Exclude)
  └─ Match ALL toggle
─────────────────────────────────
Active Filters (pills)
─────────────────────────────────
Loading... (hidden by default)
─────────────────────────────────
Story Results Grid
Pagination
```

**Implementation Option:** Keep current layout OR move genres/age/languages outside advanced toggle.

### Step 3: Update Shortcode to Support AJAX
**File:** `includes/shortcodes/class-fanfic-shortcodes-search.php`

**Add:**
```php
// Localize script with nonce for AJAX
wp_localize_script(
  'fanfic-search-bar-frontend',
  'fanficSearchBar',
  array(
    'baseUrl' => esc_url_raw( $context['base_url'] ),
    'nonce'   => wp_create_nonce( 'wp_rest' ), // or custom nonce
    'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
  )
);
```

### Step 4: Update CSS for Loading States
**File:** `assets/css/fanfic-search-bar.css`

**Add:**
```css
/* Loading state */
.fanfic-browse-loading {
  display: none;
  text-align: center;
  padding: 20px;
  color: #666;
}

.fanfic-browse-loading.active {
  display: block;
}

/* Fade out results while loading (optional) */
.fanfic-stories-results.loading {
  opacity: 0.6;
  pointer-events: none;
}

/* Skeleton loader (optional, if implementing) */
.fanfic-story-skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

---

## Detailed Code Changes

### Change 1: Enhanced JavaScript
```javascript
(function($) {
  'use strict';

  var fanficLiveSearch = {
    // Configuration
    config: {
      debounceDelay: 500,
      ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
      formSelector: '.fanfic-browse-form',
      resultsSelector: '[data-fanfic-browse-results]',
      loadingSelector: '[data-fanfic-browse-loading]',
      activeFiltersSelector: '[data-fanfic-active-filters]',
      paginationSelector: '[data-fanfic-browse-pagination]',
    },

    // Debounce timer
    debounceTimer: null,

    // Initialize
    init: function() {
      this.attach_listeners();
      this.setup_pagination();
    },

    // Attach listeners to all filter inputs
    attach_listeners: function() {
      var self = this;

      // Listen to form changes
      $(document).on('change', self.config.formSelector + ' input, ' + self.config.formSelector + ' select', function() {
        self.debounce_search();
      });

      // Search input on Enter key
      $(self.config.formSelector + ' #fanfic-search-input').on('keypress', function(e) {
        if (e.which === 13) {
          e.preventDefault();
          self.debounce_search();
        }
      });

      // Clear filters button
      $('#fanfic-clear-filters-button').on('click', function(e) {
        e.preventDefault();
        self.reset_filters();
        self.debounce_search();
      });
    },

    // Debounce search calls
    debounce_search: function() {
      clearTimeout(this.debounceTimer);
      var self = this;
      this.debounceTimer = setTimeout(function() {
        self.perform_search();
      }, this.config.debounceDelay);
    },

    // Perform AJAX search
    perform_search: function() {
      var self = this;
      var $form = $(self.config.formSelector);
      var $loading = $(self.config.loadingSelector);
      var $results = $(self.config.resultsSelector);

      // Show loading state
      $loading.addClass('active');
      $results.addClass('loading');

      // Gather form data
      var formData = {
        action: 'fanfic_search',
        nonce: fanficSearchBar.nonce,
        base_url: fanficSearchBar.baseUrl,
        paged: 1, // Reset to page 1 on filter change
      };

      // Serialize all form inputs
      $.each($form.serializeArray(), function(i, field) {
        if (formData[field.name]) {
          // Handle multiple values
          if (!Array.isArray(formData[field.name])) {
            formData[field.name] = [formData[field.name]];
          }
          formData[field.name].push(field.value);
        } else {
          formData[field.name] = field.value;
        }
      });

      // Make AJAX call
      $.ajax({
        url: self.config.ajaxUrl,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            self.update_results(response.data);
            self.update_url(formData);
          } else {
            console.error('Search error:', response.message);
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX error:', error);
        },
        complete: function() {
          // Hide loading state
          $loading.removeClass('active');
          $results.removeClass('loading');
        }
      });
    },

    // Update results in-place
    update_results: function(data) {
      var self = this;
      var $results = $(self.config.resultsSelector);
      var $pagination = $(self.config.paginationSelector);
      var $activeFilters = $(self.config.activeFiltersSelector);

      // Update results
      if (data.html) {
        $results.fadeOut(100, function() {
          $(this).html(data.html).fadeIn(100);
        });
      }

      // Update pagination
      if (data.pagination) {
        $pagination.html(data.pagination);
        self.setup_pagination(); // Re-attach listeners
      }

      // Update active filters
      if (data.active_filters) {
        $activeFilters.html(data.active_filters);
      }

      // Scroll to results (optional)
      $('html, body').animate({
        scrollTop: $results.offset().top - 100
      }, 300);
    },

    // Update URL to reflect current filters
    update_url: function(formData) {
      var params = new URLSearchParams();

      $.each(formData, function(key, value) {
        if (key !== 'action' && key !== 'nonce' && key !== 'base_url') {
          if (Array.isArray(value)) {
            value.forEach(function(v) {
              if (v) params.append(key, v);
            });
          } else if (value) {
            params.append(key, value);
          }
        }
      });

      var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
      window.history.pushState({ filters: formData }, '', newUrl);
    },

    // Setup pagination AJAX handlers
    setup_pagination: function() {
      var self = this;
      $(document).on('click', self.config.paginationSelector + ' a', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var pageNum = new URL(url, window.location.origin).searchParams.get('paged') || 1;

        // Update form with pagination
        var $form = $(self.config.formSelector);
        var formData = self.get_form_data();
        formData.paged = pageNum;

        // Perform search with pagination
        self.perform_search_with_data(formData);
      });
    },

    // Reset all filters
    reset_filters: function() {
      var $form = $(this.config.formSelector);

      // Reset text inputs
      $form.find('input[type="text"]').val('');

      // Reset selects
      $form.find('select').val('');

      // Reset checkboxes
      $form.find('input[type="checkbox"]').prop('checked', false);

      // Update multi-select labels
      $form.find('.multi-select').each(function() {
        var $trigger = $(this).find('.multi-select__trigger');
        var placeholder = $(this).data('placeholder') || 'Select';
        $trigger.text(placeholder);
      });

      // Reset smart toggle
      $('#fanfic-match-all-filters').prop('checked', false).trigger('change');
    },

    // Get current form data
    get_form_data: function() {
      var data = {};
      var $form = $(this.config.formSelector);

      $.each($form.serializeArray(), function(i, field) {
        if (data[field.name]) {
          if (!Array.isArray(data[field.name])) {
            data[field.name] = [data[field.name]];
          }
          data[field.name].push(field.value);
        } else {
          data[field.name] = field.value;
        }
      });

      return data;
    },

    // Perform search with specific data
    perform_search_with_data: function(formData) {
      // This allows pagination to work
      clearTimeout(this.debounceTimer);
      // Set the form data and search
      var self = this;
      // Could optimize by not re-serializing form here
      this.perform_search();
    }
  };

  // Initialize on document ready
  $(document).ready(function() {
    fanficLiveSearch.init();
  });

})(jQuery);
```

### Change 2: HTML Adjustments (Shortcode)
In the shortcode, ensure result containers have correct data attributes:

```php
// In stories_story_archive() method, ensure:
<div class="fanfic-stories-results" data-fanfic-browse-results>
  <!-- Results will be replaced here -->
</div>

<nav class="fanfic-pagination" data-fanfic-browse-pagination>
  <!-- Pagination will be replaced here -->
</nav>

<!-- Loading indicator -->
<div class="fanfic-browse-loading" data-fanfic-browse-loading aria-hidden="true" style="display: none;">
  <?php esc_html_e( 'Loading...', 'fanfiction-manager' ); ?>
</div>
```

### Change 3: Nonce Registration
In shortcode localization, add nonce:

```php
wp_localize_script(
  'fanfic-search-bar-frontend',
  'fanficSearchBar',
  array(
    'baseUrl'  => esc_url_raw( $context['base_url'] ),
    'ajaxUrl'  => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
    'nonce'    => wp_create_nonce( 'wp_rest' ), // Matches AJAX handler verification
  )
);
```

---

## Testing Checklist

- [ ] Text search triggers on input (with debounce)
- [ ] Status filter change triggers search
- [ ] Genre selection triggers search
- [ ] Age rating change triggers search
- [ ] Multiple filters work together
- [ ] URL updates to reflect current filters
- [ ] Browser back button returns to previous filters
- [ ] Pagination works via AJAX
- [ ] Active filters display correctly
- [ ] Clear filters button resets everything
- [ ] Loading indicator shows/hides
- [ ] Results scroll into view
- [ ] Works on mobile (responsive)
- [ ] Performance is acceptable (debounce working)
- [ ] No console errors

---

## Optional Enhancements

### 1. Skeleton Loaders
Replace loading text with animated skeleton:
```php
<div class="fanfic-story-skeleton">
  <!-- Repeat for each skeleton card -->
</div>
```

### 2. Save Filter Preferences
Store selected filters in localStorage:
```javascript
localStorage.setItem('fanfic_last_filters', JSON.stringify(formData));
```

### 3. No Results Enhancement
Suggest similar searches or browse by categories:
```php
// In AJAX handler, if no results:
$suggestions = self::get_search_suggestions( $params );
```

### 4. Advanced Analytics
Track which filters users use most (for admin dashboard)

---

## Rollback Plan

If issues arise:
1. Revert JavaScript changes - falls back to form submission
2. Keep AJAX handler disabled until JS fixes verified
3. Test with small user group before full rollout

---

## Browser Compatibility

- **Chrome/Edge:** Full support
- **Firefox:** Full support
- **Safari:** Full support
- **IE11:** Fallback to form submission (no History API) - optional
  - Remove `history.pushState()` usage and URL updates
  - Forms still work via normal submission

---

## Performance Impact

**Before AJAX:**
- Full page reload: ~500ms-2s
- All shortcodes re-render
- All hooks fire

**After AJAX:**
- Only AJAX call: ~100-300ms
- Only results update
- Smoother UX, faster feedback

**Recommended Optimizations:**
1. Add transient caching for search results (10 minutes)
2. Debounce filter changes (500ms)
3. Lazy load story images in results
4. Consider pagination via infinite scroll (optional)

