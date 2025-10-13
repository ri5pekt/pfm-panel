<?php
// class-pfmp-rest-admin-actions.php
defined('ABSPATH') || exit;

class PFMP_REST_Admin_Actions {
    private $table;

    public function __construct() {
        global $wpdb;
        // Matches your activation hook table name (no prefix there)
        $this->table = 'pfm_admin_actions';
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }

    public function register_rest_api() {
        register_rest_route('pfm-panel/v1', '/admin-actions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_actions'],
            'permission_callback' => [$this, 'can_view_admin_activity'],
            'args'                => [
                'page'          => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page'      => ['default' => 10, 'sanitize_callback' => 'absint'],
                'admin_id'      => ['sanitize_callback' => 'absint'],
                'action_type'   => ['sanitize_callback' => 'sanitize_text_field'],
                'resource_type' => ['sanitize_callback' => 'sanitize_text_field'],
                'date_from'     => ['sanitize_callback' => 'sanitize_text_field'],
                'date_to'       => ['sanitize_callback' => 'sanitize_text_field'],
                'search'        => ['sanitize_callback' => 'sanitize_text_field'], // searches description
            ],
        ]);
    }

    public function can_view_admin_activity() {
        // Reuse your existing permission check; only admins (with admin_rights) see this tab anyway.
        return PFMP_Utils::can_access_pfm_panel();
    }

    public function list_actions(WP_REST_Request $req) {
        global $wpdb;

        $page     = max(1, (int) $req->get_param('page'));
        $per_page = min(100, max(1, (int) $req->get_param('per_page')));
        $offset   = ($page - 1) * $per_page;

        $where   = [];
        $params  = [];

        // Filters
        if ($req->get_param('admin_id')) {
            $where[]  = "admin_id = %d";
            $params[] = (int) $req->get_param('admin_id');
        }
        if ($req->get_param('action_type')) {
            $where[]  = "action_type = %s";
            $params[] = $req->get_param('action_type');
        }
        if ($req->get_param('resource_type')) {
            $where[]  = "resource_type = %s";
            $params[] = $req->get_param('resource_type');
        }
        if ($req->get_param('date_from')) {
            $where[]  = "created_at >= %s";
            $params[] = $req->get_param('date_from') . ' 00:00:00';
        }
        if ($req->get_param('date_to')) {
            $where[]  = "created_at < %s";
            $params[] = date('Y-m-d', strtotime($req->get_param('date_to') . ' +1 day')) . ' 00:00:00';
        }
        if ($req->get_param('search')) {
            $where[]  = "description LIKE %s";
            $params[] = '%' . $wpdb->esc_like($req->get_param('search')) . '%';
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total
        $sql_total = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";
        $total     = (int) $wpdb->get_var($wpdb->prepare($sql_total, $params));

        // Page rows
        $sql_rows = "
            SELECT id, created_at, admin_id, admin_name, action_type, resource_type, description
            FROM {$this->table}
            {$where_sql}
            ORDER BY created_at DESC, id DESC
            LIMIT %d OFFSET %d
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql_rows, array_merge($params, [$per_page, $offset])), ARRAY_A);

        // 👉 Add +5 hours offset to created_at
        foreach ($rows as &$row) {
            if (!empty($row['created_at'])) {
                $dt = new DateTime($row['created_at'], wp_timezone());
                $dt->modify('+5 hours');
                $row['created_at'] = $dt->format('Y-m-d H:i:s');
            }
        }
        unset($row);

        $response = rest_ensure_response($rows ?: []);
        $max_pages = max(1, (int) ceil($total / $per_page));
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', $max_pages);

        return $response;
    }
}
