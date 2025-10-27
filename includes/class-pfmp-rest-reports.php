<?php
// class-pfmp-rest-reports.php
defined('ABSPATH') || exit;

class PFMP_REST_Reports {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('pfm-panel/v1', '/reports/run', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'run_report'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/reports/upload', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload_report'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);

        register_rest_route('pfm-panel/v1', '/reports/history', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_report_history'],
            'permission_callback' => ['PFMP_Utils', 'can_access_pfm_panel'],
        ]);
    }

    public function run_report(WP_REST_Request $request) {
        $data = $request->get_json_params();
        $type = sanitize_text_field($data['report_type'] ?? '');

        switch ($type) {
            case 'orders-to-priority':
                require_once __DIR__ . '/reports/class-report-orders-to-priority.php';
                $report = new PFMP_Report_Orders_To_Priority();
                return $report->generate($data);

            case 'refunds':
                require_once __DIR__ . '/reports/class-report-refunds.php';
                $report = new PFMP_Report_Refunds();
                return $report->generate($data);

            case 'taxes-verification':
                require_once __DIR__ . '/reports/class-report-taxes-verification.php';
                $report = new PFMP_Report_Taxes_Verification();
                return $report->generate($data);

            case 'refunds-verification': // NEW
                require_once __DIR__ . '/reports/class-report-refunds-verification.php';
                $report = new PFMP_Report_Refunds_Verification();
                return $report->generate($data);

            case 'export-to-narvar':
                require_once __DIR__ . '/reports/class-report-export-to-narvar.php';
                $report = new PFMP_Report_Export_To_Narvar();
                return $report->generate($data);
            default:
                return new WP_Error('unsupported_report', 'Unsupported report type.', ['status' => 400]);
        }
    }

    public function upload_report(WP_REST_Request $request) {
        $params   = $request->get_json_params();
        $type     = sanitize_text_field($params['report_type'] ?? '');
        $content  = $params['file_content'] ?? '';
        $ext      = sanitize_text_field($params['extension'] ?? 'txt');

        if (!$type || !$content) {
            return new WP_Error('invalid_data', 'Missing report type or content.', ['status' => 400]);
        }

        // Build filename context (ids or dates)
        $raw_payload = $request->get_json_params();
        $order_ids   = $raw_payload['order_ids'] ?? [];
        $date_from   = $raw_payload['date_from'] ?? null;
        $date_to     = $raw_payload['date_to'] ?? null;

        $filename_prefix = 'report';
        $filename_middle = '';

        if (!empty($order_ids)) {
            $short_ids       = array_slice(array_map('intval', $order_ids), 0, 2);
            $filename_prefix = 'by-ids';
            $filename_middle = implode('_', $short_ids);
        } elseif ($date_from || $date_to) {
            $date_from       = $date_from ?: 'start';
            $date_to         = $date_to ?: 'end';
            $filename_prefix = 'by-date';
            $filename_middle = "{$date_from}_to_{$date_to}";
        }

        $wp_uploads = wp_upload_dir();
        $upload_dir = trailingslashit($wp_uploads['basedir']) . 'reports/' . sanitize_file_name($type) . '/';
        $upload_url = trailingslashit($wp_uploads['baseurl']) . 'reports/' . sanitize_file_name($type) . '/';

        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        $base_name = "{$filename_prefix}_{$filename_middle}";
        $filename  = $base_name . '.' . $ext;
        $counter   = 1;
        while (file_exists($upload_dir . $filename)) {
            $filename = "{$base_name} ({$counter}).{$ext}";
            $counter++;
        }

        $path     = $upload_dir . $filename;
        $content  = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $handle   = fopen($path, 'w');

        // UTF-8 BOM for Excel/Windows friendliness (Hebrew, accents)
        fwrite($handle, "\xEF\xBB\xBF");
        fwrite($handle, $content);
        fclose($handle);

        return rest_ensure_response([
            'success'      => true,
            'filename'     => $filename,
            'download_url' => $upload_url . $filename,
        ]);
    }

    public function get_report_history(WP_REST_Request $request) {
        $type = sanitize_text_field($request->get_param('report_type'));
        if (!$type) {
            return new WP_Error('missing_param', 'Missing report_type', ['status' => 400]);
        }

        $wp_uploads = wp_upload_dir();
        $upload_dir = trailingslashit($wp_uploads['basedir']) . 'reports/' . sanitize_file_name($type) . '/';
        $upload_url = trailingslashit($wp_uploads['baseurl']) . 'reports/' . sanitize_file_name($type) . '/';

        if (!file_exists($upload_dir)) {
            return rest_ensure_response([]);
        }

        $files = array_merge(
            glob($upload_dir . '*.txt') ?: [],
            glob($upload_dir . '*.csv') ?: []
        );

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $result = array_map(function ($file) use ($upload_url) {
            return [
                'filename'    => basename($file),
                'download_url' => $upload_url . basename($file),
                'created_at'  => date('c', filemtime($file)),
            ];
        }, $files);

        return rest_ensure_response($result);
    }
}
