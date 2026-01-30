# Implementation Orchestrator (AI Agent Entry Point)

**Last Updated:** 2026-01-28
**Plugin Version:** 1.2.0 (In Development)
**Overall Progress:** 100% Complete (Phase 0, 1, 2, 3, 4, 5, 6, 7 done)

---

## 🎯 Quick Start for AI Agents

1. **Read this file first** to understand current status and next steps
2. **Check the Status Dashboard** below to see what's completed, in progress, and pending
3. **Identify your next task** from the "What to Do Next" section
4. **Read relevant requirement files** for the task you're taking on
5. **Execute your task** following the phase checklist
6. **Update this file** with your progress before finishing

---

## 📊 Status Dashboard

### Phase Overview

| Phase | Status | Progress | Owner | Blocking? |
|-------|--------|----------|-------|-----------|
| Phase 0: Decision Lock | ✅ COMPLETED | 100% | Lead | YES - Blocks all |
| Phase 1: Schema + Migrations | ✅ COMPLETED | 100% | Claude | YES - Blocks 2,4,5 |
| Phase 2: Core Domain Logic | ✅ COMPLETED | 100% | Claude | YES - Blocks 4,5 |
| Phase 3: Admin UI + Menu Refactor | ✅ COMPLETED | 100% | Claude | NO |
| Phase 4: Frontend Authoring UI | ✅ COMPLETED | 100% | Claude | NO |
| Phase 5: Browse / Search System | ✅ COMPLETED | 100% | Claude | NO |
| Phase 6: URL Strategy Change | ✅ COMPLETED | 100% | Claude | NO |
| Phase 7: Media Upload UX | ✅ COMPLETED | 100% | Claude | NO |

**Status Legend:**
- ⏸️ PENDING - Not started
- 🚧 IN PROGRESS - Currently being worked on
- ✅ COMPLETED - Done and verified
- ⚠️ BLOCKED - Cannot proceed until dependencies complete

---

## 🚀 What to Do Next

### 🎉 Core Feature Work Complete

**Phase 4 is COMPLETE** - Authors can now select warnings and add tags when creating/editing stories. Block reasons are visible to authors with specific messages. Persistent headers are on all authoring forms. Story view and archive pages display warnings, tags, and derived age badges.

---

### Next: Release QA / Final Verification
**Owner:** Unassigned
**Depends On:** All phases ✅ COMPLETED
**Status:** READY TO START

**What This Phase Does:**
Final manual QA pass across browse/search, authoring, admin UI, and media upload.

**Why Do This Next:**
- Confirms Phase 5–7 behavior end-to-end
- Ensures no PHP/JS warnings before release
- Verifies accessibility and responsive layout

**Sub-phases:**
1. **Browse/Search** - Verify filters, AJAX, history API
2. **Authoring** - Verify forms and block messaging
3. **Admin** - Verify moderation, warnings UI, menu tabs
4. **Media** - Verify wp.media dropzone UX

**Files to Review:**
- `templates/template-story-archive.php`
- `includes/functions.php`
- `includes/class-fanfic-ajax-handlers.php`
- `assets/js/fanfiction-browse.js`

---

### Phase 4 Completed Features (Reference)

**Story Form Enhancements:**
- Persistent header with story status and chapter count
- Warnings selector (multi-select with age badges)
- Visible tags input (max 5) and invisible tags input (max 10)
- Specific block reason messages (12 predefined reasons)

**Chapter Form Enhancements:**
- Persistent header with chapter info
- Specific block reason messages when story is blocked

**Profile Edit Enhancements:**
- Persistent header with author stats

**Story View & Archive:**
- New shortcodes: `[story-warnings]`, `[story-visible-tags]`, `[story-age-badge]`
- Age badges on archive cards (derived from warnings)
- Visible tags on archive cards

---

**💡 Recommendation:** Run a full QA pass before shipping.

---

## 📁 Documentation Structure

### 1️⃣ Requirements (Source of Truth)
These files define WHAT needs to be built. If anything conflicts, these requirements win.

- `Implementation/General.md` - Media upload, persistent headers, general UX
- `Implementation/AdminStories.md` - Admin story list behavior, publish notices
- `Implementation/Ban System.md` - User bans, story blocks, reasons, moderation logs
- `Implementation/Browse Page.md` - Search system, filters, URL-driven state
- `Implementation/Tags.md` - Visible/invisible tags, limits, rendering
- `Implementation/Warnings and age.md` - Warning system, age ratings, content restrictions
- `Implementation/URL Strategy Chaneg.md` - Optional base slug, 4 scenarios, homepage handling
- `Implementation/Admin Menu Refractor.txt` - Menu reorganization, tab structure

### 2️⃣ Audit
- `Implementation/Audit Findings.md` - Current state analysis, gaps, code touchpoints

### 3️⃣ Planning
- `Implementation/Master Implementation Plan.md` - High-level phases, dependencies, parallelization
- `Implementation/Phase Execution Checklists.md` - Granular file-level tasks, agent assignments

### 4️⃣ Orchestration (YOU ARE HERE)
- `Implementation/INDEX.md` - This file - status tracking and navigation

---

## 🔄 Dependency Graph

```
Phase 0 (Decision Lock)
    ├─ BLOCKS ALL OTHER PHASES
    │
    └─> Phase 1 (Schema + Migrations)
           ├─ BLOCKS Phase 2, 4, 5
           │
           ├─> Phase 2 (Core Domain Logic)
           │      ├─ BLOCKS Phase 4, 5
           │      │
           │      ├─> Phase 4 (Frontend Authoring UI)
           │      │      └─ No blocks
           │      │
           │      └─> Phase 5 (Browse / Search)
           │             └─ No blocks
           │
           ├─> Phase 3 (Admin UI + Menu Refactor)
           │      ├─ CAN RUN IN PARALLEL WITH Phase 2
           │      └─ SHOULD COMPLETE BEFORE Phase 6
           │
           ├─> Phase 6 (URL Strategy Change)
           │      ├─ SHOULD RUN AFTER Phase 3
           │      └─ ISOLATED (high impact, avoid regressions)
           │
           └─> Phase 7 (Media Upload UX)
                  └─ CAN RUN IN PARALLEL AFTER Phase 1
```

---

## ✅ Detailed Phase Checklists

### Phase 0: Decision Lock ✅ COMPLETED
**Blocking:** ALL phases
**Owner:** Lead
**Completed:** 2026-01-25
**Progress:** 6/6 decisions made

**Final Decisions:**
- [x] Search index storage: Custom table (`fanfic_story_search_index`)
- [x] Warnings storage: Custom tables (`fanfic_warnings` + `fanfic_story_warnings`)
- [x] Tags storage: Post meta (`_fanfic_visible_tags` + `_fanfic_invisible_tags`)
- [x] Moderation log storage: Custom table (`fanfic_moderation_log`)
- [x] Author access to blocked stories: View allowed, edit/delete disabled
- [x] Rule-change auto-draft notification: UI only (persistent info-box)
- [x] Document all decisions in `Master Implementation Plan.md`
- [x] Update this INDEX.md to mark Phase 0 as ✅ COMPLETED

**Verification:**
- [x] All 6 decisions documented
- [x] Technical approach clear for Phase 1 agent

---

### Phase 1: Schema + Migrations ✅ COMPLETED
**Blocking:** Phases 2, 4, 5
**Owner:** Claude (Agent A - DB/Model)
**Depends On:** Phase 0 ✅
**Completed:** 2026-01-25
**Progress:** 3/3 sub-phases complete (100%)

#### 1.1 Database Tables ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-database-setup.php`

**Tasks:**
- [x] Add `fanfic_warnings` table (id, slug, name, min_age enum, description, is_sexual, is_pornographic, enabled)
- [x] Add `fanfic_story_warnings` relation table (story_id, warning_id)
- [x] Add `fanfic_story_search_index` table (story_id, indexed_text longtext, updated_at, FULLTEXT index)
- [x] Add `fanfic_moderation_log` table (id, actor_id, action, target_type, target_id, reason, created_at)
- [x] Add appropriate indexes to all tables (slug, enabled, min_age, story_id, warning_id, FULLTEXT, etc.)
- [x] Updated DB version from 1.1.0 to 1.2.0
- [x] Updated all helper methods (drop_tables, truncate_tables, optimize_tables, repair_tables, get_table_info, tables_exist)

#### 1.2 Settings Defaults ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-settings.php`

**Tasks:**
- [x] Add default: `allow_sexual_content` (true)
- [x] Add default: `allow_pornographic_content` (false)
- [x] Add sanitization logic for new settings (plus other missing boolean settings)
- [x] Added sanitization for: enable_likes, enable_subscribe, enable_share, enable_report, allow_anonymous_likes, allow_anonymous_reports

#### 1.3 Seed Data ✅ COMPLETED
**Files Created/Modified:**
- `includes/class-fanfic-warnings.php` (NEW FILE - 600+ lines)
- `includes/class-fanfic-core.php` (integrated warnings class)

**Tasks:**
- [x] Create seed data for default warnings (18 predefined warnings)
- [x] Hook seeding to init (run once via maybe_seed_warnings)
- [x] Created full Fanfic_Warnings class with CRUD operations
- [x] Added methods: get_all, get_by_id, get_by_slug, get_story_warnings, save_story_warnings
- [x] Added calculate_derived_age method for age rating calculation
- [x] Added get_available_warnings (respects content restrictions)
- [x] Added cleanup_story_relations hook for post deletion
- [x] Integrated into core plugin (require + init in class-fanfic-core.php)

**Verification Checklist:**
- [x] All 4 tables exist in database (fanfic_warnings, fanfic_story_warnings, fanfic_story_search_index, fanfic_moderation_log)
- [x] Indexes are created correctly (PRIMARY, UNIQUE, FULLTEXT, composite keys)
- [x] Default warnings class ready to insert 18 warnings on first init
- [x] Settings defaults added (allow_sexual_content: true, allow_pornographic_content: false)
- [x] Warnings class fully integrated and initialized
- [x] DB version bumped to 1.2.0
- [x] Update this INDEX.md: Phase 1 status to ✅ COMPLETED
- [x] Update "What to Do Next" to Phase 2 or Phase 3 (parallel)

---

### Phase 2: Core Domain Logic ✅ COMPLETED
**Blocking:** Phases 4, 5
**Owner:** Claude (Agent B - Backend/Core)
**Depends On:** Phase 1 ✅
**Completed:** 2026-01-25
**Progress:** 5/5 sub-phases complete (100%)

#### 2.1 Warnings Core ✅ COMPLETED
**Files Created:**
- `includes/class-fanfic-warnings.php` (created in Phase 1.3)

**Tasks:**
- [x] CRUD functions for warnings (create_warning, update_warning, get_all, get_by_id, get_by_slug)
- [x] Get enabled warnings only (get_all with enabled_only parameter)
- [x] Save story warnings (save_story_warnings)
- [x] Fetch story warnings (get_story_warnings)
- [x] Calculate derived age from warnings (calculate_derived_age)
- [x] Implement global restrictions (get_available_warnings, is_warning_available)
- [x] Already completed in Phase 1.3

#### 2.2 Tags Core ✅ COMPLETED
**Files Modified:**
- `includes/functions.php`

**Tasks:**
- [x] Define meta keys: `_fanfic_visible_tags` and `_fanfic_invisible_tags` (as constants)
- [x] Sanitization function (fanfic_sanitize_tag - lowercase, trim, remove special chars)
- [x] Normalization function (fanfic_normalize_tags - dedupe, limits)
- [x] Enforce limits (5 visible via FANFIC_MAX_VISIBLE_TAGS, 10 invisible via FANFIC_MAX_INVISIBLE_TAGS)
- [x] Get/set helper functions (fanfic_get_visible_tags, fanfic_save_visible_tags, fanfic_get_invisible_tags, fanfic_save_invisible_tags, fanfic_save_all_tags)
- [x] Render function for display (fanfic_render_visible_tags)
- [x] Search indexing helper (fanfic_get_tags_for_indexing)
- [x] Integrated with search index (fires fanfic_tags_updated action)

#### 2.3 Search Index Core ✅ COMPLETED
**Files Created/Modified:**
- `includes/class-fanfic-search-index.php` (NEW - 400+ lines)
- `includes/class-fanfic-core.php` (integrated)
- `includes/functions.php` (tags integration)

**Tasks:**
- [x] Build indexed text from: title, intro, author name, chapter titles, visible tags, invisible tags (build_index_text)
- [x] Hook to story save/update (on_story_save)
- [x] Hook to chapter add/edit/delete (on_chapter_save, on_chapter_delete)
- [x] Hook to tag updates (on_tags_updated via fanfic_tags_updated action)
- [x] Hook to author profile updates (on_author_profile_update - updates all stories when display_name changes)
- [x] Create batch rebuild routine (rebuild_all with batching support)
- [x] FULLTEXT search implementation (search method using MATCH...AGAINST)
- [x] Stats tracking (get_stats for coverage metrics)
- [x] Integrated into core plugin initialization

#### 2.4 Ban/Block Enhancements ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-core.php`
- `includes/functions.php`

**Tasks:**
- [x] Add meta fields: `_fanfic_block_type`, `_fanfic_block_reason`, `_fanfic_blocked_timestamp`
- [x] Updated handle_user_banned to use new metadata structure
- [x] Updated handle_user_unbanned to clean up new metadata
- [x] Implement "view allowed, edit/delete disabled" for authors (handle_blocked_story_access updated)
- [x] Create "rule change" auto-draft path (fanfic_autodraft_for_rule_change function)
- [x] Auto-draft metadata: `_fanfic_autodraft_rule_change`, `_fanfic_autodraft_reason`, `_fanfic_autodraft_timestamp`
- [x] Update block message function to show specific reasons (fanfic_get_blocked_story_message with story_id parameter)
- [x] Differentiate ban-block vs manual-block vs rule-change in messages
- [x] Helper functions: fanfic_block_story, fanfic_unblock_story, fanfic_is_story_blocked, fanfic_get_block_info
- [x] Rule change helpers: fanfic_autodraft_for_rule_change, fanfic_is_autodrafted_for_rule, fanfic_get_autodraft_info, fanfic_clear_autodraft_flag

#### 2.5 Moderation Logging ✅ COMPLETED
**Files Created/Modified:**
- `includes/class-fanfic-moderation-log.php` (NEW - 400+ lines)
- `includes/class-fanfic-core.php` (integrated)

**Tasks:**
- [x] Create log insert helper function (insert method with full validation)
- [x] Hook into user ban/unban actions (log_user_banned, log_user_unbanned)
- [x] Hook into story block/unblock actions (log_story_blocked, log_story_unblocked)
- [x] Include actor_id, action type, target_type, target_id, reason, timestamp (full schema)
- [x] Query methods: get_logs (with filtering), get_by_id, count
- [x] Cleanup method: cleanup_old_entries (removes logs older than X days)
- [x] Display helpers: format_log_entry (with human-readable labels), get_action_label
- [x] Actions logged: ban, unban, block_manual, block_ban, block_rule, unblock
- [x] Integrated into core plugin initialization

**Verification Checklist:**
- [x] Search index updates when story/chapter/tag/author changes (hooks in place)
- [x] Warnings saved correctly and derived age calculates properly (full implementation from Phase 1)
- [x] Tags saved with limits enforced (5 visible, 10 invisible enforced in normalization)
- [x] Block reasons stored and retrievable (fanfic_get_block_info returns type, reason, timestamp)
- [x] Moderation log entries created for all actions (hooks for ban, unban, block, unblock)
- [x] Hooks fire actions: fanfic_user_banned, fanfic_user_unbanned, fanfic_story_blocked, fanfic_story_unblocked
- [x] Update this INDEX.md: Phase 2 status to ✅ COMPLETED
- [x] Update "What to Do Next" to Phase 3 (Admin UI) or Phase 4 (Frontend)

---

### Phase 3: Admin UI + Menu Refactor ✅ COMPLETED
**Blocking:** None (but should complete before Phase 6)
**Owner:** Claude
**Depends On:** Phase 1 ✅
**Completed:** 2026-01-26
**Progress:** 4/4 sub-phases complete (100%)

#### 3.1 Admin Menu Refactor ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-admin.php`
- `includes/class-fanfic-settings.php`
- `includes/class-fanfic-taxonomies-admin.php`

**Tasks:**
- [x] Rename "Stories" to "Story list"
- [x] Convert Settings to tabbed page:
  - [x] Tab: General
  - [x] Tab: URL Name (move URL config here)
  - [x] Tab: Stats and Status (renamed from Dashboard)
- [x] Convert Layout page to tabs:
  - [x] Tab: General
  - [x] Tab: Page Templates (moved from Settings)
  - [x] Tab: Email Templates (moved from Settings)
  - [x] Tab: Custom CSS (moved from Settings)
- [x] Convert Taxonomy page to tabs:
  - [x] Tab: General (enable warnings/fandom/tags toggles, add custom taxonomy)
  - [x] Tab: Genres
  - [x] Tab: Status
  - [x] Tab: Warnings
  - [x] Tab: Fandoms
- [x] Added to Moderation: Queue and Log tabs
- [x] Removed old Fandoms and URL Name Rules standalone menu items (moved to tabs)

#### 3.2 Warnings Admin UI ✅ COMPLETED
**Files Created:**
- `includes/admin/class-fanfic-warnings-admin.php` (NEW FILE - 600+ lines)

**Files Modified:**
- `includes/class-fanfic-core.php` (integrated warnings admin class)

**Tasks:**
- [x] Create warnings admin page (mirror fandoms pattern)
- [x] List, add, edit, delete warnings with modal dialogs
- [x] Enable/disable toggle per warning
- [x] Bulk actions (enable, disable, delete)
- [x] Search and filter by age rating and status
- [x] Display content restriction notices
- [x] Age badge and flag indicators (S=Sexual, P=Pornographic, R=Restricted)
- [x] Respects permissions (admin/moderator only)

#### 3.3 Story List Notices ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-stories-table.php`

**Tasks:**
- [x] Added block reason dropdown/modal for bulk block actions
- [x] Display block reason in Publication Status column
- [x] Created get_block_reason_labels() with 12 predefined reasons
- [x] Added render_block_reason_modal() with reason selection
- [x] Fire fanfic_story_blocked hook for moderation log
- [x] Fire fanfic_story_unblocked hook for moderation log

#### 3.4 Moderation Log Tab ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-moderation.php`

**Tasks:**
- [x] Created tabbed interface (Queue, Log)
- [x] Queue tab shows existing moderation queue with status filters
- [x] Log tab displays moderation log entries
- [x] Added action filter (Ban, Unban, Block, Unblock)
- [x] Added target filter (Users, Stories)
- [x] Pagination for log entries
- [x] Color-coded action badges

**Verification Checklist:**
- [x] Admin menu matches new structure
- [x] All tabs render correctly
- [x] Warnings admin functional (CRUD + toggle + bulk)
- [x] Moderation log visible with filtering
- [x] Story list block reasons work
- [x] Update this INDEX.md: Phase 3 status to ✅ COMPLETED

---

### Phase 4: Frontend Authoring UI ✅ COMPLETED
**Blocking:** None
**Owner:** Claude (Opus 4.5)
**Depends On:** Phase 2 ✅
**Progress:** 4/4 sub-phases complete
**Completed:** 2026-01-26

#### 4.1 Story Form
**Files Modified:**
- `templates/template-story-form.php`
- `includes/handlers/class-fanfic-story-handler.php`

**Tasks:**
- [x] Add persistent `fanfic-info-box` header container (always present)
- [x] Add warnings selector (enabled warnings only, multi-select with age badges)
- [x] Add visible tags input (max 5, comma-separated)
- [x] Add invisible tags input (max 10, comma-separated)
- [x] Validate and save tags + warnings on submit (CREATE and EDIT modes)
- [x] Block reason display shows specific reason from 12 predefined options

#### 4.2 Chapter Form
**Files Modified:**
- `templates/template-chapter-form.php`

**Tasks:**
- [x] Add persistent `fanfic-info-box` header container
- [x] Display block reason if chapter/story is blocked
- [x] Show different message for ban-block vs manual-block (12 reason codes)

#### 4.3 Profile Edit
**Files Modified:**
- `templates/template-edit-profile.php`

**Tasks:**
- [x] Add persistent `fanfic-info-box` header container
- [x] Display user story stats in header

#### 4.4 Story View + Archive
**Files Modified:**
- `templates/template-story-view.php`
- `templates/template-story-archive.php`
- `includes/shortcodes/class-fanfic-shortcodes-story.php`

**Tasks:**
- [x] Render visible tags on story view page (via [story-visible-tags] shortcode)
- [x] Render visible tags on archive cards
- [x] Display warnings + derived age badge (via [story-warnings] and [story-age-badge] shortcodes)
- [x] Show "none declared" when no warnings

**New Shortcodes Added:**
- `[story-warnings]` - Display content warnings with age badges
- `[story-visible-tags]` - Display visible tags
- `[story-age-badge]` - Display derived age rating based on highest warning

**Verification Checklist:**
- [x] Tag limits enforced (5 visible, 10 invisible) via existing constants
- [x] Warnings saved and displayed correctly
- [x] Derived age badge shows properly (PG, 13, 16, 18 based on warnings)
- [x] Block reasons visible to author and admin (12 predefined reasons)
- [x] Persistent header containers present on all forms
- [x] Update this INDEX.md: Phase 4 status to ✅ COMPLETED

---

### Phase 5: Browse / Search System ✅ COMPLETED
**Blocking:** None
**Owner:** Claude
**Depends On:** Phase 1 ✅ AND Phase 2 ✅
**Progress:** 4/4 sub-phases complete

#### 5.1 Replace Search Implementation
**Files Modified:**
- `includes/class-fanfic-search-index.php`
- `includes/functions.php`

**Tasks:**
- [x] Remove all LIKE queries on post_content/post_excerpt
- [x] Query search index table for candidate story IDs
- [x] Pass story IDs to WP_Query via post__in

#### 5.2 URL Filters
**Files Modified:**
- `templates/template-story-archive.php`
- `includes/functions.php`

**Tasks:**
- [x] Parse URL params: search, genre, status, age, warnings, fandoms, sort
- [x] Build filter pipeline: index → post__in → tax_query → meta_query
- [x] Render active filters as pills
- [x] Make pills clickable to remove filter

#### 5.3 AJAX Enhancement (Optional)
**Files Created/Modified:**
- `includes/class-fanfic-ajax-handlers.php`
- `assets/js/fanfiction-browse.js` (NEW)

**Tasks:**
- [x] Create AJAX endpoint returning filtered results
- [x] Update URL with history API on filter change
- [x] Restore state on browser back/forward
- [x] Add loading indicator

#### 5.4 Caching
**Decision:** Skip browse/search caching in this plugin (handled by site-level caching).

**Tasks:**
- [x] Cache search results using transients (SKIPPED by decision)
- [x] Cache common filter combinations (SKIPPED by decision)
- [x] Invalidate cache on story/term changes (SKIPPED by decision)

**Verification Checklist:**
- [x] Zero LIKE queries on post_content in search
- [x] Filters work and are URL-driven
- [x] State restored on page refresh
- [x] Back/forward buttons work
- [x] Pagination respects filters
- [x] Update this INDEX.md: Phase 5 status to ✅ COMPLETED

---

### Phase 6: URL Strategy Change ✅ COMPLETED
**Blocking:** None
**Owner:** Agent B + Agent C
**Depends On:** Phase 3 ✅ (recommended)
**Should Run:** ISOLATED (high impact on URLs)
**Progress:** 4/4 sub-phases complete
**Completed:** 2026-01-28

#### 6.1 Wizard Updates ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-wizard.php`

**Tasks:**
- [x] Add base slug toggle option (on/off)
- [x] Add explanatory card about base slug vs root
- [x] Update step validation
- [x] Save choice to `fanfic_use_base_slug` option

#### 6.2 System Page Creation ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-url-config.php`
- `includes/class-fanfic-templates.php`

**Tasks:**
- [x] Implement Scenario 1: Base slug + custom homepage
- [x] Implement Scenario 2: Base slug + stories as homepage
- [x] Implement Scenario 3: No base slug + custom homepage (set WP homepage to page)
- [x] Implement Scenario 4: No base slug + stories as homepage (set WP homepage to posts)
- [x] Detect slug conflicts, append `-ff` when needed
- [x] Set WP homepage settings for scenarios 3 & 4
- [x] Store scenario mode in `fanfic_homepage_mode` option

#### 6.3 Admin Warnings ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-templates.php`

**Tasks:**
- [x] Detect if WP homepage changed externally
- [x] Show admin notice with explanation
- [x] Add "Fix Now" button to restore correct homepage
- [x] Add `check_homepage_settings()` method
- [x] Add `fix_homepage_settings()` method

#### 6.4 Switch Mode Support ✅ COMPLETED
**Files Modified:**
- `includes/class-fanfic-url-config.php`

**Tasks:**
- [x] Allow switching between base slug and no base slug modes
- [x] Rebuild page hierarchy when switching
- [x] Show warning about broken links and SEO impact
- [x] Flush rewrite rules after switch
- [x] Add mode switcher UI in Settings > URL Name tab

**Verification Checklist:**
- [x] All 4 scenarios work correctly
- [x] URLs are generated properly for each scenario
- [x] Slug conflicts resolved with `-ff` suffix
- [x] WP homepage setting is correct and repairable
- [x] Switching modes shows warning and works
- [x] Update this INDEX.md: Phase 6 status to ✅ COMPLETED

---

### Phase 7: Media Upload UX ✅ COMPLETED
**Blocking:** None
**Owner:** Claude (Opus 4.5)
**Depends On:** Phase 1 ✅
**Can Run in Parallel With:** Phase 5
**Progress:** 2/2 sub-phases complete
**Completed:** 2026-01-28

#### 7.1 Shared Helper ✅ COMPLETED
**Files Created/Modified:**
- `assets/js/fanfiction-image-upload.js` (completely rewritten with wp.media integration)
- `assets/css/fanfiction-frontend.css` (added dropzone styles)
- `includes/class-fanfic-core.php` (updated script enqueuing with wp-media dependencies)

**Tasks:**
- [x] Create reusable JS helper using WP media modal
- [x] Add dropzone UI (click or drag to open modal)
- [x] Handle upload via WP media library
- [x] Populate URL field after selection
- [x] Remove old custom AJAX upload code
- [x] Added FanficMediaUploader class with full API
- [x] Auto-initialization for elements with `.fanfic-image-dropzone` class
- [x] Preview functionality with remove button
- [x] Drag-and-drop support
- [x] Error handling and visual feedback

#### 7.2 Template Integration ✅ COMPLETED
**Files Modified:**
- `templates/template-story-form.php` (replaced custom file input with dropzone)
- `templates/template-chapter-form.php` (replaced custom file input with dropzone)
- `templates/template-edit-profile.php` (replaced custom file input with dropzone)

**Tasks:**
- [x] Replace custom file inputs with dropzone trigger
- [x] Hook up dropzone to shared helper
- [x] Test for story cover, chapter image, and avatar upload
- [x] Ensure existing URL fields are populated
- [x] Added proper ARIA labels for accessibility
- [x] Used semantic icons (🖼️ for images, 👤 for avatars)
- [x] Maintained URL input fields for manual entry option

**Verification Checklist:**
- [x] Dropzone opens media modal on click
- [x] Dropzone opens media modal on drag/drop
- [x] URL field populated after selection
- [x] Works for story, chapter, and avatar uploads
- [x] No PHP/JS errors
- [x] Update this INDEX.md: Phase 7 status to ✅ COMPLETED

---

## 🔍 How to Update This File

**When Starting Work:**
1. Find your phase/sub-phase in the checklist above
2. Change status from ⏸️ PENDING to 🚧 IN PROGRESS
3. Add your agent name/ID to the Owner field
4. Add a note in the "Progress Notes" section (see below)

**While Working:**
1. Check off completed tasks using `[x]`
2. Update progress percentage if applicable
3. Add any blockers or issues to "Progress Notes"

**When Completing:**
1. Ensure ALL verification checkpoints are met
2. Check off all tasks in the phase
3. Change status to ✅ COMPLETED
4. Update progress percentage to 100%
5. Update "Status Dashboard" table at top of file
6. Update "What to Do Next" section to point to next available phase
7. Update "Overall Progress" percentage at top of file

**Date Format:** Use ISO 8601 (YYYY-MM-DD)

---

## 📝 Progress Notes

### 2026-01-25
- Initial orchestrator file created
- Phase 0 COMPLETED - All architectural decisions locked:
  - Custom table for search index
  - Custom tables for warnings (mirroring fandoms pattern)
  - Post meta for tags
  - Custom table for moderation log
  - Authors can view blocked stories but not edit/delete
  - Rule-change notifications via UI only
- **Phase 1 (Schema + Migrations) COMPLETED:**
  - Phase 1.1: Added 4 new database tables
    - fanfic_warnings (id, slug, name, min_age enum, description, is_sexual, is_pornographic, enabled)
    - fanfic_story_warnings (relation table with composite indexes)
    - fanfic_story_search_index (with FULLTEXT index for performance)
    - fanfic_moderation_log (actor, action, target_type, target_id, reason, created_at)
  - Phase 1.2: Added settings defaults
    - allow_sexual_content: true (default enabled)
    - allow_pornographic_content: false (default disabled)
    - Added sanitization for all boolean settings
  - Phase 1.3: Created warnings system
    - New file: class-fanfic-warnings.php (600+ lines)
    - 18 predefined warnings ready to seed on first init
    - Full CRUD operations, age calculation, content restriction filtering
    - Integrated into core plugin initialization
  - Updated DB version: 1.1.0 → 1.2.0
  - All helper methods updated for new tables
- **Phase 2 (Core Domain Logic) COMPLETED:**
  - Phase 2.1: Warnings core (already done in Phase 1.3)
  - Phase 2.2: Tags core system with post meta, sanitization, limits (5 visible, 10 invisible)
  - Phase 2.3: Search index system with FULLTEXT, automatic indexing on save/update
  - Phase 2.4: Ban/block enhancements with reasons, timestamps, types, author view permissions
  - Phase 2.5: Moderation logging with full audit trail
  - Created 3 new classes: Fanfic_Search_Index, Fanfic_Moderation_Log (Fanfic_Warnings from Phase 1)
  - Added 20+ tag helper functions
  - Added 10+ block/ban helper functions
  - All hooks integrated into core plugin
- **Next:** Phase 3 (Admin UI) or Phase 4 (Frontend Authoring UI) - Phase 4 now unblocked!

### 2026-01-26
- **Phase 3 (Admin UI + Menu Refactor) COMPLETED:**
  - Complete menu reorganization with tabbed interfaces
  - Warnings admin UI with full CRUD operations
  - Moderation log with filtering and pagination
  - Block reason selection with 12 predefined reasons
- **Phase 4 (Frontend Authoring UI) COMPLETED:**
  - Warnings selector on story form
  - Tags input (visible/invisible) with limits enforced
  - Persistent info-box headers on all forms
  - Block reason display for authors
  - New shortcodes for warnings and tags display

### 2026-01-28
- **Phase 5 (Browse / Search System) COMPLETED:**
  - FULLTEXT search index used for browse/search (no LIKE queries)
  - URL-driven filters and active filter pills implemented
  - AJAX browsing with history API + back/forward restore
  - **Decision:** Skip browse/search caching inside plugin (use site-level caching)
- **Phase 6 (URL Strategy Change) COMPLETED:**
  - Implemented all 4 URL scenarios (base slug on/off, custom/stories homepage)
  - Homepage detection and repair system
  - Mode switcher with warnings about SEO impact
  - Conflict resolution with `-ff` suffix
- **Phase 7 (Media Upload UX) COMPLETED:**
  - Completely rewrote `fanfiction-image-upload.js` to use WordPress Media Library
  - Replaced custom AJAX uploads with wp.media modal integration
  - Added dropzone UI with click and drag-and-drop support
  - Updated all three templates (story, chapter, profile) with dropzone
  - Added comprehensive CSS for dropzone states and preview
  - Image preview with remove button functionality
  - Maintains manual URL entry option for flexibility
  - Auto-initialization via data attributes
  - Full ARIA accessibility support
- **Next:** Phase 5 (Browse/Search System) - Final major feature!

---

## 🔗 Quick Reference Links

### Key Files by Function
**Database Layer:**
- `includes/class-fanfic-database-setup.php` - Schema definitions

**Core Logic:**
- `includes/class-fanfic-core.php` - Main plugin class
- `includes/class-fanfic-validation.php` - Validation rules
- `includes/functions.php` - Helper functions

**Admin Interface:**
- `includes/class-fanfic-admin.php` - Admin menu structure
- `includes/class-fanfic-settings.php` - Settings pages
- `includes/class-fanfic-stories-table.php` - Story list table

**Frontend Templates:**
- `templates/template-story-form.php` - Story create/edit
- `templates/template-chapter-form.php` - Chapter create/edit
- `templates/template-story-view.php` - Story display
- `templates/template-story-archive.php` - Story listing
- `templates/template-search-page.php` - Search interface

**Handlers:**
- `includes/handlers/class-fanfic-story-handler.php` - Story CRUD
- `includes/handlers/class-fanfic-chapter-handler.php` - Chapter CRUD
- `includes/handlers/class-fanfic-profile-handler.php` - Profile updates

### Pattern References
**To Mirror for New Features:**
- Fandoms system: `includes/class-fanfic-fandoms.php` + `includes/admin/class-fanfic-fandoms-admin.php`

---

## 📋 Important Principles

### Backward Compatibility
**NOT REQUIRED** - Remove or replace legacy code instead of adding compatibility layers.

### Conflict Resolution
If any requirements conflict:
1. Requirements files (section 1 of this INDEX) win
2. Check requirement file last-modified date - newest wins
3. When in doubt, ask the user for clarification

### Code Standards
- Follow WordPress coding standards
- Use prepared statements for all SQL
- Sanitize all input, escape all output
- Add nonces to all forms
- Check capabilities before all actions
- Use transients for caching
- Comment complex logic
- Keep functions focused and small

### Testing
After each phase:
- Test all new functionality manually
- Check for PHP errors/warnings
- Verify database changes
- Test with different user roles
- Check responsive design
- Validate accessibility (WCAG 2.1 AA)

---

## 🆘 Troubleshooting

### If You're Blocked
1. Check "Status Dashboard" for phase dependencies
2. Verify blocking phases are ✅ COMPLETED
3. Check "Progress Notes" for known blockers
4. Review requirement files for clarification
5. Ask the user if requirements are unclear

### If Requirements Are Unclear
1. Check multiple requirement files - may be cross-referenced
2. Look at "Audit Findings.md" for context
3. Check "Master Implementation Plan.md" for high-level intent
4. Ask user specific question: "For [feature], should I [option A] or [option B]?"

### If Implementation Differs from Plan
1. Document the change in "Progress Notes"
2. Update the relevant phase checklist if needed
3. Notify user of the deviation and reasoning

---

## ✨ Success Criteria (Release)

Before considering this plugin "done", verify:

- [ ] Search index updates automatically on all relevant changes
- [ ] Search page has ZERO live LIKE queries on content
- [ ] Warnings: full CRUD, selection, derived age, global toggles all work
- [ ] Tags: limits enforced, visible rendered, invisible never rendered
- [ ] Ban/block: reasons stored, authors can view but not edit/delete
- [ ] Admin story list: publish failures show per-story notices
- [ ] Admin menu: tabs and routes match specification exactly
- [ ] URL strategy: all 4 scenarios validated, fix button works
- [ ] Media upload: dropzone uses wp.media, updates URL fields
- [ ] All templates have persistent info-box headers
- [ ] No backward compatibility code added
- [ ] No PHP errors or warnings
- [ ] WCAG 2.1 AA accessibility compliance
- [ ] Responsive design works on mobile/tablet/desktop

---

**End of Orchestrator File**

*This file is the single source of truth for implementation status. Keep it updated!*
