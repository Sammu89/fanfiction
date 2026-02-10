/* global fanficFandoms */
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

	function initFandomField(container) {
		if (typeof fanficFandoms === 'undefined') {
			return;
		}

		var searchInput = container.querySelector('input[type="text"]:not([type="hidden"])');
		var resultsBox = container.querySelector('.fanfic-fandom-results');
		var selectedBox = container.querySelector('.fanfic-selected-fandoms');
		var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
		var maxFandoms = parseInt(container.getAttribute('data-max-fandoms'), 10) || 5;

		if (!searchInput || !resultsBox || !selectedBox) {
			return;
		}

		function dispatchChange() {
			var event = new CustomEvent('fanfic-fandoms-changed');
			document.dispatchEvent(event);
		}

		function getSelectedIds() {
			return Array.from(selectedBox.querySelectorAll('input[name="fanfic_story_fandoms[]"]')).map(function(input) {
				return input.value;
			});
		}

		function setOriginalMode(isOriginal) {
			if (isOriginal) {
				selectedBox.innerHTML = '';
				searchInput.value = '';
				searchInput.disabled = true;
				resultsBox.innerHTML = '';
			} else {
				searchInput.disabled = false;
			}
			dispatchChange();
		}

		function addSelected(id, label) {
			var existing = getSelectedIds();
			if (existing.indexOf(String(id)) !== -1) {
				return;
			}

			if (existing.length >= maxFandoms) {
				return;
			}

			var wrapper = document.createElement('span');
			wrapper.className = 'fanfic-selected-fandom';
			wrapper.setAttribute('data-id', id);
			wrapper.textContent = label + ' ';

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'fanfic-remove-fandom';
			remove.setAttribute('aria-label', fanficFandoms.strings.remove);
			remove.textContent = '×';

			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'fanfic_story_fandoms[]';
			hidden.value = id;

			wrapper.appendChild(remove);
			wrapper.appendChild(hidden);
			selectedBox.appendChild(wrapper);
			dispatchChange();
		}

		function renderResults(items) {
			resultsBox.innerHTML = '';
			if (!items.length) {
				return;
			}

			items.forEach(function(item) {
				var count = typeof item.count === 'number' ? item.count : parseInt(item.count, 10) || 0;
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'fanfic-fandom-result';
				btn.setAttribute('data-id', item.id);
				btn.setAttribute('data-label', item.label);
				btn.textContent = item.label + ' (' + count + ')';
				if (item.disabled || count === 0) {
					btn.disabled = true;
					btn.classList.add('is-disabled');
				}
				resultsBox.appendChild(btn);
			});
		}

		function searchFandoms(query) {
			if (query.length < 2) {
				resultsBox.innerHTML = '';
				return;
			}

			var url = fanficFandoms.restUrl + '?q=' + encodeURIComponent(query) + '&limit=20';
			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': fanficFandoms.restNonce
				}
			}).then(function(response) {
				return response.ok ? response.json() : [];
			}).then(function(data) {
				renderResults(Array.isArray(data) ? data : []);
			}).catch(function() {
				resultsBox.innerHTML = '';
			});
		}

		var debouncedSearch = debounce(function(e) {
			if (originalCheckbox && originalCheckbox.checked) {
				return;
			}
			searchFandoms(e.target.value.trim());
		}, 250);

		searchInput.addEventListener('input', debouncedSearch);
		resultsBox.addEventListener('click', function(e) {
			var target = e.target;
			if (!target.classList.contains('fanfic-fandom-result')) {
				return;
			}
			if (target.disabled || target.classList.contains('is-disabled')) {
				return;
			}
			if (originalCheckbox && originalCheckbox.checked) {
				return;
			}
			if (getSelectedIds().length >= maxFandoms) {
				return;
			}
			addSelected(target.getAttribute('data-id'), target.getAttribute('data-label') || target.textContent);
			searchInput.value = '';
			resultsBox.innerHTML = '';
		});

		selectedBox.addEventListener('click', function(e) {
			var target = e.target;
			if (!target.classList.contains('fanfic-remove-fandom')) {
				return;
			}
			var wrapper = target.closest('.fanfic-selected-fandom');
			if (wrapper) {
				wrapper.remove();
				dispatchChange();
			}
		});

		if (originalCheckbox) {
			originalCheckbox.addEventListener('change', function() {
				setOriginalMode(originalCheckbox.checked);
			});

			if (originalCheckbox.checked) {
				setOriginalMode(true);
			}
		}
	}

	function init() {
		// Initialize all fandom fields on the page
		var containers = document.querySelectorAll('.fanfic-fandoms-field');
		containers.forEach(function(container) {
			initFandomField(container);
		});
	}

	document.addEventListener('DOMContentLoaded', init);
})();
