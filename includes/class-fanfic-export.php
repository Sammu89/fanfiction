<?php
/**
 * Export Functionality Class
 *
 * Handles CSV export of stories, chapters, and taxonomies.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Export
 *
 * Provides CSV export functionality for fanfiction data.
 *
 * @since 1.0.0
 */
class Fanfic_Export {

	/**
	 * Export stories to CSV
	 *
	 * Exports all or filtered stories with their metadata to a CSV file.
	 *
	 * @since 1.0.0
	 * @param array $args Optional. Query arguments to filter stories.
	 * @return void
	 */
	public static function export_stories( $args = array() ) {
		// Set default arguments
		$defaults = array(
			'post_type'      => 'fanfiction_story',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Get stories
		$stories = get_posts( $args );

		// Prepare CSV
		$filename = 'fanfiction_stories_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		// Set headers for download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create output stream
		$output = fopen( 'php://output', 'w' );

		// Add UTF-8 BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Write CSV headers
		fputcsv( $output, array(
			'ID',
			'Title',
			'Author ID',
			'Author Name',
			'Introduction',
			'Genres',
			'Status',
			'Publication Date',
			'Last Updated',
			'Views',
			'Average Rating',
			'Featured',
		) );

		// Write story data
		foreach ( $stories as $story ) {
			// Get author
			$author = get_userdata( $story->post_author );
			$author_name = $author ? $author->display_name : '';

			// Get genres
			$genres = wp_get_post_terms( $story->ID, 'fanfiction_genre', array( 'fields' => 'names' ) );
			$genres_string = is_array( $genres ) && ! is_wp_error( $genres ) ? implode( '|', $genres ) : '';

			// Get status
			$statuses = wp_get_post_terms( $story->ID, 'fanfiction_status', array( 'fields' => 'names' ) );
			$status_string = is_array( $statuses ) && ! is_wp_error( $statuses ) && ! empty( $statuses ) ? $statuses[0] : '';

			// Get views
			$views = 0;
			if ( class_exists( 'Fanfic_Views' ) ) {
				$views = Fanfic_Views::get_story_views( $story->ID );
			}

			// Get average rating
			$rating = 0.0;
			if ( class_exists( 'Fanfic_Ratings' ) ) {
				$rating = Fanfic_Ratings::get_story_rating( $story->ID );
			}

			// Get featured status
			$featured = get_post_meta( $story->ID, '_fanfic_featured', true ) ? 'Yes' : 'No';

			// Write row
			fputcsv( $output, array(
				$story->ID,
				$story->post_title,
				$story->post_author,
				$author_name,
				$story->post_excerpt,
				$genres_string,
				$status_string,
				$story->post_date,
				$story->post_modified,
				$views,
				$rating,
				$featured,
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Export chapters to CSV
	 *
	 * Exports all chapters with their metadata to a CSV file.
	 *
	 * @since 1.0.0
	 * @param array $args Optional. Query arguments to filter chapters.
	 * @return void
	 */
	public static function export_chapters( $args = array() ) {
		// Set default arguments
		$defaults = array(
			'post_type'      => 'fanfiction_chapter',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Get chapters
		$chapters = get_posts( $args );

		// Prepare CSV
		$filename = 'fanfiction_chapters_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		// Set headers for download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create output stream
		$output = fopen( 'php://output', 'w' );

		// Add UTF-8 BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Write CSV headers
		fputcsv( $output, array(
			'ID',
			'Story ID',
			'Story Title',
			'Chapter Number',
			'Chapter Type',
			'Title',
			'Content',
			'Publication Date',
			'Views',
			'Average Rating',
		) );

		// Write chapter data
		foreach ( $chapters as $chapter ) {
			// Get parent story
			$story = get_post( $chapter->post_parent );
			$story_title = $story ? $story->post_title : '';

			// Get chapter metadata
			$chapter_number = get_post_meta( $chapter->ID, '_fanfic_chapter_number', true );
			$chapter_type = get_post_meta( $chapter->ID, '_fanfic_chapter_type', true );

			// Get views
			$views = 0;
			if ( class_exists( 'Fanfic_Views' ) ) {
				$views = Fanfic_Views::get_chapter_views( $chapter->ID );
			}

			// Get average rating
			$rating = 0.0;
			if ( class_exists( 'Fanfic_Ratings' ) ) {
				$rating = Fanfic_Ratings::get_chapter_rating( $chapter->ID );
			}

			// Write row
			fputcsv( $output, array(
				$chapter->ID,
				$chapter->post_parent,
				$story_title,
				$chapter_number,
				$chapter_type,
				$chapter->post_title,
				$chapter->post_content,
				$chapter->post_date,
				$views,
				$rating,
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Export taxonomies to CSV
	 *
	 * Exports genres and custom taxonomies with their hierarchies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function export_taxonomies() {
		// Prepare CSV
		$filename = 'fanfiction_taxonomies_' . date( 'Y-m-d_H-i-s' ) . '.csv';

		// Set headers for download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create output stream
		$output = fopen( 'php://output', 'w' );

		// Add UTF-8 BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Write CSV headers
		fputcsv( $output, array(
			'Taxonomy',
			'Term ID',
			'Term Name',
			'Slug',
			'Parent ID',
			'Parent Name',
			'Description',
		) );

		// Get taxonomies to export
		$taxonomies = array( 'fanfiction_genre', 'fanfiction_status' );

		// Add custom taxonomies
		for ( $i = 1; $i <= 10; $i++ ) {
			$custom_tax = 'fanfiction_custom_' . $i;
			if ( taxonomy_exists( $custom_tax ) ) {
				$taxonomies[] = $custom_tax;
			}
		}

		// Export each taxonomy
		foreach ( $taxonomies as $taxonomy ) {
			// Get all terms
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			) );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			// Write term data
			foreach ( $terms as $term ) {
				// Get parent name if exists
				$parent_name = '';
				if ( $term->parent > 0 ) {
					$parent_term = get_term( $term->parent, $taxonomy );
					if ( $parent_term && ! is_wp_error( $parent_term ) ) {
						$parent_name = $parent_term->name;
					}
				}

				// Write row
				fputcsv( $output, array(
					$taxonomy,
					$term->term_id,
					$term->name,
					$term->slug,
					$term->parent,
					$parent_name,
					$term->description,
				) );
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Export stories with filter options
	 *
	 * Allows filtering by status, genre, date range, etc.
	 *
	 * @since 1.0.0
	 * @param array $filters Filter options.
	 * @return void
	 */
	public static function export_stories_filtered( $filters = array() ) {
		$args = array(
			'post_type'      => 'fanfiction_story',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Apply post status filter
		if ( ! empty( $filters['post_status'] ) ) {
			$args['post_status'] = sanitize_text_field( $filters['post_status'] );
		} else {
			$args['post_status'] = 'publish';
		}

		// Apply date range filter
		if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) {
			$date_query = array();

			if ( ! empty( $filters['date_from'] ) ) {
				$date_query['after'] = sanitize_text_field( $filters['date_from'] );
			}

			if ( ! empty( $filters['date_to'] ) ) {
				$date_query['before'] = sanitize_text_field( $filters['date_to'] );
			}

			if ( ! empty( $date_query ) ) {
				$args['date_query'] = array( $date_query );
			}
		}

		// Apply taxonomy filters
		if ( ! empty( $filters['genre'] ) || ! empty( $filters['status'] ) ) {
			$tax_query = array( 'relation' => 'AND' );

			if ( ! empty( $filters['genre'] ) ) {
				$tax_query[] = array(
					'taxonomy' => 'fanfiction_genre',
					'field'    => 'term_id',
					'terms'    => absint( $filters['genre'] ),
				);
			}

			if ( ! empty( $filters['status'] ) ) {
				$tax_query[] = array(
					'taxonomy' => 'fanfiction_status',
					'field'    => 'term_id',
					'terms'    => absint( $filters['status'] ),
				);
			}

			$args['tax_query'] = $tax_query;
		}

		// Apply author filter
		if ( ! empty( $filters['author'] ) ) {
			$args['author'] = absint( $filters['author'] );
		}

		// Apply featured filter
		if ( isset( $filters['featured'] ) && '' !== $filters['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_fanfic_featured',
					'value'   => $filters['featured'] ? '1' : '0',
					'compare' => '=',
				),
			);
		}

		// Call main export function
		self::export_stories( $args );
	}

	/**
	 * Get export statistics
	 *
	 * Returns counts of exportable items.
	 *
	 * @since 1.0.0
	 * @return array Statistics array.
	 */
	public static function get_export_stats() {
		$stats = array();

		// Count stories
		$stories = wp_count_posts( 'fanfiction_story' );
		$stats['total_stories'] = $stories->publish;
		$stats['draft_stories'] = $stories->draft;

		// Count chapters
		$chapters = wp_count_posts( 'fanfiction_chapter' );
		$stats['total_chapters'] = $chapters->publish;

		// Count genres
		$genres = get_terms( array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		) );
		$stats['total_genres'] = is_array( $genres ) ? count( $genres ) : 0;

		// Count statuses
		$statuses = get_terms( array(
			'taxonomy'   => 'fanfiction_status',
			'hide_empty' => false,
		) );
		$stats['total_statuses'] = is_array( $statuses ) ? count( $statuses ) : 0;

		return $stats;
	}
}
