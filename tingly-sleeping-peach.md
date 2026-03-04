# Plan: Moderation Blacklist & Blocks Tab

## Context

Moderators need tools to control abuse from users who repeatedly submit false reports or spam moderation messages. Currently there's no way to prevent a specific user/IP from filing reports or a specific author from sending moderation messages. Additionally, there's no central place to view all blocked content (stories, chapters, comments) and unblock them — moderators must find each item individually.

On the moderation part of my plugin. On backend, on reports and author messages, we need a way to controle abuse.
   (eg, users that repeateadly report and send author messages). On reported table, on Reported by table, we must
  have the name of the user and next to it a button named "Blacklist". Cliking it opens a pop up saying that
  cliking on this, user (or ip) will be blacklisted and cant send more reports. Exacly same logit to the authors
  table, that will impeach authors from seending messages. We need to create a tab named Blocks that, amogs others,
   is  where users and ips blaclkisted are there, with columns that precised which mod blacklisted them, and a
  button to "unblacklist". We also need to thing on blacklisted user prespective experience, how this blacklist
  renders the buttons that allow the interaction unavailable. Also, the table also includes the blocked stories,
  chapters, comments (and by who), with also the option to unblock. This table is the place to go for unblock
  something. Any questions?

**Goal**: Add blacklist functionality to the Reports and Author Messages tables, create a new "Blocks" tab as the central hub for managing all blocks/blacklists, and enforce blacklists on the frontend.

---

## 1. Database: New `wp_fanfic_blacklist` Table

**File**: `includes/class-fanfic-database-setup.php`

- Add `get_blacklist_table_sql()` method with schema:
  - `id` BIGINT PK AUTO_INCREMENT
  - `blacklist_type` VARCHAR(30) — `'report'` or `'message'`
  - `user_id` BIGINT DEFAULT 0 (for logged-in users)
  - `ip_address` VARCHAR(100) DEFAULT '' (for anonymous reporters)
  - `reason` TEXT (optional moderator note)
  - `moderator_id` BIGINT NOT NULL
  - `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
  - Indexes: `(blacklist_type, user_id)`, `(blacklist_type, ip_address)`, `(moderator_id)`
- Call in `create_tables()`
- Bump `DB_VERSION` to `'2.4.0'`

## 2. New Utility Class: `Fanfic_Blacklist`

**New file**: `includes/class-fanfic-blacklist.php`

Static CRUD class (follows `Fanfic_Moderation_Messages` pattern):

- `is_reporter_blacklisted( $user_id )` — check by user_id
- `is_reporter_blacklisted_by_ip( $ip )` — check by IP
- `is_message_sender_blacklisted( $user_id )` — check by user_id
- `add( $type, $user_id, $ip, $moderator_id, $reason )` — insert entry, prevent duplicates
- `remove( $id )` — delete entry by ID
- `get_entries( $type, $args )` — paginated query (for Blocks tab table)
- `count_entries( $type )` — count (for sub-tab badges)

## 3. Core Loader

**File**: `includes/class-fanfic-core.php`

- Add `require_once` for `class-fanfic-blacklist.php` (alongside `class-fanfic-moderation-messages.php`, ~line 258, loaded always — needed for frontend checks)
- Add `require_once` for `class-fanfic-blocks-table.php` (alongside other table classes, ~line 284, admin-only block)

## 4. Reports Table: "Blacklist" Button

**File**: `includes/class-fanfic-moderation-table.php`

Modify `column_reported_by()` (~line 543):
- After rendering reporter name/IP, check `Fanfic_Blacklist::is_reporter_blacklisted()` or `is_reporter_blacklisted_by_ip()`
- If already blacklisted: append a red "Blacklisted" badge
- If not: append a `<button class="button button-small fanfic-blacklist-reporter" data-user-id="X" data-ip="Y">Blacklist</button>`
- For "Guest" reporters (no ID, no IP): no button (nothing to blacklist)

## 5. Author Messages Table: "Blacklist" Button

**File**: `includes/class-fanfic-messages-table.php`

Modify `column_author()` (~line 276):
- After rendering author name link, check `Fanfic_Blacklist::is_message_sender_blacklisted()`
- If already blacklisted: append "Blacklisted" badge
- If not: append `<button class="button button-small fanfic-blacklist-message-sender" data-user-id="X">Blacklist</button>`

## 6. New "Blocks" Tab in Moderation Page

**File**: `includes/class-fanfic-moderation.php`

- Add `'blocks'` to `$allowed_tabs` array (line 92)
- Add tab link in `<nav>` (after Log tab, ~line 119)
- Add `case 'blocks': self::render_blocks_tab(); break;` in switch (~line 125)
- New `render_blocks_tab()` method with 5 sub-tabs using `<ul class="subsubsub">`:
  1. **Blacklisted Reporters** (default) — from `wp_fanfic_blacklist` where `type='report'`
  2. **Blacklisted Authors** — from `wp_fanfic_blacklist` where `type='message'`
  3. **Blocked Stories** — posts with `_fanfic_story_blocked = '1'`
  4. **Blocked Chapters** — posts with `_fanfic_chapter_blocked = '1'`
  5. **Blocked Comments** — comments with `fanfic_moderation_action` meta indicating block

**New file**: `includes/class-fanfic-blocks-table.php`

Single `Fanfic_Blocks_Table extends WP_List_Table` with `$block_type` constructor parameter:

| Sub-tab | Columns | Data Source |
|---------|---------|-------------|
| reporters | Target (name/IP), Blacklisted by (mod name), Date, Actions (Unblacklist) | `wp_fanfic_blacklist` |
| authors | Author (name), Blacklisted by (mod name), Date, Actions (Unblacklist) | `wp_fanfic_blacklist` |
| stories | Story title (linked), Author, Block reason, Blocked by, Date, Actions (Unblock) | WP_Query meta query |
| chapters | Chapter title + parent story, Author, Block reason, Blocked by, Date, Actions (Unblock) | WP_Query meta query |
| comments | Comment excerpt, On (story/chapter title), Moderated by, Date, Actions (Unblock) | WP_Comment_Query |

## 7. AJAX Handlers

**File**: `includes/class-fanfic-moderation-table.php` (in `Fanfic_Moderation_Ajax`)

Add to `init()`:
- `wp_ajax_fanfic_blacklist_reporter` → `ajax_blacklist_reporter()`
- `wp_ajax_fanfic_blacklist_message_sender` → `ajax_blacklist_message_sender()`
- `wp_ajax_fanfic_remove_blacklist` → `ajax_remove_blacklist()`
- `wp_ajax_fanfic_unblock_content` → `ajax_unblock_content()`

All handlers:
- Verify nonce (`fanfic_moderation_action`) + capability (`moderate_fanfiction` or `manage_options`)
- Call `Fanfic_Blacklist` methods
- Log to `wp_fanfic_moderation_log` with new action types: `reporter_blacklisted`, `message_sender_blacklisted`, `reporter_unblacklisted`, `message_sender_unblacklisted`
- `ajax_unblock_content()` calls existing `fanfic_remove_post_block()` for stories/chapters, `wp_set_comment_status()` for comments

## 8. Frontend Enforcement

### 8a. Block report submissions from blacklisted users/IPs

**File**: `includes/class-fanfic-ajax-handlers.php`

In `ajax_report_content()` (~line 1553, after reporter_id/ip determined):
- If logged in: check `Fanfic_Blacklist::is_reporter_blacklisted( $reporter_id )`
- If anonymous: check `Fanfic_Blacklist::is_reporter_blacklisted_by_ip( $reporter_ip )`
- If blacklisted: return generic error "You are unable to submit reports at this time." (don't reveal blacklist)

### 8b. Block message submissions from blacklisted authors

**File**: `includes/class-fanfic-ajax-handlers.php`

In `ajax_send_moderation_message()` (~line 1791, after user_id set):
- Check `Fanfic_Blacklist::is_message_sender_blacklisted( $current_user_id )`
- If blacklisted: return generic error "You are unable to send messages at this time."

### 8c. Disable report button for blacklisted users

**File**: `includes/shortcodes/class-fanfic-shortcodes-buttons.php`

In `render_report_trigger()` (~line 1028):
- Check blacklist status (by user_id if logged in, by IP if anonymous)
- If blacklisted: render button with `disabled` attribute + different aria-label

### 8d. Disable "Message Moderation" button for blacklisted authors

**File**: `includes/functions.php`

In `fanfic_render_restriction_banner()` (~line 5362) and the suspension notice in `class-fanfic-core.php` (~line 1214):
- Check `Fanfic_Blacklist::is_message_sender_blacklisted()`
- If blacklisted: render button as disabled or hidden

## 9. Admin JavaScript

**File**: `assets/js/fanfiction-admin.js`

New `initBlacklistActions()` function (called from document.ready):
- `.fanfic-blacklist-reporter` click → `confirm()` popup → AJAX `fanfic_blacklist_reporter` → reload on success
- `.fanfic-blacklist-message-sender` click → `confirm()` popup → AJAX `fanfic_blacklist_message_sender` → reload on success
- `.fanfic-unblacklist` click → `confirm()` popup → AJAX `fanfic_remove_blacklist` → reload on success
- `.fanfic-unblock-content` click → `confirm()` popup → AJAX `fanfic_unblock_content` → reload on success

## 10. Admin CSS

**File**: `assets/css/fanfiction-admin.css`

- `.fanfic-blacklist-badge` — red badge styling (background: #ffebee, color: #c62828)
- `.fanfic-blacklist-reporter`, `.fanfic-blacklist-message-sender` — small margin-left for spacing
- Blocks tab sub-tab styles (reuse existing `subsubsub` pattern)

## 11. Localized Strings

**File**: `includes/class-fanfic-admin.php`

Add to `$localize_data['strings']` (~line 367):
- `blacklistReporterConfirm`, `blacklistMessageSenderConfirm`
- `unblacklistConfirm`, `unblockConfirm`
- `blacklistSuccess`, `unblacklistSuccess`

## 12. Moderation Log Integration

**File**: `includes/class-fanfic-moderation.php`

- Add new action types to `$action_badges` in `render_log_tab()`
- Add `'blacklist'` option to the log action filter dropdown
- Update `normalize_log_action_filter()` to expand `'blacklist'` into the 4 new action types

---

## Implementation Order

1. Database schema + migration (`class-fanfic-database-setup.php`)
2. `Fanfic_Blacklist` utility class (new file)
3. Core loader update (`class-fanfic-core.php`)
4. Reports table — Blacklist button (`class-fanfic-moderation-table.php`)
5. Messages table — Blacklist button (`class-fanfic-messages-table.php`)
6. AJAX handlers for blacklist/unblacklist (`class-fanfic-moderation-table.php`)
7. Blocks tab + sub-tabs (`class-fanfic-moderation.php`)
8. Blocks table class (new file `class-fanfic-blocks-table.php`)
9. Frontend enforcement — AJAX checks (`class-fanfic-ajax-handlers.php`)
10. Frontend enforcement — button disabling (`class-fanfic-shortcodes-buttons.php`, `functions.php`, `class-fanfic-core.php`)
11. Admin JS (`fanfiction-admin.js`)
12. Admin CSS (`fanfiction-admin.css`)
13. Localized strings + log integration (`class-fanfic-admin.php`, `class-fanfic-moderation.php`)

## Verification

1. **DB**: Deactivate + reactivate plugin → verify `wp_fanfic_blacklist` table created
2. **Reports table**: Visit Moderation → Reports → verify "Blacklist" button next to reporter names/IPs
3. **Messages table**: Visit Moderation → Author Messages → verify "Blacklist" button next to author names
4. **Blacklist action**: Click Blacklist → confirm → verify badge appears, button disappears
5. **Blocks tab**: Visit Moderation → Blocks → verify 5 sub-tabs with correct data
6. **Unblacklist**: From Blocks tab → click Unblacklist → verify entry removed
7. **Unblock**: From Blocks tab → click Unblock on a story/chapter/comment → verify content unblocked
8. **Frontend report**: As blacklisted user, verify report button disabled and submission rejected
9. **Frontend message**: As blacklisted author, verify Message Moderation button disabled and submission rejected
10. **Log**: Verify blacklist/unblacklist actions appear in Moderation → Log tab
