<?php /**
 * Preload search-index metadata needed by story cards.
 *
 * This keeps story card rendering lightweight by avoiding per-card translation
 * and language table lookups on archive/search pages.
 *
 * @since 1.5.1
 * @param int[] $story_ids Story IDs to preload.
 * @return void
 */
function fanfic_preload_story_card_index_data( $story_ids ) {
	global $wpdb;

	$story_ids = array_values( array_unique( array_map( 'absint', (array) $story_ids ) ) );
	$story_ids = array_filter( $story_ids );
	if ( empty( $story_ids ) ) {
		return;
	}

	if ( ! isset( $GLOBALS['fanfic_story_card_index_cache'] ) || ! is_array( $GLOBALS['fanfic_story_card_index_cache'] ) ) {
		$GLOBALS['fanfic_story_card_index_cache'] = array();
	}

	$cache = $GLOBALS['fanfic_story_card_index_cache'];
	$missing_ids = array();

	foreach ( $story_ids as $story_id ) {
		if ( ! array_key_exists( $story_id, $cache ) ) {
			$missing_ids[] = $story_id;
			$cache[ $story_id ] = array(
				'story_title'           => '',
				'story_slug'            => '',
				'story_summary'         => '',
				'author_id'             => 0,
				'language_slug'         => '',
				'translation_group_id'  => 0,
				'translation_count'     => 0,
				'published_date'        => '',
				'updated_date'          => '',
				'chapter_count'         => 0,
				'word_count'            => 0,
				'view_count'            => 0,
				'likes_total'           => 0,
				'rating_avg_total'      => 0,
				'fandom_slugs'          => '',
				'warning_slugs'         => '',
				'age_rating'            => '',
				'genre_names'           => '',
				'status_name'           => '',
				'coauthor_ids'          => '',
				'coauthor_names'        => '',
				'coauthor_logins'       => '',
				'visible_tags'          => '',
				'featured_image_id'     => 0,
				'author_display_name'   => '',
				'author_login'          => '',
				'language_name'         => '',
				'language_native_name'  => '',
				'warning_names'         => '',
				'fandom_names'          => '',
			);
		}
	}

	if ( empty( $missing_ids ) ) {
		$GLOBALS['fanfic_story_card_index_cache'] = $cache;
		return;
	}

	$table = $wpdb->prefix . 'fanfic_story_search_index';
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table_exists !== $table ) {
		$GLOBALS['fanfic_story_card_index_cache'] = $cache;
		return;
	}

	$available_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( empty( $available_columns ) || ! in_array( 'story_id', (array) $available_columns, true ) ) {
		$GLOBALS['fanfic_story_card_index_cache'] = $cache;
		return;
	}

	$base_select_columns = array(
		'story_id',
		'story_title',
		'story_slug',
		'story_summary',
		'author_id',
		'language_slug',
		'translation_group_id',
		'translation_count',
		'published_date',
		'updated_date',
		'chapter_count',
		'word_count',
		'view_count',
		'likes_total',
		'rating_avg_total',
		'fandom_slugs',
		'warning_slugs',
		'age_rating',
		'genre_names',
		'status_name',
		'coauthor_ids',
		'coauthor_names',
		'coauthor_logins',
		'visible_tags',
		'featured_image_id',
		'author_display_name',
		'author_login',
		'language_name',
		'language_native_name',
		'warning_names',
		'fandom_names',
	);
	$select_columns = array_values( array_intersect( $base_select_columns, (array) $available_columns ) );
	if ( ! in_array( 'story_id', $select_columns, true ) ) {
		$GLOBALS['fanfic_story_card_index_cache'] = $cache;
		return;
	}
	$select_sql = implode( ', ', $select_columns );

	$placeholders = implode( ',', array_fill( 0, count( $missing_ids ), '%d' ) );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$select_sql}
			FROM {$table}
			WHERE story_id IN ({$placeholders})",
			$missing_ids
		),
		ARRAY_A
	);

	$translation_group_ids = array();
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$sid = absint( $row['story_id'] ?? 0 );
			if ( ! $sid ) {
				continue;
			}
			$cache[ $sid ] = array(
				'story_title'           => sanitize_text_field( (string) ( $row['story_title'] ?? '' ) ),
				'story_slug'            => sanitize_title( (string) ( $row['story_slug'] ?? '' ) ),
				'story_summary'         => sanitize_textarea_field( (string) ( $row['story_summary'] ?? '' ) ),
				'author_id'             => absint( $row['author_id'] ?? 0 ),
				'language_slug'        => sanitize_title( (string) ( $row['language_slug'] ?? '' ) ),
				'translation_group_id' => absint( $row['translation_group_id'] ?? 0 ),
				'translation_count'    => absint( $row['translation_count'] ?? 0 ),
				'published_date'       => sanitize_text_field( (string) ( $row['published_date'] ?? '' ) ),
				'updated_date'         => sanitize_text_field( (string) ( $row['updated_date'] ?? '' ) ),
				'chapter_count'        => absint( $row['chapter_count'] ?? 0 ),
				'word_count'           => absint( $row['word_count'] ?? 0 ),
				'view_count'           => absint( $row['view_count'] ?? 0 ),
				'likes_total'          => absint( $row['likes_total'] ?? 0 ),
				'rating_avg_total'     => isset( $row['rating_avg_total'] ) && is_numeric( $row['rating_avg_total'] ) ? (float) $row['rating_avg_total'] : 0,
				'fandom_slugs'         => sanitize_text_field( (string) ( $row['fandom_slugs'] ?? '' ) ),
				'warning_slugs'        => sanitize_text_field( (string) ( $row['warning_slugs'] ?? '' ) ),
				'age_rating'           => sanitize_title( (string) ( $row['age_rating'] ?? '' ) ),
				'genre_names'          => sanitize_text_field( (string) ( $row['genre_names'] ?? '' ) ),
				'status_name'          => sanitize_text_field( (string) ( $row['status_name'] ?? '' ) ),
				'coauthor_ids'          => sanitize_text_field( (string) ( $row['coauthor_ids'] ?? '' ) ),
				'coauthor_names'        => sanitize_text_field( (string) ( $row['coauthor_names'] ?? '' ) ),
				'coauthor_logins'       => sanitize_text_field( (string) ( $row['coauthor_logins'] ?? '' ) ),
				'visible_tags'          => sanitize_text_field( (string) ( $row['visible_tags'] ?? '' ) ),
				'featured_image_id'     => absint( $row['featured_image_id'] ?? 0 ),
				'author_display_name'   => sanitize_text_field( (string) ( $row['author_display_name'] ?? '' ) ),
				'author_login'          => sanitize_user( (string) ( $row['author_login'] ?? '' ), true ),
				'language_name'         => sanitize_text_field( (string) ( $row['language_name'] ?? '' ) ),
				'language_native_name'  => sanitize_text_field( (string) ( $row['language_native_name'] ?? '' ) ),
				'warning_names'         => sanitize_text_field( (string) ( $row['warning_names'] ?? '' ) ),
				'fandom_names'          => sanitize_text_field( (string) ( $row['fandom_names'] ?? '' ) ),
			);
			if ( $cache[ $sid ]['translation_group_id'] > 0 ) {
				$translation_group_ids[] = $cache[ $sid ]['translation_group_id'];
			}
		}
	}

	if ( ! isset( $GLOBALS['fanfic_story_card_translation_cache'] ) || ! is_array( $GLOBALS['fanfic_story_card_translation_cache'] ) ) {
		$GLOBALS['fanfic_story_card_translation_cache'] = array();
	}
	$translation_cache = $GLOBALS['fanfic_story_card_translation_cache'];

	$translation_group_ids = array_values( array_unique( array_filter( array_map( 'absint', $translation_group_ids ) ) ) );
	$missing_group_ids = array();
	foreach ( $translation_group_ids as $group_id ) {
		if ( ! array_key_exists( $group_id, $translation_cache ) ) {
			$missing_group_ids[] = $group_id;
			$translation_cache[ $group_id ] = array();
		}
	}

	if ( ! empty( $missing_group_ids ) ) {
		if ( ! in_array( 'translation_group_id', (array) $available_columns, true ) || ! in_array( 'story_status', (array) $available_columns, true ) ) {
			$GLOBALS['fanfic_story_card_index_cache'] = $cache;
			$GLOBALS['fanfic_story_card_translation_cache'] = $translation_cache;
			return;
		}

		$translation_select_columns = array( 'story_id', 'translation_group_id' );
		if ( in_array( 'language_slug', (array) $available_columns, true ) ) {
			$translation_select_columns[] = 'language_slug';
		}
		if ( in_array( 'story_slug', (array) $available_columns, true ) ) {
			$translation_select_columns[] = 'story_slug';
		}
		if ( in_array( 'view_count', (array) $available_columns, true ) ) {
			$translation_select_columns[] = 'view_count';
		}
		if ( in_array( 'language_name', (array) $available_columns, true ) ) {
			$translation_select_columns[] = 'language_name';
		}
		$translation_select_sql = implode( ', ', $translation_select_columns );

		$group_placeholders = implode( ',', array_fill( 0, count( $missing_group_ids ), '%d' ) );
		$translation_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$translation_select_sql}
				FROM {$table}
				WHERE story_status = 'publish'
				  AND translation_group_id IN ({$group_placeholders})
				ORDER BY translation_group_id ASC, view_count DESC, story_id ASC",
				$missing_group_ids
			),
			ARRAY_A
		);

		if ( is_array( $translation_rows ) ) {
			foreach ( $translation_rows as $translation_row ) {
				$group_id = absint( $translation_row['translation_group_id'] ?? 0 );
				$sid = absint( $translation_row['story_id'] ?? 0 );
				if ( ! $group_id || ! $sid ) {
					continue;
				}

				$translation_cache[ $group_id ][] = array(
					'story_id'      => $sid,
					'language_slug' => sanitize_title( (string) ( $translation_row['language_slug'] ?? '' ) ),
					'story_slug'    => sanitize_title( (string) ( $translation_row['story_slug'] ?? '' ) ),
					'view_count'    => absint( $translation_row['view_count'] ?? 0 ),
					'language_name' => sanitize_text_field( (string) ( $translation_row['language_name'] ?? '' ) ),
				);
			}
		}
	}

	$GLOBALS['fanfic_story_card_index_cache'] = $cache;
	$GLOBALS['fanfic_story_card_translation_cache'] = $translation_cache;
}

/**
 * Get preloaded story-card index metadata for a single story.
 *
 * @since 1.5.1
 * @param int $story_id Story ID.
 * @return array{
 *   story_title:string,
 *   story_slug:string,
 *   story_summary:string,
 *   author_id:int,
 *   language_slug:string,
 *   translation_group_id:int,
 *   translation_count:int,
 *   published_date:string,
 *   updated_date:string,
 *   chapter_count:int,
 *   word_count:int,
 *   view_count:int,
 *   likes_total:int,
 *   rating_avg_total:float,
 *   fandom_slugs:string,
 *   warning_slugs:string,
 *   age_rating:string,
 *   genre_names:string,
 *   status_name:string,
 *   coauthor_ids:string,
 *   coauthor_names:string,
 *   coauthor_logins:string,
 *   visible_tags:string,
 *   featured_image_id:int,
 *   author_display_name:string,
 *   author_login:string
 * }
 */
function fanfic_get_story_card_index_data( $story_id ) {
	$story_id = absint( $story_id );
	$defaults = array(
		'story_title'           => '',
		'story_slug'            => '',
		'story_summary'         => '',
		'author_id'             => 0,
		'language_slug'        => '',
		'translation_group_id' => 0,
		'translation_count'    => 0,
		'published_date'       => '',
		'updated_date'         => '',
		'chapter_count'        => 0,
		'word_count'           => 0,
		'view_count'           => 0,
		'likes_total'          => 0,
		'rating_avg_total'     => 0,
		'fandom_slugs'         => '',
		'warning_slugs'        => '',
		'age_rating'           => '',
		'genre_names'          => '',
		'status_name'          => '',
		'coauthor_ids'          => '',
		'coauthor_names'        => '',
		'coauthor_logins'       => '',
		'visible_tags'          => '',
		'featured_image_id'     => 0,
		'author_display_name'   => '',
		'author_login'          => '',
		'language_name'         => '',
		'language_native_name'  => '',
		'warning_names'         => '',
		'fandom_names'          => '',
	);

	if ( ! $story_id ) {
		return $defaults;
	}

	fanfic_preload_story_card_index_data( array( $story_id ) );

	if ( isset( $GLOBALS['fanfic_story_card_index_cache'][ $story_id ] ) && is_array( $GLOBALS['fanfic_story_card_index_cache'][ $story_id ] ) ) {
		return wp_parse_args( $GLOBALS['fanfic_story_card_index_cache'][ $story_id ], $defaults );
	}

	return $defaults;
}

/**
 * Parse CSV values from search-index fields.
 *
 * @since 1.5.3
 * @param string $csv CSV string.
 * @return string[]
 */
function fanfic_story_card_parse_csv_values( $csv ) {
	if ( ! is_string( $csv ) || '' === trim( $csv ) ) {
		return array();
	}

	$values = array_map( 'trim', explode( ',', $csv ) );
	$values = array_values(
		array_filter(
			$values,
			static function ( $value ) {
				return '' !== $value;
			}
		)
	);

	return array_values( array_unique( $values ) );
}

/**
 * Format a slug-like value for human display.
 *
 * @since 1.5.3
 * @param string $value Raw slug or label value.
 * @return string
 */
function fanfic_story_card_format_slug_label( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}

	$label = str_replace( array( '-', '_' ), ' ', $value );
	$label = preg_replace( '/\s+/', ' ', $label );
	$label = trim( (string) $label );

	return ucwords( $label );
}

/**
 * Format language slug labels for display.
 *
 * @since 1.5.3
 * @param string $language_slug Language slug (e.g. en, pt-br).
 * @return string
 */
function fanfic_story_card_format_language_label( $language_slug ) {
	$language_slug = sanitize_title( (string) $language_slug );
	if ( '' === $language_slug ) {
		return '';
	}

	static $language_label_cache = array();
	if ( isset( $language_label_cache[ $language_slug ] ) ) {
		return $language_label_cache[ $language_slug ];
	}

	if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
		$language = Fanfic_Languages::get_by_slug( $language_slug );
		$name = is_array( $language ) ? trim( (string) ( $language['name'] ?? '' ) ) : '';
		if ( '' !== $name ) {
			$language_label_cache[ $language_slug ] = $name;
			return $name;
		}
	}

	$known = array(
		'en'     => __( 'English', 'fanfiction-manager' ),
		'pt'     => __( 'Portuguese', 'fanfiction-manager' ),
		'pt-br'  => __( 'Portuguese (Brazil)', 'fanfiction-manager' ),
		'es-es'  => __( 'Spanish (Spain)', 'fanfiction-manager' ),
		'es-419' => __( 'Spanish (Latin America)', 'fanfiction-manager' ),
	);

	if ( isset( $known[ $language_slug ] ) ) {
		$language_label_cache[ $language_slug ] = $known[ $language_slug ];
		return $known[ $language_slug ];
	}

	$language_label_cache[ $language_slug ] = fanfic_story_card_format_slug_label( $language_slug );
	return $language_label_cache[ $language_slug ];
}

/**
 * Format a date stored in search-index datetime columns.
 *
 * @since 1.5.3
 * @param string $mysql_datetime MySQL DATETIME value.
 * @return string
 */
function fanfic_story_card_format_index_date( $mysql_datetime ) {
	$mysql_datetime = trim( sanitize_text_field( (string) $mysql_datetime ) );
	if ( '' === $mysql_datetime ) {
		return '';
	}

	$timestamp = mysql2date( 'U', $mysql_datetime, false );
	if ( ! $timestamp ) {
		return '';
	}

	$date_format = get_option( 'date_format' );
	if ( '' === $date_format ) {
		$date_format = 'F j, Y';
	}

	return wp_date( $date_format, $timestamp );
}

/**
 * Build a story URL from an indexed slug without post lookup.
 *
 * @since 1.5.3
 * @param string $story_slug Story slug from search index.
 * @param int    $story_id Optional story ID fallback.
 * @return string
 */
function fanfic_story_card_build_story_url( $story_slug, $story_id = 0 ) {
	$story_slug = sanitize_title( (string) $story_slug );
	$story_id = absint( $story_id );

	if ( '' !== $story_slug && class_exists( 'Fanfic_URL_Manager' ) ) {
		$url_manager = Fanfic_URL_Manager::get_instance();
		return $url_manager->build_url(
			array(
				$url_manager->get_base_slug(),
				$url_manager->get_story_path(),
				$story_slug,
			)
		);
	}

	if ( $story_id && function_exists( 'fanfic_get_story_url' ) ) {
		return fanfic_get_story_url( $story_id );
	}

	return '';
}

/**
 * Get translation siblings for story cards from preloaded search-index cache.
 *
 * @since 1.5.3
 * @param int $story_id Story ID.
 * @param int $translation_group_id Translation group ID.
 * @return array<int,array{story_id:int,language_slug:string,story_slug:string,view_count:int,language_name:string}>
 */
function fanfic_get_story_card_translation_siblings( $story_id, $translation_group_id ) {
	$story_id = absint( $story_id );
	$translation_group_id = absint( $translation_group_id );
	if ( ! $story_id || ! $translation_group_id ) {
		return array();
	}

	fanfic_preload_story_card_index_data( array( $story_id ) );

	$group_rows = $GLOBALS['fanfic_story_card_translation_cache'][ $translation_group_id ] ?? array();
	if ( empty( $group_rows ) || ! is_array( $group_rows ) ) {
		return array();
	}

	$siblings = array();
	foreach ( $group_rows as $row ) {
		$sibling_id = absint( $row['story_id'] ?? 0 );
		if ( ! $sibling_id || $sibling_id === $story_id ) {
			continue;
		}

		$siblings[] = array(
			'story_id'      => $sibling_id,
			'language_slug' => sanitize_title( (string) ( $row['language_slug'] ?? '' ) ),
			'story_slug'    => sanitize_title( (string) ( $row['story_slug'] ?? '' ) ),
			'view_count'    => absint( $row['view_count'] ?? 0 ),
			'language_name' => sanitize_text_field( (string) ( $row['language_name'] ?? '' ) ),
		);
	}

	return $siblings;
}

/**
 * Build a clean stories URL for a single clicked card filter.
 *
 * Does not preserve existing search/filter state.
 *
 * @since 1.5.3
 * @param string $facet Filter facet (genre|warning|fandom).
 * @param string $value_slug Filter value slug.
 * @return string
 */
function fanfic_story_card_build_clean_filter_url( $facet, $value_slug ) {
	$facet = sanitize_key( (string) $facet );
	$value_slug = sanitize_title( (string) $value_slug );
	$base_url = function_exists( 'fanfic_get_story_archive_url' ) ? fanfic_get_story_archive_url() : home_url( '/' );

	if ( '' === $value_slug ) {
		return $base_url;
	}

	$params = array(
		'search'            => '',
		'genres'            => array(),
		'statuses'          => array(),
		'fandoms'           => array(),
		'languages'         => array(),
		'exclude_warnings'  => array(),
		'include_warnings'  => array(),
		'age'               => array(),
		'sort'              => '',
		'match_all_filters' => '0',
		'custom'            => array(),
	);

	if ( 'genre' === $facet ) {
		$params['genres'] = array( $value_slug );
	} elseif ( 'warning' === $facet ) {
		$params['include_warnings'] = array( $value_slug );
	} elseif ( 'fandom' === $facet ) {
		$params['fandoms'] = array( $value_slug );
	} else {
		return $base_url;
	}

	if ( function_exists( 'fanfic_build_stories_url' ) ) {
		return fanfic_build_stories_url( $base_url, $params, array( 'paged' => null ) );
	}

	return $base_url;
}

/**
 * Render a story card (archive style) for a given story.
 *
 * @since 1.2.0
 * @param int $story_id Story ID.
 * @return string HTML output.
 */
function fanfic_get_story_card_html( $story_id ) {
	$story = get_post( $story_id );
	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return '';
	}

	// Story-card metadata comes from search-index preload for table-driven cards.
	$card_index_data = fanfic_get_story_card_index_data( $story_id );
	$story_title = trim( (string) $card_index_data['story_title'] );
	if ( '' === $story_title ) {
		$story_title = get_the_title( $story_id );
	}
	$story_url = fanfic_story_card_build_story_url( $card_index_data['story_slug'], $story_id );
	if ( '' === $story_url ) {
		$story_url = get_permalink( $story_id );
	}

	$author_id = absint( $card_index_data['author_id'] );
	if ( ! $author_id ) {
		$author_id = (int) $story->post_author;
	}
	$author_name = trim( (string) $card_index_data['author_display_name'] );
	if ( '' === $author_name ) {
		$author_name = get_the_author_meta( 'display_name', $author_id );
	}
	$published_raw = trim( (string) $card_index_data['published_date'] );
	$updated_raw = trim( (string) $card_index_data['updated_date'] );
	$published_timestamp = $published_raw ? (int) mysql2date( 'U', $published_raw, false ) : 0;
	$updated_timestamp = $updated_raw ? (int) mysql2date( 'U', $updated_raw, false ) : 0;
	if ( ! $published_timestamp && ! empty( $story->post_date ) ) {
		$published_timestamp = (int) mysql2date( 'U', $story->post_date, false );
	}

	$story_date = fanfic_story_card_format_index_date( $published_raw );
	if ( '' === $story_date ) {
		$story_date = get_the_date( '', $story );
	}
	$updated_date = fanfic_story_card_format_index_date( $updated_raw );
	$display_date_type = '';
	$display_date_value = '';
	if ( '' !== $story_date && '' !== $updated_date ) {
		if ( $updated_timestamp > $published_timestamp ) {
			$display_date_type = 'updated';
			$display_date_value = $updated_date;
		} else {
			$display_date_type = 'posted';
			$display_date_value = $story_date;
		}
	} elseif ( '' !== $story_date ) {
		$display_date_type = 'posted';
		$display_date_value = $story_date;
	} elseif ( '' !== $updated_date ) {
		$display_date_type = 'updated';
		$display_date_value = $updated_date;
	}
	$story_excerpt = trim( (string) $card_index_data['story_summary'] );
	if ( '' === $story_excerpt ) {
		$story_excerpt = has_excerpt( $story_id ) ? get_the_excerpt( $story ) : wp_trim_words( $story->post_content, 30 );
	}

	$status = trim( (string) $card_index_data['status_name'] );
	$word_count = absint( $card_index_data['word_count'] );
	$chapters = absint( $card_index_data['chapter_count'] );
	$story_views = absint( $card_index_data['view_count'] );
	$story_likes = absint( $card_index_data['likes_total'] );
	$story_rating_avg = isset( $card_index_data['rating_avg_total'] ) && is_numeric( $card_index_data['rating_avg_total'] ) ? (float) $card_index_data['rating_avg_total'] : 0;
	$language_slug = sanitize_title( (string) $card_index_data['language_slug'] );
	$language_label = trim( (string) $card_index_data['language_name'] );
	if ( '' === $language_label ) {
		$language_label = fanfic_story_card_format_language_label( $language_slug );
	}
	$translation_group_id = absint( $card_index_data['translation_group_id'] );
	$translation_count = absint( $card_index_data['translation_count'] );
	$genre_items = array();
	foreach ( fanfic_story_card_parse_csv_values( $card_index_data['genre_names'] ) as $genre_name ) {
		$genre_label = trim( sanitize_text_field( (string) $genre_name ) );
		$genre_slug = sanitize_title( (string) $genre_name );
		if ( '' === $genre_label || '' === $genre_slug ) {
			continue;
		}
		$genre_items[] = array(
			'slug'  => $genre_slug,
			'label' => $genre_label,
			'url'   => fanfic_story_card_build_clean_filter_url( 'genre', $genre_slug ),
		);
	}

	$warning_items      = array();
	$warning_name_parts = fanfic_story_card_parse_csv_values( $card_index_data['warning_names'] );
	foreach ( fanfic_story_card_parse_csv_values( $card_index_data['warning_slugs'] ) as $widx => $warning_slug ) {
		$warning_slug = sanitize_title( (string) $warning_slug );
		if ( '' === $warning_slug ) {
			continue;
		}
		$warning_label = ( isset( $warning_name_parts[ $widx ] ) && '' !== trim( $warning_name_parts[ $widx ] ) )
			? trim( $warning_name_parts[ $widx ] )
			: fanfic_story_card_format_slug_label( $warning_slug );
		$warning_items[] = array(
			'slug'  => $warning_slug,
			'label' => $warning_label,
			'url'   => fanfic_story_card_build_clean_filter_url( 'warning', $warning_slug ),
		);
	}

	$fandom_items      = array();
	$fandom_name_parts = fanfic_story_card_parse_csv_values( $card_index_data['fandom_names'] );
	foreach ( fanfic_story_card_parse_csv_values( $card_index_data['fandom_slugs'] ) as $fidx => $fandom_slug ) {
		$fandom_slug = sanitize_title( (string) $fandom_slug );
		if ( '' === $fandom_slug ) {
			continue;
		}
		$fandom_label = ( isset( $fandom_name_parts[ $fidx ] ) && '' !== trim( $fandom_name_parts[ $fidx ] ) )
			? trim( $fandom_name_parts[ $fidx ] )
			: fanfic_story_card_format_slug_label( $fandom_slug );
		$fandom_items[] = array(
			'slug'  => $fandom_slug,
			'label' => $fandom_label,
			'url'   => fanfic_story_card_build_clean_filter_url( 'fandom', $fandom_slug ),
		);
	}
	$visible_tag_items = array();
	$tags_enabled = ! class_exists( 'Fanfic_Settings' ) || Fanfic_Settings::get_setting( 'enable_tags', true );
	if ( $tags_enabled ) {
		$visible_tags = fanfic_story_card_parse_csv_values( (string) $card_index_data['visible_tags'] );
		if ( empty( $visible_tags ) && function_exists( 'fanfic_get_visible_tags' ) ) {
			$visible_tags = array_map( 'strval', (array) fanfic_get_visible_tags( $story_id ) );
		}

		foreach ( $visible_tags as $visible_tag ) {
			$visible_tag = trim( sanitize_text_field( (string) $visible_tag ) );
			if ( '' !== $visible_tag ) {
				$visible_tag_items[] = $visible_tag;
			}
		}
		$visible_tag_items = array_values( array_unique( $visible_tag_items ) );
	}

	$age_badge = '';
	$highest_age = sanitize_title( (string) $card_index_data['age_rating'] );
	if ( '' !== $highest_age ) {
		$highest_age_label = fanfic_get_age_display_label( $highest_age, true );
		if ( '' !== $highest_age_label ) {
			$highest_age_class = fanfic_get_age_badge_class( $highest_age );
			$age_badge = sprintf(
				'<span class="fanfic-age-badge %1$s" aria-label="%2$s">%3$s</span>',
				esc_attr( $highest_age_class ),
				esc_attr( sprintf( __( 'Age rating: %s', 'fanfiction-manager' ), $highest_age_label ) ),
				esc_html( $highest_age_label )
			);
		}
	}

	$coauthor_ids_str = (string) $card_index_data['coauthor_ids'];
	$coauthor_names = (string) $card_index_data['coauthor_names'];
	$coauthor_logins = (string) $card_index_data['coauthor_logins'];
	$coauthor_links_html = '';
	$members_base_url = trim( (string) fanfic_get_page_url( 'members' ) );
	$members_base_url = '' !== $members_base_url ? trailingslashit( $members_base_url ) : '';

	if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() && '' !== $coauthor_ids_str && '' !== $coauthor_names ) {
		$coauthor_ids        = array_values( array_filter( array_map( 'absint', explode( ',', $coauthor_ids_str ) ) ) );
		$coauthor_name_parts = array_values( array_filter( array_map( 'trim', explode( ',', $coauthor_names ) ) ) );
		$coauthor_login_parts = array_values( array_map( 'trim', explode( ',', $coauthor_logins ) ) );
		$coauthor_links      = array();
		$max = min( count( $coauthor_ids ), count( $coauthor_name_parts ) );

		for ( $i = 0; $i < $max; $i++ ) {
			$coauthor_login = isset( $coauthor_login_parts[ $i ] ) ? sanitize_user( $coauthor_login_parts[ $i ], true ) : '';
			$coauthor_url   = ( '' !== $coauthor_login && '' !== $members_base_url )
				? $members_base_url . rawurlencode( $coauthor_login ) . '/'
				: fanfic_get_user_profile_url( $coauthor_ids[ $i ] );
			$coauthor_links[] = '<a href="' . esc_url( $coauthor_url ) . '">' . esc_html( $coauthor_name_parts[ $i ] ) . '</a>';
		}

		if ( ! empty( $coauthor_links ) ) {
			$coauthor_links_html = ', ' . implode( ', ', $coauthor_links );
		}
	}

	$translation_siblings = fanfic_get_story_card_translation_siblings( $story_id, $translation_group_id );
	$translation_links = array();
	$seen_translation_languages = array();
	foreach ( $translation_siblings as $sibling ) {
		$sibling_language_slug = sanitize_title( (string) ( $sibling['language_slug'] ?? '' ) );
		$sibling_story_id = absint( $sibling['story_id'] ?? 0 );
		$sibling_url = fanfic_story_card_build_story_url( (string) ( $sibling['story_slug'] ?? '' ), $sibling_story_id );
		if ( '' === $sibling_language_slug || '' === $sibling_url ) {
			continue;
		}
		if ( isset( $seen_translation_languages[ $sibling_language_slug ] ) ) {
			continue;
		}
		$seen_translation_languages[ $sibling_language_slug ] = true;
		$sibling_lang_name   = trim( (string) ( $sibling['language_name'] ?? '' ) );
		$translation_links[] = array(
			'label' => '' !== $sibling_lang_name ? $sibling_lang_name : fanfic_story_card_format_language_label( $sibling_language_slug ),
			'url'   => $sibling_url,
		);
	}
	$inline_translation_links = array_slice( $translation_links, 0, 3 );
	$extra_translation_links = array_slice( $translation_links, 3 );
	$taxonomy_has_values = ! empty( $genre_items ) || ! empty( $warning_items ) || ! empty( $fandom_items );

	$author_login = sanitize_user( (string) $card_index_data['author_login'], true );
	$author_profile_url = ( '' !== $author_login && '' !== $members_base_url )
		? $members_base_url . rawurlencode( $author_login ) . '/'
		: fanfic_get_user_profile_url( $author_id );

	$featured_image_size = 'fanfic_story_card_220';
	$featured_image_id = absint( $card_index_data['featured_image_id'] );
	$featured_image_url = $featured_image_id ? (string) wp_get_attachment_image_url( $featured_image_id, $featured_image_size ) : '';
	$featured_image_alt = $featured_image_id ? (string) get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true ) : '';
	if ( '' === $featured_image_url && $featured_image_id ) {
		$featured_image_url = (string) wp_get_attachment_image_url( $featured_image_id, 'medium' );
	}
	if ( '' === $featured_image_url ) {
		$thumb_id = get_post_thumbnail_id( $story_id );
		if ( $thumb_id ) {
			$featured_image_url = (string) wp_get_attachment_image_url( $thumb_id, $featured_image_size );
			if ( '' === $featured_image_url ) {
				$featured_image_url = (string) wp_get_attachment_image_url( $thumb_id, 'medium' );
			}
			$featured_image_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
		}
	}
	if ( '' === $featured_image_url ) {
		$featured_image_meta_url = trim( (string) get_post_meta( $story_id, '_fanfic_featured_image', true ) );
		if ( '' !== $featured_image_meta_url ) {
			$featured_meta_attachment_id = attachment_url_to_postid( $featured_image_meta_url );
			if ( $featured_meta_attachment_id ) {
				$featured_image_url = (string) wp_get_attachment_image_url( $featured_meta_attachment_id, $featured_image_size );
				if ( '' === $featured_image_url ) {
					$featured_image_url = (string) wp_get_attachment_image_url( $featured_meta_attachment_id, 'medium' );
				}
				if ( '' === $featured_image_alt ) {
					$featured_image_alt = (string) get_post_meta( $featured_meta_attachment_id, '_wp_attachment_image_alt', true );
				}
			}
			if ( '' === $featured_image_url ) {
				$featured_image_url = $featured_image_meta_url;
			}
		}
	}
	if ( '' === $featured_image_alt ) {
		$featured_image_alt = $story_title;
	}
	$has_featured_image = '' !== $featured_image_url;

	ob_start();
	?>
	<article id="story-<?php echo esc_attr( $story_id ); ?>" class="fanfic-story-card search-story-card" data-language="<?php echo esc_attr( $language_slug ); ?>" data-translation-group="<?php echo esc_attr( $translation_group_id ); ?>" data-views="<?php echo esc_attr( $story_views ); ?>">
		<header class="search-story-card-title-row">
			<h2 class="fanfic-story-card-title search-story-card-title">
				<a href="<?php echo esc_url( $story_url ); ?>"><?php echo esc_html( $story_title ); ?></a>
				<?php echo wp_kses_post( $age_badge ); ?>
				<span class="fanfic-badge fanfic-badge-following" data-badge-story-id="<?php echo esc_attr( $story_id ); ?>" style="display:none;" aria-label="<?php esc_attr_e( 'Following', 'fanfiction-manager' ); ?>" title="<?php esc_attr_e( 'Following', 'fanfiction-manager' ); ?>">
					<span class="dashicons dashicons-heart" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Following', 'fanfiction-manager' ); ?></span>
				</span>
			</h2>
			<?php if ( '' !== $status ) : ?>
				<span class="fanfic-botaozinho status status-<?php echo esc_attr( sanitize_title( $status ) ); ?>">
					<strong><?php echo esc_html( $status ); ?></strong>
				</span>
			<?php endif; ?>
		</header>

			<div class="search-story-card-details-row">
				<span>
					<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
					<a href="<?php echo esc_url( $author_profile_url ); ?>"><?php echo esc_html( $author_name ); ?></a><?php echo $coauthor_links_html; ?>
				</span>
				<span class="fanfic-byline-separator" aria-hidden="true"></span>
				<span>
					<span class="dashicons dashicons-text-page" aria-hidden="true"></span>
					<?php echo esc_html( number_format_i18n( $chapters ) . ' ' . _n( 'chapter', 'chapters', $chapters, 'fanfiction-manager' ) ); ?>
				</span>
				<?php if ( ! empty( $genre_items ) ) : ?>
					<span class="fanfic-byline-separator" aria-hidden="true"></span>
					<?php foreach ( $genre_items as $genre_item ) : ?>
						<span class="fanfic-botaozinho genre"><a href="<?php echo esc_url( $genre_item['url'] ); ?>" class="search-story-card-filter-link search-story-card-filter-link-genre"><?php echo esc_html( $genre_item['label'] ); ?></a></span>
					<?php endforeach; ?>
				<?php endif; ?>
				<?php if ( '' !== $display_date_type && '' !== $display_date_value ) : ?>
					<span class="fanfic-byline-separator" aria-hidden="true"></span>
					<span class="search-story-card-date">
						<?php
						if ( 'updated' === $display_date_type ) {
							printf(
								/* translators: %s: date */
								esc_html__( 'Updated on %s', 'fanfiction-manager' ),
								esc_html( $display_date_value )
							);
						} else {
							printf(
								/* translators: %s: date */
								esc_html__( 'Posted on %s', 'fanfiction-manager' ),
								esc_html( $display_date_value )
							);
						}
						?>
					</span>
				<?php endif; ?>
			</div>

		<div class="search-story-card-layout<?php echo $has_featured_image ? '' : ' search-story-card-layout-no-image'; ?>">
			<?php if ( $has_featured_image ) : ?>
				<div class="search-story-card-left">
					<div class="fanfic-story-card-image search-story-card-image">
						<a href="<?php echo esc_url( $story_url ); ?>">
							<img src="<?php echo esc_url( $featured_image_url ); ?>"
								alt="<?php echo esc_attr( $featured_image_alt ); ?>"
								loading="lazy" class="attachment-medium size-medium wp-post-image" />
						</a>
					</div>
				</div>
			<?php endif; ?>

				<?php if ( '' !== $story_excerpt ) : ?>
					<div class="fanfic-story-card-excerpt search-story-card-intro">
						<?php echo esc_html( $story_excerpt ); ?>
					</div>
				<?php endif; ?>

				<div class="search-story-card-bottom">
					<?php if ( ! empty( $visible_tag_items ) ) : ?>
						<div class="search-story-card-taxonomy-row story-card-tags-row">
							<span class="search-story-card-taxonomy-group">
								<strong><?php esc_html_e( 'Tags:', 'fanfiction-manager' ); ?></strong>
								<?php foreach ( $visible_tag_items as $visible_tag_item ) : ?>
									<span class="fanfic-botaozinho tags"><?php echo esc_html( $visible_tag_item ); ?></span>
								<?php endforeach; ?>
							</span>
						</div>
					<?php endif; ?>

					<div class="search-story-card-details-grid">
						<?php if ( ! empty( $warning_items ) || ! empty( $fandom_items ) ) : ?>
							<div class="search-story-card-taxonomies">
								<?php if ( ! empty( $warning_items ) ) : ?>
									<div class="search-story-card-taxonomy-row story-card-warnings-row">
										<span class="search-story-card-taxonomy-group">
											<strong><?php esc_html_e( 'Warnings:', 'fanfiction-manager' ); ?></strong>
											<?php foreach ( $warning_items as $index => $warning_item ) : ?>
												<?php if ( $index > 0 ) : ?>
													<span class="search-story-card-link-separator">, </span>
												<?php endif; ?>
												<a href="<?php echo esc_url( $warning_item['url'] ); ?>" class="search-story-card-filter-link search-story-card-filter-link-warning">
													<?php echo esc_html( $warning_item['label'] ); ?>
												</a>
											<?php endforeach; ?>
										</span>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $fandom_items ) ) : ?>
									<div class="search-story-card-taxonomy-row story-card-fandoms-row">
										<span class="search-story-card-taxonomy-group">
											<strong><?php esc_html_e( 'Fandoms:', 'fanfiction-manager' ); ?></strong>
											<?php foreach ( $fandom_items as $index => $fandom_item ) : ?>
												<?php if ( $index > 0 ) : ?>
													<span class="search-story-card-link-separator">, </span>
												<?php endif; ?>
												<a href="<?php echo esc_url( $fandom_item['url'] ); ?>" class="search-story-card-filter-link search-story-card-filter-link-fandom">
													<?php echo esc_html( $fandom_item['label'] ); ?>
												</a>
											<?php endforeach; ?>
										</span>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php $has_translations = ! empty( $translation_links ) || ( $translation_group_id && $translation_count > 0 ); ?>
						<div class="story-card-language-row"<?php echo $has_translations ? '' : ' style="grid-area:translations"'; ?>>
							<strong><?php esc_html_e( 'Language:', 'fanfiction-manager' ); ?></strong>
							<span><?php echo esc_html( '' !== $language_label ? $language_label : __( 'Unknown', 'fanfiction-manager' ) ); ?></span>
						</div>

						<div class="search-story-card-metrics" aria-label="<?php esc_attr_e( 'Story metrics', 'fanfiction-manager' ); ?>">
							<span class="search-story-card-metric">
								<span class="dashicons dashicons-edit" aria-hidden="true"></span>
								<span><?php printf( esc_html__( '%s words', 'fanfiction-manager' ), esc_html( number_format_i18n( $word_count ) ) ); ?></span>
							</span>
							<span class="search-story-card-metric">
								<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
								<span><?php echo esc_html( number_format_i18n( $story_views ) ); ?></span>
							</span>
							<span class="search-story-card-metric">
								<span class="dashicons dashicons-thumbs-up" aria-hidden="true"></span>
								<span><?php echo esc_html( number_format_i18n( $story_likes ) ); ?></span>
							</span>
							<span class="search-story-card-metric">
								<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
								<span><?php echo esc_html( number_format_i18n( $story_rating_avg, 1 ) ); ?></span>
							</span>
						</div>

						<?php if ( ! empty( $translation_links ) || ( $translation_group_id && $translation_count > 0 ) ) : ?>
							<div class="search-story-card-translations-row">
								<span class="dashicons dashicons-admin-site-alt3 search-story-card-translations-icon" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'Also available in:', 'fanfiction-manager' ); ?></strong>
								<?php if ( ! empty( $inline_translation_links ) ) : ?>
									<span class="story-card-translation-row">
										<?php foreach ( $inline_translation_links as $index => $translation_link ) : ?>
											<?php if ( $index > 0 ) : ?>
												<span class="search-story-card-link-separator">, </span>
											<?php endif; ?>
											<a href="<?php echo esc_url( $translation_link['url'] ); ?>" class="fanfic-translation-link">
												<?php echo esc_html( $translation_link['label'] ); ?>
											</a>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
								<?php if ( ! empty( $extra_translation_links ) ) : ?>
									<details class="search-story-card-translation-dropdown">
										<summary>
											<?php
											printf(
												/* translators: %d: number of extra translations */
												esc_html__( '+%d more', 'fanfiction-manager' ),
												absint( count( $extra_translation_links ) )
											);
											?>
										</summary>
										<ul>
											<?php foreach ( $extra_translation_links as $translation_link ) : ?>
												<li>
													<a href="<?php echo esc_url( $translation_link['url'] ); ?>" class="fanfic-translation-link">
														<?php echo esc_html( $translation_link['label'] ); ?>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									</details>
								<?php endif; ?>
								<?php if ( empty( $translation_links ) && $translation_count > 0 ) : ?>
									<span class="search-story-card-translation-count">
										<?php
										printf(
											esc_html( _n( '%d translation', '%d translations', $translation_count, 'fanfiction-manager' ) ),
											absint( $translation_count )
										);
										?>
									</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
		</div>
	</article>
	<?php
	return ob_get_clean();
}
