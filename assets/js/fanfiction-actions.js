/**
 * Fanfiction Manager - Action Buttons JavaScript
 *
 * Handles bookmark, follow, report, and share actions.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize action buttons
	 */
	function init() {
		// Bookmark buttons
		$(document).on('click', '.fanfic-bookmark-btn', handleBookmark);

		// Follow buttons
		$(document).on('click', '.fanfic-follow-btn', handleFollow);

		// Share buttons
		$(document).on('click', '.fanfic-share-btn', handleShare);

		// Report buttons
		$(document).on('click', '.fanfic-report-btn', openReportModal);

		// Modal close buttons
		$(document).on('click', '.fanfic-modal-close, .fanfic-modal-cancel', closeReportModal);
		$(document).on('click', '.fanfic-modal-overlay', closeReportModal);

		// Report form submission
		$(document).on('submit', '.fanfic-report-form', submitReport);
	}

	/**
	 * Handle bookmark/unbookmark action
	 */
	function handleBookmark(e) {
		e.preventDefault();

		var $btn = $(this);
		var storyId = $btn.data('story-id');
		var action = $btn.data('action');

		// Check if user is logged in
		if (!fanficActions.isLoggedIn) {
			window.location.href = fanficActions.loginUrl;
			return;
		}

		// Disable button during request
		$btn.prop('disabled', true);

		var ajaxAction = (action === 'bookmark') ? 'fanfic_bookmark_story' : 'fanfic_unbookmark_story';

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: fanficActions.nonce,
				story_id: storyId
			},
			success: function(response) {
				if (response.success) {
					// Update button state
					if (response.data.is_bookmarked) {
						$btn.removeClass('not-bookmarked').addClass('bookmarked');
						$btn.data('action', 'unbookmark');
						$btn.find('.fanfic-icon').html('&#9733;');
						$btn.find('.fanfic-text').text(
							$btn.closest('.fanfic-chapter-actions').length
								? fanficActions.strings.storyBookmarked || 'Story Bookmarked'
								: fanficActions.strings.bookmarked || 'Bookmarked'
						);
					} else {
						$btn.removeClass('bookmarked').addClass('not-bookmarked');
						$btn.data('action', 'bookmark');
						$btn.find('.fanfic-icon').html('&#9734;');
						$btn.find('.fanfic-text').text(
							$btn.closest('.fanfic-chapter-actions').length
								? fanficActions.strings.bookmarkStory || 'Bookmark Story'
								: fanficActions.strings.bookmark || 'Bookmark'
						);
					}

					// Show success message
					showMessage($btn, response.data.message, 'success');
				} else {
					// Show error message
					showMessage($btn, response.data.message, 'error');
				}
			},
			error: function() {
				showMessage($btn, fanficActions.strings.error || 'An error occurred. Please try again.', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	}

	/**
	 * Handle follow/unfollow action
	 */
	function handleFollow(e) {
		e.preventDefault();

		var $btn = $(this);
		var authorId = $btn.data('author-id');
		var action = $btn.data('action');

		// Check if user is logged in
		if (!fanficActions.isLoggedIn) {
			window.location.href = fanficActions.loginUrl;
			return;
		}

		// Disable button during request
		$btn.prop('disabled', true);

		var ajaxAction = (action === 'follow') ? 'fanfic_follow_author' : 'fanfic_unfollow_author';

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: fanficActions.nonce,
				author_id: authorId
			},
			success: function(response) {
				if (response.success) {
					// Update button state
					if (response.data.is_following) {
						$btn.removeClass('not-following').addClass('following');
						$btn.data('action', 'unfollow');
						$btn.find('.fanfic-icon').html('&#10003;');
						$btn.find('.fanfic-text').text(fanficActions.strings.following || 'Following');
					} else {
						$btn.removeClass('following').addClass('not-following');
						$btn.data('action', 'follow');
						$btn.find('.fanfic-icon').html('&#43;');
						$btn.find('.fanfic-text').text(fanficActions.strings.follow || 'Follow');
					}

					// Show success message
					showMessage($btn, response.data.message, 'success');
				} else {
					// Show error message
					showMessage($btn, response.data.message, 'error');
				}
			},
			error: function() {
				showMessage($btn, fanficActions.strings.error || 'An error occurred. Please try again.', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	}

	/**
	 * Handle share action
	 */
	function handleShare(e) {
		e.preventDefault();

		var $btn = $(this);
		var url = $btn.data('url');
		var title = $btn.data('title');

		// Use Web Share API if available
		if (navigator.share) {
			navigator.share({
				title: title,
				url: url
			}).then(function() {
				showMessage($btn, fanficActions.strings.shareSuccess || 'Shared successfully!', 'success');
			}).catch(function(error) {
				if (error.name !== 'AbortError') {
					fallbackShare(url);
				}
			});
		} else {
			fallbackShare(url);
		}
	}

	/**
	 * Fallback share method (copy to clipboard)
	 */
	function fallbackShare(url) {
		// Create temporary input
		var $temp = $('<input>');
		$('body').append($temp);
		$temp.val(url).select();

		try {
			document.execCommand('copy');
			alert(fanficActions.strings.linkCopied || 'Link copied to clipboard!');
		} catch (err) {
			prompt(fanficActions.strings.copyLink || 'Copy this link:', url);
		}

		$temp.remove();
	}

	/**
	 * Open report modal
	 */
	function openReportModal(e) {
		e.preventDefault();

		var $btn = $(this);
		var itemId = $btn.data('item-id');
		var itemType = $btn.data('item-type');

		// Check if user is logged in
		if (!fanficActions.isLoggedIn) {
			window.location.href = fanficActions.loginUrl;
			return;
		}

		var $modal = $('#fanfic-report-modal-' + itemId + '-' + itemType);
		if ($modal.length) {
			$modal.fadeIn(200);
			$('body').addClass('fanfic-modal-open');
		}
	}

	/**
	 * Close report modal
	 */
	function closeReportModal(e) {
		if (e) {
			e.preventDefault();
		}

		var $modal = $(this).closest('.fanfic-report-modal');
		if (!$modal.length) {
			$modal = $('.fanfic-report-modal:visible');
		}

		$modal.fadeOut(200);
		$('body').removeClass('fanfic-modal-open');

		// Reset form
		$modal.find('form')[0].reset();
		$modal.find('.fanfic-report-message').empty();

		// Reset reCAPTCHA if present
		if (typeof grecaptcha !== 'undefined') {
			var $recaptcha = $modal.find('.g-recaptcha');
			if ($recaptcha.length) {
				grecaptcha.reset();
			}
		}
	}

	/**
	 * Submit report
	 */
	function submitReport(e) {
		e.preventDefault();

		var $form = $(this);
		var $submitBtn = $form.find('button[type="submit"]');
		var $message = $form.find('.fanfic-report-message');
		var itemId = $form.data('item-id');
		var itemType = $form.data('item-type');
		var reason = $form.find('textarea[name="reason"]').val().trim();

		// Validate reason
		if (!reason) {
			$message.html('<p class="error">' + (fanficActions.strings.reasonRequired || 'Please provide a reason for reporting.') + '</p>');
			return;
		}

		// Get reCAPTCHA response if present
		var recaptchaResponse = '';
		if (typeof grecaptcha !== 'undefined') {
			var $recaptcha = $form.find('.g-recaptcha');
			if ($recaptcha.length) {
				recaptchaResponse = grecaptcha.getResponse();
			}
		}

		// Disable submit button
		$submitBtn.prop('disabled', true);
		$message.html('<p class="info">' + (fanficActions.strings.submitting || 'Submitting report...') + '</p>');

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_report_content',
				nonce: fanficActions.nonce,
				item_id: itemId,
				item_type: itemType,
				reason: reason,
				recaptcha_response: recaptchaResponse
			},
			success: function(response) {
				if (response.success) {
					$message.html('<p class="success">' + response.data.message + '</p>');

					// Close modal after 2 seconds
					setTimeout(function() {
						closeReportModal.call($form);
					}, 2000);
				} else {
					$message.html('<p class="error">' + response.data.message + '</p>');
					$submitBtn.prop('disabled', false);

					// Reset reCAPTCHA on error
					if (typeof grecaptcha !== 'undefined') {
						var $recaptcha = $form.find('.g-recaptcha');
						if ($recaptcha.length) {
							grecaptcha.reset();
						}
					}
				}
			},
			error: function() {
				$message.html('<p class="error">' + (fanficActions.strings.error || 'An error occurred. Please try again.') + '</p>');
				$submitBtn.prop('disabled', false);

				// Reset reCAPTCHA on error
				if (typeof grecaptcha !== 'undefined') {
					var $recaptcha = $form.find('.g-recaptcha');
					if ($recaptcha.length) {
						grecaptcha.reset();
					}
				}
			}
		});
	}

	/**
	 * Show temporary message near button
	 */
	function showMessage($btn, message, type) {
		// Remove any existing messages
		$btn.siblings('.fanfic-action-message').remove();

		// Create message element
		var $message = $('<span class="fanfic-action-message fanfic-action-message-' + type + '">' + message + '</span>');
		$btn.after($message);

		// Fade out after 3 seconds
		setTimeout(function() {
			$message.fadeOut(400, function() {
				$(this).remove();
			});
		}, 3000);
	}

	// Initialize when document is ready
	$(document).ready(init);

	// Prevent body scroll when modal is open
	$(document).on('keydown', function(e) {
		if ($('body').hasClass('fanfic-modal-open') && e.key === 'Escape') {
			closeReportModal();
		}
	});

})(jQuery);

/* ==================================================
   UNIFIED CONTENT ACTIONS - NEW FEATURES
   ================================================== */

(function($) {
	'use strict';

	/**
	 * Like Button Handler
	 */
	$(document).on('click', '.fanfic-like-btn:not(.disabled):not(.loading)', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var action = $btn.data('action');
		var itemId = $btn.data('item-id');
		var itemType = $btn.data('item-type');
		var allowAnonymous = $btn.data('allow-anonymous');

		// Add loading state
		$btn.addClass('loading animating');

		var ajaxAction = (action === 'like') ? 'fanfic_like_content' : 'fanfic_unlike_content';

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: fanficActions.nonce,
				item_id: itemId,
				item_type: itemType,
				allow_anonymous: allowAnonymous ? '1' : '0'
			},
			success: function(response) {
				$btn.removeClass('loading');

				if (response.success) {
					// Toggle state
					if (action === 'like') {
						$btn.removeClass('not-liked').addClass('liked');
						$btn.data('action', 'unlike');
						$btn.attr('aria-pressed', 'true');
						$btn.find('.fanfic-icon').html('&#10084;'); // Filled heart
						$btn.find('.fanfic-text').first().contents().filter(function() {
							return this.nodeType === 3;
						}).first().replaceWith('Liked ');
					} else {
						$btn.removeClass('liked').addClass('not-liked');
						$btn.data('action', 'like');
						$btn.attr('aria-pressed', 'false');
						$btn.find('.fanfic-icon').html('&#129293;'); // Outline heart
						$btn.find('.fanfic-text').first().contents().filter(function() {
							return this.nodeType === 3;
						}).first().replaceWith('Like ');
					}

					// Update counter
					if (response.data.like_count !== undefined) {
						$btn.find('.fanfic-like-count').text('(' + response.data.like_count + ')');
					}

					// Success animation
					$btn.addClass('success-flash');
					setTimeout(function() {
						$btn.removeClass('success-flash animating');
					}, 500);

				} else {
					$btn.removeClass('animating');
					showMessage($btn, response.data.message || 'Action failed', 'error');
				}
			},
			error: function() {
				$btn.removeClass('loading animating');
				showMessage($btn, 'Network error. Please try again.', 'error');
			}
		});
	});

	/**
	 * Read List Button Handler
	 */
	$(document).on('click', '.fanfic-read-list-btn:not(.disabled):not(.loading)', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var action = $btn.data('action');
		var storyId = $btn.data('story-id');

		// Check login
		if (!fanficActions.isLoggedIn) {
			window.location.href = fanficActions.loginUrl;
			return;
		}

		$btn.addClass('loading animating');

		var ajaxAction = (action === 'add') ? 'fanfic_add_to_read_list' : 'fanfic_remove_from_read_list';

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: fanficActions.nonce,
				story_id: storyId
			},
			success: function(response) {
				$btn.removeClass('loading');

				if (response.success) {
					// Toggle state
					if (action === 'add') {
						$btn.removeClass('not-in-read-list').addClass('in-read-list');
						$btn.data('action', 'remove');
						$btn.attr('aria-pressed', 'true');
						$btn.find('.fanfic-icon').html('&#128218;'); // Filled book
						$btn.find('.fanfic-text').text('In Read List');
					} else {
						$btn.removeClass('in-read-list').addClass('not-in-read-list');
						$btn.data('action', 'add');
						$btn.attr('aria-pressed', 'false');
						$btn.find('.fanfic-icon').html('&#128214;'); // Open book
						$btn.find('.fanfic-text').text('Read List');
					}

					$btn.addClass('success-flash');
					setTimeout(function() {
						$btn.removeClass('success-flash animating');
					}, 500);

				} else {
					$btn.removeClass('animating');
					showMessage($btn, response.data.message || 'Action failed', 'error');
				}
			},
			error: function() {
				$btn.removeClass('loading animating');
				showMessage($btn, 'Network error. Please try again.', 'error');
			}
		});
	});

	/**
	 * Mark as Read Button Handler
	 */
	$(document).on('click', '.fanfic-mark-read-btn:not(.disabled):not(.loading)', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var action = $btn.data('action');
		var chapterId = $btn.data('chapter-id');
		var storyId = $btn.data('story-id');

		// Check login
		if (!fanficActions.isLoggedIn) {
			window.location.href = fanficActions.loginUrl;
			return;
		}

		$btn.addClass('loading animating');

		var ajaxAction = (action === 'mark') ? 'fanfic_mark_as_read' : 'fanfic_unmark_as_read';

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: fanficActions.nonce,
				chapter_id: chapterId,
				story_id: storyId
			},
			success: function(response) {
				$btn.removeClass('loading');

				if (response.success) {
					// Toggle state
					if (action === 'mark') {
						$btn.removeClass('not-marked-read').addClass('marked-read');
						$btn.data('action', 'unmark');
						$btn.attr('aria-pressed', 'true');
						$btn.find('.fanfic-icon').html('&#9989;'); // Check mark box
						$btn.find('.fanfic-text').text('Marked');
					} else {
						$btn.removeClass('marked-read').addClass('not-marked-read');
						$btn.data('action', 'mark');
						$btn.attr('aria-pressed', 'false');
						$btn.find('.fanfic-icon').html('&#9744;'); // Empty box
						$btn.find('.fanfic-text').text('Mark as Read');
					}

					$btn.addClass('success-flash');
					setTimeout(function() {
						$btn.removeClass('success-flash animating');
					}, 500);

				} else {
					$btn.removeClass('animating');
					showMessage($btn, response.data.message || 'Action failed', 'error');
				}
			},
			error: function() {
				$btn.removeClass('loading animating');
				showMessage($btn, 'Network error. Please try again.', 'error');
			}
		});
	});

	/**
	 * Subscribe Button Handler - Opens Modal
	 */
	$(document).on('click', '.fanfic-subscribe-btn:not(.loading)', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var storyId = $btn.data('story-id');
		var $modal = $('#fanfic-subscribe-modal-' + storyId);

		if ($modal.length) {
			openSubscribeModal($modal);
		}
	});

	/**
	 * Subscribe Form Submission
	 */
	$(document).on('submit', '.fanfic-subscribe-form', function(e) {
		e.preventDefault();
		var $form = $(this);
		var $submitBtn = $form.find('button[type="submit"]');
		var $message = $form.find('.fanfic-subscribe-message');
		var storyId = $form.data('story-id');
		var email = $form.find('input[name="email"]').val();

		$submitBtn.prop('disabled', true).text('Subscribing...');
		$message.removeClass('success error').hide();

		$.ajax({
			url: fanficActions.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_subscribe_to_story',
				nonce: fanficActions.nonce,
				story_id: storyId,
				email: email
			},
			success: function(response) {
				$submitBtn.prop('disabled', false).text('Subscribe');

				if (response.success) {
					$message.addClass('success').text(response.data.message).show();
					$form[0].reset();

					// Update button state
					var $subscribeBtn = $('.fanfic-subscribe-btn[data-story-id="' + storyId + '"]');
					$subscribeBtn.addClass('subscribed').data('action', 'unsubscribe');
					$subscribeBtn.find('.fanfic-text').text('Subscribed');

					// Close modal after 2 seconds
					setTimeout(function() {
						closeSubscribeModal($form.closest('.fanfic-subscribe-modal'));
					}, 2000);
				} else {
					$message.addClass('error').text(response.data.message || 'Subscription failed').show();
				}
			},
			error: function() {
				$submitBtn.prop('disabled', false).text('Subscribe');
				$message.addClass('error').text('Network error. Please try again.').show();
			}
		});
	});

	/**
	 * Open Subscribe Modal
	 */
	function openSubscribeModal($modal) {
		$modal.fadeIn(200);
		$('body').addClass('fanfic-modal-open').css('overflow', 'hidden');

		// Pre-fill email if logged in
		if (fanficActions.isLoggedIn) {
			// Email would be pre-filled by PHP if available
		}
	}

	/**
	 * Close Subscribe Modal
	 */
	function closeSubscribeModal($modal) {
		if (!$modal) {
			$modal = $('.fanfic-subscribe-modal:visible');
		}
		$modal.fadeOut(200);
		$('body').removeClass('fanfic-modal-open').css('overflow', '');
	}

	// Subscribe Modal Close Button
	$(document).on('click', '.fanfic-subscribe-modal .fanfic-modal-close, .fanfic-subscribe-modal .fanfic-modal-cancel', function() {
		closeSubscribeModal($(this).closest('.fanfic-subscribe-modal'));
	});

	// Subscribe Modal Overlay Click
	$(document).on('click', '.fanfic-subscribe-modal .fanfic-modal-overlay', function() {
		closeSubscribeModal($(this).closest('.fanfic-subscribe-modal'));
	});

	// Close modal on Escape key
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && $('.fanfic-subscribe-modal:visible').length) {
			closeSubscribeModal();
		}
	});

	/**
	 * Disabled Button Tooltip
	 */
	$(document).on('mouseenter', '.fanfic-action-btn.disabled', function() {
		var $btn = $(this);
		if (!$btn.attr('data-tooltip')) {
			$btn.attr('data-tooltip', 'Login required');
		}
	});

	/**
	 * Helper function to show temporary messages
	 */
	function showMessage($btn, message, type) {
		// Remove any existing messages
		$btn.siblings('.fanfic-action-message').remove();

		// Create message element
		var $message = $('<span class="fanfic-action-message fanfic-action-message-' + type + '">' + message + '</span>');
		$message.css({
			'display': 'inline-block',
			'margin-left': '10px',
			'padding': '4px 8px',
			'border-radius': '4px',
			'font-size': '13px',
			'background': type === 'error' ? '#f8d7da' : '#d5f4d6',
			'color': type === 'error' ? '#a12929' : '#2f7e38',
			'border': '1px solid ' + (type === 'error' ? '#dc3232' : '#46b450')
		});

		$btn.after($message);

		// Fade out after 3 seconds
		setTimeout(function() {
			$message.fadeOut(400, function() {
				$(this).remove();
			});
		}, 3000);
	}

})(jQuery);
