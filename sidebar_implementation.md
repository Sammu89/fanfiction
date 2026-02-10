# Sidebar Implementation (Future Base)

## Purpose
Define a simple, theme-agnostic sidebar system for Fanfiction pages that works on:
- Classic themes (Widgets API)
- Block/FSE themes (Template Parts)

This is a base planning doc for later implementation.

## Goals
- Let admins decide where sidebars appear (per page context).
- Provide pre-created sidebar contexts out of the box.
- Keep behavior consistent across classic and FSE themes.
- Use predictable fallback rules so pages never break.

## Non-Goals (MVP)
- No advanced visual builder inside plugin.
- No per-user sidebar personalization.
- No dynamic sidebar logic by taxonomy/author in first version.

## Pre-Created Sidebar Contexts
- `general` (global fallback)
- `story_view`
- `chapter_view`
- `story_archive`
- `homepage`

Optional later:
- `dashboard`
- `profile_view`
- `story_edit`
- `chapter_edit`

## Theme Compatibility Model

### Classic Themes
- Register WordPress widget areas (one per context).
- Render with `dynamic_sidebar( $sidebar_id )`.

Example widget area IDs:
- `fanfiction-sidebar-general`
- `fanfiction-sidebar-story-view`
- `fanfiction-sidebar-chapter-view`
- `fanfiction-sidebar-story-archive`
- `fanfiction-sidebar-homepage`

### Block/FSE Themes
- Use template parts (one per context), example slugs:
- `fanfic-sidebar-general`
- `fanfic-sidebar-story-view`
- `fanfic-sidebar-chapter-view`
- `fanfic-sidebar-story-archive`
- `fanfic-sidebar-homepage`

Render strategy:
- Prefer context template part.
- If missing, fallback to `fanfic-sidebar-general`.
- If still missing, fallback to classic widget area (if active).
- If nothing exists, render no sidebar.

## User Experience (Admin)
Two decisions:
1. **Where sidebar appears** (visibility per context, ON/OFF)
2. **What sidebar content is used** (context-specific or general fallback)

Recommended settings screen sections:
- `Visibility by Context`
- `Content Source`
  - `Auto (recommended)` -> context first, then general
  - `Always General`
  - `Disabled`
- Quick links:
  - Classic: "Manage Widgets"
  - FSE: "Edit Template Parts"

## Data Model (Options)
Use options; keep simple and serializable.

- `fanfic_sidebar_enabled` (`'1'|'0'`)
- `fanfic_sidebar_visibility` (array)
  - keys: context names
  - values: `'1'|'0'`
- `fanfic_sidebar_mode` (`auto|general_only|disabled`)

Example:
```php
array(
  'story_view'    => '1',
  'chapter_view'  => '0',
  'story_archive' => '1',
  'homepage'      => '1',
  'general'       => '1',
)
```

## Context Resolution
Map runtime template/context to one logical context key.

Suggested mapping:
- `template-story-view.php` -> `story_view`
- `template-chapter-view.php` -> `chapter_view`
- `template-story-archive.php` -> `story_archive`
- Main page in stories-homepage mode -> `homepage`
- Unknown context -> `general`

## Render Algorithm (MVP)
1. If `fanfic_sidebar_enabled = 0`, do not render.
2. Resolve current context key.
3. If visibility for context is OFF, do not render.
4. Resolve sidebar source:
   - If `disabled`, do not render.
   - If `general_only`, render general.
   - If `auto`, render context then fallback to general.
5. Execute theme-specific renderer:
   - FSE: try template part(s)
   - Classic: try widget area(s)
6. If nothing active, render no sidebar and apply no-sidebar layout class.

## Layout Rules
- If sidebar is rendered: add class `fanfiction-with-sidebar`.
- If not rendered: add class `fanfiction-no-sidebar`.
- Keep existing responsive stack behavior on mobile.

## Backward Compatibility
- Existing option: `fanfic_show_sidebar` should map to new system.
- Existing widget area `fanfiction-sidebar` should be treated as legacy general fallback.
- Migration rule on first run:
  - If `fanfic_show_sidebar = 1`, set new system enabled and default visibility ON for all core contexts.
  - Preserve old sidebar content as fallback where possible.

## Installation Defaults
Recommended defaults after install:
- Sidebar enabled: ON
- Visibility:
  - `story_view`: ON
  - `chapter_view`: ON
  - `story_archive`: ON
  - `homepage`: OFF
  - `general`: ON
- Mode: `auto`

## Phased Implementation Plan

### Phase 1: Core Infrastructure
- Add context constants and resolver function.
- Register multiple classic widget areas.
- Add runtime render helper with fallback chain.

### Phase 2: Wrapper Integration
- Replace single hardcoded sidebar render in wrapper.
- Apply context-aware render decision and layout class.

### Phase 3: Admin Settings UI
- Add sidebar settings section (visibility + mode).
- Add contextual help links for Widgets / Template Parts.

### Phase 4: Migration + QA
- Add migration from legacy `fanfic_show_sidebar`.
- Test classic theme + FSE theme behavior.
- Validate no warnings, no broken layout, no fatal errors.

## QA Checklist
- Classic theme:
  - Context-specific widget area appears correctly.
  - General fallback works when context area is empty.
- FSE theme:
  - Context template part renders correctly.
  - General template part fallback works.
- Disabled contexts do not show sidebar.
- Mobile layout stacks correctly.
- No regression in story/chapter edit pages.

## Open Questions
- Should homepage default ON or OFF?
- Should edit forms have their own sidebar contexts in MVP?
- Should we expose per-context width controls now or later?

