<?php
// class-pfmp-rest-refunds.php

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . '/class-pfmp-utils.php';

class PFMP_REST_Refunds {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }

    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/orders/(?P<id>\d+)/refund', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'refund_order_items'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel']
        ]);

        register_rest_route('pfm-panel/v1', '/orders/(?P<id>\d+)/refund-via-credits', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'refund_order_via_store_credits'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
            'args'                => [
                'id'     => ['required' => true, 'sanitize_callback' => 'absint'],
                'amount' => ['required' => true],
                'reason' => ['required' => false],
            ],
        ]);
    }

    public function refund_order_items(WP_REST_Request $request) {
        try {
            $order_id = absint($request['id']);
            $params   = $request->get_json_params();
            $order    = wc_get_order($order_id);

            if (!$order) {
                return new WP_Error('invalid_order', 'Order not found', ['status' => 404]);
            }

            $refund_items    = $params['items'] ?? [];
            $refund_fees     = $params['fees'] ?? [];
            $refund_shipping = $params['shipping'] ?? [];

            $via_bt      = !empty($params['refund_via_braintree']);
            $via_blue    = !empty($params['refund_via_bluesnap']);
            $via_afterpay = !empty($params['refund_via_afterpay']);
            $skip_woo = !empty($params['skip_woocommerce_refund']);
            $manual_amount = null;
            if (isset($params['manual_refund_amount']) && $params['manual_refund_amount'] !== '' && $params['manual_refund_amount'] !== null) {
                $manual_amount = (float) $params['manual_refund_amount'];
            }
            $gateway_count = (int)$via_bt + (int)$via_blue + (int)$via_afterpay;
            if ($gateway_count > 1) {
                return new WP_Error('invalid_refund_gateway', 'Choose only one refund gateway (Braintree, BlueSnap, or Afterpay).', ['status' => 400]);
            }
            if ($skip_woo && $gateway_count === 0) {
                return new WP_Error('invalid_refund_gateway', 'Terminal-only refunds require selecting a refund gateway (Braintree, BlueSnap, or Afterpay).', ['status' => 400]);
            }

            $did_gateway_refund = false;
            $logged_skip_note = false;

            PFMP_Utils::log("🧾 Refund request received for Order #$order_id");
            PFMP_Utils::log("🧾 Refund items: " . print_r($refund_items, true));
            PFMP_Utils::log("🧾 Refund fees: " . print_r($refund_fees, true));
            PFMP_Utils::log("🧾 Refund shipping: " . print_r($refund_shipping, true));
            if ($manual_amount !== null) {
                PFMP_Utils::log("🧾 Manual refund amount override: " . $manual_amount);
            }

            $error_status_from_wp_error = static function ($err, $default = 400) {
                if (!is_wp_error($err)) return (int) $default;
                $data = $err->get_error_data();
                if (is_array($data) && isset($data['status'])) return (int) $data['status'];
                if (is_int($data)) return $data;
                return (int) $default;
            };

            // Get tax rate (if one and only one exists)
            $tax_rate_id = null;
            $tax_items   = $order->get_items('tax');
            if (count($tax_items) === 1) {
                $tax_rate_id = current($tax_items)->get_rate_id();
            }

            $refund_ids  = [];
            $items_by_tx = ['default' => []];

            foreach ($refund_items as $item) {
                $tx_id = $item['transaction_id'] ?? 'default';
                $items_by_tx[$tx_id][] = $item;
            }

            // Terminal-only manual refund: allow gateway refund without any Woo line-items/fees/shipping.
            if (
                $skip_woo &&
                $gateway_count === 1 &&
                $manual_amount !== null &&
                $manual_amount > 0 &&
                empty($refund_items) &&
                empty($refund_fees) &&
                empty($refund_shipping)
            ) {
                $refund_reason = !empty($params['reason']) ? sanitize_textarea_field($params['reason']) : 'Refund via PFM Panel';
                $tx_to_use = $order->get_transaction_id();
                $amount_str = number_format((float) $manual_amount, 2, '.', '');

                if ($via_bt) {
                    PFMP_Utils::log("🚦 Braintree terminal-only manual refund for tx '$tx_to_use' amount: {$amount_str}");
                    $result = $this->refund_braintree_transaction($order, $amount_str, $refund_reason, $tx_to_use);
                    if (is_wp_error($result)) {
                        return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], $error_status_from_wp_error($result, 400));
                    }
                } elseif ($via_blue) {
                    PFMP_Utils::log("🟦 BlueSnap terminal-only manual refund (placeholder) for tx '$tx_to_use' amount: {$amount_str}");
                    $result = $this->refund_bluesnap_transaction($order, $amount_str, $refund_reason, $tx_to_use);
                    if (is_wp_error($result)) {
                        return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], $error_status_from_wp_error($result, 400));
                    }
                } elseif ($via_afterpay) {
                    PFMP_Utils::log("🟩 Afterpay terminal-only manual refund (placeholder) for tx '$tx_to_use' amount: {$amount_str}");
                    $result = $this->refund_afterpay_transaction($order, $amount_str, $refund_reason, $tx_to_use);
                    if (is_wp_error($result)) {
                        return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], $error_status_from_wp_error($result, 400));
                    }
                }

                $current_user = wp_get_current_user();
                $admin_name = $current_user->display_name ?? 'Admin';
                $order->add_order_note(sprintf(
                    '🧾 WooCommerce refund skipped (terminal-only refund). By %s.%s',
                    $admin_name,
                    $refund_reason ? ' Reason: ' . $refund_reason : ''
                ));
                $order->save();
                PFMP_Utils::log_admin_action('refund_woocommerce_skipped', 'order', "WooCommerce refund skipped for order #{$order->get_id()} (terminal-only manual amount).");

                return rest_ensure_response([
                    'success'               => true,
                    'refund_ids'            => [],
                    'skipped_woocommerce'   => true,
                    'gateway_refund_status' => 'requested',
                ]);
            }

            // If manual amount is used together with split transactions, it's ambiguous which tx to refund.
            if ($manual_amount !== null && count($items_by_tx) > 1) {
                return new WP_Error('invalid_manual_refund_amount', 'Manual refund amount is not supported for orders with multiple transactions.', ['status' => 400]);
            }
            // Make sure fees/shipping also respect transactions if you ever send tx on them
            $fees_by_tx = ['default' => $refund_fees];
            $ship_by_tx = ['default' => $refund_shipping];

            // Woo safety: compute remaining refundable amount so we don't create Woo refunds
            // that exceed the remaining refundable total (or when already fully refunded).
            // Also subtract any amount already returned via store credits to prevent combined over-refunding.
            $order_total_amount      = (float) $order->get_total();
            $already_refunded_amount = (float) $order->get_total_refunded();
            $credit_refunded_amount  = (float) $order->get_meta('_pfm_credit_refund_total');
            $remaining_wc_refund     = max(0.0, $order_total_amount - $already_refunded_amount - $credit_refunded_amount);

            // (debug log removed)

            // If admin manually overrides the refund amount, it will be used for the terminal/gateway call.
            // When Woo refunds are NOT being skipped, do not allow that manual amount to exceed what Woo can refund,
            // otherwise we'd attempt a terminal refund larger than the remaining Woo refundable amount.
            if (!$skip_woo && $manual_amount !== null && $manual_amount > $remaining_wc_refund + 0.01) {
                return new WP_Error(
                    'manual_refund_exceeds_remaining',
                    sprintf(
                        'Manual refund amount (%s) exceeds the remaining refundable amount in WooCommerce (%s). Reduce the amount, or enable "Do not refund in WooCommerce" for terminal-only.',
                        wp_strip_all_tags(wc_price((float) $manual_amount, ['currency' => $order->get_currency()])),
                        wp_strip_all_tags(wc_price((float) $remaining_wc_refund, ['currency' => $order->get_currency()]))
                    ),
                    ['status' => 400]
                );
            }

            foreach ($items_by_tx as $tx_id => $grouped_items) {
                $refund_data = [
                    'order_id'      => $order_id,
                    'reason'        => !empty($params['reason']) ? sanitize_textarea_field($params['reason']) : 'Refund via PFM Panel',
                    'line_items'    => [],
                    'restock_items' => false,
                ];

                // Two different totals:
                // - $gateway_total_refund: send "as-is" (raw request) to payment gateway
                // - $wc_total_refund: Woo refund amount built from normalized (snapped) lines
                $gateway_total_refund = 0.0;
                $wc_total_refund      = 0.0;

                // 🔁 PRODUCTS
                foreach ($grouped_items as $item) {
                    $item_id   = absint($item['id']);
                    $qty       = isset($item['quantity']) ? absint($item['quantity']) : 0;
                    $req_total = floatval($item['total']);
                    $req_tax   = floatval($item['tax']);
                    if ($req_total === 0.0 && $req_tax === 0.0) {
                        continue;
                    }

                    // Gateway: add raw
                    $gateway_total_refund += ($req_total + $req_tax);

                    // WC: snap to original if "almost equal"
                    $order_item = $order->get_item($item_id);
                    if (!$order_item) {
                        continue;
                    }

                    $orig_total = (float) $order_item->get_total();
                    $orig_tax   = array_sum($order_item->get_taxes()['total'] ?? []);

                    $wc_total   = $req_total;
                    $wc_tax     = $req_tax;

                    if (abs($orig_total - $req_total) < 0.1) {
                        $wc_total = $orig_total;
                        // (debug log removed)
                    }
                    if (abs($orig_tax - $req_tax) < 0.1) {
                        $wc_tax = $orig_tax;
                        // (debug log removed)
                    }

                    $line = array_filter([
                        'qty'          => $qty > 0 ? $qty : null,
                        'refund_total' => $wc_total,
                    ]);

                    if ($wc_tax > 0) {
                        $taxes = $order_item->get_taxes();
                        $line['refund_tax'] = [];

                        if (!empty($taxes['total'])) {
                            $total_tax_original = array_sum($taxes['total']);
                            if ($total_tax_original != 0.0) {
                                foreach ($taxes['total'] as $rate_id => $tax_value) {
                                    $ratio = $tax_value / $total_tax_original;
                                    $line['refund_tax'][$rate_id] = round($wc_tax * $ratio, 2);
                                }
                            }
                        }
                    }

                    $refund_data['line_items'][$item_id] = $line;
                    $wc_total_refund += ($wc_total + (isset($line['refund_tax']) ? array_sum($line['refund_tax']) : 0));
                }

                // 💸 FEES
                foreach ($fees_by_tx[$tx_id] ?? [] as $fee) {
                    $fee_id    = absint($fee['id']);
                    $req_total = floatval($fee['total']);
                    $req_tax   = floatval($fee['tax']);
                    if ($req_total === 0.0 && $req_tax === 0.0) {
                        continue;
                    }

                    // Gateway: raw
                    $gateway_total_refund += ($req_total + $req_tax);

                    // WC: snap
                    $order_fee = $order->get_item($fee_id);
                    if (!$order_fee) {
                        continue;
                    }

                    $orig_total = (float) $order_fee->get_total();
                    $orig_tax   = array_sum($order_fee->get_taxes()['total'] ?? []);

                    $wc_total = $req_total;
                    $wc_tax   = $req_tax;

                    if (abs($orig_total - $req_total) < 0.1) {
                        $wc_total = $orig_total;
                        // (debug log removed)
                    }
                    if (abs($orig_tax - $req_tax) < 0.1) {
                        $wc_tax = $orig_tax;
                        // (debug log removed)
                    }

                    $line = ['refund_total' => $wc_total];
                    if ($wc_tax > 0 && $tax_rate_id) {
                        $line['refund_tax'] = [$tax_rate_id => round($wc_tax, 2)];
                    }

                    $refund_data['line_items'][$fee_id] = $line;
                    $wc_total_refund += ($wc_total + (isset($line['refund_tax']) ? array_sum($line['refund_tax']) : 0));
                }

                // 🚚 SHIPPING
                foreach ($ship_by_tx[$tx_id] ?? [] as $shipping) {
                    $shipping_id = absint($shipping['id']);
                    $req_total   = floatval($shipping['total']);
                    $req_tax     = floatval($shipping['tax']);
                    if ($req_total === 0.0 && $req_tax === 0.0) {
                        continue;
                    }

                    // Gateway: raw
                    $gateway_total_refund += ($req_total + $req_tax);

                    // WC: snap
                    $order_shipping = $order->get_item($shipping_id);
                    if (!$order_shipping) {
                        continue;
                    }

                    $orig_total = (float) $order_shipping->get_total();
                    $orig_tax   = array_sum($order_shipping->get_taxes()['total'] ?? []);

                    $wc_total = $req_total;
                    $wc_tax   = $req_tax;

                    if (abs($orig_total - $req_total) < 0.1) {
                        $wc_total = $orig_total;
                        // (debug log removed)
                    }
                    if (abs($orig_tax - $req_tax) < 0.1) {
                        $wc_tax = $orig_tax;
                        // (debug log removed)
                    }

                    $line = ['refund_total' => $wc_total];
                    if ($wc_tax > 0 && $tax_rate_id) {
                        $line['refund_tax'] = [$tax_rate_id => round($wc_tax, 2)];
                    }

                    $refund_data['line_items'][$shipping_id] = $line;
                    $wc_total_refund += ($wc_total + (isset($line['refund_tax']) ? array_sum($line['refund_tax']) : 0));
                }

                if ($manual_amount !== null && $manual_amount > 0) {
                    // Use the manual amount for the gateway refund (Woo refund still uses snapped line items).
                    $gateway_total_refund = (float) $manual_amount;
                }

                if (empty($refund_data['line_items'])) {
                    continue;
                }

                // ---- Woo refund safety check (before gateway calls) ----
                if (!$skip_woo) {
                    $planned_wc_amount = (float) wc_format_decimal(array_reduce(
                        $refund_data['line_items'],
                        function ($sum, $line) {
                            $t = (float) ($line['refund_total'] ?? 0);
                            $x = is_array($line['refund_tax'] ?? null) ? array_sum($line['refund_tax']) : 0;
                            return $sum + $t + $x;
                        },
                        0.0
                    ), 2);

                    // (debug log removed)

                    if ($remaining_wc_refund <= 0.01) {
                        return new WP_Error(
                            'order_already_refunded',
                            'This order is already fully refunded in WooCommerce. If you need a terminal-only refund, enable "Do not refund in WooCommerce".',
                            ['status' => 400]
                        );
                    }

                    if ($planned_wc_amount > $remaining_wc_refund + 0.01) {
                        return new WP_Error(
                            'refund_exceeds_remaining',
                            sprintf(
                                'Requested WooCommerce refund amount (%s) exceeds the remaining refundable amount (%s). Reduce the refund or enable "Do not refund in WooCommerce" for terminal-only.',
                                wp_strip_all_tags(wc_price($planned_wc_amount, ['currency' => $order->get_currency()])),
                                wp_strip_all_tags(wc_price($remaining_wc_refund, ['currency' => $order->get_currency()]))
                            ),
                            ['status' => 400]
                        );
                    }

                    // Reserve this amount so multiple grouped refunds can't exceed remaining.
                    $remaining_wc_refund = max(0.0, $remaining_wc_refund - $planned_wc_amount);
                    // (debug log removed)
                }

                // --- BRAINTREE: send raw amount (exactly what the UI requested) ---
                if ($via_bt) {
                    $tx_to_use     = ($tx_id === 'default') ? $order->get_transaction_id() : $tx_id;
                    $amount_str    = number_format((float) $gateway_total_refund, 2, '.', ''); // BT likes "12.34"
                    PFMP_Utils::log("🚦 Braintree refund for tx '$tx_to_use' raw total: {$amount_str}");
                    PFMP_Utils::log(sprintf(
                        "🚦 Calling refund_braintree_transaction: order_id=%s, amount=%s, reason=%s, tx_id=%s",
                        $order->get_id(),
                        $amount_str,
                        $refund_data['reason'],
                        $tx_to_use
                    ));
                    $result = $this->refund_braintree_transaction(
                        $order,
                        $amount_str,
                        $refund_data['reason'],
                        $tx_to_use
                    );
                    PFMP_Utils::log("🚦 Braintree refund result: " . print_r($result, true));
                    if (is_wp_error($result)) {
                        return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], $error_status_from_wp_error($result, 400));
                    }
                    $did_gateway_refund = true;
                }

                // --- BLUESNAP (placeholder): use transaction_id, we'll wire real API later ---
                if ($via_blue) {
                    $tx_to_use  = ($tx_id === 'default') ? $order->get_transaction_id() : $tx_id;
                    $amount_str = number_format((float) $gateway_total_refund, 2, '.', '');
                    PFMP_Utils::log("🟦 BlueSnap refund (placeholder) for tx '$tx_to_use' raw total: {$amount_str}");
                    $result = $this->refund_bluesnap_transaction(
                        $order,
                        $amount_str,
                        $refund_data['reason'],
                        $tx_to_use
                    );
                    PFMP_Utils::log("🟦 BlueSnap refund (placeholder) result: " . print_r($result, true));
                    if (is_wp_error($result)) {
                        return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], $error_status_from_wp_error($result, 400));
                    }
                    $did_gateway_refund = true;
                }

                // --- AFTERPAY (placeholder): use transaction_id, we'll wire real API later ---
                if ($via_afterpay) {
                    $tx_to_use  = ($tx_id === 'default') ? $order->get_transaction_id() : $tx_id;
                    $amount_str = number_format((float) $gateway_total_refund, 2, '.', '');
                    PFMP_Utils::log("🟩 Afterpay refund (placeholder) for tx '$tx_to_use' raw total: {$amount_str}");
                    $result = $this->refund_afterpay_transaction(
                        $order,
                        $amount_str,
                        $refund_data['reason'],
                        $tx_to_use
                    );
                    PFMP_Utils::log("🟩 Afterpay refund (placeholder) result: " . print_r($result, true));
                    if (is_wp_error($result)) {
                        return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], $error_status_from_wp_error($result, 400));
                    }
                    $did_gateway_refund = true;
                }

                // Terminal-only refunds: skip WooCommerce refund creation entirely.
                if ($skip_woo) {
                    if (!$logged_skip_note) {
                        $current_user = wp_get_current_user();
                        $admin_name = $current_user->display_name ?? 'Admin';
                        $order->add_order_note(sprintf(
                            '🧾 WooCommerce refund skipped (terminal-only refund). By %s.%s',
                            $admin_name,
                            !empty($refund_data['reason']) ? ' Reason: ' . $refund_data['reason'] : ''
                        ));
                        $order->save();
                        PFMP_Utils::log_admin_action('refund_woocommerce_skipped', 'order', "WooCommerce refund skipped for order #{$order->get_id()} (terminal-only).");
                        $logged_skip_note = true;
                    }
                    continue;
                }

                // If we already processed the refund via a payment gateway ("terminal"),
                // create the Woo refund record WITHOUT triggering the gateway refund again.
                // (Prevents duplicate refunds and duplicate gateway-generated order notes.)
                if ($via_bt || $via_blue || $via_afterpay) {
                    $refund_data['refund_payment'] = false;
                }

                // --- WOO: use snapped line items/taxes, recompute amount from the lines ---
                $refund_data['amount'] = wc_format_decimal(array_reduce(
                    $refund_data['line_items'],
                    function ($sum, $line) {
                        $t = (float) ($line['refund_total'] ?? 0);
                        $x = is_array($line['refund_tax'] ?? null) ? array_sum($line['refund_tax']) : 0;
                        return $sum + $t + $x;
                    },
                    0.0
                ), 2);

                // (debug logs removed)

                $refund = wc_create_refund($refund_data);

                if (!is_wp_error($refund)) {
                    PFMP_Utils::log_admin_action('refund', 'order', "Created refund #{$refund->get_id()} on order #{$order_id} ({$refund_data['amount']})");

                    // (do not modify refund timestamps; keep Woo default behavior)

                    $order->add_order_note('Refund created: ' . $refund_data['reason']);
                    $order->save();
                    $refund_ids[] = $refund->get_id();
                } else {
                    PFMP_Utils::log("❌ Refund creation error: " . $refund->get_error_message());
                    return new WP_REST_Response([
                        'success' => false,
                        'error'   => $refund->get_error_message(),
                    ], 400);
                }
            }

            if (empty($refund_ids)) {
                if ($skip_woo && $did_gateway_refund) {
                    return rest_ensure_response([
                        'success'               => true,
                        'refund_ids'            => [],
                        'skipped_woocommerce'   => true,
                        'gateway_refund_status' => 'requested',
                    ]);
                }

                return new WP_Error('no_refund_data', 'Nothing to refund', ['status' => 400]);
            }

            if ($order->get_total_refunded() >= $order->get_total()) {
                $order->update_status('refunded', 'Order fully refunded via PFM Panel');
            }

            return rest_ensure_response([
                'success'    => true,
                'refund_ids' => $refund_ids,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('server_error', 'Unexpected server error: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    public function refund_braintree_transaction($order, $amount, $reason = '', $transaction_id = null) {
        if (!$transaction_id) {
            $transaction_id = $order->get_transaction_id();
        }

        if (empty($transaction_id)) {
            return new WP_Error('refund-error', 'Missing transaction ID for this order.');
        }

        $decimals = wc_get_price_decimals(); // respects currency (e.g., 0 for JPY, 2 for USD)
        $amount   = number_format(round((float) $amount, $decimals), $decimals, '.', '');

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';

        $settings = get_option('woocommerce_braintree_api_settings');

        $environment = $settings['environment'];
        if ($environment === 'sandbox') {
            $public_key  = $settings['sandbox_public_key'];
            $private_key = $settings['sandbox_private_key'];
            $merchant_id = $settings['sandbox_merchant_id'];
        } else {
            $public_key  = $settings['production_public_key'];
            $private_key = $settings['production_private_key'];
            $merchant_id = $settings['production_merchant_id'];
        }

        \Braintree\Configuration::environment($environment);
        \Braintree\Configuration::merchantId($merchant_id);
        \Braintree\Configuration::publicKey($public_key);
        \Braintree\Configuration::privateKey($private_key);

        try {
            $result = \Braintree\Transaction::refund($transaction_id, $amount);

            if ($result->success) {
                $refund_id = $result->transaction->id;

                $order->add_order_note(sprintf(
                    '✅ Refund processed in Braintree. Amount: %s. Refund ID: %s. By %s.',
                    wc_price($amount, ['currency' => $order->get_currency()]),
                    $refund_id,
                    $admin_name
                ));
                $order->update_meta_data('_braintree_refund_id', $refund_id);
                $order->save();
                PFMP_Utils::log_admin_action('refund_gateway', 'order', "Braintree refund for order #{$order->get_id()} | tx {$transaction_id} | amount {$amount}");

                return true;
            } else {
                $error_message = $result->message;

                return new WP_Error('refund-error', 'Braintree error: ' . $error_message);
            }
        } catch (Exception $e) {
            return new WP_Error('refund-exception', 'Exception: ' . $e->getMessage());
        }
    }

    /**
     * BlueSnap refund flow (real).
     * Transaction ID source:
     * - $order->get_transaction_id()
     * - fallback: $order->get_meta('_bluesnap_invoice_id', true)
     *
     * Decision:
     * - if meta '_bluesnap_charge_captured' === 'no' => AUTH_REVERSAL
     * - else => refund (captured)
     *
     * Uses wp_safe_remote_request() and logs detailed errors when requests fail.
     */
    public function refund_bluesnap_transaction($order, $amount, $reason = '', $transaction_id = null) {
        $cfg = $this->bluesnap_get_config();
        if (is_wp_error($cfg)) return $cfg;

        if (!$transaction_id) {
            $transaction_id = $order->get_transaction_id();
        }
        if (empty($transaction_id)) {
            $transaction_id = $order->get_meta('_bluesnap_invoice_id', true);
        }
        $transaction_id = is_string($transaction_id) ? trim($transaction_id) : (string)$transaction_id;
        if ($transaction_id === '') {
            return new WP_Error('refund-error', 'Missing BlueSnap transaction ID for this order.');
        }

        $reason = (string) $reason;
        $human_reason = trim($reason) !== '' ? $reason : 'Refund via PFM Panel';
        $reason_param = 'wc_reason:' . $human_reason;

        // Amount + currency rules
        $decimals = wc_get_price_decimals();
        $req_amount = number_format(round((float) $amount, $decimals), $decimals, '.', '');
        $send_amount = (float) $req_amount;
        $send_currency = (string) ($order->get_currency() ?: 'USD');

        $charged_currency = (string) $order->get_meta('_charged_currency', true);
        $ex_rate = (float) $order->get_meta('_bsnp_ex_rate', true);
        if ($charged_currency && $charged_currency !== $send_currency && $ex_rate > 0) {
            $send_currency = $charged_currency;
            $send_amount = (float) $req_amount * $ex_rate;
            $send_amount = (float) number_format(round($send_amount, $decimals), $decimals, '.', '');
            PFMP_Utils::log("🟦 BlueSnap currency conversion applied: {$req_amount} {$order->get_currency()} * {$ex_rate} => {$send_amount} {$send_currency}");
        }

        // Full refund: omit amount parameter (mirror existing gateway behavior)
        $order_total = (float) $order->get_total();
        $full_amount_cmp = $order_total;
        if ($charged_currency && $charged_currency !== (string)($order->get_currency() ?: 'USD') && $ex_rate > 0) {
            $full_amount_cmp = $order_total * $ex_rate;
        }
        $is_full_refund = abs($send_amount - (float) number_format(round($full_amount_cmp, $decimals), $decimals, '.', '')) < 0.01;

        $captured_meta = $order->get_meta('_bluesnap_charge_captured', true);
        $is_captured = !((string)$captured_meta === 'no');

        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';

        if (!$is_captured) {
            // AUTH_REVERSAL
            $result = $this->bluesnap_auth_reversal($cfg, $transaction_id);
            if (is_wp_error($result)) {
                $err_msg = $result->get_error_message();
                $order->add_order_note(sprintf(
                    '❌ BlueSnap AUTH_REVERSAL FAILED. Tx: %s. By %s.%s Error: %s',
                    $transaction_id,
                    $admin_name,
                    $human_reason ? ' Reason: ' . $human_reason : '',
                    $err_msg
                ));
                $order->save();
                PFMP_Utils::log_admin_action('refund_gateway_failed', 'order', "BlueSnap auth reversal failed for order #{$order->get_id()} | tx {$transaction_id} | {$err_msg}");
                return $result;
            }

            $order->add_order_note(sprintf(
                '✅ BlueSnap authorization reversed. Tx: %s. By %s.%s',
                $transaction_id,
                $admin_name,
                $human_reason ? ' Reason: ' . $human_reason : ''
            ));
            $order->save();
            PFMP_Utils::log_admin_action('refund_gateway', 'order', "BlueSnap auth reversal for order #{$order->get_id()} | tx {$transaction_id}");
            return true;
        }

        // REFUND (captured)
        $result = $this->bluesnap_refund_captured($cfg, $transaction_id, $reason_param, $is_full_refund ? null : $send_amount);
        if (is_wp_error($result)) {
            $err_msg = $result->get_error_message();
            $order->add_order_note(sprintf(
                '❌ BlueSnap refund FAILED. Amount: %s %s. Tx: %s. By %s.%s Error: %s',
                number_format((float)$send_amount, $decimals, '.', ''),
                $send_currency,
                $transaction_id,
                $admin_name,
                $human_reason ? ' Reason: ' . $human_reason : '',
                $err_msg
            ));
            $order->save();
            PFMP_Utils::log_admin_action('refund_gateway_failed', 'order', "BlueSnap refund failed for order #{$order->get_id()} | tx {$transaction_id} | amount {$send_amount} {$send_currency} | {$err_msg}");
            return $result;
        }

        $order->add_order_note(sprintf(
            '✅ Refund processed in BlueSnap. Amount: %s %s%s. Tx: %s. By %s.%s',
            $is_full_refund ? 'FULL' : number_format((float)$send_amount, $decimals, '.', ''),
            $send_currency,
            $is_full_refund ? '' : '',
            $transaction_id,
            $admin_name,
            $human_reason ? ' Reason: ' . $human_reason : ''
        ));
        $order->save();
        PFMP_Utils::log_admin_action('refund_gateway', 'order', "BlueSnap refund for order #{$order->get_id()} | tx {$transaction_id} | " . ($is_full_refund ? 'FULL' : "amount {$send_amount} {$send_currency}"));

        return true;
    }

    private function bluesnap_get_config() {
        $settings = get_option('woocommerce_bluesnap_settings', []);
        if (!is_array($settings)) $settings = [];

        $testmode = (string)($settings['testmode'] ?? '');
        $base_url = ($testmode === 'yes') ? 'https://sandbox.bluesnap.com' : 'https://ws.bluesnap.com';

        $username = (string)($settings['api_username'] ?? '');
        $password = (string)($settings['api_password'] ?? '');
        if ($username === '' || $password === '') {
            return new WP_Error('bluesnap_config', 'Missing BlueSnap API credentials.');
        }

        return [
            'base_url' => $base_url,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function bluesnap_headers($cfg) {
        return [
            'Authorization'     => 'Basic ' . base64_encode($cfg['username'] . ':' . $cfg['password']),
            'bluesnap-version'  => '2.0',
            'Accept'            => 'application/json',
            'Content-Type'      => 'application/json',
            'User-Agent'        => 'PFM Panel',
        ];
    }

    // A) Refund captured transaction: PUT /services/2/transactions/{transaction_id}/refund
    // Success: HTTP 204
    private function bluesnap_refund_captured($cfg, $transaction_id, $reason_param, $amount_or_null) {
        $base = rtrim((string)$cfg['base_url'], '/');
        $url  = $base . '/services/2/transactions/' . rawurlencode((string)$transaction_id) . '/refund';

        $query = [
            'reason' => (string)$reason_param,
            'cancelsubscriptions' => 'false',
        ];
        if ($amount_or_null !== null) {
            $query['amount'] = number_format((float)$amount_or_null, 2, '.', '');
        }
        $url = $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        PFMP_Utils::log("🟦 BlueSnap refund request: PUT {$url}");

        $res = wp_safe_remote_request($url, [
            'method'  => 'PUT',
            'headers' => $this->bluesnap_headers($cfg),
            'timeout' => 70,
        ]);

        if (is_wp_error($res)) return $res;

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        if ($code === 204) {
            return true;
        }

        $msg = "BlueSnap refund failed (HTTP {$code})";
        if ($body !== '') $msg .= ": {$body}";
        PFMP_Utils::log("🟦 {$msg}");
        return new WP_Error('bluesnap_refund_error', $msg, ['status' => $code, 'body' => $body]);
    }

    // B) Auth reversal: PUT /services/2/transactions with JSON body
    // Success: HTTP 200
    private function bluesnap_auth_reversal($cfg, $transaction_id) {
        $base = rtrim((string)$cfg['base_url'], '/');
        $url  = $base . '/services/2/transactions';
        $payload = [
            'transactionId' => (string)$transaction_id,
            'cardTransactionType' => 'AUTH_REVERSAL',
        ];

        PFMP_Utils::log("🟦 BlueSnap auth reversal request: PUT {$url} tx={$transaction_id}");

        $res = wp_safe_remote_request($url, [
            'method'  => 'PUT',
            'headers' => $this->bluesnap_headers($cfg),
            'timeout' => 70,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($res)) return $res;

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        if ($code === 200) {
            return true;
        }

        $msg = "BlueSnap auth reversal failed (HTTP {$code})";
        if ($body !== '') $msg .= ": {$body}";
        PFMP_Utils::log("🟦 {$msg}");
        return new WP_Error('bluesnap_auth_reversal_error', $msg, ['status' => $code, 'body' => $body]);
    }

    /**
     * Afterpay refund placeholder (will be wired to Afterpay API later).
     * We use the transaction_id as the Afterpay payment reference/ID.
     */
    public function refund_afterpay_transaction($order, $amount, $reason = '', $transaction_id = null) {
        if (!$transaction_id) {
            $transaction_id = $order->get_transaction_id();
        }

        if (empty($transaction_id)) {
            return new WP_Error('refund-error', 'Missing transaction ID for this order.');
        }

        $decimals = wc_get_price_decimals();
        $amount   = number_format(round((float) $amount, $decimals), $decimals, '.', '');

        // Step 1: fetch Afterpay payment using Woo order id as merchant reference (and log response).
        // This gives us the Afterpay payment/order id needed for refunds.
        $lookup = $this->log_afterpay_payment_lookup((int) $order->get_id());
        $afterpay_order_id = null;
        if (is_array($lookup) && !empty($lookup['results']) && is_array($lookup['results'])) {
            $first = $lookup['results'][0] ?? null;
            if (is_array($first) && !empty($first['id'])) {
                $afterpay_order_id = (string) $first['id'];
            }
        }
        // Fallback: some stores already use the Afterpay id as transaction_id.
        if (!$afterpay_order_id) {
            $afterpay_order_id = (string) $transaction_id;
        }

        // Step 2: perform the real refund
        $currency = (string) ($order->get_currency() ?: 'USD');
        $merchant_ref = 'wc-' . (string)$order->get_id();
        $refund_res = $this->afterpay_create_refund($afterpay_order_id, $amount, $currency, $merchant_ref);
        if (is_wp_error($refund_res)) {
            // Failure: log to order notes and admin activity for visibility/audit.
            $current_user = wp_get_current_user();
            $admin_name = $current_user->display_name ?? 'Admin';
            $err_msg = $refund_res->get_error_message();

            $order->add_order_note(sprintf(
                '❌ Afterpay refund FAILED. Amount: %s. By %s.%s Error: %s',
                wc_price($amount, ['currency' => $order->get_currency()]),
                $admin_name,
                $reason ? ' Reason: ' . $reason : '',
                $err_msg
            ));
            $order->save();

            PFMP_Utils::log_admin_action(
                'refund_gateway_failed',
                'order',
                "Afterpay refund failed for order #{$order->get_id()} | afterpay_id {$afterpay_order_id} | amount {$amount} | {$err_msg}"
            );

            return $refund_res;
        }

        // Success: add order note + admin activity
        $current_user = wp_get_current_user();
        $admin_name = $current_user->display_name ?? 'Admin';
        $refund_id = is_array($refund_res) ? (string)($refund_res['refundId'] ?? '') : '';

        $order->add_order_note(sprintf(
            '✅ Refund processed in Afterpay. Amount: %s. Refund ID: %s. By %s.%s',
            wc_price($amount, ['currency' => $order->get_currency()]),
            $refund_id !== '' ? $refund_id : '—',
            $admin_name,
            $reason ? ' Reason: ' . $reason : ''
        ));
        $order->save();
        PFMP_Utils::log_admin_action(
            'refund_gateway',
            'order',
            "Afterpay refund for order #{$order->get_id()} | afterpay_id {$afterpay_order_id} | amount {$amount}" . ($refund_id !== '' ? " | refund_id {$refund_id}" : '')
        );

        return true;
    }

    /**
     * Lookup Afterpay payment(s) by merchant reference (= WooCommerce order id) and log the response.
     * Docs / pattern: GET /v2/payments?merchantReferences={merchantReference}
     *
     * Credentials are read from:
     *   get_option('woocommerce_afterpay_settings', [])
     *
     * We never log credentials.
     */
    private function log_afterpay_payment_lookup($wc_order_id) {
        try {
            $wc_order_id = (int) $wc_order_id;
            $settings = get_option('woocommerce_afterpay_settings', []);
            $mode = (string)($settings['testmode'] ?? 'production'); // plugin stores "production" or similar

            // Per request: use https://api.us.afterpay.com
            // If mode isn't production, fall back to sandbox to be safe.
            $base_url = ($mode === 'production') ? 'https://api.us.afterpay.com' : 'https://api.us-sandbox.afterpay.com';

            $username = ($mode === 'production') ? (string)($settings['prod-id'] ?? '') : (string)($settings['test-id'] ?? '');
            $password = ($mode === 'production') ? (string)($settings['prod-secret-key'] ?? '') : (string)($settings['test-secret-key'] ?? '');

            if ($username === '' || $password === '') {
                PFMP_Utils::log("🟩 Afterpay lookup: missing credentials for mode '{$mode}' (order {$wc_order_id})");
                return null;
            }

            $url = rtrim($base_url, '/') . '/v2/payments?merchantReferences=' . rawurlencode((string)$wc_order_id);
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Accept'        => 'application/json',
                'User-Agent'    => 'PFM Panel',
            ];

            PFMP_Utils::log("🟩 Afterpay lookup request: mode={$mode} url={$url}");

            $res = wp_remote_get($url, [
                'headers' => $headers,
                'timeout' => 20,
            ]);

            if (is_wp_error($res)) {
                PFMP_Utils::log("🟩 Afterpay lookup error (order {$wc_order_id}): " . $res->get_error_message());
                return null;
            }

            $code = (int) wp_remote_retrieve_response_code($res);
            $body = (string) wp_remote_retrieve_body($res);

            PFMP_Utils::log("🟩 Afterpay lookup response (order {$wc_order_id}) HTTP {$code}");
            PFMP_Utils::log("🟩 Afterpay lookup body (order {$wc_order_id}): " . $body);

            $json = null;
            try {
                $json = $body ? json_decode($body, true) : null;
            } catch (Throwable $e) {
                $json = null;
            }
            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            PFMP_Utils::log("🟩 Afterpay lookup exception (order {$wc_order_id}): " . $e->getMessage());
            return null;
        }
    }

    private function afterpay_get_config() {
        $settings = get_option('woocommerce_afterpay_settings', []);
        $mode = (string)($settings['testmode'] ?? 'production'); // plugin stores "production" or similar
        $base_url = ($mode === 'production') ? 'https://api.us.afterpay.com' : 'https://api.us-sandbox.afterpay.com';

        $username = ($mode === 'production') ? (string)($settings['prod-id'] ?? '') : (string)($settings['test-id'] ?? '');
        $password = ($mode === 'production') ? (string)($settings['prod-secret-key'] ?? '') : (string)($settings['test-secret-key'] ?? '');

        return [
            'mode'     => $mode,
            'base_url' => $base_url,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function afterpay_auth_headers($cfg) {
        return [
            'Authorization' => 'Basic ' . base64_encode($cfg['username'] . ':' . $cfg['password']),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'PFM Panel',
        ];
    }

    /**
     * Performs a full or partial Afterpay refund.
     * Endpoint: POST /v2/payments/:orderId/refund
     *
     * Reference: https://developers.afterpay.com/afterpay-online-developer/api-reference/reference/payments/create-refund
     */
    private function afterpay_create_refund($afterpay_order_id, $amount, $currency, $merchant_ref) {
        $cfg = $this->afterpay_get_config();
        if ($cfg['username'] === '' || $cfg['password'] === '') {
            return new WP_Error('afterpay_config', "Missing Afterpay credentials (mode '{$cfg['mode']}').");
        }

        $url = rtrim($cfg['base_url'], '/') . '/v2/payments/' . rawurlencode($afterpay_order_id) . '/refund';
        $request_id = wp_generate_uuid4();
        $refund_ref = $merchant_ref . '-' . substr($request_id, 0, 8);

        $payload = [
            'requestId'               => $request_id,
            'amount'                  => ['amount' => $amount, 'currency' => $currency],
            'merchantReference'       => $refund_ref,
            'refundMerchantReference' => $refund_ref,
        ];

        PFMP_Utils::log("🟩 Afterpay refund request: url={$url} merchantReference={$refund_ref} amount={$amount} {$currency}");

        $res = wp_remote_post($url, [
            'headers' => $this->afterpay_auth_headers($cfg),
            'timeout' => 70,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($res)) {
            return $res;
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        PFMP_Utils::log("🟩 Afterpay refund response HTTP {$code}");
        PFMP_Utils::log("🟩 Afterpay refund body: " . $body);

        $json = null;
        try {
            $json = $body ? json_decode($body, true) : null;
        } catch (Throwable $e) {
            $json = null;
        }

        if ($code < 200 || $code >= 300) {
            // Prefer a clean, human-readable message for the UI (avoid dumping raw JSON to admins).
            $human = (is_array($json) && !empty($json['message'])) ? (string) $json['message'] : 'Afterpay refund failed.';

            $parts = [];
            if (is_array($json) && !empty($json['errorCode'])) $parts[] = 'errorCode: ' . $json['errorCode'];
            if (is_array($json) && !empty($json['errorId']))   $parts[] = 'errorId: ' . $json['errorId'];
            $parts[] = 'HTTP ' . $code;

            $msg = $human . ' (' . implode(', ', $parts) . ')';

            return new WP_Error('afterpay_refund_error', $msg, [
                'status'   => $code,
                'afterpay' => is_array($json) ? $json : null,
            ]);
        }

        return is_array($json) ? $json : ['raw' => $body, 'status' => $code];
    }

    /**
     * Refund an order via store credits.
     *
     * No WC refund object is created. Credits are issued directly to the customer.
     * Order status is flipped to "refunded" when the cumulative credited amount
     * (tracked in _pfm_credit_refund_total) covers the full remaining order balance.
     *
     * Meta written to the order:
     *   _pfm_credit_refund_total          — running USD total credited back
     *   _pfm_sc_refunded                  — blocks restore_credits_on_refund if a
     *                                        gateway refund is ever created later
     *   _pfm_sc_original_credits_restored — prevents double-restoring checkout
     *                                        credits on subsequent partial calls
     */
    public function refund_order_via_store_credits(WP_REST_Request $request) {
        try {
            $order_id = absint($request['id']);
            $amount   = floatval($request->get_param('amount'));
            $reason   = sanitize_textarea_field($request->get_param('reason') ?? '');

            if ($amount <= 0) {
                return new WP_Error('invalid_amount', 'Amount must be greater than 0.', ['status' => 400]);
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('invalid_order', 'Order not found.', ['status' => 404]);
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                return new WP_Error('no_customer', 'Order has no associated customer.', ['status' => 400]);
            }

            if (!class_exists('PFM_SC_Credits')) {
                return new WP_Error('plugin_missing', 'PFM Store Credits plugin is not active.', ['status' => 500]);
            }

            // ── Budget cap ─────────────────────────────────────────────────────────
            $order_total          = (float) $order->get_total();
            $gateway_refunded     = (float) $order->get_total_refunded();
            $credit_refunded      = (float) $order->get_meta('_pfm_credit_refund_total');
            $remaining_creditable = max(0.0, $order_total - $gateway_refunded - $credit_refunded);

            if ($remaining_creditable <= 0.001) {
                return new WP_Error('already_refunded', 'This order has no remaining balance to refund via store credits.', ['status' => 400]);
            }

            if ($amount > $remaining_creditable + 0.01) {
                return new WP_Error(
                    'amount_exceeds_remaining',
                    sprintf(
                        'Amount (%s) exceeds the remaining creditable balance (%s).',
                        wc_price($amount, ['currency' => $order->get_currency()]),
                        wc_price($remaining_creditable, ['currency' => $order->get_currency()])
                    ),
                    ['status' => 400]
                );
            }

            $amount = min($amount, $remaining_creditable);

            // ── Suppress restore_credits_on_refund for any future gateway refund ──
            $order->update_meta_data('_pfm_sc_refunded', true);

            // ── Restore original checkout credits (once per order) ─────────────────
            $credits_used_usd = (float) $order->get_meta('_pfm_sc_used_usd');
            if ($credits_used_usd > 0 && !$order->get_meta('_pfm_sc_original_credits_restored')) {
                PFM_SC_Credits::add_credits(
                    $user_id,
                    $credits_used_usd,
                    sprintf('Checkout credits restored — Order #%s (credit refund)', $order->get_order_number())
                );
                $order->update_meta_data('_pfm_sc_original_credits_restored', true);
            }

            // ── Issue the credit refund amount ─────────────────────────────────────
            $note_text  = $reason ? sprintf('%s — ', $reason) : '';
            $new_balance = PFM_SC_Credits::add_credits(
                $user_id,
                $amount,
                sprintf('%sCredit refund for Order #%s', $note_text, $order->get_order_number())
            );

            // ── Update running total + history ─────────────────────────────────────
            $credit_refunded += $amount;
            $order->update_meta_data('_pfm_credit_refund_total', $credit_refunded);

            $credit_refund_history = $order->get_meta('_pfm_credit_refund_history') ?: [];
            if (!is_array($credit_refund_history)) $credit_refund_history = [];
            $credit_refund_history[] = [
                'date'   => current_time('mysql'),
                'amount' => $amount,
                'reason' => $reason,
                'by'     => $admin_name,
            ];
            $order->update_meta_data('_pfm_credit_refund_history', $credit_refund_history);

            // ── Order note ─────────────────────────────────────────────────────────
            $current_user = wp_get_current_user();
            $admin_name   = $current_user->display_name ?: 'Admin';
            $order->add_order_note(sprintf(
                'Refunded %s via store credits. By %s.%s New credit balance: %s.',
                wc_price($amount, ['currency' => $order->get_currency()]),
                $admin_name,
                $reason ? ' Reason: ' . $reason . '.' : '',
                wc_price($new_balance, ['currency' => 'USD'])
            ));

            // ── Flip to refunded when fully covered ────────────────────────────────
            $is_fully_refunded = ($credit_refunded + $gateway_refunded) >= ($order_total - 0.01);
            if ($is_fully_refunded) {
                $order->update_status('refunded', 'Order fully refunded via store credits.');
            }

            $order->save();

            // ── Klaviyo event ──────────────────────────────────────────────────────
            do_action(
                'pfm_sc_admin_credits_adjusted',
                $user_id,
                $amount,
                'refund_via_credits',
                $reason,
                $new_balance
            );

            PFMP_Utils::log_admin_action(
                'refund_via_credits',
                'order',
                sprintf(
                    'Credit refund of %s for order #%d (user #%d). Running total: %s. Fully refunded: %s.',
                    $amount,
                    $order_id,
                    $user_id,
                    $credit_refunded,
                    $is_fully_refunded ? 'yes' : 'no'
                )
            );

            return rest_ensure_response([
                'success'              => true,
                'new_balance'          => $new_balance,
                'credit_refunded'      => $credit_refunded,
                'remaining_creditable' => max(0.0, $order_total - $gateway_refunded - $credit_refunded),
                'fully_refunded'       => $is_fully_refunded,
            ]);

        } catch (Throwable $e) {
            return new WP_Error('server_error', 'Unexpected error: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}
