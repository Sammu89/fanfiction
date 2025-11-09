<?php
/**
 * Template: Search Page
 *
 * Displays search form and results for fanfiction stories.
 * Converted from [search-results] shortcode.
 *
 * @package FanfictionManager
 * @subpackage Templates
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get search parameters from URL
$search_term   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$genre_filter  = isset( $_GET['genre'] ) ? sanitize_text_field( wp_unslash( $_GET['genre'] ) ) : '';
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$paged         = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page      = 10;

/**
 * Perform search across stories and chapters
 *
 * Searches story titles, introductions, and chapter content.
 * Results are weighted: title matches ranked higher than content matches.
 *
 * @param string $search_term   Search term.
 * @param string $genre_filter  Genre filter slug.
 * @param string $status_filter Status filter slug.
 * @param int    $paged         Current page number.
 * @param int    $per_page      Results per page.
 * @return array Search results with 'items' and 'total' count.
 */
function fanfic_search_perform_search( $search_term, $genre_filter, $status_filter, $paged, $per_page ) {
	global $wpdb;

	$search_term_like = '%' . $wpdb->esc_like( $search_term ) . '%';
	$results = array();

	// Build tax query for filters
	$tax_query = array();

	if ( ! empty( $genre_filter ) ) {
		$tax_query[] = array(
			'taxonomy' => 'fanfiction_genre',
			'field'    => 'slug',
			'terms'    => $genre_filter,
		);
	}

	if ( ! empty( $status_filter ) ) {
		$tax_query[] = array(
			'taxonomy' => 'fanfiction_status',
			'field'    => 'slug',
			'terms'    => $status_filter,
		);
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	// 1. Search story titles (highest weight)
	$story_title_args = array(
		'post_type'      => 'fanfiction_story',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		's'              => $search_term,
		'fields'         => 'ids',
	);

	if ( ! empty( $tax_query ) ) {
		$story_title_args['tax_query'] = $tax_query;
	}

	$story_title_query = new WP_Query( $story_title_args );
	$story_title_ids = $story_title_query->posts;

	foreach ( $story_title_ids as $story_id ) {
		$results[] = array(
			'id'     => $story_id,
			'type'   => 'story',
			'weight' => 100,
			'match'  => 'title',
		);
	}

	// 2. Search story introductions/excerpts
	$story_intro_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'fanfiction_story'
			AND post_status = 'publish'
			AND post_excerpt LIKE %s
			AND ID NOT IN (" . implode( ',', array_map( 'absint', $story_title_ids ) ) . ")",
			$search_term_like
		)
	);

	// Apply taxonomy filters to intro results
	if ( ! empty( $story_intro_ids ) && ! empty( $tax_query ) ) {
		$filtered_ids = get_posts( array(
			'post_type'      => 'fanfiction_story',
			'post__in'       => $story_intro_ids,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => $tax_query,
		) );
		$story_intro_ids = $filtered_ids;
	}

	foreach ( $story_intro_ids as $story_id ) {
		$results[] = array(
			'id'     => $story_id,
			'type'   => 'story',
			'weight' => 50,
			'match'  => 'introduction',
		);
	}

	// 3. Search chapter content
	$chapter_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'fanfiction_chapter'
			AND post_status = 'publish'
			AND post_content LIKE %s",
			$search_term_like
		)
	);

	// Group chapters by parent story
	$chapter_stories = array();
	foreach ( $chapter_ids as $chapter_id ) {
		$parent_id = wp_get_post_parent_id( $chapter_id );
		if ( $parent_id ) {
			if ( ! isset( $chapter_stories[ $parent_id ] ) ) {
				$chapter_stories[ $parent_id ] = array();
			}
			$chapter_stories[ $parent_id ][] = $chapter_id;
		}
	}

	// Apply taxonomy filters to chapter results
	if ( ! empty( $chapter_stories ) && ! empty( $tax_query ) ) {
		$filtered_story_ids = get_posts( array(
			'post_type'      => 'fanfiction_story',
			'post__in'       => array_keys( $chapter_stories ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => $tax_query,
		) );
		$chapter_stories = array_intersect_key( $chapter_stories, array_flip( $filtered_story_ids ) );
	}

	foreach ( $chapter_stories as $story_id => $chapters ) {
		// Skip if already in results from title or intro match
		$already_added = false;
		foreach ( $results as $result ) {
			if ( $result['id'] === $story_id && 'story' === $result['type'] ) {
				$already_added = true;
				break;
			}
		}

		if ( ! $already_added ) {
			$results[] = array(
				'id'       => $story_id,
				'type'     => 'story',
				'weight'   => 25,
				'match'    => 'chapter',
				'chapters' => $chapters,
			);
		}
	}

	// Sort by weight (highest first)
	usort( $results, function( $a, $b ) {
		return $b['weight'] - $a['weight'];
	} );

	// Get total count
	$total_results = count( $results );

	// Paginate results
	$offset = ( $paged - 1 ) * $per_page;
	$results = array_slice( $results, $offset, $per_page );

	// Format results for display
	$formatted_results = array();
	foreach ( $results as $result ) {
		$formatted_results[] = fanfic_search_format_result( $result, $search_term );
	}

	return array(
		'items' => $formatted_results,
		'total' => $total_results,
	);
}

/**
 * Format a search result for display
 *
 * @param array  $result      Result data array.
 * @param string $search_term Search term for highlighting.
 * @return array Formatted result data.
 */
function fanfic_search_format_result( $result, $search_term ) {
	$story_id = $result['id'];
	$story = get_post( $story_id );

	if ( ! $story ) {
		return array();
	}

	// Get author info
	$author_id = $story->post_author;
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$author_url = get_author_posts_url( $author_id );

	// Get story URL
	$story_url = get_permalink( $story_id );

	// Highlight search term in title
	$title = fanfic_search_highlight_term( $story->post_title, $search_term );

	// Get excerpt based on match type
	$excerpt = '';
	$match_type_label = '';

	switch ( $result['match'] ) {
		case 'title':
			$match_type_label = esc_html__( 'Title match', 'fanfiction-manager' );
			$excerpt = get_post_field( 'post_excerpt', $story_id );
			if ( empty( $excerpt ) ) {
				$excerpt = esc_html__( 'No introduction available.', 'fanfiction-manager' );
			} else {
				$excerpt = wp_trim_words( $excerpt, 30 );
			}
			break;

		case 'introduction':
			$match_type_label = esc_html__( 'Introduction match', 'fanfiction-manager' );
			$excerpt = get_post_field( 'post_excerpt', $story_id );
			$excerpt = fanfic_search_highlight_term( $excerpt, $search_term );
			$excerpt = wp_trim_words( $excerpt, 30 );
			break;

		case 'chapter':
			$match_type_label = esc_html__( 'Chapter content match', 'fanfiction-manager' );
			if ( ! empty( $result['chapters'] ) ) {
				$first_chapter = get_post( $result['chapters'][0] );
				if ( $first_chapter ) {
					$excerpt = wp_strip_all_tags( $first_chapter->post_content );
					$excerpt = fanfic_search_get_excerpt_around_term( $excerpt, $search_term, 150 );
					$excerpt = fanfic_search_highlight_term( $excerpt, $search_term );
				}
			}
			break;
	}

	// Get meta information
	$meta = array();

	// Get genres
	$genres = get_the_terms( $story_id, 'fanfiction_genre' );
	if ( $genres && ! is_wp_error( $genres ) ) {
		$genre_names = array_map( function( $genre ) {
			return $genre->name;
		}, $genres );
		$meta[] = implode( ', ', $genre_names );
	}

	// Get status
	$statuses = get_the_terms( $story_id, 'fanfiction_status' );
	if ( $statuses && ! is_wp_error( $statuses ) ) {
		$status = reset( $statuses );
		$meta[] = $status->name;
	}

	// Get chapter count
	$chapter_count = get_posts( array(
		'post_type'      => 'fanfiction_chapter',
		'post_parent'    => $story_id,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	$meta[] = sprintf(
		_n( '%d chapter', '%d chapters', count( $chapter_count ), 'fanfiction-manager' ),
		count( $chapter_count )
	);

	return array(
		'title'      => $title,
		'url'        => $story_url,
		'author'     => $author_name,
		'author_url' => $author_url,
		'excerpt'    => $excerpt,
		'match_type' => $match_type_label,
		'meta'       => $meta,
	);
}

/**
 * Highlight search term in text
 *
 * @param string $text        Text to highlight.
 * @param string $search_term Term to highlight.
 * @return string Text with highlighted term.
 */
function fanfic_search_highlight_term( $text, $search_term ) {
	if ( empty( $text ) || empty( $search_term ) ) {
		return $text;
	}

	// Case-insensitive replacement with <mark> tag
	return preg_replace(
		'/(' . preg_quote( $search_term, '/' ) . ')/i',
		'<mark>$1</mark>',
		$text
	);
}

/**
 * Get excerpt around search term
 *
 * Extracts text around the search term for context.
 *
 * @param string $text        Full text.
 * @param string $search_term Search term.
 * @param int    $length      Excerpt length in characters.
 * @return string Excerpt with search term in context.
 */
function fanfic_search_get_excerpt_around_term( $text, $search_term, $length = 150 ) {
	if ( empty( $text ) || empty( $search_term ) ) {
		return '';
	}

	// Find position of search term (case-insensitive)
	$pos = stripos( $text, $search_term );

	if ( false === $pos ) {
		// Term not found, return beginning of text
		return wp_trim_words( $text, 30 );
	}

	// Calculate start position (try to center the term)
	$half_length = floor( $length / 2 );
	$start = max( 0, $pos - $half_length );

	// Extract excerpt
	$excerpt = substr( $text, $start, $length );

	// Add ellipsis if needed
	if ( $start > 0 ) {
		$excerpt = '...' . $excerpt;
	}

	if ( strlen( $text ) > ( $start + $length ) ) {
		$excerpt = $excerpt . '...';
	}

	return $excerpt;
}

// Perform search if search term is provided
$results = null;
if ( ! empty( $search_term ) ) {
	$results = fanfic_search_perform_search( $search_term, $genre_filter, $status_filter, $paged, $per_page );
}
?>

<div class="fanfic-search-page">
	<!-- Search Form -->
	<form class="fanfic-search-form" method="get" action="<?php echo esc_url( get_permalink() ); ?>" role="search" aria-label="<?php esc_attr_e( 'Search stories', 'fanfiction-manager' ); ?>">
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
	</form>

	<!-- Search Results -->
	<?php if ( empty( $search_term ) ) : ?>
		<div class="fanfic-search-results no-results">
			<p><?php esc_html_e( 'Please enter a search term.', 'fanfiction-manager' ); ?></p>
		</div>
	<?php else : ?>
		<div class="fanfic-search-results">
			<div class="search-results-header">
				<?php
				printf(
					'<h2 class="search-results-title">' .
					esc_html(
						_n(
							'Found %d result for "%s"',
							'Found %d results for "%s"',
							$results['total'],
							'fanfiction-manager'
						)
					) . '</h2>',
					absint( $results['total'] ),
					esc_html( $search_term )
				);
				?>
			</div>

			<?php if ( empty( $results['items'] ) ) : ?>
				<div class="no-search-results">
					<p>
						<?php
						printf(
							esc_html__( 'No results found for "%s".', 'fanfiction-manager' ),
							esc_html( $search_term )
						);
						?>
					</p>
					<p>
						<?php esc_html_e( 'Try different keywords or filters.', 'fanfiction-manager' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="search-results-list">
					<?php foreach ( $results['items'] as $item ) : ?>
						<article class="search-result-item">
							<h3 class="search-result-title">
								<a href="<?php echo esc_url( $item['url'] ); ?>">
									<?php echo wp_kses_post( $item['title'] ); ?>
								</a>
								<?php if ( ! empty( $item['match_type'] ) ) : ?>
									<span class="search-match-type">
										<?php echo esc_html( $item['match_type'] ); ?>
									</span>
								<?php endif; ?>
							</h3>

							<?php if ( ! empty( $item['author'] ) ) : ?>
								<div class="search-result-author">
									<?php
									printf(
										esc_html__( 'By %s', 'fanfiction-manager' ),
										'<a href="' . esc_url( $item['author_url'] ) . '">' . esc_html( $item['author'] ) . '</a>'
									);
									?>
								</div>
							<?php endif; ?>

							<div class="search-result-excerpt">
								<?php echo wp_kses_post( $item['excerpt'] ); ?>
							</div>

							<?php if ( ! empty( $item['meta'] ) ) : ?>
								<div class="search-result-meta">
									<?php echo wp_kses_post( implode( ' &middot; ', $item['meta'] ) ); ?>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ( $results['total'] > $per_page ) : ?>
					<div class="search-results-pagination">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => ceil( $results['total'] / $per_page ),
							'prev_text' => esc_html__( '&laquo; Previous', 'fanfiction-manager' ),
							'next_text' => esc_html__( 'Next &raquo;', 'fanfiction-manager' ),
						) );
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
