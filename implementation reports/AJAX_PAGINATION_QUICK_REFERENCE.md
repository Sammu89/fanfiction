# AJAX Pagination - Quick Reference

## Existing AJAX Actions in Codebase

### NOTIFICATIONS (Primary Implementation)
- **Action:** `fanfic_get_notifications`
- **File:** `includes/class-fanfic-ajax-handlers.php` lines 666-741
- **Handler:** `Fanfic_AJAX_Handlers::ajax_get_notifications()`
- **Parameters:** `page`, `unread_only` (optional)
- **Per Page:** 10 items
- **Max Pages:** 5 (50 total)
- **JS Handler:** `assets/js/fanfiction-frontend.js` lines 1461-1520

### OTHER INTERACTIVE ACTIONS (in class-fanfic-ajax-handlers.php)
- `fanfic_submit_rating` - Rating submission
- `fanfic_toggle_like` - Toggle chapter likes
- `fanfic_toggle_bookmark` - Toggle bookmarks
- `fanfic_toggle_follow` - Toggle story/author follows
- `fanfic_toggle_email_notifications` - Email toggle
- `fanfic_subscribe_email` - Email subscriptions
- `fanfic_get_chapter_stats` - Batch stats loading
- `fanfic_delete_notification` - Delete single notification

---

## Backend Query Methods for Bookmarks

### Get User Bookmarks
**Location:** `includes/class-fanfic-bookmarks.php::get_user_bookmarks()`

```php
Fanfic_Bookmarks::get_user_bookmarks(
    $user_id,                    // int - User ID
    $bookmark_type = null,       // null | 'story' | 'chapter'
    $limit = 50,                 // int - Items per request
    $offset = 0                  // int - Pagination offset
)
```

Returns: Array of bookmarks with `post_id`, `bookmark_type`, `created_at`

### Get Total Count
**Location:** `includes/class-fanfic-bookmarks.php::get_bookmarks_count()`

```php
Fanfic_Bookmarks::get_bookmarks_count(
    $user_id,                    // int - User ID
    $bookmark_type = null        // null | 'story' | 'chapter'
)
```

Returns: Integer count

---

## Security & Response Helpers

### Register AJAX Handler
**Location:** `includes/class-fanfic-ajax-security.php`

```php
Fanfic_AJAX_Security::register_ajax_handler(
    'action_name',                // AJAX action name
    'callback_method',            // Handler function
    true|false,                   // Require login
    array(
        'rate_limit' => true,
        'capability' => 'read'
    )
);
```

### Send Success Response
```php
Fanfic_AJAX_Security::send_success_response(
    $data_array,                  // Data to return
    'Message'                     // User message
);
```

### Send Error Response
```php
Fanfic_AJAX_Security::send_error_response(
    'error_code',                 // Error identifier
    'Error message',              // Error text
    400                           // HTTP status
);
```

---

## JavaScript Pagination Pattern

### HTML Structure Needed
```html
<div class="fanfic-bookmarks-container">
    <div class="fanfic-bookmarks-list">
        <!-- Items render here -->
    </div>
    
    <div class="fanfic-bookmarks-pagination">
        <button class="fanfic-bookmark-page-button active" data-page="1">1</button>
        <button class="fanfic-bookmark-page-button" data-page="2">2</button>
        <!-- More buttons -->
    </div>
    
    <div class="fanfic-bookmarks-loading" style="display:none;">
        Loading...
    </div>
</div>
```

### Event Handler
```javascript
// Bind click handler
$(document).on('click', '.fanfic-bookmark-page-button', handleBookmarkPagination);

// Handler function
function handleBookmarkPagination(e) {
    e.preventDefault();
    const button = $(this);
    const page = button.data('page');
    
    if (button.hasClass('active')) return;
    
    // Show loading
    $('.fanfic-bookmarks-list').hide();
    $('.fanfic-bookmarks-loading').show();
    $('.fanfic-bookmark-page-button').prop('disabled', true);
    
    $.ajax({
        url: fanficData.ajaxUrl,
        type: 'POST',
        data: {
            action: 'fanfic_get_bookmarks',
            page: page,
            nonce: fanficData.nonce
        },
        success: function(response) {
            if (response.success) {
                renderBookmarks(response.data.bookmarks);
                updateActiveButton(page);
            }
        },
        complete: function() {
            $('.fanfic-bookmarks-loading').hide();
            $('.fanfic-bookmarks-list').show();
            $('.fanfic-bookmark-page-button').prop('disabled', false);
        }
    });
}

// Render bookmarks
function renderBookmarks(bookmarks) {
    let html = '';
    bookmarks.forEach(function(b) {
        html += '<div class="fanfic-bookmark-item">' + 
                '<h3>' + escapeHtml(b.post_title) + '</h3>' +
                '</div>';
    });
    $('.fanfic-bookmarks-list').html(html);
}

// Update active button
function updateActiveButton(page) {
    $('.fanfic-bookmark-page-button').removeClass('active');
    $('.fanfic-bookmark-page-button[data-page="' + page + '"]').addClass('active');
}
```

---

## Response Format

```json
{
  "success": true,
  "data": {
    "bookmarks": [
      {
        "post_id": 123,
        "bookmark_type": "story",
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "page": 1,
    "total_pages": 5,
    "total_count": 92,
    "has_more": true
  },
  "message": "Bookmarks loaded successfully."
}
```

---

## Key Points to Remember

1. **Parameter name:** Use `page` (1-indexed), NOT `offset`
2. **Calculation:** `offset = (page - 1) * per_page`
3. **has_more flag:** `page < total_pages`
4. **Replace, don't append:** Use `.html()` not `.append()`
5. **Cache keys:** Include user_id, page, per_page, type
6. **Security:** Use `Fanfic_AJAX_Security::register_ajax_handler()`
7. **Validation:** Validate page range (min:1, max:10)
8. **Loading states:** Disable buttons during request
9. **Error handling:** Show user-friendly error messages
10. **Nonce:** Included automatically by security wrapper

