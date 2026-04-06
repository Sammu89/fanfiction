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
	 * Get the shared tooltip copy for disabled search filters.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private static function get_unavailable_filter_tooltip() {
		return __( 'No stories with this filter exist', 'fanfiction-manager' );
	}

	/**
	 * Filter scalar values by their positive count.
	 *
	 * @since 2.0.0
	 * @param array $values Value list.
	 * @param array $counts Count map.
	 * @return array
	 */
	private static function filter_scalar_values_by_counts( $values, $counts ) {
		$filtered = array();

		foreach ( (array) $values as $value ) {
			if ( ! empty( $counts[ $value ] ) ) {
				$filtered[] = $value;
			}
		}

		return $filtered;
	}

	/**
	 * Filter array items with a slug key by positive count.
	 *
	 * @since 2.0.0
	 * @param array  $items    Item list.
	 * @param array  $counts   Count map.
	 * @param string $slug_key Slug array key.
	 * @return array
	 */
	private static function filter_array_items_by_counts( $items, $counts, $slug_key = 'slug' ) {
		$filtered = array();

		foreach ( (array) $items as $item ) {
			if ( ! isset( $item[ $slug_key ] ) ) {
				continue;
			}

			if ( ! empty( $counts[ $item[ $slug_key ] ] ) ) {
				$filtered[] = $item;
			}
		}

		return $filtered;
	}

	/**
	 * Filter term objects by positive count.
	 *
	 * @since 2.0.0
	 * @param array $terms  Term list.
	 * @param array $counts Count map.
	 * @return array
	 */
	private static function filter_term_objects_by_counts( $terms, $counts ) {
		$filtered = array();

		foreach ( (array) $terms as $term ) {
			if ( empty( $term->slug ) ) {
				continue;
			}

			if ( ! empty( $counts[ $term->slug ] ) ) {
				$filtered[] = $term;
			}
		}

		return $filtered;
	}

	/**
	 * Render a standard checkbox multiselect search filter.
	 *
	 * @since 2.0.0
	 * @param array $args Filter config.
	 * @return string
	 */
	private static function render_search_multiselect_filter( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'         => '',
				'placeholder'   => '',
				'input_name'    => '',
				'options'       => array(),
				'selected'      => array(),
				'disabled'      => false,
				'tooltip'       => '',
				'wrapper_class' => '',
			)
		);

		$wrapper_class = trim( 'fanfic-advanced-search-item ' . $args['wrapper_class'] );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<label><?php echo esc_html( $args['label'] ); ?></label>
			<div class="multi-select" data-placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>">
				<button
					type="button"
					class="multi-select__trigger"
					aria-haspopup="listbox"
					<?php disabled( ! empty( $args['disabled'] ) ); ?>
					<?php if ( ! empty( $args['disabled'] ) && '' !== $args['tooltip'] ) : ?>
						title="<?php echo esc_attr( $args['tooltip'] ); ?>"
					<?php endif; ?>
				>
					<?php echo esc_html( $args['placeholder'] ); ?>
				</button>
				<div class="multi-select__dropdown">
					<?php foreach ( (array) $args['options'] as $option ) : ?>
						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( $args['input_name'] ); ?>"
								value="<?php echo esc_attr( $option['value'] ); ?>"
								<?php checked( in_array( (string) $option['value'], array_map( 'strval', (array) $args['selected'] ), true ) ); ?>
							/>
							<?php echo esc_html( sprintf( '%s (%d)', $option['label'], absint( $option['count'] ) ) ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a standard single-select search filter.
	 *
	 * @since 2.0.0
	 * @param array $args Filter config.
	 * @return string
	 */
	private static function render_search_select_filter( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'         => '',
				'input_id'      => '',
				'input_name'    => '',
				'placeholder'   => __( 'Any', 'fanfiction-manager' ),
				'options'       => array(),
				'selected'      => '',
				'disabled'      => false,
				'tooltip'       => '',
				'wrapper_class' => '',
			)
		);

		$wrapper_class = trim( 'fanfic-advanced-search-item ' . $args['wrapper_class'] );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<label for="<?php echo esc_attr( $args['input_id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
			<select
				id="<?php echo esc_attr( $args['input_id'] ); ?>"
				name="<?php echo esc_attr( $args['input_name'] ); ?>"
				<?php disabled( ! empty( $args['disabled'] ) ); ?>
				<?php if ( ! empty( $args['disabled'] ) && '' !== $args['tooltip'] ) : ?>
					title="<?php echo esc_attr( $args['tooltip'] ); ?>"
				<?php endif; ?>
			>
				<option value=""><?php echo esc_html( $args['placeholder'] ); ?></option>
				<?php foreach ( (array) $args['options'] as $option ) : ?>
					<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( (string) $args['selected'], (string) $option['value'] ); ?>>
						<?php echo esc_html( sprintf( '%s (%d)', $option['label'], absint( $option['count'] ) ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Build the selected fandom labels for the search filter.
	 *
	 * @since 2.0.0
	 * @param array $params Current search params.
	 * @return array
	 */
	private static function get_selected_fandom_labels( $params ) {
		$current_fandom_labels = array();

		if ( empty( $params['fandoms'] ) || ! class_exists( 'Fanfic_Fandoms' ) || ! Fanfic_Fandoms::is_enabled() ) {
			return $current_fandom_labels;
		}

		foreach ( (array) $params['fandoms'] as $fandom_slug ) {
			$fandom_id = Fanfic_Fandoms::get_fandom_id_by_slug( $fandom_slug );
			if ( ! $fandom_id ) {
				continue;
			}

			$fandom_label = Fanfic_Fandoms::get_fandom_label_by_id( $fandom_id );
			if ( ! $fandom_label ) {
				continue;
			}

			$current_fandom_labels[] = array(
				'id'    => $fandom_id,
				'label' => $fandom_label,
			);
		}

		return $current_fandom_labels;
	}

	/**
	 * Render the fandom search filter.
	 *
	 * @since 2.0.0
	 * @param array $context Search context.
	 * @return string
	 */
	private static function render_fandom_search_filter( $context ) {
		return fanfic_render_fandom_multiselect_field(
			array(
				'wrapper_class'       => 'fanfic-advanced-search-item fanfic-fandoms-field',
				'input_id'            => 'fanfic-fandom-filter',
				'selected_fandoms'    => self::get_selected_fandom_labels( $context['params'] ),
				'trigger_placeholder' => __( 'Select Fandoms', 'fanfiction-manager' ),
				'search_placeholder'  => __( 'Search fandoms...', 'fanfiction-manager' ),
				'field_disabled'      => empty( $context['has_fandom_options'] ),
				'disabled_title'      => $context['unavailable_filter_tip'],
				'preloaded_options'   => $context['preloaded_fandoms'],
				'show_all_on_click'   => ! empty( $context['preloaded_fandoms'] ),
			)
		);
	}

	/**
	 * Render the minimum rating filter.
	 *
	 * @since 2.0.0
	 * @param float $rating_min Selected minimum rating.
	 * @return string
	 */
	private static function render_rating_search_filter( $rating_min ) {
		ob_start();
		?>
		<div class="fanfic-advanced-search-item fanfic-advanced-search-item--rating">
			<div class="fanfic-rating-range-filter">
				<div class="fanfic-rating-range-header">
					<label for="fanfic-rating-min"><?php esc_html_e( 'Minimum rating', 'fanfiction-manager' ); ?></label>
					<span class="fanfic-rating-range-value" data-fanfic-rating-value>
						<?php echo $rating_min > 0
							? esc_html( number_format_i18n( $rating_min, 1 ) . '+' )
							: esc_html__( 'Any', 'fanfiction-manager' ); ?>
					</span>
				</div>
				<input
					type="range"
					id="fanfic-rating-min"
					name="rating_min"
					min="0"
					max="5"
					step="0.5"
					value="<?php echo esc_attr( $rating_min > 0 ? $rating_min : 0 ); ?>"
				/>
				<div class="fanfic-rating-range-scale" aria-hidden="true">
					<span><?php esc_html_e( 'Any', 'fanfiction-manager' ); ?></span>
					<span>5.0</span>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a searchable custom taxonomy filter.
	 *
	 * @since 2.0.0
	 * @param array $context          Search context.
	 * @param array $custom_taxonomy  Custom taxonomy config.
	 * @return string
	 */
	private static function render_custom_taxonomy_search_filter( $context, $custom_taxonomy ) {
		$custom_params = isset( $context['params']['custom'][ $custom_taxonomy['slug'] ] ) ? (array) $context['params']['custom'][ $custom_taxonomy['slug'] ] : array();
		$custom_counts = isset( $context['filter_counts']['custom'][ $custom_taxonomy['slug'] ] ) && is_array( $context['filter_counts']['custom'][ $custom_taxonomy['slug'] ] )
			? $context['filter_counts']['custom'][ $custom_taxonomy['slug'] ]
			: array();
		$options = array();

		foreach ( (array) $custom_taxonomy['available_terms'] as $term ) {
			$term_slug = isset( $term['slug'] ) ? (string) $term['slug'] : '';
			if ( '' === $term_slug ) {
				continue;
			}

			$options[] = array(
				'value' => $term_slug,
				'label' => isset( $term['name'] ) ? (string) $term['name'] : $term_slug,
				'count' => isset( $custom_counts[ $term_slug ] ) ? absint( $custom_counts[ $term_slug ] ) : 0,
			);
		}

		if ( 'single' === $custom_taxonomy['selection_type'] ) {
			return self::render_search_select_filter(
				array(
					'label'       => $custom_taxonomy['name'],
					'input_id'    => 'fanfic-' . sanitize_html_class( $custom_taxonomy['slug'] ) . '-filter',
					'input_name'  => $custom_taxonomy['slug'],
					'placeholder' => __( 'Any', 'fanfiction-manager' ),
					'options'     => $options,
					'selected'    => ! empty( $custom_params ) ? reset( $custom_params ) : '',
					'disabled'    => empty( $custom_taxonomy['has_available_terms'] ),
					'tooltip'     => $context['unavailable_filter_tip'],
				)
			);
		}

		return self::render_search_multiselect_filter(
			array(
				'label'       => $custom_taxonomy['name'],
				'placeholder' => sprintf( __( 'Select %s', 'fanfiction-manager' ), $custom_taxonomy['name'] ),
				'input_name'  => $custom_taxonomy['slug'] . '[]',
				'options'     => $options,
				'selected'    => $custom_params,
				'disabled'    => empty( $custom_taxonomy['has_available_terms'] ),
				'tooltip'     => $context['unavailable_filter_tip'],
			)
		);
	}

	/**
	 * Build the ordered advanced search filters.
	 *
	 * @since 2.0.0
	 * @param array $context Search context.
	 * @return array
	 */
	private static function get_advanced_search_filters( $context ) {
		$filters    = array();
		$rating_min = isset( $context['params']['rating_min'] ) ? (float) $context['params']['rating_min'] : 0.0;

		$status_options = array();
		foreach ( (array) $context['available_statuses'] as $status ) {
			$status_options[] = array(
				'value' => $status->slug,
				'label' => $status->name,
				'count' => isset( $context['filter_counts']['statuses'][ $status->slug ] ) ? absint( $context['filter_counts']['statuses'][ $status->slug ] ) : 0,
			);
		}
		$filters[] = self::render_search_multiselect_filter(
			array(
				'label'         => __( 'Status', 'fanfiction-manager' ),
				'placeholder'   => __( 'All Statuses', 'fanfiction-manager' ),
				'input_name'    => 'status[]',
				'options'       => $status_options,
				'selected'      => (array) ( $context['params']['statuses'] ?? array() ),
				'disabled'      => empty( $context['available_statuses'] ),
				'tooltip'       => $context['unavailable_filter_tip'],
				'wrapper_class' => 'fanfic-status-filter-wrapper',
			)
		);

		$genre_options = array();
		foreach ( (array) $context['available_genres'] as $genre ) {
			$genre_options[] = array(
				'value' => $genre->slug,
				'label' => $genre->name,
				'count' => isset( $context['filter_counts']['genres'][ $genre->slug ] ) ? absint( $context['filter_counts']['genres'][ $genre->slug ] ) : 0,
			);
		}
		$filters[] = self::render_search_multiselect_filter(
			array(
				'label'       => __( 'Genres', 'fanfiction-manager' ),
				'placeholder' => __( 'Select Genres', 'fanfiction-manager' ),
				'input_name'  => 'genre[]',
				'options'     => $genre_options,
				'selected'    => (array) ( $context['params']['genres'] ?? array() ),
				'disabled'    => empty( $context['available_genres'] ),
				'tooltip'     => $context['unavailable_filter_tip'],
			)
		);

		if ( ! empty( $context['show_language_filter'] ) ) {
			$language_options = array();
			foreach ( (array) $context['available_languages'] as $language ) {
				$language_slug = $language['slug'];
				$language_label = $language['name'];
				if ( ! empty( $language['native_name'] ) && $language['native_name'] !== $language['name'] ) {
					$language_label .= ' (' . $language['native_name'] . ')';
				}

				$language_options[] = array(
					'value' => $language_slug,
					'label' => $language_label,
					'count' => isset( $context['filter_counts']['languages'][ $language_slug ] ) ? absint( $context['filter_counts']['languages'][ $language_slug ] ) : 0,
				);
			}

			$filters[] = self::render_search_multiselect_filter(
				array(
					'label'       => __( 'Language', 'fanfiction-manager' ),
					'placeholder' => __( 'Select Languages', 'fanfiction-manager' ),
					'input_name'  => 'language[]',
					'options'     => $language_options,
					'selected'    => (array) ( $context['params']['languages'] ?? array() ),
					'disabled'    => empty( $context['available_languages'] ),
					'tooltip'     => $context['unavailable_filter_tip'],
				)
			);
		}

		if ( ! empty( $context['show_age_filter'] ) ) {
			$age_options = array();
			foreach ( (array) $context['available_age_options'] as $age_value ) {
				$age_label = function_exists( 'fanfic_get_age_display_label' ) ? fanfic_get_age_display_label( $age_value, true ) : (string) $age_value;
				if ( '' === $age_label ) {
					$age_label = (string) $age_value;
				}

				$age_options[] = array(
					'value' => $age_value,
					'label' => $age_label,
					'count' => isset( $context['filter_counts']['ages'][ $age_value ] ) ? absint( $context['filter_counts']['ages'][ $age_value ] ) : 0,
				);
			}

			$filters[] = self::render_search_multiselect_filter(
				array(
					'label'       => __( 'Age rating', 'fanfiction-manager' ),
					'placeholder' => __( 'Select Age Rating', 'fanfiction-manager' ),
					'input_name'  => 'age[]',
					'options'     => $age_options,
					'selected'    => (array) ( $context['params']['age'] ?? array() ),
					'disabled'    => empty( $context['available_age_options'] ),
					'tooltip'     => $context['unavailable_filter_tip'],
				)
			);
		}

		if ( ! empty( $context['show_fandom_filter'] ) ) {
			$filters[] = self::render_fandom_search_filter( $context );
		}

		if ( ! empty( $context['show_warnings_filters'] ) ) {
			$warning_options = array();
			foreach ( (array) $context['available_warnings'] as $warning ) {
				$warning_slug = $warning['slug'];
				$warning_options[] = array(
					'value' => $warning_slug,
					'label' => $warning['name'],
					'count' => isset( $context['filter_counts']['warnings'][ $warning_slug ] ) ? absint( $context['filter_counts']['warnings'][ $warning_slug ] ) : 0,
				);
			}

			$filters[] = self::render_search_multiselect_filter(
				array(
					'label'       => __( 'Exclude Warnings', 'fanfiction-manager' ),
					'placeholder' => __( 'Select Warnings to Exclude', 'fanfiction-manager' ),
					'input_name'  => 'warnings_exclude[]',
					'options'     => $warning_options,
					'selected'    => (array) ( $context['params']['exclude_warnings'] ?? array() ),
					'disabled'    => empty( $context['available_warnings'] ),
					'tooltip'     => $context['unavailable_filter_tip'],
				)
			);

			$filters[] = self::render_search_multiselect_filter(
				array(
					'label'       => __( 'Include Warnings', 'fanfiction-manager' ),
					'placeholder' => __( 'Select Warnings to Include', 'fanfiction-manager' ),
					'input_name'  => 'warnings_include[]',
					'options'     => $warning_options,
					'selected'    => (array) ( $context['params']['include_warnings'] ?? array() ),
					'disabled'    => empty( $context['available_warnings'] ),
					'tooltip'     => $context['unavailable_filter_tip'],
				)
			);
		}

		foreach ( (array) $context['custom_taxonomies'] as $custom_taxonomy ) {
			$filters[] = self::render_custom_taxonomy_search_filter( $context, $custom_taxonomy );
		}

		$filters[] = self::render_rating_search_filter( $rating_min );

		return array_filter( $filters );
	}

	/**
	 * Render the match-all advanced filter block.
	 *
	 * @since 2.0.0
	 * @param array $context Search context.
	 * @return string
	 */
	private static function render_match_all_search_filter( $context ) {
		ob_start();
		?>
		<div class="fanfic-advanced-search-match-all">
			<div class="fanfic-smart-toggle-wrapper<?php echo ( $context['params']['match_all_filters'] ?? '0' ) === '1' ? ' is-active' : ''; ?>">
				<div class="fanfic-comment-toggle-row">
					<label for="fanfic-match-all-filters" class="fanfic-comment-toggle-label">
						<?php esc_html_e( 'Match all filters', 'fanfiction-manager' ); ?>
					</label>
					<label class="fanfic-switch fanfic-comment-switch">
						<input type="checkbox" id="fanfic-match-all-filters" name="match_all_filters" value="1" <?php checked( ( $context['params']['match_all_filters'] ?? '0' ) === '1' ); ?>>
						<span class="fanfic-slider round" aria-hidden="true">
							<span class="fanfic-slider-state fanfic-slider-state-off"><?php esc_html_e( 'Off', 'fanfiction-manager' ); ?></span>
							<span class="fanfic-slider-state fanfic-slider-state-on"><?php esc_html_e( 'On', 'fanfiction-manager' ); ?></span>
						</span>
					</label>
				</div>
				<div class="fanfic-match-all-description" aria-live="polite">
					<p class="description fanfic-match-all-description-summary">
						<?php esc_html_e( 'If ON, results must match all selected filters; if OFF, results can match any of them.', 'fanfiction-manager' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
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

		$single_select_taxonomies = array( 'status', 'age', 'language' );
		$custom_taxonomy_configs  = array();
		foreach ( (array) $context['custom_taxonomies'] as $custom_taxonomy ) {
			$custom_taxonomy_slug = isset( $custom_taxonomy['slug'] ) ? sanitize_key( $custom_taxonomy['slug'] ) : '';
			if ( '' === $custom_taxonomy_slug ) {
				continue;
			}

			$custom_taxonomy_configs[] = array(
				'slug'           => $custom_taxonomy_slug,
				'label'          => isset( $custom_taxonomy['name'] ) ? (string) $custom_taxonomy['name'] : $custom_taxonomy_slug,
				'selection_type' => isset( $custom_taxonomy['selection_type'] ) ? (string) $custom_taxonomy['selection_type'] : 'multi',
			);

			if ( 'single' === ( $custom_taxonomy['selection_type'] ?? '' ) ) {
				$single_select_taxonomies[] = $custom_taxonomy_slug;
			}
		}

		wp_localize_script(
			'fanfic-search-bar-frontend',
			'fanficSearchBar',
			array(
				'singleSelectTaxonomies' => array_values( array_unique( $single_select_taxonomies ) ),
				'customTaxonomies'       => $custom_taxonomy_configs,
				'hasActiveQuery'         => ! empty( $context['has_filters'] ),
				'i18n'                   => array(
					'searchTermLabel'   => __( 'Search', 'fanfiction-manager' ),
					'removeSearchTerm'   => __( 'Remove search term', 'fanfiction-manager' ),
					'activeFilters'      => __( 'Current filters', 'fanfiction-manager' ),
				),
			)
		);

		$open_wrapper = ! self::$stories_wrapper_open;
		if ( $open_wrapper ) {
			self::$stories_wrapper_open = true;
			self::$stories_wrapper_opened_by_search = true;
		}

		ob_start();

		if ( $open_wrapper ) :
			?>
			<div class="fanfic-archive fanfic-stories-page" data-fanfic-stories>
			<?php
		endif;
		?>
		<form class="fanfic-stories-form" method="get" action="<?php echo esc_url( $context['base_url'] ); ?>" data-fanfic-stories-form>
			<div class="fanfic-search-toolbar">
				<div class="fanfic-search-toolbar__main">
					<div class="fanfic-search-input-wrapper">
						<label for="fanfic-search-input" class="screen-reader-text"><?php esc_html_e( 'Search stories', 'fanfiction-manager' ); ?></label>
						<div class="fanfic-search-input-group">
							<input
								type="text"
								id="fanfic-search-input"
								name="q"
								value="<?php echo esc_attr( $context['params']['search'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Search titles, tags, authors...', 'fanfiction-manager' ); ?>"
							/>
							<button type="submit" class="fanfic-button fanfic-search-submit">
								<?php esc_html_e( 'Search', 'fanfiction-manager' ); ?>
							</button>
						</div>
					</div>

					<div class="fanfic-search-toolbar__controls">
						<?php $direction_value = $context['params']['direction'] ?? 'desc'; ?>
						<div class="fanfic-sort-filter-wrapper">
							<label for="fanfic-sort-filter" class="screen-reader-text"><?php esc_html_e( 'Sort by', 'fanfiction-manager' ); ?></label>
							<select id="fanfic-sort-filter" name="sort">
								<option value=""><?php esc_html_e( 'Popularity', 'fanfiction-manager' ); ?></option>
								<option value="alphabetical" <?php selected( 'alphabetical', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'A-Z', 'fanfiction-manager' ); ?></option>
								<option value="updated" <?php selected( 'updated', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Updated', 'fanfiction-manager' ); ?></option>
								<option value="likes" <?php selected( 'likes', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Likes', 'fanfiction-manager' ); ?></option>
								<option value="comments" <?php selected( 'comments', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?></option>
								<option value="views" <?php selected( 'views', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Views', 'fanfiction-manager' ); ?></option>
								<option value="rating" <?php selected( 'rating', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Rating', 'fanfiction-manager' ); ?></option>
								<option value="created" <?php selected( 'created', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Publication date', 'fanfiction-manager' ); ?></option>
								<option value="followers" <?php selected( 'followers', $context['params']['sort'] ?? '' ); ?>><?php esc_html_e( 'Followers', 'fanfiction-manager' ); ?></option>
							</select>
						</div>

						<div class="fanfic-direction-filter-wrapper">
							<label for="fanfic-direction-filter" class="screen-reader-text"><?php esc_html_e( 'Order direction', 'fanfiction-manager' ); ?></label>
							<select id="fanfic-direction-filter" name="direction">
								<option value="desc" <?php selected( 'desc', $direction_value ); ?>><?php esc_html_e( 'Descending', 'fanfiction-manager' ); ?></option>
								<option value="asc" <?php selected( 'asc', $direction_value ); ?>><?php esc_html_e( 'Ascending', 'fanfiction-manager' ); ?></option>
							</select>
						</div>

						<button
							type="button"
							class="fanfic-button fanfic-search-advanced-toggle fanfic-advanced-search-toggle"
							aria-expanded="false"
							aria-controls="fanfic-advanced-search-panel"
							aria-label="<?php esc_attr_e( 'Advanced search', 'fanfiction-manager' ); ?>"
						>
							<span class="dashicons dashicons-plus" aria-hidden="true"></span>
						</button>
					</div>
				</div>

			</div>

			<div class="fanfic-advanced-search-accordion fanfic-advanced-search-filters" id="fanfic-advanced-search-panel" style="display: none;">
				<div id="fanfic_advanced_search">
					<?php foreach ( self::get_advanced_search_filters( $context ) as $advanced_filter_markup ) : ?>
						<?php echo $advanced_filter_markup; ?>
					<?php endforeach; ?>
				</div>
				<div class="fanfic-advanced-search-footer">
					<?php echo self::render_match_all_search_filter( $context ); ?>
				</div>
			</div>
			<!-- Current Filters Section with Live Pills -->
			<div class="fanfic-current-filters-section<?php echo ! empty( $context['has_real_filters'] ) ? ' is-visible' : ''; ?>">
				<div class="fanfic-current-filters-header">
					<div class="fanfic-current-filters-container" data-fanfic-active-filters></div>
					<div class="fanfic-current-filters-actions">
						<button type="submit" class="fanfic-button fanfic-search-submit fanfic-current-filters-search-button">
							<?php esc_html_e( 'Search', 'fanfiction-manager' ); ?>
						</button>
						<button type="button" class="fanfic-button fanfic-clear-search-button" id="fanfic-clear-filters-button">
							<?php esc_html_e( 'Clear all', 'fanfiction-manager' ); ?>
						</button>
					</div>
				</div>
			</div>
		</form>

		<?php
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
		?>

		<?php if ( $is_stories_all ) : ?>
			<?php echo self::render_stories_all_terms(); ?>
		<?php else : ?>
			<?php
			// Display normal story results.
			$paged = absint( get_query_var( 'paged' ) );
			if ( $paged < 1 ) {
				$paged = absint( get_query_var( 'page' ) );
			}
			$paged = max( 1, $paged );
			$per_page = (int) get_option( 'posts_per_page', 10 );

			$stories_query = null;
			$found_posts   = -1;
			if ( function_exists( 'fanfic_build_stories_query_args' ) ) {
				$query_result  = fanfic_build_stories_query_args( $context['params'], $paged, $per_page );
				$query_args    = is_array( $query_result ) && isset( $query_result['args'] ) ? $query_result['args'] : $query_result;
				$found_posts   = is_array( $query_result ) && isset( $query_result['found_posts'] ) ? (int) $query_result['found_posts'] : -1;
				$stories_query = new WP_Query( $query_args );

				// Preload story-card search-index metadata to avoid per-card queries.
				if ( $stories_query instanceof WP_Query && $stories_query->have_posts() && function_exists( 'fanfic_preload_story_card_index_data' ) ) {
					$preload_ids = wp_list_pluck( $stories_query->posts, 'ID' );
					fanfic_preload_story_card_index_data( $preload_ids );
				}
			}
			$result_count = $found_posts >= 0
				? $found_posts
				: ( $stories_query instanceof WP_Query ? (int) $stories_query->found_posts : 0 );
			?>

			<div class="fanfic-archive-content fanfic-stories-page" data-fanfic-load-more-region data-fanfic-load-more-key="stories-archive">
				<div class="fanfic-stories-results-summary" aria-live="polite">
					<?php
					printf(
						/* translators: %d: number of matching stories */
						esc_html( _n( '%d story found', '%d stories found', $result_count, 'fanfiction-manager' ) ),
						absint( $result_count )
					);
					?>
				</div>
				<div class="fanfic-stories-results" data-fanfic-stories-results>
					<?php if ( $stories_query instanceof WP_Query && $stories_query->have_posts() ) : ?>
						<div class="fanfic-story-grid" data-fanfic-load-more-list>
							<?php
							while ( $stories_query->have_posts() ) :
								$stories_query->the_post();
								echo fanfic_get_story_card_html( get_the_ID() );
							endwhile;
							?>
						</div>

						<nav class="fanfic-pagination fanfic-stories-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Stories pagination', 'fanfiction-manager' ); ?>" data-fanfic-stories-pagination data-fanfic-load-more-pagination>
							<?php
							$pagination_base = function_exists( 'fanfic_build_stories_url' )
								? fanfic_build_stories_url( $context['base_url'], $context['params'], array( 'paged' => null ) )
								: $context['base_url'];
							echo paginate_links( array(
								'base'      => add_query_arg( 'paged', '%#%', $pagination_base ),
								'format'    => '',
								'current'   => max( 1, $paged ),
								'total'     => $found_posts >= 0
								? max( 1, (int) ceil( $found_posts / $per_page ) )
								: max( 1, (int) $stories_query->max_num_pages ),
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

			<div class="fanfic-stories-loading" data-fanfic-stories-loading aria-hidden="true">
				<?php esc_html_e( 'Loading...', 'fanfiction-manager' ); ?>
			</div>

			<?php
			if ( $stories_query instanceof WP_Query ) {
				wp_reset_postdata();
			}

			self::$stories_archive_rendered = true;

			if ( $opened_here || self::$stories_wrapper_opened_by_search ) {
				self::$stories_wrapper_open = false;
				self::$stories_wrapper_opened_by_search = false;
				?>
				</div>
				<?php
			}

			return ob_get_clean();
		endif;
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
		<div class="fanfic-archive-content fanfic-stories-page">
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
						<p><?php esc_html_e( 'No terms found with visible stories.', 'fanfiction-manager' ); ?></p>
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
		// Use the current request URL (without query string) as the form action.
		// This ensures the form submits back to the same page the user is on,
		// whether that's the homepage (stories as front page), /search/, or /stories/.
		global $wp;
		$base_url = home_url( $wp->request ? trailingslashit( $wp->request ) : '/' );

		$params = function_exists( 'fanfic_get_stories_params' ) ? fanfic_get_stories_params() : array();

		$has_filters = ! empty( $params['search'] )
			|| ! empty( $params['genres'] )
			|| ! empty( $params['statuses'] )
			|| ! empty( $params['fandoms'] )
			|| ! empty( $params['languages'] )
			|| ! empty( $params['custom'] )
			|| ! empty( $params['exclude_warnings'] )
			|| ! empty( $params['include_warnings'] )
			|| ! empty( $params['age'] )
			|| ! empty( $params['sort'] )
			|| ! empty( $params['direction'] )
			|| ! empty( $params['rating_min'] );

		$non_pill_params = array( 'search', 'sort', 'direction' );
		$has_real_filters = false;
		foreach ( $params as $param_key => $param_value ) {
			if ( in_array( $param_key, $non_pill_params, true ) ) {
				continue;
			}
			if ( ! empty( $param_value ) ) {
				$has_real_filters = true;
				break;
			}
		}

		$genres = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );

		$statuses = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );

		$warnings_enabled = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_warnings', true ) : true;
		$warnings = class_exists( 'Fanfic_Warnings' ) ? Fanfic_Warnings::get_available_warnings() : array();

		$languages = array();
		$languages_enabled = class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled();
		if ( $languages_enabled ) {
			$languages = Fanfic_Languages::get_active_languages();
		}

		$fandoms_enabled = class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled();

		$custom_taxonomies = array();
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$all_custom = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $all_custom as $taxonomy ) {
				// Only include searchable taxonomies in search filters.
				if ( isset( $taxonomy['is_searchable'] ) && empty( $taxonomy['is_searchable'] ) ) {
					continue;
				}
				$custom_taxonomies[] = array(
					'id'             => $taxonomy['id'],
					'slug'           => $taxonomy['slug'],
					'name'           => $taxonomy['name'],
					'selection_type' => $taxonomy['selection_type'],
					'terms'          => Fanfic_Custom_Taxonomies::get_active_terms( $taxonomy['id'] ),
				);
			}
		}

		$raw_filter_counts = function_exists( 'fanfic_get_search_filter_option_counts' ) ? fanfic_get_search_filter_option_counts() : array();
		$age_options = function_exists( 'fanfic_get_available_age_filters' ) ? fanfic_get_available_age_filters() : array();
		if ( empty( $age_options ) && isset( $raw_filter_counts['ages'] ) && is_array( $raw_filter_counts['ages'] ) ) {
			$age_options = array_keys( $raw_filter_counts['ages'] );
		}
		$filter_counts = array(
			'genres'    => array(),
			'statuses'  => array(),
			'ages'      => array(),
			'languages' => array(),
			'warnings'  => array(),
			'fandoms'   => array(),
			'custom'    => array(),
		);
		foreach ( (array) $age_options as $age_value ) {
			$filter_counts['ages'][ $age_value ] = 0;
		}

		$genre_name_counts = isset( $raw_filter_counts['genres_by_name'] ) && is_array( $raw_filter_counts['genres_by_name'] )
			? $raw_filter_counts['genres_by_name']
			: array();
		if ( ! empty( $genres ) && ! is_wp_error( $genres ) ) {
			foreach ( $genres as $genre ) {
				$name_key = function_exists( 'fanfic_normalize_filter_label_key' ) ? fanfic_normalize_filter_label_key( $genre->name ) : strtolower( trim( (string) $genre->name ) );
				$filter_counts['genres'][ $genre->slug ] = isset( $genre_name_counts[ $name_key ] ) ? absint( $genre_name_counts[ $name_key ] ) : 0;
			}
		}

		$status_name_counts = isset( $raw_filter_counts['statuses_by_name'] ) && is_array( $raw_filter_counts['statuses_by_name'] )
			? $raw_filter_counts['statuses_by_name']
			: array();
		if ( ! empty( $statuses ) && ! is_wp_error( $statuses ) ) {
			foreach ( $statuses as $status ) {
				$name_key = function_exists( 'fanfic_normalize_filter_label_key' ) ? fanfic_normalize_filter_label_key( $status->name ) : strtolower( trim( (string) $status->name ) );
				$filter_counts['statuses'][ $status->slug ] = isset( $status_name_counts[ $name_key ] ) ? absint( $status_name_counts[ $name_key ] ) : 0;
			}
		}

		if ( isset( $raw_filter_counts['ages'] ) && is_array( $raw_filter_counts['ages'] ) ) {
			foreach ( $raw_filter_counts['ages'] as $age_key => $age_count ) {
				$filter_counts['ages'][ $age_key ] = absint( $age_count );
			}
		}

		$language_counts = isset( $raw_filter_counts['languages'] ) && is_array( $raw_filter_counts['languages'] )
			? $raw_filter_counts['languages']
			: array();
		foreach ( (array) $languages as $language ) {
			$slug = $language['slug'];
			$filter_counts['languages'][ $slug ] = isset( $language_counts[ $slug ] ) ? absint( $language_counts[ $slug ] ) : 0;
		}

		$warning_counts = isset( $raw_filter_counts['warnings'] ) && is_array( $raw_filter_counts['warnings'] )
			? $raw_filter_counts['warnings']
			: array();
		foreach ( (array) $warnings as $warning ) {
			$slug = $warning['slug'];
			$filter_counts['warnings'][ $slug ] = isset( $warning_counts[ $slug ] ) ? absint( $warning_counts[ $slug ] ) : 0;
		}

		$filter_counts['fandoms'] = isset( $raw_filter_counts['fandoms'] ) && is_array( $raw_filter_counts['fandoms'] )
			? $raw_filter_counts['fandoms']
			: array();
		$filter_counts['custom'] = isset( $raw_filter_counts['custom'] ) && is_array( $raw_filter_counts['custom'] )
			? $raw_filter_counts['custom']
			: array();

		$available_genres = ( ! empty( $genres ) && ! is_wp_error( $genres ) )
			? self::filter_term_objects_by_counts( $genres, $filter_counts['genres'] )
			: array();
		$available_statuses = ( ! empty( $statuses ) && ! is_wp_error( $statuses ) )
			? self::filter_term_objects_by_counts( $statuses, $filter_counts['statuses'] )
			: array();
		$available_age_options = self::filter_scalar_values_by_counts( $age_options, $filter_counts['ages'] );
		$available_languages = self::filter_array_items_by_counts( $languages, $filter_counts['languages'] );
		$available_warnings = self::filter_array_items_by_counts( $warnings, $filter_counts['warnings'] );
		$available_fandoms = array();
		$has_fandom_options = ! empty(
			array_filter(
				(array) $filter_counts['fandoms'],
				static function( $count ) {
					return absint( $count ) > 0;
				}
			)
		);

		if ( $fandoms_enabled ) {
			foreach ( (array) Fanfic_Fandoms::get_all_active() as $fandom ) {
				$fandom_slug = isset( $fandom['slug'] ) ? (string) $fandom['slug'] : '';
				$fandom_count = isset( $filter_counts['fandoms'][ $fandom_slug ] ) ? absint( $filter_counts['fandoms'][ $fandom_slug ] ) : 0;

				if ( '' === $fandom_slug || $fandom_count <= 0 ) {
					continue;
				}

				$available_fandoms[] = array(
					'id'    => isset( $fandom['id'] ) ? absint( $fandom['id'] ) : 0,
					'label' => isset( $fandom['name'] ) ? (string) $fandom['name'] : '',
					'count' => $fandom_count,
				);
			}
		}

		$preloaded_fandom_options = count( $available_fandoms ) <= 20 ? $available_fandoms : array();

		foreach ( $custom_taxonomies as $custom_taxonomy_index => $custom_taxonomy ) {
			$custom_counts = isset( $filter_counts['custom'][ $custom_taxonomy['slug'] ] ) && is_array( $filter_counts['custom'][ $custom_taxonomy['slug'] ] )
				? $filter_counts['custom'][ $custom_taxonomy['slug'] ]
				: array();

			$custom_taxonomies[ $custom_taxonomy_index ]['available_terms'] = self::filter_array_items_by_counts( $custom_taxonomy['terms'], $custom_counts );
			$custom_taxonomies[ $custom_taxonomy_index ]['has_available_terms'] = ! empty( $custom_taxonomies[ $custom_taxonomy_index ]['available_terms'] );
		}

		return array(
			'base_url'               => $base_url,
			'params'                 => $params,
			'has_filters'            => $has_filters,
			'has_real_filters'       => $has_real_filters,
			'genres'                 => $genres,
			'statuses'               => $statuses,
			'warnings'               => $warnings,
			'languages'              => $languages,
			'custom_taxonomies'      => $custom_taxonomies,
			'age_options'            => $age_options,
			'filter_counts'          => $filter_counts,
			'available_genres'       => $available_genres,
			'available_statuses'     => $available_statuses,
			'available_age_options'  => $available_age_options,
			'available_languages'    => $available_languages,
			'available_warnings'     => $available_warnings,
			'available_fandoms'      => $available_fandoms,
			'preloaded_fandoms'      => $preloaded_fandom_options,
			'has_fandom_options'     => $has_fandom_options,
			'show_language_filter'   => $languages_enabled,
			'show_age_filter'        => $warnings_enabled,
			'show_warnings_filters'  => $warnings_enabled,
			'show_fandom_filter'     => $fandoms_enabled,
			'unavailable_filter_tip' => self::get_unavailable_filter_tooltip(),
		);
	}
}
