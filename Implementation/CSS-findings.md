# CSS Layout Audit: Story Editor Page

## Scope
- Objective: identify what currently controls editor layout so it can later be replaced with CSS Grid.
- Constraint followed: reporting only layout-related CSS (no refactor, no edits to existing files).
- Files inspected:
  - `templates/template-story-form.php`
  - `templates/fanfiction-page-template.php`
  - `assets/css/fanfiction-frontend.css`
  - `includes/class-fanfic-core.php` (runtime inline CSS affecting layout width)

## Main Editor Wrapper
- Markup wrapper on the story editor template:
  - `.fanfic-content-layout` in `templates/template-story-form.php:392`
- Direct children:
  - `.fanfic-content-primary` in `templates/template-story-form.php:394`
  - `.fanfic-content-sidebar` in `templates/template-story-form.php:1174`
- CSS presence for those three selectors:
  - No direct CSS rules found for `.fanfic-content-layout`, `.fanfic-content-primary`, or `.fanfic-content-sidebar` in plugin stylesheet.

## Structural Layout Rules Affecting the Editor

### A) Page Shell Layout (outside editor wrapper but controls page structure)
- `body.fanfiction-page .fanfiction-page-wrapper` (`assets/css/fanfiction-frontend.css:3834`)
  - `display: flex;`
  - `flex-wrap: wrap;`
  - `gap: 2rem;`
  - `margin: 0 auto;`
  - `width: 100%;`
- `body.fanfiction-page .fanfiction-page-wrapper .fanfiction-page-main` (`assets/css/fanfiction-frontend.css:3843`)
  - `flex: 1 1 0%;`
  - `min-width: 0;`
- `body.fanfiction-page .fanfiction-page-wrapper .fanfiction-sidebar` (`assets/css/fanfiction-frontend.css:3849`)
  - `flex: 0 0 300px;`
  - `min-width: 250px;`
- `body.fanfiction-page.fanfic-no-sidebar .fanfiction-page-wrapper` (`assets/css/fanfiction-frontend.css:3855`)
  - `display: block;`
- `body.fanfiction-page.fanfic-no-sidebar .fanfiction-page-main` (`assets/css/fanfiction-frontend.css:3859`)
  - `max-width: 100%;`

### B) Runtime Width Controls (inline CSS)
- Generated in `includes/class-fanfic-core.php:1157`+ and attached to `#fanfiction-wrapper`:
  - Pixel mode (`includes/class-fanfic-core.php:1169`)
    - `max-width: {value}px;`
    - `width: 100%;`
    - `margin: 0 auto;`
    - `padding-left: 1rem;`
    - `padding-right: 1rem;`
    - `box-sizing: border-box;`
  - Percent mode (`includes/class-fanfic-core.php:1179`)
    - `width: {value}%;`
    - `margin: 0 auto;`
    - `padding-left: 1rem;`
    - `padding-right: 1rem;`
    - `box-sizing: border-box;`

### C) Editor Internal Structural Rules (within story form content)
- `.fanfic-content-section` (`assets/css/fanfiction-frontend.css:3178`)
  - `margin-bottom: 30px;`
  - `padding: 25px;`
  - section box styling (background/radius)
- `.fanfic-form-field` (`assets/css/fanfiction-frontend.css:644`)
  - `margin-bottom: 20px;`
- `.fanfic-form-field label` (`assets/css/fanfiction-frontend.css:650`)
  - `display: block;`
- `.fanfic-form-field input[type="text"], ... select, textarea` (`assets/css/fanfiction-frontend.css:656`)
  - `width: 100%;`
  - `box-sizing: border-box;`
- `.fanfic-form-header` (`assets/css/fanfiction-frontend.css:884`)
  - `display: flex;`
  - `align-items: center;`
  - `gap: 15px;`
  - `flex-wrap: wrap;`
- `.fanfic-form-actions` (`assets/css/fanfiction-frontend.css:1077`)
  - `display: flex;`
  - `gap: 10px;`
  - `margin-top: 20px;`
  - `flex-wrap: wrap;`
- `.fanfic-form-actions button` (`assets/css/fanfiction-frontend.css:1086`)
  - `flex: 1;`
  - `min-width: 150px;`

### D) Editor Component Layout Rules (inside form fields)
- Genres layout:
  - `.fanfic-checkboxes-grid` (`assets/css/fanfiction-frontend.css:858`)
    - `display: grid;`
    - `grid-template-columns: repeat(3, 1fr);`
    - `gap: 10px 15px;`
- Fandom component:
  - `.fanfic-fandom-result` (`assets/css/fanfiction-frontend.css:5223`)
    - `display: block;`
    - `width: 100%;`
  - `.fanfic-selected-fandoms` (`assets/css/fanfiction-frontend.css:5250`)
    - `display: flex;`
    - `flex-wrap: wrap;`
    - `gap: 6px;`
- Co-authors component:
  - `.fanfic-coauthors-field .fanfic-coauthor-results` (`assets/css/fanfiction-frontend.css:5273`)
    - `display: none;`
    - `max-height: 220px;`
    - `overflow-y: auto;`
  - `.fanfic-coauthors-field .fanfic-coauthor-results:not(:empty)` (`assets/css/fanfiction-frontend.css:5283`)
    - `display: block;`
  - `.fanfic-coauthor-result` (`assets/css/fanfiction-frontend.css:5287`)
    - `display: flex;`
    - `align-items: center;`
    - `gap: 0.5em;`
    - `width: 100%;`
  - `.fanfic-selected-coauthors` (`assets/css/fanfiction-frontend.css:5322`)
    - `display: flex;`
    - `flex-wrap: wrap;`
    - `gap: 0.35em;`
- Translations component:
  - `.fanfic-translations-field .fanfic-translation-results` (`assets/css/fanfiction-frontend.css:6032`)
    - `display: none;`
    - `max-height: 200px;`
    - `overflow-y: auto;`
  - `.fanfic-translations-field .fanfic-translation-results:not(:empty)` (`assets/css/fanfiction-frontend.css:6042`)
    - `display: block;`
  - `.fanfic-translation-result` (`assets/css/fanfiction-frontend.css:6046`)
    - `display: block;`
    - `width: 100%;`
  - `.fanfic-selected-translations` (`assets/css/fanfiction-frontend.css:6071`)
    - `display: flex;`
    - `flex-wrap: wrap;`
    - `gap: 0.3em;`
- Featured image / dropzone:
  - `.fanfic-image-dropzone` (`assets/css/fanfiction-frontend.css:5705`)
    - `position: relative;`
    - `display: flex;`
    - `align-items: center;`
    - `justify-content: center;`
  - `.fanfic-dropzone-preview` (`assets/css/fanfiction-frontend.css:5794`)
    - `position: relative;`
  - `.fanfic-dropzone-remove` (`assets/css/fanfiction-frontend.css:5809`)
    - `position: absolute;`
    - `width: 28px;`
  - `.fanfic-has-dropzone .fanfic-input[type="url"]` (`assets/css/fanfiction-frontend.css:5840`)
    - affects URL input spacing inside image field

### E) Float / Clear / Column Rules Relevant to Editor Context
- No `column-*` rules found for editor wrapper/form selectors.
- No float-based editor container layout found.
- Float-clearing helper on page content:
  - `body.fanfiction-page .entry-content::after` (`assets/css/fanfiction-frontend.css:3893`)
    - `display: table; clear: both;`

## Media Query Rules Affecting Layout in Editor Context
- `@media (max-width: 768px)` page shell stacking (`assets/css/fanfiction-frontend.css:3867`)
  - `.fanfiction-page-wrapper { flex-direction: column; }`
  - `.fanfiction-sidebar { flex: 1 1 auto; width: 100%; }`
- `@media (max-width: 768px)` section padding (`assets/css/fanfiction-frontend.css:3354`)
  - `.fanfic-content-section { padding: 20px 15px; }`
- `@media (max-width: 480px)` section padding (`assets/css/fanfiction-frontend.css:3392`)
  - `.fanfic-content-section { padding: 15px; }`
- `@media (max-width: 768px)` genres grid (`assets/css/fanfiction-frontend.css:920`)
  - `.fanfic-checkboxes-grid { grid-template-columns: repeat(2, 1fr); }`
- `@media (max-width: 480px)` genres grid (`assets/css/fanfiction-frontend.css:926`)
  - `.fanfic-checkboxes-grid { grid-template-columns: 1fr; }`
- `@media (max-width: 768px)` dropzone sizing (`assets/css/fanfiction-frontend.css:5847`)
  - reduces min-height/image max-height in dropzone.

## Layout Type Classification
- Result: **Hybrid**
  - Page shell is **flex-based** (`.fanfiction-page-wrapper`).
  - Editor wrapper (`.fanfic-content-layout`) itself has **no direct CSS layout rule** and therefore behaves as linear block flow by default.
  - Multiple nested editor components use flex/grid (`.fanfic-form-actions`, `.fanfic-form-header`, `.fanfic-checkboxes-grid`, selected chips lists).
  - Not float-based for primary editor layout.

## Selectors Likely to Conflict with Adding Grid to the Editor Wrapper

Target under consideration:
```css
display: grid;
grid-template-columns: repeat(2, minmax(0, 1fr));
gap: 2rem;
```

Potential conflicts or interactions:
- `body.fanfiction-page .fanfiction-page-wrapper` (`assets/css/fanfiction-frontend.css:3834`)
  - existing flex page shell + its `gap: 2rem`.
- `body.fanfiction-page.fanfic-no-sidebar .fanfiction-page-wrapper` (`assets/css/fanfiction-frontend.css:3855`)
  - switches shell to `display: block`.
- `@media (max-width: 768px)` shell override (`assets/css/fanfiction-frontend.css:3867`)
  - forces column flow on page shell.
- `body.fanfiction-page .fanfiction-page-wrapper .fanfiction-page-main` (`assets/css/fanfiction-frontend.css:3843`)
  - flex sizing context for main content container.
- `body.fanfiction-page .fanfiction-page-wrapper .fanfiction-sidebar` (`assets/css/fanfiction-frontend.css:3849`)
  - fixed sidebar basis (`300px`) and min-width (`250px`).
- Runtime inline width rules on `#fanfiction-wrapper` (`includes/class-fanfic-core.php:1169`, `includes/class-fanfic-core.php:1179`)
  - width/max-width/padding constraining available grid space.
- `.fanfic-content-section` margin/padding (`assets/css/fanfiction-frontend.css:3178`, `3354`, `3392`)
  - spacing can combine with parent grid gap.
- `.fanfic-form-field` margins (`assets/css/fanfiction-frontend.css:644`)
  - block spacing can combine with grid gap when field containers are grid items.
- `.fanfic-form-actions` flex behavior (`assets/css/fanfiction-frontend.css:1077`)
  - remains nested flex inside any parent grid item.
- `.fanfic-form-header` flex behavior (`assets/css/fanfiction-frontend.css:884`)
  - remains nested flex inside any parent grid item.

## Notes on Wrapper Control
- The editor-specific wrapper classes exist in template markup but currently have no dedicated CSS layout selectors:
  - `.fanfic-content-layout`
  - `.fanfic-content-primary`
  - `.fanfic-content-sidebar`
- Effective layout currently comes from:
  - page shell flex (`.fanfiction-page-wrapper`), and
  - default block flow of editor wrapper descendants plus local component flex/grid rules.
