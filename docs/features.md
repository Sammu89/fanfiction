# Features

## Ratings System
- 1-5 star rating interface; users can choose half stars (stored as float, e.g., 4.5).
- Ratings anonymous, but stored with hashed user_id/IP.
- When user returns to an already evaluated chapter, its rating is retrieved, and the stars scale assumes their rating. Ratings are saved on the chapter post metadata, using transients as a buffer not to overload server and database at each rating.
- Averages use decimals.

## View Counting
- One hit per IP per day for uniqueness.
- Transients buffer, synced daily via cron. If cron fails, retry every 15 minutes; if it fails 10 times, alert admin in a red notification in backend. Until that, keep the info on transients.
- [chapter_views] combines cache + DB.
- [story-views] is the sum of all chapters views on the story.

## Notifications
- Notification system has two components: in-app notifications (optional) and email notifications (primary).
- In-app: Code a section called "notification" on the dashboard template that exhibits the user notifications in form of a list, with date and new notifications are bold and with a red dot. How to know if they are new? If their date is after the user last login.
- In-app notification bell icon shows unread count.
- Batch sending every 30 min via WP-Cron.

## Comments System
- Integrate WordPress native comments.
- Threaded comments (4-level depth).
- Edit grace period (30 minutes) for authors on their own comments.
- Delete grace period.
- Mods/admins can edit comments (not just delete).
- No comment approval workflows.
- Certain keywords trigger automatic comment hold (protected by Akismet).
- No collapsible threads.
- No "best comment" feature.
- Anonymous commenters’ IPs should be hashed and stored.
- Authors can edit/delete their own comments within 30 min; mods/admins can override anytime.

## Bookmarking System
- For users with large bookmark collections (e.g., 1,000+), bookmarks are paginated (10-20 per page) in the [user-favorites] shortcode.
- Bookmark data is cached in a transient (fanfic_bookmarks_{user_id}) to reduce database queries.
- Queries use indexes on wp_fanfic_bookmarks (user_id, story_id) for efficiency.
- Transients are cleared when a bookmark is added/removed and rebuilt on-demand.

## Search & Filtering
- Search scans story titles, introductions, and chapter content using basic substring matching (works on low-resource servers without breaking).
- No advanced operators (AND, OR, NOT).
- Case-insensitive.
- No stop words excluded.
- Results weighted (title matches higher than content).

## User Profile Customization
- Authors can set display name, bio (plain text, 3000 char limit), avatar URL.
- No HTML or Markdown in bios.
- Authors can add social media links (Twitter, Tumblr, etc.).
- No custom profile page appearance (header, etc.).
- No pronouns/identity info.

## Bulk Content Management
- Admins can bulk-edit stories (apply genre to multiple stories, change status, etc.).
- Admins can bulk-delete spam stories.
- Scheduled deletion: Using WordPress's system for post soft delete.
- No undo/rollback for bulk operations.

## Export/Import
- CSV format for stories, chapters, taxonomies (idea for upgrade—see ideas-for-upgrade.md).

## Ideas for Upgrade
- Authors invite co-authors to collaborate on stories.
- Story collections & series: Group multiple stories into a "series" with metadata (title, description, reading order) and separate pages/URLs.
- Readers create custom collections/lists of stories.
- Reading statistics (time spent reading, reading streaks).
- Stories translatable (multiple language versions).
- Mods issue warnings to authors before deletion (via email/private note?).
