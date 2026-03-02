# Block/Report Moderation Messaging System — Implementation Plan

## Context

Authors whose stories, chapters, or accounts are restricted (blocked/suspended) currently have no way to understand why or communicate with moderators. Suspension shows no reason, chapter blocks show generic text, and story blocks are inconsistent across pages. The moderation log schema also has a bug preventing chapter log entries.

This plan delivers: author-visible restriction motives, a "Message Moderation" action, a moderator Messages tab with inline actions, and full audit logging — all as a separate system from the existing public report queue.

## UX Decisions

- **One message per blocked item** — button becomes "Message Sent — Awaiting Review"
- **Author notification** via existing `wp_fanfic_notifications` on moderator action
- **Mod UI**: Inline expandable rows in WP_List_Table
- **Sub-tabs**: Unread | Ignored | Resolved | All
- **Direct unblock** from message row
- **Chapter block reason**: Inline dropdown + optional text
- **Suspension reason**: Modal before confirming ban
- **Ignore**: Moves to Ignored sub-tab

---

## New Files (2)

| File | Purpose |
|------|---------|
| `includes/class-fanfic-moderation-messages.php` | Static CRUD service for moderation messages |
| `includes/class-fanfic-messages-table.php` | WP_List_Table for admin Messages tab |

## Modified Files (18)

| File | Changes |
|------|---------|
| `includes/class-fanfic-database-setup.php` | New messages table + ALTER log target_type to varchar(50) |
| `includes/class-fanfic-core.php` | Load new classes, update suspension notice, add localized strings |
| `includes/class-fanfic-ajax-handlers.php` | Add 2 AJAX endpoints (author submit + mod action) |
| `includes/class-fanfic-notifications.php` | Add 3 notification type constants |
| `includes/class-fanfic-moderation.php` | Add Messages tab + render_messages_tab() + update Log filters |
| `includes/class-fanfic-moderation-log.php` | Add new action labels |
| `includes/class-fanfic-users-admin.php` | Accept suspension_reason in ban handler |
| `includes/functions.php` | Add helpers: chapter msg, suspension msg, restriction context, banner render |
| `templates/template-story-form.php` | Replace hardcoded banner, update chapter block button |
| `templates/template-chapter-form.php` | Replace both hardcoded banners |
| `templates/template-dashboard.php` | Add message button to blocked story rows |
| `templates/template-story-view.php` | Add author-specific blocked banner |
| `templates/template-chapter-view.php` | Add author-specific blocked banner |
| `assets/js/fanfiction-interactions.js` | Message modal handler, chapter block AJAX |
| `assets/js/fanfiction-admin.js` | Ban reason modal, message table expand/actions |
| `assets/css/fanfiction-frontend.css` | Modal + button styles |
| `assets/css/fanfiction-admin.css` | Inline expand row styles, message badges |
| `includes/class-fanfic-cron-cleanup.php` | Add old message cleanup |

---

## Phase 1: Database & Schema Fixes

### 1.1 ALTER moderation log target_type
- **File**: `includes/class-fanfic-database-setup.php` (~line 562)
- Change `target_type enum('user','story')` to `varchar(50) NOT NULL`
- Add migration in `activate()` flow: `ALTER TABLE ... MODIFY COLUMN target_type varchar(50) NOT NULL`
- This fixes the existing bug where chapter log entries silently fail

### 1.2 Create `wp_fanfic_moderation_messages` table
- **File**: `includes/class-fanfic-database-setup.php` (after line 577)
- Schema:
  - `id` bigint(20) UNSIGNED AUTO_INCREMENT PK
  - `author_id` bigint(20) UNSIGNED NOT NULL
  - `target_type` varchar(50) NOT NULL — 'story', 'chapter', 'user'
  - `target_id` bigint(20) UNSIGNED NOT NULL
  - `message` text NOT NULL
  - `status` varchar(20) NOT NULL DEFAULT 'unread' — unread/ignored/resolved/deleted
  - `moderator_id` bigint(20) UNSIGNED DEFAULT NULL
  - `moderator_note` text DEFAULT NULL
  - `created_at` datetime DEFAULT CURRENT_TIMESTAMP
  - `updated_at` datetime DEFAULT NULL
  - Keys: `idx_author`, `idx_target(target_type, target_id)`, `idx_status`, `idx_created`
- One-active-message rule enforced at app layer (not unique key) — check status IN ('unread','ignored') before insert

---

## Phase 2: Backend Service Class

### 2.1 Create `includes/class-fanfic-moderation-messages.php`
- Static utility class (like `Fanfic_Moderation_Log`)
- Methods:
  - `create_message($author_id, $target_type, $target_id, $message_text)` — validates target is restricted, checks no active message exists, inserts row, returns ID or WP_Error
  - `has_active_message($author_id, $target_type, $target_id)` — bool, checks status IN ('unread','ignored')
  - `get_message($id)` — single row or null
  - `get_messages($args)` — filterable list (status, target_type, limit, offset, orderby)
  - `count_messages($args)` — int for pagination/badges
  - `get_status_counts()` — assoc array for sub-tab counts
  - `update_status($message_id, $new_status, $moderator_id, $note)` — updates row, sets updated_at
  - `cleanup_old_messages($days_old = 90)` — deletes resolved/deleted messages older than N days

### 2.2 Register in `load_dependencies()`
- **File**: `includes/class-fanfic-core.php` (~line 257)
- Add `require_once` after moderation-log require (always-loaded, needed for frontend AJAX)

---

## Phase 3: Reason Capture Improvements

### 3.1 Add suspension reason labels
- **File**: `includes/functions.php`
- Add `fanfic_get_suspension_reason_labels()` — returns array: `tos_violation`, `harassment`, `spam`, `ban_evasion`, `inappropriate`, `other`
- Add `fanfic_get_suspension_reason_label($reason)` — single lookup

### 3.2 Add suspension reason to ban handler
- **File**: `includes/class-fanfic-users-admin.php` (`ajax_ban_user()`, ~line 1322)
- Accept `suspension_reason` and `suspension_reason_text` from POST
- After existing meta writes, add: `update_user_meta($user_id, 'fanfic_suspension_reason', $reason_code)` and `update_user_meta($user_id, 'fanfic_suspension_reason_text', $reason_text)`

### 3.3 Add ban reason modal in admin JS
- **File**: `assets/js/fanfiction-admin.js` (~line 1020)
- Replace `confirm()` with a `.fanfic-admin-modal` containing: reason dropdown (from localized labels) + optional textarea + Cancel/Confirm buttons
- Pass `suspension_reason` + `suspension_reason_text` in AJAX POST

### 3.4 Convert chapter block to reason-aware AJAX
- **File**: `includes/class-fanfic-core.php` — keep existing `handle_chapter_block_toggle()` but enhance it
- Accept `block_reason` parameter (GET or convert to POST)
- Pass reason to `fanfic_block_chapter()` call instead of hardcoded `'manual'`
- **File**: `templates/template-story-form.php` (~line 1531)
- When blocking: show inline dropdown of `fanfic_get_block_reason_labels()` before triggering
- When unblocking: keep simple confirm
- **File**: `assets/js/fanfiction-interactions.js` — add handler for chapter block reason dropdown

---

## Phase 4: Canonical Restriction Context Helpers

### 4.1 Add `fanfic_get_blocked_chapter_message($chapter_id)`
- **File**: `includes/functions.php`
- Parallel to existing `fanfic_get_blocked_story_message()` (line 34)
- Reads `_fanfic_block_type`, `_fanfic_block_reason`, `_fanfic_blocked_timestamp` from chapter meta
- Returns human-readable string

### 4.2 Add `fanfic_get_suspension_message($user_id)`
- **File**: `includes/functions.php`
- Reads `fanfic_suspension_reason`, `fanfic_suspension_reason_text`, `fanfic_banned_at` from user meta
- Returns human-readable string

### 4.3 Add `fanfic_get_restriction_context($target_type, $target_id)`
- **File**: `includes/functions.php`
- Returns structured array: `is_restricted`, `restriction_type`, `target_type`, `target_id`, `reason_message`, `has_active_message`, `owner_id`
- Delegates to story/chapter/suspension helpers internally
- Used by banners and message modal

---

## Phase 5: Author-Facing Banners

### 5.1 Create `fanfic_render_restriction_banner($context, $nav_buttons)`
- **File**: `includes/functions.php`
- Renders the standard `.fanfic-message.fanfic-message-error.fanfic-blocked-notice` div
- Contents: icon + title (Story Blocked/Chapter Blocked/Account Suspended) + reason + timestamp + info text + nav buttons + "Message Moderation" button (or "Message Sent — Awaiting Review" if active message exists)
- Also renders the message modal HTML once per page (static flag)

### 5.2 Update story form banner
- **File**: `templates/template-story-form.php` (lines 120-141)
- Replace hardcoded banner with `fanfic_render_restriction_banner()` call
- Nav buttons: "Back to Dashboard" + "View Story"

### 5.3 Update chapter form banners
- **File**: `templates/template-chapter-form.php` (lines 344-410)
- Replace both gates (parent story blocked + chapter blocked) with `fanfic_render_restriction_banner()` calls
- Chapter banner now shows actual reason instead of generic text

### 5.4 Update dashboard blocked story row
- **File**: `templates/template-dashboard.php` (lines 351-360)
- After disabled Edit/Add Chapter/Delete buttons, add "Message Moderation" / "Message Sent" button
- Use `Fanfic_Moderation_Messages::has_active_message()` for state

### 5.5 Update suspension notice
- **File**: `includes/class-fanfic-core.php` (lines 1167-1213)
- Add suspension reason from `fanfic_get_suspension_message()`
- Add "Message Moderation" button at end of notice bar
- Wrap strings in `__()` for i18n

### 5.6 Enhance story-view and chapter-view for authors
- **File**: `templates/template-story-view.php` (lines 139-148)
- For the story author viewing their blocked story: replace generic "hidden from public" with restriction banner including reason + message button
- **File**: `templates/template-chapter-view.php` (lines 179-207)
- Same approach for chapter author viewing blocked chapter

---

## Phase 6: Author Message Modal + AJAX Endpoint

### 6.1 Modal HTML
- Rendered by `fanfic_render_restriction_banner()` (static flag prevents duplicates)
- ID: `#fanfic-mod-message-modal`
- Structure: `.fanfic-modal` > `.fanfic-modal-overlay` + `.fanfic-modal-content` (matches report modal pattern)
- Contents: title "Message Moderation" + description + `<textarea maxlength="1000">` + live char counter + hidden inputs (target_type, target_id) + Cancel/Submit buttons + form message area

### 6.2 Register AJAX endpoint
- **File**: `includes/class-fanfic-ajax-handlers.php` (in `init()`)
- Register `fanfic_send_moderation_message` via `Fanfic_AJAX_Security::register_ajax_handler()` — `require_login: true`, `capability: 'read'`, `rate_limit: true`
- Handler `ajax_send_moderation_message()`:
  - Get params: `target_type`, `target_id`, `message`
  - Validate target_type is story/chapter/user
  - Validate current user owns the target
  - Validate target is still restricted
  - Validate message 1-1000 chars after `sanitize_textarea_field()`
  - Call `Fanfic_Moderation_Messages::create_message()`
  - Return success/error response

### 6.3 Frontend JS handler
- **File**: `assets/js/fanfiction-interactions.js`
- Add `initModerationMessage()` called from main `init()`
- Click `.fanfic-message-mod-btn` → set modal hidden inputs from button data attrs → open modal
- Submit `#fanfic-mod-message-form` → AJAX POST → on success: close modal, swap button to "Message Sent" state
- Live char counter on textarea `input` event

### 6.4 Localized strings
- **File**: `includes/class-fanfic-core.php` (fanficAjax strings, ~line 1340)
- Add: `modMessageSent`, `modMessageError`, `modMessageTooLong`, `modMessageEmpty`

---

## Phase 7: Admin Messages Tab + Table

### 7.1 Add Messages tab to moderation page
- **File**: `includes/class-fanfic-moderation.php`
- Add `'messages'` to `$allowed_tabs` (line 56)
- Add `<a>` in nav-tab-wrapper with unread count badge
- Add `render_messages_tab()`: sub-tabs (Unread/Ignored/Resolved/All) with counts, then `Fanfic_Messages_Table` display

### 7.2 Create `includes/class-fanfic-messages-table.php`
- Extends `WP_List_Table` (same pattern as `Fanfic_Moderation_Table`)
- Columns: expand toggle | author | target | reason | date | status badge | actions
- `prepare_items()` calls `Fanfic_Moderation_Messages::get_messages()` + `count_messages()`
- Override `single_row()` to output standard `<tr>` + hidden detail `<tr class="fanfic-message-detail-row">` containing: full message text, block details, moderator note textarea, action buttons (Unblock/Ignore/Delete)
- Column renderers resolve author display name, target title+link, reason label, date format, status badge

### 7.3 Register in load_dependencies
- **File**: `includes/class-fanfic-core.php` — add `require_once` in `is_admin()` block

---

## Phase 8: Moderator Actions

### 8.1 Register mod action AJAX endpoint
- **File**: `includes/class-fanfic-ajax-handlers.php`
- Register `fanfic_mod_message_action` — `require_login: true`, `capability: 'moderate_fanfiction'`, `rate_limit: true`
- Handler `ajax_mod_message_action()`:
  - Params: `message_id`, `action_type` ('unblock'/'ignore'/'delete'), `moderator_note`
  - Load message, validate exists and actionable
  - Switch on action_type:
    - **unblock**: `fanfic_remove_post_block()` for story/chapter; reverse ban logic for user; update message status → 'resolved'
    - **ignore**: update message status → 'ignored'
    - **delete**: update message status → 'deleted'
  - Send notification + log (Phase 9)
  - Return success response

### 8.2 Admin JS for inline expand + actions
- **File**: `assets/js/fanfiction-admin.js`
- Click `.fanfic-expand-message` → `slideToggle()` next `.fanfic-message-detail-row`
- Click `.fanfic-msg-action-unblock` → confirm → AJAX POST → fade row / update badge
- Click `.fanfic-msg-action-ignore` → AJAX POST → fade row
- Click `.fanfic-msg-action-delete` → confirm → AJAX POST → remove row

### 8.3 Admin CSS
- **File**: `assets/css/fanfiction-admin.css`
- `.fanfic-message-detail-row td` — full-width cell, light bg, padding
- `.fanfic-expand-message` — toggle button with rotate animation
- `.fanfic-msg-actions` — button row styling
- Status badges: `.status-unread` (yellow), `.status-ignored` (gray), `.status-resolved` (green)

### 8.4 Localized admin strings
- **File**: `includes/class-fanfic-admin.php` (~line 326)
- Add moderation page strings to `fanfictionAdmin`: `confirmUnblock`, `confirmDelete`, `actionSuccess`, `actionError`

---

## Phase 9: Notifications + Logging

### 9.1 Add notification type constants
- **File**: `includes/class-fanfic-notifications.php`
- Add constants: `TYPE_MOD_MESSAGE_UNBLOCKED`, `TYPE_MOD_MESSAGE_IGNORED`, `TYPE_MOD_MESSAGE_DELETED`
- Add to `$valid_types` whitelist in `create_notification()` (~line 141)

### 9.2 Send notifications from mod action handler
- In `ajax_mod_message_action()` (Phase 8.1):
  - **Unblock**: persistent notification — "Your [story/chapter] '[title]' has been unblocked. [mod note]"
  - **Ignore**: notification — "Your message regarding '[title]' has been reviewed. Status unchanged."
  - **Delete**: no notification

### 9.3 Log mod actions
- Unblock: already logged by existing hooks (`fanfic_story_unblocked`, etc.)
- Ignore/Delete: call `Fanfic_Moderation_Log::insert()` with actions `message_ignored` / `message_deleted`

### 9.4 Add action labels to log
- **File**: `includes/class-fanfic-moderation-log.php` (`get_action_label()`, ~line 428)
- Add: `'message_ignored' => 'Ignored Author Message'`, `'message_deleted' => 'Deleted Author Message'`

### 9.5 Update Log tab filters
- **File**: `includes/class-fanfic-moderation.php` (`render_log_tab()`, ~line 295)
- Add `'chapter'` to target filter dropdown
- Add `elseif ('chapter' === $log['target_type'])` branch in log display for chapter name/link resolution

---

## Phase 10: Integration & Cleanup

### 10.1 Frontend CSS
- **File**: `assets/css/fanfiction-frontend.css`
- `#fanfic-mod-message-modal` styles (matches `#fanfic-report-modal` pattern)
- `.fanfic-message-mod-btn` button styling
- `.fanfic-message-sent-badge` disabled state
- `.fanfic-char-counter` styling

### 10.2 Old message cleanup
- **File**: `includes/class-fanfic-cron-cleanup.php`
- Call `Fanfic_Moderation_Messages::cleanup_old_messages()` in daily cleanup cron

### 10.3 Edge cases
- Message created → target unblocked elsewhere → admin Messages tab shows "Already unblocked" indicator, disables Unblock button
- Author submits message → restriction removed before mod sees it → message stays readable but Unblock is disabled
- `create_message()` re-checks restriction at insert time

---

## Verification Plan

### Author-side testing
1. Block a story with a reason → verify banner shows reason on dashboard, story form, story view
2. Block a chapter with a reason → verify banner shows reason on chapter form, chapter view
3. Suspend a user with a reason → verify suspension notice shows reason
4. Click "Message Moderation" → modal opens with correct target context
5. Submit empty / >1000 char message → fails with error
6. Submit valid message → succeeds, button changes to "Message Sent"
7. Try submitting again → blocked (one per item)

### Moderator-side testing
1. Messages tab loads with correct sub-tab counts
2. Click expand → message detail row slides open
3. Unblock → target is unblocked, message moves to Resolved, author gets notification, log entry created
4. Ignore → message moves to Ignored sub-tab, author gets notification, log entry created
5. Delete → message removed, no notification, log entry created
6. Message whose target was already unblocked → Unblock button disabled

### Regression testing
1. Public report submission still works (separate system)
2. Report queue tab unaffected
3. Blocked content still hidden from public readers
4. Log tab now shows chapter entries correctly (schema fix)
5. Existing block/unblock flows still work




---------------
WHAT WAS ALREADY IMPLEMENTED
---------------

 Now I'll implement Phase 1 directly and use parallel agents for the big new files.

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Added 1 line, removed 1 line
      48     * @since 1.0.0
      49     * @var string
      50     */
      51 -  const DB_VERSION = '2.2.2';
      51 +  const DB_VERSION = '2.3.0';
      52
      53    /**
      54     * Option name for database version tracking

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Error editing file

● Read 1 file (ctrl+o to expand)

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Added 1 line, removed 1 line
      563        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      564        actor_id bigint(20) UNSIGNED NOT NULL,
      565        action varchar(50) NOT NULL,
      566 -      target_type enum('user','story') NOT NULL,
      566 +      target_type varchar(50) NOT NULL,
      567        target_id bigint(20) UNSIGNED NOT NULL,
      568        reason text DEFAULT NULL,
      569        created_at datetime DEFAULT CURRENT_TIMESTAMP,

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Error editing file

● Bash(cat -A "C:\Users\Sammu\Local Sites\testando\app\public\wp-content\plugins\fanfiction\includes\class-fanfic-database-setup.php" | sed -n '577,582p')
  ⎿  ^I^I$result = dbDelta( $sql_moderation_log );$
     ^I^Iif ( empty( $result ) || ! self::verify_table_exists( $table_moderation_log ) ) {$
     ^I^I^I$errors[] = 'Failed to create moderation_log table';$
     … +3 lines (ctrl+o to expand)

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Added 25 lines
      579        $errors[] = 'Failed to create moderation_log table';
      580      }
      581
      582 +    // 23. Moderation Messages Table
      583 +    $table_mod_messages = $prefix . 'fanfic_moderation_messages';
      584 +    $sql_mod_messages   = "CREATE TABLE IF NOT EXISTS {$table_mod_messages} (
      585 +      id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      586 +      author_id bigint(20) UNSIGNED NOT NULL,
      587 +      target_type varchar(50) NOT NULL,
      588 +      target_id bigint(20) UNSIGNED NOT NULL,
      589 +      message text NOT NULL,
      590 +      status varchar(20) NOT NULL DEFAULT 'unread',
      591 +      moderator_id bigint(20) UNSIGNED DEFAULT NULL,
      592 +      moderator_note text DEFAULT NULL,
      593 +      created_at datetime DEFAULT CURRENT_TIMESTAMP,
      594 +      updated_at datetime DEFAULT NULL,
      595 +      PRIMARY KEY  (id),
      596 +      KEY idx_author (author_id),
      597 +      KEY idx_target (target_type, target_id),
      598 +      KEY idx_status (status),
      599 +      KEY idx_created (created_at)
      600 +    ) $charset_collate;";
      601 +
      602 +    $result = dbDelta( $sql_mod_messages );
      603 +    if ( empty( $result ) || ! self::verify_table_exists( $table_mod_messages ) ) {
      604 +      $errors[] = 'Failed to create moderation_messages table';
      605 +    }
      606 +
      607      // All tables created successfully, proceed to seed default data if needed.
      608
      609      // Return errors if any

● Now add the migration in maybe_run_migrations():

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Added 12 lines
      1024        self::ensure_reports_table();
      1025      }
      1026
      1027 +    // v2.3.0: Fix moderation_log target_type from enum to varchar(50) to support 'chapter'.
      1028 +    if ( version_compare( $current_version, '2.3.0', '<' ) ) {
      1029 +      global $wpdb;
      1030 +      $log_table = $wpdb->prefix . 'fanfic_moderation_log';
      1031 +      if ( self::verify_table_exists( $log_table ) ) {
      1032 +        $col = $wpdb->get_row( "SHOW COLUMNS FROM {$log_table} LIKE 'target_type'" );
      1033 +        if ( $col && false !== strpos( strtolower( $col->Type ), 'enum' ) ) {
      1034 +          $wpdb->query( "ALTER TABLE {$log_table} MODIFY COLUMN target_type varchar(50) NOT NULL" );
      1035 +        }
      1036 +      }
      1037 +    }
      1038 +
      1039    }
      1040
      1041    /**

● Also update the class doc comment to include the new table:

● Update(includes\class-fanfic-database-setup.php)
  ⎿  Added 1 line
      37   * - wp_fanfic_story_filter_map: Pre-computed filter facets map (NEW in 1.5.2)
      38   * - wp_fanfic_reports: Content reports queue
      39   * - wp_fanfic_moderation_log: Moderation action log (NEW in 1.2.0)
      40 + * - wp_fanfic_moderation_messages: Author moderation messages (NEW in 2.3.0)
      41   *
      42   * @since 1.0.0
      43   */

● Now launch parallel agents for the large new files while I work on smaller changes directly.

● 2 Agent agents launched (ctrl+o to expand)
   ├─ Create moderation messages service class
   │  ⎿  Running in the background
   └─ Create messages WP_List_Table class
      ⎿  Running in the background

● Now working on the smaller changes while agents run. Starting with notifications constants and moderation-log labels:

● Update(includes\class-fanfic-notifications.php)
  ⎿  Added 3 lines
      42    const TYPE_STORY_STATUS_CHANGE = 'story_status_change';
      43    const TYPE_USER_BANNED = 'user_banned';
      44    const TYPE_USER_UNBANNED = 'user_unbanned';
      45 +  const TYPE_MOD_MESSAGE_UNBLOCKED = 'mod_message_unblocked';
      46 +  const TYPE_MOD_MESSAGE_IGNORED = 'mod_message_ignored';
      47 +  const TYPE_MOD_MESSAGE_DELETED = 'mod_message_deleted';
      48
      49    /**
      50     * Cron continuation hook for old-notification cleanup.

● Update(includes\class-fanfic-notifications.php)
  ⎿  Added 3 lines
      157        self::TYPE_STORY_STATUS_CHANGE,
      158        self::TYPE_USER_BANNED,
      159        self::TYPE_USER_UNBANNED,
      160 +      self::TYPE_MOD_MESSAGE_UNBLOCKED,
      161 +      self::TYPE_MOD_MESSAGE_IGNORED,
      162 +      self::TYPE_MOD_MESSAGE_DELETED,
      163      );
      164
      165      if ( ! in_array( $type, $valid_types, true ) ) {

● Agent "Create moderation messages service class" completed

● Good. Now add the new action labels to moderation-log.php:

● Update(includes\class-fanfic-moderation-log.php)
  ⎿  Added 8 lines, removed 6 lines
      426     */
      427    private static function get_action_label( $action ) {
      428      $labels = array(
      429 -      'ban'          => __( 'Banned User', 'fanfiction-manager' ),
      430 -      'unban'        => __( 'Unbanned User', 'fanfiction-manager' ),
      431 -      'block_manual' => __( 'Blocked Story (Manual)', 'fanfiction-manager' ),
      432 -      'block_ban'    => __( 'Blocked Story (User Ban)', 'fanfiction-manager' ),
      433 -      'block_rule'   => __( 'Blocked Story (Rule Change)', 'fanfiction-manager' ),
      434 -      'unblock'      => __( 'Unblocked Story', 'fanfiction-manager' ),
      429 +      'ban'             => __( 'Banned User', 'fanfiction-manager' ),
      430 +      'unban'           => __( 'Unbanned User', 'fanfiction-manager' ),
      431 +      'block_manual'    => __( 'Blocked Story (Manual)', 'fanfiction-manager' ),
      432 +      'block_ban'       => __( 'Blocked Story (User Ban)', 'fanfiction-manager' ),
      433 +      'block_rule'      => __( 'Blocked Story (Rule Change)', 'fanfiction-manager' ),
      434 +      'unblock'         => __( 'Unblocked Story', 'fanfiction-manager' ),
      435        'chapter_block_manual' => __( 'Blocked Chapter (Manual)', 'fanfiction-manager' ),
      436        'chapter_block_ban'    => __( 'Blocked Chapter (User Ban)', 'fanfiction-manager' ),
      437        'chapter_block_rule'   => __( 'Blocked Chapter (Rule Change)', 'fanfiction-manager' ),
      438        'chapter_unblock'      => __( 'Unblocked Chapter', 'fanfiction-manager' ),
      439 +      'message_ignored' => __( 'Ignored Author Message', 'fanfiction-manager' ),
      440 +      'message_deleted' => __( 'Deleted Author Message', 'fanfiction-manager' ),
      441      );
      442
      443      return isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( str_replace( '_', ' ', $action ) );

● Now add helpers to functions.php — suspension reason labels, chapter message, suspension message, restriction context, and banner render:

● Read 1 file (ctrl+o to expand)

● Now let me find the end of functions.php to append the new helper functions:

● Read 2 files (ctrl+o to expand)

● Now I'll append all the new helper functions to functions.php:

● Update(includes\functions.php)
  ⎿  Added 323 lines
      4158
      4159    return md5( $normalized_type . '|' . $content_id . '|' . $revision_source );
      4160  }
      4161 +
      4162 +/**
      4163 + * Get suspension reason labels.
      4164 + *
      4165 + * @since 2.3.0
      4166 + * @return array<string,string>
      4167 + */
      4168 +function fanfic_get_suspension_reason_labels() {
      4169 +  return array(
      4170 +    'tos_violation' => __( 'Terms of Service Violation', 'fanfiction-manager' ),
      4171 +    'harassment'    => __( 'Harassment / Bullying', 'fanfiction-manager' ),
      4172 +    'spam'          => __( 'Spam / Advertising', 'fanfiction-manager' ),
      4173 +    'ban_evasion'   => __( 'Ban Evasion', 'fanfiction-manager' ),
      4174 +    'inappropriate' => __( 'Inappropriate Conduct', 'fanfiction-manager' ),
      4175 +    'other'         => __( 'Other', 'fanfiction-manager' ),
      4176 +  );
      4177 +}
      4178 +
      4179 +/**
      4180 + * Get a single suspension reason label.
      4181 + *
      4182 + * @since 2.3.0
      4183 + * @param string $reason Reason code.
      4184 + * @return string Human-readable label.
      4185 + */
      4186 +function fanfic_get_suspension_reason_label( $reason ) {
      4187 +  $labels = fanfic_get_suspension_reason_labels();
      4188 +  return isset( $labels[ $reason ] ) ? $labels[ $reason ] : (string) $reason;
      4189 +}
      4190 +
      4191 +/**
      4192 + * Get a human-readable message explaining why a chapter is blocked.
      4193 + *
      4194 + * @since 2.3.0
      4195 + * @param int $chapter_id Chapter post ID.
      4196 + * @return string
      4197 + */
      4198 +function fanfic_get_blocked_chapter_message( $chapter_id = 0 ) {
      4199 +  if ( ! $chapter_id ) {
      4200 +    return __( 'This chapter has been blocked by a moderator.', 'fanfiction-manager' );
      4201 +  }
      4202 +
      4203 +  $chapter = get_post( $chapter_id );
      4204 +  if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
      4205 +    return __( 'This chapter has been blocked by a moderator.', 'fanfiction-manager' );
      4206 +  }
      4207 +
      4208 +  $block_type   = get_post_meta( $chapter_id, '_fanfic_block_type', true );
      4209 +  $block_reason = get_post_meta( $chapter_id, '_fanfic_block_reason', true );
      4210 +  $blocked_at   = get_post_meta( $chapter_id, '_fanfic_blocked_timestamp', true );
      4211 +
      4212 +  $timestamp_text = '';
      4213 +  if ( $blocked_at ) {
      4214 +    $timestamp_text = ' ' . sprintf(
      4215 +      __( 'on %s', 'fanfiction-manager' ),
      4216 +      date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $blocked_at )
      4217 +    );
      4218 +  }
      4219 +
      4220 +  switch ( $block_type ) {
      4221 +    case 'ban':
      4222 +      return sprintf(
      4223 +        __( 'This chapter was blocked%s because the author\'s account has been suspended.', 'fanfiction-manager' ),
      4224 +        $timestamp_text
      4225 +      );
      4226 +
      4227 +    case 'rule':
      4228 +      return sprintf(
      4229 +        __( 'This chapter was automatically blocked%s because site content rules have changed. %s', 'fanfiction-manager' ),
      4230 +        $timestamp_text,
      4231 +        $block_reason ? $block_reason : ''
      4232 +      );
      4233 +
      4234 +    case 'manual':
      4235 +    default:
      4236 +      if ( $block_reason ) {
      4237 +        return sprintf(
      4238 +          __( 'Your chapter was blocked%s because: %s', 'fanfiction-manager' ),
      4239 +          $timestamp_text,
      4240 +          fanfic_get_block_reason_label( $block_reason )
      4241 +        );
      4242 +      }
      4243 +      return sprintf(
      4244 +        __( 'This chapter was blocked%s. If you believe this is a mistake, please contact the site administrator.', 'fanfiction-manager' ),
      4245 +        $timestamp_text
      4246 +      );
      4247 +  }
      4248 +}
      4249 +
      4250 +/**
      4251 + * Get a human-readable message explaining why a user's account is suspended.
      4252 + *
      4253 + * @since 2.3.0
      4254 + * @param int $user_id User ID.
      4255 + * @return string
      4256 + */
      4257 +function fanfic_get_suspension_message( $user_id = 0 ) {
      4258 +  if ( ! $user_id ) {
      4259 +    return __( 'Your account has been suspended.', 'fanfiction-manager' );
      4260 +  }
      4261 +
      4262 +  $reason      = get_user_meta( $user_id, 'fanfic_suspension_reason', true );
      4263 +  $reason_text = get_user_meta( $user_id, 'fanfic_suspension_reason_text', true );
      4264 +  $banned_at   = get_user_meta( $user_id, 'fanfic_banned_at', true );
      4265 +
      4266 +  $timestamp_text = '';
      4267 +  if ( $banned_at ) {
      4268 +    $timestamp_text = ' ' . sprintf(
      4269 +      __( 'on %s', 'fanfiction-manager' ),
      4270 +      date_i18n( get_option( 'date_format' ), strtotime( $banned_at ) )
      4271 +    );
      4272 +  }
      4273 +
      4274 +  if ( $reason && 'other' !== $reason ) {
      4275 +    $label = fanfic_get_suspension_reason_label( $reason );
      4276 +    $msg   = sprintf(
      4277 +      __( 'Your account was suspended%s for: %s', 'fanfiction-manager' ),
      4278 +      $timestamp_text,
      4279 +      $label
      4280 +    );
      4281 +    if ( $reason_text ) {
      4282 +      $msg .= ' — ' . esc_html( $reason_text );
      4283 +    }
      4284 +    return $msg;
      4285 +  }
      4286 +
      4287 +  if ( $reason_text ) {
      4288 +    return sprintf(
      4289 +      __( 'Your account was suspended%s. Reason: %s', 'fanfiction-manager' ),
      4290 +      $timestamp_text,
      4291 +      $reason_text
      4292 +    );
      4293 +  }
      4294 +
      4295 +  return sprintf(
      4296 +    __( 'Your account was suspended%s.', 'fanfiction-manager' ),
      4297 +    $timestamp_text
      4298 +  );
      4299 +}
      4300 +
      4301 +/**
      4302 + * Get structured restriction context for a target.
      4303 + *
      4304 + * Returns an array with restriction details used by banners and message modals.
      4305 + *
      4306 + * @since 2.3.0
      4307 + * @param string $target_type 'story', 'chapter', or 'user'.
      4308 + * @param int    $target_id   Post ID or user ID.
      4309 + * @return array {
      4310 + *     @type bool   $is_restricted
      4311 + *     @type string $restriction_type  'story_blocked', 'chapter_blocked', 'user_suspended', or ''
      4312 + *     @type string $target_type
      4313 + *     @type int    $target_id
      4314 + *     @type string $reason_message
      4315 + *     @type bool   $has_active_message
      4316 + *     @type int    $owner_id
      4317 + * }
      4318 + */
      4319 +function fanfic_get_restriction_context( $target_type, $target_id ) {
      4320 +  $context = array(
      4321 +    'is_restricted'      => false,
      4322 +    'restriction_type'   => '',
      4323 +    'target_type'        => $target_type,
      4324 +    'target_id'          => absint( $target_id ),
      4325 +    'reason_message'     => '',
      4326 +    'has_active_message' => false,
      4327 +    'owner_id'           => 0,
      4328 +  );
      4329 +
      4330 +  if ( 'story' === $target_type ) {
      4331 +    $story = get_post( $target_id );
      4332 +    if ( ! $story ) {
      4333 +      return $context;
      4334 +    }
      4335 +    $context['owner_id'] = absint( $story->post_author );
      4336 +    if ( fanfic_is_story_blocked( $target_id ) ) {
      4337 +      $context['is_restricted']    = true;
      4338 +      $context['restriction_type'] = 'story_blocked';
      4339 +      $context['reason_message']   = fanfic_get_blocked_story_message( $target_id );
      4340 +    }
      4341 +  } elseif ( 'chapter' === $target_type ) {
      4342 +    $chapter = get_post( $target_id );
      4343 +    if ( ! $chapter ) {
      4344 +      return $context;
      4345 +    }
      4346 +    $context['owner_id'] = absint( $chapter->post_author );
      4347 +    if ( fanfic_is_chapter_blocked( $target_id ) ) {
      4348 +      $context['is_restricted']    = true;
      4349 +      $context['restriction_type'] = 'chapter_blocked';
      4350 +      $context['reason_message']   = fanfic_get_blocked_chapter_message( $target_id );
      4351 +    }
      4352 +  } elseif ( 'user' === $target_type ) {
      4353 +    $context['owner_id'] = absint( $target_id );
      4354 +    if ( get_user_meta( $target_id, 'fanfic_banned', true ) === '1' ) {
      4355 +      $context['is_restricted']    = true;
      4356 +      $context['restriction_type'] = 'user_suspended';
      4357 +      $context['reason_message']   = fanfic_get_suspension_message( $target_id );
      4358 +    }
      4359 +  }
      4360 +
      4361 +  if ( $context['is_restricted'] && class_exists( 'Fanfic_Moderation_Messages' ) ) {
      4362 +    $context['has_active_message'] = Fanfic_Moderation_Messages::has_active_message(
      4363 +      $context['owner_id'],
      4364 +      $target_type,
      4365 +      $target_id
      4366 +    );
      4367 +  }
      4368 +
      4369 +  return $context;
      4370 +}
      4371 +
      4372 +/**
      4373 + * Render a restriction banner for blocked stories/chapters or suspended accounts.
      4374 + *
      4375 + * Also outputs the message modal HTML once per page (tracked via static flag).
      4376 + *
      4377 + * @since 2.3.0
      4378 + * @param array  $context     Restriction context from fanfic_get_restriction_context().
      4379 + * @param array  $nav_buttons Array of ['label' => string, 'url' => string, 'class' => string (optional)].
      4380 + * @return void
      4381 + */
      4382 +function fanfic_render_restriction_banner( $context, $nav_buttons = array() ) {
      4383 +  if ( empty( $context['is_restricted'] ) ) {
      4384 +    return;
      4385 +  }
      4386 +
      4387 +  static $modal_rendered = false;
      4388 +
      4389 +  $restriction_type = $context['restriction_type'];
      4390 +  $has_active       = ! empty( $context['has_active_message'] );
      4391 +  $target_type      = esc_attr( $context['target_type'] );
      4392 +  $target_id        = absint( $context['target_id'] );
      4393 +
      4394 +  switch ( $restriction_type ) {
      4395 +    case 'story_blocked':
      4396 +      $title = __( 'Story Blocked', 'fanfiction-manager' );
      4397 +      $info  = __( 'You can still view your story, but editing and publishing are disabled until the block is lifted.', 'fanfiction-manager' );
      4398 +      break;
      4399 +    case 'chapter_blocked':
      4400 +      $title = __( 'Chapter Blocked', 'fanfiction-manager' );
      4401 +      $info  = __( 'You can still view this chapter, but editing and visibility actions are disabled until the block is lifted.', 'fanfiction-manager' );
      4402 +      break;
      4403 +    case 'user_suspended':
      4404 +      $title = __( 'Account Suspended', 'fanfiction-manager' );
      4405 +      $info  = __( 'You can view your content but cannot create or edit stories while suspended.', 'fanfiction-manager' );
      4406 +      break;
      4407 +    default:
      4408 +      $title = __( 'Restricted', 'fanfiction-manager' );
      4409 +      $info  = '';
      4410 +  }
      4411 +
      4412 +  ?>
      4413 +  <div class="fanfic-message fanfic-message-error fanfic-blocked-notice" role="alert" aria-live="assertive">
      4414 +    <span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
      4415 +    <span class="fanfic-message-content">
      4416 +      <strong><?php echo esc_html( $title ); ?></strong><br>
      4417 +      <?php echo esc_html( $context['reason_message'] ); ?><br>
      4418 +      <?php if ( $info ) : ?>
      4419 +        <span class="fanfic-block-info"><?php echo esc_html( $info ); ?></span>
      4420 +      <?php endif; ?>
      4421 +      <span class="fanfic-message-actions">
      4422 +        <?php foreach ( $nav_buttons as $btn ) : ?>
      4423 +          <a href="<?php echo esc_url( $btn['url'] ); ?>" class="fanfic-button <?php echo isset( $btn['class'] ) ? esc_attr( $btn['class'] ) : 'secondary'; ?>">
      4424 +            <?php echo esc_html( $btn['label'] ); ?>
      4425 +          </a>
      4426 +        <?php endforeach; ?>
      4427 +        <?php if ( is_user_logged_in() && class_exists( 'Fanfic_Moderation_Messages' ) ) : ?>
      4428 +          <?php if ( $has_active ) : ?>
      4429 +            <span class="fanfic-button secondary fanfic-message-sent-badge disabled">
      4430 +              <?php esc_html_e( 'Message Sent — Awaiting Review', 'fanfiction-manager' ); ?>
      4431 +            </span>
      4432 +          <?php else : ?>
      4433 +            <button type="button"
      4434 +              class="fanfic-button secondary fanfic-message-mod-btn"
      4435 +              data-target-type="<?php echo esc_attr( $target_type ); ?>"
      4436 +              data-target-id="<?php echo esc_attr( $target_id ); ?>">
      4437 +              <?php esc_html_e( 'Message Moderation', 'fanfiction-manager' ); ?>
      4438 +            </button>
      4439 +          <?php endif; ?>
      4440 +        <?php endif; ?>
      4441 +      </span>
      4442 +    </span>
      4443 +  </div>
      4444 +  <?php
      4445 +
      4446 +  // Render modal once per page.
      4447 +  if ( ! $modal_rendered ) {
      4448 +    $modal_rendered = true;
      4449 +    ?>
      4450 +    <div id="fanfic-mod-message-modal" class="fanfic-modal" style="display:none;" aria-modal="true" role="dialog" aria-labelledby="fanfic-mod-modal-title">
      4451 +      <div class="fanfic-modal-overlay"></div>
      4452 +      <div class="fanfic-modal-content">
      4453 +        <h2 id="fanfic-mod-modal-title"><?php esc_html_e( 'Message Moderation', 'fanfiction-manager' ); ?></h2>
      4454 +        <p><?php esc_html_e( 'Send a message to the moderation team about this restriction. Be respectful and provide any relevant context.', 'fanfiction-manager' ); ?></p>
      4455 +        <form id="fanfic-mod-message-form">
      4456 +          <input type="hidden" name="target_type" id="fanfic-mod-target-type" value="">
      4457 +          <input type="hidden" name="target_id" id="fanfic-mod-target-id" value="">
      4458 +          <div class="fanfic-form-group">
      4459 +            <label for="fanfic-mod-message-text"><?php esc_html_e( 'Your message:', 'fanfiction-manager' ); ?></label>
      4460 +            <textarea
      4461 +              id="fanfic-mod-message-text"
      4462 +              name="message"
      4463 +              rows="5"
      4464 +              maxlength="1000"
      4465 +              placeholder="<?php esc_attr_e( 'Describe your situation...', 'fanfiction-manager' ); ?>"
      4466 +            ></textarea>
      4467 +            <span class="fanfic-char-counter"><span id="fanfic-mod-char-count">0</span>/1000</span>
      4468 +          </div>
      4469 +          <div class="fanfic-form-message" id="fanfic-mod-form-message" style="display:none;"></div>
      4470 +          <div class="fanfic-modal-actions">
      4471 +            <button type="button" class="fanfic-button secondary" id="fanfic-mod-modal-cancel">
      4472 +              <?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
      4473 +            </button>
      4474 +            <button type="submit" class="fanfic-button primary" id="fanfic-mod-modal-submit">
      4475 +              <?php esc_html_e( 'Send Message', 'fanfiction-manager' ); ?>
      4476 +            </button>
      4477 +          </div>
      4478 +        </form>
      4479 +      </div>
      4480 +    </div>
      4481 +    <?php
      4482 +  }
      4483 +}

● Agent "Create messages WP_List_Table class" completed

● Both class files created. Now let me continue with the remaining changes. I'll handle multiple files in parallel with agents for the larger JS/CSS changes, and do the PHP edits directly.

● 2 Agent agents launched (ctrl+o to expand)
   ├─ Update moderation.php messages tab and log tab
   │  ⎿  Running in the background
   └─ Update ajax-handlers.php with two new endpoints
      ⎿  Running in the background

● Now updating the templates. Let me start with story-form.php:

● Read 1 file (ctrl+o to expand)

● Update(templates\template-story-form.php)
  ⎿  Added 7 lines, removed 42 lines
       95
       96    $is_blocked = (bool) get_post_meta( $story_id, '_fanfic_story_blocked', true );
       97    if ( $is_blocked && ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
       98 -    // Get block reason for display
       99 -    $block_reason = get_post_meta( $story_id, '_fanfic_block_reason', true );
      100 -    $block_info = function_exists( 'fanfic_get_block_info' ) ? fanfic_get_block_info( $story_id ) : null;
      101 -
      102 -    // Map reason codes to user-friendly labels
      103 -    $reason_labels = array(
      104 -      'manual'              => __( 'This story has been blocked by a moderator.', 'fanfiction-manager' ),
      105 -      'tos_violation'       => __( 'This story was blocked for violating our Terms of Service.', 'fanfiction-manager' ),
      106 -      'copyright'           => __( 'This story was blocked due to a copyright concern.', 'fanfiction-manager' ),
      107 -      'inappropriate'       => __( 'This story was blocked for containing inappropriate content.', 'fanfiction-manager' ),
      108 -      'spam'                => __( 'This story was blocked for spam or advertising.', 'fanfiction-manager' ),
      109 -      'harassment'          => __( 'This story was blocked for harassment or bullying content.', 'fanfiction-manager' ),
      110 -      'illegal'             => __( 'This story was blocked for containing potentially illegal content.', 'fanfiction-manager' ),
      111 -      'underage'            => __( 'This story was blocked for content concerns regarding minors.', 'fanfiction-manager' ),
      112 -      'rating_mismatch'     => __( 'This story was blocked because the content does not match its rating/warnings.', 'fanfiction-manager' ),
      113 -      'user_request'        => __( 'This story was blocked at your request.', 'fanfiction-manager' ),
      114 -      'pending_review'      => __( 'This story is pending moderator review.', 'fanfiction-manager' ),
      115 -      'other'               => __( 'This story has been blocked. Please contact support for more information.', 'fanfiction-manager' ),
      116 -    );
      117 -
      118 -    $reason_message = isset( $reason_labels[ $block_reason ] ) ? $reason_labels[ $block_reason ] : __( 'This story has been blocked by a moderator.', 'fanfiction-manager' );
      119 -    ?>
      120 -    <div class="fanfic-message fanfic-message-error fanfic-blocked-notice" role="alert" aria-live="assertive">
      121 -      <span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
      122 -      <span class="fanfic-message-content">
      123 -        <strong><?php esc_html_e( 'Story Blocked', 'fanfiction-manager' ); ?></strong><br>
      124 -        <?php echo esc_html( $reason_message ); ?><br>
      125 -        <span class="fanfic-block-info">
      126 -          <?php esc_html_e( 'You can still view your story, but editing and publishing are disabled until the block is lifted.', 'fanfiction-manager' ); ?>
      127 -          <?php esc_html_e( 'If you believe this was done in error, please contact site administration.', 'fanfiction-manager' ); ?>
      128 -        </span>
      129 -        <span class="fanfic-message-actions">
      130 -          <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button">
      131 -            <?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
      132 -          </a>
      133 -          <a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" class="fanfic-button secondary">
      134 -            <?php esc_html_e( 'View Story', 'fanfiction-manager' ); ?>
      135 -          </a>
      136 -        </span>
      137 -      </span>
      138 -    </div>
      139 -    <?php
       98 +    if ( function_exists( 'fanfic_render_restriction_banner' ) ) {
       99 +      $ctx = fanfic_get_restriction_context( 'story', $story_id );
      100 +      fanfic_render_restriction_banner( $ctx, array(
      101 +        array( 'label' => __( 'Back to Dashboard', 'fanfiction-manager' ), 'url' => fanfic_get_dashboard_url() ),
      102 +        array( 'label' => __( 'View Story', 'fanfiction-manager' ), 'url' => get_permalink( $story_id ), 'class' => 'secondary' ),
      103 +      ) );
      104 +    }
      105      return;
      106    }
      107

● Now update chapter-form.php:

● Update(templates\template-chapter-form.php)
  ⎿  Added 14 lines, removed 58 lines
      343
      344  $is_blocked = fanfic_is_story_blocked( $story_id );
      345  if ( $is_blocked && ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
      346 -  // Get block reason for display
      347 -  $block_reason = get_post_meta( $story_id, '_fanfic_block_reason', true );
      348 -
      349 -  // Map reason codes to user-friendly labels
      350 -  $reason_labels = array(
      351 -    'manual'              => __( 'This story has been blocked by a moderator.', 'fanfiction-manager' ),
      352 -    'tos_violation'       => __( 'This story was blocked for violating our Terms of Service.', 'fanfiction-manager' ),
      353 -    'copyright'           => __( 'This story was blocked due to a copyright concern.', 'fanfiction-manager' ),
      354 -    'inappropriate'       => __( 'This story was blocked for containing inappropriate content.', 'fanfiction-manager' ),
      355 -    'spam'                => __( 'This story was blocked for spam or advertising.', 'fanfiction-manager' ),
      356 -    'harassment'          => __( 'This story was blocked for harassment or bullying content.', 'fanfiction-manager' ),
      357 -    'illegal'             => __( 'This story was blocked for containing potentially illegal content.', 'fanfiction-manager' ),
      358 -    'underage'            => __( 'This story was blocked for content concerns regarding minors.', 'fanfiction-manager' ),
      359 -    'rating_mismatch'     => __( 'This story was blocked because the content does not match its rating/warnings.', 'fanfiction-manager' ),
      360 -    'user_request'        => __( 'This story was blocked at your request.', 'fanfiction-manager' ),
      361 -    'pending_review'      => __( 'This story is pending moderator review.', 'fanfiction-manager' ),
      362 -    'other'               => __( 'This story has been blocked. Please contact support for more information.', 'fanfiction-manager' ),
      363 -  );
      364 -
      365 -  $reason_message = isset( $reason_labels[ $block_reason ] ) ? $reason_labels[ $block_reason ] : __( 'This story has been blocked by a moderator.', 'fanfiction-manager' );
      366 -  ?>
      367 -  <div class="fanfic-message fanfic-message-error fanfic-blocked-notice" role="alert" aria-live="assertive">
      368 -    <span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
      369 -    <span class="fanfic-message-content">
      370 -      <strong><?php esc_html_e( 'Story Blocked', 'fanfiction-manager' ); ?></strong><br>
      371 -      <?php echo esc_html( $reason_message ); ?><br>
      372 -      <span class="fanfic-block-info">
      373 -        <?php esc_html_e( 'You cannot add or edit chapters while the story is blocked.', 'fanfiction-manager' ); ?>
      374 -        <?php esc_html_e( 'If you believe this was done in error, please contact site administration.', 'fanfiction-manager' ); ?>
      375 -      </span>
      376 -      <span class="fanfic-message-actions">
      377 -        <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button">
      378 -          <?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
      379 -        </a>
      380 -        <a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" class="fanfic-button secondary">
      381 -          <?php esc_html_e( 'View Story', 'fanfiction-manager' ); ?>
      382 -        </a>
      383 -      </span>
      384 -    </span>
      385 -  </div>
      386 -  <?php
      346 +  if ( function_exists( 'fanfic_render_restriction_banner' ) ) {
      347 +    $ctx = fanfic_get_restriction_context( 'story', $story_id );
      348 +    fanfic_render_restriction_banner( $ctx, array(
      349 +      array( 'label' => __( 'Back to Dashboard', 'fanfiction-manager' ), 'url' => fanfic_get_dashboard_url() ),
      350 +      array( 'label' => __( 'View Story', 'fanfiction-manager' ), 'url' => get_permalink( $story_id ), 'class' => 'secondary' ),
      351 +    ) );
      352 +  }
      353    return;
      354  }
      355
      356  $is_chapter_blocked = $is_edit_mode ? fanfic_is_chapter_blocked( $chapter_id ) : false;
      357  if ( $is_chapter_blocked && ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
      392 -  ?>
      393 -  <div class="fanfic-message fanfic-message-error fanfic-blocked-notice" role="alert" aria-live="assertive">
      394 -    <span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
      395 -    <span class="fanfic-message-content">
      396 -      <strong><?php esc_html_e( 'Chapter Blocked', 'fanfiction-manager' ); ?></strong><br>
      397 -      <?php esc_html_e( 'This chapter has been blocked by a moderator. You can still view it, but editing and visibility actions are disabled until the block is lifted.', 'fanfiction-manager' ); ?><br>
      398 -      <span class="fanfic-message-actions">
      399 -        <a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button">
      400 -          <?php esc_html_e( 'Back to Story', 'fanfiction-manager' ); ?>
      401 -        </a>
      402 -        <a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button secondary">
      403 -          <?php esc_html_e( 'View Chapter', 'fanfiction-manager' ); ?>
      404 -        </a>
      405 -      </span>
      406 -    </span>
      407 -  </div>
      408 -  <?php
      358 +  if ( function_exists( 'fanfic_render_restriction_banner' ) ) {
      359 +    $ctx = fanfic_get_restriction_context( 'chapter', $chapter_id );
      360 +    fanfic_render_restriction_banner( $ctx, array(
      361 +      array( 'label' => __( 'Back to Story', 'fanfiction-manager' ), 'url' => fanfic_get_edit_story_url( $story_id ) ),
      362 +      array( 'label' => __( 'View Chapter', 'fanfiction-manager' ), 'url' => get_permalink( $chapter_id ), 'class' => 'secondary' ),
      363 +    ) );
      364 +  }
      365    return;
      366  }
      367