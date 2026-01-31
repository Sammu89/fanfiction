<?php
/**
 * Story Handler Class
 *
 * Handles all story-related operations including creation, editing, deletion, and publishing.
 *
 * @package FanfictionManager
 * @subpackage Handlers
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Story_Handler
 *
 * Story management operations.
 *
 * @since 1.0.0
 */
class Fanfic_Story_Handler {

	/**
	 * Get story image input from URL or upload
	 *
	 * @param array $errors Errors array to append to.
	 * @return array Array with image_url and attachment_id.
	 */
	private static function get_story_image_input( &$errors ) {
		// Get image URL from text input first
		$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( trim( $_POST['fanfic_story_image'] ) ) : '';
		$attachment_id = 0;

		// Then, check for a file upload, which takes precedence
		if ( function_exists( 'fanfic_handle_image_upload' ) ) {
			// Check if a file was actually uploaded for this field
			if ( ! empty( $_FILES['fanfic_story_image_file']['name'] ) && $_FILES['fanfic_story_image_file']['error'] === UPLOAD_ERR_OK ) {
				$upload = fanfic_handle_image_upload( 'fanfic_story_image_file', __( 'Story image', 'fanfiction-manager' ), $errors );
				if ( $upload && ! empty( $upload['url'] ) ) {
					$image_url = $upload['url'];
					$attachment_id = ! empty( $upload['attachment_id'] ) ? absint( $upload['attachment_id'] ) : 0;
				}
			}
		}

		return array( $image_url, $attachment_id );
	}

	/**
	 * Register story handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Register form submission handlers
		add_action( 'template_redirect', array( __CLASS__, 'handle_unified_story_form' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_create_story_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_story_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_delete_story' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_create_story', array( __CLASS__, 'ajax_create_story' ) );
		add_action( 'wp_ajax_fanfic_edit_story', array( __CLASS__, 'ajax_edit_story' ) );
		add_action( 'wp_ajax_fanfic_publish_story', array( __CLASS__, 'ajax_publish_story' ) );
		add_action( 'wp_ajax_fanfic_validate_story', array( __CLASS__, 'ajax_validate_story' ) );
	}

	/**
	 * Handle unified story form submission (template-story-form.php)
	 * Runs early on template_redirect to avoid headers already sent errors
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_unified_story_form() {
		// Check if this is a story form submission
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['fanfic_story_nonce'] ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Detect mode - check if we're editing an existing story
		$is_edit_mode = is_singular( 'fanfiction_story' ) && isset( $_GET['action'] ) && 'edit' === $_GET['action'];
		$story_id = $is_edit_mode ? get_the_ID() : 0;

		// Verify nonce
		$nonce_action = 'fanfic_story_form_action' . ( $is_edit_mode ? '_' . $story_id : '' );
		if ( ! wp_verify_nonce( $_POST['fanfic_story_nonce'], $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		$current_user = wp_get_current_user();
		$errors = array();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );
		$form_action = isset( $_POST['fanfic_form_action'] ) ? sanitize_text_field( $_POST['fanfic_form_action'] ) : 'save_draft';

		// Get warnings and tags (Phase 4.1)
		$warning_ids = isset( $_POST['fanfic_story_warnings'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_warnings'] ) : array();
		$visible_tags_raw = isset( $_POST['fanfic_visible_tags'] ) ? sanitize_text_field( $_POST['fanfic_visible_tags'] ) : '';
		$invisible_tags_raw = isset( $_POST['fanfic_invisible_tags'] ) ? sanitize_text_field( $_POST['fanfic_invisible_tags'] ) : '';

		// Get language (Phase 4.x)
		$language_id = isset( $_POST['fanfic_story_language'] ) ? absint( $_POST['fanfic_story_language'] ) : 0;

		// Get custom taxonomy values
		$custom_taxonomy_values = array();
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $custom_taxonomies as $taxonomy ) {
				$post_key = 'fanfic_custom_' . $taxonomy['slug'];
				if ( isset( $_POST[ $post_key ] ) ) {
					$custom_taxonomy_values[ $taxonomy['id'] ] = array_map( 'absint', (array) $_POST[ $post_key ] );
				}
			}
		}

		// Parse tags from comma-separated string
		$visible_tags = array_filter( array_map( 'trim', explode( ',', $visible_tags_raw ) ) );
		$invisible_tags = array_filter( array_map( 'trim', explode( ',', $invisible_tags_raw ) ) );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, store in transient and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// CREATE MODE
		if ( ! $is_edit_mode ) {
			// Generate unique slug
			$base_slug = sanitize_title( $title );
			$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

			// Create story as draft
			$new_story_id = wp_insert_post( array(
				'post_type'    => 'fanfiction_story',
				'post_title'   => $title,
				'post_name'    => $unique_slug,
				'post_excerpt' => $introduction,
				'post_status'  => 'draft',
				'post_author'  => $current_user->ID,
			) );

			if ( is_wp_error( $new_story_id ) ) {
				$errors[] = $new_story_id->get_error_message();
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			// Set genres
			if ( ! empty( $genres ) ) {
				wp_set_post_terms( $new_story_id, $genres, 'fanfiction_genre' );
			}

			// Set status
			wp_set_post_terms( $new_story_id, $status, 'fanfiction_status' );

			// Save fandoms
			if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
				Fanfic_Fandoms::save_story_fandoms( $new_story_id, $fandom_ids, $is_original_work );
			}

			// Save warnings (Phase 4.1)
			if ( class_exists( 'Fanfic_Warnings' ) ) {
				Fanfic_Warnings::save_story_warnings( $new_story_id, $warning_ids );
			}

			// Save language
			if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
				Fanfic_Languages::save_story_language( $new_story_id, $language_id );
			}

			// Save custom taxonomy values
			if ( class_exists( 'Fanfic_Custom_Taxonomies' ) && ! empty( $custom_taxonomy_values ) ) {
				foreach ( $custom_taxonomy_values as $taxonomy_id => $term_ids ) {
					Fanfic_Custom_Taxonomies::save_story_terms( $new_story_id, $taxonomy_id, $term_ids );
				}
			}

			// Save tags (Phase 4.1)
			if ( function_exists( 'fanfic_save_visible_tags' ) ) {
				fanfic_save_visible_tags( $new_story_id, $visible_tags );
			}
			if ( function_exists( 'fanfic_save_invisible_tags' ) ) {
				fanfic_save_invisible_tags( $new_story_id, $invisible_tags );
			}

			// Set featured image
			if ( ! empty( $image_url ) ) {
				update_post_meta( $new_story_id, '_fanfic_featured_image', $image_url );
			}
			if ( ! empty( $image_attachment_id ) ) {
				set_post_thumbnail( $new_story_id, $image_attachment_id );
			}

			// Initialize view count
			update_post_meta( $new_story_id, '_fanfic_views', 0 );

			// Redirect based on action
			$story_permalink = get_permalink( $new_story_id );
			if ( 'add_chapter' === $form_action ) {
				$redirect_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
			} else {
				$redirect_url = add_query_arg( 'action', 'edit', $story_permalink );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		// EDIT MODE
		else {
			$story = get_post( $story_id );

			// Check permissions
			if ( ! $story || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
				wp_die( esc_html__( 'You do not have permission to edit this story.', 'fanfiction-manager' ) );
			}

			// Determine post status based on action
			$current_status = get_post_status( $story_id );
			$post_status = $current_status;

			if ( 'save_draft' === $form_action ) {
				$post_status = 'draft';
			} elseif ( 'publish' === $form_action ) {
				// For publish action, save as draft first so we can validate the updated content
				$post_status = 'draft';
			}
			// For 'update' and 'add_chapter' actions, keep current status

			// Update story with the new data
			error_log( '=== STORY HANDLER UPDATE START ===' );
			error_log( 'Story ID: ' . $story_id );
			error_log( 'Introduction length: ' . strlen( $introduction ) );
			error_log( 'Introduction: ' . substr( $introduction, 0, 50 ) );
			error_log( 'Form action: ' . $form_action );

			$result = wp_update_post( array(
				'ID'           => $story_id,
				'post_title'   => $title,
				'post_excerpt' => $introduction,
				'post_status'  => $post_status,
			), true ); // true = return WP_Error on failure

			error_log( 'wp_update_post result: ' . ( is_wp_error( $result ) ? $result->get_error_message() : $result ) );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			if ( ! $result || 0 === $result ) {
				$errors[] = __( 'Failed to update story. Please try again.', 'fanfiction-manager' );
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			// Clear post cache to ensure fresh data
			clean_post_cache( $story_id );
			wp_cache_delete( $story_id, 'posts' );
			wp_cache_delete( $story_id, 'post_meta' );

			// Verify the save
			$verify = get_post( $story_id );
			error_log( 'Verified post_excerpt length after update: ' . strlen( $verify->post_excerpt ) );
			error_log( 'Verified post_excerpt: ' . substr( $verify->post_excerpt, 0, 50 ) );
			error_log( '=== STORY HANDLER UPDATE END ===' );

			// NOW validate if user wanted to publish
			if ( 'publish' === $form_action ) {
				$validation_errors = Fanfic_Validation::get_validation_errors( $story_id );

				if ( ! empty( $validation_errors ) ) {
					// Validation failed - story stays as draft, show errors
					set_transient( 'fanfic_story_validation_errors_' . $current_user->ID . '_' . $story_id, $validation_errors, 60 );
					Fanfic_Flash_Messages::add_message( 'error', __( 'Story could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ) );
					// Redirect back to story with validation error, removing any success params
					$referer = remove_query_arg( 'success', wp_get_referer() );
					wp_safe_redirect( add_query_arg( 'action', 'edit', $referer ) ); // Redirect to edit page
					exit;
				}

				// Validation passed - now publish
				wp_update_post( array(
					'ID'          => $story_id,
					'post_status' => 'publish',
				) );

				// Clear cache again
				clean_post_cache( $story_id );
				wp_cache_delete( $story_id, 'posts' );
				wp_cache_delete( $story_id, 'post_meta' );
			}

			// Update genres
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );

			// Update status
			wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

			// Save fandoms
			if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
				Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
			}

			// Save warnings (Phase 4.1)
			if ( class_exists( 'Fanfic_Warnings' ) ) {
				Fanfic_Warnings::save_story_warnings( $story_id, $warning_ids );
			}

			// Save language
			if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
				Fanfic_Languages::save_story_language( $story_id, $language_id );
			}

			// Save custom taxonomy values
			if ( class_exists( 'Fanfic_Custom_Taxonomies' ) && ! empty( $custom_taxonomy_values ) ) {
				foreach ( $custom_taxonomy_values as $taxonomy_id => $term_ids ) {
					Fanfic_Custom_Taxonomies::save_story_terms( $story_id, $taxonomy_id, $term_ids );
				}
			}

			// Save tags (Phase 4.1)
			if ( function_exists( 'fanfic_save_visible_tags' ) ) {
				fanfic_save_visible_tags( $story_id, $visible_tags );
			}
			if ( function_exists( 'fanfic_save_invisible_tags' ) ) {
				fanfic_save_invisible_tags( $story_id, $invisible_tags );
			}

			// Update featured image
			if ( ! empty( $image_url ) ) {
				update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
			} else {
				delete_post_meta( $story_id, '_fanfic_featured_image' );
			}
			if ( ! empty( $image_attachment_id ) ) {
				set_post_thumbnail( $story_id, $image_attachment_id );
			}

			// Redirect based on action
			if ( 'add_chapter' === $form_action ) {
				Fanfic_Flash_Messages::add_message( 'success', __( 'Story updated. Now add your first chapter!', 'fanfiction-manager' ) );
				$story_permalink = get_permalink( $story_id );
				$redirect_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
			} else {
				Fanfic_Flash_Messages::add_message( 'success', __( 'Story updated successfully!', 'fanfiction-manager' ) );
				$redirect_url = wp_get_referer();
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Get page URL with fallback to manual construction
	 *
	 * Helper function to build URLs for plugin pages. First tries to get the page by path,
	 * if that fails, constructs the URL manually using the configured slugs.
	 *
	 * @since 1.0.0
	 * @param string $page_slug The page slug to look for (e.g., 'edit-story', 'manage-stories').
	 * @param string $page_path The relative path after dashboard slug (e.g., 'edit-story', 'manage-stories').
	 * @return string The page URL.
	 */
	public static function get_page_url_with_fallback( $page_slug, $page_path = '' ) {
		// Special handling for create-story: use URL manager which returns main page with ?action=create-story
		if ( 'create-story' === $page_slug ) {
			$url_manager = Fanfic_URL_Manager::get_instance();
			$url = $url_manager->get_page_url( 'create-story' );
			if ( ! empty( $url ) ) {
				return $url;
			}
			// Fallback if URL manager fails
			$page_ids = get_option( 'fanfic_system_page_ids', array() );
			if ( isset( $page_ids['main'] ) && $page_ids['main'] > 0 ) {
				return add_query_arg( 'action', 'create-story', get_permalink( $page_ids['main'] ) );
			}
		}

		// Try to get the page by path first
		$page = get_page_by_path( $page_slug );
		if ( $page ) {
			return get_permalink( $page );
		}

		// Fallback to manual URL construction
		$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
		$dashboard_slug = get_option( 'fanfic_dashboard_slug', 'dashboard' );

		// If no custom page_path provided, use the page_slug
		if ( empty( $page_path ) ) {
			$page_path = $page_slug;
		}

		// Build URL based on whether it's a dashboard page or not
		if ( in_array( $page_slug, array( 'dashboard', 'edit-story', 'edit-chapter', 'manage-stories', 'edit-profile' ), true ) ) {
			// Dashboard pages
			$url = home_url( "/{$base_slug}/{$dashboard_slug}/{$page_path}/" );
		} else {
			// Non-dashboard pages (archive, search, create-chapter, etc.)
			$url = home_url( "/{$base_slug}/{$page_path}/" );
		}

		error_log( "Warning: Page '{$page_slug}' not found, using fallback URL: {$url}" );
		return $url;
	}

	/**
	 * Handle create story form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_create_story_submission() {
		if ( ! isset( $_POST['fanfic_create_story_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_story_nonce'], 'fanfic_create_story_action' ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$errors = array();
		$current_user = wp_get_current_user();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Generate unique slug before creating the post
		$base_slug = sanitize_title( $title );
		$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

		// Create story as draft initially
		$story_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $title,
			'post_name'    => $unique_slug,
			'post_excerpt' => $introduction,
			'post_status'  => 'draft',
			'post_author'  => $current_user->ID,
		) );

		if ( is_wp_error( $story_id ) ) {
			$errors[] = $story_id->get_error_message();
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		// Initialize view count
		update_post_meta( $story_id, '_fanfic_views', 0 );

		// Redirect to story permalink with action parameter
		$story_permalink = get_permalink( $story_id );
		Fanfic_Flash_Messages::add_message( 'success', __( 'Story created as draft. Now add your first chapter!', 'fanfiction-manager' ) );
		$add_chapter_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
		wp_redirect( $add_chapter_url );
		exit;
	}

	/**
	 * Handle edit story form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_edit_story_submission() {
		if ( ! isset( $_POST['fanfic_edit_story_submit'] ) ) {
			return;
		}

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_story_nonce'], 'fanfic_edit_story_action_' . $story_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || $story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$errors = array();

		// Get save action (draft or publish)
		$save_action = isset( $_POST['fanfic_save_action'] ) ? sanitize_text_field( $_POST['fanfic_save_action'] ) : 'draft';
		$post_status = ( 'publish' === $save_action ) ? 'publish' : 'draft';

		// Check if story currently has chapters
		$had_chapters_before = ! empty( get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) ) );

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Update story
		$result = wp_update_post( array(
			'ID'           => $story_id,
			'post_title'   => $title,
			'post_excerpt' => $introduction,
			'post_status'  => $post_status,
		) );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Update genres
		wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );

		// Update status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Update featured image
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		} else {
			delete_post_meta( $story_id, '_fanfic_featured_image' );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		// Determine redirect based on whether story had chapters before
		if ( ! $had_chapters_before && 'draft' === $save_action ) {
			// First save without chapters - redirect to add chapter page
			Fanfic_Flash_Messages::add_message( 'success', __( 'Story saved as draft. Now add your first chapter!', 'fanfiction-manager' ) );
			$redirect_url = add_query_arg(
				array(
					'action'   => 'add-chapter',
					'story_id' => $story_id,
				),
				self::get_page_url_with_fallback( 'manage-stories' )
			);
		} else {
			// Normal save - redirect back with success message
			Fanfic_Flash_Messages::add_message( 'success', __( 'Story updated successfully!', 'fanfiction-manager' ) );
			$redirect_url = wp_get_referer();
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle delete story action
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_delete_story() {
		if ( ! isset( $_POST['fanfic_delete_story_submit'] ) ) {
			return;
		}

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_delete_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_delete_story_nonce'], 'fanfic_delete_story_' . $story_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || ( $story->post_author != $current_user->ID && ! current_user_can( 'delete_others_posts' ) ) ) {
			return;
		}

		$is_blocked = (bool) get_post_meta( $story_id, '_fanfic_story_blocked', true );
		if ( $is_blocked && ! current_user_can( 'delete_others_posts' ) ) {
			Fanfic_Flash_Messages::add_message( 'error', fanfic_get_blocked_story_message() );
			$redirect_url = self::get_page_url_with_fallback( 'manage-stories' );
			wp_redirect( $redirect_url );
			exit;
		}

		// Delete all chapters first
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		foreach ( $chapters as $chapter_id ) {
			wp_delete_post( $chapter_id, true );
		}

		// Delete the story
		wp_delete_post( $story_id, true );

		// Redirect to manage stories with success message
        Fanfic_Flash_Messages::add_message( 'success', __( 'Story deleted successfully.', 'fanfiction-manager' ) );
		$redirect_url = self::get_page_url_with_fallback( 'manage-stories' );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for creating a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_create_story() {
		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_story_nonce'], 'fanfic_create_story_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'fanfiction-manager' )
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in to create a story.', 'fanfiction-manager' )
			) );
		}

		$errors = array();
		$current_user = wp_get_current_user();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, return error response
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $errors )
			) );
		}

		// Generate unique slug before creating the post
		$base_slug = sanitize_title( $title );
		$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

		// Create story as draft initially
		$story_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $title,
			'post_name'    => $unique_slug,
			'post_excerpt' => $introduction,
			'post_status'  => 'draft',
			'post_author'  => $current_user->ID,
		) );

		if ( is_wp_error( $story_id ) ) {
			wp_send_json_error( array(
				'message' => $story_id->get_error_message()
			) );
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		// Initialize view count
		update_post_meta( $story_id, '_fanfic_views', 0 );

		// Build redirect URL to story permalink with action=add-chapter
		$story_permalink = get_permalink( $story_id );
		$add_chapter_url = add_query_arg( 'action', 'add-chapter', $story_permalink );

		// Return success response
		wp_send_json_success( array(
			'message' => __( 'Story created as draft. Add your first chapter to publish it.', 'fanfiction-manager' ),
			'redirect_url' => $add_chapter_url,
			'story_id' => $story_id,
			'story_slug' => $unique_slug
		) );
	}

	/**
	 * AJAX handler for editing a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_edit_story() {
		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_story_nonce'], 'fanfic_edit_story_action_' . $story_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'fanfiction-manager' )
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in to edit a story.', 'fanfiction-manager' )
			) );
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || $story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to edit this story.', 'fanfiction-manager' )
			) );
		}

		$errors = array();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, return error response
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $errors )
			) );
		}

		// Update story
		$updated = wp_update_post( array(
			'ID'           => $story_id,
			'post_title'   => $title,
			'post_excerpt' => $introduction,
		) );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array(
				'message' => $updated->get_error_message()
			) );
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		// Build redirect URL
		$redirect_url = add_query_arg(
			array(
				'story_id' => $story_id,
				'updated' => 'success'
			),
			wp_get_referer()
		);

		// Return success response
		wp_send_json_success( array(
			'message' => __( 'Story updated successfully!', 'fanfiction-manager' ),
			'redirect_url' => $redirect_url,
			'story_id' => $story_id
		) );
	}

	/**
	 * AJAX handler for publishing a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_publish_story() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_publish_story' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to publish stories.', 'fanfiction-manager' ) ) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid story ID.', 'fanfiction-manager' ) ) );
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check if story exists
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Story not found.', 'fanfiction-manager' ) ) );
		}

		// Check permissions - must be author or have edit_others_posts capability
		if ( $story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to publish this story.', 'fanfiction-manager' ) ) );
		}

		// PRE-SAVE VALIDATION: Check if story can be published before saving
		$validation_result = Fanfic_Validation::can_publish_story( $story_id );

		if ( ! $validation_result['can_publish'] ) {
			wp_send_json_error( array(
				'message'         => __( 'Cannot publish story. Missing required fields:', 'fanfiction-manager' ),
				'missing_fields'  => $validation_result['missing_fields'], // Array of field => message pairs
				'errors'          => array_values( $validation_result['missing_fields'] ), // Just the messages
			) );
		}

		// Update story status to publish
		$result = wp_update_post( array(
			'ID'          => $story_id,
			'post_status' => 'publish',
		), true ); // true = return WP_Error on failure

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( ! $result || 0 === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to publish story. Please try again.', 'fanfiction-manager' ) ) );
		}

		// Clear post cache to ensure fresh data
		clean_post_cache( $story_id );
		wp_cache_delete( $story_id, 'posts' );
		wp_cache_delete( $story_id, 'post_meta' );

		wp_send_json_success( array(
			'message' => __( 'Story is now visible to readers.', 'fanfiction-manager' ),
			'story_id' => $story_id
		) );
	}

	/**
	 * AJAX handler for validating a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_validate_story() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_validate_story' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid story ID.', 'fanfiction-manager' ) ) );
		}

		$story = get_post( $story_id );

		// Check if story exists
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Story not found.', 'fanfiction-manager' ) ) );
		}

		// Check permissions
		if ( $story->post_author != wp_get_current_user()->ID && ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to validate this story.', 'fanfiction-manager' ) ) );
		}

		// Check validation
		$validation_result = Fanfic_Validation::can_publish_story( $story_id );

		wp_send_json_success( array(
			'is_valid'        => $validation_result['can_publish'],
			'missing_fields'  => $validation_result['missing_fields'], // Array of field => message pairs
			'errors'          => array_values( $validation_result['missing_fields'] ), // Just the messages
		) );
	}

	/**
	 * Check if a story has chapters
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID
	 * @return bool True if story has chapters, false otherwise
	 */
	public static function story_has_chapters( $story_id ) {
		$chapter_count = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return ! empty( $chapter_count );
	}

}
