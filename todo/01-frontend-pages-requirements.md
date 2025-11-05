# Comprehensive Page List for Fanfiction Manager Plugin

## Overview
The plugin creates a hybrid system with **real WordPress pages** populated with HTML and shortcodes. Pages are created during the activation wizard and are protected from deletion (auto-recreated if missing).

---

## 1. FRONTEND PAGES (Created by Plugin)

### 1.1 Parent Plugin Page
**Path:** `/plugin_base_name/` (customizable, default: `/fanfiction/`)
**Purpose:** Main plugin page, serves as story archive by default
**Template/Shortcode:**
- `[story-list]` or `[story-grid]` - Searchable, filterable list of all public stories
**Requirements:**
- Serves as parent for all other plugin pages
- By default displays the Story Archive
- Slug customizable via URL Name Rules admin page
- Cannot be deleted (auto-recreates)
- Identified by stored page ID

---

### 1.2 Login Page
**Path:** `/plugin_base_name/login/`
**Purpose:** Custom login interface for authors and readers
**Template/Shortcode:**
- Custom login form (not default WordPress login)
**Display/Features:**
- Username or email input field
- Password field
- Password recovery link → redirects to Password Reset page
- CSRF protection (nonce verification)
- Login button
- Link to Register page
**Requirements:**
- Frontend-only access (no wp-admin redirect)
- Must work for fanfiction_author and fanfiction_reader roles
- Error messages for invalid credentials
- Redirect to Dashboard after successful login

---

### 1.3 Register Page
**Path:** `/plugin_base_name/register/`
**Purpose:** New user registration with optional author profile
**Template/Shortcode:**
- Custom registration form
**Display/Features:**
- Username field (required)
- Email field (required)
- Password field (required)
- Password confirmation field (required)
- Display name field (optional, for authors)
- Bio field (optional, for authors)
- Terms of Service checkbox
- reCAPTCHA protection
**Requirements:**
- Creates WordPress user account
- Assigns fanfiction_author role by default
- Validates username/email uniqueness
- Password strength requirements
- Email verification (optional setting)
- Redirect to Dashboard after registration

---

### 1.4 Password Reset Page
**Path:** `/plugin_base_name/password-reset/`
**Purpose:** Custom password recovery (not WordPress default)
**Template/Shortcode:**
- Custom password reset form
**Display/Features:**
- Email input field
- Submit button
- Success/error messages
- Reset link sent via email
- New password form (after clicking email link)
**Requirements:**
- Uses WordPress email system
- Secure token generation
- Token expiration (24 hours)
- Email template from Email Templates admin tab

---

### 1.5 Dashboard Page
**Path:** `/plugin_base_name/dashboard_custom_name/` (customizable, default: `/dashboard/`)
**Purpose:** Personalized hub for logged-in users
**Template/Shortcodes:**
- `[user-favorites]` - Bookmarked stories
- `[user-followed-authors]` - Followed authors list
- `[user-reading-history]` - Recently read chapters
- `[author-story-list]` - User's own stories (if author)
- `[url-dashboard]` - Link to create new story
- `[user-notification-settings]` - Notification preferences
**Display/Features:**
- Welcome message with username
- Quick stats (stories published, bookmarks, follows)
- "Create New Story" button (for authors)
- List of user's published stories with edit buttons
- List of draft stories
- Recent bookmarks with quick-remove buttons
- Followed authors with their latest story
- Reading history with timestamps
- Notification settings form
**Requirements:**
- Requires user login (redirect to Login if not authenticated)
- Different views for authors vs. readers
- Infinite scroll for long lists
- AJAX for quick actions (remove bookmark, etc.)

---

### 1.6 Edit Story Page
**Path:** `/plugin_base_name/edit-story/{story-id or slug}/` (optional parameter)
**Purpose:** Create new story OR edit existing story metadata
**Template/Shortcode:**
- Story editing form
**Display/Features:**
- Story title field
- Story intro/description field (plain text)
- Featured image uploader
- Genre selector (multi-select)
- Status dropdown (Finished/Ongoing/On Hiatus/Abandoned)
- Custom taxonomy selectors (up to 10)
- Content rating dropdown
- Publication status (Draft/Published toggle)
- Save button
- Delete story button (with confirmation)
**Logic:**
- **If accessed with story ID/slug:** Pre-fill form with existing data (Edit mode)
- **If accessed without parameter:** Empty form (Create mode)
**Requirements:**
- Only accessible to: story author, moderators, admins
- Validation for required fields (title, intro)
- Auto-save drafts every 60 seconds
- "Save Draft" vs "Publish" buttons
- After save, redirect to story page
- Nonce verification
- Cannot edit story slug directly (auto-generated from title)

---

### 1.7 Edit Chapter Page
**Path:** `/plugin_base_name/edit-chapter/{chapter-id or slug}/` (optional parameter)
**Purpose:** Create new chapter OR edit existing chapter
**Template/Shortcode:**
- Chapter editing form
**Display/Features:**
- Chapter title field
- Chapter type selector (Prologue/Chapter/Epilogue)
- Chapter number field (auto-incremented, editable)
- Chapter content editor (WordPress TinyMCE or Gutenberg)
- Author's notes (before chapter)
- Author's notes (after chapter)
- Publication status (Draft/Published)
- Save button
- Delete chapter button (with confirmation)
**Logic:**
- **If accessed with chapter ID/slug:** Pre-fill form with existing data (Edit mode)
- **If accessed without parameter:** Empty form, parent story must be specified (Create mode)
**Requirements:**
- Only accessible to: chapter author, moderators, admins
- Must be associated with a parent story
- Word count auto-calculated and stored
- Auto-save every 60 seconds
- Cannot change story parent after creation
- Chapter numbers auto-increment but can be reordered
- After save, redirect to chapter page

---

### 1.8 Edit Profile Page
**Path:** `/plugin_base_name/edit-profile/`
**Purpose:** Edit user profile and author information
**Template/Shortcode:**
- Profile editing form
**Display/Features:**
- Display name field
- Bio field (textarea)
- Avatar uploader
- Email field (with verification if changed)
- Password change fields (current, new, confirm)
- Social media links (optional)
- Notification preferences
- Save button
**Requirements:**
- Only accessible to logged-in users
- Email change requires verification
- Password change requires current password
- Avatar size limits (max 2MB)
- Bio character limit (e.g., 500 chars)

---

### 1.9 Search Results Page
**Path:** `/plugin_base_name/search_custom_name/` (customizable, default: `/search/`)
**Purpose:** Display search results with filters
**Template/Shortcodes:**
- `[search-results]` - Search results display
- `[story-list]` with search query
**Display/Features:**
- Search query display ("Showing results for: {query}")
- Filterable results by genre, status, custom taxonomies
- Sortable by: Date Published, Last Updated, Rating, Bookmarks, Views
- Pagination or infinite scroll
- Result count
- "No results found" message
**Search Scope:**
- Story titles
- Story intros/descriptions
- Chapter content
**Requirements:**
- Search works for logged-out users
- Fuzzy matching support
- Highlight search terms in results
- Filter persistence across pagination
- Fast queries with proper indexing

---

### 1.10 Error Page
**Path:** `/plugin_base_name/error_custom_name/` (customizable, default: `/error/`)
**Purpose:** Generic error page for plugin-related errors
**Template/Shortcode:**
- Custom error message display
**Display/Features:**
- Error title
- Error message (dynamic based on error type)
- Back to homepage link
- Search box
**Error Types:**
- Access denied (unauthorized)
- Story not found (404)
- Chapter not found (404)
- User not found
- Permission errors
**Requirements:**
- Different error messages based on context
- SEO-friendly (proper HTTP status codes)
- Logged errors for debugging

---

### 1.11 Members/User Profile Page
**Path:** `/plugin_base_name/members/{username}/`
**Purpose:** Public profile page for any user
**Template/Shortcodes:**
- `[author-display-name]`
- `[author-username]`
- `[author-bio]`
- `[author-avatar]`
- `[author-registration-date]`
- `[author-story-count]`
- `[author-total-chapters]`
- `[author-total-words]`
- `[author-average-rating]`
- `[author-story-list]` or `[author-stories-grid]`
- `[author-completed-stories]`
- `[author-ongoing-stories]`
- `[author-featured-stories]`
- `[author-actions]` (Follow/Unfollow button)
**Display/Features:**
- Author avatar
- Display name and username
- "Member since" date
- Bio
- Statistics (story count, chapters, words, avg rating)
- Tabbed or sectioned story lists:
  - All Stories
  - Completed Stories
  - Ongoing Stories
  - Featured Stories
- Follow/Unfollow button
- Edit profile button (only for profile owner)
**Requirements:**
- Public access (no login required)
- Only shows published stories (unless viewing own profile)
- Authors can see their own drafts
- Pagination for story lists
- Profile owner sees "Edit Profile" button

---

### 1.12 Story Page (Template)
**Path:** `/plugin_base_name/{story-slug}/`
**Purpose:** Dynamic story overview page
**Template/Shortcodes:**
- `[story-title]`
- `[story-author-link]`
- `[story-intro]`
- `[story-featured-image]`
- `[story-genres]`
- `[story-status]`
- `[story-publication-date]`
- `[story-last-updated]`
- `[story-word-count-estimate]`
- `[story-chapters]`
- `[story-views]`
- `[story-rating-form]`
- `[chapters-list]`
- `[story-actions]` (Bookmark, Share, Report)
- `[story-comments]`
- `[story-comments-count]`
- `[edit-story-button]` (conditional)
- `[first-chapter]` (Start Reading button)
- `[latest-chapter]` (Continue Reading button)
**Display/Features:**
- Story header with title and author
- Featured image
- Story intro/description
- Metadata: genres, status, dates, word count
- Chapter list with links
- Rating interface (1-5 stars)
- Action buttons (Bookmark, Share, Report)
- Comments section
- "Start Reading" button → first chapter
- "Continue Reading" button → latest chapter or last read chapter
- Edit button (for author/mods/admins)
**Requirements:**
- Protected template (cannot delete, name unchangeable)
- Plugin dynamically populates this template for each story
- SEO metadata (title, description, Open Graph)
- View counter increments on page load
- Breadcrumb navigation
- Responsive layout

---

### 1.13 Chapter Page (Template)
**Path:** `/plugin_base_name/{story-slug}/chapter-{number}/`
**Alt Paths:** `/plugin_base_name/{story-slug}/prologue/`, `/plugin_base_name/{story-slug}/epilogue/`
**Purpose:** Dynamic chapter reading page
**Template/Shortcodes:**
- `[chapter-breadcrumb]`
- `[chapter-story]` (link back to story)
- `[story-title]`
- `[chapter-title]`
- Chapter content (post_content)
- `[chapters-nav]` (Previous/Next buttons + dropdown)
- `[chapter-rating-form]`
- `[chapter-actions]` (Bookmark, Share, Report)
- `[chapter-comments]`
- `[chapter-comments-count]`
- `[edit-chapter-button]` (conditional)
**Display/Features:**
- Breadcrumb: Story Title > Chapter X
- Chapter title
- Author's notes (before chapter)
- Chapter content (formatted text)
- Author's notes (after chapter)
- Navigation: Previous/Next chapter buttons
- Chapter dropdown selector
- Rating interface (1-5 stars)
- Action buttons (Bookmark Chapter, Share, Report)
- Comments section
- Edit button (for author/mods/admins)
**Requirements:**
- Protected template (cannot delete, name unchangeable)
- Plugin dynamically populates for each chapter
- Optimized reading experience:
  - Clean typography
  - Comfortable line height and width
  - Reading progress indicator
- View counter increments on page load
- "Last read" tracking for logged-in users
- SEO metadata
- Responsive layout

---

### 1.14 Maintenance Page
**Path:** `/plugin_base_name/maintenance/`
**Purpose:** Displayed when maintenance mode is enabled
**Template/Shortcode:**
- Custom maintenance message
**Display/Features:**
- Maintenance title
- Customizable message from admin
- Expected return time
- Contact information
**Requirements:**
- Redirects all fanfiction pages to this page when maintenance mode ON
- Admins and mods can bypass (still access plugin pages)
- Simple, clean design
- Does NOT affect main WordPress site (only fanfiction pages)

---

## 2. ADMIN PAGES (WordPress Admin Area)

### 2.1 Stories Admin Page
**Location:** Admin Bar > Fanfiction > Stories
**Purpose:** Manage all stories from admin panel
**Display/Features:**
- **Table Columns:**
  - Story Title (linked to frontend story page)
  - Author (linked to author profile)
  - Chapter Count (excluding prologue/epilogue)
  - Status (Finished/Ongoing/On Hiatus/Abandoned)
  - Publication Status (Published/Draft badge)
  - Views (total counter)
  - Genres
  - Custom taxonomies
  - Average Rating
  - Last Updated
  - Actions dropdown (Edit, View, Delete)
- **Filters:**
  - By Author (dropdown)
  - By Status (dropdown)
  - Publication Status (Published Only / Include Drafts)
  - By Views (min/max)
  - By Last Update (date range)
- **Sorting:** Click column headers to sort
- **Bulk Actions:** Delete multiple stories
**Requirements:**
- Only accessible to admins and moderators
- Clicking "Edit" opens frontend Edit Story page
- Delete action requires confirmation
- Pagination (20 stories per page)
- Search box for story titles

---

### 2.2 Settings Page (Tabbed Interface)
**Location:** Admin Bar > Fanfiction > Settings
**Purpose:** Configure all plugin settings

#### Tab 2.2.1: Dashboard Tab
**Display/Features:**
- **Statistics Display:**
  - Total Stories (published only)
  - Total Chapters (across published stories)
  - Total Authors (users with ≥1 published story)
  - Active Readers (total registered users)
  - Pending Reports (unreviewed reports count)
  - Suspended Users (Fanfic_Banned_Users count)
- **Chart:** View increase for last week/month
- **Time Period Selector:** All-time, Last 30 days, Last year
- **Detailed Analytics (for mods/admins):**
  - Top stories by views/ratings
  - Trending authors
  - Comment activity over time
**Requirements:**
- Visual charts and graphs
- Real-time data (no caching for stats)
- Responsive layout
- No CSV/PDF export
- No author-level analytics

#### Tab 2.2.2: General Tab
**Display/Features:**
- **Featured Stories Settings:**
  - Radio: Manual or Automatic
  - If Manual: Admin/mod selects stories
  - If Automatic: Set criteria (min rating, min votes, min comments)
  - Max featured stories field (number)
- **Maintenance Mode Toggle:**
  - ON/OFF switch
  - Custom message field for maintenance page
- **Transient Cleanup:**
  - Button: "Clear All Fanfiction Transients"
  - Last cleanup timestamp display
- **WP-Cron Settings:**
  - Hour of day selector (0-23) for daily cron tasks
  - Explanation text about server load
- **Manual Cron Trigger:**
  - "Run Cron Now" button
  - Last manual run timestamp
  - Nonce protection
**Requirements:**
- Save button for all settings
- Warning when switching from Manual to Automatic featured stories
- Confirmation for transient cleanup
- Log manual cron runs in wp_options

#### Tab 2.2.3: Email Templates Tab
**Display/Features:**
- **Editable Templates (textareas):**
  1. New Comment Notification
  2. New Story/Chapter from Followed Author
  3. New Follower Notification
  4. New Chapter Notification on Followed Story
- **Template Variables Display:**
  - Available variables for each template (e.g., `{{author_name}}`, `{{story_title}}`, `{{user_name}}`)
  - Variable list below each textarea
- **Preview Button:** Preview email with sample data
- **Reset to Default Button:** For each template
**Requirements:**
- HTML emails supported
- Variable substitution at send time
- Validation for variable syntax
- Save all templates button
- Batch frequency NOT customizable (fixed at 30 min)

#### Tab 2.2.4: Custom CSS Tab
**Display/Features:**
- **CSS Editor:**
  - Textarea with CodeMirror syntax highlighting
  - Line numbers
  - CSS syntax validation
- **Help Text:** "CSS applies only to fanfiction plugin pages"
- **Save Button**
**Requirements:**
- CSS loaded inline on every plugin page
- Does NOT create separate CSS file
- Applies only to plugin pages (not entire site)
- CSS validation on save
- Escaping to prevent XSS

---

### 2.3 Users Admin Page
**Location:** Admin Bar > Fanfiction > Users
**Purpose:** Manage fanfiction users (readers, authors, mods, admins)
**Display/Features:**
- **Table Columns:**
  - Username
  - Display Name
  - Email
  - Role (Reader/Author/Moderator/Admin)
  - Story Count
  - Registration Date
  - Last Activity
  - Actions dropdown
- **Actions:**
  - Ban User → Demote to Fanfic_Banned_Users
  - Promote to Moderator (admins only)
  - Demote from Moderator (admins only)
  - View Profile (frontend link)
  - Edit User (WordPress user edit page)
- **Filters:**
  - By Role (dropdown)
  - By Story Count (min/max)
  - Banned Users Only (checkbox)
- **Bulk Actions:**
  - Ban multiple users
  - Send email to selected users
**Requirements:**
- Only accessible to admins and moderators
- Banning preserves all user content (stories/chapters)
- Banned users cannot login
- Confirmation dialogs for ban/promote/demote
- Pagination

---

### 2.4 Taxonomies Management Page
**Location:** Admin Bar > Fanfiction > Taxonomies
**Purpose:** Manage genres, status, and custom taxonomies
**Display/Features:**
- **Built-in Taxonomies Table:**
  - Genre (hierarchical, multiple selection)
  - Status (non-hierarchical, single selection)
- **Custom Taxonomies Table:**
  - Taxonomy Label
  - Taxonomy Slug
  - Hierarchical (Yes/No)
  - Multiple Selection (Yes/No)
  - Term Count
  - Actions (Edit, Delete)
- **Add New Custom Taxonomy Form:**
  - Label field
  - Slug field (auto-generated, editable)
  - Hierarchical checkbox
  - Multiple selection checkbox
  - Add button
- **Manage Terms Button:** For each taxonomy (opens term manager)
**Requirements:**
- Maximum 10 custom taxonomies
- Built-in taxonomies cannot be deleted
- Custom taxonomy deletion requires confirmation
- Slug validation (unique, alphanumeric, ≤50 chars)
- See data-models.md for full taxonomy specs

---

### 2.5 URL Name Rules Page
**Location:** Admin Bar > Fanfiction > URL Name Rules
**Purpose:** Configure all URL slugs and paths
**Display/Features:**
- **Section 1: Core Structure**
  1. Base Slug (default: `fanfiction`)
  2. Stories Path (default: `stories`)
  3. Main Page Mode: Radio (Stories as Homepage OR Custom Homepage)
- **Section 2: System Page Slugs**
  4. Login (default: `login`)
  5. Register (default: `register`)
  6. Password Reset (default: `password-reset`)
  7. Dashboard (default: `dashboard`)
  8. Create Story (default: `create-story`)
  9. Edit Story (default: `edit-story`)
  10. Edit Chapter (default: `edit-chapter`)
  11. Edit Profile (default: `edit-profile`)
  12. Search (default: `search`)
  13. Error (default: `error`)
  14. Maintenance (default: `maintenance`)
  15. Members (default: `members`)
- **Section 3: Chapter Type Slugs**
  16. Prologue (default: `prologue`)
  17. Chapter (default: `chapter`)
  18. Epilogue (default: `epilogue`)
- **Section 4: Admin Information Box**
  - Warning: System page names renamed in WordPress Pages list, not here
  - Plugin recognizes pages by ID, not slug
- **Save Button**
- **Reset to Defaults Button**
**Requirements:**
- Slug validation: unique, alphanumeric, ≤50 chars
- Conflict detection (no duplicate slugs)
- Automatic rewrite rules flush on save
- Single dynamic redirect from old to new base slug
- Preserve SEO during slug changes
- Preview URLs before saving

---

### 2.6 Moderation Queue Page
**Location:** Admin Bar > Fanfiction > Moderation Queue
**Purpose:** Review and manage content reports
**Display/Features:**
- **Table Columns:**
  - Report ID
  - Post Title (linked to view content)
  - Post Type (Story/Chapter icon)
  - Reported By (Username or "Anonymous (IP: xxx.xxx.xxx.xxx)")
  - Report Reason (text from report form)
  - Date Submitted
  - Status (Pending/Reviewed/Dismissed/Deleted)
  - Actions dropdown
- **Actions:**
  - View Report Details (opens modal)
  - Mark as Reviewed (opens pop-up for action notes)
  - Dismiss Report (no action needed)
  - Delete Report (removes report only, not content)
  - Delete Content (removes reported story/chapter + report)
- **Filters:**
  - By Status (Pending/Reviewed/Dismissed)
  - By Post Type (Story/Chapter)
  - By Date Range
- **Bulk Actions:**
  - Mark multiple as Reviewed
  - Dismiss multiple
- **Log Display:**
  - When marking as Reviewed, pop-up asks: "What action did you take?"
  - Text field for admin/mod to enter action notes
  - Saves: who reviewed, when, what action was taken
**Requirements:**
- Only accessible to admins and moderators
- reCAPTCHA v2 on report forms (frontend)
- Email notification to admins on new report
- Pagination
- Sort by date, status, post type

---

## 3. KEY IMPLEMENTATION REQUIREMENTS

### 3.1 Activation Wizard
**Runs on:** First plugin activation
**Steps:**
1. Ask default plugin base slug (default: `/fanfiction/`)
2. Ask to customize system page slugs (dashboard, members, search, etc.)
3. Prompt to choose moderators/admins (list all WP users, explain roles)
4. Create all system pages with default templates
5. Set up rewrite rules
6. Display success/fail message
7. Redirect to plugin main page
**Check:** `get_option('fanfic_wizard_completed')` - if not set, run wizard
**Always verify:** Required pages by stored IDs; create missing without overwriting

---

### 3.2 Page Protection & Management
- **Cannot be permanently deleted** - Auto-recreate if missing
- **Detection:** On next page load after deletion, display admin notice
- **"Rebuild Pages" Button:** Recreates all missing pages
- **Recognition:** By page ID (not slug), so slug changes don't break functionality
- **Slug Changes:** When user changes page slugs, must rebuild internal links
  - Admin warning: "Page slug changed. Click to rebuild URL link system."
  - Alternatively: Automatic link rebuilding on slug change

---

### 3.3 URL Structure Rules
- All content under plugin base slug (customizable)
- **Stories:** `/plugin_base_name/{story-slug}/`
- **Prologue:** `/plugin_base_name/{story-slug}/prologue/` (customizable)
- **Chapters:** `/plugin_base_name/{story-slug}/chapter-{number}/` (both parts customizable)
- **Epilogue:** `/plugin_base_name/{story-slug}/epilogue/` (customizable)
- **Author Profiles:** `/plugin_base_name/author/{author-username}/`
- **When base slug changes:**
  - Update main plugin page slug
  - Update rewrite rules for all CPTs
  - Flush rewrite rules
  - Implement dynamic redirect from old to new (preserves SEO)
  - All child URLs automatically updated via hierarchy

---

### 3.4 Template System
- Real WordPress pages created on activation
- Pages populated with HTML + shortcodes from `/templates/` directory
- Shortcodes resolved dynamically at runtime
- Theme developers can edit page content (HTML + shortcodes)
- Plugin updates can update default templates without overwriting custom changes
- Shortcodes categorized by function (see shortcodes.md)

---

### 3.5 Security Requirements
- All forms: CSRF protection (nonces)
- User input: Sanitization and validation
- Output: Proper escaping
- Capability checks on all actions
- SQL injection prevention (prepared statements)
- XSS prevention
- reCAPTCHA v2 on: Register, Report Content

---

### 3.6 Performance Requirements
- Transient caching for expensive queries
- Lazy loading for story/chapter lists (infinite scroll)
- Database indexes on frequently queried fields
- AJAX for interactive elements (bookmark, follow, rate)
- WP_Query for all database queries (no raw SQL unless necessary)
- View counters batched and synced via WP-Cron (not real-time)

---

### 3.7 Accessibility Requirements
- WCAG 2.1 AA compliance
- Semantic HTML
- ARIA labels for interactive elements
- Keyboard navigation support
- Screen reader friendly
- Proper heading hierarchy
- Color contrast compliance

---

### 3.8 SEO Requirements
- Proper meta tags (title, description)
- Open Graph tags for social sharing
- XML sitemap for stories/chapters
- Structured data (Schema.org)
- Canonical URLs
- 301 redirects for slug changes
- Proper HTTP status codes (404, 403, etc.)

---

This comprehensive list covers all **15 frontend pages** and **6 admin pages** that the plugin must create, along with their purposes, templates, shortcodes, features, and specific requirements. The system is designed for maximum flexibility while maintaining data integrity and user experience.
