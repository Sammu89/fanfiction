A story’s updated date **does not** refresh from story-level edits alone. It is computed from its chapters:

* **Story updated date = the most recent qualifying chapter update in that story**

Story-level changes that **do not** count as a true story update by themselves:

* story title
* introduction / summary
* status
* warnings
* fandoms, genres, language, tags
* notes or settings
* story publication date

What **does** update the story:

* adding a new chapter
* making a qualifying substantial edit to a chapter

If only story metadata changes, the story won’t move in “recently updated”.

---

### Chapter updated date requires meaningful chapter-content change

Each chapter has its own “true update date” check. A chapter is truly updated only when its **actual chapter body** changes in a meaningful way (not just any save).

Usually **does not** count:

* fixing one or two typos
* punctuation changes
* small wording tweaks
* tiny formatting cleanup
* changing only the publication date

**Does** count:

* adding a noticeable amount of new text
* rewriting or replacing a meaningful portion
* substantial revision to the chapter body

---

### What counts as “significant”

The plugin uses an internal threshold so minor edits won’t trigger an updated date:

* short chapters must change meaningfully overall
* long chapters typically need ~**10%+** content change

This is intentional: fixing typos passes are meant to stay quiet.

---

## Anti-bumping: date changes don’t trigger updates

The plugin blocks date-based bumping:

* changing only a story publish date doesn’t make it “newly updated”
* changing only a chapter date doesn’t make it “newly updated”
* pushing dates forward without real content change is not treated as a true update

---

## Read flags are version-aware

The chapter **Read** flag follows the same true-update logic:

* a chapter can be marked read for its current version
* if the chapter later receives a qualifying substantial update, the old read flag no longer applies
* the reader must read/mark it again for the new version

Minor edits that don’t qualify **do not** clear the read flag.

---

## New stories and new chapters

New items get an initial starting date so they exist normally in the system. After that:

* chapter recency is driven by meaningful chapter edits
* story recency is driven by chapter activity, not story metadata edits

---

## Practical examples

**Example 1 — minor edit**

* Fix three typos in Chapter 4 → save succeeds
* Chapter updated date **does not** change
* Story **does not** move in “recently updated”

**Example 2 — substantial edit**

* Add a new scene to Chapter 4
* Plugin recognizes a substantial content change
* Chapter updated date changes
* Story updated date changes
* Story can move up in “recently updated”

**Example 3 — metadata edit**

* Change only story summary or warnings
* Story saves successfully
* Story updated date **does not** change

