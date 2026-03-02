<?php
/**
 * Utility functions for Medical Before After Gallery
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if user has reached case limit
 */
function medbeafgallery_check_case_limit() {
    $max_cases = medbeafgallery_get_max_cases();

    if ($max_cases === -1) {
        return true; // Unlimited
    }

    $current_cases = wp_count_posts('medbeafgallery_case');
    $total_cases = $current_cases->publish + $current_cases->draft + $current_cases->private;

    return $total_cases < $max_cases;
}

/**
 * Get case limit notice
 */
function medbeafgallery_get_case_limit_notice() {
    $max_cases = medbeafgallery_get_max_cases();

    if ($max_cases === -1) {
        return '';
    }

    $current_cases = wp_count_posts('medbeafgallery_case');
    $total_cases = $current_cases->publish + $current_cases->draft + $current_cases->private;

    if ($total_cases >= $max_cases) {
        return sprintf(
            /* translators: %d: maximum number of cases allowed */
            __('You have reached the maximum limit of %d cases. Please upgrade to the Pro version to add unlimited cases.', 'medical-before-after-gallery'),
            $max_cases
        );
    }

    return sprintf(
        /* translators: %1$d: current number of cases, %2$d: maximum number of cases allowed */
        __('Cases: %1$d/%2$d', 'medical-before-after-gallery'),
        $total_cases,
        $max_cases
    );
}

/**
 * Check if user has reached category limit
 */
function medbeafgallery_check_category_limit() {
    $max_categories = medbeafgallery_get_max_categories();

    if ($max_categories === -1) {
        return true; // Unlimited
    }

    // Count categories excluding the special 'All' category
    $all_category_id = get_option('medbeafgallery_all_category_id');
    $args = array(
        'taxonomy'   => 'medbeafgallery_category',
        'hide_empty' => false,
    );
    if ($all_category_id) {
        $args['exclude'] = array($all_category_id);
    }
    $categories = get_terms($args);
    $total_categories = is_wp_error($categories) ? 0 : count($categories);

    return $total_categories < $max_categories;
}

/**
 * Get current category count (excluding 'All')
 */
function medbeafgallery_get_category_count() {
    $all_category_id = get_option('medbeafgallery_all_category_id');
    $args = array(
        'taxonomy'   => 'medbeafgallery_category',
        'hide_empty' => false,
    );
    if ($all_category_id) {
        $args['exclude'] = array($all_category_id);
    }
    $categories = get_terms($args);
    return is_wp_error($categories) ? 0 : count($categories);
}

/**
 * Get category limit notice
 */
function medbeafgallery_get_category_limit_notice() {
    $max_categories = medbeafgallery_get_max_categories();

    if ($max_categories === -1) {
        return '';
    }

    $total_categories = medbeafgallery_get_category_count();

    if ($total_categories >= $max_categories) {
        return sprintf(
            /* translators: %d: maximum number of categories allowed */
            __('You have reached the maximum limit of %d categories. Please upgrade to the Pro version to add unlimited categories.', 'medical-before-after-gallery'),
            $max_categories
        );
    }

    return sprintf(
        /* translators: %1$d: current number of categories, %2$d: maximum number of categories allowed */
        __('Categories: %1$d/%2$d', 'medical-before-after-gallery'),
        $total_categories,
        $max_categories
    );
}

/**
 * Add 'All' category to the categories array
 *
 * @param array $categories The categories array
 * @return array Modified categories array with 'All' as the first item
 */
function medbeafgallery_add_all_category($categories) {
    $all_category = array(
        'term_id' => 'all',
        'name' => __('All', 'medical-before-after-gallery'),
        'slug' => 'all',
        'image_url' => '', // Removed default image reference
    );

    // Add the "All" category to the beginning of the array
    array_unshift($categories, $all_category);

    return $categories;
}

/**
 * Get all categories with images for the carousel
 *
 * @return array Categories array with image URLs
 */
function medbeafgallery_get_categories_with_images() {
    // Get the terms with count information — exclude categories with no published posts
    $categories = get_terms(array(
        'taxonomy'   => 'medbeafgallery_category',
        'hide_empty' => true,
    ));

    if (is_wp_error($categories) || empty($categories)) {
        $categories = array();
    }

    // Calculate total count for the "All" category
    $total_count = 0;

    // Format the categories array to include images
    $formatted_categories = array();
    foreach ($categories as $category) {
        $total_count += $category->count;

        // Get the category image properly
        $category_image_url = medbeafgallery_get_category_image_url($category->term_id);

        $formatted_categories[] = array(
            'term_id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'count' => $category->count,
            'image_url' => $category_image_url
        );
    }

    // Add the "All" category at the beginning
    $all_category_id = get_option('medbeafgallery_all_category_id');
    $all_category_image = '';

    if ($all_category_id) {
        $all_image_id = get_term_meta($all_category_id, 'medbeafgallery_category_image', true);
        if ($all_image_id) {
            $all_category_image = wp_get_attachment_image_url($all_image_id, 'thumbnail');
        }
    }

    // Add the "All" category
    $all_category = array(
        'term_id' => $all_category_id ? $all_category_id : 'all',
        'name' => __('All', 'medical-before-after-gallery'),
        'slug' => 'all',
        'count' => $total_count,
        'image_url' => $all_category_image, // Use the retrieved image
    );

    array_unshift($formatted_categories, $all_category);

    return $formatted_categories;
}

/**
 * Get category image URL
 *
 * @param int $term_id The term ID
 * @param string $size The image size (default: thumbnail)
 * @return string The image URL or empty string if no image
 */
function medbeafgallery_get_category_image_url($term_id, $size = 'thumbnail') {
    $image_id = get_term_meta($term_id, 'medbeafgallery_category_image', true);

    if ($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, $size);
        if ($image_url) {
            return $image_url;
        }
    }

    // Return empty string instead of default image
    return '';
}

/**
 * Create a default image in the media library
 *
 * @param string $filename The filename in the plugin's assets/images directory
 * @param string $title The title for the image in the media library
 * @return int|false The attachment ID if successful, false otherwise
 */

/**
 * Medical Before After Gallery Settings Management Class
 */
class MedBeAfGallerySettings {
    private static $defaults = array(
        'consultation_enabled' => true,
        'consultation_url' => '',
        'consultation_text' => 'Schedule a Consultation',
        'cropping_enabled' => true,
        'cropping_size' => 800,
        'gallery_primary_color' => '#2563eb'
    );

    /**
     * Get settings with defaults
     *
     * @param string|null $key Specific setting key or null for all settings
     * @return mixed Settings array or specific setting value
     */
    public static function get($key = null) {
        $settings = get_option('medbeafgallery_settings', array());
        $settings = array_merge(self::$defaults, $settings);

        return $key ? ($settings[$key] ?? self::$defaults[$key] ?? null) : $settings;
    }

    /**
     * Update a setting
     *
     * @param string|array $key Setting key or array of settings
     * @param mixed $value Setting value (ignored if $key is array)
     * @return bool True on success
     */
    public static function update($key, $value = null) {
        $settings = self::get();

        if (is_array($key)) {
            $settings = array_merge($settings, $key);
        } else {
            $settings[$key] = $value;
        }

        return update_option('medbeafgallery_settings', $settings);
    }

    /**
     * Get default values
     *
     * @return array Default settings
     */
    public static function getDefaults() {
        return self::$defaults;
    }
}

/**
 * Legacy function for backward compatibility
 * Get plugin settings with defaults
 *
 * @return array Settings array with default values for missing options
 */
function medbeafgallery_get_settings() {
    return MedBeAfGallerySettings::get();
}





















/**
 * Check if required image processing libraries are available
 *
 * @return array Status of image libraries with availability
 */
function medbeafgallery_check_image_libraries() {
    $gd_available = extension_loaded('gd') && function_exists('gd_info');
    $imagick_available = extension_loaded('imagick') && class_exists('Imagick');

    $result = array(
        'gd_available' => $gd_available,
        'imagick_available' => $imagick_available,
        'has_required_library' => $gd_available || $imagick_available,
        'preferred_library' => $imagick_available ? 'imagick' : ($gd_available ? 'gd' : 'none'),
        'available_processors' => array(),
        'message' => ''
    );

    // Build available processors list
    if ($imagick_available) {
        $result['available_processors'][] = 'ImageMagick';
    }
    if ($gd_available) {
        $result['available_processors'][] = 'GD Library';
    }

    // Prepare message based on availability
    if ($result['imagick_available'] && $result['gd_available']) {
        $result['message'] = __('Both ImageMagick and GD libraries are available.', 'medical-before-after-gallery');
    } elseif ($result['imagick_available']) {
        $result['message'] = __('ImageMagick library is available.', 'medical-before-after-gallery');
    } elseif ($result['gd_available']) {
        $result['message'] = __('GD library is available.', 'medical-before-after-gallery');
    } else {
        $result['message'] = __('Warning: Neither ImageMagick nor GD libraries are available.', 'medical-before-after-gallery');
    }

    return $result;
}









/**
 * Get the appropriate image to display based on settings
 *
 * @param int $attachment_id The attachment ID
 * @param string $size The image size
 * @return string The image URL to display
 */
function medbeafgallery_get_display_image_url($attachment_id, $size = 'full') {
    // Allow Pro add-on to substitute watermarked URL
    return apply_filters('medbeafgallery_display_image_url', wp_get_attachment_image_url($attachment_id, $size), $attachment_id, $size);
}

/**
 * Get the appropriate image HTML to display based on settings
 *
 * @param int $attachment_id The attachment ID
 * @param string $size The image size
 * @param array $attr Additional attributes
 * @return string The image HTML
 */
function medbeafgallery_get_display_image($attachment_id, $size = 'full', $attr = array()) {
    // Always return the original image
    return wp_get_attachment_image($attachment_id, $size, false, $attr);
}

/**
 * Output custom CSS based on gallery color settings
 */
function medbeafgallery_output_custom_colors() {
    $settings = get_option('medbeafgallery_settings', array());

    // Fallback color if not set
    $fallback_color = '#3b82f6';
    $primary_color = !empty($settings['gallery_primary_color']) ? sanitize_text_field($settings['gallery_primary_color']) : $fallback_color;

    // Check if it's a gradient or solid color
    $is_gradient = (strpos($primary_color, 'gradient') !== false);

    // For gradients, we also need a solid color fallback for borders, text, etc.
    $primary_solid = $primary_color;
    if ($is_gradient) {
        // Extract first color from gradient for solid color uses
        preg_match('/#[0-9A-Fa-f]{3,6}/', $primary_color, $matches);
        $primary_solid = isset($matches[0]) ? $matches[0] : $fallback_color;
    } else {
        // If it's not a gradient, ensure it's a valid hex color
        $primary_solid = sanitize_hex_color($primary_color) ?: $fallback_color;
        $primary_color = $primary_solid;
    }

    // Only output if we have valid colors
    if (empty($primary_solid)) {
        $primary_solid = $fallback_color;
        $primary_color = $fallback_color;
    }

    // Helper function to create rgba from hex
    $hex_to_rgba = function($hex, $alpha = 1) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    };

    // Build CSS string instead of direct output
    $custom_css = '
        /* Medical Before After Gallery Custom Colors - Comprehensive Coverage */
        :root {
            --medbeafgallery-primary: ' . esc_attr($primary_color) . ';
            --medbeafgallery-primary-solid: ' . esc_attr($primary_solid) . ';
            --medbeafgallery-primary-rgb: ' . esc_attr($hex_to_rgba($primary_solid, 1)) . ';
            --medbeafgallery-primary-10: ' . esc_attr($hex_to_rgba($primary_solid, 0.1)) . ';
            --medbeafgallery-primary-20: ' . esc_attr($hex_to_rgba($primary_solid, 0.2)) . ';
            --medbeafgallery-primary-50: ' . esc_attr($hex_to_rgba($primary_solid, 0.5)) . ';
        }

        /* Navigation Buttons - Increased specificity */
        body .medbeafgallery-nav-btn:hover,
        .medbeafgallery-nav-btn:hover {
            color: var(--medbeafgallery-primary-solid) !important;
        }

        /* Category Items - Increased specificity */
        body .medbeafgallery-carousel-item.active::after,
        .medbeafgallery-carousel-item.active::after {
            background: var(--medbeafgallery-primary) !important;
        }

        body .medbeafgallery-carousel-item:hover img,
        body .medbeafgallery-carousel-item.active img,
        .medbeafgallery-carousel-item:hover img,
        .medbeafgallery-carousel-item.active img {
            border-color: var(--medbeafgallery-primary-solid) !important;
        }

        body .medbeafgallery-carousel-item.active p,
        body .medbeafgallery-carousel-item.active .medbeafgallery-category-name,
        .medbeafgallery-carousel-item.active p,
        .medbeafgallery-carousel-item.active .medbeafgallery-category-name {
            color: var(--medbeafgallery-primary-solid) !important;
        }

        /* Gallery Items */
        .medbeafgallery-gallery-view-btn:hover {
            background: var(--medbeafgallery-primary) !important;
        }

        .medbeafgallery-gallery-title {
            color: var(--medbeafgallery-primary-solid) !important;
        }

        /* Loading and Action Buttons */
        .medbeafgallery-load-more {
            background: var(--medbeafgallery-primary) !important;
        }

        /* Slider Components */
        .medbeafgallery-slider-button {
            background: var(--medbeafgallery-primary) !important;
        }

        /* Modal Elements */
        .medbeafgallery-control-btn.active {
            background: var(--medbeafgallery-primary) !important;
        }

        .medbeafgallery-modal-prev:hover,
        .medbeafgallery-modal-next:hover {
            color: var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-modal-counter .medbeafgallery-counter-current {
            color: var(--medbeafgallery-primary-solid) !important;
        }

        /* Tabs */
        .medbeafgallery-tab.active,
        .medbeafgallery-tab:hover {
            color: var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-tab.active::after {
            background: var(--medbeafgallery-primary-solid) !important;
        }

        /* CTA Buttons */
        .medbeafgallery-modal-nav .medbeafgallery-cta-button,
        .medbeafgallery-accept-warning-btn {
            background: var(--medbeafgallery-primary) !important;
        }

        /* Filter Elements */
        .medbeafgallery-filter-label input[type="checkbox"]:checked {
            background-color: var(--medbeafgallery-primary-solid) !important;
            border-color: var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-apply-filters-btn {
            background-color: var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-apply-filters-btn:hover {
            background-color: var(--medbeafgallery-primary-solid) !important;
            filter: brightness(0.9);
        }

        /* Filter Tags */
        .medbeafgallery-filter-tag {
            background: var(--medbeafgallery-primary-10) !important;
            color: var(--medbeafgallery-primary-solid) !important;
            border-color: var(--medbeafgallery-primary-20) !important;
        }

        .medbeafgallery-filter-tag:hover {
            background: var(--medbeafgallery-primary-20) !important;
        }

        /* Focus States */
        .medbeafgallery-carousel-item:focus,
        .medbeafgallery-gallery-item:focus-visible,
        .medbeafgallery-tab:focus-visible,
        .medbeafgallery-modal-prev:focus-visible,
        .medbeafgallery-modal-next:focus-visible,
        .medbeafgallery-control-btn:focus-visible {
            outline: 2px solid var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-carousel-item:focus {
            border-color: var(--medbeafgallery-primary-solid) !important;
            box-shadow: 0 0 0 3px var(--medbeafgallery-primary-10) !important;
        }

        /* Category Count Badge */
        .medbeafgallery-category-count {
            background-color: var(--medbeafgallery-primary-10) !important;
            color: var(--medbeafgallery-primary-solid) !important;
        }

        /* New Badge */
        .medbeafgallery-new-badge {
            background: var(--medbeafgallery-primary-solid) !important;
            box-shadow: 0 2px 5px var(--medbeafgallery-primary-20) !important;
        }

        /* Loading Spinner */
        .medbeafgallery-loading-spinner circle {
            stroke: var(--medbeafgallery-primary-solid) !important;
        }

        /* Gallery Item Hover Effects with Primary Color */
        .medbeafgallery-gallery-item[data-category="medbeafgallery_category_face"] .medbeafgallery-gallery-category,
        .medbeafgallery-gallery-item[data-category="medbeafgallery_category_nose"] .medbeafgallery-gallery-category,
        .medbeafgallery-gallery-item[data-category="medbeafgallery_category_breast"] .medbeafgallery-gallery-category,
        .medbeafgallery-gallery-item[data-category="medbeafgallery_category_body"] .medbeafgallery-gallery-category {
            background: var(--medbeafgallery-primary-10) !important;
            color: var(--medbeafgallery-primary-solid) !important;
        }

        /* Social Share Buttons */
        .medbeafgallery-share-btn:hover {
            background: var(--medbeafgallery-primary-solid) !important;
            border-color: var(--medbeafgallery-primary-solid) !important;
            box-shadow: 0 2px 5px var(--medbeafgallery-primary-20) !important;
        }

        /* Active Filters */
        .medbeafgallery-active-filters {
            border-color: var(--medbeafgallery-primary-10) !important;
        }

        /* Reset Button with Primary Theme */
        .medbeafgallery-reset-filters-btn {
            background-color: var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-reset-filters-btn:hover {
            background-color: var(--medbeafgallery-primary-solid) !important;
            filter: brightness(0.9);
        }

        /* Clear Filters Button */
        .medbeafgallery-clear-filters {
            background: var(--medbeafgallery-primary-10) !important;
            color: var(--medbeafgallery-primary-solid) !important;
            border-color: var(--medbeafgallery-primary-20) !important;
        }

        .medbeafgallery-clear-filters:hover {
            background: var(--medbeafgallery-primary-20) !important;
            color: var(--medbeafgallery-primary-solid) !important;
            border-color: var(--medbeafgallery-primary-30) !important;
        }

        /* Image Pair Navigation */
        .medbeafgallery-pair-nav:hover {
            background: var(--medbeafgallery-primary-50) !important;
        }

        .medbeafgallery-pair-indicator.active {
            background: var(--medbeafgallery-primary-solid) !important;
        }

        /* Toggle Filters Button */
        .medbeafgallery-toggle-filters-btn.active {
            background-color: var(--medbeafgallery-primary-10) !important;
            border-color: var(--medbeafgallery-primary-solid) !important;
        }

        .medbeafgallery-toggle-filters-btn:hover {
            background-color: var(--medbeafgallery-primary-10) !important;
        }';

    // Add gradient-specific CSS if needed
    if ($is_gradient) {
        $custom_css .= '
        /* Ensure gradients work properly */
        .medbeafgallery-gallery-view-btn:hover,
        .medbeafgallery-load-more,
        .medbeafgallery-slider-button,
        .medbeafgallery-modal-nav .medbeafgallery-cta-button,
        .medbeafgallery-accept-warning-btn,
        .medbeafgallery-apply-filters-btn,
        .medbeafgallery-reset-filters-btn,
        .medbeafgallery-clear-filters {
            background: var(--medbeafgallery-primary) !important;
            background-image: var(--medbeafgallery-primary) !important;
        }

        .medbeafgallery-carousel-item.active::after {
            background: var(--medbeafgallery-primary) !important;
            background-image: var(--medbeafgallery-primary) !important;
        }';
    }

    // Add inline CSS to the main gallery stylesheet - try multiple approaches for theme compatibility
    if (wp_style_is('medbeafgallery-css', 'enqueued') || wp_style_is('medbeafgallery-css', 'done')) {
        wp_add_inline_style('medbeafgallery-css', $custom_css);
    } else {
        // Fallback: Try to add to any available stylesheet
        global $wp_styles;
        if (isset($wp_styles->registered['medbeafgallery-css'])) {
            wp_add_inline_style('medbeafgallery-css', $custom_css);
        } else {
            // Last resort: Create a standalone style tag
            add_action('wp_head', function() use ($custom_css) {
                echo '<style id="medbeafgallery-custom-colors">' . $custom_css . '</style>';
            }, 100);
        }
    }
}

// Add the function to wp_enqueue_scripts AND wp_head to ensure CSS is properly enqueued
// Priority 15 ensures it runs after most theme styles are loaded
add_action('wp_enqueue_scripts', 'medbeafgallery_output_custom_colors', 15);
add_action('wp_head', 'medbeafgallery_output_custom_colors', 100);

/**
 * Fallback function to output custom CSS in footer if inline styles failed
 */
function medbeafgallery_fallback_custom_css() {
    // Check if gallery shortcode was used on this page or if we're on a page with gallery content
    global $post;
    $has_gallery = false;

    if ($post && has_shortcode($post->post_content, 'medbeafgallery')) {
        $has_gallery = true;
    }

    // Also check if any gallery elements exist on the page
    if (!$has_gallery) {
        // This runs in footer, so we can check if gallery was rendered
        $has_gallery = did_action('medbeafgallery_shortcode_rendered') > 0;
    }

    if (!$has_gallery) {
        return;
    }

    // Always output fallback styles for problematic themes
    $settings = get_option('medbeafgallery_settings', array());
    $primary_color = !empty($settings['gallery_primary_color']) ? sanitize_text_field($settings['gallery_primary_color']) : '#3b82f6';

    // Enhanced fallback CSS with higher specificity
    echo '<style id="medbeafgallery-fallback-css">
        /* Medical Before After Gallery - Enhanced Fallback Styles */
        body .medbeafgallery-nav-btn:hover,
        html body .medbeafgallery-nav-btn:hover { color: ' . esc_attr($primary_color) . ' !important; }

        body .medbeafgallery-carousel-item.active::after,
        html body .medbeafgallery-carousel-item.active::after { background: ' . esc_attr($primary_color) . ' !important; }

        body .medbeafgallery-load-more,
        html body .medbeafgallery-load-more { background: ' . esc_attr($primary_color) . ' !important; }

        body .medbeafgallery-slider-button,
        html body .medbeafgallery-slider-button { background: ' . esc_attr($primary_color) . ' !important; }

        body .medbeafgallery-control-btn.active,
        html body .medbeafgallery-control-btn.active { background: ' . esc_attr($primary_color) . ' !important; }

        body .medbeafgallery-carousel-item.active p,
        body .medbeafgallery-carousel-item.active .medbeafgallery-category-name,
        html body .medbeafgallery-carousel-item.active p,
        html body .medbeafgallery-carousel-item.active .medbeafgallery-category-name { color: ' . esc_attr($primary_color) . ' !important; }

        body .medbeafgallery-carousel-item:hover img,
        body .medbeafgallery-carousel-item.active img,
        html body .medbeafgallery-carousel-item:hover img,
        html body .medbeafgallery-carousel-item.active img { border-color: ' . esc_attr($primary_color) . ' !important; }
    </style>';
}// Add fallback to footer with high priority
add_action('wp_footer', 'medbeafgallery_fallback_custom_css', 999);

/**
 * Alternative approach: Create dynamic CSS file for themes that don't support inline styles
 */
function medbeafgallery_create_dynamic_css() {
    $settings = get_option('medbeafgallery_settings', array());
    $primary_color = !empty($settings['gallery_primary_color']) ? sanitize_text_field($settings['gallery_primary_color']) : '#3b82f6';

    // Create a hash of the current color to detect changes
    $color_hash = md5($primary_color);
    $stored_hash = get_option('medbeafgallery_color_hash', '');

    // Only regenerate if color changed
    if ($color_hash !== $stored_hash) {
        $upload_dir = wp_upload_dir();
        $css_dir = $upload_dir['basedir'] . '/medical-before-after-gallery/';
        $css_file = $css_dir . 'custom-colors.css';

        // Create directory if it doesn't exist
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }

        // Generate CSS content
        $css_content = "/* Medical Before After Gallery Custom Colors - Generated " . date('Y-m-d H:i:s') . " */
body .medbeafgallery-nav-btn:hover { color: {$primary_color} !important; }
body .medbeafgallery-carousel-item.active::after { background: {$primary_color} !important; }
body .medbeafgallery-load-more { background: {$primary_color} !important; }
body .medbeafgallery-slider-button { background: {$primary_color} !important; }
body .medbeafgallery-control-btn.active { background: {$primary_color} !important; }
body .medbeafgallery-carousel-item.active p,
body .medbeafgallery-carousel-item.active .medbeafgallery-category-name { color: {$primary_color} !important; }
body .medbeafgallery-carousel-item:hover img,
body .medbeafgallery-carousel-item.active img { border-color: {$primary_color} !important; }";

        // Write CSS file
        if (file_put_contents($css_file, $css_content) !== false) {
            update_option('medbeafgallery_color_hash', $color_hash);
            update_option('medbeafgallery_dynamic_css_url', $upload_dir['baseurl'] . '/medical-before-after-gallery/custom-colors.css');
        }
    }
}

/**
 * Enqueue dynamic CSS file if it exists
 */
function medbeafgallery_enqueue_dynamic_css() {
    $css_url = get_option('medbeafgallery_dynamic_css_url', '');
    if ($css_url && filter_var($css_url, FILTER_VALIDATE_URL)) {
        wp_enqueue_style('medbeafgallery-dynamic-css', $css_url, array('medbeafgallery-css'), MEDBEAFGALLERY_VERSION);
    }
}

// Create dynamic CSS on settings save
add_action('init', 'medbeafgallery_create_dynamic_css');
add_action('wp_enqueue_scripts', 'medbeafgallery_enqueue_dynamic_css', 20);

/**
 * Error Handler for Medical Before After Gallery
 */
class MedBeAfGalleryErrorHandler {
    const LOG_PREFIX = 'Medical Before After Gallery: ';

    /**
     * Log an error with context
     *
     * @param string $message Error message
     * @param string $context Error context
     * @param array $data Additional data to log
     */
    public static function logError($message, $context = 'general', $data = []) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = self::LOG_PREFIX . "[$context] $message";

            if (!empty($data)) {
                $log_message .= ' | Data: ' . json_encode($data);
            }

            // Use WordPress logging if available, fallback to error_log
            if (function_exists('wp_debug_log')) {
                wp_debug_log($log_message);
            } else {
                // Only call error_log if both WP_DEBUG and WP_DEBUG_LOG are true
                call_user_func('error_log', $log_message);
            }
        }
    }

    /**
     * Log a warning
     *
     * @param string $message Warning message
     * @param string $context Warning context
     */
    public static function logWarning($message, $context = 'general') {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $warning_message = self::LOG_PREFIX . "WARNING [$context] $message";

            // Use WordPress logging if available, fallback to error_log
            if (function_exists('wp_debug_log')) {
                wp_debug_log($warning_message);
            } else {
                // Only call error_log if both WP_DEBUG and WP_DEBUG_LOG are true
                call_user_func('error_log', $warning_message);
            }
        }
    }

    /**
     * Log info for debugging
     *
     * @param string $message Info message
     * @param string $context Info context
     */
    public static function logInfo($message, $context = 'general') {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $info_message = self::LOG_PREFIX . "INFO [$context] $message";

            // Use WordPress logging if available, fallback to error_log
            if (function_exists('wp_debug_log')) {
                wp_debug_log($info_message);
            } else {
                // Only call error_log if both WP_DEBUG and WP_DEBUG_LOG are true
                call_user_func('error_log', $info_message);
            }
        }
    }

    /**
     * Handle and format exceptions
     *
     * @param Exception $exception The exception
     * @param string $context Context where exception occurred
     */
    public static function handleException($exception, $context = 'general') {
        self::logError(
            $exception->getMessage(),
            $context,
            [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        );
    }
}

/**
 * Generate SVG for "All" category
 *
 * @return string Base64 encoded SVG data URL
 */
function medbeafgallery_generate_all_category_svg() {
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
      <rect width="600" height="600" fill="#1E88E5" />
      <g transform="translate(150, 150)">
        <!-- Grid Pattern -->
        <rect x="0" y="0" width="90" height="90" rx="8" fill="white" opacity="0.9" />
        <rect x="110" y="0" width="90" height="90" rx="8" fill="white" opacity="0.6" />
        <rect x="220" y="0" width="90" height="90" rx="8" fill="white" opacity="0.9" />
        <rect x="0" y="110" width="90" height="90" rx="8" fill="white" opacity="0.6" />
        <rect x="110" y="110" width="90" height="90" rx="8" fill="white" opacity="0.9" />
        <rect x="220" y="110" width="90" height="90" rx="8" fill="white" opacity="0.6" />
        <rect x="0" y="220" width="90" height="90" rx="8" fill="white" opacity="0.9" />
        <rect x="110" y="220" width="90" height="90" rx="8" fill="white" opacity="0.6" />
        <rect x="220" y="220" width="90" height="90" rx="8" fill="white" opacity="0.9" />
      </g>
      <text x="300" y="330" font-family="Arial, sans-serif" font-size="80" font-weight="bold" text-anchor="middle" dominant-baseline="middle" fill="white">ALL</text>
    </svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Generate SVG for category placeholder
 *
 * @param string $category_slug Category slug for consistent color generation
 * @param string $category_name Category name for initials
 * @return string Base64 encoded SVG data URL
 */
function medbeafgallery_generate_category_svg($category_slug, $category_name) {
    // Generate a consistent hash-based color from the category slug
    $hash = abs(crc32($category_slug));
    $hue = $hash % 360;

    // Create a desaturated color palette
    $bg_color = "hsl($hue, 65%, 60%)";

    // Get first letter of each word in the category name
    $words = explode(' ', $category_name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) break; // Maximum 2 letters
    }

    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
      <rect width="600" height="600" fill="' . $bg_color . '" rx="300" />
      <text x="300" y="300" font-family="Arial, sans-serif" font-size="240" font-weight="bold"
            fill="white" text-anchor="middle" dominant-baseline="middle" opacity="0.9">
        ' . $initials . '
      </text>
    </svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
