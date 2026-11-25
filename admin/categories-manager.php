<?php
/**
 * Categories manager — a simple screen for the gallery's treatment categories.
 *
 * Replaces WordPress's generic term screen for everyday work (that screen stays
 * reachable through an "Advanced" link). The taxonomy underneath is unchanged:
 * `medbeafgallery_category`, hierarchical, default capabilities. This screen
 * adds one term meta key, `medbeafgallery_order` (int, 0..n among siblings),
 * which the gallery sorts by (terms without it come after, by name).
 *
 * The system term "All" (option `medbeafgallery_all_category_id`) is never
 * listed, offered as a parent, renamed, moved or deleted here — only its tile
 * image and the add-on fields flagged 'all' (e.g. Pro's icon) can be changed,
 * through op=all_edit.
 *
 * The integrator registers the page:
 *   add_submenu_page('medbeafgallery-gallery', …, 'manage_categories',
 *       MEDBEAFGALLERY_CATEGORIES_PAGE, 'medbeafgallery_categories_manager_page');
 *
 * @package Medical_Before_After_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MEDBEAFGALLERY_CATEGORIES_PAGE')) {
    define('MEDBEAFGALLERY_CATEGORIES_PAGE', 'medbeafgallery-categories');
}

/**
 * Term meta key holding a category's position among its siblings.
 */
if (!defined('MEDBEAFGALLERY_CATEGORY_ORDER_META')) {
    define('MEDBEAFGALLERY_CATEGORY_ORDER_META', 'medbeafgallery_order');
}

/**
 * Taxonomy capability for an action ('manage_terms', 'edit_terms', 'delete_terms').
 *
 * @param string $which Capability key on the taxonomy's cap object.
 * @return string
 */
function medbeafgallery_cm_cap($which) {
    $taxonomy = get_taxonomy('medbeafgallery_category');
    if ($taxonomy && isset($taxonomy->cap->$which)) {
        return $taxonomy->cap->$which;
    }
    return 'manage_categories';
}

/**
 * Term ID of the system "All" category (0 when there is none).
 *
 * @return int
 */
function medbeafgallery_cm_all_id() {
    return (int) get_option('medbeafgallery_all_category_id', 0);
}

/**
 * A term's name as plain text (WordPress stores names HTML-encoded, e.g. "&amp;").
 *
 * @param WP_Term $term
 * @return string
 */
function medbeafgallery_cm_plain_name($term) {
    return wp_specialchars_decode((string) $term->name, ENT_QUOTES);
}

/**
 * URL of the case list filtered to one category.
 *
 * The taxonomy is registered with 'query_var' => false, so the list is filtered
 * through the generic taxonomy/term query vars — the same link WordPress's own
 * list-table columns build for such a taxonomy.
 *
 * @param WP_Term $term
 * @return string
 */
function medbeafgallery_cm_cases_url($term) {
    return add_query_arg(
        array(
            'post_type' => 'medbeafgallery_case',
            'taxonomy'  => 'medbeafgallery_category',
            'term'      => $term->slug,
        ),
        admin_url('edit.php')
    );
}

/**
 * Every category except "All", in tree order (siblings by order, then name).
 *
 * Returns a flat list; each entry carries the WP_Term plus 'depth' and
 * 'parent' — the parent as this screen shows it: a term whose stored parent
 * is "All", missing, or part of a broken loop is shown at the top level.
 *
 * @return array[] Each: array('term' => WP_Term, 'parent' => int, 'depth' => int, 'order' => int|null)
 */
function medbeafgallery_cm_get_tree() {
    $all_id = medbeafgallery_cm_all_id();
    $terms  = get_terms(array(
        'taxonomy'               => 'medbeafgallery_category',
        'hide_empty'             => false,
        'update_term_meta_cache' => true,
    ));
    if (is_wp_error($terms) || empty($terms)) {
        return array();
    }

    $by_id = array();
    foreach ($terms as $term) {
        if ((int) $term->term_id === $all_id) {
            continue;
        }
        $by_id[(int) $term->term_id] = $term;
    }

    $children = array();
    foreach ($by_id as $id => $term) {
        $parent = (int) $term->parent;
        if (!$parent || !isset($by_id[$parent]) || $parent === $id) {
            $parent = 0;
        }
        $children[$parent][] = $term;
    }

    foreach ($children as $parent => $list) {
        usort($list, 'medbeafgallery_cm_compare_terms');
        $children[$parent] = $list;
    }

    $out  = array();
    $seen = array();
    $walk = function ($parent, $depth) use (&$walk, &$out, &$seen, $children) {
        if (empty($children[$parent])) {
            return;
        }
        foreach ($children[$parent] as $term) {
            $id = (int) $term->term_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = array(
                'term'   => $term,
                'parent' => $parent,
                'depth'  => $depth,
                'order'  => medbeafgallery_cm_get_order($id),
            );
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);

    // Terms in a parent loop are unreachable from the top; show them at the top
    // level rather than hiding them (moving one to "Top level" repairs it).
    foreach ($by_id as $id => $term) {
        if (!isset($seen[$id])) {
            $seen[$id] = true;
            $out[] = array(
                'term'   => $term,
                'parent' => 0,
                'depth'  => 0,
                'order'  => medbeafgallery_cm_get_order($id),
            );
        }
    }

    return $out;
}

/**
 * Stored order of a term, or null when it has none.
 *
 * @param int $term_id
 * @return int|null
 */
function medbeafgallery_cm_get_order($term_id) {
    $value = get_term_meta($term_id, MEDBEAFGALLERY_CATEGORY_ORDER_META, true);
    if ('' === $value || null === $value || false === $value || !is_numeric($value)) {
        return null;
    }
    return (int) $value;
}

/**
 * usort() callback: ordered terms first (by order), then the rest by name.
 *
 * @param WP_Term $a
 * @param WP_Term $b
 * @return int
 */
function medbeafgallery_cm_compare_terms($a, $b) {
    $oa = medbeafgallery_cm_get_order($a->term_id);
    $ob = medbeafgallery_cm_get_order($b->term_id);
    if (null !== $oa && null !== $ob && $oa !== $ob) {
        return $oa < $ob ? -1 : 1;
    }
    if (null !== $oa && null === $ob) {
        return -1;
    }
    if (null === $oa && null !== $ob) {
        return 1;
    }
    $by_name = strnatcasecmp(medbeafgallery_cm_plain_name($a), medbeafgallery_cm_plain_name($b));
    if (0 !== $by_name) {
        return $by_name;
    }
    return (int) $a->term_id - (int) $b->term_id;
}

/**
 * Row data sent to the browser for one category.
 *
 * @param array $node Entry from medbeafgallery_cm_get_tree().
 * @return array
 */
function medbeafgallery_cm_row_data($node) {
    $term     = $node['term'];
    $image_id = absint(get_term_meta($term->term_id, 'medbeafgallery_category_image', true));
    $image    = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

    return array(
        'id'        => (int) $term->term_id,
        'name'      => medbeafgallery_cm_plain_name($term),
        'slug'      => $term->slug,
        'parent'    => (int) $node['parent'],
        'depth'     => (int) $node['depth'],
        'count'     => (int) $term->count,
        'order'     => $node['order'],
        'image_id'  => $image ? $image_id : 0,
        'image_url' => $image ? $image : '',
        'description' => (string) $term->description,
        /**
         * Extra values an add-on edits on this screen (see the
         * medbeafgallery_categories_manager_fields filter), keyed by field.
         *
         * @since 1.5.0
         * @param array   $extra Field key => current value.
         * @param WP_Term $term  The category.
         */
        'extra'     => (array) apply_filters('medbeafgallery_categories_manager_row_extra', array(), $term),
        'edit_url'  => add_query_arg(
            array(
                'taxonomy'  => 'medbeafgallery_category',
                'tag_ID'    => (int) $term->term_id,
                'post_type' => 'medbeafgallery_case',
            ),
            admin_url('term.php')
        ),
        'cases_url' => medbeafgallery_cm_cases_url($term),
    );
}

/**
 * All rows, in tree order.
 *
 * @return array[]
 */
function medbeafgallery_cm_rows() {
    return array_map('medbeafgallery_cm_row_data', medbeafgallery_cm_get_tree());
}

/**
 * Data for the fixed "All cases" card.
 *
 * @return array|null Null when the site has no "All" term.
 */
function medbeafgallery_cm_all_data() {
    $all_id = medbeafgallery_cm_all_id();
    if (!$all_id) {
        return null;
    }
    $term = get_term($all_id, 'medbeafgallery_category');
    if (!$term || is_wp_error($term)) {
        return null;
    }
    $image_id = absint(get_term_meta($all_id, 'medbeafgallery_category_image', true));
    $image    = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

    return array(
        'id'        => $all_id,
        'image_id'  => $image ? $image_id : 0,
        'image_url' => $image ? $image : '',
        /** This filter is documented in medbeafgallery_cm_row_data(). */
        'extra'     => (array) apply_filters('medbeafgallery_categories_manager_row_extra', array(), $term),
    );
}

/**
 * Free-plan usage.
 *
 * @return array('used' => int, 'max' => int, 'limited' => bool, 'at_limit' => bool)
 */
function medbeafgallery_cm_usage() {
    $max  = function_exists('medbeafgallery_get_max_categories') ? (int) medbeafgallery_get_max_categories() : -1;
    $used = function_exists('medbeafgallery_get_category_count') ? (int) medbeafgallery_get_category_count() : 0;

    return array(
        'used'     => $used,
        'max'      => $max,
        'limited'  => -1 !== $max,
        'at_limit' => -1 !== $max && $used >= $max,
    );
}

/**
 * Usage line, e.g. "3 of 4 categories used". Empty when unlimited.
 *
 * @param array $usage From medbeafgallery_cm_usage().
 * @return string
 */
function medbeafgallery_cm_usage_text($usage) {
    if (!$usage['limited']) {
        return '';
    }
    return sprintf(
        /* translators: %1$d: categories in use, %2$d: categories the free plan allows */
        _n('%1$d of %2$d category used', '%1$d of %2$d categories used', $usage['max'], 'medical-before-after-gallery'),
        $usage['used'],
        $usage['max']
    );
}

/**
 * Drop the gallery's cached category data after a change.
 */
function medbeafgallery_cm_clear_caches() {
    // Same invalidation the plugin uses when cases change (medbeafgallery_clear_cache()).
    delete_transient('medbeafgallery_cases_cache');
    delete_transient('medbeafgallery_categories_cache');
}

/**
 * Does this screen's hook belong to the categories manager?
 *
 * @param string $hook_suffix
 * @return bool
 */
function medbeafgallery_cm_is_screen($hook_suffix) {
    if ('medical-before-after-gallery_page_' . MEDBEAFGALLERY_CATEGORIES_PAGE === $hook_suffix) {
        return true;
    }
    // The parent menu title is translatable, which changes the hook suffix.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return MEDBEAFGALLERY_CATEGORIES_PAGE === $page && is_string($hook_suffix) && false !== strpos($hook_suffix, '_page_' . MEDBEAFGALLERY_CATEGORIES_PAGE);
}

/**
 * Enqueue the screen's assets (this screen only).
 *
 * @param string $hook_suffix
 */
function medbeafgallery_cm_enqueue($hook_suffix) {
    if (!medbeafgallery_cm_is_screen($hook_suffix)) {
        return;
    }
    if (!current_user_can(medbeafgallery_cm_cap('manage_terms'))) {
        return;
    }

    $version = defined('MEDBEAFGALLERY_VERSION') ? MEDBEAFGALLERY_VERSION : '1.5.0';
    $base    = plugin_dir_url(__FILE__);

    wp_enqueue_media();
    wp_enqueue_style('medbeafgallery-categories-manager', $base . 'css/categories-manager.css', array(), $version);
    wp_enqueue_script('medbeafgallery-categories-manager', $base . 'js/categories-manager.js', array(), $version, true);

    $usage  = medbeafgallery_cm_usage();
    $is_pro = (bool) apply_filters('medbeafgallery_has_valid_pro_license', false);

    wp_localize_script('medbeafgallery-categories-manager', 'medbeafgalleryCategoriesManager', array(
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'action'      => 'medbeafgallery_categories_manager',
        'nonce'       => wp_create_nonce('medbeafgallery_categories_manager'),
        'rows'        => medbeafgallery_cm_rows(),
        'all'         => medbeafgallery_cm_all_data(),
        'usage'       => $usage,
        'usageText'   => medbeafgallery_cm_usage_text($usage),
        'upgradeUrl'  => 'https://medicalbeforeaftergallery.com/',
        'isPro'       => $is_pro,
        'fields'      => medbeafgallery_cm_extra_fields(),
        'can'         => array(
            'add'    => current_user_can(medbeafgallery_cm_cap('manage_terms')),
            'edit'   => current_user_can(medbeafgallery_cm_cap('edit_terms')),
            'delete' => current_user_can(medbeafgallery_cm_cap('delete_terms')),
        ),
        'suggestions' => array(
            __('Face', 'medical-before-after-gallery'),
            __('Nose', 'medical-before-after-gallery'),
            __('Breast', 'medical-before-after-gallery'),
            __('Body', 'medical-before-after-gallery'),
            __('Skin', 'medical-before-after-gallery'),
            __('Smile', 'medical-before-after-gallery'),
            __('Hair', 'medical-before-after-gallery'),
        ),
        'i18n'        => medbeafgallery_cm_js_strings(),
    ));
}
add_action('admin_enqueue_scripts', 'medbeafgallery_cm_enqueue');

/**
 * Field types the Edit panel understands. An add-on can check for a type
 * before offering a field that needs it (unknown types are skipped).
 *
 * @since 1.5.0
 * @return string[]
 */
function medbeafgallery_cm_field_types() {
    return array('choice', 'image', 'toggle');
}

/**
 * Extra fields an add-on shows in the Edit panel. Every field has key, label,
 * help, type, and optionally:
 *   all      (bool)  also offered for the "All cases" tile;
 *   show_if  { field, value } shown only while another field has that value.
 * Per type:
 *   choice   choices [ value, label, svg, group?, keywords?, pinned?, opens? ]
 *            display ('list' | 'grid'), groups [ key, label ], search (the
 *            filter box's label; '' = no box), auto_from (row value naming the
 *            choice whose icon the '' choice previews);
 *   image    an attachment from the Media Library — mimes [] (none: any image), tint_field
 *            (a toggle key: preview the image tinted while it is on);
 *   toggle   a checkbox — default (bool).
 * Older versions knew only 'choice' with value, label and svg; they skip
 * fields without choices and ignore the other keys. The SVG markup goes
 * through the plugin's SVG allow-list; everything else is plain text.
 *
 * @return array[]
 */
function medbeafgallery_cm_extra_fields() {
    /**
     * @since 1.5.0
     * @param array[] $fields Field definitions.
     */
    $fields = apply_filters('medbeafgallery_categories_manager_fields', array());
    $out    = array();
    foreach ((array) $fields as $field) {
        if (!is_array($field) || empty($field['key'])) {
            continue;
        }
        $type = isset($field['type']) ? sanitize_key((string) $field['type']) : 'choice';
        if (!in_array($type, medbeafgallery_cm_field_types(), true)) {
            continue;
        }
        $clean = array(
            'key'   => sanitize_key((string) $field['key']),
            'label' => wp_strip_all_tags(isset($field['label']) ? (string) $field['label'] : ''),
            'help'  => wp_strip_all_tags(isset($field['help']) ? (string) $field['help'] : ''),
            'type'  => $type,
            'all'   => !empty($field['all']),
        );
        if (isset($field['show_if']['field'], $field['show_if']['value']) && is_array($field['show_if'])) {
            $clean['show_if'] = array(
                'field' => sanitize_key((string) $field['show_if']['field']),
                'value' => sanitize_key((string) $field['show_if']['value']),
            );
        }

        if ('choice' === $type) {
            if (empty($field['choices']) || !is_array($field['choices'])) {
                continue;
            }
            $choices = array();
            foreach ($field['choices'] as $choice) {
                if (!is_array($choice) || !isset($choice['value'], $choice['label'])) {
                    continue;
                }
                $choices[] = array(
                    'value'    => sanitize_key((string) $choice['value']),
                    'label'    => wp_strip_all_tags((string) $choice['label']),
                    'svg'      => isset($choice['svg']) ? wp_kses((string) $choice['svg'], medbeafgallery_allowed_svg_html()) : '',
                    'group'    => isset($choice['group']) ? sanitize_key((string) $choice['group']) : '',
                    'keywords' => isset($choice['keywords']) ? wp_strip_all_tags((string) $choice['keywords']) : '',
                    'pinned'   => !empty($choice['pinned']),
                    'opens'    => isset($choice['opens']) ? sanitize_key((string) $choice['opens']) : '',
                );
            }
            $groups = array();
            if (!empty($field['groups']) && is_array($field['groups'])) {
                foreach ($field['groups'] as $group) {
                    if (is_array($group) && isset($group['key'], $group['label'])) {
                        $groups[] = array(
                            'key'   => sanitize_key((string) $group['key']),
                            'label' => wp_strip_all_tags((string) $group['label']),
                        );
                    }
                }
            }
            $clean['choices']   = $choices;
            $clean['display']   = (isset($field['display']) && 'grid' === $field['display']) ? 'grid' : 'list';
            $clean['groups']    = $groups;
            $clean['search']    = isset($field['search']) && is_string($field['search']) ? wp_strip_all_tags($field['search']) : '';
            $clean['auto_from'] = isset($field['auto_from']) ? sanitize_key((string) $field['auto_from']) : '';
        } elseif ('image' === $type) {
            $mimes = array();
            foreach (isset($field['mimes']) ? (array) $field['mimes'] : array() as $mime) {
                $mime = sanitize_mime_type((string) $mime);
                if (0 === strpos($mime, 'image/')) {
                    $mimes[] = $mime;
                }
            }
            $clean['mimes']      = $mimes; // Empty: any image.
            $clean['tint_field'] = isset($field['tint_field']) ? sanitize_key((string) $field['tint_field']) : '';
        } elseif ('toggle' === $type) {
            $clean['default'] = !isset($field['default']) || (bool) $field['default'];
        }
        $out[] = $clean;
    }
    return $out;
}

/**
 * Submitted add-on values from POST 'extra' (JSON), checked by the add-on
 * before anything is saved.
 *
 * @param int  $term_id  The category.
 * @param bool $all_only Keep only the fields offered for "All cases".
 * @return array|WP_Error Field key => value.
 */
function medbeafgallery_cm_post_extra($term_id, $all_only = false) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_cm_ajax()
    $extra = isset($_POST['extra']) && is_string($_POST['extra']) ? json_decode(wp_unslash($_POST['extra']), true) : array();
    $extra = is_array($extra) ? $extra : array();
    if ($all_only) {
        $keys = array();
        foreach (medbeafgallery_cm_extra_fields() as $field) {
            if (!empty($field['all'])) {
                $keys[$field['key']] = true;
            }
        }
        $extra = array_intersect_key($extra, $keys);
    }
    if ($extra) {
        /**
         * Refuse the add-on values before anything is saved: return a
         * WP_Error whose message the screen shows. Values are untrusted.
         *
         * @since 1.5.0
         * @param WP_Error|null $error   Null, or an earlier add-on's error.
         * @param int           $term_id The category.
         * @param array         $extra   Field key => submitted value.
         */
        $error = apply_filters('medbeafgallery_categories_manager_extra_error', null, $term_id, $extra);
        if (is_wp_error($error)) {
            return $error;
        }
    }
    return $extra;
}

/**
 * Strings used by categories-manager.js. "%s" is a category name, "%d" a number.
 *
 * @return array
 */
function medbeafgallery_cm_js_strings() {
    return array(
        'topLevel'          => __('Top level', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'reorder'           => __('Reorder “%s”', 'medical-before-after-gallery'),
        'reorderHint'       => __('Drag, or press the Up and Down arrow keys, to change the order.', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'moveUp'            => __('Move “%s” up', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'moveDown'          => __('Move “%s” down', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'chooseImage'       => __('Choose an image for “%s”', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'changeImage'       => __('Change the image for “%s”', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'renameLabel'       => __('Rename “%s”', 'medical-before-after-gallery'),
        'rename'            => __('Rename', 'medical-before-after-gallery'),
        'edit'              => __('Edit', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'editLabel'         => __('Edit “%s”', 'medical-before-after-gallery'),
        'nameLabel'         => __('Name', 'medical-before-after-gallery'),
        'descriptionLabel'  => __('Description (optional)', 'medical-before-after-gallery'),
        'descriptionHelp'   => __('A sentence or two about this treatment. The Pro treatment hub shows it at the top of the treatment’s page.', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'saved'             => __('“%s” saved.', 'medical-before-after-gallery'),
        'newName'           => __('New name', 'medical-before-after-gallery'),
        'save'              => __('Save', 'medical-before-after-gallery'),
        'cancel'            => __('Cancel', 'medical-before-after-gallery'),
        'addSub'            => __('Add subcategory', 'medical-before-after-gallery'),
        /* translators: %s: parent category name */
        'addSubLabel'       => __('New subcategory inside “%s”', 'medical-before-after-gallery'),
        'add'               => __('Add', 'medical-before-after-gallery'),
        'moveTo'            => __('Move to…', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'moveToLabel'       => __('Move “%s” to', 'medical-before-after-gallery'),
        'move'              => __('Move', 'medical-before-after-gallery'),
        'insideOf'          => __('Inside', 'medical-before-after-gallery'),
        'removeImage'       => __('Remove image', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'removeImageLabel'  => __('Remove the image of “%s”', 'medical-before-after-gallery'),
        'moreSettings'      => __('More settings', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'moreSettingsLabel' => __('More settings for “%s”', 'medical-before-after-gallery'),
        'delete'            => __('Delete', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'deleteLabel'       => __('Delete “%s”', 'medical-before-after-gallery'),
        'deleteConfirm'     => __('Delete category', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'deleteQuestion'    => __('Delete “%s”?', 'medical-before-after-gallery'),
        'deleteNoCases'     => __('No cases are filed under it.', 'medical-before-after-gallery'),
        /* translators: %d: number of cases */
        'deleteCasesOne'    => __('%d case is filed under it. It stays published but loses this category.', 'medical-before-after-gallery'),
        /* translators: %d: number of cases */
        'deleteCasesMany'   => __('%d cases are filed under it. They stay published but lose this category.', 'medical-before-after-gallery'),
        /* translators: %s: where the subcategories go ("the top level" or a category name) */
        'deleteChildren'    => __('Its subcategories move up to %s.', 'medical-before-after-gallery'),
        'theTopLevel'       => __('the top level', 'medical-before-after-gallery'),
        'noCases'           => __('No cases yet', 'medical-before-after-gallery'),
        /* translators: %d: number of cases */
        'casesOne'          => __('%d case', 'medical-before-after-gallery'),
        /* translators: %d: number of cases */
        'casesMany'         => __('%d cases', 'medical-before-after-gallery'),
        /* translators: %d: number of cases including subcategories */
        'casesTree'         => __('%d with subcategories', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'casesLinkLabel'    => __('See the cases filed under “%s”', 'medical-before-after-gallery'),
        'subcategoryOf'     => __('Subcategory', 'medical-before-after-gallery'),
        'noImage'           => __('No image', 'medical-before-after-gallery'),
        'mediaTitle'        => __('Choose a category image', 'medical-before-after-gallery'),
        'mediaButton'       => __('Use this image', 'medical-before-after-gallery'),
        'allTitle'          => __('All cases', 'medical-before-after-gallery'),
        'allNote'           => __('Always shown first in the gallery.', 'medical-before-after-gallery'),
        'allEdit'           => __('Edit', 'medical-before-after-gallery'),
        'allEditLabel'      => __('Edit the “All cases” tile', 'medical-before-after-gallery'),
        'allEditNote'       => __('“All cases” always comes first and cannot be renamed, moved or deleted.', 'medical-before-after-gallery'),
        'allSaved'          => __('“All cases” saved.', 'medical-before-after-gallery'),
        /* translators: %s: name of the chosen option, e.g. an icon */
        'chosen'            => __('Chosen: %s', 'medical-before-after-gallery'),
        'noMatch'           => __('Nothing matches. Try another word.', 'medical-before-after-gallery'),
        /* translators: %d: number of matching options */
        'matchesOne'        => __('%d match', 'medical-before-after-gallery'),
        /* translators: %d: number of matching options */
        'matchesMany'       => __('%d matches', 'medical-before-after-gallery'),
        'imageChoose'       => __('Choose image', 'medical-before-after-gallery'),
        'imageChange'       => __('Change image', 'medical-before-after-gallery'),
        'imageNone'         => __('No image chosen yet.', 'medical-before-after-gallery'),
        'imageMediaTitle'   => __('Choose an image', 'medical-before-after-gallery'),
        'allImageChoose'    => __('Choose an image for the “All cases” tile', 'medical-before-after-gallery'),
        'allImageChange'    => __('Change the image for the “All cases” tile', 'medical-before-after-gallery'),
        'allImageRemove'    => __('Remove the image of the “All cases” tile', 'medical-before-after-gallery'),
        'emptyTitle'        => __('No categories yet', 'medical-before-after-gallery'),
        'emptyText'         => __('Add your first category above, or start with one of these:', 'medical-before-after-gallery'),
        'suggestTitle'      => __('Quick start: add a common category', 'medical-before-after-gallery'),
        /* translators: %s: suggested category name */
        'suggestAdd'        => __('Add “%s”', 'medical-before-after-gallery'),
        'suggestDismiss'    => __('Hide suggestions', 'medical-before-after-gallery'),
        'nameRequired'      => __('Please type a name.', 'medical-before-after-gallery'),
        'working'           => __('Saving…', 'medical-before-after-gallery'),
        'networkError'      => __('The change could not be saved. Check your connection and try again.', 'medical-before-after-gallery'),
        'limitReached'      => __('All categories in the free plan are in use. Upgrade to the Pro add-on for unlimited categories.', 'medical-before-after-gallery'),
        'upgrade'           => __('Upgrade to Pro', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'added'             => __('“%s” added.', 'medical-before-after-gallery'),
        /* translators: 1: subcategory name, 2: parent category name */
        'addedInside'       => __('“%1$s” added inside “%2$s”.', 'medical-before-after-gallery'),
        /* translators: 1: old name, 2: new name */
        'renamed'           => __('“%1$s” renamed to “%2$s”.', 'medical-before-after-gallery'),
        /* translators: 1: category name, 2: destination ("Top level" or a category name) */
        'moved'             => __('“%1$s” moved to %2$s.', 'medical-before-after-gallery'),
        /* translators: 1: category name, 2: position, 3: number of positions */
        'reordered'         => __('“%1$s” is now %2$d of %3$d.', 'medical-before-after-gallery'),
        'orderSaved'        => __('Order saved.', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'imageSet'          => __('Image saved for “%s”.', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'imageRemoved'      => __('Image removed from “%s”.', 'medical-before-after-gallery'),
        /* translators: %s: category name */
        'deleted'           => __('“%s” deleted.', 'medical-before-after-gallery'),
        'nothingToMove'     => __('There is nowhere else to move this category.', 'medical-before-after-gallery'),
        'hasChildrenNote'   => __('It has subcategories of its own, so it can only stay at the top level.', 'medical-before-after-gallery'),
    );
}

/**
 * AJAX: every write the screen makes.
 *
 * POST: nonce, op = add|rename|edit|move|reorder|image|delete|all_edit, plus per op:
 *   add (name, parent) · rename (id, name) · move (id, parent)
 *   edit (id, name, description, extra) · all_edit (extra: add-on fields only)
 *   reorder (parent, ids[]) · image (id, attachment_id; 0 removes) · delete (id)
 *
 * Success: { row?, id?, rows, all, usage, usageText }
 * Error:   { message, rows?, ... } — 'rows' is included so the screen can
 *          resynchronise when the list changed elsewhere.
 */
function medbeafgallery_cm_ajax() {
    if (!check_ajax_referer('medbeafgallery_categories_manager', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Your session has expired. Please reload the page and try again.', 'medical-before-after-gallery')), 403);
    }

    $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';

    $caps = array(
        'add'      => 'manage_terms',
        'rename'   => 'edit_terms',
        'edit'     => 'edit_terms',
        'move'     => 'edit_terms',
        'reorder'  => 'edit_terms',
        'image'    => 'edit_terms',
        'delete'   => 'delete_terms',
        'all_edit' => 'edit_terms',
    );
    if (!isset($caps[$op])) {
        wp_send_json_error(array('message' => __('Unknown action.', 'medical-before-after-gallery')), 400);
    }
    if (!current_user_can(medbeafgallery_cm_cap($caps[$op]))) {
        wp_send_json_error(array('message' => __('You are not allowed to change categories.', 'medical-before-after-gallery')), 403);
    }

    $handler = 'medbeafgallery_cm_op_' . $op;
    $result  = call_user_func($handler);

    if (is_wp_error($result)) {
        wp_send_json_error(array_merge(
            array('message' => $result->get_error_message()),
            medbeafgallery_cm_state()
        ), 400);
    }

    medbeafgallery_cm_clear_caches();
    wp_send_json_success(array_merge($result, medbeafgallery_cm_state()));
}
add_action('wp_ajax_medbeafgallery_categories_manager', 'medbeafgallery_cm_ajax');

/**
 * Current list + usage, appended to every response.
 *
 * @return array
 */
function medbeafgallery_cm_state() {
    $usage = medbeafgallery_cm_usage();
    return array(
        'rows'      => medbeafgallery_cm_rows(),
        'all'       => medbeafgallery_cm_all_data(),
        'usage'     => $usage,
        'usageText' => medbeafgallery_cm_usage_text($usage),
    );
}

/**
 * Integer from POST.
 *
 * @param string $key
 * @return int
 */
function medbeafgallery_cm_post_int($key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_cm_ajax()
    return isset($_POST[$key]) ? absint(wp_unslash($_POST[$key])) : 0;
}

/**
 * Category name from POST: plain text, trimmed, non-empty.
 *
 * @return string|WP_Error
 */
function medbeafgallery_cm_post_name() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_cm_ajax()
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $name = trim($name);
    if ('' === $name) {
        return new WP_Error('medbeafgallery_cm_name', __('Please type a name.', 'medical-before-after-gallery'));
    }
    if (function_exists('mb_strlen') ? mb_strlen($name) > 200 : strlen($name) > 200) {
        return new WP_Error('medbeafgallery_cm_name', __('That name is too long. Please keep it under 200 characters.', 'medical-before-after-gallery'));
    }
    $reserved = array('all', strtolower(__('All', 'medical-before-after-gallery')));
    if (in_array(function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name), $reserved, true)) {
        return new WP_Error('medbeafgallery_cm_name', __('“All” is reserved for the tile that shows every case. Please choose another name.', 'medical-before-after-gallery'));
    }
    return $name;
}

/**
 * Find a category on this screen's tree (never "All").
 *
 * @param int $id
 * @return array|WP_Error Tree node.
 */
function medbeafgallery_cm_find($id) {
    if ($id && $id === medbeafgallery_cm_all_id()) {
        return new WP_Error('medbeafgallery_cm_all', __('The “All cases” tile cannot be changed this way.', 'medical-before-after-gallery'));
    }
    foreach (medbeafgallery_cm_get_tree() as $node) {
        if ((int) $node['term']->term_id === $id) {
            return $node;
        }
    }
    return new WP_Error('medbeafgallery_cm_missing', __('That category no longer exists. The list has been refreshed.', 'medical-before-after-gallery'));
}

/**
 * Validate a destination parent: 0, or a top-level category other than $self.
 *
 * One level of nesting on this screen: a subcategory cannot hold
 * subcategories, and a category with subcategories cannot become one.
 *
 * @param int $parent  Requested parent term ID.
 * @param int $self    Term being moved (0 when adding).
 * @return int|WP_Error The parent ID.
 */
function medbeafgallery_cm_check_parent($parent, $self = 0) {
    if (0 === $parent) {
        return 0;
    }
    if ($parent === medbeafgallery_cm_all_id()) {
        return new WP_Error('medbeafgallery_cm_parent', __('Categories cannot be placed inside “All cases”.', 'medical-before-after-gallery'));
    }
    if ($self && $parent === $self) {
        return new WP_Error('medbeafgallery_cm_parent', __('A category cannot be placed inside itself.', 'medical-before-after-gallery'));
    }

    $tree      = medbeafgallery_cm_get_tree();
    $parent_of = array();
    $target    = null;
    foreach ($tree as $node) {
        $parent_of[(int) $node['term']->term_id] = (int) $node['parent'];
        if ((int) $node['term']->term_id === $parent) {
            $target = $node;
        }
    }
    if (!$target) {
        return new WP_Error('medbeafgallery_cm_parent', __('The chosen parent category no longer exists. The list has been refreshed.', 'medical-before-after-gallery'));
    }

    // Not under its own descendant (walk up from the target).
    if ($self) {
        $guard = 0;
        $walk  = $parent;
        while ($walk && $guard++ < 100) {
            if ($walk === $self) {
                return new WP_Error('medbeafgallery_cm_parent', __('A category cannot be placed inside one of its own subcategories.', 'medical-before-after-gallery'));
            }
            $walk = isset($parent_of[$walk]) ? $parent_of[$walk] : 0;
        }
        if (in_array($self, $parent_of, true)) {
            return new WP_Error('medbeafgallery_cm_parent', __('This category has subcategories of its own, so it can only stay at the top level.', 'medical-before-after-gallery'));
        }
    }

    if (0 !== (int) $target['parent']) {
        return new WP_Error('medbeafgallery_cm_parent', __('Subcategories cannot hold subcategories of their own here. Choose a top-level category.', 'medical-before-after-gallery'));
    }

    return $parent;
}

/**
 * Is $name already used by a sibling under $parent (other than $except)?
 *
 * @param string $name
 * @param int    $parent
 * @param int    $except
 * @return bool
 */
function medbeafgallery_cm_name_taken($name, $parent, $except = 0) {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
    foreach (medbeafgallery_cm_get_tree() as $node) {
        if ((int) $node['parent'] !== $parent || (int) $node['term']->term_id === $except) {
            continue;
        }
        $have = medbeafgallery_cm_plain_name($node['term']);
        $have = function_exists('mb_strtolower') ? mb_strtolower($have) : strtolower($have);
        if ($have === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * Error for a duplicate sibling name.
 *
 * @param string $name
 * @return WP_Error
 */
function medbeafgallery_cm_duplicate_error($name) {
    return new WP_Error('medbeafgallery_cm_duplicate', sprintf(
        /* translators: %s: category name */
        __('There is already a category called “%s” here.', 'medical-before-after-gallery'),
        $name
    ));
}

/**
 * Order value for a term joining a sibling group: after the last one when every
 * sibling has an order, otherwise none (it then sorts by name after the
 * ordered ones, exactly like a category added on the standard screen).
 *
 * @param int $parent
 * @param int $except Term to leave out (the one being moved).
 * @return int|null
 */
function medbeafgallery_cm_next_order($parent, $except = 0) {
    $max = -1;
    foreach (medbeafgallery_cm_get_tree() as $node) {
        if ((int) $node['parent'] !== $parent || (int) $node['term']->term_id === $except) {
            continue;
        }
        if (null === $node['order']) {
            return null;
        }
        $max = max($max, (int) $node['order']);
    }
    return $max + 1;
}

/**
 * Row data for a term ID after a write.
 *
 * @param int $id
 * @return array|null
 */
function medbeafgallery_cm_row_by_id($id) {
    foreach (medbeafgallery_cm_get_tree() as $node) {
        if ((int) $node['term']->term_id === $id) {
            return medbeafgallery_cm_row_data($node);
        }
    }
    return null;
}

/**
 * op=add
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_add() {
    $name = medbeafgallery_cm_post_name();
    if (is_wp_error($name)) {
        return $name;
    }
    $parent = medbeafgallery_cm_check_parent(medbeafgallery_cm_post_int('parent'));
    if (is_wp_error($parent)) {
        return $parent;
    }
    if (medbeafgallery_cm_name_taken($name, $parent)) {
        return medbeafgallery_cm_duplicate_error($name);
    }

    $order = medbeafgallery_cm_next_order($parent);

    // The free plan's limit is enforced by a pre_insert_term filter that
    // returns a WP_Error; its message is passed straight back.
    $result = wp_insert_term($name, 'medbeafgallery_category', array('parent' => $parent));
    if (is_wp_error($result)) {
        if ('term_exists' === $result->get_error_code()) {
            return medbeafgallery_cm_duplicate_error($name);
        }
        return $result;
    }

    $id = (int) $result['term_id'];
    if (null !== $order) {
        update_term_meta($id, MEDBEAFGALLERY_CATEGORY_ORDER_META, $order);
    }

    return array('row' => medbeafgallery_cm_row_by_id($id));
}

/**
 * op=rename
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_rename() {
    $id   = medbeafgallery_cm_post_int('id');
    $node = medbeafgallery_cm_find($id);
    if (is_wp_error($node)) {
        return $node;
    }
    $name = medbeafgallery_cm_post_name();
    if (is_wp_error($name)) {
        return $name;
    }
    if (medbeafgallery_cm_name_taken($name, (int) $node['parent'], $id)) {
        return medbeafgallery_cm_duplicate_error($name);
    }

    // Only the name changes; the slug (used in links and filters) stays.
    $result = wp_update_term($id, 'medbeafgallery_category', array('name' => $name));
    if (is_wp_error($result)) {
        return $result;
    }

    return array('row' => medbeafgallery_cm_row_by_id($id));
}

/**
 * op=edit — name, description and any add-on fields, from the Edit panel.
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_edit() {
    $id   = medbeafgallery_cm_post_int('id');
    $node = medbeafgallery_cm_find($id);
    if (is_wp_error($node)) {
        return $node;
    }
    $name = medbeafgallery_cm_post_name();
    if (is_wp_error($name)) {
        return $name;
    }
    if (medbeafgallery_cm_name_taken($name, (int) $node['parent'], $id)) {
        return medbeafgallery_cm_duplicate_error($name);
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_cm_ajax()
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : null;
    if (null !== $description && (function_exists('mb_strlen') ? mb_strlen($description) : strlen($description)) > 1000) {
        return new WP_Error('medbeafgallery_cm_description', __('Please keep the description under 1,000 characters.', 'medical-before-after-gallery'));
    }
    // Checked before anything is saved, so a refused value changes nothing.
    $extra = medbeafgallery_cm_post_extra($id);
    if (is_wp_error($extra)) {
        return $extra;
    }

    // Only the name and description change; the slug (used in links and filters) stays.
    $args = array('name' => $name);
    if (null !== $description) {
        $args['description'] = $description;
    }
    $result = wp_update_term($id, 'medbeafgallery_category', $args);
    if (is_wp_error($result)) {
        return $result;
    }

    if ($extra) {
        /**
         * Save an add-on's fields from the Edit panel. Values are untrusted:
         * sanitize each one. Capability and nonce are already checked.
         *
         * @since 1.5.0
         * @param int   $term_id The category.
         * @param array $extra   Field key => submitted value.
         */
        do_action('medbeafgallery_categories_manager_save_extra', $id, $extra);
    }

    return array('row' => medbeafgallery_cm_row_by_id($id));
}

/**
 * op=all_edit — the add-on fields offered for the "All cases" tile, nothing
 * else: the term's name, slug, parent and description are never touched.
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_all_edit() {
    $all_id = medbeafgallery_cm_all_id();
    $term   = $all_id ? get_term($all_id, 'medbeafgallery_category') : null;
    if (!$term || is_wp_error($term)) {
        return new WP_Error('medbeafgallery_cm_missing', __('That category no longer exists. The list has been refreshed.', 'medical-before-after-gallery'));
    }
    if (medbeafgallery_cm_post_int('id') !== $all_id) {
        return new WP_Error('medbeafgallery_cm_all', __('The “All cases” tile cannot be changed this way.', 'medical-before-after-gallery'));
    }
    $extra = medbeafgallery_cm_post_extra($all_id, true);
    if (is_wp_error($extra)) {
        return $extra;
    }
    if ($extra) {
        /** This action is documented in medbeafgallery_cm_op_edit(). */
        do_action('medbeafgallery_categories_manager_save_extra', $all_id, $extra);
    }
    return array('id' => $all_id);
}

/**
 * op=move
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_move() {
    $id   = medbeafgallery_cm_post_int('id');
    $node = medbeafgallery_cm_find($id);
    if (is_wp_error($node)) {
        return $node;
    }
    $parent = medbeafgallery_cm_check_parent(medbeafgallery_cm_post_int('parent'), $id);
    if (is_wp_error($parent)) {
        return $parent;
    }

    $name = medbeafgallery_cm_plain_name($node['term']);
    if ($parent === (int) $node['parent'] && $parent === (int) $node['term']->parent) {
        return array('row' => medbeafgallery_cm_row_data($node));
    }
    if (medbeafgallery_cm_name_taken($name, $parent, $id)) {
        return medbeafgallery_cm_duplicate_error($name);
    }

    $order  = medbeafgallery_cm_next_order($parent, $id);
    $result = wp_update_term($id, 'medbeafgallery_category', array('parent' => $parent));
    if (is_wp_error($result)) {
        return $result;
    }

    // Its old position meant nothing among the new siblings.
    if (null === $order) {
        delete_term_meta($id, MEDBEAFGALLERY_CATEGORY_ORDER_META);
    } else {
        update_term_meta($id, MEDBEAFGALLERY_CATEGORY_ORDER_META, $order);
    }

    return array('row' => medbeafgallery_cm_row_by_id($id));
}

/**
 * op=reorder — the full sibling order in one request.
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_reorder() {
    $parent = medbeafgallery_cm_post_int('parent');
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_cm_ajax()
    $raw = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : array();
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $raw))));

    $siblings = array();
    foreach (medbeafgallery_cm_get_tree() as $node) {
        if ((int) $node['parent'] === $parent) {
            $siblings[] = (int) $node['term']->term_id;
        }
    }

    $a = $ids;
    $b = $siblings;
    sort($a);
    sort($b);
    if (empty($ids) || $a !== $b) {
        return new WP_Error('medbeafgallery_cm_stale', __('The list changed in the meantime. It has been refreshed — please try again.', 'medical-before-after-gallery'));
    }

    foreach ($ids as $position => $term_id) {
        update_term_meta($term_id, MEDBEAFGALLERY_CATEGORY_ORDER_META, (int) $position);
    }

    return array('ids' => $ids);
}

/**
 * op=image — set (attachment ID) or remove (0) a category's tile image.
 * Also allowed on the "All" term, which is otherwise never touched.
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_image() {
    $id     = medbeafgallery_cm_post_int('id');
    $all_id = medbeafgallery_cm_all_id();

    if (!$id || $id !== $all_id) {
        $node = medbeafgallery_cm_find($id);
        if (is_wp_error($node)) {
            return $node;
        }
    } else {
        $term = get_term($id, 'medbeafgallery_category');
        if (!$term || is_wp_error($term)) {
            return new WP_Error('medbeafgallery_cm_missing', __('That category no longer exists. The list has been refreshed.', 'medical-before-after-gallery'));
        }
    }

    $attachment_id = medbeafgallery_cm_post_int('attachment_id');
    if ($attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment || 'attachment' !== $attachment->post_type || !wp_attachment_is_image($attachment_id)) {
            return new WP_Error('medbeafgallery_cm_image', __('Please choose an image file.', 'medical-before-after-gallery'));
        }
        update_term_meta($id, 'medbeafgallery_category_image', $attachment_id);
    } else {
        delete_term_meta($id, 'medbeafgallery_category_image');
    }

    // Fires created_/edited_ term actions so the Pro add-on crops the image to
    // its 150×150 tile. No field named medbeafgallery_category_image is posted,
    // so the standard screen's own save handler stays out of it.
    wp_update_term($id, 'medbeafgallery_category');

    if ($id === $all_id) {
        return array('id' => $id);
    }
    return array('row' => medbeafgallery_cm_row_by_id($id));
}

/**
 * op=delete
 *
 * @return array|WP_Error
 */
function medbeafgallery_cm_op_delete() {
    $id   = medbeafgallery_cm_post_int('id');
    $node = medbeafgallery_cm_find($id);
    if (is_wp_error($node)) {
        return $node;
    }

    // Subcategories move up to this term's parent; cases keep their other categories.
    $result = wp_delete_term($id, 'medbeafgallery_category');
    if (is_wp_error($result)) {
        return $result;
    }
    if (true !== $result) {
        return new WP_Error('medbeafgallery_cm_delete', __('This category could not be deleted.', 'medical-before-after-gallery'));
    }

    return array('id' => $id);
}

/**
 * Admin page callback.
 */
function medbeafgallery_categories_manager_page() {
    if (!current_user_can(medbeafgallery_cm_cap('manage_terms'))) {
        wp_die(esc_html__('Sorry, you are not allowed to manage categories.', 'medical-before-after-gallery'), 403);
    }

    $rows     = medbeafgallery_cm_rows();
    $all      = medbeafgallery_cm_all_data();
    $usage    = medbeafgallery_cm_usage();
    $is_pro   = (bool) apply_filters('medbeafgallery_has_valid_pro_license', false);
    $advanced = add_query_arg(
        array('taxonomy' => 'medbeafgallery_category', 'post_type' => 'medbeafgallery_case'),
        admin_url('edit-tags.php')
    );
    $upgrade  = 'https://medicalbeforeaftergallery.com/';
    ?>
    <div class="wrap medbeafgallery-cm" id="medbeafgallery-cm">
        <h1 class="medbeafgallery-cm-title"><?php esc_html_e('Categories', 'medical-before-after-gallery'); ?></h1>
        <hr class="wp-header-end">

        <div class="medbeafgallery-cm-intro">
            <p class="medbeafgallery-cm-lead">
                <?php esc_html_e('Categories are the treatment groups visitors choose in the gallery — for example Face, Body or Skin. Subcategories appear under their parent.', 'medical-before-after-gallery'); ?>
            </p>
            <?php if (!$is_pro) : ?>
                <p class="medbeafgallery-cm-hint">
                    <?php esc_html_e('In the free plugin a subcategory’s cases are shown under its parent; the Pro add-on also gives each subcategory its own filter button.', 'medical-before-after-gallery'); ?>
                </p>
            <?php endif; ?>
            <p class="medbeafgallery-cm-usage" id="medbeafgallery-cm-usage" <?php echo $usage['limited'] ? '' : 'hidden'; ?>>
                <span class="medbeafgallery-cm-usage-text"><?php echo esc_html(medbeafgallery_cm_usage_text($usage)); ?></span>
                <span class="medbeafgallery-cm-usage-meter" aria-hidden="true"><span style="width: <?php echo esc_attr($usage['max'] > 0 ? min(100, round($usage['used'] / $usage['max'] * 100)) : 0); ?>%"></span></span>
                <a href="<?php echo esc_url($upgrade); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade to Pro for unlimited categories', 'medical-before-after-gallery'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'medical-before-after-gallery'); ?></span></a>
            </p>
        </div>

        <div class="medbeafgallery-cm-status screen-reader-text" id="medbeafgallery-cm-live" role="status" aria-live="polite" aria-atomic="true"></div>
        <div class="medbeafgallery-cm-notice" id="medbeafgallery-cm-notice" role="alert" hidden></div>

        <noscript>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Turn on JavaScript to add, edit, reorder or delete categories on this screen. You can also use the standard WordPress categories screen (link below).', 'medical-before-after-gallery'); ?></p>
            </div>
        </noscript>

        <form class="medbeafgallery-cm-card medbeafgallery-cm-add" id="medbeafgallery-cm-add" hidden novalidate>
            <h2 class="medbeafgallery-cm-card-title"><?php esc_html_e('Add a category', 'medical-before-after-gallery'); ?></h2>
            <div class="medbeafgallery-cm-add-fields">
                <div class="medbeafgallery-cm-field medbeafgallery-cm-field-name">
                    <label for="medbeafgallery-cm-new-name"><?php esc_html_e('Name', 'medical-before-after-gallery'); ?></label>
                    <input type="text" id="medbeafgallery-cm-new-name" class="regular-text" autocomplete="off" maxlength="200" placeholder="<?php esc_attr_e('e.g. Face', 'medical-before-after-gallery'); ?>">
                </div>
                <div class="medbeafgallery-cm-field medbeafgallery-cm-field-parent">
                    <label for="medbeafgallery-cm-new-parent"><?php esc_html_e('Inside (optional)', 'medical-before-after-gallery'); ?></label>
                    <select id="medbeafgallery-cm-new-parent"></select>
                </div>
                <div class="medbeafgallery-cm-field medbeafgallery-cm-field-submit">
                    <button type="submit" class="button button-primary" id="medbeafgallery-cm-add-button"><?php esc_html_e('Add category', 'medical-before-after-gallery'); ?></button>
                </div>
            </div>
            <p class="medbeafgallery-cm-limit" id="medbeafgallery-cm-limit" hidden></p>
        </form>

        <div class="medbeafgallery-cm-card medbeafgallery-cm-list-card">
            <h2 class="medbeafgallery-cm-card-title" id="medbeafgallery-cm-list-title"><?php esc_html_e('Your categories', 'medical-before-after-gallery'); ?></h2>

            <?php if ($all) : ?>
                <div class="medbeafgallery-cm-all" id="medbeafgallery-cm-all">
                    <span class="medbeafgallery-cm-thumb medbeafgallery-cm-thumb-static">
                        <?php if ($all['image_url']) : ?>
                            <img src="<?php echo esc_url($all['image_url']); ?>" alt="">
                        <?php else : ?>
                            <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
                        <?php endif; ?>
                    </span>
                    <div class="medbeafgallery-cm-main">
                        <div class="medbeafgallery-cm-line">
                            <span class="medbeafgallery-cm-name-static"><?php esc_html_e('All cases', 'medical-before-after-gallery'); ?></span>
                        </div>
                        <p class="medbeafgallery-cm-note"><?php esc_html_e('Always shown first in the gallery.', 'medical-before-after-gallery'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <ul class="medbeafgallery-cm-list" id="medbeafgallery-cm-list" aria-labelledby="medbeafgallery-cm-list-title">
                <?php
                // Read-only list for browsers without JavaScript; the script
                // replaces it with the editable one.
                foreach ($rows as $row) :
                    ?>
                    <li class="medbeafgallery-cm-item" style="<?php echo esc_attr('--medbeafgallery-cm-depth:' . (int) $row['depth']); ?>">
                        <div class="medbeafgallery-cm-row medbeafgallery-cm-row-static">
                            <span class="medbeafgallery-cm-thumb medbeafgallery-cm-thumb-static">
                                <?php if ($row['image_url']) : ?>
                                    <img src="<?php echo esc_url($row['image_url']); ?>" alt="">
                                <?php else : ?>
                                    <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <div class="medbeafgallery-cm-main">
                                <div class="medbeafgallery-cm-line">
                                    <span class="medbeafgallery-cm-name-static"><?php echo esc_html($row['name']); ?></span>
                                    <?php if ($row['count'] > 0) : ?>
                                        <a class="medbeafgallery-cm-count" href="<?php echo esc_url($row['cases_url']); ?>"><?php
                                            /* translators: %d: number of cases */
                                            echo esc_html(sprintf(_n('%d case', '%d cases', $row['count'], 'medical-before-after-gallery'), $row['count']));
                                        ?></a>
                                    <?php else : ?>
                                        <span class="medbeafgallery-cm-count"><?php esc_html_e('No cases yet', 'medical-before-after-gallery'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="medbeafgallery-cm-actions">
                                    <a href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('More settings', 'medical-before-after-gallery'); ?><span class="screen-reader-text"> <?php echo esc_html(sprintf(
                                        /* translators: %s: category name */
                                        __('for “%s”', 'medical-before-after-gallery'),
                                        $row['name']
                                    )); ?></span></a>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (empty($rows)) : ?>
                <p class="medbeafgallery-cm-empty-static"><?php esc_html_e('No categories yet.', 'medical-before-after-gallery'); ?></p>
            <?php endif; ?>

            <div class="medbeafgallery-cm-empty" id="medbeafgallery-cm-empty" hidden></div>
            <span class="screen-reader-text" id="medbeafgallery-cm-reorder-hint"><?php esc_html_e('Drag, or press the Up and Down arrow keys, to change the order.', 'medical-before-after-gallery'); ?></span>
        </div>

        <?php /* Everything is edited on this screen; WordPress's own term screen is only offered without JavaScript. */ ?>
        <noscript>
            <p class="medbeafgallery-cm-footer">
                <a href="<?php echo esc_url($advanced); ?>"><?php esc_html_e('Open the standard WordPress categories screen', 'medical-before-after-gallery'); ?></a>
            </p>
        </noscript>
    </div>
    <?php
}
