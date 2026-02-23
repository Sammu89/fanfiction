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
 * Check if user can manually edit publication dates.
 *
 * Allowed: fanfiction_author, fanfiction_admin, WordPress administrator.
 * Disallowed: fanfiction_moderator-only users.
 *
 * @since 2.1.0
 * @param int $user_id User ID (optional, defaults to current user).
 * @return bool True if user can edit manual publication dates.
 */
function fanfic_can_edit_publish_date( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}

	$roles = (array) $user->roles;
	return in_array( 'administrator', $roles, true )
		|| in_array( 'fanfiction_admin', $roles, true )
		|| in_array( 'fanfiction_author', $roles, true );
}

/**
 * Get required field indicator markup.
 *
 * Reusable helper for labels that need an accessible required marker.
 *
 * @since 2.1.0
 * @param string $screen_reader_text Optional accessible text for assistive tech.
 * @return string Safe HTML markup for required indicator.
 */
function fanfic_get_required_field_indicator_html( $screen_reader_text = '' ) {
	if ( '' === $screen_reader_text ) {
		$screen_reader_text = __( 'Required field', 'fanfiction-manager' );
	}

	return sprintf(
		' <span class="fanfic-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">%s</span>',
		esc_html( $screen_reader_text )
	);
}

/**
 * Output required field indicator markup.
 *
 * @since 2.1.0
 * @param string $screen_reader_text Optional accessible text for assistive tech.
 * @return void
 */
function fanfic_required_field_indicator( $screen_reader_text = '' ) {
	echo fanfic_get_required_field_indicator_html( $screen_reader_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped in helper.
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
	$stories_page_url = function_exists( 'fanfic_get_page_url' ) ? fanfic_get_page_url( 'stories' ) : '';
	if ( ! empty( $stories_page_url ) ) {
		return $stories_page_url;
	}

	$archive_url = get_post_type_archive_link( 'fanfiction_story' );
	return $archive_url ? $archive_url : '';
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
	return fanfic_get_page_url( 'stories' );
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

	// Home icon - always first.
	$homepage_state     = Fanfic_Homepage_State::get_current_state();
	$stories_archive_url = fanfic_get_story_archive_url();
	$stories_is_homepage = (
		'stories_homepage' === $homepage_state['main_page_mode'] &&
		0 === (int) $homepage_state['use_base_slug']
	);
	$home_url = $stories_is_homepage ? $stories_archive_url : fanfic_get_main_url();
	$stories_breadcrumb_url = $stories_is_homepage ? '' : $stories_archive_url;

	$items[] = array(
		'url'   => $home_url,
		'label' => __( 'Home', 'fanfiction-manager' ),
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
				'url'   => $stories_breadcrumb_url,
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
				'url'   => $stories_breadcrumb_url,
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
				<?php
				$item_class   = ! empty( $item['class'] ) ? (string) $item['class'] : '';
				$is_home_item = false !== strpos( $item_class, 'fanfic-breadcrumb-home' );
				$home_icon    = '<span class="dashicons dashicons-admin-home" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Home', 'fanfiction-manager' ) . '</span>';
				?>
				<li class="fanfic-breadcrumb-item <?php echo ! empty( $item['class'] ) ? esc_attr( $item['class'] ) : ''; ?> <?php echo ! empty( $item['active'] ) ? 'fanfic-breadcrumb-active' : ''; ?>" <?php echo ! empty( $item['active'] ) ? 'aria-current="page"' : ''; ?>>
					<?php if ( ! empty( $item['url'] ) ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo $is_home_item ? $home_icon : esc_html( $item['label'] ); ?></a>
					<?php else : ?>
						<?php echo $is_home_item ? $home_icon : esc_html( $item['label'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
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
 * @param string $value Age value.
 * @return float Numeric sort weight.
 */
function fanfic_get_age_filter_sort_weight( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 1000;
	}

	if ( class_exists( 'Fanfic_Warnings' ) ) {
		$priority = Fanfic_Warnings::get_age_priority_map( false );
		if ( isset( $priority[ $value ] ) ) {
			return (float) $priority[ $value ];
		}

		$normalized = Fanfic_Warnings::normalize_age_label( $value, false );
		if ( '' !== $normalized && isset( $priority[ $normalized ] ) ) {
			return (float) $priority[ $normalized ];
		}
	}

	$value = strtoupper( $value );
	if ( preg_match( '/\d+/', $value, $matches ) ) {
		return (float) absint( $matches[0] );
	}

	return 1000;
}

/**
 * Sort age values from least to most restrictive.
 *
 * @since 1.2.0
 * @param string[] $ages Age values.
 * @return string[] Sorted age values.
 */
function fanfic_sort_age_filter_values( $ages ) {
	$ages = array_values( array_unique( array_filter( array_map( 'trim', (array) $ages ) ) ) );
	if ( empty( $ages ) ) {
		return array();
	}

	usort(
		$ages,
		function( $left, $right ) {
			$left_weight = fanfic_get_age_filter_sort_weight( $left );
			$right_weight = fanfic_get_age_filter_sort_weight( $right );
			if ( $left_weight === $right_weight ) {
				return strcmp( (string) $left, (string) $right );
			}
			return ( $left_weight < $right_weight ) ? -1 : 1;
		}
	);

	return $ages;
}

/**
 * Format age label for UI display.
 *
 * Numeric ages are shown as "13+" while no-warning remains "PG".
 *
 * @since 1.2.0
 * @param string $value Age value.
 * @param bool   $infer_default Whether to infer default label for empty values.
 * @return string Display label.
 */
function fanfic_get_age_display_label( $value, $infer_default = true ) {
	$value = trim( (string) $value );

	if ( class_exists( 'Fanfic_Warnings' ) ) {
		$label = Fanfic_Warnings::format_age_label_for_display( $value, $infer_default );
		if ( '' !== $label ) {
			return $label;
		}
	}

	if ( '' === $value ) {
		return $infer_default ? 'PG' : '';
	}

	$numeric = rtrim( $value, '+' );
	if ( is_numeric( $numeric ) ) {
		return (string) ( (int) round( (float) $numeric ) ) . '+';
	}

	return $value;
}

/**
 * Build age badge class from age value.
 *
 * @since 1.2.0
 * @param string $value Age value.
 * @param string $prefix Class prefix.
 * @return string CSS class name.
 */
function fanfic_get_age_badge_class( $value, $prefix = 'fanfic-age-badge-' ) {
	$prefix = trim( (string) $prefix );
	if ( '' === $prefix ) {
		$prefix = 'fanfic-age-badge-';
	}

	if ( class_exists( 'Fanfic_Warnings' ) ) {
		return Fanfic_Warnings::get_age_badge_class( $value, $prefix );
	}

	$numeric = rtrim( trim( (string) $value ), '+' );
	if ( ! is_numeric( $numeric ) ) {
		return $prefix . '3-9';
	}

	$age = (int) round( (float) $numeric );
	if ( $age <= 9 ) {
		return $prefix . '3-9';
	}
	if ( $age <= 12 ) {
		return $prefix . '10-12';
	}
	if ( $age <= 15 ) {
		return $prefix . '13-15';
	}
	if ( $age <= 17 ) {
		return $prefix . '16-17';
	}

	return $prefix . '18-plus';
}

/**
 * Get available age filters from configured data.
 *
 * @since 1.2.0
 * @return string[] Age values.
 */
function fanfic_get_available_age_filters() {
	global $wpdb;

	$ages = array();
	$default_age = '';

	if ( class_exists( 'Fanfic_Warnings' ) ) {
		$default_age = Fanfic_Warnings::get_default_age_label( false );
		if ( '' !== $default_age ) {
			$ages[] = $default_age;
		}
	}

	if ( class_exists( 'Fanfic_Warnings' ) ) {
		$warnings = Fanfic_Warnings::get_available_warnings();
		foreach ( (array) $warnings as $warning ) {
			$age = Fanfic_Warnings::sanitize_age_label( $warning['min_age'] ?? '' );
			if ( '' !== $age ) {
				$ages[] = $age;
			}
		}
	}

	$index_table = $wpdb->prefix . 'fanfic_story_search_index';
	$table_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_table ) );
	if ( $table_ready === $index_table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$index_ages = $wpdb->get_col(
			"SELECT DISTINCT age_rating
			FROM {$index_table}
			WHERE story_status = 'publish'
			  AND age_rating != ''"
		);
		foreach ( (array) $index_ages as $age ) {
			$age = trim( (string) $age );
			if ( class_exists( 'Fanfic_Warnings' ) ) {
				$age = Fanfic_Warnings::normalize_age_label( $age, false );
			}
			if ( '' !== $age ) {
				$ages[] = $age;
			}
		}
	}

	if ( empty( $ages ) && '' !== $default_age ) {
		$ages[] = $default_age;
	}

	return fanfic_sort_age_filter_values( $ages );
}

/**
 * Build age filter alias map.
 *
 * @since 1.2.0
 * @param string[] $ages Canonical ages.
 * @return array<string,string> Alias map.
 */
function fanfic_get_age_filter_alias_map( $ages ) {
	$aliases = array();
	foreach ( (array) $ages as $age ) {
		$canonical = trim( (string) $age );
		if ( '' === $canonical ) {
			continue;
		}
		$upper = strtoupper( $canonical );
		$aliases[ $upper ] = $canonical;
	}

	return $aliases;
}

/**
 * Normalize age filter value.
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return string[] Normalized age values.
 */
function fanfic_normalize_age_filter( $value ) {
	if ( empty( $value ) ) {
		return array();
	}

	$allowed = fanfic_get_available_age_filters();
	if ( empty( $allowed ) ) {
		return array();
	}
	$alias_map = fanfic_get_age_filter_alias_map( $allowed );

	// Support space/comma-separated string or array input.
	$raw = is_array( $value ) ? $value : preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

	$result = array();
	foreach ( $raw as $v ) {
		$v = strtoupper( trim( sanitize_text_field( wp_unslash( $v ) ) ) );
		if ( isset( $alias_map[ $v ] ) ) {
			$result[] = $alias_map[ $v ];
		}
	}

	return array_values( array_unique( $result ) );
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
	if ( isset( $source['q'] ) ) {
		$search = sanitize_text_field( wp_unslash( $source['q'] ) );
	}

	// Handle fandoms from URL.
	// Preferred clean format: ?fandom=slug,slug2
	// Legacy format: ?fandoms=1,2 (IDs) or slugs.
	$fandom_slugs = array();
	if ( ! empty( $source['fandom'] ) ) {
		$fandom_slugs = fanfic_parse_slug_list( $source['fandom'] );
	}
	if ( ! empty( $source['fandoms'] ) ) {
		$raw_values = is_array( $source['fandoms'] )
			? $source['fandoms']
			: preg_split( '/[\s,+]+/', (string) $source['fandoms'], -1, PREG_SPLIT_NO_EMPTY );

		$legacy_ids = array();
		$legacy_slugs = array();

		foreach ( (array) $raw_values as $raw_value ) {
			$raw_value = trim( (string) $raw_value );
			if ( '' === $raw_value ) {
				continue;
			}

			if ( is_numeric( $raw_value ) ) {
				$legacy_id = absint( $raw_value );
				if ( $legacy_id > 0 ) {
					$legacy_ids[] = $legacy_id;
				}
				continue;
			}

			$legacy_slugs[] = $raw_value;
		}

		if ( ! empty( $legacy_ids ) && class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			foreach ( $legacy_ids as $fandom_id ) {
				$slug = Fanfic_Fandoms::get_fandom_slug_by_id( $fandom_id );
				if ( $slug ) {
					$fandom_slugs[] = $slug;
				}
			}
		}

		if ( ! empty( $legacy_slugs ) ) {
			$fandom_slugs = array_merge( $fandom_slugs, fanfic_parse_slug_list( $legacy_slugs ) );
		}
	}
	$fandom_slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', $fandom_slugs ) ) ) );

	// Exclude warnings slugs
	$exclude_warnings = fanfic_parse_slug_list( $source['warnings_exclude'] ?? '' );

	// Include warnings slugs
	$include_warnings = fanfic_parse_slug_list( $source['warnings_include'] ?? '' );

	// Remove any warning that appears in both lists (exclude wins)
	if ( ! empty( $include_warnings ) && ! empty( $exclude_warnings ) ) {
		$include_warnings = array_values( array_diff( $include_warnings, $exclude_warnings ) );
	}

	// Normalize age filter
	$age_filter = fanfic_normalize_age_filter( $source['age'] ?? '' );

	// If include warnings are selected, age filter is automatically cleared.
	if ( ! empty( $include_warnings ) ) {
		$age_filter = array();
	}

	// Parse match_all_filters toggle state
	$match_all_filters = isset( $source['match_all_filters'] ) ? ( '1' === $source['match_all_filters'] ? '1' : '0' ) : '0';

	$params = array(
		'search'           => trim( $search ),
		'genres'           => fanfic_parse_slug_list( $source['genre'] ?? '' ),
		'statuses'         => fanfic_parse_slug_list( $source['status'] ?? '' ),
		'fandoms'          => $fandom_slugs,
		'languages'        => fanfic_get_default_language_filter( $source ),
		'exclude_warnings' => $exclude_warnings,
		'include_warnings' => $include_warnings,
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
 * Get language filter slugs from request source.
 *
 * Returns only explicitly selected languages — no default is applied.
 * The WP site language default is used only in the story edit form,
 * not in search/browse filters.
 *
 * @since 1.2.0
 * @param array $source Request parameters ($_GET or similar).
 * @return string[] Array of language slugs.
 */
function fanfic_get_default_language_filter( $source ) {
	if ( ! empty( $source['language'] ) ) {
		return fanfic_parse_slug_list( $source['language'] );
	}

	return array();
}

/**
 * Get warning slugs that exceed a target age rating.
 *
 * Accepts a single age string or an array of ages. When multiple ages are
 * provided, uses the least restrictive (highest priority) to determine which
 * warnings to exclude.
 *
 * @since 1.2.0
 * @param string|string[] $age Age filter(s).
 * @return string[] Warning slugs to exclude.
 */
function fanfic_get_warning_slugs_above_age( $age ) {
	if ( empty( $age ) || ! class_exists( 'Fanfic_Warnings' ) ) {
		return array();
	}

	$available_ages = fanfic_get_available_age_filters();
	$priority = array();
	$rank = 1;
	foreach ( (array) $available_ages as $age_value ) {
		$priority[ $age_value ] = $rank++;
	}
	if ( empty( $priority ) ) {
		return array();
	}
	$aliases = fanfic_get_age_filter_alias_map( array_keys( $priority ) );

	// Find the least restrictive (highest priority number) among selected ages.
	$ages = (array) $age;
	$limit = 0;
	foreach ( $ages as $a ) {
		$a = strtoupper( trim( (string) $a ) );
		$canonical = $aliases[ $a ] ?? '';
		if ( '' !== $canonical && isset( $priority[ $canonical ] ) && $priority[ $canonical ] > $limit ) {
			$limit = $priority[ $canonical ];
		}
	}

	if ( 0 === $limit ) {
		return array();
	}

	$warnings = Fanfic_Warnings::get_all( true );
	if ( empty( $warnings ) ) {
		return array();
	}

	$excluded = array();
	foreach ( $warnings as $warning ) {
		$min_age = isset( $warning['min_age'] ) ? $warning['min_age'] : '';
		$min_age = trim( (string) $min_age );
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
 * Check if table-driven search runtime tables are available.
 *
 * @since 1.5.2
 * @return bool
 */
function fanfic_search_filter_map_tables_ready() {
	static $ready = null;
	if ( null !== $ready ) {
		return $ready;
	}

	global $wpdb;
	$index_table = $wpdb->prefix . 'fanfic_story_search_index';
	$map_table   = $wpdb->prefix . 'fanfic_story_filter_map';

	$index_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_table ) ) === $index_table;
	$map_ready   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $map_table ) ) === $map_table;

	$ready = ( $index_ready && $map_ready );

	return $ready;
}

/**
 * Get published story IDs by one filter-map facet.
 *
 * @since 1.5.2
 * @param string   $facet_type Facet type.
 * @param string[] $facet_values Facet values.
 * @param bool     $require_all_values Require all selected values.
 * @return int[] Story IDs.
 */
function fanfic_get_story_ids_by_filter_map_facet( $facet_type, $facet_values, $require_all_values = false ) {
	if ( ! fanfic_search_filter_map_tables_ready() ) {
		return array();
	}

	$facet_type = strtolower( trim( sanitize_text_field( (string) $facet_type ) ) );
	$facet_type = preg_replace( '/[^a-z0-9:_-]/', '', $facet_type );
	if ( '' === $facet_type ) {
		return array();
	}

	$facet_values = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $facet_values ) ) ) );
	if ( empty( $facet_values ) ) {
		return array();
	}

	$require_all_values = (bool) $require_all_values && count( $facet_values ) > 1;

	global $wpdb;
	$index_table = $wpdb->prefix . 'fanfic_story_search_index';
	$map_table   = $wpdb->prefix . 'fanfic_story_filter_map';
	$placeholders = implode( ',', array_fill( 0, count( $facet_values ), '%s' ) );

	$sql = "SELECT m.story_id
		FROM {$map_table} m
		INNER JOIN {$index_table} idx ON idx.story_id = m.story_id
		WHERE idx.story_status = 'publish'
		  AND m.facet_type = %s
		  AND m.facet_value IN ({$placeholders})
		GROUP BY m.story_id";

	$args = array_merge( array( $facet_type ), $facet_values );
	if ( $require_all_values ) {
		$sql .= ' HAVING COUNT(DISTINCT m.facet_value) >= %d';
		$args[] = count( $facet_values );
	}

	$results = $wpdb->get_col(
		$wpdb->prepare( $sql, $args )
	);

	$results = array_map( 'absint', (array) $results );

	return $results;
}

/**
 * Intersect an incoming set of IDs into the current candidate set.
 *
 * @since 1.5.2
 * @param int[]|null $current Current candidate set (null means uninitialized).
 * @param int[]      $incoming Incoming IDs to intersect.
 * @return int[] Updated candidate set.
 */
function fanfic_intersect_story_id_sets( $current, $incoming ) {
	$incoming = array_values( array_unique( array_map( 'absint', (array) $incoming ) ) );
	if ( empty( $incoming ) ) {
		return array( 0 );
	}

	if ( ! is_array( $current ) ) {
		return $incoming;
	}

	$intersected = array_values( array_intersect( $current, $incoming ) );

	return empty( $intersected ) ? array( 0 ) : $intersected;
}

/**
 * Get story IDs that contain any of the specified warning slugs.
 *
 * @since 1.2.0
 * @param string[] $warning_slugs Warning slugs to match.
 * @return int[] Story IDs.
 */
function fanfic_get_story_ids_with_warnings( $warning_slugs ) {
	return fanfic_get_story_ids_by_filter_map_facet( 'warning', $warning_slugs, false );
}

/**
 * Sort and paginate story IDs using the search index table.
 *
 * @since 1.9.0
 * @param int[]|null $post_in     Allowed story IDs (null = all published).
 * @param int[]      $post_not_in Excluded story IDs.
 * @param string     $sort        Sort key: updated|created|alphabetical.
 * @param int        $paged       Current page (1-based).
 * @param int        $per_page    Posts per page.
 * @return array{ids: int[], total: int}
 */
function fanfic_sort_story_ids_via_index( $post_in, $post_not_in, $sort, $paged, $per_page ) {
	global $wpdb;

	$table        = $wpdb->prefix . 'fanfic_story_search_index';
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table_exists !== $table ) {
		return array( 'ids' => array(), 'total' => 0 );
	}

	$sort_map = array(
		'updated'      => array( 'column' => 'updated_date', 'direction' => 'DESC' ),
		'created'      => array( 'column' => 'published_date', 'direction' => 'DESC' ),
		'alphabetical' => array( 'column' => 'story_title', 'direction' => 'ASC' ),
	);
	$sort_config  = isset( $sort_map[ $sort ] ) ? $sort_map[ $sort ] : $sort_map['updated'];
	$order_col    = $sort_config['column'];
	$order_dir    = $sort_config['direction'];

	$where_clauses = array( "story_status = 'publish'" );
	$bind_values   = array();

	if ( is_array( $post_in ) && ! empty( $post_in ) ) {
		$placeholders    = implode( ',', array_fill( 0, count( $post_in ), '%d' ) );
		$where_clauses[] = "story_id IN ({$placeholders})";
		$bind_values     = array_merge( $bind_values, $post_in );
	}

	if ( ! empty( $post_not_in ) ) {
		$placeholders    = implode( ',', array_fill( 0, count( $post_not_in ), '%d' ) );
		$where_clauses[] = "story_id NOT IN ({$placeholders})";
		$bind_values     = array_merge( $bind_values, $post_not_in );
	}

	$where_sql = implode( ' AND ', $where_clauses );
	$offset    = ( $paged - 1 ) * $per_page;

	if ( empty( $bind_values ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT story_id FROM {$table} WHERE {$where_sql} ORDER BY {$order_col} {$order_dir} LIMIT %d, %d", $offset, $per_page ) );
	} else {
		$count_values = $bind_values;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $count_values ) );
		$offset_values = array_merge( $bind_values, array( $offset, $per_page ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT story_id FROM {$table} WHERE {$where_sql} ORDER BY {$order_col} {$order_dir} LIMIT %d, %d", $offset_values ) );
	}

	return array(
		'ids'   => array_map( 'absint', (array) $ids ),
		'total' => $total,
	);
}

/**
 * Build query args for stories archive.
 *
 * @since 1.2.0
 * @param array $params Normalized stories params.
 * @param int   $paged Current page.
 * @param int   $per_page Posts per page.
 * @return array{args: array, found_posts: int} Query args and total found posts count.
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

	$post__in     = null;
	$post__not_in = array();
	$search_ids   = array();
	$match_all_filters = ( $params['match_all_filters'] ?? '0' ) === '1';

	// Search index -> candidate IDs
	if ( ! empty( $params['search'] ) && class_exists( 'Fanfic_Search_Index' ) ) {
		$limit = min( 2000, max( 200, $per_page * 20 ) );
		$search_ids = array_map( 'absint', (array) Fanfic_Search_Index::search( $params['search'], $limit ) );

		if ( empty( $search_ids ) ) {
			$post__in = array( 0 );
		} else {
			$post__in = $search_ids;
		}
	}

	// Include warnings: keep stories matching at least one selected warning.
	if ( ! empty( $params['include_warnings'] ) ) {
		$include_warning_ids = fanfic_get_story_ids_with_warnings( $params['include_warnings'] );
		$post__in = fanfic_intersect_story_id_sets( $post__in, $include_warning_ids );
	}

	// Exclude warnings: remove any story that has a selected warning.
	$all_exclude_warning_slugs = ! empty( $params['exclude_warnings'] ) ? (array) $params['exclude_warnings'] : array();
	$all_exclude_warning_slugs = array_values( array_unique( $all_exclude_warning_slugs ) );
	if ( ! empty( $all_exclude_warning_slugs ) ) {
		$exclude_ids = fanfic_get_story_ids_by_filter_map_facet( 'warning', $all_exclude_warning_slugs, false );
		if ( ! empty( $exclude_ids ) ) {
			$post__not_in = array_merge( (array) $post__not_in, $exclude_ids );
		}
	}

	if ( ! empty( $params['genres'] ) ) {
		$genre_ids = fanfic_get_story_ids_by_filter_map_facet(
			'genre',
			$params['genres'],
			$match_all_filters
		);
		$post__in = fanfic_intersect_story_id_sets( $post__in, $genre_ids );
	}

	if ( ! empty( $params['statuses'] ) ) {
		$status_ids = fanfic_get_story_ids_by_filter_map_facet(
			'status',
			$params['statuses'],
			false
		);
		$post__in = fanfic_intersect_story_id_sets( $post__in, $status_ids );
	}

	if ( ! empty( $params['fandoms'] ) ) {
		$fandom_ids = fanfic_get_story_ids_by_filter_map_facet(
			'fandom',
			$params['fandoms'],
			$match_all_filters
		);
		$post__in = fanfic_intersect_story_id_sets( $post__in, $fandom_ids );
	}

	if ( ! empty( $params['languages'] ) ) {
		$language_ids = fanfic_get_story_ids_by_filter_map_facet(
			'language',
			$params['languages'],
			false
		);
		$post__in = fanfic_intersect_story_id_sets( $post__in, $language_ids );
	}

	if ( ! empty( $params['age'] ) ) {
		$age_ids = fanfic_get_story_ids_by_filter_map_facet(
			'age',
			$params['age'],
			false
		);
		$post__in = fanfic_intersect_story_id_sets( $post__in, $age_ids );
	}

	if ( ! empty( $params['custom'] ) && class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		foreach ( $params['custom'] as $taxonomy_slug => $term_slugs ) {
			if ( empty( $term_slugs ) ) {
				continue;
			}
			$taxonomy_slug = sanitize_title( $taxonomy_slug );
			if ( '' === $taxonomy_slug ) {
				continue;
			}

			$custom_taxonomy_config = Fanfic_Custom_Taxonomies::get_taxonomy_by_slug( $taxonomy_slug );
			$require_all_custom = $match_all_filters
				&& $custom_taxonomy_config
				&& 'single' !== ( $custom_taxonomy_config['selection_type'] ?? 'single' );

			$custom_ids = fanfic_get_story_ids_by_filter_map_facet(
				'custom:' . $taxonomy_slug,
				$term_slugs,
				$require_all_custom
			);
			$post__in = fanfic_intersect_story_id_sets( $post__in, $custom_ids );
		}
	}

	if ( is_array( $post__in ) ) {
		$query_args['post__in'] = array_map( 'absint', $post__in );
	}

	if ( ! empty( $post__not_in ) ) {
		$query_args['post__not_in'] = array_values( array_unique( array_map( 'absint', $post__not_in ) ) );
	}

	// Determine sort key. Only use index-sort when not in pure relevance (search text) mode.
	$sort_key = '';
	if ( ! empty( $params['sort'] ) && in_array( $params['sort'], array( 'updated', 'created', 'alphabetical' ), true ) ) {
		$sort_key = $params['sort'];
	} elseif ( empty( $search_ids ) ) {
		$sort_key = 'updated';
	}

	if ( '' !== $sort_key ) {
		// Sort and paginate via search index — avoids wp_posts orderby lookups.
		$sort_result = fanfic_sort_story_ids_via_index(
			is_array( $post__in ) ? $post__in : null,
			$post__not_in,
			$sort_key,
			$paged,
			$per_page
		);

		if ( ! empty( $sort_result['ids'] ) ) {
			$query_args['post__in']       = $sort_result['ids'];
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = count( $sort_result['ids'] );
			$query_args['paged']          = 1;
			$query_args['no_found_rows']  = true;
			unset( $query_args['post__not_in'] );
		} else {
			// No results matching current filters.
			$query_args['post__in'] = array( 0 );
		}

		return array(
			'args'        => $query_args,
			'found_posts' => $sort_result['total'],
		);
	}

	// Fallback: relevance search mode — use WP_Query ordering.
	if ( ! empty( $search_ids ) && is_array( $post__in ) ) {
		$query_args['orderby'] = 'post__in';
	} elseif ( empty( $query_args['orderby'] ) ) {
		$query_args['orderby'] = 'modified';
		$query_args['order']   = 'DESC';
	}

	return array(
		'args'        => $query_args,
		'found_posts' => -1,
	);
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
		$args['q'] = $params['search'];
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
	if ( ! empty( $params['exclude_warnings'] ) ) {
		$args['warnings_exclude'] = implode( ' ', $params['exclude_warnings'] );
	}
	if ( ! empty( $params['include_warnings'] ) ) {
		$args['warnings_include'] = implode( ' ', $params['include_warnings'] );
	}
	if ( ! empty( $params['age'] ) ) {
		$args['age'] = implode( ' ', (array) $params['age'] );
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

	// Exclude warnings pills
	if ( ! empty( $params['exclude_warnings'] ) ) {
		foreach ( (array) $params['exclude_warnings'] as $slug ) {
			$label = isset( $warning_map[ $slug ] ) ? $warning_map[ $slug ] : $slug;
			$new_exclude = array_values( array_diff( $params['exclude_warnings'], array( $slug ) ) );
			$filters[] = array(
				'label' => sprintf( '%s: %s', __( 'Excluding', 'fanfiction-manager' ), $label ),
				'url'   => fanfic_build_stories_url( $base_url, $params, array( 'exclude_warnings' => $new_exclude, 'paged' => null ) ),
			);
		}
	}

	// Include warnings pills
	if ( ! empty( $params['include_warnings'] ) ) {
		foreach ( (array) $params['include_warnings'] as $slug ) {
			$label = isset( $warning_map[ $slug ] ) ? $warning_map[ $slug ] : $slug;
			$new_include = array_values( array_diff( $params['include_warnings'], array( $slug ) ) );
			$filters[] = array(
				'label' => sprintf( '%s: %s', __( 'Including', 'fanfiction-manager' ), $label ),
				'url'   => fanfic_build_stories_url( $base_url, $params, array( 'include_warnings' => $new_include, 'paged' => null ) ),
			);
		}
	}

	// Age filter pills, skip if include warnings are active
	if ( ! empty( $params['age'] ) && empty( $params['include_warnings'] ) ) {
		$age_labels = array();
		foreach ( (array) $params['age'] as $age_value ) {
			$age_label = fanfic_get_age_display_label( $age_value, true );
			if ( '' === $age_label ) {
				$age_label = (string) $age_value;
			}
			$age_labels[] = $age_label;
		}
		$filters[] = array(
			'label' => sprintf( __( 'Age: %s', 'fanfiction-manager' ), implode( ', ', $age_labels ) ),
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
	$base_url = function_exists( 'fanfic_get_page_url' ) ? fanfic_get_page_url( 'stories' ) : '';
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
 * Get counts by facet value from search filter map for published stories.
 *
 * @since 1.5.2
 * @param string $facet_type Facet type.
 * @return array<string,int> Value => count.
 */
function fanfic_get_filter_map_counts_by_facet( $facet_type ) {
	if ( ! fanfic_search_filter_map_tables_ready() ) {
		return array();
	}

	$facet_type = strtolower( trim( sanitize_text_field( (string) $facet_type ) ) );
	$facet_type = preg_replace( '/[^a-z0-9:_-]/', '', $facet_type );
	if ( '' === $facet_type ) {
		return array();
	}

	global $wpdb;
	$index_table = $wpdb->prefix . 'fanfic_story_search_index';
	$map_table   = $wpdb->prefix . 'fanfic_story_filter_map';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.facet_value, COUNT(DISTINCT m.story_id) AS story_count
			FROM {$map_table} m
			INNER JOIN {$index_table} idx ON idx.story_id = m.story_id
			WHERE idx.story_status = 'publish'
			  AND m.facet_type = %s
			GROUP BY m.facet_value",
			$facet_type
		),
		ARRAY_A
	);

	$counts = array();
	foreach ( (array) $rows as $row ) {
		$value = sanitize_title( $row['facet_value'] ?? '' );
		if ( '' === $value ) {
			continue;
		}
		$counts[ $value ] = absint( $row['story_count'] ?? 0 );
	}

	return $counts;
}

/**
 * Get light taxonomy terms with counts (fandom, language).
 *
 * @since 1.2.0
 * @param string $taxonomy_key Taxonomy key (fandom or language).
 * @return array Terms with name, slug, and count.
 */
function fanfic_get_light_taxonomy_terms_with_counts( $taxonomy_key ) {
	$facet_type = '';
	if ( 'fandom' === $taxonomy_key ) {
		$facet_type = 'fandom';
	} elseif ( 'language' === $taxonomy_key ) {
		$facet_type = 'language';
	} else {
		return array();
	}

	$counts = fanfic_get_filter_map_counts_by_facet( $facet_type );
	$terms = array();
	foreach ( $counts as $slug => $count ) {
		$slug = sanitize_title( $slug );
		$count = absint( $count );

		if ( $count > 0 ) {
			// Get display name
			$name = $slug;
			if ( 'language' === $taxonomy_key && class_exists( 'Fanfic_Languages' ) ) {
				$lang_data = Fanfic_Languages::get_by_slug( $slug );
				if ( $lang_data ) {
					$name = $lang_data['name'] ?? $slug;
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
	$warning_slug = sanitize_title( $warning_slug );
	if ( '' === $warning_slug ) {
		return 0;
	}

	$counts = fanfic_get_filter_map_counts_by_facet( 'warning' );
	return absint( $counts[ $warning_slug ] ?? 0 );
}

/**
 * Normalize a filter label key for stable lookups.
 *
 * @since 1.2.0
 * @param string $value Raw label value.
 * @return string Normalized key.
 */
function fanfic_normalize_filter_label_key( $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $value ) {
		return '';
	}

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
}

/**
 * Get CSV-value counts from search index for published stories.
 *
 * @since 1.2.0
 * @param string $column CSV column name in search index.
 * @param int    $max_values Maximum values to split per row.
 * @return array<string,int> Map of value => story count.
 */
function fanfic_get_search_index_csv_counts( $column, $max_values = 20 ) {
	global $wpdb;

	$allowed_columns = array( 'fandom_slugs', 'warning_slugs', 'genre_names' );
	if ( ! in_array( $column, $allowed_columns, true ) ) {
		return array();
	}

	$max_values = max( 1, min( 40, absint( $max_values ) ) );
	$numbers = array();
	for ( $i = 1; $i <= $max_values; $i++ ) {
		$numbers[] = 'SELECT ' . $i . ' n';
	}
	$numbers_sql = implode( ' UNION ALL ', $numbers );

	$table = $wpdb->prefix . 'fanfic_story_search_index';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		"SELECT
			TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX({$column}, ',', numbers.n), ',', -1)) AS value_slug,
			COUNT(DISTINCT story_id) AS story_count
		FROM {$table}
		CROSS JOIN ({$numbers_sql}) numbers
		WHERE story_status = 'publish'
		  AND {$column} != ''
		  AND CHAR_LENGTH({$column}) - CHAR_LENGTH(REPLACE({$column}, ',', '')) >= numbers.n - 1
		GROUP BY value_slug
		HAVING value_slug != ''",
		ARRAY_A
	);

	$counts = array();
	foreach ( (array) $rows as $row ) {
		$value = trim( (string) ( $row['value_slug'] ?? '' ) );
		if ( '' === $value ) {
			continue;
		}
		$counts[ $value ] = absint( $row['story_count'] ?? 0 );
	}

	return $counts;
}

/**
 * Get global filter option counts for search form.
 *
 * Uses published stories only. Built-in filters are sourced from search index.
 * Custom taxonomy counts are sourced from custom relation tables and constrained
 * by search index publish status.
 *
 * @since 1.2.0
 * @return array Filter counts by taxonomy key.
 */
function fanfic_get_search_filter_option_counts() {
	global $wpdb;

	$empty = array(
		'genres_by_name'   => array(),
		'statuses_by_name' => array(),
		'ages'             => array(),
		'languages'        => array(),
		'warnings'         => array(),
		'fandoms'          => array(),
		'custom'           => array(),
	);

	if ( ! fanfic_search_filter_map_tables_ready() ) {
		return $empty;
	}

	$index_table = $wpdb->prefix . 'fanfic_story_search_index';
	$map_table   = $wpdb->prefix . 'fanfic_story_filter_map';

	$cache_key = '';
	if ( class_exists( 'Fanfic_Cache' ) ) {
		$cache_key = Fanfic_Cache::get_key( 'search', 'global_filter_counts' );
		$cached = Fanfic_Cache::get( $cache_key, null, Fanfic_Cache::REALTIME );
		if ( false !== $cached && is_array( $cached ) ) {
			return wp_parse_args( $cached, $empty );
		}
	}

	$counts = $empty;
	$available_ages = fanfic_get_available_age_filters();
	$age_aliases = fanfic_get_age_filter_alias_map( $available_ages );
	foreach ( (array) $available_ages as $age_value ) {
		$counts['ages'][ $age_value ] = 0;
	}

	// Status counts (slug -> term name key).
	$status_slug_counts = fanfic_get_filter_map_counts_by_facet( 'status' );
	if ( ! empty( $status_slug_counts ) ) {
		$status_terms = get_terms(
			array(
				'taxonomy'   => 'fanfiction_status',
				'hide_empty' => false,
				'slug'       => array_keys( $status_slug_counts ),
			)
		);
		if ( ! is_wp_error( $status_terms ) ) {
			foreach ( (array) $status_terms as $status_term ) {
				$slug = sanitize_title( $status_term->slug ?? '' );
				if ( '' === $slug || ! isset( $status_slug_counts[ $slug ] ) ) {
					continue;
				}
				$key = fanfic_normalize_filter_label_key( $status_term->name ?? '' );
				if ( '' === $key ) {
					continue;
				}
				$counts['statuses_by_name'][ $key ] = absint( $status_slug_counts[ $slug ] );
			}
		}
	}

	// Genre counts (slug -> term name key).
	$genre_slug_counts = fanfic_get_filter_map_counts_by_facet( 'genre' );
	if ( ! empty( $genre_slug_counts ) ) {
		$genre_terms = get_terms(
			array(
				'taxonomy'   => 'fanfiction_genre',
				'hide_empty' => false,
				'slug'       => array_keys( $genre_slug_counts ),
			)
		);
		if ( ! is_wp_error( $genre_terms ) ) {
			foreach ( (array) $genre_terms as $genre_term ) {
				$slug = sanitize_title( $genre_term->slug ?? '' );
				if ( '' === $slug || ! isset( $genre_slug_counts[ $slug ] ) ) {
					continue;
				}
				$key = fanfic_normalize_filter_label_key( $genre_term->name ?? '' );
				if ( '' === $key ) {
					continue;
				}
				$counts['genres_by_name'][ $key ] = absint( $genre_slug_counts[ $slug ] );
			}
		}
	}

	// Age counts.
	$age_counts = fanfic_get_filter_map_counts_by_facet( 'age' );
	foreach ( $age_counts as $age_slug => $story_count ) {
		$raw_age = trim( (string) $age_slug );
		if ( '' === $raw_age ) {
			continue;
		}
		$lookup_key = strtoupper( $raw_age );
		$age = $age_aliases[ $lookup_key ] ?? ( $age_aliases[ str_replace( '+', '', $lookup_key ) ] ?? $raw_age );
		if ( ! isset( $counts['ages'][ $age ] ) ) {
			$counts['ages'][ $age ] = 0;
		}
		$counts['ages'][ $age ] = absint( $story_count );
	}
	$counts['ages'] = array_replace( array_fill_keys( fanfic_get_available_age_filters(), 0 ), $counts['ages'] );

	// Language counts.
	$language_counts = fanfic_get_filter_map_counts_by_facet( 'language' );
	foreach ( $language_counts as $slug => $story_count ) {
		$slug = sanitize_title( $slug );
		if ( '' !== $slug ) {
			$counts['languages'][ $slug ] = absint( $story_count );
		}
	}

	// Warning counts.
	$warning_counts = fanfic_get_filter_map_counts_by_facet( 'warning' );
	foreach ( $warning_counts as $slug => $story_count ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			continue;
		}
		$counts['warnings'][ $slug ] = absint( $story_count );
	}

	// Fandom counts.
	$fandom_counts = fanfic_get_filter_map_counts_by_facet( 'fandom' );
	foreach ( $fandom_counts as $slug => $story_count ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			continue;
		}
		$counts['fandoms'][ $slug ] = absint( $story_count );
	}

	// Custom taxonomy counts from runtime filter map.
	$custom_rows = $wpdb->get_results(
		"SELECT
			SUBSTRING_INDEX(m.facet_type, ':', -1) AS taxonomy_slug,
			m.facet_value AS term_slug,
			COUNT(DISTINCT m.story_id) AS story_count
		FROM {$map_table} m
		INNER JOIN {$index_table} idx ON idx.story_id = m.story_id
		WHERE idx.story_status = 'publish'
		  AND m.facet_type LIKE 'custom:%'
		GROUP BY taxonomy_slug, term_slug",
		ARRAY_A
	);

	foreach ( (array) $custom_rows as $row ) {
		$taxonomy_slug = sanitize_title( $row['taxonomy_slug'] ?? '' );
		$term_slug = sanitize_title( $row['term_slug'] ?? '' );
		if ( '' === $taxonomy_slug || '' === $term_slug ) {
			continue;
		}
		if ( ! isset( $counts['custom'][ $taxonomy_slug ] ) ) {
			$counts['custom'][ $taxonomy_slug ] = array();
		}
		$counts['custom'][ $taxonomy_slug ][ $term_slug ] = absint( $row['story_count'] ?? 0 );
	}

	if ( ! empty( $cache_key ) ) {
		Fanfic_Cache::set( $cache_key, $counts, Fanfic_Cache::REALTIME );
	}

	return $counts;
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
	$taxonomy_id = absint( $taxonomy_id );
	$term_slug   = sanitize_title( $term_slug );
	if ( ! $taxonomy_id || '' === $term_slug || ! class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
		return 0;
	}

	$taxonomy = Fanfic_Custom_Taxonomies::get_taxonomy_by_id( $taxonomy_id );
	if ( empty( $taxonomy ) || empty( $taxonomy['slug'] ) ) {
		return 0;
	}

	$counts = fanfic_get_filter_map_counts_by_facet( 'custom:' . sanitize_title( $taxonomy['slug'] ) );
	return absint( $counts[ $term_slug ] ?? 0 );
}
