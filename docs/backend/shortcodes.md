This list contains every shortcode tag registered in the plugin.

- Some shortcodes only show output in the right context (for example, on a story page, chapter page, or when logged in).
- Some shortcodes return nothing when there is no data to show.
- ** [story-{taxonomy-slug}] ** is dynamic and is created automatically for each active custom taxonomy.

## Story and Chapter Content

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [fanfic-story-title] | Shows the parent story title. On story/chapter pages it can also include the author/date meta and edit/status area. |
| [fanfic-chapter-title] | Shows the chapter title, or falls back to Prologue/Chapter N/Epilogue when no custom title exists. |
| [fanfic-chapter-published] | Shows the chapter publish date. |
| [fanfic-chapter-updated] | Shows the chapter last-updated date (only when meaningfully different from publish date). |
| [fanfic-chapter-content] | Shows the full chapter body content, including normal WordPress content formatting. |
| [fanfic-chapter-image] | Shows the chapter image if one is set. |
| [chapter-translations] | Shows available translated versions of the current chapter (single link or language dropdown). |

## Story Metadata and Presentation

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [story-author-link] | Shows linked author name(s), including accepted co-authors when enabled. |
| [story-intro] | Shows the story introduction/excerpt, with optional author notes placement. |
| [story-featured-image] | Shows the story featured image thumbnail. |
| [story-genres] | Shows story genres as linked text. |
| [story-genres-pills] | Shows story genres as pill-style linked badges. |
| [story-fandoms] | Shows fandom labels (or Original Work when marked as original). |
| [story-language] | Shows the story language label. |
| [story-status] | Shows a status badge (for example Ongoing/Completed, depending on taxonomy terms). |
| [story-publication-date] | Shows story publication date. |
| [story-last-updated] | Shows story last modified date. |
| [story-word-count-estimate] | Shows total estimated words across published chapters. |
| [story-chapters] | Shows number of published chapters. |
| [story-views] | Shows story total views. |
| [story-likes] | Shows story total likes. |
| [story-is-featured] | Shows a Featured badge if the story is flagged as featured. |
| [fanfic-story-image] | Shows story image from custom story image field (or featured image fallback). |
| [story-warnings] | Shows content warnings, optionally including age hints and a None declared state. |
| [story-visible-tags] | Shows visible story tags as text badges. |
| [story-age-badge] | Shows a derived age-rating badge based on warnings/default policy. |
| [story-translations] | Shows available translated versions of the story (single link or language dropdown). |

## Chapter Navigation

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [chapters-nav] | Shows previous/next chapter controls plus a chapter jump dropdown. |
| [chapters-list] | Shows a chapter table with chapter links and key stats (views, words, rating, likes, comments, updated date). |
| [first-chapter] | Shows a Start Reading link to the first chapter. |
| [latest-chapter] | Shows a link to the most recent chapter. |
| [chapter-breadcrumb] | Shows breadcrumb navigation from story to current chapter. |
| [chapter-story] | Shows a link back to the parent story. |
| [story-chapters-dropdown] | Shows a jump to chapter dropdown for all chapters in a story. |

## Comments

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [comments-list] | Shows approved comments list for a post, including empty/closed states. |
| [comment-form] | Shows the comment submission form (or login/suspension/closed messages as needed). |
| [comment-count] | Shows comment count as text (or just number format if configured). |
| [comments-section] | Shows the full comments section using plugin/default comments template. |
| [story-comments] | Shows a complete story-specific comments block (list + form + states). |
| [chapter-comments] | Shows a complete chapter-specific comments block (list + form + states). |
| [story-comments-count] | Shows story comment count with story-specific wording. |
| [chapter-comments-count] | Shows chapter comment count with chapter-specific wording. |

## Account, Auth, Ratings, and Reporting Forms

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [fanfic-login-form] | Shows frontend login form (username/email, password, remember me, submit). |
| [fanfic-register-form] | Shows frontend user registration form with validation messages. |
| [fanfic-password-reset-form] | Shows password reset request form by email. |
| [story-rating-form] | Shows read-only story average rating and rating count. |
| [chapter-rating-form] | Shows interactive chapter star rating widget and rating totals. |
| [report-content] | Shows content reporting form (reason, details, optional reCAPTCHA) for story/chapter/comment. |

## Search and Archive

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [fanfic-search-bar] | Shows the stories search/filter interface (search, taxonomies, status, language, warnings, advanced filters, active filters). |
| [fanfic-story-archive] | Shows story results grid with pagination, no-results state, and taxonomy directory mode when applicable. |

## Author Profile and Author Story Lists

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [author-display-name] | Shows the author display name. |
| [author-username] | Shows the author username/login. |
| [author-bio] | Shows author biography text or No biography available message. |
| [author-avatar] | Shows author avatar image. |
| [author-registration-date] | Shows the author's registration date. |
| [author-story-count] | Shows number of published stories by the author. |
| [author-total-chapters] | Shows total published chapters across the author's stories. |
| [author-total-words] | Shows total estimated words across the author's chapters. |
| [author-total-views] | Shows cumulative views across the author's stories. |
| [author-average-rating] | Shows average rating value (or stars mode when configured). |
| [author-story-list] | Shows paginated list/grid of the author's stories. |
| [author-coauthored-stories] | Shows stories where the author is an accepted co-author. |
| [author-stories-grid] | Shows the author's stories in grid/card layout. |
| [author-completed-stories] | Shows the author's completed stories only. |
| [author-ongoing-stories] | Shows the author's ongoing stories only. |
| [author-featured-stories] | Shows the author's featured stories only. |

## Action Buttons

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [fanfiction-action-buttons] | Shows a context-aware action bar (for example follow/bookmark, like/dislike, mark as read, share, report, edit), depending on whether the page is a story, chapter, or author context and current permissions/settings. |

## Rankings, Counts, and Leaderboards

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [story-rating-display] | Shows compact story average rating with vote count (or No ratings yet). |
| [top-rated-stories] | Shows Top Rated Stories cards list. |
| [recently-rated-stories] | Shows recently rated stories cards list. |
| [story-follow-button] | Shows follow/unfollow button for a story. |
| [story-follow-count] | Shows follow count text for a story. |
| [most-followed-stories] | Shows leaderboard of most-followed stories. |
| [story-view-count] | Shows story views count text. |
| [chapter-view-count] | Shows chapter views count text. |
| [most-viewed-stories] | Shows leaderboard of most-viewed stories. |
| [trending-stories] | Shows trending stories list. |
| [author-stats] | Shows author statistics panel (stories, chapters, total views). |
| [fanfiction-story-like-count] | Shows story like count text (hidden when zero). |
| [fanfiction-story-rating] | Shows compact story rating summary or Not rated. |

## Combined and Dynamic Taxonomy Outputs

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [story-taxonomies] | Shows a grouped taxonomy summary block for the current story (warnings, fandoms, language, and active custom taxonomies). |
| [story-{taxonomy-slug}] | Dynamic shortcode created for each active custom taxonomy. It shows that taxonomy's selected term(s) for the current story (single-select as one value, multi-select as comma-separated values). |

## URL Output Shortcodes

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [url-login] | Outputs login page URL as plain URL text. |
| [url-register] | Outputs register page URL as plain URL text. |
| [url-archive] | Outputs story archive URL as plain URL text. |
| [url-parent] | Outputs main fanfiction page URL as plain URL text. |
| [url-error] | Outputs error page URL as plain URL text. |
| [url-search] | Outputs search page URL as plain URL text. |
| [url-stories] | Outputs stories listing URL as plain URL text. |
| [url-password-reset] | Outputs password reset URL as plain URL text. |
| [url-create-story] | Outputs create-story URL as plain URL text. |
| [url-edit-story] | Outputs edit-story URL as plain URL text. |
| [url-edit-chapter] | Outputs edit-chapter URL as plain URL text. |
| [url-edit-profile] | Outputs edit-profile URL as plain URL text. |
| [url-members] | Outputs members page URL as plain URL text. |
| [url-main] | Outputs main fanfiction URL as plain URL text. |

## Utility and Status Shortcodes

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [edit-story-button] | Shows Edit Story button when current user can edit that story. |
| [edit-chapter-button] | Shows Edit Chapter button when current user can edit that chapter. |
| [fanfic-story-status] | Shows story publish status badge (Published or Draft). |
| [fanfic-chapter-status] | Shows chapter publish status badge (Published or Draft). |
| [edit-author-button] | Shows Edit Profile button when allowed (owner/admin). |

## User Dashboard and Moderation

| Shortcode | What It Outputs (WordPress User POV) |
| --- | --- |
| [user-favorites] | Shows logged-in user's followed stories list with remove actions and pagination. |
| [user-favorites-count] | Shows logged-in user's follow count. |
| [user-reading-history] | Shows logged-in user's recently read chapters/stories. |
| [user-notifications] | Shows full notifications center (unread badge, mark-read, delete, pagination). |
| [user-story-list] | Shows logged-in author's own stories with status/chapter count and edit actions. |
| [user-notification-settings] | Shows notification preferences form (email/in-app/frequency). |
| [notification-bell-icon] | Shows notification bell with unread badge and recent notifications dropdown. |
| [user-ban] | Shows moderation form button to ban a target user (permission and hierarchy restricted). |
| [user-moderator] | Shows admin-only form button to promote a user to moderator. |
| [user-demoderator] | Shows admin-only form button to demote a moderator to author. |
