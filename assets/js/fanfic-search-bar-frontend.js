(function($) {
    'use strict';

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
            document.querySelectorAll('.multi-select').forEach(function(select) {
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
            var variants = this.getLanguageVariants(base);

            var preferred = [full, base].concat(variants)
                .filter(function(value) { return value && value.trim() !== ''; })
                .map(function(value) { return String(value).toLowerCase(); })
                .filter(function(value, index, arr) { return arr.indexOf(value) === index; });

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
         * 1) exact browser variant (e.g., pt-pt)
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
        },

        // Taxonomy order (determines pill order)
        taxonomyOrder: [
            { key: 'match_all', label: 'Match all filters', type: 'toggle', naked: true },
            { key: 'language', label: 'Language', type: 'multi-select' },
            { key: 'status', label: 'Status', type: 'multi-select' },
            { key: 'fandoms', label: 'Fandom', type: 'custom', selector: '[name="fanfic_story_fandoms[]"]' },
            { key: 'genres', label: 'Genre', type: 'multi-select' },
            { key: 'age', label: 'Age', type: 'multi-select' },
            { key: 'warnings_include', label: 'Including', type: 'warnings' },
            { key: 'warnings_exclude', label: 'Excluding', type: 'warnings' },
        ],

        /**
         * Get all current filter values from form
         */
        getCurrentFilters: function() {
            var filters = {};

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
                var label = $(this).closest('.fanfic-selected-fandom').clone().find('button').remove().end().text().trim();
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

            // Custom taxonomies (multi-select and single-select dropdowns)
            var singleSelectTaxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

            // Collect all custom taxonomy names from selectors on page
            document.querySelectorAll('.fanfic-advanced-search-filters select:not([multiple]), .fanfic-advanced-search-filters .multi-select:not(.fanfic-warnings-exclude-multiselect):not(.fanfic-warnings-include-multiselect)').forEach(function(elem) {
                var selector, values = [];

                // Check if it's a select element (single-select custom taxonomy)
                if (elem.tagName === 'SELECT') {
                    selector = 'select[name="' + elem.name + '"]';
                    var selectedOption = document.querySelector(selector);
                    if (selectedOption && selectedOption.value) {
                        values.push($(selectedOption).find('option:selected').text().trim());
                    }
                } else if (elem.classList.contains('multi-select')) {
                    // Get the multi-select's name from its checkboxes
                    var $checkboxes = $(elem).find('input[type="checkbox"]:checked');
                    if ($checkboxes.length > 0) {
                        var fieldName = $checkboxes.first().attr('name');
                        if (fieldName) {
                            $checkboxes.each(function() {
                                values.push($(this).closest('label').text().trim());
                            });
                        }
                    }
                }

                // Store custom taxonomy values
                if (values.length > 0 && elem.name) {
                    var taxonomyName = elem.name.replace(/\[\]$/, ''); // Remove [] suffix if present
                    if (taxonomyName && !['status', 'age', 'language', 'genre', 'warnings_exclude', 'warnings_include'].includes(taxonomyName)) {
                        filters[taxonomyName] = values;
                    }
                }
            });

            return filters;
        },

        /**
         * Generate pills HTML from filters
         */
        generatePills: function(filters) {
            var self = this;
            var pillsHtml = '';

            // Generate pills in defined order
            this.taxonomyOrder.forEach(function(taxConfig) {
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
         * Render pills to DOM
         */
        updatePills: function() {
            var filters = this.getCurrentFilters();
            var pillsHtml = this.generatePills(filters);

            var $container = $(this.config.containerSelector);
            var $section = $container.closest('.fanfic-current-filters-section');

            if (pillsHtml) {
                var containerHtml = '<ul class="fanfic-pills-container">' + pillsHtml + '</ul>';
                $container.html(containerHtml);
                this.attachRemoveListeners();

                // Show the section when pills are present
                if ($section.length) {
                    $section.show();
                }
            } else {
                $container.empty();

                // Hide the section when no pills
                if ($section.length) {
                    $section.hide();
                }
            }
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

            // Map taxonomy to form selectors
            switch (taxonomy) {
                case 'match_all':
                    $('#fanfic-match-all-filters').prop('checked', false).trigger('change');
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
                            $(this).find('.fanfic-remove-fandom').trigger('click');
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
                    // Custom taxonomy
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
            document.querySelectorAll('.multi-select').forEach(function(select) {
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

            // Debounce text input
            var searchDebounce;
            $(self.config.formSelector + ' #fanfic-search-input').on('keyup', function() {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(function() {
                    self.updatePills();
                }, 300);
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
         * Normalizes a language code to its base (e.g., "pt-BR" -> "pt").
         * @param {string} lang The language code.
         * @returns {string} The base language code.
         */
        normalizeLanguageCode: function(lang) {
            return lang.toLowerCase().split('-')[0];
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
                    return ['pt', 'pt-pt', 'pt-br']; // Portuguese variants from languages.json
                case 'es':
                    return ['es', 'es-es', 'es-419']; // Spanish variants from languages.json
                case 'zh':
                    return ['zh', 'zh-hans', 'zh-hant']; // Chinese variants from languages.json
                default:
                    return [baseLang]; // Return the base language itself if no specific variants are defined
            }
        },

        /**
         * Prioritizes the user's browser language in the language filter dropdown.
         */
        prioritizeBrowserLanguage: function() {
            var browserLang = navigator.language || navigator.userLanguage;
            var normalizedFullBrowserLang = String(browserLang || '').toLowerCase().replace('_', '-');
            var normalizedBrowserLang = this.normalizeLanguageCode(browserLang);
            var prioritizedLangs = this.getLanguageVariants(normalizedBrowserLang);
            var preferredLangOrder = [normalizedFullBrowserLang, normalizedBrowserLang].concat(prioritizedLangs)
                .filter(function(value) { return value && value.trim() !== ''; })
                .map(function(value) { return value.toLowerCase(); })
                .filter(function(value, index, arr) { return arr.indexOf(value) === index; });

            console.log('Browser Language:', browserLang, 'Normalized:', normalizedBrowserLang, 'Prioritized Variants:', prioritizedLangs);

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

        var $advancedSearchToggle = $('.fanfic-advanced-search-toggle');
        var $advancedSearchFilters = $('.fanfic-advanced-search-filters');
        var $advancedActions = $('.fanfic-advanced-actions');
        var $toggleIcon = $advancedSearchToggle.find('.dashicons');
        var $clearFiltersButton = $('#fanfic-clear-filters-button');
        var $searchInput = $('#fanfic-search-input');
        var $searchForm = $('.fanfic-stories-form');
        var $ageFilter = $('#fanfic-age-filter');
        var $warningsExcludeMultiSelect = $('.fanfic-warnings-exclude-multiselect');
        var $warningsIncludeMultiSelect = $('.fanfic-warnings-include-multiselect');
        var $smartToggleCheckbox = $('#fanfic-match-all-filters');
        var $smartToggleLabel = $smartToggleCheckbox.closest('.fanfic-stories-row').find('.fanfic-toggle-label');

        // Get single-select taxonomy list from PHP localization
        var singleSelectTaxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

        // Advanced search toggle functionality
        $advancedSearchToggle.on('click', function() {
            var isExpanded = $advancedSearchToggle.attr('aria-expanded') === 'true';

            $advancedSearchFilters.slideToggle(200);
            $advancedActions.slideToggle(200);

            $advancedSearchToggle.attr('aria-expanded', !isExpanded);

            if (isExpanded) {
                $toggleIcon.removeClass('dashicons-minus').addClass('dashicons-plus');
            } else {
                $toggleIcon.removeClass('dashicons-plus').addClass('dashicons-minus');
            }
        });

        // Smart Toggle functionality (match_all_filters)
        $smartToggleCheckbox.on('change', function() {
            var isChecked = $(this).is(':checked');

            if (isChecked) {
                $smartToggleLabel.addClass('is-active');
                // Enforce single-select for single-select taxonomies
                SmartFilterManager.enforceMatchAllFilters();
                // Attach listeners to prevent multiple selections going forward
                SmartFilterManager.attachSingleSelectEnforcement();
            } else {
                $smartToggleLabel.removeClass('is-active');
                // Re-enable multi-select for all checkboxes
                SmartFilterManager.disableMatchAllFilters();
            }

            // Update pills after toggling
            PillsManager.updatePills();
        });
        // Set initial state
        if ($smartToggleCheckbox.is(':checked')) {
            $smartToggleLabel.addClass('is-active');
        }

        // Clear filters button functionality
        $clearFiltersButton.on('click', function(e) {
            e.preventDefault();

            // Reset all inputs
            if ($ageFilter.length) {
                $ageFilter.val('');
            }

            $('.fanfic-selected-fandoms').empty();
            $('#fanfic-fandom-filter').val('');

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
            document.querySelectorAll('.multi-select:not(.fanfic-warnings-exclude-multiselect):not(.fanfic-warnings-include-multiselect)').forEach(function(select) {
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
        });

        // Search on Enter key press
        $searchInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $searchForm.submit();
            }
        });

        // ===== CLEAN URL FORM SUBMISSION INTERCEPTOR =====
        // Intercept form submission to build clean URLs with space-separated values
        // instead of PHP array notation (e.g., ?status=abandoned+ongoing instead of ?status%5B%5D=abandoned&status%5B%5D=ongoing)
        $searchForm.on('submit', function(e) {
            e.preventDefault();

            var baseUrl = $searchForm.attr('action') || window.location.pathname;
            var params = {};

            // 1. Search text input - include only if non-empty
            var searchVal = $searchInput.val();
            if (searchVal && searchVal.trim() !== '') {
                params.search = searchVal.trim();
            }

            // 2. Sort select - include only if non-empty
            var $sortSelect = $searchForm.find('select[name="sort"]');
            if ($sortSelect.length && $sortSelect.val()) {
                params.sort = $sortSelect.val();
            }

            // 3. Match all filters toggle - include as match_all_filters=1 only if checked
            if ($smartToggleCheckbox.is(':checked')) {
                params.match_all_filters = '1';
            }

            // 4. Multi-select checkbox groups - collect checked values, join with space
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

            // 5. Fandom hidden inputs - collect all values, join with space
            var fandomValues = [];
            $searchForm.find('input[name="fanfic_story_fandoms[]"]').each(function() {
                if ($(this).val()) {
                    fandomValues.push($(this).val());
                }
            });
            if (fandomValues.length > 0) {
                params.fandoms = fandomValues.join(' ');
            }

            // 6. Custom taxonomy selects and checkboxes in advanced filters
            //    (single selects as-is, multi-select checkboxes space-joined without [])
            $searchForm.find('.fanfic-advanced-search-filters select:not([name="sort"])').each(function() {
                var name = $(this).attr('name');
                if (!name) return;
                var val = $(this).val();
                if (val && val !== '') {
                    var cleanName = name.replace(/\[\]$/, '');
                    // Skip already-handled fields
                    if (!params.hasOwnProperty(cleanName)) {
                        params[cleanName] = val;
                    }
                }
            });

            // Custom taxonomy multi-select checkboxes not already handled
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

            // Convert any remaining arrays to space-joined strings
            Object.keys(params).forEach(function(key) {
                if (Array.isArray(params[key])) {
                    params[key] = params[key].join(' ');
                }
            });

            // 7. Build query string from non-empty params (use comma as value separator)
            var queryParts = [];
            Object.keys(params).forEach(function(key) {
                if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
                    // Encode each segment individually, join with literal comma
                    var encoded = String(params[key]).split(/\s+/).map(encodeURIComponent).join(',');
                    queryParts.push(encodeURIComponent(key) + '=' + encoded);
                }
            });

            var queryString = queryParts.join('&');
            var finalUrl = queryString ? baseUrl + '?' + queryString : baseUrl;

            // Navigate to the clean URL
            window.location.assign(finalUrl);
        });

        // Prioritize browser language in the filter dropdown
        FanficLanguageFilter.prioritizeBrowserLanguage();

        // Initialize custom multi-select dropdowns
        document.querySelectorAll('.multi-select').forEach(function(select) {
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
