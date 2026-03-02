<?php
/**
 * AJAX Handlers for Medical Before After Gallery
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle cropped image uploads via AJAX
 */
function medbeafgallery_crop_image_ajax() {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Crop image AJAX request received');
    }

    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'medbeafgallery_admin_nonce')) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Nonce verification failed');
        }
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    // Check user permissions (must be able to upload files)
    if (!current_user_can('upload_files')) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Permission check failed');
        }
        wp_send_json_error(array('message' => 'Permission denied'));
        return;
    }

    // Check if we have all required data
    if (!isset($_POST['attachment_id']) || empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Missing attachment_id or file in request');
        }
        wp_send_json_error(array('message' => 'Missing required data'));
        return;
    }

    // Get original attachment ID
    $original_id = intval($_POST['attachment_id']);
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Processing crop for attachment ID: ' . $original_id);
    }

    // Get original attachment data
    $original_attachment = get_post($original_id);
    if (!$original_attachment) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Original attachment not found');
        }
        wp_send_json_error(array('message' => 'Original attachment not found'));
        return;
    }

    // Get upload directory info
    $upload_dir = wp_upload_dir();

    // Generate a unique filename
    $filename = wp_unique_filename($upload_dir['path'], 'cropped-' . time() . '.jpg');
    $filepath = $upload_dir['path'] . '/' . $filename;

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Saving cropped image to: ' . $filepath);
    }

    // Use WordPress file handling instead of move_uploaded_file
    $file_array = array(
        'name' => $filename,
        'tmp_name' => sanitize_text_field($_FILES['file']['tmp_name'])
    );

    // Use WordPress media handling
    $id = media_handle_sideload($file_array, 0);

    if (is_wp_error($id)) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Failed to handle file upload: ' . $id->get_error_message());
        }
        wp_send_json_error(array('message' => 'Failed to save cropped image'));
        return;
    }

    $attach_id = $id;

    // Mark this as a cropped image
    update_post_meta($attach_id, '_medbeafgallery_cropped_image', '1');
    update_post_meta($attach_id, '_medbeafgallery_original_image_id', $original_id);

    // Update the title
    wp_update_post(array(
        'ID' => $attach_id,
        /* translators: %s: original image title */
        'post_title' => sprintf(__('Cropped: %s', 'medical-before-after-gallery'), $original_attachment->post_title)
    ));

    // If this is part of a case, save that reference
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ($post_id > 0) {
        update_post_meta($attach_id, '_medbeafgallery_case_id', $post_id);
    }

    // Get settings
    $settings = get_option('medbeafgallery_settings', array());

    // Image processing completed successfully

    // Get the URL of the new attachment
    $attachment_url = wp_get_attachment_url($attach_id);

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Crop operation successful, new attachment ID: ' . $attach_id);
    }
    wp_send_json_success(array(
        'id' => $attach_id,
        'url' => $attachment_url,
        'message' => 'Image successfully cropped and saved'
    ));
}
add_action('wp_ajax_medbeafgallery_crop_image', 'medbeafgallery_crop_image_ajax');