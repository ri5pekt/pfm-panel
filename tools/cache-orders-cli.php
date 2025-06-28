<?php
// 🛡️ CLI only
if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from the CLI.\n");
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;

function cli_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    echo $line;
    file_put_contents(__DIR__ . '/cli_log.log', $line, FILE_APPEND);
}

function pfm_cache_order_to_stats_table($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return false;

    global $wpdb;
    $table = 'yom_pfm_orders_for_stats';

    $fees = array_map(fn($fee) => [
        'name' => $fee->get_name(),
        'total' => $fee->get_total(),
    ], $order->get_fees());

    $shipping = array_map(fn($ship) => [
        'method_id' => $ship->get_method_id(),
        'method_title' => $ship->get_name(),
        'total' => $ship->get_total(),
    ], $order->get_shipping_methods());

    $items = array_map(fn($item) => [
        'product_id' => $item->get_product_id(),
        'total' => $item->get_total(),
        'meta' => $item->get_meta_data(),
    ], $order->get_items());

    $data = [
        'order_id'     => $order->get_id(),
        'order_date'   => $order->get_date_created()->format('Y-m-d H:i:s'),
        'order_status' => $order->get_status(),
        'cached_data'  => json_encode([
            'total'       => $order->get_total(),
            'refunded'    => $order->get_total_refunded(),
            'fees'        => $fees,
            'shipping'    => $shipping,
            'customer_id' => $order->get_customer_id(),
            'meta'        => $order->get_meta_data(),
            'items'       => $items,
        ]),
        'updated_at' => current_time('mysql', 1),
    ];

    return $wpdb->replace($table, $data);
}

// Parse args
$options = getopt('', ['offset::', 'batch-size::']);
$offset = isset($options['offset']) ? (int)$options['offset'] : 0;
$batch_size = isset($options['batch-size']) ? (int)$options['batch-size'] : 100;

cli_log("⚙️ Processing batch at offset $offset (size: $batch_size)");


$order_ids = wc_get_orders([
    'limit'   => $batch_size,
    'offset'  => $offset,
    'return'  => 'ids',
    'orderby' => 'date',
    'order'   => 'ASC',
    'type'    => 'shop_order',
    'status'  => ['refunded', 'on-hold'],
]);

$batch = $order_ids;

cli_log("🚀 Caching orders {$offset} to " . ($offset + count($batch) - 1));

$start = microtime(true);
foreach ($batch as $order_id) {
    pfm_cache_order_to_stats_table($order_id);
}
$elapsed = round(microtime(true) - $start, 2);

cli_log("✅ Done! Batch took {$elapsed}s");
