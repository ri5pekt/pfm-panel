<?php

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
            '77.124.27.149',
            '77.127.100.144'
        ];

        if (in_array($remote_ip, $whitelisted_ips)) {
            return true;
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (current_user_can('manage_woocommerce')) {
                return true;
            }
        }

        return false;
    }
}
