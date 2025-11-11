/**
 * Fanfiction Manager - Rating System JavaScript
 *
 * Handles interactive star rating functionality.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize rating system
	 */
	function init() {
		// Interactive star ratings
		$(document).on('mouseenter', '.fanfic-rating-interactive .fanfic-star', handleStarHover);
		$(document).on('mouseleave', '.fanfic-rating-interactive', handleRatingLeave);
		$(document).on('click', '.fanfic-rating-interactive .fanfic-star', handleStarClick);

		// Keyboard navigation for ratings
		$(document).on('keydown', '.fanfic-rating-interactive', handleRatingKeyboard);
	}

	/**
	 * Handle star hover
	 */
	function handleStarHover(e) {
		var $star = $(this);
		var $container = $star.closest('.fanfic-rating-interactive');
		var value = parseFloat($star.data('value'));

		// Highlight stars up to hovered star
		updateStarDisplay($container, value);
	}

	/**
	 * Handle mouse leave from rating container
	 */
	function handleRatingLeave(e) {
		var $container = $(this);
		var currentRating = parseFloat($container.data('rating')) || 0;

		// Reset to current rating
		updateStarDisplay($container, currentRating);
	}

	/**
	 * Handle star click
	 */
	function handleStarClick(e) {
		e.preventDefault();

		var $star = $(this);
		var $container = $star.closest('.fanfic-rating-interactive');
		var $wrapper = $container.closest('.fanfic-chapter-rating, .fanfic-story-rating');
		var chapterId = $wrapper.data('chapter-id');
		var value = parseFloat($star.data('value'));

		// Check if clicking on half-star position
		var clickX = e.pageX - $star.offset().left;
		var starWidth = $star.outerWidth();
		var isHalf = clickX < starWidth / 2;

		var rating = isHalf ? value - 0.5 : value;

		// Update display immediately
		updateStarDisplay($container, rating);
		$container.data('rating', rating);

		// Submit rating via AJAX
		submitRating(chapterId, rating, $wrapper);
	}

	/**
	 * Handle keyboard navigation
	 */
	function handleRatingKeyboard(e) {
		var $container = $(this);
		var currentRating = parseFloat($container.data('rating')) || 0;
		var newRating = currentRating;

		// Arrow keys to adjust rating
		if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
			e.preventDefault();
			newRating = Math.min(5, currentRating + 0.5);
		} else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
			e.preventDefault();
			newRating = Math.max(0.5, currentRating - 0.5);
		} else if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			// Submit current rating
			var $wrapper = $container.closest('.fanfic-chapter-rating, .fanfic-story-rating');
			var chapterId = $wrapper.data('chapter-id');
			if (currentRating > 0) {
				submitRating(chapterId, currentRating, $wrapper);
			}
			return;
		} else {
			return;
		}

		// Update display
		updateStarDisplay($container, newRating);
		$container.data('rating', newRating);
		$container.attr('aria-valuenow', newRating);
	}

	/**
	 * Update star display
	 */
	function updateStarDisplay($container, rating) {
		$container.find('.fanfic-star').each(function(index) {
			var $star = $(this);
			var starValue = index + 1;

			$star.removeClass('fanfic-star-full fanfic-star-half fanfic-star-empty');

			if (starValue <= Math.floor(rating)) {
				$star.addClass('fanfic-star-full');
			} else if (starValue - 0.5 <= rating) {
				$star.addClass('fanfic-star-half');
			} else {
				$star.addClass('fanfic-star-empty');
			}
		});
	}

	/**
	 * Submit rating via AJAX
	 */
	function submitRating(chapterId, rating, $wrapper) {
		$.ajax({
			url: fanficRating.ajaxUrl,
			type: 'POST',
			data: {
				action: 'fanfic_submit_rating',
				nonce: fanficRating.nonce,
				chapter_id: chapterId,
				rating: rating
			},
			success: function(response) {
				if (response.success) {
					// Update average rating display
					$wrapper.find('.fanfic-rating-average').text(formatRating(response.data.avg_rating));

					// Update rating count
					var countText = response.data.total_ratings === 1
						? '(1 ' + (fanficRating.strings.rating || 'rating') + ')'
						: '(' + response.data.total_ratings + ' ' + (fanficRating.strings.ratings || 'ratings') + ')';
					$wrapper.find('.fanfic-rating-count').text(countText);
				}
			}
		});
	}

	/**
	 * Format rating number
	 */
	function formatRating(rating) {
		return parseFloat(rating).toFixed(1);
	}

	// Initialize when document is ready
	$(document).ready(init);

})(jQuery);
