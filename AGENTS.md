# AGENTS.md

This is the canonical instruction file for the Fanfiction Manager plugin. `GEMINI.md`, `CODEX.md`, and any similar duplicates should be treated as secondary references and kept in sync with this file.

## Agent Quick Start
1. Follow the decision order below.
2. Inspect the codebase first and prove reuse is insufficient before adding new PHP, JS, CSS, or templates.
3. Prefer hooks, existing classes, existing helpers, and existing patterns before extending the system.
4. Keep changes minimal, scoped, and maintainable.
5. Validate the change in the browser and check logs for runtime issues when relevant.
6. Do not consider a task done until you have personally manipulated the browser and confirmed the changed behavior in the live page.
7. Before any live browser or DevTools session, ask for the exact local URL and authentication path instead of guessing.
8. Use a visible browser for live debugging and keep the session alive when possible, even if an automation script exits.
9. Prefer attaching to an existing test server or session before starting a new one; only start a new server if attachment is not possible.
10. Use the local auth file for browser login automation and never commit it to git.

## Project Overview
Fanfiction Manager is a WordPress plugin that turns WordPress into a fanfiction publishing platform with custom post types, taxonomies, shortcodes, templates, AJAX-driven frontend/admin flows, user-role tooling, caching, and moderation features.

Tech stack:
- PHP + WordPress APIs
- Static JS/CSS assets
- Frontend behavior driven by AJAX and shortcodes
- Build/tooling files are present in the repo, including `package.json`, `package-lock.json`, and `purgecss.config.js`

## Directory Structure
- `fanfiction-manager.php` - main plugin entry point
- `includes/` - core classes and bootstrap logic
- `includes/admin/` - admin UI and settings screens
- `includes/handlers/` - story, chapter, and profile request handlers
- `includes/shortcodes/` - shortcode implementations
- `includes/widgets/` - custom widgets
- `includes/cache/` - cache utilities
- `includes/migrations/` - migration scripts
- `includes/helpers/` - form helpers
- `templates/` - frontend templates and email templates
- `assets/` - CSS and JS assets
- `database/` - database-related files
- `docs/` - feature and architecture docs
- `Implementation/` - implementation notes and artifacts
- `Bugs.txt` - known issues and workarounds

## Standards and Conventions
- PHP uses singleton-style classes where appropriate: `private __construct()` plus `get_instance()`.
- Load dependencies in a deliberate order and rely on WordPress hooks (`add_action`, `add_filter`) for extensibility.
- Sanitize all input with the relevant WordPress helper functions.
- Escape all output with the appropriate escaping function.
- Verify nonces on AJAX and form submissions.
- Gate restricted actions with `current_user_can()`.
- Use `$wpdb->prepare()` for SQL.
- AJAX handlers should verify a nonce and capability before doing work.
- Standard AJAX response shape is `array( 'success' => bool, 'data' => mixed, 'message' => string )`.
- Cache through transients prefixed with `fanfic_`; invalidate through cache hooks.
- JS should follow the existing admin-ajax pattern in `assets/js`.

## Implementation Policy
- Do not add legacy or backward-compatibility code paths.
- Always implement clean, fresh, canonical behavior only.
- Do not add alias mappings, compatibility shims, or deprecated fallbacks.

## Decision Order
Before making a change, follow this order:
1. Check whether WordPress core or existing plugin settings already solve it.
2. Check whether the result can be achieved by reusing existing plugin classes, helpers, templates, hooks, or assets.
3. If placement or timing is the problem, adjust hooks or integration points before changing markup or logic.
4. If needed, extend existing code in the smallest practical way.
5. Only create new code when reuse or extension is not sufficient.
6. Only use template overrides or deeper architectural changes when there is no cleaner path.

## Shortcode Boundaries
- Story view, chapter view, and public profile view are shortcode-driven page builders and should keep using shortcode-based composition.
- Dashboard, moderation, settings, and other private or admin-only screens do not need shortcode composition and should be coded directly in their templates or classes unless there is a strong reason not to.
- Do not introduce shortcode rendering into dashboard/private-panel code just for reuse convenience.
- When a feature belongs to a private user panel, implement it directly in the dashboard/private template path first, not in the public-profile shortcode path.

## Mandatory Inspection
Before writing code:
- Search the codebase for related patterns, helpers, selectors, hooks, and classes.
- Inspect the current implementation and the relevant runtime path.
- Use browser DevTools when layout, DOM, cascade, or JavaScript runtime behavior matters.
- Check `wp-content/debug.log` or other relevant logs when the issue may be backend or runtime related.
- Do not introduce new CSS, JS, or PHP until reuse has been ruled out.

## Entry Points and Boot
- Plugin header and constants: `fanfiction-manager.php`
- Main bootstrap class: `includes/class-fanfic-core.php`
- Activation and deactivation: `Fanfic_Core::activate()` / `Fanfic_Core::deactivate()`
- Core singleton boot: `Fanfic_Core::get_instance()`

## Core Initialization Flow
- Core dependency loading: `includes/class-fanfic-core.php`
- Post types: `includes/class-fanfic-post-types.php`
- Taxonomies: `includes/class-fanfic-taxonomies.php` and `includes/class-fanfic-custom-taxonomies.php`
- URL and routing system: `includes/class-fanfic-url-manager.php`
- Templates and page wrappers: `includes/class-fanfic-templates.php`, `templates/fanfiction-page-template.php`
- Shortcodes: `includes/class-fanfic-shortcodes.php` and `includes/shortcodes/*`
- Settings and URL configuration: `includes/class-fanfic-settings.php`, `includes/class-fanfic-url-config.php`, `includes/class-fanfic-url-schema.php`

## URL and Rewrite System
- Base slug option: `fanfic_base_slug`
- Story path option: `fanfic_story_path`
- Dynamic page slugs: `fanfic_dashboard_slug`, `fanfic_search_slug`, `fanfic_members_slug`
- Chapter slugs: `fanfic_chapter_slugs`
- Rewrite registration: `Fanfic_URL_Manager::register_rewrite_rules()`
- Query vars include: `fanfic_page`, `member_name`, `fanfiction_story`, `fanfiction_chapter`, `chapter_number`, `chapter_type`
- URL validation and flush logic live in `includes/class-fanfic-url-schema.php` and `includes/class-fanfic-url-config.php`

## Dynamic Pages
- Dynamic pages are virtual and do not require `wp_posts` rows.
- Route detection and query-var setup: `Fanfic_URL_Manager::setup_virtual_pages()`
- Virtual page post injection: `Fanfic_URL_Manager::create_virtual_page_post()`
- Content injection: `Fanfic_URL_Manager::inject_virtual_page_content()`
- Page config map: `Fanfic_URL_Manager::get_virtual_page_config()`

## Members and Profile Flow
- Members URLs resolve through `fanfic_page=members` and `member_name={username}`
- `Fanfic_URL_Manager::setup_virtual_pages()` converts this to `member_profile` when a member name is present
- Profile template: `templates/template-profile-view.php`
- Profile template logic uses `get_query_var('member_name')` and `get_user_by('login', $member_name)`
- Admin-configured profile template option: `fanfic_shortcode_profile_view`

## Template Loading
- Theme integration wrapper: `templates/fanfiction-page-template.php`
- Fanfiction content templates: `templates/template-*.php`
- CPT and main-page routing: `Fanfic_Templates::template_loader()`
- Dynamic pages bypass the template loader and are injected by `Fanfic_URL_Manager`

## Profile View Template System
- Default profile template generator: `fanfic_get_default_profile_view_template()` in `templates/template-profile-view.php`
- Admin edits are stored in `fanfic_shortcode_profile_view`
- Rendering flow swaps shortcode placeholders for `user_id` and renders via `do_shortcode()`

## Admin Settings and URL Config
- Settings framework: `includes/class-fanfic-settings.php`
- URL settings UI and save flow: `includes/class-fanfic-url-config.php`
- URL schema and validation: `includes/class-fanfic-url-schema.php`
- System page IDs: `fanfic_system_page_ids`
- System page slugs: `fanfic_system_page_slugs`

## Data Model
- CPTs: `fanfiction_story`, `fanfiction_chapter`
- Database setup: `includes/class-fanfic-database-setup.php`
- Core tables include ratings, follows, notifications, reports, likes, reading progress, read lists, and subscriptions
- Cache classes: `includes/class-fanfic-cache.php`, `includes/class-fanfic-cache-manager.php`, `includes/cache/*`

## Frontend Assets
- Frontend CSS: `assets/css/fanfiction-frontend.css`
- Frontend JS: `assets/js/fanfiction-frontend.js`, `assets/js/fanfiction-interactions.js`
- Admin CSS: `assets/css/fanfiction-admin.css`
- Admin JS: `assets/js/fanfiction-admin.js`

## Shortcodes
- Core registry: `includes/class-fanfic-shortcodes.php`
- Key shortcode groups:
  - `includes/shortcodes/class-fanfic-shortcodes-author.php`
  - `includes/shortcodes/class-fanfic-shortcodes-author-forms.php`
  - `includes/shortcodes/class-fanfic-shortcodes-url.php`
  - `includes/shortcodes/class-fanfic-shortcodes-utility.php`
  - `includes/shortcodes/class-fanfic-shortcodes-story.php`
  - `includes/shortcodes/class-fanfic-shortcodes-search.php`
  - `includes/shortcodes/class-fanfic-shortcodes-forms.php`
  - `includes/shortcodes/class-fanfic-shortcodes-buttons.php`

## Debug and Diagnostics
- Activation and template checks: `Fanfic_Core::verify_template_files()`
- Template registration status: `Fanfic_Settings::render_system_status_box()`
- Rewrite flush and URL slug management: `Fanfic_URL_Config::flush_all_rewrite_rules()`
- Debug logging should be checked in `wp-content/debug.log` when `WP_DEBUG` is enabled
- For localhost/browser debugging, confirm the exact URL with the user before navigating.
- Keep the browser visible during live debugging rather than running headless only.
- Reuse an existing test server/session when one is already available; do not restart it unless attachment fails.
- Default database port for local connection checks is `10005`. If the connection fails, ask the user for the correct port before continuing.
- Use the local auth file for `wp-admin` login automation when available.
- Validate functional changes in the browser after implementation.
- Use Playwright or browser tooling for interaction and regression checks when available.
- Use DevTools for DOM, CSS, network, and console inspection.

## Script Requirements
- Local helper scripts live in `scripts/`.
- Install Node dependencies before running them: `npm install`
- Current local helper dependencies include `playwright-core`, `mysql2`, and `php-serialize`.
- The live browser helper uses a gitignored auth file at `.codex/local-auth.json`.
- A visible Chrome or Chromium installation is required for the live session helper.
- The helper reads WordPress and fanfiction settings from the local database, so the local site database must be reachable.
- Read `scripts/README.md` before changing or running local helper scripts.

## Workflow
1. Plan changes
2. Implement code, then return to the live browser session and test the changed behavior manually in WP admin and the browser before declaring it done
3. Flush permalinks when URL rules change
4. Check `wp-content/debug.log` if `WP_DEBUG` is enabled
5. Use a concise, imperative commit message if a commit is requested

## Documentation References
- Architecture overview: `docs/overview.md`
- Pages and URL workflow: `docs/pages_and_url_workflow.md`
- Frontend templates: `docs/frontend-templates.md`
