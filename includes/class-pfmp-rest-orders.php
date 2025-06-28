<?php
// class-pfmp-rest-orders.php

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . '/class-pfmp-utils.php';

class PFMP_REST_Orders {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }
    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/orders', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_filtered_orders'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'page'          => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page'      => ['default' => 10, 'sanitize_callback' => 'absint'],
                'status'        => ['sanitize_callback' => 'sanitize_text_field'],
                'warehouse'     => ['sanitize_callback' => 'sanitize_text_field'],
                'export_status' => ['sanitize_callback' => 'sanitize_text_field'],
                'addr_status'   => ['sanitize_callback' => 'sanitize_text_field'],
                'date_from'     => ['sanitize_callback' => 'sanitize_text_field'],
                'date_to'       => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);



        register_rest_route('pfm-panel/v1', '/orders/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_single_order'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/orders/(?P<id>\d+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_order'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/orders/by-user/(?P<user_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_orders_by_user_id'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/orders/(?P<id>\d+)/refund', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'refund_order_items'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/orders/(?P<order_id>\d+)/revalidate-address', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_address_revalidation'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);


        register_rest_route('pfm-panel/v1', '/orders/(?P<order_id>\d+)/export-to-warehouse', [
            'methods'  => 'POST',
            'callback' => [$this, 'export_to_warehouse'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/orders/bulk', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_bulk_action'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/orders/(?P<id>\d+)/edit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'edit_order_items'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/products-by-category', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_products_by_category'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/orders/(?P<order_id>\d+)/notes', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_order_notes'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);
    }

    function get_order_notes(WP_REST_Request $request) {
        $order_id = absint($request['order_id']);
        if (!$order_id) {
            return new WP_Error('invalid_order', 'Invalid order ID', ['status' => 400]);
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }

        $notes = wc_get_order_notes([
            'order_id' => $order_id,
            'type'     => 'any',
            'orderby'  => 'date_created',
            'order'    => 'DESC',
        ]);

        $formatted = [];
        foreach ($notes as $note) {
            $formatted[] = [
                'id'           => $note->id,
                'note'         => $note->content,
                'date_created' => $note->date_created ? $note->date_created->date('c') : '',
                'author'       => $note->added_by ?: '',
                'is_customer'  => $note->customer_note ? true : false,
                'type'         => $note->customer_note ? 'customer' : 'internal',
            ];
        }

        return $formatted;
    }

    public function get_products_by_category(WP_REST_Request $request) {
        $categories = [
            'face' => [7, 2213887, 100362, 100370, 616257, 1077388],
            'hair' => [1860895, 570671, 216928, 1068419, 616215],
            'body' => [1646173, 2475444, 1450671, 1495139, 216919],
            'wellness' => [2293541],
            'bundles' => [1813624, 250223, 250209, 1868710, 616306, 1607485, 2769533, 2769100],
        ];
        $labels = [
            'face' => 'Face',
            'hair' => 'Hair',
            'body' => 'Body',
            'wellness' => 'Wellness',
            'bundles' => 'Bundles',
        ];

        $result = [];
        foreach ($categories as $cat_key => $product_ids) {
            $group = [
                'key' => $cat_key,
                'label' => $labels[$cat_key] ?? $cat_key,
                'products' => [],
            ];
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;
                $group['products'][] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => wc_get_price_to_display($product),
                    'image' => wp_get_attachment_url($product->get_image_id()),
                    'sku' => $product->get_sku(),
                ];
            }
            $result[] = $group;
        }
        return rest_ensure_response($result);
    }

    public function edit_order_items(WP_REST_Request $request) {
        $order_id = absint($request['id']);
        $params = $request->get_json_params();

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];

        if (!$order_id || !($order = wc_get_order($order_id))) {
            return new WP_Error('invalid_order', 'Order not found', ['status' => 404]);
        }

        $updated = false;

        // Helper to set tax, one tax rate per item/fee/shipping
        function pfmp_set_single_tax($item, $new_total_tax, $context = '') {
            try {
                $taxes = $item->get_taxes();
                $rate_ids = array_keys($taxes['total']);
                $first_rate_id = $rate_ids ? $rate_ids[0] : null;

                // If no rate in the taxes array, try to find from the order's tax items
                if (!$first_rate_id) {
                    $order_taxes = $item->get_order()->get_items('tax');
                    if (!empty($order_taxes)) {
                        $first_rate_id = (string) reset($order_taxes)->get_rate_id();
                    }
                }

                // If still none, create a fake one and log
                if (!$first_rate_id) {
                    $first_rate_id = 1;
                }

                $taxes['total']    = [$first_rate_id => floatval($new_total_tax)];
                $taxes['subtotal'] = [$first_rate_id => floatval($new_total_tax)];

                $item->set_taxes($taxes);
            } catch (\Throwable $e) {
                PFMP_Utils::log("❌ pfmp_set_single_tax ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }

        // Update items
        if (!empty($params['items']) && is_array($params['items'])) {
            foreach ($params['items'] as $item_data) {
                if (empty($item_data['id'])) continue;
                $item = $order->get_item($item_data['id']);
                if (!$item || $item->is_type('fee') || $item->is_type('shipping')) continue;


                if (isset($item_data['quantity'])) {
                    $item->set_quantity((int)$item_data['quantity']);
                }
                if (isset($item_data['total'])) {
                    $item->set_total(floatval($item_data['total']));
                }
                if (isset($item_data['tax'])) {
                    pfmp_set_single_tax($item, $item_data['tax'], 'line_item');
                }
                $item->save();
                $updated = true;
            }
        }

        // Update fees
        if (!empty($params['fees']) && is_array($params['fees'])) {
            foreach ($params['fees'] as $fee_data) {
                if (empty($fee_data['id'])) continue;
                $item = $order->get_item($fee_data['id']);
                if (!$item || !$item->is_type('fee')) continue;
                if (isset($fee_data['name'])) {
                    $item->set_name(wp_strip_all_tags($fee_data['name']));
                }
                if (isset($fee_data['total'])) {
                    $item->set_total(floatval($fee_data['total']));
                }
                if (isset($fee_data['tax'])) {
                    pfmp_set_single_tax($item, $fee_data['tax'], 'fee');
                }
                $item->save();
                $updated = true;
            }
        }

        // Update shipping
        if (!empty($params['shipping']) && is_array($params['shipping'])) {
            foreach ($params['shipping'] as $ship_data) {
                if (empty($ship_data['id'])) continue;
                $item = $order->get_item($ship_data['id']);
                if (!$item || !$item->is_type('shipping')) continue;

                if (isset($ship_data['method_title'])) {
                    $item->set_name(wp_strip_all_tags($ship_data['method_title']));
                }
                if (isset($ship_data['total'])) {
                    $item->set_total(floatval($ship_data['total']));
                }
                if (isset($ship_data['tax'])) {
                    pfmp_set_single_tax($item, $ship_data['tax'], 'shipping');
                }
                $item->save();
                $updated = true;
            }
        }


        // Sum taxes by rate_id across all items/fees/shipping
        $order_item_tax_totals = [];
        foreach ($order->get_items(array('line_item', 'fee', 'shipping')) as $item) {
            $taxes = $item->get_taxes()['total'] ?? [];
            foreach ($taxes as $rate_id => $amount) {
                if (!isset($order_item_tax_totals[$rate_id])) {
                    $order_item_tax_totals[$rate_id] = 0;
                }
                $order_item_tax_totals[$rate_id] += floatval($amount);
            }
        }


        // Update the order's tax lines (tax items)
        foreach ($order->get_items('tax') as $tax_item_id => $tax_item) {
            $rate_id = $tax_item->get_rate_id();
            $new_tax_total = isset($order_item_tax_totals[$rate_id]) ? $order_item_tax_totals[$rate_id] : 0;
            $tax_item->set_tax_total($new_tax_total);
            $tax_item->set_shipping_tax_total(0); // set if needed
            $tax_item->save();
        }

        // Add new products (line items)
        if (!empty($params['new_items']) && is_array($params['new_items'])) {
            foreach ($params['new_items'] as $item_data) {
                // Required: product_id, quantity, total, tax
                $product_id = isset($item_data['product_id']) ? absint($item_data['product_id']) : 0;
                $quantity   = isset($item_data['quantity']) ? intval($item_data['quantity']) : 1;
                $total      = isset($item_data['total']) ? floatval($item_data['total']) : 0;
                $tax        = isset($item_data['tax']) ? floatval($item_data['tax']) : 0;

                if (!$product_id || $quantity < 1) continue;

                $order_item_id = $order->add_product(
                    wc_get_product($product_id),
                    $quantity,
                    [
                        'subtotal' => $total,
                        'total' => $total,
                    ]
                );

                if ($order_item_id && $tax !== null) {
                    $item = $order->get_item($order_item_id);
                    if ($item) {
                        // Set tax
                        if (!function_exists('pfmp_set_single_tax')) {
                            function pfmp_set_single_tax($item, $new_total_tax, $context = '') {
                                $taxes = $item->get_taxes();
                                $rate_ids = array_keys($taxes['total']);
                                $first_rate_id = $rate_ids ? $rate_ids[0] : null;
                                if (!$first_rate_id) {
                                    $order_taxes = $item->get_order()->get_items('tax');
                                    if (!empty($order_taxes)) {
                                        $first_rate_id = (string) reset($order_taxes)->get_rate_id();
                                    }
                                }
                                if (!$first_rate_id) $first_rate_id = 1;
                                $taxes['total']    = [$first_rate_id => floatval($new_total_tax)];
                                $taxes['subtotal'] = [$first_rate_id => floatval($new_total_tax)];
                                $item->set_taxes($taxes);
                            }
                        }
                        pfmp_set_single_tax($item, $tax, 'line_item');
                        $item->save();
                    }
                }
                $updated = true;
            }
        }



        // ---- REMOVE ITEMS ----
        if (!empty($params['removed_items']) && is_array($params['removed_items'])) {
            foreach ($params['removed_items'] as $item_id) {
                $item = $order->get_item($item_id);
                if ($item && $item->is_type('line_item')) {
                    $order->remove_item($item_id);
                    $updated = true;
                }
            }
        }


        // Add new fees
        if (!empty($params['new_fees']) && is_array($params['new_fees'])) {
            foreach ($params['new_fees'] as $fee_data) {
                $fee_name  = isset($fee_data['name']) ? wp_strip_all_tags($fee_data['name']) : '';
                $fee_total = isset($fee_data['total']) ? floatval($fee_data['total']) : 0;
                $fee_tax   = isset($fee_data['tax']) ? floatval($fee_data['tax']) : 0;

                if (empty($fee_name)) {
                    continue;
                }

                $item = new WC_Order_Item_Fee();
                $item->set_name($fee_name);
                $item->set_total($fee_total);

                // 1. Add the item to the order first to associate the order object
                $order->add_item($item);

                // 2. Now set tax
                if ($fee_tax !== null) {
                    pfmp_set_single_tax($item, $fee_tax, 'fee');
                }

                // 3. Save the fee item
                $item->save();
                $updated = true;
            }
        }


        // Remove fees
        if (!empty($params['removed_fees']) && is_array($params['removed_fees'])) {
            foreach ($params['removed_fees'] as $fee_id) {
                $item = $order->get_item($fee_id);
                if ($item && $item->is_type('fee')) {
                    $order->remove_item($fee_id);
                    $updated = true;
                }
            }
        }

        $auto_tax = !empty($params['auto_tax']);

        if ($updated) {
            $order->add_order_note('Order items were edited by ' . $admin_name);
            if ($auto_tax) {
                if (class_exists('WC_Complyt_Tax') && method_exists('WC_Complyt_Tax', 'init') && method_exists('WC_Complyt_Tax', 'get_instance')) {
                    WC_Complyt_Tax::init();
                    $complyt = WC_Complyt_Tax::get_instance();

                    if ($complyt && method_exists($complyt, 'set_order_id')) {
                        $complyt->set_order_id($order_id);
                    }
                }

                $billing = [
                    'country'  => $order->get_billing_country(),
                    'state'    => $order->get_billing_state(),
                    'postcode' => $order->get_billing_postcode(),
                    'city'     => $order->get_billing_city(),
                ];

                $order->calculate_taxes($billing);
                $order->calculate_totals(false);
                $order->save();
            } else {
                // --- BEGIN: Remove orphaned tax lines and recalc tax totals ---
                $order_item_tax_totals = [];
                foreach ($order->get_items(array('line_item', 'fee', 'shipping')) as $item) {
                    $taxes = $item->get_taxes()['total'] ?? [];
                    foreach ($taxes as $rate_id => $amount) {
                        if (!isset($order_item_tax_totals[$rate_id])) {
                            $order_item_tax_totals[$rate_id] = 0;
                        }
                        $order_item_tax_totals[$rate_id] += floatval($amount);
                    }
                }

                foreach ($order->get_items('tax') as $tax_item_id => $tax_item) {
                    $rate_id = $tax_item->get_rate_id();
                    $new_tax_total = isset($order_item_tax_totals[$rate_id]) ? $order_item_tax_totals[$rate_id] : 0;
                    if ($new_tax_total == 0) {
                        // Remove orphaned tax line
                        $order->remove_item($tax_item_id);
                    } else {
                        $tax_item->set_tax_total($new_tax_total);
                        $tax_item->set_shipping_tax_total(0); // set if needed
                        $tax_item->save();
                    }
                }
                // --- END: Remove orphaned tax lines and recalc tax totals ---

                // Manually set order total to sum of all line items, fees, shipping, and tax
                $total_fees = 0;
                foreach ($order->get_items('fee') as $fee_item) {
                    $total_fees += floatval($fee_item->get_total());
                }
                $total_shipping = 0;
                foreach ($order->get_items('shipping') as $shipping_item) {
                    $total_shipping += floatval($shipping_item->get_total());
                }
                $total_tax = 0;
                foreach ($order->get_items('tax') as $tax_item) {
                    $total_tax += floatval($tax_item->get_tax_total());
                }
                $total_products = 0;
                foreach ($order->get_items('line_item') as $item) {
                    $total_products += floatval($item->get_total());
                }

                $order_total = $total_products + $total_fees + $total_shipping + $total_tax;

                $order->set_total($order_total);
                $order->save();
            }
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Order updated successfully',
        ]);
    }

    public function handle_bulk_action(WP_REST_Request $request) {
        $ids = $request->get_param('ids');
        $action = sanitize_text_field($request->get_param('action'));
        $value = sanitize_text_field($request->get_param('value'));

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];

        if (!is_array($ids) || empty($action)) {
            return new WP_Error('invalid_request', 'Invalid payload.', ['status' => 400]);
        }

        $results = [];
        foreach ($ids as $id) {
            $order = wc_get_order(absint($id));
            if (!$order) continue;

            if ($action === 'change_status') {
                $order->set_status($value);
                $order->add_order_note("Status changed to '$value' by $admin_name via bulk action.");
                $order->save();
                $results[] = $id;
            }

            if ($action === 'export_to_warehouse') {
                $warehouse_slug = sanitize_text_field($value);
                $decide = $warehouse_slug === 'decide_based_on_rules';

                if ($decide) {
                    $warehouse_slug = \WEX\Utils::get_warehouse_for_order($id);
                }

                if ($warehouse_slug) {
                    global $wex_plugin;
                    $wex_plugin->send_order_to_warehouse($warehouse_slug, $id);
                    $order->update_meta_data('warehouse_to_export', $warehouse_slug);
                    $order->update_meta_data('warehouse_export_status', 'exported');
                    $order->add_order_note("Bulk-exported to {$warehouse_slug} by $admin_name.");
                    $order->save();
                }
            }
        }

        return rest_ensure_response([
            'success' => true,
            'updated' => $results,
        ]);
    }

    public function export_to_warehouse(WP_REST_Request $request) {
        $order_id = absint($request->get_param('order_id'));
        $warehouse_slug = sanitize_text_field($request->get_param('warehouse'));

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];

        if ($warehouse_slug == "decide_based_on_rules") $decide = true;
        else $decide = false;

        if ($decide) $warehouse_slug = \WEX\Utils::get_warehouse_for_order($order_id);

        if (!$order_id || !$warehouse_slug) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid order ID or warehouse slug.',
            ], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        global $wex_plugin;
        if (!isset($wex_plugin) || !method_exists($wex_plugin, 'send_order_to_warehouse')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Warehouse export plugin is not available.',
            ], 500);
        }

        $response = $wex_plugin->send_order_to_warehouse($warehouse_slug, $order_id);

        if (!empty($response['status']) && $response['status'] === 'success') {
            $order->update_meta_data('warehouse_to_export', $warehouse_slug);
            $order->update_meta_data('warehouse_export_status', 'exported');


            $note = sprintf(__('Order manually exported to %s by %s.', 'warehouse-export'), $warehouse_slug, $admin_name);
            $order->add_order_note($note);
            $order->save();

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Order was manually exported successfully!',
            ]);
        }

        $error_msg = !empty($response['message']) ? $response['message'] : 'Unknown error.';
        return new WP_REST_Response([
            'success' => false,
            'message' => "Export failed: {$error_msg}",
        ], 400);
    }


    public function handle_address_revalidation(WP_REST_Request $request) {
        // 👀 Start of request

        $order_id = absint($request->get_param('order_id'));
        $params = $request->get_json_params();
        $force = !empty($params['force']);

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];

        if (!$order_id) {

            return new WP_REST_Response(['error' => 'Invalid order ID'], 400);
        }

        if ($force) {

            $order = wc_get_order($order_id);
            if (!$order) {

                return new WP_REST_Response(['error' => 'Order not found'], 404);
            }

            $order->update_meta_data('validate_address_status', 'valid');
            $order->add_order_note(__("Address status manually forced to VALID by $admin_name.", 'warehouse-export'));
            $order->save();


            return new WP_REST_Response(['success' => true, 'message' => 'Address status forced to VALID.']);
        }

        global $wex_plugin;

        if (!isset($wex_plugin)) {

            return new WP_REST_Response(['error' => 'Main_Plugin instance not available.'], 500);
        }


        $result = $wex_plugin->validate_order_address($order_id);



        if ($result->is_valid === 'true') {

            return new WP_REST_Response(['success' => true, 'message' => $result->message]);
        }


        return new WP_REST_Response(['success' => false, 'message' => $result->message]);
    }



    public function refund_order_items(WP_REST_Request $request) {
        try {
            $order_id = absint($request['id']);
            $params = $request->get_json_params();

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('invalid_order', 'Order not found', ['status' => 404]);
            }

            $refund_items    = $params['items'] ?? [];
            $refund_fees     = $params['fees'] ?? [];
            $refund_shipping = $params['shipping'] ?? [];



            // 🔍 Try to get tax_rate_id from tax items
            $tax_rate_id = null;
            $tax_items = $order->get_items('tax');
            if (count($tax_items) === 1) {
                $tax_item = current($tax_items);
                $tax_rate_id = $tax_item->get_rate_id();
            }

            $refund_ids = [];

            // 🔀 Group items by transaction ID
            $items_by_tx = ['default' => []];
            foreach ($refund_items as $item) {
                $tx_id = $item['transaction_id'] ?? 'default';
                $items_by_tx[$tx_id][] = $item;
            }

            foreach ($items_by_tx as $tx_id => $grouped_items) {
                $refund_data = [
                    'order_id'       => $order_id,
                    'refund_reason'  => 'Refund via PFM Panel',
                    'line_items'     => [],
                    'shipping_items' => [],
                    'amount'         => 0,
                    'restock_items'  => false,
                ];

                $total_refund = 0;

                foreach ($grouped_items as $item) {
                    $item_id = absint($item['id']);
                    $qty     = absint($item['quantity']);
                    $total   = floatval($item['total']);
                    $tax     = floatval($item['tax']);

                    if ($total === 0 && $tax === 0) {
                        continue;
                    }

                    $order_item = $order->get_item($item_id);
                    if (!$order_item) {
                        continue;
                    }

                    $line = array_filter([
                        'qty'          => $qty > 0 ? $qty : null,
                        'refund_total' => $total,
                    ]);

                    if ($tax > 0) {
                        if (!$tax_rate_id) {
                            return new WP_Error('tax_rate_error', 'Tax refund requested but no valid tax rate found.', ['status' => 400]);
                        }
                        $line['refund_tax'] = [$tax_rate_id => $tax];
                    }

                    $refund_data['line_items'][$item_id] = $line;
                    $total_refund += $total + $tax;
                }

                if ($tx_id === 'default') {
                    // Add fees and shipping only once
                    foreach ($refund_fees as $fee) {
                        $fee_id = absint($fee['id']);
                        $total  = floatval($fee['total']);
                        $tax    = floatval($fee['tax']);

                        if ($total === 0 && $tax === 0) {
                            continue;
                        }

                        $order_fee = $order->get_item($fee_id);
                        if (!$order_fee) {
                            continue;
                        }

                        $line = array_filter([
                            'qty'          => null,
                            'refund_total' => $total,
                        ]);

                        if ($tax > 0) {
                            if (!$tax_rate_id) {
                                return new WP_Error('tax_rate_error', 'Tax refund requested but no valid tax rate found.', ['status' => 400]);
                            }
                            $line['refund_tax'] = [$tax_rate_id => $tax];
                        }

                        $refund_data['line_items'][$fee_id] = $line;
                        $total_refund += $total + $tax;
                    }

                    foreach ($refund_shipping as $shipping) {
                        $shipping_id = absint($shipping['id']);
                        $total       = floatval($shipping['total']);
                        $tax         = floatval($shipping['tax']);

                        if ($total === 0 && $tax === 0) {
                            continue;
                        }

                        $order_shipping = $order->get_item($shipping_id);
                        if (!$order_shipping) {
                            continue;
                        }

                        $line = array_filter([
                            'refund_total' => $total,
                        ]);

                        if ($tax > 0) {
                            if (!$tax_rate_id) {
                                return new WP_Error('tax_rate_error', 'Tax refund requested but no valid tax rate found.', ['status' => 400]);
                            }
                            $line['refund_tax'] = [$tax_rate_id => $tax];
                        }

                        $refund_data['line_items'][$shipping_id] = $line;
                        $total_refund += $total + $tax;
                    }
                }

                if (empty($refund_data['line_items'])) {
                    continue;
                }

                $refund_data['amount'] = $total_refund;

                $refund = wc_create_refund($refund_data);
                if (is_wp_error($refund)) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error'   => $refund->get_error_message(),
                    ], 400);
                }

                $refund_ids[] = $refund->get_id();

                // 💳 Braintree
                if (!empty($params['refund_via_braintree'])) {
                    $tx_to_use = ($tx_id === 'default') ? $order->get_transaction_id() : $tx_id;
                    $result = $this->refund_braintree_transaction($order, $total_refund, '', $tx_to_use);
                    if (is_wp_error($result)) {
                        return new WP_REST_Response([
                            'success' => false,
                            'error'   => $result->get_error_message(),
                        ], 400);
                    }
                }
            }

            if (empty($refund_ids)) {
                return new WP_Error('no_refund_data', 'Nothing to refund', ['status' => 400]);
            }

            $total_order_amount = floatval($order->get_total()); // Total paid by customer
            $total_refunded_amount = floatval($order->get_total_refunded()); // Sum of all refunds

            if ($total_refunded_amount >= $total_order_amount) {
                $order->update_status('refunded', 'Order fully refunded via PFM Panel');
            }

            return rest_ensure_response([
                'success'    => true,
                'refund_ids' => $refund_ids,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('server_error', 'Unexpected server error: ' . $e->getMessage(), ['status' => 500]);
        }
    }





    public function refund_braintree_transaction($order, $amount, $reason = '', $transaction_id = null) {
        if (!$transaction_id) {
            $transaction_id = $order->get_transaction_id();
        }

        if (empty($transaction_id)) {

            return new WP_Error('refund-error', 'Missing transaction ID for this order.');
        }

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];

        $settings = get_option('woocommerce_braintree_api_settings');

        $environment = $settings['environment'];
        if ($environment === 'sandbox') {
            $public_key  = $settings['sandbox_public_key'];
            $private_key = $settings['sandbox_private_key'];
            $merchant_id = $settings['sandbox_merchant_id'];
        } else {
            $public_key  = $settings['production_public_key'];
            $private_key = $settings['production_private_key'];
            $merchant_id = $settings['production_merchant_id'];
        }

        \Braintree\Configuration::environment($environment);
        \Braintree\Configuration::merchantId($merchant_id);
        \Braintree\Configuration::publicKey($public_key);
        \Braintree\Configuration::privateKey($private_key);



        try {
            $result = \Braintree\Transaction::refund($transaction_id, $amount);

            if ($result->success) {
                $refund_id = $result->transaction->id;

                $order->add_order_note(sprintf(
                    '✅ Refund processed in Braintree. Amount: %s. Refund ID: %s. By %s.',
                    wc_price($amount, ['currency' => $order->get_currency()]),
                    $refund_id,
                    $admin_name
                ));
                $order->update_meta_data('_braintree_refund_id', $refund_id);
                $order->save();

                return true;
            } else {
                $error_message = $result->message;

                return new WP_Error('refund-error', 'Braintree error: ' . $error_message);
            }
        } catch (Exception $e) {

            return new WP_Error('refund-exception', 'Exception: ' . $e->getMessage());
        }
    }




    public function get_orders_by_user_id(WP_REST_Request $request) {
        global $wpdb;

        $user_id = absint($request['user_id']);
        if (!$user_id) {
            return new WP_Error('invalid_user', 'Invalid user ID.', ['status' => 400]);
        }

        $per_page = absint($request->get_param('per_page')) ?: 10;
        $page = absint($request->get_param('page')) ?: 1;

        // 1. Live Orders (standard WooCommerce)
        $args = [
            'customer_id' => $user_id,
            'limit'       => $per_page,
            'paged'       => $page,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys(wc_get_order_statuses())
        ];

        $live_orders = [];
        try {
            $orders = wc_get_orders($args);
            foreach ($orders as $order) {
                $live_orders[] = [
                    'id' => $order->get_id(),
                    'status' => $order->get_status(),
                    'total' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
                    'is_archived' => false,
                ];
            }
        } catch (Throwable $e) {
            // Optionally log or handle error
        }

        // 2. Archived Orders (optimized single query)
        $archived_orders = [];
        try {
            $offset = ($page - 1) * $per_page;
            $sql = $wpdb->prepare(
                "SELECT 
                p.ID, 
                p.post_status, 
                p.post_date_gmt,
                MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) AS order_total,
                MAX(CASE WHEN pm.meta_key = '_order_currency' THEN pm.meta_value END) AS order_currency
            FROM yom_archive_orders_posts p
            JOIN yom_archive_orders_postmeta m ON p.ID = m.post_id
            LEFT JOIN yom_archive_orders_postmeta pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
                AND p.post_status NOT IN ('trash', 'auto-draft')
                AND m.meta_key = '_customer_user'
                AND m.meta_value = %s
            GROUP BY p.ID
            ORDER BY p.post_date_gmt DESC
            LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            );
            $archived_posts = $wpdb->get_results($sql);

            foreach ($archived_posts as $post) {
                $archived_orders[] = [
                    'id' => intval($post->ID),
                    'status' => $post->post_status,
                    'total' => isset($post->order_total) ? floatval($post->order_total) : null,
                    'currency' => isset($post->order_currency) ? $post->order_currency : '',
                    'date_created' => $post->post_date_gmt,
                    'is_archived' => true,
                ];
            }
        } catch (Throwable $e) {
            // Optionally log or handle error
        }

        // 3. Merge and sort
        $all_orders = array_merge($live_orders, $archived_orders);
        usort($all_orders, function ($a, $b) {
            return strtotime($b['date_created']) <=> strtotime($a['date_created']);
        });

        // 4. Remove duplicates
        $seen = [];
        $deduped = [];
        foreach ($all_orders as $order) {
            if (isset($seen[$order['id']])) continue;
            $deduped[] = $order;
            $seen[$order['id']] = true;
        }

        // 5. Paginate after merging (optional, or just let frontend handle it)
        $result = array_slice($deduped, 0, $per_page);

        return rest_ensure_response($result);
    }


    public function update_order(WP_REST_Request $request) {
        $order_id = absint($request['id']);
        $order = wc_get_order($order_id);
        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }

        $params = $request->get_json_params();
        $updated_fields = [];

        if (isset($params['status'])) {
            $order->set_status(sanitize_key($params['status']));
            $order->add_order_note("Order status changed to '{$params['status']}' by $admin_name");
        }

        // Update meta
        if (isset($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                $order->update_meta_data(sanitize_text_field($key), sanitize_text_field($value));
            }
        }

        // Billing updates
        if (isset($params['billing']) && is_array($params['billing'])) {
            foreach ($params['billing'] as $key => $value) {
                $setter = "set_billing_" . $key;
                if (method_exists($order, $setter)) {
                    $order->$setter(sanitize_text_field($value));
                    $updated_fields[] = "billing_{$key}";
                }
            }
        }

        // Shipping updates
        if (isset($params['shipping']) && is_array($params['shipping'])) {
            foreach ($params['shipping'] as $key => $value) {
                $setter = "set_shipping_" . $key;
                if (method_exists($order, $setter)) {
                    $order->$setter(sanitize_text_field($value));
                    $updated_fields[] = "shipping_{$key}";
                }
            }
        }

        $updated_profile = false;

        // Update customer profile if requested
        if (!empty($params['update_customer_profile']) && $order->get_user_id()) {
            $user_id = $order->get_user_id();

            foreach ($params['billing'] as $key => $value) {
                update_user_meta($user_id, "billing_{$key}", sanitize_text_field($value));
            }

            foreach ($params['shipping'] as $key => $value) {
                update_user_meta($user_id, "shipping_{$key}", sanitize_text_field($value));
            }

            $updated_profile = true;
        }

        try {
            $order->save();

            // Add order note summarizing updates
            if (!empty($updated_fields)) {
                $note = "<b>Customer info updated by $admin_name</b><br><br>";
                $note .= '🔄 <b>Billing and Shipping details were updated.</b><br><br>';
                $note .= '✅ <b>Changes applied to:</b> ' . ($updated_profile ? 'Order and Customer Profile' : 'Order only');

                $order->add_order_note($note);
                $order->save();
            }

            return rest_ensure_response(['success' => true, 'order_id' => $order_id]);
        } catch (Throwable $e) {
            return new WP_Error('update_failed', $e->getMessage(), ['status' => 500]);
        }
    }


    public function get_single_order(WP_REST_Request $request) {
        $order_id = absint($request['id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }

        $data = $order->get_data();
        $data['meta_data'] = $order->get_meta_data();

        // 🛒 Line Items (Products)
        $data['line_items'] = array_values(array_map(function ($item) use ($order) {
            return $this->format_item($item, $order);
        }, array_filter($order->get_items('line_item'), function ($item) {
            return is_a($item, 'WC_Order_Item_Product');
        })));

        // 💸 Fee Items with refund data
        $data['fee_lines'] = array_values(array_map(function ($item) use ($order) {
            return $this->format_item($item, $order);
        }, $order->get_items('fee')));

        // 🚚 Shipping Items with refund data
        $data['shipping_lines'] = array_values(array_map(function ($item) use ($order) {
            return $this->format_item($item, $order);
        }, $order->get_items('shipping')));

        // 🧾 Tax and Coupons (no refund data needed)
        $data['tax_lines'] = array_values(array_map([$this, 'format_item'], $order->get_items('tax')));
        $data['coupon_lines'] = array_values(array_map([$this, 'format_item'], $order->get_items('coupon')));

        // ↩ Refunds Summary
        $data['refunds'] = array_map(function ($refund) {
            return [
                'id'     => $refund->get_id(),
                'total'  => $refund->get_amount(),
                'date'   => $refund->get_date_created() ? $refund->get_date_created()->date('Y-m-d H:i:s') : null,
                'reason' => $refund->get_reason(),
            ];
        }, $order->get_refunds());


        $data['transaction_id'] = $order->get_transaction_id() ?? '';
        $data['subscription_branch'] = $this->get_subscription_branch_for_order($order);

        return rest_ensure_response($data);
    }


    private function get_subscription_branch_for_order($order) {
        $order_id = $order->get_id();
        $subscription_id = $order->get_meta('_subscription_parent') ?: $order->get_meta('_subscription_renewal');
        $branch = [];

        if (!$subscription_id) return $branch;

        // Load Subscription
        $subscription = wcs_get_subscription($subscription_id);
        if ($subscription) {
            $branch[] = [
                'id' => $subscription->get_id(),
                'relationship' => 'Subscription',
                'date' => $subscription->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $subscription->get_status(),
                'total' => $subscription->get_formatted_order_total(),
            ];
        }

        // Parent orders
        $parents = wc_get_orders([
            'meta_key' => '_subscription_parent',
            'meta_value' => $subscription_id,
            'limit' => -1,
        ]);
        foreach ($parents as $parent) {
            $branch[] = [
                'id' => $parent->get_id(),
                'relationship' => 'Parent Order',
                'date' => $parent->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $parent->get_status(),
                'total' => wc_price($parent->get_total(), ['currency' => $parent->get_currency()]),
            ];
        }

        // Renewals
        $renewals = wc_get_orders([
            'meta_key' => '_subscription_renewal',
            'meta_value' => $subscription_id,
            'orderby' => 'date',
            'order' => 'ASC',
            'limit' => -1,
        ]);
        foreach ($renewals as $renewal) {
            $branch[] = [
                'id' => $renewal->get_id(),
                'relationship' => 'Renewal Order',
                'date' => $renewal->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $renewal->get_status(),
                'total' => wc_price($renewal->get_total(), ['currency' => $renewal->get_currency()]),
            ];
        }

        // Mark current order
        foreach ($branch as &$entry) {
            if ((int)$entry['id'] === (int)$order_id) {
                $entry['relationship'] .= ' (This Order)';
            }
        }

        return $branch;
    }


    private function format_item($item, $order = null) {
        $data = $item->get_data();

        if ($item instanceof WC_Order_Item_Product) {
            $product = $item->get_product();
            if ($product) {
                $data['sku'] = $product->get_sku();
                $data['image'] = [
                    'src' => wp_get_attachment_url($product->get_image_id()),
                    'alt' => $product->get_name(),
                ];
            }

            // Qty refunded only makes sense for products
            if ($order && method_exists($order, 'get_qty_refunded_for_item')) {
                $data['qty_refunded'] = -1 * $order->get_qty_refunded_for_item($item->get_id());
            }
        }

        // Fallback default
        $data['total_refunded'] = 0;

        if ($order) {
            // For tax (works)
            try {
                $taxes = $item->get_taxes()['total'] ?? [];
                $rate_id = key($taxes);
                $data['refunded_tax'] = $rate_id
                    ? $order->get_tax_refunded_for_item($item->get_id(), $rate_id, $item->get_type())
                    : 0;
            } catch (Throwable $e) {
                $data['refunded_tax'] = 0;
            }

            // ✅ Manual total_refunded logic
            $refunds = $order->get_refunds();
            foreach ($refunds as $refund) {
                foreach ($refund->get_items($item->get_type()) as $refunded_item) {
                    $refunded_item_id = $refunded_item->get_meta('_refunded_item_id', true);
                    if ((int)$refunded_item_id === $item->get_id()) {
                        $data['total_refunded'] += abs($refunded_item->get_total());
                    }
                }
            }
        }

        return $data;
    }







    public function get_filtered_orders(WP_REST_Request $request) {
        // Build normal args for non-ID search
        $args = [
            'type'     => 'shop_order',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'paginate' => true,
            'page'     => max(1, $request['page']),
            'limit'    => min(100, max(1, $request['per_page'])),
        ];

        // Date filters
        $date_from = $request['date_from'] ?? null;
        $date_to = $request['date_to'] ?? null;

        if ($date_from || $date_to) {
            $from_timestamp = null;
            $to_timestamp = null;

            if ($date_from) {
                $from_string = date('Y-m-d H:i:s', strtotime('-2 hours', strtotime($date_from . ' 00:00:00')));
                $from_timestamp = strtotime($from_string);
            }

            if ($date_to) {
                // Add +1 day to include full date, and +5h for offset
                $to_string = date('Y-m-d H:i:s', strtotime('+1 day -2 hours', strtotime($date_to . ' 00:00:00')));
                $to_timestamp = strtotime($to_string);
            }

            if ($from_timestamp && $to_timestamp) {
                $args['date_created'] = "{$from_timestamp}...{$to_timestamp}";
            } elseif ($from_timestamp) {
                $args['date_created'] = ">={$from_timestamp}";
            } elseif ($to_timestamp) {
                $args['date_created'] = "<={$to_timestamp}";
            }
        }

        // Status filter
        if (!empty($request['status'])) {
            $args['status'] = sanitize_key($request['status']);
        }

        // Meta filters
        $meta_query = [];

        // Warehouse
        if (isset($request['warehouse'])) {
            $wh = $request['warehouse'];
            if ($wh === 'shipstation') {
                $meta_query[] = [
                    'relation' => 'OR',
                    ['key' => 'warehouse_to_export', 'compare' => 'NOT EXISTS'],
                    ['key' => 'warehouse_to_export', 'value' => '', 'compare' => '='],
                ];
            } else {
                $meta_query[] = [
                    'key'   => 'warehouse_to_export',
                    'value' => $wh,
                ];
            }
        }

        // Export Status
        if (!empty($request['export_status'])) {
            $meta_query[] = [
                'key'   => 'warehouse_export_status',
                'value' => $request['export_status'],
            ];
        }

        // Address Validation Status
        if (!empty($request['addr_status'])) {
            $meta_query[] = [
                'key'   => 'validate_address_status',
                'value' => $request['addr_status'],
            ];
        }

        // Tag filters
        if (!empty($request['tag'])) {
            $tag = $request['tag'];
            if ($tag === 'ppu-on-hold') {
                $meta_query[] = [
                    'key' => 'ppu_status',
                    'value' => 'on-hold',
                ];
            } elseif ($tag === 'ppu-added') {
                $meta_query[] = [
                    'key' => 'ppu_products_count',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC',
                ];
            } elseif ($tag === 'facebook') {
                $meta_query[] = [
                    'key' => 'facebook_order_id',
                    'compare' => 'EXISTS',
                ];
            } elseif ($tag === 'walmart') {
                $meta_query[] = [
                    'key' => 'walmart_order_id',
                    'compare' => 'EXISTS',
                ];
            } elseif ($tag === 'subscription-renewal') {
                $meta_query[] = [
                    'key' => '_subscription_renewal',
                    'compare' => 'EXISTS',
                ];
            } elseif ($tag === 'subscription-parent') {
                $meta_query[] = [
                    'key' => '_subscription_parent',
                    'compare' => 'EXISTS',
                ];
            } elseif ($tag === 'bas-added') {
                $meta_query[] = [
                    'key' => '_upsell_amount',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC',
                ];
            }
        }

        $search_type  = $request['search_type'] ?? null;
        $search_value = $request['search_value'] ?? null;


        // If searching by order_id(s), handle specially
        if ($search_type === 'order_id' && !empty($search_value)) {
            // Support multiple IDs separated by comma or space
            $id_string = str_replace([',', ';'], ' ', $search_value);
            $ids = array_filter(array_map('absint', preg_split('/\s+/', $id_string)));
            $unique_ids = array_unique($ids);

            $orders = [];
            foreach ($unique_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $data = $order->get_data();
                    $data['meta_data'] = $order->get_meta_data();
                    $data['meta'] = [
                        'warehouse_to_export' => $order->get_meta('warehouse_to_export'),
                        'warehouse_export_status' => $order->get_meta('warehouse_export_status'),
                        'validate_address_status' => $order->get_meta('validate_address_status'),
                        '_wc_shipment_tracking_items' => $order->get_meta('_wc_shipment_tracking_items'),
                        '_subscription_renewal' => $order->get_meta('_subscription_renewal'),
                        '_subscription_parent' => $order->get_meta('_subscription_parent'),
                    ];
                    $orders[] = $data;
                }
            }

            $response = rest_ensure_response($orders);
            $response->header('X-WP-Total', count($orders));
            $response->header('X-WP-TotalPages', 1);

            return $response;
        }

        // Email search or normal filters
        if ($search_type === 'customer_email' && !empty($search_value)) {
            $args['billing_email'] = $search_value;
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }


        try {
            $query = wc_get_orders($args);
            $order_ids = array_map(function ($order) {
                return $order->get_id();
            }, $query->orders);

            $orders = array_map(function ($order) {
                $data = $order->get_data();
                $data['meta_data'] = $order->get_meta_data();
                $data['meta'] = [
                    'warehouse_to_export' => $order->get_meta('warehouse_to_export'),
                    'warehouse_export_status' => $order->get_meta('warehouse_export_status'),
                    'validate_address_status' => $order->get_meta('validate_address_status'),
                    '_wc_shipment_tracking_items' => $order->get_meta('_wc_shipment_tracking_items'),
                    '_subscription_renewal' => $order->get_meta('_subscription_renewal'),
                    '_subscription_parent' => $order->get_meta('_subscription_parent'),
                ];
                return $data;
            }, $query->orders);

            $response = rest_ensure_response($orders);
            $response->header('X-WP-Total', $query->total);
            $response->header('X-WP-TotalPages', $query->max_num_pages);

            return $response;
        } catch (Throwable $e) {
            PFMP_Utils::log('Order query failed: ' . $e->getMessage());
            return new WP_Error('order_query_failed', 'Failed to fetch orders.', ['status' => 500]);
        }
    }
}
