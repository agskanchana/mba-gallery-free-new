<?php
/**
 * Admin dashboard page for MEDBEAFGALLERY Gallery
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once MEDBEAFGALLERY_PATH . 'admin/dashboard/statistics.php';
require_once MEDBEAFGALLERY_PATH . 'admin/dashboard/help-guide.php';
require_once MEDBEAFGALLERY_PATH . 'admin/dashboard/settings.php'; // Add this line to include settings

/**
 * Display the admin dashboard page
 */
function medbeafgallery_admin_page() {
    // Initialize with safe defaults and error handling
    $stats = array();

    // Try to get statistics safely
    if (function_exists('medbeafgallery_get_dashboard_statistics')) {
        try {
            $stats = medbeafgallery_get_dashboard_statistics();
        } catch (Exception $e) {
            $stats = array();
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                call_user_func('error_log', 'MEDBEAFGALLERY Gallery Dashboard Statistics Error: ' . $e->getMessage());
            }
        }
    }

    // Try to process settings safely
    if (function_exists('medbeafgallery_process_settings')) {
        try {
            medbeafgallery_process_settings();
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                call_user_func('error_log', 'MEDBEAFGALLERY Gallery Settings Processing Error: ' . $e->getMessage());
            }
        }
    }

    // Get current settings
    $settings = get_option('medbeafgallery_settings', array(
        'cropping_enabled' => false,
        'cropping_size' => '800',
        'cropping_width' => '800',
        'cropping_height' => '600',
        'gallery_primary_color' => '#3498db',
        'category_display_mode' => 'carousel'
    ));
    ?>
    <div class="wrap medbeafgallery-admin-wrap">
        <h1><?php esc_html_e('Medical Before After Gallery Dashboard', 'medical-before-after-gallery'); ?></h1>

        <?php if ( ! apply_filters( 'medbeafgallery_has_valid_pro_license', false ) ) : ?>
        <!-- Small CTA Banner -->
        <div class="medbeafgallery-header-cta">
            <div class="header-cta-content">
                <div class="header-cta-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="header-cta-text">
                    <strong><?php esc_html_e('Upgrade to the Pro Add-on', 'medical-before-after-gallery'); ?></strong>
                    <span><?php esc_html_e('One-time payment • Lifetime access • Unlimited cases', 'medical-before-after-gallery'); ?></span>
                </div>
                <div class="header-cta-button">
                    <a href="https://medicalbeforeaftergallery.com/" target="_blank" class="button button-primary">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e('Get the Pro Add-on', 'medical-before-after-gallery'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="medbeafgallery-admin-header">
            <div class="medbeafgallery-admin-header-info">
                <h2><?php esc_html_e('Before & After Gallery Management', 'medical-before-after-gallery'); ?></h2>
                <p><?php esc_html_e('Manage your before and after gallery cases, categories, and settings.', 'medical-before-after-gallery'); ?></p>
            </div>
            <div class="medbeafgallery-admin-header-actions">
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=medbeafgallery_case')); ?>" class="button button-primary">
                    <?php esc_html_e('Add New Case', 'medical-before-after-gallery'); ?>
                </a>
                <a href="<?php echo esc_url(medbeafgallery_categories_admin_url()); ?>" class="button">
                    <?php esc_html_e('Manage Categories', 'medical-before-after-gallery'); ?>
                </a>
                <a href="https://buymeacoffee.com/wpplugindev" target="_blank" class="button medbeafgallery-coffee-button">
                    <span class="dashicons dashicons-heart"></span>
                    <?php esc_html_e('Love this plugin? Support us!', 'medical-before-after-gallery'); ?>
                </a>
            </div>
        </div>



        <div class="medbeafgallery-admin-content">
            <div class="medbeafgallery-admin-main">
                <?php
                // Display statistics boxes (1st)
                medbeafgallery_display_statistics($stats);

                // Display help guide (2nd)
                medbeafgallery_display_help_guide();

                // Display settings form (3rd)
                medbeafgallery_display_settings_form_main($settings);

                // Hook for Pro add-on to inject sections before the locked promo boxes
                do_action('medbeafgallery_dashboard_after_settings');

                // One compact card about the Pro add-on (only without a Pro licence).
                if ( ! apply_filters( 'medbeafgallery_has_valid_pro_license', false ) ) {
                    medbeafgallery_display_pro_overview();
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Process settings form submission including cropping settings
 */
function medbeafgallery_process_settings() {
    // Check if form was submitted
    if (isset($_POST['medbeafgallery_save_settings']) && check_admin_referer('medbeafgallery_settings_nonce')) {
        // Get current settings or set defaults
        $settings = get_option('medbeafgallery_settings', array());

        // Explicitly handling checkboxes - they're only included in $_POST when checked

        // Image cropping settings - IMPORTANT: Save correctly
        $settings['cropping_enabled'] = isset($_POST['cropping_enabled']) ? true : false;
        $settings['cropping_size'] = isset($_POST['cropping_size']) ? absint($_POST['cropping_size']) : 800;
        $settings['cropping_size'] = max(300, min(2000, $settings['cropping_size']));
        $fallback = $settings['cropping_size'];
        $settings['cropping_width']  = isset($_POST['cropping_width'])  ? max(1, min(4000, absint($_POST['cropping_width'])))  : $fallback;
        $settings['cropping_height'] = isset($_POST['cropping_height']) ? max(1, min(4000, absint($_POST['cropping_height']))) : $fallback;

        // Design settings. The Pro add-on's workspace moves these fields to its
        // own Design screens and saves them there (medbeafgallery_save_design_settings()).
        // The marker inside this form's Design section says the fields are in
        // the form, so a form without them never resets them to defaults.
        // (Pro add-ons before 1.6.0 leave the section in place, marker included.)
        if (isset($_POST['medbeafgallery_design_fields'])) {
            $settings = medbeafgallery_apply_design_settings($settings, array(
                'gallery_primary_color'     => isset($_POST['gallery_primary_color']) ? wp_unslash($_POST['gallery_primary_color']) : '',
                'category_display_mode'     => isset($_POST['category_display_mode']) ? wp_unslash($_POST['category_display_mode']) : 'carousel',
                'rollup_subcategory_counts' => isset($_POST['rollup_subcategory_counts']),
                'card_style'                => isset($_POST['card_style']) ? wp_unslash($_POST['card_style']) : 'split',
                'photo_backdrop'            => isset($_POST['photo_backdrop']) ? wp_unslash($_POST['photo_backdrop']) : 'blur',
            ));
        }

        // Destructive-uninstall opt-in. Stays false unless the owner ticks it.
        $settings['delete_data_on_uninstall'] = isset($_POST['delete_data_on_uninstall']);

        // The consultation-button settings were removed (free never rendered the
        // button and had no field for its link); drop values older versions saved.
        unset($settings['consultation_enabled'], $settings['consultation_url'], $settings['consultation_text']);

        // Add debug lines for cropping settings
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'MEDBEAFGALLERY Gallery: Cropping form submission - enabled: ' . (isset($_POST['cropping_enabled']) ? 'yes' : 'no'));
            call_user_func('error_log', 'MEDBEAFGALLERY Gallery: Final cropping setting saved: ' . ($settings['cropping_enabled'] ? 'enabled' : 'disabled'));
        }

        // Save updated settings
        update_option('medbeafgallery_settings', $settings);

        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            call_user_func('error_log', 'MEDBEAFGALLERY Gallery: Settings saved - Cropping enabled: ' . ($settings['cropping_enabled'] ? 'yes' : 'no'));
        }

        // Confirm the save. This runs from the dashboard page callback, after
        // admin_notices has already fired, so a hooked notice never appeared —
        // print it in place instead (WordPress moves notices to the top).
        if (did_action('admin_notices')) {
            medbeafgallery_settings_saved_notice();
        } else {
            add_action('admin_notices', 'medbeafgallery_settings_saved_notice');
        }
    }
}

/**
 * Display admin notice when settings are saved
 */
function medbeafgallery_settings_saved_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Gallery settings saved successfully!', 'medical-before-after-gallery'); ?></p>
    </div>
    <?php
}

/**
 * Display the settings form with added watermarking section
 */
function medbeafgallery_display_settings_form_main($settings) {
    // Show what is in effect. Callers pass get_option() with fallbacks of their
    // own (cropping off, 800 × 600, #3498db) that differ from what the gallery
    // and the case editor use for a setting that was never saved — so this
    // screen misreported those values, and saving it unchanged switched the
    // crop to 800 × 600 and the gallery colour from #2563eb to #3498db.
    $saved    = get_option('medbeafgallery_settings', array());
    $saved    = is_array($saved) ? $saved : array();
    $settings = array_merge((array) $settings, medbeafgallery_get_settings());
    $settings = wp_parse_args($settings, array(
        'category_display_mode' => 'carousel'
    ));
    // Until a width/height is saved, the case editor crops to
    // cropping_size × cropping_size (admin-functions.php).
    $crop_fallback = absint(isset($saved['cropping_size']) ? $saved['cropping_size'] : 800);
    $crop_fallback = $crop_fallback ? $crop_fallback : 800;
    if ( empty($saved['cropping_width']) )  { $settings['cropping_width']  = $crop_fallback; }
    if ( empty($saved['cropping_height']) ) { $settings['cropping_height'] = $crop_fallback; }
    ?>
    <div class="medbeafgallery-admin-box medbeafgallery-settings-form">
        <h2><?php esc_html_e('Gallery Settings', 'medical-before-after-gallery'); ?></h2>

        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=medbeafgallery-gallery')); ?>">
            <?php wp_nonce_field('medbeafgallery_settings_nonce'); ?>

            <!-- Updated Cropping Section -->
            <h3><?php esc_html_e('Image Cropping', 'medical-before-after-gallery'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Cropping', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <label for="cropping_enabled">
                            <input type="checkbox" name="cropping_enabled" id="cropping_enabled" value="1" <?php checked(!empty($settings['cropping_enabled'])); ?>>
                            <?php esc_html_e('Enable image cropping for before/after images', 'medical-before-after-gallery'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Shows a cropping tool on upload and saves every before/after image at the exact Crop Width × Crop Height below, so both images stay the same size and line up in the comparison slider (originals are never changed). Leave this off only if you always upload the before and after at identical dimensions — mismatched sizes can misalign the slider.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
                <tr class="medbeafgallery-crop-size-row">
                    <th scope="row"><?php esc_html_e('Crop Width', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <input type="number" name="cropping_width" id="cropping_width" min="1" max="4000" step="1" value="<?php echo esc_attr($settings['cropping_width']); ?>" class="small-text"> px
                        <p class="description"><?php esc_html_e('Output width for cropped images in pixels.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
                <tr class="medbeafgallery-crop-size-row">
                    <th scope="row"><?php esc_html_e('Crop Height', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <input type="number" name="cropping_height" id="cropping_height" min="1" max="4000" step="1" value="<?php echo esc_attr($settings['cropping_height']); ?>" class="small-text"> px
                        <p class="description"><?php esc_html_e('Output height for cropped images in pixels.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
            </table>
            <script>
            (function () {
                // Scope to THIS form copy, not the whole document. The Pro plugin
                // renders this settings form twice (free area + Pro tab) and then
                // removes the free copy, so getElementById() would bind to the
                // wrong (soon-removed) checkbox. currentScript keeps us local.
                var current = document.currentScript;
                var scope = current ? (current.closest('form') || current.parentNode) : document;
                var toggle = scope.querySelector('input[name="cropping_enabled"]');
                var rows = scope.querySelectorAll('.medbeafgallery-crop-size-row');
                if (!toggle || !rows.length) { return; }
                function sync() {
                    for (var i = 0; i < rows.length; i++) {
                        rows[i].style.display = toggle.checked ? '' : 'none';
                    }
                }
                toggle.addEventListener('change', sync);
                sync();
            })();
            </script>

            <hr>

            <!-- New Design Section -->
            <h3 class="medbeafgallery-design-heading"><?php esc_html_e('Design', 'medical-before-after-gallery'); ?></h3>
            <table class="form-table medbeafgallery-design-table">
                <tr>
                    <th scope="row">
                        <label for="gallery_primary_color"><?php esc_html_e('Gallery Color', 'medical-before-after-gallery'); ?></label>
                    </th>
                    <td>
                        <?php /* Tells the save handler this form carries the Design fields (see medbeafgallery_process_settings()). */ ?>
                        <input type="hidden" name="medbeafgallery_design_fields" value="1">
                        <input type="text" name="gallery_primary_color" id="gallery_primary_color"
                               value="<?php echo esc_attr($settings['gallery_primary_color'] ?? '#3498db'); ?>"
                               class="medbeafgallery-color-picker regular-text">
                        <div class="medbeafgallery-color-presets">
                            <p><strong>Quick Colors:</strong></p>
                            <div class="medbeafgallery-preset-colors">
                                <button type="button" class="medbeafgallery-preset-color" data-color="#3498db" style="background: #3498db" title="Blue"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="#e74c3c" style="background: #e74c3c" title="Red"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="#2ecc71" style="background: #2ecc71" title="Green"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="#f39c12" style="background: #f39c12" title="Orange"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="#9b59b6" style="background: #9b59b6" title="Purple"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="#1abc9c" style="background: #1abc9c" title="Teal"></button>
                            </div>
                            <p><strong>Gradients:</strong></p>
                            <div class="medbeafgallery-preset-gradients">
                                <button type="button" class="medbeafgallery-preset-color" data-color="linear-gradient(135deg, #667eea 0%, #764ba2 100%)" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)" title="Purple Blue"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%)" title="Pink Red"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)" title="Blue Cyan"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)" title="Green Teal"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="linear-gradient(135deg, #fa709a 0%, #fee140 100%)" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%)" title="Pink Yellow"></button>
                                <button type="button" class="medbeafgallery-preset-color" data-color="linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)" title="Mint Pink"></button>
                            </div>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Choose the main color for your gallery. Supports solid colors (hex codes) and gradients. This affects buttons, headings, and other elements throughout the gallery.', 'medical-before-after-gallery'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="category_display_mode"><?php esc_html_e('Category Navigation', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <?php $current_category_mode = medbeafgallery_get_category_display_mode(); ?>
                        <select name="category_display_mode" id="category_display_mode">
                            <?php foreach (medbeafgallery_category_display_modes() as $mode_key => $mode_label) : ?>
                                <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($current_category_mode, $mode_key); ?>><?php echo esc_html($mode_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php
                            printf(
                                /* translators: %s: example shortcode attribute */
                                esc_html__('How visitors choose a category above the gallery. One gallery can use a different style with %s.', 'medical-before-after-gallery'),
                                '<code>category_style="buttons"</code>'
                            );
                        ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="card_style"><?php esc_html_e('Card Style', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select name="card_style" id="card_style">
                            <option value="split" <?php selected($settings['card_style'] ?? 'split', 'split'); ?>><?php esc_html_e('Side by side — before and after halves', 'medical-before-after-gallery'); ?></option>
                            <option value="reveal" <?php selected($settings['card_style'] ?? 'split', 'reveal'); ?>><?php esc_html_e('Hover reveal — the before photo wipes to the after photo', 'medical-before-after-gallery'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Hover reveal shows each card\'s full before photo. Pointing at a card (or pressing and holding it on a phone) wipes to the after photo, and every card plays the wipe once when it first scrolls into view. Visitors who ask their device for reduced motion see no animation. Applies to the classic grid layout.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="photo_backdrop"><?php esc_html_e('Case Window Background', 'medical-before-after-gallery'); ?></label></th>
                    <td>
                        <select name="photo_backdrop" id="photo_backdrop">
                            <option value="blur" <?php selected($settings['photo_backdrop'] ?? 'blur', 'blur'); ?>><?php esc_html_e('Blurred copy of the photo', 'medical-before-after-gallery'); ?></option>
                            <option value="plain" <?php selected($settings['photo_backdrop'] ?? 'blur', 'plain'); ?>><?php esc_html_e('Plain black', 'medical-before-after-gallery'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('What fills the space around a photo that does not fill the case window, instead of black bars.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Category Counts', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <label for="rollup_subcategory_counts">
                            <input type="checkbox" name="rollup_subcategory_counts" id="rollup_subcategory_counts" value="1" <?php checked(!empty($settings['rollup_subcategory_counts'])); ?>>
                            <?php esc_html_e('Count subcategory cases toward their parent category', 'medical-before-after-gallery'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('A category tile normally counts only the cases assigned to it directly, so a parent whose cases all sit in subcategories shows "0" — even though clicking it does list those cases. Turn this on to show the subtree total instead, matching what visitors actually see when they open the category. This changes the numbers on your category tiles.', 'medical-before-after-gallery'); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h3><?php esc_html_e('Data &amp; Uninstall', 'medical-before-after-gallery'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('On Uninstall', 'medical-before-after-gallery'); ?></th>
                    <td>
                        <label for="delete_data_on_uninstall">
                            <input type="checkbox" name="delete_data_on_uninstall" id="delete_data_on_uninstall" value="1" <?php checked(!empty($settings['delete_data_on_uninstall'])); ?>>
                            <?php esc_html_e('Also delete all cases, categories and their images when this plugin is deleted', 'medical-before-after-gallery'); ?>
                        </label>
                        <p class="description">
                            <strong><?php esc_html_e('Leave this off unless you are sure.', 'medical-before-after-gallery'); ?></strong>
                            <?php esc_html_e('When off, deleting the plugin removes only its settings — every case and category is left untouched, so reinstalling restores your gallery. When on, deleting the plugin permanently erases your entire case library. This cannot be undone.', 'medical-before-after-gallery'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="medbeafgallery_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'medical-before-after-gallery'); ?>">
            </p>
        </form>
    </div>


    <?php
    // Add watermark refresh button
    // medbeafgallery_add_watermark_refresh_button();
}



/**
 * Diagnostic function to help troubleshoot dashboard issues
 */
function medbeafgallery_dashboard_diagnostic() {
    $diagnostics = array();

    // Check if required functions exist
    $diagnostics['functions'] = array(
        'medbeafgallery_is_premium_active' => function_exists('medbeafgallery_is_premium_active'),
        'medbeafgallery_get_dashboard_statistics' => function_exists('medbeafgallery_get_dashboard_statistics'),
        'medbeafgallery_process_settings' => function_exists('medbeafgallery_process_settings')
    );

    // Check license status
    if (function_exists('medbeafgallery_is_premium_active')) {
        $diagnostics['license_active'] = medbeafgallery_is_premium_active();
    }

    return $diagnostics;
}

/**
 * One compact "Pro add-on" card for free users, in place of the long promo
 * banner and the seven greyed-out Pro settings forms this page used to carry.
 * Nothing here is a disabled copy of a Pro control: it lists what Pro adds and
 * links to the Pro Features page.
 */
function medbeafgallery_display_pro_overview() {
    $features = array(
        array('dashicons-images-alt2', __('Unlimited cases and categories', 'medical-before-after-gallery')),
        array('dashicons-filter', __('Case filters, search, sort and doctor filter', 'medical-before-after-gallery')),
        array('dashicons-welcome-view-site', __('Masonry layout and five case-window styles', 'medical-before-after-gallery')),
        array('dashicons-chart-line', __('Progress timeline with dated photos', 'medical-before-after-gallery')),
        array('dashicons-location', __('"Find by area" body map and "Help me choose"', 'medical-before-after-gallery')),
        array('dashicons-camera', __('Ghost camera, photo line-up tool and transformation reels', 'medical-before-after-gallery')),
        array('dashicons-art', __('Watermarks that never touch your originals', 'medical-before-after-gallery')),
        array('dashicons-admin-comments', __('AI write-ups and alt text (WordPress 7)', 'medical-before-after-gallery')),
    );
    ?>
    <div class="medbeafgallery-admin-box medbeafgallery-pro-overview">
        <div class="medbeafgallery-pro-overview-head">
            <div>
                <h2><?php esc_html_e('Do more with the Pro add-on', 'medical-before-after-gallery'); ?></h2>
                <p><?php esc_html_e('One-time payment, lifetime updates. Your cases and settings carry over.', 'medical-before-after-gallery'); ?></p>
            </div>
            <div class="medbeafgallery-pro-overview-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=medbeafgallery-gallery-pro-features')); ?>" class="button"><?php esc_html_e('See all Pro features', 'medical-before-after-gallery'); ?></a>
                <a href="https://medicalbeforeaftergallery.com/" target="_blank" rel="noopener noreferrer" class="button button-primary"><?php esc_html_e('Get the Pro add-on', 'medical-before-after-gallery'); ?></a>
            </div>
        </div>
        <ul class="medbeafgallery-pro-overview-grid">
            <?php foreach ($features as $feature) : ?>
                <li><span class="dashicons <?php echo esc_attr($feature[0]); ?>" aria-hidden="true"></span><?php echo esc_html($feature[1]); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}