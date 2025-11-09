<?php
/**
 * Template: Unified Chapter Form (Create & Edit)
 * Description: Handles both creating and editing fanfiction chapters
 *
 * This template handles:
 * - Creating new chapters (when on story page with ?action=add-chapter)
 * - Editing existing chapters (when on chapter page with ?action=edit)
 * - Form submission logic for both modes
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

	// Generate available numbers (1-100)
	$available_numbers = array();
	for ( $i = 1; $i <= 100; $i++ ) {
		if ( ! in_array( $i, $used_numbers ) || ( $exclude_chapter_id && get_post_meta( $exclude_chapter_id, '_fanfic_chapter_number', true ) == $i ) ) {
			$available_numbers[] = $i;
		}
	}

	return $available_numbers;
}

// ============================================================================
// FORM SUBMISSION HANDLERS
// ============================================================================

/**
 * Handle chapter create submission
 */
if ( isset( $_POST['fanfic_create_chapter_submit'] ) ) {
	$story_id = isset( $_POST['fanfic_story_id'] ) ? absint( $_POST['fanfic_story_id'] ) : 0;

	// Verify nonce
	if ( isset( $_POST['fanfic_create_chapter_nonce'] ) && wp_verify_nonce( $_POST['fanfic_create_chapter_nonce'], 'fanfic_create_chapter_action_' . $story_id ) ) {
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$story = get_post( $story_id );

			// Check permissions
			if ( $story && ( $story->post_author == $current_user->ID || current_user_can( 'edit_others_posts' ) ) ) {
				$errors = array();

				// Get and sanitize form data
				$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
				$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
				$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
				$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
				$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';

				// Validate chapter type restrictions
				if ( 'prologue' === $chapter_type && fanfic_template_story_has_prologue( $story_id ) ) {
					$errors[] = __( 'This story already has a prologue. Only one prologue is allowed per story.', 'fanfiction-manager' );
				}

				if ( 'epilogue' === $chapter_type && fanfic_template_story_has_epilogue( $story_id ) ) {
					$errors[] = __( 'This story already has an epilogue. Only one epilogue is allowed per story.', 'fanfiction-manager' );
				}

				// Auto-calculate chapter number based on type
				$chapter_number = 0;
				if ( 'prologue' === $chapter_type ) {
					$chapter_number = 0; // Prologue is always 0
				} elseif ( 'epilogue' === $chapter_type ) {
					$chapter_number = 1000; // Epilogue starts at 1000
				} else {
					// For regular chapters, get from form input
					$chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
				}

				// Validate
				if ( 'chapter' === $chapter_type && ! $chapter_number ) {
					$errors[] = __( 'Chapter number is required.', 'fanfiction-manager' );
				}

				if ( empty( $title ) ) {
					$errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
				}

				if ( empty( $content ) ) {
					$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
				}

				// If errors, store and redirect back
				if ( ! empty( $errors ) ) {
					set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
					wp_redirect( wp_get_referer() );
					exit;
				}

				// Create chapter
				$chapter_id = wp_insert_post( array(
					'post_type'    => 'fanfiction_chapter',
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => $chapter_status,
					'post_author'  => $current_user->ID,
					'post_parent'  => $story_id,
				) );

				if ( ! is_wp_error( $chapter_id ) ) {
					// Set chapter metadata
					update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
					update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );

					// Check if this is the first published chapter and story is a draft
					$is_first_published_chapter = false;
					if ( 'publish' === $chapter_status && 'draft' === $story->post_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
						// Count published chapters (excluding the one we just created)
						$published_chapters = get_posts( array(
							'post_type'      => 'fanfiction_chapter',
							'post_parent'    => $story_id,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'post__not_in'   => array( $chapter_id ),
						) );

						// If there are no other published chapters, this is the first
						if ( empty( $published_chapters ) ) {
							$is_first_published_chapter = true;
						}
					}

					// Redirect to chapter edit page
					$chapter_url = get_permalink( $chapter_id );
					$edit_url = add_query_arg( 'action', 'edit', $chapter_url );

					// Add parameter to show publication prompt if this is first chapter
					if ( $is_first_published_chapter ) {
						$edit_url = add_query_arg( 'show_publish_prompt', '1', $edit_url );
					}

					wp_redirect( $edit_url );
					exit;
				} else {
					$errors[] = $chapter_id->get_error_message();
					set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
					wp_redirect( wp_get_referer() );
					exit;
				}
			}
		}
	}
}

/**
 * Handle chapter edit submission
 */
if ( isset( $_POST['fanfic_edit_chapter_submit'] ) ) {
	$chapter_id = isset( $_POST['fanfic_chapter_id'] ) ? absint( $_POST['fanfic_chapter_id'] ) : 0;

	// Verify nonce
	if ( isset( $_POST['fanfic_edit_chapter_nonce'] ) && wp_verify_nonce( $_POST['fanfic_edit_chapter_nonce'], 'fanfic_edit_chapter_action_' . $chapter_id ) ) {
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$chapter = get_post( $chapter_id );

			// Check permissions
			if ( $chapter && ( $chapter->post_author == $current_user->ID || current_user_can( 'edit_others_posts' ) ) ) {
				$errors = array();

				// Get and sanitize form data
				$chapter_action = isset( $_POST['fanfic_chapter_action'] ) ? sanitize_text_field( $_POST['fanfic_chapter_action'] ) : 'publish';
				$chapter_status = ( 'draft' === $chapter_action ) ? 'draft' : 'publish';
				$chapter_type = isset( $_POST['fanfic_chapter_type'] ) ? sanitize_text_field( $_POST['fanfic_chapter_type'] ) : 'chapter';
				$title = isset( $_POST['fanfic_chapter_title'] ) ? sanitize_text_field( $_POST['fanfic_chapter_title'] ) : '';
				$content = isset( $_POST['fanfic_chapter_content'] ) ? wp_kses_post( $_POST['fanfic_chapter_content'] ) : '';

				// Get story ID from chapter
				$story_id = $chapter->post_parent;

				// Get current chapter type
				$old_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
				if ( empty( $old_type ) ) {
					$old_type = 'chapter';
				}

				// Validate chapter type restrictions when changing type
				if ( 'prologue' === $chapter_type && 'prologue' !== $old_type && fanfic_template_story_has_prologue( $story_id, $chapter_id ) ) {
					$errors[] = __( 'This story already has a prologue. Only one prologue is allowed per story.', 'fanfiction-manager' );
				}

				if ( 'epilogue' === $chapter_type && 'epilogue' !== $old_type && fanfic_template_story_has_epilogue( $story_id, $chapter_id ) ) {
					$errors[] = __( 'This story already has an epilogue. Only one epilogue is allowed per story.', 'fanfiction-manager' );
				}

				// Auto-calculate chapter number based on type
				$chapter_number = 0;
				if ( 'prologue' === $chapter_type ) {
					// Preserve existing prologue number
					if ( 'prologue' === $old_type ) {
						$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
					} else {
						$chapter_number = 0;
					}
				} elseif ( 'epilogue' === $chapter_type ) {
					// Preserve existing epilogue number
					if ( 'epilogue' === $old_type ) {
						$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
					} else {
						$chapter_number = 1000;
					}
				} else {
					// For regular chapters, get from form input
					$chapter_number = isset( $_POST['fanfic_chapter_number'] ) ? absint( $_POST['fanfic_chapter_number'] ) : 0;
				}

				// Validate
				if ( 'chapter' === $chapter_type && ! $chapter_number ) {
					$errors[] = __( 'Chapter number is required.', 'fanfiction-manager' );
				}

				if ( empty( $title ) ) {
					$errors[] = __( 'Chapter title is required.', 'fanfiction-manager' );
				}

				if ( empty( $content ) ) {
					$errors[] = __( 'Chapter content is required.', 'fanfiction-manager' );
				}

				// If errors, store and redirect back
				if ( ! empty( $errors ) ) {
					set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
					wp_redirect( wp_get_referer() );
					exit;
				}

				// Update chapter
				$result = wp_update_post( array(
					'ID'           => $chapter_id,
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => $chapter_status,
				) );

				if ( ! is_wp_error( $result ) ) {
					// Update chapter metadata
					update_post_meta( $chapter_id, '_fanfic_chapter_number', $chapter_number );
					update_post_meta( $chapter_id, '_fanfic_chapter_type', $chapter_type );

					// Redirect back with success message
					$redirect_url = add_query_arg(
						array(
							'chapter_id' => $chapter_id,
							'updated'    => 'success',
						),
						wp_get_referer()
					);

					wp_redirect( $redirect_url );
					exit;
				} else {
					$errors[] = $result->get_error_message();
					set_transient( 'fanfic_chapter_errors_' . $current_user->ID, $errors, 60 );
					wp_redirect( wp_get_referer() );
					exit;
				}
			}
		}
	}
}

// ============================================================================
// TEMPLATE DISPLAY LOGIC
// ============================================================================

get_header();

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

.fanfic-button-secondary {
	background-color: #f0f0f0;
	color: #333;
}

.fanfic-button-secondary:hover:not(:disabled) {
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

.fanfic-status-draft {
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
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'You must be logged in to manage chapters.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	get_footer();
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
		<div class="fanfic-error-notice" role="alert" aria-live="assertive">
			<p><?php esc_html_e( 'Chapter not found.', 'fanfiction-manager' ); ?></p>
		</div>
		<?php
		get_footer();
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
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'Invalid context for chapter form.', 'fanfiction-manager' ); ?></p>
	</div>
	<?php
	get_footer();
	return;
}

// Validate story exists
if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'Story not found.', 'fanfiction-manager' ); ?></p>
	</div>
	<?php
	get_footer();
	return;
}

// Check permissions
if ( ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'You do not have permission to manage chapters for this story.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	get_footer();
	return;
}

// Get chapter data for edit mode
$chapter_number = '';
$chapter_type = 'chapter';

if ( $is_edit_mode ) {
	$chapter_number = get_post_meta( $chapter_id, '_fanfic_chapter_number', true );
	$chapter_type = get_post_meta( $chapter_id, '_fanfic_chapter_type', true );
	if ( empty( $chapter_type ) ) {
		$chapter_type = 'chapter';
	}
}

// Get chapter availability data
$available_numbers = fanfic_template_get_available_chapter_numbers( $story_id, $is_edit_mode ? $chapter_id : 0 );
$has_prologue = fanfic_template_story_has_prologue( $story_id, $is_edit_mode ? $chapter_id : 0 );
$has_epilogue = fanfic_template_story_has_epilogue( $story_id, $is_edit_mode ? $chapter_id : 0 );

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

<div class="fanfic-template-wrapper">
<a href="#fanfic-main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'fanfiction-manager' ); ?></a>

<!-- Breadcrumb Navigation -->
<nav class="fanfic-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php echo esc_html( $is_edit_mode ? __( 'Edit Chapter', 'fanfiction-manager' ) : __( 'Add Chapter', 'fanfiction-manager' ) ); ?>
		</li>
	</ol>
</nav>

<!-- Success/Error Messages -->
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'true' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php echo $is_edit_mode ? esc_html__( 'Chapter updated successfully!', 'fanfiction-manager' ) : esc_html__( 'Chapter created successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'success' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php esc_html_e( 'Chapter updated successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( ! empty( $errors ) && is_array( $errors ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<ul>
			<?php foreach ( $errors as $error ) : ?>
				<li><?php echo esc_html( $error ); ?></li>
			<?php endforeach; ?>
		</ul>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<!-- Page Header -->
<header class="fanfic-page-header">
	<h1 class="fanfic-page-title"><?php echo esc_html( $page_title ); ?></h1>
	<p class="fanfic-page-description"><?php echo esc_html( $page_description ); ?></p>
</header>

<!-- Info Box -->
<div class="fanfic-info-box" role="region" aria-label="<?php esc_attr_e( 'Information', 'fanfiction-manager' ); ?>">
	<span class="dashicons dashicons-info" aria-hidden="true"></span>
	<p>
		<?php esc_html_e( 'Chapter content supports plain text, bold, italic, and line breaks. No links or HTML allowed.', 'fanfiction-manager' ); ?>
	</p>
</div>

<!-- Chapter Form Section -->
<section class="fanfic-content-section fanfic-form-section" aria-labelledby="form-heading">
	<h2 id="form-heading"><?php echo esc_html( $is_edit_mode ? __( 'Chapter Details', 'fanfiction-manager' ) : __( 'New Chapter', 'fanfiction-manager' ) ); ?></h2>

	<!-- Chapter Form -->
	<div class="fanfic-form-wrapper fanfic-chapter-form-<?php echo $is_edit_mode ? 'edit' : 'create'; ?>" data-available-chapter-numbers="<?php echo esc_attr( json_encode( $available_numbers ) ); ?>">
		<form method="post" class="fanfic-<?php echo $is_edit_mode ? 'edit' : 'create'; ?>-chapter-form" id="fanfic-chapter-form" novalidate>
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
						<label for="fanfic_chapter_title"><?php esc_html_e( 'Chapter Title', 'fanfiction-manager' ); ?></label>
						<input
							type="text"
							id="fanfic_chapter_title"
							name="fanfic_chapter_title"
							class="fanfic-input"
							value="<?php echo isset( $_POST['fanfic_chapter_title'] ) ? esc_attr( $_POST['fanfic_chapter_title'] ) : ( $is_edit_mode ? esc_attr( $chapter->post_title ) : '' ); ?>"
							required
						/>
						<?php if ( isset( $field_errors['chapter_title'] ) ) : ?>
							<p class="fanfic-field-error"><?php echo esc_html( $field_errors['chapter_title'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Chapter Content -->
				<div class="fanfic-form-field">
					<label><?php esc_html_e( 'Chapter Content', 'fanfiction-manager' ); ?></label>
					<?php
					$editor_content = isset( $_POST['fanfic_chapter_content'] ) ? $_POST['fanfic_chapter_content'] : ( $is_edit_mode ? $chapter->post_content : '' );
					$editor_settings = array(
						'textarea_name' => 'fanfic_chapter_content',
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => array(
							'toolbar1' => 'bold italic underline bullist numlist blockquote undo redo',
							'toolbar2' => '',
							'menubar' => false,
							'statusbar' => false,
						),
					);
					wp_editor( $editor_content, 'fanfic_chapter_content', $editor_settings );
					?>
					<?php if ( isset( $field_errors['chapter_content'] ) ) : ?>
						<p class="fanfic-field-error"><?php echo esc_html( $field_errors['chapter_content'] ); ?></p>
					<?php endif; ?>
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
					<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-btn fanfic-btn-primary">
						<?php esc_html_e( 'Publish Chapter', 'fanfiction-manager' ); ?>
					</button>
					<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-btn fanfic-btn-secondary">
						<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
					</button>
				<?php else : ?>
					<!-- EDIT MODE -->
					<?php if ( 'publish' === $chapter->post_status ) : ?>
						<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-btn fanfic-btn-primary">
							<?php esc_html_e( 'Update & Keep Published', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-btn fanfic-btn-secondary">
							<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-btn fanfic-btn-primary">
							<?php esc_html_e( 'Publish Chapter', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-btn fanfic-btn-secondary">
							<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
						</button>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</form>
	</div>
</section>

<!-- Quick Actions -->
<section class="fanfic-content-section fanfic-quick-actions" aria-labelledby="actions-heading">
	<h2 id="actions-heading"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
	<div class="fanfic-action-buttons">
		<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button-secondary">
			<span class="dashicons dashicons-arrow-left" aria-hidden="true"></span>
			<?php esc_html_e( 'Back to Story', 'fanfiction-manager' ); ?>
		</a>
		<?php if ( $is_edit_mode ) : ?>
			<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button-secondary">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'View Chapter', 'fanfiction-manager' ); ?>
			</a>
		<?php endif; ?>
		<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button-secondary">
			<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
			<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
		</a>
	</div>
</section>

<!-- Danger Zone (only for editing) -->
<?php if ( $is_edit_mode ) : ?>
	<section class="fanfic-content-section fanfic-danger-zone" aria-labelledby="danger-heading">
		<h2 id="danger-heading" class="fanfic-danger-title">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php esc_html_e( 'Danger Zone', 'fanfiction-manager' ); ?>
		</h2>

		<div class="fanfic-danger-content">
			<div class="fanfic-danger-info">
				<h3><?php esc_html_e( 'Delete This Chapter', 'fanfiction-manager' ); ?></h3>
				<p><?php esc_html_e( 'Once you delete a chapter, there is no going back. The chapter and all its content will be permanently removed.', 'fanfiction-manager' ); ?></p>
			</div>
			<button type="button" id="delete-chapter-button" class="fanfic-button-danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( get_the_title( $chapter_id ) ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
				<?php esc_html_e( 'Delete This Chapter', 'fanfiction-manager' ); ?>
			</button>
		</div>

		<p class="fanfic-danger-warning">
			<strong><?php esc_html_e( 'Warning:', 'fanfiction-manager' ); ?></strong>
			<?php esc_html_e( 'This action cannot be undone.', 'fanfiction-manager' ); ?>
		</p>
	</section>

	<!-- Delete Confirmation Modal -->
	<div id="delete-confirm-modal" class="fanfic-modal" role="dialog" aria-labelledby="modal-title" aria-modal="true" style="display: none;">
		<div class="fanfic-modal-overlay"></div>
		<div class="fanfic-modal-content">
			<h2 id="modal-title"><?php esc_html_e( 'Confirm Deletion', 'fanfiction-manager' ); ?></h2>
			<p id="modal-message"></p>
			<div class="fanfic-modal-actions">
				<button type="button" id="confirm-delete" class="fanfic-button-danger">
					<?php esc_html_e( 'Yes, Delete', 'fanfiction-manager' ); ?>
				</button>
				<button type="button" id="cancel-delete" class="fanfic-button-secondary">
					<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Inline Script for Delete Confirmation -->
	<script>
	(function() {
		document.addEventListener('DOMContentLoaded', function() {
			// Close button functionality for notices
			var closeButtons = document.querySelectorAll('.fanfic-notice-close');
			closeButtons.forEach(function(button) {
				button.addEventListener('click', function() {
					var notice = this.closest('.fanfic-success-notice, .fanfic-error-notice');
					if (notice) {
						notice.style.display = 'none';
					}
				});
			});

			// Delete chapter confirmation
			var deleteChapterButton = document.getElementById('delete-chapter-button');
			var modal = document.getElementById('delete-confirm-modal');
			var confirmButton = document.getElementById('confirm-delete');
			var cancelButton = document.getElementById('cancel-delete');
			var modalMessage = document.getElementById('modal-message');

			if (deleteChapterButton) {
				deleteChapterButton.addEventListener('click', function() {
					var chapterTitle = this.getAttribute('data-chapter-title');
					modalMessage.textContent = '<?php esc_html_e( 'Are you sure you want to delete chapter', 'fanfiction-manager' ); ?> "' + chapterTitle + '"? <?php esc_html_e( 'This will be permanently removed.', 'fanfiction-manager' ); ?>';
					modal.style.display = 'flex';
				});
			}

			if (cancelButton) {
				cancelButton.addEventListener('click', function() {
					modal.style.display = 'none';
				});
			}

			if (confirmButton && deleteChapterButton) {
				confirmButton.addEventListener('click', function() {
					var chapterId = deleteChapterButton.getAttribute('data-chapter-id');
					var storyId = deleteChapterButton.getAttribute('data-story-id');
					window.location.href = '<?php echo esc_js( fanfic_get_dashboard_url() ); ?>?action=delete_chapter&chapter_id=' + chapterId + '&story_id=' + storyId + '&_wpnonce=<?php echo esc_js( wp_create_nonce( 'delete_chapter' ) ); ?>';
				});
			}

			// Close modal when clicking overlay
			var modalOverlay = document.querySelector('.fanfic-modal-overlay');
			if (modalOverlay) {
				modalOverlay.addEventListener('click', function() {
					modal.style.display = 'none';
				});
			}
		});
	})();
	</script>
<?php endif; ?>

<!-- Publish Story Prompt Modal -->
<div id="publish-prompt-modal" class="fanfic-modal" role="dialog" aria-labelledby="publish-modal-title" aria-modal="true" style="display: none;">
	<div class="fanfic-modal-overlay"></div>
	<div class="fanfic-modal-content">
		<h2 id="publish-modal-title"><?php esc_html_e( 'Ready to Publish Your Story?', 'fanfiction-manager' ); ?></h2>
		<p><?php esc_html_e( 'Great! Your story now has its first published chapter. Would you like to publish your story to make it visible to readers?', 'fanfiction-manager' ); ?></p>
		<div class="fanfic-modal-actions">
			<button type="button" id="publish-story-now" class="fanfic-button-primary" data-story-id="<?php echo absint( $story_id ); ?>">
				<?php esc_html_e( 'Yes, Publish Story', 'fanfiction-manager' ); ?>
			</button>
			<button type="button" id="keep-as-draft" class="fanfic-button-secondary">
				<?php esc_html_e( 'No, Keep as Draft', 'fanfiction-manager' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Publish Prompt Modal Script -->
<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var publishModal = document.getElementById('publish-prompt-modal');
		var publishNowButton = document.getElementById('publish-story-now');
		var keepDraftButton = document.getElementById('keep-as-draft');

		// Show modal if show_publish_prompt parameter is present
		var urlParams = new URLSearchParams(window.location.search);

		if (urlParams.get('show_publish_prompt') === '1' && publishModal) {
			publishModal.style.display = 'flex';
		}

		// Handle "Keep as Draft" button
		if (keepDraftButton) {
			keepDraftButton.addEventListener('click', function() {
				publishModal.style.display = 'none';
				// Remove the parameter from URL
				urlParams.delete('show_publish_prompt');
				var newUrl = window.location.pathname + window.location.search.replace(/[?&]show_publish_prompt=1/, '').replace(/^&/, '?');
				if (newUrl.endsWith('?')) {
					newUrl = newUrl.slice(0, -1);
				}
				window.history.replaceState({}, '', newUrl);
			});
		}

		// Handle "Publish Story Now" button
		if (publishNowButton) {
			publishNowButton.addEventListener('click', function() {
				var storyId = this.getAttribute('data-story-id');

				// Disable button to prevent double-clicks
				publishNowButton.disabled = true;
				publishNowButton.textContent = '<?php esc_html_e( 'Publishing...', 'fanfiction-manager' ); ?>';

				// Prepare AJAX request
				var formData = new FormData();
				formData.append('action', 'fanfic_publish_story');
				formData.append('story_id', storyId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_publish_story' ); ?>');

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
						// Redirect to Edit Story page
						window.location.href = '<?php echo esc_js( fanfic_get_edit_story_url( $story_id ) ); ?>';
					} else {
						// Re-enable button and show error
						publishNowButton.disabled = false;
						publishNowButton.textContent = '<?php esc_html_e( 'Yes, Publish Story', 'fanfiction-manager' ); ?>';
						alert(data.data.message || '<?php esc_html_e( 'Failed to publish story.', 'fanfiction-manager' ); ?>');
					}
				})
				.catch(function(error) {
					// Re-enable button and show error
					publishNowButton.disabled = false;
					publishNowButton.textContent = '<?php esc_html_e( 'Yes, Publish Story', 'fanfiction-manager' ); ?>';
					alert('<?php esc_html_e( 'An error occurred while publishing the story.', 'fanfiction-manager' ); ?>');
					console.error('Error:', error);
				});
			});
		}

		// Close modal when clicking overlay
		if (publishModal) {
			var overlay = publishModal.querySelector('.fanfic-modal-overlay');
			if (overlay) {
				overlay.addEventListener('click', function() {
					publishModal.style.display = 'none';
				});
			}
		}
	});
})();
</script>

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

		// Close notice buttons
		var closeButtons = document.querySelectorAll('.fanfic-notice-close');
		closeButtons.forEach(function(button) {
			button.addEventListener('click', function() {
				var notice = this.closest('.fanfic-success-notice, .fanfic-error-notice');
				if (notice) {
					notice.style.display = 'none';
				}
			});
		});
	});
})();
</script>

<!-- Breadcrumb Navigation (Bottom) -->
<nav class="fanfic-breadcrumb fanfic-breadcrumb-bottom" aria-label="<?php esc_attr_e( 'Breadcrumb', 'fanfiction-manager' ); ?>">
	<ol class="fanfic-breadcrumb-list">
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>"><?php esc_html_e( 'Dashboard', 'fanfiction-manager' ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item">
			<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>"><?php echo esc_html( $story_title ); ?></a>
		</li>
		<li class="fanfic-breadcrumb-item fanfic-breadcrumb-active" aria-current="page">
			<?php echo esc_html( $is_edit_mode ? __( 'Edit Chapter', 'fanfiction-manager' ) : __( 'Add Chapter', 'fanfiction-manager' ) ); ?>
		</li>
	</ol>
</nav>

</div>

<?php
get_footer();
