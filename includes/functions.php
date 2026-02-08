<?php
/**
 * Helper Functions
 *
 * Global helper functions for the fanfiction manager plugin.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin version
 *
 * @return string Plugin version
 */
function fanfic_get_version() {
	return FANFIC_VERSION;
}

/**
 * Get the blocked story message
 *
 * Shows specific reason and timestamp if available.
 *
 * @since 1.0.0
 * @param int $story_id Story post ID (optional).
 * @return string
 */
function fanfic_get_blocked_story_message( $story_id = 0 ) {
	if ( ! $story_id ) {
		return __( 'This story has been blocked. If you believe this is a mistake, please contact the site administrator.', 'fanfiction-manager' );
	}

	$block_type   = get_post_meta( $story_id, '_fanfic_block_type', true );
	$block_reason = get_post_meta( $story_id, '_fanfic_block_reason', true );
	$blocked_at   = get_post_meta( $story_id, '_fanfic_blocked_timestamp', true );

	$message = '';

	// Format timestamp
	$timestamp_text = '';
	if ( $blocked_at ) {
		$timestamp_text = ' ' . sprintf(
			__( 'on %s', 'fanfiction-manager' ),
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $blocked_at )
		);
	}

	// Build message based on block type
	switch ( $block_type ) {
		case 'ban':
			$message = sprintf(
				__( 'This story was blocked%s because the author\'s account has been suspended.', 'fanfiction-manager' ),
				$timestamp_text
			);
			break;

		case 'rule':
			$message = sprintf(
				__( 'This story was automatically set to draft%s because site content rules have changed. %s', 'fanfiction-manager' ),
				$timestamp_text,
				$block_reason ? $block_reason : ''
			);
			break;

		case 'manual':
		default:
			if ( $block_reason ) {
				$message = sprintf(
					__( 'Your story was blocked%s because: %s', 'fanfiction-manager' ),
					$timestamp_text,
					$block_reason
				);
			} else {
				$message = sprintf(
					__( 'This story was blocked%s. If you believe this is a mistake, please contact the site administrator.', 'fanfiction-manager' ),
					$timestamp_text
				);
			}
			break;
	}

	return $message;
}

/**
 * Check if user is a fanfiction author
 *
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user is a fanfiction author
 */
function fanfic_is_author( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user = get_userdata( $user_id );
	return $user && in_array( 'fanfiction_author', (array) $user->roles, true );
}

/**
 * Check if user is a fanfiction moderator
 *
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user is a fanfiction moderator
 */
function fanfic_is_moderator( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user = get_userdata( $user_id );
	return $user && in_array( 'fanfiction_moderator', (array) $user->roles, true );
}

/**
 * Get custom table name with proper prefix
 *
 * @param string $table_name Table name without prefix
 * @return string Full table name with prefix
 */
function fanfic_get_table_name( $table_name ) {
	global $wpdb;
	return $wpdb->prefix . 'fanfic_' . $table_name;
}

/**
 * Sanitize story content (allow basic HTML)
 *
 * @param string $content Content to sanitize
 * @return string Sanitized content
 */
function fanfic_sanitize_content( $content ) {
	$allowed_html = array(
		'p'      => array( 'class' => array() ),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'blockquote' => array( 'class' => array() ),
		'hr'     => array(),
	);

	return wp_kses( $content, $allowed_html );
}

/**
 * Check if current request is in edit mode
 *
 * Checks for ?action=edit or ?edit query parameter.
 * NOTE: This is a display flag only. Always verify nonces and permissions
 * before processing any edit operations.
 *
 * @since 1.0.0
 * @return bool True if in edit mode
 */
function fanfic_is_edit_mode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display purposes
	if ( isset( $_GET['action'] ) && 'edit' === sanitize_key( $_GET['action'] ) ) {
		return true;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display purposes
	if ( isset( $_GET['edit'] ) ) {
		return true;
	}

	return false;
}

/**
 * Get edit URL for a story
 *
 * Appends ?action=edit to the story URL.
 *
 * @since 1.0.0
 * @param int|WP_Post $story Story ID or post object
 * @return string|false Edit URL or false on failure
 */
function fanfic_get_story_edit_url( $story ) {
	$story = get_post( $story );

	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return false;
	}

	$story_url = get_permalink( $story->ID );
	return add_query_arg( 'action', 'edit', $story_url );
}

/**
 * Get edit URL for a chapter
 *
 * Appends ?action=edit to the chapter URL.
 *
 * @since 1.0.0
 * @param int|WP_Post $chapter Chapter ID or post object
 * @return string|false Edit URL or false on failure
 */
function fanfic_get_chapter_edit_url( $chapter ) {
	$chapter = get_post( $chapter );

	if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
		return false;
	}

	$chapter_url = get_permalink( $chapter->ID );
	return add_query_arg( 'action', 'edit', $chapter_url );
}

/**
 * Get edit URL for a user profile
 *
 * Appends ?action=edit to the profile URL.
 *
 * @since 1.0.0
 * @param int $user_id User ID (defaults to current user)
 * @return string|false Edit profile URL or false on failure
 */
function fanfic_get_profile_edit_url( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'profile', $user_id );
}

/**
 * Check if current user can edit the given content
 *
 * Verifies permissions for editing stories, chapters, or profiles.
 *
 * @since 1.0.0
 * @param string $content_type Type of content: 'story', 'chapter', or 'profile'
 * @param int    $content_id   ID of the content to edit
 * @return bool True if user can edit
 */
function fanfic_current_user_can_edit( $content_type, $content_id ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	// Administrators and moderators can edit anything
	if ( current_user_can( 'manage_options' ) || current_user_can( 'moderate_fanfiction' ) ) {
		return true;
	}

	switch ( $content_type ) {
		case 'story':
		case 'chapter':
			$post = get_post( $content_id );
			if ( ! $post ) {
				return false;
			}
			// Authors can edit their own content
			return absint( $post->post_author ) === $user_id;

		case 'profile':
			// Users can edit their own profile
			return absint( $content_id ) === $user_id;

		default:
			return false;
	}
}

// ============================================================================
// URL HELPER FUNCTIONS
// Thin wrappers around Fanfic_URL_Manager for template convenience
// ============================================================================

/**
 * Get URL for a system or dynamic page by key
 *
 * @param string $page_key The page key (e.g., 'dashboard', 'login', 'create-story').
 * @param array  $args Optional. Query parameters to add to the URL.
 * @return string The page URL, or empty string if page not found.
 */
function fanfic_get_page_url( $page_key, $args = array() ) {
	return Fanfic_URL_Manager::get_instance()->get_page_url( $page_key, $args );
}

/**
 * Get current URL safely (works for virtual pages too)
 *
 * @return string Current URL.
 */
function fanfic_get_current_url() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( empty( $request_uri ) ) {
		return home_url( '/' );
	}
	return home_url( $request_uri );
}

/**
 * Get image upload settings
 *
 * @return array Settings with enabled, max_bytes, max_value, max_unit.
 */
function fanfic_get_image_upload_settings() {
	$settings = get_option( 'fanfic_settings', array() );
	$enabled = ! empty( $settings['enable_image_uploads'] );
	$max_value = isset( $settings['image_upload_max_value'] ) ? absint( $settings['image_upload_max_value'] ) : 1;
	$max_unit = isset( $settings['image_upload_max_unit'] ) ? sanitize_key( $settings['image_upload_max_unit'] ) : 'mb';

	if ( $max_value < 1 ) {
		$max_value = 1;
	}

	if ( ! in_array( $max_unit, array( 'kb', 'mb' ), true ) ) {
		$max_unit = 'mb';
	}

	$max_bytes = ( 'mb' === $max_unit ) ? $max_value * MB_IN_BYTES : $max_value * KB_IN_BYTES;
	$max_bytes = 0; // Disable size limits; WordPress handles resizing.

	return array(
		'enabled'   => $enabled,
		'max_bytes' => $max_bytes,
		'max_value' => $max_value,
		'max_unit'  => $max_unit,
	);
}

/**
 * Handle an image upload from a form field
 *
 * @param string $file_key Upload field name.
 * @param string $context_label Context label for error messages.
 * @param array  $errors Errors array to append to.
 * @return array|null Upload result with url and attachment_id, or null if no file.
 */
function fanfic_handle_image_upload( $file_key, $context_label, &$errors ) {
	$settings = fanfic_get_image_upload_settings();
	if ( empty( $settings['enabled'] ) ) {
		return null;
	}

	if ( empty( $_FILES[ $file_key ] ) || ! is_array( $_FILES[ $file_key ] ) ) {
		return null;
	}

	$file = $_FILES[ $file_key ];
	if ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === $file['error'] ) {
		return null;
	}

	if ( ! empty( $file['error'] ) ) {
		$errors[] = sprintf(
			/* translators: %s: context label */
			__( 'File upload failed for %s.', 'fanfiction-manager' ),
			$context_label
		);
		return null;
	}

	if ( ! empty( $settings['max_bytes'] ) && $settings['max_bytes'] > 0 && ! empty( $file['size'] ) && $file['size'] > $settings['max_bytes'] ) {
		$errors[] = sprintf(
			/* translators: 1: context label, 2: max size */
			__( '%1$s exceeds the maximum size of %2$s.', 'fanfiction-manager' ),
			$context_label,
			( 'mb' === $settings['max_unit'] ? $settings['max_value'] . ' MB' : $settings['max_value'] . ' KB' )
		);
		return null;
	}

	$allowed_mimes = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
	);

	$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
	if ( empty( $filetype['type'] ) ) {
		$errors[] = sprintf(
			/* translators: %s: context label */
			__( 'Invalid image type for %s. Allowed types: JPG, PNG, GIF, WEBP.', 'fanfiction-manager' ),
			$context_label
		);
		return null;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$upload = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		)
	);

	if ( isset( $upload['error'] ) ) {
		$errors[] = $upload['error'];
		return null;
	}

	// --- Begin Image Optimization ---
	if ( ! empty( $upload['file'] ) ) {
		$image_editor = wp_get_image_editor( $upload['file'] );

		if ( ! is_wp_error( $image_editor ) ) {
			// 1. Resize if necessary
			$max_dimension = 1024;
			$size = $image_editor->get_size();
			if ( $size['width'] > $max_dimension || $size['height'] > $max_dimension ) {
				$image_editor->resize( $max_dimension, $max_dimension, false );
			}

			// 2. Convert to WebP
			$path_info = pathinfo( $upload['file'] );
			$new_filename = $path_info['filename'] . '.webp';
			$new_filepath = trailingslashit( $path_info['dirname'] ) . $new_filename;

			// Save the processed image as WebP
			$saved_image = $image_editor->save( $new_filepath, 'image/webp' );

			if ( ! is_wp_error( $saved_image ) && file_exists( $saved_image['path'] ) ) {
				// 3. Update upload info to point to the new WebP file
				// First, delete the original file
				unlink( $upload['file'] );

				// Then, update the upload array
				$upload['file'] = $saved_image['path'];
				$upload['url'] = str_replace( $path_info['basename'], $new_filename, $upload['url'] );
				$upload['type'] = 'image/webp';

				// Update the file name in the original $_FILES array for consistency
				$_FILES[ $file_key ]['name'] = $new_filename;
			}
		}
	}
	// --- End Image Optimization ---

	$attachment_id = 0;
	if ( ! empty( $upload['file'] ) && ! empty( $upload['type'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( $file['name'] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( $attachment_id ) {
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
		}
	}

	return array(
		'url'           => isset( $upload['url'] ) ? $upload['url'] : '',
		'attachment_id' => $attachment_id,
	);
}

/**
 * Override avatar URL with the user-uploaded avatar if present
 *
 * @param string $url Current avatar URL.
 * @param mixed  $id_or_email User ID, email, or object.
 * @param array  $args Avatar args.
 * @return string Avatar URL.
 */
function fanfic_get_local_avatar_url( $url, $id_or_email, $args ) {
	$user = null;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( $id_or_email instanceof WP_User ) {
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$user = $id_or_email->user_id ? get_user_by( 'id', absint( $id_or_email->user_id ) ) : null;
	} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	}

	if ( $user ) {
		$avatar_url = get_user_meta( $user->ID, '_fanfic_avatar_url', true );
		if ( ! empty( $avatar_url ) ) {
			return esc_url_raw( $avatar_url );
		}
	}

	return $url;
}
add_filter( 'get_avatar_url', 'fanfic_get_local_avatar_url', 10, 3 );

/**
 * Get URL for the story archive (all stories)
 *
 * @return string The story archive URL.
 */
function fanfic_get_story_archive_url() {
	return get_post_type_archive_link( 'fanfiction_story' );
}

/**
 * Get URL for a single story
 *
 * @param int $story_id The story post ID.
 * @return string The story URL, or empty string if invalid.
 */
function fanfic_get_story_url( $story_id ) {
	$story_id = absint( $story_id );
	if ( ! $story_id ) {
		return '';
	}
	return Fanfic_URL_Manager::get_instance()->get_story_url( $story_id );
}

/**
 * Get URL for a single chapter
 *
 * @param int $chapter_id The chapter post ID.
 * @return string The chapter URL, or empty string if invalid.
 */
function fanfic_get_chapter_url( $chapter_id ) {
	$chapter_id = absint( $chapter_id );
	if ( ! $chapter_id ) {
		return '';
	}
	return Fanfic_URL_Manager::get_instance()->get_chapter_url( $chapter_id );
}

/**
 * Get URL for a taxonomy archive
 *
 * @param string         $taxonomy The taxonomy name (e.g., 'fanfiction_genre').
 * @param string|int|object $term The term slug, ID, or object.
 * @return string The taxonomy archive URL, or empty string if invalid.
 */
function fanfic_get_taxonomy_url( $taxonomy, $term ) {
	$term_link = get_term_link( $term, $taxonomy );
	return is_wp_error( $term_link ) ? '' : $term_link;
}

/**
 * Get URL for an author's story archive
 *
 * @param int $author_id The author user ID.
 * @return string The author archive URL.
 */
function fanfic_get_author_url( $author_id ) {
	if ( ! $author_id ) {
		return '';
	}

	return add_query_arg(
		array(
			'post_type' => 'fanfiction_story',
			'author'    => $author_id,
		),
		home_url( '/' )
	);
}

/**
 * Get user profile URL
 *
 * @param mixed $user User ID, username, or WP_User object.
 * @return string User profile URL.
 */
function fanfic_get_user_profile_url( $user ) {
	if ( empty( $user ) ) {
		return fanfic_get_page_url( 'members' );
	}

	return Fanfic_URL_Manager::get_instance()->get_user_profile_url( $user );
}

/**
 * Get URL for the main/home page
 *
 * @return string The main page URL.
 */
function fanfic_get_main_url() {
	return fanfic_get_page_url( 'main' );
}

/**
 * Get URL for the dashboard page
 *
 * @return string The dashboard URL.
 */
function fanfic_get_dashboard_url() {
	return fanfic_get_page_url( 'dashboard' );
}

/**
 * Get URL for the login page
 *
 * @return string The login page URL.
 */
function fanfic_get_login_url() {
	return fanfic_get_page_url( 'login' );
}

/**
 * Get URL for the register page
 *
 * @return string The register page URL.
 */
function fanfic_get_register_url() {
	return fanfic_get_page_url( 'register' );
}

/**
 * Get URL for the password reset page
 *
 * @return string The password reset page URL.
 */
function fanfic_get_password_reset_url() {
	return fanfic_get_page_url( 'password-reset' );
}

/**
 * Get URL for the create story page
 *
 * @return string The create story page URL.
 */
function fanfic_get_create_story_url() {
	return fanfic_get_page_url( 'create-story' );
}

/**
 * Get URL for the stories page
 *
 * @return string The stories page URL.
 */
function fanfic_get_search_url() {
	return fanfic_get_page_url( 'search' );
}

/**
 * Get URL for the members page
 *
 * @return string The members page URL.
 */
function fanfic_get_members_url() {
	return fanfic_get_page_url( 'members' );
}

/**
 * Get URL for the error page
 *
 * @return string The error page URL.
 */
function fanfic_get_error_url() {
	return fanfic_get_page_url( 'error' );
}

/**
 * Get URL for the maintenance page
 *
 * @return string The maintenance page URL.
 */
function fanfic_get_maintenance_url() {
	return fanfic_get_page_url( 'maintenance' );
}

/**
 * Get error message by error code
 *
 * Returns a translatable error message for a given error code.
 *
 * @since 1.0.0
 * @param string $error_code Error code.
 * @return string Error message or empty string if code not found.
 */
function fanfic_get_error_message_by_code( $error_code ) {
	$error_messages = array(
		'invalid_story'      => __( 'The requested story could not be found.', 'fanfiction-manager' ),
		'invalid_chapter'    => __( 'The requested chapter could not be found.', 'fanfiction-manager' ),
		'permission_denied'  => __( 'You do not have permission to access this page.', 'fanfiction-manager' ),
		'not_logged_in'      => __( 'You must be logged in to access this page.', 'fanfiction-manager' ),
		'invalid_user'       => __( 'The requested user profile could not be found.', 'fanfiction-manager' ),
		'validation_failed'  => __( 'The submitted data failed validation. Please check your input and try again.', 'fanfiction-manager' ),
		'save_failed'        => __( 'Failed to save your changes. Please try again.', 'fanfiction-manager' ),
		'delete_failed'      => __( 'Failed to delete the item. Please try again.', 'fanfiction-manager' ),
		'suspended'          => __( 'Your account has been suspended. Please contact the site administrator for more information.', 'fanfiction-manager' ),
		'banned'             => __( 'Your account has been banned. If you believe this is an error, please contact the site administrator.', 'fanfiction-manager' ),
		'invalid_nonce'      => __( 'Security verification failed. Please refresh the page and try again.', 'fanfiction-manager' ),
		'session_expired'    => __( 'Your session has expired. Please log in again.', 'fanfiction-manager' ),
		'database_error'     => __( 'A database error occurred. Please try again later or contact the site administrator.', 'fanfiction-manager' ),
		'file_upload_failed' => __( 'File upload failed. Please check the file size and format, then try again.', 'fanfiction-manager' ),
		'invalid_request'    => __( 'Invalid request. Please check your input and try again.', 'fanfiction-manager' ),
		'rate_limit'         => __( 'You have exceeded the rate limit. Please wait a few minutes and try again.', 'fanfiction-manager' ),
		'maintenance'        => __( 'The site is currently under maintenance. Please try again later.', 'fanfiction-manager' ),
	);

	return isset( $error_messages[ $error_code ] ) ? $error_messages[ $error_code ] : '';
}

/**
 * Get URL for the edit profile page
 *
 * @param int $user_id Optional. User ID to edit. Defaults to current user.
 * @return string The edit profile page URL.
 */
function fanfic_get_edit_profile_url( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}
	
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return '';
	}
	
	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'profile', $user_id );
}

/**
 * Get edit story URL
 *
 * @param int $story_id The story ID to edit.
 * @return string The edit story URL with ?action=edit.
 */
function fanfic_get_edit_story_url( $story_id ) {
	$story_id = absint( $story_id );
	if ( ! $story_id ) {
		return '';
	}
	
	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'story', $story_id );
}

/**
 * Get edit chapter URL
 *
 * @param int $chapter_id The chapter ID to edit (0 for creating new chapter).
 * @param int $story_id   Optional. The story ID when creating a new chapter.
 * @return string The edit chapter URL with ?action=edit or add-chapter.
 */
function fanfic_get_edit_chapter_url( $chapter_id, $story_id = 0 ) {
	$chapter_id = absint( $chapter_id );
	$story_id = absint( $story_id );
	
	// If chapter_id is 0 and story_id is provided, return add-chapter URL
	if ( 0 === $chapter_id && $story_id > 0 ) {
		$story_url = get_permalink( $story_id );
		return add_query_arg( 'action', 'add-chapter', $story_url );
	}

	// Otherwise, return edit chapter URL
	if ( ! $chapter_id ) {
		return '';
	}
	
	return Fanfic_URL_Manager::get_instance()->get_edit_url( 'chapter', $chapter_id );
}

/**
 * Get breadcrumb parent URL
 *
 * Returns the appropriate parent URL based on context.
 *
 * @param int $post_id Optional. The post ID to get parent for.
 * @return string The parent URL.
 */
function fanfic_get_parent_url( $post_id = 0 ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return fanfic_get_main_url();
	}

	$post_type = get_post_type( $post_id );

	switch ( $post_type ) {
		case 'fanfiction_story':
			return fanfic_get_story_archive_url();

		case 'fanfiction_chapter':
			$story_id = wp_get_post_parent_id( $post_id );
			return $story_id ? fanfic_get_story_url( $story_id ) : fanfic_get_story_archive_url();

		case 'page':
			$parent_id = wp_get_post_parent_id( $post_id );
			return $parent_id ? get_permalink( $parent_id ) : fanfic_get_main_url();

		default:
			return fanfic_get_main_url();
	}
}

/**
 * Render universal breadcrumb navigation
 *
 * Generates breadcrumb navigation for all pages in the fanfiction plugin.
 * Always starts with a home icon (⌂) linking to the main page.
 *
 * @since 1.0.11
 * @param string $context The page context. Options:
 *                        - 'dashboard' : Dashboard page
 *                        - 'edit-story' : Add/Edit story page
 *                        - 'edit-chapter' : Add/Edit chapter page
 *                        - 'edit-profile' : Edit profile page
 *                        - 'view-story' : View story page (frontend)
 *                        - 'view-chapter' : View chapter page (frontend)
 *                        - 'view-profile' : View user profile (frontend)
 *                        - 'members' : Members listing page
 *                        - 'stories' : Stories page
 * @param array  $args    Optional. Additional arguments:
 *                        - 'story_id' (int) : Story ID for story/chapter contexts
 *                        - 'story_title' (string) : Story title (optional, will be fetched if not provided)
 *                        - 'chapter_id' (int) : Chapter ID for chapter contexts
 *                        - 'chapter_title' (string) : Chapter title (optional, will be fetched if not provided)
 *                        - 'user_id' (int) : User ID for profile contexts
 *                        - 'username' (string) : Username (optional, will be fetched if not provided)
 *                        - 'is_edit_mode' (bool) : Whether in edit mode (for edit contexts)
 *                        - 'position' (string) : 'top' or 'bottom' (default: 'top')
 * @return void Outputs the breadcrumb HTML
 */
function fanfic_render_breadcrumb( $context, $args = array() ) {
	// Check if breadcrumbs are enabled
	$show_breadcrumbs = get_option( 'fanfic_show_breadcrumbs', '1' );
	if ( '1' !== $show_breadcrumbs ) {
		return; // Breadcrumbs are disabled
	}

	// Default arguments
	$defaults = array(
		'story_id'      => 0,
		'story_title'   => '',
		'chapter_id'    => 0,
		'chapter_title' => '',
		'user_id'       => 0,
		'username'      => '',
		'is_edit_mode'  => false,
		'position'      => 'top',
	);
	$args     = wp_parse_args( $args, $defaults );

	// Build breadcrumb items array
	$items = array();

	// Home icon - always first
	$items[] = array(
		'url'   => fanfic_get_main_url(),
		'label' => '⌂', // U+2302 House symbol
		'class' => 'fanfic-breadcrumb-home',
	);

	// Build context-specific breadcrumbs
	switch ( $context ) {
		case 'dashboard':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Dashboard', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'edit-story':
			$items[] = array(
				'url'   => fanfic_get_dashboard_url(),
				'label' => __( 'Dashboard', 'fanfiction-manager' ),
			);

			if ( $args['is_edit_mode'] && $args['story_id'] ) {
				// Get story title if not provided
				if ( empty( $args['story_title'] ) ) {
					$story               = get_post( $args['story_id'] );
					$args['story_title'] = $story ? $story->post_title : '';
				}

				$items[] = array(
					'url'   => get_permalink( $args['story_id'] ),
					'label' => $args['story_title'],
				);
				$items[] = array(
					'url'    => '',
					'label'  => __( 'Edit', 'fanfiction-manager' ),
					'active' => true,
				);
			} else {
				$items[] = array(
					'url'    => '',
					'label'  => __( 'Create Story', 'fanfiction-manager' ),
					'active' => true,
				);
			}
			break;

		case 'edit-chapter':
			$items[] = array(
				'url'   => fanfic_get_dashboard_url(),
				'label' => __( 'Dashboard', 'fanfiction-manager' ),
			);

			// Get story info
			if ( $args['story_id'] ) {
				if ( empty( $args['story_title'] ) ) {
					$story               = get_post( $args['story_id'] );
					$args['story_title'] = $story ? $story->post_title : '';
				}

				$items[] = array(
					'url'   => fanfic_get_edit_story_url( $args['story_id'] ),
					'label' => $args['story_title'],
				);
			}

			// Chapter breadcrumb
			if ( $args['is_edit_mode'] && $args['chapter_id'] ) {
				if ( empty( $args['chapter_title'] ) ) {
					$chapter               = get_post( $args['chapter_id'] );
					$args['chapter_title'] = $chapter ? $chapter->post_title : '';
				}

				$items[] = array(
					'url'   => fanfic_get_edit_chapter_url( $args['chapter_id'], $args['story_id'] ),
					'label' => $args['chapter_title'],
				);
				$items[] = array(
					'url'    => '',
					'label'  => __( 'Edit', 'fanfiction-manager' ),
					'active' => true,
				);
			} else {
				$items[] = array(
					'url'    => '',
					'label'  => __( 'Add Chapter', 'fanfiction-manager' ),
					'active' => true,
				);
			}
			break;

		case 'edit-profile':
			$items[] = array(
				'url'   => fanfic_get_dashboard_url(),
				'label' => __( 'Dashboard', 'fanfiction-manager' ),
			);

			$items[] = array(
				'url'    => '',
				'label'  => __( 'Edit Profile', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'view-story':
			$items[] = array(
				'url'   => fanfic_get_story_archive_url(),
				'label' => __( 'Stories', 'fanfiction-manager' ),
			);

			if ( $args['story_id'] ) {
				if ( empty( $args['story_title'] ) ) {
					$story               = get_post( $args['story_id'] );
					$args['story_title'] = $story ? $story->post_title : '';
				}

				$items[] = array(
					'url'    => '',
					'label'  => $args['story_title'],
					'active' => true,
				);
			}
			break;

		case 'view-chapter':
			$items[] = array(
				'url'   => fanfic_get_story_archive_url(),
				'label' => __( 'Stories', 'fanfiction-manager' ),
			);

			// Story breadcrumb
			if ( $args['story_id'] ) {
				if ( empty( $args['story_title'] ) ) {
					$story               = get_post( $args['story_id'] );
					$args['story_title'] = $story ? $story->post_title : '';
				}

				$items[] = array(
					'url'   => get_permalink( $args['story_id'] ),
					'label' => $args['story_title'],
				);
			}

			// Chapter breadcrumb
			if ( $args['chapter_id'] ) {
				if ( empty( $args['chapter_title'] ) ) {
					$chapter               = get_post( $args['chapter_id'] );
					$args['chapter_title'] = $chapter ? $chapter->post_title : '';
				}

				$items[] = array(
					'url'    => '',
					'label'  => $args['chapter_title'],
					'active' => true,
				);
			}
			break;

		case 'view-profile':
			$items[] = array(
				'url'   => fanfic_get_members_url(),
				'label' => __( 'Members', 'fanfiction-manager' ),
			);

			if ( $args['user_id'] ) {
				if ( empty( $args['username'] ) ) {
					$user             = get_userdata( $args['user_id'] );
					$args['username'] = $user ? $user->display_name : '';
				}

				$items[] = array(
					'url'    => '',
					'label'  => $args['username'],
					'active' => true,
				);
			}
			break;

		case 'members':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Members', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'stories':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Stories', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'search':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Browse', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'login':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Login', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'register':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Register', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'password-reset':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Password Reset', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'error':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Error', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		case 'maintenance':
			$items[] = array(
				'url'    => '',
				'label'  => __( 'Maintenance', 'fanfiction-manager' ),
				'active' => true,
			);
			break;

		default:
			// Unknown context, just show home
			break;
	}

	// Add CSS class for bottom positioning
	$nav_class = 'fanfic-breadcrumb';
	if ( $args['position'] === 'bottom' ) {
		$nav_class .= ' fanfic-breadcrumb-bottom';
	}

	// Output breadcrumb HTML
	?>
	<nav class="<?php echo esc_attr( $nav_class ); ?>" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
		<ol class="fanfic-breadcrumb-list">
			<?php foreach ( $items as $item ) : ?>
				<li class="fanfic-breadcrumb-item <?php echo ! empty( $item['class'] ) ? esc_attr( $item['class'] ) : ''; ?> <?php echo ! empty( $item['active'] ) ? 'fanfic-breadcrumb-active' : ''; ?>" <?php echo ! empty( $item['active'] ) ? 'aria-current="page"' : ''; ?>>
					<?php if ( ! empty( $item['url'] ) ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $item['label'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

/**
 * Breadcrumb shortcode handler
 *
 * Allows breadcrumbs to be displayed anywhere using the [fanfic-breadcrumbs] shortcode.
 * The shortcode will automatically detect the current context and display appropriate breadcrumbs.
 *
 * @since 1.0.12
 * @param array $atts Shortcode attributes.
 *                    - 'context' (string) : Force a specific context (optional)
 *                    - 'position' (string) : 'top' or 'bottom' (default: 'top')
 * @return string The breadcrumb HTML
 */
function fanfic_breadcrumb_shortcode( $atts ) {
	// Parse attributes
	$atts = shortcode_atts(
		array(
			'context'  => '',
			'position' => 'top',
		),
		$atts,
		'fanfic-breadcrumbs'
	);

	// Auto-detect context if not provided
	$context = $atts['context'];
	if ( empty( $context ) ) {
		// Try to detect context from current page
		if ( is_admin() ) {
			// In admin area
			global $pagenow;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter check
			if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) ) {
				$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				if ( 'fanfiction' === $page || 'fanfiction-dashboard' === $page ) {
					$context = 'dashboard';
				}
			}
		} else {
			// On frontend
			$post_type = get_post_type();

			if ( 'fanfiction_story' === $post_type ) {
				$context = 'view-story';
			} elseif ( 'fanfiction_chapter' === $post_type ) {
				$context = 'view-chapter';
			} elseif ( is_post_type_archive( 'fanfiction_story' ) ) {
				$context = 'stories';
			} elseif ( function_exists( 'fanfic_is_members_page' ) && fanfic_is_members_page() ) {
				$context = 'members';
			}
		}
	}

	// If we still don't have a context, don't show breadcrumbs
	if ( empty( $context ) ) {
		return '';
	}

	// Build arguments based on context
	$args = array(
		'position' => $atts['position'],
	);

	// Add context-specific arguments
	if ( 'view-story' === $context || 'edit-story' === $context ) {
		$post_id = get_the_ID();
		if ( $post_id ) {
			$args['story_id'] = $post_id;
		}
	} elseif ( 'view-chapter' === $context || 'edit-chapter' === $context ) {
		$post_id = get_the_ID();
		if ( $post_id ) {
			$chapter = get_post( $post_id );
			$args['chapter_id'] = $post_id;
			$args['story_id'] = wp_get_post_parent_id( $post_id );
		}
	}

	// Capture output
	ob_start();
	fanfic_render_breadcrumb( $context, $args );
	return ob_get_clean();
}
add_shortcode( 'fanfic-breadcrumbs', 'fanfic_breadcrumb_shortcode' );

/**
 * Custom comment template callback
 *
 * Displays a single comment with custom HTML structure and accessibility features.
 * Used by wp_list_comments() for displaying comments in fanfiction stories and chapters.
 *
 * @since 1.0.0
 * @param WP_Comment $comment Comment object.
 * @param array      $args    Comment display arguments.
 * @param int        $depth   Comment depth level.
 */
function fanfic_custom_comment_template( $comment, $args, $depth ) {
	$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
	?>
	<<?php echo esc_html( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent', $comment ); ?> role="article" aria-label="<?php echo esc_attr( sprintf( __( 'Comment by %s', 'fanfiction-manager' ), get_comment_author() ) ); ?>">
		<article id="div-comment-<?php comment_ID(); ?>" class="fanfic-comment-body">
			<footer class="fanfic-comment-meta">
				<div class="fanfic-comment-author vcard">
					<?php
					// Display avatar
					if ( 0 !== $args['avatar_size'] ) {
						echo get_avatar(
							$comment,
							$args['avatar_size'],
							'',
							get_comment_author(),
							array( 'class' => 'fanfic-comment-avatar' )
						);
					}
					?>
					<b class="fn" itemprop="author">
						<?php
						// Generate custom author link to plugin member profile
						$author_name = get_comment_author( $comment );
						$author_user_id = $comment->user_id;

						if ( $author_user_id ) {
							// Registered user - link to plugin member profile
							$url_manager = Fanfic_URL_Manager::get_instance();
							$profile_url = $url_manager->get_user_profile_url( $author_user_id );
							echo '<a href="' . esc_url( $profile_url ) . '" class="url" rel="nofollow">' . esc_html( $author_name ) . '</a>';
						} else {
							// Guest comment - just show name (or link to URL if they provided one)
							$author_url = get_comment_author_url( $comment );
							if ( $author_url && 'http://' !== $author_url ) {
								echo '<a href="' . esc_url( $author_url ) . '" class="url" rel="external nofollow ugc">' . esc_html( $author_name ) . '</a>';
							} else {
								echo esc_html( $author_name );
							}
						}
						?>
					</b>
					<span class="says screen-reader-text"><?php esc_html_e( 'says:', 'fanfiction-manager' ); ?></span>
				</div>

				<div class="fanfic-comment-metadata">
					<a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>" class="fanfic-comment-permalink">
						<time datetime="<?php comment_time( 'c' ); ?>" itemprop="datePublished">
							<?php
							/* translators: 1: Comment date, 2: Comment time */
							printf(
								esc_html__( '%1$s at %2$s', 'fanfiction-manager' ),
								esc_html( get_comment_date( '', $comment ) ),
								esc_html( get_comment_time() )
							);
							?>
						</time>
					</a>

					<?php
					// Display edit stamp if comment was edited
					$edited_at = get_comment_meta( $comment->comment_ID, 'fanfic_edited_at', true );
					if ( $edited_at ) :
						?>
						<span class="fanfic-comment-edited">
							<?php esc_html_e( '(edited)', 'fanfiction-manager' ); ?>
						</span>
					<?php endif; ?>

					<?php
					// Display moderation notice if comment is not approved
					if ( '0' === $comment->comment_approved ) :
						?>
						<p class="fanfic-comment-awaiting-moderation">
							<?php esc_html_e( 'Your comment is awaiting moderation.', 'fanfiction-manager' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</footer>

			<div class="fanfic-comment-content" itemprop="text">
				<?php comment_text(); ?>
			</div>

			<div class="fanfic-comment-actions">
				<?php
				// Reply link
				comment_reply_link(
					array_merge(
						$args,
						array(
							'add_below' => 'div-comment',
							'depth'     => $depth,
							'max_depth' => $args['max_depth'],
							'before'    => '<div class="reply">',
							'after'     => '</div>',
						)
					)
				);

				// Report button - only show to logged-in users who are not the comment author
				if ( is_user_logged_in() && get_current_user_id() !== absint( $comment->user_id ) ) :
					?>
					<button
						type="button"
						class="fanfic-report-button comment-report-link"
						data-item-id="<?php echo esc_attr( $comment->comment_ID ); ?>"
						data-item-type="comment"
						aria-label="<?php esc_attr_e( 'Report this comment', 'fanfiction-manager' ); ?>"
					>
						<?php esc_html_e( 'Report', 'fanfiction-manager' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</article>
	<?php
}

/**
 * Clear cached theme detections on theme switch or update
 *
 * @since 1.0.0
 */
function fanfic_clear_theme_detection_cache() {
	$all_keys = get_option( 'fanfic_detection_transients', array() );

	foreach ( $all_keys as $key ) {
		delete_transient( $key );
	}

	// Clear the list
	delete_option( 'fanfic_detection_transients' );
}

// Hook into theme switch and update events
add_action( 'switch_theme', 'fanfic_clear_theme_detection_cache' );
add_action( 'after_switch_theme', 'fanfic_clear_theme_detection_cache' );
add_action( 'upgrader_process_complete', 'fanfic_clear_theme_detection_cache', 10, 0 );

/**
 * Admin action handler to manually clear theme detection cache
 *
 * @since 1.0.0
 */
function fanfic_admin_clear_cache_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'fanfiction-manager' ) );
	}

	check_admin_referer( 'fanfic_clear_cache' );
	fanfic_clear_theme_detection_cache();

	Fanfic_Flash_Messages::add_message( 'success', __( 'Theme detection cache cleared successfully!', 'fanfiction-manager' ) );
	wp_redirect( wp_get_referer() );
	exit;
}
add_action( 'admin_post_fanfic_clear_cache', 'fanfic_admin_clear_cache_handler' );

/**
 * Filter to connect the global content template variable to the template system
 *
 * This bridges the gap between class-fanfic-templates.php (which sets $fanfic_content_template)
 * and fanfiction-page-template.php (which uses the 'fanfic_content_template' filter).
 *
 * @since 1.0.0
 * @param string $template The template name.
 * @return string The content template name from global variable.
 */
function fanfic_get_content_template( $template ) {
	global $fanfic_content_template;

	if ( ! empty( $fanfic_content_template ) ) {
		return $fanfic_content_template;
	}

	return $template;
}
add_filter( 'fanfic_content_template', 'fanfic_get_content_template' );

// ============================================================================
// Story Tags Functions (Phase 2.2 - Core Logic)
// ============================================================================

/**
 * Meta key constants for story tags
 *
 * @since 1.2.0
 */
define( 'FANFIC_META_VISIBLE_TAGS', '_fanfic_visible_tags' );
define( 'FANFIC_META_INVISIBLE_TAGS', '_fanfic_invisible_tags' );

/**
 * Maximum number of visible tags per story
 *
 * @since 1.2.0
 */
define( 'FANFIC_MAX_VISIBLE_TAGS', 5 );

/**
 * Maximum number of invisible tags per story
 *
 * @since 1.2.0
 */
define( 'FANFIC_MAX_INVISIBLE_TAGS', 10 );

/**
 * Sanitize a single tag
 *
 * Converts to lowercase, trims whitespace, and removes special characters.
 *
 * @since 1.2.0
 * @param string $tag Tag to sanitize.
 * @return string Sanitized tag
 */
function fanfic_sanitize_tag( $tag ) {
	if ( ! is_string( $tag ) ) {
		return '';
	}

	// Convert to lowercase
	$tag = strtolower( $tag );

	// Trim whitespace
	$tag = trim( $tag );

	// Remove special characters, allow only alphanumeric, spaces, and hyphens
	$tag = preg_replace( '/[^a-z0-9\s\-]/', '', $tag );

	// Replace multiple spaces/hyphens with single space
	$tag = preg_replace( '/[\s\-]+/', ' ', $tag );

	// Trim again after replacements
	$tag = trim( $tag );

	return $tag;
}

/**
 * Normalize tags array
 *
 * Sanitizes, removes duplicates, and enforces limits.
 *
 * @since 1.2.0
 * @param array|string $tags  Tags to normalize (array or comma-separated string).
 * @param int          $limit Maximum number of tags allowed.
 * @return array Normalized tags array
 */
function fanfic_normalize_tags( $tags, $limit ) {
	// Handle string input (comma-separated)
	if ( is_string( $tags ) ) {
		$tags = explode( ',', $tags );
	}

	// Ensure array
	if ( ! is_array( $tags ) ) {
		return array();
	}

	// Sanitize each tag
	$sanitized = array();
	foreach ( $tags as $tag ) {
		$clean = fanfic_sanitize_tag( $tag );
		if ( ! empty( $clean ) ) {
			$sanitized[] = $clean;
		}
	}

	// Remove duplicates
	$sanitized = array_unique( $sanitized );

	// Reset array keys
	$sanitized = array_values( $sanitized );

	// Enforce limit
	if ( count( $sanitized ) > $limit ) {
		$sanitized = array_slice( $sanitized, 0, $limit );
	}

	return $sanitized;
}

/**
 * Get visible tags for a story
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return array Array of visible tags
 */
function fanfic_get_visible_tags( $story_id ) {
	if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_tags', true ) ) {
		return array();
	}

	$tags = get_post_meta( $story_id, FANFIC_META_VISIBLE_TAGS, true );

	if ( ! is_array( $tags ) ) {
		return array();
	}

	return $tags;
}

/**
 * Get invisible tags for a story
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return array Array of invisible tags
 */
function fanfic_get_invisible_tags( $story_id ) {
	if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_tags', true ) ) {
		return array();
	}

	$tags = get_post_meta( $story_id, FANFIC_META_INVISIBLE_TAGS, true );

	if ( ! is_array( $tags ) ) {
		return array();
	}

	return $tags;
}

/**
 * Get all tags for a story (visible + invisible)
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return array Array with 'visible' and 'invisible' keys
 */
function fanfic_get_all_tags( $story_id ) {
	return array(
		'visible'   => fanfic_get_visible_tags( $story_id ),
		'invisible' => fanfic_get_invisible_tags( $story_id ),
	);
}

/**
 * Save visible tags for a story
 *
 * @since 1.2.0
 * @param int          $story_id Story post ID.
 * @param array|string $tags     Tags to save (array or comma-separated string).
 * @return bool True on success, false on failure
 */
function fanfic_save_visible_tags( $story_id, $tags ) {
	$normalized = fanfic_normalize_tags( $tags, FANFIC_MAX_VISIBLE_TAGS );
	return update_post_meta( $story_id, FANFIC_META_VISIBLE_TAGS, $normalized );
}

/**
 * Save invisible tags for a story
 *
 * @since 1.2.0
 * @param int          $story_id Story post ID.
 * @param array|string $tags     Tags to save (array or comma-separated string).
 * @return bool True on success, false on failure
 */
function fanfic_save_invisible_tags( $story_id, $tags ) {
	$normalized = fanfic_normalize_tags( $tags, FANFIC_MAX_INVISIBLE_TAGS );
	return update_post_meta( $story_id, FANFIC_META_INVISIBLE_TAGS, $normalized );
}

/**
 * Save all tags for a story
 *
 * @since 1.2.0
 * @param int          $story_id       Story post ID.
 * @param array|string $visible_tags   Visible tags.
 * @param array|string $invisible_tags Invisible tags.
 * @return bool True if both saved successfully
 */
function fanfic_save_all_tags( $story_id, $visible_tags, $invisible_tags ) {
	$visible_saved   = fanfic_save_visible_tags( $story_id, $visible_tags );
	$invisible_saved = fanfic_save_invisible_tags( $story_id, $invisible_tags );

	$success = $visible_saved && $invisible_saved;

	if ( $success ) {
		// Trigger search index update
		do_action( 'fanfic_tags_updated', $story_id );
	}

	return $success;
}

/**
 * Delete all tags for a story
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return bool True if both deleted successfully
 */
function fanfic_delete_all_tags( $story_id ) {
	$visible_deleted   = delete_post_meta( $story_id, FANFIC_META_VISIBLE_TAGS );
	$invisible_deleted = delete_post_meta( $story_id, FANFIC_META_INVISIBLE_TAGS );

	return $visible_deleted && $invisible_deleted;
}

/**
 * Render visible tags as HTML
 *
 * @since 1.2.0
 * @param int    $story_id Story post ID.
 * @param string $wrapper  Wrapper element (default: 'div').
 * @param string $class    CSS class for wrapper (default: 'fanfic-tags').
 * @return string HTML output
 */
function fanfic_render_visible_tags( $story_id, $wrapper = 'div', $class = 'fanfic-tags' ) {
	if ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_tags', true ) ) {
		return '';
	}

	$tags = fanfic_get_visible_tags( $story_id );

	if ( empty( $tags ) ) {
		return '';
	}

	$output = '<' . esc_attr( $wrapper ) . ' class="' . esc_attr( $class ) . '">';

	foreach ( $tags as $tag ) {
		$output .= '<span class="fanfic-tag">' . esc_html( $tag ) . '</span>';
	}

	$output .= '</' . esc_attr( $wrapper ) . '>';

	return $output;
}

/**
 * Get combined tag text for search indexing
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return string Space-separated tags
 */
function fanfic_get_tags_for_indexing( $story_id ) {
	$all_tags = fanfic_get_all_tags( $story_id );

	$combined = array_merge(
		$all_tags['visible'],
		$all_tags['invisible']
	);

	return implode( ' ', $combined );
}

// ============================================================================
// Story Block/Ban Functions (Phase 2.4 - Ban/Block Enhancements)
// ============================================================================

/**
 * Block a story manually with a reason
 *
 * @since 1.2.0
 * @param int    $story_id Story post ID.
 * @param string $reason   Block reason.
 * @param int    $actor_id User ID who performed the block (default: current user).
 * @return bool True on success
 */
function fanfic_block_story( $story_id, $reason = '', $actor_id = 0 ) {
	if ( ! $actor_id ) {
		$actor_id = get_current_user_id();
	}

	$story = get_post( $story_id );
	if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
		return false;
	}

	$timestamp = time();

	// Save previous status if not already saved
	$story_status = get_post_status( $story_id );
	if ( $story_status && ! get_post_meta( $story_id, '_fanfic_story_blocked_prev_status', true ) ) {
		update_post_meta( $story_id, '_fanfic_story_blocked_prev_status', $story_status );
	}

	// Set block metadata
	update_post_meta( $story_id, '_fanfic_story_blocked', 1 );
	update_post_meta( $story_id, '_fanfic_block_type', 'manual' );
	update_post_meta( $story_id, '_fanfic_block_reason', $reason );
	update_post_meta( $story_id, '_fanfic_blocked_timestamp', $timestamp );

	// Set to draft
	if ( $story_status && 'draft' !== $story_status ) {
		wp_update_post(
			array(
				'ID'          => $story_id,
				'post_status' => 'draft',
			)
		);
	}

	// Fire action for moderation log
	do_action( 'fanfic_story_blocked', $story_id, $actor_id, 'manual', $reason );

	return true;
}

/**
 * Unblock a story
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @param int $actor_id User ID who performed the unblock (default: current user).
 * @return bool True on success
 */
function fanfic_unblock_story( $story_id, $actor_id = 0 ) {
	if ( ! $actor_id ) {
		$actor_id = get_current_user_id();
	}

	$story = get_post( $story_id );
	if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
		return false;
	}

	// Restore previous status
	$prev_status = get_post_meta( $story_id, '_fanfic_story_blocked_prev_status', true );
	if ( $prev_status ) {
		wp_update_post(
			array(
				'ID'          => $story_id,
				'post_status' => $prev_status,
			)
		);
	}

	// Clean up metadata
	delete_post_meta( $story_id, '_fanfic_story_blocked' );
	delete_post_meta( $story_id, '_fanfic_block_type' );
	delete_post_meta( $story_id, '_fanfic_block_reason' );
	delete_post_meta( $story_id, '_fanfic_blocked_timestamp' );
	delete_post_meta( $story_id, '_fanfic_story_blocked_prev_status' );

	// Fire action for moderation log
	do_action( 'fanfic_story_unblocked', $story_id, $actor_id );

	return true;
}

/**
 * Check if a story is blocked
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return bool True if blocked
 */
function fanfic_is_story_blocked( $story_id ) {
	return (bool) get_post_meta( $story_id, '_fanfic_story_blocked', true );
}

/**
 * Get block information for a story
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return array|false Block info or false if not blocked
 */
function fanfic_get_block_info( $story_id ) {
	if ( ! fanfic_is_story_blocked( $story_id ) ) {
		return false;
	}

	return array(
		'type'      => get_post_meta( $story_id, '_fanfic_block_type', true ),
		'reason'    => get_post_meta( $story_id, '_fanfic_block_reason', true ),
		'timestamp' => get_post_meta( $story_id, '_fanfic_blocked_timestamp', true ),
	);
}

/**
 * Auto-draft stories due to rule changes
 *
 * Used when content restrictions change (e.g., sexual content disabled).
 * Stories are set to draft but NOT blocked, allowing authors to edit and republish.
 *
 * @since 1.2.0
 * @param array  $story_ids Array of story IDs to auto-draft.
 * @param string $reason    Explanation for the rule change.
 * @return int Number of stories auto-drafted
 */
function fanfic_autodraft_for_rule_change( $story_ids, $reason ) {
	if ( empty( $story_ids ) || ! is_array( $story_ids ) ) {
		return 0;
	}

	$count = 0;
	$timestamp = time();

	foreach ( $story_ids as $story_id ) {
		$story = get_post( $story_id );
		if ( ! $story || $story->post_type !== 'fanfiction_story' ) {
			continue;
		}

		$current_status = get_post_status( $story_id );

		// Only auto-draft published stories
		if ( $current_status !== 'publish' ) {
			continue;
		}

		// Set to draft
		wp_update_post(
			array(
				'ID'          => $story_id,
				'post_status' => 'draft',
			)
		);

		// Set rule-change metadata (NOT blocked, just auto-drafted)
		update_post_meta( $story_id, '_fanfic_autodraft_rule_change', 1 );
		update_post_meta( $story_id, '_fanfic_autodraft_reason', $reason );
		update_post_meta( $story_id, '_fanfic_autodraft_timestamp', $timestamp );

		$count++;
	}

	return $count;
}

/**
 * Check if a story was auto-drafted due to rule change
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return bool True if auto-drafted for rule change
 */
function fanfic_is_autodrafted_for_rule( $story_id ) {
	return (bool) get_post_meta( $story_id, '_fanfic_autodraft_rule_change', true );
}

/**
 * Get auto-draft rule change information
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return array|false Auto-draft info or false
 */
function fanfic_get_autodraft_info( $story_id ) {
	if ( ! fanfic_is_autodrafted_for_rule( $story_id ) ) {
		return false;
	}

	return array(
		'reason'    => get_post_meta( $story_id, '_fanfic_autodraft_reason', true ),
		'timestamp' => get_post_meta( $story_id, '_fanfic_autodraft_timestamp', true ),
	);
}

/**
 * Clear auto-draft rule change flag
 *
 * Called when author edits/republishes the story.
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return bool True on success
 */
function fanfic_clear_autodraft_flag( $story_id ) {
	delete_post_meta( $story_id, '_fanfic_autodraft_rule_change' );
	delete_post_meta( $story_id, '_fanfic_autodraft_reason' );
	delete_post_meta( $story_id, '_fanfic_autodraft_timestamp' );
	return true;
}

// ============================================================================
// Browse/Search Helpers (Phase 5)
// ============================================================================

/**
 * Parse a slug list from query param input.
 *
 * Accepts arrays, space-separated, or comma-separated values.
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return string[] Sanitized slug list.
 */
function fanfic_parse_slug_list( $value ) {
	if ( empty( $value ) ) {
		return array();
	}

	$values = array();
	if ( is_array( $value ) ) {
		$values = $value;
	} else {
		$value = sanitize_text_field( wp_unslash( $value ) );
		$values = preg_split( '/[\s,]+/', $value );
	}

	$values = array_map( 'sanitize_title', (array) $values );
	$values = array_filter( array_unique( array_map( 'trim', $values ) ) );

	return array_values( $values );
}

/**
 * Parse warning exclusion list (slug list with optional leading "-").
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return string[] Warning slugs to exclude.
 */
function fanfic_parse_warning_exclusions( $value ) {
	if ( empty( $value ) ) {
		return array();
	}

	$values = array();
	if ( is_array( $value ) ) {
		$values = $value;
	} else {
		$value = sanitize_text_field( wp_unslash( $value ) );
		$values = preg_split( '/[\s,]+/', $value );
	}

	$excluded = array();
	foreach ( (array) $values as $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			continue;
		}

		if ( 0 === strpos( $raw, '-' ) ) {
			$raw = substr( $raw, 1 );
		}

		$slug = sanitize_title( $raw );
		if ( '' !== $slug ) {
			$excluded[] = $slug;
		}
	}

	return array_values( array_unique( $excluded ) );
}

/**
 * Normalize age filter value.
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return string Age value or empty string.
 */
function fanfic_normalize_age_filter( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$value = sanitize_text_field( wp_unslash( $value ) );
	$value = strtoupper( trim( $value ) );
	$allowed = array( 'PG', '13', '16', '18' );

	return in_array( $value, $allowed, true ) ? $value : '';
}

/**
 * Normalize sort option.
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return string Sort value or empty string.
 */
function fanfic_normalize_sort_filter( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$value = sanitize_key( $value );
	$allowed = array( 'updated', 'alphabetical', 'created' );

	return in_array( $value, $allowed, true ) ? $value : '';
}

/**
 * Get normalized stories archive params from a source array.
 *
 * @since 1.2.0
 * @param array|null $source Source array (defaults to $_GET).
 * @return array Normalized params.
 */
function fanfic_get_stories_params( $source = null ) {
	$source = is_array( $source ) ? $source : $_GET;

	$search = '';
	if ( isset( $source['search'] ) ) {
		$search = sanitize_text_field( wp_unslash( $source['search'] ) );
	} elseif ( isset( $source['s'] ) ) {
		$search = sanitize_text_field( wp_unslash( $source['s'] ) );
	}

	// Handle fanfic_story_fandoms[] from autofill (IDs) or fandom from direct input (slugs)
	$fandom_slugs = array();
	if ( ! empty( $source['fanfic_story_fandoms'] ) && class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
		// Convert fandom IDs to slugs (from autofill)
		$fandom_ids = is_array( $source['fanfic_story_fandoms'] ) ? $source['fanfic_story_fandoms'] : array( $source['fanfic_story_fandoms'] );
		foreach ( $fandom_ids as $fandom_id ) {
			$fandom_id = absint( $fandom_id );
			if ( $fandom_id > 0 ) {
				$slug = Fanfic_Fandoms::get_fandom_slug_by_id( $fandom_id );
				if ( $slug ) {
					$fandom_slugs[] = $slug;
				}
			}
		}
	}

	// Warnings mode (include/exclude)
	$warnings_mode = 'exclude'; // Default
	if ( isset( $source['warnings_mode'] ) && in_array( $source['warnings_mode'], array( 'include', 'exclude' ), true ) ) {
		$warnings_mode = sanitize_key( $source['warnings_mode'] );
	}

	// Selected warnings slugs
	$selected_warnings = fanfic_parse_slug_list( $source['warnings_slugs'] ?? '' );

	// Normalize age filter
	$age_filter = fanfic_normalize_age_filter( $source['age'] ?? '' );

	// If warnings mode is 'include' and warnings are selected, age filter is automatically cleared.
	if ( 'include' === $warnings_mode && ! empty( $selected_warnings ) ) {
		$age_filter = '';
	}

	// Parse match_all_filters toggle state
	$match_all_filters = isset( $source['match_all_filters'] ) ? ( '1' === $source['match_all_filters'] ? '1' : '0' ) : '0';

	$params = array(
		'search'           => trim( $search ),
		'genres'           => fanfic_parse_slug_list( $source['genre'] ?? '' ),
		'statuses'         => fanfic_parse_slug_list( $source['status'] ?? '' ),
		'fandoms'          => $fandom_slugs,
		'languages'        => fanfic_parse_slug_list( $source['language'] ?? '' ),
		'warnings_mode'    => $warnings_mode,
		'selected_warnings' => $selected_warnings,
		'age'              => $age_filter,
		'sort'             => fanfic_normalize_sort_filter( $source['sort'] ?? '' ),
		'match_all_filters' => $match_all_filters,
		'custom'           => array(),
	);

	// Parse custom taxonomy params.
	if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
		foreach ( $custom_taxonomies as $taxonomy ) {
			$slug = $taxonomy['slug'];
			if ( isset( $source[ $slug ] ) ) {
				$params['custom'][ $slug ] = fanfic_parse_slug_list( $source[ $slug ] );
			}
		}
	}

	return $params;
}

/**
 * Get warning slugs that exceed a target age rating.
 *
 * @since 1.2.0
 * @param string $age Age filter (PG, 13, 16, 18).
 * @return string[] Warning slugs to exclude.
 */
function fanfic_get_warning_slugs_above_age( $age ) {
	if ( empty( $age ) || ! class_exists( 'Fanfic_Warnings' ) ) {
		return array();
	}

	$priority = array(
		'PG' => 1,
		'13' => 2,
		'16' => 3,
		'18' => 4,
	);

	if ( ! isset( $priority[ $age ] ) ) {
		return array();
	}

	$warnings = Fanfic_Warnings::get_all( true );
	if ( empty( $warnings ) ) {
		return array();
	}

	$limit = $priority[ $age ];
	$excluded = array();
	foreach ( $warnings as $warning ) {
		$min_age = isset( $warning['min_age'] ) ? $warning['min_age'] : '';
		if ( empty( $min_age ) || ! isset( $priority[ $min_age ] ) ) {
			continue;
		}

		if ( $priority[ $min_age ] > $limit ) {
			$excluded[] = $warning['slug'];
		}
	}

	return array_values( array_unique( $excluded ) );
}

/**
 * Get story IDs that contain any of the specified warning slugs.
 *
 * @since 1.2.0
 * @param string[] $warning_slugs Warning slugs to match.
 * @return int[] Story IDs.
 */
function fanfic_get_story_ids_with_warnings( $warning_slugs ) {
	$warning_slugs = array_values( array_unique( array_map( 'sanitize_title', (array) $warning_slugs ) ) );
	if ( empty( $warning_slugs ) ) {
		return array();
	}

	if ( ! class_exists( 'Fanfic_Cache' ) ) {
		$cache_key = '';
	} else {
		$cache_key = Fanfic_Cache::get_key( 'search', 'warnings_' . md5( wp_json_encode( $warning_slugs ) ) );
	}

	if ( ! empty( $cache_key ) ) {
		$cached = Fanfic_Cache::get( $cache_key, null, Fanfic_Cache::SHORT );
		if ( false !== $cached ) {
			return array_map( 'absint', (array) $cached );
		}
	}

	global $wpdb;
	$warnings_table  = $wpdb->prefix . 'fanfic_warnings';
	$relations_table = $wpdb->prefix . 'fanfic_story_warnings';
	$placeholders = implode( ',', array_fill( 0, count( $warning_slugs ), '%s' ) );

	$results = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT r.story_id
			FROM {$relations_table} r
			INNER JOIN {$warnings_table} w ON w.id = r.warning_id
			WHERE w.enabled = 1
			  AND w.slug IN ({$placeholders})",
			$warning_slugs
		)
	);

	$results = array_map( 'absint', (array) $results );

	if ( ! empty( $cache_key ) ) {
		Fanfic_Cache::set( $cache_key, $results, Fanfic_Cache::SHORT );
	}

	return $results;
}

/**
 * Build query args for stories archive.
 *
 * @since 1.2.0
 * @param array $params Normalized stories params.
 * @param int   $paged Current page.
 * @param int   $per_page Posts per page.
 * @return array WP_Query arguments.
 */
function fanfic_build_stories_query_args( $params, $paged = 1, $per_page = 12 ) {
	$params = is_array( $params ) ? $params : array();
	$paged = max( 1, absint( $paged ) );
	$per_page = max( 1, absint( $per_page ) );

	$query_args = array(
		'post_type'           => 'fanfiction_story',
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'paged'               => $paged,
		'ignore_sticky_posts' => true,
	);

	$post__in = null;
	$post__not_in = array();

	// Search index -> candidate IDs
	if ( ! empty( $params['search'] ) && class_exists( 'Fanfic_Search_Index' ) ) {
		$limit = min( 2000, max( 200, $per_page * 20 ) );
		$search_ids = Fanfic_Search_Index::search( $params['search'], $limit );

		if ( empty( $search_ids ) ) {
			$post__in = array( 0 );
		} else {
			$post__in = array_map( 'absint', (array) $search_ids );
			if ( empty( $params['sort'] ) ) {
				$query_args['orderby'] = 'post__in';
			}
		}
	}

	// Handle Warnings (Include/Exclude)
	if ( ! empty( $params['selected_warnings'] ) ) {
		$warning_story_ids = fanfic_get_story_ids_with_warnings( $params['selected_warnings'] );

		if ( 'include' === ( $params['warnings_mode'] ?? 'exclude' ) ) {
			// Include mode: only show stories that have *at least one* of the selected warnings.
			if ( empty( $warning_story_ids ) ) {
				$post__in = array( 0 ); // No stories match
			} else {
				if ( is_array( $post__in ) ) {
					$post__in = array_values( array_intersect( (array) $post__in, $warning_story_ids ) );
					if ( empty( $post__in ) ) {
						$post__in = array( 0 );
					}
				} else {
					$post__in = $warning_story_ids;
				}
			}
			// In "Include" mode, age rating is automatically cleared, so no age-derived exclusions here.
		} else { // Exclude mode (default)
			$excluded_warnings_from_params = (array) $params['selected_warnings'];

			// Add age-derived exclusions if age is set
			if ( ! empty( $params['age'] ) ) {
				$excluded_warnings_from_params = array_merge( $excluded_warnings_from_params, fanfic_get_warning_slugs_above_age( $params['age'] ) );
			}
			$excluded_warnings_from_params = array_values( array_unique( $excluded_warnings_from_params ) );

			if ( ! empty( $excluded_warnings_from_params ) ) {
				$exclude_ids = fanfic_get_story_ids_with_warnings( $excluded_warnings_from_params );
				if ( ! empty( $exclude_ids ) ) {
					$post__not_in = array_merge( (array) $post__not_in, $exclude_ids );
				}
			}
		}
	} elseif ( ! empty( $params['age'] ) ) {
		// Only age-derived exclusions if no explicit warnings are selected and age is present (in exclude mode implicitly)
		$age_excluded_warnings = fanfic_get_warning_slugs_above_age( $params['age'] );
		if ( ! empty( $age_excluded_warnings ) ) {
			$exclude_ids = fanfic_get_story_ids_with_warnings( $age_excluded_warnings );
			if ( ! empty( $exclude_ids ) ) {
				$post__not_in = array_merge( (array) $post__not_in, $exclude_ids );
			}
		}
	}

	// Fandom filters (custom table)
	if ( ! empty( $params['fandoms'] ) && class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
		$fandom_story_ids = Fanfic_Fandoms::get_story_ids_by_fandom_slugs( $params['fandoms'] );
		if ( empty( $fandom_story_ids ) ) {
			$post__in = array( 0 );
		} else {
			$fandom_story_ids = array_map( 'absint', (array) $fandom_story_ids );
			if ( is_array( $post__in ) ) {
				$post__in = array_values( array_intersect( $post__in, $fandom_story_ids ) );
				if ( empty( $post__in ) ) {
					$post__in = array( 0 );
				}
			} else {
				$post__in = $fandom_story_ids;
			}
		}
	}

	// Language filters (custom table)
	if ( ! empty( $params['languages'] ) && class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
		$language_story_ids = Fanfic_Languages::get_story_ids_by_language_slugs( $params['languages'] );
		if ( empty( $language_story_ids ) ) {
			$post__in = array( 0 );
		} else {
			$language_story_ids = array_map( 'absint', (array) $language_story_ids );
			if ( is_array( $post__in ) ) {
				$post__in = array_values( array_intersect( $post__in, $language_story_ids ) );
				if ( empty( $post__in ) ) {
					$post__in = array( 0 );
				}
			} else {
				$post__in = $language_story_ids;
			}
		}
	}

	// Custom taxonomy filters (custom tables)
	if ( ! empty( $params['custom'] ) && class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		foreach ( $params['custom'] as $taxonomy_slug => $term_slugs ) {
			if ( empty( $term_slugs ) ) {
				continue;
			}
			$custom_story_ids = Fanfic_Custom_Taxonomies::get_story_ids_by_term_slugs( $taxonomy_slug, $term_slugs );
			if ( empty( $custom_story_ids ) ) {
				$post__in = array( 0 );
				break;
			} else {
				$custom_story_ids = array_map( 'absint', (array) $custom_story_ids );
				if ( is_array( $post__in ) ) {
					$post__in = array_values( array_intersect( $post__in, $custom_story_ids ) );
					if ( empty( $post__in ) ) {
						$post__in = array( 0 );
						break;
					}
				} else {
					$post__in = $custom_story_ids;
				}
			}
		}
	}

	$match_all_filters = ( $params['match_all_filters'] ?? '0' ) === '1'; // Get toggle state

	// Taxonomy filters (genre/status/language/custom)
	$tax_query = array();

	// Genres
	if ( ! empty( $params['genres'] ) ) {
		$genre_query = array(
			'taxonomy' => 'fanfiction_genre',
			'field'    => 'slug',
			'terms'    => $params['genres'],
		);
		if ( $match_all_filters ) {
			$genre_query['operator'] = 'AND'; // Match ALL selected genres
		}
		$tax_query[] = $genre_query;
	}

	// Statuses (usually single select, but kept consistent)
	if ( ! empty( $params['statuses'] ) ) {
		$status_query = array(
			'taxonomy' => 'fanfiction_status',
			'field'    => 'slug',
			'terms'    => $params['statuses'],
		);
		// Apply AND operator if match_all_filters is true.
		// Note: For single-select fields where statuses is an array of one element, 'AND' operator
		// is effectively similar to 'IN' if there's only one term. It primarily impacts
		// scenarios where multiple statuses could somehow be selected or if the field
		// was configured as multi-select at WP Taxonomy level.
		if ( $match_all_filters ) {
			$status_query['operator'] = 'AND'; // Match ALL selected statuses
		}
		$tax_query[] = $status_query;
	}
	
	// Languages
	if ( ! empty( $params['languages'] ) ) {
		$language_query = array(
			'taxonomy' => 'fanfiction_language', // Assuming 'fanfiction_language' is the taxonomy slug
			'field'    => 'slug',
			'terms'    => $params['languages'],
		);
		if ( $match_all_filters ) {
			$language_query['operator'] = 'AND'; // Match ALL selected languages
		}
		$tax_query[] = $language_query;
	}

	// Custom Taxonomies
	if ( ! empty( $params['custom'] ) && class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		foreach ( $params['custom'] as $taxonomy_slug => $term_slugs ) {
			if ( empty( $term_slugs ) ) {
				continue;
			}
			$custom_taxonomy_config = Fanfic_Custom_Taxonomies::get_taxonomy_by_slug( $taxonomy_slug );
			// Only apply AND operator for multi-select custom taxonomies
			if ( $custom_taxonomy_config && 'single' !== $custom_taxonomy_config['selection_type'] ) {
				 $custom_tax_query = array(
					'taxonomy' => $taxonomy_slug,
					'field'    => 'slug',
					'terms'    => $term_slugs,
				);
				if ( $match_all_filters ) {
					$custom_tax_query['operator'] = 'AND'; // Match ALL selected custom terms
				}
				$tax_query[] = $custom_tax_query;
			} else { // Single select custom taxonomies, or if selection_type is not defined.
				$tax_query[] = array(
					'taxonomy' => $taxonomy_slug,
					'field'    => 'slug',
					'terms'    => $term_slugs,
				);
			}
		}
	}


	if ( ! empty( $tax_query ) ) {
		$tax_query['relation'] = 'AND'; // Relation *between* different taxonomies remains AND
		$query_args['tax_query'] = $tax_query;
	}

	if ( is_array( $post__in ) ) {
		$query_args['post__in'] = array_map( 'absint', $post__in );
	}

	if ( ! empty( $post__not_in ) ) {
		$query_args['post__not_in'] = array_values( array_unique( array_map( 'absint', $post__not_in ) ) );
	}

	// Sorting
	if ( ! empty( $params['sort'] ) ) {
		switch ( $params['sort'] ) {
			case 'updated':
				$query_args['orderby'] = 'modified';
				$query_args['order'] = 'DESC';
				break;
			case 'alphabetical':
				$query_args['orderby'] = 'title';
				$query_args['order'] = 'ASC';
				break;
			case 'created':
				$query_args['orderby'] = 'date';
				$query_args['order'] = 'DESC';
				break;
		}
	} elseif ( empty( $query_args['orderby'] ) ) {
		$query_args['orderby'] = 'modified';
		$query_args['order'] = 'DESC';
	}

	return $query_args;
}

/**
 * Build stories query args for URLs.
 *
 * @since 1.2.0
 * @param array $params Normalized stories params.
 * @return array Query args.
 */
function fanfic_build_stories_url_args( $params ) {
	$params = is_array( $params ) ? $params : array();
	$args = array();

	if ( ! empty( $params['search'] ) ) {
		$args['search'] = $params['search'];
	}
	if ( ! empty( $params['genres'] ) ) {
		$args['genre'] = implode( ' ', $params['genres'] );
	}
	if ( ! empty( $params['statuses'] ) ) {
		$args['status'] = implode( ' ', $params['statuses'] );
	}
	if ( ! empty( $params['fandoms'] ) ) {
		$args['fandom'] = implode( ' ', $params['fandoms'] );
	}
	if ( ! empty( $params['languages'] ) ) {
		$args['language'] = implode( ' ', $params['languages'] );
	}
	if ( ! empty( $params['warnings_mode'] ) && 'exclude' !== $params['warnings_mode'] ) { // Only add if not default 'exclude'
		$args['warnings_mode'] = $params['warnings_mode'];
	}
	if ( ! empty( $params['selected_warnings'] ) ) {
		$args['warnings_slugs'] = implode( ' ', $params['selected_warnings'] );
	}
	if ( ! empty( $params['age'] ) ) {
		$args['age'] = $params['age'];
	}
	if ( ! empty( $params['sort'] ) ) {
		$args['sort'] = $params['sort'];
	}

	// Custom taxonomies.
	if ( ! empty( $params['custom'] ) && is_array( $params['custom'] ) ) {
		foreach ( $params['custom'] as $taxonomy_slug => $term_slugs ) {
			if ( ! empty( $term_slugs ) ) {
				$args[ $taxonomy_slug ] = implode( ' ', $term_slugs );
			}
		}
	}

	return $args;
}

/**
 * Build a stories URL with overrides.
 *
 * @since 1.2.0
 * @param string $base_url Base URL.
 * @param array  $params Normalized stories params.
 * @param array  $overrides Overrides to apply (null removes).
 * @return string URL.
 */
function fanfic_build_stories_url( $base_url, $params, $overrides = array() ) {
	$params = is_array( $params ) ? $params : array();
	$overrides = is_array( $overrides ) ? $overrides : array();

	foreach ( $overrides as $key => $value ) {
		if ( null === $value || '' === $value || array() === $value ) {
			unset( $params[ $key ] );
		} else {
			$params[ $key ] = $value;
		}
	}

	$args = fanfic_build_stories_url_args( $params );

	return ! empty( $args ) ? add_query_arg( $args, $base_url ) : $base_url;
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

	$author_id = $story->post_author;
	$author_name = get_the_author_meta( 'display_name', $author_id );
	$story_date = get_the_date( '', $story );
	$story_excerpt = has_excerpt( $story_id ) ? get_the_excerpt( $story ) : wp_trim_words( $story->post_content, 30 );

	$genres = get_the_terms( $story_id, 'fanfiction_genre' );
	$status_terms = get_the_terms( $story_id, 'fanfiction_status' );
	$status = $status_terms && ! is_wp_error( $status_terms ) ? $status_terms[0]->name : '';

	$word_count = get_post_meta( $story_id, '_fanfic_word_count', true );
	$chapters = function_exists( 'ffm_get_story_chapter_count' ) ? ffm_get_story_chapter_count( $story_id ) : 0;

	$age_badge = '';
	if ( class_exists( 'Fanfic_Warnings' ) ) {
		$story_warnings = Fanfic_Warnings::get_story_warnings( $story_id );
		$age_priority = array( 'PG' => 1, '13' => 2, '16' => 3, '18' => 4 );
		$highest_age = 'PG';
		$highest_priority = 1;

		if ( ! empty( $story_warnings ) ) {
			foreach ( $story_warnings as $warning ) {
				if ( ! empty( $warning['min_age'] ) && isset( $age_priority[ $warning['min_age'] ] ) ) {
					$warning_priority = $age_priority[ $warning['min_age'] ];
					if ( $warning_priority > $highest_priority ) {
						$highest_priority = $warning_priority;
						$highest_age = $warning['min_age'];
					}
				}
			}
		}

		$age_badge = sprintf(
			'<span class="fanfic-age-badge fanfic-age-badge-%1$s" aria-label="%2$s">%3$s+</span>',
			esc_attr( sanitize_title( $highest_age ) ),
			esc_attr( sprintf( __( 'Age rating: %s', 'fanfiction-manager' ), $highest_age ) ),
			esc_html( $highest_age )
		);
	}

	$visible_tags = array();
	if ( function_exists( 'fanfic_get_visible_tags' ) ) {
		$visible_tags = fanfic_get_visible_tags( $story_id );
	}

	ob_start();
	?>
	<article id="story-<?php echo esc_attr( $story_id ); ?>" <?php post_class( 'fanfic-story-card', $story_id ); ?>>
		<?php if ( has_post_thumbnail( $story_id ) ) : ?>
			<div class="fanfic-story-card-image">
				<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>">
					<?php echo get_the_post_thumbnail( $story_id, 'medium', array( 'loading' => 'lazy' ) ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="fanfic-story-card-content">
			<header class="fanfic-story-card-header">
				<h2 class="fanfic-story-card-title">
					<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>"><?php echo esc_html( get_the_title( $story_id ) ); ?></a>
				</h2>

				<div class="fanfic-story-card-meta">
					<span class="fanfic-story-author">
						<?php
						printf(
							/* translators: %s: author name */
							esc_html__( 'by %s', 'fanfiction-manager' ),
							'<a href="' . esc_url( fanfic_get_user_profile_url( $author_id ) ) . '">' . esc_html( $author_name ) . '</a>'
						);
						?>
					</span>
					<span class="fanfic-story-date"><?php echo esc_html( $story_date ); ?></span>
					<?php if ( $status ) : ?>
						<span class="fanfic-story-status fanfic-status-<?php echo esc_attr( sanitize_title( $status ) ); ?>">
							<?php echo esc_html( $status ); ?>
						</span>
					<?php endif; ?>
					<?php echo wp_kses_post( $age_badge ); ?>
				</div>
			</header>

			<div class="fanfic-story-card-excerpt">
				<?php echo wp_kses_post( $story_excerpt ); ?>
			</div>

			<footer class="fanfic-story-card-footer">
				<?php if ( $genres && ! is_wp_error( $genres ) ) : ?>
					<div class="fanfic-story-genres">
						<?php foreach ( $genres as $genre ) : ?>
							<a href="<?php echo esc_url( get_term_link( $genre ) ); ?>" class="fanfic-genre-tag">
								<?php echo esc_html( $genre->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $visible_tags ) ) : ?>
					<div class="fanfic-story-tags">
						<?php foreach ( $visible_tags as $tag ) : ?>
							<span class="fanfic-tag"><?php echo esc_html( $tag ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php echo do_shortcode( '[story-taxonomies]' ); ?>

				<div class="fanfic-story-stats">
					<?php if ( $word_count ) : ?>
						<span class="fanfic-stat">
							<span class="dashicons dashicons-edit"></span>
							<?php echo esc_html( number_format_i18n( $word_count ) ); ?> <?php esc_html_e( 'words', 'fanfiction-manager' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $chapters ) : ?>
						<span class="fanfic-stat">
							<span class="dashicons dashicons-book"></span>
							<?php echo esc_html( number_format_i18n( $chapters ) ); ?> <?php echo esc_html( _n( 'chapter', 'chapters', $chapters, 'fanfiction-manager' ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			</footer>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Build active filter pill data for stories pages.
 *
 * @since 1.2.0
 * @param array  $params Normalized stories params.
 * @param string $base_url Base URL for links.
 * @return array[] Array of filters with label and url.
 */
function fanfic_build_active_filters( $params, $base_url ) {
	$params = is_array( $params ) ? $params : array();
	$filters = array();

	if ( ! empty( $params['search'] ) ) {
		$filters[] = array(
			'label' => sprintf( __( 'Search: "%s"', 'fanfiction-manager' ), $params['search'] ),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'search' => null, 'paged' => null ) ),
		);
	}

	foreach ( (array) $params['genres'] as $slug ) {
		$term = get_term_by( 'slug', $slug, 'fanfiction_genre' );
		if ( $term ) {
			$new_values = array_values( array_diff( $params['genres'], array( $slug ) ) );
			$filters[] = array(
				'label' => sprintf( __( 'Genre: %s', 'fanfiction-manager' ), $term->name ),
				'url'   => fanfic_build_stories_url( $base_url, $params, array( 'genres' => $new_values, 'paged' => null ) ),
			);
		}
	}

	foreach ( (array) $params['statuses'] as $slug ) {
		$term = get_term_by( 'slug', $slug, 'fanfiction_status' );
		if ( $term ) {
			$new_values = array_values( array_diff( $params['statuses'], array( $slug ) ) );
			$filters[] = array(
				'label' => sprintf( __( 'Status: %s', 'fanfiction-manager' ), $term->name ),
				'url'   => fanfic_build_stories_url( $base_url, $params, array( 'statuses' => $new_values, 'paged' => null ) ),
			);
		}
	}

	foreach ( (array) $params['fandoms'] as $slug ) {
		$new_values = array_values( array_diff( $params['fandoms'], array( $slug ) ) );
		$filters[] = array(
			'label' => sprintf( __( 'Fandom: %s', 'fanfiction-manager' ), $slug ),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'fandoms' => $new_values, 'paged' => null ) ),
		);
	}

	if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
		foreach ( (array) $params['languages'] as $slug ) {
			$language = Fanfic_Languages::get_by_slug( $slug );
			$label = $language ? $language['name'] : $slug;
			$new_values = array_values( array_diff( $params['languages'], array( $slug ) ) );
			$filters[] = array(
				'label' => sprintf( __( 'Language: %s', 'fanfiction-manager' ), $label ),
				'url'   => fanfic_build_stories_url( $base_url, $params, array( 'languages' => $new_values, 'paged' => null ) ),
			);
		}
	}

	$warning_map = array();
	if ( class_exists( 'Fanfic_Warnings' ) ) {
		$warnings = Fanfic_Warnings::get_available_warnings();
		foreach ( $warnings as $warning ) {
			if ( ! empty( $warning['slug'] ) ) {
				$warning_map[ $warning['slug'] ] = $warning['name'];
			}
		}
	}

	// Warnings logic
	if ( ! empty( $params['selected_warnings'] ) ) {
		$warnings_mode_label = ( 'include' === ( $params['warnings_mode'] ?? 'exclude' ) )
			? __( 'Include', 'fanfiction-manager' )
			: __( 'Exclude', 'fanfiction-manager' );

		foreach ( (array) $params['selected_warnings'] as $slug ) {
			$label = isset( $warning_map[ $slug ] ) ? $warning_map[ $slug ] : $slug;
			$new_selected_warnings = array_values( array_diff( $params['selected_warnings'], array( $slug ) ) );
			$filters[] = array(
				'label' => sprintf( '%s: %s', $warnings_mode_label, $label ),
				'url'   => fanfic_build_stories_url( $base_url, $params, array( 'selected_warnings' => $new_selected_warnings, 'paged' => null ) ),
			);
		}
	}

	// Age filter pill, skip if warnings are included and selected warnings exist
	if ( ! empty( $params['age'] ) && ! ( 'include' === ( $params['warnings_mode'] ?? 'exclude' ) && ! empty( $params['selected_warnings'] ) ) ) {
		$filters[] = array(
			'label' => sprintf( __( 'Age: %s+', 'fanfiction-manager' ), $params['age'] ),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'age' => null, 'paged' => null ) ),
		);
	}

	if ( ! empty( $params['sort'] ) ) {
		$sort_labels = array(
			'updated'      => __( 'Updated', 'fanfiction-manager' ),
			'alphabetical' => __( 'Alphabetical', 'fanfiction-manager' ),
			'created'      => __( 'Created', 'fanfiction-manager' ),
		);
		$filters[] = array(
			'label' => sprintf( __( 'Sort: %s', 'fanfiction-manager' ), $sort_labels[ $params['sort'] ] ?? $params['sort'] ),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'sort' => null, 'paged' => null ) ),
		);
	}

	// Custom taxonomies.
	if ( ! empty( $params['custom'] ) && class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		foreach ( $params['custom'] as $taxonomy_slug => $term_slugs ) {
			if ( empty( $term_slugs ) ) {
				continue;
			}
			$taxonomy = Fanfic_Custom_Taxonomies::get_taxonomy_by_slug( $taxonomy_slug );
			if ( ! $taxonomy ) {
				continue;
			}
			foreach ( (array) $term_slugs as $slug ) {
				$term = Fanfic_Custom_Taxonomies::get_term_by_slug( $taxonomy['id'], $slug );
				$label = $term ? $term['name'] : $slug;
				$new_values = array_values( array_diff( $term_slugs, array( $slug ) ) );
				$new_custom = $params['custom'];
				$new_custom[ $taxonomy_slug ] = $new_values;
				$filters[] = array(
					'label' => sprintf( '%s: %s', $taxonomy['name'], $label ),
					'url'   => fanfic_build_stories_url( $base_url, $params, array( 'custom' => $new_custom, 'paged' => null ) ),
				);
			}
		}
	}

	return $filters;
}

/**
 * Check if the current stories request is in "browse all terms" mode.
 *
 * Detects if any taxonomy parameter is set to "all" (e.g., ?genre=all).
 *
 * @since 1.2.0
 * @return bool True if browsing all terms of a taxonomy.
 */
function fanfic_is_stories_all_terms_mode() {
	$taxonomy = fanfic_get_stories_all_taxonomy();
	return ! empty( $taxonomy );
}

/**
 * Get the taxonomy being browsed in "all terms" mode.
 *
 * Returns the taxonomy key and type when a parameter is set to "all".
 *
 * @since 1.2.0
 * @return array|null Array with 'key', 'type', and 'label', or null if not in browse all mode.
 */
function fanfic_get_stories_all_taxonomy() {
	$source = $_GET;

	// Check built-in taxonomies.
	$taxonomies = array(
		'genre'    => array(
			'type'  => 'wp_taxonomy',
			'label' => __( 'Genres', 'fanfiction-manager' ),
			'tax'   => 'fanfiction_genre',
		),
		'status'   => array(
			'type'  => 'wp_taxonomy',
			'label' => __( 'Statuses', 'fanfiction-manager' ),
			'tax'   => 'fanfiction_status',
		),
		'fandom'   => array(
			'type'  => 'light_taxonomy',
			'label' => __( 'Fandoms', 'fanfiction-manager' ),
		),
		'language' => array(
			'type'  => 'light_taxonomy',
			'label' => __( 'Languages', 'fanfiction-manager' ),
		),
	);

	foreach ( $taxonomies as $key => $config ) {
		if ( isset( $source[ $key ] ) && is_string( $source[ $key ] ) && 'all' === strtolower( trim( $source[ $key ] ) ) ) {
			return array_merge( array( 'key' => $key ), $config );
		}
	}

	// Check warnings.
	if ( isset( $source['warning'] ) && is_string( $source['warning'] ) && 'all' === strtolower( trim( $source['warning'] ) ) ) {
		return array(
			'key'   => 'warning',
			'type'  => 'warnings',
			'label' => __( 'Warnings', 'fanfiction-manager' ),
		);
	}

	// Check custom taxonomies.
	if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
		foreach ( $custom_taxonomies as $taxonomy ) {
			$slug = $taxonomy['slug'];
			if ( isset( $source[ $slug ] ) && is_string( $source[ $slug ] ) && 'all' === strtolower( trim( $source[ $slug ] ) ) ) {
				return array(
					'key'   => $slug,
					'type'  => 'custom_taxonomy',
					'label' => $taxonomy['name'],
					'id'    => $taxonomy['id'],
				);
			}
		}
	}

	return null;
}

/**
 * Get taxonomy terms with story counts for stories all mode.
 *
 * Results are cached for 1 hour for performance.
 *
 * @since 1.2.0
 * @param array $taxonomy_config Taxonomy configuration from fanfic_get_stories_all_taxonomy().
 * @return array List of terms with 'name', 'slug', 'count', and 'url'.
 */
function fanfic_get_taxonomy_terms_with_counts_for_stories_all( $taxonomy_config ) {
	if ( empty( $taxonomy_config ) ) {
		return array();
	}

	// Try to get from cache
	$cache_key = '';
	if ( class_exists( 'Fanfic_Cache' ) ) {
		$cache_key = Fanfic_Cache::get_key( 'browse_all_terms', $taxonomy_config['key'] );
		$cached = Fanfic_Cache::get( $cache_key, null, Fanfic_Cache::MEDIUM );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
	}

	$terms = array();
	$base_url = function_exists( 'fanfic_get_page_url' ) ? fanfic_get_page_url( 'search' ) : '';
	if ( empty( $base_url ) ) {
		$base_url = home_url( '/' );
	}

	$type = $taxonomy_config['type'];
	$key = $taxonomy_config['key'];

	// Handle WordPress taxonomies.
	if ( 'wp_taxonomy' === $type ) {
		$wp_terms = get_terms( array(
			'taxonomy'   => $taxonomy_config['tax'],
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		if ( ! is_wp_error( $wp_terms ) && ! empty( $wp_terms ) ) {
			foreach ( $wp_terms as $term ) {
				$terms[] = array(
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => $term->count,
					'url'   => add_query_arg( $key, $term->slug, $base_url ),
				);
			}
		}
	}

	// Handle light taxonomies (fandom, language).
	if ( 'light_taxonomy' === $type ) {
		$light_terms = fanfic_get_light_taxonomy_terms_with_counts( $key );
		foreach ( $light_terms as $term ) {
			$terms[] = array(
				'name'  => $term['name'],
				'slug'  => $term['slug'],
				'count' => $term['count'],
				'url'   => add_query_arg( $key, $term['slug'], $base_url ),
			);
		}
	}

	// Handle warnings.
	if ( 'warnings' === $type && class_exists( 'Fanfic_Warnings' ) ) {
		$all_warnings = Fanfic_Warnings::get_available_warnings();
		foreach ( $all_warnings as $warning ) {
			$count = fanfic_get_warning_story_count( $warning['slug'] );
			if ( $count > 0 ) {
				$terms[] = array(
					'name'  => $warning['name'],
					'slug'  => $warning['slug'],
					'count' => $count,
					'url'   => add_query_arg( 'warning', $warning['slug'], $base_url ),
				);
			}
		}
	}

	// Handle custom taxonomies.
	if ( 'custom_taxonomy' === $type && class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		$custom_terms = Fanfic_Custom_Taxonomies::get_active_terms( $taxonomy_config['id'] );
		foreach ( $custom_terms as $term ) {
			$count = fanfic_get_custom_taxonomy_term_count( $taxonomy_config['id'], $term['slug'] );
			if ( $count > 0 ) {
				$terms[] = array(
					'name'  => $term['name'],
					'slug'  => $term['slug'],
					'count' => $count,
					'url'   => add_query_arg( $key, $term['slug'], $base_url ),
				);
			}
		}
	}

	// Cache the results (1 hour cache)
	if ( ! empty( $cache_key ) ) {
		Fanfic_Cache::set( $cache_key, $terms, Fanfic_Cache::MEDIUM );
	}

	return $terms;
}

/**
 * Get light taxonomy terms with counts (fandom, language).
 *
 * @since 1.2.0
 * @param string $taxonomy_key Taxonomy key (fandom or language).
 * @return array Terms with name, slug, and count.
 */
function fanfic_get_light_taxonomy_terms_with_counts( $taxonomy_key ) {
	global $wpdb;

	// Determine which column to query
	$column = '';
	if ( 'fandom' === $taxonomy_key ) {
		$column = 'fandom_slugs';
	} elseif ( 'language' === $taxonomy_key ) {
		$column = 'language_slug';
	} else {
		return array();
	}

	$table = $wpdb->prefix . 'fanfic_story_search_index';

	if ( 'language' === $taxonomy_key ) {
		// Language is single-value
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT {$column} as slug, COUNT(*) as count
			FROM {$table}
			WHERE story_status = 'publish'
			AND {$column} != ''
			GROUP BY {$column}
			ORDER BY {$column} ASC",
			ARRAY_A
		);
	} else {
		// Fandom is comma-separated (need to split)
		// This uses a numbers table technique to split CSV
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT
				TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(fandom_slugs, ',', numbers.n), ',', -1)) as slug,
				COUNT(*) as count
			FROM {$table}
			CROSS JOIN (
				SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
				UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
				UNION ALL SELECT 9 UNION ALL SELECT 10
			) numbers
			WHERE story_status = 'publish'
			AND fandom_slugs != ''
			AND CHAR_LENGTH(fandom_slugs) - CHAR_LENGTH(REPLACE(fandom_slugs, ',', '')) >= numbers.n - 1
			GROUP BY slug
			HAVING slug != ''
			ORDER BY slug ASC",
			ARRAY_A
		);
	}

	$terms = array();
	foreach ( $results as $row ) {
		$slug  = $row['slug'];
		$count = absint( $row['count'] );

		if ( $count > 0 ) {
			// Get display name
			$name = $slug;
			if ( 'language' === $taxonomy_key && class_exists( 'Fanfic_Languages' ) ) {
				$lang_data = Fanfic_Languages::get_language_by_slug( $slug );
				if ( $lang_data ) {
					$name = $lang_data['name'];
				}
			} else {
				$name = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
			}

			$terms[] = array(
				'name'  => $name,
				'slug'  => $slug,
				'count' => $count,
			);
		}
	}

	return $terms;
}

/**
 * Get story count for a warning.
 *
 * @since 1.2.0
 * @param string $warning_slug Warning slug.
 * @return int Story count.
 */
function fanfic_get_warning_story_count( $warning_slug ) {
	global $wpdb;

	$table = $wpdb->prefix . 'fanfic_story_search_index';

	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$table}
			WHERE story_status = 'publish'
			AND FIND_IN_SET(%s, warning_slugs) > 0",
			$warning_slug
		)
	);

	return absint( $count );
}

/**
 * Get story count for a custom taxonomy term.
 *
 * @since 1.2.0
 * @param int    $taxonomy_id Custom taxonomy ID.
 * @param string $term_slug   Term slug.
 * @return int Story count.
 */
function fanfic_get_custom_taxonomy_term_count( $taxonomy_id, $term_slug ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'fanfic_story_custom_taxonomy';

	$count = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT sct.story_id)
		FROM {$table_name} sct
		INNER JOIN {$wpdb->posts} p ON sct.story_id = p.ID
		WHERE sct.taxonomy_id = %d
		AND sct.term_slug = %s
		AND p.post_type = 'fanfiction_story'
		AND p.post_status = 'publish'",
		$taxonomy_id,
		$term_slug
	) );

	return absint( $count );
}

