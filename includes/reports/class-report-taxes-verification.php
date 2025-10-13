<?php
//class-report-taxes-verification.php

defined('ABSPATH') || exit;

class PFMP_Report_Taxes_Verification {
    public function generate($params) {
        $offset     = intval($params['offset'] ?? 0);
        $limit      = intval($params['chunk_size'] ?? 10);
        $order_ids  = array_map('absint', $params['order_ids'] ?? []);
        $date_from  = $params['date_from'] ?? null;
        $date_to    = $params['date_to'] ?? null;

        if (empty($order_ids) && !$date_from && !$date_to) {
            return new WP_Error('missing_params', 'Please provide either order_ids or date range.');
        }

        if (!empty($order_ids)) {
            $all_ids = array_filter($order_ids, fn($id) => wc_get_order($id));
        } else {
            $args = [
                'type'     => 'shop_order',
                'orderby'  => 'date',
                'order'    => 'DESC',
                'limit'  => -1,
                'return' => 'ids',
                'status' => ['wc-completed', 'wc-processing'],
            ];

            // Date filters


            if ($date_from || $date_to) {
                $from_timestamp = null;
                $to_timestamp = null;

                // Define the offset timezone you're matching (e.g., GMT+4)
                $external_tz = new DateTimeZone('Etc/GMT+4');

                if ($date_from) {
                    $from_dt = new DateTime($date_from . ' 00:00:00', $external_tz);
                    $from_dt->setTimezone(new DateTimeZone('UTC')); // Convert to UTC for Woo query
                    $from_timestamp = $from_dt->getTimestamp();
                }

                if ($date_to) {
                    $to_dt = new DateTime($date_to . ' 23:59:59', $external_tz);
                    $to_dt->setTimezone(new DateTimeZone('UTC')); // Convert to UTC for Woo query
                    $to_timestamp = $to_dt->getTimestamp();
                }

                if ($from_timestamp && $to_timestamp) {
                    $args['date_created'] = "{$from_timestamp}...{$to_timestamp}";
                } elseif ($from_timestamp) {
                    $args['date_created'] = ">={$from_timestamp}";
                } elseif ($to_timestamp) {
                    $args['date_created'] = "<={$to_timestamp}";
                }
            }

            $all_ids = wc_get_orders($args);
        }

        $chunk = array_slice($all_ids, $offset, $limit);
        $output = [];

        if ($offset === 0) {
            $output[] = implode(",", [
                "[Woo] Order Date",
                "[Woo] Order ID",
                "[Woo] Payment Method",
                "[Woo] Payment Method ID",
                "[Woo] Country",
                "[Woo] Shipping State",
                "[Woo] Total",
                "[Woo] Total Tax",
                "[Woo] Total + Tax",
                "#################", // separator
                "[Braintree] Total",
                "[Braintree] Tax",
                "[Braintree] Total + Tax",
                "#################", // separator
                "[Complyt] External ID",
                "[Complyt] Transaction Status",
                "[Complyt] Created Date",
                "[Complyt] Transaction Type",
                "[Complyt] Country",
                "[Complyt] State",
                "[Complyt] Tax Rate",
                "[Complyt] Total Items Amount",
                "[Complyt] Non-Taxable Amount",
                "[Complyt] Currency",
                "[Complyt] Taxable Items Amount",
                "[Complyt] Sales Tax Amount",
                "[Complyt] Total + Tax",
                "#################", // separator
                "#################", // separator
                "[Woo] Total + Tax",
                "[Braintree] Total + Tax",
                "[Complyt] Total + Tax",
            ]);
        }

        $this->configure_braintree();
        $this->maybe_init_complyt();

        foreach ($chunk as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            $shipping = $order->get_address('shipping');
            $country = $shipping['country'] ?? '';

            $woo_payment_method = $order->get_payment_method_title();
            $woo_payment_method_id = $order->get_payment_method();

            if ($country !== 'US' && $country !== 'USA') {
                // Skip non-US orders
                continue;
            }


            $bt_amounts = $this->get_braintree_amounts($order_id);
            $bt_total = $bt_amounts['no_tax'];
            $bt_tax = $bt_amounts['tax'];


            $complyt = $this->get_complyt_data($order_id);

            $created = $order->get_date_created();

            $woo_order_date = $created
                ? $created->setTimezone(new DateTimeZone('Etc/GMT+4'))->format('Y-m-d H:i:s')
                : '';

            $woo_total_no_tax = wc_format_decimal($order->get_total() - $order->get_total_tax());

            $woo_total_and_tax = wc_format_decimal($woo_total_no_tax + $order->get_total_tax(), 2) ?: "0.00";
            $complyt_total_and_tax = wc_format_decimal((float) $complyt['finalTransactionAmount'], 2) ?: "0.00";
            $bt_total_and_tax = wc_format_decimal((float)$bt_total + (float)$bt_tax, 2) ?: "0.00";

            $row = [
                $woo_order_date,
                $order->get_id(),
                $woo_payment_method,
                $woo_payment_method_id,
                $country,
                $shipping['state'] ?? '',
                $woo_total_no_tax,
                wc_format_decimal($order->get_total_tax(), 2),
                $woo_total_and_tax,
                "", // separator
                $bt_total,
                $bt_tax,
                $bt_total_and_tax,
                "", // separator
                $complyt['externalId'],
                $complyt['transactionStatus'],
                $complyt['createdDate'],
                $complyt['transactionType'],
                $complyt['country'],
                $complyt['state'],
                $complyt['taxRate'],
                $complyt['totalItemsAmount'],
                $complyt['nonTaxableAmount'],
                $complyt['currency'],
                $complyt['taxableItemsAmount'],
                $complyt['salesTaxAmount'],
                $complyt_total_and_tax,
                "", // separator
                "", // separator
                $woo_total_and_tax,
                $bt_total_and_tax,
                $complyt_total_and_tax,
            ];

            $output[] = implode(",", array_map([$this, 'escape_csv'], $row));
        }

        return [
            'data'      => $output,
            'remaining' => max(0, count($all_ids) - $offset - $limit),
        ];
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
                'with_tax' => 'Error',
                'tax'      => 'Error',
                'no_tax'   => 'Error',
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


    private function maybe_init_complyt() {
        if (class_exists('WC_Complyt_Tax') && method_exists('WC_Complyt_Tax', 'init')) {
            WC_Complyt_Tax::init();
        }
    }

    private function get_complyt_data($order_id) {
        $result = apply_filters('get_complyt_tax_transaction', $order_id);
        if (!is_array($result)) return array_fill_keys([
            'externalId',
            'transactionStatus',
            'createdDate',
            'transactionType',
            'country',
            'state',
            'taxRate',
            'totalItemsAmount',
            'taxableItemsAmount',
            'salesTaxAmount',
            'currency',
        ], '');

        return [
            'externalId'           => $result['externalId'] ?? '',
            'transactionStatus'   => $result['transactionStatus'] ?? '',
            'createdDate'         => $result['externalTimestamps']['createdDate'] ?? '',
            'transactionType'     => $result['transactionType'] ?? '',
            'country'             => $result['shippingAddress']['country'] ?? '',
            'state'               => $result['shippingAddress']['state'] ?? '',
            'taxRate'             => isset($result['salesTax']['rate']) ? wc_format_decimal($result['salesTax']['rate'] * 100, 2) . '%' : '',
            'totalItemsAmount'    => $result['totalItemsAmount'] ?? '',
            'taxableItemsAmount'  => $result['taxableItemsAmount'] ?? '',
            'salesTaxAmount'      => isset($result['salesTax']['amount']) ? wc_format_decimal($result['salesTax']['amount'], 2) : '',
            'currency'            => $result['currency'] ?? '',
            'finalTransactionAmount' => $result['finalTransactionAmount'] ?? '',
        ];
    }

    private function escape_csv($value) {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
    }
}
