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
 * Generic Warning Close Handler
 * Handles closing any warning modal with a data-close-target attribute
 */
?>
<script>
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
</script>
