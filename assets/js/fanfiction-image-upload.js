(function ($) {
    'use strict';

    $(function () {
        // Use event delegation to handle the click event
        $(document).on('click', '.fanfic-ajax-upload-button', function (e) {
            e.preventDefault();

            const $button = $(this);
            const fileInputSelector = $button.data('file-input');
            const $fileInput = $(fileInputSelector);
            
            // The URL target is a data attribute on the file input itself
            const urlTargetSelector = $fileInput.data('url-target');
            const $urlTarget = $(urlTargetSelector);

            const $status = $button.next('.fanfic-upload-status');
            const nonce = $button.data('nonce');
            const context = $button.data('context');

            if ($fileInput.length === 0 || $fileInput[0].files.length === 0) {
                $status.text('Please select a file first.').css('color', 'red').fadeIn();
                return;
            }

            const file = $fileInput[0].files[0];
            // The file key for the $_FILES array is the 'name' attribute of the input
            const fileKey = $fileInput.attr('name'); 

            const formData = new FormData();
            formData.append('action', 'fanfic_ajax_image_upload');
            formData.append('nonce', nonce);
            formData.append('upload_file_key', fileKey);
            formData.append('context', context);
            formData.append(fileKey, file);

            $status.text('Uploading...').css('color', '').fadeIn();
            $button.prop('disabled', true);

            // We need the ajaxurl, which should be localized.
            // Let's assume a localized object `fanficUploader` exists.
            if (typeof fanficUploader === 'undefined' || typeof fanficUploader.ajaxUrl === 'undefined') {
                $status.text('Error: AJAX URL not configured.').css('color', 'red');
                $button.prop('disabled', false);
                return;
            }

            $.ajax({
                url: fanficUploader.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // The success message from the server is in response.data.message
                        $status.text(response.data.message).css('color', 'green');
                        // The URL is nested in response.data.data
                        $urlTarget.val(response.data.data.url).trigger('change'); // trigger change for any listeners
                        
                        // Clear the file input for cleanliness
                        $fileInput.val('');
                    } else {
                        // The error message from the server is in response.data.message
                        $status.text('Error: ' + response.data.message).css('color', 'red');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    const errorMessage = jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data.message : errorThrown;
                    $status.text('Request failed: ' + errorMessage).css('color', 'red');
                },
                complete: function () {
                    $button.prop('disabled', false);
                    // Fade out status message after a few seconds
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 5000);
                }
            });
        });
    });

})(jQuery);
