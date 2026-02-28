The translated story system allows an author to link multiple language versions of the same work.

When a user writes the same story in different languages, those versions can be grouped together as translations. This allows readers to move between language versions while keeping important classification data aligned.

---

## Reader-Facing Behavior

From the reader’s perspective:

* A story displays available translations.
* Readers can switch between language versions.
* Core classification (genres, warnings, fandoms) remains consistent across linked versions.


---

## What Happens When a User Links Translations

When a user links stories as translations:

* Readers can see the available language versions.
* Shared classification fields remain synchronized across all linked versions.

Each story remains an independent post, but they are treated as language variants of the same work.

---

## Shared (Synchronized) Fields

Within a translation group, the following fields are synchronized:

* Genres
* Warnings
* Fandoms
* Original Work flag

When a user saves one linked story and modifies any of these fields, the system propagates those changes to the other linked stories in the same group.

There is no permanent master story. The most recently saved story becomes the source of truth for shared fields at that moment.

---

## Independent (Non-Synchronized) Fields

The following fields remain independent between translations:

* Status (ongoing, complete, etc.)
* Tags
* Search tags
* Language
* Custom taxonomies defined by the site owner

This allows different language versions to progress at different publishing stages and use language-appropriate tagging.

---

## Linking Conditions

A user can link stories as translations only when:

* Both stories belong to the same author.
* Each story has a language assigned.
* The languages are different.
* The translation group does not already contain a story in the same language.

Each translation group may contain only one story per language.

---

## Unlinking Stories

If a user removes a story from a translation group:

* Synchronization stops immediately.
* Each story retains the shared field values it had at the time of unlinking.
* From that point forward, the stories behave independently.