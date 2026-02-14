# Plan: Follow / Bookmark Button Refactor

## Context

The current system uses a single "Follow" button on both stories and chapters for all users. The goal is to differentiate the UX based on **user login state** and **content type**:

| Context | Logged-in | Logged-out |
|---------|-----------|------------|
| **Story** | Follow → Following (no change) | Follow → email modal for story |
| **Chapter** | **Bookmark → Bookmarked** + optional "also follow story?" modal | **Follow** → follows story + bookmarks chapter + email modal |

Key behavioral changes:
- Logged-in chapter button is renamed "Bookmark" / "Bookmarked"
- Logged-in chapter bookmark: if parent story NOT already followed, shows modal asking "Would you like to also receive alerts on story updates?" with "Yes, follow story" / "No, just bookmark"
- Logged-out users see "Follow" on both stories and chapters (no "Bookmark" label)
- Logged-out chapter follow: follows parent story + bookmarks chapter + email modal (current behavior preserved)

---

## Phase 1: PHP Button Label Changes

**File: `includes/shortcodes/class-fanfic-shortcodes-buttons.php`**

### `get_button_label()` (~line 390)
- Change the `'follow'` labels to be context-aware:
  - If `$action === 'follow'` AND context is chapter AND user is logged in:
    - inactive: `'Bookmark'`, active: `'Bookmarked'`
  - Otherwise (story, or logged-out):
    - inactive: `'Follow'`, active: `'Following'` (unchanged)
- Requires passing `$context` and `$is_logged_in` to `get_button_label()` (currently only receives `$action`)

### `get_button_aria_label()` (~line 490)
- Same logic: "Bookmark this chapter" / "Remove bookmark" for logged-in chapter context
- "Follow this story/chapter" for logged-out or story context

### `get_button_text_class()` (~line 530)
- Change text span class for chapter follow when logged-in: `'bookmark-text'` instead of `'follow-text'`

### `render_button()` (~line 220)
- Add `data-button-mode="bookmark"` or `data-button-mode="follow"` attribute to the button
  - `bookmark` when: logged-in + chapter context
  - `follow` when: everything else
- This lets JS know which behavior to apply without re-checking conditions

---

## Phase 2: PHP — Story Follow Modal (Logged-in, Chapter Context)

**File: `includes/shortcodes/class-fanfic-shortcodes-buttons.php`**

### New method: `render_story_follow_modal()`
- Render once per page (static flag, same pattern as email modal)
- Only render for **logged-in** users (opposite of email modal)
- HTML structure (reuse existing `.fanfic-modal` pattern):

```html
<div id="fanfic-story-follow-modal" class="fanfic-modal fanfic-story-follow-modal"
     role="dialog" aria-hidden="true" style="display:none;">
  <div class="fanfic-modal-overlay"></div>
  <div class="fanfic-modal-content">
    <button type="button" class="fanfic-modal-close" aria-label="Close">&times;</button>
    <h2>Would you like to also receive alerts on story updates?</h2>
    <p class="fanfic-modal-description">You'll be notified when new chapters are published.</p>
    <div class="form-actions">
      <button type="button" id="fanfic-story-follow-yes-btn" class="fanfic-button">
        Yes, follow story
      </button>
      <button type="button" id="fanfic-story-follow-no-btn" class="fanfic-button secondary">
        No, just bookmark
      </button>
    </div>
  </div>
</div>
```

### `action_buttons()` method
- Call `render_story_follow_modal()` alongside existing `render_follow_email_modal()`

---

## Phase 3: JavaScript — Bookmark vs Follow Logic

**File: `assets/js/fanfiction-interactions.js`**

### `initFollowButtons()` — rewrite click handler

The click handler needs to branch on `data-button-mode`:

```
Click → read data-button-mode from button

MODE = "bookmark" (logged-in, chapter):
  1. Toggle chapter bookmark in localStorage
  2. Update button UI (Bookmark ↔ Bookmarked)
  3. Update bookmarked badge
  4. Check if parent story is followed in localStorage:
     - NOT followed → show story-follow modal, store pending data
     - Already followed → send AJAX immediately (chapter bookmark only)
  5. Modal "Yes, follow story":
     - Follow parent story in localStorage
     - Update story follow button + following badge
     - Send TWO AJAX calls: one for chapter bookmark, one for story follow
  6. Modal "No, just bookmark":
     - Send AJAX for chapter bookmark only

MODE = "follow" (logged-out on any, or logged-in on story):
  Current behavior unchanged:
  - Toggle follow in localStorage
  - Auto-follow parent story if chapter (logged-out only)
  - Show email modal if logged-out + following
  - Send AJAX
```

### New: `initStoryFollowModal()` handler
- Open/close handlers for `#fanfic-story-follow-modal`
- "Yes, follow story" button:
  - `FanficLocalStore.setFollow(storyId, 0, true)`
  - Update story-level follow buttons on page
  - Update badges
  - Send AJAX for story follow: `toggleFollow(storyPostId, null, false)`
  - Send AJAX for chapter bookmark: `toggleFollow(chapterPostId, $button, false)`
- "No, just bookmark" button:
  - Close modal
  - Send AJAX for chapter bookmark only

### `updateFollowDisplay()` — make mode-aware
- Read `data-button-mode` from button
- If `bookmark`: toggle between "Bookmark" / "Bookmarked" text (use `.bookmark-text` span)
- If `follow`: toggle between "Follow" / "Following" text (use `.follow-text` span) — unchanged

### Follow count update
- Bookmark actions should NOT update the follow count display (follow count tracks story-level follows only)
- Only story-level follows increment/decrement the displayed follow count

---

## Phase 4: CSS

**File: `assets/css/fanfiction-frontend.css`**

- Add `.fanfic-story-follow-modal` styles (reuse `.fanfic-follow-email-modal` pattern)
- Add `.fanfic-button-bookmarked` class (same style as `.fanfic-button-followed` but can be differentiated later)
- Ensure `.fanfic-button-bookmark` base style exists (if separate from `.fanfic-button-follow`)

---

## Phase 5: Dashboard Label

**File: `templates/template-dashboard.php`**

- Already says "Bookmarked Chapters" (done in previous work) — no change needed
- "Followed Stories" — no change needed

---

## Implementation Order

1. PHP button labels + `data-button-mode` attribute (Phase 1)
2. PHP story-follow modal (Phase 2)
3. JS click handler rewrite + modal handlers (Phase 3)
4. CSS for new modal + bookmark button state (Phase 4)
5. Test all 4 scenarios (logged-in story, logged-in chapter, logged-out story, logged-out chapter)

---

## Files Modified

| File | Changes |
|------|---------|
| `includes/shortcodes/class-fanfic-shortcodes-buttons.php` | Button labels, data-button-mode, story-follow modal |
| `assets/js/fanfiction-interactions.js` | Click handler branching, story-follow modal handlers, updateFollowDisplay |
| `assets/css/fanfiction-frontend.css` | Story-follow modal styles, bookmark button styles |

---

## Verification

1. **Logged-in + Story**: Click Follow → toggles to "Following", badge appears. No modal. Click again → "Follow", badge hides.
2. **Logged-in + Chapter (story NOT followed)**: Click "Bookmark" → toggles to "Bookmarked", bookmarked badge appears. Modal: "Would you like to also receive alerts on story updates?" → "Yes, follow story" → story followed, following badge appears on story. OR "No, just bookmark" → only chapter bookmarked.
3. **Logged-in + Chapter (story already followed)**: Click "Bookmark" → toggles to "Bookmarked" silently, no modal. Bookmarked badge appears.
4. **Logged-out + Story**: Click "Follow" → email modal appears. "Follow Only" or "Follow & Subscribe". Button shows "Following".
5. **Logged-out + Chapter**: Click "Follow" → follows parent story + bookmarks chapter in localStorage. Email modal appears (for story). Both badges appear.
6. **Dashboard**: "Followed Stories" and "Bookmarked Chapters" sections show correct items.
