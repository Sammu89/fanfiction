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

					// Save chapter image URL if provided
					if ( isset( $_POST['fanfic_chapter_image_url'] ) ) {
						update_post_meta( $chapter_id, '_fanfic_chapter_image_url', esc_url_raw( $_POST['fanfic_chapter_image_url'] ) );
					}

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
						// The prompt is now handled by Flash Messages, no need for URL parameter
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

					// Update chapter image URL
					if ( ! empty( $_POST['fanfic_chapter_image_url'] ) ) {
						update_post_meta( $chapter_id, '_fanfic_chapter_image_url', esc_url_raw( $_POST['fanfic_chapter_image_url'] ) );
					} else {
						delete_post_meta( $chapter_id, '_fanfic_chapter_image_url' );
					}

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
						// The prompt is now handled by Flash Messages, no need for URL parameter
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
<?php
fanfic_render_breadcrumb( 'edit-chapter', array(
	'story_id'      => $story_id,
	'story_title'   => $story_title,
	'chapter_id'    => $chapter_id,
	'chapter_title' => $is_edit_mode && $chapter ? $chapter->post_title : '',
	'is_edit_mode'  => $is_edit_mode,
) );
?>

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

<p class="fanfic-page-description"><?php echo esc_html( $page_description ); ?></p>

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
							<span class="fanfic-dropzone-placeholder-icon" aria-hidden="true">🖼️</span>
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
						<?php esc_html_e( 'Publish Chapter', 'fanfiction-manager' ); ?>
					</button>
					<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-button secondary">
						<?php esc_html_e( 'Save as Draft', 'fanfiction-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button secondary">
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
						<button type="submit" name="fanfic_chapter_action" value="update" class="fanfic-button" id="update-chapter-button" disabled>
							<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" name="fanfic_chapter_action" value="draft" class="fanfic-button secondary" id="unpublish-chapter-button" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
							<?php esc_html_e( 'Unpublish and save as draft', 'fanfiction-manager' ); ?>
						</button>
					<?php else : ?>
						<!-- EDIT MODE - CHAPTER IS DRAFT -->
						<button type="submit" name="fanfic_chapter_action" value="publish" class="fanfic-button">
							<?php esc_html_e( 'Publish Chapter', 'fanfiction-manager' ); ?>
						</button>
						<button type="submit" name="fanfic_chapter_action" value="update" class="fanfic-button secondary" id="update-draft-chapter-button" disabled>
							<?php esc_html_e( 'Update Draft', 'fanfiction-manager' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $is_chapter_published ) : ?>
						<a href="<?php echo esc_url( get_permalink( $chapter_id ) ); ?>" class="fanfic-button secondary" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View', 'fanfiction-manager' ); ?>
						</a>
					<?php endif; ?>
					<button type="button" id="delete-chapter-button" class="fanfic-button danger" data-chapter-id="<?php echo absint( $chapter_id ); ?>" data-chapter-title="<?php echo esc_attr( get_the_title( $chapter_id ) ); ?>" data-story-id="<?php echo absint( $story_id ); ?>">
						<?php esc_html_e( 'Delete', 'fanfiction-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button secondary">
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
									// Show auto-draft alert if story was auto-drafted
									if (data.data.story_auto_drafted) {
										alert(FanficMessages.deleteChapterAutoDraftAlert);
									} else {
										alert(FanficMessages.deleteChapterSuccess);
									}

									// Redirect to story edit page
									window.location.href = '<?php echo esc_js( fanfic_get_edit_story_url( $story_id ) ); ?>';
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
								alert(FanficMessages.errorDeletingChapter + '\n\n<?php echo esc_js( __( 'Check browser console for details.', 'fanfiction-manager' ) ); ?>');
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
						alert(FanficMessages.errorCheckingLastChapter + '\n\n<?php echo esc_js( __( 'Check browser console for details.', 'fanfiction-manager' ) ); ?>');
					});
				});
			}

			// Unpublish chapter confirmation
			var unpublishButton = document.getElementById('unpublish-chapter-button');
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
		var updateBtn = document.getElementById('update-chapter-button');
		var updateDraftBtn = document.getElementById('update-draft-chapter-button');

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
			var tinymceInitialized = false; // Track if TinyMCE has finished initial load

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
				var editorAvailable = false;
				if (typeof tinymce !== 'undefined' && tinymce.get('fanfic_chapter_content')) {
					currentContent = tinymce.get('fanfic_chapter_content').getContent();
					editorAvailable = true;
				} else if (contentField) {
					currentContent = contentField.value;
				}

				// Skip check if TinyMCE hasn't initialized yet and is returning empty content
				// This prevents false positives during initialization
				if (editorAvailable && !tinymceInitialized && originalContent.length > 0 && currentContent.length === 0) {
					return;
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
						// Wait for TinyMCE to fully initialize with content
						e.editor.on('init', function() {
							tinymceInitialized = true;
							// Run initial check now that TinyMCE is ready
							setTimeout(checkForChanges, 100);
						});

						e.editor.on('change keyup paste input NodeChange', function() {
							checkForChanges();
						});
					}
				});

				// If TinyMCE is already initialized
				if (tinymce.get('fanfic_chapter_content')) {
					var editor = tinymce.get('fanfic_chapter_content');

					// Mark as initialized since it's already loaded
					tinymceInitialized = true;

					editor.on('change keyup paste input NodeChange', function() {
						checkForChanges();
					});

					// Run initial check
					setTimeout(checkForChanges, 100);
				}

				// Poll for TinyMCE initialization
				var tinymceCheckInterval = setInterval(function() {
					var editor = tinymce.get('fanfic_chapter_content');
					if (editor && !editor.fanficEventsAttached) {
						// Mark as initialized
						tinymceInitialized = true;

						editor.on('change keyup paste input NodeChange', function() {
							checkForChanges();
						});
						editor.fanficEventsAttached = true;
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

			// Also listen to textarea changes as fallback
			var contentField = document.getElementById('fanfic_chapter_content');
			if (contentField) {
				contentField.addEventListener('input', checkForChanges);
				contentField.addEventListener('change', checkForChanges);
			}

			// Note: Initial checkForChanges() is now called after TinyMCE initialization
			// to prevent race condition where TinyMCE returns empty content before loading
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
			chapterForm.addEventListener('submit', function(e) {
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
					alert('<?php echo esc_js( __( 'Please enter content for your chapter.', 'fanfiction-manager' ) ); ?>');
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
