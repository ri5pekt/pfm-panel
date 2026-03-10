<?php
// class-report-admin-activity.php

defined('ABSPATH') || exit;

class PFMP_Report_Admin_Activity {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = 'pfm_admin_actions';
    }

    public function generate($data) {
        $offset     = intval($data['offset'] ?? 0);
        $limit      = intval($data['chunk_size'] ?? 10);
        $date_from  = $data['date_from'] ?? null;
        $date_to    = $data['date_to'] ?? null;
        $action_type = isset($data['action_type']) ? sanitize_text_field($data['action_type']) : null;
        $resource_type = isset($data['resource_type']) ? sanitize_text_field($data['resource_type']) : null;
        $admin_id   = isset($data['admin_id']) ? intval($data['admin_id']) : null;

        global $wpdb;

        $where   = [];
        $params  = [];

        // Date filters
        if ($date_from) {
            $where[]  = "created_at >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where[]  = "created_at < %s";
            $params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
        }

        // Action type filter
        if ($action_type) {
            $where[]  = "action_type = %s";
            $params[] = $action_type;
        }

        // Resource type filter
        if ($resource_type) {
            $where[]  = "resource_type = %s";
            $params[] = $resource_type;
        }

        // Admin ID filter
        if ($admin_id) {
            $where[]  = "admin_id = %d";
            $params[] = $admin_id;
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Get total count
        $sql_total = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";
        $total = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($sql_total, $params)) : (int) $wpdb->get_var($sql_total);

        // Get chunk of data
        $sql_rows = "
            SELECT id, created_at, admin_id, admin_name, action_type, resource_type, description
            FROM {$this->table}
            {$where_sql}
            ORDER BY created_at DESC, id DESC
            LIMIT %d OFFSET %d
        ";
        $page_params = array_merge($params, [$limit, $offset]);
        $rows_data = $wpdb->get_results($wpdb->prepare($sql_rows, $page_params), ARRAY_A);

        $rows = [];

        if ($offset === 0) {
            $rows[] = self::csv_row([
                'ID',
                'Created At',
                'Admin ID',
                'Admin Name',
                'Action Type',
                'Resource Type',
                'Description',
            ]);
        }

        foreach ($rows_data as $row) {
            // Format created_at with timezone offset (+5 hours)
            $created_at = $row['created_at'];
            if (!empty($created_at)) {
                $dt = new DateTime($created_at, wp_timezone());
                $dt->modify('+5 hours');
                $created_at = $dt->format('Y-m-d H:i:s');
            }

            $rows[] = self::csv_row([
                $row['id'],
                $created_at,
                $row['admin_id'],
                $row['admin_name'],
                $row['action_type'],
                $row['resource_type'],
                $row['description'],
            ]);
        }

        return rest_ensure_response([
            'success'         => true,
            'processed_count' => count($rows_data),
            'remaining'       => max(0, $total - $offset - count($rows_data)),
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
}

