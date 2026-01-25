# Phase Execution Checklists (No Backward Compatibility)

This document expands `Implementation/Master Implementation Plan.md` into granular, file-level tasks and assigns suggested agent ownership. Backward compatibility is NOT required; remove or replace legacy paths instead of layering compatibility logic.

References:
- `Implementation/General.md`
- `Implementation/AdminStories.md`
- `Implementation/Ban System.md`
- `Implementation/Browse Page.md`
- `Implementation/Tags.md`
- `Implementation/Warnings and age.md`
- `Implementation/URL Strategy Chaneg.md`
- `Implementation/Admin Menu Refractor.txt`

Agent Roles (suggested):
- Agent A: DB/Model (schema, tables, data services)
- Agent B: Backend/Core (hooks, validation, indexing, policies)
- Agent C: Admin UI (menus, tabs, admin pages)
- Agent D: Frontend UI (templates, JS)
- Agent E: QA/Verification (manual checklist + regression scan)

------------------------------------------------------------
Phase 0 - Decision Lock (Blocking)
Owner: Lead
------------------------------------------------------------
- Decide search index storage (custom table vs indexed meta). Document choice.
- Decide warnings storage (custom tables + relations). Document choice.
- Decide tags storage (post meta + search index table). Document choice.
- Decide moderation log storage (custom table).
- Confirm author access to blocked stories (view allowed, edit/delete disabled).
- Confirm notification policy for rule-change auto-draft.
- Update `Implementation/Master Implementation Plan.md` with decisions.

Verify:
- Decisions written into plan.

------------------------------------------------------------
Phase 1 - Schema + Defaults (Foundation)
------------------------------------------------------------
1.1 Database tables
Owner: Agent A
Files:
- `includes/class-fanfic-database-setup.php`
Tasks:
- Add `fanfic_warnings` table with indexes.
- Add `fanfic_story_warnings` relation table.
- Add `fanfic_story_search_index` table.
- Add `fanfic_moderation_log` table.
- Ensure dbDelta definitions include indexes and are deterministic.

1.2 Settings defaults
Owner: Agent B
Files:
- `includes/class-fanfic-settings.php`
Tasks:
- Add defaults: allow sexual content (true), allow pornographic (false).
- Add settings sanitization logic.

1.3 Seed data
Owner: Agent B
Files:
- `includes/class-fanfic-core.php`
- `includes/class-fanfic-warnings.php` (new)
Tasks:
- Seed default warnings on activation or init.

Verify (Agent E):
- Tables exist and indexed.
- Default warnings inserted once.
- Settings defaults visible in UI.

------------------------------------------------------------
Phase 2 - Core Domain Logic
------------------------------------------------------------
2.1 Warnings core
Owner: Agent B
Files:
- `includes/class-fanfic-warnings.php` (new)
- `includes/admin/class-fanfic-warnings-admin.php` (new)
Tasks:
- CRUD and enabled filtering.
- Story warnings save/load helpers.
- Derived age calculation.
- Global restrictions (sexual/pornographic toggles).

2.2 Tags core
Owner: Agent B
Files:
- `includes/functions.php`
- `includes/handlers/class-fanfic-story-handler.php`
Tasks:
- Define meta keys for visible/invisible tags.
- Add sanitization, normalization, limit enforcement.
- Add get/set helpers.

2.3 Search index core
Owner: Agent B
Files:
- `includes/class-fanfic-search-index.php` (new)
- `includes/class-fanfic-core.php`
- `includes/handlers/class-fanfic-story-handler.php`
- `includes/handlers/class-fanfic-chapter-handler.php`
Tasks:
- Build indexed text from title, intro, author, chapter titles, tags.
- Hook updates to story/chapter/tag/author changes.
- Add batch rebuild routine.

2.4 Ban/block enhancements
Owner: Agent B
Files:
- `includes/class-fanfic-core.php`
- `includes/class-fanfic-stories-table.php`
- `includes/functions.php`
Tasks:
- Store block reason + timestamp meta.
- Allow author view but disable edit/delete.
- Rule-change auto-draft path with message.

2.5 Moderation log core
Owner: Agent B
Files:
- `includes/class-fanfic-moderation-log.php` (new)
- `includes/class-fanfic-core.php`
- `includes/class-fanfic-users-admin.php`
- `includes/class-fanfic-stories-table.php`
Tasks:
- Add log insert helper.
- Hook into ban/unban and block/unblock actions.

Verify (Agent E):
- Index updates on save.
- Warnings saved, derived age correct.
- Tags saved with limits.
- Block reason stored.
- Moderation log entries created.

------------------------------------------------------------
Phase 3 - Admin UI + Menu Refactor
------------------------------------------------------------
3.1 Admin menu refactor
Owner: Agent C
Files:
- `includes/class-fanfic-admin.php`
- `includes/class-fanfic-settings.php`
- `includes/class-fanfic-url-config.php`
- `includes/class-fanfic-taxonomies-admin.php`
Tasks:
- Rename Stories -> Story list.
- Settings page with tabs (General, URL Name, Stats and Status).
- Layout page with tabs (General, Page Templates, Email Templates, Custom CSS).
- Taxonomy page with tabs (General toggles, Genres, Status, Warnings, Fandoms).
- Moderation Log tab.
- Add My Dashboard/Profile links (target=_blank).
- Remove old root menu entries moved into tabs.

3.2 Warnings admin UI
Owner: Agent C
Files:
- `includes/admin/class-fanfic-warnings-admin.php`
Tasks:
- Mirror fandoms admin UI for warnings.

3.3 Story list notices
Owner: Agent C
Files:
- `includes/class-fanfic-stories-table.php`
Tasks:
- Ensure per-story publish failure notice for row actions.
- Add block reason modal/dropdown for row and bulk.

3.4 Moderation log tab UI
Owner: Agent C
Files:
- `includes/class-fanfic-moderation.php`
- `includes/class-fanfic-moderation-table.php`
Tasks:
- Add log tab rendering and table.

Verify (Agent E):
- Admin tabs render correctly.
- Old pages removed or redirected.
- Warnings admin works.
- Moderation log visible.
- Story list notices correct.

------------------------------------------------------------
Phase 4 - Frontend Authoring UI
------------------------------------------------------------
4.1 Story form
Owner: Agent D
Files:
- `templates/template-story-form.php`
- `includes/handlers/class-fanfic-story-handler.php`
Tasks:
- Add content rating mode controls.
- Add warnings selector.
- Add visible/invisible tag inputs.
- Add persistent `fanfic-info-box` header region.

4.2 Chapter form
Owner: Agent D
Files:
- `templates/template-chapter-form.php`
Tasks:
- Add block reason display.
- Add persistent `fanfic-info-box` header region.

4.3 Profile edit
Owner: Agent D
Files:
- `templates/template-edit-profile.php`
Tasks:
- Add persistent `fanfic-info-box` header region.

4.4 Story view + archive
Owner: Agent D
Files:
- `templates/template-story-view.php`
- `templates/template-story-archive.php`
Tasks:
- Render visible tags.
- Show warnings + derived age.

Verify (Agent E):
- Tags limits enforced.
- Warnings saved and rendered.
- Block reason shown to author/admin.

------------------------------------------------------------
Phase 5 - Browse / Search System
------------------------------------------------------------
Owner: Agent B + Agent D
Files:
- `templates/template-search-page.php`
- `templates/template-story-archive.php`
- `includes/class-fanfic-search-index.php`
- `includes/class-fanfic-core.php`
Tasks:
- Replace LIKE queries with index-based search.
- Parse URL filters and apply pipeline.
- Add optional AJAX endpoint + history API.
- Add caching and invalidation.

Verify (Agent E):
- No LIKE queries in search.
- URL-driven state restoration.
- Pagination respects filters.

------------------------------------------------------------
Phase 6 - URL Strategy Change
------------------------------------------------------------
Owner: Agent B + Agent C
Files:
- `includes/class-fanfic-wizard.php`
- `includes/class-fanfic-url-config.php`
- `includes/class-fanfic-templates.php`
- `includes/class-fanfic-url-manager.php`
Tasks:
- Add base slug on/off option in wizard.
- Implement 4 scenarios for page creation.
- Add conflict detection and `-ff` suffix.
- Add homepage fix warning + button.
- Implement mode switching with warning.

Verify (Agent E):
- All scenarios produce correct URLs.
- Fix button works.
- Conflicts resolved.

------------------------------------------------------------
Phase 7 - Media Upload UX (wp.media + dropzone)
------------------------------------------------------------
Owner: Agent D
Files:
- `assets/js/fanfiction-image-upload.js` (replace with wp.media helper)
- `templates/template-story-form.php`
- `templates/template-chapter-form.php`
- `templates/template-edit-profile.php`
- `includes/class-fanfic-core.php` (enqueue + localization)
Tasks:
- Shared helper for dropzone + wp.media.
- Replace file input UIs.

Verify (Agent E):
- Dropzone opens modal on click/drag.
- URL field populated.

------------------------------------------------------------
Dependency Graph
------------------------------------------------------------
- Phase 0 blocks all work.
- Phase 1 blocks Phases 2, 4, 5.
- Phase 2 blocks Phases 4 and 5 (core APIs required by UI + search).
- Phase 3 can run after Phase 1 and in parallel with Phase 2 if tab rendering doesn’t depend on warnings/tag logic.
- Phase 4 depends on Phase 2 (warnings/tags/save logic).
- Phase 5 depends on Phase 1 + 2.
- Phase 6 should be isolated after Phase 3 to avoid URL regressions.
- Phase 7 can run in parallel after Phase 1.

Handoff Artifacts:
- Agent A -> Agent B: DB schema + table names + indexes.
- Agent B -> Agent C/D: APIs for warnings/tags/search index + helper functions.
- Agent C -> Agent E: Updated admin routes + UI behavior.
- Agent D -> Agent E: Updated template paths + new UI states.

------------------------------------------------------------
Definition of Done (Per Phase)
------------------------------------------------------------
Phase 1 Done:
- Tables created (warnings, story_warnings, search_index, moderation_log).
- Settings defaults for sexual/pornographic toggles present.
- Warnings seeded.

Phase 2 Done:
- Warnings CRUD + derived age logic available.
- Tags storage + limits enforced.
- Search index generation hooked to story/chapter/author/tag changes.
- Block reason meta stored and readable.
- Moderation log entries recorded on ban/block actions.

Phase 3 Done:
- Admin menu reorganized with tabs; old root items removed.
- Warnings admin UI works.
- Moderation log tab lists entries.
- Story list shows row-level publish failure notice.

Phase 4 Done:
- Story form includes rating mode + warnings + tags.
- Chapter form shows block reasons.
- Profile edit page has persistent info header.
- Story view/archive render visible tags + warnings + derived age badge.

Phase 5 Done:
- Search page uses index table only (no LIKE).
- URL filters work with shareable URLs.
- Optional AJAX preserves state and supports back/forward.

Phase 6 Done:
- Base slug optional across 4 scenarios.
- Slug conflicts resolved with `-ff`.
- WP homepage warnings + fix button present.
- Switching mode warns and rebuilds pages.

Phase 7 Done:
- All three upload contexts use wp.media modal + dropzone.
- URL fields set correctly.

------------------------------------------------------------
Sample Acceptance Test Paths
------------------------------------------------------------
Admin:
- `wp-admin/admin.php?page=fanfiction-manager` (Story list)
- `wp-admin/admin.php?page=fanfiction-settings&tab=general`
- `wp-admin/admin.php?page=fanfiction-settings&tab=url`
- `wp-admin/admin.php?page=fanfiction-layout&tab=page-templates`
- `wp-admin/admin.php?page=fanfiction-taxonomy&tab=warnings`
- `wp-admin/admin.php?page=fanfiction-moderation&tab=log`

Frontend:
- Story archive with filters: `/fanfiction/stories/?genre=romance&status=ongoing`
- Search: `/fanfiction/search/?search=night+city&genre=horror`
- Story view + warnings + tags: `/fanfiction/stories/my-story/`
- Chapter edit: `/fanfiction/stories/my-story/?action=add-chapter`
- Profile edit: `/fanfiction/members/username/?action=edit`

------------------------------------------------------------
End of Checklists
------------------------------------------------------------
