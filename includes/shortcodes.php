<?php
/**
 * Register shortcode for displaying the Medical Before-After Gallery
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register shortcode for displaying the complete gallery with categories and filters
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output of the gallery.
 */
function medbeafgallery_shortcode($atts) {
    // Normalize attribute keys to lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    // Default attributes - core features for free version
    $default_atts = array(
        'items_per_page' => 6,
        'default_category' => 'all',
        // 'false' hides the category bar and (with Pro) the filter sidebar/drawer.
        'show_filters' => 'true',
        // '' = Design → Card Style; 'split', 'reveal' or 'refined' for this gallery only.
        'card_style' => '',
        // '' = Design → Category Navigation; carousel|grid|buttons|dropdown (Pro
        // adds its own) for this gallery only.
        'category_style' => ''
    );

    // The third argument runs the core `shortcode_atts_medbeafgallery` filter, so an
    // add-on can declare attributes of its own.
    $atts = shortcode_atts($default_atts, $atts, 'medbeafgallery');
    $show_filters = !in_array(strtolower((string) $atts['show_filters']), array('false', '0', 'no', 'off'), true);

    $settings       = medbeafgallery_get_settings();
    $card_style     = in_array($atts['card_style'], array('split', 'reveal', 'refined'), true)
        ? $atts['card_style']
        : (isset($settings['card_style']) && in_array($settings['card_style'], array('reveal', 'refined'), true) ? $settings['card_style'] : 'split');
    // "Refined" is the hover reveal in another dress (portrait photos, no boxes):
    // scripts and add-ons keep seeing 'reveal', the stylesheet gets its own class.
    $refined_cards = 'refined' === $card_style;
    if ($refined_cards) {
        $card_style = 'reveal';
    }
    $photo_backdrop = (isset($settings['photo_backdrop']) && $settings['photo_backdrop'] === 'plain') ? 'plain' : 'blur';
    $category_mode  = medbeafgallery_get_category_display_mode($atts['category_style']);

    // Expose this gallery's attributes to add-ons while it renders
    // (medbeafgallery_get_current_shortcode_atts()): set first, so the
    // shortcode_rendered action, the config filter and the
    // before_gallery_main action all see them.
    medbeafgallery_set_current_shortcode_atts($atts);

    // Enqueue necessary scripts and styles - optimized loading
    wp_enqueue_style('medbeafgallery-css', MEDBEAFGALLERY_URL . 'assets/css/gallery.css', array(), MEDBEAFGALLERY_VERSION);
    wp_enqueue_script('cocoen-js', MEDBEAFGALLERY_URL . 'assets/vendor/cocoen/cocoen.min.js', array(), '3.2.0', true);
    wp_enqueue_script('medbeafgallery-js', MEDBEAFGALLERY_URL . 'assets/js/gallery.js', array('jquery', 'cocoen-js'), MEDBEAFGALLERY_VERSION, true);

    // Mark that gallery shortcode was rendered for fallback CSS detection
    do_action('medbeafgallery_shortcode_rendered');

    // Ensure custom colors are output (this will be called by wp_head hook)
    // add_action('wp_footer', 'medbeafgallery_output_custom_colors', 5);

    // Localize script with configuration data
    $gallery_config = array(
        'restUrl' => rest_url('medical-before-after-gallery/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'itemsPerPage' => intval($atts['items_per_page']),
        'defaultCategory' => esc_attr($atts['default_category']),
        'showFilters' => $show_filters ? 'true' : 'false',
        'cardStyle' => $card_style,
        'photoBackdrop' => $photo_backdrop,
        'beforeText' => __('Before', 'medical-before-after-gallery'),
        'afterText' => __('After', 'medical-before-after-gallery'),
        'treatedByText' => __('Treated by', 'medical-before-after-gallery'),
        'categoryDisplayMode' => $category_mode,
        'galleryEndpoint' => 'gallery-data',
        'categoriesEndpoint' => 'categories',
        'restBase' => esc_url_raw(rest_url('medical-before-after-gallery/v1')),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'pluginUrl' => MEDBEAFGALLERY_URL,
        // Every category's subcategories, from the whole tree. gallery.js used to
        // derive this from /categories, which the free plan caps at four
        // categories, so on a site over that limit (a lapsed Pro licence) a parent
        // tile lost the cases filed under any subcategory outside those four.
        'categoryDescendants' => medbeafgallery_category_descendants_map() ?: new stdClass(),
    );

    $gallery_config = apply_filters('medbeafgallery_gallery_config', $gallery_config);
    wp_localize_script('medbeafgallery-js', 'medbeafgalleryGalleryConfig', $gallery_config);

    // Start output buffering
    ob_start();

    $container_classes = array('medbeafgallery-container');
    if (!$show_filters) {
        $container_classes[] = 'medbeafgallery-no-filters';
    }
    if ($card_style === 'reveal') {
        $container_classes[] = 'medbeafgallery-cards-reveal';
    }
    if ($refined_cards) {
        $container_classes[] = 'medbeafgallery-cards-refined';
    }
    /**
     * Filter the gallery root's classes. Lets an add-on style the gallery from
     * the first paint instead of adding a class from a script later.
     *
     * @since 1.5.0
     * @param string[] $container_classes Class names.
     * @param array    $atts              This gallery's shortcode attributes.
     */
    $container_classes = apply_filters('medbeafgallery_container_classes', $container_classes, $atts);
    $container_classes = array_filter(array_map('sanitize_html_class', (array) $container_classes));
    if (!in_array('medbeafgallery-container', $container_classes, true)) {
        array_unshift($container_classes, 'medbeafgallery-container');
    }

    ?>
    <div class="<?php echo esc_attr(implode(' ', array_unique($container_classes))); ?>" id="medbeafgallery-gallery-container">
        <?php /* Gallery content */ ?>
        <div class="medbeafgallery-gallery-content" id="medbeafgallery-gallery-content">
            <?php
            if ($show_filters) {
                echo wp_kses(medbeafgallery_render_category_carousel($category_mode, $atts['default_category']), medbeafgallery_allowed_svg_html());
            }
            ?>

            <div class="medbeafgallery-gallery-layout">
                <?php do_action('medbeafgallery_before_gallery_main'); ?>
                <?php /* Main Content Area */ ?>
                <main class="medbeafgallery-main-content">
                    <?php /* Active Filter Tags */ ?>
                    <div class="medbeafgallery-active-filters" style="display:none;">
                        <div id="medbeafgallery-filter-tags" class="medbeafgallery-filter-tags"></div>
                        <button type="button" id="medbeafgallery-clear-filters" class="medbeafgallery-clear-filters" style="display:none;"><?php esc_html_e('Clear All', 'medical-before-after-gallery'); ?></button>
                    </div>
                    <?php /* Grid Container for Gallery Items */ ?>











                    <?php /* Gallery Section */ ?>
                    <div class="medbeafgallery-gallery-container"
                         data-category="<?php echo esc_attr($atts['default_category']); ?>"
                         data-per-page="<?php echo esc_attr($atts['items_per_page']); ?>">
                        <?php
                        /*
                         * Skeleton placeholders rather than a bare spinner: they are
                         * server-rendered, so the gallery has shape before any script
                         * runs, and the page does not jump when the real cards land.
                         *
                         * Deliberately self-contained inside the loading indicator and
                         * not injected into the real grid — Pro re-lays that grid out
                         * as CSS columns, and stray children would be laid out too.
                         */
                        $skeleton_count = max(1, min(6, (int) $atts['items_per_page']));
                        ?>
                        <div class="medbeafgallery-loading-indicator">
                            <div class="medbeafgallery-skeleton-grid" aria-hidden="true">
                                <?php for ($i = 0; $i < $skeleton_count; $i++) : ?>
                                    <div class="medbeafgallery-skeleton-card">
                                        <div class="medbeafgallery-skeleton medbeafgallery-skeleton-image"></div>
                                        <div class="medbeafgallery-skeleton medbeafgallery-skeleton-line medbeafgallery-skeleton-title"></div>
                                        <div class="medbeafgallery-skeleton medbeafgallery-skeleton-line"></div>
                                        <div class="medbeafgallery-skeleton medbeafgallery-skeleton-line medbeafgallery-skeleton-short"></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <p class="screen-reader-text" role="status"><?php esc_html_e('Loading gallery…', 'medical-before-after-gallery'); ?></p>
                        </div>
                        <div class="medbeafgallery-gallery-grid" id="medbeafgallery-gallery-grid" style="display: none;">
                            <?php /* Gallery items will be loaded via JavaScript */ ?>
                        </div>
                        <div id="medbeafgallery-no-results" class="medbeafgallery-no-results" style="display: none;">
                            <p>No results found. Please try different filter criteria.</p>
                        </div>
                    </div>

                    <?php /* Pagination */ ?>
                    <div class="medbeafgallery-pagination">
                        <button type="button" id="medbeafgallery-load-more" class="medbeafgallery-load-more" style="display: none;"><?php esc_html_e('Load More', 'medical-before-after-gallery'); ?></button>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <?php /* Modal for enlarged view */ ?>
    <div id="medbeafgallery-modal" class="medbeafgallery-modal<?php echo $photo_backdrop === 'blur' ? ' medbeafgallery-backdrop-blur' : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="medbeafgallery-case-title" aria-hidden="true" tabindex="-1">
        <div class="medbeafgallery-modal-content">
            <div class="medbeafgallery-modal-header">
                <h2 id="medbeafgallery-case-title">Case Study Title</h2>

                <?php /* Image Controls moved to header */ ?>
                <div class="medbeafgallery-image-controls" role="group" aria-label="<?php esc_attr_e('Image comparison view', 'medical-before-after-gallery'); ?>">
                    <button type="button" class="medbeafgallery-control-btn medbeafgallery-split-view active" aria-pressed="true" title="<?php esc_attr_e('Slider view', 'medical-before-after-gallery'); ?>" aria-label="<?php esc_attr_e('Slider view', 'medical-before-after-gallery'); ?>">
                        <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="3" x2="12" y2="21"></line><path d="M8 8l-4 4 4 4"></path><path d="M16 16l4-4-4-4"></path></svg><span class="medbeafgallery-control-label" aria-hidden="true"><?php esc_html_e('Slider', 'medical-before-after-gallery'); ?></span>
                    </button>
                    <button type="button" class="medbeafgallery-control-btn medbeafgallery-before-view" aria-pressed="false" title="<?php esc_attr_e('Before view', 'medical-before-after-gallery'); ?>" aria-label="<?php esc_attr_e('Before view', 'medical-before-after-gallery'); ?>">
                        <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2"></rect><rect x="8" y="9" width="8" height="8"></rect></svg><span class="medbeafgallery-control-label" aria-hidden="true"><?php esc_html_e('Before', 'medical-before-after-gallery'); ?></span>
                    </button>
                    <button type="button" class="medbeafgallery-control-btn medbeafgallery-after-view" aria-pressed="false" title="<?php esc_attr_e('After view', 'medical-before-after-gallery'); ?>" aria-label="<?php esc_attr_e('After view', 'medical-before-after-gallery'); ?>">
                        <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2"></rect><rect x="8" y="9" width="8" height="8"></rect><circle cx="12" cy="13" r="2"></circle></svg><span class="medbeafgallery-control-label" aria-hidden="true"><?php esc_html_e('After', 'medical-before-after-gallery'); ?></span>
                    </button>
                </div>

                <span class="medbeafgallery-pair-info medbeafgallery-pair-description" aria-live="polite">Main View</span>

                <button type="button" class="medbeafgallery-modal-close" aria-label="<?php esc_attr_e('Close', 'medical-before-after-gallery'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="medbeafgallery-modal-body">
                <div class="medbeafgallery-comparison-container">
                    <?php /* Image Pairs Navigation */ ?>
                    <div class="medbeafgallery-image-pairs-nav" role="group" aria-label="<?php esc_attr_e('Image pairs', 'medical-before-after-gallery'); ?>">
                        <button type="button" class="medbeafgallery-pair-nav medbeafgallery-pair-prev" title="<?php esc_attr_e('Previous image pair', 'medical-before-after-gallery'); ?>" aria-label="<?php esc_attr_e('Previous image pair', 'medical-before-after-gallery'); ?>">
                            <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <div class="medbeafgallery-pair-indicators">
                            <?php /* Will be populated dynamically */ ?>
                        </div>
                        <button type="button" class="medbeafgallery-pair-nav medbeafgallery-pair-next" title="<?php esc_attr_e('Next image pair', 'medical-before-after-gallery'); ?>" aria-label="<?php esc_attr_e('Next image pair', 'medical-before-after-gallery'); ?>">
                            <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>

                    <?php /* Image Sets Container */ ?>
                    <div class="medbeafgallery-image-sets-container">
                        <div class="medbeafgallery-before-after-wrapper active" data-pair-id="1">
                            <?php /* Content populated dynamically by showImagePair using Cocoen slider */ ?>
                        </div>
                    </div>

                    <?php /* Image View Labels */ ?>
                    <div class="medbeafgallery-labels">
                        <div class="medbeafgallery-before-label">Before</div>
                        <div class="medbeafgallery-after-label">After</div>
                    </div>
                </div>

                <?php /* Case Details Panel (Pro feature — hidden unless Pro add-on is active) */ ?>
                <?php if ( apply_filters( 'medbeafgallery_show_details_panel', false ) ) : ?>
                <div class="medbeafgallery-case-details">
                    <div class="medbeafgallery-case-tabs" role="tablist" aria-label="<?php esc_attr_e('Case information', 'medical-before-after-gallery'); ?>">
                        <button type="button" class="medbeafgallery-tab active" data-tab="description" role="tab" id="medbeafgallery-tab-description" aria-controls="medbeafgallery-panel-description" aria-selected="true"><?php esc_html_e('Description', 'medical-before-after-gallery'); ?></button>
                        <button type="button" class="medbeafgallery-tab" data-tab="details" role="tab" id="medbeafgallery-tab-details" aria-controls="medbeafgallery-panel-details" aria-selected="false" tabindex="-1"><?php esc_html_e('Details', 'medical-before-after-gallery'); ?></button>
                    </div>
                    <div class="medbeafgallery-tab-content active" data-tab="description" role="tabpanel" id="medbeafgallery-panel-description" aria-labelledby="medbeafgallery-tab-description" tabindex="0">
                        <div id="medbeafgallery-case-description"></div>
                    </div>
                    <div class="medbeafgallery-tab-content" data-tab="details" role="tabpanel" id="medbeafgallery-panel-details" aria-labelledby="medbeafgallery-tab-details" tabindex="0">
                        <div class="medbeafgallery-detail-grid">
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-category" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Category', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-category"></span>
                            </div>
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-gender" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Gender', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-gender"></span>
                            </div>
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-age" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Age', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-age"></span>
                            </div>
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-recovery" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Recovery', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-recovery"></span>
                            </div>
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-duration" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Duration', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-duration"></span>
                            </div>
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-results" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Results', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-results"></span>
                            </div>
                            <div class="medbeafgallery-detail-item" id="medbeafgallery-detail-procedure" style="display:none;">
                                <span class="medbeafgallery-detail-label"><?php esc_html_e('Procedure', 'medical-before-after-gallery'); ?></span>
                                <span class="medbeafgallery-detail-value" id="medbeafgallery-case-procedure"></span>
                            </div>
                        </div>
                    </div>
                    <div class="medbeafgallery-cta-container">
                        <div class="medbeafgallery-social-share">
                            <span><?php esc_html_e('Share:', 'medical-before-after-gallery'); ?></span>
                            <button type="button" class="medbeafgallery-share-btn medbeafgallery-share-facebook" aria-label="<?php esc_attr_e('Share on Facebook', 'medical-before-after-gallery'); ?>">
                                <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                            </button>
                            <button type="button" class="medbeafgallery-share-btn medbeafgallery-share-twitter" aria-label="<?php esc_attr_e('Share on Twitter', 'medical-before-after-gallery'); ?>">
                                <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
                            </button>
                            <button type="button" class="medbeafgallery-share-btn medbeafgallery-share-email" aria-label="<?php esc_attr_e('Share via Email', 'medical-before-after-gallery'); ?>">
                                <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                <?php /* Doctor who treated the case (filled by gallery.js; Pro shows it in its details panel instead) */ ?>
                <div class="medbeafgallery-doctor-strip" id="medbeafgallery-doctor-strip" hidden></div>
                <?php endif; ?>
            </div>

            <?php /* Modal Navigation */ ?>
            <div class="medbeafgallery-modal-nav">
                <div class="medbeafgallery-modal-nav-container">
                    <?php /* The bar makes case navigation distinguishable from the image-pair
                             arrows above, which are plain chevrons. Both pairs are on screen
                             at once and an identical icon on each made them ambiguous. */ ?>
                    <button type="button" class="medbeafgallery-modal-prev" aria-label="<?php esc_attr_e('Previous case', 'medical-before-after-gallery'); ?>">
                        <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 18 10 12 16 6"></polyline>
                            <line x1="6" y1="6" x2="6" y2="18"></line>
                        </svg>
                    </button>

                    <div class="medbeafgallery-modal-counter-wrapper">
                        <div class="medbeafgallery-modal-counter" aria-live="polite" aria-atomic="true">
                            <span class="screen-reader-text"><?php esc_html_e('Case', 'medical-before-after-gallery'); ?> </span>
                            <span id="medbeafgallery-current-item" class="medbeafgallery-counter-current">1</span>
                            <span class="medbeafgallery-counter-separator" aria-hidden="true">/</span>
                            <span class="screen-reader-text"><?php esc_html_e('of', 'medical-before-after-gallery'); ?> </span>
                            <span id="medbeafgallery-total-items" class="medbeafgallery-counter-total">1</span>
                        </div>
                    </div>

                    <button type="button" class="medbeafgallery-modal-next" aria-label="<?php esc_attr_e('Next case', 'medical-before-after-gallery'); ?>">
                        <svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="8 18 14 12 8 6"></polyline>
                            <line x1="18" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php

    // Return the buffered content
    return ob_get_clean();
}
add_shortcode('medbeafgallery', 'medbeafgallery_shortcode');

/**
 * Remember the attributes of the gallery shortcode that is rendering.
 *
 * @param array $atts Normalised shortcode attributes.
 */
function medbeafgallery_set_current_shortcode_atts($atts) {
    $GLOBALS['medbeafgallery_current_shortcode_atts'] = is_array($atts) ? $atts : array();
}

/**
 * The attributes of the gallery shortcode on this page, after defaults.
 *
 * Add-ons call this from the medbeafgallery_before_gallery_main action (or later)
 * to honour per-gallery attributes such as show_filters. Empty before any
 * gallery has rendered.
 *
 * @return array
 */
function medbeafgallery_get_current_shortcode_atts() {
    return isset($GLOBALS['medbeafgallery_current_shortcode_atts']) && is_array($GLOBALS['medbeafgallery_current_shortcode_atts'])
        ? $GLOBALS['medbeafgallery_current_shortcode_atts']
        : array();
}

/**
 * Render the category carousel
 */
function medbeafgallery_render_category_carousel($display_mode = 'grid', $default_category = null) {
    // Use the existing function from utilities.php to get categories
    $categories = medbeafgallery_get_categories_with_images();

    // The tile to highlight: the shortcode's default_category when given (so it
    // matches the category gallery.js starts on), else the saved setting, else 'all'.
    if ($default_category === null || $default_category === '') {
        $gallery_config = get_option('medbeafgallery_settings', array());
        $default_category = isset($gallery_config['default_category']) ? $gallery_config['default_category'] : 'all';
    }

    // Filter for PARENT categories only
    $processed_categories = array();
    $processed_slugs = array(); // Track processed slugs to prevent duplicates

    foreach ($categories as $category) {
        // Skip if this category slug has already been processed
        if (in_array($category['slug'], $processed_slugs)) {
            continue;
        }

        // Always include 'all' category
        if ($category['slug'] === 'all') {
            $processed_categories[] = $category;
            $processed_slugs[] = $category['slug'];
            continue;
        }

        // Get the full term to check if it's a parent (has no parent itself)
        $term = get_term_by('slug', $category['slug'], 'medbeafgallery_category');
        if ($term && $term->parent == 0) {
            // Every term here already has cases on itself or beneath it:
            // get_terms() (hide_empty, hierarchical) keeps an empty parent only
            // when a descendant has cases. Its own count is 0 when every case is
            // filed under a subcategory, and testing that count hid the tile —
            // leaving those cases unreachable from the category bar.
            $processed_categories[] = $category;
            $processed_slugs[] = $category['slug'];
        }
    }

    $display_mode = sanitize_key((string) $display_mode);
    if ($display_mode === '') {
        $display_mode = 'grid';
    }

    /**
     * Filters the categories shown in the category bar (top-level only, "All" first).
     * Each item: term_id, name, slug, count, image_url; an optional 'meta' string
     * is printed under the name.
     *
     * @param array  $processed_categories Items.
     * @param string $display_mode         Category bar style.
     */
    $filtered = apply_filters('medbeafgallery_category_nav_items', $processed_categories, $display_mode);
    if (is_array($filtered)) {
        $processed_categories = $filtered;
    }

    // Each style is built on one of four structures. A style an add-on registers
    // picks the structure it restyles (Pro: tabs on the carousel, photo pills on
    // the buttons); unknown styles fall back to the carousel, the long-standing look.
    $structures = array('carousel' => 'carousel', 'grid' => 'grid', 'buttons' => 'buttons', 'dropdown' => 'dropdown');
    $structure  = isset($structures[$display_mode])
        ? $structures[$display_mode]
        : sanitize_key((string) apply_filters('medbeafgallery_category_nav_structure', 'carousel', $display_mode));
    if (!isset($structures[$structure])) {
        $structure = 'carousel';
    }

    $classes = array(
        'medbeafgallery-category-carousel',
        'medbeafgallery-' . $structure . '-mode',
        'medbeafgallery-cat-mode-' . $display_mode,
    );
    /**
     * Filters the category bar's classes.
     *
     * @param string[] $classes      Classes.
     * @param string   $display_mode Category bar style.
     */
    $classes = apply_filters('medbeafgallery_category_nav_classes', $classes, $display_mode);
    $carousel_class = implode(' ', array_map('sanitize_html_class', array_filter((array) $classes)));

    // One drop-down per page id-wise; a second gallery on the page gets its own.
    static $select_index = 0;
    $select_id = 'medbeafgallery-category-select' . ($select_index++ ? '-' . $select_index : '');

    ob_start();
    ?>
    <div class="<?php echo esc_attr($carousel_class); ?>" data-display-mode="<?php echo esc_attr($display_mode); ?>">
        <?php if ($structure === 'dropdown' && !empty($processed_categories)) : ?>
            <div class="medbeafgallery-category-select-wrap">
                <label class="medbeafgallery-category-select-label" for="<?php echo esc_attr($select_id); ?>"><?php esc_html_e('Browse by category', 'medical-before-after-gallery'); ?></label>
                <span class="medbeafgallery-category-select-shell">
                    <select class="medbeafgallery-category-select" id="<?php echo esc_attr($select_id); ?>">
                        <?php foreach ($processed_categories as $category) :
                            $option_active = ($category['slug'] === $default_category) ||
                                ($default_category === 'ba_category_all' && $category['slug'] === 'all') ||
                                ($default_category === 'all' && $category['slug'] === 'ba_category_all');
                            ?>
                            <option value="<?php echo esc_attr($category['slug']); ?>"<?php echo $option_active ? ' selected="selected"' : ''; ?>><?php echo esc_html($category['slug'] === 'all' ? __('All categories', 'medical-before-after-gallery') : $category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="medbeafgallery-category-select-icon" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </span>
            </div>
        <?php endif; ?>
        <div class="medbeafgallery-nav-buttons">
            <button type="button" class="medbeafgallery-nav-btn medbeafgallery-prev-btn" aria-label="<?php esc_attr_e('Scroll categories left', 'medical-before-after-gallery'); ?>">
                <span class="medbeafgallery-arrow-left" aria-hidden="true">&#8592;</span>
            </button>
            <button type="button" class="medbeafgallery-nav-btn medbeafgallery-next-btn" aria-label="<?php esc_attr_e('Scroll categories right', 'medical-before-after-gallery'); ?>">
                <span class="medbeafgallery-arrow-right" aria-hidden="true">&#8594;</span>
            </button>
        </div>

        <div class="medbeafgallery-carousel-wrapper" id="medbeafgallery-carousel-wrapper">
            <div class="medbeafgallery-carousel-items" id="medbeafgallery-carousel-items">
                <?php
                // Check if categories exist
                if (!empty($processed_categories)) {
                    foreach ($processed_categories as $category) {
                        // Determine if this category should be active
                        $is_active = ($category['slug'] === $default_category) ||
                                    ($default_category === 'ba_category_all' && $category['slug'] === 'all') ||
                                    ($default_category === 'all' && $category['slug'] === 'ba_category_all');

                        // Determine the image HTML
                        $image_html = '';

                        // For "All" category, use the default image if no specific image is set
                        if ($category['slug'] === 'all') {
                            // First check if there's an image URL already set - remove var_dump
                            $image_url = isset($category['image_url']) && !empty($category['image_url']) ? $category['image_url'] :
                                        (isset($category['imageUrl']) && !empty($category['imageUrl']) ? $category['imageUrl'] : '');

                            // If no image URL is set, we'll use a custom SVG in the image_html directly
                            if (empty($image_url)) {
                                // Create a special SVG for the All category
                                $image_html = '
                                    <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 600 600" class="medbeafgallery-category-svg-placeholder medbeafgallery-all-category-icon">
                                        <rect width="600" height="600" fill="#1E88E5" />
                                        <g transform="translate(150, 150)">
                                            <rect x="0" y="0" width="90" height="90" rx="8" fill="white" opacity="0.9" />
                                            <rect x="110" y="0" width="90" height="90" rx="8" fill="white" opacity="0.6" />
                                            <rect x="220" y="0" width="90" height="90" rx="8" fill="white" opacity="0.9" />
                                            <rect x="0" y="110" width="90" height="90" rx="8" fill="white" opacity="0.6" />
                                            <rect x="110" y="110" width="90" height="90" rx="8" fill="white" opacity="0.9" />
                                            <rect x="220" y="110" width="90" height="90" rx="8" fill="white" opacity="0.6" />
                                            <rect x="0" y="220" width="90" height="90" rx="8" fill="white" opacity="0.9" />
                                            <rect x="110" y="220" width="90" height="90" rx="8" fill="white" opacity="0.6" />
                                            <rect x="220" y="220" width="90" height="90" rx="8" fill="white" opacity="0.9" />

                                            <text x="150" y="150" font-family="Arial, sans-serif" font-size="80"
                                                  font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle">ALL</text>
                                        </g>
                                    </svg>
                                ';
                            }
                        } else {
                            // For other categories, use the normal logic
                            $image_url = isset($category['image_url']) ? $category['image_url'] :
                                        (isset($category['imageUrl']) ? $category['imageUrl'] : '');
                        }

                        // Only process image URL if we haven't set image_html directly already
                        if (empty($image_html)) {
                            // Check if it's a data URI SVG image
                            if (!empty($image_url) && strpos($image_url, 'data:image/svg+xml;base64,') === 0) {
                                // Use the SVG directly
                                $image_html = '<img src="' . esc_attr($image_url) . '" alt="' . esc_attr($category['name']) . '">';
                            }
                            // Check if there's a valid image URL
                            elseif (!empty($image_url)) {
                                $image_html = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($category['name']) . '">';
                            }
                            // No image, use SVG placeholder similar to "All" category
                            else {
                                // Create inline SVG placeholder
                                $image_html = '
                                    <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 600 600" class="medbeafgallery-category-svg-placeholder">
                                        <rect width="600" height="600" fill="#4A90E2" />
                                        <g transform="translate(170, 150)">
                                            <rect x="0" y="0" width="80" height="80" rx="8" fill="white" />
                                            <rect x="100" y="0" width="80" height="80" rx="8" fill="white" />
                                            <rect x="200" y="0" width="80" height="80" rx="8" fill="white" />
                                            <rect x="0" y="100" width="80" height="80" rx="8" fill="white" />
                                            <rect x="100" y="100" width="80" height="80" rx="8" fill="white" />
                                            <rect x="200" y="100" width="80" height="80" rx="8" fill="white" />
                                            <rect x="0" y="200" width="80" height="80" rx="8" fill="white" />
                                            <rect x="100" y="200" width="80" height="80" rx="8" fill="white" />
                                            <rect x="200" y="200" width="80" height="80" rx="8" fill="white" />
                                        </g>
                                    </svg>
                                ';
                            }
                        }

                        // Output the category item
                        ?>
                        <div class="medbeafgallery-carousel-item <?php echo $is_active ? 'active' : ''; ?>"
                             data-id="<?php echo esc_attr($category['slug']); ?>"
                             role="button"
                             tabindex="0"
                             aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
                             aria-label="<?php echo esc_attr(sprintf(
                                 /* translators: %s: category name */
                                 __('Show %s', 'medical-before-after-gallery'),
                                 $category['name']
                             )); ?>">
                            <div class="medbeafgallery-category-image" aria-hidden="true">
                                <?php echo wp_kses($image_html, medbeafgallery_allowed_svg_html()); ?>
                            </div>
                            <span class="medbeafgallery-category-name"><?php echo esc_html($category['name']); ?></span>
                            <?php if (!empty($category['meta'])) : ?>
                                <span class="medbeafgallery-category-meta"><?php echo esc_html($category['meta']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                } else {
                    // Show loading spinner if no categories are available yet
                    ?>
                    <div class="medbeafgallery-carousel-loader">
                        <div class="medbeafgallery-spinner"></div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}