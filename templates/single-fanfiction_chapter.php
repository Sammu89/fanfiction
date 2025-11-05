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

			<nav class="fanfic-chapter-breadcrumb">
				[chapter-breadcrumb]
			</nav>

			<header class="fanfic-chapter-header">
				<h1 class="fanfic-chapter-title"><?php echo esc_html( get_the_title() ); ?></h1>

				<div class="fanfic-chapter-meta">
					<span class="fanfic-chapter-story">
						[chapter-story]
					</span>
					<span class="fanfic-chapter-date">
						<?php echo esc_html( get_the_date() ); ?>
					</span>
				</div>
			</header>

			<nav class="fanfic-chapter-navigation chapter-navigation-top">
				[chapters-nav]
			</nav>

			<div class="fanfic-chapter-actions">
				[chapter-actions]
				<?php echo do_shortcode( '[edit-chapter-button]' ); ?>
			</div>

			<div class="fanfic-chapter-content">
				<?php the_content(); ?>
			</div>

			<div class="fanfic-chapter-rating">
				<h3><?php esc_html_e( 'Rate this chapter', 'fanfiction-manager' ); ?></h3>
				[chapter-rating-form]
			</div>

			<nav class="fanfic-chapter-navigation chapter-navigation-bottom">
				[chapters-nav]
			</nav>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="fanfic-chapter-comments">
					<h2><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></h2>
					[chapter-comments]
				</div>
			<?php endif; ?>

		</article>

	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
