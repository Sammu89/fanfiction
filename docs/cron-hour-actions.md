# Daily Cron Hour: Actions and Hooks

## Purpose
This document explains which maintenance jobs run at the user-configured **Daily Cron Hour** (`fanfic_settings[cron_hour]`) and what each job does.

## Daily Cron Hour Source
- Setting key: `fanfic_settings[cron_hour]`
- Allowed range: `0-23`
- Admin UI: General Settings -> **Daily Cron Hour**

## Jobs That Run on `cron_hour`

All jobs below now use:
- Staggered start offsets (not all at minute `:00`)
- Lock guards to avoid concurrent duplicate workers
- Continuation single-events so large workloads finish in background across multiple requests
- Adaptive per-request time budgets based on PHP `max_execution_time` (keeps workers under host limits)

### 1) Author Auto-Demotion
- Hook: `fanfic_daily_author_demotion`
- Continuation hook: `fanfic_daily_author_demotion_continue`
- Class: `Fanfic_Author_Demotion`
- File: `includes/class-fanfic-author-demotion.php`
- Frequency: daily
- Start offset: `+0 min` from `cron_hour`
- What it does:
  - Scans users with role `fanfiction_author`
  - If author has `0` published `fanfiction_story` posts, demotes role
  - Stores demotion metadata

### 2) Orphaned Media Cleanup
- Hook: `fanfic_cleanup_orphaned_media`
- Continuation hook: `fanfic_cleanup_orphaned_media_continue`
- Class: `Fanfic_Media_Cleanup`
- File: `includes/class-fanfic-media-cleanup.php`
- Frequency: daily
- Start offset: `+20 min` from `cron_hour`
- What it does:
  - Scans image attachments
  - Deletes images not referenced by uploader story/chapter/avatar data
  - Timeboxed batches with continuation to reduce long runtime risk

### 3) Expired Transient Cleanup
- Hook: `fanfic_cleanup_transients`
- Continuation hook: `fanfic_cleanup_transients_continue`
- Class: `Fanfic_Cache_Admin`
- File: `includes/admin/class-fanfic-cache-admin.php`
- Frequency: daily
- Start offset: `+30 min` from `cron_hour`
- What it does:
  - Removes expired plugin transients
  - Logs cleanup stats in options for admin visibility

### 4) Old Notifications Cleanup
- Hook: `fanfic_cleanup_old_notifications`
- Continuation hook: `fanfic_cleanup_old_notifications_continue`
- Class: `Fanfic_Notifications`
- File: `includes/class-fanfic-notifications.php`
- Frequency: daily
- Start offset: `+40 min` from `cron_hour`
- What it does:
  - Deletes notifications older than 90 days from `fanfic_notifications` in bounded batches
  - Writes cleanup log message when deletions occur

### 5) Ratings/Likes Anonymization
- Hook: `fanfic_anonymize_old_data`
- Continuation hook: `fanfic_anonymize_old_data_continue`
- Class: `Fanfic_Cron_Cleanup`
- File: `includes/class-fanfic-cron-cleanup.php`
- Frequency: daily
- Start offset: `+50 min` from `cron_hour`
- What it does:
  - Clears `identifier_hash` for anonymous ratings/likes older than 30 days in resumable batches
  - Invalidates related chapter/story rating-like caches

### 6) Story Status Inactivity Automation
- Hook: `fanfic_auto_transition_story_statuses`
- Continuation hook: `fanfic_auto_transition_story_statuses_continue`
- Class: `Fanfic_Story_Status_Automation`
- File: `includes/class-fanfic-story-status-automation.php`
- Frequency: daily
- Start offset: `+10 min` from `cron_hour`
- What it does:
  - Uses search tables (`fanfic_story_search_index` + `fanfic_story_filter_map`) to detect stale stories without per-story query fan-out
  - If status facet is `ongoing` and `updated_date` is older than 4 months, switches status to `on-hiatus`
  - If status facet is `on-hiatus` and `updated_date` is older than 10 months, switches status to `abandoned`
  - Rebuilds the story search index row after each transition to keep search/filter data synced

## Reschedule Behavior on Hour Change
When `fanfic_settings` updates and `cron_hour` changes, these jobs re-schedule to the new hour:
- `fanfic_daily_author_demotion`
- `fanfic_cleanup_orphaned_media`
- `fanfic_cleanup_transients`
- `fanfic_cleanup_old_notifications`
- `fanfic_anonymize_old_data`
- `fanfic_auto_transition_story_statuses`

Continuation hooks are also cleared/reused safely to avoid duplicate workers.
Continuation events are scheduled approximately every 1 minute while work remains.

## Legacy/Duplicate Cleanup
- Removed duplicate daily demotion scheduler:
  - Legacy hook: `fanfic_daily_user_role_check`
  - Demotion is now centralized in `fanfic_daily_author_demotion`

## Notes
- WP-Cron runs when traffic hits the site. If the site has low traffic, tasks may run later than exact hour.
- The "Run Cron Tasks Now" button triggers `fanfic_daily_maintenance` as a manual trigger path; it is separate from daily scheduled hooks above.
