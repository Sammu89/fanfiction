# Master Implementation Plan (Phased)

This plan is designed for multi-agent execution and includes explicit references to requirement files, ordered steps, dependencies, and verification checkpoints.

Required reading order for any agent:
1) `Implementation/INDEX.md`
2) Requirement files (source of truth):
   - `Implementation/General.md`
   - `Implementation/AdminStories.md`
   - `Implementation/Ban System.md`
   - `Implementation/Browse Page.md`
   - `Implementation/Tags.md`
   - `Implementation/Warnings and age.md`
   - `Implementation/URL Strategy Chaneg.md`
   - `Implementation/Admin Menu Refractor.txt`
3) `Implementation/Audit Findings.md`
4) `Implementation/Master Implementation Plan.md`
5) `Implementation/Phase Execution Checklists.md`

Backwards compatibility: NOT required. Replace or remove legacy paths instead of bridging them.

============================================================
Phase 0 - Confirm Decisions (Short, blocking)
============================================================
Purpose: lock decisions that impact schema + UI.

STATUS: ✅ COMPLETED (2026-01-25)

FINAL DECISIONS:
1. ✅ Search index storage: Custom table (`fanfic_story_search_index`)
2. ✅ Warnings storage: Custom tables (`fanfic_warnings` + `fanfic_story_warnings` relation table)
3. ✅ Tags storage: Post meta (`_fanfic_visible_tags` and `_fanfic_invisible_tags`)
4. ✅ Moderation log storage: Custom table (`fanfic_moderation_log`)
5. ✅ Author access to blocked stories: View allowed, edit/delete disabled
6. ✅ Rule-change auto-draft notification: UI only (persistent info-box)

Verification:
- ✅ All 6 decisions documented
- ✅ Technical approach clear for Phase 1

============================================================
Phase 1 - Schema + Migrations (Foundation)
============================================================
Requirement refs: `Warnings and age.md`, `Tags.md`, `Browse Page.md`, `Ban System.md`

1.1 Database tables (extend `includes/class-fanfic-database-setup.php`)
- Add `fanfic_warnings` table (id, name, min_age, description, is_sexual, is_pornographic, enabled).
- Add `fanfic_story_warnings` relation table (story_id, warning_id).
- Add `fanfic_story_search_index` table (story_id, indexed_text, updated_at, optional fields for tokens).
- Add `fanfic_moderation_log` table (id, actor_id, action, target_type, target_id, reason, created_at).

1.2 Defaults + seeding
- Seed default warnings on activation/migration (mirror fandoms import pattern).
- Add version bump for DB updates.

1.3 Options
- Add settings defaults for sexual/pornographic toggles.

Verification:
- Tables exist in DB, indexes created.
- Default warnings inserted once.
- Settings default values visible in admin.

============================================================
Phase 2 - Core Domain Logic (No UI yet)
============================================================
Requirement refs: `Warnings and age.md`, `Tags.md`, `Browse Page.md`, `Ban System.md`

2.1 Warnings core
- Create a `Fanfic_Warnings` class mirroring `Fanfic_Fandoms`.
- CRUD functions, enabled filters, admin helpers.
- Story relations save + fetch.
- Derived age calculation logic.
- Global restriction logic (disable sexual/pornographic warnings).

2.2 Tags core
- Define meta keys for visible + invisible tags.
- Sanitization, normalization, limit enforcement.
- Helper functions to render visible tags.

2.3 Search index core
- Build index text per story from:
  - story title
  - story introduction
  - author display name
  - chapter titles
  - visible + invisible tags
- Hook index generation to:
  - story save/update
  - chapter add/edit/delete
  - tag updates
  - author profile updates
- Provide a background rebuild action for bulk updates.

2.4 Ban/block enhancements
- Add reason + timestamp to story block meta.
- Differentiate manual block vs ban block.
- “Rule change” auto-draft path (not blocked) with explanation message.
- Allow authors to view blocked story page while disallowing edit/delete.

2.5 Moderation logging
- Log ban/unban and block/unblock actions to moderation log table.

Verification:
- A story update updates the search index.
- Warnings/tags saved and derived age correct.
- Block reasons stored and retrievable.
- Moderation log entries created.

============================================================
Phase 3 - Admin UI + Menu Refactor
============================================================
Requirement refs: `Admin Menu Refractor.txt`, `AdminStories.md`, `Warnings and age.md`, `Ban System.md`

3.1 Admin menu refactor
- Rename **Stories** to **Story list**.
- Convert **Settings** into tabbed page:
  - General
  - URL Name (move URL config here)
  - Stats and Status (rename from Dashboard)
- Add **Layout** page with tabs:
  - General (current main content)
  - Page Templates (move from Settings)
  - Email Templates (move from Settings)
  - Custom CSS (move from Settings)
- Add **Taxonomy** page with tabs:
  - General (toggles: warnings+age, fandom, tags; add custom taxonomy)
  - Genres
  - Status
  - Warnings
  - Fandoms
- Keep **Users** unchanged.
- **Moderation**: add Log tab to show moderation actions.
- Add **My Dashboard** and **My profile** menu links (open new tab).

3.2 Warnings admin UI
- Add warnings tab UI (mirror fandoms admin pattern).
- Enable/disable warnings.

3.3 Settings integration
- Add toggle fields for sexual/pornographic content in settings.
- Add taxonomy “enable warnings / enable fandom / enable tags” in taxonomy-general tab.

3.4 Admin stories list behavior
- Ensure per-story publish failure notice exists for row-level publish.
- Add block reason selection UI for row and bulk actions.

3.5 Moderation log view
- Add log tab UI for moderation actions.

Verification:
- Menu layout matches requirement with correct tabs.
- Settings and URL config are accessible under new tab structure.
- Warnings admin page works and respects permissions.
- Moderation log page shows entries.

============================================================
Phase 4 - Frontend Authoring UI
============================================================
Requirement refs: `Warnings and age.md`, `Tags.md`, `General.md`, `Ban System.md`

4.1 Story form (`templates/template-story-form.php`)
- Add content rating mode controls (PG vs Mature).
- Add warnings selector (enabled warnings only).
- Add visible + invisible tag inputs with limits.
- Add persistent `fanfic-info-box` header container for messages.

4.2 Chapter form (`templates/template-chapter-form.php`)
- Add block reason display.
- Add persistent `fanfic-info-box` header container.

4.3 Profile edit (`templates/template-edit-profile.php`)
- Add persistent `fanfic-info-box` header container.

4.4 Story view + archive cards
- Render visible tags on story view and archive loops.
- Display warnings + derived age badge.
- Show “none declared” message when applicable.

Verification:
- Story form enforces tag limits.
- Warnings selection updates derived age.
- Blocked story reasons are visible to author and admins.

============================================================
Phase 5 - Browse / Search System
============================================================
Requirement refs: `Browse Page.md`, `Tags.md`, `Warnings and age.md`

5.1 Replace search implementation
- Remove live LIKE queries from `templates/template-search-page.php`.
- Use search index table to fetch candidate story IDs.

5.2 URL filters
- Parse URL params for:
  - search
  - genre
  - status
  - age
  - warnings (exclude)
  - fandoms
  - sort
- Apply pipeline: index -> post__in -> tax_query/meta_query.

5.3 AJAX enhancement (optional)
- Add endpoint returning HTML or JSON.
- Update URL with history API; restore state on back/forward.

5.4 Caching
- Cache search results and common filter combos.
- Invalidate on story/term changes.

Verification:
- No LIKE queries on post_content.
- Filters are URL-driven and restored on refresh.
- Back/forward works.
- Pagination respects filters.

============================================================
Phase 6 - URL Strategy Change (High impact)
============================================================
Requirement ref: `URL Strategy Chaneg.md`

6.1 Wizard updates
- Add base slug option toggle.
- Add explanatory card about base slug vs root.

6.2 System page creation
- Implement 4 scenarios (base slug on/off × homepage mode).
- If no base slug, adjust WP homepage settings.
- Detect slug conflicts and append `-ff` when needed.

6.3 Admin warnings
- If WP homepage settings are changed externally, show warning with fix button.

6.4 Switch mode support
- Rebuild page hierarchy when switching base slug mode.
- Show warning about broken links and SEO reindexing.

Verification:
- All 4 scenarios work and generate correct URLs.
- WP homepage is correctly set and repairable.
- Slug conflicts are avoided.

============================================================
Phase 7 - Media Upload UX (wp.media + dropzone)
============================================================
Requirement ref: `General.md`

7.1 Shared helper
- Implement reusable JS helper using WP media modal.
- Replace per-template custom file inputs with dropzone trigger.

7.2 Attachments
- Ensure selected media URL is stored in existing URL fields.
- Decide if existing upload pipeline is still used or replaced.

Verification:
- Modal opens on dropzone click and drag/drop.
- URL field is populated.
- Upload behavior works for story, chapter, avatar.

============================================================
Parallelization Map
============================================================
- Phase 1 must complete before phases 2/4/5.
- Phase 2 warnings core + tags core can be parallel.
- Phase 3 menu refactor can be parallel with Phase 2 if tab rendering doesn’t depend on warnings/tag logic.
- Phase 4 depends on Phase 2 (warnings/tags/save logic).
- Phase 5 depends on Phase 1 + 2.
- Phase 6 should be isolated to prevent URL regressions.
- Phase 7 can be parallel with Phase 5.

============================================================
Verification Checklist (Release)
============================================================
- Search index updates on story/chapter/tag/author changes.
- Search page has zero live content LIKE queries.
- Warnings system: CRUD, selection, derived age, global toggles.
- Tags: limits enforced, visible rendered, invisible never rendered.
- Ban/block: reason + timestamp stored; authors can view but cannot edit/delete.
- Admin list: publish failures show per-story notice.
- Admin menu: tabs and routes match requirement.
- URL strategy: all 4 scenarios validated and fix button works.
- Media upload: dropzone uses wp.media and updates URL fields.

============================================================
Open Questions
============================================================
- Do we backfill existing stories with default warning mode (PG) or leave unset?
- For rule-change auto-draft, should users receive email notification?
- Should chapter body ever be indexed (and if so, truncation size)?
