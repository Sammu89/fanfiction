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
	 * Convert a 0-1 fill fraction to an SVG star state string.
	 * Used by both FanficInteractions and FanficUnifiedInteractions.
	 */
	function fanficFillToState(fill) {
		if (fill < 0.25) { return 'empty'; }
		if (fill < 0.75) { return 'half'; }
		return 'full';
	}

	/**
	 * Apply a rating value to all .fanfic-star-wrap elements inside a stars container.
	 * Sets data-state on each wrap so CSS shows the correct SVG layer.
	 */
	function fanficPreviewStars($starsEl, rating) {
		$starsEl.find('.fanfic-star-wrap').each(function(index) {
			var fill = Math.max(0, Math.min(1, rating - index));
			$(this).attr('data-state', fanficFillToState(fill));
		});
	}

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
			loginRequired: 'You must be logged in to do that.',
			copiedLink: 'Copied link.',
			copyThisLinkPrompt: 'Copy this link:'
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
			this.hydrateFollowButtonsFromLocal();

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
		 * Hydrate follow buttons from localStorage on initial page load.
		 *
		 * If a button is already server-rendered as followed and localStorage has no
		 * explicit follow flag for that key yet, seed localStorage from server state.
		 */
		hydrateFollowButtonsFromLocal: function() {
			var self = this;

			$('.fanfic-follow-button, .fanfic-button-follow').each(function() {
				var $button = $(this);
				var storyId = parseInt($button.data('story-id'), 10) || 0;
				var chapterIdRaw = parseInt($button.data('chapter-id'), 10);
				var chapterId = Number.isFinite(chapterIdRaw) ? chapterIdRaw : 0;

				if (!storyId) {
					return;
				}

				var entry = FanficLocalStore.getChapter(storyId, chapterId);
				var hasLocalFollowFlag = !!(entry && Object.prototype.hasOwnProperty.call(entry, 'follow'));
				var isServerFollowed = $button.hasClass('fanfic-button-followed');

				// Seed local state from server-rendered follow state when needed.
				if (!hasLocalFollowFlag && isServerFollowed) {
					FanficLocalStore.setFollow(storyId, chapterId, true);
				}

				var isFollowed = FanficLocalStore.isFollowed(storyId, chapterId);
				if (!hasLocalFollowFlag && !isServerFollowed) {
					isFollowed = false;
				}

				self.updateFollowDisplay($button, isFollowed);
			});

			self.updateBadges();
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

			this.updateChapterIndicators();
		},

		/**
		 * Update chapter-scoped indicators from localStorage in one pass:
		 * - read indicator (entry.read)
		 * - bookmarked badge visibility (entry.follow)
		 */
		updateChapterIndicators: function() {
			var entryCache = {};
			var getEntry = function(storyId, chapterId) {
				var key = String(storyId) + ':' + String(chapterId);
				if (!Object.prototype.hasOwnProperty.call(entryCache, key)) {
					entryCache[key] = FanficLocalStore.getChapter(storyId, chapterId) || null;
				}
				return entryCache[key];
			};

			$('.fanfic-read-indicator[data-story-id][data-chapter-id], .fanfic-badge-bookmarked[data-badge-story-id][data-badge-chapter-id]').each(function() {
				var $node = $(this);
				var isReadIndicator = $node.hasClass('fanfic-read-indicator');
				var storyId = isReadIndicator
					? (parseInt($node.data('story-id'), 10) || 0)
					: (parseInt($node.data('badge-story-id'), 10) || 0);
				var chapterId = isReadIndicator
					? (parseInt($node.data('chapter-id'), 10) || 0)
					: (parseInt($node.data('badge-chapter-id'), 10) || 0);

				if (!storyId || !chapterId) {
					return;
				}

				var entry = getEntry(storyId, chapterId);

				if (isReadIndicator) {
					$node.toggleClass('fanfic-read-indicator-read', !!(entry && entry.read));
				} else {
					$node.toggle(!!(entry && entry.follow));
				}
			});
		},

		/**
		 * Initialize reading progress tracking
		 */
		initReadingProgress: function() {
			const self = this;

			$(document).on('click', '.fanfic-mark-read-button, .fanfic-button-mark-read', function(e) {
				e.preventDefault();

				const $button = $(this);
				const storyId = parseInt($button.data('story-id'), 10) || 0;
				const chapterNumber = parseInt($button.data('chapter-number'), 10) || 0;
				const chapterId = parseInt($button.data('chapter-id'), 10) || 0;

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				// Support legacy and current read-state classes.
				const isCurrentlyRead = $button.hasClass('fanfic-button-marked-read') ||
					$button.hasClass('fanfic-button-mark-readed') ||
					$button.hasClass('marked-read') ||
					$button.hasClass('is-markredd') ||
					$button.hasClass('read');

				self.log('Mark as read clicked:', { storyId, chapterNumber, isCurrentlyRead });

				// Optimistic UI update (toggle)
				self.updateReadDisplay($button, !isCurrentlyRead);

				// Persist optimistic local state and manual override:
				// - unread manually chosen => suppress future auto-read for this chapter
				// - manual read => clear suppression
				if (storyId && chapterId) {
					FanficLocalStore.setRead(storyId, chapterId, !isCurrentlyRead);
					FanficLocalStore.setReadAutoSuppressed(storyId, chapterId, isCurrentlyRead);
					if (window.FanficUnifiedInteractions && typeof window.FanficUnifiedInteractions.applyUiFromLocal === 'function') {
						window.FanficUnifiedInteractions.applyUiFromLocal();
					}
				}

				// If user manually unmarks, stop any in-flight auto-read timer immediately.
				if (isCurrentlyRead && window.FanficUnifiedInteractions && window.FanficUnifiedInteractions.readTimer) {
					window.clearInterval(window.FanficUnifiedInteractions.readTimer);
					window.FanficUnifiedInteractions.readTimer = null;
					window.FanficUnifiedInteractions.activeReadSeconds = 0;
				}

				// Apply read/unread status to server (explicit endpoint, not toggle ambiguity).
				self.markAsRead(storyId, chapterNumber, chapterId, $button, isCurrentlyRead);
			});
		},

		/**
		 * Initialize share buttons
		 */
		initShareButtons: function() {
			const self = this;

			$(document).on('click', '.fanfic-button-share', function(e) {
				e.preventDefault();

				const $button = $(this);
				const url = self.getShareUrl($button);
				const title = self.getShareTitle($button);
				const text = self.getShareText($button);
				const shareData = { url: url };
				if (title) {
					shareData.title = title;
				}
				if (text) {
					shareData.text = text;
				}

				// Prevent double-click
				if ($button.hasClass('loading')) {
					return;
				}

				self.log('Share button clicked:', { url, title, hasText: !!text });

				if (typeof navigator.share === 'function') {
					const compatibleShareData = self.getCompatibleShareData(shareData);
					if (!compatibleShareData) {
						self.log('No compatible share payload, falling back to copy');
						self.copyToClipboard(url, $button);
						return;
					}

					self.attemptNativeShare(compatibleShareData, url, $button);
					return;
				}

				self.copyToClipboard(url, $button);
			});
		},

		/**
		 * Pick a share payload the current browser reports as shareable.
		 */
		getCompatibleShareData: function(shareData) {
			if (typeof navigator.canShare !== 'function') {
				return shareData;
			}

			if (navigator.canShare(shareData)) {
				return shareData;
			}

			const titleAndUrlData = { url: shareData.url };
			if (shareData.title) {
				titleAndUrlData.title = shareData.title;
			}
			if (navigator.canShare(titleAndUrlData)) {
				return titleAndUrlData;
			}

			const urlOnlyData = { url: shareData.url };
			if (navigator.canShare(urlOnlyData)) {
				return urlOnlyData;
			}

			return null;
		},

		/**
		 * Attempt native share and detect no-op resolves in some desktop environments.
		 */
		attemptNativeShare: function(shareData, fallbackUrl, $button) {
			const self = this;
			const startedAt = Date.now();
			let dialogSignal = false;

			const onBlur = function() {
				dialogSignal = true;
			};
			const onVisibilityChange = function() {
				if (document.visibilityState !== 'visible') {
					dialogSignal = true;
				}
			};

			window.addEventListener('blur', onBlur, true);
			document.addEventListener('visibilitychange', onVisibilityChange, true);

			const cleanup = function() {
				window.removeEventListener('blur', onBlur, true);
				document.removeEventListener('visibilitychange', onVisibilityChange, true);
			};

			navigator.share(shareData).then(function() {
				cleanup();

				const elapsedMs = Date.now() - startedAt;
				const looksLikeNoOp = !dialogSignal && elapsedMs < 120;

				if (looksLikeNoOp) {
					self.log('Share resolved without UI signal, falling back to copy');
					self.copyToClipboard(fallbackUrl, $button);
					return;
				}

				self.log('Share successful via Web Share API');
				// Don't show success message for native share - the OS handles it
			}).catch(function(err) {
				cleanup();

				if (self.isShareCanceled(err)) {
					self.log('Share canceled by user');
					return;
				}
				self.log('Share failed, falling back to copy:', err);
				self.copyToClipboard(fallbackUrl, $button);
			});
		},

		/**
		 * Get canonical share URL from button context when available.
		 */
		getShareUrl: function($button) {
			const contextUrl = String($button.attr('data-share-url') || '').trim();
			if (contextUrl) {
				return contextUrl;
			}

			const canonicalLink = document.querySelector('link[rel="canonical"]');
			if (canonicalLink && canonicalLink.href) {
				return canonicalLink.href;
			}

			return window.location.href;
		},

		/**
		 * Get share title from button context.
		 */
		getShareTitle: function($button) {
			const contextTitle = String($button.attr('data-share-title') || '').trim();
			if (contextTitle) {
				return contextTitle;
			}

			return document.title || '';
		},

		/**
		 * Get optional share text from button context.
		 */
		getShareText: function($button) {
			return String($button.attr('data-share-text') || '').trim();
		},

		/**
		 * Detect user-cancelled native share attempts.
		 */
		isShareCanceled: function(err) {
			if (!err) {
				return false;
			}

			const name = String(err.name || '').toLowerCase();
			const message = String(err.message || '').toLowerCase();
			return name === 'aborterror' || message.indexOf('cancel') !== -1 || message.indexOf('aborted') !== -1;
		},

		/**
		 * Copy URL to clipboard
		 */
		copyToClipboard: function(url, $button) {
			const self = this;

			if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
				navigator.clipboard.writeText(url).then(function() {
					self.log('Link copied to clipboard API');
					self.showSuccess($button, self.strings.copiedLink || 'Copied link.');
				}).catch(function(err) {
					self.log('Clipboard API copy failed, trying legacy copy:', err);
					self.showPromptCopy(url, $button);
				});
				return;
			}

			self.showPromptCopy(url, $button);
		},

		/**
		 * Legacy copy fallback via execCommand, then manual prompt as last resort.
		 */
		showPromptCopy: function(url, $button) {
			const $temp = $('<textarea readonly></textarea>')
				.val(url)
				.css({
					position: 'fixed',
					top: '0',
					left: '-9999px',
					opacity: '0'
				})
				.appendTo('body');

			$temp.trigger('focus');
			$temp.trigger('select');

			let copied = false;

			try {
				copied = document.execCommand('copy');
				if (copied) {
					this.showSuccess($button, this.strings.copiedLink || 'Copied link.');
					this.log('Link copied via execCommand');
				} else {
					this.log('execCommand copy returned false');
				}
			} catch (err) {
				this.log('execCommand copy failed:', err);
			}

			if (!copied) {
				const promptText = this.strings.copyThisLinkPrompt || 'Copy this link:';
				alert(promptText + '\n\n' + url);
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
		 * Persist chapter read status to unified interactions endpoint.
		 * Local state remains the source of truth; sync is best-effort.
		 */
		markAsRead: function(storyId, chapterNumber, chapterId, $button, wasRead) {
			const self = this;
			const desiredReadState = !wasRead;
			const interactionType = desiredReadState ? 'read' : 'remove_read';

			if (!chapterId) {
				return;
			}

			// Anonymous readers are local-only for read/unread state.
			if (!this.config.isLoggedIn) {
				self.log('Mark as read kept local for anonymous user');
				return;
			}

			// Keep offline behavior local-first with no rollback.
			if (typeof navigator !== 'undefined' && navigator.onLine === false) {
				self.log('Mark as read skipped server sync while offline');
				return;
			}

			$button.addClass('loading');

			const payload = {
				action: 'fanfic_record_interaction',
				nonce: this.config.nonce,
				chapter_id: chapterId,
				type: interactionType
			};

			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(response) {
					self.log('Mark as read response:', response);

					if (response.success) {
						// Keep local desired state; server response for this endpoint does not carry read boolean.
						const isRead = desiredReadState;
						self.updateReadDisplay($button, isRead);
						if (storyId && chapterId) {
							FanficLocalStore.setRead(storyId, chapterId, isRead);
							FanficLocalStore.setReadAutoSuppressed(storyId, chapterId, !isRead);
							if (window.FanficUnifiedInteractions && typeof window.FanficUnifiedInteractions.applyUiFromLocal === 'function') {
								window.FanficUnifiedInteractions.applyUiFromLocal();
							}
						}
					} else {
						// Do not revert local state; treat server sync as best-effort.
						self.log('Mark as read sync failed, local state kept:', response);
					}
				},
				error: function(xhr) {
					self.log('Mark as read error:', xhr);
					// Do not revert local state on network/server failure.
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
				$button.addClass('fanfic-button-marked-read marked-read is-markredd read');
				$button.removeClass('fanfic-button-mark-readed');
				$button.find('.read-text').text($button.data('read-text') || 'Read');
			} else {
				$button.removeClass('fanfic-button-marked-read fanfic-button-mark-readed marked-read is-markredd read');
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
			const entry = this.getChapter(storyId, chapterId);
			return !!(entry && entry.view);
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

		setReadAutoSuppressed: function(storyId, chapterId, suppressed) {
			const entry = this.getChapter(storyId, chapterId) || {};
			if (suppressed) {
				entry.read_auto_suppressed = true;
			} else {
				delete entry.read_auto_suppressed;
			}
			entry.timestamp = Date.now();
			this.setChapter(storyId, chapterId, entry);
			return entry;
		},

		isReadAutoSuppressed: function(storyId, chapterId) {
			const entry = this.getChapter(storyId, chapterId) || {};
			return !!entry.read_auto_suppressed;
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

		mergeFromServer: function(merged, preserveSinceTs) {
			if (!merged || typeof merged !== 'object') {
				return;
			}

			const serverData = merged;
			const hasPreserveTs = Number.isFinite(preserveSinceTs) && preserveSinceTs > 0;

			if (!hasPreserveTs) {
				this.saveAll(serverData);
				return;
			}

			const localData = this.getAll();
			const finalData = Object.assign({}, serverData);

			Object.keys(localData).forEach(function(key) {
				const localEntry = localData[key];
				if (!localEntry || typeof localEntry !== 'object') {
					return;
				}

				const localTs = parseInt(localEntry.timestamp, 10) || 0;
				const serverHasKey = Object.prototype.hasOwnProperty.call(serverData, key);

				// Keep local entries created/updated after sync started to avoid stale overwrite.
				if (!serverHasKey || localTs >= preserveSinceTs) {
					finalData[key] = localEntry;
				}
			});

			this.saveAll(finalData);
		}
	};

	const FanficUnifiedInteractions = {
		init: function() {
			this.config = {
				ajaxUrl: fanficAjax.ajaxUrl || '/wp-admin/admin-ajax.php',
				nonce: fanficAjax.nonce || '',
				debug: !!fanficAjax.debug,
				isLoggedIn: !!fanficAjax.isLoggedIn,
				needsSync: !!fanficAjax.needsSync,
				enableDislikes: !!fanficAjax.enableDislikes
			};
			this.interactionLocks = {};
			this.likeDislikeLockMinMs = 700;
			this.likeDislikeLockMaxMs = 5000;
			this._readDebugLastHiddenLogAt = 0;
			this.context = this.detectChapterContext();
			this.activeReadSeconds = 0;
			this.readTimer = null;
			this.anonymousUuid = FanficLocalStore.getAnonymousUuid();
			this.debugRead('Unified interactions init', {
				context: this.context,
				isLoggedIn: this.config.isLoggedIn
			});

			if (!this.context) {
				this.debugRead('No chapter context detected; read tracking will not start');
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
			const $allNodes = $('[data-story-id][data-chapter-id]');
			this.debugRead('Detecting chapter context', { nodesFound: $allNodes.length });
			const $chapterNode = $allNodes.filter(function() {
				const storyId = parseInt($(this).data('story-id'), 10);
				const chapterId = parseInt($(this).data('chapter-id'), 10);
				return !!storyId && !!chapterId;
			}).first();
			const $node = $chapterNode.length ? $chapterNode : $allNodes.first();
			if (!$node.length) {
				this.debugRead('No [data-story-id][data-chapter-id] nodes found');
				return null;
			}
			const storyId = parseInt($node.data('story-id'), 10);
			const chapterId = parseInt($node.data('chapter-id'), 10);
			this.debugRead('Candidate chapter context', { storyId, chapterId });
			if (!storyId || !chapterId) {
				this.debugRead('Invalid chapter context candidate', { storyId, chapterId });
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
				this.debugRead('Read tracking skipped: no context');
				return;
			}
			const entry = FanficLocalStore.getChapter(this.context.storyId, this.context.chapterId) || {};
			if (entry.read_auto_suppressed) {
				this.debugRead('Read tracking disabled by manual unread override', {
					storyId: this.context.storyId,
					chapterId: this.context.chapterId
				});
				return;
			}
			this.debugRead('Read tracking initial state', {
				storyId: this.context.storyId,
				chapterId: this.context.chapterId,
				alreadyRead: !!entry.read
			});
			if (entry.read) {
				this.debugRead('Read tracking skipped: chapter already read in local storage');
				return;
			}
			const self = this;
			this.readTimer = window.setInterval(function() {
				if (document.hidden) {
					const now = Date.now();
					if (!self._readDebugLastHiddenLogAt || (now - self._readDebugLastHiddenLogAt) >= 5000) {
						self.debugRead('Timer paused: tab hidden', { activeReadSeconds: self.activeReadSeconds });
						self._readDebugLastHiddenLogAt = now;
					}
					return;
				}
				self.activeReadSeconds += 1;
				if (self.activeReadSeconds === 1 || self.activeReadSeconds % 10 === 0 || self.activeReadSeconds >= 115) {
					self.debugRead('Active read timer tick', { activeReadSeconds: self.activeReadSeconds });
				}
				if (self.activeReadSeconds < 120) {
					return;
				}
				self.debugRead('Read threshold reached; applying read state', {
					activeReadSeconds: self.activeReadSeconds,
					storyId: self.context.storyId,
					chapterId: self.context.chapterId
				});
				window.clearInterval(self.readTimer);
				self.readTimer = null;
				FanficLocalStore.setRead(self.context.storyId, self.context.chapterId, true);
				FanficLocalStore.setReadAutoSuppressed(self.context.storyId, self.context.chapterId, false);
				self.debugRead('Local read state saved', FanficLocalStore.getChapter(self.context.storyId, self.context.chapterId) || {});
				self.applyUiFromLocal();
				if (self.config.isLoggedIn) {
					self.debugRead('Posting read interaction to server');
					self.postInteraction('read');
				}
			}, 1000);
			this.debugRead('Read timer started');
		},

		bindLikeDislike: function() {
			const self = this;
			$(document).on('click', '.fanfic-like-button[data-story-id][data-chapter-id], .fanfic-button-like[data-story-id][data-chapter-id]', function(e) {
				e.preventDefault();
				const storyId = parseInt($(this).data('story-id'), 10);
				const chapterId = parseInt($(this).data('chapter-id'), 10);
				if (!storyId || !chapterId) {
					return;
				}
				const lockKey = self.getChapterInteractionLockKey(storyId, chapterId);
				if (self.isChapterInteractionLocked(lockKey)) {
					return;
				}
				const lockStartedAt = Date.now();
				self.lockChapterInteraction(lockKey, storyId, chapterId);

				const wasLiked = FanficLocalStore.getChapter(storyId, chapterId);
				const hadLike = !!(wasLiked && wasLiked.like);
				const entry = FanficLocalStore.toggleLike(storyId, chapterId);
				self.applyUiFromLocal();

				// Optimistic like count update
				self.adjustCount('.like-count', hadLike ? -1 : 1, function(count) {
					return count > 0 ? '(' + count + ')' : '';
				});

				const request = self.postInteraction(entry.like ? 'like' : 'remove_like', null, chapterId);
				const releaseLock = function() {
					const elapsed = Date.now() - lockStartedAt;
					const waitMs = Math.max(0, self.likeDislikeLockMinMs - elapsed);
					window.setTimeout(function() {
						self.unlockChapterInteraction(lockKey, storyId, chapterId);
					}, waitMs);
				};

				if (request && typeof request.always === 'function') {
					request.always(releaseLock);
				} else {
					releaseLock();
				}
			});

			$(document).on('click', '.fanfic-dislike-button[data-story-id][data-chapter-id], .fanfic-button-dislike[data-story-id][data-chapter-id]', function(e) {
				e.preventDefault();
				if (!self.config.enableDislikes) {
					return;
				}
				const storyId = parseInt($(this).data('story-id'), 10);
				const chapterId = parseInt($(this).data('chapter-id'), 10);
				if (!storyId || !chapterId) {
					return;
				}
				const lockKey = self.getChapterInteractionLockKey(storyId, chapterId);
				if (self.isChapterInteractionLocked(lockKey)) {
					return;
				}
				const lockStartedAt = Date.now();
				self.lockChapterInteraction(lockKey, storyId, chapterId);

				const wasDisliked = FanficLocalStore.getChapter(storyId, chapterId);
				const hadDislike = !!(wasDisliked && wasDisliked.dislike);
				const entry = FanficLocalStore.toggleDislike(storyId, chapterId);
				self.applyUiFromLocal();

				// Optimistic dislike count update
				self.adjustCount('.dislike-count', hadDislike ? -1 : 1, function(count) {
					return count > 0 ? '(' + count + ')' : '';
				});

				const request = self.postInteraction(entry.dislike ? 'dislike' : 'remove_dislike', null, chapterId);
				const releaseLock = function() {
					const elapsed = Date.now() - lockStartedAt;
					const waitMs = Math.max(0, self.likeDislikeLockMinMs - elapsed);
					window.setTimeout(function() {
						self.unlockChapterInteraction(lockKey, storyId, chapterId);
					}, waitMs);
				};

				if (request && typeof request.always === 'function') {
					request.always(releaseLock);
				} else {
					releaseLock();
				}
			});
		},

		getChapterInteractionLockKey: function(storyId, chapterId) {
			return String(storyId) + ':' + String(chapterId);
		},

		isChapterInteractionLocked: function(lockKey) {
			return !!(this.interactionLocks && this.interactionLocks[lockKey]);
		},

		lockChapterInteraction: function(lockKey, storyId, chapterId) {
			const self = this;
			if (!this.interactionLocks) {
				this.interactionLocks = {};
			}

			// Replace stale lock for same chapter if it exists.
			if (this.interactionLocks[lockKey] && this.interactionLocks[lockKey].timeoutId) {
				window.clearTimeout(this.interactionLocks[lockKey].timeoutId);
			}

			const timeoutId = window.setTimeout(function() {
				self.unlockChapterInteraction(lockKey, storyId, chapterId);
			}, this.likeDislikeLockMaxMs);

			this.interactionLocks[lockKey] = {
				timeoutId: timeoutId,
				storyId: storyId,
				chapterId: chapterId
			};

			this.setLikeDislikeButtonsDisabled(storyId, chapterId, true);
		},

		unlockChapterInteraction: function(lockKey, storyId, chapterId) {
			if (!this.interactionLocks || !this.interactionLocks[lockKey]) {
				this.setLikeDislikeButtonsDisabled(storyId, chapterId, false);
				return;
			}

			const lock = this.interactionLocks[lockKey];
			if (lock.timeoutId) {
				window.clearTimeout(lock.timeoutId);
			}

			delete this.interactionLocks[lockKey];
			this.setLikeDislikeButtonsDisabled(storyId, chapterId, false);
		},

		setLikeDislikeButtonsDisabled: function(storyId, chapterId, disabled) {
			const selector =
				'.fanfic-like-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], ' +
				'.fanfic-button-like[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], ' +
				'.fanfic-dislike-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], ' +
				'.fanfic-button-dislike[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]';

			const $buttons = $(selector);
			$buttons.prop('disabled', !!disabled);
			$buttons.attr('aria-disabled', disabled ? 'true' : 'false');
		},

		bindRating: function() {
			const self = this;

			$(document).on('mouseenter', '.fanfic-rating-stars-half .fanfic-star-hit', function() {
				const $hit = $(this);
				const $starsEl = $hit.closest('.fanfic-rating-stars-half');
				// Left half of star 1 previews as 0 (remove)
				const isRemoveHit = $hit.hasClass('fanfic-star-hit-left') &&
					$hit.closest('.fanfic-star-wrap').data('star') == 1;
				const previewValue = isRemoveHit ? 0 : parseFloat($hit.data('value'));
				fanficPreviewStars($starsEl, previewValue);
			});

			$(document).on('mouseleave', '.fanfic-rating-stars-half', function() {
				const $starsEl = $(this);
				const savedRating = parseFloat($starsEl.attr('data-rating')) || 0;
				fanficPreviewStars($starsEl, savedRating);
			});

			$(document).on('click', '.fanfic-rating-stars-half .fanfic-star-hit', function(e) {
				e.preventDefault();
				const $hit = $(this);
				const $root = $hit.closest('[data-story-id][data-chapter-id]');
				const storyId = parseInt($root.data('story-id'), 10);
				const chapterId = parseInt($root.data('chapter-id'), 10);
				if (!storyId || !chapterId) {
					return;
				}

				// Left half of star 1 = remove rating
				const isRemoveHit = $hit.hasClass('fanfic-star-hit-left') &&
					$hit.closest('.fanfic-star-wrap').data('star') == 1;

				var oldEntry = FanficLocalStore.getChapter(storyId, chapterId) || {};
				var oldRating = oldEntry.rating ? parseFloat(oldEntry.rating) : 0;

				if (isRemoveHit) {
					FanficLocalStore.setRating(storyId, chapterId, null);
					self.applyUiFromLocal();
					self.optimisticRatingUpdate(oldRating, 0);
					self.postInteraction('remove_rating', null, chapterId);
					return;
				}

				const value = parseFloat($hit.data('value'));
				if (!value) {
					return;
				}

				FanficLocalStore.setRating(storyId, chapterId, value);
				self.applyUiFromLocal();
				self.optimisticRatingUpdate(oldRating, value);
				self.postInteraction('rating', value, chapterId);
			});
		},

		postInteraction: function(type, value, chapterId) {
			const self = this;
			const finalChapterId = chapterId || (this.context ? this.context.chapterId : 0);
			if (!finalChapterId) {
				return null;
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
			return $.post(this.config.ajaxUrl, payload).done(function(response) {
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
			const syncStartedAt = Date.now();
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
					FanficLocalStore.mergeFromServer(merged, syncStartedAt);
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
				if ($container.hasClass('fanfic-story-rating-display')) {
					$countEl.text('(' + newCount + ')');
				} else if (newCount === 0) {
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
				$('.like-count').each(function() {
					var $el = $(this);
					var count = parseInt(stats.likes, 10);
					$el.data('count', count);
					$el.text('(' + count + ')');
				});
			}

			if (stats.dislikes !== undefined) {
				$('.dislike-count').each(function() {
					var $el = $(this);
					var count = parseInt(stats.dislikes, 10);
					$el.data('count', count);
					$el.text('(' + count + ')');
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
					if ($c.hasClass('fanfic-story-rating-display')) { $countEl.text('(' + rCount + ')'); }
					else if (rCount === 0) { $countEl.text('(No ratings yet)'); }
					else if (rCount === 1) { $countEl.text('(1 rating)'); }
					else { $countEl.text('(' + rCount + ' ratings)'); }
				});
			}
		},

		applyUiFromLocal: function() {
			if (!this.context) {
				this.debugRead('applyUiFromLocal skipped: no context');
				return;
			}
			const self = this;
			const storyId = this.context.storyId;
			const chapterId = this.context.chapterId;
			const entry = FanficLocalStore.getChapter(storyId, chapterId) || {};
			this.debugRead('Applying UI from local state', {
				storyId,
				chapterId,
				entryRead: !!entry.read
			});

			$('.fanfic-like-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], .fanfic-button-like[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').toggleClass('fanfic-button-liked', !!entry.like);
			$('.fanfic-dislike-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], .fanfic-button-dislike[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').toggleClass('fanfic-button-disliked', !!entry.dislike);
			$('.fanfic-read-indicator[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]').toggleClass('fanfic-read-indicator-read', !!entry.read);

			const $readButtons = $('.fanfic-mark-read-button[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"], .fanfic-button-mark-read[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"]');
			this.debugRead('Read button sync target count', { count: $readButtons.length });
			$readButtons.each(function(index) {
				const $btn = $(this);
				const isRead = !!entry.read;

				if (isRead) {
					$btn.addClass('fanfic-button-marked-read marked-read is-markredd read');
					$btn.removeClass('fanfic-button-mark-readed');
					$btn.find('.read-text').text($btn.data('read-text') || 'Read');
				} else {
					$btn.removeClass('fanfic-button-marked-read fanfic-button-mark-readed marked-read is-markredd read');
					$btn.find('.read-text').text($btn.data('unread-text') || 'Mark as Read');
				}

				if (index === 0) {
					self.debugRead('Read button after sync', {
						className: $btn.attr('class'),
						label: $btn.find('.read-text').text()
					});
				}
			});

			const rating = entry.rating ? parseFloat(entry.rating) : 0;
			$('.fanfic-rating-widget[data-story-id="' + storyId + '"][data-chapter-id="' + chapterId + '"] .fanfic-rating-stars-half').each(function() {
				fanficPreviewStars($(this), rating);
				$(this).attr('data-rating', rating);
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
		},

		isReadDebugEnabled: function() {
			if (this.config && this.config.debug) {
				return true;
			}
			try {
				if (window.localStorage.getItem('fanfic_read_debug') === '1') {
					return true;
				}
			} catch (e) {}
			return /(?:\?|&)fanfic_read_debug=1(?:&|$)/.test(window.location.search || '');
		},

		debugRead: function() {
			if (!this.isReadDebugEnabled()) {
				return;
			}
			console.log('[FanficReadDebug]', ...arguments);
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
