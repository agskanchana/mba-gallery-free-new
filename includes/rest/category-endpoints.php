<?php
/**
 * MEDBEAFGALLERY Gallery - Category REST API Endpoints
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET handler for categories with images
 *
 * IMPORTANT: This endpoint enforces a hard category limit for the free version.
 * The limit is applied directly at the data layer, independent of any admin-side
 * limit functions, to prevent bypass through code modification.
 */
function medbeafgallery_rest_get_categories() {
    // ── Hard-coded free-version ceiling ──
    // Intentionally NOT derived from medbeafgallery_get_max_categories().
    $free_hard_limit = 4;

    $has_pro = apply_filters('medbeafgallery_has_valid_pro_license', false);
    $effective_limit = $has_pro ? 0 : $free_hard_limit; // 0 = unlimited

    // Get the "All" category ID so we can exclude it from the limit count
    $all_category_id = get_option('medbeafgallery_all_category_id');

    // Get categories
    $categories = get_terms(array(
        'taxonomy' => 'medbeafgallery_category',
        'hide_empty' => true,
    ));

    $formatted_categories = array();
    $category_count = 0; // Counter for non-"All" categories

    if (!is_wp_error($categories) && !empty($categories)) {
        foreach ($categories as $category) {
            // Skip the special "All" category in this loop (added separately below)
            if ($all_category_id && $category->term_id == $all_category_id) {
                continue;
            }

            // Enforce the hard category limit
            if ($effective_limit > 0 && $category_count >= $effective_limit) {
                break;
            }

            // Get parent information if this is a child category
            $parent_slug = '';
            $parent_name = '';
            if ($category->parent !== 0) {
                $parent = get_term($category->parent, 'medbeafgallery_category');
                if ($parent && !is_wp_error($parent)) {
                    $parent_slug = $parent->slug;
                    $parent_name = $parent->name;
                }
            }

            $image_id = get_term_meta($category->term_id, 'medbeafgallery_category_image', true);
            $image_url = '';

            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
            }

            $formatted_categories[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'imageUrl' => $image_url,
                'parent' => $category->parent,
                'parent_slug' => $parent_slug,
                'parent_name' => $parent_name
            );

            $category_count++;
        }
    }

    // Always add the "All" category at the beginning
    $all_image_id = 0;
    $all_image_url = '';

    if ($all_category_id) {
        $all_image_id = get_term_meta($all_category_id, 'medbeafgallery_category_image', true);
        if ($all_image_id) {
            $all_image_url = wp_get_attachment_image_url($all_image_id, 'thumbnail');
        }
    }

    // Always use SVG placeholder with "VIEW ALL" text for the All category
    $all_image_url = medbeafgallery_generate_all_category_svg();

    array_unshift($formatted_categories, array(
        'id' => $all_category_id ? $all_category_id : 'all',
        'name' => __('All', 'medical-before-after-gallery'),
        'slug' => 'all',
        'description' => __('All categories', 'medical-before-after-gallery'),
        'count' => 0, // This will be calculated in JS
        'imageUrl' => $all_image_url,
        'isDefault' => true,
    ));

    return $formatted_categories;
}

/**
 * Get the "All" category image URL - Now always returns an SVG
 */
function medbeafgallery_get_all_category_image_url() {
    $image_id = get_option('medbeafgallery_all_category_image', '');
    if (!empty($image_id)) {
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        if ($image_url) {
            return $image_url;
        }
    }

    // Return SVG instead of default image file
    return medbeafgallery_generate_all_category_svg();
}