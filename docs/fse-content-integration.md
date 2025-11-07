# FSE Theme Content Integration Guide

## Overview

This document explains how dynamic page content (dashboard, members, search, user profiles) integrates with the plugin's page template system on both **Classic** and **FSE (Block)** themes.

---

## 🏗️ Architecture: Two-System Approach

The plugin uses a **hybrid page system** to maximize flexibility:

### 1. **Real WordPress Pages** (Database-backed)
- Created during setup wizard
- Stored in `wp_posts` table
- Examples: Login, Register, Password Reset, Error, Maintenance
- Content: Shortcodes wrapped in WordPress blocks

### 2. **Virtual Dynamic Pages** (Rewrite-based)
- No database entries (lightweight)
- Created via rewrite rules
- Examples: Dashboard, Create Story, Search, Members
- Content: Injected dynamically via filters

---

## 📄 System 1: Real WordPress Pages

### Page Creation Process

When pages are created (via `class-fanfic-templates.php:615`), they're populated with **block-wrapped shortcodes**:

```php
public static function get_default_template_content( $page_slug ) {
    $templates = array(
        'login'          => '<!-- wp:paragraph --><p>[fanfic-login-form]</p><!-- /wp:paragraph -->',
        'register'       => '<!-- wp:paragraph --><p>[fanfic-register-form]</p><!-- /wp:paragraph -->',
        'password-reset' => '<!-- wp:paragraph --><p>[fanfic-password-reset-form]</p><!-- /wp:paragraph -->',
        'dashboard'      => '<!-- wp:paragraph --><p>[user-dashboard]</p><!-- /wp:paragraph -->',
        'create-story'   => '<!-- wp:paragraph --><p>[author-create-story-form]</p><!-- /wp:paragraph -->',
        'search'         => '<!-- wp:paragraph --><p>[search-results]</p><!-- /wp:paragraph -->',
        'members'        => '<!-- wp:paragraph --><p>[user-profile]</p><!-- /wp:paragraph -->',
        'error'          => '<!-- wp:paragraph --><p>[fanfic-error-message]</p><!-- /wp:paragraph -->',
        'maintenance'    => '<!-- wp:paragraph --><p>[fanfic-maintenance-message]</p><!-- /wp:paragraph -->',
    );

    return isset( $templates[ $page_slug ] ) ? $templates[ $page_slug ] : '';
}
```

### How Content Renders

#### **Classic Themes** (e.g., OceanWP)
File: `templates/fanfiction-page-template.php`

```php
<div class="entry-content">
    <?php
    /**
     * the_content() processes:
     * 1. Block markup (<!-- wp:paragraph -->)
     * 2. Shortcodes ([user-dashboard])
     * 3. Filters (wpautop, wptexturize, etc.)
     */
    the_content();
    ?>
</div>
```

**Flow:**
1. WordPress loads the PHP template
2. `the_content()` is called
3. WordPress strips block comments: `<!-- wp:paragraph --><p>[user-dashboard]</p><!-- /wp:paragraph -->` → `<p>[user-dashboard]</p>`
4. `do_shortcode()` processes shortcodes automatically
5. Shortcode handler (`Fanfic_Shortcodes_User::user_dashboard()`) returns HTML
6. Final HTML is rendered

#### **Block (FSE) Themes** (e.g., Twenty Twenty-Two)
File: `templates/block/fanfiction-page-template.html`

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main"...} -->
<main class="wp-block-group fanfiction-page-content" ...>
    <!-- wp:post-title {"level":1,"className":"fanfiction-page-title"} /-->

    <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

**Flow:**
1. WordPress loads the block template
2. `<!-- wp:post-content -->` block renders the page content
3. WordPress processes stored blocks: `<!-- wp:paragraph --><p>[user-dashboard]</p><!-- /wp:paragraph -->`
4. Block renderer outputs: `<p>[user-dashboard]</p>`
5. `do_shortcode()` processes shortcodes (via `the_content` filter)
6. Shortcode handler returns HTML
7. Final HTML is rendered

---

## 🌐 System 2: Virtual Dynamic Pages

Virtual pages don't exist in the database. They're created on-the-fly using WordPress's rewrite and filter systems.

### How Virtual Pages Work

#### Step 1: Rewrite Rules
File: `class-fanfic-url-manager.php:181`

```php
// Dashboard: /fanfiction/dashboard/
add_rewrite_rule(
    '^' . $base . '/' . $slugs['dashboard'] . '/?$',
    'index.php?fanfic_page=dashboard',
    'top'
);
```

When user visits `/fanfiction/dashboard/`, WordPress sets `fanfic_page=dashboard` query var.

#### Step 2: Setup Virtual Page Context
File: `class-fanfic-url-manager.php:545`

```php
public function setup_virtual_pages() {
    $fanfic_page = get_query_var( 'fanfic_page' );

    if ( empty( $fanfic_page ) ) {
        return;
    }

    // Tell WordPress this is a page request
    global $wp_query;
    $wp_query->is_page     = true;
    $wp_query->is_singular = true;
    $wp_query->is_home     = false;
    $wp_query->is_404      = false;
}
```

This tricks WordPress into thinking it's loading a real page.

#### Step 3: Create Fake WP_Post Object
File: `class-fanfic-url-manager.php:572`

```php
public function create_virtual_page_post( $posts, $query ) {
    $fanfic_page = get_query_var( 'fanfic_page' );

    if ( empty( $fanfic_page ) ) {
        return $posts;
    }

    $page_config = $this->get_virtual_page_config( $fanfic_page );

    // Create fake post object
    $post = new stdClass();
    $post->ID           = -999; // Negative ID to avoid conflicts
    $post->post_title   = $page_config['title'];
    $post->post_content = ''; // Empty - will be injected
    $post->post_type    = 'page';
    $post->post_status  = 'publish';

    // Store page key for content injection
    $post->fanfic_page_key = $fanfic_page;

    return array( new WP_Post( $post ) );
}
```

#### Step 4: Inject Shortcode Content
File: `class-fanfic-url-manager.php:642`

```php
public function inject_virtual_page_content( $content ) {
    global $post;

    // Only process our virtual pages
    if ( ! isset( $post->fanfic_page_key ) ) {
        return $content;
    }

    $page_config = $this->get_virtual_page_config( $post->fanfic_page_key );

    // Return the shortcode - WordPress will process it automatically
    return do_shortcode( '[' . $page_config['shortcode'] . ']' );
}
```

#### Step 5: Page Configuration
File: `class-fanfic-url-manager.php:672`

```php
private function get_virtual_page_config( $page_key ) {
    $pages = array(
        'dashboard'    => array(
            'title'     => __( 'Dashboard', 'fanfiction-manager' ),
            'shortcode' => 'user-dashboard',
        ),
        'create-story' => array(
            'title'     => __( 'Create Story', 'fanfiction-manager' ),
            'shortcode' => 'author-create-story-form',
        ),
        'search'       => array(
            'title'     => __( 'Search', 'fanfiction-manager' ),
            'shortcode' => 'search-results',
        ),
        'members'      => array(
            'title'     => __( 'Members', 'fanfiction-manager' ),
            'shortcode' => 'user-profile',
        ),
    );

    return isset( $pages[ $page_key ] ) ? $pages[ $page_key ] : false;
}
```

### Template Loading for Virtual Pages

Virtual pages use the **same template system** as real pages.

#### Classic Themes
File: `class-fanfic-page-template.php:90`

```php
public static function load_page_template( $template ) {
    global $post;

    // Check if this is a virtual dynamic page
    if ( isset( $post->fanfic_page_key ) ) {
        return self::locate_template(); // Returns fanfiction-page-template.php
    }

    // ... other checks ...
}
```

The virtual page loads `templates/fanfiction-page-template.php`, which calls:
- `the_content()` → processes injected shortcode → renders HTML

#### Block (FSE) Themes
Virtual pages will use the assigned block template (`fanfiction-manager//fanfiction-page-template`).

The `<!-- wp:post-content -->` block will:
1. Call `the_content()` filter chain
2. Execute `inject_virtual_page_content()` filter
3. Process `[user-dashboard]` shortcode
4. Render final HTML

---

## 🔄 Content Flow Diagram

### Real WordPress Pages

```
┌─────────────────────────────────────────────────────────────┐
│ 1. User visits /fanfiction/login/                           │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 2. WordPress loads page with template assigned              │
│    _wp_page_template = 'fanfiction-page-template.php'       │
│                     OR                                       │
│    _wp_page_template = 'fanfiction-manager//fanfiction...'  │
└─────────────────────┬───────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
┌───────▼──────────┐    ┌──────────▼────────────┐
│ Classic Theme    │    │ Block (FSE) Theme     │
│                  │    │                       │
│ PHP Template     │    │ HTML Block Template   │
│ the_content()    │    │ <!-- wp:post-content │
└───────┬──────────┘    └──────────┬────────────┘
        │                           │
        └─────────────┬─────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 3. WordPress processes page content:                        │
│    '<!-- wp:paragraph --><p>[fanfic-login-form]</p><!--..'  │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 4. Strip block comments → '<p>[fanfic-login-form]</p>'      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 5. do_shortcode() processes [fanfic-login-form]             │
│    Calls: Fanfic_Shortcodes_Auth::login_form()              │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 6. Shortcode returns HTML (login form markup)               │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 7. Final HTML rendered on page                              │
└─────────────────────────────────────────────────────────────┘
```

### Virtual Dynamic Pages

```
┌─────────────────────────────────────────────────────────────┐
│ 1. User visits /fanfiction/dashboard/                       │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 2. Rewrite rule matches → sets fanfic_page=dashboard        │
│    (class-fanfic-url-manager.php:186)                       │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 3. setup_virtual_pages() sets $wp_query flags               │
│    - is_page = true                                          │
│    - is_singular = true                                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 4. create_virtual_page_post() creates fake WP_Post          │
│    - ID: -999                                                │
│    - post_title: 'Dashboard'                                 │
│    - post_content: '' (empty)                                │
│    - fanfic_page_key: 'dashboard'                            │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 5. load_page_template() detects virtual page                │
│    Returns: fanfiction-page-template.php OR block template  │
└─────────────────────┬───────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
┌───────▼──────────┐    ┌──────────▼────────────┐
│ Classic Theme    │    │ Block (FSE) Theme     │
│ the_content()    │    │ <!-- wp:post-content │
└───────┬──────────┘    └──────────┬────────────┘
        │                           │
        └─────────────┬─────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 6. inject_virtual_page_content() filter injects shortcode   │
│    Returns: '[user-dashboard]'                              │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 7. do_shortcode() processes [user-dashboard]                │
│    Calls: Fanfic_Shortcodes_User::user_dashboard()          │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 8. Shortcode returns HTML (dashboard interface)             │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│ 9. Final HTML rendered on page                              │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ Why This Works in Both Theme Types

### Key Compatibility Points

#### 1. **Block-Wrapped Shortcodes**
Pages are created with block markup:
```html
<!-- wp:paragraph --><p>[user-dashboard]</p><!-- /wp:paragraph -->
```

This works in both:
- **Classic themes**: Block comments are stripped, shortcode is processed
- **FSE themes**: Block is parsed, rendered as `<p>`, shortcode is processed

#### 2. **Shortcode Processing**
Both `the_content()` (classic) and `<!-- wp:post-content -->` (FSE) trigger the `the_content` filter, which includes:
- `do_shortcode()` - processes all shortcodes
- Block rendering (FSE only)
- Auto-paragraph formatting
- Other content filters

#### 3. **Template Assignment**
The updated code (`class-fanfic-page-template.php`) assigns the correct template based on theme type:

```php
public static function get_template_identifier() {
    return self::is_block_theme() ? self::BLOCK_TEMPLATE_SLUG : self::TEMPLATE_FILE;
}
```

**Classic themes**: `_wp_page_template = 'fanfiction-page-template.php'`
**FSE themes**: `_wp_page_template = 'fanfiction-manager//fanfiction-page-template'`

#### 4. **Content Injection Hook**
Virtual pages inject content via the `the_content` filter:

```php
add_filter( 'the_content', array( $this, 'inject_virtual_page_content' ) );
```

This filter fires in both:
- Classic PHP templates when `the_content()` is called
- Block templates when `<!-- wp:post-content -->` is rendered

---

## 🛠️ Shortcode Handler Example

File: `includes/shortcodes/class-fanfic-shortcodes-user.php:78`

```php
public static function user_dashboard( $atts ) {
    // Check if user is logged in
    if ( ! is_user_logged_in() ) {
        return self::login_prompt( __( 'Please log in to view your dashboard.', 'fanfiction-manager' ) );
    }

    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();

    // Build dashboard HTML
    $output = '<div class="fanfic-user-dashboard">';
    $output .= '<div class="fanfic-dashboard-header">';
    $output .= '<h2>' . sprintf( __( 'Welcome, %s', 'fanfiction-manager' ), $current_user->display_name ) . '</h2>';
    $output .= '</div>';

    // ... dashboard content sections ...
    // - Stories
    // - Favorites
    // - Notifications
    // - etc.

    $output .= '</div>';

    return $output;
}
```

The shortcode handler:
1. Receives attributes (if any)
2. Performs logic (check login, fetch data, etc.)
3. Builds HTML output
4. Returns HTML string
5. WordPress inserts this HTML into the page content

---

## 📝 Summary

### Content Integration Method

| Aspect | Classic Themes | FSE Themes | Notes |
|--------|---------------|------------|-------|
| **Template File** | `fanfiction-page-template.php` | `fanfiction-page-template.html` | Auto-selected based on theme |
| **Content Wrapper** | PHP: `the_content()` | Block: `<!-- wp:post-content -->` | Both trigger same filters |
| **Page Content** | Block-wrapped shortcodes | Block-wrapped shortcodes | Same format in database |
| **Shortcode Processing** | ✅ Via `the_content` filter | ✅ Via `the_content` filter | Automatic in both |
| **Virtual Pages** | ✅ Via content injection | ✅ Via content injection | Same mechanism |

### Why It's Theme-Agnostic

✅ **Block markup is universal** - Works in both classic and FSE
✅ **Shortcodes are theme-independent** - Process the same way everywhere
✅ **Content filters are standardized** - `the_content` fires in both systems
✅ **Template assignment is automatic** - Detects theme type on activation/switch

---

## 🚀 Testing Recommendations

### Test Scenario 1: Classic Theme
1. Activate OceanWP or another classic theme
2. Visit `/fanfiction/dashboard/` (virtual page)
3. Visit `/fanfiction/login/` (real page)
4. Verify both load the PHP template and render shortcodes

### Test Scenario 2: FSE Theme
1. Activate Twenty Twenty-Two or another block theme
2. Visit `/fanfiction/dashboard/` (virtual page)
3. Visit `/fanfiction/login/` (real page)
4. Verify both load the block template and render shortcodes

### Test Scenario 3: Theme Switch
1. Start with classic theme
2. Create/view pages
3. Switch to block theme
4. Verify admin notice appears
5. Click "Fix Pages Now" button
6. Verify pages still work with block template

---

## 🔍 Troubleshooting

### Issue: Shortcodes Not Processing in FSE Theme

**Cause**: Some custom block themes override `the_content` filter
**Solution**: Ensure block template uses standard `<!-- wp:post-content -->` block

### Issue: Virtual Pages Show 404 in FSE Theme

**Cause**: Rewrite rules not flushed, or template assignment issue
**Solution**:
1. Go to Settings → Permalinks → Save Changes
2. Verify `$wp_query->is_page` is set to true in virtual page setup

### Issue: Block Template Not Loading

**Cause**: WordPress doesn't recognize the block template
**Solution**:
1. Verify block template file exists: `templates/block/fanfiction-page-template.html`
2. Verify template slug is correct in page meta: `fanfiction-manager//fanfiction-page-template`
3. Clear theme cache: `wp_cache_flush()`

---

## 📚 Related Files

- `includes/class-fanfic-page-template.php` - Template registration & assignment
- `includes/class-fanfic-templates.php` - Page creation with shortcodes
- `includes/class-fanfic-url-manager.php` - Virtual pages & rewrite rules
- `includes/shortcodes/class-fanfic-shortcodes-user.php` - Dashboard shortcode
- `includes/shortcodes/class-fanfic-shortcodes-profile.php` - Profile shortcode
- `includes/shortcodes/class-fanfic-shortcodes-search.php` - Search shortcode
- `includes/shortcodes/class-fanfic-shortcodes-author-forms.php` - Create story shortcode
- `templates/fanfiction-page-template.php` - Classic theme template
- `templates/block/fanfiction-page-template.html` - FSE theme template

---

**Last Updated**: 2025-11-07
**Plugin Version**: 1.0.0+
**WordPress Compatibility**: 5.8+ (FSE support requires 5.9+)
