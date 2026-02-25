<?php
/**
 * Register Custom Post Types for Fanfiction Manager
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Post_Types
 *
 * Handles registration of custom post types for stories and chapters.
 */
class Fanfic_Post_Types {

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 */
	public static function register() {
		self::register_image_sizes();
		self::register_story_post_type();
		self::register_chapter_post_type();
	}

	/**
	 * Register plugin image sizes.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	private static function register_image_sizes() {
		// Story-card cover image constrained to 220px width.
		add_image_size( 'fanfic_story_card_220', 220, 0, false );
		// Author avatar thumbnail: 80×80 hard-cropped (4× retina-ready for 20 px display).
		add_image_size( 'fanfic_avatar_thumb', 80, 80, true );
	}

	/**
	 * Register the Story post type.
	 *
	 * @since 1.0.0
	 */
	private static function register_story_post_type() {
		$labels = array(
			'name'                  => __( 'Stories', 'fanfiction-manager' ),
			'singular_name'         => __( 'Story', 'fanfiction-manager' ),
			'menu_name'             => __( 'Fanfiction', 'fanfiction-manager' ),
			'name_admin_bar'        => __( 'Story', 'fanfiction-manager' ),
			'add_new'               => __( 'Add New', 'fanfiction-manager' ),
			'add_new_item'          => __( 'Add New Story', 'fanfiction-manager' ),
			'new_item'              => __( 'New Story', 'fanfiction-manager' ),
			'edit_item'             => __( 'Edit Story', 'fanfiction-manager' ),
			'view_item'             => __( 'View Story', 'fanfiction-manager' ),
			'all_items'             => __( 'All Stories', 'fanfiction-manager' ),
			'search_items'          => __( 'Search Stories', 'fanfiction-manager' ),
			'parent_item_colon'     => __( 'Parent Stories:', 'fanfiction-manager' ),
			'not_found'             => __( 'No stories found.', 'fanfiction-manager' ),
			'not_found_in_trash'    => __( 'No stories found in Trash.', 'fanfiction-manager' ),
			'featured_image'        => __( 'Story Cover Image', 'fanfiction-manager' ),
			'set_featured_image'    => __( 'Set cover image', 'fanfiction-manager' ),
			'remove_featured_image' => __( 'Remove cover image', 'fanfiction-manager' ),
			'use_featured_image'    => __( 'Use as cover image', 'fanfiction-manager' ),
			'archives'              => __( 'Story Archives', 'fanfiction-manager' ),
			'insert_into_item'      => __( 'Insert into story', 'fanfiction-manager' ),
			'uploaded_to_this_item' => __( 'Uploaded to this story', 'fanfiction-manager' ),
			'filter_items_list'     => __( 'Filter stories list', 'fanfiction-manager' ),
			'items_list_navigation' => __( 'Stories list navigation', 'fanfiction-manager' ),
			'items_list'            => __( 'Stories list', 'fanfiction-manager' ),
		);

		// Get dynamic story path
		$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
		$story_path = get_option( 'fanfic_story_path', 'stories' );

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Fanfiction stories', 'fanfiction-manager' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $base_slug . '/' . $story_path,
				'with_front' => false,
				'feeds'      => true,
				'pages'      => true,
			),
			'capability_type'    => array( 'fanfiction_story', 'fanfiction_stories' ),
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => true,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-book',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'comments' ),
			'exclude_from_search' => true,
			'show_in_rest'       => true,
		);

		register_post_type( 'fanfiction_story', $args );
	}

	/**
	 * Register the Chapter post type.
	 *
	 * @since 1.0.0
	 */
	private static function register_chapter_post_type() {
		$labels = array(
			'name'                  => __( 'Chapters', 'fanfiction-manager' ),
			'singular_name'         => __( 'Chapter', 'fanfiction-manager' ),
			'menu_name'             => __( 'Chapters', 'fanfiction-manager' ),
			'name_admin_bar'        => __( 'Chapter', 'fanfiction-manager' ),
			'add_new'               => __( 'Add New', 'fanfiction-manager' ),
			'add_new_item'          => __( 'Add New Chapter', 'fanfiction-manager' ),
			'new_item'              => __( 'New Chapter', 'fanfiction-manager' ),
			'edit_item'             => __( 'Edit Chapter', 'fanfiction-manager' ),
			'view_item'             => __( 'View Chapter', 'fanfiction-manager' ),
			'all_items'             => __( 'All Chapters', 'fanfiction-manager' ),
			'search_items'          => __( 'Search Chapters', 'fanfiction-manager' ),
			'parent_item_colon'     => __( 'Parent Story:', 'fanfiction-manager' ),
			'not_found'             => __( 'No chapters found.', 'fanfiction-manager' ),
			'not_found_in_trash'    => __( 'No chapters found in Trash.', 'fanfiction-manager' ),
			'archives'              => __( 'Chapter Archives', 'fanfiction-manager' ),
			'insert_into_item'      => __( 'Insert into chapter', 'fanfiction-manager' ),
			'uploaded_to_this_item' => __( 'Uploaded to this chapter', 'fanfiction-manager' ),
			'filter_items_list'     => __( 'Filter chapters list', 'fanfiction-manager' ),
			'items_list_navigation' => __( 'Chapters list navigation', 'fanfiction-manager' ),
			'items_list'            => __( 'Chapters list', 'fanfiction-manager' ),
		);

		// Get dynamic story path
		$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
		$story_path = get_option( 'fanfic_story_path', 'stories' );

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Fanfiction story chapters', 'fanfiction-manager' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $base_slug . '/' . $story_path . '/%fanfiction_story%',
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
			'capability_type'    => array( 'fanfiction_chapter', 'fanfiction_chapters' ),
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => true,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title', 'editor', 'custom-fields', 'page-attributes', 'comments' ),
			'exclude_from_search' => true,
			'show_in_rest'       => true,
		);

		register_post_type( 'fanfiction_chapter', $args );
	}
}
