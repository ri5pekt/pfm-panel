<?php
// reports/class-report-export-to-narvar.php
defined('ABSPATH') || exit;

class PFMP_Report_Export_To_Narvar {

    /**
     * Params (same pattern as others):
     * - offset (int), chunk_size (int), order_ids (int[]), date_from (Y-m-d), date_to (Y-m-d)
     * Returns CSV lines: "Order ID","Narvar Status","Message"
     */
    public function generate($data) {
        $offset    = intval($data['offset'] ?? 0);
        $limit     = intval($data['chunk_size'] ?? 10);
        $order_ids = $data['order_ids'] ?? [];
        $date_from = $data['date_from'] ?? null;
        $date_to   = $data['date_to'] ?? null;

        $all_ids = $this->resolve_order_ids($order_ids, $date_from, $date_to);
        $total   = count($all_ids);
        $chunk   = array_slice($all_ids, $offset, $limit);

        $rows = [];
        if ($offset === 0) {
            $rows[] = $this->csv_row(['Order ID', 'Narvar Status', 'Message']);
        }

        foreach ($chunk as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $rows[] = $this->csv_row([$order_id, 'SKIPPED', 'Order not found']);
                continue;
            }

            // Real call using the adapted sender below
            $result = $this->send_order_to_narvar($order);

            // $result shape: ['status' => 'SUCCESS|FAILED|SKIPPED|PENDING', 'message' => '...']
            $rows[] = $this->csv_row([
                $order->get_id(),
                $result['status']  ?? 'PENDING',
                $result['message'] ?? '',
            ]);
        }

        return rest_ensure_response([
            'success'         => true,
            'processed_count' => count($chunk),
            'remaining'       => max(0, $total - $offset - count($chunk)),
            'data'            => $rows,
        ]);
    }

    /** Resolve order IDs via explicit list or date window. */
    private function resolve_order_ids($order_ids, $date_from, $date_to) {
        if (!empty($order_ids) && is_array($order_ids)) {
            return array_map('absint', $order_ids);
        }

        $args = [
            'type'    => 'shop_order',
            'limit'   => -1,
            'return'  => 'ids',
            'status'  => ['wc-processing', 'wc-completed', 'wc-on-hold'],
            'orderby' => 'date',
            'order'   => 'DESC',
        ];

        if ($date_from) {
            try {
                $from = new DateTime($date_from . ' 00:00:00', new DateTimeZone('UTC'));
                $args['date_after'] = $from->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
            }
        }
        if ($date_to) {
            try {
                $to = new DateTime($date_to . ' 23:59:59', new DateTimeZone('UTC'));
                $args['date_before'] = $to->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
            }
        }

        return wc_get_orders($args);
    }

    /**
     * Adapted from your snippet. Returns ['status' => ..., 'message' => ...].
     * Notes:
     * - Reads Basic auth from option 'pfmp_narvar_basic_auth' as "username:password".
     * - Falls back to constant PFM_NARVAR_BASIC_AUTH if defined.
     * - Logs with error_log if PFMP_Utils::log is unavailable.
     */
    private function send_order_to_narvar($order_input) {
        $order = $order_input instanceof WC_Order ? $order_input : wc_get_order($order_input);
        if (!$order) {
            $this->log_message("❌ Invalid order input: " . print_r($order_input, true));
            return ['status' => 'FAILED', 'message' => 'Invalid order'];
        }

        $order_id = $order->get_id();

        try {
            $order_number = $order->get_order_number();
            $same_day     = gmdate('Y-m-d');

            $email = $order->get_billing_email() ?: 'denis@particleformen.com'; // fallback
            $phone = $order->get_billing_phone();
            $customer_id = $order->get_customer_id();

            // Shipment tracking (WooCommerce Shipment Tracking plugin meta)
            $tracking_items = $order->get_meta('_wc_shipment_tracking_items', true);
            $tracking = is_array($tracking_items) ? ($tracking_items[0] ?? null) : null;

            if ($tracking) {
                $carrier = !empty($tracking['tracking_provider'])
                    ? $tracking['tracking_provider']
                    : ($tracking['custom_tracking_provider'] ?? 'Other');

                if ($carrier === 'Amazon Shipping')  $carrier = 'amazon';
                if (stripos($carrier, 'DHL KLB') !== false)  $carrier = 'dhlglobal';

                $tracking_number = $tracking['tracking_number'] ?? '';
                $ship_date = !empty($tracking['date_shipped'])
                    ? date('c', $tracking['date_shipped'])
                    : date('c');
            } else {
                // No tracking → skip per your logic
                $this->log_message("Order #{$order_id} skipped: No tracking data.");
                return ['status' => 'SKIPPED', 'message' => 'No tracking data'];
            }

            // Build order_items (SKU, name, qty, unit price, etc.)
            $order_items = array_values(array_map(function ($item) {
                $product = $item->get_product();
                $sku     = $product ? $product->get_sku() : '';
                $image   = ($product && has_post_thumbnail($product->get_id()))
                    ? wp_get_attachment_image_url($product->get_image_id(), 'large')
                    : '';

                // Optional ACF subtitle
                $desc = '';
                if ($product && function_exists('get_field')) {
                    $acf = get_field('product_settings', $product->get_id());
                    if (is_array($acf)) {
                        $desc = $acf['product_sub_title'] ?? '';
                    }
                }

                return [
                    'item_id'            => null,
                    'sku'                => $sku,
                    'name'               => $item->get_name(),
                    'description'        => $desc,
                    'quantity'           => (int) $item->get_quantity(),
                    'unit_price'         => (float) $item->get_total() / max(1, (int)$item->get_quantity()),
                    'categories'         => [],
                    'item_image'         => $image,
                    'item_url'           => $product ? get_permalink($product->get_id()) : '',
                    'is_final_sale'      => false,
                    'fulfillment_status' => 'SHIPPED',
                    'is_gift'            => false,
                    'attributes'         => [
                        'color' => ($product && $product->get_attribute('pa_color')) ? $product->get_attribute('pa_color') : '',
                    ],
                    'events'             => null,
                ];
            }, $order->get_items()));

            $payload = [
                'order_info' => [
                    'order_number'    => $order_number,
                    'order_date'      => $same_day . 'T00:00:00Z',
                    'checkout_locale' => 'en_US',
                    'currency_code'   => $order->get_currency() ?: 'USD',
                    'customer'        => [
                        'first_name'  => $order->get_billing_first_name() ?: 'N/A',
                        'last_name'   => $order->get_billing_last_name()  ?: 'N/A',
                        'phone'       => $phone,
                        'email'       => $email,
                        'address'     => [
                            'street_1' => $order->get_billing_address_1(),
                            'street_2' => $order->get_billing_address_2(),
                            'city'     => $order->get_billing_city(),
                            'state'    => $order->get_billing_state(),
                            'zip'      => $order->get_billing_postcode(),
                            'country'  => $order->get_billing_country(),
                        ],
                        'customer_id' => $customer_id,
                    ],
                    'order_items' => $order_items,
                    'shipments'   => [[
                        'ship_source' => 'DC',
                        'items_info'  => array_values(array_map(function ($item) {
                            $product = $item->get_product();
                            return [
                                'sku'      => $product ? $product->get_sku() : '',
                                'quantity' => (int) $item->get_quantity(),
                            ];
                        }, $order->get_items())),
                        'shipped_to' => [
                            'first_name' => $order->get_shipping_first_name() ?: ($order->get_billing_first_name() ?: 'N/A'),
                            'last_name'  => $order->get_shipping_last_name()  ?: ($order->get_billing_last_name()  ?: 'N/A'),
                            'phone'      => $phone,
                            'email'      => $email,
                            'address'    => [
                                'street_1' => $order->get_shipping_address_1(),
                                'street_2' => $order->get_shipping_address_2(),
                                'city'     => $order->get_shipping_city(),
                                'state'    => $order->get_shipping_state(),
                                'zip'      => $order->get_shipping_postcode(),
                                'country'  => $order->get_shipping_country(),
                            ],
                        ],
                        'ship_date'      => $ship_date,
                        'carrier'        => $carrier ?: 'Other',
                        'tracking_number' => $tracking_number ?: 'UNKNOWN',
                    ]],
                ],
            ];

            $this->log_message("📦 Sending Narvar Payload for Order {$order_number}:");
            $this->log_message($payload);


            $basic_auth = '7325d69ef03a4dfc9a50e9a61884bda0:9f4ed68118b8424bbd6de243ab119268';
            if (!$basic_auth) {
                $this->log_message("❌ Narvar auth not configured (option 'pfmp_narvar_basic_auth').");
                return ['status' => 'FAILED', 'message' => 'Narvar auth not configured'];
            }

            $response = wp_remote_post('https://ws.narvar.com/api/v1/orders/', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($basic_auth),
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($payload),
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                $this->log_message("❌ WP_Error during Narvar POST for order {$order_number}: " . $response->get_error_message());
                return ['status' => 'FAILED', 'message' => $response->get_error_message()];
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $this->log_message("✅ Narvar Response for Order {$order_number} — HTTP {$code}:");
            $this->log_message($body);

            // Parse body if JSON
            $json = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $status  = strtoupper($json['status'] ?? '');
                $message = '';
                if (!empty($json['messages']) && is_array($json['messages'])) {
                    $parts = array_map(function ($m) {
                        return $m['message'] ?? '';
                    }, $json['messages']);
                    $message = implode(' | ', array_filter($parts));
                }

                // Treat 200/201 as success unless JSON says otherwise
                if (in_array($code, [200, 201], true) && $status === 'SUCCESS') {
                    return ['status' => 'SUCCESS', 'message' => $message ?: 'Order information saved'];
                }
                // Non-success JSON or status code → failed
                return ['status' => ($status ?: 'FAILED'), 'message' => $message ?: "HTTP {$code}"];
            }

            // Not JSON — rely on HTTP code
            if (in_array($code, [200, 201], true)) {
                return ['status' => 'SUCCESS', 'message' => 'OK'];
            }
            return ['status' => 'FAILED', 'message' => "HTTP {$code}"];
        } catch (\Throwable $e) {
            $this->log_message("🔥 Exception in send_order_to_narvar for order {$order_id}: " . $e->getMessage());
            return ['status' => 'FAILED', 'message' => $e->getMessage()];
        }
    }

    /** CSV helpers */
    private function csv_row(array $fields) {
        return implode(',', array_map([$this, 'csv_escape'], $fields));
    }
    private function csv_escape($value) {
        $escaped = str_replace('"', '""', (string)$value);
        return '"' . $escaped . '"';
    }

    /** Minimal logger shim */
    private function log_message($msg) {
    }
}
