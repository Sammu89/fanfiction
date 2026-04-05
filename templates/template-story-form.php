<?php
/**
 * Template Name: Story Form (Unified Create/Edit)
 * Description: Unified form for creating new stories and editing existing ones
 *
 * This template handles both:
 * - Create mode: /fanfiction/?action=create-story (no story_id)
 * - Edit mode: /fanfiction/stories/{story-slug}/?action=edit (has story_id from singular context)
 *
 * @package Fanfiction_Manager
 * @since 2.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'You must be logged in to create or edit stories.', 'fanfiction-manager' ); ?>
			<a href="<?php echo esc_url( wp_login_url( fanfic_get_current_url() ) ); ?>" class="fanfic-button">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</span>
	</div>
	<?php
	return;
}

$fanfic_story_form_user = wp_get_current_user();
if ( in_array( 'fanfiction_banned_user', (array) $fanfic_story_form_user->roles, true ) ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'Your account is suspended. You can view your stories, but you cannot create or edit them.', 'fanfiction-manager' ); ?>
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button">
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</span>
	</div>
	<?php
	return;
}

// ========================================================================
// MODE DETECTION: Create vs Edit
// ========================================================================

// Detect if we're in edit mode based on whether we're viewing a singular story
$is_edit_mode = is_singular( 'fanfiction_story' );
$story_id = $is_edit_mode ? get_the_ID() : 0;
$story = null;
$story_title = '';

// For edit mode, validate story exists and user has permission
if ( $is_edit_mode ) {
	$story = get_post( $story_id );

	if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
		?>
		<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
			<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
			<span class="fanfic-message-content">
				<?php esc_html_e( 'Story not found.', 'fanfiction-manager' ); ?>
				<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button">
					<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
				</a>
			</span>
		</div>
		<?php
		return;
	}

	// Check if user has permission to edit this story
	if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
		?>
		<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
			<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
			<span class="fanfic-message-content">
				<?php esc_html_e( 'Access Denied: You do not have permission to edit this story.', 'fanfiction-manager' ); ?>
				<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button">
					<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
				</a>
			</span>
		</div>
		<?php
		return;
	}

	$is_blocked = (bool) get_post_meta( $story_id, '_fanfic_story_blocked', true );
	if ( $is_blocked ) {
		$is_moderator_view = function_exists( 'fanfic_current_user_can_use_moderation_controls' ) && fanfic_current_user_can_use_moderation_controls();
		if ( ! $is_moderator_view && function_exists( 'fanfic_render_restriction_notice' ) ) {
			fanfic_render_restriction_notice( 'story', $story_id, 'edit-story', array(
				array( 'label' => __( 'Back to Dashboard', 'fanfiction-manager' ), 'url' => fanfic_get_dashboard_url() ),
				array( 'label' => __( 'View Story', 'fanfiction-manager' ), 'url' => get_permalink( $story_id ), 'class' => 'secondary' ),
			) );
		}
		if ( ! $is_moderator_view ) {
			return;
		}
	}

	$story_title = $story->post_title;
} else {
	// Create mode - check if user has capability to create stories
	if ( ! current_user_can( 'edit_fanfiction_stories' ) ) {
		?>
		<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
			<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
			<span class="fanfic-message-content"><?php esc_html_e( 'Access Denied: You do not have permission to create stories.', 'fanfiction-manager' ); ?></span>
		</div>
		<?php
		return;
	}
}

// ========================================================================
// FORM SUBMISSION HANDLING
// ========================================================================
// NOTE: Form submission is handled in includes/handlers/class-fanfic-story-handler.php
// via the handle_unified_story_form() method hooked to template_redirect.
// This ensures processing happens before any output (headers) are sent.
// ========================================================================

?>

<!-- Include Warning Modals Template -->
<?php include( plugin_dir_path( __FILE__ ) . 'modal-warnings.php' ); ?>

<?php
fanfic_render_page_header( 'edit-story', array(
	'story_id'     => $story_id,
	'story_title'  => $story_title,
	'is_edit_mode' => $is_edit_mode,
) );
?>

<?php ob_start(); ?>
<!-- [STATUS MESSAGES] Zone for form save/error feedback. Do not use for cross-page alerts. -->
<div id="fanfic-messages" class="fanfic-messages-container" role="region" aria-label="<?php esc_attr_e( 'System Messages', 'fanfiction-manager' ); ?>" aria-live="polite">
<?php
// Display flash messages from previous request.
$flash_messages = class_exists( 'Fanfic_Flash_Messages' ) ? Fanfic_Flash_Messages::get_messages() : array();
if ( ! empty( $flash_messages ) ) {
	foreach ( $flash_messages as $msg ) {
		$type = isset( $msg['type'] ) ? sanitize_key( $msg['type'] ) : 'info';
		$message = isset( $msg['message'] ) ? (string) $msg['message'] : '';
		if ( '' === $message ) {
			continue;
		}

		$icon = ( 'success' === $type || 'info' === $type ) ? '&#10003;' : ( 'warning' === $type ? '&#9888;' : '&#10007;' );
		$role = ( 'error' === $type ) ? 'alert' : 'status';

		printf(
			"<div class='fanfic-message fanfic-message-%1\$s' role='%2\$s'><span class='fanfic-message-icon' aria-hidden='true'>%3\$s</span><span class='fanfic-message-content'>%4\$s</span><button class='fanfic-message-close' aria-label='%5\$s'>&times;</button></div>",
			esc_attr( $type ),
			esc_attr( $role ),
			$icon,
			esc_html( $message ),
			esc_attr__( 'Dismiss message', 'fanfiction-manager' )
		);
	}
}

// Success message from URL
if ( isset( $_GET['success'] ) && $_GET['success'] === 'true' ) : ?>
	<div class="fanfic-message fanfic-message-success" role="status">
		<span class="fanfic-message-icon" aria-hidden="true">✓</span>
		<span class="fanfic-message-content"><?php esc_html_e( 'Story updated successfully!', 'fanfiction-manager' ); ?></span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif;

// Error message from URL
if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-message fanfic-message-error" role="alert">
		<span class="fanfic-message-icon" aria-hidden="true">✕</span>
		<span class="fanfic-message-content"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif;

// Validation errors from transient
$errors = get_transient( 'fanfic_story_errors_' . get_current_user_id() );
if ( $errors ) {
	delete_transient( 'fanfic_story_errors_' . get_current_user_id() );
	?>
	<div class="fanfic-message fanfic-message-error" role="alert">
		<span class="fanfic-message-icon" aria-hidden="true">✕</span>
		<span class="fanfic-message-content">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
	<?php
}

// Pre-save validation errors
$validation_errors = $is_edit_mode ? get_transient( 'fanfic_story_validation_errors_' . get_current_user_id() . '_' . $story_id ) : false;
if ( $validation_errors ) {
	delete_transient( 'fanfic_story_validation_errors_' . get_current_user_id() . '_' . $story_id );
	?>
	<div class="fanfic-message fanfic-message-error" role="alert">
		<span class="fanfic-message-icon" aria-hidden="true">✕</span>
		<span class="fanfic-message-content">
			<p><strong><?php echo esc_html( fanfic_get_validation_error_heading( 'story' ) ); ?></strong></p>
			<ul>
				<?php foreach ( $validation_errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
	<?php
}

/**
 * Hook for adding messages to the story form.
 *
 * @since 1.2.0
 * @param int  $story_id     Story ID (0 for create mode).
 * @param bool $is_edit_mode Whether we're in edit mode.
 */
do_action( 'fanfic_story_form_messages', $story_id, $is_edit_mode );
?>
</div>
<?php
$fanfic_story_messages_markup = ob_get_clean();
?>
<?php
if ( $is_edit_mode ) {
	fanfic_render_moderation_controls( 'view-story', array( 'story_id' => $story_id ) );
}
?>

<?php
// ========================================================================
// PREPARE FORM VARIABLES
// ========================================================================

$current_genres = array();
$current_status = '';
$current_fandoms = array();
$current_fandom_labels = array();
$current_coauthors = array();
$original_coauthor_ids = array();
$is_original_work = false;
$featured_image = '';
$story_introduction = '';
$default_create_status = 0;
$can_edit_publish_date = function_exists( 'fanfic_can_edit_publish_date' ) ? fanfic_can_edit_publish_date( get_current_user_id() ) : false;
$story_publish_date = current_time( 'Y-m-d' );
$story_licence = 'all-rights-reserved';
$story_licence_type = 'all-rights-reserved';

if ( $is_edit_mode ) {
	// Pre-populate variables for edit mode
	$current_genres = wp_get_object_terms( $story->ID, 'fanfiction_genre', array( 'fields' => 'ids' ) );
	$current_status_obj = wp_get_object_terms( $story->ID, 'fanfiction_status', array( 'fields' => 'ids' ) );
	$current_status = ! empty( $current_status_obj ) ? $current_status_obj[0] : '';
	if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) {
		$current_fandoms = Fanfic_Fandoms::get_story_fandom_ids( $story->ID );
		$current_fandom_labels = Fanfic_Fandoms::get_story_fandom_labels( $story->ID, true );
		$is_original_work = (bool) get_post_meta( $story->ID, Fanfic_Fandoms::META_ORIGINAL, true );
	}
	if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) {
		$db_coauthors = Fanfic_Coauthors::get_all_story_coauthors( $story->ID );
		foreach ( (array) $db_coauthors as $coauthor ) {
			$coauthor_id = isset( $coauthor->ID ) ? absint( $coauthor->ID ) : 0;
			if ( ! $coauthor_id ) {
				continue;
			}
			$current_coauthors[] = array(
				'id'           => $coauthor_id,
				'display_name' => isset( $coauthor->display_name ) ? (string) $coauthor->display_name : '',
				'status'       => isset( $coauthor->status ) ? sanitize_key( $coauthor->status ) : '',
			);
			$original_coauthor_ids[] = $coauthor_id;
		}
	}
	$featured_image = get_post_meta( $story->ID, '_fanfic_featured_image', true );
	$story_introduction = $story->post_excerpt;
	$story_publish_date = mysql2date( 'Y-m-d', $story->post_date, false );
	if ( class_exists( 'Fanfic_Licence' ) && Fanfic_Licence::is_enabled() ) {
		$story_licence = Fanfic_Licence::get_story_licence( $story->ID );
	}
}
if ( isset( $_POST['fanfic_story_publish_date'] ) ) {
	$story_publish_date = sanitize_text_field( wp_unslash( $_POST['fanfic_story_publish_date'] ) );
}
if ( isset( $_POST['fanfic_story_licence'] ) ) {
	$story_licence = sanitize_text_field( wp_unslash( $_POST['fanfic_story_licence'] ) );
}
if ( in_array( $story_licence, array( 'cc-by', 'cc-by-sa', 'cc-by-nc', 'cc-by-nc-sa', 'cc-by-nd', 'cc-by-nc-nd' ), true ) ) {
	$story_licence_type = 'creative-commons';
} elseif ( 'public-domain' === $story_licence ) {
	$story_licence_type = 'public-domain';
}
if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() && isset( $_POST['fanfic_story_coauthors'] ) ) {
	$posted_coauthor_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $_POST['fanfic_story_coauthors'] ) ) ) );
	$status_map = array();
	foreach ( $current_coauthors as $existing_coauthor ) {
		if ( isset( $existing_coauthor['id'] ) ) {
			$status_map[ absint( $existing_coauthor['id'] ) ] = isset( $existing_coauthor['status'] ) ? (string) $existing_coauthor['status'] : '';
		}
	}

	$current_coauthors = array();
	if ( ! empty( $posted_coauthor_ids ) ) {
		$coauthor_users = get_users(
			array(
				'include' => $posted_coauthor_ids,
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$users_by_id = array();
		foreach ( (array) $coauthor_users as $coauthor_user ) {
			$users_by_id[ absint( $coauthor_user->ID ) ] = (string) $coauthor_user->display_name;
		}

		foreach ( $posted_coauthor_ids as $posted_coauthor_id ) {
			if ( ! isset( $users_by_id[ $posted_coauthor_id ] ) ) {
				continue;
			}
			$current_coauthors[] = array(
				'id'           => $posted_coauthor_id,
				'display_name' => $users_by_id[ $posted_coauthor_id ],
				'status'       => isset( $status_map[ $posted_coauthor_id ] ) ? $status_map[ $posted_coauthor_id ] : '',
			);
		}
	}
}

$form_mode = $is_edit_mode ? 'edit' : 'create';
if ( ! $is_edit_mode ) {
	// Default new stories to the canonical "ongoing" status term.
	$status_map = get_option( 'fanfic_default_status_term_ids', array() );
	if ( isset( $status_map['ongoing'] ) ) {
		$default_create_status = absint( $status_map['ongoing'] );
	}

	if ( ! $default_create_status ) {
		$ongoing_term = get_term_by( 'slug', 'ongoing', 'fanfiction_status' );
		if ( $ongoing_term && ! is_wp_error( $ongoing_term ) ) {
			$default_create_status = absint( $ongoing_term->term_id );
		}
	}
}
$image_upload_settings = function_exists( 'fanfic_get_image_upload_settings' ) ? fanfic_get_image_upload_settings() : array( 'enabled' => false, 'max_value' => 1, 'max_unit' => 'mb' );
$image_upload_enabled = ! empty( $image_upload_settings['enabled'] );

// Prepare data attributes for change detection (edit mode only)
$data_attrs = '';
if ( $is_edit_mode ) {
	$story_notes_enabled = get_post_meta( $story->ID, '_fanfic_author_notes_enabled', true );
	if ( '1' !== $story_notes_enabled ) {
		$story_notes_enabled = '0';
	}
	$story_notes_position = get_post_meta( $story->ID, '_fanfic_author_notes_position', true );
	if ( 'above' !== $story_notes_position ) {
		$story_notes_position = 'below';
	}
	$story_notes_content = get_post_meta( $story->ID, '_fanfic_author_notes', true );

	$data_attrs = sprintf(
		'data-original-title="%s" data-original-content="%s" data-original-genres="%s" data-original-status="%s" data-original-fandoms="%s" data-original-original="%s" data-original-image="%s" data-original-translations="%s" data-original-coauthors="%s" data-original-publish-date="%s" data-original-notes-enabled="%s" data-original-notes-position="%s" data-original-notes-content="%s" data-original-comments-enabled="%s"',
		esc_attr( $story->post_title ),
		esc_attr( $story->post_excerpt ),
		esc_attr( implode( ',', $current_genres ) ),
		esc_attr( $current_status ),
		esc_attr( implode( ',', $current_fandoms ) ),
		$is_original_work ? '1' : '0',
		esc_attr( $featured_image ),
		esc_attr(
			implode(
				',',
				wp_list_pluck(
					class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled()
						? Fanfic_Translations::get_translation_siblings( $story_id, false )
						: array(),
					'story_id'
				)
			)
		),
		esc_attr( implode( ',', array_values( array_unique( array_map( 'absint', $original_coauthor_ids ) ) ) ) ),
		esc_attr( $story_publish_date ),
		esc_attr( $story_notes_enabled ),
		esc_attr( $story_notes_position ),
		esc_attr( $story_notes_content ),
		esc_attr( get_post_meta( $story->ID, '_fanfic_comments_enabled', true ) ?: '1' )
	);
}
?>

<!-- Main Content Area -->
<div class="fanfic-content-layout">
	<!-- Story Form -->
	<div class="fanfic-content-primary">
		<section class="fanfic-content-section" class="fanfic-form-section" aria-labelledby="form-heading">
			<h2 id="form-heading" class="screen-reader-text"><?php echo $is_edit_mode ? esc_html__( 'Story Edit Form', 'fanfiction-manager' ) : esc_html__( 'Story Creation Form', 'fanfiction-manager' ); ?></h2>

			<!-- Story Form -->
			<div class="fanfic-form-wrapper fanfic-story-form-<?php echo esc_attr( $form_mode ); ?>">
				<div class="fanfic-form-header">
					<h2><?php echo $is_edit_mode ? sprintf( esc_html__( 'Edit Story: "%s"', 'fanfiction-manager' ), esc_html( $story->post_title ) ) : esc_html__( 'Create New Story', 'fanfiction-manager' ); ?></h2>
					<?php if ( $is_edit_mode ) : ?>
						<?php
						$post_status = get_post_status( $story_id );
						if ( fanfic_is_story_blocked( $story_id ) ) {
							$status_class = 'blocked';
							$status_text = __( 'Blocked', 'fanfiction-manager' );
						} else {
							$status_class = 'publish' === $post_status ? 'published' : 'draft';
							// Use plain-language labels: "Visible" and "Hidden" instead of "Published" / "Draft"
							$status_text = 'publish' === $post_status ? __( 'Visible', 'fanfiction-manager' ) : __( 'Hidden', 'fanfiction-manager' );
						}
						$status_tone_class = function_exists( 'fanfic_get_badge_tone_for_status' ) ? fanfic_get_badge_tone_for_status( $status_class ) : 'is-muted';
						?>
						<span class="fanfic-badge fanfic-badge--status fanfic-badge--status-lg <?php echo esc_attr( $status_tone_class ); ?> fanfic-status-<?php echo esc_attr( $status_class ); ?>" data-badge-type="status" data-badge-scope="story-form-status" data-status="<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text ); ?>
						</span>
					<?php endif; ?>
				</div>

				<form method="post" class="fanfic-story-form" id="fanfic-story-form" novalidate <?php echo $data_attrs; ?><?php echo $image_upload_enabled ? ' enctype="multipart/form-data"' : ''; ?>>
					<?php wp_nonce_field( 'fanfic_story_form_action' . ( $is_edit_mode ? '_' . $story_id : '' ), 'fanfic_story_nonce' ); ?>
					<?php
					$notes_enabled  = $is_edit_mode ? get_post_meta( $story_id, '_fanfic_author_notes_enabled', true ) : '0';
					$notes_position = $is_edit_mode ? ( get_post_meta( $story_id, '_fanfic_author_notes_position', true ) ?: 'below' ) : 'below';
					$notes_content  = $is_edit_mode ? get_post_meta( $story_id, '_fanfic_author_notes', true ) : '';
					if ( isset( $_POST['fanfic_author_notes_enabled'] ) ) {
						$notes_enabled = '1';
					}
					if ( isset( $_POST['fanfic_author_notes_position'] ) ) {
						$notes_position = ( 'above' === $_POST['fanfic_author_notes_position'] ) ? 'above' : 'below';
					}
					if ( isset( $_POST['fanfic_author_notes'] ) ) {
						$notes_content = wp_kses_post( wp_unslash( $_POST['fanfic_author_notes'] ) );
					}
					?>

					<div class="fanfic-form-content">

						<!-- Story Title -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_title"><?php esc_html_e( 'Story Title', 'fanfiction-manager' ); ?><?php fanfic_required_field_indicator(); ?></label>
							<input
								type="text"
								id="fanfic_story_title"
								name="fanfic_story_title"
								class="fanfic-input"
								maxlength="200"
								required
								value="<?php echo isset( $_POST['fanfic_story_title'] ) ? esc_attr( $_POST['fanfic_story_title'] ) : ( $is_edit_mode ? esc_attr( $story->post_title ) : '' ); ?>"
							/>
						</div>

						<!-- Story Introduction -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_intro"><?php esc_html_e( 'Story Introduction', 'fanfiction-manager' ); ?><?php fanfic_required_field_indicator(); ?></label>
							<textarea
								id="fanfic_story_intro"
								name="fanfic_story_introduction"
								class="fanfic-textarea"
								rows="8"
								maxlength="10000"
								required
							><?php echo isset( $_POST['fanfic_story_introduction'] ) ? esc_textarea( $_POST['fanfic_story_introduction'] ) : ( $is_edit_mode ? esc_textarea( $story->post_excerpt ) : '' ); ?></textarea>
						</div>

						<!-- Author's Notes -->
						<div class="fanfic-form-field fanfic-author-notes-field">
							<label>
								<input type="checkbox" name="fanfic_author_notes_enabled" value="1" class="fanfic-author-notes-toggle" <?php checked( '1', $notes_enabled ); ?> />
								<?php esc_html_e( "Enable Author's Notes", 'fanfiction-manager' ); ?>
							</label>
							<div class="fanfic-author-notes-options" <?php echo '1' !== $notes_enabled ? 'style="display:none;"' : ''; ?>>
								<div class="fanfic-author-notes-position-row">
									<span><?php esc_html_e( 'Notes', 'fanfiction-manager' ); ?></span>
									<select name="fanfic_author_notes_position" class="fanfic-select">
										<option value="above" <?php selected( 'above', $notes_position ); ?>><?php esc_html_e( 'above', 'fanfiction-manager' ); ?></option>
										<option value="below" <?php selected( 'below', $notes_position ); ?>><?php esc_html_e( 'below', 'fanfiction-manager' ); ?></option>
									</select>
									<span><?php esc_html_e( 'the story content', 'fanfiction-manager' ); ?></span>
								</div>
								<?php
								wp_editor( $notes_content, 'fanfic_story_author_notes', array(
									'textarea_name' => 'fanfic_author_notes',
									'media_buttons' => false,
									'teeny'         => false,
									'quicktags'     => false,
									'tinymce'       => array(
										'toolbar1'  => 'bold italic underline bullist numlist blockquote undo redo',
										'toolbar2'  => '',
										'menubar'   => false,
										'statusbar' => true,
										'resize'    => 'vertical',
										'height'    => 220,
									),
								) );
								?>
							</div>
						</div>
					</div>

					<?php
					$is_cc = 'creative-commons' === $story_licence_type;
					?>
					<div class="fanfic-form-content fanfic-auto-columns">
						<?php if ( class_exists( 'Fanfic_Licence' ) && Fanfic_Licence::is_enabled() ) : ?>
							<div class="fanfic-form-field fanfic-licence-field">
								<label><?php esc_html_e( 'Licence', 'fanfiction-manager' ); ?></label>
								<select id="fanfic_story_licence_type" class="fanfic-select fanfic-licence-select">
									<option value="all-rights-reserved" <?php selected( $story_licence_type, 'all-rights-reserved' ); ?>><?php esc_html_e( 'All Rights Reserved', 'fanfiction-manager' ); ?></option>
									<option value="creative-commons" <?php selected( $story_licence_type, 'creative-commons' ); ?>><?php esc_html_e( 'Creative Commons', 'fanfiction-manager' ); ?></option>
									<option value="public-domain" <?php selected( $story_licence_type, 'public-domain' ); ?>><?php esc_html_e( 'Public Domain / CC0', 'fanfiction-manager' ); ?></option>
								</select>

								<div class="fanfic-licence-details" aria-live="polite">
									<div class="fanfic-licence-detail<?php echo 'all-rights-reserved' === $story_licence_type ? ' is-active' : ''; ?>" data-licence-detail="all-rights-reserved">
										<h4><?php esc_html_e( 'All Rights Reserved', 'fanfiction-manager' ); ?></h4>
										<p class="description fanfic-licence-explanation"><?php esc_html_e( 'People can read it here, but they cannot repost, translate, adapt, or sell it without asking you first.', 'fanfiction-manager' ); ?></p>
										<p class="description fanfic-licence-warning"><?php esc_html_e( 'Use this when you want to keep full control over reuse.', 'fanfiction-manager' ); ?></p>
									</div>

									<div class="fanfic-licence-detail<?php echo $is_cc ? ' is-active' : ''; ?>" data-licence-detail="creative-commons">
										<h4><?php esc_html_e( 'Creative Commons', 'fanfiction-manager' ); ?></h4>
										<p class="description fanfic-licence-explanation"><?php esc_html_e( 'You choose what others are allowed to do with your text, and the site maps those choices to the correct CC licence below.', 'fanfiction-manager' ); ?></p>

										<div id="fanfic-cc-toggles" class="fanfic-cc-toggles" style="display: <?php echo $is_cc ? 'block' : 'none'; ?>;">
											<div class="fanfic-cc-toggle-row">
												<span class="fanfic-cc-toggle-label">
													<?php esc_html_e( 'Allow reposting / sharing (with credit)', 'fanfiction-manager' ); ?>
												</span>
												<span class="fanfic-cc-toggle-value fanfic-cc-always-on"><?php esc_html_e( 'Always on', 'fanfiction-manager' ); ?></span>
											</div>

											<div class="fanfic-cc-toggle-row">
												<label for="fanfic_cc_commercial">
													<?php esc_html_e( 'Allow commercial use?', 'fanfiction-manager' ); ?>
													<span class="fanfic-tooltip" title="<?php esc_attr_e( 'Commercial = someone can make money from it. For example: selling it, putting it behind a paywall, printing it, or monetizing videos/audiobooks with ads/sponsors.', 'fanfiction-manager' ); ?>">(?)</span>
												</label>
												<select id="fanfic_cc_commercial" class="fanfic-select fanfic-select-inline">
													<option value="yes"><?php esc_html_e( 'Yes', 'fanfiction-manager' ); ?></option>
													<option value="no"><?php esc_html_e( 'No', 'fanfiction-manager' ); ?></option>
												</select>
											</div>

											<div class="fanfic-cc-toggle-row">
												<label for="fanfic_cc_modifications">
													<?php esc_html_e( 'Allow modifications?', 'fanfiction-manager' ); ?>
													<span class="fanfic-tooltip" title="<?php esc_attr_e( 'This includes translating, rewriting, making an audio version, or using it as a base.', 'fanfiction-manager' ); ?>">(?)</span>
												</label>
												<select id="fanfic_cc_modifications" class="fanfic-select fanfic-select-inline">
													<option value="yes"><?php esc_html_e( 'Yes', 'fanfiction-manager' ); ?></option>
													<option value="no"><?php esc_html_e( 'No', 'fanfiction-manager' ); ?></option>
												</select>
											</div>

											<div class="fanfic-cc-toggle-row" id="fanfic-cc-sharealike-row">
												<label for="fanfic_cc_sharealike">
													<?php esc_html_e( 'Require ShareAlike?', 'fanfiction-manager' ); ?>
													<span class="fanfic-tooltip" title="<?php esc_attr_e( 'If someone adapts your work, they must share it under the same rules.', 'fanfiction-manager' ); ?>">(?)</span>
												</label>
												<select id="fanfic_cc_sharealike" class="fanfic-select fanfic-select-inline">
													<option value="no"><?php esc_html_e( 'No', 'fanfiction-manager' ); ?></option>
													<option value="yes"><?php esc_html_e( 'Yes', 'fanfiction-manager' ); ?></option>
												</select>
											</div>

											<p class="description fanfic-cc-result">
												<?php esc_html_e( 'Selected licence:', 'fanfiction-manager' ); ?>
												<strong id="fanfic-cc-result-label">CC BY</strong>
											</p>
										</div>
									</div>

									<div class="fanfic-licence-detail<?php echo 'public-domain' === $story_licence_type ? ' is-active' : ''; ?>" data-licence-detail="public-domain">
										<h4><?php esc_html_e( 'Public Domain / CC0', 'fanfiction-manager' ); ?></h4>
										<p class="description fanfic-licence-explanation"><?php esc_html_e( 'Anyone can copy, remix, repost, translate, and even sell it without asking you first.', 'fanfiction-manager' ); ?></p>
										<p class="description fanfic-licence-warning"><?php esc_html_e( 'Only choose this if you truly do not care how it is used.', 'fanfiction-manager' ); ?></p>
									</div>
								</div>

								<input type="hidden" id="fanfic_story_licence" name="fanfic_story_licence" value="<?php echo esc_attr( $story_licence ); ?>">

								<p class="description fanfic-licence-footer"><?php esc_html_e( 'This controls rights over your original text. It doesn\'t give anyone rights over the original fandom/characters.', 'fanfiction-manager' ); ?></p>
								<p class="description fanfic-licence-hint"><?php esc_html_e( 'Not sure? Pick All Rights Reserved.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>

						<?php if ( $can_edit_publish_date ) : ?>
							<div class="fanfic-form-field">
								<label for="fanfic_story_publish_date"><?php esc_html_e( 'Publication Date', 'fanfiction-manager' ); ?></label>
								<input
									type="date"
									id="fanfic_story_publish_date"
									name="fanfic_story_publish_date"
									class="fanfic-input"
									max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
									value="<?php echo esc_attr( $story_publish_date ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Changing this date does not count as a update for inactivity status.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>

						<!-- Genres -->
						<div class="fanfic-form-field">
							<label><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?><?php fanfic_required_field_indicator(); ?></label>
							<div class="fanfic-checkboxes fanfic-checkboxes-grid">
								<?php
								$genres = get_terms( array(
									'taxonomy' => 'fanfiction_genre',
									'hide_empty' => false,
								) );

								foreach ( $genres as $genre ) {
									$is_checked = isset( $_POST['fanfic_story_genres'] ) ?
										in_array( $genre->term_id, (array) $_POST['fanfic_story_genres'] ) :
										( $is_edit_mode && in_array( $genre->term_id, $current_genres ) );
									?>
									<label class="fanfic-checkbox-label">
										<input
											type="checkbox"
											name="fanfic_story_genres[]"
											value="<?php echo esc_attr( $genre->term_id ); ?>"
											class="fanfic-checkbox"
											<?php checked( $is_checked ); ?>
										/>
										<?php echo esc_html( $genre->name ); ?>
									</label>
									<?php
								}
								?>
							</div>
						</div>

						<!-- Status -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?><?php fanfic_required_field_indicator(); ?></label>
							<select id="fanfic_story_status" name="fanfic_story_status" class="fanfic-select" required>
								<option value=""><?php esc_html_e( 'Select a status...', 'fanfiction-manager' ); ?></option>
								<?php
								$statuses = get_terms( array(
									'taxonomy' => 'fanfiction_status',
									'hide_empty' => false,
								) );

								foreach ( $statuses as $status ) {
									$posted_status = isset( $_POST['fanfic_story_status'] ) ? absint( $_POST['fanfic_story_status'] ) : 0;
									$is_selected = isset( $_POST['fanfic_story_status'] ) ?
										$posted_status === absint( $status->term_id ) :
										( $is_edit_mode ? absint( $current_status ) === absint( $status->term_id ) : $default_create_status === absint( $status->term_id ) );
									?>
									<option value="<?php echo esc_attr( $status->term_id ); ?>" <?php selected( $is_selected ); ?>>
										<?php echo esc_html( $status->name ); ?>
									</option>
									<?php
								}
								?>
							</select>
						</div>

						<?php if ( class_exists( 'Fanfic_Fandoms' ) && Fanfic_Fandoms::is_enabled() ) : ?>
							<!-- Original Work -->
							<div class="fanfic-form-field">
								<label><?php esc_html_e( 'Original Work', 'fanfiction-manager' ); ?></label>
								<label class="fanfic-checkbox-label">
									<input
										type="checkbox"
										name="fanfic_is_original_work"
										value="1"
										class="fanfic-checkbox"
										<?php checked( $is_original_work ); ?>
									/>
									<?php esc_html_e( 'This story is an original work', 'fanfiction-manager' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Original works do not use fandom tags.', 'fanfiction-manager' ); ?></p>
							</div>

							<!-- Fandoms -->
							<div class="fanfic-form-field fanfic-fandoms-field" data-max-fandoms="<?php echo esc_attr( Fanfic_Fandoms::MAX_FANDOMS ); ?>">
								<label for="fanfic_fandom_search"><?php esc_html_e( 'Fandoms', 'fanfiction-manager' ); ?></label>
								<div class="fanfic-pill-input-wrapper">
									<div class="fanfic-selected-fandoms fanfic-pill-values" aria-live="polite">
										<?php foreach ( $current_fandom_labels as $fandom ) : ?>
											<span class="fanfic-pill-value" data-id="<?php echo esc_attr( $fandom['id'] ); ?>">
												<span class="fanfic-pill-value-text"><?php echo esc_html( $fandom['label'] ); ?></span>
												<button type="button" class="fanfic-pill-value-remove" aria-label="<?php esc_attr_e( 'Remove fandom', 'fanfiction-manager' ); ?>">&times;</button>
												<input type="hidden" name="fanfic_story_fandoms[]" value="<?php echo esc_attr( $fandom['id'] ); ?>">
											</span>
										<?php endforeach; ?>
									</div>
									<input
										type="text"
										id="fanfic_fandom_search"
										class="fanfic-input fanfic-pill-input"
										autocomplete="off"
										placeholder="<?php esc_attr_e( 'Search fandoms...', 'fanfiction-manager' ); ?>"
									/>
								</div>
								<div class="fanfic-fandom-results" role="listbox" aria-label="<?php esc_attr_e( 'Fandom search results', 'fanfiction-manager' ); ?>"></div>
								<p class="description"><?php esc_html_e( 'Select up to 5 fandoms. Search requires at least 2 characters.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>

						<?php if ( class_exists( 'Fanfic_Languages' ) && Fanfic_Languages::is_enabled() ) : ?>
							<?php
							// Get current language for edit mode, or default to WP site language for new stories
							$current_language_id = 0;
							if ( $is_edit_mode ) {
								$current_language_id = Fanfic_Languages::get_story_language_id( $story_id );
							} else {
								// Default to WordPress locale with explicit pt/es fallback priority.
								$wp_locale = strtolower( str_replace( '_', '-', (string) get_locale() ) );
								$wp_base   = strtok( $wp_locale, '-' );
								$candidates = array();

								if ( 'pt' === $wp_base ) {
									$candidates = ( 'pt-br' === $wp_locale )
										? array( 'pt-br', 'pt', 'en' )
										: array( 'pt', 'pt-br', 'en' );
								} elseif ( 'es' === $wp_base ) {
									$candidates = ( 'es-es' === $wp_locale || 'es' === $wp_locale )
										? array( 'es-es', 'es-419', 'en' )
										: array( 'es-419', 'es-es', 'en' );
								} else {
									$candidates = array( $wp_locale, $wp_base, 'en' );
								}

								$candidates = array_values( array_unique( array_filter( array_map( 'sanitize_title', $candidates ) ) ) );
								foreach ( $candidates as $candidate_slug ) {
									$wp_lang = Fanfic_Languages::get_by_slug( $candidate_slug );
									if ( $wp_lang && ! empty( $wp_lang['is_active'] ) ) {
										$current_language_id = (int) $wp_lang['id'];
										break;
									}
								}
							}
							// Check for POST data
							if ( isset( $_POST['fanfic_story_language'] ) ) {
								$current_language_id = absint( $_POST['fanfic_story_language'] );
							}
							$available_languages = Fanfic_Languages::get_active_languages();
							?>
							<!-- Language -->
							<div class="fanfic-form-field">
								<label for="fanfic_story_language"><?php esc_html_e( 'Language', 'fanfiction-manager' ); ?></label>
								<select id="fanfic_story_language" name="fanfic_story_language" class="fanfic-select">
									<option value=""><?php esc_html_e( 'Select a language...', 'fanfiction-manager' ); ?></option>
									<?php foreach ( $available_languages as $lang ) : ?>
										<?php
										$lang_label = esc_html( $lang['name'] );
										if ( ! empty( $lang['native_name'] ) && $lang['native_name'] !== $lang['name'] ) {
											$lang_label .= ' (' . esc_html( $lang['native_name'] ) . ')';
										}
										?>
										<option value="<?php echo esc_attr( $lang['id'] ); ?>" <?php selected( $current_language_id, $lang['id'] ); ?>>
											<?php echo $lang_label; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Select the language your story is written in.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>

						<?php if ( class_exists( 'Fanfic_Translations' ) && Fanfic_Translations::is_enabled() ) : ?>
							<?php
							$current_translation_siblings = $is_edit_mode ? Fanfic_Translations::get_translation_siblings( $story_id, false ) : array();
							?>
							<!-- Translation Links -->
							<div class="fanfic-form-field fanfic-translations-field" data-story-id="<?php echo esc_attr( $story_id ); ?>" data-story-language="<?php echo esc_attr( $current_language_id ); ?>">
								<label for="fanfic_translation_search"><?php esc_html_e( 'Linked Translations', 'fanfiction-manager' ); ?></label>
								<div class="fanfic-pill-input-wrapper">
									<div class="fanfic-selected-translations fanfic-pill-values" aria-live="polite">
										<?php foreach ( $current_translation_siblings as $sibling ) : ?>
											<span class="fanfic-pill-value" data-id="<?php echo esc_attr( $sibling['story_id'] ); ?>">
												<span class="fanfic-pill-value-text"><?php echo esc_html( $sibling['title'] . ' - ' . $sibling['language_label'] ); ?></span>
												<button type="button" class="fanfic-pill-value-remove" aria-label="<?php esc_attr_e( 'Remove translation link', 'fanfiction-manager' ); ?>">&times;</button>
												<input type="hidden" name="fanfic_story_translations[]" value="<?php echo esc_attr( $sibling['story_id'] ); ?>">
											</span>
										<?php endforeach; ?>
									</div>
									<input
										type="text"
										id="fanfic_translation_search"
										class="fanfic-input fanfic-pill-input"
										autocomplete="off"
										placeholder="<?php esc_attr_e( 'Search your stories to link as translation...', 'fanfiction-manager' ); ?>"
									/>
								</div>
								<div class="fanfic-translation-results" role="listbox" aria-label="<?php esc_attr_e( 'Translation search results', 'fanfiction-manager' ); ?>"></div>
								<p class="description"><?php esc_html_e( 'Link other stories you wrote in different languages as translations of this story. Genres, fandoms, and warnings will stay synchronized across linked translations. Changes here will automatically update the sister translated stories, and their changes will update this story as well.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>

						<?php if ( class_exists( 'Fanfic_Coauthors' ) && Fanfic_Coauthors::is_enabled() ) : ?>
							<div class="fanfic-form-field fanfic-coauthors-field" data-story-id="<?php echo esc_attr( $story_id ); ?>" data-max-coauthors="<?php echo esc_attr( Fanfic_Coauthors::MAX_COAUTHORS ); ?>">
								<label for="fanfic_coauthor_search"><?php esc_html_e( 'Co-Authors', 'fanfiction-manager' ); ?></label>
								<input
									type="text"
									id="fanfic_coauthor_search"
									class="fanfic-input"
									autocomplete="off"
									placeholder="<?php esc_attr_e( 'Search users to invite as co-authors...', 'fanfiction-manager' ); ?>"
								/>
								<div class="fanfic-coauthor-results" role="listbox" aria-label="<?php esc_attr_e( 'Co-author search results', 'fanfiction-manager' ); ?>"></div>
								<div class="fanfic-selected-coauthors fanfic-pill-values" aria-live="polite">
									<?php foreach ( $current_coauthors as $coauthor ) : ?>
										<?php
										$coauthor_id = isset( $coauthor['id'] ) ? absint( $coauthor['id'] ) : 0;
										$coauthor_name = isset( $coauthor['display_name'] ) ? (string) $coauthor['display_name'] : '';
										$coauthor_status = isset( $coauthor['status'] ) ? sanitize_key( $coauthor['status'] ) : '';
										if ( ! $coauthor_id || '' === $coauthor_name ) {
											continue;
										}
										?>
										<span class="fanfic-pill-value" data-id="<?php echo esc_attr( $coauthor_id ); ?>" data-status="<?php echo esc_attr( $coauthor_status ); ?>">
											<?php echo wp_kses_post( get_avatar( $coauthor_id, 20, '', $coauthor_name, array( 'class' => 'fanfic-coauthor-avatar', 'loading' => 'lazy' ) ) ); ?>
											<span class="fanfic-pill-value-text"><?php echo esc_html( $coauthor_name ); ?></span>
											<?php if ( 'pending' === $coauthor_status ) : ?>
												<span class="fanfic-badge is-muted" data-badge-type="status" data-badge-scope="coauthor-status" data-status="pending"><?php esc_html_e( 'Pending', 'fanfiction-manager' ); ?></span>
											<?php endif; ?>
											<button type="button" class="fanfic-pill-value-remove" aria-label="<?php esc_attr_e( 'Remove co-author', 'fanfiction-manager' ); ?>">&times;</button>
											<input type="hidden" name="fanfic_story_coauthors[]" value="<?php echo esc_attr( $coauthor_id ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<p class="description">
									<?php
									printf(
										/* translators: %d: max co-authors per story. */
										esc_html__( 'Invite up to %d co-authors. Accepted co-authors can edit story metadata and chapters. Pending users can preview the story until they accept or refuse.', 'fanfiction-manager' ),
										absint( Fanfic_Coauthors::MAX_COAUTHORS )
									);
									?>
								</p>
							</div>
						<?php endif; ?>

						<?php
						// ========================================================================
						// CUSTOM TAXONOMIES SECTION
						// ========================================================================
						if ( class_exists( 'Fanfic_Custom_Taxonomies' ) ) :
							$custom_taxonomies = Fanfic_Custom_Taxonomies::get_active_taxonomies();
							foreach ( $custom_taxonomies as $custom_taxonomy ) :
								$custom_terms = Fanfic_Custom_Taxonomies::get_active_terms( $custom_taxonomy['id'] );
								if ( empty( $custom_terms ) ) {
									continue;
								}

								// Get current values for edit mode
								$current_term_ids = array();
								if ( $is_edit_mode ) {
									$current_term_ids = Fanfic_Custom_Taxonomies::get_story_term_ids( $story_id, $custom_taxonomy['id'] );
								}
								// Check for POST data
								$post_key = 'fanfic_custom_' . $custom_taxonomy['slug'];
								if ( isset( $_POST[ $post_key ] ) ) {
									$current_term_ids = array_map( 'absint', (array) $_POST[ $post_key ] );
								}
								?>
								<?php $ct_display_format = isset( $custom_taxonomy['display_format'] ) ? $custom_taxonomy['display_format'] : 'grid'; ?>
								<div class="fanfic-form-field">
									<label for="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>"><?php echo esc_html( $custom_taxonomy['name'] ); ?></label>
									<?php if ( 'single' === $custom_taxonomy['selection_type'] ) : ?>
										<select id="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>" name="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>" class="fanfic-select">
											<option value=""><?php esc_html_e( 'Select...', 'fanfiction-manager' ); ?></option>
											<?php foreach ( $custom_terms as $term ) : ?>
												<option value="<?php echo esc_attr( $term['id'] ); ?>" <?php selected( in_array( (int) $term['id'], $current_term_ids, true ) ); ?>>
													<?php echo esc_html( $term['name'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php elseif ( 'dropdown' === $ct_display_format ) : ?>
										<!-- Multi-select dropdown -->
										<?php
										$ct_selected_names = array();
										foreach ( $custom_terms as $term ) {
											if ( in_array( (int) $term['id'], $current_term_ids, true ) ) {
												$ct_selected_names[] = $term['name'];
											}
										}
										$ct_placeholder = sprintf(
											/* translators: %s: taxonomy name */
											esc_attr__( 'Select %s...', 'fanfiction-manager' ),
											esc_attr( strtolower( $custom_taxonomy['name'] ) )
										);
										?>
										<div class="multi-select fanfic-custom-multiselect" data-placeholder="<?php echo esc_attr( $ct_placeholder ); ?>">
											<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
												<?php
												if ( ! empty( $ct_selected_names ) ) {
													if ( count( $ct_selected_names ) <= 2 ) {
														echo esc_html( implode( ', ', $ct_selected_names ) );
													} else {
														printf(
															/* translators: %d: number of items selected */
															esc_html__( '%d selected', 'fanfiction-manager' ),
															count( $ct_selected_names )
														);
													}
												} else {
													echo esc_html( $ct_placeholder );
												}
												?>
											</button>
											<div class="multi-select__dropdown">
												<?php foreach ( $custom_terms as $term ) : ?>
													<?php $is_checked = in_array( (int) $term['id'], $current_term_ids, true ); ?>
													<label>
														<input type="checkbox" name="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>[]" value="<?php echo esc_attr( $term['id'] ); ?>" <?php checked( $is_checked ); ?>>
														<span class="fanfic-custom-term-name"><?php echo esc_html( $term['name'] ); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									<?php elseif ( 'search' === $ct_display_format ) : ?>
										<!-- Searchable field -->
										<div class="fanfic-custom-search-field" data-taxonomy="<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>" data-taxonomy-id="<?php echo esc_attr( $custom_taxonomy['id'] ); ?>">
											<div class="fanfic-pill-input-wrapper">
												<div class="fanfic-selected-custom-terms fanfic-pill-values" aria-live="polite">
													<?php foreach ( $custom_terms as $term ) : ?>
														<?php if ( in_array( (int) $term['id'], $current_term_ids, true ) ) : ?>
															<span class="fanfic-pill-value" data-id="<?php echo esc_attr( $term['id'] ); ?>">
																<span class="fanfic-pill-value-text"><?php echo esc_html( $term['name'] ); ?></span>
																<button type="button" class="fanfic-pill-value-remove" aria-label="<?php esc_attr_e( 'Remove', 'fanfiction-manager' ); ?>">&times;</button>
																<input type="hidden" name="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>[]" value="<?php echo esc_attr( $term['id'] ); ?>">
															</span>
														<?php endif; ?>
													<?php endforeach; ?>
												</div>
												<input
													type="text"
													class="fanfic-input fanfic-pill-input fanfic-custom-search-input"
													autocomplete="off"
													placeholder="<?php printf( esc_attr__( 'Search %s...', 'fanfiction-manager' ), esc_attr( strtolower( $custom_taxonomy['name'] ) ) ); ?>"
												/>
											</div>
											<div class="fanfic-custom-search-results" role="listbox" aria-label="<?php printf( esc_attr__( '%s search results', 'fanfiction-manager' ), esc_attr( $custom_taxonomy['name'] ) ); ?>"></div>
											<p class="description"><?php esc_html_e( 'Type at least 2 characters to search.', 'fanfiction-manager' ); ?></p>
										</div>
									<?php else : ?>
										<!-- Grid (default for multi) -->
										<div class="fanfic-checkboxes fanfic-checkboxes-grid">
											<?php foreach ( $custom_terms as $term ) : ?>
												<?php $is_checked = in_array( (int) $term['id'], $current_term_ids, true ); ?>
												<label class="fanfic-checkbox-label">
													<input type="checkbox" name="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>[]" value="<?php echo esc_attr( $term['id'] ); ?>" class="fanfic-checkbox" <?php checked( $is_checked ); ?>>
													<?php echo esc_html( $term['name'] ); ?>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
								<?php
							endforeach;
						endif;
						?>

						<?php
						// ========================================================================
						// WARNINGS AND TAGS SECTION (Phase 4.1)
						// ========================================================================
						?>

						<!-- Content Warnings -->
						<?php
						$enable_warnings = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_warnings', true ) : true;
						$enable_tags     = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_tags', true ) : true;

						$available_warnings = array();
						if ( $enable_warnings && class_exists( 'Fanfic_Warnings' ) ) {
							$available_warnings = Fanfic_Warnings::get_available_warnings();
						}
						$current_warnings = array();
						if ( $enable_warnings && $is_edit_mode && class_exists( 'Fanfic_Warnings' ) ) {
							$story_warnings = Fanfic_Warnings::get_story_warnings( $story_id );
							$current_warnings = wp_list_pluck( $story_warnings, 'id' );
						}
						?>
						<?php if ( $enable_warnings && ! empty( $available_warnings ) ) : ?>
						<div class="fanfic-form-field">
							<label><?php esc_html_e( 'Content Warnings', 'fanfiction-manager' ); ?></label>
							<div class="multi-select fanfic-warnings-multiselect" data-placeholder="<?php esc_attr_e( 'Select warnings...', 'fanfiction-manager' ); ?>">
								<button type="button" class="multi-select__trigger" aria-haspopup="listbox">
									<?php esc_html_e( 'Select warnings...', 'fanfiction-manager' ); ?>
								</button>
								<div class="multi-select__dropdown">
									<?php foreach ( $available_warnings as $warning ) : ?>
										<?php
										$is_checked = isset( $_POST['fanfic_story_warnings'] ) ?
											in_array( $warning['id'], (array) $_POST['fanfic_story_warnings'] ) :
											( $is_edit_mode && in_array( $warning['id'], $current_warnings ) );
										$age_class = function_exists( 'fanfic_get_age_badge_class' ) ? fanfic_get_age_badge_class( $warning['min_age'], 'fanfic-warning-age-' ) : 'fanfic-warning-age-18-plus';
										$age_modifier_class = function_exists( 'fanfic_get_age_badge_class' ) ? fanfic_get_age_badge_class( $warning['min_age'] ) : 'is-age-18-plus';
										$age_label = function_exists( 'fanfic_get_age_display_label' ) ? fanfic_get_age_display_label( $warning['min_age'], false ) : (string) $warning['min_age'];
										if ( '' === $age_label ) {
											$age_label = (string) $warning['min_age'];
										}
										?>
										<label class="fanfic-warning-item <?php echo esc_attr( $age_class ); ?>" title="<?php echo esc_attr( $warning['description'] ); ?>">
											<input
												type="checkbox"
												name="fanfic_story_warnings[]"
												value="<?php echo esc_attr( $warning['id'] ); ?>"
												class="fanfic-checkbox"
												<?php checked( $is_checked ); ?>
											/>
											<span class="fanfic-warning-name"><?php echo esc_html( $warning['name'] ); ?></span>
											<span class="fanfic-badge fanfic-badge--age <?php echo esc_attr( $age_modifier_class ); ?>"><?php echo esc_html( $age_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'Select content warnings that apply to your story. Warnings affect the minimum age rating.', 'fanfiction-manager' ); ?></p>
						</div>
						<?php endif; ?>

						<!-- Visible Tags -->
						<?php
						$current_visible_tags = array();
						if ( $enable_tags && $is_edit_mode && function_exists( 'fanfic_get_visible_tags' ) ) {
							$current_visible_tags = fanfic_get_visible_tags( $story_id );
						}
						$visible_tags_value = isset( $_POST['fanfic_visible_tags'] ) ? sanitize_text_field( $_POST['fanfic_visible_tags'] ) : implode( ', ', $current_visible_tags );
						?>
						<?php if ( $enable_tags ) : ?>
							<div class="fanfic-form-field">
								<label for="fanfic_visible_tags_input"><?php esc_html_e( 'Visible Tags', 'fanfiction-manager' ); ?></label>
								<div class="fanfic-tags-input-wrapper">
									<div class="fanfic-pill-values fanfic-tags-pills" data-target="fanfic_visible_tags" aria-live="polite"></div>
									<input
										type="text"
										id="fanfic_visible_tags_input"
										class="fanfic-tags-typing"
										placeholder="<?php esc_attr_e( 'Type a tag and press Enter or comma...', 'fanfiction-manager' ); ?>"
										data-max-tags="<?php echo esc_attr( defined( 'FANFIC_MAX_VISIBLE_TAGS' ) ? FANFIC_MAX_VISIBLE_TAGS : 5 ); ?>"
										data-target="fanfic_visible_tags"
									/>
								</div>
								<input
									type="hidden"
									id="fanfic_visible_tags"
									name="fanfic_visible_tags"
									value="<?php echo esc_attr( $visible_tags_value ); ?>"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: %d: Maximum number of visible tags */
										esc_html__( 'Add up to %d tags to further help classify your story. Press Enter or comma to add each tag.', 'fanfiction-manager' ),
										defined( 'FANFIC_MAX_VISIBLE_TAGS' ) ? FANFIC_MAX_VISIBLE_TAGS : 5
									);
									?>
								</p>
							</div>
						<?php endif; ?>

						<!-- Invisible Tags (for search only) -->
						<?php
						$current_invisible_tags = array();
						if ( $enable_tags && $is_edit_mode && function_exists( 'fanfic_get_invisible_tags' ) ) {
							$current_invisible_tags = fanfic_get_invisible_tags( $story_id );
						}
						$invisible_tags_value = isset( $_POST['fanfic_invisible_tags'] ) ? sanitize_text_field( $_POST['fanfic_invisible_tags'] ) : implode( ', ', $current_invisible_tags );
						?>
						<?php if ( $enable_tags ) : ?>
							<div class="fanfic-form-field">
								<label for="fanfic_invisible_tags_input"><?php esc_html_e( 'Search Tags (Hidden)', 'fanfiction-manager' ); ?></label>
								<div class="fanfic-tags-input-wrapper">
									<div class="fanfic-pill-values fanfic-tags-pills" data-target="fanfic_invisible_tags" aria-live="polite"></div>
									<input
										type="text"
										id="fanfic_invisible_tags_input"
										class="fanfic-tags-typing"
										placeholder="<?php esc_attr_e( 'Type a tag and press Enter or comma...', 'fanfiction-manager' ); ?>"
										data-max-tags="<?php echo esc_attr( defined( 'FANFIC_MAX_INVISIBLE_TAGS' ) ? FANFIC_MAX_INVISIBLE_TAGS : 10 ); ?>"
										data-target="fanfic_invisible_tags"
									/>
								</div>
								<input
									type="hidden"
									id="fanfic_invisible_tags"
									name="fanfic_invisible_tags"
									value="<?php echo esc_attr( $invisible_tags_value ); ?>"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: %d: Maximum number of invisible tags */
										esc_html__( 'Add up to %d search tags. These will not be visible, but they will help your story appear in search results when someone looks for them. Press Enter or a comma to add each tag.', 'fanfiction-manager' ),
										defined( 'FANFIC_MAX_INVISIBLE_TAGS' ) ? FANFIC_MAX_INVISIBLE_TAGS : 10
									);
									?>
								</p>
							</div>
						<?php endif; ?>

						<!-- Featured Image -->
						<div class="fanfic-form-field fanfic-has-dropzone">
							<label for="fanfic_story_image"><?php esc_html_e( 'Featured Image', 'fanfiction-manager' ); ?></label>

							<!-- Dropzone for WordPress Media Library -->
							<div
								id="fanfic_story_image_dropzone"
								class="fanfic-image-dropzone"
								data-target="#fanfic_story_image"
								data-title="<?php esc_attr_e( 'Select Story Cover Image', 'fanfiction-manager' ); ?>"
								role="button"
								tabindex="0"
								aria-label="<?php esc_attr_e( 'Click or drag to upload story cover image', 'fanfiction-manager' ); ?>"
							>
								<div class="fanfic-dropzone-placeholder">
									<span class="fanfic-dropzone-placeholder-icon dashicons dashicons-cloud-upload" aria-hidden="true"></span>
									<span class="fanfic-dropzone-placeholder-text"><?php esc_html_e( 'Click to select image', 'fanfiction-manager' ); ?></span>
									<span class="fanfic-dropzone-placeholder-hint"><?php esc_html_e( 'or drag and drop here', 'fanfiction-manager' ); ?></span>
								</div>
							</div>

							<!-- Hidden URL input (populated by dropzone) -->
							<input
								type="url"
								id="fanfic_story_image"
								name="fanfic_story_image"
								class="fanfic-input"
								value="<?php echo isset( $_POST['fanfic_story_image'] ) ? esc_attr( $_POST['fanfic_story_image'] ) : ( $is_edit_mode ? esc_attr( $featured_image ) : '' ); ?>"
								placeholder="<?php esc_attr_e( 'Image URL will appear here', 'fanfiction-manager' ); ?>"
								aria-label="<?php esc_attr_e( 'Story cover image URL', 'fanfiction-manager' ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Select an image from your media library or enter a URL directly.', 'fanfiction-manager' ); ?>
							</p>
						</div>

						<!-- Enable Comments -->
						<?php
						$comments_enabled = $is_edit_mode ? get_post_meta( $story_id, '_fanfic_comments_enabled', true ) : '1';
						if ( '' === $comments_enabled ) {
							$comments_enabled = '1';
						}
						if ( isset( $_POST['fanfic_comments_enabled'] ) ) {
							$comments_enabled = '1';
						} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
							$comments_enabled = '0';
						}
						$global_comments = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_comments', true ) : true;
						?>
						<div class="fanfic-form-field fanfic-comment-option">
							<div class="fanfic-comment-toggle-row">
								<label for="fanfic_comments_enabled" class="fanfic-comment-toggle-label">
									<?php esc_html_e( 'Comments', 'fanfiction-manager' ); ?>
								</label>
								<label class="fanfic-switch fanfic-comment-switch<?php echo ! $global_comments ? ' is-disabled' : ''; ?>">
									<input
										type="checkbox"
										id="fanfic_comments_enabled"
										name="fanfic_comments_enabled"
										value="1"
										<?php checked( '1', $comments_enabled ); ?>
										<?php disabled( ! $global_comments ); ?>
									/>
									<span class="fanfic-slider round" aria-hidden="true">
										<span class="fanfic-slider-state fanfic-slider-state-off"><?php esc_html_e( 'Off', 'fanfiction-manager' ); ?></span>
										<span class="fanfic-slider-state fanfic-slider-state-on"><?php esc_html_e( 'On', 'fanfiction-manager' ); ?></span>
									</span>
								</label>
							</div>
							<?php if ( ! $global_comments ) : ?>
								<input type="hidden" name="fanfic_comments_enabled" value="<?php echo esc_attr( $comments_enabled ); ?>" />
								<p class="description"><?php esc_html_e( 'Comments are disabled globally by the site administrator.', 'fanfiction-manager' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Allow comments on this story and its chapters. You can disable comments on individual chapters.', 'fanfiction-manager' ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Hidden fields -->
					<?php if ( $is_edit_mode ) : ?>
						<input type="hidden" name="fanfic_story_id" value="<?php echo esc_attr( $story_id ); ?>" />
					<?php endif; ?>
					<input type="hidden" name="fanfic_story_form_mode" value="<?php echo esc_attr( $form_mode ); ?>" />

					<!-- Form Actions -->
					<div class="fanfic-form-actions">
						<?php if ( ! $is_edit_mode ) : ?>
							<!-- CREATE MODE -->
							<button type="submit" name="fanfic_form_action" value="add_chapter" class="fanfic-button">
								<?php echo fanfic_get_button_content_markup( __( 'Add Chapter', 'fanfiction-manager' ), 'dashicons-plus-alt' ); ?>
							</button>
							<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-button secondary">
								<?php echo fanfic_get_button_content_markup( __( 'Save as Hidden', 'fanfiction-manager' ), 'dashicons-hidden' ); ?>
							</button>
							<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button secondary">
								<?php echo fanfic_get_button_content_markup( __( 'Cancel', 'fanfiction-manager' ), 'dashicons-no-alt' ); ?>
							</a>
						<?php else : ?>
							<!-- EDIT MODE -->
							<?php
							$current_post_status    = get_post_status( $story_id );
							$is_published           = 'publish' === $current_post_status;
							$has_published_chapters = ! empty( get_posts( array(
								'post_type'      => 'fanfiction_chapter',
								'post_parent'    => $story_id,
								'post_status'    => 'publish',
								'posts_per_page' => 1,
								'fields'         => 'ids',
							) ) );
							?>

							<!-- Update: saves changes only, disabled until form is dirty -->
							<button type="submit" name="fanfic_form_action" value="update" class="fanfic-button" id="update-button" disabled>
								<?php echo fanfic_get_button_content_markup( __( 'Update', 'fanfiction-manager' ), 'dashicons-yes-alt' ); ?>
							</button>

							<!-- Visibility toggle -->
							<?php if ( $is_published ) : ?>
								<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-button secondary" id="hide-story-button">
									<?php echo fanfic_get_button_content_markup( __( 'Hide Story', 'fanfiction-manager' ), 'dashicons-hidden' ); ?>
								</button>
							<?php else : ?>
								<button type="submit" name="fanfic_form_action" value="publish" class="fanfic-button secondary" id="make-visible-button">
									<?php echo fanfic_get_button_content_markup( __( 'Make Visible', 'fanfiction-manager' ), 'dashicons-visibility' ); ?>
								</button>
							<?php endif; ?>

							<!-- View link: only when story is visible -->
							<?php if ( $is_published ) : ?>
								<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" class="fanfic-button secondary" target="_blank" rel="noopener noreferrer" data-fanfic-story-view="1">
									<?php echo fanfic_get_button_content_markup( __( 'View', 'fanfiction-manager' ), 'dashicons-external' ); ?>
								</a>
							<?php endif; ?>

							<!-- Cancel: always present -->
							<button type="button" class="fanfic-button secondary" id="story-back-button">
								<?php echo fanfic_get_button_content_markup( __( 'Reset', 'fanfiction-manager' ), 'dashicons-update' ); ?>
							</button>

							<!-- Delete: if user has permission -->
							<?php if ( current_user_can( 'delete_fanfiction_story', $story_id ) ) : ?>
								<button type="button" id="delete-story-button" class="fanfic-button danger" data-story-id="<?php echo absint( $story_id ); ?>" data-story-title="<?php echo esc_attr( $story_title ); ?>">
									<?php echo fanfic_get_button_content_markup( __( 'Delete', 'fanfiction-manager' ), 'dashicons-trash' ); ?>
								</button>
							<?php endif; ?>

							<!-- Warning: shown when story is hidden and has no published chapters -->
							<?php if ( ! $is_published && ! $has_published_chapters ) : ?>
								<div class="fanfic-message fanfic-message-warning">
									<span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
									<span class="fanfic-message-content"><?php esc_html_e( 'To make the story visible, you need to make at least one chapter visible.', 'fanfiction-manager' ); ?></span>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<?php echo $fanfic_story_messages_markup; ?>
				</form>
			</div>
		</section>
	</div><!-- /.fanfic-content-primary -->

	<!-- Help Sidebar (Create mode only) -->
	<?php if ( ! $is_edit_mode ) : ?>
	<?php
	$help_genres = array();
	$genre_terms = get_terms(
		array(
			'taxonomy'   => 'fanfiction_genre',
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $genre_terms ) && ! empty( $genre_terms ) ) {
		foreach ( $genre_terms as $genre_term ) {
			$name = trim( (string) $genre_term->name );
			if ( '' === $name ) {
				continue;
			}

			$help_genres[] = array(
				'name'        => $name,
				'description' => trim( wp_strip_all_tags( (string) $genre_term->description ) ),
			);
		}
	}

	$help_warnings = array();
	$warnings_enabled_for_help = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_warnings', true ) : true;
	if ( $warnings_enabled_for_help && class_exists( 'Fanfic_Warnings' ) ) {
		$available_help_warnings = Fanfic_Warnings::get_available_warnings();
		foreach ( (array) $available_help_warnings as $warning ) {
			$name = isset( $warning['name'] ) ? trim( (string) $warning['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$help_warnings[] = array(
				'name'        => $name,
				'description' => isset( $warning['description'] ) ? trim( wp_strip_all_tags( (string) $warning['description'] ) ) : '',
			);
		}
	}
	?>
	<aside class="fanfic-content-sidebar" aria-labelledby="help-heading">
		<h2 id="help-heading"><?php esc_html_e( 'Tips & Guidelines', 'fanfiction-manager' ); ?></h2>

		<!-- Tips for Good Story Titles -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="title-tips-heading">
			<h3 id="title-tips-heading">
				<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
				<?php esc_html_e( 'Tips for Good Story Titles', 'fanfiction-manager' ); ?>
			</h3>
			<ul class="fanfic-help-list">
				<li><?php esc_html_e( 'Make it memorable and unique', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Keep it concise (50-100 characters)', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Avoid generic titles like "Untitled"', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Consider including keywords from your story', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Tips for Writing Good Introductions -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="intro-tips-heading">
			<h3 id="intro-tips-heading">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Writing Good Story Descriptions', 'fanfiction-manager' ); ?>
			</h3>
			<ul class="fanfic-help-list">
				<li><?php esc_html_e( 'Hook readers with the first sentence', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Summarize the main plot without spoilers', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Mention key themes or genres', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'Keep it between 100-300 words', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'If a non-finished story has no qualifying updates for 4 months, it is automatically marked as On Hiatus.', 'fanfiction-manager' ); ?></li>
				<li><?php esc_html_e( 'If a story has no qualifying updates for 10 months, it is automatically marked as Abandoned.', 'fanfiction-manager' ); ?></li>
			</ul>
		</section>

		<!-- Genre Information -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="genre-tips-heading">
			<h3 id="genre-tips-heading">
				<span class="dashicons dashicons-category" aria-hidden="true"></span>
				<?php esc_html_e( 'Understanding Genres', 'fanfiction-manager' ); ?>
			</h3>
			<?php if ( ! empty( $help_genres ) ) : ?>
				<ul class="fanfic-help-list">
					<?php foreach ( $help_genres as $help_genre ) : ?>
						<li>
							<strong><?php echo esc_html( $help_genre['name'] ); ?>:</strong>
							<?php echo '' !== $help_genre['description'] ? esc_html( $help_genre['description'] ) : esc_html__( 'No description provided yet.', 'fanfiction-manager' ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="fanfic-help-text"><?php esc_html_e( 'No active genres are currently available.', 'fanfiction-manager' ); ?></p>
			<?php endif; ?>
		</section>

		<!-- Warning Information -->
		<section class="fanfic-content-section" class="fanfic-help-widget" aria-labelledby="warning-tips-heading">
			<h3 id="warning-tips-heading">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php esc_html_e( 'Available Content Warnings', 'fanfiction-manager' ); ?>
			</h3>
			<?php if ( ! empty( $help_warnings ) ) : ?>
				<ul class="fanfic-help-list">
					<?php foreach ( $help_warnings as $help_warning ) : ?>
						<li>
							<strong><?php echo esc_html( $help_warning['name'] ); ?>:</strong>
							<?php echo '' !== $help_warning['description'] ? esc_html( $help_warning['description'] ) : esc_html__( 'No description provided yet.', 'fanfiction-manager' ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="fanfic-help-text"><?php esc_html_e( 'No active warnings are currently available.', 'fanfiction-manager' ); ?></p>
			<?php endif; ?>
		</section>

		<!-- Back to Dashboard Link -->
		<div class="fanfic-help-footer">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button-link">
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</div>
	</aside>
	<?php endif; ?>
	</div><!-- /.fanfic-content-layout -->

	<!-- Chapters Management Section (Edit mode only) — outside the column layout -->
	<?php if ( $is_edit_mode ) : ?>
	<?php $add_chapter_url = fanfic_get_edit_chapter_url( 0, $story_id ); ?>
	<section class="fanfic-content-section fanfic-chapters-section" aria-labelledby="chapters-heading">
		<div class="fanfic-section-header">
			<h2 id="chapters-heading"><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
			<?php if ( '' !== $add_chapter_url ) : ?>
				<a href="<?php echo esc_url( $add_chapter_url ); ?>" class="fanfic-button fanfic-edit-button fanfic-add-chapter-button">
					<?php echo fanfic_get_button_content_markup( __( 'Add Chapter', 'fanfiction-manager' ), 'dashicons-plus-alt' ); ?>
				</a>
			<?php endif; ?>
		</div>

	<!-- Chapters List -->
	<div class="fanfic-chapters-list">
		<?php
		// Get all chapters for this story
		$chapters_args = array(
			'post_type'      => 'fanfiction_chapter',
			'post_parent'    => $story_id,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
		);

		$chapters_query = new WP_Query( $chapters_args );
		$chapters = $chapters_query->posts;

		// Sort chapters by chapter number
		if ( ! empty( $chapters ) ) {
			usort( $chapters, function( $a, $b ) {
				$number_a = get_post_meta( $a->ID, '_fanfic_chapter_number', true );
				$number_b = get_post_meta( $b->ID, '_fanfic_chapter_number', true );

				// Convert to integers for proper comparison
				$number_a = absint( $number_a );
				$number_b = absint( $number_b );

				// Prologue (0) comes first, then regular chapters (1-999), then epilogue (1000+)
				return $number_a - $number_b;
			} );
		}

		if ( ! empty( $chapters ) ) :
			?>
			<table class="fanfic-table" role="table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Chapter #', 'fanfiction-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'fanfiction-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Visibility', 'fanfiction-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Word Count', 'fanfiction-manager' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'fanfiction-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $chapters as $chapter ) :
						$chapter_id = $chapter->ID;
						$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
						$stored_chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );

						// Display chapter label based on type
						if ( 'prologue' === $chapter_type ) {
							$display_number = __( 'Prologue', 'fanfiction-manager' );
						} elseif ( 'epilogue' === $chapter_type ) {
							$display_number = __( 'Epilogue', 'fanfiction-manager' );
						} else {
							$display_number = sprintf( __( 'Chapter %s', 'fanfiction-manager' ), $stored_chapter_number );
						}

						// Get word count
						$content = $chapter->post_content;
						$word_count = str_word_count( wp_strip_all_tags( $content ) );

						// Get status
						$status = $chapter->post_status;
						$is_chapter_blocked = fanfic_is_chapter_blocked( $chapter_id );
						$can_moderate_chapter = function_exists( 'fanfic_current_user_can_use_moderation_controls' ) && fanfic_current_user_can_use_moderation_controls();
						$chapter_block_endpoint = admin_url( 'admin-post.php' );
						$status_labels = array(
							'publish' => __( 'Visible', 'fanfiction-manager' ),
							'draft'   => __( 'Hidden', 'fanfiction-manager' ),
							'pending' => __( 'Pending', 'fanfiction-manager' ),
						);
						$status_label = $is_chapter_blocked ? __( 'Blocked', 'fanfiction-manager' ) : ( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status );
						$status_class = $is_chapter_blocked ? 'blocked' : $status;
						$status_tone_class = function_exists( 'fanfic_get_badge_tone_for_status' ) ? fanfic_get_badge_tone_for_status( $status_class ) : 'is-muted';
						?>
						<tr class="fanfic-chapter-row" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-post-status="<?php echo esc_attr( $status ); ?>">
							<td data-label="<?php esc_attr_e( 'Chapter #', 'fanfiction-manager' ); ?>">
								<strong><?php echo esc_html( $display_number ); ?></strong>
							</td>
							<td data-label="<?php esc_attr_e( 'Title', 'fanfiction-manager' ); ?>">
								<?php echo esc_html( $chapter->post_title ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Visibility', 'fanfiction-manager' ); ?>">
								<span class="fanfic-badge fanfic-badge--status <?php echo esc_attr( $status_tone_class ); ?> fanfic-status-<?php echo esc_attr( $status_class ); ?>" data-badge-type="status" data-badge-scope="chapter-row-status" data-status="<?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Word Count', 'fanfiction-manager' ); ?>">
								<?php echo esc_html( number_format_i18n( $word_count ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Actions', 'fanfiction-manager' ); ?>">
								<div class="fanfic-actions-buttons">
									<?php if ( $can_moderate_chapter ) : ?>
										<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( $chapter_id ) ); ?>" class="fanfic-button small fanfic-edit-button" aria-label="<?php esc_attr_e( 'Edit chapter', 'fanfiction-manager' ); ?>">
											<?php echo fanfic_get_button_content_markup( __( 'Edit', 'fanfiction-manager' ), 'dashicons-edit' ); ?>
										</a>
										<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button small" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'View chapter', 'fanfiction-manager' ); ?>">
											<?php echo fanfic_get_button_content_markup( __( 'View', 'fanfiction-manager' ), 'dashicons-external' ); ?>
										</a>
										<?php if ( 'publish' === $status ) : ?>
											<button type="button" class="fanfic-button small fanfic-button-warning fanfic-hide-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" data-story-id="<?php echo absint( $story_id ); ?>" aria-label="<?php esc_attr_e( 'Hide chapter', 'fanfiction-manager' ); ?>">
												<?php echo fanfic_get_button_content_markup( __( 'Hide', 'fanfiction-manager' ), 'dashicons-hidden' ); ?>
											</button>
										<?php else : ?>
											<button type="button" class="fanfic-button small fanfic-button-primary fanfic-publish-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Make chapter visible', 'fanfiction-manager' ); ?>">
												<?php echo fanfic_get_button_content_markup( __( 'Make Visible', 'fanfiction-manager' ), 'dashicons-visibility' ); ?>
											</button>
										<?php endif; ?>
										<?php if ( $is_chapter_blocked ) : ?>
											<form method="post" action="<?php echo esc_url( $chapter_block_endpoint ); ?>" class="fanfic-inline-block-toggle fanfic-chapter-block-toggle fanfic-chapter-block-toggle--unblock">
												<input type="hidden" name="action" value="fanfic_toggle_chapter_block">
												<input type="hidden" name="chapter_id" value="<?php echo esc_attr( $chapter_id ); ?>">
												<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'fanfic_toggle_chapter_block_' . $chapter_id ) ); ?>">
												<button type="submit" class="fanfic-button small fanfic-button-primary" aria-label="<?php esc_attr_e( 'Unblock chapter', 'fanfiction-manager' ); ?>">
													<?php echo fanfic_get_button_content_markup( __( 'Unblock', 'fanfiction-manager' ), 'dashicons-unlock' ); ?>
												</button>
											</form>
										<?php else : ?>
											<button
												type="button"
												class="fanfic-button small fanfic-button-warning fanfic-open-block-modal fanfic-inline-block-toggle fanfic-chapter-block-toggle"
												data-fanfic-block-target-type="chapter"
												data-fanfic-block-target-id="<?php echo absint( $chapter_id ); ?>"
												data-fanfic-block-target-input="chapter_id"
												data-fanfic-block-target-action="fanfic_toggle_chapter_block"
												data-fanfic-block-nonce="<?php echo esc_attr( wp_create_nonce( 'fanfic_toggle_chapter_block_' . $chapter_id ) ); ?>"
												data-fanfic-block-title="<?php echo esc_attr__( 'Block Chapter', 'fanfiction-manager' ); ?>"
												data-fanfic-block-warning="<?php echo esc_attr__( 'Choose a reason and optional details for the author before blocking this chapter.', 'fanfiction-manager' ); ?>"
												data-fanfic-block-submit-label="<?php echo esc_attr__( 'Block Chapter', 'fanfiction-manager' ); ?>"
												aria-label="<?php esc_attr_e( 'Block chapter', 'fanfiction-manager' ); ?>">
												<?php echo fanfic_get_button_content_markup( __( 'Block', 'fanfiction-manager' ), 'dashicons-lock' ); ?>
											</button>
										<?php endif; ?>
										<button type="button" class="fanfic-button small danger fanfic-delete-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
											<?php echo fanfic_get_button_content_markup( __( 'Delete', 'fanfiction-manager' ), 'dashicons-trash' ); ?>
										</button>
									<?php elseif ( ! $is_chapter_blocked ) : ?>
										<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( $chapter_id ) ); ?>" class="fanfic-button small fanfic-edit-button" aria-label="<?php esc_attr_e( 'Edit chapter', 'fanfiction-manager' ); ?>">
											<?php echo fanfic_get_button_content_markup( __( 'Edit', 'fanfiction-manager' ), 'dashicons-edit' ); ?>
										</a>
										<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button small" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'View chapter', 'fanfiction-manager' ); ?>">
											<?php echo fanfic_get_button_content_markup( __( 'View', 'fanfiction-manager' ), 'dashicons-external' ); ?>
										</a>
										<?php if ( 'publish' === $status ) : ?>
											<button type="button" class="fanfic-button small fanfic-button-warning fanfic-hide-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" data-story-id="<?php echo absint( $story_id ); ?>" aria-label="<?php esc_attr_e( 'Hide chapter', 'fanfiction-manager' ); ?>">
												<?php echo fanfic_get_button_content_markup( __( 'Hide', 'fanfiction-manager' ), 'dashicons-hidden' ); ?>
											</button>
										<?php else : ?>
											<button type="button" class="fanfic-button small fanfic-button-primary fanfic-publish-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Make chapter visible', 'fanfiction-manager' ); ?>">
												<?php echo fanfic_get_button_content_markup( __( 'Make Visible', 'fanfiction-manager' ), 'dashicons-visibility' ); ?>
											</button>
										<?php endif; ?>
										<button type="button" class="fanfic-button small danger fanfic-delete-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
											<?php echo fanfic_get_button_content_markup( __( 'Delete', 'fanfiction-manager' ), 'dashicons-trash' ); ?>
										</button>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			wp_reset_postdata();
		else :
			?>
			<div class="fanfic-empty-state" role="status">
				<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
				<p><?php esc_html_e( 'No chapters yet. Add your first chapter to get started!', 'fanfiction-manager' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<!-- Breadcrumb Navigation (Bottom) -->
<?php
fanfic_render_breadcrumb( 'edit-story', array(
	'story_id'     => $story_id,
	'story_title'  => $story_title,
	'is_edit_mode' => $is_edit_mode,
	'position'     => 'bottom',
) );
?>

<!-- Inline Script for Message Dismissal and Delete Actions -->
<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		// === Licence toggle logic ===
		(function() {
			var licenceTypeSelect = document.getElementById('fanfic_story_licence_type');
			var licenceDetails = document.querySelectorAll('[data-licence-detail]');
			var ccToggles = document.getElementById('fanfic-cc-toggles');
			var hiddenField = document.getElementById('fanfic_story_licence');
			var ccCommercial = document.getElementById('fanfic_cc_commercial');
			var ccModifications = document.getElementById('fanfic_cc_modifications');
			var ccSharealike = document.getElementById('fanfic_cc_sharealike');
			var sharealikeRow = document.getElementById('fanfic-cc-sharealike-row');
			var resultLabel = document.getElementById('fanfic-cc-result-label');

			if (!licenceTypeSelect || !hiddenField) return;

			var ccLabelMap = {
				'cc-by': 'CC BY',
				'cc-by-sa': 'CC BY-SA',
				'cc-by-nc': 'CC BY-NC',
				'cc-by-nc-sa': 'CC BY-NC-SA',
				'cc-by-nd': 'CC BY-ND',
				'cc-by-nc-nd': 'CC BY-NC-ND'
			};
			var ccSlugMap = {
				'yes|yes|no': 'cc-by',
				'yes|yes|yes': 'cc-by-sa',
				'no|yes|no': 'cc-by-nc',
				'no|yes|yes': 'cc-by-nc-sa',
				'yes|no|no': 'cc-by-nd',
				'no|no|no': 'cc-by-nc-nd'
			};

			function getSelectedType() {
				return licenceTypeSelect.value || 'all-rights-reserved';
			}

			function setActiveDetail(type) {
				licenceDetails.forEach(function(detail) {
					var isActive = detail.getAttribute('data-licence-detail') === type;
					detail.classList.toggle('is-active', isActive);
				});
			}

			function resolveCCSlug() {
				var commercial = ccCommercial && ccCommercial.value === 'yes';
				var modifications = ccModifications && ccModifications.value === 'yes';
				var sharealike = ccSharealike && ccSharealike.value === 'yes';

				if (sharealikeRow) {
					sharealikeRow.style.display = modifications ? '' : 'none';
				}
				if (!modifications) sharealike = false;

				var slug = ccSlugMap[[commercial ? 'yes' : 'no', modifications ? 'yes' : 'no', sharealike ? 'yes' : 'no'].join('|')];
				if (!slug) {
					slug = commercial ? 'cc-by' : 'cc-by-nc';
				}

				hiddenField.value = slug;
				if (resultLabel) {
					resultLabel.textContent = ccLabelMap[slug] || slug;
				}
			}

			function updateLicenceUI() {
				var type = getSelectedType();
				setActiveDetail(type);

				if (type === 'all-rights-reserved') {
					hiddenField.value = 'all-rights-reserved';
					if (ccToggles) {
						ccToggles.style.display = 'none';
					}
				} else if (type === 'public-domain') {
					hiddenField.value = 'public-domain';
					if (ccToggles) {
						ccToggles.style.display = 'none';
					}
				} else if (type === 'creative-commons') {
					if (ccToggles) {
						ccToggles.style.display = 'block';
					}
					resolveCCSlug();
				}
			}

			// Reverse-map existing slug to toggle states on page load
			function initFromSlug() {
				var slug = hiddenField.value;
				if (slug === 'public-domain') {
					licenceTypeSelect.value = 'public-domain';
					return;
				}

				if (!slug || slug === 'all-rights-reserved') {
					licenceTypeSelect.value = 'all-rights-reserved';
					return;
				}

				// It's a CC slug — parse components
				var hasNC = slug.indexOf('-nc') !== -1;
				var hasND = slug.indexOf('-nd') !== -1;
				var hasSA = slug.indexOf('-sa') !== -1;
				licenceTypeSelect.value = 'creative-commons';

				if (ccCommercial) ccCommercial.value = hasNC ? 'no' : 'yes';
				if (ccModifications) ccModifications.value = hasND ? 'no' : 'yes';
				if (ccSharealike) ccSharealike.value = hasSA ? 'yes' : 'no';

				resolveCCSlug();
			}

			licenceTypeSelect.addEventListener('change', updateLicenceUI);
			if (ccCommercial) ccCommercial.addEventListener('change', resolveCCSlug);
			if (ccModifications) ccModifications.addEventListener('change', resolveCCSlug);
			if (ccSharealike) ccSharealike.addEventListener('change', resolveCCSlug);

			initFromSlug();
			updateLicenceUI();
		})();

		// Initialize multi-select dropdowns (warnings)
		document.querySelectorAll('.fanfic-warnings-multiselect').forEach(function(select) {
			var trigger = select.querySelector('.multi-select__trigger');
			var checkboxes = select.querySelectorAll('input[type="checkbox"]');
			var placeholder = select.dataset.placeholder || 'Select';

			function updateLabel() {
				var checked = Array.from(checkboxes).filter(function(cb) { return cb.checked; });
				if (checked.length === 0) {
					trigger.textContent = placeholder;
				} else if (checked.length <= 2) {
					trigger.textContent = checked.map(function(cb) {
						var nameEl = cb.parentNode.querySelector('.fanfic-warning-name');
						return nameEl ? nameEl.textContent.trim() : '';
					}).join(', ');
				} else {
					trigger.textContent = checked.length + ' <?php echo esc_js( __( 'warnings selected', 'fanfiction-manager' ) ); ?>';
				}
			}

			updateLabel();

			trigger.addEventListener('click', function(e) {
				e.stopPropagation();
				select.classList.toggle('open');
			});

			checkboxes.forEach(function(cb) {
				cb.addEventListener('change', updateLabel);
			});
		});

		// Initialize custom taxonomy multi-select dropdowns
		document.querySelectorAll('.fanfic-custom-multiselect').forEach(function(select) {
			var trigger = select.querySelector('.multi-select__trigger');
			var checkboxes = select.querySelectorAll('input[type="checkbox"]');
			var placeholder = select.dataset.placeholder || 'Select';

			function updateLabel() {
				var checked = Array.from(checkboxes).filter(function(cb) { return cb.checked; });
				if (checked.length === 0) {
					trigger.textContent = placeholder;
				} else if (checked.length <= 2) {
					trigger.textContent = checked.map(function(cb) {
						var nameEl = cb.parentNode.querySelector('.fanfic-custom-term-name');
						return nameEl ? nameEl.textContent.trim() : '';
					}).join(', ');
				} else {
					trigger.textContent = checked.length + ' <?php echo esc_js( __( 'selected', 'fanfiction-manager' ) ); ?>';
				}
			}

			updateLabel();

			trigger.addEventListener('click', function(e) {
				e.stopPropagation();
				select.classList.toggle('open');
			});

			checkboxes.forEach(function(cb) {
				cb.addEventListener('change', updateLabel);
			});
		});

		// Initialize custom taxonomy searchable fields
		document.querySelectorAll('.fanfic-custom-search-field').forEach(function(searchField) {
			var taxonomySlug = searchField.dataset.taxonomy;
			var input = searchField.querySelector('.fanfic-custom-search-input');
			var resultsContainer = searchField.querySelector('.fanfic-custom-search-results');
			var pillContainer = searchField.querySelector('.fanfic-selected-custom-terms');
			var inputName = 'fanfic_custom_' + taxonomySlug + '[]';
			var searchTimeout = null;

			function getSelectedIds() {
				return Array.from(pillContainer.querySelectorAll('input[type="hidden"]')).map(function(h) {
					return h.value;
				});
			}

			function addPill(id, name) {
				if (getSelectedIds().indexOf(String(id)) !== -1) return;
				var pill = document.createElement('span');
				pill.className = 'fanfic-pill-value';
				pill.dataset.id = id;
				pill.innerHTML = '<span class="fanfic-pill-value-text">' + name + '</span>' +
					'<button type="button" class="fanfic-pill-value-remove" aria-label="<?php echo esc_js( __( 'Remove', 'fanfiction-manager' ) ); ?>">&times;</button>' +
					'<input type="hidden" name="' + inputName + '" value="' + id + '">';
				pillContainer.appendChild(pill);

				pill.querySelector('.fanfic-pill-value-remove').addEventListener('click', function() {
					pill.remove();
				});
			}

			function doSearch(query) {
				if (query.length < 2) {
					resultsContainer.innerHTML = '';
					resultsContainer.style.display = 'none';
					return;
				}

				var restUrl = '<?php echo esc_js( rest_url( 'fanfic/v1/custom-terms/search' ) ); ?>';
				var url = restUrl + '?taxonomy=' + encodeURIComponent(taxonomySlug) + '&q=' + encodeURIComponent(query);

				fetch(url, {
					headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' }
				})
				.then(function(resp) { return resp.json(); })
				.then(function(terms) {
					var selectedIds = getSelectedIds();
					var html = '';
					terms.forEach(function(term) {
						if (selectedIds.indexOf(String(term.id)) === -1) {
							html += '<div class="fanfic-custom-search-result" role="option" data-id="' + term.id + '" data-name="' + term.name.replace(/"/g, '&quot;') + '">' + term.name + '</div>';
						}
					});
					if (html === '' && terms.length > 0) {
						html = '<div class="fanfic-custom-search-no-results"><?php echo esc_js( __( 'All matching terms already selected.', 'fanfiction-manager' ) ); ?></div>';
					} else if (html === '') {
						html = '<div class="fanfic-custom-search-no-results"><?php echo esc_js( __( 'No results found.', 'fanfiction-manager' ) ); ?></div>';
					}
					resultsContainer.innerHTML = html;
					resultsContainer.style.display = 'block';

					resultsContainer.querySelectorAll('.fanfic-custom-search-result').forEach(function(result) {
						result.addEventListener('click', function() {
							addPill(this.dataset.id, this.dataset.name);
							input.value = '';
							resultsContainer.innerHTML = '';
							resultsContainer.style.display = 'none';
							input.focus();
						});
					});
				})
				.catch(function() {
					resultsContainer.innerHTML = '';
					resultsContainer.style.display = 'none';
				});
			}

			input.addEventListener('input', function() {
				clearTimeout(searchTimeout);
				var query = this.value.trim();
				searchTimeout = setTimeout(function() { doSearch(query); }, 250);
			});

			// Close results when clicking outside
			document.addEventListener('click', function(e) {
				if (!searchField.contains(e.target)) {
					resultsContainer.innerHTML = '';
					resultsContainer.style.display = 'none';
				}
			});

			// Allow removing pre-existing pills
			pillContainer.querySelectorAll('.fanfic-pill-value-remove').forEach(function(btn) {
				btn.addEventListener('click', function() {
					this.closest('.fanfic-pill-value').remove();
				});
			});
		});

		// Close multi-select dropdowns when clicking outside
		document.addEventListener('click', function(e) {
			document.querySelectorAll('.multi-select.open').forEach(function(select) {
				if (!select.contains(e.target)) {
					select.classList.remove('open');
				}
			});
		});

		// Close button functionality for messages
		var closeButtons = document.querySelectorAll('.fanfic-message-close');
		closeButtons.forEach(function(button) {
			button.addEventListener('click', function() {
				var message = this.closest('.fanfic-message');
				if (message) {
					message.style.opacity = '0';
					message.style.transform = 'translateY(-10px)';
					setTimeout(function() {
						message.remove();
					}, 300);
				}
			});
		});

		var fanficPendingFormMessageKey = 'fanfic_pending_form_message';
		var storyMessagesContainer = document.getElementById('fanfic-messages');

		function appendStoryFormMessage(type, message, persistent) {
			if (!message) {
				return;
			}

			var normalizedType = (type === 'error' || type === 'warning') ? type : 'success';
			if (!storyMessagesContainer) {
				console[(normalizedType === 'error') ? 'error' : 'log'](message);
				return;
			}

			while (storyMessagesContainer.firstChild) {
				storyMessagesContainer.removeChild(storyMessagesContainer.firstChild);
			}

			var icon = normalizedType === 'error' ? '&#10007;' : (normalizedType === 'warning' ? '&#9888;' : '&#10003;');
			var notice = document.createElement('div');
			notice.className = 'fanfic-message fanfic-message-' + normalizedType;
			notice.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
			notice.setAttribute('aria-live', normalizedType === 'error' ? 'assertive' : 'polite');
			notice.innerHTML = '<span class="fanfic-message-icon" aria-hidden="true">' + icon + '</span><span class="fanfic-message-content"></span><button type="button" class="fanfic-message-close" aria-label="<?php echo esc_attr( __( 'Close message', 'fanfiction-manager' ) ); ?>">&times;</button>';

			var contentNode = notice.querySelector('.fanfic-message-content');
			if (contentNode) {
				contentNode.textContent = message;
				contentNode.style.whiteSpace = 'pre-line';
			}

			var closeBtn = notice.querySelector('.fanfic-message-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function() {
					notice.remove();
				});
			}

			storyMessagesContainer.appendChild(notice);

			if (!persistent && normalizedType !== 'error') {
				setTimeout(function() {
					notice.remove();
				}, 5000);
			}
		}

		function queueStoryFormMessage(type, message, persistent) {
			if (!message) {
				return;
			}

			try {
				sessionStorage.setItem(fanficPendingFormMessageKey, JSON.stringify({
					type: type || 'success',
					message: message,
					persistent: !!persistent
				}));
			} catch (storageError) {
				appendStoryFormMessage(type, message, persistent);
			}
		}

		function consumeStoryFormMessage() {
			try {
				var rawMessage = sessionStorage.getItem(fanficPendingFormMessageKey);
				if (!rawMessage) {
					return;
				}

				sessionStorage.removeItem(fanficPendingFormMessageKey);
				var parsedMessage = JSON.parse(rawMessage);
				if (parsedMessage && parsedMessage.message) {
					appendStoryFormMessage(parsedMessage.type, parsedMessage.message, parsedMessage.persistent);
				}
			} catch (storageError) {
				try {
					sessionStorage.removeItem(fanficPendingFormMessageKey);
				} catch (cleanupError) {}
			}
		}

		consumeStoryFormMessage();

		function reorderStoryFormFields() {
			var form = document.getElementById('fanfic-story-form');
			if (!form) {
				return;
			}

			var formContent = form.querySelector('.fanfic-form-content.fanfic-auto-columns');
			if (!formContent) {
				return;
			}

			function getField(selector) {
				var element = formContent.querySelector(selector);
				return element ? element.closest('.fanfic-form-field') : null;
			}

			function addUnique(fields, field) {
				if (field && fields.indexOf(field) === -1) {
					fields.push(field);
				}
			}

			var titleField = getField('#fanfic_story_title');
			var introField = getField('#fanfic_story_intro');
			var statusField = getField('#fanfic_story_status');
			var languageField = getField('#fanfic_story_language');
			var translationsField = formContent.querySelector('.fanfic-translations-field');
			translationsField = translationsField ? translationsField.closest('.fanfic-form-field') : null;
			var coauthorsField = formContent.querySelector('.fanfic-coauthors-field');
			coauthorsField = coauthorsField ? coauthorsField.closest('.fanfic-form-field') : null;
			var featuredImageField = getField('#fanfic_story_image');
			var genresField = null;
			var genresCheckbox = formContent.querySelector('input[name="fanfic_story_genres[]"]');
			if (genresCheckbox) {
				genresField = genresCheckbox.closest('.fanfic-form-field');
			}
			var originalWorkField = getField('input[name="fanfic_is_original_work"]');
			var fandomsField = formContent.querySelector('.fanfic-fandoms-field');
			fandomsField = fandomsField ? fandomsField.closest('.fanfic-form-field') : null;
			var warningsField = null;
			var warningsCheckbox = formContent.querySelector('input[name="fanfic_story_warnings[]"]');
			if (warningsCheckbox) {
				warningsField = warningsCheckbox.closest('.fanfic-form-field');
			}
			var visibleTagsField = getField('#fanfic_visible_tags');
			var hiddenTagsField = getField('#fanfic_invisible_tags');
			var publicationDateField = getField('#fanfic_story_publish_date');
			var authorNotesField = formContent.querySelector('.fanfic-author-notes-field');

			var customTaxonomyFields = [];
			var customTaxonomyInputs = formContent.querySelectorAll('[id^="fanfic_custom_"], [name^="fanfic_custom_"]');
			customTaxonomyInputs.forEach(function(input) {
				var field = input.closest('.fanfic-form-field');
				if (field && customTaxonomyFields.indexOf(field) === -1) {
					customTaxonomyFields.push(field);
				}
			});

			var orderedFields = [];
			addUnique(orderedFields, titleField);
			addUnique(orderedFields, introField);
			addUnique(orderedFields, authorNotesField);
			addUnique(orderedFields, statusField);
			addUnique(orderedFields, languageField);
			addUnique(orderedFields, translationsField);
			addUnique(orderedFields, coauthorsField);
			addUnique(orderedFields, featuredImageField);
			addUnique(orderedFields, genresField);
			customTaxonomyFields.forEach(function(field) {
				addUnique(orderedFields, field);
			});
			addUnique(orderedFields, originalWorkField);
			addUnique(orderedFields, fandomsField);
			addUnique(orderedFields, warningsField);
			addUnique(orderedFields, visibleTagsField);
			addUnique(orderedFields, hiddenTagsField);
			addUnique(orderedFields, publicationDateField);

			Array.prototype.forEach.call(formContent.children, function(child) {
				if (child.classList && child.classList.contains('fanfic-form-field')) {
					addUnique(orderedFields, child);
				}
			});

			orderedFields.forEach(function(field) {
				formContent.appendChild(field);
			});

			if (originalWorkField && fandomsField) {
				originalWorkField.classList.add('fanfic-group-original-fandoms');
				fandomsField.classList.add('fanfic-group-original-fandoms');
			}
			if (visibleTagsField && hiddenTagsField) {
				visibleTagsField.classList.add('fanfic-group-tags');
				hiddenTagsField.classList.add('fanfic-group-tags');
			}
		}

		reorderStoryFormFields();

		function normalizeEditorContent(value) {
			var html = (typeof value === 'string' ? value : '').replace(/\r\n/g, '\n').trim();
			if (!html) {
				return '';
			}

			var probe = document.createElement('div');
			probe.innerHTML = html;

			// Treat TinyMCE placeholder markup as empty content.
			var text = (probe.textContent || '').replace(/\u00a0/g, ' ').trim();
			var meaningfulNode = probe.querySelector('img,video,audio,iframe,object,embed,hr,table,ul,ol,li,blockquote,pre,code');
			if (!text && !meaningfulNode) {
				return '';
			}

			return html;
		}

		function normalizeComparableList(value) {
			var items = (typeof value === 'string' ? value : '')
				.split(',')
				.map(function(item) {
					return item.trim();
				})
				.filter(function(item) {
					return '' !== item;
				});

			items.sort(function(a, b) {
				var aNum = /^-?\d+$/.test(a);
				var bNum = /^-?\d+$/.test(b);
				if (aNum && bNum) {
					return parseInt(a, 10) - parseInt(b, 10);
				}
				return a.localeCompare(b);
			});

			return items.join(',');
		}

		<?php if ( $is_edit_mode ) : ?>
		// Change detection for Update buttons
		var form = document.getElementById('fanfic-story-form');
		if (form) {
			function getCustomTaxonomyState(container) {
				var fields = container.querySelectorAll('[name^="fanfic_custom_"]');
				var values = {};

				fields.forEach(function(field) {
					var name = field.name;
					if (!name) {
						return;
					}

					if (!Object.prototype.hasOwnProperty.call(values, name)) {
						values[name] = [];
					}

					if (field.type === 'checkbox' || field.type === 'radio') {
						if (field.checked) {
							values[name].push(field.value || '');
						}
						return;
					}

					if (field.tagName === 'SELECT' && field.multiple) {
						Array.from(field.selectedOptions).forEach(function(option) {
							values[name].push(option.value || '');
						});
						return;
					}

					values[name] = [field.value || ''];
				});

				var parts = [];
				Object.keys(values).sort().forEach(function(name) {
					var entry = values[name];
					if (Array.isArray(entry)) {
						entry = entry.slice().sort();
					}
					parts.push(name + '=' + entry.join(','));
				});

				return parts.join('|');
			}

			function checkForChanges() {
				var titleField = document.getElementById('fanfic_story_title');
				var contentField = document.getElementById('fanfic_story_intro');
				var statusField = document.getElementById('fanfic_story_status');
				var imageField = document.getElementById('fanfic_story_image');
				var genreCheckboxes = document.querySelectorAll('input[name="fanfic_story_genres[]"]:checked');
				var warningCheckboxes = document.querySelectorAll('input[name="fanfic_story_warnings[]"]:checked');
				var fandomInputs = document.querySelectorAll('input[name="fanfic_story_fandoms[]"]');
				var translationInputs = document.querySelectorAll('input[name="fanfic_story_translations[]"]');
				var coauthorInputs = document.querySelectorAll('input[name="fanfic_story_coauthors[]"]');
				var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
				var languageField = document.getElementById('fanfic_story_language');
				var visibleTagsField = document.getElementById('fanfic_visible_tags');
				var invisibleTagsField = document.getElementById('fanfic_invisible_tags');
				var publishDateField = document.getElementById('fanfic_story_publish_date');
				var notesEnabledField = document.querySelector('input[name="fanfic_author_notes_enabled"]');
				var notesPositionField = document.querySelector('select[name="fanfic_author_notes_position"]');
				var notesTextarea = document.querySelector('textarea[name="fanfic_author_notes"]');
				var commentsEnabledField = document.querySelector('input[name="fanfic_comments_enabled"][type="checkbox"]');

				var currentTitle = titleField ? titleField.value : '';
				var currentContent = contentField ? contentField.value : '';
				var currentStatus = statusField ? statusField.value : '';
				var currentImage = imageField ? imageField.value : '';
				var currentGenres = normalizeComparableList(Array.from(genreCheckboxes).map(function(cb) { return cb.value; }).join(','));
				var currentWarnings = normalizeComparableList(Array.from(warningCheckboxes).map(function(cb) { return cb.value; }).join(','));
				var currentFandoms = normalizeComparableList(Array.from(fandomInputs).map(function(input) { return input.value; }).join(','));
				var currentTranslations = normalizeComparableList(Array.from(translationInputs).map(function(input) { return input.value; }).join(','));
				var currentCoauthors = normalizeComparableList(Array.from(coauthorInputs).map(function(input) { return input.value; }).join(','));
				var currentOriginal = originalCheckbox && originalCheckbox.checked ? '1' : '0';
				var currentLanguage = languageField ? languageField.value : '';
				var currentVisibleTags = visibleTagsField ? visibleTagsField.value : '';
				var currentInvisibleTags = invisibleTagsField ? invisibleTagsField.value : '';
				var currentPublishDate = publishDateField ? publishDateField.value : '';
				var currentNotesEnabled = notesEnabledField && notesEnabledField.checked ? '1' : '0';
				var currentNotesPosition = notesPositionField ? notesPositionField.value : 'below';
				var currentCommentsEnabled = commentsEnabledField && commentsEnabledField.checked ? '1' : '0';
				var currentNotesContent = '';
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_story_author_notes')) {
					currentNotesContent = tinymce.get('fanfic_story_author_notes').getContent();
				} else if (notesTextarea) {
					currentNotesContent = notesTextarea.value;
				}
				currentNotesContent = normalizeEditorContent(currentNotesContent);
				var currentCustomTaxonomies = getCustomTaxonomyState(form);
				var originalTitle = form.getAttribute('data-original-title') || '';
				var originalContent = form.getAttribute('data-original-content') || '';
				var originalGenres = normalizeComparableList(form.getAttribute('data-original-genres') || '');
				var originalStatus = form.getAttribute('data-original-status') || '';
				var originalFandoms = normalizeComparableList(form.getAttribute('data-original-fandoms') || '');
				var originalOriginal = form.getAttribute('data-original-original') || '0';
				var originalImage = form.getAttribute('data-original-image') || '';
				var originalWarnings = normalizeComparableList(form.getAttribute('data-original-warnings') || '');
				var originalLanguage = form.getAttribute('data-original-language');
				var originalTranslations = normalizeComparableList(form.getAttribute('data-original-translations') || '');
				var originalCoauthors = normalizeComparableList(form.getAttribute('data-original-coauthors') || '');
				var originalVisibleTags = form.getAttribute('data-original-visible-tags');
				var originalInvisibleTags = form.getAttribute('data-original-invisible-tags');
				var originalPublishDate = form.getAttribute('data-original-publish-date');
				var originalCustomTaxonomies = form.getAttribute('data-original-custom-taxonomies');
				var originalNotesEnabled = form.getAttribute('data-original-notes-enabled');
				var originalNotesPosition = form.getAttribute('data-original-notes-position');
				var originalNotesContentAttr = form.getAttribute('data-original-notes-content');
				var originalNotesContent = normalizeEditorContent(originalNotesContentAttr || '');
				var originalCommentsEnabled = form.getAttribute('data-original-comments-enabled');

				if (null === form.getAttribute('data-original-warnings')) {
					form.setAttribute('data-original-warnings', currentWarnings);
				}
				if (null === originalLanguage) {
					originalLanguage = currentLanguage;
					form.setAttribute('data-original-language', originalLanguage);
				}
				if (null === form.getAttribute('data-original-translations')) {
					form.setAttribute('data-original-translations', currentTranslations);
				}
				if (null === form.getAttribute('data-original-coauthors')) {
					form.setAttribute('data-original-coauthors', currentCoauthors);
				}
				if (null === originalVisibleTags) {
					originalVisibleTags = currentVisibleTags;
					form.setAttribute('data-original-visible-tags', originalVisibleTags);
				}
				if (null === originalInvisibleTags) {
					originalInvisibleTags = currentInvisibleTags;
					form.setAttribute('data-original-invisible-tags', originalInvisibleTags);
				}
				if (null === originalPublishDate) {
					originalPublishDate = currentPublishDate;
					form.setAttribute('data-original-publish-date', originalPublishDate);
				}
				if (null === originalCustomTaxonomies) {
					originalCustomTaxonomies = currentCustomTaxonomies;
					form.setAttribute('data-original-custom-taxonomies', originalCustomTaxonomies);
				}
				if (null === originalNotesEnabled) {
					originalNotesEnabled = currentNotesEnabled;
					form.setAttribute('data-original-notes-enabled', originalNotesEnabled);
				}
				if (null === originalNotesPosition) {
					originalNotesPosition = currentNotesPosition;
					form.setAttribute('data-original-notes-position', originalNotesPosition);
				}
				if (null === originalNotesContentAttr) {
					originalNotesContent = currentNotesContent;
					form.setAttribute('data-original-notes-content', originalNotesContent);
				}
				if (null === originalCommentsEnabled) {
					originalCommentsEnabled = currentCommentsEnabled;
					form.setAttribute('data-original-comments-enabled', originalCommentsEnabled);
				}

				var hasChanges = (currentTitle !== originalTitle) ||
								(currentContent !== originalContent) ||
								(currentStatus !== originalStatus) ||
								(currentImage !== originalImage) ||
								(currentGenres !== originalGenres) ||
								(currentWarnings !== originalWarnings) ||
								(currentFandoms !== originalFandoms) ||
								(currentOriginal !== originalOriginal) ||
								(currentLanguage !== originalLanguage) ||
								(currentTranslations !== originalTranslations) ||
								(currentCoauthors !== originalCoauthors) ||
								(currentVisibleTags !== originalVisibleTags) ||
								(currentInvisibleTags !== originalInvisibleTags) ||
								(currentPublishDate !== originalPublishDate) ||
								(currentCustomTaxonomies !== originalCustomTaxonomies) ||
								(currentNotesEnabled !== originalNotesEnabled) ||
								(currentNotesPosition !== originalNotesPosition) ||
								(currentNotesContent !== originalNotesContent) ||
								(currentCommentsEnabled !== originalCommentsEnabled);

				var liveUpdateBtn = document.getElementById('update-button');
				if (liveUpdateBtn) {
					liveUpdateBtn.disabled = !hasChanges;
				}
			}

			// Universal field tracking: any field mutation inside the current content
			// section re-checks dirty state, including dynamically added fields.
			var formSection = form.closest('.fanfic-content-section');
			var listenerScope = formSection || form;

			function isTrackedStoryField(target) {
				if (!target || !form.contains(target)) {
					return false;
				}

				var tagName = target.tagName ? target.tagName.toLowerCase() : '';
				if (tagName !== 'input' && tagName !== 'textarea' && tagName !== 'select') {
					return false;
				}

				var inputType = (target.type || '').toLowerCase();
				if (inputType === 'submit' || inputType === 'button' || inputType === 'reset' || inputType === 'file') {
					return false;
				}

				return true;
			}

			function handleUniversalFieldChange(event) {
				if (!isTrackedStoryField(event.target)) {
					return;
				}
				checkForChanges();
			}

			listenerScope.addEventListener('input', handleUniversalFieldChange, true);
			listenerScope.addEventListener('change', handleUniversalFieldChange, true);

			if (typeof tinymce !== 'undefined') {
				var attachStoryNotesEditorEvents = function(editor) {
					if (!editor || editor.id !== 'fanfic_story_author_notes' || editor.fanficDirtyEventsAttached) {
						return;
					}
					editor.on('init', function() {
						var initializedContent = normalizeEditorContent(editor.getContent());
						form.setAttribute('data-original-notes-content', initializedContent);
						checkForChanges();
					});
					editor.on('change keyup paste input NodeChange', function() {
						checkForChanges();
					});
					editor.fanficDirtyEventsAttached = true;
				};

				tinymce.on('AddEditor', function(e) {
					attachStoryNotesEditorEvents(e.editor);
				});

				attachStoryNotesEditorEvents(tinymce.get('fanfic_story_author_notes'));
			}

			// Initial check on page load
			checkForChanges();

			window.addEventListener('beforeunload', function(e) {
				if (window.__fanficStoryResetting) {
					return;
				}
				var updateBtn = document.getElementById('update-button');
				var hasUnsaved = (updateBtn && !updateBtn.disabled);
				if (hasUnsaved) {
					e.preventDefault();
				}
			});
		}

		var storyBackButton = document.getElementById('story-back-button');
		if (storyBackButton) {
			storyBackButton.addEventListener('click', function(e) {
				e.preventDefault();
				window.__fanficStoryResetting = true;
				window.location.reload();
			});
		}

			// Delete story confirmation
			var deleteStoryButton = document.getElementById('delete-story-button');

			if (deleteStoryButton) {
				deleteStoryButton.addEventListener('click', function() {
					var storyId = this.getAttribute('data-story-id');
					var storyTitle = this.getAttribute('data-story-title') || '';

					if (!window.FanficStoryDelete) {
						return;
					}

					window.FanficStoryDelete.openConfirm({
						trigger: this,
						buttonElement: deleteStoryButton,
						storyId: storyId,
						storyTitle: storyTitle,
						nonce: '<?php echo esc_js( wp_create_nonce( 'fanfic_delete_story' ) ); ?>',
						redirectUrl: '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>',
						ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>'
					});
				});
			}

		// Chapter hide buttons with AJAX
		var chapterHideButtons = document.querySelectorAll('.fanfic-hide-chapter');
			chapterHideButtons.forEach(function(button) {
				button.addEventListener('click', function(e) {
					e.preventDefault();
					var rowElement = button.closest('tr');
					var chapterTitle = this.getAttribute('data-chapter-title');
					if (!chapterTitle && rowElement) {
						var titleCell = rowElement.querySelector('td:nth-child(2)');
						chapterTitle = titleCell ? titleCell.textContent.trim() : '';
					}
					if (!chapterTitle) {
						chapterTitle = '<?php echo esc_js( __( 'this chapter', 'fanfiction-manager' ) ); ?>';
					}
					var chapterId = this.getAttribute('data-chapter-id');
					var storyId = this.getAttribute('data-story-id');
					var buttonElement = this;

					// Check if this is the last chapter via AJAX
				var checkFormData = new FormData();
				checkFormData.append('action', 'fanfic_check_last_chapter');
				checkFormData.append('chapter_id', chapterId);
				checkFormData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_check_last_chapter' ); ?>');

				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: checkFormData
				})
				.then(function(response) {
					return response.json();
				})
					.then(function(checkData) {
						var confirmMessage = '<?php esc_html_e( 'Are you sure you want to hide', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?';

						// If this is the last chapter, add warning
						if (checkData.success && checkData.data.is_last_chapter) {
							confirmMessage += '\n\n<?php esc_html_e( 'This is the last visible chapter, prologue, or epilogue. Hiding it will hide your story from readers.', 'fanfiction-manager' ); ?>';
						}

					if (!confirm(confirmMessage)) {
						return; // User cancelled
					}

					// User confirmed - proceed with hide
					buttonElement.disabled = true;
					buttonElement.textContent = FanficMessages.hideing;

					// Prepare AJAX request
					var formData = new FormData();
					formData.append('action', 'fanfic_hide_chapter');
					formData.append('chapter_id', chapterId);
					formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_hide_chapter' ); ?>');

					// Send AJAX request
					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						if (data.success) {
							// Show alert if story was auto-drafted
							if (data.data.story_auto_drafted) {
								queueStoryFormMessage('warning', FanficMessages.hideChapterAutoDraftAlert, true);
							}
							// Reload page to show updated status
							location.reload();
						} else {
							// Re-enable button and show error
							buttonElement.disabled = false;
							buttonElement.textContent = FanficMessages.hide;
							appendStoryFormMessage('error', data.data.message || FanficMessages.errorHideingChapter, true);
						}
					})
					.catch(function(error) {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.hide;
						appendStoryFormMessage('error', FanficMessages.errorHideingChapter, true);
						console.error('Error:', error);
					});
				})
				.catch(function(error) {
					console.error('Error checking last chapter:', error);
					// Fall back to simple confirmation
					if (confirm('<?php esc_html_e( 'Are you sure you want to hide', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
						// Proceed with hide even if check failed
						buttonElement.disabled = true;
						buttonElement.textContent = FanficMessages.hideing;

						var formData = new FormData();
						formData.append('action', 'fanfic_hide_chapter');
						formData.append('chapter_id', chapterId);
						formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_hide_chapter' ); ?>');

						fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							credentials: 'same-origin',
							body: formData
						})
						.then(function(response) {
							return response.json();
						})
						.then(function(data) {
							if (data.success) {
								if (data.data.story_auto_drafted) {
									queueStoryFormMessage('warning', FanficMessages.hideChapterAutoDraftAlert, true);
								}
								location.reload();
							} else {
								buttonElement.disabled = false;
								buttonElement.textContent = FanficMessages.hide;
								appendStoryFormMessage('error', data.data.message || FanficMessages.errorHideingChapter, true);
							}
						});
					}
				});
			});
		});

		// Chapter publish buttons with AJAX and validation
		var chapterPublishButtons = document.querySelectorAll('.fanfic-publish-chapter');
		chapterPublishButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				var chapterTitle = this.getAttribute('data-chapter-title');
				var chapterId = this.getAttribute('data-chapter-id');
				var buttonElement = this;
				var rowElement = buttonElement.closest('tr');

				// Disable button to prevent double-clicks
				buttonElement.disabled = true;
				buttonElement.textContent = FanficMessages.publishing;

				// Prepare AJAX request
				var formData = new FormData();
				formData.append('action', 'fanfic_publish_chapter');
				formData.append('chapter_id', chapterId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_publish_chapter' ); ?>');

				// Send AJAX request
				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						// Check if story became publishable
						if (data.data.story_became_publishable) {
							// Redirect to edit page with publish prompt and success parameters
							var editUrl = '<?php echo esc_js( fanfic_get_edit_story_url( $story_id ) ); ?>';
							// Check if URL already has query parameters
							var separator = editUrl.indexOf('?') !== -1 ? '&' : '?';
							window.location.href = editUrl + separator + 'success=true&show_publish_prompt=1';
						} else {
							// Just reload to show updated chapter status
							location.reload();
						}
					} else {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.publish;

						// Show validation errors if present
						if (data.data && data.data.errors && data.data.errors.length > 0) {
							// Build error message from server-provided messages
							var errorMessage = data.data.message + '\n\n';
							data.data.errors.forEach(function(message) {
								errorMessage += '- ' + message + '\n';
							});
							errorMessage += '\n' + FanficMessages.clickEditToFix;
							appendStoryFormMessage('error', errorMessage, true);
						} else {
							appendStoryFormMessage('error', data.data.message || FanficMessages.errorPublishingChapter, true);
						}
					}
				})
				.catch(function(error) {
					// Re-enable button and show error
					buttonElement.disabled = false;
					buttonElement.textContent = FanficMessages.publish;
					appendStoryFormMessage('error', FanficMessages.errorPublishingChapter, true);
					console.error('Error:', error);
				});
			});
		});

			// Chapter delete buttons with modal confirmation and last chapter warning
			function performChapterDelete(buttonElement, chapterId, rowElement) {
				buttonElement.disabled = true;
				buttonElement.textContent = FanficMessages.deleting;

				var formData = new FormData();
				formData.append('action', 'fanfic_delete_chapter');
				formData.append('chapter_id', chapterId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

				fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						if (data.data.story_auto_drafted) {
							queueStoryFormMessage('warning', '<?php echo esc_js( __( 'Chapter deleted. Your story has been set to Hidden because it no longer has any visible chapters, prologues, or epilogues.', 'fanfiction-manager' ) ); ?>', true);
							window.location.reload();
							return;
						}

						rowElement.style.transition = 'opacity 0.5s ease-out';
						rowElement.style.opacity = '0';

						setTimeout(function() {
							rowElement.remove();

							var tableBody = document.querySelector('.fanfic-chapters-table tbody');
							if (tableBody && tableBody.children.length === 0) {
								window.location.reload();
							}
						}, 500);
						return;
					}

					buttonElement.disabled = false;
					buttonElement.textContent = FanficMessages.delete;
					appendStoryFormMessage('error', data.data.message || FanficMessages.errorDeletingChapter, true);
				})
				.catch(function(error) {
					buttonElement.disabled = false;
					buttonElement.textContent = FanficMessages.delete;
					appendStoryFormMessage('error', FanficMessages.errorDeletingChapter, true);
					console.error('Error:', error);
				});
			}

			var chapterDeleteButtons = document.querySelectorAll('[data-chapter-id]');
			chapterDeleteButtons.forEach(function(button) {
				if (button.classList.contains('danger')) {
					button.addEventListener('click', function() {
						var chapterTitle = this.getAttribute('data-chapter-title') || '';
						var chapterId = this.getAttribute('data-chapter-id');
						var buttonElement = this;
						var rowElement = buttonElement.closest('tr');

						var checkFormData = new FormData();
						checkFormData.append('action', 'fanfic_check_last_chapter');
						checkFormData.append('chapter_id', chapterId);
						checkFormData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

						fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							credentials: 'same-origin',
							body: checkFormData
						})
						.then(function(response) {
							return response.json();
						})
						.then(function(checkData) {
							var isLastChapter = checkData.success && checkData.data.is_last_chapter;
						var warning = FanficDeleteMessages.deleteChapterWarning;
						if (isLastChapter) {
							warning += ' ' + FanficDeleteMessages.deleteChapterLastWarning;
						}

							if (!window.FanficDeleteConfirmModal) {
								return;
							}

							window.FanficDeleteConfirmModal.open({
								trigger: buttonElement,
								title: FanficDeleteMessages.deleteChapterTitle + (chapterTitle ? ': ' + chapterTitle : ''),
								warning: warning,
								confirmLabel: FanficMessages.delete,
								onConfirm: function() {
									performChapterDelete(buttonElement, chapterId, rowElement);
								}
							});
						})
							.catch(function(error) {
								console.error('Error checking last chapter:', error);
								if (window.FanficDeleteConfirmModal) {
									window.FanficDeleteConfirmModal.open({
								trigger: buttonElement,
								title: FanficDeleteMessages.deleteChapterTitle + (chapterTitle ? ': ' + chapterTitle : ''),
								warning: FanficDeleteMessages.deleteChapterWarning,
								confirmLabel: FanficMessages.delete,
								onConfirm: function() {
									performChapterDelete(buttonElement, chapterId, rowElement);
										}
									});
								}
							});
						});
					}
				});

		// Handle auto-draft warning from chapter deletion redirect
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('story_auto_drafted') === '1') {
			var warningModal = document.getElementById('fanfic-story-auto-draft-warning');
			var storyTitleEl = document.getElementById('fanfic-story-warning-title');
			if (warningModal && storyTitleEl) {
				var storyTitle = urlParams.get('story_title');
				if (storyTitle) {
					storyTitleEl.textContent = decodeURIComponent(storyTitle);
				}
				warningModal.classList.add('show');
				// Remove the parameter from URL
				urlParams.delete('story_auto_drafted');
				urlParams.delete('story_title');
				urlParams.delete('chapter_deleted');
				urlParams.delete('story_id');
				var newUrl = window.location.pathname;
				if (urlParams.toString()) {
					newUrl += '?' + urlParams.toString();
				}
				window.history.replaceState({}, '', newUrl);
			}
		}
		<?php endif; ?>

		// Form validation for genres (at least one must be checked)
		var storyForm = document.getElementById('fanfic-story-form');
		if (storyForm) {
			var storyAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

			function showStoryFormMessage(type, message, persistent) {
				appendStoryFormMessage(type, message, persistent);
			}

			function highlightFieldContainer(fieldElement) {
				if (!fieldElement) {
					return;
				}

				var fieldContainer = fieldElement.closest('.fanfic-form-field');
				if (!fieldContainer) {
					return;
				}

				fieldContainer.style.border = '2px solid #e74c3c';
				setTimeout(function() {
					fieldContainer.style.border = '';
				}, 3000);
			}

			function updateStoryOriginalState() {
				var titleField = document.getElementById('fanfic_story_title');
				var contentField = document.getElementById('fanfic_story_intro');
				var statusField = document.getElementById('fanfic_story_status');
				var imageField = document.getElementById('fanfic_story_image');
				var genreCheckboxes = document.querySelectorAll('input[name="fanfic_story_genres[]"]:checked');
				var warningCheckboxes = document.querySelectorAll('input[name="fanfic_story_warnings[]"]:checked');
				var fandomInputs = document.querySelectorAll('input[name="fanfic_story_fandoms[]"]');
				var translationInputs = document.querySelectorAll('input[name="fanfic_story_translations[]"]');
				var coauthorInputs = document.querySelectorAll('input[name="fanfic_story_coauthors[]"]');
				var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
				var languageField = document.getElementById('fanfic_story_language');
				var visibleTagsField = document.getElementById('fanfic_visible_tags');
				var invisibleTagsField = document.getElementById('fanfic_invisible_tags');
				var publishDateField = document.getElementById('fanfic_story_publish_date');
				var notesEnabledField = document.querySelector('input[name="fanfic_author_notes_enabled"]');
				var notesPositionField = document.querySelector('select[name="fanfic_author_notes_position"]');
				var notesTextarea = document.querySelector('textarea[name="fanfic_author_notes"]');
				var customTaxonomyFields = storyForm.querySelectorAll('[name^="fanfic_custom_"]');
				var notesContent = '';
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_story_author_notes')) {
					notesContent = tinymce.get('fanfic_story_author_notes').getContent();
				} else if (notesTextarea) {
					notesContent = notesTextarea.value;
				}
				notesContent = normalizeEditorContent(notesContent);

				var customTaxonomyValues = {};
				customTaxonomyFields.forEach(function(field) {
					var name = field.name;
					if (!name) {
						return;
					}

					if (!Object.prototype.hasOwnProperty.call(customTaxonomyValues, name)) {
						customTaxonomyValues[name] = [];
					}

					if (field.type === 'checkbox' || field.type === 'radio') {
						if (field.checked) {
							customTaxonomyValues[name].push(field.value || '');
						}
						return;
					}

					if (field.tagName === 'SELECT' && field.multiple) {
						Array.from(field.selectedOptions).forEach(function(option) {
							customTaxonomyValues[name].push(option.value || '');
						});
						return;
					}

					customTaxonomyValues[name] = [field.value || ''];
				});

				var customTaxonomyState = [];
				Object.keys(customTaxonomyValues).sort().forEach(function(name) {
					var values = customTaxonomyValues[name];
					if (Array.isArray(values)) {
						values = values.slice().sort();
					}
					customTaxonomyState.push(name + '=' + values.join(','));
				});

				storyForm.setAttribute('data-original-title', titleField ? titleField.value : '');
				storyForm.setAttribute('data-original-content', contentField ? contentField.value : '');
				storyForm.setAttribute('data-original-status', statusField ? statusField.value : '');
				storyForm.setAttribute('data-original-image', imageField ? imageField.value : '');
				storyForm.setAttribute('data-original-genres', Array.from(genreCheckboxes).map(function(cb) { return cb.value; }).sort().join(','));
				storyForm.setAttribute('data-original-warnings', Array.from(warningCheckboxes).map(function(cb) { return cb.value; }).sort().join(','));
				storyForm.setAttribute('data-original-fandoms', Array.from(fandomInputs).map(function(input) { return input.value; }).sort().join(','));
				storyForm.setAttribute('data-original-translations', Array.from(translationInputs).map(function(input) { return input.value; }).sort().join(','));
				storyForm.setAttribute('data-original-coauthors', Array.from(coauthorInputs).map(function(input) { return input.value; }).sort().join(','));
				storyForm.setAttribute('data-original-original', originalCheckbox && originalCheckbox.checked ? '1' : '0');
				storyForm.setAttribute('data-original-language', languageField ? languageField.value : '');
				storyForm.setAttribute('data-original-visible-tags', visibleTagsField ? visibleTagsField.value : '');
				storyForm.setAttribute('data-original-invisible-tags', invisibleTagsField ? invisibleTagsField.value : '');
				storyForm.setAttribute('data-original-publish-date', publishDateField ? publishDateField.value : '');
				storyForm.setAttribute('data-original-custom-taxonomies', customTaxonomyState.join('|'));
				storyForm.setAttribute('data-original-notes-enabled', notesEnabledField && notesEnabledField.checked ? '1' : '0');
				storyForm.setAttribute('data-original-notes-position', notesPositionField ? notesPositionField.value : 'below');
				storyForm.setAttribute('data-original-notes-content', notesContent);
			}

			function getViewUrlFromEditUrl(editUrl) {
				if (!editUrl) {
					return '';
				}
				try {
					var parsed = new URL(editUrl, window.location.origin);
					parsed.searchParams.delete('action');
					var normalized = parsed.toString();
					if (normalized.slice(-1) === '?') {
						normalized = normalized.slice(0, -1);
					}
					return normalized;
				} catch (err) {
					return '';
				}
			}

			function getBadgeToneClass(statusClass) {
				var normalized = String(statusClass || '').toLowerCase();
				if (['publish', 'published', 'visible', 'active', 'enabled'].indexOf(normalized) !== -1) {
					return 'is-success';
				}
				if (['draft', 'hidden', 'pending'].indexOf(normalized) !== -1) {
					return 'is-warning';
				}
				if (['blocked', 'suspended', 'deleted'].indexOf(normalized) !== -1) {
					return 'is-danger';
				}
				if (['dismissed', 'info'].indexOf(normalized) !== -1) {
					return 'is-info';
				}
				return 'is-muted';
			}

			function getStoryStatusBadgeClass(statusClass) {
				var normalized = String(statusClass || '').toLowerCase();
				var toneClass = getBadgeToneClass(normalized);
				return 'fanfic-badge fanfic-badge--status fanfic-badge--status-lg ' + toneClass + ' fanfic-status-' + normalized;
			}

			function syncStoryActionButtons(postStatus, editUrl) {
				var actionsContainer = storyForm.querySelector('.fanfic-form-actions');
				if (!actionsContainer) {
					return;
				}

				var actionButtons = actionsContainer.querySelectorAll('button[type="submit"][name="fanfic_form_action"]');
				if (actionButtons.length < 2) {
					return;
				}

				var primaryButton = actionButtons[0];
				var secondaryButton = actionButtons[1];
				var viewLink = actionsContainer.querySelector('a[data-fanfic-story-view="1"]');
				var viewUrl = getViewUrlFromEditUrl(editUrl);

				// Primary always becomes Update (dirty-tracked, disabled until changes)
				primaryButton.value = 'update';
				primaryButton.id = 'update-button';
				primaryButton.textContent = '<?php echo esc_js( __( 'Update', 'fanfiction-manager' ) ); ?>';
				primaryButton.disabled = true;

				if (postStatus === 'publish') {
					// Secondary becomes Hide Story
					secondaryButton.value = 'save_draft';
					secondaryButton.id = 'hide-story-button';
					secondaryButton.textContent = '<?php echo esc_js( __( 'Hide Story', 'fanfiction-manager' ) ); ?>';
					secondaryButton.disabled = false;

					// Create or update the View link
					if (!viewLink && viewUrl) {
						var cancelLink = actionsContainer.querySelector('a.fanfic-button:not([data-fanfic-story-view])');
						viewLink = document.createElement('a');
						viewLink.className = 'fanfic-button secondary';
						viewLink.target = '_blank';
						viewLink.rel = 'noopener noreferrer';
						viewLink.setAttribute('data-fanfic-story-view', '1');
						viewLink.textContent = '<?php echo esc_js( __( 'View', 'fanfiction-manager' ) ); ?>';
						viewLink.href = viewUrl;
						if (cancelLink) {
							actionsContainer.insertBefore(viewLink, cancelLink);
						} else {
							actionsContainer.appendChild(viewLink);
						}
					} else if (viewLink && viewUrl) {
						viewLink.href = viewUrl;
					}
				} else {
					// Secondary becomes Make Visible
					secondaryButton.value = 'publish';
					secondaryButton.id = 'make-visible-button';
					secondaryButton.textContent = '<?php echo esc_js( __( 'Make Visible', 'fanfiction-manager' ) ); ?>';
					secondaryButton.disabled = false;

					if (viewLink) {
						viewLink.remove();
					}
				}
			}

			storyForm.addEventListener('submit', function(e) {
				var submitter = e.submitter || document.activeElement;
				var titleField = document.getElementById('fanfic_story_title');
				var titleValue = titleField ? titleField.value.trim() : '';
				var genreCheckboxes = document.querySelectorAll('input[name="fanfic_story_genres[]"]:checked');

				if (!titleValue) {
					e.preventDefault();
					showStoryFormMessage('error', '<?php echo esc_js( __( 'Story title is required.', 'fanfiction-manager' ) ); ?>', true);
					if (titleField) {
						titleField.scrollIntoView({ behavior: 'smooth', block: 'center' });
						titleField.focus();
						highlightFieldContainer(titleField);
					}
					return false;
				}

				if (genreCheckboxes.length === 0) {
					e.preventDefault();
					showStoryFormMessage('error', '<?php echo esc_js( __( 'Please select at least one genre for your story.', 'fanfiction-manager' ) ); ?>', true);
					// Scroll to genres section
					var genresLabel = document.querySelector('label:has(+ .fanfic-checkboxes)');
					if (!genresLabel) {
						// Fallback for browsers that don't support :has()
						var genresDiv = document.querySelector('.fanfic-checkboxes-grid');
						if (genresDiv && genresDiv.parentElement) {
							genresLabel = genresDiv.parentElement.querySelector('label');
						}
					}
					if (genresLabel) {
						genresLabel.scrollIntoView({ behavior: 'smooth', block: 'center' });
						highlightFieldContainer(genresLabel);
					}
					return false;
				}

				e.preventDefault();

				if (typeof tinymce !== 'undefined' && typeof tinymce.triggerSave === 'function') {
					tinymce.triggerSave();
				}

				var formData = new FormData(storyForm);
				formData.append('action', 'fanfic_submit_story_form');
				if (submitter && submitter.name && submitter.value) {
					formData.set(submitter.name, submitter.value);
				}

				var notesEnabledField = document.querySelector('input[name="fanfic_author_notes_enabled"]');
				var notesPositionField = document.querySelector('select[name="fanfic_author_notes_position"]');
				var notesTextarea = document.querySelector('textarea[name="fanfic_author_notes"]');
				var notesContent = '';
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_story_author_notes')) {
					notesContent = tinymce.get('fanfic_story_author_notes').getContent();
				} else if (notesTextarea) {
					notesContent = notesTextarea.value;
				}

				if (notesEnabledField && notesEnabledField.checked) {
					formData.set('fanfic_author_notes_enabled', '1');
				} else {
					formData.delete('fanfic_author_notes_enabled');
				}
				formData.set('fanfic_author_notes_position', notesPositionField ? notesPositionField.value : 'below');
				formData.set('fanfic_author_notes', notesContent);

				var allSubmitButtons = storyForm.querySelectorAll('button[type="submit"]');
				var originalButtonLabel = submitter ? submitter.textContent : '';
				var shouldRestoreSubmitterLabel = true;
				allSubmitButtons.forEach(function(button) {
					button.disabled = true;
				});
				if (submitter) {
					submitter.textContent = '<?php echo esc_js( __( 'Saving...', 'fanfiction-manager' ) ); ?>';
				}

				fetch(storyAjaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(payload) {
					var data = payload && payload.data ? payload.data : {};

					if (!payload || !payload.success) {
						var errorMessage = '';
						if (data.errors && data.errors.length) {
							errorMessage = data.errors.join(' ');
						} else {
							errorMessage = data.message || '<?php echo esc_js( __( 'Failed to save story. Please try again.', 'fanfiction-manager' ) ); ?>';
						}
						showStoryFormMessage('error', errorMessage, true);
						return;
					}

					showStoryFormMessage('success', data.message || '<?php echo esc_js( __( 'Story saved successfully.', 'fanfiction-manager' ) ); ?>', false);
					if (Array.isArray(data.coauthor_errors) && data.coauthor_errors.length > 0) {
						showStoryFormMessage('error', data.coauthor_errors.join(' '), true);
					}

					// Keep form synchronized with the story now being in edit mode.
					if (data.story_id) {
						var storyIdInput = storyForm.querySelector('input[name="fanfic_story_id"]');
						if (!storyIdInput) {
							storyIdInput = document.createElement('input');
							storyIdInput.type = 'hidden';
							storyIdInput.name = 'fanfic_story_id';
							storyForm.appendChild(storyIdInput);
						}
						storyIdInput.value = String(data.story_id);
					}

					var formModeInput = storyForm.querySelector('input[name="fanfic_story_form_mode"]');
					if (formModeInput) {
						formModeInput.value = 'edit';
					}

					var nonceInput = storyForm.querySelector('input[name="fanfic_story_nonce"]');
					if (nonceInput && data.edit_nonce) {
						nonceInput.value = data.edit_nonce;
					}

					var statusBadge = document.querySelector('[data-badge-type="status"][data-badge-scope="story-form-status"]');
					var formHeader = document.querySelector('.fanfic-form-header');
					if (!statusBadge && formHeader) {
						statusBadge = document.createElement('span');
						statusBadge.className = 'fanfic-badge fanfic-badge--status fanfic-badge--status-lg is-muted';
						statusBadge.setAttribute('data-badge-type', 'status');
						statusBadge.setAttribute('data-badge-scope', 'story-form-status');
						formHeader.appendChild(statusBadge);
					}
					if (statusBadge && data.status_class && data.status_label) {
						statusBadge.className = getStoryStatusBadgeClass(data.status_class);
						statusBadge.setAttribute('data-status', data.status_class);
						statusBadge.textContent = data.status_label;
					}

					if (data.post_status) {
						syncStoryActionButtons(data.post_status, data.edit_url || data.redirect_url || '');
					}
					shouldRestoreSubmitterLabel = false;

					updateStoryOriginalState();

					if (submitter && submitter.value === 'add_chapter' && data.redirect_url) {
						window.location.href = data.redirect_url;
						return;
					}

					if (data.edit_url) {
						window.history.replaceState({}, '', data.edit_url);
					}
				})
				.catch(function(error) {
					console.error('Error saving story via AJAX:', error);
					showStoryFormMessage('error', '<?php echo esc_js( __( 'An unexpected error occurred while saving the story.', 'fanfiction-manager' ) ); ?>', true);
				})
				.finally(function() {
					allSubmitButtons.forEach(function(button) {
						button.disabled = false;
					});
					if (submitter && shouldRestoreSubmitterLabel) {
						submitter.textContent = originalButtonLabel;
					}
					// Only re-disable the dirty-tracked Update button
					var updateButton = document.getElementById('update-button');
					if (updateButton) {
						updateButton.disabled = true;
					}
				});
			});
		}
	});
})();

/* Tag Pills System */
(function() {
	'use strict';

	function createTagPill(tag, pillsContainer, hiddenInput, maxTags) {
		var pill = document.createElement('span');
		pill.className = 'fanfic-pill-value';

		var text = document.createElement('span');
		text.className = 'fanfic-pill-value-text';
		text.textContent = tag;
		pill.appendChild(text);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'fanfic-pill-value-remove';
		remove.setAttribute('aria-label', 'Remove tag');
		remove.textContent = '\u00d7';
		remove.addEventListener('click', function() {
			pill.remove();
			syncHiddenInput(pillsContainer, hiddenInput);
		});
		pill.appendChild(remove);

		pillsContainer.appendChild(pill);
	}

	function syncHiddenInput(pillsContainer, hiddenInput) {
		var tags = Array.from(pillsContainer.querySelectorAll('.fanfic-pill-value-text')).map(function(el) {
			return el.textContent.trim();
		});
		hiddenInput.value = tags.join(', ');
		hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function getCurrentTags(pillsContainer) {
		return Array.from(pillsContainer.querySelectorAll('.fanfic-pill-value-text')).map(function(el) {
			return el.textContent.trim().toLowerCase();
		});
	}

	function initTagField(typingInput) {
		var targetId = typingInput.getAttribute('data-target');
		if (!targetId) {
			return;
		}

		var hiddenInput = document.getElementById(targetId);
		var pillsContainer = typingInput.parentNode.querySelector('.fanfic-tags-pills[data-target="' + targetId + '"]');
		if (!hiddenInput || !pillsContainer) {
			return;
		}

		var maxTags = parseInt(typingInput.getAttribute('data-max-tags'), 10) || 10;
		var wrapper = typingInput.closest('.fanfic-tags-input-wrapper');

		// Click on wrapper focuses the typing input
		if (wrapper) {
			wrapper.addEventListener('click', function(e) {
				if (e.target === wrapper || e.target === pillsContainer) {
					typingInput.focus();
				}
			});
		}

		// Initialize pills from existing hidden input value
		var existingValue = (hiddenInput.value || '').trim();
		if (existingValue) {
			existingValue.split(',').forEach(function(tag) {
				var trimmed = tag.trim();
				if (trimmed) {
					createTagPill(trimmed, pillsContainer, hiddenInput, maxTags);
				}
			});
		}

		function addTag(value) {
			var tag = value.trim();
			if (!tag) {
				return;
			}

			var currentTags = getCurrentTags(pillsContainer);
			if (currentTags.length >= maxTags) {
				return;
			}

			if (currentTags.indexOf(tag.toLowerCase()) !== -1) {
				return;
			}

			createTagPill(tag, pillsContainer, hiddenInput, maxTags);
			syncHiddenInput(pillsContainer, hiddenInput);
		}

		typingInput.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ',') {
				e.preventDefault();
				addTag(typingInput.value.replace(/,/g, ''));
				typingInput.value = '';
			}

			if (e.key === 'Backspace' && typingInput.value === '') {
				var pills = pillsContainer.querySelectorAll('.fanfic-pill-value');
				if (pills.length > 0) {
					pills[pills.length - 1].remove();
					syncHiddenInput(pillsContainer, hiddenInput);
				}
			}
		});

		// Handle paste with commas
		typingInput.addEventListener('input', function() {
			var val = typingInput.value;
			if (val.indexOf(',') !== -1) {
				var parts = val.split(',');
				for (var i = 0; i < parts.length - 1; i++) {
					addTag(parts[i]);
				}
				typingInput.value = parts[parts.length - 1];
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.fanfic-tags-typing').forEach(initTagField);
	});
})();
</script>
