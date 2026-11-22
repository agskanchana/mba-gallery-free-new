<?php
/**
 * Doctors manager — a people-directory screen for the gallery's doctors.
 *
 * Replaces WordPress's generic term screen for everyday work (that screen stays
 * reachable through an "Advanced" link). The taxonomy underneath is unchanged:
 * `medbeafgallery_doctor`, non-hierarchical, default capabilities, term meta
 * from medbeafgallery_doctor_meta_keys() (photo, credentials, location, url).
 * This screen adds one term meta key, `medbeafgallery_order` (int, 0..n), the
 * position chosen here. Nothing in the free plugin sorts by it yet; the Pro
 * add-on's doctor filter may.
 *
 * includes/doctors.php registers the page:
 *   add_submenu_page('medbeafgallery-gallery', …, MEDBEAFGALLERY_DOCTORS_PAGE,
 *       'medbeafgallery_doctors_manager_page');
 *
 * @package Medical_Before_After_Gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MEDBEAFGALLERY_DOCTORS_PAGE')) {
    define('MEDBEAFGALLERY_DOCTORS_PAGE', 'medbeafgallery-doctors');
}

/**
 * Term meta key holding a doctor's position on this screen.
 */
if (!defined('MEDBEAFGALLERY_DOCTOR_ORDER_META')) {
    define('MEDBEAFGALLERY_DOCTOR_ORDER_META', 'medbeafgallery_order');
}

/**
 * Taxonomy capability for an action ('manage_terms', 'edit_terms', 'delete_terms').
 *
 * @param string $which Capability key on the taxonomy's cap object.
 * @return string
 */
function medbeafgallery_dm_cap($which) {
    $taxonomy = get_taxonomy('medbeafgallery_doctor');
    if ($taxonomy && isset($taxonomy->cap->$which)) {
        return $taxonomy->cap->$which;
    }
    return 'manage_categories';
}

/**
 * A term's name as plain text (WordPress stores names HTML-encoded, e.g. "&amp;").
 *
 * @param WP_Term $term
 * @return string
 */
function medbeafgallery_dm_plain_name($term) {
    return wp_specialchars_decode((string) $term->name, ENT_QUOTES);
}

/**
 * Lower-case a string (multibyte-safe when mbstring is available).
 *
 * @param string $value
 * @return string
 */
function medbeafgallery_dm_lower($value) {
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

/**
 * Up to two initials for the avatar placeholder, e.g. "Dr. Jane Smith, MD" → "JS".
 *
 * Kept in step with initials() in admin/js/doctors-manager.js.
 *
 * @param string $name Plain-text name.
 * @return string
 */
function medbeafgallery_dm_initials($name) {
    $name = trim((string) $name);
    // Credentials after a comma are not part of the name.
    $comma = strpos($name, ',');
    if (false !== $comma && $comma > 0) {
        $name = substr($name, 0, $comma);
    }
    // Spaces only: "Lindqvist-Barros" gives L, not B.
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return '';
    }
    $titles = array('dr', 'dr.', 'dra', 'dra.', 'prof', 'prof.', 'mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'miss', 'mx', 'mx.');
    while (count($words) > 1 && in_array(medbeafgallery_dm_lower($words[0]), $titles, true)) {
        array_shift($words);
    }
    $first = function ($word) {
        // First letter or digit, skipping quotes and brackets.
        if (preg_match('/[\p{L}\p{N}]/u', $word, $m)) {
            return $m[0];
        }
        return '';
    };
    $out = $first($words[0]);
    if (count($words) > 1) {
        $out .= $first($words[count($words) - 1]);
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($out) : strtoupper($out);
}

/**
 * URL of the case list filtered to one doctor.
 *
 * The taxonomy is registered with 'query_var' => false, so the list is filtered
 * through the generic taxonomy/term query vars.
 *
 * @param WP_Term $term
 * @return string
 */
function medbeafgallery_dm_cases_url($term) {
    return add_query_arg(
        array(
            'post_type' => 'medbeafgallery_case',
            'taxonomy'  => 'medbeafgallery_doctor',
            'term'      => $term->slug,
        ),
        admin_url('edit.php')
    );
}

/**
 * URL of WordPress's own doctors screen ("Advanced").
 *
 * @return string
 */
function medbeafgallery_dm_advanced_url() {
    return add_query_arg(
        array('taxonomy' => 'medbeafgallery_doctor', 'post_type' => 'medbeafgallery_case'),
        admin_url('edit-tags.php')
    );
}

/**
 * Stored position of a doctor, or null when it has none.
 *
 * @param int $term_id
 * @return int|null
 */
function medbeafgallery_dm_get_order($term_id) {
    $value = get_term_meta($term_id, MEDBEAFGALLERY_DOCTOR_ORDER_META, true);
    if ('' === $value || null === $value || false === $value || !is_numeric($value)) {
        return null;
    }
    return (int) $value;
}

/**
 * usort() callback: ordered doctors first (by order), then the rest by name.
 *
 * @param WP_Term $a
 * @param WP_Term $b
 * @return int
 */
function medbeafgallery_dm_compare_terms($a, $b) {
    $oa = medbeafgallery_dm_get_order($a->term_id);
    $ob = medbeafgallery_dm_get_order($b->term_id);
    if (null !== $oa && null !== $ob && $oa !== $ob) {
        return $oa < $ob ? -1 : 1;
    }
    if (null !== $oa && null === $ob) {
        return -1;
    }
    if (null === $oa && null !== $ob) {
        return 1;
    }
    $by_name = strnatcasecmp(medbeafgallery_dm_plain_name($a), medbeafgallery_dm_plain_name($b));
    if (0 !== $by_name) {
        return $by_name;
    }
    return (int) $a->term_id - (int) $b->term_id;
}

/**
 * Every doctor, in screen order.
 *
 * @return WP_Term[]
 */
function medbeafgallery_dm_get_terms() {
    $terms = get_terms(array(
        'taxonomy'               => 'medbeafgallery_doctor',
        'hide_empty'             => false,
        'update_term_meta_cache' => true,
    ));
    if (is_wp_error($terms) || empty($terms)) {
        return array();
    }
    usort($terms, 'medbeafgallery_dm_compare_terms');
    return $terms;
}

/**
 * Row data sent to the browser for one doctor.
 *
 * @param WP_Term $term
 * @return array
 */
function medbeafgallery_dm_row_data($term) {
    $keys     = medbeafgallery_doctor_meta_keys();
    $id       = (int) $term->term_id;
    $name     = medbeafgallery_dm_plain_name($term);
    $photo_id = absint(get_term_meta($id, $keys['photo'], true));
    $photo    = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';

    return array(
        'id'          => $id,
        'name'        => $name,
        'slug'        => $term->slug,
        'count'       => (int) $term->count,
        'order'       => medbeafgallery_dm_get_order($id),
        'photo_id'    => $photo ? $photo_id : 0,
        'photo_url'   => $photo ? $photo : '',
        'credentials' => (string) get_term_meta($id, $keys['credentials'], true),
        'location'    => (string) get_term_meta($id, $keys['location'], true),
        'url'         => (string) get_term_meta($id, $keys['url'], true),
        'initials'    => medbeafgallery_dm_initials($name),
        // One of six calm avatar tones; tied to the ID so a rename keeps it.
        'tone'        => $id % 6,
        'cases_url'   => medbeafgallery_dm_cases_url($term),
        'edit_url'    => add_query_arg(
            array(
                'taxonomy'  => 'medbeafgallery_doctor',
                'tag_ID'    => $id,
                'post_type' => 'medbeafgallery_case',
            ),
            admin_url('term.php')
        ),
    );
}

/**
 * All rows, in screen order.
 *
 * @return array[]
 */
function medbeafgallery_dm_rows() {
    return array_map('medbeafgallery_dm_row_data', medbeafgallery_dm_get_terms());
}

/**
 * Drop the gallery's cached data after a change (cases carry their doctor).
 */
function medbeafgallery_dm_clear_caches() {
    // Same invalidation the plugin uses when cases change (medbeafgallery_clear_cache()).
    delete_transient('medbeafgallery_cases_cache');
    delete_transient('medbeafgallery_categories_cache');
}

/**
 * Does this screen's hook belong to the doctors manager?
 *
 * @param string $hook_suffix
 * @return bool
 */
function medbeafgallery_dm_is_screen($hook_suffix) {
    if ('medical-before-after-gallery_page_' . MEDBEAFGALLERY_DOCTORS_PAGE === $hook_suffix) {
        return true;
    }
    // The parent menu title is translatable, which changes the hook suffix.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return MEDBEAFGALLERY_DOCTORS_PAGE === $page && is_string($hook_suffix) && false !== strpos($hook_suffix, '_page_' . MEDBEAFGALLERY_DOCTORS_PAGE);
}

/**
 * Whether the Pro add-on is active with a licence.
 *
 * @return bool
 */
function medbeafgallery_dm_is_pro() {
    return (bool) apply_filters('medbeafgallery_has_valid_pro_license', false);
}

/**
 * Enqueue the screen's assets (this screen only).
 *
 * @param string $hook_suffix
 */
function medbeafgallery_dm_enqueue($hook_suffix) {
    if (!medbeafgallery_dm_is_screen($hook_suffix)) {
        return;
    }
    if (!current_user_can(medbeafgallery_dm_cap('manage_terms'))) {
        return;
    }

    $version = defined('MEDBEAFGALLERY_VERSION') ? MEDBEAFGALLERY_VERSION : '1.5.0';
    $base    = plugin_dir_url(__FILE__);

    wp_enqueue_media();
    wp_enqueue_style('medbeafgallery-doctors-manager', $base . 'css/doctors-manager.css', array(), $version);
    wp_enqueue_script('medbeafgallery-doctors-manager', $base . 'js/doctors-manager.js', array(), $version, true);

    wp_localize_script('medbeafgallery-doctors-manager', 'medbeafgalleryDoctorsManager', array(
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'action'      => 'medbeafgallery_doctors_manager',
        'nonce'       => wp_create_nonce('medbeafgallery_doctors_manager'),
        'rows'        => medbeafgallery_dm_rows(),
        'searchFrom'  => 7,
        'can'         => array(
            'add'    => current_user_can(medbeafgallery_dm_cap('manage_terms')),
            'edit'   => current_user_can(medbeafgallery_dm_cap('edit_terms')),
            'delete' => current_user_can(medbeafgallery_dm_cap('delete_terms')),
        ),
        'i18n'        => medbeafgallery_dm_js_strings(),
    ));
}
add_action('admin_enqueue_scripts', 'medbeafgallery_dm_enqueue');

/**
 * Strings used by doctors-manager.js. "%s" is a doctor's name, "%d" a number.
 *
 * @return array
 */
function medbeafgallery_dm_js_strings() {
    return array(
        'addTitle'        => __('Add doctor', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'editTitle'       => __('Edit %s', 'medical-before-after-gallery'),
        'editTitleEmpty'  => __('Edit doctor', 'medical-before-after-gallery'),
        'save'            => __('Save doctor', 'medical-before-after-gallery'),
        'saveChanges'     => __('Save changes', 'medical-before-after-gallery'),
        'edit'            => __('Edit', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'editLabel'       => __('Edit %s', 'medical-before-after-gallery'),
        'delete'          => __('Delete', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'deleteLabel'     => __('Delete %s', 'medical-before-after-gallery'),
        'deleteConfirm'   => __('Delete doctor', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'deleteQuestion'  => __('Delete %s?', 'medical-before-after-gallery'),
        'deleteNoCases'   => __('No cases name this doctor.', 'medical-before-after-gallery'),
        'deleteCasesOne'  => __('1 case will no longer show a doctor.', 'medical-before-after-gallery'),
        /* translators: %d: number of cases */
        'deleteCasesMany' => __('%d cases will no longer show a doctor.', 'medical-before-after-gallery'),
        'deleteKeep'      => __('The cases themselves stay published.', 'medical-before-after-gallery'),
        'cancel'          => __('Cancel', 'medical-before-after-gallery'),
        'noCases'         => __('No cases yet', 'medical-before-after-gallery'),
        'casesOne'        => __('1 case', 'medical-before-after-gallery'),
        /* translators: %d: number of cases */
        'casesMany'       => __('%d cases', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'casesLinkLabel'  => __('See the cases of %s', 'medical-before-after-gallery'),
        'profile'         => __('Profile', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'profileLabel'    => __('Profile page of %s (opens in a new tab)', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'photoAlt'        => __('Photo of %s', 'medical-before-after-gallery'),
        'choosePhoto'     => __('Choose photo', 'medical-before-after-gallery'),
        'changePhoto'     => __('Change photo', 'medical-before-after-gallery'),
        'mediaTitle'      => __('Choose a photo of the doctor', 'medical-before-after-gallery'),
        'mediaButton'     => __('Use this photo', 'medical-before-after-gallery'),
        'noMedia'         => __('The media library is not available on this page.', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'reorder'         => __('Reorder %s', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'moveUp'          => __('Move %s earlier', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'moveDown'        => __('Move %s later', 'medical-before-after-gallery'),
        /* translators: 1: doctor name, 2: position, 3: number of doctors */
        'reordered'       => __('%1$s is now %2$d of %3$d.', 'medical-before-after-gallery'),
        'nameRequired'    => __('Please type the doctor’s name.', 'medical-before-after-gallery'),
        'urlInvalid'      => __('Please enter a full web address, for example https://example.com/dr-smith', 'medical-before-after-gallery'),
        'fixErrors'       => __('Please check the highlighted field.', 'medical-before-after-gallery'),
        'working'         => __('Saving…', 'medical-before-after-gallery'),
        'networkError'    => __('The change could not be saved. Check your connection and try again.', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'added'           => __('%s added.', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'saved'           => __('%s saved.', 'medical-before-after-gallery'),
        /* translators: %s: doctor name */
        'deleted'         => __('%s deleted.', 'medical-before-after-gallery'),
        'countOne'        => __('1 doctor', 'medical-before-after-gallery'),
        /* translators: %d: number of doctors */
        'countMany'       => __('%d doctors', 'medical-before-after-gallery'),
        /* translators: 1: doctors shown, 2: all doctors */
        'countFiltered'   => __('%1$d of %2$d doctors', 'medical-before-after-gallery'),
        /* translators: %s: search text */
        'noMatch'         => __('No doctor matches “%s”.', 'medical-before-after-gallery'),
        'clearSearch'     => __('Clear search', 'medical-before-after-gallery'),
        'reorderOff'      => __('Clear the search to change the order.', 'medical-before-after-gallery'),
    );
}

/**
 * AJAX: every write the screen makes.
 *
 * POST: nonce, op = save|delete|reorder, plus per op:
 *   save (id — 0 adds —, name, credentials, location, url, photo_id)
 *   delete (id) · reorder (ids[] — every doctor, in the new order)
 *
 * Success: { row?, id?, rows }
 * Error:   { message, field?, rows } — 'rows' is included so the screen can
 *          resynchronise when the list changed elsewhere.
 */
function medbeafgallery_dm_ajax() {
    if (!check_ajax_referer('medbeafgallery_doctors_manager', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Your session has expired. Please reload the page and try again.', 'medical-before-after-gallery')), 403);
    }

    $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
    $id = medbeafgallery_dm_post_int('id');

    $caps = array(
        'save'    => $id ? 'edit_terms' : 'manage_terms',
        'delete'  => 'delete_terms',
        'reorder' => 'edit_terms',
    );
    if (!isset($caps[$op])) {
        wp_send_json_error(array('message' => __('Unknown action.', 'medical-before-after-gallery')), 400);
    }
    if (!taxonomy_exists('medbeafgallery_doctor')) {
        wp_send_json_error(array('message' => __('Doctors are not available.', 'medical-before-after-gallery')), 400);
    }
    if (!current_user_can(medbeafgallery_dm_cap($caps[$op]))) {
        wp_send_json_error(array('message' => __('You are not allowed to change doctors.', 'medical-before-after-gallery')), 403);
    }

    $handler = 'medbeafgallery_dm_op_' . $op;
    $result  = call_user_func($handler);

    if (is_wp_error($result)) {
        $data = $result->get_error_data();
        wp_send_json_error(array(
            'message' => $result->get_error_message(),
            'field'   => (is_array($data) && isset($data['field'])) ? $data['field'] : '',
            'rows'    => medbeafgallery_dm_rows(),
        ), 400);
    }

    medbeafgallery_dm_clear_caches();
    $result['rows'] = medbeafgallery_dm_rows();
    wp_send_json_success($result);
}
add_action('wp_ajax_medbeafgallery_doctors_manager', 'medbeafgallery_dm_ajax');

/**
 * Integer from POST.
 *
 * @param string $key
 * @return int
 */
function medbeafgallery_dm_post_int($key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_dm_ajax()
    return isset($_POST[$key]) ? absint(wp_unslash($_POST[$key])) : 0;
}

/**
 * Plain-text field from POST, trimmed and cut to $max characters.
 *
 * @param string $key
 * @param int    $max
 * @return string
 */
function medbeafgallery_dm_post_text($key, $max) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_dm_ajax()
    $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

/**
 * Normalise a profile link: '' stays '', "example.com/x" gains https://,
 * anything that is not an http(s) address with a host is an error.
 *
 * Kept in step with normaliseUrl() in admin/js/doctors-manager.js.
 *
 * @param string $raw
 * @return string|WP_Error
 */
function medbeafgallery_dm_normalise_url($raw) {
    $raw = trim(preg_replace('/[\r\n\t ]+/', '', (string) $raw));
    if ('' === $raw) {
        return '';
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $raw)) {
        $raw = 'https://' . ltrim($raw, '/');
    }
    $url   = esc_url_raw($raw, array('http', 'https'));
    $parts = $url ? wp_parse_url($url) : false;
    if (!$url || !is_array($parts) || empty($parts['host']) || false === strpos($parts['host'], '.') && 'localhost' !== $parts['host']) {
        return new WP_Error('medbeafgallery_dm_url', __('Please enter a full web address, for example https://example.com/dr-smith', 'medical-before-after-gallery'), array('field' => 'url'));
    }
    return $url;
}

/**
 * Find a doctor term.
 *
 * @param int $id
 * @return WP_Term|WP_Error
 */
function medbeafgallery_dm_find($id) {
    $term = $id ? get_term($id, 'medbeafgallery_doctor') : null;
    if (!$term || is_wp_error($term)) {
        return new WP_Error('medbeafgallery_dm_missing', __('That doctor no longer exists. The list has been refreshed.', 'medical-before-after-gallery'));
    }
    return $term;
}

/**
 * Is $name already used by another doctor (other than $except)?
 *
 * @param string $name
 * @param int    $except
 * @return bool
 */
function medbeafgallery_dm_name_taken($name, $except = 0) {
    $needle = medbeafgallery_dm_lower($name);
    foreach (medbeafgallery_dm_get_terms() as $term) {
        if ((int) $term->term_id === $except) {
            continue;
        }
        if (medbeafgallery_dm_lower(medbeafgallery_dm_plain_name($term)) === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * Error for a duplicate name.
 *
 * @param string $name
 * @return WP_Error
 */
function medbeafgallery_dm_duplicate_error($name) {
    return new WP_Error('medbeafgallery_dm_duplicate', sprintf(
        /* translators: %s: doctor name */
        __('There is already a doctor called “%s”.', 'medical-before-after-gallery'),
        $name
    ), array('field' => 'name'));
}

/**
 * Order value for a new doctor: after the last one when every doctor has an
 * order, otherwise none (it then sorts by name after the ordered ones, exactly
 * like a doctor added on the standard screen).
 *
 * @return int|null
 */
function medbeafgallery_dm_next_order() {
    $max = -1;
    foreach (medbeafgallery_dm_get_terms() as $term) {
        $order = medbeafgallery_dm_get_order($term->term_id);
        if (null === $order) {
            return null;
        }
        $max = max($max, $order);
    }
    return $max + 1;
}

/**
 * op=save — add (id 0) or update a doctor and its details.
 *
 * @return array|WP_Error
 */
function medbeafgallery_dm_op_save() {
    $id = medbeafgallery_dm_post_int('id');

    $term = null;
    if ($id) {
        $term = medbeafgallery_dm_find($id);
        if (is_wp_error($term)) {
            return $term;
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_dm_ajax()
    $name = isset($_POST['name']) ? trim(sanitize_text_field(wp_unslash($_POST['name']))) : '';
    if ('' === $name) {
        return new WP_Error('medbeafgallery_dm_name', __('Please type the doctor’s name.', 'medical-before-after-gallery'), array('field' => 'name'));
    }
    if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 200) {
        return new WP_Error('medbeafgallery_dm_name', __('That name is too long. Please keep it under 200 characters.', 'medical-before-after-gallery'), array('field' => 'name'));
    }
    if (medbeafgallery_dm_name_taken($name, $id)) {
        return medbeafgallery_dm_duplicate_error($name);
    }

    // Not sanitize_text_field(): it strips %xx sequences that URLs need.
    // esc_url_raw() in medbeafgallery_dm_normalise_url() does the cleaning.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in medbeafgallery_dm_ajax(); sanitized by esc_url_raw()
    $url = medbeafgallery_dm_normalise_url(isset($_POST['url']) && is_string($_POST['url']) ? wp_unslash($_POST['url']) : '');
    if (is_wp_error($url)) {
        return $url;
    }

    $photo = medbeafgallery_dm_post_int('photo_id');
    if ($photo) {
        $attachment = get_post($photo);
        if (!$attachment || 'attachment' !== $attachment->post_type || !wp_attachment_is_image($photo)) {
            return new WP_Error('medbeafgallery_dm_photo', __('Please choose an image file for the photo.', 'medical-before-after-gallery'), array('field' => 'photo'));
        }
    }

    $fields = array(
        'credentials' => medbeafgallery_dm_post_text('credentials', 160),
        'location'    => medbeafgallery_dm_post_text('location', 120),
        'url'         => $url,
    );

    if (!$id) {
        $order  = medbeafgallery_dm_next_order();
        $result = wp_insert_term($name, 'medbeafgallery_doctor');
        if (is_wp_error($result)) {
            if ('term_exists' === $result->get_error_code()) {
                return medbeafgallery_dm_duplicate_error($name);
            }
            return $result;
        }
        $id = (int) $result['term_id'];
        if (null !== $order) {
            update_term_meta($id, MEDBEAFGALLERY_DOCTOR_ORDER_META, $order);
        }
        $added = true;
    } else {
        // Only the name changes; the slug (used in links and filters) stays.
        if (medbeafgallery_dm_plain_name($term) !== $name) {
            $result = wp_update_term($id, 'medbeafgallery_doctor', array('name' => $name));
            if (is_wp_error($result)) {
                if ('duplicate_term_slug' === $result->get_error_code() || 'term_exists' === $result->get_error_code()) {
                    return medbeafgallery_dm_duplicate_error($name);
                }
                return $result;
            }
        }
        $added = false;
    }

    $keys = medbeafgallery_doctor_meta_keys();
    if ($photo) {
        update_term_meta($id, $keys['photo'], $photo);
    } else {
        delete_term_meta($id, $keys['photo']);
    }
    foreach ($fields as $field => $value) {
        if ('' !== $value) {
            update_term_meta($id, $keys[$field], $value);
        } else {
            delete_term_meta($id, $keys[$field]);
        }
    }

    $fresh = get_term($id, 'medbeafgallery_doctor');
    return array(
        'row'   => ($fresh && !is_wp_error($fresh)) ? medbeafgallery_dm_row_data($fresh) : null,
        'added' => $added,
    );
}

/**
 * op=delete
 *
 * @return array|WP_Error
 */
function medbeafgallery_dm_op_delete() {
    $id   = medbeafgallery_dm_post_int('id');
    $term = medbeafgallery_dm_find($id);
    if (is_wp_error($term)) {
        return $term;
    }

    // Cases keep everything else; they simply no longer name a doctor.
    $result = wp_delete_term($id, 'medbeafgallery_doctor');
    if (is_wp_error($result)) {
        return $result;
    }
    if (true !== $result) {
        return new WP_Error('medbeafgallery_dm_delete', __('This doctor could not be deleted.', 'medical-before-after-gallery'));
    }

    return array('id' => $id);
}

/**
 * op=reorder — the full order in one request.
 *
 * @return array|WP_Error
 */
function medbeafgallery_dm_op_reorder() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in medbeafgallery_dm_ajax()
    $raw = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : array();
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $raw))));

    $all = array_map(function ($term) {
        return (int) $term->term_id;
    }, medbeafgallery_dm_get_terms());

    $a = $ids;
    $b = $all;
    sort($a);
    sort($b);
    if (empty($ids) || $a !== $b) {
        return new WP_Error('medbeafgallery_dm_stale', __('The list changed in the meantime. It has been refreshed — please try again.', 'medical-before-after-gallery'));
    }

    foreach ($ids as $position => $term_id) {
        update_term_meta($term_id, MEDBEAFGALLERY_DOCTOR_ORDER_META, (int) $position);
    }

    return array('ids' => $ids);
}

/**
 * Inline SVG for the empty state: three soft "people" cards.
 *
 * @return string Static markup (no user data).
 */
function medbeafgallery_dm_empty_illustration() {
    return '<svg class="medbeafgallery-dm-empty-art" width="220" height="132" viewBox="0 0 220 132" aria-hidden="true" focusable="false">'
        . '<rect x="14" y="30" width="58" height="80" rx="10" fill="#ffffff" stroke="#dcdcde"/>'
        . '<circle cx="43" cy="56" r="13" fill="#e7f4ef"/><path d="M27 88c2-10 9-15 16-15s14 5 16 15" fill="#e7f4ef"/>'
        . '<rect x="26" y="96" width="34" height="5" rx="2.5" fill="#ececee"/>'
        . '<rect x="148" y="30" width="58" height="80" rx="10" fill="#ffffff" stroke="#dcdcde"/>'
        . '<circle cx="177" cy="56" r="13" fill="#f3ecf8"/><path d="M161 88c2-10 9-15 16-15s14 5 16 15" fill="#f3ecf8"/>'
        . '<rect x="160" y="96" width="34" height="5" rx="2.5" fill="#ececee"/>'
        . '<rect x="72" y="14" width="76" height="104" rx="12" fill="#ffffff" stroke="#c3c4c7"/>'
        . '<rect x="72.5" y="14.5" width="75" height="26" rx="11.5" fill="#e6f0fa"/>'
        . '<circle cx="110" cy="46" r="17" fill="#ffffff"/><circle cx="110" cy="46" r="14" fill="#cfe2f5"/>'
        . '<path d="M90 86c2.5-12 11-18 20-18s17.5 6 20 18" fill="#cfe2f5"/>'
        . '<rect x="88" y="94" width="44" height="6" rx="3" fill="#dcdcde"/>'
        . '<rect x="95" y="105" width="30" height="5" rx="2.5" fill="#ececee"/>'
        . '<circle class="medbeafgallery-dm-art-accent" cx="146" cy="20" r="11" fill="#2271b1"/><path d="M146 15v10M141 20h10" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>'
        . '</svg>';
}

/**
 * Admin page callback.
 */
function medbeafgallery_doctors_manager_page() {
    if (!current_user_can(medbeafgallery_dm_cap('manage_terms'))) {
        wp_die(esc_html__('Sorry, you are not allowed to manage doctors.', 'medical-before-after-gallery'), 403);
    }

    $rows     = medbeafgallery_dm_rows();
    $advanced = medbeafgallery_dm_advanced_url();
    $lead     = medbeafgallery_dm_is_pro()
        ? __('Name the doctor who treated each case: their card appears in the case window, and visitors can filter the gallery by doctor.', 'medical-before-after-gallery')
        : __('Name the doctor who treated each case: their card appears in the case window, and the Pro add-on adds a doctor filter to the gallery.', 'medical-before-after-gallery');
    ?>
    <div class="wrap medbeafgallery-dm" id="medbeafgallery-dm">
        <div class="medbeafgallery-dm-header">
            <div class="medbeafgallery-dm-heading">
                <h1 class="medbeafgallery-dm-title"><?php esc_html_e('Doctors', 'medical-before-after-gallery'); ?></h1>
                <p class="medbeafgallery-dm-lead"><?php echo esc_html($lead); ?></p>
            </div>
            <a class="button button-primary medbeafgallery-dm-add" id="medbeafgallery-dm-add" href="<?php echo esc_url($advanced . '#addtag'); ?>">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e('Add doctor', 'medical-before-after-gallery'); ?>
            </a>
        </div>
        <hr class="wp-header-end">

        <div class="medbeafgallery-dm-status screen-reader-text" id="medbeafgallery-dm-live" role="status" aria-live="polite" aria-atomic="true"></div>
        <div class="medbeafgallery-dm-notice" id="medbeafgallery-dm-notice" role="alert" hidden></div>

        <noscript>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('Turn on JavaScript to add, edit, reorder or delete doctors on this screen. You can also use the standard WordPress screen (link below).', 'medical-before-after-gallery'); ?></p>
            </div>
        </noscript>

        <div class="medbeafgallery-dm-toolbar" id="medbeafgallery-dm-toolbar" hidden>
            <div class="medbeafgallery-dm-search">
                <label for="medbeafgallery-dm-search-input" class="screen-reader-text"><?php esc_html_e('Search doctors', 'medical-before-after-gallery'); ?></label>
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search" id="medbeafgallery-dm-search-input" autocomplete="off" placeholder="<?php esc_attr_e('Search by name, credentials or location', 'medical-before-after-gallery'); ?>" aria-controls="medbeafgallery-dm-grid">
            </div>
            <p class="medbeafgallery-dm-count" id="medbeafgallery-dm-count"></p>
        </div>

        <h2 class="screen-reader-text" id="medbeafgallery-dm-grid-title"><?php esc_html_e('Your doctors', 'medical-before-after-gallery'); ?></h2>
        <ul class="medbeafgallery-dm-grid" id="medbeafgallery-dm-grid" aria-labelledby="medbeafgallery-dm-grid-title">
            <?php
            // Read-only cards for browsers without JavaScript; the script
            // replaces them with editable ones.
            foreach ($rows as $row) :
                ?>
                <li class="medbeafgallery-dm-card medbeafgallery-dm-tone-<?php echo esc_attr($row['tone']); ?>">
                    <div class="medbeafgallery-dm-card-band" aria-hidden="true"></div>
                    <div class="medbeafgallery-dm-card-body">
                        <span class="medbeafgallery-dm-avatar">
                            <?php if ($row['photo_url']) : ?>
                                <img src="<?php echo esc_url($row['photo_url']); ?>" alt="">
                            <?php else : ?>
                                <span class="medbeafgallery-dm-initials" aria-hidden="true"><?php echo esc_html($row['initials']); ?></span>
                            <?php endif; ?>
                        </span>
                        <h3 class="medbeafgallery-dm-name"><?php echo esc_html($row['name']); ?></h3>
                        <?php if ('' !== $row['credentials']) : ?>
                            <p class="medbeafgallery-dm-credentials"><?php echo esc_html($row['credentials']); ?></p>
                        <?php endif; ?>
                        <?php if ('' !== $row['location']) : ?>
                            <p class="medbeafgallery-dm-location"><span class="dashicons dashicons-location" aria-hidden="true"></span><?php echo esc_html($row['location']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="medbeafgallery-dm-card-meta">
                        <?php if ($row['count'] > 0) : ?>
                            <a class="medbeafgallery-dm-cases" href="<?php echo esc_url($row['cases_url']); ?>"><?php
                                /* translators: %d: number of cases */
                                echo esc_html(sprintf(_n('%d case', '%d cases', $row['count'], 'medical-before-after-gallery'), $row['count']));
                            ?></a>
                        <?php else : ?>
                            <span class="medbeafgallery-dm-cases is-zero"><?php esc_html_e('No cases yet', 'medical-before-after-gallery'); ?></span>
                        <?php endif; ?>
                        <?php if ('' !== $row['url']) : ?>
                            <a class="medbeafgallery-dm-profile" href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Profile', 'medical-before-after-gallery'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'medical-before-after-gallery'); ?></span></a>
                        <?php endif; ?>
                    </div>
                    <div class="medbeafgallery-dm-card-actions">
                        <a class="button medbeafgallery-dm-edit" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'medical-before-after-gallery'); ?><span class="screen-reader-text"> <?php echo esc_html($row['name']); ?></span></a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="medbeafgallery-dm-nomatch" id="medbeafgallery-dm-nomatch" hidden></p>

        <div class="medbeafgallery-dm-empty" id="medbeafgallery-dm-empty" <?php echo empty($rows) ? '' : 'hidden'; ?>>
            <?php
            // Static SVG, no user data.
            echo medbeafgallery_dm_empty_illustration(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <h2 class="medbeafgallery-dm-empty-title"><?php esc_html_e('No doctors yet', 'medical-before-after-gallery'); ?></h2>
            <p class="medbeafgallery-dm-empty-text"><?php esc_html_e('Add the doctors and other providers at your practice, then choose one on each case to show visitors who treated it.', 'medical-before-after-gallery'); ?></p>
            <a class="button button-primary medbeafgallery-dm-empty-add" id="medbeafgallery-dm-empty-add" href="<?php echo esc_url($advanced . '#addtag'); ?>"><?php esc_html_e('Add your first doctor', 'medical-before-after-gallery'); ?></a>
        </div>

        <span class="screen-reader-text" id="medbeafgallery-dm-reorder-hint"><?php esc_html_e('Drag, or press the arrow keys, to change the order.', 'medical-before-after-gallery'); ?></span>

        <?php /* Everything is edited on this screen; WordPress's own term screen is only offered without JavaScript. */ ?>
        <noscript>
            <p class="medbeafgallery-dm-footer">
                <a href="<?php echo esc_url($advanced); ?>"><?php esc_html_e('Open the standard WordPress doctors screen', 'medical-before-after-gallery'); ?></a>
            </p>
        </noscript>

        <?php medbeafgallery_dm_drawer(); ?>
    </div>
    <?php
}

/**
 * The add / edit side panel (filled in and opened by the script).
 */
function medbeafgallery_dm_drawer() {
    ?>
    <div class="medbeafgallery-dm-drawer" id="medbeafgallery-dm-drawer" hidden>
        <div class="medbeafgallery-dm-backdrop" data-dm-close></div>
        <div class="medbeafgallery-dm-sheet" role="dialog" aria-modal="true" aria-labelledby="medbeafgallery-dm-drawer-title">
            <form class="medbeafgallery-dm-form" id="medbeafgallery-dm-form" novalidate>
                <div class="medbeafgallery-dm-sheet-head">
                    <h2 class="medbeafgallery-dm-sheet-title" id="medbeafgallery-dm-drawer-title"><?php esc_html_e('Add doctor', 'medical-before-after-gallery'); ?></h2>
                    <button type="button" class="medbeafgallery-dm-close" data-dm-close aria-label="<?php esc_attr_e('Close without saving', 'medical-before-after-gallery'); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="medbeafgallery-dm-sheet-body">
                    <div class="medbeafgallery-dm-form-error" id="medbeafgallery-dm-form-error" role="alert" hidden></div>

                    <div class="medbeafgallery-dm-photo-field" role="group" aria-labelledby="medbeafgallery-dm-photo-label">
                        <span class="medbeafgallery-dm-avatar medbeafgallery-dm-avatar-lg" id="medbeafgallery-dm-photo-preview"></span>
                        <div class="medbeafgallery-dm-photo-controls">
                            <span class="medbeafgallery-dm-label" id="medbeafgallery-dm-photo-label"><?php esc_html_e('Photo', 'medical-before-after-gallery'); ?></span>
                            <div class="medbeafgallery-dm-photo-buttons">
                                <button type="button" class="button" id="medbeafgallery-dm-photo-choose"><?php esc_html_e('Choose photo', 'medical-before-after-gallery'); ?></button>
                                <button type="button" class="button-link medbeafgallery-dm-photo-remove" id="medbeafgallery-dm-photo-remove" hidden><?php esc_html_e('Remove', 'medical-before-after-gallery'); ?><span class="screen-reader-text"> <?php esc_html_e('photo', 'medical-before-after-gallery'); ?></span></button>
                            </div>
                            <p class="medbeafgallery-dm-hint" id="medbeafgallery-dm-photo-hint"><?php esc_html_e('A square head-and-shoulders photo works best.', 'medical-before-after-gallery'); ?></p>
                        </div>
                    </div>

                    <div class="medbeafgallery-dm-field">
                        <label for="medbeafgallery-dm-name"><?php esc_html_e('Name', 'medical-before-after-gallery'); ?> <span class="medbeafgallery-dm-required"><?php esc_html_e('(required)', 'medical-before-after-gallery'); ?></span></label>
                        <input type="text" id="medbeafgallery-dm-name" name="name" maxlength="200" autocomplete="off" required aria-required="true" placeholder="<?php esc_attr_e('e.g. Dr. Jane Smith', 'medical-before-after-gallery'); ?>">
                        <p class="medbeafgallery-dm-field-error" id="medbeafgallery-dm-name-error" hidden></p>
                    </div>

                    <div class="medbeafgallery-dm-field">
                        <label for="medbeafgallery-dm-credentials"><?php esc_html_e('Credentials', 'medical-before-after-gallery'); ?></label>
                        <input type="text" id="medbeafgallery-dm-credentials" name="credentials" maxlength="160" autocomplete="off" aria-describedby="medbeafgallery-dm-credentials-hint">
                        <p class="medbeafgallery-dm-hint" id="medbeafgallery-dm-credentials-hint"><?php esc_html_e('For example: MD, FACS — Board-certified plastic surgeon.', 'medical-before-after-gallery'); ?></p>
                    </div>

                    <div class="medbeafgallery-dm-field">
                        <label for="medbeafgallery-dm-location"><?php esc_html_e('Location', 'medical-before-after-gallery'); ?></label>
                        <input type="text" id="medbeafgallery-dm-location" name="location" maxlength="120" autocomplete="off" aria-describedby="medbeafgallery-dm-location-hint">
                        <p class="medbeafgallery-dm-hint" id="medbeafgallery-dm-location-hint"><?php esc_html_e('The clinic or city where they see patients.', 'medical-before-after-gallery'); ?></p>
                    </div>

                    <div class="medbeafgallery-dm-field">
                        <label for="medbeafgallery-dm-url"><?php esc_html_e('Profile link', 'medical-before-after-gallery'); ?></label>
                        <input type="url" id="medbeafgallery-dm-url" name="url" inputmode="url" autocomplete="off" placeholder="https://" aria-describedby="medbeafgallery-dm-url-hint">
                        <p class="medbeafgallery-dm-hint" id="medbeafgallery-dm-url-hint"><?php esc_html_e('Optional page about this doctor on your website.', 'medical-before-after-gallery'); ?></p>
                        <p class="medbeafgallery-dm-field-error" id="medbeafgallery-dm-url-error" hidden></p>
                    </div>
                </div>

                <div class="medbeafgallery-dm-sheet-foot">
                    <button type="submit" class="button button-primary" id="medbeafgallery-dm-save"><?php esc_html_e('Save doctor', 'medical-before-after-gallery'); ?></button>
                    <button type="button" class="button" data-dm-close><?php esc_html_e('Cancel', 'medical-before-after-gallery'); ?></button>
                    <a class="medbeafgallery-dm-sheet-more" id="medbeafgallery-dm-more" href="#" hidden><?php esc_html_e('More settings', 'medical-before-after-gallery'); ?></a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
