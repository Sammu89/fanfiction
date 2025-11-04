<?php
/**
 * List Shortcodes Class
 *
 * Handles story list and grid display shortcodes with filtering and sorting.
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
 * Class Fanfic_Shortcodes_Lists
 *
 * Story list and grid display shortcodes.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Lists {

	/**
	 * Register list shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'story-list', array( __CLASS__, 'story_list' ) );
		add_shortcode( 'story-grid', array( __CLASS__, 'story_grid' ) );
	}

	/**
	 * Story list shortcode
	 *
	 * [story-list genre="comedy,drama" status="ongoing" author="5" orderby="date" order="DESC" posts_per_page="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story list HTML.
	 */
	public static function story_list( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'genre'          => '',
				'status'         => '',
				'author'         => '',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => 10,
			),
			'story-list'
		);

		// Get stories
		$stories = self::get_filtered_stories( $atts );

		if ( ! $stories->have_posts() ) {
			return '<div class="fanfic-story-list fanfic-no-stories"><p>' . esc_html__( 'No stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="fanfic-story-list" role="region" aria-label="' . esc_attr__( 'Story list', 'fanfiction-manager' ) . '">';

		while ( $stories->have_posts() ) {
			$stories->the_post();
			$story_id = get_the_ID();

			$output .= self::render_story_list_item( $story_id );
		}

		$output .= '</div>';

		// Add pagination
		$output .= self::render_pagination( $stories );

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Story grid shortcode
	 *
	 * [story-grid genre="comedy,drama" status="ongoing" author="5" orderby="date" order="DESC" posts_per_page="10"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Story grid HTML.
	 */
	public static function story_grid( $atts ) {
		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'genre'          => '',
				'status'         => '',
				'author'         => '',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => 10,
			),
			'story-grid'
		);

		// Get stories
		$stories = self::get_filtered_stories( $atts );

		if ( ! $stories->have_posts() ) {
			return '<div class="fanfic-story-grid fanfic-no-stories"><p>' . esc_html__( 'No stories found.', 'fanfiction-manager' ) . '</p></div>';
		}

		// Build output
		$output = '<div class="fanfic-story-grid" role="region" aria-label="' . esc_attr__( 'Story grid', 'fanfiction-manager' ) . '">';

		while ( $stories->have_posts() ) {
			$stories->the_post();
			$story_id = get_the_ID();

			$output .= self::render_story_grid_item( $story_id );
		}

		$output .= '</div>';

		// Add pagination
		$output .= self::render_pagination( $stories );

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Get filtered stories based on shortcode attributes
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return WP_Query Stories query object.
	 */
	private static function get_filtered_stories( $atts ) {
		// Get current page
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		// Build base query args
		$query_args = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $atts['posts_per_page'] ),
			'paged'          => $paged,
		);

		// Build tax_query for genre and status filters
		$tax_query = array();

		// Genre filter
		if ( ! empty( $atts['genre'] ) ) {
			$genres = array_map( 'trim', explode( ',', $atts['genre'] ) );
			$tax_query[] = array(
				'taxonomy' => 'fanfiction_genre',
				'field'    => 'slug',
				'terms'    => $genres,
				'operator' => 'AND',
			);
		}

		// Status filter
		if ( ! empty( $atts['status'] ) ) {
			$statuses = array_map( 'trim', explode( ',', $atts['status'] ) );
			$tax_query[] = array(
				'taxonomy' => 'fanfiction_status',
				'field'    => 'slug',
				'terms'    => $statuses,
				'operator' => 'IN',
			);
		}

		// Add custom taxonomy filters
		foreach ( $atts as $key => $value ) {
			if ( strpos( $key, 'custom-taxo-' ) === 0 && ! empty( $value ) ) {
				$taxonomy_slug = str_replace( 'custom-taxo-', '', $key );
				$terms = array_map( 'trim', explode( ',', $value ) );
				$tax_query[] = array(
					'taxonomy' => 'fanfic_' . $taxonomy_slug,
					'field'    => 'slug',
					'terms'    => $terms,
					'operator' => 'AND',
				);
			}
		}

		// Add tax_query to query args if we have filters
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query );
		}

		// Author filter
		if ( ! empty( $atts['author'] ) ) {
			$query_args['author'] = absint( $atts['author'] );
		}

		// Handle orderby
		switch ( $atts['orderby'] ) {
			case 'title':
				$query_args['orderby'] = 'title';
				$query_args['order'] = sanitize_text_field( $atts['order'] );
				break;

			case 'modified':
			case 'updated':
				$query_args['orderby'] = 'modified';
				$query_args['order'] = sanitize_text_field( $atts['order'] );
				break;

			case 'rating':
				// Sort by average rating stored in post meta
				$query_args['meta_key'] = 'fanfic_average_rating';
				$query_args['orderby'] = 'meta_value_num';
				$query_args['order'] = sanitize_text_field( $atts['order'] );
				break;

			case 'views':
				// Sort by views stored in post meta
				$query_args['meta_key'] = 'fanfic_total_views';
				$query_args['orderby'] = 'meta_value_num';
				$query_args['order'] = sanitize_text_field( $atts['order'] );
				break;

			case 'bookmarks':
				// Sort by bookmark count stored in post meta
				$query_args['meta_key'] = 'fanfic_bookmark_count';
				$query_args['orderby'] = 'meta_value_num';
				$query_args['order'] = sanitize_text_field( $atts['order'] );
				break;

			case 'date':
			default:
				$query_args['orderby'] = 'date';
				$query_args['order'] = sanitize_text_field( $atts['order'] );
				break;
		}

		// Generate cache key using Fanfic_Cache format
		$cache_key = Fanfic_Cache::get_key( 'list', 'filtered', 0, md5( serialize( $query_args ) ) );

		// Try to get cached results using Fanfic_Cache
		$stories = Fanfic_Cache::get(
			$cache_key,
			function() use ( $query_args ) {
				// Query not cached, run it
				return new WP_Query( $query_args );
			},
			HOUR_IN_SECONDS
		);

		return $stories;
	}

	/**
	 * Render a single story list item
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return string Story list item HTML.
	 */
	private static function render_story_list_item( $story_id ) {
		$output = '<article class="fanfic-story-list-item" id="story-' . esc_attr( $story_id ) . '">';

		// Story title and author
		$output .= '<header class="story-header">';
		$output .= '<h3 class="story-title"><a href="' . esc_url( get_permalink( $story_id ) ) . '">' . esc_html( get_the_title( $story_id ) ) . '</a></h3>';

		$author_id = get_post_field( 'post_author', $story_id );
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = get_author_posts_url( $author_id );

		$output .= '<p class="story-author">' . esc_html__( 'by', 'fanfiction-manager' ) . ' <a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a></p>';
		$output .= '</header>';

		// Story excerpt
		$excerpt = get_post_field( 'post_excerpt', $story_id );
		if ( ! empty( $excerpt ) ) {
			$output .= '<div class="story-excerpt">' . wp_kses_post( wpautop( $excerpt ) ) . '</div>';
		}

		// Story metadata
		$output .= '<div class="story-meta">';

		// Genres
		$genres = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( $genres && ! is_wp_error( $genres ) ) {
			$genre_links = array();
			foreach ( $genres as $genre ) {
				$genre_links[] = '<a href="' . esc_url( get_term_link( $genre ) ) . '">' . esc_html( $genre->name ) . '</a>';
			}
			$output .= '<span class="story-genres"><strong>' . esc_html__( 'Genres:', 'fanfiction-manager' ) . '</strong> ' . implode( ', ', $genre_links ) . '</span>';
		}

		// Status
		$statuses = get_the_terms( $story_id, 'fanfiction_status' );
		if ( $statuses && ! is_wp_error( $statuses ) ) {
			$status = reset( $statuses );
			$status_slug = sanitize_html_class( $status->slug );
			$output .= '<span class="story-status story-status-' . esc_attr( $status_slug ) . '"><strong>' . esc_html__( 'Status:', 'fanfiction-manager' ) . '</strong> ' . esc_html( $status->name ) . '</span>';
		}

		$output .= '</div>';

		// Story stats
		$output .= '<div class="story-stats">';

		// Chapters (use cached function)
		$chapter_count = self::get_story_chapter_count( $story_id );
		$output .= '<span class="stat-chapters"><strong>' . esc_html__( 'Chapters:', 'fanfiction-manager' ) . '</strong> ' . Fanfic_Shortcodes::format_number( $chapter_count ) . '</span>';

		// Words
		$total_words = self::get_story_word_count( $story_id );
		$output .= '<span class="stat-words"><strong>' . esc_html__( 'Words:', 'fanfiction-manager' ) . '</strong> ' . Fanfic_Shortcodes::format_number( $total_words ) . '</span>';

		// Views
		$total_views = self::get_story_views( $story_id );
		$output .= '<span class="stat-views"><strong>' . esc_html__( 'Views:', 'fanfiction-manager' ) . '</strong> ' . Fanfic_Shortcodes::format_number( $total_views ) . '</span>';

		// Rating
		$average_rating = self::get_story_rating( $story_id );
		if ( $average_rating > 0 ) {
			$output .= '<span class="stat-rating"><strong>' . esc_html__( 'Rating:', 'fanfiction-manager' ) . '</strong> ' . number_format( $average_rating, 1 ) . '/5</span>';
		}

		$output .= '</div>';

		$output .= '</article>';

		return $output;
	}

	/**
	 * Render a single story grid item
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return string Story grid item HTML.
	 */
	private static function render_story_grid_item( $story_id ) {
		$output = '<article class="fanfic-story-card" id="story-' . esc_attr( $story_id ) . '">';

		// Featured image with lazy loading
		if ( has_post_thumbnail( $story_id ) ) {
			$output .= '<div class="story-card-image">';
			$output .= '<a href="' . esc_url( get_permalink( $story_id ) ) . '">';
			$output .= get_the_post_thumbnail( $story_id, 'medium', array( 'class' => 'story-thumbnail', 'loading' => 'lazy' ) );
			$output .= '</a>';
			$output .= '</div>';
		}

		$output .= '<div class="story-card-content">';

		// Story title and author
		$output .= '<header class="story-card-header">';
		$output .= '<h3 class="story-title"><a href="' . esc_url( get_permalink( $story_id ) ) . '">' . esc_html( get_the_title( $story_id ) ) . '</a></h3>';

		$author_id = get_post_field( 'post_author', $story_id );
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url = get_author_posts_url( $author_id );

		$output .= '<p class="story-author">' . esc_html__( 'by', 'fanfiction-manager' ) . ' <a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a></p>';
		$output .= '</header>';

		// Story excerpt
		$excerpt = get_post_field( 'post_excerpt', $story_id );
		if ( ! empty( $excerpt ) ) {
			// Truncate excerpt for card view
			$excerpt_text = wp_strip_all_tags( $excerpt );
			if ( strlen( $excerpt_text ) > 150 ) {
				$excerpt_text = substr( $excerpt_text, 0, 150 ) . '...';
			}
			$output .= '<div class="story-excerpt">' . esc_html( $excerpt_text ) . '</div>';
		}

		// Story metadata
		$output .= '<div class="story-card-meta">';

		// Status badge
		$statuses = get_the_terms( $story_id, 'fanfiction_status' );
		if ( $statuses && ! is_wp_error( $statuses ) ) {
			$status = reset( $statuses );
			$status_slug = sanitize_html_class( $status->slug );
			$output .= '<span class="story-status story-status-' . esc_attr( $status_slug ) . '">' . esc_html( $status->name ) . '</span>';
		}

		// Genres
		$genres = get_the_terms( $story_id, 'fanfiction_genre' );
		if ( $genres && ! is_wp_error( $genres ) ) {
			$genre_names = array();
			foreach ( $genres as $genre ) {
				$genre_names[] = esc_html( $genre->name );
			}
			$output .= '<span class="story-genres-list">' . implode( ', ', $genre_names ) . '</span>';
		}

		$output .= '</div>';

		// Story stats
		$output .= '<div class="story-card-stats">';

		// Chapters (use cached function)
		$chapter_count = self::get_story_chapter_count( $story_id );
		$output .= '<span class="stat-item stat-chapters" title="' . esc_attr__( 'Chapters', 'fanfiction-manager' ) . '">' . Fanfic_Shortcodes::format_number( $chapter_count ) . ' ' . esc_html__( 'ch', 'fanfiction-manager' ) . '</span>';

		// Words
		$total_words = self::get_story_word_count( $story_id );
		$output .= '<span class="stat-item stat-words" title="' . esc_attr__( 'Words', 'fanfiction-manager' ) . '">' . Fanfic_Shortcodes::format_number( $total_words ) . ' ' . esc_html__( 'words', 'fanfiction-manager' ) . '</span>';

		// Views
		$total_views = self::get_story_views( $story_id );
		$output .= '<span class="stat-item stat-views" title="' . esc_attr__( 'Views', 'fanfiction-manager' ) . '">' . Fanfic_Shortcodes::format_number( $total_views ) . ' ' . esc_html__( 'views', 'fanfiction-manager' ) . '</span>';

		// Rating
		$average_rating = self::get_story_rating( $story_id );
		if ( $average_rating > 0 ) {
			$output .= '<span class="stat-item stat-rating" title="' . esc_attr__( 'Rating', 'fanfiction-manager' ) . '">' . number_format( $average_rating, 1 ) . '/5</span>';
		}

		$output .= '</div>';

		$output .= '</div>'; // .story-card-content

		$output .= '</article>';

		return $output;
	}

	/**
	 * Get story chapter count (cached)
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Chapter count.
	 */
	private static function get_story_chapter_count( $story_id ) {
		// Use Fanfic_Cache for transient caching
		$cache_key = Fanfic_Cache::get_key( 'story', 'chapter_count', $story_id );

		return Fanfic_Cache::get(
			$cache_key,
			function() use ( $story_id ) {
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				return count( $chapters );
			},
			6 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Render pagination links
	 *
	 * @since 1.0.0
	 * @param WP_Query $query Query object.
	 * @return string Pagination HTML.
	 */
	private static function render_pagination( $query ) {
		if ( $query->max_num_pages <= 1 ) {
			return '';
		}

		$output = '<nav class="fanfic-pagination" role="navigation" aria-label="' . esc_attr__( 'Story pagination', 'fanfiction-manager' ) . '">';

		$pagination_args = array(
			'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
			'format'    => '?paged=%#%',
			'current'   => max( 1, get_query_var( 'paged' ) ),
			'total'     => $query->max_num_pages,
			'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'fanfiction-manager' ),
			'next_text' => esc_html__( 'Next', 'fanfiction-manager' ) . ' &raquo;',
		);

		$output .= paginate_links( $pagination_args );

		$output .= '</nav>';

		return $output;
	}

	/**
	 * Get story word count
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Total word count.
	 */
	private static function get_story_word_count( $story_id ) {
		// Use Fanfic_Cache for transient caching
		$cache_key = Fanfic_Cache::get_key( 'story', 'word_count', $story_id );

		return Fanfic_Cache::get(
			$cache_key,
			function() use ( $story_id ) {
				// Check if cached in post meta
				$cached_count = get_post_meta( $story_id, 'fanfic_word_count', true );

				if ( $cached_count !== '' ) {
					return absint( $cached_count );
				}

				// Calculate word count
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_words = 0;

				foreach ( $chapters as $chapter_id ) {
					$content = get_post_field( 'post_content', $chapter_id );
					$content = wp_strip_all_tags( $content );
					$word_count = str_word_count( $content );
					$total_words += $word_count;
				}

				// Cache the result in post meta too
				update_post_meta( $story_id, 'fanfic_word_count', $total_words );

				return $total_words;
			},
			6 * HOUR_IN_SECONDS
		);
	}

	/**
	 * Get story total views
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Total views.
	 */
	private static function get_story_views( $story_id ) {
		// Use Fanfic_Cache for transient caching (short TTL for frequently changing data)
		$cache_key = Fanfic_Cache::get_key( 'story', 'view_count', $story_id );

		return Fanfic_Cache::get(
			$cache_key,
			function() use ( $story_id ) {
				// Check if cached in post meta
				$cached_views = get_post_meta( $story_id, 'fanfic_total_views', true );

				if ( $cached_views !== '' ) {
					return absint( $cached_views );
				}

				// Calculate total views
				$chapters = get_posts( array(
					'post_type'      => 'fanfiction_chapter',
					'post_parent'    => $story_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );

				$total_views = 0;

				foreach ( $chapters as $chapter_id ) {
					$views = get_post_meta( $chapter_id, 'fanfic_views', true );
					$total_views += absint( $views );
				}

				// Cache the result in post meta too
				update_post_meta( $story_id, 'fanfic_total_views', $total_views );

				return $total_views;
			},
			Fanfic_Cache::SHORT
		);
	}

	/**
	 * Get story average rating
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return float Average rating (0-5).
	 */
	private static function get_story_rating( $story_id ) {
		global $wpdb;

		// Check if cached in post meta
		$cached_rating = get_post_meta( $story_id, 'fanfic_average_rating', true );

		if ( $cached_rating !== '' ) {
			return floatval( $cached_rating );
		}

		// Get all chapter IDs for this story
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $chapters ) ) {
			return 0;
		}

		// Calculate average rating from all chapters
		// This assumes ratings are stored in wp_fanfic_ratings table
		$table_name = $wpdb->prefix . 'fanfic_ratings';

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return 0;
		}

		$chapter_ids = array_map( 'absint', $chapters );
		$placeholders = implode( ',', array_fill( 0, count( $chapter_ids ), '%d' ) );

		$average = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$table_name} WHERE chapter_id IN ({$placeholders})",
				$chapter_ids
			)
		);

		$average_rating = $average ? floatval( $average ) : 0;

		// Cache the result
		update_post_meta( $story_id, 'fanfic_average_rating', $average_rating );

		return $average_rating;
	}
}
