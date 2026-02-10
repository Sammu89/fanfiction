/* global fanficTranslations */
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

	function initTranslationField(container) {
		if (typeof fanficTranslations === 'undefined') {
			return;
		}

		var storyForm = container.closest('form');
		var searchInput = container.querySelector('input[type="text"]:not([type="hidden"])');
		var resultsBox = container.querySelector('.fanfic-translation-results');
		var selectedBox = container.querySelector('.fanfic-selected-translations');
		var storyId = container.getAttribute('data-story-id') || '0';
		var languageField = storyForm ? storyForm.querySelector('#fanfic_story_language') : null;

		if (!searchInput || !resultsBox || !selectedBox) {
			return;
		}

		function dispatchChange() {
			var event = new CustomEvent('fanfic-translations-changed');
			document.dispatchEvent(event);

			// Also trigger generic change detection for the story form
			if (storyForm && typeof storyForm.dispatchEvent === 'function') {
				storyForm.dispatchEvent(new Event('input', { bubbles: true }));
			}
		}

		function getSelectedIds() {
			return Array.from(selectedBox.querySelectorAll('input[name="fanfic_story_translations[]"]')).map(function(input) {
				return input.value;
			});
		}

		function normalizeIdList(values) {
			var unique = {};
			(values || []).forEach(function(value) {
				var strValue = String(value || '').trim();
				if (strValue !== '') {
					unique[strValue] = true;
				}
			});
			return Object.keys(unique);
		}

		function setCheckboxValues(name, values) {
			if (!storyForm) {
				return;
			}

			var selected = normalizeIdList(values);
			var selectedMap = {};
			selected.forEach(function(value) {
				selectedMap[value] = true;
			});

			var checkboxes = storyForm.querySelectorAll('input[name="' + name + '"]');
			checkboxes.forEach(function(checkbox) {
				checkbox.checked = !!selectedMap[String(checkbox.value)];
			});
		}

		function setSelectValue(selector, value) {
			if (!storyForm) {
				return;
			}

			var field = storyForm.querySelector(selector);
			if (!field) {
				return;
			}
			field.value = value ? String(value) : '';
		}

		function renderFandomSelection(items) {
			if (!storyForm) {
				return;
			}

			var selectedFandomsBox = storyForm.querySelector('.fanfic-selected-fandoms');
			if (!selectedFandomsBox) {
				return;
			}

			selectedFandomsBox.innerHTML = '';
			(items || []).forEach(function(item) {
				var id = String(item && item.id ? item.id : '').trim();
				var label = String(item && item.label ? item.label : '').trim();
				if (!id || !label) {
					return;
				}

				var wrapper = document.createElement('span');
				wrapper.className = 'fanfic-selected-fandom';
				wrapper.setAttribute('data-id', id);
				wrapper.textContent = label + ' ';

				var remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'fanfic-remove-fandom';
				remove.setAttribute('aria-label', (fanficTranslations.strings && fanficTranslations.strings.removeFandom) ? fanficTranslations.strings.removeFandom : 'Remove fandom');
				remove.textContent = '\u00d7';

				var hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = 'fanfic_story_fandoms[]';
				hidden.value = id;

				wrapper.appendChild(remove);
				wrapper.appendChild(hidden);
				selectedFandomsBox.appendChild(wrapper);
			});
		}

		function applyCustomTaxonomies(customTaxonomies) {
			if (!storyForm || !Array.isArray(customTaxonomies)) {
				return;
			}

			customTaxonomies.forEach(function(taxonomy) {
				if (!taxonomy || !taxonomy.slug) {
					return;
				}

				var slug = String(taxonomy.slug);
				var termIds = normalizeIdList(taxonomy.term_ids || []);
				if (taxonomy.selection_type === 'single') {
					setSelectValue('select[name="fanfic_custom_' + slug + '"]', termIds.length ? termIds[0] : '');
					return;
				}
				setCheckboxValues('fanfic_custom_' + slug + '[]', termIds);
			});
		}

		function applyInheritanceData(data) {
			if (!storyForm || !data || typeof data !== 'object') {
				return;
			}

			setCheckboxValues('fanfic_story_genres[]', data.genre_ids || []);
			setSelectValue('#fanfic_story_status', data.status_id || '');
			setCheckboxValues('fanfic_story_warnings[]', data.warning_ids || []);

			var originalCheckbox = storyForm.querySelector('input[name="fanfic_is_original_work"]');
			if (originalCheckbox) {
				originalCheckbox.checked = !!parseInt(data.is_original_work, 10);
				originalCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
			}

			if (originalCheckbox && originalCheckbox.checked) {
				renderFandomSelection([]);
			} else {
				renderFandomSelection(Array.isArray(data.fandoms) ? data.fandoms : []);
			}

			document.dispatchEvent(new CustomEvent('fanfic-fandoms-changed'));
			applyCustomTaxonomies(data.custom_taxonomies || []);

			if (typeof storyForm.dispatchEvent === 'function') {
				storyForm.dispatchEvent(new Event('input', { bubbles: true }));
				storyForm.dispatchEvent(new Event('change', { bubbles: true }));
			}
		}

		function fetchAndApplyInheritance(sourceStoryId) {
			var sourceId = String(sourceStoryId || '').trim();
			if (!sourceId || !fanficTranslations.inheritRestUrl) {
				return;
			}

			var url = fanficTranslations.inheritRestUrl + '?source_story_id=' + encodeURIComponent(sourceId);
			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': fanficTranslations.restNonce
				}
			}).then(function(response) {
				return response.json().then(function(payload) {
					return {
						ok: response.ok,
						payload: payload
					};
				});
			}).then(function(result) {
				if (!result.ok || !result.payload || result.payload.code) {
					return;
				}
				applyInheritanceData(result.payload);
			}).catch(function() {
				// Keep translation linking functional even if inheritance prefill fails.
			});
		}

		function getCurrentLanguageId() {
			if (languageField && languageField.value) {
				return parseInt(languageField.value, 10) || 0;
			}
			var attrValue = container.getAttribute('data-story-language') || '0';
			return parseInt(attrValue, 10) || 0;
		}

		function getCurrentStoryId() {
			if (storyForm) {
				var storyIdInput = storyForm.querySelector('input[name="fanfic_story_id"]');
				if (storyIdInput && storyIdInput.value) {
					var inputId = parseInt(storyIdInput.value, 10) || 0;
					if (inputId > 0) {
						return inputId;
					}
				}
			}
			return parseInt(storyId, 10) || 0;
		}

		function addSelected(id, label) {
			var existing = getSelectedIds();
			if (existing.indexOf(String(id)) !== -1) {
				return;
			}

			var wrapper = document.createElement('span');
			wrapper.className = 'fanfic-selected-translation';
			wrapper.setAttribute('data-id', id);
			wrapper.textContent = label + ' ';

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'fanfic-remove-translation';
			remove.setAttribute('aria-label', fanficTranslations.strings.remove);
			remove.textContent = '\u00d7';

			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'fanfic_story_translations[]';
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
				btn.className = 'fanfic-translation-result';
				btn.setAttribute('data-id', item.id);
				btn.setAttribute('data-label', item.label);
				btn.textContent = item.label;

				// Disable if already selected
				if (getSelectedIds().indexOf(String(item.id)) !== -1) {
					btn.disabled = true;
					btn.classList.add('is-disabled');
				}

				resultsBox.appendChild(btn);
			});
		}

		function searchStories(query) {
			var currentStoryId = getCurrentStoryId();
			var url = fanficTranslations.restUrl +
				'?story_id=' + encodeURIComponent(currentStoryId) +
				'&limit=20';
			var currentLanguageId = getCurrentLanguageId();

			if (currentLanguageId > 0) {
				url += '&current_language_id=' + encodeURIComponent(currentLanguageId);
			}

			if (query.length >= 2) {
				url += '&q=' + encodeURIComponent(query);
			}

			fetch(url, {
				method: 'GET',
				headers: {
					'X-WP-Nonce': fanficTranslations.restNonce
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
			searchStories(e.target.value.trim());
		}, 250);

		searchInput.addEventListener('input', debouncedSearch);

		if (languageField) {
			languageField.addEventListener('change', function() {
				container.setAttribute('data-story-language', languageField.value || '0');
				searchInput.value = '';
				resultsBox.innerHTML = '';
			});
		}

		// Also show results on focus if input is empty (show all linkable stories)
		searchInput.addEventListener('focus', function() {
			if (searchInput.value.trim().length < 2) {
				searchStories('');
			}
		});

		resultsBox.addEventListener('click', function(e) {
			var target = e.target;
			if (!target.classList.contains('fanfic-translation-result')) {
				return;
			}
			if (target.disabled || target.classList.contains('is-disabled')) {
				return;
			}
			var selectedId = target.getAttribute('data-id');
			addSelected(selectedId, target.getAttribute('data-label') || target.textContent);
			fetchAndApplyInheritance(selectedId);
			searchInput.value = '';
			resultsBox.innerHTML = '';
		});

		selectedBox.addEventListener('click', function(e) {
			var target = e.target;
			if (!target.classList.contains('fanfic-remove-translation')) {
				return;
			}
			var wrapper = target.closest('.fanfic-selected-translation');
			if (wrapper) {
				wrapper.remove();
				dispatchChange();
			}
		});

		// Close results when clicking outside
		document.addEventListener('click', function(e) {
			if (!container.contains(e.target)) {
				resultsBox.innerHTML = '';
			}
		});
	}

	function init() {
		var containers = document.querySelectorAll('.fanfic-translations-field');
		containers.forEach(function(container) {
			initTranslationField(container);
		});
	}

	document.addEventListener('DOMContentLoaded', init);
})();
