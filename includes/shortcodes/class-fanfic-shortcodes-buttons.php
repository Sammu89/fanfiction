<?php
/**
 * Action Buttons Shortcodes Class
 *
 * Handles context-aware action buttons for stories, chapters, and authors.
 * This is a NEW shortcode system (v2.0) that integrates with the unified interaction backend.
 *
 * @package FanfictionManager
 * @subpackage Shortcodes
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Shortcodes_Buttons
 *
 * Context-aware action buttons (bookmark, like, follow, subscribe, etc.).
 *
 * @since 2.0.0
 */
class Fanfic_Shortcodes_Buttons {

	/**
	 * Register action button shortcodes
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'fanfiction-action-buttons', array( __CLASS__, 'action_buttons' ) );
	}

	/**
	 * Context-aware action buttons shortcode
	 *
	 * [fanfiction-action-buttons context="story" actions="bookmark,subscribe,share"]
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Action buttons HTML.
	 */
	public static function action_buttons( $atts ) {
		global $post;

		$atts = Fanfic_Shortcodes::sanitize_atts(
			$atts,
			array(
				'context' => '',
				'actions' => '',
			),
			'fanfiction-action-buttons'
		);

		// Auto-detect context if not provided
		$context = sanitize_key( $atts['context'] );
		if ( empty( $context ) ) {
			$context = self::detect_context();
		}

		// Return empty if context couldn't be determined
		if ( ! $context ) {
			return '';
		}

		// Parse actions parameter (comma-separated list)
		$requested_actions = array();
		if ( ! empty( $atts['actions'] ) ) {
			$requested_actions = array_map( 'trim', explode( ',', $atts['actions'] ) );
		}

		// Get available actions for this context
		$available_actions = self::get_context_actions( $context );

		// Filter actions if specific ones were requested
		if ( ! empty( $requested_actions ) ) {
			$available_actions = array_intersect( $available_actions, $requested_actions );
		}

		// Return empty if no actions available
		if ( empty( $available_actions ) ) {
			return '';
		}

		// Get context IDs
		$context_ids = self::get_context_ids( $context );
		if ( ! $context_ids ) {
			return '';
		}

		// Generate nonce for AJAX actions
		$nonce = wp_create_nonce( 'fanfic_ajax_nonce' );

		// Build output
		$output = '<div class="fanfic-action-buttons fanfic-action-buttons-' . esc_attr( $context ) . '" data-context="' . esc_attr( $context ) . '">';

		foreach ( $available_actions as $action ) {
			$output .= self::render_button( $action, $context, $context_ids, $nonce );
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Auto-detect context from current post
	 *
	 * @since 2.0.0
	 * @return string|false Context (story, chapter, author) or false.
	 */
	private static function detect_context() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		if ( 'fanfiction_story' === $post->post_type ) {
			return 'story';
		}

		if ( 'fanfiction_chapter' === $post->post_type ) {
			return 'chapter';
		}

		// Check if we're on an author archive page
		if ( is_author() ) {
			return 'author';
		}

		return false;
	}

	/**
	 * Get available actions for context
	 *
	 * @since 2.0.0
	 * @param string $context Context (story, chapter, author).
	 * @return array Available actions.
	 */
	private static function get_context_actions( $context ) {
		$actions = array();

		switch ( $context ) {
			case 'story':
				$actions = array( 'follow', 'bookmark', 'subscribe', 'share', 'report', 'edit' );
				break;

			case 'chapter':
				$actions = array( 'like', 'bookmark', 'mark-read', 'subscribe', 'share', 'report', 'edit' );
				break;

			case 'author':
				$actions = array( 'follow', 'share' );
				break;
		}

		return apply_filters( 'fanfic_action_buttons_actions', $actions, $context );
	}

	/**
	 * Get context IDs (story, chapter, author)
	 *
	 * @since 2.0.0
	 * @param string $context Context (story, chapter, author).
	 * @return array|false Context IDs or false.
	 */
	private static function get_context_ids( $context ) {
		global $post;

		$ids = array();

		switch ( $context ) {
			case 'story':
				if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
					return false;
				}
				$ids['story_id'] = $post->ID;
				$ids['author_id'] = $post->post_author;
				break;

			case 'chapter':
				if ( ! $post || 'fanfiction_chapter' !== $post->post_type ) {
					return false;
				}
				$ids['chapter_id'] = $post->ID;
				$ids['story_id'] = $post->post_parent;
				$ids['author_id'] = $post->post_author;
				// Get chapter number from post meta for mark-as-read functionality
				$chapter_number = get_post_meta( $post->ID, '_fanfic_chapter_number', true );
				$ids['chapter_number'] = $chapter_number ? absint( $chapter_number ) : 1;
				break;

			case 'author':
				if ( is_author() ) {
					$ids['author_id'] = get_queried_object_id();
				} elseif ( $post ) {
					$ids['author_id'] = $post->post_author;
				} else {
					return false;
				}
				break;
		}

		return $ids;
	}

	/**
	 * Render individual button
	 *
	 * @since 2.0.0
	 * @param string $action      Action type.
	 * @param string $context     Context (story, chapter, author).
	 * @param array  $context_ids Context IDs.
	 * @param string $nonce       AJAX nonce.
	 * @return string Button HTML.
	 */
	private static function render_button( $action, $context, $context_ids, $nonce ) {
		$user_id = get_current_user_id();

		// Check if user can edit this content
		if ( 'edit' === $action ) {
			if ( ! $user_id || $user_id !== absint( $context_ids['author_id'] ) ) {
				return ''; // Only show edit button to the author
			}
		}

		// Get current state for toggle buttons
		$current_state = self::get_button_state( $action, $context_ids, $user_id );

		// Build button classes
		$classes = array(
			'fanfic-action-button',
			'fanfic-' . $action . '-button',
		);

		if ( $current_state ) {
			$classes[] = 'is-active';
			$classes[] = 'is-' . str_replace( '-', '', $action ) . 'd'; // is-bookmarked, is-liked, etc.
		}

		// Build data attributes
		$data_attrs = array(
			'data-action' => $action,
			'data-nonce' => $nonce,
		);

		foreach ( $context_ids as $key => $value ) {
			$data_attrs[ 'data-' . str_replace( '_', '-', $key ) ] = absint( $value );
		}

		// Add action-specific data attributes
		if ( 'follow' === $action ) {
			// Determine target_id and follow_type based on context
			if ( isset( $context_ids['story_id'] ) && ! isset( $context_ids['chapter_id'] ) ) {
				// Story context - follow the story
				$data_attrs['data-target-id'] = $context_ids['story_id'];
				$data_attrs['data-follow-type'] = 'story';
			} elseif ( isset( $context_ids['author_id'] ) ) {
				// Author context - follow the author
				$data_attrs['data-target-id'] = $context_ids['author_id'];
				$data_attrs['data-follow-type'] = 'author';
			}
		} elseif ( 'bookmark' === $action ) {
			// Add bookmark_type for bookmark buttons
			if ( isset( $context_ids['chapter_id'] ) ) {
				$data_attrs['data-bookmark-type'] = 'chapter';
				$data_attrs['data-post-id'] = $context_ids['chapter_id'];
			} elseif ( isset( $context_ids['story_id'] ) ) {
				$data_attrs['data-bookmark-type'] = 'story';
				$data_attrs['data-post-id'] = $context_ids['story_id'];
			}
		}

		// Get button label and icon
		$label = self::get_button_label( $action, $current_state );
		$icon = self::get_button_icon( $action, $current_state );
		$aria_label = self::get_button_aria_label( $action, $current_state, $context );

		// Build button HTML
		$output = '<button class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		foreach ( $data_attrs as $key => $value ) {
			$output .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		$output .= ' aria-label="' . esc_attr( $aria_label ) . '"';
		$output .= ' type="button">';
		$output .= '<span class="fanfic-button-icon">' . $icon . '</span>';
		$output .= '<span class="fanfic-button-text">' . esc_html( $label ) . '</span>';
		$output .= '</button>';

		return $output;
	}

	/**
	 * Get button current state
	 *
	 * @since 2.0.0
	 * @param string $action      Action type.
	 * @param array  $context_ids Context IDs.
	 * @param int    $user_id     User ID.
	 * @return bool Current state (true if active).
	 */
	private static function get_button_state( $action, $context_ids, $user_id ) {
		// Guest users don't have saved states
		if ( ! $user_id ) {
			return false;
		}

		switch ( $action ) {
			case 'bookmark':
				if ( isset( $context_ids['story_id'] ) ) {
					// Fanfic_Bookmarks::is_bookmarked( $user_id, $post_id, $bookmark_type )
					return Fanfic_Bookmarks::is_bookmarked( $user_id, $context_ids['story_id'], 'story' );
				}
				return false;

			case 'follow':
				// Determine follow type based on available IDs
				if ( isset( $context_ids['story_id'] ) && ! isset( $context_ids['chapter_id'] ) ) {
					// Story context - follow the story
					return Fanfic_Follows::is_following( $user_id, $context_ids['story_id'], 'story' );
				} elseif ( isset( $context_ids['author_id'] ) ) {
					// Author context - follow the author
					return Fanfic_Follows::is_following( $user_id, $context_ids['author_id'], 'author' );
				}
				return false;

			case 'like':
				if ( isset( $context_ids['chapter_id'] ) ) {
					// Fanfic_Like_System::user_has_liked( $chapter_id, $user_id )
					return Fanfic_Like_System::user_has_liked( $context_ids['chapter_id'], $user_id );
				}
				return false;

			case 'mark-read':
				// For mark-read, check if THIS specific chapter has been marked as read
				if ( isset( $context_ids['story_id'] ) && isset( $context_ids['chapter_number'] ) ) {
					// Use the Reading Progress class method for accurate chapter-specific check
					return Fanfic_Reading_Progress::is_chapter_read( $user_id, $context_ids['story_id'], $context_ids['chapter_number'] );
				}
				return false;

			case 'subscribe':
				// Check if user has email subscription for this story
				if ( isset( $context_ids['story_id'] ) ) {
					global $wpdb;
					$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';
					$user = wp_get_current_user();
					if ( ! $user->exists() ) {
						return false;
					}
					$subscribed = $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$table_name} WHERE target_id = %d AND email = %s AND subscription_type = 'story' AND verified = 1",
						$context_ids['story_id'],
						$user->user_email
					) );
					return $subscribed > 0;
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Get button label
	 *
	 * @since 2.0.0
	 * @param string $action        Action type.
	 * @param bool   $current_state Current state.
	 * @return string Button label.
	 */
	private static function get_button_label( $action, $current_state ) {
		$labels = array(
			'bookmark' => array(
				'inactive' => __( 'Bookmark', 'fanfiction-manager' ),
				'active'   => __( 'Bookmarked', 'fanfiction-manager' ),
			),
			'subscribe' => array(
				'inactive' => __( 'Subscribe', 'fanfiction-manager' ),
				'active'   => __( 'Subscribed', 'fanfiction-manager' ),
			),
			'follow' => array(
				'inactive' => __( 'Follow', 'fanfiction-manager' ),
				'active'   => __( 'Following', 'fanfiction-manager' ),
			),
			'like' => array(
				'inactive' => __( 'Like', 'fanfiction-manager' ),
				'active'   => __( 'Liked', 'fanfiction-manager' ),
			),
			'mark-read' => array(
				'inactive' => __( 'Mark as Read', 'fanfiction-manager' ),
				'active'   => __( 'Read', 'fanfiction-manager' ),
			),
			'share' => array(
				'inactive' => __( 'Share', 'fanfiction-manager' ),
				'active'   => __( 'Share', 'fanfiction-manager' ),
			),
			'report' => array(
				'inactive' => __( 'Report', 'fanfiction-manager' ),
				'active'   => __( 'Report', 'fanfiction-manager' ),
			),
			'edit' => array(
				'inactive' => __( 'Edit', 'fanfiction-manager' ),
				'active'   => __( 'Edit', 'fanfiction-manager' ),
			),
		);

		$state = $current_state ? 'active' : 'inactive';
		return isset( $labels[ $action ][ $state ] ) ? $labels[ $action ][ $state ] : ucfirst( $action );
	}

	/**
	 * Get button icon
	 *
	 * @since 2.0.0
	 * @param string $action        Action type.
	 * @param bool   $current_state Current state.
	 * @return string Icon HTML/entity.
	 */
	private static function get_button_icon( $action, $current_state ) {
		$icons = array(
			'bookmark' => array(
				'inactive' => '&#128278;', // Bookmark outline
				'active'   => '&#128278;', // Bookmark filled
			),
			'subscribe' => array(
				'inactive' => '&#128276;', // Bell
				'active'   => '&#128276;', // Bell
			),
			'follow' => array(
				'inactive' => '&#10133;', // Plus
				'active'   => '&#10003;', // Check
			),
			'like' => array(
				'inactive' => '&#9829;', // Heart outline
				'active'   => '&#9829;', // Heart filled
			),
			'mark-read' => array(
				'inactive' => '&#128214;', // Book
				'active'   => '&#10003;', // Check
			),
			'share' => array(
				'inactive' => '&#128279;', // Link/share
				'active'   => '&#128279;', // Link/share
			),
			'report' => array(
				'inactive' => '&#9888;', // Warning
				'active'   => '&#9888;', // Warning
			),
			'edit' => array(
				'inactive' => '&#9998;', // Pencil
				'active'   => '&#9998;', // Pencil
			),
		);

		$state = $current_state ? 'active' : 'inactive';
		return isset( $icons[ $action ][ $state ] ) ? $icons[ $action ][ $state ] : '';
	}

	/**
	 * Get button ARIA label for accessibility
	 *
	 * @since 2.0.0
	 * @param string $action        Action type.
	 * @param bool   $current_state Current state.
	 * @param string $context       Context (story, chapter, author).
	 * @return string ARIA label.
	 */
	private static function get_button_aria_label( $action, $current_state, $context ) {
		// Map context to object type for labels
		$object_type = 'story';
		if ( 'chapter' === $context ) {
			$object_type = 'chapter';
		} elseif ( 'author' === $context ) {
			$object_type = 'author';
		}

		$labels = array(
			'bookmark' => array(
				'inactive' => sprintf( __( 'Bookmark this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Remove bookmark from this %s', 'fanfiction-manager' ), $object_type ),
			),
			'subscribe' => array(
				'inactive' => sprintf( __( 'Subscribe to updates for this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Unsubscribe from this %s', 'fanfiction-manager' ), $object_type ),
			),
			'follow' => array(
				'inactive' => __( 'Follow this author', 'fanfiction-manager' ),
				'active'   => __( 'Unfollow this author', 'fanfiction-manager' ),
			),
			'like' => array(
				'inactive' => sprintf( __( 'Like this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Unlike this %s', 'fanfiction-manager' ), $object_type ),
			),
			'mark-read' => array(
				'inactive' => __( 'Mark this chapter as read', 'fanfiction-manager' ),
				'active'   => __( 'Chapter marked as read', 'fanfiction-manager' ),
			),
			'share' => array(
				'inactive' => sprintf( __( 'Share this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Share this %s', 'fanfiction-manager' ), $object_type ),
			),
			'report' => array(
				'inactive' => sprintf( __( 'Report this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Report this %s', 'fanfiction-manager' ), $object_type ),
			),
			'edit' => array(
				'inactive' => sprintf( __( 'Edit this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Edit this %s', 'fanfiction-manager' ), $object_type ),
			),
		);

		$state = $current_state ? 'active' : 'inactive';
		return isset( $labels[ $action ][ $state ] ) ? $labels[ $action ][ $state ] : ucfirst( $action );
	}
}
