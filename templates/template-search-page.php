<?php
/**
 * Template: Search Page
 *
 * Displays browse/search filters and story results using the search index.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = function_exists( 'fanfic_get_page_url' ) ? fanfic_get_page_url( 'search' ) : fanfic_get_current_url();
$params = function_exists( 'fanfic_get_browse_params' ) ? fanfic_get_browse_params() : array();

$paged = (int) get_query_var( 'paged' );
if ( $paged <= 0 ) {
	$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
}
$per_page = (int) get_option( 'posts_per_page', 10 );

$query_args = function_exists( 'fanfic_build_browse_query_args' )
	? fanfic_build_browse_query_args( $params, $paged, $per_page )
	: array();

$browse_query = new WP_Query( $query_args );

$genres = get_terms( array(
	'taxonomy'   => 'fanfiction_genre',
	'hide_empty' => false,
) );

$statuses = get_terms( array(
	'taxonomy'   => 'fanfiction_status',
	'hide_empty' => false,
) );

$warnings = class_exists( 'Fanfic_Warnings' ) ? Fanfic_Warnings::get_available_warnings() : array();

$active_filters = function_exists( 'fanfic_build_active_filters' ) ? fanfic_build_active_filters( $params, $base_url ) : array();
?>

<div class="fanfic-search-page fanfic-browse-page" data-fanfic-browse>
	<form class="fanfic-browse-form" method="get" action="<?php echo esc_url( $base_url ); ?>" data-fanfic-browse-form>
		<div class="fanfic-browse-row">
			<label for="fanfic-search-input"><?php esc_html_e( 'Search stories', 'fanfiction-manager' ); ?></label>
			<input
				type="text"
				id="fanfic-search-input"
				name="search"
				value="<?php echo esc_attr( $params['search'] ?? '' ); ?>"
				placeholder="<?php esc_attr_e( 'Search titles, tags, authors...', 'fanfiction-manager' ); ?>"
			/>
		</div>

		<div class="fanfic-browse-row fanfic-browse-columns">
			<div class="fanfic-browse-column">
				<label for="fanfic-genre-filter"><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></label>
				<select id="fanfic-genre-filter" name="genre[]" multiple>
					<?php if ( ! empty( $genres ) && ! is_wp_error( $genres ) ) : ?>
						<?php foreach ( $genres as $genre ) : ?>
							<option value="<?php echo esc_attr( $genre->slug ); ?>" <?php selected( in_array( $genre->slug, (array) $params['genres'], true ) ); ?>>
								<?php echo esc_html( $genre->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<div class="fanfic-browse-column">
				<label for="fanfic-status-filter"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></label>
				<select id="fanfic-status-filter" name="status[]" multiple>
					<?php if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) : ?>
						<?php foreach ( $statuses as $status ) : ?>
							<option value="<?php echo esc_attr( $status->slug ); ?>" <?php selected( in_array( $status->slug, (array) $params['statuses'], true ) ); ?>>
								<?php echo esc_html( $status->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<div class="fanfic-browse-column">
				<label for="fanfic-age-filter"><?php esc_html_e( 'Age rating', 'fanfiction-manager' ); ?></label>
				<select id="fanfic-age-filter" name="age">
					<option value=""><?php esc_html_e( 'Any age', 'fanfiction-manager' ); ?></option>
					<option value="PG" <?php selected( 'PG', $params['age'] ?? '' ); ?>><?php esc_html_e( 'PG', 'fanfiction-manager' ); ?></option>
					<option value="13" <?php selected( '13', $params['age'] ?? '' ); ?>><?php esc_html_e( '13+', 'fanfiction-manager' ); ?></option>
					<option value="16" <?php selected( '16', $params['age'] ?? '' ); ?>><?php esc_html_e( '16+', 'fanfiction-manager' ); ?></option>
					<option value="18" <?php selected( '18', $params['age'] ?? '' ); ?>><?php esc_html_e( '18+', 'fanfiction-manager' ); ?></option>
				</select>
			</div>

			<div class="fanfic-browse-column">
				<label for="fanfic-sort-filter"><?php esc_html_e( 'Sort by', 'fanfiction-manager' ); ?></label>
				<select id="fanfic-sort-filter" name="sort">
					<option value=""><?php esc_html_e( 'Relevance / Updated', 'fanfiction-manager' ); ?></option>
					<option value="updated" <?php selected( 'updated', $params['sort'] ?? '' ); ?>><?php esc_html_e( 'Recently updated', 'fanfiction-manager' ); ?></option>
					<option value="created" <?php selected( 'created', $params['sort'] ?? '' ); ?>><?php esc_html_e( 'Newest', 'fanfiction-manager' ); ?></option>
					<option value="alphabetical" <?php selected( 'alphabetical', $params['sort'] ?? '' ); ?>><?php esc_html_e( 'A-Z', 'fanfiction-manager' ); ?></option>
				</select>
			</div>
		</div>

		<div class="fanfic-browse-row">
			<label for="fanfic-fandom-filter"><?php esc_html_e( 'Fandoms', 'fanfiction-manager' ); ?></label>
			<input
				type="text"
				id="fanfic-fandom-filter"
				name="fandom"
				value="<?php echo esc_attr( implode( ' ', (array) $params['fandoms'] ) ); ?>"
				placeholder="<?php esc_attr_e( 'fandom slug(s)', 'fanfiction-manager' ); ?>"
			/>
			<p class="description"><?php esc_html_e( 'Enter fandom slugs separated by spaces.', 'fanfiction-manager' ); ?></p>
		</div>

		<?php if ( ! empty( $warnings ) ) : ?>
			<div class="fanfic-browse-row fanfic-browse-warnings">
				<span class="fanfic-browse-label"><?php esc_html_e( 'Exclude warnings', 'fanfiction-manager' ); ?></span>
				<div class="fanfic-browse-warning-list">
					<?php foreach ( $warnings as $warning ) : ?>
						<label>
							<input
								type="checkbox"
								name="warning[]"
								value="-<?php echo esc_attr( $warning['slug'] ); ?>"
								<?php checked( in_array( $warning['slug'], (array) $params['exclude_warnings'], true ) ); ?>
							/>
							<?php echo esc_html( $warning['name'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="fanfic-browse-actions">
			<button type="submit" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Apply filters', 'fanfiction-manager' ); ?>
			</button>
			<a class="fanfic-button" href="<?php echo esc_url( $base_url ); ?>">
				<?php esc_html_e( 'Clear all', 'fanfiction-manager' ); ?>
			</a>
		</div>
	</form>

	<div data-fanfic-active-filters>
		<?php if ( ! empty( $active_filters ) ) : ?>
			<div class="fanfic-active-filters">
				<p class="fanfic-filters-label"><strong><?php esc_html_e( 'Active Filters:', 'fanfiction-manager' ); ?></strong></p>
				<ul class="fanfic-filter-list">
					<?php foreach ( $active_filters as $filter ) : ?>
						<li class="fanfic-filter-item">
							<span class="fanfic-filter-label"><?php echo esc_html( $filter['label'] ); ?></span>
							<a href="<?php echo esc_url( $filter['url'] ); ?>" class="fanfic-filter-remove" aria-label="<?php esc_attr_e( 'Remove filter', 'fanfiction-manager' ); ?>">
								&times;
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $base_url ); ?>" class="fanfic-clear-filters">
					<?php esc_html_e( 'Clear All Filters', 'fanfiction-manager' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<div class="fanfic-browse-results" data-fanfic-browse-results>
		<div class="fanfic-browse-header">
			<h2 class="fanfic-browse-title">
				<?php
				printf(
					esc_html(
						_n(
							'Found %d story',
							'Found %d stories',
							$browse_query->found_posts,
							'fanfiction-manager'
						)
					),
					absint( $browse_query->found_posts )
				);
				?>
			</h2>
		</div>

		<?php if ( $browse_query->have_posts() ) : ?>
			<div class="fanfic-story-grid">
				<?php
				while ( $browse_query->have_posts() ) :
					$browse_query->the_post();
					echo fanfic_get_story_card_html( get_the_ID() );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<div class="fanfic-no-results">
				<p><?php esc_html_e( 'No stories found matching your criteria.', 'fanfiction-manager' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<div class="fanfic-browse-pagination" data-fanfic-browse-pagination>
		<?php
		$total_pages = $browse_query->max_num_pages;
		if ( $total_pages > 1 ) {
			$pagination_base = fanfic_build_browse_url( $base_url, $params, array( 'paged' => null ) );
			echo paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%', $pagination_base ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => esc_html__( '&laquo; Previous', 'fanfiction-manager' ),
				'next_text' => esc_html__( 'Next &raquo;', 'fanfiction-manager' ),
			) );
		}
		?>
	</div>

	<div class="fanfic-browse-loading" data-fanfic-browse-loading aria-hidden="true">
		<?php esc_html_e( 'Loading...', 'fanfiction-manager' ); ?>
	</div>
</div>
