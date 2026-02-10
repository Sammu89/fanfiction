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
	if ( $is_blocked && ! current_user_can( 'manage_options' ) && ! current_user_can( 'moderate_fanfiction' ) ) {
		// Get block reason for display
		$block_reason = get_post_meta( $story_id, '_fanfic_story_blocked_reason', true );
		$block_info = function_exists( 'fanfic_get_block_info' ) ? fanfic_get_block_info( $story_id ) : null;

		// Map reason codes to user-friendly labels
		$reason_labels = array(
			'manual'              => __( 'This story has been blocked by a moderator.', 'fanfiction-manager' ),
			'tos_violation'       => __( 'This story was blocked for violating our Terms of Service.', 'fanfiction-manager' ),
			'copyright'           => __( 'This story was blocked due to a copyright concern.', 'fanfiction-manager' ),
			'inappropriate'       => __( 'This story was blocked for containing inappropriate content.', 'fanfiction-manager' ),
			'spam'                => __( 'This story was blocked for spam or advertising.', 'fanfiction-manager' ),
			'harassment'          => __( 'This story was blocked for harassment or bullying content.', 'fanfiction-manager' ),
			'illegal'             => __( 'This story was blocked for containing potentially illegal content.', 'fanfiction-manager' ),
			'underage'            => __( 'This story was blocked for content concerns regarding minors.', 'fanfiction-manager' ),
			'rating_mismatch'     => __( 'This story was blocked because the content does not match its rating/warnings.', 'fanfiction-manager' ),
			'user_request'        => __( 'This story was blocked at your request.', 'fanfiction-manager' ),
			'pending_review'      => __( 'This story is pending moderator review.', 'fanfiction-manager' ),
			'other'               => __( 'This story has been blocked. Please contact support for more information.', 'fanfiction-manager' ),
		);

		$reason_message = isset( $reason_labels[ $block_reason ] ) ? $reason_labels[ $block_reason ] : __( 'This story has been blocked by a moderator.', 'fanfiction-manager' );
		?>
		<div class="fanfic-message fanfic-message-error fanfic-blocked-notice" role="alert" aria-live="assertive">
			<span class="fanfic-message-icon" aria-hidden="true">&#9888;</span>
			<span class="fanfic-message-content">
				<strong><?php esc_html_e( 'Story Blocked', 'fanfiction-manager' ); ?></strong><br>
				<?php echo esc_html( $reason_message ); ?><br>
				<span class="fanfic-block-info">
					<?php esc_html_e( 'You can still view your story, but editing and publishing are disabled until the block is lifted.', 'fanfiction-manager' ); ?>
					<?php esc_html_e( 'If you believe this was done in error, please contact site administration.', 'fanfiction-manager' ); ?>
				</span>
				<span class="fanfic-message-actions">
					<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button">
						<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
					</a>
					<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" class="fanfic-button secondary">
						<?php esc_html_e( 'View Story', 'fanfiction-manager' ); ?>
					</a>
				</span>
			</span>
		</div>
		<?php
		return;
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

<!-- Breadcrumb Navigation -->
<?php
fanfic_render_breadcrumb( 'edit-story', array(
	'story_id'     => $story_id,
	'story_title'  => $story_title,
	'is_edit_mode' => $is_edit_mode,
) );
?>

<!-- Unified Messages Container -->
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
// ========================================================================
// PREPARE FORM VARIABLES
// ========================================================================

$current_genres = array();
$current_status = '';
$current_fandoms = array();
$current_fandom_labels = array();
$is_original_work = false;
$featured_image = '';
$story_introduction = '';
$default_create_status = 0;
$can_edit_publish_date = function_exists( 'fanfic_can_edit_publish_date' ) ? fanfic_can_edit_publish_date( get_current_user_id() ) : false;
$story_publish_date = current_time( 'Y-m-d' );

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
	$featured_image = get_post_meta( $story->ID, '_fanfic_featured_image', true );
	$story_introduction = $story->post_excerpt;
	$story_publish_date = mysql2date( 'Y-m-d', $story->post_date, false );
}
if ( isset( $_POST['fanfic_story_publish_date'] ) ) {
	$story_publish_date = sanitize_text_field( wp_unslash( $_POST['fanfic_story_publish_date'] ) );
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
	$data_attrs = sprintf(
		'data-original-title="%s" data-original-content="%s" data-original-genres="%s" data-original-status="%s" data-original-fandoms="%s" data-original-original="%s" data-original-image="%s" data-original-translations="%s" data-original-publish-date="%s"',
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
						? Fanfic_Translations::get_translation_siblings( $story_id )
						: array(),
					'story_id'
				)
			)
		),
		esc_attr( $story_publish_date )
	);
}
?>

<!-- Main Content Area -->
<div class="fanfic-content-layout">
	<!-- Story Form -->
	<div class="fanfic-content-primary">
		<section class="fanfic-content-section" class="fanfic-form-section" aria-labelledby="form-heading">
			<h2 id="form-heading" class="screen-reader-text"><?php echo $is_edit_mode ? esc_html__( 'Story Edit Form', 'fanfiction-manager' ) : esc_html__( 'Story Creation Form', 'fanfiction-manager' ); ?></h2>

			<!-- Info Box -->
			<div class="fanfic-message fanfic-message-info" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
				<span class="fanfic-message-icon" aria-hidden="true">&#8505;</span>
				<span class="fanfic-message-content">
					<?php echo $is_edit_mode ? esc_html__( 'Your story must have an introduction, at least one chapter, a genre, and a status to be published.', 'fanfiction-manager' ) : esc_html__( 'All fields marked with an asterisk (*) are required. Your story will be saved as a draft until you add at least one chapter.', 'fanfiction-manager' ); ?>
				</span>
			</div>

			<!-- Story Form -->
			<div class="fanfic-form-wrapper fanfic-story-form-<?php echo esc_attr( $form_mode ); ?>">
				<div class="fanfic-form-header">
					<h2><?php echo $is_edit_mode ? sprintf( esc_html__( 'Edit Story: "%s"', 'fanfiction-manager' ), esc_html( $story->post_title ) ) : esc_html__( 'Create New Story', 'fanfiction-manager' ); ?></h2>
					<?php if ( $is_edit_mode ) : ?>
						<?php
						$post_status = get_post_status( $story_id );
						$status_class = 'publish' === $post_status ? 'published' : 'draft';
						// Use "Visible" instead of "Published" for stories to avoid confusing users
						$status_text = 'publish' === $post_status ? __( 'Visible', 'fanfiction-manager' ) : __( 'Draft', 'fanfiction-manager' );
						?>
						<span class="fanfic-story-status-badge fanfic-status-<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text ); ?>
						</span>
					<?php endif; ?>
				</div>

				<form method="post" class="fanfic-story-form" id="fanfic-story-form" <?php echo $data_attrs; ?><?php echo $image_upload_enabled ? ' enctype="multipart/form-data"' : ''; ?>>
					<div class="fanfic-form-content">
						<?php wp_nonce_field( 'fanfic_story_form_action' . ( $is_edit_mode ? '_' . $story_id : '' ), 'fanfic_story_nonce' ); ?>

						<!-- Story Title -->
						<div class="fanfic-form-field">
							<label for="fanfic_story_title"><?php esc_html_e( 'Story Title', 'fanfiction-manager' ); ?></label>
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
							<label for="fanfic_story_intro"><?php esc_html_e( 'Story Introduction', 'fanfiction-manager' ); ?></label>
							<textarea
								id="fanfic_story_intro"
								name="fanfic_story_introduction"
								class="fanfic-textarea"
								rows="8"
								maxlength="10000"
								required
							><?php echo isset( $_POST['fanfic_story_introduction'] ) ? esc_textarea( $_POST['fanfic_story_introduction'] ) : ( $is_edit_mode ? esc_textarea( $story->post_excerpt ) : '' ); ?></textarea>
						</div>

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
								<p class="description"><?php esc_html_e( 'Use this for importing older works. Changing this date does not count as a qualifying update for inactivity status.', 'fanfiction-manager' ); ?></p>
							</div>
						<?php endif; ?>

						<!-- Genres -->
						<div class="fanfic-form-field">
							<label><?php esc_html_e( 'Genres', 'fanfiction-manager' ); ?></label>
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
							<label for="fanfic_story_status"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></label>
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
								<input
									type="text"
									id="fanfic_fandom_search"
									class="fanfic-input"
									autocomplete="off"
									placeholder="<?php esc_attr_e( 'Search fandoms...', 'fanfiction-manager' ); ?>"
								/>
								<div class="fanfic-fandom-results" role="listbox" aria-label="<?php esc_attr_e( 'Fandom search results', 'fanfiction-manager' ); ?>"></div>
								<div class="fanfic-selected-fandoms" aria-live="polite">
									<?php foreach ( $current_fandom_labels as $fandom ) : ?>
										<span class="fanfic-selected-fandom" data-id="<?php echo esc_attr( $fandom['id'] ); ?>">
											<?php echo esc_html( $fandom['label'] ); ?>
											<button type="button" class="fanfic-remove-fandom" aria-label="<?php esc_attr_e( 'Remove fandom', 'fanfiction-manager' ); ?>">&times;</button>
											<input type="hidden" name="fanfic_story_fandoms[]" value="<?php echo esc_attr( $fandom['id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
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
								// Default to WordPress site language for new stories
								$wp_locale    = get_locale();
								$wp_lang_code = strtolower( substr( $wp_locale, 0, 2 ) );
								$wp_lang      = Fanfic_Languages::get_by_slug( $wp_lang_code );
								if ( $wp_lang && ! empty( $wp_lang['is_active'] ) ) {
									$current_language_id = (int) $wp_lang['id'];
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
							$current_translation_siblings = $is_edit_mode ? Fanfic_Translations::get_translation_siblings( $story_id ) : array();
							?>
							<!-- Translation Links -->
							<div class="fanfic-form-field fanfic-translations-field" data-story-id="<?php echo esc_attr( $story_id ); ?>" data-story-language="<?php echo esc_attr( $current_language_id ); ?>">
								<label for="fanfic_translation_search"><?php esc_html_e( 'Linked Translations', 'fanfiction-manager' ); ?></label>
								<input
									type="text"
									id="fanfic_translation_search"
									class="fanfic-input"
									autocomplete="off"
									placeholder="<?php esc_attr_e( 'Search your stories to link as translation...', 'fanfiction-manager' ); ?>"
								/>
								<div class="fanfic-translation-results" role="listbox" aria-label="<?php esc_attr_e( 'Translation search results', 'fanfiction-manager' ); ?>"></div>
								<div class="fanfic-selected-translations" aria-live="polite">
									<?php foreach ( $current_translation_siblings as $sibling ) : ?>
										<span class="fanfic-selected-translation" data-id="<?php echo esc_attr( $sibling['story_id'] ); ?>">
											<?php echo esc_html( $sibling['title'] . ' - ' . $sibling['language_label'] ); ?>
											<button type="button" class="fanfic-remove-translation" aria-label="<?php esc_attr_e( 'Remove translation link', 'fanfiction-manager' ); ?>">&times;</button>
											<input type="hidden" name="fanfic_story_translations[]" value="<?php echo esc_attr( $sibling['story_id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Link other stories you wrote in different languages as translations of this story.', 'fanfiction-manager' ); ?></p>
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
									<?php else : ?>
										<div class="fanfic-checkboxes fanfic-checkboxes-custom">
											<?php foreach ( $custom_terms as $term ) : ?>
												<?php $is_checked = in_array( (int) $term['id'], $current_term_ids, true ); ?>
												<label class="fanfic-checkbox-label">
													<input type="checkbox" name="fanfic_custom_<?php echo esc_attr( $custom_taxonomy['slug'] ); ?>[]" value="<?php echo esc_attr( $term['id'] ); ?>" <?php checked( $is_checked ); ?>>
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
							<div class="fanfic-checkboxes fanfic-checkboxes-warnings">
								<?php foreach ( $available_warnings as $warning ) : ?>
									<?php
									$is_checked = isset( $_POST['fanfic_story_warnings'] ) ?
										in_array( $warning['id'], (array) $_POST['fanfic_story_warnings'] ) :
										( $is_edit_mode && in_array( $warning['id'], $current_warnings ) );
									$age_class = function_exists( 'fanfic_get_age_badge_class' ) ? fanfic_get_age_badge_class( $warning['min_age'], 'fanfic-warning-age-' ) : 'fanfic-warning-age-18-plus';
									$age_label = function_exists( 'fanfic_get_age_display_label' ) ? fanfic_get_age_display_label( $warning['min_age'], false ) : (string) $warning['min_age'];
									if ( '' === $age_label ) {
										$age_label = (string) $warning['min_age'];
									}
									?>
									<label class="fanfic-checkbox-label fanfic-warning-item <?php echo esc_attr( $age_class ); ?>" title="<?php echo esc_attr( $warning['description'] ); ?>">
										<input
											type="checkbox"
											name="fanfic_story_warnings[]"
											value="<?php echo esc_attr( $warning['id'] ); ?>"
											class="fanfic-checkbox"
											<?php checked( $is_checked ); ?>
										/>
										<span class="fanfic-warning-name"><?php echo esc_html( $warning['name'] ); ?></span>
										<span class="fanfic-warning-age-badge <?php echo esc_attr( $age_class ); ?>"><?php echo esc_html( $age_label ); ?></span>
									</label>
								<?php endforeach; ?>
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
								<label for="fanfic_visible_tags"><?php esc_html_e( 'Visible Tags', 'fanfiction-manager' ); ?></label>
								<input
									type="text"
									id="fanfic_visible_tags"
									name="fanfic_visible_tags"
									class="fanfic-input fanfic-tags-input"
									value="<?php echo esc_attr( $visible_tags_value ); ?>"
									placeholder="<?php esc_attr_e( 'tag1, tag2, tag3', 'fanfiction-manager' ); ?>"
									data-max-tags="<?php echo esc_attr( defined( 'FANFIC_MAX_VISIBLE_TAGS' ) ? FANFIC_MAX_VISIBLE_TAGS : 5 ); ?>"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: %d: Maximum number of visible tags */
										esc_html__( 'Add up to %d visible tags separated by commas. These tags will be displayed on your story page and used for search.', 'fanfiction-manager' ),
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
								<label for="fanfic_invisible_tags"><?php esc_html_e( 'Search Tags (Hidden)', 'fanfiction-manager' ); ?></label>
								<input
									type="text"
									id="fanfic_invisible_tags"
									name="fanfic_invisible_tags"
									class="fanfic-input fanfic-tags-input"
									value="<?php echo esc_attr( $invisible_tags_value ); ?>"
									placeholder="<?php esc_attr_e( 'search term 1, search term 2', 'fanfiction-manager' ); ?>"
									data-max-tags="<?php echo esc_attr( defined( 'FANFIC_MAX_INVISIBLE_TAGS' ) ? FANFIC_MAX_INVISIBLE_TAGS : 10 ); ?>"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: %d: Maximum number of invisible tags */
										esc_html__( 'Add up to %d hidden tags for search indexing only. These tags help readers find your story but are not displayed publicly.', 'fanfiction-manager' ),
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
									<span class="fanfic-dropzone-placeholder-icon" aria-hidden="true">🖼️</span>
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
								<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
							</button>
							<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-button secondary">
								<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
							</button>
							<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button secondary">
								<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
							</a>
						<?php else : ?>
							<!-- EDIT MODE -->
							<?php
							// Check if story has published chapters
							$published_chapter_count = get_posts( array(
								'post_type'      => 'fanfiction_chapter',
								'post_parent'    => $story_id,
								'post_status'    => 'publish',
								'posts_per_page' => 1,
								'fields'         => 'ids',
							) );
							$has_published_chapters = ! empty( $published_chapter_count );
							$current_post_status = get_post_status( $story_id );
							$is_published = 'publish' === $current_post_status;
							?>
							<?php if ( ! $has_published_chapters ) : ?>
								<!-- EDIT MODE - NO PUBLISHED CHAPTERS -->
								<?php
								// Check if story has any chapters at all (draft or published)
								$all_chapter_count = get_posts( array(
									'post_type'      => 'fanfiction_chapter',
									'post_parent'    => $story_id,
									'post_status'    => 'any',
									'posts_per_page' => 1,
									'fields'         => 'ids',
								) );
								$has_any_chapters = ! empty( $all_chapter_count );
								?>
								<?php if ( ! $is_published && $has_any_chapters ) : ?>
									<!-- Story is draft with draft chapters but no published chapters -->
									<button type="submit" name="fanfic_form_action" value="publish" class="fanfic-button" disabled>
										<?php esc_html_e( 'Update and Publish', 'fanfiction-manager' ); ?>
									</button>
									<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-button secondary">
										<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>
									</button>
								<?php else : ?>
									<!-- Story has no chapters yet, or is published but chapters were unpublished -->
									<button type="submit" name="fanfic_form_action" value="add_chapter" class="fanfic-button">
										<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
									</button>
									<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-button secondary">
										<?php esc_html_e( 'Update Draft', 'fanfiction-manager' ); ?>
									</button>
								<?php endif; ?>
							<?php elseif ( ! $is_published ) : ?>
								<!-- EDIT MODE - HAS PUBLISHED CHAPTERS BUT STORY IS DRAFT -->
								<button type="submit" name="fanfic_form_action" value="publish" class="fanfic-button">
									<?php esc_html_e( 'Make Visible', 'fanfiction-manager' ); ?>
								</button>
								<button type="submit" name="fanfic_form_action" value="update" class="fanfic-button secondary" id="update-draft-button" disabled>
									<?php esc_html_e( 'Update Draft', 'fanfiction-manager' ); ?>
								</button>
							<?php else : ?>
								<!-- EDIT MODE - HAS PUBLISHED CHAPTERS AND STORY IS PUBLISHED -->
								<button type="submit" name="fanfic_form_action" value="update" class="fanfic-button" id="update-button" disabled>
									<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>
								</button>
								<button type="submit" name="fanfic_form_action" value="save_draft" class="fanfic-button secondary">
									<?php esc_html_e( 'Draft', 'fanfiction-manager' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( $is_published ) : ?>
								<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" class="fanfic-button secondary" target="_blank" rel="noopener noreferrer" data-fanfic-story-view="1">
									<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
								</a>
							<?php endif; ?>
							<button type="button" id="delete-story-button" class="fanfic-button danger" data-story-id="<?php echo absint( $story_id ); ?>" data-story-title="<?php echo esc_attr( $story_title ); ?>">
								<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
							</button>
						<?php endif; ?>

						<!-- Warning for draft stories with unpublished chapters -->
						<?php if ( $is_edit_mode && ! $has_published_chapters && ! $is_published && ! empty( $all_chapter_count ) ) : ?>
							<div style="margin-top: 12px; padding: 8px 12px; background-color: #fff3cd; border-left: 3px solid #ffc107; font-size: 13px; color: #856404;">
								<?php esc_html_e( 'To make the story visible, you need to publish at least one chapter.', 'fanfiction-manager' ); ?>
							</div>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</section>

		<!-- Chapters Management Section (Edit mode only) -->
		<?php if ( $is_edit_mode ) : ?>
		<section class="fanfic-content-section" class="fanfic-chapters-section" aria-labelledby="chapters-heading">
			<div class="fanfic-section-header">
				<h2 id="chapters-heading"><?php esc_html_e( 'Chapters', 'fanfiction-manager' ); ?></h2>
				<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( 0, $story_id ) ); ?>" class="fanfic-button">
					<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Add Chapter', 'fanfiction-manager' ); ?>
				</a>
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
								<th scope="col"><?php esc_html_e( 'Status', 'fanfiction-manager' ); ?></th>
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
								$status_labels = array(
									'publish' => __( 'Published', 'fanfiction-manager' ),
									'draft'   => __( 'Draft', 'fanfiction-manager' ),
									'pending' => __( 'Pending', 'fanfiction-manager' ),
								);
								$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
								?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Chapter #', 'fanfiction-manager' ); ?>">
										<strong><?php echo esc_html( $display_number ); ?></strong>
									</td>
									<td data-label="<?php esc_attr_e( 'Title', 'fanfiction-manager' ); ?>">
										<?php echo esc_html( $chapter->post_title ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Status', 'fanfiction-manager' ); ?>">
										<span class="fanfic-status-badge fanfic-status-<?php echo esc_attr( $status ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</span>
									</td>
									<td data-label="<?php esc_attr_e( 'Word Count', 'fanfiction-manager' ); ?>">
										<?php echo esc_html( number_format_i18n( $word_count ) ); ?>
									</td>
									<td data-label="<?php esc_attr_e( 'Actions', 'fanfiction-manager' ); ?>">
										<div class="fanfic-actions-buttons">
											<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( $chapter_id ) ); ?>" class="fanfic-button small" aria-label="<?php esc_attr_e( 'Edit chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Edit', 'fanfiction-manager' ); ?>
											</a>
										<?php if ( 'publish' === $status ) : ?>
											<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button small" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'View chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
											</a>
											<button type="button" class="fanfic-button small fanfic-button-warning fanfic-unpublish-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" data-story-id="<?php echo absint( $story_id ); ?>" aria-label="<?php esc_attr_e( 'Unpublish chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Unpublish', 'fanfiction-manager' ); ?>
											</button>
										<?php elseif ( 'draft' === $status ) : ?>
											<button type="button" class="fanfic-button small fanfic-button-primary fanfic-publish-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Publish chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Publish', 'fanfiction-manager' ); ?>
											</button>
										<?php endif; ?>
											<button type="button" class="fanfic-button small danger fanfic-delete-chapter" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( $chapter->post_title ); ?>" aria-label="<?php esc_attr_e( 'Delete chapter', 'fanfiction-manager' ); ?>">
												<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
											</button>
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
						<a href="<?php echo esc_url( fanfic_get_edit_chapter_url( 0, $story_id ) ); ?>" class="fanfic-button-primary">
							<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Add First Chapter', 'fanfiction-manager' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php endif; ?>
	</div>

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
</div>

<!-- Delete Confirmation Modal (Edit mode only) -->
<?php if ( $is_edit_mode ) : ?>
<div id="delete-confirm-modal" class="fanfic-modal" role="dialog" aria-labelledby="modal-title" aria-modal="true" style="display: none;">
	<div class="fanfic-modal-overlay"></div>
	<div class="fanfic-modal-content">
		<h2 id="modal-title"><?php esc_html_e( 'Confirm Deletion', 'fanfiction-manager' ); ?></h2>
		<p id="modal-message"></p>
		<div class="fanfic-modal-actions">
			<button type="button" id="confirm-delete" class="danger">
				<?php esc_html_e( 'Yes, Delete', 'fanfiction-manager' ); ?>
			</button>
			<button type="button" id="cancel-delete" class="secondary">
				<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Make Story Visible Prompt Modal -->
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

<!-- Inline Script for Message Dismissal and Delete Confirmation -->
<script>
(function() {
	// Close button functionality for messages
	document.addEventListener('DOMContentLoaded', function() {
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
				var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
				var languageField = document.getElementById('fanfic_story_language');
				var visibleTagsField = document.getElementById('fanfic_visible_tags');
				var invisibleTagsField = document.getElementById('fanfic_invisible_tags');
				var publishDateField = document.getElementById('fanfic_story_publish_date');

				var currentTitle = titleField ? titleField.value : '';
				var currentContent = contentField ? contentField.value : '';
				var currentStatus = statusField ? statusField.value : '';
				var currentImage = imageField ? imageField.value : '';
				var currentGenres = Array.from(genreCheckboxes).map(function(cb) { return cb.value; }).sort().join(',');
				var currentWarnings = Array.from(warningCheckboxes).map(function(cb) { return cb.value; }).sort().join(',');
				var currentFandoms = Array.from(fandomInputs).map(function(input) { return input.value; }).sort().join(',');
				var currentTranslations = Array.from(translationInputs).map(function(input) { return input.value; }).sort().join(',');
				var currentOriginal = originalCheckbox && originalCheckbox.checked ? '1' : '0';
				var currentLanguage = languageField ? languageField.value : '';
				var currentVisibleTags = visibleTagsField ? visibleTagsField.value : '';
				var currentInvisibleTags = invisibleTagsField ? invisibleTagsField.value : '';
				var currentPublishDate = publishDateField ? publishDateField.value : '';
				var currentCustomTaxonomies = getCustomTaxonomyState(form);
				var originalTitle = form.getAttribute('data-original-title') || '';
				var originalContent = form.getAttribute('data-original-content') || '';
				var originalGenres = form.getAttribute('data-original-genres') || '';
				var originalStatus = form.getAttribute('data-original-status') || '';
				var originalFandoms = form.getAttribute('data-original-fandoms') || '';
				var originalOriginal = form.getAttribute('data-original-original') || '0';
				var originalImage = form.getAttribute('data-original-image') || '';
				var originalWarnings = form.getAttribute('data-original-warnings');
				var originalLanguage = form.getAttribute('data-original-language');
				var originalTranslations = form.getAttribute('data-original-translations');
				var originalVisibleTags = form.getAttribute('data-original-visible-tags');
				var originalInvisibleTags = form.getAttribute('data-original-invisible-tags');
				var originalPublishDate = form.getAttribute('data-original-publish-date');
				var originalCustomTaxonomies = form.getAttribute('data-original-custom-taxonomies');

				if (null === originalWarnings) {
					originalWarnings = currentWarnings;
					form.setAttribute('data-original-warnings', originalWarnings);
				}
				if (null === originalLanguage) {
					originalLanguage = currentLanguage;
					form.setAttribute('data-original-language', originalLanguage);
				}
				if (null === originalTranslations) {
					originalTranslations = currentTranslations;
					form.setAttribute('data-original-translations', originalTranslations);
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
								(currentVisibleTags !== originalVisibleTags) ||
								(currentInvisibleTags !== originalInvisibleTags) ||
								(currentPublishDate !== originalPublishDate) ||
								(currentCustomTaxonomies !== originalCustomTaxonomies);

				var liveUpdateBtn = document.getElementById('update-button');
				var liveUpdateDraftBtn = document.getElementById('update-draft-button');

				if (liveUpdateBtn) {
					liveUpdateBtn.disabled = !hasChanges;
				}
				if (liveUpdateDraftBtn) {
					liveUpdateDraftBtn.disabled = !hasChanges;
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

			// Initial check on page load
			checkForChanges();
		}

		// Delete story confirmation
		var deleteStoryButton = document.getElementById('delete-story-button');

		if (deleteStoryButton) {
			deleteStoryButton.addEventListener('click', function() {
				var storyId = this.getAttribute('data-story-id');
				var storyTitle = this.getAttribute('data-story-title');
				var buttonElement = this;

				var confirmed = confirm(FanficMessages.deleteStory);
				if (confirmed) {
					// Disable button to prevent double-clicks
					buttonElement.disabled = true;
					buttonElement.textContent = FanficMessages.deleting;

					// Prepare AJAX request
					var formData = new FormData();
					formData.append('action', 'fanfic_story_delete');
					formData.append('story_id', storyId);
					formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_story' ); ?>');

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
							// Story deleted successfully - redirect to dashboard
							window.location.href = '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>';
						} else {
							// Re-enable button and show error
							buttonElement.disabled = false;
							buttonElement.textContent = FanficMessages.delete;

							// Build detailed error message
							var errorMessage = data.data && data.data.message ? data.data.message : FanficMessages.errorDeletingStory;
							if (data.data && data.data.errors && data.data.errors.length > 0) {
								errorMessage += '\n\n' + '<?php echo esc_js( __( 'Details:', 'fanfiction-manager' ) ); ?>\n';
								data.data.errors.forEach(function(error) {
									errorMessage += '- ' + error + '\n';
								});
							}
							alert(errorMessage);

							// Log detailed error for debugging
							console.error('Story delete failed:', data);
						}
					})
					.catch(function(error) {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.delete;
						alert(FanficMessages.errorDeletingStory + '\n\n' + '<?php echo esc_js( __( 'Check browser console for details.', 'fanfiction-manager' ) ); ?>');
						console.error('Error deleting story:', error);
					});
				}
			});
		}

		// Chapter unpublish buttons with AJAX
		var chapterUnpublishButtons = document.querySelectorAll('.fanfic-unpublish-chapter');
		chapterUnpublishButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				var chapterTitle = this.getAttribute('data-chapter-title');
				var chapterId = this.getAttribute('data-chapter-id');
				var storyId = this.getAttribute('data-story-id');
				var buttonElement = this;
				var rowElement = buttonElement.closest('tr');

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
					var confirmMessage = '<?php esc_html_e( 'Are you sure you want to unpublish', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?';

					// If this is the last chapter, add warning
					if (checkData.success && checkData.data.is_last_chapter) {
						confirmMessage += '\n\n<?php esc_html_e( 'WARNING: This is your last published chapter/prologue. Unpublishing it will automatically hide your story from readers (Draft status).', 'fanfiction-manager' ); ?>';
					}

					if (!confirm(confirmMessage)) {
						return; // User cancelled
					}

					// User confirmed - proceed with unpublish
					buttonElement.disabled = true;
					buttonElement.textContent = FanficMessages.unpublishing;

					// Prepare AJAX request
					var formData = new FormData();
					formData.append('action', 'fanfic_unpublish_chapter');
					formData.append('chapter_id', chapterId);
					formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_unpublish_chapter' ); ?>');

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
								alert(FanficMessages.unpublishChapterAutoDraftAlert);
							}
							// Reload page to show updated status
							location.reload();
						} else {
							// Re-enable button and show error
							buttonElement.disabled = false;
							buttonElement.textContent = FanficMessages.unpublish;
							alert(data.data.message || FanficMessages.errorUnpublishingChapter);
						}
					})
					.catch(function(error) {
						// Re-enable button and show error
						buttonElement.disabled = false;
						buttonElement.textContent = FanficMessages.unpublish;
						alert(FanficMessages.errorUnpublishingChapter);
						console.error('Error:', error);
					});
				})
				.catch(function(error) {
					console.error('Error checking last chapter:', error);
					// Fall back to simple confirmation
					if (confirm('<?php esc_html_e( 'Are you sure you want to unpublish', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
						// Proceed with unpublish even if check failed
						buttonElement.disabled = true;
						buttonElement.textContent = FanficMessages.unpublishing;

						var formData = new FormData();
						formData.append('action', 'fanfic_unpublish_chapter');
						formData.append('chapter_id', chapterId);
						formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_unpublish_chapter' ); ?>');

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
									alert(FanficMessages.unpublishChapterAutoDraftAlert);
								}
								location.reload();
							} else {
								buttonElement.disabled = false;
								buttonElement.textContent = FanficMessages.unpublish;
								alert(data.data.message || FanficMessages.errorUnpublishingChapter);
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
							alert(errorMessage);
						} else {
							alert(data.data.message || FanficMessages.errorPublishingChapter);
						}
					}
				})
				.catch(function(error) {
					// Re-enable button and show error
					buttonElement.disabled = false;
					buttonElement.textContent = FanficMessages.publish;
					alert(FanficMessages.errorPublishingChapter);
					console.error('Error:', error);
				});
			});
		});

		// Chapter delete buttons with AJAX and last chapter warning
		var chapterDeleteButtons = document.querySelectorAll('[data-chapter-id]');
		chapterDeleteButtons.forEach(function(button) {
			if (button.classList.contains('danger')) {
				button.addEventListener('click', function() {
					var chapterTitle = this.getAttribute('data-chapter-title');
					var chapterId = this.getAttribute('data-chapter-id');
					var buttonElement = this;
					var rowElement = buttonElement.closest('tr');

					// First check if this is the last chapter/prologue
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
						var confirmMessage = '<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?';

						if (isLastChapter) {
							confirmMessage += '\n\n<?php esc_html_e( 'WARNING: This is your last chapter/prologue. Deleting it will automatically set your story to DRAFT status, making it invisible to readers. Epilogues alone are not enough to keep a story published.', 'fanfiction-manager' ); ?>';
						}

						if (confirm(confirmMessage)) {
							// Disable the button to prevent double-clicks
							buttonElement.disabled = true;
							buttonElement.textContent = FanficMessages.deleting;

							// Prepare AJAX request
							var formData = new FormData();
							formData.append('action', 'fanfic_delete_chapter');
							formData.append('chapter_id', chapterId);
							formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

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
									// If story was auto-drafted, reload page to update all button states and warnings
									if (data.data.story_auto_drafted) {
										alert('<?php esc_html_e( 'Chapter deleted. Your story has been set to DRAFT because it no longer has any chapters or prologues.', 'fanfiction-manager' ); ?>');
										window.location.reload();
										return;
									}

									// Add fade-out animation
									rowElement.style.transition = 'opacity 0.5s ease-out';
									rowElement.style.opacity = '0';

									// Remove row after animation completes
									setTimeout(function() {
										rowElement.remove();

										// Check if there are any chapters left
										var tableBody = document.querySelector('.fanfic-chapters-table tbody');
										if (tableBody && tableBody.children.length === 0) {
											// Reload page to show "no chapters" state
											window.location.reload();
										}
									}, 500);
								} else {
									// Re-enable button and show error
									buttonElement.disabled = false;
									buttonElement.textContent = FanficMessages.delete;
									alert(data.data.message || FanficMessages.errorDeletingChapter);
								}
							})
							.catch(function(error) {
								// Re-enable button and show error
								buttonElement.disabled = false;
								buttonElement.textContent = '<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>';
								alert(FanficMessages.errorDeletingChapter);
								console.error('Error:', error);
							});
						}
					})
					.catch(function(error) {
						console.error('Error checking last chapter:', error);
						// Fall back to simple confirmation
						if (confirm('<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"?')) {
							// Proceed with deletion
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
									rowElement.style.transition = 'opacity 0.5s ease-out';
									rowElement.style.opacity = '0';
									setTimeout(function() {
										rowElement.remove();
										var tableBody = document.querySelector('.fanfic-chapters-table tbody');
										if (tableBody && tableBody.children.length === 0) {
											window.location.reload();
										}
									}, 500);
								} else {
									buttonElement.disabled = false;
									buttonElement.textContent = FanficMessages.delete;
									alert(data.data.message || FanficMessages.errorDeletingChapter);
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
			var storyMessagesContainer = document.getElementById('fanfic-messages');

			function showStoryFormMessage(type, message, persistent) {
				if (!message) {
					return;
				}

				if (window.FanficMessages && typeof window.FanficMessages[type] === 'function') {
					if ('error' === type) {
						window.FanficMessages.error(message, { autoDismiss: !persistent });
					} else {
						window.FanficMessages.success(message);
					}
					return;
				}

				if (!storyMessagesContainer) {
					alert(message);
					return;
				}

				var notice = document.createElement('div');
				notice.className = 'fanfic-message ' + ('error' === type ? 'fanfic-message-error' : 'fanfic-message-success');
				notice.setAttribute('role', 'error' === type ? 'alert' : 'status');
				notice.setAttribute('aria-live', 'error' === type ? 'assertive' : 'polite');
				notice.innerHTML = '<span class="fanfic-message-icon" aria-hidden="true">' + ('error' === type ? '&#10007;' : '&#10003;') + '</span><span class="fanfic-message-content"></span><button type="button" class="fanfic-message-close" aria-label="<?php echo esc_attr( __( 'Close message', 'fanfiction-manager' ) ); ?>">&times;</button>';
				notice.querySelector('.fanfic-message-content').textContent = message;
				storyMessagesContainer.appendChild(notice);

				var closeBtn = notice.querySelector('.fanfic-message-close');
				if (closeBtn) {
					closeBtn.addEventListener('click', function() {
						notice.remove();
					});
				}

				if (!persistent && 'error' !== type) {
					setTimeout(function() {
						notice.remove();
					}, 5000);
				}
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
				var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
				var languageField = document.getElementById('fanfic_story_language');
				var visibleTagsField = document.getElementById('fanfic_visible_tags');
				var invisibleTagsField = document.getElementById('fanfic_invisible_tags');
				var publishDateField = document.getElementById('fanfic_story_publish_date');
				var customTaxonomyFields = storyForm.querySelectorAll('[name^="fanfic_custom_"]');

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
				storyForm.setAttribute('data-original-original', originalCheckbox && originalCheckbox.checked ? '1' : '0');
				storyForm.setAttribute('data-original-language', languageField ? languageField.value : '');
				storyForm.setAttribute('data-original-visible-tags', visibleTagsField ? visibleTagsField.value : '');
				storyForm.setAttribute('data-original-invisible-tags', invisibleTagsField ? invisibleTagsField.value : '');
				storyForm.setAttribute('data-original-publish-date', publishDateField ? publishDateField.value : '');
				storyForm.setAttribute('data-original-custom-taxonomies', customTaxonomyState.join('|'));
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

				if (postStatus === 'publish') {
					primaryButton.value = 'update';
					primaryButton.id = 'update-button';
					primaryButton.textContent = '<?php echo esc_js( __( 'Update', 'fanfiction-manager' ) ); ?>';
					primaryButton.disabled = true;

					secondaryButton.value = 'save_draft';
					secondaryButton.removeAttribute('id');
					secondaryButton.textContent = '<?php echo esc_js( __( 'Draft', 'fanfiction-manager' ) ); ?>';
					secondaryButton.disabled = false;

					if (!viewLink && viewUrl) {
						var deleteButton = actionsContainer.querySelector('#delete-story-button');
						viewLink = document.createElement('a');
						viewLink.className = 'fanfic-button secondary';
						viewLink.target = '_blank';
						viewLink.rel = 'noopener noreferrer';
						viewLink.setAttribute('data-fanfic-story-view', '1');
						viewLink.textContent = '<?php echo esc_js( __( 'View', 'fanfiction-manager' ) ); ?>';
						viewLink.href = viewUrl;
						if (deleteButton) {
							actionsContainer.insertBefore(viewLink, deleteButton);
						} else {
							actionsContainer.appendChild(viewLink);
						}
					} else if (viewLink && viewUrl) {
						viewLink.href = viewUrl;
					}
				} else if (postStatus === 'draft') {
					if (primaryButton.value === 'add_chapter') {
						secondaryButton.value = 'save_draft';
						secondaryButton.id = 'update-draft-button';
						secondaryButton.textContent = '<?php echo esc_js( __( 'Update Draft', 'fanfiction-manager' ) ); ?>';
						secondaryButton.disabled = false;
						if (viewLink) {
							viewLink.remove();
						}
						return;
					}

					primaryButton.value = 'publish';
					primaryButton.removeAttribute('id');
					primaryButton.textContent = '<?php echo esc_js( __( 'Make Visible', 'fanfiction-manager' ) ); ?>';
					primaryButton.disabled = false;

					secondaryButton.value = 'update';
					secondaryButton.id = 'update-draft-button';
					secondaryButton.textContent = '<?php echo esc_js( __( 'Update Draft', 'fanfiction-manager' ) ); ?>';
					secondaryButton.disabled = true;

					if (viewLink) {
						viewLink.remove();
					}
				}
			}

			storyForm.addEventListener('submit', function(e) {
				var submitter = e.submitter || document.activeElement;
				var genreCheckboxes = document.querySelectorAll('input[name="fanfic_story_genres[]"]:checked');
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
						// Highlight the field briefly
						var genresContainer = genresLabel.parentElement;
						if (genresContainer) {
							genresContainer.style.border = '2px solid #e74c3c';
							setTimeout(function() {
								genresContainer.style.border = '';
							}, 3000);
						}
					}
					return false;
				}

				e.preventDefault();

				var formData = new FormData(storyForm);
				formData.append('action', 'fanfic_submit_story_form');
				if (submitter && submitter.name && submitter.value) {
					formData.set(submitter.name, submitter.value);
				}

				var allSubmitButtons = storyForm.querySelectorAll('button[type="submit"]');
				var originalButtonLabel = submitter ? submitter.textContent : '';
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

					var statusBadge = document.querySelector('.fanfic-story-status-badge');
					var formHeader = document.querySelector('.fanfic-form-header');
					if (!statusBadge && formHeader) {
						statusBadge = document.createElement('span');
						statusBadge.className = 'fanfic-story-status-badge';
						formHeader.appendChild(statusBadge);
					}
					if (statusBadge && data.status_class && data.status_label) {
						statusBadge.className = 'fanfic-story-status-badge fanfic-status-' + data.status_class;
						statusBadge.textContent = data.status_label;
					}

					if (data.post_status) {
						syncStoryActionButtons(data.post_status, data.edit_url || data.redirect_url || '');
					}

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
					if (submitter) {
						submitter.textContent = originalButtonLabel;
					}
					var updateButton = document.getElementById('update-button');
					var updateDraftButton = document.getElementById('update-draft-button');
					if (updateButton) {
						updateButton.disabled = true;
					}
					if (updateDraftButton) {
						updateDraftButton.disabled = true;
					}
				});
			});
		}
	});
})();
</script>
