# Shortcode Architecture

## Shortcode System Design
All content display is handled through shortcodes stored as grouped shortcode PHP files. This approach provides:
- **Maintainability:** Modifying a shortcode's output updates it everywhere it's used across the site.
- **Reusability:** The same shortcode displays correctly in any template (story page, archive, sidebar widget, etc.).
- **Developer Accessibility:** Developers can easily locate and modify specific display components.
- **Translation Support:** Shortcodes handle text wrapping for translation.

Each shortcode is implemented as a class method in organized shortcode handler classes (e.g., `shortcodes_story`, `shortcodes_author`).

## Core Shortcode Categories

**Story Information Shortcodes** display static story metadata:
- `[fanfic-story-title]` - The story's title (includes edit buttons on story/chapter views).
- `[story-author-link]` - Author name linked to their profile.
- `[story-intro]` - Story introduction/description (excerpt field, plain text only).
- `[story-featured-image]` - Featured image URL (or placeholder if none provided).
- `[story-genres]` - Comma-separated genre links.
- `[story-status]` - Story status badge (Finished/Ongoing/On Hiatus/Abandoned).
- `[story-publication-date]` - Story publication date with customizable format.
- `[story-last-updated]` - Last chapter or metadata update date.
- `[story-word-count-estimate]` - Total word count across all chapters.
- `[story-chapters]` - Count of published chapters (excluding prologue/epilogue).
- `[story-views]` - Story view counter (sum of all chapters views on the story).

**Taxonomy Shortcodes** display categorization:
- `[story-genres]` - Built-in genre taxonomy.
- `[story-status]` - Built-in status taxonomy.
- `[fanfic-custom-taxo-{slug}]` - Dynamically generated for each custom taxonomy (e.g., `[fanfic-custom-taxo-fandom]`).
- `[fanfic-custom-taxo-{slug}-title]` - Displays the custom taxonomy's label (auto-generated per taxonomy, e.g., for "Fandom", `[fanfic-custom-taxo-fandom-title]`).

**Interactive Shortcodes** provide user interaction capabilities:
- `[story-rating-form]` - 1-5 star rating interface for the story (displays mean of chapter ratings).
- `[chapter-rating-form]` - 1-5 star rating for individual chapters.
- `[story-actions]` - Action buttons: Follow Story, Share Story, Report Story (with reCAPTCHA protection).
- `[chapter-actions]` - Action buttons: Follow Chapter, Share Chapter, Report Chapter.
- `[author-actions]` - Follow/Unfollow button for an author.

**Navigation Shortcodes** provide reading navigation:
- `[chapters-nav]` - Previous/Next chapter buttons plus dropdown chapter selector.
- `[chapters-list]` - Full list of all chapters (prologue, chapters 1-N, epilogue) with direct links.
- `[story-chapters-dropdown]` - Dropdown select menu of all chapters.
- `[first-chapter]` - Link to the prologue (if exists) or Chapter 1.
- `[latest-chapter]` - Link to the most recently published chapter.
- `[chapter-breadcrumb]` - Breadcrumb trail: Story Title > Chapter X.
- `[chapter-story]` - Link back to the parent story overview page.

**Author Shortcodes** display author profile information:
- `[author-display-name]` - Author's chosen display name.
- `[author-username]` - Author's WordPress username.
- `[author-bio]` - Author biography (plain text).
- `[author-avatar]` - Author profile image URL (or placeholder).
- `[author-registration-date]` - "Member since" date.
- `[author-story-count]` - Total number of published stories.
- `[author-total-chapters]` - Total chapters across all stories.
- `[author-total-words]` - Total word count across all stories.
- `[author-average-rating]` - Mean rating of all author's chapters.
- `[author-story-list]` - Paginated list of author's stories (published only, except author viewing own profile).
- `[author-stories-grid]` - Grid layout version of author's stories.
- `[author-completed-stories]` - Filtered list of only completed stories.
- `[author-ongoing-stories]` - Filtered list of only ongoing stories.
- `[author-featured-stories]` - Stories marked as featured from this author (even if no longer featured, they were at a certain point and must appear here).
- `[author-follow-list]` - Authors being followed by the user.

**List & Filter Shortcodes** enable content discovery:
- `[story-list]` - Paginated, filterable, sortable list of stories. Parameters: `genre="comedy,drama"` `status="ongoing"` `custom-taxo-fandom="harry-potter"`. Multiple filters combined with AND logic (must match ALL criteria). Sorting options: Date Published, Last Updated, Rating, Follows, Views. Results: 10 per page with AJAX infinite scroll.
- `[story-grid]` - Grid display version of story-list (same filtering/sorting capabilities).

**Comment & Rating Shortcodes:**
- `[story-comments]` - Full comments section for stories (threaded, 4-level depth, edit/delete grace period 30 min).
- `[story-comments-count]` - Badge showing total comments.
- `[chapter-comments]` - Full comments section for chapters.
- `[chapter-comments-count]` - Comment count badge for chapter.

**User Dashboard Shortcodes:**
- `[user-favorites]` - List of user's followed stories with timestamps and quick-remove buttons.
- `[user-followed-authors]` - List of followed authors with their latest story.
- `[user-reading-history]` - Recently read chapters with last-read timestamps.
- `[user-notification-settings]` - Form for managing notification preferences (which notifications to receive, email vs. in-app, frequency).
- `[most-followed-stories]` - Platform-wide list of most-followed stories.
- `[most-followed-authors]` - Platform-wide list of most-followed authors.
- `[user-ban]` - Demotes user to Fanfic_Banned_Users (only used by moderators and above).
- `[user-moderator]` - Promotes user to Fanfic_Mod (only by admins).
- `[user-demoderator]` - Demotes user to Fanfic_Author (only by admins).

**Utility Shortcodes:**
- `[search-results]` - Search results display (searches story titles, intros, chapter content).
- `[edit-story-button]` - Conditional edit button (visible only to story author, mods, admins).
- `[edit-chapter-button]` - Conditional edit button for chapters.
- `[edit-author-button]` - Conditional edit button for author profiles.
- `[report-content]` - Standalone report form with reCAPTCHA v2 protection, can be placed anywhere.

**URL Navigation Shortcodes:**
- `[url-login]` - Link to login page.
- `[url-register]` - Link to registration page.
- `[url-archive]` - Link to story archive.
- `[url-dashboard]` - Link to user dashboard.
- `[url-parent]` - Link to plugin base page.
- `[url-error]` - Link to error page.
- `[url-search]` - Link to search page.

These shortcodes resolve at runtime to the correct page URLs regardless of whether admins have customized page slugs or renamed pages. This provides complete localization support and prevents broken links even after site migrations.
