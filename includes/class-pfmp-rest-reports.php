<?php
// class-pfmp-rest-reports.php

defined('ABSPATH') || exit;

class PFMP_REST_Reports {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('pfm-panel/v1', '/reports/run', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'run_report'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/reports/upload', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload_report'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/reports/history', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_report_history'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);
    }

    public function run_report(WP_REST_Request $request) {
        $data = $request->get_json_params();
        $type = sanitize_text_field($data['report_type'] ?? '');

        switch ($type) {
            case 'orders-to-priority':
                return $this->generate_orders_to_priority_report($data);
            case 'refunds':
                require_once __DIR__ . '/reports/class-report-refunds.php';
                $report = new PFMP_Report_Refunds();
                return $report->generate($data);
            case 'taxes-verification':
                require_once __DIR__ . '/reports/class-report-taxes-verification.php';
                $report = new PFMP_Report_Taxes_Verification();
                return $report->generate($data);
            default:
                return new WP_Error('unsupported_report', 'Unsupported report type.', ['status' => 400]);
        }
    }

    private function csv_escape($value) {
        $escaped = str_replace('"', '""', (string)$value);
        return '"' . $escaped . '"';
    }

    private function generate_orders_to_priority_report($data) {
        $offset    = intval($data['offset'] ?? 0);
        $limit     = intval($data['chunk_size'] ?? 10);
        $order_ids = $data['order_ids'] ?? [];
        $date_from = $data['date_from'] ?? null;
        $date_to   = $data['date_to'] ?? null;

        $all_order_ids = $this->get_order_ids($order_ids, $date_from, $date_to);
        $total_orders  = count($all_order_ids);
        $chunk         = $this->get_orders_data_chunk($all_order_ids, $offset, $limit);

        return rest_ensure_response([
            'success'         => true,
            'total_orders'    => $total_orders,
            'processed_count' => count($chunk),
            'remaining'       => max(0, $total_orders - $offset - count($chunk)),
            'data'            => $chunk,
        ]);
    }

    private function get_order_ids($order_ids, $date_from, $date_to) {
        if (!empty($order_ids) && is_array($order_ids)) {
            return array_map('absint', $order_ids);
        }

        $args = [
            'type'     => 'shop_order',
            'limit'    => -1,
            'return'   => 'ids',
            'status'       => array('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded'),
        ];

        if ($date_from) {
            try {
                $from_dt = DateTime::createFromFormat('Y-m-d', $date_from);
                $from_dt->setTime(0, 0, 0);
                $from_dt->modify('+4 hours');
                $args['date_after'] = $from_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Handle error if needed
            }
        }

        if ($date_to) {
            try {
                $to_dt = DateTime::createFromFormat('Y-m-d', $date_to);
                $to_dt->setTime(23, 59, 59);
                $to_dt->modify('+4 hours');
                $args['date_before'] = $to_dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Handle error if needed
            }
        }

        $order_ids = wc_get_orders($args);

        return $order_ids;
    }

    private function get_orders_data_chunk($order_ids, $offset = 0, $limit = 10) {
        $chunk = array_slice($order_ids, $offset, $limit);
        $output = [];

        global $wpdb;
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
            $query = $wpdb->prepare("
            SELECT p.ID, p.post_parent, pm.meta_value as sku
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.ID IN ($placeholders)
        ", $product_ids);
            $results = $wpdb->get_results($query);
            foreach ($results as $r) {
                $sku_map[$r->ID] = $r->sku ?: ($sku_map[$r->post_parent] ?? '');
                if ($r->post_parent) {
                    $variation_parents[$r->ID] = $r->post_parent;
                }
            }

            if (!empty($variation_parents)) {
                $parent_ids = array_unique(array_values($variation_parents));
                $placeholders = implode(',', array_fill(0, count($parent_ids), '%d'));
                $query = $wpdb->prepare("
        SELECT p.ID, pm.meta_value as sku
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE p.ID IN ($placeholders)
    ", $parent_ids);
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
            $lines[] = $this->safe_implode_tab(['1', $cust_user, $name, $order_date, $data['number'], $data['payment_method'], $warehouse]);
            $lines[] = '2';
            $lines[] = $this->safe_implode_tab(['3', $data['billing']['phone'], $data['billing']['email'], $name, $data['billing']['address_1'], $data['billing']['city'], $data['billing']['postcode']]);

            foreach ($order->get_items() as $item) {
                $item_data = $item->get_data();
                $pid = $item_data['variation_id'] ?: $item_data['product_id'];
                $sku = $sku_map[$pid] ?? $pid;
                $lines[] = $this->safe_implode_tab(['5', $sku, $item_data['quantity'], 0, 0, $item_data['subtotal']]);
            }

            if ($data['discount_total'] > 0) {
                $lines[] = $this->safe_implode_tab(['5', '000', '-1', '', '', -1 * $data['discount_total']]);
            }

            if ($data['total_tax'] > 0) {
                $lines[] = $this->safe_implode_tab(['5', '998', '1', '', '', $data['total_tax']]);
            }

            foreach ($order->get_fees() as $fee) {
                $f = $fee->get_data();
                $code = (strcasecmp(trim($f['name']), 'Facebook Discount') === 0) ? '2987537' : '100';
                $lines[] = $this->safe_implode_tab(['5', $code, '1', '', '', $f['total']]);
            }

            if ($data['shipping_total'] > 0) {
                $lines[] = $this->safe_implode_tab(['5', '999', '1', '', '', $data['shipping_total']]);
            }

            $output[] = implode("\n", $lines);
        }

        return $output;
    }

    private function safe_implode_tab($fields) {
        // Normalize and trim each field
        $fields = array_map(fn($v) => trim((string) $v), $fields);

        // Remove trailing blanks
        while (!empty($fields) && $fields[count($fields) - 1] === '') {
            array_pop($fields);
        }

        return implode("\t", $fields);
    }

    public function upload_report(WP_REST_Request $request) {
        $params  = $request->get_json_params();
        $type    = sanitize_text_field($params['report_type'] ?? '');
        $content = $params['file_content'] ?? '';
        $ext     = sanitize_text_field($params['extension'] ?? 'txt');

        if (!$type || !$content) {
            return new WP_Error('invalid_data', 'Missing report type or content.', ['status' => 400]);
        }

        $first_line = strtok($content, "\n");
        $filename_prefix = 'report';
        $filename_middle = '';

        $raw_payload = $request->get_json_params();
        $order_ids   = $raw_payload['order_ids'] ?? [];
        $date_from   = $raw_payload['date_from'] ?? null;
        $date_to     = $raw_payload['date_to'] ?? null;

        if (!empty($order_ids)) {
            $short_ids = array_slice(array_map('intval', $order_ids), 0, 2);
            $filename_prefix = 'by-ids';
            $filename_middle = implode('_', $short_ids);
        } elseif ($date_from || $date_to) {
            $date_from = $date_from ?: 'start';
            $date_to   = $date_to ?: 'end';
            $filename_prefix = 'by-date';
            $filename_middle = "{$date_from}_to_{$date_to}";
        }

        $wp_uploads  = wp_upload_dir();
        $upload_dir  = trailingslashit($wp_uploads['basedir']) . 'reports/' . sanitize_file_name($type) . '/';
        $upload_url  = trailingslashit($wp_uploads['baseurl']) . 'reports/' . sanitize_file_name($type) . '/';

        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        // Safe filename generation
        $base_name = "{$filename_prefix}_{$filename_middle}";
        $filename  = $base_name . '.' . $ext;
        $counter   = 1;
        while (file_exists($upload_dir . $filename)) {
            $filename = "{$base_name} ({$counter}).{$ext}";
            $counter++;
        }

        $path = $upload_dir . $filename;
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $file_handle = fopen($path, 'w');

        // Add UTF-8 BOM for full Hebrew + accented support in Windows / Excel / Notepad
        fwrite($file_handle, "\xEF\xBB\xBF");

        // Write content
        fwrite($file_handle, $content);
        fclose($file_handle);

        return rest_ensure_response([
            'success'      => true,
            'filename'     => $filename,
            'download_url' => $upload_url . $filename,
        ]);
    }



    public function get_report_history(WP_REST_Request $request) {
        $type = sanitize_text_field($request->get_param('report_type'));
        if (!$type) {
            return new WP_Error('missing_param', 'Missing report_type', ['status' => 400]);
        }

        $wp_uploads = wp_upload_dir();
        $upload_dir = trailingslashit($wp_uploads['basedir']) . 'reports/' . sanitize_file_name($type) . '/';
        $upload_url = trailingslashit($wp_uploads['baseurl']) . 'reports/' . sanitize_file_name($type) . '/';

        if (!file_exists($upload_dir)) {
            return rest_ensure_response([]);
        }

        $extensions = ['txt', 'csv'];
        $files = [];

        foreach ($extensions as $ext) {
            $files = array_merge($files, glob($upload_dir . '*.' . $ext));
        }

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $result = array_map(function ($file) use ($upload_url) {
            return [
                'filename'     => basename($file),
                'download_url' => $upload_url . basename($file),
                'created_at'   => date('c', filemtime($file)),
            ];
        }, $files);

        return rest_ensure_response($result);
    }



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


    function get_rate($currencyCode, $currencyRates) {
        // Check if the currency code exists in the conversion rates
        if (property_exists($currencyRates->conversion_rates, $currencyCode)) {
            // Return the exchange rate for the given currency code
            return $currencyRates->conversion_rates->$currencyCode;
        } else {
            // Return null or some default value if the currency code is not found
            return null;
        }
    }
}
