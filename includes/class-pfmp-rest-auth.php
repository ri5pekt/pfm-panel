<?php
// class-pfmp-rest-auth.php
defined('ABSPATH') || exit;

class PFMP_Rest_Auth {

    public function __construct() {
        add_action('init', [$this, 'handle_cors'], 1);
        add_action('init', [$this, 'handle_sso']);
        add_filter('rest_authentication_errors', [$this, 'authenticate_bearer'], 20);
    }

    // -------------------------------------------------------------------------
    // Bearer token auth — overrides WordPress cookie check errors for
    // cross-origin requests from the external panel domain.
    // Priority 20 runs after rest_cookie_check_errors (priority 100 in WP core).
    // -------------------------------------------------------------------------
    public function authenticate_bearer($result) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($auth_header, 'Bearer ')) return $result;

        $token = substr($auth_header, 7);
        $user  = self::validate_token($token);

        if (!$user) {
            return new WP_Error('pfm_invalid_token', 'Invalid or expired token.', ['status' => 401]);
        }

        if (!user_can($user, 'access_pfm_panel') && !user_can($user, 'manage_woocommerce')) {
            return new WP_Error('pfm_forbidden', 'Insufficient permissions.', ['status' => 403]);
        }

        wp_set_current_user($user->ID);
        return true;
    }

    // -------------------------------------------------------------------------
    // CORS — allows the external panel origin to call the WP REST API
    // -------------------------------------------------------------------------
    public function handle_cors(): void {
        $allowed = defined('PFM_PANEL_EXTERNAL_URL') ? PFM_PANEL_EXTERNAL_URL : '';
        if (empty($allowed)) return;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== rtrim($allowed, '/')) return;

        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
    }

    // -------------------------------------------------------------------------
    // SSO handler — triggered by /?pfm_auth=1&redirect_uri=...
    // -------------------------------------------------------------------------
    public function handle_sso(): void {
        if (!isset($_GET['pfm_auth'])) return;

        $redirect_uri = esc_url_raw($_GET['redirect_uri'] ?? '');

        if (!self::is_allowed_redirect_uri($redirect_uri)) {
            wp_die('Invalid redirect URI.', 'PFM Panel', ['response' => 400]);
        }

        if (!is_user_logged_in()) {
            $back_here = home_url(add_query_arg(null, null));
            wp_redirect(wp_login_url($back_here));
            exit;
        }

        $user = wp_get_current_user();

        if (!user_can($user, 'access_pfm_panel') && !user_can($user, 'manage_woocommerce')) {
            wp_die('You do not have permission to access PFM Panel.', 'Access Denied', ['response' => 403]);
        }

        $token = self::generate_token($user);

        wp_redirect(add_query_arg('token', $token, $redirect_uri));
        exit;
    }

    // -------------------------------------------------------------------------
    // Allowed redirect URIs — production URL + localhost when WP_DEBUG is on
    // -------------------------------------------------------------------------
    private static function is_allowed_redirect_uri(string $uri): bool {
        if (empty($uri)) return false;

        $allowed = defined('PFM_PANEL_EXTERNAL_URL') ? PFM_PANEL_EXTERNAL_URL : '';
        if (!empty($allowed) && str_starts_with($uri, rtrim($allowed, '/'))) return true;

        // Always allow localhost (dev machines only — still requires valid WP credentials)
        if (
            str_starts_with($uri, 'http://localhost') ||
            str_starts_with($uri, 'http://127.0.0.1')
        ) {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Token generation — HMAC-signed, 12-hour expiry
    // Payload: user_id, name, roles, exp
    // -------------------------------------------------------------------------
    public static function generate_token(WP_User $user): string {
        $payload = base64_encode(json_encode([
            'user_id' => $user->ID,
            'name'    => $user->display_name,
            'roles'   => $user->roles,
            'exp'     => time() + (12 * HOUR_IN_SECONDS),
        ]));

        $signature = hash_hmac('sha256', $payload, AUTH_KEY . AUTH_SALT);

        return $payload . '.' . $signature;
    }

    // -------------------------------------------------------------------------
    // Token validation — used by PFMP_Utils::can_access_pfm_panel()
    // -------------------------------------------------------------------------
    public static function validate_token(string $token): WP_User|false {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return false;

        [$payload, $signature] = $parts;

        $expected = hash_hmac('sha256', $payload, AUTH_KEY . AUTH_SALT);
        if (!hash_equals($expected, $signature)) return false;

        $data = json_decode(base64_decode($payload), true);
        if (!$data || empty($data['user_id']) || empty($data['exp'])) return false;
        if ($data['exp'] < time()) return false;

        return get_user_by('id', $data['user_id']) ?: false;
    }
}
