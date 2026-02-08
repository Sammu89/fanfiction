(function($) {
    'use strict';

    // ===== SMART FILTER MANAGER (Match All Logic) =====
    var SmartFilterManager = {
        /**
         * Enforce single-select mode for single-select taxonomies when match_all is ON
         */
        enforceMatchAllFilters: function() {
            var taxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

            var self = this;
            taxonomies.forEach(function(taxName) {
                self.enforceSingleSelectForTaxonomy(taxName);
            });
        },

        /**
         * Enforce single-select for a specific taxonomy
         */
        enforceSingleSelectForTaxonomy: function(taxName) {
            var checkboxes = this.getCheckboxesForTaxonomy(taxName);
            if (checkboxes.length === 0) return;

            var checked = checkboxes.filter(':checked');
            if (checked.length <= 1) {
                return; // Already single or empty
            }

            // Keep only the last checked one
            var lastCheckedElement = checked.last()[0]; // Get DOM element

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
         * Disable match_all_filters restrictions - allow multi-select again
         */
        disableMatchAllFilters: function() {
            // No action needed here - just remove the restriction
            // Checkboxes are already enabled, just allow multiple selections
        }
    };

    // ===== WARNINGS CROSS-DISABLE MANAGER =====
    // Note: Warnings use a single set of checkboxes with warnings_mode radio to switch between include/exclude
    // Cross-disable prevents selecting the same warning in both modes simultaneously
    var WarningsManager = {
        // Store which warnings are selected in each mode
        includeWarnings: {},
        excludeWarnings: {},

        /**
         * Initialize warnings cross-disable logic
         */
        init: function() {
            var self = this;
            var $warningsCheckboxes = $('input[name="warnings_slugs[]"]');
            var $warningsModeRadios = $('input[name="warnings_mode"]');

            // Load initial state from form
            this.loadState();

            // Watch for changes on warning checkboxes
            $warningsCheckboxes.on('change', function() {
                self.onWarningCheckboxChange($(this));
            });

            // Watch for mode changes (Include/Exclude radio buttons)
            $warningsModeRadios.on('change', function() {
                self.onModeChange();
            });
        },

        /**
         * Load current state from form
         */
        loadState: function() {
            // Since there's only one set of checkboxes, we store the checked values
            // in a way that lets us restore them when switching modes
            var $warningsCheckboxes = $('input[name="warnings_slugs[]"]');
            var currentMode = $('input[name="warnings_mode"]:checked').val() || 'exclude';

            // Initialize storage if needed
            if (!window.fanficWarningsState) {
                window.fanficWarningsState = {
                    include: {},
                    exclude: {}
                };
            }

            // Load current checked values into the appropriate mode storage
            $warningsCheckboxes.each(function() {
                var $cb = $(this);
                var value = $cb.val();
                window.fanficWarningsState[currentMode][value] = $cb.is(':checked');
            });
        },

        /**
         * Handle warning checkbox change
         */
        onWarningCheckboxChange: function($checkbox) {
            var value = $checkbox.val();
            var currentMode = $('input[name="warnings_mode"]:checked').val() || 'exclude';
            var isChecked = $checkbox.is(':checked');

            // Update state storage
            if (!window.fanficWarningsState) {
                window.fanficWarningsState = { include: {}, exclude: {} };
            }
            window.fanficWarningsState[currentMode][value] = isChecked;

            // If checking in one mode, uncheck in other mode
            if (isChecked) {
                var otherMode = currentMode === 'include' ? 'exclude' : 'include';
                window.fanficWarningsState[otherMode][value] = false;
            }

            PillsManager.updatePills();
        },

        /**
         * Handle mode change (Include/Exclude radio)
         */
        onModeChange: function() {
            var newMode = $('input[name="warnings_mode"]:checked').val() || 'exclude';
            var $warningsCheckboxes = $('input[name="warnings_slugs[]"]');

            // Initialize state storage if needed
            if (!window.fanficWarningsState) {
                window.fanficWarningsState = { include: {}, exclude: {} };
            }

            // Update checkboxes to reflect the new mode's saved state
            $warningsCheckboxes.each(function() {
                var $cb = $(this);
                var value = $cb.val();
                var shouldCheck = window.fanficWarningsState[newMode][value] || false;
                $cb.prop('checked', shouldCheck);
            });

            // Update the display label in the multi-select trigger
            var trigger = $('.fanfic-warnings-multiselect .multi-select__trigger');
            if (trigger.length) {
                var checked = $warningsCheckboxes.filter(':checked').length;
                var placeholder = $('.fanfic-warnings-multiselect').data('placeholder') || 'Select Warnings';
                if (checked === 0) {
                    trigger.text(placeholder);
                } else {
                    trigger.text(checked + ' selected');
                }
            }

            PillsManager.updatePills();
        }
    };

    // ===== GENERIC PILLS SYSTEM =====
    var PillsManager = {
        // Configuration
        config: {
            containerSelector: '[data-fanfic-active-filters]',
            formSelector: '.fanfic-browse-form',
        },

        // Taxonomy order (determines pill order)
        taxonomyOrder: [
            { key: 'match_all', label: 'Match all filters', type: 'toggle' },
            { key: 'language', label: 'Language', type: 'multi-select' },
            { key: 'status', label: 'Status', type: 'multi-select' },
            { key: 'fandoms', label: 'Fandom', type: 'custom', selector: '[name="fanfic_story_fandoms[]"]' },
            { key: 'genres', label: 'Genre', type: 'multi-select' },
            { key: 'age', label: 'Age', type: 'multi-select' },
            { key: 'warnings_include', label: 'Including', type: 'warnings', mode: 'include' },
            { key: 'warnings_exclude', label: 'Excluding', type: 'warnings', mode: 'exclude' },
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

            // Warnings (separate by include/exclude mode)
            var warningsMode = $('input[name="warnings_mode"]:checked').val() || 'exclude';
            var warnings = [];
            $('input[name="warnings_slugs[]"]:checked').each(function() {
                warnings.push($(this).closest('label').text().trim());
            });
            if (warnings.length) {
                if (warningsMode === 'include') {
                    filters.warnings_include = warnings;
                } else {
                    filters.warnings_exclude = warnings;
                }
            }

            // Custom taxonomies (multi-select and single-select dropdowns)
            var singleSelectTaxonomies = window.fanficSearchBar && window.fanficSearchBar.singleSelectTaxonomies ? window.fanficSearchBar.singleSelectTaxonomies : ['status', 'age', 'language'];

            // Collect all custom taxonomy names from selectors on page
            document.querySelectorAll('.fanfic-advanced-search-filters select:not([multiple]), .fanfic-advanced-search-filters .multi-select:not(.fanfic-warnings-multiselect)').forEach(function(elem) {
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
                    if (taxonomyName && !['status', 'age', 'language', 'genre', 'warnings_slugs'].includes(taxonomyName)) {
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
                } else if (taxConfig.type === 'warnings') {
                    if (taxConfig.mode === 'include') {
                        values = filters.warnings_include || null;
                    } else {
                        values = filters.warnings_exclude || null;
                    }
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

                // Generate pill for this taxonomy
                var pillHtml = '<li class="fanfic-pill" data-taxonomy="' + key + '">';
                pillHtml += '<span class="fanfic-pill-label">' + label + ':</span>';
                pillHtml += '<ul class="fanfic-pill-values">';

                values.forEach(function(value) {
                    // Capitalize first letter of value for better aesthetics
                    var displayValue = value.charAt(0).toUpperCase() + value.slice(1);
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
                var valueText = $value.attr('data-value');

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

                case 'warnings_include':
                case 'warnings_exclude':
                    $('input[name="warnings_slugs[]"]').each(function() {
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
        }
    };

    $(document).ready(function() {
        // Initialize pills system
        PillsManager.init();

        // Initialize warnings manager for cross-disable logic
        WarningsManager.init();

        var $advancedSearchToggle = $('.fanfic-advanced-search-toggle');
        var $advancedSearchFilters = $('.fanfic-advanced-search-filters');
        var $advancedActions = $('.fanfic-advanced-actions');
        var $toggleIcon = $advancedSearchToggle.find('.dashicons');
        var $clearFiltersButton = $('#fanfic-clear-filters-button');
        var $searchInput = $('#fanfic-search-input');
        var $searchForm = $('.fanfic-browse-form');
        var $ageFilter = $('#fanfic-age-filter');
        var $warningsModeRadios = $('input[name="warnings_mode"]');
        var $warningsMultiSelect = $('.fanfic-warnings-multiselect');
        var $smartToggleCheckbox = $('#fanfic-match-all-filters');
        var $smartToggleLabel = $smartToggleCheckbox.closest('.fanfic-browse-row').find('.fanfic-toggle-label');

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

            if ($warningsModeRadios.length) {
                $('input[name="warnings_mode"][value="exclude"]').prop('checked', true);
            }
            if ($warningsMultiSelect.length) {
                $warningsMultiSelect.find('input[type="checkbox"]').prop('checked', false);
                var $trigger = $warningsMultiSelect.find('.multi-select__trigger')[0];
                if ($trigger) {
                    $trigger.textContent = $warningsMultiSelect.data('placeholder') || 'Select Warnings';
                }
            }

            // Reset multi-select dropdowns (genres, languages, custom)
            document.querySelectorAll('.multi-select:not(.fanfic-warnings-multiselect)').forEach(function(select) {
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

        // Warnings Mode change logic
        $warningsModeRadios.on('change', function() {
            if ($(this).val() === 'include') {
                if ($ageFilter.length) {
                    $ageFilter.val('');
                }
            }
        });

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
