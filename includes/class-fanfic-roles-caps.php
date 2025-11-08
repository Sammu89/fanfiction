<?php
/**
 * Custom User Roles and Capabilities
 *
 * Defines custom user roles and capabilities for the fanfiction manager plugin.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Roles_Caps
 *
 * Manages custom user roles and capabilities for the fanfiction system.
 *
 * @since 1.0.0
 */
class Fanfic_Roles_Caps {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Create custom roles and assign capabilities.
	 *
	 * This method should be called on plugin activation.
	 * Note: Role display names use non-translated strings to avoid
	 * translation loading warnings during activation. Names are updated
	 * after init hook fires via update_role_names().
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function create_roles() {
		// Get base capabilities for subscriber role
		$subscriber = get_role( 'subscriber' );
		$subscriber_caps = $subscriber ? $subscriber->capabilities : array( 'read' => true );

		// Get base capabilities for editor role
		$editor = get_role( 'editor' );
		$editor_caps = $editor ? $editor->capabilities : array( 'read' => true );

		// Remove read capability to prevent admin access for authors
		$author_base_caps = array( 'read' => true );

		// Create Fanfiction Author role
		$author_caps = array_merge(
			$author_base_caps,
			array(
				'edit_fanfiction_stories'      => true,
				'publish_fanfiction_stories'   => true,
				'delete_fanfiction_stories'    => true,
				'edit_fanfiction_chapters'     => true,
				'publish_fanfiction_chapters'  => true,
				'delete_fanfiction_chapters'   => true,
			)
		);

		add_role(
			'fanfiction_author',
			'Fanfiction Author',
			$author_caps
		);

		// Create Fanfiction Reader role
		// This role has the same capabilities as fanfiction_author
		// It's used purely for tracking/statistical purposes (users who haven't published stories yet)
		$reader_caps = array_merge(
			$author_base_caps,
			array(
				'edit_fanfiction_stories'      => true,
				'publish_fanfiction_stories'   => true,
				'delete_fanfiction_stories'    => true,
				'edit_fanfiction_chapters'     => true,
				'publish_fanfiction_chapters'  => true,
				'delete_fanfiction_chapters'   => true,
			)
		);

		add_role(
			'fanfiction_reader',
			'Fanfiction Reader',
			$reader_caps
		);

		// Create Fanfiction Moderator role
		$moderator_caps = array_merge(
			$editor_caps,
			array(
				'edit_fanfiction_stories'          => true,
				'edit_others_fanfiction_stories'   => true,
				'publish_fanfiction_stories'       => true,
				'delete_fanfiction_stories'        => true,
				'delete_others_fanfiction_stories' => true,
				'edit_fanfiction_chapters'         => true,
				'edit_others_fanfiction_chapters'  => true,
				'publish_fanfiction_chapters'      => true,
				'delete_fanfiction_chapters'       => true,
				'delete_others_fanfiction_chapters'=> true,
				'moderate_fanfiction'              => true,
			)
		);

		add_role(
			'fanfiction_moderator',
			'Fanfiction Moderator',
			$moderator_caps
		);

		// Create Fanfiction Admin role
		// Admin role has full control over plugin settings and inherits all moderator permissions
		$admin_caps = array_merge(
			$moderator_caps,
			array(
				'manage_fanfiction_settings'    => true,
				'manage_fanfiction_taxonomies'  => true,
				'manage_fanfiction_url_config'  => true,
				'manage_fanfiction_emails'      => true,
				'manage_fanfiction_css'         => true,
			)
		);

		add_role(
			'fanfiction_admin',
			'Fanfiction Admin',
			$admin_caps
		);

		// Create Fanfiction Banned User role
		// Banned users can only log in (read capability) but cannot do anything else
		$banned_caps = array(
			'read' => true,
		);

		add_role(
			'fanfiction_banned_user',
			'Fanfiction Banned User',
			$banned_caps
		);

		// NOTE: WordPress administrators do NOT get fanfiction capabilities added to their role.
		// Instead, they inherit all permissions automatically via the cascade system in map_meta_cap().
		// This follows the hierarchy: WordPress Admin >> Fanfic Admin > Fanfic Moderator > Fanfic Author > Fanfic Reader
	}

	/**
	 * Update role display names with translations after init hook.
	 *
	 * This is called after the init hook to ensure translations are loaded.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function update_role_names() {
		$author_role = get_role( 'fanfiction_author' );
		if ( $author_role ) {
			$author_role->name = __( 'Fanfiction Author', 'fanfiction-manager' );
		}

		$reader_role = get_role( 'fanfiction_reader' );
		if ( $reader_role ) {
			$reader_role->name = __( 'Fanfiction Reader', 'fanfiction-manager' );
		}

		$moderator_role = get_role( 'fanfiction_moderator' );
		if ( $moderator_role ) {
			$moderator_role->name = __( 'Fanfiction Moderator', 'fanfiction-manager' );
		}

		$admin_role = get_role( 'fanfiction_admin' );
		if ( $admin_role ) {
			$admin_role->name = __( 'Fanfiction Admin', 'fanfiction-manager' );
		}

		$banned_role = get_role( 'fanfiction_banned_user' );
		if ( $banned_role ) {
			$banned_role->name = __( 'Fanfiction Banned User', 'fanfiction-manager' );
		}
	}

	/**
	 * Remove custom roles.
	 *
	 * This method should be called on plugin uninstall.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function remove_roles() {
		// Remove custom roles
		remove_role( 'fanfiction_author' );
		remove_role( 'fanfiction_reader' );
		remove_role( 'fanfiction_moderator' );
		remove_role( 'fanfiction_admin' );
		remove_role( 'fanfiction_banned_user' );

		// Clean up any fanfiction capabilities that may have been added to administrator role
		// in earlier versions of the plugin
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( 'edit_fanfiction_stories' );
			$admin->remove_cap( 'edit_others_fanfiction_stories' );
			$admin->remove_cap( 'publish_fanfiction_stories' );
			$admin->remove_cap( 'delete_fanfiction_stories' );
			$admin->remove_cap( 'delete_others_fanfiction_stories' );
			$admin->remove_cap( 'edit_fanfiction_chapters' );
			$admin->remove_cap( 'edit_others_fanfiction_chapters' );
			$admin->remove_cap( 'publish_fanfiction_chapters' );
			$admin->remove_cap( 'delete_fanfiction_chapters' );
			$admin->remove_cap( 'delete_others_fanfiction_chapters' );
			$admin->remove_cap( 'moderate_fanfiction' );
			$admin->remove_cap( 'manage_fanfiction_settings' );
			$admin->remove_cap( 'manage_fanfiction_taxonomies' );
			$admin->remove_cap( 'manage_fanfiction_url_config' );
			$admin->remove_cap( 'manage_fanfiction_emails' );
			$admin->remove_cap( 'manage_fanfiction_css' );
		}
	}

	/**
	 * Map meta capabilities to primitive capabilities.
	 *
	 * Handles granular permission checks, such as ensuring authors
	 * can only edit their own stories and chapters.
	 *
	 * Implements cascade system: WordPress Admin >> Fanfic Admin > Fanfic Moderator > Fanfic Author > Fanfic Reader
	 * WordPress admins automatically have all fanfiction permissions without needing explicit capabilities.
	 *
	 * @since 1.0.0
	 * @param array  $caps    Primitive capabilities required.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional arguments.
	 * @return array Modified capabilities required.
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		// Debug: Log ALL capability checks to understand what's being called
		if ( strpos( $cap, 'fanfiction' ) !== false || $cap === 'edit_post' || $cap === 'read_post' ) {
			error_log( '=== MAP_META_CAP CALLED ===' );
			error_log( 'Capability: ' . $cap );
			error_log( 'User ID: ' . $user_id );
			error_log( 'Args: ' . print_r( $args, true ) );
			error_log( 'Incoming $caps: ' . print_r( $caps, true ) );
		}

		// WordPress admins automatically have all fanfiction permissions (cascade system)
		// Check if this is a fanfiction-related capability
		$fanfic_caps = array(
			'read_post',
			'read_fanfiction_story',
			'edit_fanfiction_story',
			'delete_fanfiction_story',
			'edit_fanfiction_chapter',
			'delete_fanfiction_chapter',
			'edit_fanfiction_stories',
			'edit_others_fanfiction_stories',
			'publish_fanfiction_stories',
			'delete_fanfiction_stories',
			'delete_others_fanfiction_stories',
			'edit_fanfiction_chapters',
			'edit_others_fanfiction_chapters',
			'publish_fanfiction_chapters',
			'delete_fanfiction_chapters',
			'delete_others_fanfiction_chapters',
			'moderate_fanfiction',
			'manage_fanfiction_settings',
			'manage_fanfiction_taxonomies',
			'manage_fanfiction_url_config',
			'manage_fanfiction_emails',
			'manage_fanfiction_css',
		);

		if ( in_array( $cap, $fanfic_caps, true ) ) {
			// WordPress admins bypass all checks - they have manage_options which is top-level
			if ( user_can( $user_id, 'manage_options' ) ) {
				return array( 'manage_options' );
			}
		}

		// Handle edit_post capability for fanfiction stories and chapters
		// WordPress checks 'edit_post' (not 'edit_fanfiction_story') when verifying edit permissions
		if ( 'edit_post' === $cap && ! empty( $args[0] ) ) {
			$post = get_post( $args[0] );

			// Only handle fanfiction post types
			if ( $post && in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
				error_log( '=== HANDLING edit_post FOR FANFICTION ===' );
				error_log( 'Post ID: ' . $post->ID );
				error_log( 'Post status: ' . $post->post_status );
				error_log( 'Post author: ' . $post->post_author );
				error_log( 'Current user: ' . $user_id );

				// Check if user is the post author
				if ( (int) $user_id === (int) $post->post_author ) {
					// Author can edit their own posts
					if ( 'fanfiction_story' === $post->post_type ) {
						error_log( 'User is author - returning edit_fanfiction_stories' );
						return array( 'edit_fanfiction_stories' );
					} elseif ( 'fanfiction_chapter' === $post->post_type ) {
						error_log( 'User is author - returning edit_fanfiction_chapters' );
						return array( 'edit_fanfiction_chapters' );
					}
				} else {
					// Not the author - requires 'edit_others' capability
					if ( 'fanfiction_story' === $post->post_type ) {
						error_log( 'User is NOT author - returning edit_others_fanfiction_stories' );
						return array( 'edit_others_fanfiction_stories' );
					} elseif ( 'fanfiction_chapter' === $post->post_type ) {
						error_log( 'User is NOT author - returning edit_others_fanfiction_chapters' );
						return array( 'edit_others_fanfiction_chapters' );
					}
				}
			}
		}

		// Handle read_post capability for fanfiction stories and chapters
		// This allows authors to view their own draft stories
		if ( 'read_post' === $cap && ! empty( $args[0] ) ) {
			$post = get_post( $args[0] );

			// Only handle fanfiction post types
			if ( $post && in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
				// Published posts are readable by everyone
				if ( 'publish' === $post->post_status ) {
					return array( 'read' );
				}

				// Draft/private posts:
				// Allow the post author to read their own drafts
				if ( (int) $user_id === (int) $post->post_author ) {
					return array( 'read' );
				}

				// Allow moderators/admins to read any draft
				if ( 'fanfiction_story' === $post->post_type ) {
					return array( 'edit_others_fanfiction_stories' );
				} elseif ( 'fanfiction_chapter' === $post->post_type ) {
					return array( 'edit_others_fanfiction_chapters' );
				}
			}
		}

		// Handle story capabilities
		if ( in_array( $cap, array( 'edit_fanfiction_story', 'delete_fanfiction_story' ), true ) ) {
			error_log( '=== MAP_META_CAP DEBUG (Story) ===' );
			error_log( 'Capability requested: ' . $cap );
			error_log( 'User ID: ' . $user_id );
			error_log( 'Post ID (args[0]): ' . ( isset( $args[0] ) ? $args[0] : 'NOT SET' ) );

			// WordPress admins should have already been granted access above,
			// but double-check here as a safety net
			if ( user_can( $user_id, 'manage_options' ) ) {
				error_log( 'User has manage_options - GRANTED' );
				return array( 'manage_options' );
			}
			error_log( 'User does NOT have manage_options' );

			$post = get_post( $args[0] );
			error_log( 'get_post() returned: ' . ( $post ? 'POST OBJECT' : 'NULL/FALSE' ) );

			if ( $post ) {
				error_log( 'Post type: ' . $post->post_type );
				error_log( 'Post author: ' . $post->post_author );
			}

			// If post doesn't exist or isn't a fanfiction story, require the "others" capability
			// This allows moderators/admins to access, but prevents authors from accessing invalid stories
			if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
				error_log( 'Post invalid or wrong type - requiring "others" capability' );
				if ( 'edit_fanfiction_story' === $cap ) {
					error_log( 'Returning: edit_others_fanfiction_stories' );
					return array( 'edit_others_fanfiction_stories' );
				} elseif ( 'delete_fanfiction_story' === $cap ) {
					error_log( 'Returning: delete_others_fanfiction_stories' );
					return array( 'delete_others_fanfiction_stories' );
				}
			}

			// Check if user can edit/delete others' stories
			if ( 'edit_fanfiction_story' === $cap ) {
				error_log( 'Checking authorship: user ' . $user_id . ' vs post_author ' . $post->post_author );
				if ( (int) $user_id === (int) $post->post_author ) {
					error_log( 'User IS author - Returning: edit_fanfiction_stories' );
					$caps = array( 'edit_fanfiction_stories' );
				} else {
					error_log( 'User is NOT author - Returning: edit_others_fanfiction_stories' );
					$caps = array( 'edit_others_fanfiction_stories' );
				}
			} elseif ( 'delete_fanfiction_story' === $cap ) {
				if ( (int) $user_id === (int) $post->post_author ) {
					error_log( 'User IS author - Returning: delete_fanfiction_stories' );
					$caps = array( 'delete_fanfiction_stories' );
				} else {
					error_log( 'User is NOT author - Returning: delete_others_fanfiction_stories' );
					$caps = array( 'delete_others_fanfiction_stories' );
				}
			}
		}

		// Handle chapter capabilities
		if ( in_array( $cap, array( 'edit_fanfiction_chapter', 'delete_fanfiction_chapter' ), true ) ) {
			// WordPress admins should have already been granted access above,
			// but double-check here as a safety net
			if ( user_can( $user_id, 'manage_options' ) ) {
				return array( 'manage_options' );
			}

			$post = get_post( $args[0] );

			// If post doesn't exist or isn't a fanfiction chapter, require the "others" capability
			// This allows moderators/admins to access, but prevents authors from accessing invalid chapters
			if ( ! $post || 'fanfiction_chapter' !== $post->post_type ) {
				if ( 'edit_fanfiction_chapter' === $cap ) {
					return array( 'edit_others_fanfiction_chapters' );
				} elseif ( 'delete_fanfiction_chapter' === $cap ) {
					return array( 'delete_others_fanfiction_chapters' );
				}
			}

			// Check if user can edit/delete others' chapters
			if ( 'edit_fanfiction_chapter' === $cap ) {
				if ( (int) $user_id === (int) $post->post_author ) {
					$caps = array( 'edit_fanfiction_chapters' );
				} else {
					$caps = array( 'edit_others_fanfiction_chapters' );
				}
			} elseif ( 'delete_fanfiction_chapter' === $cap ) {
				if ( (int) $user_id === (int) $post->post_author ) {
					$caps = array( 'delete_fanfiction_chapters' );
				} else {
					$caps = array( 'delete_others_fanfiction_chapters' );
				}
			}
		}

		return $caps;
	}
}
