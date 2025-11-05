<?php
/**
 * Universal Archive Template for Fanfiction Stories
 *
 * This template handles:
 * - Post type archives (all stories)
 * - Taxonomy archives (genre, status, rating, etc.)
 * - Multi-taxonomy filtering via URL parameters
 * - Custom filtering combinations
 *
 * URL Examples:
 * - /fanfiction/stories/ (all stories)
 * - /fanfiction/genre/romance/ (single taxonomy)
 * - /fanfiction/stories/?genre=romance&status=completed (multiple filters)
 * - /fanfiction/stories/?genre=romance,fantasy (multiple values in one taxonomy)
 *
 * Theme developers can override this by copying to their theme directory.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 1.0.0
 */

get_header();

// Detect the context
$is_taxonomy = is_tax();
$queried_object = get_queried_object();
$page_title = '';
$page_description = '';
$shortcode_attributes = array();

// Build filters from URL parameters
$url_filters = array();
$valid_taxonomies = array( 'fanfiction_genre', 'fanfiction_status', 'fanfiction_rating', 'fanfiction_character', 'fanfiction_relationship' );

foreach ( $valid_taxonomies as $taxonomy ) {
	// Extract taxonomy slug (remove fanfiction_ prefix for URL parameter)
	$param_name = str_replace( 'fanfiction_', '', $taxonomy );

	if ( isset( $_GET[ $param_name ] ) && ! empty( $_GET[ $param_name ] ) ) {
		$url_filters[ $param_name ] = sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) );
	}
}

// Determine page title and description
if ( $is_taxonomy && $queried_object instanceof WP_Term ) {
	// Single taxonomy archive (e.g., /genre/romance/)
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

	// Add current taxonomy to shortcode attributes
	$param_name = str_replace( 'fanfiction_', '', $queried_object->taxonomy );
	$shortcode_attributes[ $param_name ] = $queried_object->slug;

} elseif ( ! empty( $url_filters ) ) {
	// Multi-taxonomy filtering via URL parameters
	$filter_labels = array();

	foreach ( $url_filters as $param_name => $value ) {
		$taxonomy = 'fanfiction_' . $param_name;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		// Handle comma-separated values
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
			$filter_labels[] = sprintf(
				'%s: %s',
				$label,
				implode( ', ', $term_names )
			);
		}

		// Add to shortcode attributes
		$shortcode_attributes[ $param_name ] = $value;
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
	// Default post type archive (all stories)
	$page_title = esc_html__( 'Story Archive', 'fanfiction-manager' );
	$page_description = esc_html__( 'Browse all published fanfiction stories.', 'fanfiction-manager' );
}

// Build shortcode string
$shortcode_string = '[story-list';
foreach ( $shortcode_attributes as $attr => $value ) {
	$shortcode_string .= ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
}
$shortcode_string .= ']';
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
		<?php
		// Output the story list with appropriate filters
		echo do_shortcode( $shortcode_string );
		?>
	</div>
</div>

<?php get_footer(); ?>
