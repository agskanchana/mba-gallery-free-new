/**
 * Doctors screen: photo picker for the add and edit forms.
 */
(function ($) {
    'use strict';

    var l10n = window.medbeafgalleryDoctors || {};
    var frame = null;

    function fieldOf(el) {
        return $(el).closest('.medbeafgallery-doctor-photo-field');
    }

    function setPhoto($field, id, url) {
        $field.find('.medbeafgallery-doctor-photo-id').val(id || '');
        var $img = $field.find('.medbeafgallery-doctor-photo-preview');
        if (url) {
            $img.attr('src', url).show();
            $field.find('.medbeafgallery-doctor-photo-remove').show();
        } else {
            $img.attr('src', '').hide();
            $field.find('.medbeafgallery-doctor-photo-remove').hide();
        }
    }

    $(document).on('click', '.medbeafgallery-doctor-photo-choose', function (e) {
        e.preventDefault();
        var $field = fieldOf(this);
        if (!window.wp || !wp.media) {
            return;
        }
        frame = wp.media({
            title: l10n.frameTitle || 'Choose a photo',
            button: { text: l10n.frameButton || 'Use this photo' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var a = frame.state().get('selection').first().toJSON();
            var url = (a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url) || a.url;
            setPhoto($field, a.id, url);
        });
        frame.open();
    });

    $(document).on('click', '.medbeafgallery-doctor-photo-remove', function (e) {
        e.preventDefault();
        setPhoto(fieldOf(this), '', '');
    });

    // The "Add New Doctor" form is submitted over AJAX and WordPress only resets
    // its own fields, so clear the photo after a successful add.
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (!settings || typeof settings.data !== 'string' || settings.data.indexOf('action=add-tag') === -1) {
            return;
        }
        if (xhr && xhr.responseXML && $(xhr.responseXML).find('wp_error').length) {
            return;
        }
        $('#addtag .medbeafgallery-doctor-photo-field').each(function () {
            setPhoto($(this), '', '');
        });
    });
})(jQuery);
