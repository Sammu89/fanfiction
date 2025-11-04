# User Roles & Permissions

The plugin defines distinct user roles, each with specific capabilities. Roles build progressively (e.g., higher roles inherit lower ones).

## Roles

**Normal, Non-Logged-In User**
- Not a user role per se.
- Can rate chapters (one rating per IP), comment on content (if they leave their email).

**Fanfic_Reader**
- Default role for registered users who haven't published stories.
- Can bookmark stories, follow authors, rate chapters, comment on content.
- Can manage their personal profile (bio and avatar URL, no file uploads).
- Can view their dashboard (bookmarks, reading history, followed authors).
- Can control notification preferences.

**Fanfic_Author**
- Automatically assigned when a user publishes their first validated story.
- Can create, edit, and delete their own stories and chapters.
- Can manage their complete profile.
- A daily WP-Cron job checks if Fanfic_Author users have zero published stories. If so, they are demoted to Fanfic_Reader, regardless of draft stories. Draft stories remain accessible in the author’s dashboard but do not prevent demotion.
- If a user with Fanfic_Reader role publishes a valid story, they are automatically assigned the Fanfic_Author role. This applies to both first-time publishers and users previously demoted to Fanfic_Reader by the daily WP-Cron job (e.g., after deleting all published stories).
- Inherits all Fanfic_Reader permissions (can still bookmark, follow, comment on other stories).

**Fanfic_Mod**
- Can view and edit any story or chapter on the platform (with edit stamps showing who modified what).
- Can access the moderation queue to review, approve, or delete reported content (WordPress backend).
- Can suspend or unsuspend users (by changing their role to Fanfic_Banned_Users) via the user profile with "Ban user" link.
- Can view author profiles and manage platform user list.
- Inherits all Fanfic_Author and Fanfic_Reader permissions.

**Fanfic_Admin**
- Full access to plugin settings and configuration.
- Can manage custom taxonomies (create, edit, delete).
- Can configure notification templates and preferences.
- Can manage URL slugs and rewrite rules.
- Can add custom CSS for the plugin.
- Can access all moderation functions.
- Inherits all Fanfic_Mod permissions.
- WordPress admins are automatically Fanfic_Admin, but the inverse might not be true.

**Fanfic_Banned_Users**
- Special role assigned when a moderator or admin suspends a user.
- Suspended users can still log in and view their own stories (read-only, in their dashboard on story listing).
- A persistent sticky notice displays: "Your account has been suspended".
- Cannot access the WordPress admin dashboard at all.
- Their stories do not appear in public listings, archives, or search results (except to mods/admins).
- Cannot create, edit, or delete any content.
- Cannot appear in "most followed authors" lists.
- Banned users (Fanfic_Banned_Users role) cannot receive notifications (e.g., for comments, followers, or followed authors’ chapters). Their stories, hidden from public view (except to mods/admins and themselves), do not allow new comments. Existing comments remain visible to the banned user and mods/admins but cannot be added to.

## Permissions Granularity
- No content-level permissions (e.g., mod can edit stories but not suspend users)—admins and mods can both suspend users.
- No different "mod levels" (junior mod, senior mod)—all same level.
- Authors cannot invite co-authors to collaborate on stories (idea for upgrade—see ideas-for-upgrade.md).
- No story permissions (private/friends-only/public).