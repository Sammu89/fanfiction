<?php
/**
 * Template Name: Fanfiction Page Template
 * Template Post Type: page
 * Description: A template for Fanfiction Manager plugin pages that integrates with your theme
 *
 * This template provides a consistent layout for all Fanfiction plugin pages while
 * fully integrating with your active theme's header, footer, and styling.
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div id="primary" class="content-area fanfiction-page-content">
	<main id="main" class="site-main" role="main">

		<?php
		// Check if we should show sidebar (from Customizer or default)
		$show_sidebar = get_theme_mod( 'fanfic_show_sidebar', true );
		$layout_class = $show_sidebar ? 'fanfic-with-sidebar' : 'fanfic-no-sidebar';
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
							/**
							 * The content - can be:
							 * - Regular page content with shortcodes (wizard-generated pages)
							 * - Injected shortcode content (virtual dynamic pages)
							 */
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

			<?php if ( $show_sidebar && is_active_sidebar( 'fanfiction-sidebar' ) ) : ?>
				<aside id="secondary" class="widget-area fanfiction-sidebar" role="complementary">
					<?php dynamic_sidebar( 'fanfiction-sidebar' ); ?>
				</aside>
			<?php endif; ?>

		</div><!-- .fanfiction-page-wrapper -->

	</main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
