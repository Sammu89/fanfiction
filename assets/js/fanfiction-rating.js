/**
 * Fanfiction Manager - Rating System JavaScript (v2.0)
 *
 * Handles interactive star rating with browser fingerprinting.
 * Features:
 * - Browser fingerprint generation (cached per session)
 * - 1-5 star ratings (no half-stars for simplicity)
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
	 * Called once per page load, cached in memory
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
	 * Initialize rating system
	 */
	function init() {
		// Initialize all rating widgets
		$('.fanfic-rating-widget').each(function() {
			initRatingWidget($(this));
		});

		// Star interaction handlers
		$(document).on('click', '.fanfic-rating-stars .fanfic-star', handleStarClick);
		$(document).on('mouseenter', '.fanfic-rating-stars .fanfic-star', handleStarHover);
		$(document).on('mouseleave', '.fanfic-rating-stars', handleRatingLeave);
	}

	/**
	 * Initialize individual rating widget
	 */
	function initRatingWidget($widget) {
		var chapterId = $widget.data('chapter-id');

		if (!chapterId) {
			return;
		}

		// Check eligibility and existing rating
		checkRatingEligibility(chapterId, function(data) {
			if (data.existing_rating) {
				// User already rated - show their rating
				updateStarDisplay($widget.find('.fanfic-rating-stars'), data.existing_rating);
			}
		});
	}

	/**
	 * Check if user can rate (and get existing rating)
	 */
	function checkRatingEligibility(chapterId, callback) {
		$.ajax({
			url: fanficAjax.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_check_rating_eligibility',
				nonce: fanficAjax.nonce,
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
	 * Handle star click
	 */
	function handleStarClick(e) {
		e.preventDefault();

		var $star = $(this);
		var $container = $star.closest('.fanfic-rating-stars');
		var $widget = $star.closest('.fanfic-rating-widget');
		var chapterId = $widget.data('chapter-id');
		var rating = parseInt($star.data('value'));

		if (!chapterId || !rating) {
			return;
		}

		// Update display immediately (optimistic update)
		updateStarDisplay($container, rating);

		// Disable clicks temporarily
		$container.addClass('fanfic-rating-submitting');

		// Submit rating
		submitRating(chapterId, rating, $widget);
	}

	/**
	 * Handle star hover (preview)
	 */
	function handleStarHover(e) {
		var $star = $(this);
		var $container = $star.closest('.fanfic-rating-stars');
		var rating = parseInt($star.data('value'));

		// Don't hover if currently submitting
		if ($container.hasClass('fanfic-rating-submitting')) {
			return;
		}

		updateStarDisplay($container, rating);
	}

	/**
	 * Handle mouse leave (reset to actual rating)
	 */
	function handleRatingLeave(e) {
		var $container = $(this);
		var $widget = $container.closest('.fanfic-rating-widget');
		var currentRating = $widget.data('user-rating') || 0;

		// Don't reset if currently submitting
		if ($container.hasClass('fanfic-rating-submitting')) {
			return;
		}

		updateStarDisplay($container, currentRating);
	}

	/**
	 * Update star display
	 */
	function updateStarDisplay($container, rating) {
		$container.find('.fanfic-star').each(function() {
			var $star = $(this);
			var starValue = parseInt($star.data('value'));

			if (starValue <= rating) {
				$star.addClass('active');
			} else {
				$star.removeClass('active');
			}
		});
	}

	/**
	 * Submit rating via AJAX
	 */
	function submitRating(chapterId, rating, $widget) {
		$.ajax({
			url: fanficAjax.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_submit_rating',
				nonce: fanficAjax.nonce,
				chapter_id: chapterId,
				rating: rating,
				fingerprint: getFingerprint()
			},
			success: function(response) {
				var $container = $widget.find('.fanfic-rating-stars');
				$container.removeClass('fanfic-rating-submitting');

				if (response.success) {
					// Store user's rating
					$widget.data('user-rating', rating);

					// Update average and count display
					if ($widget.find('.fanfic-rating-average').length) {
						$widget.find('.fanfic-rating-average').text(response.data.average_rating.toFixed(1));
					}

					if ($widget.find('.fanfic-rating-count').length) {
						var countText = response.data.total_votes === 1
							? '1 rating'
							: response.data.total_votes + ' ratings';
						$widget.find('.fanfic-rating-count').text(countText);
					}

					// Show message
					showMessage($widget, response.data.message, 'success');
				} else {
					// Error - reset display
					var currentRating = $widget.data('user-rating') || 0;
					updateStarDisplay($container, currentRating);
					showMessage($widget, response.data.message || 'An error occurred. Please try again.', 'error');
				}
			},
			error: function() {
				var $container = $widget.find('.fanfic-rating-stars');
				$container.removeClass('fanfic-rating-submitting');

				// Reset display
				var currentRating = $widget.data('user-rating') || 0;
				updateStarDisplay($container, currentRating);
				showMessage($widget, 'An error occurred. Please try again.', 'error');
			}
		});
	}

	/**
	 * Show message to user
	 */
	function showMessage($widget, message, type) {
		var $message = $widget.find('.fanfic-rating-message');

		if ($message.length === 0) {
			$message = $('<div class="fanfic-rating-message"></div>');
			$widget.append($message);
		}

		$message
			.removeClass('success error')
			.addClass(type)
			.text(message)
			.fadeIn(200);

		// Hide after 3 seconds
		setTimeout(function() {
			$message.fadeOut(200);
		}, 3000);
	}

	// Initialize when document is ready
	$(document).ready(init);

})(jQuery);
