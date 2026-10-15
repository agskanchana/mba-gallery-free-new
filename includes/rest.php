<?php
/**
 * Medical Before After Gallery REST API
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include REST API controllers
require_once MEDBEAFGALLERY_PATH . 'includes/rest/gallery-endpoints.php';
require_once MEDBEAFGALLERY_PATH . 'includes/rest/category-endpoints.php';
require_once MEDBEAFGALLERY_PATH . 'includes/rest/all-category-endpoints.php';

/**
 * Permission callback for the public read-only gallery endpoints.
 *
 * These endpoints serve the same data the gallery shortcode renders to anonymous
 * visitors, so they are intentionally public. This is not a license gate — the
 * free/Pro case and category ceilings are enforced inside the callbacks
 * themselves, at the data layer.
 *
 * Kept as a named function because the Pro add-on references it by name for its
 * own /subcategories route.
 *
 * @return true
 */
function medbeafgallery_rest_permission_check() {
    return true;
}

/**
 * Register REST API endpoints
 */
function medbeafgallery_register_rest_routes() {
    // Gallery data endpoint
    register_rest_route('medical-before-after-gallery/v1', '/gallery-data', array(
        'methods' => 'GET',
        'callback' => 'medbeafgallery_rest_get_gallery_data',
        'permission_callback' => 'medbeafgallery_rest_permission_check', // Public read endpoint
    ));

    // Categories endpoint
    register_rest_route('medical-before-after-gallery/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'medbeafgallery_rest_get_categories',
        'permission_callback' => 'medbeafgallery_rest_permission_check', // Public read endpoint
    ));

    // "All" category image endpoints (admin only)
    register_rest_route('medical-before-after-gallery/v1', '/all-category-image', array(
        'methods' => 'GET',
        'callback' => 'medbeafgallery_rest_get_all_category_image',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('medical-before-after-gallery/v1', '/all-category-image', array(
        'methods' => 'POST',
        'callback' => 'medbeafgallery_rest_update_all_category_image',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));

    register_rest_route('medical-before-after-gallery/v1', '/all-category-image', array(
        'methods' => 'DELETE',
        'callback' => 'medbeafgallery_rest_delete_all_category_image',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('rest_api_init', 'medbeafgallery_register_rest_routes');