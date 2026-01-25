# Audit Findings (2026-01-25)

This audit compares current plugin behavior against the Implementation requirements and maps each change to the exact code areas that will be touched.

Requirements referenced:
- `Implementation/General.md`
- `Implementation/AdminStories.md`
- `Implementation/Ban System.md`
- `Implementation/Browse Page.md`
- `Implementation/Tags.md`
- `Implementation/Warnings and age.md`
- `Implementation/URL Strategy Chaneg.md`
- `Implementation/Admin Menu Refractor.txt`

------------------------------------------------------------
1) Media Upload UX (wp.media + dropzone)
------------------------------------------------------------
Requirement source: `Implementation/General.md`

Current:
- Uses custom file input + AJAX upload (`assets/js/fanfiction-image-upload.js`).
- Upload processing via `fanfic_handle_image_upload()` and `Fanfic_AJAX_Handlers::ajax_image_upload()`.
- Implemented in:
  - `templates/template-story-form.php`
  - `templates/template-chapter-form.php`
  - `templates/template-edit-profile.php`

Gap:
- Must use WP native media modal (`wp.media`) and dropzone UI.
- Must be a shared helper with no code duplication.
- Dropzone must open modal, upload directly, and fill URL field.

Risks:
- Existing AJAX upload pipeline (resize/WEBP) may be bypassed if using WP media. Decide whether to retain or migrate.


------------------------------------------------------------
2) Persistent Message Header (fanfic-info-box)
------------------------------------------------------------
Requirement source: `Implementation/General.md`

Current:
- Info boxes exist only conditionally (success/error/transient).
- No always-present header container on story edit, chapter edit, or user profile page.
- Implemented in:
  - `templates/template-story-form.php`
  - `templates/template-chapter-form.php`
  - `templates/template-edit-profile.php`

Gap:
- Must add a persistent header `fanfic-info-box` container to display system messages consistently.


------------------------------------------------------------
3) Ban / Block System
------------------------------------------------------------
Requirement source: `Implementation/Ban System.md`

Current:
- User ban uses role `fanfiction_banned_user` (admin + AJAX in `includes/class-fanfic-users-admin.php`).
- User ban triggers story block in `Fanfic_Core::handle_user_banned()`.
- Manual block/unblock in `includes/class-fanfic-stories-table.php` sets `_fanfic_story_blocked`.
- Blocked content hidden in public queries via `Fanfic_Core::hide_banned_users_content()`.
- Blocked story access denies authors (`Fanfic_Core::handle_blocked_story_access()`).
- Block message is generic (`fanfic_get_blocked_story_message()`).

Gaps:
- Story block reason must be selectable from a dropdown and stored with timestamp.
- Authors should be able to view blocked story pages but edit/delete must be disabled.
- Story/chapter pages must display block reason text.
- Blocks caused by user ban should show “blocked because user is banned”.
- “Rule change” must auto-draft affected stories (not blocked) and show a specific message.
- Moderation log entries for user/story block actions must appear in moderation panel.


------------------------------------------------------------
4) Admin Stories Listing
------------------------------------------------------------
Requirement source: `Implementation/AdminStories.md`

Current:
- `Fanfic_Stories_Table` supports row actions (edit/view/delete) and bulk actions.
- Bulk publish validates with `Fanfic_Validation::can_publish_story()` and shows a combined notice.
- Granular control exists; no change required on that point.

Gap:
- When publishing a story fails (no chapters/description/etc.), admin must see a specific per-story failure notice.
  - This is only done for bulk publish; individual publish still needs the same notice behavior.


------------------------------------------------------------
5) Browse / Search System
------------------------------------------------------------
Requirement source: `Implementation/Browse Page.md`

Current:
- Archive template: `templates/template-story-archive.php` supports taxonomy filters via URL params.
- Search template: `templates/template-search-page.php` performs live LIKE queries on `post_excerpt` and `post_content`.

Gaps:
- Must use pre-indexed search (custom table or indexed meta). No live LIKE on content.
- Search must cover: title, intro, author name, chapter titles, visible + invisible tags.
- URL-driven filters required: genre, status, age, warnings exclusions, fandoms, sort.
- UI must be pill-based; state in URL; browser back/forward must restore state.
- AJAX optional but recommended, must update URL with history API.


------------------------------------------------------------
6) Tags (Non-taxonomy)
------------------------------------------------------------
Requirement source: `Implementation/Tags.md`

Current:
- No custom tag system.

Gaps:
- Visible tags (max 5) and invisible search tags (max 10).
- Tags editable on story create/edit.
- Visible tags must render on story view and archive cards.
- Invisible tags must never render, only in search index.


------------------------------------------------------------
7) Warnings & Age Rating System
------------------------------------------------------------
Requirement source: `Implementation/Warnings and age.md`

Current:
- No warnings system; Fandoms exist with custom tables and admin UI.

Gaps:
- Warnings admin CRUD (parallel to fandoms).
- Store warnings, min age, flags; seed defaults.
- Story UI: content rating mode (PG vs Mature) and warnings selection.
- Derived age calculation and display.
- Global settings: allow sexual content / allow pornographic content.
- Enforcement: disabled warnings hidden and existing stories drafted or blocked.


------------------------------------------------------------
8) URL Strategy Change (base slug optional)
------------------------------------------------------------
Requirement source: `Implementation/URL Strategy Chaneg.md`

Current:
- Base slug is mandatory and always used.
- Wizard supports “stories as homepage” vs “custom homepage”.
- Pages created under base slug hierarchy only.

Gaps:
- Optional base slug (4 scenarios) + homepage mode interaction.
- If no base slug, plugin must set WP homepage and warn if user changes it.
- Fix button to restore WP homepage setting.
- Page creation must detect slug conflicts and append `-ff` if needed.
- Switching modes later must rebuild hierarchy and warn of broken links.


------------------------------------------------------------
9) Search Index / Data Layer
------------------------------------------------------------
Requirement sources: `Implementation/Browse Page.md`, `Implementation/Tags.md`

Current:
- No search index table or meta index.

Gaps:
- Add storage for search index (table or meta).
- Regenerate index on story/chapter/tag/author changes.
- Use index results to seed filters and pagination.


------------------------------------------------------------
10) WP Admin Menu Refactor
------------------------------------------------------------
Requirement source: `Implementation/Admin Menu Refractor.txt`

Current:
- Admin menu uses separate root pages for Settings, URL config, templates, etc.

Gaps:
- Rename “Stories” to “Story list”.
- Settings page becomes tabbed: General, URL Name, Stats and Status.
- Layout page with tabs: General, Page Templates, Email Templates, Custom CSS.
- Taxonomy page with tabs: General (toggles + add custom taxonomy), Genres, Status, Warnings, Fandoms.
- Moderation: add Log tab.
- Add My Dashboard and My Profile links opening in new tab.


------------------------------------------------------------
Key Code Touchpoints (non-exhaustive)
------------------------------------------------------------
- URL + wizard: `includes/class-fanfic-url-config.php`, `includes/class-fanfic-url-manager.php`, `includes/class-fanfic-wizard.php`, `includes/class-fanfic-templates.php`
- Search/browse: `templates/template-search-page.php`, `templates/template-story-archive.php`
- Fandoms pattern (mirror for warnings): `includes/class-fanfic-fandoms.php`, `includes/admin/class-fanfic-fandoms-admin.php`
- Story form: `templates/template-story-form.php`, handler `includes/handlers/class-fanfic-story-handler.php`
- Chapter form: `templates/template-chapter-form.php`, handler `includes/handlers/class-fanfic-chapter-handler.php`
- Profile edit: `templates/template-edit-profile.php`, handler `includes/handlers/class-fanfic-profile-handler.php`
- Bans/blocks: `includes/class-fanfic-core.php`, `includes/class-fanfic-stories-table.php`, `includes/class-fanfic-users-admin.php`
- Moderation: `includes/class-fanfic-moderation.php`, `includes/class-fanfic-moderation-table.php`
- Admin menu: `includes/class-fanfic-admin.php`, `includes/class-fanfic-settings.php`, `includes/class-fanfic-taxonomies-admin.php`
