<?php
/**
 * Metaboxes for the MEDBEAFGALLERY Gallery plugin
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register metaboxes for the case post type
 */
function medbeafgallery_add_metaboxes() {
    // Before-After Image Pairs
    add_meta_box(
        'medbeafgallery_image_pairs',
        esc_html__('Before & After Image Pairs', 'medical-before-after-gallery'),
        'medbeafgallery_image_pairs_metabox',
        'medbeafgallery_case',
        'normal',
        'high'
    );

    // Case Details (PRO - Locked in Free Version)
    add_meta_box(
        'medbeafgallery_case_details',
        esc_html__('Case Details', 'medical-before-after-gallery'),
        'medbeafgallery_case_details_metabox',
        'medbeafgallery_case',
        'normal',
        'default'
    );

    // Shortcode Display
    add_meta_box(
        'medbeafgallery_shortcode',
        esc_html__('Gallery Shortcode', 'medical-before-after-gallery'),
        'medbeafgallery_shortcode_metabox',
        'medbeafgallery_case',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'medbeafgallery_add_metaboxes');

/**
 * Display the image pairs metabox
 */
function medbeafgallery_image_pairs_metabox($post) {
    // Add nonce for security
    wp_nonce_field('medbeafgallery_save_meta', 'medbeafgallery_meta_nonce');

    // Get main before and after images
    $main_before_id = get_post_meta($post->ID, '_medbeafgallery_main_before_image', true);
    $main_after_id = get_post_meta($post->ID, '_medbeafgallery_main_after_image', true);

    // Get additional image pairs
    $additional_pairs = get_post_meta($post->ID, '_medbeafgallery_additional_image_pairs', true);

    // If no additional pairs exist, create an empty array
    if (!is_array($additional_pairs)) {
        $additional_pairs = array();
    }
    ?>
    <div class="medbeafgallery-admin-metabox">
        <p class="description">
            <?php esc_html_e('Add before and after images for this case.', 'medical-before-after-gallery'); ?>
        </p>

        <!-- Main Before and After Images -->
        <div class="medbeafgallery-main-image-pair medbeafgallery-image-pair">
            <h3 class="medbeafgallery-pair-header"><?php esc_html_e('Main Before & After Images (Required)', 'medical-before-after-gallery'); ?></h3>

            <div class="medbeafgallery-image-pair-content">
                <div class="medbeafgallery-image-pair-row">
                    <div class="medbeafgallery-image-pair-column">
                        <div class="medbeafgallery-image-container">
                            <label><?php esc_html_e('Before Image', 'medical-before-after-gallery'); ?></label>
                            <div class="medbeafgallery-image-preview" id="medbeafgallery-main-before-preview">
                                <?php if (!empty($main_before_id)) :
                                    echo wp_get_attachment_image($main_before_id, 'medium');
                                endif; ?>
                            </div>
                            <input type="hidden" name="medbeafgallery_main_before_id" value="<?php echo esc_attr($main_before_id); ?>" id="medbeafgallery-main-before-id">
                            <button type="button" class="button" id="medbeafgallery-upload-main-before"><?php esc_html_e('Upload Before Image', 'medical-before-after-gallery'); ?></button>
                            <button type="button" class="button" id="medbeafgallery-remove-main-before" <?php echo empty($main_before_id) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                        </div>
                    </div>

                    <div class="medbeafgallery-image-pair-column">
                        <div class="medbeafgallery-image-container">
                            <label><?php esc_html_e('After Image', 'medical-before-after-gallery'); ?></label>
                            <div class="medbeafgallery-image-preview" id="medbeafgallery-main-after-preview">
                                <?php if (!empty($main_after_id)) :
                                    echo wp_get_attachment_image($main_after_id, 'medium');
                                endif; ?>
                            </div>
                            <input type="hidden" name="medbeafgallery_main_after_id" value="<?php echo esc_attr($main_after_id); ?>" id="medbeafgallery-main-after-id">
                            <button type="button" class="button" id="medbeafgallery-upload-main-after"><?php esc_html_e('Upload After Image', 'medical-before-after-gallery'); ?></button>
                            <button type="button" class="button" id="medbeafgallery-remove-main-after" <?php echo empty($main_after_id) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Image Pairs -->
        <?php
        $has_pro = apply_filters('medbeafgallery_has_valid_pro_license', false);
        if ($has_pro) :
            // ── PRO ACTIVE: Render working additional pairs ──
        ?>
        <div class="medbeafgallery-additional-pairs-section">
            <div class="medbeafgallery-pro-section-header">
                <h3><?php esc_html_e('Additional Image Pairs (Optional)', 'medical-before-after-gallery'); ?></h3>
            </div>
            <p class="description"><?php esc_html_e('Add multiple before/after image pairs for different views or angles.', 'medical-before-after-gallery'); ?></p>

            <div id="medbeafgallery-additional-pairs-container">
                <?php if (!empty($additional_pairs) && is_array($additional_pairs)) : ?>
                    <?php foreach ($additional_pairs as $index => $pair) :
                        $before_id    = isset($pair['before_image']) ? $pair['before_image'] : '';
                        $after_id     = isset($pair['after_image']) ? $pair['after_image'] : '';
                        $description  = isset($pair['description']) ? $pair['description'] : '';
                    ?>
                    <div class="medbeafgallery-image-pair medbeafgallery-additional-pair" data-index="<?php echo esc_attr($index); ?>">
                        <div class="medbeafgallery-pair-header">
                            <?php printf(esc_html__('Image Pair %d', 'medical-before-after-gallery'), $index + 1); ?>
                            <span class="medbeafgallery-remove-pair dashicons dashicons-trash"></span>
                            <span class="medbeafgallery-drag-handle dashicons dashicons-move"></span>
                        </div>
                        <div class="medbeafgallery-image-pair-row">
                            <div class="medbeafgallery-image-pair-column">
                                <div class="medbeafgallery-image-container">
                                    <label><?php esc_html_e('Before Image', 'medical-before-after-gallery'); ?></label>
                                    <div class="medbeafgallery-image-preview" id="medbeafgallery-pair-before-preview-<?php echo esc_attr($index); ?>">
                                        <?php if (!empty($before_id)) : echo wp_get_attachment_image($before_id, 'medium'); endif; ?>
                                    </div>
                                    <input type="hidden" class="medbeafgallery-before-id" name="medbeafgallery_pairs[<?php echo esc_attr($index); ?>][before_image]" value="<?php echo esc_attr($before_id); ?>">
                                    <button type="button" class="button medbeafgallery-upload-before-btn"><?php esc_html_e('Upload Before Image', 'medical-before-after-gallery'); ?></button>
                                    <button type="button" class="button medbeafgallery-remove-before-btn" <?php echo empty($before_id) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                                </div>
                            </div>
                            <div class="medbeafgallery-image-pair-column">
                                <div class="medbeafgallery-image-container">
                                    <label><?php esc_html_e('After Image', 'medical-before-after-gallery'); ?></label>
                                    <div class="medbeafgallery-image-preview" id="medbeafgallery-pair-after-preview-<?php echo esc_attr($index); ?>">
                                        <?php if (!empty($after_id)) : echo wp_get_attachment_image($after_id, 'medium'); endif; ?>
                                    </div>
                                    <input type="hidden" class="medbeafgallery-after-id" name="medbeafgallery_pairs[<?php echo esc_attr($index); ?>][after_image]" value="<?php echo esc_attr($after_id); ?>">
                                    <button type="button" class="button medbeafgallery-upload-after-btn"><?php esc_html_e('Upload After Image', 'medical-before-after-gallery'); ?></button>
                                    <button type="button" class="button medbeafgallery-remove-after-btn" <?php echo empty($after_id) ? 'style="display:none;"' : ''; ?>><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div class="medbeafgallery-image-description">
                            <label><?php esc_html_e('View Label:', 'medical-before-after-gallery'); ?></label>
                            <input type="text" class="regular-text" name="medbeafgallery_pairs[<?php echo esc_attr($index); ?>][description]" value="<?php echo esc_attr($description); ?>" placeholder="<?php esc_attr_e('e.g. Front View', 'medical-before-after-gallery'); ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="medbeafgallery-add-pair-container">
                <button type="button" class="button" id="medbeafgallery-add-image-pair">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Add Additional Image Pair', 'medical-before-after-gallery'); ?>
                </button>
            </div>
        </div>

        <!-- Template for dynamically adding new pairs -->
        <script type="text/html" id="medbeafgallery-image-pair-template">
            <div class="medbeafgallery-image-pair medbeafgallery-additional-pair" data-index="{{index}}">
                <div class="medbeafgallery-pair-header">
                    <?php esc_html_e('Image Pair', 'medical-before-after-gallery'); ?>
                    <span class="medbeafgallery-remove-pair dashicons dashicons-trash"></span>
                    <span class="medbeafgallery-drag-handle dashicons dashicons-move"></span>
                </div>
                <div class="medbeafgallery-image-pair-row">
                    <div class="medbeafgallery-image-pair-column">
                        <div class="medbeafgallery-image-container">
                            <label><?php esc_html_e('Before Image', 'medical-before-after-gallery'); ?></label>
                            <div class="medbeafgallery-image-preview" id="medbeafgallery-pair-before-preview-{{index}}"></div>
                            <input type="hidden" class="medbeafgallery-before-id" name="medbeafgallery_pairs[{{index}}][before_image]" value="">
                            <button type="button" class="button medbeafgallery-upload-before-btn"><?php esc_html_e('Upload Before Image', 'medical-before-after-gallery'); ?></button>
                            <button type="button" class="button medbeafgallery-remove-before-btn" style="display:none;"><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                        </div>
                    </div>
                    <div class="medbeafgallery-image-pair-column">
                        <div class="medbeafgallery-image-container">
                            <label><?php esc_html_e('After Image', 'medical-before-after-gallery'); ?></label>
                            <div class="medbeafgallery-image-preview" id="medbeafgallery-pair-after-preview-{{index}}"></div>
                            <input type="hidden" class="medbeafgallery-after-id" name="medbeafgallery_pairs[{{index}}][after_image]" value="">
                            <button type="button" class="button medbeafgallery-upload-after-btn"><?php esc_html_e('Upload After Image', 'medical-before-after-gallery'); ?></button>
                            <button type="button" class="button medbeafgallery-remove-after-btn" style="display:none;"><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                        </div>
                    </div>
                </div>
                <div class="medbeafgallery-image-description">
                    <label><?php esc_html_e('View Label:', 'medical-before-after-gallery'); ?></label>
                    <input type="text" class="regular-text" name="medbeafgallery_pairs[{{index}}][description]" value="" placeholder="<?php esc_attr_e('e.g. Front View', 'medical-before-after-gallery'); ?>">
                </div>
            </div>
        </script>

        <?php else :
            // ── FREE VERSION: Show locked promo ──
        ?>
        <div class="medbeafgallery-pro-metabox-section">
            <div class="medbeafgallery-pro-section-header">
                <h3><?php esc_html_e('Additional Image Pairs (Optional)', 'medical-before-after-gallery'); ?></h3>
                <span class="medbeafgallery-pro-badge">
                    <span class="dashicons dashicons-lock"></span> PRO
                </span>
            </div>

            <div class="medbeafgallery-pro-overlay">
                <div class="medbeafgallery-pro-overlay-content">
                    <span class="dashicons dashicons-lock"></span>
                    <p><?php esc_html_e('This feature is available in PRO version', 'medical-before-after-gallery'); ?></p>
                    <p class="pro-price-small"><strong><?php esc_html_e('Just $10 - One-Time Payment', 'medical-before-after-gallery'); ?></strong></p>
                    <a href="https://medicalbeforeaftergallery.com/#pricing" target="_blank" class="button button-primary">
                        <?php esc_html_e('Upgrade to PRO - $10', 'medical-before-after-gallery'); ?>
                    </a>
                </div>
            </div>

            <div class="medbeafgallery-pro-content">
                <p class="description"><?php esc_html_e('Add multiple before/after image pairs for different views or angles.', 'medical-before-after-gallery'); ?></p>
                
                <!-- Sample Additional Pair -->
                <div class="medbeafgallery-image-pair medbeafgallery-additional-pair">
                    <div class="medbeafgallery-pair-header">
                        <span><?php esc_html_e('Image Pair #1', 'medical-before-after-gallery'); ?></span>
                        <button type="button" class="button" disabled><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?></button>
                    </div>
                    <div class="medbeafgallery-image-pair-row">
                        <div class="medbeafgallery-image-pair-column">
                            <div class="medbeafgallery-image-container">
                                <label><?php esc_html_e('Before Image', 'medical-before-after-gallery'); ?></label>
                                <div class="medbeafgallery-image-preview"></div>
                                <button type="button" class="button" disabled><?php esc_html_e('Upload Before Image', 'medical-before-after-gallery'); ?></button>
                            </div>
                        </div>
                        <div class="medbeafgallery-image-pair-column">
                            <div class="medbeafgallery-image-container">
                                <label><?php esc_html_e('After Image', 'medical-before-after-gallery'); ?></label>
                                <div class="medbeafgallery-image-preview"></div>
                                <button type="button" class="button" disabled><?php esc_html_e('Upload After Image', 'medical-before-after-gallery'); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="medbeafgallery-image-description">
                        <label><?php esc_html_e('View Description:', 'medical-before-after-gallery'); ?></label>
                        <input type="text" class="regular-text" placeholder="<?php esc_attr_e('e.g. Front View', 'medical-before-after-gallery'); ?>" disabled>
                    </div>
                </div>

                <div class="medbeafgallery-add-pair-container">
                    <button type="button" class="button" disabled>
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Additional Image Pair', 'medical-before-after-gallery'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display the Case Details metabox.
 * When Pro is active the filters are rendered dynamically from the saved
 * configuration; otherwise the locked promo UI is shown.
 */
function medbeafgallery_case_details_metabox($post) {
    $has_pro = apply_filters('medbeafgallery_has_valid_pro_license', false);

    if ($has_pro && function_exists('mba_gallery_pro_render_case_filters')) {
        // ── Pro active: render working filter fields ──
        ?>
        <div class="medbeafgallery-admin-metabox">
            <p class="description"><?php esc_html_e('These details will be used for filtering and displaying information about the case.', 'medical-before-after-gallery'); ?></p>
            <?php mba_gallery_pro_render_case_filters($post); ?>
        </div>
        <?php
        return;
    }

    // ── Free / no Pro: locked promo ──
    ?>
    <div class="medbeafgallery-admin-metabox medbeafgallery-pro-metabox">
        <div class="medbeafgallery-pro-metabox-header">
            <p class="description"><?php esc_html_e('These details will be used for filtering and displaying information about the case.', 'medical-before-after-gallery'); ?></p>
            <span class="medbeafgallery-pro-badge">
                <span class="dashicons dashicons-lock"></span> PRO
            </span>
        </div>

        <div class="medbeafgallery-pro-overlay">
            <div class="medbeafgallery-pro-overlay-content">
                <span class="dashicons dashicons-lock"></span>
                <p><?php esc_html_e('This feature is available in PRO version', 'medical-before-after-gallery'); ?></p>
                <p class="pro-price-small"><strong><?php esc_html_e('Just $10 - One-Time Payment', 'medical-before-after-gallery'); ?></strong></p>
                <a href="https://medicalbeforeaftergallery.com/#pricing" target="_blank" class="button button-primary">
                    <?php esc_html_e('Upgrade to PRO - $10', 'medical-before-after-gallery'); ?>
                </a>
            </div>
        </div>

        <div class="medbeafgallery-pro-content">
            <table class="form-table medbeafgallery-case-details">
                <tr>
                    <th scope="row"><label><?php esc_html_e('Gender', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Select Gender', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Male', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Female', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Other', 'medical-before-after-gallery'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Age Range', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Select Age Range', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('18-25', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('26-35', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('36-45', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('46-55', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('56+', 'medical-before-after-gallery'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Recovery Time', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Select Recovery Time', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('1-3 days', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('1 week', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('2 weeks', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('1 month', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('3+ months', 'medical-before-after-gallery'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Treatment Duration', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Select Treatment Duration', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Single session', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Multiple sessions', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Ongoing', 'medical-before-after-gallery'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Results Visibility', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Select Results Visibility', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Immediate', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('1-2 weeks', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('1 month', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('3+ months', 'medical-before-after-gallery'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Procedure Type', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Select Procedure Type', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Surgical', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Non-surgical', 'medical-before-after-gallery'); ?></option>
                            <option><?php esc_html_e('Minimally invasive', 'medical-before-after-gallery'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Display the shortcode metabox
 */
function medbeafgallery_shortcode_metabox($post) {
    ?>
    <div class="medbeafgallery-admin-metabox">
        <p><?php esc_html_e('Use this shortcode to display your gallery:', 'medical-before-after-gallery'); ?></p>
        <div class="medbeafgallery-shortcode-display">
            <code>[medbeafgallery]</code>
            <button class="medbeafgallery-copy-shortcode button" data-shortcode="[medbeafgallery]">
                <?php esc_html_e('Copy', 'medical-before-after-gallery'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Save meta box data when the post is saved
 */
function medbeafgallery_save_meta_data($post_id) {
    // Check if our nonce is set
    if (!isset($_POST['medbeafgallery_meta_nonce'])) {
        return;
    }

    // Verify that the nonce is valid
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['medbeafgallery_meta_nonce'])), 'medbeafgallery_save_meta')) {
        return;
    }

    // If this is an autosave, we don't want to do anything
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save main before/after images
    if (isset($_POST['medbeafgallery_main_before_id'])) {
        update_post_meta($post_id, '_medbeafgallery_main_before_image', sanitize_text_field(wp_unslash($_POST['medbeafgallery_main_before_id'])));
    }

    if (isset($_POST['medbeafgallery_main_after_id'])) {
        update_post_meta($post_id, '_medbeafgallery_main_after_image', sanitize_text_field(wp_unslash($_POST['medbeafgallery_main_after_id'])));
    }

}
add_action('save_post_medbeafgallery_case', 'medbeafgallery_save_meta_data');

/**
 * Enqueue scripts for case metabox image uploads
 */
function medbeafgallery_case_admin_scripts() {
    $screen = get_current_screen();

    // Only load on case edit pages
    if ($screen->post_type !== 'medbeafgallery_case') {
        return;
    }

    // No need to add inline script here - admin-script.js already handles this
    // Just ensure media library is available
    wp_enqueue_media();

    // Add inline CSS for the metabox styling
    wp_add_inline_style('wp-admin', '
        .medbeafgallery-admin-metabox {
            padding: 20px;
        }

        .medbeafgallery-image-pair-content {
            margin-top: 15px;
        }

        .medbeafgallery-image-pair-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .medbeafgallery-image-pair-column {
            flex: 1;
            min-width: 250px;
        }

        .medbeafgallery-image-container {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            background: #f9f9f9;
        }

        .medbeafgallery-image-container label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .medbeafgallery-image-preview {
            width: 100%;
            min-height: 150px;
            background-color: #f1f1f1;
            border: 2px dashed #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .medbeafgallery-image-preview img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .medbeafgallery-image-preview:empty::after {
            content: "No image selected";
            color: #666;
            font-style: italic;
        }

        .medbeafgallery-image-container .button {
            margin-right: 5px;
        }

        .medbeafgallery-pair-header {
            margin: 0 0 15px 0;
            color: #333;
        }

        /* Pro Metabox Styles */
        .medbeafgallery-pro-metabox,
        .medbeafgallery-pro-metabox-section {
            position: relative;
        }

        .medbeafgallery-pro-metabox-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .medbeafgallery-pro-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .medbeafgallery-pro-section-header h3 {
            margin: 0;
        }

        .medbeafgallery-pro-metabox .medbeafgallery-pro-badge,
        .medbeafgallery-pro-metabox-section .medbeafgallery-pro-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .medbeafgallery-pro-badge .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }

        .medbeafgallery-pro-metabox .medbeafgallery-pro-overlay,
        .medbeafgallery-pro-metabox-section .medbeafgallery-pro-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 10;
            border-radius: 4px;
            cursor: not-allowed;
        }

        .medbeafgallery-pro-metabox:hover .medbeafgallery-pro-overlay,
        .medbeafgallery-pro-metabox-section:hover .medbeafgallery-pro-overlay {
            opacity: 1;
            visibility: visible;
        }

        .medbeafgallery-pro-overlay-content {
            text-align: center;
            padding: 30px;
        }

        .medbeafgallery-pro-overlay-content .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .medbeafgallery-pro-overlay-content p {
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 15px;
        }

        .medbeafgallery-pro-overlay-content .button-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .medbeafgallery-pro-overlay-content .button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .medbeafgallery-pro-content {
            pointer-events: none;
            opacity: 0.6;
        }

        .medbeafgallery-pro-content input,
        .medbeafgallery-pro-content select,
        .medbeafgallery-pro-content button,
        .medbeafgallery-pro-content textarea {
            cursor: not-allowed !important;
        }

        .medbeafgallery-additional-pair {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .medbeafgallery-additional-pair .medbeafgallery-pair-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    ');
}
add_action('admin_enqueue_scripts', 'medbeafgallery_case_admin_scripts');/**
 * Category image field functionality
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add image field to category add form
 */
function medbeafgallery_add_category_image_field() {
    ?>
    <div class="form-field term-image-wrap">
        <label for="medbeafgallery-category-image"><?php esc_html_e('Category Image', 'medical-before-after-gallery'); ?></label>
        <div class="medbeafgallery-category-image-container">
            <div id="medbeafgallery-category-image-preview"></div>
            <input type="hidden" id="medbeafgallery-category-image" name="medbeafgallery_category_image" value="" />
            <button type="button" class="button button-secondary" id="medbeafgallery-upload-category-image"><?php esc_html_e('Upload Image', 'medical-before-after-gallery'); ?></button>
            <button type="button" class="button button-secondary" id="medbeafgallery-remove-category-image" style="display:none"><?php esc_html_e('Remove Image', 'medical-before-after-gallery'); ?></button>
        </div>
        <p><?php esc_html_e('Upload an image for the category carousel. Recommended size: 120x120 pixels.', 'medical-before-after-gallery'); ?></p>
    </div>
    <?php
}
add_action('medbeafgallery_category_add_form_fields', 'medbeafgallery_add_category_image_field');

/**
 * Add image field to category edit form
 */
function medbeafgallery_edit_category_image_field($term) {
    // Get current image
    $image_id = get_term_meta($term->term_id, 'medbeafgallery_category_image', true);
    $image_url = '';

    if ($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
    }
    ?>
    <tr class="form-field term-image-wrap">
        <th scope="row"><label for="medbeafgallery-category-image"><?php esc_html_e('Category Image', 'medical-before-after-gallery'); ?></label></th>
        <td>
            <div class="medbeafgallery-category-image-container">
                <div id="medbeafgallery-category-image-preview">
                    <?php if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($term->name); ?>" />
                    <?php endif; ?>
                </div>
                <input type="hidden" id="medbeafgallery-category-image" name="medbeafgallery_category_image" value="<?php echo esc_attr($image_id); ?>" />
                <button type="button" class="button button-secondary" id="medbeafgallery-upload-category-image"><?php esc_html_e('Upload Image', 'medical-before-after-gallery'); ?></button>
                <button type="button" class="button button-secondary" id="medbeafgallery-remove-category-image" <?php echo empty($image_id) ? 'style="display:none"' : ''; ?>><?php esc_html_e('Remove Image', 'medical-before-after-gallery'); ?></button>
            </div>
            <p class="description"><?php esc_html_e('Upload an image for the category carousel. Recommended size: 120x120 pixels.', 'medical-before-after-gallery'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('medbeafgallery_category_edit_form_fields', 'medbeafgallery_edit_category_image_field');

/**
 * Enqueue media scripts for category image
 */
function medbeafgallery_category_admin_scripts() {
    $screen = get_current_screen();

    // Only load on category edit pages
    if ($screen->id !== 'edit-medbeafgallery_category') {
        return;
    }

    wp_enqueue_media();

    // Add custom script for handling the media uploader - depend on jquery instead of media-upload
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            var mediaFrame;

            // Handle the upload button click
            $(document).on("click", "#medbeafgallery-upload-category-image", function(e) {
                e.preventDefault();

                var button = $(this);
                var preview = $("#medbeafgallery-category-image-preview");
                var imageField = $("#medbeafgallery-category-image");
                var removeButton = $("#medbeafgallery-remove-category-image");

                // If the media frame already exists, reopen it
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                // Create the media frame
                mediaFrame = wp.media({
                    title: "Select or Upload Category Image",
                    button: {
                        text: "Use this image"
                    },
                    multiple: false,
                    library: {
                        type: "image"
                    }
                });

                // When an image is selected in the media frame...
                mediaFrame.on("select", function() {
                    // Get media attachment details
                    var attachment = mediaFrame.state().get("selection").first().toJSON();

                    // Create image element
                    var imgElement = $("<img>").attr({
                        "src": attachment.url,
                        "alt": attachment.alt || "",
                        "style": "max-width: 100%; max-height: 100%;"
                    });

                    // Set the image to the preview div
                    preview.html(imgElement);

                    // Set the image ID to the hidden input
                    imageField.val(attachment.id);

                    // Show the remove button
                    removeButton.show();
                });

                // Open the modal
                mediaFrame.open();
            });

            // Handle the remove button click
            $(document).on("click", "#medbeafgallery-remove-category-image", function(e) {
                e.preventDefault();

                var button = $(this);
                var preview = $("#medbeafgallery-category-image-preview");
                var imageField = $("#medbeafgallery-category-image");

                // Clear the preview
                preview.html("");

                // Clear the hidden input
                imageField.val("");

                // Hide the remove button
                button.hide();
            });
        });
    ');

    // Add inline CSS for the preview area
    wp_add_inline_style('wp-admin', '
        .medbeafgallery-category-image-container {
            margin-top: 10px;
            margin-bottom: 10px;
        }

        #medbeafgallery-category-image-preview {
            width: 120px;
            height: 120px;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #medbeafgallery-category-image-preview img {
            max-width: 100%;
            max-height: 100%;
            display: block;
        }

        #medbeafgallery-remove-category-image {
            margin-left: 5px;
        }
    ');
}
add_action('admin_enqueue_scripts', 'medbeafgallery_category_admin_scripts');

/**
 * Save category image field
 */
function medbeafgallery_save_category_image($term_id) {
    // Check if we have the category image data
    if (!isset($_POST['medbeafgallery_category_image'])) {
        return;
    }

    $nonce_valid = false;
    // For create action, check create nonce
    if (isset($_POST['_wpnonce_add-tag'])) {
        $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce_add-tag'])), 'add-tag');
    } elseif (isset($_POST['_wpnonce'])) {
        $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-tag_' . $term_id);
    }
    if (!$nonce_valid) {
        return;
    }

    // Check user permissions (must be able to edit terms)
    if (!current_user_can('manage_categories')) {
        return;
    }

    // Save the image
    $image_id = absint(wp_unslash($_POST['medbeafgallery_category_image']));
    update_term_meta($term_id, 'medbeafgallery_category_image', $image_id);
}
add_action('edited_medbeafgallery_category', 'medbeafgallery_save_category_image');
add_action('create_medbeafgallery_category', 'medbeafgallery_save_category_image');

