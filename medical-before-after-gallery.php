<?php
/**
 * Plugin Name: Medical Before After Gallery
 * Plugin URI: https://medicalbeforeaftergallery.com/
 * Description: Professional before-after image gallery plugin with filtering and categories for healthcare professionals. Free version with core features.
 * Version: 1.3.3
 * Author: Medical Before After Gallery
 * Text Domain: medical-before-after-gallery
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package Medical_Before_After_Gallery
 * @version 1.3.3
 * @author Medical Before After Gallery
 * @copyright Copyright (c) 2024, Medical Before After Gallery
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}


// Define security constants
define('MEDBEAFGALLERY_SECURE', true);

/**
 * Check plugin compatibility and requirements
 */
function medbeafgallery_check_requirements() {
    $requirements = array(
        'php_version' => '7.4',
        'wp_version' => '5.0',
        'mysql_version' => '5.6'
    );

    $errors = array();

    // Check PHP version
    if (version_compare(PHP_VERSION, $requirements['php_version'], '<')) {
        $errors[] = sprintf(
            /* translators: %1$s: required PHP version, %2$s: current PHP version */
            __('Medical Before After Gallery requires PHP version %1$s or higher. You are running version %2$s.', 'medical-before-after-gallery'),
            $requirements['php_version'],
            PHP_VERSION
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), $requirements['wp_version'], '<')) {
        $errors[] = sprintf(
            /* translators: %1$s: required WordPress version, %2$s: current WordPress version */
            __('Medical Before After Gallery requires WordPress version %1$s or higher. You are running version %2$s.', 'medical-before-after-gallery'),
            $requirements['wp_version'],
            get_bloginfo('version')
        );
    }

    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e('Medical Before After Gallery cannot be activated:', 'medical-before-after-gallery'); ?></strong></p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        });

        return false;
    }

    return true;
}

// Run compatibility check early
if (!medbeafgallery_check_requirements()) {
    return; // Stop loading if requirements not met
}

/**
 * Uninstall cleanup function
 */
function medbeafgallery_uninstall_cleanup() {
    // Remove plugin options
    delete_option('medbeafgallery_settings');
    delete_option('medbeafgallery_version');
    delete_option('medbeafgallery_db_version');

    // Remove user meta
    delete_metadata('user', 0, 'medbeafgallery_warning_acknowledged', '', true);

    // Remove transients
    delete_transient('medbeafgallery_library_warning');
    delete_transient('medbeafgallery_free_to_pro_migration');

    // Get and remove all custom post type data
    $posts = get_posts(array(
        'post_type' => 'medbeafgallery_case',
        'numberposts' => -1,
        'post_status' => 'any'
    ));

    foreach ($posts as $post) {
        // Delete all meta data first
        $meta_keys = get_post_meta($post->ID);
        foreach ($meta_keys as $key => $value) {
            delete_post_meta($post->ID, $key);
        }

        // Delete the post
        wp_delete_post($post->ID, true);
    }

    // Remove custom taxonomy terms
    $taxonomies = array('medbeafgallery_category');

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ));

        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
    }

    // Clear any cached data
    wp_cache_flush();

    // Log cleanup completion
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        call_user_func('error_log', 'Medical Before After Gallery: Uninstall cleanup completed');
    }
}

/**
 * Check if all features are enabled (always true for free version)
 */
function medbeafgallery_is_premium_active() {
    return true;
}

/**
 * Get maximum allowed cases (12 for free version, unlimited for pro)
 */
function medbeafgallery_get_max_cases() {
    if (apply_filters('medbeafgallery_has_valid_pro_license', false)) {
        return -1; // Unlimited
    }
    return 12;
}

/**
 * Get maximum allowed categories (4 for free version, unlimited for pro, excludes 'All')
 */
function medbeafgallery_get_max_categories() {
    if (apply_filters('medbeafgallery_has_valid_pro_license', false)) {
        return -1; // Unlimited
    }
    return 4;
}

/**
 * Check if advanced filters are enabled (always true for free version)
 */
function medbeafgallery_advanced_filters_enabled() {
    return true;
}







// Define plugin constants
define('MEDBEAFGALLERY_VERSION', '1.3.4');
define('MEDBEAFGALLERY_DB_VERSION', '1.0.0');
define('MEDBEAFGALLERY_PATH', plugin_dir_path(__FILE__));
define('MEDBEAFGALLERY_URL', plugin_dir_url(__FILE__));
define('MEDBEAFGALLERY_BASENAME', plugin_basename(__FILE__));

/**
 * Database version check and upgrade
 */
function medbeafgallery_check_db_version() {
    $installed_version = get_option('medbeafgallery_db_version', '0.0.0');

    if (version_compare($installed_version, MEDBEAFGALLERY_DB_VERSION, '<')) {
        medbeafgallery_upgrade_database($installed_version);
        update_option('medbeafgallery_db_version', MEDBEAFGALLERY_DB_VERSION);
    }
}
add_action('plugins_loaded', 'medbeafgallery_check_db_version');

/**
 * Handle database upgrades
 */
function medbeafgallery_upgrade_database($from_version) {
    // Example upgrade routines
    if (version_compare($from_version, '1.0.0', '<')) {
        // Upgrade to version 1.0.0
        // Add any database schema changes here
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'Medical Before After Gallery: Database upgraded to version 1.0.0');
        }
    }
}

if (!function_exists('medbeafgallery_enqueue_scripts')) {
// Enqueue scripts and styles
function medbeafgallery_enqueue_scripts() {
    // Only enqueue on pages that need it
    if (!is_admin() && (has_shortcode(get_post()->post_content ?? '', 'medical-before-after-gallery') || is_singular('medbeafgallery_case'))) {
        // Register and enqueue CSS
        wp_register_style('medbeafgallery-css', MEDBEAFGALLERY_URL . 'assets/css/gallery.css', array(), MEDBEAFGALLERY_VERSION);
        wp_enqueue_style('medbeafgallery-css');

        // Register and enqueue Cocoen before-after slider
        wp_register_script('cocoen-js', MEDBEAFGALLERY_URL . 'assets/vendor/cocoen/cocoen.min.js', array(), '3.2.0', true);
        wp_enqueue_script('cocoen-js');

        // Register and enqueue JavaScript
        wp_register_script('medbeafgallery-js', MEDBEAFGALLERY_URL . 'assets/js/gallery.js', array('jquery', 'cocoen-js'), MEDBEAFGALLERY_VERSION, true);

        // Create localization data with enhanced security
        $gallery_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('medical-before-after-gallery_nonce'),
            'rest_url' => rest_url('medical-before-after-gallery/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'plugin_url' => MEDBEAFGALLERY_URL,
            'is_admin' => current_user_can('manage_options'),
            'strings' => array(
                'loading' => __('Loading...', 'medical-before-after-gallery'),
                'error' => __('An error occurred. Please try again.', 'medical-before-after-gallery'),
                'no_results' => __('No results found.', 'medical-before-after-gallery'),
            )
        );

        // Localize script with data
        wp_localize_script('medbeafgallery-js', 'medbeafgalleryData', $gallery_data);

        // Enqueue script
        wp_enqueue_script('medbeafgallery-js');
    }
}
add_action('wp_enqueue_scripts', 'medbeafgallery_enqueue_scripts');
}

/**
 * Enhanced security: Add nonce verification for AJAX calls
 */
function medbeafgallery_verify_ajax_nonce() {
    $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';

    if (strpos($action, 'medical-before-after-gallery') !== false) {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'medical-before-after-gallery_nonce')) {
            wp_die(esc_html__('Security check failed', 'medical-before-after-gallery'), esc_html__('Security Error', 'medical-before-after-gallery'), array('response' => 403));
        }
    }
}
add_action('wp_ajax_nopriv_medbeafgallery_get_cases', 'medbeafgallery_verify_ajax_nonce', 1);
add_action('wp_ajax_medbeafgallery_get_cases', 'medbeafgallery_verify_ajax_nonce', 1);

/**
 * Log errors for debugging (only in debug mode)
 */
function medbeafgallery_log_error($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $log_message = '[Medical Before After Gallery] ' . $message;
        if ($data) {
            $log_message .= ' Data: ' . wp_json_encode($data);
        }
        call_user_func('error_log', $log_message);
    }
}

/**
 * Handle plugin errors gracefully
 */
function medbeafgallery_handle_error($error_message, $context = '') {
    // Log the error
    medbeafgallery_log_error($error_message, $context);

    // Return user-friendly message
    if (current_user_can('manage_options')) {
        /* translators: %s: error message */
        return sprintf(esc_html__('Medical Before After Gallery Error: %s', 'medical-before-after-gallery'), esc_html($error_message));
    } else {
        return esc_html__('Gallery temporarily unavailable. Please try again later.', 'medical-before-after-gallery');
    }
}

/**
 * Performance optimization: Cache gallery data
 */
function medbeafgallery_get_cached_data($cache_key, $callback, $expiration = 3600) {
    $cached_data = get_transient($cache_key);

    if (false === $cached_data) {
        $cached_data = call_user_func($callback);
        set_transient($cache_key, $cached_data, $expiration);
    }

    return $cached_data;
}

/**
 * Clear gallery cache when posts are updated
 */
function medbeafgallery_clear_cache($post_id) {
    if (get_post_type($post_id) === 'medbeafgallery_case') {
        delete_transient('medbeafgallery_cases_cache');
        delete_transient('medbeafgallery_categories_cache');
    }
}
add_action('save_post', 'medbeafgallery_clear_cache');
add_action('delete_post', 'medbeafgallery_clear_cache');



// Register uninstall hook
register_uninstall_hook(__FILE__, 'medbeafgallery_uninstall_cleanup');

// Register activation hook
function medbeafgallery_activate() {
    // Create custom post type
    medbeafgallery_register_post_types();

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'medbeafgallery_activate');

// Register deactivation hook
function medbeafgallery_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'medbeafgallery_deactivate');

/**
 * Check for required image libraries on plugin activation
 */
function medbeafgallery_check_libraries_on_activation() {
    $image_libraries = medbeafgallery_check_image_libraries();

    if (!$image_libraries['has_required_library']) {
        set_transient('medbeafgallery_library_warning', true, 60 * 60 * 24); // 1 day notice
    }
}
register_activation_hook(__FILE__, 'medbeafgallery_check_libraries_on_activation');

/**
 * Display admin notice if libraries are missing
 */
function medbeafgallery_library_warning_notice() {
    if (get_transient('medbeafgallery_library_warning')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Medical Before After Gallery Warning:', 'medical-before-after-gallery'); ?></strong>
                <?php esc_html_e('Your server configuration may limit some functionality. Please contact your hosting provider if you experience issues.', 'medical-before-after-gallery'); ?>
            </p>
        </div>
        <?php
        delete_transient('medbeafgallery_library_warning');
    }
}
add_action('admin_notices', 'medbeafgallery_library_warning_notice');

/**
 * Plugin health check - run diagnostics
 */
function medbeafgallery_health_check() {
    $health_status = array(
        'php_version' => version_compare(PHP_VERSION, '7.4', '>='),
        'wp_version' => version_compare(get_bloginfo('version'), '5.0', '>='),
        'image_library' => function_exists('gd_info') || extension_loaded('imagick'),
        'memory_limit' => wp_convert_hr_to_bytes(ini_get('memory_limit')) >= 134217728, // 128MB
        'upload_dir_writable' => wp_is_writable(wp_upload_dir()['basedir']),
    );

    return $health_status;
}

/**
 * Display health check results in admin
 */
function medbeafgallery_admin_health_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_medbeafgallery') {
        return;
    }

    $health = medbeafgallery_health_check();
    $has_issues = array_search(false, $health, true) !== false;

    if ($has_issues) {
        ?>
        <div class="notice notice-warning">
            <h3><?php esc_html_e('Medical Before After Gallery Health Check', 'medical-before-after-gallery'); ?></h3>
            <ul>
                <?php if (!$health['php_version']): ?>
                    <li><?php esc_html_e('⚠️ PHP version should be 7.4 or higher for optimal performance', 'medical-before-after-gallery'); ?></li>
                <?php endif; ?>
                <?php if (!$health['wp_version']): ?>
                    <li><?php esc_html_e('⚠️ WordPress version should be 5.0 or higher', 'medical-before-after-gallery'); ?></li>
                <?php endif; ?>
                <?php if (!$health['image_library']): ?>
                    <li><?php esc_html_e('⚠️ No image processing library detected.', 'medical-before-after-gallery'); ?></li>
                <?php endif; ?>
                <?php if (!$health['memory_limit']): ?>
                    <li><?php esc_html_e('⚠️ Memory limit may be too low for processing large images', 'medical-before-after-gallery'); ?></li>
                <?php endif; ?>
                <?php if (!$health['upload_dir_writable']): ?>
                    <li><?php esc_html_e('⚠️ Upload directory is not writable', 'medical-before-after-gallery'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
}
add_action('admin_notices', 'medbeafgallery_admin_health_notice');

// Include required files
require_once MEDBEAFGALLERY_PATH . 'includes/utilities.php';
require_once MEDBEAFGALLERY_PATH . 'includes/post-types.php';
require_once MEDBEAFGALLERY_PATH . 'includes/shortcodes.php';
require_once MEDBEAFGALLERY_PATH . 'includes/admin-functions.php';
require_once MEDBEAFGALLERY_PATH . 'includes/rest.php';
require_once MEDBEAFGALLERY_PATH . 'includes/ajax-handlers.php';

// Include admin files
if (is_admin()) {
    require_once MEDBEAFGALLERY_PATH . 'admin/metaboxes.php';
}

// Call this on plugin activation
register_activation_hook(__FILE__, 'medbeafgallery_create_default_categories');

/**
 * =========================================================================
 * FREE VERSION LIMITS ENFORCEMENT
 * =========================================================================
 */

/**
 * Redirect away from "Add New Case" page if case limit is reached
 */
function medbeafgallery_enforce_case_limit_redirect() {
    global $pagenow;

    if ($pagenow !== 'post-new.php') {
        return;
    }

    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'medbeafgallery_case') {
        return;
    }

    if (!function_exists('medbeafgallery_check_case_limit')) {
        return;
    }

    if (!medbeafgallery_check_case_limit()) {
        // Set a transient to show the notice after redirect
        set_transient('medbeafgallery_case_limit_notice', true, 30);
        wp_safe_redirect(admin_url('edit.php?post_type=medbeafgallery_case'));
        exit;
    }
}
add_action('admin_init', 'medbeafgallery_enforce_case_limit_redirect');

/**
 * Prevent saving new cases when limit is reached (belt and suspenders)
 */
function medbeafgallery_enforce_case_limit_on_save($post_id, $post, $update) {
    // Only check for new posts, not updates
    if ($update) {
        return;
    }

    if ($post->post_type !== 'medbeafgallery_case') {
        return;
    }

    // Allow auto-drafts to be created but prevent publishing
    if ($post->post_status === 'auto-draft') {
        return;
    }

    if (!function_exists('medbeafgallery_get_max_cases')) {
        return;
    }

    $max_cases = medbeafgallery_get_max_cases();
    if ($max_cases === -1) {
        return;
    }

    $current_cases = wp_count_posts('medbeafgallery_case');
    // Subtract 1 because the current post being saved is already counted
    $total_cases = $current_cases->publish + $current_cases->draft + $current_cases->private;

    if ($total_cases > $max_cases) {
        // Trash the post that was just created over the limit
        wp_trash_post($post_id);
        set_transient('medbeafgallery_case_limit_notice', true, 30);
        wp_safe_redirect(admin_url('edit.php?post_type=medbeafgallery_case'));
        exit;
    }
}
add_action('wp_insert_post', 'medbeafgallery_enforce_case_limit_on_save', 10, 3);

/**
 * Prevent creating new categories when limit is reached
 */
function medbeafgallery_enforce_category_limit_on_create($term, $taxonomy) {
    if ($taxonomy !== 'medbeafgallery_category') {
        return $term;
    }

    if (!function_exists('medbeafgallery_check_category_limit')) {
        return $term;
    }

    if (!medbeafgallery_check_category_limit()) {
        $max_categories = medbeafgallery_get_max_categories();
        return new WP_Error(
            'medbeafgallery_category_limit',
            sprintf(
                /* translators: %d: maximum number of categories allowed */
                __('You have reached the maximum limit of %d categories in the free version. Please upgrade to the Pro version to add unlimited categories.', 'medical-before-after-gallery'),
                $max_categories
            )
        );
    }

    return $term;
}
add_filter('pre_insert_term', 'medbeafgallery_enforce_category_limit_on_create', 10, 2);

/**
 * Display admin notices for case and category limits
 */
function medbeafgallery_display_limit_notices() {
    global $pagenow, $current_screen;

    if (!current_user_can('manage_options')) {
        return;
    }

    $pro_url = 'https://medicalbeforeaftergallery.com/';
    $pro_link = '<a href="' . esc_url($pro_url) . '" target="_blank" style="font-weight:bold;">' . esc_html__('Upgrade to Pro', 'medical-before-after-gallery') . '</a>';

    // Show case limit redirect notice
    if (get_transient('medbeafgallery_case_limit_notice')) {
        delete_transient('medbeafgallery_case_limit_notice');
        $max_cases = medbeafgallery_get_max_cases();
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Medical Before After Gallery', 'medical-before-after-gallery'); ?>:</strong>
                <?php
                printf(
                    /* translators: %1$d: maximum cases, %2$s: upgrade link */
                    esc_html__('You have reached the maximum limit of %1$d cases in the free version. %2$s to add unlimited cases.', 'medical-before-after-gallery'),
                    $max_cases,
                    $pro_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above
                );
                ?>
            </p>
        </div>
        <?php
    }

    // Show persistent warning on case listing page when near or at limit
    if ($current_screen && $current_screen->post_type === 'medbeafgallery_case' && ($pagenow === 'edit.php' || $pagenow === 'post.php')) {
        $max_cases = medbeafgallery_get_max_cases();
        if ($max_cases !== -1) {
            $current_cases = wp_count_posts('medbeafgallery_case');
            $total_cases = $current_cases->publish + $current_cases->draft + $current_cases->private;

            if ($total_cases >= $max_cases) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e('Medical Before After Gallery', 'medical-before-after-gallery'); ?>:</strong>
                        <?php
                        printf(
                            /* translators: %1$d: current cases, %2$d: maximum cases, %3$s: upgrade link */
                            esc_html__('You are using %1$d of %2$d cases allowed in the free version. %3$s for unlimited cases, watermarking, advanced filters, and more.', 'medical-before-after-gallery'),
                            $total_cases,
                            $max_cases,
                            $pro_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        );
                        ?>
                    </p>
                </div>
                <?php
            } elseif ($total_cases >= ($max_cases - 2)) {
                // Show info notice when approaching limit (within 2)
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong><?php esc_html_e('Medical Before After Gallery', 'medical-before-after-gallery'); ?>:</strong>
                        <?php
                        printf(
                            /* translators: %1$d: current cases, %2$d: maximum cases, %3$s: upgrade link */
                            esc_html__('You are using %1$d of %2$d cases allowed in the free version. %3$s for unlimited cases and more features.', 'medical-before-after-gallery'),
                            $total_cases,
                            $max_cases,
                            $pro_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    // Show persistent warning on category management pages when near or at limit
    if ($current_screen && $current_screen->taxonomy === 'medbeafgallery_category') {
        $max_categories = medbeafgallery_get_max_categories();
        if ($max_categories !== -1) {
            $total_categories = medbeafgallery_get_category_count();

            if ($total_categories >= $max_categories) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e('Medical Before After Gallery', 'medical-before-after-gallery'); ?>:</strong>
                        <?php
                        printf(
                            /* translators: %1$d: current categories, %2$d: maximum categories, %3$s: upgrade link */
                            esc_html__('You are using %1$d of %2$d categories allowed in the free version. %3$s for unlimited categories and more features.', 'medical-before-after-gallery'),
                            $total_categories,
                            $max_categories,
                            $pro_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        );
                        ?>
                    </p>
                </div>
                <?php
            } elseif ($total_categories >= ($max_categories - 1)) {
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong><?php esc_html_e('Medical Before After Gallery', 'medical-before-after-gallery'); ?>:</strong>
                        <?php
                        printf(
                            /* translators: %1$d: current categories, %2$d: maximum categories, %3$s: upgrade link */
                            esc_html__('You are using %1$d of %2$d categories allowed in the free version. %3$s for unlimited categories.', 'medical-before-after-gallery'),
                            $total_categories,
                            $max_categories,
                            $pro_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }
}
add_action('admin_notices', 'medbeafgallery_display_limit_notices');

/**
 * Hide the "Add New" button on cases listing when limit is reached
 */
function medbeafgallery_hide_add_new_button_at_limit() {
    global $current_screen;

    if (!$current_screen || $current_screen->post_type !== 'medbeafgallery_case') {
        return;
    }

    $max_cases = medbeafgallery_get_max_cases();
    if ($max_cases === -1) {
        return;
    }

    $current_cases = wp_count_posts('medbeafgallery_case');
    $total_cases = $current_cases->publish + $current_cases->draft + $current_cases->private;

    if ($total_cases >= $max_cases) {
        ?>
        <style>
            .post-type-medbeafgallery_case .page-title-action,
            .post-type-medbeafgallery_case #wp-admin-bar-new-medbeafgallery_case {
                display: none !important;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'medbeafgallery_hide_add_new_button_at_limit');

/**
 * Hide the "Add New Category" form when limit is reached
 */
function medbeafgallery_hide_add_category_form_at_limit() {
    global $current_screen;

    if (!$current_screen || $current_screen->taxonomy !== 'medbeafgallery_category') {
        return;
    }

    if (!function_exists('medbeafgallery_check_category_limit')) {
        return;
    }

    if (!medbeafgallery_check_category_limit()) {
        ?>
        <style>
            #col-left { display: none !important; }
            #col-right { float: none !important; width: 100% !important; }
        </style>
        <?php
    }
}
add_action('admin_head', 'medbeafgallery_hide_add_category_form_at_limit');

/**
 * Add limit info to the "Add New Case" submenu label
 */
function medbeafgallery_modify_submenu_labels() {
    global $submenu;

    if (!isset($submenu['medbeafgallery-gallery'])) {
        return;
    }

    $max_cases = medbeafgallery_get_max_cases();
    if ($max_cases === -1) {
        return;
    }

    $current_cases = wp_count_posts('medbeafgallery_case');
    $total_cases = $current_cases->publish + $current_cases->draft + $current_cases->private;

    foreach ($submenu['medbeafgallery-gallery'] as &$item) {
        // "Add New Case" menu item
        if ($item[2] === 'post-new.php?post_type=medbeafgallery_case') {
            $item[0] = sprintf(
                /* translators: %1$d: current cases, %2$d: max cases */
                __('Add New Case (%1$d/%2$d)', 'medical-before-after-gallery'),
                $total_cases,
                $max_cases
            );

            if ($total_cases >= $max_cases) {
                $item[0] = '<span style="opacity:0.5;">' . esc_html(sprintf(
                    __('Add New Case (%1$d/%2$d)', 'medical-before-after-gallery'),
                    $total_cases,
                    $max_cases
                )) . '</span>';
            }
        }
    }
}
add_action('admin_menu', 'medbeafgallery_modify_submenu_labels', 999);
