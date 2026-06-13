/**
 * MEDBEAFGALLERY Gallery Admin JavaScript
 *
 * Handles all admin interactions including:
 * - Media uploads for before/after images
 * - Adding/removing image pairs
 * - Sorting image pairs
 * - Form validation
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initMainImagePair();
        initImagePairControls();
        initMediaUpload();
        initSortableImagePairs();
        handleFormValidation();
        initCopyShortcode();
    });

    /**
     * Handle image upload with cropping
     */
    function handleImageUpload(button, preview, inputField, removeButton) {
        console.log('MEDBEAFGALLERY Gallery: handleImageUpload called');

        // Create media frame
        const frame = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        // When image selected
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            console.log('MEDBEAFGALLERY Gallery: Media selected', attachment);

            // Check if cropping is enabled and the cropper module exists
            // Fix: Convert string '1' to boolean true by using == instead of === or by explicit conversion
            const croppingEnabled = typeof medbeafgalleryAdmin !== 'undefined' &&
                                   (medbeafgalleryAdmin.cropping_enabled == true ||
                                    medbeafgalleryAdmin.cropping_enabled == 1 ||
                                    medbeafgalleryAdmin.cropping_enabled == '1');

            const cropperAvailable = typeof window.medbeafgalleryImageCropper !== 'undefined';

            console.log('MEDBEAFGALLERY Gallery: Cropping enabled setting:', croppingEnabled);
            console.log('MEDBEAFGALLERY Gallery: Raw medbeafgalleryAdmin.cropping_enabled value:', medbeafgalleryAdmin ? medbeafgalleryAdmin.cropping_enabled : 'undefined');
            console.log('MEDBEAFGALLERY Gallery: Cropper available:', cropperAvailable);
            console.log('MEDBEAFGALLERY Gallery: Window object check:', typeof window.medbeafgalleryImageCropper);

            if (croppingEnabled && cropperAvailable) {
                // Use cropper
                console.log('MEDBEAFGALLERY Gallery: Using cropper for image');
                window.medbeafgalleryImageCropper.setActiveElements(button, inputField, preview);
                window.medbeafgalleryImageCropper.showCropperModal(attachment);
            } else if (croppingEnabled && !cropperAvailable) {
                // Cropping is enabled but cropper isn't ready yet, try again in a moment
                console.log('MEDBEAFGALLERY Gallery: Cropper not ready, retrying...');
                setTimeout(function() {
                    if (typeof window.medbeafgalleryImageCropper !== 'undefined') {
                        console.log('MEDBEAFGALLERY Gallery: Using cropper for image (retry successful)');
                        window.medbeafgalleryImageCropper.setActiveElements(button, inputField, preview);
                        window.medbeafgalleryImageCropper.showCropperModal(attachment);
                    } else {
                        console.log('MEDBEAFGALLERY Gallery: Cropper still not available, using direct image');
                        inputField.val(attachment.id);
                        preview.html('<img src="' + attachment.url + '" alt="Preview">');
                        removeButton.show();
                    }
                }, 100);
            } else {
                // No cropping - directly use the selected image
                console.log('MEDBEAFGALLERY Gallery: Using image directly (no cropping)');
                console.log('MEDBEAFGALLERY Gallery: Cropping enabled:', croppingEnabled, 'Cropper available:', cropperAvailable);
                inputField.val(attachment.id);
                preview.html('<img src="' + attachment.url + '" alt="Preview">');
                removeButton.show();
            }
        });

        // Open media frame
        frame.open();
    }

    /**
     * Initialize main before/after image pair
     */
    function initMainImagePair() {
        // Main Before Image Upload
        $('#medbeafgallery-upload-main-before').on('click', function() {
            const button = $(this);
            const preview = $('#medbeafgallery-main-before-preview');
            const inputField = $('#medbeafgallery-main-before-id');
            const removeButton = $('#medbeafgallery-remove-main-before');

            handleImageUpload(button, preview, inputField, removeButton);
        });

        // Main After Image Upload
        $('#medbeafgallery-upload-main-after').on('click', function() {
            const button = $(this);
            const preview = $('#medbeafgallery-main-after-preview');
            const inputField = $('#medbeafgallery-main-after-id');
            const removeButton = $('#medbeafgallery-remove-main-after');

            handleImageUpload(button, preview, inputField, removeButton);
        });

        // Remove Main Before Image
        $('#medbeafgallery-remove-main-before').on('click', function() {
            $('#medbeafgallery-main-before-id').val('');
            $('#medbeafgallery-main-before-preview').empty();
            $(this).hide();
        });

        // Remove Main After Image
        $('#medbeafgallery-remove-main-after').on('click', function() {
            $('#medbeafgallery-main-after-id').val('');
            $('#medbeafgallery-main-after-preview').empty();
            $(this).hide();
        });
    }

    /**
     * Initialize add/remove functionality for image pairs
     */
    function initImagePairControls() {
        // Add new image pair
        $('#medbeafgallery-add-image-pair').on('click', function() {
            // Get the template and replace the index placeholder
            let template = $('#medbeafgallery-image-pair-template').html();
            const newIndex = $('#medbeafgallery-additional-pairs-container .medbeafgallery-image-pair').length;
            template = template.replace(/\{\{index\}\}/g, newIndex);

            // Append the new pair
            $('#medbeafgallery-additional-pairs-container').append(template);

            // Initialize media buttons for the new pair
            initMediaButtonsForPair($('#medbeafgallery-additional-pairs-container .medbeafgallery-image-pair').last());

            // Update indices for all pairs
            updateImagePairIndices();
        });

        // Remove image pair (using event delegation for dynamically added elements)
        $(document).on('click', '.medbeafgallery-remove-pair', function() {
            if (confirm(medbeafgalleryAdmin.i18n.confirm_delete)) {
                $(this).closest('.medbeafgallery-image-pair').remove();

                // Update indices of remaining pairs for proper labeling
                updateImagePairIndices();
            }
        });

        // Remove single image (Before)
        $(document).on('click', '.medbeafgallery-remove-before-btn', function() {
            const container = $(this).closest('.medbeafgallery-image-container');
            container.find('.medbeafgallery-before-id').val('');
            container.find('.medbeafgallery-image-preview').empty();
            $(this).hide();
        });

        // Remove single image (After)
        $(document).on('click', '.medbeafgallery-remove-after-btn', function() {
            const container = $(this).closest('.medbeafgallery-image-container');
            container.find('.medbeafgallery-after-id').val('');
            container.find('.medbeafgallery-image-preview').empty();
            $(this).hide();
        });
    }

    /**
     * Initialize WordPress media uploader for before/after images
     */
    function initMediaUpload() {
        // Initialize existing pair buttons
        $('#medbeafgallery-additional-pairs-container .medbeafgallery-image-pair').each(function() {
            initMediaButtonsForPair($(this));
        });
    }

    /**
     * Initialize media buttons for a specific image pair
     */
    function initMediaButtonsForPair($pair) {
        // Before image button
        $pair.find('.medbeafgallery-upload-before-btn').on('click', function() {
            const button = $(this);
            const container = button.closest('.medbeafgallery-image-container');
            const preview = container.find('.medbeafgallery-image-preview');
            const inputField = container.find('.medbeafgallery-before-id');
            const removeButton = container.find('.medbeafgallery-remove-before-btn');

            handleImageUpload(button, preview, inputField, removeButton);
        });

        // After image button
        $pair.find('.medbeafgallery-upload-after-btn').on('click', function() {
            const button = $(this);
            const container = button.closest('.medbeafgallery-image-container');
            const preview = container.find('.medbeafgallery-image-preview');
            const inputField = container.find('.medbeafgallery-after-id');
            const removeButton = container.find('.medbeafgallery-remove-after-btn');

            handleImageUpload(button, preview, inputField, removeButton);
        });
    }

    /**
     * Make image pairs sortable
     */
    function initSortableImagePairs() {
        if ($.fn.sortable) {
            $('#medbeafgallery-additional-pairs-container').sortable({
                handle: '.medbeafgallery-drag-handle',
                items: '.medbeafgallery-image-pair',
                cursor: 'move',
                axis: 'y',
                opacity: 0.7,
                update: updateImagePairIndices
            });
        }
    }

    /**
     * Update indices of image pairs after sorting or removal
     */
    function updateImagePairIndices() {
        $('#medbeafgallery-additional-pairs-container .medbeafgallery-image-pair').each(function(index) {
            // Update data attribute
            $(this).attr('data-index', index);

            // Update header text
            const header = $(this).find('.medbeafgallery-pair-header').first();

            // Clear the header and add new content
            header.empty();
            header.append('Image Pair ' + (index + 1));
            header.append('<span class="medbeafgallery-remove-pair dashicons dashicons-trash"></span>');
            header.append('<span class="medbeafgallery-drag-handle dashicons dashicons-move"></span>');

            // Update input IDs to match the new index
            $(this).find('input[id^="medbeafgallery_gallery_pair_description_"]').attr('id', 'medbeafgallery_gallery_pair_description_' + index);
            $(this).find('.medbeafgallery-image-preview').attr('id', function() {
                return $(this).attr('id').replace(/\d+$/, index);
            });

            // Update name attributes so indices stay sequential
            $(this).find('.medbeafgallery-before-id').attr('name', 'medbeafgallery_pairs[' + index + '][before_image]');
            $(this).find('.medbeafgallery-after-id').attr('name', 'medbeafgallery_pairs[' + index + '][after_image]');
            $(this).find('.medbeafgallery-image-description input').attr('name', 'medbeafgallery_pairs[' + index + '][description]');
        });
    }

    /**
     * Form validation for case submission
     */
    function handleFormValidation() {
        $('form#post').on('submit', function(e) {
            // Check if we're on the case post type page
            if ($('#post_type').val() !== 'medbeafgallery_case') {
                return true;
            }

            // Check if at least main before/after images exist
            const mainBeforeId = $('#medbeafgallery-main-before-id').val();
            const mainAfterId = $('#medbeafgallery-main-after-id').val();

            if (!mainBeforeId || !mainAfterId) {
                e.preventDefault();
                alert('Main before and after images are required.');
                return false;
            }

            return true;
        });
    }

    /**
     * Initialize copy shortcode functionality
     */
    function initCopyShortcode() {
        $('.medbeafgallery-copy-shortcode').on('click', function() {
            const shortcode = $(this).data('shortcode');

            // Create a temporary textarea
            const textarea = document.createElement('textarea');
            textarea.value = shortcode;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);

            // Select and copy
            textarea.select();
            document.execCommand('copy');

            // Remove the textarea
            document.body.removeChild(textarea);

            // Update button text temporarily
            const originalText = $(this).text();
            $(this).text('Copied!');

            setTimeout(() => {
                $(this).text(originalText);
            }, 2000);
        });
    }

})(jQuery);



 jQuery(document).ready(function($) {
        // Initialize color picker
        if ($.fn.wpColorPicker) {
            $('#gallery_primary_color').wpColorPicker({
                defaultColor: '#3498db',
                change: function(event, ui) {
                    updateActivePreset(ui.color.toString());
                },
                clear: function() {
                    $('.medbeafgallery-preset-color').removeClass('active');
                }
            });
        }

        // Enhanced color preset handling
        function updateActivePreset(colorValue) {
            $('.medbeafgallery-preset-color').removeClass('active');
            $('.medbeafgallery-preset-color[data-color="' + colorValue + '"]').addClass('active');
        }

        // Handle color preset clicks
        $(document).on('click', '.medbeafgallery-preset-color', function(e) {
            e.preventDefault();
            var colorValue = $(this).data('color');

            // Update the input field
            $('#gallery_primary_color').val(colorValue);

            // Update WordPress color picker if it's a hex color
            if (colorValue.startsWith('#')) {
                $('#gallery_primary_color').wpColorPicker('color', colorValue);
            } else {
                // For gradients, trigger change event manually
                $('#gallery_primary_color').trigger('change');
            }

            // Update active state
            updateActivePreset(colorValue);
        });

        // Set initial active preset on page load
        $(document).ready(function() {
            var initialColor = $('#gallery_primary_color').val();
            updateActivePreset(initialColor);
        });
    });


    // Copy shortcode functionality
document.addEventListener('DOMContentLoaded', function() {
    const copyButtons = document.querySelectorAll('.medbeafgallery-copy-shortcode');

    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const shortcode = this.getAttribute('data-shortcode');

            // Create a temporary textarea to copy from
            const textarea = document.createElement('textarea');
            textarea.value = shortcode;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);

            // Select and copy
            textarea.select();
            document.execCommand('copy');

            // Remove the textarea
            document.body.removeChild(textarea);

            // Update button text temporarily
            const originalText = this.textContent;
            this.textContent = 'Copied!';

            setTimeout(() => {
                this.textContent = originalText;
            }, 2000);
        });
    });
});