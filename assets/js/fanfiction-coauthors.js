/* global fanficCoauthors */
(function() {
	'use strict';

	function debounce(fn, delay) {
		var timer = null;
		return function() {
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function() {
				fn.apply(null, args);
			}, delay);
		};
	}

	function initCoauthorField(container) {
		if (typeof fanficCoauthors === 'undefined') {
			return;
		}

		var storyForm = container.closest('form');
		var searchInput = container.querySelector('#fanfic_coauthor_search');
		var resultsBox = container.querySelector('.fanfic-coauthor-results');
		var selectedBox = container.querySelector('.fanfic-selected-coauthors');
		var maxCoauthors = parseInt(container.getAttribute('data-max-coauthors'), 10) || parseInt(fanficCoauthors.maxCoauthors, 10) || 5;

		if (!searchInput || !resultsBox || !selectedBox) {
			return;
		}

		function getCurrentStoryId() {
			if (storyForm) {
				var storyIdInput = storyForm.querySelector('input[name="fanfic_story_id"]');
				if (storyIdInput && storyIdInput.value) {
					return parseInt(storyIdInput.value, 10) || 0;
				}
			}
			return parseInt(container.getAttribute('data-story-id'), 10) || 0;
		}

		function getSelectedIds() {
			return Array.from(selectedBox.querySelectorAll('input[name="fanfic_story_coauthors[]"]')).map(function(input) {
				return String(input.value || '').trim();
			}).filter(function(value) {
				return value !== '';
			});
		}

		function dispatchChange() {
			document.dispatchEvent(new CustomEvent('fanfic-coauthors-changed'));
			if (storyForm && typeof storyForm.dispatchEvent === 'function') {
				storyForm.dispatchEvent(new Event('input', { bubbles: true }));
				storyForm.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}

		function clearResults() {
			resultsBox.innerHTML = '';
		}

		function createSelectedNode(userId, displayName, avatarUrl, status) {
			var wrapper = document.createElement('span');
			wrapper.className = 'fanfic-selected-coauthor';
			wrapper.setAttribute('data-id', String(userId));
			wrapper.setAttribute('data-status', status || '');

			if (avatarUrl) {
				var avatar = document.createElement('img');
				avatar.src = avatarUrl;
				avatar.alt = displayName;
				avatar.className = 'fanfic-coauthor-avatar';
				wrapper.appendChild(avatar);
			}

			var name = document.createElement('span');
			name.className = 'fanfic-coauthor-name';
			name.textContent = displayName;
			wrapper.appendChild(name);

			if (status === 'pending') {
				var badge = document.createElement('span');
				badge.className = 'fanfic-coauthor-status-badge';
				badge.textContent = (fanficCoauthors.strings && fanficCoauthors.strings.pending) ? fanficCoauthors.strings.pending : 'Pending';
				wrapper.appendChild(badge);
			}

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'fanfic-remove-coauthor';
			remove.setAttribute('aria-label', (fanficCoauthors.strings && fanficCoauthors.strings.remove) ? fanficCoauthors.strings.remove : 'Remove co-author');
			remove.textContent = '\u00d7';
			wrapper.appendChild(remove);

			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'fanfic_story_coauthors[]';
			hidden.value = String(userId);
			wrapper.appendChild(hidden);

			return wrapper;
		}

		function addSelected(userId, displayName, avatarUrl) {
			var selectedIds = getSelectedIds();
			var userIdString = String(userId);
			if (selectedIds.indexOf(userIdString) !== -1) {
				return;
			}

			if (selectedIds.length >= maxCoauthors) {
				clearResults();
				return;
			}

			selectedBox.appendChild(createSelectedNode(userIdString, displayName, avatarUrl || '', ''));
			dispatchChange();
		}

		function renderResults(items) {
			clearResults();
			if (!Array.isArray(items) || !items.length) {
				return;
			}

			var selectedMap = {};
			getSelectedIds().forEach(function(id) {
				selectedMap[id] = true;
			});

			var atLimit = getSelectedIds().length >= maxCoauthors;

			items.forEach(function(item) {
				if (!item || !item.id || !item.display_name) {
					return;
				}

				var id = String(item.id);
				if (selectedMap[id]) {
					return;
				}

				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'fanfic-coauthor-result';
				button.setAttribute('data-id', id);
				button.setAttribute('data-name', item.display_name);
				button.setAttribute('data-avatar-url', item.avatar_url || '');

				if (item.avatar_url) {
					var avatar = document.createElement('img');
					avatar.src = item.avatar_url;
					avatar.alt = item.display_name;
					button.appendChild(avatar);
				}

				var label = document.createElement('span');
				label.textContent = item.display_name;
				button.appendChild(label);

				if (atLimit) {
					button.disabled = true;
					button.classList.add('is-disabled');
				}

				resultsBox.appendChild(button);
			});
		}

		function searchUsers(query) {
			if (!query || query.length < 2) {
				clearResults();
				return;
			}

			if (getSelectedIds().length >= maxCoauthors) {
				clearResults();
				return;
			}

			var url = fanficCoauthors.restUrl + '?q=' + encodeURIComponent(query) + '&limit=20';
			var storyId = getCurrentStoryId();
			if (storyId > 0) {
				url += '&story_id=' + encodeURIComponent(String(storyId));
			}

			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': fanficCoauthors.restNonce
				}
			}).then(function(response) {
				return response.ok ? response.json() : [];
			}).then(function(data) {
				renderResults(data);
			}).catch(function() {
				clearResults();
			});
		}

		var debouncedSearch = debounce(function(event) {
			searchUsers(String(event.target.value || '').trim());
		}, 250);

		searchInput.addEventListener('input', debouncedSearch);

		resultsBox.addEventListener('click', function(event) {
			var target = event.target;
			if (!target.classList.contains('fanfic-coauthor-result')) {
				target = target.closest('.fanfic-coauthor-result');
			}
			if (!target || target.disabled || target.classList.contains('is-disabled')) {
				return;
			}

			addSelected(
				target.getAttribute('data-id'),
				target.getAttribute('data-name') || target.textContent || '',
				target.getAttribute('data-avatar-url') || ''
			);
			searchInput.value = '';
			clearResults();
		});

		selectedBox.addEventListener('click', function(event) {
			var target = event.target;
			if (!target.classList.contains('fanfic-remove-coauthor')) {
				return;
			}
			var wrapper = target.closest('.fanfic-selected-coauthor');
			if (wrapper) {
				wrapper.remove();
				dispatchChange();
			}
		});

		document.addEventListener('click', function(event) {
			if (!container.contains(event.target)) {
				clearResults();
			}
		});
	}

	function init() {
		var containers = document.querySelectorAll('.fanfic-coauthors-field');
		containers.forEach(function(container) {
			initCoauthorField(container);
		});
	}

	document.addEventListener('DOMContentLoaded', init);
})();
