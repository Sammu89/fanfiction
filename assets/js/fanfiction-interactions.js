/**
 * Fanfiction Interactions JavaScript
 *
 * Unified interaction handling for all user actions:
 * - Ratings (1-5 stars)
 * - Likes (toggle)
 * - Bookmarks (toggle)
 * - Follows (toggle + email toggle)
 * - Email subscriptions
 * - Reading progress
 *
 * Features:
 * - Optimistic UI updates
 * - Loading states
 * - Error handling with user-friendly messages
 * - Rate limit handling
 * - Nonce verification
 *
 * @package FanfictionManager
 * @since 1.0.15
 */

(function($) {
	'use strict';

	/**
	 * Main interactions object
	 */
	const FanficInteractions = {
		/**
		 * Configuration
		 */
		config: {
			ajaxUrl: fanficAjax.ajaxUrl || '/wp-admin/admin-ajax.php',
			nonce: fanficAjax.nonce || '',
			debug: fanficAjax.debug || false,
			isLoggedIn: !!fanficAjax.isLoggedIn
		},

		/**
		 * Localized strings
		 */
		strings: fanficAjax.strings || {
			ratingSubmitted: 'Rating submitted!',
			ratingUpdated: 'Rating updated!',
			liked: 'Liked!',
			unliked: 'Like removed',
			bookmarkAdded: 'Bookmark added!',
			bookmarkRemoved: 'Bookmark removed',
			followAdded: 'Now following!',
			followRemoved: 'Unfollowed',
			emailEnabled: 'Email notifications enabled',
			emailDisabled: 'Email notifications disabled',
			markedRead: 'Marked as read',
			subscribed: 'Subscription successful!',
			error: 'An error occurred. Please try again.',
			rateLimited: 'Too many requests. Please wait a moment.',
			loginRequired: 'You must be logged in to do that.'
		},

		/**
		 * Initialize all interaction handlers
		 */
		init: function() {
			this.log('Initializing Fanfic Interactions');

			// Rating widgets
			this.initRatingWidgets();

			// Bookmark buttons
			this.initBookmarkButtons();

			// Follow buttons
			this.initFollowButtons();

			// Email subscription forms
			this.initEmailSubscriptionForms();

			// Reading progress
			this.initReadingProgress();

			// Share buttons
			this.initShareButtons();
		// Subscribe buttons (open modal)
		this.initSubscribeButtons();
		// Report buttons
		this.initReportButtons();
		// Login-required buttons
		this.initLoginRequiredButtons();

			this.log('Fanfic Interactions initialized');
		},

		/**
		 * Initialize rating widgets (star clicks and hover preview)
		 */
		initRatingWidgets: function() {
			const self = this;

			// Click handler
			$(document).on('click', '.fanfic-rating-widget .star', function(e) {
				e.preventDefault();

				const $star = $(this);
				const $widget = $star.closest('.fanfic-rating-widget');
				const chapterId = $widget.data('chapter-id');
				const rating = $star.data('rating');

				// Prevent double-click
				if ($widget.hasClass('loading')) {
					return;
				}

				self.log('Rating clicked:', { chapterId, rating });

				// Optimistic UI update
				self.updateRatingDisplay($widget, rating);

				// Submit rating
				self.submitRating(chapterId, rating, $widget);
			});

			// Hover preview - show what rating would look like
			$(document).on('mouseenter', '.fanfic-rating-widget .star', function() {
				const $star = $(this);
				const $widget = $star.closest('.fanfic-rating-widget');

				// Don't show preview if already loading
				if ($widget.hasClass('loading')) {
					return;
				}

				const rating = $star.data('rating');

				// Temporarily show what this rating would look like
				$widget.find('.star').each(function() {
					const $s = $(this);
					const starRating = $s.data('rating');

					if (starRating <= rating) {
						$s.text('★'); // Filled star
						$s.addClass('hover-preview');
					} else {
						$s.text('☆'); // Empty star
						$s.removeClass('hover-preview');
					}
				});
			});

			// Remove hover preview when mouse leaves the widget
			$(document).on('mouseleave', '.fanfic-rating-widget', function() {
				const $widget = $(this);
				const currentRating = $widget.data('user-rating') || 0;

				// Restore actual rating display
				self.updateRatingDisplay($widget, currentRating);
			});
		},

		/**
		 * Initialize bookmark buttons (localStorage-first, anonymous-capable)
		 */
		initBookmarkButtons: function() {
			const self = this;

			$(document).on('click', '.fanfic-bookmark-button', function(e) {
				e.preventDefault();

				const $button = $(this);
				const postId = parseInt($button.data('post-id'), 10);
				const storyId = parseInt($button.data('story-id'), 10) || 0;
				const chapterId = parseInt($button.data('chapter-id'), 10) || 0;

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Bookmark button clicked:', { postId, storyId, chapterId });

				// Toggle in localStorage immediately
				const entry = FanficLocalStore.toggleBookmark(storyId, chapterId);
				const isNowBookmarked = !!entry.bookmark;

				// Optimistic UI update
				self.updateBookmarkDisplay($button, isNowBookmarked);

				// AJAX to server
				self.toggleBookmark(postId, $button, !isNowBookmarked);
			});
		},

		/**
		 * Initialize follow buttons
		 */
		initFollowButtons: function() {
			const self = this;

			// Follow toggle
			$(document).on('click', '.fanfic-follow-button', function(e) {
				e.preventDefault();

				const $button = $(this);
				const targetId = $button.data('target-id');
				const followType = $button.data('follow-type') || 'story';

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Follow button clicked:', { targetId, followType });

				// Optimistic UI update - check for 'is-followd' class added by PHP
				const wasFollowing = $button.hasClass('fanfic-button-following');
				self.updateFollowDisplay($button, !wasFollowing);

				// Toggle follow
				self.toggleFollow(targetId, followType, $button, wasFollowing);
			});

			// Email notification toggle
			$(document).on('click', '.fanfic-email-toggle', function(e) {
				e.preventDefault();

				const $toggle = $(this);
				const targetId = $toggle.data('target-id');
				const followType = $toggle.data('follow-type') || 'story';

				// Prevent double-click
				if ($toggle.hasClass('loading')) {
					return;
				}

				self.log('Email toggle clicked:', { targetId, followType });

				// Optimistic UI update
				const wasEnabled = $toggle.hasClass('enabled');
				self.updateEmailToggleDisplay($toggle, !wasEnabled);

				// Toggle email notifications
				self.toggleEmailNotifications(targetId, followType, $toggle, wasEnabled);
			});
		},

		/**
		 * Initialize email subscription forms
		 */
		initEmailSubscriptionForms: function() {
			const self = this;

			$(document).on('submit', '.fanfic-email-subscription-form', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submitButton = $form.find('button[type="submit"]');
				const $emailInput = $form.find('input[type="email"]');
				const email = $emailInput.val().trim();
				const targetId = $form.data('target-id');
				const subscriptionType = $form.data('subscription-type') || 'story';

				// Validate email
				if (!self.validateEmail(email)) {
					self.showError($form, 'Please enter a valid email address.');
					return;
				}

				// Prevent double-submit
				if ($submitButton.hasClass('loading')) {
					return;
				}

				self.log('Email subscription form submitted:', { email, targetId, subscriptionType });

				// Show loading state
				$submitButton.addClass('loading').prop('disabled', true);

				// Submit subscription
				self.subscribeEmail(email, targetId, subscriptionType, $form);
			});
		},

		/**
		 * Initialize reading progress tracking
		 */
		initReadingProgress: function() {
			const self = this;

			$(document).on('click', '.fanfic-mark-read-button', function(e) {
				e.preventDefault();

				const $button = $(this);
				const storyId = $button.data('story-id');
				const chapterNumber = $button.data('chapter-number');

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				// Check current read state - check for 'is-markredd' class added by PHP
				const isCurrentlyRead = $button.hasClass('is-markredd') || $button.hasClass('read');

				self.log('Mark as read clicked:', { storyId, chapterNumber, isCurrentlyRead });

				// Optimistic UI update (toggle)
				self.updateReadDisplay($button, !isCurrentlyRead);

				// Toggle read status
				self.markAsRead(storyId, chapterNumber, $button, isCurrentlyRead);
			});
		},

		/**
		 * Initialize share buttons
		 */
		initShareButtons: function() {
			const self = this;

			$(document).on('click', '.fanfic-share-button', function(e) {
				e.preventDefault();

				const $button = $(this);
				const url = window.location.href;
				const title = document.title;

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Share button clicked:', { url, title });

				// Check if we should use Web Share API (mobile devices primarily)
				const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

				// Try Web Share API on mobile devices (where it works reliably)
				if (navigator.share && isMobile) {
					navigator.share({
						title: title,
						url: url
					}).then(function() {
						self.log('Share successful via Web Share API');
						// Don't show success message for native share - the OS handles it
					}).catch(function(err) {
						// User cancelled or error
						if (err.name !== 'AbortError') {
							self.log('Share failed:', err);
							// Fallback to clipboard on error
							self.copyToClipboard(url, $button);
						}
					});
				} else if (navigator.clipboard && navigator.clipboard.writeText) {
					// Use clipboard API for desktop (more reliable than Web Share on desktop)
					self.copyToClipboard(url, $button);
				} else {
					// Last resort - show prompt for manual copy
					self.showPromptCopy(url, $button);
				}
			});
		},

		/**
		 * Copy URL to clipboard
		 */
		copyToClipboard: function(url, $button) {
			const self = this;

			navigator.clipboard.writeText(url).then(function() {
				self.log('Link copied to clipboard');
				self.showSuccess($button, 'Link copied to clipboard!');
			}).catch(function(err) {
				self.log('Clipboard copy failed:', err);
				// Fallback to prompt
				self.showPromptCopy(url, $button);
			});
		},

		/**
		 * Show prompt for manual copy (last resort)
		 */
		showPromptCopy: function(url, $button) {
			// Create a temporary input to select and copy
			const $temp = $('<input>').val(url).appendTo('body').select();

			try {
				document.execCommand('copy');
				this.showSuccess($button, 'Link copied to clipboard!');
				this.log('Link copied via execCommand');
			} catch (err) {
				this.log('execCommand copy failed:', err);
				// Ultimate fallback - show alert with URL
				alert('Copy this link:\n\n' + url);
			}

			$temp.remove();
		},

	/**
	 * Initialize report buttons
	 */
	initReportButtons: function() {
		const self = this;

		$(document).on('click', '.fanfic-report-button', function(e) {
			e.preventDefault();

			const $button = $(this);
			const contentId = $button.data('content-id');
			const reportType = $button.data('report-type');

			// Prevent double-click
			if ($button.hasClass('loading')) {
				return;
			}

			self.log('Report button clicked:', { contentId, reportType });

			// Show confirmation dialog
			const confirmed = confirm(self.strings.reportConfirm || 'Are you sure you want to report this content?');
			if (!confirmed) {
				return;
			}

			// Show prompt for report reason
			const reason = prompt(self.strings.reportReason || 'Please provide a reason for reporting (optional):');
			if (reason === null) {
				return; // User cancelled
			}

			// Submit report
			self.submitReport(contentId, reportType, reason, $button);
		});
	},

	/**
	 * Initialize subscribe buttons (open modal)
	 */
	initSubscribeButtons: function() {
		const self = this;

		$(document).on('click', '.fanfic-subscribe-button', function(e) {
			e.preventDefault();

			const $button = $(this);
			const targetId = $button.data('target-id');
			const subscriptionType = $button.data('subscription-type');

			self.log('Subscribe button clicked:', { targetId, subscriptionType });

			// Open the subscription modal
			const modalId = 'fanfic-subscribe-modal-' + subscriptionType + '-' + targetId;
			
			// Use EnhancedModal if available, otherwise use basic Modal
			if (typeof window.EnhancedModal !== 'undefined') {
				window.EnhancedModal.open(modalId);
			} else if (typeof window.Modal !== 'undefined') {
				window.Modal.open(modalId);
			} else {
				// Fallback: just show the modal
				$('#' + modalId).fadeIn(200);
			}
		});
	},

	/**
	 * Initialize login-required buttons (show balloon on hover)
	 */
	initLoginRequiredButtons: function() {
		const self = this;

		// Show balloon on mouseenter for disabled buttons with login message
		$(document).on('mouseenter', '.fanfic-button.requires-login', function() {
			const $button = $(this);
			const message = $button.data('login-message');

			if (message && typeof BalloonNotification !== 'undefined') {
				BalloonNotification.show(message, 'info');
			}
		});

		// Hide balloon on mouseleave
		$(document).on('mouseleave', '.fanfic-button.requires-login', function() {
			if (typeof BalloonNotification !== 'undefined') {
				BalloonNotification.hide();
			}
		});

		// Prevent click on disabled buttons
		$(document).on('click', '.fanfic-button.requires-login', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		});
	},

		/**
		 * Submit rating to server
		 */
		submitRating: function(chapterId, rating, $widget) {
			const self = this;

			$widget.addClass('loading');
			const payload = {
				action: 'fanfic_record_interaction',
				nonce: this.config.nonce,
				chapter_id: chapterId,
				type: 'rating',
				value: rating
			};
			if (!this.config.isLoggedIn) {
				const anonymousUuid = this.getAnonymousUuid();
				if (anonymousUuid) {
					payload.anonymous_uuid = anonymousUuid;
				}
			}

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(response) {
					self.log('Rating response:', response);

					if (response.success) {
						// Update stats from server
						if (response.data && response.data.data && response.data.data.stats) {
							self.updateStatsDisplay($widget, {
								rating_average: response.data.data.stats.rating_avg || 0,
								rating_count: response.data.data.stats.rating_count || 0
							});
						} else if (response.data && response.data.data) {
							self.updateStatsDisplay($widget, response.data.data);
						}
						self.showSuccess($widget, response.data.message || self.strings.ratingSubmitted);
					} else {
						// Revert optimistic update
						self.showError($widget, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Rating error:', xhr);
					self.handleAjaxError(xhr, $widget);
				},
				complete: function() {
					$widget.removeClass('loading');
				}
			});
		},

		/**
		 * Toggle bookmark on server (supports anonymous via UUID)
		 */
		toggleBookmark: function(postId, $button, wasBookmarked) {
			const self = this;

			$button.addClass('loading');

			const payload = {
				action: 'fanfic_toggle_bookmark',
				nonce: this.config.nonce,
				post_id: postId
			};
			if (!this.config.isLoggedIn) {
				const anonymousUuid = FanficLocalStore.getAnonymousUuid();
				if (anonymousUuid) {
					payload.anonymous_uuid = anonymousUuid;
				}
			}

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(response) {
					self.log('Bookmark response:', response);

					if (response.success) {
						const isBookmarked = response.data.data.is_bookmarked;
						self.updateBookmarkDisplay($button, isBookmarked);
						self.showSuccess($button, response.data.message || (isBookmarked ? self.strings.bookmarkAdded : self.strings.bookmarkRemoved));
					} else {
						// Revert optimistic update + localStorage
						self.revertBookmarkLocal($button, wasBookmarked);
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Bookmark error:', xhr);
					// Revert optimistic update + localStorage
					self.revertBookmarkLocal($button, wasBookmarked);
					self.handleAjaxError(xhr, $button);
				},
				complete: function() {
					$button.removeClass('loading');
				}
			});
		},

		/**
		 * Revert bookmark in localStorage and UI on error
		 */
		revertBookmarkLocal: function($button, wasBookmarked) {
			const storyId = parseInt($button.data('story-id'), 10) || 0;
			const chapterId = parseInt($button.data('chapter-id'), 10) || 0;
			// Toggle back to previous state
			FanficLocalStore.toggleBookmark(storyId, chapterId);
			this.updateBookmarkDisplay($button, wasBookmarked);
		},

		/**
		 * Toggle follow on server
		 */
		toggleFollow: function(targetId, followType, $button, wasFollowing) {
			const self = this;

			$button.addClass('loading');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_toggle_follow',
					nonce: this.config.nonce,
					target_id: targetId,
					follow_type: followType
				},
				success: function(response) {
					self.log('Follow response:', response);

					if (response.success) {
						const isFollowing = response.data.data.is_following;
						const emailEnabled = response.data.data.email_enabled;

						self.updateFollowDisplay($button, isFollowing);

						// Update email toggle if present
						const $emailToggle = $button.siblings('.fanfic-email-toggle');
						if ($emailToggle.length) {
							self.updateEmailToggleDisplay($emailToggle, emailEnabled);
							$emailToggle.toggle(isFollowing); // Show/hide email toggle
						}

						self.showSuccess($button, response.data.message || (isFollowing ? self.strings.followAdded : self.strings.followRemoved));
					} else {
						// Revert optimistic update
						self.updateFollowDisplay($button, wasFollowing);
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Follow error:', xhr);
					// Revert optimistic update
					self.updateFollowDisplay($button, wasFollowing);
					self.handleAjaxError(xhr, $button);
				},
				complete: function() {
					$button.removeClass('loading');
				}
			});
		},

		/**
		 * Toggle email notifications on server
		 */
		toggleEmailNotifications: function(targetId, followType, $toggle, wasEnabled) {
			const self = this;

			$toggle.addClass('loading');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_toggle_email_notifications',
					nonce: this.config.nonce,
					target_id: targetId,
					follow_type: followType
				},
				success: function(response) {
					self.log('Email toggle response:', response);

					if (response.success) {
						const emailEnabled = response.data.data.email_enabled;
						self.updateEmailToggleDisplay($toggle, emailEnabled);
						self.showSuccess($toggle, response.data.message || (emailEnabled ? self.strings.emailEnabled : self.strings.emailDisabled));
					} else {
						// Revert optimistic update
						self.updateEmailToggleDisplay($toggle, wasEnabled);
						self.showError($toggle, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Email toggle error:', xhr);
					// Revert optimistic update
					self.updateEmailToggleDisplay($toggle, wasEnabled);
					self.handleAjaxError(xhr, $toggle);
				},
				complete: function() {
					$toggle.removeClass('loading');
				}
			});
		},

		/**
		 * Subscribe email on server
		 */
		subscribeEmail: function(email, targetId, subscriptionType, $form) {
			const self = this;
			const $submitButton = $form.find('button[type="submit"]');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_subscribe_email',
					nonce: this.config.nonce,
					email: email,
					target_id: targetId,
					subscription_type: subscriptionType
				},
				success: function(response) {
					self.log('Email subscription response:', response);

					if (response.success) {
						// Clear form
						$form.find('input[type="email"]').val('');
						self.showSuccess($form, response.data.message || self.strings.subscribed);
					} else {
						self.showError($form, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Email subscription error:', xhr);
					self.handleAjaxError(xhr, $form);
				},
				complete: function() {
					$submitButton.removeClass('loading').prop('disabled', false);
				}
			});
		},

		/**
		 * Toggle chapter read status on server
		 */
		markAsRead: function(storyId, chapterNumber, $button, wasRead) {
			const self = this;

			$button.addClass('loading');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_mark_as_read',
					nonce: this.config.nonce,
					story_id: storyId,
					chapter_number: chapterNumber
				},
				success: function(response) {
					self.log('Mark as read response:', response);

					if (response.success) {
						const isRead = response.data.is_read;
						// Update from server response
						self.updateReadDisplay($button, isRead);
						self.showSuccess($button, response.data.message || self.strings.markedRead);
					} else {
						// Revert optimistic update on error
						self.updateReadDisplay($button, wasRead);
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Mark as read error:', xhr);
					// Revert optimistic update on error
					self.updateReadDisplay($button, wasRead);
					self.handleAjaxError(xhr, $button);
				},
				complete: function() {
					$button.removeClass('loading');
				}
			});
		},

	/**
	 * Submit report to server
	 */
	submitReport: function(contentId, reportType, reason, $button) {
		const self = this;

		$button.addClass('loading');

		$.ajax({
			url: this.config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_submit_report',
				nonce: this.config.nonce,
				content_id: contentId,
				report_type: reportType,
				reason: reason
			},
			success: function(response) {
				self.log('Report response:', response);

				if (response.success) {
					self.showSuccess($button, response.data.message || 'Report submitted successfully. Thank you!');
				} else {
					self.showError($button, response.data.message || self.strings.error);
				}
			},
			error: function(xhr) {
				self.log('Report error:', xhr);
				self.handleAjaxError(xhr, $button);
			},
			complete: function() {
				$button.removeClass('loading');
			}
		});
	},

		/**
		 * Update rating display (optimistic)
		 */
		updateRatingDisplay: function($widget, rating) {
			$widget.find('.star').each(function() {
				const $star = $(this);
				const starRating = $star.data('rating');

				// Remove hover preview class
				$star.removeClass('hover-preview');

				// Update star character based on rating
				if (starRating <= rating) {
					$star.text('★'); // Filled star (U+2605)
					$star.addClass('filled').removeClass('empty');
				} else {
					$star.text('☆'); // Empty star (U+2606)
					$star.addClass('empty').removeClass('filled');
				}
			});

			$widget.attr('data-user-rating', rating);
		},

		/**
		 * Update bookmark display (optimistic)
		 */
		updateBookmarkDisplay: function($button, isBookmarked) {
			if (isBookmarked) {
				$button.addClass('fanfic-button-bookmarked');
				$button.find('.bookmark-text').text($button.data('bookmarked-text') || 'Bookmarked');
			} else {
				$button.removeClass('fanfic-button-bookmarked');
				$button.find('.bookmark-text').text($button.data('bookmark-text') || 'Bookmark');
			}
		},

		/**
		 * Update follow display (optimistic)
		 */
		updateFollowDisplay: function($button, isFollowing) {
			if (isFollowing) {
				$button.addClass('fanfic-button-following');
				$button.find('.follow-text').text($button.data('following-text') || 'Following');
			} else {
				$button.removeClass('fanfic-button-following');
				$button.find('.follow-text').text($button.data('follow-text') || 'Follow');
			}
		},

		/**
		 * Update email toggle display (optimistic)
		 */
		updateEmailToggleDisplay: function($toggle, isEnabled) {
			if (isEnabled) {
				$toggle.addClass('enabled').removeClass('disabled');
				$toggle.find('.toggle-text').text($toggle.data('enabled-text') || 'Email: On');
			} else {
				$toggle.addClass('disabled').removeClass('enabled');
				$toggle.find('.toggle-text').text($toggle.data('disabled-text') || 'Email: Off');
			}
		},

		/**
		 * Update read display (toggle)
		 */
		updateReadDisplay: function($button, isRead) {
			if (isRead) {
				$button.addClass('fanfic-button-marked-read');
				$button.find('.read-text').text($button.data('read-text') || 'Read');
			} else {
				$button.removeClass('fanfic-button-marked-read');
				$button.find('.read-text').text($button.data('unread-text') || 'Mark as Read');
			}
		},

		/**
		 * Update stats display from server data
		 */
		updateStatsDisplay: function($widget, data) {
			// Update average rating
			if (data.rating_average !== undefined) {
				$widget.find('.fanfic-rating-average').text(data.rating_average.toFixed(1));
			}

			// Update count display
			if (data.rating_count !== undefined) {
				const count = parseInt(data.rating_count);
				const $countEl = $widget.find('.fanfic-rating-count');

				if (count === 0) {
					$countEl.text('(No ratings yet)');
				} else if (count === 1) {
					$countEl.text('(1 rating)');
				} else {
					$countEl.text('(' + count + ' ratings)');
				}
			}
		},

		/**
		 * Handle AJAX errors
		 */
		handleAjaxError: function(xhr, $element) {
			let message = this.strings.error;

			if (xhr.status === 429) {
				message = this.strings.rateLimited;
			} else if (xhr.status === 401) {
				message = this.strings.loginRequired;
			} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				message = xhr.responseJSON.data.message;
			}

			this.showError($element, message);
		},

		/**
		 * Show success message
		 */
		showSuccess: function($element, message) {
			this.showNotification($element, message, 'success');
		},

		/**
		 * Show error message
		 */
		showError: function($element, message) {
			this.showNotification($element, message, 'error');
		},

		/**
		 * Show notification (success or error)
		 */
	showNotification: function($element, message, type) {
		// Use BalloonNotification if available (from fanfiction-frontend.js)
		if (typeof window.BalloonNotification !== 'undefined') {
			this.log('Using BalloonNotification for:', message);
			window.BalloonNotification.show($element, message, type, 3000);
		} else {
			this.log('BalloonNotification not available, using fallback for:', message);
			// Fallback: Create inline notification (old behavior)
			// Remove existing notifications
			$element.find('.fanfic-notification').remove();

			// Create notification
			const $notification = $('<div></div>')
				.addClass('fanfic-notification')
				.addClass('fanfic-notification-' + type)
				.text(message);

			// Insert after element
			$element.after($notification);

			// Auto-hide after 3 seconds
			setTimeout(function() {
				$notification.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		}
	},

		/**
		 * Validate email address
		 */
		validateEmail: function(email) {
			const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return regex.test(email);
		},

		/**
		 * Log to console (if debug enabled)
		 */
		log: function() {
			if (this.config.debug) {
				console.log('[FanficInteractions]', ...arguments);
			}
		}
	};

	const FanficLocalStore = {
		key: 'fanfic_interactions',
		uuidKey: 'fanfic_anonymous_uuid',

		getAll: function() {
			try {
				const raw = window.localStorage.getItem(this.key);
				if (!raw) {
					return {};
				}
				const parsed = JSON.parse(raw);
				return parsed && typeof parsed === 'object' ? parsed : {};
			} catch (e) {
				return {};
			}
		},

		getAnonymousUuid: function() {
			const key = 'fanfic_anonymous_uuid';
			try {
				let existing = window.localStorage.getItem(key);
				if (existing && typeof existing === 'string') {
					existing = existing.trim();
				}
				if (existing) {
					return existing;
				}
				let generated = '';
				if (window.crypto && typeof window.crypto.randomUUID === 'function') {
					generated = window.crypto.randomUUID();
				} else {
					generated = 'anon-' + Date.now() + '-' + Math.random().toString(16).slice(2) + '-' + Math.random().toString(16).slice(2);
				}
				window.localStorage.setItem(key, generated);
				return generated;
			} catch (e) {
				return '';
			}
		},

		saveAll: function(data) {
			try {
				window.localStorage.setItem(this.key, JSON.stringify(data || {}));
				return true;
			} catch (e) {
				return false;
			}
		},

		getAnonymousUuid: function() {
			try {
				let existing = window.localStorage.getItem(this.uuidKey);
				if (existing && typeof existing === 'string') {
					existing = existing.trim();
				}
				if (existing) {
					return existing;
				}

				let generated = '';
				if (window.crypto && typeof window.crypto.randomUUID === 'function') {
					generated = window.crypto.randomUUID();
				} else {
					generated = 'anon-' + Date.now() + '-' + Math.random().toString(16).slice(2) + '-' + Math.random().toString(16).slice(2);
				}
				window.localStorage.setItem(this.uuidKey, generated);
				return generated;
			} catch (e) {
				return '';
			}
		},

		makeKey: function(storyId, chapterId) {
			return 'story_' + parseInt(storyId, 10) + '_chapter_' + parseInt(chapterId, 10);
		},

		getChapter: function(storyId, chapterId) {
			const all = this.getAll();
			const key = this.makeKey(storyId, chapterId);
			return all[key] || null;
		},

		setChapter: function(storyId, chapterId, entry) {
			const all = this.getAll();
			const key = this.makeKey(storyId, chapterId);
			all[key] = entry || {};
			return this.saveAll(all);
		},

		hasViewed: function(storyId, chapterId) {
			return !!this.getChapter(storyId, chapterId);
		},

		recordView: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId) || {};
			entry.timestamp = Date.now();
			entry.view = true;
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		toggleLike: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId) || {};
			const next = !entry.like;
			entry.like = next;
			if (next) {
				delete entry.dislike;
			}
			if (!next) {
				delete entry.like;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		toggleDislike: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId) || {};
			const next = !entry.dislike;
			entry.dislike = next;
			if (next) {
				delete entry.like;
			}
			if (!next) {
				delete entry.dislike;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		setRating: function(storyId, chapterId, rating) {
			const entry = this.getChapter(storyId, chapterId) || {};
			if (rating === null || rating === undefined || rating === '') {
				delete entry.rating;
			} else {
				entry.rating = parseFloat(rating);
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		setRead: function(storyId, chapterId, read) {
			const entry = this.getChapter(storyId, chapterId) || {};
			if (read) {
				entry.read = true;
			} else {
				delete entry.read;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		toggleBookmark: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId) || {};
			if (entry.bookmark) {
				delete entry.bookmark;
			} else {
				entry.bookmark = true;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		isBookmarked: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId);
			return !!(entry && entry.bookmark);
		},

		mergeFromServer: function(merged) {
			if (!merged || typeof merged !== 'object') {
				return;
			}
			this.saveAll(merged);
		}
	};

	const FanficUnifiedInteractions = {
		init: function() {
			this.config = {
				ajaxUrl: fanficAjax.ajaxUrl || '/wp-admin/admin-ajax.php',
				nonce: fanficAjax.nonce || '',
				isLoggedIn: !!fanficAjax.isLoggedIn,
				needsSync: !!fanficAjax.needsSync,
				enableDislikes: !!fanficAjax.enableDislikes
			};
			this.context = this.detectChapterContext();
			this.activeReadSeconds = 0;
			this.readTimer = null;
			this.anonymousUuid = FanficLocalStore.getAnonymousUuid();

			if (!this.context) {
				return;
			}

			this.applyUiFromLocal();
			this.bindLikeDislike();
			this.bindRating();
			this.initViewTracking();
			this.initReadTracking();
			this.initSyncOnLogin();
			this.bindCrossTabSync();
		},

		detectChapterContext: function() {
			const $node = $('[data-story-id][data-chapter-id]').first();
			if (!$node.length) {
				return null;
			}
			const storyId = parseInt($node.data('story-id'), 10);
			const chapterId = parseInt($node.data('chapter-id'), 10);
			if (!storyId || !chapterId) {
				return null;
			}
			return { storyId, chapterId };
		},

		initViewTracking: function() {
			if (!this.context) {
				return;
			}
			const storyId = this.context.storyId;
			const chapterId = this.context.chapterId;
			if (FanficLocalStore.hasViewed(storyId, chapterId)) {
				return;
			}
			FanficLocalStore.recordView(storyId, chapterId);
			$.post(this.config.ajaxUrl, {
				action: 'fanfic_record_view',
				nonce: this.config.nonce,
				chapter_id: chapterId,
				story_id: storyId
			});
		},

		initReadTracking: function() {
			if (!this.context) {
				return;
			}
			const entry = FanficLocalStore.getChapter(this.context.storyId, this.context.chapterId) || {};
			if (entry.read) {
				return;
			}
			const self = this;
			this.readTimer = window.setInterval(function() {
				if (document.hidden) {
					return;
				}
				self.activeReadSeconds += 1;
				if (self.activeReadSeconds < 120) {
					return;
				}
				window.clearInterval(self.readTimer);
				FanficLocalStore.setRead(self.context.storyId, self.context.chapterId, true);
				self.applyUiFromLocal();
				if (self.config.isLoggedIn) {
					self.postInteraction('read');
				}
			}, 1000);
		},

		bindLikeDislike: function() {
			const self = this;
			$(document).on('click', '.fanfic-like-button[data-story-id][data-chapter-id], .fanfic-button-like[data-story-id][data-chapter-id]', function(e) {
				e.preventDefault();
				const storyId = parseInt($(this).data('story-id'), 10);
				const chapterId = parseInt($(this).data('chapter-id'), 10);
				const entry = FanficLocalStore.toggleLike(storyId, chapterId);
				self.applyUiFromLocal();
				self.postInteraction(entry.like ? 'like' : 'remove_like', null, chapterId);
			});

			$(document).on('click', '.fanfic-dislike-button[data-story-id][data-chapter-id], .fanfic-button-dislike[data-story-id][data-chapter-id]', function(e) {
				e.preventDefault();
				if (!self.config.enableDislikes) {
					return;
				}
				const storyId = parseInt($(this).data('story-id'), 10);
				const chapterId = parseInt($(this).data('chapter-id'), 10);
				const entry = FanficLocalStore.toggleDislike(storyId, chapterId);
				self.applyUiFromLocal();
				self.postInteraction(entry.dislike ? 'dislike' : 'remove_dislike', null, chapterId);
			});
		},

		bindRating: function() {
			const self = this;
			$(document).on('click', '.fanfic-rating-stars-half .fanfic-star-hit', function(e) {
				e.preventDefault();
				const $hit = $(this);
				const $root = $hit.closest('[data-story-id][data-chapter-id]');
				const storyId = parseInt($root.data('story-id'), 10);
				const chapterId = parseInt($root.data('chapter-id'), 10);
				const value = parseFloat($hit.data('value'));
				if (!storyId || !chapterId || !value) {
					return;
				}
				FanficLocalStore.setRating(storyId, chapterId, value);
				self.applyUiFromLocal();
				self.postInteraction('rating', value, chapterId);
			});
		},

		postInteraction: function(type, value, chapterId) {
			const finalChapterId = chapterId || (this.context ? this.context.chapterId : 0);
			if (!finalChapterId) {
				return;
			}
			const payload = {
				action: 'fanfic_record_interaction',
				nonce: this.config.nonce,
				chapter_id: finalChapterId,
				type: type
			};
			if (!this.config.isLoggedIn && this.anonymousUuid) {
				payload.anonymous_uuid = this.anonymousUuid;
			}
			if (value !== undefined && value !== null) {
				payload.value = value;
			}
			$.post(this.config.ajaxUrl, payload);
		},

		initSyncOnLogin: function() {
			const self = this;
			if (!this.config.isLoggedIn) {
				return;
			}
			const localData = FanficLocalStore.getAll();
			const shouldSync = this.config.needsSync || (localData && Object.keys(localData).length > 0);
			if (!shouldSync) {
				return;
			}
			$.post(this.config.ajaxUrl, {
				action: 'fanfic_sync_interactions',
				nonce: this.config.nonce,
				local_data: JSON.stringify(localData),
				anonymous_uuid: this.anonymousUuid || ''
			}).done(function(response) {
				const merged = response && response.data && response.data.data ? response.data.data.merged : null;
				if (merged && typeof merged === 'object') {
					FanficLocalStore.mergeFromServer(merged);
					self.applyUiFromLocal();
				}
			});
		},

		bindCrossTabSync: function() {
			const self = this;
			window.addEventListener('storage', function(event) {
				if (event.key !== FanficLocalStore.key) {
					return;
				}
				self.applyUiFromLocal();
			});
		},

		applyUiFromLocal: function() {
			if (!this.context) {
				return;
			}
			const storyId = this.context.storyId;
			const chapterId = this.context.chapterId;
			const entry = FanficLocalStore.getChapter(storyId, chapterId) || {};

			$('.fanfic-like-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], .fanfic-button-like[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').toggleClass('fanfic-button-liked', !!entry.like);
			$('.fanfic-dislike-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], .fanfic-button-dislike[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').toggleClass('fanfic-button-disliked', !!entry.dislike);
			$('.fanfic-read-indicator[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').toggleClass('fanfic-read-indicator-read', !!entry.read);

			const rating = entry.rating ? parseFloat(entry.rating) : 0;
			$('.fanfic-rating-stars-half[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').each(function() {
				$(this).attr('data-rating', rating);
				$(this).find('.fanfic-star-wrap').each(function(index) {
					const starNumber = index + 1;
					const fill = Math.max(0, Math.min(1, rating - (starNumber - 1)));
					$(this).find('.fanfic-star-fill').css('width', (fill * 100) + '%');
				});
			});

			// Apply bookmark state for chapter-level bookmark button
			$('.fanfic-bookmark-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').each(function() {
				const $btn = $(this);
				const isBookmarked = !!entry.bookmark;
				$btn.toggleClass('fanfic-button-bookmarked', isBookmarked);
				$btn.find('.bookmark-text').text(isBookmarked ? ($btn.data('bookmarked-text') || 'Bookmarked') : ($btn.data('bookmark-text') || 'Bookmark'));
			});

			// Apply bookmark state for story-level bookmark button (chapter_id=0)
			const storyEntry = FanficLocalStore.getChapter(storyId, 0) || {};
			$('.fanfic-bookmark-button[data-story-id="' + storyId + '"][data-chapter-id="0"]').each(function() {
				const $btn = $(this);
				const isBookmarked = !!storyEntry.bookmark;
				$btn.toggleClass('fanfic-button-bookmarked', isBookmarked);
				$btn.find('.bookmark-text').text(isBookmarked ? ($btn.data('bookmarked-text') || 'Bookmarked') : ($btn.data('bookmark-text') || 'Bookmark'));
			});
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		FanficInteractions.init();
		FanficUnifiedInteractions.init();
	});

	// Expose to global scope
	window.FanficInteractions = FanficInteractions;
	window.FanficLocalStore = FanficLocalStore;
	window.FanficUnifiedInteractions = FanficUnifiedInteractions;

})(jQuery);
