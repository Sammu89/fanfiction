(function($) {
    'use strict';

    // Keep the applied search term separate from the draft text in the input.
    // Filter changes should not promote a new search string until the user submits.
    var searchFormState = {
        committedSearchTerm: '',
        liveFilteringEnabled: false,
        pendingSearchCommit: false,
        autoSubmitTimer: null,
        suppressAutoSubmit: false
    };

    // ===== SMART FILTER MANAGER (Match All Logic) =====
    var SmartFilterManager = {
        /**
         * Enforce single-select mode for single-select taxonomies when match_all is ON
         */
        enforceMatchAllFilters: function() {
            var taxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

            console.log('Match All: Enforcing single-select for taxonomies:', taxonomies);

            var self = this;
            taxonomies.forEach(function(taxName) {
                console.log('Processing taxonomy:', taxName);
                self.enforceSingleSelectForTaxonomy(taxName);
            });

            // Update all multi-select dropdown labels to reflect the new single values
            this.updateAllMultiSelectLabels();
        },

        /**
         * Update all multi-select dropdown labels
         */
        updateAllMultiSelectLabels: function() {
            document.querySelectorAll('.multi-select:not(.fanfic-fandom-multiselect)').forEach(function(select) {
                var trigger = select.querySelector('.multi-select__trigger');
                var checkboxes = select.querySelectorAll('input[type="checkbox"]');
                var placeholder = select.dataset.placeholder || 'Select';

                var checked = Array.from(checkboxes).filter(function(cb) { return cb.checked; });

                if (checked.length === 0) {
                    trigger.textContent = placeholder;
                } else if (checked.length <= 2) {
                    trigger.textContent = checked.map(function(cb) { return cb.parentNode.textContent.trim(); }).join(', ');
                } else {
                    trigger.textContent = checked.length + ' selected';
                }
            });
        },

        /**
         * Enforce single-select for a specific taxonomy
         */
        enforceSingleSelectForTaxonomy: function(taxName) {
            var checkboxes = this.getCheckboxesForTaxonomy(taxName);
            console.log('Checkboxes found for ' + taxName + ':', checkboxes.length);

            if (checkboxes.length === 0) {
                console.log('No checkboxes found for ' + taxName);
                return;
            }

            var checked = checkboxes.filter(':checked');
            console.log('Checked for ' + taxName + ':', checked.length);

            if (checked.length <= 1) {
                console.log(taxName + ' already single or empty');
                return; // Already single or empty
            }

            // Keep only the last checked one
            var lastCheckedElement = checked.last()[0]; // Get DOM element
            console.log('Unchecking all but last for ' + taxName);

            // Uncheck all except the last one
            checkboxes.each(function() {
                if (this !== lastCheckedElement && this.checked) {
                    $(this).prop('checked', false).trigger('change');
                }
            });
        },

        /**
         * Get checkboxes for a specific taxonomy
         */
        getCheckboxesForTaxonomy: function(taxName) {
            var selector;
            if (taxName === 'status') {
                selector = 'input[name="status[]"]';
            } else if (taxName === 'age') {
                selector = 'input[name="age[]"]';
            } else if (taxName === 'language') {
                selector = 'input[name="language[]"]';
            } else {
                // Custom taxonomy
                selector = 'input[name="' + taxName + '[]"]';
            }
            return $(selector);
        },

        /**
         * Attach change listeners to checkboxes to enforce single-select when match_all is ON
         */
        attachSingleSelectEnforcement: function() {
            var self = this;
            var taxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

            taxonomies.forEach(function(taxName) {
                var checkboxes = self.getCheckboxesForTaxonomy(taxName);

                checkboxes.off('change.smartfilter').on('change.smartfilter', function() {
                    // Only enforce if match_all is currently ON
                    if ($('#fanfic-match-all-filters').is(':checked')) {
                        // If this checkbox is being checked
                        if ($(this).is(':checked')) {
                            // Uncheck all others in this taxonomy
                            checkboxes.not(this).prop('checked', false).trigger('change');
                        }

                        // Update dropdown label
                        self.updateAllMultiSelectLabels();
                    }
                });
            });
        },

        /**
         * Disable match_all_filters restrictions - allow multi-select again
         */
        disableMatchAllFilters: function() {
            // Remove single-select enforcement listeners when match_all is OFF
            var taxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

            var self = this;
            taxonomies.forEach(function(taxName) {
                var checkboxes = self.getCheckboxesForTaxonomy(taxName);
                // Remove the .smartfilter event namespace
                checkboxes.off('change.smartfilter');
            });
        }
    };

    // ===== WARNINGS CROSS-DISABLE MANAGER =====
    // Warnings use two separate multiselect dropdowns (Exclude and Include).
    // Cross-disable prevents selecting the same warning in both dropdowns simultaneously.
    var WarningsManager = {
        /**
         * Initialize warnings cross-disable logic
         */
        init: function() {
            var self = this;
            var $excludeCheckboxes = $('input[name="warnings_exclude[]"]');
            var $includeCheckboxes = $('input[name="warnings_include[]"]');

            if (!$excludeCheckboxes.length && !$includeCheckboxes.length) {
                return;
            }

            // On exclude checkbox change, uncheck the same value in include
            $excludeCheckboxes.on('change.warnings', function() {
                if ($(this).is(':checked')) {
                    var value = $(this).val();
                    $includeCheckboxes.filter('[value="' + value + '"]').prop('checked', false);
                    self.updateMultiSelectLabel($('.fanfic-warnings-include-multiselect'));
                }
                PillsManager.updatePills();
            });

            // On include checkbox change, uncheck the same value in exclude
            $includeCheckboxes.on('change.warnings', function() {
                if ($(this).is(':checked')) {
                    var value = $(this).val();
                    $excludeCheckboxes.filter('[value="' + value + '"]').prop('checked', false);
                    self.updateMultiSelectLabel($('.fanfic-warnings-exclude-multiselect'));
                }
                PillsManager.updatePills();
            });
        },

        /**
         * Update a multi-select dropdown label
         */
        updateMultiSelectLabel: function($multiSelect) {
            if (!$multiSelect.length) return;
            var trigger = $multiSelect.find('.multi-select__trigger')[0];
            if (!trigger) return;
            var checkboxes = $multiSelect.find('input[type="checkbox"]');
            var placeholder = $multiSelect.data('placeholder') || 'Select';
            var checked = checkboxes.filter(':checked');

            if (checked.length === 0) {
                trigger.textContent = placeholder;
            } else if (checked.length <= 2) {
                trigger.textContent = checked.map(function() { return $(this).parent().text().trim(); }).get().join(', ');
            } else {
                trigger.textContent = checked.length + ' selected';
            }
        }
    };

    // ===== TRANSLATION DEDUPLICATOR =====
    var TranslationDeduplicator = {
        /**
         * Initialize deduplication
         */
        init: function() {
            this.deduplicate();
        },

        /**
         * Normalize language code to lowercase hyphen form.
         */
        normalizeLanguageCode: function(lang) {
            return String(lang || '').toLowerCase().replace('_', '-');
        },

        /**
         * Get base language (e.g., pt-br -> pt).
         */
        getBaseLanguage: function(lang) {
            return this.normalizeLanguageCode(lang).split('-')[0];
        },

        /**
         * Get known variants for a base language.
         * Reuses search-bar language variant logic when available.
         */
        getLanguageVariants: function(baseLang) {
            if (typeof FanficLanguageFilter !== 'undefined' && typeof FanficLanguageFilter.getLanguageVariants === 'function') {
                return FanficLanguageFilter.getLanguageVariants(baseLang);
            }
            return [baseLang];
        },

        /**
         * Build language preference order:
         * exact browser variant -> base variant list -> English variants.
         */
        getBrowserLanguagePreference: function() {
            var raw = navigator.language || navigator.userLanguage || 'en';
            var full = this.normalizeLanguageCode(raw);
            var base = this.getBaseLanguage(full);
            var preferred = [];

            if (typeof FanficLanguageFilter !== 'undefined' && typeof FanficLanguageFilter.buildPreferredLanguageOrder === 'function') {
                preferred = FanficLanguageFilter.buildPreferredLanguageOrder(full);
            } else {
                var variants = this.getLanguageVariants(base);
                preferred = [full, base].concat(variants).concat(['en'])
                    .filter(function(value) { return value && value.trim() !== ''; })
                    .map(function(value) { return String(value).toLowerCase(); })
                    .filter(function(value, index, arr) { return arr.indexOf(value) === index; });
            }

            return {
                full: full,
                base: base,
                preferredOrder: preferred
            };
        },

        /**
         * Read normalized card language slug.
         */
        getCardLanguage: function(card) {
            return this.normalizeLanguageCode(card.getAttribute('data-language') || '');
        },

        /**
         * Sort cards descending by view count.
         */
        sortByViewsDesc: function(cards) {
            return cards.slice().sort(function(a, b) {
                return parseInt(b.getAttribute('data-views') || '0', 10) - parseInt(a.getAttribute('data-views') || '0', 10);
            });
        },

        /**
         * Run deduplication on all story cards in the results
         */
        deduplicate: function() {
            var browserPreference = this.getBrowserLanguagePreference();
            var groups = {};

            // Collect all cards that belong to a translation group
            var cards = document.querySelectorAll('.fanfic-story-card[data-translation-group]');
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var groupId = card.getAttribute('data-translation-group');
                if (!groupId || groupId === '0' || groupId === '') {
                    continue;
                }
                if (!groups[groupId]) {
                    groups[groupId] = [];
                }
                groups[groupId].push(card);
            }

            // For each group with 2+ cards, show preferred version and hide others
            var self = this;
            Object.keys(groups).forEach(function(groupId) {
                var groupCards = groups[groupId];
                if (groupCards.length <= 1) {
                    return;
                }

                var preferred = self.selectPreferred(groupCards, browserPreference);
                for (var j = 0; j < groupCards.length; j++) {
                    if (groupCards[j] === preferred) {
                        groupCards[j].style.display = '';
                        groupCards[j].classList.remove('fanfic-translation-hidden');
                    } else {
                        groupCards[j].style.display = 'none';
                        groupCards[j].classList.add('fanfic-translation-hidden');
                    }
                }
            });
        },

        /**
         * Select the preferred card from a group based on browser language
         * Priority:
         * 1) exact browser variant (e.g., pt-br)
         * 2) any same-base variant (e.g., any pt)
         * 3) English variant
         * 4) most views
         */
        selectPreferred: function(cards, browserPreference) {
            var full = browserPreference.full;
            var base = browserPreference.base;
            var preferredOrder = browserPreference.preferredOrder || [];

            // Priority 1: exact browser language variant match
            for (var i = 0; i < cards.length; i++) {
                if (this.getCardLanguage(cards[i]) === full) {
                    return cards[i];
                }
            }

            // Priority 2a: preferred variant order (same logic used by language filter sorting)
            for (var orderIndex = 0; orderIndex < preferredOrder.length; orderIndex++) {
                var wantedLang = preferredOrder[orderIndex];
                for (var j = 0; j < cards.length; j++) {
                    if (this.getCardLanguage(cards[j]) === wantedLang) {
                        return cards[j];
                    }
                }
            }

            // Priority 2b: any same-base variant, highest views wins
            var sameBaseCards = [];
            for (var k = 0; k < cards.length; k++) {
                if (this.getBaseLanguage(this.getCardLanguage(cards[k])) === base) {
                    sameBaseCards.push(cards[k]);
                }
            }
            if (sameBaseCards.length > 0) {
                return this.sortByViewsDesc(sameBaseCards)[0];
            }

            // Priority 3: English variant
            var englishCards = [];
            for (var m = 0; m < cards.length; m++) {
                if (this.getBaseLanguage(this.getCardLanguage(cards[m])) === 'en') {
                    englishCards.push(cards[m]);
                }
            }
            if (englishCards.length > 0) {
                return this.sortByViewsDesc(englishCards)[0];
            }

            // Priority 4: most views overall
            return this.sortByViewsDesc(cards)[0];
        }
    };

    // ===== GENERIC PILLS SYSTEM =====
    var PillsManager = {
        // Configuration
        config: {
            containerSelector: '[data-fanfic-active-filters]',
            formSelector: '.fanfic-stories-form',
            nonActivatingKeys: [ 'search', 'sort', 'direction' ],
        },

        // Taxonomy order (determines pill order)
        taxonomyOrder: [
            { key: 'status', label: 'Status', type: 'multi-select' },
            { key: 'genres', label: 'Genre', type: 'multi-select' },
            { key: 'language', label: 'Language', type: 'multi-select' },
            { key: 'age', label: 'Age', type: 'multi-select' },
            { key: 'fandoms', label: 'Fandom', type: 'custom', selector: '[name="fanfic_story_fandoms[]"]' },
            { key: 'warnings_include', label: 'Including', type: 'warnings' },
            { key: 'warnings_exclude', label: 'Excluding', type: 'warnings' },
        ],

        /**
         * Return custom taxonomy pill configs from localized data.
         */
        getCustomTaxonomyOrder: function() {
            var customTaxonomies = window.fanficSearchBar && Array.isArray(window.fanficSearchBar.customTaxonomies) ? window.fanficSearchBar.customTaxonomies : [];

            return customTaxonomies
                .filter(function(taxonomy) {
                    return taxonomy && taxonomy.slug;
                })
                .map(function(taxonomy) {
                    return {
                        key: taxonomy.slug,
                        label: taxonomy.label || taxonomy.slug,
                        type: 'single' === taxonomy.selection_type ? 'single-select' : 'multi-select',
                        custom: true,
                    };
                });
        },

        /**
         * Return the full taxonomy order, including custom taxonomies and tail controls.
         */
        getTaxonomyOrder: function() {
            var order = this.taxonomyOrder.slice(0);
            var customTaxonomies = this.getCustomTaxonomyOrder();

            if (customTaxonomies.length) {
                order = order.concat(customTaxonomies);
            }

            order.push({ key: 'rating', label: 'Rating', type: 'single-select' });
            order.push({ key: 'match_all', label: 'Match all filters', type: 'toggle', naked: true });

            return order;
        },

        /**
         * Get all current filter values from form
         */
        getCurrentFilters: function() {
            var filters = {};

            // Search text
            var search = searchFormState.committedSearchTerm || '';
            if (search && search.trim()) {
                filters.search = search.trim();
            }

            var sortValue = $('select[name="sort"]').val();
            var sortText = $('select[name="sort"] option:selected').text().trim();
            if (sortValue) {
                filters.sort = sortText;
            }

            var directionValue = $('select[name="direction"]').val();
            var directionText = $('select[name="direction"] option:selected').text().trim();
            if (directionValue) {
                filters.direction = directionText;
            }

            var ratingMin = parseFloat($('#fanfic-rating-min').val() || '0');
            if (!isNaN(ratingMin) && ratingMin > 0) {
                filters.rating = [ratingMin.toFixed(1) + '+'];
            }

            // Status multi-select
            var statuses = [];
            $('input[name="status[]"]:checked').each(function() {
                statuses.push($(this).closest('label').text().trim());
            });
            if (statuses.length) {
                filters.status = statuses;
            }

            // Match all toggle
            if ($('#fanfic-match-all-filters').is(':checked')) {
                filters.match_all = true;
            }

            // Genres multi-select
            var genres = [];
            $('input[name="genre[]"]:checked').each(function() {
                genres.push($(this).closest('label').text().trim());
            });
            if (genres.length) {
                filters.genres = genres;
            }

            // Age multi-select
            var ages = [];
            $('input[name="age[]"]:checked').each(function() {
                ages.push($(this).closest('label').text().trim());
            });
            if (ages.length) {
                filters.age = ages;
            }

            // Languages multi-select
            var languages = [];
            $('input[name="language[]"]:checked').each(function() {
                languages.push($(this).closest('label').text().trim());
            });
            if (languages.length) {
                filters.language = languages;
            }

            // Fandoms (custom field)
            var fandomLabels = [];
            $('.fanfic-selected-fandoms input[name="fanfic_story_fandoms[]"]').each(function() {
                var label = $(this).closest('.fanfic-pill-value').clone().find('button').remove().end().text().trim();
                if (label) {
                    fandomLabels.push(label);
                }
            });
            if (fandomLabels.length) {
                filters.fandoms = fandomLabels;
            }

            // Exclude Warnings (separate dropdown)
            var excludeWarnings = [];
            $('input[name="warnings_exclude[]"]:checked').each(function() {
                excludeWarnings.push($(this).closest('label').text().trim());
            });
            if (excludeWarnings.length) {
                filters.warnings_exclude = excludeWarnings;
            }

            // Include Warnings (separate dropdown)
            var includeWarnings = [];
            $('input[name="warnings_include[]"]:checked').each(function() {
                includeWarnings.push($(this).closest('label').text().trim());
            });
            if (includeWarnings.length) {
                filters.warnings_include = includeWarnings;
            }

            // Custom taxonomies (single-select and multi-select dropdowns)
            var customTaxonomies = window.fanficSearchBar && Array.isArray(window.fanficSearchBar.customTaxonomies) ? window.fanficSearchBar.customTaxonomies : [];

            customTaxonomies.forEach(function(customTaxonomy) {
                if (!customTaxonomy || !customTaxonomy.slug) {
                    return;
                }

                var taxonomySlug = customTaxonomy.slug;
                var values = [];

                if (customTaxonomy.selection_type === 'single') {
                    var $selectedOption = $('.fanfic-advanced-search-filters select[name="' + taxonomySlug + '"] option:selected');
                    var selectedValue = $selectedOption.val();

                    if (selectedValue) {
                        values.push($selectedOption.text().trim());
                    }
                } else {
                    $('.fanfic-advanced-search-filters input[name="' + taxonomySlug + '[]"]:checked').each(function() {
                        values.push($(this).closest('label').text().trim());
                    });
                }

                if (values.length > 0) {
                    filters[taxonomySlug] = values;
                }
            });

            return filters;
        },

        /**
         * Remove pill-inactive filter keys from the filter map.
         */
        getPillRelevantFilters: function(filters) {
            var self = this;
            var pillFilters = {};

            Object.keys(filters || {}).forEach(function(key) {
                if (self.config.nonActivatingKeys.indexOf(key) !== -1) {
                    return;
                }
                pillFilters[key] = filters[key];
            });

            return pillFilters;
        },

        /**
         * Generate pills HTML from filters
         */
        generatePills: function(filters) {
            var self = this;
            var pillsHtml = '';

            // Generate pills in defined order
            this.getTaxonomyOrder().forEach(function(taxConfig) {
                var key = taxConfig.key;
                var label = taxConfig.label;
                var values = null;

                // Get values for this taxonomy
                if (taxConfig.type === 'toggle') {
                    values = filters[key] ? [label] : null;
                } else {
                    values = filters[key] || null;
                }

                // Skip if no values
                if (!values) {
                    return;
                }

                // Normalize values to always be an array (for single-select fields like status, age)
                if (!Array.isArray(values)) {
                    values = [values];
                }

                // Skip if empty array
                if (values.length === 0) {
                    return;
                }

                if (taxConfig.type === 'search') {
                    var searchLabel = (window.fanficSearchBar && window.fanficSearchBar.i18n && window.fanficSearchBar.i18n.searchTermLabel) ? window.fanficSearchBar.i18n.searchTermLabel : 'Search';
                    var removeSearchLabel = (window.fanficSearchBar && window.fanficSearchBar.i18n && window.fanficSearchBar.i18n.removeSearchTerm) ? window.fanficSearchBar.i18n.removeSearchTerm : 'Remove search term';
                    var searchValue = values[0];
                    pillsHtml += '<li class="fanfic-pill fanfic-pill-search" data-taxonomy="' + key + '" data-value="' + self.escapeAttr(searchValue) + '">';
                    pillsHtml += '<span class="fanfic-pill-label">' + searchLabel + ':</span>';
                    pillsHtml += '<span class="fanfic-pill-value-text">' + self.escapeHtml(searchValue) + '</span>';
                    pillsHtml += '<button type="button" class="fanfic-pill-value-remove" aria-label="' + self.escapeAttr(removeSearchLabel) + '">&times;</button>';
                    pillsHtml += '</li>';
                    return;
                }

                // Naked pills render just the value without the outer container
                if (taxConfig.naked) {
                    values.forEach(function(value) {
                        var displayValue = value.charAt(0).toUpperCase() + value.slice(1);
                        pillsHtml += '<li class="fanfic-pill fanfic-pill-naked" data-taxonomy="' + key + '" data-value="' + self.escapeAttr(value) + '">';
                        pillsHtml += '<span class="fanfic-pill-value-text">' + self.escapeHtml(displayValue) + '</span>';
                        pillsHtml += '<button type="button" class="fanfic-pill-value-remove" aria-label="Remove ' + self.escapeAttr(value) + '">&times;</button>';
                        pillsHtml += '</li>';
                    });
                    return;
                }

                // Generate pill for this taxonomy
                var pillHtml = '<li class="fanfic-pill" data-taxonomy="' + key + '">';

                pillHtml += '<span class="fanfic-pill-label">' + label + ':</span>';

                pillHtml += '<ul class="fanfic-pill-values">';

                values.forEach(function(value) {
                    // Capitalize first letter and strip counter suffix like " (123)"
                    var displayValue = value.replace(/\s*\([\d,]+\)\s*$/, '');
                    displayValue = displayValue.charAt(0).toUpperCase() + displayValue.slice(1);
                    pillHtml += '<li class="fanfic-pill-value" data-value="' + self.escapeAttr(value) + '">';
                    pillHtml += '<span class="fanfic-pill-value-text">' + self.escapeHtml(displayValue) + '</span>';
                    pillHtml += '<button type="button" class="fanfic-pill-value-remove" aria-label="Remove ' + self.escapeAttr(value) + '">&times;</button>';
                    pillHtml += '</li>';
                });

                pillHtml += '</ul></li>';
                pillsHtml += pillHtml;
            });

            return pillsHtml;
        },

        /**
         * Count active filter selections.
         */
        countActiveFilters: function(filters) {
            var count = 0;

            Object.keys(filters || {}).forEach(function(key) {
                var value = filters[key];

                if (Array.isArray(value)) {
                    count += value.length;
                } else if (value) {
                    count += 1;
                }
            });

            return count;
        },

        /**
         * Render pills to DOM
         */
        updatePills: function() {
            var filters = this.getPillRelevantFilters(this.getCurrentFilters());
            var pillsHtml = this.generatePills(filters);
            var activeCount = this.countActiveFilters(filters);

            var $container = $(this.config.containerSelector);
            var $section = $container.closest('.fanfic-current-filters-section');

            if (!$section.length) {
                return;
            }

            if (activeCount <= 0 || !pillsHtml) {
                $section.removeClass('is-visible');
                $container.empty();
                return;
            }

            $section.addClass('is-visible');
            $container.html('<ul class="fanfic-pills-container">' + pillsHtml + '</ul>');
            this.attachRemoveListeners();
        },

        /**
         * Attach click handlers to remove buttons
         */
        attachRemoveListeners: function() {
            var self = this;

            $(this.config.containerSelector).on('click', '.fanfic-pill-value-remove', function(e) {
                e.preventDefault();

                var $pill = $(this).closest('.fanfic-pill');
                var $value = $(this).closest('.fanfic-pill-value');
                var taxonomy = $pill.attr('data-taxonomy');
                // Naked pills store data-value on the pill itself
                var valueText = $value.length ? $value.attr('data-value') : $pill.attr('data-value');

                self.removeValueFromTaxonomy(taxonomy, valueText);
            });
        },

        /**
         * Remove a specific value from a taxonomy
         */
        removeValueFromTaxonomy: function(taxonomy, valueText) {
            var self = this;
            var customTaxonomies = window.fanficSearchBar && Array.isArray(window.fanficSearchBar.customTaxonomies) ? window.fanficSearchBar.customTaxonomies : [];
            var customTaxonomy = null;

            for (var i = 0; i < customTaxonomies.length; i++) {
                if (customTaxonomies[i] && customTaxonomies[i].slug === taxonomy) {
                    customTaxonomy = customTaxonomies[i];
                    break;
                }
            }

            // Map taxonomy to form selectors
            switch (taxonomy) {
                case 'search':
                    $('#fanfic-search-input').val('').trigger('input');
                    if (window.fanficSearchBarState && typeof window.fanficSearchBarState.setCommittedSearchTerm === 'function') {
                        window.fanficSearchBarState.setCommittedSearchTerm('');
                    }
                    if (window.fanficSearchBarState && typeof window.fanficSearchBarState.submitSearchForm === 'function') {
                        window.fanficSearchBarState.submitSearchForm(false);
                    }
                    break;

                case 'match_all':
                    $('#fanfic-match-all-filters').prop('checked', false).trigger('change');
                    break;

                case 'sort':
                    $('select[name="sort"]').val('').trigger('change');
                    break;

                case 'direction':
                    $('select[name="direction"]').val('').trigger('change');
                    break;

                case 'rating':
                    $('#fanfic-rating-min').val('0').trigger('input').trigger('change');
                    break;

                case 'language':
                    $('input[name="language[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                case 'status':
                    $('input[name="status[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                case 'genres':
                    $('input[name="genre[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                case 'age':
                    $('input[name="age[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                case 'fandoms':
                    $('.fanfic-selected-fandoms span').each(function() {
                        var label = $(this).clone().find('button').remove().end().text().trim();
                        if (label === valueText) {
                            $(this).find('.fanfic-pill-value-remove').trigger('click');
                        }
                    });
                    break;

                case 'warnings_exclude':
                    $('input[name="warnings_exclude[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                case 'warnings_include':
                    $('input[name="warnings_include[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                default:
                    if (customTaxonomy && customTaxonomy.selection_type === 'single') {
                        $('select[name="' + taxonomy + '"]').val('').trigger('change');
                        break;
                    }

                    // Custom taxonomy multi-select
                    var selector = '[name="' + taxonomy + '[]"]';
                    $(selector).each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
            }

            // Refresh all multi-select dropdown labels to reflect current state
            this.updateMultiSelectLabels();

            // Update pills after removal
            this.updatePills();
        },

        /**
         * Update all multi-select dropdown labels
         * Called after removing values to ensure dropdowns show correct count
         */
        updateMultiSelectLabels: function() {
            document.querySelectorAll('.multi-select:not(.fanfic-fandom-multiselect)').forEach(function(select) {
                var trigger = select.querySelector('.multi-select__trigger');
                var checkboxes = select.querySelectorAll('input[type="checkbox"]');
                var placeholder = select.dataset.placeholder || 'Select';

                var checked = Array.from(checkboxes).filter(function(cb) { return cb.checked; });

                if (checked.length === 0) {
                    trigger.textContent = placeholder;
                } else if (checked.length <= 2) {
                    trigger.textContent = checked.map(function(cb) { return cb.parentNode.textContent.trim(); }).join(', ');
                } else {
                    trigger.textContent = checked.length + ' selected';
                }
            });
        },

        /**
         * Escape HTML special characters
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Escape attribute value
         */
        escapeAttr: function(text) {
            return text.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        },

        /**
         * Initialize pills system
         */
        init: function() {
            // Initial render
            this.updatePills();

            // Update on form changes
            var self = this;
            $(document).on('change', self.config.formSelector + ' input, ' + self.config.formSelector + ' select', function() {
                self.updatePills();
            });

            // Update pills when fandoms are added/removed (custom event from fandoms JS)
            document.addEventListener('fanfic-fandoms-changed', function() {
                self.updatePills();
            });
        }
    };

    // ===== LANGUAGE FILTER MANAGER =====
    var FanficLanguageFilter = {
        /**
         * Normalizes a language code to lowercase hyphen form.
         * @param {string} lang The language code.
         * @returns {string} Normalized language code.
         */
        normalizeFullLanguageCode: function(lang) {
            return String(lang || '').toLowerCase().replace('_', '-');
        },

        /**
         * Normalizes a language code to its base (e.g., "pt-BR" -> "pt").
         * @param {string} lang The language code.
         * @returns {string} The base language code.
         */
        normalizeLanguageCode: function(lang) {
            return this.normalizeFullLanguageCode(lang).split('-')[0];
        },

        /**
         * Gets common variant codes for a given base language.
         * This list should be expanded based on the actual values used in your WordPress taxonomies.
         * @param {string} baseLang The base language code (e.g., 'pt', 'es', 'zh').
         * @returns {string[]} An array of language codes representing variants.
         */
        getLanguageVariants: function(baseLang) {
            switch (baseLang) {
                case 'pt':
                    return ['pt', 'pt-br']; // Canonical Portuguese variants
                case 'es':
                    return ['es-es', 'es-419']; // Canonical Spanish variants
                case 'zh':
                    return ['zh', 'zh-hans', 'zh-hant']; // Chinese variants from languages.json
                default:
                    return [baseLang]; // Return the base language itself if no specific variants are defined
            }
        },

        /**
         * Builds a preferred language order for sorting language options.
         * Includes explicit pt/es behavior and English fallback.
         *
         * @param {string} fullLanguage Full browser language (e.g., pt-br, es-mx).
         * @returns {string[]} Preferred language order.
         */
        buildPreferredLanguageOrder: function(fullLanguage) {
            var full = this.normalizeFullLanguageCode(fullLanguage);
            var base = this.normalizeLanguageCode(full);
            var preferred = [];

            if (base === 'pt') {
                preferred = full === 'pt-br'
                    ? ['pt-br', 'pt', 'en']
                    : ['pt', 'pt-br', 'en'];
            } else if (base === 'es') {
                preferred = (full === 'es-es' || full === 'es')
                    ? ['es-es', 'es-419', 'en']
                    : ['es-419', 'es-es', 'en'];
            } else {
                preferred = [full, base].concat(this.getLanguageVariants(base)).concat(['en']);
            }

            return preferred
                .filter(function(value) { return value && value.trim() !== ''; })
                .map(function(value) { return String(value).toLowerCase(); })
                .filter(function(value, index, arr) { return arr.indexOf(value) === index; });
        },

        /**
         * Prioritizes the user's browser language in the language filter dropdown.
         */
        prioritizeBrowserLanguage: function() {
            var browserLang = navigator.language || navigator.userLanguage;
            var normalizedFullBrowserLang = this.normalizeFullLanguageCode(browserLang || 'en');
            var preferredLangOrder = this.buildPreferredLanguageOrder(normalizedFullBrowserLang);

            console.log('Browser Language:', browserLang, 'Preferred Language Order:', preferredLangOrder);

            var $languageCheckboxes = $('.multi-select input[name="language[]"]');
            if (!$languageCheckboxes.length) {
                return;
            }

            var $languageSelectOptions = $languageCheckboxes.first().closest('.multi-select__dropdown, .multi-select__options');
            if (!$languageSelectOptions.length) {
                return;
            }

            var $allLanguageLabels = $languageSelectOptions.find('label');
            var prioritizedLabels = [];
            var otherLabels = [];

            $allLanguageLabels.each(function() {
                var $this = $(this);
                var langValue = String($this.find('input[name="language[]"]').val() || '').toLowerCase();
                if (langValue && preferredLangOrder.indexOf(langValue) !== -1) {
                    prioritizedLabels.push(this);
                } else {
                    otherLabels.push(this);
                }
            });

            // If we found prioritized languages, reorder them
            if (prioritizedLabels.length > 0) {
                // Sort prioritized labels by desired language order first.
                prioritizedLabels.sort(function(a, b) {
                    var aLang = String($(a).find('input[name="language[]"]').val() || '').toLowerCase();
                    var bLang = String($(b).find('input[name="language[]"]').val() || '').toLowerCase();

                    var aIndex = preferredLangOrder.indexOf(aLang);
                    var bIndex = preferredLangOrder.indexOf(bLang);
                    if (aIndex === -1) {
                        aIndex = Number.MAX_SAFE_INTEGER;
                    }
                    if (bIndex === -1) {
                        bIndex = Number.MAX_SAFE_INTEGER;
                    }

                    if (aIndex !== bIndex) {
                        return aIndex - bIndex;
                    }

                    return $(a).text().trim().localeCompare($(b).text().trim());
                });

                // Detach all labels and prepend prioritized ones, then append others
                $languageSelectOptions.empty().append(prioritizedLabels).append(otherLabels);
                console.log('Language filter reordered. Prioritized:', prioritizedLabels.map(function(label){ return $(label).text().trim(); }));
            } else {
                console.log('No matching prioritized languages found in the filter for browser language ' + browserLang);
            }
        }
    };

    $(document).ready(function() {
        // Initialize pills system
        PillsManager.init();

        // Initialize warnings manager for cross-disable logic
        WarningsManager.init();

        // Initialize translation deduplication
        TranslationDeduplicator.init();
        document.addEventListener('fanfic:load-more-appended', function() {
            TranslationDeduplicator.deduplicate();
        });

        var $advancedSearchToggle = $('.fanfic-advanced-search-toggle');
        var $advancedSearchAccordion = $('#fanfic-advanced-search-panel');
        var $advancedSearchFilters = $('.fanfic-advanced-search-filters');
        var $toggleIcon = $advancedSearchToggle.find('.dashicons');
        var $clearFiltersButton = $('#fanfic-clear-filters-button');
        var $searchInput = $('#fanfic-search-input');
        var $searchForm = $('.fanfic-stories-form');
        var $sortSelect = $('#fanfic-sort-filter');
        var $directionSelect = $('#fanfic-direction-filter');
        var $ratingFilter = $('#fanfic-rating-min');
        var $ratingValue = $('[data-fanfic-rating-value]');
        var $ageFilter = $('#fanfic-age-filter');
        var $warningsExcludeMultiSelect = $('.fanfic-warnings-exclude-multiselect');
        var $warningsIncludeMultiSelect = $('.fanfic-warnings-include-multiselect');
        var $smartToggleCheckbox = $('#fanfic-match-all-filters');
        var $smartToggleWrapper = $smartToggleCheckbox.closest('.fanfic-smart-toggle-wrapper');
        var $smartToggleLabel = $smartToggleWrapper.find('.fanfic-comment-toggle-label');
        var $currentFiltersSection = $('.fanfic-current-filters-section');

        searchFormState.committedSearchTerm = ($searchInput.val() || '').trim();

        function setCommittedSearchTerm(value) {
            searchFormState.committedSearchTerm = String(value || '').trim();
        }

        function canUseSessionStorage() {
            try {
                return typeof window.sessionStorage !== 'undefined';
            } catch (err) {
                return false;
            }
        }

        function getStoredLiveFilteringEnabled() {
            if (!canUseSessionStorage()) {
                return false;
            }

            return window.sessionStorage.getItem('fanficSearchLiveFilteringEnabled') === '1';
        }

        function setStoredLiveFilteringEnabled(isEnabled) {
            searchFormState.liveFilteringEnabled = !!isEnabled;

            if (!canUseSessionStorage()) {
                return;
            }

            window.sessionStorage.setItem('fanficSearchLiveFilteringEnabled', isEnabled ? '1' : '0');
        }

        function initializeLiveFilteringState() {
            var hasActiveQuery = !!(window.fanficSearchBar && window.fanficSearchBar.hasActiveQuery);
            searchFormState.liveFilteringEnabled = getStoredLiveFilteringEnabled() || hasActiveQuery;
        }

        function buildCleanSearchUrl(includeDraftSearch) {
            var baseUrl = $searchForm.attr('action') || window.location.pathname;
            var params = {};

            var searchVal = includeDraftSearch ? $searchInput.val() : searchFormState.committedSearchTerm;
            if (searchVal && String(searchVal).trim() !== '') {
                params.q = String(searchVal).trim();
            }

            var $sortSelectField = $searchForm.find('select[name="sort"]');
            if ($sortSelectField.length && $sortSelectField.val()) {
                params.sort = $sortSelectField.val();
            }

            var $directionSelectField = $searchForm.find('select[name="direction"]');
            if ($directionSelectField.length && $directionSelectField.val()) {
                params.direction = $directionSelectField.val();
            }

            var $ratingMin = $searchForm.find('input[name="rating_min"]');
            if ($ratingMin.length && parseFloat($ratingMin.val() || '0') > 0) {
                params.rating_min = $ratingMin.val();
            }

            if ($smartToggleCheckbox.is(':checked')) {
                params.match_all_filters = '1';
            }

            var checkboxGroups = [
                'status[]',
                'genre[]',
                'age[]',
                'language[]',
                'warnings_exclude[]',
                'warnings_include[]'
            ];

            checkboxGroups.forEach(function(groupName) {
                var values = [];
                $searchForm.find('input[name="' + groupName + '"]:checked').each(function() {
                    values.push($(this).val());
                });
                if (values.length > 0) {
                    var cleanName = groupName.replace(/\[\]$/, '');
                    params[cleanName] = values.join(' ');
                }
            });

            var fandomValues = [];
            $searchForm.find('input[name="fanfic_story_fandoms[]"]').each(function() {
                if ($(this).val()) {
                    fandomValues.push($(this).val());
                }
            });
            if (fandomValues.length > 0) {
                params.fandoms = fandomValues.join(' ');
            }

            $searchForm.find('.fanfic-advanced-search-filters select:not([name="sort"])').each(function() {
                var name = $(this).attr('name');
                if (!name) return;
                var val = $(this).val();
                if (val && val !== '') {
                    var cleanName = name.replace(/\[\]$/, '');
                    if (!params.hasOwnProperty(cleanName)) {
                        params[cleanName] = val;
                    }
                }
            });

            var handledNames = ['status', 'genre', 'age', 'language', 'warnings_exclude', 'warnings_include', 'fandoms'];
            $searchForm.find('.fanfic-advanced-search-filters .multi-select input[type="checkbox"]:checked').each(function() {
                var name = $(this).attr('name');
                if (!name) return;
                var cleanName = name.replace(/\[\]$/, '');
                if (handledNames.indexOf(cleanName) !== -1) return;
                if (!params[cleanName]) {
                    params[cleanName] = [];
                }
                if (Array.isArray(params[cleanName])) {
                    params[cleanName].push($(this).val());
                }
            });

            Object.keys(params).forEach(function(key) {
                if (Array.isArray(params[key])) {
                    params[key] = params[key].join(' ');
                }
            });

            var queryParts = [];
            Object.keys(params).forEach(function(key) {
                if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
                    var encoded = String(params[key]).split(/\s+/).map(encodeURIComponent).join(',');
                    queryParts.push(encodeURIComponent(key) + '=' + encoded);
                }
            });

            var queryString = queryParts.join('&');
            return queryString ? baseUrl + '?' + queryString : baseUrl;
        }

        function submitSearchForm(includeDraftSearch) {
            window.location.assign(buildCleanSearchUrl(!!includeDraftSearch));
        }

        function submitFiltersInstantly() {
            if (!searchFormState.liveFilteringEnabled) {
                return;
            }

            if (searchFormState.suppressAutoSubmit) {
                return;
            }

            clearTimeout(searchFormState.autoSubmitTimer);
            searchFormState.autoSubmitTimer = setTimeout(function() {
                submitSearchForm(false);
            }, 0);
        }

        function commitAndSubmitSearch() {
            setCommittedSearchTerm($searchInput.val());
            searchFormState.pendingSearchCommit = false;
            setStoredLiveFilteringEnabled(true);
            submitSearchForm(true);
        }

        window.fanficSearchBarState = window.fanficSearchBarState || {};
        window.fanficSearchBarState.setCommittedSearchTerm = setCommittedSearchTerm;
        window.fanficSearchBarState.setLiveFilteringEnabled = setStoredLiveFilteringEnabled;
        window.fanficSearchBarState.submitFiltersInstantly = submitFiltersInstantly;
        window.fanficSearchBarState.submitSearchForm = submitSearchForm;

        $(document).on('change', PillsManager.config.formSelector + ' input:not(#fanfic-search-input), ' + PillsManager.config.formSelector + ' select', function() {
            submitFiltersInstantly();
        });

        document.addEventListener('fanfic-fandoms-changed', function() {
            submitFiltersInstantly();
        });

        if ($currentFiltersSection.length && $searchForm.length) {
            var $targetForm = $searchForm.first();
            if ($currentFiltersSection.parent()[0] !== $targetForm[0]) {
                $currentFiltersSection.appendTo($targetForm);
            }
        }
        initializeLiveFilteringState();

        // Get single-select taxonomy list from PHP localization
        var singleSelectTaxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];
        var advancedSearchStateKey = 'fanficAdvancedSearchExpanded';

        function getAdvancedSearchExpandedState() {
            if (!canUseSessionStorage()) {
                return false;
            }

            return window.sessionStorage.getItem(advancedSearchStateKey) === '1';
        }

        function setAdvancedSearchExpandedState(isExpanded) {
            if (!canUseSessionStorage()) {
                return;
            }

            window.sessionStorage.setItem(advancedSearchStateKey, isExpanded ? '1' : '0');
        }

        function syncAdvancedSearchToggleUi(isExpanded) {
            $advancedSearchToggle.attr('aria-expanded', isExpanded ? 'true' : 'false');
            if (isExpanded) {
                $toggleIcon.removeClass('dashicons-plus').addClass('dashicons-minus');
            } else {
                $toggleIcon.removeClass('dashicons-minus').addClass('dashicons-plus');
            }
        }

        if (getAdvancedSearchExpandedState()) {
            $advancedSearchAccordion.show();
            syncAdvancedSearchToggleUi(true);
        }

        // Advanced search toggle functionality
        $advancedSearchToggle.on('click', function() {
            var isExpanded = $advancedSearchToggle.attr('aria-expanded') === 'true';

            $advancedSearchAccordion.stop(true, true).slideToggle(200);
            syncAdvancedSearchToggleUi(!isExpanded);
            setAdvancedSearchExpandedState(!isExpanded);
        });

        // Smart Toggle functionality (match_all_filters)
        $smartToggleCheckbox.on('change', function() {
            var isChecked = $(this).is(':checked');

            if (isChecked) {
                $smartToggleLabel.addClass('is-active');
                $smartToggleWrapper.addClass('is-active');
                // Enforce single-select for single-select taxonomies
                SmartFilterManager.enforceMatchAllFilters();
                // Attach listeners to prevent multiple selections going forward
                SmartFilterManager.attachSingleSelectEnforcement();
            } else {
                $smartToggleLabel.removeClass('is-active');
                $smartToggleWrapper.removeClass('is-active');
                // Re-enable multi-select for all checkboxes
                SmartFilterManager.disableMatchAllFilters();
            }

            // Update pills after toggling
            PillsManager.updatePills();
            submitFiltersInstantly();
        });
        // Set initial state
        if ($smartToggleCheckbox.is(':checked')) {
            $smartToggleLabel.addClass('is-active');
            $smartToggleWrapper.addClass('is-active');
        }

        function updateRatingDisplay(value) {
            var numericValue = parseFloat(value || '0');
            if (!$ratingValue.length) {
                return;
            }

            if (isNaN(numericValue) || numericValue <= 0) {
                $ratingValue.text('Any');
                return;
            }

            $ratingValue.text(
                numericValue.toLocaleString(undefined, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                }) + '+'
            );
        }

        updateRatingDisplay($ratingFilter.val());

        $ratingFilter.on('input', function() {
            updateRatingDisplay(this.value);
            PillsManager.updatePills();
        });

        $ratingFilter.on('change', function() {
            submitFiltersInstantly();
        });

        // Clear filters button functionality
        $clearFiltersButton.on('click', function(e) {
            e.preventDefault();

            searchFormState.suppressAutoSubmit = true;
            searchFormState.pendingSearchCommit = false;
            clearTimeout(searchFormState.autoSubmitTimer);

            // Reset all inputs
            $('#fanfic-search-input').val('');
            setCommittedSearchTerm('');
            if ($sortSelect.length) {
                $sortSelect.val('');
            }
            if ($directionSelect.length) {
                $directionSelect.val('desc');
            }
            if ($ratingFilter.length) {
                $ratingFilter.val('0');
                updateRatingDisplay('0');
            }
            if ($ageFilter.length) {
                $ageFilter.val('');
            }

            $('.fanfic-selected-fandoms').empty();
            $('#fanfic-fandom-filter').val('');
            document.dispatchEvent(new CustomEvent('fanfic-fandoms-changed'));

            // Reset both warnings multiselects
            if ($warningsExcludeMultiSelect.length) {
                $warningsExcludeMultiSelect.find('input[type="checkbox"]').prop('checked', false);
                var $excludeTrigger = $warningsExcludeMultiSelect.find('.multi-select__trigger')[0];
                if ($excludeTrigger) {
                    $excludeTrigger.textContent = $warningsExcludeMultiSelect.data('placeholder') || 'Select Warnings to Exclude';
                }
            }
            if ($warningsIncludeMultiSelect.length) {
                $warningsIncludeMultiSelect.find('input[type="checkbox"]').prop('checked', false);
                var $includeTrigger = $warningsIncludeMultiSelect.find('.multi-select__trigger')[0];
                if ($includeTrigger) {
                    $includeTrigger.textContent = $warningsIncludeMultiSelect.data('placeholder') || 'Select Warnings to Include';
                }
            }

            // Reset multi-select dropdowns (genres, languages, custom)
            document.querySelectorAll('.multi-select:not(.fanfic-warnings-exclude-multiselect):not(.fanfic-warnings-include-multiselect):not(.fanfic-fandom-multiselect)').forEach(function(select) {
                var checkboxes = select.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = false;
                });
                var trigger = select.querySelector('.multi-select__trigger');
                var placeholder = select.dataset.placeholder || 'Select';
                if (trigger) {
                    trigger.textContent = placeholder;
                }
            });

            // Reset single-select custom taxonomies
            $('.fanfic-advanced-search-filters select:not([multiple]):not(#fanfic-age-filter)').val('');

            // Reset Smart Toggle to OFF
            if ($smartToggleCheckbox.length) {
                $smartToggleCheckbox.prop('checked', false).trigger('change');
            }

            // Update pills
            PillsManager.updatePills();

            searchFormState.suppressAutoSubmit = false;
            submitFiltersInstantly();
        });

        // Search on Enter key press
        $searchInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                commitAndSubmitSearch();
            }
        });

        $(document).on('click', '.fanfic-search-submit', function() {
            searchFormState.pendingSearchCommit = true;
        });

        // ===== CLEAN URL FORM SUBMISSION INTERCEPTOR =====
        // Intercept form submission to build clean URLs with space-separated values
        // instead of PHP array notation (e.g., ?status=abandoned+ongoing instead of ?status%5B%5D=abandoned&status%5B%5D=ongoing)
        $searchForm.on('submit', function(e) {
            e.preventDefault();
            if (searchFormState.pendingSearchCommit) {
                commitAndSubmitSearch();
                searchFormState.pendingSearchCommit = false;
                return;
            }

            submitSearchForm(false);
        });

        // Prioritize browser language in the filter dropdown
        FanficLanguageFilter.prioritizeBrowserLanguage();

        // Initialize custom multi-select dropdowns
        document.querySelectorAll('.multi-select:not(.fanfic-fandom-multiselect)').forEach(function(select) {
            var trigger = select.querySelector('.multi-select__trigger');
            var checkboxes = select.querySelectorAll('input[type="checkbox"]');
            var placeholder = select.dataset.placeholder || 'Select';

            function updateLabel() {
                var checked = Array.from(checkboxes).filter(function(cb) { return cb.checked; });

                if (checked.length === 0) {
                    trigger.textContent = placeholder;
                } else if (checked.length <= 2) {
                    trigger.textContent = checked.map(function(cb) { return cb.parentNode.textContent.trim(); }).join(', ');
                } else {
                    trigger.textContent = checked.length + ' selected';
                }
            }

            // Initial update
            updateLabel();

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                select.classList.toggle('open');
            });

            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', updateLabel);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.multi-select.open').forEach(function(select) {
                if (!select.contains(e.target)) {
                    select.classList.remove('open');
                }
            });
        });
    });

})(jQuery);
