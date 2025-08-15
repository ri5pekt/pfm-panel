<?php
// class-pfmp-admin.php
defined('ABSPATH') || exit;

class PFMP_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_head', [$this, 'clean_admin_panel_if_needed']);
        add_action('add_meta_boxes', [$this, 'add_order_meta_metabox']);

        add_action('admin_init', [$this, 'redirect_richpanel_links'], 20);
        add_filter('user_has_cap', [$this, 'maybe_redirect_on_denied_wc_order_cap'], 100, 3);
        add_filter('login_redirect', [$this, 'redirect_pfm_operators_after_login'], 10, 3);
    }


    public function redirect_richpanel_links() {
        if (!is_admin() || headers_sent()) return;

        // WooCommerce order edit page URL format:
        if (
            isset($_GET['page'], $_GET['action'], $_GET['id']) &&
            $_GET['page'] === 'wc-orders' &&
            $_GET['action'] === 'edit' &&
            current_user_can('access_pfm_panel')
        ) {
            // Check referrer
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'app.richpanel.com') !== false) {
                $target_id = intval($_GET['id']);
                $redirect_url = admin_url("admin.php?page=pfm-panel#/orders/{$target_id}");
                wp_redirect($redirect_url);
                exit;
            }
        }
    }

    public function redirect_pfm_operators_after_login($redirect_to, $request, $user) {
        $roles = [
            'pfm_operator_1',
            'pfm_operator_2',
            'pfm_operator_3',
            'pfm_operator_4',
        ];

        if (is_a($user, 'WP_User')) {
            foreach ($roles as $role) {
                if (in_array($role, (array) $user->roles, true)) {
                    return admin_url('admin.php?page=pfm-panel');
                }
            }
        }

        return $redirect_to;
    }


    public function maybe_redirect_on_denied_wc_order_cap($allcaps, $caps, $args) {
        static $already_redirected = false;

        if ($already_redirected || !is_admin() || headers_sent()) return $allcaps;

        if (
            isset($caps[0]) &&
            in_array($caps[0], ['edit_shop_order', 'edit_shop_orders', 'edit_others_shop_orders']) &&
            empty($allcaps[$caps[0]]) &&
            current_user_can('access_pfm_panel')
        ) {
            if (
                isset($_GET['page'], $_GET['action'], $_GET['id']) &&
                $_GET['page'] === 'wc-orders' &&
                $_GET['action'] === 'edit'
            ) {
                $target_id = intval($_GET['id']);
                $redirect_url = admin_url("admin.php?page=pfm-panel#/orders/{$target_id}");
                PFMP_Utils::log("Redirecting to {$redirect_url}");

                $already_redirected = true;
                wp_redirect($redirect_url);
                exit;
            }
        }

        return $allcaps;
    }

    public static function register_roles() {
        // 🎩 Add or update custom PFM roles
        $roles = [
            'pfm_operator_1',
            'pfm_operator_2',
            'pfm_operator_3',
            'pfm_operator_4',
        ];

        foreach ($roles as $role) {
            $caps = [
                'read'             => true,  // Required to access admin
                'edit_posts'       => true,  // Hide post editor
                'access_pfm_panel' => true,  // Custom panel permission
            ];

            // Only pfm_operator_2, 3, 4 get user switch and edit caps
            if (in_array($role, ['pfm_operator_2', 'pfm_operator_3', 'pfm_operator_4'])) {
                $caps['switch_to_user'] = true;
                $caps['edit_users'] = true;
            }

            // Add role if it doesn't exist
            if (!get_role($role)) {
                add_role($role, ucwords(str_replace('_', ' ', $role)), $caps);
            } else {
                // Role exists, update its capabilities just in case
                $wp_role = get_role($role);
                $wp_role->add_cap('read');
                $wp_role->add_cap('edit_posts');
                $wp_role->add_cap('access_pfm_panel');
                if (in_array($role, ['pfm_operator_2', 'pfm_operator_3', 'pfm_operator_4'])) {
                    $wp_role->add_cap('switch_to_user');
                    $wp_role->add_cap('edit_users');
                } else {
                    $wp_role->remove_cap('switch_to_user');
                    $wp_role->remove_cap('edit_users');
                }
            }
        }

        // 🛡️ Make sure admins have the custom panel access too
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('access_pfm_panel')) {
            $admin_role->add_cap('access_pfm_panel');
        }
    }


    public function add_order_meta_metabox() {
        $screen = class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'full_order_meta_box',
            __('🔍 All Order Meta', 'your-textdomain'),
            [$this, 'render_order_meta_metabox'],
            $screen,
            'normal',
            'default'
        );
    }


    public function render_order_meta_metabox($post) {
        $order = wc_get_order($post->ID);

        if (!$order) {
            echo '<p>No order found.</p>';
            return;
        }

        $meta_data = $order->get_meta_data();

        if (empty($meta_data)) {
            echo '<p><em>No metadata found.</em></p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Meta Key</th><th>Meta Value</th></tr></thead><tbody>';

        foreach ($meta_data as $meta) {
            $key = esc_html($meta->key);
            $value = $meta->value;
            $value_output = is_scalar($value)
                ? esc_html($value)
                : '<pre style="white-space:pre-wrap;word-wrap:break-word; max-height:200px; overflow:auto;">' . print_r($value, true) . '</pre>';

            echo "<tr><td style='font-weight:bold;'>{$key}</td><td>{$value_output}</td></tr>";
        }

        echo '</tbody></table>';
    }


    public function clean_admin_panel_if_needed() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'pfm-panel') {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        add_filter('admin_footer_text', '__return_empty_string', 11);
        add_filter('update_footer', '__return_empty_string', 11);
    }

    public function add_admin_menu() {
        add_menu_page(
            'PFM Panel',
            'PFM Panel',
            'access_pfm_panel',
            'pfm-panel',
            [$this, 'render_panel_page'],
            'dashicons-admin-generic',
            1
        );



        // Fake submenu links with different hashes (Vue will handle it)
        add_submenu_page(
            'pfm-panel',
            'Orders',
            'Orders',
            'access_pfm_panel',
            'pfm-orders',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=pfm-panel#/orders'));
                exit;
            }
        );

        add_submenu_page(
            'pfm-panel',
            'Subscriptions',
            'Subscriptions',
            'access_pfm_panel',
            'pfm-subscriptions',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=pfm-panel#/subscriptions'));
                exit;
            }
        );

        add_submenu_page(
            'pfm-panel',
            'Customers',
            'Customers',
            'access_pfm_panel',
            'pfm-customers',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=pfm-panel#/customers'));
                exit;
            }
        );

        add_submenu_page(
            'pfm-panel',
            'Replacements',
            'Replacements',
            'access_pfm_panel',
            'pfm-replacements',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=pfm-panel#/replacements'));
                exit;
            }
        );

        add_submenu_page(
            'pfm-panel',
            'Reports',
            'Reports',
            'access_pfm_panel',
            'pfm-reports',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=pfm-panel#/reports'));
                exit;
            }
        );


        // Primary tools page
        add_submenu_page(
            'pfm-panel',
            'PFM Tools',
            'Tools',
            'access_pfm_panel',
            'pfm-tools',
            [$this, 'render_tools_page']
        );
    }


    public function render_tools_page() {
?>
        <div class="wrap">
            <h1>PFM Tools</h1>

            <h2>Create Replacement Order</h2>
            <form method="post">
                <?php wp_nonce_field('create_replacement_order_action', 'create_replacement_order_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="original_order_id">Original Woo Order ID</label></th>
                        <td><input name="original_order_id" type="number" id="original_order_id" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('Create Replacement Order'); ?>
            </form>
            <?php
            // Handle form submission
            if (
                isset($_POST['original_order_id'], $_POST['create_replacement_order_nonce']) &&
                wp_verify_nonce($_POST['create_replacement_order_nonce'], 'create_replacement_order_action')
            ) {
                $order_id = absint($_POST['original_order_id']);
                $replacement = wro_create_from_wc_order($order_id, get_current_user_id());

                if ($replacement) {
                    echo '<div class="notice notice-success"><p>Replacement order created! ID: ' . esc_html($replacement->id) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to create replacement order. Invalid order ID?</p></div>';
                }
            }

            if (
                isset($_POST['replacement_order_id'], $_POST['send_to_aftership_nonce']) &&
                wp_verify_nonce($_POST['send_to_aftership_nonce'], 'send_to_aftership_action')
            ) {
                $replacement_id = absint($_POST['replacement_order_id']);

                $result = $this->send_replacement_to_aftership($replacement_id);

                if ($result === true) {
                    echo '<div class="notice notice-success"><p>Replacement order sent to AfterShip!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to send to AfterShip: ' . esc_html($result) . '</p></div>';
                }
            }
            ?>

            <h2>Send Replacement Order to AfterShip</h2>
            <form method="post">
                <?php wp_nonce_field('send_to_aftership_action', 'send_to_aftership_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="replacement_order_id">Replacement Order ID</label></th>
                        <td><input name="replacement_order_id" type="number" id="replacement_order_id" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('Send to AfterShip'); ?>
            </form>




            <h2>Check Complyt Tax Transaction</h2>
            <form method="post">
                <?php wp_nonce_field('check_complyt_tax_action', 'check_complyt_tax_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="complyt_order_id">Order ID</label></th>
                        <td><input name="complyt_order_id" type="number" id="complyt_order_id" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('Get Complyt Tax Info'); ?>
            </form>
            <?php
            if (
                isset($_POST['complyt_order_id'], $_POST['check_complyt_tax_nonce']) &&
                wp_verify_nonce($_POST['check_complyt_tax_nonce'], 'check_complyt_tax_action')
            ) {
                $order_id = absint($_POST['complyt_order_id']);
                $result = apply_filters('get_complyt_tax_transaction', $order_id);
                var_dump($result);
                echo '<h3>🧾 Complyt Tax Result</h3>';
                echo '<pre style="background:#f6f6f6; border:1px solid #ccc; padding:1em; overflow:auto;">' .
                    esc_html(print_r($result, true)) .
                    '</pre>';
            }
            ?>
            <h2>Update Subscription Meta</h2>
            <form method="post">
                <?php wp_nonce_field('update_subscription_meta_action', 'update_subscription_meta_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="subscription_ids">Subscription IDs</label></th>
                        <td>
                            <textarea name="subscription_ids" id="subscription_ids" rows="5" class="large-text code" placeholder="1212, 2323&#10;3434 4545"></textarea>
                            <p class="description">Separate with commas, spaces, or line breaks.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="subscription_meta_key">Meta Key</label></th>
                        <td><input type="text" name="subscription_meta_key" id="subscription_meta_key" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="subscription_meta_value">Meta Value</label></th>
                        <td><input type="text" name="subscription_meta_value" id="subscription_meta_value" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('Update Subscription Meta'); ?>
            </form>
            <?php
            if (
                isset($_POST['subscription_ids'], $_POST['subscription_meta_key'], $_POST['subscription_meta_value'], $_POST['update_subscription_meta_nonce']) &&
                wp_verify_nonce($_POST['update_subscription_meta_nonce'], 'update_subscription_meta_action')
            ) {
                $raw_ids = $_POST['subscription_ids'];
                $meta_key = sanitize_text_field($_POST['subscription_meta_key']);
                $meta_value = sanitize_text_field($_POST['subscription_meta_value']);

                $id_list = preg_split('/[\s,]+/', $raw_ids);
                $id_list = array_filter(array_map('absint', $id_list));

                $updated = 0;
                $skipped = 0;

                foreach ($id_list as $sub_id) {
                    $subscription = wcs_get_subscription($sub_id);
                    if ($subscription) {
                        $subscription->update_meta_data($meta_key, $meta_value);
                        $subscription->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                echo '<div class="notice notice-success"><p>';
                echo "Updated <strong>{$updated}</strong> subscriptions. ";
                if ($skipped > 0) {
                    echo "<strong>{$skipped}</strong> were invalid or not found.";
                }
                echo '</p></div>';
            }
            ?>


            <h2>Bulk Add Order Meta</h2>
            <form method="post">
                <?php wp_nonce_field('bulk_add_order_meta_action', 'bulk_add_order_meta_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bulk_order_ids">Order IDs</label></th>
                        <td>
                            <textarea name="bulk_order_ids" id="bulk_order_ids" rows="5" class="large-text code" placeholder="12345, 12346 12347&#10;12348"></textarea>
                            <p class="description">Separate with commas, spaces, or line breaks.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk_meta_key">Meta Key</label></th>
                        <td><input type="text" name="bulk_meta_key" id="bulk_meta_key" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bulk_meta_value">Meta Value</label></th>
                        <td><input type="text" name="bulk_meta_value" id="bulk_meta_value" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('Update Meta'); ?>
            </form>



            <h2>🔍 View Subscription Meta</h2>
            <form method="post">
                <?php wp_nonce_field('view_subscription_meta_action', 'view_subscription_meta_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="subscription_meta_id">Subscription ID</label></th>
                        <td><input name="subscription_meta_id" type="number" id="subscription_meta_id" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('View Meta'); ?>
            </form>
            <?php

            if (
                isset($_POST['subscription_meta_id'], $_POST['view_subscription_meta_nonce']) &&
                wp_verify_nonce($_POST['view_subscription_meta_nonce'], 'view_subscription_meta_action')
            ) {
                $subscription_id = absint($_POST['subscription_meta_id']);
                $subscription = wcs_get_subscription($subscription_id);

                if (!$subscription) {
                    echo '<div class="notice notice-error"><p>Invalid subscription ID.</p></div>';
                } else {
                    $meta_data = $subscription->get_meta_data();

                    if (empty($meta_data)) {
                        echo '<p><em>No metadata found for this subscription.</em></p>';
                    } else {
                        echo '<h3>🔍 Meta for Subscription ID: ' . esc_html($subscription_id) . '</h3>';
                        echo '<table class="widefat striped">';
                        echo '<thead><tr><th>Meta Key</th><th>Meta Value</th></tr></thead><tbody>';

                        foreach ($meta_data as $meta) {
                            $key = esc_html($meta->key);
                            $value = $meta->value;
                            $value_output = is_scalar($value)
                                ? esc_html($value)
                                : '<pre style="white-space:pre-wrap;word-wrap:break-word; max-height:200px; overflow:auto;">' . print_r($value, true) . '</pre>';

                            echo "<tr><td style='font-weight:bold;'>{$key}</td><td>{$value_output}</td></tr>";
                        }

                        echo '</tbody></table>';
                    }
                }
            }
            ?>
        </div>

<?php
        if (
            isset($_POST['bulk_order_ids'], $_POST['bulk_meta_key'], $_POST['bulk_meta_value'], $_POST['bulk_add_order_meta_nonce']) &&
            wp_verify_nonce($_POST['bulk_add_order_meta_nonce'], 'bulk_add_order_meta_action')
        ) {
            $raw_ids = $_POST['bulk_order_ids'];
            $meta_key = sanitize_text_field($_POST['bulk_meta_key']);
            $meta_value = sanitize_text_field($_POST['bulk_meta_value']);

            // Normalize IDs
            $id_list = preg_split('/[\s,]+/', $raw_ids);
            $id_list = array_filter(array_map('absint', $id_list));

            $updated = 0;
            $skipped = 0;

            foreach ($id_list as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_meta_data($meta_key, $meta_value);
                    $order->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            }

            echo '<div class="notice notice-success"><p>';
            echo "Updated <strong>{$updated}</strong> orders. ";
            if ($skipped > 0) {
                echo "<strong>{$skipped}</strong> orders were invalid or not found.";
            }
            echo '</p></div>';
        }
    }


    private function send_replacement_to_aftership($replacement_id) {
        $replacement = wro_get_order($replacement_id);
        if (!$replacement) return 'Invalid replacement order ID.';

        $tracking_items = $replacement->get_meta('_aftership_tracking_items');

        $first_item = $tracking_items[0];
        $tracking_number = $first_item['tracking_number'] ?? '';
        $slug = $first_item['slug'] ?: 'dhl';

        if (!$tracking_number) {
            return 'Missing tracking number.';
        }

        $destination_country_raw = $first_item['additional_fields']['destination_country'] ?? 'US';
        $destination_country = strtoupper($destination_country_raw) === 'US' ? 'USA' : $destination_country_raw;
        $destination_state = $first_item['additional_fields']['state'] ?? '';
        $destination_postal = $first_item['additional_fields']['postal_code'] ?? '';
        $order_number = $replacement->get_order_number();

        // Build product summary string
        $items = $replacement->get_items();
        $product_descriptions = [];
        $total_items = 0;

        foreach ($items as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $name = $product->get_name();
            $qty = $item->get_quantity();
            $total_items++;

            $product_descriptions[] = "{$name} x{$qty}";
        }

        $product_string = !empty($product_descriptions) ? implode(', ', $product_descriptions) : 'Unknown Product';

        // Build payload
        $payload = [
            'tracking' => [
                //'slug' => $slug,
                'tracking_number' => $tracking_number,
                'title' => 'Replacement Order',
                'order_id' => (string) $replacement_id,
                'order_number' => $order_number,
                'emails' => [$replacement->get_billing_email()],
                'smses' => [$replacement->get_billing_phone()],
                'custom_fields' => [
                    'products' => $product_string,
                    'total_items' => (string) $total_items,
                ],
                'language' => 'en',
                'destination_country_iso3' => $destination_country,
                'destination_state' => $destination_state,
                'destination_postal_code' => $destination_postal,
                'destination_raw_location' => $this->get_formatted_shipping_address_for_order($replacement),
            ]
        ];

        // Echo payload for review
        echo '<h3>📦 Sending This Payload to AfterShip:</h3>';
        echo '<pre style="background:#f5f5f5; padding:1em; border:1px solid #ccc; overflow:auto; max-height:400px;">' .
            esc_html(json_encode($payload, JSON_PRETTY_PRINT)) .
            '</pre>';

        // Send the request
        $response = wp_remote_post('https://api.aftership.com/v4/trackings', [
            'headers' => [
                'Content-Type' => 'application/json',
                'as-api-key'   => 'asat_3764419c24b843cb9640e15238da3901',
            ],
            'body' => json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return 'Request failed: ' . $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        echo '<h3>📬 AfterShip Response:</h3>';
        echo '<pre style="background:#eef9ff; padding:1em; border:1px solid #88c0f0; overflow:auto; max-height:500px;">' .
            esc_html(json_encode(json_decode($body, true), JSON_PRETTY_PRINT)) .
            '</pre>';

        return "AfterShip responded with status {$code}.";
    }


    private function get_formatted_shipping_address_for_order($order) {
        return implode(', ', array_filter([
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_city(),
            $order->get_shipping_state(),
            $order->get_shipping_postcode(),
            $order->get_shipping_country(),
        ]));
    }

    public function render_panel_page() {
        echo '<div id="pfm-panel-app">Loading PFM Panel...</div>';
    }

    public function enqueue_assets($hook) {
        // Only enqueue if we are on the correct admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'pfm-panel') {
            return;
        }

        $current_user = wp_get_current_user();
        $user_info = [
            'first_name' => $current_user->user_firstname,
            'last_name'  => $current_user->user_lastname,
            'full_name'  => $current_user->display_name,
            'roles'      => $current_user->roles,
        ];


        $nonce = wp_create_nonce('wp_rest');

        wp_enqueue_script(
            'pfm-panel-js',
            plugin_dir_url(dirname(__FILE__)) . 'dist/assets/app.js',
            [],
            filemtime(plugin_dir_url(dirname(__FILE__)) . 'dist/assets/app.js'),
            true
        );


        wp_localize_script('pfm-panel-js', 'PFMPanelData', [
            'restUrl' => rest_url('pfm-panel/v1/'),
            'nonce'   => $nonce,
            'user'    => $user_info,
        ]);

        wp_enqueue_style(
            'pfm-panel-css',
            plugin_dir_url(__FILE__) . 'dist/assets/app.css',
            [],
            filemtime(plugin_dir_url(dirname(__FILE__)) . 'dist/assets/app.css')
        );
    }
}
