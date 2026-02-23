# Plan: Complete Dirty State Audit for Story & Chapter Forms

## Context
The story and chapter forms already have a working dirty state system (`checkForChanges()`, `data-original-*` attributes, event listeners). The Update/Update Draft buttons already start disabled and enable on field changes. However, there are two gaps:
1. **Publish buttons are NOT tracked** by dirty state — they stay always enabled in edit mode
2. **No `beforeunload` warning** when navigating away with unsaved changes

## Changes Required

### 1. Story Form (`templates/template-story-form.php`)

**A. HTML — Disable Publish buttons on load (edit mode only)**

- **Line 1064**: `value="publish"` ("Update and Publish") — already `disabled` (permanently, due to no published chapters). No change needed here since it's a special case.
- **Line 1081**: `value="publish"` ("Make Visible") — add `disabled` attribute and `id="publish-button"` so `checkForChanges` can target it.

**B. JS — Add Publish button to `checkForChanges()`**

In the `checkForChanges()` function (~line 1857), after enabling/disabling `#update-button` and `#update-draft-button`, also enable/disable `#publish-button`:
```js
var livePublishBtn = document.getElementById('publish-button');
if (livePublishBtn) {
    livePublishBtn.disabled = !hasChanges;
}
```

**C. JS — Update `syncStoryActionButtons()` (~line 2445)**

When this function dynamically recreates buttons after AJAX save, it needs to:
- Set `id="publish-button"` on the Publish/Make Visible button
- Start it disabled (since original state just got reset)

Currently in the `draft` branch (~line 2501), the primary button gets `disabled = false` — change to `disabled = true` and add `id="publish-button"`.

**D. JS — Add `beforeunload` warning**

After the `checkForChanges` function and event listeners, add:
```js
window.addEventListener('beforeunload', function(e) {
    var updateBtn = document.getElementById('update-button');
    var updateDraftBtn = document.getElementById('update-draft-button');
    var publishBtn = document.getElementById('publish-button');
    var hasUnsaved = (updateBtn && !updateBtn.disabled) ||
                     (updateDraftBtn && !updateDraftBtn.disabled) ||
                     (publishBtn && !publishBtn.disabled);
    if (hasUnsaved) {
        e.preventDefault();
    }
});
```

**E. JS — Re-disable Publish in `.finally()` block (~line 2672)**

After successful save, the `.finally()` block re-enables all buttons then re-disables update buttons. Also re-disable `#publish-button`:
```js
var publishButton = document.getElementById('publish-button');
if (publishButton) {
    publishButton.disabled = true;
}
```

### 2. Chapter Form (`templates/template-chapter-form.php`)

**A. HTML — Disable Publish button on load (edit mode, draft chapter)**

- **Line 1135**: `value="publish"` ("Publish Chapter") — add `disabled` attribute and `id="publish-chapter-button"`.

**B. JS — Add Publish button to `checkForChanges()`**

In the chapter's `checkForChanges()` function (~line 1774), after enabling/disabling `#update-chapter-button` and `#update-draft-chapter-button`, also handle `#publish-chapter-button`:
```js
var livePublishBtn = document.getElementById('publish-chapter-button');
if (livePublishBtn) {
    livePublishBtn.disabled = !hasChanges;
}
```

**C. JS — Add `beforeunload` warning**

Similar to story form, after the event listeners:
```js
window.addEventListener('beforeunload', function(e) {
    var updateBtn = document.getElementById('update-chapter-button');
    var updateDraftBtn = document.getElementById('update-draft-chapter-button');
    var publishBtn = document.getElementById('publish-chapter-button');
    var hasUnsaved = (updateBtn && !updateBtn.disabled) ||
                     (updateDraftBtn && !updateDraftBtn.disabled) ||
                     (publishBtn && !publishBtn.disabled);
    if (hasUnsaved) {
        e.preventDefault();
    }
});
```

**D. JS — After successful AJAX update, re-disable Publish button**

In the success handler (~line 1628-1630), after `button.disabled = true`, also check for publish button. And in the dynamic button sync code that may exist after AJAX chapter operations.

### 3. CSS — Disabled button styling (`assets/css/fanfiction-frontend.css`)

Verify that `.fanfic-button:disabled` has appropriate styling (grayed out, no pointer). If not, add:
```css
.fanfic-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
```

## Files to Modify
1. `templates/template-story-form.php` — HTML button attributes + inline JS
2. `templates/template-chapter-form.php` — HTML button attributes + inline JS
3. `assets/css/fanfiction-frontend.css` — Verify/add disabled button styling

## Verification
1. **Story edit (published)**: Load form → Update button disabled → change any field → Update enables → save → Update disabled again
2. **Story edit (draft + published chapters)**: Load form → "Make Visible" disabled → change field → enables → save → disabled again
3. **Chapter edit (published)**: Load form → Update disabled → change field → enables → save → disabled again
4. **Chapter edit (draft)**: Load form → "Publish Chapter" disabled → change field → enables → save → disabled again
5. **beforeunload**: Make a change → try to navigate away → browser warns
6. **beforeunload (no changes)**: Load form → navigate away → no warning
7. **TinyMCE**: Change content in TinyMCE editor → buttons enable
8. **Checkboxes/radios/selects**: Toggle genre, status, type → buttons enable
