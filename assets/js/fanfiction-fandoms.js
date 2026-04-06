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
		if (typeof fanficFandoms === 'undefined' || container.dataset.fandomsInitialized === 'true') {
			return;
		}

		var multiselect = container.querySelector('.fanfic-fandom-multiselect');
		var dropdown = multiselect ? multiselect.querySelector('.multi-select__dropdown') : null;
		var searchInput = container.querySelector('.fanfic-fandom-search-input');
		var resultsBox = container.querySelector('.fanfic-fandom-results');
		var selectedBox = container.querySelector('.fanfic-selected-fandoms');
		var originalCheckbox = document.querySelector('input[name="fanfic_is_original_work"]');
		var maxFandoms = parseInt(container.getAttribute('data-max-fandoms'), 10) || 5;
		var parentForm = container.closest('form');
		var disableEmptyFandoms = !!(parentForm && parentForm.classList.contains('fanfic-stories-form'));
		var showAllOnClick = container.getAttribute('data-show-all-on-click') === '1';
		var preloadedOptions = [];
		var savedFandomHTML = selectedBox ? selectedBox.innerHTML : '';

		if (!multiselect || !dropdown || !searchInput || !resultsBox || !selectedBox) {
			return;
		}

		container.dataset.fandomsInitialized = 'true';

		try {
			preloadedOptions = JSON.parse(container.getAttribute('data-preloaded-options') || '[]');
		} catch (error) {
			preloadedOptions = [];
		}

		function dispatchChange() {
			document.dispatchEvent(new CustomEvent('fanfic-fandoms-changed'));
		}

		function getSelectedWrappers() {
			return Array.from(selectedBox.querySelectorAll('.fanfic-pill-value'));
		}

		function getSelectedItems() {
			return getSelectedWrappers().map(function(wrapper) {
				var labelText = wrapper.getAttribute('data-label');
				if (!labelText) {
					var labelNode = wrapper.querySelector('.fanfic-pill-value-text');
					labelText = labelNode ? labelNode.textContent.trim() : '';
				}

				return {
					id: wrapper.getAttribute('data-id'),
					label: labelText
				};
			}).filter(function(item) {
				return item.id && item.label;
			});
		}

		function getSelectedIds() {
			return getSelectedItems().map(function(item) {
				return String(item.id);
			});
		}

		function closeDropdown() {
			multiselect.classList.remove('open');
			searchInput.setAttribute('aria-expanded', 'false');
		}

		function openDropdown() {
			document.querySelectorAll('.multi-select.open').forEach(function(select) {
				if (select !== multiselect) {
					select.classList.remove('open');
					var otherInput = select.querySelector('.fanfic-fandom-search-input');
					if (otherInput) {
						otherInput.setAttribute('aria-expanded', 'false');
					}
				}
			});

			multiselect.classList.add('open');
			searchInput.setAttribute('aria-expanded', 'true');
		}

		function clearResults() {
			resultsBox.innerHTML = '';
			closeDropdown();
		}

		function buildSelectedPill(id, label) {
			var wrapper = document.createElement('span');
			var text = document.createElement('span');
			var remove = document.createElement('button');
			var hidden = document.createElement('input');

			wrapper.className = 'fanfic-pill-value';
			wrapper.setAttribute('data-id', id);
			wrapper.setAttribute('data-label', label);

			text.className = 'fanfic-pill-value-text';
			text.textContent = label;

			remove.type = 'button';
			remove.className = 'fanfic-pill-value-remove';
			remove.setAttribute('aria-label', fanficFandoms.strings.remove);
			remove.textContent = '×';

			hidden.type = 'hidden';
			hidden.name = 'fanfic_story_fandoms[]';
			hidden.value = id;

			wrapper.appendChild(text);
			wrapper.appendChild(remove);
			wrapper.appendChild(hidden);

			return wrapper;
		}

		function syncVisibleResultStates() {
			var selectedIds = getSelectedIds();

			Array.from(resultsBox.querySelectorAll('.fanfic-fandom-result')).forEach(function(button) {
				var isSelected = selectedIds.indexOf(String(button.getAttribute('data-id'))) !== -1;
				button.classList.toggle('is-selected', isSelected);
				button.setAttribute('aria-selected', isSelected ? 'true' : 'false');
			});
		}

		function addSelected(id, label) {
			var selectedIds = getSelectedIds();

			if (selectedIds.indexOf(String(id)) !== -1 || selectedIds.length >= maxFandoms) {
				return;
			}

			selectedBox.appendChild(buildSelectedPill(id, label));
			syncVisibleResultStates();
			dispatchChange();
		}

		function removeSelected(id) {
			var wrappers = getSelectedWrappers();

			wrappers.forEach(function(wrapper) {
				if (String(wrapper.getAttribute('data-id')) === String(id)) {
					wrapper.remove();
				}
			});

			syncVisibleResultStates();
			dispatchChange();
		}

		function renderResults(items) {
			var selectedIds = getSelectedIds();

			resultsBox.innerHTML = '';
			items = Array.isArray(items) ? items : [];

			if (disableEmptyFandoms && showAllOnClick && !searchInput.value.trim()) {
				items = items.filter(function(item) {
					var count = typeof item.count === 'number' ? item.count : parseInt(item.count, 10) || 0;
					return !item.disabled && count > 0;
				});
			}

			if (!items.length) {
				closeDropdown();
				return;
			}

			items.forEach(function(item) {
				var count = typeof item.count === 'number' ? item.count : parseInt(item.count, 10) || 0;
				var button = document.createElement('button');
				var isSelected = selectedIds.indexOf(String(item.id)) !== -1;
				var isDisabled = false;

				button.type = 'button';
				button.className = 'fanfic-fandom-result';
				button.setAttribute('data-id', item.id);
				button.setAttribute('data-label', item.label);
				button.setAttribute('aria-selected', isSelected ? 'true' : 'false');

				if (isSelected) {
					button.classList.add('is-selected');
				}

				if (isDisabled) {
					button.disabled = true;
					button.classList.add('is-disabled');
				}

				button.textContent = disableEmptyFandoms ? (item.label + ' (' + count + ')') : item.label;
				resultsBox.appendChild(button);
			});

			openDropdown();
		}

		function searchFandoms(query) {
			if (query.length < 2) {
				clearResults();
				return;
			}

			fetch(fanficFandoms.restUrl + '?q=' + encodeURIComponent(query) + '&limit=20', {
				method: 'GET',
				headers: {
					'X-WP-Nonce': fanficFandoms.restNonce
				}
			}).then(function(response) {
				return response.ok ? response.json() : [];
			}).then(function(data) {
				renderResults(Array.isArray(data) ? data : []);
			}).catch(function() {
				clearResults();
			});
		}

		function setOriginalMode(isOriginal) {
			if (isOriginal) {
				savedFandomHTML = selectedBox.innerHTML;
				selectedBox.innerHTML = '';
				searchInput.value = '';
				searchInput.disabled = true;
				searchInput.setAttribute('placeholder', '');
				clearResults();
				closeDropdown();
				container.style.display = 'none';
			} else {
				container.style.display = '';
				selectedBox.innerHTML = savedFandomHTML;
				searchInput.disabled = false;
				searchInput.setAttribute('placeholder', fanficFandoms.strings.searchPlaceholder || searchInput.getAttribute('data-default-placeholder') || '');
			}

			dispatchChange();
		}

		var debouncedSearch = debounce(function() {
			if (originalCheckbox && originalCheckbox.checked) {
				return;
			}

			searchFandoms(searchInput.value.trim());
		}, 250);

		searchInput.setAttribute('data-default-placeholder', searchInput.getAttribute('placeholder') || '');

		searchInput.addEventListener('click', function(event) {
			event.stopPropagation();
			if (searchInput.disabled) {
				return;
			}

			if (showAllOnClick && !searchInput.value.trim() && preloadedOptions.length) {
				renderResults(preloadedOptions);
				return;
			}

			if (searchInput.value.trim().length < 2 || !resultsBox.children.length) {
				return;
			}

			openDropdown();
		});

		searchInput.addEventListener('input', debouncedSearch);

		searchInput.addEventListener('keydown', function(event) {
			if (event.key === 'Escape') {
				closeDropdown();
			}
		});

		resultsBox.addEventListener('click', function(event) {
			var target = event.target.closest('.fanfic-fandom-result');
			var selectedIds;

			if (!target || target.disabled || target.classList.contains('is-disabled')) {
				return;
			}

			if (originalCheckbox && originalCheckbox.checked) {
				return;
			}

			selectedIds = getSelectedIds();

			if (selectedIds.indexOf(String(target.getAttribute('data-id'))) !== -1) {
				removeSelected(target.getAttribute('data-id'));
				return;
			}

			if (selectedIds.length >= maxFandoms) {
				return;
			}

			addSelected(target.getAttribute('data-id'), target.getAttribute('data-label') || target.textContent.trim());
			searchInput.value = '';
			clearResults();
			closeDropdown();
		});

		selectedBox.addEventListener('click', function(event) {
			var removeButton = event.target.closest('.fanfic-pill-value-remove');
			var wrapper;

			if (!removeButton) {
				return;
			}

			wrapper = removeButton.closest('.fanfic-pill-value');
			if (wrapper) {
				removeSelected(wrapper.getAttribute('data-id'));
			}
		});

		dropdown.addEventListener('click', function(event) {
			event.stopPropagation();
		});

		document.addEventListener('click', function(event) {
			if (!container.contains(event.target)) {
				closeDropdown();
			}
		});

		document.addEventListener('fanfic-fandoms-changed', function() {
			syncVisibleResultStates();
		});

		if (originalCheckbox) {
			originalCheckbox.addEventListener('change', function() {
				setOriginalMode(originalCheckbox.checked);
			});

			if (originalCheckbox.checked) {
				setOriginalMode(true);
				return;
			}
		}

		syncVisibleResultStates();
	}

	function init() {
		document.querySelectorAll('.fanfic-fandoms-field').forEach(function(container) {
			initFandomField(container);
		});
	}

	document.addEventListener('DOMContentLoaded', init);
})();
