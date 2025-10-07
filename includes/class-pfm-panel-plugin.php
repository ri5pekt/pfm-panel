<?php
// class-pfm-panel-plugin.php
class PFM_Panel_Plugin {
    public function __construct() {
        new PFMP_Admin();
        new PFMP_REST_Orders();
        new PFMP_REST_Stats();
        new PFMP_REST_Subscriptions();
        new PFMP_REST_Customers();
        new PFMP_REST_Replacements();
        new PFMP_REST_Reports();
        new PFMP_REST_Admin_Actions();
        new PFMP_REST_Coupons();
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
