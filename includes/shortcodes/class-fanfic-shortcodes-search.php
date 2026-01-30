<?php
/**
 * Search Shortcodes Class
 *
 * Handles all search-related shortcodes.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Search
 *
 * Search form and results shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Search {
	/**
	 * Track browse wrapper state between shortcodes.
	 *
	 * @var bool
	 */
	private static $browse_wrapper_open = false;

	/**
	 * Track whether the wrapper was opened by the search bar shortcode.
	 *
	 * @var bool
	 */
	private static $browse_wrapper_opened_by_search = false;

	/**
	 * Track whether the archive shortcode already rendered.
	 *
	 * @var bool
	 */
	private static $browse_archive_rendered = false;

	/**
	 * Register search shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'search-form', array( __CLASS__, 'search_form' ) );
		add_shortcode( 'fanfic-search-bar', array( __CLASS__, 'browse_search_bar' ) );
		add_shortcode( 'fanfic-story-archive', array( __CLASS__, 'browse_story_archive' ) );
	}

	/**
	 * Search form shortcode
	 *
	 * [search-form]
	 *
	 * Displays a search form with text input, genre filter, and status filter.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Search form HTML.
	 */
	public static function search_form( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'show_filters' => 'yes', // Show genre and status filters
			),
			'search-form'
		);

		// Get current search term and filters
		$search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$genre_filter = isset( $_GET['genre'] ) ? sanitize_text_field( wp_unslash( $_GET['genre'] ) ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		// Start output
		ob_start();
		?>
		<form class="fanfic-search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" aria-label="<?php esc_attr_e( 'Search stories', 'fanfiction-manager' ); ?>">
			<div class="search-field-wrapper">
				<label for="fanfic-search-input" class="screen-reader-text">
					<?php esc_html_e( 'Search stories', 'fanfiction-manager' ); ?>
				</label>
				<input
					type="text"
					id="fanfic-search-input"
					name="s"
					class="fanfic-search-input"
					placeholder="<?php esc_attr_e( 'Search stories...', 'fanfiction-manager' ); ?>"
					value="<?php echo esc_attr( $search_term ); ?>"
					required
				/>
				<button type="submit" class="fanfic-search-submit">
					<?php esc_html_e( 'Search', 'fanfiction-manager' ); ?>
				</button>
			</div>

			<?php if ( 'yes' === $atts['show_filters'] ) : ?>
				<div class="search-filters-wrapper">
					<!-- Genre Filter -->
					<div class="search-filter">
						<label for="fanfic-genre-filter">
							<?php esc_html_e( 'Genre', 'fanfiction-manager' ); ?>
						</label>
						<select name="genre" id="fanfic-genre-filter" class="fanfic-filter-select" aria-label="<?php esc_attr_e( 'Filter by genre', 'fanfiction-manager' ); ?>">
							<option value=""><?php esc_html_e( 'All Genres', 'fanfiction-manager' ); ?></option>
							<?php
							$genres = get_terms( array(
								'taxonomy'   => 'fanfiction_genre',
								'hide_empty' => false,
							) );

							if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) {
								foreach ( $genres as $genre ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $genre->slug ),
										selected( $genre_filter, $genre->slug, false ),
										esc_html( $genre->name )
									);
								}
							}
							?>
						</select>
					</div>

					<!-- Status Filter -->
					<div class="search-filter">
						<label for="fanfic-status-filter">
							<?php esc_html_e( 'Status', 'fanfiction-manager' ); ?>
						</label>
						<select name="status" id="fanfic-status-filter" class="fanfic-filter-select">
							<option value=""><?php esc_html_e( 'All Statuses', 'fanfiction-manager' ); ?></option>
							<?php
							$statuses = get_terms( array(
								'taxonomy'   => 'fanfiction_status',
								'hide_empty' => false,
							) );

							if ( ! is_wp_error( $statuses ) && ! empty( $statuses ) ) {
								foreach ( $statuses as $status ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $status->slug ),
										selected( $status_filter, $status->slug, false ),
										esc_html( $status->name )
									);
								}
							}
							?>
						</select>
					</div>
				</div>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Browse search bar shortcode
	 *
	 * [fanfic-search-bar]
	 *
	 * Outputs the browse header, filters, and active filters list.
	 *
	 * @since 1.2.0
	 * @return string Browse search bar HTML.
	 */
	public static function browse_search_bar() {
		$context = self::get_browse_context();

		$open_wrapper = ! self::$browse_wrapper_open;
		$close_wrapper = false;

		if ( $open_wrapper ) {
			self::$browse_wrapper_open = true;
			self::$browse_wrapper_opened_by_search = true;
			if ( self::$browse_archive_rendered ) {
				$close_wrapper = true;
			}
		}

		ob_start();

		if ( $open_wrapper ) :
			?>
			<div class="fanfic-archive fanfic-browse-page" data-fanfic-browse>
			<?php
		endif;
		?>
		<header class="fanfic-archive-header">
			<h1 class="fanfic-title fanfic-archive-title"><?php echo $context['page_title']; ?></h1>

			<?php if ( ! empty( $context['page_description'] ) ) : ?>
				<div class="fanfic-archive-description">
					<?php echo wp_kses_post( $context['page_description'] ); ?>
				</div>
			<?php endif; ?>
		</header>

		<form class="fanfic-browse-form" method="get" action="<?php echo esc_url( $context['base_url'] ); ?>" data-fanfic-browse-form>
			<div class="fanfic-browse-row">
				<label for="fanfic-search-input"><?php esc_html_e( 'Search stories', 'fanfiction-manager' ); ?></label>
				<input
					type="text"
					id="fanfic-search-input"
					name="search"
					value="<?php echo esc_attr( $context['params']['search'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'Search titles, tags, authors...', 'fanfiction-manager' ); ?>"
				/>
			</div>

			<div class="fanfic-browse-row fanfic-browse-columns">
				<div class="fanfic-browse-column">
					<label for="fanfic-genre-filter"><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-genre-filter" name="genre[]" multiple>
						<?php if ( ! empty( $context['genres'] ) && ! is_wp_error( $context['genres'] ) ) : ?>
							<?php foreach ( $context['genres'] as $genre ) : ?>
								<option value="<?php echo esc_attr( $genre->slug ); ?>" <?php selected( in_array( $genre->slug, (array) $context['params']['genres'], true ) ); ?>>
									<?php echo esc_html( $genre->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<div class="fanfic-browse-column">
					<label for="fanfic-status-filter"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-status-filter" name="status[]" multiple>
						<?php if ( ! empty( $context['statuses'] ) && ! is_wp_error( $context['statuses'] ) ) : ?>
							<?php foreach ( $context['statuses'] as $status ) : ?>
								<option value="<?php echo esc_attr( $status->slug ); ?>" <?php selected( in_array( $status->slug, (array) $context['params']['statuses'], true ) ); ?>>
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
						<option value="PG" <?php selected( 'PG', $context['params']['age'] ?? '' ); ?>><?php esc_html_e( 'PG', 'fanfiction-manager' ); ?></option>
						<option value="13" <?php selected( '13', $context['params']['age'] ?? '' ); ?>><?php esc_html_e( '13+', 'fanfiction-manager' ); ?></option>
						<option value="16" <?php selected( '16', $context['params']['age'] ?? '' ); ?>><?php esc_html_e( '16+', 'fanfiction-manager' ); ?></option>
						<option value="18" <?php selected( '18', $context['params']['age'] ?? '' ); ?>><?php esc_html_e( '18+', 'fanfiction-manager' ); ?></option>
					</select>
				</div>

				<div class="fanfic-browse-column">
					<label for="fanfic-sort-filter"><?php esc_html_e( 'Sort by', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-sort-filter" name="sort">
						<option value=""><?php esc_html_e( 'Relevance / Updated', 'fanfiction-manager' ); ?></option>
						<option value="updated" <?php selected( 'updated', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Recently updated', 'fanfiction-manager' ); ?></option>
						<option value="created" <?php selected( 'created', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Newest', 'fanfiction-manager' ); ?></option>
						<option value="alphabetical" <?php selected( 'alphabetical', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'A-Z', 'fanfiction-manager' ); ?></option>
					</select>
				</div>
			</div>

			<div class="fanfic-browse-row">
				<label for="fanfic-fandom-filter"><?php esc_html_e( 'Fandoms', 'fanfiction-manager' ); ?></label>
				<input
					type="text"
					id="fanfic-fandom-filter"
					name="fandom"
					value="<?php echo esc_attr( implode( ' ', (array) $context['params']['fandoms'] ) ); ?>"
					placeholder="<?php esc_attr_e( 'fandom slug(s)', 'fanfiction-manager' ); ?>"
				/>
				<p class="description"><?php esc_html_e( 'Enter fandom slugs separated by spaces.', 'fanfiction-manager' ); ?></p>
			</div>

			<?php if ( ! empty( $context['warnings'] ) ) : ?>
				<div class="fanfic-browse-row fanfic-browse-warnings">
					<span class="fanfic-browse-label"><?php esc_html_e( 'Exclude warnings', 'fanfiction-manager' ); ?></span>
					<div class="fanfic-browse-warning-list">
						<?php foreach ( $context['warnings'] as $warning ) : ?>
							<label>
								<input
									type="checkbox"
									name="warning[]"
									value="-<?php echo esc_attr( $warning['slug'] ); ?>"
									<?php checked( in_array( $warning['slug'], (array) $context['params']['exclude_warnings'], true ) ); ?>
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
				<a class="fanfic-button" href="<?php echo esc_url( $context['base_url'] ); ?>">
					<?php esc_html_e( 'Clear all', 'fanfiction-manager' ); ?>
				</a>
			</div>
		</form>

		<div data-fanfic-active-filters>
		<?php if ( ! empty( $context['active_filters'] ) ) : ?>
			<div class="fanfic-active-filters">
				<p class="fanfic-filters-label"><strong><?php esc_html_e( 'Active Filters:', 'fanfiction-manager' ); ?></strong></p>
				<ul class="fanfic-filter-list">
					<?php foreach ( $context['active_filters'] as $filter ) : ?>
						<li class="fanfic-filter-item">
							<span class="fanfic-filter-label"><?php echo esc_html( $filter['label'] ); ?></span>
							<a href="<?php echo esc_url( $filter['url'] ); ?>" class="fanfic-filter-remove" aria-label="<?php esc_attr_e( 'Remove filter', 'fanfiction-manager' ); ?>">
								&times;
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( $context['base_url'] ); ?>" class="fanfic-clear-filters">
					<?php esc_html_e( 'Clear All Filters', 'fanfiction-manager' ); ?>
				</a>
			</div>
		<?php endif; ?>
		</div>
		<?php

		if ( $close_wrapper ) :
			self::$browse_wrapper_open = false;
			self::$browse_wrapper_opened_by_search = false;
			?>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Browse story archive shortcode
	 *
	 * [fanfic-story-archive]
	 *
	 * Outputs the story results and pagination container for browse pages.
	 *
	 * @since 1.2.0
	 * @return string Browse results HTML.
	 */
	public static function browse_story_archive() {
		$context = self::get_browse_context();

		$opened_here = false;
		if ( ! self::$browse_wrapper_open ) {
			self::$browse_wrapper_open = true;
			self::$browse_wrapper_opened_by_search = false;
			$opened_here = true;
		}

		$paged = absint( get_query_var( 'paged' ) );
		if ( $paged < 1 ) {
			$paged = absint( get_query_var( 'page' ) );
		}
		$paged = max( 1, $paged );
		$per_page = (int) get_option( 'posts_per_page', 10 );

		$browse_query = null;
		if ( function_exists( 'fanfic_build_browse_query_args' ) ) {
			$args = fanfic_build_browse_query_args( $context['params'], $paged, $per_page );
			$browse_query = new WP_Query( $args );
		}

		ob_start();

		if ( $opened_here ) :
			?>
			<div class="fanfic-archive fanfic-browse-page" data-fanfic-browse>
			<?php
		endif;
		?>

		<div class="fanfic-archive-content">
			<div class="fanfic-browse-results" data-fanfic-browse-results>
				<?php if ( $browse_query instanceof WP_Query && $browse_query->have_posts() ) : ?>
					<div class="fanfic-story-grid">
						<?php
						while ( $browse_query->have_posts() ) :
							$browse_query->the_post();
							echo fanfic_get_story_card_html( get_the_ID() );
						endwhile;
						?>
					</div>

					<nav class="fanfic-pagination fanfic-browse-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Stories pagination', 'fanfiction-manager' ); ?>" data-fanfic-browse-pagination>
						<?php
						$pagination_base = function_exists( 'fanfic_build_browse_url' )
							? fanfic_build_browse_url( $context['base_url'], $context['params'], array( 'paged' => null ) )
							: $context['base_url'];
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%', $pagination_base ),
							'format'    => '',
							'current'   => max( 1, $paged ),
							'total'     => max( 1, (int) $browse_query->max_num_pages ),
							'prev_text' => esc_html__( '&laquo; Previous', 'fanfiction-manager' ),
							'next_text' => esc_html__( 'Next &raquo;', 'fanfiction-manager' ),
						) );
						?>
					</nav>
				<?php else : ?>
					<div class="fanfic-no-results">
						<p><?php esc_html_e( 'No stories found matching your criteria.', 'fanfiction-manager' ); ?></p>
						<?php if ( ! empty( $context['has_filters'] ) ) : ?>
							<p>
								<a href="<?php echo esc_url( $context['base_url'] ); ?>" class="fanfic-button fanfic-button-primary">
									<?php esc_html_e( 'View All Stories', 'fanfiction-manager' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="fanfic-browse-loading" data-fanfic-browse-loading aria-hidden="true">
			<?php esc_html_e( 'Loading...', 'fanfiction-manager' ); ?>
		</div>

		<?php
		if ( $browse_query instanceof WP_Query ) {
			wp_reset_postdata();
		}

		self::$browse_archive_rendered = true;

		if ( $opened_here || self::$browse_wrapper_opened_by_search ) :
			self::$browse_wrapper_open = false;
			self::$browse_wrapper_opened_by_search = false;
			?>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Get browse context values.
	 *
	 * @since 1.2.0
	 * @return array Browse context data.
	 */
	private static function get_browse_context() {
		$base_url = function_exists( 'fanfic_get_page_url' ) ? fanfic_get_page_url( 'search' ) : '';
		if ( empty( $base_url ) ) {
			$base_url = function_exists( 'fanfic_get_story_archive_url' ) ? fanfic_get_story_archive_url() : home_url( '/' );
		}

		$params = function_exists( 'fanfic_get_browse_params' ) ? fanfic_get_browse_params() : array();

		$has_filters = ! empty( $params['search'] )
			|| ! empty( $params['genres'] )
			|| ! empty( $params['statuses'] )
			|| ! empty( $params['fandoms'] )
			|| ! empty( $params['exclude_warnings'] )
			|| ! empty( $params['age'] )
			|| ! empty( $params['sort'] );

		$page_title = esc_html__( 'Browse Stories', 'fanfiction-manager' );
		$page_description = $has_filters
			? esc_html__( 'Browse stories matching your selected filters.', 'fanfiction-manager' )
			: esc_html__( 'Browse all published fanfiction stories.', 'fanfiction-manager' );

		$genres = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );

		$statuses = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );

		$warnings = class_exists( 'Fanfic_Warnings' ) ? Fanfic_Warnings::get_available_warnings() : array();

		$active_filters = function_exists( 'fanfic_build_active_filters' )
			? fanfic_build_active_filters( $params, $base_url )
			: array();

		return array(
			'base_url'        => $base_url,
			'params'          => $params,
			'has_filters'     => $has_filters,
			'page_title'      => $page_title,
			'page_description'=> $page_description,
			'genres'          => $genres,
			'statuses'        => $statuses,
			'warnings'        => $warnings,
			'active_filters'  => $active_filters,
		);
	}
}
