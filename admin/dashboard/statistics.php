<?php
/**
 * Dashboard statistics functions for MEDBEAFGALLERY Gallery
 *
 * @package MEDBEAFGALLERY_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get dashboard statistics
 *
 * @return array Statistics data
 */
function medbeafgallery_get_dashboard_statistics() {
    // Get statistics
    $total_cases = wp_count_posts('medbeafgallery_case')->publish;

    // Get categories
    $categories = get_terms(array('taxonomy' => 'medbeafgallery_category', 'hide_empty' => false));
    $total_categories = is_wp_error($categories) ? 0 : count($categories);

    // Get total image count
    $total_images = 0;
    $cases = get_posts(array(
        'post_type' => 'medbeafgallery_case',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));

    foreach($cases as $case_id) {
        // Count main images
        if(get_post_meta($case_id, '_medbeafgallery_main_before_image', true)) $total_images++;
        if(get_post_meta($case_id, '_medbeafgallery_main_after_image', true)) $total_images++;

        // Count additional images
        $additional_pairs = get_post_meta($case_id, '_medbeafgallery_additional_image_pairs', true);
        if(is_array($additional_pairs)) {
            foreach($additional_pairs as $pair) {
                if(!empty($pair['before_id'])) $total_images++;
                if(!empty($pair['after_id'])) $total_images++;
            }
        }
    }

    // Get most popular category
    $most_popular = '';
    $most_count = 0;
    if(!is_wp_error($categories)) {
        foreach($categories as $cat) {
            if($cat->count > $most_count && $cat->term_id != get_option('medbeafgallery_all_category_id')) {
                $most_count = $cat->count;
                $most_popular = $cat->name;
            }
        }
    }

    return array(
        'total_cases' => $total_cases,
        'total_categories' => $total_categories,
        'total_images' => $total_images,
        'most_popular_category' => $most_popular ? $most_popular : __('None', 'medical-before-after-gallery'),
        'most_popular_count' => $most_count
    );
}

/**
 * Display statistics boxes on dashboard
 *
 * @param array $stats Statistics data
 */
function medbeafgallery_display_statistics($stats) {
    ?>
    <div class="medbeafgallery-admin-stats">
        <div class="medbeafgallery-stat-box">
            <h3><?php esc_html_e('Total Cases', 'medical-before-after-gallery'); ?></h3>
            <span class="medbeafgallery-stat-number">
                <?php
                $max_cases = medbeafgallery_get_max_cases();
                if ($max_cases !== -1) {
                    echo esc_html($stats['total_cases'] . ' / ' . $max_cases);
                } else {
                    echo esc_html($stats['total_cases']);
                }
                ?>
            </span>
            <?php if ($max_cases !== -1 && $stats['total_cases'] >= $max_cases): ?>
                <p class="medbeafgallery-stat-limit" style="color: #d63638; font-size: 12px; margin: 4px 0;">
                    <?php esc_html_e('Limit reached', 'medical-before-after-gallery'); ?> — 
                    <a href="https://medicalbeforeaftergallery.com/" target="_blank"><?php esc_html_e('Upgrade to Pro', 'medical-before-after-gallery'); ?></a>
                </p>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=medbeafgallery_case')); ?>" class="medbeafgallery-stat-link">
                <?php esc_html_e('View All', 'medical-before-after-gallery'); ?> →
            </a>
        </div>

        <div class="medbeafgallery-stat-box">
            <h3><?php esc_html_e('Categories', 'medical-before-after-gallery'); ?></h3>
            <span class="medbeafgallery-stat-number">
                <?php
                $max_categories = medbeafgallery_get_max_categories();
                $cat_count = function_exists('medbeafgallery_get_category_count') ? medbeafgallery_get_category_count() : $stats['total_categories'];
                if ($max_categories !== -1) {
                    echo esc_html($cat_count . ' / ' . $max_categories);
                } else {
                    echo esc_html($stats['total_categories']);
                }
                ?>
            </span>
            <?php if ($max_categories !== -1 && $cat_count >= $max_categories): ?>
                <p class="medbeafgallery-stat-limit" style="color: #d63638; font-size: 12px; margin: 4px 0;">
                    <?php esc_html_e('Limit reached', 'medical-before-after-gallery'); ?> — 
                    <a href="https://medicalbeforeaftergallery.com/" target="_blank"><?php esc_html_e('Upgrade to Pro', 'medical-before-after-gallery'); ?></a>
                </p>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=medbeafgallery_category&post_type=medbeafgallery_case')); ?>" class="medbeafgallery-stat-link">
                <?php esc_html_e('Manage', 'medical-before-after-gallery'); ?> →
            </a>
        </div>

        <div class="medbeafgallery-stat-box">
            <h3><?php esc_html_e('Total Images', 'medical-before-after-gallery'); ?></h3>
            <span class="medbeafgallery-stat-number"><?php echo esc_html($stats['total_images']); ?></span>
            <p class="medbeafgallery-stat-description">
                <?php esc_html_e('Before & after photos in gallery', 'medical-before-after-gallery'); ?>
            </p>
        </div>

        <div class="medbeafgallery-stat-box">
            <h3><?php esc_html_e('Popular Category', 'medical-before-after-gallery'); ?></h3>
            <span class="medbeafgallery-stat-text"><?php echo esc_html($stats['most_popular_category']); ?></span>
            <span class="medbeafgallery-stat-subtitle"><?php
                echo esc_html(sprintf(
                    /* translators: %d: number of cases in the most popular category */
                    _n('%d case', '%d cases', $stats['most_popular_count'], 'medical-before-after-gallery'),
                    $stats['most_popular_count']
                ));
            ?></span>
        </div>
    </div>

    <?php
}