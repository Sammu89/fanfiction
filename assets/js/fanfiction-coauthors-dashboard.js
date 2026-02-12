/* global fanficCoauthorsDashboard */
(function() {
	'use strict';

	function hideIfEmpty(section) {
		if (!section) {
			return;
		}
		var remaining = section.querySelectorAll('.fanfic-invitation-item');
		if (remaining.length === 0) {
			section.style.display = 'none';
		}
	}

	function setItemLoading(item, isLoading) {
		if (!item) {
			return;
		}
		var buttons = item.querySelectorAll('button');
		buttons.forEach(function(button) {
			button.disabled = !!isLoading;
		});
	}

	function showError(message) {
		if (window.FanficMessages && typeof window.FanficMessages.error === 'function') {
			window.FanficMessages.error(message);
			return;
		}
		window.alert(message);
	}

	function handleInvitationResponse(button, response) {
		if (!button || typeof fanficCoauthorsDashboard === 'undefined') {
			return;
		}

		var storyId = parseInt(button.getAttribute('data-story-id'), 10) || 0;
		if (!storyId) {
			showError((fanficCoauthorsDashboard.strings && fanficCoauthorsDashboard.strings.invalidStory) ? fanficCoauthorsDashboard.strings.invalidStory : 'Invalid story.');
			return;
		}

		var item = button.closest('.fanfic-invitation-item');
		var section = button.closest('.fanfic-dashboard-invitations');
		setItemLoading(item, true);

		var payload = new FormData();
		payload.append('action', 'fanfic_respond_coauthor');
		payload.append('story_id', String(storyId));
		payload.append('response', response);
		payload.append('nonce', fanficCoauthorsDashboard.nonce);

		fetch(fanficCoauthorsDashboard.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: payload
		}).then(function(res) {
			return res.json();
		}).then(function(data) {
			var responseData = data && data.data ? data.data : {};
			if (!data || !data.success) {
				var errorMessage = responseData.message || ((fanficCoauthorsDashboard.strings && fanficCoauthorsDashboard.strings.error) ? fanficCoauthorsDashboard.strings.error : 'Failed to process invitation response.');
				showError(errorMessage);
				setItemLoading(item, false);
				return;
			}

			if (response === 'accept') {
				window.location.reload();
				return;
			}

			if (item) {
				item.remove();
			}
			hideIfEmpty(section);
		}).catch(function() {
			showError((fanficCoauthorsDashboard.strings && fanficCoauthorsDashboard.strings.error) ? fanficCoauthorsDashboard.strings.error : 'Failed to process invitation response.');
			setItemLoading(item, false);
		});
	}

	function init() {
		if (typeof fanficCoauthorsDashboard === 'undefined') {
			return;
		}

		document.addEventListener('click', function(event) {
			var acceptButton = event.target.closest('.fanfic-accept-invitation');
			if (acceptButton) {
				event.preventDefault();
				handleInvitationResponse(acceptButton, 'accept');
				return;
			}

			var refuseButton = event.target.closest('.fanfic-refuse-invitation');
			if (refuseButton) {
				event.preventDefault();
				handleInvitationResponse(refuseButton, 'refuse');
			}
		});
	}

	document.addEventListener('DOMContentLoaded', init);
})();
