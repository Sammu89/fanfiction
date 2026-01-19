# Phase 10: Code Reference - Quick Lookup

## JavaScript Modal Creation

### Opening the Modal
```javascript
// File: assets/js/fanfiction-admin.js
// Lines: 53-89

function openReviewedModal(e) {
    e.preventDefault();

    var $button = $(this);
    var reportId = $button.data('report-id');
    var nonce = $button.data('nonce');

    // Create modal HTML
    var modalHtml = '<div class="fanfic-admin-modal">...</div>';

    // Append and show
    $('body').append(modalHtml);
    $('.fanfic-admin-modal').fadeIn(200);
    $('body').addClass('fanfic-admin-modal-open');

    // Focus textarea
    $('#moderator-notes').focus();
}
```

### Submitting Notes via AJAX
```javascript
// File: assets/js/fanfiction-admin.js
// Lines: 183-240

function submitModeratorNotes(e) {
    e.preventDefault();

    var notes = $('#moderator-notes').val().trim();

    // Validate
    if (!notes || notes.length > 500) {
        // Show error
        return;
    }

    // AJAX call
    $.ajax({
        url: fanfictionAdmin.ajaxUrl,
        type: 'POST',
        data: {
            action: 'fanfic_mark_reviewed',
            nonce: nonce,
            report_id: reportId,
            notes: notes
        },
        success: function(response) {
            if (response.success) {
                // Show success, wait, reload
                setTimeout(function() {
                    closeModal();
                    location.reload();
                }, 1000);
            }
        }
    });
}
```

## PHP AJAX Handler

### Mark as Reviewed Handler
```php
// File: includes/class-fanfic-moderation-table.php
// Lines: 903-928

public static function ajax_mark_reviewed() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) ||
         ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'fanfic_moderation_action' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.' ) ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) &&
         ! current_user_can( 'moderate_fanfiction' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.' ) ) );
    }

    // Get and validate data
    $report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
    $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

    if ( ! $report_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid report ID.' ) ) );
    }

    // Update database
    $result = Fanfic_Moderation_Table::mark_as_reviewed( $report_id, $notes );

    if ( $result ) {
        wp_send_json_success( array( 'message' => __( 'Report marked as reviewed.' ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Failed to update report.' ) ) );
    }
}
```

### Database Update Method
```php
// File: includes/class-fanfic-moderation-table.php
// Lines: 798-816

public static function mark_as_reviewed( $report_id, $notes = '' ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'fanfic_reports';

    $result = $wpdb->update(
        $table_name,
        array(
            'status'          => 'reviewed',
            'moderator_id'    => get_current_user_id(),
            'moderator_notes' => sanitize_textarea_field( $notes ),
        ),
        array( 'id' => absint( $report_id ) ),
        array( '%s', '%d', '%s' ),
        array( '%d' )
    );

    return false !== $result;
}
```

## CSS Modal Styles

### Core Modal Structure
```css
/* File: assets/css/fanfiction-admin.css */
/* Lines: 57-91 */

.fanfic-admin-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 160000;
    display: none;
    align-items: center;
    justify-content: center;
}

.fanfic-admin-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    z-index: -1;
}

.fanfic-admin-modal-content {
    background: #fff;
    border-radius: 4px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 1;
}
```

### Form Input Styles
```css
/* File: assets/css/fanfiction-admin.css */
/* Lines: 122-138 */

.fanfic-admin-modal-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    resize: vertical;
    min-height: 120px;
    box-sizing: border-box;
}

.fanfic-admin-modal-form textarea:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}
```

### Message Styles
```css
/* File: assets/css/fanfiction-admin.css */
/* Lines: 173-189 */

.fanfic-admin-modal-message p.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.fanfic-admin-modal-message p.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.fanfic-admin-modal-message p.info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
```

## HTML Button Markup

### Action Buttons (Already Implemented)
```php
// File: includes/class-fanfic-moderation-table.php
// Lines: 343-350

// Mark as Reviewed action (if not already reviewed)
if ( 'reviewed' !== $item->status ) {
    $actions[] = sprintf(
        '<a href="#" class="button button-small mark-reviewed-button" data-report-id="%d" data-nonce="%s">%s</a>',
        $report_id,
        esc_attr( $nonce ),
        __( 'Mark as Reviewed', 'fanfiction-manager' )
    );
}
```

### View Report Button
```php
// File: includes/class-fanfic-moderation-table.php
// Lines: 336-341

// View Report action
$actions[] = sprintf(
    '<a href="#" class="button button-small view-report-button" data-report-id="%d" data-nonce="%s">%s</a>',
    $report_id,
    esc_attr( $nonce ),
    __( 'View Report', 'fanfiction-manager' )
);
```

## Script Localization

### Enqueue with Nonces
```php
// File: includes/class-fanfic-admin.php
// Lines: 158-174

// Prepare localization data
$localize_data = array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'fanfiction_admin_nonce' ),
);

// Add moderation-specific nonce if on moderation page
if ( strpos( $hook, 'fanfiction-moderation' ) !== false ) {
    $localize_data['moderationNonce'] = wp_create_nonce( 'fanfic_moderation_action' );
}

// Localize script with data
wp_localize_script(
    'fanfiction-admin',
    'fanfictionAdmin',
    $localize_data
);
```

## AJAX Registration

### Register AJAX Actions
```php
// File: includes/class-fanfic-moderation-table.php
// Lines: 832-835

public static function init() {
    add_action( 'wp_ajax_fanfic_get_report_details', array( __CLASS__, 'ajax_get_report_details' ) );
    add_action( 'wp_ajax_fanfic_mark_reviewed', array( __CLASS__, 'ajax_mark_reviewed' ) );
}

// Initialize AJAX handlers
Fanfic_Moderation_Ajax::init();
```

## HTML Escaping (Security)

### JavaScript HTML Escaping
```javascript
// File: assets/js/fanfiction-admin.js
// Lines: 274-283

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}
```

### Usage in Report Details
```javascript
// File: assets/js/fanfiction-admin.js
// Line 157

var postLink = report.post_link ?
    '<a href="' + escapeHtml(report.post_link) + '" target="_blank">' +
    escapeHtml(report.post_title) + '</a>' :
    escapeHtml(report.post_title);
```

## Event Handler Registration

### Document Ready Setup
```javascript
// File: assets/js/fanfiction-admin.js
// Lines: 14-47

function initModerationActions() {
    // Mark as Reviewed button (opens modal)
    $(document).on('click', '.mark-reviewed-button', openReviewedModal);

    // View Report button
    $(document).on('click', '.view-report-button', viewReportDetails);

    // Modal close handlers
    $(document).on('click', '.fanfic-admin-modal-close, .fanfic-admin-modal-cancel', closeModal);
    $(document).on('click', '.fanfic-admin-modal-overlay', function(e) {
        if (e.target === this) {
            closeModal.call(this, e);
        }
    });

    // Submit moderator notes
    $(document).on('click', '.fanfic-admin-modal-submit', submitModeratorNotes);

    // ESC key to close modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('.fanfic-admin-modal:visible').length) {
            closeModal();
        }
    });
}
```

## Validation Examples

### Client-Side Validation
```javascript
// File: assets/js/fanfiction-admin.js
// Lines: 194-205

// Validate notes
if (!notes) {
    $message.html('<p class="error">Please provide moderator notes describing the action taken.</p>');
    $textarea.focus();
    return;
}

if (notes.length > 500) {
    $message.html('<p class="error">Moderator notes must be 500 characters or less.</p>');
    $textarea.focus();
    return;
}
```

### Server-Side Validation
```php
// File: includes/class-fanfic-moderation-table.php
// Lines: 914-919

$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

if ( ! $report_id ) {
    wp_send_json_error( array( 'message' => __( 'Invalid report ID.', 'fanfiction-manager' ) ) );
}
```

## Modal Close Function

### Complete Close Handler
```javascript
// File: assets/js/fanfiction-admin.js
// Lines: 245-257

function closeModal(e) {
    if (e) {
        e.preventDefault();
    }

    var $modal = $('.fanfic-admin-modal:visible');

    $modal.fadeOut(200, function() {
        $modal.remove();
    });

    $('body').removeClass('fanfic-admin-modal-open');
}
```

## Complete Modal HTML Structure

```html
<div class="fanfic-admin-modal" data-report-id="123" data-nonce="abc123">
    <div class="fanfic-admin-modal-overlay"></div>
    <div class="fanfic-admin-modal-content">
        <h2>Mark Report as Reviewed</h2>
        <p>Please describe what action you took to resolve this report:</p>

        <div class="fanfic-admin-modal-form">
            <label for="moderator-notes">
                Moderator Notes <span class="required">*</span>
            </label>
            <textarea
                id="moderator-notes"
                rows="5"
                maxlength="500"
                placeholder="Describe the action taken (e.g., Content removed, Warning issued, No action needed, etc.)">
            </textarea>
            <p class="description">
                Maximum 500 characters. This will be stored in the moderation log.
            </p>
            <div class="fanfic-admin-modal-message"></div>
        </div>

        <div class="fanfic-admin-modal-actions">
            <button type="button" class="button fanfic-admin-modal-cancel">Cancel</button>
            <button type="button" class="button button-primary fanfic-admin-modal-submit">Submit</button>
        </div>
    </div>
</div>
```

## Testing Snippets

### Test Modal Opening in Browser Console
```javascript
// Simulate button click
jQuery('.mark-reviewed-button').first().trigger('click');
```

### Test AJAX Call Directly
```javascript
jQuery.ajax({
    url: fanfictionAdmin.ajaxUrl,
    type: 'POST',
    data: {
        action: 'fanfic_mark_reviewed',
        nonce: fanfictionAdmin.moderationNonce,
        report_id: 1,
        notes: 'Test note'
    },
    success: function(response) {
        console.log('Success:', response);
    },
    error: function(xhr, status, error) {
        console.log('Error:', error);
    }
});
```

### Check if Scripts Loaded
```javascript
// In browser console
console.log('Admin object:', fanfictionAdmin);
console.log('jQuery loaded:', typeof jQuery !== 'undefined');
console.log('Modal functions:', typeof openReviewedModal);
```

## Database Query Example

### Check Report Status After Update
```sql
-- Direct database query
SELECT
    id,
    status,
    moderator_id,
    moderator_notes,
    updated_at
FROM wp_fanfic_reports
WHERE id = 1;
```

## WordPress Admin Functions

### Check User Capabilities
```php
// Check if user can moderate
if ( current_user_can( 'moderate_fanfiction' ) ) {
    // Allow moderation
}

if ( current_user_can( 'manage_options' ) ) {
    // Allow admin access
}
```

### Create Nonce
```php
// Create nonce for moderation actions
$nonce = wp_create_nonce( 'fanfic_moderation_action' );

// Verify nonce
if ( wp_verify_nonce( $nonce, 'fanfic_moderation_action' ) ) {
    // Nonce is valid
}
```

## Common Issues and Solutions

### Issue: Modal doesn't open
**Solution:** Check if JavaScript is loaded and button has correct class
```javascript
// Browser console
jQuery('.mark-reviewed-button').length  // Should be > 0
```

### Issue: AJAX returns 400/403 error
**Solution:** Check nonce and user permissions
```javascript
// Verify nonce is being sent
console.log(fanfictionAdmin.moderationNonce);
```

### Issue: Notes not saving
**Solution:** Check database field and sanitization
```php
// Verify field exists
global $wpdb;
$wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}fanfic_reports LIKE 'moderator_notes'");
```

### Issue: Modal styling broken
**Solution:** Verify CSS file is enqueued
```php
// Check if style is registered
wp_style_is('fanfiction-admin', 'enqueued');
```
