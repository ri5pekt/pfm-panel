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
    file_put_contents(__DIR__ . '/cli_log_quantity_patch.log', $line, FILE_APPEND);
}

cli_log("🔁 Starting quantity backfill...");

$options = getopt('', ['offset::', 'batch-size::']);
$offset = isset($options['offset']) ? (int)$options['offset'] : 0;
$limit  = isset($options['batch-size']) ? (int)$options['batch-size'] : 100;

// Define January date range
$start_date = '2025-01-01 00:00:00';
$end_date   = '2025-01-31 23:59:59';

$rows = $wpdb->get_results($wpdb->prepare("
    SELECT order_id, cached_data, order_date
    FROM yom_pfm_orders_for_stats
    WHERE order_date >= %s AND order_date <= %s AND type = 'order'
    ORDER BY order_id ASC
    LIMIT %d OFFSET %d
", $start_date, $end_date, $limit, $offset));

cli_log("📦 Found " . count($rows) . " orders to patch (offset: $offset)");

$patched = 0;

foreach ($rows as $row) {
    $cached = json_decode($row->cached_data, true);

    if (!isset($cached['items']) || !is_array($cached['items'])) {
        cli_log("⚠️ No items found in order #{$row->order_id}, skipping");
        continue;
    }

    $order = wc_get_order($row->order_id);
    if (!$order) {
        cli_log("❌ Order #{$row->order_id} not found");
        continue;
    }

    $items = [];
    foreach ($order->get_items() as $item) {
        $items[] = [
            'product_id' => $item->get_product_id(),
            'total'      => $item->get_total(),
            'quantity'   => $item->get_quantity(),
            'meta'       => $item->get_meta_data(),
        ];
    }

    $cached['items'] = $items;

    $updated = $wpdb->update(
        'yom_pfm_orders_for_stats',
        ['cached_data' => json_encode($cached)],
        ['order_id' => $row->order_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        $patched++;
    } else {
        cli_log("❌ Failed to update order #{$row->order_id}");
    }
}

cli_log("🏁 Done! Total patched: {$patched}");
