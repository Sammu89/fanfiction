My plugin has evolved from a pure shortcode-based system to a hybrid model. The current architecture uses:

Virtual Pages (4): Dynamic pages using shortcodes via virtual WP_Post objects
WordPress Pages (6): Real database pages with shortcode content
Custom Post Types (2): Stories & Chapters with template-based shortcode processing
Archive Page (1): PHP template with WordPress query

DETAILED PAGE-BY-PAGE BREAKDOWN

1. MAIN PAGE
Type: WordPress Page (created by wizard)
Location: Database page
Content Source:

IF fanfic_main_page_mode = 'stories_homepage': Uses /templates/archive-fanfiction_story.php (pure PHP template, NO shortcodes)
IF fanfic_main_page_mode = 'custom_homepage': Page content is editable (default: block content), wrapped in fanfiction-page-template.php
Template Chain:

class-fanfic-templates.php:54-66
  → archive-fanfiction_story.php (if stories_homepage mode)
  → OR fanfiction-page-template.php (if custom_homepage mode)
Status: ✅ Active, dual-mode system
### What needs to be done: Create a /templates/template-main-page.php that has the text that is feed to create this page into the database when it's created by the wizard. This allows it to be translatable with a redion pot file.

2. ERROR PAGE
Type: WordPress Page
Location: Database page
Content Source: /templates/template-error.php content OR shortcode
Shortcode Used: [fanfic-error-message]
Registered In: class-fanfic-shortcodes-utility.php:33

Template Chain:

Fanfic_Templates::get_default_template_content('error')
  → Returns: [fanfic-error-message] shortcode
  → Processed by: Fanfic_Shortcodes_Utility::error_message()
Status: ✅ Active shortcode
### What needs to be done: Make the /templates/template-error.php the only source of truth for this file (this allows it to be translatable) and delete all the code that generates and uses the [fanfic-error-message] and related to it



3. LOGIN
Type: WordPress Page
Location: Database page
Content Source: Shortcode
Shortcode Used: [fanfic-login-form]
Registered In: class-fanfic-shortcodes-forms.php:33

Template Chain:

class-fanfic-templates.php:1167
  → Returns: [fanfic-login-form] shortcode
  → Processed by: Fanfic_Shortcodes_Forms::login_form()
Status: ✅ Active shortcode
### What needs to be done: Nothing, this is correct as allows user to construct this page with the shortcodes

4. REGISTER
Type: WordPress Page
Location: Database page
Content Source: Shortcode
Shortcode Used: [fanfic-register-form]
Registered In: class-fanfic-shortcodes-forms.php:34

Template Chain:

class-fanfic-templates.php:1168
  → Returns: [fanfic-register-form] shortcode
  → Processed by: Fanfic_Shortcodes_Forms::register_form()
Status: ✅ Active shortcode
### What needs to be done: Nothing, this is correct as allows user to construct this page with the shortcodes

5. PASSWORD RESET
Type: WordPress Page
Location: Database page
Content Source: Shortcode
Shortcode Used: [fanfic-password-reset-form]
Registered In: class-fanfic-shortcodes-forms.php:35


Template Chain:

class-fanfic-templates.php:1169
  → Returns: [fanfic-password-reset-form] shortcode
  → Processed by: Fanfic_Shortcodes_Forms::password_reset_form()
Status: ✅ Active shortcode
### What needs to be done: Nothing, this is correct as allows user to construct this page with the shortcodes

6. MAINTENANCE
Type: WordPress Page
Location: Database page
Content Source: Shortcode
Shortcode Used: [fanfic-maintenance-message]
Registered In: class-fanfic-shortcodes-utility.php:34

Template Chain:

class-fanfic-templates.php:1175
  → Returns: [fanfic-maintenance-message] shortcode
  → Processed by: Fanfic_Shortcodes_Utility::maintenance_message()
Status: ✅ Active shortcode
### What needs to be done: Make the /templates/template-maintenance.php the only source of truth for this file, with the text (this allows it to be translatable) and delete all the code that generates and uses the [fanfic-maintenance-message] and related to it

7. USER PROFILE VIEW
Type: Hybrid (Virtual Page + Template)
Location: Virtual page OR WordPress page (members page with member_name param)
Content Source: /templates/template-view-profile.php (PHP template with embedded shortcodes)

Shortcodes Used:

[author-avatar] - line 32
[author-display-name] - line 35
[author-registration-date] - line 41
[author-story-count] - line 49
[author-actions] - line 58
[author-bio] - line 70
[author-story-list] - line 75
All Registered In: class-fanfic-shortcodes-author.php:33-48

Template Chain:

Virtual Page System (URL: /fanfiction/members/{username}/)
  → class-fanfic-url-manager.php:737-758
  → Injects: [user-profile] shortcode
  → OR loads: /templates/template-view-profile.php
Status: ✅ Active - Uses BOTH shortcodes AND template file
### What needs to be done: Nothing, this is correct as allows user to construct this page with the shortcodes




8. USER PROFILE EDIT
Type: Action-based template
Location: Accessed via ?action=edit on members page
Content Source: /templates/template-edit-profile.php (PHP template)

Shortcode Used:

[author-edit-profile-form] - line 25
[url-dashboard] - line 29
Registered In:

class-fanfic-shortcodes-author-forms.php:41
class-fanfic-shortcodes-url.php:36
Status: ✅ Active shortcodes
### What needs to be done: Make the /templates/template-edit-profile.php the only source of truth for this file and delete all the code that generates and uses the [author-edit-profile-form] and [url-dashboard] and related to it. All the code needed to generate this page and at its functions will be directed on the php page without shortcode.

9. STORY ARCHIVE
Type: Post Type Archive
Location: /templates/archive-fanfiction_story.php
Content Source: Pure PHP template - NO shortcodes used

Template Chain:

is_post_type_archive('fanfiction_story')
  → class-fanfic-templates.php:117-122
  → Loads: archive-fanfiction_story.php
  → Pure WordPress loop with native PHP
Status: ✅ Active PHP template (NOT shortcode-based)
### What needs to be done: Rename page to template-story-archive.php and make all the functions and links to this page updated to the new name.

10. STORY VIEW
Type: Custom Post Type Single
Location: /templates/single-fanfiction_story.php
Content Source: Admin-configurable template string loaded from fanfic_story_view_template option

How It Works:

$template = get_option('fanfic_story_view_template', '');
echo do_shortcode($template);
Default Template Uses These Shortcodes (from Fanfic_Settings::get_default_story_template):

[story-title]
[story-author-link]
[story-genres]
[story-status]
[story-intro]
[chapters-list]
[story-rating-display]
[story-bookmark-button]
[story-comments]
And many more...
All Registered In: class-fanfic-shortcodes-story.php

Status: ✅ Active - User-configurable shortcode template
### What needs to be done: Nothing, this is correct as allows user to construct this page with the shortcodes


11. STORY EDIT/ADD
Type: Action-based template
Location: Accessed via ?action=edit on story OR /fanfiction/create-story/

Current Architecture: Two Routes, Two Templates, Two Shortcodes

ROUTE 1: Create Story (Virtual Page)
URL: /fanfiction/create-story/
Method: Virtual page system
Template File: ❌ template-create-story.php is DEAD (not loaded)
Shortcode Used: [author-create-story-form]
How It Works:

Virtual Page System
  → class-fanfic-url-manager.php:743-746
  → Injects: [author-create-story-form] shortcode
  → Rendered by: Fanfic_Shortcodes_Author_Forms::create_story_form()
  → Wrapped in: fanfiction-page-template.php
What the shortcode does:

Shows empty form fields
No story_id parameter
On submit: Creates NEW story post
Redirects to: Edit page with new story_id
ROUTE 2: Edit Existing Story (Action-based)
URL: /fanfiction/stories/{story-slug}/?action=edit
Method: Action parameter on story permalink
Template File: ✅ template-edit-story.php (ACTIVE)
Shortcode Used: [author-edit-story-form story_id="X"]
How It Works:

Action-based Template Loading
  → class-fanfic-templates.php:76-85
  → Detects: is_singular('fanfiction_story') && $_GET['action'] === 'edit'
  → Loads: template-edit-story.php
  → Template executes: do_shortcode('[author-edit-story-form story_id="' . $story_id . '"]')
  → Rendered by: Fanfic_Shortcodes_Author_Forms::edit_story_form()
  → Wrapped in: fanfiction-page-template.php
What the shortcode does:

Receives story_id parameter
Loads existing story data
Pre-fills all form fields with current values
Checks user permissions
On submit: Updates EXISTING story post
Shows chapter management section
Shows delete story button
Are They Using Two Different Templates?
YES and NO:

Template Files:
❌ template-create-story.php - DEAD CODE (exists but never loaded, virtual page uses shortcode directly)
✅ template-edit-story.php - ACTIVE (loaded for editing existing stories)
Wrapper Template:
Both routes use fanfiction-page-template.php as the wrapper (classic themes)

Shortcodes:
Two completely separate shortcode functions:

create_story_form() - Line 355 of class-fanfic-shortcodes-author-forms.php
edit_story_form() - Line 509 of class-fanfic-shortcodes-author-forms.php
Code Comparison: Create vs Edit
Create Story Shortcode (create_story_form())
// No story exists
// Empty form fields
<input 
    type="text" 
    name="fanfic_story_title" 
    value=""  // Empty
/>

<textarea name="fanfic_story_introduction"></textarea>  // Empty

// Form submission creates NEW post
wp_insert_post( array(
    'post_type' => 'fanfiction_story',
    'post_title' => $_POST['fanfic_story_title'],
    // ...
));
Edit Story Shortcode (edit_story_form())
// Story exists - loads it
$story_id = $atts['story_id'];
$story = get_post( $story_id );

// Pre-filled form fields
<input 
    type="text" 
    name="fanfic_story_title" 
    value="<?php echo esc_attr( $story->post_title ); ?>"  // Pre-filled
/>

<textarea name="fanfic_story_introduction">
    <?php echo esc_textarea( $story->post_content ); ?>  // Pre-filled
</textarea>

// Form submission UPDATES existing post
wp_update_post( array(
    'ID' => $story_id,  // Updates existing
    'post_title' => $_POST['fanfic_story_title'],
    // ...
));
Could You Use Only One Template/Shortcode?
Technical Answer: YES, you could consolidate
You could create a unified story form that handles both modes:

public static function story_form( $atts ) {
    $story_id = isset( $atts['story_id'] ) ? absint( $atts['story_id'] ) : 0;
    
    if ( $story_id ) {
        // EDIT MODE
        $story = get_post( $story_id );
        $mode = 'edit';
        $values = array(
            'title' => $story->post_title,
            'intro' => $story->post_content,
            // ...
        );
    } else {
        // CREATE MODE
        $mode = 'create';
        $values = array(
            'title' => '',
            'intro' => '',
            // ...
        );
    }
    
    // Single form that works for both modes
    // ...
}
Practical Answer: Current separation makes sense
The two forms serve different purposes:

| Aspect | Create Form | Edit Form | |--------|-------------|-----------| | Purpose | Initial story creation | Managing existing story | | UI Elements | Simple, focused on basics | Complex with chapters list | | Additional Features | Tips & guidelines sidebar | Chapter management table | | Delete Option | None | Danger zone with delete | | Submit Action | Creates new post | Updates existing post | | Success Flow | Redirects to edit mode | Shows success message | | Context | Standalone page | Story context (breadcrumbs to story) |

Current Dead Code
template-create-story.php is DEAD:

Line 117: Contains [author-create-story-form] shortcode
Has fancy sidebar with tips and guidelines
BUT: Never loaded! Virtual page injects shortcode directly
Could be deleted without breaking anything
Status: ✅ Active - dual-route system
### What needs to be done: Lets delete the use of /base_slug/create-story/ (be sure to delete the slug definition of this both in wizard, and on URL Name settings where we set the slug of this page). Stories will be created via URL/base_slug?action=create-story, to be in line with other actions. You need to be meticulous to delete the remains of this not to polute my code by using dedicated agent. This will be unified with one php file, the template/template-story-form.php

TEMPLATE RENDERING
   → template-story-form.php
   → PHP logic at top determines mode (create vs edit)
   → Single unified template

FORM SUBMISSION (same file)
   → Process POST at top of template
   → Show success/error messages
   → Pre-fill form on errors
   → NO init hook needed


CREATE UNIFIED PHP TEMPLATE
Create: /templates/template-story-form.php

<?php
/**
 * Unified Story Form Template (Preserves Exact Current Design)
 * Handles both CREATE and EDIT modes
 * 
 * @package FanfictionManager
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================================
// DETERMINE MODE
// ============================================================================
$mode = 'create';
$story = null;
$story_id = 0;

if ( is_singular( 'fanfiction_story' ) ) {
    $story = get_post();
    if ( $story && 'fanfiction_story' === $story->post_type ) {
        $mode = 'edit';
        $story_id = $story->ID;
    }
}

// ============================================================================
// HANDLE FORM SUBMISSION
// ============================================================================
$errors = array();
$success = false;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $nonce_action = ( 'create' === $mode ) ? 'fanfic_create_story_action' : 'fanfic_edit_story_action_' . $story_id;
    $nonce_field = ( 'create' === $mode ) ? 'fanfic_create_story_nonce' : 'fanfic_edit_story_nonce';
    
    if ( isset( $_POST[ $nonce_field ] ) && wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) {
        if ( ! is_user_logged_in() ) {
            $errors[] = __( 'You must be logged in.', 'fanfiction-manager' );
        }
        
        $title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
        $introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
        $genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
        $status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
        $image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( $_POST['fanfic_story_image'] ) : '';
        
        if ( empty( $title ) ) {
            $errors[] = __( 'Story title is required.', 'fanfiction-manager' );
        }
        if ( empty( $introduction ) ) {
            $errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
        }
        if ( ! $status ) {
            $errors[] = __( 'Story status is required.', 'fanfiction-manager' );
        }
        
        if ( empty( $errors ) ) {
            if ( 'create' === $mode ) {
                // CREATE
                $current_user = wp_get_current_user();
                $base_slug = sanitize_title( $title );
                $unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );
                
                $story_id = wp_insert_post( array(
                    'post_type'    => 'fanfiction_story',
                    'post_title'   => $title,
                    'post_name'    => $unique_slug,
                    'post_content' => $introduction,
                    'post_status'  => 'draft',
                    'post_author'  => $current_user->ID,
                ) );
                
                if ( ! is_wp_error( $story_id ) ) {
                    if ( ! empty( $genres ) ) {
                        wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
                    }
                    wp_set_post_terms( $story_id, $status, 'fanfiction_status' );
                    if ( ! empty( $image_url ) ) {
                        update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
                    }
                    update_post_meta( $story_id, '_fanfic_views', 0 );
                    
                    $story_permalink = get_permalink( $story_id );
                    $add_chapter_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
                    wp_redirect( $add_chapter_url );
                    exit;
                } else {
                    $errors[] = $story_id->get_error_message();
                }
            } else {
                // EDIT
                if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
                    $errors[] = __( 'You do not have permission to edit this story.', 'fanfiction-manager' );
                } else {
                    $update_result = wp_update_post( array(
                        'ID'           => $story_id,
                        'post_title'   => $title,
                        'post_content' => $introduction,
                    ) );
                    
                    if ( ! is_wp_error( $update_result ) ) {
                        if ( ! empty( $genres ) ) {
                            wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
                        }
                        wp_set_post_terms( $story_id, $status, 'fanfiction_status' );
                        if ( ! empty( $image_url ) ) {
                            update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
                        } else {
                            delete_post_meta( $story_id, '_fanfic_featured_image' );
                        }
                        $success = true;
                    } else {
                        $errors[] = $update_result->get_error_message();
                    }
                }
            }
        }
    }
}

// ============================================================================
// GET DATA FOR FORM
// ============================================================================
$genres = get_terms( array( 'taxonomy' => 'fanfiction_genre', 'hide_empty' => false ) );
$statuses = get_terms( array( 'taxonomy' => 'fanfiction_status', 'hide_empty' => false ) );

if ( 'edit' === $mode && $story ) {
    $current_title = $story->post_title;
    $current_intro = $story->post_content;
    $current_genres = wp_get_post_terms( $story_id, 'fanfiction_genre', array( 'fields' => 'ids' ) );
    $current_status_terms = wp_get_post_terms( $story_id, 'fanfiction_status' );
    $current_status = ! empty( $current_status_terms ) ? $current_status_terms[0]->term_id : 0;
    $current_image = get_post_meta( $story_id, '_fanfic_featured_image', true );
    
    // Check if story has chapters
    $has_chapters = ! empty( get_posts( array(
        'post_type'      => 'fanfiction_chapter',
        'post_parent'    => $story_id,
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) ) );
    
    $story_title = $story->post_title;
} else {
    $current_title = isset( $_POST['fanfic_story_title'] ) ? $_POST['fanfic_story_title'] : '';
    $current_intro = isset( $_POST['fanfic_story_introduction'] ) ? $_POST['fanfic_story_introduction'] : '';
    $current_genres = isset( $_POST['fanfic_story_genres'] ) ? (array) $_POST['fanfic_story_genres'] : array();
    $current_status = isset( $_POST['fanfic_story_status'] ) ? $_POST['fanfic_story_status'] : 0;
    $current_image = isset( $_POST['fanfic_story_image'] ) ? $_POST['fanfic_story_image'] : '';
    $has_chapters = false;
}

// ============================================================================
// RENDER HTML - EXACT COPY OF CURRENT TEMPLATE STRUCTURE
// ============================================================================
?>

<div class="fanfic-template-wrapper">
<?php
// Check if user is logged in
if ( ! is_user_logged_in() ) {
    ?>
    <div class="fanfic-error-notice" role="alert" aria-live="assertive">
        <p><?php esc_html_e( 'You must be logged in to create or edit stories.', 'fanfiction-manager' ); ?></p>
        <p>
            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">
                <?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
            </a>
        </p>
    </div>
    <?php
    return;
}

// EDIT MODE: Check permissions
if ( 'edit' === $mode && ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
    ?>
    <div class="fanfic-error-notice" role="alert" aria-live="assertive">
        <p><?php esc_html_e( 'Access Denied: You do not have permission to edit this story, or the story does not exist.', 'fanfiction-manager' ); ?></p>
        <p>
            <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-primary">
                <?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
            </a>
        </p>
    </div>
    <?php
    return;
}
?>

<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<?php if ( 'edit' === $mode ) : ?>
<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
    <ol class="fanfic-breadcrumb-list">
        <li class="fanfic-breadcrumb-item">
            <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
        </li>
        <li class="fanfic-breadcrumb-item">
            <a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
        </li>
        <li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
            <?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
        </li>
    </ol>
</nav>
<?php else : ?>
<!-- Breadcrumb Navigation for Create -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
    <ol class="fanfic-breadcrumb-list">
        <li class="fanfic-breadcrumb-item">
            <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
        </li>
        <li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
            <?php esc_html_e( 'Create Story', 'fanfiction-manager' ); ?>
        </li>
    </ol>
</nav>
<?php endif; ?>

<!-- Success/Error Messages -->
<?php if ( $success ) : ?>
    <div class="fanfic-success-notice" role="status" aria-live="polite">
        <p><?php esc_html_e( 'Story updated successfully!', 'fanfiction-manager' ); ?></p>
        <button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
    </div>
<?php endif; ?>

<?php if ( ! empty( $errors ) ) : ?>
    <div class="fanfic-error-notice" role="alert" aria-live="assertive">
        <ul>
            <?php foreach ( $errors as $error ) : ?>
                <li><?php echo esc_html( $error ); ?></li>
            <?php endforeach; ?>
        </ul>
        <button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<header class="fanfic-page-header">
    <h1 class="fanfic-page-title">
        <?php echo ( 'create' === $mode ) ? esc_html__( 'Create a New Story', 'fanfiction-manager' ) : esc_html__( 'Edit Your Story', 'fanfiction-manager' ); ?>
    </h1>
    <p class="fanfic-page-description">
        <?php echo ( 'create' === $mode ) ? esc_html__( 'Tell us about your story! Fill out the form below to get started.', 'fanfiction-manager' ) : esc_html__( 'Update your story details below. Changes will be saved immediately.', 'fanfiction-manager' ); ?>
    </p>
</header>

<!-- Info Box -->
<div class="fanfic-info-box" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
    <span class="dashicons dashicons-info" aria-hidden="true"></span>
    <p>
        <?php 
        if ( 'create' === $mode ) {
            esc_html_e( 'All fields marked with an asterisk (*) are required. Your story will be saved as a draft until you add at least one chapter.', 'fanfiction-manager' );
        } else {
            esc_html_e( 'Your story must have an introduction, at least one chapter, a genre, and a status to be published.', 'fanfiction-manager' );
        }
        ?>
    </p>
</div>

<!-- Story Edit Form -->
<section class="fanfic-content-section fanfic-form-section" aria-labelledby="edit-form-heading">
    <h2 id="edit-form-heading"><?php esc_html_e( 'Story Details', 'fanfiction-manager' ); ?></h2>

    <!-- FORM HTML - EXACT COPY FROM SHORTCODE -->
    <div class="fanfic-author-form-wrapper">
        <form class="fanfic-author-form fanfic-edit-story-form" method="post" action="" novalidate>
            <?php 
            if ( 'create' === $mode ) {
                wp_nonce_field( 'fanfic_create_story_action', 'fanfic_create_story_nonce' );
            } else {
                wp_nonce_field( 'fanfic_edit_story_action_' . $story_id, 'fanfic_edit_story_nonce' );
            }
            ?>

            <div class="fanfic-form-section">
                <div class="fanfic-form-field" data-field-type="text">
                    <label for="fanfic_story_title">
                        <?php esc_html_e( 'Story Title', 'fanfiction-manager' ); ?>
                        <span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
                    </label>
                    <input
                        type="text"
                        name="fanfic_story_title"
                        id="fanfic_story_title"
                        class="fanfic-input"
                        required
                        aria-required="true"
                        maxlength="200"
                        value="<?php echo esc_attr( $current_title ); ?>"
                    />
                </div>

                <div class="fanfic-form-field" data-field-type="textarea">
                    <label for="fanfic_story_introduction">
                        <?php esc_html_e( 'Story Introduction', 'fanfiction-manager' ); ?>
                        <span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
                    </label>
                    <textarea
                        name="fanfic_story_introduction"
                        id="fanfic_story_introduction"
                        class="fanfic-textarea"
                        required
                        aria-required="true"
                        rows="8"
                        maxlength="10000"
                    ><?php echo esc_textarea( $current_intro ); ?></textarea>
                    <p class="fanfic-field-description"><?php esc_html_e( 'Brief description of your story (max 10,000 characters).', 'fanfiction-manager' ); ?></p>
                </div>

                <?php if ( ! empty( $genres ) && ! is_wp_error( $genres ) ) : ?>
                    <div class="fanfic-form-field" data-field-type="checkbox">
                        <label><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></label>
                        <div class="fanfic-checkbox-group">
                            <?php foreach ( $genres as $genre ) : ?>
                                <label class="fanfic-checkbox-label">
                                    <input
                                        type="checkbox"
                                        name="fanfic_story_genres[]"
                                        value="<?php echo esc_attr( $genre->term_id ); ?>"
                                        <?php checked( in_array( $genre->term_id, $current_genres ) ); ?>
                                    />
                                    <?php echo esc_html( $genre->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) : ?>
                    <div class="fanfic-form-field" data-field-type="radio">
                        <label>
                            <?php esc_html_e( 'Story Status', 'fanfiction-manager' ); ?>
                            <span class="required" aria-label="<?php esc_attr_e( 'required', 'fanfiction-manager' ); ?>">*</span>
                        </label>
                        <div class="fanfic-radio-group">
                            <?php foreach ( $statuses as $status ) : ?>
                                <label class="fanfic-radio-label">
                                    <input
                                        type="radio"
                                        name="fanfic_story_status"
                                        value="<?php echo esc_attr( $status->term_id ); ?>"
                                        required
                                        <?php checked( $current_status, $status->term_id ); ?>
                                    />
                                    <?php echo esc_html( $status->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="fanfic-form-field" data-field-type="url">
                    <label for="fanfic_story_image">
                        <?php esc_html_e( 'Featured Image URL', 'fanfiction-manager' ); ?>
                    </label>
                    <input
                        type="url"
                        name="fanfic_story_image"
                        id="fanfic_story_image"
                        class="fanfic-input"
                        placeholder="https://"
                        value="<?php echo esc_attr( $current_image ); ?>"
                    />
                    <p class="fanfic-field-description"><?php esc_html_e( 'Optional. Enter a URL to an image for your story cover.', 'fanfiction-manager' ); ?></p>
                </div>
            </div>

            <input type="hidden" name="fanfic_story_id" value="<?php echo esc_attr( $story_id ); ?>" />
            <input type="hidden" name="fanfic_edit_story_submit" value="1" />

            <div class="fanfic-form-actions">
                <?php if ( 'edit' === $mode && ! $has_chapters ) : ?>
                    <!-- New story without chapters: Only Save Draft and Cancel -->
                    <button type="submit" name="fanfic_save_action" value="draft" class="fanfic-btn fanfic-btn-primary">
                        <?php esc_html_e( 'Save Draft', 'fanfiction-manager' ); ?>
                    </button>
                    <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-btn fanfic-btn-secondary">
                        <?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
                    </a>
                <?php elseif ( 'edit' === $mode && $has_chapters ) : ?>
                    <!-- Story with chapters: Save Draft, Publish, and Delete -->
                    <button type="submit" name="fanfic_save_action" value="draft" class="fanfic-btn fanfic-btn-secondary">
                        <?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
                    </button>
                    <button type="submit" name="fanfic_save_action" value="publish" class="fanfic-btn fanfic-btn-primary">
                        <?php esc_html_e( 'Publish', 'fanfiction-manager' ); ?>
                    </button>
                    <button type="button" id="delete-story-trigger" class="fanfic-btn fanfic-btn-danger" data-story-id="<?php echo esc_attr( $story_id ); ?>" data-story-title="<?php echo esc_attr( $story->post_title ); ?>">
                        <?php esc_html_e( 'Delete Story', 'fanfiction-manager' ); ?>
                    </button>
                    <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-btn fanfic-btn-secondary">
                        <?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
                    </a>
                <?php else : ?>
                    <!-- CREATE MODE -->
                    <button type="submit" class="fanfic-button-primary">
                        <?php esc_html_e( 'Create Story', 'fanfiction-manager' ); ?>
                    </button>
                    <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button-secondary">
                        <?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>

<?php if ( 'edit' === $mode ) : ?>
<!-- EDIT MODE ONLY: Chapters Management Section - EXACT COPY FROM CURRENT TEMPLATE -->
<section class="fanfic-content-section fanfic-chapters-section" aria-labelledby="chapters-heading">
    <div class="fanfic-section-header">
        <h2 id="chapters-heading"><?php esc_html_e( 'Chapters in This Story', 'fanfiction-manager' ); ?></h2>
        <a href="<?php echo esc_url( add_query_arg( 'action', 'add-chapter', get_permalink( $story_id ) ) ); ?>" class="fanfic-button-primary">
            <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
            <?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
        </a>
    </div>

    <!-- Chapters List -->
    <div class="fanfic-chapters-list">
        <?php
        // Get all chapters for this story
        $chapters_args = array(
            'post_type'      => 'fanfiction_chapter',
            'post_parent'    => $story_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'post_status'    => array( 'publish', 'draft', 'pending' ),
        );

        $chapters_query = new WP_Query( $chapters_args );
        $chapters = $chapters_query->posts;

        // Sort chapters by chapter number
        if ( ! empty( $chapters ) ) {
            usort( $chapters, function( $a, $b ) {
                $number_a = get_post_meta( $a->ID, '_fanfic_chapter_number', true );
                $number_b = get_post_meta( $b->ID, '_fanfic_chapter_number', true );
                return absint( $number_a ) - absint( $number_b );
            } );
        }

        if ( ! empty( $chapters ) ) :
            ?>
            <table class="fanfic-table" role="table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Chapter #', 'fanfiction-manager' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Title', 'fanfiction-manager' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Word Count', 'fanfiction-manager' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ( $chapters as $chapter ) :
                        $chapter_id = $chapter->ID;
                        $chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
                        $stored_chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

                        if ( 'prologue' === $chapter_type ) {
                            $display_number = __( 'Prologue', 'fanfiction-manager' );
                        } elseif ( 'epilogue' === $chapter_type ) {
                            $display_number = __( 'Epilogue', 'fanfiction-manager' );
                        } else {
                            $display_number = sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $stored_chapter_number );
                        }

                        $content = $chapter->post_content;
                        $word_count = str_word_count( wp_strip_all_tags( $content ) );

                        $status = $chapter->post_status;
                        $status_labels = array(
                            'publish' => __( 'Published', 'fanfiction-manager' ),
                            'draft'   => __( 'Draft', 'fanfiction-manager' ),
                            'pending' => __( 'Pending', 'fanfiction-manager' ),
                        );
                        $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
                        ?>
                        <tr>
                            <td data-label="<?php esc_attr_e( 'Chapter #', 'fanfiction-manager' ); ?>">
                                <strong><?php echo esc_html( $display_number ); ?></strong>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Title', 'fanfiction-manager' ); ?>">
                                <?php echo esc_html( $chapter->post_title ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Status', 'fanfiction-manager' ); ?>">
                                <span class="fanfic-status-badge fanfic-status-<?php echo esc_attr( $status ); ?>">
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Word Count', 'fanfiction-manager' ); ?>">
                                <?php echo esc_html( number_format_i18n( $word_count ) ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Actions', 'fanfiction-manager' ); ?>">
                                <div class="fanfic-actions-buttons">
                                    <a href="<?php echo esc_url( add_query_arg( 'action', 'edit', get_permalink( $chapter_id ) ) ); ?>" class="fanfic-button-small">
                                        <?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
                                    </a>
                                <?php if ( 'publish' === $status ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button-small" target="_blank">
                                        <?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
                                    </a>
                                <?php endif; ?>
                                    <button type="button" class="fanfic-button-small fanfic-button-danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>">
                                        <?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            wp_reset_postdata();
        else :
            ?>
            <div class="fanfic-empty-state" role="status">
                <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                <p><?php esc_html_e( 'No chapters yet. Add your first chapter to get started!', 'fanfiction-manager' ); ?></p>
                <a href="<?php echo esc_url( add_query_arg( 'action', 'add-chapter', get_permalink( $story_id ) ) ); ?>" class="fanfic-button-primary">
                    <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Add First Chapter', 'fanfiction-manager' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Danger Zone - EXACT COPY FROM CURRENT TEMPLATE -->
<section class="fanfic-content-section fanfic-danger-zone" aria-labelledby="danger-heading">
    <h2 id="danger-heading" class="fanfic-danger-title">
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <?php esc_html_e( 'Danger Zone', 'fanfiction-manager' ); ?>
    </h2>

    <div class="fanfic-danger-content">
        <div class="fanfic-danger-info">
            <h3><?php esc_html_e( 'Delete This Story', 'fanfiction-manager' ); ?></h3>
            <p><?php esc_html_e( 'Once you delete a story, there is no going back. All chapters and data will be permanently removed.', 'fanfiction-manager' ); ?></p>
        </div>
        <button type="button" id="delete-story-button" class="fanfic-button-danger" data-story-id="<?php echo absint( $story_id ); ?>" data-story-title="<?php echo esc_attr( $story_title ); ?>">
            <?php esc_html_e( 'Delete This Story', 'fanfiction-manager' ); ?>
        </button>
    </div>

    <p class="fanfic-danger-warning">
        <strong><?php esc_html_e( 'Warning:', 'fanfiction-manager' ); ?></strong>
        <?php esc_html_e( 'This action cannot be undone.', 'fanfiction-manager' ); ?>
    </p>
</section>

<!-- Delete Confirmation Modal - EXACT COPY -->
<div id="delete-confirm-modal" class="fanfic-modal" role="dialog" aria-labelledby="modal-title" aria-modal="true" style="display: none;">
    <div class="fanfic-modal-overlay"></div>
    <div class="fanfic-modal-content">
        <h2 id="modal-title"><?php esc_html_e( 'Confirm Deletion', 'fanfiction-manager' ); ?></h2>
        <p id="modal-message"></p>
        <div class="fanfic-modal-actions">
            <button type="button" id="confirm-delete" class="fanfic-button-danger">
                <?php esc_html_e( 'Yes, Delete', 'fanfiction-manager' ); ?>
            </button>
            <button type="button" id="cancel-delete" class="fanfic-button-secondary">
                <?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- Publish Story Prompt Modal - EXACT COPY -->
<div id="publish-prompt-modal" class="fanfic-modal" role="dialog" aria-labelledby="publish-modal-title" aria-modal="true" style="display: none;">
    <div class="fanfic-modal-overlay"></div>
    <div class="fanfic-modal-content">
        <h2 id="publish-modal-title"><?php esc_html_e( 'Ready to Publish?', 'fanfiction-manager' ); ?></h2>
        <p><?php esc_html_e( 'Great! Your story now has its first published chapter. You can now publish your story to make it visible to readers, or keep it as a draft to continue working on it.', 'fanfiction-manager' ); ?></p>
        <div class="fanfic-modal-actions">
            <button type="button" id="publish-story-now" class="fanfic-button-primary" data-story-id="<?php echo absint( $story_id ); ?>">
                <?php esc_html_e( 'Publish Story Now', 'fanfiction-manager' ); ?>
            </button>
            <button type="button" id="keep-as-draft" class="fanfic-button-secondary">
                <?php esc_html_e( 'Keep as Draft', 'fanfiction-manager' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- Breadcrumb Navigation (Bottom) -->
<nav class="fanfic-breadcrumb fanfic-breadcrumb-bottom" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
    <ol class="fanfic-breadcrumb-list">
        <li class="fanfic-breadcrumb-item">
            <a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
        </li>
        <li class="fanfic-breadcrumb-item">
            <a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
        </li>
        <li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
            <?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
        </li>
    </ol>
</nav>

<!-- ALL JAVASCRIPT - EXACT COPY FROM LINES 330-589 of template-edit-story.php -->
<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Notice close buttons
        var closeButtons = document.querySelectorAll('.fanfic-notice-close');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var notice = this.closest('.fanfic-success-notice, .fanfic-error-notice');
                if (notice) {
                    notice.style.display = 'none';
                }
            });
        });

        // Delete story confirmation
        var deleteStoryButton = document.getElementById('delete-story-button');
        var modal = document.getElementById('delete-confirm-modal');
        var confirmButton = document.getElementById('confirm-delete');
        var cancelButton = document.getElementById('cancel-delete');
        var modalMessage = document.getElementById('modal-message');

        if (deleteStoryButton) {
            deleteStoryButton.addEventListener('click', function() {
                var storyTitle = this.getAttribute('data-story-title');
                modalMessage.textContent = '<?php esc_html_e( 'Are you sure you want to delete', 'fanfiction-manager' ); ?> "' + storyTitle + '"? <?php esc_html_e( 'This will also delete all chapters.', 'fanfiction-manager' ); ?>';
                modal.style.display = 'block';
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        if (confirmButton) {
            confirmButton.addEventListener('click', function() {
                var storyId = deleteStoryButton.getAttribute('data-story-id');
                window.location.href = '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>?action=delete_story&story_id=' + storyId + '&_wpnonce=<?php echo esc_js( wp_create_nonce( 'delete_story_' . $story_id ) ); ?>';
            });
        }

        // Chapter delete with AJAX
        var chapterDeleteButtons = document.querySelectorAll('[data-chapter-id]');
        chapterDeleteButtons.forEach(function(button) {
            if (button.classList.contains('fanfic-button-danger')) {
                button.addEventListener('click', function() {
                    var chapterTitle = this.getAttribute('data-chapter-title');
                    var chapterId = this.getAttribute('data-chapter-id');
                    
                    if (confirm('<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
                        // Simple implementation - could add AJAX here
                        window.location.href = '<?php echo esc_js( admin_url( 'admin-post.php' ) ); ?>?action=fanfic_delete_chapter&chapter_id=' + chapterId;
                    }
                });
            }
        });
    });
})();
</script>

<?php endif; // End edit mode only sections ?>

</div>


STEP 2: UPDATE ROUTING
Update class-fanfic-url-manager.php
Change line 743-746 from:

'create-story' => array(
    'title'     => __( 'Create Story', 'fanfiction-manager' ),
    'shortcode' => 'author-create-story-form',  // OLD
),


To:

'create-story' => array(
    'title'     => __( 'Create Story', 'fanfiction-manager' ),
    'template'  => 'story-form',  // NEW: Load template directly
),
Update class-fanfic-url-manager.php - Modify inject_virtual_page_content()
Change around line 706-728 to support templates:

public function inject_virtual_page_content( $content ) {
    $post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
    
    if ( ! $post || ! isset( $post->fanfic_page_key ) ) {
        return $content;
    }
    
    if ( ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }
    
    $page_config = $this->get_virtual_page_config( $post->fanfic_page_key );
    
    if ( ! $page_config ) {
        return $content;
    }
    
    // NEW: Check if template is specified instead of shortcode
    if ( ! empty( $page_config['template'] ) ) {
        ob_start();
        $template_path = FANFIC_PLUGIN_DIR . 'templates/template-' . $page_config['template'] . '.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
        return ob_get_clean();
    }
    
  
    
    return $content;
}
Update class-fanfic-templates.php
Change around line 76-85 for edit mode:

if ( is_singular( 'fanfiction_story' ) ) {
    $content_template = 'single-fanfiction_story.php';
    
    if ( ! empty( $action ) ) {
        switch ( $action ) {
            case 'edit':
                // NEW: Use unified template
                $content_template = 'template-story-form.php';
                break;
            case 'add-chapter':
                $content_template = 'template-edit-chapter.php';
                break;
        }
    }
    
    global $fanfic_content_template;
    $fanfic_content_template = $content_template;
    
    $custom_template = self::locate_template( 'fanfiction-page-template.php' );
    if ( $custom_template ) {
        return $custom_template;
    }
}



STEP 3: WHAT CAN BE DELETED
Delete Entirely:
❌ /templates/template-create-story.php (already dead)

❌ /templates/template-edit-story.php (replaced by unified template)

❌ Shortcode registrations in class-fanfic-shortcodes-author-forms.php line 37-38:

add_shortcode( 'author-create-story-form', array( __CLASS__, 'create_story_form' ) );
add_shortcode( 'author-edit-story-form', array( __CLASS__, 'edit_story_form' ) );
❌ Shortcode functions in class-fanfic-shortcodes-author-forms.php:

create_story_form() (line 355-507)
edit_story_form() (line 509-655 approx)
❌ Init hook handlers in class-fanfic-shortcodes-author-forms.php:

handle_create_story_submission() (line 1482-1569)
handle_edit_story_submission() (line 1577+)
The hook registration:
add_action( 'init', array( __CLASS__, 'handle_create_story_submission' ) );
add_action( 'init', array( __CLASS__, 'handle_edit_story_submission' ) );






12. CHAPTER VIEW
Type: Custom Post Type Single
Location: /templates/template-chapter-view.php
Content Source: Admin-configurable template string loaded from fanfic_chapter_view_template option

How It Works:

$template = get_option('fanfic_chapter_view_template', '');
$chapter_content = the_content(); // Gets actual chapter text
$template = str_replace('<!-- Chapter content -->', $chapter_content, $template);
echo do_shortcode($template);
Default Template Uses These Shortcodes:

[chapter-breadcrumb]
[chapters-nav]
[chapter-comments]
[story-title]
And chapter content placeholder
Registered In: class-fanfic-shortcodes-navigation.php, class-fanfic-shortcodes-comments.php

Special Feature: Draft story access control (lines 20-51)

Status: ✅ Active - User-configurable shortcode template
###DONE: Renamed single-fanfiction_chapter.php to template-chapter-view.php. TODO: Shortcodes extra needed are [chapter-ratings] [chapter-views] - [chapter-breadcrumb] should become [breadcrumbs] and be usable in all plugin pages.






13. CHAPTER EDIT/ADD
Type: Action-based template
Location: Accessed via ?action=edit on chapter OR ?action=add-chapter on story

Template: /templates/template-edit-chapter.php

Shortcodes Used:

[author-edit-chapter-form] (edit mode)
[author-create-chapter-form] (create mode)
Registered In: class-fanfic-shortcodes-author-forms.php:39-40

Template Chain:

Action-based routing:
  → class-fanfic-templates.php:98-114
  → If action=edit: loads template-edit-chapter.php
  → Contains conditional shortcode based on mode

1. Template File (template-edit-chapter.php) provides:
✅ Inline CSS styles (lines 28-141) - Modal and badge styles
✅ Debug logging (lines 20-23, 144-196)
✅ Security/permissions checks (lines 147-212)
✅ Mode detection logic (lines 161-193) - Determines create vs edit
✅ Breadcrumb navigation (lines 248-261)
✅ Success/error messages (lines 264-276)
✅ Page header (lines 278-282)
✅ Info box (lines 284-290)
✅ Section heading (line 294)
✅ Quick Actions section (lines 304-323) - Back to Story, View Chapter, Dashboard links
✅ Danger Zone section (lines 325-347) - Delete chapter (edit only)
✅ Delete confirmation modal (lines 349-364)
✅ Delete JavaScript (lines 367-419)
✅ Publish story prompt modal (lines 423-438) - Shown after first chapter
✅ Publish prompt JavaScript (lines 441-624)
✅ Overall wrapper structure (get_header() on line 25, template wrapper div)


2. Shortcode Functions provide:
Two separate functions:

create_chapter_form() (line 768) - Create mode
edit_chapter_form() (line 1028) - Edit mode
Both output (via ob_start()):

✅ Form header with story title
✅ Form wrapper div
✅ Nonce field
✅ Chapter Type radio buttons (Prologue/Chapter/Epilogue)
✅ Chapter Number field (conditional - only for regular chapters)
✅ Chapter Title input
✅ Chapter Content textarea
✅ Hidden fields (story_id, submit flag)
✅ Submit buttons (Publish Chapter / Save as Draft / Cancel)
✅ JavaScript for toggling chapter number field (lines 982-1013)



Current Flow:

ROUTE 1: Add Chapter
URL: /fanfiction/stories/{story-slug}/?action=add-chapter

Method: Action parameter on story permalink
Template File: ✅ template-edit-chapter.php
Shortcode Used: [author-create-chapter-form story_id="X"]

How It Works:

Story URL with ?action=add-chapter
  → class-fanfic-templates.php:98-114 detects action
  → Loads: template-edit-chapter.php
  → Template detects: is_singular('fanfiction_story')
  → Sets: $story_id = get_the_ID(), $chapter_id = 0
  → Calls: do_shortcode('[author-create-chapter-form story_id="X"]')
  → Shortcode renders: Empty form fields
  → Form submission: Creates NEW chapter


ROUTE 2: Edit Chapter
URL: /fanfiction/stories/{story-slug}/{chapter-slug}/?action=edit
Method: Action parameter on chapter permalink
Template File: ✅ template-edit-chapter.php (same file!)
Shortcode Used: [author-edit-chapter-form chapter_id="X"]

How It Works:

Chapter URL with ?action=edit
  → class-fanfic-templates.php detects action on chapter
  → Loads: template-edit-chapter.php
  → Template detects: is_singular('fanfiction_chapter')
  → Sets: $chapter_id = get_the_ID(), $story_id = $chapter->post_parent
  → Calls: do_shortcode('[author-edit-chapter-form chapter_id="X"]')
  → Shortcode renders: Pre-filled form fields
  → Form submission: Updates EXISTING chapter


Code Comparison: Create vs Edit Shortcodes
Create Chapter Form (create_chapter_form() - line 768)
// No chapter exists
$story_id = $atts['story_id'];

// Empty form fields
<input 
    type="text" 
    name="fanfic_chapter_title" 
    value=""  // Empty
/>

<textarea name="fanfic_chapter_content"></textarea>  // Empty

// Get next available chapter number
$available_numbers = self::get_available_chapter_numbers( $story_id );
$default_number = $available_numbers[0]; // Lowest available

// Check if prologue/epilogue exist
$has_prologue = self::story_has_prologue( $story_id );
$has_epilogue = self::story_has_epilogue( $story_id );

// Disable if already exist
<?php disabled( $has_prologue ); ?> // For prologue radio
<?php disabled( $has_epilogue ); ?> // For epilogue radio

// Form submission creates NEW post
wp_insert_post( array(
    'post_type'   => 'fanfiction_chapter',
    'post_parent' => $story_id,
    'post_title'  => $_POST['fanfic_chapter_title'],
    'post_content' => $_POST['fanfic_chapter_content'],
    // ...
));
Edit Chapter Form (edit_chapter_form() - line 1028)
// Chapter exists - loads it
$chapter_id = $atts['chapter_id'];
$chapter = get_post( $chapter_id );
$story_id = $chapter->post_parent;

// Get current chapter data
$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

// Pre-filled form fields
<input 
    type="text" 
    name="fanfic_chapter_title" 
    value="<?php echo esc_attr( $chapter->post_title ); ?>"  // Pre-filled
/>

<textarea name="fanfic_chapter_content">
    <?php echo esc_textarea( $chapter->post_content ); ?>  // Pre-filled
</textarea>

// Check chapter number (preserve current chapter type from conflicting)
<?php checked( $chapter_type === 'prologue' ); ?>
<?php disabled( $has_prologue && $chapter_type !== 'prologue' ); ?>

// Form submission UPDATES existing post
wp_update_post( array(
    'ID'          => $chapter_id,  // Updates existing
    'post_title'  => $_POST['fanfic_chapter_title'],
    'post_content' => $_POST['fanfic_chapter_content'],
    // ...
));
Form Fields Breakdown:
Both forms have these fields:

Chapter Type (Radio buttons)

Prologue (disabled if story already has one and not editing prologue)
Chapter (default)
Epilogue (disabled if story already has one and not editing epilogue)
Chapter Number (Number input - conditionally shown)

Only visible when "Chapter" type is selected
JavaScript toggles visibility (lines 982-1013)
Auto-suggests next available number (create mode)
Shows current number (edit mode)
Chapter Title (Text input)

Required field
Max 200 characters
Chapter Content (Textarea)

Required field
20 rows tall
Supports basic HTML
Action Buttons

Publish Chapter (primary button)
Save as Draft (secondary button)
Cancel (link button)
Form Submission Handlers:
Currently handled via init hooks (same pattern as story forms):

// In class-fanfic-shortcodes-author-forms.php __construct():
add_action( 'init', array( __CLASS__, 'handle_create_chapter_submission' ) );
add_action( 'init', array( __CLASS__, 'handle_edit_chapter_submission' ) );

// Handlers:
handle_create_chapter_submission()  // Line 1704+
handle_edit_chapter_submission()    // Line 1854+
These run on EVERY page load, check for POST data, process form, then redirect.

Special Features:
Prologue/Epilogue Logic

Only one prologue per story
Only one epilogue per story
Auto-assigned chapter numbers (0 for prologue, 999 for epilogue)
Disabled radio buttons if already exist
Chapter Number Auto-suggestion

Scans existing chapters
Suggests lowest available number
Prevents duplicate numbers
Publish Story Prompt (lines 423-624)

Shows modal after first chapter is published
Asks if user wants to publish the story
AJAX publish via fanfic_publish_story action
Dynamic JavaScript

Toggle chapter number field based on type
Delete confirmation modal
Publish prompt modal
Notice dismissal
Template Routing:
In class-fanfic-templates.php:

// Lines 98-114
if ( is_singular( 'fanfiction_chapter' ) ) {
    $content_template = 'template-chapter-view.php';

    if ( ! empty( $action ) && 'edit' === $action ) {
        $content_template = 'template-edit-chapter.php';
    }
}

// Lines 76-85
if ( is_singular( 'fanfiction_story' ) ) {
    $content_template = 'single-fanfiction_story.php';
    
    if ( ! empty( $action ) ) {
        switch ( $action ) {
            case 'edit':
                $content_template = 'template-story-form.php';
                break;
            case 'add-chapter':
                $content_template = 'template-edit-chapter.php';  // Same template!
                break;
        }
    }
}
Key Point: The template detects mode internally based on is_singular() checks.

What Creates the Current Look - COMPLETE BREAKDOWN:
From Template File:
✅ <style> tags with modal CSS (lines 28-141)
✅ get_header() - Your theme header
✅ Breadcrumb nav structure
✅ Success/error message containers
✅ Page header (h1 + description)
✅ Info box with dashicons
✅ Section headings
✅ "Quick Actions" section with 3 buttons
✅ "Danger Zone" section (edit only)
✅ Delete confirmation modal HTML
✅ Publish prompt modal HTML
✅ All JavaScript for modals and interactions
✅ <div class="fanfic-template-wrapper"> wrapper


From Shortcode Function:
✅ <div class="fanfic-author-form-wrapper">
✅ <div class="fanfic-form-header"><h2>
✅ <form class="fanfic-author-form fanfic-create/edit-chapter-form">
✅ Nonce field
✅ <div class="fanfic-form-section">
✅ All form fields with proper classes
✅ Submit buttons with fanfic-btn classes
✅ JavaScript for chapter type toggle


PURE PHP TEMPLATE CONVERSION - template-chapter-form.php

Here's how you'd convert to a single unified PHP template (I'll juste give a condensed version focusing on the key parts):

<?php
/**
 * Unified Chapter Form Template
 * Handles both ADD and EDIT modes
 * 
 * @package FanfictionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================================
// DETERMINE MODE
// ============================================================================
$mode = 'create';
$chapter = null;
$chapter_id = 0;
$story_id = 0;

// Check if editing existing chapter
if ( is_singular( 'fanfiction_chapter' ) ) {
    $chapter = get_post();
    if ( $chapter && 'fanfiction_chapter' === $chapter->post_type ) {
        $mode = 'edit';
        $chapter_id = $chapter->ID;
        $story_id = $chapter->post_parent;
    }
}
// Check if adding chapter to story
elseif ( is_singular( 'fanfiction_story' ) ) {
    $story_id = get_the_ID();
    $mode = 'create';
}

// Fallback to URL params
if ( ! $story_id && isset( $_GET['story_id'] ) ) {
    $story_id = absint( $_GET['story_id'] );
}
if ( ! $chapter_id && isset( $_GET['chapter_id'] ) ) {
    $chapter_id = absint( $_GET['chapter_id'] );
    $chapter = get_post( $chapter_id );
    $story_id = $chapter ? $chapter->post_parent : 0;
    $mode = 'edit';
}

// ============================================================================
// HANDLE FORM SUBMISSION
// ============================================================================
$errors = array();
$success = false;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    
    $nonce_action = ( 'create' === $mode ) 
        ? 'fanfic_create_chapter_action_' . $story_id 
        : 'fanfic_edit_chapter_action_' . $chapter_id;
    $nonce_field = ( 'create' === $mode ) 
        ? 'fanfic_create_chapter_nonce' 
        : 'fanfic_edit_chapter_nonce';
    
    if ( isset( $_POST[ $nonce_field ] ) && wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) {
        
        if ( ! is_user_logged_in() ) {
            $errors[] = __( 'You must be logged in.', 'fanfiction-manager' );
        }
        
        // Get form data
        $chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
        $chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
        $chapter_title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
        $chapter_content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';
        $chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'draft';
        
        // Validate
        if ( empty( $chapter_title ) ) {
            $errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
        }
        if ( empty( $chapter_content ) ) {
            $errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
        }
        
        // Assign chapter number based on type
        if ( 'prologue' === $chapter_type ) {
            $chapter_number = 0;
        } elseif ( 'epilogue' === $chapter_type ) {
            $chapter_number = 999;
        } elseif ( ! $chapter_number ) {
            $errors[] = __( 'Chapter number is required for regular chapters.', 'fanfiction-manager' );
        }
        
        if ( empty( $errors ) ) {
            
            if ( 'create' === $mode ) {
                // CREATE MODE
                $chapter_id = wp_insert_post( array(
                    'post_type'    => 'fanfiction_chapter',
                    'post_title'   => $chapter_title,
                    'post_content' => $chapter_content,
                    'post_status'  => ( 'publish' === $chapter_action ) ? 'publish' : 'draft',
                    'post_parent'  => $story_id,
                    'post_author'  => get_current_user_id(),
                ) );
                
                if ( ! is_wp_error( $chapter_id ) ) {
                    update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );
                    update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
                    
                    // Redirect to edit chapter with success
                    $redirect_url = add_query_arg( 
                        array( 'action' => 'edit', 'success' => 'true' ), 
                        get_permalink( $chapter_id ) 
                    );
                    wp_redirect( $redirect_url );
                    exit;
                } else {
                    $errors[] = $chapter_id->get_error_message();
                }
                
            } else {
                // EDIT MODE
                if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
                    $errors[] = __( 'Permission denied.', 'fanfiction-manager' );
                } else {
                    $update_result = wp_update_post( array(
                        'ID'           => $chapter_id,
                        'post_title'   => $chapter_title,
                        'post_content' => $chapter_content,
                        'post_status'  => ( 'publish' === $chapter_action ) ? 'publish' : 'draft',
                    ) );
                    
                    if ( ! is_wp_error( $update_result ) ) {
                        update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );
                        update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
                        $success = true;
                    } else {
                        $errors[] = $update_result->get_error_message();
                    }
                }
            }
        }
    }
}

// ============================================================================
// GET DATA FOR FORM
// ============================================================================

$story = get_post( $story_id );
$story_title = $story ? $story->post_title : __( 'Unknown Story', 'fanfiction-manager' );

// Get current values (for edit mode or on error)
if ( 'edit' === $mode && $chapter ) {
    $current_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
    $current_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
    $current_title = $chapter->post_title;
    $current_content = $chapter->post_content;
} else {
    $current_type = isset( $_POST['fanfic_chapter_type'] ) ? $_POST['fanfic_chapter_type'] : 'chapter';
    $current_number = isset( $_POST['fanfic_chapter_number'] ) ? $_POST['fanfic_chapter_number'] : '';
    $current_title = isset( $_POST['fanfic_chapter_title'] ) ? $_POST['fanfic_chapter_title'] : '';
    $current_content = isset( $_POST['fanfic_chapter_content'] ) ? $_POST['fanfic_chapter_content'] : '';
}

// Check if prologue/epilogue exist
$has_prologue = /* check function */;
$has_epilogue = /* check function */;

get_header();
?>

<!-- ALL YOUR EXISTING TEMPLATE HTML FROM LINES 28-624 -->
<!-- Just replace the shortcode calls (lines 297-301) with direct form HTML -->
<!-- Keep all breadcrumbs, modals, JavaScript, etc. -->

<section class="fanfic-content-section">
    <h2><?php echo esc_html( $chapter_id ? __( 'Chapter Details', 'fanfiction-manager' ) : __( 'New Chapter', 'fanfiction-manager' ) ); ?></h2>
    
    <!-- DIRECT FORM HTML - NO SHORTCODE -->
    <div class="fanfic-author-form-wrapper">
        <form class="fanfic-author-form" method="post" action="" novalidate>
            <?php 
            if ( 'create' === $mode ) {
                wp_nonce_field( 'fanfic_create_chapter_action_' . $story_id, 'fanfic_create_chapter_nonce' );
            } else {
                wp_nonce_field( 'fanfic_edit_chapter_action_' . $chapter_id, 'fanfic_edit_chapter_nonce' );
            }
            ?>
            
            <!-- ALL FORM FIELDS HERE - COPY FROM SHORTCODE LINES 862-979 -->
            <!-- Chapter Type, Number, Title, Content fields -->
            <!-- Submit buttons -->
            
        </form>
    </div>
</section>

<?php get_footer(); ?>


WHAT CAN BE DELETED:
After Conversion:
❌ Shortcode registrations (lines 39-40 in class-fanfic-shortcodes-author-forms.php):

add_shortcode( 'author-create-chapter-form', array( __CLASS__, 'create_chapter_form' ) );
add_shortcode( 'author-edit-chapter-form', array( __CLASS__, 'edit_chapter_form' ) );
❌ Shortcode functions:

create_chapter_form() (lines 768-1017) - ~250 lines
edit_chapter_form() (lines 1028-1400+) - ~400 lines
❌ Init hook handlers:

handle_create_chapter_submission() (line 1704+) - ~150 lines
handle_edit_chapter_submission() (line 1854+) - ~200 lines
Hook registrations in constructor
❌ Helper functions (if not used elsewhere):

get_available_chapter_numbers()  - check first if it isnt used
story_has_prologue() - check first if it isnt used
story_has_epilogue() - check first if it isnt used



### What needs to be done:  This will be unified with one php file, the template/template-chapter-form.php that detects the add or edit, similar to the Story add/edit logic.


14. MEMBERS DIRECTORY
Type: Virtual Page
Location: /fanfiction/members/
Content Source: Shortcode
Shortcode Used: [user-profile]
Registered In: class-fanfic-shortcodes-profile.php:18

Template Chain:

Virtual Page System
  → class-fanfic-url-manager.php:751-754
  → Injects: [user-profile] shortcode
  → Processed by: Fanfic_Shortcodes_Profile::user_profile()
Note: This is confusing! The members directory page uses [user-profile] shortcode, but individual member pages use /templates/template-view-profile.php. The shortcode likely shows a directory/list view.

Status: ✅ Active shortcode
### What needs to be done:  No shortcode. We need to check if this page is showing users with plugin roles + wordpress admins. It should output a list with the author display name with link to their profile, how many published stories they have and if they have a special role, not it (Admin, Moderator, else dont). Using the most optimized WP way query to do this, (search online for best practices). Use a php template named template-user-list.php



15. SEARCH PAGE
Type: Virtual Page
Location: /fanfiction/search/
Content Source: Shortcode
Shortcode Used: [search-results]
Registered In: class-fanfic-shortcodes-search.php:34

Template Chain:

Virtual Page System
  → class-fanfic-url-manager.php:747-750
  → Injects: [search-results] shortcode
  → Processed by: Fanfic_Shortcodes_Search::search_results()
Status: ✅ Active shortcode
### What needs to be done:  No shortcode. See what shorcode does, code it to template-search-page.php and delete all the shortcode logic and class-fanfic-shortcodes-search.php:34

16. DASHBOARD
Type: Virtual Page
Location: /fanfiction/dashboard/
Content Source: Shortcode
Shortcode Used: [user-dashboard]
Registered In: class-fanfic-shortcodes-user.php:47

Template Chain:

Virtual Page System
  → class-fanfic-url-manager.php:739-742
  → Injects: [user-dashboard] shortcode
  → Processed by: Fanfic_Shortcodes_User::user_dashboard()
Status: ✅ Active shortcode
### What needs to be done:  No shortcode. See what shorcode does, code it to template-dashboard.php and delete all the shortcode logic on other files.

TEMPLATE FILES STATUS
Templates Folder Files:
| Template File | Used? | How? | |--------------|-------|------| | fanfiction-page-template.php | ✅ YES | Main wrapper for all plugin pages (classic themes) | | single-fanfiction_story.php | ✅ YES | Story view - processes shortcode template from settings | | template-chapter-view.php | ✅ YES | Chapter view - processes shortcode template from settings | | archive-fanfiction_story.php | ✅ YES | Story archive - pure PHP, NO shortcodes | | template-dashboard.php | ❌ NO | DEAD - Virtual page uses shortcode instead | | template-create-story.php | ❌ NO | DEAD - Virtual page uses shortcode instead | | template-edit-story.php | ✅ YES | Story edit form | | template-edit-chapter.php | ✅ YES | Chapter create/edit form | | template-search.php | ❌ NO | DEAD - Virtual page uses shortcode instead | | template-login.php | ❌ NO | DEAD - WordPress page uses shortcode instead | | template-register.php | ❌ NO | DEAD - WordPress page uses shortcode instead | | template-edit-profile.php | ✅ YES | Profile edit form with shortcodes | | template-view-profile.php | ✅ YES | User profile view with shortcodes | | template-members.php | ❌ NO | DEAD - Virtual page uses shortcode instead | | template-comments.php | ❓ UNKNOWN | Not referenced anywhere I found | | template-password-reset.php | ❌ NO | DEAD - WordPress page uses shortcode instead | | template-error.php | ❌ NO | DEAD - WordPress page uses shortcode instead | | template-maintenance.php | ❌ NO | DEAD - WordPress page uses shortcode instead |

DEAD CODE ANALYSIS
Potentially Unused Template Files (8 files):
These template files exist but are NEVER loaded because the pages use shortcodes directly in WordPress page content:

template-dashboard.php - Virtual page uses [user-dashboard] shortcode
template-create-story.php - Virtual page uses [author-create-story-form] shortcode
template-search.php - Virtual page uses [search-results] shortcode
template-members.php - Virtual page uses [user-profile] shortcode
template-login.php - WP page contains [fanfic-login-form] shortcode
template-register.php - WP page contains [fanfic-register-form] shortcode
template-password-reset.php - WP page contains [fanfic-password-reset-form] shortcode
template-error.php - WP page contains [fanfic-error-message] shortcode
template-maintenance.php - WP page contains [fanfic-maintenance-message] shortcode
These files are legacy from the old system where content was loaded from template files. Now they're replaced by shortcodes in page content.

Comments Template:
template-comments.php - I couldn't find any reference to this file being loaded. It might be completely unused.

ARCHITECTURE SUMMARY
Your plugin uses THREE different content delivery methods:

Method 1: Virtual Pages (4 pages)
Dashboard
Create Story
Search
Members (directory)
How it works: Creates fake WP_Post objects, injects shortcodes via the_content filter

Method 2: WordPress Pages with Shortcodes (6 pages)
Main page (if custom_homepage mode)
Login
Register
Password Reset
Error
Maintenance
How it works: Real database pages with shortcode content, wrapped in fanfiction-page-template.php

Method 3: Custom Post Types with Template Strings (2 types)
Story View
Chapter View
How it works: Loads template string from options, processes shortcodes dynamically

Method 4: Pure PHP Templates (1 page)
Story Archive
How it works: Traditional WordPress template file with PHP/HTML, no shortcodes

RECOMMENDATIONS
Delete Dead Template Files - 9 template files can be safely removed
Investigate template-comments.php - Determine if it's needed or delete it
Consolidate Architecture - Having 4 different content delivery methods makes maintenance complex
Document Template Overrides - If you want theme developers to override templates, document which files are actually used
Update Load Template Function - Fanfic_Templates::load_template_content() (line 1144) tries to load template files, but many are never called


What do i want you to do: Create several agents (one per page) to implement the changes needed, with an orchestrator. Run then in parallel, stop each time you need a clarification. The goal is to clean up dead code and files and optimize my approach.
