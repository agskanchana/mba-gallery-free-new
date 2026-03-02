<?php
/**
 * Register post type and taxonomy for Medical Before After Gallery
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom post type and taxonomy
 */
function medbeafgallery_register_post_types() {
    // Register Before After Case post type
    register_post_type('medbeafgallery_case', array(
        'labels' => array(
            'name'               => _x('Before After Cases', 'post type general name', 'medical-before-after-gallery'),
            'singular_name'      => _x('Case', 'post type singular name', 'medical-before-after-gallery'),
            'menu_name'          => _x('Before After Cases', 'admin menu', 'medical-before-after-gallery'),
            'name_admin_bar'     => _x('Case', 'add new on admin bar', 'medical-before-after-gallery'),
            'add_new'            => _x('Add New', 'case', 'medical-before-after-gallery'),
            'add_new_item'       => __('Add New Case', 'medical-before-after-gallery'),
            'new_item'           => __('New Case', 'medical-before-after-gallery'),
            'edit_item'          => __('Edit Case', 'medical-before-after-gallery'),
            'view_item'          => __('View Case', 'medical-before-after-gallery'),
            'all_items'          => __('All Cases', 'medical-before-after-gallery'),
            'search_items'       => __('Search Cases', 'medical-before-after-gallery'),
            'parent_item_colon'  => __('Parent Cases:', 'medical-before-after-gallery'),
            'not_found'          => __('No cases found.', 'medical-before-after-gallery'),
            'not_found_in_trash' => __('No cases found in Trash.', 'medical-before-after-gallery')
        ),
        'public'              => false, // Change to false
        'exclude_from_search' => true,  // Exclude from search
        'publicly_queryable'  => false, // Change to false
        'show_ui'             => true,
        'show_in_menu'        => false, // Hide from main menu - we'll add it under our custom menu
        'show_in_nav_menus'   => false, // Change to false
        'show_in_admin_bar'   => true,
        'menu_position'       => 20,
        'menu_icon'           => 'dashicons-images-alt2',
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array('title',),
        'has_archive'         => false, // Change to false
        'rewrite'             => false, // Change to false instead of array
        'query_var'           => false  // Change to false
     ));

    // Register Case Category taxonomy
    register_taxonomy(
        'medbeafgallery_category',
        'medbeafgallery_case',
        array(
            'labels' => array(
                'name'                       => _x('Case Categories', 'taxonomy general name', 'medical-before-after-gallery'),
                'singular_name'              => _x('Case Category', 'taxonomy singular name', 'medical-before-after-gallery'),
                'search_items'               => __('Search Case Categories', 'medical-before-after-gallery'),
                'popular_items'              => __('Popular Case Categories', 'medical-before-after-gallery'),
                'all_items'                  => __('All Case Categories', 'medical-before-after-gallery'),
                'parent_item'                => __('Parent Case Category', 'medical-before-after-gallery'),
                'parent_item_colon'          => __('Parent Case Category:', 'medical-before-after-gallery'),
                'edit_item'                  => __('Edit Case Category', 'medical-before-after-gallery'),
                'update_item'                => __('Update Case Category', 'medical-before-after-gallery'),
                'add_new_item'               => __('Add New Case Category', 'medical-before-after-gallery'),
                'new_item_name'              => __('New Case Category Name', 'medical-before-after-gallery'),
                'separate_items_with_commas' => __('Separate case categories with commas', 'medical-before-after-gallery'),
                'add_or_remove_items'        => __('Add or remove case categories', 'medical-before-after-gallery'),
                'choose_from_most_used'      => __('Choose from the most used case categories', 'medical-before-after-gallery'),
                'not_found'                  => __('No case categories found.', 'medical-before-after-gallery'),
                'menu_name'                  => __('Case Categories', 'medical-before-after-gallery'),
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false, // Change to false
            'rewrite'           => false, // Change to false
            'show_in_rest'      => true, // Enable in block editor
            'public'            => false, // Add this line
        )
    );
}
add_action('init', 'medbeafgallery_register_post_types');

/**
 * Flush rewrite rules on plugin activation
 */
function medbeafgallery_rewrite_flush() {
    // First register the post type and taxonomy, then flush rewrite rules
    medbeafgallery_register_post_types();
    flush_rewrite_rules();
}

/**
 * Create default "All" category on plugin activation
 */
function medbeafgallery_create_default_categories() {
    // Check if the "All" category already exists
    $all_category = get_term_by('slug', 'all', 'medbeafgallery_category');

    if (!$all_category) {
        // Create the "All" category
        $term = wp_insert_term(
            __('All', 'medical-before-after-gallery'),
            'medbeafgallery_category',
            array(
                'slug' => 'all',
                'description' => __('Default category that shows all cases', 'medical-before-after-gallery')
            )
        );

        if (!is_wp_error($term)) {
            // Store the term ID for future reference
            update_option('medbeafgallery_all_category_id', $term['term_id']);

            // Just mark it as default category, but don't add an image
            if (function_exists('update_term_meta')) {
                update_term_meta($term['term_id'], 'medbeafgallery_is_default_category', true);
                // Removed code that creates and assigns default image
            }
        }
    } else {
        // Store the term ID for future reference in case it doesn't exist yet
        update_option('medbeafgallery_all_category_id', $all_category->term_id);

        // Set as default category, but don't assign an image
        if (function_exists('update_term_meta')) {
            update_term_meta($all_category->term_id, 'medbeafgallery_is_default_category', true);
            // Removed code that checks for and assigns default image
        }
    }
}

/**
 * Prevent deletion of the "All" category
 */
function medbeafgallery_prevent_default_category_deletion($term_id, $taxonomy) {
    if ($taxonomy === 'medbeafgallery_category') {
        $all_category_id = get_option('medbeafgallery_all_category_id');

        // If this is our "All" category
        if ($term_id == $all_category_id) {
            wp_die(
                esc_html__('You cannot delete the "All" category as it is a system category.', 'medical-before-after-gallery'),
                esc_html__('Error: Cannot Delete', 'medical-before-after-gallery'),
                array('back_link' => true)
            );
        }
    }
}
add_action('pre_delete_term', 'medbeafgallery_prevent_default_category_deletion', 10, 2);

/**
 * Add a visual indicator for the "All" category in admin
 */
function medbeafgallery_add_all_category_indicator($columns) {
    $columns['is_default'] = __('Default', 'medical-before-after-gallery');
    return $columns;
}
add_filter('manage_edit-medbeafgallery_category_columns', 'medbeafgallery_add_all_category_indicator');

/**
 * Display the indicator for the "All" category
 */
function medbeafgallery_display_all_category_indicator($content, $column_name, $term_id) {
    if ($column_name === 'is_default') {
        if (get_term_meta($term_id, 'medbeafgallery_is_default_category', true)) {
            return '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>';
        }
    }
    return $content;
}
add_filter('manage_medbeafgallery_category_custom_column', 'medbeafgallery_display_all_category_indicator', 10, 3);

/**
 * Add "All" category image field to settings
 */
function medbeafgallery_add_all_category_image_setting($settings) {
    $all_category_id = get_option('medbeafgallery_all_category_id');
    $image_id = 0;

    if ($all_category_id) {
        $image_id = get_term_meta($all_category_id, 'medbeafgallery_category_image', true);
    }

    $image_url = '';
    if ($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'medium');
    }

    $settings_fields = $settings['fields'];

    // Add a section for the "All" category image
    $settings_fields['all_category_image'] = [
    'title' => __('All Categories Image', 'medical-before-after-gallery'),
        'type' => 'image',
        'default' => $image_id,
    'description' => __('Select an image to represent the "All" categories option in the frontend.', 'medical-before-after-gallery'),
        'callback' => function ($value) use ($image_url) {
            ?>
            <div class="medbeafgallery-image-uploader-field">
                <input type="hidden" name="medbeafgallery_settings[all_category_image]" id="medbeafgallery-all-category-image" value="<?php echo esc_attr($value); ?>">
                <div id="medbeafgallery-all-category-preview" class="medbeafgallery-image-preview" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
                    <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="medbeafgallery-image-actions">
                    <button type="button" class="button medbeafgallery-upload-image" id="medbeafgallery-upload-all-image">
                        <?php esc_html_e('Select Image', 'medical-before-after-gallery'); ?>
                    </button>
                    <button type="button" class="button medbeafgallery-remove-image" id="medbeafgallery-remove-all-image" style="<?php echo $image_url ? '' : 'display:none;'; ?>">
                        <?php esc_html_e('Remove Image', 'medical-before-after-gallery'); ?>
                    </button>
                </div>
            </div>
            <?php
        }
    ];

    $settings['fields'] = $settings_fields;
    return $settings;
}
add_filter('medbeafgallery_settings_fields', 'medbeafgallery_add_all_category_image_setting');

/**
 * Save "All" category image when settings are saved
 */
function medbeafgallery_save_all_category_image($old_values, $values) {
    if (isset($values['all_category_image'])) {
        $all_category_id = get_option('medbeafgallery_all_category_id');

        if ($all_category_id) {
            update_term_meta($all_category_id, 'medbeafgallery_category_image', $values['all_category_image']);
        }
    }
}
add_action('medbeafgallery_settings_saved', 'medbeafgallery_save_all_category_image', 10, 2);



/**
 * Remove the "All" category from the category checklist
 */
function medbeafgallery_exclude_all_category_from_checklist($args, $post_id) {
    global $current_screen;

    // Get the post type either from current screen or from the post object
    $current_post_type = null;

    // If we have a valid post ID, try to get its post type
    if (!empty($post_id) && is_numeric($post_id)) {
        $post = get_post($post_id);
        if ($post) {
            $current_post_type = $post->post_type;
        }
    }

    // If we couldn't get it that way, try getting it from the current screen
    if (!$current_post_type && $current_screen) {
        $current_post_type = $current_screen->post_type;
    }

    // If we still don't have it, try get_current_screen() as a fallback
    if (!$current_post_type && is_admin()) {
        $screen = get_current_screen();
        if ($screen && isset($screen->post_type)) {
            $current_post_type = $screen->post_type;
        }
    }

    // Only modify for our custom post type
    if ($current_post_type !== 'medbeafgallery_case') {
        return $args;
    }

    // Get the "All" category ID
    $all_category_id = get_option('medbeafgallery_all_category_id');
    if (!$all_category_id) {
        return $args;
    }

    // Add the All category ID to the exclude array
    if (!isset($args['exclude'])) {
        $args['exclude'] = array($all_category_id);
    } else {
        $args['exclude'][] = $all_category_id;
    }

    return $args;
}
add_filter('wp_terms_checklist_args', 'medbeafgallery_exclude_all_category_from_checklist', 10, 2);


/**
 * Prevent posts from being assigned to the "All" category
 */
function medbeafgallery_prevent_all_category_assignment($post_id) {
    // Skip if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Skip if this is not our post type
    if (get_post_type($post_id) !== 'medbeafgallery_case') {
        return;
    }

    // Get the "All" category ID
    $all_category_id = get_option('medbeafgallery_all_category_id');
    if (!$all_category_id) {
        return;
    }

    // Check if this post has been assigned to the "All" category
    if (has_term($all_category_id, 'medbeafgallery_category', $post_id)) {
        // Remove the "All" category from this post
        wp_remove_object_terms($post_id, $all_category_id, 'medbeafgallery_category');
    }
}
add_action('save_post', 'medbeafgallery_prevent_all_category_assignment', 20);


/**
 * Hide the "All" category checkbox in the post editor
 */


function medbeafgallery_hide_all_category() {
    global $typenow;
    if ($typenow !== 'medbeafgallery_case') return;

    $all_category_id = get_option('medbeafgallery_all_category_id');
    if (!$all_category_id) return;

    $custom_css = '
        #in-medbeafgallery_category-' . intval($all_category_id) . '-1 {
            display: none !important;
        }
        label[for="in-medbeafgallery_category-' . intval($all_category_id) . '"] {
            display: none !important;
        }
    ';

    // Register a dummy admin style handle if not already registered
    wp_register_style('medbeafgallery-admin-inline', false);
    wp_enqueue_style('medbeafgallery-admin-inline');
    wp_add_inline_style('medbeafgallery-admin-inline', $custom_css);
}
add_action('admin_enqueue_scripts', 'medbeafgallery_hide_all_category');


