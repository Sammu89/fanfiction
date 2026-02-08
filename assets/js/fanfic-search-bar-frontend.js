(function($) {
    'use strict';

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
            { key: 'status', label: 'Status', type: 'select' },
            { key: 'fandoms', label: 'Fandom', type: 'custom', selector: '[name="fanfic_story_fandoms[]"]' },
            { key: 'genres', label: 'Genre', type: 'multi-select' },
            { key: 'age', label: 'Age', type: 'select' },
            { key: 'warnings_include', label: 'Including', type: 'warnings', mode: 'include' },
            { key: 'warnings_exclude', label: 'Excluding', type: 'warnings', mode: 'exclude' },
        ],

        /**
         * Get all current filter values from form
         */
        getCurrentFilters: function() {
            var filters = {};

            // Status select
            var statusVal = $('#fanfic-status-filter').val();
            if (statusVal) {
                filters.status = statusVal;
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

            // Age select
            var ageVal = $('#fanfic-age-filter').val();
            if (ageVal) {
                filters.age = ageVal;
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
                if (!values || values.length === 0) {
                    return;
                }

                // Generate pill for this taxonomy
                var pillHtml = '<li class="fanfic-pill" data-taxonomy="' + key + '">';
                pillHtml += '<span class="fanfic-pill-label">' + label + ':</span>';
                pillHtml += '<ul class="fanfic-pill-values">';

                values.forEach(function(value, index) {
                    var isLast = index === values.length - 1;
                    pillHtml += '<li class="fanfic-pill-value" data-value="' + self.escapeAttr(value) + '">';
                    pillHtml += '<span class="fanfic-pill-value-text">' + self.escapeHtml(value) + '</span>';
                    pillHtml += '<button type="button" class="fanfic-pill-value-remove" aria-label="Remove ' + self.escapeAttr(value) + '">&times;</button>';
                    if (!isLast) {
                        pillHtml += '<span class="fanfic-pill-value-separator">,</span>';
                    }
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
                    $('#fanfic-status-filter').val('').trigger('change');
                    break;

                case 'genres':
                    $('input[name="genre[]"]').each(function() {
                        if ($(this).closest('label').text().trim() === valueText) {
                            $(this).prop('checked', false).trigger('change');
                        }
                    });
                    break;

                case 'age':
                    $('#fanfic-age-filter').val('').trigger('change');
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
            if ($(this).is(':checked')) {
                $smartToggleLabel.addClass('is-active');
            } else {
                $smartToggleLabel.removeClass('is-active');
            }
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
