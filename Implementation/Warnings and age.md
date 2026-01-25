# IMPLEMENTATION PROMPT — FANFICTION WARNINGS & AGE RATING SYSTEM

## Context

This WordPress plugin already exists and implements a **custom classification system for Fandoms**, including:

* Admin CRUD interface
* Persistent storage
* Selection on story creation
* Display on story view

The **Warnings system must reuse the same architectural patterns** (data handling, admin UI logic, permissions, rendering).

This feature is strictly scoped to:

* defining warnings
* assigning them to stories
* deriving minimum age
* enforcing admin-level content restrictions
* displaying warnings

No taxonomies. No moderation queues. No recommendation logic.

---

## 1. Admin: Warnings Definition System

### 1.1 Admin Menu

Add a new admin page:

**Fanfic → Taxonomy → Warnings (TAB)**

This page mirrors the existing **Fandom management UI**.

---

### 1.2 Warning Data Model

Each **Warning** must contain:

| Field             | Type         | Required | Notes                         |
| ----------------- | ------------ | -------- | ----------------------------- |
| `id`              | string / int | yes      | Internal identifier           |
| `name`            | string       | yes      | Display name                  |
| `minimum_age`     | enum         | yes      | `PG`, `13+`, `16+`, `18+`     |
| `description`     | text         | yes      | User-facing explanation       |
| `is_sexual`       | boolean      | no       | Sexual but not pornographic   |
| `is_pornographic` | boolean      | no       | Explicitly pornographic       |
| `enabled`         | boolean      | yes      | Admin-controlled availability |

Warnings are stored persistently using the **same strategy as Fandoms** (custom table or structured plugin storage).

---

### 1.3 Default Warnings (Predefined)

On activation or migration, seed the following warnings:

+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Warning Name                          | Min Age     | Flags                                              | Short Description / Notes         |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Graphic Violence                      | 16+         | —                                                  | Detailed, bloody, or intense      |
|                                       |             |                                                    | depictions of fighting/injury     |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Character Death           | 13+         | —                                                  | Death of a major/important        |
|                                       |             |                                                    | character (can be emotional)      |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Sexual Violence                       | 18+         | is_sexual = true                                   | Rape, sexual assault, coercion    |
|                                       |             |                                                    | (non-consensual)                  |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Underage (sexual content)             | 18+         | is_sexual = true                                   | Sexual activity involving         |
|                                       |             |                                                    | characters under 18               |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Incest                                | 18+         | is_sexual = true                                   | Sexual content between family     |
|                                       |             |                                                    | members                           |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Non-explicit Sexual  Content          | 13+         | is_sexual = false                                   | Implied/non-explicit sexual scenes:|
|                                       |             |                                                    | fade-to-black, heavy kissing &    |
|                                       |             |                                                    | touching, sensuality without      |
|                                       |             |                                                    | graphic detail    |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Explicit Sexual Descriptions          | 16+         | is_sexual = true                                   | Detailed, graphic descriptions of |
|                                       |             |                                                    | sexual acts (not necessarily      |
|                                       |             |                                                    | "pornographic")                   |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Pornographic Sexual Content           | 18+         | is_sexual = true, is_pornographic = true           | Focus on explicit sex as main     |
|                                       |             |                                                    | purpose, highly detailed/erotic   |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Self-Harm                             | 16+         | —                                                  | Depictions or discussions of      |
|                                       |             |                                                    | cutting, burning, etc.            |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Suicide                               | 16+         | —                                                  | Suicide attempts, completion, or  |
|                                       |             |                                                    | heavy ideation                    |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Abuse                                 | 16+         | —                                                  | Physical, emotional, or           |
|                                       |             |                                                    | psychological domestic/family     |
|                                       |             |                                                    | abuse                             |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Substance Abuse                       | 16+         | —                                                  | Heavy/addictive drug or alcohol   |
|                                       |             |                                                    | use, addiction portrayal          |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Eating Disorders                      | 16+         | —                                                  | Anorexia, bulimia, binge eating,  |
|                                       |             |                                                    | body dysmorphia                   |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Miscarriage / Abortion    | 16+         | —                                                  | Detailed pregnancy loss,          |
|                                       |             |                                                    | termination, or traumatic birth   |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Torture                               | 18+         | —                                                  | Prolonged, sadistic infliction of |
|                                       |             |                                                    | pain/injury                       |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Blood / Gore                          | 16+         | —                                                  | Extreme blood, mutilation,        |
|                                       |             |                                                    | detailed injury descriptions      |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Animal Cruelty                 | 16+         | —                                                  | Abuse, killing, or suffering of   |
|                                       |             |                                                    | animals                           |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+
| Homophobia     | 16+         | —                                                  | Depictions of hate speech,        |
|                                       |             |                                                    | discrimination, slurs             |
+---------------------------------------+-------------+----------------------------------------------------+-----------------------------------+

Admins may:

* edit **name**
* edit **minimum age**
* edit **description**
* enable/disable warnings

Core warnings cannot be deleted, only disabled.

---

### 1.4 Custom Warnings

Admins may add **custom warnings** with:

* custom name
* custom minimum age
* custom description
* optional `is_sexual` / `is_pornographic` flags

Custom warnings behave identically to built-in ones.

---

## 2. Global Content Permissions (Critical)

### 2.1 Settings Location

Add to:

**Fanfic → Settings → General**

---

### 2.2 Content Toggles

Provide **two separate checkboxes**:

1. **Allow sexual content**

   * Default: **enabled**
   * Controls all warnings flagged `is_sexual = true`
   * Explain that sexual content might be in the form of a non-pornographic way (sexual mild desriptions of romance)

2. **Allow pornographic content**

   * Default: **disabled**
   * Controls all warnings flagged `is_pornographic = true`
   * Explain that this implies heavly descriptions of pornographic content

---

### 2.3 Enforcement Rules

* If **sexual content is disabled**:

  * All warnings with `is_sexual = true` are disabled
* If **pornographic content is disabled**:

  * All warnings with `is_pornographic = true` are disabled
* Disabled warnings:

  * cannot be selected on story creation
  * are hidden or visually disabled
  * existing stories using them may be blocked or forced to draft (implementation choice)

This mirrors how fandom availability is filtered globally.

---

## 3. Story Creation / Edit Screen

### 3.1 Content Mode Selection

At the top of the Warnings section, add:

**Content Rating Mode**

* ( ) **All Ages (PG)**
* ( ) **Contains Mature Content**

---

### 3.2 Mode Rules

* If **All Ages (PG)** is selected:

  * No warnings may be selected
  * Story minimum age is **PG**
* If **Contains Mature Content** is selected:

  * Warnings section becomes available

PG is an **explicit declaration**, not inferred.

---

### 3.3 Warnings Selection

If **Contains Mature Content**:

* Display all **enabled warnings**
* Multi-select allowed
* UI follows the same conventions as Fandom selection

There is **no “No Archive Warnings Apply” option**.

---

### 3.4 Derived Classification Logic

* If **PG is selected**:

  * Age = PG
* If **PG is NOT selected** and **no warnings are selected**:

  * Age is derived as **PG-equivalent (no warnings present)**
  * Internally treated as “no applicable warnings”
* If **warnings are selected**:

  * Age = `max(minimum_age of selected warnings)`

Users **never manually select age**.

---

## 4. Storage

* Selected warnings are stored on the story post
* Storage mechanism matches the existing Fandom system

Example:

```json
["explicit_sexual_content", "graphic_violence"]
```

Derived age may be:

* computed on the fly
* or cached as post meta (implementation choice)

---

## 5. Story View Page

### 5.1 Display

On the public story page:

* Display selected warnings
* Show:

  * warning name
  * description (tooltip or expandable)
* Optionally display derived minimum age badge

Warnings are read-only for readers.

---

### 5.2 No-Warning Case

If:

* PG is not selected
* No warnings are selected

Display:

> **Content Warnings: None declared**

This is informational only.

---

## 6. Architectural Consistency

The implementation **must reuse**:

* admin CRUD logic
* validation patterns
* permission checks
* UI components
* storage abstractions

Warnings are **parallel to Fandoms**, not taxonomies.

---

## 7. Explicit Non-Goals

* No WordPress taxonomies
* No user age verification
* No moderation workflow
* No automatic content detection
* No front-end filtering UI

---

## Success Criteria

The system is correct if:

* Sexual and pornographic content are independently controllable
* Sexual content is allowed by default
* Pornographic content is opt-in
* PG is explicit but not forced
* “No warnings” is derived, not selectable
* Stories always derive a minimum age
* Architecture matches existing Fandom system

---

This version is **tight, coherent, and implementation-safe**.
If you want, next I can:

* turn this into a **developer task checklist**
* convert it into **pseudo-code**
* or write a **DB schema matching WordPress conventions**

Just say which.
