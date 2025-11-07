/**
 * Fanfiction Manager - Setup Wizard JavaScript
 *
 * Handles wizard step navigation, form validation, and AJAX submissions.
 *
 * @package FanfictionManager
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Wizard object
	 */
	var FanficWizard = {
		/**
		 * Initialize wizard
		 */
		init: function() {
			this.bindEvents();
			this.updatePreviewSlugs();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Next button click
			$('.fanfic-wizard-next').on('click', this.handleNext.bind(this));

			// Complete button click
			$('.fanfic-wizard-complete').on('click', this.handleComplete.bind(this));

			// Live preview updates for base slug
			$('#fanfic_base_slug').on('input', this.updateBaseSlugPreview.bind(this));

			// Live preview updates for story path
			$('#fanfic_story_path').on('input', this.updateStoryPathPreview.bind(this));

			// Live preview updates for secondary paths
			$('input[name^="fanfic_secondary_paths"]').on('input', this.updateSecondaryPathPreview.bind(this));

			// Form validation on input
			$('.fanfic-wizard-form input[required]').on('blur', function() {
			FanficWizard.validateField.call(this);
		});
		},

		/**
		 * Handle next button click
		 */
		handleNext: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var currentStep = parseInt(fanficWizard.current_step, 10);

			// Validate current step
			if (!this.validateStep(currentStep)) {
				return;
			}

			// Save current step data (only for steps that need saving)
			if (currentStep === 2 || currentStep === 3 || currentStep === 4) {
				this.saveStep(currentStep, function(response) {
					if (response.success) {
						console.log('Fanfic Wizard: AJAX success - Navigating to:', response.data.next_url);
						window.location.href = response.data.next_url;
					}
				});
			} else {
				// No saving needed, just navigate
				var nextUrl = fanficWizard.admin_url + 'admin.php?page=fanfic-setup-wizard&step=' + (currentStep + 1);
				// Preserve force parameter if present
				nextUrl += FanficWizard.getForceParameter();
				console.log("Fanfic Wizard: Building next URL", nextUrl);
				window.location.href = nextUrl;
			}
		},

		/**
		 * Handle complete button click
		 */
		handleComplete: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);

			// Show confirmation
			if (!confirm(fanficWizard.strings.confirm || 'Are you sure you want to complete the setup? This will create all system pages.')) {
				return;
			}

			// Disable button and show loading
			$button.prop('disabled', true);
			$button.html('<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>' + fanficWizard.strings.completing);

			// Show progress status
			$('#fanfic-wizard-completion-status').show();

			// Send AJAX request to complete wizard
			$.ajax({
				url: fanficWizard.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'fanfic_wizard_complete',
					nonce: fanficWizard.nonce
				},
				success: function(response) {
					if (response.success) {
						// Show success message
						FanficWizard.showMessage('success', response.data.message);

						// Redirect after a short delay
						setTimeout(function() {
							window.location.href = response.data.redirect_url;
						}, 1500);
					} else {
						// Show error message
						FanficWizard.showMessage('error', response.data.message || fanficWizard.strings.error);
						$button.prop('disabled', false);
						$button.text(fanficWizard.strings.complete_setup || 'Complete Setup');
						$('#fanfic-wizard-completion-status').hide();
					}
				},
				error: function() {
					FanficWizard.showMessage('error', fanficWizard.strings.error);
					$button.prop('disabled', false);
					$button.text(fanficWizard.strings.complete_setup || 'Complete Setup');
					$('#fanfic-wizard-completion-status').hide();
				}
			});
		},

		/**
		 * Validate current step
		 *
		 * @param {number} step Step number
		 * @return {boolean} True if valid
		 */
		validateStep: function(step) {
			var $form = $('#fanfic-wizard-form-step-' + step);

			if ($form.length === 0) {
				return true; // No form to validate
			}

			var isValid = true;
			var $requiredFields = $form.find('input[required], select[required]');

			$requiredFields.each(function() {
				if (!FanficWizard.validateField.call(this)) {
					isValid = false;
				}
			});

			if (!isValid) {
				this.showMessage('error', 'Please fill in all required fields correctly.');
			}

			return isValid;
		},

		/**
		 * Validate individual field
		 *
		 * @return {boolean} True if valid
		 */
		validateField: function() {
			var $field = $(this);
			var value = $field.val().trim();
			var pattern = $field.attr('pattern');
			var maxLength = parseInt($field.attr('maxlength'), 10);
			var isValid = true;
			var errorMessage = '';

			// Check if required field is empty
			if ($field.prop('required') && value === '') {
				isValid = false;
				errorMessage = 'This field is required.';
			}

			// Check pattern validation
			if (isValid && pattern && value !== '') {
				var regex = new RegExp('^' + pattern + '$');
				if (!regex.test(value)) {
					isValid = false;
					errorMessage = 'Please use only lowercase letters, numbers, and hyphens.';
				}
			}

			// Check max length
			if (isValid && maxLength && value.length > maxLength) {
				isValid = false;
				errorMessage = 'Maximum ' + maxLength + ' characters allowed.';
			}

			// Show/hide error message
			var $errorMsg = $field.siblings('.field-error');
			if (!isValid) {
				if ($errorMsg.length === 0) {
					$field.after('<span class="field-error" style="color: #d63638; display: block; margin-top: 5px;">' + errorMessage + '</span>');
				} else {
					$errorMsg.text(errorMessage);
				}
				$field.css('border-color', '#d63638');
			} else {
				$errorMsg.remove();
				$field.css('border-color', '');
			}

			return isValid;
		},

		/**
		 * Save step data via AJAX
		 *
		 * @param {number} step Step number
		 * @param {function} callback Success callback
		 */
		saveStep: function(step, callback) {
			var $form = $('#fanfic-wizard-form-step-' + step);
			var formData = $form.serialize();

			// Add action and nonce
			formData += '&action=fanfic_wizard_save_step';
			formData += '&nonce=' + fanficWizard.nonce;
			formData += '&step=' + step;

			// Check if force parameter is present in current URL and add to AJAX data
			var urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('force') === 'true') {
				formData += '&force=true';
				console.log('Fanfic Wizard: Including force=true in AJAX request for step', step);
			}

			// Show loading
			$('.fanfic-wizard-next').prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>' + fanficWizard.strings.saving);

			$.ajax({
				url: fanficWizard.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: formData,
				success: function(response) {
					if (response.success) {
						callback(response);
					} else {
						FanficWizard.showMessage('error', response.data.message || fanficWizard.strings.error);
						$('.fanfic-wizard-next').prop('disabled', false).text('Next');
					}
				},
				error: function() {
					FanficWizard.showMessage('error', fanficWizard.strings.error);
					$('.fanfic-wizard-next').prop('disabled', false).text('Next');
				}
			});
		},

		/**
		 * Update base slug preview
		 */
		updateBaseSlugPreview: function(e) {
			var slug = $(e.currentTarget).val().trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
			$('.fanfic-base-slug-preview').text(slug || 'fanfiction');
		},

		/**
		 * Update story path preview
		 */
		updateStoryPathPreview: function(e) {
			var path = $(e.currentTarget).val().trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
			$('.fanfic-story-path-preview').text(path || 'stories');
		},

		/**
		 * Update secondary path preview
		 */
		updateSecondaryPathPreview: function(e) {
			var $input = $(e.currentTarget);
			var path = $input.val().trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
			var pathKey = $input.attr('name').match(/\[([a-z]+)\]/)[1];

			$('.fanfic-path-preview[data-path="' + pathKey + '"]').text(path || pathKey);
		},

		/**
		 * Initialize preview slugs
		 */
		updatePreviewSlugs: function() {
			// Update base slug preview
			var baseSlug = $('#fanfic_base_slug').val();
			if (baseSlug) {
				$('.fanfic-base-slug-preview').text(baseSlug);
			}

			// Update story path preview
			var storyPath = $('#fanfic_story_path').val();
			if (storyPath) {
				$('.fanfic-story-path-preview').text(storyPath);
			}

			// Update secondary path previews
			$('input[name^="fanfic_secondary_paths"]').each(function() {
				var $input = $(this);
				var path = $input.val();
				var pathKey = $input.attr('name').match(/\[([a-z]+)\]/)[1];
				if (path) {
					$('.fanfic-path-preview[data-path="' + pathKey + '"]').text(path);
				}
			});
		},

		/**
		 * Show message to user
		 *
		 * @param {string} type Message type (success, error, info)
		 * @param {string} message Message text
		 */
		showMessage: function(type, message) {
			var iconClass = '';
			switch (type) {
				case 'success':
					iconClass = 'dashicons-yes-alt';
					break;
				case 'error':
					iconClass = 'dashicons-dismiss';
					break;
				case 'info':
					iconClass = 'dashicons-info';
					break;
			}

			var $message = $('<div class="fanfic-wizard-message ' + type + '">' +
				'<span class="dashicons ' + iconClass + '"></span>' +
				'<span>' + message + '</span>' +
				'</div>');

			$('.fanfic-wizard-messages').empty().append($message);

			// Scroll to message
			if ($message.offset()) {
				$('html, body').animate({
					scrollTop: $message.offset().top - 100
				}, 500);
			}

			// Auto-hide success messages after 5 seconds
			if (type === 'success') {
				setTimeout(function() {
					$message.fadeOut(function() {
						$(this).remove();
					});
				}, 5000);
			}
		},

		/**
		 * Clear messages
		 */
		clearMessages: function() {
			$('.fanfic-wizard-messages').empty();
		},

		/**
		 * Get force parameter from current URL if present
		 *
		 * @return {string} Force parameter string or empty string
		 */
		getForceParameter: function() {
			var urlParams = new URLSearchParams(window.location.search);
			var hasForce = urlParams.get('force') === 'true';
			console.log('Fanfic Wizard: Checking force parameter - Found:', hasForce);
			if (hasForce) {
				return '&force=true';
			}
			return '';
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		if ($('.fanfic-wizard-wrap').length > 0) {
			FanficWizard.init();
		}
	});

})(jQuery);
