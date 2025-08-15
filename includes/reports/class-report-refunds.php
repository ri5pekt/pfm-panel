<?php
// class-report-refunds.php

defined('ABSPATH') || exit;

class PFMP_Report_Refunds {
    public function generate($data) {
        $offset    = intval($data['offset'] ?? 0);
        $limit     = intval($data['chunk_size'] ?? 10);
        $date_from = $data['date_from'] ?? null;
        $date_to   = $data['date_to'] ?? null;

        $args = [
            'type'   => 'shop_order_refund',
            'limit'  => -1,
            'return' => 'ids',
            'status' => ['wc-completed', 'wc-refunded'],
        ];

        if ($date_from) {
            $from_dt = new DateTime($date_from);
            $from_dt->setTime(0, 0)->modify('+4 hours');
            $args['date_after'] = $from_dt->format('Y-m-d H:i:s');
        }

        if ($date_to) {
            $to_dt = new DateTime($date_to);
            $to_dt->setTime(23, 59, 59)->modify('+4 hours');
            $args['date_before'] = $to_dt->format('Y-m-d H:i:s');
        }

        $all_ids = wc_get_orders($args);
        $total   = count($all_ids);
        $chunk   = array_slice($all_ids, $offset, $limit);

        $rows = [];

        if ($offset === 0) {
            $rows[] = self::csv_row([
                'Refund ID',
                'Parent Order ID',
                'Order Created Date',
                'Order Total',
                'Order Tax',
                'Shipping Country',
                'Shipping State',
                'Shipping City',
                'Shipping Postcode',
                'Shipping Address 1',
                'Shipping Address 2',
                'Refund Created Date',
                'Refund Total',
                'Refunded Tax',
                'Refunded Shipping',
                'Customer Email',
                'Customer Name',
                'Reason'
            ]);
        }

        $rates = self::get_cur_rates();

        foreach ($chunk as $refund_id) {
            $refund = wc_get_order($refund_id);
            if (!$refund) continue;

            $parent_id = $refund->get_parent_id();
            $parent    = wc_get_order($parent_id);

            $order_date = $parent && $parent->get_date_created()
                ? $parent->get_date_created()->date_i18n('Y-m-d H:i:s')
                : '';
            $order_total = $parent ? $parent->get_total() : '';
            $order_tax   = $parent ? $parent->get_total_tax() : '';

            $refund_date = $refund->get_date_created()
                ? $refund->get_date_created()->date_i18n('Y-m-d H:i:s')
                : '';

            $refunded_total    = abs($refund->get_total());
            $refunded_tax      = 0;
            $refunded_shipping = 0;

            foreach ($refund->get_items('shipping') as $item) {
                $refunded_shipping += abs($item->get_total());
            }

            foreach ($refund->get_items('tax') as $item) {
                $refunded_tax += abs($item->get_tax_total());
            }

            $currency = $refund->get_currency();
            $rate     = self::get_rate($currency, $rates);

            if ($rate > 0) {
                $refunded_total    = $refunded_total / $rate;
                $refunded_tax      = $refunded_tax / $rate;
                $refunded_shipping = $refunded_shipping / $rate;
            }

            $rows[] = self::csv_row([
                $refund_id,
                $parent_id,
                $order_date,
                $order_total,
                $order_tax,
                $parent ? $parent->get_shipping_country() : '',
                $parent ? $parent->get_shipping_state() : '',
                $parent ? $parent->get_shipping_city() : '',
                $parent ? $parent->get_shipping_postcode() : '',
                $parent ? $parent->get_shipping_address_1() : '',
                $parent ? $parent->get_shipping_address_2() : '',
                $refund_date,
                $refunded_total,
                $refunded_tax,
                $refunded_shipping,
                $parent ? $parent->get_billing_email() : '',
                $parent ? trim($parent->get_billing_first_name() . ' ' . $parent->get_billing_last_name()) : '',
                $refund->get_reason()
            ]);
        }

        return rest_ensure_response([
            'success'         => true,
            'processed_count' => count($chunk),
            'remaining'       => max(0, $total - $offset - count($chunk)),
            'data'            => $rows,
        ]);
    }

    private static function csv_row($fields) {
        return implode(',', array_map([self::class, 'csv_escape'], $fields));
    }

    private static function csv_escape($value) {
        $escaped = str_replace('"', '""', (string)$value);
        return '"' . $escaped . '"';
    }

    private static function get_cur_rates() {
        $last_updated = get_option('export_stats_rates_last_updated');
        $today        = date('Y-m-d');

        if ($last_updated === $today) {
            $rates = json_decode(get_option('export_stats_currency_rates'));
        } else {
            $response = wp_remote_get('https://v6.exchangerate-api.com/v6/871e5e2ef51033185690c90e/latest/USD');
            if (is_wp_error($response)) return null;

            $rates = json_decode(wp_remote_retrieve_body($response));
            update_option('export_stats_currency_rates', json_encode($rates));
            update_option('export_stats_rates_last_updated', $today);
        }

        return $rates;
    }

    private static function get_rate($currencyCode, $currencyRates) {
        return $currencyRates->conversion_rates->$currencyCode ?? null;
    }
}
