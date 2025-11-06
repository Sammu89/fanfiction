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
			$('.fanfic-wizard-form input[required]').on('blur', this.validateField.bind(this));
		},

		/**
		 * Validate individual field
		 *
		 * @return {boolean} True if valid
		 */
		validateField: function() {
			// DEBUG: Log what 'this' actually is
			console.log('=== validateField DEBUG ===');
			console.log('typeof this:', typeof this);
			console.log('this:', this);
			console.log('this === FanficWizard:', this === FanficWizard);
			if (this.constructor) {
				console.log('this.constructor.name:', this.constructor.name);
			}
			var $field = $(this);
			console.log('$field:', $field);
			console.log('$field.length:', $field.length);
			if ($field[0]) {
				console.log('$field[0]:', $field[0]);
				console.log('$field[0].tagName:', $field[0].tagName);
			}
			console.log('$field.val():', $field.val());
			console.log('=== END DEBUG ===');

			var value = $field.val() ? $field.val().trim() : '';
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
		}
	};

	// Make it accessible for debugging
	window.FanficWizard = FanficWizard;

})(jQuery);
