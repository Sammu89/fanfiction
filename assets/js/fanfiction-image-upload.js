/**
 * Fanfiction Manager - WordPress Media Library Integration with Dropzone UI
 *
 * Provides a reusable interface for image uploads using WordPress's native
 * media library modal with an intuitive dropzone interface.
 *
 * @package FanfictionManager
 * @since 1.2.0 (Phase 7)
 */

(function ($) {
    'use strict';

    /**
     * Initialize WordPress Media Library integration
     *
     * @param {string} dropzoneSelector - CSS selector for the dropzone element
     * @param {string} targetSelector - CSS selector for the URL input field
     * @param {Object} options - Configuration options
     * @returns {Object} - Public API for the uploader
     */
    window.FanficMediaUploader = function(dropzoneSelector, targetSelector, options) {
        var self = this;

        // Default options
        var defaults = {
            title: 'Select or Upload Image',
            buttonText: 'Use This Image',
            multiple: false,
            library: {
                type: 'image'
            },
            onSelect: null, // Callback when image is selected
            onError: null   // Callback for errors
        };

        // Merge options
        this.options = $.extend({}, defaults, options || {});
        this.$dropzone = $(dropzoneSelector);
        this.$target = $(targetSelector);
        this.mediaFrame = null;

        // Validate elements
        if (this.$dropzone.length === 0) {
            console.error('FanficMediaUploader: Dropzone element not found:', dropzoneSelector);
            return null;
        }

        if (this.$target.length === 0) {
            console.error('FanficMediaUploader: Target input not found:', targetSelector);
            return null;
        }

        /**
         * Initialize the media frame
         */
        this.initMediaFrame = function() {
            // If the media frame already exists, reopen it
            if (self.mediaFrame) {
                self.mediaFrame.open();
                return;
            }

            // Create the media frame
            self.mediaFrame = wp.media({
                title: self.options.title,
                button: {
                    text: self.options.buttonText
                },
                multiple: self.options.multiple,
                library: self.options.library
            });

            // When an image is selected, run a callback
            self.mediaFrame.on('select', function() {
                var attachment = self.mediaFrame.state().get('selection').first().toJSON();
                self.handleImageSelect(attachment);
            });

            // Open the modal
            self.mediaFrame.open();
        };

        /**
         * Handle image selection from media library
         *
         * @param {Object} attachment - The selected attachment object
         */
        this.handleImageSelect = function(attachment) {
            if (!attachment || !attachment.url) {
                self.handleError('Invalid image selection');
                return;
            }

            // Populate the target URL field
            self.$target.val(attachment.url).trigger('change');

            // Update dropzone preview if it exists
            self.updatePreview(attachment.url);

            // Remove error state
            self.$dropzone.removeClass('fanfic-dropzone-error');

            // Execute custom callback if provided
            if (typeof self.options.onSelect === 'function') {
                self.options.onSelect(attachment);
            }
        };

        /**
         * Update dropzone preview image
         *
         * @param {string} imageUrl - URL of the image to preview
         */
        this.updatePreview = function(imageUrl) {
            var $preview = self.$dropzone.find('.fanfic-dropzone-preview');
            var $placeholder = self.$dropzone.find('.fanfic-dropzone-placeholder');

            if ($preview.length === 0) {
                // Create preview element if it doesn't exist
                $preview = $('<div class="fanfic-dropzone-preview"><img src="" alt="Preview" /><button type="button" class="fanfic-dropzone-remove" aria-label="Remove image">&times;</button></div>');
                self.$dropzone.append($preview);

                // Add remove button handler
                $preview.find('.fanfic-dropzone-remove').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.clearImage();
                });
            }

            $preview.find('img').attr('src', imageUrl);
            $preview.show();
            $placeholder.hide();
        };

        /**
         * Clear the selected image
         */
        this.clearImage = function() {
            self.$target.val('').trigger('change');

            var $preview = self.$dropzone.find('.fanfic-dropzone-preview');
            var $placeholder = self.$dropzone.find('.fanfic-dropzone-placeholder');

            $preview.hide();
            $placeholder.show();
        };

        /**
         * Handle errors
         *
         * @param {string} message - Error message
         */
        this.handleError = function(message) {
            self.$dropzone.addClass('fanfic-dropzone-error');

            if (typeof self.options.onError === 'function') {
                self.options.onError(message);
            } else {
                console.error('FanficMediaUploader:', message);
            }
        };

        /**
         * Handle drag over event
         */
        this.handleDragOver = function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.$dropzone.addClass('fanfic-dropzone-dragover');
        };

        /**
         * Handle drag leave event
         */
        this.handleDragLeave = function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.$dropzone.removeClass('fanfic-dropzone-dragover');
        };

        /**
         * Handle drop event - upload the dropped file directly
         */
        this.handleDrop = function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.$dropzone.removeClass('fanfic-dropzone-dragover');

            // Get the dropped files
            var files = e.originalEvent.dataTransfer.files;

            if (files.length === 0) {
                self.handleError('No files were dropped');
                return;
            }

            // Take only the first file if multiple were dropped
            var file = files[0];

            // Validate file type
            if (!file.type.match('image.*')) {
                self.handleError('Please drop an image file');
                return;
            }

            // Upload the file
            self.uploadFile(file);
        };

        /**
         * Upload a file directly to WordPress media library
         *
         * @param {File} file - The file to upload
         */
        this.uploadFile = function(file) {
            // Show uploading state
            self.$dropzone.addClass('fanfic-dropzone-uploading');
            var $placeholder = self.$dropzone.find('.fanfic-dropzone-placeholder');
            var originalText = $placeholder.text();
            $placeholder.text('Uploading...');

            // Create FormData object
            var formData = new FormData();
            formData.append('file', file);
            formData.append('title', file.name.replace(/\.[^/.]+$/, '')); // Filename without extension

            // Get REST API URL and nonce from localized script data
            var restUrl = typeof fanficUploader !== 'undefined' && fanficUploader.restUrl
                ? fanficUploader.restUrl + 'wp/v2/media'
                : '/wp-json/wp/v2/media';

            var nonce = '';

            // Try to get nonce from our localized data first
            if (typeof fanficUploader !== 'undefined' && fanficUploader.restNonce) {
                nonce = fanficUploader.restNonce;
            }
            // Fallback to wpApiSettings (WordPress core)
            else if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
                nonce = wpApiSettings.nonce;
            }
            // Another fallback to wp.api.settings
            else if (typeof wp !== 'undefined' && typeof wp.api !== 'undefined' && wp.api.settings && wp.api.settings.nonce) {
                nonce = wp.api.settings.nonce;
            }

            // Upload via REST API
            $.ajax({
                url: restUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: nonce ? {
                    'X-WP-Nonce': nonce
                } : {},
                success: function(response) {
                    // Handle successful upload
                    if (response && response.source_url) {
                        self.handleImageSelect({
                            url: response.source_url,
                            id: response.id,
                            title: response.title.rendered || ''
                        });
                    } else {
                        self.handleError('Upload successful but no URL returned');
                    }

                    // Remove uploading state
                    self.$dropzone.removeClass('fanfic-dropzone-uploading');
                    $placeholder.text(originalText);
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Upload failed';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 401 || xhr.status === 403) {
                        errorMessage = 'You do not have permission to upload files';
                    } else if (error) {
                        errorMessage = 'Upload failed: ' + error;
                    }

                    self.handleError(errorMessage);

                    // Remove uploading state
                    self.$dropzone.removeClass('fanfic-dropzone-uploading');
                    $placeholder.text(originalText);
                }
            });
        };

        /**
         * Bind all event handlers
         */
        this.bindEvents = function() {
            // Click to open media library
            self.$dropzone.on('click', function(e) {
                e.preventDefault();
                self.initMediaFrame();
            });

            // Drag and drop events
            self.$dropzone.on('dragover', self.handleDragOver);
            self.$dropzone.on('dragleave', self.handleDragLeave);
            self.$dropzone.on('drop', self.handleDrop);

            // Prevent default drag behavior on document
            $(document).on('dragover drop', function(e) {
                if ($(e.target).closest(dropzoneSelector).length === 0) {
                    e.preventDefault();
                }
            });
        };

        /**
         * Initialize the uploader
         */
        this.init = function() {
            // Check if WordPress media library is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.error('FanficMediaUploader: WordPress media library not available');
                return;
            }

            // Bind events
            self.bindEvents();

            // Initialize preview if URL already exists
            var existingUrl = self.$target.val();
            if (existingUrl) {
                self.updatePreview(existingUrl);
            }
        };

        // Auto-initialize
        this.init();

        // Return public API
        return {
            open: self.initMediaFrame,
            clear: self.clearImage,
            destroy: function() {
                self.$dropzone.off('click dragover dragleave drop');
                if (self.mediaFrame) {
                    self.mediaFrame.off('select');
                }
            }
        };
    };

    /**
     * jQuery plugin wrapper for easier initialization
     */
    $.fn.fanficMediaUploader = function(targetSelector, options) {
        return this.each(function() {
            var $this = $(this);
            var uploader = new window.FanficMediaUploader('#' + $this.attr('id'), targetSelector, options);
            $this.data('fanficMediaUploader', uploader);
        });
    };

    /**
     * Auto-initialize all dropzones on page load
     */
    $(function() {
        // Auto-initialize dropzones with data attributes
        $('.fanfic-image-dropzone').each(function() {
            var $dropzone = $(this);
            var targetSelector = $dropzone.data('target');
            var title = $dropzone.data('title') || 'Select or Upload Image';

            if (targetSelector) {
                new window.FanficMediaUploader('#' + $dropzone.attr('id'), targetSelector, {
                    title: title,
                    buttonText: 'Use This Image'
                });
            }
        });
    });

})(jQuery);
