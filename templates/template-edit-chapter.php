<?php
/**
 * Template Name: Edit Chapter
 * Description: Form for creating and editing fanfiction chapters
 *
 * This template displays:
 * - Chapter create/edit form
 * - Chapter preview section
 * - Delete chapter option (danger zone)
 *
 * @package Fanfiction_Manager
 * @since 1.0.0
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

error_log( '=== EDIT CHAPTER TEMPLATE DEBUG START ===' );
error_log( 'Template loaded for URL: ' . $_SERVER['REQUEST_URI'] );
error_log( 'Current User ID: ' . get_current_user_id() );
error_log( 'Is user logged in: ' . ( is_user_logged_in() ? 'YES' : 'NO' ) );

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
error_log( 'After style section' );

// Check if user is logged in
if ( ! is_user_logged_in() ) {
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'You must be logged in to edit chapters.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Log In', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

// Get story ID and chapter ID from current post or URL parameters (for backwards compatibility)
$story_id = 0;
$chapter_id = 0;

error_log( 'Checking post type...' );
error_log( 'is_singular(fanfiction_story): ' . ( is_singular( 'fanfiction_story' ) ? 'YES' : 'NO' ) );
error_log( 'is_singular(fanfiction_chapter): ' . ( is_singular( 'fanfiction_chapter' ) ? 'YES' : 'NO' ) );
error_log( 'Current post ID: ' . get_the_ID() );

// Check if we're on a story post with ?action=add-chapter
if ( is_singular( 'fanfiction_story' ) ) {
	$story_id = get_the_ID();
	$chapter_id = 0; // Creating new chapter
	error_log( 'Creating new chapter for story ID: ' . $story_id );
}
// Check if we're on a chapter post with ?action=edit
elseif ( is_singular( 'fanfiction_chapter' ) ) {
	$chapter_id = get_the_ID();
	$chapter_post = get_post( $chapter_id );
	$story_id = $chapter_post ? $chapter_post->post_parent : 0;
	error_log( 'Editing chapter ID: ' . $chapter_id . ', Story ID: ' . $story_id );
}

// Fallback to URL parameters for backwards compatibility
if ( ! $story_id && isset( $_GET['story_id'] ) ) {
	$story_id = absint( $_GET['story_id'] );
	error_log( 'Story ID from URL parameter: ' . $story_id );
}
if ( ! $chapter_id && isset( $_GET['chapter_id'] ) ) {
	$chapter_id = absint( $_GET['chapter_id'] );
	error_log( 'Chapter ID from URL parameter: ' . $chapter_id );
}

error_log( 'Final Story ID: ' . $story_id . ', Chapter ID: ' . $chapter_id );
error_log( 'Checking permissions for story ID: ' . $story_id );
error_log( 'Can edit story: ' . ( current_user_can( 'edit_fanfiction_story', $story_id ) ? 'YES' : 'NO' ) );

// Validate story exists and user has permission
if ( ! $story_id || ! current_user_can( 'edit_fanfiction_story', $story_id ) ) {
	error_log( 'ACCESS DENIED: story_id=' . $story_id . ', can_edit=' . ( current_user_can( 'edit_fanfiction_story', $story_id ) ? 'YES' : 'NO' ) );
	?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php esc_html_e( 'Access Denied: You do not have permission to add chapters to this story, or the story does not exist.', 'fanfiction-manager' ); ?></p>
		<p>
			<a href="<?php echo esc_url( fanfic_get_dashboard_url() ); ?>" class="fanfic-button fanfic-button-primary">
				<?php esc_html_e( 'Back to Dashboard', 'fanfiction-manager' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

// If editing a chapter, validate it belongs to this story
if ( $chapter_id ) {
	$chapter = get_post( $chapter_id );
	if ( ! $chapter || $chapter->post_type !== 'fanfiction_chapter' || (int) $chapter->post_parent !== $story_id ) {
		?>
		<div class="fanfic-error-notice" role="alert" aria-live="assertive">
			<p><?php esc_html_e( 'Chapter not found or does not belong to this story.', 'fanfiction-manager' ); ?></p>
			<p>
				<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button fanfic-button-primary">
					<?php esc_html_e( 'Back to Story', 'fanfiction-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
		return;
	}
}

// Get story and chapter titles for breadcrumb
$story = get_post( $story_id );
$story_title = $story ? $story->post_title : __( 'Unknown Story', 'fanfiction-manager' );

$page_title = $chapter_id
	? sprintf( __( 'Edit Chapter: %s', 'fanfiction-manager' ), get_the_title( $chapter_id ) )
	: __( 'Create Chapter', 'fanfiction-manager' );

$page_description = $chapter_id
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
			<?php echo esc_html( $chapter_id ? __( 'Edit Chapter', 'fanfiction-manager' ) : __( 'Add Chapter', 'fanfiction-manager' ) ); ?>
		</li>
	</ol>
</nav>

<!-- Success/Error Messages -->
<?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'true' ) : ?>
	<div class="fanfic-success-notice" role="status" aria-live="polite">
		<p><?php echo $chapter_id ? esc_html__( 'Chapter updated successfully!', 'fanfiction-manager' ) : esc_html__( 'Chapter created successfully!', 'fanfiction-manager' ); ?></p>
		<button class="fanfic-notice-close" aria-label="<?php esc_attr_e( 'Close notice', 'fanfiction-manager' ); ?>">&times;</button>
	</div>
<?php endif; ?>

<?php if ( isset( $_GET['error'] ) ) : ?>
	<div class="fanfic-error-notice" role="alert" aria-live="assertive">
		<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
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
<section class="fanfic-content-section" class="fanfic-form-section" aria-labelledby="form-heading">
	<h2 id="form-heading"><?php echo esc_html( $chapter_id ? __( 'Chapter Details', 'fanfiction-manager' ) : __( 'New Chapter', 'fanfiction-manager' ) ); ?></h2>

	<!-- Chapter Form -->
	<?php
	if ( $chapter_id ) {
		// Edit mode - pass chapter_id
		echo Fanfic_Shortcodes_Author_Forms::render_chapter_form( $chapter_id );
	} else {
		// Create mode - pass story_id as second parameter
		echo Fanfic_Shortcodes_Author_Forms::render_chapter_form( 0, $story_id );
	}
	?>
</section>

<!-- Quick Actions -->
<section class="fanfic-content-section" class="fanfic-quick-actions" aria-labelledby="actions-heading">
	<h2 id="actions-heading"><?php esc_html_e( 'Quick Actions', 'fanfiction-manager' ); ?></h2>
	<div class="fanfic-action-buttons">
		<a href="<?php echo esc_url( fanfic_get_edit_story_url( $story_id ) ); ?>" class="fanfic-button-secondary">
			<span class="dashicons dashicons-arrow-left" aria-hidden="true"></span>
			<?php esc_html_e( 'Back to Story', 'fanfiction-manager' ); ?>
		</a>
		<?php if ( $chapter_id ) : ?>
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
<?php if ( $chapter_id ) : ?>
	<section class="fanfic-content-section" class="fanfic-danger-zone" aria-labelledby="danger-heading">
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
					modal.style.display = 'block';
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
		console.log('URL Params:', window.location.search);
		console.log('show_publish_prompt value:', urlParams.get('show_publish_prompt'));

		if (urlParams.get('show_publish_prompt') === '1' && publishModal) {
			console.log('Showing publish prompt modal');
			publishModal.style.display = 'block';
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
			<?php echo esc_html( $chapter_id ? __( 'Edit Chapter', 'fanfiction-manager' ) : __( 'Add Chapter', 'fanfiction-manager' ) ); ?>
		</li>
	</ol>
</nav>

</div>


<!-- Chapter Form Change Detection and Button State Management -->
<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var form = document.querySelector('.fanfic-create-chapter-form, .fanfic-edit-chapter-form');
		if (!form) return;

		var publishBtn = form.querySelector('button[name="fanfic_chapter_action"][value="publish"]');
		var draftBtn = form.querySelector('button[name="fanfic_chapter_action"][value="draft"]');
		
		if (!publishBtn) return;

		// Store original values
		var originalValues = {};
		var inputs = form.querySelectorAll('input[type="text"], input[type="number"], textarea, select, input[type="radio"]:checked');
		inputs.forEach(function(input) {
			if (input.type === 'radio') {
				originalValues[input.name] = input.value;
			} else {
				originalValues[input.name] = input.value;
			}
		});

		// Function to check if form has changes
		function hasChanges() {
			var changed = false;
			inputs = form.querySelectorAll('input[type="text"], input[type="number"], textarea, select, input[type="radio"]:checked');
			inputs.forEach(function(input) {
				var currentValue;
				if (input.type === 'radio') {
					currentValue = input.value;
				} else {
					currentValue = input.value;
				}
				
				if (originalValues[input.name] !== currentValue) {
					changed = true;
				}
			});
			return changed;
		}

		// Function to update button states
		function updateButtonStates() {
			var changed = hasChanges();
			
			// Save as Draft button is ALWAYS enabled (users can unpublish)
			if (draftBtn) {
				draftBtn.disabled = false;
			}

			if (changed) {
				publishBtn.disabled = false;
				publishBtn.textContent = '<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>';
			} else {
				publishBtn.disabled = true;
				publishBtn.textContent = '<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>';
				if (draftBtn) {
					draftBtn.disabled = true;
				}
			}
		}

		// Update button states on initial load (for edit form)
		if (form.classList.contains('fanfic-edit-chapter-form')) {
			// On edit page, check if chapter is published
			var chapterStatus = '<?php echo isset($chapter) ? $chapter->post_status : ""; ?>';
			if (chapterStatus === 'publish') {
				publishBtn.textContent = '<?php esc_html_e( 'Update', 'fanfiction-manager' ); ?>';
			}
			updateButtonStates();
		}

		// Listen for changes
		form.addEventListener('input', updateButtonStates);
		form.addEventListener('change', updateButtonStates);
	});
})();
</script>

