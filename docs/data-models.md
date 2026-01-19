# Data Models

## Story Structure
A **Story** is the top-level content unit representing a complete fanfiction work. It consists of:
- **Introduction (uses the Post_Excerpt field):** A summary or description displayed on the story overview page, written in plain text.
- **Metadata:** Genre(s), status (Finished/Ongoing/On Hiatus/Abandoned), custom taxonomies, featured image URL, publication date.
- **Chapters:** A hierarchical collection of individual reading sections, always including at least one chapter.

**Story Validation:** A story is "valid" (publicly visible) only when it meets all three criteria:
1. Has an introduction (excerpt field is not empty).
2. Has at least one chapter.
3. Is categorized with at least one genre and status.

If a story becomes invalid (e.g., all chapters deleted, genres removed), it automatically reverts to draft status and disappears from public listings. Authors and mods/admins can still view it in their administrative dashboards.

When a story becomes invalid due to chapter deletion (e.g., deleting the last chapter), it is quietly set to `post_status = 'draft'`. The user remains on the edit page (`/plugin_base_name/dashboard_custom_name/edit-story/{story-id}/`), and a JavaScript pop-up notice displays: “Your story is not published because it’s invalid.” The notice appears only once per deletion to avoid annoyance.

If a story title generates a duplicate slug (e.g., “my-cool-story” exists), append Roman numerals (e.g., “my-cool-story-II”, “my-cool-story-III”) instead of numeric suffixes. This applies site-wide to avoid conflicts with other post types.

## Chapter Organization
Chapters are organized hierarchically under their parent story using WordPress's `post_parent` relationship; they are children posts of the story. Each story can contain exactly three chapter types:

**Prologue** (optional, menu_order = 0): 
- Appears first in the reading order.
- Typically used for preambles, world-building, or context.
- URL: `/plugin_base_name/{story-slug}/prologue/`.
- Author can customize the "prologue" slug via admin settings (e.g., use "preamble" instead).

**Chapters** (required minimum 1, menu_order = 1 to N):
- Numbered sequentially (Chapter 1, Chapter 2, etc.).
- Form the main body of the story.
- URL: `/plugin_base_name/{story-slug}/chapter-{number}/` ("chapter-" slug customizable via admin).
- Authors input a chapter number when creating; the system auto-orders them.

**Epilogue** (optional, menu_order = N+1):
- Appears last in the reading order.
- Typically used for conclusions, aftermath, or reflections.
- URL: `/plugin_base_name/{story-slug}/epilogue/`.
- Author can customize the "epilogue" slug via admin settings.

Story validation requires at least one regular chapter but allows 0 prologues and 0 epilogues.

**Chapter Content and Formatting:** Allow plain text and basic HTML that enables bold, italic, line breaks. No links. If user enters plain text, WordPress should handle line breaks on the HTML part (e.g., via `nl2br()` or auto-paragraphs).

**Chapter Slug Uniqueness:** When creating/editing a chapter, the chapter number input prevents selecting a number already used by another chapter in the same story (e.g., if “chapter-1” exists, “1” is disabled in the form). This ensures unique URLs like `/plugin_base_name/{story-slug}/chapter-{number}/`.

Changing a chapter number reorders all subsequent `menu_order` values automatically.

## Custom Post Types (CPTs)
- Stories: `post_type = 'story'`, hierarchical = true (post_parent = main plugin page ID).
- Chapters: `post_type = 'chapter'`, hierarchical = true (post_parent = story ID).

URLs generated like `/plugin_base_name/my-story/chapter-1/`.

## Taxonomies
**Built-in Taxonomies (Genres, Status):**
- Edited using WordPress built-in taxonomy backend page system.
- Can be deleted (terms are removed from stories on next refresh).
- Allows viewing/managing all terms within each taxonomy.

**Custom Taxonomies (Admin-created):**
- Created via form at the bottom of the Taxonomies Management Page.
- Form fields: Taxonomy Name (max 50 chars), Taxonomy Slug (max 50 chars).
- On creation, the plugin automatically generates shortcodes (e.g., `[fanfic-custom-taxo-fandom]` and `[fanfic-custom-taxo-fandom-title]` for labels/titles).
- Hierarchical (like categories), affecting UI (checkboxes) and queries.
- Limited to 10 to prevent performance issues.
- Slug must be unique and cannot match built-in taxonomies (“genre”, “status”) or existing custom taxonomies. If duplicate, error: “Taxonomy slug already exists.” If limit reached, error: “Maximum 10 custom taxonomies allowed.”
- Slugs sanitized to prevent conflicts; prefixed with "fanfic-" for shortcodes.
- When changed/deleted/created, apply bulk cache invalidation (see performance-optimization.md).

## Custom Tables
Use a shared prefix like `wp_fanfic_` for all (e.g., `wp_fanfic_ratings`, `wp_fanfic_bookmarks`, `wp_fanfic_follows`, `wp_fanfic_notifications`, `wp_fanfic_reports`).
- Make it uniform across all custom tables.

## Multisite Compatibility
The plugin supports WordPress Multisite with strict data isolation. Each site has independent stories, chapters, taxonomies, and system pages. Custom post types (`story`, `chapter`) and taxonomies (`genre`, `status`, custom) are registered per-site, stored in site-specific tables (e.g., `wp_2_posts` for Site 2). Activation hooks (e.g., page creation, taxonomy setup) run for each blog using `switch_to_blog()`. User roles are managed per-site, allowing different roles across sites for the same user.

## Plugin Deactivation
On plugin deactivation, all data (stories, chapters in `wp_posts`, system pages in `wp_posts`, custom tables like `wp_fanfic_ratings`) is preserved by default. A warning notice/modal prompts admins: “Do you want to delete all plugin content (stories, chapters, pages)? This cannot be undone.” If confirmed, stories, chapters, and system pages are moved to the trash (`wp_trash_post()`), and custom tables are dropped. The warning is shown only to users with `delete_posts` capability.
