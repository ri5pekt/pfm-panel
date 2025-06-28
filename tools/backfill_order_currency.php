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
    file_put_contents(__DIR__ . '/cli_log_currency_patch.log', $line, FILE_APPEND);
}

function get_currency_for_order($order_id) {
    $order = wc_get_order($order_id);

    if ($order instanceof WC_Abstract_Order) {
        // If it's a refund, try parent order
        if ($order instanceof WC_Order_Refund) {
            $parent_order = wc_get_order($order->get_parent_id());
            if ($parent_order instanceof WC_Abstract_Order) {
                return $parent_order->get_currency();
            }
        }

        // Otherwise, return the order's own currency
        return $order->get_currency();
    }

    // Fallback to archive (for really old or archived orders)
    global $wpdb;
    $val = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value FROM yom_archive_orders_postmeta
        WHERE post_id = %d AND meta_key = '_order_currency'
        LIMIT 1
    ", $order_id));

    return maybe_unserialize($val) ?: null;
}


cli_log("🔁 Starting currency backfill...");

$options = getopt('', ['offset::', 'batch-size::']);
$offset = isset($options['offset']) ? (int)$options['offset'] : 0;
$limit  = isset($options['batch-size'])  ? (int)$options['batch-size']  : 100;

// Get orders from stats table
$rows = $wpdb->get_results($wpdb->prepare("
    SELECT order_id, cached_data FROM yom_pfm_orders_for_stats
    WHERE type = 'refund'
    ORDER BY order_id ASC
    LIMIT %d OFFSET %d
", $limit, $offset));

cli_log("📦 Found " . count($rows) . " orders to patch (offset: $offset)");

$patched = 0;

foreach ($rows as $row) {
    $cached = json_decode($row->cached_data, true);
    if (isset($cached['currency'])) continue;

    $currency = get_currency_for_order($row->order_id);
    if (!$currency) {
        cli_log("⚠️ Currency not found for order #{$row->order_id}");
        continue;
    }

    $cached['currency'] = $currency;

    $updated = $wpdb->update(
        'yom_pfm_orders_for_stats',
        ['cached_data' => json_encode($cached)],
        ['order_id' => $row->order_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        $patched++;
        //cli_log("✅ Order #{$row->order_id} updated with currency: $currency");
    } else {
        cli_log("❌ Failed to update order #{$row->order_id}");
    }
}

cli_log("🏁 Done! Total patched: {$patched}");

