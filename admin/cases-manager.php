<?php
/**
 * Cases screen — a card grid of the gallery's cases for everyday work.
 *
 * Replaces WordPress's list table for the `medbeafgallery_case` post type as the
 * "All Cases" screen. The list table stays reachable ("Classic list") and keeps
 * the menu highlight. Nothing about the data changes: this screen reads posts,
 * their meta and terms, and writes only post status (publish, draft, trash,
 * restore, delete permanently) through WordPress's own functions.
 *
 * The page is rendered on the server (filters are a GET form, actions are
 * WordPress's own nonce links), so it works without JavaScript. The script
 * swaps in results from one admin-ajax action, `medbeafgallery_cases_manager`
 * (nonce + per-post capability checks), which returns the same server-rendered
 * HTML.
 *
 * Registered by admin/menu.php:
 *   add_submenu_page('medbeafgallery-gallery', …, MEDBEAFGALLERY_CASES_PAGE,
 *       'medbeafgallery_cases_manager_page');
 *
 * @package Medical_Before_After_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MEDBEAFGALLERY_CASES_PAGE')) {
    define('MEDBEAFGALLERY_CASES_PAGE', 'medbeafgallery-cases');
}

/**
 * Cases per page.
 */
if (!defined('MEDBEAFGALLERY_CASES_PER_PAGE')) {
    define('MEDBEAFGALLERY_CASES_PER_PAGE', 24);
}

/**
 * Capability of the case post type for an action ('edit_posts', 'publish_posts', 'create_posts', …).
 *
 * @param string $which Capability key on the post type's cap object.
 * @return string
 */
function medbeafgallery_cs_cap($which) {
    $type = get_post_type_object('medbeafgallery_case');
    if ($type && isset($type->cap->$which)) {
        return $type->cap->$which;
    }
    return 'edit_posts';
}

/**
 * Screen URL, optionally with filter state.
 *
 * @param array $state From medbeafgallery_cs_read_state(); defaults are left out.
 * @return string
 */
function medbeafgallery_cs_url($state = array()) {
    $args = array('page' => MEDBEAFGALLERY_CASES_PAGE);
    if (!empty($state)) {
        if (!empty($state['status']) && 'all' !== $state['status']) {
            $args['status'] = $state['status'];
        }
        if (!empty($state['q'])) {
            $args['q'] = $state['q'];
        }
        if (!empty($state['cat'])) {
            $args['cat'] = (int) $state['cat'];
        }
        if (!empty($state['doctor'])) {
            $args['doctor'] = (int) $state['doctor'];
        }
        if (!empty($state['sort']) && 'newest' !== $state['sort']) {
            $args['sort'] = $state['sort'];
        }
        if (!empty($state['paged']) && (int) $state['paged'] > 1) {
            $args['paged'] = (int) $state['paged'];
        }
    }
    return add_query_arg(array_map('rawurlencode', $args), admin_url('admin.php'));
}

/**
 * URL of WordPress's own list table for cases.
 *
 * @return string
 */
function medbeafgallery_cs_classic_url() {
    return admin_url('edit.php?post_type=medbeafgallery_case');
}

/**
 * Status filters shown on the screen: key => label.
 *
 * @return array
 */
function medbeafgallery_cs_status_filters() {
    return array(
        'all'     => __('All', 'medical-before-after-gallery'),
        'publish' => __('Published', 'medical-before-after-gallery'),
        'draft'   => __('Drafts', 'medical-before-after-gallery'),
        'pending' => __('Pending', 'medical-before-after-gallery'),
        'trash'   => __('Trash', 'medical-before-after-gallery'),
    );
}

/**
 * Statuses that "All" covers.
 *
 * @return string[]
 */
function medbeafgallery_cs_all_statuses() {
    return array('publish', 'draft', 'pending', 'private', 'future');
}

/**
 * Sort options: key => label.
 *
 * @return array
 */
function medbeafgallery_cs_sorts() {
    return array(
        'newest'   => __('Newest first', 'medical-before-after-gallery'),
        'oldest'   => __('Oldest first', 'medical-before-after-gallery'),
        'title'    => __('Title A–Z', 'medical-before-after-gallery'),
        'modified' => __('Recently edited', 'medical-before-after-gallery'),
    );
}

/**
 * Filter state from a request array ($_GET or $_POST), sanitised.
 *
 * @param array $source Unslashed request values.
 * @return array status, q, cat, doctor, sort, paged
 */
function medbeafgallery_cs_read_state($source) {
    $status = isset($source['status']) ? sanitize_key($source['status']) : 'all';
    if (!array_key_exists($status, medbeafgallery_cs_status_filters())) {
        $status = 'all';
    }
    $sort = isset($source['sort']) ? sanitize_key($source['sort']) : 'newest';
    if (!array_key_exists($sort, medbeafgallery_cs_sorts())) {
        $sort = 'newest';
    }
    $q = isset($source['q']) && is_scalar($source['q']) ? sanitize_text_field((string) $source['q']) : '';
    if (function_exists('mb_substr')) {
        $q = mb_substr($q, 0, 200);
    }

    $cat = isset($source['cat']) ? absint($source['cat']) : 0;
    if ($cat && (!term_exists($cat, 'medbeafgallery_category') || $cat === medbeafgallery_cs_all_id())) {
        $cat = 0;
    }
    $doctor = isset($source['doctor']) ? absint($source['doctor']) : 0;
    if ($doctor && (!taxonomy_exists('medbeafgallery_doctor') || !term_exists($doctor, 'medbeafgallery_doctor'))) {
        $doctor = 0;
    }

    return array(
        'status' => $status,
        'q'      => trim($q),
        'cat'    => $cat,
        'doctor' => $doctor,
        'sort'   => $sort,
        'paged'  => isset($source['paged']) ? max(1, absint($source['paged'])) : 1,
    );
}

/**
 * Are any filters (other than status and sort) active?
 *
 * @param array $state
 * @return bool
 */
function medbeafgallery_cs_is_filtered($state) {
    return '' !== $state['q'] || $state['cat'] || $state['doctor'];
}

/**
 * Term ID of the system "All" category (0 when there is none).
 *
 * @return int
 */
function medbeafgallery_cs_all_id() {
    return (int) get_option('medbeafgallery_all_category_id', 0);
}

/**
 * The query for a filter state.
 *
 * @param array $state
 * @return WP_Query
 */
function medbeafgallery_cs_query($state) {
    $args = array(
        'post_type'              => 'medbeafgallery_case',
        'post_status'            => 'all' === $state['status'] ? medbeafgallery_cs_all_statuses() : array($state['status']),
        'posts_per_page'         => MEDBEAFGALLERY_CASES_PER_PAGE,
        'paged'                  => max(1, (int) $state['paged']),
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
        'ignore_sticky_posts'    => true,
    );

    if ('' !== $state['q']) {
        $args['s'] = $state['q'];
    }

    $tax = array();
    if ($state['cat']) {
        $tax[] = array(
            'taxonomy'         => 'medbeafgallery_category',
            'field'            => 'term_id',
            'terms'            => array((int) $state['cat']),
            'include_children' => true,
        );
    }
    if ($state['doctor']) {
        $tax[] = array(
            'taxonomy' => 'medbeafgallery_doctor',
            'field'    => 'term_id',
            'terms'    => array((int) $state['doctor']),
        );
    }
    if ($tax) {
        $tax['relation']   = 'AND';
        $args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
    }

    switch ($state['sort']) {
        case 'oldest':
            $args['orderby'] = array('date' => 'ASC', 'ID' => 'ASC');
            break;
        case 'title':
            $args['orderby'] = array('title' => 'ASC', 'ID' => 'ASC');
            break;
        case 'modified':
            $args['orderby'] = array('modified' => 'DESC', 'ID' => 'DESC');
            break;
        default:
            $args['orderby'] = array('date' => 'DESC', 'ID' => 'DESC');
    }

    $query = new WP_Query($args);

    // A page past the end (e.g. after deleting the last case on it): show the last page.
    if ($query->max_num_pages > 0 && $args['paged'] > $query->max_num_pages) {
        $args['paged'] = (int) $query->max_num_pages;
        $query         = new WP_Query($args);
    }

    return $query;
}

/**
 * Case counts per status filter.
 *
 * @return array key => int (all, publish, draft, pending, trash)
 */
function medbeafgallery_cs_counts() {
    $counts = wp_count_posts('medbeafgallery_case');
    $out    = array('all' => 0);
    foreach (medbeafgallery_cs_all_statuses() as $status) {
        $out['all'] += isset($counts->$status) ? (int) $counts->$status : 0;
    }
    foreach (array('publish', 'draft', 'pending', 'trash') as $status) {
        $out[$status] = isset($counts->$status) ? (int) $counts->$status : 0;
    }
    return $out;
}

/**
 * Free-plan usage (counted the way the free plugin's limit counts: published, drafts, private).
 *
 * @return array used, max, limited, at_limit
 */
function medbeafgallery_cs_usage() {
    $max    = function_exists('medbeafgallery_get_max_cases') ? (int) medbeafgallery_get_max_cases() : -1;
    $counts = wp_count_posts('medbeafgallery_case');
    $used   = (int) $counts->publish + (int) $counts->draft + (int) $counts->private;

    return array(
        'used'     => $used,
        'max'      => $max,
        'limited'  => -1 !== $max,
        'at_limit' => -1 !== $max && $used >= $max,
    );
}

/**
 * Usage line, e.g. "9 of 12 cases". Empty when unlimited.
 *
 * @param array $usage
 * @return string
 */
function medbeafgallery_cs_usage_text($usage) {
    if (!$usage['limited']) {
        return '';
    }
    return sprintf(
        /* translators: %1$d: cases in use, %2$d: cases the free plan allows */
        _n('%1$d of %2$d case used', '%1$d of %2$d cases used', $usage['max'], 'medical-before-after-gallery'),
        $usage['used'],
        $usage['max']
    );
}

/**
 * Number of before/after photo pairs a case has (main pair + extra pairs with a photo).
 *
 * @param int $post_id
 * @return int
 */
function medbeafgallery_cs_pair_count($post_id) {
    $count  = 0;
    $before = absint(get_post_meta($post_id, '_medbeafgallery_main_before_image', true));
    $after  = absint(get_post_meta($post_id, '_medbeafgallery_main_after_image', true));
    if ($before || $after) {
        $count++;
    }
    $pairs = get_post_meta($post_id, '_medbeafgallery_additional_image_pairs', true);
    if (is_array($pairs)) {
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $b = !empty($pair['before_image']) ? $pair['before_image'] : (!empty($pair['before_id']) ? $pair['before_id'] : 0);
            $a = !empty($pair['after_image']) ? $pair['after_image'] : (!empty($pair['after_id']) ? $pair['after_id'] : 0);
            if (absint($b) || absint($a)) {
                $count++;
            }
        }
    }
    return $count;
}

/**
 * Status badge label.
 *
 * @param string $status
 * @return string
 */
function medbeafgallery_cs_status_label($status) {
    switch ($status) {
        case 'publish':
            return __('Published', 'medical-before-after-gallery');
        case 'draft':
            return __('Draft', 'medical-before-after-gallery');
        case 'pending':
            return __('Pending review', 'medical-before-after-gallery');
        case 'private':
            return __('Private', 'medical-before-after-gallery');
        case 'future':
            return __('Scheduled', 'medical-before-after-gallery');
        case 'trash':
            return __('In trash', 'medical-before-after-gallery');
    }
    $object = get_post_status_object($status);
    return $object ? $object->label : $status;
}

/**
 * A case's title as plain text ("(no title)" when empty).
 *
 * @param WP_Post $post
 * @return string
 */
function medbeafgallery_cs_title($post) {
    $title = trim(wp_specialchars_decode(wp_strip_all_tags((string) $post->post_title), ENT_QUOTES));
    return '' === $title ? __('(no title)', 'medical-before-after-gallery') : $title;
}

/**
 * Pro's readiness issues for a case (empty when Pro does not provide them).
 *
 * @param WP_Post $post
 * @return array[] Each: message, severity
 */
function medbeafgallery_cs_issues($post) {
    if ('trash' === $post->post_status || !function_exists('mba_gallery_pro_case_issues')) {
        return array();
    }
    $issues = mba_gallery_pro_case_issues($post->ID);
    if (!is_array($issues)) {
        return array();
    }
    $out = array();
    foreach ($issues as $issue) {
        if (is_array($issue) && !empty($issue['message'])) {
            $out[] = array(
                'message'  => (string) $issue['message'],
                'severity' => isset($issue['severity']) ? (string) $issue['severity'] : 'warning',
            );
        } elseif (is_string($issue) && '' !== $issue) {
            $out[] = array('message' => $issue, 'severity' => 'warning');
        }
    }
    return $out;
}

/**
 * Restore link (WordPress's own, works without JavaScript).
 *
 * @param int $post_id
 * @return string
 */
function medbeafgallery_cs_untrash_url($post_id) {
    return wp_nonce_url(admin_url(sprintf('post.php?post=%d&action=untrash', $post_id)), 'untrash-post_' . $post_id);
}

/**
 * One photo half of a card thumbnail.
 *
 * @param int    $attachment_id
 * @param string $label "Before" / "After"
 * @param string $which before|after
 */
function medbeafgallery_cs_thumb_half($attachment_id, $label, $which) {
    $url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
    ?>
    <span class="medbeafgallery-cs-half is-<?php echo esc_attr($which); ?><?php echo $url ? '' : ' is-missing'; ?>">
        <?php if ($url) : ?>
            <img src="<?php echo esc_url($url); ?>" alt="" loading="lazy" decoding="async">
        <?php else : ?>
            <span class="medbeafgallery-cs-missing">
                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                <span class="medbeafgallery-cs-missing-text"><?php esc_html_e('No photo', 'medical-before-after-gallery'); ?></span>
            </span>
        <?php endif; ?>
        <span class="medbeafgallery-cs-half-label"><?php echo esc_html($label); ?></span>
    </span>
    <?php
}

/**
 * One case card.
 *
 * @param WP_Post $post
 * @param array   $context all_id, can_duplicate (bool), at_limit (bool)
 */
function medbeafgallery_cs_render_card($post, $context) {
    $id       = (int) $post->ID;
    $title    = medbeafgallery_cs_title($post);
    $is_trash = 'trash' === $post->post_status;
    $can_edit = current_user_can('edit_post', $id);
    $can_del  = current_user_can('delete_post', $id);
    $edit_url = $can_edit && !$is_trash ? get_edit_post_link($id, 'raw') : '';
    $before   = absint(get_post_meta($id, '_medbeafgallery_main_before_image', true));
    $after    = absint(get_post_meta($id, '_medbeafgallery_main_after_image', true));
    $pairs    = medbeafgallery_cs_pair_count($id);
    $issues   = medbeafgallery_cs_issues($post);

    $cats = get_the_terms($post, 'medbeafgallery_category');
    $cats = is_array($cats) ? array_values(array_filter($cats, function ($term) use ($context) {
        return (int) $term->term_id !== (int) $context['all_id'];
    })) : array();
    $doctors = taxonomy_exists('medbeafgallery_doctor') ? get_the_terms($post, 'medbeafgallery_doctor') : array();
    $doctors = is_array($doctors) ? $doctors : array();

    $modified = (int) get_post_modified_time('U', true, $post);
    $edited   = $modified ? sprintf(
        /* translators: %s: time since the case was last edited, e.g. "3 days" */
        __('Edited %s ago', 'medical-before-after-gallery'),
        human_time_diff($modified, time())
    ) : '';

    $can_duplicate = !$is_trash && !empty($context['can_duplicate']) && empty($context['at_limit'])
        && function_exists('mba_gallery_pro_photo_tools_can_duplicate')
        && mba_gallery_pro_photo_tools_can_duplicate($post);

    $issues_id = 'medbeafgallery-cs-issues-' . $id;
    $label_id  = 'medbeafgallery-cs-title-' . $id;
    ?>
    <li class="medbeafgallery-cs-card is-<?php echo esc_attr($post->post_status); ?>" data-id="<?php echo esc_attr($id); ?>" data-status="<?php echo esc_attr($post->post_status); ?>" data-title="<?php echo esc_attr($title); ?>">
        <div class="medbeafgallery-cs-media">
            <?php if ($edit_url) : ?>
                <a class="medbeafgallery-cs-thumbs" href="<?php echo esc_url($edit_url); ?>" tabindex="-1" aria-hidden="true">
            <?php else : ?>
                <span class="medbeafgallery-cs-thumbs">
            <?php endif; ?>
                <?php
                medbeafgallery_cs_thumb_half($before, __('Before', 'medical-before-after-gallery'), 'before');
                medbeafgallery_cs_thumb_half($after, __('After', 'medical-before-after-gallery'), 'after');
                ?>
            <?php echo $edit_url ? '</a>' : '</span>'; ?>
            <?php if ($can_edit || $can_del) : ?>
                <span class="medbeafgallery-cs-select">
                    <input type="checkbox" class="medbeafgallery-cs-check" id="medbeafgallery-cs-check-<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($id); ?>" aria-labelledby="medbeafgallery-cs-check-label-<?php echo esc_attr($id); ?>">
                    <span class="screen-reader-text" id="medbeafgallery-cs-check-label-<?php echo esc_attr($id); ?>"><?php
                        /* translators: %s: case title */
                        echo esc_html(sprintf(__('Select “%s”', 'medical-before-after-gallery'), $title));
                    ?></span>
                </span>
            <?php endif; ?>
        </div>

        <div class="medbeafgallery-cs-body">
            <div class="medbeafgallery-cs-badges">
                <span class="medbeafgallery-cs-status is-<?php echo esc_attr($post->post_status); ?>"><?php echo esc_html(medbeafgallery_cs_status_label($post->post_status)); ?></span>
                <?php if ($issues) : ?>
                    <button type="button" class="medbeafgallery-cs-issues-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($issues_id); ?>">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <?php
                        /* translators: %d: number of things to fix before the case is ready */
                        echo esc_html(sprintf(_n('%d to fix', '%d to fix', count($issues), 'medical-before-after-gallery'), count($issues)));
                        ?>
                        <span class="screen-reader-text"><?php
                            /* translators: %s: case title */
                            echo esc_html(sprintf(__('in “%s”', 'medical-before-after-gallery'), $title));
                        ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <h3 class="medbeafgallery-cs-title" id="<?php echo esc_attr($label_id); ?>">
                <?php if ($edit_url) : ?>
                    <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($title); ?></a>
                <?php else : ?>
                    <?php echo esc_html($title); ?>
                <?php endif; ?>
            </h3>

            <?php if ($cats) : ?>
                <ul class="medbeafgallery-cs-chips" aria-label="<?php esc_attr_e('Categories', 'medical-before-after-gallery'); ?>">
                    <?php
                    $shown = array_slice($cats, 0, 3);
                    foreach ($shown as $term) :
                        ?>
                        <li class="medbeafgallery-cs-chip"><?php echo esc_html(wp_specialchars_decode($term->name, ENT_QUOTES)); ?></li>
                    <?php endforeach; ?>
                    <?php if (count($cats) > 3) : ?>
                        <li class="medbeafgallery-cs-chip is-more" title="<?php echo esc_attr(implode(', ', array_map(function ($term) {
                            return wp_specialchars_decode($term->name, ENT_QUOTES);
                        }, array_slice($cats, 3)))); ?>"><?php
                            /* translators: %d: number of further categories */
                            echo esc_html(sprintf(__('+%d more', 'medical-before-after-gallery'), count($cats) - 3));
                        ?></li>
                    <?php endif; ?>
                </ul>
            <?php else : ?>
                <p class="medbeafgallery-cs-chips is-empty"><?php esc_html_e('No category', 'medical-before-after-gallery'); ?></p>
            <?php endif; ?>

            <ul class="medbeafgallery-cs-facts">
                <?php if ($doctors) : ?>
                    <li class="medbeafgallery-cs-fact is-doctor">
                        <span class="dashicons dashicons-id" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Doctor:', 'medical-before-after-gallery'); ?></span>
                        <?php echo esc_html(implode(', ', array_map(function ($term) {
                            return wp_specialchars_decode($term->name, ENT_QUOTES);
                        }, $doctors))); ?>
                    </li>
                <?php endif; ?>
                <li class="medbeafgallery-cs-fact is-pairs<?php echo $pairs ? '' : ' is-none'; ?>">
                    <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                    <?php
                    if ($pairs) {
                        /* translators: %d: number of before/after photo pairs */
                        echo esc_html(sprintf(_n('%d photo pair', '%d photo pairs', $pairs, 'medical-before-after-gallery'), $pairs));
                    } else {
                        esc_html_e('No photos yet', 'medical-before-after-gallery');
                    }
                    ?>
                </li>
                <?php if ($edited) : ?>
                    <li class="medbeafgallery-cs-fact is-edited">
                        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                        <time datetime="<?php echo esc_attr(gmdate('c', $modified)); ?>" title="<?php echo esc_attr(get_post_modified_time(get_option('date_format') . ' ' . get_option('time_format'), false, $post, true)); ?>"><?php echo esc_html($edited); ?></time>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($issues) : ?>
                <div class="medbeafgallery-cs-issues" id="<?php echo esc_attr($issues_id); ?>" hidden>
                    <ul>
                        <?php foreach ($issues as $issue) : ?>
                            <li class="is-<?php echo esc_attr('error' === $issue['severity'] ? 'error' : 'warning'); ?>"><?php echo esc_html($issue['message']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($edit_url) : ?>
                        <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Open the case to fix these', 'medical-before-after-gallery'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="medbeafgallery-cs-actions">
                <?php if (!$is_trash) : ?>
                    <?php if ($edit_url) : ?>
                        <a class="button medbeafgallery-cs-edit" href="<?php echo esc_url($edit_url); ?>" aria-describedby="<?php echo esc_attr($label_id); ?>"><?php esc_html_e('Edit', 'medical-before-after-gallery'); ?></a>
                    <?php endif; ?>
                    <?php if ($can_duplicate) : ?>
                        <a class="medbeafgallery-cs-link" href="<?php echo esc_url(mba_gallery_pro_photo_tools_duplicate_url($id)); ?>" aria-describedby="<?php echo esc_attr($label_id); ?>"><?php esc_html_e('Duplicate', 'medical-before-after-gallery'); ?></a>
                    <?php endif; ?>
                    <?php if ($can_del) : ?>
                        <a class="medbeafgallery-cs-link is-danger" href="<?php echo esc_url(get_delete_post_link($id)); ?>" data-op="trash" aria-describedby="<?php echo esc_attr($label_id); ?>"><?php esc_html_e('Move to trash', 'medical-before-after-gallery'); ?></a>
                    <?php endif; ?>
                <?php elseif ($can_del) : ?>
                    <a class="button medbeafgallery-cs-restore" href="<?php echo esc_url(medbeafgallery_cs_untrash_url($id)); ?>" data-op="restore" aria-describedby="<?php echo esc_attr($label_id); ?>"><?php esc_html_e('Restore', 'medical-before-after-gallery'); ?></a>
                    <a class="medbeafgallery-cs-link is-danger" href="<?php echo esc_url(get_delete_post_link($id, '', true)); ?>" data-op="delete" aria-describedby="<?php echo esc_attr($label_id); ?>"><?php esc_html_e('Delete permanently', 'medical-before-after-gallery'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </li>
    <?php
}

/**
 * Status tabs (with counts) as HTML.
 *
 * @param array $state
 * @param array $counts
 * @return string
 */
function medbeafgallery_cs_tabs_html($state, $counts) {
    ob_start();
    ?>
    <ul class="medbeafgallery-cs-tabs">
        <?php foreach (medbeafgallery_cs_status_filters() as $key => $label) :
            if ('pending' === $key && empty($counts['pending']) && 'pending' !== $state['status']) {
                continue; // Most practices never use "Pending review".
            }
            $current = $key === $state['status'];
            $url     = medbeafgallery_cs_url(array_merge($state, array('status' => $key, 'paged' => 1)));
            ?>
            <li>
                <a href="<?php echo esc_url($url); ?>" class="medbeafgallery-cs-tab<?php echo $current ? ' is-current' : ''; ?><?php echo 'trash' === $key ? ' is-trash' : ''; ?>" data-status="<?php echo esc_attr($key); ?>"<?php echo $current ? ' aria-current="page"' : ''; ?>>
                    <?php echo esc_html($label); ?>
                    <span class="medbeafgallery-cs-tab-count"><?php echo esc_html(number_format_i18n(isset($counts[$key]) ? $counts[$key] : 0)); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return ob_get_clean();
}

/**
 * Results (summary, grid or empty state, pagination) as HTML.
 *
 * @param array $state  Filter state; 'paged' may be corrected to the last page.
 * @param array $counts From medbeafgallery_cs_counts().
 * @return array html, total, pages, paged
 */
function medbeafgallery_cs_results($state, $counts) {
    $query = medbeafgallery_cs_query($state);
    $paged = max(1, (int) $query->get('paged'));
    $total = (int) $query->found_posts;
    $pages = max(1, (int) $query->max_num_pages);
    $usage = medbeafgallery_cs_usage();

    $context = array(
        'all_id'        => medbeafgallery_cs_all_id(),
        'can_duplicate' => function_exists('mba_gallery_pro_photo_tools_duplicate_url') && function_exists('mba_gallery_pro_photo_tools_can_duplicate'),
        'at_limit'      => $usage['at_limit'],
    );

    $state['paged'] = $paged;
    $filtered       = medbeafgallery_cs_is_filtered($state);
    $nothing_at_all = 0 === $counts['all'] && 0 === $counts['trash'];

    ob_start();

    if ($total > 0) {
        $from = ($paged - 1) * MEDBEAFGALLERY_CASES_PER_PAGE + 1;
        $to   = min($total, $paged * MEDBEAFGALLERY_CASES_PER_PAGE);
        ?>
        <p class="medbeafgallery-cs-summary" id="medbeafgallery-cs-summary" tabindex="-1"><?php
            if ($pages > 1) {
                echo esc_html(sprintf(
                    /* translators: 1: first case shown, 2: last case shown, 3: total cases */
                    __('Showing %1$d–%2$d of %3$d cases', 'medical-before-after-gallery'),
                    $from,
                    $to,
                    $total
                ));
            } else {
                /* translators: %d: number of cases */
                echo esc_html(sprintf(_n('%d case', '%d cases', $total, 'medical-before-after-gallery'), $total));
            }
        ?></p>
        <ul class="medbeafgallery-cs-grid" id="medbeafgallery-cs-grid" aria-labelledby="medbeafgallery-cs-summary">
            <?php
            foreach ($query->posts as $post) {
                medbeafgallery_cs_render_card($post, $context);
            }
            ?>
        </ul>
        <?php if ($pages > 1) : ?>
            <nav class="medbeafgallery-cs-pagination" aria-label="<?php esc_attr_e('Pages of cases', 'medical-before-after-gallery'); ?>">
                <?php if ($paged > 1) : ?>
                    <a class="button" href="<?php echo esc_url(medbeafgallery_cs_url(array_merge($state, array('paged' => $paged - 1)))); ?>" data-paged="<?php echo esc_attr($paged - 1); ?>" rel="prev"><span aria-hidden="true">‹ </span><?php esc_html_e('Previous', 'medical-before-after-gallery'); ?></a>
                <?php else : ?>
                    <span class="button disabled" aria-hidden="true"><span aria-hidden="true">‹ </span><?php esc_html_e('Previous', 'medical-before-after-gallery'); ?></span>
                <?php endif; ?>
                <span class="medbeafgallery-cs-page-of"><?php
                    /* translators: 1: current page, 2: number of pages */
                    echo esc_html(sprintf(__('Page %1$d of %2$d', 'medical-before-after-gallery'), $paged, $pages));
                ?></span>
                <?php if ($paged < $pages) : ?>
                    <a class="button" href="<?php echo esc_url(medbeafgallery_cs_url(array_merge($state, array('paged' => $paged + 1)))); ?>" data-paged="<?php echo esc_attr($paged + 1); ?>" rel="next"><?php esc_html_e('Next', 'medical-before-after-gallery'); ?><span aria-hidden="true"> ›</span></a>
                <?php else : ?>
                    <span class="button disabled" aria-hidden="true"><?php esc_html_e('Next', 'medical-before-after-gallery'); ?><span aria-hidden="true"> ›</span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
        <?php
    } elseif ($nothing_at_all) {
        $can_create = current_user_can(medbeafgallery_cs_cap('create_posts'));
        ?>
        <div class="medbeafgallery-cs-empty is-first" id="medbeafgallery-cs-summary" tabindex="-1">
            <div class="medbeafgallery-cs-empty-art" aria-hidden="true"><span></span><span></span></div>
            <h2 class="medbeafgallery-cs-empty-title"><?php esc_html_e('No cases yet', 'medical-before-after-gallery'); ?></h2>
            <p><?php esc_html_e('A case is one patient’s before and after photos, with a title and a category. Add one and it appears in your gallery as soon as you publish it.', 'medical-before-after-gallery'); ?></p>
            <?php if ($can_create) : ?>
                <a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('post-new.php?post_type=medbeafgallery_case')); ?>"><?php esc_html_e('Add your first case', 'medical-before-after-gallery'); ?></a>
            <?php endif; ?>
        </div>
        <?php
    } else {
        ?>
        <div class="medbeafgallery-cs-empty" id="medbeafgallery-cs-summary" tabindex="-1">
            <span class="dashicons <?php echo 'trash' === $state['status'] ? 'dashicons-trash' : 'dashicons-search'; ?>" aria-hidden="true"></span>
            <?php if ($filtered) : ?>
                <h2 class="medbeafgallery-cs-empty-title"><?php esc_html_e('No cases match these filters', 'medical-before-after-gallery'); ?></h2>
                <p><a href="<?php echo esc_url(medbeafgallery_cs_url(array('status' => $state['status'], 'sort' => $state['sort']))); ?>" class="medbeafgallery-cs-clear" data-clear="1"><?php esc_html_e('Clear the search and filters', 'medical-before-after-gallery'); ?></a></p>
            <?php elseif ('trash' === $state['status']) : ?>
                <h2 class="medbeafgallery-cs-empty-title"><?php esc_html_e('The trash is empty', 'medical-before-after-gallery'); ?></h2>
                <p><?php esc_html_e('Cases you move to the trash wait here until you restore them or delete them permanently.', 'medical-before-after-gallery'); ?></p>
            <?php else : ?>
                <h2 class="medbeafgallery-cs-empty-title"><?php
                    echo esc_html(sprintf(
                        /* translators: %s: status filter name, e.g. "Drafts" */
                        __('Nothing in “%s”', 'medical-before-after-gallery'),
                        medbeafgallery_cs_status_filters()[$state['status']]
                    ));
                ?></h2>
                <p><a href="<?php echo esc_url(medbeafgallery_cs_url()); ?>" data-status="all" class="medbeafgallery-cs-clear"><?php esc_html_e('Show all cases', 'medical-before-after-gallery'); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    return array(
        'html'  => ob_get_clean(),
        'total' => $total,
        'pages' => $pages,
        'paged' => $paged,
    );
}

/**
 * Does this screen's hook belong to the Cases screen?
 *
 * @param string $hook_suffix
 * @return bool
 */
function medbeafgallery_cs_is_screen($hook_suffix) {
    if ('medical-before-after-gallery_page_' . MEDBEAFGALLERY_CASES_PAGE === $hook_suffix) {
        return true;
    }
    // The parent menu title is translatable, which changes the hook suffix.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return MEDBEAFGALLERY_CASES_PAGE === $page && is_string($hook_suffix) && false !== strpos($hook_suffix, '_page_' . MEDBEAFGALLERY_CASES_PAGE);
}

/**
 * Enqueue the screen's assets (this screen only).
 *
 * @param string $hook_suffix
 */
function medbeafgallery_cs_enqueue($hook_suffix) {
    if (!medbeafgallery_cs_is_screen($hook_suffix) || !current_user_can(medbeafgallery_cs_cap('edit_posts'))) {
        return;
    }

    $version = defined('MEDBEAFGALLERY_VERSION') ? MEDBEAFGALLERY_VERSION : '1.5.0';
    $base    = plugin_dir_url(__FILE__);

    wp_enqueue_style('medbeafgallery-cases-manager', $base . 'css/cases-manager.css', array('dashicons'), $version);
    wp_enqueue_script('medbeafgallery-cases-manager', $base . 'js/cases-manager.js', array(), $version, true);

    $type = get_post_type_object('medbeafgallery_case');

    wp_localize_script('medbeafgallery-cases-manager', 'medbeafgalleryCasesManager', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action'  => 'medbeafgallery_cases_manager',
        'nonce'   => wp_create_nonce('medbeafgallery_cases_manager'),
        'pageUrl' => medbeafgallery_cs_url(),
        'viewKey' => 'medbeafgalleryCasesView-' . get_current_user_id(),
        'can'     => array(
            'publish' => $type && current_user_can($type->cap->publish_posts),
        ),
        'i18n'    => array(
            'working'        => __('Working…', 'medical-before-after-gallery'),
            'loading'        => __('Loading cases…', 'medical-before-after-gallery'),
            'networkError'   => __('That did not work. Check your connection and try again.', 'medical-before-after-gallery'),
            'undo'           => __('Undo', 'medical-before-after-gallery'),
            'dismiss'        => __('Dismiss', 'medical-before-after-gallery'),
            'cancel'         => __('Cancel', 'medical-before-after-gallery'),
            'deleteForever'  => __('Delete permanently', 'medical-before-after-gallery'),
            /* translators: %s: case title */
            'deleteQuestion' => __('Delete “%s” permanently?', 'medical-before-after-gallery'),
            'deleteNote'     => __('This cannot be undone. Its photos stay in the Media Library.', 'medical-before-after-gallery'),
            /* translators: %d: number of cases */
            'bulkDeleteQuestionOne'  => __('Delete %d case permanently?', 'medical-before-after-gallery'),
            /* translators: %d: number of cases */
            'bulkDeleteQuestionMany' => __('Delete %d cases permanently?', 'medical-before-after-gallery'),
            /* translators: %d: number of selected cases */
            'selectedOne'    => __('%d selected', 'medical-before-after-gallery'),
            /* translators: %d: number of selected cases */
            'selectedMany'   => __('%d selected', 'medical-before-after-gallery'),
            'noneSelected'   => __('Select cases to publish, unpublish or trash several at once.', 'medical-before-after-gallery'),
            'noneSelectedTrash' => __('Select cases to restore or delete several at once.', 'medical-before-after-gallery'),
            'gridView'       => __('Grid', 'medical-before-after-gallery'),
            'listView'       => __('List', 'medical-before-after-gallery'),
            'showIssues'     => __('Show what to fix', 'medical-before-after-gallery'),
        ),
    ));
}
add_action('admin_enqueue_scripts', 'medbeafgallery_cs_enqueue');

/**
 * Highlight "All Cases" on WordPress's own list table too, and give that list a
 * way back to this screen.
 *
 * @param array $views
 * @return array
 */
function medbeafgallery_cs_classic_views($views) {
    if (function_exists('medbeafgallery_cases_manager_page')) {
        $views['medbeafgallery_cards'] = '<a href="' . esc_url(medbeafgallery_cs_url()) . '">' . esc_html__('Card view', 'medical-before-after-gallery') . '</a>';
    }
    return $views;
}
add_filter('views_edit-medbeafgallery_case', 'medbeafgallery_cs_classic_views');

/**
 * Everything the screen re-renders after a change.
 *
 * @param array $state
 * @return array
 */
function medbeafgallery_cs_state_payload($state) {
    $counts  = medbeafgallery_cs_counts();
    $results = medbeafgallery_cs_results($state, $counts);
    $usage   = medbeafgallery_cs_usage();
    $state['paged'] = $results['paged'];

    return array(
        'results'   => $results['html'],
        'tabs'      => medbeafgallery_cs_tabs_html($state, $counts),
        'total'     => $results['total'],
        'pages'     => $results['pages'],
        'paged'     => $results['paged'],
        'counts'    => $counts,
        'usage'     => $usage,
        'usageText' => medbeafgallery_cs_usage_text($usage),
        'url'       => medbeafgallery_cs_url($state),
        'status'    => $state['status'],
    );
}

/**
 * Is there room under the free plan for one more counted case?
 *
 * @return bool
 */
function medbeafgallery_cs_has_room() {
    return !function_exists('medbeafgallery_check_case_limit') || medbeafgallery_check_case_limit();
}

/**
 * The limit message shown when a change would go over the free plan.
 *
 * @return string
 */
function medbeafgallery_cs_limit_message() {
    $max = function_exists('medbeafgallery_get_max_cases') ? (int) medbeafgallery_get_max_cases() : 12;
    return sprintf(
        /* translators: %d: number of cases the free plan allows */
        __('The free plan holds %d cases (published, drafts and private). Move one to the trash first, or upgrade to the Pro add-on for unlimited cases.', 'medical-before-after-gallery'),
        $max
    );
}

/**
 * Apply one change to one case. Every check happens here.
 *
 * @param string $op     trash|restore|undo|delete|publish|draft
 * @param int    $id
 * @return true|WP_Error
 */
function medbeafgallery_cs_apply($op, $id) {
    $post = get_post($id);
    if (!$post || 'medbeafgallery_case' !== $post->post_type) {
        return new WP_Error('missing', __('That case no longer exists.', 'medical-before-after-gallery'));
    }
    $counted = array('publish', 'draft', 'private');

    switch ($op) {
        case 'trash':
            if (!current_user_can('delete_post', $id)) {
                return new WP_Error('cap', __('You are not allowed to move this case to the trash.', 'medical-before-after-gallery'));
            }
            if ('trash' === $post->post_status) {
                return true;
            }
            return wp_trash_post($id) ? true : new WP_Error('failed', __('The case could not be moved to the trash.', 'medical-before-after-gallery'));

        case 'restore':
        case 'undo':
            if (!current_user_can('delete_post', $id)) {
                return new WP_Error('cap', __('You are not allowed to restore this case.', 'medical-before-after-gallery'));
            }
            if ('trash' !== $post->post_status) {
                return true;
            }
            if (!medbeafgallery_cs_has_room()) {
                return new WP_Error('limit', medbeafgallery_cs_limit_message());
            }
            // "Undo" right after trashing puts the case back exactly as it was;
            // "Restore" follows WordPress and brings it back as a draft.
            if ('undo' === $op && function_exists('wp_untrash_post_set_previous_status')) {
                add_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3);
            }
            $done = wp_untrash_post($id);
            if ('undo' === $op && function_exists('wp_untrash_post_set_previous_status')) {
                remove_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10);
            }
            return $done ? true : new WP_Error('failed', __('The case could not be restored.', 'medical-before-after-gallery'));

        case 'delete':
            if (!current_user_can('delete_post', $id)) {
                return new WP_Error('cap', __('You are not allowed to delete this case.', 'medical-before-after-gallery'));
            }
            if ('trash' !== $post->post_status) {
                return new WP_Error('not_trashed', __('Only cases in the trash can be deleted permanently.', 'medical-before-after-gallery'));
            }
            return wp_delete_post($id, true) ? true : new WP_Error('failed', __('The case could not be deleted.', 'medical-before-after-gallery'));

        case 'publish':
        case 'draft':
            if (!current_user_can('edit_post', $id)) {
                return new WP_Error('cap', __('You are not allowed to edit this case.', 'medical-before-after-gallery'));
            }
            if ('publish' === $op && !current_user_can(medbeafgallery_cs_cap('publish_posts'))) {
                return new WP_Error('cap', __('You are not allowed to publish cases.', 'medical-before-after-gallery'));
            }
            if ('trash' === $post->post_status) {
                return new WP_Error('trashed', __('Restore the case from the trash first.', 'medical-before-after-gallery'));
            }
            if ($op === $post->post_status) {
                return true;
            }
            if (!in_array($post->post_status, $counted, true) && !medbeafgallery_cs_has_room()) {
                return new WP_Error('limit', medbeafgallery_cs_limit_message());
            }
            $result = wp_update_post(array('ID' => $id, 'post_status' => $op), true);
            return is_wp_error($result) ? $result : true;
    }

    return new WP_Error('unknown', __('Unknown action.', 'medical-before-after-gallery'));
}

/**
 * Message after a change.
 *
 * @param string $op
 * @param int    $done   Cases changed.
 * @param string $title  Title when one case changed.
 * @return string
 */
function medbeafgallery_cs_done_message($op, $done, $title) {
    if (1 === $done && '' !== $title) {
        switch ($op) {
            case 'trash':
                /* translators: %s: case title */
                return sprintf(__('“%s” moved to the trash.', 'medical-before-after-gallery'), $title);
            case 'restore':
                /* translators: %s: case title */
                return sprintf(__('“%s” restored as a draft.', 'medical-before-after-gallery'), $title);
            case 'undo':
                /* translators: %s: case title */
                return sprintf(__('“%s” is back.', 'medical-before-after-gallery'), $title);
            case 'delete':
                /* translators: %s: case title */
                return sprintf(__('“%s” deleted permanently.', 'medical-before-after-gallery'), $title);
            case 'publish':
                /* translators: %s: case title */
                return sprintf(__('“%s” published.', 'medical-before-after-gallery'), $title);
            case 'draft':
                /* translators: %s: case title */
                return sprintf(__('“%s” moved to drafts.', 'medical-before-after-gallery'), $title);
        }
    }
    switch ($op) {
        case 'trash':
            /* translators: %d: number of cases */
            return sprintf(_n('%d case moved to the trash.', '%d cases moved to the trash.', $done, 'medical-before-after-gallery'), $done);
        case 'restore':
            /* translators: %d: number of cases */
            return sprintf(_n('%d case restored as a draft.', '%d cases restored as drafts.', $done, 'medical-before-after-gallery'), $done);
        case 'undo':
            /* translators: %d: number of cases */
            return sprintf(_n('%d case is back.', '%d cases are back.', $done, 'medical-before-after-gallery'), $done);
        case 'delete':
            /* translators: %d: number of cases */
            return sprintf(_n('%d case deleted permanently.', '%d cases deleted permanently.', $done, 'medical-before-after-gallery'), $done);
        case 'publish':
            /* translators: %d: number of cases */
            return sprintf(_n('%d case published.', '%d cases published.', $done, 'medical-before-after-gallery'), $done);
        case 'draft':
            /* translators: %d: number of cases */
            return sprintf(_n('%d case moved to drafts.', '%d cases moved to drafts.', $done, 'medical-before-after-gallery'), $done);
    }
    return '';
}

/**
 * AJAX: list and change cases.
 *
 * POST: nonce, op = list|trash|restore|undo|delete|publish|draft, ids[] (for changes),
 *       plus the filter state (status, q, cat, doctor, sort, paged).
 * Success: medbeafgallery_cs_state_payload() + message, done, failed[], ids[]
 */
function medbeafgallery_cs_ajax() {
    if (!check_ajax_referer('medbeafgallery_cases_manager', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Your session has expired. Please reload the page and try again.', 'medical-before-after-gallery')), 403);
    }
    if (!current_user_can(medbeafgallery_cs_cap('edit_posts'))) {
        wp_send_json_error(array('message' => __('You are not allowed to manage cases.', 'medical-before-after-gallery')), 403);
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitised in medbeafgallery_cs_read_state()
    $state = medbeafgallery_cs_read_state(wp_unslash($_POST));
    $op    = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : 'list';

    if ('list' === $op) {
        wp_send_json_success(medbeafgallery_cs_state_payload($state));
    }

    if (!in_array($op, array('trash', 'restore', 'undo', 'delete', 'publish', 'draft'), true)) {
        wp_send_json_error(array('message' => __('Unknown action.', 'medical-before-after-gallery')), 400);
    }

    $raw = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint below
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    $ids = array_slice(array_values(array_unique(array_filter(array_map('absint', (array) $raw)))), 0, 200);
    if (empty($ids)) {
        wp_send_json_error(array('message' => __('No cases were selected.', 'medical-before-after-gallery')), 400);
    }

    $done   = array();
    $failed = array();
    $title  = '';
    foreach ($ids as $id) {
        $post   = get_post($id);
        $result = medbeafgallery_cs_apply($op, $id);
        if (true === $result) {
            $done[] = $id;
            if ($post) {
                $title = medbeafgallery_cs_title($post);
            }
        } else {
            $failed[] = array(
                'id'      => $id,
                'title'   => $post ? medbeafgallery_cs_title($post) : '',
                'message' => $result->get_error_message(),
            );
        }
    }

    if (function_exists('medbeafgallery_clear_cache') && $done) {
        medbeafgallery_clear_cache($done[0]);
    }

    $payload            = medbeafgallery_cs_state_payload($state);
    $payload['op']      = $op;
    $payload['ids']     = $done;
    $payload['done']    = count($done);
    $payload['failed']  = $failed;
    $payload['message'] = $done ? medbeafgallery_cs_done_message($op, count($done), 1 === count($done) ? $title : '') : '';

    if (empty($done)) {
        $payload['message'] = $failed ? $failed[0]['message'] : __('Nothing changed.', 'medical-before-after-gallery');
        wp_send_json_error($payload, 400);
    }
    wp_send_json_success($payload);
}
add_action('wp_ajax_medbeafgallery_cases_manager', 'medbeafgallery_cs_ajax');

/**
 * Category <option>s (tree order, "All" left out).
 *
 * @param int $selected
 */
function medbeafgallery_cs_category_options($selected) {
    $rows = array();
    if (function_exists('medbeafgallery_cm_get_tree')) {
        foreach (medbeafgallery_cm_get_tree() as $node) {
            $rows[] = array('id' => (int) $node['term']->term_id, 'name' => $node['term']->name, 'depth' => (int) $node['depth']);
        }
    } else {
        $terms = get_terms(array('taxonomy' => 'medbeafgallery_category', 'hide_empty' => false));
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                if ((int) $term->term_id !== medbeafgallery_cs_all_id()) {
                    $rows[] = array('id' => (int) $term->term_id, 'name' => $term->name, 'depth' => 0);
                }
            }
        }
    }
    foreach ($rows as $row) {
        printf(
            '<option value="%1$d"%2$s>%3$s%4$s</option>',
            (int) $row['id'],
            selected($selected, $row['id'], false),
            str_repeat('&nbsp;&nbsp;&nbsp;', max(0, $row['depth'])),
            esc_html(wp_specialchars_decode($row['name'], ENT_QUOTES))
        );
    }
}

/**
 * Admin page callback.
 */
function medbeafgallery_cases_manager_page() {
    if (!current_user_can(medbeafgallery_cs_cap('edit_posts'))) {
        wp_die(esc_html__('Sorry, you are not allowed to manage cases.', 'medical-before-after-gallery'), 403);
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters
    $state   = medbeafgallery_cs_read_state(wp_unslash($_GET));
    $counts  = medbeafgallery_cs_counts();
    $results = medbeafgallery_cs_results($state, $counts);
    $state['paged'] = $results['paged'];
    $usage   = medbeafgallery_cs_usage();
    $upgrade = 'https://medicalbeforeaftergallery.com/';
    $add_url = admin_url('post-new.php?post_type=medbeafgallery_case');
    $can_add = current_user_can(medbeafgallery_cs_cap('create_posts'));

    $doctors = taxonomy_exists('medbeafgallery_doctor') ? get_terms(array('taxonomy' => 'medbeafgallery_doctor', 'hide_empty' => false, 'orderby' => 'name')) : array();
    $doctors = is_wp_error($doctors) ? array() : $doctors;
    ?>
    <div class="wrap medbeafgallery-cs" id="medbeafgallery-cs">
        <div class="medbeafgallery-cs-head">
            <h1 class="medbeafgallery-cs-heading"><?php esc_html_e('Cases', 'medical-before-after-gallery'); ?></h1>
            <?php if ($can_add) : ?>
                <?php // At the free limit the link has no href (WordPress would redirect back anyway); the script toggles it. ?>
                <a class="button button-primary medbeafgallery-cs-add<?php echo $usage['at_limit'] ? ' disabled' : ''; ?>" id="medbeafgallery-cs-add" data-href="<?php echo esc_url($add_url); ?>"
                    <?php echo $usage['at_limit'] ? 'role="link" aria-disabled="true" aria-describedby="medbeafgallery-cs-usage-text"' : 'href="' . esc_url($add_url) . '"'; ?>>
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e('Add case', 'medical-before-after-gallery'); ?>
                </a>
            <?php endif; ?>
        </div>
        <hr class="wp-header-end">

        <div class="medbeafgallery-cs-usage<?php echo $usage['at_limit'] ? ' is-full' : ''; ?>" id="medbeafgallery-cs-usage" <?php echo $usage['limited'] ? '' : 'hidden'; ?>>
            <span class="medbeafgallery-cs-usage-text" id="medbeafgallery-cs-usage-text"><?php echo esc_html(medbeafgallery_cs_usage_text($usage)); ?></span>
            <span class="medbeafgallery-cs-usage-meter" aria-hidden="true"><span style="width: <?php echo esc_attr($usage['max'] > 0 ? min(100, round($usage['used'] / $usage['max'] * 100)) : 0); ?>%"></span></span>
            <span class="medbeafgallery-cs-usage-note"><?php
                echo $usage['at_limit']
                    ? esc_html__('The free plan is full.', 'medical-before-after-gallery')
                    : esc_html__('Free plan.', 'medical-before-after-gallery');
            ?></span>
            <a href="<?php echo esc_url($upgrade); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade to Pro for unlimited cases', 'medical-before-after-gallery'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'medical-before-after-gallery'); ?></span></a>
        </div>

        <div class="screen-reader-text" id="medbeafgallery-cs-live" role="status" aria-live="polite" aria-atomic="true"></div>
        <div class="medbeafgallery-cs-notice" id="medbeafgallery-cs-notice" hidden></div>

        <noscript>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Turn on JavaScript to select several cases at once and to change cases without reloading the page. Everything else on this screen works without it.', 'medical-before-after-gallery'); ?></p>
            </div>
        </noscript>

        <nav class="medbeafgallery-cs-tabs-wrap" id="medbeafgallery-cs-tabs" aria-label="<?php esc_attr_e('Filter cases by status', 'medical-before-after-gallery'); ?>">
            <?php echo medbeafgallery_cs_tabs_html($state, $counts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built ?>
        </nav>

        <form class="medbeafgallery-cs-toolbar" id="medbeafgallery-cs-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" role="search" aria-label="<?php esc_attr_e('Find cases', 'medical-before-after-gallery'); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr(MEDBEAFGALLERY_CASES_PAGE); ?>">
            <input type="hidden" name="status" value="<?php echo esc_attr($state['status']); ?>" id="medbeafgallery-cs-status">
            <div class="medbeafgallery-cs-field is-search">
                <label for="medbeafgallery-cs-q" class="screen-reader-text"><?php esc_html_e('Search cases', 'medical-before-after-gallery'); ?></label>
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search" name="q" id="medbeafgallery-cs-q" value="<?php echo esc_attr($state['q']); ?>" placeholder="<?php esc_attr_e('Search cases…', 'medical-before-after-gallery'); ?>" autocomplete="off">
            </div>
            <div class="medbeafgallery-cs-field">
                <label for="medbeafgallery-cs-cat"><?php esc_html_e('Category', 'medical-before-after-gallery'); ?></label>
                <select name="cat" id="medbeafgallery-cs-cat">
                    <option value="0"><?php esc_html_e('All categories', 'medical-before-after-gallery'); ?></option>
                    <?php medbeafgallery_cs_category_options($state['cat']); ?>
                </select>
            </div>
            <?php if ($doctors) : ?>
                <div class="medbeafgallery-cs-field">
                    <label for="medbeafgallery-cs-doctor"><?php esc_html_e('Doctor', 'medical-before-after-gallery'); ?></label>
                    <select name="doctor" id="medbeafgallery-cs-doctor">
                        <option value="0"><?php esc_html_e('All doctors', 'medical-before-after-gallery'); ?></option>
                        <?php foreach ($doctors as $doctor) : ?>
                            <option value="<?php echo esc_attr($doctor->term_id); ?>" <?php selected($state['doctor'], (int) $doctor->term_id); ?>><?php echo esc_html(wp_specialchars_decode($doctor->name, ENT_QUOTES)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="medbeafgallery-cs-field">
                <label for="medbeafgallery-cs-sort"><?php esc_html_e('Sort', 'medical-before-after-gallery'); ?></label>
                <select name="sort" id="medbeafgallery-cs-sort">
                    <?php foreach (medbeafgallery_cs_sorts() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($state['sort'], $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="medbeafgallery-cs-field is-submit">
                <button type="submit" class="button"><?php esc_html_e('Apply', 'medical-before-after-gallery'); ?></button>
            </div>
            <div class="medbeafgallery-cs-view" id="medbeafgallery-cs-view" role="group" aria-label="<?php esc_attr_e('Layout', 'medical-before-after-gallery'); ?>" hidden>
                <button type="button" class="medbeafgallery-cs-view-button" data-view="grid" aria-pressed="true">
                    <span class="dashicons dashicons-grid-view" aria-hidden="true"></span><span class="medbeafgallery-cs-view-label"><?php esc_html_e('Grid', 'medical-before-after-gallery'); ?></span>
                </button>
                <button type="button" class="medbeafgallery-cs-view-button" data-view="list" aria-pressed="false">
                    <span class="dashicons dashicons-list-view" aria-hidden="true"></span><span class="medbeafgallery-cs-view-label"><?php esc_html_e('List', 'medical-before-after-gallery'); ?></span>
                </button>
            </div>
        </form>

        <div class="medbeafgallery-cs-bulk" id="medbeafgallery-cs-bulk" role="region" aria-label="<?php esc_attr_e('Change several cases', 'medical-before-after-gallery'); ?>" hidden>
            <label class="medbeafgallery-cs-bulk-all">
                <input type="checkbox" id="medbeafgallery-cs-select-all">
                <span><?php esc_html_e('Select all on this page', 'medical-before-after-gallery'); ?></span>
            </label>
            <span class="medbeafgallery-cs-bulk-count" id="medbeafgallery-cs-bulk-count"></span>
            <span class="medbeafgallery-cs-bulk-buttons" id="medbeafgallery-cs-bulk-buttons">
                <button type="button" class="button" data-bulk="publish" data-scope="live"><?php esc_html_e('Publish', 'medical-before-after-gallery'); ?></button>
                <button type="button" class="button" data-bulk="draft" data-scope="live"><?php esc_html_e('Move to draft', 'medical-before-after-gallery'); ?></button>
                <button type="button" class="button medbeafgallery-cs-danger" data-bulk="trash" data-scope="live"><?php esc_html_e('Move to trash', 'medical-before-after-gallery'); ?></button>
                <button type="button" class="button" data-bulk="restore" data-scope="trash"><?php esc_html_e('Restore', 'medical-before-after-gallery'); ?></button>
                <button type="button" class="button medbeafgallery-cs-danger" data-bulk="delete" data-scope="trash"><?php esc_html_e('Delete permanently', 'medical-before-after-gallery'); ?></button>
            </span>
            <div class="medbeafgallery-cs-bulk-confirm" id="medbeafgallery-cs-bulk-confirm" hidden></div>
        </div>

        <div class="medbeafgallery-cs-results" id="medbeafgallery-cs-results" data-status="<?php echo esc_attr($state['status']); ?>">
            <?php echo $results['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built ?>
        </div>

        <p class="medbeafgallery-cs-footer">
            <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
            <a href="<?php echo esc_url(medbeafgallery_cs_classic_url()); ?>"><?php esc_html_e('Classic list: open the standard WordPress cases table (quick edit, more columns)', 'medical-before-after-gallery'); ?></a>
        </p>
    </div>
    <?php
}
