<?php
defined('ABSPATH') || exit;

class PFMP_REST_Customers {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }
    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/customers', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_customers'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'page'     => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 10, 'sanitize_callback' => 'absint'],
                'search_type' => [],
                'search_value' => [],
                'registered_from' => [],
                'registered_to' => [],
                'last_order_from' => [],
                'last_order_to' => [],
            ]
        ]);

        register_rest_route('pfm-panel/v1', '/customers/(?P<id>\d+)', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_customer'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('pfm-panel/v1', '/customers/(?P<id>\d+)', [
            'methods'  => ['POST', 'PATCH'],
            'callback' => [$this, 'update_customer'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function update_customer(WP_REST_Request $request) {
        $id = absint($request->get_param('id'));
        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $admin_roles = $current_user->roles ?? [];

        $user = get_userdata($id);
        if (
            !$user ||
            !(
                in_array('customer', (array)$user->roles) ||
                in_array('subscriber', (array)$user->roles) ||
                in_array('administrator', (array)$user->roles)
            )
        ) {
            return new WP_Error('not_found', 'Customer not found', ['status' => 404]);
        }

        $params = $request->get_json_params();
        $fields = [];

        try {
            // Prepare for updating WP core user fields
            $userdata_update = ['ID' => $id];
            $has_name_update = false;

            // Update first name
            if (isset($params['first_name'])) {
                $first_name = sanitize_text_field($params['first_name']);
                update_user_meta($id, 'billing_first_name', $first_name);
                $userdata_update['first_name'] = $first_name;
                $fields[] = 'billing_first_name';
                $has_name_update = true;
            }
            // Update last name
            if (isset($params['last_name'])) {
                $last_name = sanitize_text_field($params['last_name']);
                update_user_meta($id, 'billing_last_name', $last_name);
                $userdata_update['last_name'] = $last_name;
                $fields[] = 'billing_last_name';
                $has_name_update = true;
            }

            // If either first or last name changed, update display_name and WP user fields
            if ($has_name_update) {
                $display = trim(
                    (isset($userdata_update['first_name']) ? $userdata_update['first_name'] : $user->first_name) .
                        ' ' .
                        (isset($userdata_update['last_name']) ? $userdata_update['last_name'] : $user->last_name)
                );
                $userdata_update['display_name'] = $display;
                wp_update_user($userdata_update);
                $fields[] = 'user_first_name';
                $fields[] = 'user_last_name';
                $fields[] = 'display_name';
            }

            // Update email
            if (isset($params['email'])) {
                wp_update_user(['ID' => $id, 'user_email' => sanitize_email($params['email'])]);
                $fields[] = 'user_email';
            }

            // Update billing meta fields
            if (isset($params['billing']) && is_array($params['billing'])) {
                foreach ($params['billing'] as $key => $value) {
                    update_user_meta($id, "billing_$key", sanitize_text_field($value));
                    $fields[] = "billing_$key";
                }
            }
            // Update shipping meta fields
            if (isset($params['shipping']) && is_array($params['shipping'])) {
                foreach ($params['shipping'] as $key => $value) {
                    update_user_meta($id, "shipping_$key", sanitize_text_field($value));
                    $fields[] = "shipping_$key";
                }
            }

            // Bulk update orders if requested
            if (!empty($params['update_all_orders']) && $user) {
                $user_id = $user->ID;
                $orders = wc_get_orders(['customer_id' => $user_id, 'return' => 'objects']);
                foreach ($orders as $order) {
                    if (isset($params['billing']) && is_array($params['billing'])) {
                        foreach ($params['billing'] as $key => $value) {
                            $setter = "set_billing_" . $key;
                            if (method_exists($order, $setter)) {
                                $order->$setter(sanitize_text_field($value));
                            }
                        }
                    }
                    if (isset($params['shipping']) && is_array($params['shipping'])) {
                        foreach ($params['shipping'] as $key => $value) {
                            $setter = "set_shipping_" . $key;
                            if (method_exists($order, $setter)) {
                                $order->$setter(sanitize_text_field($value));
                            }
                        }
                    }
                    $order->save();
                    $order->add_order_note("Billing and/or shipping info updated via 'Update all user Orders' option by $admin_name.");
                }
            }

            // Bulk update subscriptions if requested
            if (
                !empty($params['update_all_subscriptions']) &&
                $user &&
                (isset($params['billing']) || isset($params['shipping']))
            ) {
                $user_id = $user->ID;
                $subscriptions = function_exists('wcs_get_users_subscriptions') ? wcs_get_users_subscriptions($user_id) : [];
                foreach ($subscriptions as $sub_id => $sub) {
                    if (isset($params['billing']) && is_array($params['billing'])) {
                        foreach ($params['billing'] as $key => $value) {
                            $setter = "set_billing_" . $key;
                            if (method_exists($sub, $setter)) {
                                $sub->$setter(sanitize_text_field($value));
                            }
                        }
                    }
                    if (isset($params['shipping']) && is_array($params['shipping'])) {
                        foreach ($params['shipping'] as $key => $value) {
                            $setter = "set_shipping_" . $key;
                            if (method_exists($sub, $setter)) {
                                $sub->$setter(sanitize_text_field($value));
                            }
                        }
                    }
                    $sub->save();
                    $sub->add_order_note("Billing and/or shipping info updated via 'Update all user Subscriptions' option by $admin_name.");
                }
            }
        } catch (Throwable $e) {
            return new WP_Error('update_failed', $e->getMessage(), ['status' => 500]);
        }

        return rest_ensure_response(['success' => true, 'fields' => $fields]);
    }

    public function get_customer(WP_REST_Request $request) {
        $id = absint($request->get_param('id'));
        $user = get_user_by('ID', $id);
        if (
            !$user ||
            !(
                in_array('customer', (array)$user->roles) ||
                in_array('subscriber', (array)$user->roles) ||
                in_array('administrator', (array)$user->roles)
            )
        ) {
            return new WP_Error('not_found', 'Customer not found', ['status' => 404]);
        }
        $orders = wc_get_orders([
            'customer_id' => $user->ID,
            'orderby' => 'date',
            'order' => 'DESC',
            'limit' => 1,
        ]);
        $orders_count = count(wc_get_orders(['customer_id' => $user->ID, 'return' => 'ids']));
        $last_order_date = null;
        if (!empty($orders)) {
            $last_order = $orders[0];
            if ($last_order && method_exists($last_order, 'get_date_created') && $last_order->get_date_created()) {
                $last_order_date = $last_order->get_date_created()->date('Y-m-d');
            }
        }

        // Try to get billing/shipping from meta
        $billing = [];
        $shipping = [];
        foreach (
            [
                'first_name',
                'last_name',
                'company',
                'address_1',
                'address_2',
                'city',
                'state',
                'postcode',
                'country',
                'phone',
                'email'
            ] as $field
        ) {
            $billing[$field] = get_user_meta($user->ID, 'billing_' . $field, true);
            $shipping[$field] = get_user_meta($user->ID, 'shipping_' . $field, true);
        }

        $data = [
            'id'              => $user->ID,
            'name'            => $user->display_name,
            'first_name'      => $billing['first_name'],
            'last_name'       => $billing['last_name'],
            'email'           => $user->user_email,
            'orders_count'    => $orders_count,
            'last_order_date' => $last_order_date,
            'registered'      => date('Y-m-d', strtotime($user->user_registered)),
            'billing'         => $billing,
            'shipping'        => $shipping,
        ];
        return rest_ensure_response($data);
    }

    public function get_customers(WP_REST_Request $request) {
        $page = max(1, absint($request->get_param('page')));
        $per_page = min(100, max(1, absint($request->get_param('per_page'))));
        $offset = ($page - 1) * $per_page;

        // Initial user query args
        $args = [
            'role__in' => ['customer', 'subscriber', 'administrator'],
            'number'   => $per_page,
            'offset'   => $offset,
            'orderby'  => 'registered',
            'order'    => 'DESC',
            'fields'   => ['ID', 'display_name', 'user_email', 'user_registered'],
        ];

        // === SEARCH LOGIC ===
        $search_type = $request->get_param('search_type');
        $search_value = $request->get_param('search_value');

        if ($search_type && $search_value) {
            switch ($search_type) {
                case 'customer_name':
                    // Search by display name (case-insensitive)
                    $args['search'] = '*' . esc_attr($search_value) . '*';
                    $args['search_columns'] = ['display_name'];
                    break;
                case 'customer_email':
                    $args['search'] = '*' . esc_attr($search_value) . '*';
                    $args['search_columns'] = ['user_email'];
                    break;
                case 'order_id':
                    $order = wc_get_order(absint($search_value));
                    if ($order && $order->get_customer_id()) {
                        $args['include'] = [$order->get_customer_id()];
                    } else {
                        $args['include'] = [0]; // No results
                    }
                    break;
            }
        }

        // === Registered Date Range ===
        $registered_from = $request->get_param('registered_from');
        $registered_to = $request->get_param('registered_to');
        if ($registered_from || $registered_to) {
            $args['date_query'] = [];
            if ($registered_from) {
                $args['date_query'][] = [
                    'column' => 'user_registered',
                    'after'  => $registered_from . ' 00:00:00',
                ];
            }
            if ($registered_to) {
                $args['date_query'][] = [
                    'column' => 'user_registered',
                    'before' => $registered_to . ' 23:59:59',
                ];
            }
        }

        // === Get initial users ===
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        // === Last Order Date Range filter ===
        $last_order_from = $request->get_param('last_order_from');
        $last_order_to = $request->get_param('last_order_to');

        $data = [];
        foreach ($users as $user) {
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'orderby' => 'date',
                'order' => 'DESC',
                'limit' => 1,
            ]);
            $orders_count = count(wc_get_orders(['customer_id' => $user->ID, 'return' => 'ids']));
            $last_order_date = null;
            if (!empty($orders)) {
                $last_order = $orders[0];
                if ($last_order && method_exists($last_order, 'get_date_created') && $last_order->get_date_created()) {
                    $last_order_date = $last_order->get_date_created()->date('Y-m-d');
                }
            }



            $data[] = [
                'id'              => $user->ID,
                'name'            => $user->display_name,
                'email'           => $user->user_email,
                'orders_count'    => $orders_count,
                'last_order_date' => $last_order_date,
                'registered'      => date('Y-m-d', strtotime($user->user_registered)),
            ];
        }

        // For last_order_date filter, we need to update total count for pagination
        $total = $user_query->get_total();

        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));
        return $response;
    }
}
