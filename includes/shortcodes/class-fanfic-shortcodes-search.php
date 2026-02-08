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
	 * Track stories wrapper state between shortcodes.
	 *
	 * @var bool
	 */
	private static $stories_wrapper_open = false;

	/**
	 * Track whether the wrapper was opened by the search bar shortcode.
	 *
	 * @var bool
	 */
	private static $stories_wrapper_opened_by_search = false;

	/**
	 * Track whether the archive shortcode already rendered.
	 *
	 * @var bool
	 */
	private static $stories_archive_rendered = false;

	/**
	 * Register search shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'fanfic-search-bar', array( __CLASS__, 'stories_search_bar' ) );
		add_shortcode( 'fanfic-story-archive', array( __CLASS__, 'stories_story_archive' ) );
	}

	/**
	 * Stories search bar shortcode
	 *
	 * [fanfic-search-bar]
	 *
	 * Outputs the stories header, filters, and active filters list.
	 *
	 * @since 1.2.0
	 * @return string Stories search bar HTML.
	 */
	public static function stories_search_bar() {
		wp_enqueue_script(
			'fanfic-search-bar-frontend',
			FANFIC_PLUGIN_URL . 'assets/js/fanfic-search-bar-frontend.js',
			array( 'jquery' ),
			FANFIC_VERSION,
			true
		);

		wp_enqueue_style(
			'fanfic-search-bar-frontend',
			FANFIC_PLUGIN_URL . 'assets/css/fanfic-search-bar.css',
			array(),
			FANFIC_VERSION
		);

		// Generic pills CSS (reusable throughout site)
		wp_enqueue_style(
			'fanfic-pills',
			FANFIC_PLUGIN_URL . 'assets/css/fanfic-pills.css',
			array(),
			FANFIC_VERSION
		);
		// Enqueue fandoms script if Fanfic_Fandoms is enabled
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			wp_enqueue_script(
				'fanfiction-fandoms',
				FANFIC_PLUGIN_URL . 'assets/js/fanfiction-fandoms.js',
				array(),
				FANFIC_VERSION,
				true
			);

			wp_localize_script(
				'fanfiction-fandoms',
				'fanficFandoms',
				array(
					'restUrl'    => esc_url_raw( rest_url( Fanfic_Fandoms::REST_NAMESPACE . '/fandoms/search' ) ),
					'restNonce'  => wp_create_nonce( 'wp_rest' ),
					'maxFandoms' => Fanfic_Fandoms::MAX_FANDOMS,
					'strings'    => array(
						'remove' => __( 'Remove fandom', 'fanfiction-manager' ),
					),
				)
			);
		}

		$context = self::get_stories_context();

		$open_wrapper = ! self::$stories_wrapper_open;
		$close_wrapper = false;

		if ( $open_wrapper ) {
			self::$stories_wrapper_open = true;
			self::$stories_wrapper_opened_by_search = true;
			if ( self::$stories_archive_rendered ) {
				$close_wrapper = true;
			}
		}

		ob_start();

		if ( $open_wrapper ) :
			?>
			<div class="fanfic-archive fanfic-stories-page" data-fanfic-stories>
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
			<div class="fanfic-basic-search-row">
				<div class="fanfic-search-input-wrapper">
					<label for="fanfic-search-input" class="screen-reader-text"><?php esc_html_e( 'Search stories', 'fanfiction-manager' ); ?></label>
					<input
						type="text"
						id="fanfic-search-input"
						name="search"
						value="<?php echo esc_attr( $context['params']['search'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'Search titles, tags, authors...', 'fanfiction-manager' ); ?>"
					/>
				</div>

				<div class="fanfic-status-filter-wrapper">
					<label><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></label>
					<div class="multi-select" data-placeholder="<?php esc_attr_e( 'Select Status', 'fanfiction-manager' ); ?>">
						<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
							<?php esc_html_e( 'Select Status', 'fanfiction-manager' ); ?>
						</button>
						<div class="multi-select__dropdown">
							<?php if ( ! empty( $context['statuses'] ) && ! is_wp_error( $context['statuses'] ) ) : ?>
								<?php foreach ( $context['statuses'] as $status ) : ?>
									<label>
										<input type="checkbox" name="status[]" value="<?php echo esc_attr( $status->slug ); ?>" <?php in_array( $status->slug, (array) ( $context['params']['statuses'] ?? [] ) ) ? checked( true ) : ''; ?> />
										<?php echo esc_html( $status->name ); ?>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="fanfic-sort-filter-wrapper">
					<label for="fanfic-sort-filter" class="screen-reader-text"><?php esc_html_e( 'Sort by', 'fanfiction-manager' ); ?></label>
					<select id="fanfic-sort-filter" name="sort">
						<option value=""><?php esc_html_e( 'Relevance / Updated', 'fanfiction-manager' ); ?></option>
						<option value="updated" <?php selected( 'updated', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Recently updated', 'fanfiction-manager' ); ?></option>
						<option value="created" <?php selected( 'created', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Newest', 'fanfiction-manager' ); ?></option>
						<option value="alphabetical" <?php selected( 'alphabetical', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'A-Z', 'fanfiction-manager' ); ?></option>
					</select>
				</div>

				<button type="button" class="fanfic-button fanfic-clear-search-button" id="fanfic-clear-filters-button">
					<?php esc_html_e( 'Clear filters', 'fanfiction-manager' ); ?>
				</button>
				<button type="submit" class="fanfic-button fanfic-search-submit">
					<?php esc_html_e( 'Search', 'fanfiction-manager' ); ?>
				</button>
			</div>

			<!-- Common Filters (Always Visible) -->
			<div class="fanfic-common-filters">
				<div class="fanfic-browse-row fanfic-browse-columns">
					<div class="fanfic-browse-column">
						<label for="fanfic-genre-filter"><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></label>
						<div class="multi-select" data-placeholder="<?php esc_attr_e( 'Select Genres', 'fanfiction-manager' ); ?>">
							<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
								<?php esc_html_e( 'Select Genres', 'fanfiction-manager' ); ?>
							</button>
							<div class="multi-select__dropdown">
								<?php if ( ! empty( $context['genres'] ) && ! is_wp_error( $context['genres'] ) ) : ?>
									<?php foreach ( $context['genres'] as $genre ) : ?>
										<label>
											<input
												type="checkbox"
												name="genre[]"
												value="<?php echo esc_attr( $genre->slug ); ?>"
												<?php checked( in_array( $genre->slug, (array) $context['params']['genres'], true ) ); ?>
											/>
											<?php echo esc_html( $genre->name ); ?>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<div class="fanfic-browse-column">
						<label><?php esc_html_e( 'Age rating', 'fanfiction-manager' ); ?></label>
						<div class="multi-select" data-placeholder="<?php esc_attr_e( 'Select Age Rating', 'fanfiction-manager' ); ?>">
							<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
								<?php esc_html_e( 'Select Age Rating', 'fanfiction-manager' ); ?>
							</button>
							<div class="multi-select__dropdown">
								<label>
									<input type="checkbox" name="age[]" value="PG" <?php in_array( 'PG', (array) ( $context['params']['age'] ?? [] ) ) ? checked( true ) : ''; ?> />
									<?php esc_html_e( 'PG', 'fanfiction-manager' ); ?>
								</label>
								<label>
									<input type="checkbox" name="age[]" value="13" <?php in_array( '13', (array) ( $context['params']['age'] ?? [] ) ) ? checked( true ) : ''; ?> />
									<?php esc_html_e( '13+', 'fanfiction-manager' ); ?>
								</label>
								<label>
									<input type="checkbox" name="age[]" value="16" <?php in_array( '16', (array) ( $context['params']['age'] ?? [] ) ) ? checked( true ) : ''; ?> />
									<?php esc_html_e( '16+', 'fanfiction-manager' ); ?>
								</label>
								<label>
									<input type="checkbox" name="age[]" value="18" <?php in_array( '18', (array) ( $context['params']['age'] ?? [] ) ) ? checked( true ) : ''; ?> />
									<?php esc_html_e( '18+', 'fanfiction-manager' ); ?>
								</label>
							</div>
						</div>

					<?php if ( ! empty( $context['languages'] ) ) : ?>
						<div class="fanfic-browse-column">
							<label for="fanfic-language-filter"><?php esc_html_e( 'Language', 'fanfiction-manager' ); ?></label>
							<div class="multi-select" data-placeholder="<?php esc_attr_e( 'Select Languages', 'fanfiction-manager' ); ?>">
								<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
									<?php esc_html_e( 'Select Languages', 'fanfiction-manager' ); ?>
								</button>
								<div class="multi-select__dropdown">
									<?php foreach ( $context['languages'] as $language ) : ?>
										<label>
											<input
												type="checkbox"
												name="language[]"
												value="<?php echo esc_attr( $language['slug'] ); ?>"
												<?php checked( in_array( $language['slug'], (array) $context['params']['languages'], true ) ); ?>
											/>
											<?php
											echo esc_html( $language['name'] );
											if ( ! empty( $language['native_name'] ) && $language['native_name'] !== $language['name'] ) {
												echo ' (' . esc_html( $language['native_name'] ) . ')';
											}
											?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Advanced Filters (Collapsible) -->
			<div class="fanfic-advanced-search-toggle" aria-expanded="false" role="button" tabindex="0">
				<span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'More filters', 'fanfiction-manager' ); ?>
			</div>

			<div class="fanfic-advanced-search-filters" style="display: none;">
				<div class="fanfic-browse-row fanfic-smart-toggle-wrapper">
					<label class="fanfic-toggle-label" for="fanfic-match-all-filters">
						<?php esc_html_e( 'Match ALL selected filters', 'fanfiction-manager' ); ?>
						<span class="fanfic-toggle-subtext"><?php esc_html_e( 'When ON, we\'ll show stories that match ALL the selections within a category.', 'fanfiction-manager' ); ?></span>
					</label>
					<label class="fanfic-switch">
						<input type="checkbox" id="fanfic-match-all-filters" name="match_all_filters" value="1" <?php checked( ( $context['params']['match_all_filters'] ?? '0' ) === '1' ); ?>>
						<span class="fanfic-slider round"></span>
					</label>
				</div>

				<?php if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) : ?>
					<?php
					// Get current fandoms from URL parameters
					$current_fandom_labels = array();
					if ( ! empty( $context['params']['fandoms'] ) ) {
						foreach ( (array) $context['params']['fandoms'] as $fandom_slug ) {
							$fandom_id = Fanfic_Fandoms::get_fandom_id_by_slug( $fandom_slug );
							if ( $fandom_id ) {
								$fandom_label = Fanfic_Fandoms::get_fandom_label_by_id( $fandom_id );
								if ( $fandom_label ) {
									$current_fandom_labels[] = array(
										'id'    => $fandom_id,
										'label' => $fandom_label,
									);
								}
							}
						}
					}
					?>
					<div class="fanfic-browse-row fanfic-fandoms-field" data-max-fandoms="<?php echo esc_attr( Fanfic_Fandoms::MAX_FANDOMS ); ?>">
						<label for="fanfic-fandom-filter"><?php esc_html_e( 'Fandoms', 'fanfiction-manager' ); ?></label>
						<input
							type="text"
							id="fanfic-fandom-filter"
							class="fanfic-input"
							autocomplete="off"
							placeholder="<?php esc_attr_e( 'Search fandoms...', 'fanfiction-manager' ); ?>"
						/>
						<div class="fanfic-fandom-results" role="listbox" aria-label="<?php esc_attr_e( 'Fandom search results', 'fanfiction-manager' ); ?>"></div>
						<div class="fanfic-selected-fandoms" aria-live="polite">
							<?php foreach ( $current_fandom_labels as $fandom ) : ?>
								<span class="fanfic-selected-fandom" data-id="<?php echo esc_attr( $fandom['id'] ); ?>">
									<?php echo esc_html( $fandom['label'] ); ?>
									<button type="button" class="fanfic-remove-fandom" aria-label="<?php esc_attr_e( 'Remove fandom', 'fanfiction-manager' ); ?>">&times;</button>
									<input type="hidden" name="fanfic_story_fandoms[]" value="<?php echo esc_attr( $fandom['id'] ); ?>">
								</span>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Select up to 5 fandoms. Search requires at least 2 characters.', 'fanfiction-manager' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $context['custom_taxonomies'] ) ) : ?>
					<?php foreach ( $context['custom_taxonomies'] as $custom_taxonomy ) : ?>
						<?php if ( ! empty( $custom_taxonomy['terms'] ) ) : ?>
							<?php
							$custom_params = isset( $context['params']['custom'][ $custom_taxonomy['slug'] ] ) ? (array) $context['params']['custom'][ $custom_taxonomy['slug'] ] : array();
							?>
							<div class="fanfic-browse-row">
								<label><?php echo esc_html( $custom_taxonomy['name'] ); ?></label>
								<?php if ( 'single' === $custom_taxonomy['selection_type'] ) : ?>
									<select id="fanfic-<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>-filter" name="<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>">
										<option value=""><?php esc_html_e( 'Any', 'fanfiction-manager' ); ?></option>
										<?php foreach ( $custom_taxonomy['terms'] as $term ) : ?>
											<option value="<?php echo esc_attr( $term['slug'] ); ?>" <?php selected( in_array( $term['slug'], $custom_params, true ) ); ?>>
												<?php echo esc_html( $term['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<div class="multi-select" data-placeholder="<?php echo esc_attr( sprintf( __( 'Select %s', 'fanfiction-manager' ), $custom_taxonomy['name'] ) ); ?>">
										<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
											<?php echo esc_html( sprintf( __( 'Select %s', 'fanfiction-manager' ), $custom_taxonomy['name'] ) ); ?>
										</button>
										<div class="multi-select__dropdown">
											<?php foreach ( $custom_taxonomy['terms'] as $term ) : ?>
												<label>
													<input
														type="checkbox"
														name="<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>[]"
														value="<?php echo esc_attr( $term['slug'] ); ?>"
														<?php checked( in_array( $term['slug'], $custom_params, true ) ); ?>
													/>
													<?php echo esc_html( $term['name'] ); ?>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( ! empty( $context['warnings'] ) ) : ?>
					<div class="fanfic-browse-row fanfic-browse-warnings">
						<label class="fanfic-browse-label"><?php esc_html_e( 'Warnings', 'fanfiction-manager' ); ?></label>

						<div class="fanfic-warnings-mode">
							<label>
								<input type="radio" name="warnings_mode" value="exclude" <?php checked( ( $context['params']['warnings_mode'] ?? 'exclude' ) === 'exclude' ); ?>>
								<?php esc_html_e( 'Exclude', 'fanfiction-manager' ); ?>
							</label>
							<label>
								<input type="radio" name="warnings_mode" value="include" <?php checked( ( $context['params']['warnings_mode'] ?? 'exclude' ) === 'include' ); ?>>
								<?php esc_html_e( 'Include', 'fanfiction-manager' ); ?>
							</label>
						</div>

						<div class="multi-select fanfic-warnings-multiselect" data-placeholder="<?php esc_attr_e( 'Select Warnings', 'fanfiction-manager' ); ?>">
							<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
								<?php esc_html_e( 'Select Warnings', 'fanfiction-manager' ); ?>
							</button>
							<div class="multi-select__dropdown">
								<?php foreach ( $context['warnings'] as $warning ) : ?>
									<label>
										<input
											type="checkbox"
											name="warnings_slugs[]"
											value="<?php echo esc_attr( $warning['slug'] ); ?>"
											<?php checked( in_array( $warning['slug'], (array) $context['params']['selected_warnings'], true ) ); ?>
										/>
										<?php echo esc_html( $warning['name'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="fanfic-browse-actions fanfic-advanced-actions" style="display: none;">
			</div>
		</form>

		<?php
		// Localize script with base_url and translations for live pills
		wp_localize_script(
			'fanfic-search-bar-frontend',
			'fanficSearchBar',
			array(
				'baseUrl' => esc_url_raw( $context['base_url'] ),
				'i18n'    => array(
					'activeFilters' => esc_html__( 'Active Filters', 'fanfiction-manager' ),
				),
			)
		);
		?>

		<!-- Current Filters Section with Live Pills -->
		<div class="fanfic-current-filters-section">
			<h3 class="fanfic-current-filters-label"><?php esc_html_e( 'Current Filters', 'fanfiction-manager' ); ?></h3>
			<div class="fanfic-current-filters-container" data-fanfic-active-filters></div>
		</div>
		<?php

		if ( $close_wrapper ) :
			self::$stories_wrapper_open = false;
			self::$stories_wrapper_opened_by_search = false;
			?>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Stories story archive shortcode
	 *
	 * [fanfic-story-archive]
	 *
	 * Outputs the story results and pagination container for stories pages.
	 *
	 * @since 1.2.0
	 * @return string Stories results HTML.
	 */
	public static function stories_story_archive() {
		$context = self::get_stories_context();

		$opened_here = false;
		if ( ! self::$stories_wrapper_open ) {
			self::$stories_wrapper_open = true;
			self::$stories_wrapper_opened_by_search = false;
			$opened_here = true;
		}

		// Check if we're in "stories all terms" mode.
		$is_stories_all = function_exists( 'fanfic_is_stories_all_terms_mode' ) && fanfic_is_stories_all_terms_mode();

		ob_start();

		if ( $opened_here ) :
			?>
			<div class="fanfic-archive fanfic-stories-page" data-fanfic-stories>
			<?php
		endif;

		if ( $is_stories_all ) :
			// Display taxonomy terms directory.
			echo self::render_stories_all_terms();
		else :
			// Display normal story results.
			$paged = absint( get_query_var( 'paged' ) );
			if ( $paged < 1 ) {
				$paged = absint( get_query_var( 'page' ) );
			}
			$paged = max( 1, $paged );
			$per_page = (int) get_option( 'posts_per_page', 10 );

			$stories_query = null;
			if ( function_exists( 'fanfic_build_stories_query_args' ) ) {
				$args = fanfic_build_stories_query_args( $context['params'], $paged, $per_page );
				$stories_query = new WP_Query( $args );
			}
			?>

			<div class="fanfic-archive-content">
				<div class="fanfic-stories-results" data-fanfic-browse-results>
					<?php if ( $stories_query instanceof WP_Query && $stories_query->have_posts() ) : ?>
						<div class="fanfic-story-grid">
							<?php
							while ( $stories_query->have_posts() ) :
								$stories_query->the_post();
								echo fanfic_get_story_card_html( get_the_ID() );
							endwhile;
							?>
						</div>

						<nav class="fanfic-pagination fanfic-browse-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Stories pagination', 'fanfiction-manager' ); ?>" data-fanfic-browse-pagination>
							<?php
							$pagination_base = function_exists( 'fanfic_build_stories_url' )
								? fanfic_build_stories_url( $context['base_url'], $context['params'], array( 'paged' => null ) )
								: $context['base_url'];
							echo paginate_links( array(
								'base'      => add_query_arg( 'paged', '%#%', $pagination_base ),
								'format'    => '',
								'current'   => max( 1, $paged ),
								'total'     => max( 1, (int) $stories_query->max_num_pages ),
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
									<a href="<?php echo esc_url( $context['base_url'] ); ?>" class="fanfic-button">
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
			if ( $stories_query instanceof WP_Query ) {
				wp_reset_postdata();
			}
		endif;

		self::$stories_archive_rendered = true;

		if ( $opened_here || self::$stories_wrapper_opened_by_search ) :
			self::$stories_wrapper_open = false;
			self::$stories_wrapper_opened_by_search = false;
			?>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Render the stories all terms directory.
	 *
	 * @since 1.2.0
	 * @return string Stories all terms HTML.
	 */
	private static function render_stories_all_terms() {
		if ( ! function_exists( 'fanfic_get_stories_all_taxonomy' ) || ! function_exists( 'fanfic_get_taxonomy_terms_with_counts_for_stories_all' ) ) {
			return '';
		}

		$taxonomy_config = fanfic_get_stories_all_taxonomy();
		if ( empty( $taxonomy_config ) ) {
			return '';
		}

		$terms = fanfic_get_taxonomy_terms_with_counts_for_stories_all( $taxonomy_config );

		ob_start();
		?>
		<div class="fanfic-archive-content">
			<div class="fanfic-stories-results">
				<header class="fanfic-taxonomy-directory-header">
					<h2 class="fanfic-taxonomy-directory-title">
						<?php
						printf(
							/* translators: %s: Taxonomy label (e.g., "Genres", "Fandoms") */
							esc_html__( 'Browse by %s', 'fanfiction-manager' ),
							esc_html( $taxonomy_config['label'] )
						);
						?>
					</h2>
					<p class="fanfic-taxonomy-directory-description">
						<?php
						printf(
							/* translators: %d: Number of terms */
							esc_html( _n(
								'%d term with stories available.',
								'%d terms with stories available.',
								count( $terms ),
								'fanfiction-manager'
							) ),
							count( $terms )
						);
						?>
					</p>
				</header>

				<?php if ( ! empty( $terms ) ) : ?>
					<div class="fanfic-taxonomy-directory">
						<?php foreach ( $terms as $term ) : ?>
							<div class="fanfic-taxonomy-directory-item">
								<a href="<?php echo esc_url( $term['url'] ); ?>" class="fanfic-taxonomy-directory-link">
									<span class="fanfic-taxonomy-directory-name">
										<?php echo esc_html( $term['name'] ); ?>
									</span>
									<span class="fanfic-taxonomy-directory-count">
										<?php
										printf(
											/* translators: %d: Number of stories */
											esc_html( _n(
												'%d story',
												'%d stories',
												$term['count'],
												'fanfiction-manager'
											) ),
											$term['count']
										);
										?>
									</span>
								</a>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="fanfic-no-results">
						<p><?php esc_html_e( 'No terms found with published stories.', 'fanfiction-manager' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get stories context values.
	 *
	 * @since 1.2.0
	 * @return array Stories context data.
	 */
	private static function get_stories_context() {
		// Get the permalink of the current page where the shortcode is embedded.
		// This ensures the form submits to the same page.
		$base_url = get_the_permalink();

		// Fallback for cases where get_the_permalink() might not return a valid URL,
		// e.g., if the shortcode is placed on a dynamically generated page without a static ID.
		if ( ! $base_url ) {
			// Get current URL without query parameters.
			global $wp;
			$base_url = home_url( $wp->request );
		}

		$params = function_exists( 'fanfic_get_stories_params' ) ? fanfic_get_stories_params() : array();

		$has_filters = ! empty( $params['search'] )
			|| ! empty( $params['genres'] )
			|| ! empty( $params['statuses'] )
			|| ! empty( $params['fandoms'] )
			|| ! empty( $params['languages'] )
			|| ! empty( $params['custom'] )
			|| ! empty( $params['exclude_warnings'] )
			|| ! empty( $params['age'] )
			|| ! empty( $params['sort'] );

		// Check if we're in "stories all terms" mode.
		$is_stories_all = function_exists( 'fanfic_is_stories_all_terms_mode' ) && fanfic_is_stories_all_terms_mode();
		if ( $is_stories_all && function_exists( 'fanfic_get_stories_all_taxonomy' ) ) {
			$taxonomy_config = fanfic_get_stories_all_taxonomy();
			$page_title = sprintf(
				/* translators: %s: Taxonomy label (e.g., "Genres", "Fandoms") */
				esc_html__( 'Browse by %s', 'fanfiction-manager' ),
				esc_html( $taxonomy_config['label'] )
			);
			$page_description = sprintf(
				/* translators: %s: Taxonomy label */
				esc_html__( 'Browse all %s that have published stories.', 'fanfiction-manager' ),
				strtolower( esc_html( $taxonomy_config['label'] ) )
			);
		} else {
			$page_title = esc_html__( 'Browse Stories', 'fanfiction-manager' );
			$page_description = $has_filters
				? esc_html__( 'Browse stories matching your selected filters.', 'fanfiction-manager' )
				: esc_html__( 'Browse all published fanfiction stories.', 'fanfiction-manager' );
		}

		$genres = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );

		$statuses = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );

		$warnings = class_exists( 'Fanfic_Warnings' ) ? Fanfic_Warnings::get_available_warnings() : array();

		$languages = array();
		if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
			$languages = Fanfic_Languages::get_active_languages();
		}

		$custom_taxonomies = array();
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$all_custom = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $all_custom as $taxonomy ) {
				$custom_taxonomies[] = array(
					'id'             => $taxonomy['id'],
					'slug'           => $taxonomy['slug'],
					'name'           => $taxonomy['name'],
					'selection_type' => $taxonomy['selection_type'],
					'terms'          => Fanfic_Custom_Taxonomies::get_active_terms( $taxonomy['id'] ),
				);
			}
		}


		return array(
			'base_url'          => $base_url,
			'params'            => $params,
			'has_filters'       => $has_filters,
			'page_title'        => $page_title,
			'page_description'  => $page_description,
			'genres'            => $genres,
			'statuses'          => $statuses,
			'warnings'          => $warnings,
			'languages'         => $languages,
			'custom_taxonomies' => $custom_taxonomies,
		);
	}
}
