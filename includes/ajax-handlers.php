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

    // Confirm this really is a PHP upload and not an arbitrary server path.
    // wp_handle_upload() checks this too; keeping it here means we reject a
    // forged tmp_name with a clear error before touching the media APIs.
    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Rejected a non-uploaded tmp_name');
        }
        wp_send_json_error(array('message' => 'Invalid upload'));
        return;
    }

    // Get upload directory info
    $upload_dir = wp_upload_dir();

    // Generate a unique filename
    $filename = wp_unique_filename($upload_dir['path'], 'cropped-' . time() . '.jpg');

    // This is a real browser upload sitting in $_FILES, so it goes through
    // media_handle_upload(): that path uses move_uploaded_file() and re-checks
    // is_uploaded_file() itself. media_handle_sideload() is the API for files
    // already on the server — it rename()s instead, skipping both guards.
    //
    // media_handle_upload() takes the $_FILES key rather than a file array, so
    // the desired name is applied by overriding the uploaded one first;
    // wp_handle_upload() still sanitises and de-duplicates whatever we set.
    $_FILES['file']['name'] = $filename;

    // test_form is off because this arrives via admin-ajax: $_POST['action'] is
    // our own action name, not the 'wp_handle_upload' value the check expects.
    $id = media_handle_upload('file', 0, array(), array('test_form' => false));

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