<?php
/**
 * Modal Warnings Template
 *
 * Reusable template for displaying inline warning modals across different pages.
 * This template provides a flexible warning box that can be shown/hidden via JavaScript.
 *
 * Usage:
 * - Include this file in templates that need warning modals
 * - Use the warning box HTML with ID: fanfic-{context}-auto-draft-warning
 * - Trigger display with: element.classList.add('show')
 *
 * Example:
 * include( get_theme_file_path( 'templates/modal-warnings.php' ) );
 *
 * @package Fanfiction_Manager
 * @since 1.0.8
 */

// Security check - prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get validation error heading message
 *
 * Centralized function for validation error messages to ensure consistency
 * and translatability across all forms.
 *
 * @since 1.0.9
 * @param string $type Type of validation error ('story' or 'chapter').
 * @return string Translated validation error heading.
 */
function fanfic_get_validation_error_heading( $type = 'story' ) {
	if ( 'chapter' === $type ) {
		return __( 'Chapter cannot be published due to the following issues:', 'fanfiction-manager' );
	}
	return __( 'Story cannot be published due to the following issues:', 'fanfiction-manager' );
}

/**
 * Inline CSS for all warning modals
 * This is output once and applies to all warning boxes on the page
 */
?>
<style>
	/* Auto-Draft Warning Container */
	.fanfic-auto-draft-warning-container {
		display: none;
		margin: 20px 0;
		border: 2px solid #e74c3c;
		border-radius: 8px;
		background-color: #fef5f5;
		overflow: hidden;
	}

	.fanfic-auto-draft-warning-container.show {
		display: block !important;
		animation: slideDown 0.3s ease-out;
	}

	@keyframes slideDown {
		from {
			opacity: 0;
			transform: translateY(-10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.fanfic-auto-draft-warning-box {
		padding: 20px;
	}

	.fanfic-auto-draft-warning-header {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 15px;
		position: relative;
	}

	.fanfic-auto-draft-warning-header .dashicons {
		color: #e74c3c;
		font-size: 24px;
		width: 24px;
		height: 24px;
		flex-shrink: 0;
	}

	.fanfic-auto-draft-warning-header h3 {
		margin: 0;
		color: #e74c3c;
		font-size: 18px;
		flex: 1;
	}

	.fanfic-auto-draft-warning-close {
		background: none;
		border: none;
		cursor: pointer;
		padding: 5px;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #666;
		transition: color 0.2s;
		flex-shrink: 0;
	}

	.fanfic-auto-draft-warning-close:hover {
		color: #e74c3c;
	}

	.fanfic-auto-draft-warning-close .dashicons {
		font-size: 20px;
		width: 20px;
		height: 20px;
	}

	.fanfic-auto-draft-warning-body {
		color: #333;
		line-height: 1.6;
	}

	.fanfic-auto-draft-warning-body p {
		margin: 10px 0;
	}

	.fanfic-auto-draft-warning-body p:first-child {
		margin-top: 0;
	}

	.fanfic-auto-draft-warning-body p:last-child {
		margin-bottom: 0;
	}
</style>

<?php
/**
 * Story Form Auto-Draft Warning
 * Shows when a story is auto-drafted from unpublishing a chapter on the story edit form
 */
?>
<div id="fanfic-story-auto-draft-warning" class="fanfic-auto-draft-warning-container">
	<div class="fanfic-auto-draft-warning-box">
		<div class="fanfic-auto-draft-warning-header">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Story Automatically Drafted', 'fanfiction-manager' ); ?></h3>
			<button type="button" class="fanfic-auto-draft-warning-close" data-close-target="fanfic-story-auto-draft-warning" aria-label="<?php esc_attr_e( 'Close warning', 'fanfiction-manager' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
		<div class="fanfic-auto-draft-warning-body">
			<p><?php esc_html_e( 'Your story has been automatically moved to draft status because it no longer has any published chapters.', 'fanfiction-manager' ); ?></p>
			<p>
				<strong><?php esc_html_e( 'Story: ', 'fanfiction-manager' ); ?></strong>
				<span id="fanfic-story-warning-title"></span>
			</p>
			<p><?php esc_html_e( 'To make your story visible to readers again, publish at least one chapter or prologue.', 'fanfiction-manager' ); ?></p>
		</div>
	</div>
</div>

<?php
/**
 * Chapter Form Auto-Draft Warning
 * Shows when a story is auto-drafted from unpublishing a chapter on the chapter edit form
 */
?>
<div id="fanfic-chapter-auto-draft-warning" class="fanfic-auto-draft-warning-container">
	<div class="fanfic-auto-draft-warning-box">
		<div class="fanfic-auto-draft-warning-header">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Story Automatically Drafted', 'fanfiction-manager' ); ?></h3>
			<button type="button" class="fanfic-auto-draft-warning-close" data-close-target="fanfic-chapter-auto-draft-warning" aria-label="<?php esc_attr_e( 'Close warning', 'fanfiction-manager' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
		<div class="fanfic-auto-draft-warning-body">
			<p><?php esc_html_e( 'Your story has been automatically moved to draft status because it no longer has any published chapters.', 'fanfiction-manager' ); ?></p>

			<p><?php esc_html_e( 'To make your story visible to readers again, you need to publish this chapter or another chapter/prologue.', 'fanfiction-manager' ); ?></p>
		</div>
	</div>
</div>

<?php
/**
 * Publish Story Prompt Modal (Shared across story and chapter forms)
 * Shows when a story becomes publishable after publishing its first chapter
 */
?>
<div id="publish-prompt-modal" class="fanfic-modal" role="dialog" aria-labelledby="publish-modal-title" aria-modal="true" style="display: none;">
	<div class="fanfic-modal-overlay"></div>
	<div class="fanfic-modal-content">
		<h2 id="publish-modal-title"><?php esc_html_e( 'Ready to Publish Your Story?', 'fanfiction-manager' ); ?></h2>
		<p><?php esc_html_e( 'Great! Your story now has its first published chapter. Would you like to publish your story to make it visible to readers?', 'fanfiction-manager' ); ?></p>
		<div class="fanfic-modal-actions">
			<button type="button" id="publish-story-now" class="fanfic-button-primary">
				<?php esc_html_e( 'Yes, Publish Story', 'fanfiction-manager' ); ?>
			</button>
			<button type="button" id="keep-as-draft" class="fanfic-button-secondary">
				<?php esc_html_e( 'No, Keep as Draft', 'fanfiction-manager' ); ?>
			</button>
		</div>
	</div>
</div>

<?php
/**
 * Generic Warning Close Handler
 * Handles closing any warning modal with a data-close-target attribute
 */
?>
<script>
// ============================================================================
// CENTRALIZED TRANSLATABLE MESSAGES
// All alert() and confirm() messages are defined here for easy translation
// ============================================================================
var FanficMessages = {
	// Delete Confirmations
	deleteChapter: '<?php echo esc_js( __( 'Once you delete a chapter, there is no going back. All data will be permanently removed.', 'fanfiction-manager' ) ); ?>',
	deleteChapterLastWarning: '<?php echo esc_js( __( 'WARNING: This is your last chapter/prologue. Deleting it will automatically set your story to DRAFT status, making it invisible to readers.', 'fanfiction-manager' ) ); ?>',
	deleteStory: '<?php echo esc_js( __( 'Once you delete a story, there is no going back. All data will be permanently removed.', 'fanfiction-manager' ) ); ?>',

	// Unpublish Confirmations
	unpublishChapter: '<?php echo esc_js( __( 'Are you sure you want to unpublish', 'fanfiction-manager' ) ); ?>',
	unpublishChapterLastWarning: '<?php echo esc_js( __( 'WARNING: This is your last published chapter/prologue. Unpublishing it will automatically hide your story from readers (Draft status).', 'fanfiction-manager' ) ); ?>',
	unpublishChapterAutoDraftAlert: '<?php echo esc_js( __( 'Chapter unpublished. Your story has been set to DRAFT because it no longer has any published chapters or prologues.', 'fanfiction-manager' ) ); ?>',

	// Delete Success Alerts
	deleteChapterSuccess: '<?php echo esc_js( __( 'Chapter deleted successfully.', 'fanfiction-manager' ) ); ?>',
	deleteChapterAutoDraftAlert: '<?php echo esc_js( __( 'Chapter deleted. Your story has been set to DRAFT because it no longer has any chapters or prologues.', 'fanfiction-manager' ) ); ?>',

	// Error Messages
	errorCheckingLastChapter: '<?php echo esc_js( __( 'Error checking if this is the last chapter. Please try again.', 'fanfiction-manager' ) ); ?>',
	errorDeletingChapter: '<?php echo esc_js( __( 'An error occurred while deleting the chapter.', 'fanfiction-manager' ) ); ?>',
	errorDeletingStory: '<?php echo esc_js( __( 'An error occurred while deleting the story.', 'fanfiction-manager' ) ); ?>',
	errorUnpublishingChapter: '<?php echo esc_js( __( 'An error occurred while unpublishing the chapter.', 'fanfiction-manager' ) ); ?>',
	errorPublishingChapter: '<?php echo esc_js( __( 'An error occurred while publishing the chapter.', 'fanfiction-manager' ) ); ?>',
	errorUpdatingChapter: '<?php echo esc_js( __( 'An error occurred while updating the chapter.', 'fanfiction-manager' ) ); ?>',

	// Loading States
	deleting: '<?php echo esc_js( __( 'Deleting...', 'fanfiction-manager' ) ); ?>',
	unpublishing: '<?php echo esc_js( __( 'Unpublishing...', 'fanfiction-manager' ) ); ?>',
	publishing: '<?php echo esc_js( __( 'Publishing...', 'fanfiction-manager' ) ); ?>',
	updating: '<?php echo esc_js( __( 'Updating...', 'fanfiction-manager' ) ); ?>',

	// Button Labels
	delete: '<?php echo esc_js( __( 'Delete', 'fanfiction-manager' ) ); ?>',
	unpublish: '<?php echo esc_js( __( 'Unpublish', 'fanfiction-manager' ) ); ?>',
	publish: '<?php echo esc_js( __( 'Publish', 'fanfiction-manager' ) ); ?>',

	// Publish Story Prompts
	publishStoryPromptTitle: '<?php echo esc_js( __( 'Ready to Publish Your Story?', 'fanfiction-manager' ) ); ?>',
	publishStoryPromptMessage: '<?php echo esc_js( __( 'Great! Your story now has its first published chapter. Would you like to publish your story to make it visible to readers?', 'fanfiction-manager' ) ); ?>',
	publishStoryYes: '<?php echo esc_js( __( 'Yes, Publish Story', 'fanfiction-manager' ) ); ?>',
	publishStoryNo: '<?php echo esc_js( __( 'No, Keep as Draft', 'fanfiction-manager' ) ); ?>',
	publishingStory: '<?php echo esc_js( __( 'Publishing...', 'fanfiction-manager' ) ); ?>',

	// Validation Errors
	validationErrorsPrefix: '<?php echo esc_js( __( 'Cannot publish chapter. Missing required fields:', 'fanfiction-manager' ) ); ?>',
	clickEditToFix: '<?php echo esc_js( __( 'Click Edit to correct these issues.', 'fanfiction-manager' ) ); ?>',
	storyValidationErrorHeading: '<?php echo esc_js( __( 'Story cannot be published due to the following issues:', 'fanfiction-manager' ) ); ?>',
	chapterValidationErrorHeading: '<?php echo esc_js( __( 'Chapter cannot be published due to the following issues:', 'fanfiction-manager' ) ); ?>'
};

// ============================================================================
// GENERIC WARNING MODAL CLOSE HANDLER
// ============================================================================
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		// Handle all warning close buttons
		var closeButtons = document.querySelectorAll('[data-close-target]');
		closeButtons.forEach(function(button) {
			button.addEventListener('click', function(e) {
				e.preventDefault();
				var targetId = this.getAttribute('data-close-target');
				var targetElement = document.getElementById(targetId);
				if (targetElement) {
					targetElement.classList.remove('show');
				}
			});
		});
	});
})();

// ============================================================================
// PUBLISH STORY PROMPT MODAL HANDLER (Shared)
// ============================================================================
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var publishModal = document.getElementById('publish-prompt-modal');
		var publishNowButton = document.getElementById('publish-story-now');
		var keepDraftButton = document.getElementById('keep-as-draft');

		// Show modal if show_publish_prompt parameter is present
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('show_publish_prompt') === '1' && publishModal) {
			publishModal.style.display = 'flex';

			// Get story ID from URL or data attribute
			var storyId = urlParams.get('story_id');
			if (!storyId) {
				// Try to get from page context
				var storyIdElement = document.querySelector('[data-story-id]');
				if (storyIdElement) {
					storyId = storyIdElement.getAttribute('data-story-id');
				}
			}

			if (storyId && publishNowButton) {
				publishNowButton.setAttribute('data-story-id', storyId);
			}
		}

		// Handle "Keep as Draft" button
		if (keepDraftButton) {
			keepDraftButton.addEventListener('click', function() {
				publishModal.style.display = 'none';
				// Remove the parameter from URL without reloading
				var urlParams = new URLSearchParams(window.location.search);
				urlParams.delete('show_publish_prompt');
				urlParams.delete('story_id');
				var newUrl = window.location.pathname;
				var queryString = urlParams.toString();
				if (queryString) {
					newUrl += '?' + queryString;
				}
				window.history.replaceState({}, '', newUrl);
			});
		}

		// Handle "Publish Story Now" button
		if (publishNowButton) {
			publishNowButton.addEventListener('click', function() {
				var storyId = this.getAttribute('data-story-id');

				if (!storyId) {
					alert('<?php echo esc_js( __( 'Story ID not found.', 'fanfiction-manager' ) ); ?>');
					return;
				}

				// Disable button to prevent double-clicks
				publishNowButton.disabled = true;
				publishNowButton.textContent = FanficMessages.publishingStory;

				// Prepare AJAX request
				var formData = new FormData();
				formData.append('action', 'fanfic_publish_story');
				formData.append('story_id', storyId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'fanfic_publish_story' ); ?>');

				// Send AJAX request
				var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						// Remove show_publish_prompt from URL and reload to show updated story status
						var urlParams = new URLSearchParams(window.location.search);
						urlParams.delete('show_publish_prompt');
						urlParams.delete('story_id');
						var newUrl = window.location.pathname;
						var queryString = urlParams.toString();
						if (queryString) {
							newUrl += '?' + queryString;
						}
						window.location.href = newUrl;
					} else {
						// Re-enable button and show error
						publishNowButton.disabled = false;
						publishNowButton.textContent = FanficMessages.publishStoryYes;
						alert(data.data.message || '<?php esc_html_e( 'Failed to publish story.', 'fanfiction-manager' ); ?>');
					}
				})
				.catch(function(error) {
					// Re-enable button and show error
					publishNowButton.disabled = false;
					publishNowButton.textContent = FanficMessages.publishStoryYes;
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
