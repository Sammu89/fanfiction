<?php
/**
 * Template: Unified Chapter Form (Create & Edit)
 * Description: Handles both creating and editing fanfiction chapters
 *
 * This template renders:
 * - New chapter forms (when on story page with ?action=add-chapter)
 * - Edit chapter forms (when on chapter page with ?action=edit)
 * - Chapter type selection (Prologue/Chapter/Epilogue)
 * - Chapter number auto-suggestion
 * - Publish story prompt modal (after first chapter)
 * - Delete confirmation modal (edit mode only)
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if a story already has a prologue
 */
function fanfic_template_story_has_prologue( $story_id, $exclude_chapter_id = 0 ) {
	$args = array(
		'post_type'      => 'fanfiction_chapter',
		'post_parent'    => $story_id,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_fanfic_chapter_type',
				'value' => 'prologue',
			),
		),
	);

	if ( $exclude_chapter_id ) {
		$args['post__not_in'] = array( $exclude_chapter_id );
	}

	$prologues = get_posts( $args );
	return ! empty( $prologues );
}

/**
 * Check if a story already has an epilogue
 */
function fanfic_template_story_has_epilogue( $story_id, $exclude_chapter_id = 0 ) {
	$args = array(
		'post_type'      => 'fanfiction_chapter',
		'post_parent'    => $story_id,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_fanfic_chapter_type',
				'value' => 'epilogue',
			),
		),
	);

	if ( $exclude_chapter_id ) {
		$args['post__not_in'] = array( $exclude_chapter_id );
	}

	$epilogues = get_posts( $args );
	return ! empty( $epilogues );
}

/**
 * Get available chapter numbers for a story
 */
function fanfic_template_get_available_chapter_numbers( $story_id, $exclude_chapter_id = 0 ) {
	// Get existing chapters
	$args = array(
		'post_type'      => 'fanfiction_chapter',
		'post_parent'    => $story_id,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);

	if ( $exclude_chapter_id ) {
		$args['post__not_in'] = array( $exclude_chapter_id );
	}

	$chapters = get_posts( $args );

	// Get used chapter numbers
	$used_numbers = array();
	foreach ( $chapters as $chapter_id ) {
		$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
		if ( $chapter_number ) {
			$used_numbers[] = absint( $chapter_number );
		}
	}

	// Generate available numbers (1-999)
	$available_numbers = array();
	for ( $i = 1; $i <= 999; $i++ ) {
		if ( ! in_array( $i, $used_numbers ) || ( $exclude_chapter_id && get_post_meta( $exclude_chapter_id, '_fanfic_chapter_number', true ) == $i ) ) {
			$available_numbers[] = $i;
		}
	}

	return $available_numbers;
}

// ============================================================================
// TEMPLATE DISPLAY LOGIC
// ============================================================================

?>
<style>
.fanfic-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 9999;
	display: flex;
	align-items: center;
	justify-content: center;
}

.fanfic-modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.6);
	backdrop-filter: blur(5px);
	-webkit-backdrop-filter: blur(5px);
}

.fanfic-modal-content {
	position: relative;
	background: #fff;
	padding: 2rem;
	border-radius: 8px;
	max-width: 500px;
	width: 90%;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
	z-index: 10000;
}

.fanfic-modal-content h2 {
	margin-top: 0;
	margin-bottom: 1rem;
	font-size: 1.5rem;
}

.fanfic-modal-content p {
	margin-bottom: 1.5rem;
	line-height: 1.6;
}

.fanfic-modal-actions {
	display: flex;
	gap: 1rem;
	justify-content: flex-end;
}

.fanfic-modal-actions button {
	padding: 0.75rem 1.5rem;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	font-size: 1rem;
	transition: background-color 0.2s;
}

.fanfic-button-primary {
	background-color: #0073aa;
	color: #fff;
}

.fanfic-button-primary:hover:not(:disabled) {
	background-color: #005a87;
}

.fanfic-button-primary:disabled {
	background-color: #ccc;
	cursor: not-allowed;
}

.secondary {
	background-color: #f0f0f0;
	color: #333;
}

.secondary:hover:not(:disabled) {
	background-color: #ddd;
}

/* Status Badge Styles */
.fanfic-status-badge {
	display: inline-block;
	padding: 0.25rem 0.75rem;
	font-size: 0.875rem;
	font-weight: 600;
	border-radius: 12px;
	margin-left: 0.5rem;
}

.fanfic-status-publish {
	background-color: #4caf50;
	color: #fff;
}

.fanfic-status-hidden {
	background-color: #ff9800;
	color: #fff;
}

.fanfic-status-pending {
	background-color: #2196f3;
	color: #fff;
}

.fanfic-status-private {
	background-color: #9e9e9e;
	color: #fff;
}
</style>

<?php

// ============================================================================
// PERMISSION AND MODE DETECTION
// ============================================================================

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'You must be logged in to manage chapters.', 'fanfiction-manager' ); ?>
			<a href="<?php echo esc_url( wp_login_url( fanfic_get_current_url() ) ); ?>" class="fanfic-button">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</span>
	</div>
	<?php
	return;
}

// Detect mode based on current post type
$is_edit_mode = is_singular( 'fanfiction_chapter' );
$is_create_mode = is_singular( 'fanfiction_story' );

$story_id = 0;
$chapter_id = 0;
$chapter = null;
$story = null;

if ( $is_edit_mode ) {
	// Edit mode - on chapter post with ?action=edit
	$chapter_id = get_the_ID();
	$chapter = get_post( $chapter_id );

	if ( ! $chapter || 'fanfiction_chapter' !== $chapter->post_type ) {
		?>
		<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
			<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
			<span class="fanfic-message-content"><?php esc_html_e( 'Chapter not found.', 'fanfiction-manager' ); ?></span>
		</div>
		<?php
		return;
	}

	$story_id = $chapter->post_parent;
	$story = get_post( $story_id );
} elseif ( $is_create_mode ) {
	// Create mode - on story post with ?action=add-chapter
	$story_id = get_the_ID();
	$story = get_post( $story_id );
} else {
	// Invalid context
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content"><?php esc_html_e( 'Invalid context for chapter form.', 'fanfiction-manager' ); ?></span>
	</div>
	<?php
	return;
}

// Validate story exists
if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content"><?php esc_html_e( 'Story not found.', 'fanfiction-manager' ); ?></span>
	</div>
	<?php
	return;
}

// Check permissions
if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
	?>
	<div class="fanfic-message fanfic-message-error" role="alert" aria-live="assertive">
		<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span>
		<span class="fanfic-message-content">
			<?php esc_html_e( 'You do not have permission to manage chapters for this story.', 'fanfiction-manager' ); ?>
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
				<?php esc_html_e( 'You cannot add or edit chapters while the story is blocked.', 'fanfiction-manager' ); ?>
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

// Get chapter data for edit mode
$chapter_number = '';
$chapter_type = 'chapter';
$chapter_image_url = '';
$can_edit_publish_date = function_exists( 'fanfic_can_edit_publish_date' ) ? fanfic_can_edit_publish_date( get_current_user_id() ) : false;
$chapter_publish_date = current_time( 'Y-m-d' );

if ( $is_edit_mode ) {
	$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
	$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
	$chapter_publish_date = mysql2date( 'Y-m-d', $chapter->post_date, false );
	if ( empty( $chapter_type ) ) {
		$chapter_type = 'chapter';
	}
}
if ( isset( $_POST['fanfic_chapter_publish_date'] ) ) {
	$chapter_publish_date = sanitize_text_field( wp_unslash( $_POST['fanfic_chapter_publish_date'] ) );
}

// Get chapter availability data
$available_numbers = fanfic_template_get_available_chapter_numbers( $story_id, $is_edit_mode ? $chapter_id : 0 );
$has_prologue = fanfic_template_story_has_prologue( $story_id, $is_edit_mode ? $chapter_id : 0 );
$has_epilogue = fanfic_template_story_has_epilogue( $story_id, $is_edit_mode ? $chapter_id : 0 );

// Prepare data attributes for change detection (edit mode only)
$data_attrs = '';
if ( $is_edit_mode ) {
	$chapter_notes_enabled = get_post_meta( $chapter_id, '_fanfic_author_notes_enabled', true );
	if ( '1' !== $chapter_notes_enabled ) {
		$chapter_notes_enabled = '0';
	}
	$chapter_notes_position = get_post_meta( $chapter_id, '_fanfic_author_notes_position', true );
	if ( 'above' !== $chapter_notes_position ) {
		$chapter_notes_position = 'below';
	}
	$chapter_notes_content = get_post_meta( $chapter_id, '_fanfic_author_notes', true );

	$data_attrs = sprintf(
		'data-original-title="%s" data-original-content="%s" data-original-type="%s" data-original-number="%s" data-original-publish-date="%s" data-original-notes-enabled="%s" data-original-notes-position="%s" data-original-notes-content="%s"',
		esc_attr( $chapter->post_title ),
		esc_attr( $chapter->post_content ),
		esc_attr( $chapter_type ),
		esc_attr( $chapter_number ),
		esc_attr( $chapter_publish_date ),
		esc_attr( $chapter_notes_enabled ),
		esc_attr( $chapter_notes_position ),
		esc_attr( $chapter_notes_content )
	);
}

// Pre-fill chapter number for new chapters (create mode)
if ( ! $is_edit_mode && empty( $chapter_number ) && 'chapter' === $chapter_type ) {
	// Get the lowest available chapter number
	if ( ! empty( $available_numbers ) ) {
		$chapter_number = min( $available_numbers );
	} else {
		$chapter_number = 1;
	}
}

// Get error messages
$errors = get_transient( 'fanfic_chapter_errors_' . get_current_user_id() );
if ( $errors ) {
	delete_transient( 'fanfic_chapter_errors_' . get_current_user_id() );
}

// Build error map for field-level validation display
$field_errors = array();
if ( is_array( $errors ) ) {
	foreach ( $errors as $error ) {
		if ( strpos( $error, 'Chapter number' ) !== false ) {
			$field_errors['chapter_number'] = $error;
		} elseif ( strpos( $error, 'Chapter title' ) !== false ) {
			$field_errors['chapter_title'] = $error;
		} elseif ( strpos( $error, 'Chapter content' ) !== false ) {
			$field_errors['chapter_content'] = $error;
		}
	}
}

// Get page title and description
$story_title = $story->post_title;
$page_title = $is_edit_mode
	? sprintf( __( 'Edit Chapter: %s', 'fanfiction-manager' ), get_the_title( $chapter_id ) )
	: __( 'Create Chapter', 'fanfiction-manager' );

$page_description = $is_edit_mode
	? __( 'Update your chapter details below. Changes will be saved immediately.', 'fanfiction-manager' )
	: __( 'Create a new chapter for your story below.', 'fanfiction-manager' );

?>

<!-- Include Warning Modals Template -->
<?php include( plugin_dir_path( __FILE__ ) . 'modal-warnings.php' ); ?>

<!-- Breadcrumb Navigation -->
<?php
fanfic_render_breadcrumb( 'edit-chapter', array(
	'story_id'      => $story_id,
	'story_title'   => $story_title,
	'chapter_id'    => $chapter_id,
	'chapter_title' => $is_edit_mode && $chapter ? $chapter->post_title : '',
	'is_edit_mode'  => $is_edit_mode,
) );
?>

<?php ob_start(); ?>
<!-- Unified Messages Container -->
<div id="fanfic-messages" class="fanfic-messages-container" role="region" aria-label="<?php esc_attr_e( 'System Messages', 'fanfiction-manager' ); ?>" aria-live="polite">
<?php
// Display flash messages
$flash_messages = Fanfic_Flash_Messages::get_messages();
if ( ! empty( $flash_messages ) ) {
	foreach ( $flash_messages as $msg ) {
		$type = esc_attr( $msg['type'] );
		$message = esc_html( $msg['message'] );
		$icon = ( $type === 'success' || $type === 'info' ) ? '&#10003;' : ( $type === 'warning' ? '&#9888;' : '&#10007;' );
		$role = ( $type === 'error' ) ? 'alert' : 'status';

		echo "<div class='fanfic-message fanfic-message-{$type}' role='{$role}'>
				<span class='fanfic-message-icon' aria-hidden='true'>{$icon}</span>
				<span class='fanfic-message-content'>{$message}</span>
				<button class='fanfic-message-close' aria-label='" . esc_attr__( 'Dismiss message', 'fanfiction-manager' ) . "'>&times;</button>
			  </div>";
	}
}

// General form errors from chapter handler.
if ( ! empty( $errors ) && is_array( $errors ) ) {
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

// Validation errors from transient (specific to chapter forms)
$validation_errors_transient = $is_edit_mode ? get_transient( 'fanfic_chapter_validation_errors_' . get_current_user_id() . '_' . $chapter_id ) : false;
if ( $validation_errors_transient && is_array( $validation_errors_transient ) ) {
	delete_transient( 'fanfic_chapter_validation_errors_' . get_current_user_id() . '_' . $chapter_id );
	?>
	<div class="fanfic-message fanfic-message-error" role="alert">
		<span class="fanfic-message-icon" aria-hidden="true">✕</span>
		<span class="fanfic-message-content">
			<p><strong><?php echo esc_html( fanfic_get_validation_error_heading( 'chapter' ) ); ?></strong></p>
			<ul>
				<?php foreach ( $validation_errors_transient as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</span>
		<button class="fanfic-message-close" aria-label="<?php esc_attr_e( 'Dismiss message', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
	<?php
}
?>
</div>
<?php
$fanfic_chapter_messages_markup = ob_get_clean();
if ( ! $is_edit_mode ) {
	echo $fanfic_chapter_messages_markup;
}
?>

<p class="fanfic-page-description"><?php echo esc_html( $page_description ); ?></p>

<!-- Chapter Form Section -->
<section class="fanfic-content-section fanfic-form-section" aria-labelledby="form-heading">
	<h2 id="form-heading"><?php echo esc_html( $is_edit_mode ? __( 'Chapter Details', 'fanfiction-manager' ) : __( 'New Chapter', 'fanfiction-manager' ) ); ?></h2>

	<!-- Chapter Form -->
	<div class="fanfic-form-wrapper fanfic-chapter-form-<?php echo $is_edit_mode ? 'edit' : 'create'; ?>" data-available-chapter-numbers="<?php echo esc_attr( json_encode( $available_numbers ) ); ?>">
		<?php if ( $is_edit_mode ) : ?>
			<div class="fanfic-form-header">
				<?php
				$chapter_post_status = get_post_status( $chapter_id );
				$chapter_badge_class = 'publish' === $chapter_post_status ? 'published' : 'draft';
				$chapter_badge_text  = 'publish' === $chapter_post_status ? __( 'Visible', 'fanfiction-manager' ) : __( 'Hidden', 'fanfiction-manager' );
				?>
				<span class="fanfic-chapter-status-badge fanfic-story-status-badge fanfic-status-<?php echo esc_attr( $chapter_badge_class ); ?>">
					<?php if ( 'publish' !== $chapter_post_status ) : ?>
						<span class="dashicons dashicons-hidden"></span>
					<?php endif; ?>
					<?php echo esc_html( $chapter_badge_text ); ?>
				</span>
			</div>
		<?php endif; ?>
		<form method="post" class="fanfic-<?php echo $is_edit_mode ? 'edit' : 'create'; ?>-chapter-form" id="fanfic-chapter-form" novalidate <?php echo $data_attrs; ?>>
			<div class="fanfic-form-content">
				<?php
				if ( $is_edit_mode ) {
					wp_nonce_field( 'fanfic_edit_chapter_action_' . $chapter_id, 'fanfic_edit_chapter_nonce' );
				} else {
					wp_nonce_field( 'fanfic_create_chapter_action_' . $story_id, 'fanfic_create_chapter_nonce' );
				}
				?>

				<!-- Chapter Type -->
				<div class="fanfic-form-field">
					<label><?php esc_html_e( 'Chapter Type', 'fanfiction-manager' ); ?></label>
					<div class="fanfic-radios">
						<!-- Prologue -->
						<label class="fanfic-radio-label<?php echo $has_prologue && ( ! $is_edit_mode || $chapter_type !== 'prologue' ) ? ' disabled' : ''; ?>">
							<input
								type="radio"
								name="fanfic_chapter_type"
								value="prologue"
								class="fanfic-chapter-type-input"
								<?php checked( isset( $_POST['fanfic_chapter_type'] ) ? $_POST['fanfic_chapter_type'] === 'prologue' : $chapter_type === 'prologue' ); ?>
								<?php disabled( $has_prologue && ( ! $is_edit_mode || $chapter_type !== 'prologue' ) ); ?>
							/>
							<?php esc_html_e( 'Prologue', 'fanfiction-manager' ); ?>
						</label>

						<!-- Regular Chapter -->
						<label class="fanfic-radio-label">
							<input
								type="radio"
								name="fanfic_chapter_type"
								value="chapter"
								class="fanfic-chapter-type-input"
								<?php checked( isset( $_POST['fanfic_chapter_type'] ) ? $_POST['fanfic_chapter_type'] === 'chapter' : $chapter_type === 'chapter' ); ?>
							/>
							<?php esc_html_e( 'Chapter', 'fanfiction-manager' ); ?>
						</label>

						<!-- Epilogue -->
						<label class="fanfic-radio-label<?php echo $has_epilogue && ( ! $is_edit_mode || $chapter_type !== 'epilogue' ) ? ' disabled' : ''; ?>">
							<input
								type="radio"
								name="fanfic_chapter_type"
								value="epilogue"
								class="fanfic-chapter-type-input"
								<?php checked( isset( $_POST['fanfic_chapter_type'] ) ? $_POST['fanfic_chapter_type'] === 'epilogue' : $chapter_type === 'epilogue' ); ?>
								<?php disabled( $has_epilogue && ( ! $is_edit_mode || $chapter_type !== 'epilogue' ) ); ?>
							/>
							<?php esc_html_e( 'Epilogue', 'fanfiction-manager' ); ?>
						</label>
					</div>
				</div>

				<!-- Chapter Number & Title (Row) -->
				<div class="fanfic-form-row" style="display: flex; gap: 15px;">
					<!-- Chapter Number -->
					<div class="fanfic-form-field fanfic-chapter-number-field" data-field-type="number" style="<?php echo ( isset( $_POST['fanfic_chapter_type'] ) ? $_POST['fanfic_chapter_type'] === 'chapter' : $chapter_type === 'chapter' ) ? '' : 'display: none;'; ?>; flex: 0 0 120px;">
						<label for="fanfic_chapter_number"><?php esc_html_e( 'Chapter #', 'fanfiction-manager' ); ?></label>
						<input
							type="number"
							id="fanfic_chapter_number"
							name="fanfic_chapter_number"
							class="fanfic-input fanfic-chapter-number-input"
							value="<?php echo isset( $_POST['fanfic_chapter_number'] ) ? esc_attr( $_POST['fanfic_chapter_number'] ) : esc_attr( $chapter_number ); ?>"
							min="1"
							required
						/>
						<?php if ( isset( $field_errors['chapter_number'] ) ) : ?>
							<p class="fanfic-field-error"><?php echo esc_html( $field_errors['chapter_number'] ); ?></p>
						<?php endif; ?>
					</div>

					<!-- Chapter Title -->
					<div class="fanfic-form-field" style="flex: 1;">
						<label for="fanfic_chapter_title"><?php esc_html_e( 'Chapter Title', 'fanfiction-manager' ); ?> <span class="description"><?php esc_html_e( '(Optional)', 'fanfiction-manager' ); ?></span></label>
						<input
							type="text"
							id="fanfic_chapter_title"
							name="fanfic_chapter_title"
							class="fanfic-input"
							value="<?php echo isset( $_POST['fanfic_chapter_title'] ) ? esc_attr( $_POST['fanfic_chapter_title'] ) : ( $is_edit_mode ? esc_attr( $chapter->post_title ) : '' ); ?>"
							placeholder="<?php esc_attr_e( 'Leave blank for default', 'fanfiction-manager' ); ?>"
						/>
						<?php if ( isset( $field_errors['chapter_title'] ) ) : ?>
							<p class="fanfic-field-error"><?php echo esc_html( $field_errors['chapter_title'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $can_edit_publish_date ) : ?>
					<div class="fanfic-form-field">
						<label for="fanfic_chapter_publish_date"><?php esc_html_e( 'Publication Date', 'fanfiction-manager' ); ?></label>
						<input
							type="date"
							id="fanfic_chapter_publish_date"
							name="fanfic_chapter_publish_date"
							class="fanfic-input"
							max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
							value="<?php echo esc_attr( $chapter_publish_date ); ?>"
						/>
						<p class="description"><?php esc_html_e( 'Use this for importing older chapters. Changing this date does not count as a qualifying update for inactivity status.', 'fanfiction-manager' ); ?></p>
					</div>
				<?php endif; ?>

				<!-- Chapter Image -->
				<div class="fanfic-form-field fanfic-has-dropzone">
					<label for="fanfic_chapter_image_url"><?php esc_html_e( 'Chapter Cover Image', 'fanfiction-manager' ); ?> <span class="description"><?php esc_html_e( '(Optional)', 'fanfiction-manager' ); ?></span></label>

					<!-- Dropzone for WordPress Media Library -->
					<div
						id="fanfic_chapter_image_dropzone"
						class="fanfic-image-dropzone"
						data-target="#fanfic_chapter_image_url"
						data-title="<?php esc_attr_e( 'Select Chapter Cover Image', 'fanfiction-manager' ); ?>"
						role="button"
						tabindex="0"
						aria-label="<?php esc_attr_e( 'Click or drag to upload chapter cover image', 'fanfiction-manager' ); ?>"
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
						id="fanfic_chapter_image_url"
						name="fanfic_chapter_image_url"
						class="fanfic-input"
						value="<?php echo esc_attr( $chapter_image_url ); ?>"
						placeholder="<?php esc_attr_e( 'Image URL will appear here', 'fanfiction-manager' ); ?>"
						aria-label="<?php esc_attr_e( 'Chapter cover image URL', 'fanfiction-manager' ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Select an image from your media library or enter a URL directly.', 'fanfiction-manager' ); ?>
					</p>
				</div>

				<!-- Chapter Content -->
				<div class="fanfic-form-field">
					<label><?php esc_html_e( 'Chapter Content', 'fanfiction-manager' ); ?></label>
					<?php
					$editor_content = isset( $_POST['fanfic_chapter_content'] ) ? $_POST['fanfic_chapter_content'] : ( $is_edit_mode ? $chapter->post_content : '' );
					$editor_settings = array(
						'textarea_name' => 'fanfic_chapter_content',
						'media_buttons' => false,
						'teeny'         => false,
						'quicktags'     => false,
						'tinymce'       => array(
							'toolbar1'  => 'bold italic underline bullist numlist blockquote undo redo',
							'toolbar2'  => '',
							'menubar'   => false,
							'statusbar' => false,
						),
					);
					wp_editor( $editor_content, 'fanfic_chapter_content', $editor_settings );
					?>
					<?php if ( isset( $field_errors['chapter_content'] ) ) : ?>
						<p class="fanfic-field-error"><?php echo esc_html( $field_errors['chapter_content'] ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Author's Notes -->
				<?php
				$notes_enabled  = $is_edit_mode ? get_post_meta( $chapter_id, '_fanfic_author_notes_enabled', true ) : '0';
				$notes_position = $is_edit_mode ? ( get_post_meta( $chapter_id, '_fanfic_author_notes_position', true ) ?: 'below' ) : 'below';
				$notes_content  = $is_edit_mode ? get_post_meta( $chapter_id, '_fanfic_author_notes', true ) : '';
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
							<span><?php esc_html_e( 'the chapter content', 'fanfiction-manager' ); ?></span>
						</div>
						<?php
						wp_editor( $notes_content, 'fanfic_chapter_author_notes', array(
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

				<!-- Enable Comments -->
				<?php
				$chapter_comments = $is_edit_mode ? get_post_meta( $chapter_id, '_fanfic_chapter_comments_enabled', true ) : '1';
				if ( '' === $chapter_comments ) {
					$chapter_comments = '1';
				}
				if ( isset( $_POST['fanfic_chapter_comments_enabled'] ) ) {
					$chapter_comments = '1';
				} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
					$chapter_comments = '0';
				}
				$global_comments = class_exists( 'Fanfic_Settings' ) ? Fanfic_Settings::get_setting( 'enable_comments', true ) : true;
				$story_comments_meta = get_post_meta( $story_id, '_fanfic_comments_enabled', true );
				if ( '' === $story_comments_meta ) {
					$story_comments_meta = '1';
				}
				$parent_disabled = ! $global_comments || '1' !== $story_comments_meta;
				?>
				<div class="fanfic-form-field">
					<label>
						<input type="checkbox" id="fanfic_chapter_comments_enabled" name="fanfic_chapter_comments_enabled" value="1" <?php checked( '1', $chapter_comments ); ?> <?php disabled( $parent_disabled ); ?> />
						<?php esc_html_e( 'Enable Comments', 'fanfiction-manager' ); ?>
					</label>
					<?php if ( $parent_disabled ) : ?>
						<input type="hidden" name="fanfic_chapter_comments_enabled" value="<?php echo esc_attr( $chapter_comments ); ?>" />
					<?php endif; ?>
					<?php if ( ! $global_comments ) : ?>
						<p class="description"><?php esc_html_e( 'Comments are disabled globally by the site administrator.', 'fanfiction-manager' ); ?></p>
					<?php elseif ( '1' !== $story_comments_meta ) : ?>
						<p class="description"><?php esc_html_e( 'Comments are disabled for this story. Enable them in story settings first.', 'fanfiction-manager' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Allow comments on this chapter.', 'fanfiction-manager' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Info Box -->
				<div class="fanfic-message fanfic-message-info" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
					<span class="fanfic-message-icon" aria-hidden="true">&#8505;</span>
					<span class="fanfic-message-content">
						<?php esc_html_e( 'Chapter content supports plain text, bold, italic, and line breaks. No links or HTML allowed.', 'fanfiction-manager' ); ?>
					</span>
				</div>
			</div>

			<!-- Hidden fields -->
			<?php if ( $is_edit_mode ) : ?>
				<input type="hidden" name="fanfic_chapter_id" value="<?php echo esc_attr( $chapter_id ); ?>" />
				<input type="hidden" name="fanfic_edit_chapter_submit" value="1" />
			<?php else : ?>
				<input type="hidden" name="fanfic_create_chapter_submit" value="1" />
			<?php endif; ?>
			<input type="hidden" name="fanfic_story_id" value="<?php echo esc_attr( $story_id ); ?>" />

			<!-- Form Actions -->
			<div class="fanfic-form-actions">
				<?php if ( ! $is_edit_mode ) : ?>
					<!-- CREATE MODE -->
					<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-button">
						<?php esc_html_e( 'Make Visible', 'fanfiction-manager' ); ?>
					</button>
					<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-button secondary">
						<?php esc_html_e( 'Save', 'fanfiction-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button secondary">
						<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
					</a>
				<?php else : ?>
					<!-- EDIT MODE -->
					<?php
					$current_chapter_status = get_post_status( $chapter_id );
					$is_chapter_published   = 'publish' === $current_chapter_status;
					?>

					<!-- Update: saves changes only, disabled until form is dirty -->
					<button type="submit" name="fanfic_chapter_action" value="update" class="fanfic-button" id="update-chapter-button" disabled>
						<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>
					</button>

					<!-- Visibility toggle -->
					<?php if ( $is_chapter_published ) : ?>
						<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-button secondary" id="hide-chapter-button" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
							<?php esc_html_e( 'Hide Chapter', 'fanfiction-manager' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-button secondary" id="make-chapter-visible-button">
							<?php esc_html_e( 'Make Visible', 'fanfiction-manager' ); ?>
						</button>
					<?php endif; ?>

					<!-- View link: only when chapter is visible -->
					<?php if ( $is_chapter_published ) : ?>
						<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button secondary" target="_blank" rel="noopener noreferrer" data-fanfic-chapter-view="1">
							<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
						</a>
					<?php endif; ?>

					<!-- Cancel: always present -->
					<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button secondary">
						<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
					</a>

					<!-- Delete: always present in edit mode -->
					<button type="button" id="delete-chapter-button" class="fanfic-button danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( get_the_title( $chapter_id ) ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
						<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</form>
		<?php if ( $is_edit_mode ) : ?>
			<?php echo $fanfic_chapter_messages_markup; ?>
		<?php endif; ?>
	</div>
</section>

<!-- Quick Actions -->
<section class="fanfic-content-section fanfic-quick-actions" aria-labelledby="actions-heading">
	<h2 id="actions-heading"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
	<div class="fanfic-buttons">
		<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button secondary">
			<span class="dashicons dashicons-arrow-left" aria-hidden="true"></span>
			<?php esc_html_e( 'Back to Story', 'fanfiction-manager' ); ?>
		</a>
		<?php if ( $is_edit_mode ) : ?>
			<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button secondary">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'View Chapter', 'fanfiction-manager' ); ?>
			</a>
		<?php endif; ?>
		<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button secondary">
			<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
			<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
		</a>
	</div>
</section>

	<!-- Inline Script for Delete Confirmation (only for editing) -->
	<?php if ( $is_edit_mode ) : ?>
	<script>
	(function() {
		console.log('Chapter form script IIFE executed');
		document.addEventListener('DOMContentLoaded', function() {
			console.log('DOMContentLoaded fired in chapter form');

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
			var chapterMessagesContainer = document.getElementById('fanfic-messages');

			function scrollChapterMessagesIntoViewTwice() {
				if (!chapterMessagesContainer) {
					return;
				}

				var adminBar = document.getElementById('wpadminbar');
				var topOffset = (adminBar ? adminBar.offsetHeight : 0) + 16;
				var targetY = chapterMessagesContainer.getBoundingClientRect().top + window.pageYOffset - topOffset;
				var scrollTarget = Math.max(targetY, 0);

				window.scrollTo({ top: scrollTarget, behavior: 'smooth' });
				setTimeout(function() {
					window.scrollTo({ top: scrollTarget, behavior: 'smooth' });
				}, 220);
			}

			function appendChapterFormMessage(type, message, persistent) {
				if (!message) {
					return;
				}

				var normalizedType = (type === 'error' || type === 'warning') ? type : 'success';
				if (!chapterMessagesContainer) {
					console[(normalizedType === 'error') ? 'error' : 'log'](message);
					return;
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

				chapterMessagesContainer.appendChild(notice);
				scrollChapterMessagesIntoViewTwice();

				if (!persistent && normalizedType !== 'error') {
					setTimeout(function() {
						notice.remove();
					}, 5000);
				}
			}

			function queueChapterFormMessage(type, message, persistent) {
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
					appendChapterFormMessage(type, message, persistent);
				}
			}

			if (chapterMessagesContainer && chapterMessagesContainer.querySelector('.fanfic-message')) {
				setTimeout(scrollChapterMessagesIntoViewTwice, 60);
			}

			// Delete chapter confirmation with AJAX
			var deleteChapterButton = document.getElementById('delete-chapter-button');
			console.log('Delete button search result:', deleteChapterButton);

			if (deleteChapterButton) {
				console.log('Delete button found! Attaching click handler');
			} else {
				console.warn('Delete button NOT found on page!');
			}

			if (deleteChapterButton) {
				deleteChapterButton.addEventListener('click', function() {
					console.log('Delete button CLICKED!');
					var chapterId = this.getAttribute('data-chapter-id');
					var storyId = this.getAttribute('data-story-id');
					var chapterTitle = this.getAttribute('data-chapter-title');
					var buttonElement = this;
					console.log('Chapter ID:', chapterId, 'Story ID:', storyId);

					// Check if this is the last chapter via AJAX
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
						var confirmMessage = FanficMessages.deleteChapter;

						// If this is the last chapter, add warning about auto-draft
						if (isLastChapter) {
							confirmMessage = FanficMessages.deleteChapterLastWarning + '\n\n' + confirmMessage;
						}

						if (confirm(confirmMessage)) {
							// Disable button to prevent double-clicks
							buttonElement.disabled = true;
							buttonElement.textContent = FanficMessages.deleting;

							// Prepare AJAX request for delete
							var formData = new FormData();
							formData.append('action', 'fanfic_delete_chapter');
							formData.append('chapter_id', chapterId);
							formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_delete_chapter' ); ?>');

							// Send AJAX delete request
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
									// Queue message for the destination page message container.
									if (data.data.story_auto_drafted) {
										queueChapterFormMessage('warning', FanficMessages.deleteChapterAutoDraftAlert, true);
									} else {
										queueChapterFormMessage('success', FanficMessages.deleteChapterSuccess, false);
									}

									// Redirect to story edit page
									window.location.href = '<?php echo esc_js( fanfic_get_edit_story_url( $story_id ) ); ?>';
								} else {
									// Re-enable button and show error
									buttonElement.disabled = false;
									buttonElement.textContent = FanficMessages.delete;
									appendChapterFormMessage('error', data.data.message || FanficMessages.errorDeletingChapter, true);
								}
							})
							.catch(function(error) {
								// Re-enable button and show error
								buttonElement.disabled = false;
								buttonElement.textContent = FanficMessages.delete;
								appendChapterFormMessage('error', FanficMessages.errorDeletingChapter + '\n\n<?php echo esc_js( __( 'Check browser console for details.', 'fanfiction-manager' ) ); ?>', true);
								console.error('Error deleting chapter:', error);
								console.error('Full error details:', {
									error: error,
									message: error.message,
									stack: error.stack
								});
							});
						}
					})
					.catch(function(error) {
						console.error('Error checking last chapter:', error);
						console.error('Full error details:', {
							error: error,
							message: error.message,
							stack: error.stack
						});
						appendChapterFormMessage('error', FanficMessages.errorCheckingLastChapter + '\n\n<?php echo esc_js( __( 'Check browser console for details.', 'fanfiction-manager' ) ); ?>', true);
					});
				});
			}

			// Hide chapter confirmation (delegated so it survives dynamic button state changes)
			var chapterForm = document.getElementById('fanfic-chapter-form');
			if (chapterForm) {
				chapterForm.addEventListener('click', function(e) {
					var hideButton = e.target.closest('#hide-chapter-button');
					if (!hideButton) {
						return;
					}

					if (hideButton.getAttribute('data-hide-confirmed') === '1') {
						hideButton.removeAttribute('data-hide-confirmed');
						return; // Allow the actual submit click
					}

					e.preventDefault(); // Intercept first click and run checks

					var chapterId = hideButton.getAttribute('data-chapter-id');
					if (!chapterId) {
						hideButton.setAttribute('data-hide-confirmed', '1');
						hideButton.click();
						return;
					}

					// Check if this is the last published chapter via AJAX
					var formData = new FormData();
					formData.append('action', 'fanfic_check_last_chapter');
					formData.append('chapter_id', chapterId);
					formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_check_last_chapter' ); ?>');

					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					})
					.then(function(response) {
						return response.json();
					})
					.then(function(data) {
						var shouldProceed = true;
						if (data.success && data.data.is_last_chapter) {
							shouldProceed = confirm(FanficMessages.hideChapterLastWarning);
						}

						if (shouldProceed) {
							hideButton.setAttribute('data-hide-confirmed', '1');
							hideButton.click();
						}
					})
					.catch(function(error) {
						console.error('Error checking last chapter:', error);
						appendChapterFormMessage('error', FanficMessages.errorCheckingLastChapter, true);
					});
				});
			}
		});
	})();
	</script>
<?php endif; ?>

<!-- Chapter Form Change Detection and Button State Management -->
<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var form = document.querySelector('.fanfic-create-chapter-form, .fanfic-edit-chapter-form');
		if (!form) return;

		var chapterTypeInputs = document.querySelectorAll('.fanfic-chapter-type-input');
		var chapterNumberField = document.querySelector('.fanfic-chapter-number-field');
		var chapterNumberInput = document.getElementById('fanfic_chapter_number');
		var formWrapper = document.querySelector('.fanfic-chapter-form-create, .fanfic-chapter-form-edit');

		// Get available chapter numbers from data attribute
		var availableNumbersJson = formWrapper ? formWrapper.getAttribute('data-available-chapter-numbers') : '[]';
		var availableNumbers = [];
		try {
			availableNumbers = JSON.parse(availableNumbersJson);
		} catch(e) {
			availableNumbers = [];
		}

		function toggleChapterNumberField() {
			var selectedType = document.querySelector('.fanfic-chapter-type-input:checked');
			if (selectedType && selectedType.value === 'chapter') {
				chapterNumberField.style.display = '';
				if (chapterNumberInput) {
					chapterNumberInput.removeAttribute('disabled');

					// Auto-fill chapter number if empty and in create mode
					if (!chapterNumberInput.value && availableNumbers.length > 0) {
						var minNumber = Math.min.apply(null, availableNumbers);
						chapterNumberInput.value = minNumber;
					} else if (!chapterNumberInput.value && availableNumbers.length === 0) {
						chapterNumberInput.value = '1';
					}
				}
			} else {
				chapterNumberField.style.display = 'none';
				if (chapterNumberInput) {
					chapterNumberInput.setAttribute('disabled', 'disabled');
					// Clear value when field is hidden (not a regular chapter)
					chapterNumberInput.value = '';
				}
			}
		}

		// Toggle chapter number field on page load
		toggleChapterNumberField();

		// Listen for chapter type changes
		chapterTypeInputs.forEach(function(input) {
			input.addEventListener('change', toggleChapterNumberField);
		});

		// Change detection and AJAX for Update buttons (edit mode only)

		// Handle Update button click via AJAX
		function handleUpdateClick(e, button) {
			e.preventDefault();

			// Disable button and show loading state
			button.disabled = true;
			var originalText = button.textContent;
			button.textContent = '<?php echo esc_js( __( 'Updating...', 'fanfiction-manager' ) ); ?>';

			// Get form data
			var titleField = document.getElementById('fanfic_chapter_title');
			var typeRadios = document.querySelectorAll('.fanfic-chapter-type-input:checked');
			var numberField = document.getElementById('fanfic_chapter_number');
			var publishDateField = document.getElementById('fanfic_chapter_publish_date');

			var chapterTitle = titleField ? titleField.value : '';
			var chapterType = typeRadios.length > 0 ? typeRadios[0].value : '';
			var chapterNumber = (numberField && chapterType === 'chapter') ? numberField.value : '';
			var chapterPublishDate = publishDateField ? publishDateField.value : '';
			var notesEnabledField = document.querySelector('input[name="fanfic_author_notes_enabled"]');
			var notesPositionField = document.querySelector('select[name="fanfic_author_notes_position"]');
			var notesTextarea = document.querySelector('textarea[name="fanfic_author_notes"]');
			var notesEnabled = notesEnabledField && notesEnabledField.checked;
			var notesPosition = notesPositionField ? notesPositionField.value : 'below';

			// Get content from TinyMCE if available
			var chapterContent = '';
			if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
				chapterContent = tinymce.get('fanfic_chapter_content').getContent();
			} else {
				var contentField = document.getElementById('fanfic_chapter_content');
				chapterContent = contentField ? contentField.value : '';
			}

			// Get notes content from TinyMCE if available
			var notesContent = '';
			if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_author_notes')) {
				notesContent = tinymce.get('fanfic_chapter_author_notes').getContent();
			} else if (notesTextarea) {
				notesContent = notesTextarea.value;
			}

			// Prepare form data
			var formData = new FormData();
			formData.append('action', 'fanfic_update_chapter');
			var chapterIdInput = document.querySelector('input[name="fanfic_chapter_id"]');
			formData.append('chapter_id', chapterIdInput ? chapterIdInput.value : '<?php echo absint( $chapter_id ); ?>');
			formData.append('chapter_title', chapterTitle);
			formData.append('chapter_content', chapterContent);
			formData.append('chapter_type', chapterType);
			formData.append('chapter_number', chapterNumber);
			formData.append('chapter_publish_date', chapterPublishDate);
			if (notesEnabled) {
				formData.append('fanfic_author_notes_enabled', '1');
			}
			formData.append('fanfic_author_notes_position', notesPosition);
			formData.append('fanfic_author_notes', notesContent);
			var commentsField = document.getElementById('fanfic_chapter_comments_enabled');
			if (commentsField && !commentsField.disabled && commentsField.checked) {
				formData.append('fanfic_chapter_comments_enabled', '1');
			}
			formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_update_chapter' ); ?>');

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
					// Update succeeded - show success message using unified message system
					if (window.FanficMessages) {
						window.FanficMessages.success(data.data.message);
					} else {
						// Fallback if FanficMessages not available
						var successNotice = document.createElement('div');
						successNotice.className = 'fanfic-message fanfic-message-success';
						successNotice.setAttribute('role', 'status');
						successNotice.setAttribute('aria-live', 'polite');
						successNotice.innerHTML = '<span class="fanfic-message-icon" aria-hidden="true">&#10003;</span><span class="fanfic-message-content">' + data.data.message + '</span><button class="fanfic-message-close" aria-label="Close message">&times;</button>';

						var container = document.getElementById('fanfic-messages');
						if (container) {
							container.appendChild(successNotice);
							var closeBtn = successNotice.querySelector('.fanfic-message-close');
							if (closeBtn) {
								closeBtn.addEventListener('click', function() {
									successNotice.style.opacity = '0';
									setTimeout(function() { successNotice.remove(); }, 300);
								});
							}
							setTimeout(function() {
								successNotice.style.opacity = '0';
								setTimeout(function() { successNotice.remove(); }, 300);
							}, 5000);
						}
					}

					// Update original values to match current values
					form.setAttribute('data-original-title', chapterTitle);
					form.setAttribute('data-original-content', chapterContent);
					form.setAttribute('data-original-type', chapterType);
					form.setAttribute('data-original-number', chapterNumber);
					form.setAttribute('data-original-publish-date', chapterPublishDate);
					form.setAttribute('data-original-notes-enabled', notesEnabled ? '1' : '0');
					form.setAttribute('data-original-notes-position', notesPosition);
					form.setAttribute('data-original-notes-content', notesContent);
					var commentsVal = commentsField && commentsField.checked ? '1' : '0';
					form.setAttribute('data-original-comments-enabled', commentsVal);

					// Re-enable button and disable it again (no changes)
					button.textContent = originalText;
					button.disabled = true;
				} else {
					// Update failed - show error message using unified message system
					var errorMessage = '';
					if (data.data && data.data.errors) {
						errorMessage = data.data.errors.join(', ');
					} else {
						errorMessage = data.data.message || '<?php echo esc_js( __( 'Failed to update chapter.', 'fanfiction-manager' ) ); ?>';
					}

					if (window.FanficMessages) {
						window.FanficMessages.error(errorMessage, { autoDismiss: false });
					} else {
						// Fallback if FanficMessages not available
						var errorNotice = document.createElement('div');
						errorNotice.className = 'fanfic-message fanfic-message-error';
						errorNotice.setAttribute('role', 'alert');
						errorNotice.setAttribute('aria-live', 'assertive');
						errorNotice.innerHTML = '<span class="fanfic-message-icon" aria-hidden="true">&#10007;</span><span class="fanfic-message-content">' + errorMessage + '</span><button class="fanfic-message-close" aria-label="Close message">&times;</button>';

						var container = document.getElementById('fanfic-messages');
						if (container) {
							container.appendChild(errorNotice);
							var closeBtn = errorNotice.querySelector('.fanfic-message-close');
							if (closeBtn) {
								closeBtn.addEventListener('click', function() {
									errorNotice.style.opacity = '0';
									setTimeout(function() { errorNotice.remove(); }, 300);
								});
							}
						}
					}

					// Re-enable button
					button.textContent = originalText;
					button.disabled = false;
				}
			})
			.catch(function(error) {
				console.error('Error updating chapter:', error);
				showChapterFormMessage('error', '<?php echo esc_js( __( 'An error occurred while updating the chapter.', 'fanfiction-manager' ) ); ?>', true);

				// Re-enable button
				button.textContent = originalText;
				button.disabled = false;
			});
		}

		// Handle update button click through delegation.
		if (form) {
			form.addEventListener('click', function(e) {
				var updateButton = e.target.closest('#update-chapter-button');
				if (updateButton) {
					handleUpdateClick(e, updateButton);
				}
			});
		}

		if (form) {
			var tinymceInitialized = false; // Track if TinyMCE has finished initial load

			function checkForChanges() {
				var titleField = document.getElementById('fanfic_chapter_title');
				var contentField = document.getElementById('fanfic_chapter_content');
				var typeRadios = document.querySelectorAll('.fanfic-chapter-type-input:checked');
				var numberField = document.getElementById('fanfic_chapter_number');
				var publishDateField = document.getElementById('fanfic_chapter_publish_date');
				var notesEnabledField = document.querySelector('input[name="fanfic_author_notes_enabled"]');
				var notesPositionField = document.querySelector('select[name="fanfic_author_notes_position"]');
				var notesTextarea = document.querySelector('textarea[name="fanfic_author_notes"]');
				var commentsEnabledField = document.getElementById('fanfic_chapter_comments_enabled');

				var currentTitle = titleField ? titleField.value : '';
				var currentType = typeRadios.length > 0 ? typeRadios[0].value : '';
				var currentNumber = (numberField && numberField.style.display !== 'none') ? numberField.value : '';
				var currentPublishDate = publishDateField ? publishDateField.value : '';
				var currentNotesEnabled = notesEnabledField && notesEnabledField.checked ? '1' : '0';
				var currentNotesPosition = notesPositionField ? notesPositionField.value : 'below';
				var currentCommentsEnabled = commentsEnabledField && commentsEnabledField.checked ? '1' : '0';
				var currentNotesContent = '';
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_author_notes')) {
					currentNotesContent = tinymce.get('fanfic_chapter_author_notes').getContent();
				} else if (notesTextarea) {
					currentNotesContent = notesTextarea.value;
				}

				// Get content from TinyMCE if available, otherwise from textarea
				var currentContent = '';
				var editorAvailable = false;
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
					currentContent = tinymce.get('fanfic_chapter_content').getContent();
					editorAvailable = true;
				} else if (contentField) {
					currentContent = contentField.value;
				}

				// Skip check if TinyMCE hasn't initialized yet and is returning empty content
				// This prevents false positives during initialization
				var originalTitle = form.getAttribute('data-original-title') || '';
				var originalContent = form.getAttribute('data-original-content') || '';
				var originalType = form.getAttribute('data-original-type') || '';
				var originalNumber = form.getAttribute('data-original-number') || '';
				var originalPublishDate = form.getAttribute('data-original-publish-date');
				var originalNotesEnabled = form.getAttribute('data-original-notes-enabled');
				var originalNotesPosition = form.getAttribute('data-original-notes-position');
				var originalNotesContent = form.getAttribute('data-original-notes-content');

				if (null === originalPublishDate) {
					originalPublishDate = currentPublishDate;
					form.setAttribute('data-original-publish-date', originalPublishDate);
				}
				if (null === originalNotesEnabled) {
					originalNotesEnabled = currentNotesEnabled;
					form.setAttribute('data-original-notes-enabled', originalNotesEnabled);
				}
				if (null === originalNotesPosition) {
					originalNotesPosition = currentNotesPosition;
					form.setAttribute('data-original-notes-position', originalNotesPosition);
				}
				if (null === originalNotesContent) {
					originalNotesContent = currentNotesContent;
					form.setAttribute('data-original-notes-content', originalNotesContent);
				}

				var originalCommentsEnabled = form.getAttribute('data-original-comments-enabled');
				if (null === originalCommentsEnabled) {
					originalCommentsEnabled = currentCommentsEnabled;
					form.setAttribute('data-original-comments-enabled', originalCommentsEnabled);
				}

				if (editorAvailable && !tinymceInitialized && originalContent.length > 0 && currentContent.length === 0) {
					return;
				}

				var hasChanges = (currentTitle !== originalTitle) ||
								(currentContent !== originalContent) ||
								(currentType !== originalType) ||
								(currentNumber !== originalNumber) ||
								(currentPublishDate !== originalPublishDate) ||
								(currentNotesEnabled !== originalNotesEnabled) ||
								(currentNotesPosition !== originalNotesPosition) ||
								(currentNotesContent !== originalNotesContent) ||
								(currentCommentsEnabled !== originalCommentsEnabled);

				var liveUpdateBtn = document.getElementById('update-chapter-button');
				if (liveUpdateBtn) {
					liveUpdateBtn.disabled = !hasChanges;
				}
			}

			// Universal field tracking: any field mutation inside the current content
			// section re-checks dirty state, including dynamically added fields.
			var formSection = form.closest('.fanfic-content-section');
			var listenerScope = formSection || form;

			function isTrackedChapterField(target) {
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
				if (!isTrackedChapterField(event.target)) {
					return;
				}
				checkForChanges();
			}

			listenerScope.addEventListener('input', handleUniversalFieldChange);
			listenerScope.addEventListener('change', handleUniversalFieldChange);

			// Listen for TinyMCE changes
			if (typeof tinymce !== 'undefined') {
				function attachChapterEditorEvents(editor) {
					if (!editor || editor.fanficEventsAttached) {
						return;
					}

					if (editor.id === 'fanfic_chapter_content') {
						// Wait for TinyMCE to fully initialize with content
						editor.on('init', function() {
							tinymceInitialized = true;
							// Run initial check now that TinyMCE is ready
							setTimeout(checkForChanges, 100);
						});
					}

					if (editor.id === 'fanfic_chapter_content' || editor.id === 'fanfic_chapter_author_notes') {
						editor.on('change keyup paste input NodeChange', function() {
							checkForChanges();
						});
					}

					editor.fanficEventsAttached = true;
				}

				tinymce.on('AddEditor', function(e) {
					attachChapterEditorEvents(e.editor);
				});

				// If TinyMCE is already initialized
				if (tinymce.get('fanfic_chapter_content')) {
					var editor = tinymce.get('fanfic_chapter_content');
					attachChapterEditorEvents(editor);
					setTimeout(checkForChanges, 100);
				}
				if (tinymce.get('fanfic_chapter_author_notes')) {
					attachChapterEditorEvents(tinymce.get('fanfic_chapter_author_notes'));
				}

				// Poll for TinyMCE initialization
				var tinymceCheckInterval = setInterval(function() {
					var editor = tinymce.get('fanfic_chapter_content');
					if (editor && !editor.fanficEventsAttached) {
						attachChapterEditorEvents(editor);
						clearInterval(tinymceCheckInterval);

						// Run initial check
						setTimeout(checkForChanges, 100);
					}
				}, 500);

				// Clear interval after 10 seconds
				setTimeout(function() {
					clearInterval(tinymceCheckInterval);
				}, 10000);
			}

			// Note: Initial checkForChanges() is now called after TinyMCE initialization
			// to prevent race condition where TinyMCE returns empty content before loading

			window.addEventListener('beforeunload', function(e) {
				var updateBtn = document.getElementById('update-chapter-button');
				var hasUnsaved = (updateBtn && !updateBtn.disabled);
				if (hasUnsaved) {
					e.preventDefault();
				}
			});
		}

		// Close message buttons
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

		// Form validation for chapter content (TinyMCE editor)
		var chapterForm = document.querySelector('.fanfic-create-chapter-form, .fanfic-edit-chapter-form');
		if (chapterForm) {
			var chapterAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			var chapterMessagesContainer = document.getElementById('fanfic-messages');

			function ensureChapterMessagesContainer() {
				if (chapterMessagesContainer) {
					return chapterMessagesContainer;
				}

				chapterMessagesContainer = document.getElementById('fanfic-messages');
				if (chapterMessagesContainer) {
					return chapterMessagesContainer;
				}

				var candidateTarget = chapterForm.closest('.fanfic-content-section') || chapterForm.parentElement || document.body;
				if (!candidateTarget) {
					return null;
				}

				chapterMessagesContainer = document.createElement('div');
				chapterMessagesContainer.id = 'fanfic-messages';
				chapterMessagesContainer.className = 'fanfic-messages-container';
				chapterMessagesContainer.setAttribute('role', 'region');
				chapterMessagesContainer.setAttribute('aria-label', '<?php echo esc_attr( __( 'System Messages', 'fanfiction-manager' ) ); ?>');
				chapterMessagesContainer.setAttribute('aria-live', 'polite');
				candidateTarget.insertBefore(chapterMessagesContainer, candidateTarget.firstChild);

				return chapterMessagesContainer;
			}

			function scrollChapterMessagesIntoViewTwice() {
				var targetContainer = ensureChapterMessagesContainer();
				if (!targetContainer) {
					return;
				}

				var adminBar = document.getElementById('wpadminbar');
				var topOffset = (adminBar ? adminBar.offsetHeight : 0) + 16;
				var targetY = targetContainer.getBoundingClientRect().top + window.pageYOffset - topOffset;
				var scrollTarget = Math.max(targetY, 0);

				window.scrollTo({ top: scrollTarget, behavior: 'smooth' });
				setTimeout(function() {
					window.scrollTo({ top: scrollTarget, behavior: 'smooth' });
				}, 220);
			}

			function showChapterFormMessage(type, message, persistent) {
				if (!message) {
					return;
				}

				if (window.FanficMessages && typeof window.FanficMessages[type] === 'function') {
					if ('error' === type) {
						window.FanficMessages.error(message, { autoDismiss: !persistent });
					} else {
						window.FanficMessages.success(message);
					}
					setTimeout(scrollChapterMessagesIntoViewTwice, 20);
					return;
				}

				var targetContainer = ensureChapterMessagesContainer();
				if (!targetContainer) {
					console.error(message);
					return;
				}

				var notice = document.createElement('div');
				notice.className = 'fanfic-message ' + ('error' === type ? 'fanfic-message-error' : 'fanfic-message-success');
				notice.setAttribute('role', 'error' === type ? 'alert' : 'status');
				notice.setAttribute('aria-live', 'error' === type ? 'assertive' : 'polite');
				notice.innerHTML = '<span class="fanfic-message-icon" aria-hidden="true">' + ('error' === type ? '&#10007;' : '&#10003;') + '</span><span class="fanfic-message-content"></span><button type="button" class="fanfic-message-close" aria-label="<?php echo esc_attr( __( 'Close message', 'fanfiction-manager' ) ); ?>">&times;</button>';
				notice.querySelector('.fanfic-message-content').textContent = message;
				notice.querySelector('.fanfic-message-content').style.whiteSpace = 'pre-line';
				targetContainer.appendChild(notice);
				scrollChapterMessagesIntoViewTwice();

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

			if (chapterMessagesContainer && chapterMessagesContainer.querySelector('.fanfic-message')) {
				setTimeout(scrollChapterMessagesIntoViewTwice, 60);
			}

			function moveChapterFormToEditMode(chapterId, editNonce) {
				var createSubmitInput = chapterForm.querySelector('input[name="fanfic_create_chapter_submit"]');
				if (createSubmitInput) {
					createSubmitInput.remove();
				}

				var editSubmitInput = chapterForm.querySelector('input[name="fanfic_edit_chapter_submit"]');
				if (!editSubmitInput) {
					editSubmitInput = document.createElement('input');
					editSubmitInput.type = 'hidden';
					editSubmitInput.name = 'fanfic_edit_chapter_submit';
					editSubmitInput.value = '1';
					chapterForm.appendChild(editSubmitInput);
				}

				var chapterIdInput = chapterForm.querySelector('input[name="fanfic_chapter_id"]');
				if (!chapterIdInput) {
					chapterIdInput = document.createElement('input');
					chapterIdInput.type = 'hidden';
					chapterIdInput.name = 'fanfic_chapter_id';
					chapterForm.appendChild(chapterIdInput);
				}
				chapterIdInput.value = String(chapterId);

				var createNonceInput = chapterForm.querySelector('input[name="fanfic_create_chapter_nonce"]');
				if (createNonceInput) {
					createNonceInput.name = 'fanfic_edit_chapter_nonce';
				}

				var editNonceInput = chapterForm.querySelector('input[name="fanfic_edit_chapter_nonce"]');
				if (!editNonceInput) {
					editNonceInput = document.createElement('input');
					editNonceInput.type = 'hidden';
					editNonceInput.name = 'fanfic_edit_chapter_nonce';
					chapterForm.appendChild(editNonceInput);
				}
				if (editNonce) {
					editNonceInput.value = editNonce;
				}
			}

			function getChapterViewUrl(editUrl) {
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

			function syncChapterActionButtons(chapterStatus, chapterId, storyId, editUrl) {
				var actionsContainer = chapterForm.querySelector('.fanfic-form-actions');
				if (!actionsContainer) {
					return;
				}

				var submitButtons = actionsContainer.querySelectorAll('button[type="submit"][name="fanfic_chapter_action"]');
				if (submitButtons.length < 2) {
					return;
				}

				var primaryButton = submitButtons[0];
				var secondaryButton = submitButtons[1];
				var viewLink = actionsContainer.querySelector('a[data-fanfic-chapter-view="1"]');
				var chapterViewUrl = getChapterViewUrl(editUrl);

				// Primary always becomes Update (dirty-tracked, disabled until changes)
				primaryButton.value = 'update';
				primaryButton.id = 'update-chapter-button';
				primaryButton.textContent = '<?php echo esc_js( __( 'Update', 'fanfiction-manager' ) ); ?>';
				primaryButton.disabled = true;

				// Update status badge
				var statusBadge = document.querySelector('.fanfic-chapter-status-badge');
				if (statusBadge) {
					if (chapterStatus === 'publish') {
						statusBadge.className = 'fanfic-chapter-status-badge fanfic-story-status-badge fanfic-status-visible';
						statusBadge.textContent = '<?php echo esc_js( __( 'Visible', 'fanfiction-manager' ) ); ?>';
					} else {
						statusBadge.className = 'fanfic-chapter-status-badge fanfic-story-status-badge fanfic-status-hidden';
						statusBadge.innerHTML = '<span class="dashicons dashicons-hidden"></span><?php echo esc_js( __( 'Hidden', 'fanfiction-manager' ) ); ?>';
					}
				}

				if (chapterStatus === 'publish') {
					// Secondary becomes Hide Chapter
					secondaryButton.value = 'draft';
					secondaryButton.id = 'hide-chapter-button';
					secondaryButton.textContent = '<?php echo esc_js( __( 'Hide Chapter', 'fanfiction-manager' ) ); ?>';
					secondaryButton.disabled = false;
					if (chapterId) {
						secondaryButton.setAttribute('data-chapter-id', String(chapterId));
					}
					if (storyId) {
						secondaryButton.setAttribute('data-story-id', String(storyId));
					}

					// Create or update the View link
					if (!viewLink && chapterViewUrl) {
						var cancelLink = actionsContainer.querySelector('a.fanfic-button:not([data-fanfic-chapter-view])');
						viewLink = document.createElement('a');
						viewLink.className = 'fanfic-button secondary';
						viewLink.target = '_blank';
						viewLink.rel = 'noopener noreferrer';
						viewLink.setAttribute('data-fanfic-chapter-view', '1');
						viewLink.textContent = '<?php echo esc_js( __( 'View', 'fanfiction-manager' ) ); ?>';
						viewLink.href = chapterViewUrl;
						if (cancelLink) {
							actionsContainer.insertBefore(viewLink, cancelLink);
						} else {
							actionsContainer.appendChild(viewLink);
						}
					} else if (viewLink && chapterViewUrl) {
						viewLink.href = chapterViewUrl;
					}
				} else {
					// Secondary becomes Make Visible
					secondaryButton.value = 'publish';
					secondaryButton.id = 'make-chapter-visible-button';
					secondaryButton.textContent = '<?php echo esc_js( __( 'Make Visible', 'fanfiction-manager' ) ); ?>';
					secondaryButton.disabled = false;
					secondaryButton.removeAttribute('data-chapter-id');
					secondaryButton.removeAttribute('data-story-id');

					if (viewLink) {
						viewLink.remove();
					}
				}
			}

			chapterForm.addEventListener('submit', function(e) {
				var submitter = e.submitter || document.activeElement;
				if (submitter && submitter.id === 'update-chapter-button') {
					return;
				}

				// Get content from TinyMCE editor
				var editorContent = '';
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
					editorContent = tinymce.get('fanfic_chapter_content').getContent({format: 'text'});
				} else {
					// Fallback to textarea
					var contentField = document.getElementById('fanfic_chapter_content');
					if (contentField) {
						editorContent = contentField.value;
					}
				}

				// Check if content is empty (strip HTML tags and trim)
				var textContent = editorContent.replace(/<[^>]*>/g, '').trim();

				if (textContent.length === 0) {
					e.preventDefault();
					showChapterFormMessage('error', '<?php echo esc_js( __( 'Please enter content for your chapter.', 'fanfiction-manager' ) ); ?>', true);
					// Try to focus the editor
					if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
						tinymce.get('fanfic_chapter_content').focus();
					}
					// Scroll to editor
					var editorContainer = document.getElementById('wp-fanfic_chapter_content-wrap');
					if (editorContainer) {
						editorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
						// Highlight briefly
						editorContainer.style.border = '2px solid #e74c3c';
						setTimeout(function() {
							editorContainer.style.border = '';
						}, 3000);
					}
					return false;
				}

				e.preventDefault();

				var isEditMode = !!chapterForm.querySelector('input[name="fanfic_edit_chapter_submit"]');
				var ajaxAction = isEditMode ? 'fanfic_edit_chapter' : 'fanfic_create_chapter';
				var chapterFormData = new FormData(chapterForm);
				chapterFormData.append('action', ajaxAction);

				if (submitter && submitter.name && submitter.value) {
					chapterFormData.set(submitter.name, submitter.value);
				}

				var allSubmitButtons = chapterForm.querySelectorAll('button[type="submit"]');
				var originalButtonLabel = submitter ? submitter.textContent : '';
				var shouldRestoreSubmitterLabel = true;
				allSubmitButtons.forEach(function(button) {
					button.disabled = true;
				});
				if (submitter) {
					submitter.textContent = '<?php echo esc_js( __( 'Saving...', 'fanfiction-manager' ) ); ?>';
				}

				fetch(chapterAjaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: chapterFormData
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
							errorMessage = data.message || '<?php echo esc_js( __( 'Failed to save chapter. Please try again.', 'fanfiction-manager' ) ); ?>';
						}
						showChapterFormMessage('error', errorMessage, true);
						return;
					}

					showChapterFormMessage('success', data.message || '<?php echo esc_js( __( 'Chapter saved successfully.', 'fanfiction-manager' ) ); ?>', false);

					if (!isEditMode && data.chapter_id) {
						moveChapterFormToEditMode(data.chapter_id, data.edit_nonce || '');
					} else if (isEditMode && data.edit_nonce) {
						var editNonceInput = chapterForm.querySelector('input[name="fanfic_edit_chapter_nonce"]');
						if (editNonceInput) {
							editNonceInput.value = data.edit_nonce;
						}
					}

					var titleField = document.getElementById('fanfic_chapter_title');
					var contentField = document.getElementById('fanfic_chapter_content');
					var typeField = document.querySelector('.fanfic-chapter-type-input:checked');
					var numberField = document.getElementById('fanfic_chapter_number');
					var publishDateField = document.getElementById('fanfic_chapter_publish_date');
					var notesEnabledField = document.querySelector('input[name="fanfic_author_notes_enabled"]');
					var notesPositionField = document.querySelector('select[name="fanfic_author_notes_position"]');
					var notesTextarea = document.querySelector('textarea[name="fanfic_author_notes"]');
					var savedContent = '';
					if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
						savedContent = tinymce.get('fanfic_chapter_content').getContent();
					} else if (contentField) {
						savedContent = contentField.value;
					}
					var savedNotesContent = '';
					if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_author_notes')) {
						savedNotesContent = tinymce.get('fanfic_chapter_author_notes').getContent();
					} else if (notesTextarea) {
						savedNotesContent = notesTextarea.value;
					}

					chapterForm.setAttribute('data-original-title', titleField ? titleField.value : '');
					chapterForm.setAttribute('data-original-content', savedContent);
					chapterForm.setAttribute('data-original-type', typeField ? typeField.value : '');
					chapterForm.setAttribute('data-original-number', numberField ? numberField.value : '');
					chapterForm.setAttribute('data-original-publish-date', publishDateField ? publishDateField.value : '');
					chapterForm.setAttribute('data-original-notes-enabled', notesEnabledField && notesEnabledField.checked ? '1' : '0');
					chapterForm.setAttribute('data-original-notes-position', notesPositionField ? notesPositionField.value : 'below');
					chapterForm.setAttribute('data-original-notes-content', savedNotesContent);

					if (data.chapter_status) {
						var storyIdInput = chapterForm.querySelector('input[name="fanfic_story_id"]');
						var storyId = storyIdInput ? storyIdInput.value : '';
						syncChapterActionButtons(data.chapter_status, data.chapter_id || '', data.story_id || storyId, data.edit_url || data.redirect_url || '');
					}
					shouldRestoreSubmitterLabel = false;

					if (data.edit_url) {
						window.history.replaceState({}, '', data.edit_url);
					} else if (data.redirect_url) {
						window.history.replaceState({}, '', data.redirect_url);
					}

					if (data.story_auto_drafted) {
						showChapterFormMessage('error', '<?php echo esc_js( __( 'Story was automatically set to draft because this was its last published chapter or prologue.', 'fanfiction-manager' ) ); ?>', true);
					}

					if (data.is_first_published_chapter) {
						showChapterFormMessage('success', '<?php echo esc_js( __( 'This is your first published chapter. You can now publish your story.', 'fanfiction-manager' ) ); ?>', false);
					}
				})
				.catch(function(error) {
					console.error('Error saving chapter via AJAX:', error);
					showChapterFormMessage('error', '<?php echo esc_js( __( 'An unexpected error occurred while saving the chapter.', 'fanfiction-manager' ) ); ?>', true);
				})
				.finally(function() {
					allSubmitButtons.forEach(function(button) {
						button.disabled = false;
					});
					if (submitter && shouldRestoreSubmitterLabel) {
						submitter.textContent = originalButtonLabel;
					}
					// Only re-disable the dirty-tracked Update button
					var updateButton = document.getElementById('update-chapter-button');
					if (updateButton) {
						updateButton.disabled = true;
					}
				});
			});
		}
	});
})();
</script>

<!-- Breadcrumb Navigation (Bottom) -->
<?php
fanfic_render_breadcrumb( 'edit-chapter', array(
	'story_id'      => $story_id,
	'story_title'   => $story_title,
	'chapter_id'    => $chapter_id,
	'chapter_title' => $is_edit_mode && $chapter ? $chapter->post_title : '',
	'is_edit_mode'  => $is_edit_mode,
	'position'      => 'bottom',
) );
?>
