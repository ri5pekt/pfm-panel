<?php
// class-pfmp-rest-replacements.php
defined('ABSPATH') || exit;


class PFMP_REST_Replacements {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }

    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/replacements', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_replacements'],
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

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_single_replacement'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_replacement'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)/notes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_replacement_notes'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)/edit', [
            'methods'  => 'POST',
            'callback' => [$this, 'edit_replacement'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)/export-to-warehouse', [
            'methods'             => 'POST',
            'callback'            => [$this, 'export_replacement_to_warehouse'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)/revalidate-address', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_replacement_address_revalidation'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_replacement'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/replacements/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [$this, 'delete_replacement'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);
    }

    public function delete_replacement(WP_REST_Request $request) {
        $id = absint($request['id']);
        global $wpdb;
        $table = $wpdb->prefix . 'replacement_orders';
        $wpdb->delete($table, ['id' => $id]);
        return rest_ensure_response(['success' => true]);
    }

    public function create_replacement(WP_REST_Request $request) {
        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';

        PFMP_Utils::log("📥 Replacement creation initiated by {$admin_name}");

        try {
            $order_id = absint($request->get_param('order_id'));

            if ($order_id) {
                PFMP_Utils::log("🔄 Creating replacement from WC Order ID: {$order_id}");
                $replacement = wro_create_from_wc_order($order_id, $current_user->ID, false);

                if (!$replacement) {
                    throw new Exception("Failed to clone from order #{$order_id}");
                }
            } else {
                // 🛠️ Manual creation flow
                $args = [
                    'customer_id' => 0,
                    'created_by'  => $current_user->ID,
                    'status'      => 'pending',
                    'items'       => [],
                    'meta'        => [],
                ];

                // 🔧 Optional: customer_id
                if ($request->get_param('customer_id') !== null) {
                    $args['customer_id'] = absint($request->get_param('customer_id'));
                }

                // 🔧 Optional: items
                if ($request->get_param('items')) {
                    $items = $request->get_param('items');
                    if (is_array($items)) {
                        $args['items'] = $items;
                    }
                }

                // 🔧 Optional: meta
                if ($request->get_param('meta')) {
                    $meta = $request->get_param('meta');
                    if (is_array($meta)) {
                        $args['meta'] = $meta;
                    }
                }

                $replacement = wro_create_order($args);
            }

            $replacement->assign_customer_if_missing();
            $replacement->add_note('Replacement order created by ' . $admin_name, $current_user->ID);
            $replacement->save();

            PFMP_Utils::log("✅ Replacement created: ID " . $replacement->get_id());

            return rest_ensure_response([
                'id'         => $replacement->get_id(),
                'order_note' => 'Created by ' . $admin_name,
                'status'     => $replacement->status,
            ]);
        } catch (Throwable $e) {
            PFMP_Utils::log("🔥 Replacement creation failed: " . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error creating replacement: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function get_replacements(WP_REST_Request $request) {
        $page     = max(1, absint($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?? 10)));
        $offset   = ($page - 1) * $per_page;

        $args = [
            'limit'       => $per_page,
            'offset'      => $offset,
            'page'        => $page,
            'paginate'    => true,
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
                $order = wro_get_order($order_id);
                if ($order) {
                    $orders[] = $this->format_replacement_order($order);
                }
            }

            $response = rest_ensure_response($orders);
            $response->header('X-WP-Total', count($orders));
            $response->header('X-WP-TotalPages', 1);

            return $response;
        }

        // Email search or normal filters
        // Email search or normal filters
        if ($search_type === 'customer_email' && !empty($search_value)) {
            $user = get_user_by('email', $search_value);

            if ($user && $user->ID) {
                $args['customer_id'] = (int) $user->ID;
            } else {
                // No customer found — return empty result
                $response = rest_ensure_response([]);
                $response->header('X-WP-Total', 0);
                $response->header('X-WP-TotalPages', 0);
                return $response;
            }
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        if (!empty($request['customer_id'])) {
            $args['customer_id'] = absint($request['customer_id']);
        }

        // ⚙️ Perform query
        $result = wro_get_orders($args);
        $orders = $result['orders'] ?? [];
        $total = $result['total'] ?? 0;
        $total_pages = $result['total_pages'] ?? 1;

        $data = array_map([$this, 'format_replacement_order'], $orders);

        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', $total_pages);

        return $response;
    }

    public function get_single_replacement(WP_REST_Request $request) {
        $id = absint($request['id']);
        $replacement = new WRO_Replacement_Order($id);

        if (!$replacement->id) {
            return new WP_Error('not_found', 'Replacement order not found', ['status' => 404]);
        }

        // Load user data
        $customer_user = get_userdata($replacement->customer_id);
        $creator_user = get_userdata($replacement->created_by);

        // Define billing & shipping keys
        $billing_keys = ['first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];
        $shipping_keys = ['first_name', 'last_name', 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];

        // 🧾 Extract billing & shipping from meta if available, else fallback to profile
        $billing = [];
        foreach ($billing_keys as $key) {
            $meta_key = "billing_$key";
            $billing[$key] = (string) (
                $replacement->meta[$meta_key]
                ?? get_user_meta($replacement->customer_id, $meta_key, true)
                ?: ($key === 'email' ? ($customer_user->user_email ?? '') : '')
            );
        }

        $shipping = [];
        foreach ($shipping_keys as $key) {
            $meta_key = "shipping_$key";
            $shipping[$key] = (string) (
                $replacement->meta[$meta_key]
                ?? get_user_meta($replacement->customer_id, $meta_key, true)
                ?: ''
            );
        }

        // 👷 Creator info
        $created_by = [
            'id'         => $replacement->created_by,
            'first_name' => get_user_meta($replacement->created_by, 'first_name', true),
            'last_name'  => get_user_meta($replacement->created_by, 'last_name', true),
            'email'      => $creator_user ? $creator_user->user_email : '',
        ];

        // 🧾 Meta (convert to WC-style key/value pairs)
        $meta_array = [];
        foreach ($replacement->meta as $key => $value) {
            $meta_array[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        // 🕒 Date formatting
        $created_date = $replacement->created_at ?: current_time('mysql');
        if (strpos($created_date, '.') === false) {
            $created_date .= '.000000';
        }

        $line_items =  array_map(function ($item) {
            return [
                'id'           => $item->get_id(),
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'sku'          => $item->get_sku(),
                'quantity'     => $item->get_quantity(),
                'total'        => $item->get_total(),
                'subtotal'     => $item->get_subtotal(),
                'total_tax'    => $item->get_total_tax(),
                'subtotal_tax' => $item->get_subtotal_tax(),
                'image'        => $item->get_image(),
            ];
        }, $replacement->get_items('line_item'));

        // ✅ Final response
        $data = [
            'id'                 => $replacement->id,
            'status'             => $replacement->status,
            'created_at'         => $replacement->created_at,
            'date_created'       => [
                'date'           => $created_date,
                'timezone_type'  => 1,
                'timezone'       => '+00:00',
            ],
            'items'              => $replacement->items,
            'meta_data'          => $meta_array,
            'notes'              => $replacement->notes,
            'customer_id'        => $replacement->customer_id,
            'billing'            => $billing,
            'shipping'           => $shipping,
            'created_by'         => $created_by,
            'transaction_id'     => $replacement->meta['transaction_id'] ?? '',
            'subscription_branch' => [],
            'line_items' => $line_items,
        ];

        $customer_id = isset($replacement->customer_id) ? intval($replacement->customer_id) : 0;
        if ($customer_id) {
            $user = get_userdata($customer_id);
            if ($user) {
                $data['billing']['first_name'] = get_user_meta($customer_id, 'billing_first_name', true) ?: ($user->first_name ?? '');
                $data['billing']['last_name']  = get_user_meta($customer_id, 'billing_last_name', true) ?: ($user->last_name ?? '');
                $data['billing']['email']      = $user->user_email;

                $data['customer_profile'] = [
                    'first_name' => $data['billing']['first_name'],
                    'last_name'  => $data['billing']['last_name'],
                    'email'      => $data['billing']['email'],
                ];
            }
        }

        $data['shipping_lines'] = $replacement->get_shipping_lines();

        return rest_ensure_response($data);
    }


    public function update_replacement(WP_REST_Request $request) {
        $id = absint($request['id']);
        $replacement = new WRO_Replacement_Order($id);

        if (!$replacement->id) {
            return new WP_Error('not_found', 'Replacement order not found', ['status' => 404]);
        }

        $params = $request->get_json_params();
        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';

        $updated_fields = [];

        // Update status
        if (isset($params['status'])) {
            $replacement->status = sanitize_key($params['status']);
            $replacement->add_note("Status changed to '{$replacement->status}' by {$admin_name}", $current_user->ID);
            $updated_fields[] = 'status';
        }

        // Update meta
        if (isset($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                $replacement->meta[$key] = sanitize_text_field($value);
                $updated_fields[] = "meta:{$key}";
            }
        }

        // Billing
        if (isset($params['billing']) && is_array($params['billing'])) {
            foreach ($params['billing'] as $key => $value) {
                $replacement->meta["billing_{$key}"] = sanitize_text_field($value);
                $updated_fields[] = "billing:{$key}";
            }
        }

        // Shipping
        if (isset($params['shipping']) && is_array($params['shipping'])) {
            foreach ($params['shipping'] as $key => $value) {
                $replacement->meta["shipping_{$key}"] = sanitize_text_field($value);
                $updated_fields[] = "shipping:{$key}";
            }
        }

        // Update customer profile if requested
        $updated_profile = false;
        if (!empty($params['update_customer_profile']) && $replacement->customer_id) {
            foreach ($params['billing'] as $key => $value) {
                update_user_meta($replacement->customer_id, "billing_{$key}", sanitize_text_field($value));
            }
            foreach ($params['shipping'] as $key => $value) {
                update_user_meta($replacement->customer_id, "shipping_{$key}", sanitize_text_field($value));
            }
            $updated_profile = true;
        }

        if (isset($params['customer_id']) && is_numeric($params['customer_id'])) {
            $new_customer_id = absint($params['customer_id']);
            $user_info = get_userdata($new_customer_id);
            $user_display = $user_info ? $user_info->display_name : "User #{$new_customer_id}";
            if ($new_customer_id !== $replacement->get_customer_id()) {
                $replacement->set_customer_id($new_customer_id);
                $replacement->add_order_note("Customer changed to {$user_display} (ID: {$new_customer_id}) by {$admin_name}.");
            }
        }




        try {
            $replacement->assign_customer_if_missing();
            $replacement->save();

            return rest_ensure_response([
                'success' => true,
                'replacement_id' => $replacement->id,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('update_failed', $e->getMessage(), ['status' => 500]);
        }
    }


    public function get_replacement_notes(WP_REST_Request $request) {
        $id = absint($request['id']);
        $replacement = new WRO_Replacement_Order($id);

        if (!$replacement->id) {
            return new WP_Error('not_found', 'Replacement order not found', ['status' => 404]);
        }

        $notes = [];
        foreach ($replacement->notes as $index => $note) {
            $notes[] = [
                'id' => $index + 1,
                'note' => $note['note'] ?? '',
                'date_created' => $note['timestamp'] ?? '',
                'author' => !empty($note['author_id']) ? get_userdata($note['author_id'])?->display_name : 'System',
            ];
        }
        $notes = array_reverse($notes);
        return rest_ensure_response($notes);
    }


    public function edit_replacement(WP_REST_Request $request) {
        $id = absint($request['id']);
        $params = $request->get_json_params();

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?: 'Admin';

        $replacement = new WRO_Replacement_Order($id);
        if (!$replacement->id) {
            return new WP_Error('not_found', 'Replacement order not found', ['status' => 404]);
        }

        $changed = false;

        // ✅ Modify existing items
        if (!empty($params['items']) && is_array($params['items'])) {
            foreach ($params['items'] as $item) {
                if (!isset($item['id'])) continue;
                $index = $item['id'] - 1;
                if (!isset($replacement->items[$index])) continue;

                if (isset($item['quantity'])) {
                    $replacement->items[$index]['quantity'] = max(1, (int) $item['quantity']);
                }


                $changed = true;
            }
        }

        // ✅ Remove items by index-based ID (id = index + 1)
        if (!empty($params['removed_items']) && is_array($params['removed_items'])) {
            foreach ($params['removed_items'] as $idToRemove) {
                $index = $idToRemove - 1;
                if (isset($replacement->items[$index])) {
                    unset($replacement->items[$index]);
                    $changed = true;
                }
            }
            // Reindex
            $replacement->items = array_values($replacement->items);
        }

        // ✅ Add new items
        if (!empty($params['new_items']) && is_array($params['new_items'])) {
            foreach ($params['new_items'] as $item) {
                $product_id = absint($item['product_id'] ?? 0);
                $quantity = absint($item['quantity'] ?? 0);
                if (!$product_id || $quantity < 1) continue;

                $product = wc_get_product($product_id);
                if (!$product) continue;

                $replacement->items[] = [
                    'product_id'   => $product->get_id(),
                    'variation_id' => 0,
                    'quantity'     => $quantity,
                    'name'         => $product->get_name(),
                    'sku'          => $product->get_sku(),
                    'image'        => wp_get_attachment_url($product->get_image_id()),
                    'total'        => 0,
                    'tax'          => 0,
                ];

                $changed = true;
            }
        }

        // Update shipping method (if using simplified shipping_lines array)
        if (isset($params['shipping']) && is_array($params['shipping']) && isset($params['shipping'][0]['method_id'])) {

            $shipping_line = $params['shipping'][0];

            $method_id     = sanitize_text_field($shipping_line['method_id'] ?? '');
            $method_title  = sanitize_text_field($shipping_line['method_title'] ?? '');
            $total         = 0;
            $tax           = 0;

            $instance_parts = explode(':', $method_id);
            $base_method_id = $instance_parts[0] ?? '';
            $instance_id    = $instance_parts[1] ?? '';

            $replacement->meta['shipping_lines'] = [[
                'method_id'     => $base_method_id,
                'instance_id'   => $instance_id,
                'method_title'  => $method_title,
                'total'         => $total,
                'tax'           => $tax,
            ]];

            $changed = true;
        }

        // ✅ Save and log note
        if ($changed) {
            $replacement->assign_customer_if_missing();
            $replacement->add_note('Replacement order updated by ' . $admin_name, $current_user->ID);
            $replacement->save();
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Replacement order updated successfully.',
        ]);
    }


    public function export_replacement_to_warehouse(WP_REST_Request $request) {
        $id = absint($request->get_param('id'));
        $warehouse_slug = sanitize_text_field($request->get_param('warehouse'));

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';

        if ($warehouse_slug === 'decide_based_on_rules') {
            if (!class_exists('\WEX\Utils') || !method_exists('\WEX\Utils', 'get_warehouse_for_order')) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Warehouse rules engine is unavailable.',
                ], 500);
            }
            $warehouse_slug = \WEX\Utils::get_warehouse_for_order($id);
        }

        if (!$id || !$warehouse_slug) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid replacement ID or warehouse slug.',
            ], 400);
        }

        $replacement = new WRO_Replacement_Order($id);
        if (!$replacement->id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Replacement order not found.',
            ], 404);
        }

        global $wex_plugin;
        if (!isset($wex_plugin) || !method_exists($wex_plugin, 'send_order_to_warehouse')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Warehouse export plugin is not available.',
            ], 500);
        }

        $response = $wex_plugin->send_order_to_warehouse($warehouse_slug, $id);

        if (!empty($response['status']) && $response['status'] === 'success') {
            $replacement->meta['warehouse_to_export'] = $warehouse_slug;
            $replacement->meta['warehouse_export_status'] = 'exported';
            $replacement->add_note("Replacement manually exported to {$warehouse_slug} by {$admin_name}.", $current_user->ID);
            $replacement->save();

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Replacement was manually exported successfully!',
            ]);
        }

        $error_msg = !empty($response['message']) ? $response['message'] : 'Unknown error.';
        return new WP_REST_Response([
            'success' => false,
            'message' => "Export failed: {$error_msg}",
        ], 400);
    }

    public function handle_replacement_address_revalidation(WP_REST_Request $request) {
        try {
            $replacement_id = absint($request->get_param('id'));
            $params = $request->get_json_params();
            $force = !empty($params['force']);

            $current_user = wp_get_current_user();
            $admin_name = $current_user->display_name ?? 'Admin';

            if (!$replacement_id) {
                return new WP_REST_Response(['error' => 'Invalid replacement ID'], 400);
            }

            $replacement = wro_get_order($replacement_id);
            if (!$replacement->id) {
                return new WP_REST_Response(['error' => 'Replacement not found'], 404);
            }

            if ($force) {
                $replacement->meta['validate_address_status'] = 'valid';
                $replacement->add_note("Address status manually forced to VALID by $admin_name.", $current_user->ID);
                $replacement->save();

                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Address status forced to VALID.',
                ]);
            }

            global $wex_plugin;
            if (!isset($wex_plugin)) {
                return new WP_REST_Response(['error' => 'Main_Plugin instance not available.'], 500);
            }

            $result = $wex_plugin->validate_order_address($replacement_id);

            if (!empty($result->is_valid) && $result->is_valid === 'true') {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => $result->message,
                ]);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => $result->message ?? 'Validation failed.',
            ]);
        } catch (Throwable $e) {
            error_log('[handle_replacement_address_revalidation] Exception: ' . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }


    private function format_replacement_order($order) {
        $customer = get_userdata($order->customer_id);
        $creator  = get_userdata($order->created_by);

        return [
            'id'         => $order->id,
            'status'     => $order->status,
            'created_at' => $order->created_at,
            'items'      => $order->items,
            'customer'   => [
                'id'         => $order->customer_id,
                'first_name' => get_user_meta($order->customer_id, 'first_name', true),
                'last_name'  => get_user_meta($order->customer_id, 'last_name', true),
                'email'      => $customer ? $customer->user_email : '',
            ],
            'created_by' => [
                'id'         => $order->created_by,
                'first_name' => get_user_meta($order->created_by, 'first_name', true),
                'last_name'  => get_user_meta($order->created_by, 'last_name', true),
                'email'      => $creator ? $creator->user_email : '',
            ],
            'meta_data' => method_exists($order, 'get_meta_data') ? $order->get_meta_data() : [],
        ];
    }
}
