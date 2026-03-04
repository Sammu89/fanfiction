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
		initBlacklistActions();
		initMessageActions();
		initBanReasonModal();
		initTaxonomyFilter();
		initFandomsManager();

		console.log('Fanfiction Manager Admin loaded');
	}

	function initBanReasonModal() {
		if (!window.fanfictionAdmin || !fanfictionAdmin.suspensionReasons) {
			return;
		}

		const modalId = 'fanfic-ban-user-modal';

		function buildOptions() {
			return Object.keys(fanfictionAdmin.suspensionReasons).map(function(key) {
				return '<option value="' + escapeHtml(key) + '">' + escapeHtml(fanfictionAdmin.suspensionReasons[key]) + '</option>';
			}).join('');
		}

		function getModal() {
			let $modal = $('#' + modalId);
			if ($modal.length) {
				return $modal;
			}

			const strings = fanfictionAdmin.strings || {};
			const modalHtml =
				'<div class="fanfic-admin-modal fanfic-ban-user-modal" id="' + modalId + '" aria-hidden="true">' +
					'<div class="fanfic-admin-modal-overlay"></div>' +
					'<div class="fanfic-admin-modal-content">' +
						'<h2>' + escapeHtml(strings.banUserTitle || 'Suspend User') + '</h2>' +
						'<p>' + escapeHtml(strings.banUserDescription || 'Choose a suspension reason before confirming.') + '</p>' +
						'<div class="fanfic-admin-modal-form">' +
							'<label for="fanfic-ban-reason">' + escapeHtml(strings.banReasonLabel || 'Suspension reason') + '</label>' +
							'<select id="fanfic-ban-reason" class="widefat">' + buildOptions() + '</select>' +
							'<label for="fanfic-ban-reason-text" style="margin-top:12px;">' + escapeHtml(strings.banReasonTextLabel || 'Additional details') + '</label>' +
							'<textarea id="fanfic-ban-reason-text" rows="4" maxlength="500" placeholder="' + escapeHtml(strings.banReasonPlaceholder || '') + '"></textarea>' +
							'<div class="fanfic-admin-modal-message"></div>' +
						'</div>' +
						'<div class="fanfic-admin-modal-actions">' +
							'<button type="button" class="button fanfic-ban-modal-cancel">' + escapeHtml(strings.banCancel || 'Cancel') + '</button>' +
							'<button type="button" class="button button-primary fanfic-ban-modal-submit">' + escapeHtml(strings.banConfirm || 'Suspend User') + '</button>' +
						'</div>' +
					'</div>' +
				'</div>';

			$('body').append(modalHtml);
			return $('#' + modalId);
		}

		function openFromTrigger($trigger) {
			const $modal = getModal();
			$modal.data('userId', $trigger.data('user-id'));
			$modal.data('nonce', $trigger.data('nonce'));
			$modal.find('.fanfic-admin-modal-message').empty();
			$modal.find('#fanfic-ban-reason').prop('selectedIndex', 0);
			$modal.find('#fanfic-ban-reason-text').val('');
			$modal.fadeIn(150).attr('aria-hidden', 'false');
			$('body').addClass('fanfic-admin-modal-open');
			setTimeout(function() {
				$modal.find('#fanfic-ban-reason').trigger('focus');
			}, 20);
		}

		function closeBanModal() {
			const $modal = $('#' + modalId);
			if (!$modal.length) {
				return;
			}
			$modal.fadeOut(150).attr('aria-hidden', 'true');
			$('body').removeClass('fanfic-admin-modal-open');
		}

		$(document).on('click', '.fanfic-ban-modal-cancel, #' + modalId + ' .fanfic-admin-modal-overlay', function(e) {
			e.preventDefault();
			closeBanModal();
		});

		$(document).on('click', '#' + modalId + ' .fanfic-ban-modal-submit', function(e) {
			e.preventDefault();

			const $modal = $('#' + modalId);
			const $message = $modal.find('.fanfic-admin-modal-message');
			const $submit = $(this);
			const strings = fanfictionAdmin.strings || {};
			const reason = String($modal.find('#fanfic-ban-reason').val() || '').trim();
			const reasonText = String($modal.find('#fanfic-ban-reason-text').val() || '').trim();
			const userId = parseInt($modal.data('userId'), 10) || 0;
			const nonce = String($modal.data('nonce') || '');

			if (!reason) {
				$message.html('<p class="error">' + escapeHtml(strings.banReasonRequired || 'Please choose a suspension reason.') + '</p>');
				return;
			}

			$submit.prop('disabled', true).text(strings.banSubmitting || 'Suspending...');
			$message.html('');

			$.ajax({
				url: fanfictionAdmin.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'fanfic_ban_user',
					user_id: userId,
					nonce: nonce,
					suspension_reason: reason,
					suspension_reason_text: reasonText
				}
			}).done(function(response) {
				if (response && response.success) {
					window.location.reload();
					return;
				}

				$message.html('<p class="error">' + escapeHtml((response && response.data && response.data.message) || strings.actionError || 'An error occurred. Please try again.') + '</p>');
				$submit.prop('disabled', false).text(strings.banConfirm || 'Suspend User');
			}).fail(function() {
				$message.html('<p class="error">' + escapeHtml(strings.actionError || 'An error occurred. Please try again.') + '</p>');
				$submit.prop('disabled', false).text(strings.banConfirm || 'Suspend User');
			});
		});

		window.FanficAdminBanModal = {
			openFromTrigger: openFromTrigger
		};
	}

	function initMessageActions() {
		const strings = fanfictionAdmin.strings || {};

		function getMessageRows(messageId) {
			return {
				$mainRow: $('#fanfic-msg-row-' + messageId),
				$detailRow: $('#fanfic-msg-detail-' + messageId)
			};
		}

		function getMessagePrompt(messageId) {
			return getMessageRows(messageId).$detailRow.find('.fanfic-message-action-prompt').first();
		}

		function renderThreadEntries(entries) {
			const rows = Array.isArray(entries) ? entries : [];
			if (!rows.length) {
				return '<p class="fanfic-report-message-empty">' + escapeHtml(strings.threadEmpty || 'No messages in this conversation yet.') + '</p>';
			}

			let html = '<div class="fanfic-admin-thread-list">';
			rows.forEach(function(entry) {
				const role = String(entry && entry.sender_role ? entry.sender_role : 'system').trim();
				const roleClass = role === 'author' ? 'author' : (role === 'moderator' ? 'moderator' : 'system');
				const senderName = escapeHtml(String(entry && entry.sender_name ? entry.sender_name : ''));
				const created = escapeHtml(String(entry && entry.created_human ? entry.created_human : ''));
				const message = escapeHtml(String(entry && entry.message ? entry.message : '')).replace(/\n/g, '<br>');

				html += '<div class="fanfic-admin-thread-entry fanfic-admin-thread-entry-' + roleClass + '">';
				html += '<div class="fanfic-admin-thread-entry-meta"><strong>' + senderName + '</strong><span>' + created + '</span></div>';
				html += '<div class="fanfic-admin-thread-entry-message">' + message + '</div>';
				html += '</div>';
			});
			html += '</div>';
			return html;
		}

		function buildMessageThreadModalHtml(messageId, title, author, threadData) {
			const entriesHtml = renderThreadEntries(threadData && threadData.entries ? threadData.entries : []);
			const canSend = !!(threadData && threadData.can_send);
			const status = String(threadData && threadData.status ? threadData.status : '').trim();
			const replyLabel = strings.threadReplyLabel || 'Reply to Author';
			const sendLabel = strings.threadReplySend || 'Send Reply';
			const disabledAttr = canSend ? '' : ' disabled aria-disabled="true"';
			const closedLabel = status === 'ignored'
				? (strings.statusIgnored || 'Ignored')
				: (status === 'deleted' ? 'Deleted' : (strings.statusResolved || 'Resolved'));
			const statusInfo = canSend
				? ''
				: '<p class="description">' + escapeHtml(closedLabel) + '</p>';

			return '<h2>' + escapeHtml(strings.authorMessageTitle || 'Author Message') + '</h2>' +
				'<div class="fanfic-report-details"><table class="widefat"><tbody>' +
				'<tr><th>Title</th><td>' + escapeHtml(title) + '</td></tr>' +
				'<tr><th>Author</th><td>' + escapeHtml(author) + '</td></tr>' +
				'<tr><th>' + escapeHtml(strings.authorMessageLabel || 'Message') + '</th><td>' + entriesHtml + '</td></tr>' +
				'</tbody></table></div>' +
				statusInfo +
				'<div class="fanfic-admin-modal-form fanfic-admin-thread-reply-form">' +
				'<label for="fanfic-thread-reply-' + messageId + '">' + escapeHtml(replyLabel) + '</label>' +
				'<textarea id="fanfic-thread-reply-' + messageId + '" class="fanfic-thread-reply-input" rows="4" maxlength="1000"' + disabledAttr + '></textarea>' +
				'<div class="fanfic-admin-modal-actions">' +
				'<button type="button" class="button button-primary fanfic-thread-send-reply" data-message-id="' + messageId + '"' + disabledAttr + '>' + escapeHtml(sendLabel) + '</button>' +
				'<button type="button" class="button fanfic-admin-modal-close">Close</button>' +
				'</div>' +
				'</div>';
		}

		function openMessageModal(messageId) {
			const rows = getMessageRows(messageId);
			const title = $.trim(rows.$mainRow.find('.column-title').text() || '');
			const author = $.trim(rows.$mainRow.find('.column-author').text() || '');
			openAdminModal(
				'<h2>' + escapeHtml(strings.authorMessageTitle || 'Author Message') + '</h2>' +
				'<div class="fanfic-admin-modal-loading"><p>' + escapeHtml(strings.threadLoading || 'Loading conversation...') + '</p></div>'
			);

			$.ajax({
				url: fanfictionAdmin.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'fanfic_get_moderation_thread',
					nonce: fanfictionAdmin.nonce || '',
					message_id: messageId
				}
			}).done(function(response) {
				if (response && response.success && response.data) {
					$('.fanfic-admin-modal-content').html(buildMessageThreadModalHtml(messageId, title, author, response.data));
					return;
				}

				showModalError((response && response.data && response.data.message) || (strings.actionError || 'An error occurred. Please try again.'));
			}).fail(function() {
				showModalError(strings.actionError || 'An error occurred. Please try again.');
			});
		}

		function ensureAlreadyUnblockedNote($detailRow) {
			if ($detailRow.find('.fanfic-msg-already-unblocked-note').length) {
				return;
			}

			const noteHtml = '<p class="description fanfic-msg-already-unblocked-note">' + (strings.alreadyUnblockedNote || 'This target is already unblocked. Unblock is unavailable, but the message remains for review.') + '</p>';
			const $reason = $detailRow.find('.fanfic-msg-restriction-reason').first();
			if ($reason.length) {
				$reason.after(noteHtml);
			} else {
				$detailRow.find('.fanfic-message-full-text').first().after(noteHtml);
			}
		}

		function setBusyState(messageId, isBusy) {
			$('[data-message-id="' + messageId + '"].fanfic-msg-action-unblock, [data-message-id="' + messageId + '"].fanfic-msg-action-ignore, [data-message-id="' + messageId + '"].fanfic-msg-action-delete')
				.toggleClass('is-busy', isBusy)
				.attr('aria-disabled', isBusy ? 'true' : 'false')
				.prop('disabled', isBusy);
		}

		function fadeOutMessage(messageId) {
			const rows = getMessageRows(messageId);
			rows.$mainRow.add(rows.$detailRow).fadeOut(200, function() {
				$(this).remove();
			});
		}

		function ensureDetailRowVisible(messageId) {
			const rows = getMessageRows(messageId);
			if (!rows.$detailRow.is(':visible')) {
				rows.$detailRow.stop(true, true).slideDown(150);
			}
		}

		function closeMessagePrompt(messageId) {
			const rows = getMessageRows(messageId);
			const $prompt = getMessagePrompt(messageId);

			$prompt.removeAttr('data-action-type').hide();

			if (!rows.$detailRow.find('.fanfic-block-comparison-container').is(':visible')) {
				rows.$detailRow.stop(true, true).slideUp(150);
			}
		}

		function openMessagePrompt(messageId, actionType) {
			const $prompt = getMessagePrompt(messageId);
			const actionLabel = actionType === 'unblock'
				? (strings.unblockPromptLabel || 'Unblock')
				: (strings.ignorePromptLabel || 'Ignore');

			ensureDetailRowVisible(messageId);
			$prompt.attr('data-action-type', actionType);
			$prompt.find('.fanfic-message-action-prompt-title').text((strings.messagePromptTitle || 'Before continuing, add moderator notes for:') + ' ' + actionLabel);
			$prompt.find('.fanfic-msg-confirm-action').text((strings.confirmActionPrefix || 'Confirm') + ' ' + actionLabel);
			$prompt.stop(true, true).slideDown(150);
		}

		function submitMessageAction(messageId, actionType, $busyTarget) {
			const $detailRow = $('#fanfic-msg-detail-' + messageId);
			const internalNote = $detailRow.find('.fanfic-msg-internal-note-input').val() || '';
			const authorReply = $detailRow.find('.fanfic-msg-author-reply-input').val() || '';

			setBusyState(messageId, true);
			if ($busyTarget && $busyTarget.length) {
				$busyTarget.prop('disabled', true);
			}

			$.ajax({
				url: fanfictionAdmin.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'fanfic_mod_message_action',
					nonce: fanfictionAdmin.nonce || '',
					message_id: messageId,
					action_type: actionType,
					internal_note: internalNote,
					author_reply: authorReply
				}
			}).done(function(response) {
				if (response && response.success) {
					const data = response.data || {};
					if (!data.is_restricted) {
						ensureAlreadyUnblockedNote(getMessageRows(messageId).$detailRow);
					}
					fadeOutMessage(messageId);
					return;
				}

				window.alert((response && response.data && response.data.message) || (strings.actionError || 'An error occurred. Please try again.'));
				setBusyState(messageId, false);
				if ($busyTarget && $busyTarget.length) {
					$busyTarget.prop('disabled', false);
				}
			}).fail(function() {
				window.alert(strings.actionError || 'An error occurred. Please try again.');
				setBusyState(messageId, false);
				if ($busyTarget && $busyTarget.length) {
					$busyTarget.prop('disabled', false);
				}
			}).always(function() {
				if ($busyTarget && $busyTarget.length) {
					$busyTarget.blur();
				}
			});
		}

		$(document).on('click', '.fanfic-msg-view-message', function(e) {
			e.preventDefault();
			openMessageModal(parseInt($(this).data('message-id'), 10) || 0);
		});

		$(document).on('click', '.fanfic-thread-send-reply', function(e) {
			e.preventDefault();

			const $button = $(this);
			const messageId = parseInt($button.data('message-id'), 10) || 0;
			const $textarea = $('#fanfic-thread-reply-' + messageId);
			const reply = String($textarea.val() || '').trim();
			const originalLabel = $button.text();

			if (!messageId) {
				return;
			}

			if (!reply) {
				window.alert(strings.threadReplyEmpty || 'Please enter a reply before sending.');
				return;
			}

			$button.prop('disabled', true).text(strings.threadReplySending || 'Sending...');

			$.ajax({
				url: fanfictionAdmin.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'fanfic_send_moderation_reply',
					nonce: fanfictionAdmin.nonce || '',
					message_id: messageId,
					reply: reply
				}
			}).done(function(response) {
				if (response && response.success) {
					$textarea.val('');
					$('.fanfic-admin-modal').remove();
					$('body').removeClass('fanfic-admin-modal-open');
					openMessageModal(messageId);
					return;
				}

				window.alert((response && response.data && response.data.message) || (strings.actionError || 'An error occurred. Please try again.'));
				$button.prop('disabled', false).text(originalLabel);
			}).fail(function() {
				window.alert(strings.actionError || 'An error occurred. Please try again.');
				$button.prop('disabled', false).text(originalLabel);
			});
		});

		$(document).on('click', '.fanfic-msg-review-changes', function(e) {
			e.preventDefault();

			const $button = $(this);
			if ($button.hasClass('is-busy')) {
				return;
			}

			const messageId = parseInt($button.data('message-id'), 10) || 0;
			const rows = getMessageRows(messageId);
			const $container = rows.$detailRow.find('.fanfic-block-comparison-container').first();
			const compareLabel = strings.reviewChangesLabel || 'Review modifications';
			const hideCompareLabel = strings.hideReviewLabel || 'Hide review';

			if (!$container.length) {
				return;
			}

			ensureDetailRowVisible(messageId);

			if ($container.data('loaded')) {
				const isVisible = $container.is(':visible');
				$container.stop(true, true).slideToggle(150);
				rows.$mainRow.add(rows.$detailRow).find('.fanfic-msg-review-changes[data-message-id="' + messageId + '"]').text(isVisible ? compareLabel : hideCompareLabel);
				return;
			}

			$button.addClass('is-busy').prop('disabled', true).text(strings.reviewLoading || 'Loading review...');

			$.ajax({
				url: fanfictionAdmin.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'fanfic_get_block_comparison',
					nonce: fanfictionAdmin.nonce || '',
					message_id: messageId
				}
			}).done(function(response) {
				if (response && response.success && response.data && response.data.html) {
					$container.html(response.data.html).data('loaded', true).hide().slideDown(150);
					rows.$mainRow.add(rows.$detailRow).find('.fanfic-msg-review-changes[data-message-id="' + messageId + '"]').text(hideCompareLabel);
					return;
				}

				window.alert((response && response.data && response.data.message) || (strings.actionError || 'An error occurred. Please try again.'));
			}).fail(function() {
				window.alert(strings.actionError || 'An error occurred. Please try again.');
			}).always(function() {
				$button.removeClass('is-busy').prop('disabled', false);
				if (!$container.data('loaded')) {
					rows.$mainRow.add(rows.$detailRow).find('.fanfic-msg-review-changes[data-message-id="' + messageId + '"]').text(compareLabel);
				}
			});
		});

		$(document).on('click', '.fanfic-msg-action-unblock, .fanfic-msg-action-ignore, .fanfic-msg-action-delete', function(e) {
			e.preventDefault();

			const $button = $(this);
			if ($button.hasClass('is-busy')) {
				return;
			}
			const messageId = parseInt($button.data('message-id'), 10) || 0;
			let actionType = 'ignore';

			if ($button.hasClass('fanfic-msg-action-delete')) {
				actionType = 'delete';
			} else if ($button.hasClass('fanfic-msg-action-unblock')) {
				actionType = 'unblock';
			}

			if ('delete' === actionType && !window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.confirmDelete) || 'Delete this moderation message?')) {
				return;
			}

			if ('delete' !== actionType) {
				openMessagePrompt(messageId, actionType);
				return;
			}

			submitMessageAction(messageId, actionType, $button);
		});

		$(document).on('click', '.fanfic-msg-confirm-action', function(e) {
			e.preventDefault();

			const $button = $(this);
			const messageId = parseInt($button.data('message-id'), 10) || 0;
			const actionType = String(getMessagePrompt(messageId).attr('data-action-type') || '').trim();

			if (!actionType) {
				return;
			}

			if ('unblock' === actionType && !window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.confirmUnblock) || 'Unblock this restricted item?')) {
				return;
			}

			submitMessageAction(messageId, actionType, $button);
		});

		$(document).on('click', '.fanfic-msg-cancel-action', function(e) {
			e.preventDefault();
			closeMessagePrompt(parseInt($(this).data('message-id'), 10) || 0);
		});
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
		$('.fanfic-report-detail-row').removeClass('is-open').hide();
		$('.fanfic-report-toggle-block').attr('aria-expanded', 'false');
		$(document).on('click', '.fanfic-admin-modal-close, .fanfic-admin-modal-cancel', closeModal);
		$(document).on('click', '.fanfic-admin-modal-overlay', function(e) {
			if (e.target === this) {
				closeModal.call(this, e);
			}
		});
		$(document).on('click', '.fanfic-report-view-message', viewReportDetails);
		$(document).on('click', '.fanfic-report-toggle-block', toggleReportBlockPanel);
		$(document).on('click', '.fanfic-report-cancel-block', cancelReportBlockPanel);
		$(document).on('click', '.fanfic-report-confirm-block', submitReportBlock);
		$(document).on('click', '.fanfic-report-dismiss', dismissReport);
		$(document).on('click', '.fanfic-report-delete', deleteReport);
		$(document).on('click', '.fanfic-report-block-comment', blockCommentReport);
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

	function getModerationNonce() {
		return (fanfictionAdmin && fanfictionAdmin.moderationNonce) || (fanfictionAdmin && fanfictionAdmin.nonce) || '';
	}

	function openAdminModal(contentHtml) {
		var modalHtml = '<div class="fanfic-admin-modal" aria-hidden="false"><div class="fanfic-admin-modal-overlay"></div><div class="fanfic-admin-modal-content">' + contentHtml + '</div></div>';
		$('body').append(modalHtml);
		$('.fanfic-admin-modal').fadeIn(200);
		$('body').addClass('fanfic-admin-modal-open');
	}

	function buildReportMessageHTML(report) {
		var message = String(report.message || '').trim();
		var messageLabel = String(report.message_label || 'Message');
		var secondaryMessage = String(report.secondary_message || '').trim();
		var secondaryRow = '';
		if (secondaryMessage) {
			secondaryRow = '<tr><th>Reporter message</th><td><div class="fanfic-report-message-copy">' + escapeHtml(secondaryMessage).replace(/\n/g, '<br>') + '</div></td></tr>';
		}
		return '<h2>' + escapeHtml((fanfictionAdmin.strings && fanfictionAdmin.strings.reportMessageTitle) || 'Report Message') + '</h2>' +
			'<div class="fanfic-report-details"><table class="widefat"><tbody>' +
			'<tr><th>Title</th><td>' + escapeHtml(report.title || '') + '</td></tr>' +
			'<tr><th>Reported by</th><td>' + escapeHtml(report.reported_by || '') + '</td></tr>' +
			'<tr><th>Reason</th><td>' + escapeHtml(report.reason || '') + '</td></tr>' +
			'<tr><th>' + escapeHtml(messageLabel) + '</th><td>' + (message ? '<div class="fanfic-report-message-copy">' + escapeHtml(message).replace(/\n/g, '<br>') + '</div>' : '<span class="fanfic-report-message-empty">' + escapeHtml((fanfictionAdmin.strings && fanfictionAdmin.strings.reportMessageEmpty) || 'This report does not include a message.') + '</span>') + '</td></tr>' +
			secondaryRow +
			'</tbody></table></div><div class="fanfic-admin-modal-actions"><button type="button" class="button fanfic-admin-modal-close">Close</button></div>';
	}

	function viewReportDetails(e) {
		e.preventDefault();
		var reportId = parseInt($(this).data('report-id'), 10) || 0;
		openAdminModal('<h2>' + escapeHtml((fanfictionAdmin.strings && fanfictionAdmin.strings.reportMessageTitle) || 'Report Message') + '</h2><div class="fanfic-admin-modal-loading"><p>' + escapeHtml((fanfictionAdmin.strings && fanfictionAdmin.strings.reportLoading) || 'Loading message...') + '</p></div>');
		$.ajax({
			url: fanfictionAdmin.ajaxUrl || ajaxurl,
			type: 'POST',
			data: { action: 'fanfic_get_report_details', nonce: getModerationNonce(), report_id: reportId }
		}).done(function(response) {
			if (response && response.success && response.data && response.data.report) {
				$('.fanfic-admin-modal-content').html(buildReportMessageHTML(response.data.report));
				return;
			}
			showModalError((response && response.data && response.data.message) || (fanfictionAdmin.strings && fanfictionAdmin.strings.actionError) || 'An error occurred. Please try again.');
		}).fail(function() {
			showModalError((fanfictionAdmin.strings && fanfictionAdmin.strings.actionError) || 'An error occurred. Please try again.');
		});
	}

	function toggleReportBlockPanel(e) {
		e.preventDefault();
		var reportId = parseInt($(this).data('report-id'), 10) || 0;
		var $button = $(this);
		var $row = $('#fanfic-report-panel-row-' + reportId);
		var expanded = $row.hasClass('is-open');
		if (!$row.length) {
			return;
		}
		$('.fanfic-report-detail-row').removeClass('is-open').hide();
		$('.fanfic-report-toggle-block').attr('aria-expanded', 'false');
		if (!expanded) {
			$row.addClass('is-open').css('display', 'table-row');
		}
		$button.attr('aria-expanded', expanded ? 'false' : 'true');
	}

	function cancelReportBlockPanel(e) {
		e.preventDefault();
		var reportId = parseInt($(this).data('report-id'), 10) || 0;
		$('#fanfic-report-panel-row-' + reportId).removeClass('is-open').hide();
		$('.fanfic-report-toggle-block[data-report-id="' + reportId + '"]').attr('aria-expanded', 'false');
	}

	function submitReportAction(actionName, reportId, extraData, $busyTarget) {
		var payload = $.extend({ action: actionName, nonce: getModerationNonce(), report_id: reportId }, extraData || {});
		if ($busyTarget && $busyTarget.length) {
			$busyTarget.prop('disabled', true);
		}
		$.ajax({ url: fanfictionAdmin.ajaxUrl || ajaxurl, type: 'POST', data: payload }).done(function(response) {
			if (response && response.success) {
				window.location.reload();
				return;
			}
			window.alert((response && response.data && response.data.message) || ((fanfictionAdmin.strings && fanfictionAdmin.strings.actionError) || 'An error occurred. Please try again.'));
			if ($busyTarget && $busyTarget.length) {
				$busyTarget.prop('disabled', false);
			}
		}).fail(function() {
			window.alert((fanfictionAdmin.strings && fanfictionAdmin.strings.actionError) || 'An error occurred. Please try again.');
			if ($busyTarget && $busyTarget.length) {
				$busyTarget.prop('disabled', false);
			}
		});
	}

	function submitReportBlock(e) {
		e.preventDefault();
		var reportId = parseInt($(this).data('report-id'), 10) || 0;
		var $panel = $('.fanfic-report-block-panel[data-report-id="' + reportId + '"]');
		submitReportAction('fanfic_block_report', reportId, {
			block_reason: $panel.find('.fanfic-report-block-reason').val() || '',
			internal_note: $panel.find('.fanfic-report-internal-note').val() || '',
			author_message: $panel.find('.fanfic-report-author-message').val() || ''
		}, $(this));
	}

	function dismissReport(e) {
		e.preventDefault();
		if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.reportDismissConfirm) || 'Dismiss this report?')) {
			return;
		}
		submitReportAction('fanfic_dismiss_report', parseInt($(this).data('report-id'), 10) || 0, {}, $(this));
	}

	function deleteReport(e) {
		e.preventDefault();
		if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.reportDeleteConfirm) || 'Delete this report?')) {
			return;
		}
		submitReportAction('fanfic_delete_report', parseInt($(this).data('report-id'), 10) || 0, {}, $(this));
	}

	function blockCommentReport(e) {
		e.preventDefault();
		if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.reportCommentBlockConfirm) || 'Block this reported comment?')) {
			return;
		}
		submitReportAction('fanfic_block_report', parseInt($(this).data('report-id'), 10) || 0, {}, $(this));
	}

	function initBlacklistActions() {
		function submitAction(action, payload, $button) {
			if ($button && $button.length) {
				$button.prop('disabled', true);
			}

			$.ajax({
				url: fanfictionAdmin.ajaxUrl || ajaxurl,
				type: 'POST',
				data: $.extend(
					{
						action: action,
						nonce: getModerationNonce()
					},
					payload || {}
				)
			}).done(function(response) {
				if (response && response.success) {
					window.location.reload();
					return;
				}

				window.alert((response && response.data && response.data.message) || ((fanfictionAdmin.strings && fanfictionAdmin.strings.actionError) || 'An error occurred. Please try again.'));
				if ($button && $button.length) {
					$button.prop('disabled', false);
				}
			}).fail(function() {
				window.alert((fanfictionAdmin.strings && fanfictionAdmin.strings.actionError) || 'An error occurred. Please try again.');
				if ($button && $button.length) {
					$button.prop('disabled', false);
				}
			});
		}

		$(document).on('click', '.fanfic-blacklist-reporter', function(e) {
			e.preventDefault();
			var $button = $(this);
			if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.blacklistReporterConfirm) || 'Blacklist this reporter? They will no longer be able to submit reports.')) {
				return;
			}

			submitAction(
				'fanfic_blacklist_reporter',
				{
					user_id: parseInt($button.data('user-id'), 10) || 0,
					ip: String($button.data('ip') || '').trim()
				},
				$button
			);
		});

		$(document).on('click', '.fanfic-blacklist-message-sender', function(e) {
			e.preventDefault();
			var $button = $(this);
			if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.blacklistMessageSenderConfirm) || 'Blacklist this author? They will no longer be able to send moderation messages.')) {
				return;
			}

			submitAction(
				'fanfic_blacklist_message_sender',
				{
					user_id: parseInt($button.data('user-id'), 10) || 0
				},
				$button
			);
		});

		$(document).on('click', '.fanfic-unblacklist', function(e) {
			e.preventDefault();
			var $button = $(this);
			if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.unblacklistConfirm) || 'Remove this blacklist entry?')) {
				return;
			}

			submitAction(
				'fanfic_remove_blacklist',
				{
					blacklist_id: parseInt($button.data('blacklist-id'), 10) || 0
				},
				$button
			);
		});

		$(document).on('click', '.fanfic-unblock-content', function(e) {
			e.preventDefault();
			var $button = $(this);
			if (!window.confirm((fanfictionAdmin.strings && fanfictionAdmin.strings.unblockConfirm) || 'Unblock this content?')) {
				return;
			}

			submitAction(
				'fanfic_unblock_content',
				{
					target_type: String($button.data('target-type') || '').trim(),
					target_id: parseInt($button.data('target-id'), 10) || 0
				},
				$button
			);
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
