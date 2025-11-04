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

		// Add fanfiction capabilities to administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'edit_fanfiction_stories' );
			$admin->add_cap( 'edit_others_fanfiction_stories' );
			$admin->add_cap( 'publish_fanfiction_stories' );
			$admin->add_cap( 'delete_fanfiction_stories' );
			$admin->add_cap( 'delete_others_fanfiction_stories' );
			$admin->add_cap( 'edit_fanfiction_chapters' );
			$admin->add_cap( 'edit_others_fanfiction_chapters' );
			$admin->add_cap( 'publish_fanfiction_chapters' );
			$admin->add_cap( 'delete_fanfiction_chapters' );
			$admin->add_cap( 'delete_others_fanfiction_chapters' );
			$admin->add_cap( 'moderate_fanfiction' );
			$admin->add_cap( 'manage_fanfiction_settings' );
			$admin->add_cap( 'manage_fanfiction_taxonomies' );
			$admin->add_cap( 'manage_fanfiction_url_config' );
			$admin->add_cap( 'manage_fanfiction_emails' );
			$admin->add_cap( 'manage_fanfiction_css' );
		}
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
		remove_role( 'fanfiction_banned_user' );

		// Remove fanfiction capabilities from administrator
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
		}
	}

	/**
	 * Map meta capabilities to primitive capabilities.
	 *
	 * Handles granular permission checks, such as ensuring authors
	 * can only edit their own stories and chapters.
	 *
	 * @since 1.0.0
	 * @param array  $caps    Primitive capabilities required.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional arguments.
	 * @return array Modified capabilities required.
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		// Handle story capabilities
		if ( in_array( $cap, array( 'edit_fanfiction_story', 'delete_fanfiction_story' ), true ) ) {
			$post = get_post( $args[0] );

			if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
				return $caps;
			}

			// Check if user can edit/delete others' stories
			if ( 'edit_fanfiction_story' === $cap ) {
				if ( (int) $user_id === (int) $post->post_author ) {
					$caps = array( 'edit_fanfiction_stories' );
				} else {
					$caps = array( 'edit_others_fanfiction_stories' );
				}
			} elseif ( 'delete_fanfiction_story' === $cap ) {
				if ( (int) $user_id === (int) $post->post_author ) {
					$caps = array( 'delete_fanfiction_stories' );
				} else {
					$caps = array( 'delete_others_fanfiction_stories' );
				}
			}
		}

		// Handle chapter capabilities
		if ( in_array( $cap, array( 'edit_fanfiction_chapter', 'delete_fanfiction_chapter' ), true ) ) {
			$post = get_post( $args[0] );

			if ( ! $post || 'fanfiction_chapter' !== $post->post_type ) {
				return $caps;
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
