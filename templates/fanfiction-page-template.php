<?php
get_header();
?>
<!-- Fanfic content -->
<div id="primary" class="content-area fanfiction-page-content">
<?php
// Check if we should show sidebar (from global option)
$show_sidebar = get_option( 'fanfic_show_sidebar', '1' );
$layout_class = ( '1' === $show_sidebar ) ? 'fanfiction-with-sidebar' : 'fanfiction-no-sidebar';
?>
<div class="fanfiction-page-wrapper <?php echo esc_attr( $layout_class ); ?>">
<div class="fanfiction-page-main">
<?php
while ( have_posts() ) :
    the_post();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'fanfiction-page' ); ?>>
<?php
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
the_content();
// Pagination for multi-page content
wp_link_pages(
    array(
        'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'fanfiction-manager' ),
        'after'  => '</div>',
    )
);
?>
</div>
</article>
<?php
endwhile;
?>
</div>
<?php if ( '1' === $show_sidebar && is_active_sidebar( 'fanfiction-sidebar' ) ) : ?>
<aside id="secondary" class="widget-area fanfiction-sidebar" role="complementary">
<?php dynamic_sidebar( 'fanfiction-sidebar' ); ?>
</aside>
<?php endif; ?>
</div><!-- .fanfiction-page-wrapper -->
</div><!-- #primary -->
<!-- End Fanfic content -->
<?php
get_footer();