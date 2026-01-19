# Frontend Templates & Pages

## Wizard
When the user activates the plugin, a wizard runs. It:
1. Asks the default slug for the plugin page slug (`/fanfic/` by default).
2. Asks user if they want to change the name of several paths (ex: /plugin_base_name/dashboard_custom_name/ , /plugin_base_name/member_custom_name/, /plugin_base_name/search_custom_name/ etc (user can chose to rename dashboard, member, search to other slugs).
5. Prompts user to choose moderators or other Admins, if he says yes, prompts a list of all WordPress users to implement either Fanfic_Admin or Fanfic_Mod (Small text that explains differences between moderator and admin).
6. Creates the system pages and plugin pages with user definitions, and outputs a fail or success message, then redirects to the plugin page.
7. On activation, check `get_option('fanfic_wizard_completed')`. If not set, run full wizard. Always verify required pages by stored IDs; create missing ones without overwriting existing (update content only if defaults changed via plugin update).

## Template System Overview
The plugin uses a hybrid template system combining static WordPress pages with shortcodes and dynamic query-parameter-based editing for maximum flexibility and maintainability.

**How It Works:**
1. On plugin activation wizard, the system creates real WordPress pages (stored in the `wp_posts` table) for essential display and form functions.
2. Each page is populated with HTML + shortcodes from template files in the plugin's `/templates/` directory.
3. Shortcodes are resolved dynamically at runtime, allowing template updates to propagate without manual page edits.
4. Edit functionality uses `?action=edit` query parameters on existing pages instead of separate edit pages.
5. Theme developers can change the HTML and shortcodes, but cannot delete the plugin pages (explained in theme-integration.md).

## Static Pages vs Dynamic Editing

### **Static Pages Created by Wizard**
These pages exist as real WordPress pages in `wp_posts` table:

- **Login Page** (`/plugin_base_name/login/`)
  - Custom login form with username/email and password fields, password recovery link, CSRF protection
  - Shortcode: `[fanfic-login-form]`

- **Register Page** (`/plugin_base_name/register/`)
  - Custom registration form with username, email, password, and optional author profile fields (display name, bio)
  - Shortcode: `[fanfic-register-form]`

- **Password Reset Page** (`/plugin_base_name/password-reset/`)
  - Custom password recovery interface (not WordPress default)
  - Shortcode: `[fanfic-password-reset-form]`

- **Story Archive** (`/plugin_base_name/`)
  - Searchable, filterable list of all public stories with sorting options
  - By default this is the main page of the plugin
  - Shortcodes: `[story-list]`, `[story-grid]`, `[search-form]`

- **Dashboard** (`/plugin_base_name/dashboard_custom_name/`)
  - Personalized hub for logged-in users showing bookmarks, reading history, followed authors, story listing
  - Shortcode: `[user-dashboard]`

- **Create Story** (`/plugin_base_name/create-story/`)
  - Form interface for authors to create NEW stories only (empty form)
  - Shortcode: `[author-create-story-form]`

- **Search Results** (`/plugin_base_name/search_custom_name/`)
  - Search results display with filters
  - Shortcode: `[search-results]`

- **Error Page** (`/plugin_base_name/error_custom_name/`)
  - Generic error page for access denied, missing stories, etc
  - Shortcode: `[fanfic-error-message]`

- **Members Page** (`/plugin_base_name/members/`)
  - User profile directory or specific user profile display
  - Template content configured in **Settings > Page Templates** tab

### **Dynamic Edit Modes (Query Parameter Based)**
These do NOT exist as separate WordPress pages. They use `?action=edit` on existing URLs:

- **Edit Story Mode**
  - URL Pattern: `/plugin_base_name/view-story/?action=edit&story_id={id}` or `/plugin_base_name/{story-slug}/?action=edit`
  - Template: `templates/template-edit-story.php`
  - Triggered by: `?action=edit` query parameter on story URL
  - Permission Check: Only story author, moderators, or admins can access
  - Pre-filled with existing story metadata (title, summary, genres, status, cover image, etc.)
  - Helper function: `fanfic_get_story_edit_url($story_id)`

- **Edit Chapter Mode**
  - URL Pattern: `/plugin_base_name/view-chapter/?action=edit&chapter_id={id}` or `/plugin_base_name/{story-slug}/chapter-{number}/?action=edit`
  - Template: `templates/template-edit-chapter.php`
  - Triggered by: `?action=edit` query parameter on chapter URL
  - Permission Check: Only chapter author, moderators, or admins can access
  - Pre-filled with existing chapter content (title, content editor, chapter notes, etc.)
  - Helper function: `fanfic_get_chapter_edit_url($chapter_id)`

- **Edit Profile Mode**
  - URL Pattern: `/plugin_base_name/members/{username}/?action=edit`
  - Template: `templates/template-edit-profile.php`
  - Triggered by: `?action=edit` query parameter on profile URL
  - Permission Check: Only profile owner or admins can access
  - Pre-filled with user data (display name, bio, avatar, social links, email preferences, etc.)
  - Helper function: `fanfic_get_profile_edit_url($user_id)`

### **Helper Functions for Edit Mode**
The following helper functions are available in `includes/functions.php`:

```php
// Check if current page is in edit mode
fanfic_is_edit_mode() // Returns true if ?action=edit or ?edit is present

// Get edit URLs
fanfic_get_story_edit_url($story_id)     // Returns story edit URL
fanfic_get_chapter_edit_url($chapter_id) // Returns chapter edit URL
fanfic_get_profile_edit_url($user_id)    // Returns profile edit URL

// Check edit permissions
fanfic_current_user_can_edit($content_type, $content_id) // $content_type: 'story', 'chapter', or 'profile'
```

## Page Templates Settings

The plugin provides a **Page Templates** settings tab (`Settings > Fanfiction > Page Templates`) where administrators can customize the templates used to display stories, chapters, and user profiles.

### **Templates Available**

1. **Story View Template** - Displayed when viewing individual story pages (`/fanfiction/story-name/`)
2. **Chapter View Template** - Displayed when viewing individual chapter pages (`/fanfiction/story-name/chapter-1/`)
3. **User Profile Template** - Displayed when viewing user profile pages (`/fanfiction/members/username/`)

### **How It Works**

- Templates are stored as HTML with shortcodes in WordPress options:
  - `fanfic_shortcode_story_view`
  - `fanfic_shortcode_chapter_view`
  - `fanfic_profile_view_template`

- The templates are loaded by the single post templates:
  - `templates/template-story-view.php` - Loads story template
  - `templates/template-chapter-view.php` - Loads chapter template
  - `templates/template-members.php` - Loads profile template

- Administrators can customize templates by adding/removing shortcodes and HTML
- Each template has a "Reset to Default" button to restore the original template

### **Available Shortcodes**

**Story Template:**
- `[fanfic-story-title]` `[story-author-link]` `[story-intro]` `[story-genres]` `[story-status]`
- `[story-word-count-estimate]` `[story-chapters]` `[story-views]` `[story-rating-form]`
- `[story-actions]` `[edit-story-button]` `[chapters-list]` `[story-chapters-dropdown]`
- `[story-featured-image]` `[story-comments]`

**Chapter Template:**
- `[chapter-breadcrumb]` `[chapter-story]` `[chapters-nav]` `[chapter-actions]`
- `[edit-chapter-button]` `[chapter-rating-form]` `[chapter-comments]`
- **Note:** Chapter content is automatically inserted; do not add it manually

**Profile Template:**
- `[author-display-name]` `[author-bio]` `[author-story-list]` `[author-actions]`
- `[edit-profile-button]` `[author-avatar]` `[author-joined-date]` `[author-story-count]`

### **Benefits**

- Full control over layout and structure without editing PHP files
- No separate WordPress pages needed for viewing stories/chapters/profiles
- Changes apply immediately to all stories/chapters/profiles
- Easy to reset to defaults if needed
- Templates are stored safely in the database

## Important Notes

- All page names (plugin_base_name, login, register, password_reset, etc.) MUST be defined in the wizard when plugin runs for the first time
- This will change the name of the pages created by default and thus changing the URLs (WordPress handles this)
- Users can later modify the names of pages, but for links not to break, a mechanism should be coded to remake internal links without breaking
- Consider displaying an admin warning when user changes page names to rebuild the URL link system

## Custom Post Types (Stories and Chapters)

Stories and chapters use WordPress custom post types that behave like hierarchical pages:

- **Story**: `post_type = fanfiction_story`, hierarchical = true, post_parent = main page ID, Parent = Main Plugin Page
- **Chapter**: `post_type = fanfiction_chapter`, hierarchical = true, post_parent = story ID, Parent = Specific Story

**Important**: Stories and chapters URLs are generated automatically by WordPress based on the custom post type configuration. They do NOT use the View Story/View Chapter static pages. The static pages are only used as fallback templates or for testing purposes.

Example URLs:
- Story: `/plugin_base_name/my-story/`
- Chapter: `/plugin_base_name/my-story/chapter-1/`
- Edit Story: `/plugin_base_name/my-story/?action=edit`
- Edit Chapter: `/plugin_base_name/my-story/chapter-1/?action=edit`

## Page Management & Protection
**Plugin Pages are Protected:**
- Plugin pages cannot be permanently deleted by admins (they're automatically recreated if missing).
- If a user deletes a system page, the plugin detects it on the next load and displays an admin notice with a "Rebuild Pages" button.
- Clicking "Rebuild Pages" recreates all missing pages using the default templates.
- The plugin page slug (`/plugin_base_name/`) can only be changed through the URL Name Rules admin page (not by directly editing the page).

**User-Editable Content:**
- Administrators can edit the content of any plugin page directly in the WordPress editor.
- Page titles and slugs can be customized (e.g., change `/plugin page slug/register/` to `/plugin page slug/register/`).
- The plugin recognizes pages by their ID (not slug), so changing the slug doesn't break functionality.
- A help notice in the URL Name Rules page informs admins that they can rename plugin pages by editing them in the WordPress Pages list.

## URL Structure
All fanfiction content lives under the plugin page slug (default `/plugin_base_name/`, customizable):

### **Static Page URLs**
- **Main Archive:** `/plugin_base_name/`
- **Login:** `/plugin_base_name/login/`
- **Register:** `/plugin_base_name/register/`
- **Dashboard:** `/plugin_base_name/dashboard/` (slug customizable)
- **Create Story:** `/plugin_base_name/create-story/`
- **View Story:** `/plugin_base_name/view-story/` (fallback/testing only)
- **View Chapter:** `/plugin_base_name/view-chapter/` (fallback/testing only)
- **Search:** `/plugin_base_name/search/` (slug customizable)
- **Members:** `/plugin_base_name/members/` or `/plugin_base_name/members/{username}/`
- **Error:** `/plugin_base_name/error/`

### **Custom Post Type URLs (Generated by WordPress)**
- **Stories:** `/plugin_base_name/{story-slug}/`
- **Prologue:** `/plugin_base_name/{story-slug}/prologue/` (slug customizable via admin URL Name Rules)
- **Chapters:** `/plugin_base_name/{story-slug}/chapter-{number}/` (both "chapter" and number customizable via admin URL Name Rules)
- **Epilogue:** `/plugin_base_name/{story-slug}/epilogue/` (slug customizable via admin URL Name Rules)

### **Edit Mode URLs (Query Parameters)**
- **Edit Story:** `/plugin_base_name/{story-slug}/?action=edit`
- **Edit Chapter:** `/plugin_base_name/{story-slug}/chapter-{number}/?action=edit`
- **Edit Prologue:** `/plugin_base_name/{story-slug}/prologue/?action=edit`
- **Edit Epilogue:** `/plugin_base_name/{story-slug}/epilogue/?action=edit`
- **Edit Profile:** `/plugin_base_name/members/{username}/?action=edit`

### **Base Slug Changes**
When the user changes the plugin base slug on URL Name Rules (e.g., from `/plugin_base_name/` to `/fanfics/`), the plugin:
- Updates the slug of the main plugin page
- Updates all parent posts (stories) and automatically all child posts (chapters) to reflect the new hierarchy (slugs themselves remain untouched; only rewrite rules change)
- Updates custom post type rewrite rules and flushes WordPress rewrite rules
- Implements a single dynamic redirect from the old base slug to the new one, automatically covering all child URLs and preserving SEO

### **Slug Customization**
- Secondary paths (e.g., `/dashboard/`, `/members/`) customizable to alphanumeric, <=50 chars
- Validation for conflicts: Primary slugs prevent conflicts with non-plugin pages
- Simple validation returns an error if user has two pages with the same name
