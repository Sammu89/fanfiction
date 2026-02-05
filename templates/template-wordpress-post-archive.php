<?php
/**
 * WordPress Posts Archive Template for Fanfiction main page.
 *
 * @package FanfictionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$query = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'paged'               => $paged,
		'posts_per_page'      => (int) get_option( 'posts_per_page', 10 ),
		'ignore_sticky_posts' => false,
	)
);
?>
<div class="fanfic-wp-post-archive">
	<header class="fanfic-wp-post-archive-header">
		<h1><?php esc_html_e( 'Latest Posts', 'fanfiction-manager' ); ?></h1>
	</header>

	<?php if ( $query->have_posts() ) : ?>
		<div class="fanfic-wp-post-list">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'fanfic-wp-post-item' ); ?>>
					<h2 class="fanfic-wp-post-title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h2>
					<p class="fanfic-wp-post-meta">
						<?php echo esc_html( get_the_date() ); ?>
					</p>
					<div class="fanfic-wp-post-excerpt">
						<?php the_excerpt(); ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<?php
		$pagination = paginate_links(
			array(
				'total'   => (int) $query->max_num_pages,
				'current' => $paged,
				'type'    => 'list',
			)
		);
		if ( $pagination ) :
			?>
			<nav class="fanfic-wp-post-pagination" aria-label="<?php esc_attr_e( 'Posts pagination', 'fanfiction-manager' ); ?>">
				<?php echo wp_kses_post( $pagination ); ?>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No posts found.', 'fanfiction-manager' ); ?></p>
	<?php endif; ?>
</div>
<?php
wp_reset_postdata();
