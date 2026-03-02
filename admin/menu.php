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
    add_submenu_page(
        'medbeafgallery-gallery',
        esc_html__('All Cases', 'medical-before-after-gallery'),
        esc_html__('All Cases', 'medical-before-after-gallery'),
        'manage_options',
        'edit.php?post_type=medbeafgallery_case',
        ''
    );

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
        add_submenu_page(
            'medbeafgallery-gallery',
            esc_html__('Categories', 'medical-before-after-gallery'),
            esc_html__('Categories', 'medical-before-after-gallery'),
            'manage_options',
            'edit-tags.php?taxonomy=medbeafgallery_category&post_type=medbeafgallery_case',
            ''
        );
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

    // Highlight the All Cases submenu when on the case edit screen
    if ($pagenow === 'post.php' && $current_screen->post_type === 'medbeafgallery_case') {
        $submenu_file = 'edit.php?post_type=medbeafgallery_case';
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
                <?php esc_html_e('LIMITED TIME OFFER', 'medical-before-after-gallery'); ?>
            </div>
            <h2><?php esc_html_e('Upgrade to Pro Today - One-Time Payment', 'medical-before-after-gallery'); ?></h2>
            <div class="pricing-box">
                <div class="price-tag">
                    <span class="currency">$</span>
                    <span class="amount">10</span>
                    <span class="period"><?php esc_html_e('one-time', 'medical-before-after-gallery'); ?></span>
                </div>
                <div class="value-props">
                    <p class="value-highlight">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Less than a cup of coffee!', 'medical-before-after-gallery'); ?>
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
                        <?php esc_html_e('Upgrade Now - Just $10', 'medical-before-after-gallery'); ?>
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
                        <?php esc_html_e('Get Pro Version - $10', 'medical-before-after-gallery'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Trust Badges -->
        <div class="trust-badges">
            <div class="trust-badge">
                <span class="dashicons dashicons-groups"></span>
                <strong>500+</strong>
                <?php esc_html_e('Medical Professionals', 'medical-before-after-gallery'); ?>
            </div>
            <div class="trust-badge">
                <span class="dashicons dashicons-star-filled"></span>
                <strong>5-Star</strong>
                <?php esc_html_e('Rated Plugin', 'medical-before-after-gallery'); ?>
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
                    <div class="roi-number">$10</div>
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
                <?php esc_html_e('Just one new patient from your improved gallery pays for itself hundreds of times over. Showcase your work professionally and attract more clients!', 'medical-before-after-gallery'); ?>
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
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                    <h4><?php esc_html_e('Interactive Before-After Slider', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Engage visitors with an intuitive slider that reveals the transformation process.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-grid-view"></span>
                    </div>
                    <h4><?php esc_html_e('Responsive Gallery Grid', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Beautifully displays your cases in a responsive grid that works on all devices.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-filter"></span>
                    </div>
                    <h4><?php esc_html_e('Advanced Filtering', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Allow visitors to filter by procedure type, category, age, and more.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <h4><?php esc_html_e('Content Warning', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Protect sensitive content with customizable content warnings and blurring.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-editor-code"></span>
                    </div>
                    <h4><?php esc_html_e('Image Cropping Tool', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Ensure consistent image sizes with the built-in cropping functionality.', 'medical-before-after-gallery'); ?></p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-admin-customizer"></span>
                    </div>
                    <h4><?php esc_html_e('Image Watermarking', 'medical-before-after-gallery'); ?></h4>
                    <p><?php esc_html_e('Protect your valuable images with customizable watermarks (Pro feature).', 'medical-before-after-gallery'); ?></p>
                </div>
            </div>
        </div>

        <div class="medbeafgallery-cta-section">
            <div class="cta-content">
                <span class="cta-badge">
                    <span class="dashicons dashicons-awards"></span>
                    <?php esc_html_e('BEST VALUE', 'medical-before-after-gallery'); ?>
                </span>
                <h3><?php esc_html_e('Ready to Upgrade? Get Started for Just $10', 'medical-before-after-gallery'); ?></h3>
                <p class="cta-tagline"><?php esc_html_e('Join hundreds of medical professionals who trust our plugin to showcase their work professionally.', 'medical-before-after-gallery'); ?></p>
                
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
                        <?php esc_html_e('Upgrade to Pro - Only $10', 'medical-before-after-gallery'); ?>
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

        <!-- Testimonial Section -->
        <div class="testimonials-section">
            <h3><?php esc_html_e('What Medical Professionals Are Saying', 'medical-before-after-gallery'); ?></h3>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars">★★★★★</div>
                    <p class="testimonial-text"><?php esc_html_e('"Best $10 I\'ve spent on my practice website. The before-after galleries look incredibly professional and have helped convert more inquiries into actual patients."', 'medical-before-after-gallery'); ?></p>
                    <p class="testimonial-author"><?php esc_html_e('- Dr. Sarah M., Cosmetic Surgeon', 'medical-before-after-gallery'); ?></p>
                </div>
                <div class="testimonial-card">
                    <div class="stars">★★★★★</div>
                    <p class="testimonial-text"><?php esc_html_e('"For just $10, the unlimited cases and watermarking features alone are worth it. Setup was easy and the support team is fantastic."', 'medical-before-after-gallery'); ?></p>
                    <p class="testimonial-author"><?php esc_html_e('- Dr. James L., Dental Practice', 'medical-before-after-gallery'); ?></p>
                </div>
                <div class="testimonial-card">
                    <div class="stars">★★★★★</div>
                    <p class="testimonial-text"><?php esc_html_e('"The ROI on this plugin is incredible. Paid for itself with one consultation. The filtering options help patients find exactly what they\'re looking for."', 'medical-before-after-gallery'); ?></p>
                    <p class="testimonial-author"><?php esc_html_e('- Dr. Maria K., Dermatologist', 'medical-before-after-gallery'); ?></p>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h3><?php esc_html_e('Frequently Asked Questions', 'medical-before-after-gallery'); ?></h3>
            <div class="faq-grid">
                <div class="faq-item">
                    <h4><?php esc_html_e('Is it really just $10 one-time?', 'medical-before-after-gallery'); ?></h4>
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