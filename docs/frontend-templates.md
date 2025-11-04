# Frontend Templates & Pages

## Wizard
When the user activates the plugin, a wizard runs. It:
1. Asks the default slug for the plugin page slug (`/fanfic/` by default).
2. Asks user if they want to change the name of several paths (ex: /plugin_base_name/dashboard_custom_name/ , /plugin_base_name/member_custom_name/, /plugin_base_name/search_custom_name/ etc (user can chose to rename dashboard, member, search to other slugs).
5. Prompts user to choose moderators or other Admins, if he says yes, prompts a list of all WordPress users to implement either Fanfic_Admin or Fanfic_Mod (Small text that explains differences between moderator and admin).
6. Creates the system pages and plugin pages with user definitions, and outputs a fail or success message, then redirects to the plugin page.
7. On activation, check `get_option('fanfic_wizard_completed')`. If not set, run full wizard. Always verify required pages by stored IDs; create missing ones without overwriting existing (update content only if defaults changed via plugin update).

## Template System Overview
The plugin uses a hybrid template system combining real WordPress pages with shortcodes for maximum flexibility and maintainability.

**How It Works:**
1. On plugin activation wizard, the system creates real WordPress pages (stored in the `wp_posts` table) for essential functions (Login, Register, Archive, Dashboard, etc.).
2. Each page is populated with HTML + shortcodes from template files in the plugin's `/templates/` directory.
3. Shortcodes are resolved dynamically at runtime, allowing template updates to propagate without manual page edits.
4. Theme developers can change the HTML and shortcodes, but cannot delete the plugin pages (explained in theme-integration.md).

**Template Pages Created:**
The plugin creates a parent page (`/plugin_base_name/` by default) and child pages under it:
- **Login Page** (`/plugin_base_name/login/`): Custom login form with username/email and password fields, password recovery link, CSRF protection.
- **Register Page** (`/plugin_base_name/register/`): Custom registration form with username, email, password, and optional author profile fields (display name, bio).
- **Password Reset Page** (`/plugin_base_name/password-reset/`): Custom password recovery interface (not WordPress default).
- **Story Archive** (`/plugin_base_name/`): Searchable, filterable list of all public stories with sorting options. By default this is the main page of the plugin
- **Dashboard** (`/plugin_base_name/dashboard_custom_name/`): Personalized hub for logged-in users showing bookmarks, reading history, followed authors, story listing, create story link.
- **Edit Story** (`/plugin_base_name/edit-story/(option parameter to charge existing story id/slug)/`): Form for modifying story metadata or Form interface for authors to create new stories. The logic is: if this page is access via a already created story the forms are filled with the existig information. (Ex: author is in its story page and clicks Edit, this opens this page pre filled. If user clicks on Create Story, there is no story to charge so the forms are empty and it created a new story.
- **Edit Chapter** (`/plugin_base_name/edit-chapter/(option parameter to charge existing chapter id/slug)/`): Form for creating/editing individual chapters. Same logic of the edit story page
- **Edit Profile** (`/plugin_base_name/edit-profile/`): Author profile editor.
- **Search Results** (`/plugin_base_name/search_custom_name/`): Search results display with filters.
- **Error Page** (`/plugin_base_name/error_custom_name/`): Generic error page for access denied, missing stories, etc.
- **User page** (`/plugin_base_name/members/user_name_id/`): Profile page for the user.
- **Story page**: Page that the plugin uses to show the story to the users clicking on a story. Plugin uses the HTML and shortcode of this page to dynamically build the individual page stories. Protected, name can't be changed.
- **Chapter page**: Page that the plugin uses to show the story to the users clicking on a chapter. Same logic for Story Page, Protected, name can't be changed.

## Important

- All these page names plugin_base_name, login, register, password_reset etc MUST be defined on the wizard when plugin runs for the first time. This will change the name of the pages created by default and thus changing the urls (wordpress handles this). User can later mofidy the names of the pages but, for links not to break (ex, if user started with  Search Results page as search and then changes to "procurar", it will break internal links. A mechanism should be coded so that wordpress can remake this internal links without breaking. How? Maybe display a admin warning when user changes page names to rebuild the url link system or other thing else, think of best ideas and ask the user who is coding.

**Dynamic Story/Chapter Pages:**
Stories and chapters are custom post types that behave like pages. 
- A story is `post_type = story`, hierarchical = true (post_parent = main page ID), Parent = Main Plugin Page.
- A chapter is `post_type = chapter`, hierarchical = true (post_parent = story ID), Parent = Specific Story.

URLs are generated like `/plugin_base_name/my-story/chapter-1/`.

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

- **Stories:** `/plugin_base_name/{story-slug}/`
- **Prologue:** `/plugin_base_name/{story-slug}/prologue/` (slug customizable via admin URL Name Rules).
- **Chapters:** `/plugin_base_name/{story-slug}/chapter-{number}/` (both "chapter" and number customizable via admin URL Name Rules).
- **Epilogue:** `/plugin_base_name/{story-slug}/epilogue/` (slug customizable via admin URL Name Rules).
- **Author Profiles:** `/plugin_base_name/author/{author-username}/`

When the user changes the plugin base slug on URL Name Rules (e.g., from /plugin_base_name/ to /fanfics/), the plugin:
- Updates the slug of the main plugin page.
- Updates all parent posts (stories) and automatically all child posts (chapters) to reflect the new hierarchy (slugs themselves remain untouched; only rewrite rules change).
- Updates custom post type rewrite rules and flushes WordPress rewrite rules.
- Implements a single dynamic redirect from the old base slug to the new one, automatically covering all child URLs and preserving SEO.

Secondary paths (e.g., /dashboard_custom_name/, /user/) customizable to alphanumeric, <=50 chars. Validation for conflicts: Primary slugs prevent conflicts with non-plugin pages, but add a simple validation that returns an error if user has two pages with the same name.