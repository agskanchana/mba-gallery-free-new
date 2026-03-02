<?php
/**
 * Dashboard help guide for MEDBEAFGALLERY Gallery
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display help guide on dashboard
 */
function medbeafgallery_display_help_guide() {
    ?>
    <div class="medbeafgallery-admin-help">
        <h2><?php esc_html_e('Getting Started', 'medical-before-after-gallery'); ?></h2>

        <div class="medbeafgallery-help-columns">
            <div class="medbeafgallery-help-column">
                <h3><?php esc_html_e('1. Create Categories', 'medical-before-after-gallery'); ?></h3>
                <p><?php esc_html_e('First, create categories for your before-after cases, such as "Face", "Nose", "Body", etc.', 'medical-before-after-gallery'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=medbeafgallery_category&post_type=medbeafgallery_case')); ?>" class="button">
                    <?php esc_html_e('Manage Categories', 'medical-before-after-gallery'); ?>
                </a>
            </div>

            <div class="medbeafgallery-help-column">
                <h3><?php esc_html_e('2. Add Cases', 'medical-before-after-gallery'); ?></h3>
                <p><?php esc_html_e('Add your before-after cases with images, descriptions, and metadata.', 'medical-before-after-gallery'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=medbeafgallery_case')); ?>" class="button medbeafgallery-launch-wizard">
                    <?php esc_html_e('Add New Case', 'medical-before-after-gallery'); ?>
                </a>
            </div>

            <div class="medbeafgallery-help-column">
                <h3><?php esc_html_e('3. Display Gallery', 'medical-before-after-gallery'); ?></h3>
                <p><?php esc_html_e('Use the shortcode [medbeafgallery] to display your gallery on any page or post.', 'medical-before-after-gallery'); ?></p>
                <div class="medbeafgallery-shortcode-display">
                    <code>[medbeafgallery]</code>
                    <button class="medbeafgallery-copy-shortcode" data-shortcode="[medbeafgallery]">
                        <?php esc_html_e('Copy', 'medical-before-after-gallery'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>


    <?php
}