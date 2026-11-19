<?php
/**
 * Case editor layout — post.php / post-new.php for `medbeafgallery_case`.
 *
 * Turns the generic WordPress post screen into a guided form without replacing
 * anything underneath: every metabox (free and Pro) keeps its markup, fields,
 * nonces and save handlers. This file only
 *
 * - prints a header band (back link, status, a five-step checklist with jump
 *   links, and proxy Save/Publish buttons that click WordPress's own ones);
 * - puts the photos box first, directly under the title, in its own metabox
 *   area ("medbeafgallery_lead") — a real sortable area, so drag-to-reorder and
 *   Screen Options keep working and a user's saved order is respected;
 * - moves the "Gallery Shortcode" box to the bottom of the sidebar;
 * - styles the boxes as cards (admin/css/case-editor.css).
 *
 * @package Medical_Before_After_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Metabox context of the area under the title.
 */
if (!defined('MEDBEAFGALLERY_CASE_LEAD_CONTEXT')) {
    define('MEDBEAFGALLERY_CASE_LEAD_CONTEXT', 'medbeafgallery_lead');
}

/**
 * Is the current admin screen the case editor (classic editor)?
 *
 * @return bool
 */
function medbeafgallery_ce_is_screen() {
    if (!function_exists('get_current_screen')) {
        return false;
    }
    $screen = get_current_screen();
    return $screen && 'post' === $screen->base && 'medbeafgallery_case' === $screen->post_type
        && !(method_exists($screen, 'is_block_editor') && $screen->is_block_editor());
}

/**
 * URL of the Cases screen (or WordPress's list when that screen is absent).
 *
 * @return string
 */
function medbeafgallery_ce_cases_url() {
    if (function_exists('medbeafgallery_cs_url') && function_exists('medbeafgallery_cases_manager_page')) {
        return medbeafgallery_cs_url();
    }
    return admin_url('edit.php?post_type=medbeafgallery_case');
}

/**
 * Move a registered metabox to another context/priority (no-op when absent).
 *
 * @param string $id
 * @param string $to_context
 * @param string $to_priority
 */
function medbeafgallery_ce_move_box($id, $to_context, $to_priority) {
    global $wp_meta_boxes;
    $page = 'medbeafgallery_case';
    if (empty($wp_meta_boxes[$page]) || !is_array($wp_meta_boxes[$page])) {
        return;
    }
    foreach ($wp_meta_boxes[$page] as $context => $priorities) {
        if (!is_array($priorities)) {
            continue;
        }
        foreach ($priorities as $priority => $boxes) {
            if (!is_array($boxes) || empty($boxes[$id])) {
                continue;
            }
            $box = $boxes[$id];
            unset($wp_meta_boxes[$page][$context][$priority][$id]);
            $wp_meta_boxes[$page][$to_context][$to_priority][$id] = $box;
            return;
        }
    }
}

/**
 * Arrange the boxes after everyone (free, Pro, others) has registered theirs.
 */
function medbeafgallery_ce_arrange_boxes() {
    if (!medbeafgallery_ce_is_screen()) {
        return;
    }
    // Photos first, right under the title.
    medbeafgallery_ce_move_box('medbeafgallery_image_pairs', MEDBEAFGALLERY_CASE_LEAD_CONTEXT, 'high');
    // The gallery shortcode is the same on every case: keep it, but last.
    medbeafgallery_ce_move_box('medbeafgallery_shortcode', 'side', 'low');
}
add_action('add_meta_boxes_medbeafgallery_case', 'medbeafgallery_ce_arrange_boxes', 999);

/**
 * A box order saved before this layout existed lists the photos box in
 * "normal"; that saved order would pull it back down. Until the user saves an
 * order that knows the new area, read it with the photos box taken out (it
 * then stays where medbeafgallery_ce_arrange_boxes() put it). Read-only: the
 * stored preference is not changed.
 *
 * @param mixed $order
 * @return mixed
 */
function medbeafgallery_ce_saved_order($order) {
    if (!is_array($order) || isset($order[MEDBEAFGALLERY_CASE_LEAD_CONTEXT]) || !medbeafgallery_ce_is_screen()) {
        return $order;
    }
    foreach ($order as $context => $ids) {
        if (!is_string($ids) || '' === $ids) {
            continue;
        }
        $list = array_values(array_diff(explode(',', $ids), array('medbeafgallery_image_pairs')));
        $order[$context] = implode(',', $list);
    }
    $order[MEDBEAFGALLERY_CASE_LEAD_CONTEXT] = 'medbeafgallery_image_pairs';
    return $order;
}
add_filter('get_user_option_meta-box-order_medbeafgallery_case', 'medbeafgallery_ce_saved_order');

/**
 * The area under the title (a sortable metabox area of its own).
 *
 * @param WP_Post $post
 */
function medbeafgallery_ce_lead_area($post) {
    if (!$post || 'medbeafgallery_case' !== $post->post_type || !medbeafgallery_ce_is_screen()) {
        return;
    }
    echo '<div class="medbeafgallery-ce-lead" id="medbeafgallery-ce-lead">';
    do_meta_boxes(get_current_screen(), MEDBEAFGALLERY_CASE_LEAD_CONTEXT, $post);
    echo '</div>';
}
add_action('edit_form_after_title', 'medbeafgallery_ce_lead_area');

/**
 * Title placeholder.
 *
 * @param string  $text
 * @param WP_Post $post
 * @return string
 */
function medbeafgallery_ce_title_placeholder($text, $post) {
    if ($post && 'medbeafgallery_case' === $post->post_type) {
        return __('Case title, e.g. “Rhinoplasty — 6 months after”', 'medical-before-after-gallery');
    }
    return $text;
}
add_filter('enter_title_here', 'medbeafgallery_ce_title_placeholder', 10, 2);

/**
 * Status label for the header band.
 *
 * @param WP_Post $post
 * @return array label, class
 */
function medbeafgallery_ce_status($post) {
    switch ($post->post_status) {
        case 'publish':
            return array(__('Published', 'medical-before-after-gallery'), 'publish');
        case 'future':
            return array(__('Scheduled', 'medical-before-after-gallery'), 'future');
        case 'private':
            return array(__('Private', 'medical-before-after-gallery'), 'private');
        case 'pending':
            return array(__('Pending review', 'medical-before-after-gallery'), 'pending');
        case 'draft':
            return array(__('Draft', 'medical-before-after-gallery'), 'draft');
    }
    return array(__('Not saved yet', 'medical-before-after-gallery'), 'new');
}

/**
 * The steps of the checklist: key => array(label, target id).
 *
 * @return array
 */
function medbeafgallery_ce_steps() {
    return array(
        'title'    => array(__('Title', 'medical-before-after-gallery'), 'titlediv'),
        'photos'   => array(__('Photos', 'medical-before-after-gallery'), 'medbeafgallery_image_pairs'),
        'category' => array(__('Category', 'medical-before-after-gallery'), 'medbeafgallery_categorydiv'),
        'details'  => array(__('Details', 'medical-before-after-gallery'), 'postdivrich'),
        'publish'  => array(__('Publish', 'medical-before-after-gallery'), 'submitdiv'),
    );
}

/**
 * Header band, at the top of the edit form.
 *
 * @param WP_Post $post
 */
function medbeafgallery_ce_band($post) {
    if (!$post || 'medbeafgallery_case' !== $post->post_type || !medbeafgallery_ce_is_screen()) {
        return;
    }
    $is_new = 'auto-draft' === $post->post_status;
    $status = medbeafgallery_ce_status($post);
    ?>
    <div class="medbeafgallery-ce-band" id="medbeafgallery-ce-band">
        <div class="medbeafgallery-ce-band-main">
            <a class="medbeafgallery-ce-back" href="<?php echo esc_url(medbeafgallery_ce_cases_url()); ?>">
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e('Cases', 'medical-before-after-gallery'); ?>
            </a>
            <div class="medbeafgallery-ce-heading">
                <span class="medbeafgallery-ce-eyebrow"><?php echo $is_new ? esc_html__('New case', 'medical-before-after-gallery') : esc_html__('Edit case', 'medical-before-after-gallery'); ?></span>
                <span class="medbeafgallery-ce-status is-<?php echo esc_attr($status[1]); ?>" id="medbeafgallery-ce-status"><?php echo esc_html($status[0]); ?></span>
                <span class="medbeafgallery-ce-progress" id="medbeafgallery-ce-progress" aria-live="polite"></span>
            </div>
        </div>

        <nav class="medbeafgallery-ce-steps-wrap" aria-label="<?php esc_attr_e('Case checklist', 'medical-before-after-gallery'); ?>">
            <ol class="medbeafgallery-ce-steps" id="medbeafgallery-ce-steps">
                <?php
                $n = 0;
                foreach (medbeafgallery_ce_steps() as $key => $step) :
                    $n++;
                    ?>
                    <li class="medbeafgallery-ce-step" data-step="<?php echo esc_attr($key); ?>">
                        <a href="#<?php echo esc_attr($step[1]); ?>" data-target="<?php echo esc_attr($step[1]); ?>">
                            <span class="medbeafgallery-ce-step-dot" aria-hidden="true"><span class="medbeafgallery-ce-step-num"><?php echo (int) $n; ?></span></span>
                            <span class="medbeafgallery-ce-step-label"><?php echo esc_html($step[0]); ?></span>
                            <span class="screen-reader-text medbeafgallery-ce-step-state"></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="medbeafgallery-ce-actions" id="medbeafgallery-ce-actions" hidden>
            <button type="button" class="button medbeafgallery-ce-proxy" data-proxy="save-post"></button>
            <button type="button" class="button button-primary medbeafgallery-ce-proxy" data-proxy="publish"></button>
        </div>
    </div>
    <?php
}
add_action('edit_form_top', 'medbeafgallery_ce_band');

/**
 * Body class for scoping the styles.
 *
 * @param string $classes
 * @return string
 */
function medbeafgallery_ce_body_class($classes) {
    if (medbeafgallery_ce_is_screen()) {
        $classes .= ' medbeafgallery-ce';
    }
    return $classes;
}
add_filter('admin_body_class', 'medbeafgallery_ce_body_class');

/**
 * Enqueue the layout's assets (case editor only).
 *
 * @param string $hook_suffix
 */
function medbeafgallery_ce_enqueue($hook_suffix) {
    if (('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) || !medbeafgallery_ce_is_screen()) {
        return;
    }
    $version = defined('MEDBEAFGALLERY_VERSION') ? MEDBEAFGALLERY_VERSION : '1.5.0';
    $base    = plugin_dir_url(__FILE__);

    wp_enqueue_style('medbeafgallery-case-editor', $base . 'css/case-editor.css', array(), $version);
    wp_enqueue_script('medbeafgallery-case-editor', $base . 'js/case-editor.js', array('postbox'), $version, true);

    wp_localize_script('medbeafgallery-case-editor', 'medbeafgalleryCaseEditor', array(
        'allCategoryId' => (int) get_option('medbeafgallery_all_category_id', 0),
        'i18n'          => array(
            'done'        => __('done', 'medical-before-after-gallery'),
            'todo'        => __('to do', 'medical-before-after-gallery'),
            'optional'    => __('optional', 'medical-before-after-gallery'),
            /* translators: 1: steps done, 2: number of steps */
            'progress'    => __('%1$d of %2$d done', 'medical-before-after-gallery'),
            'allDone'     => __('Ready', 'medical-before-after-gallery'),
            'description' => __('Description', 'medical-before-after-gallery'),
            'descHint'    => __('What was treated, how, and over what time. Shown with the photos.', 'medical-before-after-gallery'),
            'titleLabel'  => __('Case title', 'medical-before-after-gallery'),
        ),
    ));
}
add_action('admin_enqueue_scripts', 'medbeafgallery_ce_enqueue');
