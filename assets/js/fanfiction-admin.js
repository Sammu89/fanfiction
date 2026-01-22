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
		initTaxonomyFilter();
		initFandomsManager();

		console.log('Fanfiction Manager Admin loaded');
	}

	/**
	 * Initialize fandoms manager UI
	 */
	function initFandomsManager() {
		var $manager = $('.fanfic-fandoms-manager');
		if (!$manager.length) {
			return;
		}

		var selected = {};
		var selectedCategories = {};

		function updateBulkButtons() {
			var hasTags = Object.keys(selected).length > 0;
			var hasCategories = Object.keys(selectedCategories).length > 0;
			var state = getSelectionState();
			$('.fanfic-action-move').prop('disabled', !hasTags || hasCategories);
			$('.fanfic-action-activate').prop('disabled', (!hasTags && !hasCategories) || !state.hasInactive);
			$('.fanfic-action-deactivate').prop('disabled', (!hasTags && !hasCategories) || !state.hasActive);
			$('.fanfic-action-delete').prop('disabled', (!hasTags && !hasCategories));
			var singleTagRename = hasTags && !hasCategories && Object.keys(selected).length === 1;
			var singleCategoryRename = hasCategories && !hasTags && Object.keys(selectedCategories).length === 1;
			$('.fanfic-action-rename').prop('disabled', !(singleTagRename || singleCategoryRename));
		}

		function getSelectionState() {
			if (Object.keys(selected).length) {
				return getTagSelectionState();
			}
			if (Object.keys(selectedCategories).length) {
				return getCategorySelectionState();
			}
			return { hasActive: false, hasInactive: false };
		}

		function getTagSelectionState() {
			var ids = Object.keys(selected);
			var hasActive = false;
			var hasInactive = false;
			ids.forEach(function(id) {
				var $tag = $('.fanfic-fandom-tag[data-id="' + id + '"]').first();
				if (!$tag.length) {
					return;
				}
				if (String($tag.data('active')) === '1') {
					hasActive = true;
				} else {
					hasInactive = true;
				}
			});
			return { hasActive: hasActive, hasInactive: hasInactive };
		}

		function getCategorySelectionState() {
			var slugs = Object.keys(selectedCategories);
			var hasActive = false;
			var hasInactive = false;
			slugs.forEach(function(slug) {
				var $tags = $('.fanfic-category-card[data-category="' + slug + '"]').find('.fanfic-fandom-tag');
				$tags.each(function() {
					if (String($(this).data('active')) === '1') {
						hasActive = true;
					} else {
						hasInactive = true;
					}
				});
			});
			return { hasActive: hasActive, hasInactive: hasInactive };
		}

		function toggleSelection($tag) {
			if (Object.keys(selectedCategories).length) {
				clearCategorySelection();
			}
			var id = String($tag.data('id') || '');
			if (!id) {
				return;
			}
			if ($tag.hasClass('is-selected')) {
				$tag.removeClass('is-selected');
				delete selected[id];
			} else {
				$tag.addClass('is-selected');
				selected[id] = true;
			}
			updateBulkButtons();
		}

		function toggleCategorySelection($button) {
			if (Object.keys(selected).length) {
				clearTagSelection();
			}
			var slug = String($button.data('category') || '');
			if (!slug) {
				return;
			}
			if ($button.prop('checked')) {
				selectedCategories[slug] = true;
			} else {
				delete selectedCategories[slug];
			}
			updateBulkButtons();
		}

		function clearTagSelection() {
			selected = {};
			$('.fanfic-fandom-tag.is-selected').removeClass('is-selected');
		}

		function clearCategorySelection() {
			selectedCategories = {};
			$('.fanfic-category-select').prop('checked', false);
		}

		function openModal($modal) {
			if (!$modal.length) {
				return;
			}
			$modal.attr('aria-hidden', 'false').css('display', 'flex').hide().fadeIn(150);
			$('body').addClass('fanfic-admin-modal-open');
		}

		function closeModal($modal) {
			if (!$modal.length) {
				return;
			}
			$modal.fadeOut(150, function() {
				$modal.attr('aria-hidden', 'true');
			});
			$('body').removeClass('fanfic-admin-modal-open');
		}

		function openFandomEditModal($tag) {
			var $modal = $('#fanfic-fandom-edit-modal');
			var id = $tag.data('id');
			var name = $tag.data('name');
			var slug = $tag.data('slug');
			var category = $tag.data('category');
			var isActive = String($tag.data('active')) === '1';
			var updateNonce = $tag.data('update-nonce');
			var deleteNonce = $tag.data('delete-nonce');

			$modal.find('.fanfic-fandom-edit-title').text('Edit ' + name);
			$modal.find('input[name="fandom_id"]').val(id);
			$modal.find('input[name="fanfic_update_fandom_nonce"]').val(updateNonce);
			$modal.find('input[name="fandom_name"]').val(name);
			$modal.find('input[name="fandom_slug"]').val(slug);
			$modal.find('select[name="fandom_category"]').val(category);
			$modal.find('input[name="fandom_is_active"]').prop('checked', isActive);

			$modal.find('#fanfic-fandom-delete-form input[name="fandom_id"]').val(id);
			$modal.find('#fanfic-fandom-delete-form input[name="fanfic_delete_fandom_nonce"]').val(deleteNonce);

			openModal($modal);
		}

		function openCategoryModal($button) {
			var $modal = $('#fanfic-category-modal');
			$modal.find('input[name="category_slug"]').val($button.data('category'));
			$modal.find('input[name="category_name"]').val($button.data('label'));
			openModal($modal);
		}

		$(document).on('click', '.fanfic-fandom-tag', function(e) {
			if ($(e.target).closest('.fanfic-fandom-grip').length) {
				return;
			}
			toggleSelection($(this));
		});

		$(document).on('change', '.fanfic-category-select', function(e) {
			e.stopPropagation();
			toggleCategorySelection($(this));
		});

		function toggleCategoryCard($card) {
			var $toggle = $card.find('.fanfic-category-toggle');
			var isCollapsed = $card.hasClass('is-collapsed');
			if (isCollapsed) {
				$card.removeClass('is-collapsed');
				$toggle.attr('aria-expanded', 'true');
			} else {
				$card.addClass('is-collapsed');
				$toggle.attr('aria-expanded', 'false');
			}
		}

		$(document).on('click', '.fanfic-category-toggle', function(e) {
			e.stopPropagation();
			toggleCategoryCard($(this).closest('.fanfic-category-card'));
		});

		$(document).on('click', '.fanfic-category-header', function(e) {
			if ($(e.target).closest('.fanfic-category-select, .fanfic-category-toggle, input, button, a').length) {
				return;
			}
			toggleCategoryCard($(this).closest('.fanfic-category-card'));
		});

		$(document).on('click', '.fanfic-action-add-category', function() {
			openModal($('#fanfic-add-category-modal'));
		});

		$(document).on('click', '.fanfic-action-add-fandom', function() {
			openModal($('#fanfic-add-fandom-modal'));
		});

		$(document).on('click', '.fanfic-action-move', function() {
			var ids = Object.keys(selected);
			if (!ids.length) {
				return;
			}
			var $modal = $('#fanfic-bulk-move-modal');
			$modal.find('input[name="fandom_ids"]').val(ids.join(','));
			openModal($modal);
		});

		$(document).on('click', '.fanfic-action-rename', function() {
			var ids = Object.keys(selected);
			var categories = Object.keys(selectedCategories);
			if (ids.length === 1 && !categories.length) {
				var $tag = $('.fanfic-fandom-tag[data-id="' + ids[0] + '"]').first();
				if ($tag.length) {
					openFandomEditModal($tag);
				}
				return;
			}
			if (categories.length === 1 && !ids.length) {
				var $modal = $('#fanfic-category-rename-modal');
				var slug = categories[0];
				var label = $('.fanfic-category-card[data-category="' + slug + '"]').find('.fanfic-category-header h2').first().text().trim();
				$modal.find('input[name="category_slugs"]').val(slug);
				$modal.find('input[name="category_name"]').val(label);
				openModal($modal);
			}
		});

		$(document).on('submit', '#fanfic-category-rename-form', function() {
			var name = $(this).find('input[name="category_name"]').val().trim();
			if (!name) {
				return false;
			}
			return true;
		});

		$(document).on('click', '.fanfic-category-settings', function() {
			openCategoryModal($(this));
		});

		$(document).on('click', '.fanfic-fandoms-modal-cancel', function() {
			closeModal($(this).closest('.fanfic-admin-modal'));
		});

		$(document).on('click', '.fanfic-fandoms-modal-overlay', function(e) {
			if (e.target !== this) {
				return;
			}
			closeModal($(this).closest('.fanfic-admin-modal'));
		});

		$(document).on('submit', '#fanfic-fandom-delete-form', function() {
			return window.confirm('Delete this fandom?');
		});

		$(document).on('click', '.fanfic-action-activate, .fanfic-action-deactivate, .fanfic-action-delete', function() {
			var action = $(this).hasClass('fanfic-action-activate') ? 'activate' :
				$(this).hasClass('fanfic-action-deactivate') ? 'deactivate' : 'delete';
			var ids = Object.keys(selected);
			var categories = Object.keys(selectedCategories);

			if (!ids.length && !categories.length) {
				return;
			}

			if (action === 'delete' && !window.confirm('Delete selected items?')) {
				return;
			}

			if (categories.length) {
				var $categoryForm = $('#fanfic-category-bulk-form');
				$categoryForm.find('input[name="category_action"]').val(action);
				$categoryForm.find('input[name="category_slugs"]').val(categories.join(','));
				$categoryForm.trigger('submit');
				return;
			}

			var $form = $('#fanfic-fandom-bulk-form');
			$form.find('input[name="bulk_action"]').val(action);
			$form.find('input[name="fandom_ids"]').val(ids.join(','));
			$form.trigger('submit');
		});

	}

	/**
	 * Initialize moderation queue actions
	 */
	function initModerationActions() {
		// Mark as Reviewed button (opens modal)
		$(document).on('click', '.mark-reviewed-button', openReviewedModal);

		// View Report button
		$(document).on('click', '.view-report-button', viewReportDetails);

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
	 * Initialize taxonomy filter UI
	 */
	function initTaxonomyFilter() {
		var $container = $('.fanfic-taxonomies-search');
		if (!$container.length) {
			return;
		}

		var $input = $('#fanfic-taxonomy-search');
		var $results = $container.find('.fanfic-taxonomy-results');
		var $selected = $container.find('.fanfic-taxonomy-selected');
		var $rows = $('.fanfic-taxonomies-table tbody tr[data-taxonomy-slug]');

		var items = $rows.map(function() {
			var $row = $(this);
			return {
				name: String($row.data('taxonomy-name') || ''),
				slug: String($row.data('taxonomy-slug') || ''),
				row: $row
			};
		}).get();

		function getSelectedSlugs() {
			return $selected.find('[data-slug]').map(function() {
				return $(this).data('slug');
			}).get();
		}

		function updateFilter() {
			var selected = getSelectedSlugs();
			if (!selected.length) {
				$rows.show();
				return;
			}

			$rows.each(function() {
				var slug = String($(this).data('taxonomy-slug') || '');
				$(this).toggle(selected.indexOf(slug) !== -1);
			});
		}

		function renderResults(query) {
			var q = query.toLowerCase();
			var selected = getSelectedSlugs();
			var matches = items.filter(function(item) {
				if (!q) {
					return false;
				}
				if (selected.indexOf(item.slug) !== -1) {
					return false;
				}
				return item.name.toLowerCase().indexOf(q) !== -1 || item.slug.toLowerCase().indexOf(q) !== -1;
			});

			$results.empty();
			if (!matches.length) {
				return;
			}

			matches.forEach(function(item) {
				var $btn = $('<button type="button" class="fanfic-taxonomy-result"></button>');
				$btn.text(item.name + ' (' + item.slug + ')');
				$btn.attr('data-slug', item.slug);
				$results.append($btn);
			});
		}

		function addSelection(item) {
			var slug = item.slug;
			var name = item.name;
			var $chip = $('<span class="fanfic-taxonomy-chip"></span>');
			$chip.attr('data-slug', slug);
			$chip.text(name);

			var $remove = $('<button type="button" class="fanfic-taxonomy-remove" aria-label="Remove"></button>');
			$remove.text('×');
			$chip.append($remove);
			$selected.append($chip);
			updateFilter();
		}

		$input.on('input', function() {
			renderResults($(this).val());
		});

		$results.on('click', '.fanfic-taxonomy-result', function() {
			var slug = $(this).attr('data-slug');
			var item = items.find(function(entry) {
				return entry.slug === slug;
			});

			if (item) {
				addSelection(item);
			}

			$input.val('');
			$results.empty();
		});

		$selected.on('click', '.fanfic-taxonomy-remove', function() {
			$(this).closest('.fanfic-taxonomy-chip').remove();
			updateFilter();
		});
	}

	/**
	 * Open modal for marking report as reviewed
	 */
	function openReviewedModal(e) {
		e.preventDefault();

		var $button = $(this);
		var reportId = $button.data('report-id');
		var nonce = $button.data('nonce');

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

		var $button = $(this);
		var reportId = $button.data('report-id');
		var nonce = $button.data('nonce');

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

		if ($modal.hasClass('fanfic-fandoms-modal')) {
			$modal.fadeOut(200, function() {
				$modal.attr('aria-hidden', 'true');
			});
		} else {
			$modal.fadeOut(200, function() {
				$modal.remove();
			});
		}

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
