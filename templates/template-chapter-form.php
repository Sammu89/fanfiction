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

				// Determine chapter status based on action
				$current_chapter_status = get_post_status( $chapter_id );
				if ( 'draft' === $chapter_action ) {
					$chapter_status = 'draft';
				} elseif ( 'publish' === $chapter_action ) {
					$chapter_status = 'publish';
				} else {
					// 'update' action - keep current status
					$chapter_status = $current_chapter_status;
				}

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

					// Check if this is the first published chapter and story is a draft
					$is_first_published_chapter = false;
					$story = get_post( $story_id );

					// Only check if we're publishing a chapter (not just updating) and story is draft
					if ( 'publish' === $chapter_status && $current_chapter_status !== 'publish' && 'draft' === $story->post_status && in_array( $chapter_type, array( 'prologue', 'chapter' ) ) ) {
						// Count published chapters (excluding the one we just published)
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

					// Redirect back with success message
					$redirect_url = add_query_arg(
						array(
							'chapter_id' => $chapter_id,
							'updated'    => 'success',
						),
						wp_get_referer()
					);

					// Add parameter to show publication prompt if this is first chapter
					if ( $is_first_published_chapter ) {
						$redirect_url = add_query_arg( 'show_publish_prompt', '1', $redirect_url );
					}

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
	return;
}

// Validate story exists
if ( ! $story || 'fanfiction_story' !== $story->post_type ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'Story not found.', 'fanfiction-manager' ); ?></p>
	</div>
	<?php
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

// Prepare data attributes for change detection (edit mode only)
$data_attrs = '';
if ( $is_edit_mode ) {
	$data_attrs = sprintf(
		'data-original-title="%s" data-original-content="%s" data-original-type="%s" data-original-number="%s"',
		esc_attr( $chapter->post_title ),
		esc_attr( $chapter->post_content ),
		esc_attr( $chapter_type ),
		esc_attr( $chapter_number )
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

<p class="fanfic-page-description"><?php echo esc_html( $page_description ); ?></p>

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
					<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-btn fanfic-btn-secondary">
						<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
					</a>
				<?php else : ?>
					<!-- EDIT MODE -->
					<?php
					$current_chapter_status = get_post_status( $chapter_id );
					$is_chapter_published = 'publish' === $current_chapter_status;
					?>
					<?php if ( $is_chapter_published ) : ?>
						<!-- EDIT MODE - CHAPTER IS PUBLISHED -->
						<button type="submit" name="fanfic_chapter_action" value="update" class="fanfic-btn fanfic-btn-primary" id="update-chapter-btn" disabled>
							<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-btn fanfic-btn-secondary" id="unpublish-chapter-btn" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
							<?php esc_html_e( 'Unpublish and save as draft', 'fanfiction-manager' ); ?>
						</button>
					<?php else : ?>
						<!-- EDIT MODE - CHAPTER IS DRAFT -->
						<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-btn fanfic-btn-primary">
							<?php esc_html_e( 'Publish Chapter', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" name="fanfic_chapter_action" value="update" class="fanfic-btn fanfic-btn-secondary" id="update-draft-chapter-btn" disabled>
							<?php esc_html_e( 'Update Draft', 'fanfiction-manager' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $is_chapter_published ) : ?>
						<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-btn fanfic-btn-secondary" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
						</a>
					<?php endif; ?>
					<button type="button" id="delete-chapter-button" class="fanfic-btn fanfic-btn-danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( get_the_title( $chapter_id ) ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
						<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-btn fanfic-btn-secondary">
						<?php esc_html_e( 'Cancel', 'fanfiction-manager' ); ?>
					</a>
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

	<!-- Inline Script for Delete Confirmation (only for editing) -->
	<?php if ( $is_edit_mode ) : ?>
	<script>
	(function() {
		console.log('Chapter form script IIFE executed');
		document.addEventListener('DOMContentLoaded', function() {
			console.log('DOMContentLoaded fired in chapter form');

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

			// Delete chapter confirmation with AJAX
			var deleteChapterButton = document.getElementById('delete-chapter-button');

			if (deleteChapterButton) {
				deleteChapterButton.addEventListener('click', function() {
					var chapterId = this.getAttribute('data-chapter-id');
					var storyId = this.getAttribute('data-story-id');
					var chapterTitle = this.getAttribute('data-chapter-title');
					var buttonElement = this;

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
									// Show auto-draft alert if story was auto-drafted
									if (data.data.story_auto_drafted) {
										alert(FanficMessages.deleteChapterAutoDraftAlert);
									} else {
										alert(FanficMessages.deleteChapterSuccess);
									}

									// Redirect to story edit page
									window.location.href = '<?php echo esc_js( fanfic_get_edit_story_url() ); ?>?story_id=' + storyId;
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
								buttonElement.textContent = FanficMessages.delete;
								alert(FanficMessages.errorDeletingChapter);
								console.error('Error deleting chapter:', error);
							});
						}
					})
					.catch(function(error) {
						console.error('Error checking last chapter:', error);
						alert(FanficMessages.errorCheckingLastChapter);
					});
				});
			}

			// Unpublish chapter confirmation
			var unpublishButton = document.getElementById('unpublish-chapter-btn');
			var chapterForm = document.querySelector('form');
			var unpublishConfirmed = false;

			console.log('Unpublish button found:', unpublishButton);
			console.log('Chapter form found:', chapterForm);

			if (unpublishButton && chapterForm) {
				unpublishButton.addEventListener('click', function(e) {
					console.log('Unpublish button clicked, unpublishConfirmed:', unpublishConfirmed);
					if (unpublishConfirmed) {
						console.log('Already confirmed, allowing submission');
						return; // Allow submission
					}

					console.log('Preventing default, checking if last chapter');
					e.preventDefault(); // Stop form submission

					var chapterId = this.getAttribute('data-chapter-id');
					var storyId = this.getAttribute('data-story-id');
					console.log('Chapter ID:', chapterId, 'Story ID:', storyId);

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
						console.log('AJAX response received:', data);
						if (data.success && data.data.is_last_chapter) {
							// This is the last chapter, show confirmation
							console.log('This IS the last chapter, showing confirmation');
							var confirmed = confirm(FanficMessages.unpublishChapterLastWarning);

							if (confirmed) {
								console.log('User confirmed, submitting form');
								unpublishConfirmed = true;
								unpublishButton.click(); // Trigger the button click again
							} else {
								console.log('User cancelled');
							}
						} else {
							// Not the last chapter, proceed normally
							console.log('NOT the last chapter, proceeding without confirmation');
							unpublishConfirmed = true;
							unpublishButton.click();
						}
					})
					.catch(function(error) {
						console.error('Error checking last chapter:', error);
						alert(FanficMessages.errorCheckingLastChapter);
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
		var updateBtn = document.getElementById('update-chapter-btn');
		var updateDraftBtn = document.getElementById('update-draft-chapter-btn');

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

			var chapterTitle = titleField ? titleField.value : '';
			var chapterType = typeRadios.length > 0 ? typeRadios[0].value : '';
			var chapterNumber = (numberField && chapterType === 'chapter') ? numberField.value : '';

			// Get content from TinyMCE if available
			var chapterContent = '';
			if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
				chapterContent = tinymce.get('fanfic_chapter_content').getContent();
			} else {
				var contentField = document.getElementById('fanfic_chapter_content');
				chapterContent = contentField ? contentField.value : '';
			}

			// Prepare form data
			var formData = new FormData();
			formData.append('action', 'fanfic_update_chapter');
			formData.append('chapter_id', '<?php echo absint( $chapter_id ); ?>');
			formData.append('chapter_title', chapterTitle);
			formData.append('chapter_content', chapterContent);
			formData.append('chapter_type', chapterType);
			formData.append('chapter_number', chapterNumber);
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
					// Update succeeded - show success message and update original values
					var successNotice = document.createElement('div');
					successNotice.className = 'fanfic-success-notice';
					successNotice.setAttribute('role', 'status');
					successNotice.setAttribute('aria-live', 'polite');
					successNotice.innerHTML = '<p>' + data.data.message + '</p><button class="fanfic-notice-close" aria-label="Close notice">&times;</button>';

					// Insert notice at top of form
					var formSection = document.querySelector('.fanfic-form-section');
					if (formSection) {
						formSection.insertBefore(successNotice, formSection.firstChild);

						// Add close button handler
						var closeBtn = successNotice.querySelector('.fanfic-notice-close');
						if (closeBtn) {
							closeBtn.addEventListener('click', function() {
								successNotice.style.display = 'none';
							});
						}

						// Auto-hide after 5 seconds
						setTimeout(function() {
							successNotice.style.display = 'none';
						}, 5000);
					}

					// Update original values to match current values
					form.setAttribute('data-original-title', chapterTitle);
					form.setAttribute('data-original-content', chapterContent);
					form.setAttribute('data-original-type', chapterType);
					form.setAttribute('data-original-number', chapterNumber);

					// Re-enable button and disable it again (no changes)
					button.textContent = originalText;
					button.disabled = true;
				} else {
					// Update failed - show error message
					var errorNotice = document.createElement('div');
					errorNotice.className = 'fanfic-error-notice';
					errorNotice.setAttribute('role', 'alert');
					errorNotice.setAttribute('aria-live', 'assertive');

					var errorHtml = '<ul>';
					if (data.data && data.data.errors) {
						data.data.errors.forEach(function(error) {
							errorHtml += '<li>' + error + '</li>';
						});
					} else {
						errorHtml += '<li>' + (data.data.message || '<?php echo esc_js( __( 'Failed to update chapter.', 'fanfiction-manager' ) ); ?>') + '</li>';
					}
					errorHtml += '</ul><button class="fanfic-notice-close" aria-label="Close notice">&times;</button>';
					errorNotice.innerHTML = errorHtml;

					// Insert notice at top of form
					var formSection = document.querySelector('.fanfic-form-section');
					if (formSection) {
						formSection.insertBefore(errorNotice, formSection.firstChild);

						// Add close button handler
						var closeBtn = errorNotice.querySelector('.fanfic-notice-close');
						if (closeBtn) {
							closeBtn.addEventListener('click', function() {
								errorNotice.style.display = 'none';
							});
						}
					}

					// Re-enable button
					button.textContent = originalText;
					button.disabled = false;
				}
			})
			.catch(function(error) {
				console.error('Error updating chapter:', error);
				alert('<?php echo esc_js( __( 'An error occurred while updating the chapter.', 'fanfiction-manager' ) ); ?>');

				// Re-enable button
				button.textContent = originalText;
				button.disabled = false;
			});
		}

		// Attach click handlers to Update buttons
		if (updateBtn) {
			updateBtn.addEventListener('click', function(e) {
				handleUpdateClick(e, this);
			});
		}

		if (updateDraftBtn) {
			updateDraftBtn.addEventListener('click', function(e) {
				handleUpdateClick(e, this);
			});
		}

		if (form && (updateBtn || updateDraftBtn)) {
			var originalTitle = form.getAttribute('data-original-title') || '';
			var originalContent = form.getAttribute('data-original-content') || '';
			var originalType = form.getAttribute('data-original-type') || '';
			var originalNumber = form.getAttribute('data-original-number') || '';

			function checkForChanges() {
				var titleField = document.getElementById('fanfic_chapter_title');
				var contentField = document.getElementById('fanfic_chapter_content');
				var typeRadios = document.querySelectorAll('.fanfic-chapter-type-input:checked');
				var numberField = document.getElementById('fanfic_chapter_number');

				var currentTitle = titleField ? titleField.value : '';
				var currentType = typeRadios.length > 0 ? typeRadios[0].value : '';
				var currentNumber = (numberField && numberField.style.display !== 'none') ? numberField.value : '';

				// Get content from TinyMCE if available, otherwise from textarea
				var currentContent = '';
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
					currentContent = tinymce.get('fanfic_chapter_content').getContent();
				} else if (contentField) {
					currentContent = contentField.value;
				}

				var hasChanges = (currentTitle !== originalTitle) ||
								(currentContent !== originalContent) ||
								(currentType !== originalType) ||
								(currentNumber !== originalNumber);

				if (updateBtn) {
					updateBtn.disabled = !hasChanges;
				}
				if (updateDraftBtn) {
					updateDraftBtn.disabled = !hasChanges;
				}
			}

			// Attach event listeners
			var titleField = document.getElementById('fanfic_chapter_title');
			var numberField = document.getElementById('fanfic_chapter_number');

			if (titleField) titleField.addEventListener('input', checkForChanges);
			if (numberField) numberField.addEventListener('input', checkForChanges);

			chapterTypeInputs.forEach(function(input) {
				input.addEventListener('change', checkForChanges);
			});

			// Listen for TinyMCE changes
			if (typeof tinymce !== 'undefined') {
				tinymce.on('AddEditor', function(e) {
					if (e.editor.id === 'fanfic_chapter_content') {
						e.editor.on('change keyup paste input NodeChange', checkForChanges);
					}
				});

				// If TinyMCE is already initialized
				if (tinymce.get('fanfic_chapter_content')) {
					tinymce.get('fanfic_chapter_content').on('change keyup paste input NodeChange', checkForChanges);
				}

				// Poll for TinyMCE initialization
				var tinymceCheckInterval = setInterval(function() {
					var editor = tinymce.get('fanfic_chapter_content');
					if (editor && !editor.fanficEventsAttached) {
						editor.on('change keyup paste input NodeChange', checkForChanges);
						editor.fanficEventsAttached = true;
						clearInterval(tinymceCheckInterval);
					}
				}, 500);

				// Clear interval after 10 seconds
				setTimeout(function() {
					clearInterval(tinymceCheckInterval);
				}, 10000);
			}

			// Also listen to textarea changes as fallback
			var contentField = document.getElementById('fanfic_chapter_content');
			if (contentField) {
				contentField.addEventListener('input', checkForChanges);
				contentField.addEventListener('change', checkForChanges);
			}

			// Initial check on page load
			setTimeout(checkForChanges, 100);
			setTimeout(checkForChanges, 1000); // Check again after 1 second
		}

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
