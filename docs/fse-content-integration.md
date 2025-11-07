# FSE Theme Content Integration Guide

## Overview

This document explains how the Fanfiction Manager plugin works with both **Classic** and **FSE (Full Site Editing / Block)** themes.

---

## 🏗️ Architecture: Two Theme Approaches

### Classic Themes (e.g., OceanWP, Astra, GeneratePress)

**Uses:** Custom PHP template (`templates/fanfiction-page-template.php`)

**How it works:**
- Plugin registers a custom page template in WordPress
- Pages are assigned this template automatically
- Template includes theme's header/footer via `get_header()` and `get_footer()`
- Provides custom layout with optional sidebar
- Full control over HTML structure and styling

### FSE/Block Themes (e.g., Twenty Twenty-Two, Twenty Twenty-Three)

**Uses:** Theme's default page template (no custom template)

**How it works:**
- Plugin does NOT provide a custom template
- Pages use the active FSE theme's default `page.html` template
- Content is injected via WordPress's standard content filters
- Theme's Site Editor controls the layout
- Users can customize appearance via Appearance > Editor

---

## 📄 Content System: Two Page Types

### 1. Real WordPress Pages (Database-backed)

**Created by:** Setup wizard
**Stored in:** `wp_posts` table
**Examples:** Login, Register, Password Reset, Error, Maintenance

**Content format:**
```php
// Block-wrapped shortcodes
'<!-- wp:paragraph --><p>[fanfic-login-form]</p><!-- /wp:paragraph -->'
```

**Rendering flow:**
1. WordPress loads appropriate template (custom PHP for classic, theme's page.html for FSE)
2. `the_content()` or `<!-- wp:post-content /-->` block renders content
3. WordPress processes blocks and strips block comments
4. `do_shortcode()` processes shortcodes automatically
5. Shortcode handlers return HTML
6. Final output is rendered

### 2. Virtual Dynamic Pages (Rewrite-based)

**Created by:** Rewrite rules (no database entry)
**Examples:** Dashboard, Create Story, Search, Members
**File:** `includes/class-fanfic-url-manager.php`

**How it works:**
1. URL rewrite rules catch specific URLs (e.g., `/fanfiction/dashboard/`)
2. Query var `fanfic_page` is set
3. `the_posts` filter creates a fake `WP_Post` object
4. WordPress treats it as a real page
5. `the_content` filter injects shortcode dynamically
6. Normal template loading applies (custom PHP or FSE theme default)

**Code example:**
```php
// In create_virtual_page_post()
$post->post_content = ''; // Empty initially

// In inject_virtual_page_content()
public function inject_virtual_page_content( $content ) {
    if ( ! isset( $post->fanfic_page_key ) ) {
        return $content;
    }

    $page_config = $this->get_virtual_page_config( $post->fanfic_page_key );
    return do_shortcode( '[' . $page_config['shortcode'] . ']' );
}
```

---

## 🎨 Template Assignment

### On Page Creation
```php
// In class-fanfic-templates.php
$template_identifier = Fanfic_Page_Template::get_template_identifier();
update_post_meta( $page_id, '_wp_page_template', $template_identifier );
```

**Result:**
- **Classic themes:** Assigns `fanfiction-page-template.php`
- **FSE themes:** Assigns `default` (theme handles it)

### On Theme Switch

When switching between classic and FSE themes:

1. Plugin detects theme type change
2. Automatically updates all plugin pages' template assignments
3. Shows admin notice confirming update
4. If auto-fix fails, provides "Fix Pages" button

**Code:**
```php
// In class-fanfic-page-template.php
public static function handle_theme_switch() {
    $previous_theme_type = get_option( 'fanfic_theme_type' );
    $current_theme_type = self::is_block_theme() ? 'block' : 'classic';

    if ( $previous_theme_type !== $current_theme_type ) {
        self::auto_fix_pages_for_theme_type( $current_theme_type );
    }
}
```

---

## 🔧 Template Loading Logic

### Classic Themes
```php
// In class-fanfic-page-template.php
public static function load_page_template( $template ) {
    if ( self::is_block_theme() ) {
        return $template; // Let FSE theme handle it
    }

    // For classic themes, load our custom template
    if ( isset( $post->fanfic_page_key ) || self::is_plugin_page( $post->ID ) ) {
        return self::locate_template(); // Returns templates/fanfiction-page-template.php
    }

    return $template;
}
```

### FSE Themes

No custom template loading. The plugin:
1. Sets page template meta to `'default'`
2. Returns early from `load_page_template()` filter
3. Lets WordPress use the theme's `page.html` template
4. Content still renders via `the_content()` filter

---

## 🎯 Why This Approach?

### Classic Themes: Custom Template

**Advantages:**
- Full control over layout and structure
- Can provide custom sidebar with widgets
- Easy to add plugin-specific styling
- Works with all classic themes consistently

**How it works:**
```php
get_header(); // Theme's header
?>
<div class="fanfiction-page-wrapper">
    <div class="fanfiction-page-main">
        <?php the_content(); // Shortcode content ?>
    </div>
    <?php if ( is_active_sidebar( 'fanfiction-sidebar' ) ) : ?>
        <aside class="fanfiction-sidebar">
            <?php dynamic_sidebar( 'fanfiction-sidebar' ); ?>
        </aside>
    <?php endif; ?>
</div>
<?php
get_footer(); // Theme's footer
```

### FSE Themes: No Custom Template

**Advantages:**
- Respects user's chosen theme design
- Users can customize via Site Editor (Appearance > Editor)
- No maintenance of block template HTML
- Works with any FSE theme's page template
- Follows WordPress FSE best practices

**Why not use a custom block template?**
- FSE themes are designed to be fully customizable by users
- Custom templates would override user's design choices
- Would require maintaining complex block markup
- Users expect FSE themes to use Site Editor
- Plugin content still renders perfectly via filters

---

## 🔍 Troubleshooting

### Content Not Showing (Classic Themes)

**Issue:** Blank page or no content
**Fix:** Check that `the_content()` is called in the template

### Content Not Showing (FSE Themes)

**Issue:** Blank page or no content
**Fix:**
1. Check theme has `<!-- wp:post-content /-->` block in page template
2. Verify page template meta is set to `'default'`
3. Check virtual page rewrite rules are flushed

### Template Not Applying (Classic Themes)

**Issue:** Using wrong template
**Fix:**
1. Go to Settings > Permalinks > Save (flushes rewrite rules)
2. Edit the page, check Template dropdown shows "Fanfiction Page Template"
3. Clear theme cache: deactivate/reactivate plugin

### Styling Issues (FSE Themes)

**Issue:** Layout doesn't match theme
**Fix:** This is expected! FSE themes use Site Editor for customization.
- Go to Appearance > Editor > Templates
- Find the page template
- Customize layout as desired

---

## 📝 Key Takeaways

1. **Classic themes:** Plugin provides custom template with full control
2. **FSE themes:** Plugin uses theme's default template, no customization needed
3. **Both work seamlessly:** Content renders via standard WordPress filters
4. **Theme switching:** Automatically handled by plugin
5. **Virtual pages:** Work identically in both theme types
6. **Shortcodes:** Process normally in all scenarios

The plugin is designed to work perfectly with both theme types while respecting WordPress conventions and user expectations.
