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
    file_put_contents(__DIR__ . '/cli_log_taxes_patch.log', $line, FILE_APPEND);
}

/**
 * Return total order tax for a given order ID.
 * For refunds, returns the parent order's taxes.
 * Falls back to archive table if order not found.
 */
function get_taxes_for_order($order_id) {
    $order = wc_get_order($order_id);

    if ($order instanceof WC_Abstract_Order) {
        // If it's a refund, try parent order
        if ($order instanceof WC_Order_Refund) {
            $parent_order = wc_get_order($order->get_parent_id());
            if ($parent_order instanceof WC_Abstract_Order) {
                return (float)$parent_order->get_total_tax();
            }
        }
        // Otherwise, return the order's own taxes
        return (float)$order->get_total_tax();
    }

    // Fallback to archive (for really old or archived orders)
    global $wpdb;
    $val = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value FROM yom_archive_orders_postmeta
        WHERE post_id = %d AND meta_key = '_order_tax'
        LIMIT 1
    ", $order_id));

    return $val !== null ? (float)maybe_unserialize($val) : null;
}

cli_log("🔁 Starting taxes backfill...");

$options = getopt('', ['offset::', 'batch-size::']);
$offset = isset($options['offset']) ? (int)$options['offset'] : 0;
$limit  = isset($options['batch-size'])  ? (int)$options['batch-size']  : 100;

// Restrict to orders created from 01.01.2025 to 31.01.2025
$start_date = '2025-01-01 00:00:00';
$end_date   = '2025-01-31 23:59:59';

// Get orders from stats table within the date range
$rows = $wpdb->get_results($wpdb->prepare("
    SELECT order_id, cached_data, order_date
    FROM yom_pfm_orders_for_stats
    WHERE order_date >= %s AND order_date <= %s AND type = 'order'
    ORDER BY order_id ASC
    LIMIT %d OFFSET %d
", $start_date, $end_date, $limit, $offset));

cli_log("📦 Found " . count($rows) . " orders to patch (offset: $offset, range: $start_date to $end_date)");

$patched = 0;

foreach ($rows as $row) {
    $cached = json_decode($row->cached_data, true);

    // Skip if taxes already set
    if (isset($cached['taxes'])) continue;

    $taxes = get_taxes_for_order($row->order_id);

    // If tax not found, skip with warning
    if ($taxes === null) {
        cli_log("⚠️ Taxes not found for order #{$row->order_id}");
        continue;
    }

    $cached['taxes'] = $taxes;

    $updated = $wpdb->update(
        'yom_pfm_orders_for_stats',
        ['cached_data' => json_encode($cached)],
        ['order_id' => $row->order_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        $patched++;
        //cli_log("✅ Order #{$row->order_id} updated with taxes: $taxes");
    } else {
        cli_log("❌ Failed to update order #{$row->order_id}");
    }
}

cli_log("🏁 Done! Total patched: {$patched}");