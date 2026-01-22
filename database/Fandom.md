

# Feature Specification — **Fandom Classification System (Lean, Scalable, Boring-in-a-Good-Way)**

## 1. Intent

Optional fandom classification for fanfiction stories that:

* scales cleanly to ~3,500 fandom entries
* avoids `wp_terms` / WordPress taxonomies entirely
* uses **custom tables only**
* provides **fast AJAX/REST autocomplete**
* supports **multi-fandom stories**
* supports a **mutually exclusive “Original Work” mode**
* loads **nothing** when disabled

Performance > ideology. Always.

The database file is here: "C:\Users\Sammu\Local Sites\teste\app\public\wp-content\plugins\fanfiction\database\fandoms.json"
and should be used to build the database. Its divided by categories.

---

## 2. Activation Toggle

**Setting**

```
enable_fandom_classification (bool)
```

**Location**
Admin → Fanfic Settings → Taxonomies

Also activated by default on Wizard (on same page were user choses genre and status)

### Behavior

If **disabled**:

* No editor UI (no metabox / no Gutenberg sidebar)
* No scripts or styles enqueued
* No REST routes registered
* No queries executed
* Existing DB data untouched

If **enabled**:

* Editor UI enabled
* Fandom catalogue admin enabled
* Search endpoint registered
* Fandom tab appears under Admin → Fanfic Settings → Taxonomies

Everything is gated early. No “just in case” loading.

---

## 3. Data Model (Custom Tables)

### 3.1 Fandom Master Table

**Table**

```
{$wpdb->prefix}fanfic_fandoms
```

**Purpose**
Stores the global fandom catalogue.

| Column    | Type                  | Notes                    |
| --------- | --------------------- | ------------------------ |
| id        | BIGINT UNSIGNED PK AI | Internal identifier      |
| slug      | VARCHAR(191) NOT NULL | Stable internal key      |
| name      | VARCHAR(255) NOT NULL | Admin-facing label       |
| category  | VARCHAR(191) NOT NULL | Category slug            |
| is_active | TINYINT(1) NOT NULL   | Soft disable (default 1) |

**Indexes**

* `UNIQUE (slug)`
* `INDEX (is_active)`
* `INDEX (category)`
* `INDEX (name)` — helps prefix LIKE
* `FULLTEXT (name)` — optional, used for fallback search

**Collation (important)**

Use an accent- and case-insensitive collation, e.g.:

```
utf8mb4_unicode_ci
```

or, if available:

```
utf8mb4_0900_ai_ci
```

This guarantees:

* `pokemon` = `Pokémon`
* `STAR WARS` = `Star Wars`

No app-level normalization needed.

---

### 3.2 Story ↔ Fandom Relation Table

**Table**

```
{$wpdb->prefix}fanfic_story_fandoms
```

| Column    | Type                     | Notes      |
| --------- | ------------------------ | ---------- |
| story_id  | BIGINT UNSIGNED NOT NULL | WP post ID |
| fandom_id | BIGINT UNSIGNED NOT NULL | fandom ID  |

**Indexes**

* `UNIQUE (story_id, fandom_id)`
* `INDEX (story_id)`
* `INDEX (fandom_id, story_id)`

No ordering, no metadata, no fluff.

---

### 3.3 Original Work Flag (Post Meta)

**Meta key**

```
_fanfic_is_original_work
```

**Values**

* `'1'` → original work
* missing → not original

**Rules**

* If `_fanfic_is_original_work = 1`:

  * the story **must have zero fandom relations**
* Original Work is **not** a fandom row
* It is a **mode**, not a category

Why this is good:

* Zero special rows
* Zero “fake fandom”
* One source of truth
* Trivial editor checkbox
* Fast reads (`get_post_meta` is cached)

---

## 4. Admin UX — Fandom Catalogue

**Menu**
Admin → Fanfic → Taxonomu - Fandoms
Inside the page they are divided by their categories
### Capabilities

* Required capability: `manage_options`
* All actions protected by nonces

### Features

Admins can:

* Add fandom (name + slug)
* Add category and move fandoms between categories
  * category slug auto-suggested, editable
* Rename fandom (`name` only)
* Soft disable fandom (`is_active = 0`)
* Search fandoms (server-side)

Admins can:

* Hard delete fandoms (stories that have a non existing fandom will default to "Unknown") but admin has a warning before confirming deletion

---

## 5. Author UX — Story Editor

Displayed only when:

* fandom classification enabled
* correct CPT (e.g. `fanfic_story`)
* user can edit the post
* Appears below genre and status, user can select several fandoms up to 5 via search box

---

### A) Checkbox — “Original Work”

* Label configurable via plugin strings/settings
* When checked:

  * disables fandom selector UI
  * clears any selected fandoms visually
* When unchecked:

  * fandom selector enabled

This is strict mutual exclusivity.

---

### B) Fandom Multi-Select (AJAX autocomplete)

* Shown only if Original Work is unchecked
* Debounced (200–300 ms)
* Max results: 20
* Never loads full list
* Only active fandoms returned
* Max 5 selectable

No “Original Work” appears here. Ever.

---

## 6. Search Endpoint (REST, Lean)

**Route**

```
/wp-json/fanfic/v1/fandoms/search
```

### Parameters

| Param | Type   | Rules          |
| ----- | ------ | -------------- |
| q     | string | min length = 2 |
| limit | int    | clamp 1–20     |

### Access Control

* User must be logged in
* Must have `edit_posts` (or CPT-specific cap)
* REST nonce required

---

### Query Strategy (Fast + Scalable)

#### Step 1 — Prefix search (cheap, indexed)

```sql
SELECT id, name
FROM wp_fanfic_fandoms
WHERE is_active = 1
  AND name LIKE CONCAT(%s, '%%')
LIMIT %d
```

#### Step 2 — Fallback (only if needed)

Only if:

* fewer than `limit` results
* `strlen(q) >= 3`
* FULLTEXT index exists

```sql
SELECT id, name
FROM wp_fanfic_fandoms
WHERE is_active = 1
  AND MATCH(name) AGAINST (%s IN NATURAL LANGUAGE MODE)
LIMIT remaining
```

No `%term%` scans. Ever.

Accent + case handling is done by collation, not PHP hacks.

---

### Response

```json
[
  { "id": 12, "label": "Star Wars" },
  { "id": 18, "label": "Stargate" }
]
```

Minimal. UI-friendly.

---

## 7. Saving Logic (Simple, Safe)

### Conditions

Only run on save if:

* feature enabled
* correct CPT
* not autosave / not revision
* user can edit post
* nonce verified

---

### Inputs

* `_fanfic_is_original_work` (checkbox)
* `fandom_ids[]` (array of ints)

---

### Validation + Save Rules

#### Case 1 — Original Work checked

* Save post meta `_fanfic_is_original_work = 1`
* **Delete all fandom relations** for the story
* Ignore `fandom_ids`

One query. Done.

---

#### Case 2 — Original Work unchecked

* Delete `_fanfic_is_original_work` meta
* Validate `fandom_ids`:

  * integers
  * unique
  * optional max count (e.g. 5–10)
  * exist in fandom table
  * `is_active = 1`

**Saving strategy (intentionally boring):**

1. Delete all existing relations for the story
2. Bulk insert validated fandom IDs

Why:

* fandom count per story is tiny
* saves are rare
* simpler = fewer bugs

No diff gymnastics unless profiling proves otherwise.

---

## 8. Frontend Reads

### Determine Original Work

```php
$is_original = get_post_meta($post_id, '_fanfic_is_original_work', true);
```

If true:

* display “Original Work” label
* skip fandom query entirely

---

### Fetch Story Fandoms

Only if not original:

```sql
SELECT f.id, f.name, f.slug
FROM wp_fanfic_fandoms f
JOIN wp_fanfic_story_fandoms sf
  ON sf.fandom_id = f.id
WHERE sf.story_id = ?
  AND f.is_active = 1
ORDER BY f.name ASC
```

If a relation points to a missing fandom row, render a placeholder label of "Unknown" (translatable with `__()`), instead of failing hard.

No joins to WP tables. Predictable. Cacheable.

---

## 9. Data Integrity — Cheap Guardrails

* On story delete:

  * optionally delete related rows (cleanup hook)
* On fandom disable:

  * relations kept (historical)
  * frontend ignores inactive fandoms
* On save:

  * invalid fandom IDs silently dropped
* On plugin activation / upgrade:

  * ensure tables + indexes exist
  * FULLTEXT index created only if DB supports it

No foreign keys. No drama.

---

## 10. Performance Rules (Hard)

* No WP taxonomies
* No preloading lists
* No `%term%` queries
* Min 2 chars for search
* Max 20 results
* Scripts/styles only enqueued when:

  * feature enabled
  * relevant admin screen
