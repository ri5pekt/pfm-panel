<?php
// class-pfmp-rest-customers.php

use Automattic\WooCommerce\Blocks\Utils\Utils;

defined('ABSPATH') || exit;

class PFMP_REST_Customers {

    private $yotpo_sources = [
        'loyalty' => [
            'guid' => "0n2wUSuS-MWk45Oz-2_erg",
            'api_key' => "fojr1QSLPmNiIqKgc5ChXgtt",
        ],
        'sweepstakes' => [
            'guid' => "jUdSO8kyGvLndIkCc14UQw",
            'api_key' => "Nz8YDU9AWo8UgkVRAPad9Qtt",
        ],
    ];

    private function get_yotpo_credentials($source) {
        return $this->yotpo_sources[$source] ?? $this->yotpo_sources['loyalty'];
    }

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


        register_rest_route('pfm-panel/v1', '/customers/(?P<id>\d+)/assume_user', [
            'methods'  => 'POST',
            'callback' => [$this, 'assume_user'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);


        register_rest_route('pfm-panel/v1', '/customers/(?P<id>\d+)/yotpo', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_customer_yotpo_data'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'source' => [
                    'required' => false,
                    'default'  => 'loyalty',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);


        register_rest_route('pfm-panel/v1', '/customers/(?P<id>\d+)/yotpo-adjust', [
            'methods'             => 'POST',
            'callback'            => [$this, 'adjust_customer_yotpo_points'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args'                => [
                'id' => [
                    'required'            => true,
                    'sanitize_callback'   => 'absint',
                ],
                'points' => [
                    'required'            => true,
                ],
                'reason' => [
                    'required'            => false,
                ],
                'source' => [
                    'required' => false,
                    'default'  => 'loyalty',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }


    public function adjust_customer_yotpo_points(WP_REST_Request $request) {
        $user_id = absint($request->get_param('id'));
        $points  = intval($request->get_param('points'));
        $reason  = sanitize_text_field($request->get_param('reason') ?? '');
        $source = $request->get_param('source') ?: 'loyalty';

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('not_found', 'Customer not found', ['status' => 404]);
        }

        $email = $user->user_email;
        $creds = $this->get_yotpo_credentials($source);
        $guid = $creds['guid'];
        $api_key = $creds['api_key'];

        if (!$guid || !$api_key) {
            return new WP_Error('yotpo_auth_error', 'Missing Yotpo API credentials', ['status' => 500]);
        }

        // Determine whether this affects total points earned
        $apply_to_earned = $points > 0;

        $payload = [
            'customer_email'                    => $email,
            'point_adjustment_amount'          => $points,
            'apply_adjustment_to_points_earned' => $apply_to_earned,
            'visible_to_customer'              => true,
        ];

        if (!empty($reason)) {
            $payload['history_title'] = $reason;
        }

        // 🔥 Call Yotpo API
        $response = wp_remote_post('https://loyalty.yotpo.com/api/v2/points/adjust', [
            'headers' => [
                'x-guid'     => $guid,
                'x-api-key'  => $api_key,
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('yotpo_request_error', $response->get_error_message(), ['status' => 500]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error('yotpo_api_error', 'Yotpo API error', [
                'status' => $code,
                'body'   => $body,
            ]);
        }

        return rest_ensure_response([
            'status'  => 'success',
            'message' => 'Points successfully adjusted',
            'data'    => $body,
        ]);
    }


    public function get_customer_yotpo_data(WP_REST_Request $request) {
        $id = absint($request->get_param('id'));
        $user = get_userdata($id);
        $source = $request->get_param('source') ?: 'loyalty';
        
        if (!$user) {
            return new WP_Error('not_found', 'Customer not found', ['status' => 404]);
        }

        $email = strtolower($user->user_email);
        $creds = $this->get_yotpo_credentials($source);
        $guid = $creds['guid'];
        $api_key = $creds['api_key'];

        if (!$guid || !$api_key) {
            return new WP_Error('missing_keys', 'Yotpo credentials missing', ['status' => 500]);
        }

        $url = add_query_arg([
            'customer_email'     => $email,
            'with_referral_code' => 'true',
            'with_history'       => 'true',
        ], 'https://loyalty.yotpo.com/api/v2/customers');

        $args = [
            'headers' => [
                'x-guid'    => $guid,
                'x-api-key' => $api_key,
                'Accept'    => 'application/json',
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message(), ['status' => 502]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Extract data
        $referral = $body['referral_link'] ?? [];
        $history  = $body['history_items'] ?? [];

        $data = [
            'points_balance'      => $body['points_balance'] ?? 0,
            'vip_tier'            => $body['vip_tier_name'] ?? null,
            'vip_entry_date'      => $body['vip_tier_entry_date'] ?? null,
            'credit_balance'      => $body['credit_balance'] ?? null,
            'total_purchases'     => $body['total_purchases'] ?? 0,
            'referral_code'       => $referral['code'] ?? null,
            'referral_url'        => $referral['link'] ?? null,
            'referrals_completed' => $referral['completed_referral_customers'] ?? [],
            'history'             => $history,
        ];

        return rest_ensure_response($data);
    }



    public function assume_user(WP_REST_Request $request) {
        $id = absint($request->get_param('id'));
        $user = get_userdata($id);

        if (
            !$user ||
            !(in_array('customer', (array)$user->roles) ||
                in_array('subscriber', (array)$user->roles) ||
                in_array('administrator', (array)$user->roles))
        ) {
            return new WP_Error('not_found', 'Customer not found', ['status' => 404]);
        }

        if (!class_exists('user_switching')) {
            return new WP_Error('plugin_missing', 'User Switching plugin not active', ['status' => 500]);
        }

        // Generate the switch-to-user URL
        $switch_to_user_link = user_switching::switch_to_url($user);
        $switch_to_user_link = html_entity_decode($switch_to_user_link);

        PFMP_Utils::log($switch_to_user_link);
        return rest_ensure_response([
            'success' => true,
            'switch_url' => $switch_to_user_link
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

        // Basic query args
        $args = [
            'number'   => $per_page,
            'offset'   => $offset,
            'orderby'  => 'registered',
            'order'    => 'DESC',
            'fields'   => ['ID', 'display_name', 'user_email', 'user_registered'],
        ];

        // Handle search filters
        $search_type = $request->get_param('search_type');
        $search_value = $request->get_param('search_value');

        if ($search_type && $search_value) {
            switch ($search_type) {
                case 'customer_name':
                    $args['search'] = '*' . esc_attr($search_value) . '*';
                    $args['search_columns'] = ['display_name'];
                    break;
                case 'customer_email':
                    $args['search'] = '*' . esc_attr($search_value) . '*';
                    $args['search_columns'] = ['user_email'];
                    break;
                case 'order_id':
                    $order = wc_get_order(absint($search_value));
                    $args['include'] = $order && $order->get_customer_id()
                        ? [$order->get_customer_id()]
                        : [0];
                    break;
            }
        }

        // Filter by registration date range
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

        // Query users
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        $data = [];
        foreach ($users as $user) {
            // Get last order
            $orders = wc_get_orders([
                'customer_id' => $user->ID,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'limit'       => 1,
            ]);

            // Count all orders
            $orders_count = count(wc_get_orders([
                'customer_id' => $user->ID,
                'return'      => 'ids',
            ]));

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

        // Prepare paginated response
        $total = $user_query->get_total();
        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));

        return $response;
    }
}
