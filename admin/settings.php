<?php
/**
 * Admin settings page for MEDBEAFGALLERY Gallery
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display a simple info page instead of settings
 */
function medbeafgallery_settings_page() {
    ?>
    <div class="wrap medbeafgallery-admin-wrap">
        <h1><?php esc_html_e('Medical Before After Gallery', 'medical-before-after-gallery'); ?></h1>

        <div class="notice notice-info">
            <p><?php esc_html_e('Use the shortcode [medbeafgallery] to display your gallery on any page.', 'medical-before-after-gallery'); ?></p>
        </div>
    </div>
    <?php
}