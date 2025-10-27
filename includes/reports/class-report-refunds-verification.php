<?php
// includes/reports/class-report-refunds-verification.php
defined('ABSPATH') || exit;

class PFMP_Report_Refunds_Verification {
    public function generate($params) {
        $offset     = intval($params['offset'] ?? 0);
        $limit      = intval($params['chunk_size'] ?? 10);
        $order_ids  = array_map('absint', $params['order_ids'] ?? []);
        $date_from  = $params['date_from'] ?? null;
        $date_to    = $params['date_to'] ?? null;

        if (empty($order_ids) && !$date_from && !$date_to) {
            return new WP_Error('missing_params', 'Please provide either order_ids or date range.');
        }

        // Build list of REFUND IDs to process
        if (!empty($order_ids)) {
            $valid_parent_ids = array_values(array_filter($order_ids, fn($id) => wc_get_order($id)));
            if (empty($valid_parent_ids)) {
                return [
                    'data'      => [$this->csv_header()],
                    'remaining' => 0,
                ];
            }
            $all_ids = wc_get_orders([
                'type'    => 'shop_order_refund',
                'parent'  => $valid_parent_ids,
                'orderby' => 'date',
                'order'   => 'DESC',
                'limit'   => -1,
                'return'  => 'ids',
            ]);
        } else {
            $args = [
                'type'    => 'shop_order_refund',
                'orderby' => 'date',
                'order'   => 'DESC',
                'limit'   => -1,
                'return'  => 'ids',
            ];
            if ($date_from || $date_to) {
                $from_ts = null;
                $to_ts = null;
                $ext_tz = new DateTimeZone('Etc/GMT+4');
                if ($date_from) {
                    $dt = new DateTime($date_from . ' 00:00:00', $ext_tz);
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $from_ts = $dt->getTimestamp();
                }
                if ($date_to) {
                    $dt = new DateTime($date_to . ' 23:59:59', $ext_tz);
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $to_ts = $dt->getTimestamp();
                }
                if ($from_ts && $to_ts)       $args['date_created'] = "{$from_ts}...{$to_ts}";
                elseif ($from_ts)             $args['date_created'] = ">={$from_ts}";
                elseif ($to_ts)               $args['date_created'] = "<={$to_ts}";
            }
            $all_ids = wc_get_orders($args);
        }

        $chunk  = array_slice($all_ids, $offset, $limit);
        $output = [];
        if ($offset === 0) $output[] = $this->csv_header();

        $this->configure_braintree();
        $this->maybe_init_complyt();

        foreach ($chunk as $refund_id) {
            $refund = wc_get_order($refund_id);
            if (!$refund || !($refund instanceof WC_Order_Refund)) continue;

            $parent = wc_get_order($refund->get_parent_id());
            if (!$parent) continue;

            $shipping = $parent->get_address('shipping');
            $country  = $shipping['country'] ?? '';
            // Keep parity with taxes report (US-only). Remove if you need global.
            if ($country !== 'US' && $country !== 'USA') continue;

            $woo_pm_title = $parent->get_payment_method_title();
            $woo_pm_id    = $parent->get_payment_method();

            $created = $refund->get_date_created();
            $woo_refund_date = $created ? $created->setTimezone(new DateTimeZone('Etc/GMT+4'))->format('Y-m-d H:i:s') : '';

            // Woo (refunds are negative -> show absolute magnitudes)
            $woo_tax     = (float) $refund->get_total_tax();
            $woo_no_tax  = (float) ($refund->get_total() - $refund->get_total_tax());
            $woo_no_tax_f   = wc_format_decimal(abs($woo_no_tax), 2);
            $woo_tax_f      = wc_format_decimal(abs($woo_tax), 2);
            $woo_gross_f    = wc_format_decimal(abs((float)$refund->get_total()), 2);

            // Braintree credits for this parent (incl. PPU suffixes)
            $bt = $this->get_braintree_refund_amounts($parent, $refund);
            $bt_no_tax = $bt['no_tax'];
            $bt_tax    = $bt['tax'];
            $bt_gross  = $bt['with_tax'];

            // Complyt refund transaction
            $comp = $this->get_complyt_refund_data((int)$parent->get_id(), (int)$refund->get_id());
            $comp_gross = wc_format_decimal((float)($comp['finalTransactionAmount'] ?? 0), 2) ?: '0.00';

            $row = [
                // Woo Refund
                $woo_refund_date,
                $refund->get_id(),
                $parent->get_id(),
                $woo_pm_title,
                $woo_pm_id,
                $country,
                $shipping['state'] ?? '',
                $woo_no_tax_f,
                $woo_tax_f,
                $woo_gross_f,
                "",

                // Braintree Refund
                $bt_no_tax,
                $bt_tax,
                $bt_gross,
                "",

                // Complyt Refund
                $comp['externalId'] ?? '',
                $comp['transactionStatus'] ?? '',
                $comp['createdDate'] ?? '',
                $comp['transactionType'] ?? '',
                $comp['country'] ?? '',
                $comp['state'] ?? '',
                $comp['taxRate'] ?? '',
                $comp['totalItemsAmount'] ?? '',
                $comp['nonTaxableAmount'] ?? '',
                $comp['currency'] ?? '',
                $comp['taxableItemsAmount'] ?? '',
                $comp['salesTaxAmount'] ?? '',
                $comp_gross,

                "",
                "",
                // 3-way gross comparison (absolute)
                $woo_gross_f,
                $bt_gross,
                $comp_gross,
            ];

            $output[] = implode(",", array_map([$this, 'escape_csv'], $row));
        }

        return [
            'data'      => $output,
            'remaining' => max(0, count($all_ids) - $offset - $limit),
        ];
    }

    private function csv_header(): string {
        return implode(",", [
            "[Woo Refund] Date",
            "[Woo Refund] ID",
            "[Woo Parent] Order ID",
            "[Woo] Payment Method",
            "[Woo] Payment Method ID",
            "[Woo] Country",
            "[Woo] Shipping State",
            "[Woo Refund] Total (no tax)",
            "[Woo Refund] Tax",
            "[Woo Refund] Total + Tax",
            "#################",
            "[Braintree Refund] Total (no tax)",
            "[Braintree Refund] Tax",
            "[Braintree Refund] Total + Tax",
            "#################",
            "[Complyt Refund] External ID",
            "[Complyt Refund] Transaction Status",
            "[Complyt Refund] Created Date",
            "[Complyt Refund] Transaction Type",
            "[Complyt Refund] Country",
            "[Complyt Refund] State",
            "[Complyt Refund] Tax Rate",
            "[Complyt Refund] Total Items Amount",
            "[Complyt Refund] Non-Taxable Amount",
            "[Complyt Refund] Currency",
            "[Complyt Refund] Taxable Items Amount",
            "[Complyt Refund] Sales Tax Amount",
            "[Complyt Refund] Total + Tax",
            "#################",
            "#################",
            "[Woo Refund] Total + Tax",
            "[Braintree Refund] Total + Tax",
            "[Complyt Refund] Total + Tax",
        ]);
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

    private function get_braintree_refund_amounts(WC_Order $parent_order, ?WC_Order_Refund $refund = null): array {
        $format_row = static function ($amount, $tax) {
            $amount = (float)$amount;
            $tax    = (float)$tax;
            $net    = $amount - $tax;
            return [
                'with_tax' => wc_format_decimal(abs($amount), 2),
                'tax'      => $tax > 0 ? wc_format_decimal(abs($tax), 2) : '',
                'no_tax'   => wc_format_decimal(abs($net), 2),
            ];
        };

        try {
            $oid  = (string) $parent_order->get_id();
            $ids  = [$oid];

            // PPU suffix handling (kept as-is)
            $ppu   = (int) $parent_order->get_meta('ppu_products_count');
            $items = (int) $parent_order->get_item_count();
            $non_ppu = $items - $ppu;
            if ($ppu > 0) {
                for ($i = $non_ppu; $i <= $items; $i++) {
                    $ids[] = $oid . '-' . $i;
                }
            }

            // Helper: load ALL credit txns for the order (and PPU-suffix variants)
            $ok_status = ['settled', 'submitted_for_settlement', 'settling'];
            $credits = []; // each: ['id','orderId','amount','tax','createdAt']
            foreach ($ids as $bt_order_id) {
                $col = \Braintree\Transaction::search([
                    \Braintree\TransactionSearch::orderId()->is($bt_order_id),
                ]);
                foreach ($col as $tx) {
                    if ($tx->type === 'credit' && in_array($tx->status, $ok_status, true)) {
                        $credits[] = [
                            'id'        => (string) $tx->id,
                            'orderId'   => (string) $tx->orderId,
                            'amount'    => (float) $tx->amount,
                            'tax'       => isset($tx->taxAmount) ? (float) $tx->taxAmount : 0.00,
                            'createdAt' => isset($tx->createdAt) && $tx->createdAt instanceof \DateTime
                                ? $tx->createdAt->getTimestamp()
                                : null,
                        ];
                    }
                }
            }

            // If no specific refund requested, keep legacy "sum" behavior
            if (!$refund) {
                $sumAmount = 0.0;
                $sumTax = 0.0;
                foreach ($credits as $c) {
                    $sumAmount += $c['amount'];
                    $sumTax    += $c['tax'];
                }
                return $format_row($sumAmount, $sumTax);
            }

            // 1) Try direct ID match from refund meta (several likely keys)
            $meta_keys = [
                '_braintree_refund_id',
                '_wc_braintree_refund_id',
                'braintree_refund_id',
                '_braintree_transaction_id', // some gateways reuse this
            ];
            $bt_id = '';
            foreach ($meta_keys as $k) {
                $v = (string) $refund->get_meta($k);
                if ($v !== '') {
                    $bt_id = $v;
                    break;
                }
            }
            if ($bt_id !== '') {
                try {
                    $tx = \Braintree\Transaction::find($bt_id);
                    if ($tx && $tx->type === 'credit' && in_array($tx->status, $ok_status, true)) {
                        $amount = (float) $tx->amount;
                        $tax    = isset($tx->taxAmount) ? (float) $tx->taxAmount : 0.00;
                        return $format_row($amount, $tax);
                    }
                } catch (\Exception $e) {
                    // fall through to heuristic
                }
            }

            // 2) Heuristic: match by amount (+/- 0.01) and closest date to Woo refund
            $target_amount = abs((float) $refund->get_total());      // Woo refunds are negative -> use abs
            $target_tax    = abs((float) $refund->get_total_tax());
            $target_ts     = $refund->get_date_created() ? $refund->get_date_created()->getTimestamp() : null;

            // Filter by amount match (±0.01)
            $epsilon = 0.01;
            $candidates = array_values(array_filter($credits, static function ($c) use ($target_amount, $epsilon) {
                return abs($c['amount'] - $target_amount) <= $epsilon;
            }));

            // If we have tax on both sides, prefer tax match too
            if ($target_tax > 0 && !empty($candidates)) {
                $tax_candidates = array_values(array_filter($candidates, static function ($c) use ($target_tax, $epsilon) {
                    return abs($c['tax'] - $target_tax) <= $epsilon;
                }));
                if (!empty($tax_candidates)) {
                    $candidates = $tax_candidates;
                }
            }

            // If multiple candidates, choose the one closest in time to Woo refund
            if (!empty($candidates)) {
                if ($target_ts) {
                    usort($candidates, static function ($a, $b) use ($target_ts) {
                        $da = isset($a['createdAt']) && $a['createdAt'] ? abs($a['createdAt'] - $target_ts) : PHP_INT_MAX;
                        $db = isset($b['createdAt']) && $b['createdAt'] ? abs($b['createdAt'] - $target_ts) : PHP_INT_MAX;
                        return $da <=> $db;
                    });
                }
                $pick = $candidates[0];
                return $format_row($pick['amount'], $pick['tax']);
            }

            // 3) Nothing matched cleanly -> fall back to legacy total (at least not empty)
            $sumAmount = 0.0;
            $sumTax = 0.0;
            foreach ($credits as $c) {
                $sumAmount += $c['amount'];
                $sumTax    += $c['tax'];
            }
            return $format_row($sumAmount, $sumTax);
        } catch (\Exception $e) {
            if (class_exists('PFMP_Utils') && method_exists('PFMP_Utils', 'log')) {
                PFMP_Utils::log('BT refunds error: ' . $e->getMessage());
            }
            return ['with_tax' => 'Error', 'tax' => 'Error', 'no_tax' => 'Error'];
        }
    }


    private function get_complyt_refund_data(int $parent_order_id, int $refund_id): array {
        // Preferred hook (you implement): returns specific refund tx
        $result = apply_filters('get_complyt_tax_refund_transaction', $parent_order_id, $refund_id);
        if (!is_array($result) || empty($result)) {
            // Fallback to generic transaction lookup by parent
            $result = apply_filters('get_complyt_tax_transaction', $refund_id);
        }

        if (!is_array($result)) {
            return array_fill_keys([
                'externalId',
                'transactionStatus',
                'createdDate',
                'transactionType',
                'country',
                'state',
                'taxRate',
                'totalItemsAmount',
                'nonTaxableAmount',
                'currency',
                'taxableItemsAmount',
                'salesTaxAmount',
                'finalTransactionAmount',
            ], '');
        }
        return [
            'externalId'             => $result['externalId'] ?? '',
            'transactionStatus'      => $result['transactionStatus'] ?? '',
            'createdDate'            => $result['externalTimestamps']['createdDate'] ?? '',
            'transactionType'        => $result['transactionType'] ?? '',
            'country'                => $result['shippingAddress']['country'] ?? '',
            'state'                  => $result['shippingAddress']['state'] ?? '',
            'taxRate'                => isset($result['salesTax']['rate']) ? wc_format_decimal($result['salesTax']['rate'] * 100, 2) . '%' : '',
            'totalItemsAmount'       => $result['totalItemsAmount'] ?? '',
            'nonTaxableAmount'       => $result['nonTaxableAmount'] ?? '',
            'currency'               => $result['currency'] ?? '',
            'taxableItemsAmount'     => $result['taxableItemsAmount'] ?? '',
            'salesTaxAmount'         => isset($result['salesTax']['amount']) ? wc_format_decimal($result['salesTax']['amount'], 2) : '',
            'finalTransactionAmount' => $result['finalTransactionAmount'] ?? '',
        ];
    }

    private function escape_csv($v) {
        $v = (string)$v;
        return '"' . str_replace('"', '""', $v) . '"';
    }
}
