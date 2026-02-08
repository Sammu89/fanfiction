# AGENTS.md

## Project Overview
Fanfiction Manager is a WordPress plugin that turns WordPress into a fanfiction publishing platform with custom post types, taxonomies, shortcodes, templates, and AJAX-driven frontend/admin flows. Tech stack is PHP + WordPress APIs with static JS/CSS assets; no Gutenberg blocks detected (no `block.json` or `register_block_type`), and no external dependency manifests (`package.json`/`composer.json`) found.

## Directory Structure
- `fanfiction-manager.php` - main plugin entry point
- `includes/` - core classes (core loader, URL/homepage, CPTs, taxonomies, security, caching, AJAX, wizard)
- `includes/handlers/` - story/chapter/profile request handlers
- `includes/shortcodes/` - shortcode implementations
- `includes/admin/` - admin UI and settings screens
- `includes/widgets/` - custom widgets
- `includes/cache/` - cache utilities
- `includes/migrations/` - migration scripts
- `includes/helpers/` - form helpers
- `templates/` - frontend templates and email templates
- `assets/` - CSS and JS assets
- `docs/` - detailed feature and architecture docs
- `database/` - database-related files
- `Implementation/` - implementation notes/artifacts
- `Bugs.txt` - known issues/workarounds

## Standards & Conventions
- PHP: Use singletons (`private __construct()` + `get_instance()`), load dependencies in order, and rely on WordPress hooks (`add_action`, `add_filter`) for extensibility.
- PHP: Security checklist before committing: sanitize all input (`sanitize_text_field()`, `sanitize_email()`, etc.), escape all output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`), verify nonces on AJAX/form submissions, gate restricted actions with `current_user_can()`, and use `$wpdb->prepare()` for SQL.
- PHP: AJAX calls require nonce verification + capability check; standard response format is `array( 'success' => bool, 'data' => mixed, 'message' => string )`.
- PHP: Cache via transients prefixed with `fanfic_`; invalidate via hooks in `class-fanfic-cache-hooks.php`.
- JS: No build system documented; JS lives in `assets/js` and should follow existing patterns for AJAX calls to `/wp-admin/admin-ajax.php` and frontend interactions.
- Git: No repo-specific Git conventions documented.

## Workflows
1. Plan changes
2. Code + test (manual testing via WP admin and browser; flush permalinks when URL rules change; check `wp-content/debug.log` if `WP_DEBUG` is enabled)
3. Commit message format: Not specified in `CLAUDE.md` (use concise, imperative summary)
