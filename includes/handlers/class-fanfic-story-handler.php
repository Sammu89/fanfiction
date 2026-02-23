<?php
/**
 * Story Handler Class
 *
 * Handles all story-related operations including creation, editing, deletion, and publishing.
 *
 * @package FanfictionManager
 * @subpackage Handlers
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Fanfic_Story_Handler
 *
 * Story management operations.
 *
 * @since 1.0.0
 */
class Fanfic_Story_Handler {

	/**
	 * Registration flag to prevent duplicate registration
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Get story image input from URL or upload
	 *
	 * @param array $errors Errors array to append to.
	 * @return array Array with image_url and attachment_id.
	 */
	private static function get_story_image_input( &$errors ) {
		// Get image URL from text input first
		$image_url = isset( $_POST['fanfic_story_image'] ) ? esc_url_raw( trim( $_POST['fanfic_story_image'] ) ) : '';
		$attachment_id = 0;

		// Then, check for a file upload, which takes precedence
		if ( function_exists( 'fanfic_handle_image_upload' ) ) {
			// Check if a file was actually uploaded for this field
			if ( ! empty( $_FILES['fanfic_story_image_file']['name'] ) && $_FILES['fanfic_story_image_file']['error'] === UPLOAD_ERR_OK ) {
				$upload = fanfic_handle_image_upload( 'fanfic_story_image_file', __( 'Story image', 'fanfiction-manager' ), $errors );
				if ( $upload && ! empty( $upload['url'] ) ) {
					$image_url = $upload['url'];
					$attachment_id = ! empty( $upload['attachment_id'] ) ? absint( $upload['attachment_id'] ) : 0;
				}
			}
		}

		return array( $image_url, $attachment_id );
	}

	/**
	 * Check whether the current user can manually edit publication dates.
	 *
	 * Allowed: fanfiction_author, fanfiction_admin, WordPress administrator.
	 * Disallowed: fanfiction_moderator-only users.
	 *
	 * @since 2.1.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function user_can_edit_publish_date( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
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
	 * Parse and validate a YYYY-MM-DD publication date input.
	 *
	 * @since 2.1.0
	 * @param string $raw_date Raw request value.
	 * @param array  $errors Errors array by reference.
	 * @return array|null|false Array with local/gmt values, null for empty, false for invalid.
	 */
	private static function parse_publication_date_input( $raw_date, &$errors ) {
		$raw_date = trim( (string) $raw_date );
		if ( '' === $raw_date ) {
			return null;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
			$errors[] = __( 'Publication date format is invalid.', 'fanfiction-manager' );
			return false;
		}

		$timezone = wp_timezone();
		$date_obj = DateTime::createFromFormat( '!Y-m-d', $raw_date, $timezone );
		if ( ! $date_obj ) {
			$errors[] = __( 'Publication date is invalid.', 'fanfiction-manager' );
			return false;
		}

		$date_errors = DateTime::getLastErrors();
		if ( ( $date_errors && ! empty( $date_errors['warning_count'] ) ) || ( $date_errors && ! empty( $date_errors['error_count'] ) ) ) {
			$errors[] = __( 'Publication date is invalid.', 'fanfiction-manager' );
			return false;
		}

		$local_datetime = $date_obj->format( 'Y-m-d 00:00:00' );
		$local_ts       = strtotime( $local_datetime );
		if ( false === $local_ts ) {
			$errors[] = __( 'Publication date is invalid.', 'fanfiction-manager' );
			return false;
		}

		if ( $local_ts > current_time( 'timestamp' ) ) {
			$errors[] = __( 'Publication date cannot be in the future.', 'fanfiction-manager' );
			return false;
		}

		return array(
			'local' => $local_datetime,
			'gmt'   => get_gmt_from_date( $local_datetime ),
		);
	}

	/**
	 * Normalize submitted co-author IDs.
	 *
	 * @since 1.5.3
	 * @param array<int,mixed> $raw_ids Raw IDs from request.
	 * @param int              $story_id Story ID (0 for create mode before insert).
	 * @return int[]
	 */
	private static function sanitize_coauthor_ids( $raw_ids, $story_id = 0 ) {
		$story_id = absint( $story_id );
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw_ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$original_author = $story_id > 0 ? (int) get_post_field( 'post_author', $story_id ) : get_current_user_id();

		$filtered = array();
		foreach ( $ids as $user_id ) {
			if ( $user_id <= 0 ) {
				continue;
			}
			if ( $user_id === $original_author ) {
				continue;
			}
			$filtered[] = $user_id;
		}

		return array_values( array_unique( $filtered ) );
	}

	/**
	 * Sync co-author invitation/assignment set for a story.
	 *
	 * @since 1.5.3
	 * @param int              $story_id Story ID.
	 * @param int              $actor_user_id User performing the sync.
	 * @param array<int,mixed> $selected_ids Desired co-author user IDs.
	 * @return array{errors:array<int,string>,invited:array<int,int>,removed:array<int,int>}
	 */
	private static function sync_story_coauthors( $story_id, $actor_user_id, $selected_ids ) {
		$result = array(
			'errors'  => array(),
			'invited' => array(),
			'removed' => array(),
		);

		if ( ! class_exists( 'Fanfic_Coauthors' ) || ! Fanfic_Coauthors::is_enabled() ) {
			return $result;
		}

		$story_id      = absint( $story_id );
		$actor_user_id = absint( $actor_user_id );
		if ( ! $story_id || ! $actor_user_id ) {
			return $result;
		}

		$selected_ids = self::sanitize_coauthor_ids( $selected_ids, $story_id );
		$existing_rows = Fanfic_Coauthors::get_all_story_coauthors( $story_id );
		$existing_ids  = array_values( array_unique( array_filter( array_map( 'absint', wp_list_pluck( (array) $existing_rows, 'ID' ) ) ) ) );

		$to_invite = array_values( array_diff( $selected_ids, $existing_ids ) );
		$to_remove = array_values( array_diff( $existing_ids, $selected_ids ) );

		foreach ( $to_invite as $invite_user_id ) {
			$invite_result = Fanfic_Coauthors::invite_coauthor( $story_id, $invite_user_id, $actor_user_id );
			if ( ! empty( $invite_result['success'] ) ) {
				$result['invited'][] = $invite_user_id;
				continue;
			}

			if ( ! empty( $invite_result['message'] ) ) {
				$result['errors'][] = (string) $invite_result['message'];
			}
		}

		foreach ( $to_remove as $remove_user_id ) {
			$remove_result = Fanfic_Coauthors::remove_coauthor( $story_id, $remove_user_id, $actor_user_id );
			if ( ! empty( $remove_result['success'] ) ) {
				$result['removed'][] = $remove_user_id;
				continue;
			}

			if ( ! empty( $remove_result['message'] ) ) {
				$result['errors'][] = (string) $remove_result['message'];
			}
		}

		$result['errors'] = array_values( array_unique( array_filter( $result['errors'] ) ) );
		return $result;
	}

	/**
	 * Refresh story search index after story field updates.
	 *
	 * @since 2.1.0
	 * @param int $story_id Story ID.
	 * @return void
	 */
	private static function refresh_story_search_index( $story_id ) {
		$story_id = absint( $story_id );
		if ( ! $story_id ) {
			return;
		}

		if ( class_exists( 'Fanfic_Search_Index' ) && method_exists( 'Fanfic_Search_Index', 'update_index' ) ) {
			Fanfic_Search_Index::update_index( $story_id );
		}
	}

	/**
	 * Get a safe referer URL with fallback.
	 *
	 * @since 1.2.0
	 * @param string $fallback_url Fallback URL when referer is missing/invalid.
	 * @return string Safe URL.
	 */
	private static function get_safe_referer_url( $fallback_url = '' ) {
		$referer = wp_get_referer();
		if ( ! empty( $referer ) ) {
			$validated_referer = wp_validate_redirect( $referer, '' );
			if ( ! empty( $validated_referer ) ) {
				return $validated_referer;
			}
		}

		if ( ! empty( $fallback_url ) ) {
			return $fallback_url;
		}

		return home_url( '/' );
	}

	/**
	 * Get canonical story edit URL.
	 *
	 * @since 1.2.0
	 * @param int $story_id Story ID.
	 * @return string Story edit URL.
	 */
	private static function get_story_edit_url( $story_id ) {
		$story_id = absint( $story_id );
		if ( $story_id > 0 ) {
			$story_permalink = get_permalink( $story_id );
			if ( ! empty( $story_permalink ) ) {
				return add_query_arg( 'action', 'edit', $story_permalink );
			}
		}

		return self::get_page_url_with_fallback( 'create-story' );
	}

	/**
	 * Redirect safely with fallback to avoid blank pages on invalid targets.
	 *
	 * @since 1.2.0
	 * @param string $target_url   Preferred redirect URL.
	 * @param string $fallback_url Fallback redirect URL.
	 * @return void
	 */
	private static function redirect_with_fallback( $target_url, $fallback_url ) {
		if ( empty( $fallback_url ) ) {
			$fallback_url = home_url( '/' );
		}

		$target = wp_validate_redirect( $target_url, '' );
		if ( empty( $target ) ) {
			$target = $fallback_url;
		}

		if ( ! wp_safe_redirect( $target ) ) {
			wp_safe_redirect( $fallback_url );
		}
		exit;
	}

	/**
	 * Register story handlers
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Prevent duplicate registration
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		// Register form submission handlers
		add_action( 'template_redirect', array( __CLASS__, 'handle_unified_story_form' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_create_story_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_edit_story_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_delete_story' ) );

		// Register AJAX handlers for logged-in users
		add_action( 'wp_ajax_fanfic_submit_story_form', array( __CLASS__, 'handle_unified_story_form' ) );
		add_action( 'wp_ajax_fanfic_create_story', array( __CLASS__, 'ajax_create_story' ) );
		add_action( 'wp_ajax_fanfic_edit_story', array( __CLASS__, 'ajax_edit_story' ) );
		add_action( 'wp_ajax_fanfic_publish_story', array( __CLASS__, 'ajax_publish_story' ) );
		add_action( 'wp_ajax_fanfic_validate_story', array( __CLASS__, 'ajax_validate_story' ) );
	}

	/**
	 * Handle unified story form submission (template-story-form.php)
	 * Runs early on template_redirect to avoid headers already sent errors
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_unified_story_form() {
		// Check if this is a story form submission
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['fanfic_story_nonce'] ) ) {
			return;
		}
		$is_ajax = wp_doing_ajax();

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'You must be logged in to perform this action.', 'fanfiction-manager' ),
					'errors'  => array( __( 'You must be logged in to perform this action.', 'fanfiction-manager' ) ),
				) );
			}
			return;
		}

		// Detect mode.
		$is_edit_mode = false;
		$story_id = 0;
		if ( $is_ajax ) {
			$form_mode = isset( $_POST['fanfic_story_form_mode'] ) ? sanitize_key( wp_unslash( $_POST['fanfic_story_form_mode'] ) ) : 'create';
			$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;
			$is_edit_mode = ( 'edit' === $form_mode && $story_id > 0 );
		} else {
			$is_edit_mode = is_singular( 'fanfiction_story' ) && isset( $_GET['action'] ) && 'edit' === $_GET['action'];
			$story_id = $is_edit_mode ? get_the_ID() : 0;
		}

		// Verify nonce
		$nonce_action = 'fanfic_story_form_action' . ( $is_edit_mode ? '_' . $story_id : '' );
		if ( ! wp_verify_nonce( $_POST['fanfic_story_nonce'], $nonce_action ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => __( 'Security check failed.', 'fanfiction-manager' ),
					'errors'  => array( __( 'Security check failed.', 'fanfiction-manager' ) ),
				) );
			}
			wp_die( esc_html__( 'Security check failed.', 'fanfiction-manager' ) );
		}

		$current_user = wp_get_current_user();
		$errors = array();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$coauthor_ids = isset( $_POST['fanfic_story_coauthors'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );
		$form_action = isset( $_POST['fanfic_form_action'] ) ? sanitize_text_field( $_POST['fanfic_form_action'] ) : 'save_draft';
		$comments_enabled = isset( $_POST['fanfic_comments_enabled'] ) ? '1' : '0';

		// Get warnings and tags (Phase 4.1)
		$enable_warnings = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_warnings', true ) : true;
		$enable_tags     = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_tags', true ) : true;
		$warning_ids = $enable_warnings && isset( $_POST['fanfic_story_warnings'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_warnings'] ) : array();
		$visible_tags_raw = $enable_tags && isset( $_POST['fanfic_visible_tags'] ) ? sanitize_text_field( $_POST['fanfic_visible_tags'] ) : '';
		$invisible_tags_raw = $enable_tags && isset( $_POST['fanfic_invisible_tags'] ) ? sanitize_text_field( $_POST['fanfic_invisible_tags'] ) : '';

		// Get language (Phase 4.x)
		$language_id = isset( $_POST['fanfic_story_language'] ) ? absint( $_POST['fanfic_story_language'] ) : 0;
		$publish_date_raw = isset( $_POST['fanfic_story_publish_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fanfic_story_publish_date'] ) ) : '';
		$publish_date_data = null;
		if ( '' !== $publish_date_raw && self::user_can_edit_publish_date( get_current_user_id() ) ) {
			$publish_date_data = self::parse_publication_date_input( $publish_date_raw, $errors );
		}

		// Get translation links (Phase 5 - translation groups)
		$translation_ids = isset( $_POST['fanfic_story_translations'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_translations'] ) : array();

		// Get custom taxonomy values
		$custom_taxonomy_values = array();
		if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) {
			$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
			foreach ( $custom_taxonomies as $taxonomy ) {
				$post_key = 'fanfic_custom_' . $taxonomy['slug'];
				if ( isset( $_POST[ $post_key ] ) ) {
					$custom_taxonomy_values[ $taxonomy['id'] ] = array_map( 'absint', (array) $_POST[ $post_key ] );
				}
			}
		}

		// Parse tags from comma-separated string
		$visible_tags = $enable_tags ? array_filter( array_map( 'trim', explode( ',', $visible_tags_raw ) ) ) : array();
		$invisible_tags = $enable_tags ? array_filter( array_map( 'trim', explode( ',', $invisible_tags_raw ) ) ) : array();

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// Block manual transition from On Hiatus / Abandoned back to Ongoing
		// only when the story's content update date does not meet the ongoing threshold.
		// If no content update date exists (e.g. new story, no chapters yet) the change is allowed.
		// Administrators, fanfiction admins, and moderators bypass this restriction entirely.
		$privileged_roles   = array( 'administrator', 'fanfiction_admin', 'fanfiction_moderator' );
		$current_user_roles = (array) $current_user->roles;
		$bypass_status_restriction = count( array_intersect( $privileged_roles, $current_user_roles ) ) > 0;

		if ( ! $bypass_status_restriction && $is_edit_mode && $story_id > 0 && $status > 0 ) {
			$status_id_map = get_option( 'fanfic_default_status_term_ids', array() );
			$status_id_map = is_array( $status_id_map ) ? array_map( 'absint', $status_id_map ) : array();
			$id_ongoing    = isset( $status_id_map['ongoing'] ) ? $status_id_map['ongoing'] : 0;
			$id_on_hiatus  = isset( $status_id_map['on-hiatus'] ) ? $status_id_map['on-hiatus'] : 0;
			$id_abandoned  = isset( $status_id_map['abandoned'] ) ? $status_id_map['abandoned'] : 0;

			if ( $id_ongoing > 0 && $status === $id_ongoing ) {
				$current_terms    = wp_get_post_terms( $story_id, 'fanfiction_status', array( 'fields' => 'ids' ) );
				$current_term_ids = ( ! is_wp_error( $current_terms ) ) ? array_map( 'absint', $current_terms ) : array();
				$is_from_restricted = ( $id_on_hiatus > 0 && in_array( $id_on_hiatus, $current_term_ids, true ) ) ||
					( $id_abandoned > 0 && in_array( $id_abandoned, $current_term_ids, true ) );

				if ( $is_from_restricted ) {
					$content_updated_raw = (string) get_post_meta( $story_id, '_fanfic_content_updated_date', true );
					$qualifies           = true; // Allow by default when no update date exists yet.

					if ( '' !== $content_updated_raw ) {
						$updated_ts = strtotime( $content_updated_raw );
						$cutoff_ts  = Fanfic_Story_Status_Automation::get_hiatus_cutoff_timestamp();
						if ( false !== $updated_ts && $updated_ts <= $cutoff_ts ) {
							$qualifies = false;
						}
					}

					if ( ! $qualifies ) {
						$errors[] = __( "You can't change the current status to Ongoing unless you add or update a chapter.", 'fanfiction-manager' );
					}
				}
			}
		}

		// If errors, store in transient and redirect back
		if ( ! empty( $errors ) ) {
			if ( $is_ajax ) {
				wp_send_json_error( array(
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				) );
			}
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			$fallback_url = self::get_page_url_with_fallback( 'create-story' );
			$redirect_url = self::get_safe_referer_url( $fallback_url );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}

		// CREATE MODE
		if ( ! $is_edit_mode ) {
			// Generate unique slug
			$base_slug = sanitize_title( $title );
			$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

			// Create story as draft
			$insert_data = array(
				'post_type'    => 'fanfiction_story',
				'post_title'   => $title,
				'post_name'    => $unique_slug,
				'post_excerpt' => $introduction,
				'post_status'  => 'draft',
				'post_author'  => $current_user->ID,
			);
			if ( is_array( $publish_date_data ) ) {
				$insert_data['post_date'] = $publish_date_data['local'];
				$insert_data['post_date_gmt'] = $publish_date_data['gmt'];
			}

			$new_story_id = wp_insert_post( $insert_data );

			if ( is_wp_error( $new_story_id ) ) {
				$errors[] = $new_story_id->get_error_message();
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => implode( ' ', $errors ),
						'errors'  => $errors,
					) );
				}
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				$fallback_url = self::get_page_url_with_fallback( 'create-story' );
				$redirect_url = self::get_safe_referer_url( $fallback_url );
				self::redirect_with_fallback( $redirect_url, $fallback_url );
			}

			// Set genres
			if ( ! empty( $genres ) ) {
				wp_set_post_terms( $new_story_id, $genres, 'fanfiction_genre' );
			}

			// Set status
			wp_set_post_terms( $new_story_id, $status, 'fanfiction_status' );

			// Save fandoms
			if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
				Fanfic_Fandoms::save_story_fandoms( $new_story_id, $fandom_ids, $is_original_work );
			}

			// Save warnings (Phase 4.1)
			if ( $enable_warnings && class_exists( 'Fanfic_Warnings' ) ) {
				Fanfic_Warnings::save_story_warnings( $new_story_id, $warning_ids );
			}

			// Save language
			if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
				Fanfic_Languages::save_story_language( $new_story_id, $language_id );
			}

			// Save translation links on first save (create mode).
			if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
				Fanfic_Translations::save_story_translations( $new_story_id, $translation_ids );
			}

			// Save custom taxonomy values
			if ( class_exists( 'Fanfic_Custom_Taxonomies' ) && ! empty( $custom_taxonomy_values ) ) {
				foreach ( $custom_taxonomy_values as $taxonomy_id => $term_ids ) {
					Fanfic_Custom_Taxonomies::save_story_terms( $new_story_id, $taxonomy_id, $term_ids );
				}
			}

			// Save tags (Phase 4.1)
			if ( $enable_tags && function_exists( 'fanfic_save_visible_tags' ) ) {
				fanfic_save_visible_tags( $new_story_id, $visible_tags );
			}
			if ( $enable_tags && function_exists( 'fanfic_save_invisible_tags' ) ) {
				fanfic_save_invisible_tags( $new_story_id, $invisible_tags );
			}

			// Set featured image
			if ( ! empty( $image_url ) ) {
				update_post_meta( $new_story_id, '_fanfic_featured_image', $image_url );
			}
			if ( ! empty( $image_attachment_id ) ) {
				set_post_thumbnail( $new_story_id, $image_attachment_id );
			}

			// Author's Notes
			$notes_enabled  = isset( $_POST['fanfic_author_notes_enabled'] ) ? '1' : '0';
			$notes_position = ( isset( $_POST['fanfic_author_notes_position'] ) && 'above' === $_POST['fanfic_author_notes_position'] ) ? 'above' : 'below';
			$notes_content  = isset( $_POST['fanfic_author_notes'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) ) : '';
			update_post_meta( $new_story_id, '_fanfic_author_notes_enabled', $notes_enabled );
			update_post_meta( $new_story_id, '_fanfic_author_notes_position', $notes_position );
			update_post_meta( $new_story_id, '_fanfic_author_notes', $notes_content );
			update_post_meta( $new_story_id, '_fanfic_comments_enabled', $comments_enabled );

			$coauthor_sync = self::sync_story_coauthors( $new_story_id, $current_user->ID, $coauthor_ids );
			if ( ! empty( $coauthor_sync['errors'] ) && ! $is_ajax ) {
				Fanfic_Flash_Messages::add_message( 'warning', implode( ' ', $coauthor_sync['errors'] ) );
			}
			self::refresh_story_search_index( $new_story_id );

			// Redirect based on action
			$story_permalink = get_permalink( $new_story_id );
			if ( 'add_chapter' === $form_action ) {
				$redirect_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
			} else {
				$redirect_url = add_query_arg( 'action', 'edit', $story_permalink );
			}

			if ( $is_ajax ) {
				$status_label = __( 'Hidden', 'fanfiction-manager' );
				wp_send_json_success( array(
					'message'         => __( 'Story saved successfully.', 'fanfiction-manager' ),
					'story_id'        => $new_story_id,
					'post_status'     => 'draft',
					'status_class'    => 'draft',
					'status_label'    => $status_label,
					'form_mode'       => 'edit',
					'redirect_url'    => $redirect_url,
					'edit_url'        => add_query_arg( 'action', 'edit', $story_permalink ),
					'add_chapter_url' => add_query_arg( 'action', 'add-chapter', $story_permalink ),
					'edit_nonce'      => wp_create_nonce( 'fanfic_story_form_action_' . $new_story_id ),
					'coauthor_errors' => isset( $coauthor_sync['errors'] ) ? $coauthor_sync['errors'] : array(),
				) );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		// EDIT MODE
		else {
			$story = get_post( $story_id );

			// Check permissions
			if ( ! $story || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => __( 'You do not have permission to edit this story.', 'fanfiction-manager' ),
						'errors'  => array( __( 'You do not have permission to edit this story.', 'fanfiction-manager' ) ),
					) );
				}
				wp_die( esc_html__( 'You do not have permission to edit this story.', 'fanfiction-manager' ) );
			}

			// Determine post status based on action
			$current_status = get_post_status( $story_id );
			$post_status = $current_status;
			$existing_content_updated_date = (string) get_post_meta( $story_id, '_fanfic_content_updated_date', true );
			$current_publish_date = isset( $story->post_date ) ? substr( (string) $story->post_date, 0, 10 ) : '';
			$new_publish_date = is_array( $publish_date_data ) ? substr( (string) $publish_date_data['local'], 0, 10 ) : $current_publish_date;
			$publish_date_changed = is_array( $publish_date_data ) && $new_publish_date !== $current_publish_date;
			$content_unchanged = ( (string) $story->post_title === (string) $title ) && ( (string) $story->post_excerpt === (string) $introduction );
			$preserve_content_updated_meta = $publish_date_changed && $content_unchanged && '' !== $existing_content_updated_date;
			$is_forward_publish_date_change = false;
			if ( is_array( $publish_date_data ) ) {
				$current_publish_ts = strtotime( (string) $story->post_date );
				$new_publish_ts = strtotime( (string) $publish_date_data['local'] );
				if ( false !== $current_publish_ts && false !== $new_publish_ts ) {
					$is_forward_publish_date_change = $new_publish_ts > $current_publish_ts;
				}
			}

			// Anti-cheat validation: story-level forward date changes are not allowed.
			// Content update recency is chapter-driven only.
			if ( $is_forward_publish_date_change ) {
				$errors[] = __( 'Story update date is controlled by chapter activity. You can backdate publication for imports, but you cannot move it forward from the story form.', 'fanfiction-manager' );
			}

			if ( ! empty( $errors ) ) {
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => implode( ' ', $errors ),
						'errors'  => $errors,
					) );
				}
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				$fallback_url = self::get_story_edit_url( $story_id );
				$redirect_url = self::get_safe_referer_url( $fallback_url );
				self::redirect_with_fallback( $redirect_url, $fallback_url );
			}

			if ( 'save_draft' === $form_action ) {
				$post_status = 'draft';
			} elseif ( 'publish' === $form_action ) {
				// For publish action, save as draft first so we can validate the updated content
				$post_status = 'draft';
			}
			// For 'update' and 'add_chapter' actions, keep current status

			// Update story with the new data
			error_log( '=== STORY HANDLER UPDATE START ===' );
			error_log( 'Story ID: ' . $story_id );
			error_log( 'Introduction length: ' . strlen( $introduction ) );
			error_log( 'Introduction: ' . substr( $introduction, 0, 50 ) );
			error_log( 'Form action: ' . $form_action );

			$update_data = array(
				'ID'           => $story_id,
				'post_title'   => $title,
				'post_excerpt' => $introduction,
				'post_status'  => $post_status,
				'edit_date'    => true,
			);
			if ( is_array( $publish_date_data ) ) {
				$update_data['post_date'] = $publish_date_data['local'];
				$update_data['post_date_gmt'] = $publish_date_data['gmt'];
			}

			$result = wp_update_post( $update_data, true ); // true = return WP_Error on failure

			error_log( 'wp_update_post result: ' . ( is_wp_error( $result ) ? $result->get_error_message() : $result ) );

			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => implode( ' ', $errors ),
						'errors'  => $errors,
					) );
				}
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				$fallback_url = self::get_story_edit_url( $story_id );
				$redirect_url = self::get_safe_referer_url( $fallback_url );
				self::redirect_with_fallback( $redirect_url, $fallback_url );
			}

			if ( ! $result || 0 === $result ) {
				$errors[] = __( 'Failed to update story. Please try again.', 'fanfiction-manager' );
				if ( $is_ajax ) {
					wp_send_json_error( array(
						'message' => implode( ' ', $errors ),
						'errors'  => $errors,
					) );
				}
				set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
				$fallback_url = self::get_story_edit_url( $story_id );
				$redirect_url = self::get_safe_referer_url( $fallback_url );
				self::redirect_with_fallback( $redirect_url, $fallback_url );
			}

			// Clear post cache to ensure fresh data
			clean_post_cache( $story_id );
			wp_cache_delete( $story_id, 'posts' );
			wp_cache_delete( $story_id, 'post_meta' );

			// Verify the save
			$verify = get_post( $story_id );
			error_log( 'Verified post_excerpt length after update: ' . strlen( $verify->post_excerpt ) );
			error_log( 'Verified post_excerpt: ' . substr( $verify->post_excerpt, 0, 50 ) );
			error_log( '=== STORY HANDLER UPDATE END ===' );

			// NOW validate if user wanted to publish
			if ( 'publish' === $form_action ) {
				$validation_errors = Fanfic_Validation::get_validation_errors( $story_id );

				if ( ! empty( $validation_errors ) ) {
					if ( $is_ajax ) {
						wp_send_json_error( array(
							'message' => __( 'Story could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ),
							'errors'  => array_values( $validation_errors ),
						) );
					}
					// Validation failed - story stays as draft, show errors
					set_transient( 'fanfic_story_validation_errors_' . $current_user->ID . '_' . $story_id, $validation_errors, 60 );
					Fanfic_Flash_Messages::add_message( 'error', __( 'Story could not be published due to validation errors. Please correct them.', 'fanfiction-manager' ) );
					// Redirect back to story with validation error, removing any success params
					$fallback_url = self::get_story_edit_url( $story_id );
					$redirect_url = self::get_safe_referer_url( $fallback_url );
					$redirect_url = remove_query_arg( array( 'success', 'error' ), $redirect_url );
					$redirect_url = add_query_arg( 'action', 'edit', $redirect_url );
					self::redirect_with_fallback( $redirect_url, $fallback_url );
				}

				// Validation passed - now publish
				wp_update_post( array(
					'ID'          => $story_id,
					'post_status' => 'publish',
				) );

				// Clear cache again
				clean_post_cache( $story_id );
				wp_cache_delete( $story_id, 'posts' );
				wp_cache_delete( $story_id, 'post_meta' );
			}

			// Update genres
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );

			// Update status
			wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

			// Save fandoms
			if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
				Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
			}

			// Save warnings (Phase 4.1)
			if ( $enable_warnings && class_exists( 'Fanfic_Warnings' ) ) {
				Fanfic_Warnings::save_story_warnings( $story_id, $warning_ids );
			}

			// Save language
			if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) {
				Fanfic_Languages::save_story_language( $story_id, $language_id );
			}

			// Anti-cheat guard: manual date edits must not count as qualifying content updates.
			if ( $preserve_content_updated_meta ) {
				update_post_meta( $story_id, '_fanfic_content_updated_date', $existing_content_updated_date );
			}

			// Save translation links
			if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) {
				Fanfic_Translations::save_story_translations( $story_id, $translation_ids );
			}

			// Save custom taxonomy values
			if ( class_exists( 'Fanfic_Custom_Taxonomies' ) && ! empty( $custom_taxonomy_values ) ) {
				foreach ( $custom_taxonomy_values as $taxonomy_id => $term_ids ) {
					Fanfic_Custom_Taxonomies::save_story_terms( $story_id, $taxonomy_id, $term_ids );
				}
			}

			// Save tags (Phase 4.1)
			if ( $enable_tags && function_exists( 'fanfic_save_visible_tags' ) ) {
				fanfic_save_visible_tags( $story_id, $visible_tags );
			}
			if ( $enable_tags && function_exists( 'fanfic_save_invisible_tags' ) ) {
				fanfic_save_invisible_tags( $story_id, $invisible_tags );
			}

			// Update featured image
			if ( ! empty( $image_url ) ) {
				update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
			} else {
				delete_post_meta( $story_id, '_fanfic_featured_image' );
			}
			if ( ! empty( $image_attachment_id ) ) {
				set_post_thumbnail( $story_id, $image_attachment_id );
			}

			// Author's Notes
			$notes_enabled  = isset( $_POST['fanfic_author_notes_enabled'] ) ? '1' : '0';
			$notes_position = ( isset( $_POST['fanfic_author_notes_position'] ) && 'above' === $_POST['fanfic_author_notes_position'] ) ? 'above' : 'below';
			$notes_content  = isset( $_POST['fanfic_author_notes'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) ) : '';
			update_post_meta( $story_id, '_fanfic_author_notes_enabled', $notes_enabled );
			update_post_meta( $story_id, '_fanfic_author_notes_position', $notes_position );
			update_post_meta( $story_id, '_fanfic_author_notes', $notes_content );
			update_post_meta( $story_id, '_fanfic_comments_enabled', $comments_enabled );

			$coauthor_sync = self::sync_story_coauthors( $story_id, $current_user->ID, $coauthor_ids );
			if ( ! empty( $coauthor_sync['errors'] ) && ! $is_ajax ) {
				Fanfic_Flash_Messages::add_message( 'warning', implode( ' ', $coauthor_sync['errors'] ) );
			}
			self::refresh_story_search_index( $story_id );

			// Redirect based on action
			if ( 'add_chapter' === $form_action ) {
				if ( ! $is_ajax ) {
					Fanfic_Flash_Messages::add_message( 'success', __( 'Story updated. Now add your first chapter!', 'fanfiction-manager' ) );
				}
				$story_permalink = get_permalink( $story_id );
				$redirect_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
			} else {
				if ( ! $is_ajax ) {
					Fanfic_Flash_Messages::add_message( 'success', __( 'Story updated successfully!', 'fanfiction-manager' ) );
				}
				$redirect_url = self::get_story_edit_url( $story_id );
				$redirect_url = remove_query_arg( array( 'success', 'error' ), $redirect_url );
				$redirect_url = add_query_arg( 'action', 'edit', $redirect_url );
			}

			if ( $is_ajax ) {
				$final_status = get_post_status( $story_id );
				$status_class = ( 'publish' === $final_status ) ? 'published' : 'draft';
				$status_label = ( 'publish' === $final_status ) ? __( 'Visible', 'fanfiction-manager' ) : __( 'Hidden', 'fanfiction-manager' );
				$success_message = ( 'add_chapter' === $form_action )
					? __( 'Story updated. You can now add a chapter.', 'fanfiction-manager' )
					: __( 'Story updated successfully!', 'fanfiction-manager' );

				wp_send_json_success( array(
					'message'         => $success_message,
					'story_id'        => $story_id,
					'post_status'     => $final_status,
					'status_class'    => $status_class,
					'status_label'    => $status_label,
					'form_mode'       => 'edit',
					'redirect_url'    => $redirect_url,
					'edit_url'        => self::get_story_edit_url( $story_id ),
					'add_chapter_url' => add_query_arg( 'action', 'add-chapter', get_permalink( $story_id ) ),
					'edit_nonce'      => wp_create_nonce( 'fanfic_story_form_action_' . $story_id ),
					'coauthor_errors' => isset( $coauthor_sync['errors'] ) ? $coauthor_sync['errors'] : array(),
				) );
			}

			$fallback_url = self::get_story_edit_url( $story_id );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}
	}

	/**
	 * Get page URL with fallback to manual construction
	 *
	 * Helper function to build URLs for plugin pages. First tries to get the page by path,
	 * if that fails, constructs the URL manually using the configured slugs.
	 *
	 * @since 1.0.0
	 * @param string $page_slug The page slug to look for (e.g., 'edit-story', 'manage-stories').
	 * @param string $page_path The relative path after dashboard slug (e.g., 'edit-story', 'manage-stories').
	 * @return string The page URL.
	 */
	public static function get_page_url_with_fallback( $page_slug, $page_path = '' ) {
		// Special handling for create-story: use URL manager which returns main page with ?action=create-story
		if ( 'create-story' === $page_slug ) {
			$url_manager = Fanfic_URL_Manager::get_instance();
			$url = $url_manager->get_page_url( 'create-story' );
			if ( ! empty( $url ) ) {
				return $url;
			}
			// Fallback if URL manager fails
			$page_ids = get_option( 'fanfic_system_page_ids', array() );
			$use_base_slug = (bool) get_option( 'fanfic_use_base_slug', true );
			$main_page_mode = (string) get_option( 'fanfic_main_page_mode', 'custom_homepage' );
			$target_page_id = 0;

			if ( ! $use_base_slug && 'stories_homepage' === $main_page_mode && ! empty( $page_ids['stories'] ) ) {
				$target_page_id = absint( $page_ids['stories'] );
			} elseif ( ! empty( $page_ids['main'] ) ) {
				$target_page_id = absint( $page_ids['main'] );
			} elseif ( ! empty( $page_ids['stories'] ) ) {
				$target_page_id = absint( $page_ids['stories'] );
			}

			if ( $target_page_id > 0 ) {
				$page_url = get_permalink( $target_page_id );
				if ( $page_url ) {
					return add_query_arg( 'action', 'create-story', $page_url );
				}
			}
		}

		// Try to get the page by path first
		$page = get_page_by_path( $page_slug );
		if ( $page ) {
			return get_permalink( $page );
		}

		// Fallback to manual URL construction
		$base_slug = get_option( 'fanfic_base_slug', 'fanfiction' );
		$dashboard_slug = get_option( 'fanfic_dashboard_slug', 'dashboard' );

		// If no custom page_path provided, use the page_slug
		if ( empty( $page_path ) ) {
			$page_path = $page_slug;
		}

		// Build URL based on whether it's a dashboard page or not
		if ( in_array( $page_slug, array( 'dashboard', 'edit-story', 'edit-chapter', 'manage-stories', 'edit-profile' ), true ) ) {
			// Dashboard pages
			$url = home_url( "/{$base_slug}/{$dashboard_slug}/{$page_path}/" );
		} else {
			// Non-dashboard pages (archive, search, create-chapter, etc.)
			$url = home_url( "/{$base_slug}/{$page_path}/" );
		}

		error_log( "Warning: Page '{$page_slug}' not found, using fallback URL: {$url}" );
		return $url;
	}

	/**
	 * Handle create story form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_create_story_submission() {
		if ( ! isset( $_POST['fanfic_create_story_submit'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_story_nonce'], 'fanfic_create_story_action' ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$errors = array();
		$current_user = wp_get_current_user();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$coauthor_ids = isset( $_POST['fanfic_story_coauthors'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			$fallback_url = self::get_page_url_with_fallback( 'create-story' );
			$redirect_url = self::get_safe_referer_url( $fallback_url );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}

		// Generate unique slug before creating the post
		$base_slug = sanitize_title( $title );
		$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

		// Create story as draft initially
		$story_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $title,
			'post_name'    => $unique_slug,
			'post_excerpt' => $introduction,
			'post_status'  => 'draft',
			'post_author'  => $current_user->ID,
		) );

		if ( is_wp_error( $story_id ) ) {
			$errors[] = $story_id->get_error_message();
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			$fallback_url = self::get_page_url_with_fallback( 'create-story' );
			$redirect_url = self::get_safe_referer_url( $fallback_url );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		// Author's Notes
		$notes_enabled  = isset( $_POST['fanfic_author_notes_enabled'] ) ? '1' : '0';
		$notes_position = ( isset( $_POST['fanfic_author_notes_position'] ) && 'above' === $_POST['fanfic_author_notes_position'] ) ? 'above' : 'below';
		$notes_content  = isset( $_POST['fanfic_author_notes'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) ) : '';
		update_post_meta( $story_id, '_fanfic_author_notes_enabled', $notes_enabled );
		update_post_meta( $story_id, '_fanfic_author_notes_position', $notes_position );
		update_post_meta( $story_id, '_fanfic_author_notes', $notes_content );
		update_post_meta( $story_id, '_fanfic_comments_enabled', $comments_enabled );

		$coauthor_sync = self::sync_story_coauthors( $story_id, $current_user->ID, $coauthor_ids );
		if ( ! empty( $coauthor_sync['errors'] ) ) {
			Fanfic_Flash_Messages::add_message( 'warning', implode( ' ', $coauthor_sync['errors'] ) );
		}
		self::refresh_story_search_index( $story_id );

		// Redirect to story permalink with action parameter
		$story_permalink = get_permalink( $story_id );
		Fanfic_Flash_Messages::add_message( 'success', __( 'Story created as draft. Now add your first chapter!', 'fanfiction-manager' ) );
		$add_chapter_url = add_query_arg( 'action', 'add-chapter', $story_permalink );
		$fallback_url = self::get_story_edit_url( $story_id );
		self::redirect_with_fallback( $add_chapter_url, $fallback_url );
	}

	/**
	 * Handle edit story form submission
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_edit_story_submission() {
		if ( ! isset( $_POST['fanfic_edit_story_submit'] ) ) {
			return;
		}

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_story_nonce'], 'fanfic_edit_story_action_' . $story_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			return;
		}

		$errors = array();

		// Get save action (draft or publish)
		$save_action = isset( $_POST['fanfic_save_action'] ) ? sanitize_text_field( $_POST['fanfic_save_action'] ) : 'draft';
		$post_status = ( 'publish' === $save_action ) ? 'publish' : 'draft';

		// Check if story currently has chapters
		$had_chapters_before = ! empty( get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) ) );

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$coauthor_ids = isset( $_POST['fanfic_story_coauthors'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, store and redirect back
		if ( ! empty( $errors ) ) {
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			$fallback_url = self::get_story_edit_url( $story_id );
			$redirect_url = self::get_safe_referer_url( $fallback_url );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}

		// Update story
		$result = wp_update_post( array(
			'ID'           => $story_id,
			'post_title'   => $title,
			'post_excerpt' => $introduction,
			'post_status'  => $post_status,
		) );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
			set_transient( 'fanfic_story_errors_' . $current_user->ID, $errors, 60 );
			$fallback_url = self::get_story_edit_url( $story_id );
			$redirect_url = self::get_safe_referer_url( $fallback_url );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}

		// Update genres
		wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );

		// Update status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Update featured image
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		} else {
			delete_post_meta( $story_id, '_fanfic_featured_image' );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		// Author's Notes
		$notes_enabled  = isset( $_POST['fanfic_author_notes_enabled'] ) ? '1' : '0';
		$notes_position = ( isset( $_POST['fanfic_author_notes_position'] ) && 'above' === $_POST['fanfic_author_notes_position'] ) ? 'above' : 'below';
		$notes_content  = isset( $_POST['fanfic_author_notes'] ) ? wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) ) : '';
		update_post_meta( $story_id, '_fanfic_author_notes_enabled', $notes_enabled );
		update_post_meta( $story_id, '_fanfic_author_notes_position', $notes_position );
		update_post_meta( $story_id, '_fanfic_author_notes', $notes_content );
		update_post_meta( $story_id, '_fanfic_comments_enabled', $comments_enabled );

		$coauthor_sync = self::sync_story_coauthors( $story_id, $current_user->ID, $coauthor_ids );
		if ( ! empty( $coauthor_sync['errors'] ) ) {
			Fanfic_Flash_Messages::add_message( 'warning', implode( ' ', $coauthor_sync['errors'] ) );
		}
		self::refresh_story_search_index( $story_id );

		// Determine redirect based on whether story had chapters before
		if ( ! $had_chapters_before && 'draft' === $save_action ) {
			// First save without chapters - redirect to add chapter page
			Fanfic_Flash_Messages::add_message( 'success', __( 'Story saved as draft. Now add your first chapter!', 'fanfiction-manager' ) );
			$redirect_url = add_query_arg(
				array(
					'action'   => 'add-chapter',
					'story_id' => $story_id,
				),
				self::get_page_url_with_fallback( 'manage-stories' )
			);
		} else {
			// Normal save - redirect back with success message
			Fanfic_Flash_Messages::add_message( 'success', __( 'Story updated successfully!', 'fanfiction-manager' ) );
			$redirect_url = self::get_story_edit_url( $story_id );
			$redirect_url = add_query_arg( 'action', 'edit', remove_query_arg( array( 'success', 'error' ), $redirect_url ) );
		}

		$fallback_url = self::get_story_edit_url( $story_id );
		self::redirect_with_fallback( $redirect_url, $fallback_url );
	}

	/**
	 * Handle delete story action
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_delete_story() {
		if ( ! isset( $_POST['fanfic_delete_story_submit'] ) ) {
			return;
		}

		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_delete_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_delete_story_nonce'], 'fanfic_delete_story_' . $story_id ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || ! current_user_can( 'delete_fanfiction_story', $story_id ) ) {
			return;
		}

		$is_blocked = (bool) get_post_meta( $story_id, '_fanfic_story_blocked', true );
		if ( $is_blocked && ! current_user_can( 'delete_others_posts' ) ) {
			Fanfic_Flash_Messages::add_message( 'error', fanfic_get_blocked_story_message() );
			$redirect_url = self::get_page_url_with_fallback( 'manage-stories' );
			$fallback_url = self::get_story_edit_url( $story_id );
			self::redirect_with_fallback( $redirect_url, $fallback_url );
		}

		// Delete all chapters first
		$chapters = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		foreach ( $chapters as $chapter_id ) {
			wp_delete_post( $chapter_id, true );
		}

		// Delete the story
		wp_delete_post( $story_id, true );

		// Redirect to manage stories with success message
        Fanfic_Flash_Messages::add_message( 'success', __( 'Story deleted successfully.', 'fanfiction-manager' ) );
		$redirect_url = self::get_page_url_with_fallback( 'manage-stories' );
		$fallback_url = function_exists( 'fanfic_get_dashboard_url' ) ? fanfic_get_dashboard_url() : home_url( '/' );
		self::redirect_with_fallback( $redirect_url, $fallback_url );
	}

	/**
	 * AJAX handler for creating a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_create_story() {
		// Verify nonce
		if ( ! isset( $_POST['fanfic_create_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_create_story_nonce'], 'fanfic_create_story_action' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'fanfiction-manager' )
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in to create a story.', 'fanfiction-manager' )
			) );
		}

		$errors = array();
		$current_user = wp_get_current_user();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$coauthor_ids = isset( $_POST['fanfic_story_coauthors'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, return error response
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $errors )
			) );
		}

		// Generate unique slug before creating the post
		$base_slug = sanitize_title( $title );
		$unique_slug = wp_unique_post_slug( $base_slug, 0, 'draft', 'fanfiction_story', 0 );

		// Create story as draft initially
		$story_id = wp_insert_post( array(
			'post_type'    => 'fanfiction_story',
			'post_title'   => $title,
			'post_name'    => $unique_slug,
			'post_excerpt' => $introduction,
			'post_status'  => 'draft',
			'post_author'  => $current_user->ID,
		) );

		if ( is_wp_error( $story_id ) ) {
			wp_send_json_error( array(
				'message' => $story_id->get_error_message()
			) );
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}

		$coauthor_sync = self::sync_story_coauthors( $story_id, $current_user->ID, $coauthor_ids );
		self::refresh_story_search_index( $story_id );

		// Build redirect URL to story permalink with action=add-chapter
		$story_permalink = get_permalink( $story_id );
		$add_chapter_url = add_query_arg( 'action', 'add-chapter', $story_permalink );

		// Return success response
		wp_send_json_success( array(
			'message' => __( 'Story created as draft. Add your first chapter to publish it.', 'fanfiction-manager' ),
			'redirect_url' => $add_chapter_url,
			'story_id' => $story_id,
			'story_slug' => $unique_slug,
			'coauthor_errors' => isset( $coauthor_sync['errors'] ) ? $coauthor_sync['errors'] : array(),
		) );
	}

	/**
	 * AJAX handler for editing a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_edit_story() {
		$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

		// Verify nonce
		if ( ! isset( $_POST['fanfic_edit_story_nonce'] ) || ! wp_verify_nonce( $_POST['fanfic_edit_story_nonce'], 'fanfic_edit_story_action_' . $story_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed. Please refresh and try again.', 'fanfiction-manager' )
			) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You must be logged in to edit a story.', 'fanfiction-manager' )
			) );
		}

		$current_user = wp_get_current_user();
		$story = get_post( $story_id );

		// Check permissions
		if ( ! $story || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to edit this story.', 'fanfiction-manager' )
			) );
		}

		$errors = array();

		// Get and sanitize form data
		$title = isset( $_POST['fanfic_story_title'] ) ? sanitize_text_field( $_POST['fanfic_story_title'] ) : '';
		$introduction = isset( $_POST['fanfic_story_introduction'] ) ? wp_kses_post( $_POST['fanfic_story_introduction'] ) : '';
		$genres = isset( $_POST['fanfic_story_genres'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_genres'] ) : array();
		$status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
		$fandom_ids = isset( $_POST['fanfic_story_fandoms'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_fandoms'] ) : array();
		$coauthor_ids = isset( $_POST['fanfic_story_coauthors'] ) ? array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] ) : array();
		$is_original_work = isset( $_POST['fanfic_is_original_work'] ) && '1' === $_POST['fanfic_is_original_work'];
		list( $image_url, $image_attachment_id ) = self::get_story_image_input( $errors );

		// Validate
		if ( empty( $title ) ) {
			$errors[] = __( 'Story title is required.', 'fanfiction-manager' );
		}

		if ( empty( $introduction ) ) {
			$errors[] = __( 'Story introduction is required.', 'fanfiction-manager' );
		}

		if ( ! $status ) {
			$errors[] = __( 'Story status is required.', 'fanfiction-manager' );
		}

		// If errors, return error response
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $errors )
			) );
		}

		// Update story
		$updated = wp_update_post( array(
			'ID'           => $story_id,
			'post_title'   => $title,
			'post_excerpt' => $introduction,
		) );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array(
				'message' => $updated->get_error_message()
			) );
		}

		// Set genres
		if ( ! empty( $genres ) ) {
			wp_set_post_terms( $story_id, $genres, 'fanfiction_genre' );
		}

		// Set status
		wp_set_post_terms( $story_id, $status, 'fanfiction_status' );

		// Save fandoms
		if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
			Fanfic_Fandoms::save_story_fandoms( $story_id, $fandom_ids, $is_original_work );
		}

		// Set featured image if provided
		if ( ! empty( $image_url ) ) {
			update_post_meta( $story_id, '_fanfic_featured_image', $image_url );
		}
		if ( ! empty( $image_attachment_id ) ) {
			set_post_thumbnail( $story_id, $image_attachment_id );
		}
		$coauthor_sync = self::sync_story_coauthors( $story_id, $current_user->ID, $coauthor_ids );
		self::refresh_story_search_index( $story_id );

		// Build redirect URL with fallback for clients that do full-page navigation.
		$fallback_url = self::get_story_edit_url( $story_id );
		$redirect_base = self::get_safe_referer_url( $fallback_url );
		$redirect_url = add_query_arg(
			array(
				'action'  => 'edit',
				'story_id' => $story_id,
				'updated' => 'success',
			),
			remove_query_arg( array( 'success', 'error' ), $redirect_base )
		);

		// Return success response
		wp_send_json_success( array(
			'message' => __( 'Story updated successfully!', 'fanfiction-manager' ),
			'redirect_url' => $redirect_url,
			'story_id' => $story_id,
			'coauthor_errors' => isset( $coauthor_sync['errors'] ) ? $coauthor_sync['errors'] : array(),
		) );
	}

	/**
	 * AJAX handler for publishing a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_publish_story() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_publish_story' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to publish stories.', 'fanfiction-manager' ) ) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid story ID.', 'fanfiction-manager' ) ) );
		}

		$story = get_post( $story_id );

		// Check if story exists
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Story not found.', 'fanfiction-manager' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to publish this story.', 'fanfiction-manager' ) ) );
		}

		// PRE-SAVE VALIDATION: Check if story can be published before saving
		$validation_result = Fanfic_Validation::can_publish_story( $story_id );

		if ( ! $validation_result['can_publish'] ) {
			wp_send_json_error( array(
				'message'         => __( 'Cannot publish story. Missing required fields:', 'fanfiction-manager' ),
				'missing_fields'  => $validation_result['missing_fields'], // Array of field => message pairs
				'errors'          => array_values( $validation_result['missing_fields'] ), // Just the messages
			) );
		}

		// Update story status to publish
		$result = wp_update_post( array(
			'ID'          => $story_id,
			'post_status' => 'publish',
		), true ); // true = return WP_Error on failure

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( ! $result || 0 === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to publish story. Please try again.', 'fanfiction-manager' ) ) );
		}

		// Clear post cache to ensure fresh data
		clean_post_cache( $story_id );
		wp_cache_delete( $story_id, 'posts' );
		wp_cache_delete( $story_id, 'post_meta' );
		self::refresh_story_search_index( $story_id );

		wp_send_json_success( array(
			'message' => __( 'Story is now visible to readers.', 'fanfiction-manager' ),
			'story_id' => $story_id
		) );
	}

	/**
	 * AJAX handler for validating a story
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_validate_story() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fanfic_validate_story' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fanfiction-manager' ) ) );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'fanfiction-manager' ) ) );
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid story ID.', 'fanfiction-manager' ) ) );
		}

		$story = get_post( $story_id );

		// Check if story exists
		if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Story not found.', 'fanfiction-manager' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to validate this story.', 'fanfiction-manager' ) ) );
		}

		// Check validation
		$validation_result = Fanfic_Validation::can_publish_story( $story_id );

		wp_send_json_success( array(
			'is_valid'        => $validation_result['can_publish'],
			'missing_fields'  => $validation_result['missing_fields'], // Array of field => message pairs
			'errors'          => array_values( $validation_result['missing_fields'] ), // Just the messages
		) );
	}

	/**
	 * Check if a story has chapters
	 *
	 * @since 1.0.0
	 * @param int $story_id Story ID
	 * @return bool True if story has chapters, false otherwise
	 */
	public static function story_has_chapters( $story_id ) {
		$chapter_count = get_posts( array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return ! empty( $chapter_count );
	}

}
