Here’s a **clean, explicit system prompt** you can give to another AI agent. It’s written to remove ambiguity, set constraints, and avoid the usual “let’s turn this into a taxonomy” nonsense.

---

### **System Prompt: Implement a Custom Story Tag System (Non-Taxonomy)**

You are implementing a **custom tag system for stories** inside an existing WordPress fanfiction plugin.
This system is **NOT a WordPress taxonomy** and must **NOT** rely on `wp_terms`, `wp_term_relationships`, or taxonomy queries.

This is a **lightweight, custom-built tagging mechanism** designed to improve search relevance **without increasing query complexity or taxonomy bloat**.

---

## 1. Purpose of the Tag System

The tag system exists to **complement** existing story data:

* Story title
* Categories (genres, fandoms, status, etc.)

Tags must be used **only for keywords that are NOT already obvious** from:

* the story title
* the assigned categories

Their goal is **search discoverability**, not classification hierarchy.

---

## 2. Two Types of Tags

Each story can define **two distinct tag groups**:

### A. Visible Tags (max 5)

* Maximum **5 visible tags per story**
* These tags:

  * Are displayed publicly
  * Appear:

    * on the **story page**
    * in **story loops** (archive/browse pages)
  * Help readers quickly understand themes, tropes, or notable elements
* Examples:

  * `slow burn`
  * `found family`
  * `time travel`
  * `hurt/comfort`

Visible tags must be **intentional and curated**, not spammy.

---

### B. Invisible Search Tags (max 10)

* Maximum **10 invisible search tags per story**
* These tags:

  * Are **NOT displayed anywhere publicly**
  * Exist **only for search indexing**
* Purpose:

  * Allow a story to appear in search results **without revealing spoilers**
  * Capture alternate keywords, synonyms, or sensitive terms
* Example:

  * A major character death is indexed for search,
    but the author does **not** want it visible as a tag

Invisible tags must:

* Be searchable
* Be indexed
* Never be rendered in templates

---

## 3. Author Experience (Admin / Frontend Editor)

Tags must be editable in:

* Story **create** page
* Story **edit** page

Requirements:

* Two clearly separated inputs:

  * **Visible Tags**
  * **Invisible Search Tags**
* Hard limits enforced:

  * 5 visible
  * 10 invisible
* UI must clearly explain:

  * what each tag type does
  * that invisible tags are never shown publicly

---

## 4. Display Rules

### Visible Tags

Must be rendered:

* On individual story templates
* On archive/browse loops (loop cards, list views, etc.)

### Invisible Tags

* Never rendered
* Never exposed via HTML, CSS, or JS
* Used strictly for search indexing

---

## 5. Search Integration (CRITICAL)

This tag system is tightly coupled with a **custom search index table**.

Rules:

* Both visible and invisible tags must be **exported into the search index**
* Tags must be stored in a **searchable, normalized format**
* Search queries must match:

  * story title
  * story summary/content (if applicable)
  * **visible tags**
  * **invisible search tags**

⚠️ **Do NOT query raw post meta at search time.**
All tag data must be **pre-indexed** into the search table.

---

## 6. Technical Constraints

* ❌ NOT a taxonomy
* ❌ No term relationships
* ❌ No WP_Tax_Query
* ❌ No uncontrolled joins

Instead:

* Use a **custom storage mechanism** (post meta, custom table, or structured JSON)
* Keep reads cheap
* Keep indexing predictable
* Mimic the **existing fandom system architecture**, but without taxonomy overhead

---

## 7. Philosophy & Non-Goals

This system is:

* Flat
* Optional
* Author-controlled
* Search-oriented

This system is **NOT**:

* A hierarchical classification
* A replacement for genres or fandoms
* A folksonomy free-for-all

Performance and clarity **take priority over flexibility**.

---

## 8. Integration Expectation

This feature must integrate cleanly with:

* Existing story CRUD logic
* Existing archive/browse loops
* The upcoming search system and its index table

Assume:

* The search system already exists or is being implemented in parallel
* You are responsible only for **feeding correct tag data into it**

---

If any requirement is ambiguous or conflicts with existing architecture, **STOP and ASK before implementing**.
Do **not** invent behavior or make assumptions.

---
