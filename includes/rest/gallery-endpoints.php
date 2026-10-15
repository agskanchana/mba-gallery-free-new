<?php
/**
 * Medical Before After Gallery - Gallery REST API Endpoints
 *
 * @package MEDBEAFGALLERY
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Case Query Builder Class
 */
class MedBeAfGalleryCaseQueryBuilder {
    private $args = array();
    private $meta_query = array();
    private $tax_query = array();

    public function __construct() {
        $this->args = array(
            'post_type' => 'medbeafgallery_case',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );
    }

    /**
     * Set pagination
     */
    public function withPagination($per_page = -1, $page = 1) {
        $this->args['posts_per_page'] = $per_page;
        $this->args['paged'] = $page;
        return $this;
    }

    /**
     * Filter by category
     */
    public function withCategory($category) {
        if (!empty($category) && $category !== 'all') {
            $this->tax_query[] = array(
                'taxonomy' => 'medbeafgallery_category',
                'field' => 'slug',
                'terms' => $category,
            );
        }
        return $this;
    }

    /**
     * Filter by featured status
     */
    public function withFeatured($featured) {
        if ($featured === 'true' || $featured === '1') {
            $this->meta_query[] = array(
                'key' => '_medbeafgallery_case_featured',
                'value' => '1',
                'compare' => '=',
            );
        }
        return $this;
    }

    /**
     * Filter by gender
     */
    public function withGender($gender) {
        if (!empty($gender)) {
            $this->meta_query[] = array(
                'key' => '_medbeafgallery_case_gender',
                'value' => $gender,
                'compare' => '=',
            );
        }
        return $this;
    }

    /**
     * Filter by age group
     */
    public function withAgeGroup($age_group) {
        if (!empty($age_group)) {
            $age_range = $this->getAgeRange($age_group);
            $this->meta_query[] = array(
                'key' => '_medbeafgallery_case_age',
                'value' => $age_range,
                'type' => 'numeric',
                'compare' => 'BETWEEN',
            );
        }
        return $this;
    }

    /**
     * Filter by procedure
     */
    public function withProcedure($procedure) {
        if (!empty($procedure)) {
            $this->meta_query[] = array(
                'key' => '_medbeafgallery_case_procedure_type',
                'value' => $procedure,
                'compare' => '=',
            );
        }
        return $this;
    }

    /**
     * Get age range from age group
     */
    private function getAgeRange($age_group) {
        switch ($age_group) {
            case '18-30':
                return array(18, 30);
            case '31-45':
                return array(31, 45);
            case '46-60':
                return array(46, 60);
            case '60+':
                return array(60, 999);
            default:
                return array(0, 999);
        }
    }

    /**
     * Build and return the query arguments
     */
    public function build() {
        if (!empty($this->meta_query)) {
            $this->args['meta_query'] = $this->meta_query;
        }

        if (!empty($this->tax_query)) {
            $this->args['tax_query'] = $this->tax_query;
        }

        return $this->args;
    }
}

/**
 * GET handler for gallery data (Improved version)
 *
 * IMPORTANT: This endpoint enforces a hard case limit for the free version.
 * The limit is applied directly at the data layer, independent of any
 * admin-side limit functions, to prevent bypass through code modification.
 */
function medbeafgallery_rest_get_gallery_data($request) {
    try {
        // ── Hard-coded free-version ceiling ──
        // This value is intentionally NOT derived from medbeafgallery_get_max_cases()
        // so that editing that function cannot lift the REST output cap.
        $free_hard_limit = 12;

        // If a valid Pro license is active the ceiling is removed.
        $has_pro = apply_filters('medbeafgallery_has_valid_pro_license', false);
        $effective_limit = $has_pro ? 0 : $free_hard_limit; // 0 = unlimited

        // Get query parameters
        $category = $request->get_param('category');
        $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : -1;
        $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
        $featured = $request->get_param('featured');
        $gender = $request->get_param('gender');
        $age_group = $request->get_param('age_group');
        $procedure = $request->get_param('procedure');

        // If per_page is unlimited (-1) and a limit is active, cap it
        if ($effective_limit > 0 && ($per_page === -1 || $per_page > $effective_limit)) {
            $per_page = $effective_limit;
        }

        $page_size = $per_page;

        // Guard against pagination walking past the free ceiling. The per-page
        // cap above only bounds the size of each page — on its own it would let
        // per_page=12&page=2 return cases 13-24.
        //
        // Note the offset is enforced by slicing the collected results rather
        // than by shrinking $per_page: WP_Query derives its offset from
        // paged * posts_per_page, so shrinking posts_per_page for a page that
        // straddles the ceiling would silently re-point it at earlier cases.
        $offset = 0;

        if ($effective_limit > 0) {
            if ($page < 1) {
                $page = 1;
            }

            $offset = ($page - 1) * $per_page;

            if ($offset >= $effective_limit) {
                return array(
                    'cases'       => array(),
                    'total'       => 0,
                    'total_pages' => $page_size > 0 ? (int) ceil($effective_limit / $page_size) : 0,
                    'is_limited'  => true,
                    'limit'       => $effective_limit,
                );
            }
        }

        // Build query using the query builder
        $builder = new MedBeAfGalleryCaseQueryBuilder();
        $args = $builder
            ->withPagination($per_page, $page)
            ->withCategory($category)
            ->withFeatured($featured)
            ->withGender($gender)
            ->withAgeGroup($age_group)
            ->withProcedure($procedure)
            ->build();

        // Get cases
        $query = new WP_Query($args);
        $cases = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $cases[] = medbeafgallery_format_case_data(get_the_ID());

                // Double-guard: stop collecting once this page reaches the hard
                // limit. Counted from the page's offset so a later page cannot
                // slip past the ceiling.
                if ($effective_limit > 0 && ($offset + count($cases)) >= $effective_limit) {
                    break;
                }
            }
            wp_reset_postdata();
        }

        // Total cases that actually exist in the DB (for "upgrade" messaging)
        $total_in_db = intval($query->found_posts);

        // Report the capped page count, otherwise the front-end would render
        // "next page" controls for pages the ceiling now returns empty.
        $total_pages = (int) $query->max_num_pages;
        // Unpaginated (per_page=-1, the default once Pro lifts the ceiling),
        // WP_Query never computes max_num_pages, so a non-empty result
        // reported zero pages.
        if ($per_page === -1) {
            $total_pages = $total_in_db > 0 ? 1 : 0;
        }
        if ($effective_limit > 0 && $page_size > 0) {
            $total_pages = min($total_pages, (int) ceil($effective_limit / $page_size));
        }

        $response = array(
            'cases'       => $cases,
            'total'       => count($cases),
            'total_pages' => $total_pages,
        );

        // If more cases exist than the limit, tell the front-end so it can
        // display an upgrade prompt instead of a "next page" button.
        if ($effective_limit > 0 && $total_in_db > $effective_limit) {
            $response['is_limited']  = true;
            $response['limit']       = $effective_limit;
            $response['total_in_db'] = $total_in_db;
        }

        return $response;

    } catch (Exception $e) {
        MedBeAfGalleryErrorHandler::handleException($e, 'rest-api');
        return new WP_Error('query_error', 'Failed to retrieve gallery data', array('status' => 500));
    }
}

/**
 * Format case data for API response
 */
function medbeafgallery_format_case_data($post_id) {
    // Get category info
    $categories = get_the_terms($post_id, 'medbeafgallery_category');
    $category_data = array();
    $category_names = array();

    if ($categories && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            $category_data[] = array(
                'id' => $category->term_id,
                'slug' => $category->slug,
                'name' => $category->name
            );
            $category_names[] = $category->name;
        }
    }

    // Get first category for primary display
    $primary_category = !empty($category_data) ? $category_data[0] : array('id' => '', 'slug' => '', 'name' => '');

    // Get main before/after images
    $main_before_id = get_post_meta($post_id, '_medbeafgallery_main_before_image', true);
    $main_after_id = get_post_meta($post_id, '_medbeafgallery_main_after_image', true);
    $main_description = get_post_meta($post_id, '_medbeafgallery_main_description', true);

    $main_before_url = medbeafgallery_get_display_image_url($main_before_id, 'full');
    $main_after_url = medbeafgallery_get_display_image_url($main_after_id, 'full');

    // Card-sized variants. The grid and the Pro masonry/carousel cards render
    // these into ~200-400px slots, so shipping the full-resolution original
    // there meant downloading multi-megabyte clinical photos to draw a thumbnail.
    // The full-size URLs above are untouched — the modal and the Pro single-case
    // embed still use them, and existing consumers keep working unchanged.
    $main_before_thumb = medbeafgallery_get_card_image_url($main_before_id);
    $main_after_thumb = medbeafgallery_get_card_image_url($main_after_id);

    // Get alt text for the images
    $main_before_alt = get_post_meta($main_before_id, '_wp_attachment_image_alt', true);
    $main_after_alt = get_post_meta($main_after_id, '_wp_attachment_image_alt', true);

    if (empty($main_before_alt)) {
        /* translators: %s: case title */
        $main_before_alt = sprintf(__('Before photo - %s', 'medical-before-after-gallery'), get_the_title($post_id));
    }

    if (empty($main_after_alt)) {
        /* translators: %s: case title */
        $main_after_alt = sprintf(__('After photo - %s', 'medical-before-after-gallery'), get_the_title($post_id));
    }

    // Get additional image pairs
    $additional_pairs = get_post_meta($post_id, '_medbeafgallery_additional_image_pairs', true);
    $image_pairs = array();

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $debug_message = 'Post ID: ' . $post_id . ' - Additional pairs: ' . json_encode($additional_pairs);
        // Use call_user_func to avoid static analysis warnings
        call_user_func('error_log', $debug_message);
    }

    if (is_array($additional_pairs) && !empty($additional_pairs)) {
        foreach ($additional_pairs as $index => $pair) {
            // Check for both field name variations
            $before_id = !empty($pair['before_id']) ? $pair['before_id'] : (!empty($pair['before_image']) ? $pair['before_image'] : null);
            $after_id = !empty($pair['after_id']) ? $pair['after_id'] : (!empty($pair['after_image']) ? $pair['after_image'] : null);

            if (!empty($before_id) && !empty($after_id)) {
                $pair_before_url = medbeafgallery_get_display_image_url($before_id, 'full');
                $pair_after_url = medbeafgallery_get_display_image_url($after_id, 'full');

                // Skip if images don't exist
                if (!$pair_before_url || !$pair_after_url) {
                    continue;
                }

                $pair_before_alt = get_post_meta($before_id, '_wp_attachment_image_alt', true);
                $pair_after_alt = get_post_meta($after_id, '_wp_attachment_image_alt', true);

                if (empty($pair_before_alt)) {
                    /* translators: %s: case title */
                    $pair_before_alt = sprintf(__('Before photo - %s', 'medical-before-after-gallery'), get_the_title($post_id));
                }

                if (empty($pair_after_alt)) {
                    /* translators: %s: case title */
                    $pair_after_alt = sprintf(__('After photo - %s', 'medical-before-after-gallery'), get_the_title($post_id));
                }

                $image_pairs[] = array(
                    'beforeImg' => $pair_before_url,
                    'afterImg' => $pair_after_url,
                    // Card-sized variants; see medbeafgallery_get_card_image_url().
                    'beforeThumb' => medbeafgallery_get_card_image_url($before_id),
                    'afterThumb' => medbeafgallery_get_card_image_url($after_id),
                    'beforeAlt' => $pair_before_alt,
                    'afterAlt' => $pair_after_alt,
                    'description' => isset($pair['description']) ? $pair['description'] : ''
                );
            }
        }
    }

    // Get case details
    $gender = get_post_meta($post_id, '_medbeafgallery_case_gender', true);
    $age = get_post_meta($post_id, '_medbeafgallery_case_age', true);
    $recovery = get_post_meta($post_id, '_medbeafgallery_case_recovery', true);
    $duration = get_post_meta($post_id, '_medbeafgallery_case_duration', true);
    $results = get_post_meta($post_id, '_medbeafgallery_case_results', true);
    $procedure = get_post_meta($post_id, '_medbeafgallery_case_procedure_type', true);
    $procedure_info = get_post_meta($post_id, '_medbeafgallery_procedure_info', true);

    // Determine age group
    $age_group = '';
    if (!empty($age)) {
        $age_num = intval($age);
        if ($age_num >= 18 && $age_num <= 30) {
            $age_group = '18-30';
        } elseif ($age_num >= 31 && $age_num <= 45) {
            $age_group = '31-45';
        } elseif ($age_num >= 46 && $age_num <= 60) {
            $age_group = '46-60';
        } elseif ($age_num > 60) {
            $age_group = '60+';
        }
    }

    // Render the post content once; 'description' and 'content' both expose it.
    // Read the content from the post object rather than get_the_content(), which
    // depends on the loop globals and returns the wrong post (or nothing) when
    // this function is called outside a loop — as the Pro single-case embed does.
    $post_object  = get_post($post_id);
    $raw_content  = ($post_object && !post_password_required($post_object)) ? $post_object->post_content : '';
    $rendered_content = apply_filters('the_content', $raw_content);

    // Build case data
    $case_data = array(
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'categories' => $category_data,
        'category_names' => $category_names,
        'primary_category' => $primary_category,
        'main_before_image' => $main_before_url,
        'main_after_image' => $main_after_url,
        'main_before_alt' => $main_before_alt,
        'main_after_alt' => $main_after_alt,
        'main_description' => $main_description,
        'image_pairs' => $image_pairs,
        'total_pairs' => count($image_pairs) + 1, // +1 for main pair
        'gender' => $gender,
        'age' => $age,
        'age_group' => $age_group,
        'recovery' => $recovery,
        'duration' => $duration,
        'results' => $results,
        'procedure' => $procedure,
        'procedure_info' => $procedure_info,
        'featured' => get_post_meta($post_id, '_medbeafgallery_case_featured', true) === '1',
        // Add backward compatibility fields for JavaScript
        'date' => get_the_date('F j, Y', $post_id),
        // Machine-readable publish timestamp (GMT Unix) for reliable client-side sorting.
        'timestamp' => (int) get_post_time('U', true, $post_id),
        'category' => $primary_category['slug'],
        'categoryName' => $primary_category['name'],
        'categoryNames' => $category_names,
        'beforeImg' => $main_before_url,
        'afterImg' => $main_after_url,
        // Card-sized variants — additive, so an older Pro add-on that only knows
        // about beforeImg/afterImg keeps rendering exactly as before.
        'beforeThumb' => $main_before_thumb,
        'afterThumb' => $main_after_thumb,
        'beforeAlt' => $main_before_alt,
        'afterAlt' => $main_after_alt,
        'imagePairs' => $image_pairs,
        'ageGroup' => $age_group,
        'procedureType' => $procedure, // Add procedureType for JavaScript compatibility
        // Run the_content once and reuse it — the filter chain is expensive
        // (shortcodes, oEmbed, wpautop, plus every other plugin's filters).
        'description' => $rendered_content,
        'content' => $rendered_content,
        'procedureInfo' => apply_filters('the_content', $procedure_info),
        // No 'permalink' key: the case post type is registered with
        // publicly_queryable => false, so get_permalink() only ever produced a
        // URL that 404s. Nothing consumed it.
    );

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $debug_message = 'Final case data for ' . $post_id . ': imagePairs count = ' . count($image_pairs);
        call_user_func('error_log', $debug_message);
    }

    return $case_data;
}