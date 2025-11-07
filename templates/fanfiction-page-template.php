<?php
get_header();
?>

<div id="primary" class="content-area fanfiction-page-content">    
    <?php
    // Check if we should show sidebar
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
                    <header class="entry-header">
                        <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                    </header>

                    <div class="entry-content">
                        <?php
                        the_content();
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

<?php
get_footer();