# Plan: Merge Subscribe into Follow System

## Context

The plugin currently has **two separate systems**: Follow (localStorage + interactions table) and Subscribe (email subscriptions table + verification). The goal is to merge them into a single unified Follow system where email subscription is an optional add-on within the follow flow. This eliminates the standalone subscribe button and simplifies the UX.

**Key behavioral changes:**
- Follow button is the single entry point for both following and email subscriptions
- Logged-out users: follow → modal offers email subscription (no verification required)
- Logged-in users: follow → standard toggle, email alerts controlled by preferences
- Chapter follow → auto-follows parent story (deduplicated) + bookmarks the chapter
- Dashboard: "Followed Chapters" renamed to "Bookmarked Chapters"
- Unsubscribe link in emails only stops emails, doesn't remove the follow

---

## Phase 1: Database Changes

**File: `includes/class-fanfic-database-setup.php`**

- Change `verified tinyint(1) NOT NULL DEFAULT 0` → `DEFAULT 1` in `wp_fanfic_email_subscriptions` table
- Bump `DB_VERSION` to `'1.8.0'`
- Add migration: `UPDATE wp_fanfic_email_subscriptions SET verified = 1 WHERE verified = 0`

No new tables needed.

---

## Phase 2: PHP Backend — Follow Auto-Parent

**File: `includes/class-fanfic-interactions.php`**
- Add `upsert_follow($post_id, $user_id, $anonymous_uuid)` — idempotent insert (doesn't remove if exists). Uses existing `upsert_interaction()` with `interaction_type = 'follow'`. Fires `do_action('fanfic_follow_added')` only on new insert.

**File: `includes/class-fanfic-follows.php`**
- Add `auto_follow_parent_story($user_id, $chapter_id, $anonymous_uuid)` — checks if chapter's parent story is already followed, if not, calls `upsert_follow()` on the story
- Modify `toggle_follow()` — after a successful follow (not unfollow) of a chapter, call `auto_follow_parent_story()`
- Clear caches for both chapter and parent story

---

## Phase 3: PHP Backend — Email Subscription Simplification

**File: `includes/class-fanfic-email-subscriptions.php`**
- Simplify `subscribe()`: remove verification email logic, set `verified = 1` on insert, return success immediately
- Remove `verify_subscription()` method
- Remove `send_verification_email()` method
- Simplify `handle_unsubscribe_link()`: remove verify action handler, keep unsubscribe handler, render a proper confirmation page ("You will no longer receive email updates for [story name]")
- Add `subscribe_from_follow($email, $story_id)` — streamlined method for the follow modal flow

---

## Phase 4: PHP Backend — Notification Triggers

**File: `includes/class-fanfic-email-queue.php`**
- Modify `handle_chapter_publish()` to also query logged-in story followers from `wp_fanfic_interactions` and send emails to those with email prefs enabled (deduplicating against email subscribers)
- Add `handle_chapter_update()` — for publish→publish transitions where content changed. Notifies:
  - Chapter bookmarkers (users who followed that specific chapter)
  - Story followers (for all chapter updates)
- Add `handle_story_status_change()` — notifies story followers when status changes to "finished" or to "ongoing"

**File: `includes/class-fanfic-notifications.php`**
- Add constants: `TYPE_CHAPTER_UPDATE`, `TYPE_STORY_STATUS_CHANGE`
- Add `create_chapter_update_notification()` — for chapter content updates
- Add `create_story_status_notification()` — for story status changes

**File: `includes/class-fanfic-notification-preferences.php`**
- Add new preference keys: `fanfic_email_chapter_update`, `fanfic_email_story_status`, `fanfic_inapp_chapter_update`, `fanfic_inapp_story_status` (all default ON)

---

## Phase 5: PHP Backend — AJAX Handlers

**File: `includes/class-fanfic-ajax-handlers.php`**
- Modify `ajax_toggle_follow()`: accept optional `email` param. After successful follow, if email provided + valid, call `subscribe_from_follow()`. Return `email_subscribed: true/false` in response.
- Remove `ajax_subscribe_email()` handler
- Remove `fanfic_subscribe_email` AJAX registration from `init()`

---

## Phase 6: PHP Backend — Subscribe Button Removal

**File: `includes/shortcodes/class-fanfic-shortcodes-buttons.php`**
- Remove `'subscribe'` from action arrays in `get_context_actions()` (lines 130, 134)
- Remove `enable_subscribe` setting check (lines 122, 149-151)
- Remove `render_subscribe_button()` method
- Remove `render_subscription_modal()` method
- Remove subscribe case from `render_button()` (line 232-235)
- Remove subscribe from `get_button_state()`, `get_button_label()`, `get_button_icon()`, `get_button_aria_label()`
- Add `data-user-logged-in="0/1"` to follow button data attributes
- Add `render_follow_email_modal()` — renders once per page (static flag). Modal HTML:
  - Title: "Would you like to receive email updates?"
  - Text: "Get notified when new chapters are published for this story."
  - Email input (pre-filled from localStorage via JS)
  - Buttons: "Follow Only" | "Follow & Subscribe"

**File: `includes/class-fanfic-settings.php`**
- Remove `enable_subscribe` from defaults, sanitization, and admin UI

---

## Phase 7: Frontend JavaScript

**File: `assets/js/fanfiction-interactions.js`**

### Follow button click handler (`initFollowButtons()`)
- For **logged-out** users following (not unfollowing): Update localStorage + UI optimistically, then **show modal** instead of immediately sending AJAX
  - Modal "Follow Only": send AJAX `fanfic_toggle_follow` without email
  - Modal "Follow & Subscribe": validate email, store in `localStorage('fanfic_user_email')`, send AJAX with email param
- For **logged-in** users: same as current (immediate AJAX, no modal)

### Chapter auto-follow story in localStorage
- When following a chapter, also set `FanficLocalStore.setFollow(storyId, 0, true)` for the parent story
- Update any story-level follow button on the page
- Update any "Following" badge on the page for that story

### Modal handlers
- Add modal open/close handlers for the follow-email modal
- Pre-fill email from `localStorage.getItem('fanfic_user_email')`

### Badge system (zero DB queries — pure localStorage)
- Extend `applyUiFromLocal()` in `FanficUnifiedInteractions` to also show/hide badges:
  ```javascript
  // On page load, for every story card and chapter element:
  $('.fanfic-badge-following[data-badge-story-id]').each(function() {
      var storyId = $(this).data('badge-story-id');
      var entry = FanficLocalStore.getChapter(storyId, 0);
      $(this).toggle(!!(entry && entry.follow));
  });
  $('.fanfic-badge-bookmarked[data-badge-story-id][data-badge-chapter-id]').each(function() {
      var storyId = $(this).data('badge-story-id');
      var chapterId = $(this).data('badge-chapter-id');
      var entry = FanficLocalStore.getChapter(storyId, chapterId);
      $(this).toggle(!!(entry && entry.follow));
  });
  ```
- When follow is toggled, also toggle corresponding badge visibility in real-time

### Cleanup
- Remove all subscribe-specific code: `initEmailSubscriptionForms()`, `subscribeEmail()`, subscribe button handlers

---

## Phase 8: CSS Changes

**File: `assets/css/fanfiction-frontend.css`**

### Remove
- `.fanfic-button-subscribe`, `.fanfic-button-subscribed` styles
- `.fanfic-subscribe-button`, `.fanfic-subscribe-modal`, `.fanfic-subscribe-message` styles

### Add
- `.fanfic-follow-email-modal` styles (reuse existing modal pattern)
- `.fanfic-badge-following` — small green badge, hidden by default (`display:none`), shown by JS
- `.fanfic-badge-bookmarked` — small blue/info badge, hidden by default, shown by JS
- Badge positioning within `.fanfic-story-card-header` and chapter navigation

---

## Phase 9: Template & Badge Placeholder Changes

**File: `includes/functions.php` — `fanfic_get_story_card_html()`**
- Add hidden badge placeholder in story card header:
  ```html
  <span class="fanfic-badge fanfic-badge-following"
        data-badge-story-id="{story_id}" style="display:none;">Following</span>
  ```
- Badge rendered for ALL users (PHP doesn't check localStorage). JS shows/hides it on DOM ready.

**File: `templates/template-chapter-view.php`**
- Add hidden "Bookmarked" badge in chapter header:
  ```html
  <span class="fanfic-badge fanfic-badge-bookmarked"
        data-badge-story-id="{story_id}" data-badge-chapter-id="{chapter_id}"
        style="display:none;">Bookmarked</span>
  ```
- Also add "Following" badge in story title area for the parent story

**File: `templates/template-dashboard.php`**
- Rename "Followed Chapters" → "Bookmarked Chapters" in heading text

**File: `templates/emails/verification.php`**
- Delete this file (verification emails no longer sent)

**File: `templates/emails/new-chapter.php`**
- Update unsubscribe text to "Stop receiving email updates for this story"

---

## Phase 10: Cleanup & Verification

- Grep entire codebase for stale references: `enable_subscribe`, `verify_subscription`, `send_verification_email`, `fanfic_subscribe_email`, `subscribe-modal`, `fanfic-button-subscribe`
- Verify cache invalidation covers auto-follow of parent story
- Ensure email deduplication (logged-in user email vs email subscription table)

---

## Implementation Order

1. Database schema change (Phase 1)
2. `upsert_follow()` in Interactions (Phase 2)
3. Auto-follow parent story in Follows (Phase 2)
4. Email subscription simplification (Phase 3)
5. AJAX handler merge (Phase 5)
6. Notification triggers (Phase 4)
7. Subscribe button removal + follow modal PHP (Phase 6)
8. Badge placeholders in templates & story cards (Phase 9)
9. JavaScript changes + badge logic (Phase 7)
10. CSS cleanup + badge styles (Phase 8)
11. Dashboard rename + email template cleanup (Phase 9)
12. Final grep sweep (Phase 10)

---

## Badge System — How It Works (Zero DB Queries)

**Storage**: localStorage key `fanfic_interactions` already stores all follow states:
- `story_X_chapter_0` with `follow: true` = story followed → "Following" badge
- `story_X_chapter_Y` with `follow: true` = chapter bookmarked → "Bookmarked" badge

**Rendering flow**:
1. PHP outputs hidden `<span>` badge placeholders with `data-badge-story-id` (and `data-badge-chapter-id` for chapters)
2. On `$(document).ready()`, `applyUiFromLocal()` iterates all badge elements and shows/hides based on localStorage
3. When user clicks follow, badge toggles in real-time alongside the button state
4. Same code path for logged-in and logged-out users — localStorage is always in sync with the server

**Performance**: No AJAX, no DB queries, no server-side rendering logic for badges.

---

## Verification

- Follow a story as logged-out → modal appears, email pre-fills from previous use, "Following" badge appears on story card
- "Follow Only" → story followed in localStorage, badge visible, no email subscription
- "Follow & Subscribe" → story followed + email stored, badge visible, emails sent on new chapters
- Follow a chapter → parent story auto-followed (no duplicates), chapter bookmarked, "Following" badge on story + "Bookmarked" badge on chapter
- Follow same story's chapters multiple times → only one story follow entry, badges all consistent
- Logged-in user follow → "Following" button + badge, no modal, notifications + emails based on preferences
- Unsubscribe link in email → stops emails, follow + badge remains
- Dashboard shows "Followed Stories" and "Bookmarked Chapters"
- Low server load: queue-based emails, transient caching, batch queries, zero-query badges
