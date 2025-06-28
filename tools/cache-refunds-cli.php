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
    file_put_contents(__DIR__ . '/cli_refunds_log.log', $line, FILE_APPEND);
}

function pfm_cache_refund_to_stats_table($refund_id) {
    $refund = wc_get_order($refund_id);
    if (!$refund || $refund->get_type() !== 'shop_order_refund') return false;

    global $wpdb;
    $table = 'yom_pfm_orders_for_stats';

    $items = array_map(fn($item) => [
        'product_id' => $item->get_product_id(),
        'total'      => $item->get_total(),
        'meta'       => $item->get_meta_data(),
    ], $refund->get_items());

    $data = [
        'order_id'     => $refund->get_id(),
        'order_date'   => $refund->get_date_created()->format('Y-m-d H:i:s'),
        'order_status' => $refund->get_status(),
        'cached_data'  => json_encode([
            'parent_order_id' => $refund->get_parent_id(),
            'total_refunded'  => $refund->get_total(),
            'reason'          => $refund->get_reason(),
            'items'           => $items,
            'meta'            => $refund->get_meta_data(),
        ]),
        'updated_at' => current_time('mysql', 1),
        'type'       => 'refund',
    ];

    return $wpdb->replace($table, $data);
}

// 🧮 Parse CLI args
$options = getopt('', ['offset::', 'batch-size::']);
$offset = isset($options['offset']) ? (int)$options['offset'] : 0;
$batch_size = isset($options['batch-size']) ? (int)$options['batch-size'] : 100;

cli_log("🔁 Processing refunds batch at offset $offset (size: $batch_size)");

$refund_ids = wc_get_orders([
    'limit'   => $batch_size,
    'offset'  => $offset,
    'type'    => 'shop_order_refund',
    'orderby' => 'date',
    'order'   => 'ASC',
    'return'  => 'ids',
]);

cli_log("📦 Caching refunds {$offset} to " . ($offset + count($refund_ids) - 1));

$start = microtime(true);
foreach ($refund_ids as $refund_id) {
    pfm_cache_refund_to_stats_table($refund_id);
}
$elapsed = round(microtime(true) - $start, 2);

cli_log("✅ Done caching refunds! Batch took {$elapsed}s");
