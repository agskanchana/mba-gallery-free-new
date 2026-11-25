<?php
/**
 * Doctors — who treated a case.
 *
 * The medbeafgallery_doctor taxonomy (registered in includes/post-types.php)
 * holds one term per doctor or other provider. The term name is the person's
 * name; their photo, credentials, location and profile link are term meta.
 * Each case can name one doctor; the gallery shows a doctor card with the case.
 *
 * @package MEDBEAFGALLERY
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Term meta keys used for a doctor.
 *
 * @return array field => meta key
 */
function medbeafgallery_doctor_meta_keys() {
    return array(
        'photo'       => 'medbeafgallery_doctor_photo',
        'credentials' => 'medbeafgallery_doctor_credentials',
        'location'    => 'medbeafgallery_doctor_location',
        'url'         => 'medbeafgallery_doctor_url',
    );
}

/**
 * A doctor term as the gallery uses it.
 *
 * @param WP_Term $term Doctor term.
 * @return array { id, name, slug, photo, photoAlt, credentials, location, url }
 */
function medbeafgallery_format_doctor($term) {
    $keys     = medbeafgallery_doctor_meta_keys();
    $photo_id = (int) get_term_meta($term->term_id, $keys['photo'], true);
    $photo    = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    $alt      = $photo_id ? get_post_meta($photo_id, '_wp_attachment_image_alt', true) : '';
    // WordPress stores "&" in term names as "&amp;"; consumers escape on output.
    $name     = wp_specialchars_decode($term->name, ENT_QUOTES);

    return array(
        'id'          => (int) $term->term_id,
        'name'        => $name,
        'slug'        => $term->slug,
        'photo'       => $photo ? $photo : '',
        'photoAlt'    => $alt ? $alt : $name,
        'credentials' => (string) get_term_meta($term->term_id, $keys['credentials'], true),
        'location'    => (string) get_term_meta($term->term_id, $keys['location'], true),
        'url'         => (string) get_term_meta($term->term_id, $keys['url'], true),
    );
}

/**
 * The doctors assigned to a case, formatted.
 *
 * @param int $post_id Case ID.
 * @return array
 */
function medbeafgallery_get_case_doctors($post_id) {
    $terms = get_the_terms($post_id, 'medbeafgallery_doctor');
    if (!$terms || is_wp_error($terms)) {
        return array();
    }
    return array_map('medbeafgallery_format_doctor', array_values($terms));
}

/* ── Admin menu ────────────────────────────────────────────────────────────── */

/**
 * Is the plugin's own Doctors screen (admin/doctors-manager.php) loaded?
 *
 * @return bool
 */
function medbeafgallery_doctors_manager_available() {
    return defined('MEDBEAFGALLERY_DOCTORS_PAGE') && function_exists('medbeafgallery_doctors_manager_page');
}

/**
 * Where "manage doctors" links go: the plugin's Doctors screen when it is
 * loaded, else WordPress's term screen.
 *
 * @param bool $add Open the "Add doctor" panel straight away.
 * @return string
 */
function medbeafgallery_doctors_admin_url($add = false) {
    if (medbeafgallery_doctors_manager_available()) {
        $args = array('page' => MEDBEAFGALLERY_DOCTORS_PAGE);
        if ($add) {
            $args['add'] = '1';
        }
        return add_query_arg($args, admin_url('admin.php'));
    }
    return admin_url('edit-tags.php?taxonomy=medbeafgallery_doctor&post_type=medbeafgallery_case');
}

function medbeafgallery_doctors_admin_menu() {
    if (!taxonomy_exists('medbeafgallery_doctor')) {
        return;
    }
    if (medbeafgallery_doctors_manager_available()) {
        // The plugin's own Doctors screen; WordPress's term screen stays
        // reachable from its "Advanced" link.
        add_submenu_page(
            'medbeafgallery-gallery',
            esc_html__('Doctors', 'medical-before-after-gallery'),
            esc_html__('Doctors', 'medical-before-after-gallery'),
            'manage_options',
            MEDBEAFGALLERY_DOCTORS_PAGE,
            'medbeafgallery_doctors_manager_page',
            4 // After Categories, before Pro Features.
        );
        return;
    }
    add_submenu_page(
        'medbeafgallery-gallery',
        esc_html__('Doctors', 'medical-before-after-gallery'),
        esc_html__('Doctors', 'medical-before-after-gallery'),
        'manage_options',
        'edit-tags.php?taxonomy=medbeafgallery_doctor&post_type=medbeafgallery_case',
        '',
        4 // After Categories, before Pro Features.
    );
}
add_action('admin_menu', 'medbeafgallery_doctors_admin_menu', 20);

function medbeafgallery_doctors_parent_file($parent_file) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->taxonomy === 'medbeafgallery_doctor') {
        return 'medbeafgallery-gallery';
    }
    return $parent_file;
}
add_filter('parent_file', 'medbeafgallery_doctors_parent_file', 20);

function medbeafgallery_doctors_submenu_file($submenu_file) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->taxonomy === 'medbeafgallery_doctor') {
        // WordPress's own doctor screens ("Advanced") still belong to Doctors.
        return medbeafgallery_doctors_manager_available()
            ? MEDBEAFGALLERY_DOCTORS_PAGE
            : 'edit-tags.php?taxonomy=medbeafgallery_doctor&post_type=medbeafgallery_case';
    }
    return $submenu_file;
}
add_filter('submenu_file', 'medbeafgallery_doctors_submenu_file', 20);

/* ── Doctor fields on the Doctors screen ───────────────────────────────────── */

function medbeafgallery_doctors_enqueue($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->taxonomy !== 'medbeafgallery_doctor' || !in_array($hook, array('edit-tags.php', 'term.php'), true)) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script(
        'medbeafgallery-doctors-admin',
        MEDBEAFGALLERY_URL . 'admin/js/doctors.js',
        array('jquery'),
        MEDBEAFGALLERY_VERSION,
        true
    );
    wp_localize_script('medbeafgallery-doctors-admin', 'medbeafgalleryDoctors', array(
        'frameTitle'  => __('Choose a photo', 'medical-before-after-gallery'),
        'frameButton' => __('Use this photo', 'medical-before-after-gallery'),
    ));
}
add_action('admin_enqueue_scripts', 'medbeafgallery_doctors_enqueue');

/**
 * The photo picker shared by the add and edit forms.
 *
 * @param int $photo_id Attachment ID or 0.
 */
function medbeafgallery_doctor_photo_field($photo_id) {
    $url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    ?>
    <div class="medbeafgallery-doctor-photo-field">
        <input type="hidden" name="medbeafgallery_doctor_photo" class="medbeafgallery-doctor-photo-id" value="<?php echo esc_attr($photo_id ? $photo_id : ''); ?>">
        <img class="medbeafgallery-doctor-photo-preview" src="<?php echo esc_url($url); ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:50%;<?php echo $url ? '' : 'display:none;'; ?>">
        <p>
            <button type="button" class="button medbeafgallery-doctor-photo-choose"><?php esc_html_e('Choose photo', 'medical-before-after-gallery'); ?></button>
            <button type="button" class="button-link medbeafgallery-doctor-photo-remove" <?php echo $url ? '' : 'style="display:none"'; ?>><?php esc_html_e('Remove photo', 'medical-before-after-gallery'); ?></button>
        </p>
    </div>
    <?php
}

function medbeafgallery_doctor_add_fields() {
    wp_nonce_field('medbeafgallery_doctor_meta', 'medbeafgallery_doctor_nonce');
    ?>
    <div class="form-field">
        <label><?php esc_html_e('Photo', 'medical-before-after-gallery'); ?></label>
        <?php medbeafgallery_doctor_photo_field(0); ?>
        <p class="description"><?php esc_html_e('A square head-and-shoulders photo works best.', 'medical-before-after-gallery'); ?></p>
    </div>
    <div class="form-field">
        <label for="medbeafgallery_doctor_credentials"><?php esc_html_e('Credentials', 'medical-before-after-gallery'); ?></label>
        <input type="text" name="medbeafgallery_doctor_credentials" id="medbeafgallery_doctor_credentials" value="" maxlength="160">
        <p class="description"><?php esc_html_e('For example: MD, FACS — Board-certified plastic surgeon.', 'medical-before-after-gallery'); ?></p>
    </div>
    <div class="form-field">
        <label for="medbeafgallery_doctor_location"><?php esc_html_e('Location', 'medical-before-after-gallery'); ?></label>
        <input type="text" name="medbeafgallery_doctor_location" id="medbeafgallery_doctor_location" value="" maxlength="120">
    </div>
    <div class="form-field">
        <label for="medbeafgallery_doctor_url"><?php esc_html_e('Profile link', 'medical-before-after-gallery'); ?></label>
        <input type="url" name="medbeafgallery_doctor_url" id="medbeafgallery_doctor_url" value="" placeholder="https://">
        <p class="description"><?php esc_html_e('Optional page about this doctor on your website.', 'medical-before-after-gallery'); ?></p>
    </div>
    <?php
}
add_action('medbeafgallery_doctor_add_form_fields', 'medbeafgallery_doctor_add_fields');

function medbeafgallery_doctor_edit_fields($term) {
    $keys = medbeafgallery_doctor_meta_keys();
    wp_nonce_field('medbeafgallery_doctor_meta', 'medbeafgallery_doctor_nonce');
    ?>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Photo', 'medical-before-after-gallery'); ?></th>
        <td><?php medbeafgallery_doctor_photo_field((int) get_term_meta($term->term_id, $keys['photo'], true)); ?></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="medbeafgallery_doctor_credentials"><?php esc_html_e('Credentials', 'medical-before-after-gallery'); ?></label></th>
        <td>
            <input type="text" name="medbeafgallery_doctor_credentials" id="medbeafgallery_doctor_credentials" maxlength="160" value="<?php echo esc_attr(get_term_meta($term->term_id, $keys['credentials'], true)); ?>">
            <p class="description"><?php esc_html_e('For example: MD, FACS — Board-certified plastic surgeon.', 'medical-before-after-gallery'); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="medbeafgallery_doctor_location"><?php esc_html_e('Location', 'medical-before-after-gallery'); ?></label></th>
        <td><input type="text" name="medbeafgallery_doctor_location" id="medbeafgallery_doctor_location" maxlength="120" value="<?php echo esc_attr(get_term_meta($term->term_id, $keys['location'], true)); ?>"></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="medbeafgallery_doctor_url"><?php esc_html_e('Profile link', 'medical-before-after-gallery'); ?></label></th>
        <td><input type="url" name="medbeafgallery_doctor_url" id="medbeafgallery_doctor_url" placeholder="https://" value="<?php echo esc_attr(get_term_meta($term->term_id, $keys['url'], true)); ?>"></td>
    </tr>
    <?php
}
add_action('medbeafgallery_doctor_edit_form_fields', 'medbeafgallery_doctor_edit_fields');

/**
 * Save the doctor fields. Only from our own form (nonce), never from other
 * term updates such as wp_insert_term() calls made by code.
 *
 * @param int $term_id Term ID.
 */
function medbeafgallery_doctor_save_fields($term_id) {
    if (!isset($_POST['medbeafgallery_doctor_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['medbeafgallery_doctor_nonce'])), 'medbeafgallery_doctor_meta')) {
        return;
    }
    $tax = get_taxonomy('medbeafgallery_doctor');
    if (!$tax || !current_user_can($tax->cap->edit_terms)) {
        return;
    }
    $keys = medbeafgallery_doctor_meta_keys();

    $photo = isset($_POST['medbeafgallery_doctor_photo']) ? absint($_POST['medbeafgallery_doctor_photo']) : 0;
    if ($photo && wp_attachment_is_image($photo)) {
        update_term_meta($term_id, $keys['photo'], $photo);
    } else {
        delete_term_meta($term_id, $keys['photo']);
    }

    foreach (array('credentials' => 160, 'location' => 120) as $field => $max) {
        $value = isset($_POST['medbeafgallery_doctor_' . $field]) ? sanitize_text_field(wp_unslash($_POST['medbeafgallery_doctor_' . $field])) : '';
        $value = mb_substr($value, 0, $max);
        if ($value !== '') {
            update_term_meta($term_id, $keys[$field], $value);
        } else {
            delete_term_meta($term_id, $keys[$field]);
        }
    }

    $url = isset($_POST['medbeafgallery_doctor_url']) ? esc_url_raw(wp_unslash($_POST['medbeafgallery_doctor_url']), array('http', 'https')) : '';
    if ($url !== '') {
        update_term_meta($term_id, $keys['url'], $url);
    } else {
        delete_term_meta($term_id, $keys['url']);
    }
}
add_action('created_medbeafgallery_doctor', 'medbeafgallery_doctor_save_fields');
add_action('edited_medbeafgallery_doctor', 'medbeafgallery_doctor_save_fields');

/* ── Photo column on the Doctors screen ────────────────────────────────────── */

function medbeafgallery_doctor_columns($columns) {
    $new = array();
    foreach ($columns as $key => $label) {
        if ($key === 'name') {
            $new['medbeafgallery_photo'] = esc_html__('Photo', 'medical-before-after-gallery');
        }
        if ($key === 'description' || $key === 'slug') {
            continue;
        }
        $new[$key] = $label;
    }
    if (isset($new['posts'])) {
        $new['posts'] = esc_html__('Cases', 'medical-before-after-gallery');
    }
    return $new;
}
add_filter('manage_edit-medbeafgallery_doctor_columns', 'medbeafgallery_doctor_columns');

function medbeafgallery_doctor_column_content($content, $column, $term_id) {
    if ($column !== 'medbeafgallery_photo') {
        return $content;
    }
    $keys     = medbeafgallery_doctor_meta_keys();
    $photo_id = (int) get_term_meta($term_id, $keys['photo'], true);
    $url      = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    if (!$url) {
        return '<span aria-hidden="true" style="display:inline-block;width:40px;height:40px;border-radius:50%;background:#eef1f5;"></span>';
    }
    return '<img src="' . esc_url($url) . '" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">';
}
add_filter('manage_medbeafgallery_doctor_custom_column', 'medbeafgallery_doctor_column_content', 10, 3);

/* ── "Doctor" box in the case editor ───────────────────────────────────────── */

function medbeafgallery_doctor_meta_box() {
    add_meta_box(
        'medbeafgallery_doctor_box',
        esc_html__('Doctor', 'medical-before-after-gallery'),
        'medbeafgallery_doctor_meta_box_render',
        'medbeafgallery_case',
        'side',
        'default'
    );
}
add_action('add_meta_boxes_medbeafgallery_case', 'medbeafgallery_doctor_meta_box');

function medbeafgallery_doctor_meta_box_render($post) {
    $doctors = get_terms(array('taxonomy' => 'medbeafgallery_doctor', 'hide_empty' => false, 'orderby' => 'name'));
    $current = wp_get_object_terms($post->ID, 'medbeafgallery_doctor', array('fields' => 'ids'));
    $current = (!is_wp_error($current) && !empty($current)) ? (int) $current[0] : 0;
    $manage  = medbeafgallery_doctors_admin_url();

    wp_nonce_field('medbeafgallery_case_doctor', 'medbeafgallery_case_doctor_nonce');

    if (is_wp_error($doctors) || empty($doctors)) {
        echo '<p>' . esc_html__('No doctors yet.', 'medical-before-after-gallery') . ' <a href="' . esc_url(medbeafgallery_doctors_admin_url(true)) . '">' . esc_html__('Add a doctor', 'medical-before-after-gallery') . '</a></p>';
        echo '<input type="hidden" name="medbeafgallery_case_doctor" value="0">';
        return;
    }
    ?>
    <label for="medbeafgallery_case_doctor" class="screen-reader-text"><?php esc_html_e('Doctor who treated this case', 'medical-before-after-gallery'); ?></label>
    <select name="medbeafgallery_case_doctor" id="medbeafgallery_case_doctor" style="width:100%;">
        <option value="0"><?php esc_html_e('— None —', 'medical-before-after-gallery'); ?></option>
        <?php foreach ($doctors as $doctor) : ?>
            <option value="<?php echo esc_attr($doctor->term_id); ?>" <?php selected($current, $doctor->term_id); ?>><?php echo esc_html(wp_specialchars_decode($doctor->name, ENT_QUOTES)); ?></option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e('Shown as a doctor card with this case.', 'medical-before-after-gallery'); ?> <a href="<?php echo esc_url($manage); ?>"><?php esc_html_e('Manage doctors', 'medical-before-after-gallery'); ?></a></p>
    <?php
}

/**
 * Save the case's doctor. save_post also fires for trash, Quick Edit, REST and
 * code, so act only on a request that carries this box's nonce.
 *
 * @param int $post_id Case ID.
 */
function medbeafgallery_doctor_save_case($post_id) {
    if (!isset($_POST['medbeafgallery_case_doctor_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['medbeafgallery_case_doctor_nonce'])), 'medbeafgallery_case_doctor')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $term_id = isset($_POST['medbeafgallery_case_doctor']) ? absint($_POST['medbeafgallery_case_doctor']) : 0;
    if ($term_id && term_exists($term_id, 'medbeafgallery_doctor')) {
        wp_set_object_terms($post_id, array($term_id), 'medbeafgallery_doctor', false);
    } else {
        wp_set_object_terms($post_id, array(), 'medbeafgallery_doctor', false);
    }
}
add_action('save_post_medbeafgallery_case', 'medbeafgallery_doctor_save_case');
