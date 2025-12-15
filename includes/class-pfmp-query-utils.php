<?php
final class PFMP_Query_Utils {

    /**
     * Parse product IDs from string "7,616257" or array [7, 616257].
     */
    public static function parse_product_ids($raw): array {
        if (is_array($raw)) {
            $ids = array_map('absint', $raw);
        } else {
            preg_match_all('/\d+/', (string) $raw, $m);
            $ids = array_map('intval', $m[0] ?? []);
        }
        $ids = array_values(array_unique(array_filter($ids)));
        return $ids;
    }

    /**
     * Expand product IDs to include all WPML translations of each product.
     * Falls back gracefully if WPML not present.
     */
    public static function expand_product_ids_wpml(array $product_ids): array {
        // No WPML → nothing to do
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return array_values(array_unique(array_map('absint', $product_ids)));
        }

        $all = [];
        foreach ($product_ids as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }

            // Get TRID (translation group id) for this product
            // wpml_element_trid: ($trid, $element_id, $element_type)
            $trid = apply_filters('wpml_element_trid', null, $pid, 'post_product');

            if ($trid) {
                // Get all translations for this TRID
                // wpml_get_element_translations: ($translations, $trid, $element_type)
                $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_product');

                if (is_array($translations)) {
                    foreach ($translations as $lang_code => $t) {
                        // $t can be array or object depending on WPML version
                        if (is_array($t) && !empty($t['element_id'])) {
                            $all[] = (int) $t['element_id'];
                        } elseif (is_object($t) && !empty($t->element_id)) {
                            $all[] = (int) $t->element_id;
                        }
                    }
                } else {
                    // Fallback: at least ensure original exists in its default language
                    $orig_id = apply_filters('wpml_object_id', $pid, 'product', true);
                    if ($orig_id) $all[] = (int) $orig_id;
                }
            } else {
                // Not in a translation group
                $all[] = $pid;
            }
        }

        $expanded = array_values(array_unique(array_filter(array_map('absint', $all))));
        return $expanded ?: array_values(array_unique(array_map('absint', $product_ids)));
    }

    /**
     * HPOS SQL (fast): paginated order IDs for product set.
     * AND: order must contain ALL of the requested products (across languages).
     * OR: order must contain ANY requested product.
     *
     * @return array{ids:int[], total:int}
     */
    /**
     * Paginated order IDs where each group contributes at least one matching line item.
     * @param int[][] $product_groups  e.g., [[A_trans_ids...],[B_trans_ids...]]
     * @return array{ids:int[], total:int}
     */
    public static function get_order_ids_by_product_groups_paginated(array $product_groups, int $page, int $per_page): array {
        global $wpdb;

        // Flatten all ids for WHERE; skip empties
        $groups = array_values(array_filter(array_map(function ($g) {
            $g = array_values(array_unique(array_filter(array_map('absint', (array)$g))));
            return $g ?: [];
        }, $product_groups)));

        if (empty($groups)) return ['ids' => [], 'total' => 0];

        $all_ids = [];
        foreach ($groups as $g) $all_ids = array_merge($all_ids, $g);
        $all_ids = array_values(array_unique($all_ids));

        $page     = max(1, $page);
        $per_page = min(100, max(1, $per_page));
        $offset   = ($page - 1) * $per_page;

        $opl = $wpdb->prefix . 'wc_order_product_lookup';
        $o   = $wpdb->prefix . 'wc_orders';

        // WHERE for the union
        $ph_all = implode(',', array_fill(0, count($all_ids), '%d'));
        $where  = "WHERE opl.product_id IN ($ph_all) AND o.type = 'shop_order'";

        // Build HAVING: each group must hit at least once
        // -> SUM(opl.product_id IN (g1...)) > 0 AND SUM(opl.product_id IN (g2...)) > 0 ...
        $having_parts = [];
        $having_params = [];
        foreach ($groups as $g) {
            $ph_g = implode(',', array_fill(0, count($g), '%d'));
            $having_parts[] = "SUM(opl.product_id IN ($ph_g)) > 0";
            array_push($having_params, ...$g);
        }
        $having_sql = 'HAVING ' . implode(' AND ', $having_parts);

        // COUNT via derived table (so HAVING applies)
        $count_sql = "
        SELECT COUNT(*) FROM (
            SELECT o.id
            FROM {$opl} AS opl
            INNER JOIN {$o}  AS o ON o.id = opl.order_id
            {$where}
            GROUP BY o.id
            {$having_sql}
        ) t
    ";

        // Page IDs: same grouped subquery, then sort by date and LIMIT/OFFSET
        $grouped_sql = "
        SELECT o.id
        FROM {$opl} AS opl
        INNER JOIN {$o}  AS o ON o.id = opl.order_id
        {$where}
        GROUP BY o.id
        {$having_sql}
    ";

        $page_sql = "
        SELECT g.id
        FROM ({$grouped_sql}) AS g
        INNER JOIN {$o} AS o ON o.id = g.id
        ORDER BY o.date_created_gmt DESC, o.id DESC
        LIMIT %d OFFSET %d
    ";

        // Params = all_ids (WHERE) + all group params (HAVING)
        $params_common = array_merge($all_ids, $having_params);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params_common));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $ids   = $wpdb->get_col($wpdb->prepare($page_sql, array_merge($params_common, [$per_page, $offset])));


        return ['ids' => array_map('absint', $ids ?: []), 'total' => $total];
    }


    /**
     * Load orders for given IDs and map to your payload shape.
     */
    public static function load_orders_payload(array $order_ids): array {
        $order_ids = array_values(array_unique(array_filter(array_map('absint', $order_ids))));
        if (empty($order_ids)) return [];

        $t0 = microtime(true);
        $orders = [];
        foreach ($order_ids as $oid) {
            $o = wc_get_order((int)$oid);
            if ($o) $orders[] = $o;
        }
        $t1 = microtime(true);


        $payload = array_map(function (WC_Order $order) {
            $data = $order->get_data();
            $data['meta_data'] = $order->get_meta_data();
            $data['coupon_codes'] = array_map(function ($coupon) {
                return $coupon->get_code();
            }, $order->get_items('coupon'));
            $data['refunded_amount'] = (float) $order->get_total_refunded();
            $data['meta'] = [
                'warehouse_to_export' => $order->get_meta('warehouse_to_export'),
                'warehouse_export_status' => $order->get_meta('warehouse_export_status'),
                'validate_address_status' => $order->get_meta('validate_address_status'),
                '_wc_shipment_tracking_items' => $order->get_meta('_wc_shipment_tracking_items'),
                '_subscription_renewal' => $order->get_meta('_subscription_renewal'),
                '_subscription_parent' => $order->get_meta('_subscription_parent'),
            ];
            return $data;
        }, $orders);

        return $payload;
    }


    public static function expand_product_groups_wpml(array $selected_ids): array {
        // If no WPML, each selected id is its own group
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return array_map(fn($id) => [(int)$id], array_values(array_unique(array_filter($selected_ids, 'absint'))));
        }

        $groups = [];
        foreach ($selected_ids as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;

            $grp = [];

            $trid = apply_filters('wpml_element_trid', null, $pid, 'post_product');
            if ($trid) {
                $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_product');
                if (is_array($translations)) {
                    foreach ($translations as $t) {
                        if (is_array($t) && !empty($t['element_id'])) $grp[] = (int)$t['element_id'];
                        elseif (is_object($t) && !empty($t->element_id)) $grp[] = (int)$t->element_id;
                    }
                }
            }
            if (empty($grp)) $grp[] = $pid; // fallback
            $groups[] = array_values(array_unique(array_filter($grp)));
        }

        // de-duplicate identical groups just in case
        $norm = [];
        foreach ($groups as $g) {
            sort($g);
            $norm[join(',', $g)] = $g;
        }
        return array_values($norm);
    }



    /**
     * Paginated replacement IDs whose items_json contains products.
     * - AND logic when $require_all = true (order must contain ALL selected products)
     * - OR logic when $require_all = false (order contains ANY selected product)
     * Also applies optional filters on status, meta_json keys and date range.
     *
     * @param int[] $product_ids
     * @param int   $page
     * @param int   $per_page
     * @param bool  $require_all
     * @param array $filters ['status','warehouse','export_status','addr_status','date_from','date_to']
     * @return array{ids:int[], total:int}
     */
    public static function get_replacement_ids_by_products_paginated(array $product_ids, int $page, int $per_page, bool $require_all = true, array $filters = []): array {
        global $wpdb;

        $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
        if (empty($product_ids)) {
            return ['ids' => [], 'total' => 0];
        }

        $page     = max(1, $page);
        $per_page = min(100, max(1, $per_page));
        $offset   = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'replacement_orders';
        $pid_ph = implode(',', array_fill(0, count($product_ids), '%d'));

        // Base WHERE (push cheap filters here)
        $where = 'WHERE 1=1';
        $params_where = [];

        // Status
        if (!empty($filters['status'])) {
            $where         .= ' AND r.status = %s';
            $params_where[] = sanitize_key($filters['status']);
        }

        // Date range on created_at (stored as DATETIME)
        $date_from = $filters['date_from'] ?? null;
        $date_to   = $filters['date_to']   ?? null;
        if ($date_from || $date_to) {
            // Use same offsets as your orders endpoint (-2h start, +1d-2h end) if desired
            if ($date_from) {
                $from = date('Y-m-d H:i:s', strtotime('-2 hours', strtotime($date_from . ' 00:00:00')));
                $where         .= ' AND r.created_at >= %s';
                $params_where[] = $from;
            }
            if ($date_to) {
                $to   = date('Y-m-d H:i:s', strtotime('+1 day -2 hours', strtotime($date_to . ' 00:00:00')));
                $where         .= ' AND r.created_at <= %s';
                $params_where[] = $to;
            }
        }

        // Meta filters on meta_json
        // warehouse
        if (array_key_exists('warehouse', $filters) && $filters['warehouse'] !== null) {
            if ($filters['warehouse'] === 'shipstation') {
                // NOT EXISTS or empty
                $where .= " AND (JSON_EXTRACT(r.meta_json, '$.warehouse_to_export') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.warehouse_to_export')) = '')";
            } else {
                $where         .= " AND JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.warehouse_to_export')) = %s";
                $params_where[] = sanitize_text_field($filters['warehouse']);
            }
        }
        // export_status
        if (!empty($filters['export_status'])) {
            $where         .= " AND JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.warehouse_export_status')) = %s";
            $params_where[] = sanitize_text_field($filters['export_status']);
        }
        // addr_status
        if (!empty($filters['addr_status'])) {
            $where         .= " AND JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.validate_address_status')) = %s";
            $params_where[] = sanitize_text_field($filters['addr_status']);
        }

        /**
         * Items join using JSON_TABLE (MySQL 8+). WP Engine uses MySQL 8, so this is available.
         * If you ever need a fallback for older MySQL, we could emulate with JSON_SEARCH + REGEXP, but 8+ is best.
         */
        $items_join = "
            JOIN JSON_TABLE(
                r.items_json,
                '$[*]' COLUMNS (
                    product_id INT PATH '$.product_id',
                    variation_id INT PATH '$.variation_id'
                )
            ) AS it
        ";

        // AND vs OR logic:
        //  - OR:   just "WHERE it.product_id IN (...)"
        //  - AND:  require that DISTINCT matching product_ids count equals requested count
        $base_from_group = "
            FROM {$table} AS r
            {$items_join}
            {$where}
            AND it.product_id IN ({$pid_ph})
            GROUP BY r.id
        ";

        if ($require_all) {
            // AND = order must contain ALL selected product_ids
            $having = "HAVING COUNT(DISTINCT it.product_id) = %d";
        } else {
            $having = ""; // OR needs no HAVING
        }

        // Count via derived table (so HAVING applies)
        $count_sql = "
            SELECT COUNT(*) FROM (
                SELECT r.id
                {$base_from_group}
                " . ($require_all ? $wpdb->prepare($having, count($product_ids)) : "") . "
            ) t
        ";

        // Paged IDs, sorted by created_at DESC then id DESC
        $page_sql = "
            SELECT g.id
            FROM (
                SELECT r.id
                {$base_from_group}
                " . ($require_all ? $wpdb->prepare($having, count($product_ids)) : "") . "
            ) AS g
            JOIN {$table} AS r ON r.id = g.id
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT %d OFFSET %d
        ";

        // Build params: WHERE (status/date/meta) + product_ids (+ again for page) + limit/offset
        $params_common = array_merge($params_where, $product_ids);
        $params_page   = array_merge($params_where, $product_ids, [$per_page, $offset]);

        $t0 = microtime(true);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params_common));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $ids   = $wpdb->get_col($wpdb->prepare($page_sql, $params_page));
        $t1 = microtime(true);

        PFMP_Utils::log(['repl.products.sql' => [
            'require_all' => (int)$require_all,
            'num_products' => count($product_ids),
            'filters'     => [
                'status'        => $filters['status']        ?? null,
                'warehouse'     => $filters['warehouse']     ?? null,
                'export_status' => $filters['export_status'] ?? null,
                'addr_status'   => $filters['addr_status']   ?? null,
                'date_from'     => $filters['date_from']     ?? null,
                'date_to'       => $filters['date_to']       ?? null,
            ],
            'total'       => $total,
            'returned'    => is_array($ids) ? count($ids) : 0,
            'ms'          => round(($t1 - $t0) * 1000),
        ]]);

        return [
            'ids'   => array_map('absint', $ids ?: []),
            'total' => $total,
        ];
    }
}
