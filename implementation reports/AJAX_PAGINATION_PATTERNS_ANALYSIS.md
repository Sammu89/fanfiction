# AJAX Pagination Patterns Analysis

## Overview
This document provides a comprehensive analysis of existing AJAX pagination implementations in the Fanfiction Manager plugin, specifically to inform "show more" pagination for bookmarks.

---

## 1. Existing AJAX Pagination Endpoints

### 1.1 Notifications (Primary Reference Implementation)
**File:** `includes/class-fanfic-ajax-handlers.php` (lines 666-741)

**AJAX Action:** `fanfic_get_notifications`

**Handler Method:** `Fanfic_AJAX_Handlers::ajax_get_notifications()`

**Request Parameters:**
- `page` (int, required) - Page number (1-indexed)
- `unread_only` (bool, optional) - Filter to unread only (default: true)

**Response Format (JSON):**
- `success` (bool) - Operation success
- `data.notifications` (array) - List of notification objects
- `data.page` (int) - Current page number
- `data.total_pages` (int) - Total pages available
- `data.total_count` (int) - Total notifications
- `data.has_more` (bool) - Whether more pages exist

**Backend Logic:**
- Per-page: 10 items
- Max pages: 5 (capped at 50 total notifications)
- Calculates offset: `($page - 1) * $per_page`
- Includes `has_more` flag: `$page < $total_pages`

---

## 2. Core Database Query Methods

### 2.1 Bookmarks - `get_user_bookmarks()`
**File:** `includes/class-fanfic-bookmarks.php` (lines 260-310)

**Method Signature:**
```php
public static function get_user_bookmarks( 
    $user_id, 
    $bookmark_type = null,
    $limit = 50, 
    $offset = 0 
)
```

**Features:**
- Supports filtering by bookmark type (story/chapter/all)
- Uses LIMIT/OFFSET for pagination
- Transient caching (5-minute TTL)
- Returns: Array of bookmark records with post_id, bookmark_type, created_at
- Ordered by created_at DESC (newest first)

**Cache Key:** `fanfic_user_bookmarks_[user_id]_[type]_[limit]_[offset]`

### 2.2 Bookmarks Count - `get_bookmarks_count()`
**File:** `includes/class-fanfic-bookmarks.php` (lines 320-360)

**Method Signature:**
```php
public static function get_bookmarks_count( $user_id, $bookmark_type = null )
```

**Features:**
- Returns total count for pagination calculations
- Transient caching (10-minute TTL)

---

## 3. JavaScript Pagination Handlers

### 3.1 Notifications Pagination Handler
**File:** `assets/js/fanfiction-frontend.js` (lines 1461-1520)

**Function:** `handleNotificationPagination(e)`

**Event Binding:**
```javascript
$(document).on('click', '.fanfic-notification-page-button', handleNotificationPagination);
```

**Workflow:**
1. Get page number from button `data-page` attribute
2. Check if already on this page (skip if same)
3. Show loading state
4. Disable pagination buttons
5. Send AJAX request
6. On success:
   - Replace notifications list (NOT append)
   - Update pagination button states
   - Update badge count
7. On error: Show error message
8. Finally: Hide loading, re-enable buttons

**Key Pattern - REPLACES, doesn't append:**
```javascript
function renderNotifications(notifications) {
    const notificationsList = $('.fanfic-notifications-list');
    
    if (notifications.length === 0) {
        showEmptyNotifications();
        return;
    }
    
    let html = '';
    notifications.forEach(function(notification) {
        html += buildNotificationHtml(notification);
    });
    
    notificationsList.html(html);  // REPLACE content
}
```

---

## 4. Response Format Patterns

### Standard AJAX Response Structure
```json
{
  "success": true,
  "data": {
    "items": [],
    "page": 1,
    "total_pages": 5,
    "total_count": 50,
    "has_more": true
  },
  "message": "Loaded successfully"
}
```

---

## 5. Implementation Pattern for Bookmarks

### A. AJAX Handler Registration
In `class-fanfic-ajax-handlers.php::init()`:

```php
Fanfic_AJAX_Security::register_ajax_handler(
    'fanfic_get_bookmarks',
    array( __CLASS__, 'ajax_get_bookmarks' ),
    true, // Require login
    array(
        'rate_limit'  => true,
        'capability'  => 'read',
    )
);
```

### B. AJAX Handler Method
```php
public static function ajax_get_bookmarks() {
    $params = Fanfic_AJAX_Security::get_ajax_parameters(
        array( 'page' ),
        array( 'bookmark_type' )
    );
    
    if ( is_wp_error( $params ) ) {
        Fanfic_AJAX_Security::send_error_response(...);
    }
    
    $page = absint( $params['page'] );
    $bookmark_type = isset( $params['bookmark_type'] ) 
        ? sanitize_text_field( $params['bookmark_type'] ) 
        : null;
    $user_id = get_current_user_id();
    
    if ( $page < 1 ) $page = 1;
    if ( $page > 10 ) $page = 10;
    
    $per_page = 20;
    $offset = ( $page - 1 ) * $per_page;
    
    $bookmarks = Fanfic_Bookmarks::get_user_bookmarks(
        $user_id,
        $bookmark_type,
        $per_page,
        $offset
    );
    
    $total_count = Fanfic_Bookmarks::get_bookmarks_count( $user_id, $bookmark_type );
    $total_pages = min( ceil( $total_count / $per_page ), 10 );
    
    Fanfic_AJAX_Security::send_success_response(
        array(
            'bookmarks'   => $bookmarks,
            'page'        => $page,
            'total_pages' => $total_pages,
            'total_count' => $total_count,
            'has_more'    => $page < $total_pages,
        ),
        __( 'Bookmarks loaded successfully.', 'fanfiction-manager' )
    );
}
```

### C. JavaScript Handler
```javascript
function handleBookmarkPagination(e) {
    e.preventDefault();
    const button = $(this);
    const page = button.data('page');
    
    if (button.hasClass('active')) return;
    
    showBookmarksLoading();
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
            if (response.success && response.data.bookmarks) {
                renderBookmarks(response.data.bookmarks);
                updatePaginationButtons(page);
            }
        },
        error: function() {
            showError('Failed to load bookmarks');
        },
        complete: function() {
            hideBookmarksLoading();
            $('.fanfic-bookmark-page-button').prop('disabled', false);
        }
    });
}

function renderBookmarks(bookmarks) {
    const list = $('.fanfic-bookmarks-list');
    
    if (bookmarks.length === 0) {
        list.html('<p>No bookmarks found</p>');
        return;
    }
    
    let html = '';
    bookmarks.forEach(function(bookmark) {
        html += buildBookmarkHtml(bookmark);
    });
    
    list.html(html); // REPLACE
}
```

---

## 6. File Paths Reference

| Component | File Path |
|-----------|-----------|
| AJAX Handler | `includes/class-fanfic-ajax-handlers.php` |
| Security | `includes/class-fanfic-ajax-security.php` |
| Bookmarks | `includes/class-fanfic-bookmarks.php` |
| Notifications | `includes/class-fanfic-notifications.php` |
| Frontend JS | `assets/js/fanfiction-frontend.js` |
| Interactions JS | `assets/js/fanfiction-interactions.js` |

