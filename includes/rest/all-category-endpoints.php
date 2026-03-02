<?php
/**
 * MEDBEAFGALLERY Gallery - "All" Category REST API Endpoints
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET handler for the "All" category image
 */
function medbeafgallery_rest_get_all_category_image() {
    $image_id = get_option('medbeafgallery_all_category_image', '');
    $image_url = '';

    if (!empty($image_id)) {
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
    }

    // If no custom image is set or it's not valid, use SVG placeholder
    if (empty($image_url)) {
        $image_url = medbeafgallery_generate_all_category_svg();
    }

    return array(
        'success' => true,
        'image_id' => $image_id,
        'image_url' => $image_url
    );
}

/**
 * POST handler for updating the "All" category image
 */
function medbeafgallery_rest_update_all_category_image($request) {
    $image_id = $request->get_param('image_id');

    if (empty($image_id)) {
        return array(
            'success' => false,
            'message' => __('No image ID provided', 'medical-before-after-gallery')
        );
    }

    // Ensure the attachment exists
    $attachment = get_post($image_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        return array(
            'success' => false,
            'message' => __('Invalid attachment ID', 'medical-before-after-gallery')
        );
    }

    // Update the option
    update_option('medbeafgallery_all_category_image', $image_id);

    return array(
        'success' => true,
        'image_id' => $image_id,
        'image_url' => wp_get_attachment_image_url($image_id, 'thumbnail')
    );
}

/**
 * DELETE handler for removing the "All" category image
 */
function medbeafgallery_rest_delete_all_category_image() {
    // Delete the option
    delete_option('medbeafgallery_all_category_image');

    return array(
        'success' => true,
        'message' => __('Image removed successfully', 'medical-before-after-gallery')
    );
}