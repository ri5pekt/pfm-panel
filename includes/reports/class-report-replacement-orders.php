<?php
// reports/class-report-replacement-orders.php

defined('ABSPATH') || exit;

require_once dirname(__DIR__) . '/class-pfmp-query-utils.php';

class PFMP_Report_Replacement_Orders {
    public function generate($data) {
        $offset = max(0, intval($data['offset'] ?? 0));
        $limit  = min(500, max(1, intval($data['chunk_size'] ?? 100)));

        $date_from = isset($data['date_from']) ? $this->sanitize_date($data['date_from']) : null;
        $date_to   = isset($data['date_to']) ? $this->sanitize_date($data['date_to'], true) : null;
        $warehouse = isset($data['warehouse']) ? sanitize_text_field($data['warehouse']) : null;

        $product_ids = PFMP_Query_Utils::parse_product_ids($data['product_ids'] ?? ($data['products'] ?? []));
        $reasons     = $this->parse_reasons($data['replacement_reasons'] ?? ($data['replacement_reason'] ?? []));
        $created_by  = $this->parse_id_list($data['created_by'] ?? []);

        $filters = [
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'warehouse' => $warehouse,
            'product_ids' => $product_ids,
            'replacement_reasons' => $reasons,
            'created_by' => $created_by,
        ];

        $query_result = $this->get_replacement_ids($filters, $offset, $limit);
        $ids   = $query_result['ids'];
        $total = $query_result['total'];

        $rows = [];
        if ($offset === 0) {
            $rows[] = $this->csv_row([
                'Replacement ID',
                'Created At',
                'Status',
                'Warehouse',
                'Warehouse Export Status',
                'Replacement Reason',
                'Total Items',
                'Items Summary',
                'Customer ID',
                'Customer Name',
                'Customer Email',
                'Created By',
                'Notes Count',
            ]);
        }

        foreach ($ids as $replacement_id) {
            $order = new WRO_Replacement_Order((int) $replacement_id);
            if (!$order->id) {
                continue;
            }

            $created_at = $order->created_at ? date_i18n('Y-m-d H:i:s', strtotime($order->created_at)) : '';
            $status     = $order->status ?? '';

            $warehouse_meta = $order->meta['warehouse_to_export'] ?? '';
            $warehouse_value = $warehouse_meta !== '' ? $warehouse_meta : 'shipstation';
            $export_status  = $order->meta['warehouse_export_status'] ?? '';
            $replacement_reason = $order->meta['replacement_reason'] ?? '';

            $items_summary = $this->summarize_items($order->items ?? []);
            $total_items   = $items_summary['total_quantity'];
            $items_string  = $items_summary['summary'];

            $customer_id    = (int) ($order->customer_id ?? 0);
            $customer_name  = '';
            $customer_email = '';
            if ($customer_id) {
                $customer_user = get_userdata($customer_id);
                if ($customer_user) {
                    $first = get_user_meta($customer_id, 'first_name', true);
                    $last  = get_user_meta($customer_id, 'last_name', true);
                    $customer_name = trim(($first ?: '') . ' ' . ($last ?: ''));
                    $customer_email = $customer_user->user_email ?: '';
                }
            }

            $creator_name = '';
            if (!empty($order->created_by)) {
                $creator = get_userdata((int) $order->created_by);
                if ($creator) {
                    $creator_name = trim(($creator->first_name ?? '') . ' ' . ($creator->last_name ?? ''));
                    if ($creator_name === '') {
                        $creator_name = $creator->display_name ?: $creator->user_login;
                    }
                }
            }

            $notes_count = is_array($order->notes ?? null) ? count($order->notes) : 0;

            $rows[] = $this->csv_row([
                $order->id,
                $created_at,
                $status,
                $warehouse_value,
                $export_status,
                $replacement_reason,
                $total_items,
                $items_string,
                $customer_id ?: '',
                $customer_name,
                $customer_email,
                $creator_name,
                $notes_count,
            ]);
        }

        return rest_ensure_response([
            'success'         => true,
            'processed_count' => count($ids),
            'remaining'       => max(0, $total - $offset - count($ids)),
            'data'            => $rows,
        ]);
    }

    private function sanitize_date($date, $end_of_day = false) {
        if (!$date) {
            return null;
        }
        try {
            $dt = new DateTime($date);
            if ($end_of_day) {
                $dt->setTime(23, 59, 59)->modify('+4 hours');
            } else {
                $dt->setTime(0, 0, 0)->modify('+4 hours');
            }
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function parse_reasons($input): array {
        if (empty($input)) {
            return [];
        }

        $raw = [];
        if (is_array($input)) {
            $raw = $input;
        } elseif (is_string($input)) {
            $raw = preg_split('/[,\n\r]+/', $input);
        }

        $clean = [];
        foreach ($raw as $value) {
            $sanitized = sanitize_text_field($value);
            if ($sanitized !== '') {
                $clean[] = $sanitized;
            }
        }

        return array_values(array_unique($clean));
    }

    private function parse_id_list($input): array {
        if (empty($input)) {
            return [];
        }

        if (is_array($input)) {
            return array_values(array_unique(array_filter(array_map('absint', $input))));
        }

        if (is_string($input)) {
            $parts = preg_split('/[,\s]+/', $input);
            return array_values(array_unique(array_filter(array_map('absint', $parts))));
        }

        return [];
    }

    private function get_replacement_ids(array $filters, int $offset, int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'replacement_orders';
        $joins = '';
        $where = [];
        $params = [];
        $group_by = '';
        $having_clause = '';
        $having_params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'r.created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'r.created_at <= %s';
            $params[] = $filters['date_to'];
        }

        if (isset($filters['warehouse']) && $filters['warehouse'] !== null && $filters['warehouse'] !== '') {
            $warehouse = strtolower($filters['warehouse']);
            if ($warehouse === 'shipstation') {
                $where[] = "(JSON_EXTRACT(r.meta_json, '$.warehouse_to_export') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.warehouse_to_export')) = '')";
            } else {
                $where[] = "JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.warehouse_to_export')) = %s";
                $params[] = sanitize_text_field($filters['warehouse']);
            }
        }

        if (!empty($filters['replacement_reasons'])) {
            $placeholders = implode(',', array_fill(0, count($filters['replacement_reasons']), '%s'));
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(r.meta_json, '$.replacement_reason')) IN ($placeholders)";
            foreach ($filters['replacement_reasons'] as $reason) {
                $params[] = $reason;
            }
        }

        if (!empty($filters['product_ids'])) {
            $product_ids = array_values(array_unique(array_filter(array_map('absint', $filters['product_ids']))));
            if (!empty($product_ids)) {
                $match_expr = "COALESCE(NULLIF(items.variation_id, 0), items.product_id)";
                $joins .= "
                    JOIN JSON_TABLE(
                        r.items_json,
                        '$[*]' COLUMNS (
                            product_id INT PATH '$.product_id',
                            variation_id INT PATH '$.variation_id'
                        )
                    ) AS items ON 1=1
                ";
                $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                $where[] = "{$match_expr} IN ($placeholders)";
                foreach ($product_ids as $pid) {
                    $params[] = $pid;
                }
                $group_by = 'GROUP BY r.id';

                if (count($product_ids) > 1) {
                    $having_parts = [];
                    foreach ($product_ids as $pid) {
                        $having_parts[] = "SUM(CASE WHEN {$match_expr} = %d THEN 1 ELSE 0 END) > 0";
                        $having_params[] = $pid;
                    }
                    $having_clause = 'HAVING ' . implode(' AND ', $having_parts);
                }
            }
        }

        if (!empty($filters['created_by'])) {
            $creator_ids = array_values(array_unique(array_filter(array_map('absint', $filters['created_by']))));
            if (!empty($creator_ids)) {
                $placeholders = implode(',', array_fill(0, count($creator_ids), '%d'));
                $where[] = "r.created_by IN ($placeholders)";
                foreach ($creator_ids as $cid) {
                    $params[] = $cid;
                }
            }
        }

        $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        $from_sql  = "FROM {$table} AS r {$joins} {$where_sql}";

        $group_sql = $group_by;
        if ($having_clause !== '') {
            $group_sql .= ' ' . $having_clause;
        }

        $count_sql = "SELECT COUNT(*) FROM (SELECT r.id {$from_sql} {$group_sql}) AS counted";
        $count_params = array_merge($params, $having_params);
        $count_query = !empty($count_params) ? $wpdb->prepare($count_sql, $count_params) : $count_sql;
        $total = (int) $wpdb->get_var($count_query);

        $page_sql = "
            SELECT r.id
            {$from_sql}
            {$group_sql}
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT %d OFFSET %d
        ";

        $page_params = array_merge($params, $having_params, [$limit, $offset]);
        $ids = $wpdb->get_col($wpdb->prepare($page_sql, $page_params));

        return [
            'total' => $total,
            'ids'   => array_map('absint', $ids ?: []),
        ];
    }

    private function summarize_items($items): array {
        $summary_parts = [];
        $total_quantity = 0;
        $product_cache = [];

        if (is_iterable($items)) {
            foreach ($items as $item) {
                $name = '';
                $sku  = '';
                $qty  = 0;
                $product_id = 0;
                $variation_id = 0;

                if (is_array($item)) {
                    $name         = $item['name'] ?? '';
                    $sku          = $item['sku'] ?? '';
                    $qty          = intval($item['quantity'] ?? 0);
                    $product_id   = intval($item['product_id'] ?? 0);
                    $variation_id = intval($item['variation_id'] ?? 0);
                } elseif (is_object($item)) {
                    if (method_exists($item, 'get_name')) {
                        $name = $item->get_name();
                    } elseif (isset($item->name)) {
                        $name = $item->name;
                    }

                    if (method_exists($item, 'get_sku')) {
                        $sku = $item->get_sku();
                    } elseif (isset($item->sku)) {
                        $sku = $item->sku;
                    }

                    if (method_exists($item, 'get_quantity')) {
                        $qty = intval($item->get_quantity());
                    } elseif (isset($item->quantity)) {
                        $qty = intval($item->quantity);
                    }

                    if (method_exists($item, 'get_product_id')) {
                        $product_id = intval($item->get_product_id());
                    } elseif (isset($item->product_id)) {
                        $product_id = intval($item->product_id);
                    }

                    if (method_exists($item, 'get_variation_id')) {
                        $variation_id = intval($item->get_variation_id());
                    } elseif (isset($item->variation_id)) {
                        $variation_id = intval($item->variation_id);
                    }
                }

                $qty = max(0, $qty);
                $total_quantity += $qty;

                $lookup_id = $variation_id ?: $product_id;
                if ($lookup_id > 0 && ($name === '' || $sku === '')) {
                    if (!isset($product_cache[$lookup_id])) {
                        $product_cache[$lookup_id] = wc_get_product($lookup_id);
                    }

                    $product = $product_cache[$lookup_id];
                    if ($product instanceof WC_Product) {
                        if ($name === '') {
                            $name = $product->get_name();
                        }
                        if ($sku === '') {
                            $sku = $product->get_sku();
                        }
                    } elseif ($product_id > 0 && $lookup_id !== $product_id) {
                        if (!isset($product_cache[$product_id])) {
                            $product_cache[$product_id] = wc_get_product($product_id);
                        }
                        $parent_product = $product_cache[$product_id];
                        if ($parent_product instanceof WC_Product) {
                            if ($name === '') {
                                $name = $parent_product->get_name();
                            }
                            if ($sku === '') {
                                $sku = $parent_product->get_sku();
                            }
                        }
                    }
                }

                if ($name === '') {
                    $name = 'Product';
                }

                $label = $name;
                if ($sku) {
                    $label .= " (SKU: {$sku})";
                }
                $label .= " x{$qty}";
                $summary_parts[] = $label;
            }
        }

        return [
            'summary' => implode('; ', $summary_parts),
            'total_quantity' => $total_quantity,
        ];
    }

    private function csv_row($fields) {
        return implode(',', array_map([$this, 'escape_csv_value'], $fields));
    }

    private function escape_csv_value($value) {
        $escaped = str_replace('"', '""', (string) $value);
        return '"' . $escaped . '"';
    }
}
