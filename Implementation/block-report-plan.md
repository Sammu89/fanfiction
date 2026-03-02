# Block / Report System Audit And Implementation Plan

Date: March 1, 2026
Status: Messaging system implemented; correction workflow plan updated to adopted snapshot/revision design
Scope: Blocked-author messaging implementation plus the simplified blocked-content correction and compare workflow.

## Objective

Implement a complete author-to-moderation messaging flow for blocked stories, blocked chapters, and suspended accounts, while keeping the existing public content report system separate.

This phase must deliver:

- author-visible block and suspension motives
- a `Message Moderation` action on blocked and suspended banners
- a modal with a 1000 character maximum
- a new moderation `Messages` tab
- moderator actions: `Unblock`, `Ignore`, `Delete`
- optional moderator note on unblock
- moderation log entries for every action

This phase must not deliver:

- report-system unification
- threaded back-and-forth conversations

This document originally described a later, more complex `Correct Problem` workflow based on working copies and extra tables. That design has now been superseded by the simpler snapshot-plus-revision approach described at the end of this document.

## Decision

Do not extend the current public reports queue for this feature.

Build a separate moderation-message system for blocked authors.

Reason:

- public reports are anonymous or public-user complaints about content
- blocked-author messages are authenticated appeals tied to restriction state
- the data model, actions, UI, and lifecycle are different
- the existing report queue already has structural UI issues and should not be the foundation for this work

## Audited Files

Concept docs:

- `docs/backend/blocking-and-suspension.md`
- `Implementation/Ban System.md`
- `Implementation/Audit Findings.md`

Key implementation files:

- `includes/functions.php`
- `includes/class-fanfic-core.php`
- `includes/class-fanfic-database-setup.php`
- `includes/class-fanfic-moderation.php`
- `includes/class-fanfic-moderation-table.php`
- `includes/class-fanfic-moderation-log.php`
- `includes/class-fanfic-users-admin.php`
- `includes/class-fanfic-stories-table.php`
- `includes/class-fanfic-ajax-handlers.php`
- `includes/shortcodes/class-fanfic-shortcodes-buttons.php`
- `includes/shortcodes/class-fanfic-shortcodes-forms.php`
- `templates/template-dashboard.php`
- `templates/template-story-form.php`
- `templates/template-story-view.php`
- `templates/template-chapter-form.php`
- `templates/template-chapter-view.php`
- `assets/js/fanfiction-admin.js`
- `assets/js/fanfiction-interactions.js`

## Current System Summary

### Restriction model

Current restriction types:

- hide
- block
- suspend user

Current block state for stories and chapters is stored with:

- `_fanfic_story_blocked`
- `_fanfic_chapter_blocked`
- `_fanfic_block_type`
- `_fanfic_block_reason`
- `_fanfic_blocked_timestamp`

Current suspension state is stored with:

- `fanfic_banned`
- `fanfic_banned_by`
- `fanfic_banned_at`
- `fanfic_original_role`

Current public reports are stored in:

- `wp_fanfic_reports`

Current moderation log is stored in:

- `wp_fanfic_moderation_log`

## Audit Findings

### 1. Suspension reason is not canonically stored or displayed

Observed behavior:

- banning writes `fanfic_banned_by` and `fanfic_banned_at`
- the moderation log reads `fanfic_suspension_reason`
- no reliable code path writes `fanfic_suspension_reason`
- the frontend suspension notice only shows the date

Impact:

- authors cannot understand why the account is suspended
- log quality is incomplete
- requested suspension motive banner cannot be implemented cleanly until this is fixed

### 2. Chapter blocks do not capture a real reason

Observed behavior:

- chapter block/unblock is a simple toggle
- block action calls `fanfic_block_chapter( $chapter_id, 'manual', ... )`
- there is no chapter-specific reason capture UI

Impact:

- blocked chapter banner cannot explain the actual motive
- chapter moderation is not auditable enough for this feature

### 3. Author-facing block messaging is inconsistent

Observed behavior:

- dashboard tooltips use `fanfic_get_blocked_story_message()`
- story form uses its own local reason-label map
- chapter form shows generic blocked text
- story view does not render canonical blocked reason text
- chapter view only shows generic warning text like `this chapter is blocked`

Impact:

- the same restriction can produce different explanations depending on the page
- there is no single canonical source for author-facing restriction copy

### 4. The current public report queue UI is mismatched

Observed behavior:

- moderation table outputs selectors like `fanfic-view-report-button`, `fanfic-view-report-link`, `fanfic-resolve-report`
- admin JS listens for `.view-report-button` and `.mark-reviewed-button`
- admin JS expects button nonces that the table does not emit

Impact:

- current report detail / review behavior is fragile or broken
- new author-messaging UI should not reuse this architecture

### 5. Moderation log schema does not support chapter targets

Observed behavior:

- the log table schema limits `target_type` to `user` or `story`
- runtime logging code inserts `chapter`

Impact:

- chapter block/unblock logging is structurally inconsistent
- the log must be fixed before building chapter-related message actions

### 6. Story block reason support is only partial

Observed behavior:

- story blocks can store reasons
- story bulk block modal uses a fixed dropdown
- unblock does not collect any note

Impact:

- story motives are better than chapter/account, but still not complete for the requested workflow

### 7. Native WordPress revisions are not enabled on story and chapter post types

Observed behavior:

- `fanfiction_story` supports `title`, `editor`, `thumbnail`, `custom-fields`, `comments`
- `fanfiction_chapter` supports `title`, `editor`, `custom-fields`, `page-attributes`, `comments`
- neither post type declares `revisions`

Impact:

- native revision compare cannot be used yet for stories or chapters
- any correction workflow that depends on WordPress diff UI must first enable revisions support

### 8. Native WordPress revisions alone will not cover all required moderation diffs

Observed behavior:

- story and chapter handlers write important values directly through `update_post_meta()`
- story handlers also write taxonomy relationships through `wp_set_post_terms()`
- story cover image is stored with `_fanfic_featured_image` and `set_post_thumbnail()`

Impact:

- native revision compare is useful for title and content fields
- native revision compare is not enough on its own for taxonomies, images, and most custom meta
- the future correction workflow must combine WordPress revision compare with a custom structured change summary

## Product Requirements For This Phase

### Author side

- blocked story must show readable motive
- blocked chapter must show readable motive
- suspended account must show readable motive
- each must offer `Message Moderation`
- message modal must enforce max 1000 chars

### Moderator side

- a new `Messages` tab must exist under moderation
- each message must show:
  - author username
  - origin type: story, chapter, or account
  - origin target
  - message body
  - unread/read state
  - timestamps
- moderator actions:
  - `Unblock`
  - `Ignore`
  - `Delete`
- `Unblock` must allow optional note
- every action must be logged

## Recommended Architecture

### 1. New moderation messages table

Create a dedicated table:

- `wp_fanfic_moderation_messages`

Recommended fields:

- `id`
- `author_id`
- `origin_type` with values `account`, `story`, `chapter`
- `origin_id`
- `restriction_type`
- `restriction_reason_code`
- `restriction_reason_text`
- `message`
- `status` with values `unread`, `ignored`, `resolved`
- `moderator_id`
- `moderator_action`
- `moderator_note`
- `created_at`
- `updated_at`
- `handled_at`

### 2. Canonical restriction context helpers

Add helpers that return structured restriction data for:

- story block
- chapter block
- account suspension

Each helper should return:

- whether restricted
- title
- reason code
- reason text
- display text
- timestamp
- origin type
- origin id

### 3. Canonical reason storage

Keep story and chapter block meta, but ensure chapter actions actually write useful values.

For user suspension, add canonical user meta:

- `fanfic_suspension_reason_code`
- `fanfic_suspension_reason_text`

At minimum, this phase must store readable suspension text.

### 4. Separate moderation messages UI

Add a `Messages` tab to the moderation page.

Keep existing moderation tabs conceptually as:

- `Queue` for public reports
- `Messages` for blocked-author messaging
- `Log` for moderation history

### 5. Moderation log schema fix

Change moderation log `target_type` away from enum and allow at least:

- `user`
- `story`
- `chapter`

Prefer `varchar(50)` to avoid repeating the same schema limitation later.

## Backend Implementation Plan

### Database

Update:

- `includes/class-fanfic-database-setup.php`

Tasks:

- add moderation messages table SQL
- ensure-table method for messages table
- update moderation log table SQL to allow chapter targets

### New message service

Create:

- `includes/class-fanfic-moderation-messages.php`

Responsibilities:

- create message
- list messages
- count messages
- fetch single message
- ignore message
- resolve message
- delete message

### Canonical helpers

Update:

- `includes/functions.php`

Add helpers such as:

- `fanfic_get_story_block_context()`
- `fanfic_get_chapter_block_context()`
- `fanfic_get_account_suspension_context()`
- `fanfic_get_restriction_context_for_origin()`

### Suspension write-path

Update:

- `includes/class-fanfic-users-admin.php`

Tasks:

- capture and store suspension reason
- ensure unban flow remains clean
- ensure log reason is meaningful

### Chapter block write-path

Update:

- `includes/class-fanfic-core.php`
- `templates/template-story-form.php`
- `templates/template-chapter-form.php`

Tasks:

- replace chapter block toggle-only flow with reason-aware blocking
- preserve unblock path
- allow optional unblock note from moderation message action

### Author message submission endpoint

Add authenticated AJAX action, recommended name:

- `fanfic_submit_moderation_message`

Rules:

- nonce required
- logged-in only
- user must own the blocked story or chapter, or be the suspended account owner
- restriction must still exist at submit time
- message must be non-empty and <= 1000 chars

### Moderator message action endpoints

Add actions such as:

- `fanfic_get_moderation_message`
- `fanfic_ignore_moderation_message`
- `fanfic_delete_moderation_message`
- `fanfic_unblock_from_moderation_message`

Rules:

- moderator/admin capability required
- nonce required
- target validation required
- log every action

## Frontend Implementation Plan

### Author-facing banners

Standardize banner rendering across:

- `templates/template-story-form.php`
- `templates/template-chapter-form.php`
- `templates/template-story-view.php`
- `templates/template-chapter-view.php`
- account suspension notice in `includes/class-fanfic-core.php`

Each banner must show:

- restriction title
- readable motive
- timestamp if available
- current restriction explanation
- `Message Moderation`

### Message modal

Do not reuse the public report modal.

Create a dedicated modal, recommended ID:

- `fanfic-moderation-message-modal`

Use:

- clear origin context
- textarea
- character limit messaging
- AJAX submit

### Frontend JS

Update:

- `assets/js/fanfiction-interactions.js`

Tasks:

- open moderation-message modal
- validate content
- submit via AJAX
- display success and error states

## Admin Implementation Plan

### Moderation page

Update:

- `includes/class-fanfic-moderation.php`

Tasks:

- add `Messages` tab
- keep `Queue` for public reports
- keep `Log`

### Messages table UI

Create:

- `includes/class-fanfic-moderation-messages-table.php`

Recommended columns:

- status
- author
- origin
- target
- submitted
- preview
- actions

### Admin JS

Update:

- `assets/js/fanfiction-admin.js`

Use new selectors for the Messages tab only.

Do not build the new system on top of the current report queue selectors.

## Logging Requirements

Every moderator action in the new system must log.

Required action types:

- `message_ignore`
- `message_delete`
- `message_unblock_story`
- `message_unblock_chapter`
- `message_unsuspend_user`

Recommended logging behavior:

- log against the real moderated target: story, chapter, or user
- optionally include message ID in the reason text

## Edge Cases

- story blocked because account is suspended
- chapter blocked while story is also blocked
- message opened after target already unblocked elsewhere
- deleted story or chapter target
- multiple messages from same author and origin
- attempted message submission after restriction removed

Recommended behavior:

- always validate current state at action time
- preserve message readability even if target later disappears
- disable or hide unblock when target no longer requires it

## Testing Plan

### Author tests

- blocked story shows motive on dashboard, story view, story edit
- blocked chapter shows motive on chapter view and chapter edit
- suspended account shows motive in suspension notice
- message modal opens correctly from each state
- empty and >1000 char messages fail
- valid messages succeed

### Moderator tests

- `Messages` tab loads
- unread messages display author, origin, and content correctly
- `Ignore` changes message state without changing restriction
- `Delete` removes message
- `Unblock` removes correct restriction and stores optional note
- all actions write moderation log entries

### Regression tests

- public report submission still works
- public report queue remains separate
- public readers still cannot access blocked content
- authors remain read-only while blocked or suspended
- moderators retain full access

## Recommended Implementation Order

1. Add moderation messages table
2. Fix moderation log schema target support
3. Add suspension reason storage
4. Add canonical restriction context helpers
5. Add author message modal and submit endpoint
6. Add moderation `Messages` tab and listing
7. Add moderator `Ignore`, `Delete`, and `Unblock` actions
8. Standardize author-facing banners across story, chapter, and account surfaces

## Acceptance Criteria

The phase is complete when:

- authors can read the motive for blocked story, blocked chapter, and suspended account states
- each relevant restriction surface offers `Message Moderation`
- author messages are stored separately from public reports
- moderation has a working `Messages` tab
- moderators can `Unblock`, `Ignore`, and `Delete`
- unblock supports an optional note
- every moderator action is logged
- chapter logging works because the moderation log schema supports chapter targets
- public reports remain separate and unaffected

## Implementation Status As Of March 3, 2026

This section compares the plan above against the code currently in the plugin.

This is a code-state summary, not a manual QA sign-off.

### Messaging System: Already Coded

- dedicated `wp_fanfic_moderation_messages` table exists
- moderation message CRUD service exists in `includes/class-fanfic-moderation-messages.php`
- moderation `Messages` tab exists and is wired into the moderation screen
- moderation messages table UI exists in `includes/class-fanfic-messages-table.php`
- author message modal exists and is submitted through AJAX
- moderator actions `Unblock`, `Ignore`, and `Delete` are implemented
- moderator note on message handling is implemented and stored
- suspension reasons are stored on ban and shown in the frontend suspension notice
- chapter block actions now collect and store a real reason plus optional reason text
- canonical restriction helpers and author-facing restriction messages are implemented
- story view, chapter view, story form, chapter form, and suspension notice all expose `Message Moderation`
- moderation log schema supports `varchar(50)` `target_type`, so `chapter` targets are supported
- ignore and delete moderation actions are logged
- unblock actions flow through the real unblock hooks and log paths
- public reports remain separate from blocked-author messaging

### Messaging System: Still To Be Coded Or Cleaned Up

- the old public report queue UI mismatch described in the audit remains a separate backlog item
- dashboard blocked-state UX is still partly custom and tooltip-based instead of using the shared restriction-banner renderer everywhere
- the historical plan mentioned unread/read state; the shipped system uses `unread`, `ignored`, `resolved`, and `deleted`
- the historical plan described several separate moderator AJAX endpoints; the shipped code uses a consolidated `fanfic_mod_message_action` endpoint instead

### Adopted Diff System: Already Coded

- `fanfiction_story` and `fanfiction_chapter` now support revisions
- `wp_revisions_to_keep` is filtered so only blocked stories and blocked chapters retain revisions
- blocking a story writes `_fanfic_block_snapshot`
- unblocking a story clears `_fanfic_block_snapshot` and `_fanfic_re_review_requested`
- blocked story owners can open and use the real story edit form
- blocked story owners can open and use chapter edit forms for chapters inside that blocked story
- blocked save handlers keep the current status for non-moderators so blocked content cannot be made visible through normal owner edits
- re-review submission is implemented through `fanfic_submit_re_review`
- re-review requests create moderation messages with a generated change summary
- `_fanfic_re_review_requested` is set on submit and cleared on ignore, delete, and unblock
- moderators can open `Compare Changes` from the `Messages` tab
- compare rendering shows snapshot-versus-current rows and links to WordPress revision compare
- admin JS and CSS for the compare panel are implemented

### Adopted Diff System: Still To Be Coded

- the compare and re-review flow is currently story-centric; chapter-only blocked content does not yet have its own dedicated snapshot, submit, and compare path
- the chapter edit banner can now expose the blocked-edit workflow, but chapter-only re-review still needs its own canonical backend flow if chapter moderation should be fully independent from story moderation
- comparison rendering is row-based old-versus-new; more granular inline added/removed diff rendering is not implemented yet
- the snapshot field list may still need extension if moderators want additional story metadata reviewed in the compare panel
- end-to-end manual verification in WordPress admin and browser still needs to be done

### Historical Plan Items Now Superseded

The following older plan assumptions should no longer be treated as current:

- blocked authors being fully read-only is no longer the intended rule for blocked stories and blocked chapters owned by the author
- the working-copy / correction-submission / correction-items / separate `Corrections` tab architecture is no longer the recommended direction
- approval-time copy-back from shadow content is no longer part of the design

## Adopted Blocked-Content Diff System

The earlier working-copy proposal is no longer the recommended direction.

The adopted design is intentionally simpler:

- authors edit the real blocked story and blocked chapters
- the blocked state itself keeps content non-public
- a JSON snapshot captured at block time is the canonical "before" baseline
- normal WordPress revisions are retained only for blocked saves
- moderators review snapshot-versus-current values, with native revision compare as a supplemental text diff

### Core Design Decision

Do not create working copies, correction tables, or shadow-post workflows.

Use the real blocked post objects plus:

- `_fanfic_block_snapshot` on blocked stories
- blocked-only revision retention through `wp_revisions_to_keep`
- `_fanfic_re_review_requested` to prevent duplicate resubmission state

Reason:

- no new database tables are needed
- authors fix the exact object moderators will later unblock
- the moderation baseline is explicit and immutable at block time
- WordPress revisions still provide content diff support without accumulating for normal edits

### Current Baseline Model

When a story is blocked:

- the system writes the normal block metadata
- it also stores a JSON snapshot in `_fanfic_block_snapshot`

The snapshot is the authoritative "before changes" record for moderation review.

It captures at minimum:

- `post_title`
- `post_content`
- genre IDs and names
- status IDs and names
- fandom IDs and labels
- warning IDs and names
- language ID and label
- licence
- age rating
- cover image ID and URL
- author notes enabled, position, and content
- original-work flag
- `snapshot_time`

This is required because the first retained blocked edit revision is already the edited state, not the original blocked state.

### Revision Strategy

Revision support should exist on both:

- `fanfiction_story`
- `fanfiction_chapter`

But revisions should only be retained when moderation needs them.

Adopted rule:

- keep `0` revisions for normal story and chapter saves
- keep a positive number of revisions only when:
  - the story itself is blocked
  - the chapter itself is blocked
  - the chapter belongs to a blocked story

This keeps native revision history available for blocked correction work without adding normal-edit revision noise.

### Author Correction Flow

If a story is blocked:

- the story author may still open the story edit form
- the story author may still open chapter edit forms for chapters in that blocked story
- saves write directly to the real blocked story or chapter
- non-moderators cannot change publish visibility while blocked

If a chapter is blocked while the parent story is not:

- the content owner may still edit that blocked chapter
- the chapter remains hidden until moderator action

Blocked-owner UI should show:

- a warning banner that edits remain hidden while blocked
- a `Submit for Re-review` action
- an awaiting badge once submitted

### Re-review Submission Model

No separate correction submission system is required.

Re-review uses the existing moderation messages table.

Recommended behavior:

- author clicks `Submit for Re-review`
- system compares `_fanfic_block_snapshot` with the current story state
- system builds a human-readable change summary
- system creates a moderation message prefixed with `[Re-review Request]`
- system sets `_fanfic_re_review_requested = 1`

Duplicate prevention should remain simple:

- only one active unread re-review message per blocked story
- when the moderator ignores or deletes the message, clear `_fanfic_re_review_requested`
- when the story is unblocked, also clear `_fanfic_re_review_requested`

### Moderator Compare Model

The moderator compare screen should be based on snapshot-versus-current rows, not a custom correction-queue architecture.

Recommended UI:

- keep using the moderation `Messages` tab
- show `Compare Changes` on relevant re-review messages
- render a comparison table:
  - field name
  - blocked snapshot value
  - current value
- highlight changed rows
- gray unchanged rows
- show old and new cover thumbnails side by side
- include a link to WordPress revision comparison for detailed title/content review

The snapshot comparison is canonical for:

- taxonomy changes
- cover image changes
- warning changes
- language changes
- meta-field changes

The WordPress revision screen is supplemental for:

- title text differences
- long-form content differences

### Supported Change Surface

This simplified system is intended for correction of existing blocked content.

Tracked story-level changes:

- title
- main content
- introduction if later added to snapshot state
- genres
- statuses
- fandoms
- warnings
- language
- licence
- age rating
- cover image
- author notes
- original-work flag

Tracked chapter-level changes:

- chapter revisions are retained while blocked
- moderator review is still story-centric when the blocked story owns the review flow
- blocked chapter edits remain supported without introducing a separate chapter working-copy system

### Explicit Non-Goals

This design should not add:

- working-copy posts
- correction submission tables
- correction item mapping tables
- a separate `Corrections` moderation tab
- multi-branch review flows
- approval-time copy-back from shadow content

### Validation And Permissions

Author edit access:

- must own the blocked story or blocked chapter
- banned users remain unable to edit
- blocked edit context only relaxes the "blocked content cannot be edited" rule for the owner path

Save rules for non-moderators:

- keep current story status while story is blocked
- keep current chapter status while chapter or parent story is blocked
- allow edits to save, but do not allow blocked content to be made visible through the form

Moderator review rules:

- compare access requires moderator/admin capability
- unblock still acts on the real story or chapter
- unblocking deletes the stored snapshot and re-review marker

### Logging And Message Lifecycle

The moderation message remains the workflow object.

Expected lifecycle:

- unread: awaiting moderator review
- ignored: restriction remains; author may submit again later
- deleted: dismissed; author may submit again later
- resolved through unblock: restriction removed and cleanup runs

The moderation log should continue to record:

- ignore
- delete
- unblock

No extra correction-specific log family is required for this design.

### Testing Plan For The Adopted Diff System

Author tests:

- block a story and confirm `_fanfic_block_snapshot` is written
- open blocked story edit as the owner and confirm the full form still renders
- open blocked chapter edit as the owner and confirm the full form still renders
- save blocked edits and confirm content stays blocked
- submit re-review and confirm the awaiting badge appears

Moderator tests:

- open the moderation `Messages` tab
- open a re-review request
- click `Compare Changes`
- confirm old versus new values render correctly
- confirm revision link opens when a retained revision exists
- ignore or delete and confirm resubmission becomes possible
- unblock and confirm snapshot cleanup runs

Regression tests:

- unblocked stories and chapters should still keep no retained revisions
- public readers should still not see blocked content
- blocked edits should not create a separate shadow workflow
- public reports remain separate from moderation messages

### Recommended Next-Step Order

1. Keep the snapshot field list aligned with the real moderation review needs
2. Extend comparison rendering if additional story fields need review
3. Decide whether chapter-only compare needs its own snapshot layer later
4. Manually validate the end-to-end unblock and cleanup flow in WordPress admin
