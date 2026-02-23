Every story has a **status** that tells readers how active it is.

They have a built-in system behavior and trigger automatic rules. Any additional custom statuses you create act as simple labels — they do not trigger automation.

---

## The Four System Statuses

| Status    | Default Slug | Meaning                                            |
| --------- | ------------ | -------------------------------------------------- |
| Ongoing   | `ongoing`    | The story is actively being updated                |
| On Hiatus | `on-hiatus`  | Updates are paused, but the story is not abandoned |
| Abandoned | `abandoned`  | The story has not been updated for a long time     |
| Completed | `completed`  | The author has finished the story                  |

`on-hiatus` and `abandoned` are assigned automatically based on inactivity.

You can rename slugs under **Fanfiction → Taxonomies → Status**, but the four system statuses cannot be deleted.

---

## Automatic Status Changes

A daily background task checks all published stories and updates their status when needed.

### Ongoing → On Hiatus

A story moves from **Ongoing** to **On Hiatus** if it has not received a meaningful content update for **the configured hiatus threshold** (default: 4 months).

### On Hiatus → Abandoned

A story moves from **On Hiatus** to **Abandoned** if it has not received a meaningful content update for **the configured abandoned threshold** (default: 10 months).

Both periods are calculated from the **last content update**, not from the date the status was changed.

You can configure these thresholds under **Fanfiction → Settings → General** in the "Inactivity Thresholds" section.


---

## What Counts as a Content Update

The system only considers **chapter-level content changes** as valid updates:

* Publishing a new chapter
* Making significant edits to an existing chapter’s text and saving it

The following do **not** reset the inactivity timer:

* Changing the story title, description, or cover image
* Updating genres, warnings, tags, or other metadata
* Changing the publication date
* Saving the story without editing chapter content

This prevents superficial edits from keeping a story marked as Ongoing.

---

## Manual Status Changes

Authors can change a story’s status from the edit screen — with one important limitation.

### Restriction on Setting Ongoing

Authors cannot manually set a story to **Ongoing** if the last content update is older than the configured hiatus threshold (default: 4 months).

If the system has moved a story to **On Hiatus** or **Abandoned** due to inactivity, the author must publish or update chapter content before setting it back to Ongoing.

### When the Restriction Is Removed

* Publishing a new chapter automatically sets the status to `ongoing`.
* Making significant edits to an existing chapter allows the author to manually change the status back to Ongoing.

---

## Summary Table

| Current Status | Trying to Set | Recent update | Old update |
| -------------- | ------------- | ---------------------- | ---------------------- |
| On Hiatus      | Ongoing       | ✅ Allowed              | ❌ Blocked              |
| Abandoned      | Ongoing       | ✅ Allowed              | ❌ Blocked              |
| Any            | Completed     | ✅ Allowed       | ✅ Allowed       |
| Any            | On Hiatus     | ✅ Allowed       | ✅ Allowed       |
| Any            | Abandoned     | ✅ Allowed       | ✅ Allowed       |

---

## Background Processing

The plugin processes up to **200 stories per run** to avoid performance issues on slower servers.

If more than 200 stories need to be checked, the system schedules another run 1 minute later and continues until all eligible stories are evaluated.

You can adjust the scheduled run time under **Fanfiction → Settings → General**.

---

## Administrator Override

`Administrators` and `Moderators` can always set any status on any story, bypassing all restrictions.

The Ongoing restriction only applies to users with the `fanfiction_author` role.
