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
 * Context-aware action buttons (follow, like, subscribe, etc.).
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
		add_shortcode( 'fanfic-like-dislike', array( __CLASS__, 'like_dislike_buttons' ) );
	}

	/**
	 * Context-aware action buttons shortcode
	 *
	 * [fanfiction-action-buttons]
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Action buttons HTML.
	 */
	public static function action_buttons( $atts ) {
		$context = self::detect_context();

		// Return empty if context couldn't be determined
		if ( ! $context ) {
			return '';
		}

		// Get available actions for this context
		$available_actions = self::get_context_actions( $context );

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
		$output = '<div class="fanfic-buttons fanfic-buttons-' . esc_attr( $context ) . '" data-context="' . esc_attr( $context ) . '">';

		$has_dislike = in_array( 'dislike', $available_actions, true );
		$skip_dislike = false;

		foreach ( $available_actions as $action ) {
			// Skip dislike if already rendered inside segmented like/dislike group.
			if ( 'dislike' === $action && $skip_dislike ) {
				continue;
			}

			// Segmented like/dislike group (YouTube-style).
			if ( 'like' === $action && $has_dislike ) {
				$output .= '<div class="fanfic-segmented-like-dislike">';
				$output .= self::render_button( 'like', $context, $context_ids, $nonce, 'segmented-start' );
				$output .= self::render_button( 'dislike', $context, $context_ids, $nonce, 'segmented-end' );
				$output .= '</div>';
				$skip_dislike = true;
				continue;
			}

			$output .= self::render_button( $action, $context, $context_ids, $nonce );
		}

		$output .= '</div>';

		// Render follow email modal once per page (for logged-out users).
		if ( ! is_user_logged_in() ) {
			$output .= self::render_follow_email_modal();
		}

		return $output;
	}

	/**
	 * Standalone like/dislike segmented buttons shortcode
	 *
	 * [fanfic-like-dislike]
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Like/dislike buttons HTML.
	 */
	public static function like_dislike_buttons( $atts ) {
		$context = self::detect_context();

		if ( 'chapter' !== $context ) {
			return '';
		}

		$context_ids = self::get_context_ids( $context );
		if ( ! $context_ids ) {
			return '';
		}

		$enable_likes    = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_likes', true ) : true;
		$enable_dislikes = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_dislikes', false ) : false;

		if ( ! $enable_likes && ! $enable_dislikes ) {
			return '';
		}

		$nonce  = wp_create_nonce( 'fanfic_ajax_nonce' );
		$output = '';

		if ( $enable_likes && $enable_dislikes ) {
			$output .= '<div class="fanfic-segmented-like-dislike">';
			$output .= self::render_button( 'like', $context, $context_ids, $nonce, 'segmented-start' );
			$output .= self::render_button( 'dislike', $context, $context_ids, $nonce, 'segmented-end' );
			$output .= '</div>';
		} elseif ( $enable_likes ) {
			$output .= self::render_button( 'like', $context, $context_ids, $nonce );
		} elseif ( $enable_dislikes ) {
			$output .= self::render_button( 'dislike', $context, $context_ids, $nonce );
		}

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
		$enable_likes = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_likes', true ) : true;
		$enable_dislikes = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_dislikes', false ) : false;
		$enable_share = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_share', true ) : true;
		$enable_report = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_report', true ) : true;

		$actions = array();

		switch ( $context ) {
			case 'story':
				$actions = array( 'follow', 'share', 'report', 'edit' );
				break;

			case 'chapter':
				$actions = array( 'follow', 'mark-read', 'share', 'report', 'edit' );
				break;

			case 'author':
				$actions = array( 'share' );
				break;
		}

		// Apply settings toggles.
		if ( ! $enable_likes ) {
			$actions = array_diff( $actions, array( 'like' ) );
		}
		if ( ! $enable_dislikes ) {
			$actions = array_diff( $actions, array( 'dislike' ) );
		}
		if ( ! $enable_share ) {
			$actions = array_diff( $actions, array( 'share' ) );
		}
		if ( ! $enable_report ) {
			$actions = array_diff( $actions, array( 'report' ) );
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
	 * @param string $action    Action type.
	 * @param string $context   Context (story, chapter, author).
	 * @param array  $context_ids Context IDs.
	 * @param string $nonce     AJAX nonce.
	 * @param string $segmented Segmented position: '' (none), 'segmented-start', 'segmented-end'.
	 * @return string Button HTML.
	 */
	private static function render_button( $action, $context, $context_ids, $nonce, $segmented = '' ) {
		$user_id = get_current_user_id();

		// Handle Edit button separately (it's a link, not a button)
		if ( 'edit' === $action ) {
			return self::render_edit_link( $context, $context_ids, $user_id );
		}

		// Handle Report button separately (opens modal/form)
		if ( 'report' === $action ) {
			return self::render_report_button( $context, $context_ids, $nonce );
		}

		// Check if action requires login and user is not logged in
		$requires_login = self::action_requires_login( $action );
		$is_disabled = $requires_login && ! $user_id;

		// Get current state for toggle buttons
		$current_state = self::get_button_state( $action, $context_ids, $user_id );

		// Segmented like/dislike buttons skip the heavy fanfic-button base class.
		$is_segmented = ( '' !== $segmented );
		$base_class   = $is_segmented ? 'fanfic-action-btn' : 'fanfic-button';

		// Build button classes
		$classes = array(
			$base_class,
			'fanfic-button-' . $action,
		);

		if ( $current_state ) {
			if ( 'mark-read' === $action ) {
				$classes[] = 'fanfic-button-marked-read';
			} else {
				$classes[] = 'fanfic-button-' . $action . 'ed';
			}
		}

		// Add segmented position class.
		if ( $is_segmented ) {
			$classes[] = 'fanfic-' . $segmented;
		}

		// Add disabled class for login-required buttons when not logged in
		if ( $is_disabled ) {
			$classes[] = 'is-disabled';
			$classes[] = 'requires-login';
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
			// Add post-id and story-id for follow buttons (localStorage key = story_N_chapter_M)
			if ( isset( $context_ids['chapter_id'] ) ) {
				$data_attrs['data-post-id']    = $context_ids['chapter_id'];
				$data_attrs['data-story-id']   = isset( $context_ids['story_id'] ) ? $context_ids['story_id'] : 0;
				$data_attrs['data-chapter-id'] = $context_ids['chapter_id'];
			} elseif ( isset( $context_ids['story_id'] ) ) {
				$data_attrs['data-post-id']    = $context_ids['story_id'];
				$data_attrs['data-story-id']   = $context_ids['story_id'];
				$data_attrs['data-chapter-id'] = 0;
			}
			// Tell JS whether user is logged in (for follow email modal).
			$data_attrs['data-user-logged-in'] = $user_id ? '1' : '0';
		} elseif ( 'share' === $action ) {
			$share_data = self::get_share_context_data( $context, $context_ids );

			if ( ! empty( $share_data['url'] ) ) {
				$data_attrs['data-share-url'] = $share_data['url'];
			}

			if ( ! empty( $share_data['title'] ) ) {
				$data_attrs['data-share-title'] = $share_data['title'];
			}

			if ( ! empty( $share_data['text'] ) ) {
				$data_attrs['data-share-text'] = $share_data['text'];
			}
		}

		// Get button label and icon
		$label = self::get_button_label( $action, $current_state, $context );
		$icon = self::get_button_icon( $action, $current_state );
		$aria_label = self::get_button_aria_label( $action, $current_state, $context );

		// Get inactive and active labels for JavaScript toggle
		$inactive_label = self::get_button_label( $action, false, $context );
		$active_label = self::get_button_label( $action, true, $context );

		// Get text class name for JavaScript updates
		$text_class = self::get_text_class_for_action( $action );

		// Build button HTML
		$output = '<button class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		foreach ( $data_attrs as $key => $value ) {
			$output .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}

		// Add data attributes for text toggling (used by JavaScript)
		if ( $text_class ) {
			$data_attr_names = self::get_data_attr_names_for_action( $action );
			$output .= ' data-' . esc_attr( $data_attr_names['inactive'] ) . '="' . esc_attr( $inactive_label ) . '"';
			$output .= ' data-' . esc_attr( $data_attr_names['active'] ) . '="' . esc_attr( $active_label ) . '"';
		}

		// Add disabled attribute and login message for login-required buttons
		if ( $is_disabled ) {
			$output .= ' disabled';
			$output .= ' data-login-message="' . esc_attr__( 'You must be logged in to use this feature', 'fanfiction-manager' ) . '"';
		}

		$output .= ' aria-label="' . esc_attr( $aria_label ) . '"';
		$output .= ' type="button">';
		$output .= '<span class="fanfic-button-icon">' . $icon . '</span>';

		// Segmented buttons hide text label but still show count.
		$hide_text_label = ( '' !== $segmented );

		if ( ! $hide_text_label ) {
			$output .= '<span class="fanfic-button-text ' . ( $text_class ? esc_attr( $text_class ) : '' ) . '">' . esc_html( $label ) . '</span>';
		}

		// Add count display for like/dislike buttons.
		if ( 'like' === $action && isset( $context_ids['chapter_id'] ) ) {
			$like_count = Fanfic_Interactions::get_chapter_likes( $context_ids['chapter_id'] );
			$count_text = absint( $like_count ) > 0 ? '(' . Fanfic_Shortcodes::format_engagement_number( $like_count ) . ')' : '';
			$output .= '<span class="fanfic-button-count like-count" data-count="' . absint( $like_count ) . '" title="' . esc_attr( Fanfic_Shortcodes::format_number( $like_count ) ) . '" aria-label="' . esc_attr( Fanfic_Shortcodes::format_number( $like_count ) ) . '">' . esc_html( $count_text ) . '</span>';
		}
		if ( 'dislike' === $action && isset( $context_ids['chapter_id'] ) ) {
			$stats         = Fanfic_Interactions::get_chapter_stats( $context_ids['chapter_id'] );
			$dislike_count = absint( $stats['dislikes'] ?? 0 );
			$count_text    = $dislike_count > 0 ? '(' . Fanfic_Shortcodes::format_engagement_number( $dislike_count ) . ')' : '';
			$output .= '<span class="fanfic-button-count dislike-count" data-count="' . $dislike_count . '" title="' . esc_attr( Fanfic_Shortcodes::format_number( $dislike_count ) ) . '" aria-label="' . esc_attr( Fanfic_Shortcodes::format_number( $dislike_count ) ) . '">' . esc_html( $count_text ) . '</span>';
		}

		$output .= '</button>';

		return $output;
	}

	/**
	 * Build canonical share data for story/chapter/author contexts.
	 *
	 * @since 2.0.0
	 * @param string $context Context (story, chapter, author).
	 * @param array  $context_ids Context IDs.
	 * @return array{url:string,title:string,text:string} Share data.
	 */
	private static function get_share_context_data( $context, $context_ids ) {
		$share_data = array(
			'url'   => '',
			'title' => '',
			'text'  => '',
		);

		switch ( $context ) {
			case 'story':
				$story_id = isset( $context_ids['story_id'] ) ? absint( $context_ids['story_id'] ) : 0;
				if ( ! $story_id ) {
					break;
				}

				$story = get_post( $story_id );
				if ( ! $story ) {
					break;
				}

				$share_data['url'] = function_exists( 'fanfic_get_story_url' ) ? fanfic_get_story_url( $story_id ) : get_permalink( $story_id );
				$share_data['title'] = get_the_title( $story_id );
				$share_data['text']  = self::normalize_share_text( $story->post_excerpt );
				break;

			case 'chapter':
				$chapter_id = isset( $context_ids['chapter_id'] ) ? absint( $context_ids['chapter_id'] ) : 0;
				if ( ! $chapter_id ) {
					break;
				}

				$chapter = get_post( $chapter_id );
				if ( ! $chapter ) {
					break;
				}

				$share_data['url']   = function_exists( 'fanfic_get_chapter_url' ) ? fanfic_get_chapter_url( $chapter_id ) : get_permalink( $chapter_id );
				$share_data['title'] = get_the_title( $chapter_id );
				$share_data['text']  = self::normalize_share_text( $chapter->post_excerpt );

				if ( '' === $share_data['text'] ) {
					$story_id = isset( $context_ids['story_id'] ) ? absint( $context_ids['story_id'] ) : absint( $chapter->post_parent );
					if ( $story_id ) {
						$story = get_post( $story_id );
						if ( $story ) {
							$share_data['text'] = self::normalize_share_text( $story->post_excerpt );
						}
					}
				}
				break;

			case 'author':
				$author_id = isset( $context_ids['author_id'] ) ? absint( $context_ids['author_id'] ) : 0;
				if ( ! $author_id ) {
					break;
				}

				$profile_url = function_exists( 'fanfic_get_user_profile_url' ) ? fanfic_get_user_profile_url( $author_id ) : '';
				$share_data['url'] = ! empty( $profile_url ) ? $profile_url : get_author_posts_url( $author_id );
				$share_data['title'] = (string) get_the_author_meta( 'display_name', $author_id );
				$share_data['text']  = self::normalize_share_text( get_the_author_meta( 'description', $author_id ) );
				break;
		}

		$share_data['url']   = '' !== $share_data['url'] ? esc_url_raw( $share_data['url'] ) : '';
		$share_data['title'] = '' !== $share_data['title'] ? wp_strip_all_tags( (string) $share_data['title'] ) : '';

		return $share_data;
	}

	/**
	 * Normalize optional share text and cap length for the Web Share API payload.
	 *
	 * @since 2.0.0
	 * @param string $text Raw text.
	 * @return string Normalized text.
	 */
	private static function normalize_share_text( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		if ( '' === $text ) {
			return '';
		}

		return wp_html_excerpt( $text, 280, '...' );
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
			case 'follow':
				$follow_post_id = 0;

				if ( isset( $context_ids['chapter_id'] ) && absint( $context_ids['chapter_id'] ) > 0 ) {
					$follow_post_id = absint( $context_ids['chapter_id'] );
				} elseif ( isset( $context_ids['story_id'] ) ) {
					$follow_post_id = absint( $context_ids['story_id'] );
				}

				if ( ! $follow_post_id || ! class_exists( 'Fanfic_Follows' ) ) {
					return false;
				}

				return Fanfic_Follows::is_followed( $user_id, $follow_post_id );

			case 'like':
			case 'dislike':
				// Unified interactions use localStorage as initial UI source-of-truth.
				return false;

			case 'mark-read':
				// For mark-read, check if THIS specific chapter has been marked as read
				if ( isset( $context_ids['story_id'] ) && isset( $context_ids['chapter_number'] ) ) {
					// Use the Reading Progress class method for accurate chapter-specific check
					return Fanfic_Reading_Progress::is_chapter_read( $user_id, $context_ids['story_id'], $context_ids['chapter_number'] );
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Check if action requires login
	 *
	 * @since 2.0.0
	 * @param string $action Action type.
	 * @return bool True if login is required.
	 */
	private static function action_requires_login( $action ) {
		// Actions that require login
		$login_required = array();

		return in_array( $action, $login_required, true );
	}

	/**
	 * Get button label
	 *
	 * @since 2.0.0
	 * @param string $action        Action type.
	 * @param bool   $current_state Current state.
	 * @return string Button label.
	 */
	private static function get_button_label( $action, $current_state, $context = 'story' ) {
		$labels = array(
			'follow' => array(
				'inactive' => 'chapter' === $context ? __( 'Bookmark', 'fanfiction-manager' ) : __( 'Follow', 'fanfiction-manager' ),
				'active'   => 'chapter' === $context ? __( 'Bookmarked', 'fanfiction-manager' ) : __( 'Followed', 'fanfiction-manager' ),
			),
			'like' => array(
				'inactive' => __( 'Like', 'fanfiction-manager' ),
				'active'   => __( 'Liked', 'fanfiction-manager' ),
			),
			'dislike' => array(
				'inactive' => __( 'Dislike', 'fanfiction-manager' ),
				'active'   => __( 'Disliked', 'fanfiction-manager' ),
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
		// SVG thumbs-up/down paths (viewBox 0 0 32 32).
		// Outline paths (two sub-paths: outer contour + inner cutout).
		$thumb_up_outline   = 'M19.017 31.992c-9.088 0-9.158-0.377-10.284-1.224-0.597-0.449-1.723-0.76-5.838-1.028-0.298-0.020-0.583-0.134-0.773-0.365-0.087-0.107-2.143-3.105-2.143-7.907 0-4.732 1.472-6.89 1.534-6.99 0.182-0.293 0.503-0.47 0.847-0.47 3.378 0 8.062-4.313 11.21-11.841 0.544-1.302 0.657-2.159 2.657-2.159 1.137 0 2.413 0.815 3.042 1.86 1.291 2.135 0.636 6.721 0.029 9.171 2.063-0.017 5.796-0.045 7.572-0.045 2.471 0 4.107 1.473 4.156 3.627 0.017 0.711-0.077 1.619-0.282 2.089 0.544 0.543 1.245 1.36 1.276 2.414 0.038 1.36-0.852 2.395-1.421 2.989 0.131 0.395 0.391 0.92 0.366 1.547-0.063 1.542-1.253 2.535-1.994 3.054 0.061 0.422 0.11 1.218-0.026 1.834-0.535 2.457-4.137 3.443-9.928 3.443zM3.426 27.712c3.584 0.297 5.5 0.698 6.51 1.459 0.782 0.589 0.662 0.822 9.081 0.822 2.568 0 7.59-0.107 7.976-1.87 0.153-0.705-0.59-1.398-0.593-1.403-0.203-0.501 0.023-1.089 0.518-1.305 0.008-0.004 2.005-0.719 2.050-1.835 0.030-0.713-0.46-1.142-0.471-1.16-0.291-0.452-0.185-1.072 0.257-1.38 0.005-0.004 1.299-0.788 1.267-1.857-0.024-0.849-1.143-1.447-1.177-1.466-0.25-0.143-0.432-0.39-0.489-0.674-0.056-0.282 0.007-0.579 0.183-0.808 0 0 0.509-0.808 0.49-1.566-0.037-1.623-1.782-1.674-2.156-1.674-2.523 0-9.001 0.025-9.001 0.025-0.349 0.002-0.652-0.164-0.84-0.443s-0.201-0.627-0.092-0.944c0.977-2.813 1.523-7.228 0.616-8.736-0.267-0.445-0.328-0.889-1.328-0.889-0.139 0-0.468 0.11-0.812 0.929-3.341 7.995-8.332 12.62-12.421 13.037-0.353 0.804-1.016 2.47-1.016 5.493 0 3.085 0.977 5.473 1.447 6.245z';
		$thumb_down_outline = 'M12.982 0.007c9.088 0 9.159 0.377 10.284 1.225 0.597 0.449 1.723 0.76 5.838 1.028 0.299 0.019 0.583 0.134 0.773 0.365 0.087 0.107 2.143 3.105 2.143 7.907 0 4.732-1.471 6.89-1.534 6.991-0.183 0.292-0.503 0.469-0.848 0.469-3.378 0-8.062 4.313-11.211 11.841-0.544 1.302-0.657 2.158-2.657 2.158-1.137 0-2.412-0.814-3.043-1.86-1.291-2.135-0.636-6.721-0.028-9.171-2.063 0.017-5.796 0.045-7.572 0.045-2.471 0-4.106-1.474-4.157-3.628-0.016-0.711 0.077-1.62 0.283-2.088-0.543-0.543-1.245-1.361-1.276-2.415-0.038-1.36 0.852-2.395 1.42-2.989-0.13-0.396-0.391-0.92-0.366-1.547 0.063-1.542 1.253-2.536 1.995-3.054-0.061-0.42-0.109-1.217 0.026-1.832 0.535-2.457 4.138-3.445 9.928-3.445zM28.575 4.289c-3.584-0.296-5.5-0.698-6.51-1.459-0.782-0.588-0.661-0.822-9.082-0.822-2.568 0-7.59 0.107-7.976 1.869-0.154 0.705 0.59 1.398 0.593 1.403 0.203 0.502-0.024 1.089-0.518 1.305-0.008 0.004-2.004 0.72-2.050 1.836-0.030 0.713 0.46 1.142 0.471 1.159 0.291 0.452 0.184 1.072-0.257 1.38-0.005 0.004-1.299 0.788-1.267 1.857 0.025 0.848 1.143 1.447 1.177 1.466 0.25 0.143 0.432 0.39 0.489 0.674 0.057 0.282-0.007 0.579-0.182 0.807 0 0-0.509 0.808-0.49 1.566 0.037 1.623 1.782 1.674 2.156 1.674 2.522 0 9.001-0.026 9.001-0.026 0.35-0.001 0.652 0.164 0.839 0.444s0.202 0.627 0.091 0.945c-0.976 2.814-1.522 7.227-0.616 8.735 0.267 0.445 0.328 0.889 1.328 0.889 0.139 0 0.468-0.11 0.812-0.93 3.343-7.994 8.334-12.619 12.423-13.036 0.352-0.804 1.015-2.47 1.015-5.493-0.001-3.085-0.979-5.472-1.449-6.245z';
		// Filled paths (outer contour only).
		$thumb_up_filled   = 'M19.017 31.992c-9.088 0-9.158-0.377-10.284-1.224-0.597-0.449-1.723-0.76-5.838-1.028-0.298-0.020-0.583-0.134-0.773-0.365-0.087-0.107-2.143-3.105-2.143-7.907 0-4.732 1.472-6.89 1.534-6.99 0.182-0.293 0.503-0.47 0.847-0.47 3.378 0 8.062-4.313 11.21-11.841 0.544-1.302 0.657-2.159 2.657-2.159 1.137 0 2.413 0.815 3.042 1.86 1.291 2.135 0.636 6.721 0.029 9.171 2.063-0.017 5.796-0.045 7.572-0.045 2.471 0 4.107 1.473 4.156 3.627 0.017 0.711-0.077 1.619-0.282 2.089 0.544 0.543 1.245 1.36 1.276 2.414 0.038 1.36-0.852 2.395-1.421 2.989 0.131 0.395 0.391 0.92 0.366 1.547-0.063 1.542-1.253 2.535-1.994 3.054 0.061 0.422 0.11 1.218-0.026 1.834-0.535 2.457-4.137 3.443-9.928 3.443z';
		$thumb_down_filled = 'M12.982 0.007c9.088 0 9.159 0.377 10.284 1.225 0.597 0.449 1.723 0.76 5.838 1.028 0.299 0.019 0.583 0.134 0.773 0.365 0.087 0.107 2.143 3.105 2.143 7.907 0 4.732-1.471 6.89-1.534 6.991-0.183 0.292-0.503 0.469-0.848 0.469-3.378 0-8.062 4.313-11.211 11.841-0.544 1.302-0.657 2.158-2.657 2.158-1.137 0-2.412-0.814-3.043-1.86-1.291-2.135-0.636-6.721-0.028-9.171-2.063 0.017-5.796 0.045-7.572 0.045-2.471 0-4.106-1.474-4.157-3.628-0.016-0.711 0.077-1.62 0.283-2.088-0.543-0.543-1.245-1.361-1.276-2.415-0.038-1.36 0.852-2.395 1.42-2.989-0.13-0.396-0.391-0.92-0.366-1.547 0.063-1.542 1.253-2.536 1.995-3.054-0.061-0.42-0.109-1.217 0.026-1.832 0.535-2.457 4.138-3.445 9.928-3.445z';

		$icons = array(
			'follow' => array(
				'inactive' => '<span class="dashicons dashicons-heart" aria-hidden="true"></span>',
				'active'   => '<span class="dashicons dashicons-heart" aria-hidden="true"></span>',
			),
			'like' => array(
				'inactive' => '<svg class="fanfic-thumb-svg" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path class="fanfic-thumb-bg" d="' . esc_attr( $thumb_up_outline ) . '"/><path class="fanfic-thumb-fg" d="' . esc_attr( $thumb_up_filled ) . '"/></svg>',
				'active'   => '<svg class="fanfic-thumb-svg" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path class="fanfic-thumb-bg" d="' . esc_attr( $thumb_up_outline ) . '"/><path class="fanfic-thumb-fg" d="' . esc_attr( $thumb_up_filled ) . '"/></svg>',
			),
			'dislike' => array(
				'inactive' => '<svg class="fanfic-thumb-svg" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path class="fanfic-thumb-bg" d="' . esc_attr( $thumb_down_outline ) . '"/><path class="fanfic-thumb-fg" d="' . esc_attr( $thumb_down_filled ) . '"/></svg>',
				'active'   => '<svg class="fanfic-thumb-svg" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path class="fanfic-thumb-bg" d="' . esc_attr( $thumb_down_outline ) . '"/><path class="fanfic-thumb-fg" d="' . esc_attr( $thumb_down_filled ) . '"/></svg>',
			),
			'mark-read' => array(
				'inactive' => '',
				'active'   => '<span class="fanfic-read-check" aria-hidden="true">&#10003;</span>', // Same checkmark used by read badge
			),
			'share' => array(
				'inactive' => '<span class="dashicons dashicons-share" aria-hidden="true"></span>',
				'active'   => '<span class="dashicons dashicons-share" aria-hidden="true"></span>',
			),
			'report' => array(
				'inactive' => '<span class="dashicons dashicons-flag" aria-hidden="true"></span>',
				'active'   => '<span class="dashicons dashicons-flag" aria-hidden="true"></span>',
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
			'follow' => array(
				'inactive' => 'chapter' === $context
					? __( 'Bookmark this chapter', 'fanfiction-manager' )
					: sprintf( __( 'Follow this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => 'chapter' === $context
					? __( 'Remove bookmark from this chapter', 'fanfiction-manager' )
					: sprintf( __( 'Remove follow from this %s', 'fanfiction-manager' ), $object_type ),
			),
			'like' => array(
				'inactive' => sprintf( __( 'Like this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Unlike this %s', 'fanfiction-manager' ), $object_type ),
			),
			'dislike' => array(
				'inactive' => sprintf( __( 'Dislike this %s', 'fanfiction-manager' ), $object_type ),
				'active'   => sprintf( __( 'Remove dislike from this %s', 'fanfiction-manager' ), $object_type ),
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

	/**
	 * Get text class name for action (used by JavaScript for text updates)
	 *
	 * Maps action types to their CSS class names that JavaScript uses to update text.
	 *
	 * @since 2.0.0
	 * @param string $action Action type.
	 * @return string Text class name.
	 */
	private static function get_text_class_for_action( $action ) {
		$text_classes = array(
			'like'      => 'like-text',
			'dislike'   => 'dislike-text',
			'follow'  => 'follow-text',
			'mark-read' => 'read-text',
			// 'subscribe' is NOT a toggle button - it opens a subscription form
		);

		return isset( $text_classes[ $action ] ) ? $text_classes[ $action ] : '';
	}

	/**
	 * Get data attribute names for action (used by JavaScript for text toggling)
	 *
	 * Returns the inactive and active data attribute names that JavaScript expects.
	 *
	 * @since 2.0.0
	 * @param string $action Action type.
	 * @return array Array with 'inactive' and 'active' keys.
	 */
	private static function get_data_attr_names_for_action( $action ) {
		$data_attrs = array(
			'like' => array(
				'inactive' => 'like-text',
				'active'   => 'liked-text',
			),
			'dislike' => array(
				'inactive' => 'dislike-text',
				'active'   => 'disliked-text',
			),
			'follow' => array(
				'inactive' => 'follow-text',
				'active'   => 'followed-text',
			),
			'mark-read' => array(
				'inactive' => 'unread-text',
				'active'   => 'read-text',
			),
			// 'subscribe' is NOT a toggle button - it opens a subscription form
		);

		return isset( $data_attrs[ $action ] ) ? $data_attrs[ $action ] : array( 'inactive' => $action . '-text', 'active' => $action . 'd-text' );
	}

	/**
	 * Render edit link (for content authors only)
	 *
	 * @since 2.0.0
	 * @param string $context     Context (story, chapter, author).
	 * @param array  $context_ids Context IDs.
	 * @param int    $user_id     Current user ID.
	 * @return string Edit link HTML or empty string.
	 */
	private static function render_edit_link( $context, $context_ids, $user_id ) {
		// Get the post ID or user ID to check permissions
		$content_id = 0;
		$content_type = '';

		if ( 'chapter' === $context && isset( $context_ids['chapter_id'] ) ) {
			$content_id = $context_ids['chapter_id'];
			$content_type = 'chapter';
		} elseif ( 'story' === $context && isset( $context_ids['story_id'] ) ) {
			$content_id = $context_ids['story_id'];
			$content_type = 'story';
		} elseif ( 'author' === $context && isset( $context_ids['author_id'] ) ) {
			$content_id = $context_ids['author_id'];
			$content_type = 'profile';
		}

		if ( ! $content_id ) {
			return '';
		}

		// Check permissions using the fanfic permission function
		// This checks: author, moderators (moderate_fanfiction), and admins (manage_options)
		if ( ! fanfic_current_user_can_edit( $content_type, $content_id ) ) {
			return '';
		}

		// Get edit link
		$edit_url = '';
		if ( 'profile' === $content_type ) {
			// For author profiles, link to author dashboard or profile edit page
			$edit_url = fanfic_get_page_url( 'dashboard' );
		} elseif ( 'story' === $content_type ) {
			// For stories, use fanfic edit story URL
			$edit_url = fanfic_get_edit_story_url( $content_id );
		} elseif ( 'chapter' === $content_type ) {
			// For chapters, use fanfic edit chapter URL
			$story_id = isset( $context_ids['story_id'] ) ? $context_ids['story_id'] : 0;
			$edit_url = fanfic_get_edit_chapter_url( $content_id, $story_id );
		}

		if ( ! $edit_url ) {
			return '';
		}

		$label = __( 'Edit', 'fanfiction-manager' );
		$aria_object = $context;

		if ( 'story' === $content_type ) {
			$label = __( 'Edit Story', 'fanfiction-manager' );
			$aria_object = 'story';
		} elseif ( 'chapter' === $content_type ) {
			$label = __( 'Edit Chapter', 'fanfiction-manager' );
			$aria_object = 'chapter';
		} elseif ( 'profile' === $content_type ) {
			$label = __( 'Edit Profile', 'fanfiction-manager' );
			$aria_object = 'profile';
		}

		$icon = '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';

		// Render as link (not button) since it navigates to a different page
		// Uses same structure and classes as other action buttons for visual consistency
		$output = '<a href="' . esc_url( $edit_url ) . '" class="fanfic-button fanfic-edit-button" aria-label="' . esc_attr( sprintf( __( 'Edit this %s', 'fanfiction-manager' ), $aria_object ) ) . '" role="button">';
		$output .= '<span class="fanfic-button-icon">' . $icon . '</span>';
		$output .= '<span class="fanfic-button-text">' . esc_html( $label ) . '</span>';
		$output .= '</a>';

		return $output;
	}

	/**
	 * Render report button
	 *
	 * @since 2.0.0
	 * @param string $context     Context (story, chapter, author).
	 * @param array  $context_ids Context IDs.
	 * @param string $nonce       AJAX nonce.
	 * @return string Report button HTML.
	 */
	private static function render_report_button( $context, $context_ids, $nonce ) {
		// Get the content ID to report
		$content_id = 0;
		$report_type = '';

		if ( 'chapter' === $context && isset( $context_ids['chapter_id'] ) ) {
			$content_id = $context_ids['chapter_id'];
			$report_type = 'chapter';
		} elseif ( 'story' === $context && isset( $context_ids['story_id'] ) ) {
			$content_id = $context_ids['story_id'];
			$report_type = 'story';
		} elseif ( 'author' === $context && isset( $context_ids['author_id'] ) ) {
			$content_id = $context_ids['author_id'];
			$report_type = 'author';
		}

		if ( ! $content_id ) {
			return '';
		}

		$label = __( 'Report', 'fanfiction-manager' );
		$icon = '<span class="dashicons dashicons-flag" aria-hidden="true"></span>';

		$output = '<button type="button" class="fanfic-button fanfic-report-button" ';
		$output .= 'data-content-id="' . absint( $content_id ) . '" ';
		$output .= 'data-report-type="' . esc_attr( $report_type ) . '" ';
		$output .= 'data-nonce="' . esc_attr( $nonce ) . '" ';
		$output .= 'aria-label="' . esc_attr( sprintf( __( 'Report this %s', 'fanfiction-manager' ), $context ) ) . '">';
		$output .= '<span class="fanfic-button-icon">' . $icon . '</span>';
		$output .= '<span class="fanfic-button-text">' . esc_html( $label ) . '</span>';
		$output .= '</button>';

		return $output;
	}

	/**
	 * Whether the follow email modal has already been rendered on this page.
	 *
	 * @var bool
	 */
	private static $follow_modal_rendered = false;

	/**
	 * Render the follow email modal (once per page).
	 *
	 * Shown to logged-out users when they click Follow. Lets them optionally
	 * provide an email address to receive story update emails.
	 *
	 * @since 1.8.0
	 * @return string Modal HTML, or empty string if already rendered.
	 */
	public static function render_follow_email_modal() {
		if ( self::$follow_modal_rendered ) {
			return '';
		}
		self::$follow_modal_rendered = true;

		ob_start();
		?>
		<div id="fanfic-follow-email-modal" class="fanfic-modal fanfic-follow-email-modal" role="dialog" aria-hidden="true" aria-labelledby="fanfic-follow-email-modal-title" style="display:none;">
			<div class="fanfic-modal-overlay"></div>
			<div class="fanfic-modal-content">
				<button type="button" class="fanfic-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fanfiction-manager' ); ?>">&times;</button>
				<h2 id="fanfic-follow-email-modal-title"><?php esc_html_e( 'Would you like to receive email updates?', 'fanfiction-manager' ); ?></h2>
				<p><?php esc_html_e( 'Get notified when new chapters are published for this story.', 'fanfiction-manager' ); ?></p>

				<div class="form-group">
					<label for="fanfic-follow-email-input">
						<?php esc_html_e( 'Email Address', 'fanfiction-manager' ); ?>
					</label>
					<input
						type="email"
						id="fanfic-follow-email-input"
						name="email"
						placeholder="<?php esc_attr_e( 'your@email.com', 'fanfiction-manager' ); ?>"
						autocomplete="email"
					/>
				</div>

				<div class="form-actions">
					<button type="button" id="fanfic-follow-subscribe-btn" class="fanfic-button">
						<?php esc_html_e( 'Follow & Subscribe', 'fanfiction-manager' ); ?>
					</button>
					<button type="button" id="fanfic-follow-only-btn" class="fanfic-button secondary">
						<?php esc_html_e( 'Follow Only', 'fanfiction-manager' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
