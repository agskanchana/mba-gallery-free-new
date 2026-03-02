/**
 * Medical Before After Gallery Image Cropper
 * Handles image cropping with Cropper.js
 */
(function($) {
    'use strict';

    // Store references to important elements
    let activeCropper = null;
    let activeUploadBtn = null;
    let activeInputField = null;
    let activePreviewContainer = null;
    let activeImageData = null;

    // Debug function to help track issues
    function debug(message, data) {
        console.log('Medical Before After Gallery Debug: ' + message, data || '');
    }

    // Initialize when document is ready
    $(document).ready(function() {
        debug('Image cropper script initialized');

        // Check if Cropper.js is available
        if (typeof Cropper === 'undefined') {
            console.error('Medical Before After Gallery Critical Error: Cropper.js not loaded!');
            alert('Error: Cropper.js not loaded. Image cropping will not work. Please check console for more details.');
            return;
        }

        debug('Cropper.js loaded successfully');

        // Create modal for cropping
        createCropperModal();

        // Make cropper functions available globally
        window.medbeafgalleryImageCropper = {
            showCropperModal: openCropperModal,
            setActiveElements: setActiveElements
        };

        debug('Cropper initialization complete');
    });

    /**
     * Set active elements for the cropper
     */
    function setActiveElements(button, input, preview) {
        debug('Setting active elements', { button, input, preview });

        activeUploadBtn = button;
        activeInputField = input;
        activePreviewContainer = preview;
    }

    /**
     * Create cropper modal HTML
     */
    function createCropperModal() {
        debug('Creating cropper modal');

        if ($('#medbeafgallery-cropper-modal').length === 0) {
            const modalHTML = `
                <div id="medbeafgallery-cropper-modal" class="medbeafgallery-cropper-modal">
                    <div class="medbeafgallery-cropper-overlay"></div>
                    <div class="medbeafgallery-cropper-content">
                        <div class="medbeafgallery-cropper-header">
                            <h3>Crop Image</h3>
                            <button type="button" class="medbeafgallery-cropper-close">&times;</button>
                        </div>
                        <div class="medbeafgallery-cropper-body">
                            <div class="medbeafgallery-cropper-container">
                                <img id="medbeafgallery-image-to-crop" src="" alt="Image to crop">
                            </div>
                            <div class="medbeafgallery-cropper-controls">
                                <button type="button" class="button" id="medbeafgallery-rotate-left">
                                    <span class="dashicons dashicons-image-rotate-left"></span> Rotate Left
                                </button>
                                <button type="button" class="button" id="medbeafgallery-rotate-right">
                                    <span class="dashicons dashicons-image-rotate-right"></span> Rotate Right
                                </button>
                                <button type="button" class="button" id="medbeafgallery-reset-crop">
                                    <span class="dashicons dashicons-image-rotate"></span> Reset
                                </button>
                            </div>
                        </div>
                        <div class="medbeafgallery-cropper-footer">
                            <button type="button" class="button button-secondary" id="medbeafgallery-cancel-crop">Cancel</button>
                            <button type="button" class="button button-primary" id="medbeafgallery-apply-crop">Apply Crop</button>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to the page
            $('body').append(modalHTML);

            // Add event handlers
            $('#medbeafgallery-cropper-modal .medbeafgallery-cropper-close, #medbeafgallery-cancel-crop').on('click', closeCropperModal);
            $('#medbeafgallery-apply-crop').on('click', applyCrop);
            $('#medbeafgallery-rotate-left').on('click', function() {
                if (activeCropper) activeCropper.rotate(-90);
            });
            $('#medbeafgallery-rotate-right').on('click', function() {
                if (activeCropper) activeCropper.rotate(90);
            });
            $('#medbeafgallery-reset-crop').on('click', function() {
                if (activeCropper) activeCropper.reset();
            });

            debug('Cropper modal created successfully');
        } else {
            debug('Cropper modal already exists');
        }
    }

    /**
     * Open the cropper modal with the selected image
     */
    function openCropperModal(attachment) {
        debug('Opening cropper modal for', attachment);

        // Store reference to attachment
        activeImageData = attachment;

        // Get the modal and image elements
        const modal = $('#medbeafgallery-cropper-modal');
        const img = $('#medbeafgallery-image-to-crop');

        if (!modal.length || !img.length) {
            console.error('Medical Before After Gallery Error: Modal or image element not found');
            return;
        }

        // Set the image source
        img.attr('src', attachment.url);

        // Show the modal
        modal.addClass('active');

        // Initialize Cropper.js when the image is loaded
        img.off('load').on('load', function() {
            debug('Image loaded, initializing cropper');

            // Destroy previous cropper if exists
            if (activeCropper) {
                activeCropper.destroy();
                activeCropper = null;
            }

            try {
                // Get cropping size from settings, default to square
                const cropSize = medbeafgalleryAdmin.cropping_size || 800;
                debug('Using crop size: ' + cropSize + 'px');

                // Initialize Cropper with square aspect ratio
                activeCropper = new Cropper(img[0], {
                    aspectRatio: 1, // Force square aspect ratio
                    viewMode: 1,
                    autoCropArea: 0.9,
                    zoomable: true,
                    cropBoxResizable: true,
                    cropBoxMovable: true,
                    ready: function() {
                        debug('Cropper instance ready');
                    }
                });
            } catch (error) {
                console.error('Medical Before After Gallery Error: Failed to initialize Cropper.js', error);
                alert('Error initializing image cropper. Please check console for details.');
            }
        });
    }

    /**
     * Close the cropper modal
     */
    function closeCropperModal() {
        debug('Closing cropper modal');

        const modal = $('#medbeafgallery-cropper-modal');

        // Destroy cropper instance if exists
        if (activeCropper) {
            try {
                activeCropper.destroy();
            } catch (e) {
                console.error('Medical Before After Gallery: Error destroying cropper', e);
            }
            activeCropper = null;
        }

        // Hide modal
        modal.removeClass('active');
    }

    /**
     * Apply the crop and save the image
     */
    function applyCrop() {
        console.log('Medical Before After Gallery: Applying crop');

        if (!activeCropper || !activeImageData) {
            console.error('Medical Before After Gallery: Missing cropper or image data');
            return;
        }

        // Show loading state
        const applyButton = $('#medbeafgallery-apply-crop');
        applyButton.prop('disabled', true).text('Processing...');

        try {
            // Get cropped canvas
            const cropSize = medbeafgalleryAdmin && medbeafgalleryAdmin.cropping_size ? medbeafgalleryAdmin.cropping_size : 800;
            debug('Creating cropped canvas with size: ' + cropSize);

            const canvas = activeCropper.getCroppedCanvas({
                width: cropSize,
                height: cropSize,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            if (!canvas) {
                console.error('Medical Before After Gallery Error: Failed to get cropped canvas');
                applyButton.prop('disabled', false).text('Apply Crop');
                return;
            }

            // Convert canvas to blob
            canvas.toBlob(function(blob) {
                debug('Converting cropped image to blob');

                // Create form data for AJAX upload
                const formData = new FormData();
                formData.append('action', 'medbeafgallery_crop_image');
                formData.append('nonce', medbeafgalleryAdmin.nonce);
                formData.append('attachment_id', activeImageData.id);
                formData.append('file', blob, 'cropped-image.jpg');

                // Get current post ID if available
                const postId = $('#post_ID').val();
                if (postId) {
                    formData.append('post_id', postId);
                }

                debug('Sending cropped image to server');

                // Send AJAX request
                $.ajax({
                    url: medbeafgalleryAdmin.ajax_url,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        debug('Server response', response);

                        if (response.success && response.data) {
                            // Update input field with new image ID
                            activeInputField.val(response.data.id);

                            // Update preview with new image URL
                            activePreviewContainer.html('<img src="' + response.data.url + '" alt="Preview">');

                            // Show remove button if exists
                            const removeBtn = activeUploadBtn.siblings('.medbeafgallery-remove-main-before, .medbeafgallery-remove-main-after, .medbeafgallery-remove-before-btn, .medbeafgallery-remove-after-btn');
                            if (removeBtn.length) {
                                removeBtn.show();
                            }

                            // If watermarking is enabled, show a message
                            if (medbeafgalleryAdmin.watermark_enabled) {
                                debug('Watermarking is enabled, watermark will be applied to the cropped image');
                            }

                            // Close modal and reset button
                            closeCropperModal();
                            applyButton.prop('disabled', false).text('Apply Crop');
                        } else {
                            console.error('Medical Before After Gallery Error: Cropping failed', response);
                            alert('Failed to crop image: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                            applyButton.prop('disabled', false).text('Apply Crop');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Medical Before After Gallery AJAX Error:', xhr.responseText);
                        alert('Error uploading cropped image: ' + error);
                        applyButton.prop('disabled', false).text('Apply Crop');
                    }
                });
            }, 'image/jpeg', 0.95);
        } catch (error) {
            console.error('Medical Before After Gallery Error:', error);
            alert('Error processing image: ' + error.message);
            applyButton.prop('disabled', false).text('Apply Crop');
        }
    }
})(jQuery);