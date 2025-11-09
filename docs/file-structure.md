
UI text localization: Use `__()` for localized defaults. The plugin is translation-ready; translations for main languages will be added when shipping. The UI will be available in multiple languages via translation files.


# File Structure
fanfiction-manager/
├── fanfiction-manager.php (50 lines, main plugin entry point)
│
├── includes/ (Core plugin classes)
│   ├── class-fanfic-core.php (Core initialization, activation/deactivation hooks)
│   ├── class-fanfic-post-types.php (Register stories/chapters post types)
│   ├── class-fanfic-taxonomies.php (Register built-in taxonomies, dynamic custom taxonomies)
│   ├── class-fanfic-roles-caps.php (User roles, capabilities, map_meta_cap)
│   ├── class-fanfic-shortcodes.php (Shortcode registration and central dispatch)
│   ├── class-fanfic-templates.php (Template system, page creation/management)
│   ├── class-fanfic-admin.php (Admin pages, menus, settings)
│   ├── class-fanfic-frontend.php (Frontend author dashboard, forms)
│   ├── class-fanfic-notifications.php (Email, cron, notification logic)
│   ├── class-fanfic-moderation.php (Reports, moderation queue, suspension)
│   ├── class-fanfic-caching.php (Transients, cache invalidation)
│   ├── class-fanfic-ratings.php (Ratings database logic)
│   ├── class-fanfic-comments.php (Comment threading, grace period)
│   ├── class-fanfic-bookmarks.php (Bookmark/follow database logic)
│   ├── class-fanfic-rewrite.php (URL rewriting, slug management)
│   ├── class-fanfic-validation.php (Story validation, draft transitions)
│   ├── class-fanfic-security.php (Sanitization, escaping, nonce verification)
│   ├── class-fanfic-widgets.php (Custom widgets)
│   └── functions.php (Helper functions, utilities)
│
├── templates/ (Frontend page templates with shortcodes)
│   ├── login.html
│   ├── register.html
│   ├── password-reset.html
│   ├── archive.html
│   ├── dashboard.html
│   ├── create-story.html
│   ├── edit-story.html
│   ├── manage-stories.html
│   ├── edit-chapter.html
│   ├── edit-profile.html
│   ├── search.html
│   └── error.html
│   ├── single-fanfiction_story.php (Fallback template if not overridden by theme)
│   ├── template-chapter-view.php (Fallback template if not overridden by theme)
│   ├── archive-fanfiction_story.php (Fallback template if not overridden by theme)
│   ├── taxonomy-fanfiction_genre.php (Fallback template if not overridden by theme)
│   └── taxonomy-fanfiction_status.php (Fallback template if not overridden by theme)
│
├── assets/
│   ├── css/
│   │   ├── fanfiction-frontend.css (Frontend styling)
│   │   ├── fanfiction-admin.css (Admin panel styling)
│   │   └── fanfiction-responsive.css (Mobile/responsive)
│   │
│   ├── js/
│   │   ├── fanfiction-frontend.js (Frontend interactions, keyboard nav)
│   │   ├── fanfiction-admin.js (Admin panel interactions)
│   │   ├── ajax-handlers.js (AJAX endpoints)
│   │   └── lazy-load.js (Lazy loading component)
│   │
│   └── images/ (SVG icons, logos)
│
├── languages/
│   └── fanfiction-manager.pot (Translation strings)
│
└── README.md (Plugin documentation)