<?php
// class-pfmp-utils.php
defined('ABSPATH') || exit;

class PFMP_Utils {
    public static function log($data) {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        global $wp_filesystem;

        $plugin_dir = plugin_dir_path(__FILE__);
        $log_file = $plugin_dir . 'my_log.txt';

        $current_time = current_time('Y-m-d H:i:s');

        // Format the data appropriately
        if (is_array($data) || is_object($data)) {
            $formatted_data = print_r($data, true);
        } else {
            $formatted_data = (string)$data;
        }

        $log_entry = "[{$current_time}] {$formatted_data}\n";

        if ($wp_filesystem->exists($log_file)) {
            $existing_content = $wp_filesystem->get_contents($log_file);
            $wp_filesystem->put_contents($log_file, $existing_content . $log_entry);
        } else {
            $wp_filesystem->put_contents($log_file, $log_entry);
        }
    }


    public static function can_access_pfm_panel() {
        $remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $whitelisted_ips = [
            '199.203.127.85',
            '77.127.100.144'
        ];

        $result = false;
        $reason = '';

        if (in_array($remote_ip, $whitelisted_ips)) {
            $result = true;
            //$reason = 'Whitelisted IP';
        } elseif (is_user_logged_in()) {
            //$user = wp_get_current_user();
            if (current_user_can('manage_woocommerce') || current_user_can('access_pfm_panel')) {
                $result = true;
                //$reason = 'User has manage_woocommerce capability';
            } else {
                //$reason = 'User logged in but lacks capability';
            }
        } else {
            //$reason = 'Not logged in and IP not whitelisted';
        }

        // Log attempt
        /*
        self::log([
            'ip'      => $remote_ip,
            'time'    => current_time('Y-m-d H:i:s'),
            'result'  => $result ? 'ACCESS GRANTED' : 'ACCESS DENIED',
            'reason'  => $reason,
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
        ]);
        */
        return $result;
    }


    public static function log_admin_action($action_type, $resource_type, $description) {
        // Table name (you chose no prefix)
        $table = 'pfm_admin_actions';

        // Resolve current user (falls back to "System")
        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $admin_id   = ($user && isset($user->ID)) ? (int) $user->ID : 0;
        $admin_name = ($user && method_exists($user, 'exists') && $user->exists())
            ? ($user->display_name ?: 'Unknown')
            : 'System';

        // Sanitize inputs
        $action_type   = sanitize_key($action_type);
        $resource_type = sanitize_key($resource_type);
        $description   = wp_strip_all_tags((string) $description);

        global $wpdb;

        // Quick existence check once per request (optional but helpful)
        static $table_checked = false;
        if (!$table_checked) {
            $maybe = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
                $table
            ));
            $table_checked = true;
            if (!$maybe) {
                // Avoid fataling if table isn't there yet
                error_log("[pfm_admin_actions] Table missing. Skipping log for {$action_type}/{$resource_type}.");
                return false;
            }
        }

        $data = [
            'created_at'    => current_time('mysql'),
            'admin_id'      => $admin_id,
            'admin_name'    => $admin_name,
            'action_type'   => $action_type,
            'resource_type' => $resource_type,
            'description'   => $description,
        ];
        $formats = ['%s', '%d', '%s', '%s', '%s', '%s'];

        $ok = $wpdb->insert($table, $data, $formats);

        if ($ok === false) {
            // Don’t throw in runtime paths—just log
            error_log('[pfm_admin_actions] Insert failed: ' . $wpdb->last_error);
            return false;
        }
        return (int) $wpdb->insert_id;
    }
}
