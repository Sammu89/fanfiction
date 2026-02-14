# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## High-Level Architecture

The Fanfiction Manager is a WordPress plugin that transforms WordPress into a dedicated fanfiction publishing platform. It follows a **modular, singleton-pattern architecture** with clear separation of concerns:

### Core Layers

1. **Foundation (class-fanfic-core.php)**
   - Single entry point that loads all dependencies via `load_dependencies()`
   - Loads classes in a specific order: database setup → cache → security → features
   - Implements WordPress hooks (`register_activation_hook`, `register_deactivation_hook`)
   - Main plugin file (`fanfiction-manager.php`) initializes via `Fanfic_Core::get_instance()` on the `init` hook

2. **URL & Homepage System (class-fanfic-url-manager.php, class-fanfic-homepage-state.php)**
   - **Centralized URL management**: `Fanfic_URL_Manager` handles rewrite rules, dynamic pages (dashboard, members), and static pages
   - **Homepage state management**: `Fanfic_Homepage_State` manages 2 axes of homepage configuration:
     - `use_base_slug` (0/1): Whether fanfiction lives at base slug or uses WP native homepage
     - `homepage_choice`: Which page serves as homepage (stories_archive, fanfiction_page, wordpress_archive, existing_page)
     - Result: 8 homepage scenarios with different WP front page settings
   - **Important**: `resolve_wp_front_page_target()` determines WordPress `show_on_front`/`page_on_front` based on state
   - **Critical lesson**: "Stories Archive" homepage uses the **stories page** (`page_ids['stories']`), NOT the main page

3. **Data Model (class-fanfic-post-types.php, class-fanfic-taxonomies.php)**
   - Custom post types: `fanfiction_story` (stories container), `fanfiction_chapter` (individual chapters)
   - Core taxonomies: `fanfiction_genre` (hierarchical), `fanfiction_status` (flat)
   - Custom tables: `wp_fanfic_ratings`, `wp_fanfic_follows`, `wp_fanfic_follows`, `wp_fanfic_notifications`, `wp_fanfic_reports`
   - Database setup in `class-fanfic-database-setup.php` runs on activation via `activate()` hook

4. **User System (class-fanfic-roles-caps.php)**
   - Roles: `fanfiction_author` (frontend-only), `fanfiction_moderator` (admin access), enhanced `administrator`
   - Capabilities tied to roles; enforced via `current_user_can()` checks throughout
   - User banning logic preserves content (story/chapter records remain, author marked banned)

5. **Security & Performance**
   - `class-fanfic-security.php`: Nonce verification, capability checks, input sanitization
   - `class-fanfic-ajax-security.php`: Unified AJAX security layer
   - `class-fanfic-rate-limit.php`: Prevents abuse of form submissions
   - `class-fanfic-cache.php` + `class-fanfic-cache-hooks.php`: Transient-based caching with automatic invalidation
   - `class-fanfic-performance-monitor.php`: Monitors slow queries/performance issues

6. **Frontend Features (Shortcodes, Handlers, Templates)**
   - **Shortcodes** (`includes/shortcodes/`): ~13 shortcode classes handling story display, search, comments, forms, etc.
   - **Handlers** (`includes/handlers/`): Request processing for stories, chapters, profiles
   - **Templates** (`includes/class-fanfic-templates.php`, `templates/`): Theme fallback templates + custom page template
   - **AJAX handlers** (`class-fanfic-ajax-handlers.php`): Centralized AJAX endpoints with nonce + capability verification

7. **Wizard (class-fanfic-wizard.php)**
   - 5-step setup flow: homepage choice → URL mode + slugs → user roles → taxonomies → review
   - Completion order: `commit_draft()` → `sync_homepage_settings()` → `create_pages()` → `verify_gates()`
   - State stored as WP options; pages created as physical posts

### Key Architecture Patterns

- **Singletons**: All core classes use `private __construct()` + `get_instance()` static method
- **Hooks + Actions**: Heavy use of WordPress hooks (`add_action`, `add_filter`) for extensibility
- **Transients**: Caching via `set_transient()`/`get_transient()` with automatic invalidation on content changes
- **Nonces**: AJAX and form submissions protected via `wp_verify_nonce()`
- **Capabilities**: All admin/moderator actions gated by `current_user_can()`
- **WP_Query**: Stories/chapters queried via `WP_Query` or custom SQL with prepared statements

## Commonly Used Commands

### Development Setup
- **WP CLI location**: `wp-content/plugins/fanfiction/` (you are here)
- **Activate plugin**: Log into WP admin, go to Plugins, activate "Fanfiction Manager"
- **Deactivate plugin**: Plugins → Deactivate "Fanfiction Manager"

### Build & Testing
- **npm build** (if applicable): Run from plugin directory if `package.json` and `npm` dependencies exist
  - Check for `node_modules` and scripts in `package.json` first
  - Currently no standard test suite; test manually via WP browser or AJAX calls

### Quick Debugging
- **Check permalinks**: Visit WP Settings → Permalinks to ensure rewrite rules are registered (flush via "Save Changes")
- **View plugin logs**: Enable `WP_DEBUG` in `wp-config.php` and check `wp-content/debug.log`
- **Test AJAX**: Browser DevTools Console → Network tab to inspect AJAX requests to `/wp-admin/admin-ajax.php`
- **Inspect database**: Use phpMyAdmin or `wp db` (WP CLI) to view custom tables (`wp_fanfic_*`)

### Common Files to Modify
- **Add/edit shortcodes**: `includes/shortcodes/class-fanfic-shortcodes-*.php`
- **Add/edit UI handlers**: `includes/handlers/class-fanfic-*-handler.php`
- **Modify URL structure**: `class-fanfic-url-manager.php` + `class-fanfic-url-config.php`
- **Adjust homepage logic**: `class-fanfic-homepage-state.php`
- **Add caching**: Update `class-fanfic-cache-hooks.php` to invalidate transients on new actions
- **Modify templates**: `templates/template-*.php` + `class-fanfic-templates.php`

## Critical Implementation Notes

### Homepage System Complexity
- **8 scenarios** exist based on 2 axes: `use_base_slug` × homepage choice
- **Never** set `show_on_front = 'posts'` expecting fanfiction stories—that shows WP blog archive
- **Always** sync WordPress front page settings via `Fanfic_Homepage_State::sync_homepage_settings()` after option changes
- The `search` page must exist in **both** creation array AND verification array (Gate 3)

### Wizard Flow Timing
- Pages are created **after** `sync_homepage_settings()`, not before
- `sync_homepage_settings()` is safe to call before pages exist (no-op when IDs = 0)
- Verification gates check that all 8 physical pages exist + URL rewrite rules are active

### Database Queries
- Always use `wpdb->prepare()` to prevent SQL injection
- Use `WP_Query` for post queries; custom SQL only for performance-critical aggregations
- Custom table queries must handle table prefix via `$wpdb->prefix . 'fanfic_ratings'`

### Frontend vs Admin Access
- Authors and readers should **never** access WordPress admin
- All author tools via shortcodes + AJAX (dashboard, story creation, profile management)
- Moderators + admins only in WP admin (Moderation Queue, Content Moderation, Taxonomies)

### Caching Strategy
- Invalidate transients on post/comment/user changes via hooks in `class-fanfic-cache-hooks.php`
- Prefix all transients with `fanfic_` to avoid collisions
- Use `fanfic_get_transient()` wrapper if custom logic needed (check `class-fanfic-cache.php`)

### AJAX Security
- All AJAX calls require nonce verification + capability check
- Use `class-fanfic-ajax-security.php::verify_request()` for standard verification
- Response format: `array( 'success' => bool, 'data' => mixed, 'message' => string )`

## Documentation Structure

- **`docs/coding.md`**: Multi-agent orchestration strategy (historical; for reference)
- **`docs/data-models.md`**: CPTs, taxonomies, custom tables, Multisite behavior
- **`docs/user-roles.md`**: Role definitions, capabilities, inheritance rules
- **`docs/frontend-templates.md`**: Shortcodes, templates, page structure, URL design
- **`docs/shortcodes.md`**: Shortcode reference (categories, parameters, output)
- **`docs/admin-interface.md`**: Admin pages, moderation tools, settings
- **`docs/features.md`**: Ratings, follows, follows, notifications, search, profiles
- **`docs/performance-optimization.md`**: Query optimization, caching, lazy loading
- **`docs/pages_and_url_workflow.md`**: Complete URL/page system documentation
- **`docs/setup-wizard-audit-and-optimization.md`**: Wizard deep dive with Gate verification details

## Code Quality & Debugging

### Security Checklist Before Committing
- [ ] All user input sanitized via `sanitize_text_field()`, `sanitize_email()`, etc.
- [ ] All output escaped via `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- [ ] Nonces verified on AJAX/form submissions
- [ ] Capability checks via `current_user_can()` on all restricted actions
- [ ] SQL prepared statements via `$wpdb->prepare()`

### Debugging Tips
- **Trace execution**: Add `error_log( 'DEBUG: ' . print_r( $var, true ) );` and check `debug.log`
- **Test AJAX manually**: Use browser console: `fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: new FormData({ action: 'fanfic_action', nonce: '...' }) })`
- **Check transient status**: `wp transient list` (WP CLI) or direct DB query
- **Verify rewrite rules**: Visit Permalinks settings page to flush rules after code changes
- **Test as different roles**: Create test users with fanfiction_author, fanfiction_moderator roles

### When Things Break
- **404 errors on new URLs**: Rewrite rules not flushed. Visit Settings → Permalinks → Save Changes
- **AJAX returning 0**: Nonce verification failed or action hook not registered. Check `add_action( 'wp_ajax_action_name', ... )`
- **Pages not created**: `create_pages()` failed. Check error logs; ensure database tables exist
- **Caching stale data**: Transient not invalidated. Add cache-clearing hook to `class-fanfic-cache-hooks.php`
- **Permissions denied**: Check role/capabilities via `current_user_can()` debug output

## File Structure Quick Reference

```
fanfiction-manager.php              # Main plugin entry point
includes/
  class-fanfic-core.php            # Core initialization & dependency loader
  class-fanfic-database-setup.php  # DB table creation (activation hook)
  class-fanfic-wizard.php          # Setup wizard (5-step flow)
  class-fanfic-homepage-state.php  # Homepage state management (8 scenarios)
  class-fanfic-url-manager.php     # URL/rewrite rule management (centralized)
  class-fanfic-post-types.php      # Register stories/chapters CPTs
  class-fanfic-taxonomies.php      # Register genres/status taxonomies
  class-fanfic-roles-caps.php      # Define user roles & capabilities
  class-fanfic-security.php        # Nonce, sanitization, escaping helpers
  class-fanfic-ajax-handlers.php   # Unified AJAX endpoints
  class-fanfic-cache*.php          # Transient caching + invalidation
  handlers/
    class-fanfic-story-handler.php    # Story request processing
    class-fanfic-chapter-handler.php  # Chapter request processing
    class-fanfic-profile-handler.php  # Author profile processing
  shortcodes/
    class-fanfic-shortcodes-*.php     # Shortcode implementations
templates/
  template-*.php                   # Frontend templates
assets/
  css/fanfiction-frontend.css      # Frontend styles
  js/fanfiction-*.js               # Frontend JS
docs/
  *.md                             # Detailed specifications
```

## Recent Changes & Known Issues

Check `Bugs.txt` for logged issues and workarounds. Key known areas:
- URL rewrite edge cases in Multisite
- Homepage state sync timing during wizard completion
- Cache invalidation in high-concurrency scenarios

## Getting Help

- **For Claude Code questions**: Use `/help` in Claude Code CLI
- **For codebase questions**: Consult `docs/` files (listed above) for detailed specs
- **For ambiguities**: Ask explicitly rather than assuming (e.g., "Should half-stars be allowed in ratings?")
