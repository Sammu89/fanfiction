/**
 * Fanfiction Manager - Like System JavaScript (v2.0)
 *
 * Handles like/unlike toggle with browser fingerprinting.
 * Features:
 * - Browser fingerprint generation (shared with ratings)
 * - Binary toggle (like/unlike)
 * - Real-time updates with incremental cache
 * - Anonymous + logged-in user support
 *
 * @package FanfictionManager
 * @since 2.0.0
 */

(function($) {
	'use strict';

	var userFingerprint = null; // Cached fingerprint for session

	/**
	 * Collect browser fingerprint
	 * Same as rating system for consistency
	 */
	function collectFingerprint() {
		return JSON.stringify({
			ua: navigator.userAgent,
			screen: screen.width + 'x' + screen.height,
			tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
			lang: navigator.language,
			platform: navigator.platform,
			colorDepth: screen.colorDepth,
			cores: navigator.hardwareConcurrency || 0
		});
	}

	/**
	 * Get fingerprint (with caching)
	 */
	function getFingerprint() {
		if (!userFingerprint) {
			userFingerprint = collectFingerprint();
		}
		return userFingerprint;
	}

	/**
	 * Initialize like system
	 */
	function init() {
		// Initialize all like buttons
		$('.fanfic-like-button').each(function() {
			initLikeButton($(this));
		});

		// Click handler
		$(document).on('click', '.fanfic-like-button', handleLikeClick);
	}

	/**
	 * Initialize individual like button
	 */
	function initLikeButton($button) {
		var chapterId = $button.data('chapter-id');

		if (!chapterId) {
			return;
		}

		// Check like status
		checkLikeStatus(chapterId, function(data) {
			if (data.is_liked) {
				$button.addClass('liked');
			}
			updateLikeCount($button, data.count);
		});
	}

	/**
	 * Check if user has liked this chapter
	 */
	function checkLikeStatus(chapterId, callback) {
		$.ajax({
			url: fanficLikes.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_check_like_status',
				nonce: fanficLikes.nonce,
				chapter_id: chapterId,
				fingerprint: getFingerprint()
			},
			success: function(response) {
				if (response.success) {
					callback(response.data);
				}
			}
		});
	}

	/**
	 * Handle like button click
	 */
	function handleLikeClick(e) {
		e.preventDefault();

		var $button = $(this);
		var chapterId = $button.data('chapter-id');

		if (!chapterId || $button.hasClass('submitting')) {
			return;
		}

		// Disable temporarily
		$button.addClass('submitting');

		// Toggle like
		toggleLike(chapterId, $button);
	}

	/**
	 * Toggle like via AJAX
	 */
	function toggleLike(chapterId, $button) {
		$.ajax({
			url: fanficLikes.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_toggle_like',
				nonce: fanficLikes.nonce,
				chapter_id: chapterId,
				fingerprint: getFingerprint()
			},
			success: function(response) {
				$button.removeClass('submitting');

				if (response.success) {
					// Update button state
					if (response.data.is_liked) {
						$button.addClass('liked');
					} else {
						$button.removeClass('liked');
					}

					// Update counts
					updateLikeCount($button, response.data.chapter_count);

					// Update story count if present
					if ($button.data('story-id')) {
						updateStoryLikeCount($button.data('story-id'), response.data.story_count);
					}

					// Show message briefly
					showMessage($button, response.data.message, 'success');
				} else {
					showMessage($button, response.data.message || fanficLikes.strings.error, 'error');
				}
			},
			error: function() {
				$button.removeClass('submitting');
				showMessage($button, fanficLikes.strings.error, 'error');
			}
		});
	}

	/**
	 * Update like count display
	 */
	function updateLikeCount($button, count) {
		var $count = $button.find('.fanfic-like-count');

		if ($count.length) {
			$count.text(count);
		}
	}

	/**
	 * Update story like count (if on story page)
	 */
	function updateStoryLikeCount(storyId, count) {
		$('.fanfic-story-likes[data-story-id="' + storyId + '"] .fanfic-like-count').text(count);
	}

	/**
	 * Show message to user
	 */
	function showMessage($button, message, type) {
		// Create temporary message element
		var $message = $('<span class="fanfic-like-message"></span>')
			.addClass(type)
			.text(message)
			.insertAfter($button)
			.fadeIn(200);

		// Remove after 2 seconds
		setTimeout(function() {
			$message.fadeOut(200, function() {
				$(this).remove();
			});
		}, 2000);
	}

	// Initialize when document is ready
	$(document).ready(init);

})(jQuery);
