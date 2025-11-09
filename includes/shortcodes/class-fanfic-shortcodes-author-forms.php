<?php
/**
 * Author Forms Shortcodes Class
 *
 * Handles all author dashboard form shortcodes for story/chapter creation, editing, and profile management.
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
 * Class Fanfic_Shortcodes_Author_Forms
 *
 * Author dashboard forms for content management.
 *
 * @since 1.0.0
 */
class Fanfic_Shortcodes_Author_Forms {

	/**
	 * Register author forms shortcodes
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		error_log( 'Fanfic_Shortcodes_Author_Forms::register() called' );

		// Register form submission handlers
		// Use 'template_redirect' instead of 'init' to ensure it runs on every request
		add_action( 'template_redirect', array( __CLASS__, 'handle_create_story_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_story_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_create_chapter_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_chapter_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_profile_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_delete_story' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_delete_chapter' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_create_story', array( __CLASS__, 'ajax_create_story' ) );
		add_action( 'wp_ajax_fanfic_edit_story', array( __CLASS__, 'ajax_edit_story' ) );
		add_action( 'wp_ajax_fanfic_create_chapter', array( __CLASS__, 'handle_create_chapter_submission' ) );
		add_action( 'wp_ajax_fanfic_edit_chapter', array( __CLASS__, 'handle_edit_chapter_submission' ) );
		add_action( 'wp_ajax_fanfic_edit_profile', array( __CLASS__, 'handle_edit_profile_submission' ) );
	add_action( 'wp_ajax_fanfic_delete_chapter', array( __CLASS__, 'ajax_delete_chapter' ) );
	add_action( 'wp_ajax_fanfic_publish_story', array( __CLASS__, 'ajax_publish_story' ) );
		add_action( 'wp_ajax_fanfic_check_last_chapter', array( __CLASS__, 'ajax_check_last_chapter' ) );

		// Filter chapters by parent story status (hide chapters of draft stories on frontend)
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_chapters_by_story_status' ) );
		add_filter( 'posts_join', array( __CLASS__, 'filter_chapters_join' ), 10, 2 );
		add_filter( 'posts_where', array( __CLASS__, 'filter_chapters_where' ), 10, 2 );
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
	private static function get_page_url_with_fallback( $page_slug, $page_path = '' ) {
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
		$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( $_POST['fanfic_story_image'] ) : '';

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
			'post_content' => $introduction,
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

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}

		// Initialize view count
		update_post_meta( $story_id, '_fanfic_views', 0 );

		// Redirect to story permalink with action parameter
		$story_permalink = get_permalink( $story_id );
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
		$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( $_POST['fanfic_story_image'] ) : '';

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
			'post_content' => $introduction,
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

		// Update featured image
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		} else {
			delete_post_meta( $story_id, '_fanfic_featured_image' );
		}

		// Determine redirect based on whether story had chapters before
		if ( ! $had_chapters_before && 'draft' === $save_action ) {
			// First save without chapters - redirect to add chapter page
			$redirect_url = add_query_arg(
				array(
					'action'   => 'add-chapter',
					'story_id' => $story_id,
				),
				self::get_page_url_with_fallback( 'manage-stories' )
			);
		} else {
			// Normal save - redirect back with success message
			$redirect_url = add_query_arg(
				array(
					'story_id' => $story_id,
					'updated'  => 'success',
				),
				wp_get_referer()
			);
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle create chapter form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_create_chapter_submission() {
		if ( ! isset( $_POST['fanfic_create_chapter_submit'] ) ) {
			return;
		}

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_chapter_nonce'], 'fanfic_create_chapter_action_' . $story_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || ( $story->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) ) {
			return;
		}

		$errors = array();

		// Get and sanitize form data
		$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
		$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
		error_log( 'Create chapter - Action: ' . $chapter_action . ', Status: ' . $chapter_status );
		$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
		$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
		$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';

		// Validate chapter type restrictions
		if ( 'prologue' === $chapter_type && self::story_has_prologue( $story_id ) ) {
			$errors[] = __( 'This story already has a prologue. Only one prologue is allowed per story.', 'fanfiction-manager' );
		}

		if ( 'epilogue' === $chapter_type && self::story_has_epilogue( $story_id ) ) {
			$errors[] = __( 'This story already has an epilogue. Only one epilogue is allowed per story.', 'fanfiction-manager' );
		}

		// Auto-calculate chapter number based on type
		$chapter_number = 0;
		if ( 'prologue' === $chapter_type ) {
			$chapter_number = self::get_next_prologue_number( $story_id );
		} elseif ( 'epilogue' === $chapter_type ) {
			$chapter_number = self::get_next_epilogue_number( $story_id );
		} else {
			// For regular chapters, get from form input
			$chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
		}

		// Validate
		if ( 'chapter' === $chapter_type && ! $chapter_number ) {
			$errors[] = __( 'Chapter number is required.', 'fanfiction-manager' );
		}

		if ( empty( $title ) ) {
			$errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
		}

		if ( empty( $content ) ) {
			$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

			error_log( 'Chapter created with ID: ' . $chapter_id . ', Status: ' . $chapter_status );
		// Create chapter
		$chapter_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_chapter',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $chapter_status,
			'post_author'  => $current_user->ID,
			'post_parent'  => $story_id,
		) );

		if ( is_wp_error( $chapter_id ) ) {
			$errors[] = $chapter_id->get_error_message();
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Set chapter metadata
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );

		// Check if this is the first published chapter and story is a draft
		error_log( 'Checking for first published chapter. Chapter status: ' . $chapter_status . ', Story status: ' . $story->post_status . ', Chapter type: ' . $chapter_type );
		$is_first_published_chapter = false;
		if ( 'publish' === $chapter_status && 'draft' === $story->post_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count published chapters (excluding the one we just created)
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
			) );
			error_log( 'Other published chapters count: ' . count( $published_chapters ) );

			// If there are no other published chapters, this is the first
			if ( empty( $published_chapters ) ) {
				error_log( 'This is the FIRST published chapter! Will show prompt.' );
				$is_first_published_chapter = true;
			}
		}



	// Redirect to chapter edit page
	$chapter_url = get_permalink( $chapter_id );
	$edit_url = add_query_arg( 'action', 'edit', $chapter_url );

	// Add parameter to show publication prompt if this is first chapter
	if ( $is_first_published_chapter ) {
		error_log( 'Redirecting to: ' . $edit_url );
		$edit_url = add_query_arg( 'show_publish_prompt', '1', $edit_url );
	}

		// Check if this is an AJAX request
		if ( wp_doing_ajax() ) {
			// Return JSON response for AJAX
			wp_send_json_success( array(
				'redirect_url' => $edit_url,
				'chapter_id' => $chapter_id,
			) );
		} else {
			// Regular form submission - redirect
			wp_redirect( $edit_url );
			exit;
		}
	}

	/**
	 * Handle edit chapter form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_edit_chapter_submission() {
		if ( ! isset( $_POST['fanfic_edit_chapter_submit'] ) ) {
			return;
		}

		$chapter_id = isset( $_POST['fanfic_chapter_id'] ) ? absint( $_POST['fanfic_chapter_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_chapter_nonce'], 'fanfic_edit_chapter_action_' . $chapter_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );

		// Check permissions
		if ( ! $chapter || ( $chapter->post_author != $current_user->ID && ! current_user_can( 'edit_others_posts' ) ) ) {
			return;
		}

		$errors = array();

		// Get and sanitize form data
		$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
		$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
		$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
		$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
		$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';

		// Get story ID from chapter
		$story_id = $chapter->post_parent;

		// Get current chapter type
		$old_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		if ( empty( $old_type ) ) {
			$old_type = 'chapter';
		}

		// Validate chapter type restrictions when changing type
		if ( 'prologue' === $chapter_type && 'prologue' !== $old_type && self::story_has_prologue( $story_id, $chapter_id ) ) {
			$errors[] = __( 'This story already has a prologue. Only one prologue is allowed per story.', 'fanfiction-manager' );
		}

		if ( 'epilogue' === $chapter_type && 'epilogue' !== $old_type && self::story_has_epilogue( $story_id, $chapter_id ) ) {
			$errors[] = __( 'This story already has an epilogue. Only one epilogue is allowed per story.', 'fanfiction-manager' );
		}

		// Auto-calculate chapter number based on type
		$chapter_number = 0;
		if ( 'prologue' === $chapter_type ) {
			// When editing, preserve the existing prologue number if it was already a prologue
			if ( 'prologue' === $old_type ) {
				$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			} else {
				$chapter_number = self::get_next_prologue_number( $story_id );
			}
		} elseif ( 'epilogue' === $chapter_type ) {
			// When editing, preserve the existing epilogue number if it was already an epilogue
			if ( 'epilogue' === $old_type ) {
				$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			} else {
				$chapter_number = self::get_next_epilogue_number( $story_id );
			}
		} else {
			// For regular chapters, get from form input
			$chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
		}

		// Validate
		if ( 'chapter' === $chapter_type && ! $chapter_number ) {
			$errors[] = __( 'Chapter number is required.', 'fanfiction-manager' );
		}

		if ( empty( $title ) ) {
			$errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
		}

		if ( empty( $content ) ) {
			$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Update chapter
		$result = wp_update_post( array(
			'ID'           => $chapter_id,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $chapter_status,
		) );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
			set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Update chapter metadata
		update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
		update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );


		// Check if this is becoming the first published chapter
		$is_first_published_chapter = false;
		$old_status = get_post_status( $chapter_id );
		$story = get_post( $story_id );
		
		// Only check if we're publishing a chapter that was draft and story is also draft
		if ( 'publish' === $chapter_status && 'draft' === $old_status && 'draft' === $story->post_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other published chapters
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
			) );

			// If there are no other published chapters, this is the first
			if ( empty( $published_chapters ) ) {
				$is_first_published_chapter = true;
			}
		}

		// Check if we're drafting the last published chapter/prologue
		if ( 'draft' === $chapter_status && 'publish' === $old_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other published chapters/prologues (excluding this one)
			$published_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
			) );

			// If no other published chapters/prologues, auto-draft the story
			if ( empty( $published_chapters ) ) {
				wp_update_post( array(
					'ID'          => $story_id,
					'post_status' => 'draft',
				) );
				error_log( 'Auto-drafted story ' . $story_id . ' because last published chapter/prologue was drafted' );
			}
		}

		// Redirect back with success message
		$redirect_url = add_query_arg(
			array(
				'chapter_id' => $chapter_id,
				'updated' => 'success'
			),
			wp_get_referer()
		);
		
		// Add parameter to show publication prompt if this is first chapter
		if ( $is_first_published_chapter ) {
			$redirect_url = add_query_arg( 'show_publish_prompt', '1', $redirect_url );
		}
		
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle edit profile form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_edit_profile_submission() {
		if ( ! isset( $_POST['fanfic_edit_profile_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_profile_nonce'] ) ) {
			error_log( 'Nonce not set' );
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				set_transient( 'fanfic_profile_errors_' . $current_user->ID, array( __( 'Security verification failed (missing nonce). Please try again.', 'fanfiction-manager' ) ), 60 );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		if ( ! wp_verify_nonce( $_POST['fanfic_edit_profile_nonce'], 'fanfic_edit_profile_action' ) ) {
			error_log( 'Nonce verification failed' );
			// Nonce verification failed - add error
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				set_transient( 'fanfic_profile_errors_' . $current_user->ID, array( __( 'Security verification failed. Please try again.', 'fanfiction-manager' ) ), 60 );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$current_user = wp_get_current_user();
		$errors = array();

		// Get and sanitize form data
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( trim( $_POST['display_name'] ) ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( trim( $_POST['user_email'] ) ) : '';
		$user_url = isset( $_POST['user_url'] ) ? esc_url_raw( trim( $_POST['user_url'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';
		$avatar_url = isset( $_POST['fanfic_avatar_url'] ) ? esc_url_raw( trim( $_POST['fanfic_avatar_url'] ) ) : '';

		// Validate required fields
		if ( empty( $display_name ) ) {
			$errors[] = __( 'Display name is required.', 'fanfiction-manager' );
		}

		if ( empty( $user_email ) ) {
			$errors[] = __( 'Email address is required.', 'fanfiction-manager' );
		}

		// Validate email format
		if ( ! empty( $user_email ) && ! is_email( $user_email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'fanfiction-manager' );
		}

		// Check if email is already in use by another user
		if ( ! empty( $user_email ) && $user_email !== $current_user->user_email ) {
			$email_exists = email_exists( $user_email );
			if ( $email_exists && $email_exists !== $current_user->ID ) {
				$errors[] = __( 'This email address is already in use by another user.', 'fanfiction-manager' );
			}
		}

		// Validate bio length
		if ( ! empty( $description ) && strlen( $description ) > 5000 ) {
			$errors[] = __( 'Biographical info must be less than 5000 characters.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			error_log( 'Validation errors found: ' . implode( ', ', $errors ) );
			set_transient( 'fanfic_profile_errors_' . $current_user->ID, $errors, 60 );

			// Check if AJAX request
			$is_ajax = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

			if ( $is_ajax ) {
				wp_send_json_error(
					array(
						'message' => implode( ' ', $errors ),
					)
				);
			} else {
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
		}

		// Update user using native WordPress function
		$user_data = array(
			'ID'           => $current_user->ID,
			'display_name' => $display_name,  // Native WP field
			'user_email'   => $user_email,    // Native WP field
			'user_url'     => $user_url,      // Native WP field
			'description'  => $description,   // Native WP field (bio)
		);

		// Log the data we're trying to save
		error_log( 'Attempting to update user with data: ' . print_r( $user_data, true ) );

		// Update user profile
		$updated = wp_update_user( $user_data );

		// Log the result
		error_log( 'Update result: ' . print_r( $updated, true ) );

		if ( is_wp_error( $updated ) ) {
			error_log( 'Update failed with error: ' . $updated->get_error_message() );
			$errors[] = $updated->get_error_message();
			set_transient( 'fanfic_profile_errors_' . $current_user->ID, $errors, 60 );

			// Check if AJAX request
			$is_ajax = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

			if ( $is_ajax ) {
				wp_send_json_error(
					array(
						'message' => $updated->get_error_message(),
					)
				);
			} else {
				wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
				exit;
			}
		}

		error_log( 'User updated successfully. User ID: ' . $updated );

		// Update custom avatar URL (non-native field)
		if ( ! empty( $avatar_url ) ) {
			update_user_meta( $current_user->ID, '_fanfic_avatar_url', $avatar_url );
		} else {
			delete_user_meta( $current_user->ID, '_fanfic_avatar_url' );
		}

		// Clear any cached user data
		clean_user_cache( $current_user->ID );

		// Check if this is an AJAX request
		$is_ajax = ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest';

		if ( $is_ajax ) {
			// Return JSON response for AJAX
			error_log( 'AJAX request detected - returning JSON' );
			wp_send_json_success(
				array(
					'message' => __( 'Profile updated successfully!', 'fanfiction-manager' ),
				)
			);
		} else {
			// Regular form submission - redirect
			$redirect_url = add_query_arg( 'updated', 'success', wp_get_referer() ? wp_get_referer() : home_url() );
			error_log( 'Regular submission - Redirecting to: ' . $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}
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
		$redirect_url = add_query_arg( 'story_deleted', 'success', self::get_page_url_with_fallback( 'manage-stories' ) );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle delete chapter action
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_delete_chapter() {
		if ( ! isset( $_POST['fanfic_delete_chapter_submit'] ) ) {
			return;
		}

		$chapter_id = isset( $_POST['fanfic_chapter_id'] ) ? absint( $_POST['fanfic_chapter_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_delete_chapter_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_delete_chapter_nonce'], 'fanfic_delete_chapter_' . $chapter_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );

		// Check permissions
		if ( ! $chapter || ( $chapter->post_author != $current_user->ID && ! current_user_can( 'delete_others_posts' ) ) ) {
			return;
		}

		// Delete the chapter
		wp_delete_post( $chapter_id, true );

		// Redirect to manage stories with success message
		$redirect_url = add_query_arg( 'chapter_deleted', 'success', self::get_page_url_with_fallback( 'manage-stories' ) );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for deleting a chapter
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_delete_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_delete_chapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to delete chapters.', 'fanfiction-manager' ) ) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter ID.', 'fanfiction-manager' ) ) );
		}

		$current_user = wp_get_current_user();
		$chapter = get_post( $chapter_id );

		// Check if chapter exists
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Chapter not found.', 'fanfiction-manager' ) ) );
		}

		// Get the story to check permissions
		$story = get_post( $chapter->post_parent );
		if ( ! $story ) {
			wp_send_json_error( array( 'message' => __( 'Parent story not found.', 'fanfiction-manager' ) ) );
		}

		// Check permissions - must be author or have delete_others_posts capability
		if ( $story->post_author != $current_user->ID && ! current_user_can( 'delete_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this chapter.', 'fanfiction-manager' ) ) );
		}

		// Get chapter type
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );

		// Check if this is the last chapter/prologue (epilogues don't count)
		$is_last_publishable_chapter = false;
		if ( in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other chapters/prologues (exclude epilogues and the one being deleted)
			$other_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $story->ID,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
			) );

			if ( empty( $other_chapters ) ) {
				$is_last_publishable_chapter = true;
				
				// Auto-draft the story
				wp_update_post( array(
					'ID'          => $story->ID,
					'post_status' => 'draft',
				) );
				
				error_log( 'Auto-drafted story ' . $story->ID . ' because last chapter/prologue was deleted' );
			}
		}

		// Delete the chapter
		$result = wp_delete_post( $chapter_id, true );

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Chapter deleted successfully.', 'fanfiction-manager' ),
				'chapter_id' => $chapter_id,
				'story_auto_drafted' => $is_last_publishable_chapter,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete chapter.', 'fanfiction-manager' ) ) );
		}
	}

	/**
	 * AJAX handler to check if chapter is the last publishable one
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_check_last_chapter() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_delete_chapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		$chapter_id = isset( $_POST['chapter_id'] ) ? absint( $_POST['chapter_id'] ) : 0;

		if ( ! $chapter_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chapter ID.', 'fanfiction-manager' ) ) );
		}

		$chapter = get_post( $chapter_id );
		if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Chapter not found.', 'fanfiction-manager' ) ) );
		}

		// Get chapter type
		$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
		$is_last = false;

		// Only check for prologue/chapter (epilogues don't count)
		if ( in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
			// Count other chapters/prologues
			$other_chapters = get_posts( array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $chapter->post_parent,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $chapter_id ),
				'meta_query'     => array(
					array(
						'key'     => '_fanfic_chapter_type',
						'value'   => array( 'prologue', 'chapter' ),
						'compare' => 'IN',
					),
				),
			) );

			$is_last = empty( $other_chapters );
		}

		wp_send_json_success( array(
			'is_last_chapter' => $is_last,
			'chapter_type' => $chapter_type,
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

		// Update story status to publish
		$result = wp_update_post( array(
			'ID'          => $story_id,
			'post_status' => 'publish',
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Story published successfully.', 'fanfiction-manager' ),
			'story_id' => $story_id
		) );
	}

	/**
	 * Get available chapter numbers for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $exclude_chapter_id Optional. Chapter ID to exclude (for editing).
	 * @return array Available chapter numbers.
	 */
	private static function get_available_chapter_numbers( $story_id, $exclude_chapter_id = 0 ) {
		// Get existing chapters
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( $exclude_chapter_id ) {
			$args['post__not_in'] = array( $exclude_chapter_id );
		}

		$chapters = get_posts( $args );

		// Get used chapter numbers
		$used_numbers = array();
		foreach ( $chapters as $chapter_id ) {
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			if ( $chapter_number ) {
				$used_numbers[] = absint( $chapter_number );
			}
		}

		// Generate available numbers (1-100)
		$available_numbers = array();
		for ( $i = 1; $i <= 100; $i++ ) {
			if ( ! in_array( $i, $used_numbers ) || ( $exclude_chapter_id && get_post_meta( $exclude_chapter_id, '_fanfic_chapter_number', true ) == $i ) ) {
				$available_numbers[] = $i;
			}
		}

		return $available_numbers;
	}

	/**
	 * Get prologue number for a story
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Prologue number (always 0).
	 */
	private static function get_next_prologue_number( $story_id ) {
		// Prologue is always number 0
		return 0;
	}

	/**
	 * Get epilogue number for a story
	 *
	 * Epilogues start from 1000. If there's a conflict with an existing chapter
	 * (e.g., a story with 1000+ chapters), the epilogue number is incremented
	 * until a free number is found.
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @return int Epilogue number (1000 or higher if conflict exists).
	 */
	private static function get_next_epilogue_number( $story_id ) {
		// Get all existing chapter numbers
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$chapters = get_posts( $args );

		// Get used chapter numbers
		$used_numbers = array();
		foreach ( $chapters as $chapter_id ) {
			$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
			if ( $chapter_number ) {
				$used_numbers[] = absint( $chapter_number );
			}
		}

		// Start from 1000 and find the first available number
		$epilogue_number = 1000;
		while ( in_array( $epilogue_number, $used_numbers ) ) {
			$epilogue_number++;
		}

		return $epilogue_number;
	}

	/**
	 * Check if a story already has a prologue
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $exclude_chapter_id Optional. Chapter ID to exclude (for editing).
	 * @return bool True if prologue exists, false otherwise.
	 */
	private static function story_has_prologue( $story_id, $exclude_chapter_id = 0 ) {
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_fanfic_chapter_type',
					'value' => 'prologue',
				),
			),
		);

		if ( $exclude_chapter_id ) {
			$args['post__not_in'] = array( $exclude_chapter_id );
		}

		$prologues = get_posts( $args );
		return ! empty( $prologues );
	}

	/**
	 * Check if a story already has an epilogue
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID.
	 * @param int $exclude_chapter_id Optional. Chapter ID to exclude (for editing).
	 * @return bool True if epilogue exists, false otherwise.
	 */
	private static function story_has_epilogue( $story_id, $exclude_chapter_id = 0 ) {
		$args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_fanfic_chapter_type',
					'value' => 'epilogue',
				),
			),
		);

		if ( $exclude_chapter_id ) {
			$args['post__not_in'] = array( $exclude_chapter_id );
		}

		$epilogues = get_posts( $args );
		return ! empty( $epilogues );
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
		$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( $_POST['fanfic_story_image'] ) : '';

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
			'post_content' => $introduction,
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

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
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
		$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( $_POST['fanfic_story_image'] ) : '';

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
			'post_content' => $introduction,
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

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
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
	 * AJAX handler for editing user profile
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_edit_profile() {
		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_profile_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_profile_nonce'], 'fanfic_edit_profile_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'fanfiction-manager' )
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in to edit your profile.', 'fanfiction-manager' )
			) );
		}

		$current_user = wp_get_current_user();
		$errors = array();

		// Get and sanitize form data
		$display_name = isset( $_POST['fanfic_display_name'] ) ? sanitize_text_field( $_POST['fanfic_display_name'] ) : '';
		$bio = isset( $_POST['fanfic_bio'] ) ? wp_kses_post( $_POST['fanfic_bio'] ) : '';
		$website = isset( $_POST['fanfic_website'] ) ? esc_url_raw( $_POST['fanfic_website'] ) : '';

		// Validate
		if ( empty( $display_name ) ) {
			$errors[] = __( 'Display name is required.', 'fanfiction-manager' );
		}

		// If errors, return error response
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $errors )
			) );
		}

		// Update user
		$updated = wp_update_user( array(
			'ID'           => $current_user->ID,
			'display_name' => $display_name,
			'user_url'     => $website,
		) );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array(
				'message' => $updated->get_error_message()
			) );
		}

		// Update bio
		update_user_meta( $current_user->ID, 'description', $bio );

		// Return success response (no redirect for profile edit)
		wp_send_json_success( array(
			'message' => __( 'Profile updated successfully!', 'fanfiction-manager' )
		) );
	}
	/**
	 * Filter chapters by parent story status
	 *
	 * Hides chapters whose parent story is in draft status from frontend queries.
	 * This ensures that chapters are automatically hidden when their story is drafted
	 * and automatically visible when the story is republished.
	 *
	 * Uses SQL JOIN for optimal performance instead of multiple get_posts() calls.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public static function filter_chapters_by_story_status( $query ) {
		// Only filter on frontend (not admin)
		if ( is_admin() ) {
			return;
		}

		// Only filter main queries for fanfiction_chapter post type
		if ( ! $query->is_main_query() || $query->get( 'post_type' ) !== 'fanfiction_chapter' ) {
			return;
		}

		// Set a flag so our JOIN and WHERE filters know to apply
		$query->set( 'fanfic_filter_by_parent_status', true );
	}

	/**
	 * Add JOIN clause to check parent story status
	 *
	 * Joins the posts table with itself to access parent post data.
	 * Only applies when fanfic_filter_by_parent_status flag is set.
	 *
	 * @since 1.0.0
	 * @param string   $join  The JOIN clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 * @return string Modified JOIN clause.
	 */
	public static function filter_chapters_join( $join, $query ) {
		global $wpdb;

		// Only apply if our flag is set
		if ( ! $query->get( 'fanfic_filter_by_parent_status' ) ) {
			return $join;
		}

		// JOIN with parent posts table to check parent status
		$join .= " INNER JOIN {$wpdb->posts} AS parent_story ON {$wpdb->posts}.post_parent = parent_story.ID";

		return $join;
	}

	/**
	 * Add WHERE clause to exclude chapters with draft parent stories
	 *
	 * Filters out chapters whose parent story has post_status = 'draft'.
	 * Only applies when fanfic_filter_by_parent_status flag is set.
	 *
	 * @since 1.0.0
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 * @return string Modified WHERE clause.
	 */
	public static function filter_chapters_where( $where, $query ) {
		// Only apply if our flag is set
		if ( ! $query->get( 'fanfic_filter_by_parent_status' ) ) {
			return $where;
		}

		// Exclude chapters whose parent story is draft
		$where .= " AND parent_story.post_status = 'publish'";

		return $where;
	}


	/**
	 * Get error message HTML
	 *
	 * @param string $message Error message
	 * @return string HTML error message
	 */
	private static function get_error_message( $message ) {
		return sprintf(
			'<div class="fanfic-message fanfic-error" role="alert">%s</div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Get success message HTML
	 *
	 * @param string $message Success message
	 * @return string HTML success message
	 */
	private static function get_success_message( $message ) {
		return sprintf(
			'<div class="fanfic-message fanfic-success" role="alert">%s</div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Render error display list
	 *
	 * @param array $errors Array of error messages
	 * @return void (echoes HTML)
	 */
	private static function render_error_display( $errors ) {
		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}
		?>
		<div class="fanfic-message fanfic-error" role="alert">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Check if a story has chapters
	 *
	 * @param int $story_id Story ID
	 * @return bool True if story has chapters, false otherwise
	 */
	private static function story_has_chapters( $story_id ) {
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
