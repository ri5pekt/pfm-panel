<?php
// class-report-warehouse-export.php

defined('ABSPATH') || exit;

class PFMP_Report_Warehouse_Export {
    public function generate($data) {
        $offset     = intval($data['offset'] ?? 0);
        $limit      = intval($data['chunk_size'] ?? 10);
        $date_from  = $data['date_from'] ?? null;
        $date_to    = $data['date_to'] ?? null;
        $warehouse  = isset($data['warehouse']) ? sanitize_text_field($data['warehouse']) : null;

        if (!$warehouse) {
            return new WP_Error('missing_warehouse', 'Please provide a warehouse.', ['status' => 400]);
        }

        $args = [
            'type'   => 'shop_order',
            'limit'  => -1,
            'return' => 'ids',
            'status' => ['wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded'],
            'meta_query' => [
                [
                    'key'   => 'warehouse_to_export',
                    'value' => $warehouse,
                    'compare' => '=',
                ],
            ],
        ];

        // Date filters
        if ($date_from || $date_to) {
            $from_timestamp = null;
            $to_timestamp = null;

            // Define the offset timezone you're matching (e.g., GMT+4)
            $external_tz = new DateTimeZone('Etc/GMT+4');

            if ($date_from) {
                $from_dt = new DateTime($date_from . ' 00:00:00', $external_tz);
                $from_dt->setTimezone(new DateTimeZone('UTC')); // Convert to UTC for Woo query
                $from_timestamp = $from_dt->getTimestamp();
            }

            if ($date_to) {
                $to_dt = new DateTime($date_to . ' 23:59:59', $external_tz);
                $to_dt->setTimezone(new DateTimeZone('UTC')); // Convert to UTC for Woo query
                $to_timestamp = $to_dt->getTimestamp();
            }

            if ($from_timestamp && $to_timestamp) {
                $args['date_created'] = "{$from_timestamp}...{$to_timestamp}";
            } elseif ($from_timestamp) {
                $args['date_created'] = ">={$from_timestamp}";
            } elseif ($to_timestamp) {
                $args['date_created'] = "<={$to_timestamp}";
            }
        }

        // Get all order IDs filtered by warehouse
        $all_order_ids = wc_get_orders($args);
        $total = count($all_order_ids);
        $chunk = array_slice($all_order_ids, $offset, $limit);

        $rows = [];

        if ($offset === 0) {
            $rows[] = self::csv_row([
                'Order ID',
                'Order Date',
                'Order Status',
                'Gross sales - USD',
                'Gross Sales - Original curr',
                'Sales Tax - USD',
                'Sales Tax - Original curr',
                'Shipping Country',
                'Shipping State',
                'Currency',
            ]);
        }

        $rates = self::get_cur_rates();

        foreach ($chunk as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            $currency = $order->get_currency();
            $rate = self::get_rate($currency, $rates);

            // Calculate gross sales (total without tax)
            $gross_sales_original = wc_format_decimal($order->get_total() - $order->get_total_tax(), 2);
            $gross_sales_usd = $gross_sales_original;
            if ($rate > 0 && $currency !== 'USD') {
                $gross_sales_usd = wc_format_decimal($gross_sales_original / $rate, 2);
            }

            // Calculate sales tax
            $sales_tax_original = wc_format_decimal($order->get_total_tax(), 2);
            $sales_tax_usd = $sales_tax_original;
            if ($rate > 0 && $currency !== 'USD') {
                $sales_tax_usd = wc_format_decimal($sales_tax_original / $rate, 2);
            }

            $shipping = $order->get_address('shipping');
            $shipping_country = $shipping['country'] ?? '';
            $shipping_state = $shipping['state'] ?? '';

            // Get order date
            $order_date = '';
            $created = $order->get_date_created();
            if ($created) {
                $order_date = $created->setTimezone(new DateTimeZone('Etc/GMT+4'))->format('Y-m-d H:i:s');
            }

            // Get order status (remove 'wc-' prefix if present)
            $order_status = $order->get_status();
            $order_status = str_replace('wc-', '', $order_status);
            $order_status = ucfirst($order_status);

            $rows[] = self::csv_row([
                $order->get_id(),
                $order_date,
                $order_status,
                $gross_sales_usd,
                $gross_sales_original,
                $sales_tax_usd,
                $sales_tax_original,
                $shipping_country,
                $shipping_state,
                $currency,
            ]);
        }

        return rest_ensure_response([
            'success'         => true,
            'processed_count' => count($chunk),
            'remaining'       => max(0, $total - $offset - count($chunk)),
            'data'            => $rows,
        ]);
    }

    private static function csv_row($fields) {
        return implode(',', array_map([self::class, 'csv_escape'], $fields));
    }

    private static function csv_escape($value) {
        $escaped = str_replace('"', '""', (string)$value);
        return '"' . $escaped . '"';
    }

    private static function get_cur_rates() {
        $last_updated = get_option('export_stats_rates_last_updated');
        $today        = date('Y-m-d');

        if ($last_updated === $today) {
            $rates = json_decode(get_option('export_stats_currency_rates'));
        } else {
            $response = wp_remote_get('https://v6.exchangerate-api.com/v6/871e5e2ef51033185690c90e/latest/USD');
            if (is_wp_error($response)) return null;

            $rates = json_decode(wp_remote_retrieve_body($response));
            update_option('export_stats_currency_rates', json_encode($rates));
            update_option('export_stats_rates_last_updated', $today);
        }

        return $rates;
    }

    private static function get_rate($currencyCode, $currencyRates) {
        if (!$currencyRates || !isset($currencyRates->conversion_rates)) {
            return null;
        }
        return $currencyRates->conversion_rates->$currencyCode ?? null;
    }
}
