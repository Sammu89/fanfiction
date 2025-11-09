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
	 * Register search shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'search-form', array( __CLASS__, 'search_form' ) );
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
}
