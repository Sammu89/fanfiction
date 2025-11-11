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
 * Get URL for the search page
 *
 * @return string The search page URL.
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
 *                        - 'stories' : Story archive page
 *                        - 'search' : Search page
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
				'label'  => __( 'Search', 'fanfiction-manager' ),
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
			} elseif ( function_exists( 'fanfic_is_search_page' ) && fanfic_is_search_page() ) {
				$context = 'search';
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
						class="fanfic-report-btn comment-report-link"
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

	wp_redirect( add_query_arg( 'cache_cleared', '1', wp_get_referer() ) );
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