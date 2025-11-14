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
			debug: fanficAjax.debug || false
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

			// Like buttons
			this.initLikeButtons();

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

			this.log('Fanfic Interactions initialized');
		},

		/**
		 * Initialize rating widgets (star clicks)
		 */
		initRatingWidgets: function() {
			const self = this;

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
		},

		/**
		 * Initialize like buttons
		 */
		initLikeButtons: function() {
			const self = this;

			$(document).on('click', '.fanfic-like-button', function(e) {
				e.preventDefault();

				const $button = $(this);
				const chapterId = $button.data('chapter-id');

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Like button clicked:', { chapterId });

				// Optimistic UI update
				const wasLiked = $button.hasClass('liked');
				self.updateLikeDisplay($button, !wasLiked);

				// Toggle like
				self.toggleLike(chapterId, $button, wasLiked);
			});
		},

		/**
		 * Initialize bookmark buttons
		 */
		initBookmarkButtons: function() {
			const self = this;

			$(document).on('click', '.fanfic-bookmark-button', function(e) {
				e.preventDefault();

				const $button = $(this);
				const postId = $button.data('post-id');
				const bookmarkType = $button.data('bookmark-type') || 'story';

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Bookmark button clicked:', { postId, bookmarkType });

				// Optimistic UI update
				const wasBookmarked = $button.hasClass('bookmarked');
				self.updateBookmarkDisplay($button, !wasBookmarked);

				// Toggle bookmark
				self.toggleBookmark(postId, bookmarkType, $button, wasBookmarked);
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

				// Optimistic UI update
				const wasFollowing = $button.hasClass('following');
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

				self.log('Mark as read clicked:', { storyId, chapterNumber });

				// Optimistic UI update
				self.updateReadDisplay($button, true);

				// Mark as read
				self.markAsRead(storyId, chapterNumber, $button);
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

				// Try Web Share API first (native mobile/desktop sharing)
				if (navigator.share) {
					navigator.share({
						title: title,
						url: url
					}).then(function() {
						self.log('Share successful');
						self.showSuccess($button, 'Shared successfully!');
					}).catch(function(err) {
						// User cancelled or error - fail silently for cancellation
						if (err.name !== 'AbortError') {
							self.log('Share failed:', err);
							// Fallback to clipboard on error (but not on cancellation)
							self.copyToClipboard(url, $button);
						}
					});
				} else if (navigator.clipboard && navigator.clipboard.writeText) {
					// Fallback to clipboard API
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
		 * Submit rating to server
		 */
		submitRating: function(chapterId, rating, $widget) {
			const self = this;

			$widget.addClass('loading');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_submit_rating',
					nonce: this.config.nonce,
					chapter_id: chapterId,
					rating: rating
				},
				success: function(response) {
					self.log('Rating response:', response);

					if (response.success) {
						// Update stats from server
						if (response.data && response.data.data) {
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
		 * Toggle like on server
		 */
		toggleLike: function(chapterId, $button, wasLiked) {
			const self = this;

			$button.addClass('loading');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_toggle_like',
					nonce: this.config.nonce,
					chapter_id: chapterId
				},
				success: function(response) {
					self.log('Like response:', response);

					if (response.success) {
						// Update from server (in case optimistic update was wrong)
						const isLiked = response.data.data.is_liked;
						self.updateLikeDisplay($button, isLiked);

						// Update count if available
						if (response.data.data.like_count !== undefined) {
							self.updateLikeCount($button, response.data.data.like_count);
						}

						self.showSuccess($button, response.data.message || (isLiked ? self.strings.liked : self.strings.unliked));
					} else {
						// Revert optimistic update
						self.updateLikeDisplay($button, wasLiked);
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Like error:', xhr);
					// Revert optimistic update
					self.updateLikeDisplay($button, wasLiked);
					self.handleAjaxError(xhr, $button);
				},
				complete: function() {
					$button.removeClass('loading');
				}
			});
		},

		/**
		 * Toggle bookmark on server
		 */
		toggleBookmark: function(postId, bookmarkType, $button, wasBookmarked) {
			const self = this;

			$button.addClass('loading');

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fanfic_toggle_bookmark',
					nonce: this.config.nonce,
					post_id: postId,
					bookmark_type: bookmarkType
				},
				success: function(response) {
					self.log('Bookmark response:', response);

					if (response.success) {
						const isBookmarked = response.data.data.is_bookmarked;
						self.updateBookmarkDisplay($button, isBookmarked);
						self.showSuccess($button, response.data.message || (isBookmarked ? self.strings.bookmarkAdded : self.strings.bookmarkRemoved));
					} else {
						// Revert optimistic update
						self.updateBookmarkDisplay($button, wasBookmarked);
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Bookmark error:', xhr);
					// Revert optimistic update
					self.updateBookmarkDisplay($button, wasBookmarked);
					self.handleAjaxError(xhr, $button);
				},
				complete: function() {
					$button.removeClass('loading');
				}
			});
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
		 * Mark chapter as read on server
		 */
		markAsRead: function(storyId, chapterNumber, $button) {
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
						self.updateReadDisplay($button, true);
						self.showSuccess($button, response.data.message || self.strings.markedRead);
					} else {
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Mark as read error:', xhr);
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

				if (starRating <= rating) {
					$star.addClass('filled').removeClass('empty');
				} else {
					$star.addClass('empty').removeClass('filled');
				}
			});

			$widget.attr('data-user-rating', rating);
		},

		/**
		 * Update like display (optimistic)
		 */
		updateLikeDisplay: function($button, isLiked) {
			if (isLiked) {
				$button.addClass('liked').removeClass('unliked');
				$button.find('.like-text').text($button.data('liked-text') || 'Liked');
			} else {
				$button.addClass('unliked').removeClass('liked');
				$button.find('.like-text').text($button.data('like-text') || 'Like');
			}
		},

		/**
		 * Update like count
		 */
		updateLikeCount: function($button, count) {
			const $count = $button.find('.like-count');
			if ($count.length) {
				$count.text(count);
			}
		},

		/**
		 * Update bookmark display (optimistic)
		 */
		updateBookmarkDisplay: function($button, isBookmarked) {
			if (isBookmarked) {
				$button.addClass('bookmarked').removeClass('unbookmarked');
				$button.find('.bookmark-text').text($button.data('bookmarked-text') || 'Bookmarked');
			} else {
				$button.addClass('unbookmarked').removeClass('bookmarked');
				$button.find('.bookmark-text').text($button.data('bookmark-text') || 'Bookmark');
			}
		},

		/**
		 * Update follow display (optimistic)
		 */
		updateFollowDisplay: function($button, isFollowing) {
			if (isFollowing) {
				$button.addClass('following').removeClass('unfollowing');
				$button.find('.follow-text').text($button.data('following-text') || 'Following');
			} else {
				$button.addClass('unfollowing').removeClass('following');
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
		 * Update read display (optimistic)
		 */
		updateReadDisplay: function($button, isRead) {
			if (isRead) {
				$button.addClass('read').prop('disabled', true);
				$button.find('.read-text').text($button.data('read-text') || 'Read');
			}
		},

		/**
		 * Update stats display from server data
		 */
		updateStatsDisplay: function($widget, data) {
			if (data.rating_count !== undefined) {
				$widget.find('.rating-count').text(data.rating_count);
			}
			if (data.rating_average !== undefined) {
				$widget.find('.rating-average').text(data.rating_average.toFixed(1));
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

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		FanficInteractions.init();
	});

	// Expose to global scope
	window.FanficInteractions = FanficInteractions;

})(jQuery);
