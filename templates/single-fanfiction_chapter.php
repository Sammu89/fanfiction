<?php
/**
 * Template for single chapter display
 *
 * This template is used when viewing a single chapter.
 * Theme developers can override this by copying to their theme directory.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

get_header(); ?>

<div class="fanfic-chapter-single">
	<?php
	while ( have_posts() ) :
		the_post();
		?>

		<article id="chapter-<?php the_ID(); ?>" <?php post_class( 'fanfic-chapter' ); ?>>

			<nav class="chapter-breadcrumb">
				[chapter-breadcrumb]
			</nav>

			<header class="chapter-header">
				<h1 class="chapter-title"><?php echo esc_html( get_the_title() ); ?></h1>

				<div class="chapter-meta">
					<span class="chapter-story">
						[chapter-story]
					</span>
					<span class="chapter-date">
						<?php echo esc_html( get_the_date() ); ?>
					</span>
				</div>
			</header>

			<nav class="chapter-navigation chapter-navigation-top">
				[chapters-nav]
			</nav>

			<div class="chapter-actions">
				[chapter-actions]
				<?php echo do_shortcode( '[edit-chapter-button]' ); ?>
			</div>

			<div class="chapter-content">
				<?php the_content(); ?>
			</div>

			<div class="chapter-rating">
				<h3><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
				[chapter-rating-form]
			</div>

			<nav class="chapter-navigation chapter-navigation-bottom">
				[chapters-nav]
			</nav>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="chapter-comments">
					<h2><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h2>
					[chapter-comments]
				</div>
			<?php endif; ?>

		</article>

	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
