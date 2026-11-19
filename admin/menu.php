<?php
/**
 * Admin menu registration for MEDBEAFGALLERY Gallery
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add MEDBEAFGALLERY Gallery admin menu
 */
function medbeafgallery_admin_menu() {
    // Always add main menu item (dashboard handles license check internally)
    add_menu_page(
        esc_html__('Medical Before After Gallery', 'medical-before-after-gallery'),
        esc_html__('Medical Before After Gallery', 'medical-before-after-gallery'),
        'manage_options',
        'medbeafgallery-gallery',
        'medbeafgallery_admin_page',
        'dashicons-format-gallery',
        30
    );

    // Add submenu items
    add_submenu_page(
        'medbeafgallery-gallery',
        esc_html__('Dashboard', 'medical-before-after-gallery'),
        esc_html__('Dashboard', 'medical-before-after-gallery'),
        'manage_options',
        'medbeafgallery-gallery',
        'medbeafgallery_admin_page'
    );

    // All Cases menu item
    if (defined('MEDBEAFGALLERY_CASES_PAGE') && function_exists('medbeafgallery_cases_manager_page')) {
        // The plugin's own Cases screen (admin/cases-manager.php); WordPress's
        // list table stays reachable from its "Classic list" link.
        add_submenu_page(
            'medbeafgallery-gallery',
            esc_html__('Cases', 'medical-before-after-gallery'),
            esc_html__('All Cases', 'medical-before-after-gallery'),
            'manage_options',
            MEDBEAFGALLERY_CASES_PAGE,
            'medbeafgallery_cases_manager_page'
        );
    } else {
        add_submenu_page(
            'medbeafgallery-gallery',
            esc_html__('All Cases', 'medical-before-after-gallery'),
            esc_html__('All Cases', 'medical-before-after-gallery'),
            'manage_options',
            'edit.php?post_type=medbeafgallery_case',
            ''
        );
    }

    add_submenu_page(
        'medbeafgallery-gallery',
        esc_html__('Add New Case', 'medical-before-after-gallery'),
        esc_html__('Add New Case', 'medical-before-after-gallery'),
        'manage_options',
        'post-new.php?post_type=medbeafgallery_case',
        ''
    );

    // Check if the taxonomy exists before adding the menu
    if (taxonomy_exists('medbeafgallery_category')) {
        if (defined('MEDBEAFGALLERY_CATEGORIES_PAGE') && function_exists('medbeafgallery_categories_manager_page')) {
            // The plugin's own Categories screen (admin/categories-manager.php);
            // WordPress's term screen stays reachable from its "Advanced" link.
            add_submenu_page(
                'medbeafgallery-gallery',
                esc_html__('Categories', 'medical-before-after-gallery'),
                esc_html__('Categories', 'medical-before-after-gallery'),
                'manage_options',
                MEDBEAFGALLERY_CATEGORIES_PAGE,
                'medbeafgallery_categories_manager_page'
            );
        } else {
            add_submenu_page(
                'medbeafgallery-gallery',
                esc_html__('Categories', 'medical-before-after-gallery'),
                esc_html__('Categories', 'medical-before-after-gallery'),
                'manage_options',
                'edit-tags.php?taxonomy=medbeafgallery_category&post_type=medbeafgallery_case',
                ''
            );
        }
    }

    // Add Pro Features page only if Pro is NOT active
    if (!apply_filters('medbeafgallery_has_valid_pro_license', false)) {
        add_submenu_page(
            'medbeafgallery-gallery',
            esc_html__('Pro Features', 'medical-before-after-gallery'),
            esc_html__('Pro Features', 'medical-before-after-gallery'),
            'manage_options',
            'medbeafgallery-gallery-pro-features',
            'medbeafgallery_pro_features_page'
        );
    }
}

/**
 * Fix submenu highlighting for custom taxonomy pages and post listings
 */
function medbeafgallery_fix_submenu_highlighting($parent_file) {
    global $current_screen, $pagenow;

    // Set the parent file to Medical Before After Gallery when on the medbeafgallery_category taxonomy page
    if ($current_screen->taxonomy === 'medbeafgallery_category') {
        $parent_file = 'medbeafgallery-gallery';
    }

    // Set the parent file to Medical Before After Gallery when on the medbeafgallery_case post type listing or editing pages
    if (($pagenow === 'edit.php' || $pagenow === 'post.php' || $pagenow === 'post-new.php')
        && $current_screen->post_type === 'medbeafgallery_case') {
        $parent_file = 'medbeafgallery-gallery';
    }

    return $parent_file;
}
add_filter('parent_file', 'medbeafgallery_fix_submenu_highlighting');

/**
 * Fix the current submenu highlighting
 */
function medbeafgallery_fix_submenu($submenu_file) {
    global $current_screen, $pagenow;

    // Highlight the All Cases submenu on the case edit screen and, when the
    // Cases screen exists, on WordPress's own list table ("Classic list").
    if ($current_screen && $current_screen->post_type === 'medbeafgallery_case'
        && ($pagenow === 'post.php' || $pagenow === 'edit.php')) {
        $submenu_file = (defined('MEDBEAFGALLERY_CASES_PAGE') && function_exists('medbeafgallery_cases_manager_page'))
            ? MEDBEAFGALLERY_CASES_PAGE
            : 'edit.php?post_type=medbeafgallery_case';
    }

    // WordPress's own category screens ("Advanced") still belong to Categories.
    if ($current_screen && $current_screen->taxonomy === 'medbeafgallery_category' && defined('MEDBEAFGALLERY_CATEGORIES_PAGE')) {
        $submenu_file = MEDBEAFGALLERY_CATEGORIES_PAGE;
    }

    return $submenu_file;
}
add_filter('submenu_file', 'medbeafgallery_fix_submenu');

/**
 * Make sure admin menus are registered after taxonomies
 */
function medbeafgallery_register_admin_menu() {
    add_action('admin_menu', 'medbeafgallery_admin_menu');
}
add_action('init', 'medbeafgallery_register_admin_menu', 99); // Register after taxonomies (priority 99)

// Remove the default admin_menu hook since we're registering it later
remove_action('admin_menu', 'medbeafgallery_admin_menu');

/**
 * Pro Features page content
 */
function medbeafgallery_pro_features_page() {
    // Add inline styles to ensure immediate styling (backup for caching issues)
    ?>
    <style>
        .wrap.medbeafgallery-pro-features {
            background: #f1f1f1 !important;
            margin: 0 0 0 -20px !important;
            padding: 0 !important;
            max-width: none !important;
        }
        .wrap.medbeafgallery-pro-features > h1 {
            background: #fff !important;
            margin: 0 0 30px 0 !important;
            padding: 20px 30px !important;
            border-bottom: 1px solid #ddd !important;
        }
        .medbeafgallery-offer-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 50px 30px !important;
            text-align: center !important;
            margin: 0 0 30px 0 !important;
        }
        .pricing-box {
            max-width: 800px !important;
            margin: 0 auto !important;
            background: white !important;
            padding: 40px !important;
            border-radius: 15px !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
        }
        .price-tag .amount {
            font-size: 80px !important;
            font-weight: 900 !important;
            color: #667eea !important;
        }
        .trust-badges, .roi-section, .medbeafgallery-features-comparison,
        .medbeafgallery-pro-highlights, .testimonials-section, .faq-section {
            background: white !important;
            padding: 40px 30px !important;
            margin-bottom: 40px !important;
        }
        .medbeafgallery-pro-hero, .medbeafgallery-cta-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 60px 30px !important;
        }
    </style>
    <div class="wrap medbeafgallery-pro-features">
        <h1><?php esc_html_e('Medical Before After Gallery - Pro Features', 'medical-before-after-gallery'); ?></h1>

        <!-- Special Offer Banner -->
        <div class="medbeafgallery-offer-banner">
            <div class="offer-badge">
                <span class="dashicons dashicons-tag"></span>
                <?php esc_html_e('PAY ONCE', 'medical-before-after-gallery'); ?>
            </div>
            <h2><?php esc_html_e('Upgrade to the Pro Add-on - One-Time Payment', 'medical-before-after-gallery'); ?></h2>
            <div class="pricing-box">
                <div class="price-tag">
                    <span class="period" style="font-size:26px;font-weight:800;color:#667eea;"><?php esc_html_e('Pro Add-on — Lifetime License', 'medical-before-after-gallery'); ?></span>
                </div>
                <div class="value-props">
                    <p class="value-highlight">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Unlock every Pro feature', 'medical-before-after-gallery'); ?>
                    </p>
                    <p class="value-highlight">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('No recurring fees - Pay once, use forever', 'medical-before-after-gallery'); ?>
                    </p>
                    <p class="value-highlight">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Lifetime updates & support', 'medical-before-after-gallery'); ?>
                    </p>
                    <p class="value-highlight">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('30-day money-back guarantee', 'medical-before-after-gallery'); ?>
                    </p>
                </div>
                <div class="cta-primary">
                    <a href="https://medicalbeforeaftergallery.com/" target="_blank" class="button button-primary button-hero upgrade-now-btn">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e('Get the Pro Add-on', 'medical-before-after-gallery'); ?>
                    </a>
                    <p class="guarantee-text">
                        <span class="dashicons dashicons-shield-alt"></span>
                        <?php esc_html_e('100% Risk-Free - 30-Day Money-Back Guarantee', 'medical-before-after-gallery'); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="medbeafgallery-pro-hero">
            <div class="hero-content">
                <h2><?php esc_html_e('Unlock the Full Potential of Your Gallery', 'medical-before-after-gallery'); ?></h2>
                <p><?php esc_html_e('Take your medical gallery to the next level with advanced features designed for professional practices.', 'medical-before-after-gallery'); ?></p>

                <div class="hero-buttons">
                    <a href="https://demo.medicalbeforeaftergallery.com/" target="_blank" class="button button-secondary button-large demo-btn">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('View Live Demo', 'medical-before-after-gallery'); ?>
                    </a>
                    <a href="https://medicalbeforeaftergallery.com/" target="_blank" class="button button-primary button-large get-pro-btn">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('Get the Pro Add-on', 'medical-before-after-gallery'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Trust Badges -->
        <div class="trust-badges">
            <div class="trust-badge">
                <span class="dashicons dashicons-format-gallery"></span>
                <strong><?php esc_html_e('Unlimited', 'medical-before-after-gallery'); ?></strong>
                <?php esc_html_e('Cases & Categories', 'medical-before-after-gallery'); ?>
            </div>
            <div class="trust-badge">
                <span class="dashicons dashicons-admin-appearance"></span>
                <strong><?php esc_html_e('Any Theme', 'medical-before-after-gallery'); ?></strong>
                <?php esc_html_e('Compatible', 'medical-before-after-gallery'); ?>
            </div>
            <div class="trust-badge">
                <span class="dashicons dashicons-shield"></span>
                <strong>30-Day</strong>
                <?php esc_html_e('Money-Back Guarantee', 'medical-before-after-gallery'); ?>
            </div>
            <div class="trust-badge">
                <span class="dashicons dashicons-update"></span>
                <strong>Lifetime</strong>
                <?php esc_html_e('Updates Included', 'medical-before-after-gallery'); ?>
            </div>
        </div>

        <!-- ROI Calculator Section -->
        <div class="roi-section">
            <h3><?php esc_html_e('See Your Return on Investment', 'medical-before-after-gallery'); ?></h3>
            <div class="roi-calculator">
                <div class="roi-item">
                    <div class="roi-number" style="font-size:28px;"><?php esc_html_e('Once', 'medical-before-after-gallery'); ?></div>
                    <div class="roi-label"><?php esc_html_e('One-Time Investment', 'medical-before-after-gallery'); ?></div>
                </div>
                <div class="roi-arrow">→</div>
                <div class="roi-item">
                    <div class="roi-number">1</div>
                    <div class="roi-label"><?php esc_html_e('New Patient', 'medical-before-after-gallery'); ?></div>
                </div>
                <div class="roi-arrow">→</div>
                <div class="roi-item">
                    <div class="roi-number">∞</div>
                    <div class="roi-label"><?php esc_html_e('Unlimited ROI', 'medical-before-after-gallery'); ?></div>
                </div>
            </div>
            <p class="roi-description">
                <?php esc_html_e('If your gallery helps bring in even one new patient, Pro can pay for itself. Showcase your work professionally and attract more clients!', 'medical-before-after-gallery'); ?>
            </p>
        </div>

        <div class="medbeafgallery-features-comparison">
            <h3><?php esc_html_e('Free vs Pro Features', 'medical-before-after-gallery'); ?></h3>
            <p class="subtitle"><?php esc_html_e('See what you\'re missing with the free version', 'medical-before-after-gallery'); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="feature-name"><?php esc_html_e('Feature', 'medical-before-after-gallery'); ?></th>
                        <th class="free-version"><?php esc_html_e('Free Version', 'medical-before-after-gallery'); ?></th>
                        <th class="pro-version"><?php esc_html_e('Pro Version', 'medical-before-after-gallery'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Before-After Cases', 'medical-before-after-gallery'); ?></td>
                        <td><?php esc_html_e('Up to 12', 'medical-before-after-gallery'); ?></td>
                        <td><strong><?php esc_html_e('Unlimited', 'medical-before-after-gallery'); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Categories', 'medical-before-after-gallery'); ?></td>
                        <td><?php esc_html_e('Up to 4', 'medical-before-after-gallery'); ?></td>
                        <td><strong><?php esc_html_e('Unlimited', 'medical-before-after-gallery'); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Basic Before-After Gallery', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Category Filtering', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Image Cropping', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Responsive Design', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Before-After View Switching', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Modal Case Navigation', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Category Navigation', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <?php
                    // Features both versions have (free 1.5.0).
                    foreach (array(
                        __('Hover-Reveal Cards', 'medical-before-after-gallery'),
                        __('Blurred Photo Background in the Case Window', 'medical-before-after-gallery'),
                        __('Doctor Cards', 'medical-before-after-gallery'),
                        __('Category Styles: Carousel, Grid, Buttons, Drop-down', 'medical-before-after-gallery'),
                        __('Categories Screen with Drag-and-Drop Order', 'medical-before-after-gallery'),
                    ) as $medbeafgallery_both) : ?>
                    <tr>
                        <td><?php echo esc_html($medbeafgallery_both); ?></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Category Tabs and Photo Tiles', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Multiple Before-After Pairs', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Advanced Filtering (Age, Gender, Procedure)', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Watermarking Capabilities', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Additional Images Carousel', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Sensitive Content Warning', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Detailed Before-After Case Information', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Subcategories', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Search & Sort', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Deep-Linking / Shareable Views', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Masonry Layout + Redesigned Modal', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Dark Mode', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Image Annotations / Hotspots', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Single Case Embed Shortcode', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Modal Call-to-Action Button', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <?php
                    // Pro 1.6.0 additions.
                    foreach (array(
                        __('Case-Window Styles: Cinema, Drawer, Comparison Desk, Case Story, Phone Story Mode', 'medical-before-after-gallery'),
                        __('Gallery Layouts and Style Presets', 'medical-before-after-gallery'),
                        __('Progress Timeline with Dated Photos', 'medical-before-after-gallery'),
                        __('"Find by Area" Body Map and "Help Me Choose"', 'medical-before-after-gallery'),
                        __('Ghost Camera, Photo Line-Up Tool and Transformation Reels', 'medical-before-after-gallery'),
                        __('Filter by Doctor', 'medical-before-after-gallery'),
                        __('AI Write-ups and Alt Text (WordPress 7)', 'medical-before-after-gallery'),
                        __('Setup Wizard, Live Preview and Ready-to-Publish Checklist', 'medical-before-after-gallery'),
                    ) as $medbeafgallery_pro_only) : ?>
                    <tr class="pro-feature">
                        <td><?php echo esc_html($medbeafgallery_pro_only); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="pro-feature">
                        <td><?php esc_html_e('Premium Support', 'medical-before-after-gallery'); ?></td>
                        <td><span class="dashicons dashicons-dismiss feature-not-included"></span></td>
                        <td><span class="dashicons dashicons-yes-alt feature-included"></span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="medbeafgallery-pro-highlights">
            <h3><?php esc_html_e('Pro Features Highlights', 'medical-before-after-gallery'); ?></h3>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-welcome-view-site"></span>
                    </div>
                    <h4><?php esc_html_e('Case-Window Styles', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Cinema, side drawer, a comparison desk with a linked magnifier, a scrolling case story — and a swipeable story mode on phones.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <h4><?php esc_html_e('Progress Timeline', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Show real, dated photos from each stage — 6 weeks, 3 months, 1 year — on a scrubber. Never blended or invented.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <h4><?php esc_html_e('Find by Area & Help Me Choose', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Visitors tap the nose, lips, teeth or abdomen on a body map, or answer two or three questions, to see matching cases.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-camera"></span>
                    </div>
                    <h4><?php esc_html_e('Ghost Camera & Reels', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Take the after photo with the before photo as a ghost guide, line up pairs, and export a short before-and-after video for social media.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-grid-view"></span>
                    </div>
                    <h4><?php esc_html_e('Masonry Layout & Dark Mode', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('A premium Pinterest-style layout with an immersive, redesigned case modal and optional dark mode.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-location-alt"></span>
                    </div>
                    <h4><?php esc_html_e('Image Annotations', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Drop numbered hotspot markers on your images to point out exactly what changed — revealed on hover or tap.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-filter"></span>
                    </div>
                    <h4><?php esc_html_e('Advanced & Custom Filters', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Filter by gender, age, procedure and unlimited custom filters — plus instant search, sort and shareable views.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-admin-customizer"></span>
                    </div>
                    <h4><?php esc_html_e('Watermarking & Protection', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Protect images with a text or logo watermark in nine positions — your originals are never modified.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <h4><?php esc_html_e('Sensitive-Content Consent', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Gate galleries behind a customizable consent overlay for responsible, compliant presentation.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-shortcode"></span>
                    </div>
                    <h4><?php esc_html_e('Single Case & Carousel', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Embed one case with [mba_case] or a category carousel with [mba_category_carousel] anywhere on your site.', 'medical-before-after-gallery'); ?></p>
                </div>
            </div>
        </div>

        <div class="medbeafgallery-cta-section">
            <div class="cta-content">
                <span class="cta-badge">
                    <span class="dashicons dashicons-awards"></span>
                    <?php esc_html_e('BEST VALUE', 'medical-before-after-gallery'); ?>
                </span>
                <h3><?php esc_html_e('Ready to Upgrade to the Pro Add-on?', 'medical-before-after-gallery'); ?></h3>
                <p class="cta-tagline"><?php esc_html_e('Every Pro feature for a one-time payment, with updates included.', 'medical-before-after-gallery'); ?></p>
                
                <div class="cta-features-mini">
                    <div class="mini-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Unlimited Cases & Categories', 'medical-before-after-gallery'); ?>
                    </div>
                    <div class="mini-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Advanced Filtering & Watermarking', 'medical-before-after-gallery'); ?>
                    </div>
                    <div class="mini-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Priority Support & Updates', 'medical-before-after-gallery'); ?>
                    </div>
                    <div class="mini-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('30-Day Money-Back Guarantee', 'medical-before-after-gallery'); ?>
                    </div>
                </div>

                <div class="cta-buttons">
                    <a href="https://medicalbeforeaftergallery.com/" target="_blank" class="button button-primary button-hero get-pro-btn pulse-animation">
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e('Get the Pro Add-on', 'medical-before-after-gallery'); ?>
                    </a>
                    <a href="https://demo.medicalbeforeaftergallery.com/" target="_blank" class="button button-secondary button-hero demo-btn">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('View Live Demo', 'medical-before-after-gallery'); ?>
                    </a>
                </div>
                
                <div class="cta-guarantee">
                    <div class="guarantee-badge">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="guarantee-content">
                        <strong><?php esc_html_e('Risk-Free Guarantee', 'medical-before-after-gallery'); ?></strong>
                        <p><?php esc_html_e('Try it for 30 days. If you\'re not completely satisfied, get a full refund. No questions asked.', 'medical-before-after-gallery'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews: real, public WordPress.org reviews of the free plugin, quoted
             verbatim and linked. Not translatable on purpose — they are quotes. -->
        <?php
        $medbeafgallery_reviews = array(
            array(
                'text'   => 'Really happy with this plugin! I run a small clinic and needed a clean way to show before-and-after results. Setup was super easy, the slider looks professional, and it works great on mobile too.',
                'author' => __('Anonymous reviewer', 'medical-before-after-gallery'),
                'url'    => 'https://wordpress.org/support/topic/best-before-after-plugin-ive-tried/',
            ),
            array(
                'text'   => 'This is by far the best WordPress before and after image tool i found on the internet. Easy to use, reliable, and low impact on site.',
                'author' => 'yourguru16',
                'url'    => 'https://wordpress.org/support/topic/most-complete-before-and-after-gallery-tool/',
            ),
            array(
                'text'   => 'I love how it displays on mobile, way it shows before afters with split view, before only view and after only view. just love this plugin',
                'author' => 'alex7087',
                'url'    => 'https://wordpress.org/support/topic/love-it-4303/',
            ),
        );
        ?>
        <div class="testimonials-section">
            <h3><?php esc_html_e('What users say on WordPress.org', 'medical-before-after-gallery'); ?></h3>
            <div class="testimonials-grid">
                <?php foreach ($medbeafgallery_reviews as $medbeafgallery_review) : ?>
                <div class="testimonial-card">
                    <div class="stars" aria-label="<?php esc_attr_e('5 out of 5 stars', 'medical-before-after-gallery'); ?>">★★★★★</div>
                    <p class="testimonial-text">&ldquo;<?php echo esc_html($medbeafgallery_review['text']); ?>&rdquo;</p>
                    <p class="testimonial-author">
                        &mdash; <a href="<?php echo esc_url($medbeafgallery_review['url']); ?>" target="_blank" rel="noopener noreferrer"><?php
                        /* translators: %s: reviewer's WordPress.org username */
                        echo esc_html(sprintf(__('%s on WordPress.org', 'medical-before-after-gallery'), $medbeafgallery_review['author']));
                        ?></a>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="testimonials-source">
                <?php esc_html_e('Reviews of the free plugin.', 'medical-before-after-gallery'); ?>
                <a href="https://wordpress.org/support/plugin/medical-before-after-gallery/reviews/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read all reviews', 'medical-before-after-gallery'); ?></a>
            </p>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h3><?php esc_html_e('Frequently Asked Questions', 'medical-before-after-gallery'); ?></h3>
            <div class="faq-grid">
                <div class="faq-item">
                    <h4><?php esc_html_e('Is it a one-time payment?', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Yes! Pay once and use it forever. No hidden fees, no subscriptions. You\'ll get lifetime updates and support.', 'medical-before-after-gallery'); ?></p>
                </div>
                <div class="faq-item">
                    <h4><?php esc_html_e('What if I\'m not satisfied?', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('We offer a 30-day money-back guarantee. If you\'re not happy, we\'ll refund you in full, no questions asked.', 'medical-before-after-gallery'); ?></p>
                </div>
                <div class="faq-item">
                    <h4><?php esc_html_e('Will it work with my theme?', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Yes! Our plugin works with all WordPress themes. If you have any issues, our support team will help you get it working perfectly.', 'medical-before-after-gallery'); ?></p>
                </div>
                <div class="faq-item">
                    <h4><?php esc_html_e('How do I upgrade from free to pro?', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Simply purchase the Pro version, install it, and all your existing cases and settings will be preserved. It\'s a seamless upgrade.', 'medical-before-after-gallery'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
}