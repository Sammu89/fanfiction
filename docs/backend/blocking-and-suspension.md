# Block and Moderation Messaging System

## Overview

The system provides three restriction levels for content and accounts, a two-way author/moderator chat workflow for blocked authors, a correction flow that lets blocked owners fix and resubmit their content, and a moderator compare view to review what changed before deciding whether to unblock.

---

## Restriction States

### Hide

Removes content from public view without locking the author out.

- Readers lose access.
- The author can still edit and restore visibility.

Applies to stories and chapters.

### Block

Freezes content under moderator control while allowing the owner to correct it.

- Readers lose access.
- The author can open and edit the blocked story or chapter (see Correction Flow).
- The author cannot change the post status (cannot force publish or restore visibility).
- Moderators retain full access.

Applies to stories and chapters.

### Suspend

Applies a restriction to the whole user account.

- The user can log in and view their dashboard.
- They cannot create or edit anything while suspended.
- They cannot comment.
- All of their stories become blocked automatically.
- Moderators can still unblock individual stories while the user remains suspended.

---

## Storage Model

### Story block state

| Meta key | Type | Description |
|---|---|---|
| `_fanfic_story_blocked` | `1` / empty | Whether the story is blocked |
| `_fanfic_block_type` | string | Block category code (e.g. `content_violation`) |
| `_fanfic_block_reason` | string | Reason code |
| `_fanfic_block_reason_text` | string | Human-readable reason shown to author |
| `_fanfic_blocked_timestamp` | int | Unix timestamp of block time |
| `_fanfic_block_snapshot` | JSON | Snapshot of story state at block time (see Snapshot) |
| `_fanfic_re_review_requested` | `1` / empty | Whether author has an active re-review request pending |

### Chapter block state

| Meta key | Type | Description |
|---|---|---|
| `_fanfic_chapter_blocked` | `1` / empty | Whether the chapter is blocked |
| `_fanfic_block_type` | string | Block category code |
| `_fanfic_block_reason` | string | Reason code |
| `_fanfic_block_reason_text` | string | Human-readable reason |
| `_fanfic_blocked_timestamp` | int | Unix timestamp of block time |

### Account suspension state

| User meta key | Type | Description |
|---|---|---|
| `fanfic_banned` | `1` / empty | Whether the user is suspended |
| `fanfic_banned_by` | int | Moderator user ID who suspended |
| `fanfic_banned_at` | int | Unix timestamp |
| `fanfic_suspension_reason` | string | Reason code |
| `fanfic_suspension_reason_text` | string | Human-readable reason shown to author |
| `fanfic_original_role` | string | Role to restore on unsuspend |

---

## Block Snapshot

When a story is blocked, the system captures a JSON snapshot of the story's full state and stores it in `_fanfic_block_snapshot`. This snapshot becomes the authoritative "before correction" baseline used in the moderator compare view.

### Why the snapshot is necessary

WordPress revisions capture the first saved state after a blocked edit — not the original blocked state. The snapshot captures the original blocked state explicitly so the comparison baseline is always accurate.

### Snapshot fields

- `post_title`, `post_content`
- `genre_ids`, `genre_names`
- `status_ids`, `status_names`
- `fandom_ids`, `fandom_labels`
- `warning_ids`, `warning_names`
- `language_id`, `language_label`
- `licence`, `age_rating`
- `cover_image_id`, `cover_image_url`
- `author_notes_enabled`, `author_notes_position`, `author_notes`
- `is_original_work`
- `snapshot_time`

### Snapshot lifecycle

- **Created**: in `fanfic_apply_post_block()` via `fanfic_create_block_snapshot()`, for stories only
- **Deleted**: in `fanfic_remove_post_block()` together with `_fanfic_re_review_requested`

**Key files**: `includes/functions.php` — `fanfic_apply_post_block()`, `fanfic_create_block_snapshot()`, `fanfic_remove_post_block()`

---

## Revision Strategy

Both `fanfiction_story` and `fanfiction_chapter` post types declare `revisions` support. However, the `wp_revisions_to_keep` filter controls when revisions are actually retained:

- Returns `25` when: the story is blocked, the chapter is blocked, or the chapter belongs to a blocked story.
- Returns `0` for all other story and chapter saves.

This keeps native WordPress revision history available for blocked correction edits without accumulating revision noise for normal publishing activity.

**Key file**: `includes/functions.php` — `fanfic_filter_revisions_to_keep()`, registered in `includes/class-fanfic-core.php`

---

## Author Correction Flow

When a story is blocked, the owner (and only the owner) is allowed to open the story and chapter edit forms and save changes. The blocked state keeps content non-public regardless of those saves. Banned users cannot access this flow.

### Permission gate: `fanfic_current_user_can_edit()`

**File**: `includes/functions.php`

The function accepts an optional `$context` parameter. When `$context === 'blocked_edit'` and the current user is the content owner, the blocked state does not trigger an early return. All other callers use the default context, which keeps the existing behavior.

### Story form template

**File**: `templates/template-story-form.php`

- If the story is blocked and the current user is NOT the owner: renders a restriction banner and returns early.
- If the story is blocked and the current user IS the owner: renders a warning banner stating that edits will remain hidden until a moderator unblocks, then renders the full edit form. Shows the re-review button or awaiting badge.

### Chapter form template

**File**: `templates/template-chapter-form.php`

Same pattern:
- Blocked story with owner: shows warning banner, renders full chapter edit form.
- Blocked chapter with owner: shows warning banner, renders full chapter edit form.
- Non-owners: restriction banner and early return.

### Story handler: enforcing status on save

**File**: `includes/handlers/class-fanfic-story-handler.php`

Calls `fanfic_current_user_can_edit('story', $story_id, 'blocked_edit')`. When a non-moderator saves a blocked story, the handler forces the post status to remain at its current value — blocked content cannot be made visible through the owner edit form.

### Chapter handler: enforcing status on save

**File**: `includes/handlers/class-fanfic-chapter-handler.php`

Calls `fanfic_current_user_can_edit('chapter', $chapter_id, 'blocked_edit')`. Tracks `$is_blocked_edit` flag. When a non-moderator saves a blocked chapter, the status is preserved. Calls `fanfic_refresh_re_review_message()` on blocked saves.

---

## Re-review Submission

Once the author has corrected their blocked story, they can submit it for re-review. The re-review flow reuses the moderation messages table — no separate submission queue or correction tables exist.

### Flow

1. Author clicks "Submit for Re-review" on the blocked story edit form.
2. Frontend JS sends an AJAX request to `fanfic_submit_re_review`.
3. The endpoint verifies the user owns the blocked story.
4. It checks that no active unread re-review message already exists (`Fanfic_Moderation_Messages::has_active_message()`).
5. It calls `fanfic_build_change_summary($post_id)` to produce a human-readable diff of snapshot vs current state.
6. It creates a moderation message via `Fanfic_Moderation_Messages::create_message()`, prefixed with `[Re-review Request]`.
7. It sets `_fanfic_re_review_requested = 1` on the story.
8. Frontend swaps the submit button for an "Awaiting Re-review" badge.

### Change summary

**Function**: `fanfic_build_change_summary($post_id)` — `includes/functions.php`

Compares `_fanfic_block_snapshot` against the current story state field by field, using `fanfic_get_block_comparison_rows()`. Returns a human-readable string such as: "Title changed. Genres: added Romance, removed Horror. Cover image changed."

### Duplicate prevention

`has_active_message()` prevents multiple simultaneous re-review requests for the same story. After a moderator ignores or deletes the message, `_fanfic_re_review_requested` is cleared, allowing the author to resubmit.

**Key files**: `includes/class-fanfic-ajax-handlers.php` — `ajax_submit_re_review()`, `assets/js/fanfiction-interactions.js`

---

## Moderation Messaging System

The messaging system handles authenticated appeals from blocked or suspended authors through an open thread (chat) per restricted target. It is entirely separate from the public reports queue.

### Database table: `wp_fanfic_moderation_messages` (thread header)

| Column | Type | Description |
|---|---|---|
| `id` | int | Primary key |
| `author_id` | int | Author who sent the message |
| `target_type` | varchar(50) | `story`, `chapter`, or `user` |
| `target_id` | int | ID of the blocked story, chapter, or user |
| `message` | text | Latest author message preview (max 1000 chars) |
| `status` | varchar(20) | `unread`, `ignored`, `resolved`, `deleted` |
| `unread_for_moderator` | tinyint(1) | `1` when moderators have unread author messages |
| `unread_for_author` | tinyint(1) | `1` when author has unread moderator replies |
| `moderator_id` | int | Moderator who acted on the message |
| `moderator_note` | text | Optional note left by moderator on action |
| `author_reply` | text | Latest moderator reply preview |
| `created_at` | datetime | Submission timestamp |
| `last_message_at` | datetime | Last public chat activity timestamp |
| `updated_at` | datetime | Last update timestamp |

### Database table: `wp_fanfic_moderation_message_entries` (chat messages)

| Column | Type | Description |
|---|---|---|
| `id` | int | Primary key |
| `message_id` | int | Parent thread ID (`wp_fanfic_moderation_messages.id`) |
| `sender_id` | int | Sender user ID |
| `sender_role` | varchar(20) | `author`, `moderator`, or `system` |
| `message` | text | Entry body (max 1000 chars) |
| `is_internal` | tinyint(1) | Internal-only flag (currently `0` for public chat entries) |
| `created_at` | datetime | Entry timestamp |

### Message service: `Fanfic_Moderation_Messages`

**File**: `includes/class-fanfic-moderation-messages.php`

Static methods:

| Method | Description |
|---|---|
| `create_message()` | Create a new thread and insert first author entry |
| `send_author_message()` | Append author entry to active thread or create a new one |
| `send_moderator_message()` | Append moderator reply to open thread |
| `get_active_message()` | Get the active `status = unread` thread for author + target |
| `get_messages()` | List messages with status filter and pagination |
| `get_message()` | Fetch a single message by ID |
| `get_message_entries()` | Load ordered chat entries for a thread |
| `has_active_message()` | Check whether an unread message already exists for a target |
| `active_thread_has_unread_for_author()` | Check unread moderator reply flag for author UI |
| `mark_thread_read_for_moderator()` | Clear moderator unread flag after thread view |
| `mark_thread_read_for_author()` | Clear author unread flag after thread view |
| `count_needing_moderator()` | Count open threads where `unread_for_moderator = 1` |
| `update_status()` | Change message status (ignored, resolved, deleted) |
| `count_messages()` | Count messages matching a filter |
| `get_status_counts()` | Return counts by status for badge display |
| `cleanup_old_messages()` | Delete old closed threads and their entries |

### Author message submission

**AJAX action**: `fanfic_send_moderation_message`

Rules:
- Requires nonce and logged-in user.
- User must own the blocked story or chapter, or be the suspended account holder.
- Restriction must still be active at submit time.
- Message must be non-empty and ≤ 1000 characters.
- If an active thread exists for that target, message is appended to it.
- If not, a new thread is created.

### Thread load endpoint

**AJAX action**: `fanfic_get_moderation_thread`

Supports both contexts:
- **Author side**: load active thread by `target_type` + `target_id`.
- **Moderator side**: load specific thread by `message_id`.

The response includes thread status, permissions (`can_send`), unread flags for each side, and ordered `entries`.

### Moderator thread reply endpoint

**AJAX action**: `fanfic_send_moderation_reply`

Rules:
- Requires nonce, login, and `moderate_fanfiction` capability.
- Thread must exist and still be open (`status = unread`).
- Reply must be 1..1000 chars.
- Appends a `moderator` entry, updates unread flags, stores latest reply preview, and creates `TYPE_MOD_MESSAGE_REPLY` notification for the author.

**File**: `includes/class-fanfic-ajax-handlers.php`

### Canonical restriction context

**Function**: `fanfic_get_restriction_context($target_type, $target_id)` — `includes/functions.php`

Returns a unified array for any target type:

```
is_restricted      bool
restriction_type   string
target_type        string
target_id          int
reason_message     string
has_active_message bool
active_message_id  int
has_unread_moderator_reply bool
owner_id           int
moderator_reply    string
```

This is used by templates to populate author-facing banners consistently across story view, chapter view, story form, chapter form, and the account suspension notice.

### Author-facing banners

All restriction surfaces render through `fanfic_render_restriction_banner()`, which uses the restriction context to show:
- Restriction title and type
- Human-readable reason text
- Timestamp
- `Message Moderation` when no active thread exists.
- `Open Moderation Chat` when an active thread exists.
- Unread visual state when moderator has replied and author has not opened the thread yet.

Surfaces covered: story view, chapter view, story edit form, chapter edit form, account suspension notice in the site header area.

---

## Moderator Messages Tab

The moderation page at `wp-admin` has four tabs: Reports, Author Messages, Log, and Blocks.

### Messages table: `Fanfic_Messages_Table`

**File**: `includes/class-fanfic-messages-table.php`

Extends `WP_List_Table`. Columns: date, title, author, view message, review submitted, actions.

`View message` opens a modal that loads the full conversation via `fanfic_get_moderation_thread`. In that modal, moderators can send live replies through `fanfic_send_moderation_reply`.

### Moderator actions

Status-changing actions (Unblock, Ignore, Delete) flow through a single consolidated AJAX endpoint:

**AJAX action**: `fanfic_mod_message_action`
**Handler**: `ajax_mod_message_action()` — `includes/class-fanfic-ajax-handlers.php`

Requires `moderate_fanfiction` capability and nonce. Supported actions:

| Action | Effect |
|---|---|
| `unblock` | Calls the canonical unblock/unsuspend path for the target story, chapter, or user. Stores optional moderator note. |
| `ignore` | Marks message as ignored. For story targets, clears `_fanfic_re_review_requested` so author can resubmit. |
| `delete` | Marks message as deleted. For story targets, clears `_fanfic_re_review_requested`. |

Ignore/Delete actions write message-specific moderation log entries (`message_ignored`, `message_deleted`). Unblock uses the canonical unblock path for story/chapter/user.

---

## Moderator Compare View

When a re-review message exists and the blocked story has a snapshot, the moderator can open a comparison view directly from the Messages tab.

### Compare endpoint

**AJAX action**: `fanfic_get_block_comparison`
**Handler**: `ajax_get_block_comparison()` — `includes/class-fanfic-ajax-handlers.php`

Requires `moderate_fanfiction` capability. Loads the stored `_fanfic_block_snapshot`, reads the current story state, and returns an HTML comparison table.

### Comparison rendering

The comparison table shows one row per tracked field:
- Changed rows are highlighted (red for old value, green for new value).
- Unchanged rows are rendered in a muted style.
- Cover image changes show old and new thumbnails side by side.
- A link to WordPress's native revision comparison screen is included for detailed title and content diffs.

The snapshot comparison is canonical for taxonomy, image, and meta field changes. The WordPress revision screen is supplemental for title and long-form content diffs.

### Admin JS

**File**: `assets/js/fanfiction-admin.js`

Clicking `.fanfic-msg-review-changes` sends a request to `fanfic_get_block_comparison`, appends the returned HTML into the expanded message row, and toggles it on subsequent clicks.

### Admin CSS

**File**: `assets/css/fanfiction-admin.css`

Relevant selectors: `.fanfic-block-comparison`, `.fanfic-comparison-table`, `.snapshot-value`, `.current-value`, `.comparison-row.unchanged`, `.fanfic-diff-added`, `.fanfic-diff-removed`.

---

## Message Lifecycle

```
[author starts or continues chat]
              |
              v
      thread status: unread
              |
      [chat entries append]
 author -> moderator sets unread_for_moderator = 1
 moderator -> author sets unread_for_author = 1
              |
              v
        moderator decision
      +---------+---------+---------+
      |         |         |         |
      v         v         v         v
   continue   ignore    delete    unblock
     chat      close     close     resolve
      |         |         |         |
      |         +---- restriction stays
      |               author may re-message
      +---- while open, both sides can reply
```

- `unread`: open thread; chat is allowed
- `ignored`: restriction stays; author may resubmit
- `deleted`: dismissed; author may resubmit
- `resolved` (via unblock): restriction removed, snapshot and re-review marker cleaned up

---

## Moderation Log

The log table (`wp_fanfic_moderation_log`) uses `target_type varchar(50)` to support `user`, `story`, and `chapter` targets equally.

Message ignore/delete actions write dedicated entries, while unblock actions use the normal unblock/suspension paths. Log rows record moderator ID, action type, target type, target ID, and optional reason/note.

**File**: `includes/class-fanfic-moderation-log.php`

---

## Key Files Reference

| File | Role |
|---|---|
| `includes/functions.php` | `fanfic_apply_post_block()`, `fanfic_remove_post_block()`, `fanfic_create_block_snapshot()`, `fanfic_build_change_summary()`, `fanfic_current_user_can_edit()`, `fanfic_filter_revisions_to_keep()`, `fanfic_get_restriction_context()` |
| `includes/class-fanfic-post-types.php` | Declares `revisions` support on both post types |
| `includes/class-fanfic-core.php` | Registers `wp_revisions_to_keep` filter |
| `includes/class-fanfic-moderation-messages.php` | Thread + entries service (chat CRUD + unread flags) |
| `includes/class-fanfic-messages-table.php` | Admin messages list table |
| `includes/class-fanfic-moderation.php` | Moderation page with Messages tab |
| `includes/class-fanfic-ajax-handlers.php` | `fanfic_submit_re_review`, `fanfic_send_moderation_message`, `fanfic_get_moderation_thread`, `fanfic_send_moderation_reply`, `fanfic_get_block_comparison`, `fanfic_mod_message_action` |
| `includes/class-fanfic-database-setup.php` | `wp_fanfic_moderation_messages` + `wp_fanfic_moderation_message_entries` schema |
| `includes/class-fanfic-users-admin.php` | Suspension reason capture and storage |
| `includes/handlers/class-fanfic-story-handler.php` | Blocked edit save path for stories |
| `includes/handlers/class-fanfic-chapter-handler.php` | Blocked edit save path for chapters |
| `templates/template-story-form.php` | Owner edit gate + re-review button |
| `templates/template-chapter-form.php` | Owner edit gate for chapters |
| `assets/js/fanfiction-interactions.js` | Author moderation chat modal (load thread + send message) |
| `assets/js/fanfiction-admin.js` | Moderator thread modal, send reply, and comparison toggle |
| `assets/css/fanfiction-admin.css` | Comparison table styles |

---

## Scope Boundaries

The following are explicitly out of scope for this system:

- Nested/branching thread trees (current model is one linear chat stream per active target)
- Working-copy or shadow-post workflows
- Separate correction submission tables
- A dedicated Corrections moderation tab
- Approval-time copy-back from shadow content
- Chapter-only blocked content having its own dedicated snapshot and re-review path (currently story-centric)
