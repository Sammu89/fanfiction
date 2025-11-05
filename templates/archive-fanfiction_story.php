<?php
/**
 * Universal Archive Template for Fanfiction Stories
 *
 * Uses WordPress native query - optimal performance with single database query.
 * Handles post type archives, taxonomy archives, and multi-taxonomy filtering.
 *
 * URL Examples:
 * - /fanfiction/stories/ (all stories)
 * - /fanfiction/genre/romance/ (single taxonomy)
 * - /fanfiction/stories/?genre=romance&status=completed (multiple filters)
 * - /fanfiction/stories/?genre=romance,fantasy (multiple values)
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 1.0.0
 */

get_header();

// Detect context
$is_taxonomy = is_tax();
$queried_object = get_queried_object();
$page_title = '';
$page_description = '';

// Get active filters from URL
$url_filters = array();
$valid_taxonomies = array( 'fanfiction_genre', 'fanfiction_status', 'fanfiction_rating', 'fanfiction_character', 'fanfiction_relationship' );

foreach ( $valid_taxonomies as $taxonomy ) {
	$param_name = str_replace( 'fanfiction_', '', $taxonomy );
	if ( isset( $_GET[ $param_name ] ) && ! empty( $_GET[ $param_name ] ) ) {
		$url_filters[ $param_name ] = sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) );
	}
}

// Build page title and description
if ( $is_taxonomy && $queried_object instanceof WP_Term ) {
	$taxonomy_object = get_taxonomy( $queried_object->taxonomy );
	$taxonomy_label = $taxonomy_object ? $taxonomy_object->labels->singular_name : __( 'Category', 'fanfiction-manager' );

	$page_title = sprintf(
		/* translators: 1: taxonomy name, 2: term name */
		esc_html__( '%1$s: %2$s', 'fanfiction-manager' ),
		$taxonomy_label,
		$queried_object->name
	);

	if ( ! empty( $queried_object->description ) ) {
		$page_description = $queried_object->description;
	}

} elseif ( ! empty( $url_filters ) ) {
	$filter_labels = array();

	foreach ( $url_filters as $param_name => $value ) {
		$taxonomy = 'fanfiction_' . $param_name;
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$values = explode( ',', $value );
		$term_names = array();

		foreach ( $values as $term_slug ) {
			$term = get_term_by( 'slug', trim( $term_slug ), $taxonomy );
			if ( $term ) {
				$term_names[] = $term->name;
			}
		}

		if ( ! empty( $term_names ) ) {
			$taxonomy_object = get_taxonomy( $taxonomy );
			$label = $taxonomy_object ? $taxonomy_object->labels->singular_name : ucfirst( $param_name );
			$filter_labels[] = sprintf( '%s: %s', $label, implode( ', ', $term_names ) );
		}
	}

	$page_title = ! empty( $filter_labels )
		? sprintf(
			/* translators: %s: comma-separated list of filters */
			esc_html__( 'Stories Filtered by %s', 'fanfiction-manager' ),
			implode( ' & ', $filter_labels )
		)
		: esc_html__( 'Story Archive', 'fanfiction-manager' );

	$page_description = esc_html__( 'Browse stories matching your selected filters.', 'fanfiction-manager' );

} else {
	$page_title = esc_html__( 'Story Archive', 'fanfiction-manager' );
	$page_description = esc_html__( 'Browse all published fanfiction stories.', 'fanfiction-manager' );
}
?>

<div class="fanfic-archive">
	<header class="fanfic-archive-header">
		<h1 class="fanfic-archive-title"><?php echo esc_html( $page_title ); ?></h1>

		<?php if ( ! empty( $page_description ) ) : ?>
			<div class="fanfic-archive-description">
				<?php echo wp_kses_post( $page_description ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $url_filters ) ) : ?>
			<div class="fanfic-active-filters">
				<p class="fanfic-filters-label">
					<strong><?php esc_html_e( 'Active Filters:', 'fanfiction-manager' ); ?></strong>
				</p>
				<ul class="fanfic-filter-list">
					<?php foreach ( $url_filters as $param_name => $value ) : ?>
						<?php
						$taxonomy = 'fanfiction_' . $param_name;
						if ( ! taxonomy_exists( $taxonomy ) ) {
							continue;
						}

						$values = explode( ',', $value );
						foreach ( $values as $term_slug ) {
							$term = get_term_by( 'slug', trim( $term_slug ), $taxonomy );
							if ( ! $term ) {
								continue;
							}

							// Build URL to remove this filter
							$current_url_params = $_GET;
							$current_values = explode( ',', $current_url_params[ $param_name ] );
							$filtered_values = array_filter(
								$current_values,
								function( $v ) use ( $term_slug ) {
									return trim( $v ) !== trim( $term_slug );
								}
							);

							if ( empty( $filtered_values ) ) {
								unset( $current_url_params[ $param_name ] );
							} else {
								$current_url_params[ $param_name ] = implode( ',', $filtered_values );
							}

							$remove_url = remove_query_arg( array_keys( $url_filters ), get_post_type_archive_link( 'fanfiction_story' ) );
							if ( ! empty( $current_url_params ) ) {
								$remove_url = add_query_arg( $current_url_params, $remove_url );
							}
							?>
							<li class="fanfic-filter-item">
								<span class="fanfic-filter-label"><?php echo esc_html( $term->name ); ?></span>
								<a href="<?php echo esc_url( $remove_url ); ?>" class="fanfic-filter-remove" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s filter', 'fanfiction-manager' ), $term->name ) ); ?>">
									&times;
								</a>
							</li>
						<?php } ?>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'fanfiction_story' ) ); ?>" class="fanfic-clear-filters">
					<?php esc_html_e( 'Clear All Filters', 'fanfiction-manager' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</header>

	<div class="fanfic-archive-content">
		<?php if ( have_posts() ) : ?>
			<div class="fanfic-story-grid">
				<?php
				while ( have_posts() ) :
					the_post();

					// Get story metadata
					$author_id = get_the_author_meta( 'ID' );
					$author_name = get_the_author();
					$story_date = get_the_date();
					$story_excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 30 );

					// Get taxonomies
					$genres = get_the_terms( get_the_ID(), 'fanfiction_genre' );
					$status_terms = get_the_terms( get_the_ID(), 'fanfiction_status' );
					$status = $status_terms && ! is_wp_error( $status_terms ) ? $status_terms[0]->name : '';

					// Get metadata
					$word_count = get_post_meta( get_the_ID(), '_fanfic_word_count', true );
					$chapter_count = wp_count_posts( array(
						'post_type' => 'fanfiction_chapter',
						'post_parent' => get_the_ID(),
					) );
					$chapters = $chapter_count ? $chapter_count->publish : 0;
					?>

					<article id="story-<?php the_ID(); ?>" <?php post_class( 'fanfic-story-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="fanfic-story-card-image">
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); ?>
								</a>
							</div>
						<?php endif; ?>

						<div class="fanfic-story-card-content">
							<header class="fanfic-story-card-header">
								<h2 class="fanfic-story-card-title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h2>

								<div class="fanfic-story-card-meta">
									<span class="fanfic-story-author">
										<?php
										printf(
											/* translators: %s: author name */
											esc_html__( 'by %s', 'fanfiction-manager' ),
											'<a href="' . esc_url( get_author_posts_url( $author_id ) ) . '">' . esc_html( $author_name ) . '</a>'
										);
										?>
									</span>
									<span class="fanfic-story-date"><?php echo esc_html( $story_date ); ?></span>
									<?php if ( $status ) : ?>
										<span class="fanfic-story-status fanfic-status-<?php echo esc_attr( sanitize_title( $status ) ); ?>">
											<?php echo esc_html( $status ); ?>
										</span>
									<?php endif; ?>
								</div>
							</header>

							<div class="fanfic-story-card-excerpt">
								<?php echo wp_kses_post( $story_excerpt ); ?>
							</div>

							<footer class="fanfic-story-card-footer">
								<?php if ( $genres && ! is_wp_error( $genres ) ) : ?>
									<div class="fanfic-story-genres">
										<?php foreach ( $genres as $genre ) : ?>
											<a href="<?php echo esc_url( get_term_link( $genre ) ); ?>" class="fanfic-genre-tag">
												<?php echo esc_html( $genre->name ); ?>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<div class="fanfic-story-stats">
									<?php if ( $word_count ) : ?>
										<span class="fanfic-stat">
											<span class="dashicons dashicons-edit"></span>
											<?php echo esc_html( number_format_i18n( $word_count ) ); ?> <?php esc_html_e( 'words', 'fanfiction-manager' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( $chapters ) : ?>
										<span class="fanfic-stat">
											<span class="dashicons dashicons-book"></span>
											<?php echo esc_html( number_format_i18n( $chapters ) ); ?> <?php echo esc_html( _n( 'chapter', 'chapters', $chapters, 'fanfiction-manager' ) ); ?>
										</span>
									<?php endif; ?>
								</div>
							</footer>
						</div>
					</article>

				<?php endwhile; ?>
			</div>

			<nav class="fanfic-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Stories pagination', 'fanfiction-manager' ); ?>">
				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 2,
						'prev_text' => __( '&larr; Previous', 'fanfiction-manager' ),
						'next_text' => __( 'Next &rarr;', 'fanfiction-manager' ),
					)
				);
				?>
			</nav>

		<?php else : ?>

			<div class="fanfic-no-results">
				<p><?php esc_html_e( 'No stories found matching your criteria.', 'fanfiction-manager' ); ?></p>
				<?php if ( ! empty( $url_filters ) ) : ?>
					<p>
						<a href="<?php echo esc_url( get_post_type_archive_link( 'fanfiction_story' ) ); ?>" class="fanfic-button fanfic-button-primary">
							<?php esc_html_e( 'View All Stories', 'fanfiction-manager' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
