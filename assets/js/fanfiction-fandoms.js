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

	function init() {
		if (typeof fanficFandoms === 'undefined') {
			return;
		}

		var searchInput = document.getElementById('fanfic_fandom_search');
		var resultsBox = document.querySelector('.fanfic-fandom-results');
		var selectedBox = document.querySelector('.fanfic-selected-fandoms');
		var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
		var maxFandoms = parseInt(fanficFandoms.maxFandoms, 10) || 5;

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
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'fanfic-fandom-result';
				btn.setAttribute('data-id', item.id);
				btn.textContent = item.label;
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
			if (originalCheckbox && originalCheckbox.checked) {
				return;
			}
			if (getSelectedIds().length >= maxFandoms) {
				return;
			}
			addSelected(target.getAttribute('data-id'), target.textContent);
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

	document.addEventListener('DOMContentLoaded', init);
})();
