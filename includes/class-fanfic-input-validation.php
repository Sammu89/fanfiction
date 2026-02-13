<?php
/**
 * Input Validation Utility Class
 *
 * Centralized validation for all user inputs across interaction systems.
 * Returns WP_Error objects on validation failures for consistent error handling.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Input_Validation
 *
 * Provides centralized input validation with consistent error reporting.
 * All validation methods return validated value or WP_Error on failure.
 *
 * @since 1.0.0
 */
class Fanfic_Input_Validation {

	/**
	 * Validate chapter ID
	 *
	 * Checks that chapter exists, is published, and is readable.
	 *
	 * @since 1.0.0
	 * @param int  $chapter_id   Chapter ID to validate.
	 * @param bool $return_post  Whether to return post object instead of ID.
	 * @return int|WP_Post|WP_Error Chapter ID/post on success, WP_Error on failure.
	 */
	public static function validate_chapter_id( $chapter_id, $return_post = false ) {
		$chapter_id = absint( $chapter_id );

		if ( ! $chapter_id ) {
			return new WP_Error( 'invalid_chapter_id', __( 'Invalid chapter ID.', 'fanfiction-manager' ) );
		}

		$chapter = get_post( $chapter_id );

		if ( ! $chapter ) {
			return new WP_Error( 'chapter_not_found', __( 'Chapter not found.', 'fanfiction-manager' ) );
		}

		if ( 'fanfiction_chapter' !== $chapter->post_type ) {
			return new WP_Error( 'invalid_post_type', __( 'Post is not a chapter.', 'fanfiction-manager' ) );
		}

		if ( 'publish' !== $chapter->post_status && ! current_user_can( 'edit_post', $chapter_id ) ) {
			return new WP_Error( 'chapter_not_published', __( 'Chapter is not published.', 'fanfiction-manager' ) );
		}

		// Check if user can read this chapter
		if ( ! is_user_logged_in() && 'publish' !== $chapter->post_status ) {
			return new WP_Error( 'chapter_not_accessible', __( 'You do not have permission to access this chapter.', 'fanfiction-manager' ) );
		}

		return $return_post ? $chapter : $chapter_id;
	}

	/**
	 * Validate story ID
	 *
	 * Checks that story exists, is published, and is readable.
	 *
	 * @since 1.0.0
	 * @param int  $story_id     Story ID to validate.
	 * @param bool $return_post  Whether to return post object instead of ID.
	 * @return int|WP_Post|WP_Error Story ID/post on success, WP_Error on failure.
	 */
	public static function validate_story_id( $story_id, $return_post = false ) {
		$story_id = absint( $story_id );

		if ( ! $story_id ) {
			return new WP_Error( 'invalid_story_id', __( 'Invalid story ID.', 'fanfiction-manager' ) );
		}

		$story = get_post( $story_id );

		if ( ! $story ) {
			return new WP_Error( 'story_not_found', __( 'Story not found.', 'fanfiction-manager' ) );
		}

		if ( 'fanfiction_story' !== $story->post_type ) {
			return new WP_Error( 'invalid_post_type', __( 'Post is not a story.', 'fanfiction-manager' ) );
		}

		if ( 'publish' !== $story->post_status && ! current_user_can( 'edit_post', $story_id ) ) {
			return new WP_Error( 'story_not_published', __( 'Story is not published.', 'fanfiction-manager' ) );
		}

		// Check if user can read this story
		if ( ! is_user_logged_in() && 'publish' !== $story->post_status ) {
			return new WP_Error( 'story_not_accessible', __( 'You do not have permission to access this story.', 'fanfiction-manager' ) );
		}

		return $return_post ? $story : $story_id;
	}

	/**
	 * Validate user ID
	 *
	 * Checks that user ID is valid and optionally that user exists.
	 *
	 * @since 1.0.0
	 * @param int  $user_id      User ID to validate.
	 * @param bool $check_exists Whether to verify user exists in database.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	public static function validate_user_id( $user_id, $check_exists = true ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user_id', __( 'Invalid user ID.', 'fanfiction-manager' ) );
		}

		if ( $check_exists ) {
			$user = get_userdata( $user_id );

			if ( ! $user ) {
				return new WP_Error( 'user_not_found', __( 'User not found.', 'fanfiction-manager' ) );
			}
		}

		return $user_id;
	}

	/**
	 * Validate rating value
	 *
	 * Checks that rating is between 1 and 5.
	 *
	 * @since 1.0.0
	 * @param int $rating Rating value to validate.
	 * @return int|WP_Error Rating on success, WP_Error on failure.
	 */
	public static function validate_rating( $rating ) {
		$rating = absint( $rating );

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'fanfiction-manager' ) );
		}

		return $rating;
	}

	/**
	 * Validate bookmark type
	 *
	 * Checks that bookmark type is valid enum value.
	 *
	 * @since 1.0.0
	 * @param string $type Bookmark type to validate.
	 * @return string|WP_Error Bookmark type on success, WP_Error on failure.
	 */
	public static function validate_bookmark_type( $type ) {
		$type = sanitize_text_field( $type );

		if ( ! in_array( $type, array( 'story', 'chapter' ), true ) ) {
			return new WP_Error( 'invalid_bookmark_type', __( 'Invalid bookmark type. Must be "story" or "chapter".', 'fanfiction-manager' ) );
		}

		return $type;
	}

	/**
	 * Validate subscription target type.
	 *
	 * Checks that subscription type is valid enum value.
	 *
	 * @since 1.0.0
	 * @param string $type Subscription type to validate.
	 * @return string|WP_Error Subscription type on success, WP_Error on failure.
	 */
	public static function validate_subscription_type( $type ) {
		$type = sanitize_text_field( $type );

		if ( ! in_array( $type, array( 'story', 'author' ), true ) ) {
			return new WP_Error( 'invalid_subscription_type', __( 'Invalid subscription type. Must be "story" or "author".', 'fanfiction-manager' ) );
		}

		return $type;
	}

	/**
	 * Sanitize and validate email for subscriptions
	 *
	 * Checks that email is valid and not empty.
	 *
	 * @since 1.0.0
	 * @param string $email Email address to validate.
	 * @return string|WP_Error Email address on success, WP_Error on failure.
	 */
	public static function sanitize_email_for_subscription( $email ) {
		$email = sanitize_email( $email );

		if ( empty( $email ) ) {
			return new WP_Error( 'empty_email', __( 'Email address is required.', 'fanfiction-manager' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'fanfiction-manager' ) );
		}

		return $email;
	}

	/**
	 * Generate secure unsubscribe token
	 *
	 * Creates a unique, secure token for email unsubscribe links.
	 *
	 * @since 1.0.0
	 * @return string 64-character secure token.
	 */
	public static function generate_unsubscribe_token() {
		// Use WordPress UUID function for uniqueness
		$uuid = wp_generate_uuid4();

		// Add additional entropy with hash
		$token = hash( 'sha256', $uuid . time() . wp_rand() );

		return $token;
	}

	/**
	 * Verify unsubscribe token
	 *
	 * Validates that token exists and matches the provided parameters.
	 *
	 * @since 1.0.0
	 * @param string $token             Unsubscribe token to verify.
	 * @param string $email             Email address.
	 * @param int    $target_id         Target ID (story or author).
	 * @param string $subscription_type Subscription type ('story' or 'author').
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function verify_unsubscribe_token( $token, $email, $target_id, $subscription_type ) {
		global $wpdb;

		// Validate inputs
		$token = sanitize_text_field( $token );
		$email = sanitize_email( $email );
		$target_id = absint( $target_id );
		$subscription_type = sanitize_text_field( $subscription_type );

		if ( empty( $token ) || empty( $email ) || ! $target_id ) {
			return new WP_Error( 'missing_parameters', __( 'Missing required parameters.', 'fanfiction-manager' ) );
		}

		// Validate subscription type
		if ( ! in_array( $subscription_type, array( 'story', 'author' ), true ) ) {
			return new WP_Error( 'invalid_subscription_type', __( 'Invalid subscription type.', 'fanfiction-manager' ) );
		}

		$table_name = $wpdb->prefix . 'fanfic_email_subscriptions';

		// Check if token exists and matches
		$subscription = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name}
			WHERE token = %s
			AND email = %s
			AND target_id = %d
			AND subscription_type = %s
			LIMIT 1",
			$token,
			$email,
			$target_id,
			$subscription_type
		) );

		if ( ! $subscription ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired unsubscribe token.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Check if user can interact
	 *
	 * Verifies user has permission to perform interaction actions.
	 * Checks for banned status, suspension, and capabilities.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID to check.
	 * @param string $action  Action type: 'read', 'rate', 'like', 'bookmark', 'comment'.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function can_interact( $user_id, $action = 'read' ) {
		// Validate user ID
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID.', 'fanfiction-manager' ) );
		}

		// Get user object
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User not found.', 'fanfiction-manager' ) );
		}

		// Check if user is banned/suspended (using meta keys)
		$is_banned = get_user_meta( $user_id, 'fanfic_banned', true );
		if ( $is_banned ) {
			return new WP_Error( 'user_banned', __( 'Your account has been banned from interacting.', 'fanfiction-manager' ) );
		}

		$is_suspended = get_user_meta( $user_id, 'fanfic_suspended', true );
		if ( $is_suspended ) {
			return new WP_Error( 'user_suspended', __( 'Your account has been temporarily suspended.', 'fanfiction-manager' ) );
		}

		// Check capabilities based on action
		$required_cap = 'read'; // Default capability

		switch ( $action ) {
			case 'rate':
			case 'like':
			case 'bookmark':
				// All logged-in users can perform these actions
				$required_cap = 'read';
				break;

			case 'comment':
				// Check if user can comment
				if ( ! user_can( $user, 'read' ) ) {
					return new WP_Error( 'cannot_comment', __( 'You do not have permission to comment.', 'fanfiction-manager' ) );
				}
				break;

			case 'read':
			default:
				// Just check basic read capability
				$required_cap = 'read';
				break;
		}

		if ( ! user_can( $user, $required_cap ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Validate post ID (generic for story or chapter)
	 *
	 * Checks that post exists and is a fanfiction post type.
	 *
	 * @since 1.0.0
	 * @param int  $post_id     Post ID to validate.
	 * @param bool $return_post Whether to return post object instead of ID.
	 * @return int|WP_Post|WP_Error Post ID/object on success, WP_Error on failure.
	 */
	public static function validate_post_id( $post_id, $return_post = false ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return new WP_Error( 'invalid_post_id', __( 'Invalid post ID.', 'fanfiction-manager' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'fanfiction-manager' ) );
		}

		if ( ! in_array( $post->post_type, array( 'fanfiction_story', 'fanfiction_chapter' ), true ) ) {
			return new WP_Error( 'invalid_post_type', __( 'Post is not a fanfiction story or chapter.', 'fanfiction-manager' ) );
		}

		return $return_post ? $post : $post_id;
	}

	/**
	 * Validate nonce for AJAX requests
	 *
	 * Wrapper around wp_verify_nonce with consistent error reporting.
	 *
	 * @since 1.0.0
	 * @param string $nonce  Nonce value to verify.
	 * @param string $action Action name for nonce.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function validate_nonce( $nonce, $action ) {
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed. Please refresh the page and try again.', 'fanfiction-manager' ) );
		}

		return true;
	}

	/**
	 * Validate integer within range
	 *
	 * Validates that value is an integer within specified min/max range.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to validate.
	 * @param int   $min   Minimum allowed value.
	 * @param int   $max   Maximum allowed value.
	 * @param string $field_name Field name for error messages.
	 * @return int|WP_Error Integer value on success, WP_Error on failure.
	 */
	public static function validate_int_range( $value, $min, $max, $field_name = 'Value' ) {
		$value = absint( $value );

		if ( $value < $min || $value > $max ) {
			return new WP_Error(
				'out_of_range',
				sprintf(
					/* translators: 1: field name, 2: min value, 3: max value */
					__( '%1$s must be between %2$d and %3$d.', 'fanfiction-manager' ),
					$field_name,
					$min,
					$max
				)
			);
		}

		return $value;
	}

	/**
	 * Validate array of IDs
	 *
	 * Validates that value is an array of positive integers.
	 *
	 * @since 1.0.0
	 * @param mixed $value Array to validate.
	 * @return array|WP_Error Array of integers on success, WP_Error on failure.
	 */
	public static function validate_id_array( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'not_array', __( 'Value must be an array.', 'fanfiction-manager' ) );
		}

		$value = array_map( 'absint', $value );
		$value = array_filter( $value );

		if ( empty( $value ) ) {
			return new WP_Error( 'empty_array', __( 'Array cannot be empty.', 'fanfiction-manager' ) );
		}

		return array_values( $value ); // Reindex array
	}

	/**
	 * Sanitize and validate text field
	 *
	 * Sanitizes text and optionally checks length constraints.
	 *
	 * @since 1.0.0
	 * @param string $value      Text to validate.
	 * @param int    $max_length Maximum allowed length (0 for no limit).
	 * @param string $field_name Field name for error messages.
	 * @return string|WP_Error Sanitized text on success, WP_Error on failure.
	 */
	public static function validate_text_field( $value, $max_length = 0, $field_name = 'Field' ) {
		$value = sanitize_text_field( $value );

		if ( $max_length > 0 && strlen( $value ) > $max_length ) {
			return new WP_Error(
				'text_too_long',
				sprintf(
					/* translators: 1: field name, 2: max length */
					__( '%1$s must not exceed %2$d characters.', 'fanfiction-manager' ),
					$field_name,
					$max_length
				)
			);
		}

		return $value;
	}
}
