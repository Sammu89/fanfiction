# Fanfiction Manager Codex

This file is a concise, high-signal map of how the plugin works so future fixes can be made quickly.

## Entry Points / Boot
- Plugin header + constants: `fanfiction-manager.php`.
- Bootstrap: `Fanfic_Core::get_instance()` on `init` (priority 0).
- Activation/deactivation: `Fanfic_Core::activate()` / `Fanfic_Core::deactivate()`.

## Core Initialization Flow
- Dependencies and class loading: `includes/class-fanfic-core.php`.
- Key init hooks:
  - Post types + taxonomies: `includes/class-fanfic-post-types.php`, `includes/class-fanfic-taxonomies.php`.
  - URL/routing system: `includes/class-fanfic-url-manager.php`.
  - Templates + page template wrapper: `includes/class-fanfic-templates.php`, `includes/class-fanfic-page-template.php`.
  - Shortcodes: `includes/class-fanfic-shortcodes.php` and `includes/shortcodes/*`.
  - Settings UI + admin features: `includes/class-fanfic-settings.php`, `includes/class-fanfic-url-config.php`.

## URL/Rewrite System (Base Slug is User-Defined)
- Base slug option: `fanfic_base_slug` (set in `includes/class-fanfic-url-config.php`).
- Story path: `fanfic_story_path`.
- Dynamic page slugs: `fanfic_dashboard_slug`, `fanfic_search_slug`, `fanfic_members_slug`.
- Chapter slugs: `fanfic_chapter_slugs` array.
- Rewrite registration: `Fanfic_URL_Manager::register_rewrite_rules()` in `includes/class-fanfic-url-manager.php`.
- Query vars: `fanfic_page`, `member_name`, `fanfiction_story`, `fanfiction_chapter`, `chapter_number`, `chapter_type`.

## Dynamic Pages (Virtual Pages)
- Dynamic pages are virtual (no wp_posts rows).
- Route detection + query vars: `Fanfic_URL_Manager::setup_virtual_pages()`.
- Virtual WP_Post injection: `Fanfic_URL_Manager::create_virtual_page_post()`.
- Content injection: `Fanfic_URL_Manager::inject_virtual_page_content()`.
- Page config map: `Fanfic_URL_Manager::get_virtual_page_config()`.

## Members Directory + Profile Flow (Important for `/members/{username}/`)
- Rewrite: `fanfic_page=members` + `member_name={username}`.
- `Fanfic_URL_Manager::setup_virtual_pages()` converts `fanfic_page` to `member_profile` when `member_name` exists.
- Template injected: `templates/template-profile-view.php`.
- Profile template uses `get_query_var('member_name')` + `get_user_by('login', $member_name)`.
- Admin-configured profile template option: `fanfic_shortcode_profile_view` (set in `includes/class-fanfic-settings.php`).

## Template Loading Overview
- Wrapper template (theme integration): `templates/fanfiction-page-template.php`.
- Fanfiction content templates: `templates/template-*.php`.
- Template routing for CPTs and main page: `Fanfic_Templates::template_loader()` in `includes/class-fanfic-templates.php`.
- Dynamic pages do NOT use `Fanfic_Templates::template_loader()`; they are injected by `Fanfic_URL_Manager`.

## Profile View Template System
- Default profile template generator: `fanfic_get_default_profile_view_template()` in `templates/template-profile-view.php`.
- Admin UI edits saved into `fanfic_shortcode_profile_view`.
- Rendering: profile template is loaded, then shortcodes are swapped to include `user_id` and rendered via `do_shortcode()`.

## Admin Settings + URL Config
- Settings framework: `includes/class-fanfic-settings.php`.
- URL settings UI + save flow: `includes/class-fanfic-url-config.php`.
- URL validation and slug schema: `includes/class-fanfic-url-schema.php`.
- System pages (physical pages) IDs: `fanfic_system_page_ids`.
- System pages slugs: `fanfic_system_page_slugs`.

## Data Model
- CPTs: `fanfiction_story`, `fanfiction_chapter`.
- Tables (created in `includes/class-fanfic-database-setup.php`): ratings, follows, follows, notifications, reports, likes, reading_progress, read_lists, subscriptions.
- Caches: `includes/class-fanfic-cache.php`, `includes/cache/*`.

## Frontend Assets
- CSS: `assets/css/fanfiction-frontend.css`.
- JS: `assets/js/fanfiction-frontend.js`, `assets/js/fanfiction-interactions.js`.
- Admin: `assets/css/fanfiction-admin.css`, `assets/js/fanfiction-admin.js`.

## Shortcodes (Key Sets)
- Core shortcodes registry: `includes/class-fanfic-shortcodes.php`.
- Author/profile shortcodes: `includes/shortcodes/class-fanfic-shortcodes-author.php`.
- URL shortcodes: `includes/shortcodes/class-fanfic-shortcodes-url.php`.
- Utility buttons: `includes/shortcodes/class-fanfic-shortcodes-utility.php`.

## Debug/Diagnostics Pointers
- Activation/template checks: `Fanfic_Core::verify_template_files()` in `includes/class-fanfic-core.php`.
- Template registration status: `Fanfic_Settings::render_system_status_box()` in `includes/class-fanfic-settings.php`.
- URL slugs + rewrite flush: `Fanfic_URL_Config::flush_all_rewrite_rules()` in `includes/class-fanfic-url-config.php`.

## Known Documentation References
- Architecture overview: `docs/overview.md`.
- Pages + URL workflow: `docs/pages_and_url_workflow.md`.
- Frontend templates: `docs/frontend-templates.md`.
