<?php
// class-pfmp-rest-coupons.php

defined('ABSPATH') || exit;

class PFMP_REST_Coupons {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }

    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/coupons', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_coupons'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 10, 'sanitize_callback' => 'absint'],
                'search' => ['sanitize_callback' => 'sanitize_text_field'], // coupon code
                'sc_coupon_category' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route('pfm-panel/v1', '/coupons/categories', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_coupon_categories'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'hide_empty' => ['sanitize_callback' => 'rest_sanitize_boolean'],
            ],
        ]);
    }

    public function get_coupon_categories(WP_REST_Request $request) {
        $hide_empty = $request->get_param('hide_empty');
        $hide_empty = is_null($hide_empty) ? true : (bool) $hide_empty;

        // Only top-level categories
        $parents = get_terms([
            'taxonomy'   => 'sc_coupon_category',
            'hide_empty' => $hide_empty,
            'parent'     => 0,                   // ✅ top-level only
        ]);
        if (is_wp_error($parents)) {
            return new WP_Error('terms_error', $parents->get_error_message(), ['status' => 500]);
        }

        // Sum counts including children for each parent (nicer display like "CS Team (1443)")
        $out = [];
        foreach ($parents as $p) {
            $sum = (int) $p->count;
            $children = get_terms([
                'taxonomy'   => 'sc_coupon_category',
                'hide_empty' => $hide_empty,
                'parent'     => $p->term_id,
                'fields'     => 'ids',
            ]);
            if (!is_wp_error($children) && !empty($children)) {
                foreach ($children as $child_id) {
                    $child = get_term($child_id, 'sc_coupon_category');
                    if (!is_wp_error($child) && $child) {
                        $sum += (int) $child->count;
                    }
                }
            }
            $out[] = [
                'slug'  => $p->slug,
                'name'  => $p->name,
                'count' => $sum,
            ];
        }

        usort($out, fn($a, $b) => $b['count'] <=> $a['count']);
        return rest_ensure_response($out);
    }


    public function get_coupons(WP_REST_Request $request) {
        $page     = max(1, absint($request->get_param('page')));
        $per_page = min(100, max(1, absint($request->get_param('per_page'))));
        $offset   = ($page - 1) * $per_page;

        $search   = $request->get_param('search');
        $category = $request->get_param('coupon_category');

        $args = [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'fields'         => 'ids',
            'suppress_filters' => true,
        ];

        if (!empty($search)) {
            // Coupon codes are stored in post_title (also in post_name slug).
            $args['s'] = $search;
        }

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'sc_coupon_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                    'include_children' => true, // ✅ parent pick matches children by default
                ]
            ];
        }

        $query = new WP_Query($args);
        $ids   = $query->posts ?: [];
        $rows  = [];

        foreach ($ids as $coupon_id) {
            $coupon = new WC_Coupon($coupon_id);

            $code          = $coupon->get_code();
            $discount_type = $coupon->get_discount_type();
            $amount        = $coupon->get_amount();
            $usage_count   = (int) $coupon->get_usage_count();
            $usage_limit   = $coupon->get_usage_limit() ? (int) $coupon->get_usage_limit() : null;
            $date_obj      = $coupon->get_date_expires();
            $expiry_date   = $date_obj ? $date_obj->date('Y-m-d') : null;

            // All assigned terms (names)
            $terms = wp_get_post_terms($coupon_id, 'sc_coupon_category');
            $categories = [];
            $parent_tops = [];

            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $t) {
                    $categories[] = $t->name;

                    // Walk up to top-level parent
                    $current = $t;
                    while ($current && $current->parent) {
                        $current = get_term($current->parent, 'sc_coupon_category');
                        if (is_wp_error($current)) {
                            $current = null;
                            break;
                        }
                    }
                    if ($current) {
                        $parent_tops[] = $current->name;
                    } else {
                        // If it was already top-level
                        $parent_tops[] = $t->name;
                    }
                }
            }
            // De-duplicate parent names
            $parent_tops = array_values(array_unique($parent_tops));

            $rows[] = [
                'id'                 => $coupon_id,
                'code'               => $code,
                'discount_type'      => $discount_type,
                'amount'             => $amount,
                'usage_count'        => $usage_count,
                'usage_limit'        => $usage_limit,
                'expiry_date'        => $expiry_date,
                'categories'         => $categories,
                'parent_categories'  => $parent_tops,   // ✅ NEW
            ];
        }

        $total    = (int) $query->found_posts;
        $response = rest_ensure_response($rows);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', (string) ceil($total / $per_page));
        return $response;
    }
}
