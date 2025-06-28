<?php

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . '/class-pfmp-utils.php';

class PFMP_REST_Stats {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }
    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/stats/orders', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_orders_stats'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        // New endpoint for orders count time series
        register_rest_route('pfm-panel/v1', '/stats/orders/timeseries', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_orders_timeseries'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);
    }


    public function get_orders_timeseries(WP_REST_Request $request) {
        global $wpdb;
        $table = 'yom_pfm_orders_for_stats';

        $date_from = $request['date_from'] ?? date('Y-m-d');
        $date_to = $request['date_to'] ?? $date_from;

        $from_ts = strtotime($date_from . ' 00:00:00');
        $to_ts = strtotime($date_to . ' 23:59:59');

        $results = [];
        $grouping = '';
        $sql = '';
        $labels = [];
        $counts = [];

        $range_days = ceil(($to_ts - $from_ts) / 86400);

        if ($range_days <= 1) {
            // 🕒 Hourly
            $grouping = 'hour';
            $counts = array_fill(0, 24, 0);
            for ($h = 0; $h < 24; $h++) $labels[] = sprintf('%02d:00', $h);

            $sql = "SELECT HOUR(order_date) as bucket, COUNT(*) as cnt
                    FROM {$table}
                    WHERE type = 'order' AND order_date BETWEEN %s AND %s
                    GROUP BY bucket";

            $rows = $wpdb->get_results($wpdb->prepare($sql, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts)));
            foreach ($rows as $row) {
                $counts[(int)$row->bucket] = (int)$row->cnt;
            }

            foreach ($labels as $i => $label) {
                $results[] = ['label' => $label, 'count' => $counts[$i]];
            }
        } elseif ($range_days <= 14) {
            // 📆 Daily
            $grouping = 'day';
            $period = new DatePeriod(new DateTime($date_from), new DateInterval('P1D'), (new DateTime($date_to))->modify('+1 day'));

            foreach ($period as $dt) {
                $label = $dt->format('Y-m-d');
                $labels[] = $label;
                $counts[$label] = 0;
            }

            $sql = "SELECT DATE(order_date) as bucket, COUNT(*) as cnt
                    FROM {$table}
                    WHERE type = 'order' AND order_date BETWEEN %s AND %s
                    GROUP BY bucket";

            $rows = $wpdb->get_results($wpdb->prepare($sql, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts)));
            foreach ($rows as $row) {
                $counts[$row->bucket] = (int)$row->cnt;
            }

            foreach ($labels as $label) {
                $results[] = ['label' => $label, 'count' => $counts[$label]];
            }
        } elseif ($range_days <= 90) {
            // 📊 Every 3 days
            $grouping = '3day';
            $sql = "SELECT FLOOR(DATEDIFF(order_date, %s)/3) as bucket_group, MIN(DATE(order_date)) as label_date, COUNT(*) as cnt
                    FROM {$table}
                    WHERE type = 'order' AND order_date BETWEEN %s AND %s
                    GROUP BY bucket_group
                    ORDER BY label_date ASC";

            $rows = $wpdb->get_results($wpdb->prepare($sql, $date_from, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts)));
            foreach ($rows as $row) {
                $results[] = ['label' => $row->label_date, 'count' => (int)$row->cnt];
            }
        } elseif ($range_days <= 365) {
            // 📅 Weekly
            $grouping = 'week';
            $sql = "SELECT YEAR(order_date) as yr, WEEK(order_date, 1) as wk, COUNT(*) as cnt,
                           MIN(DATE(order_date)) as label_date
                    FROM {$table}
                    WHERE type = 'order' AND order_date BETWEEN %s AND %s
                    GROUP BY yr, wk
                    ORDER BY label_date ASC";

            $rows = $wpdb->get_results($wpdb->prepare($sql, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts)));
            foreach ($rows as $row) {
                $results[] = ['label' => 'Week of ' . $row->label_date, 'count' => (int)$row->cnt];
            }
        } elseif ($range_days <= 1200) {
            // 📆 Monthly
            $grouping = 'month';
            $sql = "SELECT DATE_FORMAT(order_date, '%Y-%m') as bucket, COUNT(*) as cnt
                    FROM {$table}
                    WHERE type = 'order' AND order_date BETWEEN %s AND %s
                    GROUP BY bucket
                    ORDER BY bucket ASC";

            $rows = $wpdb->get_results($wpdb->prepare($sql, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts)));
            foreach ($rows as $row) {
                $results[] = ['label' => $row->bucket, 'count' => (int)$row->cnt];
            }
        } else {
            // 📅 Yearly
            $grouping = 'year';
            $sql = "SELECT YEAR(order_date) as bucket, COUNT(*) as cnt
                    FROM {$table}
                    WHERE type = 'order' AND order_date BETWEEN %s AND %s
                    GROUP BY bucket
                    ORDER BY bucket ASC";

            $rows = $wpdb->get_results($wpdb->prepare($sql, date('Y-m-d H:i:s', $from_ts), date('Y-m-d H:i:s', $to_ts)));
            foreach ($rows as $row) {
                $results[] = ['label' => $row->bucket, 'count' => (int)$row->cnt];
            }
        }

        return rest_ensure_response([
            'orders_time_series' => $results,
            'grouping' => $grouping,
            'from' => $date_from,
            'to' => $date_to
        ]);
    }



    public function get_orders_stats(WP_REST_Request $request) {
        PFMP_Utils::log("Fetching order stats with request: " . print_r($request->get_params(), true));

        global $wpdb;
        $table = 'yom_pfm_orders_for_stats';

        // Date filters and time offset handling
        $where = "WHERE 1=1";
        $params = [];

        $date_from = $request['date_from'] ?? null;
        $date_to = $request['date_to'] ?? null;

        if ($date_from || $date_to) {
            if ($date_from) {
                $from_string = date('Y-m-d H:i:s', strtotime('+5 hours', strtotime($date_from . ' 00:00:00')));
                $where .= " AND order_date >= %s";
                $params[] = $from_string;
            }
            if ($date_to) {
                $to_string = date('Y-m-d H:i:s', strtotime('+1 day +5 hours', strtotime($date_to . ' 00:00:00')));
                $where .= " AND order_date < %s";
                $params[] = $to_string;
            }
        }

        // Status filter
        $statuses = ['completed', 'processing', 'on-hold', 'refunded'];
        $where_status = " AND order_status IN (" . implode(",", array_fill(0, count($statuses), "%s")) . ")";
        $params_status = $statuses;

        $base_currency = 'USD';
        $rates = $this->get_cur_rates();

        $limit = 1000;

        // ---- ORDERS ----
        $order_where = $where . " AND type = 'order' " . $where_status;
        $order_params = array_merge($params, $params_status);

        $offset = 0;
        $gross_sales = 0.0;
        $total_taxes = 0.0;
        $order_count = 0;
        $items_sold = 0;
        $new_customers = 0;
        $returning_customers = 0;
        $ppu_orders_count = 0;
        $ppu_products_count = 0;
        $ppu_products_value = 0.0;

        $new_subs_count = 0;
        $new_subs_value = 0.0;
        $renew_subs_count = 0;
        $renew_subs_value = 0.0;

        while (true) {
            $sql = "SELECT order_id, order_date, cached_data FROM {$table} {$order_where} ORDER BY order_date DESC LIMIT %d OFFSET %d";
            $paged_params = array_merge($order_params, [$limit, $offset]);
            $paged_sql = $wpdb->prepare($sql, ...$paged_params);
            $rows = $wpdb->get_results($paged_sql);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $data = json_decode($row->cached_data, true);
                $total = isset($data['total']) ? (float)$data['total'] : 0;
                $tax = isset($data['taxes']) ? (float)$data['taxes'] : 0;
                $order_currency = $data['currency'] ?? $base_currency;

                $exchange_rate = $this->get_rate($order_currency, $rates) ?: 1;
                $total = $total / $exchange_rate;
                $tax = $tax / $exchange_rate;

                $gross_sales += $total;
                $total_taxes += $tax;
                $order_count += 1;


                $ppu_count = (int) $this->get_meta_value($data['meta'] ?? [], 'ppu_products_count');
                $ppu_value = (float) $this->get_meta_value($data['meta'] ?? [], 'ppu_products_value');
                $exchange_rate = $this->get_rate($order_currency, $rates) ?: 1;

                $subscription_parent = $this->get_meta_value($data['meta'] ?? [], '_subscription_parent');
                $subscription_renewal = $this->get_meta_value($data['meta'] ?? [], '_subscription_renewal');
                $exchange_rate = $this->get_rate($order_currency, $rates) ?: 1;

                // New Subscriptions
                if (!empty($subscription_parent)) {
                    $new_subs_count += 1;
                    $new_subs_value += $total / $exchange_rate;
                }
                // Renewals
                if (!empty($subscription_renewal)) {
                    $renew_subs_count += 1;
                    $renew_subs_value += $total / $exchange_rate;
                }

                // Only count if upsell products were sold
                if ($ppu_count > 0) {
                    $ppu_orders_count += 1;
                    $ppu_products_count += $ppu_count;
                    $ppu_products_value += $ppu_value / $exchange_rate;
                }

                // --- Count items sold ---
                if (!empty($data['items']) && is_array($data['items'])) {
                    foreach ($data['items'] as $item) {
                        $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                        $items_sold += $qty;
                    }
                }

                // --- New/Returning customer counts ---
                $nor = $this->get_meta_value($data['meta'] ?? [], 'new_or_returning');
                if ($nor === 'new') {
                    $new_customers += 1;
                } elseif ($nor === 'returning') {
                    $returning_customers += 1;
                }
            }

            if (count($rows) < $limit) {
                break;
            }
            $offset += $limit;
            unset($rows);
        }

        // ---- REFUNDS ---- (unchanged)
        $refund_where = $where . " AND type = 'refund' ";
        $refund_params = $params;

        $refund_offset = 0;
        $refund_amount = 0.0;
        $refund_count = 0;

        while (true) {
            $sql = "SELECT order_id, order_date, cached_data FROM {$table} {$refund_where} ORDER BY order_date DESC LIMIT %d OFFSET %d";
            $paged_params = array_merge($refund_params, [$limit, $refund_offset]);
            $paged_sql = $wpdb->prepare($sql, ...$paged_params);
            $rows = $wpdb->get_results($paged_sql);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $data = json_decode($row->cached_data, true);
                $refunded = isset($data['total_refunded']) ? (float)$data['total_refunded'] : 0;
                $parent_currency = $data['currency'] ?? $base_currency;

                $exchange_rate = $this->get_rate($parent_currency, $rates) ?: 1;
                $refunded = $refunded / $exchange_rate;

                $refund_amount += $refunded;
                $refund_count += 1;
            }

            if (count($rows) < $limit) {
                break;
            }
            $refund_offset += $limit;
            unset($rows);
        }

        $net_sales = $gross_sales + $refund_amount - $total_taxes;

        return rest_ensure_response([
            'gross_sales'   => round($gross_sales, 2),
            'net_sales'     => round($net_sales, 2),
            'total_taxes'   => round($total_taxes, 2),
            'order_count'   => $order_count,
            'items_sold'    => $items_sold,
            'refunded'      => round($refund_amount, 2),
            'refund_count'  => $refund_count,
            'currency'      => $base_currency,
            'new_customers'       => $new_customers,
            'returning_customers' => $returning_customers,
            'ppu_orders_count'    => $ppu_orders_count,
            'ppu_products_count'  => $ppu_products_count,
            'ppu_products_value'  => round($ppu_products_value, 2),
            'new_subs_count'    => $new_subs_count,
            'new_subs_value'    => round($new_subs_value, 2),
            'renew_subs_count'  => $renew_subs_count,
            'renew_subs_value'  => round($renew_subs_value, 2),
        ]);
    }


    private function get_meta_value($meta, $wanted_key) {
        if (!is_array($meta)) return null;
        foreach ($meta as $entry) {
            if (!empty($entry['key']) && $entry['key'] === $wanted_key) {
                return $entry['value'];
            }
        }
        return null;
    }


    // --- Helper methods (place these in your class) ---

    private function get_cur_rates() {
        $start_time_get_cur_rates = microtime(true);
        $last_updated = get_option('export_stats_rates_last_updated');
        $current_date = date('Y-m-d');

        if ($last_updated === $current_date) {
            $json_rates = get_option('export_stats_currency_rates');
            $rates = json_decode($json_rates);
        } else {
            $curl = curl_init();
            $url = "https://v6.exchangerate-api.com/v6/871e5e2ef51033185690c90e/latest/USD";

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                echo "cURL Error #:" . $err;
                return;
            } else {
                $rates = json_decode($response);
            }

            $json_rates = json_encode($rates);
            update_option('export_stats_currency_rates', $json_rates);
            update_option('export_stats_rates_last_updated', $current_date);
        }
        return $rates;
    }

    private function get_rate($currencyCode, $currencyRates) {
        if (property_exists($currencyRates->conversion_rates, $currencyCode)) {
            return $currencyRates->conversion_rates->$currencyCode;
        } else {
            return null;
        }
    }
}
