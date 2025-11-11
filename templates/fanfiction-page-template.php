<?php
/**
 * Fanfiction Page Template
 *
 * This template provides universal HTML5 semantic tag detection that works
 * with ANY WordPress theme without hardcoding theme names.
 *
 * How it works:
 * - Captures and analyzes get_header() output to detect <main> tags
 * - Intelligently decides whether to output <article> tags based on WordPress structure
 * - Provides filters for edge cases where themes use non-standard approaches
 *
 * This ensures proper HTML5 semantics (one main, one article per content piece)
 * regardless of which theme is active.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

/**
 * Universal detection for semantic HTML5 tags
 *
 * Capture the header output and analyze it to detect if the theme already
 * outputs <main> or <article> tags. This works with ANY theme.
 */
ob_start();
get_header();
$header_output = ob_get_clean();

// Detect if theme outputs <main> tag in its header
$theme_outputs_main = ( stripos( $header_output, '<main' ) !== false );

// Store for later use in article detection (article tags typically come after header)
$theme_has_main_tag = $theme_outputs_main;

/**
 * Filter to override main tag detection
 *
 * @param bool $theme_outputs_main Whether the theme already outputs a main tag.
 */
$theme_outputs_main = apply_filters( 'fanfic_theme_outputs_main', $theme_outputs_main );

// Output the captured header
echo $header_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

// Check if we should show sidebar (from global option)
$show_sidebar = get_option( 'fanfic_show_sidebar', '1' );
$layout_class = ( '1' === $show_sidebar ) ? 'fanfiction-with-sidebar' : 'fanfiction-no-sidebar';

// Use semantic <main> tag if theme doesn't provide it, otherwise use div
$main_tag = $theme_outputs_main ? 'div' : 'main';
$main_attrs = $theme_outputs_main ? 'id="primary" class="fanfiction-page-content"' : 'id="primary" class="fanfiction-page-content" role="main"';
?>
<!-- Fanfic content -->
<<?php echo esc_html( $main_tag ); ?> <?php echo $main_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<div class="fanfiction-page-wrapper <?php echo esc_attr( $layout_class ); ?>">
<div class="fanfiction-page-main">
<?php
while ( have_posts() ) :
    the_post();

/**
 * Universal detection for article tag
 *
 * Since we're using a custom page template (fanfiction-page-template.php), we're
 * replacing the theme's page.php/singular.php template. Article tags in WordPress
 * themes are typically in page templates, not in headers/footers.
 *
 * Therefore, by default we output our own article tag. The filter below allows
 * overriding this for rare cases where themes inject article tags via hooks.
 */
$theme_outputs_article = false;

/**
 * Filter to override article tag output
 *
 * Set to true if your theme outputs article tags via template hooks.
 * Example: add_filter( 'fanfic_theme_outputs_article', '__return_true' );
 *
 * @param bool $theme_outputs_article Whether the theme already outputs an article tag.
 */
$theme_outputs_article = apply_filters( 'fanfic_theme_outputs_article', $theme_outputs_article );

// Open article tag only if theme doesn't provide it
if ( ! $theme_outputs_article ) :
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'fanfiction-page' ); ?>>
<?php
endif;

/**
 * Show page title conditionally
 *
 * Some themes display the page title in their page header before the content. We detect this to avoid duplicate H1 tags.
 */
$show_title_setting = get_option( 'fanfic_show_page_title', 'auto' );
$show_title = true;
if ( 'never' === $show_title_setting ) {
    // User explicitly disabled title
    $show_title = false;
} elseif ( 'auto' === $show_title_setting ) {
    // Auto-detect themes that display page titles in their header
    $current_theme = get_template();
    $themes_with_page_header = array( 'oceanwp', 'astra', 'generatepress', 'kadence', 'blocksy' );
    if ( in_array( $current_theme, $themes_with_page_header, true ) ) {
        $show_title = false;
    }
}
// If 'always', $show_title remains true
/**
 * Filter to override page title display
 *
 * @param bool $show_title Whether to show the title.
 */
$show_title = apply_filters( 'fanfic_show_page_title', $show_title );
if ( $show_title ) :
?>
<header class="entry-header">
<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
</header>
<?php
endif;
?>
<div class="entry-content">
<?php
// Check if a custom content template is set (for story/chapter views)
global $fanfic_content_template;

if ( ! empty( $fanfic_content_template ) ) {
    // Load the specified content template
    $content_template_path = '';

    // Check theme directory first
    $theme_template = locate_template( array(
        'fanfiction-manager/' . $fanfic_content_template,
        $fanfic_content_template,
    ) );

    if ( $theme_template ) {
        $content_template_path = $theme_template;
    } else {
        // Check plugin templates directory
        $plugin_template = FANFIC_PLUGIN_DIR . 'templates/' . $fanfic_content_template;
        if ( file_exists( $plugin_template ) ) {
            $content_template_path = $plugin_template;
        }
    }

    if ( $content_template_path ) {
        // Set flag to tell template to execute rendering logic (not just function definition)
        $fanfic_load_template = true;
        include $content_template_path;
    } else {
        // Fallback to the_content() if template not found
        the_content();
    }
} else {
    // Default behavior for regular pages
    the_content();
    // Pagination for multi-page content
    wp_link_pages(
        array(
            'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'fanfiction-manager' ),
            'after'  => '</div>',
        )
    );
}
?>
</div>
<?php
// Close article tag only if we opened it
if ( ! $theme_outputs_article ) :
?>
</article>
<?php
endif;
endwhile;
?>
</div>
<?php if ( '1' === $show_sidebar && is_active_sidebar( 'fanfiction-sidebar' ) ) : ?>
<aside id="secondary" class="widget-area fanfiction-sidebar" role="complementary">
<?php dynamic_sidebar( 'fanfiction-sidebar' ); ?>
</aside>
<?php endif; ?>
</div><!-- .fanfiction-page-wrapper -->
</<?php echo esc_html( $main_tag ); ?>><!-- #primary -->
<!-- End Fanfic content -->
<?php
get_footer();