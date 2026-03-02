# Blocked Story Correction & Moderator Compare System

## Context

Authors whose stories are blocked have no way to fix the problem — the edit form returns early and blocks all access. We need to let authors edit their blocked story (safe because the `_fanfic_story_blocked` meta hides it from public), submit for re-review, and give moderators a before/after comparison to decide whether to unblock.

No new DB tables. No shadow copies. Authors edit the real post. A JSON snapshot at block time captures the "before" state. WordPress revisions track title/content diffs, but they must be scoped to blocked-content saves only.

Important implementation note: opening `?action=edit` does not create or control revisions by itself. WordPress decides whether to save a revision during the later `wp_update_post()` request. So the correct design is blocked-only revision retention at save time, not "turn revisions on when the user clicks edit".

---

## Phase 1: Scoped Revisions for Blocked Content

**File:** `includes/class-fanfic-post-types.php`
- Line ~102: Add `'revisions'` to `fanfiction_story` supports array
- Line ~163: Add `'revisions'` to `fanfiction_chapter` supports array

**File:** plugin bootstrap / hooks

Add a `wp_revisions_to_keep` filter:
- Return `0` for normal `fanfiction_story` and `fanfiction_chapter` saves
- Return a positive value only when:
  - the story itself is blocked, or
  - the chapter is blocked, or
  - the chapter belongs to a blocked story

This gives us:
- revision support available to WordPress when blocked edits are saved
- no revision accumulation for normal story/chapter editing
- no need to toggle support on the edit-page GET request

Note: the snapshot is still required because the first blocked edit revision is the edited state, not the original blocked state.

---

## Phase 2: Snapshot at Block Time

**File:** `includes/functions.php`

### 2a. New function `fanfic_create_block_snapshot( $post_id )`
Insert after `fanfic_remove_post_block()` (~line 510). Captures:
- `post_title`, `post_content`
- `genre_ids` + `genre_names` — via `wp_get_object_terms($id, 'fanfiction_genre')`
- `status_ids` + `status_names` — via `wp_get_object_terms($id, 'fanfiction_status')`
- `fandom_ids` + `fandom_labels` — via `Fanfic_Fandoms::get_story_fandom_ids()` / `get_story_fandom_labels()`
- `warning_ids` + `warning_names` — via `Fanfic_Warnings::get_story_warnings()`
- `language_id` + `language_label` — via `Fanfic_Languages::get_story_language_id()` / `get_story_language_label()`
- `licence` — `_fanfic_licence` meta
- `age_rating` — `_fanfic_age_rating` meta
- `cover_image_id` + `cover_image_url` — `get_post_thumbnail_id()` + `wp_get_attachment_image_url()`
- `author_notes_enabled`, `author_notes_position`, `author_notes` — respective meta
- `is_original_work` — `_fanfic_is_original_work` meta
- `snapshot_time` — `time()`

Stores as `_fanfic_block_snapshot` post meta (JSON).

### 2b. Call snapshot in `fanfic_apply_post_block()` (~line 439)
After setting `_fanfic_blocked_timestamp`, call `fanfic_create_block_snapshot( $post_id )` for stories only.

This snapshot becomes the authoritative "before block edits" baseline for moderator comparison. Revisions complement it; they do not replace it.

### 2c. Cleanup in `fanfic_remove_post_block()` (~line 500)
Add `delete_post_meta` for `_fanfic_block_snapshot` and `_fanfic_re_review_requested`.

---

## Phase 3: Let Authors Edit Blocked Stories

### 3a. Permission function — `fanfic_current_user_can_edit()`
**File:** `includes/functions.php` (~line 729)

Add optional `$context` parameter. When `$context === 'blocked_edit'` and user is the post author, allow edit even if story/chapter is blocked. Default context keeps current behavior (blocks editing).

### 3b. Story form template gate
**File:** `templates/template-story-form.php` (lines 96-106)

Replace early return with:
- Non-owners of blocked stories: still return early with restriction banner
- Owners of blocked stories: show warning banner ("Story blocked — edits won't be visible until moderator unblocks") + "Submit for Re-review" button (or "Re-review Submitted" badge), then continue rendering the full edit form

### 3c. Chapter form template gate
**File:** `templates/template-chapter-form.php` (lines 344-366)

Same pattern — let the chapter's story author edit chapters of their blocked story with a warning banner.

### 3d. Story handler edit path
**File:** `includes/handlers/class-fanfic-story-handler.php` (~line 659)

Pass `'blocked_edit'` context to `fanfic_current_user_can_edit()`. When saving a blocked story as a non-moderator, force-keep current post_status (prevent publish/draft toggles).

### 3e. Chapter handler edit path
**File:** `includes/handlers/class-fanfic-chapter-handler.php`

Pass `'blocked_edit'` context to `fanfic_current_user_can_edit()` for blocked-story / blocked-chapter edits. When saving as a non-moderator, force-keep the current chapter status.

---

## Phase 4: "Submit for Re-review" Action

### 4a. Change summary helper — `fanfic_build_change_summary( $post_id )`
**File:** `includes/functions.php`

Compares `_fanfic_block_snapshot` against current state. Returns human-readable string: "Title changed. Genres: added Romance, removed Horror. Cover image changed." etc.

### 4b. AJAX endpoint — `fanfic_submit_re_review`
**File:** `includes/class-fanfic-ajax-handlers.php`

- Validates user owns the blocked story
- Checks no active moderation message exists
- Builds change summary via `fanfic_build_change_summary()`
- Creates moderation message via `Fanfic_Moderation_Messages::create_message()` prefixed with "[Re-review Request]"
- Sets `_fanfic_re_review_requested = 1` meta
- Returns success

### 4c. Frontend JS handler
**File:** `assets/js/fanfiction-interactions.js`

Click handler for `.fanfic-submit-re-review-btn` — AJAX call, swap button for "Awaiting" badge on success.

### 4d. Localized strings
**File:** `includes/class-fanfic-core.php` (fanficAjax strings)

Add: `reReviewSent`, `reReviewError`

---

## Phase 5: Moderator Compare View

### 5a. AJAX endpoint — `fanfic_get_block_comparison`
**File:** `includes/class-fanfic-ajax-handlers.php`

Admin-only endpoint. Loads snapshot, builds current state, returns HTML comparison table:
- Each field as a row: field name | old value | new value
- Changed rows highlighted (red/green)
- Unchanged rows grayed out
- Cover image: old/new thumbnails side by side
- Link to WordPress revision comparison for detailed content diff
- Revision link is supplemental. Snapshot comparison remains the canonical source for taxonomy/meta/media changes.

### 5b. "Compare Changes" button in messages table
**File:** `includes/class-fanfic-messages-table.php` — `single_row()` detail row

Add button when target has `_fanfic_block_snapshot` meta. Sits alongside existing Unblock/Ignore/Delete buttons.

### 5c. Admin JS handler
**File:** `assets/js/fanfiction-admin.js`

Click `.fanfic-msg-compare-changes` → AJAX load → append comparison HTML into detail row. Toggle on subsequent clicks.

---

## Phase 6: Admin CSS

**File:** `assets/css/fanfiction-admin.css`

Comparison table styles: `.fanfic-block-comparison`, `.fanfic-comparison-table`, `.snapshot-value` (red bg), `.current-value` (green bg), `.comparison-row.unchanged`, cover image max-width, `.fanfic-diff-added`, `.fanfic-diff-removed`.

---

## Phase 7: Cleanup of `_fanfic_re_review_requested`

**File:** `includes/class-fanfic-ajax-handlers.php` — `ajax_mod_message_action()`

Clear `_fanfic_re_review_requested` meta when moderator ignores or deletes the message (so author can resubmit).

---

## Edge Cases

- **Author edits but doesn't submit re-review**: Story stays blocked, edits are saved but invisible. No moderator action needed.
- **Banned user**: Role is `fanfiction_banned_user`, story handler already rejects at line 387. Banned users cannot edit. Only individually-blocked active authors get the edit flow.
- **Multiple re-review attempts**: `has_active_message()` prevents duplicates. After moderator resolves/ignores, author can resubmit.
- **Pre-existing blocked stories (no snapshot)**: "Compare Changes" button only appears when snapshot exists. Old blocked stories use existing workflow.
- **Revision scope**: normal story/chapter edits should not retain revisions; only blocked stories and their chapters should.
- **Edit click vs save**: visiting the edit form should not create revisions. Revisions are only retained when a blocked item is actually saved.
- **Cover image change only**: Snapshot tracks `cover_image_id` + URL, comparison shows thumbnails side by side.

---

## Verification

1. Block a story → confirm `_fanfic_block_snapshot` meta is created with all fields
2. Edit a normal, unblocked story/chapter → confirm no retained revision is created for plugin content
3. Author visits edit form for their blocked story → sees warning banner + edit form (not early return)
4. Author edits title, changes genre, swaps cover image → saves successfully
5. Confirm blocked story save retained a revision; confirm blocked chapter save retained a revision
6. Author clicks "Submit for Re-review" → moderation message created, button changes to "Awaiting"
7. Moderator opens Messages tab → sees re-review message → clicks "Compare Changes"
8. Comparison view shows old vs new for each changed field, unchanged fields grayed
9. Moderator clicks "Unblock" → story unblocked, snapshot deleted, author notified
10. Unblock a story → confirm `_fanfic_block_snapshot` and `_fanfic_re_review_requested` meta cleaned up
11. Banned user tries to edit blocked story → rejected (cannot access form)
