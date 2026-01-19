# Phase 10: Moderator Notes Modal - Flow Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     MODERATION QUEUE PAGE                           │
│  (includes/class-fanfic-moderation-table.php)                      │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ Renders buttons with:
                                    │ - data-report-id
                                    │ - data-nonce
                                    │ - class="mark-reviewed-button"
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     USER CLICKS BUTTON                              │
│                  "Mark as Reviewed"                                 │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ Event caught by
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              JAVASCRIPT (fanfiction-admin.js)                       │
│                                                                      │
│  openReviewedModal(e)                                               │
│  ├─ Extract report_id and nonce from button                         │
│  ├─ Build modal HTML dynamically                                    │
│  ├─ Append to document body                                         │
│  ├─ Fade in modal                                                   │
│  └─ Focus on textarea                                               │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    MODAL DISPLAYED                                  │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Mark Report as Reviewed                                  │     │
│  │                                                            │     │
│  │  Please describe what action you took:                    │     │
│  │                                                            │     │
│  │  Moderator Notes *                                         │     │
│  │  ┌──────────────────────────────────────────────────┐    │     │
│  │  │                                                   │    │     │
│  │  │  [Textarea for notes input]                      │    │     │
│  │  │                                                   │    │     │
│  │  └──────────────────────────────────────────────────┘    │     │
│  │  Maximum 500 characters                                   │     │
│  │                                                            │     │
│  │                          [Cancel]  [Submit]               │     │
│  └───────────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ User types notes
                                    │ and clicks Submit
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              JAVASCRIPT VALIDATION                                  │
│                                                                      │
│  submitModeratorNotes()                                             │
│  ├─ Check if notes is empty → Show error if empty                   │
│  ├─ Check if notes > 500 chars → Show error if too long            │
│  ├─ Disable submit button                                           │
│  ├─ Show "Submitting..." message                                    │
│  └─ Make AJAX call                                                  │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ AJAX POST
                                    │ action: 'fanfic_mark_reviewed'
                                    │ data: { report_id, notes, nonce }
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              SERVER-SIDE HANDLER (PHP)                              │
│  (includes/class-fanfic-moderation-table.php)                      │
│                                                                      │
│  Fanfic_Moderation_Ajax::ajax_mark_reviewed()                      │
│  ├─ Verify nonce (security check)                                   │
│  ├─ Check user capabilities                                         │
│  ├─ Validate report_id                                              │
│  ├─ Sanitize notes input                                            │
│  └─ Call mark_as_reviewed()                                         │
│                                                                      │
│  Fanfic_Moderation_Table::mark_as_reviewed($id, $notes)            │
│  ├─ Update wp_fanfic_reports table:                                │
│  │   ├─ status = 'reviewed'                                         │
│  │   ├─ moderator_id = current_user_id()                           │
│  │   └─ moderator_notes = sanitized_notes                          │
│  └─ Return success/failure                                          │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ Return JSON response:
                                    │ { success: true, data: {...} }
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              JAVASCRIPT HANDLES RESPONSE                            │
│                                                                      │
│  Success:                                                            │
│  ├─ Display success message                                         │
│  ├─ Wait 1 second                                                   │
│  ├─ Close modal with fadeOut()                                      │
│  └─ Reload page to show updated queue                               │
│                                                                      │
│  Error:                                                             │
│  ├─ Display error message                                           │
│  └─ Re-enable submit button                                         │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ Page reload
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              UPDATED MODERATION QUEUE                               │
│                                                                      │
│  Report now shows:                                                   │
│  ├─ Status: "Reviewed" (green badge)                                │
│  ├─ Reviewed By: [Moderator Name]                                   │
│  └─ "Mark as Reviewed" button hidden                                │
└─────────────────────────────────────────────────────────────────────┘
```

## View Report Details Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                USER CLICKS "View Report"                            │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              JAVASCRIPT                                             │
│  viewReportDetails()                                                │
│  ├─ Show loading modal                                              │
│  └─ AJAX request to 'fanfic_get_report_details'                    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              SERVER FETCHES DATA                                    │
│  ajax_get_report_details()                                          │
│  ├─ Verify nonce & capabilities                                     │
│  ├─ Fetch report from database                                      │
│  ├─ Format report data                                              │
│  └─ Return JSON with report details                                 │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│              JAVASCRIPT BUILDS HTML                                 │
│  buildReportDetailsHTML()                                           │
│  ├─ Create table with report info                                   │
│  ├─ Escape all HTML for security                                    │
│  └─ Update modal content                                            │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    DETAILS MODAL DISPLAYED                          │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  Report Details #123                                      │     │
│  │  ┌─────────────────────────────────────────────────────┐ │     │
│  │  │ Reported Content:  Story Title                      │ │     │
│  │  │ Content Type:      fanfiction_story                 │ │     │
│  │  │ Reported By:       Username                         │ │     │
│  │  │ Reason:            [Full reason text]               │ │     │
│  │  │ Status:            Reviewed                         │ │     │
│  │  │ Reported On:       2025-01-15 10:30                │ │     │
│  │  │ Last Updated:      2025-01-15 14:45                │ │     │
│  │  │ Reviewed By:       Moderator Name                   │ │     │
│  │  │ Moderator Notes:   [Action taken description]       │ │     │
│  │  └─────────────────────────────────────────────────────┘ │     │
│  │                                        [Close]            │     │
│  └───────────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────────┘
```

## Modal Close Events

```
User can close modal via:

1. Cancel Button → closeModal()
2. Close Button → closeModal()
3. ESC Key → closeModal()
4. Click Overlay → closeModal() (if clicked directly on overlay)

closeModal():
├─ Fade out modal (200ms)
├─ Remove modal from DOM
└─ Remove 'fanfic-admin-modal-open' class from body
```

## Security Layers

```
┌─────────────────────────────────────────────────────────────────────┐
│                     CLIENT-SIDE VALIDATION                          │
│  ├─ Required field check                                            │
│  ├─ Character limit enforcement (500 chars)                         │
│  └─ HTML escaping on display                                        │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     NONCE VERIFICATION                              │
│  ├─ Nonce created with 'fanfic_moderation_action'                  │
│  ├─ Sent with every AJAX request                                    │
│  └─ Verified server-side before processing                          │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     CAPABILITY CHECK                                │
│  ├─ User must have 'manage_options' OR                              │
│  └─ User must have 'moderate_fanfiction'                            │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     INPUT SANITIZATION                              │
│  ├─ sanitize_textarea_field() on notes                             │
│  ├─ absint() on report_id                                           │
│  └─ wp_unslash() on POST data                                       │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     DATABASE UPDATE                                 │
│  ├─ Using $wpdb->prepare() for SQL                                  │
│  └─ Type casting (%s, %d) enforced                                  │
└─────────────────────────────────────────────────────────────────────┘
```

## File Dependencies

```
fanfiction-manager.php (Main Plugin)
    │
    ├─ includes/class-fanfic-core.php
    │       │
    │       └─ includes/class-fanfic-admin.php
    │               │
    │               ├─ Enqueues: assets/js/fanfiction-admin.js
    │               ├─ Enqueues: assets/css/fanfiction-admin.css
    │               └─ Localizes: fanfictionAdmin object
    │
    └─ includes/class-fanfic-moderation-table.php
            │
            ├─ Fanfic_Moderation_Table (WP_List_Table)
            │   ├─ column_actions() - Renders buttons
            │   ├─ get_report_details() - Fetches report
            │   └─ mark_as_reviewed() - Updates database
            │
            └─ Fanfic_Moderation_Ajax
                ├─ ajax_get_report_details() - AJAX handler
                └─ ajax_mark_reviewed() - AJAX handler
```

## CSS Class Structure

```
.fanfic-admin-modal (Container)
    │
    ├─ .fanfic-admin-modal-overlay (Dark background)
    │
    └─ .fanfic-admin-modal-content (White box)
            │
            ├─ h2 (Title)
            │
            ├─ p (Description)
            │
            ├─ .fanfic-admin-modal-form
            │       │
            │       ├─ label (with .required)
            │       ├─ textarea#moderator-notes
            │       ├─ p.description
            │       └─ .fanfic-admin-modal-message
            │               │
            │               └─ p.success / p.error / p.info
            │
            ├─ .fanfic-report-details (For view modal)
            │       │
            │       └─ table.widefat
            │               │
            │               ├─ th (Labels)
            │               └─ td (Values)
            │
            └─ .fanfic-admin-modal-actions
                    │
                    ├─ button.fanfic-admin-modal-cancel
                    └─ button.fanfic-admin-modal-submit
```

## Event Handlers

```javascript
Document Ready
    │
    └─ init()
            │
            └─ initModerationActions()
                    │
                    ├─ .mark-reviewed-button click → openReviewedModal()
                    ├─ .view-report-button click → viewReportDetails()
                    ├─ .fanfic-admin-modal-close click → closeModal()
                    ├─ .fanfic-admin-modal-cancel click → closeModal()
                    ├─ .fanfic-admin-modal-overlay click → closeModal()
                    ├─ .fanfic-admin-modal-submit click → submitModeratorNotes()
                    └─ ESC key → closeModal()
```

## Database Schema

```sql
wp_fanfic_reports
├─ id (Primary Key)
├─ reported_item_id
├─ reported_item_type
├─ reporter_id
├─ reason
├─ status (pending/reviewed/dismissed)
├─ moderator_id (Updated by mark_as_reviewed)
├─ moderator_notes (Updated by mark_as_reviewed) ← NEW DATA
├─ created_at
└─ updated_at (Auto-updated)
```
