# Author's Notes — Implementation Plan

## Meta Keys (both story and chapter post types)
- `_fanfic_author_notes_enabled` — `'1'` or `'0'`
- `_fanfic_author_notes_position` — `'above'` or `'below'`
- `_fanfic_author_notes` — rich text content (sanitized via `wp_kses_post()`)

---

## 1. Form UI — Chapter (`templates/template-chapter-form.php`)

Add a new section **below the chapter content editor**, with:

```
┌─────────────────────────────────────────────────┐
│ [✓] Enable Author's Notes                        │
│                                                  │
│  Notes [above ▼] the chapter content            │
│                                                  │
│  [TinyMCE editor — same settings as chapter     │
│   content: bold/italic/underline/lists/          │
│   blockquote/undo/redo, no media/quicktags]      │
└─────────────────────────────────────────────────┘
```

- Checkbox toggle: `fanfic_author_notes_enabled` (value `1`)
- Position select: `fanfic_author_notes_position` (options: `above`, `below`)
- Editor: `wp_editor()` with ID `fanfic_chapter_author_notes`, textarea name `fanfic_author_notes`
- Notes fields hidden by default; shown via JS when checkbox is checked
- On edit, pre-fill all three fields from saved meta

---

## 2. Form UI — Story (`templates/template-story-form.php`)

Same structure, added **below the story introduction textarea**:

- Checkbox: `fanfic_author_notes_enabled`
- Position select: `fanfic_author_notes_position`
- Editor: ID `fanfic_story_author_notes`, textarea name `fanfic_author_notes`
- Same show/hide JS behavior
- On edit, pre-fill from saved meta

---

## 3. JS Toggle (`assets/js/fanfiction-frontend.js`)

On `change` of the `fanfic_author_notes_enabled` checkbox:
- If checked → show the notes position row + notes editor wrapper
- If unchecked → hide them

Runs on DOM ready, handles both story and chapter forms.

---

## 4. Save Handler — Chapter (`includes/handlers/class-fanfic-chapter-handler.php`)

In both `handle_create_chapter_submission()` and `handle_edit_chapter_submission()`:

```php
$notes_enabled  = isset( $_POST['fanfic_author_notes_enabled'] ) ? '1' : '0';
$notes_position = isset( $_POST['fanfic_author_notes_position'] )
    ? ( 'above' === $_POST['fanfic_author_notes_position'] ? 'above' : 'below' )
    : 'below';
$notes_content  = isset( $_POST['fanfic_author_notes'] )
    ? wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) )
    : '';

update_post_meta( $chapter_id, '_fanfic_author_notes_enabled',  $notes_enabled );
update_post_meta( $chapter_id, '_fanfic_author_notes_position', $notes_position );
update_post_meta( $chapter_id, '_fanfic_author_notes',          $notes_content );
```

---

## 5. Save Handler — Story (`includes/handlers/class-fanfic-story-handler.php`)

Same logic added to `handle_unified_story_form()`, using `$story_id`.

---

## 6. Output — Chapter Shortcode (`includes/shortcodes/class-fanfic-shortcodes-chapter.php`)

Modify `chapter_content()` (`[fanfic-chapter-content]`):

```php
$notes_enabled  = get_post_meta( $chapter_id, '_fanfic_author_notes_enabled', true );
$notes_position = get_post_meta( $chapter_id, '_fanfic_author_notes_position', true ) ?: 'below';
$notes_content  = get_post_meta( $chapter_id, '_fanfic_author_notes', true );

$notes_html = '';
if ( $notes_enabled && ! empty( $notes_content ) ) {
    $notes_html = '<aside class="fanfic-author-notes">'
        . '<h4 class="fanfic-author-notes-title">' . esc_html__( "Author's Notes", 'fanfiction-manager' ) . '</h4>'
        . '<div class="fanfic-author-notes-content">' . wp_kses_post( wpautop( $notes_content ) ) . '</div>'
        . '</aside>';
}

$content = apply_filters( 'the_content', $chapter->post_content );

return ( 'above' === $notes_position )
    ? $notes_html . $content
    : $content . $notes_html;
```

---

## 7. Output — Story Shortcode (`includes/shortcodes/class-fanfic-shortcodes-story.php`)

Modify `story_intro()` (`[story-intro]`):

Same pattern — read the 3 meta keys for `$story_id`, build `$notes_html`, wrap or append around the existing intro `<div class="story-intro">`.

---

## 8. CSS (`assets/css/fanfiction-frontend.css`)

Add styles for:
- `.fanfic-author-notes` — container (distinct background, border-left accent, padding)
- `.fanfic-author-notes-title` — "Author's Notes" label
- `.fanfic-author-notes-content` — the notes text
- `.fanfic-author-notes-fields` — form section wrapper (toggle target)

---

## Files Modified

| File | Change |
|------|--------|
| `templates/template-chapter-form.php` | Add author's notes form section |
| `templates/template-story-form.php` | Add author's notes form section |
| `includes/handlers/class-fanfic-chapter-handler.php` | Save meta (create + edit) |
| `includes/handlers/class-fanfic-story-handler.php` | Save meta |
| `includes/shortcodes/class-fanfic-shortcodes-chapter.php` | Wrap chapter content with notes |
| `includes/shortcodes/class-fanfic-shortcodes-story.php` | Wrap story intro with notes |
| `assets/css/fanfiction-frontend.css` | Author's notes styles |
| `assets/js/fanfiction-frontend.js` | Checkbox toggle behavior |
