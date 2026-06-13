<?php
/**
 * Settings functionality for Medical Before After Gallery
 *
 * @package MedBeAfGallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 *            <h3><?php esc_html_e('Image Cropping', 'medical-before-after-gallery'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Cropping', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <label for="cropping_enabled">
                            <input type="checkbox" name="cropping_enabled" id="cropping_enabled" value="1" <?php checked(!empty($settings['cropping_enabled'])); ?>>
                            <?php esc_html_e('Enable square image cropping for before/after images', 'medical-before-after-gallery'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('This will provide a cropping interface when uploading images.', 'medical-before-after-gallery'); ?></p>m CSS to style the admin settings
 */



/**
 * Display the settings form with cropping section
 */
function medbeafgallery_display_settings_form($settings) {
    // Add default values if not set
    $settings = wp_parse_args($settings, array(
        'consultation_enabled' => true,
        'consultation_url' => '',
        'consultation_text' => 'Schedule a Consultation',
        'content_warning_enabled' => false,
        'content_warning_title' => 'Content Warning',
        'content_warning_text' => 'This gallery contains before and after medical images which may include sensitive content.',
        'content_warning_button' => 'I understand, show the gallery',
        // Watermark settings
        'watermark_enabled' => false,
        'watermark_type' => 'text',
        'watermark_text' => '',
        'watermark_font_size' => '24',
        'watermark_color' => '#ffffff',
        'watermark_opacity' => '50',
        'watermark_image' => '',
        'watermark_position' => 'bottom-right',
        // Cropping settings
        'cropping_enabled' => true,
        'cropping_size' => 800
    ));
    ?>
    <div class="medbeafgallery-admin-box medbeafgallery-settings-form">
        <h2><?php esc_html_e('Gallery Settings', 'medical-before-after-gallery'); ?></h2>

        <form method="post" action="">
            <?php wp_nonce_field('medbeafgallery_settings_nonce'); ?>

            <!-- Other settings sections -->

            <!-- New Cropping Section -->
            <h3><?php esc_html_e('Image Cropping', 'medical-before-after-gallery'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Cropping', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <label for="cropping_enabled">
                            <input type="checkbox" name="cropping_enabled" id="cropping_enabled" value="1" <?php checked($settings['cropping_enabled']); ?>>
                            <?php esc_html_e('Enable square image cropping for before/after images', 'medical-before-after-gallery'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('This will provide a cropping interface when uploading images.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cropping Size', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <input type="number" name="cropping_size" id="cropping_size" min="300" max="2000" step="50" value="<?php echo esc_attr($settings['cropping_size']); ?>" class="small-text"> px
                        <p class="description"><?php esc_html_e('Output size for cropped images (width = height for square aspect ratio). Minimum 300px, maximum 2000px.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <!-- Existing Watermark Section -->
            <h3><?php esc_html_e('Watermarking', 'medical-before-after-gallery'); ?></h3>

            <!-- Include existing watermarking settings -->

            <p class="submit">
                <input type="submit" name="medbeafgallery_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'medical-before-after-gallery'); ?>">
            </p>
        </form>
    </div>
    <?php
}