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

	$story = get_post( $story_id );
	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return __( 'This story has been blocked. If you believe this is a mistake, please contact the site administrator.', 'fanfiction-manager' );
	}

	$block_type   = get_post_meta( $story_id, '_fanfic_block_type', true );
	$block_reason = get_post_meta( $story_id, '_fanfic_block_reason', true );
	$block_reason_text = get_post_meta( $story_id, '_fanfic_block_reason_text', true );
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
				__( 'This story was automatically set to hidden%s because site content rules have changed. %s', 'fanfiction-manager' ),
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
					fanfic_get_block_reason_label( $block_reason )
				);
				if ( $block_reason_text ) {
					$message .= ' - ' . sanitize_text_field( $block_reason_text );
				}
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
 * Get report reason labels.
 *
 * Canonical moderation reason list shared by reports and moderator block actions.
 *
 * @since 2.3.0
 * @return array<string,string>
 */
function fanfic_get_report_reason_labels() {
	return array(
		'spam'            => __( 'Spam', 'fanfiction-manager' ),
		'harassment'      => __( 'Harassment or Bullying', 'fanfiction-manager' ),
		'inappropriate'   => __( 'Inappropriate Content', 'fanfiction-manager' ),
		'copyright'       => __( 'Copyright Violation', 'fanfiction-manager' ),
		'rating_mismatch' => __( 'Rating / Warning Mismatch', 'fanfiction-manager' ),
		'other'           => __( 'Other', 'fanfiction-manager' ),
	);
}

/**
 * Get block reason labels.
 *
 * @since 1.0.0
 * @return array<string,string>
 */
function fanfic_get_block_reason_labels() {
	return fanfic_get_report_reason_labels();
}

/**
 * Get a human-readable block reason label.
 *
 * @since 1.0.0
 * @param string $block_reason Block reason code or freeform text.
 * @return string
 */
function fanfic_get_block_reason_label( $block_reason ) {
	$block_reason = is_string( $block_reason ) ? trim( $block_reason ) : '';
	if ( '' === $block_reason ) {
		return '';
	}

	$labels = fanfic_get_block_reason_labels();
	return isset( $labels[ $block_reason ] ) ? $labels[ $block_reason ] : $block_reason;
}

/**
 * Check whether a block reason is a defined moderation reason code.
 *
 * @since 2.3.0
 * @param string $block_reason Block reason code.
 * @return bool
 */
function fanfic_is_valid_block_reason_code( $block_reason ) {
	$block_reason = is_string( $block_reason ) ? trim( $block_reason ) : '';
	if ( '' === $block_reason ) {
		return false;
	}

	$labels = fanfic_get_block_reason_labels();
	return isset( $labels[ $block_reason ] );
}

/**
 * Normalize a moderator-entered block reason code.
 *
 * @since 2.3.0
 * @param string $block_reason Submitted block reason.
 * @param string $fallback     Fallback reason code.
 * @return string
 */
function fanfic_normalize_block_reason_code( $block_reason, $fallback = 'other' ) {
	$block_reason = is_string( $block_reason ) ? trim( $block_reason ) : '';
	$fallback     = is_string( $fallback ) ? trim( $fallback ) : 'other';

	if ( fanfic_is_valid_block_reason_code( $block_reason ) ) {
		return $block_reason;
	}

	return fanfic_is_valid_block_reason_code( $fallback ) ? $fallback : 'other';
}

/**
 * Normalize moderator-entered block reason text.
 *
 * @since 2.3.0
 * @param string $reason_text Submitted free-text details.
 * @param int    $max_length  Maximum length.
 * @return string
 */
function fanfic_normalize_block_reason_text( $reason_text, $max_length = 1000 ) {
	$reason_text = sanitize_textarea_field( (string) $reason_text );
	$max_length  = max( 1, absint( $max_length ) );

	if ( function_exists( 'mb_strlen' ) && mb_strlen( $reason_text ) > $max_length ) {
		$reason_text = mb_substr( $reason_text, 0, $max_length );
	} elseif ( strlen( $reason_text ) > $max_length ) {
		$reason_text = substr( $reason_text, 0, $max_length );
	}

	return trim( $reason_text );
}

/**
 * Check whether block reason text exceeds the allowed limit.
 *
 * @since 2.3.0
 * @param string $reason_text Submitted free-text details.
 * @param int    $max_length  Maximum length.
 * @return bool
 */
function fanfic_block_reason_text_exceeds_limit( $reason_text, $max_length = 1000 ) {
	$reason_text = sanitize_textarea_field( (string) $reason_text );
	$max_length  = max( 1, absint( $max_length ) );

	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $reason_text ) > $max_length;
	}

	return strlen( $reason_text ) > $max_length;
}

/**
 * Get the block flag meta key for a post.
 *
 * @since 1.0.0
 * @param int|WP_Post $post Post object or ID.
 * @return string
 */
function fanfic_get_block_flag_meta_key( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	if ( 'fanfiction_story' === $post->post_type ) {
		return '_fanfic_story_blocked';
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		return '_fanfic_chapter_blocked';
	}

	return '';
}

/**
 * Check whether a chapter is blocked.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter ID.
 * @return bool
 */
function fanfic_is_chapter_blocked( $chapter_id ) {
	return (bool) get_post_meta( $chapter_id, '_fanfic_chapter_blocked', true );
}

/**
 * Check whether a post is blocked.
 *
 * @since 1.0.0
 * @param int $post_id Post ID.
 * @return bool
 */
function fanfic_is_post_blocked( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	if ( 'fanfiction_story' === $post->post_type ) {
		return fanfic_is_story_blocked( $post_id );
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		return fanfic_is_chapter_blocked( $post_id );
	}

	return false;
}

/**
 * Get supported frontend preview modes for privileged users.
 *
 * @since 1.0.0
 * @param string $context Optional page context.
 * @return array<string,array<string,string>>
 */
function fanfic_get_frontend_preview_modes( $context = '' ) {
	$context = '' !== $context ? sanitize_key( (string) $context ) : fanfic_get_frontend_preview_context();

	$modes = array(
		'admin'  => array(
			'label' => __( 'See as admin', 'fanfiction-manager' ),
		),
		'author' => array(
			'label' => __( 'See as Author', 'fanfiction-manager' ),
		),
		'guest'  => array(
			'label' => __( 'See as guest', 'fanfiction-manager' ),
		),
		'banned' => array(
			'label' => __( 'See as banned user', 'fanfiction-manager' ),
		),
	);

	if ( in_array( $context, array( 'dashboard', 'edit-story', 'edit-chapter', 'edit-profile' ), true ) ) {
		unset( $modes['guest'], $modes['banned'] );
	}

	/**
	 * Filter the available frontend preview modes for the current request.
	 *
	 * @since 2.6.0
	 * @param array<string,array<string,string>> $modes   Available modes.
	 * @param string                             $context Current request context.
	 */
	return apply_filters( 'fanfic_frontend_preview_modes', $modes, $context );
}

/**
 * Detect the current frontend preview context.
 *
 * @since 2.6.0
 * @return string
 */
function fanfic_get_frontend_preview_context() {
	$fanfic_page = sanitize_key( (string) get_query_var( 'fanfic_page' ) );
	$action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

	if ( 'dashboard' === $fanfic_page ) {
		return 'dashboard';
	}

	if ( 'member_profile' === $fanfic_page && 'edit' === $action ) {
		return 'edit-profile';
	}

	if ( is_singular( 'fanfiction_story' ) && 'edit' === $action ) {
		return 'edit-story';
	}

	if ( is_singular( 'fanfiction_chapter' ) && 'edit' === $action ) {
		return 'edit-chapter';
	}

	return '';
}

/**
 * Check whether the current user can switch frontend preview modes.
 *
 * @since 1.0.0
 * @return bool
 */
function fanfic_current_user_can_switch_frontend_preview() {
	return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'manage_fanfiction_settings' ) );
}

/**
 * Check whether the current user should see frontend moderation controls.
 *
 * Fanfiction admins use manage_fanfiction_settings instead of manage_options,
 * so treat that capability the same as moderator/admin access on the frontend.
 *
 * @since 2.6.0
 * @return bool
 */
function fanfic_current_user_can_use_moderation_controls() {
	return fanfic_current_user_has_preview_cap( 'manage_options' )
		|| fanfic_current_user_has_preview_cap( 'manage_fanfiction_settings' )
		|| fanfic_current_user_has_preview_cap( 'moderate_fanfiction' );
}

/**
 * Sanitize a frontend preview mode value.
 *
 * @since 1.0.0
 * @param string $mode Requested mode.
 * @return string
 */
function fanfic_sanitize_frontend_preview_mode( $mode ) {
	$mode  = sanitize_key( (string) $mode );
	$modes = fanfic_get_frontend_preview_modes();

	return isset( $modes[ $mode ] ) ? $mode : 'admin';
}

/**
 * Get the active frontend preview mode for the current user.
 *
 * @since 1.0.0
 * @return string
 */
function fanfic_get_frontend_preview_mode() {
	if ( ! fanfic_current_user_can_switch_frontend_preview() ) {
		return 'admin';
	}

	$stored_mode = get_user_meta( get_current_user_id(), 'fanfic_frontend_preview_mode', true );
	return fanfic_sanitize_frontend_preview_mode( $stored_mode );
}

/**
 * Check whether a specific frontend preview mode is active.
 *
 * @since 1.0.0
 * @param string $mode Preview mode.
 * @return bool
 */
function fanfic_is_frontend_preview_mode( $mode ) {
	return fanfic_sanitize_frontend_preview_mode( $mode ) === fanfic_get_frontend_preview_mode();
}

/**
 * Whether frontend fanfiction rendering should behave as logged out.
 *
 * This does not change the underlying WordPress session; it only affects
 * plugin rendering decisions so privileged users can inspect guest access
 * without losing the admin bar.
 *
 * @since 1.0.0
 * @return bool
 */
function fanfic_effective_is_user_logged_in() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$preview_mode = function_exists( 'fanfic_get_frontend_preview_mode' ) ? fanfic_get_frontend_preview_mode() : 'admin';
	return ! in_array( $preview_mode, array( 'guest', 'banned' ), true );
}

/**
 * Get the effective current user ID for fanfiction frontend rendering.
 *
 * @since 1.0.0
 * @return int
 */
function fanfic_get_effective_current_user_id() {
	return fanfic_effective_is_user_logged_in() ? get_current_user_id() : 0;
}

/**
 * Get the effective role list for frontend fanfiction rendering.
 *
 * @since 1.0.0
 * @param int $user_id Optional user ID. Defaults to current effective user.
 * @return array<int,string>
 */
function fanfic_get_effective_user_roles( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = fanfic_get_effective_current_user_id();
	}

	if ( $user_id <= 0 ) {
		return array();
	}

	$preview_mode = fanfic_get_frontend_preview_mode();
	if ( 'author' === $preview_mode ) {
		return array( 'fanfiction_author' );
	}

	if ( 'banned' === $preview_mode ) {
		return array( 'fanfiction_banned_user' );
	}

	$user = get_userdata( $user_id );
	return $user ? (array) $user->roles : array();
}

/**
 * Check whether the current preview context should act as content owner.
 *
 * Author preview is intentionally limited to the currently viewed story/chapter
 * context so admins can inspect author-facing states without inheriting sitewide
 * ownership of unrelated content.
 *
 * @since 1.0.0
 * @param int $post_id Post ID.
 * @return bool
 */
function fanfic_preview_treats_current_user_as_content_owner( $post_id ) {
	$post_id = absint( $post_id );
	if ( $post_id <= 0 || ! fanfic_is_frontend_preview_mode( 'author' ) ) {
		return false;
	}

	$queried_id = get_queried_object_id();
	if ( $queried_id <= 0 ) {
		return false;
	}

	if ( $post_id === $queried_id ) {
		return true;
	}

	$queried_post = get_post( $queried_id );
	if ( ! $queried_post ) {
		return false;
	}

	if ( 'fanfiction_chapter' === $queried_post->post_type && (int) $queried_post->post_parent === $post_id ) {
		return true;
	}

	return false;
}

/**
 * Check frontend preview capabilities for fanfiction-only rendering.
 *
 * @since 1.0.0
 * @param string $capability Fanfiction capability.
 * @param int    $object_id  Optional object ID for meta-cap-like checks.
 * @return bool
 */
function fanfic_current_user_has_preview_cap( $capability, $object_id = 0 ) {
	$capability   = sanitize_key( (string) $capability );
	$object_id    = absint( $object_id );
	$preview_mode = fanfic_get_frontend_preview_mode();

	if ( 'admin' === $preview_mode ) {
		return current_user_can( $capability, $object_id );
	}

	if ( 'guest' === $preview_mode ) {
		return false;
	}

	if ( 'banned' === $preview_mode ) {
		return false;
	}

	if ( 'author' !== $preview_mode ) {
		return false;
	}

	switch ( $capability ) {
		case 'manage_options':
		case 'moderate_fanfiction':
		case 'manage_fanfiction_settings':
		case 'manage_fanfiction_taxonomies':
		case 'manage_fanfiction_url_config':
		case 'manage_fanfiction_emails':
		case 'manage_fanfiction_css':
		case 'edit_others_fanfiction_stories':
		case 'delete_others_fanfiction_stories':
		case 'edit_others_fanfiction_chapters':
		case 'delete_others_fanfiction_chapters':
			return false;

		case 'edit_fanfiction_stories':
		case 'publish_fanfiction_stories':
		case 'delete_fanfiction_stories':
		case 'edit_fanfiction_chapters':
		case 'publish_fanfiction_chapters':
		case 'delete_fanfiction_chapters':
			return true;

		case 'edit_fanfiction_story':
		case 'delete_fanfiction_story':
		case 'edit_fanfiction_chapter':
		case 'delete_fanfiction_chapter':
			return $object_id > 0 && fanfic_preview_treats_current_user_as_content_owner( $object_id );
	}

	return false;
}

/**
 * Check whether the current user owns a story or chapter.
 *
 * For chapters, ownership is granted to the chapter author and the parent story author.
 *
 * @since 1.0.0
 * @param int $post_id Post ID.
 * @return bool
 */
function fanfic_current_user_is_content_owner( $post_id ) {
	$user_id = fanfic_get_effective_current_user_id();
	if ( ! $user_id ) {
		return fanfic_preview_treats_current_user_as_content_owner( $post_id );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	if ( (int) $post->post_author === $user_id ) {
		return true;
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		$story_id = (int) $post->post_parent;
		if ( $story_id && (int) get_post_field( 'post_author', $story_id ) === $user_id ) {
			return true;
		}
	}

	return fanfic_preview_treats_current_user_as_content_owner( $post_id );
}

/**
 * Check whether the current user can view a non-public story or chapter.
 *
 * @since 1.0.0
 * @param int $post_id Post ID.
 * @return bool
 */
function fanfic_current_user_can_view_restricted_post( $post_id ) {
	if ( fanfic_current_user_can_use_moderation_controls() ) {
		return true;
	}

	return fanfic_current_user_is_content_owner( $post_id );
}

/**
 * Check whether a story is publicly visible.
 *
 * @since 1.0.0
 * @param int $story_id Story ID.
 * @return bool
 */
function fanfic_is_story_publicly_visible( $story_id ) {
	$story = get_post( $story_id );
	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return false;
	}

	return 'publish' === $story->post_status && ! fanfic_is_story_blocked( $story_id );
}

/**
 * Check whether a chapter is publicly visible.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter ID.
 * @return bool
 */
function fanfic_is_chapter_publicly_visible( $chapter_id ) {
	$chapter = get_post( $chapter_id );
	if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
		return false;
	}

	if ( 'publish' !== $chapter->post_status || fanfic_is_chapter_blocked( $chapter_id ) ) {
		return false;
	}

	$story_id = (int) $chapter->post_parent;
	return $story_id > 0 && fanfic_is_story_publicly_visible( $story_id );
}

/**
 * Check whether the current user can view a story or chapter.
 *
 * @since 1.0.0
 * @param int $post_id Post ID.
 * @return bool
 */
function fanfic_current_user_can_view_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	if ( 'fanfiction_story' === $post->post_type ) {
		if ( fanfic_is_story_publicly_visible( $post_id ) ) {
			return true;
		}

		return fanfic_current_user_can_view_restricted_post( $post_id );
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		if ( fanfic_is_chapter_publicly_visible( $post_id ) ) {
			return true;
		}

		return fanfic_current_user_can_view_restricted_post( $post_id ) || fanfic_current_user_can_view_restricted_post( (int) $post->post_parent );
	}

	return false;
}

/**
 * Block a post with canonical metadata.
 *
 * @since 1.0.0
 * @param int   $post_id Post ID.
 * @param array $args    Block arguments.
 * @return bool
 */
function fanfic_apply_post_block( $post_id, $args = array() ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
		return false;
	}

	$defaults = array(
		'actor_id'      => get_current_user_id(),
		'block_type'    => 'manual',
		'block_reason'  => '',
		'block_reason_text' => '',
		'change_status' => false,
		'new_status'    => 'draft',
		'save_status'   => false,
	);
	$args = wp_parse_args( $args, $defaults );

	$flag_meta_key = fanfic_get_block_flag_meta_key( $post );
	if ( '' === $flag_meta_key ) {
		return false;
	}

	$current_status = get_post_status( $post_id );
	if ( $args['save_status'] && $current_status && ! get_post_meta( $post_id, '_fanfic_story_blocked_prev_status', true ) ) {
		update_post_meta( $post_id, '_fanfic_story_blocked_prev_status', $current_status );
	}

	update_post_meta( $post_id, $flag_meta_key, 1 );
	update_post_meta( $post_id, '_fanfic_block_type', sanitize_key( $args['block_type'] ) );
	update_post_meta( $post_id, '_fanfic_block_reason', sanitize_text_field( $args['block_reason'] ) );
	$normalized_reason_text = function_exists( 'fanfic_normalize_block_reason_text' )
		? fanfic_normalize_block_reason_text( $args['block_reason_text'] )
		: sanitize_textarea_field( $args['block_reason_text'] );
	if ( '' !== $normalized_reason_text ) {
		update_post_meta( $post_id, '_fanfic_block_reason_text', $normalized_reason_text );
	} else {
		delete_post_meta( $post_id, '_fanfic_block_reason_text' );
	}
	update_post_meta( $post_id, '_fanfic_blocked_timestamp', time() );

	if ( 'fanfiction_story' === $post->post_type ) {
		fanfic_create_block_snapshot( $post_id );
	}

	// Canonical moderation review state keeps only snapshot + current content.
	fanfic_delete_post_revisions( $post_id );
	fanfic_ensure_block_diff_baseline_revision( $post_id );

	if ( 'fanfiction_story' === $post->post_type ) {
		$chapter_ids = get_posts(
			array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $post_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( array_map( 'absint', $chapter_ids ) as $chapter_id ) {
			if ( $chapter_id > 0 ) {
				fanfic_delete_post_revisions( $chapter_id );
				fanfic_ensure_block_diff_baseline_revision( $chapter_id );
			}
		}
	}

	if ( $args['change_status'] && $current_status && $args['new_status'] !== $current_status ) {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $args['new_status'],
			)
		);
	}

	if ( 'fanfiction_story' === $post->post_type ) {
		do_action( 'fanfic_story_blocked', $post_id, (int) $args['actor_id'], sanitize_key( $args['block_type'] ), sanitize_text_field( $args['block_reason'] ) );
	} else {
		do_action( 'fanfic_chapter_blocked', $post_id, (int) $args['actor_id'], sanitize_key( $args['block_type'] ), sanitize_text_field( $args['block_reason'] ) );
	}

	return true;
}

/**
 * Unblock a post with canonical metadata.
 *
 * @since 1.0.0
 * @param int   $post_id Post ID.
 * @param array $args    Unblock arguments.
 * @return bool
 */
function fanfic_remove_post_block( $post_id, $args = array() ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
		return false;
	}

	$defaults = array(
		'actor_id'       => get_current_user_id(),
		'restore_status' => false,
	);
	$args = wp_parse_args( $args, $defaults );

	$flag_meta_key = fanfic_get_block_flag_meta_key( $post );
	if ( '' === $flag_meta_key ) {
		return false;
	}

	if ( $args['restore_status'] ) {
		$prev_status = get_post_meta( $post_id, '_fanfic_story_blocked_prev_status', true );
		if ( $prev_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $prev_status,
				)
			);
		}
	}

	delete_post_meta( $post_id, $flag_meta_key );
	delete_post_meta( $post_id, '_fanfic_block_type' );
	delete_post_meta( $post_id, '_fanfic_block_reason' );
	delete_post_meta( $post_id, '_fanfic_block_reason_text' );
	delete_post_meta( $post_id, '_fanfic_blocked_timestamp' );
	delete_post_meta( $post_id, '_fanfic_story_blocked_prev_status' );
	delete_post_meta( $post_id, '_fanfic_block_snapshot' );
	delete_post_meta( $post_id, '_fanfic_re_review_requested' );
	delete_post_meta( $post_id, '_fanfic_block_diff_baseline_revision_id' );
	delete_post_meta( $post_id, '_fanfic_block_diff_latest_revision_id' );

	if ( 'fanfiction_story' === $post->post_type ) {
		$chapter_ids = get_posts(
			array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $post_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( array_map( 'absint', $chapter_ids ) as $chapter_id ) {
			if ( $chapter_id > 0 ) {
				delete_post_meta( $chapter_id, '_fanfic_block_diff_baseline_revision_id' );
				delete_post_meta( $chapter_id, '_fanfic_block_diff_latest_revision_id' );
			}
		}
	}

	fanfic_cleanup_unblocked_revisions( $post_id );

	if ( 'fanfiction_story' === $post->post_type ) {
		do_action( 'fanfic_story_unblocked', $post_id, (int) $args['actor_id'] );
	} else {
		do_action( 'fanfic_chapter_unblocked', $post_id, (int) $args['actor_id'] );
	}

	return true;
}

/**
 * Delete all WordPress revision posts for a fanfiction post.
 *
 * @since 2.3.0
 * @param int $post_id Post ID.
 * @return int Number of deleted revisions.
 */
function fanfic_delete_post_revisions( $post_id ) {
	$post_id   = absint( $post_id );
	$revisions = wp_get_post_revisions(
		$post_id,
		array(
			'posts_per_page' => -1,
		)
	);

	if ( empty( $revisions ) ) {
		return 0;
	}

	$deleted = 0;
	foreach ( array_map( 'absint', array_keys( $revisions ) ) as $revision_id ) {
		if ( $revision_id > 0 && wp_delete_post_revision( $revision_id ) ) {
			$deleted++;
		}
	}

	return $deleted;
}

/**
 * Validate a stored block-diff revision ID for a post.
 *
 * @since 2.3.0
 * @param int    $post_id  Parent post ID.
 * @param string $meta_key Meta key containing revision ID.
 * @return int
 */
function fanfic_get_block_diff_revision_id( $post_id, $meta_key ) {
	$post_id     = absint( $post_id );
	$revision_id = absint( get_post_meta( $post_id, $meta_key, true ) );
	if ( $post_id <= 0 || $revision_id <= 0 ) {
		return 0;
	}

	$revision = get_post( $revision_id );
	if ( ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $post_id ) {
		return 0;
	}

	return $revision_id;
}

/**
 * Force-create a revision for a fanfiction post even when default retention is 0.
 *
 * @since 2.3.0
 * @param int $post_id Post ID.
 * @return int Revision ID, or 0 on failure/no-op.
 */
function fanfic_force_create_post_revision( $post_id ) {
	$post_id = absint( $post_id );
	$post    = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
		return 0;
	}

	$force_revisions_filter = static function ( $num, $candidate_post ) use ( $post_id ) {
		$candidate_post = get_post( $candidate_post );
		if ( $candidate_post && (int) $candidate_post->ID === $post_id ) {
			return 50;
		}

		return $num;
	};

	add_filter( 'wp_revisions_to_keep', $force_revisions_filter, 9999, 2 );
	$revision_id = wp_save_post_revision( $post_id );
	remove_filter( 'wp_revisions_to_keep', $force_revisions_filter, 9999 );

	return absint( $revision_id );
}

/**
 * Keep only baseline and latest revisions for blocked-content diffing.
 *
 * @since 2.3.0
 * @param int $post_id Post ID.
 * @return void
 */
function fanfic_prune_block_diff_revisions( $post_id ) {
	$post_id = absint( $post_id );
	if ( $post_id <= 0 ) {
		return;
	}

	$baseline_id = fanfic_get_block_diff_revision_id( $post_id, '_fanfic_block_diff_baseline_revision_id' );
	$latest_id   = fanfic_get_block_diff_revision_id( $post_id, '_fanfic_block_diff_latest_revision_id' );
	$keep_ids    = array_filter( array_unique( array( $baseline_id, $latest_id ) ) );

	$revisions = wp_get_post_revisions(
		$post_id,
		array(
			'posts_per_page' => -1,
		)
	);

	foreach ( array_map( 'absint', array_keys( $revisions ) ) as $revision_id ) {
		if ( $revision_id > 0 && ! in_array( $revision_id, $keep_ids, true ) ) {
			wp_delete_post_revision( $revision_id );
		}
	}
}

/**
 * Ensure the block-time baseline revision exists for diffing.
 *
 * @since 2.3.0
 * @param int $post_id Post ID.
 * @return int Baseline revision ID, or 0.
 */
function fanfic_ensure_block_diff_baseline_revision( $post_id ) {
	$post_id     = absint( $post_id );
	$baseline_id = fanfic_get_block_diff_revision_id( $post_id, '_fanfic_block_diff_baseline_revision_id' );
	if ( $baseline_id > 0 ) {
		return $baseline_id;
	}

	$baseline_id = fanfic_force_create_post_revision( $post_id );
	if ( $baseline_id > 0 ) {
		update_post_meta( $post_id, '_fanfic_block_diff_baseline_revision_id', $baseline_id );
		update_post_meta( $post_id, '_fanfic_block_diff_latest_revision_id', $baseline_id );
	}

	return $baseline_id;
}

/**
 * Refresh the latest revision in the block diff pair for a post.
 *
 * @since 2.3.0
 * @param int $post_id Post ID.
 * @return bool
 */
function fanfic_refresh_block_diff_revision_pair( $post_id ) {
	$post_id = absint( $post_id );
	$post    = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
		return false;
	}

	$baseline_id = fanfic_ensure_block_diff_baseline_revision( $post_id );
	if ( $baseline_id <= 0 ) {
		return false;
	}

	$new_latest_id = fanfic_force_create_post_revision( $post_id );
	if ( $new_latest_id > 0 ) {
		update_post_meta( $post_id, '_fanfic_block_diff_latest_revision_id', $new_latest_id );
	}

	$latest_id = fanfic_get_block_diff_revision_id( $post_id, '_fanfic_block_diff_latest_revision_id' );
	if ( $latest_id <= 0 ) {
		update_post_meta( $post_id, '_fanfic_block_diff_latest_revision_id', $baseline_id );
	}

	fanfic_prune_block_diff_revisions( $post_id );
	return true;
}

/**
 * Remove blocked-only retained revisions once content is no longer blocked.
 *
 * Stories lose their own retained revisions immediately on unblock. Child
 * chapter revisions are also removed unless a chapter remains individually
 * blocked. Chapters only lose revisions when neither the chapter nor its
 * parent story is blocked anymore.
 *
 * @since 2.3.0
 * @param int $post_id Post ID being unblocked.
 * @return int Number of deleted revisions.
 */
function fanfic_cleanup_unblocked_revisions( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return 0;
	}

	$deleted = 0;

	if ( 'fanfiction_story' === $post->post_type ) {
		$deleted += fanfic_delete_post_revisions( $post->ID );

		$chapter_ids = get_posts(
			array(
				'post_type'      => 'fanfiction_chapter',
				'post_parent'    => $post->ID,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( array_map( 'absint', $chapter_ids ) as $chapter_id ) {
			if ( $chapter_id > 0 && ! fanfic_is_chapter_blocked( $chapter_id ) ) {
				$deleted += fanfic_delete_post_revisions( $chapter_id );
			}
		}

		return $deleted;
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		$story_id = (int) $post->post_parent;
		if ( ! fanfic_is_chapter_blocked( $post->ID ) && ! ( $story_id > 0 && fanfic_is_story_blocked( $story_id ) ) ) {
			$deleted += fanfic_delete_post_revisions( $post->ID );
		}
	}

	return $deleted;
}

/**
 * Capture a lightweight chapter-structure snapshot for a story.
 *
 * Snapshot contains only chapter IDs and titles to detect:
 * - chapter added
 * - chapter deleted
 * - chapter title changed
 *
 * @since 2.4.1
 * @param int $story_id Story ID.
 * @return array<string,string> Map of chapter_id => chapter_title.
 */
function fanfic_get_story_chapter_structure_state( $story_id ) {
	$story_id = absint( $story_id );
	if ( $story_id <= 0 ) {
		return array();
	}

	$chapters = get_posts(
		array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$structure = array();
	foreach ( (array) $chapters as $chapter ) {
		if ( ! ( $chapter instanceof WP_Post ) ) {
			continue;
		}

		$chapter_id = absint( $chapter->ID );
		if ( $chapter_id <= 0 ) {
			continue;
		}

		$structure[ (string) $chapter_id ] = trim( (string) $chapter->post_title );
	}

	ksort( $structure, SORT_NUMERIC );
	return $structure;
}

/**
 * Normalize chapter structure snapshot into a stable map.
 *
 * @since 2.4.1
 * @param mixed $value Raw chapter structure value.
 * @return array<string,string>
 */
function fanfic_normalize_story_chapter_structure( $value ) {
	$normalized = array();

	foreach ( (array) $value as $chapter_id => $chapter_title ) {
		$chapter_id = absint( $chapter_id );
		if ( $chapter_id <= 0 ) {
			continue;
		}

		$normalized[ (string) $chapter_id ] = trim( (string) $chapter_title );
	}

	ksort( $normalized, SORT_NUMERIC );
	return $normalized;
}

/**
 * Build chapter-structure comparison details for the blocked-story table.
 *
 * @since 2.4.1
 * @param array<string,string> $old_structure Baseline chapter map.
 * @param array<string,string> $new_structure Current chapter map.
 * @return array<string,mixed>
 */
function fanfic_build_story_chapter_structure_comparison( $old_structure, $new_structure ) {
	$old_structure = fanfic_normalize_story_chapter_structure( $old_structure );
	$new_structure = fanfic_normalize_story_chapter_structure( $new_structure );

	$added_ids   = array_diff_key( $new_structure, $old_structure );
	$removed_ids = array_diff_key( $old_structure, $new_structure );
	$renamed_ids = array();

	foreach ( array_intersect_key( $new_structure, $old_structure ) as $chapter_id => $current_title ) {
		$baseline_title = isset( $old_structure[ $chapter_id ] ) ? (string) $old_structure[ $chapter_id ] : '';
		if ( (string) $current_title !== $baseline_title ) {
			$renamed_ids[] = $chapter_id;
		}
	}

	$added_count   = count( $added_ids );
	$removed_count = count( $removed_ids );
	$renamed_count = count( $renamed_ids );

	$old_count = count( $old_structure );
	$new_count = count( $new_structure );

	$old_display = sprintf(
		/* translators: %d: chapter count */
		_n( '%d chapter', '%d chapters', $old_count, 'fanfiction-manager' ),
		$old_count
	);

	$new_lines   = array();
	$new_lines[] = sprintf(
		/* translators: %d: chapter count */
		_n( '%d chapter', '%d chapters', $new_count, 'fanfiction-manager' ),
		$new_count
	);

	if ( $added_count > 0 ) {
		$new_lines[] = sprintf(
			/* translators: %d: number of chapters */
			_n( '%d chapter added', '%d chapters added', $added_count, 'fanfiction-manager' ),
			$added_count
		);
	}

	if ( $removed_count > 0 ) {
		$new_lines[] = sprintf(
			/* translators: %d: number of chapters */
			_n( '%d chapter removed', '%d chapters removed', $removed_count, 'fanfiction-manager' ),
			$removed_count
		);
	}

	if ( $renamed_count > 0 ) {
		$new_lines[] = sprintf(
			/* translators: %d: number of chapters */
			_n( '%d chapter title changed', '%d chapter titles changed', $renamed_count, 'fanfiction-manager' ),
			$renamed_count
		);
	}

	return array(
		'changed'        => ( $added_count + $removed_count + $renamed_count ) > 0,
		'old_normalized' => $old_structure,
		'new_normalized' => $new_structure,
		'old_display'    => $old_display,
		'new_display'    => implode( "\n", $new_lines ),
	);
}

/**
 * Build the canonical snapshot state for a blocked story.
 *
 * @since 2.3.0
 * @param int $post_id Story ID.
 * @return array
 */
function fanfic_get_story_block_snapshot_state( $post_id ) {
	$story = get_post( $post_id );
	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return array();
	}

	$genre_terms  = wp_get_object_terms( $post_id, 'fanfiction_genre' );
	$status_terms = wp_get_object_terms( $post_id, 'fanfiction_status' );
	$fandom_ids   = class_exists( 'Fanfic_Fandoms' ) && method_exists( 'Fanfic_Fandoms', 'get_story_fandom_ids' )
		? Fanfic_Fandoms::get_story_fandom_ids( $post_id )
		: array();
	$fandom_rows  = class_exists( 'Fanfic_Fandoms' ) && method_exists( 'Fanfic_Fandoms', 'get_story_fandom_labels' )
		? Fanfic_Fandoms::get_story_fandom_labels( $post_id )
		: array();
	$warning_rows = class_exists( 'Fanfic_Warnings' ) && method_exists( 'Fanfic_Warnings', 'get_story_warnings' )
		? Fanfic_Warnings::get_story_warnings( $post_id )
		: array();
	$language_id  = class_exists( 'Fanfic_Languages' ) && method_exists( 'Fanfic_Languages', 'get_story_language_id' )
		? Fanfic_Languages::get_story_language_id( $post_id )
		: null;
	$language_label = class_exists( 'Fanfic_Languages' ) && method_exists( 'Fanfic_Languages', 'get_story_language_label' )
		? Fanfic_Languages::get_story_language_label( $post_id, true )
		: '';
	$cover_image_id = get_post_thumbnail_id( $post_id );
	$cover_image_id = $cover_image_id ? absint( $cover_image_id ) : 0;
	$cover_image_url = $cover_image_id ? wp_get_attachment_image_url( $cover_image_id, 'medium' ) : '';

	$genre_ids = is_wp_error( $genre_terms ) ? array() : array_map( 'absint', wp_list_pluck( $genre_terms, 'term_id' ) );
	$genre_names = is_wp_error( $genre_terms ) ? array() : array_map( 'strval', wp_list_pluck( $genre_terms, 'name' ) );
	$status_ids = is_wp_error( $status_terms ) ? array() : array_map( 'absint', wp_list_pluck( $status_terms, 'term_id' ) );
	$status_names = is_wp_error( $status_terms ) ? array() : array_map( 'strval', wp_list_pluck( $status_terms, 'name' ) );
	$fandom_ids = array_map( 'absint', (array) $fandom_ids );
	$fandom_labels = array();
	foreach ( (array) $fandom_rows as $row ) {
		if ( is_array( $row ) && ! empty( $row['label'] ) ) {
			$fandom_labels[] = (string) $row['label'];
		}
	}

	$warning_ids = array();
	$warning_names = array();
	foreach ( (array) $warning_rows as $row ) {
		if ( ! empty( $row['id'] ) ) {
			$warning_ids[] = absint( $row['id'] );
		}
		if ( ! empty( $row['name'] ) ) {
			$warning_names[] = (string) $row['name'];
		}
	}

	sort( $genre_ids );
	sort( $genre_names );
	sort( $status_ids );
	sort( $status_names );
	sort( $fandom_ids );
	sort( $fandom_labels );
	sort( $warning_ids );
	sort( $warning_names );

	return array(
		'post_title'            => (string) $story->post_title,
		'post_excerpt'          => (string) $story->post_excerpt,
		'post_content'          => (string) $story->post_content,
		'chapter_structure'     => fanfic_get_story_chapter_structure_state( $post_id ),
		'genre_ids'             => $genre_ids,
		'genre_names'           => $genre_names,
		'status_ids'            => $status_ids,
		'status_names'          => $status_names,
		'fandom_ids'            => $fandom_ids,
		'fandom_labels'         => $fandom_labels,
		'warning_ids'           => $warning_ids,
		'warning_names'         => $warning_names,
		'language_id'           => $language_id ? absint( $language_id ) : 0,
		'language_label'        => (string) $language_label,
		'licence'               => (string) get_post_meta( $post_id, '_fanfic_licence', true ),
		'age_rating'            => (string) get_post_meta( $post_id, '_fanfic_age_rating', true ),
		'cover_image_id'        => $cover_image_id,
		'cover_image_url'       => $cover_image_url ? (string) $cover_image_url : '',
		'author_notes_enabled'  => '1' === (string) get_post_meta( $post_id, '_fanfic_author_notes_enabled', true ) ? '1' : '0',
		'author_notes_position' => 'above' === (string) get_post_meta( $post_id, '_fanfic_author_notes_position', true ) ? 'above' : 'below',
		'author_notes'          => (string) get_post_meta( $post_id, '_fanfic_author_notes', true ),
		'is_original_work'      => '1' === (string) get_post_meta( $post_id, '_fanfic_is_original_work', true ) ? '1' : '0',
	);
}

/**
 * Create the canonical snapshot stored when a story is blocked.
 *
 * @since 2.3.0
 * @param int $post_id Story ID.
 * @return bool
 */
function fanfic_create_block_snapshot( $post_id ) {
	$snapshot = fanfic_get_story_block_snapshot_state( $post_id );
	if ( empty( $snapshot ) ) {
		return false;
	}

	$snapshot['snapshot_time'] = time();
	return false !== update_post_meta( $post_id, '_fanfic_block_snapshot', wp_slash( wp_json_encode( $snapshot ) ) );
}

/**
 * Get a decoded block snapshot from post meta.
 *
 * @since 2.3.0
 * @param int $post_id Story ID.
 * @return array
 */
function fanfic_get_block_snapshot( $post_id ) {
	$raw_snapshot = get_post_meta( $post_id, '_fanfic_block_snapshot', true );

	if ( is_array( $raw_snapshot ) ) {
		return fanfic_repair_block_snapshot_unicode_sequences( $raw_snapshot );
	}

	if ( ! is_string( $raw_snapshot ) || '' === $raw_snapshot ) {
		return array();
	}

	$decoded = json_decode( $raw_snapshot, true );
	return is_array( $decoded ) ? fanfic_repair_block_snapshot_unicode_sequences( $decoded ) : array();
}

/**
 * Repair legacy snapshot strings where WordPress stripped JSON unicode slashes.
 *
 * Older snapshots could be stored with bare `uXXXX` sequences like `Romu00e2nu0103`
 * because post meta writes unslash JSON strings. This normalizes those values after
 * decoding so comparison output stays readable.
 *
 * @since 2.3.0
 * @param mixed $value Snapshot value.
 * @return mixed
 */
function fanfic_repair_block_snapshot_unicode_sequences( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = fanfic_repair_block_snapshot_unicode_sequences( $item );
		}

		return $value;
	}

	if ( ! is_string( $value ) || ! preg_match( '/(?<!\\\\)u[0-9a-fA-F]{4}/', $value ) ) {
		return $value;
	}

	$repaired = preg_replace( '/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $value );
	if ( ! is_string( $repaired ) || $repaired === $value ) {
		return $value;
	}

	$json_string = '"' . str_replace(
		array( '"', "\n", "\r", "\t", "\f", "\b" ),
		array( '\"', '\n', '\r', '\t', '\f', '\b' ),
		$repaired
	) . '"';
	$decoded = json_decode( $json_string, true );

	return is_string( $decoded ) ? $decoded : $value;
}

/**
 * Normalize a list-like snapshot value.
 *
 * @since 2.3.0
 * @param mixed $value Raw list value.
 * @return string[]
 */
function fanfic_normalize_block_snapshot_list( $value ) {
	$normalized = array();

	foreach ( (array) $value as $item ) {
		$item = trim( (string) $item );
		if ( '' !== $item ) {
			$normalized[] = $item;
		}
	}

	sort( $normalized );
	return array_values( array_unique( $normalized ) );
}

/**
 * Build normalized comparison rows for a blocked story snapshot.
 *
 * @since 2.3.0
 * @param int $post_id Story ID.
 * @return array<int,array<string,mixed>>
 */
function fanfic_get_block_comparison_rows( $post_id ) {
	$snapshot = fanfic_get_block_snapshot( $post_id );
	$current  = fanfic_get_story_block_snapshot_state( $post_id );

	if ( empty( $snapshot ) || empty( $current ) ) {
		return array();
	}

	$field_map = array(
		'post_title'            => array( 'label' => __( 'Title', 'fanfiction-manager' ), 'type' => 'text' ),
		'post_excerpt'          => array( 'label' => __( 'Introduction', 'fanfiction-manager' ), 'type' => 'longtext' ),
		'post_content'          => array( 'label' => __( 'Content', 'fanfiction-manager' ), 'type' => 'longtext' ),
		'chapter_structure'     => array( 'label' => __( 'Chapters', 'fanfiction-manager' ), 'type' => 'chapter_structure' ),
		'genre_names'           => array( 'label' => __( 'Genres', 'fanfiction-manager' ), 'type' => 'list' ),
		'status_names'          => array( 'label' => __( 'Statuses', 'fanfiction-manager' ), 'type' => 'list' ),
		'fandom_labels'         => array( 'label' => __( 'Fandoms', 'fanfiction-manager' ), 'type' => 'list' ),
		'warning_names'         => array( 'label' => __( 'Warnings', 'fanfiction-manager' ), 'type' => 'list' ),
		'language_label'        => array( 'label' => __( 'Language', 'fanfiction-manager' ), 'type' => 'text' ),
		'licence'               => array( 'label' => __( 'Licence', 'fanfiction-manager' ), 'type' => 'text' ),
		'age_rating'            => array( 'label' => __( 'Age Rating', 'fanfiction-manager' ), 'type' => 'text' ),
		'cover_image_url'       => array( 'label' => __( 'Cover Image', 'fanfiction-manager' ), 'type' => 'image', 'id_key' => 'cover_image_id' ),
		'author_notes_enabled'  => array( 'label' => __( 'Author Notes Enabled', 'fanfiction-manager' ), 'type' => 'boolean' ),
		'author_notes_position' => array( 'label' => __( 'Author Notes Position', 'fanfiction-manager' ), 'type' => 'text' ),
		'author_notes'          => array( 'label' => __( 'Author Notes', 'fanfiction-manager' ), 'type' => 'longtext' ),
		'is_original_work'      => array( 'label' => __( 'Original Work', 'fanfiction-manager' ), 'type' => 'boolean' ),
	);

	$rows = array();

	foreach ( $field_map as $field_key => $field_config ) {
		$type = $field_config['type'];
		$old_value = isset( $snapshot[ $field_key ] ) ? $snapshot[ $field_key ] : '';
		$new_value = isset( $current[ $field_key ] ) ? $current[ $field_key ] : '';

		switch ( $type ) {
			case 'chapter_structure':
				if ( ! array_key_exists( $field_key, $snapshot ) ) {
					$old_normalized = array();
					$new_normalized = array();
					$old_display = '';
					$new_display = '';
					$changed = false;
					break;
				}

				$chapter_comparison = fanfic_build_story_chapter_structure_comparison( $old_value, $new_value );
				$old_normalized = $chapter_comparison['old_normalized'];
				$new_normalized = $chapter_comparison['new_normalized'];
				$old_display = (string) $chapter_comparison['old_display'];
				$new_display = (string) $chapter_comparison['new_display'];
				$changed = ! empty( $chapter_comparison['changed'] );
				break;

			case 'list':
				$old_normalized = fanfic_normalize_block_snapshot_list( $old_value );
				$new_normalized = fanfic_normalize_block_snapshot_list( $new_value );
				$old_display = empty( $old_normalized ) ? '' : implode( ', ', $old_normalized );
				$new_display = empty( $new_normalized ) ? '' : implode( ', ', $new_normalized );
				$changed = $old_normalized !== $new_normalized;
				break;

			case 'boolean':
				$old_normalized = '1' === (string) $old_value ? '1' : '0';
				$new_normalized = '1' === (string) $new_value ? '1' : '0';
				$old_display = '1' === $old_normalized ? __( 'Yes', 'fanfiction-manager' ) : __( 'No', 'fanfiction-manager' );
				$new_display = '1' === $new_normalized ? __( 'Yes', 'fanfiction-manager' ) : __( 'No', 'fanfiction-manager' );
				$changed = $old_normalized !== $new_normalized;
				break;

			case 'image':
				$id_key = $field_config['id_key'];
				$old_id = isset( $snapshot[ $id_key ] ) ? absint( $snapshot[ $id_key ] ) : 0;
				$new_id = isset( $current[ $id_key ] ) ? absint( $current[ $id_key ] ) : 0;
				$old_normalized = (string) $old_value;
				$new_normalized = (string) $new_value;
				$old_display = $old_normalized;
				$new_display = $new_normalized;
				$changed = $old_id !== $new_id || $old_normalized !== $new_normalized;
				break;

			case 'longtext':
			case 'text':
			default:
				$old_normalized = trim( (string) $old_value );
				$new_normalized = trim( (string) $new_value );
				$old_display = $old_normalized;
				$new_display = $new_normalized;
				$changed = $old_normalized !== $new_normalized;
				break;
		}

		$rows[] = array(
			'key'            => $field_key,
			'label'          => $field_config['label'],
			'type'           => $type,
			'changed'        => $changed,
			'old_value'      => $old_display,
			'new_value'      => $new_display,
			'old_normalized' => $old_normalized,
			'new_normalized' => $new_normalized,
		);
	}

	return $rows;
}

/**
 * Determine whether a blocked story has saved modifications versus its snapshot.
 *
 * @since 2.3.0
 * @param int $post_id Story ID.
 * @return bool
 */
function fanfic_story_has_block_snapshot_changes( $post_id ) {
	$post_id = absint( $post_id );
	if ( $post_id <= 0 ) {
		return false;
	}

	$snapshot = fanfic_get_block_snapshot( $post_id );
	if ( empty( $snapshot ) ) {
		return false;
	}

	$rows = fanfic_get_block_comparison_rows( $post_id );
	foreach ( $rows as $row ) {
		if ( ! empty( $row['changed'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Build a concise list-diff fragment for a change summary.
 *
 * @since 2.3.0
 * @param string   $label    Field label.
 * @param string[] $old_list Previous values.
 * @param string[] $new_list New values.
 * @return string
 */
function fanfic_build_block_list_change_summary( $label, $old_list, $new_list ) {
	$old_list = fanfic_normalize_block_snapshot_list( $old_list );
	$new_list = fanfic_normalize_block_snapshot_list( $new_list );
	$added    = array_values( array_diff( $new_list, $old_list ) );
	$removed  = array_values( array_diff( $old_list, $new_list ) );
	$parts    = array();

	if ( ! empty( $added ) ) {
		$parts[] = sprintf(
			/* translators: %s: comma-separated list of values */
			__( 'added %s', 'fanfiction-manager' ),
			implode( ', ', $added )
		);
	}

	if ( ! empty( $removed ) ) {
		$parts[] = sprintf(
			/* translators: %s: comma-separated list of values */
			__( 'removed %s', 'fanfiction-manager' ),
			implode( ', ', $removed )
		);
	}

	if ( empty( $parts ) ) {
		return sprintf(
			/* translators: %s: field label */
			__( '%s changed.', 'fanfiction-manager' ),
			$label
		);
	}

	return sprintf(
		/* translators: 1: field label, 2: change description */
		__( '%1$s: %2$s.', 'fanfiction-manager' ),
		$label,
		implode( ', ', $parts )
	);
}

/**
 * Build a moderator-facing change summary for a blocked story re-review.
 *
 * @since 2.3.0
 * @param int $post_id Story ID.
 * @return string
 */
function fanfic_build_change_summary( $post_id ) {
	$rows = fanfic_get_block_comparison_rows( $post_id );
	if ( empty( $rows ) ) {
		return __( 'Author requested a re-review after editing the blocked story.', 'fanfiction-manager' );
	}

	$summary_parts = array();

	foreach ( $rows as $row ) {
		if ( empty( $row['changed'] ) ) {
			continue;
		}

		switch ( $row['type'] ) {
			case 'chapter_structure':
				$summary_parts[] = __( 'Chapters changed (added, removed, or renamed).', 'fanfiction-manager' );
				break;

			case 'list':
				$summary_parts[] = fanfic_build_block_list_change_summary(
					$row['label'],
					$row['old_normalized'],
					$row['new_normalized']
				);
				break;

			case 'boolean':
				$summary_parts[] = sprintf(
					/* translators: 1: field label, 2: boolean state */
					__( '%1$s: %2$s.', 'fanfiction-manager' ),
					$row['label'],
					'1' === $row['new_normalized'] ? __( 'enabled', 'fanfiction-manager' ) : __( 'disabled', 'fanfiction-manager' )
				);
				break;

			case 'image':
				$summary_parts[] = __( 'Cover image changed.', 'fanfiction-manager' );
				break;

			default:
				$summary_parts[] = sprintf(
					/* translators: %s: field label */
					__( '%s changed.', 'fanfiction-manager' ),
					$row['label']
				);
				break;
		}
	}

	if ( empty( $summary_parts ) ) {
		return __( 'Author requested a re-review, but no snapshot changes were detected.', 'fanfiction-manager' );
	}

	$summary = implode( ' ', $summary_parts );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $summary ) > 900 ) {
		$summary = mb_substr( $summary, 0, 897 ) . '...';
	} elseif ( strlen( $summary ) > 900 ) {
		$summary = substr( $summary, 0, 897 ) . '...';
	}

	return $summary;
}

/**
 * Sync blocked-story review state after author saves edits.
 *
 * Saving keeps comparison data current but does not notify moderators.
 * Moderator unread state is only triggered by explicit author messages.
 *
 * @since 2.3.0
 * @param int $story_id Story ID.
 * @return bool True when an active moderation thread exists.
 */
function fanfic_refresh_re_review_message( $story_id ) {
	$story_id = absint( $story_id );
	if ( ! $story_id || ! class_exists( 'Fanfic_Moderation_Messages' ) ) {
		return false;
	}

	$story = get_post( $story_id );
	if ( ! $story || 'fanfiction_story' !== $story->post_type || ! fanfic_is_story_blocked( $story_id ) ) {
		return false;
	}

	$author_id = (int) $story->post_author;
	if ( $author_id <= 0 ) {
		return false;
	}

	// Keep only canonical moderation states: block snapshot + latest saved content.
	fanfic_refresh_block_diff_revision_pair( $story_id );

	$has_active_message = Fanfic_Moderation_Messages::has_active_message( $author_id, 'story', $story_id );
	if ( $has_active_message ) {
		update_post_meta( $story_id, '_fanfic_re_review_requested', 1 );
		return true;
	}

	delete_post_meta( $story_id, '_fanfic_re_review_requested' );
	return false;
}

/**
 * Get the WordPress revision comparison URL for blocked-content baseline/current pair.
 *
 * @since 2.3.0
 * @param int $post_id Post ID.
 * @return string
 */
function fanfic_get_revision_compare_url( $post_id ) {
	$post_id     = absint( $post_id );
	$baseline_id = fanfic_get_block_diff_revision_id( $post_id, '_fanfic_block_diff_baseline_revision_id' );
	$latest_id   = fanfic_get_block_diff_revision_id( $post_id, '_fanfic_block_diff_latest_revision_id' );

	if ( $baseline_id <= 0 ) {
		return '';
	}

	if ( $latest_id > 0 && $latest_id !== $baseline_id ) {
		return admin_url(
			add_query_arg(
				array(
					'from' => $baseline_id,
					'to'   => $latest_id,
				),
				'revision.php'
			)
		);
	}

	return admin_url( 'revision.php?revision=' . $baseline_id );
}

/**
 * Determine whether a blocked story has any moderator-reviewable saved changes.
 *
 * Uses only story snapshot differences (including chapter structure changes).
 *
 * @since 2.4.0
 * @param int $story_id Story ID.
 * @return bool
 */
function fanfic_story_has_reviewable_modifications( $story_id ) {
	$story_id = absint( $story_id );
	if ( $story_id <= 0 ) {
		return false;
	}

	return fanfic_story_has_block_snapshot_changes( $story_id );
}

/**
 * Disable WordPress revisions for fanfiction stories and chapters.
 *
 * Blocked moderation review uses the block snapshot plus current saved content.
 *
 * @since 2.3.0
 * @param int         $num  Number of revisions to keep.
 * @param int|WP_Post $post Post being saved.
 * @return int
 */
function fanfic_filter_revisions_to_keep( $num, $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return $num;
	}

	if ( 'fanfiction_story' === $post->post_type ) {
		return 0;
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		return 0;
	}

	return $num;
}

/**
 * Check if user is a fanfiction author
 *
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user is a fanfiction author
 */
function fanfic_is_author( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = fanfic_get_effective_current_user_id();
	}

	return in_array( 'fanfiction_author', fanfic_get_effective_user_roles( $user_id ), true );
}

/**
 * Check if user is a fanfiction moderator
 *
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user is a fanfiction moderator
 */
function fanfic_is_moderator( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = fanfic_get_effective_current_user_id();
	}

	$roles = fanfic_get_effective_user_roles( $user_id );
	return in_array( 'fanfiction_moderator', $roles, true ) || in_array( 'fanfiction_admin', $roles, true ) || in_array( 'administrator', $roles, true );
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
		$user_id = fanfic_get_effective_current_user_id();
	}

	$roles = fanfic_get_effective_user_roles( $user_id );
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
	$user_id = fanfic_get_effective_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	// Administrators and moderators can edit anything
	if ( fanfic_current_user_can_use_moderation_controls() ) {
		return true;
	}

	switch ( $content_type ) {
		case 'story':
			$post = get_post( $content_id );
			if ( ! $post || 'fanfiction_story' !== $post->post_type ) {
				return false;
			}
			if ( fanfic_is_story_blocked( $content_id ) ) {
				return false;
			}
			return fanfic_current_user_has_preview_cap( 'edit_fanfiction_story', $content_id ) || fanfic_current_user_is_content_owner( $content_id );

		case 'chapter':
			$post = get_post( $content_id );
			if ( ! $post || 'fanfiction_chapter' !== $post->post_type ) {
				return false;
			}
			if ( fanfic_is_chapter_blocked( $content_id ) || fanfic_is_story_blocked( (int) $post->post_parent ) ) {
				return false;
			}
			return fanfic_current_user_has_preview_cap( 'edit_fanfiction_chapter', $content_id )
				|| fanfic_current_user_has_preview_cap( 'edit_fanfiction_story', (int) $post->post_parent )
				|| fanfic_current_user_is_content_owner( $content_id )
				|| fanfic_current_user_is_content_owner( (int) $post->post_parent );

		case 'profile':
			// Users can edit their own profile
			return absint( $content_id ) === $user_id && ! in_array( 'fanfiction_banned_user', fanfic_get_effective_user_roles( $user_id ), true );

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
 * Return a round mini avatar img when the user has a custom profile picture,
 * or the generic dashicons-admin-users icon span as fallback.
 *
 * Relies on the `_fanfic_avatar_url` user meta written by the profile handler.
 *
 * @param int $user_id WordPress user ID.
 * @param int $size    Pixel size passed to get_avatar() (default 20).
 * @return string HTML – either an <img> or a <span class="dashicons …">.
 */
function fanfic_get_author_avatar_or_icon( $user_id, $size = 20 ) {
	$has_avatar = ! empty( get_user_meta( absint( $user_id ), '_fanfic_avatar_url', true ) );
	if ( $has_avatar ) {
		return get_avatar(
			$user_id,
			$size,
			'',
			'',
			array(
				'class'   => 'fanfic-story-author-avatar',
				'loading' => 'lazy',
			)
		);
	}
	return '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>';
}

/**
 * Render the shared icon slot used by Fanfic buttons.
 *
 * @since 2.6.0
 * @param string $icon_class Dashicons class name.
 * @return string HTML markup for the icon slot.
 */
function fanfic_get_button_icon_markup( $icon_class ) {
	$icon_class = sanitize_html_class( (string) $icon_class );
	if ( '' === $icon_class ) {
		return '';
	}

	return '<span class="fanfic-button-icon" aria-hidden="true"><span class="dashicons ' . esc_attr( $icon_class ) . '"></span></span>';
}

/**
 * Render a standardized Fanfic button label with optional icon.
 *
 * @since 2.6.0
 * @param string $label      Button label.
 * @param string $icon_class Optional Dashicons class name.
 * @return string Button inner HTML.
 */
function fanfic_get_button_content_markup( $label, $icon_class = '' ) {
	$output = '';

	if ( '' !== (string) $icon_class ) {
		$output .= fanfic_get_button_icon_markup( $icon_class );
	}

	$output .= '<span class="fanfic-button-text">' . esc_html( (string) $label ) . '</span>';

	return $output;
}

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
 * Render the general page alerts zone.
 *
 * Outputs a container for system-wide author alerts. Any feature that needs
 * to surface a persistent alert to an author should hook into
 * 'fanfic_page_alerts' and check $context to decide which pages to target.
 *
 * Context strings match the breadcrumb context names:
 * 'dashboard', 'edit-story', 'edit-chapter', 'view-story', 'view-chapter',
 * 'edit-profile', 'view-profile'.
 *
 * Prefer calling fanfic_render_page_header() instead of this function
 * directly — it renders the breadcrumb and this zone together in one call.
 *
 * @since 1.0.0
 * @param string $context The page context identifier.
 * @return void
 */
function fanfic_render_page_alerts( $context = '' ) {
	?>
<!-- [GENERAL ALERTS] System-wide author alerts. Inject via: do_action( 'fanfic_page_alerts', $context ) -->
<div id="fanfic-page-alerts" class="fanfic-page-alerts" aria-live="polite">
	<?php do_action( 'fanfic_page_alerts', $context ); ?>
</div>
	<?php
}

/**
 * Render the page header: breadcrumb navigation + general alerts zone.
 *
 * Single call that replaces separate fanfic_render_breadcrumb() +
 * fanfic_render_page_alerts() calls. Use this in every template that has
 * a breadcrumb. The $context string is shared between both so hooks and
 * breadcrumb configuration use the same identifier.
 *
 * Context strings:
 * 'dashboard'    — Author dashboard
 * 'edit-story'   — Story create / edit form
 * 'edit-chapter' — Chapter create / edit form
 * 'view-story'   — Story reading page
 * 'view-chapter' — Chapter reading page
 * 'edit-profile' — Profile edit form
 * 'view-profile' — Public author profile
 * 'members'      — Members listing
 *
 * @since 1.0.0
 * @param string $context       Page context identifier (see above).
 * @param array  $breadcrumb_args Optional args forwarded to fanfic_render_breadcrumb().
 * @return void
 */
function fanfic_render_page_header( $context, $breadcrumb_args = array() ) {
	fanfic_render_breadcrumb( $context, $breadcrumb_args );
	fanfic_render_page_alerts( $context );
}

/**
 * Check whether a user account is suspended.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return bool
 */
function fanfic_is_user_suspended( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = fanfic_get_effective_current_user_id();
	}

	$user_id = absint( $user_id );
	if ( $user_id <= 0 ) {
		return false;
	}

	return in_array( 'fanfiction_banned_user', fanfic_get_effective_user_roles( $user_id ), true );
}

/**
 * Check whether a user is the story author or an accepted co-author.
 *
 * @since 1.0.0
 * @param int $story_id Story ID.
 * @param int $user_id  User ID.
 * @return bool
 */
function fanfic_user_is_story_author_or_coauthor( $story_id, $user_id = 0 ) {
	$story_id = absint( $story_id );
	if ( ! $user_id ) {
		$user_id = fanfic_get_effective_current_user_id();
	}
	$user_id  = absint( $user_id );
	if ( $story_id <= 0 || $user_id <= 0 ) {
		return fanfic_preview_treats_current_user_as_content_owner( $story_id );
	}

	$story = get_post( $story_id );
	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		return false;
	}

	if ( (int) $story->post_author === $user_id ) {
		return true;
	}

	if ( fanfic_preview_treats_current_user_as_content_owner( $story_id ) ) {
		return true;
	}

	if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
		return Fanfic_Coauthors::is_coauthor( $story_id, $user_id );
	}

	return false;
}

/**
 * Build story block/unblock controls.
 *
 * @since 1.0.0
 * @param int $story_id Story ID.
 * @return string
 */
function fanfic_get_story_block_controls_markup( $story_id ) {
	$story_id = absint( $story_id );
	if ( $story_id <= 0 ) {
		return '';
	}

	if ( ! fanfic_current_user_can_use_moderation_controls() ) {
		return '';
	}

	$is_story_blocked = fanfic_is_story_blocked( $story_id );
	$block_endpoint   = admin_url( 'admin-post.php' );

	ob_start();
	if ( $is_story_blocked ) :
		?>
		<form method="post" action="<?php echo esc_url( $block_endpoint ); ?>" class="fanfic-inline-block-toggle fanfic-story-block-toggle fanfic-story-block-toggle--unblock" data-story-id="<?php echo absint( $story_id ); ?>">
			<input type="hidden" name="action" value="fanfic_toggle_story_block">
			<input type="hidden" name="story_id" value="<?php echo esc_attr( $story_id ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'fanfic_toggle_story_block_' . $story_id ) ); ?>">
			<button type="submit" class="fanfic-button secondary">
				<?php echo fanfic_get_button_content_markup( __( 'Unblock Story', 'fanfiction-manager' ), 'dashicons-unlock' ); ?>
			</button>
		</form>
		<?php
	else :
		?>
		<button type="button" class="fanfic-button secondary fanfic-open-block-modal fanfic-inline-block-toggle fanfic-story-block-toggle" data-story-id="<?php echo absint( $story_id ); ?>">
			<?php echo fanfic_get_button_content_markup( __( 'Block Story', 'fanfiction-manager' ), 'dashicons-lock' ); ?>
		</button>
		<?php
	endif;

	return trim( (string) ob_get_clean() );
}

/**
 * Build story delete control markup.
 *
 * @since 2.6.0
 * @param int $story_id Story ID.
 * @return string
 */
function fanfic_get_story_delete_controls_markup( $story_id ) {
	$story_id = absint( $story_id );
	if ( $story_id <= 0 ) {
		return '';
	}

	if ( ! fanfic_current_user_has_preview_cap( 'delete_fanfiction_story', $story_id ) ) {
		return '';
	}

	ob_start();
	?>
	<button
		type="button"
		class="fanfic-button danger fanfic-story-delete-trigger"
		data-story-id="<?php echo esc_attr( $story_id ); ?>"
		data-story-title="<?php echo esc_attr( get_the_title( $story_id ) ); ?>"
		data-story-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_delete_story' ) ); ?>"
		data-story-redirect-url="<?php echo esc_attr( fanfic_get_dashboard_url() ); ?>"
		data-story-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
		aria-label="<?php esc_attr_e( 'Delete story', 'fanfiction-manager' ); ?>">
		<?php echo fanfic_get_button_content_markup( __( 'Delete', 'fanfiction-manager' ), 'dashicons-trash' ); ?>
	</button>
	<?php
	return trim( (string) ob_get_clean() );
}

/**
 * Build chapter block/unblock controls.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter ID.
 * @return string
 */
function fanfic_get_chapter_block_controls_markup( $chapter_id ) {
	$chapter_id = absint( $chapter_id );
	if ( $chapter_id <= 0 ) {
		return '';
	}

	if ( ! fanfic_current_user_can_use_moderation_controls() ) {
		return '';
	}

	$is_chapter_blocked = fanfic_is_chapter_blocked( $chapter_id );
	$block_endpoint     = admin_url( 'admin-post.php' );

	ob_start();
	if ( $is_chapter_blocked ) :
		?>
		<form method="post" action="<?php echo esc_url( $block_endpoint ); ?>" class="fanfic-inline-block-toggle fanfic-chapter-block-toggle fanfic-chapter-block-toggle--unblock" data-chapter-id="<?php echo absint( $chapter_id ); ?>">
			<input type="hidden" name="action" value="fanfic_toggle_chapter_block">
			<input type="hidden" name="chapter_id" value="<?php echo esc_attr( $chapter_id ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'fanfic_toggle_chapter_block_' . $chapter_id ) ); ?>">
			<button type="submit" class="fanfic-button secondary">
				<?php echo fanfic_get_button_content_markup( __( 'Unblock Chapter', 'fanfiction-manager' ), 'dashicons-unlock' ); ?>
			</button>
		</form>
		<?php
	else :
		?>
		<button type="button" class="fanfic-button secondary fanfic-open-block-modal fanfic-inline-block-toggle fanfic-chapter-block-toggle" data-chapter-id="<?php echo absint( $chapter_id ); ?>">
			<?php echo fanfic_get_button_content_markup( __( 'Block Chapter', 'fanfiction-manager' ), 'dashicons-lock' ); ?>
		</button>
		<?php
	endif;

	return trim( (string) ob_get_clean() );
}

/**
 * Build chapter delete control markup.
 *
 * @since 2.6.0
 * @param int $chapter_id Chapter ID.
 * @param int $story_id Story ID. Optional but used for redirect.
 * @return string
 */
function fanfic_get_chapter_delete_controls_markup( $chapter_id, $story_id = 0 ) {
	$chapter_id = absint( $chapter_id );
	$story_id   = absint( $story_id );

	if ( $chapter_id <= 0 ) {
		return '';
	}

	if ( ! fanfic_current_user_has_preview_cap( 'delete_fanfiction_chapter', $chapter_id ) ) {
		return '';
	}

	if ( ! $story_id ) {
		$story_id = absint( wp_get_post_parent_id( $chapter_id ) );
	}

	ob_start();
	?>
	<button
		type="button"
		class="fanfic-button danger fanfic-chapter-delete-trigger"
		data-chapter-id="<?php echo esc_attr( $chapter_id ); ?>"
		data-chapter-title="<?php echo esc_attr( get_the_title( $chapter_id ) ); ?>"
		data-story-id="<?php echo esc_attr( $story_id ); ?>"
		data-story-edit-url="<?php echo esc_attr( $story_id ? fanfic_get_edit_story_url( $story_id ) : fanfic_get_dashboard_url() ); ?>"
		data-chapter-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_delete_chapter' ) ); ?>"
		data-chapter-check-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_delete_chapter' ) ); ?>"
		data-chapter-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
		aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
		<?php echo fanfic_get_button_content_markup( __( 'Delete', 'fanfiction-manager' ), 'dashicons-trash' ); ?>
	</button>
	<?php
	return trim( (string) ob_get_clean() );
}

/**
 * Render shared moderation controls below page header alerts.
 *
 * @since 1.0.0
 * @param string $context Context key.
 * @param array  $args    Optional IDs.
 * @return void
 */
function fanfic_render_moderation_controls( $context, $args = array() ) {
	if ( ! fanfic_effective_is_user_logged_in() ) {
		return;
	}

	$context = sanitize_key( (string) $context );
	$args    = wp_parse_args(
		(array) $args,
		array(
			'story_id'   => 0,
			'chapter_id' => 0,
			'user_id'    => 0,
		)
	);

	$current_user_id        = fanfic_get_effective_current_user_id();
	$is_moderator           = fanfic_current_user_can_use_moderation_controls();
	$is_current_user_banned = fanfic_is_user_suspended( $current_user_id );
	$buttons                = array();
	$should_render_message_modal = false;
	$current_preview_context  = function_exists( 'fanfic_get_frontend_preview_context' ) ? fanfic_get_frontend_preview_context() : '';

	if ( in_array( $context, array( 'view-story', 'edit-story' ), true ) ) {
		$story_id = absint( $args['story_id'] );
		if ( ! $story_id || 'fanfiction_story' !== get_post_type( $story_id ) ) {
			return;
		}

		$is_story_blocked = fanfic_is_story_blocked( $story_id );

		if ( ! $is_current_user_banned && fanfic_current_user_can_edit( 'story', $story_id ) ) {
			if ( 'edit-story' === $current_preview_context ) {
				$buttons[] = sprintf(
					'<button type="button" class="fanfic-button fanfic-edit-button" disabled aria-disabled="true">%s</button>',
					fanfic_get_button_content_markup( __( 'Edit Story', 'fanfiction-manager' ), 'dashicons-edit' )
				);
			} elseif ( 'view-story' === $context ) {
				$buttons[] = sprintf(
					'<a href="%1$s" class="fanfic-button fanfic-edit-button">%2$s</a>',
					esc_url( fanfic_get_edit_story_url( $story_id ) ),
					fanfic_get_button_content_markup( __( 'Edit Story', 'fanfiction-manager' ), 'dashicons-edit' )
				);
			}
		}

		if ( ! $is_current_user_banned && ! $is_story_blocked && fanfic_user_is_story_author_or_coauthor( $story_id, $current_user_id ) ) {
			$buttons[] = sprintf(
				'<a href="%1$s" class="fanfic-button fanfic-edit-button fanfic-add-chapter-button">%2$s</a>',
				esc_url( fanfic_get_edit_chapter_url( 0, $story_id ) ),
				fanfic_get_button_content_markup( __( 'Add Chapter', 'fanfiction-manager' ), 'dashicons-plus-alt' )
			);
		}

		$dashboard_url = fanfic_get_dashboard_url();
		if ( '' !== $dashboard_url ) {
			$buttons[] = sprintf(
				'<a href="%1$s" class="fanfic-button secondary fanfic-dashboard-button">%2$s</a>',
				esc_url( $dashboard_url ),
				fanfic_get_button_content_markup( __( 'Dashboard', 'fanfiction-manager' ), 'dashicons-dashboard' )
			);
		}

		if ( $is_moderator ) {
			if ( 'view-story' === $context ) {
				$story_author_id = (int) get_post_field( 'post_author', $story_id );
				if ( $story_author_id > 0 && $story_author_id !== $current_user_id ) {
					$direct_thread = class_exists( 'Fanfic_Moderation_Messages' )
						? Fanfic_Moderation_Messages::get_active_message( $story_author_id, 'user', $story_author_id, 'direct_profile' )
						: null;
					$has_active_direct_thread = ! empty( $direct_thread['id'] );
					$has_unread_for_moderator = ! empty( $direct_thread['unread_for_moderator'] );
					$is_profile_chat_closed   = function_exists( 'fanfic_is_mod_chat_closed' )
						? fanfic_is_mod_chat_closed( 'user', $story_author_id )
						: false;
					$open_label = esc_html__( 'Open Conversation', 'fanfiction-manager' );

					if ( $is_profile_chat_closed ) {
						$buttons[] = sprintf(
							'<button type="button" class="fanfic-button secondary" disabled aria-disabled="true">%s</button>',
							fanfic_get_button_content_markup( __( 'Chat Closed', 'fanfiction-manager' ), 'dashicons-lock' )
						);
					} else {
						$buttons[] = sprintf(
							'<button type="button" class="fanfic-button secondary fanfic-message-mod-btn%1$s%2$s" data-target-type="user" data-target-id="%3$d" data-thread-context="direct_profile" data-is-moderator="1" data-has-unread="%4$s" data-open-label="%5$s">%6$s</button>',
							$has_active_direct_thread ? ' fanfic-message-chat-active' : '',
							$has_unread_for_moderator ? ' fanfic-message-chat-has-unread' : '',
							absint( $story_author_id ),
							$has_unread_for_moderator ? '1' : '0',
							esc_attr( $open_label ),
							fanfic_get_button_content_markup( $has_active_direct_thread ? $open_label : __( 'Message Author', 'fanfiction-manager' ), 'dashicons-email-alt' )
						);
						$should_render_message_modal = true;
					}
				}
			}

			if ( class_exists( 'Fanfic_Featured_Stories' ) ) {
				$feature_button = trim( (string) Fanfic_Featured_Stories::render_feature_button( $story_id ) );
				if ( '' !== $feature_button ) {
					$buttons[] = $feature_button;
				}
			}

			$story_block_controls = fanfic_get_story_block_controls_markup( $story_id );
			if ( '' !== $story_block_controls ) {
				$buttons[] = $story_block_controls;
			}
		}

		$story_delete_controls = fanfic_get_story_delete_controls_markup( $story_id );
		if ( '' !== $story_delete_controls ) {
			$buttons[] = $story_delete_controls;
		}
	} elseif ( in_array( $context, array( 'view-chapter', 'edit-chapter' ), true ) ) {
		$chapter_id = absint( $args['chapter_id'] );
		if ( ! $chapter_id || 'fanfiction_chapter' !== get_post_type( $chapter_id ) ) {
			return;
		}
		$story_id = absint( $args['story_id'] );
		if ( ! $story_id ) {
			$story_id = absint( wp_get_post_parent_id( $chapter_id ) );
		}

		if ( ! $is_current_user_banned && fanfic_current_user_can_edit( 'chapter', $chapter_id ) ) {
			if ( 'edit-chapter' === $current_preview_context ) {
				$buttons[] = sprintf(
					'<button type="button" class="fanfic-button fanfic-edit-button" disabled aria-disabled="true">%s</button>',
					fanfic_get_button_content_markup( __( 'Edit Chapter', 'fanfiction-manager' ), 'dashicons-edit' )
				);
			} elseif ( 'view-chapter' === $context ) {
				$buttons[] = sprintf(
					'<a href="%1$s" class="fanfic-button fanfic-edit-button">%2$s</a>',
					esc_url( fanfic_get_edit_chapter_url( $chapter_id, $story_id ) ),
					fanfic_get_button_content_markup( __( 'Edit Chapter', 'fanfiction-manager' ), 'dashicons-edit' )
				);
			}
		}

		if ( $story_id && ! $is_current_user_banned && fanfic_current_user_can_edit( 'story', $story_id ) && 'view-chapter' === $context ) {
			$buttons[] = sprintf(
				'<a href="%1$s" class="fanfic-button fanfic-edit-button">%2$s</a>',
				esc_url( fanfic_get_edit_story_url( $story_id ) ),
				fanfic_get_button_content_markup( __( 'Edit Story', 'fanfiction-manager' ), 'dashicons-edit' )
			);
		}

		$dashboard_url = fanfic_get_dashboard_url();
		if ( '' !== $dashboard_url ) {
			$buttons[] = sprintf(
				'<a href="%1$s" class="fanfic-button secondary fanfic-dashboard-button">%2$s</a>',
				esc_url( $dashboard_url ),
				fanfic_get_button_content_markup( __( 'Dashboard', 'fanfiction-manager' ), 'dashicons-dashboard' )
			);
		}

		if ( $is_moderator ) {
			if ( 'view-chapter' === $context ) {
				$chapter_author_id = (int) get_post_field( 'post_author', $chapter_id );
				if ( $chapter_author_id > 0 && $chapter_author_id !== $current_user_id ) {
					$direct_thread = class_exists( 'Fanfic_Moderation_Messages' )
						? Fanfic_Moderation_Messages::get_active_message( $chapter_author_id, 'user', $chapter_author_id, 'direct_profile' )
						: null;
					$has_active_direct_thread = ! empty( $direct_thread['id'] );
					$has_unread_for_moderator = ! empty( $direct_thread['unread_for_moderator'] );
					$is_profile_chat_closed   = function_exists( 'fanfic_is_mod_chat_closed' )
						? fanfic_is_mod_chat_closed( 'user', $chapter_author_id )
						: false;
					$open_label = esc_html__( 'Open Conversation', 'fanfiction-manager' );

					if ( $is_profile_chat_closed ) {
						$buttons[] = sprintf(
							'<button type="button" class="fanfic-button secondary" disabled aria-disabled="true">%s</button>',
							fanfic_get_button_content_markup( __( 'Chat Closed', 'fanfiction-manager' ), 'dashicons-lock' )
						);
					} else {
						$buttons[] = sprintf(
							'<button type="button" class="fanfic-button secondary fanfic-message-mod-btn%1$s%2$s" data-target-type="user" data-target-id="%3$d" data-thread-context="direct_profile" data-is-moderator="1" data-has-unread="%4$s" data-open-label="%5$s">%6$s</button>',
							$has_active_direct_thread ? ' fanfic-message-chat-active' : '',
							$has_unread_for_moderator ? ' fanfic-message-chat-has-unread' : '',
							absint( $chapter_author_id ),
							$has_unread_for_moderator ? '1' : '0',
							esc_attr( $open_label ),
							fanfic_get_button_content_markup( $has_active_direct_thread ? $open_label : __( 'Message Author', 'fanfiction-manager' ), 'dashicons-email-alt' )
						);
						$should_render_message_modal = true;
					}
				}
			}

			$chapter_block_controls = fanfic_get_chapter_block_controls_markup( $chapter_id );
			if ( '' !== $chapter_block_controls ) {
				$buttons[] = $chapter_block_controls;
			}
		}

		$chapter_delete_controls = fanfic_get_chapter_delete_controls_markup( $chapter_id, $story_id );
		if ( '' !== $chapter_delete_controls ) {
			$buttons[] = $chapter_delete_controls;
		}
	} elseif ( 'view-profile' === $context ) {
		$target_user_id = absint( $args['user_id'] );
		if ( $target_user_id <= 0 ) {
			return;
		}

		$target_user = get_userdata( $target_user_id );
		if ( ! $target_user ) {
			return;
		}

		if ( $target_user_id === $current_user_id && ! $is_current_user_banned ) {
			$edit_profile_url = fanfic_get_edit_profile_url( $target_user_id );
			if ( '' !== $edit_profile_url ) {
				$buttons[] = sprintf(
					'<a href="%1$s" class="fanfic-button fanfic-edit-button">%2$s</a>',
					esc_url( $edit_profile_url ),
					fanfic_get_button_content_markup( __( 'Edit Profile', 'fanfiction-manager' ), 'dashicons-edit' )
				);
			}
		}

		if ( ! $is_moderator ) {
			if ( empty( $buttons ) ) {
				return;
			}
		} else {

			$direct_thread = class_exists( 'Fanfic_Moderation_Messages' )
				? Fanfic_Moderation_Messages::get_active_message( $target_user_id, 'user', $target_user_id, 'direct_profile' )
				: null;
			$has_active_direct_thread = ! empty( $direct_thread['id'] );
			$has_unread_for_moderator = ! empty( $direct_thread['unread_for_moderator'] );
			$is_profile_chat_closed   = function_exists( 'fanfic_is_mod_chat_closed' )
				? fanfic_is_mod_chat_closed( 'user', $target_user_id )
				: false;
			$open_label = esc_html__( 'Open Conversation', 'fanfiction-manager' );

			if ( $is_profile_chat_closed ) {
				$buttons[] = sprintf(
					'<button type="button" class="fanfic-button secondary" disabled aria-disabled="true">%s</button>',
					fanfic_get_button_content_markup( __( 'Chat Closed', 'fanfiction-manager' ), 'dashicons-lock' )
				);
			} else {
				$buttons[] = sprintf(
					'<button type="button" class="fanfic-button secondary fanfic-message-mod-btn%1$s%2$s" data-target-type="user" data-target-id="%3$d" data-thread-context="direct_profile" data-is-moderator="1" data-has-unread="%4$s" data-open-label="%5$s">%6$s</button>',
					$has_active_direct_thread ? ' fanfic-message-chat-active' : '',
					$has_unread_for_moderator ? ' fanfic-message-chat-has-unread' : '',
					absint( $target_user_id ),
					$has_unread_for_moderator ? '1' : '0',
					esc_attr( $open_label ),
					fanfic_get_button_content_markup( $has_active_direct_thread ? $open_label : __( 'Message User', 'fanfiction-manager' ), 'dashicons-email-alt' )
				);
				$should_render_message_modal = true;
			}

			if ( $target_user_id !== $current_user_id ) {
				$is_target_banned = in_array( 'fanfiction_banned_user', (array) $target_user->roles, true );
				$block_label      = esc_html__( 'Block User', 'fanfiction-manager' );
				$unblock_label    = esc_html__( 'Unblock User', 'fanfiction-manager' );

				$buttons[] = sprintf(
					'<button type="button" class="fanfic-button secondary fanfic-profile-ban-toggle%1$s" data-user-id="%2$d" data-nonce="%3$s" data-is-banned="%4$s" data-ban-label="%5$s" data-unban-label="%6$s" data-target-name="%7$s">%8$s</button>',
					$is_target_banned ? ' is-banned' : '',
					absint( $target_user_id ),
					esc_attr( wp_create_nonce( 'fanfic_user_action_' . $target_user_id ) ),
					$is_target_banned ? '1' : '0',
					esc_attr( $block_label ),
					esc_attr( $unblock_label ),
					esc_attr( $target_user->display_name ),
					fanfic_get_button_content_markup( $is_target_banned ? __( 'Unblock User', 'fanfiction-manager' ) : __( 'Block User', 'fanfiction-manager' ), $is_target_banned ? 'dashicons-unlock' : 'dashicons-lock' )
				);
			}
		}
	}

	if ( empty( $buttons ) ) {
		return;
	}
	?>
	<section id="fanfiction-author-mod-controls" class="fanfic-content-section fanfic-page-moderation-controls fanfic-page-moderation-controls-<?php echo esc_attr( $context ); ?>" aria-label="<?php esc_attr_e( 'Content actions', 'fanfiction-manager' ); ?>">
		<h2 class="screen-reader-text"><?php esc_html_e( 'Content actions', 'fanfiction-manager' ); ?></h2>
		<div class="fanfic-buttons">
			<?php foreach ( $buttons as $button ) : ?>
				<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped at build time. ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php

	if ( $should_render_message_modal && function_exists( 'fanfic_render_moderation_message_modal' ) ) {
		fanfic_render_moderation_message_modal();
	}
}

/**
 * Get markup for the dynamic interaction buttons.
 *
 * @since 1.0.0
 * @return string
 */
function fanfic_get_dynamic_action_buttons_markup() {
	if ( ! class_exists( 'Fanfic_Shortcodes_Buttons' ) ) {
		return '';
	}

	ob_start();
	fanfic_render_dynamic_action_buttons();
	return trim( (string) ob_get_clean() );
}

/**
 * Ensure the dynamic action buttons placeholder exists in the right place
 * for the current template context.
 *
 * @since 1.0.0
 * @param string $template Template markup.
 * @param string $context  Template context.
 * @return string
 */
function fanfic_inject_dynamic_action_buttons_placeholder( $template, $context ) {
	$template = (string) $template;

	if ( '' === $template || false !== strpos( $template, '[fanfiction-action-buttons]' ) ) {
		return $template;
	}

	switch ( $context ) {
		case 'view-story':
			$template = preg_replace(
				'/(<section[^>]*class="[^"]*fanfic-story-comments[^"]*"[^>]*>)/i',
				"[fanfiction-action-buttons]\n$1",
				$template,
				1
			);
			break;

		case 'view-chapter':
			$template = preg_replace(
				'/(<section[^>]*class="[^"]*fanfic-chapter-comments[^"]*"[^>]*>)/i',
				"[fanfiction-action-buttons]\n$1",
				$template,
				1
			);
			break;

		case 'view-profile':
			$template = preg_replace(
				'/(<div[^>]*class="[^"]*fanfic-profile-stories[^"]*"[^>]*>)/i',
				"[fanfiction-action-buttons]\n$1",
				$template,
				1
			);

			if ( false === strpos( $template, '[fanfiction-action-buttons]' ) ) {
				$template = preg_replace(
					'/(<div[^>]*class="[^"]*fanfic-profile-coauthored-stories[^"]*"[^>]*>)/i',
					"[fanfiction-action-buttons]\n$1",
					$template,
					1
				);
			}
			break;
	}

	if ( false === strpos( $template, '[fanfiction-action-buttons]' ) ) {
		$template .= "\n[fanfiction-action-buttons]";
	}

	return $template;
}

function fanfic_render_dynamic_action_buttons() {
	if ( ! class_exists( 'Fanfic_Shortcodes_Buttons' ) ) {
		return;
	}

	$buttons_markup = trim( (string) Fanfic_Shortcodes_Buttons::action_buttons( array() ) );
	if ( '' === $buttons_markup ) {
		return;
	}
	?>
	<section id="fanfic-action-buttons" class="fanfic-content-section fanfic-page-dynamic-actions" aria-label="<?php esc_attr_e( 'Page actions', 'fanfiction-manager' ); ?>">
		<h2 class="screen-reader-text"><?php esc_html_e( 'Page actions', 'fanfiction-manager' ); ?></h2>
		<?php echo $buttons_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped by shortcode renderer. ?>
	</section>
	<?php
}

/**
 * Custom comment template callback.
 *
 * Shared by the story, chapter, and comments-section renderers so the walker
 * callback is always available before wp_list_comments() runs.
 *
 * @since 1.0.0
 * @param WP_Comment $comment Comment object.
 * @param array      $args    Comment display arguments.
 * @param int        $depth   Comment depth level.
 * @return void
 */
function fanfic_custom_comment_template( $comment, $args, $depth ) {
	$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
	?>
	<<?php echo esc_html( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent', $comment ); ?> role="article" aria-label="<?php echo esc_attr( sprintf( __( 'Comment by %s', 'fanfiction-manager' ), get_comment_author() ) ); ?>">
		<article id="div-comment-<?php comment_ID(); ?>" class="fanfic-comment-body">
			<footer class="fanfic-comment-meta">
				<div class="fanfic-comment-author vcard">
					<?php
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
						$author_name = get_comment_author( $comment );
						$author_user_id = $comment->user_id;

						if ( $author_user_id ) {
							$url_manager = Fanfic_URL_Manager::get_instance();
							$profile_url = $url_manager->get_user_profile_url( $author_user_id );
							echo '<a href="' . esc_url( $profile_url ) . '" class="url" rel="nofollow">' . esc_html( $author_name ) . '</a>';
						} else {
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
							printf(
								esc_html__( '%1$s at %2$s', 'fanfiction-manager' ),
								esc_html( get_comment_date( '', $comment ) ),
								esc_html( get_comment_time() )
							);
							?>
						</time>
					</a>

					<?php
					$edited_at = get_comment_meta( $comment->comment_ID, 'fanfic_edited_at', true );
					if ( $edited_at ) :
						?>
						<span class="fanfic-comment-edited">
							<?php esc_html_e( '(edited)', 'fanfiction-manager' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( '0' === $comment->comment_approved ) : ?>
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

				$reporting_enabled = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_report', true ) : true;
				$allow_anonymous_reports = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'allow_anonymous_reports', false ) : false;
				$can_report_comment = false;

				if ( $reporting_enabled ) {
					if ( fanfic_effective_is_user_logged_in() ) {
						$can_report_comment = fanfic_get_effective_current_user_id() !== absint( $comment->user_id );
					} else {
						$can_report_comment = $allow_anonymous_reports;
					}
				}

				if ( $can_report_comment && class_exists( 'Fanfic_Shortcodes_Buttons' ) ) {
					echo Fanfic_Shortcodes_Buttons::render_report_trigger(
						$comment->comment_ID,
						'comment',
						__( 'Report this comment', 'fanfiction-manager' ),
						'comment-report-link'
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
		</article>
	</<?php echo esc_html( $tag ); ?>>
	<?php
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
 * Render the shared fandom multi-select field.
 *
 * The fandom picker uses the same dropdown shell as the other multi-select
 * filters, while keeping remote search so large fandom datasets stay light.
 *
 * @since 2.0.0
 * @param array $args Field arguments.
 * @return string
 */
function fanfic_render_fandom_multiselect_field( $args = array() ) {
	if ( ! class_exists( 'Fanfic_Fandoms' ) || ! Fanfic_Fandoms::is_enabled() ) {
		return '';
	}

	$defaults = array(
		'wrapper_class'       => 'fanfic-fandoms-field',
		'field_id'            => '',
		'input_id'            => 'fanfic_fandom_search',
		'label'               => __( 'Fandoms', 'fanfiction-manager' ),
		'trigger_placeholder' => __( 'Select Fandoms', 'fanfiction-manager' ),
		'search_placeholder'  => __( 'Search fandoms...', 'fanfiction-manager' ),
		'results_aria_label'  => __( 'Fandom search results', 'fanfiction-manager' ),
		'description'         => '',
		'selected_fandoms'    => array(),
		'max_fandoms'         => Fanfic_Fandoms::MAX_FANDOMS,
		'field_disabled'      => false,
		'disabled_title'      => '',
		'preloaded_options'   => array(),
		'show_all_on_click'   => false,
	);

	$args            = wp_parse_args( $args, $defaults );
	$wrapper_classes = trim( (string) $args['wrapper_class'] );
	$field_id        = sanitize_html_class( (string) $args['field_id'] );

	ob_start();
	?>
	<div
		class="<?php echo esc_attr( $wrapper_classes ); ?>"
		<?php if ( '' !== $field_id ) : ?>
			id="<?php echo esc_attr( $field_id ); ?>"
		<?php endif; ?>
		data-max-fandoms="<?php echo esc_attr( absint( $args['max_fandoms'] ) ); ?>"
		data-show-all-on-click="<?php echo ! empty( $args['show_all_on_click'] ) ? '1' : '0'; ?>"
		data-preloaded-options="<?php echo esc_attr( wp_json_encode( array_values( (array) $args['preloaded_options'] ) ) ); ?>"
	>
		<label for="<?php echo esc_attr( $args['input_id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
		<div class="multi-select fanfic-fandom-multiselect" data-placeholder="<?php echo esc_attr( $args['trigger_placeholder'] ); ?>">
			<input
				type="text"
				id="<?php echo esc_attr( $args['input_id'] ); ?>"
				class="fanfic-input fanfic-fandom-search-input"
				autocomplete="off"
				placeholder="<?php echo esc_attr( $args['search_placeholder'] ); ?>"
				aria-haspopup="listbox"
				aria-expanded="false"
				<?php disabled( ! empty( $args['field_disabled'] ) ); ?>
				<?php if ( ! empty( $args['field_disabled'] ) && '' !== $args['disabled_title'] ) : ?>
					title="<?php echo esc_attr( $args['disabled_title'] ); ?>"
				<?php endif; ?>
			/>
			<div class="multi-select__dropdown">
				<div class="fanfic-fandom-results" role="listbox" aria-label="<?php echo esc_attr( $args['results_aria_label'] ); ?>"></div>
			</div>
		</div>
		<div class="fanfic-selected-fandoms fanfic-pill-values" aria-live="polite">
			<?php foreach ( (array) $args['selected_fandoms'] as $fandom ) : ?>
				<?php
				$fandom_id    = isset( $fandom['id'] ) ? absint( $fandom['id'] ) : 0;
				$fandom_label = isset( $fandom['label'] ) ? sanitize_text_field( (string) $fandom['label'] ) : '';
				if ( ! $fandom_id || '' === $fandom_label ) {
					continue;
				}
				?>
				<span class="fanfic-pill-value" data-id="<?php echo esc_attr( $fandom_id ); ?>" data-label="<?php echo esc_attr( $fandom_label ); ?>">
					<span class="fanfic-pill-value-text"><?php echo esc_html( $fandom_label ); ?></span>
					<button type="button" class="fanfic-pill-value-remove" aria-label="<?php esc_attr_e( 'Remove fandom', 'fanfiction-manager' ); ?>">&times;</button>
					<input type="hidden" name="fanfic_story_fandoms[]" value="<?php echo esc_attr( $fandom_id ); ?>">
				</span>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== $args['description'] ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
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
function fanfic_block_story( $story_id, $reason = '', $actor_id = 0, $reason_text = '' ) {
	return fanfic_apply_post_block(
		$story_id,
		array(
			'actor_id'      => $actor_id ? $actor_id : get_current_user_id(),
			'block_type'    => 'manual',
			'block_reason'  => $reason,
			'block_reason_text' => $reason_text,
			'change_status' => false,
			'save_status'   => false,
		)
	);
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
	return fanfic_remove_post_block(
		$story_id,
		array(
			'actor_id'       => $actor_id ? $actor_id : get_current_user_id(),
			'restore_status' => false,
		)
	);
}

/**
 * Block a chapter.
 *
 * @since 1.0.0
 * @param int    $chapter_id Chapter post ID.
 * @param string $reason     Block reason code or text.
 * @param int    $actor_id   Actor user ID.
 * @return bool
 */
function fanfic_block_chapter( $chapter_id, $reason = '', $actor_id = 0, $reason_text = '' ) {
	return fanfic_apply_post_block(
		$chapter_id,
		array(
			'actor_id'      => $actor_id ? $actor_id : get_current_user_id(),
			'block_type'    => 'manual',
			'block_reason'  => $reason,
			'block_reason_text' => $reason_text,
			'change_status' => false,
			'save_status'   => false,
		)
	);
}

/**
 * Unblock a chapter.
 *
 * @since 1.0.0
 * @param int $chapter_id Chapter post ID.
 * @param int $actor_id   Actor user ID.
 * @return bool
 */
function fanfic_unblock_chapter( $chapter_id, $actor_id = 0 ) {
	return fanfic_remove_post_block(
		$chapter_id,
		array(
			'actor_id'       => $actor_id ? $actor_id : get_current_user_id(),
			'restore_status' => false,
		)
	);
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
 * Check whether the moderation chat is closed for a given blocked target.
 *
 * When chat is closed, authors cannot send new messages about this target.
 * Moderators can reopen it at any time.
 *
 * @since 2.3.0
 * @param string $target_type One of 'story', 'chapter', or 'user'.
 * @param int    $target_id   Post ID (story/chapter) or user ID.
 * @return bool True if chat is closed.
 */
function fanfic_is_mod_chat_closed( $target_type, $target_id ) {
	if ( 'story' === $target_type || 'chapter' === $target_type ) {
		return '1' === get_post_meta( (int) $target_id, '_fanfic_mod_chat_closed', true );
	}
	if ( 'user' === $target_type ) {
		return '1' === get_user_meta( (int) $target_id, 'fanfic_mod_chat_closed', true );
	}
	return false;
}

/**
 * Get block information for a story
 *
 * @since 1.2.0
 * @param int $story_id Story post ID.
 * @return array|false Block info or false if not blocked
 */
function fanfic_get_block_info( $story_id ) {
	if ( ! fanfic_is_post_blocked( $story_id ) ) {
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
function fanfic_get_age_badge_class( $value, $prefix = 'is-age-' ) {
	$prefix = trim( (string) $prefix );
	if ( '' === $prefix ) {
		$prefix = 'is-age-';
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
 * Map a status slug to canonical badge tone class.
 *
 * @since 1.0.0
 * @param string $status Status key (for example publish, draft, blocked).
 * @return string Tone class without leading dot.
 */
function fanfic_get_badge_tone_for_status( $status ) {
	$status = sanitize_key( (string) $status );

	$success = array( 'publish', 'published', 'visible', 'active', 'enabled', 'resolved', 'success' );
	$warning = array( 'draft', 'hidden', 'pending', 'warning', 'unread' );
	$danger  = array( 'blocked', 'suspended', 'deleted', 'danger', 'error' );
	$info    = array( 'info', 'dismissed' );
	$muted   = array( 'inactive', 'disabled', 'private', 'ignored', 'muted' );

	if ( in_array( $status, $success, true ) ) {
		return 'is-success';
	}
	if ( in_array( $status, $warning, true ) ) {
		return 'is-warning';
	}
	if ( in_array( $status, $danger, true ) ) {
		return 'is-danger';
	}
	if ( in_array( $status, $info, true ) ) {
		return 'is-info';
	}
	if ( in_array( $status, $muted, true ) ) {
		return 'is-muted';
	}

	return 'is-muted';
}

/**
 * Normalize age class name to canonical `is-age-*` modifier.
 *
 * @since 1.0.0
 * @param string $age_badge_class Age class (for example is-age-18-plus).
 * @return string Canonical modifier (for example is-age-18-plus).
 */
function fanfic_get_badge_age_modifier_class( $age_badge_class ) {
	$age_badge_class = sanitize_html_class( (string) $age_badge_class );
	if ( '' === $age_badge_class ) {
		return 'is-age-18-plus';
	}

	if ( 0 === strpos( $age_badge_class, 'is-age-' ) ) {
		return $age_badge_class;
	}

	return 'is-age-18-plus';
}

/**
 * Get normalized story age-confirmation settings.
 *
 * @since 2.0.0
 * @return array{enabled:bool,minimum_age:int}
 */
function fanfic_get_story_age_confirmation_settings() {
	$enabled = class_exists( 'Fanfic_Settings' ) ? (bool) Fanfic_Settings::get_setting( 'enable_story_age_confirmation', false ) : false;
	$minimum_age = class_exists( 'Fanfic_Settings' ) ? absint( Fanfic_Settings::get_setting( 'story_age_confirmation_minimum', 18 ) ) : 18;
	$minimum_age = min( 18, max( 1, $minimum_age ) );

	return array(
		'enabled'     => $enabled,
		'minimum_age' => $minimum_age,
	);
}

/**
 * Get age-gate data for a story when warning-based confirmation is required.
 *
 * @since 2.0.0
 * @param int $story_id Story post ID.
 * @return array<string,mixed>
 */
function fanfic_get_story_age_confirmation_data( $story_id ) {
	$story_id = absint( $story_id );
	$settings = fanfic_get_story_age_confirmation_settings();
	$inactive = array(
		'active'      => false,
		'story_id'    => $story_id,
		'minimum_age' => $settings['minimum_age'],
	);

	if ( ! $story_id || ! $settings['enabled'] ) {
		return $inactive;
	}

	if ( ! class_exists( 'Fanfic_Warnings' ) || ( class_exists( 'Fanfic_Settings' ) && ! Fanfic_Settings::get_setting( 'enable_warnings', true ) ) ) {
		return $inactive;
	}

	$warning_ids = Fanfic_Warnings::get_story_warning_ids( $story_id );
	if ( empty( $warning_ids ) ) {
		return $inactive;
	}

	$required_age = Fanfic_Warnings::calculate_derived_age( $warning_ids );
	$required_age = Fanfic_Warnings::normalize_age_label( $required_age, false );
	if ( '' === $required_age || ! is_numeric( $required_age ) ) {
		return $inactive;
	}

	$required_age_int = absint( $required_age );
	if ( $required_age_int < $settings['minimum_age'] ) {
		return $inactive;
	}

	$required_age_label = fanfic_get_age_display_label( (string) $required_age_int, false );
	if ( '' === $required_age_label ) {
		$required_age_label = (string) $required_age_int . '+';
	}

	return array(
		'active'             => true,
		'story_id'           => $story_id,
		'minimum_age'        => $settings['minimum_age'],
		'required_age'       => $required_age_int,
		'required_age_label' => $required_age_label,
		'badge_class'        => fanfic_get_age_badge_class( (string) $required_age_int ),
		'confirm_key'        => 'fanfic_age_gate_confirmed_' . $required_age_int,
		'leave_url'          => fanfic_get_story_archive_url(),
	);
}

/**
 * Wrap page content in the warning-based story age gate markup when needed.
 *
 * @since 2.0.0
 * @param string $content Rendered page content.
 * @param int    $story_id Story post ID.
 * @param string $context Gate context slug.
 * @return string
 */
function fanfic_wrap_story_age_confirmation_gate( $content, $story_id, $context = 'story' ) {
	$gate = fanfic_get_story_age_confirmation_data( $story_id );
	if ( empty( $gate['active'] ) ) {
		return (string) $content;
	}

	$context = sanitize_key( (string) $context );
	if ( '' === $context ) {
		$context = 'story';
	}

	$modal_id = 'fanfic-age-confirmation-modal-' . $context . '-' . $gate['story_id'];
	$title_id = $modal_id . '-title';
	$text_id  = $modal_id . '-text';
	$target_id = 'fanfic-age-gate-target-' . $context . '-' . $gate['story_id'];
	$story_title = wp_strip_all_tags( get_the_title( $gate['story_id'] ) );
	if ( '' === $story_title ) {
		$story_title = __( 'This story', 'fanfiction-manager' );
	}

	ob_start();
	?>
	<div
		id="<?php echo esc_attr( $modal_id ); ?>"
		class="fanfic-modal fanfic-age-confirmation-modal"
		role="dialog"
		aria-hidden="true"
		aria-modal="true"
		aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
		aria-describedby="<?php echo esc_attr( $text_id ); ?>"
		data-static-modal="true"
		style="display:none;"
	>
		<div class="fanfic-modal-overlay"></div>
		<div class="fanfic-modal-content fanfic-age-confirmation-modal-content">
			<div class="fanfic-modal-body fanfic-age-confirmation-modal-body">
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Age Confirmation Required', 'fanfiction-manager' ); ?></h2>
				<div id="<?php echo esc_attr( $text_id ); ?>" class="fanfic-age-confirmation-copy">
					<p>
						<?php
						printf(
							/* translators: 1: story title, 2: warning-derived age rating */
							esc_html__( '"%1$s" is marked %2$s.', 'fanfiction-manager' ),
							esc_html( $story_title ),
							esc_html( $gate['required_age_label'] )
						);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %d: minimum reader age */
							esc_html__( 'Please confirm that you are at least %d years old to continue reading.', 'fanfiction-manager' ),
							$gate['required_age']
						);
						?>
					</p>
				</div>
				<div class="fanfic-modal-actions fanfic-age-confirmation-actions">
					<button
						type="button"
						class="fanfic-button"
						data-fanfic-age-confirm="yes"
						data-target-id="<?php echo esc_attr( $target_id ); ?>"
						data-modal-id="<?php echo esc_attr( $modal_id ); ?>"
						data-confirm-key="<?php echo esc_attr( $gate['confirm_key'] ); ?>"
					>
						<?php
						printf(
							/* translators: %d: minimum reader age */
							esc_html__( 'I am %d or older', 'fanfiction-manager' ),
							$gate['required_age']
						);
						?>
					</button>
					<a class="fanfic-button secondary" href="<?php echo esc_url( $gate['leave_url'] ); ?>">
						<?php esc_html_e( 'Leave this page', 'fanfiction-manager' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<div
		id="<?php echo esc_attr( $target_id ); ?>"
		class="fanfic-age-gate-target fanfic-age-gate-is-locked"
		data-fanfic-age-gate="true"
		data-modal-id="<?php echo esc_attr( $modal_id ); ?>"
		data-confirm-key="<?php echo esc_attr( $gate['confirm_key'] ); ?>"
	>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php

	return ob_get_clean();
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
	$allowed = array( 'popularity', 'updated', 'alphabetical', 'created', 'likes', 'comments', 'views', 'rating', 'followers' );

	return in_array( $value, $allowed, true ) ? $value : '';
}

/**
 * Build SQL for the story popularity score.
 *
 * Volume leads, rating acts as a quality multiplier.
 *
 * @since 1.2.0
 * @param string $prefix SQL column prefix/alias.
 * @return string SQL expression.
 */
function fanfic_build_story_popularity_score_sql( $prefix = '' ) {
	$prefix = '' !== $prefix ? rtrim( $prefix, '.' ) . '.' : '';

	return sprintf(
		'((0.35 * LOG(1 + COALESCE(%1$sview_count, 0))) + (0.30 * LOG(1 + COALESCE(%1$slikes_total, 0))) + (0.20 * LOG(1 + COALESCE(%1$sfollow_count, 0))) + (0.15 * LOG(1 + COALESCE(%1$scomment_count, 0))) - (0.10 * LOG(1 + COALESCE(%1$sdislikes_total, 0)))) * (0.75 + (0.25 * (COALESCE(%1$srating_avg_total, 0) / 5)))',
		$prefix
	);
}

/**
 * Normalize sort direction.
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return string Direction value or empty string.
 */
function fanfic_normalize_sort_direction( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	$value = sanitize_key( $value );

	return in_array( $value, array( 'asc', 'desc' ), true ) ? $value : '';
}

/**
 * Normalize minimum story rating filter.
 *
 * Accepts values between 0 and 5 in 0.5 increments.
 *
 * @since 1.2.0
 * @param mixed $value Raw param value.
 * @return float Normalized minimum rating.
 */
function fanfic_normalize_rating_min_filter( $value ) {
	if ( '' === $value || null === $value ) {
		return 0.0;
	}

	$value = wp_unslash( (string) $value );
	$value = str_replace( ',', '.', trim( $value ) );

	if ( ! is_numeric( $value ) ) {
		return 0.0;
	}

	$rating = (float) $value;
	$rating = max( 0.0, min( 5.0, $rating ) );

	return round( $rating * 2 ) / 2;
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
		'direction'        => fanfic_normalize_sort_direction( $source['direction'] ?? '' ),
		'rating_min'       => fanfic_normalize_rating_min_filter( $source['rating_min'] ?? '' ),
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
 * Get story IDs that meet a minimum average rating.
 *
 * @since 1.2.0
 * @param float      $rating_min  Minimum average rating.
 * @param int[]|null $post_in     Allowed story IDs.
 * @param int[]      $post_not_in Excluded story IDs.
 * @return int[] Story IDs.
 */
function fanfic_get_story_ids_by_min_rating( $rating_min, $post_in = null, $post_not_in = array() ) {
	global $wpdb;

	$rating_min = max( 0.0, min( 5.0, (float) $rating_min ) );
	if ( $rating_min <= 0 ) {
		return is_array( $post_in ) ? array_map( 'absint', $post_in ) : array();
	}

	$table        = $wpdb->prefix . 'fanfic_story_search_index';
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table_exists !== $table ) {
		return array();
	}

	$where_clauses = array( "story_status = 'publish'", 'rating_avg_total >= %f' );
	$bind_values   = array( $rating_min );

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
	$results   = $wpdb->get_col(
		$wpdb->prepare( "SELECT story_id FROM {$table} WHERE {$where_sql}", $bind_values )
	);

	return array_map( 'absint', (array) $results );
}

/**
 * Sort and paginate story IDs using the search index table.
 *
 * @since 1.9.0
 * @param int[]|null $post_in     Allowed story IDs (null = all published).
 * @param int[]      $post_not_in Excluded story IDs.
 * @param string     $sort        Sort key: popularity|updated|created|alphabetical|likes|comments|views|rating|followers.
 * @param int        $paged       Current page (1-based).
 * @param int        $per_page    Posts per page.
 * @param string     $direction   Requested sort direction.
 * @param float      $rating_min  Minimum average rating.
 * @return array{ids: int[], total: int}
 */
function fanfic_sort_story_ids_via_index( $post_in, $post_not_in, $sort, $paged, $per_page, $direction = '', $rating_min = 0.0 ) {
	global $wpdb;

	$table        = $wpdb->prefix . 'fanfic_story_search_index';
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table_exists !== $table ) {
		return array( 'ids' => array(), 'total' => 0 );
	}

	$sort_map = array(
		'popularity'   => array( 'column' => fanfic_build_story_popularity_score_sql(), 'direction' => 'DESC', 'expression' => true ),
		'updated'      => array( 'column' => 'updated_date', 'direction' => 'DESC' ),
		'created'      => array( 'column' => 'published_date', 'direction' => 'DESC' ),
		'alphabetical' => array( 'column' => 'story_title', 'direction' => 'ASC' ),
		'likes'        => array( 'column' => 'likes_total', 'direction' => 'DESC' ),
		'comments'     => array( 'column' => 'comment_count', 'direction' => 'DESC' ),
		'views'        => array( 'column' => 'view_count', 'direction' => 'DESC' ),
		'rating'       => array( 'column' => 'rating_avg_total', 'direction' => 'DESC' ),
		'followers'    => array( 'column' => 'follow_count', 'direction' => 'DESC' ),
	);
	$sort_config  = isset( $sort_map[ $sort ] ) ? $sort_map[ $sort ] : $sort_map['updated'];
	$order_col    = $sort_config['column'];
	$order_dir    = 'asc' === strtolower( (string) $direction )
		? 'ASC'
		: ( 'desc' === strtolower( (string) $direction ) ? 'DESC' : $sort_config['direction'] );
	$order_expr   = ! empty( $sort_config['expression'] ) ? $order_col : "{$order_col} {$order_dir}";

	$where_clauses = array( "story_status = 'publish'" );
	$bind_values   = array();
	$rating_min    = max( 0.0, min( 5.0, (float) $rating_min ) );

	if ( $rating_min > 0 ) {
		$where_clauses[] = 'rating_avg_total >= %f';
		$bind_values[]   = $rating_min;
	}

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
	$order_sql = ! empty( $sort_config['expression'] )
		? "{$order_expr} {$order_dir}, updated_date DESC, story_id DESC"
		: "{$order_expr}, updated_date DESC, story_id DESC";

	if ( empty( $bind_values ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT story_id FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d, %d", $offset, $per_page ) );
	} else {
		$count_values = $bind_values;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $count_values ) );
		$offset_values = array_merge( $bind_values, array( $offset, $per_page ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT story_id FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d, %d", $offset_values ) );
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
	$sort_direction = $params['direction'] ?? '';

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

	// Determine sort key. Popularity is the default archive sort.
	$sort_key = '';
	if ( ! empty( $params['sort'] ) && in_array( $params['sort'], array( 'popularity', 'updated', 'created', 'alphabetical', 'likes', 'comments', 'views', 'rating', 'followers' ), true ) ) {
		$sort_key = $params['sort'];
	} else {
		$sort_key = 'popularity';
	}

	if ( '' !== $sort_key ) {
		// Sort and paginate via search index — avoids wp_posts orderby lookups.
		$sort_result = fanfic_sort_story_ids_via_index(
			is_array( $post__in ) ? $post__in : null,
			$post__not_in,
			$sort_key,
			$paged,
			$per_page,
			$sort_direction,
			(float) ( $params['rating_min'] ?? 0 )
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

	// Fallback: use popularity for archive defaults.
	if ( empty( $query_args['orderby'] ) ) {
		$sort_result = fanfic_sort_story_ids_via_index(
			is_array( $post__in ) ? $post__in : null,
			$post__not_in,
			'popularity',
			$paged,
			$per_page,
			$sort_direction,
			(float) ( $params['rating_min'] ?? 0 )
		);

		if ( ! empty( $sort_result['ids'] ) ) {
			$query_args['post__in']       = $sort_result['ids'];
			$query_args['orderby']        = 'post__in';
			$query_args['posts_per_page'] = count( $sort_result['ids'] );
			$query_args['paged']          = 1;
			$query_args['no_found_rows']  = true;
			unset( $query_args['post__not_in'] );
			return array(
				'args'        => $query_args,
				'found_posts' => $sort_result['total'],
			);
		}

		$query_args['post__in'] = array( 0 );
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
	if ( ! empty( $params['direction'] ) ) {
		$args['direction'] = $params['direction'];
	}
	if ( ! empty( $params['rating_min'] ) ) {
		$args['rating_min'] = number_format( (float) $params['rating_min'], 1, '.', '' );
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

	if ( ! empty( $params['rating_min'] ) ) {
		$filters[] = array(
			'label' => sprintf(
				__( 'Rating: %s+', 'fanfiction-manager' ),
				number_format_i18n( (float) $params['rating_min'], 1 )
			),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'rating_min' => null, 'paged' => null ) ),
		);
	}

	if ( ! empty( $params['sort'] ) ) {
		$sort_labels = array(
			'popularity'   => __( 'Popularity', 'fanfiction-manager' ),
			'updated'      => __( 'Updated', 'fanfiction-manager' ),
			'alphabetical' => __( 'A-Z', 'fanfiction-manager' ),
			'created'      => __( 'Publication date', 'fanfiction-manager' ),
			'likes'        => __( 'Likes', 'fanfiction-manager' ),
			'comments'     => __( 'Comments', 'fanfiction-manager' ),
			'views'        => __( 'Views', 'fanfiction-manager' ),
			'rating'       => __( 'Rating', 'fanfiction-manager' ),
			'followers'    => __( 'Followers', 'fanfiction-manager' ),
		);
		$filters[] = array(
			'label' => sprintf( __( 'Sort: %s', 'fanfiction-manager' ), $sort_labels[ $params['sort'] ] ?? $params['sort'] ),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'sort' => null, 'paged' => null ) ),
		);
	}

	if ( ! empty( $params['direction'] ) ) {
		$direction_labels = array(
			'asc'  => __( 'Ascending', 'fanfiction-manager' ),
			'desc' => __( 'Descending', 'fanfiction-manager' ),
		);
		$filters[] = array(
			'label' => sprintf( __( 'Order: %s', 'fanfiction-manager' ), $direction_labels[ $params['direction'] ] ?? $params['direction'] ),
			'url'   => fanfic_build_stories_url( $base_url, $params, array( 'direction' => null, 'paged' => null ) ),
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

/**
 * Get the canonical story content-updated datetime.
 *
 * @since 2.2.2
 * @param int $story_id Story post ID.
 * @return string MySQL datetime or empty string.
 */
function fanfic_sync_story_content_updated_date( $story_id ) {
	global $wpdb;

	$story_id = absint( $story_id );
	if ( ! $story_id ) {
		return '';
	}

	$postmeta_table = $wpdb->postmeta;
	$posts_table    = $wpdb->posts;
	$meta_key       = '_fanfic_chapter_content_updated_date';
	$current_value  = (string) get_post_meta( $story_id, '_fanfic_content_updated_date', true );
	$latest_update  = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT MAX(
				CASE
					WHEN pm.meta_value IS NOT NULL AND pm.meta_value <> '' THEN pm.meta_value
					ELSE ''
				END
			)
			FROM {$posts_table} p
			LEFT JOIN {$postmeta_table} pm
				ON pm.post_id = p.ID
				AND pm.meta_key = %s
			WHERE p.post_parent = %d
				AND p.post_type = 'fanfiction_chapter'
				AND p.post_status IN ('publish', 'draft', 'pending', 'private')",
			$meta_key,
			$story_id
		)
	);

	$canonical_date = $latest_update ? (string) $latest_update : $current_value;
	update_post_meta( $story_id, '_fanfic_content_updated_date', $canonical_date );

	return $canonical_date;
}

/**
 * Get the canonical story content-updated datetime.
 *
 * @since 2.2.2
 * @param int $story_id Story post ID.
 * @return string MySQL datetime or empty string.
 */
function fanfic_get_story_content_updated_date( $story_id ) {
	$story_id = absint( $story_id );
	if ( ! $story_id ) {
		return '';
	}

	$content_updated = (string) get_post_meta( $story_id, '_fanfic_content_updated_date', true );
	if ( '' !== $content_updated ) {
		return $content_updated;
	}

	return fanfic_sync_story_content_updated_date( $story_id );
}

/**
 * Get the canonical chapter content-updated datetime.
 *
 * @since 2.2.2
 * @param int $chapter_id Chapter post ID.
 * @return string MySQL datetime or empty string.
 */
function fanfic_get_chapter_content_updated_date( $chapter_id ) {
	$chapter_id = absint( $chapter_id );
	if ( ! $chapter_id ) {
		return '';
	}

	$content_updated = (string) get_post_meta( $chapter_id, '_fanfic_chapter_content_updated_date', true );
	return $content_updated;
}

/**
 * Get the canonical updated datetime for supported content.
 *
 * @since 2.2.2
 * @param int|WP_Post $post_or_id Post object or post ID.
 * @return string MySQL datetime or empty string.
 */
function fanfic_get_content_updated_date( $post_or_id ) {
	$post = get_post( $post_or_id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	if ( 'fanfiction_story' === $post->post_type ) {
		return fanfic_get_story_content_updated_date( $post->ID );
	}

	if ( 'fanfiction_chapter' === $post->post_type ) {
		return fanfic_get_chapter_content_updated_date( $post->ID );
	}

	return (string) $post->post_modified;
}

/**
 * Get the canonical updated timestamp for supported content.
 *
 * @since 2.2.2
 * @param int|WP_Post $post_or_id Post object or post ID.
 * @return int Unix timestamp or 0.
 */
function fanfic_get_content_updated_timestamp( $post_or_id ) {
	$datetime = fanfic_get_content_updated_date( $post_or_id );
	$timestamp = $datetime ? strtotime( $datetime ) : false;
	return false !== $timestamp ? (int) $timestamp : 0;
}

/**
 * Get the canonical updated ISO-8601 datetime for supported content.
 *
 * @since 2.2.2
 * @param int|WP_Post $post_or_id Post object or post ID.
 * @return string
 */
function fanfic_get_content_updated_iso8601( $post_or_id ) {
	$datetime = fanfic_get_content_updated_date( $post_or_id );
	if ( '' === $datetime ) {
		return '';
	}

	return mysql2date( 'c', $datetime, false );
}

/**
 * Get a stable report revision token for content.
 *
 * @since 2.2.2
 * @param int    $content_id   Content ID.
 * @param string $content_type Content type slug or post type.
 * @return string
 */
function fanfic_get_report_revision_token( $content_id, $content_type ) {
	$content_id   = absint( $content_id );
	$content_type = sanitize_key( (string) $content_type );

	if ( ! $content_id || '' === $content_type ) {
		return '';
	}

	$revision_source = '';
	$normalized_type = $content_type;

	if ( in_array( $content_type, array( 'story', 'fanfiction_story' ), true ) ) {
		$normalized_type = 'story';
		$revision_source = fanfic_get_story_content_updated_date( $content_id );
	} elseif ( in_array( $content_type, array( 'chapter', 'fanfiction_chapter' ), true ) ) {
		$normalized_type = 'chapter';
		$revision_source = fanfic_get_chapter_content_updated_date( $content_id );
	} elseif ( 'comment' === $content_type ) {
		$comment = get_comment( $content_id );
		if ( $comment ) {
			$normalized_type = 'comment';
			$revision_source = trim( preg_replace( '/\s+/', ' ', (string) $comment->comment_content ) );
		}
	}

	if ( '' === $revision_source ) {
		return '';
	}

	return md5( $normalized_type . '|' . $content_id . '|' . $revision_source );
}

/**
 * Get suspension reason labels.
 *
 * @since 2.3.0
 * @return array<string,string>
 */
function fanfic_get_suspension_reason_labels() {
	return array(
		'tos_violation' => __( 'Terms of Service Violation', 'fanfiction-manager' ),
		'harassment'    => __( 'Harassment / Bullying', 'fanfiction-manager' ),
		'spam'          => __( 'Spam / Advertising', 'fanfiction-manager' ),
		'ban_evasion'   => __( 'Ban Evasion', 'fanfiction-manager' ),
		'inappropriate' => __( 'Inappropriate Conduct', 'fanfiction-manager' ),
		'other'         => __( 'Other', 'fanfiction-manager' ),
	);
}

/**
 * Get a single suspension reason label.
 *
 * @since 2.3.0
 * @param string $reason Reason code.
 * @return string Human-readable label.
 */
function fanfic_get_suspension_reason_label( $reason ) {
	$labels = fanfic_get_suspension_reason_labels();
	return isset( $labels[ $reason ] ) ? $labels[ $reason ] : (string) $reason;
}

/**
 * Get a human-readable message explaining why a chapter is blocked.
 *
 * @since 2.3.0
 * @param int $chapter_id Chapter post ID.
 * @return string
 */
function fanfic_get_blocked_chapter_message( $chapter_id = 0 ) {
	if ( ! $chapter_id ) {
		return __( 'This chapter has been blocked by a moderator.', 'fanfiction-manager' );
	}

	$chapter = get_post( $chapter_id );
	if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
		return __( 'This chapter has been blocked by a moderator.', 'fanfiction-manager' );
	}

	$block_type   = get_post_meta( $chapter_id, '_fanfic_block_type', true );
	$block_reason = get_post_meta( $chapter_id, '_fanfic_block_reason', true );
	$block_reason_text = get_post_meta( $chapter_id, '_fanfic_block_reason_text', true );
	$blocked_at   = get_post_meta( $chapter_id, '_fanfic_blocked_timestamp', true );

	$timestamp_text = '';
	if ( $blocked_at ) {
		$timestamp_text = ' ' . sprintf(
			__( 'on %s', 'fanfiction-manager' ),
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $blocked_at )
		);
	}

	switch ( $block_type ) {
		case 'ban':
			return sprintf(
				__( 'This chapter was blocked%s because the author\'s account has been suspended.', 'fanfiction-manager' ),
				$timestamp_text
			);

		case 'rule':
			$rule_reason = is_string( $block_reason ) ? sanitize_text_field( $block_reason ) : '';
			return sprintf(
				__( 'This chapter was automatically blocked%s because site content rules have changed. %s', 'fanfiction-manager' ),
				$timestamp_text,
				$rule_reason
			);

		case 'manual':
		default:
			if ( $block_reason ) {
				$message = sprintf(
					__( 'Your chapter was blocked%s because: %s', 'fanfiction-manager' ),
					$timestamp_text,
					fanfic_get_block_reason_label( $block_reason )
				);
				if ( $block_reason_text ) {
					$message .= ' - ' . sanitize_text_field( $block_reason_text );
				}
				return $message;
			}
			return sprintf(
				__( 'This chapter was blocked%s. If you believe this is a mistake, please contact the site administrator.', 'fanfiction-manager' ),
				$timestamp_text
			);
	}
}

/**
 * Get a human-readable message explaining why a user's account is suspended.
 *
 * @since 2.3.0
 * @param int $user_id User ID.
 * @return string
 */
function fanfic_get_suspension_message( $user_id = 0 ) {
	if ( ! $user_id ) {
		return __( 'Your account has been suspended.', 'fanfiction-manager' );
	}

	$reason      = get_user_meta( $user_id, 'fanfic_suspension_reason', true );
	$reason_text = get_user_meta( $user_id, 'fanfic_suspension_reason_text', true );
	$banned_at   = get_user_meta( $user_id, 'fanfic_banned_at', true );

	$timestamp_text = '';
	if ( $banned_at ) {
		$timestamp_text = ' ' . sprintf(
			__( 'on %s', 'fanfiction-manager' ),
			date_i18n( get_option( 'date_format' ), strtotime( $banned_at ) )
		);
	}

	if ( $reason && 'other' !== $reason ) {
		$label = fanfic_get_suspension_reason_label( $reason );
		$msg   = sprintf(
			__( 'Your account was suspended%s for: %s', 'fanfiction-manager' ),
			$timestamp_text,
			$label
		);
		if ( $reason_text ) {
			$msg .= ' - ' . sanitize_text_field( $reason_text );
		}
		return $msg;
	}

	if ( $reason_text ) {
		return sprintf(
			__( 'Your account was suspended%s. Reason: %s', 'fanfiction-manager' ),
			$timestamp_text,
			sanitize_text_field( $reason_text )
		);
	}

	return sprintf(
		__( 'Your account was suspended%s.', 'fanfiction-manager' ),
		$timestamp_text
	);
}

/**
 * Get structured restriction context for a target.
 *
 * Returns an array with restriction details used by banners and message modals.
 *
 * @since 2.3.0
 * @param string $target_type 'story', 'chapter', or 'user'.
 * @param int    $target_id   Post ID or user ID.
 * @return array {
 *     @type bool   $is_restricted
 *     @type string $restriction_type  'story_blocked', 'chapter_blocked', 'user_suspended', or ''
 *     @type string $target_type
 *     @type int    $target_id
 *     @type string $reason_message
 *     @type bool   $has_active_message
 *     @type int    $owner_id
 *     @type string $moderator_reply
 * }
 */
function fanfic_get_restriction_context( $target_type, $target_id ) {
	$context = array(
		'is_restricted'      => false,
		'restriction_type'   => '',
		'target_type'        => $target_type,
		'target_id'          => absint( $target_id ),
		'reason_message'     => '',
		'has_active_message' => false,
		'active_message_id'  => 0,
		'has_unread_moderator_reply' => false,
		'owner_id'           => 0,
		'moderator_reply'    => '',
	);

	if ( 'story' === $target_type ) {
		$story = get_post( $target_id );
		if ( ! $story ) {
			return $context;
		}
		$context['owner_id'] = absint( $story->post_author );
		if ( fanfic_is_story_blocked( $target_id ) ) {
			$context['is_restricted']    = true;
			$context['restriction_type'] = 'story_blocked';
			$context['reason_message']   = fanfic_get_blocked_story_message( $target_id );
		}
	} elseif ( 'chapter' === $target_type ) {
		$chapter = get_post( $target_id );
		if ( ! $chapter ) {
			return $context;
		}
		$context['owner_id'] = absint( $chapter->post_author );
		if ( fanfic_is_chapter_blocked( $target_id ) ) {
			$context['is_restricted']    = true;
			$context['restriction_type'] = 'chapter_blocked';
			$context['reason_message']   = fanfic_get_blocked_chapter_message( $target_id );
		}
	} elseif ( 'user' === $target_type ) {
		$context['owner_id'] = absint( $target_id );
		if ( get_user_meta( $target_id, 'fanfic_banned', true ) === '1' ) {
			$context['is_restricted']    = true;
			$context['restriction_type'] = 'user_suspended';
			$context['reason_message']   = fanfic_get_suspension_message( $target_id );
		}
	}

	if ( $context['is_restricted'] && class_exists( 'Fanfic_Moderation_Messages' ) ) {
		$active_message = Fanfic_Moderation_Messages::get_active_message(
			$context['owner_id'],
			$target_type,
			$target_id
		);
		$context['has_active_message'] = ! empty( $active_message['id'] );
		$context['active_message_id']  = ! empty( $active_message['id'] ) ? absint( $active_message['id'] ) : 0;
		$context['has_unread_moderator_reply'] = ! empty( $active_message['unread_for_author'] );
		$context['moderator_reply'] = fanfic_get_restriction_reply_message( $target_type, $target_id );

		if ( '' === $context['moderator_reply'] && $context['active_message_id'] > 0 && method_exists( 'Fanfic_Moderation_Messages', 'get_message_entries' ) ) {
			$entries = Fanfic_Moderation_Messages::get_message_entries( $context['active_message_id'], false );
			if ( ! empty( $entries ) ) {
				for ( $i = count( $entries ) - 1; $i >= 0; $i-- ) {
					if ( isset( $entries[ $i ]['sender_role'] ) && 'moderator' === $entries[ $i ]['sender_role'] ) {
						$context['moderator_reply'] = sanitize_textarea_field( (string) $entries[ $i ]['message'] );
						break;
					}
				}
			}
		}
	}

	return $context;
}

/**
 * Get the moderator reply currently attached to a restricted target.
 *
 * @since 2.3.1
 * @param string $target_type Restriction target type.
 * @param int    $target_id   Target object ID.
 * @return string
 */
function fanfic_get_restriction_reply_message( $target_type, $target_id ) {
	$target_type = sanitize_key( $target_type );
	$target_id   = absint( $target_id );

	if ( ! $target_id ) {
		return '';
	}

	if ( 'story' === $target_type || 'chapter' === $target_type ) {
		return sanitize_textarea_field( (string) get_post_meta( $target_id, '_fanfic_moderation_reply_message', true ) );
	}

	if ( 'user' === $target_type ) {
		return sanitize_textarea_field( (string) get_user_meta( $target_id, 'fanfic_moderation_reply_message', true ) );
	}

	return '';
}

/**
 * Store or clear the current moderator reply for a restricted target.
 *
 * @since 2.3.1
 * @param string $target_type Restriction target type.
 * @param int    $target_id   Target object ID.
 * @param string $reply       Reply text. Empty clears the stored reply.
 * @return void
 */
function fanfic_set_restriction_reply_message( $target_type, $target_id, $reply ) {
	$target_type = sanitize_key( $target_type );
	$target_id   = absint( $target_id );
	$reply       = sanitize_textarea_field( (string) $reply );

	if ( ! $target_id ) {
		return;
	}

	if ( '' === $reply ) {
		fanfic_clear_restriction_reply_message( $target_type, $target_id );
		return;
	}

	if ( 'story' === $target_type || 'chapter' === $target_type ) {
		update_post_meta( $target_id, '_fanfic_moderation_reply_message', $reply );
		return;
	}

	if ( 'user' === $target_type ) {
		update_user_meta( $target_id, 'fanfic_moderation_reply_message', $reply );
	}
}

/**
 * Clear the stored moderator reply for a restricted target.
 *
 * @since 2.3.1
 * @param string $target_type Restriction target type.
 * @param int    $target_id   Target object ID.
 * @return void
 */
function fanfic_clear_restriction_reply_message( $target_type, $target_id ) {
	$target_type = sanitize_key( $target_type );
	$target_id   = absint( $target_id );

	if ( ! $target_id ) {
		return;
	}

	if ( 'story' === $target_type || 'chapter' === $target_type ) {
		delete_post_meta( $target_id, '_fanfic_moderation_reply_message' );
		return;
	}

	if ( 'user' === $target_type ) {
		delete_user_meta( $target_id, 'fanfic_moderation_reply_message' );
	}
}

/**
 * Get an admin-facing restriction summary for moderation screens.
 *
 * @since 2.3.0
 * @param string $target_type Restriction target type.
 * @param int    $target_id   Target object ID.
 * @return string
 */
function fanfic_get_admin_restriction_summary( $target_type, $target_id ) {
	$target_type = sanitize_key( $target_type );
	$target_id   = absint( $target_id );

	if ( ! $target_id ) {
		return '';
	}

	$moderator_name = '';
	$timestamp      = '';
	$reason         = '';
	$reason_text    = '';
	$type_label     = '';

	if ( 'story' === $target_type || 'chapter' === $target_type ) {
		$post = get_post( $target_id );
		if ( ! $post ) {
			return '';
		}

		$type_label  = 'story' === $target_type ? __( 'Story blocked', 'fanfiction-manager' ) : __( 'Chapter blocked', 'fanfiction-manager' );
		$timestamp   = get_post_meta( $target_id, '_fanfic_blocked_timestamp', true );
		$reason      = get_post_meta( $target_id, '_fanfic_block_reason', true );
		$reason_text = get_post_meta( $target_id, '_fanfic_block_reason_text', true );

		if ( class_exists( 'Fanfic_Moderation_Log' ) ) {
			$actions = 'story' === $target_type
				? array( 'block_manual', 'block_ban', 'block_rule' )
				: array( 'chapter_block_manual', 'chapter_block_ban', 'chapter_block_rule' );
			$logs = Fanfic_Moderation_Log::get_logs(
				array(
					'target_type' => $target_type,
					'target_id'   => $target_id,
					'action'      => $actions,
					'limit'       => 1,
				)
			);

			if ( ! empty( $logs[0]['actor_id'] ) ) {
				$user = get_userdata( absint( $logs[0]['actor_id'] ) );
				if ( $user ) {
					$moderator_name = $user->display_name;
				}
			}
		}
	} elseif ( 'user' === $target_type ) {
		$type_label  = __( 'Account suspended', 'fanfiction-manager' );
		$timestamp   = get_user_meta( $target_id, 'fanfic_banned_at', true );
		$reason      = get_user_meta( $target_id, 'fanfic_suspension_reason', true );
		$reason_text = get_user_meta( $target_id, 'fanfic_suspension_reason_text', true );

		$moderator_id = absint( get_user_meta( $target_id, 'fanfic_banned_by', true ) );
		if ( $moderator_id ) {
			$user = get_userdata( $moderator_id );
			if ( $user ) {
				$moderator_name = $user->display_name;
			}
		}
	} else {
		return '';
	}

	$parts = array( $type_label );

	if ( $timestamp ) {
		$formatted_timestamp = is_numeric( $timestamp )
			? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp )
			: date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $timestamp ) );
		$parts[] = sprintf( __( 'on %s', 'fanfiction-manager' ), $formatted_timestamp );
	}

	if ( $moderator_name ) {
		$parts[] = sprintf( __( 'by %s.', 'fanfiction-manager' ), $moderator_name );
	} else {
		$parts[] = '.';
	}

	$summary = implode( ' ', array_filter( $parts ) );
	$summary = preg_replace( '/\s+\./', '.', $summary );

	if ( 'user' === $target_type ) {
		$reason_label = $reason ? fanfic_get_suspension_reason_label( $reason ) : '';
	} else {
		$reason_label = $reason ? fanfic_get_block_reason_label( $reason ) : '';
	}

	if ( $reason_label ) {
		$summary .= ' ' . sprintf( __( 'Reason: %s', 'fanfiction-manager' ), $reason_label );
	}

	if ( $reason_text ) {
		$summary .= ' - ' . sanitize_text_field( $reason_text );
	}

	return trim( $summary );
}

/**
 * Render the moderation message modal once per page.
 *
 * @since 2.3.0
 * @return void
 */
function fanfic_render_moderation_message_modal() {
	static $modal_rendered = false;
	if ( $modal_rendered ) {
		return;
	}

	$modal_rendered = true;
	?>
	<div id="fanfic-mod-message-modal" class="fanfic-modal" style="display:none;" aria-hidden="true" aria-modal="true" role="dialog" aria-labelledby="fanfic-mod-modal-title">
		<div class="fanfic-modal-overlay"></div>
		<div class="fanfic-modal-content">
			<h2 id="fanfic-mod-modal-title"><?php esc_html_e( 'Moderation Chat', 'fanfiction-manager' ); ?></h2>
			<p class="fanfic-modal-description"><?php esc_html_e( 'Use this chat to communicate with the moderation team.', 'fanfiction-manager' ); ?></p>
			<form id="fanfic-mod-message-form">
				<input type="hidden" name="target_type" id="fanfic-mod-target-type" value="">
				<input type="hidden" name="target_id" id="fanfic-mod-target-id" value="">
				<input type="hidden" name="message_id" id="fanfic-mod-thread-id" value="">
				<input type="hidden" name="thread_context" id="fanfic-mod-thread-context" value="restriction">
				<div id="fanfic-mod-thread-state" class="fanfic-form-message" style="display:none;"></div>
				<div id="fanfic-mod-thread-history" class="fanfic-mod-thread-history" aria-live="polite"></div>
				<div class="fanfic-form-group">
					<label for="fanfic-mod-message-text"><?php esc_html_e( 'Send message:', 'fanfiction-manager' ); ?></label>
					<textarea
						id="fanfic-mod-message-text"
						name="message"
						rows="5"
						maxlength="1000"
						placeholder="<?php esc_attr_e( 'Write your message...', 'fanfiction-manager' ); ?>"
					></textarea>
				</div>
				<div class="fanfic-form-message" id="fanfic-mod-form-message" style="display:none;"></div>
				<div class="fanfic-modal-actions">
					<button type="button" class="fanfic-button secondary" id="fanfic-mod-modal-cancel">
						<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
					</button>
					<button type="submit" class="fanfic-button primary" id="fanfic-mod-modal-submit">
						<?php esc_html_e( 'Send Message', 'fanfiction-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

/**
 * Render the shared restriction notice for story/chapter view and edit contexts.
 *
 * @since 2.4.0
 * @param string $target_type Restriction target type.
 * @param int    $target_id   Target object ID.
 * @param string $page_context UI context (view-story, view-chapter, edit-story, edit-chapter).
 * @param array  $nav_buttons Optional nav buttons.
 * @return void
 */
function fanfic_render_restriction_notice( $target_type, $target_id, $page_context, $nav_buttons = array() ) {
	$target_type  = sanitize_key( (string) $target_type );
	$target_id    = absint( $target_id );
	$page_context = sanitize_key( (string) $page_context );

	if ( ! $target_id || '' === $target_type || ! function_exists( 'fanfic_get_restriction_context' ) ) {
		return;
	}

	$context = fanfic_get_restriction_context( $target_type, $target_id );
	if ( empty( $context['is_restricted'] ) ) {
		return;
	}

	$context['page_context'] = $page_context;
	fanfic_render_restriction_banner( $context, $nav_buttons );
}

/**
 * Render a restriction banner for blocked stories/chapters or suspended accounts.
 *
 * @since 2.3.0
 * @param array  $context     Restriction context from fanfic_get_restriction_context().
 * @param array  $nav_buttons Array of ['label' => string, 'url' => string, 'class' => string (optional)].
 * @return void
 */
function fanfic_render_restriction_banner( $context, $nav_buttons = array() ) {
	if ( empty( $context['is_restricted'] ) ) {
		return;
	}

	$restriction_type = $context['restriction_type'];
	$has_active       = ! empty( $context['has_active_message'] );
	$has_unread_reply = ! empty( $context['has_unread_moderator_reply'] );
	$target_type      = esc_attr( $context['target_type'] );
	$target_id        = absint( $context['target_id'] );
	$page_context     = isset( $context['page_context'] ) ? sanitize_key( (string) $context['page_context'] ) : '';
	$current_user_id  = get_current_user_id();
	$is_owner_view    = $current_user_id > 0 && ! empty( $context['owner_id'] ) && (int) $context['owner_id'] === (int) $current_user_id;
	$message_blacklisted = is_user_logged_in() && class_exists( 'Fanfic_Blacklist' )
		? Fanfic_Blacklist::is_message_sender_blacklisted( get_current_user_id() )
		: false;

	switch ( $restriction_type ) {
		case 'story_blocked':
			$title = __( 'Story Blocked', 'fanfiction-manager' );
			if ( 'edit-story' === $page_context && $is_owner_view ) {
				$info = __( 'Your edits stay hidden while this story is blocked. Saving lets moderation access your latest saved modifications. To prompt moderators to review them, send a moderation message after saving.', 'fanfiction-manager' );
			} elseif ( 'edit-chapter' === $page_context && $is_owner_view ) {
				$info = __( 'This chapter belongs to blocked story content. Saving lets moderation access your latest saved modifications. To prompt moderators to review them, send a moderation message after saving.', 'fanfiction-manager' );
			} else {
				$info = __( 'You can still view your story, but editing and visibility changes are disabled until the block is lifted.', 'fanfiction-manager' );
				if ( $is_owner_view ) {
					$info .= ' ' . __( 'Moderation can access your latest saved version. Send a moderation message to prompt review.', 'fanfiction-manager' );
				}
			}
			break;
		case 'chapter_blocked':
			$title = __( 'Chapter Blocked', 'fanfiction-manager' );
			if ( 'edit-chapter' === $page_context && $is_owner_view ) {
				$info = __( 'This chapter is blocked. Saving lets moderation access your latest saved modifications. To prompt moderators to review them, send a moderation message after saving.', 'fanfiction-manager' );
			} else {
				$info = __( 'You can still view this chapter, but editing and visibility actions are disabled until the block is lifted.', 'fanfiction-manager' );
				if ( $is_owner_view ) {
					$info .= ' ' . __( 'Moderation can access your latest saved version. Send a moderation message to prompt review.', 'fanfiction-manager' );
				}
			}
			break;
		case 'user_suspended':
			$title = __( 'Account Suspended', 'fanfiction-manager' );
			$info  = __( 'You can view your content but cannot create or edit stories while suspended.', 'fanfiction-manager' );
			break;
		default:
			$title = __( 'Restricted', 'fanfiction-manager' );
			$info  = '';
	}

	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
		<span class="fanfic-message-content">
			<strong><?php echo esc_html( $title ); ?></strong><br>
			<?php echo esc_html( $context['reason_message'] ); ?><br>
			<?php if ( $info ) : ?>
				<span class="fanfic-block-info"><?php echo esc_html( $info ); ?></span>
			<?php endif; ?>
			<span class="fanfic-message-actions">
				<?php foreach ( $nav_buttons as $btn ) : ?>
					<a href="<?php echo esc_url( $btn['url'] ); ?>" class="fanfic-button <?php echo isset( $btn['class'] ) ? esc_attr( $btn['class'] ) : 'secondary'; ?>">
						<?php echo esc_html( $btn['label'] ); ?>
					</a>
				<?php endforeach; ?>
				<?php if ( is_user_logged_in() && class_exists( 'Fanfic_Moderation_Messages' ) ) : ?>
					<?php if ( $message_blacklisted && ! $has_active ) : ?>
						<button type="button" class="fanfic-button secondary" disabled aria-disabled="true">
							<?php esc_html_e( 'Messaging Unavailable', 'fanfiction-manager' ); ?>
						</button>
					<?php else : ?>
						<?php $preview_mode = function_exists( 'fanfic_get_frontend_preview_mode' ) ? fanfic_get_frontend_preview_mode() : 'admin'; ?>
						<button type="button"
							class="fanfic-button secondary fanfic-message-mod-btn<?php echo $has_active ? ' fanfic-message-chat-active' : ''; ?><?php echo $has_unread_reply ? ' fanfic-message-chat-has-unread' : ''; ?>"
							data-target-type="<?php echo esc_attr( $target_type ); ?>"
							data-target-id="<?php echo esc_attr( $target_id ); ?>"
							data-thread-context="restriction"
							data-preview-mode="<?php echo esc_attr( $preview_mode ); ?>"
							data-open-label="<?php echo esc_attr( __( 'Open Moderation Chat', 'fanfiction-manager' ) ); ?>"
							data-has-unread="<?php echo $has_unread_reply ? '1' : '0'; ?>">
							<?php echo esc_html( $has_active ? __( 'Open Moderation Chat', 'fanfiction-manager' ) : __( 'Message Moderation', 'fanfiction-manager' ) ); ?>
						</button>
					<?php endif; ?>
				<?php endif; ?>
			</span>
		</span>
	</div>
	<?php

	fanfic_render_moderation_message_modal();
}
