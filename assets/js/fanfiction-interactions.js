/**
 * Fanfiction Interactions JavaScript
 *
 * Unified interaction handling for all user actions:
 * - Ratings (1-5 stars)
 * - Likes (toggle)
 * - Follows (toggle) with optional email subscription
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
			followAdded: 'Follow added!',
			followRemoved: 'Follow removed',
			markedRead: 'Marked as read',
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

			// Follow buttons
			this.initFollowButtons();

			// Follow email modal handlers
			this.initFollowEmailModal();

			// Reading progress
			this.initReadingProgress();

			// Share buttons
			this.initShareButtons();
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

				// Get old user rating for optimistic average calculation
				const oldRating = parseFloat($widget.attr('data-user-rating')) || 0;

				// Optimistic UI update (stars)
				self.updateRatingDisplay($widget, rating);

				// Optimistic average/count update
				FanficUnifiedInteractions.optimisticRatingUpdate(oldRating, rating);

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
		 * Initialize follow buttons (localStorage-first, anonymous-capable)
		 */
		initFollowButtons: function() {
			const self = this;

			$(document).on('click', '.fanfic-follow-button, .fanfic-button-follow', function(e) {
				e.preventDefault();

				const $button = $(this);
				const postId = parseInt($button.data('post-id'), 10);
				const storyId = parseInt($button.data('story-id'), 10) || 0;
				const chapterId = parseInt($button.data('chapter-id'), 10) || 0;
				const isLoggedIn = !!parseInt($button.data('user-logged-in'), 10);

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Follow button clicked:', { postId, storyId, chapterId, isLoggedIn });

				// Toggle in localStorage immediately
				const entry = FanficLocalStore.toggleFollow(storyId, chapterId);
				const isNowFollowed = !!entry.follow;

				// Auto-follow parent story when following a chapter
				if (isNowFollowed && chapterId > 0 && storyId > 0) {
					FanficLocalStore.setFollow(storyId, 0, true);
					// Update any story-level follow buttons on page
					self.updateStoryFollowButtons(storyId, true);
					self.updateBadges();
				}

				// Optimistic UI update
				self.updateFollowDisplay($button, isNowFollowed);
				self.updateBadges();

				// Optimistic follow count update
				self.adjustCount('.fanfic-follow-count', isNowFollowed ? 1 : -1, function(count) {
					if (count === 0) { return ''; }
					return count + (count === 1 ? ' follow' : ' follows');
				});

				// For logged-out users following (not unfollowing), show email modal
				if (!isLoggedIn && isNowFollowed) {
					self._pendingFollowData = { postId: postId, $button: $button, wasFollowed: !isNowFollowed, storyId: storyId, chapterId: chapterId };
					self.showFollowEmailModal();
					return;
				}

				// For logged-out users unfollowing, pass saved email so server can unsubscribe
				var unfollowEmail = null;
				if (!isLoggedIn && !isNowFollowed) {
					try {
						unfollowEmail = window.localStorage.getItem('fanfic_user_email') || null;
					} catch (err) {}
				}

				// AJAX to server
				self.toggleFollow(postId, $button, !isNowFollowed, unfollowEmail);
			});
		},

		/**
		 * Initialize follow email modal handlers
		 */
		initFollowEmailModal: function() {
			const self = this;
			const $modal = $('#fanfic-follow-email-modal');

			if (!$modal.length) {
				return;
			}

			// Close modal handlers
			$modal.on('click', '.fanfic-modal-overlay, .fanfic-modal-close', function(e) {
				e.preventDefault();
				self.closeFollowEmailModal('follow_only');
			});

			// "Follow Only" button
			$modal.on('click', '.fanfic-follow-only-btn', function(e) {
				e.preventDefault();
				self.closeFollowEmailModal('follow_only');
			});

			// "Follow & Subscribe" button
			$modal.on('click', '.fanfic-follow-subscribe-btn', function(e) {
				e.preventDefault();
				const email = $modal.find('.fanfic-follow-email-input').val().trim();

				if (!self.validateEmail(email)) {
					self.showError($modal.find('.fanfic-modal-body'), 'Please enter a valid email address.');
					return;
				}

				// Store email for future use
				try {
					window.localStorage.setItem('fanfic_user_email', email);
				} catch (err) {}

				self.closeFollowEmailModal('follow_subscribe', email);
			});

			// Close on Escape key
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $modal.is(':visible')) {
					self.closeFollowEmailModal('follow_only');
				}
			});
		},

		/**
		 * Show the follow email modal for logged-out users
		 */
		showFollowEmailModal: function() {
			const $modal = $('#fanfic-follow-email-modal');
			if (!$modal.length) {
				// No modal on page — just send AJAX without email
				if (this._pendingFollowData) {
					this.toggleFollow(this._pendingFollowData.postId, this._pendingFollowData.$button, this._pendingFollowData.wasFollowed);
					this._pendingFollowData = null;
				}
				return;
			}

			// Pre-fill email from localStorage
			var savedEmail = '';
			try {
				savedEmail = window.localStorage.getItem('fanfic_user_email') || '';
			} catch (err) {}
			$modal.find('.fanfic-follow-email-input').val(savedEmail);

			// Show modal
			$modal.fadeIn(200);
		},

		/**
		 * Close follow email modal and send AJAX
		 */
		closeFollowEmailModal: function(action, email) {
			$('#fanfic-follow-email-modal').fadeOut(200);

			if (!this._pendingFollowData) {
				return;
			}

			var data = this._pendingFollowData;
			this._pendingFollowData = null;

			// Send AJAX with or without email
			this.toggleFollow(data.postId, data.$button, data.wasFollowed, email || null);
		},

		/**
		 * Update all story-level follow buttons on the page
		 */
		updateStoryFollowButtons: function(storyId, isFollowed) {
			var self = this;
			$('.fanfic-follow-button[data-story-id="' + storyId + '"][data-chapter-id="0"], .fanfic-button-follow[data-story-id="' + storyId + '"][data-chapter-id="0"]').each(function() {
				self.updateFollowDisplay($(this), isFollowed);
			});
		},

		/**
		 * Update badge visibility from localStorage
		 */
		updateBadges: function() {
			// Following badges (story-level: chapter_id = 0)
			$('.fanfic-badge-following[data-badge-story-id]').each(function() {
				var storyId = parseInt($(this).data('badge-story-id'), 10);
				var entry = FanficLocalStore.getChapter(storyId, 0);
				$(this).toggle(!!(entry && entry.follow));
			});

			// Bookmarked badges (chapter-level)
			$('.fanfic-badge-bookmarked[data-badge-story-id][data-badge-chapter-id]').each(function() {
				var storyId = parseInt($(this).data('badge-story-id'), 10);
				var chapterId = parseInt($(this).data('badge-chapter-id'), 10);
				var entry = FanficLocalStore.getChapter(storyId, chapterId);
				$(this).toggle(!!(entry && entry.follow));
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
		 * Toggle follow on server (supports anonymous via UUID)
		 */
		toggleFollow: function(postId, $button, wasFollowed, email) {
			const self = this;

			$button.addClass('loading');

			const payload = {
				action: 'fanfic_toggle_follow',
				nonce: this.config.nonce,
				post_id: postId
			};
			if (!this.config.isLoggedIn) {
				const anonymousUuid = FanficLocalStore.getAnonymousUuid();
				if (anonymousUuid) {
					payload.anonymous_uuid = anonymousUuid;
				}
			}
			if (email) {
				payload.email = email;
			}

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(response) {
					self.log('Follow response:', response);

					if (response.success) {
						const isFollowed = response.data.data.is_followed;
						self.updateFollowDisplay($button, isFollowed);
						self.updateBadges();

						// Server-authoritative follow count correction
						if (response.data.data.follow_count !== undefined) {
							$('.fanfic-follow-count').each(function() {
								var $el = $(this);
								var count = parseInt(response.data.data.follow_count, 10);
								$el.data('count', count);
								if (count === 0) { $el.text(''); }
								else { $el.text(count + (count === 1 ? ' follow' : ' follows')); }
							});
						}

						self.showSuccess($button, response.data.message || (isFollowed ? self.strings.followAdded : self.strings.followRemoved));
					} else {
						// Revert optimistic update + localStorage
						self.revertFollowLocal($button, wasFollowed);
						self.updateBadges();
						self.showError($button, response.data.message || self.strings.error);
					}
				},
				error: function(xhr) {
					self.log('Follow error:', xhr);
					// Revert optimistic update + localStorage
					self.revertFollowLocal($button, wasFollowed);
					self.handleAjaxError(xhr, $button);
				},
				complete: function() {
					$button.removeClass('loading');
				}
			});
		},

		/**
		 * Revert follow in localStorage and UI on error
		 */
		revertFollowLocal: function($button, wasFollowed) {
			const storyId = parseInt($button.data('story-id'), 10) || 0;
			const chapterId = parseInt($button.data('chapter-id'), 10) || 0;
			// Toggle back to previous state
			FanficLocalStore.toggleFollow(storyId, chapterId);
			this.updateFollowDisplay($button, wasFollowed);
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
		 * Update follow display (optimistic)
		 */
		updateFollowDisplay: function($button, isFollowed) {
			if (isFollowed) {
				$button.addClass('fanfic-button-followed');
				$button.find('.follow-text').text($button.data('followed-text') || 'Followed');
			} else {
				$button.removeClass('fanfic-button-followed');
				$button.find('.follow-text').text($button.data('follow-text') || 'Follow');
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
				$widget.data('avg', data.rating_average);
			}

			// Update count display
			if (data.rating_count !== undefined) {
				const count = parseInt(data.rating_count);
				$widget.data('count', count);
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

		toggleFollow: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId) || {};
			if (entry.follow) {
				delete entry.follow;
			} else {
				entry.follow = true;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		setFollow: function(storyId, chapterId, followed) {
			const entry = this.getChapter(storyId, chapterId) || {};
			if (followed) {
				entry.follow = true;
			} else {
				delete entry.follow;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		isFollowed: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId);
			return !!(entry && entry.follow);
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
			const self = this;
			const storyId = this.context.storyId;
			const chapterId = this.context.chapterId;
			if (FanficLocalStore.hasViewed(storyId, chapterId)) {
				return;
			}
			FanficLocalStore.recordView(storyId, chapterId);

			// Optimistic view count update (+1)
			this.adjustCount('.fanfic-view-count', 1, function(count) {
				if (count === 1) { return '1 view'; }
				return count + ' views';
			});

			$.post(this.config.ajaxUrl, {
				action: 'fanfic_record_view',
				nonce: this.config.nonce,
				chapter_id: chapterId,
				story_id: storyId
			}).done(function(response) {
				// Correct with server value if available
				if (response && response.data && response.data.data && response.data.data.stats) {
					self.applyStatsToCountElements(response.data.data.stats);
				}
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
				const wasLiked = FanficLocalStore.getChapter(storyId, chapterId);
				const hadLike = !!(wasLiked && wasLiked.like);
				const entry = FanficLocalStore.toggleLike(storyId, chapterId);
				self.applyUiFromLocal();

				// Optimistic like count update
				self.adjustCount('.fanfic-like-count', hadLike ? -1 : 1, function(count) {
					if (count === 1) { return '1 like'; }
					return count + ' likes';
				});

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

				// Get old rating before updating localStorage
				var oldEntry = FanficLocalStore.getChapter(storyId, chapterId) || {};
				var oldRating = oldEntry.rating ? parseFloat(oldEntry.rating) : 0;

				FanficLocalStore.setRating(storyId, chapterId, value);
				self.applyUiFromLocal();

				// Optimistic average/count update
				self.optimisticRatingUpdate(oldRating, value);

				self.postInteraction('rating', value, chapterId);
			});
		},

		postInteraction: function(type, value, chapterId) {
			const self = this;
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
			$.post(this.config.ajaxUrl, payload).done(function(response) {
				// Correct counts from server response
				if (response && response.data && response.data.data && response.data.data.stats) {
					self.applyStatsToCountElements(response.data.data.stats);
				}
			});
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

		/**
		 * Optimistically adjust a count element by a delta (+1 or -1)
		 *
		 * @param {string}   selector  CSS selector for the count element
		 * @param {number}   delta     Amount to adjust (+1 or -1)
		 * @param {function} formatter Function(count) returning display text
		 */
		adjustCount: function(selector, delta, formatter) {
			$(selector).each(function() {
				var $el = $(this);
				var current = parseInt($el.data('count'), 10) || 0;
				var updated = Math.max(0, current + delta);
				$el.data('count', updated);
				$el.text(formatter(updated));
			});
		},

		/**
		 * Optimistically update rating average and count on all rating widgets
		 *
		 * @param {number} oldRating Previous user rating (0 if new)
		 * @param {number} newRating New user rating
		 */
		optimisticRatingUpdate: function(oldRating, newRating) {
			$('.fanfic-rating-widget, .fanfic-story-rating-display, .fanfic-story-rating').each(function() {
				var $container = $(this);
				var currentAvg = parseFloat($container.data('avg')) || 0;
				var currentCount = parseInt($container.data('count'), 10) || 0;
				var newAvg, newCount;

				if (oldRating > 0) {
					// Changing existing rating: count stays same
					newCount = currentCount;
					if (newCount > 0) {
						newAvg = (currentAvg * currentCount - oldRating + newRating) / newCount;
					} else {
						newAvg = newRating;
						newCount = 1;
					}
				} else {
					// New rating
					newCount = currentCount + 1;
					newAvg = (currentAvg * currentCount + newRating) / newCount;
				}

				newAvg = Math.max(0, Math.min(5, newAvg));

				// Update data attributes
				$container.data('avg', newAvg);
				$container.data('count', newCount);

				// Update display text
				$container.find('.fanfic-rating-average').text(newAvg.toFixed(1));
				var $countEl = $container.find('.fanfic-rating-count');
				if (newCount === 0) {
					$countEl.text('(No ratings yet)');
				} else if (newCount === 1) {
					$countEl.text('(1 rating)');
				} else {
					$countEl.text('(' + newCount + ' ratings)');
				}
			});
		},

		/**
		 * Apply authoritative server stats to all count elements on the page
		 *
		 * @param {object} stats Server stats object {views, likes, dislikes, rating_avg, rating_count}
		 */
		applyStatsToCountElements: function(stats) {
			if (!stats) {
				return;
			}

			if (stats.views !== undefined) {
				$('.fanfic-view-count').each(function() {
					var $el = $(this);
					var count = parseInt(stats.views, 10);
					$el.data('count', count);
					$el.text(count + (count === 1 ? ' view' : ' views'));
				});
			}

			if (stats.likes !== undefined) {
				$('.fanfic-like-count').each(function() {
					var $el = $(this);
					var count = parseInt(stats.likes, 10);
					$el.data('count', count);
					if (count === 0) {
						$el.text('');
					} else {
						$el.text(count + (count === 1 ? ' like' : ' likes'));
					}
				});
			}

			if (stats.follow_count !== undefined) {
				$('.fanfic-follow-count').each(function() {
					var $el = $(this);
					var count = parseInt(stats.follow_count, 10);
					$el.data('count', count);
					if (count === 0) { $el.text(''); }
					else { $el.text(count + (count === 1 ? ' follow' : ' follows')); }
				});
			}

			if (stats.rating_avg !== undefined && stats.rating_count !== undefined) {
				var avg = parseFloat(stats.rating_avg) || 0;
				var rCount = parseInt(stats.rating_count, 10) || 0;
				$('.fanfic-rating-widget, .fanfic-story-rating-display, .fanfic-story-rating').each(function() {
					var $c = $(this);
					$c.data('avg', avg);
					$c.data('count', rCount);
					$c.find('.fanfic-rating-average').text(avg.toFixed(1));
					var $countEl = $c.find('.fanfic-rating-count');
					if (rCount === 0) { $countEl.text('(No ratings yet)'); }
					else if (rCount === 1) { $countEl.text('(1 rating)'); }
					else { $countEl.text('(' + rCount + ' ratings)'); }
				});
			}
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

			// Apply follow state for chapter-level follow button
			$('.fanfic-follow-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], .fanfic-button-follow[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').each(function() {
				const $btn = $(this);
				const isFollowed = !!entry.follow;
				$btn.toggleClass('fanfic-button-followed', isFollowed);
				$btn.find('.follow-text').text(isFollowed ? ($btn.data('followed-text') || 'Followed') : ($btn.data('follow-text') || 'Follow'));
			});

			// Apply follow state for story-level follow button (chapter_id=0)
			const storyEntry = FanficLocalStore.getChapter(storyId, 0) || {};
			$('.fanfic-follow-button[data-story-id="' + storyId + '"][data-chapter-id="0"], .fanfic-button-follow[data-story-id="' + storyId + '"][data-chapter-id="0"]').each(function() {
				const $btn = $(this);
				const isFollowed = !!storyEntry.follow;
				$btn.toggleClass('fanfic-button-followed', isFollowed);
				$btn.find('.follow-text').text(isFollowed ? ($btn.data('followed-text') || 'Followed') : ($btn.data('follow-text') || 'Follow'));
			});

			// Update badges
			FanficInteractions.updateBadges();
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
