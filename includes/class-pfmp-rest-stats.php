<?php
// class-pfmp-rest-stats.php
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

    private function get_day_bounds_utc(?string $date_from, ?string $date_to): array {
        // defaults
        $date_from = $date_from ?: date('Y-m-d');
        $date_to   = $date_to   ?: $date_from;

        $siteTz = wp_timezone(); // WP’s site timezone (matches Woo/Metorik)
        $utcTz  = new DateTimeZone('UTC');

        $startLocal = (new DateTimeImmutable($date_from . ' 00:00:00', $siteTz))->modify('-6 hours');
        $endLocal   = (new DateTimeImmutable($date_to   . ' 23:59:59', $siteTz))->modify('-6 hours');

        $startUtc = $startLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $endUtc   = $endLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');

        // Debug log so you can verify against Metorik
        //PFMP_Utils::log("Bounds local {$siteTz->getName()}: {$startLocal->format('Y-m-d H:i:s')} → {$endLocal->format('Y-m-d H:i:s')}");
        //PFMP_Utils::log("Bounds UTC: {$startUtc} → {$endUtc}");

        return [$startUtc, $endUtc];
    }



    public function get_orders_timeseries(WP_REST_Request $request) {
        global $wpdb;
        $table   = 'yom_woocommerce_order_api_log';
        $dateCol = 'created_at'; // or 'created_at'

        // Inputs (nullable). We still need them for labels/grouping.
        $date_from = $request['date_from'] ?? null;
        $date_to   = $request['date_to']   ?? null;

        // 1) Use the unified helper for DB bounds (in UTC)
        [$fromUtc, $toUtc] = $this->get_day_bounds_utc($date_from, $date_to);

        // 2) Build local-window objects for grouping/labels and SQL “localization”
        $siteTz    = wp_timezone();
        $utcTz     = new DateTimeZone('UTC');

        // Respect defaults exactly like the helper does
        $df = $date_from ?: date('Y-m-d');
        $dt = $date_to   ?: $df;

        $startLocal = new DateTimeImmutable($df . ' 00:00:00', $siteTz);
        $endLocal   = new DateTimeImmutable($dt . ' 23:59:59', $siteTz);

        // Fixed offset (from start) to shift UTC → local inside SQL for bucketing
        $offsetSeconds = $startLocal->getOffset();
        $localExpr     = "FROM_UNIXTIME(UNIX_TIMESTAMP($dateCol) + %d)";

        // Range math (LOCAL)
        $from_ts_local = $startLocal->getTimestamp();
        $to_ts_local   = $endLocal->getTimestamp();
        $range_days    = (int) ceil(($to_ts_local - $from_ts_local) / 86400);

        $results  = [];
        $grouping = '';
        $labels   = [];
        $counts   = [];

        if ($range_days <= 1) {
            // Hourly
            $grouping = 'hour';
            $counts = array_fill(0, 24, 0);
            for ($h = 0; $h < 24; $h++) $labels[] = sprintf('%02d:00', $h);

            $sql = "
            SELECT HOUR($localExpr) AS bucket, COUNT(*) AS cnt
            FROM {$table}
            WHERE $dateCol BETWEEN %s AND %s
            GROUP BY bucket
        ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $offsetSeconds, $fromUtc, $toUtc));
            foreach ($rows as $row) {
                $idx = (int) $row->bucket;
                if ($idx >= 0 && $idx <= 23) $counts[$idx] = (int) $row->cnt;
            }
            foreach ($labels as $i => $label) {
                $results[] = ['label' => $label, 'count' => $counts[$i]];
            }
        } elseif ($range_days <= 14) {
            // Daily
            $grouping = 'day';
            $period = new DatePeriod($startLocal, new DateInterval('P1D'), $endLocal->modify('+1 day'));
            foreach ($period as $dtIt) {
                $label = $dtIt->format('Y-m-d');
                $labels[] = $label;
                $counts[$label] = 0;
            }

            $sql = "
            SELECT DATE($localExpr) AS bucket, COUNT(*) AS cnt
            FROM {$table}
            WHERE $dateCol BETWEEN %s AND %s
            GROUP BY bucket
        ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $offsetSeconds, $fromUtc, $toUtc));
            foreach ($rows as $row) {
                $b = $row->bucket;
                if (isset($counts[$b])) $counts[$b] = (int) $row->cnt;
            }
            foreach ($labels as $label) {
                $results[] = ['label' => $label, 'count' => $counts[$label]];
            }
        } elseif ($range_days <= 90) {
            $grouping = '3day';
            $sql = "
        SELECT FLOOR(DATEDIFF(DATE($localExpr), %s)/3) AS bucket_group,
               MIN(DATE($localExpr)) AS label_date,
               COUNT(*) AS cnt
        FROM {$table}
        WHERE $dateCol BETWEEN %s AND %s
        GROUP BY bucket_group
        ORDER BY label_date ASC
    ";
            // NOTE: $localExpr appears twice -> pass $offsetSeconds twice
            $rows = $wpdb->get_results($wpdb->prepare(
                $sql,
                // placeholders in order: (for first $localExpr) %d, then %s (baseline date),
                // then (for second $localExpr) %d, then %s, %s for bounds
                $offsetSeconds,
                $startLocal->format('Y-m-d'),
                $offsetSeconds,
                $fromUtc,
                $toUtc
            ));
            foreach ($rows as $row) {
                $results[] = ['label' => $row->label_date, 'count' => (int) $row->cnt];
            }

            // --- Weekly bucketing (range_days <= 365) ---
        } elseif ($range_days <= 365) {
            $grouping = 'week';
            $sql = "
        SELECT YEAR($localExpr) AS yr,
               WEEK($localExpr, 1) AS wk,
               COUNT(*) AS cnt,
               MIN(DATE($localExpr)) AS label_date
        FROM {$table}
        WHERE $dateCol BETWEEN %s AND %s
        GROUP BY yr, wk
        ORDER BY label_date ASC
    ";
            // $localExpr appears 3 times -> pass $offsetSeconds 3 times
            $rows = $wpdb->get_results($wpdb->prepare(
                $sql,
                $offsetSeconds,  // YEAR($localExpr)
                $offsetSeconds,  // WEEK($localExpr, 1)
                $offsetSeconds,  // MIN(DATE($localExpr))
                $fromUtc,
                $toUtc
            ));
            foreach ($rows as $row) {
                $results[] = ['label' => 'Week of ' . $row->label_date, 'count' => (int) $row->cnt];
            }
        } elseif ($range_days <= 1200) {
            // Monthly
            $grouping = 'month';
            $sql = "
            SELECT DATE_FORMAT($localExpr, '%Y-%m') AS bucket, COUNT(*) AS cnt
            FROM {$table}
            WHERE $dateCol BETWEEN %s AND %s
            GROUP BY bucket
            ORDER BY bucket ASC
        ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $offsetSeconds, $fromUtc, $toUtc));
            foreach ($rows as $row) {
                $results[] = ['label' => $row->bucket, 'count' => (int) $row->cnt];
            }
        } else {
            // Yearly
            $grouping = 'year';
            $sql = "
            SELECT YEAR($localExpr) AS bucket, COUNT(*) AS cnt
            FROM {$table}
            WHERE $dateCol BETWEEN %s AND %s
            GROUP BY bucket
            ORDER BY bucket ASC
        ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $offsetSeconds, $fromUtc, $toUtc));
            foreach ($rows as $row) {
                $results[] = ['label' => $row->bucket, 'count' => (int) $row->cnt];
            }
        }

        return rest_ensure_response([
            'orders_time_series' => $results,
            'grouping'           => $grouping,
            'from'               => $df,
            'to'                 => $dt,
            'bounds' => [
                'local' => [
                    'from' => $startLocal->format('Y-m-d H:i:s') . ' ' . $siteTz->getName(),
                    'to'   => $endLocal->format('Y-m-d H:i:s')   . ' ' . $siteTz->getName(),
                ],
                'utc'   => ['from' => $fromUtc, 'to' => $toUtc],
            ],
        ]);
    }




    public function get_orders_stats(WP_REST_Request $request) {
        //PFMP_Utils::log("Fetching order stats with request: " . print_r($request->get_params(), true));

        global $wpdb;
        $table = 'yom_woocommerce_order_api_log';

        $where  = "WHERE 1=1";
        $params = [];

        $date_from = $request['date_from'] ?? null;
        $date_to   = $request['date_to']   ?? null;

        // If either bound is provided, filter by the helper's UTC window.
        if ($date_from || $date_to) {
            [$fromUtc, $toUtc] = $this->get_day_bounds_utc($date_from, $date_to);
            $where .= " AND created_at BETWEEN %s AND %s";
            $params[] = $fromUtc;
            $params[] = $toUtc;
        }

        // Status from the column
        $statuses = ['completed', 'processing', 'on-hold', 'refunded'];
        $where_status   = " AND order_status IN (" . implode(",", array_fill(0, count($statuses), "%s")) . ")";
        $params_status  = $statuses;

        $base_currency = 'USD';
        $rates = $this->get_cur_rates();

        $limit  = 1000;
        $offset = 0;

        $gross_sales = 0.0;
        $total_taxes = 0.0;
        $order_count = 0;
        $items_sold  = 0;
        $new_customers = 0;
        $returning_customers = 0;

        $ppu_orders_count   = 0;
        $ppu_products_count = 0;
        $ppu_products_value = 0.0;

        $new_subs_count   = 0;
        $new_subs_value   = 0.0;
        $renew_subs_count = 0;
        $renew_subs_value = 0.0;

        $refund_amount = 0.0;
        $refund_count  = 0;

        $order_ids_in_report = [];

        while (true) {
            $sql = "SELECT order_id, order_status, created_at, api_response
                FROM {$table}
                {$where} {$where_status}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d";
            $paged_params = array_merge($params, $params_status, [$limit, $offset]);
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$paged_params));

            if (empty($rows)) break;

            foreach ($rows as $row) {
                $order_ids_in_report[] = $row->order_id;

                $payload = json_decode($row->api_response, true);
                if (!$payload) continue;
                $data = isset($payload[0]) && is_array($payload[0]) ? $payload[0] : $payload;

                $order_currency = $data['currency'] ?? $base_currency;
                $rate = $this->get_rate($order_currency, $rates) ?: 1;

                $total = isset($data['total']) ? (float) $data['total'] : 0.0;
                $tax   = isset($data['total_tax']) ? (float) $data['total_tax'] : 0.0;

                $gross_sales += $total / $rate;
                $total_taxes += $tax   / $rate;
                $order_count += 1;

                // PPU / upsell
                $ppu_count = (int) $this->get_meta_value($data['meta_data'] ?? [], 'ppu_products_count');
                $ppu_value = (float)$this->get_meta_value($data['meta_data'] ?? [], 'ppu_products_value');
                if ($ppu_count > 0) {
                    $ppu_orders_count   += 1;
                    $ppu_products_count += $ppu_count;
                    $ppu_products_value += $ppu_value / $rate;
                }

                // Subscriptions
                $subscription_parent  = $this->get_meta_value($data['meta_data'] ?? [], '_subscription_parent');
                $subscription_renewal = $this->get_meta_value($data['meta_data'] ?? [], '_subscription_renewal');
                if (!empty($subscription_parent)) {
                    $new_subs_count++;
                    $new_subs_value   += $total / $rate;
                }
                if (!empty($subscription_renewal)) {
                    $renew_subs_count++;
                    $renew_subs_value += $total / $rate;
                }

                // Items sold
                if (!empty($data['line_items']) && is_array($data['line_items'])) {
                    foreach ($data['line_items'] as $item) {
                        $items_sold += (int) ($item['quantity'] ?? 0);
                    }
                }

                // New / returning
                $nor = $this->get_meta_value($data['meta_data'] ?? [], 'new_or_returning');
                if ($nor === 'new') $new_customers++;
                elseif ($nor === 'returning') $returning_customers++;

                // Refunds (positive magnitude, regardless of sign in payload)
                $refunded_local = 0.0;

                if (isset($data['total_refunded']) && $data['total_refunded'] !== '') {
                    // Some APIs provide a positive number here; others give negative. Normalize.
                    $refunded_local = abs((float)$data['total_refunded']);
                } elseif (!empty($data['refunds']) && is_array($data['refunds'])) {
                    foreach ($data['refunds'] as $r) {
                        // Woo often uses 'total' with a negative string (e.g., "-174.41"); fallback to 'amount'
                        $raw = isset($r['total']) ? $r['total'] : (isset($r['amount']) ? $r['amount'] : 0);
                        $refunded_local += abs((float)$raw);
                    }
                }

                if ($refunded_local > 0) {
                    $refund_amount += $refunded_local / $rate; // accumulate in USD
                    // If you want to count refund *events*, use count($data['refunds']); otherwise keep per-order binary:
                    $refund_count += !empty($data['refunds']) && is_array($data['refunds']) ? count($data['refunds']) : 1;
                }
            }

            if (count($rows) < $limit) break;
            $offset += $limit;
            unset($rows);
        }

        $net_sales = $gross_sales + $refund_amount - $total_taxes;

        //PFMP_Utils::log("Orders in stats report 5: " . implode(',', $order_ids_in_report));
        return rest_ensure_response([
            'gross_sales'          => round($gross_sales, 2),
            'net_sales'            => round($net_sales, 2),
            'total_taxes'          => round($total_taxes, 2),
            'order_count'          => $order_count,
            'items_sold'           => $items_sold,
            'refunded'             => round($refund_amount, 2),
            'refund_count'         => $refund_count,
            'currency'             => $base_currency,
            'new_customers'        => $new_customers,
            'returning_customers'  => $returning_customers,
            'ppu_orders_count'     => $ppu_orders_count,
            'ppu_products_count'   => $ppu_products_count,
            'ppu_products_value'   => round($ppu_products_value, 2),
            'new_subs_count'       => $new_subs_count,
            'new_subs_value'       => round($new_subs_value, 2),
            'renew_subs_count'     => $renew_subs_count,
            'renew_subs_value'     => round($renew_subs_value, 2),
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
