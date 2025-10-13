<?php
// reports/class-report-orders-to-priority.php
defined('ABSPATH') || exit;

class PFMP_Report_Orders_To_Priority {

    public function generate($data) {
        $offset    = intval($data['offset'] ?? 0);
        $limit     = intval($data['chunk_size'] ?? 10);
        $order_ids = $data['order_ids'] ?? [];
        $date_from = $data['date_from'] ?? null;
        $date_to   = $data['date_to'] ?? null;

        $all_order_ids = $this->get_order_ids($order_ids, $date_from, $date_to);
        $total_orders  = count($all_order_ids);
        $chunk         = $this->get_orders_data_chunk($all_order_ids, $offset, $limit);

        return rest_ensure_response([
            'success'          => true,
            'total_orders'     => $total_orders,
            'processed_count'  => count($chunk),
            'remaining'        => max(0, $total_orders - $offset - count($chunk)),
            'data'             => $chunk, // array of tab-delimited lines per order
        ]);
    }

    /** @return int[] */
    private function get_order_ids($order_ids, $date_from, $date_to) {
        if (!empty($order_ids) && is_array($order_ids)) {
            return array_map('absint', $order_ids);
        }

        $args = [
            'type'   => 'shop_order',
            'limit'  => -1,
            'return' => 'ids',
            'status' => ['wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded'],
        ];

        if ($date_from) {
            try {
                $from_dt = DateTime::createFromFormat('Y-m-d', $date_from);
                $from_dt->setTime(0, 0, 0);
                $from_dt->modify('+4 hours'); // external system offset
                $args['date_after'] = $from_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) { /* ignore */
            }
        }

        if ($date_to) {
            try {
                $to_dt = DateTime::createFromFormat('Y-m-d', $date_to);
                $to_dt->setTime(23, 59, 59);
                $to_dt->modify('+4 hours'); // external system offset
                $args['date_before'] = $to_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) { /* ignore */
            }
        }

        return wc_get_orders($args);
    }

    /** @return string[] */
    private function get_orders_data_chunk($order_ids, $offset = 0, $limit = 10) {
        $chunk      = array_slice($order_ids, $offset, $limit);
        $output     = [];
        global $wpdb;

        // collect product IDs in this batch to prefetch SKUs
        $product_ids = [];
        foreach ($chunk as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            foreach ($order->get_items() as $item) {
                $pid = $item->get_variation_id() ?: $item->get_product_id();
                if ($pid) $product_ids[] = $pid;
            }
        }

        $sku_map = [];
        $variation_parents = [];

        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT p.ID, p.post_parent, pm.meta_value AS sku
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm
                   ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                 WHERE p.ID IN ($placeholders)",
                $product_ids
            );
            $results = $wpdb->get_results($query);

            foreach ($results as $r) {
                $sku_map[$r->ID] = $r->sku ?: ($sku_map[$r->post_parent] ?? '');
                if ($r->post_parent) {
                    $variation_parents[$r->ID] = $r->post_parent;
                }
            }

            if (!empty($variation_parents)) {
                $parent_ids  = array_unique(array_values($variation_parents));
                $placeholders = implode(',', array_fill(0, count($parent_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT p.ID, pm.meta_value AS sku
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm
                       ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                     WHERE p.ID IN ($placeholders)",
                    $parent_ids
                );
                $parent_results = $wpdb->get_results($query);

                foreach ($parent_results as $parent) {
                    foreach ($variation_parents as $vid => $pid) {
                        if ($pid == $parent->ID && empty($sku_map[$vid])) {
                            $sku_map[$vid] = $parent->sku;
                        }
                    }
                }
            }
        }

        foreach ($chunk as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            $data = $order->get_data();
            $name = trim($data['billing']['first_name'] . ' ' . $data['billing']['last_name']);
            $order_date = date('d/m/Y', strtotime($data['date_created'] . ' -4 hours'));

            // currency → (customer_user, warehouse)
            $cust_map = [
                "USD" => ["77", "SbNJ"],
                "EUR" => ["78", "SbCA"],
                "AUD" => ["79", "SbCA"],
                "CAD" => ["292", "SbCA"],
                "GBP" => ["291", "SbCA"],
                "ILS" => ["76", "Gre"],
                "BRL" => ["100001", "SbCA"],
                "MXN" => ["100002", "SbCA"],
                "KRW" => ["100003", "SbCA"],
                "JPX" => ["100004", "SbCA"],
                "JPY" => ["100005", "SbCA"],
            ];

            $cust_user = $cust_map[$data['currency']][0] ?? "77";
            $warehouse = $cust_map[$data['currency']][1] ?? "SbCA";

            $lines = [];
            // 1: header-ish line
            $lines[] = $this->safe_implode_tab(['1', $cust_user, $name, $order_date, $data['number'], $data['payment_method'], $warehouse]);
            // 2: section separator?
            $lines[] = '2';
            // 3: contact/address
            $lines[] = $this->safe_implode_tab(['3', $data['billing']['phone'], $data['billing']['email'], $name, $data['billing']['address_1'], $data['billing']['city'], $data['billing']['postcode']]);

            // products
            foreach ($order->get_items() as $item) {
                $item_data = $item->get_data();
                $pid = $item_data['variation_id'] ?: $item_data['product_id'];
                $sku = $sku_map[$pid] ?? $pid;
                $lines[] = $this->safe_implode_tab(['5', $sku, $item_data['quantity'], 0, 0, $item_data['subtotal']]);
            }

            // discounts
            if ($data['discount_total'] > 0) {
                $lines[] = $this->safe_implode_tab(['5', '000', '-1', '', '', -1 * $data['discount_total']]);
            }

            // tax
            if ($data['total_tax'] > 0) {
                $lines[] = $this->safe_implode_tab(['5', '998', '1', '', '', $data['total_tax']]);
            }

            // fees
            foreach ($order->get_fees() as $fee) {
                $f = $fee->get_data();
                $code = (strcasecmp(trim($f['name']), 'Facebook Discount') === 0) ? '2987537' : '100';
                $lines[] = $this->safe_implode_tab(['5', $code, '1', '', '', $f['total']]);
            }

            // shipping
            if ($data['shipping_total'] > 0) {
                $lines[] = $this->safe_implode_tab(['5', '999', '1', '', '', $data['shipping_total']]);
            }

            $output[] = implode("\n", $lines);
        }

        return $output;
    }

    private function safe_implode_tab($fields) {
        $fields = array_map(fn($v) => trim((string)$v), $fields);
        while (!empty($fields) && $fields[count($fields) - 1] === '') {
            array_pop($fields);
        }
        return implode("\t", $fields);
    }
}
