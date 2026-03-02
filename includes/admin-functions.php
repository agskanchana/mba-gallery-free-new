<?php
/**
 * Admin functions for the Medical Before After Gallery plugin
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin scripts and styles
 */
function medbeafgallery_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'medbeafgallery') === false && $hook != 'post.php' && $hook != 'post-new.php') {
        return;
    }

    // Debug information to troubleshoot script loading
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Enqueuing scripts for ' . $hook);
    }

    // Get current screen
    $current_screen = get_current_screen();
    $is_case_edit = ($current_screen && $current_screen->post_type === 'medbeafgallery_case');

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Current screen: ' . ($current_screen ? $current_screen->post_type : 'none'));
    }

    // Register admin CSS with cache busting
    wp_register_style('medbeafgallery-admin-css', MEDBEAFGALLERY_URL . 'admin/css/admin-style.css', array(), MEDBEAFGALLERY_VERSION . '.' . time());
    wp_enqueue_style('medbeafgallery-admin-css');

    // Add the WordPress color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // Get plugin settings
    $settings = get_option('medbeafgallery_settings', array());

    // Ensure cropping settings have defaults if they don't exist yet
    if (!isset($settings['cropping_enabled'])) {
        $settings['cropping_enabled'] = true; // Enable by default
        update_option('medbeafgallery_settings', $settings);
    }

    if (!isset($settings['cropping_size']) || !is_numeric($settings['cropping_size'])) {
        $settings['cropping_size'] = 800;
        update_option('medbeafgallery_settings', $settings);
    }

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Cropping status check: ' . ($settings['cropping_enabled'] ? 'enabled' : 'disabled'));
    }

    // If this is a case edit page and cropping is enabled, load the cropper
    if ($is_case_edit) {
        // Add Cropper.js library directly to the plugin to avoid CDN dependencies
        wp_enqueue_style('cropper-css', MEDBEAFGALLERY_URL . 'assets/vendor/cropper/cropper.min.css', array(), '1.5.13');
        wp_enqueue_script('cropper-js', MEDBEAFGALLERY_URL . 'assets/vendor/cropper/cropper.min.js', array('jquery'), '1.5.13', true);

        // Add our custom cropper CSS and script
        wp_enqueue_style('medbeafgallery-image-cropper-css', MEDBEAFGALLERY_URL . 'admin/css/image-cropper.css', array(), MEDBEAFGALLERY_VERSION);
        wp_enqueue_script('medbeafgallery-image-cropper', MEDBEAFGALLERY_URL . 'admin/js/image-cropper.js', array('jquery', 'cropper-js'), MEDBEAFGALLERY_VERSION . '.' . time(), true);

        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Cropper scripts enqueued');
        }
    }

    // Register admin JavaScript with proper dependencies
    $admin_deps = array('jquery', 'jquery-ui-sortable');
    if ($is_case_edit && !empty($settings['cropping_enabled'])) {
        $admin_deps[] = 'medbeafgallery-image-cropper';
    }
    wp_register_script('medbeafgallery-admin-js', MEDBEAFGALLERY_URL . 'admin/js/admin-script.js', $admin_deps, MEDBEAFGALLERY_VERSION . '.' . time(), true);

    // Create localization data
    $admin_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('medbeafgallery_admin_nonce'),
        'cropping_enabled' => !empty($settings['cropping_enabled']) ? 1 : 0,
        'cropping_size' => absint($settings['cropping_size']),
        'crop_enabled' => !empty($settings['crop_enabled']) ? 1 : 0,
        'i18n' => array(
            'confirm_delete' => esc_html__('Are you sure you want to delete this item? This cannot be undone.', 'medical-before-after-gallery'),
            'no_images' => esc_html__('Please add at least one before-after image pair.', 'medical-before-after-gallery'),
            'uploading' => esc_html__('Uploading...', 'medical-before-after-gallery'),
            'upload_error' => esc_html__('Error uploading image.', 'medical-before-after-gallery')
        )
    );

    // Localize script with data
    wp_localize_script('medbeafgallery-admin-js', 'medbeafgalleryAdmin', $admin_data);
    wp_enqueue_script('medbeafgallery-admin-js');

    // WordPress media uploader
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'medbeafgallery_admin_enqueue_scripts');

// Include admin files
require_once MEDBEAFGALLERY_PATH . 'admin/menu.php';
require_once MEDBEAFGALLERY_PATH . 'admin/dashboard.php';
require_once MEDBEAFGALLERY_PATH . 'admin/settings.php';
// metaboxes.php is now included in main plugin file


/**
 * Display data in custom columns
 */
function medbeafgallery_custom_column_content($column, $post_id) {
    switch ($column) {


        case 'case_images':
            $image_pairs = get_post_meta($post_id, '_medbeafgallery_image_pairs', true);
            if (is_array($image_pairs)) {
                echo count($image_pairs) . ' ' . esc_html(_n('pair', 'pairs', count($image_pairs), 'medical-before-after-gallery'));
            } else {
                echo '0 ' . esc_html__('pairs', 'medical-before-after-gallery');
            }
            break;

        case 'case_details':
            $gender = get_post_meta($post_id, '_medbeafgallery_case_gender', true);
            $age = get_post_meta($post_id, '_medbeafgallery_case_age', true);
            $procedure = get_post_meta($post_id, '_medbeafgallery_case_procedure_type', true);

            $details = array();
            if (!empty($gender)) {
                $details[] = ucfirst($gender);
            }
            if (!empty($age)) {
                $details[] = $age;
            }
            if (!empty($procedure)) {
                $details[] = ucfirst($procedure);
            }

            echo esc_html(implode(' | ', $details));
            break;
    }
}
add_action('manage_medbeafgallery_case_posts_custom_column', 'medbeafgallery_custom_column_content', 10, 2);


