(function($) {
	'use strict';

	if ( typeof window.fanficStories === 'undefined' ) {
		return;
	}

	function normalizeList(value) {
		if (!value) {
			return [];
		}
		return value
			.replace(/,/g, ' ')
			.split(/\s+/)
			.map(function(item) { return item.trim(); })
			.filter(function(item) { return item.length > 0; });
	}

	function getParamList(params, key) {
		var value = params.get(key);
		if (value) {
			return normalizeList(value);
		}

		var arrayValues = params.getAll(key + '[]');
		if (arrayValues && arrayValues.length) {
			var merged = [];
			arrayValues.forEach(function(entry) {
				merged = merged.concat(normalizeList(entry));
			});
			return merged;
		}

		return [];
	}

	function buildUrl(baseUrl, data) {
		var params = new URLSearchParams();
		Object.keys(data).forEach(function(key) {
			if (!data[key]) {
				return;
			}
			params.set(key, data[key]);
		});

		var query = params.toString();
		return query ? baseUrl + '?' + query : baseUrl;
	}

	function setLoading($context, isLoading) {
		var $loader = $context.find('[data-fanfic-stories-loading]');
		if (!$loader.length) {
			return;
		}

		$loader.toggleClass('is-active', isLoading).attr('aria-hidden', isLoading ? 'false' : 'true');
	}

	function updateFormFromUrl($form, url) {
		var params = new URL(url, window.location.origin).searchParams;
		var search = params.get('search') || params.get('s') || '';
		$form.find('input[name="search"]').val(search);

		var genres = getParamList(params, 'genre');
		var statuses = getParamList(params, 'status');
		var warnings = getParamList(params, 'warning').map(function(item) {
			return item.replace(/^\-/, '');
		});
		var fandoms = getParamList(params, 'fandom');
		var age = params.get('age') || '';
		var sort = params.get('sort') || '';

		$form.find('select[name="genre[]"] option').prop('selected', false).filter(function() {
			return genres.indexOf($(this).val()) !== -1;
		}).prop('selected', true);

		$form.find('select[name="status[]"] option').prop('selected', false).filter(function() {
			return statuses.indexOf($(this).val()) !== -1;
		}).prop('selected', true);

		$form.find('input[name="warning[]"]').prop('checked', false).filter(function() {
			var value = $(this).val().replace(/^\-/, '');
			return warnings.indexOf(value) !== -1;
		}).prop('checked', true);

		// Note: Selected fandoms are handled by PHP on page load via the autofill structure
		// and don't need to be updated here (same as story form)

		$form.find('select[name="age"]').val(age);
		$form.find('select[name="sort"]').val(sort);
	}

	function buildFormPayload($form, page) {
		var search = $.trim($form.find('input[name="search"]').val() || '');
		var genres = $form.find('select[name="genre[]"]').val() || [];
		var statuses = $form.find('select[name="status[]"]').val() || [];
		var warnings = $form.find('input[name="warning[]"]:checked').map(function() {
			return $(this).val();
		}).get();

		// Get fandom IDs from autofill (same as story form)
		var fandomIds = $form.find('input[name="fanfic_story_fandoms[]"]').map(function() {
			return $(this).val();
		}).get();
		var fandoms = fandomIds.length > 0 ? fandomIds.join(' ') : '';

		var age = $form.find('select[name="age"]').val() || '';
		var sort = $form.find('select[name="sort"]').val() || '';

		var payload = {};
		if (search) {
			payload.search = search;
		}
		if (genres.length) {
			payload.genre = genres.join(' ');
		}
		if (statuses.length) {
			payload.status = statuses.join(' ');
		}
		if (warnings.length) {
			payload.warning = warnings.join(' ');
		}
		if (fandoms) {
			payload.fandom = fandoms;
		}
		if (age) {
			payload.age = age;
		}
		if (sort) {
			payload.sort = sort;
		}
		if (page && page > 1) {
			payload.paged = String(page);
		}

		return payload;
	}

	function fetchResults($context, url, payload, pushState) {
		if ($context.data('fanficStoriesLoading')) {
			return;
		}

		$context.data('fanficStoriesLoading', true);
		setLoading($context, true);

		var baseUrl = $context.find('form[data-fanfic-stories-form]').attr('action') || window.location.pathname;
		var ajaxData = $.extend({}, payload, {
			action: window.fanficStories.action,
			nonce: window.fanficStories.nonce,
			base_url: baseUrl
		});

		$.post(window.fanficStories.ajaxUrl, ajaxData)
			.done(function(response) {
				if (!response || !response.success || !response.data) {
					return;
				}

				var data = response.data;
				$context.find('[data-fanfic-stories-results]').html(data.html || '');
				$context.find('[data-fanfic-stories-pagination]').html(data.pagination || '');

				if (typeof data.active_filters !== 'undefined') {
					$context.find('[data-fanfic-active-filters]').html(data.active_filters || '');
				}

				if (data.count_label) {
					$context.find('.fanfic-stories-title').text(data.count_label);
				}

				if (pushState) {
					window.history.pushState({ fanficStories: true }, '', url);
				}
			})
			.fail(function() {
				window.location.href = url;
			})
			.always(function() {
				$context.data('fanficStoriesLoading', false);
				setLoading($context, false);
			});
	}

	function handleFormSubmit(e) {
		e.preventDefault();
		var $form = $(this);
		var $context = $form.closest('[data-fanfic-stories]');
		var baseUrl = $form.attr('action') || window.location.pathname;
		var payload = buildFormPayload($form, 1);
		var url = buildUrl(baseUrl, payload);

		fetchResults($context, url, payload, true);
	}

	function handlePaginationClick(e) {
		e.preventDefault();
		var $link = $(this);
		var $context = $link.closest('[data-fanfic-stories]');
		var $form = $context.find('form[data-fanfic-stories-form]');
		var pageMatch = ($link.attr('href') || '').match(/[?&]paged=(\d+)/);
		var page = pageMatch ? parseInt(pageMatch[1], 10) : 1;
		var payload = buildFormPayload($form, page);
		var url = buildUrl($form.attr('action') || window.location.pathname, payload);

		fetchResults($context, url, payload, true);
	}

	function attachStories($context) {
		var $form = $context.find('form[data-fanfic-stories-form]');
		if (!$form.length) {
			return;
		}

		$form.on('submit', handleFormSubmit);
		$form.on('change', 'select, input[type="checkbox"]', function() {
			$form.trigger('submit');
		});

		var searchTimeout;
		$form.on('input', 'input[name="search"]', function() {
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(function() {
				$form.trigger('submit');
			}, 500);
		});

		$context.on('click', '[data-fanfic-stories-pagination] a', handlePaginationClick);
	}

	$(document).ready(function() {
		$('[data-fanfic-stories]').each(function() {
			attachStories($(this));
		});

		window.addEventListener('popstate', function() {
			$('[data-fanfic-stories]').each(function() {
				var $context = $(this);
				var $form = $context.find('form[data-fanfic-stories-form]');
				if (!$form.length) {
					return;
				}

				updateFormFromUrl($form, window.location.href);
				var payload = buildFormPayload($form, parseInt(new URL(window.location.href).searchParams.get('paged') || '1', 10));
				var url = buildUrl($form.attr('action') || window.location.pathname, payload);
				fetchResults($context, url, payload, false);
			});
		});
	});
})(jQuery);
