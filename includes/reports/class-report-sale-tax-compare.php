<?php
//class-report-sale-tax-compare.php

defined('ABSPATH') || exit;

class PFMP_Report_Sale_Tax_Compare {
    private $csv_rows = [];
    private $csv_headers = [];
    private $external_id_index = null;

    public function generate($params) {
        $offset     = intval($params['offset'] ?? 0);
        $limit      = intval($params['chunk_size'] ?? 10);
        $file_content = $params['file_content'] ?? '';

        if (empty($file_content)) {
            return new WP_Error('missing_file', 'Please provide CSV file content.');
        }

        // Parse CSV on every request (PHP doesn't maintain state)
        $this->parse_csv($file_content);

        // Get chunk of rows to process
        $chunk = array_slice($this->csv_rows, $offset, $limit);
        $output = [];

        // Add headers on first chunk only
        if ($offset === 0) {
            $headers = $this->csv_headers;
            // Add separator and new columns after existing headers
            $headers[] = '#####';
            $headers[] = '[Woo] Tax';
            $headers[] = '[Woo] Total + Tax';
            $headers[] = '[Braintree] Tax';
            $headers[] = '[Braintree] Total + Tax';
            $output[] = implode(",", array_map([$this, 'escape_csv'], $headers));
        }

        $this->configure_braintree();

        foreach ($chunk as $row) {
            $processed_row = $this->process_row($row);
            $output[] = implode(",", array_map([$this, 'escape_csv'], $processed_row));
        }

        return [
            'data'      => $output,
            'remaining' => max(0, count($this->csv_rows) - $offset - $limit),
        ];
    }

    private function parse_csv($content) {
        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Use fgetcsv for proper CSV parsing
        $temp_file = tmpfile();
        fwrite($temp_file, $content);
        rewind($temp_file);

        // Read header row
        $this->csv_headers = fgetcsv($temp_file);
        if (empty($this->csv_headers)) {
            fclose($temp_file);
            return;
        }

        // Find externalId column index
        $this->external_id_index = array_search('externalId', $this->csv_headers);
        if ($this->external_id_index === false) {
            // Try alternative names
            $this->external_id_index = array_search('external_id', $this->csv_headers);
        }

        // Parse data rows
        while (($row = fgetcsv($temp_file)) !== false) {
            if (empty($row) || (count($row) === 1 && empty(trim($row[0])))) {
                continue;
            }
            // Pad row to match header count
            while (count($row) < count($this->csv_headers)) {
                $row[] = '';
            }
            $this->csv_rows[] = $row;
        }

        fclose($temp_file);
    }

    private function process_row($row) {
        $external_id = '';
        if ($this->external_id_index !== false && isset($row[$this->external_id_index])) {
            $external_id = trim($row[$this->external_id_index]);
        }

        $woo_tax = '';
        $woo_total_plus_tax = '';
        $bt_tax = '';
        $bt_total_plus_tax = '';

        if (!empty($external_id) && is_numeric($external_id)) {
            $order_id = absint($external_id);
            $order = wc_get_order($order_id);

            if ($order) {
                // Calculate WooCommerce tax and total
                $woo_total_tax = $order->get_total_tax();
                $woo_total_no_tax = $order->get_total() - $woo_total_tax;
                $woo_tax = wc_format_decimal($woo_total_tax, 2) ?: "0.00";
                $woo_total_plus_tax = wc_format_decimal($order->get_total(), 2) ?: "0.00";

                // Get Braintree amounts
                $bt_amounts = $this->get_braintree_amounts($order_id);
                $bt_tax = $bt_amounts['tax'] ?: "0.00";
                $bt_total = $bt_amounts['no_tax'] ?: "0.00";
                $bt_total_plus_tax = wc_format_decimal((float)$bt_total + (float)$bt_tax, 2) ?: "0.00";
            }
        }

        // Return original row plus new columns
        $result = $row;
        $result[] = '#####'; // separator column
        $result[] = $woo_tax;
        $result[] = $woo_total_plus_tax;
        $result[] = $bt_tax;
        $result[] = $bt_total_plus_tax;

        return $result;
    }

    private function get_braintree_amounts($order_id) {
        $total_with_tax    = 0.00;
        $total_tax         = 0.00;
        $total_without_tax = 0.00;

        try {
            $order_id_str = (string) $order_id;
            $order        = wc_get_order($order_id);

            // Start with the original ID
            $all_order_ids = [$order_id_str];

            // Check for PPU meta field
            $ppu_count = intval($order->get_meta('ppu_products_count'));
            $items_count = $order->get_item_count();
            $non_ppu_count = $items_count - $ppu_count;

            if ($ppu_count > 0) {
                for ($i = $non_ppu_count; $i <= $items_count; $i++) {
                    $all_order_ids[] = $order_id_str . '-' . $i;
                }
            }

            $valid_statuses = ['settled', 'submitted_for_settlement', 'settling'];

            foreach ($all_order_ids as $braintree_id) {
                $collection = \Braintree\Transaction::search([
                    \Braintree\TransactionSearch::orderId()->is($braintree_id),
                ]);

                foreach ($collection as $tx) {
                    if ($tx->type === 'sale' && in_array($tx->status, $valid_statuses, true)) {
                        $amount = floatval($tx->amount);
                        $tax    = isset($tx->taxAmount) ? floatval($tx->taxAmount) : 0.00;
                        $net    = $amount - $tax;

                        $total_with_tax    += $amount;
                        $total_tax         += $tax;
                        $total_without_tax += $net;
                    }
                }
            }

            return [
                'with_tax' => wc_format_decimal($total_with_tax, 2),
                'tax'      => $total_tax > 0 ? wc_format_decimal($total_tax, 2) : '',
                'no_tax'   => wc_format_decimal($total_without_tax, 2),
            ];
        } catch (Exception $e) {
            PFMP_Utils::log("Error fetching Braintree amounts: " . $e->getMessage());
            return [
                'with_tax' => '',
                'tax'      => '',
                'no_tax'   => '',
            ];
        }
    }

    private function configure_braintree() {
        $cfg = get_option('woocommerce_braintree_api_settings');
        if (!$cfg) return;

        $env = $cfg['environment'];
        \Braintree\Configuration::environment($env);
        \Braintree\Configuration::merchantId($cfg[$env . '_merchant_id']);
        \Braintree\Configuration::publicKey($cfg[$env . '_public_key']);
        \Braintree\Configuration::privateKey($cfg[$env . '_private_key']);
    }

    private function escape_csv($value) {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
    }
}
