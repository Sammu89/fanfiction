<?php
/**
 * Template for single story display
 *
 * This template is used when viewing a single fanfiction story.
 * Theme developers can override this by copying to their theme directory.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

get_header(); ?>

<div class="fanfic-story-single">
	<?php
	while ( have_posts() ) :
		the_post();
		?>

		<article id="story-<?php the_ID(); ?>" <?php post_class( 'fanfic-story' ); ?>>

			<header class="fanfic-story-header">
				<h1 class="fanfic-story-title"><?php echo esc_html( get_the_title() ); ?></h1>

				<div class="fanfic-story-meta">
					<span class="fanfic-story-author">
						<?php
						printf(
							/* translators: %s: story author link */
							esc_html__( 'by %s', 'fanfiction-manager' ),
							do_shortcode( '[story-author-link]' )
						);
						?>
					</span>
					<span class="fanfic-story-date">
						<?php echo esc_html( get_the_date() ); ?>
					</span>
					<span class="fanfic-story-status">
						<?php echo do_shortcode( '[story-status]' ); ?>
					</span>
				</div>
			</header>

			<div class="fanfic-story-featured-image">
				<?php echo do_shortcode( '[story-featured-image]' ); ?>
			</div>

			<div class="fanfic-story-content">
				<div class="fanfic-story-intro">
					<h2><?php esc_html_e( 'Summary', 'fanfiction-manager' ); ?></h2>
					<?php echo do_shortcode( '[story-intro]' ); ?>
				</div>

				<div class="fanfic-story-taxonomies">
					<div class="fanfic-story-genres">
						<strong><?php esc_html_e( 'Genres:', 'fanfiction-manager' ); ?></strong>
						<?php echo do_shortcode( '[story-genres]' ); ?>
					</div>
				</div>

				<div class="fanfic-story-stats">
					<span class="fanfic-story-word-count">
						<strong><?php esc_html_e( 'Words:', 'fanfiction-manager' ); ?></strong>
						<?php echo do_shortcode( '[story-word-count-estimate]' ); ?>
					</span>
					<span class="fanfic-story-chapters-count">
						<strong><?php esc_html_e( 'Chapters:', 'fanfiction-manager' ); ?></strong>
						<?php echo do_shortcode( '[story-chapters]' ); ?>
					</span>
					<span class="fanfic-story-views">
						<strong><?php esc_html_e( 'Views:', 'fanfiction-manager' ); ?></strong>
						<?php echo do_shortcode( '[story-views]' ); ?>
					</span>
					<span class="fanfic-story-rating">
						<strong><?php esc_html_e( 'Rating:', 'fanfiction-manager' ); ?></strong>
						<?php echo do_shortcode( '[story-rating-form]' ); ?>
					</span>
				</div>
			</div>

			<div class="fanfic-story-actions">
				<?php echo do_shortcode( '[story-actions]' ); ?>
				<?php echo do_shortcode( '[edit-story-button]' ); ?>
			</div>

			<div class="fanfic-story-navigation">
				<div class="fanfic-story-chapters-dropdown">
					<?php echo do_shortcode( '[story-chapters-dropdown]' ); ?>
				</div>
			</div>

			<div class="fanfic-story-chapters-list">
				<h2><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
				<?php echo do_shortcode( '[chapters-list]' ); ?>
			</div>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="fanfic-story-comments">
					<?php echo do_shortcode( '[story-comments]' ); ?>
				</div>
			<?php endif; ?>

		</article>

	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
