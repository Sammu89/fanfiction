<?php
/**
 * Import Functionality Class
 *
 * Handles CSV import of stories, chapters, and taxonomies.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Import
 *
 * Provides CSV import functionality for fanfiction data.
 *
 * @since 1.0.0
 */
class Fanfic_Import {

	/**
	 * Import stories from CSV file
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to CSV file.
	 * @param bool   $dry_run   Whether to perform a dry run (validation only).
	 * @return array|WP_Error Result array or error.
	 */
	public static function import_stories( $file_path, $dry_run = true ) {
		// Validate file exists
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'CSV file not found.', 'fanfiction-manager' ) );
		}

		// Open file
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'file_read_error', __( 'Failed to read CSV file.', 'fanfiction-manager' ) );
		}

		// Add UTF-8 BOM filter
		$bom = fread( $handle, 3 );
		if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
			rewind( $handle );
		}

		// Read headers
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return new WP_Error( 'invalid_csv', __( 'Failed to read CSV headers.', 'fanfiction-manager' ) );
		}

		// Validate headers
		$required_headers = array( 'Title', 'Author ID', 'Introduction', 'Genres', 'Status' );
		$validation_result = self::validate_csv_headers( $headers, $required_headers );
		if ( is_wp_error( $validation_result ) ) {
			fclose( $handle );
			return $validation_result;
		}

		// Initialize counters
		$success_count = 0;
		$error_count = 0;
		$errors = array();
		$row_number = 1; // Start at 1 (header is row 0)

		// Process each row
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			// Map data to headers
			$story_data = array_combine( $headers, $data );

			// Import story
			$result = self::import_story( $story_data, $dry_run, $row_number );

			if ( is_wp_error( $result ) ) {
				$error_count++;
				$errors[] = sprintf(
					/* translators: 1: row number, 2: error message */
					__( 'Row %1$d: %2$s', 'fanfiction-manager' ),
					$row_number,
					$result->get_error_message()
				);
			} else {
				$success_count++;
			}
		}

		fclose( $handle );

		// Return results
		return array(
			'success_count' => $success_count,
			'error_count'   => $error_count,
			'errors'        => $errors,
			'dry_run'       => $dry_run,
		);
	}

	/**
	 * Import single story from CSV data
	 *
	 * @since 1.0.0
	 * @param array $data       Story data from CSV row.
	 * @param bool  $dry_run    Whether this is a dry run.
	 * @param int   $row_number Row number for error reporting.
	 * @return int|WP_Error Story ID on success, WP_Error on failure.
	 */
	private static function import_story( $data, $dry_run = true, $row_number = 0 ) {
		// Validate required fields
		if ( empty( $data['Title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Story title is required.', 'fanfiction-manager' ) );
		}

		if ( empty( $data['Author ID'] ) ) {
			return new WP_Error( 'missing_author', __( 'Author ID is required.', 'fanfiction-manager' ) );
		}

		if ( empty( $data['Introduction'] ) ) {
			return new WP_Error( 'missing_introduction', __( 'Story introduction is required.', 'fanfiction-manager' ) );
		}

		if ( empty( $data['Genres'] ) ) {
			return new WP_Error( 'missing_genres', __( 'At least one genre is required.', 'fanfiction-manager' ) );
		}

		if ( empty( $data['Status'] ) ) {
			return new WP_Error( 'missing_status', __( 'Story status is required.', 'fanfiction-manager' ) );
		}

		// Validate author exists
		$author_id = absint( $data['Author ID'] );
		$author = get_userdata( $author_id );
		if ( ! $author ) {
			return new WP_Error( 'invalid_author', sprintf(
				/* translators: %d: author ID */
				__( 'Author with ID %d not found.', 'fanfiction-manager' ),
				$author_id
			) );
		}

		// Check for duplicate title
		$existing_title = sanitize_text_field( $data['Title'] );
		$duplicate = get_page_by_title( $existing_title, OBJECT, 'fanfiction_story' );

		if ( $duplicate ) {
			// Append Roman numeral to make unique
			$existing_title = self::make_unique_title( $existing_title, 'fanfiction_story' );
		}

		// If dry run, return success without creating
		if ( $dry_run ) {
			return 1; // Return success indicator
		}

		// Prepare post data
		$post_data = array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $existing_title,
			'post_excerpt' => wp_kses_post( $data['Introduction'] ),
			'post_status'  => 'draft', // Always import as draft initially
			'post_author'  => $author_id,
		);

		// Set publication date if provided
		if ( ! empty( $data['Publication Date'] ) ) {
			$post_data['post_date'] = sanitize_text_field( $data['Publication Date'] );
		}

		// Insert story
		$story_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $story_id ) ) {
			return $story_id;
		}

		// Process genres (pipe-separated)
		$genres = explode( '|', $data['Genres'] );
		$genre_ids = array();
		foreach ( $genres as $genre_name ) {
			$genre_name = trim( $genre_name );
			if ( empty( $genre_name ) ) {
				continue;
			}

			$term = get_term_by( 'name', $genre_name, 'fanfiction_genre' );
			if ( ! $term ) {
				// Create term if it doesn't exist
				$new_term = wp_insert_term( $genre_name, 'fanfiction_genre' );
				if ( ! is_wp_error( $new_term ) ) {
					$genre_ids[] = $new_term['term_id'];
				}
			} else {
				$genre_ids[] = $term->term_id;
			}
		}

		// Assign genres
		if ( ! empty( $genre_ids ) ) {
			wp_set_post_terms( $story_id, $genre_ids, 'fanfiction_genre', false );
		}

		// Process status
		$status_name = trim( $data['Status'] );
		$status_term = get_term_by( 'name', $status_name, 'fanfiction_status' );
		if ( $status_term ) {
			wp_set_post_terms( $story_id, array( $status_term->term_id ), 'fanfiction_status', false );
		}

		// Set featured flag if provided
		if ( isset( $data['Featured'] ) && 'Yes' === $data['Featured'] ) {
			update_post_meta( $story_id, 'fanfic_is_featured', 1 );
			update_post_meta( $story_id, 'fanfic_featured_type', 'manual' );
			update_post_meta( $story_id, 'fanfic_featured_at', current_time( 'mysql' ) );
		}

		// Note: Story will remain draft until chapters are added and validation passes

		return $story_id;
	}

	/**
	 * Import chapters from CSV file
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to CSV file.
	 * @param bool   $dry_run   Whether to perform a dry run (validation only).
	 * @return array|WP_Error Result array or error.
	 */
	public static function import_chapters( $file_path, $dry_run = true ) {
		// Validate file exists
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'CSV file not found.', 'fanfiction-manager' ) );
		}

		// Open file
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'file_read_error', __( 'Failed to read CSV file.', 'fanfiction-manager' ) );
		}

		// Add UTF-8 BOM filter
		$bom = fread( $handle, 3 );
		if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
			rewind( $handle );
		}

		// Read headers
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return new WP_Error( 'invalid_csv', __( 'Failed to read CSV headers.', 'fanfiction-manager' ) );
		}

		// Validate headers
		$required_headers = array( 'Story ID', 'Title', 'Content' );
		$validation_result = self::validate_csv_headers( $headers, $required_headers );
		if ( is_wp_error( $validation_result ) ) {
			fclose( $handle );
			return $validation_result;
		}

		// Initialize counters
		$success_count = 0;
		$error_count = 0;
		$errors = array();
		$row_number = 1;

		// Process each row
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			// Map data to headers
			$chapter_data = array_combine( $headers, $data );

			// Import chapter
			$result = self::import_chapter( $chapter_data, $dry_run, $row_number );

			if ( is_wp_error( $result ) ) {
				$error_count++;
				$errors[] = sprintf(
					/* translators: 1: row number, 2: error message */
					__( 'Row %1$d: %2$s', 'fanfiction-manager' ),
					$row_number,
					$result->get_error_message()
				);
			} else {
				$success_count++;
			}
		}

		fclose( $handle );

		// Return results
		return array(
			'success_count' => $success_count,
			'error_count'   => $error_count,
			'errors'        => $errors,
			'dry_run'       => $dry_run,
		);
	}

	/**
	 * Import single chapter from CSV data
	 *
	 * @since 1.0.0
	 * @param array $data       Chapter data from CSV row.
	 * @param bool  $dry_run    Whether this is a dry run.
	 * @param int   $row_number Row number for error reporting.
	 * @return int|WP_Error Chapter ID on success, WP_Error on failure.
	 */
	private static function import_chapter( $data, $dry_run = true, $row_number = 0 ) {
		// Validate required fields
		if ( empty( $data['Story ID'] ) ) {
			return new WP_Error( 'missing_story_id', __( 'Story ID is required.', 'fanfiction-manager' ) );
		}

		if ( empty( $data['Title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Chapter title is required.', 'fanfiction-manager' ) );
		}

		if ( empty( $data['Content'] ) ) {
			return new WP_Error( 'missing_content', __( 'Chapter content is required.', 'fanfiction-manager' ) );
		}

		// Validate parent story exists
		$story_id = absint( $data['Story ID'] );
		$story = get_post( $story_id );
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			return new WP_Error( 'invalid_story', sprintf(
				/* translators: %d: story ID */
				__( 'Parent story with ID %d not found.', 'fanfiction-manager' ),
				$story_id
			) );
		}

		// If dry run, return success without creating
		if ( $dry_run ) {
			return 1;
		}

		// Prepare post data
		$post_data = array(
			'post_type'    => 'fanfiction_chapter',
			'post_title'   => sanitize_text_field( $data['Title'] ),
			'post_content' => wp_kses_post( $data['Content'] ),
			'post_status'  => 'publish',
			'post_parent'  => $story_id,
			'post_author'  => $story->post_author,
		);

		// Set publication date if provided
		if ( ! empty( $data['Publication Date'] ) ) {
			$post_data['post_date'] = sanitize_text_field( $data['Publication Date'] );
		}

		// Insert chapter
		$chapter_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $chapter_id ) ) {
			return $chapter_id;
		}

		// Set chapter metadata
		if ( ! empty( $data['Chapter Number'] ) ) {
			update_post_meta( $chapter_id, '_fanfic_chapter_number', absint( $data['Chapter Number'] ) );
		}

		if ( ! empty( $data['Chapter Type'] ) ) {
			$valid_types = array( 'prologue', 'chapter', 'epilogue' );
			$chapter_type = strtolower( trim( $data['Chapter Type'] ) );
			if ( in_array( $chapter_type, $valid_types, true ) ) {
				update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );
			}
		}

		return $chapter_id;
	}

	/**
	 * Import taxonomies from CSV file
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to CSV file.
	 * @return array|WP_Error Result array or error.
	 */
	public static function import_taxonomies( $file_path ) {
		// Validate file exists
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'CSV file not found.', 'fanfiction-manager' ) );
		}

		// Open file
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'file_read_error', __( 'Failed to read CSV file.', 'fanfiction-manager' ) );
		}

		// Add UTF-8 BOM filter
		$bom = fread( $handle, 3 );
		if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
			rewind( $handle );
		}

		// Read headers
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return new WP_Error( 'invalid_csv', __( 'Failed to read CSV headers.', 'fanfiction-manager' ) );
		}

		// Validate headers
		$required_headers = array( 'Taxonomy', 'Term Name', 'Slug' );
		$validation_result = self::validate_csv_headers( $headers, $required_headers );
		if ( is_wp_error( $validation_result ) ) {
			fclose( $handle );
			return $validation_result;
		}

		// Initialize counters
		$success_count = 0;
		$error_count = 0;
		$errors = array();
		$row_number = 1;

		// Process each row
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			// Map data to headers
			$term_data = array_combine( $headers, $data );

			// Validate taxonomy exists
			$taxonomy = sanitize_text_field( $term_data['Taxonomy'] );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				$error_count++;
				$errors[] = sprintf(
					/* translators: 1: row number, 2: taxonomy name */
					__( 'Row %1$d: Taxonomy "%2$s" does not exist.', 'fanfiction-manager' ),
					$row_number,
					$taxonomy
				);
				continue;
			}

			// Check if term already exists by slug
			$slug = sanitize_title( $term_data['Slug'] );
			$existing = term_exists( $slug, $taxonomy );

			if ( $existing ) {
				// Term already exists, skip
				$success_count++;
				continue;
			}

			// Prepare term args
			$args = array(
				'slug' => $slug,
			);

			// Add description if provided
			if ( ! empty( $term_data['Description'] ) ) {
				$args['description'] = sanitize_textarea_field( $term_data['Description'] );
			}

			// Add parent if provided
			if ( ! empty( $term_data['Parent ID'] ) ) {
				$parent_id = absint( $term_data['Parent ID'] );
				$parent = get_term( $parent_id, $taxonomy );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$args['parent'] = $parent_id;
				}
			}

			// Insert term
			$result = wp_insert_term(
				sanitize_text_field( $term_data['Term Name'] ),
				$taxonomy,
				$args
			);

			if ( is_wp_error( $result ) ) {
				$error_count++;
				$errors[] = sprintf(
					/* translators: 1: row number, 2: error message */
					__( 'Row %1$d: %2$s', 'fanfiction-manager' ),
					$row_number,
					$result->get_error_message()
				);
			} else {
				$success_count++;
			}
		}

		fclose( $handle );

		// Return results
		return array(
			'success_count' => $success_count,
			'error_count'   => $error_count,
			'errors'        => $errors,
		);
	}

	/**
	 * Validate CSV headers
	 *
	 * @since 1.0.0
	 * @param array $headers          Actual headers from CSV.
	 * @param array $required_headers Required headers.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private static function validate_csv_headers( $headers, $required_headers ) {
		$missing = array();

		foreach ( $required_headers as $required ) {
			if ( ! in_array( $required, $headers, true ) ) {
				$missing[] = $required;
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error( 'missing_headers', sprintf(
				/* translators: %s: comma-separated list of missing headers */
				__( 'Missing required columns: %s', 'fanfiction-manager' ),
				implode( ', ', $missing )
			) );
		}

		return true;
	}

	/**
	 * Make a title unique by appending Roman numerals
	 *
	 * @since 1.0.0
	 * @param string $title     Original title.
	 * @param string $post_type Post type.
	 * @return string Unique title.
	 */
	private static function make_unique_title( $title, $post_type ) {
		$roman_numerals = array( 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X' );
		$suffix_index = 0;

		$new_title = $title;

		while ( get_page_by_title( $new_title, OBJECT, $post_type ) ) {
			if ( $suffix_index >= count( $roman_numerals ) ) {
				// Fall back to regular numbers after X
				$new_title = $title . ' ' . ( $suffix_index + 1 );
			} else {
				$new_title = $title . ' ' . $roman_numerals[ $suffix_index ];
			}
			$suffix_index++;
		}

		return $new_title;
	}

	/**
	 * Validate uploaded file
	 *
	 * @since 1.0.0
	 * @param array $file Uploaded file array from $_FILES.
	 * @return string|WP_Error File path on success, WP_Error on failure.
	 */
	public static function validate_uploaded_file( $file ) {
		// Check for upload errors
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'File upload failed.', 'fanfiction-manager' ) );
		}

		// Validate file extension
		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $file_extension ) {
			return new WP_Error( 'invalid_extension', __( 'Only CSV files are allowed.', 'fanfiction-manager' ) );
		}

		// Validate MIME type
		$allowed_mimes = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		$file_type = wp_check_filetype( $file['name'], $allowed_mimes );

		if ( ! in_array( $file['type'], $allowed_mimes, true ) && ! $file_type['ext'] ) {
			return new WP_Error( 'invalid_mime', __( 'Invalid file type. Please upload a CSV file.', 'fanfiction-manager' ) );
		}

		// Validate file size (10MB max)
		$max_size = 10 * 1024 * 1024; // 10MB in bytes
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'file_too_large', __( 'File size exceeds 10MB limit.', 'fanfiction-manager' ) );
		}

		// Move to temp location
		$upload_dir = wp_upload_dir();
		$temp_file = $upload_dir['basedir'] . '/fanfic_import_' . time() . '.csv';

		if ( ! move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
			return new WP_Error( 'move_failed', __( 'Failed to move uploaded file.', 'fanfiction-manager' ) );
		}

		return $temp_file;
	}

	/**
	 * Clean up temporary import file
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to temporary file.
	 * @return bool True on success, false on failure.
	 */
	public static function cleanup_temp_file( $file_path ) {
		if ( file_exists( $file_path ) ) {
			return unlink( $file_path );
		}
		return false;
	}
}
