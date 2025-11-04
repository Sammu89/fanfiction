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

			<header class="story-header">
				<h1 class="story-title"><?php echo esc_html( get_the_title() ); ?></h1>

				<div class="story-meta">
					<span class="story-author">
						<?php
						printf(
							/* translators: %s: story author link */
							esc_html__( 'by %s', 'fanfiction-manager' ),
							'[story-author-link]'
						);
						?>
					</span>
					<span class="story-date">
						<?php echo esc_html( get_the_date() ); ?>
					</span>
					<span class="story-status">
						[story-status]
					</span>
				</div>
			</header>

			<div class="story-featured-image">
				[story-featured-image]
			</div>

			<div class="story-content">
				<div class="story-intro">
					<h2><?php esc_html_e( 'Summary', 'fanfiction-manager' ); ?></h2>
					[story-intro]
				</div>

				<div class="story-taxonomies">
					<div class="story-genres">
						<strong><?php esc_html_e( 'Genres:', 'fanfiction-manager' ); ?></strong>
						[story-genres]
					</div>
				</div>

				<div class="story-stats">
					<span class="story-word-count">
						<strong><?php esc_html_e( 'Words:', 'fanfiction-manager' ); ?></strong>
						[story-word-count-estimate]
					</span>
					<span class="story-chapters-count">
						<strong><?php esc_html_e( 'Chapters:', 'fanfiction-manager' ); ?></strong>
						[story-chapters]
					</span>
					<span class="story-views">
						<strong><?php esc_html_e( 'Views:', 'fanfiction-manager' ); ?></strong>
						[story-views]
					</span>
					<span class="story-rating">
						<strong><?php esc_html_e( 'Rating:', 'fanfiction-manager' ); ?></strong>
						[story-rating-form]
					</span>
				</div>
			</div>

			<div class="story-actions">
				[story-actions]
			</div>

			<div class="story-chapters-list">
				<h2><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
				[chapters-list]
			</div>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="story-comments">
					[story-comments]
				</div>
			<?php endif; ?>

		</article>

	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
