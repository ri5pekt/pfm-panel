<?php
// class-pfm-panel-plugin.php
class PFM_Panel_Plugin {

    private static $allowed_origins = [
        'https://panel.pfm-qa.com',
    ];

    public function __construct() {
        new PFMP_Admin();
        new PFMP_REST_Orders();
        new PFMP_REST_Refunds();
        new PFMP_REST_Stats();
        new PFMP_REST_Subscriptions();
        new PFMP_REST_Customers();
        new PFMP_REST_Replacements();
        new PFMP_REST_Reports();
        new PFMP_REST_Admin_Actions();
        new PFMP_REST_Coupons();

        add_action('init', [$this, 'handle_cors_preflight']);
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 3);
        add_action('rest_api_init', [$this, 'register_me_route']);
    }

    public function handle_cors_preflight() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') return;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!in_array($origin, self::$allowed_origins, true)) return;
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        header('Access-Control-Max-Age: 86400');
        status_header(200);
        exit;
    }

    public function add_cors_headers($served, $result, $request) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!in_array($origin, self::$allowed_origins, true)) return $served;
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        return $served;
    }

    public function register_me_route() {
        register_rest_route('pfm-panel/v1', '/me', [
            'methods'             => 'GET',
            'callback'            => [$this, 'me_callback'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);
    }

    public function me_callback() {
        $user  = wp_get_current_user();
        $roles = array_values($user->roles);
        if (in_array('super_editor', $roles, true)) {
            $roles[] = 'administrator';
        }
        return [
            'id'         => $user->ID,
            'username'   => $user->user_login,
            'full_name'  => $user->display_name,
            'first_name' => $user->user_firstname,
            'last_name'  => $user->user_lastname,
            'roles'      => $roles,
        ];
    }
}


register_activation_hook(__FILE__, 'pfmp_create_admin_actions_table');

function pfmp_create_admin_actions_table() {
    global $wpdb;
    $table_name = 'pfm_admin_actions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        admin_id BIGINT UNSIGNED NOT NULL,
        admin_name VARCHAR(255) NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        resource_type VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        PRIMARY KEY (id),
        INDEX idx_created_at (created_at),
        INDEX idx_admin_id (admin_id),
        INDEX idx_action_type (action_type),
        INDEX idx_resource_type (resource_type)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
