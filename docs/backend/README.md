# Backend Documents

This folder contains backend-focused documentation for systems that affect how stories are stored, linked, synchronized, and displayed.

## Available Documents

- [Co-Authors](coauthors.md)  
  Explains invitation flow, permissions, accepted vs pending behavior, and how collaborative authorship affects stories.

- [Translated Stories](translated-stories.md)  
  Explains what translated stories are, why the system exists, what stays synchronized between linked stories, and what does not.

- [Status System](status-system.md)  
  Explains the built-in story status behavior, including automatic inactivity changes and author restrictions.

- [Shortcodes](shortcodes.md)  
  Lists the shortcode tags registered by the plugin and what each one outputs.

## How The Admin Documentation Page Works

The WordPress admin page at:

`admin.php?page=fanfiction-docs`

does **not** automatically scan this folder for Markdown files.

It is built from a hardcoded accordion list in:

- `includes/admin/class-fanfic-docs-admin.php`

That means a new `.md` file will not appear in the admin Documentation page unless the PHP configuration for the accordion is also updated.

## How To Add A New Backend Document To The Admin Accordion

To make a document appear correctly inside the Documentation admin page, do all of the following:

1. Create the Markdown file inside `docs/backend/`.
2. Add a new accordion item in `Fanfic_Docs_Admin::render_page()`.
3. Add the matching doc ID and file path in `Fanfic_Docs_Admin::get_docs_map()`.
4. Make sure the accordion `id` and the map key are exactly the same.

If any of those steps are missing, the document will exist on disk but will not load inside `admin.php?page=fanfiction-docs`.

## Required PHP Changes

### 1. Add the accordion entry

Inside `includes/admin/class-fanfic-docs-admin.php`, update the `$accordions` array in `render_page()`.

Example:

```php
$accordions = array(
	array(
		'id'    => 'status-system',
		'label' => __( 'Status Classification System', 'fanfiction-manager' ),
	),
	array(
		'id'    => 'translated-stories',
		'label' => __( 'Translated Stories', 'fanfiction-manager' ),
	),
);
```

### 2. Add the Markdown file mapping

Inside the same file, update `get_docs_map()`.

Example:

```php
self::$docs_map = array(
	'status-system'     => FANFIC_PLUGIN_DIR . 'docs/backend/status-system.md',
	'shortcode'         => FANFIC_PLUGIN_DIR . 'docs/backend/shortcodes.md',
	'translated-stories' => FANFIC_PLUGIN_DIR . 'docs/backend/translated-stories.md',
);
```

The key must match the accordion `id`.

## Important Notes

- The admin page uses AJAX action `fanfic_fetch_doc` to load content on first accordion expand.
- Only documents present in `get_docs_map()` are allowed to load.
- The system uses a custom Markdown-to-HTML converter, not a full Markdown library.
- The converter supports headings, paragraphs, lists, blockquotes, code blocks, horizontal rules, tables, bold, italic, and inline code.
- Links in this README do not make a document appear in the admin accordion. The PHP registration is what controls visibility there.

## Rule For Future Documentation

When adding a new backend document, treat it as a two-part change:

1. Add the Markdown file.
2. Register it in `includes/admin/class-fanfic-docs-admin.php`.

Do not assume the admin Documentation page is folder-driven. It is currently whitelist-driven.
