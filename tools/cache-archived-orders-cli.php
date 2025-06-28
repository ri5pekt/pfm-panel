<?php
// 🛡️ CLI only
if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from the CLI.\n");
}

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;

// === Log helper
function cli_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    echo $line;
    file_put_contents(__DIR__ . '/cli_log_archived.log', $line, FILE_APPEND);
}

// === Helper to get meta by post_id
function get_archived_order_meta($post_id) {
    global $wpdb;
    $meta = [];

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT meta_key, meta_value
        FROM yom_archive_orders_postmeta
        WHERE post_id = %d
    ", $post_id));

    foreach ($rows as $row) {
        $meta[$row->meta_key] = maybe_unserialize($row->meta_value);
    }

    return $meta;
}

// === Helper to get order items
function get_archived_order_items($order_id) {
    global $wpdb;
    $items = [];

    $order_items = $wpdb->get_results($wpdb->prepare("
        SELECT order_item_id, order_item_name, order_item_type
        FROM yom_archive_orders_woocommerce_order_items
        WHERE order_id = %d
    ", $order_id));

    foreach ($order_items as $item) {
        $meta = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value
            FROM yom_archive_orders_woocommerce_order_itemmeta
            WHERE order_item_id = %d
        ", $item->order_item_id));

        $meta_array = [];
        foreach ($meta as $meta_row) {
            $meta_array[$meta_row->meta_key] = maybe_unserialize($meta_row->meta_value);
        }

        $items[] = [
            'name' => $item->order_item_name,
            'type' => $item->order_item_type,
            'meta' => $meta_array,
        ];
    }

    return $items;
}

// === Cache archived order to stats table
function cache_archived_order_to_stats_table($post) {
    global $wpdb;

    $meta = get_archived_order_meta($post->ID);
    $items = get_archived_order_items($post->ID);

    $data = [
        'order_id'     => $post->ID,
        'order_date'   => $post->post_date_gmt,
        'order_status' => $post->post_status,
        'cached_data'  => json_encode([
            'customer_id' => $meta['_customer_user'] ?? 0,
            'total'       => $meta['_order_total'] ?? 0,
            'refunded'    => $meta['_refunded_amount'] ?? 0,
            'fees'        => [], // Archive may not have fees
            'shipping'    => [], // Same for shipping
            'meta'        => $meta,
            'items'       => $items,
        ]),
        'updated_at' => current_time('mysql', 1),
    ];

    return $wpdb->replace('yom_pfm_orders_for_stats', $data);
}

// === CLI options
$options = getopt('', ['offset::', 'batch-size::']);
$offset = isset($options['offset']) ? (int)$options['offset'] : 0;
$batch_size = isset($options['batch-size']) ? (int)$options['batch-size'] : 100;

cli_log("⚙️ Caching archived orders | Offset: $offset | Batch size: $batch_size");

// === Get all archived order IDs
try {
    cli_log("🔍 Fetching archived orders from database (offset: $offset, batch size: $batch_size)...");
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM yom_archive_orders_posts
        WHERE post_type = 'shop_order' AND post_status NOT IN ('trash', 'auto-draft')
        ORDER BY post_date_gmt ASC
        LIMIT %d OFFSET %d
    ", $batch_size, $offset));
} catch (Exception $e) {
    cli_log("🚨 Error: " . $e->getMessage());
    exit(1);
}

cli_log("🚀 Caching archived orders from {$offset} to " . ($offset + count($orders) - 1));


$start = microtime(true);
foreach ($orders as $order_post) {
    cache_archived_order_to_stats_table($order_post);
}
$elapsed = round(microtime(true) - $start, 2);

cli_log("✅ Batch completed in {$elapsed}s");
