<?php
<<<<<<< HEAD
// class-pfmp-admin.php
=======

>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
defined('ABSPATH') || exit;

class PFMP_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_head', [$this, 'clean_admin_panel_if_needed']);
        add_action('add_meta_boxes', [$this, 'add_order_meta_metabox']);
    }

<<<<<<< HEAD
    public static function register_roles() {
        // 🎩 Add or update custom PFM roles
        $roles = [
            'pfm_operator_1',
            'pfm_operator_2',
            'pfm_operator_3',
            'pfm_operator_4',
        ];

        foreach ($roles as $role) {
            // Add role if it doesn't exist
            if (!get_role($role)) {
                add_role($role, ucwords(str_replace('_', ' ', $role)), [
                    'read'             => true,  // Required to access admin
                    'edit_posts'       => true, // Hide post editor
                    'access_pfm_panel' => true,  // Custom panel permission
                ]);
            } else {
                // Role exists, update its capabilities just in case
                $wp_role = get_role($role);
                $wp_role->add_cap('read');
                $wp_role->add_cap('access_pfm_panel');
                $wp_role->add_cap('edit_posts');
            }
        }

        // 🛡️ Make sure admins have the custom panel access too
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('access_pfm_panel')) {
            $admin_role->add_cap('access_pfm_panel');
        }
    }

=======
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9

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
<<<<<<< HEAD
            'access_pfm_panel',
=======
            'manage_woocommerce',
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
            'pfm-panel',
            [$this, 'render_panel_page'],
            'dashicons-admin-generic',
            1
        );

        add_submenu_page(
            'pfm-panel',
            'PFM Tools',
            'Tools',
<<<<<<< HEAD
            'access_pfm_panel',
=======
            'manage_woocommerce',
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
            'pfm-tools',
            [$this, 'render_tools_page']
        );
    }


    public function render_tools_page() {
        $start = microtime(true);
        $output = '';

        // --- Handle by IDs ---
        if (isset($_POST['pfm_tools_ids']) && !empty($_POST['pfm_tools_ids'])) {
            $raw_input = sanitize_textarea_field($_POST['pfm_tools_ids']);
            $ids = preg_split('/[\s,]+/', $raw_input, -1, PREG_SPLIT_NO_EMPTY);

            $output .= '<div class="notice notice-info"><p><strong>Processing ' . count($ids) . ' orders by ID...</strong></p>';

            foreach ($ids as $order_id) {
                $order_id = intval($order_id);
                if (!$order_id) {
                    $output .= "<p>❌ Invalid order ID: $order_id</p>";
                    continue;
                }

                $result = $this->pfm_cache_order_to_stats_table($order_id);
                $output .= "<p>✅ Cached Order #{$order_id}</p>";
            }

            $elapsed = round(microtime(true) - $start, 2);
            $output .= "<p><strong>Done in {$elapsed} seconds.</strong></p></div>";
        }

        // --- Handle by date range ---
        if (!empty($_POST['pfm_date_from']) && !empty($_POST['pfm_date_to'])) {
            $from = sanitize_text_field($_POST['pfm_date_from']);
            $to   = sanitize_text_field($_POST['pfm_date_to']);

            $orders = wc_get_orders([
                'limit'        => -1,
                'type' => 'shop_order',
                'date_created' => $from . '...' . $to,
                'return'       => 'ids',
                'status'       => ['processing', 'completed', 'on-hold'],
            ]);

            $output .= '<div class="notice notice-info"><p><strong>Processing ' . count($orders) . ' orders from ' . esc_html($from) . ' to ' . esc_html($to) . '...</strong></p>';

            foreach ($orders as $order_id) {
                $result = $this->pfm_cache_order_to_stats_table($order_id);
                $output .= "<p>✅ Cached Order #{$order_id}</p>";
            }

            $elapsed = round(microtime(true) - $start, 2);
            $output .= "<p><strong>Done in {$elapsed} seconds.</strong></p></div>";
        }

        // --- Render UI ---
?>
        <div class="wrap">
            <h1>PFM Tools: Cache Orders</h1>

            <h2>1. Cache Orders by ID</h2>
            <form method="post">
                <textarea name="pfm_tools_ids" rows="8" style="width:100%;" placeholder="Enter order IDs (comma or newline)..."></textarea>
                <p><button type="submit" class="button button-primary">Cache by ID</button></p>
            </form>

            <hr>

            <h2>2. Cache Orders by Date Range</h2>
            <form method="post">
                <label for="pfm_date_from">From:</label>
                <input type="date" name="pfm_date_from" required>
                <label for="pfm_date_to">To:</label>
                <input type="date" name="pfm_date_to" required>
                <p><button type="submit" class="button button-primary">Cache by Date Range</button></p>
            </form>

            <hr>

            <?= $output ?>
        </div>
<?php


        global $wpdb;
        $orders = $wpdb->get_results("
        SELECT *
        FROM yom_archive_orders_posts
        WHERE post_type = 'shop_order' AND post_status NOT IN ('trash', 'auto-draft')
        ORDER BY post_date_gmt ASC
    ");

        $total = count($orders);
        echo "<div class='notice notice-info'><p><strong>Total archived orders found: {$total}</strong></p></div>";
    }



    function pfm_cache_order_to_stats_table($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        global $wpdb;
        $table = 'yom_pfm_orders_for_stats';

        $fees = array_map(function ($fee) {
            return [
                'name'  => $fee->get_name(),
                'total' => $fee->get_total(),
            ];
        }, $order->get_fees());

        $shipping_lines = array_map(function ($ship) {
            return [
                'method_id' => $ship->get_method_id(),
                'method_title' => $ship->get_name(),
                'total' => $ship->get_total(),
            ];
        }, $order->get_shipping_methods());

        $items = array_map(function ($item) {
            return [
                'product_id' => $item->get_product_id(),
                'total'      => $item->get_total(),
                'meta'       => $item->get_meta_data(),
            ];
        }, $order->get_items());

        $data = [
            'order_id'      => $order->get_id(),
            'order_date'    => $order->get_date_created()->format('Y-m-d H:i:s'),
            'order_status'  => $order->get_status(),
            'cached_data'   => json_encode([
                'total'         => $order->get_total(),
                'refunded'      => $order->get_total_refunded(),
                'fees'          => $fees,
                'shipping'      => $shipping_lines,
                'customer_id'   => $order->get_customer_id(),
                'meta'          => $order->get_meta_data(),
                'items'         => $items,
            ]),
            'updated_at'    => current_time('mysql', 1),
        ];

        return $wpdb->replace($table, $data);
    }

    public function render_panel_page() {
        echo '<div id="pfm-panel-app">Loading PFM Panel...</div>';
    }

    public function enqueue_assets($hook) {
        // Only enqueue if we are on the correct admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'pfm-panel') {
            return;
        }

<<<<<<< HEAD
        $current_user = wp_get_current_user();
        $user_info = [
            'first_name' => $current_user->user_firstname,
            'last_name'  => $current_user->user_lastname,
            'full_name'  => $current_user->display_name,
            'roles'      => $current_user->roles,
        ];


=======
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
        $nonce = wp_create_nonce('wp_rest');

        wp_enqueue_script(
            'pfm-panel-js',
<<<<<<< HEAD
            plugin_dir_url(dirname(__FILE__)) . 'dist/assets/app.js',
            [],
            filemtime(plugin_dir_url(dirname(__FILE__)) . 'dist/assets/app.js'),
=======
            plugin_dir_url(__FILE__) . 'dist/assets/app.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'dist/assets/app.js'),
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
            true
        );


        wp_localize_script('pfm-panel-js', 'PFMPanelData', [
            'restUrl' => rest_url('pfm-panel/v1/orders'),
<<<<<<< HEAD
            'nonce'   => $nonce,
            'user'    => $user_info,
=======
            'nonce'   => $nonce
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
        ]);

        wp_enqueue_style(
            'pfm-panel-css',
            plugin_dir_url(__FILE__) . 'dist/assets/app.css',
            [],
<<<<<<< HEAD
            filemtime(plugin_dir_url(dirname(__FILE__)) . 'dist/assets/app.css')
=======
            filemtime(plugin_dir_path(__FILE__) . 'dist/assets/app.css')
>>>>>>> f5b007a70eb1c253267053db148c7ed2e27286a9
        );
    }
}
