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

    // Get plugin settings
    $settings = medbeafgallery_get_settings();

    // Default attributes - core features for free version
    $default_atts = array(
        'items_per_page' => 6,
        'default_category' => 'all',
        'cta_text' => $settings['consultation_text'],
        'cta_link' => $settings['consultation_url'],
        'show_cta' => $settings['consultation_enabled'] ? 'true' : 'false',
        'show_filters' => 'true'       // Always available
    );

    $atts = shortcode_atts($default_atts, $atts);

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
        'ctaText' => esc_html($atts['cta_text']),
        'ctaLink' => esc_url($atts['cta_link']),
        'showCta' => esc_attr($atts['show_cta']),
        'categoryDisplayMode' => esc_attr($settings['category_display_mode'] ?? 'grid'),
        'galleryEndpoint' => 'gallery-data',
        'categoriesEndpoint' => 'categories',
        'restBase' => esc_url_raw(rest_url('medical-before-after-gallery/v1')),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'pluginUrl' => MEDBEAFGALLERY_URL,
    );

    $gallery_config = apply_filters('medbeafgallery_gallery_config', $gallery_config);
    wp_localize_script('medbeafgallery-js', 'medbeafgalleryGalleryConfig', $gallery_config);

    // Start output buffering
    ob_start();

    ?>
    <div class="medbeafgallery-container" id="medbeafgallery-gallery-container">
        <!-- Gallery content -->
        <div class="medbeafgallery-gallery-content" id="medbeafgallery-gallery-content">
            <!-- Category Carousel - now populated by PHP -->
            <?php echo wp_kses_post(medbeafgallery_render_category_carousel($settings['category_display_mode'] ?? 'grid')); ?>

            <div class="medbeafgallery-gallery-layout">
                <?php do_action('medbeafgallery_before_gallery_main'); ?>
                <!-- Main Content Area -->
                <main class="medbeafgallery-main-content">
                    <!-- Active Filter Tags -->
                    <div class="medbeafgallery-active-filters" style="display:none;">
                        <div id="medbeafgallery-filter-tags" class="medbeafgallery-filter-tags"></div>
                        <button id="medbeafgallery-clear-filters" class="medbeafgallery-clear-filters" style="display:none;"><?php esc_html_e('Clear All', 'medical-before-after-gallery'); ?></button>
                    </div>
                    <!-- Grid Container for Gallery Items -->











                    <!-- Gallery Section -->
                    <div class="medbeafgallery-gallery-container"
                         data-category="<?php echo esc_attr($atts['default_category']); ?>"
                         data-per-page="<?php echo esc_attr($atts['items_per_page']); ?>">
                        <div class="medbeafgallery-loading-indicator">
                            <div class="medbeafgallery-spinner"></div>
                            <p>Loading gallery...</p>
                        </div>
                        <div class="medbeafgallery-gallery-grid" id="medbeafgallery-gallery-grid" style="display: none;">
                            <!-- Gallery items will be loaded via JavaScript -->
                        </div>
                        <div id="medbeafgallery-no-results" class="medbeafgallery-no-results" style="display: none;">
                            <p>No results found. Please try different filter criteria.</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="medbeafgallery-pagination">
                        <button id="medbeafgallery-load-more" class="medbeafgallery-load-more" style="display: none;">Load More</button>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Modal for enlarged view -->
    <div id="medbeafgallery-modal" class="medbeafgallery-modal">
        <div class="medbeafgallery-modal-content">
            <div class="medbeafgallery-modal-header">
                <h2 id="medbeafgallery-case-title">Case Study Title</h2>

                <!-- Image Controls moved to header -->
                <div class="medbeafgallery-image-controls">
                    <button class="medbeafgallery-control-btn medbeafgallery-split-view active" title="Split view">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="3" x2="12" y2="21"></line><path d="M8 8l-4 4 4 4"></path><path d="M16 16l4-4-4-4"></path></svg>
                    </button>
                    <button class="medbeafgallery-control-btn medbeafgallery-before-view" title="Before view">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2"></rect><rect x="8" y="9" width="8" height="8"></rect></svg>
                    </button>
                    <button class="medbeafgallery-control-btn medbeafgallery-after-view" title="After view">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2"></rect><rect x="8" y="9" width="8" height="8"></rect><circle cx="12" cy="13" r="2"></circle></svg>
                    </button>
                </div>

                <span class="medbeafgallery-pair-info medbeafgallery-pair-description">Main View</span>

                <button class="medbeafgallery-modal-close" aria-label="Close modal">&times;</button>
            </div>

            <div class="medbeafgallery-modal-body">
                <div class="medbeafgallery-comparison-container">
                    <!-- Image Pairs Navigation -->
                    <div class="medbeafgallery-image-pairs-nav">
                        <button class="medbeafgallery-pair-nav medbeafgallery-pair-prev" title="Previous image pair">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>
                        <div class="medbeafgallery-pair-indicators">
                            <!-- Will be populated dynamically -->
                        </div>
                        <button class="medbeafgallery-pair-nav medbeafgallery-pair-next" title="Next image pair">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>

                    <!-- Image Sets Container -->
                    <div class="medbeafgallery-image-sets-container">
                        <div class="medbeafgallery-before-after-wrapper active" data-pair-id="1">
                            <!-- Content populated dynamically by showImagePair using Cocoen slider -->
                        </div>
                    </div>

                    <!-- Image View Labels -->
                    <div class="medbeafgallery-labels">
                        <div class="medbeafgallery-before-label">Before</div>
                        <div class="medbeafgallery-after-label">After</div>
                    </div>
                </div>

                <!-- Case Details Panel -->
                <div class="medbeafgallery-case-details">
                    <div class="medbeafgallery-case-tabs">
                        <button class="medbeafgallery-tab active" data-tab="description"><?php esc_html_e('Description', 'medical-before-after-gallery'); ?></button>
                        <button class="medbeafgallery-tab" data-tab="details"><?php esc_html_e('Details', 'medical-before-after-gallery'); ?></button>
                    </div>
                    <div class="medbeafgallery-tab-content active" data-tab="description">
                        <div id="medbeafgallery-case-description"></div>
                    </div>
                    <div class="medbeafgallery-tab-content" data-tab="details">
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
                            <button class="medbeafgallery-share-btn medbeafgallery-share-facebook" aria-label="<?php esc_attr_e('Share on Facebook', 'medical-before-after-gallery'); ?>" onclick="shareOnSocial('facebook')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                            </button>
                            <button class="medbeafgallery-share-btn medbeafgallery-share-twitter" aria-label="<?php esc_attr_e('Share on Twitter', 'medical-before-after-gallery'); ?>" onclick="shareOnSocial('twitter')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
                            </button>
                            <button class="medbeafgallery-share-btn medbeafgallery-share-email" aria-label="<?php esc_attr_e('Share via Email', 'medical-before-after-gallery'); ?>" onclick="shareOnSocial('email')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Navigation -->
            <div class="medbeafgallery-modal-nav">
                <div class="medbeafgallery-modal-nav-container">
                    <button class="medbeafgallery-modal-prev" aria-label="Previous case">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>

                    <div class="medbeafgallery-modal-counter-wrapper">
                        <div class="medbeafgallery-modal-counter">
                            <span id="medbeafgallery-current-item" class="medbeafgallery-counter-current">1</span>
                            <span class="medbeafgallery-counter-separator">/</span>
                            <span id="medbeafgallery-total-items" class="medbeafgallery-counter-total">1</span>
                        </div>
                    </div>

                    <button class="medbeafgallery-modal-next" aria-label="Next case">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
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
 * Render the category carousel
 */
function medbeafgallery_render_category_carousel($display_mode = 'grid') {
    // Use the existing function from utilities.php to get categories
    $categories = medbeafgallery_get_categories_with_images();

    // Get default category from config or use 'all' as default
    $gallery_config = get_option('medbeafgallery_settings', array());
    $default_category = isset($gallery_config['default_category']) ? $gallery_config['default_category'] : 'all';

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
            // Only include if it has posts or child categories with posts
            if (isset($category['count']) && $category['count'] > 0) {
                $processed_categories[] = $category;
                $processed_slugs[] = $category['slug'];
            }
        }
    }

    $carousel_class = 'medbeafgallery-category-carousel';
    if ($display_mode === 'carousel') {
        $carousel_class .= ' medbeafgallery-carousel-mode';
    } else {
        $carousel_class .= ' medbeafgallery-grid-mode';
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($carousel_class); ?>" data-display-mode="<?php echo esc_attr($display_mode); ?>">
        <div class="medbeafgallery-nav-buttons">
            <button class="medbeafgallery-nav-btn medbeafgallery-prev-btn" aria-label="Previous">
                <span class="medbeafgallery-arrow-left">&#8592;</span>
            </button>
            <button class="medbeafgallery-nav-btn medbeafgallery-next-btn" aria-label="Next">
                <span class="medbeafgallery-arrow-right">&#8594;</span>
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

                                            <!-- "All" Text Overlay -->
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
                        <div class="medbeafgallery-carousel-item <?php echo $is_active ? 'active' : ''; ?>" data-id="<?php echo esc_attr($category['slug']); ?>">
                            <div class="medbeafgallery-category-image">
                                <?php echo wp_kses_post($image_html); ?>
                            </div>
                            <span class="medbeafgallery-category-name"><?php echo esc_html($category['name']); ?></span>
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