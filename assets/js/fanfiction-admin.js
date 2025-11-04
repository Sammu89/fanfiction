/**
 * Fanfiction Manager - Admin JavaScript
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin functionality
	 */
	function init() {
		// Handle moderation actions
		initModerationActions();

		console.log('Fanfiction Manager Admin loaded');
	}

	/**
	 * Initialize moderation queue actions
	 */
	function initModerationActions() {
		// Mark as Reviewed button (opens modal)
		$(document).on('click', '.mark-reviewed-btn', openReviewedModal);

		// View Report button
		$(document).on('click', '.view-report-btn', viewReportDetails);

		// Modal close handlers
		$(document).on('click', '.fanfic-admin-modal-close, .fanfic-admin-modal-cancel', closeModal);
		$(document).on('click', '.fanfic-admin-modal-overlay', function(e) {
			if (e.target === this) {
				closeModal.call(this, e);
			}
		});

		// Submit moderator notes
		$(document).on('click', '.fanfic-admin-modal-submit', submitModeratorNotes);

		// ESC key to close modal
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('.fanfic-admin-modal:visible').length) {
				closeModal();
			}
		});
	}

	/**
	 * Open modal for marking report as reviewed
	 */
	function openReviewedModal(e) {
		e.preventDefault();

		var $btn = $(this);
		var reportId = $btn.data('report-id');
		var nonce = $btn.data('nonce');

		// Create modal HTML
		var modalHtml =
			'<div class="fanfic-admin-modal" data-report-id="' + reportId + '" data-nonce="' + nonce + '">' +
				'<div class="fanfic-admin-modal-overlay"></div>' +
				'<div class="fanfic-admin-modal-content">' +
					'<h2>Mark Report as Reviewed</h2>' +
					'<p>Please describe what action you took to resolve this report:</p>' +
					'<div class="fanfic-admin-modal-form">' +
						'<label for="moderator-notes">Moderator Notes <span class="required">*</span></label>' +
						'<textarea id="moderator-notes" rows="5" maxlength="500" placeholder="Describe the action taken (e.g., Content removed, Warning issued, No action needed, etc.)"></textarea>' +
						'<p class="description">Maximum 500 characters. This will be stored in the moderation log.</p>' +
						'<div class="fanfic-admin-modal-message"></div>' +
					'</div>' +
					'<div class="fanfic-admin-modal-actions">' +
						'<button type="button" class="button fanfic-admin-modal-cancel">Cancel</button>' +
						'<button type="button" class="button button-primary fanfic-admin-modal-submit">Submit</button>' +
					'</div>' +
				'</div>' +
			'</div>';

		// Append modal to body
		$('body').append(modalHtml);

		// Show modal with fade-in effect
		$('.fanfic-admin-modal').fadeIn(200);
		$('body').addClass('fanfic-admin-modal-open');

		// Focus on textarea
		$('#moderator-notes').focus();
	}

	/**
	 * View report details modal
	 */
	function viewReportDetails(e) {
		e.preventDefault();

		var $btn = $(this);
		var reportId = $btn.data('report-id');
		var nonce = $btn.data('nonce');

		// Show loading modal
		var loadingModal =
			'<div class="fanfic-admin-modal" data-report-id="' + reportId + '">' +
				'<div class="fanfic-admin-modal-overlay"></div>' +
				'<div class="fanfic-admin-modal-content">' +
					'<h2>Report Details</h2>' +
					'<div class="fanfic-admin-modal-loading">' +
						'<p>Loading report details...</p>' +
					'</div>' +
				'</div>' +
			'</div>';

		$('body').append(loadingModal);
		$('.fanfic-admin-modal').fadeIn(200);
		$('body').addClass('fanfic-admin-modal-open');

		// Fetch report details via AJAX
		$.ajax({
			url: fanfictionAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_get_report_details',
				nonce: nonce,
				report_id: reportId
			},
			success: function(response) {
				if (response.success && response.data.report) {
					var report = response.data.report;
					var detailsHtml = buildReportDetailsHTML(report);
					$('.fanfic-admin-modal-content').html(detailsHtml);
				} else {
					showModalError(response.data.message || 'Failed to load report details.');
				}
			},
			error: function() {
				showModalError('An error occurred while loading report details.');
			}
		});
	}

	/**
	 * Build HTML for report details
	 */
	function buildReportDetailsHTML(report) {
		var statusClass = 'status-' + report.status;
		var moderatorInfo = '';

		if (report.moderator_name) {
			moderatorInfo = '<tr><th>Reviewed By:</th><td>' + escapeHtml(report.moderator_name) + '</td></tr>';
		}

		var moderatorNotes = '';
		if (report.moderator_notes) {
			moderatorNotes = '<tr><th>Moderator Notes:</th><td>' + escapeHtml(report.moderator_notes) + '</td></tr>';
		}

		var postLink = report.post_link ? '<a href="' + escapeHtml(report.post_link) + '" target="_blank">' + escapeHtml(report.post_title) + '</a>' : escapeHtml(report.post_title);

		return '<h2>Report Details #' + report.id + '</h2>' +
			'<div class="fanfic-report-details">' +
				'<table class="widefat">' +
					'<tbody>' +
						'<tr><th>Reported Content:</th><td>' + postLink + '</td></tr>' +
						'<tr><th>Content Type:</th><td>' + escapeHtml(report.post_type) + '</td></tr>' +
						'<tr><th>Reported By:</th><td>' + escapeHtml(report.reporter_name) + '</td></tr>' +
						'<tr><th>Reason:</th><td>' + escapeHtml(report.reason) + '</td></tr>' +
						'<tr><th>Status:</th><td><span class="status-badge ' + statusClass + '">' + escapeHtml(report.status) + '</span></td></tr>' +
						'<tr><th>Reported On:</th><td>' + escapeHtml(report.created_at) + '</td></tr>' +
						'<tr><th>Last Updated:</th><td>' + escapeHtml(report.updated_at) + '</td></tr>' +
						moderatorInfo +
						moderatorNotes +
					'</tbody>' +
				'</table>' +
			'</div>' +
			'<div class="fanfic-admin-modal-actions">' +
				'<button type="button" class="button fanfic-admin-modal-close">Close</button>' +
			'</div>';
	}

	/**
	 * Submit moderator notes via AJAX
	 */
	function submitModeratorNotes(e) {
		e.preventDefault();

		var $modal = $('.fanfic-admin-modal:visible');
		var $submitBtn = $(this);
		var $textarea = $('#moderator-notes');
		var $message = $('.fanfic-admin-modal-message');
		var notes = $textarea.val().trim();
		var reportId = $modal.data('report-id');
		var nonce = $modal.data('nonce');

		// Validate notes
		if (!notes) {
			$message.html('<p class="error">Please provide moderator notes describing the action taken.</p>');
			$textarea.focus();
			return;
		}

		if (notes.length > 500) {
			$message.html('<p class="error">Moderator notes must be 500 characters or less.</p>');
			$textarea.focus();
			return;
		}

		// Disable submit button and show loading state
		$submitBtn.prop('disabled', true).text('Submitting...');
		$message.html('<p class="info">Submitting...</p>');

		// Submit via AJAX
		$.ajax({
			url: fanfictionAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_mark_reviewed',
				nonce: nonce,
				report_id: reportId,
				notes: notes
			},
			success: function(response) {
				if (response.success) {
					$message.html('<p class="success">' + (response.data.message || 'Report marked as reviewed successfully.') + '</p>');

					// Close modal and reload page after 1 second
					setTimeout(function() {
						closeModal();
						location.reload();
					}, 1000);
				} else {
					$message.html('<p class="error">' + (response.data.message || 'Failed to update report.') + '</p>');
					$submitBtn.prop('disabled', false).text('Submit');
				}
			},
			error: function() {
				$message.html('<p class="error">An error occurred. Please try again.</p>');
				$submitBtn.prop('disabled', false).text('Submit');
			}
		});
	}

	/**
	 * Close modal
	 */
	function closeModal(e) {
		if (e) {
			e.preventDefault();
		}

		var $modal = $('.fanfic-admin-modal:visible');

		$modal.fadeOut(200, function() {
			$modal.remove();
		});

		$('body').removeClass('fanfic-admin-modal-open');
	}

	/**
	 * Show error message in modal
	 */
	function showModalError(message) {
		var errorHtml = '<h2>Error</h2>' +
			'<p class="error">' + escapeHtml(message) + '</p>' +
			'<div class="fanfic-admin-modal-actions">' +
				'<button type="button" class="button fanfic-admin-modal-close">Close</button>' +
			'</div>';
		$('.fanfic-admin-modal-content').html(errorHtml);
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	// Initialize when document is ready
	$(document).ready(init);

})(jQuery);
