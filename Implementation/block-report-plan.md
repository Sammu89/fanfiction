# Block / Report System Audit And Implementation Plan

Date: March 1, 2026
Status: Handoff plan for implementation
Scope: Blocked-author messaging implementation plus author self-correction workflow planning.

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

- author-side `Correct Problem` editing flow in this implementation pass
- report-system unification
- threaded back-and-forth conversations

This document also includes the planned architecture for the later `Correct Problem` workflow so the message system can be built without creating conflicts.

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

## Future Phase: Author Correction Submission Workflow

### Goal

Allow an author to correct a blocked story or blocked chapter without editing the live blocked object directly.

The author should be able to:

- click `Correct Problem`
- edit allowed content in a remediation path
- save work safely without changing the live blocked content
- submit the correction to moderation

Moderators should be able to:

- see a compact summary of what changed before opening anything
- review the actual diff using as much native WordPress revision UI as practical
- open the full working copy or full live content only when more context is needed
- approve and unblock, or reject

### Core Design Decision

Do not reopen direct editing on the live blocked story or chapter.

Use a separate correction-submission workflow with working copies.

Reason:

- it keeps the live blocked version stable while review is happening
- it gives a clean baseline for comparison
- it avoids mixing proposed corrections with the public blocked version
- it makes approval, rejection, rollback, and logging much cleaner

### Scope Rules

If a story is blocked:

- the `Correct Problem` action should be available from the blocked story context
- the correction workflow should permit edits to the story and its chapters
- all chapter edits in that flow belong to the same story correction submission

If only a chapter is blocked:

- the `Correct Problem` action should only be available for that chapter
- the correction workflow should only allow editing that blocked chapter
- no sibling chapters or story-level fields should become editable through that submission

### Required WordPress Foundation Changes

Before building the correction flow:

- enable `revisions` support on `fanfiction_story`
- enable `revisions` support on `fanfiction_chapter`

Reason:

- this is required to use native revision history and comparison screens
- without this, the moderation review cannot lean on WordPress core diff tools

Recommended update in post type registration:

- story supports: add `revisions`
- chapter supports: add `revisions`

### Data Model

#### 1. Correction submissions

Create a dedicated table:

- `wp_fanfic_correction_submissions`

Recommended fields:

- `id`
- `author_id`
- `target_type` with values `story`, `chapter`
- `target_id`
- `story_id`
- `scope_type` with values `story_bundle`, `chapter_only`
- `status` with values `draft`, `submitted`, `approved`, `rejected`, `cancelled`
- `submission_note`
- `block_reason_code`
- `block_reason_text`
- `baseline_created_at`
- `submitted_at`
- `reviewed_at`
- `moderator_id`
- `moderator_decision`
- `moderator_note`
- `created_at`
- `updated_at`

Behavior rules:

- only one active correction submission should exist per blocked target at a time
- `draft` and `submitted` count as active
- author can continue editing while in `draft`
- after `submitted`, author editing should be locked unless moderators send it back or the submission is cancelled

#### 2. Working copy mapping

Create a dedicated table:

- `wp_fanfic_correction_items`

Recommended fields:

- `id`
- `submission_id`
- `item_type` with values `story`, `chapter`
- `source_post_id`
- `working_post_id`
- `baseline_revision_id`
- `latest_revision_id`
- `change_manifest` as longtext storing JSON
- `created_at`
- `updated_at`

Purpose:

- maps each live object to its hidden working copy
- stores the canonical baseline revision for moderator comparison
- stores compact change summary data for queue and review UI

#### 3. Working copies

Use normal WordPress posts as working copies instead of inventing a second shadow storage format.

Recommended behavior:

- duplicate the live blocked story or chapter into a hidden working copy post
- for story-scope submissions, duplicate the story and all included chapters into hidden working copies
- keep the same post type as the source item so the editor and revision system remain native

Recommended post meta on each working copy:

- `_fanfic_is_correction_working_copy` = `1`
- `_fanfic_correction_submission_id`
- `_fanfic_correction_source_post_id`
- `_fanfic_correction_scope_type`
- `_fanfic_correction_locked` = `0` or `1`

Visibility rules:

- working copies must never appear publicly
- working copies must not appear in normal author dashboards as regular stories or chapters
- frontend routing must reject direct viewing of working copies

### Revision Baseline Strategy

This is the most important part if the goal is to use native WordPress diff UI cleanly.

#### Baseline creation

When the author first clicks `Correct Problem`:

- create the correction submission
- create the working copy or working copies
- immediately create a first revision on each working copy that represents the exact original blocked state

That first revision becomes the immutable baseline for moderation comparison.

Recommended stored references:

- `baseline_revision_id` on each correction item
- `baseline_created_at` on the submission

#### Ongoing saves

Each author save in correction mode should:

- save only to the working copy
- create normal WordPress revisions on the working copy
- update `latest_revision_id` on the correction item
- recompute the structured change manifest

#### Moderator compare target

Moderators should compare:

- baseline revision: original blocked version
- latest revision: current proposed correction

This keeps the comparison focused on what the author changed to address moderation.

### Change Manifest

Native WordPress revisions will not compactly summarize everything moderators need.

Add a computed structured manifest per correction item.

Purpose:

- drive compact queue badges
- power a clean review summary
- cover changes that native revision compare does not show well

#### Recommended manifest structure

Store JSON with sections such as:

- `core_fields`
- `meta_fields`
- `taxonomies`
- `media`
- `chapters`

Recommended detail level:

- `core_fields`
  - `post_title`
  - `post_content`
  - `post_excerpt`
  - `post_name` if relevant
- `meta_fields`
  - field key
  - label
  - old value summary
  - new value summary
- `taxonomies`
  - taxonomy
  - added terms
  - removed terms
- `media`
  - cover image changed yes or no
  - old attachment ID or URL summary
  - new attachment ID or URL summary
- `chapters`
  - for story-scope submissions, per chapter summary of changed fields

#### Recommended tracked fields

At minimum, include:

- story title
- story content or summary fields
- story cover image
- story author notes fields
- story comments enabled
- story taxonomy assignments such as genre and status
- chapter title
- chapter content
- chapter number
- chapter type
- chapter author notes fields
- chapter comments enabled

#### Manifest rendering examples

Compact queue badges:

- `Story: title, summary, cover, genres`
- `Chapters changed: 3`
- `Chapter 4: content, notes`

Detailed review summary:

- `Removed genre: Horror`
- `Added genre: Romance`
- `Cover image replaced`
- `Chapter 2 title updated`
- `Chapter 2 content edited`

### Moderator Screens

The moderator experience should use a two-level review model.

#### 1. Corrections queue

Add a new moderation tab:

- `Corrections`

Keep it separate from:

- `Queue` for public reports
- `Messages` for blocked-author messages
- `Log`

Recommended queue columns:

- status
- author
- target
- scope
- submitted
- compact changed-fields summary
- actions

Recommended row actions:

- `Review`
- `Open Live`
- `Open Working Copy`

The queue should show compact change badges first so moderators can scan without opening each item.

#### 2. Correction review screen

Recommended layout:

- header summary
- changed-fields summary panel
- native revision diff panel
- chapter changes panel for story-scope submissions
- moderator actions panel

Header summary should show:

- author
- target
- scope
- current block reason
- submission note
- submit date

Changed-fields summary panel should show:

- concise structured changes from the manifest
- taxonomy additions and removals
- image changed state
- chapter count changed state if that is ever supported later

Native revision diff panel should show:

- WordPress revision comparison between baseline revision and latest revision
- used primarily for title and content review

Chapter changes panel should show:

- one row per changed chapter
- chapter title or number
- compact changed-fields summary
- `View Diff`
- `Open Full Chapter`

Moderator actions panel should include:

- `Approve And Unblock`
- `Reject`
- optional moderator note
- link to full live story or chapter
- link to full working copy story or chapter

### Maximum Native WordPress Use

The cleanest possible approach while staying close to core WordPress is:

- use revisions on the working copy posts
- use WordPress revision compare for text fields
- use normal post editing screens or current frontend forms against the working copy
- store only a thin custom manifest for what WordPress does not summarize well

This is the recommended balance.

Do not try to force the entire moderation review into the raw WordPress revision UI because:

- taxonomy changes are not clearly summarized there
- featured image changes are not clearly summarized there
- grouped story-plus-chapter review is not something core provides out of the box

### Approval And Apply Strategy

When moderators approve a correction:

- copy approved working-copy data into the live blocked target
- for story-scope submissions, apply approved chapter working copies as well
- create a normal live-post revision checkpoint when possible
- unblock the story or chapter
- store moderator note if provided
- mark submission `approved`
- write moderation log entries

When moderators reject a correction:

- keep the live blocked target unchanged
- mark submission `rejected`
- store moderator note
- write moderation log entries

### Supported Changes In First Version

To keep the workflow manageable, the first implementation should support editing existing objects only.

Allowed in version 1:

- edit story fields
- edit story taxonomies
- edit story cover image
- edit existing chapter fields

Not recommended for version 1:

- create new chapters inside remediation
- delete chapters inside remediation
- reorder chapters inside remediation
- split one blocked submission into multiple review branches

Reason:

- those structural edits complicate scope, diff rendering, and approval logic significantly
- existing-object corrections already solve the primary moderation use case

### Routing And UX Rules

Author-facing `Correct Problem` behavior:

- if a story is blocked, the button should lead into the story correction workspace
- if a chapter is blocked, the button should lead into the chapter correction workspace
- if only a chapter is blocked, no story-level correction route should be opened

Workspace UX:

- show a visible `Correction Draft` or `Correction Submission` banner
- explain that edits are not yet live
- provide `Save Draft` and `Submit For Review`
- prevent normal publish actions

### Validation And Permissions

Author correction access rules:

- user must own the blocked target
- target must still be blocked
- only one active submission per target

Moderator review rules:

- moderator or admin capability required
- approval must validate that the submission still maps to the same live target
- approval should re-check block state before unblocking

### Logging Requirements For Correction Workflow

Recommended new action types:

- `correction_submission_created`
- `correction_submission_submitted`
- `correction_submission_cancelled`
- `correction_submission_approved`
- `correction_submission_rejected`
- `correction_unblock_story`
- `correction_unblock_chapter`

Optional but useful:

- include submission ID in log details
- include working post IDs in internal metadata

### Testing Plan For Correction Workflow

Author tests:

- blocked story can enter correction mode
- story correction mode can edit story and included chapters
- blocked chapter can enter chapter-only correction mode
- normal live blocked content is unchanged while edits are saved in correction mode
- draft saves create revisions on working copies
- submit locks the submission for review

Moderator tests:

- `Corrections` tab shows compact change summaries
- review screen shows baseline-to-latest diff
- moderators can open full live and full working content
- approval applies corrected values to live content and unblocks correctly
- rejection leaves live content unchanged

Regression tests:

- blocked content remains inaccessible to public readers during correction review
- regular story and chapter editing paths stay unchanged for non-blocked content
- messages and public reports remain separate systems

### Recommended Implementation Order For Future Correction Phase

1. Enable revisions on story and chapter post types
2. Add correction submission and correction item tables
3. Add working-copy creation and mapping
4. Add author correction routes and correction-mode save handling
5. Add baseline revision creation and latest revision tracking
6. Add structured change manifest generation
7. Add moderation `Corrections` tab and queue
8. Add correction review screen with native diff integration
9. Add approval, apply, reject, unblock, and logging flows
