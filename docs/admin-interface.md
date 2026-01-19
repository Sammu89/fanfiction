# Admin Interface & Configuration

## Admin Bar Menu Structure
The plugin adds a top-level "Fanfiction" item to the WordPress admin bar (not nested under Tools or Settings). The menu structure is:
Fanfiction
├─ Stories (Admin page listing all stories, when entering the story, the frontend manage story opens, giving the possibility to edit chapters)
├─ Settings (Tabbed admin page with all configuration)
│   ├─ Dashboard (Platform statistics overview)
│   ├─ General (Transient cleanup settings, Maintenance mode)
│   ├─ Email templates (Email template management)
│   └─ Custom CSS (CSS editor with syntax highlighting)
├─ Users (a list of the fanfic users (readers, authors, mods and admin) each with a story count, and actions to ban, promote or demote
├─ Taxonomies (Manage genres, status, and custom taxonomies)
├─ URL Name Rules (Configure base slug and chapter-type slugs)
└─ Moderation Queue (View, approve, or delete reports) Reports are shown in form of entries, telling what was reported, why and by who. And have a check button that saves which admin or mod checked them as treated and what action they did (per writing) for log purposes
text## Stories Page
The Stories page displays a sortable, filterable table of all fanfiction stories:

**Columns:**
- Story Title (linked to story edit page).
- Author (linked to author's profile).
- Chapter Count (number of chapters, excluding prologue/epilogue).
- Status (Finished/Ongoing/On Hiatus/Abandoned).
- Publication Status (Published/Draft badge with icon).
- Views (total view counter).
- Genre.
- Custom taxonomies.
- Average Rating (mean of all chapter ratings).
- Last Updated (date of most recent chapter or metadata change).
- Actions (Edit, View, Delete dropdown).

**Filters:**
- By Author (dropdown of all authors with published stories).
- By Status (dropdown: Finished/Ongoing/etc.).
- Publication Status (toggle: Published Only / Include Drafts).
- By views.
- By last update.

**Child Chapter Display:**
When an admin clicks "Edit" on a story, they are prompted in a new window to the specific story management page in frontend. It's the same page authors use to manage their story, but it's also accessible to admins and mods.

## Settings Pages (Tabbed Interface)

### Dashboard Tab
Displays platform statistics:
- Total Stories (published only).
- Total Chapters (across all published stories).
- Total Authors (users with at least one published story).
- Active Readers (total registered users).
- Pending Reports (count of unreviewed reports in moderation queue).
- Suspended Users (count of users with Fanfic_Banned_Users role).
- Chart that shows the view increase for the last week and last month.

Visual representations (charts, numbers) make it easy to understand platform health at a glance.

Moderators have access to detailed analytics (top stories, trending authors, comment activity over time). Dashboard is sufficient—no separate Analytics page. Time periods: Let the user choose between All-time, last 30 days, last year. Stats not exportable to CSV/PDF. No author-level analytics (overkill on server).

### General Tab
Configures core plugin behavior:
- Featured stories [radio box] with "Manual" or "Automatic". Featured stories can be set to 'Manual' (mods/admins select stories) or 'Automatic' (based on criteria like mean rating ≥ X after ≥ X votes, and or ≥ X comments). Maximum X featured stories at a time. A 'Featured' badge is displayed on story cards via [story-is-featured].
- If automatic feature is ON, there is no possibility of having a mod or admin feat a custom story. If a featured story is tagged manually and then admin chooses to go automatic, the story loses their featured tag.
- Maintenance mode (if activated, every fanfic-related page will redirect to Maintenance page template).
- Transient cleanup settings: option to manually clear all fanfiction transients.
- Define hour of wp_cron tasks (explain to user that sync tasks occur once per day, and user should choose an hour of least traffic not to overload the server).
- A “Run Cron Now” button allows admins to manually trigger the daily WP-Cron tasks (e.g., author demotion, view count sync) if cron is disabled or delayed on the hosting environment. The button is in the Settings > General tab, protected by a nonce, and logs the manual run timestamp in wp_options (fanfic_last_cron_run).

### Email Templates Tab
Manages email template system:
- Displays email templates as editable textareas for each notification type:
  - New Comment Notification.
  - New Story/Chapter from Followed Author.
  - New Follower Notification.
  - New Chapter Notification on followed story.
  
Each template shows available variables (e.g., `{{author_name}}`, `{{story_title}}`, `{{user_name}}`) that the system substitutes with actual values when sending.

Email batch frequency not customizable (every 30 min). No SMS notifications. No per-author notification rules.

### Custom CSS Tab
CSS editor interface:
- Textarea with syntax highlighting (CodeMirror).
- Help text: "CSS applies only to fanfiction plugin pages".
- CSS is generated dynamically on every page load (not saved to file) and applied inline to plugin pages only.

## Taxonomies Management Page
Displays a table of all content taxonomies (built-in and custom). See data-models.md for full details.

## URL Name Rules Page
Consolidated page for managing all URL-related configuration:

  Core Structure:

  1. Base Slug (fanfic_base_slug) - default: fanfiction
  2. Stories Path (fanfic_story_path) - default: stories
  3. Main Page Mode (new) - Stories as Homepage OR Custom Homepage

  System Page Slugs:

  4. Login - default: login
  5. Register - default: register
  6. Password Reset - default: password-reset
  7. Dashboard - default: dashboard
  8. Create Story - default: create-story
  9. Edit Story - default: edit-story
  10. Edit Chapter - default: edit-chapter
  11. Edit Profile - default: edit-profile
  12. Search - default: search
  13. Error - default: error
  14. Maintenance - default: maintenance
  15. Members (new) - default: members

  Chapter Type Slugs:

  16. Prologue - default: prologue
  17. Chapter - default: chapter
  18. Epilogue - default: epilogue

  Total: 18 modifiable elements

Slugs must be unique, alphanumeric, and no longer than 50 characters to prevent conflicts.

**Section 4: Admin Information Box**
Warning notice explaining that system page names (Login, Register, etc.) are renamed directly in the WordPress Pages list, not here. The plugin recognizes pages by ID, so changing the slug/title in Pages doesn't break functionality.

## Moderation Queue Page
Displays all reported content in a sortable, filterable table. The reports are like messages sent via the report tab.

**Columns:**
- View report.
- Post Title (linked to view content).
- Post Type (Story/Chapter icon or label).
- Reported By (Username if logged in, or "Anonymous (IP: xxx.xxx.xxx.xxx)").
- Date (when report was submitted).
- Status (Pending/Reviewed/Dismissed).

Statuses: Pending/Reviewed/Dismissed/Deleted (content removed).
Actions: Resolved (marks reviewed), Dismissed (no action), Delete (removes the report, considered useless; does not remove content + report, just report).

Log: "check button that saves which admin or mod checked them as treated and what action they did (per writing)"—meaning when admin clicks on Reviewed, a pop-up asks what they did exactly in a text field, and that is saved for reference and consultation.
