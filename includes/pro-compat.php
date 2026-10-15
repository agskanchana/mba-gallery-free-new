<?php
/**
 * Compatibility with the Pro add-on.
 *
 * The free plugin (WordPress.org) and the Pro add-on (Freemius) ship and update
 * separately, so any pair of versions can meet on a live site. Free never removes
 * or changes what Pro relies on (see the free <-> Pro contract in CLAUDE.md); this
 * file covers the two things it can still do for an older Pro:
 *
 * - tell the owner when the active Pro add-on is older than the Pro release this
 *   version of free was built and tested with (MEDBEAFGALLERY_RECOMMENDED_PRO_VERSION);
 * - keep Pro's embed shortcodes from printing as raw text while Pro is not
 *   running them.
 *
 * Pro's half of the handshake — warning when free is the one that is behind —
 * lives in the Pro add-on (mba_gallery_pro_init()).
 *
 * @package Medical_Before_After_Gallery
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Version of the active Pro add-on, or null when Pro is not active.
 *
 * Every Pro release defines MBA_GALLERY_PRO_VERSION as its main file loads,
 * licensed or not, whatever folder Freemius installed it in. The plugin header
 * is deliberately not used: Pro 1.3.7, 1.3.8 and 1.4.0 all shipped with
 * "Version: 1.3.2" in it.
 *
 * Only reliable from plugins_loaded on: a network-activated free plugin loads
 * before a site-activated Pro.
 *
 * @return string|null
 */
function medbeafgallery_get_pro_version() {
    return defined('MBA_GALLERY_PRO_VERSION') ? (string) MBA_GALLERY_PRO_VERSION : null;
}

/**
 * Whether the active Pro add-on is older than this free release expects.
 *
 * @return bool
 */
function medbeafgallery_pro_needs_update() {
    $pro_version = medbeafgallery_get_pro_version();

    return $pro_version !== null
        && version_compare($pro_version, MEDBEAFGALLERY_RECOMMENDED_PRO_VERSION, '<');
}

/**
 * The version pair a dismissal applies to: the notice comes back when either
 * plugin changes while the two are still out of step.
 *
 * @return string
 */
function medbeafgallery_pro_compat_pair() {
    return MEDBEAFGALLERY_VERSION . '|' . medbeafgallery_get_pro_version();
}

/**
 * Admin notice: the Pro add-on needs updating to match this free release.
 *
 * Shown to people who can update plugins, on the screens where that is the
 * natural next step (Dashboard, Plugins, Updates) and on the gallery's own
 * screens — never site-wide on every admin page.
 */
function medbeafgallery_outdated_pro_notice() {
    if (!medbeafgallery_pro_needs_update() || !current_user_can('update_plugins')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screens = array('dashboard', 'dashboard-network', 'plugins', 'plugins-network', 'update-core', 'update-core-network');
    if (!$screen || (!in_array($screen->id, $screens, true) && strpos($screen->id, 'medbeafgallery') === false)) {
        return;
    }

    if (get_user_meta(get_current_user_id(), 'medbeafgallery_pro_compat_dismissed', true) === medbeafgallery_pro_compat_pair()) {
        return;
    }

    $dismiss_url = wp_nonce_url(
        add_query_arg('medbeafgallery_dismiss_pro_compat', '1'),
        'medbeafgallery_dismiss_pro_compat'
    );
    ?>
    <div class="notice notice-warning medbeafgallery-pro-compat-notice">
        <p>
            <strong><?php esc_html_e('Medical Before After Gallery:', 'medical-before-after-gallery'); ?></strong>
            <?php
            printf(
                /* translators: %1$s: installed Pro add-on version, %2$s: this free plugin's version, %3$s: Pro version it was built for */
                esc_html__('The Pro add-on is at version %1$s, but this version of the plugin (%2$s) is built for Pro %3$s or newer. Your galleries keep working; update the Pro add-on to get the fixes that need both plugins.', 'medical-before-after-gallery'),
                esc_html(medbeafgallery_get_pro_version()),
                esc_html(MEDBEAFGALLERY_VERSION),
                esc_html(MEDBEAFGALLERY_RECOMMENDED_PRO_VERSION)
            );
            ?>
        </p>
        <p>
            <a href="<?php echo esc_url(self_admin_url('plugins.php')); ?>"><?php esc_html_e('Go to Plugins', 'medical-before-after-gallery'); ?></a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss', 'medical-before-after-gallery'); ?></a>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'medbeafgallery_outdated_pro_notice');
add_action('network_admin_notices', 'medbeafgallery_outdated_pro_notice');

/**
 * Remember a dismissal of the outdated-Pro notice for this version pair only.
 */
function medbeafgallery_handle_pro_compat_dismiss() {
    if (empty($_GET['medbeafgallery_dismiss_pro_compat'])) {
        return;
    }

    if (!current_user_can('update_plugins')) {
        return;
    }

    check_admin_referer('medbeafgallery_dismiss_pro_compat');

    update_user_meta(get_current_user_id(), 'medbeafgallery_pro_compat_dismissed', medbeafgallery_pro_compat_pair());

    wp_safe_redirect(remove_query_arg(array('medbeafgallery_dismiss_pro_compat', '_wpnonce')));
    exit;
}
add_action('admin_init', 'medbeafgallery_handle_pro_compat_dismiss');

/**
 * Stand-ins for the Pro add-on's embed shortcodes.
 *
 * Every Pro release registers [mba_case] and [mba_category_carousel] only while
 * its features are running, so a page that uses them showed the raw shortcode
 * text to visitors whenever Pro was inactive, unlicensed or waiting for a newer
 * free plugin. Registered late on init, and only for a tag nobody registered:
 * when Pro runs, its own handler is already in place and this does nothing.
 */
function medbeafgallery_register_pro_embed_placeholders() {
    foreach (array('mba_case', 'mba_category_carousel') as $tag) {
        if (!shortcode_exists($tag)) {
            add_shortcode($tag, 'medbeafgallery_pro_embed_placeholder');
        }
    }
}
add_action('init', 'medbeafgallery_register_pro_embed_placeholders', 99);

/**
 * Renders nothing for visitors, and a short explanation for people who can edit
 * the page, so a missing embed is noticed rather than silently absent.
 *
 * @param array|string $atts    Shortcode attributes (unused).
 * @param string|null  $content Enclosed content (unused).
 * @param string       $tag     The shortcode tag.
 * @return string
 */
function medbeafgallery_pro_embed_placeholder($atts = array(), $content = null, $tag = '') {
    if (!current_user_can('edit_posts')) {
        return '';
    }

    return sprintf(
        '<p class="medbeafgallery-pro-embed-placeholder" style="padding:8px 12px;border:1px dashed #c3c4c7;color:#50575e;">%s</p>',
        esc_html(sprintf(
            /* translators: %s: shortcode tag, e.g. mba_case */
            __('[%s] is a Medical Before After Gallery Pro embed. Visitors see nothing here while the Pro add-on is inactive, unlicensed or waiting for an update; only people who can edit this page see this note.', 'medical-before-after-gallery'),
            $tag
        ))
    );
}
