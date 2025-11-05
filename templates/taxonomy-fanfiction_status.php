<?php
/**
 * Template for status taxonomy archive
 *
 * This template is used when viewing stories filtered by status.
 * Theme developers can override this by copying to their theme directory.
 *
 * @package FanfictionManager
 * @subpackage Templates
 */

get_header(); ?>

<div class="fanfic-status-archive">
	<header class="fanfic-archive-header">
		<h1 class="fanfic-archive-title">
			<?php
			printf(
				/* translators: %s: status name */
				esc_html__( 'Status: %s', 'fanfiction-manager' ),
				single_term_title( '', false )
			);
			?>
		</h1>

		<?php if ( term_description() ) : ?>
			<div class="fanfic-archive-description">
				<?php echo wp_kses_post( term_description() ); ?>
			</div>
		<?php endif; ?>
	</header>

	<div class="fanfic-archive-content">
		<?php
		$current_term = get_queried_object();
		if ( $current_term ) {
			echo do_shortcode( '[story-list status="' . esc_attr( $current_term->slug ) . '"]' );
		}
		?>
	</div>
</div>

<?php get_footer(); ?>
